/**
 * ORDER FLOW 30-DEPTH HEADLESS DAEMON
 * 
 * Streams live 30-depth order book and trade prints from Upstox Market Data Feed V3 directly,
 * computes Flow Intelligence, CVD, Whales, FOMO cascades, and S/R anchors,
 * and persists real-time snapshots and trades to MySQL database without needing any browser open!
 */

import WebSocket from 'ws';
import https from 'https';
import fs from 'fs';
import path from 'path';
import protobuf from 'protobufjs';
import mysql from 'mysql2/promise';
import dotenv from 'dotenv';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');

// Load environment variables
dotenv.config({ path: path.join(rootDir, '.env') });

// Configuration
const CONFIG = {
    symbol: process.env.ORDER_FLOW_SYMBOL || 'NIFTY',
    indexKey: 'NSE_INDEX|Nifty 50',
    minTradeQty: 500,
    maxTradeQty: 35000,
    minWhaleQty: 8000,
    minFomoQty: 1800,
    dominanceThreshold: 2.5,
    snapshotIntervalMs: 60000, // 60s periodic snapshot
    tradeBatchFlushMs: 3000,   // Flush trades every 3s
    reconnectDelayMs: 5000,
};

// Database Connection Pool
const pool = mysql.createPool({
    host: process.env.DB_HOST || '127.0.0.1',
    port: parseInt(process.env.DB_PORT || '3306'),
    user: process.env.DB_USERNAME || 'oiohlc',
    password: process.env.DB_PASSWORD || 'oiohlc',
    database: process.env.DB_DATABASE || 'oiohlc',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0,
});

// Running State
const state = {
    activeInstrumentKey: null,
    activeTradingSymbol: null,
    protobufRoot: null,
    FeedResponse: null,
    ws: null,
    wsUrl: null,
    reconnectTimer: null,
    isReconnecting: false,
    
    // Market Data
    ltp: null,
    prevLtp: null,
    indexLtp: null,
    atp: null,
    vtt: 0,
    prevVtt: 0,
    tbq: 0,
    tsq: 0,
    bestBid: null,
    bestAsk: null,
    bids: [],
    asks: [],
    microPrice: null,

    // Cumulative Clusters & CVD
    cvd: 0,
    allAskClusters: new Map(), // price -> qty
    allBidClusters: new Map(), // price -> qty
    recentTrades: [],          // Last 100 trades for Whale & FOMO detection

    // Institutional Anchors
    tdSup: null,
    tdRes: null,
    ydSup: null,
    ydRes: null,

    // Intelligence Metrics
    bias: 'BALANCED',
    dominanceRatio: 1.0,
    netVolume: 0,
    convictionScore: 50,
    regime: 'EQUILIBRIUM_CHOP',
    target1: null,
    target2: null,
    concreteSl: null,
    tradeStatus: 'FRESH',
    whaleStatus: null,
    fomoStatus: null,
    lastTriggerPrice: null,

    // Narrative & Playbook
    pastStory: '',
    presentStory: '',
    futureStory: '',
    playbook: '',

    // Batch buffers
    pendingTrades: [],
    lastSnapshotAt: 0,
    lastLoggedBias: null,
};

// Utility Helpers
function getTodayDateStr() {
    const d = new Date();
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

function getTimeStr(d = new Date()) {
    return d.toTimeString().split(' ')[0]; // HH:MM:SS
}

function formatQtyAbbr(val) {
    const n = Math.abs(Number(val) || 0);
    if (n >= 10000000) return (n / 10000000).toFixed(1) + 'Cr';
    if (n >= 100000) return (n / 100000).toFixed(1) + 'L';
    if (n >= 1000) return (n / 1000).toFixed(1) + 'k';
    return String(Math.round(n));
}

// 1. Fetch Upstox Analytics Token
function getUpstoxToken() {
    if (process.env.UPSTOX_ANALYTICS_TOKEN) {
        return process.env.UPSTOX_ANALYTICS_TOKEN;
    }
    try {
        const configStr = fs.readFileSync(path.join(rootDir, 'config/services.php'), 'utf8');
        const match = configStr.match(/'analytics_token'\s*=>\s*'([^']+)'/);
        if (match && match[1]) return match[1];
    } catch (e) {
        console.warn('⚠️ Could not read config/services.php for Upstox token:', e.message);
    }
    return null;
}

// 2. Authorize WebSocket with Upstox API
async function getAuthorizedWsUrl(token) {
    return new Promise((resolve, reject) => {
        const req = https.request('https://api.upstox.com/v3/feed/market-data-feed/authorize', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            timeout: 8000
        }, (res) => {
            let body = '';
            res.on('data', chunk => body += chunk);
            res.on('end', () => {
                try {
                    const json = JSON.parse(body);
                    const uri = json?.data?.authorizedRedirectUri || json?.authorizedRedirectUri;
                    if (uri) resolve(uri);
                    else reject(new Error('No authorizedRedirectUri returned: ' + body));
                } catch (err) {
                    reject(err);
                }
            });
        });

        req.on('error', reject);
        req.on('timeout', () => {
            req.destroy();
            reject(new Error('Upstox WebSocket authorize timeout'));
        });
        req.end();
    });
}

// 3. Resolve Current Active NIFTY Future Contract from MySQL
async function resolveActiveInstrument() {
    try {
        const [rows] = await pool.execute(
            `SELECT instrument_key, trading_symbol, expiry FROM instruments 
             WHERE name = ? AND instrument_type = 'FUT' 
             ORDER BY expiry ASC LIMIT 1`,
            [CONFIG.symbol]
        );

        if (rows && rows.length > 0) {
            state.activeInstrumentKey = rows[0].instrument_key;
            state.activeTradingSymbol = rows[0].trading_symbol;
            console.log(`🎯 Active Future Contract: ${state.activeTradingSymbol} (${state.activeInstrumentKey})`);
        } else {
            // Fallback
            state.activeInstrumentKey = 'NSE_FO|61471';
            state.activeTradingSymbol = 'NIFTY FUT';
            console.warn(`⚠️ Instruments table empty. Using fallback contract: ${state.activeInstrumentKey}`);
        }
    } catch (e) {
        console.error('Error resolving active contract:', e.message);
        state.activeInstrumentKey = 'NSE_FO|61471';
    }
}

// 4. Fetch Yesterday's Anchors (Top Ask / Demand & Top Bid / Supply) from MySQL
async function fetchYesterdayAnchors() {
    try {
        const today = getTodayDateStr();
        // Top Ask (Demand)
        const [askRows] = await pool.execute(
            `SELECT price, quantity FROM order_flow_traded_volumes 
             WHERE trade_date < ? AND symbol = ? AND side = 'ASK' AND trade_time > '09:30:00'
             ORDER BY quantity DESC LIMIT 1`,
            [today, CONFIG.symbol]
        );
        if (askRows && askRows.length > 0) {
            state.ydSup = { price: Number(askRows[0].price), qty: Number(askRows[0].quantity) };
        }

        // Top Bid (Supply)
        const [bidRows] = await pool.execute(
            `SELECT price, quantity FROM order_flow_traded_volumes 
             WHERE trade_date < ? AND symbol = ? AND side = 'BID' AND trade_time > '09:30:00'
             ORDER BY quantity DESC LIMIT 1`,
            [today, CONFIG.symbol]
        );
        if (bidRows && bidRows.length > 0) {
            state.ydRes = { price: Number(bidRows[0].price), qty: Number(bidRows[0].quantity) };
        }

        console.log(`⚓ Institutional Anchors Loaded: YD SUP = ${state.ydSup?.price ?? '--'}, YD RES = ${state.ydRes?.price ?? '--'}`);
    } catch (e) {
        console.warn('Could not load yesterday anchors:', e.message);
    }
}

// 5. Initialize Protobuf
async function initProtobuf() {
    const protoPath = path.join(rootDir, 'public/MarketDataFeed_v3.proto');
    state.protobufRoot = await protobuf.load(protoPath);
    state.FeedResponse = state.protobufRoot.lookupType('com.upstox.marketdatafeederv3udapi.rpc.proto.FeedResponse');
    console.log('✅ Protobuf schema loaded successfully.');
}

// 6. Connect to Upstox WebSocket Feed
async function connectFeed() {
    if (state.reconnectTimer) {
        clearTimeout(state.reconnectTimer);
        state.reconnectTimer = null;
    }

    try {
        const token = getUpstoxToken();
        if (!token) {
            throw new Error('Upstox analytics token not found');
        }

        console.log('🔄 Authorizing Upstox 30-depth WebSocket...');
        state.wsUrl = await getAuthorizedWsUrl(token);
        console.log('🔗 WebSocket URL obtained.');

        state.ws = new WebSocket(state.wsUrl);
        state.isReconnecting = true;

        state.ws.on('open', () => {
            console.log('🚀 WebSocket Connected to Upstox 30-depth feed!');
            state.isReconnecting = true;
            subscribeStream();
        });

        state.ws.on('message', (data) => {
            handleBinaryFeed(data);
        });

        state.ws.on('close', (code, reason) => {
            console.warn(`⚠️ WebSocket closed (code ${code}: ${reason || 'no reason'}). Reconnecting in ${CONFIG.reconnectDelayMs / 1000}s...`);
            scheduleReconnect();
        });

        state.ws.on('error', (err) => {
            console.error('❌ WebSocket error:', err.message);
            // close event will trigger reconnect
        });

    } catch (err) {
        console.error('Failed to establish feed:', err.message);
        scheduleReconnect();
    }
}

function scheduleReconnect() {
    if (state.reconnectTimer) return;
    state.reconnectTimer = setTimeout(() => {
        state.reconnectTimer = null;
        connectFeed();
    }, CONFIG.reconnectDelayMs);
}

// 7. Subscribe to full_d30
function subscribeStream() {
    if (!state.ws || state.ws.readyState !== WebSocket.OPEN) return;
    const subMsg = {
        guid: `headless_d30_${Date.now()}`,
        method: 'sub',
        data: {
            mode: 'full_d30',
            instrumentKeys: [state.activeInstrumentKey, CONFIG.indexKey]
        }
    };
    state.ws.send(JSON.stringify(subMsg));
    console.log(`📡 Subscribed to [${state.activeInstrumentKey}, ${CONFIG.indexKey}] in full_d30 mode.`);
}

// 8. Process Binary Protobuf Frame
function handleBinaryFeed(buffer) {
    if (!buffer || buffer.length === 0) return;
    // Skip JSON ping/handshake frames (ASCII 123 = '{')
    if (buffer[0] === 123) return;

    try {
        const decoded = state.FeedResponse.decode(buffer);
        const obj = state.FeedResponse.toObject(decoded, { enums: String, bytes: String });
        if (!obj || !obj.feeds) return;

        // Process Index Feed (Spot LTP)
        if (obj.feeds[CONFIG.indexKey]) {
            const idxFeed = obj.feeds[CONFIG.indexKey];
            const idxLtp = idxFeed.fullFeed?.indexFF?.ltpc?.ltp ?? idxFeed.ltpc?.ltp;
            if (idxLtp) state.indexLtp = Number(idxLtp);
        }

        // Process Active Target Feed (NIFTY Future 30-depth)
        if (obj.feeds[state.activeInstrumentKey]) {
            const targetFeed = obj.feeds[state.activeInstrumentKey];
            parseTargetFeed(targetFeed);
        }

    } catch (e) {
        console.warn('Decode frame error:', e.message);
    }
}

// 9. Parse Market Feed & Extract 30-depth
function parseTargetFeed(feed) {
    const mff = feed.fullFeed?.marketFF;
    if (!mff) return;

    const ltp = mff.ltpc?.ltp ? parseFloat(mff.ltpc.ltp) : state.ltp;
    const atp = mff.atp ? parseFloat(mff.atp) : state.atp;
    const vtt = mff.vtt ? parseInt(mff.vtt) : state.vtt;
    const tbq = mff.tbq ? parseFloat(mff.tbq) : state.tbq;
    const tsq = mff.tsq ? parseFloat(mff.tsq) : state.tsq;

    const quotes = mff.marketLevel?.bidAskQuote || [];
    const bids = [];
    const asks = [];

    quotes.forEach(q => {
        if (q.bidP && q.bidP > 0) bids.push({ price: parseFloat(q.bidP), qty: parseInt(q.bidQ || 0) });
        if (q.askP && q.askP > 0) asks.push({ price: parseFloat(q.askP), qty: parseInt(q.askQ || 0) });
    });

    bids.sort((a, b) => b.price - a.price);
    asks.sort((a, b) => a.price - b.price);

    applyDepthUpdate(bids, asks, ltp, atp, vtt, tbq, tsq);
}

// 10. Core Order Flow Ingestion & Calculations
function applyDepthUpdate(bids, asks, ltp, atp, vtt, tbq, tsq) {
    state.bids = bids;
    state.asks = asks;
    state.prevLtp = state.ltp;
    state.ltp = ltp;
    state.atp = atp;
    state.tbq = tbq;
    state.tsq = tsq;

    const bestBid = bids.length > 0 ? bids[0].price : (ltp ? ltp - 0.05 : null);
    const bestAsk = asks.length > 0 ? asks[0].price : (ltp ? ltp + 0.05 : null);
    state.bestBid = bestBid;
    state.bestAsk = bestAsk;

    // Micro-price calculation
    if (bids.length > 0 && asks.length > 0) {
        const topBidQ = bids[0].qty || 1;
        const topAskQ = asks[0].qty || 1;
        state.microPrice = ((bestBid * topAskQ) + (bestAsk * topBidQ)) / (topBidQ + topAskQ);
    } else {
        state.microPrice = ltp;
    }

    // Baseline resync on reconnect to prevent ghost volume spikes
    if (state.isReconnecting) {
        state.prevVtt = vtt;
        state.vtt = vtt;
        state.isReconnecting = false;
        return;
    }

    // Trade derivation from volume traded today (VTT)
    const deltaV = vtt - state.vtt;
    state.prevVtt = state.vtt;
    state.vtt = vtt;

    if (deltaV >= CONFIG.minTradeQty && deltaV <= CONFIG.maxTradeQty && ltp) {
        let side = 'ASK';
        if (bestAsk && ltp >= bestAsk) {
            side = 'ASK'; // Aggressive buyer lifting offer
            state.cvd += deltaV;
        } else if (bestBid && ltp <= bestBid) {
            side = 'BID'; // Aggressive seller hitting bid
            state.cvd -= deltaV;
        } else {
            // Inside spread: check micro-price drift
            const midPrice = (bestBid && bestAsk) ? (bestBid + bestAsk) / 2 : ltp;
            if (state.microPrice > midPrice) {
                side = 'ASK';
                state.cvd += Math.round(deltaV * 0.7);
            } else {
                side = 'BID';
                state.cvd -= Math.round(deltaV * 0.7);
            }
        }

        recordTradePrint(side, ltp, deltaV);
    }

    // Refresh Flow Intelligence state
    computeFlowIntelligence();
}

// 11. Record Trade Print & Cluster Tracking
function recordTradePrint(side, price, qty) {
    const timeStr = getTimeStr();
    const today = getTodayDateStr();
    const roundP = Math.round(price * 10) / 10;

    // Buffer for MySQL persistence
    state.pendingTrades.push({
        trade_date: today,
        trade_time: timeStr,
        symbol: CONFIG.symbol,
        instrument: state.activeTradingSymbol,
        side: side,
        price: roundP,
        quantity: qty,
        source: 'HEADLESS_DAEMON',
    });

    // In-memory cluster accumulation
    const clusterMap = (side === 'ASK') ? state.allAskClusters : state.allBidClusters;
    clusterMap.set(roundP, (clusterMap.get(roundP) || 0) + qty);

    // Keep circular buffer of recent trades for Whale & FOMO
    state.recentTrades.unshift({ time: timeStr, price: roundP, qty: qty, side: side });
    if (state.recentTrades.length > 100) state.recentTrades.pop();

    // Check Whale alert (>= 8000 qty)
    if (qty >= CONFIG.minWhaleQty) {
        handleWhaleAlert(side, roundP, qty, timeStr);
    }
}

// 12. Handle Whale Alert & Persist to order_flow_alert_logs
async function handleWhaleAlert(side, price, qty, timeStr) {
    try {
        const today = getTodayDateStr();
        const title = `WHALE ${side === 'ASK' ? 'BUY' : 'SELL'}: ${formatQtyAbbr(qty)} at ₹${price.toFixed(1)}`;
        console.log(`🐳 ${title}`);

        await pool.execute(
            `INSERT INTO order_flow_alert_logs 
             (mode, trade_date, alert_time, symbol, instrument, alert_type, side, price, quantity, title, guidance, sound, created_at, updated_at)
             VALUES ('live', ?, ?, ?, ?, 'WHALE_RADAR', ?, ?, ?, ?, ?, 'whale_bell', NOW(), NOW())`,
            [today, timeStr, CONFIG.symbol, state.activeTradingSymbol, side, price, qty, title, 'Track absorption vs displacement']
        );
    } catch (e) {
        console.warn('Error inserting whale alert:', e.message);
    }
}

// 13. Compute Flow Intelligence, Conviction, Targets & Playbook
function computeFlowIntelligence() {
    const ltp = state.ltp;
    if (!ltp) return;

    // Sort clusters to find Top 1 anchors
    const askList = Array.from(state.allAskClusters.entries())
        .map(([price, totalQty]) => ({ price, totalQty }))
        .sort((a, b) => b.totalQty - a.totalQty);

    const bidList = Array.from(state.allBidClusters.entries())
        .map(([price, totalQty]) => ({ price, totalQty }))
        .sort((a, b) => b.totalQty - a.totalQty);

    state.tdSup = askList[0] || null; // Today Demand Anchor
    state.tdRes = bidList[0] || null; // Today Supply Anchor

    const totalAskVol = askList.reduce((sum, x) => sum + x.totalQty, 0);
    const totalBidVol = bidList.reduce((sum, x) => sum + x.totalQty, 0);
    const netVol = totalAskVol - totalBidVol;
    state.netVolume = netVol;

    // Dominance & Bias
    let ratioVal = 1.0;
    const isBullish = totalAskVol >= totalBidVol;
    if (isBullish) {
        ratioVal = totalBidVol > 0 ? (totalAskVol / totalBidVol) : (totalAskVol > 0 ? 99.0 : 1.0);
    } else {
        ratioVal = totalAskVol > 0 ? (totalBidVol / totalAskVol) : (totalBidVol > 0 ? 99.0 : 1.0);
    }
    state.dominanceRatio = Math.round(ratioVal * 100) / 100;

    const isDirectional = ratioVal >= CONFIG.dominanceThreshold;
    const currentBias = isDirectional ? (isBullish ? 'BUY' : 'SELL') : 'BALANCED';
    state.bias = currentBias;

    // Whale displacement bonus
    let whaleBonusScore = 0;
    state.whaleStatus = 'Orderly institutional flow';
    const latestWhale = state.recentTrades.find(t => t.qty >= CONFIG.minWhaleQty);
    if (latestWhale) {
        const diffWhale = ltp - latestWhale.price;
        const isWhaleBuy = (latestWhale.side === 'ASK');
        if (isWhaleBuy) {
            if (diffWhale >= 2.5) {
                whaleBonusScore = +18;
                state.whaleStatus = `TAKING THE RIDE: ${formatQtyAbbr(latestWhale.qty)} Buy at ₹${latestWhale.price} (+${diffWhale.toFixed(1)} pts)`;
            } else if (diffWhale <= -1.5) {
                whaleBonusScore = -15; // Bull Trap
                state.whaleStatus = `SUNK IN SHIP (BULL TRAP): ${formatQtyAbbr(latestWhale.qty)} Buy absorbed at ₹${latestWhale.price}`;
            }
        } else {
            if (diffWhale <= -2.5) {
                whaleBonusScore = +18;
                state.whaleStatus = `WATERFALL SELLING: ${formatQtyAbbr(latestWhale.qty)} Sell at ₹${latestWhale.price} (-${Math.abs(diffWhale).toFixed(1)} pts)`;
            } else if (diffWhale >= 1.5) {
                whaleBonusScore = +22; // Bear Trap = Short squeeze fuel!
                state.whaleStatus = `SUNK IN SHIP (BEAR TRAP): ${formatQtyAbbr(latestWhale.qty)} Sell absorbed at ₹${latestWhale.price}! Short squeeze primed!`;
            }
        }
    }

    // FOMO & Herd Radar
    let fomoBonusScore = 0;
    state.fomoStatus = 'Execution pace orderly';
    const recentBlocks = state.recentTrades.filter(t => t.qty >= CONFIG.minFomoQty).slice(0, 15);
    let fomoBuyCount = 0;
    let fomoSellCount = 0;
    recentBlocks.forEach(t => {
        if (Math.abs(t.price - ltp) <= 2.8) {
            if (t.side === 'ASK') fomoBuyCount++;
            else fomoSellCount++;
        }
    });

    if (fomoBuyCount >= 3) {
        fomoBonusScore = (currentBias === 'BUY') ? +15 : -8;
        state.fomoStatus = `FOMO BUY CASCADE: ${fomoBuyCount} block buys within 2.8 pts`;
    } else if (fomoSellCount >= 3) {
        fomoBonusScore = (currentBias === 'SELL') ? +15 : +12;
        state.fomoStatus = `PANIC CAPITULATION: ${fomoSellCount} block sells hitting Bid`;
    }

    // Session clock modifier
    const now = new Date();
    const hm = now.getHours() * 100 + now.getMinutes();
    let clockScoreMod = 0;
    if (hm >= 1145 && hm <= 1245) clockScoreMod = -12; // Lunch chop
    else if (hm >= 1315 && hm <= 1515) clockScoreMod = +8; // Afternoon squeeze

    // Conviction Score
    let baseScore = 40;
    if (ratioVal >= 3.5) baseScore += 28;
    else if (ratioVal >= 2.5) baseScore += 22;
    else if (ratioVal >= 1.8) baseScore += 14;
    else if (ratioVal >= 1.3) baseScore += 8;

    if (Math.abs(netVol) >= 1000000) baseScore += 18;
    else if (Math.abs(netVol) >= 500000) baseScore += 12;
    else if (Math.abs(netVol) >= 200000) baseScore += 8;

    state.convictionScore = Math.min(96, Math.max(35, Math.round(baseScore + whaleBonusScore + fomoBonusScore + clockScoreMod)));

    // Regime classification
    if (currentBias === 'BUY' && state.convictionScore >= 75) state.regime = 'BULLISH_SQUEEZE';
    else if (currentBias === 'SELL' && state.convictionScore >= 75) state.regime = 'BEARISH_EXPANSION';
    else state.regime = 'EQUILIBRIUM_CHOP';

    // Target Trajectory & Stop Loss
    const pTdSup = state.tdSup ? state.tdSup.price : null;
    const pTdRes = state.tdRes ? state.tdRes.price : null;

    if (currentBias === 'BUY') {
        state.target1 = (pTdRes !== null && pTdRes > ltp) ? pTdRes : (ltp + 25);
        state.target2 = state.target1 + 22;
        state.concreteSl = (pTdSup !== null && pTdSup <= ltp) ? (pTdSup - 5.5) : (ltp - 14.0);
    } else if (currentBias === 'SELL') {
        state.target1 = (pTdSup !== null && pTdSup < ltp) ? pTdSup : (ltp - 25);
        state.target2 = state.target1 - 22;
        state.concreteSl = (pTdRes !== null && pTdRes >= ltp) ? (pTdRes + 5.5) : (ltp + 14.0);
    } else {
        state.target1 = (pTdRes && pTdRes > ltp) ? pTdRes : (ltp + 20);
        state.target2 = (pTdSup && pTdSup < ltp) ? pTdSup : (ltp - 20);
        state.concreteSl = ltp - 15;
    }

    // Trade State Guard
    if (state.concreteSl && ((currentBias === 'BUY' && ltp < state.concreteSl) || (currentBias === 'SELL' && ltp > state.concreteSl))) {
        state.tradeStatus = 'INVALIDATED';
    } else if (state.target1 && ((currentBias === 'BUY' && ltp >= state.target1) || (currentBias === 'SELL' && ltp <= state.target1))) {
        state.tradeStatus = 'TARGET_HIT';
    } else if (state.lastTriggerPrice && Math.abs(ltp - state.lastTriggerPrice) >= 7.0) {
        state.tradeStatus = 'IN_FLIGHT';
    } else {
        state.tradeStatus = 'FRESH';
        state.lastTriggerPrice = ltp;
    }

    // 3-Timeline Story Narratives
    const supStr = pTdSup ? `₹${pTdSup.toFixed(0)}` : 'support';
    const resStr = pTdRes ? `₹${pTdRes.toFixed(0)}` : 'resistance';
    const t1Str = state.target1 ? `₹${state.target1.toFixed(0)}` : 'target';

    if (currentBias === 'BUY') {
        state.pastStory = `Institutional demand formed a major floor at ${supStr} (${formatQtyAbbr(state.tdSup?.totalQty)} Ask volume).`;
        state.presentStory = `Aggressive buyers hold ${state.dominanceRatio}x dominance with +${formatQtyAbbr(netVol)} net volume delta. Expanding above supply.`;
        state.futureStory = `Trajectory open toward Target 1 (${t1Str}). High probability short squeeze while price holds above ₹${state.concreteSl?.toFixed(0)}.`;
        state.playbook = `Bullish Momentum: Buy ATM/Slightly OTM Calls or deploy Bull Put Credit Spread below ${supStr}.`;
    } else if (currentBias === 'SELL') {
        state.pastStory = `Heavy supply ceiling verified at ${resStr} (${formatQtyAbbr(state.tdRes?.totalQty)} Bid volume).`;
        state.presentStory = `Aggressive sellers hold ${state.dominanceRatio}x dominance with ${formatQtyAbbr(netVol)} net volume. Downside running.`;
        state.futureStory = `Path of least resistance toward Target 1 (${t1Str}). Invalidation on 5M close above ₹${state.concreteSl?.toFixed(0)}.`;
        state.playbook = `Bearish Momentum: Buy ATM Put or deploy Bear Call Credit Spread above ${resStr}.`;
    } else {
        state.pastStory = `Order flow balanced between ${supStr} demand and ${resStr} supply.`;
        state.presentStory = `Neither buyers nor sellers hold ≥2.0x dominance. Range coiling.`;
        state.futureStory = `Wait for high-probability breakout above ${resStr} or breakdown below ${supStr}.`;
        state.playbook = `Non-Directional Range: Iron Fly / Short Strangle centered at ATM to harvest theta decay.`;
    }

    // Check if state changed significantly for instant snapshot
    const shouldSnapshotNow = (state.lastLoggedBias !== null && state.lastLoggedBias !== state.bias) ||
                              (Date.now() - state.lastSnapshotAt >= CONFIG.snapshotIntervalMs);

    if (shouldSnapshotNow) {
        persistIntelligenceSnapshot();
    }
}

// 14. Persist Intelligence Snapshot to MySQL
async function persistIntelligenceSnapshot() {
    if (!state.ltp) return;
    state.lastSnapshotAt = Date.now();
    state.lastLoggedBias = state.bias;

    const today = getTodayDateStr();
    const timeStr = getTimeStr();

    try {
        const [res] = await pool.execute(
            `INSERT INTO order_flow_intelligence_snapshots 
             (trade_date, snapshot_time, symbol, instrument, ltp, index_ltp, conviction_score, regime, bias, dominance_ratio, net_volume, total_ask_volume, total_bid_volume, td_sup, td_sup_qty, td_res, td_res_qty, yd_sup, yd_res, target1, target2, concrete_sl, trade_status, whale_status, fomo_status, past_story, present_story, future_story, playbook_suggestion, meta, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())`,
            [
                today,
                timeStr,
                CONFIG.symbol,
                state.activeTradingSymbol,
                state.ltp,
                state.indexLtp,
                state.convictionScore,
                state.regime,
                state.bias,
                state.dominanceRatio,
                state.netVolume,
                Array.from(state.allAskClusters.values()).reduce((a, b) => a + b, 0),
                Array.from(state.allBidClusters.values()).reduce((a, b) => a + b, 0),
                state.tdSup ? state.tdSup.price : null,
                state.tdSup ? state.tdSup.totalQty : null,
                state.tdRes ? state.tdRes.price : null,
                state.tdRes ? state.tdRes.totalQty : null,
                state.ydSup ? state.ydSup.price : null,
                state.ydRes ? state.ydRes.price : null,
                state.target1,
                state.target2,
                state.concreteSl,
                state.tradeStatus,
                state.whaleStatus,
                state.fomoStatus,
                state.pastStory,
                state.presentStory,
                state.futureStory,
                state.playbook,
                JSON.stringify({ cvd: state.cvd, top_ask: state.tdSup, top_bid: state.tdRes })
            ]
        );

        console.log(`💾 Snapshot logged at ${timeStr}: LTP ₹${state.ltp.toFixed(1)} | ${state.convictionScore}% ${state.regime} | ${state.bias} (${state.dominanceRatio}x)`);
    } catch (e) {
        console.warn('Error saving intelligence snapshot:', e.message);
    }
}

// 15. Periodic Batch Flush for Trades to order_flow_traded_volumes
async function flushTradeBatch() {
    if (state.pendingTrades.length === 0) return;
    const batch = state.pendingTrades.splice(0, 100);

    try {
        const values = batch.map(t => [
            t.trade_date,
            t.trade_time,
            t.symbol,
            t.instrument,
            t.side,
            t.price,
            t.quantity,
            t.source,
            null,
            new Date(),
            new Date(),
        ]);

        await pool.query(
            `INSERT IGNORE INTO order_flow_traded_volumes 
             (trade_date, trade_time, symbol, instrument, side, price, quantity, source, meta, created_at, updated_at)
             VALUES ?`,
            [values]
        );
        // console.log(`💾 Flushed ${batch.length} trades to order_flow_traded_volumes`);
    } catch (e) {
        console.warn('Error flushing trade batch:', e.message);
    }
}

// Timer for batch flush
setInterval(flushTradeBatch, CONFIG.tradeBatchFlushMs);

// Periodic keepalive / status ticker every 30s
setInterval(() => {
    const timeStr = getTimeStr();
    const ltpStr = state.ltp ? `₹${state.ltp.toFixed(1)}` : 'Waiting for ticks';
    console.log(`⏱️ [${timeStr}] Status: ${state.ws && state.ws.readyState === WebSocket.OPEN ? 'FEED ONLINE' : 'CONNECTING'} | LTP: ${ltpStr} | Bias: ${state.bias} (${state.dominanceRatio}x) | Conviction: ${state.convictionScore}% | Pending Trades: ${state.pendingTrades.length}`);
}, 30000);

// Global Error Boundaries to ensure 100% daemon uptime
process.on('uncaughtException', (err) => {
    console.error('⚠️ Uncaught Exception in Headless Daemon:', err);
});

process.on('unhandledRejection', (reason, promise) => {
    console.error('⚠️ Unhandled Rejection in Headless Daemon:', reason);
});

// Main Starter
async function main() {
    console.log('====================================================');
    console.log('🚀 INITIALIZING HEADLESS 30-DEPTH ORDER FLOW DAEMON');
    console.log(`⏰ Time: ${new Date().toLocaleString('en-IN', { timeZone: 'Asia/Kolkata' })}`);
    console.log('====================================================');

    await resolveActiveInstrument();
    await fetchYesterdayAnchors();
    await initProtobuf();
    await connectFeed();
}

main().catch(err => {
    console.error('Fatal initialization error:', err);
    process.exit(1);
});
