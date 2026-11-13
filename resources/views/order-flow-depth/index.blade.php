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

                {{-- Cumulative EOD Institutional Report Button --}}
                <button id="btn-open-eod-report" onclick="openCumulativeReportModal()" title="View Institutional End-of-Day Cumulative Report & Tomorrow's S/R Levels" class="px-2.5 py-0.5 rounded-lg bg-gradient-to-r from-indigo-950 via-slate-900 to-amber-950 border border-amber-500/50 hover:border-amber-400 text-amber-300 hover:text-white transition text-[11px] flex items-center gap-1.5 font-bold shadow-md cursor-pointer hover:shadow-amber-500/20 active:scale-95">
                    <span class="text-xs">📊</span>
                    <span class="hidden sm:inline">EOD Report</span>
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                </button>

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
                        <span id="legend-market-tag" class="px-1.5 py-0.5 rounded bg-emerald-500/20 border border-emerald-400/50 text-emerald-300 font-black text-[9px] whitespace-nowrap" title="Heavy Aggressive Market Order Sweep">💥 Trade</span>
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

                    {{-- Dynamic Order Flow Execution Capsule: Lifting the Offer vs Hitting the Bid --}}
                    <div id="banner-tape-capsule" class="px-2 py-0.5 rounded text-[9.5px] font-black font-mono tracking-wider flex items-center gap-1.5 bg-slate-950 text-slate-400 border border-slate-800 shrink-0 shadow-xs transition-all duration-150 cursor-help" title="Tape Execution State: Real-time aggressive market orders vs resting liquidity">
                        <span id="tape-capsule-icon">⚖️</span>
                        <span id="tape-capsule-text">BALANCED AUCTION</span>
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
            <div id="top-recommendation-card" class="bg-gradient-to-br from-slate-900 via-slate-900 to-slate-950 border border-slate-800 rounded-xl p-3 shadow-xl relative overflow-hidden transition-all duration-200">
                <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <span id="signal-badge-icon" class="text-base sm:text-lg">🎯</span>
                        <div>
                            <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider block">Confluence Engine</span>
                            <h2 id="signal-main-text" class="text-xs sm:text-sm font-black text-white leading-tight">
                                CONFLUENCE SCANNER
                            </h2>
                        </div>
                    </div>

                    {{-- 4 Pillar Real-Time Status Boxes: 50 Stock | Opt Flow | Trend OI | 30 D Book --}}
                    <div class="flex items-center gap-1 sm:gap-1.5 flex-wrap">
                        <div id="pillar-box-stock" class="px-2 py-0.5 rounded-md text-[10px] font-bold tracking-wide border transition-all duration-150 bg-slate-950/80 text-slate-500 border-slate-800 flex items-center gap-1" title="Pillar 1: Nifty 50 Stocks Breadth (≥80% Weight & Stocks)">
                            <span>50 Stock</span>
                        </div>
                        <div id="pillar-box-optflow" class="px-2 py-0.5 rounded-md text-[10px] font-bold tracking-wide border transition-all duration-150 bg-slate-950/80 text-slate-500 border-slate-800 flex items-center gap-1" title="Pillar 2: Options Flow Parity (≥70% Buy or Sell Flow)">
                            <span>Opt Flow</span>
                        </div>
                        <div id="pillar-box-trendoi" class="px-2 py-0.5 rounded-md text-[10px] font-bold tracking-wide border transition-all duration-150 bg-slate-950/80 text-slate-500 border-slate-800 flex items-center gap-1" title="Pillar 3: Trending OI & PCR Difference">
                            <span>Trend OI</span>
                        </div>
                        <div id="pillar-box-d30" class="px-2 py-0.5 rounded-md text-[10px] font-bold tracking-wide border transition-all duration-150 bg-slate-950/80 text-slate-500 border-slate-800 flex items-center gap-1" title="Pillar 4: 30-Level Order Book (OBI, Stack & Divergence)">
                            <span>30 D Book</span>
                        </div>
                        <button type="button" onclick="toggleRecDetails()" id="btn-toggle-rec-details" class="ml-1 px-1.5 py-0.5 rounded-md bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 text-[10px] font-bold transition flex items-center gap-1 cursor-pointer" title="Show/Hide Trade & Strike Details to enlarge Engine Inspector">
                            <span id="rec-toggle-label">Details</span>
                            <span id="rec-toggle-icon">▼</span>
                        </button>
                    </div>
                </div>

                {{-- Master Anchor Process Indicator (NIFTY 50 Stock Anchor & API Cap) --}}
                <div id="master-anchor-banner" class="mb-2 px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800/90 text-[10px] flex items-center justify-between flex-wrap gap-1">
                    <div class="flex items-center gap-1.5 font-mono">
                        <span class="text-amber-400">⚓</span>
                        <span class="text-slate-400 font-bold uppercase text-[9px]">Master Anchor:</span>
                        <span id="anchor-candle-pill" class="font-bold text-amber-300">NIFTY 50 Stocks (Awaiting Trigger)</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span id="rec-tape-capsule" class="px-1.5 py-0.2 rounded text-[8.5px] font-black font-mono bg-slate-900 text-slate-400 border border-slate-800" title="Tape State">TAPE: BALANCED</span>
                        <span id="dom-driver-diagnosis-pill" class="px-1.5 py-0.2 rounded text-[8.5px] font-black font-mono bg-slate-900 text-slate-400 border border-slate-800 cursor-help" title="Institutional Driver Diagnosis: Detects and resolves divergences between Options Flow, Equities, and Futures Book">DRIVER: BALANCED</span>
                        <span id="anchor-status-pill" class="px-1.5 py-0.2 rounded text-[8.5px] font-bold bg-slate-800 text-slate-400">BIAS: NEUTRAL</span>
                        <span id="api-trade-cap-pill" class="hidden px-1.5 py-0.2 rounded text-[8.5px] font-bold font-mono bg-amber-500/20 text-amber-300 border border-amber-500/30">API: 0/3</span>
                    </div>
                </div>

                {{-- Collapsible Details Section: Trade Parameters & Option Strike Recommendation --}}
                <div id="rec-collapsible-section" class="space-y-2 transition-all duration-200">
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
                    <div class="bg-slate-950/70 px-2.5 rounded-lg border border-slate-800/80 text-[10.5px] text-slate-300 flex items-center gap-1.5 overflow-hidden h-7">
                        <span class="text-amber-400 text-xs shrink-0">💡</span>
                        <p id="trade-rationale" class="truncate whitespace-nowrap text-slate-300 font-medium flex-1">
                            Awaiting 30-depth order book alignment with quant and heavyweight breadth.
                        </p>
                    </div>

                    {{-- ⚡ DIRECT OPTION STRIKE EXECUTION CARD (Options Trader Mode) --}}
                    <div class="p-2 rounded-xl bg-slate-950 border border-slate-800/90 space-y-1.5 shadow-inner">
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
            </div>

            {{-- 1.5. Highest Number of ASK & BID QTY Traded So Far (Top 10 Footprint with Time) --}}
            <div id="top-traded-volume-card" class="bg-gradient-to-br from-slate-900 via-slate-900 to-slate-950 border border-slate-800 rounded-xl p-3 shadow-xl space-y-2 relative transition-all duration-200">
                <div class="flex items-center justify-between flex-wrap gap-2 pb-1.5 border-b border-slate-800/80">
                    <div class="flex items-center gap-2">
                        <span class="text-base text-amber-400">🌊</span>
                        <div>
                            <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider block">Session Volume Footprint</span>
                            <h2 class="text-xs sm:text-sm font-black text-white leading-tight flex items-center gap-1.5 flex-wrap">
                                <span>HIGHEST TRADED QTY</span>
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-amber-500/10 text-amber-300 border border-amber-500/30">TOP 10</span>
                                <span class="px-1.5 py-0.2 rounded text-[8.5px] font-mono font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/40" title="Every trade print >= 500 Qty is automatically persisted to MySQL database for historical backtesting">💾 DB PERSISTED</span>
                            </h2>
                        </div>
                    </div>

                    {{-- View Switcher (Both / Ask Only / Bid Only), Skip Open Filter & Collapse Button --}}
                    <div class="flex items-center gap-1.5 flex-wrap">
                        {{-- Skip Open Time Filter --}}
                        <div class="flex items-center gap-1 text-[9px] bg-slate-950 px-1.5 py-0.5 rounded-lg border border-slate-800" title="Skip opening minutes (e.g. 09:30 means records start from 09:31)">
                            <span class="text-slate-400 font-bold uppercase text-[8.5px]">Skip Open:</span>
                            <select id="sel-top-traded-skip" onchange="onTopTradedSkipChange(this.value)" class="bg-transparent text-amber-300 font-mono font-bold text-[9.5px] focus:outline-none cursor-pointer">
                                <option value="09:15" class="bg-slate-900 text-slate-200">None (09:15+)</option>
                                <option value="09:20" class="bg-slate-900 text-slate-200">09:20 (Start 09:21)</option>
                                <option value="09:30" selected class="bg-slate-900 text-amber-300">09:30 (Start 09:31)</option>
                                <option value="09:45" class="bg-slate-900 text-slate-200">09:45 (Start 09:46)</option>
                                <option value="10:00" class="bg-slate-900 text-slate-200">10:00 (Start 10:01)</option>
                            </select>
                        </div>

                        <div class="flex items-center bg-slate-950 p-0.5 rounded-lg border border-slate-800 text-[9.5px] font-bold">
                            <button type="button" onclick="switchTopTradedView('both')" id="btn-top-traded-both" class="px-2 py-0.5 rounded bg-indigo-600 text-white transition cursor-pointer" title="Side-by-Side Top 10 Ask & Bid">Both</button>
                            <button type="button" onclick="switchTopTradedView('ask')" id="btn-top-traded-ask" class="px-2 py-0.5 rounded text-emerald-400 hover:text-white transition cursor-pointer" title="Top 10 Ask Traded Qty (Aggressive Buys)">Ask (Buys)</button>
                            <button type="button" onclick="switchTopTradedView('bid')" id="btn-top-traded-bid" class="px-2 py-0.5 rounded text-rose-400 hover:text-white transition cursor-pointer" title="Top 10 Bid Traded Qty (Aggressive Sells)">Bid (Sells)</button>
                        </div>
                        <button type="button" onclick="toggleTopTradedCard()" id="btn-toggle-top-traded" class="px-1.5 py-0.5 rounded-md bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 text-[10px] font-bold transition flex items-center gap-1 cursor-pointer" title="Collapse / Expand Top Traded Qty">
                            <span id="top-traded-toggle-icon">▲</span>
                        </button>
                    </div>
                </div>

                {{-- Summary Pill Bar --}}
                <div id="top-traded-summary-bar" class="flex items-center justify-between text-[10px] font-mono px-2 py-1 rounded bg-slate-950 border border-slate-800/90 gap-2">
                    <div class="flex items-center gap-1.5 truncate">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        <span class="text-slate-400 font-sans text-[9px] uppercase font-bold">Peak Ask:</span>
                        <span id="top-traded-peak-ask" class="text-emerald-300 font-bold">--</span>
                    </div>
                    <div class="flex items-center gap-1.5 truncate">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                        <span class="text-slate-400 font-sans text-[9px] uppercase font-bold">Peak Bid:</span>
                        <span id="top-traded-peak-bid" class="text-rose-300 font-bold">--</span>
                    </div>
                    <div class="flex items-center gap-1 text-[9px]">
                        <span class="text-slate-500 font-sans font-bold">BIAS:</span>
                        <span id="top-traded-bias-pill" class="px-1 py-0.2 rounded font-black text-[9px] bg-slate-900 text-slate-400">NEUTRAL</span>
                    </div>
                </div>

                {{-- Collapsible Container --}}
                <div id="top-traded-collapsible" class="transition-all duration-200">
                    {{-- Grid for Top 10 Ask & Top 10 Bid --}}
                    <div id="top-traded-grid" class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs font-mono">
                        
                        {{-- Top 10 ASK QTY Traded (Green / Aggressive Buy) --}}
                        <div id="col-top-traded-ask" class="bg-slate-950/90 rounded-lg p-2 border border-emerald-900/30 flex flex-col justify-between">
                            <div class="flex items-center justify-between pb-1 mb-1 border-b border-slate-800/80 text-[10px]">
                                <span class="font-bold text-emerald-400 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                    <span>TOP 10 ASK (BUY TRADES)</span>
                                </span>
                                <span class="text-[9px] text-slate-500">Lifting Offer</span>
                            </div>
                            <div class="space-y-1 custom-scrollbar max-h-[220px] overflow-y-auto" id="list-top-traded-ask">
                                <div class="text-slate-500 text-[10px] py-3 text-center italic">Awaiting Ask volume prints...</div>
                            </div>
                        </div>

                        {{-- Top 10 BID QTY Traded (Red / Aggressive Sell) --}}
                        <div id="col-top-traded-bid" class="bg-slate-950/90 rounded-lg p-2 border border-rose-900/30 flex flex-col justify-between">
                            <div class="flex items-center justify-between pb-1 mb-1 border-b border-slate-800/80 text-[10px]">
                                <span class="font-bold text-rose-400 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                    <span>TOP 10 BID (SELL TRADES)</span>
                                </span>
                                <span class="text-[9px] text-slate-500">Hitting Bid</span>
                            </div>
                            <div class="space-y-1 custom-scrollbar max-h-[220px] overflow-y-auto" id="list-top-traded-bid">
                                <div class="text-slate-500 text-[10px] py-3 text-center italic">Awaiting Bid volume prints...</div>
                            </div>
                        </div>

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
                        <div class="flex items-center gap-2">
                            <button type="button" id="btn-toggle-all-journal" onclick="toggleAllJournalCards()" class="px-2 py-0.5 rounded bg-slate-900 hover:bg-slate-800 text-slate-300 hover:text-white border border-slate-800 font-bold flex items-center gap-1 cursor-pointer text-[10.5px] transition" title="Toggle Show/Hide on all Journal rows">
                                <span id="label-toggle-all-journal">▼ Expand All</span>
                            </button>
                            <button type="button" onclick="refreshSignalLogs()" class="text-indigo-400 hover:text-indigo-300 font-bold flex items-center gap-1 cursor-pointer text-xs transition">
                                <span>🔄</span>
                                <span>Refresh</span>
                            </button>
                        </div>
                    </div>

                    {{-- Scrollable Signals List (Enlarged Height for Full Multi-Engine Card Viewing with Dark Scrollbar) --}}
                    <div id="journal-logs-container" class="space-y-2 max-h-[46rem] xl:max-h-[52rem] overflow-y-auto pr-1.5 font-sans custom-scrollbar">
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
                    <div id="live-alerts-history-container" class="space-y-1.5 overflow-y-auto max-h-[46rem] xl:max-h-[52rem] pr-1.5 custom-scrollbar">
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

            {{-- 0B. Previous Day Institutional Anchors (Top 5 Ask & Bid Traded Volumes vs Live Stream Price) --}}
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 shadow-xl space-y-2.5" id="pd-anchors-card">
                {{-- Card Header --}}
                <div class="flex items-center justify-between flex-wrap gap-1.5 pb-2 border-b border-slate-800/80">
                    <div class="flex items-center gap-1.5">
                        <span class="text-amber-400 text-sm">⚓</span>
                        <div>
                            <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider block">Institutional Memory</span>
                            <h3 class="text-xs font-black text-white leading-tight flex items-center gap-1.5">
                                <span>PREV DAY TOP 5 ANCHORS</span>
                                <span class="px-1.5 py-0.2 rounded text-[8.5px] font-mono font-bold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30" id="pd-anchors-date-badge" title="Reference Previous Trading Day">{{ \Carbon\Carbon::parse($prevTradingDate ?? now())->format('d-M') }}</span>
                            </h3>
                        </div>
                    </div>

                    {{-- Controls: View Filter & Collapse Toggle --}}
                    <div class="flex items-center gap-1">
                        <div class="flex items-center bg-slate-950 p-0.5 rounded-lg border border-slate-800 text-[9px] font-bold">
                            <button type="button" onclick="switchPdAnchorsView('both')" id="btn-pd-view-both" class="px-1.5 py-0.5 rounded bg-indigo-600 text-white transition cursor-pointer" title="Show Both Ask & Bid Anchors">Both</button>
                            <button type="button" onclick="switchPdAnchorsView('ask')" id="btn-pd-view-ask" class="px-1.5 py-0.5 rounded text-emerald-400 hover:text-white transition cursor-pointer" title="Top 5 Ask Anchors (Institutional Demand)">Ask</button>
                            <button type="button" onclick="switchPdAnchorsView('bid')" id="btn-pd-view-bid" class="px-1.5 py-0.5 rounded text-rose-400 hover:text-white transition cursor-pointer" title="Top 5 Bid Anchors (Institutional Supply)">Bid</button>
                        </div>
                        <button type="button" onclick="togglePdAnchorsCard()" id="btn-toggle-pd-anchors" class="px-1.5 py-0.5 rounded-md bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 text-[10px] font-bold transition flex items-center gap-1 cursor-pointer" title="Hide / Show Previous Day Anchors">
                            <span id="pd-anchors-toggle-icon">▲</span>
                        </button>
                    </div>
                </div>

                {{-- Collapsible Content Body --}}
                <div id="pd-anchors-body" class="space-y-2 transition-all duration-200">
                    {{-- Live Proximity Summary Bar --}}
                    <div class="flex items-center justify-between text-[9.5px] font-mono px-2 py-1 rounded-lg bg-slate-950 border border-slate-800/90 gap-1.5">
                        <div class="flex items-center gap-1 truncate">
                            <span class="text-slate-500 font-sans text-[8.5px] uppercase font-bold">LTP:</span>
                            <span id="pd-live-ltp-display" class="font-bold text-cyan-300">₹--</span>
                        </div>
                        <div class="flex items-center gap-1 truncate" title="Nearest Prev Day Institutional Volume Cluster">
                            <span class="text-slate-500 font-sans text-[8.5px] uppercase font-bold">Nearest:</span>
                            <span id="pd-nearest-anchor-text" class="font-bold text-amber-300 truncate max-w-[120px]">Calculating...</span>
                        </div>
                        <span id="pd-regime-status-badge" class="px-1.5 py-0.2 rounded font-black text-[8px] bg-slate-900 text-slate-400 border border-slate-800 shrink-0">SCANNING</span>
                    </div>

                    {{-- Two Sub-Panels: Top 5 Ask (Demand) & Top 5 Bid (Supply) --}}
                    <div id="pd-anchors-grid" class="space-y-2 text-xs font-mono">
                        
                        {{-- 1. Top 5 ASK Anchors (Institutional Buying / Demand Zones) --}}
                        <div id="pd-col-ask" class="bg-slate-950/90 rounded-lg p-2 border border-emerald-900/30 space-y-1">
                            <div class="flex items-center justify-between pb-1 border-b border-slate-800/80 text-[10px]">
                                <span class="font-bold text-emerald-400 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                    <span>TOP 5 PREV ASK (BUY CLUSTERS)</span>
                                </span>
                                <span class="text-[8.5px] text-slate-500 font-sans">LTP vs Level</span>
                            </div>

                            {{-- Rows Container --}}
                            <div class="space-y-1" id="pd-list-ask">
                                <div class="text-slate-500 text-[10px] py-2 text-center italic">Awaiting Prev Day Ask prints...</div>
                            </div>
                        </div>

                        {{-- 2. Top 5 BID Anchors (Institutional Selling / Supply Zones) --}}
                        <div id="pd-col-bid" class="bg-slate-950/90 rounded-lg p-2 border border-rose-900/30 space-y-1">
                            <div class="flex items-center justify-between pb-1 border-b border-slate-800/80 text-[10px]">
                                <span class="font-bold text-rose-400 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                    <span>TOP 5 PREV BID (SELL CLUSTERS)</span>
                                </span>
                                <span class="text-[8.5px] text-slate-500 font-sans">LTP vs Level</span>
                            </div>

                            {{-- Rows Container --}}
                            <div class="space-y-1" id="pd-list-bid">
                                <div class="text-slate-500 text-[10px] py-2 text-center italic">Awaiting Prev Day Bid prints...</div>
                            </div>
                        </div>

                    </div>
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

            {{-- 1B. Cumulative Volume Delta (CVD) Continuous Line & Divergence Radar --}}
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 shadow-xl space-y-2">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs">📈</span>
                        <span class="text-[11px] font-black uppercase text-white tracking-wider">Cumulative Volume Delta (CVD)</span>
                    </div>
                    <span id="cvd-current-val" class="text-xs font-black font-mono text-emerald-400">+0 Delta</span>
                </div>

                {{-- CVD Mini Canvas Chart --}}
                <div class="relative w-full h-[68px] bg-slate-950 rounded-lg border border-slate-800 overflow-hidden p-1">
                    <canvas id="cvd-mini-canvas" class="w-full h-full block"></canvas>
                    <div class="absolute top-1 left-2 text-[8px] font-mono text-slate-500 pointer-events-none">
                        High: <span id="cvd-stat-high" class="text-slate-400">0</span> · Low: <span id="cvd-stat-low" class="text-slate-400">0</span>
                    </div>
                </div>

                {{-- Delta Divergence Alert Status & Execution State --}}
                <div class="flex items-center justify-between pt-0.5 flex-wrap gap-1">
                    <span id="cvd-divergence-badge" class="px-2 py-0.5 rounded text-[9.5px] font-mono font-bold bg-slate-950 text-slate-400 border border-slate-800 flex items-center gap-1 truncate max-w-[200px]">
                        <span class="text-slate-500">●</span>
                        <span id="cvd-divergence-text" class="truncate">Normal Flow (In Sync)</span>
                    </span>
                    <span id="cvd-tape-capsule" class="px-1.5 py-0.2 rounded text-[9px] font-mono font-black bg-slate-950 text-slate-400 border border-slate-800 truncate" title="Tape Execution State">⚖️ BALANCED AUCTION</span>
                </div>
            </div>

            {{-- 2. Confluence Alignment Scorecard --}}
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 shadow-xl space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-black uppercase text-white tracking-wider">Confluence Alignment Scorecard</span>
                    <span id="scorecard-driver-diagnosis-pill" class="px-1.5 py-0.2 rounded text-[8.5px] font-black font-mono bg-slate-950 text-slate-400 border border-slate-800 cursor-help" title="Market Driver Diagnosis">⚖️ DRIVER: BALANCED</span>
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

            {{-- 3B. Order Book Slope & Curvature (Depth Buildup Asymmetry) --}}
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 shadow-xl space-y-2">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs">📐</span>
                        <span class="text-[11px] font-black uppercase text-white tracking-wider">Book Slope &amp; Curvature</span>
                    </div>
                    <span id="book-slope-asymmetry-badge" class="px-1.5 py-0.2 rounded text-[9.5px] font-mono font-bold bg-slate-950 text-slate-300 border border-slate-800">
                        1.00x Balanced
                    </span>
                </div>

                {{-- Slope Rates Comparison Grid --}}
                <div class="grid grid-cols-2 gap-2 text-xs font-mono">
                    <div class="p-2 rounded-lg bg-slate-950 border border-slate-800 space-y-0.5">
                        <div class="flex justify-between items-center text-[9.5px] text-slate-400 font-sans font-bold">
                            <span>Bid Slope (Support)</span>
                            <span class="text-emerald-400 font-mono">▲</span>
                        </div>
                        <div id="val-bid-slope" class="text-sm font-black text-emerald-400 font-mono">0 qty/pt</div>
                        <div class="text-[8.5px] text-slate-500 font-sans">Accumulation rate per rupee drop</div>
                    </div>

                    <div class="p-2 rounded-lg bg-slate-950 border border-slate-800 space-y-0.5">
                        <div class="flex justify-between items-center text-[9.5px] text-slate-400 font-sans font-bold">
                            <span>Ask Slope (Resistance)</span>
                            <span class="text-rose-400 font-mono">▼</span>
                        </div>
                        <div id="val-ask-slope" class="text-sm font-black text-rose-400 font-mono">0 qty/pt</div>
                        <div class="text-[8.5px] text-slate-500 font-sans">Accumulation rate per rupee rise</div>
                    </div>
                </div>

                {{-- Curvature Steepness Visualizer Bar --}}
                <div class="space-y-1">
                    <div class="flex justify-between text-[9px] text-slate-400">
                        <span id="lbl-slope-bid-ratio">Bids 50%</span>
                        <span class="text-slate-500 font-mono">Slope Curvature Asymmetry</span>
                        <span id="lbl-slope-ask-ratio">Asks 50%</span>
                    </div>
                    <div class="w-full bg-slate-950 h-2 rounded-full overflow-hidden flex border border-slate-800">
                        <div id="bar-slope-bid" class="bg-gradient-to-r from-emerald-600 to-emerald-400 h-full transition-all duration-300" style="width: 50%;"></div>
                        <div id="bar-slope-ask" class="bg-gradient-to-r from-rose-400 to-rose-600 h-full transition-all duration-300" style="width: 50%;"></div>
                    </div>
                </div>

                <p id="book-slope-desc" class="text-[9.5px] text-slate-400 leading-tight">
                    Measures how rapidly resting liquidity builds up away from LTP. Steeper Bid slope indicates institutional floor defense.
                </p>
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
                            <input type="checkbox" id="cfg-strict-confluence" class="accent-indigo-500 w-3.5 h-3.5 cursor-pointer" onchange="updateStrictConfluenceLabel()">
                            <span id="lbl-strict-confluence">Strict 4/4 Full Confluence</span>
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

                    {{-- 4 Pillars Thresholds Grid with Individual Enable/Disable --}}
                    <div class="grid grid-cols-2 gap-2 pt-1 border-t border-slate-900 text-[10px]">
                        {{-- Pillar 1: Nifty 50 Stocks Breadth (BOTH Weight & Stocks) --}}
                        <div class="p-2 rounded-lg bg-slate-900/90 border border-slate-800 space-y-1">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-bold text-slate-200 block text-[10.5px] truncate">🏢 Pillar 1: Nifty 50 Stocks</span>
                                <label class="relative inline-flex items-center cursor-pointer" title="Enable or disable Pillar 1">
                                    <input type="checkbox" id="cfg-pillar1-enabled" class="sr-only peer" checked onchange="updateStrictConfluenceLabel()">
                                    <div class="w-7 h-3.5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[1px] after:left-[1px] after:bg-white after:rounded-full after:h-2.5 after:w-2.5 after:transition-all peer-checked:bg-emerald-500"></div>
                                </label>
                            </div>
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-slate-400 text-[9.5px]">Min Weight %:</span>
                                <input type="number" id="cfg-pillar-breadth-weight" min="50" max="95" step="1" value="80" class="w-16 bg-slate-950 border border-slate-700 rounded px-1.5 py-0.5 text-right font-mono font-bold text-white text-[10.5px]">
                            </div>
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-slate-400 text-[9.5px]">Min Stocks %:</span>
                                <input type="number" id="cfg-pillar-breadth-stocks" min="50" max="95" step="1" value="80" class="w-16 bg-slate-950 border border-slate-700 rounded px-1.5 py-0.5 text-right font-mono font-bold text-white text-[10.5px]" title="Min % of 50 stocks (80% = 40 stocks)">
                            </div>
                        </div>

                        {{-- Pillar 2: Nifty 50 Options Flow Parity --}}
                        <div class="p-2 rounded-lg bg-slate-900/90 border border-slate-800 space-y-1">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-bold text-slate-200 block text-[10.5px] truncate">📊 Pillar 2: Nifty Options Flow</span>
                                <label class="relative inline-flex items-center cursor-pointer" title="Enable or disable Pillar 2">
                                    <input type="checkbox" id="cfg-pillar2-enabled" class="sr-only peer" checked onchange="updateStrictConfluenceLabel()">
                                    <div class="w-7 h-3.5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[1px] after:left-[1px] after:bg-white after:rounded-full after:h-2.5 after:w-2.5 after:transition-all peer-checked:bg-cyan-500"></div>
                                </label>
                            </div>
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-slate-400 text-[9.5px]">Min Flow %:</span>
                                <input type="number" id="cfg-pillar-options-flow" min="50" max="95" step="1" value="70" class="w-16 bg-slate-950 border border-slate-700 rounded px-1.5 py-0.5 text-right font-mono font-bold text-cyan-300 text-[10.5px]" title="Requires Buy or Sell flow >= 70%">
                            </div>
                            <span class="text-[9px] text-slate-500 block leading-tight">Requires BUY or SELL flow ≥ 70%</span>
                        </div>

                        {{-- Pillar 3: Trending OI & PCR Sentiment --}}
                        <div class="p-2 rounded-lg bg-slate-900/90 border border-slate-800 space-y-1">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-bold text-slate-200 block text-[10.5px] truncate">📈 Pillar 3: Trending OI</span>
                                <label class="relative inline-flex items-center cursor-pointer" title="Enable or disable Pillar 3">
                                    <input type="checkbox" id="cfg-pillar3-enabled" class="sr-only peer" checked onchange="updateStrictConfluenceLabel()">
                                    <div class="w-7 h-3.5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[1px] after:left-[1px] after:bg-white after:rounded-full after:h-2.5 after:w-2.5 after:transition-all peer-checked:bg-indigo-500"></div>
                                </label>
                            </div>
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-slate-400 text-[9.5px]">Bull Min PCR:</span>
                                <input type="number" id="cfg-pillar-oi-bull-pcr" min="0.5" max="2.5" step="0.05" value="1.05" class="w-16 bg-slate-950 border border-slate-700 rounded px-1.5 py-0.5 text-right font-mono font-bold text-emerald-400 text-[10.5px]">
                            </div>
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-slate-400 text-[9.5px]">Bear Max PCR:</span>
                                <input type="number" id="cfg-pillar-oi-bear-pcr" min="0.5" max="2.5" step="0.05" value="0.95" class="w-16 bg-slate-950 border border-slate-700 rounded px-1.5 py-0.5 text-right font-mono font-bold text-rose-400 text-[10.5px]">
                            </div>
                        </div>

                        {{-- Pillar 4: 30-Level Deep Order Book --}}
                        <div class="p-2 rounded-lg bg-slate-900/90 border border-slate-800 space-y-1">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-bold text-slate-200 block text-[10.5px] truncate">🌊 Pillar 4: 30-Depth Order Book</span>
                                <label class="relative inline-flex items-center cursor-pointer" title="Enable or disable Pillar 4">
                                    <input type="checkbox" id="cfg-pillar4-enabled" class="sr-only peer" checked onchange="updateStrictConfluenceLabel()">
                                    <div class="w-7 h-3.5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[1px] after:left-[1px] after:bg-white after:rounded-full after:h-2.5 after:w-2.5 after:transition-all peer-checked:bg-amber-500"></div>
                                </label>
                            </div>
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

                {{-- Category 2B: Aggressive Market Order (Tape Sweep) Alert --}}
                <div class="bg-slate-950 p-3 rounded-xl border border-emerald-900/60 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="font-bold text-white uppercase text-[11px] flex items-center gap-1.5">
                            <span>💥 Aggressive Market Order (Tape Sweep)</span>
                            <span class="text-[10px] text-emerald-400 font-normal">(Trade Qty ≥ X)</span>
                        </label>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="cfg-enable-market-order" class="sr-only peer" checked>
                            <div class="w-8 h-4 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-emerald-500"></div>
                        </label>
                    </div>
                    <p class="text-[10px] text-slate-400">
                        Captures aggressive executed market orders (lifting the ask offer or hitting the bid) that equal or exceed your threshold.
                    </p>
                    <div class="flex items-center gap-2">
                        <input type="number" id="cfg-market-order-qty" min="100" step="100" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-emerald-300 font-bold font-mono focus:outline-none focus:border-emerald-400" value="2000" placeholder="e.g. 2000">
                        <span class="text-[11px] text-slate-400 font-bold">Qty</span>
                    </div>
                    <div class="flex items-center gap-1.5 flex-wrap pt-0.5">
                        <span class="text-[9px] text-slate-500 font-bold uppercase">Presets:</span>
                        <button type="button" onclick="setMarketOrderPreset(500)" class="px-2 py-0.5 rounded bg-slate-900 hover:bg-slate-800 text-slate-300 text-[10px] font-mono border border-slate-800">500</button>
                        <button type="button" onclick="setMarketOrderPreset(1000)" class="px-2 py-0.5 rounded bg-slate-900 hover:bg-slate-800 text-slate-300 text-[10px] font-mono border border-slate-800">1,000</button>
                        <button type="button" onclick="setMarketOrderPreset(2000)" class="px-2 py-0.5 rounded bg-slate-900 hover:bg-slate-800 text-slate-300 text-[10px] font-mono border border-slate-800">2,000</button>
                        <button type="button" onclick="setMarketOrderPreset(3500)" class="px-2 py-0.5 rounded bg-slate-900 hover:bg-slate-800 text-slate-300 text-[10px] font-mono border border-slate-800">3,500</button>
                        <button type="button" onclick="setMarketOrderPreset(5000)" class="px-2 py-0.5 rounded bg-slate-900 hover:bg-slate-800 text-slate-300 text-[10px] font-mono border border-slate-800">5,000</button>
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
                    <div class="pt-2 border-t border-slate-900 grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <label class="flex items-center gap-2 p-1.5 rounded-lg bg-slate-900/80 border border-slate-800 cursor-pointer">
                            <input type="checkbox" id="cfg-sound-stack" class="rounded bg-slate-800 border-slate-700 text-amber-500 focus:ring-0 cursor-pointer" checked>
                            <span class="text-[10px] font-bold text-slate-300">Blocks &amp; Stacks</span>
                        </label>
                        <label class="flex items-center gap-2 p-1.5 rounded-lg bg-slate-900/80 border border-slate-800 cursor-pointer">
                            <input type="checkbox" id="cfg-sound-market" class="rounded bg-slate-800 border-slate-700 text-emerald-500 focus:ring-0 cursor-pointer" checked>
                            <span class="text-[10px] font-bold text-slate-300">Market Orders</span>
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
                                <label class="text-slate-400 block mb-0.5">SL & TP Reference Basis</label>
                                <select id="zc-protection-basis-select" onchange="updateZcBasisHint()" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-1.5 py-1 text-xs text-sky-300 font-bold focus:outline-none focus:border-sky-400 cursor-pointer">
                                    <option value="STRIKE" selected>🎯 Strike / Option Premium (Pts)</option>
                                    <option value="NIFTY">📈 NIFTY 50 Spot (Index Pts)</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-rose-400 font-bold block mb-0.5" id="lbl-zc-sl-pts">SL (Points)</label>
                                <input type="number" id="zc-sl-pts-input" oninput="updateZcBasisHint()" value="20" min="0" max="500" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 text-xs text-rose-300 font-mono font-bold focus:outline-none">
                            </div>

                            <div>
                                <label class="text-emerald-400 font-bold block mb-0.5" id="lbl-zc-tp-pts">TP (Points)</label>
                                <input type="number" id="zc-tp-pts-input" oninput="updateZcBasisHint()" value="20" min="1" max="1000" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 text-xs text-emerald-300 font-mono font-bold focus:outline-none">
                            </div>
                        </div>

                        {{-- Dynamic Protection Formula Hint --}}
                        <div id="zc-basis-hint-box" class="px-2.5 py-1.5 rounded-lg bg-sky-950/40 border border-sky-500/30 text-[10px] text-sky-200 flex items-center gap-2">
                            <span class="text-xs">💡</span>
                            <span id="zc-basis-hint-text"><b>Strike Basis:</b> If Option Entry is ₹135 with 20 SL & 20 TP &rarr; <b>SL: ₹155.00</b> (+20), <b>Target: ₹115.00</b> (-20) on option selling decay.</span>
                        </div>

                        {{-- Optional Portfolio MTM Amount Protection Row --}}
                        <div class="p-2 rounded-lg bg-slate-900/60 border border-slate-800 space-y-1.5">
                            <div class="text-[10.5px] font-bold text-slate-300 flex items-center gap-1.5">
                                <span>🛡️ Portfolio MTM Protection (₹ Amount)</span>
                                <span class="text-[9px] text-slate-500 font-normal">(Optional dual protection running simultaneously in 0Click)</span>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                <div>
                                    <label class="text-emerald-400 font-bold text-[9.5px] block mb-0.5">Target Profit (₹)</label>
                                    <input type="number" id="zc-mtm-target-input" placeholder="e.g. 5000" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1 text-xs text-emerald-300 font-mono focus:outline-none">
                                </div>
                                <div>
                                    <label class="text-rose-400 font-bold text-[9.5px] block mb-0.5">Max Stop Loss (₹)</label>
                                    <input type="number" id="zc-mtm-loss-input" placeholder="e.g. 3000" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1 text-xs text-rose-300 font-mono focus:outline-none">
                                </div>
                                <div class="flex items-center gap-2 pt-3 sm:pt-4">
                                    <input type="checkbox" id="zc-mtm-auto-trail" class="accent-amber-400 h-3.5 w-3.5 cursor-pointer">
                                    <label for="zc-mtm-auto-trail" class="text-[10px] font-bold text-amber-300 cursor-pointer">Auto-Trail MTM</label>
                                </div>
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
                        <div class="p-2.5 rounded-xl bg-slate-900/90 border border-amber-500/30 space-y-2">
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <div class="flex items-center gap-2">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" id="zc-enable-daily-cap" onchange="toggleDailyCapInput(this.checked)" class="sr-only peer">
                                        <div class="w-8 h-4 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-amber-500"></div>
                                    </label>
                                    <div>
                                        <div class="text-[11px] font-bold text-amber-300 flex items-center gap-1.5 flex-wrap">
                                            <span>Max API Executed Trades Per Day:</span>
                                            <span id="zc-daily-trades-badge" class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40">0 / 3 Dispatched Today</span>
                                        </div>
                                    </div>
                                </div>
                                <div id="zc-daily-cap-input-wrap" class="flex items-center gap-1.5 shrink-0 ml-auto transition-opacity">
                                    <input type="number" id="zc-max-daily-trades" value="3" min="0" max="100" placeholder="3" class="w-16 bg-slate-950 border border-amber-500/50 rounded-lg px-2 py-1 text-xs text-amber-300 font-mono font-black focus:outline-none focus:border-amber-400 text-center">
                                    <span class="text-[10px] text-slate-400 font-bold">trades</span>
                                </div>
                            </div>
                            <div class="text-[9.5px] text-slate-400 leading-tight">
                                Turn OFF or enter <strong>0</strong> for <strong>Unlimited</strong> API trades. When enabled (default 3), auto-dispatch to Broker/0Click locks after reaching this daily count, while confluence signals continue scanning and showing live on screen.
                            </div>
                        </div>

                        {{-- Earliest API Execution Time Restriction --}}
                        <div class="p-2.5 rounded-xl bg-slate-900/90 border border-indigo-500/30 space-y-2">
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <div class="flex items-center gap-2">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" id="zc-enable-time-restriction" onchange="toggleTimeRestrictionInput(this.checked)" class="sr-only peer">
                                        <div class="w-8 h-4 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-indigo-500"></div>
                                    </label>
                                    <div>
                                        <div class="text-[11px] font-bold text-indigo-300 flex items-center gap-1.5 flex-wrap">
                                            <span>Earliest API Execution Start Time:</span>
                                            <span id="zc-time-restriction-badge" class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/40">Active after 09:30 AM</span>
                                        </div>
                                    </div>
                                </div>
                                <div id="zc-time-restriction-input-wrap" class="flex items-center gap-1.5 shrink-0 ml-auto transition-opacity">
                                    <input type="time" id="zc-api-start-time" value="09:30" class="w-24 bg-slate-950 border border-indigo-500/50 rounded-lg px-2 py-1 text-xs text-indigo-300 font-mono font-black focus:outline-none focus:border-indigo-400 text-center">
                                    <span class="text-[10px] text-slate-400 font-bold">IST</span>
                                </div>
                            </div>
                            <div class="text-[9.5px] text-slate-400 leading-tight">
                                Confluence signals, sound chimes, and Journal tracking operate uninterrupted from market open (09:15). When enabled, automated broker/0Click API order execution is locked until this time (default <strong>09:30 AM</strong>) to avoid market open volatility.
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

    {{-- ════════════════════════ 3B. MODAL: 0CLICK DISPATCH CONFIRMATION SAFETY GUARD ════════════════════════ --}}
    <div id="modal-dispatch-confirm" onclick="if (event.target === this) closeDispatchConfirmModal()" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-xs p-3">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full flex flex-col shadow-2xl overflow-hidden ring-1 ring-purple-500/30">
            
            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-slate-800 px-4 py-3 bg-gradient-to-r from-purple-950/60 to-slate-950">
                <div class="flex items-center gap-2">
                    <span class="text-xl">⚡</span>
                    <div>
                        <h3 class="text-sm font-black text-white">Confirm 0Click API Order Dispatch</h3>
                        <p class="text-[10px] text-purple-300/80">Safety Check: Verify order parameters before transmitting</p>
                    </div>
                </div>
                <button type="button" onclick="closeDispatchConfirmModal()" class="text-slate-400 hover:text-white p-1 rounded-lg bg-slate-900 hover:bg-slate-800 text-sm cursor-pointer">
                    ✕
                </button>
            </div>

            {{-- Body --}}
            <div class="p-4 space-y-3 text-xs">
                
                {{-- Order Summary Box --}}
                <div class="p-3 rounded-xl bg-slate-950 border border-slate-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span id="confirm-modal-action-badge" class="px-2 py-0.5 rounded font-black text-[10px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                            ▲ CALL / LONG
                        </span>
                        <span id="confirm-modal-style-badge" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30">
                            👑 OPTION SELLING
                        </span>
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <div>
                            <div class="text-[10px] text-slate-400 uppercase font-bold">Suggested Contract</div>
                            <div id="confirm-modal-contract" class="text-sm sm:text-base font-black text-white font-mono">NIFTY 23450 PE</div>
                        </div>
                        <div class="text-right">
                            <div class="text-[10px] text-slate-400 uppercase font-bold">Execution Size</div>
                            <div id="confirm-modal-lots" class="text-sm font-black text-amber-300 font-mono">1 Lot (25 Qty)</div>
                        </div>
                    </div>

                    {{-- Entry / SL / Target row --}}
                    <div class="grid grid-cols-3 gap-1.5 pt-2 border-t border-slate-900 text-center font-mono">
                        <div class="p-1.5 rounded-lg bg-slate-900/80 border border-slate-800">
                            <span class="text-[9px] text-slate-400 block font-sans">Opt Entry</span>
                            <strong id="confirm-modal-entry" class="text-white text-xs">₹154</strong>
                        </div>
                        <div class="p-1.5 rounded-lg bg-slate-900/80 border border-slate-800">
                            <span class="text-[9px] text-slate-400 block font-sans">Stop Loss</span>
                            <strong id="confirm-modal-sl" class="text-rose-400 text-xs">₹182</strong>
                        </div>
                        <div class="p-1.5 rounded-lg bg-slate-900/80 border border-slate-800">
                            <span class="text-[9px] text-slate-400 block font-sans">Target</span>
                            <strong id="confirm-modal-target" class="text-emerald-400 text-xs">₹79</strong>
                        </div>
                    </div>
                </div>

                {{-- Mode & Destination Details --}}
                <div class="p-2.5 rounded-xl bg-slate-950/80 border border-slate-800/80 space-y-1 text-[10.5px]">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Target Mode:</span>
                        <span id="confirm-modal-mode" class="px-1.5 py-0.2 rounded font-mono font-bold text-[9.5px] bg-purple-500/20 text-purple-300 border border-purple-500/40">SIMULATION</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Webhook URL:</span>
                        <span id="confirm-modal-url" class="font-mono text-slate-300 truncate max-w-[200px]" title="0click.in">0click.in</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Trigger Setup:</span>
                        <span id="confirm-modal-reason" class="text-amber-300/90 truncate max-w-[200px]">Liquidity Event</span>
                    </div>
                </div>

                {{-- Opening Time Restriction Notice (visible if executing before configured start time) --}}
                <div id="confirm-modal-time-warn" class="hidden text-[10px] flex items-start gap-1.5 leading-tight p-2 rounded-lg bg-indigo-500/15 border border-indigo-500/30 text-indigo-300">
                    <span class="text-indigo-400 text-xs shrink-0">🕒</span>
                    <span id="confirm-modal-time-warn-text">Opening Time Alert: Current time is before configured 09:30 AM API execution time. Manual dispatch will override and execute immediately.</span>
                </div>

                {{-- Safety notice --}}
                <div class="text-[10px] text-slate-400 flex items-start gap-1.5 leading-tight p-1.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-300/90">
                    <span class="text-amber-400 text-xs">⚠️</span>
                    <span>Confirming will immediately transmit this order instruction to your 0Click broker webhook.</span>
                </div>

            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-2 px-4 py-3 border-t border-slate-800 bg-slate-950">
                <button type="button" onclick="closeDispatchConfirmModal()" class="px-3.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-750 text-slate-300 text-xs font-bold transition cursor-pointer">
                    Cancel
                </button>
                <button type="button" id="btn-execute-confirmed-dispatch" onclick="executeConfirmedDispatch()" class="px-4 py-1.5 rounded-lg bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white text-xs font-black shadow-lg shadow-purple-500/25 transition flex items-center gap-1.5 cursor-pointer active:scale-95">
                    <span class="text-amber-300 font-black">⚡</span>
                    <span>Yes, Dispatch Order</span>
                </button>
            </div>

        </div>
    </div>

    {{-- ════════════════════════ 3C. MODAL: ENTERPRISE CUMULATIVE EOD REPORT ════════════════════════ --}}
    <div id="modal-cumulative-report" onclick="if (event.target === this) closeCumulativeReportModal()" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-xs p-2 sm:p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-4xl w-full max-h-[92vh] flex flex-col shadow-2xl overflow-hidden ring-1 ring-amber-500/30">
            
            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-slate-800 px-4 py-3 bg-gradient-to-r from-slate-950 via-slate-900 to-indigo-950">
                <div class="flex items-center gap-2.5">
                    <span class="text-xl">🏛️</span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm sm:text-base font-black text-white">Institutional EOD Cumulative Report</h3>
                            <span id="modal-report-badge" class="px-2 py-0.2 rounded text-[10px] font-black uppercase bg-amber-500/20 text-amber-300 border border-amber-500/40">
                                SYNTHESIS
                            </span>
                        </div>
                        <p class="text-[10px] text-slate-400">Order Flow Alerts, Journal Audit, Range Dominance &amp; Tomorrow S/R Playbook</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input type="date" id="modal-report-date-input" value="{{ $workingDate }}" onchange="fetchModalCumulativeReport(this.value)" class="bg-slate-950 text-amber-300 text-xs font-mono font-bold rounded-lg px-2 py-1 border border-slate-800 focus:outline-none cursor-pointer">
                    
                    <a id="btn-modal-report-fullscreen" href="{{ route('order-flow-depth.report') }}?date={{ $workingDate }}" target="_blank" class="px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition flex items-center gap-1 shadow-sm" title="Open Full-Screen Dashboard">
                        <span>↗</span>
                        <span class="hidden sm:inline">Full Screen</span>
                    </a>

                    <button type="button" onclick="closeCumulativeReportModal()" class="text-slate-400 hover:text-white p-1 rounded-lg bg-slate-900 hover:bg-slate-800 text-sm cursor-pointer">
                        ✕
                    </button>
                </div>
            </div>

            {{-- Modal Body: Dynamic Report Content --}}
            <div id="modal-report-content" class="p-4 overflow-y-auto custom-scrollbar flex-1 space-y-4 text-xs">
                <div class="flex flex-col items-center justify-center py-12 text-slate-400 space-y-2">
                    <span class="text-2xl animate-spin">⚡</span>
                    <p class="text-xs font-mono">Synthesizing institutional microstructure data...</p>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-between px-4 py-2.5 border-t border-slate-800 bg-slate-950 text-xs">
                <span class="text-[10px] text-slate-400 font-mono">Enterprise Microstructure AI Engine</span>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="copyModalDeskBrief()" class="px-3 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition flex items-center gap-1 cursor-pointer">
                        <span>📋</span>
                        <span>Copy Brief</span>
                    </button>
                    <button type="button" onclick="closeCumulativeReportModal()" class="px-3 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition cursor-pointer">
                        Close
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
        enableMarketOrder: true,     // Aggressive market order (tape sweep) alert toggle
        marketOrderThreshold: 2000,  // Executed trade Qty >= X
        enableConfluence: true,      // Confluence Alignment Scorecard alerts
        enableAbsorption: true,      // Absorption wall warnings
        soundEnabled: true,          // Master sound toggle
        soundStack: true,            // Sound for blocks & stacks
        soundMarket: true,           // Sound for aggressive market orders
        soundStrategy: true,         // Sound for confluence strategy setups
        soundProfile: 'crystal',     // 'crystal', 'radar', 'laser', 'marimba', 'tactical'
        autoLog: true,               // Auto-log opportunities to Journal

        // 🎯 4-Pillar High-Probability Strategy Customization
        strictConfluence: true,      // Require simultaneous confluence across all active pillars
        enablePillar1: true,         // Pillar 1: Nifty 50 Stocks Breadth
        enablePillar2: true,         // Pillar 2: Nifty Options Flow Parity
        enablePillar3: true,         // Pillar 3: Trending OI & PCR
        enablePillar4: true,         // Pillar 4: 30-Depth Order Book
        pillarBreadthWeight: 80,     // Pillar 1: Min Weight % (Default: 80%)
        pillarBreadthStocks: 80,     // Pillar 1: Min Stocks % (Default: 80% = 40 stocks)
        pillarOptionsFlow: 70,       // Pillar 2: Min Options Flow % (Default: 70%)
        pillarOiBullPcr: 1.05,       // Pillar 3: Bull Min PCR (Default: 1.05)
        pillarOiBearPcr: 0.95,       // Pillar 3: Bear Max PCR (Default: 0.95)
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
        protectionBasis: 'STRIKE', // 'STRIKE' (Strike/Option Premium Points) or 'NIFTY' (Index Spot Points)
        slPts: 20,
        tpPts: 20,
        mtmTarget: null,
        mtmLoss: null,
        mtmAutoTrail: false,
        slMode: 'candle_hl',
        slBufferPts: 15,
        targetTp1Pts: 100,
        targetTp2Pts: 150,
        enableDailyCap: true,
        maxDailyTrades: 3,
        enableTimeRestriction: true,
        apiStartTime: '09:30'
    };

    window.updateZcBasisHint = function() {
        const basis = document.getElementById('zc-protection-basis-select')?.value || zeroClickSettings.protectionBasis || 'STRIKE';
        const textEl = document.getElementById('zc-basis-hint-text');
        const slInput = document.getElementById('zc-sl-pts-input');
        const tpInput = document.getElementById('zc-tp-pts-input');
        const slVal = parseFloat(slInput?.value || 20);
        const tpVal = parseFloat(tpInput?.value || 20);

        if (!textEl) return;
        if (basis === 'STRIKE') {
            const sampleEntry = 135.0;
            const slPrice = (sampleEntry + slVal).toFixed(2);
            const tpPrice = Math.max(0.05, sampleEntry - tpVal).toFixed(2);
            textEl.innerHTML = `<b>🎯 Strike / Option Premium Basis:</b> If Option entry is ₹${sampleEntry} with ${slVal} SL & ${tpVal} TP &rarr; <b>SL: ₹${slPrice}</b> (+${slVal} pts), <b>Target: ₹${tpPrice}</b> (-${tpVal} pts) for Option Selling (decay).`;
        } else {
            const sampleSpot = 23670.0;
            const bullSl = (sampleSpot - slVal).toFixed(1);
            const bullTp = (sampleSpot + tpVal).toFixed(1);
            const bearSl = (sampleSpot + slVal).toFixed(1);
            const bearTp = (sampleSpot - tpVal).toFixed(1);
            textEl.innerHTML = `<b>📈 NIFTY 50 Spot Basis:</b> If Nifty Spot is 23,670 with ${slVal} SL & ${tpVal} TP &rarr; <b>Bull:</b> SL ${bullSl} (-${slVal}), TP ${bullTp} (+${tpVal}) | <b>Bear:</b> SL ${bearSl} (+${slVal}), TP ${bearTp} (-${tpVal}).`;
        }
    };

    function getApiDispatchedCount() {
        return (state.cachedJournalLogs || []).filter(l => {
            if (!l.notes) return false;
            if (typeof l.notes === 'string') {
                return l.notes.includes('0Click: SENT') || (l.notes.includes('webhook') && l.notes.includes('SENT'));
            }
            if (typeof l.notes === 'object' && l.notes.webhook) {
                return String(l.notes.webhook).includes('SENT');
            }
            return false;
        }).length;
    }

    window.getIstCurrentTimeStr = function() {
        try {
            const parts = new Intl.DateTimeFormat('en-GB', {
                timeZone: 'Asia/Kolkata',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            }).formatToParts(new Date());
            const hour = parts.find(p => p.type === 'hour')?.value || '00';
            const minute = parts.find(p => p.type === 'minute')?.value || '00';
            return `${hour}:${minute}`;
        } catch(e) {
            const d = new Date();
            const pad = n => String(n).padStart(2, '0');
            return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
        }
    };

    window.isApiTimePermitted = function() {
        if (zeroClickSettings.enableTimeRestriction === false) return true;
        const target = zeroClickSettings.apiStartTime || '09:30';
        const current = getIstCurrentTimeStr();
        return current >= target;
    };

    window.toggleTimeRestrictionInput = function(enabled) {
        const wrap = document.getElementById('zc-time-restriction-input-wrap');
        const badge = document.getElementById('zc-time-restriction-badge');
        const input = document.getElementById('zc-api-start-time');
        const timeVal = (input && input.value) ? input.value : '09:30';

        if (wrap) {
            wrap.style.opacity = enabled ? '1' : '0.4';
            wrap.style.pointerEvents = enabled ? 'auto' : 'none';
        }

        if (badge) {
            if (enabled) {
                badge.textContent = `Active after ${timeVal} IST`;
                badge.className = 'px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/40';
            } else {
                badge.textContent = 'Unlocked (No Time Restriction)';
                badge.className = 'px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40';
            }
        }
    };

    window.toggleDailyCapInput = function(enabled) {
        const wrap = document.getElementById('zc-daily-cap-input-wrap');
        const badge = document.getElementById('zc-daily-trades-badge');
        const input = document.getElementById('zc-max-daily-trades');
        const count = getApiDispatchedCount();

        if (wrap) {
            wrap.style.opacity = enabled ? '1' : '0.4';
            wrap.style.pointerEvents = enabled ? 'auto' : 'none';
        }

        if (enabled) {
            if (input && (!input.value || parseInt(input.value, 10) <= 0)) {
                input.value = 3;
            }
            const capVal = parseInt(input ? input.value : 3, 10);
            if (badge) {
                badge.textContent = `${count} / ${capVal} Dispatched Today`;
                badge.className = (count >= capVal)
                    ? 'px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40'
                    : 'px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40';
            }
        } else {
            if (badge) {
                badge.textContent = `${count} Dispatched · Unlimited (Cap Disabled)`;
                badge.className = 'px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40';
            }
        }
    };

    window.toggleRecDetails = function(forced) {
        const sec = document.getElementById('rec-collapsible-section');
        const icon = document.getElementById('rec-toggle-icon');
        const label = document.getElementById('rec-toggle-label');
        if (!sec) return;

        let willHide;
        if (typeof forced === 'boolean') {
            willHide = forced;
        } else {
            willHide = !sec.classList.contains('hidden');
        }

        if (willHide) {
            sec.classList.add('hidden');
            if (icon) icon.textContent = '▶';
            if (label) label.textContent = 'Details';
        } else {
            sec.classList.remove('hidden');
            if (icon) icon.textContent = '▼';
            if (label) label.textContent = 'Details';
        }

        try {
            localStorage.setItem('order_flow_rec_collapsed', willHide ? 'true' : 'false');
        } catch(e) {}
    };

    window.initRecCollapsibleState = function() {
        const saved = localStorage.getItem('order_flow_rec_collapsed');
        // Default to collapsed so Engine Inspector sits high up immediately
        const isCollapsed = (saved === null) ? true : (saved === 'true');
        toggleRecDetails(isCollapsed);
    };

    window.updatePillarStatusBoxes = function(isP1Bull, isP1Bear, isP2Bull, isP2Bear, isP3Bull, isP3Bear, isP4Bull, isP4Bear) {
        const boxStock = document.getElementById('pillar-box-stock');
        const boxOptFlow = document.getElementById('pillar-box-optflow');
        const boxTrendOi = document.getElementById('pillar-box-trendoi');
        const boxD30 = document.getElementById('pillar-box-d30');

        const p1Active = alertSettings.enablePillar1 !== false;
        const p2Active = alertSettings.enablePillar2 !== false;
        const p3Active = alertSettings.enablePillar3 !== false;
        const p4Active = alertSettings.enablePillar4 !== false;

        function renderBox(el, isBull, isBear, label, isActive) {
            if (!el) return;
            if (!isActive) {
                el.className = 'px-2 py-0.5 rounded-md text-[10px] font-bold tracking-wide border transition-all duration-150 bg-slate-950/40 text-slate-600 border-slate-900 flex items-center gap-1 opacity-50';
                el.innerHTML = `<span>${label} (OFF)</span>`;
                return;
            }
            if (isBull) {
                el.className = 'px-2 py-0.5 rounded-md text-[10px] font-black tracking-wide border transition-all duration-150 bg-emerald-500/25 text-emerald-300 border-emerald-500/50 shadow-xs flex items-center gap-1';
                el.innerHTML = `<span class="text-[9px]">▲</span><span>${label}</span>`;
            } else if (isBear) {
                el.className = 'px-2 py-0.5 rounded-md text-[10px] font-black tracking-wide border transition-all duration-150 bg-rose-500/25 text-rose-300 border-rose-500/50 shadow-xs flex items-center gap-1';
                el.innerHTML = `<span class="text-[9px]">▼</span><span>${label}</span>`;
            } else {
                el.className = 'px-2 py-0.5 rounded-md text-[10px] font-bold tracking-wide border transition-all duration-150 bg-slate-950/60 text-slate-500 border-slate-800 flex items-center gap-1';
                el.innerHTML = `<span>${label}</span>`;
            }
        }

        renderBox(boxStock, isP1Bull, isP1Bear, '50 Stock', p1Active);
        renderBox(boxOptFlow, isP2Bull, isP2Bear, 'Opt Flow', p2Active);
        renderBox(boxTrendOi, isP3Bull, isP3Bear, 'Trend OI', p3Active);
        renderBox(boxD30, isP4Bull, isP4Bear, '30 D Book', p4Active);
    };

    window.updateDomDriverDiagnosis = function(gw, rw, gcPct, rcPct, bullFlow, bearFlow, obi30) {
        const domPill = document.getElementById('dom-driver-diagnosis-pill');
        const scorePill = document.getElementById('scorecard-driver-diagnosis-pill');

        let shortLabel = 'DRIVER: BALANCED';
        let fullLabel = '⚖️ DRIVER: BALANCED AUCTION';
        let badgeClass = 'bg-slate-900 text-slate-400 border-slate-800';
        let tooltip = 'Two-way auction in equilibrium. No single institutional driver dominating.';

        // Scenario 1: Full Institutional Trend (Both Stocks & Options aligned)
        if (gw >= 75 && gcPct >= 70 && bullFlow >= 70) {
            shortLabel = 'DRIVER: FULL BULL TREND';
            fullLabel = '🚀 FULL INSTITUTIONAL TREND (BULL)';
            badgeClass = 'bg-emerald-950/90 text-emerald-300 border-emerald-500/60 shadow-[0_0_10px_rgba(16,185,129,0.25)]';
            tooltip = 'High Conviction: Equities (Stocks) & Options Flow are in unanimous Bullish alignment. Trend continuation expected.';
        } else if (rw >= 75 && rcPct >= 70 && bearFlow >= 70) {
            shortLabel = 'DRIVER: FULL BEAR TREND';
            fullLabel = '🩸 FULL INSTITUTIONAL TREND (BEAR)';
            badgeClass = 'bg-rose-950/90 text-rose-300 border-rose-500/60 shadow-[0_0_10px_rgba(244,63,94,0.25)]';
            tooltip = 'High Conviction: Heavy cash selling & Bearish Options flow aligned. Downside continuation expected.';
        }
        // Scenario 2: Options-Led Squeeze (Options strongly Bullish >=70%, but Stocks mixed <65%)
        else if (bullFlow >= 70 && gw < 65) {
            shortLabel = 'DRIVER: OPTIONS SQUEEZE';
            fullLabel = '⚡ OPTIONS-LED SQUEEZE (HEAVYWEIGHTS LAGGING)';
            badgeClass = 'bg-indigo-950/90 text-indigo-300 border-indigo-400/60 shadow-[0_0_10px_rgba(99,102,241,0.25)] animate-pulse';
            tooltip = 'Institutional Put Writing & Call buying driving index UP while core heavyweights rest. Futures Ask walls are hedging blocks, not real resistance.';
        }
        // Scenario 3: Options-Led Downside (Options strongly Bearish >=70%, but Stocks mixed <65%)
        else if (bearFlow >= 70 && rw < 65) {
            shortLabel = 'DRIVER: OPTIONS DOWNSIDE';
            fullLabel = '⚡ OPTIONS-LED DOWNSIDE (CALL WRITING)';
            badgeClass = 'bg-purple-950/90 text-purple-300 border-purple-400/60 shadow-[0_0_10px_rgba(168,85,247,0.25)] animate-pulse';
            tooltip = 'Heavy Call Writing overhead capping index while cash equities hold steady. Trade with Options flow.';
        }
        // Scenario 4: Cash Equities Heavyweights Driving (Stocks strong >=75%, Options slower <65%)
        else if (gw >= 75 && bullFlow < 65) {
            shortLabel = 'DRIVER: CASH RALLY';
            fullLabel = '🏛️ CASH HEAVYWEIGHT DRIVEN RALLY';
            badgeClass = 'bg-cyan-950/90 text-cyan-300 border-cyan-500/60 shadow-xs';
            tooltip = 'Institutions accumulating core heavyweight stocks (Reliance, HDFC Bank, etc.). Options delta will catch up soon.';
        } else if (rw >= 75 && bearFlow < 65) {
            shortLabel = 'DRIVER: CASH SELLOFF';
            fullLabel = '🏛️ CASH HEAVYWEIGHT SELLOFF';
            badgeClass = 'bg-amber-950/90 text-amber-300 border-amber-500/60 shadow-xs';
            tooltip = 'Heavyweight equities dragging index down. Options chain expected to price in downside risk.';
        }

        if (domPill) {
            domPill.textContent = shortLabel;
            domPill.className = `px-1.5 py-0.2 rounded text-[8.5px] font-black font-mono border transition-all duration-200 cursor-help ${badgeClass}`;
            domPill.title = `Market Driver Diagnosis: ${tooltip}`;
        }
        if (scorePill) {
            scorePill.textContent = fullLabel;
            scorePill.className = `px-1.5 py-0.2 rounded text-[8.5px] font-black font-mono border transition-all duration-200 cursor-help ${badgeClass}`;
            scorePill.title = `Market Driver Diagnosis: ${tooltip}`;
        }
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
        const basisSelect = document.getElementById('zc-protection-basis-select');
        const slPtsInput = document.getElementById('zc-sl-pts-input');
        const tpPtsInput = document.getElementById('zc-tp-pts-input');
        const tradeStyleSelect = document.getElementById('zc-trade-style');
        const mtmTargetInput = document.getElementById('zc-mtm-target-input');
        const mtmLossInput = document.getElementById('zc-mtm-loss-input');
        const mtmAutoTrail = document.getElementById('zc-mtm-auto-trail');

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

        const protectionBasis = basisSelect?.value || zeroClickSettings.protectionBasis || 'STRIKE';
        const slPts = parseFloat(slPtsInput?.value || zeroClickSettings.slPts || 20);
        const tpPts = parseFloat(tpPtsInput?.value || zeroClickSettings.tpPts || 20);
        const mtmTarget = parseFloat(mtmTargetInput?.value || zeroClickSettings.mtmTarget || 0);
        const mtmLoss = parseFloat(mtmLossInput?.value || zeroClickSettings.mtmLoss || (mtmAutoTrail?.checked && mtmTarget > 0 ? mtmTarget : 0));

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
                    protection_basis: protectionBasis,
                    sl_pts: slPts,
                    tp_pts: tpPts,
                    price: state.depthData?.ltp || 23300.0,
                    opt_price: 135.0,
                    mtm_target: mtmTarget > 0 ? mtmTarget : null,
                    mtm_loss: mtmLoss > 0 ? mtmLoss : null,
                    auto_trailing_loss: !!mtmAutoTrail?.checked
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
        const marketTag = document.getElementById('legend-market-tag');
        if (marketTag) {
            const threshK = alertSettings.marketOrderThreshold >= 1000 ? `${Math.round(alertSettings.marketOrderThreshold / 1000)}k` : alertSettings.marketOrderThreshold;
            marketTag.textContent = alertSettings.enableMarketOrder 
                ? `💥 Trade (≥${threshK})`
                : `💥 Trade (OFF)`;
            marketTag.title = alertSettings.enableMarketOrder
                ? `Heavy Aggressive Market Order Alert (≥${alertSettings.marketOrderThreshold.toLocaleString()} Qty)`
                : `Market Order Alerts Disabled`;
            marketTag.className = alertSettings.enableMarketOrder
                ? 'px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-black border border-emerald-500/40 whitespace-nowrap text-[9px]'
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
        const enableMarket = document.getElementById('cfg-enable-market-order');
        const marketInput = document.getElementById('cfg-market-order-qty');
        const enableConf = document.getElementById('cfg-enable-confluence');
        const enableAbs = document.getElementById('cfg-enable-absorption');
        const soundMaster = document.getElementById('cfg-sound-master');
        const soundStack = document.getElementById('cfg-sound-stack');
        const soundMarket = document.getElementById('cfg-sound-market');
        const soundStrategy = document.getElementById('cfg-sound-strategy');
        const soundProfile = document.getElementById('cfg-sound-profile');
        const autoLogInput = document.getElementById('cfg-autolog-journal');

        if (enableBlock) enableBlock.checked = alertSettings.enableBlock !== false;
        if (singleInput) singleInput.value = alertSettings.singleThreshold;
        if (enableStack) enableStack.checked = alertSettings.enableStack !== false;
        if (stackCount) stackCount.value = alertSettings.stackMinConsecutive;
        if (stackQty) stackQty.value = alertSettings.stackMinQty;
        if (enableMarket) enableMarket.checked = alertSettings.enableMarketOrder !== false;
        if (marketInput) marketInput.value = alertSettings.marketOrderThreshold ?? 2000;
        if (enableConf) enableConf.checked = alertSettings.enableConfluence !== false;
        if (enableAbs) enableAbs.checked = alertSettings.enableAbsorption !== false;
        if (soundMaster) soundMaster.checked = alertSettings.soundEnabled !== false;
        if (soundStack) soundStack.checked = alertSettings.soundStack !== false;
        if (soundMarket) soundMarket.checked = alertSettings.soundMarket !== false;
        if (soundStrategy) soundStrategy.checked = alertSettings.soundStrategy !== false;
        if (soundProfile) soundProfile.value = alertSettings.soundProfile || 'crystal';
        if (autoLogInput) autoLogInput.checked = alertSettings.autoLog !== false;

        // 4-Pillar High-Probability Inputs
        const strictConf = document.getElementById('cfg-strict-confluence');
        const p1Enabled = document.getElementById('cfg-pillar1-enabled');
        const p2Enabled = document.getElementById('cfg-pillar2-enabled');
        const p3Enabled = document.getElementById('cfg-pillar3-enabled');
        const p4Enabled = document.getElementById('cfg-pillar4-enabled');
        const bWeight = document.getElementById('cfg-pillar-breadth-weight');
        const bStocks = document.getElementById('cfg-pillar-breadth-stocks');
        const optFlow = document.getElementById('cfg-pillar-options-flow');
        const oiBull = document.getElementById('cfg-pillar-oi-bull-pcr');
        const oiBear = document.getElementById('cfg-pillar-oi-bear-pcr');
        const obiPct = document.getElementById('cfg-pillar-obi-pct');
        const microDrift = document.getElementById('cfg-pillar-micro-drift');

        if (strictConf) strictConf.checked = !!alertSettings.strictConfluence;
        if (p1Enabled) p1Enabled.checked = alertSettings.enablePillar1 !== false;
        if (p2Enabled) p2Enabled.checked = alertSettings.enablePillar2 !== false;
        if (p3Enabled) p3Enabled.checked = alertSettings.enablePillar3 !== false;
        if (p4Enabled) p4Enabled.checked = alertSettings.enablePillar4 !== false;
        if (bWeight) bWeight.value = alertSettings.pillarBreadthWeight ?? 80;
        if (bStocks) bStocks.value = alertSettings.pillarBreadthStocks ?? 80;
        if (optFlow) optFlow.value = alertSettings.pillarOptionsFlow ?? 70;
        if (oiBull) oiBull.value = alertSettings.pillarOiBullPcr ?? 1.05;
        if (oiBear) oiBear.value = alertSettings.pillarOiBearPcr ?? 0.95;
        if (obiPct) obiPct.value = alertSettings.pillarObiPct ?? 20;
        if (microDrift) microDrift.value = alertSettings.pillarMicroDrift ?? 0.15;
        updateStrictConfluenceLabel();

        // 0Click Inputs
        const zcAutoLog = document.getElementById('zc-auto-log-toggle');
        const zcForward = document.getElementById('zc-forward-toggle');
        const zcTradeStyle = document.getElementById('zc-trade-style');
        const zcUrl = document.getElementById('zc-webhook-url');
        const zcToken = document.getElementById('zc-webhook-token');
        const zcMode = document.getElementById('zc-mode-select');
        const zcLots = document.getElementById('zc-lots-input');
        const zcBasis = document.getElementById('zc-protection-basis-select');
        const zcSlPts = document.getElementById('zc-sl-pts-input');
        const zcTpPts = document.getElementById('zc-tp-pts-input');
        const zcTp2Pts = document.getElementById('zc-tp2-pts-input');
        const zcMtmTarget = document.getElementById('zc-mtm-target-input');
        const zcMtmLoss = document.getElementById('zc-mtm-loss-input');
        const zcMtmTrail = document.getElementById('zc-mtm-auto-trail');

        if (zcAutoLog) zcAutoLog.checked = zeroClickSettings.autoLog !== false;
        if (zcForward) zcForward.checked = !!zeroClickSettings.forwardToZeroClick;
        if (zcTradeStyle) zcTradeStyle.value = zeroClickSettings.tradeStyle || 'OPTION_SELLING';
        if (zcUrl) zcUrl.value = zeroClickSettings.webhookUrl || 'https://0click.in/api/webhook/tradingview';
        if (zcToken) zcToken.value = zeroClickSettings.webhookToken || '';
        if (zcMode) zcMode.value = zeroClickSettings.mode || 'SIMULATION';
        if (zcLots) zcLots.value = zeroClickSettings.lots || 1;
        if (zcBasis) zcBasis.value = zeroClickSettings.protectionBasis || 'STRIKE';
        if (zcSlPts) zcSlPts.value = zeroClickSettings.slPts ?? (zeroClickSettings.slBufferPts || 20);
        if (zcTpPts) zcTpPts.value = zeroClickSettings.tpPts ?? (zeroClickSettings.targetTp1Pts || 20);
        if (zcTp2Pts) zcTp2Pts.value = zeroClickSettings.targetTp2Pts || 150;
        if (zcMtmTarget) zcMtmTarget.value = zeroClickSettings.mtmTarget || '';
        if (zcMtmLoss) zcMtmLoss.value = zeroClickSettings.mtmLoss || '';
        if (zcMtmTrail) zcMtmTrail.checked = !!zeroClickSettings.mtmAutoTrail;
        if (typeof updateZcBasisHint === 'function') updateZcBasisHint();

        const zcEnableCap = document.getElementById('zc-enable-daily-cap');
        const zcMaxTrades = document.getElementById('zc-max-daily-trades');
        const zcDailyBadge = document.getElementById('zc-daily-trades-badge');
        const zcCapWrap = document.getElementById('zc-daily-cap-input-wrap');

        const isCapEnabled = zeroClickSettings.enableDailyCap !== false;
        if (zcEnableCap) zcEnableCap.checked = isCapEnabled;
        if (zcMaxTrades) zcMaxTrades.value = (zeroClickSettings.maxDailyTrades !== undefined) ? zeroClickSettings.maxDailyTrades : 3;
        if (zcCapWrap) {
            zcCapWrap.style.opacity = isCapEnabled ? '1' : '0.4';
            zcCapWrap.style.pointerEvents = isCapEnabled ? 'auto' : 'none';
        }
        if (zcDailyBadge) {
            const apiDispatched = getApiDispatchedCount();
            if (!isCapEnabled || parseInt(zeroClickSettings.maxDailyTrades, 10) === 0) {
                zcDailyBadge.textContent = `${apiDispatched} Dispatched · Unlimited (Cap Disabled)`;
                zcDailyBadge.className = 'px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40';
            } else {
                const maxAllowed = parseInt(zeroClickSettings.maxDailyTrades || 3, 10);
                zcDailyBadge.textContent = `${apiDispatched} / ${maxAllowed} Dispatched Today`;
                zcDailyBadge.className = apiDispatched >= maxAllowed
                    ? 'px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40'
                    : 'px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40';
            }
        }

        // Time restriction controls
        const zcEnableTime = document.getElementById('zc-enable-time-restriction');
        const zcStartTime = document.getElementById('zc-api-start-time');
        const isTimeEnabled = zeroClickSettings.enableTimeRestriction !== false;
        if (zcEnableTime) zcEnableTime.checked = isTimeEnabled;
        if (zcStartTime) zcStartTime.value = zeroClickSettings.apiStartTime || '09:30';
        toggleTimeRestrictionInput(isTimeEnabled);

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

    window.updateStrictConfluenceLabel = function() {
        const p1 = document.getElementById('cfg-pillar1-enabled')?.checked;
        const p2 = document.getElementById('cfg-pillar2-enabled')?.checked;
        const p3 = document.getElementById('cfg-pillar3-enabled')?.checked;
        const p4 = document.getElementById('cfg-pillar4-enabled')?.checked;
        const count = (p1 ? 1 : 0) + (p2 ? 1 : 0) + (p3 ? 1 : 0) + (p4 ? 1 : 0);
        const lbl = document.getElementById('lbl-strict-confluence');
        if (lbl) {
            lbl.textContent = `Strict ${count}/${count} Full Confluence`;
        }
    };

    window.setSinglePreset = function(qty) {
        const input = document.getElementById('cfg-single-qty');
        if (input) input.value = qty;
    };

    window.setMarketOrderPreset = function(qty) {
        const input = document.getElementById('cfg-market-order-qty');
        if (input) input.value = qty;
    };

    window.setPillarPreset = function(presetName) {
        const strictEl = document.getElementById('cfg-strict-confluence');
        const p1Enabled = document.getElementById('cfg-pillar1-enabled');
        const p2Enabled = document.getElementById('cfg-pillar2-enabled');
        const p3Enabled = document.getElementById('cfg-pillar3-enabled');
        const p4Enabled = document.getElementById('cfg-pillar4-enabled');
        const bWeight = document.getElementById('cfg-pillar-breadth-weight');
        const bStocks = document.getElementById('cfg-pillar-breadth-stocks');
        const optFlow = document.getElementById('cfg-pillar-options-flow');
        const oiBull = document.getElementById('cfg-pillar-oi-bull-pcr');
        const oiBear = document.getElementById('cfg-pillar-oi-bear-pcr');
        const obiPct = document.getElementById('cfg-pillar-obi-pct');
        const microDrift = document.getElementById('cfg-pillar-micro-drift');

        if (p1Enabled) p1Enabled.checked = true;
        if (p2Enabled) p2Enabled.checked = true;
        if (p3Enabled) p3Enabled.checked = true;
        if (p4Enabled) p4Enabled.checked = true;

        if (presetName === 'sniper') {
            if (strictEl) strictEl.checked = true;
            if (bWeight) bWeight.value = 85;
            if (bStocks) bStocks.value = 85;
            if (optFlow) optFlow.value = 75;
            if (oiBull) oiBull.value = 1.15;
            if (oiBear) oiBear.value = 0.85;
            if (obiPct) obiPct.value = 25;
            if (microDrift) microDrift.value = 0.20;
        } else if (presetName === 'standard') {
            if (strictEl) strictEl.checked = true;
            if (bWeight) bWeight.value = 80;
            if (bStocks) bStocks.value = 80;
            if (optFlow) optFlow.value = 70;
            if (oiBull) oiBull.value = 1.05;
            if (oiBear) oiBear.value = 0.95;
            if (obiPct) obiPct.value = 20;
            if (microDrift) microDrift.value = 0.15;
        } else if (presetName === 'scalper') {
            if (strictEl) strictEl.checked = false;
            if (bWeight) bWeight.value = 75;
            if (bStocks) bStocks.value = 75;
            if (optFlow) optFlow.value = 65;
            if (oiBull) oiBull.value = 1.02;
            if (oiBear) oiBear.value = 0.98;
            if (obiPct) obiPct.value = 15;
            if (microDrift) microDrift.value = 0.10;
        }
        updateStrictConfluenceLabel();
    };

    window.resetAlertDefaults = function() {
        alertSettings.enableBlock = true;
        alertSettings.singleThreshold = 2500;
        alertSettings.enableStack = true;
        alertSettings.stackMinConsecutive = 3;
        alertSettings.stackMinQty = 1500;
        alertSettings.enableMarketOrder = true;
        alertSettings.marketOrderThreshold = 2000;
        alertSettings.enableConfluence = true;
        alertSettings.enableAbsorption = true;
        alertSettings.soundEnabled = true;
        alertSettings.soundStack = true;
        alertSettings.soundMarket = true;
        alertSettings.soundStrategy = true;
        alertSettings.soundProfile = 'crystal';
        alertSettings.autoLog = true;

        alertSettings.strictConfluence = true;
        alertSettings.enablePillar1 = true;
        alertSettings.enablePillar2 = true;
        alertSettings.enablePillar3 = true;
        alertSettings.enablePillar4 = true;
        alertSettings.pillarBreadthWeight = 80;
        alertSettings.pillarBreadthStocks = 80;
        alertSettings.pillarOptionsFlow = 70;
        alertSettings.pillarOiBullPcr = 1.05;
        alertSettings.pillarOiBearPcr = 0.95;
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
        zeroClickSettings.enableDailyCap = true;
        zeroClickSettings.maxDailyTrades = 3;
        zeroClickSettings.enableTimeRestriction = true;
        zeroClickSettings.apiStartTime = '09:30';

        loadAlertSettings();
    };

    window.saveAlertSettings = function() {
        const enableBlock = document.getElementById('cfg-enable-block');
        const singleInput = document.getElementById('cfg-single-qty');
        const enableStack = document.getElementById('cfg-enable-stack');
        const stackCount = document.getElementById('cfg-stack-count');
        const stackQty = document.getElementById('cfg-stack-qty');
        const enableMarket = document.getElementById('cfg-enable-market-order');
        const marketInput = document.getElementById('cfg-market-order-qty');
        const enableConf = document.getElementById('cfg-enable-confluence');
        const enableAbs = document.getElementById('cfg-enable-absorption');
        const soundMaster = document.getElementById('cfg-sound-master');
        const soundStack = document.getElementById('cfg-sound-stack');
        const soundMarket = document.getElementById('cfg-sound-market');
        const soundStrategy = document.getElementById('cfg-sound-strategy');
        const soundProfile = document.getElementById('cfg-sound-profile');
        const autoLogInput = document.getElementById('cfg-autolog-journal');

        alertSettings.enableBlock = !!(enableBlock?.checked);
        alertSettings.singleThreshold = Math.max(100, parseInt(singleInput?.value || 2500));
        alertSettings.enableStack = !!(enableStack?.checked);
        alertSettings.stackMinConsecutive = Math.max(2, parseInt(stackCount?.value || 3));
        alertSettings.stackMinQty = Math.max(100, parseInt(stackQty?.value || 1500));
        alertSettings.enableMarketOrder = !!(enableMarket?.checked);
        alertSettings.marketOrderThreshold = Math.max(100, parseInt(marketInput?.value || 2000));
        alertSettings.enableConfluence = !!(enableConf?.checked);
        alertSettings.enableAbsorption = !!(enableAbs?.checked);
        alertSettings.soundEnabled = !!(soundMaster?.checked);
        alertSettings.soundStack = !!(soundStack?.checked);
        alertSettings.soundMarket = !!(soundMarket?.checked);
        alertSettings.soundStrategy = !!(soundStrategy?.checked);
        alertSettings.soundProfile = soundProfile?.value || 'crystal';
        alertSettings.autoLog = !!(autoLogInput?.checked);

        // 4-Pillar High-Probability Settings
        const strictConf = document.getElementById('cfg-strict-confluence');
        const p1Enabled = document.getElementById('cfg-pillar1-enabled');
        const p2Enabled = document.getElementById('cfg-pillar2-enabled');
        const p3Enabled = document.getElementById('cfg-pillar3-enabled');
        const p4Enabled = document.getElementById('cfg-pillar4-enabled');
        const bWeight = document.getElementById('cfg-pillar-breadth-weight');
        const bStocks = document.getElementById('cfg-pillar-breadth-stocks');
        const optFlow = document.getElementById('cfg-pillar-options-flow');
        const oiBull = document.getElementById('cfg-pillar-oi-bull-pcr');
        const oiBear = document.getElementById('cfg-pillar-oi-bear-pcr');
        const obiPct = document.getElementById('cfg-pillar-obi-pct');
        const microDrift = document.getElementById('cfg-pillar-micro-drift');

        alertSettings.strictConfluence = !!(strictConf?.checked);
        alertSettings.enablePillar1 = !!(p1Enabled?.checked);
        alertSettings.enablePillar2 = !!(p2Enabled?.checked);
        alertSettings.enablePillar3 = !!(p3Enabled?.checked);
        alertSettings.enablePillar4 = !!(p4Enabled?.checked);
        alertSettings.pillarBreadthWeight = Math.max(50, Math.min(99, parseFloat(bWeight?.value || 80)));
        alertSettings.pillarBreadthStocks = Math.max(50, Math.min(99, parseFloat(bStocks?.value || 80)));
        alertSettings.pillarOptionsFlow = Math.max(50, Math.min(99, parseFloat(optFlow?.value || 70)));
        alertSettings.pillarOiBullPcr = Math.max(0.5, parseFloat(oiBull?.value || 1.05));
        alertSettings.pillarOiBearPcr = Math.max(0.2, parseFloat(oiBear?.value || 0.95));
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
        const zcBasis = document.getElementById('zc-protection-basis-select');
        const zcSlPts = document.getElementById('zc-sl-pts-input');
        const zcTpPts = document.getElementById('zc-tp-pts-input');
        const zcTp2Pts = document.getElementById('zc-tp2-pts-input');
        const zcMtmTarget = document.getElementById('zc-mtm-target-input');
        const zcMtmLoss = document.getElementById('zc-mtm-loss-input');
        const zcMtmTrail = document.getElementById('zc-mtm-auto-trail');

        zeroClickSettings.autoLog = !!zcAutoLog?.checked;
        zeroClickSettings.forwardToZeroClick = !!zcForward?.checked;
        zeroClickSettings.tradeStyle = zcTradeStyle?.value || 'OPTION_SELLING';
        zeroClickSettings.webhookUrl = zcUrl?.value.trim() || 'https://0click.in/api/webhook/tradingview';
        zeroClickSettings.webhookToken = zcToken?.value.trim() || '';
        zeroClickSettings.mode = zcMode?.value || 'SIMULATION';
        zeroClickSettings.lots = parseInt(zcLots?.value || 1, 10);
        zeroClickSettings.protectionBasis = zcBasis?.value || 'STRIKE';
        zeroClickSettings.slPts = parseFloat(zcSlPts?.value || 20);
        zeroClickSettings.tpPts = parseFloat(zcTpPts?.value || 20);
        zeroClickSettings.slBufferPts = zeroClickSettings.slPts;
        zeroClickSettings.targetTp1Pts = zeroClickSettings.tpPts;
        zeroClickSettings.targetTp2Pts = parseInt(zcTp2Pts?.value || 150, 10);
        zeroClickSettings.mtmTarget = zcMtmTarget?.value ? parseFloat(zcMtmTarget.value) : null;
        zeroClickSettings.mtmLoss = zcMtmLoss?.value ? parseFloat(zcMtmLoss.value) : (zcMtmTrail?.checked && zeroClickSettings.mtmTarget ? zeroClickSettings.mtmTarget : null);
        zeroClickSettings.mtmAutoTrail = !!zcMtmTrail?.checked;

        const zcEnableCap = document.getElementById('zc-enable-daily-cap');
        const zcMaxTrades = document.getElementById('zc-max-daily-trades');
        zeroClickSettings.enableDailyCap = !!zcEnableCap?.checked;
        const parsedTrades = parseInt(zcMaxTrades?.value, 10);
        if (isNaN(parsedTrades) || parsedTrades < 0) {
            zeroClickSettings.maxDailyTrades = zeroClickSettings.enableDailyCap ? 3 : 0;
        } else {
            zeroClickSettings.maxDailyTrades = parsedTrades;
        }

        const zcEnableTime = document.getElementById('zc-enable-time-restriction');
        const zcStartTime = document.getElementById('zc-api-start-time');
        zeroClickSettings.enableTimeRestriction = !!zcEnableTime?.checked;
        zeroClickSettings.apiStartTime = (zcStartTime?.value && zcStartTime.value.trim()) ? zcStartTime.value.trim() : '09:30';

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
        activeMarketOrderAlert: null,
        lastLoggedMarketAlertKey: '',
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
            cvd: 0,
            bookSlopeRatio: 1.0,
            vpoc: {
                price: 0,
                volume: 0,
                isSupport: true,
                ordersEstimate: 0
            }
        },
        
        // Continuous Cumulative Volume Delta (CVD) & Divergence State
        cvd: {
            current: 0,
            sessionHigh: 0,
            sessionLow: 0,
            history: [],
            divergence: 'NONE', // 'NONE', 'BULLISH', 'BEARISH'
            divergenceText: 'Normal Flow (In Sync)',
            lastDivergenceTime: 0
        },

        // Book Slope & Curvature State
        bookSlope: {
            bidSlopeNear: 0,
            askSlopeNear: 0,
            bidSlopeDeep: 0,
            askSlopeDeep: 0,
            ratio: 1.0,
            asymmetry: 'NEUTRAL',
            asymmetryText: 'Balanced Book (1.00x)'
        },

        // Real-Time Tape Mechanics & Order Flow Execution State ("Lifting the Offer" vs "Hitting the Bid" vs "Absorption")
        tapeMechanics: {
            state: 'BALANCED',
            label: 'BALANCED AUCTION',
            subtext: 'Spread Equilibrium',
            color: 'slate',
            recentAskVolume: 0,
            recentBidVolume: 0,
            askHits: 0,
            bidHits: 0,
            lastTickTime: 0
        },

        // Master Strategic Anchor State (NIFTY 50 Breadth Anchor)
        masterAnchor: null,

        // Engine Inspector Journal State
        journalFilter: 'ALL',
        cachedJournalLogs: [],

        volumeProfile: {},
        lastVtt: 0,
        simInterval: null,

        // Highest Traded ASK and BID Quantities (Master Session Storage from 09:15 onwards)
        topTradedVolume: {
            sessionAnchorAsk: @json($sessionAlertsAsk ?? []),
            sessionAnchorBid: @json($sessionAlertsBid ?? []),
            allAsk: @json($sessionAlertsAsk ?? []),
            allBid: @json($sessionAlertsBid ?? []),
            skipTime: localStorage.getItem('order_flow_skip_open_time') || 'none',
            activeView: 'both', // 'both', 'ask', 'bid'
            isCollapsed: false,
        },

        // Previous Day Institutional Anchors (Top 5 Ask & Bid Traded Volumes vs Live Stream Price)
        pdAnchors: {
            prevDate: "{{ $prevTradingDate }}",
            topAsk: @json($prevTopAsk ?? []),
            topBid: @json($prevTopBid ?? []),
            activeView: 'both', // 'both', 'ask', 'bid'
            isCollapsed: localStorage.getItem('order_flow_pd_anchors_collapsed') === 'true',
        }
    };

    // ── HIGHEST TRADED ASK & BID VOLUME FOOTPRINT (FROM 09:15 WITH SKIP FILTER) ──
    function initTopTradedVolume() {
        const workingDate = "{{ $workingDate }}";
        const savedSkip = localStorage.getItem('order_flow_skip_open_time');
        if (savedSkip) {
            state.topTradedVolume.skipTime = savedSkip;
            const sel = document.getElementById('sel-top-traded-skip');
            if (sel) sel.value = savedSkip;
        }

        try {
            const savedAsk = localStorage.getItem(`order_flow_all_ask_${workingDate}`);
            const savedBid = localStorage.getItem(`order_flow_all_bid_${workingDate}`);
            if (savedAsk) {
                const pAsk = JSON.parse(savedAsk);
                if (Array.isArray(pAsk) && pAsk.length > 0) {
                    state.topTradedVolume.allAsk = mergeSessionTrades(state.topTradedVolume.allAsk, pAsk);
                }
            }
            if (savedBid) {
                const pBid = JSON.parse(savedBid);
                if (Array.isArray(pBid) && pBid.length > 0) {
                    state.topTradedVolume.allBid = mergeSessionTrades(state.topTradedVolume.allBid, pBid);
                }
            }
        } catch (e) {
            console.warn('Error reading saved session trades:', e);
        }

        // Always re-merge master sessionAnchor trades (from 09:15 start) to ensure morning peak trades are never lost
        if (state.topTradedVolume.sessionAnchorAsk && state.topTradedVolume.sessionAnchorAsk.length > 0) {
            state.topTradedVolume.allAsk = mergeSessionTrades(state.topTradedVolume.allAsk, state.topTradedVolume.sessionAnchorAsk);
        }
        if (state.topTradedVolume.sessionAnchorBid && state.topTradedVolume.sessionAnchorBid.length > 0) {
            state.topTradedVolume.allBid = mergeSessionTrades(state.topTradedVolume.allBid, state.topTradedVolume.sessionAnchorBid);
        }

        // Flush any initial localStorage cached trades into database to ensure complete sync
        if (state.topTradedVolume.allAsk.length > 0 || state.topTradedVolume.allBid.length > 0) {
            setTimeout(() => {
                syncBatchTradedVolumesToDb([...state.topTradedVolume.allAsk, ...state.topTradedVolume.allBid]);
            }, 2500);
        }

        renderTopTradedVolumeUI();
    }

    function mergeSessionTrades(existing, incoming) {
        const map = new Map();
        [...(existing || []), ...(incoming || [])].forEach(item => {
            if (!item || !item.qty || item.qty <= 0) return;
            const priceNum = parseFloat(item.price);
            const qtyNum = parseInt(item.qty);
            const timeStr = String(item.time || '').trim();
            const sideStr = String(item.side || 'ASK').toUpperCase();
            if (!timeStr || isNaN(priceNum) || isNaN(qtyNum)) return;

            const key = `${timeStr}_${priceNum.toFixed(2)}_${sideStr}`;
            if (!map.has(key) || map.get(key).qty < qtyNum) {
                map.set(key, {
                    time: timeStr,
                    price: priceNum,
                    qty: qtyNum,
                    side: sideStr
                });
            }
        });
        const arr = Array.from(map.values());
        arr.sort((a, b) => a.time.localeCompare(b.time));
        return arr;
    }

    function pruneTradesKeepHighest(list, maxKeep = 300, anchorList = []) {
        if (!Array.isArray(list) || list.length <= maxKeep) return list;

        // Collect anchor items so they are permanently protected from eviction
        const anchorKeys = new Set((anchorList || []).map(x => `${x.time}_${Number(x.price).toFixed(2)}_${x.side}`));

        // Sort copy by quantity descending to identify the highest session prints
        const sortedByQty = [...list].sort((a, b) => b.qty - a.qty);
        // Protect top 100 highest prints of all time across the entire day
        const topPrints = sortedByQty.slice(0, 100);
        const protectedKeys = new Set([
            ...anchorKeys,
            ...topPrints.map(x => `${x.time}_${Number(x.price).toFixed(2)}_${x.side}`)
        ]);

        const kept = [];
        const seen = new Set();

        // 1. Keep all protected items (anchors from trade start + top 100 highest volume prints)
        list.forEach(item => {
            const k = `${item.time}_${Number(item.price).toFixed(2)}_${item.side}`;
            if (protectedKeys.has(k) && !seen.has(k)) {
                kept.push(item);
                seen.add(k);
            }
        });

        // 2. Fill remaining capacity up to maxKeep with recent prints
        for (let i = list.length - 1; i >= 0 && kept.length < maxKeep; i--) {
            const item = list[i];
            const k = `${item.time}_${Number(item.price).toFixed(2)}_${item.side}`;
            if (!seen.has(k)) {
                kept.push(item);
                seen.add(k);
            }
        }

        kept.sort((a, b) => a.time.localeCompare(b.time));
        return kept;
    }

    function onTopTradedSkipChange(newVal) {
        state.topTradedVolume.skipTime = newVal;
        try {
            localStorage.setItem('order_flow_skip_open_time', newVal);
        } catch (e) {}
        renderTopTradedVolumeUI();
    }
    window.onTopTradedSkipChange = onTopTradedSkipChange;

    function getFilteredTopTraded(rawList, skipTime) {
        if (!Array.isArray(rawList)) return [];
        let filtered = rawList;
        if (skipTime && skipTime !== 'none' && skipTime !== '09:15') {
            // E.g. skipTime = '09:30'. Cutoff is 09:30:59, so values start from 09:31:00 onwards
            const cutoff = skipTime.length === 5 ? (skipTime + ':59') : skipTime;
            filtered = rawList.filter(item => item.time > cutoff);
        }
        // Deduplicate very close prints (same minute & price) to show distinct institutional levels
        const map = new Map();
        filtered.forEach(item => {
            const key = `${item.time.substring(0, 5)}_${Number(item.price).toFixed(2)}_${item.side}`;
            if (!map.has(key) || item.qty > map.get(key).qty) {
                map.set(key, item);
            }
        });
        const arr = Array.from(map.values());
        arr.sort((a, b) => b.qty - a.qty);
        return arr.slice(0, 10);
    }

    function recordTradedVolumePrint(side, price, qty, timeStr = null) {
        const parsedQty = parseInt(qty);
        const parsedPrice = parseFloat(price);
        // Ignore retail noise ticks (< 500 qty) to preserve institutional integrity
        if (!parsedQty || parsedQty < 500 || !parsedPrice || parsedPrice <= 0) return;

        const time = timeStr || new Date().toLocaleTimeString('en-IN', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
        const isAsk = side === 'ASK' || side === 'BUY';
        const normSide = isAsk ? 'ASK' : 'BID';

        const newItem = {
            time: time,
            price: parsedPrice,
            qty: parsedQty,
            side: normSide
        };

        const workingDate = "{{ $workingDate }}";
        if (isAsk) {
            state.topTradedVolume.allAsk = mergeSessionTrades(state.topTradedVolume.allAsk, [newItem]);
            if (state.topTradedVolume.allAsk.length > 500) {
                state.topTradedVolume.allAsk = pruneTradesKeepHighest(state.topTradedVolume.allAsk, 300, state.topTradedVolume.sessionAnchorAsk);
            }
            try {
                localStorage.setItem(`order_flow_all_ask_${workingDate}`, JSON.stringify(state.topTradedVolume.allAsk));
            } catch (e) {}
        } else {
            state.topTradedVolume.allBid = mergeSessionTrades(state.topTradedVolume.allBid, [newItem]);
            if (state.topTradedVolume.allBid.length > 500) {
                state.topTradedVolume.allBid = pruneTradesKeepHighest(state.topTradedVolume.allBid, 300, state.topTradedVolume.sessionAnchorBid);
            }
            try {
                localStorage.setItem(`order_flow_all_bid_${workingDate}`, JSON.stringify(state.topTradedVolume.allBid));
            } catch (e) {}
        }

        // 💾 Persist Trade Print to MySQL Database for Historical Backtesting
        queueTradedVolumeForDbSync(newItem);

        renderTopTradedVolumeUI();
    }
    window.recordTradedVolumePrint = recordTradedVolumePrint;

    // ── DATABASE PERSISTENCE FOR HIGHEST TRADED VOLUME FOOTPRINT ──
    let pendingTradedVolumeSync = [];
    let tradedVolumeSyncTimer = null;
    let isTradedVolumeSyncing = false;

    function queueTradedVolumeForDbSync(item) {
        if (!item || item.qty < 500) return;
        pendingTradedVolumeSync.push(item);

        if (pendingTradedVolumeSync.length >= 10) {
            flushTradedVolumeSync();
        } else if (!tradedVolumeSyncTimer) {
            tradedVolumeSyncTimer = setTimeout(flushTradedVolumeSync, 4000);
        }
    }

    async function flushTradedVolumeSync() {
        if (tradedVolumeSyncTimer) {
            clearTimeout(tradedVolumeSyncTimer);
            tradedVolumeSyncTimer = null;
        }

        if (pendingTradedVolumeSync.length === 0 || isTradedVolumeSyncing) return;

        const batch = [...pendingTradedVolumeSync];
        pendingTradedVolumeSync = [];
        await syncBatchTradedVolumesToDb(batch);
    }

    async function syncBatchTradedVolumesToDb(trades) {
        if (!Array.isArray(trades) || trades.length === 0) return;
        isTradedVolumeSyncing = true;

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
            const payload = {
                trade_date: "{{ $workingDate }}",
                symbol: state.symbol || 'NIFTY',
                instrument: state.depthData?.symbol || null,
                source: state.feedMode === 'sim' ? 'SIMULATOR' : 'LIVE_TICK',
                trades: trades.slice(0, 100)
            };

            const resp = await fetch('{{ route('api.order-flow.save-traded-volume') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            if (!resp.ok) {
                console.warn('Backend responded with error saving traded volume batch:', resp.status);
            }
        } catch (e) {
            console.warn('Error syncing traded volume prints to DB:', e);
        } finally {
            isTradedVolumeSyncing = false;
        }
    }

    // Flush any pending prints before page unload
    window.addEventListener('beforeunload', () => {
        if (pendingTradedVolumeSync.length > 0) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
            const payload = JSON.stringify({
                trade_date: "{{ $workingDate }}",
                symbol: state.symbol || 'NIFTY',
                instrument: state.depthData?.symbol || null,
                source: state.feedMode === 'sim' ? 'SIMULATOR' : 'LIVE_TICK',
                trades: pendingTradedVolumeSync
            });
            try {
                navigator.sendBeacon('{{ route('api.order-flow.save-traded-volume') }}', new Blob([payload], { type: 'application/json' }));
            } catch (e) {}
        }
    });

    function renderTopTradedVolumeUI() {
        const skipTime = state.topTradedVolume.skipTime || '09:30';
        const askList = getFilteredTopTraded(state.topTradedVolume.allAsk, skipTime);
        const bidList = getFilteredTopTraded(state.topTradedVolume.allBid, skipTime);

        const maxAsk = askList.length > 0 ? askList[0].qty : 0;
        const maxBid = bidList.length > 0 ? bidList[0].qty : 0;
        const sessionMax = Math.max(maxAsk, maxBid, 1);

        // Peak badges in summary strip
        const peakAskEl = document.getElementById('top-traded-peak-ask');
        const peakBidEl = document.getElementById('top-traded-peak-bid');
        const biasPillEl = document.getElementById('top-traded-bias-pill');

        if (peakAskEl) {
            if (askList.length > 0) {
                peakAskEl.innerHTML = `${askList[0].qty.toLocaleString()} <span class="text-slate-400 font-sans text-[8.5px]">@ ₹${askList[0].price.toFixed(2)} (${askList[0].time})</span>`;
            } else {
                peakAskEl.textContent = '--';
            }
        }
        if (peakBidEl) {
            if (bidList.length > 0) {
                peakBidEl.innerHTML = `${bidList[0].qty.toLocaleString()} <span class="text-slate-400 font-sans text-[8.5px]">@ ₹${bidList[0].price.toFixed(2)} (${bidList[0].time})</span>`;
            } else {
                peakBidEl.textContent = '--';
            }
        }

        if (biasPillEl) {
            const totalAsk = askList.reduce((sum, x) => sum + x.qty, 0);
            const totalBid = bidList.reduce((sum, x) => sum + x.qty, 0);
            if (totalAsk > totalBid * 1.25) {
                biasPillEl.className = 'px-1.5 py-0.2 rounded font-black text-[9px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/40';
                biasPillEl.textContent = '🟢 BUYERS DOMINANT';
            } else if (totalBid > totalAsk * 1.25) {
                biasPillEl.className = 'px-1.5 py-0.2 rounded font-black text-[9px] bg-rose-500/20 text-rose-300 border border-rose-500/40';
                biasPillEl.textContent = '🔴 SELLERS DOMINANT';
            } else {
                biasPillEl.className = 'px-1.5 py-0.2 rounded font-black text-[9px] bg-slate-900 text-slate-400 border border-slate-800';
                biasPillEl.textContent = '⚖️ BALANCED TAPE';
            }
        }

        // Render Ask list
        const askContainer = document.getElementById('list-top-traded-ask');
        if (askContainer) {
            if (askList.length === 0) {
                askContainer.innerHTML = `<div class="text-slate-500 text-[10px] py-3 text-center italic">No Ask prints after ${skipTime}...</div>`;
            } else {
                askContainer.innerHTML = askList.map((item, idx) => {
                    const pct = Math.min(100, Math.max(12, Math.round((item.qty / sessionMax) * 100)));
                    return `
                        <div class="flex items-center justify-between text-[10px] px-2 py-1 rounded bg-slate-900/80 border border-slate-800/60 hover:bg-slate-800/80 transition relative overflow-hidden group">
                            <div class="absolute inset-y-0 left-0 bg-emerald-500/15 pointer-events-none transition-all duration-300" style="width: ${pct}%;"></div>
                            <div class="flex items-center gap-1.5 z-10 shrink-0">
                                <span class="text-[9px] font-bold ${idx < 3 ? 'text-amber-400' : 'text-slate-400'} w-4">#${idx + 1}</span>
                                <span class="text-slate-300 font-mono text-[9.5px]">${item.time}</span>
                            </div>
                            <div class="flex items-center gap-2 z-10 shrink-0">
                                <span class="text-slate-400 font-mono text-[9.5px]">₹${item.price.toFixed(2)}</span>
                                <span class="font-black text-emerald-300 font-mono text-[10.5px] text-right min-w-[55px]">${item.qty.toLocaleString()}</span>
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }

        // Render Bid list
        const bidContainer = document.getElementById('list-top-traded-bid');
        if (bidContainer) {
            if (bidList.length === 0) {
                bidContainer.innerHTML = `<div class="text-slate-500 text-[10px] py-3 text-center italic">No Bid prints after ${skipTime}...</div>`;
            } else {
                bidContainer.innerHTML = bidList.map((item, idx) => {
                    const pct = Math.min(100, Math.max(12, Math.round((item.qty / sessionMax) * 100)));
                    return `
                        <div class="flex items-center justify-between text-[10px] px-2 py-1 rounded bg-slate-900/80 border border-slate-800/60 hover:bg-slate-800/80 transition relative overflow-hidden group">
                            <div class="absolute inset-y-0 left-0 bg-rose-500/15 pointer-events-none transition-all duration-300" style="width: ${pct}%;"></div>
                            <div class="flex items-center gap-1.5 z-10 shrink-0">
                                <span class="text-[9px] font-bold ${idx < 3 ? 'text-amber-400' : 'text-slate-400'} w-4">#${idx + 1}</span>
                                <span class="text-slate-300 font-mono text-[9.5px]">${item.time}</span>
                            </div>
                            <div class="flex items-center gap-2 z-10 shrink-0">
                                <span class="text-slate-400 font-mono text-[9.5px]">₹${item.price.toFixed(2)}</span>
                                <span class="font-black text-rose-300 font-mono text-[10.5px] text-right min-w-[55px]">${item.qty.toLocaleString()}</span>
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }
    }

    function switchTopTradedView(view) {
        state.topTradedVolume.activeView = view;
        const btnBoth = document.getElementById('btn-top-traded-both');
        const btnAsk = document.getElementById('btn-top-traded-ask');
        const btnBid = document.getElementById('btn-top-traded-bid');
        const colAsk = document.getElementById('col-top-traded-ask');
        const colBid = document.getElementById('col-top-traded-bid');
        const grid = document.getElementById('top-traded-grid');

        if (btnBoth) btnBoth.className = view === 'both' ? 'px-2 py-0.5 rounded bg-indigo-600 text-white transition cursor-pointer' : 'px-2 py-0.5 rounded text-slate-400 hover:text-white transition cursor-pointer';
        if (btnAsk) btnAsk.className = view === 'ask' ? 'px-2 py-0.5 rounded bg-emerald-600 text-white transition cursor-pointer' : 'px-2 py-0.5 rounded text-emerald-400 hover:text-white transition cursor-pointer';
        if (btnBid) btnBid.className = view === 'bid' ? 'px-2 py-0.5 rounded bg-rose-600 text-white transition cursor-pointer' : 'px-2 py-0.5 rounded text-rose-400 hover:text-white transition cursor-pointer';

        if (view === 'both') {
            if (colAsk) colAsk.classList.remove('hidden', 'sm:col-span-2');
            if (colBid) colBid.classList.remove('hidden', 'sm:col-span-2');
            if (grid) grid.className = 'grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs font-mono';
        } else if (view === 'ask') {
            if (colAsk) {
                colAsk.classList.remove('hidden');
                colAsk.classList.add('sm:col-span-2');
            }
            if (colBid) colBid.classList.add('hidden');
            if (grid) grid.className = 'grid grid-cols-1 gap-2 text-xs font-mono';
        } else if (view === 'bid') {
            if (colAsk) colAsk.classList.add('hidden');
            if (colBid) {
                colBid.classList.remove('hidden');
                colBid.classList.add('sm:col-span-2');
            }
            if (grid) grid.className = 'grid grid-cols-1 gap-2 text-xs font-mono';
        }
    }
    window.switchTopTradedView = switchTopTradedView;

    function toggleTopTradedCard() {
        const coll = document.getElementById('top-traded-collapsible');
        const icon = document.getElementById('top-traded-toggle-icon');
        if (!coll) return;
        const isHidden = coll.classList.contains('hidden');
        if (isHidden) {
            coll.classList.remove('hidden');
            if (icon) icon.textContent = '▲';
        } else {
            coll.classList.add('hidden');
            if (icon) icon.textContent = '▼';
        }
    }
    window.toggleTopTradedCard = toggleTopTradedCard;

    // ── 2B. PREVIOUS DAY INSTITUTIONAL ANCHORS (TOP 5 ASK & BID VS LIVE STREAM PRICE) ──
    function initPdAnchors() {
        applyPdAnchorsCollapseUI();
        applyPdAnchorsViewUI();
        const initialLtp = state.depthData.ltp || {{ (float) ($spotPrice ?? 0) }};
        renderPdAnchorsUI(initialLtp);
    }
    window.initPdAnchors = initPdAnchors;

    function togglePdAnchorsCard() {
        state.pdAnchors.isCollapsed = !state.pdAnchors.isCollapsed;
        try {
            localStorage.setItem('order_flow_pd_anchors_collapsed', state.pdAnchors.isCollapsed ? 'true' : 'false');
        } catch(e) {}
        applyPdAnchorsCollapseUI();
    }
    window.togglePdAnchorsCard = togglePdAnchorsCard;

    function applyPdAnchorsCollapseUI() {
        const body = document.getElementById('pd-anchors-body');
        const icon = document.getElementById('pd-anchors-toggle-icon');
        if (!body) return;
        if (state.pdAnchors.isCollapsed) {
            body.classList.add('hidden');
            if (icon) icon.textContent = '▼';
        } else {
            body.classList.remove('hidden');
            if (icon) icon.textContent = '▲';
        }
    }

    function switchPdAnchorsView(view) {
        state.pdAnchors.activeView = view;
        applyPdAnchorsViewUI();
    }
    window.switchPdAnchorsView = switchPdAnchorsView;

    function applyPdAnchorsViewUI() {
        const view = state.pdAnchors.activeView || 'both';
        const colAsk = document.getElementById('pd-col-ask');
        const colBid = document.getElementById('pd-col-bid');
        const btnBoth = document.getElementById('btn-pd-view-both');
        const btnAsk = document.getElementById('btn-pd-view-ask');
        const btnBid = document.getElementById('btn-pd-view-bid');

        if (colAsk && colBid) {
            if (view === 'ask') {
                colAsk.classList.remove('hidden');
                colBid.classList.add('hidden');
            } else if (view === 'bid') {
                colAsk.classList.add('hidden');
                colBid.classList.remove('hidden');
            } else {
                colAsk.classList.remove('hidden');
                colBid.classList.remove('hidden');
            }
        }

        const activeClass = 'px-1.5 py-0.5 rounded bg-indigo-600 text-white transition cursor-pointer';
        const inactiveAskClass = 'px-1.5 py-0.5 rounded text-emerald-400 hover:text-white transition cursor-pointer';
        const inactiveBidClass = 'px-1.5 py-0.5 rounded text-rose-400 hover:text-white transition cursor-pointer';
        const inactiveBothClass = 'px-1.5 py-0.5 rounded text-slate-400 hover:text-white transition cursor-pointer';

        if (btnBoth) btnBoth.className = view === 'both' ? activeClass : inactiveBothClass;
        if (btnAsk) btnAsk.className = view === 'ask' ? activeClass : inactiveAskClass;
        if (btnBid) btnBid.className = view === 'bid' ? activeClass : inactiveBidClass;
    }

    function renderPdAnchorsUI(forcedLtp = null) {
        const ltp = forcedLtp !== null ? forcedLtp : (state.depthData.ltp || 0);
        const ltpEl = document.getElementById('pd-live-ltp-display');
        if (ltpEl) {
            ltpEl.textContent = ltp > 0 ? `₹${ltp.toFixed(2)}` : '₹--';
        }

        const askList = state.pdAnchors.topAsk || [];
        const bidList = state.pdAnchors.topBid || [];

        const allQtys = [...askList.map(x => x.qty), ...bidList.map(x => x.qty)];
        const maxSessionQty = Math.max(...allQtys, 1);

        let nearestItem = null;
        let minAbsDiff = Infinity;
        let askAboveCount = 0;
        let bidAboveCount = 0;

        // 1. Render Top 5 Ask (Demand Anchors)
        const askContainer = document.getElementById('pd-list-ask');
        if (askContainer) {
            if (askList.length === 0) {
                askContainer.innerHTML = `<div class="text-slate-500 text-[9.5px] py-2 text-center italic">No Prev Day Ask volume recorded</div>`;
            } else {
                askContainer.innerHTML = askList.map((item, idx) => {
                    const price = parseFloat(item.price);
                    const qty = parseInt(item.qty);
                    const diff = ltp > 0 ? (ltp - price) : 0;
                    const absDiff = Math.abs(diff);

                    if (absDiff < minAbsDiff && ltp > 0) {
                        minAbsDiff = absDiff;
                        nearestItem = { side: 'Ask', rank: idx + 1, price: price, diff: diff };
                    }
                    if (diff > 0) askAboveCount++;

                    const fillPct = Math.min(100, Math.max(15, Math.round((qty / maxSessionQty) * 100)));

                    let diffBadgeHtml = '';
                    if (ltp <= 0) {
                        diffBadgeHtml = `<span class="px-1.5 py-0.2 rounded font-mono font-bold text-[9px] bg-slate-800 text-slate-400">Waiting LTP</span>`;
                    } else if (absDiff <= 3.5) {
                        diffBadgeHtml = `<span class="px-1.5 py-0.2 rounded font-mono font-black text-[9px] bg-amber-500/25 text-amber-300 border border-amber-400/60 animate-pulse" title="Testing Level Right Now (±${absDiff.toFixed(1)} pts)">🎯 AT LEVEL (${diff >= 0 ? '+' : ''}${diff.toFixed(1)})</span>`;
                    } else if (diff > 0) {
                        diffBadgeHtml = `<span class="px-1.5 py-0.2 rounded font-mono font-black text-[9px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/40" title="Holding Above Demand (+${diff.toFixed(2)} pts)">+${diff.toFixed(1)} pts</span>`;
                    } else {
                        diffBadgeHtml = `<span class="px-1.5 py-0.2 rounded font-mono font-black text-[9px] bg-rose-500/20 text-rose-300 border border-rose-500/40" title="Below Demand (${diff.toFixed(2)} pts)">${diff.toFixed(1)} pts</span>`;
                    }

                    return `
                        <div class="flex items-center justify-between text-[9.5px] px-2 py-1 rounded bg-slate-900/90 border border-slate-800/80 hover:bg-slate-800/80 transition relative overflow-hidden group">
                            <div class="absolute inset-y-0 left-0 bg-emerald-500/12 pointer-events-none transition-all duration-300" style="width: ${fillPct}%;"></div>
                            <div class="flex items-center gap-1.5 z-10 shrink-0">
                                <span class="text-[9px] font-black ${idx === 0 ? 'text-amber-400' : idx === 1 ? 'text-slate-200' : 'text-slate-400'} w-3.5">#${idx + 1}</span>
                                <span class="text-slate-400 font-mono text-[8.5px]">${item.time}</span>
                            </div>
                            <div class="flex items-center gap-2 z-10 shrink-0">
                                <span class="text-slate-200 font-mono font-bold text-[9.5px]">₹${price.toFixed(2)}</span>
                                <span class="font-black text-emerald-300 font-mono text-[9.5px]">${qty.toLocaleString()}</span>
                            </div>
                            <div class="flex items-center gap-1 z-10 shrink-0 text-right min-w-[70px] justify-end">
                                ${diffBadgeHtml}
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }

        // 2. Render Top 5 Bid (Supply Anchors)
        const bidContainer = document.getElementById('pd-list-bid');
        if (bidContainer) {
            if (bidList.length === 0) {
                bidContainer.innerHTML = `<div class="text-slate-500 text-[9.5px] py-2 text-center italic">No Prev Day Bid volume recorded</div>`;
            } else {
                bidContainer.innerHTML = bidList.map((item, idx) => {
                    const price = parseFloat(item.price);
                    const qty = parseInt(item.qty);
                    const diff = ltp > 0 ? (ltp - price) : 0;
                    const absDiff = Math.abs(diff);

                    if (absDiff < minAbsDiff && ltp > 0) {
                        minAbsDiff = absDiff;
                        nearestItem = { side: 'Bid', rank: idx + 1, price: price, diff: diff };
                    }
                    if (diff > 0) bidAboveCount++;

                    const fillPct = Math.min(100, Math.max(15, Math.round((qty / maxSessionQty) * 100)));

                    let diffBadgeHtml = '';
                    if (ltp <= 0) {
                        diffBadgeHtml = `<span class="px-1.5 py-0.2 rounded font-mono font-bold text-[9px] bg-slate-800 text-slate-400">Waiting LTP</span>`;
                    } else if (absDiff <= 3.5) {
                        diffBadgeHtml = `<span class="px-1.5 py-0.2 rounded font-mono font-black text-[9px] bg-amber-500/25 text-amber-300 border border-amber-400/60 animate-pulse" title="Testing Level Right Now (±${absDiff.toFixed(1)} pts)">🎯 AT LEVEL (${diff >= 0 ? '+' : ''}${diff.toFixed(1)})</span>`;
                    } else if (diff > 0) {
                        diffBadgeHtml = `<span class="px-1.5 py-0.2 rounded font-mono font-black text-[9px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/40" title="Reclaimed Supply (+${diff.toFixed(2)} pts)">+${diff.toFixed(1)} pts</span>`;
                    } else {
                        diffBadgeHtml = `<span class="px-1.5 py-0.2 rounded font-mono font-black text-[9px] bg-rose-500/20 text-rose-300 border border-rose-500/40" title="Below Supply (${diff.toFixed(2)} pts)">${diff.toFixed(1)} pts</span>`;
                    }

                    return `
                        <div class="flex items-center justify-between text-[9.5px] px-2 py-1 rounded bg-slate-900/90 border border-slate-800/80 hover:bg-slate-800/80 transition relative overflow-hidden group">
                            <div class="absolute inset-y-0 left-0 bg-rose-500/12 pointer-events-none transition-all duration-300" style="width: ${fillPct}%;"></div>
                            <div class="flex items-center gap-1.5 z-10 shrink-0">
                                <span class="text-[9px] font-black ${idx === 0 ? 'text-amber-400' : idx === 1 ? 'text-slate-200' : 'text-slate-400'} w-3.5">#${idx + 1}</span>
                                <span class="text-slate-400 font-mono text-[8.5px]">${item.time}</span>
                            </div>
                            <div class="flex items-center gap-2 z-10 shrink-0">
                                <span class="text-slate-200 font-mono font-bold text-[9.5px]">₹${price.toFixed(2)}</span>
                                <span class="font-black text-rose-300 font-mono text-[9.5px]">${qty.toLocaleString()}</span>
                            </div>
                            <div class="flex items-center gap-1 z-10 shrink-0 text-right min-w-[70px] justify-end">
                                ${diffBadgeHtml}
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }

        // 3. Update Nearest Anchor in Header Summary
        const nearestEl = document.getElementById('pd-nearest-anchor-text');
        if (nearestEl) {
            if (nearestItem) {
                const sign = nearestItem.diff >= 0 ? '+' : '';
                nearestEl.innerHTML = `<span class="${nearestItem.side === 'Ask' ? 'text-emerald-300' : 'text-rose-300'}">#${nearestItem.rank} ${nearestItem.side}</span> <span class="text-slate-400 font-sans text-[8px]">(₹${nearestItem.price.toFixed(0)})</span> <span class="${nearestItem.diff >= 0 ? 'text-emerald-400' : 'text-rose-400'}">${sign}${nearestItem.diff.toFixed(1)} pts</span>`;
            } else {
                nearestEl.textContent = '--';
            }
        }

        // 4. Update Regime Status Badge
        const regimeEl = document.getElementById('pd-regime-status-badge');
        if (regimeEl && ltp > 0) {
            const totalAnchors = askList.length + bidList.length;
            const totalAbove = askAboveCount + bidAboveCount;
            if (totalAnchors > 0 && totalAbove === totalAnchors) {
                regimeEl.className = 'px-1.5 py-0.2 rounded font-black text-[8px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 shrink-0';
                regimeEl.textContent = '🚀 ABOVE ALL ANCHORS';
            } else if (totalAnchors > 0 && totalAbove === 0) {
                regimeEl.className = 'px-1.5 py-0.2 rounded font-black text-[8px] bg-rose-500/20 text-rose-300 border border-rose-500/40 shrink-0';
                regimeEl.textContent = '📉 BELOW ALL ANCHORS';
            } else {
                regimeEl.className = 'px-1.5 py-0.2 rounded font-black text-[8px] bg-indigo-500/20 text-indigo-300 border border-indigo-500/40 shrink-0';
                regimeEl.textContent = `🎯 VALUE CORRIDOR (${totalAbove}/${totalAnchors} HELD)`;
            }
        }
    }
    window.renderPdAnchorsUI = renderPdAnchorsUI;

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

        // ── 1. COMPUTE TICK DELTA VOLUME ──
        let deltaV = 0;
        if (ltp > 0) {
            const pKey = ltp.toFixed(2);
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

        // ── 2. CUMULATIVE VOLUME DELTA (CVD) & DELTA DIVERGENCE RADAR ──
        updateCvdAndDivergence(deltaV, ltp, bestBid, bestAsk);

        // ── 3. ORDER BOOK SLOPE & DEPTH CURVATURE ASYMMETRY ──
        calculateBookSlope(bids, asks, ltp);

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
        const activeMarketOrder = (alertSettings.enableMarketOrder && state.activeMarketOrderAlert && state.activeMarketOrderAlert.expiresAt > Date.now()) 
            ? state.activeMarketOrderAlert 
            : null;

        // Priority 1: Multi-level continuous stacks
        if (allowedBidStack && (!allowedAskStack || allowedBidStack.totalQty >= allowedAskStack.totalQty)) {
            alertType = 'STACK_BUY';
            alertKey = `STACK_BUY_${allowedBidStack.lowP}_${allowedBidStack.highP}_${allowedBidStack.totalQty}`;
        } else if (allowedAskStack) {
            alertType = 'STACK_SELL';
            alertKey = `STACK_SELL_${allowedAskStack.lowP}_${allowedAskStack.highP}_${allowedAskStack.totalQty}`;
        } else if (activeMarketOrder) {
            // Priority 2: Immediate Aggressive Market Order Sweep (Tape Print)
            alertType = activeMarketOrder.type;
            alertKey = `MARKET_${activeMarketOrder.side}_${activeMarketOrder.price}_${activeMarketOrder.qty}_${activeMarketOrder.time}`;
        } else if (allowedBidBlock && (!allowedAskBlock || allowedBidBlock.qty >= allowedAskBlock.qty)) {
            // Priority 3: Single level heavy institutional block (Qty >= alertSettings.singleThreshold)
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
                    price: bidStack.lowP,
                    quantity: bidStack.totalQty,
                    title: `Bullish Stack (${bidStack.count} Levels)`,
                    details: `${bidStack.totalQty.toLocaleString()} Qty at ₹${bidStack.lowP.toFixed(2)} - ₹${bidStack.highP.toFixed(2)}`,
                    guidance: '⚡ Direction: GO LONG / BUY CALL'
                });

                const confBullMet = alertSettings.strictConfluence 
                    ? !!(state.confluenceState?.meetsStrictBull) 
                    : (!alertSettings.enableConfluence || ((state.confluenceState?.bullPillarsCount || 0) >= Math.min(2, state.confluenceState?.activePillarsCount || 4)));

                if (confBullMet && (alertSettings.autoLog || zeroClickSettings.autoLog || zeroClickSettings.forwardToZeroClick)) {
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
                        confluence: state.confluenceState?.totalConfluence || 75,
                        alignedPillars: state.confluenceState?.bullPillarsCount || 1,
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
                    price: askStack.highP,
                    quantity: askStack.totalQty,
                    title: `Bearish Stack (${askStack.count} Levels)`,
                    details: `${askStack.totalQty.toLocaleString()} Qty at ₹${askStack.lowP.toFixed(2)} - ₹${askStack.highP.toFixed(2)}`,
                    guidance: '⚡ Direction: GO SHORT / BUY PUT'
                });

                const confBearMet = alertSettings.strictConfluence 
                    ? !!(state.confluenceState?.meetsStrictBear) 
                    : (!alertSettings.enableConfluence || ((state.confluenceState?.bearPillarsCount || 0) >= Math.min(2, state.confluenceState?.activePillarsCount || 4)));

                if (confBearMet && (alertSettings.autoLog || zeroClickSettings.autoLog || zeroClickSettings.forwardToZeroClick)) {
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
                        confluence: state.confluenceState?.totalConfluence || 25,
                        alignedPillars: state.confluenceState?.bearPillarsCount || 1,
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
                    price: heavyBidBlock.price,
                    quantity: heavyBidBlock.qty,
                    level: heavyBidBlock.level,
                    title: `Heavy Bid Block: ${heavyBidBlock.qty.toLocaleString()} Qty`,
                    details: `₹${heavyBidBlock.price.toFixed(2)} (Level ${heavyBidBlock.level})`,
                    guidance: '🛡️ Strong Support Wall / Accumulation'
                });

                const confBullMet = alertSettings.strictConfluence 
                    ? !!(state.confluenceState?.meetsStrictBull) 
                    : (!alertSettings.enableConfluence || ((state.confluenceState?.bullPillarsCount || 0) >= Math.min(2, state.confluenceState?.activePillarsCount || 4)));

                if (confBullMet && (alertSettings.autoLog || zeroClickSettings.autoLog || zeroClickSettings.forwardToZeroClick)) {
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
                        confluence: state.confluenceState?.totalConfluence || 70,
                        alignedPillars: state.confluenceState?.bullPillarsCount || 1,
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
                    price: heavyAskBlock.price,
                    quantity: heavyAskBlock.qty,
                    level: heavyAskBlock.level,
                    title: `Heavy Ask Block: ${heavyAskBlock.qty.toLocaleString()} Qty`,
                    details: `₹${heavyAskBlock.price.toFixed(2)} (Level ${heavyAskBlock.level})`,
                    guidance: '🚧 Strong Resistance Wall / Supply'
                });

                const confBearMet = alertSettings.strictConfluence 
                    ? !!(state.confluenceState?.meetsStrictBear) 
                    : (!alertSettings.enableConfluence || ((state.confluenceState?.bearPillarsCount || 0) >= Math.min(2, state.confluenceState?.activePillarsCount || 4)));

                if (confBearMet && (alertSettings.autoLog || zeroClickSettings.autoLog || zeroClickSettings.forwardToZeroClick)) {
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
                        confluence: state.confluenceState?.totalConfluence || 30,
                        alignedPillars: state.confluenceState?.bearPillarsCount || 1,
                        rationale: `⚡ Institutional Ask Block: ${heavyAskBlock.qty.toLocaleString()} Qty at ₹${heavyAskBlock.price.toFixed(2)} (Level ${heavyAskBlock.level})`
                    });
                }
            }
        } else if (alertType === 'MARKET_BUY') {
            const mo = activeMarketOrder || state.activeMarketOrderAlert;
            banner.className = 'bg-gradient-to-br from-emerald-950 via-teal-950 to-slate-950 border-2 border-emerald-400/90 rounded-xl p-2.5 shadow-xl shadow-emerald-500/20 text-white space-y-1.5 transition-all duration-300';
            banner.innerHTML = `
                <div class="flex items-center justify-between gap-1">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-sm">💥</span>
                        <span class="px-1.5 py-0.2 rounded bg-emerald-400 text-slate-950 font-black text-[9px] uppercase tracking-wider">
                            MARKET BUY SWEEP
                        </span>
                    </div>
                    <button onclick="dismissAlertBanner()" class="text-slate-400 hover:text-white px-1.5 py-0.2 rounded text-[10px] bg-slate-950 border border-slate-800 cursor-pointer" title="Dismiss Alert">✕</button>
                </div>
                <div>
                    <p class="font-black text-emerald-300 text-xs font-mono leading-tight">
                        ${mo ? mo.qty.toLocaleString() : 'Trade'} Qty Executed @ ₹${mo ? mo.price.toFixed(2) : '--'}
                    </p>
                    <span class="text-[9px] text-slate-300 font-mono">Aggressive Buyer Lifting Offer (≥${parseInt(alertSettings.marketOrderThreshold).toLocaleString()})</span>
                </div>
                <div class="flex items-center justify-between gap-1 pt-1 border-t border-slate-800/80 text-[8.5px]">
                    <span class="text-emerald-300 font-bold bg-slate-950 px-1.5 py-0.2 rounded border border-emerald-500/40">
                        ⚡ DIRECTION: BULLISH MOMENTUM
                    </span>
                    <span class="text-amber-300/90 font-mono">🔊 ${soundProfileLabel} (Tape Sweep)</span>
                </div>
            `;
        } else if (alertType === 'MARKET_SELL') {
            const mo = activeMarketOrder || state.activeMarketOrderAlert;
            banner.className = 'bg-gradient-to-br from-rose-950 via-pink-950 to-slate-950 border-2 border-rose-400/90 rounded-xl p-2.5 shadow-xl shadow-rose-500/20 text-white space-y-1.5 transition-all duration-300';
            banner.innerHTML = `
                <div class="flex items-center justify-between gap-1">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-sm">🩸</span>
                        <span class="px-1.5 py-0.2 rounded bg-rose-500 text-white font-black text-[9px] uppercase tracking-wider">
                            MARKET SELL DUMP
                        </span>
                    </div>
                    <button onclick="dismissAlertBanner()" class="text-slate-400 hover:text-white px-1.5 py-0.2 rounded text-[10px] bg-slate-950 border border-slate-800 cursor-pointer" title="Dismiss Alert">✕</button>
                </div>
                <div>
                    <p class="font-black text-rose-300 text-xs font-mono leading-tight">
                        ${mo ? mo.qty.toLocaleString() : 'Trade'} Qty Executed @ ₹${mo ? mo.price.toFixed(2) : '--'}
                    </p>
                    <span class="text-[9px] text-slate-300 font-mono">Aggressive Seller Hitting Bid (≥${parseInt(alertSettings.marketOrderThreshold).toLocaleString()})</span>
                </div>
                <div class="flex items-center justify-between gap-1 pt-1 border-t border-slate-800/80 text-[8.5px]">
                    <span class="text-rose-300 font-bold bg-slate-950 px-1.5 py-0.2 rounded border border-rose-500/40">
                        ⚡ DIRECTION: BEARISH MOMENTUM
                    </span>
                    <span class="text-amber-300/90 font-mono">🔊 ${soundProfileLabel} (Tape Dump)</span>
                </div>
            `;
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
            side: event.side || 'BUY',
            title: event.title,
            details: event.details,
            sound: getSoundProfileLabel(alertSettings.soundProfile || 'crystal'),
            guidance: event.guidance || '',
            price: event.price || (state.depthData ? state.depthData.ltp : {{ $spotPrice }}),
            quantity: event.quantity || 0,
            level: event.level || null,
            confluence: event.confluence || null,
            instrument: event.instrument || (state.selectedTargetContract ? state.selectedTargetContract.name : 'NIFTY')
        };

        state.recentAlertEvents.unshift(alertItem);
        if (state.recentAlertEvents.length > 100) {
            state.recentAlertEvents.pop();
        }

        renderAlertFeedUI();

        // Feed into Session Volume Footprint (Top Traded Qty)
        let alertQty = parseInt(event.quantity) || 0;
        if (alertQty <= 0 && event.title) {
            const m = String(event.title).match(/(\d+[\d,]*)\s*Qty/i);
            if (m) alertQty = parseInt(m[1].replace(/,/g, ''));
        }
        if (alertQty <= 0 && event.details) {
            const m = String(event.details).match(/(\d+[\d,]*)\s*Qty/i);
            if (m) alertQty = parseInt(m[1].replace(/,/g, ''));
        }
        if (alertQty >= 500 && alertItem.price > 0) {
            const isAsk = ['SELL', 'ASK'].includes(String(alertItem.side || '').toUpperCase())
                || String(alertItem.type || '').includes('SELL')
                || String(alertItem.type || '').includes('ASK')
                || String(alertItem.title || '').toUpperCase().includes('ASK');
            recordTradedVolumePrint(isAsk ? 'ASK' : 'BID', alertItem.price, alertQty, alertItem.time);
        }

        // 💾 Persist Alert Event to Backend Database
        persistAlertToBackend(alertItem);
    }

    async function persistAlertToBackend(item) {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
            const payload = {
                mode: state.feedMode || 'live',
                symbol: state.symbol || 'NIFTY',
                instrument: item.instrument || 'NIFTY',
                alert_type: item.type,
                side: item.side,
                price: item.price,
                quantity: item.quantity || null,
                level: item.level || null,
                confluence_score: item.confluence || null,
                title: item.title,
                details: item.details,
                guidance: item.guidance,
                sound: item.sound
            };

            const resp = await fetch('{{ route('api.order-flow.log-alert') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });
            if (resp.ok) {
                const res = await resp.json();
                if (res.data && res.data.id) {
                    item.id = res.data.id;
                }
            }
        } catch(e) {
            console.warn('Failed to persist alert event to database:', e);
        }
    }

    window.loadPersistedAlerts = async function() {
        try {
            const resp = await fetch(`{{ route('api.order-flow.alert-logs') }}?date={{ $workingDate }}&mode=${state.feedMode}`);
            if (!resp.ok) return;
            const res = await resp.json();
            if (res.success && Array.isArray(res.alerts) && res.alerts.length > 0) {
                const mapped = res.alerts.map(a => ({
                    id: a.id,
                    timestamp: new Date(a.created_at || (a.trade_date + 'T' + a.alert_time)).getTime(),
                    time: a.alert_time,
                    type: a.alert_type,
                    side: a.side,
                    price: parseFloat(a.price) || 0,
                    quantity: parseInt(a.quantity) || 0,
                    level: a.level,
                    title: a.title,
                    details: a.details,
                    sound: a.sound || getSoundProfileLabel(alertSettings.soundProfile || 'crystal'),
                    guidance: a.guidance || ''
                }));

                const mapIds = new Set(mapped.map(m => m.id));
                const fresh = (state.recentAlertEvents || []).filter(item => !mapIds.has(item.id));
                state.recentAlertEvents = [...fresh, ...mapped].slice(0, 100);
                renderAlertFeedUI();

                // Sync session trades from backend database into topTradedVolume
                const incomingAsk = [];
                const incomingBid = [];
                res.alerts.forEach(a => {
                    let qty = parseInt(a.quantity) || 0;
                    if (qty <= 0 && a.title) {
                        const m = String(a.title).match(/(\d+[\d,]*)\s*Qty/i);
                        if (m) qty = parseInt(m[1].replace(/,/g, ''));
                    }
                    if (qty <= 0 && a.details) {
                        const m = String(a.details).match(/(\d+[\d,]*)\s*Qty/i);
                        if (m) qty = parseInt(m[1].replace(/,/g, ''));
                    }
                    if (qty <= 0) return;
                    const price = parseFloat(a.price) || 0;
                    if (price <= 0) return;

                    const isAsk = ['SELL', 'ASK'].includes(String(a.side || '').toUpperCase())
                        || String(a.alert_type || '').includes('SELL')
                        || String(a.alert_type || '').includes('ASK')
                        || String(a.title || '').toUpperCase().includes('ASK');

                    const item = {
                        time: a.alert_time,
                        price: price,
                        qty: qty,
                        side: isAsk ? 'ASK' : 'BID'
                    };
                    if (isAsk) incomingAsk.push(item);
                    else incomingBid.push(item);
                });

                if (incomingAsk.length > 0 || incomingBid.length > 0) {
                    if (incomingAsk.length > 0) {
                        state.topTradedVolume.sessionAnchorAsk = mergeSessionTrades(state.topTradedVolume.sessionAnchorAsk || [], incomingAsk);
                        state.topTradedVolume.allAsk = mergeSessionTrades(state.topTradedVolume.allAsk, incomingAsk);
                    }
                    if (incomingBid.length > 0) {
                        state.topTradedVolume.sessionAnchorBid = mergeSessionTrades(state.topTradedVolume.sessionAnchorBid || [], incomingBid);
                        state.topTradedVolume.allBid = mergeSessionTrades(state.topTradedVolume.allBid, incomingBid);
                    }
                    renderTopTradedVolumeUI();
                }
            }
        } catch(e) {
            console.warn('Failed loading persisted alerts:', e);
        }
    };

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
        state.expandedAlertIds = state.expandedAlertIds || new Set();

        state.recentAlertEvents.forEach((a, idx) => {
            const isBuy = (a.side === 'BUY');
            const isBlock = a.type.includes('BLOCK');
            const isStack = a.type.includes('STACK');
            const badgeBg = isBlock ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40' : (isBuy ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-rose-500/20 text-rose-300 border border-rose-500/40');
            const badgeIcon = isBlock ? '⚡ BLOCK' : (isStack ? '🌊 STACK' : (isBuy ? '🚀 BUY' : '🩸 SELL'));
            const alertKey = a.id ? `alert-${a.id}` : `alert-idx-${idx}`;
            const isExpanded = state.expandedAlertIds.has(alertKey);

            html += `
            <div class="rounded-lg bg-slate-950 border ${isBuy ? 'border-emerald-800/40' : 'border-rose-800/40'} text-[10px] overflow-hidden transition">
                <div onclick="toggleAlertFeedItem('${alertKey}')" class="flex items-center justify-between p-1.5 cursor-pointer hover:bg-slate-900/60 transition gap-1.5">
                    <div class="flex items-center gap-1.5 font-mono flex-wrap">
                        <span class="text-slate-400 text-[9px] font-bold">${a.time}</span>
                        <span class="px-1 py-0.2 rounded font-black text-[8px] uppercase ${badgeBg}">
                            ${badgeIcon}
                        </span>
                        <span class="font-bold text-white text-[10px] truncate max-w-[140px] sm:max-w-[200px]">${a.title}</span>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0 ml-auto">
                        <span class="font-bold text-[9px] ${isBuy ? 'text-emerald-400' : 'text-rose-400'} hidden sm:inline-block">${a.guidance}</span>
                        <span class="text-[8px] text-amber-400 font-mono bg-slate-900 px-1 py-0.2 rounded border border-slate-800">${a.sound}</span>
                        <span id="chevron-${alertKey}" class="text-slate-400 text-[8px]">${isExpanded ? '▲' : '▼'}</span>
                    </div>
                </div>
                <div id="body-${alertKey}" class="${isExpanded ? '' : 'hidden '}px-2 py-1 bg-slate-900/50 border-t border-slate-900 text-[9px] text-slate-300 flex items-center justify-between gap-1">
                    <span class="text-slate-400 font-mono">${a.details}</span>
                    <span class="font-bold ${isBuy ? 'text-emerald-400' : 'text-rose-400'} sm:hidden">${a.guidance}</span>
                </div>
            </div>
            `;
        });
        container.innerHTML = html;
    }

    window.toggleAlertFeedItem = function(key) {
        const body = document.getElementById(`body-${key}`);
        const chevron = document.getElementById(`chevron-${key}`);
        if (!body) return;
        state.expandedAlertIds = state.expandedAlertIds || new Set();
        if (body.classList.contains('hidden')) {
            body.classList.remove('hidden');
            if (chevron) chevron.textContent = '▲';
            state.expandedAlertIds.add(key);
        } else {
            body.classList.add('hidden');
            if (chevron) chevron.textContent = '▼';
            state.expandedAlertIds.delete(key);
        }
    };

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
        renderCvdUI();
        renderBookSlopeUI();
        renderTapeMechanicsCapsule();
        if (typeof renderPdAnchorsUI === 'function') {
            renderPdAnchorsUI(d.ltp);
        }
    }

    // ── CUMULATIVE VOLUME DELTA (CVD) & DIVERGENCE DETECTION ──
    function updateCvdAndDivergence(deltaV, ltp, bestBid, bestAsk) {
        let tickDelta = 0;
        let isAskHit = false;
        let isBidHit = false;
        const tm = state.tapeMechanics;
        const timeStr = new Date().toLocaleTimeString('en-IN', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });

        if (deltaV > 0) {
            if (ltp >= bestAsk) {
                tickDelta = deltaV; // Aggressive Buyer hitting Ask (Lifting the Offer)
                isAskHit = true;
                tm.askHits = (tm.askHits || 0) + 1;
                tm.bidHits = Math.max(0, (tm.bidHits || 0) - 1);
                tm.recentAskVolume = (tm.recentAskVolume || 0) * 0.82 + deltaV;
                tm.recentBidVolume = (tm.recentBidVolume || 0) * 0.82;
                if (deltaV >= 500) recordTradedVolumePrint('ASK', ltp, deltaV, timeStr);
            } else if (ltp <= bestBid) {
                tickDelta = -deltaV; // Aggressive Seller hitting Bid (Hitting the Bid)
                isBidHit = true;
                tm.bidHits = (tm.bidHits || 0) + 1;
                tm.askHits = Math.max(0, (tm.askHits || 0) - 1);
                tm.recentBidVolume = (tm.recentBidVolume || 0) * 0.82 + deltaV;
                tm.recentAskVolume = (tm.recentAskVolume || 0) * 0.82;
                if (deltaV >= 500) recordTradedVolumePrint('BID', ltp, deltaV, timeStr);
            } else {
                const drift = state.depthData.microPrice - state.depthData.midPrice;
                if (drift > 0.02) {
                    tickDelta = Math.round(deltaV * 0.7);
                    tm.recentAskVolume = (tm.recentAskVolume || 0) * 0.85 + (deltaV * 0.7);
                    if (tickDelta >= 500) recordTradedVolumePrint('ASK', ltp, tickDelta, timeStr);
                } else if (drift < -0.02) {
                    tickDelta = -Math.round(deltaV * 0.7);
                    isBidHit = true;
                    tm.recentBidVolume = (tm.recentBidVolume || 0) * 0.85 + (deltaV * 0.7);
                    if (Math.abs(tickDelta) >= 500) recordTradedVolumePrint('BID', ltp, Math.abs(tickDelta), timeStr);
                } else {
                    tickDelta = Math.round((Math.random() - 0.5) * deltaV * 0.25);
                }
            }

            // ── CHECK HEAVY AGGRESSIVE MARKET ORDER ALERT (TAPE SWEEP) ──
            const mThresh = parseInt(alertSettings.marketOrderThreshold || 2000);
            if (alertSettings.enableMarketOrder && deltaV >= mThresh && deltaV < 2500000) {
                const moSide = (isAskHit || tickDelta > 0) ? 'BUY' : 'SELL';
                const moType = moSide === 'BUY' ? 'MARKET_BUY' : 'MARKET_SELL';
                const moKey = `MARKET_${moSide}_${ltp.toFixed(2)}_${deltaV}_${timeStr}`;

                if (state.lastLoggedMarketAlertKey !== moKey) {
                    state.lastLoggedMarketAlertKey = moKey;

                    state.activeMarketOrderAlert = {
                        type: moType,
                        side: moSide,
                        price: ltp,
                        qty: deltaV,
                        time: timeStr,
                        expiresAt: Date.now() + 8000
                    };

                    if (state.soundEnabled && alertSettings.soundEnabled && alertSettings.soundMarket !== false) {
                        playSynthesizedAlert(alertSettings.soundProfile || 'crystal', moSide);
                    }

                    recordAlertEvent({
                        type: moType,
                        side: moSide,
                        price: ltp,
                        quantity: deltaV,
                        title: moSide === 'BUY'
                            ? `💥 Heavy Market Buy: ${deltaV.toLocaleString()} Qty`
                            : `💥 Heavy Market Sell: ${deltaV.toLocaleString()} Qty`,
                        details: moSide === 'BUY'
                            ? `₹${ltp.toFixed(2)} (Aggressive Buyer Lifting Offer)`
                            : `₹${ltp.toFixed(2)} (Aggressive Seller Hitting Bid)`,
                        guidance: moSide === 'BUY'
                            ? '⚡ Direction: BULLISH TAPE SWEEP'
                            : '🩸 Direction: BEARISH TAPE SWEEP'
                    });
                }
            }
        }

        state.cvd.current += tickDelta;
        state.depthData.cvd = state.cvd.current;
        state.cvd.sessionHigh = Math.max(state.cvd.sessionHigh, state.cvd.current);
        state.cvd.sessionLow = Math.min(state.cvd.sessionLow, state.cvd.current);

        const nowTs = Date.now();

        state.cvd.history.push({
            time: timeStr,
            ts: nowTs,
            ltp: ltp,
            cvd: state.cvd.current,
            delta: tickDelta
        });

        if (state.cvd.history.length > 60) {
            state.cvd.history.shift();
        }

        // Automatic Delta Divergence Detector across swing window
        if (state.cvd.history.length >= 10) {
            const h = state.cvd.history;
            const recent = h[h.length - 1];
            const prevWindow = h.slice(0, h.length - 3);

            let prevMaxPrice = -Infinity, prevMaxCvd = -Infinity;
            let prevMinPrice = Infinity, prevMinCvd = Infinity;

            prevWindow.forEach(pt => {
                if (pt.ltp > prevMaxPrice) { prevMaxPrice = pt.ltp; prevMaxCvd = pt.cvd; }
                if (pt.ltp < prevMinPrice) { prevMinPrice = pt.ltp; prevMinCvd = pt.cvd; }
            });

            // 1. Bearish Divergence (Absorption at Highs: Price breaks higher but CVD fails)
            if (recent.ltp >= prevMaxPrice && recent.cvd < (prevMaxCvd - 300)) {
                state.cvd.divergence = 'BEARISH';
                state.cvd.divergenceText = '⚠️ BEARISH DIVERGENCE (Aggressive Buying Absorbed)';
                state.cvd.lastDivergenceTime = nowTs;
            }
            // 2. Bullish Divergence (Absorption at Lows: Price breaks lower but CVD holds)
            else if (recent.ltp <= prevMinPrice && recent.cvd > (prevMinCvd + 300)) {
                state.cvd.divergence = 'BULLISH';
                state.cvd.divergenceText = '🎯 BULLISH DIVERGENCE (Aggressive Selling Absorbed)';
                state.cvd.lastDivergenceTime = nowTs;
            }
            else if (nowTs - state.cvd.lastDivergenceTime < 12000) {
                // Keep active recent divergence for 12 seconds so trader can act
            } else {
                state.cvd.divergence = 'NONE';
                state.cvd.divergenceText = state.cvd.current >= 0 
                    ? '▲ Buyer Volume Dominating (In Sync)' 
                    : '▼ Seller Volume Dominating (In Sync)';
            }
        }

        // ── DYNAMIC TAPE MECHANICS DETERMINATION ──
        const askVol = tm.recentAskVolume || 0;
        const bidVol = tm.recentBidVolume || 0;
        const isBearDiv = (state.cvd.divergence === 'BEARISH');
        const isBullDiv = (state.cvd.divergence === 'BULLISH');
        const heavyAsk = state.depthData?.heavyAskBlock;
        const heavyBid = state.depthData?.heavyBidBlock;

        if (isBearDiv || (heavyAsk && askVol > bidVol * 1.4 && heavyAsk.level <= 3)) {
            tm.state = 'ASK_ABSORPTION';
            tm.label = '⚡ ASK ABSORPTION';
            tm.subtext = 'Aggressive Buys Absorbed by Passive Sellers';
            tm.color = 'amber';
        } else if (isBullDiv || (heavyBid && bidVol > askVol * 1.4 && heavyBid.level <= 3)) {
            tm.state = 'BID_ABSORPTION';
            tm.label = '🛡️ BID ABSORPTION';
            tm.subtext = 'Aggressive Sells Absorbed by Passive Buyers';
            tm.color = 'cyan';
        } else if (isAskHit || askVol > Math.max(300, bidVol * 1.25) || tm.askHits >= 2) {
            tm.state = 'LIFTING_OFFER';
            tm.label = '🟢 LIFTING THE OFFER';
            tm.subtext = 'Aggressive Market Buying @ Ask (+Delta)';
            tm.color = 'emerald';
        } else if (isBidHit || bidVol > Math.max(300, askVol * 1.25) || tm.bidHits >= 2) {
            tm.state = 'HITTING_BID';
            tm.label = '🔴 HITTING THE BID';
            tm.subtext = 'Aggressive Market Selling @ Bid (-Delta)';
            tm.color = 'rose';
        } else {
            tm.state = 'BALANCED';
            tm.label = '⚖️ BALANCED AUCTION';
            tm.subtext = 'Equilibrium at Mid-Price';
            tm.color = 'slate';
        }
    }

    // ── ORDER BOOK SLOPE & CURVATURE ASYMMETRY CALCULATION ──
    function calculateBookSlope(bids, asks, ltp) {
        if (!bids.length || !asks.length || !ltp) return;

        // Near Book Slope (Top 5 levels)
        let cumBid5 = 0;
        const nBid5 = Math.min(5, bids.length);
        for (let i = 0; i < nBid5; i++) cumBid5 += bids[i].qty;
        const distBid5 = Math.max(0.10, ltp - bids[nBid5 - 1].price);
        const bidSlopeNear = Math.round(cumBid5 / distBid5);

        let cumAsk5 = 0;
        const nAsk5 = Math.min(5, asks.length);
        for (let i = 0; i < nAsk5; i++) cumAsk5 += asks[i].qty;
        const distAsk5 = Math.max(0.10, asks[nAsk5 - 1].price - ltp);
        const askSlopeNear = Math.round(cumAsk5 / distAsk5);

        // Deep Book Slope (All 30 levels)
        let cumBid30 = 0;
        const nBid30 = Math.min(30, bids.length);
        for (let i = 0; i < nBid30; i++) cumBid30 += bids[i].qty;
        const distBid30 = Math.max(0.50, ltp - bids[nBid30 - 1].price);
        const bidSlopeDeep = Math.round(cumBid30 / distBid30);

        let cumAsk30 = 0;
        const nAsk30 = Math.min(30, asks.length);
        for (let i = 0; i < nAsk30; i++) cumAsk30 += asks[i].qty;
        const distAsk30 = Math.max(0.50, asks[nAsk30 - 1].price - ltp);
        const askSlopeDeep = Math.round(cumAsk30 / distAsk30);

        const ratio = parseFloat((bidSlopeNear / Math.max(1, askSlopeNear)).toFixed(2));
        state.depthData.bookSlopeRatio = ratio;

        let asymmetry = 'NEUTRAL';
        let asymmetryText = `${ratio}x Balanced`;
        if (ratio >= 1.30) {
            asymmetry = 'BULLISH';
            asymmetryText = `${ratio}x Bull Slope`;
        } else if (ratio <= 0.70) {
            asymmetry = 'BEARISH';
            asymmetryText = `${ratio}x Bear Slope`;
        }

        state.bookSlope = {
            bidSlopeNear,
            askSlopeNear,
            bidSlopeDeep,
            askSlopeDeep,
            ratio,
            asymmetry,
            asymmetryText
        };
    }

    // ── RENDER CVD MINI-CHART & DIVERGENCE UI ──
    function renderCvdUI() {
        const valEl = document.getElementById('cvd-current-val');
        const badgeEl = document.getElementById('cvd-divergence-badge');
        const textEl = document.getElementById('cvd-divergence-text');
        const highEl = document.getElementById('cvd-stat-high');
        const lowEl = document.getElementById('cvd-stat-low');
        const canvas = document.getElementById('cvd-mini-canvas');

        const cvdVal = state.cvd.current;
        if (valEl) {
            valEl.textContent = `${cvdVal >= 0 ? '+' : ''}${cvdVal.toLocaleString()} Delta`;
            valEl.className = cvdVal > 0 ? 'text-xs font-black font-mono text-emerald-400' : (cvdVal < 0 ? 'text-xs font-black font-mono text-rose-400' : 'text-xs font-black font-mono text-slate-300');
        }

        if (highEl) highEl.textContent = `${state.cvd.sessionHigh >= 0 ? '+' : ''}${state.cvd.sessionHigh.toLocaleString()}`;
        if (lowEl) lowEl.textContent = `${state.cvd.sessionLow.toLocaleString()}`;

        if (badgeEl && textEl) {
            textEl.textContent = state.cvd.divergenceText;
            if (state.cvd.divergence === 'BULLISH') {
                badgeEl.className = 'px-2 py-0.5 rounded text-[9.5px] font-mono font-black bg-emerald-500/25 text-emerald-300 border border-emerald-400 shadow-[0_0_8px_rgba(16,185,129,0.3)] animate-pulse flex items-center gap-1 truncate max-w-[210px]';
            } else if (state.cvd.divergence === 'BEARISH') {
                badgeEl.className = 'px-2 py-0.5 rounded text-[9.5px] font-mono font-black bg-rose-500/25 text-rose-300 border border-rose-400 shadow-[0_0_8px_rgba(244,63,94,0.3)] animate-pulse flex items-center gap-1 truncate max-w-[210px]';
            } else {
                badgeEl.className = 'px-2 py-0.5 rounded text-[9.5px] font-mono font-bold bg-slate-950 text-slate-400 border border-slate-800 flex items-center gap-1 truncate max-w-[210px]';
            }
        }

        // Draw Canvas Mini Chart (Zero dependency, high FPS)
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        const dpr = window.devicePixelRatio || 1;
        const rect = canvas.getBoundingClientRect();
        if (rect.width > 0 && rect.height > 0) {
            if (canvas.width !== Math.floor(rect.width * dpr) || canvas.height !== Math.floor(rect.height * dpr)) {
                canvas.width = Math.floor(rect.width * dpr);
                canvas.height = Math.floor(rect.height * dpr);
            }
        }

        ctx.save();
        ctx.scale(dpr, dpr);
        const w = rect.width || 240;
        const h = rect.height || 68;

        ctx.clearRect(0, 0, w, h);

        const history = state.cvd.history;
        if (history.length < 2) {
            ctx.restore();
            return;
        }

        let minCvd = Math.min(0, ...history.map(p => p.cvd));
        let maxCvd = Math.max(0, ...history.map(p => p.cvd));
        let range = maxCvd - minCvd;
        if (range === 0) range = 100;
        const pad = range * 0.12;
        minCvd -= pad;
        maxCvd += pad;
        range = maxCvd - minCvd;

        const getY = (val) => h - ((val - minCvd) / range) * (h - 8) - 4;
        const getX = (idx) => (idx / (history.length - 1)) * (w - 10) + 5;

        // Draw Zero Baseline (Dashed)
        const zeroY = Math.max(2, Math.min(h - 2, getY(0)));
        ctx.beginPath();
        ctx.setLineDash([3, 3]);
        ctx.strokeStyle = '#334155'; // slate-700
        ctx.lineWidth = 1;
        ctx.moveTo(0, zeroY);
        ctx.lineTo(w, zeroY);
        ctx.stroke();
        ctx.setLineDash([]);

        // Area Fill under curve
        ctx.beginPath();
        ctx.moveTo(getX(0), zeroY);
        for (let i = 0; i < history.length; i++) {
            ctx.lineTo(getX(i), getY(history[i].cvd));
        }
        ctx.lineTo(getX(history.length - 1), zeroY);
        ctx.closePath();

        const grad = ctx.createLinearGradient(0, 0, 0, h);
        if (cvdVal >= 0) {
            grad.addColorStop(0, 'rgba(16, 185, 129, 0.28)');
            grad.addColorStop(1, 'rgba(16, 185, 129, 0.0)');
        } else {
            grad.addColorStop(0, 'rgba(244, 63, 94, 0.0)');
            grad.addColorStop(1, 'rgba(244, 63, 94, 0.28)');
        }
        ctx.fillStyle = grad;
        ctx.fill();

        // Stroke Line
        ctx.beginPath();
        for (let i = 0; i < history.length; i++) {
            const x = getX(i);
            const y = getY(history[i].cvd);
            if (i === 0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        }
        ctx.strokeStyle = cvdVal >= 0 ? '#10b981' : '#f43f5e';
        ctx.lineWidth = 2;
        ctx.stroke();

        // Latest Point Dot
        const lastX = getX(history.length - 1);
        const lastY = getY(cvdVal);
        ctx.beginPath();
        ctx.arc(lastX, lastY, 3, 0, Math.PI * 2);
        ctx.fillStyle = cvdVal >= 0 ? '#34d399' : '#fb7185';
        ctx.fill();

        // Radiating halo on Delta Divergence
        if (state.cvd.divergence !== 'NONE') {
            ctx.beginPath();
            ctx.arc(lastX, lastY, 6, 0, Math.PI * 2);
            ctx.strokeStyle = state.cvd.divergence === 'BULLISH' ? '#10b981' : '#f43f5e';
            ctx.lineWidth = 1.5;
            ctx.stroke();
        }

        ctx.restore();
    }

    // ── RENDER BOOK SLOPE & CURVATURE UI ──
    function renderBookSlopeUI() {
        const s = state.bookSlope;
        const bidVal = document.getElementById('val-bid-slope');
        const askVal = document.getElementById('val-ask-slope');
        const badge = document.getElementById('book-slope-asymmetry-badge');
        const bidBar = document.getElementById('bar-slope-bid');
        const askBar = document.getElementById('bar-slope-ask');
        const bidRatioLbl = document.getElementById('lbl-slope-bid-ratio');
        const askRatioLbl = document.getElementById('lbl-slope-ask-ratio');
        const descEl = document.getElementById('book-slope-desc');

        if (bidVal) bidVal.textContent = `${s.bidSlopeNear.toLocaleString()} qty/pt`;
        if (askVal) askVal.textContent = `${s.askSlopeNear.toLocaleString()} qty/pt`;

        const totalSlope = s.bidSlopeNear + s.askSlopeNear;
        const bidPct = totalSlope > 0 ? Math.round((s.bidSlopeNear / totalSlope) * 100) : 50;
        const askPct = 100 - bidPct;

        if (bidBar) bidBar.style.width = `${bidPct}%`;
        if (askBar) askBar.style.width = `${askPct}%`;

        if (bidRatioLbl) bidRatioLbl.textContent = `Bids ${bidPct}% (${s.bidSlopeNear.toLocaleString()}/pt)`;
        if (askRatioLbl) askRatioLbl.textContent = `Asks ${askPct}% (${s.askSlopeNear.toLocaleString()}/pt)`;

        if (badge) {
            badge.textContent = s.asymmetryText;
            if (s.asymmetry === 'BULLISH') {
                badge.className = 'px-1.5 py-0.2 rounded text-[9.5px] font-mono font-black bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 shadow-xs';
            } else if (s.asymmetry === 'BEARISH') {
                badge.className = 'px-1.5 py-0.2 rounded text-[9.5px] font-mono font-black bg-rose-500/20 text-rose-300 border border-rose-500/40 shadow-xs';
            } else {
                badge.className = 'px-1.5 py-0.2 rounded text-[9.5px] font-mono font-bold bg-slate-950 text-slate-300 border border-slate-800';
            }
        }

        if (descEl) {
            if (s.asymmetry === 'BULLISH') {
                descEl.innerHTML = `<span class="text-emerald-300 font-bold">▲ Strong Bid Slope:</span> Resting buy orders accumulate <strong class="text-white">${s.ratio}x faster</strong> per point drop than asks. Support is thick and active near LTP.`;
            } else if (s.asymmetry === 'BEARISH') {
                descEl.innerHTML = `<span class="text-rose-300 font-bold">▼ Aggressive Ask Slope:</span> Resting sell orders accumulate <strong class="text-white">${(1 / Math.max(0.01, s.ratio)).toFixed(2)}x faster</strong> per point rise than bids. Overhead ceiling is heavy.`;
            } else {
                descEl.innerHTML = `Order book slope is balanced across 30 levels. Resting liquidity builds symmetrically on both sides of LTP.`;
            }
        }
    }

    // ── RENDER DYNAMIC ORDER FLOW TAPE EXECUTION CAPSULE ──
    function renderTapeMechanicsCapsule() {
        const tm = state.tapeMechanics;
        if (!tm) return;

        const bannerCapsule = document.getElementById('banner-tape-capsule');
        const iconEl = document.getElementById('tape-capsule-icon');
        const textEl = document.getElementById('tape-capsule-text');
        const recTapeEl = document.getElementById('rec-tape-capsule');
        const cvdCapsule = document.getElementById('cvd-tape-capsule');

        let bgClass, borderClass, textClass, iconChar, shortLabel;

        switch (tm.state) {
            case 'LIFTING_OFFER':
                bgClass = 'bg-emerald-500/20';
                borderClass = 'border-emerald-500/50';
                textClass = 'text-emerald-300';
                iconChar = '🟢';
                shortLabel = 'LIFTING OFFER';
                break;
            case 'HITTING_BID':
                bgClass = 'bg-rose-500/20';
                borderClass = 'border-rose-500/50';
                textClass = 'text-rose-300';
                iconChar = '🔴';
                shortLabel = 'HITTING BID';
                break;
            case 'ASK_ABSORPTION':
                bgClass = 'bg-amber-500/20';
                borderClass = 'border-amber-500/50';
                textClass = 'text-amber-300';
                iconChar = '⚡';
                shortLabel = 'ASK ABSORPTION';
                break;
            case 'BID_ABSORPTION':
                bgClass = 'bg-cyan-500/20';
                borderClass = 'border-cyan-500/50';
                textClass = 'text-cyan-300';
                iconChar = '🛡️';
                shortLabel = 'BID ABSORPTION';
                break;
            default:
                bgClass = 'bg-slate-950';
                borderClass = 'border-slate-800';
                textClass = 'text-slate-400';
                iconChar = '⚖️';
                shortLabel = 'BALANCED AUCTION';
        }

        if (bannerCapsule) {
            bannerCapsule.className = `px-2 py-0.5 rounded text-[9.5px] font-black font-mono tracking-wider flex items-center gap-1.5 ${bgClass} ${textClass} border ${borderClass} shrink-0 shadow-xs transition-all duration-150 cursor-help`;
            bannerCapsule.title = `${tm.label}: ${tm.subtext}`;
            if (iconEl) iconEl.textContent = iconChar;
            if (textEl) textEl.textContent = shortLabel;
        }

        if (recTapeEl) {
            recTapeEl.className = `px-1.5 py-0.2 rounded text-[8.5px] font-black font-mono ${bgClass} ${textClass} border ${borderClass}`;
            recTapeEl.textContent = `TAPE: ${shortLabel}`;
            recTapeEl.title = tm.subtext;
        }

        if (cvdCapsule) {
            cvdCapsule.className = `px-1.5 py-0.2 rounded text-[9px] font-mono font-black ${bgClass} ${textClass} border ${borderClass} truncate`;
            cvdCapsule.textContent = `${iconChar} ${shortLabel}`;
            cvdCapsule.title = tm.subtext;
        }
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

        // Boost score on Delta Divergence (Absorption at extremes)
        if (state.cvd?.divergence === 'BULLISH') depthScore += 10;
        if (state.cvd?.divergence === 'BEARISH') depthScore -= 10;

        // Boost score on Book Slope Asymmetry
        if (state.bookSlope?.asymmetry === 'BULLISH') depthScore += 6;
        if (state.bookSlope?.asymmetry === 'BEARISH') depthScore -= 6;

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
        // 1. Pillar 1: NIFTY 50 Stocks Breadth (MUST be >= 80% in BOTH Weight AND Stocks)
        const reqBreadthWeight = parseFloat(alertSettings.pillarBreadthWeight || 80);
        const reqBreadthStocks = parseFloat(alertSettings.pillarBreadthStocks || 80); // 80% of 50 stocks = 40 stocks

        const mb = signals.market_breadth || {};
        const gw = parseFloat(mb.green_weight ?? (mb.advances ? ((mb.advances / (mb.advances + mb.declines)) * 100) : 50));
        const rw = parseFloat(mb.red_weight ?? (mb.declines ? ((mb.declines / (mb.advances + mb.declines)) * 100) : 50));
        const gcPct = parseFloat(mb.green_count_pct ?? (mb.advances ? ((mb.advances / 50) * 100) : 50));
        const rcPct = parseFloat(mb.red_count_pct ?? (mb.declines ? ((mb.declines / 50) * 100) : 50));

        // Master Strategic Anchor: NIFTY 50 Stock Breadth Engine
        // When Nifty 50 stocks trigger a signal (>= 80% in BOTH Weight and Stocks),
        // it locks our macro direction until an opposite Nifty 50 80%+ call triggers.
        if (signals.anchor_trigger) {
            state.masterAnchor = signals.anchor_trigger;
        } else if (unified && unified.has_call) {
            const uWeight = parseFloat(unified.breadth_weight || 0);
            const uCount = parseInt(unified.breadth_count || 0);
            const uCountPct = (uCount / 50) * 100;
            // Strictly require >= 80% in BOTH Weight and Stocks to establish master anchor!
            if (uWeight >= reqBreadthWeight && uCountPct >= reqBreadthStocks) {
                const uDir = unified.signal === 'BUY' ? 'BULLISH' : (unified.signal === 'SELL' ? 'BEARISH' : 'NEUTRAL');
                if (uDir !== 'NEUTRAL') {
                    state.masterAnchor = {
                        direction: uDir,
                        candleTime: unified.candle_time || '10:55',
                        strike: unified.strike || 23450,
                        weight: uWeight,
                        count: uCount,
                        count_pct: uCountPct,
                        netPoints: parseFloat(unified.net_points || 0),
                        timestamp: Date.now()
                    };
                }
            }
        }

        const rawAnchor = signals.anchor_trigger || state.masterAnchor;
        const isAnchorValid = rawAnchor && (rawAnchor.weight >= reqBreadthWeight) && ((rawAnchor.count_pct || rawAnchor.countPct || (rawAnchor.count * 2)) >= reqBreadthStocks);
        const activeAnchor = isAnchorValid ? rawAnchor : null;

        const isP1Bull = (gw >= reqBreadthWeight && gcPct >= reqBreadthStocks) ||
                         (activeAnchor && activeAnchor.direction === 'BULLISH');
        const isP1Bear = (rw >= reqBreadthWeight && rcPct >= reqBreadthStocks) ||
                         (activeAnchor && activeAnchor.direction === 'BEARISH');

        // 2. Pillar 2: NIFTY 50 Options Flow Parity (MUST be >= 70% in either BUY or SELL flow)
        const reqOptFlow = parseFloat(alertSettings.pillarOptionsFlow || 70);
        const of = signals.options_flow || {};
        const bullFlow = parseFloat(of.bull_flow_pct ?? 0);
        const bearFlow = parseFloat(of.bear_flow_pct ?? 0);

        const isP2Bull = (bullFlow >= reqOptFlow);
        const isP2Bear = (bearFlow >= reqOptFlow);

        // 3. Pillar 3: Trending OI & PCR Sentiment (Difference of CE and PE MUST match the flow)
        // Bullish: Put OI > Call OI (PE - CE > 0, PCR >= 1.05)
        // Bearish: Call OI > Put OI (CE - PE > 0, PCR <= 0.95)
        const toi = signals.trending_oi || {};
        const toiPcr = parseFloat(toi.pcr || signals.oi_buildup?.pcr || 1.0);
        const toiDiff = parseFloat(toi.diff_oi ?? 0);
        const reqBullPcr = parseFloat(alertSettings.pillarOiBullPcr || 1.05);
        const reqBearPcr = parseFloat(alertSettings.pillarOiBearPcr || 0.95);

        const isP3Bull = (toiPcr >= reqBullPcr && toiDiff >= 0);
        const isP3Bear = (toiPcr <= reqBearPcr && toiDiff <= 0);

        // 4. Pillar 4: 30-Level Deep Order Book & Microstructure
        const reqObi = parseFloat(alertSettings.pillarObiPct || 20);
        const isP4Bull = (d.obi30 >= reqObi || d.bidStack != null || (d.vpoc?.price > 0 && d.ltp >= d.vpoc.price && d.obi30 > 5) || state.cvd?.divergence === 'BULLISH' || (state.bookSlope?.asymmetry === 'BULLISH' && state.bookSlope?.ratio >= 1.35));
        const isP4Bear = (d.obi30 <= -reqObi || d.askStack != null || (d.vpoc?.price > 0 && d.ltp <= d.vpoc.price && d.obi30 < -5) || state.cvd?.divergence === 'BEARISH' || (state.bookSlope?.asymmetry === 'BEARISH' && state.bookSlope?.ratio <= 0.74));

        // Active pillars filtering based on user toggles
        const p1Active = alertSettings.enablePillar1 !== false;
        const p2Active = alertSettings.enablePillar2 !== false;
        const p3Active = alertSettings.enablePillar3 !== false;
        const p4Active = alertSettings.enablePillar4 !== false;
        const activePillarsCount = (p1Active ? 1 : 0) + (p2Active ? 1 : 0) + (p3Active ? 1 : 0) + (p4Active ? 1 : 0);

        const bullPillarsCount = (p1Active && isP1Bull ? 1 : 0) + 
                                 (p2Active && isP2Bull ? 1 : 0) + 
                                 (p3Active && isP3Bull ? 1 : 0) + 
                                 (p4Active && isP4Bull ? 1 : 0);

        const bearPillarsCount = (p1Active && isP1Bear ? 1 : 0) + 
                                 (p2Active && isP2Bear ? 1 : 0) + 
                                 (p3Active && isP3Bear ? 1 : 0) + 
                                 (p4Active && isP4Bear ? 1 : 0);

        // Update 4-Pillar Real-Time UI Boxes (50 Stock | Opt Flow | Trend OI | 30 D Book)
        updatePillarStatusBoxes(isP1Bull, isP1Bear, isP2Bull, isP2Bear, isP3Bull, isP3Bear, isP4Bull, isP4Bear);

        // Update Institutional Market Driver Diagnosis & Conflict Resolver
        updateDomDriverDiagnosis(gw, rw, gcPct, rcPct, bullFlow, bearFlow, d.obi30);

        // Check for Counter-Trend Liquidity Absorption Wall
        const isUnifiedSell = unified?.has_call && unified.signal === 'SELL';
        const isUnifiedBuy = unified?.has_call && unified.signal === 'BUY';

        const anchorPill = document.getElementById('anchor-candle-pill');
        const anchorBadge = document.getElementById('anchor-status-pill');
        const apiCapPill = document.getElementById('api-trade-cap-pill');

        if (activeAnchor) {
            const isBearAnchor = (activeAnchor.direction === 'BEARISH');
            if (anchorPill) {
                const cTime = activeAnchor.candleTime || activeAnchor.candle_time || 'Anchor';
                anchorPill.textContent = `${cTime} Candle · ${activeAnchor.weight}% ${isBearAnchor ? 'Red' : 'Green'} (${activeAnchor.count} Stocks)`;
            }
            if (anchorBadge) {
                anchorBadge.textContent = isBearAnchor ? '▼ DOWNSIDE BIAS' : '▲ UPSIDE BIAS';
                anchorBadge.className = isBearAnchor
                    ? 'px-1.5 py-0.2 rounded text-[8.5px] font-black bg-rose-500 text-white uppercase shadow-xs'
                    : 'px-1.5 py-0.2 rounded text-[8.5px] font-black bg-emerald-500 text-slate-950 uppercase shadow-xs';
            }
        } else {
            if (anchorPill) {
                anchorPill.textContent = 'Awaiting 80%+ Breadth Trigger';
            }
            if (anchorBadge) {
                anchorBadge.textContent = 'STAND ASIDE';
                anchorBadge.className = 'px-1.5 py-0.2 rounded text-[8.5px] font-bold bg-slate-800 text-slate-400 uppercase';
            }
        }

        // Daily trade execution limit check (strictly tracking broker/0click dispatched orders)
        const totalDispatched = getApiDispatchedCount();
        const isCapActive = zeroClickSettings.enableDailyCap !== false && parseInt(zeroClickSettings.maxDailyTrades, 10) > 0;
        const maxTradesAllowed = isCapActive ? parseInt(zeroClickSettings.maxDailyTrades, 10) : 0;
        const isTradeCapReached = isCapActive && (totalDispatched >= maxTradesAllowed);
        const isTimeAllowed = isApiTimePermitted();

        if (apiCapPill) {
            apiCapPill.classList.remove('hidden');
            if (!isTimeAllowed) {
                const startTimeStr = zeroClickSettings.apiStartTime || '09:30';
                apiCapPill.textContent = `🔒 API Locked (Starts ${startTimeStr})`;
                apiCapPill.className = 'px-1.5 py-0.2 rounded text-[8.5px] font-bold font-mono bg-indigo-500/20 text-indigo-300 border border-indigo-500/40';
                apiCapPill.title = `API order execution is locked until ${startTimeStr} IST (Opening Volatility Filter). Confluence signals, sound alarms, and Journal continue running 100%!`;
            } else if (!isCapActive) {
                apiCapPill.textContent = 'API: Unlimited';
                apiCapPill.className = 'px-1.5 py-0.2 rounded text-[8.5px] font-bold font-mono bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
                apiCapPill.title = 'Daily trade restriction disabled: unlimited API orders permitted';
            } else if (isTradeCapReached) {
                apiCapPill.textContent = `🔒 Daily API Cap: ${totalDispatched}/${maxTradesAllowed}`;
                apiCapPill.className = 'px-1.5 py-0.2 rounded text-[8.5px] font-bold font-mono bg-rose-500/20 text-rose-300 border border-rose-500/40';
                apiCapPill.title = 'Daily API trade limit reached: auto-dispatch is locked to protect capital';
            } else {
                apiCapPill.textContent = `API Cap: ${totalDispatched}/${maxTradesAllowed}`;
                apiCapPill.className = 'px-1.5 py-0.2 rounded text-[8.5px] font-bold font-mono bg-amber-500/20 text-amber-300 border border-amber-500/30';
                apiCapPill.title = `API trades executed today: ${totalDispatched} out of ${maxTradesAllowed}`;
            }
        }

        // Strict Filter Check (Requires 100% of currently enabled active pillars)
        const strictMode = !!alertSettings.strictConfluence;
        const meetsStrictBull = (activePillarsCount > 0 && bullPillarsCount === activePillarsCount);
        const meetsStrictBear = (activePillarsCount > 0 && bearPillarsCount === activePillarsCount);
        const reqMinPillars = Math.max(1, Math.min(3, activePillarsCount));

        // Nifty 50 Anchor Directional Lock:
        let isBuyQualified = false;
        let isSellQualified = false;

        if (p1Active && activeAnchor && activeAnchor.direction === 'BEARISH') {
            isSellQualified = alertSettings.enableConfluence && (strictMode ? meetsStrictBear : (bearPillarsCount >= reqMinPillars && isP1Bear));
            isBuyQualified = false;
        } else if (p1Active && activeAnchor && activeAnchor.direction === 'BULLISH') {
            isBuyQualified = alertSettings.enableConfluence && (strictMode ? meetsStrictBull : (bullPillarsCount >= reqMinPillars && isP1Bull));
            isSellQualified = false;
        } else if (p1Active && isP1Bull) {
            isBuyQualified = alertSettings.enableConfluence && (strictMode ? meetsStrictBull : (bullPillarsCount >= reqMinPillars));
            isSellQualified = false;
        } else if (p1Active && isP1Bear) {
            isSellQualified = alertSettings.enableConfluence && (strictMode ? meetsStrictBear : (bearPillarsCount >= reqMinPillars));
            isBuyQualified = false;
        } else if (!p1Active && activePillarsCount > 0) {
            isBuyQualified = alertSettings.enableConfluence && (strictMode ? meetsStrictBull : (bullPillarsCount >= reqMinPillars));
            isSellQualified = alertSettings.enableConfluence && (strictMode ? meetsStrictBear : (bearPillarsCount >= reqMinPillars));
        } else {
            // Stand aside when neither anchor nor active requirements met
            isBuyQualified = false;
            isSellQualified = false;
        }

        // Cache live confluence status globally
        state.confluenceState = {
            isBuyQualified,
            isSellQualified,
            meetsStrictBull,
            meetsStrictBear,
            bullPillarsCount,
            bearPillarsCount,
            activePillarsCount,
            totalConfluence,
            p1Active,
            p2Active,
            p3Active,
            p4Active
        };

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
                sigPill.textContent = strictMode ? `🎯 ${activePillarsCount}/${activePillarsCount} FULL CONFLUENCE` : 'STRONG BUY';
                sigPill.className = 'px-2 py-0.5 rounded-full text-[10px] font-black bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950';
            }
            if (sigIcon) sigIcon.textContent = '🔥';
            if (heroBadge) {
                heroBadge.textContent = strictMode ? `🎯 ${activePillarsCount}/${activePillarsCount} CONFLUENCE` : 'STRONG BUY';
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
                const confTag = strictMode ? `🎯 ${activePillarsCount}/${activePillarsCount} Confirmed · ` : '';
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
                    title: strictMode ? `🎯 ${activePillarsCount}/${activePillarsCount} Full Confluence BUY Setup` : 'High-Confidence BUY Setup',
                    details: `${totalConfluence}% Confluence (${instLabel})`,
                    guidance: '⚡ Direction: GO LONG / BUY CALL'
                });
            }

        } else if (isSellQualified) {
            if (sigText) sigText.textContent = '🩸 HIGH CONFIDENCE SELL / SHORT';
            if (sigPill) {
                sigPill.textContent = strictMode ? `🎯 ${activePillarsCount}/${activePillarsCount} FULL CONFLUENCE` : 'STRONG SELL';
                sigPill.className = 'px-2 py-0.5 rounded-full text-[10px] font-black bg-gradient-to-r from-rose-500 to-amber-500 text-white';
            }
            if (sigIcon) sigIcon.textContent = '🩸';
            if (heroBadge) {
                heroBadge.textContent = strictMode ? `🎯 ${activePillarsCount}/${activePillarsCount} CONFLUENCE` : 'STRONG SELL';
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
                const confTag = strictMode ? `🎯 ${activePillarsCount}/${activePillarsCount} Confirmed · ` : '';
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
                    title: strictMode ? `🎯 ${activePillarsCount}/${activePillarsCount} Full Confluence SELL Setup` : 'High-Confidence SELL Setup',
                    details: `${totalConfluence}% Confluence (${instLabel})`,
                    guidance: '⚡ Direction: GO SHORT / BUY PUT'
                });
            }

        } else {
            state.lastConfluenceSignalKey = 'NEUTRAL';
            if (sigText) {
                if (!alertSettings.enableConfluence) {
                    sigText.textContent = 'CONFLUENCE ALERTS MUTED';
                } else {
                    sigText.textContent = 'CONFLUENCE SCANNER';
                }
            }
            if (sigPill) {
                sigPill.textContent = 'SCANNING';
                sigPill.className = 'px-2 py-0.5 rounded-full text-[10px] font-black bg-slate-800 text-slate-400 border border-slate-700';
            }
            if (sigIcon) sigIcon.textContent = strictMode ? '🎯' : '⚖️';
            if (heroBadge) {
                heroBadge.textContent = strictMode ? `STRICT (${Math.max(bullPillarsCount, bearPillarsCount)}/${activePillarsCount})` : (!alertSettings.enableConfluence ? 'ALERTS OFF' : 'NEUTRAL CHOP');
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
                    if (p1Active && !isP1Bull && !isP1Bear) missing.push('Breadth');
                    if (p2Active && !isP2Bull && !isP2Bear) missing.push('Options Flow');
                    if (p3Active && !isP3Bull && !isP3Bear) missing.push('Trending OI');
                    if (p4Active && !isP4Bull && !isP4Bear) missing.push('30D Book');
                    tradeRationale.textContent = `Strict ${activePillarsCount}/${activePillarsCount} filter active · Pending alignment: ${missing.join(', ') || 'Mixed divergence'} · Stand aside`;
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

        // Strict Confluence Guard: If strict confluence is active, reject any signal not 100% aligned with active pillars!
        if (alertSettings.strictConfluence) {
            const isBuyCheck = tradeSetup.action.includes('CALL') || tradeSetup.action.includes('BUY') || (tradeSetup.signalType && tradeSetup.signalType.includes('BUY')) || (tradeSetup.action.includes('SELL PUT'));
            const isQual = isBuyCheck ? state.confluenceState?.meetsStrictBull : state.confluenceState?.meetsStrictBear;
            if (isQual === false) {
                console.log('⛔ [Strict Confluence Filter] Rejected trade setup not meeting full active confluence:', tradeSetup.action);
                return;
            }
        }

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
                    signal: sigs.market_breadth?.signal || (isBuy ? 'BULLISH' : 'BEARISH'),
                    score: sigs.market_breadth?.score || (isBuy ? 80 : 20),
                    green_weight: sigs.market_breadth?.green_weight,
                    red_weight: sigs.market_breadth?.red_weight,
                    green_count: sigs.market_breadth?.green_count,
                    red_count: sigs.market_breadth?.red_count,
                    green_count_pct: sigs.market_breadth?.green_count_pct,
                    red_count_pct: sigs.market_breadth?.red_count_pct,
                    advances: sigs.market_breadth?.advances ?? (isBuy ? 40 : 10),
                    declines: sigs.market_breadth?.declines ?? (isBuy ? 10 : 40),
                    details: sigs.market_breadth?.details || 'NIFTY 50 stock breadth'
                },
                options_flow: {
                    name: 'NIFTY Options Flow Parity',
                    signal: sigs.options_flow?.signal || (isBuy ? 'BULLISH' : 'BEARISH'),
                    score: isBuy ? (sigs.options_flow?.bull_flow_pct || 75) : (sigs.options_flow?.bear_flow_pct || 75),
                    bull_flow_pct: sigs.options_flow?.bull_flow_pct,
                    bear_flow_pct: sigs.options_flow?.bear_flow_pct,
                    details: sigs.options_flow?.details || 'Options Flow'
                },
                oi_flow: {
                    name: 'Options Flow Parity',
                    signal: sigs.options_flow?.signal || (isBuy ? 'BULLISH' : 'BEARISH'),
                    score: isBuy ? (sigs.options_flow?.bull_flow_pct || 75) : (sigs.options_flow?.bear_flow_pct || 75),
                    pcr: sigs.trending_oi?.pcr || sigs.oi_buildup?.pcr || 1.0,
                    details: sigs.options_flow?.details || 'Options Flow'
                },
                trending_oi: {
                    name: 'Trending OI & Sentiment',
                    signal: sigs.trending_oi?.signal || (isBuy ? 'BULLISH' : 'BEARISH'),
                    pcr: sigs.trending_oi?.pcr || sigs.oi_buildup?.pcr || 1.0,
                    diff_oi: sigs.trending_oi?.diff_oi || 0,
                    score: sigs.trending_oi?.score || 50,
                    details: sigs.trending_oi?.details || 'Trending OI'
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
                    vpoc_role: d.vpoc?.isSupport ? 'Absorption Support' : 'Trap Resistance',
                    cvd: state.cvd?.current || 0,
                    cvd_divergence: state.cvd?.divergence || null,
                    book_slope_ratio: state.bookSlope?.ratio || 1.0,
                    bid_slope: state.bookSlope?.bidSlopeNear || 0,
                    ask_slope: state.bookSlope?.askSlopeNear || 0
                },
                meta: {
                    strict_mode: !!alertSettings.strictConfluence,
                    aligned_pillars: tradeSetup.alignedPillars || 4
                }
            };

            // Enforce Daily Trade Cap for Broker / 0Click API Execution
            const totalDispatched = getApiDispatchedCount();
            const isCapActive = zeroClickSettings.enableDailyCap !== false && parseInt(zeroClickSettings.maxDailyTrades, 10) > 0;
            const maxTradesAllowed = isCapActive ? parseInt(zeroClickSettings.maxDailyTrades, 10) : 0;
            const isTradeCapReached = isCapActive && (totalDispatched >= maxTradesAllowed);

            const isTimeAllowed = isApiTimePermitted();
            let finalTriggerReason = tradeSetup.rationale || 'Liquidity event';
            if (!isTimeAllowed) {
                const startTimeStr = zeroClickSettings.apiStartTime || '09:30';
                finalTriggerReason += ` · 🔒 [Pre-${startTimeStr} Filter: Signal Logged Only, API Locked]`;
            } else if (isTradeCapReached) {
                finalTriggerReason += ` · 🔒 [Daily API Cap Reached: ${totalDispatched}/${maxTradesAllowed} - Signal Logged Only]`;
            }

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
                trigger_reason: finalTriggerReason,

                // Multi-engine snapshot
                factors: factorsSnapshot,

                // 0Click.in Trade Webhook Integration fields (Restricted by time window and user daily cap)
                dispatch_webhook: !!zeroClickSettings.forwardToZeroClick && !isTradeCapReached && isTimeAllowed,
                api_start_time: zeroClickSettings.apiStartTime || '09:30',
                enable_time_restriction: zeroClickSettings.enableTimeRestriction !== false,
                max_daily_trades: maxTradesAllowed,
                webhook_url: zeroClickSettings.webhookUrl || 'https://0click.in/api/webhook/tradingview',
                webhook_token: zeroClickSettings.webhookToken || '',
                execution_mode: zeroClickSettings.mode || 'SIMULATION',
                lots: parseInt(zeroClickSettings.lots || 1, 10),
                trade_style: zeroClickSettings.tradeStyle || 'OPTION_SELLING',
                protection_basis: zeroClickSettings.protectionBasis || 'STRIKE',
                sl_pts: zeroClickSettings.slPts || 20,
                tp_pts: zeroClickSettings.tpPts || 20,
                mtm_target: zeroClickSettings.mtmTarget || null,
                mtm_loss: zeroClickSettings.mtmLoss || null,
                auto_trailing_loss: !!zeroClickSettings.mtmAutoTrail
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

            const isSentTo0Click = (webhookStatus && webhookStatus.includes('SENT')) || (typeof l.notes === 'string' && l.notes.includes('0Click: SENT'));
            const isError0Click = (webhookStatus && webhookStatus.includes('ERR')) || (typeof l.notes === 'string' && l.notes.includes('0Click: FAILED'));

            let webhookBadge = '';
            if (isSentTo0Click) {
                webhookBadge = `<span class="px-2 py-0.5 rounded text-[9.5px] font-mono font-black bg-purple-500 text-white shadow-xs flex items-center gap-1 shadow-purple-900/40" title="${webhookStatus || 'Sent to 0Click'}"><span class="animate-pulse">⚡</span> 0CLICK: SENT</span>`;
            } else if (isError0Click) {
                webhookBadge = `<span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40" title="${webhookStatus}">⚡ 0CLICK: ERR</span>`;
            }

            // Distinct visual highlight for Journal entries that have been dispatched to 0Click API
            const cardHighlightClass = isSentTo0Click
                ? 'border-purple-500/80 ring-1 ring-purple-500/50 bg-gradient-to-r from-purple-950/25 via-slate-950 to-slate-950 shadow-lg shadow-purple-950/30 border-l-4 border-l-purple-400'
                : (isBuy ? 'border-emerald-800/50 shadow-emerald-950/20' : 'border-rose-800/50 shadow-rose-950/20');

            const dispatchedPill = isSentTo0Click
                ? `<span class="px-1.5 py-0.2 rounded text-[8.5px] font-mono font-bold text-purple-300 bg-purple-950/90 border border-purple-500/50 flex items-center gap-1"><span>✓</span> API DISPATCHED</span>`
                : '';

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
            const bSig = factors?.breadth?.signal || 'NEUTRAL';
            const oSig = factors?.options_flow?.signal || factors?.oi_flow?.signal || 'NEUTRAL';
            const qSig = factors?.trending_oi?.signal || 'NEUTRAL';
            const dSig = factors?.dom_depth?.signal || (l.obi_pct > 10 ? 'BULLISH' : (l.obi_pct < -10 ? 'BEARISH' : 'NEUTRAL'));

            const engines = [
                { label: '🏢 Stocks', sig: bSig, active: alertSettings.enablePillar1 !== false },
                { label: '📊 Options', sig: oSig, active: alertSettings.enablePillar2 !== false },
                { label: '📈 Trending OI', sig: qSig, active: alertSettings.enablePillar3 !== false },
                { label: '🌊 30-Depth Book', sig: dSig, active: alertSettings.enablePillar4 !== false }
            ];

            const activeCount = engines.filter(e => e.active).length || 4;
            let alignedCount = 0;
            engines.forEach(e => {
                if (e.active && e.sig === targetDir) alignedCount++;
            });

            if (alignedCount === activeCount && activeCount > 0) {
                alignmentBadge = `<span class="px-2 py-0.5 rounded text-[9.5px] font-black bg-emerald-500/30 text-emerald-300 border border-emerald-400 shadow-[0_0_8px_rgba(16,185,129,0.3)] animate-pulse">🎯 ${activeCount}/${activeCount} FULL CONFLUENCE</span>`;
            } else if (alignedCount >= Math.max(1, activeCount - 1)) {
                alignmentBadge = `<span class="px-2 py-0.5 rounded text-[9.5px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/40">⚡ ${alignedCount}/${activeCount} ALIGNMENT</span>`;
            } else {
                alignmentBadge = `<span class="px-2 py-0.5 rounded text-[9.5px] font-mono text-slate-400 bg-slate-900 border border-slate-800">${alignedCount}/${activeCount} Align</span>`;
            }

            // Extract exact values for the 4 pillars
            let bVal = '--';
            if (factors?.breadth?.green_weight !== undefined && factors?.breadth?.red_weight !== undefined) {
                bVal = isBuy 
                    ? `${factors.breadth.green_weight}%W · ${factors.breadth.green_count}S`
                    : `${factors.breadth.red_weight}%W · ${factors.breadth.red_count}S`;
            } else if (factors?.breadth?.advances !== undefined && factors?.breadth?.declines !== undefined) {
                bVal = `${factors.breadth.advances}A / ${factors.breadth.declines}D`;
            } else {
                bVal = factors?.breadth?.score ? `${factors.breadth.score}% Score` : (isBuy ? '80%W · 40S' : '80%W · 40S');
            }

            let oVal = '--';
            if (factors?.options_flow?.bull_flow_pct !== undefined && factors?.options_flow?.bear_flow_pct !== undefined) {
                oVal = isBuy 
                    ? `${factors.options_flow.bull_flow_pct}% Bull`
                    : `${factors.options_flow.bear_flow_pct}% Bear`;
            } else if (factors?.oi_flow?.score !== undefined) {
                oVal = `${factors.oi_flow.score}% Flow`;
            } else if (factors?.oi_flow?.pcr) {
                oVal = `${Number(factors.oi_flow.pcr).toFixed(2)} PCR`;
            } else {
                oVal = isBuy ? '75% Bull' : '75% Bear';
            }

            let qVal = '--';
            if (factors?.trending_oi?.pcr !== undefined) {
                const diffStr = factors.trending_oi.diff_oi ? ` (${factors.trending_oi.diff_oi > 0 ? '+' : ''}${Math.round(factors.trending_oi.diff_oi/100000)}L)` : '';
                qVal = `${Number(factors.trending_oi.pcr).toFixed(2)} PCR${diffStr}`;
            } else if (factors?.trending_oi?.pop_pct !== undefined) {
                qVal = `${factors.trending_oi.pop_pct}% POP`;
            } else {
                qVal = `${factors?.trending_oi?.score || 50}% Score`;
            }

            const dVal = factors?.dom_depth?.obi !== undefined 
                ? `${factors.dom_depth.obi > 0 ? '+' : ''}${factors.dom_depth.obi}% OBI`
                : (l.obi_pct !== undefined && l.obi_pct !== null ? `${l.obi_pct > 0 ? '+' : ''}${l.obi_pct}% OBI` : (isBuy ? '+14.6% OBI' : '-16.2% OBI'));

            const getPillarSigBadge = (sig, isActive) => {
                if (!isActive) return '<span class="text-slate-600 font-bold text-[9px]">OFF</span>';
                if (sig === 'BULLISH') return '<span class="text-emerald-400 font-black text-[9.5px]">▲ BULL</span>';
                if (sig === 'BEARISH') return '<span class="text-rose-400 font-black text-[9.5px]">▼ BEAR</span>';
                return '<span class="text-slate-400 font-bold text-[9.5px]">— NEUT</span>';
            };

            const p1On = alertSettings.enablePillar1 !== false;
            const p2On = alertSettings.enablePillar2 !== false;
            const p3On = alertSettings.enablePillar3 !== false;
            const p4On = alertSettings.enablePillar4 !== false;

            factorGridHtml = `
            <div class="grid grid-cols-4 gap-1.5 pt-1.5 border-t border-slate-900/90 text-xs font-mono">
                <div class="p-1.5 rounded-lg bg-slate-900/90 border border-slate-800 text-center space-y-0.5 ${p1On ? '' : 'opacity-40'}">
                    <span class="text-slate-400 block text-[9px] font-bold font-sans truncate">🏢 NIFTY 50</span>
                    <div class="font-mono font-black text-amber-200 text-[10px] truncate leading-tight">${bVal}</div>
                    <div>${getPillarSigBadge(bSig, p1On)}</div>
                </div>
                <div class="p-1.5 rounded-lg bg-slate-900/90 border border-slate-800 text-center space-y-0.5 ${p2On ? '' : 'opacity-40'}">
                    <span class="text-slate-400 block text-[9px] font-bold font-sans truncate">📊 Opt Flow</span>
                    <div class="font-mono font-black text-cyan-300 text-[10px] truncate leading-tight">${oVal}</div>
                    <div>${getPillarSigBadge(oSig, p2On)}</div>
                </div>
                <div class="p-1.5 rounded-lg bg-slate-900/90 border border-slate-800 text-center space-y-0.5 ${p3On ? '' : 'opacity-40'}">
                    <span class="text-slate-400 block text-[9px] font-bold font-sans truncate">📈 Trend OI</span>
                    <div class="font-mono font-black text-indigo-300 text-[10px] truncate leading-tight">${qVal}</div>
                    <div>${getPillarSigBadge(qSig, p3On)}</div>
                </div>
                <div class="p-1.5 rounded-lg bg-slate-900/90 border border-slate-800 text-center space-y-0.5 ${p4On ? '' : 'opacity-40'}">
                    <span class="text-slate-400 block text-[9px] font-bold font-sans truncate">🌊 30D Book</span>
                    <div class="font-mono font-black text-amber-300 text-[10px] truncate leading-tight">${dVal}</div>
                    <div>${getPillarSigBadge(dSig, p4On)}</div>
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

            const isCardExpanded = state.expandedJournalCardIds && state.expandedJournalCardIds.has(l.id);

            html += `
            <div id="journal-card-${l.id}" class="rounded-xl bg-slate-950 border ${cardHighlightClass} text-xs shadow-lg transition duration-200 overflow-hidden">
                {{-- Single Row Header: Default Always Visible (1 Compact Row) --}}
                <div onclick="toggleJournalCard(${l.id})" class="flex items-center justify-between gap-1.5 p-2 px-2.5 cursor-pointer hover:bg-slate-900/60 transition select-none flex-wrap sm:flex-nowrap">
                    <div class="flex items-center gap-1.5 font-mono flex-wrap">
                        <span class="text-slate-400 text-[10px] font-bold shrink-0">${l.signal_time}</span>
                        <span class="px-1.5 py-0.2 rounded font-black text-[9px] ${isBuy ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-rose-500/20 text-rose-300 border border-rose-500/40'}">
                            ${actionBadgeText}
                        </span>
                        ${styleBadge}
                        ${dispatchedPill}
                        <span class="font-black text-white text-xs truncate max-w-[130px] sm:max-w-[170px]">${l.instrument}</span>
                        ${l.opt_strike ? `<span class="text-amber-300 font-mono font-bold text-[9.5px] hidden sm:inline-block">⚡ ${l.opt_strike} ${l.opt_type}</span>` : ''}
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0 ml-auto">
                        ${alignmentBadge}
                        ${webhookBadge}
                        ${statusBadge}
                        <button type="button" class="w-5 h-5 flex items-center justify-center rounded text-slate-400 hover:text-white hover:bg-slate-800 transition text-[9px]" title="Toggle Details">
                            <span id="journal-chevron-${l.id}">${isCardExpanded ? '▲' : '▼'}</span>
                        </button>
                    </div>
                </div>

                {{-- Collapsible Details Body (DEFAULT TO HIDE) --}}
                <div id="journal-body-${l.id}" class="${isCardExpanded ? '' : 'hidden '}p-2.5 pt-0 space-y-2 border-t border-slate-900/90 text-xs">
                    ${optContractSnippet}

                    <div class="text-[10.5px] text-amber-300/95 font-sans leading-tight pt-1">${l.trigger_reason || l.action}</div>
                    
                    <div class="flex items-center justify-between text-[10.5px] text-slate-300 font-mono pt-1.5 border-t border-slate-900 flex-wrap gap-1.5">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span>Entry: <strong class="text-white">₹${l.entry_price}</strong></span>
                            <span class="text-rose-400 font-semibold">SL: ₹${l.stop_loss}</span>
                            <span class="text-emerald-400 font-semibold">TGT: ₹${l.target_price}</span>
                            <span class="text-amber-300 font-bold bg-slate-900 px-1.5 py-0.2 rounded border border-slate-800">${l.confluence_score}% Conf</span>
                        </div>
                        <div>
                            ${isSentTo0Click 
                                ? `<button type="button" onclick="event.stopPropagation(); confirmAndDispatchJournalItem(${l.id}, this)" class="px-2 py-0.5 rounded-lg bg-purple-950 hover:bg-purple-900 border border-purple-500/60 text-purple-200 text-[9.5px] font-bold transition flex items-center gap-1 cursor-pointer active:scale-95 shadow-xs" title="Already sent to 0Click API. Click to re-dispatch this trade to broker">
                                     <span>⚡ Re-send API</span>
                                   </button>`
                                : `<button type="button" onclick="event.stopPropagation(); confirmAndDispatchJournalItem(${l.id}, this)" class="px-2.5 py-0.5 rounded-lg bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white text-[9.5px] font-bold shadow-md hover:shadow-purple-500/30 transition flex items-center gap-1 cursor-pointer active:scale-95" title="Click to review and safely send this trade to Broker / 0Click API">
                                     <span class="text-amber-300 font-black">⚡</span>
                                     <span>Send to 0Click</span>
                                   </button>`
                            }
                        </div>
                    </div>

                    ${factorGridHtml}
                </div>
            </div>
            `;
        });

        container.innerHTML = html;
    }

    state.expandedJournalCardIds = state.expandedJournalCardIds || new Set();

    window.toggleJournalCard = function(id) {
        const body = document.getElementById(`journal-body-${id}`);
        const chevron = document.getElementById(`journal-chevron-${id}`);
        if (!body) return;
        state.expandedJournalCardIds = state.expandedJournalCardIds || new Set();
        if (body.classList.contains('hidden')) {
            body.classList.remove('hidden');
            if (chevron) chevron.textContent = '▲';
            state.expandedJournalCardIds.add(id);
        } else {
            body.classList.add('hidden');
            if (chevron) chevron.textContent = '▼';
            state.expandedJournalCardIds.delete(id);
        }
    };

    window.toggleAllJournalCards = function() {
        const bodies = document.querySelectorAll('[id^="journal-body-"]');
        if (!bodies.length) return;
        const anyHidden = Array.from(bodies).some(b => b.classList.contains('hidden'));
        state.expandedJournalCardIds = state.expandedJournalCardIds || new Set();
        bodies.forEach(b => {
            const idStr = b.id.replace('journal-body-', '');
            const id = parseInt(idStr, 10);
            const chevron = document.getElementById(`journal-chevron-${id}`);
            if (anyHidden) {
                b.classList.remove('hidden');
                if (chevron) chevron.textContent = '▲';
                state.expandedJournalCardIds.add(id);
            } else {
                b.classList.add('hidden');
                if (chevron) chevron.textContent = '▼';
                state.expandedJournalCardIds.delete(id);
            }
        });
        const label = document.getElementById('label-toggle-all-journal');
        if (label) {
            label.textContent = anyHidden ? '▲ Collapse All' : '▼ Expand All';
        }
    };

    window.confirmAndDispatchJournalItem = function(logId, btnEl) {
        if (!logId) return;
        const log = (state.cachedJournalLogs || []).find(l => l.id == logId);
        if (!log) {
            dispatchJournalItemManually(logId, btnEl);
            return;
        }

        const modal = document.getElementById('modal-dispatch-confirm');
        if (!modal) {
            if (confirm(`Confirm Order Dispatch:\n\nInstrument: ${log.instrument}\nAction: ${log.action}\nEntry: ₹${log.entry_price}\n\nSend this order to 0Click API now?`)) {
                dispatchJournalItemManually(logId, btnEl);
            }
            return;
        }

        state.pendingDispatchLogId = logId;
        state.pendingDispatchBtnEl = btnEl;

        const isBuy = log.action.includes('CALL') || log.action.includes('BUY') || (log.signal_type && log.signal_type.includes('BUY')) || log.action.includes('SELL PUT');
        const actionBadge = document.getElementById('confirm-modal-action-badge');
        const styleBadge = document.getElementById('confirm-modal-style-badge');
        const contractEl = document.getElementById('confirm-modal-contract');
        const lotsEl = document.getElementById('confirm-modal-lots');
        const entryEl = document.getElementById('confirm-modal-entry');
        const slEl = document.getElementById('confirm-modal-sl');
        const tgtEl = document.getElementById('confirm-modal-target');
        const modeEl = document.getElementById('confirm-modal-mode');
        const urlEl = document.getElementById('confirm-modal-url');
        const reasonEl = document.getElementById('confirm-modal-reason');
        const submitBtn = document.getElementById('btn-execute-confirmed-dispatch');

        if (actionBadge) {
            actionBadge.textContent = isBuy ? '▲ CALL / LONG' : '▼ PUT / SHORT';
            actionBadge.className = isBuy 
                ? 'px-2 py-0.5 rounded font-black text-[10px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' 
                : 'px-2 py-0.5 rounded font-black text-[10px] bg-rose-500/20 text-rose-300 border border-rose-500/40';
        }
        if (styleBadge) {
            const style = zeroClickSettings.tradeStyle || 'OPTION_SELLING';
            styleBadge.textContent = style === 'OPTION_BUYING' ? '🚀 OPTION BUYING' : '👑 OPTION SELLING';
            styleBadge.className = style === 'OPTION_BUYING'
                ? 'px-1.5 py-0.5 rounded text-[9px] font-bold bg-sky-500/15 text-sky-300 border border-sky-500/30'
                : 'px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30';
        }
        if (contractEl) {
            contractEl.textContent = log.opt_strike ? `NIFTY ${log.opt_strike} ${log.opt_type || ''}` : log.instrument;
        }
        if (lotsEl) {
            const lots = parseInt(zeroClickSettings.lots || 1, 10);
            lotsEl.textContent = `${lots} Lot${lots > 1 ? 's' : ''} (${lots * 25} Qty)`;
        }
        if (entryEl) entryEl.textContent = `₹${log.opt_entry || log.entry_price || '--'}`;
        if (slEl) slEl.textContent = `₹${log.opt_sl || log.stop_loss || '--'}`;
        if (tgtEl) tgtEl.textContent = `₹${log.opt_target || log.target_price || '--'}`;
        if (modeEl) {
            const mode = zeroClickSettings.mode || 'SIMULATION';
            modeEl.textContent = mode;
            modeEl.className = mode === 'LIVE'
                ? 'px-1.5 py-0.2 rounded font-mono font-bold text-[9.5px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                : 'px-1.5 py-0.2 rounded font-mono font-bold text-[9.5px] bg-purple-500/20 text-purple-300 border border-purple-500/40';
        }
        if (urlEl) {
            urlEl.textContent = (zeroClickSettings.webhookUrl || 'https://0click.in').replace(/^https?:\/\//, '');
        }
        if (reasonEl) {
            reasonEl.textContent = log.trigger_reason || log.action || 'High Confluence Setup';
        }

        const timeWarnEl = document.getElementById('confirm-modal-time-warn');
        const timeWarnTextEl = document.getElementById('confirm-modal-time-warn-text');
        if (timeWarnEl) {
            const isTimeAllowed = isApiTimePermitted();
            if (!isTimeAllowed) {
                const startTimeStr = zeroClickSettings.apiStartTime || '09:30';
                timeWarnEl.classList.remove('hidden');
                if (timeWarnTextEl) {
                    timeWarnTextEl.textContent = `Opening Time Alert: Current time is before configured ${startTimeStr} AM API execution time. Confirming will override the restriction and manually dispatch this order.`;
                }
            } else {
                timeWarnEl.classList.add('hidden');
            }
        }

        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = `<span class="text-amber-300 font-black">⚡</span><span>Yes, Dispatch Order</span>`;
        }

        modal.classList.remove('hidden');
    };

    window.closeDispatchConfirmModal = function() {
        const modal = document.getElementById('modal-dispatch-confirm');
        if (modal) modal.classList.add('hidden');
        state.pendingDispatchLogId = null;
        state.pendingDispatchBtnEl = null;
    };

    window.executeConfirmedDispatch = async function() {
        const submitBtn = document.getElementById('btn-execute-confirmed-dispatch');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = `<svg class="animate-spin -ml-0.5 mr-1.5 h-3.5 w-3.5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> Dispatched...`;
        }
        if (state.pendingDispatchLogId) {
            await dispatchJournalItemManually(state.pendingDispatchLogId, state.pendingDispatchBtnEl);
        }
        closeDispatchConfirmModal();
    };

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeDispatchConfirmModal();
        }
    });

    window.dispatchJournalItemManually = async function(logId, btnEl) {
        if (!logId) return;

        const originalHtml = btnEl ? btnEl.innerHTML : '';
        const originalClass = btnEl ? btnEl.className : '';
        if (btnEl) {
            btnEl.disabled = true;
            btnEl.innerHTML = `<svg class="animate-spin -ml-0.5 mr-1 h-3 w-3 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> Sending...`;
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
            const payload = {
                webhook_url: zeroClickSettings.webhookUrl || 'https://0click.in/api/webhook/tradingview',
                webhook_token: zeroClickSettings.webhookToken || '',
                execution_mode: zeroClickSettings.mode || 'SIMULATION',
                lots: parseInt(zeroClickSettings.lots || 1, 10),
                trade_style: zeroClickSettings.tradeStyle || 'OPTION_SELLING',
                protection_basis: zeroClickSettings.protectionBasis || 'STRIKE',
                sl_pts: zeroClickSettings.slPts || 20,
                tp_pts: zeroClickSettings.tpPts || 20,
                mtm_target: zeroClickSettings.mtmTarget || null,
                mtm_loss: zeroClickSettings.mtmLoss || null,
                auto_trailing_loss: !!zeroClickSettings.mtmAutoTrail
            };

            const resp = await fetch(`/api/order-flow/dispatch-log/${logId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const res = await resp.json();
            if (res.success) {
                if (btnEl) {
                    btnEl.className = 'px-2 py-0.5 rounded-lg bg-emerald-950 border border-emerald-500/60 text-emerald-300 text-[9.5px] font-bold flex items-center gap-1';
                    btnEl.innerHTML = `✓ Dispatched (${res.status_code || 200})`;
                }
                
                try {
                    playSynthesizedAlert('crystal', 'BUY');
                } catch(e) {}

                setTimeout(() => {
                    refreshSignalLogs();
                }, 600);
            } else {
                alert(`0Click API Dispatch Failed: ${res.message || res.error || 'Server error'}`);
                if (btnEl) {
                    btnEl.disabled = false;
                    btnEl.innerHTML = originalHtml;
                    btnEl.className = originalClass;
                }
            }
        } catch (e) {
            console.error('Error dispatching to 0Click:', e);
            alert('Network error while dispatching to 0Click API.');
            if (btnEl) {
                btnEl.disabled = false;
                btnEl.innerHTML = originalHtml;
                btnEl.className = originalClass;
            }
        }
    };

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
            if (tickCount % 12 === 0) {
                simVtt += Math.max(2200, (alertSettings.marketOrderThreshold || 2000) + 250);
            } else {
                simVtt += Math.floor(60 + Math.random() * 240);
            }
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
        if (typeof window.loadPersistedAlerts === 'function') {
            window.loadPersistedAlerts();
        }
    };

    function updateSpotUI(spot) {
        const spotEl = document.getElementById('val-spot-price');
        if (spotEl) spotEl.textContent = `₹${spot.toFixed(2)}`;
    }

    // ── 16. EVENT LISTENERS & SETUP ──
    document.addEventListener('DOMContentLoaded', async () => {
        loadAlertSettings();
        refreshSignalLogs();
        if (typeof window.loadPersistedAlerts === 'function') {
            window.loadPersistedAlerts();
        }
        if (typeof initRecCollapsibleState === 'function') {
            initRecCollapsibleState();
        }
        if (typeof initTopTradedVolume === 'function') {
            initTopTradedVolume();
        }
        if (typeof initPdAnchors === 'function') {
            initPdAnchors();
        }

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

        // Auto-sync logged alerts & session trades from database every 20s
        setInterval(() => {
            if (typeof window.loadPersistedAlerts === 'function') {
                window.loadPersistedAlerts();
            }
        }, 20000);
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

    // ── 17. CUMULATIVE EOD REPORT MODAL CONTROLLERS ──
    let _modalReportCache = null;

    window.openCumulativeReportModal = function(targetDate) {
        const modal = document.getElementById('modal-cumulative-report');
        if (!modal) return;
        modal.classList.remove('hidden');

        const dateInput = document.getElementById('modal-report-date-input');
        const selectedDate = targetDate || (dateInput ? dateInput.value : '{{ $workingDate }}');
        if (dateInput) dateInput.value = selectedDate;

        const fullBtn = document.getElementById('btn-modal-report-fullscreen');
        if (fullBtn) fullBtn.href = `{{ route('order-flow-depth.report') }}?date=${selectedDate}`;

        fetchModalCumulativeReport(selectedDate);
    };

    window.closeCumulativeReportModal = function() {
        const modal = document.getElementById('modal-cumulative-report');
        if (modal) modal.classList.add('hidden');
    };

    window.fetchModalCumulativeReport = async function(date) {
        const container = document.getElementById('modal-report-content');
        if (!container) return;

        container.innerHTML = `
            <div class="flex flex-col items-center justify-center py-12 text-slate-400 space-y-2">
                <span class="text-3xl animate-spin">⚡</span>
                <p class="text-xs font-mono font-bold text-amber-300">Computing EOD institutional synthesis for ${date}...</p>
                <p class="text-[10px] text-slate-500">Synthesizing Depth Alerts, Journal Setups, Value Area &amp; S/R Pivots</p>
            </div>
        `;

        const fullBtn = document.getElementById('btn-modal-report-fullscreen');
        if (fullBtn) fullBtn.href = `{{ route('order-flow-depth.report') }}?date=${date}`;

        try {
            const resp = await fetch(`{{ route('api.order-flow.cumulative-report') }}?date=${date}&mode=${state.feedMode}&symbol=NIFTY`);
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const res = await resp.json();
            if (res.success && res.report) {
                _modalReportCache = res.report;
                renderModalReportUI(res.report);
            } else {
                container.innerHTML = `<div class="p-6 text-center text-rose-400 text-xs font-bold">Failed to generate report for ${date}.</div>`;
            }
        } catch(e) {
            container.innerHTML = `<div class="p-6 text-center text-rose-400 text-xs font-bold">Error loading cumulative report: ${e.message}</div>`;
        }
    };

    function renderModalReportUI(r) {
        const container = document.getElementById('modal-report-content');
        if (!container) return;

        const dom = r.dominance_matrix;
        const j = r.journal_summary;
        const a = r.alerts_summary;
        const rng = r.range_summary;
        const tb = r.tomorrow_playbook;

        const bullPct = dom.bull_score_pct;
        const bearPct = dom.bear_score_pct;

        let levelsHtml = '';
        tb.levels.forEach(lvl => {
            const isRes = (lvl.type === 'RESISTANCE');
            const isSup = (lvl.type === 'SUPPORT');
            const bgClass = isRes ? 'bg-rose-950/20 border-rose-500/30 text-rose-200' : (isSup ? 'bg-emerald-950/20 border-emerald-500/30 text-emerald-200' : 'bg-amber-950/30 border-amber-500/50 text-amber-200');
            const badgeClass = isRes ? 'bg-rose-500 text-white' : (isSup ? 'bg-emerald-500 text-white' : 'bg-amber-400 text-slate-950');

            levelsHtml += `
                <div class="p-2 rounded-lg border flex items-center justify-between text-xs ${bgClass}">
                    <div class="flex items-center gap-2">
                        <span class="px-1.5 py-0.2 rounded font-mono font-black text-[10px] ${badgeClass}">${lvl.code}</span>
                        <div>
                            <span class="font-bold text-white text-[11px] block leading-tight">${lvl.level_name}</span>
                            <span class="text-[9.5px] text-slate-400 block leading-tight">${lvl.description}</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="font-mono text-xs font-black text-white">₹${parseFloat(lvl.price).toFixed(2)}</span>
                        <span class="text-[8.5px] uppercase font-bold text-amber-400 block">${lvl.strength}</span>
                    </div>
                </div>
            `;
        });

        container.innerHTML = `
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-2.5">
                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="text-[9.5px] uppercase font-black tracking-wider text-slate-400">WHO DOMINATED</span>
                        <span class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase ${dom.dominant_side === 'BULLS' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-rose-500/20 text-rose-300 border border-rose-500/40'}">
                            ${dom.archetype_tag}
                        </span>
                    </div>
                    <p class="text-xs font-black text-white leading-tight">${dom.who.verdict}</p>
                    <div class="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden flex mt-1.5">
                        <div class="bg-emerald-500 h-full" style="width: ${bullPct}%"></div>
                        <div class="bg-rose-500 h-full" style="width: ${bearPct}%"></div>
                    </div>
                    <div class="flex justify-between text-[9px] font-mono text-slate-400 pt-0.5">
                        <span class="text-emerald-400 font-bold">${bullPct}% Bull</span>
                        <span class="text-rose-400 font-bold">${bearPct}% Bear</span>
                    </div>
                </div>

                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-1">
                    <span class="text-[9.5px] uppercase font-black tracking-wider text-slate-400">VALUE AREA POC</span>
                    <div class="flex items-baseline gap-1">
                        <span class="text-lg font-black text-amber-300 font-mono">${dom.range.poc}</span>
                    </div>
                    <p class="text-[10px] text-slate-400">Shelf: <b class="text-white font-mono">${rng.dominant_shelf}</b></p>
                    <p class="text-[9.5px] text-slate-400">Range: <b class="text-amber-300 font-mono">${rng.day_range_pts} pts</b></p>
                </div>

                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-1">
                    <span class="text-[9.5px] uppercase font-black tracking-wider text-slate-400">JOURNAL SCORECARD</span>
                    <div class="flex items-baseline gap-1">
                        <span class="text-lg font-black ${j.net_pnl_pts >= 0 ? 'text-emerald-400' : 'text-rose-400'} font-mono">${j.net_pnl_pts >= 0 ? '+' : ''}${j.net_pnl_pts} pts</span>
                    </div>
                    <p class="text-[10px] text-emerald-400 font-bold">${j.win_rate_pct}% Win Rate (${j.target_hit} Hit / ${j.stopped_out} Stopped)</p>
                    <p class="text-[9.5px] text-slate-400">${j.total_signals} total trade setups</p>
                </div>

                <div class="bg-slate-950 p-3 rounded-xl border border-amber-500/40 space-y-1">
                    <span class="text-[9.5px] uppercase font-black tracking-wider text-amber-300">TOMORROW ANCHOR</span>
                    <div class="flex items-baseline gap-1">
                        <span class="text-lg font-black text-amber-300 font-mono">₹${Math.round(tb.central_pivot).toLocaleString()}</span>
                    </div>
                    <p class="text-[10px] text-slate-300 font-bold">Central Balance Pivot</p>
                    <p class="text-[9.5px] text-slate-400">Bullish above · Bearish below</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                
                <div class="space-y-3">
                    <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-2">
                        <span class="text-[10px] uppercase font-bold text-indigo-300">🏛️ MICROSTRUCTURE SYNTHESIS</span>
                        <p class="text-[11px] text-slate-300 leading-snug">${dom.who.description}</p>
                        <div class="pt-1.5 border-t border-slate-800 text-[10.5px] space-y-1">
                            <p><b class="text-slate-400">When:</b> Peak at ${dom.when.peak_window} · Opening: <span class="font-bold text-white">${dom.when.opening_verdict}</span></p>
                            <p><b class="text-slate-400">Which:</b> Magnet: <span class="text-indigo-300 font-bold">${dom.which.magnet_strike}</span></p>
                            <p><b class="text-slate-400">Ceiling:</b> <span class="text-rose-300 font-bold">${dom.which.resistance_strike}</span> · Floor: <span class="text-emerald-300 font-bold">${dom.which.support_strike}</span></p>
                        </div>
                    </div>

                    <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-2">
                        <span class="text-[10px] uppercase font-bold text-amber-300">🎯 TOMORROW'S TRADE BLUEPRINT</span>
                        
                        <div class="p-2 rounded-lg bg-emerald-950/20 border border-emerald-500/30 text-[11px] space-y-1">
                            <span class="font-black text-emerald-400 block">${tb.trade_plans.bull_plan.title}</span>
                            <p class="text-slate-300"><b class="text-slate-400">Trigger:</b> ${tb.trade_plans.bull_plan.trigger}</p>
                            <p class="text-emerald-300 font-mono"><b class="text-slate-400 font-sans">Targets:</b> ${tb.trade_plans.bull_plan.target_1} · ${tb.trade_plans.bull_plan.target_2}</p>
                        </div>

                        <div class="p-2 rounded-lg bg-rose-950/20 border border-rose-500/30 text-[11px] space-y-1">
                            <span class="font-black text-rose-400 block">${tb.trade_plans.bear_plan.title}</span>
                            <p class="text-slate-300"><b class="text-slate-400">Trigger:</b> ${tb.trade_plans.bear_plan.trigger}</p>
                            <p class="text-rose-300 font-mono"><b class="text-slate-400 font-sans">Targets:</b> ${tb.trade_plans.bear_plan.target_1} · ${tb.trade_plans.bear_plan.target_2}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] uppercase font-bold text-amber-300">📐 TOMORROW QUANT S/R MATRIX</span>
                        <span class="text-[9px] font-mono text-emerald-400 font-bold bg-emerald-950/60 px-1.5 py-0.5 rounded border border-emerald-800/40">NIFTY SPOT</span>
                    </div>
                    <div class="space-y-1.5">
                        ${levelsHtml}
                    </div>
                </div>

            </div>

            {{-- MODAL SECTION: TOP 20 HEAVY BLOCKS --}}
            <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-2 mt-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-800 pb-2">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-[9.5px] uppercase font-black text-amber-300">🏛️ TOP 20 HEAVY ORDER FLOW BLOCKS</span>
                            <span class="text-[9px] font-mono text-slate-400">(${r.heavy_blocks ? r.heavy_blocks.total_count : 0} detected)</span>
                        </div>
                        <p class="text-[10px] text-slate-400">Institutional order book walls, absorption, and high-volume tape prints.</p>
                    </div>
                    <div class="flex items-center gap-1 flex-wrap">
                        <button type="button" onclick="setModalBlockFilter('core', this)" class="modal-block-time-btn px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-600 text-white border border-indigo-400/30">
                            ⭐ Core Hours (09:30-15:15)
                        </button>
                        <button type="button" onclick="setModalBlockFilter('all', this)" class="modal-block-time-btn px-2 py-0.5 rounded text-[10px] font-bold bg-slate-900 text-slate-400 hover:text-white border border-slate-800">
                            All Hours
                        </button>
                        <button type="button" onclick="setModalBlockFilter('opening', this)" class="modal-block-time-btn px-2 py-0.5 rounded text-[10px] font-bold bg-slate-900 text-slate-400 hover:text-white border border-slate-800">
                            Opening (09:15-09:30)
                        </button>
                        <button type="button" onclick="setModalBlockFilter('closing', this)" class="modal-block-time-btn px-2 py-0.5 rounded text-[10px] font-bold bg-slate-900 text-slate-400 hover:text-white border border-slate-800">
                            Closing (15:15-15:30)
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto rounded border border-slate-800/80 custom-scrollbar max-h-64">
                    <table class="w-full text-left text-[11px] whitespace-nowrap">
                        <thead class="bg-slate-900/90 text-slate-400 uppercase font-black text-[9px] tracking-wider sticky top-0 border-b border-slate-800">
                            <tr>
                                <th class="py-1.5 px-2 text-center">#</th>
                                <th class="py-1.5 px-2">Time</th>
                                <th class="py-1.5 px-2">Strike</th>
                                <th class="py-1.5 px-2 text-center">Side</th>
                                <th class="py-1.5 px-2 text-right">Wall / Block Qty</th>
                                <th class="py-1.5 px-2 text-right">Spot Ref</th>
                                <th class="py-1.5 px-2 text-right">VPOC / CVD</th>
                                <th class="py-1.5 px-2">OI / Details</th>
                                <th class="py-1.5 px-2 text-center">Outcome</th>
                            </tr>
                        </thead>
                        <tbody id="modal-heavy-blocks-tbody" class="divide-y divide-slate-800/50 bg-slate-950 font-mono text-[10.5px]">
                            <!-- Dynamically populated -->
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        renderModalBlocksTable();
    }

    window._activeModalBlockFilter = 'core';

    window.setModalBlockFilter = function(filterType, btnEl) {
        window._activeModalBlockFilter = filterType;
        document.querySelectorAll('.modal-block-time-btn').forEach(b => {
            b.className = 'modal-block-time-btn px-2 py-0.5 rounded text-[10px] font-bold bg-slate-900 text-slate-400 hover:text-white border border-slate-800';
        });
        if (btnEl) {
            btnEl.className = 'modal-block-time-btn px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-600 text-white border border-indigo-400/30';
        }
        renderModalBlocksTable();
    };

    window.renderModalBlocksTable = function() {
        if (!_modalReportCache || !_modalReportCache.heavy_blocks) return;
        const allBlocks = _modalReportCache.heavy_blocks.all || [];
        const tbody = document.getElementById('modal-heavy-blocks-tbody');
        if (!tbody) return;

        let filtered = allBlocks.slice();
        if (window._activeModalBlockFilter === 'core') {
            filtered = filtered.filter(b => b.is_core_hours);
        } else if (window._activeModalBlockFilter === 'opening') {
            filtered = filtered.filter(b => b.is_opening);
        } else if (window._activeModalBlockFilter === 'closing') {
            filtered = filtered.filter(b => b.is_closing);
        }

        filtered.sort((a, b) => b.wall_qty - a.wall_qty);
        const top20 = filtered.slice(0, 20);

        if (top20.length === 0) {
            tbody.innerHTML = `<tr><td colspan="9" class="py-4 text-center text-slate-500 font-sans text-xs">No blocks found for this window.</td></tr>`;
            return;
        }

        let html = '';
        top20.forEach((b, idx) => {
            const isBid = (b.side === 'BID');
            const sideBadge = isBid 
                ? '<span class="px-1.5 py-0.2 rounded text-[9px] font-black bg-emerald-500/20 text-emerald-300">🟢 BID</span>'
                : '<span class="px-1.5 py-0.2 rounded text-[9px] font-black bg-rose-500/20 text-rose-300">🔴 ASK</span>';

            let outcomeBadge = '<span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-slate-800 text-slate-300">Active</span>';
            if (b.outcome === 'DEFENDED') {
                outcomeBadge = '<span class="px-1.5 py-0.2 rounded text-[9px] font-black bg-emerald-500/20 text-emerald-300">🎯 Defended</span>';
            } else if (b.outcome === 'SWEPT') {
                outcomeBadge = '<span class="px-1.5 py-0.2 rounded text-[9px] font-black bg-amber-500/20 text-amber-300">💥 Swept</span>';
            }

            html += `
                <tr class="hover:bg-slate-900/60 transition">
                    <td class="py-1.5 px-2 text-center text-slate-400 font-bold">${idx + 1}</td>
                    <td class="py-1.5 px-2 text-white font-bold whitespace-nowrap">${b.time}</td>
                    <td class="py-1.5 px-2 text-amber-300 font-bold">${b.strike}</td>
                    <td class="py-1.5 px-2 text-center">${sideBadge}</td>
                    <td class="py-1.5 px-2 text-right font-black text-amber-300">${b.wall_qty.toLocaleString()} <span class="text-[8.5px] text-slate-400 font-normal">qty</span></td>
                    <td class="py-1.5 px-2 text-right text-slate-200">₹${parseFloat(b.spot_price).toFixed(1)}</td>
                    <td class="py-1.5 px-2 text-right">
                        <span class="text-slate-300 block">${b.vpoc_volume ? (Math.round(b.vpoc_volume/1000) + 'k vol') : '—'}</span>
                        <span class="text-[9px] ${b.cvd > 0 ? 'text-emerald-400' : (b.cvd < 0 ? 'text-rose-400' : 'text-slate-500')} block">
                            ${b.cvd !== null && b.cvd !== undefined ? ((b.cvd > 0 ? '+' : '') + Math.round(b.cvd/1000) + 'k CVD') : '—'}
                        </span>
                    </td>
                    <td class="py-1.5 px-2 font-sans text-[10px] text-slate-300 max-w-xs truncate" title="${b.oi_details || ''}">
                        ${b.oi_details || '—'}
                    </td>
                    <td class="py-1.5 px-2 text-center font-sans">${outcomeBadge}</td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    };

    window.copyModalDeskBrief = function() {
        if (!_modalReportCache) {
            alert('Report not loaded yet.');
            return;
        }
        const r = _modalReportCache;
        const text = `🏛️ **NIFTY EOD CUMULATIVE INSTITUTIONAL REPORT (${r.meta.trade_date})**
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 **WHO DOMINATED:** ${r.dominance_matrix.who.verdict} (Bulls: ${r.dominance_matrix.bull_score_pct}% | Bears: ${r.dominance_matrix.bear_score_pct}%)
🎯 **ARCHETYPE:** ${r.dominance_matrix.archetype}
⏱️ **PEAK WINDOW:** ${r.dominance_matrix.when.peak_window}
📍 **POC (POINT OF CONTROL):** ${r.dominance_matrix.range.poc}
📏 **VALUE AREA SHELF:** ${r.range_summary.dominant_shelf}
📖 **JOURNAL WIN RATE:** ${r.journal_summary.win_rate_pct}% (Net PnL: ${r.journal_summary.net_pnl_pts} Pts)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📐 **TOMORROW'S KEY QUANT LEVELS:**
• R3: ₹${r.tomorrow_playbook.levels[0].price}
• R2: ₹${r.tomorrow_playbook.levels[1].price}
• R1: ₹${r.tomorrow_playbook.levels[2].price}
⚡ **CENTRAL PIVOT: ₹${r.tomorrow_playbook.central_pivot}**
• S1: ₹${r.tomorrow_playbook.levels[4].price}
• S2: ₹${r.tomorrow_playbook.levels[5].price}
• S3: ₹${r.tomorrow_playbook.levels[6].price}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🚀 **PLAN A (BULLISH):** Above ₹${Math.round(r.tomorrow_playbook.central_pivot)} targets ₹${Math.round(r.tomorrow_playbook.levels[1].price)}.
🩸 **PLAN B (BEARISH):** Below ₹${Math.round(r.tomorrow_playbook.central_pivot)} targets ₹${Math.round(r.tomorrow_playbook.levels[5].price)}.`;

        navigator.clipboard.writeText(text).then(() => {
            alert('Institutional Desk Brief copied to clipboard!');
        }).catch(() => {
            prompt('Copy desk brief:', text);
        });
    };

})();
</script>
@endsection
