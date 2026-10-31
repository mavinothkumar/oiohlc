@extends('layouts.app')

@section('title', '30-Depth Order Flow & High-Probability Strategy Confluence Radar')

@section('content')
<div class="w-full px-2 sm:px-4 py-3 space-y-4 text-slate-100 font-sans" id="order-flow-app">

    {{-- ════════════════════════ 1. TOP HEADER & STREAM CONTROLS ════════════════════════ --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 sm:p-4 shadow-2xl backdrop-blur-md">
        <div class="flex flex-wrap items-center justify-between gap-3">
            
            {{-- Left: Identity, Symbol & Expiry --}}
            <div class="flex items-center flex-wrap gap-2.5">
                <div class="flex items-center gap-2 pr-3 border-r border-slate-800">
                    <span class="flex h-3.5 w-3.5 relative">
                        <span id="pulse-indicator" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                        <span id="dot-indicator" class="relative inline-flex rounded-full h-3.5 w-3.5 bg-rose-500"></span>
                    </span>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <h1 class="text-base sm:text-lg font-black tracking-tight bg-gradient-to-r from-rose-400 via-amber-300 to-emerald-400 bg-clip-text text-transparent">
                                🌊 30-DEPTH ORDER FLOW RADAR
                            </h1>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-rose-500/20 text-rose-300 border border-rose-500/40">
                                Level 3 Microstructure
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400 font-medium">Upstox 30-Depth Live Stream + Multi-Strategy Confluence Matrix</p>
                    </div>
                </div>

                {{-- Underlying (Exclusively NIFTY 50) --}}
                <div class="flex items-center gap-1.5 bg-slate-800/80 border border-slate-700/80 rounded-xl px-3 py-1">
                    <span class="text-[11px] text-slate-400 font-semibold uppercase">Underlying:</span>
                    <span class="text-xs font-black text-white flex items-center gap-1.5">
                        <span>🇮🇳 NIFTY 50</span>
                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-indigo-500/20 text-indigo-300 font-bold border border-indigo-500/40">FUTURES</span>
                    </span>
                </div>

                {{-- Expiry Selector --}}
                <div class="flex items-center gap-1.5 bg-slate-800/80 border border-slate-700/80 rounded-xl px-2.5 py-1">
                    <span class="text-[11px] text-slate-400 font-semibold uppercase">Expiry:</span>
                    <select id="sel-expiry" class="bg-transparent text-xs text-emerald-300 font-bold focus:outline-none cursor-pointer">
                        @foreach($expiries as $exp)
                            <option value="{{ $exp->expiry_date }}" {{ $exp->expiry_date == $selectedExpiry ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::parse($exp->expiry_date)->format('d M Y') }} {{ $exp->is_current ? '⭐' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Active Instrument to Stream 30-Depth --}}
                <div class="flex items-center gap-1.5 bg-slate-800/80 border border-slate-700/80 rounded-xl px-2.5 py-1">
                    <span class="text-[11px] text-amber-400 font-semibold uppercase">Stream Target:</span>
                    <select id="sel-target-contract" class="bg-transparent text-xs text-amber-200 font-bold focus:outline-none cursor-pointer">
                        @if(!empty($contracts['future']))
                            <option value="{{ $contracts['future']['instrument_key'] }}" data-type="FUT" data-strike="0">
                                🚀 {{ $contracts['future']['trading_symbol'] }} (Future)
                            </option>
                        @endif
                        @if(!empty($contracts['atm_ce']))
                            <option value="{{ $contracts['atm_ce']['instrument_key'] }}" data-type="CE" data-strike="{{ $contracts['atm_ce']['strike'] }}">
                                🟢 ATM CALL: {{ $contracts['atm_ce']['trading_symbol'] }}
                            </option>
                        @endif
                        @if(!empty($contracts['atm_pe']))
                            <option value="{{ $contracts['atm_pe']['instrument_key'] }}" data-type="PE" data-strike="{{ $contracts['atm_pe']['strike'] }}">
                                🔴 ATM PUT: {{ $contracts['atm_pe']['trading_symbol'] }}
                            </option>
                        @endif
                        @foreach($contracts['strikes'] as $strike => $pair)
                            @if(isset($pair['CE']) && $pair['CE']['instrument_key'] !== ($contracts['atm_ce']['instrument_key'] ?? ''))
                                <option value="{{ $pair['CE']['instrument_key'] }}" data-type="CE" data-strike="{{ $strike }}">
                                    CE {{ $strike }}
                                </option>
                            @endif
                            @if(isset($pair['PE']) && $pair['PE']['instrument_key'] !== ($contracts['atm_pe']['instrument_key'] ?? ''))
                                <option value="{{ $pair['PE']['instrument_key'] }}" data-type="PE" data-strike="{{ $strike }}">
                                    PE {{ $strike }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Right: Live Feed Connection & Mode Toggles --}}
            <div class="flex items-center gap-2">
                {{-- Feed Mode Toggle --}}
                <div class="flex items-center bg-slate-950 p-1 rounded-xl border border-slate-800 text-xs">
                    <button id="btn-mode-live" class="px-2.5 py-1 rounded-lg font-bold text-xs bg-emerald-600 text-white transition shadow-sm">
                        🔴 Upstox Live Feed
                    </button>
                    <button id="btn-mode-sim" class="px-2.5 py-1 rounded-lg font-bold text-xs text-slate-400 hover:text-white transition">
                        🧪 Micro-Simulator
                    </button>
                </div>

                {{-- Status Pill --}}
                <div id="feed-status-pill" class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-800 border border-slate-700 text-slate-300">
                    <span id="feed-status-dot" class="h-2 w-2 rounded-full bg-amber-400 animate-pulse"></span>
                    <span id="feed-status-text">Connecting...</span>
                </div>

                {{-- Sound Alert Toggle --}}
                <button id="btn-sound-toggle" title="Toggle audio alert on high confluence signals" class="p-2 rounded-xl bg-slate-800 border border-slate-700 hover:bg-slate-750 text-slate-300 hover:text-white transition">
                    <span id="sound-icon">🔔</span>
                </button>
            </div>
        </div>

        {{-- Live Spot & Tick Summary Bar --}}
        <div class="grid grid-cols-2 sm:grid-cols-6 gap-2 mt-3 pt-3 border-t border-slate-800/80 text-xs">
            <div class="bg-slate-950/60 p-2 rounded-xl border border-slate-800">
                <span class="text-[10px] uppercase font-bold text-slate-400">Index Spot</span>
                <div class="flex items-baseline gap-1 mt-0.5">
                    <span id="val-spot-price" class="text-sm sm:text-base font-black text-white">₹{{ number_format($spotPrice, 2) }}</span>
                    <span id="val-spot-change" class="text-[11px] font-bold text-emerald-400">+0.00%</span>
                </div>
            </div>

            <div class="bg-slate-950/60 p-2 rounded-xl border border-slate-800">
                <span class="text-[10px] uppercase font-bold text-slate-400">Streamed Target LTP</span>
                <div class="flex items-baseline gap-1 mt-0.5">
                    <span id="val-target-ltp" class="text-sm sm:text-base font-black text-amber-300">₹--</span>
                    <span id="val-target-tick-dir" class="text-[11px] font-bold text-slate-400">--</span>
                </div>
            </div>

            <div class="bg-slate-950/60 p-2 rounded-xl border border-slate-800">
                <span class="text-[10px] uppercase font-bold text-slate-400">30-Depth Spread</span>
                <div class="flex items-baseline gap-1 mt-0.5">
                    <span id="val-spread" class="text-sm sm:text-base font-black text-slate-200">0.05</span>
                    <span class="text-[10px] text-slate-500">pts</span>
                </div>
            </div>

            <div class="bg-slate-950/60 p-2 rounded-xl border border-slate-800">
                <span class="text-[10px] uppercase font-bold text-slate-400">ATP / VWAP</span>
                <div class="flex items-baseline gap-1 mt-0.5">
                    <span id="val-atp" class="text-sm sm:text-base font-black text-cyan-300">₹--</span>
                    <span id="val-atp-status" class="text-[10px] font-bold text-emerald-400">Above</span>
                </div>
            </div>

            <div class="bg-slate-950/60 p-2 rounded-xl border border-slate-800">
                <span class="text-[10px] uppercase font-bold text-slate-400">Total Buy vs Sell Qty</span>
                <div class="flex items-baseline gap-1 mt-0.5">
                    <span id="val-ratio-tbq-tsq" class="text-sm sm:text-base font-black text-emerald-400">--</span>
                    <span class="text-[10px] text-slate-400">Ratio</span>
                </div>
            </div>

            <div class="bg-slate-950/60 p-2 rounded-xl border border-slate-800">
                <span class="text-[10px] uppercase font-bold text-slate-400">Confluence Score</span>
                <div class="flex items-baseline gap-1 mt-0.5">
                    <span id="val-hero-confluence" class="text-sm sm:text-base font-black text-rose-400">--%</span>
                    <span id="val-hero-badge" class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-slate-800 text-slate-300">ANALYZING</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ════════════════════════ 2. NAVIGATION TABS ════════════════════════ --}}
    <div class="flex items-center gap-1.5 border-b border-slate-800 pb-2 overflow-x-auto">
        <button onclick="switchTab('tab-cumulative')" id="nav-tab-cumulative" class="tab-btn px-4 py-2 rounded-xl text-xs sm:text-sm font-bold flex items-center gap-2 bg-gradient-to-r from-rose-600 to-amber-600 text-white shadow-lg transition">
            <span>🎯 Main Confluence Radar</span>
            <span class="px-1.5 py-0.5 rounded text-[10px] bg-white/20 text-white font-extrabold">LIVE</span>
        </button>

        <button onclick="switchTab('tab-obi')" id="nav-tab-obi" class="tab-btn px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold flex items-center gap-2 bg-slate-800/80 hover:bg-slate-800 text-slate-300 hover:text-white transition">
            <span>📊 30-Depth Imbalance (OBI)</span>
            <span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-700 text-slate-300">Tiered Math</span>
        </button>

        <button onclick="switchTab('tab-microprice')" id="nav-tab-microprice" class="tab-btn px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold flex items-center gap-2 bg-slate-800/80 hover:bg-slate-800 text-slate-300 hover:text-white transition">
            <span>⚡ Micro-Price & Drift</span>
            <span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-700 text-slate-300">Flow Lead</span>
        </button>

        <button onclick="switchTab('tab-liquidity-walls')" id="nav-tab-liquidity-walls" class="tab-btn px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold flex items-center gap-2 bg-slate-800/80 hover:bg-slate-800 text-slate-300 hover:text-white transition">
            <span>🛡️ Liquidity Walls & Spoofing</span>
            <span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-700 text-slate-300">Block Order</span>
        </button>

        <button onclick="switchTab('tab-confluence-debug')" id="nav-tab-confluence-debug" class="tab-btn px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold flex items-center gap-2 bg-slate-800/80 hover:bg-slate-800 text-slate-300 hover:text-white transition">
            <span>🧠 Strategy Confluence Debugger</span>
            <span class="px-1.5 py-0.5 rounded text-[10px] bg-indigo-500/30 text-indigo-300">All Engines</span>
        </button>
    </div>

    {{-- ════════════════════════ 3. TAB CONTENT CONTAINERS ════════════════════════ --}}

    {{-- ───────────────────── TAB 0: MAIN CONFLUENCE RADAR (CUMULATIVE) ───────────────────── --}}
    <div id="tab-cumulative" class="tab-pane space-y-4">
        
        {{-- Hero High-Probability Suggestion Card --}}
        <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-slate-900 to-slate-950 border-2 border-slate-800 rounded-3xl p-4 sm:p-6 shadow-2xl">
            <div class="absolute -right-16 -top-16 w-64 h-64 bg-rose-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -left-16 -bottom-16 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                
                {{-- Left: Big Signal Alert & Execution Recommendation --}}
                <div class="lg:col-span-7 space-y-4">
                    <div class="flex items-center gap-2">
                        <span id="signal-badge-icon" class="text-2xl">🔥</span>
                        <div>
                            <span class="text-xs font-black tracking-widest uppercase text-slate-400">Real-Time Confluence Recommendation</span>
                            <div class="flex items-center gap-3">
                                <h2 id="signal-main-text" class="text-2xl sm:text-3xl font-black tracking-tight text-white">
                                    INITIALIZING CONFLUENCE...
                                </h2>
                                <span id="signal-confidence-pill" class="px-3 py-1 rounded-full text-xs font-black bg-slate-800 text-slate-300 border border-slate-700">
                                    WAITING
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- High-Probability Trade Setup Details --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <div class="bg-slate-950/80 p-3 rounded-2xl border border-slate-800">
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Suggested Instrument</span>
                            <p id="trade-instrument" class="text-sm font-black text-amber-300 mt-0.5">NIFTY 24500 CE</p>
                            <span id="trade-type" class="text-[10px] font-bold text-emerald-400">Momentum Scalp</span>
                        </div>

                        <div class="bg-slate-950/80 p-3 rounded-2xl border border-slate-800">
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Entry Zone</span>
                            <p id="trade-entry-zone" class="text-sm font-black text-white mt-0.5">₹132.00 - 134.50</p>
                            <span class="text-[10px] text-slate-400">Limit Pullback</span>
                        </div>

                        <div class="bg-slate-950/80 p-3 rounded-2xl border border-slate-800">
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Stop Loss (Depth Wall)</span>
                            <p id="trade-sl" class="text-sm font-black text-rose-400 mt-0.5">₹121.00</p>
                            <span id="trade-sl-wall" class="text-[10px] text-slate-400">Behind 42k Bid Wall</span>
                        </div>

                        <div class="bg-slate-950/80 p-3 rounded-2xl border border-slate-800">
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Target (Ask Vacuum)</span>
                            <p id="trade-target" class="text-sm font-black text-emerald-400 mt-0.5">₹158.00</p>
                            <span id="trade-rr" class="text-[10px] font-bold text-emerald-300">1 : 2.2 R:R</span>
                        </div>
                    </div>

                    {{-- Quick Rationale Note --}}
                    <div class="flex items-start gap-2 bg-slate-950/50 p-2.5 rounded-xl border border-slate-800/80 text-xs text-slate-300">
                        <span class="text-amber-400 mt-0.5">💡</span>
                        <p id="trade-rationale">
                            Monitoring order book pressure: Awaiting alignment between 30-Depth Order Book Imbalance, Quant expected move, and Heavyweight breadth.
                        </p>
                    </div>
                </div>

                {{-- Right: Visual Gauge Dial & Multi-Strategy Alignment Scorecard --}}
                <div class="lg:col-span-5 bg-slate-950/90 border border-slate-800 rounded-2xl p-4 flex flex-col justify-between space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-300 uppercase">Confluence Scorecard</span>
                        <span class="text-[10px] text-slate-500">6 Strategy Engines Voting</span>
                    </div>

                    {{-- Confluence Progress Meter --}}
                    <div>
                        <div class="flex justify-between items-baseline mb-1">
                            <span class="text-xs text-slate-400">Total System Alignment</span>
                            <span id="confluence-meter-text" class="text-xl font-black text-rose-400">0%</span>
                        </div>
                        <div class="w-full bg-slate-800 rounded-full h-3.5 p-0.5 overflow-hidden">
                            <div id="confluence-meter-bar" class="bg-gradient-to-r from-rose-500 via-amber-500 to-emerald-400 h-full rounded-full transition-all duration-500" style="width: 50%;"></div>
                        </div>
                        <div class="flex justify-between text-[10px] text-slate-500 mt-1 font-semibold">
                            <span>0% Extreme Bear</span>
                            <span>50% Neutral Chop</span>
                            <span>100% Extreme Bull</span>
                        </div>
                    </div>

                    {{-- Engine Status Badges Grid --}}
                    <div class="grid grid-cols-2 gap-2 text-[11px]">
                        <div id="engine-card-depth" class="flex items-center justify-between p-2 rounded-xl bg-slate-900 border border-slate-800">
                            <span class="text-slate-400">30-Depth OBI</span>
                            <span class="font-bold text-amber-300">0.0% (Bal)</span>
                        </div>

                        <div id="engine-card-micro" class="flex items-center justify-between p-2 rounded-xl bg-slate-900 border border-slate-800">
                            <span class="text-slate-400">Micro-Price Drift</span>
                            <span class="font-bold text-slate-300">0.00 pts</span>
                        </div>

                        <div id="engine-card-wall" class="flex items-center justify-between p-2 rounded-xl bg-slate-900 border border-slate-800">
                            <span class="text-slate-400">Wall Absorption</span>
                            <span class="font-bold text-slate-300">Neutral</span>
                        </div>

                        <div id="engine-card-quant" class="flex items-center justify-between p-2 rounded-xl bg-slate-900 border border-slate-800">
                            <span class="text-slate-400">Quant POP Engine</span>
                            <span class="font-bold text-emerald-400">{{ $strategySignals['factors']['quant_engine']['signal'] ?? 'SAFE' }}</span>
                        </div>

                        <div id="engine-card-oi" class="flex items-center justify-between p-2 rounded-xl bg-slate-900 border border-slate-800">
                            <span class="text-slate-400">OI Flow / PCR</span>
                            <span class="font-bold text-emerald-400">{{ $strategySignals['factors']['oi_buildup']['signal'] ?? 'NEUTRAL' }}</span>
                        </div>

                        <div id="engine-card-breadth" class="flex items-center justify-between p-2 rounded-xl bg-slate-900 border border-slate-800">
                            <span class="text-slate-400">Heavyweight Breadth</span>
                            <span class="font-bold text-slate-300">{{ $strategySignals['factors']['market_breadth']['signal'] ?? 'NEUTRAL' }}</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- Split Section: 30-Depth Visual DOM Ladder (Left) & Real-time Flow Metrics (Right) --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
            
            {{-- 30-Depth Interactive DOM Visualizer --}}
            <div class="lg:col-span-8 bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-base font-black text-white">🌊 Live 30-Level Order Book (DOM)</span>
                        <span id="dom-contract-name" class="text-xs font-bold text-amber-300 bg-amber-500/10 px-2 py-0.5 rounded-full border border-amber-500/20">
                            Loading...
                        </span>
                    </div>

                    <div class="flex items-center gap-3 text-xs">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block w-2.5 h-2.5 rounded bg-emerald-500"></span>
                            <span class="text-slate-400">Bids (Buy Orders)</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block w-2.5 h-2.5 rounded bg-rose-500"></span>
                            <span class="text-slate-400">Asks (Sell Orders)</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block w-2.5 h-2.5 rounded bg-amber-400"></span>
                            <span class="text-slate-400">Liquidity Wall</span>
                        </div>
                    </div>
                </div>

                {{-- DOM Table Header --}}
                <div class="grid grid-cols-12 gap-1 text-[11px] font-bold text-slate-400 uppercase bg-slate-950 p-2 rounded-xl mb-1.5 border border-slate-800">
                    <div class="col-span-2 text-left">Bid Orders</div>
                    <div class="col-span-2 text-right">Bid Qty</div>
                    <div class="col-span-2 text-right text-emerald-400">Bid Price</div>
                    <div class="col-span-2 text-left text-rose-400">Ask Price</div>
                    <div class="col-span-2 text-left">Ask Qty</div>
                    <div class="col-span-2 text-right">Ask Orders</div>
                </div>

                {{-- 30-Depth Scrollable Visual Rows --}}
                <div id="dom-rows-container" class="space-y-1 max-h-[540px] overflow-y-auto pr-1 select-none font-mono text-xs">
                    {{-- Dynamically populated with 30 depth levels via JavaScript --}}
                    <div class="text-center py-12 text-slate-500">
                        Connecting to Upstox Market Data Feed v3 for 30-Depth Orders...
                    </div>
                </div>

                {{-- Depth Summary Footer Bar --}}
                <div class="grid grid-cols-2 gap-3 mt-3 pt-3 border-t border-slate-800 text-xs">
                    <div class="flex items-center justify-between p-2 rounded-xl bg-emerald-950/30 border border-emerald-900/40">
                        <span class="text-emerald-400 font-bold uppercase">Total 30-Level Bid Depth</span>
                        <span id="val-total-bid-depth" class="font-black text-emerald-300 text-sm">--</span>
                    </div>

                    <div class="flex items-center justify-between p-2 rounded-xl bg-rose-950/30 border border-rose-900/40">
                        <span class="text-rose-400 font-bold uppercase">Total 30-Level Ask Depth</span>
                        <span id="val-total-ask-depth" class="font-black text-rose-300 text-sm">--</span>
                    </div>
                </div>
            </div>

            {{-- Right Column: Tape Velocity, Absorption & Microstructure Widgets --}}
            <div class="lg:col-span-4 space-y-4">
                
                {{-- Tiered Imbalance Visualizer Card --}}
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black uppercase text-white tracking-wider">Tiered Order Book Imbalance</span>
                        <span class="text-[10px] text-slate-400">Formula: (B - A) / (B + A)</span>
                    </div>

                    {{-- Top 5 (Near Book) --}}
                    <div class="space-y-1">
                        <div class="flex justify-between text-xs">
                            <span class="text-slate-400">Near Book (Top 5 Levels)</span>
                            <span id="val-obi-5" class="font-bold text-slate-200">0.0%</span>
                        </div>
                        <div class="w-full bg-slate-950 h-2.5 rounded-full overflow-hidden flex border border-slate-800">
                            <div id="bar-obi-5-bid" class="bg-emerald-500 h-full transition-all duration-300" style="width: 50%;"></div>
                            <div id="bar-obi-5-ask" class="bg-rose-500 h-full transition-all duration-300" style="width: 50%;"></div>
                        </div>
                    </div>

                    {{-- Top 10 (Mid Book) --}}
                    <div class="space-y-1">
                        <div class="flex justify-between text-xs">
                            <span class="text-slate-400">Mid Book (Top 10 Levels)</span>
                            <span id="val-obi-10" class="font-bold text-slate-200">0.0%</span>
                        </div>
                        <div class="w-full bg-slate-950 h-2.5 rounded-full overflow-hidden flex border border-slate-800">
                            <div id="bar-obi-10-bid" class="bg-emerald-500 h-full transition-all duration-300" style="width: 50%;"></div>
                            <div id="bar-obi-10-ask" class="bg-rose-500 h-full transition-all duration-300" style="width: 50%;"></div>
                        </div>
                    </div>

                    {{-- Top 30 (Full Deep Book) --}}
                    <div class="space-y-1">
                        <div class="flex justify-between text-xs">
                            <span class="text-slate-400">Full Deep Book (All 30 Levels)</span>
                            <span id="val-obi-30" class="font-bold text-emerald-400">0.0%</span>
                        </div>
                        <div class="w-full bg-slate-950 h-2.5 rounded-full overflow-hidden flex border border-slate-800">
                            <div id="bar-obi-30-bid" class="bg-emerald-500 h-full transition-all duration-300" style="width: 50%;"></div>
                            <div id="bar-obi-30-ask" class="bg-rose-500 h-full transition-all duration-300" style="width: 50%;"></div>
                        </div>
                    </div>
                </div>

                {{-- Micro-Price vs Mid-Price Visual Divergence --}}
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black uppercase text-white tracking-wider">Depth-Weighted Micro-Price</span>
                        <span id="val-micro-divergence" class="text-xs font-bold text-cyan-300">+0.00 Divergence</span>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="bg-slate-950 p-2 rounded-xl border border-slate-800">
                            <span class="text-[10px] text-slate-400 block uppercase">Standard Mid</span>
                            <span id="val-mid-price" class="font-bold text-white mt-1 block">--</span>
                        </div>

                        <div class="bg-slate-950 p-2 rounded-xl border border-cyan-800/60">
                            <span class="text-[10px] text-cyan-400 block uppercase font-bold">Micro-Price</span>
                            <span id="val-micro-price" class="font-black text-cyan-300 mt-1 block">--</span>
                        </div>

                        <div class="bg-slate-950 p-2 rounded-xl border border-slate-800">
                            <span class="text-[10px] text-slate-400 block uppercase">Last Traded</span>
                            <span id="val-side-ltp" class="font-bold text-amber-300 mt-1 block">--</span>
                        </div>
                    </div>

                    <p class="text-[11px] text-slate-400 leading-relaxed">
                        When Depth Micro-Price is higher than LTP, buyers are pressing into the spread and breakout upward is probable.
                    </p>
                </div>

                {{-- Detected Institutional Liquidity Walls --}}
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black uppercase text-white tracking-wider">Active Liquidity Walls</span>
                        <span class="text-[10px] text-amber-400 font-bold">Block Orders ≥ 2x Avg</span>
                    </div>

                    <div id="liquidity-walls-list" class="space-y-1.5 text-xs">
                        <div class="p-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-400 text-center">
                            Scanning 30-depth order book for institutional walls...
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>

    {{-- ───────────────────── TAB 1: 30-DEPTH IMBALANCE (OBI) BREAKDOWN ───────────────────── --}}
    <div id="tab-obi" class="tab-pane hidden space-y-4">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div>
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>📊 Order Book Imbalance (OBI) Mathematical Breakdown</span>
                    </h3>
                    <p class="text-xs text-slate-400">Full visibility into how cumulative bid and ask orders form market pressure across depth tiers.</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/40">
                    Microstructure Formula
                </span>
            </div>

            {{-- Formula Card --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2">
                    <span class="text-xs font-bold text-amber-300 uppercase">Mathematical Formulation</span>
                    <div class="p-3 bg-slate-900 rounded-lg text-emerald-400 font-mono text-sm border border-slate-800 overflow-x-auto">
                        OBI_N = ( ∑ Bid_Qty[1..N] - ∑ Ask_Qty[1..N] ) / ( ∑ Bid_Qty[1..N] + ∑ Ask_Qty[1..N] )
                    </div>
                    <ul class="text-xs text-slate-400 space-y-1 list-disc pl-4">
                        <li><strong class="text-white">+0.30 to +1.00:</strong> High buying pressure (Support holding / Upside breakout likely).</li>
                        <li><strong class="text-white">-0.30 to -1.00:</strong> High selling pressure (Resistance holding / Downside slip likely).</li>
                        <li><strong class="text-white">-0.29 to +0.29:</strong> Balanced book / Neutral consolidation.</li>
                    </ul>
                </div>

                {{-- Live Step-by-Step Numerical Trace --}}
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2">
                    <span class="text-xs font-bold text-cyan-300 uppercase">Live Step-by-Step Calculation Trace</span>
                    <div class="space-y-1.5 text-xs font-mono">
                        <div class="flex justify-between p-1.5 rounded bg-slate-900">
                            <span class="text-slate-400">Numerator (BidQty - AskQty):</span>
                            <span id="trace-obi-numerator" class="text-white font-bold">0</span>
                        </div>
                        <div class="flex justify-between p-1.5 rounded bg-slate-900">
                            <span class="text-slate-400">Denominator (BidQty + AskQty):</span>
                            <span id="trace-obi-denominator" class="text-white font-bold">0</span>
                        </div>
                        <div class="flex justify-between p-1.5 rounded bg-slate-900">
                            <span class="text-slate-400">Raw Ratio:</span>
                            <span id="trace-obi-raw" class="text-emerald-400 font-bold">0.000</span>
                        </div>
                        <div class="flex justify-between p-1.5 rounded bg-slate-900 border border-emerald-500/40">
                            <span class="text-emerald-300 font-bold">Final OBI Percentage:</span>
                            <span id="trace-obi-final" class="text-emerald-300 font-black">0.0%</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Cumulative Depth Curve Table / Chart --}}
            <div class="space-y-2">
                <span class="text-xs font-bold text-white uppercase tracking-wider">Depth Tier Breakdown (Top 5, 10, 20, 30)</span>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
                    <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 text-center">
                        <span class="text-slate-400 text-[11px] block">Top 5 Levels</span>
                        <span id="card-tier-5" class="text-lg font-black text-white block mt-1">0.0%</span>
                        <span class="text-[10px] text-slate-500">Retail & Scalper Layer</span>
                    </div>

                    <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 text-center">
                        <span class="text-slate-400 text-[11px] block">Top 10 Levels</span>
                        <span id="card-tier-10" class="text-lg font-black text-white block mt-1">0.0%</span>
                        <span class="text-[10px] text-slate-500">Day Trader Buffer</span>
                    </div>

                    <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 text-center">
                        <span class="text-slate-400 text-[11px] block">Top 20 Levels</span>
                        <span id="card-tier-20" class="text-lg font-black text-white block mt-1">0.0%</span>
                        <span class="text-[10px] text-slate-500">Market Maker Zone</span>
                    </div>

                    <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 text-center">
                        <span class="text-slate-400 text-[11px] block">Top 30 Levels</span>
                        <span id="card-tier-30" class="text-lg font-black text-emerald-400 block mt-1">0.0%</span>
                        <span class="text-[10px] text-slate-500">Institutional Moat</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- ───────────────────── TAB 2: MICRO-PRICE & DRIFT ───────────────────── --}}
    <div id="tab-microprice" class="tab-pane hidden space-y-4">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div>
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>⚡ Depth-Weighted Micro-Price & Liquidity Vacuum Analysis</span>
                    </h3>
                    <p class="text-xs text-slate-400">Micro-price incorporates order volume into the spread to predict where the next tick will clear.</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-cyan-500/20 text-cyan-300 border border-cyan-500/40">
                    Lead Indicator
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Mathematical Formula --}}
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2">
                    <span class="text-xs font-bold text-cyan-300 uppercase">Micro-Price Equation</span>
                    <div class="p-3 bg-slate-900 rounded-lg text-cyan-400 font-mono text-sm border border-slate-800 overflow-x-auto">
                        MicroPrice = ( BestAsk * BidQty + BestBid * AskQty ) / ( BidQty + AskQty )
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Notice the cross-weighting: when Bid Quantity is larger, the price is pulled closer to the <strong class="text-white">Ask Price</strong> because buyers are demanding liquidity from sellers.
                    </p>
                </div>

                {{-- Live Micro-Price Values --}}
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2">
                    <span class="text-xs font-bold text-amber-300 uppercase">Live Pricing Comparison</span>
                    <div class="space-y-1.5 text-xs font-mono">
                        <div class="flex justify-between p-1.5 rounded bg-slate-900">
                            <span class="text-slate-400">Best Bid:</span>
                            <span id="trace-best-bid" class="text-emerald-400 font-bold">--</span>
                        </div>
                        <div class="flex justify-between p-1.5 rounded bg-slate-900">
                            <span class="text-slate-400">Best Ask:</span>
                            <span id="trace-best-ask" class="text-rose-400 font-bold">--</span>
                        </div>
                        <div class="flex justify-between p-1.5 rounded bg-slate-900">
                            <span class="text-slate-400">Mid-Price (Unweighted):</span>
                            <span id="trace-standard-mid" class="text-white font-bold">--</span>
                        </div>
                        <div class="flex justify-between p-1.5 rounded bg-slate-900 border border-cyan-500/50">
                            <span class="text-cyan-300 font-bold">Micro-Price (Depth-Weighted):</span>
                            <span id="trace-weighted-micro" class="text-cyan-300 font-black">--</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Liquidity Vacuum / Air Pocket Detector --}}
            <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-white uppercase">Liquidity Vacuum & Air Pocket Detector</span>
                    <span id="vacuum-status-badge" class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-400">NORMAL BOOK</span>
                </div>
                <p class="text-xs text-slate-400 leading-relaxed">
                    An <strong class="text-white">Air Pocket (Vacuum)</strong> occurs when 5 or more consecutive price levels have unusually low resting orders. When an aggressive market order hits this zone, price glides through effortlessly with almost zero resistance.
                </p>
                <div id="vacuum-alert-box" class="p-3 rounded-xl bg-slate-900 border border-slate-800 text-xs text-slate-300">
                    No active liquidity vacuum detected. Depth is currently continuous.
                </div>
            </div>

        </div>
    </div>

    {{-- ───────────────────── TAB 3: LIQUIDITY WALLS & ABSORPTION / SPOOFING ───────────────────── --}}
    <div id="tab-liquidity-walls" class="tab-pane hidden space-y-4">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div>
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>🛡️ Liquidity Walls, Order Absorption & Spoofing Detector</span>
                    </h3>
                    <p class="text-xs text-slate-400">Separates genuine institutional support/resistance from fake spoofed limit orders.</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40">
                    Wall Tracker
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2">
                    <span class="text-xs font-bold text-emerald-400 uppercase">1. Bid Support Wall</span>
                    <p id="wall-detail-bid" class="text-sm font-black text-white">Scanning...</p>
                    <p class="text-xs text-slate-400">Natural price floor where large buyers are committed to absorb selling pressure.</p>
                </div>

                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2">
                    <span class="text-xs font-bold text-rose-400 uppercase">2. Ask Resistance Wall</span>
                    <p id="wall-detail-ask" class="text-sm font-black text-white">Scanning...</p>
                    <p class="text-xs text-slate-400">Natural price ceiling where large sellers have parked limit orders.</p>
                </div>

                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2">
                    <span class="text-xs font-bold text-amber-300 uppercase">3. Spoofing Risk Score</span>
                    <p id="wall-spoof-score" class="text-sm font-black text-emerald-400">LOW (Genuine Orders)</p>
                    <p class="text-xs text-slate-400">Flags when large resting orders are abruptly withdrawn as the market approaches them.</p>
                </div>
            </div>

            {{-- Absorption Meter --}}
            <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2">
                <div class="flex justify-between items-center text-xs">
                    <span class="font-bold text-white uppercase">Absorption Progress at Current Key Wall</span>
                    <span id="val-absorption-pct" class="font-bold text-amber-300">0% Absorbed</span>
                </div>
                <div class="w-full bg-slate-900 h-3 rounded-full overflow-hidden border border-slate-800">
                    <div id="bar-absorption" class="bg-gradient-to-r from-amber-500 to-emerald-400 h-full transition-all duration-300" style="width: 0%;"></div>
                </div>
                <p class="text-[11px] text-slate-400">
                    When an ask wall is <strong class="text-white">&gt; 80% absorbed</strong> without price retreating, the wall is exhausted and an immediate breakout occurs.
                </p>
            </div>
        </div>
    </div>

    {{-- ───────────────────── TAB 4: MULTI-STRATEGY CONFLUENCE DEBUGGER ───────────────────── --}}
    <div id="tab-confluence-debug" class="tab-pane hidden space-y-4">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div>
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>🧠 Multi-Strategy Confluence Debugger & Weight Tuning</span>
                    </h3>
                    <p class="text-xs text-slate-400">Inspect the exact vote and state of each independent trading engine in the system.</p>
                </div>
                <button onclick="refreshStrategySignals()" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white transition flex items-center gap-1.5 shadow-sm">
                    <span>🔄 Refresh Signals</span>
                </button>
            </div>

            {{-- Strategy Engines Deep Dive Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                
                {{-- 1. Quant Probability Engine --}}
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-indigo-400 uppercase">Quant Probability</span>
                        <span class="text-xs font-black text-emerald-400">{{ $strategySignals['factors']['quant_engine']['signal'] ?? 'SAFE' }}</span>
                    </div>
                    <div class="text-xs space-y-1 text-slate-300">
                        <p><strong>Weight:</strong> 25%</p>
                        <p><strong>POP:</strong> {{ $strategySignals['factors']['quant_engine']['pop_pct'] ?? 70 }}%</p>
                        <p><strong>Expected Move:</strong> ±{{ $strategySignals['factors']['quant_engine']['expected_move'] ?? 110 }} pts</p>
                        <p class="text-[11px] text-slate-400">{{ $strategySignals['factors']['quant_engine']['details'] ?? '' }}</p>
                    </div>
                </div>

                {{-- 2. OI Buildup & Trending Flow --}}
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-cyan-400 uppercase">OI Buildup / Trending</span>
                        <span class="text-xs font-black text-emerald-400">{{ $strategySignals['factors']['oi_buildup']['signal'] ?? 'NEUTRAL' }}</span>
                    </div>
                    <div class="text-xs space-y-1 text-slate-300">
                        <p><strong>Weight:</strong> 25%</p>
                        <p><strong>PCR:</strong> {{ $strategySignals['factors']['oi_buildup']['pcr'] ?? 1.0 }}</p>
                        <p><strong>Flow:</strong> {{ $strategySignals['factors']['oi_buildup']['net_flow'] ?? 'Neutral' }}</p>
                        <p class="text-[11px] text-slate-400">{{ $strategySignals['factors']['oi_buildup']['details'] ?? '' }}</p>
                    </div>
                </div>

                {{-- 3. Heavyweight Market Breadth --}}
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-amber-400 uppercase">Heavyweight Breadth</span>
                        <span class="text-xs font-black text-slate-300">{{ $strategySignals['factors']['market_breadth']['signal'] ?? 'NEUTRAL' }}</span>
                    </div>
                    <div class="text-xs space-y-1 text-slate-300">
                        <p><strong>Weight:</strong> 20%</p>
                        <p><strong>Advances:</strong> {{ $strategySignals['factors']['market_breadth']['advances'] ?? 25 }}</p>
                        <p><strong>Declines:</strong> {{ $strategySignals['factors']['market_breadth']['declines'] ?? 25 }}</p>
                        <p class="text-[11px] text-slate-400">{{ $strategySignals['factors']['market_breadth']['details'] ?? '' }}</p>
                    </div>
                </div>

                {{-- 4. Price Action & Pivots --}}
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-emerald-400 uppercase">Price Action & Pivots</span>
                        <span class="text-xs font-black text-slate-300">NEUTRAL</span>
                    </div>
                    <div class="text-xs space-y-1 text-slate-300">
                        <p><strong>Weight:</strong> 15%</p>
                        <p><strong>Pivot Level:</strong> {{ $atmStrike }}</p>
                        <p><strong>R1 / S1:</strong> {{ $atmStrike + 100 }} / {{ $atmStrike - 100 }}</p>
                    </div>
                </div>

                {{-- 5. 30-Depth Microstructure (Live Order Book) --}}
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-rose-400 uppercase">30-Depth Live Stream</span>
                        <span id="debug-depth-signal" class="text-xs font-black text-amber-300">BALANCED</span>
                    </div>
                    <div class="text-xs space-y-1 text-slate-300">
                        <p><strong>Weight:</strong> 15%</p>
                        <p><strong>Live OBI:</strong> <span id="debug-depth-obi">0.0%</span></p>
                        <p><strong>Wall Support:</strong> <span id="debug-depth-wall">Active</span></p>
                    </div>
                </div>

            </div>

            {{-- Raw JSON Inspector --}}
            <div class="space-y-2">
                <span class="text-xs font-bold text-slate-400 uppercase">Raw Strategy Confluence JSON Packet</span>
                <pre id="raw-json-inspector" class="bg-slate-950 p-4 rounded-xl border border-slate-800 text-[11px] text-emerald-400 font-mono overflow-x-auto max-h-48">{{ json_encode($strategySignals, JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
    </div>

</div>

{{-- ════════════════════════ 4. SCRIPTS: PROTOBUF, WEBSOCKET & CONFLUENCE ENGINE ════════════════════════ --}}
<script src="https://cdn.jsdelivr.net/npm/protobufjs@7.2.5/dist/protobuf.min.js"></script>

<script>
(function() {
    'use strict';

    // ── 1. GLOBAL STATE ──
    const state = {
        symbol: "{{ $symbol }}",
        expiry: "{{ $selectedExpiry }}",
        indexKey: "{{ $indexKey }}",
        contracts: @json($contracts),
        strategySignals: @json($strategySignals),
        activeInstrumentKey: null,
        activeInstrumentType: 'FUT',
        activeStrike: 0,
        feedMode: 'live', // 'live' or 'sim'
        ws: null,
        wsUrl: null,
        protobufRoot: null,
        soundEnabled: true,
        lastAlertTime: 0,
        
        // 30 Depth Data Store
        depthData: {
            bids: [], // [{ price, qty, orders }, ...] (up to 30)
            asks: [], // [{ price, qty, orders }, ...] (up to 30)
            ltp: 0,
            atp: 0,
            vtt: 0,
            tbq: 0,
            tsq: 0,
            spread: 0,
            microPrice: 0,
            midPrice: 0,
            obi5: 0,
            obi10: 0,
            obi20: 0,
            obi30: 0,
            liquidityWalls: [],
            absorptionPct: 0
        },
        
        simInterval: null
    };

    // ── 2. TAB SWITCHER ──
    window.switchTab = function(tabId) {
        document.querySelectorAll('.tab-pane').forEach(el => el.classList.add('hidden'));
        const target = document.getElementById(tabId);
        if (target) target.classList.remove('hidden');

        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('bg-gradient-to-r', 'from-rose-600', 'to-amber-600', 'text-white', 'shadow-lg');
            btn.classList.add('bg-slate-800/80', 'text-slate-300');
        });

        const activeNavBtn = document.getElementById('nav-' + tabId);
        if (activeNavBtn) {
            activeNavBtn.classList.remove('bg-slate-800/80', 'text-slate-300');
            activeNavBtn.classList.add('bg-gradient-to-r', 'from-rose-600', 'to-amber-600', 'text-white', 'shadow-lg');
        }
    };

    // ── 3. INITIALIZE PROTOBUF ──
    async function initProtobuf() {
        try {
            state.protobufRoot = await protobuf.load('/MarketDataFeed_v3.proto');
            console.log('✅ MarketDataFeed_v3.proto loaded successfully for 30-depth stream.');
        } catch (e) {
            console.warn('⚠️ Could not load MarketDataFeed_v3.proto, running in simulator mode:', e);
            switchMode('sim');
        }
    }

    // ── 4. UPSTOX WEBSOCKET CONNECTION ──
    async function connectWebSocket() {
        if (state.feedMode !== 'live') return;

        const statusDot = document.getElementById('feed-status-dot');
        const statusText = document.getElementById('feed-status-text');

        if (statusDot) statusDot.className = 'h-2 w-2 rounded-full bg-amber-400 animate-pulse';
        if (statusText) statusText.textContent = 'Authorizing Upstox Feed...';

        try {
            const resp = await fetch('{{ route("api.order-flow.ws-url") }}');
            const json = await resp.json();

            const uri = json.authorizedRedirectUri || json.data?.data?.authorizedRedirectUri || json.data?.authorizedRedirectUri;

            if (!uri) {
                console.warn('Upstox WebSocket auth not available, falling back to Micro-Simulator:', json);
                if (statusDot) statusDot.className = 'h-2 w-2 rounded-full bg-rose-500';
                if (statusText) statusText.textContent = 'Token Expired';
                switchMode('sim');
                return;
            }

            state.wsUrl = uri;
            if (state.ws) {
                state.ws.close();
            }

            state.ws = new WebSocket(state.wsUrl);
            state.ws.binaryType = 'arraybuffer';

            state.ws.onopen = () => {
                console.log('🔌 Upstox WebSocket connected:', uri);
                if (statusDot) statusDot.className = 'h-2 w-2 rounded-full bg-emerald-400';
                if (statusText) statusText.textContent = 'Upstox 30-Depth Live';
                subscribeActiveTarget();

                // Detect if outside trading hours (Mon-Fri 09:15 - 15:30 IST)
                const now = new Date();
                const day = now.getDay();
                const hr = now.getHours();
                const min = now.getMinutes();
                const isWeekday = (day >= 1 && day <= 5);
                const isMarketHrs = isWeekday && ((hr > 9 || (hr === 9 && min >= 15)) && (hr < 15 || (hr === 15 && min <= 30)));

                if (!isMarketHrs) {
                    if (statusText) statusText.textContent = 'Live Connected (Market Closed)';
                    const domContainer = document.getElementById('dom-rows-container');
                    if (domContainer && (!state.depthData.bids || state.depthData.bids.length === 0)) {
                        domContainer.innerHTML = `
                            <div class="text-center py-12 px-4 space-y-2">
                                <div class="text-emerald-400 text-sm font-bold">🟢 Upstox WebSocket Connected & Authorized!</div>
                                <div class="text-slate-400 text-xs">The market is currently closed (Trading hours: Mon–Fri, 09:15 – 15:30 IST). The live exchange feed will stream active depth updates once the market opens.</div>
                                <div class="pt-2">
                                    <button onclick="window.switchMode('sim')" class="px-3.5 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs transition shadow-md">
                                        🧪 Test Live 30-Depth with Micro-Simulator
                                    </button>
                                </div>
                            </div>
                        `;
                    }
                }
            };

            state.ws.onmessage = (event) => {
                decodeProtobufFeed(event.data);
            };

            state.ws.onclose = () => {
                if (state.feedMode === 'live') {
                    if (statusDot) statusDot.className = 'h-2 w-2 rounded-full bg-rose-500';
                    if (statusText) statusText.textContent = 'Disconnected, retrying in 5s...';
                    setTimeout(connectWebSocket, 5000);
                }
            };

            state.ws.onerror = (err) => {
                console.error('WebSocket error:', err);
                if (statusDot) statusDot.className = 'h-2 w-2 rounded-full bg-rose-500';
                if (statusText) statusText.textContent = 'Feed Error';
            };

        } catch (e) {
            console.error('Exception establishing Upstox feed:', e);
            switchMode('sim');
        }
    }

    // ── 5. SUBSCRIBE IN "full_d30" MODE ──
    function subscribeActiveTarget() {
        if (!state.ws || state.ws.readyState !== WebSocket.OPEN) return;
        if (!state.activeInstrumentKey) return;

        // Subscribing in "full_d30" (Level 3 - 30 Depth Orders)
        const subMsg = {
            guid: "d30_sub_" + Date.now(),
            method: "sub",
            data: {
                mode: "full_d30",
                instrumentKeys: [state.activeInstrumentKey, state.indexKey]
            }
        };

        state.ws.send(new TextEncoder().encode(JSON.stringify(subMsg)));
        console.log(`📡 Subscribed to 30-depth stream for ${state.activeInstrumentKey} (mode: full_d30)`);
    }

    // ── 6. PROTOBUF DECODER ──
    function decodeProtobufFeed(buffer) {
        if (!state.protobufRoot) return;

        try {
            const arr = new Uint8Array(buffer);
            if (arr.length > 0 && arr[0] === 123) return; // Skip JSON text handshake

            const FeedResponse = state.protobufRoot.lookupType("com.upstox.marketdatafeederv3udapi.rpc.proto.FeedResponse");
            const message = FeedResponse.decode(arr);
            const obj = FeedResponse.toObject(message, { enums: String, bytes: String });

            if (obj && obj.feeds) {
                // Check Index Spot Key
                if (obj.feeds[state.indexKey]) {
                    const idxFeed = obj.feeds[state.indexKey];
                    let idxLtp = idxFeed.fullFeed?.indexFF?.ltpc?.ltp ?? idxFeed.ltpc?.ltp;
                    if (idxLtp) {
                        updateSpotUI(parseFloat(idxLtp));
                    }
                }

                // Check Active Target Contract (Future / Option)
                if (obj.feeds[state.activeInstrumentKey]) {
                    const targetFeed = obj.feeds[state.activeInstrumentKey];
                    parseMarketFeed(targetFeed);
                }
            }
        } catch (e) {
            console.error('Protobuf decode error:', e);
        }
    }

    // ── 7. PARSE 30-DEPTH ORDERS FROM FEED ──
    function parseMarketFeed(feed) {
        const mff = feed.fullFeed?.marketFF;
        if (!mff) return;

        const ltp = mff.ltpc?.ltp ? parseFloat(mff.ltpc.ltp) : state.depthData.ltp;
        const atp = mff.atp ? parseFloat(mff.atp) : state.depthData.atp;
        const vtt = mff.vtt ? parseInt(mff.vtt) : state.depthData.vtt;
        const tbq = mff.tbq ? parseFloat(mff.tbq) : state.depthData.tbq;
        const tsq = mff.tsq ? parseFloat(mff.tsq) : state.depthData.tsq;

        const quotes = mff.marketLevel?.bidAskQuote || [];
        const bids = [];
        const asks = [];

        quotes.forEach(q => {
            if (q.bidP && q.bidP > 0) {
                bids.push({ price: parseFloat(q.bidP), qty: parseInt(q.bidQ || 0), orders: 1 });
            }
            if (q.askP && q.askP > 0) {
                asks.push({ price: parseFloat(q.askP), qty: parseInt(q.askQ || 0), orders: 1 });
            }
        });

        // Sort bids descending, asks ascending
        bids.sort((a, b) => b.price - a.price);
        asks.sort((a, b) => a.price - b.price);

        applyNewDepthState(bids, asks, ltp, atp, vtt, tbq, tsq);
    }

    // ── 8. MICROSTRUCTURE CALCULATIONS & RE-EVALUATION ──
    function applyNewDepthState(bids, asks, ltp, atp, vtt, tbq, tsq) {
        state.depthData.bids = bids;
        state.depthData.asks = asks;
        state.depthData.ltp = ltp;
        state.depthData.atp = atp;
        state.depthData.vtt = vtt;
        state.depthData.tbq = tbq;
        state.depthData.tsq = tsq;

        const bestBid = bids.length > 0 ? bids[0].price : ltp - 0.05;
        const bestAsk = asks.length > 0 ? asks[0].price : ltp + 0.05;
        const bestBidQ = bids.length > 0 ? bids[0].qty : 100;
        const bestAskQ = asks.length > 0 ? asks[0].qty : 100;

        // 1. Spread & Mid Price
        state.depthData.spread = Math.max(0.05, parseFloat((bestAsk - bestBid).toFixed(2)));
        state.depthData.midPrice = parseFloat(((bestBid + bestAsk) / 2).toFixed(2));

        // 2. Micro-Price (Cross Depth-Weighted)
        const totalTopQ = bestBidQ + bestAskQ;
        if (totalTopQ > 0) {
            state.depthData.microPrice = parseFloat(((bestAsk * bestBidQ + bestBid * bestAskQ) / totalTopQ).toFixed(2));
        } else {
            state.depthData.microPrice = state.depthData.midPrice;
        }

        // 3. Tiered OBI Calculations (5, 10, 20, 30)
        state.depthData.obi5  = calculateTierObi(bids, asks, 5);
        state.depthData.obi10 = calculateTierObi(bids, asks, 10);
        state.depthData.obi20 = calculateTierObi(bids, asks, 20);
        state.depthData.obi30 = calculateTierObi(bids, asks, 30);

        // 4. Liquidity Wall Scanner
        scanLiquidityWalls(bids, asks);

        // 5. Update All Visual Components
        renderDOM();
        updateMicrostructureUI();
        evaluateConfluenceRadar();
    }

    function calculateTierObi(bids, asks, tier) {
        let sumB = 0;
        let sumA = 0;
        for (let i = 0; i < tier; i++) {
            if (bids[i]) sumB += bids[i].qty;
            if (asks[i]) sumA += asks[i].qty;
        }
        if (sumB + sumA === 0) return 0;
        return parseFloat((((sumB - sumA) / (sumB + sumA)) * 100).toFixed(1));
    }

    function scanLiquidityWalls(bids, asks) {
        const walls = [];
        let avgBidQ = bids.length > 0 ? (bids.reduce((acc, b) => acc + b.qty, 0) / bids.length) : 500;
        let avgAskQ = asks.length > 0 ? (asks.reduce((acc, a) => acc + a.qty, 0) / asks.length) : 500;

        // Bids Wall (Institutional floor)
        bids.forEach((b, idx) => {
            if (b.qty >= avgBidQ * 2.2 && b.qty > 500) {
                walls.push({ side: 'BID', price: b.price, qty: b.qty, level: idx + 1, multiple: (b.qty / avgBidQ).toFixed(1) });
            }
        });

        // Asks Wall (Institutional ceiling)
        asks.forEach((a, idx) => {
            if (a.qty >= avgAskQ * 2.2 && a.qty > 500) {
                walls.push({ side: 'ASK', price: a.price, qty: a.qty, level: idx + 1, multiple: (a.qty / avgAskQ).toFixed(1) });
            }
        });

        state.depthData.liquidityWalls = walls;
    }

    // ── 9. RENDER 30-DEPTH DOM VISUAL LADDER ──
    function renderDOM() {
        const container = document.getElementById('dom-rows-container');
        if (!container) return;

        const bids = state.depthData.bids;
        const asks = state.depthData.asks;
        const ltp = state.depthData.ltp;

        const maxQty = Math.max(
            ...bids.map(b => b.qty),
            ...asks.map(a => a.qty),
            1000
        );

        let html = '';
        const levels = Math.max(bids.length, asks.length, 30);

        let totalBidSum = 0;
        let totalAskSum = 0;

        for (let i = 0; i < levels; i++) {
            const b = bids[i] || null;
            const a = asks[i] || null;

            if (b) totalBidSum += b.qty;
            if (a) totalAskSum += a.qty;

            const bidPct = b ? Math.min(100, (b.qty / maxQty) * 100) : 0;
            const askPct = a ? Math.min(100, (a.qty / maxQty) * 100) : 0;

            const isBidWall = b && b.qty >= maxQty * 0.7;
            const isAskWall = a && a.qty >= maxQty * 0.7;

            html += `
            <div class="grid grid-cols-12 gap-1 items-center py-1 px-2 rounded hover:bg-slate-800/60 transition group text-[11px] relative">
                
                {{-- Bid Side (Cols 1-6) --}}
                <div class="col-span-2 text-left text-slate-400 group-hover:text-slate-200">
                    ${b ? (b.orders || 1) : '-'}
                </div>
                <div class="col-span-2 text-right relative pr-2">
                    <div class="absolute right-0 top-0 bottom-0 bg-emerald-500/20 rounded-l ${isBidWall ? 'border-r-2 border-amber-400' : ''}" style="width: ${bidPct}%;"></div>
                    <span class="relative font-bold text-slate-100 ${isBidWall ? 'text-amber-300 font-black' : ''}">
                        ${b ? b.qty.toLocaleString() : '-'}
                    </span>
                </div>
                <div class="col-span-2 text-right font-extrabold text-emerald-400">
                    ${b ? b.price.toFixed(2) : '-'}
                </div>

                {{-- Ask Side (Cols 7-12) --}}
                <div class="col-span-2 text-left font-extrabold text-rose-400 pl-2">
                    ${a ? a.price.toFixed(2) : '-'}
                </div>
                <div class="col-span-2 text-left relative pl-2">
                    <div class="absolute left-0 top-0 bottom-0 bg-rose-500/20 rounded-r ${isAskWall ? 'border-l-2 border-amber-400' : ''}" style="width: ${askPct}%;"></div>
                    <span class="relative font-bold text-slate-100 ${isAskWall ? 'text-amber-300 font-black' : ''}">
                        ${a ? a.qty.toLocaleString() : '-'}
                    </span>
                </div>
                <div class="col-span-2 text-right text-slate-400 group-hover:text-slate-200">
                    ${a ? (a.orders || 1) : '-'}
                </div>

            </div>
            `;
        }

        container.innerHTML = html;

        // Update totals
        const bidTotEl = document.getElementById('val-total-bid-depth');
        const askTotEl = document.getElementById('val-total-ask-depth');
        if (bidTotEl) bidTotEl.textContent = totalBidSum.toLocaleString() + ' Qty';
        if (askTotEl) askTotEl.textContent = totalAskSum.toLocaleString() + ' Qty';
    }

    // ── 10. UPDATE MICROSTRUCTURE UI & FORMULA TRACES ──
    function updateMicrostructureUI() {
        const d = state.depthData;

        // Top bar values
        const targetLtpEl = document.getElementById('val-target-ltp');
        const spreadEl = document.getElementById('val-spread');
        const atpEl = document.getElementById('val-atp');
        const ratioEl = document.getElementById('val-ratio-tbq-tsq');

        if (targetLtpEl) targetLtpEl.textContent = `₹${d.ltp.toFixed(2)}`;
        if (spreadEl) spreadEl.textContent = d.spread.toFixed(2);
        if (atpEl) atpEl.textContent = d.atp > 0 ? `₹${d.atp.toFixed(2)}` : `₹${d.midPrice.toFixed(2)}`;
        
        if (ratioEl) {
            const ratio = d.tsq > 0 ? (d.tbq / d.tsq).toFixed(2) : '1.00';
            ratioEl.textContent = `${ratio}x`;
            ratioEl.className = ratio >= 1.2 ? 'text-sm sm:text-base font-black text-emerald-400' : (ratio <= 0.8 ? 'text-sm sm:text-base font-black text-rose-400' : 'text-sm sm:text-base font-black text-slate-300');
        }

        // Tiered OBI Bars
        updateObiBar('5', d.obi5);
        updateObiBar('10', d.obi10);
        updateObiBar('30', d.obi30);

        // Micro-price UI
        const midPriceEl = document.getElementById('val-mid-price');
        const microPriceEl = document.getElementById('val-micro-price');
        const sideLtpEl = document.getElementById('val-side-ltp');
        const microDivEl = document.getElementById('val-micro-divergence');

        if (midPriceEl) midPriceEl.textContent = `₹${d.midPrice.toFixed(2)}`;
        if (microPriceEl) microPriceEl.textContent = `₹${d.microPrice.toFixed(2)}`;
        if (sideLtpEl) sideLtpEl.textContent = `₹${d.ltp.toFixed(2)}`;

        const div = parseFloat((d.microPrice - d.ltp).toFixed(2));
        if (microDivEl) {
            microDivEl.textContent = `${div >= 0 ? '+' : ''}${div.toFixed(2)} pts Drift`;
            microDivEl.className = div > 0.1 ? 'text-xs font-bold text-emerald-400' : (div < -0.1 ? 'text-xs font-bold text-rose-400' : 'text-xs font-bold text-slate-400');
        }

        // Trace Tabs Data
        const traceNum = document.getElementById('trace-obi-numerator');
        const traceDen = document.getElementById('trace-obi-denominator');
        const traceRaw = document.getElementById('trace-obi-raw');
        const traceFin = document.getElementById('trace-obi-final');

        let sumB30 = d.bids.slice(0, 30).reduce((a, b) => a + b.qty, 0);
        let sumA30 = d.asks.slice(0, 30).reduce((a, b) => a + b.qty, 0);

        if (traceNum) traceNum.textContent = (sumB30 - sumA30).toLocaleString();
        if (traceDen) traceDen.textContent = (sumB30 + sumA30).toLocaleString();
        if (traceRaw) traceRaw.textContent = (sumB30 + sumA30 > 0 ? (sumB30 - sumA30) / (sumB30 + sumA30) : 0).toFixed(4);
        if (traceFin) traceFin.textContent = `${d.obi30}%`;

        // Micro-price trace
        const bestBid = d.bids[0] ? d.bids[0].price : d.ltp - 0.05;
        const bestAsk = d.asks[0] ? d.asks[0].price : d.ltp + 0.05;
        const tbBidEl = document.getElementById('trace-best-bid');
        const tbAskEl = document.getElementById('trace-best-ask');
        const tbMidEl = document.getElementById('trace-standard-mid');
        const tbMicEl = document.getElementById('trace-weighted-micro');

        if (tbBidEl) tbBidEl.textContent = `₹${bestBid.toFixed(2)}`;
        if (tbAskEl) tbAskEl.textContent = `₹${bestAsk.toFixed(2)}`;
        if (tbMidEl) tbMidEl.textContent = `₹${d.midPrice.toFixed(2)}`;
        if (tbMicEl) tbMicEl.textContent = `₹${d.microPrice.toFixed(2)}`;

        // Liquidity Walls Cards
        renderLiquidityWallsUI();
    }

    function updateObiBar(tier, val) {
        const valEl = document.getElementById(`val-obi-${tier}`);
        const bidBar = document.getElementById(`bar-obi-${tier}-bid`);
        const askBar = document.getElementById(`bar-obi-${tier}-ask`);
        const cardTier = document.getElementById(`card-tier-${tier}`);

        if (valEl) {
            valEl.textContent = `${val >= 0 ? '+' : ''}${val}%`;
            valEl.className = val >= 25 ? 'font-bold text-emerald-400' : (val <= -25 ? 'font-bold text-rose-400' : 'font-bold text-slate-300');
        }

        if (cardTier) {
            cardTier.textContent = `${val >= 0 ? '+' : ''}${val}%`;
            cardTier.className = val >= 25 ? 'text-lg font-black text-emerald-400 block mt-1' : (val <= -25 ? 'text-lg font-black text-rose-400 block mt-1' : 'text-lg font-black text-white block mt-1');
        }

        // Bid / Ask Split (from -100 to +100 to 0% - 100%)
        const bidPct = Math.max(5, Math.min(95, 50 + (val / 2)));
        const askPct = 100 - bidPct;

        if (bidBar) bidBar.style.width = `${bidPct}%`;
        if (askBar) askBar.style.width = `${askPct}%`;
    }

    function renderLiquidityWallsUI() {
        const list = document.getElementById('liquidity-walls-list');
        if (!list) return;

        const walls = state.depthData.liquidityWalls;
        if (walls.length === 0) {
            list.innerHTML = `<div class="p-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-400 text-center">No extreme institutional walls detected in top 30 depth.</div>`;
            return;
        }

        let html = '';
        walls.slice(0, 4).forEach(w => {
            const isBid = w.side === 'BID';
            html += `
            <div class="flex items-center justify-between p-2 rounded-xl bg-slate-950 border ${isBid ? 'border-emerald-800/50' : 'border-rose-800/50'}">
                <div class="flex items-center gap-1.5">
                    <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold ${isBid ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300'}">
                        ${w.side} WALL (L${w.level})
                    </span>
                    <span class="font-bold text-white">₹${w.price.toFixed(2)}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="font-black ${isBid ? 'text-emerald-400' : 'text-rose-400'}">${w.qty.toLocaleString()} Qty</span>
                    <span class="text-[10px] text-amber-400 font-bold">(${w.multiple}x)</span>
                </div>
            </div>
            `;
        });

        list.innerHTML = html;

        // Update Tab 3 details
        const bidWall = walls.find(w => w.side === 'BID');
        const askWall = walls.find(w => w.side === 'ASK');

        const wbEl = document.getElementById('wall-detail-bid');
        const waEl = document.getElementById('wall-detail-ask');

        if (wbEl) wbEl.textContent = bidWall ? `₹${bidWall.price.toFixed(2)} (${bidWall.qty.toLocaleString()} Qty at Level ${bidWall.level})` : 'No Bid Wall';
        if (waEl) waEl.textContent = askWall ? `₹${askWall.price.toFixed(2)} (${askWall.qty.toLocaleString()} Qty at Level ${askWall.level})` : 'No Ask Wall';
    }

    // ── 11. MULTI-STRATEGY CONFLUENCE & TRADE SUGGESTION ENGINE ──
    function evaluateConfluenceRadar() {
        const d = state.depthData;
        const signals = state.strategySignals?.factors || {};

        // Base Strategy Scores (0 to 100)
        let quantScore = signals.quant_engine ? signals.quant_engine.score : 50;
        let oiScore = signals.oi_buildup ? signals.oi_buildup.score : 50;
        let breadthScore = signals.market_breadth ? signals.market_breadth.score : 50;

        // Depth Microstructure Score (Derived live from 30-depth OBI & Micro-Price Drift)
        let depthScore = 50 + (d.obi30 * 0.45); // OBI pushes score between 5 and 95
        if (d.microPrice > d.ltp + 0.1) depthScore += 8;
        if (d.microPrice < d.ltp - 0.1) depthScore -= 8;
        depthScore = Math.max(5, Math.min(95, Math.round(depthScore)));

        // Weighted Aggregation
        const totalConfluence = Math.round(
            (quantScore * 0.25) +
            (oiScore * 0.25) +
            (breadthScore * 0.20) +
            (depthScore * 0.30)
        );

        // Update UI Gauge
        const meterText = document.getElementById('confluence-meter-text');
        const meterBar = document.getElementById('confluence-meter-bar');
        const heroConf = document.getElementById('val-hero-confluence');
        const heroBadge = document.getElementById('val-hero-badge');

        if (meterText) meterText.textContent = `${totalConfluence}%`;
        if (heroConf) heroConf.textContent = `${totalConfluence}%`;
        if (meterBar) meterBar.style.width = `${totalConfluence}%`;

        // Signal Classification
        const sigText = document.getElementById('signal-main-text');
        const sigPill = document.getElementById('signal-confidence-pill');
        const sigIcon = document.getElementById('signal-badge-icon');
        const tradeInst = document.getElementById('trade-instrument');
        const tradeEntry = document.getElementById('trade-entry-zone');
        const tradeSl = document.getElementById('trade-sl');
        const tradeTarget = document.getElementById('trade-target');
        const tradeRationale = document.getElementById('trade-rationale');

        const activeOption = document.querySelector('#sel-target-contract option:checked');
        const targetName = activeOption ? activeOption.textContent.trim() : 'Contract';

        if (totalConfluence >= 78) {
            // 🔥 HIGH CONFIDENCE BUY (LONG)
            if (sigText) sigText.textContent = '🚀 HIGH CONFIDENCE BUY / LONG';
            if (sigPill) {
                sigPill.textContent = '95%+ PROBABILITY';
                sigPill.className = 'px-3 py-1 rounded-full text-xs font-black bg-emerald-500 text-slate-950';
            }
            if (sigIcon) sigIcon.textContent = '🔥';
            if (heroBadge) {
                heroBadge.textContent = 'STRONG BUY';
                heroBadge.className = 'px-1.5 py-0.2 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40';
            }

            // Calculate SL behind closest bid wall, Target at closest ask wall
            const bidWall = d.liquidityWalls.find(w => w.side === 'BID');
            const askWall = d.liquidityWalls.find(w => w.side === 'ASK');

            const slPrice = bidWall ? (bidWall.price - 0.20).toFixed(2) : (d.ltp * 0.96).toFixed(2);
            const targetPrice = askWall ? (askWall.price).toFixed(2) : (d.ltp * 1.08).toFixed(2);

            if (tradeInst) tradeInst.textContent = targetName;
            if (tradeEntry) tradeEntry.textContent = `₹${(d.ltp - 0.15).toFixed(2)} - ₹${d.ltp.toFixed(2)}`;
            if (tradeSl) tradeSl.textContent = `₹${slPrice}`;
            if (tradeTarget) tradeTarget.textContent = `₹${targetPrice}`;
            if (tradeRationale) {
                tradeRationale.textContent = `High confluence alignment! 30-Depth OBI (+${d.obi30}%) confirms institutional buying, reinforced by Quant POP Engine and Heavyweight Breadth.`;
            }

            triggerAlertAudio('BUY');

        } else if (totalConfluence <= 32) {
            // 🩸 HIGH CONFIDENCE SELL (SHORT)
            if (sigText) sigText.textContent = '🩸 HIGH CONFIDENCE SELL / SHORT';
            if (sigPill) {
                sigPill.textContent = 'HIGH DOWNSIDE BIAS';
                sigPill.className = 'px-3 py-1 rounded-full text-xs font-black bg-rose-500 text-white';
            }
            if (sigIcon) sigIcon.textContent = '🩸';
            if (heroBadge) {
                heroBadge.textContent = 'STRONG SELL';
                heroBadge.className = 'px-1.5 py-0.2 rounded text-[10px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40';
            }

            const askWall = d.liquidityWalls.find(w => w.side === 'ASK');
            const bidWall = d.liquidityWalls.find(w => w.side === 'BID');

            const slPrice = askWall ? (askWall.price + 0.20).toFixed(2) : (d.ltp * 1.04).toFixed(2);
            const targetPrice = bidWall ? (bidWall.price).toFixed(2) : (d.ltp * 0.92).toFixed(2);

            if (tradeInst) tradeInst.textContent = targetName;
            if (tradeEntry) tradeEntry.textContent = `₹${d.ltp.toFixed(2)} - ₹${(d.ltp + 0.15).toFixed(2)}`;
            if (tradeSl) tradeSl.textContent = `₹${slPrice}`;
            if (tradeTarget) tradeTarget.textContent = `₹${targetPrice}`;
            if (tradeRationale) {
                tradeRationale.textContent = `Sustained sell pressure: 30-Depth Ask Wall dominating with negative OBI (${d.obi30}%). Bearish confluence confirmed across multiple strategies.`;
            }

            triggerAlertAudio('SELL');

        } else {
            // ⏸️ NEUTRAL / WAIT
            if (sigText) sigText.textContent = '⏸️ BALANCED / WAIT FOR CONFLUENCE';
            if (sigPill) {
                sigPill.textContent = 'NEUTRAL ZONE';
                sigPill.className = 'px-3 py-1 rounded-full text-xs font-black bg-slate-800 text-slate-400 border border-slate-700';
            }
            if (sigIcon) sigIcon.textContent = '⚖️';
            if (heroBadge) {
                heroBadge.textContent = 'NEUTRAL CHOP';
                heroBadge.className = 'px-1.5 py-0.2 rounded text-[10px] font-bold bg-slate-800 text-slate-400';
            }

            if (tradeInst) tradeInst.textContent = targetName;
            if (tradeEntry) tradeEntry.textContent = `₹${d.ltp.toFixed(2)}`;
            if (tradeSl) tradeSl.textContent = `Wait for Breakout`;
            if (tradeTarget) tradeTarget.textContent = `Range Bound`;
            if (tradeRationale) {
                tradeRationale.textContent = `Order book is in equilibrium (OBI: ${d.obi30}%). Strategies have conflicting votes. Standing aside preserves capital until clear confluence appears.`;
            }
        }
    }

    function triggerAlertAudio(type) {
        if (!state.soundEnabled) return;
        const now = Date.now();
        if (now - state.lastAlertTime < 30000) return; // Limit to once every 30 seconds
        state.lastAlertTime = now;

        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(type === 'BUY' ? 880 : 440, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(type === 'BUY' ? 1200 : 330, ctx.currentTime + 0.25);

            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.25);

            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.25);
        } catch (e) {
            // Audio context blocked until interaction
        }
    }

    // ── 12. HIGH-FIDELITY MICRO-SIMULATOR (FOR OFF-MARKET TESTING & DEBUG) ──
    function startSimulator() {
        if (state.simInterval) clearInterval(state.simInterval);

        let basePrice = 145.0;
        let trendBias = 1; // 1 for bull, -1 for bear

        state.simInterval = setInterval(() => {
            // Drift base price slightly
            basePrice += (Math.random() - 0.48) * 0.4 * trendBias;
            basePrice = Math.max(10, parseFloat(basePrice.toFixed(2)));

            // Occasionally flip trend bias to show dynamic strategy confluence shifts
            if (Math.random() < 0.05) trendBias *= -1;

            const bids = [];
            const asks = [];

            // Generate 30 Bid Levels
            let runningBidP = basePrice - 0.05;
            for (let i = 0; i < 30; i++) {
                runningBidP = parseFloat((runningBidP - (0.05 + Math.random() * 0.05)).toFixed(2));
                let qty = Math.floor(150 + Math.random() * 800);
                
                // Introduce simulated institutional walls at level 8 or 15
                if (i === 7 && trendBias > 0) qty = 4500;
                if (i === 14) qty = 2800;

                bids.push({ price: runningBidP, qty: qty, orders: Math.floor(qty / 120) + 1 });
            }

            // Generate 30 Ask Levels
            let runningAskP = basePrice + 0.05;
            for (let i = 0; i < 30; i++) {
                runningAskP = parseFloat((runningAskP + (0.05 + Math.random() * 0.05)).toFixed(2));
                let qty = Math.floor(150 + Math.random() * 800);

                if (i === 9 && trendBias < 0) qty = 5200;
                if (i === 18) qty = 3100;

                asks.push({ price: runningAskP, qty: qty, orders: Math.floor(qty / 120) + 1 });
            }

            const ltp = basePrice;
            const atp = parseFloat((basePrice - 0.35).toFixed(2));
            const tbq = bids.reduce((acc, b) => acc + b.qty, 0) * 1.5;
            const tsq = asks.reduce((acc, a) => acc + a.qty, 0) * 1.5;

            applyNewDepthState(bids, asks, ltp, atp, 125000, tbq, tsq);

        }, 1200);

        console.log('🧪 Interactive 30-Depth Micro-Simulator running.');
    }

    window.switchMode = function(mode) {
        state.feedMode = mode;
        const btnLive = document.getElementById('btn-mode-live');
        const btnSim = document.getElementById('btn-mode-sim');
        const statusDot = document.getElementById('feed-status-dot');
        const statusText = document.getElementById('feed-status-text');

        if (mode === 'sim') {
            if (btnLive) {
                btnLive.className = 'px-2.5 py-1 rounded-lg font-bold text-xs text-slate-400 hover:text-white transition';
            }
            if (btnSim) {
                btnSim.className = 'px-2.5 py-1 rounded-lg font-bold text-xs bg-amber-600 text-white transition shadow-sm';
            }
            if (statusDot) statusDot.className = 'h-2 w-2 rounded-full bg-amber-400 animate-pulse';
            if (statusText) statusText.textContent = 'Simulator Active';

            if (state.ws) state.ws.close();
            startSimulator();
        } else {
            if (btnLive) {
                btnLive.className = 'px-2.5 py-1 rounded-lg font-bold text-xs bg-emerald-600 text-white transition shadow-sm';
            }
            if (btnSim) {
                btnSim.className = 'px-2.5 py-1 rounded-lg font-bold text-xs text-slate-400 hover:text-white transition';
            }
            if (state.simInterval) clearInterval(state.simInterval);
            connectWebSocket();
        }
    }

    function updateSpotUI(spot) {
        const spotEl = document.getElementById('val-spot-price');
        if (spotEl) spotEl.textContent = `₹${spot.toFixed(2)}`;
    }

    // ── 13. EVENT LISTENERS & SETUP ──
    document.addEventListener('DOMContentLoaded', async () => {
        // Mode buttons
        const btnLive = document.getElementById('btn-mode-live');
        const btnSim = document.getElementById('btn-mode-sim');
        if (btnLive) btnLive.addEventListener('click', () => switchMode('live'));
        if (btnSim) btnSim.addEventListener('click', () => switchMode('sim'));

        // Sound Toggle
        const soundBtn = document.getElementById('btn-sound-toggle');
        const soundIcon = document.getElementById('sound-icon');
        if (soundBtn) {
            soundBtn.addEventListener('click', () => {
                state.soundEnabled = !state.soundEnabled;
                if (soundIcon) soundIcon.textContent = state.soundEnabled ? '🔔' : '🔕';
            });
        }

        // Target Contract Switcher
        const contractSel = document.getElementById('sel-target-contract');
        const domContractLabel = document.getElementById('dom-contract-name');

        function onTargetContractChanged() {
            if (!contractSel) return;
            const opt = contractSel.options[contractSel.selectedIndex];
            if (opt) {
                state.activeInstrumentKey = opt.value;
                state.activeInstrumentType = opt.dataset.type || 'FUT';
                state.activeStrike = parseInt(opt.dataset.strike || 0);

                if (domContractLabel) domContractLabel.textContent = opt.textContent.trim();
                subscribeActiveTarget();
            }
        }

        if (contractSel) {
            contractSel.addEventListener('change', onTargetContractChanged);
            onTargetContractChanged();
        }

        // Expiry changes (NIFTY 50 Expiries)
        const selExpiry = document.getElementById('sel-expiry');
        if (selExpiry) {
            selExpiry.addEventListener('change', () => {
                window.location.href = `?expiry=${selExpiry.value}`;
            });
        }

        // Initialize Engine
        await initProtobuf();
        connectWebSocket();
    });

    // Strategy Signals AJAX Refresh
    window.refreshStrategySignals = async function() {
        try {
            const resp = await fetch(`{{ route('api.order-flow.strategy-signals') }}?symbol=${state.symbol}&expiry=${state.expiry}`);
            const res = await resp.json();
            if (res.success && res.data) {
                state.strategySignals = res.data;
                const inspector = document.getElementById('raw-json-inspector');
                if (inspector) inspector.textContent = JSON.stringify(res.data, null, 2);
                evaluateConfluenceRadar();
            }
        } catch (e) {
            console.error('Failed refreshing signals:', e);
        }
    };

})();
</script>
@endsection
