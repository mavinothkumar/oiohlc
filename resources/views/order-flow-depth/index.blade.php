@extends('layouts.app')

@section('title', '30-Depth Order Flow & High-Probability Strategy Confluence Radar')

@push('styles')
<style>
    body {
        background-color: #0b0f19 !important;
    }

    /* Sleek Custom Dark Scrollbar for Engine Inspector & data panels */
    .custom-scrollbar {
        scrollbar-width: thin;
        scrollbar-color: #334155 #0b0f19;
    }
    .custom-scrollbar::-webkit-scrollbar {
        width: 5px;
        height: 5px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #090d16;
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #334155;
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #475569;
    }

    /* Global Dark Scrollbars for Webkit */
    ::-webkit-scrollbar {
        width: 5px;
        height: 5px;
    }
    ::-webkit-scrollbar-track {
        background: #090d16;
    }
    ::-webkit-scrollbar-thumb {
        background: #334155;
        border-radius: 4px;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: #475569;
    }

    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>
@endpush

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

                {{-- Zen Mode Fullscreen Toggle Button (Seamless in-toolbar control, Esc supported) --}}
                <button id="btn-zen-mode" onclick="toggleGlobalZenMode()" title="Toggle Zen Mode / Distraction-Free (Esc)" class="px-2 py-0.5 rounded-lg bg-slate-950 border border-slate-800 hover:bg-slate-800 text-slate-300 hover:text-white transition text-[11px] flex items-center gap-1 font-bold shadow-sm cursor-pointer zen-mode-toggle-btn">
                    <span id="zen-btn-icon" class="zen-btn-icon">⛶</span>
                    <span id="zen-btn-label" class="zen-btn-label hidden sm:inline">Zen</span>
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
                {{-- DOM Header with Target & Dynamic Legend (Single Strict Line) --}}
                <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-800 text-xs overflow-x-auto overflow-y-hidden whitespace-nowrap gap-1.5 no-scrollbar">
                    <div class="flex items-center gap-1.5 shrink-0">
                        <span class="font-black text-white text-xs sm:text-sm whitespace-nowrap">🌊 30-Level</span>
                        <span id="dom-contract-name" class="text-[10px] font-bold text-amber-300 bg-amber-500/10 px-1.5 py-0.5 rounded border border-amber-500/30 truncate max-w-[130px]" title="Streaming Target Contract">
                            Loading...
                        </span>
                    </div>

                    <div class="flex items-center gap-1 text-[10px] shrink-0">
                        <span id="legend-block-tag" class="px-1.5 py-0.5 rounded bg-amber-500/20 border border-amber-400/50 text-amber-300 font-black text-[9px] whitespace-nowrap" title="Heavy Institutional Block Wall">⚡ Block</span>
                        <span id="legend-stack-tag" class="px-1.5 py-0.5 rounded bg-cyan-500/20 border border-cyan-400/50 text-cyan-300 font-black text-[9px] whitespace-nowrap" title="Consecutive Liquidity Order Stack">🌊 Stack</span>
                        <span id="dom-header-vpoc" class="px-1.5 py-0.5 rounded bg-slate-950 border border-slate-800 text-slate-400 font-mono font-black text-[9.5px] whitespace-nowrap shadow-xs" title="Session Executed Volume Point of Control (Absorption Support / Trap)">
                            🎯 VPOC: --
                        </span>
                        <span id="dom-header-poc" class="px-1.5 py-0.5 rounded bg-slate-950 border border-slate-800 text-slate-400 font-mono font-black text-[9.5px] whitespace-nowrap shadow-xs" title="Order Book Point of Control (Highest Limit Volume)">
                            👑 POC: --
                        </span>
                    </div>
                </div>

                {{-- Prominent Current Trading Price (LTP) Sticky Highlight Bar with Micro-Price Pull Vector & Spread Guard (Single Strict Line) --}}
                <div id="dom-ltp-highlight-banner" class="mb-1.5 py-1 px-2.5 rounded-lg bg-gradient-to-r from-amber-500/15 via-amber-500/25 to-amber-500/15 border border-amber-400/80 flex items-center justify-between overflow-x-auto overflow-y-hidden whitespace-nowrap gap-1.5 text-xs font-black shadow-md shadow-amber-500/5 no-scrollbar">
                    <div class="flex items-center gap-1.5 shrink-0">
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
                    <div class="flex items-center gap-1.5 text-[10px] shrink-0">
                        {{-- Spread Traffic Light Guard --}}
                        <span id="banner-spread-guard" class="px-1.5 py-0.2 rounded text-[9px] font-black bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                            🟢 TIGHT
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

        {{-- ───────────────────── COLUMN 2: TOP RECOMMENDATION & ENGINE INSPECTOR ───────────────────── --}}
        <div class="lg:col-span-4 space-y-2.5">
            
            {{-- 1. Real-Time High Probability Trade Suggestion Card (TOP PRIORITY) --}}
            <div class="bg-gradient-to-br from-slate-900 via-slate-900 to-slate-950 border border-slate-800 rounded-xl p-3 shadow-xl relative overflow-hidden">
                <div class="flex items-center justify-between mb-1.5">
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

                {{-- Master Anchor Process Indicator (NIFTY 50 Stock Anchor & API Cap) --}}
                <div id="master-anchor-banner" class="mb-2 px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800/90 text-[10px] flex items-center justify-between flex-wrap gap-1">
                    <div class="flex items-center gap-1.5 font-mono">
                        <span class="text-amber-400">⚓</span>
                        <span class="text-slate-400 font-bold uppercase text-[9px]">Master Anchor:</span>
                        <span id="anchor-candle-pill" class="font-bold text-amber-300">NIFTY 50 Stocks (Awaiting Trigger)</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span id="anchor-status-pill" class="px-1.5 py-0.2 rounded text-[8.5px] font-bold bg-slate-800 text-slate-400">BIAS: NEUTRAL</span>
                        <span id="api-trade-cap-pill" class="hidden px-1.5 py-0.2 rounded text-[8.5px] font-bold font-mono bg-amber-500/20 text-amber-300 border border-amber-500/30">API: 0/3</span>
                    </div>
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

                {{-- Rationale Snippet (Single line fixed height to prevent layout jerking) --}}
                <div class="mt-2 bg-slate-950/70 px-2.5 rounded-lg border border-slate-800/80 text-[10.5px] text-slate-300 flex items-center gap-1.5 overflow-hidden h-7">
                    <span class="text-amber-400 text-xs shrink-0">💡</span>
                    <p id="trade-rationale" class="truncate whitespace-nowrap text-slate-300 font-medium flex-1">
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

            {{-- 2. Interactive Mini-Tabs: Engine Inspector (Journal Default, Alert, Math, Vacuum, JSON) --}}
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 shadow-xl space-y-2.5">
                <div class="flex items-center justify-between border-b border-slate-800 pb-2 flex-wrap gap-1.5">
                    <span class="text-xs sm:text-sm font-black uppercase text-white tracking-wider flex items-center gap-1.5">
                        <span>⚡ Engine Inspector</span>
                    </span>
                    
                    {{-- Mini Tab Buttons (Journal as 1st Default, Alerts 2nd, Math 3rd, Vacuum 4th, JSON 5th) --}}
                    <div class="flex items-center gap-1 text-xs flex-wrap">
                        <button onclick="switchMiniTab('mini-journal')" id="btn-mini-journal" class="mini-tab-btn px-2.5 py-1 rounded-lg bg-indigo-600 text-white font-bold transition flex items-center gap-1 cursor-pointer">
                            <span>Journal 📖</span>
                        </button>
                        <button onclick="switchMiniTab('mini-alerts')" id="btn-mini-alerts" class="mini-tab-btn px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 font-bold hover:text-white transition flex items-center gap-1 cursor-pointer">
                            <span>Alerts ⚡</span>
                            <span id="badge-alert-count" class="px-1.5 py-0.2 rounded bg-amber-500/20 text-amber-300 text-[10px] font-mono">0</span>
                        </button>
                        <button onclick="switchMiniTab('mini-trace')" id="btn-mini-trace" class="mini-tab-btn px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 font-bold hover:text-white transition cursor-pointer">
                            <span>Math</span>
                        </button>
                        <button onclick="switchMiniTab('mini-vacuum')" id="btn-mini-vacuum" class="mini-tab-btn px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 font-bold hover:text-white transition cursor-pointer">
                            <span>Vacuum</span>
                        </button>
                        <button onclick="switchMiniTab('mini-json')" id="btn-mini-json" class="mini-tab-btn px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 font-bold hover:text-white transition cursor-pointer">
                            <span>JSON</span>
                        </button>
                    </div>
                </div>

                {{-- Mini Tab Pane 1: Today's Opportunity Journal & Outcome Audit (DEFAULT ACTIVE, 2X HEIGHT) --}}
                <div id="pane-mini-journal" class="mini-tab-pane space-y-2.5 text-xs">
                    {{-- Performance Scorecard Summary --}}
                    <div class="grid grid-cols-4 gap-2 text-center bg-slate-950 p-2.5 rounded-xl border border-slate-800 shadow-inner">
                        <div onclick="setJournalFilter('ALL')" class="cursor-pointer hover:bg-slate-900/80 p-1 rounded-lg transition" title="Show All Setups">
                            <span class="text-[10px] uppercase text-slate-400 font-bold block tracking-wider">Calls</span>
                            <span id="journal-total-count" class="font-black text-white text-sm sm:text-base">0</span>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase text-slate-400 font-bold block tracking-wider">Win Rate</span>
                            <span id="journal-win-rate" class="font-black text-emerald-400 text-sm sm:text-base">0%</span>
                        </div>
                        <div class="p-1 rounded-lg bg-slate-900/40 border border-slate-800/80">
                            <span class="text-[10px] uppercase text-slate-400 font-bold block tracking-wider">Hit / Stop</span>
                            <div class="font-black text-sm sm:text-base font-mono flex items-center justify-center gap-1">
                                <button type="button" id="card-filter-hit" onclick="setJournalFilter('TARGET_HIT')" class="text-emerald-400 hover:scale-110 transition px-1.5 py-0.2 rounded hover:bg-emerald-500/20 cursor-pointer" title="Click to filter Target Hits">
                                    <span id="journal-target-count">0</span>
                                </button>
                                <span class="text-slate-500">/</span>
                                <button type="button" id="card-filter-stop" onclick="setJournalFilter('STOPPED_OUT')" class="text-rose-400 hover:scale-110 transition px-1.5 py-0.2 rounded hover:bg-rose-500/20 cursor-pointer" title="Click to filter Stopped Out">
                                    <span id="journal-stopped-count">0</span>
                                </button>
                            </div>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase text-slate-400 font-bold block tracking-wider">Net Pts</span>
                            <span id="journal-net-pnl" class="font-black text-amber-300 text-sm sm:text-base font-mono">+0.0</span>
                        </div>
                    </div>

                    {{-- Filter Pill Bar --}}
                    <div class="flex items-center justify-between text-xs text-slate-300 border-b border-slate-800/80 pb-1.5 px-0.5 flex-wrap gap-1">
                        <div class="flex items-center gap-1 flex-wrap">
                            <span class="font-bold text-slate-200 text-xs mr-1">Filter:</span>
                            <button type="button" id="btn-jfilter-all" onclick="setJournalFilter('ALL')" class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-600 text-white transition cursor-pointer">
                                All (<span id="count-jfilter-all">0</span>)
                            </button>
                            <button type="button" id="btn-jfilter-hit" onclick="setJournalFilter('TARGET_HIT')" class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-900 text-slate-400 hover:text-emerald-300 transition cursor-pointer">
                                🎯 Hits (<span id="count-jfilter-hit">0</span>)
                            </button>
                            <button type="button" id="btn-jfilter-stop" onclick="setJournalFilter('STOPPED_OUT')" class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-900 text-slate-400 hover:text-rose-300 transition cursor-pointer">
                                🛑 Stops (<span id="count-jfilter-stop">0</span>)
                            </button>
                            <button type="button" id="btn-jfilter-active" onclick="setJournalFilter('ACTIVE')" class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-900 text-slate-400 hover:text-amber-300 transition cursor-pointer">
                                ⏳ Active (<span id="count-jfilter-active">0</span>)
                            </button>
                        </div>
                        <button type="button" onclick="refreshSignalLogs()" class="text-indigo-400 hover:text-indigo-300 font-bold flex items-center gap-1 cursor-pointer text-xs transition">
                            <span>🔄</span>
                            <span>Refresh</span>
                        </button>
                    </div>

                    {{-- Scrollable Signals List (Doubled Height for Full Multi-Engine Card Viewing with Dark Scrollbar) --}}
                    <div id="journal-logs-container" class="space-y-2 max-h-[28rem] overflow-y-auto pr-1.5 font-sans custom-scrollbar">
                        <div class="text-center py-10 text-slate-500 text-xs">
                            No trade signals logged yet today. Waiting for setup triggers...
                        </div>
                    </div>
                </div>

                {{-- Mini Tab Pane 2: Live Session Alert Feed & Trigger Reason (2X HEIGHT) --}}
                <div id="pane-mini-alerts" class="mini-tab-pane hidden space-y-2 text-xs">
                    <div class="flex items-center justify-between text-xs pb-1.5 border-b border-slate-800 px-0.5">
                        <div class="flex items-center gap-2">
                            <span class="text-slate-300 font-bold uppercase tracking-wider text-xs">Live Alarm Trigger Feed:</span>
                            <span id="market-status-alert-pill" class="hidden text-[9.5px] px-1.5 py-0.5 rounded font-mono font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30">⏸️ Market Closed · Alarms Paused</span>
                        </div>
                        <button type="button" onclick="clearLiveAlertHistory()" class="text-rose-400 hover:text-rose-300 text-[10.5px] font-bold cursor-pointer transition">
                            Clear Feed
                        </button>
                    </div>
                    <div id="live-alerts-history-container" class="space-y-1.5 overflow-y-auto max-h-[28rem] pr-1.5 custom-scrollbar">
                        <div class="text-center py-10 text-slate-500 text-xs">
                            No alarms triggered yet this session. When audio sounds, exact trigger reasons appear here in real-time.
                        </div>
                    </div>
                </div>

                {{-- Mini Tab Pane 3: Math Trace --}}
                <div id="pane-mini-trace" class="mini-tab-pane hidden space-y-2 font-mono text-xs">
                    <div class="flex justify-between p-2 rounded-lg bg-slate-950 border border-slate-800">
                        <span class="text-slate-400">Numerator (B - A):</span>
                        <span id="trace-obi-numerator" class="text-white font-bold text-xs sm:text-sm">0</span>
                    </div>
                    <div class="flex justify-between p-2 rounded-lg bg-slate-950 border border-slate-800">
                        <span class="text-slate-400">Denominator (B + A):</span>
                        <span id="trace-obi-denominator" class="text-white font-bold text-xs sm:text-sm">0</span>
                    </div>
                    <div class="flex justify-between p-2 rounded-lg bg-slate-950 border border-slate-800">
                        <span class="text-slate-400">Raw Ratio:</span>
                        <span id="trace-obi-raw" class="text-emerald-400 font-bold text-xs sm:text-sm">0.000</span>
                    </div>
                    <div class="flex justify-between p-2 rounded-lg bg-slate-950 border border-emerald-500/40">
                        <span class="text-emerald-300 font-bold">30-Depth OBI:</span>
                        <span id="trace-obi-final" class="text-emerald-300 font-black text-xs sm:text-sm">0.0%</span>
                    </div>
                </div>

                {{-- Mini Tab Pane 4: Vacuum & Air Pockets --}}
                <div id="pane-mini-vacuum" class="mini-tab-pane hidden space-y-2 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400 text-xs">Air Pocket Status:</span>
                        <span id="vacuum-status-badge" class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-950 text-slate-300 border border-slate-800">NORMAL BOOK</span>
                    </div>
                    <div id="vacuum-alert-box" class="p-3 rounded-lg bg-slate-950 border border-slate-800 text-xs text-slate-300">
                        No continuous air pocket detected. 30-depth order flow is continuous.
                    </div>
                </div>

                {{-- Mini Tab Pane 5: Raw Strategy JSON (Enlarged) --}}
                <div id="pane-mini-json" class="mini-tab-pane hidden space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-300 font-bold">Live Strategy Signals:</span>
                        <button onclick="refreshStrategySignals()" class="text-indigo-400 hover:text-indigo-300 font-bold cursor-pointer text-xs">
                            🔄 Refresh
                        </button>
                    </div>
                    <pre id="raw-json-inspector" class="bg-slate-950 p-3 rounded-lg border border-slate-800 text-[10.5px] text-emerald-400 font-mono overflow-x-auto max-h-80 custom-scrollbar">{{ json_encode($strategySignals, JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>

        </div>

        {{-- ───────────────────── COLUMN 3: NOTIFICATIONS, MICRO-PRICE, CONFLUENCE, OBI & LIQUIDITY WALLS ───────────────────── --}}
        <div class="lg:col-span-3 space-y-2.5">
            
            {{-- 0. Microstructure & Directional Liquidity Alert Banner (Non-Jumping Fixed Placeholder) --}}
            <div id="liquidity-alert-banner" class="bg-slate-900 border border-slate-800 rounded-xl p-2.5 shadow-xl transition-all duration-300">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="relative flex h-2 w-2">
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        <div>
                            <span class="text-[10px] font-black uppercase text-slate-300 tracking-wider flex items-center gap-1">
                                <span>🛡️ Microstructure Radar</span>
                                <span class="text-[8px] bg-emerald-950 text-emerald-400 border border-emerald-800/60 px-1 py-0.2 rounded font-mono">NORMAL</span>
                            </span>
                            <p class="text-[9px] text-slate-400 font-sans mt-0.5 leading-tight">
                                No active block or stack alerts. Order flow stable.
                            </p>
                        </div>
                    </div>
                    <span class="text-[8px] text-emerald-400 font-mono font-bold bg-slate-950 px-1.5 py-0.5 rounded border border-slate-800">
                        MONITORING
                    </span>
                </div>
            </div>

            {{-- 1. Depth-Weighted Micro-Price & Lead Drift --}}
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 shadow-xl space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-black uppercase text-white tracking-wider">Micro-Price &amp; Lead Drift</span>
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

            {{-- 2. Confluence Alignment Scorecard --}}
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

            {{-- 4. Active Liquidity Walls (≥2.2x) & Absorption / Spoofing Tracker --}}
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

        </div>

    </div>

    {{-- ════════════════════════ 3. MODAL: LIQUIDITY & STRATEGY ALERT RULES (TABBED ENTERPRISE) ════════════════════════ --}}
    <div id="modal-alert-settings" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-xs p-3">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-xl w-full max-h-[92vh] flex flex-col shadow-2xl overflow-hidden">
            
            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-slate-800 px-4 py-3 bg-slate-950/90">
                <div class="flex items-center gap-2">
                    <span class="text-xl">⚙️</span>
                    <div>
                        <h3 class="text-sm sm:text-base font-black text-white">Order Flow Rules &amp; Auto-Execution</h3>
                        <p class="text-[10px] text-slate-400">Configure microstructure detection, acoustic alarms, and automated 0Click trade execution</p>
                    </div>
                </div>
                <button onclick="toggleSettingsModal(false)" class="text-slate-400 hover:text-white p-1 rounded-lg bg-slate-900 hover:bg-slate-800 text-sm">
                    ✕
                </button>
            </div>

            {{-- Enterprise Tab Selector Bar --}}
            <div class="flex border-b border-slate-800 bg-slate-950 px-4 pt-2 gap-2 text-xs font-bold">
                <button type="button" id="tab-btn-alerts" onclick="switchAlertModalTab('alerts')" class="px-3.5 py-2 rounded-t-lg border-b-2 border-indigo-500 text-white bg-slate-900 flex items-center gap-1.5 transition cursor-pointer">
                    <span>🔔</span>
                    <span>Alert Rules &amp; Sound</span>
                </button>
                <button type="button" id="tab-btn-api" onclick="switchAlertModalTab('api')" class="px-3.5 py-2 rounded-t-lg border-b-2 border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-900/60 flex items-center gap-1.5 transition cursor-pointer">
                    <span>⚡</span>
                    <span>0Click Trade API &amp; Webhook</span>
                    <span id="tab-api-badge" class="ml-1 px-1.5 py-0.2 rounded text-[8px] font-mono bg-purple-500/20 text-purple-300 border border-purple-500/40">SIMULATION</span>
                </button>
            </div>

            {{-- ════════ TAB 1 PANE: ALERT RULES & ACOUSTIC CHIMES ════════ --}}
            <div id="tab-pane-alerts" class="space-y-3 p-4 text-xs overflow-y-auto flex-1 custom-scrollbar max-h-[68vh]">
                
                {{-- Category 1: Confluence Alignment Scorecard & Strategy Alerts --}}
                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <label class="font-bold text-white uppercase text-[11px] flex items-center gap-1.5">
                            <span>🎯 Confluence Alignment Scorecard Alerts</span>
                        </label>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="cfg-enable-confluence" class="sr-only peer" checked>
                            <div class="w-8 h-4 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-indigo-500"></div>
                        </label>
                    </div>
                    <p class="text-[10px] text-slate-400 leading-relaxed">
                        Evaluates multi-engine confluence (Breadth, Quant Engine, OI Buildup, and 30-Depth) and triggers high-probability trade setups (≥68% Bull / ≤35% Bear).
                    </p>
                    
                    {{-- Sub-toggle: Absorption Wall Warnings --}}
                    <div class="pt-2 border-t border-slate-900 flex items-center justify-between">
                        <div>
                            <span class="text-[10.5px] font-bold text-slate-300 block">🛡️ Absorption Wall Divergence Warnings</span>
                            <span class="text-[9.5px] text-slate-500">Warns when resting walls in top 3 DOM levels absorb against macro trend</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="cfg-enable-absorption" class="sr-only peer" checked>
                            <div class="w-8 h-4 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-amber-500"></div>
                        </label>
                    </div>
                </div>

                {{-- Category 1B: 🎯 4-Pillar High-Probability Strategy Customization & Strict Confluence --}}
                <div class="bg-slate-950 p-3 rounded-xl border border-indigo-900/60 space-y-2.5">
                    <div class="flex items-center justify-between border-b border-slate-900 pb-2">
                        <div class="flex items-center gap-1.5">
                            <span class="font-bold text-white uppercase text-[11px] flex items-center gap-1">
                                <span>🎯 4-Pillar Custom Fine-Tuning</span>
                                <span class="text-[9px] bg-indigo-950 text-indigo-300 border border-indigo-700/60 px-1.5 py-0.2 rounded font-mono">High Win Rate</span>
                            </span>
                        </div>
                        <label class="flex items-center gap-1.5 cursor-pointer text-[10.5px] font-black text-amber-300">
                            <input type="checkbox" id="cfg-strict-confluence" class="accent-indigo-500 w-3.5 h-3.5 cursor-pointer">
                            <span>Strict 4/4 Full Confluence</span>
                        </label>
                    </div>

                    <p class="text-[10px] text-slate-400 leading-tight">
                        Customize exact quantitative parameters across all 4 Pillars to focus solely on high-conviction trades and suppress over-trading.
                    </p>

                    {{-- 1-Click Strategy Presets --}}
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-[9px] text-slate-500 font-bold uppercase">Presets:</span>
                        <button type="button" onclick="setPillarPreset('sniper')" class="px-2 py-0.5 rounded bg-indigo-950 hover:bg-indigo-900 text-indigo-300 text-[10px] font-bold border border-indigo-700/60 transition cursor-pointer">
                            🎯 Ultra Sniper (80% POP / 4/4)
                        </button>
                        <button type="button" onclick="setPillarPreset('standard')" class="px-2 py-0.5 rounded bg-slate-900 hover:bg-slate-800 text-emerald-300 text-[10px] font-bold border border-slate-800 transition cursor-pointer">
                            ⚡ High Probability (Default)
                        </button>
                        <button type="button" onclick="setPillarPreset('scalper')" class="px-2 py-0.5 rounded bg-slate-900 hover:bg-slate-800 text-amber-300 text-[10px] font-bold border border-slate-800 transition cursor-pointer">
                            🌊 Active Scalper (Dynamic)
                        </button>
                    </div>

                    {{-- 4 Pillars Thresholds Grid --}}
                    <div class="grid grid-cols-2 gap-2 pt-1 border-t border-slate-900 text-[10px]">
                        {{-- Pillar 1: Market Breadth --}}
                        <div class="p-2 rounded-lg bg-slate-900/90 border border-slate-800 space-y-1">
                            <span class="font-bold text-slate-200 block text-[10.5px] truncate">🏢 Pillar 1: Nifty 50 Breadth</span>
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-slate-400 text-[9.5px]">Min Weight %:</span>
                                <input type="number" id="cfg-pillar-breadth-weight" min="50" max="95" step="1" value="60" class="w-16 bg-slate-950 border border-slate-700 rounded px-1.5 py-0.5 text-right font-mono font-bold text-white text-[10.5px]">
                            </div>
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-slate-400 text-[9.5px]">Min Net Drag/Push:</span>
                                <input type="number" id="cfg-pillar-breadth-pts" min="0.5" max="30" step="0.5" value="2.5" class="w-16 bg-slate-950 border border-slate-700 rounded px-1.5 py-0.5 text-right font-mono font-bold text-white text-[10.5px]">
                            </div>
                        </div>

                        {{-- Pillar 2: OI Sentiment & PCR --}}
                        <div class="p-2 rounded-lg bg-slate-900/90 border border-slate-800 space-y-1">
                            <span class="font-bold text-slate-200 block text-[10.5px] truncate">📊 Pillar 2: Trending OI &amp; PCR</span>
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-slate-400 text-[9.5px]">Bull Min PCR:</span>
                                <input type="number" id="cfg-pillar-oi-bull-pcr" min="0.5" max="2.5" step="0.05" value="1.05" class="w-16 bg-slate-950 border border-slate-700 rounded px-1.5 py-0.5 text-right font-mono font-bold text-emerald-400 text-[10.5px]">
                            </div>
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-slate-400 text-[9.5px]">Bear Max PCR:</span>
                                <input type="number" id="cfg-pillar-oi-bear-pcr" min="0.5" max="2.5" step="0.05" value="0.85" class="w-16 bg-slate-950 border border-slate-700 rounded px-1.5 py-0.5 text-right font-mono font-bold text-rose-400 text-[10.5px]">
                            </div>
                        </div>

                        {{-- Pillar 3: Quant POP Engine --}}
                        <div class="p-2 rounded-lg bg-slate-900/90 border border-slate-800 space-y-1">
                            <span class="font-bold text-slate-200 block text-[10.5px] truncate">📈 Pillar 3: Quant POP Engine</span>
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-slate-400 text-[9.5px]">Min POP (%):</span>
                                <input type="number" id="cfg-pillar-quant-pop" min="50" max="95" step="1" value="70" class="w-16 bg-slate-950 border border-slate-700 rounded px-1.5 py-0.5 text-right font-mono font-bold text-cyan-300 text-[10.5px]">
                            </div>
                            <span class="text-[9px] text-slate-500 block leading-tight">Statistical probability of profit threshold</span>
                        </div>

                        {{-- Pillar 4: 30-Level Deep Order Book --}}
                        <div class="p-2 rounded-lg bg-slate-900/90 border border-slate-800 space-y-1">
                            <span class="font-bold text-slate-200 block text-[10.5px] truncate">🌊 Pillar 4: 30-Depth Order Book</span>
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-slate-400 text-[9.5px]">Min OBI (%):</span>
                                <input type="number" id="cfg-pillar-obi-pct" min="5" max="80" step="1" value="20" class="w-16 bg-slate-950 border border-slate-700 rounded px-1.5 py-0.5 text-right font-mono font-bold text-amber-300 text-[10.5px]">
                            </div>
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-slate-400 text-[9.5px]">Min Micro Drift:</span>
                                <input type="number" id="cfg-pillar-micro-drift" min="0.05" max="5.0" step="0.05" value="0.15" class="w-16 bg-slate-950 border border-slate-700 rounded px-1.5 py-0.5 text-right font-mono font-bold text-cyan-300 text-[10.5px]">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Category 2: Single Level Heavy Block Alert --}}
                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="font-bold text-white uppercase text-[11px] flex items-center gap-1.5">
                            <span>⚡ Single Level Heavy Block</span>
                            <span class="text-[10px] text-amber-400 font-normal">(Qty ≥ X)</span>
                        </label>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="cfg-enable-block" class="sr-only peer" checked>
                            <div class="w-8 h-4 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-amber-500"></div>
                        </label>
                    </div>
                    <p class="text-[10px] text-slate-400">
                        Detects and highlights resting institutional blocks at any individual price level in the 30-depth ladder.
                    </p>
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

                {{-- Category 3: Continuous / Consecutive Stack Alert (Directional Rush) --}}
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

                {{-- Category 4: Audio Alarm Toggles & Selectable Sound Profiles --}}
                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-bold text-white text-[11px] block">🔊 Master Sound Alarms</span>
                            <span class="text-[10px] text-slate-400">Play directional acoustic chimes upon alerts</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="cfg-sound-master" class="sr-only peer" checked>
                            <div class="w-8 h-4 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-amber-500"></div>
                        </label>
                    </div>

                    {{-- Individual Audio Toggles by Category --}}
                    <div class="pt-2 border-t border-slate-900 grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 p-1.5 rounded-lg bg-slate-900/80 border border-slate-800 cursor-pointer">
                            <input type="checkbox" id="cfg-sound-stack" class="rounded bg-slate-800 border-slate-700 text-amber-500 focus:ring-0 cursor-pointer" checked>
                            <span class="text-[10px] font-bold text-slate-300">Blocks &amp; Stacks</span>
                        </label>
                        <label class="flex items-center gap-2 p-1.5 rounded-lg bg-slate-900/80 border border-slate-800 cursor-pointer">
                            <input type="checkbox" id="cfg-sound-strategy" class="rounded bg-slate-800 border-slate-700 text-indigo-500 focus:ring-0 cursor-pointer" checked>
                            <span class="text-[10px] font-bold text-slate-300">Confluence Strategy</span>
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
                                <button type="button" onclick="testCurrentSound('BUY')" title="Test Bullish tone" class="w-full py-2 px-3 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs flex items-center justify-center gap-1.5 transition shadow-md shadow-emerald-600/20 active:scale-95 cursor-pointer">
                                    <span>▶️ Test Bull Tone</span>
                                </button>
                                <button type="button" onclick="testCurrentSound('SELL')" title="Test Bearish tone" class="w-full py-2 px-3 rounded-lg bg-rose-600 hover:bg-rose-500 text-white font-black text-xs flex items-center justify-center gap-1.5 transition shadow-md shadow-rose-600/20 active:scale-95 cursor-pointer">
                                    <span>▶️ Test Bear Tone</span>
                                </button>
                            </div>
                        </div>
                        <p class="text-[9px] text-slate-500 leading-tight">
                            Sounds automatically modulate higher in pitch for Bullish signals and lower in pitch for Bearish signals.
                        </p>
                    </div>
                </div>

                {{-- Category 5: Auto-Log Opportunities to Journal --}}
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
                        Whenever an entry setup triggers (Confluence or Liquidity Stacks), saves entry, SL, target, and tracks live outcome for performance auditing.
                    </p>
                </div>

            </div>

            {{-- ════════ TAB 2 PANE: 0CLICK.IN TRADE WEBHOOK & API EXECUTION ════════ --}}
            <div id="tab-pane-api" class="space-y-3 p-4 text-xs overflow-y-auto flex-1 custom-scrollbar max-h-[68vh] hidden">
                
                {{-- Category 6: 0Click.in Trade Webhook & API Dispatch (Contract v1.0) --}}
                <div class="bg-slate-950 p-3 rounded-xl border border-sky-900/40 space-y-2.5">
                    <div class="flex items-center justify-between border-b border-slate-900 pb-2">
                        <div class="flex items-center gap-1.5">
                            <span class="font-bold text-white text-[11px]">⚡ 0Click.in Trade Webhook &amp; Logs</span>
                            <span class="text-[9px] bg-sky-950 text-sky-300 border border-sky-700/60 px-1.5 py-0.2 rounded font-mono">Contract v1.0</span>
                        </div>
                        <label class="flex items-center gap-1.5 cursor-pointer text-[10px] font-bold text-slate-300">
                            <input type="checkbox" id="zc-auto-log-toggle" checked class="accent-sky-500 w-3 h-3 cursor-pointer">
                            <span>Auto-Log Internally</span>
                        </label>
                    </div>

                    {{-- Dispatch Toggle Row --}}
                    <div class="flex items-center justify-between p-2 rounded-lg bg-slate-900/90 border border-slate-800">
                        <div>
                            <div class="text-[11px] font-bold text-white flex items-center gap-1.5">
                                <span>Dispatch to 0click.in Webhook</span>
                                <span id="zc-mode-badge" class="px-1.5 py-0.2 rounded text-[8.5px] font-mono bg-purple-500/20 text-purple-300 border border-purple-500/40">SIMULATION</span>
                            </div>
                            <div class="text-[9.5px] text-slate-400">Off = Save logs internally only; On = Auto-dispatch to 0click.in API</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="zc-forward-toggle" class="sr-only peer">
                            <div class="w-8 h-4 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-sky-500"></div>
                        </label>
                    </div>

                    {{-- Trade Strategy Style Selection (Option Selling vs Option Buying) --}}
                    <div class="p-2.5 rounded-xl bg-slate-900/90 border border-indigo-900/40 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-[11px] font-bold text-white flex items-center gap-1">
                                <span>👑 Preferred Execution Strategy:</span>
                            </label>
                            <span class="text-[9px] text-amber-300 font-bold font-mono">Statistical Edge</span>
                        </div>
                        <select id="zc-trade-style" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-amber-300 font-bold focus:outline-none focus:border-amber-400 cursor-pointer">
                            <option value="OPTION_SELLING" selected>👑 Option Selling (Writing - Theta Decay Advantage) [Recommended]</option>
                            <option value="OPTION_BUYING">🚀 Option Buying (Directional Momentum Breakout)</option>
                        </select>
                        <p class="text-[9.5px] text-slate-400 leading-tight">
                            <span class="text-emerald-400 font-bold">Option Selling:</span> Bull trend sells ITM PE; Bear trend sells ITM CE to profit from time decay and high win-rate POP.<br>
                            <span class="text-sky-400 font-bold">Option Buying:</span> Buys ATM CE/PE for fast scalp momentum.
                        </p>
                    </div>

                    {{-- Webhook Connection Details --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[10px]">
                        <div>
                            <label class="text-slate-400 font-bold block mb-0.5">0Click Webhook URL</label>
                            <input type="text" id="zc-webhook-url" value="https://0click.in/api/webhook/tradingview" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 text-xs text-white font-mono focus:outline-none focus:border-sky-400">
                        </div>
                        <div>
                            <label class="text-amber-300 font-bold block mb-0.5">🔑 Security Token ("X-Webhook-Token")</label>
                            <div class="relative flex items-center">
                                <input type="password" id="zc-webhook-token" placeholder="Paste your 0click token (tv_sec_...)" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 pr-8 text-xs text-amber-200 font-mono focus:outline-none focus:border-amber-400">
                                <button type="button" id="btn-toggle-zc-token" onclick="toggleZcTokenVisibility()" title="Show / Hide Token" class="absolute right-2 text-slate-400 hover:text-white text-xs cursor-pointer">👁️</button>
                            </div>
                        </div>
                    </div>

                    {{-- Order Sizing & SL / Target Parameters --}}
                    <div class="space-y-2 pt-2 border-t border-slate-900">
                        <div class="flex items-center justify-between text-[10.5px] font-bold text-white">
                            <span class="flex items-center gap-1.5">
                                <span>🎯 Stop Loss (SL) &amp; Profit Target (TP) Restructure</span>
                            </span>
                            <span class="text-[9.5px] text-emerald-400 font-mono">Candle Extreme ± Buffer</span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-[10px]">
                            <div>
                                <label class="text-slate-400 block mb-0.5">Execution Mode</label>
                                <select id="zc-mode-select" onchange="updateZcModeBadge()" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-1.5 py-1 text-xs text-white font-bold focus:outline-none focus:border-sky-400 cursor-pointer">
                                    <option value="SIMULATION" selected>SIMULATION</option>
                                    <option value="LIVE">LIVE</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-slate-400 block mb-0.5">Lots</label>
                                <input type="number" id="zc-lots-input" value="1" min="1" max="100" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 text-xs text-white font-mono focus:outline-none">
                            </div>

                            <div>
                                <label class="text-slate-400 block mb-0.5">SL Calculation</label>
                                <select id="zc-sl-mode-select" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-1.5 py-1 text-xs text-white focus:outline-none cursor-pointer">
                                    <option value="candle_hl" selected>Candle High/Low</option>
                                    <option value="wall">Order Book Wall</option>
                                    <option value="fixed_pts">Fixed Points</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-rose-400 font-bold block mb-0.5">SL Buffer (Pts)</label>
                                <input type="number" id="zc-sl-pts-input" value="15" min="0" max="100" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 text-xs text-rose-300 font-mono focus:outline-none">
                            </div>

                            <div>
                                <label class="text-emerald-400 font-bold block mb-0.5">Target TP1 (Pts)</label>
                                <input type="number" id="zc-tp-pts-input" value="100" min="10" max="500" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 text-xs text-emerald-300 font-mono focus:outline-none">
                            </div>
                        </div>

                        {{-- Secondary Runner Target --}}
                        <div class="flex items-center justify-between p-2 rounded-lg bg-slate-900/60 border border-slate-800">
                            <div>
                                <div class="text-[10.5px] font-bold text-slate-300 flex items-center gap-1">
                                    <span>🚀 Secondary Runner Target (TP2):</span>
                                </div>
                                <div class="text-[9px] text-slate-500">Optional extended target for trend runners (default: 150 pts)</div>
                            </div>
                            <div class="flex items-center gap-1">
                                <input type="number" id="zc-tp2-pts-input" value="150" min="20" max="600" class="w-20 bg-slate-900 border border-slate-700 rounded px-2 py-0.5 text-xs text-cyan-300 font-mono focus:outline-none">
                                <span class="text-[10px] text-slate-400 font-bold">pts</span>
                            </div>
                        </div>

                        {{-- Daily Trade Frequency Restriction & Guard --}}
                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-900/90 border border-amber-500/30">
                            <div>
                                <div class="text-[11px] font-bold text-amber-300 flex items-center gap-1.5">
                                    <span>🛑 Max API Executed Trades Per Day:</span>
                                    <span id="zc-daily-trades-badge" class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40">0 / 3 Executed Today</span>
                                </div>
                                <div class="text-[9.5px] text-slate-400 leading-tight mt-0.5">
                                    Confluence signals always scan and display on screen. Once this daily cap is reached, auto-dispatch to Broker/0Click is safely locked.
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0 ml-2">
                                <input type="number" id="zc-max-daily-trades" value="3" min="1" max="50" class="w-16 bg-slate-950 border border-amber-500/50 rounded-lg px-2 py-1 text-xs text-amber-300 font-mono font-black focus:outline-none focus:border-amber-400 text-center">
                                <span class="text-[10px] text-slate-400 font-bold">trades</span>
                            </div>
                        </div>

                        {{-- Test Dispatch Buttons & Live Status Box --}}
                        <div class="pt-1.5 space-y-1.5">
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="testZeroClickWebhook('BUY')" title="Send a simulated test Bullish Call to 0click.in" class="flex-1 py-1.5 px-2.5 rounded-lg bg-emerald-950 hover:bg-emerald-900 text-emerald-300 border border-emerald-700/60 text-[10.5px] font-bold transition flex items-center justify-center gap-1 cursor-pointer">
                                    <span>Preview Bull Call</span>
                                </button>
                                <button type="button" onclick="testZeroClickWebhook('SELL')" title="Send a simulated test Bearish Call to 0click.in" class="flex-1 py-1.5 px-2.5 rounded-lg bg-rose-950 hover:bg-rose-900 text-rose-300 border border-rose-700/60 text-[10.5px] font-bold transition flex items-center justify-center gap-1 cursor-pointer">
                                    <span>Preview Bear Call</span>
                                </button>
                            </div>
                            <div id="zc-test-status-box" class="hidden p-2 rounded-lg bg-slate-900 border text-[9.5px] font-mono whitespace-pre-wrap"></div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between px-4 py-3 border-t border-slate-800 bg-slate-950/90">
                <button type="button" onclick="resetAlertDefaults()" class="text-xs text-slate-400 hover:text-white font-semibold cursor-pointer">
                    Reset Defaults
                </button>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="toggleSettingsModal(false)" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-750 text-slate-300 text-xs font-bold transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="button" onclick="saveAlertSettings()" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black shadow-md transition cursor-pointer">
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

    // ── 1. ALERT & WEBHOOK SETTINGS STATE & STORAGE ──
    const alertSettings = {
        enableBlock: true,           // Single level heavy block alert toggle
        singleThreshold: 2500,       // Single level Qty >= X
        enableStack: true,           // Consecutive stack detection
        stackMinConsecutive: 3,      // N continuous levels
        stackMinQty: 1500,           // Min qty on each level
        enableConfluence: true,      // Confluence Alignment Scorecard alerts
        enableAbsorption: true,      // Absorption wall warnings
        soundEnabled: true,          // Master sound toggle
        soundStack: true,            // Sound for blocks & stacks
        soundStrategy: true,         // Sound for confluence strategy setups
        soundProfile: 'crystal',     // 'crystal', 'radar', 'laser', 'marimba', 'tactical'
        autoLog: true,               // Auto-log opportunities to Journal

        // 🎯 4-Pillar High-Probability Strategy Customization
        strictConfluence: false,     // Require 4/4 simultaneous confluence
        pillarBreadthWeight: 60,     // Pillar 1: Min Weight % (Default: 60%)
        pillarBreadthPts: 2.5,       // Pillar 1: Min Net Pts (Default: 2.5 pts)
        pillarOiBullPcr: 1.05,       // Pillar 2: Bull Min PCR (Default: 1.05)
        pillarOiBearPcr: 0.85,       // Pillar 2: Bear Max PCR (Default: 0.85)
        pillarQuantPop: 70,          // Pillar 3: Min Quant POP % (Default: 70%)
        pillarObiPct: 20,            // Pillar 4: Min 30D OBI % (Default: 20%)
        pillarMicroDrift: 0.15       // Pillar 4: Min Lead Drift (Default: 0.15 pts)
    };

    const zeroClickSettings = {
        autoLog: true,
        forwardToZeroClick: false,
        webhookUrl: 'https://0click.in/api/webhook/tradingview',
        webhookToken: '',
        mode: 'SIMULATION',
        lots: 1,
        tradeStyle: 'OPTION_SELLING',
        slMode: 'candle_hl',
        slBufferPts: 15,
        targetTp1Pts: 100,
        targetTp2Pts: 150,
        maxDailyTrades: 3
    };

    window.toggleZcTokenVisibility = function() {
        const tokenInput = document.getElementById('zc-webhook-token');
        if (tokenInput) {
            tokenInput.type = tokenInput.type === 'password' ? 'text' : 'password';
        }
    };

    window.switchAlertModalTab = function(tabName) {
        const paneAlerts = document.getElementById('tab-pane-alerts');
        const paneApi = document.getElementById('tab-pane-api');
        const btnAlerts = document.getElementById('tab-btn-alerts');
        const btnApi = document.getElementById('tab-btn-api');

        if (tabName === 'api') {
            if (paneAlerts) paneAlerts.classList.add('hidden');
            if (paneApi) paneApi.classList.remove('hidden');
            if (btnAlerts) {
                btnAlerts.className = 'px-3.5 py-2 rounded-t-lg border-b-2 border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-900/60 flex items-center gap-1.5 transition cursor-pointer';
            }
            if (btnApi) {
                btnApi.className = 'px-3.5 py-2 rounded-t-lg border-b-2 border-sky-400 text-white bg-slate-900 flex items-center gap-1.5 transition cursor-pointer';
            }
        } else {
            if (paneApi) paneApi.classList.add('hidden');
            if (paneAlerts) paneAlerts.classList.remove('hidden');
            if (btnAlerts) {
                btnAlerts.className = 'px-3.5 py-2 rounded-t-lg border-b-2 border-indigo-500 text-white bg-slate-900 flex items-center gap-1.5 transition cursor-pointer';
            }
            if (btnApi) {
                btnApi.className = 'px-3.5 py-2 rounded-t-lg border-b-2 border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-900/60 flex items-center gap-1.5 transition cursor-pointer';
            }
        }
    };

    window.updateZcModeBadge = function() {
        const modeSelect = document.getElementById('zc-mode-select');
        const badge = document.getElementById('zc-mode-badge');
        const tabBadge = document.getElementById('tab-api-badge');
        if (modeSelect) {
            const val = modeSelect.value;
            if (badge) {
                badge.textContent = val;
                badge.className = val === 'LIVE'
                    ? 'px-1.5 py-0.2 rounded text-[8.5px] font-mono bg-rose-500/20 text-rose-300 border border-rose-500/40'
                    : 'px-1.5 py-0.2 rounded text-[8.5px] font-mono bg-purple-500/20 text-purple-300 border border-purple-500/40';
            }
            if (tabBadge) {
                tabBadge.textContent = val;
                tabBadge.className = val === 'LIVE'
                    ? 'ml-1 px-1.5 py-0.2 rounded text-[8px] font-mono bg-rose-500/20 text-rose-300 border border-rose-500/40'
                    : 'ml-1 px-1.5 py-0.2 rounded text-[8px] font-mono bg-purple-500/20 text-purple-300 border border-purple-500/40';
            }
        }
    };

    window.testZeroClickWebhook = async function(signal = 'BUY') {
        const statusBox = document.getElementById('zc-test-status-box');
        if (statusBox) {
            statusBox.className = 'p-2 rounded-lg bg-slate-900 border border-slate-700 text-[9.5px] font-mono text-amber-300 block';
            statusBox.textContent = `⏳ Sending ${signal} test dispatch to 0click.in...`;
        }

        const urlInput = document.getElementById('zc-webhook-url');
        const tokenInput = document.getElementById('zc-webhook-token');
        const modeSelect = document.getElementById('zc-mode-select');
        const lotsInput = document.getElementById('zc-lots-input');
        const slPtsInput = document.getElementById('zc-sl-pts-input');
        const tpPtsInput = document.getElementById('zc-tp-pts-input');
        const tradeStyleSelect = document.getElementById('zc-trade-style');

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

        try {
            const resp = await fetch(`{{ route('api.order-flow.test-webhook') }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    signal: signal,
                    webhook_url: urlInput?.value.trim() || 'https://0click.in/api/webhook/tradingview',
                    webhook_token: tokenInput?.value.trim() || '',
                    mode: modeSelect?.value || 'SIMULATION',
                    trade_style: tradeStyleSelect?.value || zeroClickSettings.tradeStyle || 'OPTION_SELLING',
                    lots: parseInt(lotsInput?.value || 1, 10),
                    price: state.depthData?.ltp || 23300.0,
                    sl: (state.depthData?.ltp || 23300.0) + (signal === 'BUY' ? -parseInt(slPtsInput?.value || 15) : parseInt(slPtsInput?.value || 15)),
                    tp: (state.depthData?.ltp || 23300.0) + (signal === 'BUY' ? parseInt(tpPtsInput?.value || 100) : -parseInt(tpPtsInput?.value || 100))
                })
            });

            const res = await resp.json();
            if (statusBox) {
                if (res.success) {
                    statusBox.className = 'p-2 rounded-lg bg-emerald-950/80 border border-emerald-500/60 text-[9.5px] font-mono text-emerald-300 block';
                    statusBox.textContent = `✅ 0Click.in Dispatch SUCCESS (${res.status_code} in ${res.latency_ms}ms)!\nResponse: ${JSON.stringify(res.response, null, 2)}`;
                } else {
                    statusBox.className = 'p-2 rounded-lg bg-rose-950/80 border border-rose-500/60 text-[9.5px] font-mono text-rose-300 block';
                    statusBox.textContent = `❌ 0Click.in Dispatch FAILED (${res.status_code || 500} in ${res.latency_ms || 0}ms)\n${res.error || JSON.stringify(res.response, null, 2)}`;
                }
            }
        } catch (e) {
            if (statusBox) {
                statusBox.className = 'p-2 rounded-lg bg-rose-950/80 border border-rose-500/60 text-[9.5px] font-mono text-rose-300 block';
                statusBox.textContent = `❌ Connection Error: ${e.message}`;
            }
        }
    };

    function updateLegendLabels() {
        const blockTag = document.getElementById('legend-block-tag');
        const stackTag = document.getElementById('legend-stack-tag');
        if (blockTag) {
            const threshK = alertSettings.singleThreshold >= 1000 ? `${Math.round(alertSettings.singleThreshold / 1000)}k` : alertSettings.singleThreshold;
            blockTag.textContent = alertSettings.enableBlock 
                ? `⚡ Block (≥${threshK})`
                : `⚡ Block (OFF)`;
            blockTag.title = alertSettings.enableBlock
                ? `Heavy Resting Block Wall (≥${alertSettings.singleThreshold.toLocaleString()} Qty)`
                : `Block Alerts Disabled`;
            blockTag.className = alertSettings.enableBlock
                ? 'px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 font-black border border-amber-500/40 whitespace-nowrap text-[9px]'
                : 'px-1.5 py-0.5 rounded bg-slate-900 text-slate-500 font-bold border border-slate-800 line-through whitespace-nowrap text-[9px]';
        }
        if (stackTag) {
            const stackQtyK = alertSettings.stackMinQty >= 1000 ? `${Math.round(alertSettings.stackMinQty / 1000)}k` : alertSettings.stackMinQty;
            stackTag.textContent = alertSettings.enableStack 
                ? `🌊 Stack (≥${alertSettings.stackMinConsecutive}L)`
                : `🌊 Stack (OFF)`;
            stackTag.title = alertSettings.enableStack
                ? `Consecutive Liquidity Order Stack (≥${alertSettings.stackMinConsecutive} Levels · ≥${alertSettings.stackMinQty.toLocaleString()} Qty)`
                : `Stack Alerts Disabled`;
            stackTag.className = alertSettings.enableStack
                ? 'px-1.5 py-0.5 rounded bg-cyan-500/20 text-cyan-300 font-black border border-cyan-500/40 whitespace-nowrap text-[9px]'
                : 'px-1.5 py-0.5 rounded bg-slate-900 text-slate-500 font-bold border border-slate-800 line-through whitespace-nowrap text-[9px]';
        }
    }

    function loadAlertSettings() {
        try {
            const saved = localStorage.getItem('d30_alert_rules');
            if (saved) {
                Object.assign(alertSettings, JSON.parse(saved));
            }
        } catch(e) {}

        // Load 0Click Webhook Settings (fallback to unified settings if already configured there)
        try {
            const savedZc = localStorage.getItem('order_flow_zeroclick_settings') || localStorage.getItem('nifty_zeroclick_settings');
            if (savedZc) {
                Object.assign(zeroClickSettings, JSON.parse(savedZc));
            }
        } catch(e) {}

        const enableBlock = document.getElementById('cfg-enable-block');
        const singleInput = document.getElementById('cfg-single-qty');
        const enableStack = document.getElementById('cfg-enable-stack');
        const stackCount = document.getElementById('cfg-stack-count');
        const stackQty = document.getElementById('cfg-stack-qty');
        const enableConf = document.getElementById('cfg-enable-confluence');
        const enableAbs = document.getElementById('cfg-enable-absorption');
        const soundMaster = document.getElementById('cfg-sound-master');
        const soundStack = document.getElementById('cfg-sound-stack');
        const soundStrategy = document.getElementById('cfg-sound-strategy');
        const soundProfile = document.getElementById('cfg-sound-profile');
        const autoLogInput = document.getElementById('cfg-autolog-journal');

        if (enableBlock) enableBlock.checked = alertSettings.enableBlock !== false;
        if (singleInput) singleInput.value = alertSettings.singleThreshold;
        if (enableStack) enableStack.checked = alertSettings.enableStack !== false;
        if (stackCount) stackCount.value = alertSettings.stackMinConsecutive;
        if (stackQty) stackQty.value = alertSettings.stackMinQty;
        if (enableConf) enableConf.checked = alertSettings.enableConfluence !== false;
        if (enableAbs) enableAbs.checked = alertSettings.enableAbsorption !== false;
        if (soundMaster) soundMaster.checked = alertSettings.soundEnabled !== false;
        if (soundStack) soundStack.checked = alertSettings.soundStack !== false;
        if (soundStrategy) soundStrategy.checked = alertSettings.soundStrategy !== false;
        if (soundProfile) soundProfile.value = alertSettings.soundProfile || 'crystal';
        if (autoLogInput) autoLogInput.checked = alertSettings.autoLog !== false;

        // 4-Pillar High-Probability Inputs
        const strictConf = document.getElementById('cfg-strict-confluence');
        const bWeight = document.getElementById('cfg-pillar-breadth-weight');
        const bPts = document.getElementById('cfg-pillar-breadth-pts');
        const oiBull = document.getElementById('cfg-pillar-oi-bull-pcr');
        const oiBear = document.getElementById('cfg-pillar-oi-bear-pcr');
        const quantPop = document.getElementById('cfg-pillar-quant-pop');
        const obiPct = document.getElementById('cfg-pillar-obi-pct');
        const microDrift = document.getElementById('cfg-pillar-micro-drift');

        if (strictConf) strictConf.checked = !!alertSettings.strictConfluence;
        if (bWeight) bWeight.value = alertSettings.pillarBreadthWeight ?? 60;
        if (bPts) bPts.value = alertSettings.pillarBreadthPts ?? 2.5;
        if (oiBull) oiBull.value = alertSettings.pillarOiBullPcr ?? 1.05;
        if (oiBear) oiBear.value = alertSettings.pillarOiBearPcr ?? 0.85;
        if (quantPop) quantPop.value = alertSettings.pillarQuantPop ?? 70;
        if (obiPct) obiPct.value = alertSettings.pillarObiPct ?? 20;
        if (microDrift) microDrift.value = alertSettings.pillarMicroDrift ?? 0.15;

        // 0Click Inputs
        const zcAutoLog = document.getElementById('zc-auto-log-toggle');
        const zcForward = document.getElementById('zc-forward-toggle');
        const zcTradeStyle = document.getElementById('zc-trade-style');
        const zcUrl = document.getElementById('zc-webhook-url');
        const zcToken = document.getElementById('zc-webhook-token');
        const zcMode = document.getElementById('zc-mode-select');
        const zcLots = document.getElementById('zc-lots-input');
        const zcSlMode = document.getElementById('zc-sl-mode-select');
        const zcSlPts = document.getElementById('zc-sl-pts-input');
        const zcTpPts = document.getElementById('zc-tp-pts-input');
        const zcTp2Pts = document.getElementById('zc-tp2-pts-input');

        if (zcAutoLog) zcAutoLog.checked = zeroClickSettings.autoLog !== false;
        if (zcForward) zcForward.checked = !!zeroClickSettings.forwardToZeroClick;
        if (zcTradeStyle) zcTradeStyle.value = zeroClickSettings.tradeStyle || 'OPTION_SELLING';
        if (zcUrl) zcUrl.value = zeroClickSettings.webhookUrl || 'https://0click.in/api/webhook/tradingview';
        if (zcToken) zcToken.value = zeroClickSettings.webhookToken || '';
        if (zcMode) zcMode.value = zeroClickSettings.mode || 'SIMULATION';
        if (zcLots) zcLots.value = zeroClickSettings.lots || 1;
        if (zcSlMode) zcSlMode.value = zeroClickSettings.slMode || 'candle_hl';
        if (zcSlPts) zcSlPts.value = zeroClickSettings.slBufferPts || 15;
        if (zcTpPts) zcTpPts.value = zeroClickSettings.targetTp1Pts || 100;
        if (zcTp2Pts) zcTp2Pts.value = zeroClickSettings.targetTp2Pts || 150;

        const zcMaxTrades = document.getElementById('zc-max-daily-trades');
        const zcDailyBadge = document.getElementById('zc-daily-trades-badge');
        if (zcMaxTrades) zcMaxTrades.value = zeroClickSettings.maxDailyTrades || 3;
        if (zcDailyBadge) {
            const todayCount = (state.cachedJournalLogs || []).filter(l => l.status === 'TARGET_HIT' || l.status === 'STOPPED_OUT' || l.status === 'ACTIVE').length;
            zcDailyBadge.textContent = `${todayCount} / ${zeroClickSettings.maxDailyTrades || 3} Executed Today`;
        }

        updateZcModeBadge();
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

    window.setPillarPreset = function(presetName) {
        const strictEl = document.getElementById('cfg-strict-confluence');
        const bWeight = document.getElementById('cfg-pillar-breadth-weight');
        const bPts = document.getElementById('cfg-pillar-breadth-pts');
        const oiBull = document.getElementById('cfg-pillar-oi-bull-pcr');
        const oiBear = document.getElementById('cfg-pillar-oi-bear-pcr');
        const quantPop = document.getElementById('cfg-pillar-quant-pop');
        const obiPct = document.getElementById('cfg-pillar-obi-pct');
        const microDrift = document.getElementById('cfg-pillar-micro-drift');

        if (presetName === 'sniper') {
            if (strictEl) strictEl.checked = true;
            if (bWeight) bWeight.value = 70;
            if (bPts) bPts.value = 5.0;
            if (oiBull) oiBull.value = 1.15;
            if (oiBear) oiBear.value = 0.80;
            if (quantPop) quantPop.value = 75;
            if (obiPct) obiPct.value = 25;
            if (microDrift) microDrift.value = 0.20;
        } else if (presetName === 'standard') {
            if (strictEl) strictEl.checked = false;
            if (bWeight) bWeight.value = 60;
            if (bPts) bPts.value = 2.5;
            if (oiBull) oiBull.value = 1.05;
            if (oiBear) oiBear.value = 0.85;
            if (quantPop) quantPop.value = 70;
            if (obiPct) obiPct.value = 20;
            if (microDrift) microDrift.value = 0.15;
        } else if (presetName === 'scalper') {
            if (strictEl) strictEl.checked = false;
            if (bWeight) bWeight.value = 55;
            if (bPts) bPts.value = 1.5;
            if (oiBull) oiBull.value = 1.02;
            if (oiBear) oiBear.value = 0.90;
            if (quantPop) quantPop.value = 60;
            if (obiPct) obiPct.value = 12;
            if (microDrift) microDrift.value = 0.10;
        }
    };

    window.resetAlertDefaults = function() {
        alertSettings.enableBlock = true;
        alertSettings.singleThreshold = 2500;
        alertSettings.enableStack = true;
        alertSettings.stackMinConsecutive = 3;
        alertSettings.stackMinQty = 1500;
        alertSettings.enableConfluence = true;
        alertSettings.enableAbsorption = true;
        alertSettings.soundEnabled = true;
        alertSettings.soundStack = true;
        alertSettings.soundStrategy = true;
        alertSettings.soundProfile = 'crystal';
        alertSettings.autoLog = true;

        alertSettings.strictConfluence = false;
        alertSettings.pillarBreadthWeight = 60;
        alertSettings.pillarBreadthPts = 2.5;
        alertSettings.pillarOiBullPcr = 1.05;
        alertSettings.pillarOiBearPcr = 0.85;
        alertSettings.pillarQuantPop = 70;
        alertSettings.pillarObiPct = 20;
        alertSettings.pillarMicroDrift = 0.15;

        zeroClickSettings.autoLog = true;
        zeroClickSettings.forwardToZeroClick = false;
        zeroClickSettings.tradeStyle = 'OPTION_SELLING';
        zeroClickSettings.webhookUrl = 'https://0click.in/api/webhook/tradingview';
        zeroClickSettings.mode = 'SIMULATION';
        zeroClickSettings.lots = 1;
        zeroClickSettings.slMode = 'candle_hl';
        zeroClickSettings.slBufferPts = 15;
        zeroClickSettings.targetTp1Pts = 100;
        zeroClickSettings.targetTp2Pts = 150;

        loadAlertSettings();
    };

    window.saveAlertSettings = function() {
        const enableBlock = document.getElementById('cfg-enable-block');
        const singleInput = document.getElementById('cfg-single-qty');
        const enableStack = document.getElementById('cfg-enable-stack');
        const stackCount = document.getElementById('cfg-stack-count');
        const stackQty = document.getElementById('cfg-stack-qty');
        const enableConf = document.getElementById('cfg-enable-confluence');
        const enableAbs = document.getElementById('cfg-enable-absorption');
        const soundMaster = document.getElementById('cfg-sound-master');
        const soundStack = document.getElementById('cfg-sound-stack');
        const soundStrategy = document.getElementById('cfg-sound-strategy');
        const soundProfile = document.getElementById('cfg-sound-profile');
        const autoLogInput = document.getElementById('cfg-autolog-journal');

        alertSettings.enableBlock = !!(enableBlock?.checked);
        alertSettings.singleThreshold = Math.max(100, parseInt(singleInput?.value || 2500));
        alertSettings.enableStack = !!(enableStack?.checked);
        alertSettings.stackMinConsecutive = Math.max(2, parseInt(stackCount?.value || 3));
        alertSettings.stackMinQty = Math.max(100, parseInt(stackQty?.value || 1500));
        alertSettings.enableConfluence = !!(enableConf?.checked);
        alertSettings.enableAbsorption = !!(enableAbs?.checked);
        alertSettings.soundEnabled = !!(soundMaster?.checked);
        alertSettings.soundStack = !!(soundStack?.checked);
        alertSettings.soundStrategy = !!(soundStrategy?.checked);
        alertSettings.soundProfile = soundProfile?.value || 'crystal';
        alertSettings.autoLog = !!(autoLogInput?.checked);

        // 4-Pillar High-Probability Settings
        const strictConf = document.getElementById('cfg-strict-confluence');
        const bWeight = document.getElementById('cfg-pillar-breadth-weight');
        const bPts = document.getElementById('cfg-pillar-breadth-pts');
        const oiBull = document.getElementById('cfg-pillar-oi-bull-pcr');
        const oiBear = document.getElementById('cfg-pillar-oi-bear-pcr');
        const quantPop = document.getElementById('cfg-pillar-quant-pop');
        const obiPct = document.getElementById('cfg-pillar-obi-pct');
        const microDrift = document.getElementById('cfg-pillar-micro-drift');

        alertSettings.strictConfluence = !!(strictConf?.checked);
        alertSettings.pillarBreadthWeight = Math.max(50, Math.min(99, parseFloat(bWeight?.value || 60)));
        alertSettings.pillarBreadthPts = Math.max(0.1, parseFloat(bPts?.value || 2.5));
        alertSettings.pillarOiBullPcr = Math.max(0.5, parseFloat(oiBull?.value || 1.05));
        alertSettings.pillarOiBearPcr = Math.max(0.2, parseFloat(oiBear?.value || 0.85));
        alertSettings.pillarQuantPop = Math.max(50, Math.min(99, parseFloat(quantPop?.value || 70)));
        alertSettings.pillarObiPct = Math.max(5, Math.min(95, parseFloat(obiPct?.value || 20)));
        alertSettings.pillarMicroDrift = Math.max(0.01, parseFloat(microDrift?.value || 0.15));

        // Save 0Click Settings
        const zcAutoLog = document.getElementById('zc-auto-log-toggle');
        const zcForward = document.getElementById('zc-forward-toggle');
        const zcTradeStyle = document.getElementById('zc-trade-style');
        const zcUrl = document.getElementById('zc-webhook-url');
        const zcToken = document.getElementById('zc-webhook-token');
        const zcMode = document.getElementById('zc-mode-select');
        const zcLots = document.getElementById('zc-lots-input');
        const zcSlMode = document.getElementById('zc-sl-mode-select');
        const zcSlPts = document.getElementById('zc-sl-pts-input');
        const zcTpPts = document.getElementById('zc-tp-pts-input');
        const zcTp2Pts = document.getElementById('zc-tp2-pts-input');

        zeroClickSettings.autoLog = !!zcAutoLog?.checked;
        zeroClickSettings.forwardToZeroClick = !!zcForward?.checked;
        zeroClickSettings.tradeStyle = zcTradeStyle?.value || 'OPTION_SELLING';
        zeroClickSettings.webhookUrl = zcUrl?.value.trim() || 'https://0click.in/api/webhook/tradingview';
        zeroClickSettings.webhookToken = zcToken?.value.trim() || '';
        zeroClickSettings.mode = zcMode?.value || 'SIMULATION';
        zeroClickSettings.lots = parseInt(zcLots?.value || 1, 10);
        zeroClickSettings.slMode = zcSlMode?.value || 'candle_hl';
        zeroClickSettings.slBufferPts = parseInt(zcSlPts?.value || 15, 10);
        zeroClickSettings.targetTp1Pts = parseInt(zcTpPts?.value || 100, 10);
        zeroClickSettings.targetTp2Pts = parseInt(zcTp2Pts?.value || 150, 10);

        const zcMaxTrades = document.getElementById('zc-max-daily-trades');
        zeroClickSettings.maxDailyTrades = Math.max(1, Math.min(50, parseInt(zcMaxTrades?.value || 3, 10)));

        try {
            localStorage.setItem('d30_alert_rules', JSON.stringify(alertSettings));
            localStorage.setItem('order_flow_zeroclick_settings', JSON.stringify(zeroClickSettings));
            localStorage.setItem('nifty_zeroclick_settings', JSON.stringify(zeroClickSettings));
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
        state.dismissedAlertKey = state.currentAlertKey;
        const banner = document.getElementById('liquidity-alert-banner');
        if (banner) {
            banner.className = 'bg-slate-900 border border-slate-800 rounded-xl p-2.5 shadow-xl transition-all duration-300';
            banner.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="relative flex h-2 w-2">
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        <div>
                            <span class="text-[10px] font-black uppercase text-slate-300 tracking-wider flex items-center gap-1">
                                <span>🛡️ Microstructure Radar</span>
                                <span class="text-[8px] bg-slate-950 text-slate-400 border border-slate-800 px-1 py-0.2 rounded font-mono">MUTED</span>
                            </span>
                            <p class="text-[9px] text-slate-400 font-sans mt-0.5 leading-tight">
                                Alert dismissed. Resuming background order flow monitoring.
                            </p>
                        </div>
                    </div>
                    <span class="text-[8px] text-slate-400 font-mono font-bold bg-slate-950 px-1.5 py-0.5 rounded border border-slate-800">
                        MUTED
                    </span>
                </div>
            `;
        }
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
            askStack: null,
            vpoc: {
                price: 0,
                volume: 0,
                isSupport: true,
                ordersEstimate: 0
            }
        },
        
        // Master Strategic Anchor State (NIFTY 50 Breadth Anchor)
        masterAnchor: null,

        // Engine Inspector Journal State
        journalFilter: 'ALL',
        cachedJournalLogs: [],

        volumeProfile: {},
        lastVtt: 0,
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

    function isMarketHours() {
        const now = new Date();
        const istOffset = 5.5 * 60 * 60 * 1000;
        const istDate = new Date(now.getTime() + (now.getTimezoneOffset() * 60 * 1000) + istOffset);
        const day = istDate.getDay();
        const isWeekday = (day >= 1 && day <= 5);
        const hr = istDate.getHours();
        const min = istDate.getMinutes();
        const timeVal = hr * 60 + min;
        return isWeekday && (timeVal >= (9 * 60 + 15) && timeVal <= (15 * 60 + 30));
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
                const open = isMarketHours();
                if (statusText) statusText.textContent = open ? 'Live Feed Active' : 'Live Connected (Market Closed)';
                subscribeActiveTarget();
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

        // ── TRACK SESSION EXECUTED VOLUME PROFILE & SESSION VPOC ──
        if (ltp > 0) {
            const pKey = ltp.toFixed(2);
            let deltaV = 0;
            if (vtt > 0) {
                if (state.lastVtt > 0 && vtt >= state.lastVtt) {
                    deltaV = vtt - state.lastVtt;
                } else if (state.lastVtt === 0) {
                    deltaV = Math.floor(120 + Math.random() * 280);
                }
                state.lastVtt = vtt;
            } else {
                deltaV = Math.floor(40 + Math.random() * 160);
            }

            if (deltaV > 0 && deltaV < 2500000) {
                state.volumeProfile[pKey] = (state.volumeProfile[pKey] || 0) + deltaV;
            }

            // Find price with highest cumulative executed volume
            let maxVol = 0;
            let maxPrice = 0;
            for (const [k, v] of Object.entries(state.volumeProfile)) {
                if (v > maxVol) {
                    maxVol = v;
                    maxPrice = parseFloat(k);
                }
            }

            if (maxPrice > 0) {
                state.depthData.vpoc = {
                    price: maxPrice,
                    volume: maxVol,
                    isSupport: ltp >= maxPrice,
                    ordersEstimate: Math.max(1, Math.round(maxVol / 125))
                };
            }
        }

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

    // ── 11. DIRECTIONAL ALERT BANNER (COLUMN 3 TOP DOCKED WIDGET) ──
    function renderAlertBanner(bidStack, askStack, heavyBidBlock = null, heavyAskBlock = null) {
        const banner = document.getElementById('liquidity-alert-banner');
        if (!banner) return;

        function renderAlertPlaceholder(msg = 'No active block or stack alerts. Order flow stable.', status = 'NORMAL') {
            banner.className = 'bg-slate-900 border border-slate-800 rounded-xl p-2.5 shadow-xl transition-all duration-300';
            banner.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="relative flex h-2 w-2">
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        <div>
                            <span class="text-[10px] font-black uppercase text-slate-300 tracking-wider flex items-center gap-1">
                                <span>🛡️ Microstructure Radar</span>
                                <span class="text-[8px] bg-emerald-950 text-emerald-400 border border-emerald-800/60 px-1 py-0.2 rounded font-mono">${status}</span>
                            </span>
                            <p class="text-[9px] text-slate-400 font-sans mt-0.5 leading-tight">
                                ${msg}
                            </p>
                        </div>
                    </div>
                    <span class="text-[8px] text-emerald-400 font-mono font-bold bg-slate-950 px-1.5 py-0.5 rounded border border-slate-800">
                        MONITORING
                    </span>
                </div>
            `;
        }

        let alertType = null;
        let alertKey = '';

        const allowedBidStack = alertSettings.enableStack ? bidStack : null;
        const allowedAskStack = alertSettings.enableStack ? askStack : null;
        const allowedBidBlock = alertSettings.enableBlock ? heavyBidBlock : null;
        const allowedAskBlock = alertSettings.enableBlock ? heavyAskBlock : null;

        // Priority 1: Multi-level continuous stacks
        if (allowedBidStack && (!allowedAskStack || allowedBidStack.totalQty >= allowedAskStack.totalQty)) {
            alertType = 'STACK_BUY';
            alertKey = `STACK_BUY_${allowedBidStack.lowP}_${allowedBidStack.highP}_${allowedBidStack.totalQty}`;
        } else if (allowedAskStack) {
            alertType = 'STACK_SELL';
            alertKey = `STACK_SELL_${allowedAskStack.lowP}_${allowedAskStack.highP}_${allowedAskStack.totalQty}`;
        } else if (allowedBidBlock && (!allowedAskBlock || allowedBidBlock.qty >= allowedAskBlock.qty)) {
            // Priority 2: Single level heavy institutional block (Qty >= alertSettings.singleThreshold)
            alertType = 'BLOCK_BUY';
            alertKey = `BLOCK_BUY_${allowedBidBlock.price}_${allowedBidBlock.qty}`;
        } else if (allowedAskBlock) {
            alertType = 'BLOCK_SELL';
            alertKey = `BLOCK_SELL_${allowedAskBlock.price}_${allowedAskBlock.qty}`;
        }

        if (!alertType) {
            renderAlertPlaceholder();
            state.currentAlertKey = '';
            state.lastLoggedBannerAlertKey = '';
            return;
        }

        const isNewBannerAlert = (state.lastLoggedBannerAlertKey !== alertKey);
        state.lastLoggedBannerAlertKey = alertKey;
        state.currentAlertKey = alertKey;

        // Respect manual dismissal of this specific alert until a new event occurs
        if (state.dismissedAlertKey === alertKey) {
            renderAlertPlaceholder('Alert dismissed. Resuming background order book scan.', 'MUTED');
            return;
        }

        const soundProfileLabel = getSoundProfileLabel(alertSettings.soundProfile || 'crystal');

        if (alertType === 'STACK_BUY') {
            banner.className = 'bg-gradient-to-br from-emerald-950 via-slate-900 to-slate-950 border-2 border-emerald-500/80 rounded-xl p-2.5 shadow-xl shadow-emerald-500/15 text-white space-y-1.5 transition-all duration-300';
            banner.innerHTML = `
                <div class="flex items-center justify-between gap-1">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-sm">🚀</span>
                        <span class="px-1.5 py-0.2 rounded bg-emerald-500 text-slate-950 font-black text-[9px] uppercase tracking-wider">
                            BULLISH STACK (${bidStack.count} LEVELS)
                        </span>
                    </div>
                    <button onclick="dismissAlertBanner()" class="text-slate-400 hover:text-white px-1.5 py-0.2 rounded text-[10px] bg-slate-950 border border-slate-800 cursor-pointer" title="Dismiss Alert">✕</button>
                </div>
                <div>
                    <p class="font-black text-emerald-300 text-xs font-mono leading-tight">
                        ${bidStack.count} Bid Levels · ${bidStack.totalQty.toLocaleString()} Qty
                    </p>
                    <span class="text-[9px] text-slate-400 font-mono">₹${bidStack.lowP.toFixed(2)} - ₹${bidStack.highP.toFixed(2)}</span>
                </div>
                <div class="flex items-center justify-between gap-1 pt-1 border-t border-slate-800/80 text-[8.5px]">
                    <span class="text-emerald-300 font-bold bg-slate-950 px-1.5 py-0.2 rounded border border-emerald-500/40">
                        ⚡ DIRECTION: GO LONG
                    </span>
                    <span class="text-amber-300/90 font-mono">🔊 ${soundProfileLabel} (Bull)</span>
                </div>
            `;

            if (isNewBannerAlert && (state.feedMode !== 'live' || isMarketHours())) {
                triggerStackAlertAudio('BUY');

                recordAlertEvent({
                    type: 'STACK_BUY',
                    side: 'BUY',
                    title: `Bullish Stack (${bidStack.count} Levels)`,
                    details: `${bidStack.totalQty.toLocaleString()} Qty at ₹${bidStack.lowP.toFixed(2)} - ₹${bidStack.highP.toFixed(2)}`,
                    guidance: '⚡ Direction: GO LONG / BUY CALL'
                });

                if (alertSettings.autoLog || zeroClickSettings.autoLog || zeroClickSettings.forwardToZeroClick) {
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
            }

        } else if (alertType === 'STACK_SELL') {
            banner.className = 'bg-gradient-to-br from-rose-950 via-slate-900 to-slate-950 border-2 border-rose-500/80 rounded-xl p-2.5 shadow-xl shadow-rose-500/15 text-white space-y-1.5 transition-all duration-300';
            banner.innerHTML = `
                <div class="flex items-center justify-between gap-1">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-sm">🩸</span>
                        <span class="px-1.5 py-0.2 rounded bg-rose-500 text-white font-black text-[9px] uppercase tracking-wider">
                            BEARISH STACK (${askStack.count} LEVELS)
                        </span>
                    </div>
                    <button onclick="dismissAlertBanner()" class="text-slate-400 hover:text-white px-1.5 py-0.2 rounded text-[10px] bg-slate-950 border border-slate-800 cursor-pointer" title="Dismiss Alert">✕</button>
                </div>
                <div>
                    <p class="font-black text-rose-300 text-xs font-mono leading-tight">
                        ${askStack.count} Ask Levels · ${askStack.totalQty.toLocaleString()} Qty
                    </p>
                    <span class="text-[9px] text-slate-400 font-mono">₹${askStack.lowP.toFixed(2)} - ₹${askStack.highP.toFixed(2)}</span>
                </div>
                <div class="flex items-center justify-between gap-1 pt-1 border-t border-slate-800/80 text-[8.5px]">
                    <span class="text-rose-300 font-bold bg-slate-950 px-1.5 py-0.2 rounded border border-rose-500/40">
                        ⚡ DIRECTION: GO SHORT
                    </span>
                    <span class="text-amber-300/90 font-mono">🔊 ${soundProfileLabel} (Bear)</span>
                </div>
            `;

            if (isNewBannerAlert && (state.feedMode !== 'live' || isMarketHours())) {
                triggerStackAlertAudio('SELL');

                recordAlertEvent({
                    type: 'STACK_SELL',
                    side: 'SELL',
                    title: `Bearish Stack (${askStack.count} Levels)`,
                    details: `${askStack.totalQty.toLocaleString()} Qty at ₹${askStack.lowP.toFixed(2)} - ₹${askStack.highP.toFixed(2)}`,
                    guidance: '⚡ Direction: GO SHORT / BUY PUT'
                });

                if (alertSettings.autoLog || zeroClickSettings.autoLog || zeroClickSettings.forwardToZeroClick) {
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
            }

        } else if (alertType === 'BLOCK_BUY') {
            banner.className = 'bg-gradient-to-br from-amber-950/90 via-slate-900 to-slate-950 border-2 border-amber-400/90 rounded-xl p-2.5 shadow-xl shadow-amber-400/20 text-white space-y-1.5 transition-all duration-300';
            banner.innerHTML = `
                <div class="flex items-center justify-between gap-1">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-sm">⚡</span>
                        <span class="px-1.5 py-0.2 rounded bg-amber-400 text-slate-950 font-black text-[9px] uppercase tracking-wider">
                            BID BLOCK (LVL ${heavyBidBlock.level})
                        </span>
                    </div>
                    <button onclick="dismissAlertBanner()" class="text-slate-400 hover:text-white px-1.5 py-0.2 rounded text-[10px] bg-slate-950 border border-slate-800 cursor-pointer" title="Dismiss Alert">✕</button>
                </div>
                <div>
                    <p class="font-black text-amber-300 text-xs font-mono leading-tight">
                        ${heavyBidBlock.qty.toLocaleString()} Qty at ₹${heavyBidBlock.price.toFixed(2)}
                    </p>
                    <span class="text-[9px] text-slate-400 font-mono">Resting Buy Wall (≥${parseInt(alertSettings.singleThreshold).toLocaleString()})</span>
                </div>
                <div class="flex items-center justify-between gap-1 pt-1 border-t border-slate-800/80 text-[8.5px]">
                    <span class="text-emerald-300 font-bold bg-slate-950 px-1.5 py-0.2 rounded border border-emerald-500/40">
                        🛡️ STRONG SUPPORT WALL
                    </span>
                    <span class="text-amber-300/90 font-mono">🔊 ${soundProfileLabel}</span>
                </div>
            `;

            if (isNewBannerAlert && (state.feedMode !== 'live' || isMarketHours())) {
                triggerStackAlertAudio('BUY');

                recordAlertEvent({
                    type: 'BLOCK_BUY',
                    side: 'BUY',
                    title: `Heavy Bid Block: ${heavyBidBlock.qty.toLocaleString()} Qty`,
                    details: `₹${heavyBidBlock.price.toFixed(2)} (Level ${heavyBidBlock.level})`,
                    guidance: '🛡️ Strong Support Wall / Accumulation'
                });

                if (alertSettings.autoLog || zeroClickSettings.autoLog || zeroClickSettings.forwardToZeroClick) {
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
            }

        } else if (alertType === 'BLOCK_SELL') {
            banner.className = 'bg-gradient-to-br from-amber-950/90 via-slate-900 to-slate-950 border-2 border-rose-400/90 rounded-xl p-2.5 shadow-xl shadow-rose-400/20 text-white space-y-1.5 transition-all duration-300';
            banner.innerHTML = `
                <div class="flex items-center justify-between gap-1">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-sm">⚡</span>
                        <span class="px-1.5 py-0.2 rounded bg-rose-500 text-white font-black text-[9px] uppercase tracking-wider">
                            ASK BLOCK (LVL ${heavyAskBlock.level})
                        </span>
                    </div>
                    <button onclick="dismissAlertBanner()" class="text-slate-400 hover:text-white px-1.5 py-0.2 rounded text-[10px] bg-slate-950 border border-slate-800 cursor-pointer" title="Dismiss Alert">✕</button>
                </div>
                <div>
                    <p class="font-black text-rose-300 text-xs font-mono leading-tight">
                        ${heavyAskBlock.qty.toLocaleString()} Qty at ₹${heavyAskBlock.price.toFixed(2)}
                    </p>
                    <span class="text-[9px] text-slate-400 font-mono">Resting Sell Wall (≥${parseInt(alertSettings.singleThreshold).toLocaleString()})</span>
                </div>
                <div class="flex items-center justify-between gap-1 pt-1 border-t border-slate-800/80 text-[8.5px]">
                    <span class="text-rose-300 font-bold bg-slate-950 px-1.5 py-0.2 rounded border border-rose-500/40">
                        🚧 STRONG RESISTANCE WALL
                    </span>
                    <span class="text-amber-300/90 font-mono">🔊 ${soundProfileLabel}</span>
                </div>
            `;

            if (isNewBannerAlert && (state.feedMode !== 'live' || isMarketHours())) {
                triggerStackAlertAudio('SELL');

                recordAlertEvent({
                    type: 'BLOCK_SELL',
                    side: 'SELL',
                    title: `Heavy Ask Block: ${heavyAskBlock.qty.toLocaleString()} Qty`,
                    details: `₹${heavyAskBlock.price.toFixed(2)} (Level ${heavyAskBlock.level})`,
                    guidance: '🚧 Strong Resistance Wall / Supply'
                });

                if (alertSettings.autoLog || zeroClickSettings.autoLog || zeroClickSettings.forwardToZeroClick) {
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
            }
        } else {
            renderAlertPlaceholder();
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
                _sharedAudioCtx.resume().catch(() => {});
            }
        } catch(e) {}
        return _sharedAudioCtx;
    }

    // Auto-unlock audio context on user interaction
    ['click', 'touchstart', 'keydown'].forEach(evt => {
        document.addEventListener(evt, () => {
            if (!_sharedAudioCtx) {
                getAudioCtx();
            } else if (_sharedAudioCtx.state === 'suspended') {
                _sharedAudioCtx.resume().catch(() => {});
            }
        }, { passive: true, once: true });
    });

    function playSynthesizedAlert(profile = 'crystal', side = 'BUY') {
        try {
            const ctx = getAudioCtx();
            if (!ctx) return;
            if (ctx.state === 'suspended') {
                ctx.resume().catch(() => {});
                return; // Don't trigger browser autoplay warning until user interacts
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
        // Guard: In Live Feed mode, pause recording alerts when the market is closed
        if (state.feedMode === 'live' && !isMarketHours()) {
            return;
        }

        state.recentAlertEvents = state.recentAlertEvents || [];

        // Deduplication: Avoid re-recording identical alert within 60s
        const isDuplicate = state.recentAlertEvents.some(prev => 
            prev.type === event.type && 
            prev.title === event.title && 
            prev.details === event.details && 
            (Date.now() - prev.timestamp) < 60000
        );
        if (isDuplicate) return;

        if (state.recentAlertEvents.length > 0) {
            const last = state.recentAlertEvents[0];
            if (last.type === event.type && (Date.now() - last.timestamp) < 10000) {
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
        const marketClosedPill = document.getElementById('market-status-alert-pill');

        if (marketClosedPill) {
            if (state.feedMode === 'live' && !isMarketHours()) {
                marketClosedPill.classList.remove('hidden');
            } else {
                marketClosedPill.classList.add('hidden');
            }
        }

        if (countBadge) countBadge.textContent = (state.recentAlertEvents || []).length;

        if (!container) return;
        if (!state.recentAlertEvents || state.recentAlertEvents.length === 0) {
            const emptyNotice = (state.feedMode === 'live' && !isMarketHours())
                ? 'Market is closed (Session: 09:15 - 15:30 IST). Live alarms are paused until next trading session.'
                : 'No alarms triggered yet this session. When audio sounds, exact trigger reasons appear here in real-time.';
            container.innerHTML = `<div class="text-center py-6 text-slate-500 text-[10px]">${emptyNotice}</div>`;
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
        if (!state.soundEnabled || !alertSettings.soundEnabled || !alertSettings.soundStack) return;
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
        const bannerVpoc = document.getElementById('banner-vpoc-pill');
        const bannerSpreadGuard = document.getElementById('banner-spread-guard');
        const bannerMicroVector = document.getElementById('banner-micro-vector');

        if (bannerLtp) bannerLtp.textContent = `₹${ltp.toFixed(2)}`;
        if (bannerSpread) bannerSpread.textContent = `${spread.toFixed(2)} pts`;
        if (bannerVwap) bannerVwap.textContent = `VWAP: ₹${state.depthData.atp > 0 ? state.depthData.atp.toFixed(2) : ltp.toFixed(2)}`;

        // Session Executed VPOC (Volume Point of Control - Max Orders Level)
        if (bannerVpoc) {
            const vp = state.depthData.vpoc;
            if (vp && vp.price > 0) {
                const volK = vp.volume >= 1000 ? `${(vp.volume / 1000).toFixed(1)}k` : vp.volume;
                const roleLabel = vp.isSupport ? 'Supp' : 'Trap';
                const roleColor = vp.isSupport ? 'text-emerald-300' : 'text-rose-300';
                bannerVpoc.innerHTML = `🎯 VPOC: ₹${vp.price.toFixed(2)} <span class="font-bold ${roleColor}">(${volK} · ${roleLabel})</span>`;
            } else {
                bannerVpoc.innerHTML = `🎯 VPOC: ₹--`;
            }
        }

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

        // 1.b Update Dynamic Single-Line Header VPOC & POC Badges (Prefix B/A with Green/Red)
        const domHeaderVpoc = document.getElementById('dom-header-vpoc');
        if (domHeaderVpoc) {
            const vp = state.depthData.vpoc;
            if (vp && vp.price > 0) {
                const volK = vp.volume >= 1000 ? `${(vp.volume / 1000).toFixed(1)}k` : vp.volume;
                const isBuyer = vp.isSupport || vp.price <= ltp;
                const pfx = isBuyer ? 'B' : 'A';
                const pfxClass = isBuyer ? 'text-emerald-400 font-black' : 'text-rose-400 font-black';
                const pillBorder = isBuyer ? 'border-emerald-500/50 bg-emerald-950/40 text-emerald-300' : 'border-rose-500/50 bg-rose-950/40 text-rose-300';
                domHeaderVpoc.className = `px-1.5 py-0.5 rounded font-mono font-black text-[9.5px] border ${pillBorder} whitespace-nowrap shadow-xs`;
                domHeaderVpoc.innerHTML = `🎯 VPOC: <span class="${pfxClass}">${pfx}</span> ₹${vp.price.toFixed(2)} <span class="text-slate-400 text-[8.5px]">(${volK})</span>`;
                domHeaderVpoc.title = `Volume POC: ${isBuyer ? 'Buyer Dominated Support (B)' : 'Seller Trap Resistance (A)'} at ₹${vp.price.toFixed(2)} (${vp.volume.toLocaleString()} executed volume)`;
            } else {
                domHeaderVpoc.className = 'px-1.5 py-0.5 rounded bg-slate-950 border border-slate-800 text-slate-500 font-mono text-[9.5px] whitespace-nowrap';
                domHeaderVpoc.innerHTML = `🎯 VPOC: --`;
            }
        }

        const domHeaderPoc = document.getElementById('dom-header-poc');
        if (domHeaderPoc) {
            if (pocSide && pocIndex >= 0) {
                const pocPrice = pocSide === 'BID' ? bids[pocIndex]?.price : asks[pocIndex]?.price;
                const pocQtyK = maxBookQty >= 1000 ? `${(maxBookQty / 1000).toFixed(1)}k` : maxBookQty;
                const isBid = pocSide === 'BID';
                const pfx = isBid ? 'B' : 'A';
                const pfxClass = isBid ? 'text-emerald-400 font-black' : 'text-rose-400 font-black';
                const pillBorder = isBid ? 'border-emerald-500/50 bg-emerald-950/40 text-emerald-300' : 'border-rose-500/50 bg-rose-950/40 text-rose-300';
                domHeaderPoc.className = `px-1.5 py-0.5 rounded font-mono font-black text-[9.5px] border ${pillBorder} whitespace-nowrap shadow-xs`;
                domHeaderPoc.innerHTML = `👑 POC: <span class="${pfxClass}">${pfx}</span> ₹${pocPrice ? pocPrice.toFixed(2) : '--'} <span class="text-slate-400 text-[8.5px]">(${pocQtyK})</span>`;
                domHeaderPoc.title = `Order Book POC: Heaviest Limit Order Wall on ${isBid ? 'Bid Side (B)' : 'Ask Side (A)'} at ₹${pocPrice ? pocPrice.toFixed(2) : '--'} (${maxBookQty.toLocaleString()} resting orders)`;
            } else {
                domHeaderPoc.className = 'px-1.5 py-0.5 rounded bg-slate-950 border border-slate-800 text-slate-500 font-mono text-[9.5px] whitespace-nowrap';
                domHeaderPoc.innerHTML = `👑 POC: --`;
            }
        }

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
            const isBidBlock = alertSettings.enableBlock && b && (b.qty >= alertSettings.singleThreshold);
            const isAskBlock = alertSettings.enableBlock && a && (a.qty >= alertSettings.singleThreshold);

            // 2. Consecutive Stack Check (User setting)
            const isBidInStack = alertSettings.enableStack && bidStack && bidStack.levels.some(x => x.index === i);
            const isAskInStack = alertSettings.enableStack && askStack && askStack.levels.some(x => x.index === i);

            // 3. Point of Control (POC Anchor) Check
            const isBidPoc = (pocSide === 'BID' && pocIndex === i && maxBookQty >= 1200);
            const isAskPoc = (pocSide === 'ASK' && pocIndex === i && maxBookQty >= 1200);

            // 3B. Session Executed Volume Point of Control (VPOC - Max Executed Trade Level)
            const vpPrice = state.depthData.vpoc?.price || 0;
            const isBidVpoc = b && vpPrice > 0 && Math.abs(b.price - vpPrice) < 0.03;
            const isAskVpoc = a && vpPrice > 0 && Math.abs(a.price - vpPrice) < 0.03;
            const isVpocRow = isBidVpoc || isAskVpoc;

            // 4. LTP Row Check
            const isNearLtp = (b && Math.abs(b.price - ltp) < 0.05) || (a && Math.abs(a.price - ltp) < 0.05);

            // Dynamic Row Classes
            let rowClasses = "grid grid-cols-12 gap-1 items-center py-0.5 px-1.5 rounded transition text-[11px] relative ";
            if (isNearLtp) {
                rowClasses += "bg-amber-500/20 ring-1 ring-amber-400 font-bold ";
            } else if (isVpocRow) {
                rowClasses += "bg-gradient-to-r from-amber-500/15 via-yellow-500/25 to-amber-500/15 ring-1 ring-amber-400/90 shadow-[0_0_10px_rgba(245,158,11,0.25)] ";
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

            // Orders Column Content with Stack Pills, POC Anchor & VPOC Badges
            let bidOrderHtml = b ? `
                <div class="flex items-center gap-1">
                    ${isBidVpoc ? `<span class="px-1 py-0.2 rounded text-[7px] font-black bg-gradient-to-r from-amber-400 to-yellow-400 text-slate-950 uppercase tracking-tighter shadow-xs" title="Session Executed VPOC (${state.depthData.vpoc.volume.toLocaleString()} Vol · ${state.depthData.vpoc.isSupport ? 'Absorption Support' : 'Overhead Trap'})">🎯 VPOC</span>` : ''}
                    ${isBidPoc ? '<span class="px-1 py-0.2 rounded text-[7px] font-black bg-fuchsia-500 text-white uppercase tracking-tighter shadow-xs" title="Point of Control (Resting Anchor)">👑 POC</span>' : ''}
                    ${isBidInStack ? '<span class="px-1 py-0.2 rounded text-[7.5px] font-black bg-emerald-500 text-slate-950 uppercase tracking-tighter shadow-xs">STACK</span>' : ''}
                    <span class="${isBidInStack ? 'text-emerald-300 font-bold' : (isBidVpoc ? 'text-amber-300 font-black' : 'text-slate-400')}">${b.orders || 1}</span>
                </div>
            ` : '-';

            let askOrderHtml = a ? `
                <div class="flex items-center justify-end gap-1">
                    <span class="${isAskInStack ? 'text-rose-300 font-bold' : (isAskVpoc ? 'text-amber-300 font-black' : 'text-slate-400')}">${a.orders || 1}</span>
                    ${isAskInStack ? '<span class="px-1 py-0.2 rounded text-[7.5px] font-black bg-rose-500 text-white uppercase tracking-tighter shadow-xs">STACK</span>' : ''}
                    ${isAskPoc ? '<span class="px-1 py-0.2 rounded text-[7px] font-black bg-fuchsia-500 text-white uppercase tracking-tighter shadow-xs" title="Point of Control (Resting Anchor)">👑 POC</span>' : ''}
                    ${isAskVpoc ? `<span class="px-1 py-0.2 rounded text-[7px] font-black bg-gradient-to-r from-amber-400 to-yellow-400 text-slate-950 uppercase tracking-tighter shadow-xs" title="Session Executed VPOC (${state.depthData.vpoc.volume.toLocaleString()} Vol · ${state.depthData.vpoc.isSupport ? 'Absorption Support' : 'Overhead Trap'})">🎯 VPOC</span>` : ''}
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

        // ── 4-PILLAR RIGOROUS CONFLUENCE EVALUATION ──
        // 1. Pillar 1: Market Breadth (Nifty 50 Stocks)
        const breadthWeightVal = parseFloat(unified?.breadth_weight || (signals.market_breadth?.advances ? ((signals.market_breadth.advances / (signals.market_breadth.advances + signals.market_breadth.declines)) * 100) : 50));
        const netPtsVal = unified?.net_points ? Math.abs(parseFloat(unified.net_points)) : 0;
        const reqBreadthWeight = parseFloat(alertSettings.pillarBreadthWeight || 60);
        const reqBreadthPts = parseFloat(alertSettings.pillarBreadthPts || 2.5);

        const isP1Bull = (breadthWeightVal >= reqBreadthWeight || signals.market_breadth?.signal === 'BULLISH') && (netPtsVal >= reqBreadthPts || netPtsVal === 0);
        const isP1Bear = (breadthWeightVal >= reqBreadthWeight || signals.market_breadth?.signal === 'BEARISH') && (netPtsVal >= reqBreadthPts || netPtsVal === 0);

        // 2. Pillar 2: Trending OI Flow & PCR Sentiment
        const livePcr = parseFloat(signals.oi_buildup?.pcr || 1.0);
        const reqBullPcr = parseFloat(alertSettings.pillarOiBullPcr || 1.05);
        const reqBearPcr = parseFloat(alertSettings.pillarOiBearPcr || 0.85);

        const isP2Bull = (livePcr >= reqBullPcr) || (signals.oi_buildup?.signal === 'BULLISH');
        const isP2Bear = (livePcr <= reqBearPcr) || (signals.oi_buildup?.signal === 'BEARISH');

        // 3. Pillar 3: Quant POP Engine
        const popVal = parseFloat(signals.quant_engine?.pop_pct || 70);
        const reqQuantPop = parseFloat(alertSettings.pillarQuantPop || 70);

        const isP3Bull = (popVal >= reqQuantPop) && (signals.quant_engine?.signal === 'BULLISH' || !signals.quant_engine?.signal || signals.quant_engine?.signal === 'NEUTRAL');
        const isP3Bear = (popVal >= reqQuantPop) && (signals.quant_engine?.signal === 'BEARISH' || !signals.quant_engine?.signal || signals.quant_engine?.signal === 'NEUTRAL');

        // 4. Pillar 4: 30-Level Deep Order Book & Microstructure
        const reqObi = parseFloat(alertSettings.pillarObiPct || 20);
        const isP4Bull = (d.obi30 >= reqObi || d.bidStack != null || (d.vpoc?.price > 0 && d.ltp >= d.vpoc.price && d.obi30 > 5));
        const isP4Bear = (d.obi30 <= -reqObi || d.askStack != null || (d.vpoc?.price > 0 && d.ltp <= d.vpoc.price && d.obi30 < -5));

        const bullPillarsCount = (isP1Bull ? 1 : 0) + (isP2Bull ? 1 : 0) + (isP3Bull ? 1 : 0) + (isP4Bull ? 1 : 0);
        const bearPillarsCount = (isP1Bear ? 1 : 0) + (isP2Bear ? 1 : 0) + (isP3Bear ? 1 : 0) + (isP4Bear ? 1 : 0);

        // Check for Counter-Trend Liquidity Absorption Wall
        const isUnifiedSell = unified?.has_call && unified.signal === 'SELL';
        const isUnifiedBuy = unified?.has_call && unified.signal === 'BUY';

        // ── ⚓ MASTER STRATEGIC ANCHOR: NIFTY 50 STOCK BREADTH ENGINE ──
        // When Nifty 50 stocks trigger a signal (e.g. 10:55 candle with >80% breadth),
        // it locks our macro direction until an opposite Nifty 50 call triggers.
        if (unified && unified.has_call) {
            const uDir = unified.signal === 'BUY' ? 'BULLISH' : (unified.signal === 'SELL' ? 'BEARISH' : 'NEUTRAL');
            if (uDir !== 'NEUTRAL') {
                if (!state.masterAnchor || state.masterAnchor.direction !== uDir || state.masterAnchor.candleTime !== unified.candle_time) {
                    state.masterAnchor = {
                        direction: uDir,
                        candleTime: unified.candle_time || '10:55',
                        strike: unified.strike || 23450,
                        weight: parseFloat(unified.breadth_weight || 80),
                        count: parseInt(unified.breadth_count || 40),
                        netPoints: parseFloat(unified.net_points || 0),
                        timestamp: Date.now()
                    };
                }
            }
        }

        const activeAnchor = state.masterAnchor;
        const anchorPill = document.getElementById('anchor-candle-pill');
        const anchorBadge = document.getElementById('anchor-status-pill');
        const apiCapPill = document.getElementById('api-trade-cap-pill');

        if (activeAnchor) {
            const isBearAnchor = (activeAnchor.direction === 'BEARISH');
            if (anchorPill) {
                anchorPill.textContent = `${activeAnchor.candleTime} Candle · ${activeAnchor.weight}% ${isBearAnchor ? 'Red' : 'Green'} (${activeAnchor.count} Stocks)`;
            }
            if (anchorBadge) {
                anchorBadge.textContent = isBearAnchor ? '▼ DOWNSIDE BIAS' : '▲ UPSIDE BIAS';
                anchorBadge.className = isBearAnchor
                    ? 'px-1.5 py-0.2 rounded text-[8.5px] font-black bg-rose-500 text-white uppercase shadow-xs'
                    : 'px-1.5 py-0.2 rounded text-[8.5px] font-black bg-emerald-500 text-slate-950 uppercase shadow-xs';
            }
        }

        // Daily trade execution limit check
        const totalExecutedToday = (state.cachedJournalLogs || []).filter(l => l.status === 'TARGET_HIT' || l.status === 'STOPPED_OUT' || l.status === 'ACTIVE').length;
        const maxTradesAllowed = parseInt(zeroClickSettings.maxDailyTrades || 3, 10);
        const isTradeCapReached = (totalExecutedToday >= maxTradesAllowed);

        if (apiCapPill) {
            apiCapPill.classList.remove('hidden');
            if (isTradeCapReached) {
                apiCapPill.textContent = `🔒 Daily API Cap: ${totalExecutedToday}/${maxTradesAllowed}`;
                apiCapPill.className = 'px-1.5 py-0.2 rounded text-[8.5px] font-bold font-mono bg-rose-500/20 text-rose-300 border border-rose-500/40';
            } else {
                apiCapPill.textContent = `API Cap: ${totalExecutedToday}/${maxTradesAllowed}`;
                apiCapPill.className = 'px-1.5 py-0.2 rounded text-[8.5px] font-bold font-mono bg-amber-500/20 text-amber-300 border border-amber-500/30';
            }
        }

        // Strict 4/4 Filter Check
        const strictMode = !!alertSettings.strictConfluence;
        const meetsStrictBull = (bullPillarsCount === 4);
        const meetsStrictBear = (bearPillarsCount === 4);

        // Nifty 50 Anchor Directional Lock:
        // Downside anchor strictly allows only Downside confluence and blocks opposite Upside;
        // Upside anchor strictly allows only Upside confluence and blocks opposite Downside!
        let isBuyQualified = false;
        let isSellQualified = false;

        if (activeAnchor && activeAnchor.direction === 'BEARISH') {
            isSellQualified = alertSettings.enableConfluence && (strictMode ? meetsStrictBear : (bearPillarsCount >= 3 || totalConfluence <= 38 || isUnifiedSell));
            isBuyQualified = false;
        } else if (activeAnchor && activeAnchor.direction === 'BULLISH') {
            isBuyQualified = alertSettings.enableConfluence && (strictMode ? meetsStrictBull : (bullPillarsCount >= 3 || totalConfluence >= 65 || isUnifiedBuy));
            isSellQualified = false;
        } else {
            isBuyQualified = alertSettings.enableConfluence && (strictMode ? meetsStrictBull : (totalConfluence >= 68 || isUnifiedBuy));
            isSellQualified = alertSettings.enableConfluence && (strictMode ? meetsStrictBear : (totalConfluence <= 35 || isUnifiedSell));
        }

        if (alertSettings.enableAbsorption && isUnifiedSell && d.heavyBidBlock && d.heavyBidBlock.level <= 3) {
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
                tradeRationale.textContent = `Unified ${unified?.candle_time || ''} SELL · Level ${d.heavyBidBlock.level} Bid Wall (${d.heavyBidBlock.qty.toLocaleString()} Qty) absorbing downside`;
            }
        } else if (alertSettings.enableAbsorption && isUnifiedBuy && d.heavyAskBlock && d.heavyAskBlock.level <= 3) {
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
                tradeRationale.textContent = `Unified ${unified?.candle_time || ''} BUY · Level ${d.heavyAskBlock.level} Ask Wall (${d.heavyAskBlock.qty.toLocaleString()} Qty) capping upside`;
            }
        } else if (isBuyQualified) {
            if (sigText) sigText.textContent = '🚀 HIGH CONFIDENCE BUY / LONG';
            if (sigPill) {
                sigPill.textContent = strictMode ? '🎯 4/4 FULL CONFLUENCE' : 'STRONG BUY';
                sigPill.className = 'px-2 py-0.5 rounded-full text-[10px] font-black bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950';
            }
            if (sigIcon) sigIcon.textContent = '🔥';
            if (heroBadge) {
                heroBadge.textContent = strictMode ? '🎯 4/4 CONFLUENCE' : 'STRONG BUY';
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
                const confTag = strictMode ? '🎯 4/4 Confirmed · ' : '';
                const anchorDesc = activeAnchor ? `⚓ NIFTY 50 ${activeAnchor.candleTime} Upside (${activeAnchor.weight}% Green) · ` : (unified?.has_call ? `Unified ${unified.candle_time} BUY (${unified.breadth_weight}% Green) · ` : '');
                const domDesc = d.bidStack ? `${d.bidStack.count}L Stack` : (d.heavyBidBlock ? `${d.heavyBidBlock.qty.toLocaleString()} Wall` : `OBI +${d.obi30}%`);
                tradeRationale.textContent = `${confTag}${anchorDesc}${domDesc} · Upside active`;
            }

            const currentBuySignalKey = `BUY_${unified?.candle_time || 'live'}_${unified?.strike || ''}_${instLabel}`;
            const isNewBuySignal = (state.lastConfluenceSignalKey !== currentBuySignalKey);
            state.lastConfluenceSignalKey = currentBuySignalKey;

            if (isNewBuySignal && (state.feedMode !== 'live' || isMarketHours())) {
                triggerAlertAudio('BUY');

                recordAlertEvent({
                    type: 'STRATEGY_BUY',
                    side: 'BUY',
                    title: strictMode ? '🎯 4/4 Full Confluence BUY Setup' : 'High-Confidence BUY Setup',
                    details: `${totalConfluence}% Confluence (${instLabel})`,
                    guidance: '⚡ Direction: GO LONG / BUY CALL'
                });
            }

        } else if (isSellQualified) {
            if (sigText) sigText.textContent = '🩸 HIGH CONFIDENCE SELL / SHORT';
            if (sigPill) {
                sigPill.textContent = strictMode ? '🎯 4/4 FULL CONFLUENCE' : 'STRONG SELL';
                sigPill.className = 'px-2 py-0.5 rounded-full text-[10px] font-black bg-gradient-to-r from-rose-500 to-amber-500 text-white';
            }
            if (sigIcon) sigIcon.textContent = '🩸';
            if (heroBadge) {
                heroBadge.textContent = strictMode ? '🎯 4/4 CONFLUENCE' : 'STRONG SELL';
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
                const confTag = strictMode ? '🎯 4/4 Confirmed · ' : '';
                const anchorDesc = activeAnchor ? `⚓ NIFTY 50 ${activeAnchor.candleTime} Downside (${activeAnchor.weight}% Red) · ` : (unified?.has_call ? `Unified ${unified.candle_time} SELL (${unified.breadth_weight}% Red) · ` : '');
                const domDesc = d.askStack ? `${d.askStack.count}L Stack` : (d.heavyAskBlock ? `${d.heavyAskBlock.qty.toLocaleString()} Wall` : `OBI ${d.obi30}%`);
                tradeRationale.textContent = `${confTag}${anchorDesc}${domDesc} · Downside active`;
            }

            const currentSellSignalKey = `SELL_${unified?.candle_time || 'live'}_${unified?.strike || ''}_${instLabel}`;
            const isNewSellSignal = (state.lastConfluenceSignalKey !== currentSellSignalKey);
            state.lastConfluenceSignalKey = currentSellSignalKey;

            if (isNewSellSignal && (state.feedMode !== 'live' || isMarketHours())) {
                triggerAlertAudio('SELL');

                recordAlertEvent({
                    type: 'STRATEGY_SELL',
                    side: 'SELL',
                    title: strictMode ? '🎯 4/4 Full Confluence SELL Setup' : 'High-Confidence SELL Setup',
                    details: `${totalConfluence}% Confluence (${instLabel})`,
                    guidance: '⚡ Direction: GO SHORT / BUY PUT'
                });
            }

        } else {
            state.lastConfluenceSignalKey = 'NEUTRAL';
            if (sigText) {
                if (!alertSettings.enableConfluence) {
                    sigText.textContent = '⏸️ CONFLUENCE ALERTS MUTED';
                } else if (strictMode) {
                    const maxPillars = Math.max(bullPillarsCount, bearPillarsCount);
                    sigText.textContent = `⏸️ WAITING FOR STRICT 4/4 CONFLUENCE (${maxPillars}/4 Aligned)`;
                } else {
                    sigText.textContent = '⏸️ BALANCED / WAIT FOR SIGNAL';
                }
            }
            if (sigPill) {
                if (!alertSettings.enableConfluence) {
                    sigPill.textContent = 'MUTED';
                    sigPill.className = 'px-2 py-0.5 rounded-full text-[10px] font-black bg-slate-800 text-slate-400 border border-slate-700';
                } else if (strictMode) {
                    sigPill.textContent = `STRICT ${Math.max(bullPillarsCount, bearPillarsCount)}/4 ACTIVE`;
                    sigPill.className = 'px-2 py-0.5 rounded-full text-[10px] font-black bg-indigo-500/20 text-indigo-300 border border-indigo-500/50';
                } else {
                    sigPill.textContent = 'NEUTRAL';
                    sigPill.className = 'px-2 py-0.5 rounded-full text-[10px] font-black bg-slate-800 text-slate-400 border border-slate-700';
                }
            }
            if (sigIcon) sigIcon.textContent = strictMode ? '🎯' : '⚖️';
            if (heroBadge) {
                heroBadge.textContent = strictMode ? `STRICT 4/4 (${Math.max(bullPillarsCount, bearPillarsCount)}/4)` : (!alertSettings.enableConfluence ? 'ALERTS OFF' : 'NEUTRAL CHOP');
                heroBadge.className = 'px-1 py-0.2 rounded text-[9px] font-bold bg-slate-800 text-slate-400';
            }

            if (tradeInst) tradeInst.textContent = targetName;
            if (tradeEntry) tradeEntry.textContent = `₹${d.ltp.toFixed(2)}`;
            if (tradeSl) tradeSl.textContent = `Wait for Breakout`;
            if (tradeTarget) tradeTarget.textContent = `Range Bound`;
            if (tradeRationale) {
                if (!alertSettings.enableConfluence) {
                    tradeRationale.textContent = 'Confluence alerts paused in settings';
                } else if (strictMode) {
                    const missing = [];
                    if (!isP1Bull && !isP1Bear) missing.push('Breadth');
                    if (!isP2Bull && !isP2Bear) missing.push('OI PCR');
                    if (!isP3Bull && !isP3Bear) missing.push('Quant POP');
                    if (!isP4Bull && !isP4Bear) missing.push('30D Book');
                    tradeRationale.textContent = `Strict 4/4 filter active · Pending alignment: ${missing.join(', ') || 'Mixed divergence'} · Stand aside`;
                } else {
                    tradeRationale.textContent = `Order book balanced (OBI: ${d.obi30}%) · Stand aside`;
                }
            }
        }

        // ── CALCULATE INSTANT OPTION STRIKE SETUP (Options Trader Mode) ──
        const elOptBadge  = document.getElementById('opt-rec-badge');
        const elOptStrike = document.getElementById('opt-rec-strike');
        const elOptEntry  = document.getElementById('opt-rec-entry');
        const elOptSlTgt  = document.getElementById('opt-rec-sltgt');

        const atmRounded = Math.round(d.ltp / 50) * 50;
        let recommendedStrike = atmRounded;
        let estPremium = 125;
        let optSymbol = `NIFTY ${atmRounded} CE`;
        let optAction = 'WAIT';
        let optEntryStr = '--';
        let optSlStr = '--';
        let optTgtStr = '--';

        const isOptionSelling = (zeroClickSettings.tradeStyle !== 'OPTION_BUYING'); // Default to Option Selling as requested by user

        if (isBuyQualified) {
            recommendedStrike = isOptionSelling ? (atmRounded + 50) : atmRounded;
            const optType = isOptionSelling ? 'PE' : 'CE';
            optAction = isOptionSelling ? 'SELL PUT (PE ITM)' : 'BUY CALL (CE)';

            if (state.contracts?.strikes?.[recommendedStrike]?.[optType]?.trading_symbol) {
                optSymbol = state.contracts.strikes[recommendedStrike][optType].trading_symbol;
            } else if (optType === 'CE' && state.contracts?.atm_ce?.trading_symbol) {
                optSymbol = state.contracts.atm_ce.trading_symbol;
            } else if (optType === 'PE' && state.contracts?.atm_pe?.trading_symbol) {
                optSymbol = state.contracts.atm_pe.trading_symbol;
            } else {
                optSymbol = `NIFTY ${recommendedStrike} ${optType}`;
            }

            estPremium = isOptionSelling ? Math.max(90, Math.round(135 + ((recommendedStrike - d.ltp) * 0.55))) : Math.max(75, Math.round(115 + (d.ltp - recommendedStrike) * 0.65));
            optEntryStr = isOptionSelling ? `Collect ₹${estPremium}` : `₹${estPremium - 3} - ₹${estPremium + 2}`;
            optSlStr = isOptionSelling ? `₹${estPremium + 28}` : `₹${Math.max(10, estPremium - 18)}`;
            optTgtStr = isOptionSelling ? `₹${Math.max(15, estPremium - 75)}` : `₹${estPremium + 42}`;

            if (elOptBadge) {
                elOptBadge.textContent = isOptionSelling ? '👑 SELL PUT (ITM)' : 'BUY CALL (CE)';
                elOptBadge.className = 'px-1.5 py-0.2 rounded text-[9px] font-black bg-emerald-500 text-slate-950 uppercase';
            }
            if (elOptStrike) elOptStrike.textContent = optSymbol;
            if (elOptEntry) elOptEntry.textContent = optEntryStr;
            if (elOptSlTgt) elOptSlTgt.textContent = `SL: ${optSlStr} | TGT: ${optTgtStr}`;

        } else if (isSellQualified) {
            recommendedStrike = isOptionSelling ? (atmRounded - 50) : atmRounded;
            const optType = isOptionSelling ? 'CE' : 'PE';
            optAction = isOptionSelling ? 'SELL CALL (CE ITM)' : 'BUY PUT (PE)';

            if (state.contracts?.strikes?.[recommendedStrike]?.[optType]?.trading_symbol) {
                optSymbol = state.contracts.strikes[recommendedStrike][optType].trading_symbol;
            } else if (optType === 'PE' && state.contracts?.atm_pe?.trading_symbol) {
                optSymbol = state.contracts.atm_pe.trading_symbol;
            } else if (optType === 'CE' && state.contracts?.atm_ce?.trading_symbol) {
                optSymbol = state.contracts.atm_ce.trading_symbol;
            } else {
                optSymbol = `NIFTY ${recommendedStrike} ${optType}`;
            }

            estPremium = isOptionSelling ? Math.max(90, Math.round(135 + ((d.ltp - recommendedStrike) * 0.55))) : Math.max(75, Math.round(115 + (recommendedStrike - d.ltp) * 0.65));
            optEntryStr = isOptionSelling ? `Collect ₹${estPremium}` : `₹${estPremium - 3} - ₹${estPremium + 2}`;
            optSlStr = isOptionSelling ? `₹${estPremium + 28}` : `₹${Math.max(10, estPremium - 18)}`;
            optTgtStr = isOptionSelling ? `₹${Math.max(15, estPremium - 75)}` : `₹${estPremium + 42}`;

            if (elOptBadge) {
                elOptBadge.textContent = isOptionSelling ? '👑 SELL CALL (ITM)' : 'BUY PUT (PE)';
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
        if (alertSettings.enableConfluence && optAction !== 'WAIT' && (alertSettings.autoLog || zeroClickSettings.autoLog || zeroClickSettings.forwardToZeroClick)) {
            if (state.feedMode !== 'live' || isMarketHours()) {
                const journalSignalKey = `${optAction}_${recommendedStrike}_${unified?.candle_time || 'live'}`;
                if (state.lastLoggedJournalSignalKey !== journalSignalKey) {
                    state.lastLoggedJournalSignalKey = journalSignalKey;
                    const isBuy = isBuyQualified;
                    const alignedCount = isBuy ? bullPillarsCount : bearPillarsCount;
                    logOpportunityCall({
                        action: optAction,
                        symbol: optSymbol,
                        strike: recommendedStrike,
                        optType: isOptionSelling ? (isBuy ? 'PE' : 'CE') : (isBuy ? 'CE' : 'PE'),
                        slPrice: isBuy ? (d.ltp - 18) : (d.ltp + 18),
                        targetPrice: isBuy ? (d.ltp + 42) : (d.ltp - 42),
                        optEstEntry: estPremium,
                        optEstSl: isOptionSelling ? estPremium + 28 : Math.max(10, estPremium - 18),
                        optEstTgt: isOptionSelling ? Math.max(15, estPremium - 75) : estPremium + 42,
                        confluence: totalConfluence,
                        alignedPillars: alignedCount,
                        rationale: tradeRationale ? tradeRationale.textContent.trim() : 'Confluence signal'
                    });
                }
            }
        }
    }

    function triggerAlertAudio(type) {
        if (!state.soundEnabled || !alertSettings.soundEnabled || !alertSettings.soundStrategy) return;
        const now = Date.now();
        if (now - state.lastAlertTime < 30000) return;
        state.lastAlertTime = now;

        playSynthesizedAlert(alertSettings.soundProfile || 'crystal', type);
    }

    // ── 15. JOURNAL LOGGING & OUTCOME VERIFICATION ENGINE ──
    async function logOpportunityCall(tradeSetup) {
        if (!alertSettings.autoLog && !zeroClickSettings.autoLog && !zeroClickSettings.forwardToZeroClick) return;
        if (state.feedMode === 'live' && !isMarketHours()) return;
        const now = Date.now();
        if (now - state.lastSignalLogTime < 15000) return; // 15s debounce between calls
        state.lastSignalLogTime = now;

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
            
            const entryPrice = parseFloat(tradeSetup.entryPrice || state.depthData.ltp);
            const isBuy = tradeSetup.action.includes('CALL') || tradeSetup.action.includes('BUY') || (tradeSetup.signalType && tradeSetup.signalType.includes('BUY')) || (tradeSetup.action.includes('SELL PUT'));

            let slPrice = parseFloat(tradeSetup.slPrice);
            let targetPrice = parseFloat(tradeSetup.targetPrice);

            // Dynamically restructure SL and Target if custom 0Click settings are active
            if (zeroClickSettings.forwardToZeroClick) {
                const buf = parseFloat(zeroClickSettings.slBufferPts) || 15;
                const tp1 = parseFloat(zeroClickSettings.targetTp1Pts) || 100;

                if (zeroClickSettings.slMode === 'fixed_pts') {
                    slPrice = isBuy ? (entryPrice - buf) : (entryPrice + buf);
                } else if (tradeSetup.slPrice) {
                    slPrice = isBuy ? Math.min(entryPrice - 5, parseFloat(tradeSetup.slPrice) - buf) : Math.max(entryPrice + 5, parseFloat(tradeSetup.slPrice) + buf);
                } else {
                    slPrice = isBuy ? (entryPrice - buf) : (entryPrice + buf);
                }

                if (tp1 > 0) {
                    targetPrice = isBuy ? (entryPrice + tp1) : (entryPrice - tp1);
                }
            }

            // Capture complete 4-5 multi-engine factors snapshot
            const sigs = state.strategySignals?.factors || {};
            const d = state.depthData;
            const factorsSnapshot = tradeSetup.factors || {
                breadth: {
                    name: 'NIFTY 50 Stocks Breadth',
                    signal: sigs.market_breadth?.signal || 'NEUTRAL',
                    score: sigs.market_breadth?.score || 50,
                    advances: sigs.market_breadth?.advances ?? 25,
                    declines: sigs.market_breadth?.declines ?? 25,
                    details: sigs.market_breadth?.details || 'NIFTY 50 stock weightage'
                },
                oi_flow: {
                    name: 'Options Flow & Buildup',
                    signal: sigs.oi_buildup?.signal || 'NEUTRAL',
                    score: sigs.oi_buildup?.score || 50,
                    pcr: sigs.oi_buildup?.pcr || 1.0,
                    details: sigs.oi_buildup?.details || 'Call/Put writing flow'
                },
                trending_oi: {
                    name: 'Trending OI & Quant Engine',
                    signal: sigs.quant_engine?.signal || 'NEUTRAL',
                    score: sigs.quant_engine?.score || 50,
                    pop_pct: sigs.quant_engine?.pop_pct || 50,
                    details: sigs.quant_engine?.details || 'Quant probability'
                },
                dom_depth: {
                    name: '30-Level Order Book (DOM)',
                    signal: d.obi30 > 12 ? 'BULLISH' : (d.obi30 < -12 ? 'BEARISH' : 'NEUTRAL'),
                    score: Math.round(50 + (d.obi30 * 0.45)),
                    obi: d.obi30,
                    near_obi: d.obi5,
                    mid_obi: d.obi10,
                    drift: parseFloat((d.microPrice - d.ltp).toFixed(2)),
                    stack: d.bidStack ? `${d.bidStack.count}L Bull Stack` : (d.askStack ? `${d.askStack.count}L Bear Stack` : null),
                    wall: d.heavyBidBlock ? `Bid Wall (${d.heavyBidBlock.qty.toLocaleString()})` : (d.heavyAskBlock ? `Ask Wall (${d.heavyAskBlock.qty.toLocaleString()})` : null),
                    vpoc_price: d.vpoc?.price || null,
                    vpoc_volume: d.vpoc?.volume || 0,
                    vpoc_role: d.vpoc?.isSupport ? 'Absorption Support' : 'Trap Resistance'
                },
                meta: {
                    strict_mode: !!alertSettings.strictConfluence,
                    aligned_pillars: tradeSetup.alignedPillars || 4
                }
            };

            // Enforce Daily Trade Cap for Broker / 0Click API Execution
            const totalExecutedToday = (state.cachedJournalLogs || []).filter(l => l.status === 'TARGET_HIT' || l.status === 'STOPPED_OUT' || l.status === 'ACTIVE').length;
            const maxTradesAllowed = parseInt(zeroClickSettings.maxDailyTrades || 3, 10);
            const isTradeCapReached = (totalExecutedToday >= maxTradesAllowed);

            const payload = {
                mode: state.feedMode,
                symbol: state.symbol || 'NIFTY',
                signal_type: tradeSetup.signalType || (isBuy ? 'BUY_CE' : 'SELL_PE'),
                action: tradeSetup.action,
                instrument: tradeSetup.symbol || state.activeInstrumentKey || 'NIFTY FUT',
                spot_price: state.depthData.ltp,
                future_price: state.depthData.ltp,
                entry_price: entryPrice,
                stop_loss: parseFloat(slPrice.toFixed(2)),
                target_price: parseFloat(targetPrice.toFixed(2)),
                opt_strike: tradeSetup.strike || Math.round(state.depthData.ltp / 50) * 50,
                opt_type: tradeSetup.optType || (tradeSetup.action.includes('PE') ? 'PE' : 'CE'),
                opt_entry: tradeSetup.optEstEntry || 135,
                opt_sl: tradeSetup.optEstSl || 115,
                opt_target: tradeSetup.optEstTgt || 175,
                confluence_score: tradeSetup.confluence || 50,
                obi_pct: state.depthData.obi30,
                micro_drift: parseFloat((state.depthData.microPrice - state.depthData.ltp).toFixed(2)),
                trigger_reason: isTradeCapReached 
                    ? `${tradeSetup.rationale || 'Liquidity event'} · [Daily API Cap Reached: ${totalExecutedToday}/${maxTradesAllowed} - Signal Logged Only]`
                    : (tradeSetup.rationale || 'Liquidity event'),

                // Multi-engine snapshot
                factors: factorsSnapshot,

                // 0Click.in Trade Webhook Integration fields (Restricted by user daily cap)
                dispatch_webhook: !!zeroClickSettings.forwardToZeroClick && !isTradeCapReached,
                max_daily_trades: maxTradesAllowed,
                webhook_url: zeroClickSettings.webhookUrl || 'https://0click.in/api/webhook/tradingview',
                webhook_token: zeroClickSettings.webhookToken || '',
                execution_mode: zeroClickSettings.mode || 'SIMULATION',
                lots: parseInt(zeroClickSettings.lots || 1, 10),
                trade_style: zeroClickSettings.tradeStyle || 'OPTION_SELLING'
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

                if (res.webhook_result) {
                    console.log('⚡ 0Click Webhook Dispatched:', res.webhook_result);
                }
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
            const isBuy = (sig.signal_type === 'BUY_CE' || sig.action.includes('CALL') || sig.action.includes('BUY') || sig.action.includes('SELL PUT'));
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

    window.setJournalFilter = function(filter) {
        state.journalFilter = filter || 'ALL';
        updateJournalFilterUI();
        renderJournalLogsUI();
    };

    function updateJournalFilterUI() {
        const f = state.journalFilter || 'ALL';
        const btnAll = document.getElementById('btn-jfilter-all');
        const btnHit = document.getElementById('btn-jfilter-hit');
        const btnStop = document.getElementById('btn-jfilter-stop');
        const btnActive = document.getElementById('btn-jfilter-active');
        const cardHit = document.getElementById('card-filter-hit');
        const cardStop = document.getElementById('card-filter-stop');

        if (btnAll) btnAll.className = 'px-2 py-0.5 rounded text-[10px] font-bold transition cursor-pointer ' + (f === 'ALL' ? 'bg-indigo-600 text-white shadow-xs ring-1 ring-indigo-400/50' : 'bg-slate-900 text-slate-400 hover:text-white');
        if (btnHit) btnHit.className = 'px-2 py-0.5 rounded text-[10px] font-bold transition cursor-pointer ' + (f === 'TARGET_HIT' ? 'bg-emerald-600 text-white font-black shadow-xs ring-1 ring-emerald-400/50' : 'bg-slate-900 text-slate-400 hover:text-emerald-300');
        if (btnStop) btnStop.className = 'px-2 py-0.5 rounded text-[10px] font-bold transition cursor-pointer ' + (f === 'STOPPED_OUT' ? 'bg-rose-600 text-white font-black shadow-xs ring-1 ring-rose-400/50' : 'bg-slate-900 text-slate-400 hover:text-rose-300');
        if (btnActive) btnActive.className = 'px-2 py-0.5 rounded text-[10px] font-bold transition cursor-pointer ' + (f === 'ACTIVE' ? 'bg-amber-600 text-white font-black shadow-xs ring-1 ring-amber-400/50' : 'bg-slate-900 text-slate-400 hover:text-amber-300');

        if (cardHit) {
            if (f === 'TARGET_HIT') {
                cardHit.classList.add('bg-emerald-500/30', 'ring-1', 'ring-emerald-400');
            } else {
                cardHit.classList.remove('bg-emerald-500/30', 'ring-1', 'ring-emerald-400');
            }
        }
        if (cardStop) {
            if (f === 'STOPPED_OUT') {
                cardStop.classList.add('bg-rose-500/30', 'ring-1', 'ring-rose-400');
            } else {
                cardStop.classList.remove('bg-rose-500/30', 'ring-1', 'ring-rose-400');
            }
        }
    }

    function renderJournalLogsUI() {
        const container = document.getElementById('journal-logs-container');
        if (!container) return;

        const allLogs = state.cachedJournalLogs || [];
        const allCount = allLogs.length;
        const hitCount = allLogs.filter(l => l.status === 'TARGET_HIT').length;
        const stopCount = allLogs.filter(l => l.status === 'STOPPED_OUT').length;
        const activeCount = allLogs.filter(l => l.status === 'ACTIVE').length;

        const cAll = document.getElementById('count-jfilter-all');
        const cHit = document.getElementById('count-jfilter-hit');
        const cStop = document.getElementById('count-jfilter-stop');
        const cActive = document.getElementById('count-jfilter-active');
        if (cAll) cAll.textContent = allCount;
        if (cHit) cHit.textContent = hitCount;
        if (cStop) cStop.textContent = stopCount;
        if (cActive) cActive.textContent = activeCount;

        const f = state.journalFilter || 'ALL';
        let filtered = allLogs;
        if (f === 'TARGET_HIT') filtered = allLogs.filter(l => l.status === 'TARGET_HIT');
        else if (f === 'STOPPED_OUT') filtered = allLogs.filter(l => l.status === 'STOPPED_OUT');
        else if (f === 'ACTIVE') filtered = allLogs.filter(l => l.status === 'ACTIVE');

        if (filtered.length === 0) {
            let emptyMsg = 'No trade signals logged yet today. Waiting for setup triggers...';
            if (f === 'TARGET_HIT') emptyMsg = 'No Target Hits recorded yet today.';
            else if (f === 'STOPPED_OUT') emptyMsg = 'No Stopped Out trades recorded yet today.';
            else if (f === 'ACTIVE') emptyMsg = 'No Active setups running right now.';
            container.innerHTML = `<div class="text-center py-6 text-slate-500 text-[10px]">${emptyMsg}</div>`;
            return;
        }

        let html = '';
        filtered.forEach(l => {
            const isBuy = l.action.includes('CALL') || l.action.includes('BUY') || (l.signal_type && l.signal_type.includes('BUY')) || l.action.includes('SELL PUT');
            
            let statusBadge = '';
            if (l.status === 'TARGET_HIT') {
                statusBadge = `<span class="px-2 py-0.5 rounded text-[9.5px] font-black bg-emerald-500 text-slate-950 uppercase shadow-xs">TARGET HIT (+${l.pnl_points} pts)</span>`;
            } else if (l.status === 'STOPPED_OUT') {
                statusBadge = `<span class="px-2 py-0.5 rounded text-[9.5px] font-black bg-rose-500 text-white uppercase shadow-xs">STOPPED (${l.pnl_points} pts)</span>`;
            } else {
                statusBadge = `<span class="px-2 py-0.5 rounded text-[9.5px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 animate-pulse">ACTIVE (${l.max_favorable_pts > 0 ? '+' + l.max_favorable_pts : '0'} MFE)</span>`;
            }

            // Parse Notes (supports JSON format and legacy strings)
            let parsedNotes = {};
            try {
                parsedNotes = typeof l.notes === 'string' ? JSON.parse(l.notes) : (l.notes || {});
            } catch(e) {
                parsedNotes = { webhook: l.notes };
            }

            const webhookStatus = parsedNotes.webhook || (typeof l.notes === 'string' && l.notes.includes('0Click:') ? l.notes : null);
            const tradeStyle = parsedNotes.trade_style || (l.action.includes('SELL') ? 'OPTION_SELLING' : 'OPTION_BUYING');
            const factors = parsedNotes.factors || null;

            let webhookBadge = '';
            if (webhookStatus) {
                const isSent = webhookStatus.includes('SENT');
                webhookBadge = isSent
                    ? `<span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-purple-500/20 text-purple-300 border border-purple-500/40" title="${webhookStatus}">⚡ 0CLICK: SENT</span>`
                    : `<span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40" title="${webhookStatus}">⚡ 0CLICK: ERR</span>`;
            }

            const actionBadgeText = l.action.includes('WALL') 
                ? (isBuy ? '🛡️ BUY WALL' : '🚧 SELL WALL') 
                : (l.action.includes('STACK') 
                    ? (isBuy ? '🌊 BUY STACK' : '🌊 SELL STACK') 
                    : (isBuy ? '▲ CALL / LONG' : '▼ PUT / SHORT'));

            const styleBadge = tradeStyle === 'OPTION_SELLING'
                ? `<span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30">👑 SELL (DECAY)</span>`
                : `<span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-sky-500/15 text-sky-300 border border-sky-500/30">🚀 BUY (MOMENTUM)</span>`;

            // Evaluate multi-engine alignment count and format 4-pillar scorecard
            let factorGridHtml = '';
            let alignmentBadge = '';
            
            const targetDir = isBuy ? 'BULLISH' : 'BEARISH';
            const bSig = factors?.breadth?.signal || (isBuy ? 'BULLISH' : 'BEARISH');
            const oSig = factors?.oi_flow?.signal || (isBuy ? 'BULLISH' : 'BEARISH');
            const qSig = factors?.trending_oi?.signal || (isBuy ? 'BULLISH' : 'BEARISH');
            const dSig = factors?.dom_depth?.signal || (l.obi_pct > 10 ? 'BULLISH' : (l.obi_pct < -10 ? 'BEARISH' : 'NEUTRAL'));

            const engines = [
                { label: '🏢 Stocks', sig: bSig },
                { label: '📊 Options', sig: oSig },
                { label: '📈 Trending OI', sig: qSig },
                { label: '🌊 30-Depth Book', sig: dSig }
            ];

            let alignedCount = 0;
            engines.forEach(e => {
                if (e.sig === targetDir) alignedCount++;
            });

            if (alignedCount === 4) {
                alignmentBadge = `<span class="px-2 py-0.5 rounded text-[9.5px] font-black bg-emerald-500/30 text-emerald-300 border border-emerald-400 shadow-[0_0_8px_rgba(16,185,129,0.3)] animate-pulse">🎯 4/4 FULL CONFLUENCE</span>`;
            } else if (alignedCount === 3) {
                alignmentBadge = `<span class="px-2 py-0.5 rounded text-[9.5px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/40">⚡ 3/4 ALIGNMENT</span>`;
            } else {
                alignmentBadge = `<span class="px-2 py-0.5 rounded text-[9.5px] font-mono text-slate-400 bg-slate-900 border border-slate-800">${alignedCount}/4 Align</span>`;
            }

            // Extract exact values for the 4 pillars
            const bVal = (factors?.breadth?.advances !== undefined && factors?.breadth?.declines !== undefined)
                ? `${factors.breadth.advances}A / ${factors.breadth.declines}D`
                : (factors?.breadth?.score ? `${factors.breadth.score}% Score` : (isBuy ? '34A / 16D' : '16A / 34D'));

            const oVal = factors?.oi_flow?.pcr 
                ? `${Number(factors.oi_flow.pcr).toFixed(2)} PCR`
                : (factors?.oi_flow?.score ? `${factors.oi_flow.score}% Flow` : (isBuy ? '1.24 PCR' : '0.78 PCR'));

            const qVal = factors?.trending_oi?.pop_pct 
                ? `${factors.trending_oi.pop_pct}% POP`
                : (factors?.trending_oi?.score ? `${factors.trending_oi.score}% Quant` : (isBuy ? '68% POP' : '38% POP'));

            const dVal = factors?.dom_depth?.obi !== undefined 
                ? `${factors.dom_depth.obi > 0 ? '+' : ''}${factors.dom_depth.obi}% OBI`
                : (l.obi_pct !== undefined && l.obi_pct !== null ? `${l.obi_pct > 0 ? '+' : ''}${l.obi_pct}% OBI` : (isBuy ? '+14.6% OBI' : '-16.2% OBI'));

            const getPillarSigBadge = (sig) => {
                if (sig === 'BULLISH') return '<span class="text-emerald-400 font-black text-[9.5px]">▲ BULL</span>';
                if (sig === 'BEARISH') return '<span class="text-rose-400 font-black text-[9.5px]">▼ BEAR</span>';
                return '<span class="text-slate-400 font-bold text-[9.5px]">— NEUT</span>';
            };

            factorGridHtml = `
            <div class="grid grid-cols-4 gap-1.5 pt-1.5 border-t border-slate-900/90 text-xs font-mono">
                <div class="p-1.5 rounded-lg bg-slate-900/90 border border-slate-800 text-center space-y-0.5">
                    <span class="text-slate-400 block text-[9px] font-bold font-sans truncate">🏢 NIFTY 50</span>
                    <div class="font-mono font-black text-amber-200 text-[10px] truncate leading-tight">${bVal}</div>
                    <div>${getPillarSigBadge(bSig)}</div>
                </div>
                <div class="p-1.5 rounded-lg bg-slate-900/90 border border-slate-800 text-center space-y-0.5">
                    <span class="text-slate-400 block text-[9px] font-bold font-sans truncate">📊 Opt Flow</span>
                    <div class="font-mono font-black text-cyan-300 text-[10px] truncate leading-tight">${oVal}</div>
                    <div>${getPillarSigBadge(oSig)}</div>
                </div>
                <div class="p-1.5 rounded-lg bg-slate-900/90 border border-slate-800 text-center space-y-0.5">
                    <span class="text-slate-400 block text-[9px] font-bold font-sans truncate">📈 Trend OI</span>
                    <div class="font-mono font-black text-indigo-300 text-[10px] truncate leading-tight">${qVal}</div>
                    <div>${getPillarSigBadge(qSig)}</div>
                </div>
                <div class="p-1.5 rounded-lg bg-slate-900/90 border border-slate-800 text-center space-y-0.5">
                    <span class="text-slate-400 block text-[9px] font-bold font-sans truncate">🌊 30D Book</span>
                    <div class="font-mono font-black text-amber-300 text-[10px] truncate leading-tight">${dVal}</div>
                    <div>${getPillarSigBadge(dSig)}</div>
                </div>
            </div>
            `;

            // Recommended Option Strike badge
            let optContractSnippet = '';
            if (l.opt_strike) {
                optContractSnippet = `
                <div class="flex items-center justify-between text-[10px] font-mono px-2 py-1 rounded-lg bg-slate-900/80 border border-slate-800/90 text-slate-300 flex-wrap gap-1">
                    <span class="text-amber-300 font-black truncate">⚡ Option: NIFTY ${l.opt_strike} ${l.opt_type}</span>
                    <span class="text-slate-300 text-[9.5px]">Entry: <strong class="text-white">₹${l.opt_entry || '--'}</strong> | SL: <strong class="text-rose-400">₹${l.opt_sl || '--'}</strong> | TGT: <strong class="text-emerald-400">₹${l.opt_target || '--'}</strong></span>
                </div>
                `;
            }

            html += `
            <div class="p-2.5 rounded-xl bg-slate-950 border ${isBuy ? 'border-emerald-800/50 shadow-emerald-950/20' : 'border-rose-800/50 shadow-rose-950/20'} text-xs space-y-2 shadow-lg">
                <div class="flex items-center justify-between gap-1 flex-wrap">
                    <div class="flex items-center gap-1.5 font-mono flex-wrap">
                        <span class="text-slate-400 text-[10.5px]">${l.signal_time}</span>
                        <span class="px-2 py-0.5 rounded font-black text-[9.5px] ${isBuy ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-rose-500/20 text-rose-300 border border-rose-500/40'}">
                            ${actionBadgeText}
                        </span>
                        ${styleBadge}
                        <span class="font-black text-white text-xs sm:text-[13px] truncate max-w-[150px]">${l.instrument}</span>
                    </div>
                    <div class="flex items-center gap-1">
                        ${alignmentBadge}
                        ${webhookBadge}
                        ${statusBadge}
                    </div>
                </div>

                ${optContractSnippet}

                <div class="text-[10.5px] text-amber-300/95 font-sans leading-tight">${l.trigger_reason || l.action}</div>
                
                <div class="flex items-center justify-between text-[10.5px] text-slate-300 font-mono pt-1 border-t border-slate-900 flex-wrap gap-1">
                    <span>Entry: <strong class="text-white">₹${l.entry_price}</strong></span>
                    <span class="text-rose-400 font-semibold">SL: ₹${l.stop_loss}</span>
                    <span class="text-emerald-400 font-semibold">TGT: ₹${l.target_price}</span>
                    <span class="text-amber-300 font-bold bg-slate-900 px-1.5 py-0.2 rounded border border-slate-800">${l.confluence_score}% Conf</span>
                </div>

                ${factorGridHtml}
            </div>
            `;
        });

        container.innerHTML = html;
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
                    winRateEl.className = s.win_rate >= 60 ? 'font-black text-emerald-400 text-sm sm:text-base' : (s.win_rate <= 40 ? 'font-black text-rose-400 text-sm sm:text-base' : 'font-black text-amber-300 text-sm sm:text-base');
                }
                if (tgtEl) tgtEl.textContent = s.target_hit;
                if (stpEl) stpEl.textContent = s.stopped_out;
                if (pnlEl) {
                    pnlEl.textContent = `${s.net_pnl_pts >= 0 ? '+' : ''}${s.net_pnl_pts} pts`;
                    pnlEl.className = s.net_pnl_pts > 0 ? 'font-black text-emerald-400 text-sm sm:text-base font-mono' : (s.net_pnl_pts < 0 ? 'font-black text-rose-400 text-sm sm:text-base font-mono' : 'font-black text-slate-300 text-sm sm:text-base font-mono');
                }

                state.cachedJournalLogs = res.logs || [];
                state.activeLoggedSignals = (res.logs || []).filter(l => l.status === 'ACTIVE');

                updateJournalFilterUI();
                renderJournalLogsUI();
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
        let simVtt = 125000;

        state.simInterval = setInterval(() => {
            tickCount++;
            simVtt += Math.floor(60 + Math.random() * 240);
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

            applyNewDepthState(bids, asks, ltp, atp, simVtt, tbq, tsq);

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

                // Reset Volume Profile & VPOC for newly selected instrument
                state.volumeProfile = {};
                state.lastVtt = 0;
                state.depthData.vpoc = { price: 0, volume: 0, isSupport: true, ordersEstimate: 0 };

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
