@extends('layouts.app')

@section('title', '30-Depth Order Flow & High-Probability Strategy Confluence Radar')

@section('content')
<div class="w-full px-2 sm:px-3 py-1 space-y-2 text-slate-100 font-sans" id="order-flow-app">

    {{-- ════════════════════════ 1. COMPACT TOP HEADER & CONTROLS ════════════════════════ --}}
    <div class="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 shadow-lg backdrop-blur-md">
        <div class="flex flex-wrap items-center justify-between gap-2">
            
            {{-- Left: Brand, Underlying & Selectors in one tight line --}}
            <div class="flex items-center flex-wrap gap-2">
                <div class="flex items-center gap-1.5 pr-2.5 border-r border-slate-800">
                    <span class="flex h-2.5 w-2.5 relative">
                        <span id="pulse-indicator" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                        <span id="dot-indicator" class="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500"></span>
                    </span>
                    <h1 class="text-sm sm:text-base font-black tracking-tight bg-gradient-to-r from-rose-400 via-amber-300 to-emerald-400 bg-clip-text text-transparent">
                        🌊 30-DEPTH ORDER FLOW
                    </h1>
                </div>

                {{-- Underlying (Exclusively NIFTY 50) --}}
                <div class="flex items-center gap-1 bg-slate-950 px-2 py-0.5 rounded-lg border border-slate-800 text-xs">
                    <span class="text-[10px] text-slate-400 uppercase font-bold">Underlying:</span>
                    <span class="font-black text-white text-[11px] flex items-center gap-1">
                        <span>🇮🇳 NIFTY 50</span>
                        <span class="text-[9px] px-1 py-0.2 rounded bg-indigo-500/20 text-indigo-300 font-bold border border-indigo-500/40">FUT</span>
                    </span>
                </div>

                {{-- Expiry Selector --}}
                <div class="flex items-center gap-1 bg-slate-950 px-2 py-0.5 rounded-lg border border-slate-800 text-xs">
                    <span class="text-[10px] text-slate-400 uppercase font-bold">Expiry:</span>
                    <select id="sel-expiry" class="bg-transparent text-[11px] text-emerald-300 font-bold focus:outline-none cursor-pointer">
                        @foreach($expiries as $exp)
                            <option value="{{ $exp->expiry_date }}" {{ $exp->expiry_date == $selectedExpiry ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::parse($exp->expiry_date)->format('d M Y') }} {{ $exp->is_current ? '⭐' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Active Instrument to Stream 30-Depth --}}
                <div class="flex items-center gap-1 bg-slate-950 px-2 py-0.5 rounded-lg border border-slate-800 text-xs">
                    <span class="text-[10px] text-amber-400 uppercase font-bold">Stream Target:</span>
                    <select id="sel-target-contract" class="bg-transparent text-[11px] text-amber-200 font-bold focus:outline-none cursor-pointer max-w-[200px] truncate">
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
            <div class="flex items-center gap-1.5 text-xs">
                <div class="flex items-center bg-slate-950 p-0.5 rounded-lg border border-slate-800">
                    <button id="btn-mode-live" class="px-2 py-0.5 rounded font-bold text-[11px] bg-emerald-600 text-white transition shadow-sm">
                        🔴 Live Feed
                    </button>
                    <button id="btn-mode-sim" class="px-2 py-0.5 rounded font-bold text-[11px] text-slate-400 hover:text-white transition">
                        🧪 Simulator
                    </button>
                </div>

                {{-- Status Pill --}}
                <div id="feed-status-pill" class="flex items-center gap-1 px-2 py-0.5 rounded-lg text-[11px] font-semibold bg-slate-950 border border-slate-800 text-slate-300">
                    <span id="feed-status-dot" class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                    <span id="feed-status-text">Connecting...</span>
                </div>

                {{-- Sound Alert Toggle --}}
                <button id="btn-sound-toggle" title="Toggle audio alert on high confluence signals" class="p-1 rounded-lg bg-slate-950 border border-slate-800 hover:bg-slate-800 text-slate-300 hover:text-white transition text-xs">
                    <span id="sound-icon">🔔</span>
                </button>
            </div>
        </div>

        {{-- Single-Row Compact Metrics Bar --}}
        <div class="grid grid-cols-2 sm:grid-cols-6 gap-1.5 mt-1.5 pt-1.5 border-t border-slate-800/80 text-[11px]">
            <div class="bg-slate-950/70 px-2 py-1 rounded-lg border border-slate-800 flex items-center justify-between">
                <span class="text-[9px] uppercase font-bold text-slate-400">Index Spot</span>
                <div class="flex items-baseline gap-1">
                    <span id="val-spot-price" class="font-black text-white text-xs">₹{{ number_format($spotPrice, 2) }}</span>
                    <span id="val-spot-change" class="text-[9px] font-bold text-emerald-400">+0.00%</span>
                </div>
            </div>

            <div class="bg-slate-950/70 px-2 py-1 rounded-lg border border-slate-800 flex items-center justify-between">
                <span class="text-[9px] uppercase font-bold text-slate-400">Target LTP</span>
                <div class="flex items-baseline gap-1">
                    <span id="val-target-ltp" class="font-black text-amber-300 text-xs">₹--</span>
                    <span id="val-target-tick-dir" class="text-[9px] font-bold text-slate-400">--</span>
                </div>
            </div>

            <div class="bg-slate-950/70 px-2 py-1 rounded-lg border border-slate-800 flex items-center justify-between">
                <span class="text-[9px] uppercase font-bold text-slate-400">Spread</span>
                <div class="flex items-baseline gap-0.5">
                    <span id="val-spread" class="font-black text-slate-200 text-xs">0.05</span>
                    <span class="text-[9px] text-slate-500">pts</span>
                </div>
            </div>

            <div class="bg-slate-950/70 px-2 py-1 rounded-lg border border-slate-800 flex items-center justify-between">
                <span class="text-[9px] uppercase font-bold text-slate-400">ATP (VWAP)</span>
                <div class="flex items-baseline gap-1">
                    <span id="val-atp" class="font-black text-cyan-300 text-xs">₹--</span>
                    <span id="val-atp-status" class="text-[9px] font-bold text-emerald-400">Above</span>
                </div>
            </div>

            <div class="bg-slate-950/70 px-2 py-1 rounded-lg border border-slate-800 flex items-center justify-between">
                <span class="text-[9px] uppercase font-bold text-slate-400">TBQ / TSQ</span>
                <div class="flex items-baseline gap-0.5">
                    <span id="val-ratio-tbq-tsq" class="font-black text-emerald-400 text-xs">--</span>
                    <span class="text-[9px] text-slate-400">Ratio</span>
                </div>
            </div>

            <div class="bg-slate-950/70 px-2 py-1 rounded-lg border border-slate-800 flex items-center justify-between">
                <span class="text-[9px] uppercase font-bold text-slate-400">Confluence</span>
                <div class="flex items-baseline gap-1">
                    <span id="val-hero-confluence" class="font-black text-rose-400 text-xs">--%</span>
                    <span id="val-hero-badge" class="px-1 py-0.2 rounded text-[9px] font-bold bg-slate-800 text-slate-300">WAIT</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ════════════════════════ 2. THREE-COLUMN COMMAND DASHBOARD (NO SCROLL) ════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-2.5 items-start">
        
        {{-- ───────────────────── COLUMN 1: LIVE 30-LEVEL ORDER BOOK (DOM) ───────────────────── --}}
        <div class="lg:col-span-5 bg-slate-900 border border-slate-800 rounded-xl p-2.5 shadow-xl flex flex-col justify-between">
            <div>
                {{-- DOM Header with Target & Legend --}}
                <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-800 text-xs">
                    <div class="flex items-center gap-1.5">
                        <span class="font-black text-white text-xs sm:text-sm">🌊 30-Level Order Book (DOM)</span>
                        <span id="dom-contract-name" class="text-[10px] font-bold text-amber-300 bg-amber-500/10 px-1.5 py-0.5 rounded border border-amber-500/30 truncate max-w-[150px]">
                            Loading...
                        </span>
                    </div>

                    <div class="flex items-center gap-2 text-[10px]">
                        <span class="text-emerald-400 font-bold">● Bids</span>
                        <span class="text-rose-400 font-bold">● Asks</span>
                        <span class="text-amber-300 font-bold">■ Wall</span>
                    </div>
                </div>

                {{-- Prominent Current Trading Price (LTP) Sticky Highlight Bar --}}
                <div id="dom-ltp-highlight-banner" class="mb-1.5 py-1 px-2.5 rounded-lg bg-gradient-to-r from-amber-500/15 via-amber-500/25 to-amber-500/15 border border-amber-400/80 flex items-center justify-between text-xs font-black shadow-md shadow-amber-500/5">
                    <div class="flex items-center gap-1.5">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-400"></span>
                        </span>
                        <span class="text-slate-300 text-[10px] uppercase tracking-wider font-extrabold">Current Trading Price (LTP):</span>
                        <span id="banner-ltp-val" class="text-xs sm:text-sm font-black text-white bg-slate-950 px-2 py-0.2 rounded border border-amber-400/60 font-mono">
                            ₹--
                        </span>
                    </div>
                    <div class="flex items-center gap-2 text-[10px]">
                        <span class="text-slate-400">Spread: <strong id="banner-spread-val" class="text-white font-mono">0.05 pts</strong></span>
                        <span id="banner-vwap-pill" class="px-1.5 py-0.2 rounded bg-slate-950 text-cyan-300 border border-slate-800 font-mono">
                            VWAP: ₹--
                        </span>
                    </div>
                </div>

                {{-- DOM Table Column Headers --}}
                <div class="grid grid-cols-12 gap-1 text-[10px] font-black text-slate-400 uppercase bg-slate-950 px-1.5 py-1 rounded-lg mb-1 border border-slate-800">
                    <div class="col-span-2 text-left">Orders</div>
                    <div class="col-span-2 text-right">Bid Qty</div>
                    <div class="col-span-2 text-right text-emerald-400">Bid ₹</div>
                    <div class="col-span-2 text-left text-rose-400 pl-1.5">Ask ₹</div>
                    <div class="col-span-2 text-left pl-1">Ask Qty</div>
                    <div class="col-span-2 text-right">Orders</div>
                </div>

                {{-- 30-Depth Scrollable Visual Rows (Auto fits ~550px height) --}}
                <div id="dom-rows-container" class="space-y-0.5 max-h-[calc(100vh-255px)] min-h-[460px] overflow-y-auto pr-0.5 select-none font-mono text-[11px]">
                    <div class="text-center py-16 text-slate-500 text-xs">
                        Connecting to Upstox 30-Depth Live Feed...
                    </div>
                </div>
            </div>

            {{-- Depth Summary Footer Bar --}}
            <div class="grid grid-cols-2 gap-2 mt-1.5 pt-1.5 border-t border-slate-800 text-[11px]">
                <div class="flex items-center justify-between px-2 py-1 rounded-lg bg-emerald-950/40 border border-emerald-900/50">
                    <span class="text-emerald-400 font-bold text-[10px] uppercase">Total 30 Bid</span>
                    <span id="val-total-bid-depth" class="font-black text-emerald-300 font-mono">--</span>
                </div>

                <div class="flex items-center justify-between px-2 py-1 rounded-lg bg-rose-950/40 border border-rose-900/50">
                    <span class="text-rose-400 font-bold text-[10px] uppercase">Total 30 Ask</span>
                    <span id="val-total-ask-depth" class="font-black text-rose-300 font-mono">--</span>
                </div>
            </div>
        </div>

        {{-- ───────────────────── COLUMN 2: HIGH WATCHABLE STATS & CONFLUENCE ───────────────────── --}}
        <div class="lg:col-span-4 space-y-2.5">
            
            {{-- 1. Real-Time High Probability Trade Suggestion Card (TOP PRIORITY) --}}
            <div class="bg-gradient-to-br from-slate-900 via-slate-900 to-slate-950 border border-slate-800 rounded-xl p-3 shadow-xl relative overflow-hidden">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-1.5">
                        <span id="signal-badge-icon" class="text-lg">🔥</span>
                        <div>
                            <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Top Recommendation</span>
                            <h2 id="signal-main-text" class="text-sm sm:text-base font-black text-white leading-tight">
                                INITIALIZING CONFLUENCE...
                            </h2>
                        </div>
                    </div>
                    <span id="signal-confidence-pill" class="px-2 py-0.5 rounded-full text-[10px] font-black bg-slate-800 text-slate-300 border border-slate-700">
                        WAITING
                    </span>
                </div>

                {{-- Trade Parameters Grid --}}
                <div class="grid grid-cols-2 gap-1.5 text-xs">
                    <div class="bg-slate-950 p-2 rounded-lg border border-slate-800">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block">Instrument</span>
                        <p id="trade-instrument" class="text-xs font-black text-amber-300 truncate mt-0.5">NIFTY FUT</p>
                        <span id="trade-type" class="text-[9px] font-bold text-emerald-400">Momentum Scalp</span>
                    </div>

                    <div class="bg-slate-950 p-2 rounded-lg border border-slate-800">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block">Entry Zone</span>
                        <p id="trade-entry-zone" class="text-xs font-black text-white font-mono mt-0.5">₹--</p>
                        <span class="text-[9px] text-slate-400">Limit Pullback</span>
                    </div>

                    <div class="bg-slate-950 p-2 rounded-lg border border-slate-800">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block">Stop Loss (Wall)</span>
                        <p id="trade-sl" class="text-xs font-black text-rose-400 font-mono mt-0.5">₹--</p>
                        <span id="trade-sl-wall" class="text-[9px] text-slate-400 truncate block">Behind Bid Wall</span>
                    </div>

                    <div class="bg-slate-950 p-2 rounded-lg border border-slate-800">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block">Target (Wall)</span>
                        <p id="trade-target" class="text-xs font-black text-emerald-400 font-mono mt-0.5">₹--</p>
                        <span id="trade-rr" class="text-[9px] font-bold text-emerald-300">1 : 2.2 R:R</span>
                    </div>
                </div>

                {{-- Rationale Snippet --}}
                <div class="mt-2 bg-slate-950/70 p-2 rounded-lg border border-slate-800/80 text-[11px] text-slate-300 flex items-start gap-1.5">
                    <span class="text-amber-400 text-xs">💡</span>
                    <p id="trade-rationale" class="line-clamp-2 leading-relaxed">
                        Awaiting 30-depth order book alignment with quant and heavyweight breadth.
                    </p>
                </div>
            </div>

            {{-- 2. Confluence Scorecard & Alignment Meter --}}
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 shadow-xl space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-black uppercase text-white tracking-wider">Confluence Alignment Scorecard</span>
                    <span class="text-[10px] text-slate-400">6 Strategy Engines</span>
                </div>

                {{-- Alignment Bar --}}
                <div>
                    <div class="flex justify-between items-baseline mb-0.5">
                        <span class="text-[10px] text-slate-400">Total System Alignment</span>
                        <span id="confluence-meter-text" class="text-base font-black text-rose-400 font-mono">0%</span>
                    </div>
                    <div class="w-full bg-slate-950 rounded-full h-2.5 p-0.5 overflow-hidden border border-slate-800">
                        <div id="confluence-meter-bar" class="bg-gradient-to-r from-rose-500 via-amber-500 to-emerald-400 h-full rounded-full transition-all duration-500" style="width: 50%;"></div>
                    </div>
                    <div class="flex justify-between text-[9px] text-slate-500 mt-0.5 font-bold">
                        <span>0% Bear</span>
                        <span>50% Neutral Chop</span>
                        <span>100% Bull</span>
                    </div>
                </div>

                {{-- 6-Engine Badges Grid --}}
                <div class="grid grid-cols-2 gap-1.5 text-[10px]">
                    <div id="engine-card-depth" class="flex items-center justify-between p-1.5 rounded-lg bg-slate-950 border border-slate-800">
                        <span class="text-slate-400">30-Depth OBI</span>
                        <span class="font-bold text-amber-300 font-mono">0.0% (Bal)</span>
                    </div>

                    <div id="engine-card-micro" class="flex items-center justify-between p-1.5 rounded-lg bg-slate-950 border border-slate-800">
                        <span class="text-slate-400">Micro-Price Drift</span>
                        <span class="font-bold text-slate-300 font-mono">0.00 pts</span>
                    </div>

                    <div id="engine-card-wall" class="flex items-center justify-between p-1.5 rounded-lg bg-slate-950 border border-slate-800">
                        <span class="text-slate-400">Wall Absorption</span>
                        <span class="font-bold text-slate-300">Neutral</span>
                    </div>

                    <div id="engine-card-quant" class="flex items-center justify-between p-1.5 rounded-lg bg-slate-950 border border-slate-800">
                        <span class="text-slate-400">Quant POP</span>
                        <span class="font-bold text-emerald-400">{{ $strategySignals['factors']['quant_engine']['signal'] ?? 'SAFE' }}</span>
                    </div>

                    <div id="engine-card-oi" class="flex items-center justify-between p-1.5 rounded-lg bg-slate-950 border border-slate-800">
                        <span class="text-slate-400">OI Flow / PCR</span>
                        <span class="font-bold text-emerald-400">{{ $strategySignals['factors']['oi_buildup']['signal'] ?? 'NEUTRAL' }}</span>
                    </div>

                    <div id="engine-card-breadth" class="flex items-center justify-between p-1.5 rounded-lg bg-slate-950 border border-slate-800">
                        <span class="text-slate-400">Breadth</span>
                        <span class="font-bold text-slate-300">{{ $strategySignals['factors']['market_breadth']['signal'] ?? 'NEUTRAL' }}</span>
                    </div>
                </div>
            </div>

            {{-- 3. Tiered Order Book Imbalance (OBI) --}}
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 shadow-xl space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-black uppercase text-white tracking-wider">Tiered Order Book Imbalance (OBI)</span>
                    <span class="text-[9px] text-slate-400">(B - A) / (B + A)</span>
                </div>

                {{-- Top 5 (Near Book) --}}
                <div class="space-y-0.5">
                    <div class="flex justify-between text-[10px]">
                        <span class="text-slate-400">Near Book (Top 5 Levels - Retail)</span>
                        <span id="val-obi-5" class="font-bold text-slate-200 font-mono">0.0%</span>
                    </div>
                    <div class="w-full bg-slate-950 h-2 rounded-full overflow-hidden flex border border-slate-800">
                        <div id="bar-obi-5-bid" class="bg-emerald-500 h-full transition-all duration-300" style="width: 50%;"></div>
                        <div id="bar-obi-5-ask" class="bg-rose-500 h-full transition-all duration-300" style="width: 50%;"></div>
                    </div>
                </div>

                {{-- Top 10 (Mid Book) --}}
                <div class="space-y-0.5">
                    <div class="flex justify-between text-[10px]">
                        <span class="text-slate-400">Mid Book (Top 10 Levels - Scalpers)</span>
                        <span id="val-obi-10" class="font-bold text-slate-200 font-mono">0.0%</span>
                    </div>
                    <div class="w-full bg-slate-950 h-2 rounded-full overflow-hidden flex border border-slate-800">
                        <div id="bar-obi-10-bid" class="bg-emerald-500 h-full transition-all duration-300" style="width: 50%;"></div>
                        <div id="bar-obi-10-ask" class="bg-rose-500 h-full transition-all duration-300" style="width: 50%;"></div>
                    </div>
                </div>

                {{-- Top 30 (Full Deep Book) --}}
                <div class="space-y-0.5">
                    <div class="flex justify-between text-[10px]">
                        <span class="text-emerald-400 font-bold">Deep Book (All 30 Levels - Institutions)</span>
                        <span id="val-obi-30" class="font-black text-emerald-400 font-mono">0.0%</span>
                    </div>
                    <div class="w-full bg-slate-950 h-2 rounded-full overflow-hidden flex border border-slate-800">
                        <div id="bar-obi-30-bid" class="bg-emerald-500 h-full transition-all duration-300" style="width: 50%;"></div>
                        <div id="bar-obi-30-ask" class="bg-rose-500 h-full transition-all duration-300" style="width: 50%;"></div>
                    </div>
                </div>
            </div>

        </div>

        {{-- ───────────────────── COLUMN 3: LEAD DRIFT, WALLS & DEEP-DIVE TABS ───────────────────── --}}
        <div class="lg:col-span-3 space-y-2.5">
            
            {{-- 1. Depth-Weighted Micro-Price & Drift --}}
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 shadow-xl space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-black uppercase text-white tracking-wider">Micro-Price & Lead Drift</span>
                    <span id="val-micro-divergence" class="text-[10px] font-bold text-cyan-300 font-mono">+0.00 pts</span>
                </div>

                <div class="grid grid-cols-3 gap-1.5 text-center text-xs">
                    <div class="bg-slate-950 p-1.5 rounded-lg border border-slate-800">
                        <span class="text-[9px] text-slate-400 block uppercase font-bold">Std Mid</span>
                        <span id="val-mid-price" class="font-bold text-white text-[11px] font-mono mt-0.5 block">--</span>
                    </div>

                    <div class="bg-slate-950 p-1.5 rounded-lg border border-cyan-800/60">
                        <span class="text-[9px] text-cyan-400 block uppercase font-black">Micro</span>
                        <span id="val-micro-price" class="font-black text-cyan-300 text-[11px] font-mono mt-0.5 block">--</span>
                    </div>

                    <div class="bg-slate-950 p-1.5 rounded-lg border border-slate-800">
                        <span class="text-[9px] text-slate-400 block uppercase font-bold">LTP</span>
                        <span id="val-side-ltp" class="font-bold text-amber-300 text-[11px] font-mono mt-0.5 block">--</span>
                    </div>
                </div>

                <p class="text-[10px] text-slate-400 leading-tight">
                    Micro-Price incorporates order volume into the spread to lead the next market tick.
                </p>
            </div>

            {{-- 2. Active Liquidity Walls & Absorption / Spoofing Tracker --}}
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 shadow-xl space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-black uppercase text-white tracking-wider">Liquidity Walls (≥2.2x)</span>
                    <span id="wall-spoof-score" class="text-[10px] font-bold text-emerald-400">Genuine Orders</span>
                </div>

                {{-- Active Walls Mini List --}}
                <div id="liquidity-walls-list" class="space-y-1 text-xs">
                    <div class="p-2 rounded-lg bg-slate-950 border border-slate-800 text-slate-400 text-center text-[10px]">
                        Scanning 30-depth order book for walls...
                    </div>
                </div>

                {{-- Absorption Meter --}}
                <div class="pt-1 border-t border-slate-800/80 space-y-1">
                    <div class="flex justify-between items-center text-[10px]">
                        <span class="text-slate-400">Wall Absorption Progress:</span>
                        <span id="val-absorption-pct" class="font-bold text-amber-300 font-mono">0%</span>
                    </div>
                    <div class="w-full bg-slate-950 h-1.5 rounded-full overflow-hidden border border-slate-800">
                        <div id="bar-absorption" class="bg-gradient-to-r from-amber-500 to-emerald-400 h-full transition-all duration-300" style="width: 0%;"></div>
                    </div>
                </div>
            </div>

            {{-- 3. Interactive Mini-Tabs for Deep-Dive Debugging --}}
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 shadow-xl space-y-2">
                <div class="flex items-center justify-between border-b border-slate-800 pb-1.5">
                    <span class="text-[11px] font-black uppercase text-white tracking-wider">Engine Inspector</span>
                    
                    {{-- Mini Tab Buttons --}}
                    <div class="flex items-center gap-1 text-[10px]">
                        <button onclick="switchMiniTab('mini-trace')" id="btn-mini-trace" class="mini-tab-btn px-2 py-0.5 rounded bg-indigo-600 text-white font-bold transition">
                            Math
                        </button>
                        <button onclick="switchMiniTab('mini-vacuum')" id="btn-mini-vacuum" class="mini-tab-btn px-2 py-0.5 rounded bg-slate-800 text-slate-300 font-bold hover:text-white transition">
                            Vacuum
                        </button>
                        <button onclick="switchMiniTab('mini-json')" id="btn-mini-json" class="mini-tab-btn px-2 py-0.5 rounded bg-slate-800 text-slate-300 font-bold hover:text-white transition">
                            JSON
                        </button>
                    </div>
                </div>

                {{-- Mini Tab Pane 1: Math Trace --}}
                <div id="pane-mini-trace" class="mini-tab-pane space-y-1 font-mono text-[10px]">
                    <div class="flex justify-between p-1 rounded bg-slate-950">
                        <span class="text-slate-400">Numerator (B - A):</span>
                        <span id="trace-obi-numerator" class="text-white font-bold">0</span>
                    </div>
                    <div class="flex justify-between p-1 rounded bg-slate-950">
                        <span class="text-slate-400">Denominator (B + A):</span>
                        <span id="trace-obi-denominator" class="text-white font-bold">0</span>
                    </div>
                    <div class="flex justify-between p-1 rounded bg-slate-950">
                        <span class="text-slate-400">Raw Ratio:</span>
                        <span id="trace-obi-raw" class="text-emerald-400 font-bold">0.000</span>
                    </div>
                    <div class="flex justify-between p-1 rounded bg-slate-950 border border-emerald-500/40">
                        <span class="text-emerald-300 font-bold">30-Depth OBI:</span>
                        <span id="trace-obi-final" class="text-emerald-300 font-black">0.0%</span>
                    </div>
                </div>

                {{-- Mini Tab Pane 2: Vacuum & Air Pockets --}}
                <div id="pane-mini-vacuum" class="mini-tab-pane hidden space-y-1.5 text-[11px]">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400 text-[10px]">Air Pocket Status:</span>
                        <span id="vacuum-status-badge" class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-slate-950 text-slate-300 border border-slate-800">NORMAL BOOK</span>
                    </div>
                    <div id="vacuum-alert-box" class="p-2 rounded-lg bg-slate-950 border border-slate-800 text-[10px] text-slate-400">
                        No continuous air pocket detected. 30-depth order flow is continuous.
                    </div>
                </div>

                {{-- Mini Tab Pane 3: Raw Strategy JSON --}}
                <div id="pane-mini-json" class="mini-tab-pane hidden space-y-1.5">
                    <div class="flex items-center justify-between text-[10px]">
                        <span class="text-slate-400">Live Strategy Signals:</span>
                        <button onclick="refreshStrategySignals()" class="text-indigo-400 hover:text-indigo-300 font-bold">
                            🔄 Refresh
                        </button>
                    </div>
                    <pre id="raw-json-inspector" class="bg-slate-950 p-2 rounded-lg border border-slate-800 text-[9px] text-emerald-400 font-mono overflow-x-auto max-h-32">{{ json_encode($strategySignals, JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>

        </div>

    </div>

</div>

{{-- ════════════════════════ 3. SCRIPTS: PROTOBUF, WEBSOCKET & CONFLUENCE ENGINE ════════════════════════ --}}
<script src="https://cdn.jsdelivr.net/npm/protobufjs@7.2.5/dist/protobuf.min.js"></script>

<script>
(function() {
    'use strict';

    // ── 1. GLOBAL STATE ──
    const state = {
        symbol: "NIFTY",
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
            bids: [],
            asks: [],
            ltp: 0,
            atp: 0,
            vtt: 0,
            tbq: 0,
            tsq: 0,
            spread: 0.05,
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

    // ── 2. MINI-TAB SWITCHER ──
    window.switchMiniTab = function(tabName) {
        document.querySelectorAll('.mini-tab-pane').forEach(el => el.classList.add('hidden'));
        const target = document.getElementById('pane-' + tabName);
        if (target) target.classList.remove('hidden');

        document.querySelectorAll('.mini-tab-btn').forEach(btn => {
            btn.classList.remove('bg-indigo-600', 'text-white');
            btn.classList.add('bg-slate-800', 'text-slate-300');
        });

        const activeBtn = document.getElementById('btn-' + tabName);
        if (activeBtn) {
            activeBtn.classList.remove('bg-slate-800', 'text-slate-300');
            activeBtn.classList.add('bg-indigo-600', 'text-white');
        }
    };

    // ── 3. INITIALIZE PROTOBUF ──
    async function initProtobuf() {
        try {
            state.protobufRoot = await protobuf.load('/MarketDataFeed_v3.proto');
            console.log('✅ MarketDataFeed_v3.proto loaded successfully for 30-depth stream.');
        } catch (e) {
            console.warn('⚠️ Could not load MarketDataFeed_v3.proto, running in simulator mode:', e);
            window.switchMode('sim');
        }
    }

    // ── 4. UPSTOX WEBSOCKET CONNECTION ──
    async function connectWebSocket() {
        if (state.feedMode !== 'live') return;

        const statusDot = document.getElementById('feed-status-dot');
        const statusText = document.getElementById('feed-status-text');

        if (statusDot) statusDot.className = 'h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse';
        if (statusText) statusText.textContent = 'Authorizing...';

        try {
            const resp = await fetch('{{ route("api.order-flow.ws-url") }}');
            const json = await resp.json();

            const uri = json.authorizedRedirectUri || json.data?.data?.authorizedRedirectUri || json.data?.authorizedRedirectUri;

            if (!uri) {
                console.warn('Upstox WebSocket auth not available, falling back to Micro-Simulator:', json);
                if (statusDot) statusDot.className = 'h-1.5 w-1.5 rounded-full bg-rose-500';
                if (statusText) statusText.textContent = 'Token Expired';
                window.switchMode('sim');
                return;
            }

            state.wsUrl = uri;
            if (state.ws) {
                state.ws.close();
            }

            state.ws = new WebSocket(state.wsUrl);
            state.ws.binaryType = 'arraybuffer';

            state.ws.onopen = () => {
                console.log('🔌 Upstox WebSocket connected.');
                if (statusDot) statusDot.className = 'h-1.5 w-1.5 rounded-full bg-emerald-400';
                if (statusText) statusText.textContent = 'Live Feed Active';
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
                }
            };

            state.ws.onmessage = (event) => {
                decodeProtobufFeed(event.data);
            };

            state.ws.onclose = () => {
                if (state.feedMode === 'live') {
                    if (statusDot) statusDot.className = 'h-1.5 w-1.5 rounded-full bg-rose-500';
                    if (statusText) statusText.textContent = 'Disconnected, retrying in 5s...';
                    setTimeout(connectWebSocket, 5000);
                }
            };

            state.ws.onerror = (err) => {
                console.error('WebSocket error:', err);
                if (statusDot) statusDot.className = 'h-1.5 w-1.5 rounded-full bg-rose-500';
                if (statusText) statusText.textContent = 'Feed Error';
            };

        } catch (e) {
            console.error('Exception establishing Upstox feed:', e);
            window.switchMode('sim');
        }
    }

    // ── 5. SUBSCRIBE IN "full_d30" MODE ──
    function subscribeActiveTarget() {
        if (!state.ws || state.ws.readyState !== WebSocket.OPEN) return;
        if (!state.activeInstrumentKey) return;

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

        bids.forEach((b, idx) => {
            if (b.qty >= avgBidQ * 2.2 && b.qty > 500) {
                walls.push({ side: 'BID', price: b.price, qty: b.qty, level: idx + 1, multiple: (b.qty / avgBidQ).toFixed(1) });
            }
        });

        asks.forEach((a, idx) => {
            if (a.qty >= avgAskQ * 2.2 && a.qty > 500) {
                walls.push({ side: 'ASK', price: a.price, qty: a.qty, level: idx + 1, multiple: (a.qty / avgAskQ).toFixed(1) });
            }
        });

        state.depthData.liquidityWalls = walls;
    }

    // ── 9. RENDER 30-DEPTH DOM VISUAL LADDER WITH LTP HIGHLIGHT ──
    function renderDOM() {
        const container = document.getElementById('dom-rows-container');
        if (!container) return;

        const bids = state.depthData.bids;
        const asks = state.depthData.asks;
        const ltp = state.depthData.ltp;

        // Update the top sticky LTP Highlight banner
        const bannerLtp = document.getElementById('banner-ltp-val');
        const bannerSpread = document.getElementById('banner-spread-val');
        const bannerVwap = document.getElementById('banner-vwap-pill');
        if (bannerLtp) bannerLtp.textContent = `₹${ltp.toFixed(2)}`;
        if (bannerSpread) bannerSpread.textContent = `${state.depthData.spread.toFixed(2)} pts`;
        if (bannerVwap) bannerVwap.textContent = `VWAP: ₹${state.depthData.atp > 0 ? state.depthData.atp.toFixed(2) : ltp.toFixed(2)}`;

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

            // Highlight row closest to LTP
            const isNearLtp = (b && Math.abs(b.price - ltp) < 0.05) || (a && Math.abs(a.price - ltp) < 0.05);

            html += `
            <div class="grid grid-cols-12 gap-1 items-center py-0.5 px-1.5 rounded transition text-[11px] relative ${isNearLtp ? 'bg-amber-500/20 ring-1 ring-amber-400 font-bold' : (i % 2 === 0 ? 'bg-slate-950/40 hover:bg-slate-800/60' : 'hover:bg-slate-800/60')}">
                
                {{-- Bid Orders --}}
                <div class="col-span-2 text-left text-slate-400 text-[10px]">
                    ${b ? (b.orders || 1) : '-'}
                </div>

                {{-- Bid Qty with horizontal fill bar --}}
                <div class="col-span-2 text-right relative pr-1.5">
                    <div class="absolute right-0 top-0 bottom-0 bg-emerald-500/20 rounded-l ${isBidWall ? 'border-r-2 border-amber-400' : ''}" style="width: ${bidPct}%;"></div>
                    <span class="relative font-mono font-bold text-slate-100 ${isBidWall ? 'text-amber-300 font-black' : ''}">
                        ${b ? b.qty.toLocaleString() : '-'}
                    </span>
                </div>

                {{-- Bid Price --}}
                <div class="col-span-2 text-right font-mono font-black text-emerald-400 text-xs">
                    ${b ? b.price.toFixed(2) : '-'}
                </div>

                {{-- Ask Price --}}
                <div class="col-span-2 text-left font-mono font-black text-rose-400 text-xs pl-1.5">
                    ${a ? a.price.toFixed(2) : '-'}
                </div>

                {{-- Ask Qty with horizontal fill bar --}}
                <div class="col-span-2 text-left relative pl-1.5">
                    <div class="absolute left-0 top-0 bottom-0 bg-rose-500/20 rounded-r ${isAskWall ? 'border-l-2 border-amber-400' : ''}" style="width: ${askPct}%;"></div>
                    <span class="relative font-mono font-bold text-slate-100 ${isAskWall ? 'text-amber-300 font-black' : ''}">
                        ${a ? a.qty.toLocaleString() : '-'}
                    </span>
                </div>

                {{-- Ask Orders --}}
                <div class="col-span-2 text-right text-slate-400 text-[10px]">
                    ${a ? (a.orders || 1) : '-'}
                </div>

            </div>
            `;

            // If Level 1 (i == 0), inject the prominent LTP Spread Center Divider
            if (i === 0 && ltp > 0) {
                html += `
                <div class="my-0.5 py-0.5 px-2 rounded bg-amber-500/15 border-y border-amber-400/60 flex items-center justify-between text-[10px] font-black text-amber-300 shadow-sm">
                    <div class="flex items-center gap-1">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-ping"></span>
                        <span>◄ CURRENT TRADING PRICE (LTP):</span>
                        <span class="text-white bg-slate-900 px-1.5 py-0.2 rounded font-mono text-xs border border-amber-400/40">₹${ltp.toFixed(2)}</span>
                    </div>
                    <div class="text-[9px] text-slate-300 font-mono">
                        Inside Spread: <strong class="text-white">${state.depthData.spread.toFixed(2)} pts</strong>
                    </div>
                </div>
                `;
            }
        }

        container.innerHTML = html;

        // Update totals
        const bidTotEl = document.getElementById('val-total-bid-depth');
        const askTotEl = document.getElementById('val-total-ask-depth');
        if (bidTotEl) bidTotEl.textContent = totalBidSum.toLocaleString();
        if (askTotEl) askTotEl.textContent = totalAskSum.toLocaleString();
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
            ratioEl.className = ratio >= 1.2 ? 'font-black text-emerald-400 text-xs' : (ratio <= 0.8 ? 'font-black text-rose-400 text-xs' : 'font-black text-slate-300 text-xs');
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
            microDivEl.textContent = `${div >= 0 ? '+' : ''}${div.toFixed(2)} pts`;
            microDivEl.className = div > 0.1 ? 'text-[10px] font-bold text-emerald-400 font-mono' : (div < -0.1 ? 'text-[10px] font-bold text-rose-400 font-mono' : 'text-[10px] font-bold text-slate-400 font-mono');
        }

        // Math Trace Data
        const traceNum = document.getElementById('trace-obi-numerator');
        const traceDen = document.getElementById('trace-obi-denominator');
        const traceRaw = document.getElementById('trace-obi-raw');
        const traceFin = document.getElementById('trace-obi-final');

        let sumB30 = d.bids.slice(0, 30).reduce((a, b) => a + b.qty, 0);
        let sumA30 = d.asks.slice(0, 30).reduce((a, b) => a + b.qty, 0);

        if (traceNum) traceNum.textContent = (sumB30 - sumA30).toLocaleString();
        if (traceDen) traceDen.textContent = (sumB30 + sumA30).toLocaleString();
        if (traceRaw) traceRaw.textContent = (sumB30 + sumA30 > 0 ? (sumB30 - sumA30) / (sumB30 + sumA30) : 0).toFixed(3);
        if (traceFin) traceFin.textContent = `${d.obi30}%`;

        // Liquidity Walls Cards
        renderLiquidityWallsUI();
    }

    function updateObiBar(tier, val) {
        const valEl = document.getElementById(`val-obi-${tier}`);
        const bidBar = document.getElementById(`bar-obi-${tier}-bid`);
        const askBar = document.getElementById(`bar-obi-${tier}-ask`);

        if (valEl) {
            valEl.textContent = `${val >= 0 ? '+' : ''}${val}%`;
            valEl.className = val >= 25 ? 'font-bold text-emerald-400 font-mono' : (val <= -25 ? 'font-bold text-rose-400 font-mono' : 'font-bold text-slate-300 font-mono');
        }

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
            list.innerHTML = `<div class="p-1.5 rounded-lg bg-slate-950 border border-slate-800 text-slate-400 text-center text-[10px]">No institutional walls detected.</div>`;
            return;
        }

        let html = '';
        walls.slice(0, 3).forEach(w => {
            const isBid = w.side === 'BID';
            html += `
            <div class="flex items-center justify-between p-1.5 rounded-lg bg-slate-950 border ${isBid ? 'border-emerald-800/50' : 'border-rose-800/50'} text-[11px]">
                <div class="flex items-center gap-1">
                    <span class="px-1 py-0.2 rounded text-[9px] font-black ${isBid ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300'}">
                        ${w.side} (L${w.level})
                    </span>
                    <span class="font-bold text-white font-mono">₹${w.price.toFixed(2)}</span>
                </div>
                <div class="flex items-center gap-1 font-mono">
                    <span class="font-black ${isBid ? 'text-emerald-400' : 'text-rose-400'}">${w.qty.toLocaleString()}</span>
                    <span class="text-[9px] text-amber-400 font-bold">(${w.multiple}x)</span>
                </div>
            </div>
            `;
        });

        list.innerHTML = html;
    }

    // ── 11. MULTI-STRATEGY CONFLUENCE & TRADE SUGGESTION ENGINE ──
    function evaluateConfluenceRadar() {
        const d = state.depthData;
        const signals = state.strategySignals?.factors || {};

        let quantScore = signals.quant_engine ? signals.quant_engine.score : 50;
        let oiScore = signals.oi_buildup ? signals.oi_buildup.score : 50;
        let breadthScore = signals.market_breadth ? signals.market_breadth.score : 50;

        let depthScore = 50 + (d.obi30 * 0.45);
        if (d.microPrice > d.ltp + 0.1) depthScore += 8;
        if (d.microPrice < d.ltp - 0.1) depthScore -= 8;
        depthScore = Math.max(5, Math.min(95, Math.round(depthScore)));

        const totalConfluence = Math.round(
            (quantScore * 0.25) +
            (oiScore * 0.25) +
            (breadthScore * 0.20) +
            (depthScore * 0.30)
        );

        const meterText = document.getElementById('confluence-meter-text');
        const meterBar = document.getElementById('confluence-meter-bar');
        const heroConf = document.getElementById('val-hero-confluence');
        const heroBadge = document.getElementById('val-hero-badge');

        if (meterText) meterText.textContent = `${totalConfluence}%`;
        if (heroConf) heroConf.textContent = `${totalConfluence}%`;
        if (meterBar) meterBar.style.width = `${totalConfluence}%`;

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
            if (sigText) sigText.textContent = '🚀 HIGH CONFIDENCE BUY / LONG';
            if (sigPill) {
                sigPill.textContent = 'STRONG BUY';
                sigPill.className = 'px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-500 text-slate-950';
            }
            if (sigIcon) sigIcon.textContent = '🔥';
            if (heroBadge) {
                heroBadge.textContent = 'STRONG BUY';
                heroBadge.className = 'px-1 py-0.2 rounded text-[9px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40';
            }

            const bidWall = d.liquidityWalls.find(w => w.side === 'BID');
            const askWall = d.liquidityWalls.find(w => w.side === 'ASK');

            const slPrice = bidWall ? (bidWall.price - 0.20).toFixed(2) : (d.ltp * 0.96).toFixed(2);
            const targetPrice = askWall ? (askWall.price).toFixed(2) : (d.ltp * 1.08).toFixed(2);

            if (tradeInst) tradeInst.textContent = targetName;
            if (tradeEntry) tradeEntry.textContent = `₹${(d.ltp - 0.15).toFixed(2)} - ₹${d.ltp.toFixed(2)}`;
            if (tradeSl) tradeSl.textContent = `₹${slPrice}`;
            if (tradeTarget) tradeTarget.textContent = `₹${targetPrice}`;
            if (tradeRationale) {
                tradeRationale.textContent = `30-Depth OBI (+${d.obi30}%) confirms buying force, reinforced by Quant POP and Heavyweight Breadth.`;
            }

            triggerAlertAudio('BUY');

        } else if (totalConfluence <= 32) {
            if (sigText) sigText.textContent = '🩸 HIGH CONFIDENCE SELL / SHORT';
            if (sigPill) {
                sigPill.textContent = 'STRONG SELL';
                sigPill.className = 'px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-500 text-white';
            }
            if (sigIcon) sigIcon.textContent = '🩸';
            if (heroBadge) {
                heroBadge.textContent = 'STRONG SELL';
                heroBadge.className = 'px-1 py-0.2 rounded text-[9px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40';
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
                tradeRationale.textContent = `Ask Wall dominating with negative OBI (${d.obi30}%). Downside bias confirmed across strategies.`;
            }

            triggerAlertAudio('SELL');

        } else {
            if (sigText) sigText.textContent = '⏸️ BALANCED / WAIT FOR SIGNAL';
            if (sigPill) {
                sigPill.textContent = 'NEUTRAL';
                sigPill.className = 'px-2 py-0.5 rounded-full text-[10px] font-black bg-slate-800 text-slate-400 border border-slate-700';
            }
            if (sigIcon) sigIcon.textContent = '⚖️';
            if (heroBadge) {
                heroBadge.textContent = 'NEUTRAL CHOP';
                heroBadge.className = 'px-1 py-0.2 rounded text-[9px] font-bold bg-slate-800 text-slate-400';
            }

            if (tradeInst) tradeInst.textContent = targetName;
            if (tradeEntry) tradeEntry.textContent = `₹${d.ltp.toFixed(2)}`;
            if (tradeSl) tradeSl.textContent = `Wait for Breakout`;
            if (tradeTarget) tradeTarget.textContent = `Range Bound`;
            if (tradeRationale) {
                tradeRationale.textContent = `Order book in equilibrium (OBI: ${d.obi30}%). Stand aside until clear confluence appears.`;
            }
        }
    }

    function triggerAlertAudio(type) {
        if (!state.soundEnabled) return;
        const now = Date.now();
        if (now - state.lastAlertTime < 30000) return;
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
            // Audio context blocked
        }
    }

    // ── 12. HIGH-FIDELITY MICRO-SIMULATOR (FOR OFF-MARKET TESTING) ──
    function startSimulator() {
        if (state.simInterval) clearInterval(state.simInterval);

        let basePrice = 24150.0;
        let trendBias = 1;

        state.simInterval = setInterval(() => {
            basePrice += (Math.random() - 0.48) * 0.4 * trendBias;
            basePrice = Math.max(10, parseFloat(basePrice.toFixed(2)));

            if (Math.random() < 0.05) trendBias *= -1;

            const bids = [];
            const asks = [];

            let runningBidP = basePrice - 0.05;
            for (let i = 0; i < 30; i++) {
                runningBidP = parseFloat((runningBidP - (0.05 + Math.random() * 0.05)).toFixed(2));
                let qty = Math.floor(150 + Math.random() * 800);
                
                if (i === 7 && trendBias > 0) qty = 4500;
                if (i === 14) qty = 2800;

                bids.push({ price: runningBidP, qty: qty, orders: Math.floor(qty / 120) + 1 });
            }

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
                btnLive.className = 'px-2 py-0.5 rounded font-bold text-[11px] text-slate-400 hover:text-white transition';
            }
            if (btnSim) {
                btnSim.className = 'px-2 py-0.5 rounded font-bold text-[11px] bg-amber-600 text-white transition shadow-sm';
            }
            if (statusDot) statusDot.className = 'h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse';
            if (statusText) statusText.textContent = 'Simulator';

            if (state.ws) state.ws.close();
            startSimulator();
        } else {
            if (btnLive) {
                btnLive.className = 'px-2 py-0.5 rounded font-bold text-[11px] bg-emerald-600 text-white transition shadow-sm';
            }
            if (btnSim) {
                btnSim.className = 'px-2 py-0.5 rounded font-bold text-[11px] text-slate-400 hover:text-white transition';
            }
            if (state.simInterval) clearInterval(state.simInterval);
            connectWebSocket();
        }
    };

    function updateSpotUI(spot) {
        const spotEl = document.getElementById('val-spot-price');
        if (spotEl) spotEl.textContent = `₹${spot.toFixed(2)}`;
    }

    // ── 13. EVENT LISTENERS & SETUP ──
    document.addEventListener('DOMContentLoaded', async () => {
        const btnLive = document.getElementById('btn-mode-live');
        const btnSim = document.getElementById('btn-mode-sim');
        if (btnLive) btnLive.addEventListener('click', () => window.switchMode('live'));
        if (btnSim) btnSim.addEventListener('click', () => window.switchMode('sim'));

        const soundBtn = document.getElementById('btn-sound-toggle');
        const soundIcon = document.getElementById('sound-icon');
        if (soundBtn) {
            soundBtn.addEventListener('click', () => {
                state.soundEnabled = !state.soundEnabled;
                if (soundIcon) soundIcon.textContent = state.soundEnabled ? '🔔' : '🔕';
            });
        }

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

        const selExpiry = document.getElementById('sel-expiry');
        if (selExpiry) {
            selExpiry.addEventListener('change', () => {
                window.location.href = `?expiry=${selExpiry.value}`;
            });
        }

        await initProtobuf();
        connectWebSocket();
    });

    window.refreshStrategySignals = async function() {
        try {
            const resp = await fetch(`{{ route('api.order-flow.strategy-signals') }}?symbol=NIFTY&expiry=${state.expiry}`);
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
