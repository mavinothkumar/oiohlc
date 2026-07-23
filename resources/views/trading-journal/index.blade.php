<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trading Journal - Live Strategy Tracker</title>
    <!-- Tailwind CSS (via CDN for simplicity, though project has it built-in) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
    <!-- Axios -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <!-- Protobuf JS -->
    <script src="https://cdn.jsdelivr.net/npm/protobufjs@7.2.5/dist/protobuf.min.js"></script>
    
    <style>
        [x-cloak] { display: none !important; }
        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-200 min-h-screen font-sans" x-data="tradingJournal()">

    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <header class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-emerald-400">Trading Journal</h1>
                <p class="text-slate-400 mt-1">Live Strategy Tracker (Upstox WebSocket)</p>
            </div>
            <div class="flex gap-4 items-center">
                <div class="flex items-center gap-2">
                    <span class="relative flex h-3 w-3">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" x-show="wsConnected"></span>
                      <span class="relative inline-flex rounded-full h-3 w-3" :class="wsConnected ? 'bg-emerald-500' : 'bg-red-500'"></span>
                    </span>
                    <span class="text-sm text-slate-400" x-text="wsConnected ? 'Live Feed Connected' : 'Disconnected'"></span>
                </div>
                <button @click="addNewPanel()" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg font-medium transition-colors shadow-lg shadow-blue-500/30 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    New Strategy Panel
                </button>
            </div>
        </header>

        <div class="space-y-6">
            <!-- Panels Loop -->
            <template x-for="(panel, pIndex) in panels" :key="panel.id || 'new-'+pIndex">
                <div class="glass-panel rounded-xl overflow-hidden shadow-2xl transition-all">
                    <!-- Panel Header -->
                    <div class="bg-slate-800/80 px-6 py-4 flex flex-wrap justify-between items-center border-b border-slate-700/50">
                        <div class="flex items-center gap-4 flex-1">
                            <input type="text" x-model="panel.name" placeholder="Strategy Name (e.g., Short Straddle)" class="bg-slate-900 border border-slate-700 rounded-md px-3 py-1.5 text-white focus:ring-2 focus:ring-blue-500 outline-none w-64">
                            
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-slate-400">Entry Time:</label>
                                <input type="time" x-model="panel.entry_time" min="09:15" max="15:30" class="bg-slate-900 border border-slate-700 rounded-md px-3 py-1.5 text-white focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>
                        </div>

                        <div class="flex items-center gap-4 mt-4 sm:mt-0">
                            <div class="px-4 py-1.5 rounded-lg bg-slate-900 border border-slate-700 flex items-center gap-3">
                                <span class="text-sm text-slate-400">Total P&L:</span>
                                <span class="font-bold text-lg" :class="calculatePanelPnL(panel) >= 0 ? 'text-emerald-400' : 'text-rose-400'" x-text="formatCurrency(calculatePanelPnL(panel))"></span>
                            </div>
                            
                            <button @click="savePanel(panel)" class="bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1.5 rounded-md text-sm transition-colors flex items-center gap-1">
                                Save
                            </button>
                            <button @click="deletePanel(panel.id, pIndex)" class="bg-rose-900/50 hover:bg-rose-800 text-rose-300 px-3 py-1.5 rounded-md text-sm transition-colors">
                                Delete
                            </button>
                        </div>
                    </div>

                    <!-- Panel Legs -->
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-xs uppercase text-slate-400 border-b border-slate-700/50">
                                        <th class="pb-3 px-2 font-medium">Strike</th>
                                        <th class="pb-3 px-2 font-medium">Type</th>
                                        <th class="pb-3 px-2 font-medium">Expiry</th>
                                        <th class="pb-3 px-2 font-medium">Qty (Lots)</th>
                                        <th class="pb-3 px-2 font-medium">Side</th>
                                        <th class="pb-3 px-2 font-medium text-right">Entry Price</th>
                                        <th class="pb-3 px-2 font-medium text-right">Live Price</th>
                                        <th class="pb-3 px-2 font-medium text-right">Diff Pts</th>
                                        <th class="pb-3 px-2 font-medium text-right">Change %</th>
                                        <th class="pb-3 px-2 font-medium text-right">P&L</th>
                                        <th class="pb-3 px-2 font-medium text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm">
                                    <template x-for="(leg, lIndex) in panel.legs" :key="lIndex">
                                        <tr class="border-b border-slate-700/30 hover:bg-slate-800/30 transition-colors">
                                            <td class="py-3 px-2">
                                                <input type="number" x-model="leg.strike_price" placeholder="Strike" class="w-24 bg-slate-900 border border-slate-700 rounded px-2 py-1 focus:ring-1 focus:ring-blue-500 outline-none">
                                            </td>
                                            <td class="py-3 px-2">
                                                <select x-model="leg.option_type" class="bg-slate-900 border border-slate-700 rounded px-2 py-1 focus:ring-1 focus:ring-blue-500 outline-none text-white">
                                                    <option value="CE">CE</option>
                                                    <option value="PE">PE</option>
                                                </select>
                                            </td>
                                            <td class="py-3 px-2">
                                                <select x-model="leg.expiry_type" class="bg-slate-900 border border-slate-700 rounded px-2 py-1 focus:ring-1 focus:ring-blue-500 outline-none text-white w-24">
                                                    <option value="Current">Current</option>
                                                    <option value="Next">Next</option>
                                                </select>
                                            </td>
                                            <td class="py-3 px-2">
                                                <select x-model="leg.quantity" class="bg-slate-900 border border-slate-700 rounded px-2 py-1 focus:ring-1 focus:ring-blue-500 outline-none text-white w-24">
                                                    <template x-for="i in 10">
                                                        <option :value="i * 65" x-text="i + ' (' + (i * 65) + ')'"></option>
                                                    </template>
                                                </select>
                                            </td>
                                            <td class="py-3 px-2">
                                                <select x-model="leg.side" class="bg-slate-900 border border-slate-700 rounded px-2 py-1 focus:ring-1 focus:ring-blue-500 outline-none" :class="leg.side === 'Buy' ? 'text-blue-400' : 'text-rose-400'">
                                                    <option value="Buy">Buy</option>
                                                    <option value="Sell">Sell</option>
                                                </select>
                                            </td>
                                            <td class="py-3 px-2 text-right font-mono" x-text="formatPrice(leg.entry_price)"></td>
                                            <td class="py-3 px-2 text-right font-mono font-medium text-emerald-400" x-text="formatPrice(getLivePrice(leg))"></td>
                                            <td class="py-3 px-2 text-right font-mono" :class="getDiffPts(leg) >= 0 ? 'text-emerald-400' : 'text-rose-400'" x-text="getDiffPts(leg) > 0 ? '+'+getDiffPts(leg).toFixed(2) : getDiffPts(leg).toFixed(2)"></td>
                                            <td class="py-3 px-2 text-right font-mono text-xs" :class="getChangePct(leg) >= 0 ? 'text-emerald-400' : 'text-rose-400'" x-text="(getChangePct(leg) > 0 ? '+' : '') + getChangePct(leg).toFixed(2) + '%'"></td>
                                            <td class="py-3 px-2 text-right font-mono font-bold" :class="calculateLegPnL(leg) >= 0 ? 'text-emerald-400' : 'text-rose-400'" x-text="formatCurrency(calculateLegPnL(leg))"></td>
                                            <td class="py-3 px-2 text-center">
                                                <button @click="panel.legs.splice(lIndex, 1)" class="text-slate-500 hover:text-rose-400 transition-colors p-1" title="Remove Leg">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            <button @click="addLeg(panel)" class="text-sm text-blue-400 hover:text-blue-300 flex items-center gap-1 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                </svg>
                                Add Strategy Leg
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Empty State -->
            <div x-show="panels.length === 0" class="text-center py-20 glass-panel rounded-xl">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-slate-500 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <h3 class="text-xl font-medium text-slate-300">No Strategies Found</h3>
                <p class="text-slate-400 mt-2">Create your first strategy panel to start tracking.</p>
                <button @click="addNewPanel()" class="mt-4 bg-blue-600 hover:bg-blue-500 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                    Create Panel
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('tradingJournal', () => ({
                panels: [],
                livePrices: {}, // { instrument_key: price }
                wsConnected: false,
                socket: null,
                protobufRoot: null,

                async init() {
                    await this.fetchPanels();
                    await this.initProtobuf();
                    await this.connectWebsocket();
                },

                async fetchPanels() {
                    try {
                        const response = await axios.get('/trading-journal/data');
                        this.panels = response.data;
                    } catch (error) {
                        console.error('Error fetching panels:', error);
                    }
                },

                addNewPanel() {
                    this.panels.unshift({
                        id: null,
                        name: 'New Strategy',
                        entry_time: '09:15',
                        legs: [
                            { strike_price: '', option_type: 'CE', expiry_type: 'Current', quantity: 65, side: 'Sell', entry_price: 0, instrument_key: null }
                        ]
                    });
                },

                addLeg(panel) {
                    panel.legs.push({
                        strike_price: panel.legs.length > 0 ? panel.legs[panel.legs.length - 1].strike_price : '',
                        option_type: 'PE', 
                        expiry_type: 'Current', 
                        quantity: 65, 
                        side: 'Sell', 
                        entry_price: 0, 
                        instrument_key: null
                    });
                },

                async savePanel(panel) {
                    if (!panel.name || panel.legs.length === 0) {
                        alert('Please fill out strategy name and add at least one leg.');
                        return;
                    }
                    try {
                        const response = await axios.post('/trading-journal/panel', panel);
                        if (response.data.success) {
                            // Re-fetch to get accurate entry_prices and instrument_keys updated by backend
                            await this.fetchPanels();
                            this.subscribeToInstruments();
                            // Optional: Show success toast
                        }
                    } catch (error) {
                        console.error('Error saving panel:', error);
                        alert('Failed to save panel.');
                    }
                },

                async deletePanel(id, index) {
                    if (confirm('Are you sure you want to delete this panel?')) {
                        if (id) {
                            try {
                                await axios.post(`/trading-journal/panel/delete/${id}`);
                            } catch (e) {
                                console.error('Error deleting panel:', e);
                            }
                        }
                        this.panels.splice(index, 1);
                    }
                },

                getLivePrice(leg) {
                    if (leg.instrument_key && this.livePrices[leg.instrument_key]) {
                        return this.livePrices[leg.instrument_key];
                    }
                    return leg.entry_price || 0; // fallback to entry if live not available
                },

                getDiffPts(leg) {
                    let live = this.getLivePrice(leg);
                    let entry = parseFloat(leg.entry_price) || 0;
                    if (entry === 0) return 0;
                    return live - entry;
                },

                getChangePct(leg) {
                    let diff = this.getDiffPts(leg);
                    let entry = parseFloat(leg.entry_price) || 0;
                    if (entry === 0) return 0;
                    return (diff / entry) * 100;
                },

                calculateLegPnL(leg) {
                    let diff = this.getDiffPts(leg);
                    let qty = parseInt(leg.quantity) || 0;
                    // If Sell, diff is reversed (lower price = profit)
                    if (leg.side === 'Sell') {
                        return (diff * -1) * qty;
                    }
                    // If Buy
                    return diff * qty;
                },

                calculatePanelPnL(panel) {
                    return panel.legs.reduce((total, leg) => total + this.calculateLegPnL(leg), 0);
                },

                formatPrice(val) {
                    return parseFloat(val).toFixed(2);
                },

                formatCurrency(val) {
                    let formatted = parseFloat(val).toFixed(2);
                    return formatted > 0 ? '+₹' + formatted : '₹' + formatted;
                },

                // WebSocket Integration
                async initProtobuf() {
                    try {
                        this.protobufRoot = await protobuf.load('/MarketDataFeed.proto');
                        console.log('Protobuf initialized');
                    } catch (e) {
                        console.error('Error loading protobuf:', e);
                    }
                },

                async connectWebsocket() {
                    try {
                        const authResponse = await axios.get('/trading-journal/ws-url');
                        const wsUrl = authResponse.data.data.authorizedRedirectUri;
                        
                        this.socket = new WebSocket(wsUrl);
                        this.socket.binaryType = "arraybuffer";

                        this.socket.onopen = () => {
                            this.wsConnected = true;
                            this.subscribeToInstruments();
                        };

                        this.socket.onclose = () => {
                            this.wsConnected = false;
                            setTimeout(() => this.connectWebsocket(), 5000); // Reconnect
                        };

                        this.socket.onmessage = (event) => {
                            this.decodeProtobuf(event.data);
                        };

                    } catch (error) {
                        console.error('WebSocket connection error:', error);
                    }
                },

                subscribeToInstruments() {
                    if (!this.socket || this.socket.readyState !== WebSocket.OPEN) return;

                    // Collect all unique instrument keys
                    const keys = new Set();
                    this.panels.forEach(panel => {
                        panel.legs.forEach(leg => {
                            if (leg.instrument_key) keys.add(leg.instrument_key);
                        });
                    });

                    if (keys.size === 0) return;

                    const data = {
                        guid: "someguid",
                        method: "sub",
                        data: {
                            mode: "full",
                            instrumentKeys: Array.from(keys)
                        }
                    };
                    
                    this.socket.send(new TextEncoder().encode(JSON.stringify(data)));
                },

                decodeProtobuf(buffer) {
                    if (!this.protobufRoot) return;
                    
                    try {
                        let FeedResponse = this.protobufRoot.lookupType("com.upstox.marketdatafeeder.rpc.proto.FeedResponse");
                        let message = FeedResponse.decode(new Uint8Array(buffer));
                        let obj = FeedResponse.toObject(message, { enums: String, bytes: String });
                        
                        if (obj.feeds) {
                            for (const [key, feed] of Object.entries(obj.feeds)) {
                                if (feed.ff && feed.ff.marketFF && feed.ff.marketFF.ltpc) {
                                    this.livePrices[key] = feed.ff.marketFF.ltpc.ltp;
                                } else if (feed.sf && feed.sf.ltpc) {
                                    this.livePrices[key] = feed.sf.ltpc.ltp;
                                }
                            }
                        }
                    } catch (e) {
                        console.warn('Protobuf decode error:', e);
                    }
                }
            }));
        });
    </script>
</body>
</html>
