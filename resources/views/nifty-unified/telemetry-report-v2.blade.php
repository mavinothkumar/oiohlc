@extends('layouts.app')

@section('title', 'NIFTY 50 • 5-Minute Candle Decision & Telemetry Forensics Report (v2)')

@push('styles')
    <!-- Flatpickr (Calendar Date Picker) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        body {
            background-color: #0b0e14 !important;
            color: #d1d5db;
        }
        header {
            padding: 0.5rem 1rem !important;
        }
        main {
            padding: 0.5rem !important;
            max-width: 100vw !important;
        }
        /* Custom Dark Scrollbars */
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        ::-webkit-scrollbar-track {
            background: #111827;
        }
        ::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 3px;
        }
    </style>
@endpush

@section('content')
<div class="w-full flex flex-col p-2 space-y-3 font-sans select-none" id="telemetry-report-root">

    <!-- ════════════ TOP HEADER BAR & CONTROLS ════════════ -->
    <div class="bg-[#0f1424] border border-gray-800 rounded-lg p-3 shadow-lg flex flex-col md:flex-row items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="w-3 h-3 rounded-full bg-cyan-400 animate-pulse"></span>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-base sm:text-lg font-black tracking-wider text-white">NIFTY 50 • 5-MINUTE CANDLE DECISION &amp; TELEMETRY REPORT</h1>
                    <span class="text-[9.5px] px-1.5 py-0.2 bg-cyan-950 text-cyan-300 rounded border border-cyan-700/60 font-mono font-bold">v2 FORENSICS</span>
                </div>
                <p class="text-xs text-gray-400">Exhaustive Candle-by-Candle Decision Analysis • Filter Diagnostics • Opportunity &amp; Trap Telemetry</p>
            </div>
        </div>

        <!-- Date & Expiry Form -->
        <form method="GET" action="{{ route('nifty.trade.telemetry.v2') }}" class="flex items-center gap-2 flex-wrap">
            <div class="flex items-center gap-1.5 bg-[#151c2e] border border-gray-700 rounded px-2 py-1 text-xs font-mono">
                <span class="text-gray-400">Date:</span>
                <input type="text" name="date" id="report-date-picker" value="{{ $selectedDate }}" class="bg-transparent text-white font-bold focus:outline-none w-24 cursor-pointer">
            </div>

            <div class="flex items-center gap-1.5 bg-[#151c2e] border border-gray-700 rounded px-2 py-1 text-xs font-mono">
                <span class="text-gray-400">Expiry:</span>
                <select name="expiry" onchange="this.form.submit()" class="bg-[#101626] text-white font-bold focus:outline-none cursor-pointer" style="color-scheme: dark;">
                    @foreach($expiries as $exp)
                        <option value="{{ $exp }}" {{ ($selectedExpiry == $exp) ? 'selected' : '' }}>{{ $exp }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="px-3 py-1 bg-sky-600 hover:bg-sky-500 text-white font-bold rounded text-xs transition cursor-pointer">
                Load Report
            </button>

            <a href="{{ route('nifty.multi.timeframe.monitor.v2', ['date' => $selectedDate, 'expiry' => $selectedExpiry]) }}" class="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-sky-300 font-bold rounded text-xs border border-slate-700 flex items-center gap-1 transition">
                <span>🎯 Back to Cockpit</span>
            </a>
        </form>
    </div>

    <!-- ════════════ KPI SUMMARY CARDS ════════════ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 font-mono">
        <!-- 1. Total Candles -->
        <div class="bg-[#0f1422] border border-gray-800 rounded-lg p-2.5">
            <span class="text-gray-400 text-[10px] block font-semibold uppercase">5M Candles Analyzed</span>
            <div class="text-xl font-black text-white mt-0.5" id="kpi-total-candles">75</div>
            <span class="text-[9.5px] text-gray-500">Full Trading Session (09:15 - 15:30)</span>
        </div>

        <!-- 2. Trades Executed -->
        <div class="bg-[#0f1422] border border-gray-800 rounded-lg p-2.5">
            <span class="text-gray-400 text-[10px] block font-semibold uppercase">Executed Trades</span>
            <div class="text-xl font-black text-emerald-400 mt-0.5" id="kpi-total-trades">0 Trades</div>
            <span class="text-[9.5px] text-emerald-300" id="kpi-pnl-summary">+₹0.00 (0.0 pts)</span>
        </div>

        <!-- 3. Vetoed Traps -->
        <div class="bg-[#0f1422] border border-gray-800 rounded-lg p-2.5">
            <span class="text-gray-400 text-[10px] block font-semibold uppercase">Opposing Walls Vetoed</span>
            <div class="text-xl font-black text-amber-400 mt-0.5" id="kpi-vetoed-count">0 Traps</div>
            <span class="text-[9.5px] text-amber-300">Capital Protected from Traps</span>
        </div>

        <!-- 4. Squeeze Breakouts -->
        <div class="bg-[#0f1422] border border-gray-800 rounded-lg p-2.5">
            <span class="text-gray-400 text-[10px] block font-semibold uppercase">Squeeze Breakouts</span>
            <div class="text-xl font-black text-cyan-400 mt-0.5" id="kpi-squeeze-count">0 Setups</div>
            <span class="text-[9.5px] text-cyan-300">Tight OI Equilibrium (&le;15%)</span>
        </div>

        <!-- 5. Near-Miss Impulses -->
        <div class="bg-[#0f1422] border border-gray-800 rounded-lg p-2.5">
            <span class="text-gray-400 text-[10px] block font-semibold uppercase">Near-Miss Impulses</span>
            <div class="text-xl font-black text-yellow-400 mt-0.5" id="kpi-nearmiss-count">0 Impulses</div>
            <span class="text-[9.5px] text-yellow-300">70% - 79% Breadth Impulses</span>
        </div>

        <!-- 6. Target Hit Ratio -->
        <div class="bg-[#0f1422] border border-gray-800 rounded-lg p-2.5">
            <span class="text-gray-400 text-[10px] block font-semibold uppercase">Target Hit Success</span>
            <div class="text-xl font-black text-purple-400 mt-0.5" id="kpi-winrate">0%</div>
            <span class="text-[9.5px] text-purple-300">Target 1/2/3 Hit Ratio</span>
        </div>
    </div>

    <!-- ════════════ FILTER TOOLBAR & CSV EXPORT ════════════ -->
    <div class="bg-[#0f1422] border border-gray-800 rounded-lg p-2 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs font-mono">
        <!-- Filter Buttons -->
        <div class="flex items-center gap-1 flex-wrap">
            <span class="text-gray-500 text-[10px] uppercase font-bold mr-1">Filter Verdict:</span>
            <button type="button" onclick="setReportFilter('all')" class="rep-filt-btn px-2 py-0.5 rounded bg-sky-950 text-sky-300 border border-sky-700/60 font-bold" data-rep-filter="all">All (75)</button>
            <button type="button" onclick="setReportFilter('trades')" class="rep-filt-btn px-2 py-0.5 rounded bg-slate-900 text-gray-400 hover:text-white" data-rep-filter="trades">Trades &amp; Exits</button>
            <button type="button" onclick="setReportFilter('vetoed')" class="rep-filt-btn px-2 py-0.5 rounded bg-slate-900 text-gray-400 hover:text-amber-300" data-rep-filter="vetoed">⛔ Vetoed Traps</button>
            <button type="button" onclick="setReportFilter('squeeze')" class="rep-filt-btn px-2 py-0.5 rounded bg-slate-900 text-gray-400 hover:text-cyan-300" data-rep-filter="squeeze">🚀 Squeeze</button>
            <button type="button" onclick="setReportFilter('nearmiss')" class="rep-filt-btn px-2 py-0.5 rounded bg-slate-900 text-gray-400 hover:text-yellow-300" data-rep-filter="nearmiss">Near-Miss (70%+)</button>
        </div>

        <!-- Search Input & CSV Export -->
        <div class="flex items-center gap-2">
            <div class="relative">
                <input type="text" id="rep-search-input" onkeyup="handleReportSearch(this.value)" placeholder="Search time, strike, reason..." class="bg-[#151c2e] border border-gray-700 rounded px-2.5 py-0.5 text-xs text-white placeholder-gray-500 focus:outline-none w-48 sm:w-60">
            </div>
            <button type="button" onclick="exportReportToCsv()" class="px-2.5 py-0.5 bg-emerald-950/80 hover:bg-emerald-900 text-emerald-300 border border-emerald-700/60 rounded text-xs font-bold flex items-center gap-1 transition cursor-pointer" title="Export Exhaustive 5-Minute Telemetry Report as CSV for Excel / Modeling">
                <span>📥 Export CSV</span>
            </button>
        </div>
    </div>

    <!-- ════════════ MAIN TELEMETRY TABLE ════════════ -->
    <div class="bg-[#0f1422] border border-gray-800 rounded-lg p-2 shadow-inner overflow-hidden flex flex-col">
        <div class="overflow-x-auto max-h-[72vh] overflow-y-auto">
            <table class="w-full text-left font-mono text-[10.5px]">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-800 uppercase text-[9px] sticky top-0 bg-[#0f1422] z-20">
                        <th class="py-1.5 px-2 w-12 text-center">#</th>
                        <th class="py-1.5 px-2 w-16">Time</th>
                        <th class="py-1.5 px-2 w-24">Spot Price</th>
                        <th class="py-1.5 px-2 w-40">Stock Breadth</th>
                        <th class="py-1.5 px-2 w-32">Options Flow</th>
                        <th class="py-1.5 px-2 w-44">Trending OI Skew</th>
                        <th class="py-1.5 px-2 w-44">Decision &amp; Verdict</th>
                        <th class="py-1.5 px-2">Telemetry Diagnostics &amp; Forensic Reason</th>
                        <th class="py-1.5 px-2 text-right w-28">Trade P&amp;L</th>
                    </tr>
                </thead>
                <tbody id="telemetry-table-tbody" class="divide-y divide-gray-800/40">
                    <tr>
                        <td colspan="9" class="text-center py-8 text-gray-400">
                            <div class="inline-block animate-spin rounded-full h-6 w-6 border-2 border-sky-500 border-t-transparent mb-2"></div>
                            <div>Compiling Exhaustive 5-Minute Session Telemetry...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    'use strict';

    const selectedDate = "{{ $selectedDate }}";
    const selectedExpiry = "{{ $selectedExpiry }}";
    let telemetryData = [];
    let currentFilter = 'all';
    let searchQuery = '';

    // Flatpickr initialization
    if (document.getElementById('report-date-picker')) {
        flatpickr('#report-date-picker', {
            dateFormat: 'Y-m-d',
            defaultDate: selectedDate,
            onChange: function(selectedDates, dateStr) {
                if (dateStr) {
                    window.location.href = `{{ route('nifty.trade.telemetry.v2') }}?date=${dateStr}&expiry=${selectedExpiry}`;
                }
            }
        });
    }

    async function loadTelemetryReportData() {
        try {
            // Load weightage breadth, options breadth, and trending OI in parallel
            const [wRes, bRes, toiRes] = await Promise.all([
                fetch(`/api/nifty-weightage-monitor/data?date=${selectedDate}&timeframe=5m`).then(r => r.json()),
                fetch(`/api/nifty-options-breadth/data?date=${selectedDate}&expiry=${selectedExpiry}&timeframe=5m`).then(r => r.json()),
                fetch(`/api/nifty-trending-oi/data?date=${selectedDate}&expiry=${selectedExpiry}&interval=5`).then(r => r.json())
            ]);

            const rawCandles = wRes?.candles || bRes?.nifty_candles || [];
            if (!rawCandles || rawCandles.length === 0) {
                document.getElementById('telemetry-table-tbody').innerHTML = `
                    <tr><td colspan="9" class="text-center py-8 text-amber-400">No candle data recorded for date ${selectedDate}.</td></tr>
                `;
                return;
            }

            compileTelemetryRecords(rawCandles, wRes, bRes, toiRes);
        } catch (err) {
            console.error('Failed to load telemetry data:', err);
            document.getElementById('telemetry-table-tbody').innerHTML = `
                <tr><td colspan="9" class="text-center py-8 text-rose-400">Error generating telemetry report: ${err.message}</td></tr>
            `;
        }
    }

    function compileTelemetryRecords(rawCandles, wRes, bRes, toiRes) {
        telemetryData = [];
        let completedTrades = [];
        let currentTrade = null;
        let vetoCount = 0;
        let squeezeCount = 0;
        let nearMissCount = 0;

        const chart = toiRes?.chart || {};
        const toiLabels = chart.labels || [];

        function getOiSnap(tStr) {
            if (toiLabels.length === 0) return null;
            let idx = toiLabels.indexOf(tStr);
            if (idx === -1) {
                for (let j = toiLabels.length - 1; j >= 0; j--) {
                    if (toiLabels[j] <= tStr) { idx = j; break; }
                }
            }
            if (idx === -1) idx = 0;
            const ceVal = (chart.call_oi && chart.call_oi[idx] !== undefined) ? Number(chart.call_oi[idx]) : 0;
            const peVal = (chart.put_oi && chart.put_oi[idx] !== undefined) ? Number(chart.put_oi[idx]) : 0;
            const diff = (chart.sentiment && chart.sentiment[idx] !== undefined) ? Number(chart.sentiment[idx]) : (peVal - ceVal);
            const maxOi = Math.max(Math.abs(peVal), Math.abs(ceVal));
            const diffPctVal = maxOi > 0 ? ((diff / maxOi) * 100) : 0;
            return { ceVal, peVal, diff, diffPctVal };
        }

        for (let i = 0; i < rawCandles.length; i++) {
            const candle = rawCandles[i];
            const tStr = candle.time_str;
            const cOpen = Number(candle.open);
            const cHigh = Number(candle.high);
            const cLow = Number(candle.low);
            const cClose = Number(candle.close);

            const b = wRes?.breadth_by_time?.[tStr];
            const oB = bRes?.breadth_by_time?.[tStr];
            const oi = getOiSnap(tStr);

            const stkBullCount = b ? Number(b.green_count_pct || 0) : 0;
            const stkBearCount = b ? Number(b.red_count_pct || 0) : 0;
            const stkBullWt = b ? Number(b.green_weight || 0) : 0;
            const stkBearWt = b ? Number(b.red_weight || 0) : 0;

            const optUpWt = oB ? Number(oB.up_weight || 0) : 0;
            const optDownWt = oB ? Number(oB.down_weight || 0) : 0;

            const oiDiff = oi ? oi.diff : 0;
            const oiDiffPct = oi ? oi.diffPctVal : 0;

            let candleState = 'STANDBY';
            let verdictBadge = '';
            let verdictText = '';
            let diagnosticReason = '';
            let pnlHtml = '--';

            // Check active trade progression on candle i
            if (currentTrade) {
                const isBull = (currentTrade.direction === 'BULL');
                const favorableSpotMove = isBull ? (cClose - currentTrade.entrySpot) : (currentTrade.entrySpot - cClose);
                const elapsedCandles = i - currentTrade.entryIndex;
                const thetaDecay = Math.min(25.0, elapsedCandles * 0.35);

                let ptsGain = 0;
                if (currentTrade.action === 'COMBO') {
                    ptsGain = (favorableSpotMove * 0.90) + (thetaDecay * 0.2);
                } else if (currentTrade.action === 'BUY') {
                    ptsGain = (favorableSpotMove * 0.52) - thetaDecay;
                } else {
                    ptsGain = (favorableSpotMove * 0.35) + thetaDecay;
                }
                const curOptPrice = Math.max(1.0, Math.round((currentTrade.entryOptPrice + ptsGain) * 10) / 10);
                const openPnlRs = Math.round(currentTrade.openLots * ptsGain * currentTrade.lotSize);
                const totalPnlRs = currentTrade.bookedPnlRupees + openPnlRs;
                const totalPts = Math.round((totalPnlRs / (currentTrade.entryLots * currentTrade.lotSize)) * 10) / 10;

                const pnlSign = totalPts >= 0 ? '+' : '';
                const pnlColor = totalPts >= 0 ? 'text-emerald-400 font-bold' : 'text-rose-400 font-bold';
                pnlHtml = `<span class="${pnlColor}">${pnlSign}₹${totalPnlRs.toLocaleString('en-IN')} (${pnlSign}${totalPts} pts)</span>`;

                // Track Peak Spot in Trade
                currentTrade.peakSpot = currentTrade.peakSpot !== undefined
                    ? (isBull ? Math.max(currentTrade.peakSpot, cHigh) : Math.min(currentTrade.peakSpot, cLow))
                    : (isBull ? cHigh : cLow);

                // Stagnation 45m check (9 candles) - Dynamic 1:1 trailing with 5pt buffer
                const elapsedMins = elapsedCandles * 5;
                if (elapsedMins >= 45 && !currentTrade.t1Hit) {
                    currentTrade.isStagnationTrailed = true;
                    const stagBuffer = 5.0;
                    const dynamicSl = isBull 
                        ? Math.round((currentTrade.peakSpot - stagBuffer) * 10) / 10
                        : Math.round((currentTrade.peakSpot + stagBuffer) * 10) / 10;
                    if (isBull && dynamicSl > currentTrade.currentSl) {
                        currentTrade.currentSl = dynamicSl;
                        currentTrade.slType = 'STAGNATION_1TO1_TRAIL';
                    } else if (!isBull && dynamicSl < currentTrade.currentSl) {
                        currentTrade.currentSl = dynamicSl;
                        currentTrade.slType = 'STAGNATION_1TO1_TRAIL';
                    }
                }

                // Target Hits
                if (!currentTrade.t1Hit && ((isBull && cHigh >= currentTrade.t1) || (!isBull && cLow <= currentTrade.t1))) {
                    currentTrade.t1Hit = true;
                    candleState = 'TARGET_HIT';
                    verdictBadge = '<span class="px-1.5 py-0.2 rounded text-[9px] bg-purple-950 text-purple-300 border border-purple-700 font-bold">🎯 TARGET 1 HIT</span>';
                    verdictText = 'Target 1 Reached';
                    diagnosticReason = `Target 1 hit at ${currentTrade.t1.toFixed(1)}. Partial lot booked. SL trailed to Cost Breakeven.`;
                    currentTrade.currentSl = currentTrade.entrySpot;
                } else if (!currentTrade.t2Hit && currentTrade.t1Hit && ((isBull && cHigh >= currentTrade.t2) || (!isBull && cLow <= currentTrade.t2))) {
                    currentTrade.t2Hit = true;
                    candleState = 'TARGET_HIT';
                    verdictBadge = '<span class="px-1.5 py-0.2 rounded text-[9px] bg-purple-950 text-purple-300 border border-purple-700 font-bold">🎯 TARGET 2 HIT</span>';
                    verdictText = 'Target 2 Reached';
                    diagnosticReason = `Target 2 hit at ${currentTrade.t2.toFixed(1)}. Partial lot booked. SL trailed to Target 1.`;
                    currentTrade.currentSl = currentTrade.t1;
                } else if (!currentTrade.t3Hit && currentTrade.t2Hit && ((isBull && cHigh >= currentTrade.t3) || (!isBull && cLow <= currentTrade.t3))) {
                    currentTrade.t3Hit = true;
                    candleState = 'TARGET_HIT';
                    verdictBadge = '<span class="px-1.5 py-0.2 rounded text-[9px] bg-emerald-950 text-emerald-300 border border-emerald-600 font-bold">🎯 T3 FULL PROFIT</span>';
                    verdictText = 'Runner Target Hit';
                    diagnosticReason = `Target 3 hit at ${currentTrade.t3.toFixed(1)}. Complete profit booked.`;
                    currentTrade.status = 'TARGET_HIT';
                    completedTrades.push({ ...currentTrade, pnlRupees: totalPnlRs, pnlPoints: totalPts });
                    currentTrade = null;
                } else if ((isBull && cLow <= currentTrade.currentSl) || (!isBull && cHigh >= currentTrade.currentSl)) {
                    // SL Hit
                    candleState = 'EXIT_SL';
                    const isStagnation = currentTrade.slType === 'STAGNATION_1TO1_TRAIL';
                    verdictBadge = isStagnation
                        ? '<span class="px-1.5 py-0.2 rounded text-[9px] bg-amber-950 text-amber-300 border border-amber-600 font-bold">⏳ STAGNATION EXIT</span>'
                        : '<span class="px-1.5 py-0.2 rounded text-[9px] bg-rose-950 text-rose-300 border border-rose-700 font-bold">🔴 STOPPED OUT</span>';
                    verdictText = isStagnation ? 'Stagnation Trailed SL Hit' : 'Stop Loss Triggered';
                    diagnosticReason = isStagnation
                        ? `Stagnation 1:1 Trailed SL breached at ${currentTrade.currentSl.toFixed(1)} (after 45m without expansion). Risk strictly minimized.`
                        : `Price breached SL at ${currentTrade.currentSl.toFixed(1)}. Position closed to preserve capital.`;
                    currentTrade.status = currentTrade.t1Hit ? 'PARTIAL_PROFIT_EXIT' : 'STOPPED_OUT';
                    completedTrades.push({ ...currentTrade, pnlRupees: totalPnlRs, pnlPoints: totalPts });
                    currentTrade = null;
                } else {
                    // Holding
                    candleState = 'HOLDING';
                    if (currentTrade.slType === 'STAGNATION_1TO1_TRAIL') {
                        verdictBadge = '<span class="px-1.5 py-0.2 rounded text-[9px] bg-amber-950 text-amber-300 border border-amber-700 font-bold">🛡️ STAGNATION 1:1 TRAIL</span>';
                        verdictText = '1:1 Trailing SL Active';
                        diagnosticReason = `Trade stagnant for ${elapsedMins}m. SL tightened 1:1 at ${currentTrade.currentSl.toFixed(1)} (5pt buffer behind peak ${currentTrade.peakSpot.toFixed(1)}). Capital protected from sudden gamma expansion.`;
                    } else {
                        verdictBadge = '<span class="px-1.5 py-0.2 rounded text-[9px] bg-sky-950 text-sky-300 border border-sky-700">🔵 IN-TRADE HOLD</span>';
                        verdictText = 'Holding Position';
                        diagnosticReason = `Trade active. Distance to SL: ${Math.abs(cClose - currentTrade.currentSl).toFixed(1)} pts safe. Directional flow maintained.`;
                    }
                }

            } else {
                // No active trade: evaluate signals formed on candle i
                const sBull = (stkBullCount >= 80 && stkBullWt >= 80);
                const sBear = (stkBearCount >= 80 && stkBearWt >= 80);
                const oBull = (optUpWt >= 70);
                const oBear = (optDownWt >= 70);

                let dir = null;
                if (sBull && oBull) dir = 'BULL';
                else if (sBear && oBear) dir = 'BEAR';
                else if (sBull && !oBear) dir = 'BULL';
                else if (sBear && !oBull) dir = 'BEAR';

                if (dir && i < rawCandles.length - 1) {
                    let isVetoed = false;
                    let vetoMsg = '';
                    let setup = 'STANDARD';

                    if (dir === 'BULL') {
                        if (oiDiffPct <= -25) {
                            isVetoed = true;
                            vetoMsg = `Opposing Call Writing Wall (${Math.abs(oiDiffPct).toFixed(1)}%) overhead`;
                        } else if (Math.abs(oiDiffPct) <= 15) {
                            setup = 'SQUEEZE_BREAKOUT';
                        } else if (oiDiffPct >= 20) {
                            setup = 'GRADE_A_CONFLUENCE';
                        }
                    } else {
                        if (oiDiffPct >= 25) {
                            isVetoed = true;
                            vetoMsg = `Opposing Put Writing Floor (+${oiDiffPct.toFixed(1)}%) below`;
                        } else if (Math.abs(oiDiffPct) <= 15) {
                            setup = 'SQUEEZE_BREAKOUT';
                        } else if (oiDiffPct <= -20) {
                            setup = 'GRADE_A_CONFLUENCE';
                        }
                    }

                    if (isVetoed) {
                        vetoCount++;
                        candleState = 'VETOED';
                        verdictBadge = '<span class="px-1.5 py-0.2 rounded text-[9px] bg-amber-950 text-amber-300 border border-amber-600/70 font-bold">⛔ VETOED TRAP</span>';
                        verdictText = 'Vetoed by OI Wall';
                        diagnosticReason = `${dir} impulse reached 80% breadth, but rejected due to ${vetoMsg}. Protected from false breakout.`;
                    } else {
                        if (setup === 'SQUEEZE_BREAKOUT') squeezeCount++;
                        candleState = 'ENTRY_TRIGGERED';
                        verdictBadge = (setup === 'SQUEEZE_BREAKOUT')
                            ? '<span class="px-1.5 py-0.2 rounded text-[9px] bg-cyan-950 text-cyan-300 border border-cyan-600/70 font-bold">🚀 SQUEEZE BREAKOUT</span>'
                            : (setup === 'GRADE_A_CONFLUENCE'
                                ? '<span class="px-1.5 py-0.2 rounded text-[9px] bg-emerald-950 text-emerald-300 border border-emerald-600/70 font-bold">🌟 GRADE A+ CONFLUENCE</span>'
                                : '<span class="px-1.5 py-0.2 rounded text-[9px] bg-sky-950 text-sky-300 border border-sky-700 font-bold">🎯 TRADE ENTRY</span>');
                        verdictText = `${dir} Entry Queued`;
                        diagnosticReason = `High-probability ${dir} signal validated. OI Skew: ${oiDiffPct >= 0 ? '+' : ''}${oiDiffPct.toFixed(1)}%. Order executed at open of next candle (${rawCandles[i+1].time_str}).`;

                        // Instantiate trade for subsequent candles
                        const nextOpen = Number(rawCandles[i+1].open);
                        const atmStrike = Math.round(nextOpen / 100) * 100;
                        const baseSl = (dir === 'BULL') ? cLow : cHigh;
                        const riskPts = Math.max(Math.abs(nextOpen - baseSl), 20.0);
                        currentTrade = {
                            id: `T_${rawCandles[i+1].time_str}`,
                            direction: dir,
                            action: 'SELL',
                            optType: (dir === 'BULL') ? 'PE' : 'CE',
                            strike: (dir === 'BULL') ? atmStrike - 100 : atmStrike + 100,
                            entryIndex: i + 1,
                            entrySpot: nextOpen,
                            entryOptPrice: 85.0,
                            currentSl: baseSl,
                            baseSl: baseSl,
                            riskPts: riskPts,
                            t1: (dir === 'BULL') ? nextOpen + (riskPts * 1.2) : nextOpen - (riskPts * 1.2),
                            t2: (dir === 'BULL') ? nextOpen + (riskPts * 2.2) : nextOpen - (riskPts * 2.2),
                            t3: (dir === 'BULL') ? nextOpen + (riskPts * 3.2) : nextOpen - (riskPts * 3.2),
                            t1Hit: false,
                            t2Hit: false,
                            t3Hit: false,
                            entryLots: 3,
                            openLots: 3,
                            lotSize: 65,
                            bookedPnlRupees: 0
                        };
                    }
                } else {
                    // Check near-miss or balanced standby
                    if (stkBullCount >= 70 || stkBearCount >= 70 || stkBullWt >= 70 || stkBearWt >= 70) {
                        nearMissCount++;
                        candleState = 'NEAR_MISS';
                        verdictBadge = '<span class="px-1.5 py-0.2 rounded text-[9px] bg-yellow-950/70 text-yellow-300 border border-yellow-700/50">🟡 NEAR-MISS (70%+)</span>';
                        verdictText = 'Impulse Near-Miss';
                        diagnosticReason = `Sub-threshold impulse: Bull ${stkBullCount}% vs Bear ${stkBearCount}%. Did not cross 80% threshold required for disciplined entry.`;
                    } else if ((stkBullCount > 60 && optDownWt > 60) || (stkBearCount > 60 && optUpWt > 60)) {
                        candleState = 'DIVERGENCE';
                        verdictBadge = '<span class="px-1.5 py-0.2 rounded text-[9px] bg-orange-950/70 text-orange-300 border border-orange-700/50">🟠 FLOW DIVERGENCE</span>';
                        verdictText = 'Conflicting Signals';
                        diagnosticReason = `Divergence: Stock Breadth leans ${stkBullCount > stkBearCount ? 'Bull' : 'Bear'} while Options Flow leans opposite. Trade blocked to avoid whipsaws.`;
                    } else {
                        candleState = 'STANDBY';
                        verdictBadge = '<span class="px-1.5 py-0.2 rounded text-[9px] bg-slate-900 text-slate-400 border border-slate-800">⚪ STANDBY (RANGE)</span>';
                        verdictText = 'Market Equilibrium';
                        diagnosticReason = `Market in equilibrium chop: Bull ${stkBullCount}% / Bear ${stkBearCount}%. Net OI Skew: ${oiDiffPct >= 0 ? '+' : ''}${oiDiffPct.toFixed(1)}%. No statistical edge.`;
                    }
                }
            }

            telemetryData.push({
                index: i + 1,
                time: tStr,
                open: cOpen,
                high: cHigh,
                low: cLow,
                close: cClose,
                state: candleState,
                verdictBadge: verdictBadge,
                verdictText: verdictText,
                diagnostics: diagnosticReason,
                stkBreadth: `${stkBullCount}% UP / ${stkBearCount}% DN`,
                stkWt: `Wt: ${stkBullWt}% / ${stkBearWt}%`,
                optFlow: `${optUpWt}% Buy / ${optDownWt}% Sell`,
                oiSkew: `${oiDiffPct >= 0 ? '+' : ''}${oiDiffPct.toFixed(1)}% (${(oiDiff / 100000).toFixed(1)}L)`,
                pnlHtml: pnlHtml
            });
        }

        // Update KPI summary cards
        document.getElementById('kpi-total-candles').textContent = telemetryData.length;
        document.getElementById('kpi-total-trades').textContent = `${completedTrades.length} Trades`;

        let totalPnl = 0, totalPts = 0, wins = 0;
        completedTrades.forEach(tr => {
            totalPnl += (tr.pnlRupees || 0);
            totalPts += (tr.pnlPoints || 0);
            if ((tr.pnlPoints || 0) >= 0) wins++;
        });

        const pnlSign = totalPts >= 0 ? '+' : '';
        const pnlSummaryEl = document.getElementById('kpi-pnl-summary');
        if (pnlSummaryEl) {
            pnlSummaryEl.textContent = `${pnlSign}₹${totalPnl.toLocaleString('en-IN')} (${pnlSign}${totalPts.toFixed(1)} pts)`;
            pnlSummaryEl.className = totalPts >= 0 ? 'text-[9.5px] text-emerald-400 font-bold' : 'text-[9.5px] text-rose-400 font-bold';
        }

        document.getElementById('kpi-vetoed-count').textContent = `${vetoCount} Traps`;
        document.getElementById('kpi-squeeze-count').textContent = `${squeezeCount} Setups`;
        document.getElementById('kpi-nearmiss-count').textContent = `${nearMissCount} Impulses`;

        const winRate = completedTrades.length > 0 ? Math.round((wins / completedTrades.length) * 100) : 0;
        document.getElementById('kpi-winrate').textContent = `${winRate}%`;

        renderReportTable();
    }

    function renderReportTable() {
        const tbody = document.getElementById('telemetry-table-tbody');
        if (!tbody) return;

        let filtered = telemetryData.filter(row => {
            // Category filter
            if (currentFilter === 'trades') {
                if (!['ENTRY_TRIGGERED', 'TARGET_HIT', 'EXIT_SL'].includes(row.state)) return false;
            } else if (currentFilter === 'vetoed') {
                if (row.state !== 'VETOED') return false;
            } else if (currentFilter === 'squeeze') {
                if (!row.verdictBadge.includes('SQUEEZE')) return false;
            } else if (currentFilter === 'nearmiss') {
                if (row.state !== 'NEAR_MISS') return false;
            }

            // Search query
            if (searchQuery) {
                const q = searchQuery.toLowerCase();
                const matchTime = row.time.toLowerCase().includes(q);
                const matchDiag = row.diagnostics.toLowerCase().includes(q);
                const matchVerd = row.verdictText.toLowerCase().includes(q);
                if (!matchTime && !matchDiag && !matchVerd) return false;
            }

            return true;
        });

        if (filtered.length === 0) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-6 text-gray-500">No telemetry logs match the current filter.</td></tr>`;
            return;
        }

        tbody.innerHTML = filtered.map(row => {
            const isEntry = row.state === 'ENTRY_TRIGGERED';
            const isTarget = row.state === 'TARGET_HIT';
            const isExit = row.state === 'EXIT_SL';
            const isVeto = row.state === 'VETOED';

            let rowClass = 'hover:bg-slate-800/40 transition';
            if (isEntry) rowClass = 'bg-emerald-950/20 hover:bg-emerald-950/30 border-l-2 border-emerald-500';
            else if (isTarget) rowClass = 'bg-purple-950/20 hover:bg-purple-950/30 border-l-2 border-purple-500';
            else if (isExit) rowClass = 'bg-rose-950/20 hover:bg-rose-950/30 border-l-2 border-rose-500';
            else if (isVeto) rowClass = 'bg-amber-950/20 hover:bg-amber-950/30 border-l-2 border-amber-500';

            return `
                <tr class="${rowClass}">
                    <td class="py-1.5 px-2 text-center text-gray-500">${row.index}</td>
                    <td class="py-1.5 px-2 font-bold text-white flex items-center gap-1">
                        <span>⏰</span>
                        <span>${row.time}</span>
                    </td>
                    <td class="py-1.5 px-2 text-gray-200">
                        <span class="font-bold">${row.close.toFixed(1)}</span>
                        <div class="text-[9px] text-gray-500 font-normal">H: ${row.high.toFixed(0)} • L: ${row.low.toFixed(0)}</div>
                    </td>
                    <td class="py-1.5 px-2 text-gray-300">
                        <div>${row.stkBreadth}</div>
                        <div class="text-[9px] text-gray-400 font-normal">${row.stkWt}</div>
                    </td>
                    <td class="py-1.5 px-2 text-gray-300">${row.optFlow}</td>
                    <td class="py-1.5 px-2 font-bold ${row.oiSkew.startsWith('+') ? 'text-emerald-400' : 'text-rose-400'}">${row.oiSkew}</td>
                    <td class="py-1.5 px-2">${row.verdictBadge}</td>
                    <td class="py-1.5 px-2 text-gray-300 text-[10px] leading-snug">${row.diagnostics}</td>
                    <td class="py-1.5 px-2 text-right">${row.pnlHtml}</td>
                </tr>
            `;
        }).join('');
    }

    window.setReportFilter = function(filter) {
        currentFilter = filter;
        document.querySelectorAll('.rep-filt-btn').forEach(btn => {
            if (btn.getAttribute('data-rep-filter') === filter) {
                btn.className = 'rep-filt-btn px-2 py-0.5 rounded bg-sky-950 text-sky-300 border border-sky-700/60 font-bold';
            } else {
                btn.className = 'rep-filt-btn px-2 py-0.5 rounded bg-slate-900 text-gray-400 hover:text-white';
            }
        });
        renderReportTable();
    };

    window.handleReportSearch = function(val) {
        searchQuery = val.trim();
        renderReportTable();
    };

    window.exportReportToCsv = function() {
        if (!telemetryData || telemetryData.length === 0) return;
        const headers = ["Candle", "Time", "Open", "High", "Low", "Close", "Stock Breadth", "Option Flow", "OI Skew", "Verdict", "Telemetry Reason"];
        const rows = telemetryData.map(r => [
            r.index,
            r.time,
            r.open,
            r.high,
            r.low,
            r.close,
            `"${r.stkBreadth}"`,
            `"${r.optFlow}"`,
            `"${r.oiSkew}"`,
            `"${r.verdictText}"`,
            `"${r.diagnostics.replace(/"/g, '""')}"`
        ]);

        const csvContent = "data:text/csv;charset=utf-8," + [headers.join(","), ...rows.map(e => e.join(","))].join("\n");
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `NIFTY_5M_Telemetry_Report_${selectedDate}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    // Load on page ready
    document.addEventListener('DOMContentLoaded', loadTelemetryReportData);
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        loadTelemetryReportData();
    }
})();
</script>
@endpush
