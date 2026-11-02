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

            {{-- Right: Live Feed Connection, Alert Rules & Mode Toggles --}}
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

                {{-- Alert Rules Settings Button --}}
                <button id="btn-open-settings" onclick="toggleSettingsModal(true)" title="Configure Liquidity Block & Consecutive Stack Alert Rules" class="px-2 py-0.5 rounded-lg bg-slate-950 border border-amber-500/40 hover:bg-slate-800 text-amber-300 hover:text-white transition text-[11px] flex items-center gap-1 font-bold shadow-sm">
                    <span>⚙️</span>
                    <span class="hidden sm:inline">Alert Rules</span>
                </button>

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

    {{-- ════════════════════════ LIVE DIRECTIONAL LIQUIDITY ALERT BANNER ════════════════════════ --}}
    <div id="liquidity-alert-banner" class="hidden transition-all duration-300">
        {{-- Injected dynamically when Single Block or Consecutive Stack is active --}}
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

                    <div class="flex items-center gap-1.5 text-[10px] flex-wrap justify-end">
                        <span class="text-emerald-400 font-bold">● Bids</span>
                        <span class="text-rose-400 font-bold">● Asks</span>
                        <span id="legend-block-tag" class="px-1.5 py-0.5 rounded bg-amber-500/20 border border-amber-400/50 text-amber-300 font-black text-[9px]">⚡ Block (≥2,500)</span>
                        <span id="legend-stack-tag" class="px-1.5 py-0.5 rounded bg-cyan-500/20 border border-cyan-400/50 text-cyan-300 font-black text-[9px]">🌊 Stack (≥3 Lvl)</span>
                        <span class="px-1.5 py-0.5 rounded bg-fuchsia-500/20 border border-fuchsia-400/50 text-fuchsia-300 font-black text-[9px]">👑 POC (Anchor)</span>
                    </div>
                </div>

                {{-- Prominent Current Trading Price (LTP) Sticky Highlight Bar with Micro-Price Pull Vector & Spread Guard --}}
                <div id="dom-ltp-highlight-banner" class="mb-1.5 py-1 px-2.5 rounded-lg bg-gradient-to-r from-amber-500/15 via-amber-500/25 to-amber-500/15 border border-amber-400/80 flex items-center justify-between flex-wrap gap-1 text-xs font-black shadow-md shadow-amber-500/5">
                    <div class="flex items-center gap-1.5">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-400"></span>
                        </span>
                        <span class="text-slate-300 text-[10px] uppercase tracking-wider font-extrabold">LTP:</span>
                        <span id="banner-ltp-val" class="text-xs sm:text-sm font-black text-white bg-slate-950 px-2 py-0.2 rounded border border-amber-400/60 font-mono">
                            ₹--
                        </span>
                        {{-- Directional Vector Pull Arrow --}}
                        <div id="banner-micro-vector" class="ml-1 px-1.5 py-0.2 rounded bg-slate-950/80 border border-slate-700/60 text-[10px] font-mono">
                            <span class="text-slate-400">➔ BALANCED</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 text-[10px]">
                        {{-- Spread Traffic Light Guard --}}
                        <span id="banner-spread-guard" class="px-1.5 py-0.2 rounded text-[9px] font-black bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                            🟢 TIGHT SPREAD
                        </span>
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

                {{-- ⚡ DIRECT OPTION STRIKE EXECUTION CARD (Options Trader Mode) --}}
                <div class="mt-2 p-2 rounded-xl bg-slate-950 border border-slate-800/90 space-y-1.5 shadow-inner">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs">⚡</span>
                            <span class="text-[10px] font-black uppercase text-slate-300 tracking-wider">Option Strike Recommendation:</span>
                            <span id="opt-rec-badge" class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-slate-800 text-slate-400">STAND ASIDE</span>
                        </div>
                        <button type="button" onclick="copyOrderDetails()" id="btn-copy-order" class="px-2 py-0.5 rounded bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[10px] flex items-center gap-1 transition shadow-xs active:scale-95 cursor-pointer">
                            <span>📋</span>
                            <span>Copy Order</span>
                        </button>
                    </div>

                    <div class="grid grid-cols-3 gap-1.5 text-center text-xs">
                        <div class="bg-slate-900/90 p-1.5 rounded-lg border border-slate-800">
                            <span class="text-[8.5px] uppercase font-bold text-slate-400 block">Suggested Strike</span>
                            <span id="opt-rec-strike" class="text-[11px] font-black text-amber-300 truncate mt-0.5 block font-mono">--</span>
                        </div>
                        <div class="bg-slate-900/90 p-1.5 rounded-lg border border-slate-800">
                            <span class="text-[8.5px] uppercase font-bold text-slate-400 block">Option Entry</span>
                            <span id="opt-rec-entry" class="text-[11px] font-bold text-white font-mono mt-0.5 block">--</span>
                        </div>
                        <div class="bg-slate-900/90 p-1.5 rounded-lg border border-slate-800">
                            <span class="text-[8.5px] uppercase font-bold text-slate-400 block">SL / Target</span>
                            <span id="opt-rec-sltgt" class="text-[10px] font-bold text-slate-300 font-mono mt-0.5 block">--</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-[9px] text-slate-400 pt-0.5 px-0.5">
                        <span id="opt-rec-note">Calculated for NIFTY Derivatives (Lot Size: 25)</span>
                        <span id="opt-copy-status" class="text-emerald-400 font-bold hidden animate-pulse">✓ Copied to clipboard!</span>
                    </div>
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
                        <button onclick="switchMiniTab('mini-alerts')" id="btn-mini-alerts" class="mini-tab-btn px-2 py-0.5 rounded bg-slate-800 text-slate-300 font-bold hover:text-white transition flex items-center gap-1">
                            <span>Alerts ⚡</span>
                            <span id="badge-alert-count" class="px-1 rounded bg-amber-500/20 text-amber-300 text-[8.5px] font-mono">0</span>
                        </button>
                        <button onclick="switchMiniTab('mini-journal')" id="btn-mini-journal" class="mini-tab-btn px-2 py-0.5 rounded bg-slate-800 text-slate-300 font-bold hover:text-white transition">
                            Journal 📖
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

                {{-- Mini Tab Pane 4: Live Session Alert Feed & Trigger Reason --}}
                <div id="pane-mini-alerts" class="mini-tab-pane hidden space-y-1.5 text-[10px]">
                    <div class="flex items-center justify-between text-[10px] pb-1 border-b border-slate-800">
                        <span class="text-slate-400 font-bold uppercase tracking-wider">Live Alarm Trigger Feed:</span>
                        <button type="button" onclick="clearLiveAlertHistory()" class="text-rose-400 hover:text-rose-300 text-[9px] font-bold">
                            Clear Feed
                        </button>
                    </div>
                    <div id="live-alerts-history-container" class="space-y-1 overflow-y-auto max-h-48 pr-0.5">
                        <div class="text-center py-6 text-slate-500 text-[10px]">
                            No alarms triggered yet this session. When audio sounds, exact trigger reasons appear here in real-time.
                        </div>
                    </div>
                </div>

                {{-- Mini Tab Pane 5: Today's Opportunity Journal & Outcome Audit --}}
                <div id="pane-mini-journal" class="mini-tab-pane hidden space-y-2 text-[10px]">
                    {{-- Performance Scorecard Summary --}}
                    <div class="grid grid-cols-4 gap-1 text-center bg-slate-950 p-1.5 rounded-lg border border-slate-800">
                        <div>
                            <span class="text-[8px] uppercase text-slate-400 font-bold block">Calls</span>
                            <span id="journal-total-count" class="font-black text-white text-xs">0</span>
                        </div>
                        <div>
                            <span class="text-[8px] uppercase text-slate-400 font-bold block">Win Rate</span>
                            <span id="journal-win-rate" class="font-black text-emerald-400 text-xs">0%</span>
                        </div>
                        <div>
                            <span class="text-[8px] uppercase text-slate-400 font-bold block">Hit / Stop</span>
                            <span class="font-black text-xs font-mono">
                                <span id="journal-target-count" class="text-emerald-400">0</span>/<span id="journal-stopped-count" class="text-rose-400">0</span>
                            </span>
                        </div>
                        <div>
                            <span class="text-[8px] uppercase text-slate-400 font-bold block">Net Pts</span>
                            <span id="journal-net-pnl" class="font-black text-amber-300 text-xs font-mono">+0.0</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-[10px] text-slate-400 border-b border-slate-800/80 pb-1">
                        <span>Today's Verified Setups:</span>
                        <button type="button" onclick="refreshSignalLogs()" class="text-indigo-400 hover:text-indigo-300 font-bold flex items-center gap-1 cursor-pointer">
                            <span>🔄</span>
                            <span>Refresh</span>
                        </button>
                    </div>

                    {{-- Scrollable Signals List --}}
                    <div id="journal-logs-container" class="space-y-1 max-h-48 overflow-y-auto pr-0.5 font-sans">
                        <div class="text-center py-6 text-slate-500 text-[10px]">
                            No trade signals logged yet today.
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

    {{-- ════════════════════════ 3. MODAL: LIQUIDITY ALERT RULES SETTINGS ════════════════════════ --}}
    <div id="modal-alert-settings" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-xs p-3">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 sm:p-5 max-w-md w-full shadow-2xl space-y-3.5">
            
            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-slate-800 pb-2.5">
                <div class="flex items-center gap-2">
                    <span class="text-lg">⚙️</span>
                    <div>
                        <h3 class="text-sm sm:text-base font-black text-white">Liquidity &amp; Stack Alert Rules</h3>
                        <p class="text-[10px] text-slate-400">Highlight large institutional orders and continuous directional stacks</p>
                    </div>
                </div>
                <button onclick="toggleSettingsModal(false)" class="text-slate-400 hover:text-white p-1 rounded-lg bg-slate-950 hover:bg-slate-800 text-sm">
                    ✕
                </button>
            </div>

            {{-- Body Settings Form --}}
            <div class="space-y-3 text-xs">
                
                {{-- Rule 1: Single Level QTY Threshold --}}
                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="font-bold text-white uppercase text-[11px] flex items-center gap-1.5">
                            <span>⚡ Single Level Heavy Block</span>
                            <span class="text-[10px] text-amber-400 font-normal">(Qty ≥ X)</span>
                        </label>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="number" id="cfg-single-qty" min="100" step="100" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-amber-300 font-bold font-mono focus:outline-none focus:border-amber-400" value="2500" placeholder="e.g. 2500">
                        <span class="text-[11px] text-slate-400 font-bold">Qty</span>
                    </div>
                    <div class="flex items-center gap-1.5 flex-wrap pt-0.5">
                        <span class="text-[9px] text-slate-500 font-bold uppercase">Presets:</span>
                        <button type="button" onclick="setSinglePreset(1000)" class="px-2 py-0.5 rounded bg-slate-900 hover:bg-slate-800 text-slate-300 text-[10px] font-mono border border-slate-800">1,000</button>
                        <button type="button" onclick="setSinglePreset(2000)" class="px-2 py-0.5 rounded bg-slate-900 hover:bg-slate-800 text-slate-300 text-[10px] font-mono border border-slate-800">2,000</button>
                        <button type="button" onclick="setSinglePreset(3500)" class="px-2 py-0.5 rounded bg-slate-900 hover:bg-slate-800 text-slate-300 text-[10px] font-mono border border-slate-800">3,500</button>
                        <button type="button" onclick="setSinglePreset(5000)" class="px-2 py-0.5 rounded bg-slate-900 hover:bg-slate-800 text-slate-300 text-[10px] font-mono border border-slate-800">5,000</button>
                        <button type="button" onclick="setSinglePreset(10000)" class="px-2 py-0.5 rounded bg-slate-900 hover:bg-slate-800 text-slate-300 text-[10px] font-mono border border-slate-800">10,000</button>
                    </div>
                </div>

                {{-- Rule 2: Continuous / Consecutive Stack Alert (Directional Rush) --}}
                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="font-bold text-white uppercase text-[11px] flex items-center gap-1.5">
                            <span>🌊 Continuous / Consecutive Stack</span>
                        </label>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="cfg-enable-stack" class="sr-only peer" checked>
                            <div class="w-8 h-4 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-emerald-500"></div>
                        </label>
                    </div>
                    <p class="text-[10px] text-slate-400">
                        Flags when multiple continuous price levels are all stacked with heavy size, revealing institutional push in that direction.
                    </p>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold block mb-1">Consecutive Levels:</span>
                            <select id="cfg-stack-count" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white font-bold cursor-pointer focus:outline-none">
                                <option value="2">2 Levels in a row</option>
                                <option value="3" selected>3 Levels in a row (Recommended)</option>
                                <option value="4">4 Levels in a row</option>
                                <option value="5">5 Levels in a row</option>
                            </select>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold block mb-1">Min Qty Each Level:</span>
                            <div class="flex items-center gap-1">
                                <input type="number" id="cfg-stack-qty" min="100" step="100" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-cyan-300 font-bold font-mono focus:outline-none focus:border-cyan-400" value="1500" placeholder="1500">
                                <span class="text-[10px] text-slate-400 font-bold">Qty</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Rule 3: Audio Alert & 5 Selectable Sound Profiles --}}
                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-bold text-white text-[11px] block">Audio Alert on Liquidity Triggers</span>
                            <span class="text-[10px] text-slate-400">Play directional acoustic chime when blocks or stacks trigger</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="cfg-sound-stack" class="sr-only peer" checked>
                            <div class="w-8 h-4 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-amber-500"></div>
                        </label>
                    </div>

                    {{-- 5 Sound Profile Selector & Live Test Buttons --}}
                    <div class="pt-2 border-t border-slate-800/80 space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Sound Profile (5 Available):</label>
                            <span class="text-[9px] text-amber-400 font-bold">Synthesized Zero-Lag Web Audio</span>
                        </div>
                        <div class="space-y-2">
                            <select id="cfg-sound-profile" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-2 text-xs text-white font-bold cursor-pointer focus:outline-none focus:border-amber-400">
                                <option value="crystal">🔔 1. Crystal Chime (Harmonic Bell)</option>
                                <option value="radar">📡 2. Radar Sonar Pulse (Submarine Ping)</option>
                                <option value="laser">⚡ 3. Cyber Laser Sweep (Sci-Fi Warp)</option>
                                <option value="marimba">🎶 4. Warm Marimba (3-Tone Arpeggio)</option>
                                <option value="tactical">🎯 5. Tactical Double-Pip (Dual Click)</option>
                            </select>
                            
                            {{-- Test Buttons: Bull & Bear full width 2-column grid --}}
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" onclick="testCurrentSound('BUY')" title="Test Bullish (Buy / Bid Stack & Block) tone" class="w-full py-2 px-3 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs flex items-center justify-center gap-1.5 transition shadow-md shadow-emerald-600/20 active:scale-95">
                                    <span>▶️ Test Bull (Bid)</span>
                                </button>
                                <button type="button" onclick="testCurrentSound('SELL')" title="Test Bearish (Sell / Ask Stack & Block) tone" class="w-full py-2 px-3 rounded-lg bg-rose-600 hover:bg-rose-500 text-white font-black text-xs flex items-center justify-center gap-1.5 transition shadow-md shadow-rose-600/20 active:scale-95">
                                    <span>▶️ Test Bear (Ask)</span>
                                </button>
                            </div>
                        </div>
                        <p class="text-[9px] text-slate-500 leading-tight">
                            Sounds automatically modulate higher in pitch for Bullish Bid Stacks/Blocks and lower in pitch for Bearish Ask Stacks/Blocks.
                        </p>
                    </div>
                </div>

                {{-- Rule 4: Auto-Log Opportunities to Journal --}}
                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-1.5">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-bold text-white text-[11px] block">📝 Auto-Log Opportunities to Journal</span>
                            <span class="text-[10px] text-slate-400">Record every high-confluence entry alert to database</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="cfg-autolog-journal" class="sr-only peer" checked>
                            <div class="w-8 h-4 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-indigo-600"></div>
                        </label>
                    </div>
                    <p class="text-[9px] text-slate-500 leading-tight">
                        Whenever an entry setup triggers (≥75% Bull or ≤32% Bear, or Liquidity Stacks), saves entry, SL, target, and tracks live outcome (target hit vs stopped out) for performance auditing.
                    </p>
                </div>

            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between pt-2 border-t border-slate-800">
                <button type="button" onclick="resetAlertDefaults()" class="text-xs text-slate-400 hover:text-white font-semibold">
                    Reset Defaults
                </button>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="toggleSettingsModal(false)" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-750 text-slate-300 text-xs font-bold transition">
                        Cancel
                    </button>
                    <button type="button" onclick="saveAlertSettings()" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black shadow-md transition">
                        Save &amp; Apply
                    </button>
                </div>
            </div>

        </div>
    </div>

</div>

{{-- ════════════════════════ 4. SCRIPTS: PROTOBUF, WEBSOCKET & CONFLUENCE ENGINE ════════════════════════ --}}
<script src="https://cdn.jsdelivr.net/npm/protobufjs@7.2.5/dist/protobuf.min.js"></script>

<script>
(function() {
    'use strict';

    // ── 1. ALERT SETTINGS STATE & STORAGE ──
    const alertSettings = {
        singleThreshold: 2500,     // Single level Qty >= X
        enableStack: true,         // Consecutive stack detection
        stackMinConsecutive: 3,    // N continuous levels
        stackMinQty: 1500,         // Min qty on each level
        soundEnabled: true,
        soundProfile: 'crystal',   // 'crystal', 'radar', 'laser', 'marimba', 'tactical'
        autoLog: true              // Auto-log opportunities to Journal
    };

    function updateLegendLabels() {
        const blockTag = document.getElementById('legend-block-tag');
        const stackTag = document.getElementById('legend-stack-tag');
        if (blockTag) blockTag.textContent = `⚡ Block (≥${alertSettings.singleThreshold.toLocaleString()})`;
        if (stackTag) stackTag.textContent = `🌊 Stack (≥${alertSettings.stackMinConsecutive} Lvl · ≥${alertSettings.stackMinQty.toLocaleString()})`;
    }

    function loadAlertSettings() {
        try {
            const saved = localStorage.getItem('d30_alert_rules');
            if (saved) {
                Object.assign(alertSettings, JSON.parse(saved));
            }
        } catch(e) {}

        const singleInput = document.getElementById('cfg-single-qty');
        const enableStack = document.getElementById('cfg-enable-stack');
        const stackCount = document.getElementById('cfg-stack-count');
        const stackQty = document.getElementById('cfg-stack-qty');
        const soundStack = document.getElementById('cfg-sound-stack');
        const soundProfile = document.getElementById('cfg-sound-profile');
        const autoLogInput = document.getElementById('cfg-autolog-journal');

        if (singleInput) singleInput.value = alertSettings.singleThreshold;
        if (enableStack) enableStack.checked = alertSettings.enableStack;
        if (stackCount) stackCount.value = alertSettings.stackMinConsecutive;
        if (stackQty) stackQty.value = alertSettings.stackMinQty;
        if (soundStack) soundStack.checked = alertSettings.soundEnabled;
        if (soundProfile) soundProfile.value = alertSettings.soundProfile || 'crystal';
        if (autoLogInput) autoLogInput.checked = alertSettings.autoLog !== false;

        updateLegendLabels();
    }

    window.toggleSettingsModal = function(show) {
        const modal = document.getElementById('modal-alert-settings');
        if (!modal) return;
        if (show) {
            loadAlertSettings();
            modal.classList.remove('hidden');
        } else {
            modal.classList.add('hidden');
        }
    };

    window.setSinglePreset = function(qty) {
        const input = document.getElementById('cfg-single-qty');
        if (input) input.value = qty;
    };

    window.resetAlertDefaults = function() {
        alertSettings.singleThreshold = 2500;
        alertSettings.enableStack = true;
        alertSettings.stackMinConsecutive = 3;
        alertSettings.stackMinQty = 1500;
        alertSettings.soundEnabled = true;
        alertSettings.soundProfile = 'crystal';
        alertSettings.autoLog = true;

        const singleInput = document.getElementById('cfg-single-qty');
        const enableStack = document.getElementById('cfg-enable-stack');
        const stackCount = document.getElementById('cfg-stack-count');
        const stackQty = document.getElementById('cfg-stack-qty');
        const soundStack = document.getElementById('cfg-sound-stack');
        const soundProfile = document.getElementById('cfg-sound-profile');
        const autoLogInput = document.getElementById('cfg-autolog-journal');

        if (singleInput) singleInput.value = 2500;
        if (enableStack) enableStack.checked = true;
        if (stackCount) stackCount.value = 3;
        if (stackQty) stackQty.value = 1500;
        if (soundStack) soundStack.checked = true;
        if (soundProfile) soundProfile.value = 'crystal';
        if (autoLogInput) autoLogInput.checked = true;

        updateLegendLabels();
    };

    window.saveAlertSettings = function() {
        const singleInput = document.getElementById('cfg-single-qty');
        const enableStack = document.getElementById('cfg-enable-stack');
        const stackCount = document.getElementById('cfg-stack-count');
        const stackQty = document.getElementById('cfg-stack-qty');
        const soundStack = document.getElementById('cfg-sound-stack');
        const soundProfile = document.getElementById('cfg-sound-profile');
        const autoLogInput = document.getElementById('cfg-autolog-journal');

        alertSettings.singleThreshold = Math.max(100, parseInt(singleInput?.value || 2500));
        alertSettings.enableStack = !!(enableStack?.checked);
        alertSettings.stackMinConsecutive = Math.max(2, parseInt(stackCount?.value || 3));
        alertSettings.stackMinQty = Math.max(100, parseInt(stackQty?.value || 1500));
        alertSettings.soundEnabled = !!(soundStack?.checked);
        alertSettings.soundProfile = soundProfile?.value || 'crystal';
        alertSettings.autoLog = !!(autoLogInput?.checked);

        try {
            localStorage.setItem('d30_alert_rules', JSON.stringify(alertSettings));
        } catch(e) {}

        updateLegendLabels();
        toggleSettingsModal(false);

        // Re-render DOM with new rules immediately
        renderDOM();
        evaluateConfluenceRadar();
    };

    window.testCurrentSound = function(testSide = 'BUY') {
        const ctx = getAudioCtx();
        if (ctx && ctx.state === 'suspended') {
            ctx.resume();
        }
        const sel = document.getElementById('cfg-sound-profile');
        const profile = sel ? sel.value : (alertSettings.soundProfile || 'crystal');
        playSynthesizedAlert(profile, testSide);
    };

    window.dismissAlertBanner = function() {
        const banner = document.getElementById('liquidity-alert-banner');
        if (banner) banner.className = 'hidden';
        state.dismissedAlertKey = state.currentAlertKey;
    };

    window.copyOrderDetails = function() {
        const trade = state.currentOptionTrade;
        if (!trade || trade.action === 'WAIT') {
            alert('No active directional signal. Stand aside during neutral chop to preserve capital.');
            return;
        }

        const textToCopy = `ACTION: ${trade.action}\nINSTRUMENT: ${trade.symbol}\nENTRY ZONE: ${trade.entry}\nSTOP LOSS: ${trade.sl}\nTARGET: ${trade.target}\nLOT SIZE: ${trade.lotSize}\nCONFLUENCE: ${trade.confluence}%\nMICRO-DRIFT: ${trade.drift}`;

        navigator.clipboard.writeText(textToCopy).then(() => {
            const copyStatus = document.getElementById('opt-copy-status');
            if (copyStatus) {
                copyStatus.classList.remove('hidden');
                setTimeout(() => copyStatus.classList.add('hidden'), 2500);
            }
        }).catch(err => {
            console.error('Failed to copy order:', err);
        });
    };

    // ── 2. GLOBAL APP STATE ──
    const state = {
        symbol: "NIFTY",
        expiry: "{{ $selectedExpiry }}",
        indexKey: "{{ $indexKey }}",
        contracts: @json($contracts),
        strategySignals: @json($strategySignals),
        activeInstrumentKey: null,
        activeInstrumentType: 'FUT',
        activeStrike: 0,
        feedMode: 'live',
        ws: null,
        wsUrl: null,
        protobufRoot: null,
        soundEnabled: true,
        currentAlertKey: '',
        dismissedAlertKey: '',
        lastAlertTime: 0,
        lastStackAlertTime: 0,
        lastSignalLogTime: 0,
        activeLoggedSignals: [],
        currentOptionTrade: null,
        wallPersistence: {},
        
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
            pulledSpoofCount: 0,
            absorptionPct: 0,
            bidStack: null,
            askStack: null
        },
        
        simInterval: null
    };

    // ── 3. MINI-TAB SWITCHER ──
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

    // ── 4. INITIALIZE PROTOBUF ──
    async function initProtobuf() {
        try {
            state.protobufRoot = await protobuf.load('/MarketDataFeed_v3.proto');
            console.log('✅ MarketDataFeed_v3.proto loaded successfully for 30-depth stream.');
        } catch (e) {
            console.warn('⚠️ Could not load MarketDataFeed_v3.proto, running in simulator mode:', e);
            window.switchMode('sim');
        }
    }

    // ── 5. UPSTOX WEBSOCKET CONNECTION ──
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

    // ── 6. SUBSCRIBE IN "full_d30" MODE ──
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

    // ── 7. PROTOBUF DECODER ──
    function decodeProtobufFeed(buffer) {
        if (!state.protobufRoot) return;

        try {
            const arr = new Uint8Array(buffer);
            if (arr.length > 0 && arr[0] === 123) return;

            const FeedResponse = state.protobufRoot.lookupType("com.upstox.marketdatafeederv3udapi.rpc.proto.FeedResponse");
            const message = FeedResponse.decode(arr);
            const obj = FeedResponse.toObject(message, { enums: String, bytes: String });

            if (obj && obj.feeds) {
                if (obj.feeds[state.indexKey]) {
                    const idxFeed = obj.feeds[state.indexKey];
                    let idxLtp = idxFeed.fullFeed?.indexFF?.ltpc?.ltp ?? idxFeed.ltpc?.ltp;
                    if (idxLtp) {
                        updateSpotUI(parseFloat(idxLtp));
                    }
                }

                if (obj.feeds[state.activeInstrumentKey]) {
                    const targetFeed = obj.feeds[state.activeInstrumentKey];
                    parseMarketFeed(targetFeed);
                }
            }
        } catch (e) {
            console.error('Protobuf decode error:', e);
        }
    }

    // ── 8. PARSE 30-DEPTH ORDERS FROM FEED ──
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

    // ── 9. MICROSTRUCTURE & CONSECUTIVE STACK CALCULATIONS ──
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

        state.depthData.spread = Math.max(0.05, parseFloat((bestAsk - bestBid).toFixed(2)));
        state.depthData.midPrice = parseFloat(((bestBid + bestAsk) / 2).toFixed(2));

        const totalTopQ = bestBidQ + bestAskQ;
        if (totalTopQ > 0) {
            state.depthData.microPrice = parseFloat(((bestAsk * bestBidQ + bestBid * bestAskQ) / totalTopQ).toFixed(2));
        } else {
            state.depthData.microPrice = state.depthData.midPrice;
        }

        state.depthData.obi5  = calculateTierObi(bids, asks, 5);
        state.depthData.obi10 = calculateTierObi(bids, asks, 10);
        state.depthData.obi20 = calculateTierObi(bids, asks, 20);
        state.depthData.obi30 = calculateTierObi(bids, asks, 30);

        // Scan standard walls
        scanLiquidityWalls(bids, asks);

        // Continuous Stack Detection (User Setting: Continuous X Qty in Bid or Ask)
        const stacks = detectLiquidityStacks(bids, asks);
        state.depthData.bidStack = stacks.bidStack;
        state.depthData.askStack = stacks.askStack;

        // Single Level Heavy Block Detection (User Setting: Single Level Qty >= Threshold)
        let heavyBidBlock = null;
        let heavyAskBlock = null;
        const sThresh = parseInt(alertSettings.singleThreshold || 2500);

        for (let i = 0; i < Math.min(bids.length, 30); i++) {
            if (bids[i] && bids[i].qty >= sThresh) {
                if (!heavyBidBlock || bids[i].qty > heavyBidBlock.qty) {
                    heavyBidBlock = {
                        side: 'BID',
                        level: i + 1,
                        price: bids[i].price,
                        qty: bids[i].qty
                    };
                }
            }
        }

        for (let i = 0; i < Math.min(asks.length, 30); i++) {
            if (asks[i] && asks[i].qty >= sThresh) {
                if (!heavyAskBlock || asks[i].qty > heavyAskBlock.qty) {
                    heavyAskBlock = {
                        side: 'ASK',
                        level: i + 1,
                        price: asks[i].price,
                        qty: asks[i].qty
                    };
                }
            }
        }

        state.depthData.heavyBidBlock = heavyBidBlock;
        state.depthData.heavyAskBlock = heavyAskBlock;

        // Render UI, DOM & Alert Banner
        renderDOM();
        updateMicrostructureUI();
        evaluateConfluenceRadar();
        renderAlertBanner(stacks.bidStack, stacks.askStack, heavyBidBlock, heavyAskBlock);

        // Check active trade signals against live price for Target / Stop Loss hits
        checkActiveSignalsOutcome(ltp);
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
        const currentSeenKeys = new Set();
        const now = Date.now();

        bids.forEach((b, idx) => {
            if (b.qty >= avgBidQ * 2.2 && b.qty > 500) {
                const key = `BID_${b.price.toFixed(2)}`;
                currentSeenKeys.add(key);

                if (!state.wallPersistence[key]) {
                    state.wallPersistence[key] = {
                        firstSeen: now,
                        lastSeen: now,
                        ticks: 1,
                        initialQty: b.qty,
                        maxQty: b.qty,
                        status: 'DETECTING'
                    };
                } else {
                    const wp = state.wallPersistence[key];
                    wp.lastSeen = now;
                    wp.ticks++;
                    wp.maxQty = Math.max(wp.maxQty, b.qty);
                    wp.status = wp.ticks >= 3 ? 'GENUINE' : 'DETECTING';
                }

                walls.push({
                    side: 'BID',
                    price: b.price,
                    qty: b.qty,
                    level: idx + 1,
                    multiple: (b.qty / avgBidQ).toFixed(1),
                    status: state.wallPersistence[key]?.status || 'GENUINE',
                    ticks: state.wallPersistence[key]?.ticks || 1
                });
            }
        });

        asks.forEach((a, idx) => {
            if (a.qty >= avgAskQ * 2.2 && a.qty > 500) {
                const key = `ASK_${a.price.toFixed(2)}`;
                currentSeenKeys.add(key);

                if (!state.wallPersistence[key]) {
                    state.wallPersistence[key] = {
                        firstSeen: now,
                        lastSeen: now,
                        ticks: 1,
                        initialQty: a.qty,
                        maxQty: a.qty,
                        status: 'DETECTING'
                    };
                } else {
                    const wp = state.wallPersistence[key];
                    wp.lastSeen = now;
                    wp.ticks++;
                    wp.maxQty = Math.max(wp.maxQty, a.qty);
                    wp.status = wp.ticks >= 3 ? 'GENUINE' : 'DETECTING';
                }

                walls.push({
                    side: 'ASK',
                    price: a.price,
                    qty: a.qty,
                    level: idx + 1,
                    multiple: (a.qty / avgAskQ).toFixed(1),
                    status: state.wallPersistence[key]?.status || 'GENUINE',
                    ticks: state.wallPersistence[key]?.ticks || 1
                });
            }
        });

        // Scan for pulled/spoofed walls (large orders deleted without price reaching them)
        let pulledSpoofCount = 0;
        for (const [key, wp] of Object.entries(state.wallPersistence)) {
            if (!currentSeenKeys.has(key)) {
                if (wp.maxQty >= 1500 && (now - wp.lastSeen < 15000) && wp.ticks < 8) {
                    pulledSpoofCount++;
                }
                if (now - wp.lastSeen > 25000) {
                    delete state.wallPersistence[key];
                }
            }
        }

        state.depthData.liquidityWalls = walls;
        state.depthData.pulledSpoofCount = pulledSpoofCount;
    }

    // ── 10. CONSECUTIVE / CONTINUOUS STACK DETECTION ──
    function detectLiquidityStacks(bids, asks) {
        if (!alertSettings.enableStack) return { bidStack: null, askStack: null };

        let bidStack = null;
        let currentBidRun = [];
        for (let i = 0; i < Math.min(bids.length, 30); i++) {
            if (bids[i] && bids[i].qty >= alertSettings.stackMinQty) {
                currentBidRun.push({ index: i, level: i + 1, price: bids[i].price, qty: bids[i].qty });
            } else {
                if (currentBidRun.length >= alertSettings.stackMinConsecutive) {
                    if (!bidStack || currentBidRun.length > bidStack.levels.length) {
                        bidStack = {
                            side: 'BID',
                            levels: [...currentBidRun],
                            count: currentBidRun.length,
                            totalQty: currentBidRun.reduce((sum, x) => sum + x.qty, 0),
                            highP: Math.max(...currentBidRun.map(x => x.price)),
                            lowP: Math.min(...currentBidRun.map(x => x.price))
                        };
                    }
                }
                currentBidRun = [];
            }
        }
        if (currentBidRun.length >= alertSettings.stackMinConsecutive) {
            if (!bidStack || currentBidRun.length > bidStack.levels.length) {
                bidStack = {
                    side: 'BID',
                    levels: [...currentBidRun],
                    count: currentBidRun.length,
                    totalQty: currentBidRun.reduce((sum, x) => sum + x.qty, 0),
                    highP: Math.max(...currentBidRun.map(x => x.price)),
                    lowP: Math.min(...currentBidRun.map(x => x.price))
                };
            }
        }

        let askStack = null;
        let currentAskRun = [];
        for (let i = 0; i < Math.min(asks.length, 30); i++) {
            if (asks[i] && asks[i].qty >= alertSettings.stackMinQty) {
                currentAskRun.push({ index: i, level: i + 1, price: asks[i].price, qty: asks[i].qty });
            } else {
                if (currentAskRun.length >= alertSettings.stackMinConsecutive) {
                    if (!askStack || currentAskRun.length > askStack.levels.length) {
                        askStack = {
                            side: 'ASK',
                            levels: [...currentAskRun],
                            count: currentAskRun.length,
                            totalQty: currentAskRun.reduce((sum, x) => sum + x.qty, 0),
                            highP: Math.max(...currentAskRun.map(x => x.price)),
                            lowP: Math.min(...currentAskRun.map(x => x.price))
                        };
                    }
                }
                currentAskRun = [];
            }
        }
        if (currentAskRun.length >= alertSettings.stackMinConsecutive) {
            if (!askStack || currentAskRun.length > askStack.levels.length) {
                askStack = {
                    side: 'ASK',
                    levels: [...currentAskRun],
                    count: currentAskRun.length,
                    totalQty: currentAskRun.reduce((sum, x) => sum + x.qty, 0),
                    highP: Math.max(...currentAskRun.map(x => x.price)),
                    lowP: Math.min(...currentAskRun.map(x => x.price))
                };
            }
        }

        return { bidStack, askStack };
    }

    // ── 11. DIRECTIONAL ALERT BANNER ──
    function renderAlertBanner(bidStack, askStack, heavyBidBlock = null, heavyAskBlock = null) {
        const banner = document.getElementById('liquidity-alert-banner');
        if (!banner) return;

        let alertType = null;
        let alertKey = '';

        // Priority 1: Multi-level continuous stacks
        if (bidStack && (!askStack || bidStack.totalQty >= askStack.totalQty)) {
            alertType = 'STACK_BUY';
            alertKey = `STACK_BUY_${bidStack.lowP}_${bidStack.highP}_${bidStack.totalQty}`;
        } else if (askStack) {
            alertType = 'STACK_SELL';
            alertKey = `STACK_SELL_${askStack.lowP}_${askStack.highP}_${askStack.totalQty}`;
        } else if (heavyBidBlock && (!heavyAskBlock || heavyBidBlock.qty >= heavyAskBlock.qty)) {
            // Priority 2: Single level heavy institutional block (Qty >= alertSettings.singleThreshold)
            alertType = 'BLOCK_BUY';
            alertKey = `BLOCK_BUY_${heavyBidBlock.price}_${heavyBidBlock.qty}`;
        } else if (heavyAskBlock) {
            alertType = 'BLOCK_SELL';
            alertKey = `BLOCK_SELL_${heavyAskBlock.price}_${heavyAskBlock.qty}`;
        }

        if (!alertType) {
            banner.className = 'hidden';
            state.currentAlertKey = '';
            return;
        }

        state.currentAlertKey = alertKey;

        // Respect manual dismissal of this specific alert until a new event occurs
        if (state.dismissedAlertKey === alertKey) {
            banner.className = 'hidden';
            return;
        }

        const soundProfileLabel = getSoundProfileLabel(alertSettings.soundProfile || 'crystal');

        if (alertType === 'STACK_BUY') {
            banner.className = 'block my-1 px-3 py-1.5 rounded-xl bg-gradient-to-r from-emerald-950 via-emerald-900/90 to-slate-900 border-2 border-emerald-500/80 shadow-lg shadow-emerald-500/10 text-white';
            banner.innerHTML = `
                <div class="flex items-center justify-between flex-wrap gap-2 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="text-base animate-bounce">🚀</span>
                        <div>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="px-1.5 py-0.2 rounded bg-emerald-500 text-slate-950 font-black text-[9px] uppercase tracking-wider">
                                    BULLISH LIQUIDITY STACK (${bidStack.count} CONTINUOUS LEVELS)
                                </span>
                                <span class="text-[9px] px-1.5 py-0.2 rounded bg-slate-950 font-bold border border-slate-800 text-amber-300 font-mono">
                                    🔊 Sound: ${soundProfileLabel} (Bull Tone)
                                </span>
                            </div>
                            <p class="font-black text-emerald-200 text-xs mt-0.5 font-mono">
                                ${bidStack.count} Consecutive Bid Levels Stacked (Total ${bidStack.totalQty.toLocaleString()} Qty at ₹${bidStack.lowP.toFixed(2)} - ₹${bidStack.highP.toFixed(2)})
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] text-emerald-300 font-black bg-slate-950/90 px-2 py-0.5 rounded-lg border border-emerald-500/50">
                            ⚡ DIRECTION: GO LONG / BUY CALL
                        </span>
                        <button onclick="dismissAlertBanner()" class="text-slate-400 hover:text-white px-1.5 py-0.2 rounded text-xs bg-slate-950">✕</button>
                    </div>
                </div>
            `;
            triggerStackAlertAudio('BUY');

            recordAlertEvent({
                type: 'STACK_BUY',
                side: 'BUY',
                title: `Bullish Stack (${bidStack.count} Levels)`,
                details: `${bidStack.totalQty.toLocaleString()} Qty at ₹${bidStack.lowP.toFixed(2)} - ₹${bidStack.highP.toFixed(2)}`,
                guidance: '⚡ Direction: GO LONG / BUY CALL'
            });

            if (alertSettings.autoLog) {
                logOpportunityCall({
                    signalType: 'BUY_STACK',
                    action: 'BUY (LIQUIDITY STACK)',
                    symbol: state.symbol,
                    entryPrice: state.depthData.ltp,
                    slPrice: (bidStack.lowP - 0.20).toFixed(2),
                    targetPrice: (state.depthData.ltp + 45.0).toFixed(2),
                    optEstEntry: 140,
                    optEstSl: 118,
                    optEstTgt: 185,
                    confluence: 75,
                    rationale: `🌊 Bullish Stack: ${bidStack.count} Consecutive Levels (${bidStack.totalQty.toLocaleString()} Qty)`
                });
            }

        } else if (alertType === 'STACK_SELL') {
            banner.className = 'block my-1 px-3 py-1.5 rounded-xl bg-gradient-to-r from-rose-950 via-rose-900/90 to-slate-900 border-2 border-rose-500/80 shadow-lg shadow-rose-500/10 text-white';
            banner.innerHTML = `
                <div class="flex items-center justify-between flex-wrap gap-2 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="text-base animate-bounce">🩸</span>
                        <div>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="px-1.5 py-0.2 rounded bg-rose-500 text-white font-black text-[9px] uppercase tracking-wider">
                                    BEARISH LIQUIDITY STACK (${askStack.count} CONTINUOUS LEVELS)
                                </span>
                                <span class="text-[9px] px-1.5 py-0.2 rounded bg-slate-950 font-bold border border-slate-800 text-amber-300 font-mono">
                                    🔊 Sound: ${soundProfileLabel} (Bear Tone)
                                </span>
                            </div>
                            <p class="font-black text-rose-200 text-xs mt-0.5 font-mono">
                                ${askStack.count} Consecutive Ask Levels Stacked (Total ${askStack.totalQty.toLocaleString()} Qty at ₹${askStack.lowP.toFixed(2)} - ₹${askStack.highP.toFixed(2)})
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] text-rose-300 font-black bg-slate-950/90 px-2 py-0.5 rounded-lg border border-rose-500/50">
                            ⚡ DIRECTION: GO SHORT / BUY PUT
                        </span>
                        <button onclick="dismissAlertBanner()" class="text-slate-400 hover:text-white px-1.5 py-0.2 rounded text-xs bg-slate-950">✕</button>
                    </div>
                </div>
            `;
            triggerStackAlertAudio('SELL');

            recordAlertEvent({
                type: 'STACK_SELL',
                side: 'SELL',
                title: `Bearish Stack (${askStack.count} Levels)`,
                details: `${askStack.totalQty.toLocaleString()} Qty at ₹${askStack.lowP.toFixed(2)} - ₹${askStack.highP.toFixed(2)}`,
                guidance: '⚡ Direction: GO SHORT / BUY PUT'
            });

            if (alertSettings.autoLog) {
                logOpportunityCall({
                    signalType: 'SELL_STACK',
                    action: 'SELL (LIQUIDITY STACK)',
                    symbol: state.symbol,
                    entryPrice: state.depthData.ltp,
                    slPrice: (askStack.highP + 0.20).toFixed(2),
                    targetPrice: (state.depthData.ltp - 45.0).toFixed(2),
                    optEstEntry: 140,
                    optEstSl: 118,
                    optEstTgt: 185,
                    confluence: 25,
                    rationale: `🌊 Bearish Stack: ${askStack.count} Consecutive Levels (${askStack.totalQty.toLocaleString()} Qty)`
                });
            }

        } else if (alertType === 'BLOCK_BUY') {
            banner.className = 'block my-1 px-3 py-1.5 rounded-xl bg-gradient-to-r from-amber-950/90 via-emerald-950/90 to-slate-900 border-2 border-amber-400/90 shadow-lg shadow-amber-400/20 text-white';
            banner.innerHTML = `
                <div class="flex items-center justify-between flex-wrap gap-2 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="text-base animate-bounce">⚡</span>
                        <div>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="px-1.5 py-0.2 rounded bg-amber-400 text-slate-950 font-black text-[9px] uppercase tracking-wider">
                                    INSTITUTIONAL BID BLOCK (LEVEL ${heavyBidBlock.level})
                                </span>
                                <span class="text-[9px] px-1.5 py-0.2 rounded bg-slate-950 font-bold border border-slate-800 text-amber-300 font-mono">
                                    🔊 Sound: ${soundProfileLabel} (Bull Tone)
                                </span>
                            </div>
                            <p class="font-black text-amber-200 text-xs mt-0.5 font-mono">
                                Heavy Resting Buy Wall: ${heavyBidBlock.qty.toLocaleString()} Qty at ₹${heavyBidBlock.price.toFixed(2)} (≥${parseInt(alertSettings.singleThreshold).toLocaleString()})
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] text-emerald-300 font-black bg-slate-950/90 px-2 py-0.5 rounded-lg border border-emerald-500/50">
                            🛡️ STRONG SUPPORT / ACCUMULATION
                        </span>
                        <button onclick="dismissAlertBanner()" class="text-slate-400 hover:text-white px-1.5 py-0.2 rounded text-xs bg-slate-950">✕</button>
                    </div>
                </div>
            `;
            triggerStackAlertAudio('BUY');

            recordAlertEvent({
                type: 'BLOCK_BUY',
                side: 'BUY',
                title: `Heavy Bid Block: ${heavyBidBlock.qty.toLocaleString()} Qty`,
                details: `₹${heavyBidBlock.price.toFixed(2)} (Level ${heavyBidBlock.level})`,
                guidance: '🛡️ Strong Support Wall / Accumulation'
            });

            if (alertSettings.autoLog) {
                logOpportunityCall({
                    signalType: 'BUY_BLOCK',
                    action: 'BUY (SUPPORT WALL)',
                    symbol: state.symbol,
                    entryPrice: heavyBidBlock.price,
                    slPrice: (heavyBidBlock.price - 0.20).toFixed(2),
                    targetPrice: (state.depthData.ltp + 35.0).toFixed(2),
                    optEstEntry: 135,
                    optEstSl: 115,
                    optEstTgt: 175,
                    confluence: 70,
                    rationale: `⚡ Institutional Bid Block: ${heavyBidBlock.qty.toLocaleString()} Qty at ₹${heavyBidBlock.price.toFixed(2)} (Level ${heavyBidBlock.level})`
                });
            }

        } else if (alertType === 'BLOCK_SELL') {
            banner.className = 'block my-1 px-3 py-1.5 rounded-xl bg-gradient-to-r from-amber-950/90 via-rose-950/90 to-slate-900 border-2 border-rose-400/90 shadow-lg shadow-rose-400/20 text-white';
            banner.innerHTML = `
                <div class="flex items-center justify-between flex-wrap gap-2 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="text-base animate-bounce">⚡</span>
                        <div>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="px-1.5 py-0.2 rounded bg-rose-500 text-white font-black text-[9px] uppercase tracking-wider">
                                    INSTITUTIONAL ASK BLOCK (LEVEL ${heavyAskBlock.level})
                                </span>
                                <span class="text-[9px] px-1.5 py-0.2 rounded bg-slate-950 font-bold border border-slate-800 text-amber-300 font-mono">
                                    🔊 Sound: ${soundProfileLabel} (Bear Tone)
                                </span>
                            </div>
                            <p class="font-black text-rose-200 text-xs mt-0.5 font-mono">
                                Heavy Resting Sell Block: ${heavyAskBlock.qty.toLocaleString()} Qty at ₹${heavyAskBlock.price.toFixed(2)} (≥${parseInt(alertSettings.singleThreshold).toLocaleString()})
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] text-rose-300 font-black bg-slate-950/90 px-2 py-0.5 rounded-lg border border-rose-500/50">
                            🚧 STRONG RESISTANCE / SUPPLY
                        </span>
                        <button onclick="dismissAlertBanner()" class="text-slate-400 hover:text-white px-1.5 py-0.2 rounded text-xs bg-slate-950">✕</button>
                    </div>
                </div>
            `;
            triggerStackAlertAudio('SELL');

            recordAlertEvent({
                type: 'BLOCK_SELL',
                side: 'SELL',
                title: `Heavy Ask Block: ${heavyAskBlock.qty.toLocaleString()} Qty`,
                details: `₹${heavyAskBlock.price.toFixed(2)} (Level ${heavyAskBlock.level})`,
                guidance: '🚧 Strong Resistance Wall / Supply'
            });

            if (alertSettings.autoLog) {
                logOpportunityCall({
                    signalType: 'SELL_BLOCK',
                    action: 'SELL (RESISTANCE WALL)',
                    symbol: state.symbol,
                    entryPrice: heavyAskBlock.price,
                    slPrice: (heavyAskBlock.price + 0.20).toFixed(2),
                    targetPrice: (state.depthData.ltp - 35.0).toFixed(2),
                    optEstEntry: 135,
                    optEstSl: 115,
                    optEstTgt: 175,
                    confluence: 30,
                    rationale: `⚡ Institutional Ask Block: ${heavyAskBlock.qty.toLocaleString()} Qty at ₹${heavyAskBlock.price.toFixed(2)} (Level ${heavyAskBlock.level})`
                });
            }
        } else {
            banner.className = 'hidden';
        }
    }

    // ── 12. SOUND SYNTHESIS ENGINE (5 WEB AUDIO PROFILES) ──
    let _sharedAudioCtx = null;
    function getAudioCtx() {
        try {
            if (!_sharedAudioCtx) {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (AudioCtx) _sharedAudioCtx = new AudioCtx();
            }
            if (_sharedAudioCtx && _sharedAudioCtx.state === 'suspended') {
                _sharedAudioCtx.resume();
            }
        } catch(e) {
            console.warn('AudioContext init error:', e);
        }
        return _sharedAudioCtx;
    }

    // Auto-unlock audio context on user interaction
    ['click', 'touchstart', 'keydown'].forEach(evt => {
        document.addEventListener(evt, () => {
            getAudioCtx();
        }, { passive: true });
    });

    function playSynthesizedAlert(profile = 'crystal', side = 'BUY') {
        try {
            const ctx = getAudioCtx();
            if (!ctx) return;
            if (ctx.state === 'suspended') {
                ctx.resume();
            }
            const now = ctx.currentTime;
            const isBuy = (side === 'BUY');

            switch (profile) {
                case 'crystal': {
                    // 🔔 1. Crystal Chime: High resonant dual sine bells with sparkling decay
                    const rootFreq = isBuy ? 880 : 587.33; // A5 (Bull) vs D5 (Bear)
                    const secondFreq = isBuy ? 1318.5 : 440; // E6 vs A4

                    [rootFreq, secondFreq].forEach((f, idx) => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(f, now + idx * 0.08);

                        gain.gain.setValueAtTime(0, now + idx * 0.08);
                        gain.gain.linearRampToValueAtTime(0.24, now + idx * 0.08 + 0.02);
                        gain.gain.exponentialRampToValueAtTime(0.0001, now + idx * 0.08 + 0.55);

                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.start(now + idx * 0.08);
                        osc.stop(now + idx * 0.08 + 0.56);
                    });
                    break;
                }

                case 'radar': {
                    // 📡 2. Radar Sonar Pulse: Deep ping with frequency envelope and lingering echo
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    const startFreq = isBuy ? 1400 : 720;
                    const endFreq = isBuy ? 1680 : 480;

                    osc.frequency.setValueAtTime(startFreq, now);
                    osc.frequency.exponentialRampToValueAtTime(endFreq, now + 0.15);

                    gain.gain.setValueAtTime(0.32, now);
                    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.55);

                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(now);
                    osc.stop(now + 0.56);
                    break;
                }

                case 'laser': {
                    // ⚡ 3. Cyber Laser Sweep: Sci-fi rapid frequency plunge or surge
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sawtooth';

                    if (isBuy) {
                        osc.frequency.setValueAtTime(400, now);
                        osc.frequency.exponentialRampToValueAtTime(1600, now + 0.18);
                    } else {
                        osc.frequency.setValueAtTime(1400, now);
                        osc.frequency.exponentialRampToValueAtTime(300, now + 0.18);
                    }

                    gain.gain.setValueAtTime(0.18, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.25);

                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(now);
                    osc.stop(now + 0.26);
                    break;
                }

                case 'marimba': {
                    // 🎶 4. Warm Marimba: 3-note warm acoustic arpeggio
                    const notes = isBuy ? [523.25, 659.25, 783.99] : [783.99, 659.25, 523.25]; // C5-E5-G5 or G5-E5-C5
                    notes.forEach((freq, idx) => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.type = 'triangle';
                        osc.frequency.setValueAtTime(freq, now + idx * 0.08);

                        gain.gain.setValueAtTime(0, now + idx * 0.08);
                        gain.gain.linearRampToValueAtTime(0.25, now + idx * 0.08 + 0.015);
                        gain.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.08 + 0.35);

                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.start(now + idx * 0.08);
                        osc.stop(now + idx * 0.08 + 0.36);
                    });
                    break;
                }

                case 'tactical': {
                    // 🎯 5. Tactical Double-Pip: Crisp dual military pip clicks
                    [0, 0.09].forEach((delay, idx) => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.type = 'square';
                        const freq = isBuy ? (idx === 0 ? 987 : 1318) : (idx === 0 ? 659 : 493);
                        osc.frequency.setValueAtTime(freq, now + delay);

                        gain.gain.setValueAtTime(0.14, now + delay);
                        gain.gain.exponentialRampToValueAtTime(0.001, now + delay + 0.06);

                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.start(now + delay);
                        osc.stop(now + delay + 0.065);
                    });
                    break;
                }
            }
        } catch(e) {
            console.warn('Audio playback error:', e);
        }
    }

    function getSoundProfileLabel(profile) {
        const labels = {
            crystal: '🔔 Crystal Chime',
            radar: '📡 Radar Sonar',
            laser: '⚡ Cyber Laser',
            marimba: '🎶 Warm Marimba',
            tactical: '🎯 Tactical Pip'
        };
        return labels[profile] || '🔔 Chime';
    }

    function recordAlertEvent(event) {
        state.recentAlertEvents = state.recentAlertEvents || [];
        if (state.recentAlertEvents.length > 0) {
            const last = state.recentAlertEvents[0];
            if (last.type === event.type && (Date.now() - last.timestamp) < 6000) {
                return;
            }
        }

        const alertItem = {
            id: Date.now(),
            timestamp: Date.now(),
            time: new Date().toLocaleTimeString('en-IN', { hour12: false }),
            type: event.type,
            side: event.side,
            title: event.title,
            details: event.details,
            sound: getSoundProfileLabel(alertSettings.soundProfile || 'crystal'),
            guidance: event.guidance || ''
        };

        state.recentAlertEvents.unshift(alertItem);
        if (state.recentAlertEvents.length > 50) {
            state.recentAlertEvents.pop();
        }

        renderAlertFeedUI();
    }

    function renderAlertFeedUI() {
        const countBadge = document.getElementById('badge-alert-count');
        const container = document.getElementById('live-alerts-history-container');
        if (countBadge) countBadge.textContent = (state.recentAlertEvents || []).length;

        if (!container) return;
        if (!state.recentAlertEvents || state.recentAlertEvents.length === 0) {
            container.innerHTML = `<div class="text-center py-6 text-slate-500 text-[10px]">No alarms triggered yet this session. When audio sounds, exact trigger reasons appear here in real-time.</div>`;
            return;
        }

        let html = '';
        state.recentAlertEvents.forEach(a => {
            const isBuy = (a.side === 'BUY');
            const isBlock = a.type.includes('BLOCK');
            const isStack = a.type.includes('STACK');
            const badgeBg = isBlock ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40' : (isBuy ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-rose-500/20 text-rose-300 border border-rose-500/40');
            const badgeIcon = isBlock ? '⚡ BLOCK' : (isStack ? '🌊 STACK' : (isBuy ? '🚀 BUY' : '🩸 SELL'));

            html += `
            <div class="p-1.5 rounded-lg bg-slate-950 border ${isBuy ? 'border-emerald-800/40' : 'border-rose-800/40'} text-[10px] space-y-1">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5 font-mono">
                        <span class="text-slate-400 text-[9px]">${a.time}</span>
                        <span class="px-1 py-0.2 rounded font-black text-[8px] uppercase ${badgeBg}">
                            ${badgeIcon}
                        </span>
                        <span class="font-bold text-white text-[10.5px] truncate max-w-[130px]">${a.title}</span>
                    </div>
                    <span class="text-[8.5px] text-amber-400 font-mono bg-slate-900 px-1 py-0.2 rounded border border-slate-800">${a.sound}</span>
                </div>
                <div class="flex items-center justify-between text-[9px] text-slate-300 pt-0.5 border-t border-slate-900">
                    <span class="text-slate-400 font-mono">${a.details}</span>
                    <span class="font-bold ${isBuy ? 'text-emerald-400' : 'text-rose-400'}">${a.guidance}</span>
                </div>
            </div>
            `;
        });
        container.innerHTML = html;
    }

    window.clearLiveAlertHistory = function() {
        state.recentAlertEvents = [];
        renderAlertFeedUI();
    };

    function triggerStackAlertAudio(side) {
        if (!state.soundEnabled || !alertSettings.soundEnabled) return;
        const now = Date.now();
        if (now - state.lastStackAlertTime < 8000) return; // 8s throttle
        state.lastStackAlertTime = now;

        playSynthesizedAlert(alertSettings.soundProfile || 'crystal', side);
    }

    // ── 13. RENDER 30-DEPTH DOM VISUAL LADDER WITH CUSTOM HIGHLIGHTS & HEATMAP ──
    function renderDOM() {
        const container = document.getElementById('dom-rows-container');
        if (!container) return;

        const bids = state.depthData.bids;
        const asks = state.depthData.asks;
        const ltp = state.depthData.ltp;
        const bidStack = state.depthData.bidStack;
        const askStack = state.depthData.askStack;
        const spread = state.depthData.spread;
        const microPrice = state.depthData.microPrice;
        const drift = parseFloat((microPrice - ltp).toFixed(2));

        // 1. Sticky LTP banner, Spread Traffic Light & Directional Pull Vector
        const bannerLtp = document.getElementById('banner-ltp-val');
        const bannerSpread = document.getElementById('banner-spread-val');
        const bannerVwap = document.getElementById('banner-vwap-pill');
        const bannerSpreadGuard = document.getElementById('banner-spread-guard');
        const bannerMicroVector = document.getElementById('banner-micro-vector');

        if (bannerLtp) bannerLtp.textContent = `₹${ltp.toFixed(2)}`;
        if (bannerSpread) bannerSpread.textContent = `${spread.toFixed(2)} pts`;
        if (bannerVwap) bannerVwap.textContent = `VWAP: ₹${state.depthData.atp > 0 ? state.depthData.atp.toFixed(2) : ltp.toFixed(2)}`;

        // Spread Traffic Light Safety Guard
        if (bannerSpreadGuard) {
            if (spread <= 0.35) {
                bannerSpreadGuard.className = 'px-1.5 py-0.2 rounded font-black text-[9px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/50';
                bannerSpreadGuard.textContent = '🟢 TIGHT (SAFE)';
            } else if (spread <= 1.00) {
                bannerSpreadGuard.className = 'px-1.5 py-0.2 rounded font-black text-[9px] bg-amber-500/20 text-amber-300 border border-amber-500/50';
                bannerSpreadGuard.textContent = '🟡 MODERATE (LIMIT ONLY)';
            } else {
                bannerSpreadGuard.className = 'px-1.5 py-0.2 rounded font-black text-[9px] bg-rose-500/25 text-rose-300 border border-rose-500/60 animate-pulse';
                bannerSpreadGuard.textContent = `🔴 WIDE SPREAD (${spread.toFixed(1)} pts)`;
            }
        }

        // Micro-Price Directional Vector Pull Arrow
        if (bannerMicroVector) {
            if (drift > 0.10) {
                bannerMicroVector.innerHTML = `<span class="inline-flex items-center gap-1 text-[10px] font-black text-emerald-300 animate-pulse"><span>▲ PULLING UP</span> <span class="font-mono">(+${drift.toFixed(2)} pts)</span></span>`;
            } else if (drift < -0.10) {
                bannerMicroVector.innerHTML = `<span class="inline-flex items-center gap-1 text-[10px] font-black text-rose-300 animate-pulse"><span>▼ PULLING DOWN</span> <span class="font-mono">(${drift.toFixed(2)} pts)</span></span>`;
            } else {
                bannerMicroVector.innerHTML = `<span class="text-[10px] font-bold text-slate-400">➔ BALANCED (±0.00)</span>`;
            }
        }

        // 2. Identify Point of Control (POC Anchor - Heaviest resting volume level)
        let pocSide = null;
        let pocIndex = -1;
        let maxBookQty = 0;

        bids.forEach((b, idx) => {
            if (b && b.qty > maxBookQty) {
                maxBookQty = b.qty;
                pocSide = 'BID';
                pocIndex = idx;
            }
        });

        asks.forEach((a, idx) => {
            if (a && a.qty > maxBookQty) {
                maxBookQty = a.qty;
                pocSide = 'ASK';
                pocIndex = idx;
            }
        });

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

            // 1. Single Level Block Check (User threshold)
            const isBidBlock = b && (b.qty >= alertSettings.singleThreshold);
            const isAskBlock = a && (a.qty >= alertSettings.singleThreshold);

            // 2. Consecutive Stack Check (User setting)
            const isBidInStack = bidStack && bidStack.levels.some(x => x.index === i);
            const isAskInStack = askStack && askStack.levels.some(x => x.index === i);

            // 3. Point of Control (POC Anchor) Check
            const isBidPoc = (pocSide === 'BID' && pocIndex === i && maxBookQty >= 1200);
            const isAskPoc = (pocSide === 'ASK' && pocIndex === i && maxBookQty >= 1200);

            // 4. LTP Row Check
            const isNearLtp = (b && Math.abs(b.price - ltp) < 0.05) || (a && Math.abs(a.price - ltp) < 0.05);

            // Dynamic Row Classes
            let rowClasses = "grid grid-cols-12 gap-1 items-center py-0.5 px-1.5 rounded transition text-[11px] relative ";
            if (isNearLtp) {
                rowClasses += "bg-amber-500/20 ring-1 ring-amber-400 font-bold ";
            } else if (isBidInStack) {
                rowClasses += "bg-emerald-950/60 border-l-4 border-emerald-400 shadow-[inset_0_0_10px_rgba(16,185,129,0.25)] ";
            } else if (isAskInStack) {
                rowClasses += "bg-rose-950/60 border-r-4 border-rose-400 shadow-[inset_0_0_10px_rgba(244,63,94,0.25)] ";
            } else if (isBidPoc || isAskPoc) {
                rowClasses += "bg-fuchsia-950/30 ring-1 ring-fuchsia-500/50 ";
            } else if (i % 2 === 0) {
                rowClasses += "bg-slate-950/40 hover:bg-slate-800/60 ";
            } else {
                rowClasses += "hover:bg-slate-800/60 ";
            }

            // Heatmap Bar Styling
            let bidFillClass = 'bg-emerald-500/20';
            if (b && b.qty >= 3000) {
                bidFillClass = 'bg-gradient-to-r from-emerald-500/60 via-emerald-400/40 to-emerald-300/30 shadow-[0_0_8px_rgba(16,185,129,0.35)]';
            } else if (b && b.qty >= 1500) {
                bidFillClass = 'bg-gradient-to-r from-emerald-500/35 to-emerald-400/25';
            }

            let askFillClass = 'bg-rose-500/20';
            if (a && a.qty >= 3000) {
                askFillClass = 'bg-gradient-to-r from-rose-500/60 via-rose-400/40 to-rose-300/30 shadow-[0_0_8px_rgba(244,63,94,0.35)]';
            } else if (a && a.qty >= 1500) {
                askFillClass = 'bg-gradient-to-r from-rose-500/35 to-rose-400/25';
            }

            // High-Impact Quantity Content & Badges
            let bidQtyHtml = '';
            if (b) {
                if (isBidBlock && isBidInStack) {
                    bidQtyHtml = `
                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded bg-gradient-to-r from-amber-500/30 to-emerald-500/30 border border-amber-400 text-amber-200 font-black font-mono text-[10.5px] shadow-[0_0_10px_rgba(251,191,36,0.6)] animate-pulse">
                            <span>⚡🌊</span>
                            <span>${b.qty.toLocaleString()}</span>
                        </span>
                    `;
                } else if (isBidInStack) {
                    bidQtyHtml = `
                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded bg-emerald-500/25 border border-emerald-400 text-emerald-200 font-black font-mono text-[10.5px] shadow-[0_0_8px_rgba(52,211,153,0.5)]">
                            <span>🌊</span>
                            <span>${b.qty.toLocaleString()}</span>
                        </span>
                    `;
                } else if (isBidBlock) {
                    bidQtyHtml = `
                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded bg-amber-500/25 border border-amber-400 text-amber-300 font-black font-mono text-[10.5px] shadow-[0_0_8px_rgba(251,191,36,0.5)] animate-pulse">
                            <span>⚡</span>
                            <span>${b.qty.toLocaleString()}</span>
                        </span>
                    `;
                } else {
                    bidQtyHtml = `<span class="font-mono font-bold text-slate-100">${b.qty.toLocaleString()}</span>`;
                }
            } else {
                bidQtyHtml = `<span class="text-slate-600">-</span>`;
            }

            let askQtyHtml = '';
            if (a) {
                if (isAskBlock && isAskInStack) {
                    askQtyHtml = `
                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded bg-gradient-to-r from-rose-500/30 to-amber-500/30 border border-amber-400 text-amber-200 font-black font-mono text-[10.5px] shadow-[0_0_10px_rgba(251,191,36,0.6)] animate-pulse">
                            <span>⚡🌊</span>
                            <span>${a.qty.toLocaleString()}</span>
                        </span>
                    `;
                } else if (isAskInStack) {
                    askQtyHtml = `
                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded bg-rose-500/25 border border-rose-400 text-rose-200 font-black font-mono text-[10.5px] shadow-[0_0_8px_rgba(244,63,94,0.5)]">
                            <span>🌊</span>
                            <span>${a.qty.toLocaleString()}</span>
                        </span>
                    `;
                } else if (isAskBlock) {
                    askQtyHtml = `
                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded bg-amber-500/25 border border-amber-400 text-amber-300 font-black font-mono text-[10.5px] shadow-[0_0_8px_rgba(251,191,36,0.5)] animate-pulse">
                            <span>⚡</span>
                            <span>${a.qty.toLocaleString()}</span>
                        </span>
                    `;
                } else {
                    askQtyHtml = `<span class="font-mono font-bold text-slate-100">${a.qty.toLocaleString()}</span>`;
                }
            } else {
                askQtyHtml = `<span class="text-slate-600">-</span>`;
            }

            // Orders Column Content with Stack Pills & POC Anchor Badges
            let bidOrderHtml = b ? `
                <div class="flex items-center gap-1">
                    ${isBidPoc ? '<span class="px-1 py-0.2 rounded text-[7px] font-black bg-fuchsia-500 text-white uppercase tracking-tighter shadow-xs" title="Point of Control (Resting Anchor)">👑 POC</span>' : ''}
                    ${isBidInStack ? '<span class="px-1 py-0.2 rounded text-[7.5px] font-black bg-emerald-500 text-slate-950 uppercase tracking-tighter shadow-xs">STACK</span>' : ''}
                    <span class="${isBidInStack ? 'text-emerald-300 font-bold' : 'text-slate-400'}">${b.orders || 1}</span>
                </div>
            ` : '-';

            let askOrderHtml = a ? `
                <div class="flex items-center justify-end gap-1">
                    <span class="${isAskInStack ? 'text-rose-300 font-bold' : 'text-slate-400'}">${a.orders || 1}</span>
                    ${isAskInStack ? '<span class="px-1 py-0.2 rounded text-[7.5px] font-black bg-rose-500 text-white uppercase tracking-tighter shadow-xs">STACK</span>' : ''}
                    ${isAskPoc ? '<span class="px-1 py-0.2 rounded text-[7px] font-black bg-fuchsia-500 text-white uppercase tracking-tighter shadow-xs" title="Point of Control (Resting Anchor)">👑 POC</span>' : ''}
                </div>
            ` : '-';

            html += `
            <div class="${rowClasses}">
                
                {{-- Bid Orders --}}
                <div class="col-span-2 text-left text-[10px]">
                    ${bidOrderHtml}
                </div>

                {{-- Bid Qty with horizontal fill bar & block/stack highlight --}}
                <div class="col-span-2 text-right relative pr-1.5 ${isBidBlock && !isBidInStack ? 'ring-1 ring-amber-400/60 bg-amber-500/10 rounded' : ''}">
                    <div class="absolute right-0 top-0 bottom-0 ${bidFillClass} rounded-l ${isBidBlock ? 'border-r-2 border-amber-400' : ''}" style="width: ${bidPct}%;"></div>
                    ${bidQtyHtml}
                </div>

                {{-- Bid Price --}}
                <div class="col-span-2 text-right font-mono font-black text-emerald-400 text-xs">
                    ${b ? b.price.toFixed(2) : '-'}
                </div>

                {{-- Ask Price --}}
                <div class="col-span-2 text-left font-mono font-black text-rose-400 text-xs pl-1.5">
                    ${a ? a.price.toFixed(2) : '-'}
                </div>

                {{-- Ask Qty with horizontal fill bar & block/stack highlight --}}
                <div class="col-span-2 text-left relative pl-1.5 ${isAskBlock && !isAskInStack ? 'ring-1 ring-amber-400/60 bg-amber-500/10 rounded' : ''}">
                    <div class="absolute left-0 top-0 bottom-0 ${askFillClass} rounded-r ${isAskBlock ? 'border-l-2 border-amber-400' : ''}" style="width: ${askPct}%;"></div>
                    ${askQtyHtml}
                </div>

                {{-- Ask Orders --}}
                <div class="col-span-2 text-right text-[10px]">
                    ${askOrderHtml}
                </div>

            </div>
            `;

            // Insert LTP divider right after Level 1 (i == 0)
            if (i === 0 && ltp > 0) {
                html += `
                <div class="my-0.5 py-0.5 px-2 rounded bg-amber-500/15 border-y border-amber-400/60 flex items-center justify-between text-[10px] font-black text-amber-300 shadow-sm">
                    <div class="flex items-center gap-1">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-ping"></span>
                        <span>◄ CURRENT TRADING PRICE (LTP):</span>
                        <span class="text-white bg-slate-900 px-1.5 py-0.2 rounded font-mono text-xs border border-amber-400/40">₹${ltp.toFixed(2)}</span>
                    </div>
                    <div class="flex items-center gap-2 text-[9px] text-slate-300 font-mono">
                        <span>Spread: <strong class="text-white">${spread.toFixed(2)} pts</strong></span>
                        <span class="${drift > 0.1 ? 'text-emerald-400' : (drift < -0.1 ? 'text-rose-400' : 'text-slate-400')} font-bold">
                            Pull: ${drift > 0 ? '+' : ''}${drift.toFixed(2)} pts ${drift > 0.1 ? '▲' : (drift < -0.1 ? '▼' : '⏸️')}
                        </span>
                    </div>
                </div>
                `;
            }
        }

        container.innerHTML = html;

        const bidTotEl = document.getElementById('val-total-bid-depth');
        const askTotEl = document.getElementById('val-total-ask-depth');
        if (bidTotEl) bidTotEl.textContent = totalBidSum.toLocaleString();
        if (askTotEl) askTotEl.textContent = totalAskSum.toLocaleString();
    }

    // ── 13. UPDATE MICROSTRUCTURE UI & FORMULA TRACES ──
    function updateMicrostructureUI() {
        const d = state.depthData;

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

        updateObiBar('5', d.obi5);
        updateObiBar('10', d.obi10);
        updateObiBar('30', d.obi30);

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
        const spoofScore = document.getElementById('wall-spoof-score');
        const absVal = document.getElementById('val-absorption-pct');
        const absBar = document.getElementById('bar-absorption');
        if (!list) return;

        const walls = state.depthData.liquidityWalls;
        const spoofCount = state.depthData.pulledSpoofCount || 0;

        if (spoofScore) {
            if (spoofCount > 0) {
                spoofScore.innerHTML = `<span class="text-amber-400 font-bold">⚠️ ${spoofCount} Spoofs Pulled</span>`;
            } else if (walls.some(w => w.status === 'GENUINE')) {
                spoofScore.innerHTML = `<span class="text-emerald-400 font-bold">🟢 Genuine Book (High Trust)</span>`;
            } else {
                spoofScore.innerHTML = `<span class="text-slate-400 font-bold">Scanning Depth...</span>`;
            }
        }

        if (walls.length === 0) {
            list.innerHTML = `<div class="p-1.5 rounded-lg bg-slate-950 border border-slate-800 text-slate-400 text-center text-[10px]">No institutional walls detected.</div>`;
            if (absVal) absVal.textContent = '0%';
            if (absBar) absBar.style.width = '0%';
            return;
        }

        let html = '';
        let totalWallQty = 0;
        let persistentQty = 0;

        walls.slice(0, 3).forEach(w => {
            const isBid = w.side === 'BID';
            const isGenuine = w.status === 'GENUINE';
            totalWallQty += w.qty;
            if (isGenuine) persistentQty += w.qty;

            html += `
            <div class="flex items-center justify-between p-1.5 rounded-lg bg-slate-950 border ${isBid ? 'border-emerald-800/50' : 'border-rose-800/50'} text-[11px]">
                <div class="flex items-center gap-1">
                    <span class="px-1 py-0.2 rounded text-[9px] font-black ${isBid ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300'}">
                        ${w.side} (L${w.level})
                    </span>
                    <span class="font-bold text-white font-mono">₹${w.price.toFixed(2)}</span>
                    <span class="px-1 py-0.2 rounded text-[8px] font-bold ${isGenuine ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30'}">
                        ${isGenuine ? '✓ HELD' : 'NEW'}
                    </span>
                </div>
                <div class="flex items-center gap-1 font-mono">
                    <span class="font-black ${isBid ? 'text-emerald-400' : 'text-rose-400'}">${w.qty.toLocaleString()}</span>
                    <span class="text-[9px] text-amber-400 font-bold">(${w.multiple}x)</span>
                </div>
            </div>
            `;
        });

        list.innerHTML = html;

        // Calculate Wall Absorption %
        const absPct = totalWallQty > 0 ? Math.min(100, Math.round((persistentQty / totalWallQty) * 100)) : 0;
        if (absVal) absVal.textContent = `${absPct}% (${persistentQty.toLocaleString()} Qty)`;
        if (absBar) absBar.style.width = `${absPct}%`;
    }

    // ── 14. MULTI-STRATEGY CONFLUENCE & TRADE SUGGESTION ENGINE ──
    function evaluateConfluenceRadar() {
        const d = state.depthData;
        const signals = state.strategySignals?.factors || {};
        const unified = signals.unified_macro;

        let quantScore = signals.quant_engine ? signals.quant_engine.score : 50;
        let oiScore = signals.oi_buildup ? signals.oi_buildup.score : 50;
        let breadthScore = signals.market_breadth ? signals.market_breadth.score : 50;

        let depthScore = 50 + (d.obi30 * 0.45);
        if (d.microPrice > d.ltp + 0.1) depthScore += 8;
        if (d.microPrice < d.ltp - 0.1) depthScore -= 8;

        // Boost score if institutional stack detected
        if (d.bidStack) depthScore += 12;
        if (d.askStack) depthScore -= 12;

        // Boost score if single heavy block detected
        if (d.heavyBidBlock) depthScore += 8;
        if (d.heavyAskBlock) depthScore -= 8;

        depthScore = Math.max(5, Math.min(95, Math.round(depthScore)));

        const totalConfluence = Math.round(
            (quantScore * 0.25) +
            (oiScore * 0.25) +
            (breadthScore * 0.20) +
            (depthScore * 0.30)
        );

        // Update Confluence scorecard badges dynamically
        const badgeQuant = document.querySelector('#engine-card-quant span:last-child');
        const badgeOi = document.querySelector('#engine-card-oi span:last-child');
        const badgeBreadth = document.querySelector('#engine-card-breadth span:last-child');
        if (badgeQuant && signals.quant_engine) {
            badgeQuant.textContent = signals.quant_engine.signal;
            badgeQuant.className = signals.quant_engine.signal === 'BULLISH' ? 'font-bold text-emerald-400' : (signals.quant_engine.signal === 'BEARISH' ? 'font-bold text-rose-400' : 'font-bold text-slate-300');
        }
        if (badgeOi && signals.oi_buildup) {
            badgeOi.textContent = signals.oi_buildup.signal;
            badgeOi.className = signals.oi_buildup.signal === 'BULLISH' ? 'font-bold text-emerald-400' : (signals.oi_buildup.signal === 'BEARISH' ? 'font-bold text-rose-400' : 'font-bold text-slate-300');
        }
        if (badgeBreadth && signals.market_breadth) {
            badgeBreadth.textContent = signals.market_breadth.signal;
            badgeBreadth.className = signals.market_breadth.signal === 'BULLISH' ? 'font-bold text-emerald-400' : (signals.market_breadth.signal === 'BEARISH' ? 'font-bold text-rose-400' : 'font-bold text-slate-300');
        }

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

        // Check for Counter-Trend Liquidity Absorption Wall
        const isUnifiedSell = unified?.has_call && unified.signal === 'SELL';
        const isUnifiedBuy = unified?.has_call && unified.signal === 'BUY';

        if (isUnifiedSell && d.heavyBidBlock && d.heavyBidBlock.level <= 3) {
            // Unified is Bearish, but resting buy wall is holding the line
            if (sigText) sigText.textContent = '🛡️ BID WALL ABSORPTION (WAIT FOR BREAK)';
            if (sigPill) {
                sigPill.textContent = 'ABSORPTION WALL';
                sigPill.className = 'px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-500 text-slate-950';
            }
            if (sigIcon) sigIcon.textContent = '⚡';
            if (heroBadge) {
                heroBadge.textContent = 'SUPPORT WALL';
                heroBadge.className = 'px-1 py-0.2 rounded text-[9px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40';
            }
            if (tradeInst) tradeInst.textContent = `NIFTY ${unified.strike} CE (Sell) / PE (Buy)`;
            if (tradeEntry) tradeEntry.textContent = `₹${(d.heavyBidBlock.price - 0.10).toFixed(2)} (Post-Break)`;
            if (tradeSl) tradeSl.textContent = `₹${(d.heavyBidBlock.price + 5.0).toFixed(2)}`;
            if (tradeTarget) tradeTarget.textContent = `₹${unified.tp_price ? unified.tp_price.toFixed(2) : (d.ltp - 40).toFixed(2)}`;
            if (tradeRationale) {
                tradeRationale.textContent = `Unified 5m Trend is BEARISH (${unified.candle_time} SELL ${unified.strike} CE), BUT Level ${d.heavyBidBlock.level} DOM has ${d.heavyBidBlock.qty.toLocaleString()} Qty Bid Wall at ₹${d.heavyBidBlock.price.toFixed(2)}. Do not short directly into this buy block — wait for wall consumption before entry!`;
            }
        } else if (isUnifiedBuy && d.heavyAskBlock && d.heavyAskBlock.level <= 3) {
            // Unified is Bullish, but resting sell wall is capping the move
            if (sigText) sigText.textContent = '🚧 ASK WALL RESISTANCE (WAIT FOR BREAK)';
            if (sigPill) {
                sigPill.textContent = 'RESISTANCE WALL';
                sigPill.className = 'px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-500 text-slate-950';
            }
            if (sigIcon) sigIcon.textContent = '⚡';
            if (heroBadge) {
                heroBadge.textContent = 'RESISTANCE WALL';
                heroBadge.className = 'px-1 py-0.2 rounded text-[9px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40';
            }
            if (tradeInst) tradeInst.textContent = `NIFTY ${unified.strike} PE (Sell) / CE (Buy)`;
            if (tradeEntry) tradeEntry.textContent = `₹${(d.heavyAskBlock.price + 0.10).toFixed(2)} (Post-Break)`;
            if (tradeSl) tradeSl.textContent = `₹${(d.heavyAskBlock.price - 5.0).toFixed(2)}`;
            if (tradeTarget) tradeTarget.textContent = `₹${unified.tp_price ? unified.tp_price.toFixed(2) : (d.ltp + 40).toFixed(2)}`;
            if (tradeRationale) {
                tradeRationale.textContent = `Unified 5m Trend is BULLISH (${unified.candle_time} BUY ${unified.strike} PE), BUT Level ${d.heavyAskBlock.level} DOM has ${d.heavyAskBlock.qty.toLocaleString()} Qty Ask Wall at ₹${d.heavyAskBlock.price.toFixed(2)}. Do not buy directly into this wall — wait for breakout before entry!`;
            }
        } else if (totalConfluence >= 68 || isUnifiedBuy) {
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

            const slPrice = unified?.sl_price ? unified.sl_price.toFixed(2) : (bidWall ? (bidWall.price - 0.20).toFixed(2) : (d.ltp * 0.96).toFixed(2));
            const targetPrice = unified?.tp_price ? unified.tp_price.toFixed(2) : (askWall ? (askWall.price).toFixed(2) : (d.ltp * 1.08).toFixed(2));

            const instLabel = unified?.has_call ? `NIFTY ${unified.strike} PE (Sell ITM) / CE (Buy)` : targetName;
            if (tradeInst) tradeInst.textContent = instLabel;
            if (tradeEntry) tradeEntry.textContent = `₹${(d.ltp - 0.15).toFixed(2)} - ₹${d.ltp.toFixed(2)}`;
            if (tradeSl) tradeSl.textContent = `₹${slPrice}`;
            if (tradeTarget) tradeTarget.textContent = `₹${targetPrice}`;
            if (tradeRationale) {
                const stackDesc = d.bidStack ? `with ${d.bidStack.count}-level Bid Stack` : (d.heavyBidBlock ? `with ${d.heavyBidBlock.qty.toLocaleString()} Qty Bid Block` : '');
                const uniDesc = unified?.has_call ? `Unified ${unified.candle_time} BUY (${unified.breadth_weight}% Green) + ` : '';
                tradeRationale.textContent = `${uniDesc}30-Depth OBI (+${d.obi30}%) ${stackDesc} confirms buying momentum, backed by Quant & Breadth.`;
            }

            triggerAlertAudio('BUY');

            recordAlertEvent({
                type: 'STRATEGY_BUY',
                side: 'BUY',
                title: 'High-Confidence BUY Setup',
                details: `${totalConfluence}% Confluence (${instLabel})`,
                guidance: '⚡ Direction: GO LONG / BUY CALL'
            });

        } else if (totalConfluence <= 35 || isUnifiedSell) {
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

            const slPrice = unified?.sl_price ? unified.sl_price.toFixed(2) : (askWall ? (askWall.price + 0.20).toFixed(2) : (d.ltp * 1.04).toFixed(2));
            const targetPrice = unified?.tp_price ? unified.tp_price.toFixed(2) : (bidWall ? (bidWall.price).toFixed(2) : (d.ltp * 0.92).toFixed(2));

            const instLabel = unified?.has_call ? `NIFTY ${unified.strike} CE (Sell ITM) / PE (Buy)` : targetName;
            if (tradeInst) tradeInst.textContent = instLabel;
            if (tradeEntry) tradeEntry.textContent = `₹${d.ltp.toFixed(2)} - ₹${(d.ltp + 0.15).toFixed(2)}`;
            if (tradeSl) tradeSl.textContent = `₹${slPrice}`;
            if (tradeTarget) tradeTarget.textContent = `₹${targetPrice}`;
            if (tradeRationale) {
                const stackDesc = d.askStack ? `and ${d.askStack.count}-level Ask Stack` : (d.heavyAskBlock ? `and ${d.heavyAskBlock.qty.toLocaleString()} Qty Ask Block` : '');
                const uniDesc = unified?.has_call ? `Unified ${unified.candle_time} SELL (${unified.breadth_weight}% Red) + ` : '';
                tradeRationale.textContent = `${uniDesc}Ask Wall dominating with negative OBI (${d.obi30}%) ${stackDesc}. Downside momentum confirmed.`;
            }

            triggerAlertAudio('SELL');

            recordAlertEvent({
                type: 'STRATEGY_SELL',
                side: 'SELL',
                title: 'High-Confidence SELL Setup',
                details: `${totalConfluence}% Confluence (${instLabel})`,
                guidance: '⚡ Direction: GO SHORT / BUY PUT'
            });

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

        // ── CALCULATE INSTANT OPTION STRIKE SETUP (Options Trader Mode) ──
        const elOptBadge  = document.getElementById('opt-rec-badge');
        const elOptStrike = document.getElementById('opt-rec-strike');
        const elOptEntry  = document.getElementById('opt-rec-entry');
        const elOptSlTgt  = document.getElementById('opt-rec-sltgt');

        const atmRounded = Math.round(d.ltp / 50) * 50;
        let optSymbol = `NIFTY ${atmRounded} CE`;
        let optAction = 'WAIT';
        let optEntryStr = '--';
        let optSlStr = '--';
        let optTgtStr = '--';

        if (totalConfluence >= 75) {
            optAction = 'BUY CALL (CE)';
            const recommendedStrike = atmRounded;
            if (state.contracts?.strikes?.[recommendedStrike]?.CE?.trading_symbol) {
                optSymbol = state.contracts.strikes[recommendedStrike].CE.trading_symbol;
            } else if (state.contracts?.atm_ce?.trading_symbol) {
                optSymbol = state.contracts.atm_ce.trading_symbol;
            } else {
                optSymbol = `NIFTY ${recommendedStrike} CE`;
            }

            const estPremium = Math.max(75, Math.round(115 + (d.ltp - recommendedStrike) * 0.65));
            optEntryStr = `₹${estPremium - 3} - ₹${estPremium + 2}`;
            optSlStr = `₹${Math.max(10, estPremium - 18)}`;
            optTgtStr = `₹${estPremium + 42}`;

            if (elOptBadge) {
                elOptBadge.textContent = 'BUY CALL (CE)';
                elOptBadge.className = 'px-1.5 py-0.2 rounded text-[9px] font-black bg-emerald-500 text-slate-950 uppercase';
            }
            if (elOptStrike) elOptStrike.textContent = optSymbol;
            if (elOptEntry) elOptEntry.textContent = optEntryStr;
            if (elOptSlTgt) elOptSlTgt.textContent = `SL: ${optSlStr} | TGT: ${optTgtStr}`;

        } else if (totalConfluence <= 32) {
            optAction = 'BUY PUT (PE)';
            const recommendedStrike = atmRounded;
            if (state.contracts?.strikes?.[recommendedStrike]?.PE?.trading_symbol) {
                optSymbol = state.contracts.strikes[recommendedStrike].PE.trading_symbol;
            } else if (state.contracts?.atm_pe?.trading_symbol) {
                optSymbol = state.contracts.atm_pe.trading_symbol;
            } else {
                optSymbol = `NIFTY ${recommendedStrike} PE`;
            }

            const estPremium = Math.max(75, Math.round(115 + (recommendedStrike - d.ltp) * 0.65));
            optEntryStr = `₹${estPremium - 3} - ₹${estPremium + 2}`;
            optSlStr = `₹${Math.max(10, estPremium - 18)}`;
            optTgtStr = `₹${estPremium + 42}`;

            if (elOptBadge) {
                elOptBadge.textContent = 'BUY PUT (PE)';
                elOptBadge.className = 'px-1.5 py-0.2 rounded text-[9px] font-black bg-rose-500 text-white uppercase';
            }
            if (elOptStrike) elOptStrike.textContent = optSymbol;
            if (elOptEntry) elOptEntry.textContent = optEntryStr;
            if (elOptSlTgt) elOptSlTgt.textContent = `SL: ${optSlStr} | TGT: ${optTgtStr}`;

        } else {
            optAction = 'WAIT';
            optSymbol = `NIFTY ${atmRounded} (WAIT)`;
            optEntryStr = 'Stand Aside';
            optSlStr = 'No Position';
            optTgtStr = 'Range Bound';

            if (elOptBadge) {
                elOptBadge.textContent = 'STAND ASIDE';
                elOptBadge.className = 'px-1.5 py-0.2 rounded text-[9px] font-bold bg-slate-800 text-slate-400';
            }
            if (elOptStrike) elOptStrike.textContent = optSymbol;
            if (elOptEntry) elOptEntry.textContent = optEntryStr;
            if (elOptSlTgt) elOptSlTgt.textContent = 'SL: -- | TGT: --';
        }

        // Cache setup for 1-click clipboard copy
        state.currentOptionTrade = {
            action: optAction,
            symbol: optSymbol,
            entry: optEntryStr,
            sl: optSlStr,
            target: optTgtStr,
            lotSize: 25,
            confluence: totalConfluence,
            drift: `${parseFloat((d.microPrice - d.ltp).toFixed(2))} pts`
        };

        // Auto-Log High Probability Opportunity into Journal
        if (optAction !== 'WAIT' && alertSettings.autoLog) {
            logOpportunityCall({
                action: optAction,
                symbol: optSymbol,
                strike: recommendedStrike,
                slPrice: (optAction.includes('CALL') ? d.ltp - 18 : d.ltp + 18),
                targetPrice: (optAction.includes('CALL') ? d.ltp + 42 : d.ltp - 42),
                optEstEntry: estPremium,
                optEstSl: Math.max(10, estPremium - 18),
                optEstTgt: estPremium + 42,
                confluence: totalConfluence,
                rationale: tradeRationale ? tradeRationale.textContent.trim() : 'Confluence signal'
            });
        }
    }

    function triggerAlertAudio(type) {
        if (!state.soundEnabled || !alertSettings.soundEnabled) return;
        const now = Date.now();
        if (now - state.lastAlertTime < 30000) return;
        state.lastAlertTime = now;

        playSynthesizedAlert(alertSettings.soundProfile || 'crystal', type);
    }

    // ── 15. JOURNAL LOGGING & OUTCOME VERIFICATION ENGINE ──
    async function logOpportunityCall(tradeSetup) {
        if (!alertSettings.autoLog) return;
        const now = Date.now();
        if (now - state.lastSignalLogTime < 15000) return; // 15s debounce between calls
        state.lastSignalLogTime = now;

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
            const payload = {
                mode: state.feedMode,
                symbol: state.symbol || 'NIFTY',
                signal_type: tradeSetup.signalType || (tradeSetup.action.includes('CALL') ? 'BUY_CE' : (tradeSetup.action.includes('BUY') ? 'BUY_BLOCK' : 'SELL_BLOCK')),
                action: tradeSetup.action,
                instrument: tradeSetup.symbol || state.activeInstrumentKey || 'NIFTY FUT',
                spot_price: state.depthData.ltp,
                future_price: state.depthData.ltp,
                entry_price: parseFloat(tradeSetup.entryPrice || state.depthData.ltp),
                stop_loss: parseFloat(tradeSetup.slPrice),
                target_price: parseFloat(tradeSetup.targetPrice),
                opt_strike: tradeSetup.strike || Math.round(state.depthData.ltp / 50) * 50,
                opt_type: (tradeSetup.action.includes('CALL') || tradeSetup.action.includes('BUY')) ? 'CE' : 'PE',
                opt_entry: tradeSetup.optEstEntry || 135,
                opt_sl: tradeSetup.optEstSl || 115,
                opt_target: tradeSetup.optEstTgt || 175,
                confluence_score: tradeSetup.confluence || 50,
                obi_pct: state.depthData.obi30,
                micro_drift: parseFloat((state.depthData.microPrice - state.depthData.ltp).toFixed(2)),
                trigger_reason: tradeSetup.rationale || 'Liquidity event'
            };

            const resp = await fetch(`{{ route('api.order-flow.log-signal') }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const res = await resp.json();
            if (res.success && res.data && res.data.id) {
                state.activeLoggedSignals.push(res.data);
                refreshSignalLogs();
            }
        } catch (e) {
            console.error('Failed logging opportunity call:', e);
        }
    }

    async function checkActiveSignalsOutcome(currentLtp) {
        if (!state.activeLoggedSignals || state.activeLoggedSignals.length === 0) return;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

        for (let i = state.activeLoggedSignals.length - 1; i >= 0; i--) {
            const sig = state.activeLoggedSignals[i];
            const isBuy = (sig.signal_type === 'BUY_CE' || sig.action.includes('CALL') || sig.action.includes('BUY'));
            const pnlPts = isBuy ? (currentLtp - sig.entry_price) : (sig.entry_price - currentLtp);

            sig.max_favorable_pts = Math.max(sig.max_favorable_pts || 0, pnlPts);
            sig.max_adverse_pts = Math.min(sig.max_adverse_pts || 0, pnlPts);

            let newStatus = null;
            if (isBuy) {
                if (currentLtp >= sig.target_price) newStatus = 'TARGET_HIT';
                else if (currentLtp <= sig.stop_loss) newStatus = 'STOPPED_OUT';
            } else {
                if (currentLtp <= sig.target_price) newStatus = 'TARGET_HIT';
                else if (currentLtp >= sig.stop_loss) newStatus = 'STOPPED_OUT';
            }

            if (newStatus) {
                state.activeLoggedSignals.splice(i, 1);
                try {
                    await fetch(`{{ route('api.order-flow.update-outcome') }}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            id: sig.id,
                            status: newStatus,
                            exit_price: currentLtp,
                            pnl_points: parseFloat(pnlPts.toFixed(2)),
                            max_favorable_pts: parseFloat((sig.max_favorable_pts || 0).toFixed(2)),
                            max_adverse_pts: parseFloat((sig.max_adverse_pts || 0).toFixed(2))
                        })
                    });
                    refreshSignalLogs();
                } catch(e) {}
            }
        }
    }

    window.refreshSignalLogs = async function() {
        try {
            const resp = await fetch(`{{ route('api.order-flow.signal-logs') }}?mode=${state.feedMode}`);
            const res = await resp.json();
            if (res.success && res.summary) {
                const s = res.summary;
                const totalEl = document.getElementById('journal-total-count');
                const winRateEl = document.getElementById('journal-win-rate');
                const tgtEl = document.getElementById('journal-target-count');
                const stpEl = document.getElementById('journal-stopped-count');
                const pnlEl = document.getElementById('journal-net-pnl');

                if (totalEl) totalEl.textContent = s.total_signals;
                if (winRateEl) {
                    winRateEl.textContent = `${s.win_rate}%`;
                    winRateEl.className = s.win_rate >= 60 ? 'font-black text-emerald-400 text-xs' : (s.win_rate <= 40 ? 'font-black text-rose-400 text-xs' : 'font-black text-amber-300 text-xs');
                }
                if (tgtEl) tgtEl.textContent = s.target_hit;
                if (stpEl) stpEl.textContent = s.stopped_out;
                if (pnlEl) {
                    pnlEl.textContent = `${s.net_pnl_pts >= 0 ? '+' : ''}${s.net_pnl_pts} pts`;
                    pnlEl.className = s.net_pnl_pts > 0 ? 'font-black text-emerald-400 text-xs font-mono' : (s.net_pnl_pts < 0 ? 'font-black text-rose-400 text-xs font-mono' : 'font-black text-slate-300 text-xs font-mono');
                }

                state.activeLoggedSignals = (res.logs || []).filter(l => l.status === 'ACTIVE');

                const container = document.getElementById('journal-logs-container');
                if (!container) return;

                if (!res.logs || res.logs.length === 0) {
                    container.innerHTML = `<div class="text-center py-6 text-slate-500 text-[10px]">No trade signals logged yet today. Waiting for setup triggers...</div>`;
                    return;
                }

                let html = '';
                res.logs.forEach(l => {
                    const isBuy = l.action.includes('CALL') || l.action.includes('BUY') || (l.signal_type && l.signal_type.includes('BUY'));
                    let statusBadge = '';
                    if (l.status === 'TARGET_HIT') {
                        statusBadge = `<span class="px-1.5 py-0.2 rounded text-[8px] font-black bg-emerald-500 text-slate-950 uppercase">TARGET HIT (+${l.pnl_points} pts)</span>`;
                    } else if (l.status === 'STOPPED_OUT') {
                        statusBadge = `<span class="px-1.5 py-0.2 rounded text-[8px] font-black bg-rose-500 text-white uppercase">STOPPED (${l.pnl_points} pts)</span>`;
                    } else {
                        statusBadge = `<span class="px-1.5 py-0.2 rounded text-[8px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 animate-pulse">ACTIVE (${l.max_favorable_pts > 0 ? '+' + l.max_favorable_pts : '0'} MFE)</span>`;
                    }

                    const actionBadgeText = l.action.includes('WALL') ? (isBuy ? '🛡️ BUY WALL' : '🚧 SELL WALL') : (l.action.includes('STACK') ? (isBuy ? '🌊 BUY STACK' : '🌊 SELL STACK') : (isBuy ? '▲ CALL' : '▼ PUT'));

                    html += `
                    <div class="p-1.5 rounded-lg bg-slate-950 border ${isBuy ? 'border-emerald-800/40' : 'border-rose-800/40'} text-[10px] space-y-1">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1 font-mono">
                                <span class="text-slate-400 text-[9px]">${l.signal_time}</span>
                                <span class="px-1 py-0.2 rounded font-black text-[8px] ${isBuy ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300'}">
                                    ${actionBadgeText}
                                </span>
                                <span class="font-bold text-white text-[10.5px] truncate max-w-[120px]">${l.instrument}</span>
                            </div>
                            <div>${statusBadge}</div>
                        </div>
                        <div class="text-[9px] text-amber-300/90 font-sans truncate">${l.trigger_reason || l.action}</div>
                        <div class="flex items-center justify-between text-[9px] text-slate-400 font-mono pt-0.5 border-t border-slate-900">
                            <span>Entry: ₹${l.entry_price}</span>
                            <span class="text-rose-400">SL: ₹${l.stop_loss}</span>
                            <span class="text-emerald-400">TGT: ₹${l.target_price}</span>
                            <span class="text-slate-300 font-bold">${l.confluence_score}% Conf</span>
                        </div>
                    </div>
                    `;
                });

                container.innerHTML = html;
            }
        } catch(e) {
            console.error('Failed refreshing signal logs:', e);
        }
    };

    // ── 15. HIGH-FIDELITY MICRO-SIMULATOR (WITH STACK & BLOCK INJECTIONS) ──
    function startSimulator() {
        if (state.simInterval) clearInterval(state.simInterval);

        let basePrice = 24150.0;
        let trendBias = 1;
        let tickCount = 0;

        state.simInterval = setInterval(() => {
            tickCount++;
            basePrice += (Math.random() - 0.48) * 0.4 * trendBias;
            basePrice = Math.max(10, parseFloat(basePrice.toFixed(2)));

            if (Math.random() < 0.05) trendBias *= -1;

            const bids = [];
            const asks = [];

            // Generate Bids
            let runningBidP = basePrice - 0.05;
            for (let i = 0; i < 30; i++) {
                runningBidP = parseFloat((runningBidP - (0.05 + Math.random() * 0.05)).toFixed(2));
                let qty = Math.floor(150 + Math.random() * 800);
                
                // Simulate consecutive stack on levels 3, 4, 5 every few ticks
                if (trendBias > 0 && (i >= 2 && i <= 4)) {
                    qty = 2800 + Math.floor(Math.random() * 800);
                } else if (i === 8) {
                    qty = Math.max(5200, (alertSettings.singleThreshold || 2500) + 250); // Single heavy block
                }

                bids.push({ price: runningBidP, qty: qty, orders: Math.floor(qty / 120) + 1 });
            }

            // Generate Asks
            let runningAskP = basePrice + 0.05;
            for (let i = 0; i < 30; i++) {
                runningAskP = parseFloat((runningAskP + (0.05 + Math.random() * 0.05)).toFixed(2));
                let qty = Math.floor(150 + Math.random() * 800);

                if (trendBias < 0 && (i >= 2 && i <= 4)) {
                    qty = 3100 + Math.floor(Math.random() * 900);
                } else if (i === 7) {
                    qty = Math.max(5200, (alertSettings.singleThreshold || 2500) + 250); // Single heavy block
                }

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
        refreshSignalLogs();
    };

    function updateSpotUI(spot) {
        const spotEl = document.getElementById('val-spot-price');
        if (spotEl) spotEl.textContent = `₹${spot.toFixed(2)}`;
    }

    // ── 16. EVENT LISTENERS & SETUP ──
    document.addEventListener('DOMContentLoaded', async () => {
        loadAlertSettings();
        refreshSignalLogs();

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

        // Auto-sync Unified Monitor strategy signals every 15s
        window.refreshStrategySignals();
        setInterval(window.refreshStrategySignals, 15000);
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
