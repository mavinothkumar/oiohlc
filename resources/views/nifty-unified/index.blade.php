@extends('layouts.app')

@section('title', 'NIFTY 50 Unified Command Center | 75/25 Confluence Cockpit')

@push('styles')
    <!-- TradingView Lightweight Charts -->
    <script src="https://cdn.jsdelivr.net/npm/lightweight-charts@4.2.3/dist/lightweight-charts.standalone.production.js"></script>
    <!-- Chart.js 4.4 -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <!-- Flatpickr (Calendar Date Picker) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        body {
            background-color: #0b0e14 !important;
            color: #d1d5db;
        }

        /* Top header minimal override in Zen mode */
        body.zen-mode header,
        body.zen-mode #page-updated-time {
            display: none !important;
        }
        body.zen-mode main {
            padding: 0 !important;
            margin: 0 !important;
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
        ::-webkit-scrollbar-thumb:hover {
            background: #4b5563;
        }

        /* Pulse Animations */
        @keyframes pulse-subtle {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.15); }
        }
        .live-dot {
            animation: pulse-subtle 2s infinite ease-in-out;
        }

        /* Flatpickr Dark Customization */
        .flatpickr-calendar {
            background: #131722 !important;
            border: 1px solid #374151 !important;
            border-radius: 8px !important;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.8) !important;
            font-family: inherit !important;
        }
        .flatpickr-calendar .flatpickr-months {
            background: #1a1e29 !important;
        }
        .flatpickr-day {
            color: #e5e7eb !important;
            border-radius: 6px !important;
        }
        .flatpickr-day.selected {
            background: #2563eb !important;
            border-color: #3b82f6 !important;
            color: #ffffff !important;
        }

        /* Cockpit card styling */
        .cockpit-card {
            background-color: #131722;
            border: 1px solid #1f2937;
            border-radius: 0.5rem;
        }
    </style>
@endpush

@section('content')
<div class="w-full flex flex-col p-2 space-y-2 select-none min-h-screen" id="nifty-unified-root">

    <!-- ════════════ TOP UNIFIED COMMAND BAR ════════════ -->
    <div class="cockpit-card px-3 py-1.5 shadow-lg flex flex-wrap items-center justify-between gap-2 shrink-0 border-gray-800 text-xs">
        
        <!-- Left: Branding & Spot Price -->
        <div class="flex items-center gap-2.5">
            <span class="p-1 bg-gradient-to-br from-cyan-600 to-blue-700 rounded text-white flex items-center justify-center shadow-xs">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </span>
            <div class="flex items-center gap-1.5">
                <span class="font-extrabold text-white text-xs tracking-wide">NIFTY 50 UNIFIED COCKPIT</span>
                <span class="hidden sm:inline-block px-1.5 py-0.2 text-[9px] font-bold rounded bg-cyan-950 text-cyan-400 border border-cyan-800">
                    75/25 CONFLUENCE
                </span>
            </div>

            <!-- Spot price pill -->
            <div class="flex items-center gap-1 bg-[#1a1e29] border border-gray-700/80 px-2 py-0.5 rounded text-[11px] font-mono">
                <span class="text-gray-400">SPOT:</span>
                <span id="spot-price-display" class="font-bold text-cyan-400">---</span>
                <span id="spot-change-display" class="font-semibold text-gray-400 text-[10px]"></span>
            </div>
        </div>

        <!-- Center: Master Unified Timeline Scrubber & Replay Controller -->
        <div class="flex items-center gap-2 bg-[#1a1e29] border border-gray-700/90 rounded-lg px-2.5 py-1 shadow-inner">
            <div class="flex items-center gap-1">
                <span class="text-gray-400 text-[10px] font-semibold hidden md:inline">TIMELINE:</span>
                <span id="master-time-badge" class="px-2 py-0.5 bg-sky-950 border border-sky-500/50 text-sky-300 font-mono font-bold text-xs rounded">
                    09:15
                </span>
            </div>

            <!-- Stepper buttons -->
            <div class="flex items-center gap-0.5 border-l border-gray-700 pl-1.5">
                <button id="btn-step-start" title="First candle (09:15)" class="p-1 hover:bg-gray-700 rounded text-gray-400 hover:text-white transition cursor-pointer">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
                </button>
                <button id="btn-step-prev" title="Previous 5m candle (← Key)" class="p-1 hover:bg-gray-700 rounded text-gray-300 hover:text-white transition cursor-pointer">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button id="btn-play-pause" title="Play / Pause Timeline Replay" class="px-2 py-0.5 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded text-[11px] flex items-center gap-1 transition shadow-xs cursor-pointer">
                    <span id="play-icon">▶</span>
                    <span id="play-text">Play</span>
                </button>
                <button id="btn-step-next" title="Next 5m candle (→ Key)" class="p-1 hover:bg-gray-700 rounded text-gray-300 hover:text-white transition cursor-pointer">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
                <button id="btn-snap-live" title="Snap to Latest Live Candle" class="px-2 py-0.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded text-[10px] flex items-center gap-1 transition cursor-pointer">
                    <span class="w-1.5 h-1.5 rounded-full bg-white live-dot"></span>
                    <span>LIVE</span>
                </button>
            </div>

            <!-- Master Scrubber Slider -->
            <div class="w-28 sm:w-40 md:w-52 flex items-center px-1">
                <input id="master-slider" type="range" min="0" max="74" value="0" class="w-full h-1.5 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-sky-400 focus:outline-none">
            </div>
        </div>

        <!-- Right: Date, Expiry, Chart Height, Alarm, Auto-Refresh & Controls -->
        <div class="flex items-center gap-2">
            <!-- Calendar Date Picker -->
            <div class="flex items-center bg-[#1a1e29] border border-gray-700 rounded px-2 py-0.5 text-xs gap-1">
                <button id="btn-prev-day" type="button" title="Previous Trading Day" class="p-0.5 hover:bg-gray-700 rounded text-gray-400 hover:text-white transition cursor-pointer">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <input type="text" id="date-select" readonly
                       class="bg-transparent text-white font-mono font-medium focus:outline-none cursor-pointer w-24 text-[11px] selection:bg-transparent"
                       value="{{ $selectedDate }}">
                <button id="btn-next-day" type="button" title="Next Trading Day" class="p-0.5 hover:bg-gray-700 rounded text-gray-400 hover:text-white transition cursor-pointer">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>

            <!-- Expiry Selector -->
            <div class="flex items-center bg-[#1a1e29] border border-gray-700 rounded px-2 py-0.5 text-xs gap-1">
                <span class="text-gray-400 text-[10.5px]">EXP:</span>
                <select id="expiry-select" class="bg-transparent text-amber-400 font-mono font-bold focus:outline-none cursor-pointer text-[11px]">
                    @foreach($expiries as $exp)
                        <option value="{{ $exp }}" {{ $exp === $selectedExpiry ? 'selected' : '' }} class="bg-[#131722] text-white">
                            {{ \Carbon\Carbon::parse($exp)->format('d-M-Y') }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Dynamic Chart Height Selector -->
            <div class="flex items-center bg-[#1a1e29] border border-gray-700 rounded px-2 py-0.5 text-xs gap-1">
                <span class="text-gray-400 text-[10px] flex items-center gap-1">
                    <svg class="w-3 h-3 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                    </svg>
                    H:
                </span>
                <select id="chart-height-select" class="bg-transparent text-sky-400 font-mono font-bold focus:outline-none cursor-pointer text-[11px]" title="Change Height of Both Candlestick Charts">
                    <option value="300" class="bg-[#131722] text-white">300px</option>
                    <option value="350" class="bg-[#131722] text-white">350px</option>
                    <option value="380" class="bg-[#131722] text-white">380px</option>
                    <option value="420" selected class="bg-[#131722] text-white">420px (Default)</option>
                    <option value="480" class="bg-[#131722] text-white">480px</option>
                    <option value="550" class="bg-[#131722] text-white">550px</option>
                </select>
            </div>

            <!-- Confluence & Alarm Controls (User Requested) -->
            <div class="flex items-center bg-[#1a1e29] border border-gray-700 rounded text-xs overflow-hidden">
                <button id="btn-quick-alarm" title="Click to Toggle Alarm On/Off" class="flex items-center gap-1 px-2 py-0.5 text-amber-400 hover:bg-amber-950/50 font-bold transition cursor-pointer">
                    <span id="alarm-bell-icon">🔔</span>
                    <span id="alarm-toolbar-label">Alarm ON (80%)</span>
                </button>
                <button id="btn-open-alarm-settings" title="Configure NIFTY Breadth Extreme & 0Click.in Webhook" class="px-1.5 py-0.5 border-l border-gray-700 text-gray-400 hover:text-white hover:bg-gray-800 transition cursor-pointer font-bold">
                    ⚙️
                </button>
            </div>

            <!-- 0Click Signal Logs Button -->
            <button id="btn-open-logs" title="View 0Click.in Signal Execution & Internal Audit Logs" class="flex items-center gap-1.5 px-2 py-0.5 bg-[#1a1e29] hover:bg-gray-800 border border-gray-700 text-sky-400 hover:text-sky-300 rounded text-xs font-semibold transition cursor-pointer">
                <span>📋</span>
                <span class="hidden sm:inline">0Click Logs</span>
                <span id="log-count-badge" class="ml-0.5 px-1.5 py-0.2 bg-sky-950/90 border border-sky-500/50 text-sky-300 font-mono text-[10px] rounded-full">0</span>
            </button>

            <!-- Auto-Refresh Selector -->
            <div class="flex items-center bg-[#1a1e29] border border-gray-700 rounded px-2 py-0.5 text-xs gap-1">
                <span class="text-gray-400 text-[10px] flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 live-dot"></span>
                    Auto:
                </span>
                <select id="auto-refresh-select" class="bg-transparent text-white font-bold focus:outline-none cursor-pointer text-[11px]">
                    <option value="5" class="bg-[#131722]">5s</option>
                    <option value="15" class="bg-[#131722]">15s</option>
                    <option value="30" class="bg-[#131722]">30s</option>
                    <option value="60" class="bg-[#131722]">1m</option>
                    <option value="180" class="bg-[#131722]">3m</option>
                    <option value="300" selected class="bg-[#131722]">5m (Default)</option>
                    <option value="0" class="bg-[#131722]">Off</option>
                </select>
                <span id="countdown-badge" class="px-1.5 py-0.2 bg-gray-800 text-sky-300 font-mono text-[10px] rounded font-bold">05:00</span>
            </div>

            <!-- Manual Reload -->
            <button id="btn-reload" title="Refresh All Data Now" class="p-1.5 bg-[#1a1e29] hover:bg-gray-700 border border-gray-700 rounded text-gray-300 hover:text-white transition cursor-pointer">
                <svg id="reload-spinner" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </button>

            <!-- Zen Mode -->
            <button id="btn-zen" title="Toggle Fullscreen Zen Mode" class="p-1.5 bg-[#1a1e29] hover:bg-gray-700 border border-gray-700 rounded text-gray-300 hover:text-white transition cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- ════════════ MAIN LAYOUT: COLUMN 1 (75%) | COLUMN 2 (25%) ════════════ -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-2.5 w-full items-start">

        <!-- ──────────────────────────────────────────────────────────────────────────── -->
        <!-- COLUMN 1 (75% WIDTH): TWO ROWS (NIFTY 50 WEIGHTAGE & OPTIONS BREADTH)        -->
        <!-- ──────────────────────────────────────────────────────────────────────────── -->
        <div class="xl:col-span-9 flex flex-col space-y-2.5 w-full">

            <!-- ── ROW 1: NIFTY 50 WEIGHTAGE (75% Chart | 25% Data) ────────────────── -->
            <div class="cockpit-card p-2.5 border-blue-900/40 shadow-md">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-2.5 items-start">
                    
                    <!-- Left: 75% Candlestick Chart -->
                    <div class="lg:col-span-9 flex flex-col">
                        <div class="flex items-center justify-between pb-1 mb-1 border-b border-gray-800">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                                <span class="text-xs font-bold text-white tracking-wide">NIFTY 50 (5m Candles)</span>
                                <span class="text-[10px] text-gray-400 hidden sm:inline">&bull; Stock Weightage Breadth</span>
                            </div>
                            <div class="flex items-center gap-2 text-[10.5px]">
                                <span class="text-gray-400">Selected: <strong id="w-chart-time" class="text-sky-400 font-mono">09:15</strong></span>
                                <div class="flex items-center gap-1.5 text-[10px] font-mono">
                                    <span class="text-gray-400">O: <span id="w-chart-open" class="text-gray-200">-</span></span>
                                    <span class="text-gray-400">C: <span id="w-chart-close" class="text-gray-200">-</span></span>
                                    <span id="w-chart-chg" class="font-bold text-emerald-400">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Candlestick Canvas -->
                        <div id="w-chart-container" class="w-full relative rounded overflow-hidden" style="height: 420px;">
                            <div id="w-chart-loading" class="absolute inset-0 bg-[#131722] flex items-center justify-center text-xs text-gray-400 z-10">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                Loading Weightage Chart...
                            </div>
                        </div>

                        <!-- Scrubber Slider -->
                        <div class="pt-1.5 flex items-center gap-2 text-[10px] text-gray-400">
                            <span class="font-mono text-[9px]" id="w-slider-start">09:15</span>
                            <input id="w-slider" type="range" min="0" max="74" value="0" class="w-full h-1 bg-gray-700 rounded appearance-none cursor-pointer accent-blue-400 focus:outline-none">
                            <span class="font-mono text-[9px]" id="w-slider-end">15:25</span>
                        </div>
                    </div>

                    <!-- Right: 25% Weightage Data Panel -->
                    <div class="lg:col-span-3 flex flex-col space-y-2 border-t lg:border-t-0 lg:border-l border-gray-800 lg:pl-2.5 pt-2 lg:pt-0">
                        <!-- Interval Header & Move -->
                        <div class="flex items-center justify-between pb-1 border-b border-gray-800">
                            <div>
                                <span class="text-[9px] uppercase tracking-wider text-gray-400 font-medium">SELECTED CANDLE</span>
                                <div class="text-[11px] font-bold text-sky-400 font-mono" id="w-stat-time">
                                    09:15 - 5-min candle
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-[9px] text-gray-400">Index Move</span>
                                <div id="w-stat-pts" class="text-xs font-black font-mono text-emerald-400">
                                    +0.0 pts (+0.0%)
                                </div>
                            </div>
                        </div>

                        <!-- UP vs DOWN Big Blocks -->
                        <div class="grid grid-cols-2 gap-1.5">
                            <div id="w-box-up" class="bg-gradient-to-br from-emerald-950/70 to-emerald-900/30 border border-emerald-500/40 rounded p-1.5 text-center shadow-inner relative overflow-hidden">
                                <span class="text-lg font-black text-emerald-400 font-mono" id="w-stat-green-pct">0%</span>
                                <span class="ml-1 px-1 py-0.2 bg-emerald-500/20 text-emerald-300 font-bold text-[8.5px] rounded uppercase">UP</span>
                                <div class="text-[9px] text-emerald-200/90 font-medium mt-0.5 space-y-0.2 leading-tight">
                                    <div>Weight: <strong id="w-stat-green-wt" class="text-white">0.0%</strong></div>
                                    <div><strong id="w-stat-green-cnt" class="text-white">0</strong> stocks &bull; <span id="w-stat-green-pts" class="text-emerald-300">+0.0</span></div>
                                </div>
                            </div>

                            <div id="w-box-down" class="bg-gradient-to-br from-rose-950/70 to-rose-900/30 border border-rose-500/40 rounded p-1.5 text-center shadow-inner relative overflow-hidden">
                                <span class="text-lg font-black text-rose-400 font-mono" id="w-stat-red-pct">0%</span>
                                <span class="ml-1 px-1 py-0.2 bg-rose-500/20 text-rose-300 font-bold text-[8.5px] rounded uppercase">DOWN</span>
                                <div class="text-[9px] text-rose-200/90 font-medium mt-0.5 space-y-0.2 leading-tight">
                                    <div>Weight: <strong id="w-stat-red-wt" class="text-white">0.0%</strong></div>
                                    <div><strong id="w-stat-red-cnt" class="text-white">0</strong> stocks &bull; <span id="w-stat-red-pts" class="text-rose-300">-0.0</span></div>
                                </div>
                            </div>
                        </div>

                        <!-- Weight Influence Progress Bar -->
                        <div class="bg-[#1a1e29] border border-gray-800 rounded px-2 py-1 text-[9.5px] space-y-0.5">
                            <div class="flex justify-between items-center text-gray-400">
                                <span>Weight Influence (100% Index)</span>
                                <span id="w-stat-split" class="font-mono text-gray-300 font-bold">50% G / 50% R</span>
                            </div>
                            <div class="w-full h-1.5 bg-gray-800 rounded-full overflow-hidden flex">
                                <div id="w-bar-green" class="h-full bg-emerald-500 transition-all duration-150" style="width: 50%;"></div>
                                <div id="w-bar-red" class="h-full bg-rose-500 transition-all duration-150" style="width: 50%;"></div>
                            </div>
                        </div>

                        <!-- Institutional Conviction Verdict -->
                        <div id="w-insight-card" class="p-1.5 rounded bg-gray-800/40 border border-gray-700 text-[10px]">
                            <div class="font-bold text-white flex items-center gap-1" id="w-insight-badge">
                                <span>🚀 Broad Institutional Buying</span>
                            </div>
                            <p class="text-[9.5px] text-gray-400 leading-snug mt-0.5" id="w-insight-desc">
                                High conviction rally: stocks UP backed by weight.
                            </p>
                        </div>

                        <!-- Candle Key Movers -->
                        <div class="space-y-1">
                            <div class="flex items-center justify-between text-[9.5px] text-gray-400 font-semibold px-0.5">
                                <span>⚡ Key Movers</span>
                                <span id="w-movers-time" class="font-mono text-sky-400 text-[9px]">09:15</span>
                            </div>
                            <div class="grid grid-cols-2 gap-1.5">
                                <div class="space-y-0.5">
                                    <div class="text-[8.5px] font-bold text-emerald-400 uppercase tracking-wider">▲ DRIVERS (+PTS)</div>
                                    <div id="w-top-drivers" class="space-y-0.5 text-[9px]">
                                        <div class="text-gray-500 italic text-[8.5px]">Loading...</div>
                                    </div>
                                </div>
                                <div class="space-y-0.5">
                                    <div class="text-[8.5px] font-bold text-rose-400 uppercase tracking-wider">▼ DRAGGERS (-PTS)</div>
                                    <div id="w-top-draggers" class="space-y-0.5 text-[9px]">
                                        <div class="text-gray-500 italic text-[8.5px]">Loading...</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- NIFTY 50 Stocks Trigger Strength Analysis & Overall Breadth -->
                        <div class="pt-1.5 border-t border-gray-800/80 space-y-1 text-[9.5px]">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-300 font-bold flex items-center gap-1">
                                    <span class="text-[11px]">🎯</span>
                                    <span>Stocks Trigger Strength</span>
                                    <span class="text-[8px] text-gray-400 font-normal font-mono" id="stk-trigger-val-badge">(≥80%)</span>
                                </span>
                                <span id="stk-trigger-verdict-badge" class="px-1.5 py-0.2 rounded text-[8.5px] font-mono font-bold bg-gray-800 text-gray-400 border border-gray-700">
                                    Nothing happened
                                </span>
                            </div>

                            <!-- Trigger Counts Grid: Positive vs Negative -->
                            <div class="grid grid-cols-2 gap-1 text-[8.5px] font-mono">
                                <div class="bg-[#181d2a] p-1 rounded border border-emerald-950/70 text-left">
                                    <div class="flex items-center justify-between text-gray-400">
                                        <span class="text-emerald-400 font-bold">▲ POSITIVE TRIGGERS</span>
                                        <strong id="stk-pos-triggers-count" class="text-emerald-400 text-[10px]">0</strong>
                                    </div>
                                    <div class="text-gray-400 text-[8px] truncate mt-0.5" id="stk-pos-details">
                                        Pk: <span class="text-emerald-300 font-bold" id="stk-pos-peak">0%</span> <span class="text-gray-500" id="stk-pos-peak-time"></span>
                                    </div>
                                </div>

                                <div class="bg-[#181d2a] p-1 rounded border border-rose-950/70 text-left">
                                    <div class="flex items-center justify-between text-gray-400">
                                        <span class="text-rose-400 font-bold">▼ NEGATIVE TRIGGERS</span>
                                        <strong id="stk-neg-triggers-count" class="text-rose-400 text-[10px]">0</strong>
                                    </div>
                                    <div class="text-gray-400 text-[8px] truncate mt-0.5" id="stk-neg-details">
                                        Pk: <span class="text-rose-300 font-bold" id="stk-neg-peak">0%</span> <span class="text-gray-500" id="stk-neg-peak-time"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Status & Overall Stock Breadth So Far -->
                            <div class="bg-[#181d2a] px-1.5 py-0.5 rounded border border-gray-800/80 flex items-center justify-between text-[8px] font-mono">
                                <span id="stk-trigger-lead-text" class="text-gray-400 font-medium truncate">Nothing happened</span>
                                <span class="text-gray-400">Stocks Avg: <strong id="stk-session-bull" class="text-emerald-400">50%</strong>/<strong id="stk-session-bear" class="text-rose-400">50%</strong></span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ── ROW 2: OPTIONS BREADTH (75% Chart | 25% Data) ───────────────────── -->
            <div class="cockpit-card p-2.5 border-indigo-900/40 shadow-md">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-2.5 items-start">
                    
                    <!-- Left: 75% Candlestick & Parity Chart -->
                    <div class="lg:col-span-9 flex flex-col">
                        <div class="flex items-center justify-between pb-1 mb-1 border-b border-gray-800">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                                <span class="text-xs font-bold text-white tracking-wide">NIFTY (5m Candles &amp; Parity)</span>
                                <span class="text-[10px] text-gray-400 hidden sm:inline">&bull; Options Flow Parity</span>
                            </div>
                            <div class="flex items-center gap-2 text-[10.5px]">
                                <span class="text-gray-400">Selected: <strong id="o-chart-time" class="text-sky-400 font-mono">09:15</strong></span>
                                <div class="flex items-center gap-1.5 text-[10px] font-mono">
                                    <span class="text-gray-400">O: <span id="o-chart-open" class="text-gray-200">-</span></span>
                                    <span class="text-gray-400">C: <span id="o-chart-close" class="text-gray-200">-</span></span>
                                    <span id="o-chart-move" class="font-bold text-emerald-400">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Candlestick Canvas -->
                        <div id="o-chart-container" class="w-full relative rounded overflow-hidden" style="height: 420px;">
                            <div id="o-chart-loading" class="absolute inset-0 bg-[#131722] flex items-center justify-center text-xs text-gray-400 z-10">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                Loading Options Parity Chart...
                            </div>
                        </div>

                        <!-- Scrubber Slider -->
                        <div class="pt-1.5 flex items-center gap-2 text-[10px] text-gray-400">
                            <span class="font-mono text-[9px]" id="o-slider-start">09:15</span>
                            <input id="o-slider" type="range" min="0" max="74" value="0" class="w-full h-1 bg-gray-700 rounded appearance-none cursor-pointer accent-indigo-400 focus:outline-none">
                            <span class="font-mono text-[9px]" id="o-slider-end">15:25</span>
                        </div>
                    </div>

                    <!-- Right: 25% Options Breadth Data Panel -->
                    <div class="lg:col-span-3 flex flex-col space-y-2 border-t lg:border-t-0 lg:border-l border-gray-800 lg:pl-2.5 pt-2 lg:pt-0">
                        <!-- Interval Header & Impulse -->
                        <div class="flex items-center justify-between pb-1 border-b border-gray-800">
                            <div>
                                <span class="text-[9px] uppercase tracking-wider text-gray-400 font-medium">SELECTED CANDLE</span>
                                <div class="text-[11px] font-bold text-sky-400 font-mono" id="o-stat-time">
                                    09:15 - 5-min candle
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-[9px] text-gray-400">Net Impulse</span>
                                <div id="o-stat-impulse" class="text-xs font-black font-mono text-emerald-400">
                                    +0.00 pts
                                </div>
                            </div>
                        </div>

                        <!-- BUY vs SELL Big Blocks -->
                        <div class="grid grid-cols-2 gap-1.5">
                            <div class="bg-gradient-to-br from-emerald-950/70 to-emerald-900/30 border border-emerald-500/40 rounded p-1.5 text-center shadow-inner relative overflow-hidden">
                                <div class="text-[8.5px] font-bold text-emerald-400 uppercase tracking-wider mb-0.5">BUY (BULL FLOW)</div>
                                <span class="text-lg font-black text-emerald-300 font-mono" id="o-stat-green-ratio">50%</span>
                                <div class="text-[9px] text-gray-300 mt-0.5">
                                    <span id="o-stat-green-wt">50% Wt</span> &bull; <span id="o-stat-green-cnt">0</span> strikes
                                </div>
                            </div>

                            <div class="bg-gradient-to-br from-rose-950/70 to-rose-900/30 border border-rose-500/40 rounded p-1.5 text-center shadow-inner relative overflow-hidden">
                                <div class="text-[8.5px] font-bold text-rose-400 uppercase tracking-wider mb-0.5">SELL (BEAR FLOW)</div>
                                <span class="text-lg font-black text-rose-300 font-mono" id="o-stat-red-ratio">50%</span>
                                <div class="text-[9px] text-gray-300 mt-0.5">
                                    <span id="o-stat-red-wt">50% Wt</span> &bull; <span id="o-stat-red-cnt">0</span> strikes
                                </div>
                            </div>
                        </div>

                        <!-- Tug-of-War Balance Bar -->
                        <div class="bg-[#1a1e29] border border-gray-800 rounded px-2 py-1 text-[9.5px] space-y-0.5">
                            <div class="flex justify-between items-center text-gray-400">
                                <span>Options Tug-of-War</span>
                                <span id="o-stat-split" class="font-mono text-gray-300 font-bold">50% / 50%</span>
                            </div>
                            <div class="w-full h-1.5 bg-gray-800 rounded-full overflow-hidden flex">
                                <div id="o-bar-green" class="h-full bg-emerald-500 transition-all duration-150" style="width: 50%;"></div>
                                <div id="o-bar-red" class="h-full bg-rose-500 transition-all duration-150" style="width: 50%;"></div>
                            </div>
                        </div>

                        <!-- Equilibrium Verdict -->
                        <div class="p-1.5 rounded bg-[#181d2a] border border-gray-800 text-[10px]">
                            <div class="font-bold text-white flex items-center gap-1 mb-0.5" id="o-verdict-title">
                                <span>⚖️ Balanced Options Equilibrium</span>
                            </div>
                            <p class="text-[9.5px] text-gray-400 leading-snug" id="o-verdict-desc">
                                Options flow is balanced between Call buying and Put shorting.
                            </p>
                        </div>

                        <!-- Greek Snapshot Footer -->
                        <div class="pt-1.5 border-t border-gray-800 grid grid-cols-4 gap-1 text-center font-mono text-[9px]">
                            <div class="bg-[#181d2a] p-1 rounded border border-gray-800">
                                <div class="text-[8px] text-gray-400">Delta</div>
                                <div class="text-[10.5px] font-bold text-sky-400" id="o-greek-delta">0.0k</div>
                            </div>
                            <div class="bg-[#181d2a] p-1 rounded border border-gray-800">
                                <div class="text-[8px] text-gray-400">CE Vega</div>
                                <div class="text-[10.5px] font-bold text-emerald-400" id="o-greek-ce-vega">0.00</div>
                            </div>
                            <div class="bg-[#181d2a] p-1 rounded border border-gray-800">
                                <div class="text-[8px] text-gray-400">PE Vega</div>
                                <div class="text-[10.5px] font-bold text-rose-400" id="o-greek-pe-vega">0.00</div>
                            </div>
                            <div class="bg-[#181d2a] p-1 rounded border border-gray-800">
                                <div class="text-[8px] text-gray-400">PCR</div>
                                <div class="text-[10.5px] font-bold text-amber-400" id="o-greek-pcr">1.00</div>
                            </div>
                        </div>

                        <!-- NIFTY Options Trigger Strength Analysis & Overall Breadth -->
                        <div class="pt-1.5 border-t border-gray-800/80 space-y-1 text-[9.5px]">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-300 font-bold flex items-center gap-1">
                                    <span class="text-[11px]">🎯</span>
                                    <span>Options Trigger Strength</span>
                                    <span class="text-[8px] text-gray-400 font-normal font-mono" id="opt-trigger-val-badge">(≥70%)</span>
                                </span>
                                <span id="opt-trigger-verdict-badge" class="px-1.5 py-0.2 rounded text-[8.5px] font-mono font-bold bg-gray-800 text-gray-400 border border-gray-700">
                                    Nothing happened
                                </span>
                            </div>

                            <!-- Trigger Counts Grid: Positive vs Negative -->
                            <div class="grid grid-cols-2 gap-1 text-[8.5px] font-mono">
                                <div class="bg-[#181d2a] p-1 rounded border border-emerald-950/70 text-left">
                                    <div class="flex items-center justify-between text-gray-400">
                                        <span class="text-emerald-400 font-bold">▲ POSITIVE TRIGGERS</span>
                                        <strong id="opt-pos-triggers-count" class="text-emerald-400 text-[10px]">0</strong>
                                    </div>
                                    <div class="text-gray-400 text-[8px] truncate mt-0.5" id="opt-pos-details">
                                        Pk: <span class="text-emerald-300 font-bold" id="opt-pos-peak">0%</span> <span class="text-gray-500" id="opt-pos-peak-time"></span>
                                    </div>
                                </div>

                                <div class="bg-[#181d2a] p-1 rounded border border-rose-950/70 text-left">
                                    <div class="flex items-center justify-between text-gray-400">
                                        <span class="text-rose-400 font-bold">▼ NEGATIVE TRIGGERS</span>
                                        <strong id="opt-neg-triggers-count" class="text-rose-400 text-[10px]">0</strong>
                                    </div>
                                    <div class="text-gray-400 text-[8px] truncate mt-0.5" id="opt-neg-details">
                                        Pk: <span class="text-rose-300 font-bold" id="opt-neg-peak">0%</span> <span class="text-gray-500" id="opt-neg-peak-time"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Status & Overall Options Breadth So Far -->
                            <div class="bg-[#181d2a] px-1.5 py-0.5 rounded border border-gray-800/80 flex items-center justify-between text-[8px] font-mono">
                                <span id="opt-trigger-lead-text" class="text-gray-400 font-medium truncate">Nothing happened</span>
                                <span class="text-gray-400">Options Avg: <strong id="opt-session-bull" class="text-emerald-400">50%</strong>/<strong id="opt-session-bear" class="text-rose-400">50%</strong></span>
                            </div>

                            <!-- Combined Confluence Trigger Summary Strip -->
                            <div class="flex items-center justify-between px-1.5 py-0.5 rounded bg-[#151922] border border-gray-800/90 text-[8.5px] font-mono">
                                <span class="text-amber-400 font-bold flex items-center gap-1">
                                    <span>🔥 Combined:</span>
                                </span>
                                <span id="comb-trigger-badge" class="font-bold text-gray-400">
                                    Nothing happened
                                </span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>

        <!-- ──────────────────────────────────────────────────────────────────────────── -->
        <!-- COLUMN 2 (25% WIDTH): THREE ROWS (TRENDING OI, OI BUILDUP, SENTIMENT)        -->
        <!-- ──────────────────────────────────────────────────────────────────────────── -->
        <div class="xl:col-span-3 flex flex-col space-y-2.5 w-full">

            <!-- ── ROW 1: TRENDING OI CHART ───────────────────────────────────────── -->
            <div class="cockpit-card p-2 flex flex-col border-rose-900/40 shadow-md">
                <div class="flex items-center justify-between pb-1 mb-1 border-b border-gray-800">
                    <h3 class="text-xs font-bold text-white flex items-center gap-1.5">
                        📈 Trending OI
                    </h3>
                    <div class="flex items-center gap-1.5 text-[9px]">
                        <span class="flex items-center gap-0.5 text-emerald-400 font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Call</span>
                        <span class="flex items-center gap-0.5 text-rose-400 font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Put</span>
                        <span class="flex items-center gap-0.5 text-cyan-400 font-semibold"><span class="w-1.5 h-0.5 border-t border-dashed border-cyan-400"></span> Spot</span>
                    </div>
                </div>

                <div class="relative w-full overflow-hidden" id="toi-chart-wrapper" style="height: 250px;">
                    <div id="toi-chart-loading" class="absolute inset-0 bg-[#131722] flex items-center justify-center text-xs text-gray-400 z-10">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-rose-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        Loading Trending OI...
                    </div>
                    <canvas id="chart-trending-oi"></canvas>
                </div>
            </div>

            <!-- ── ROW 2: OI BUILDUP (HORIZONTAL BAR CHART) ───────────────────────── -->
            <div class="cockpit-card p-2 flex flex-col border-amber-900/40 shadow-md">
                <div class="flex items-center justify-between pb-1 mb-1 border-b border-gray-800">
                    <div class="flex items-center gap-1.5">
                        <h3 class="text-xs font-bold text-white flex items-center gap-1">🔥 OI Buildup</h3>
                        <span id="bld-window-badge" class="text-[9px] bg-slate-800 text-sky-300 font-mono font-bold px-1.5 py-0.2 rounded border border-gray-700">
                            15:20 - 15:25
                        </span>
                    </div>
                    <div class="flex items-center gap-0.5 bg-[#1a1e29] p-0.5 rounded text-[9px] font-bold">
                        <button type="button" data-tf="5m" class="bld-tf-btn px-1.5 py-0.5 rounded bg-blue-600 text-white cursor-pointer transition">5M</button>
                        <button type="button" data-tf="15m" class="bld-tf-btn px-1.5 py-0.5 rounded text-gray-400 hover:text-white cursor-pointer transition">15M</button>
                        <button type="button" data-tf="30m" class="bld-tf-btn px-1.5 py-0.5 rounded text-gray-400 hover:text-white cursor-pointer transition">30M</button>
                        <button type="button" data-tf="today" class="bld-tf-btn px-1.5 py-0.5 rounded text-gray-400 hover:text-white cursor-pointer transition">Today</button>
                    </div>
                </div>

                <div class="flex items-center justify-between text-[9px] py-0.5 text-gray-400">
                    <span id="bld-activity-desc" class="font-semibold text-gray-300">Top 8 Activity (5M)</span>
                    <div class="flex items-center gap-1.5 text-[8.5px]">
                        <span class="text-rose-400" title="Short Buildup"><span class="w-1.5 h-1.5 rounded-xs bg-[#dc2626] inline-block"></span> SB</span>
                        <span class="text-blue-400" title="Short Covering"><span class="w-1.5 h-1.5 rounded-xs bg-[#1e3a8a] inline-block"></span> SC</span>
                        <span class="text-emerald-400" title="Long Buildup"><span class="w-1.5 h-1.5 rounded-xs bg-[#16a34a] inline-block"></span> LB</span>
                        <span class="text-yellow-400" title="Long Unwinding"><span class="w-1.5 h-1.5 rounded-xs bg-[#eab308] inline-block"></span> LU</span>
                    </div>
                </div>

                <div id="bld-chart-wrapper" class="relative w-full overflow-hidden" style="height: 240px;">
                    <canvas id="chart-oi-buildup"></canvas>
                </div>

                <!-- Buildup Summary Footer -->
                <div class="pt-1 mt-1 border-t border-gray-800 bg-[#181d2a]/80 p-1.5 rounded text-[9.5px] space-y-0.5">
                    <div class="flex justify-between items-center text-gray-300 font-mono">
                        <span>CE Net: <strong id="bld-ce-net" class="text-rose-400 font-bold">+0.0 L</strong></span>
                        <span>PE Net: <strong id="bld-pe-net" class="text-blue-400 font-bold">+0.0 L</strong></span>
                    </div>
                    <div class="text-[9px] text-gray-400 flex justify-between items-center">
                        <span>Dominant Flow:</span>
                        <span id="bld-dominant" class="font-bold text-sky-300 text-[9px] truncate max-w-[190px] text-right">---</span>
                    </div>
                </div>
            </div>

            <!-- ── ROW 3: SENTIMENT CHART ─────────────────────────────────────────── -->
            <div class="cockpit-card p-2 flex flex-col border-cyan-900/40 shadow-md">
                <div class="flex items-center justify-between pb-1 mb-1 border-b border-gray-800">
                    <h3 class="text-xs font-bold text-white flex items-center gap-1.5">
                        📊 Trending OI Sentiment
                    </h3>
                    <div class="flex items-center gap-1.5 text-[9px]">
                        <span class="flex items-center gap-0.5 text-amber-400 font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Sentiment</span>
                        <span class="flex items-center gap-0.5 text-cyan-400 font-semibold"><span class="w-1.5 h-0.5 border-t border-dashed border-cyan-400"></span> Spot</span>
                    </div>
                </div>

                <div class="relative w-full overflow-hidden" id="toi-sentiment-wrapper" style="height: 250px;">
                    <canvas id="chart-trending-sentiment"></canvas>
                </div>
            </div>

        </div>

    </div>

    <!-- ════════════ ALARM SETTINGS MODAL (SPLIT SELECTION: STOCKS, OPTIONS & CONFLUENCE) ════════════ -->
    <div id="alarm-settings-modal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4 overflow-y-auto">
        <div class="cockpit-card p-4 sm:p-5 max-w-xl w-full border border-gray-700 shadow-2xl space-y-3 relative max-h-[92vh] overflow-y-auto">
            <!-- Header -->
            <div class="flex items-center justify-between pb-2 border-b border-gray-800">
                <div class="flex items-center gap-2">
                    <span class="text-xl">🔔</span>
                    <div>
                        <h3 class="text-sm font-bold text-white flex items-center gap-1.5">
                            <span>NIFTY 50 Alert &amp; Execution Command Center</span>
                        </h3>
                        <p class="text-[10.5px] text-gray-400">Independent alerts &amp; sounds for Stocks, Options &amp; High-Probability Confluence</p>
                    </div>
                </div>
                <button type="button" id="btn-close-alarm-modal" class="text-gray-400 hover:text-white text-lg font-bold cursor-pointer">&times;</button>
            </div>

            <!-- Master Alarm Status & Global Sound Volume -->
            <div class="flex items-center justify-between p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800">
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-white">Master Sound &amp; Popups</span>
                        <span id="alarm-status-badge" class="px-1.5 py-0.2 rounded text-[10px] font-mono font-bold bg-emerald-950 text-emerald-400 border border-emerald-600/40">ENABLED</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="alarm-active-toggle" class="sr-only peer" checked>
                        <div class="w-8 h-4 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-emerald-500"></div>
                    </label>
                </div>

                <!-- Global Volume Slider -->
                <div class="flex items-center gap-2">
                    <span class="text-[11px] text-gray-400">Vol:</span>
                    <input type="range" id="alarm-volume-slider" min="10" max="100" value="100" class="w-20 h-1 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-blue-500">
                    <span id="alarm-vol-label" class="font-mono text-white text-[10px] w-6 text-right">100%</span>
                </div>
            </div>

            <!-- Tab Switcher (3 Split Sections) -->
            <div class="flex items-center gap-1.5 p-1 bg-[#131722] rounded-lg border border-gray-800 text-xs font-semibold">
                <button type="button" class="alarm-tab-btn flex-1 py-1.5 px-2 rounded-md transition text-center cursor-pointer bg-blue-600 text-white font-bold" data-tab="stocks">
                    📊 Nifty 50 Stocks
                </button>
                <button type="button" class="alarm-tab-btn flex-1 py-1.5 px-2 rounded-md transition text-center cursor-pointer text-gray-400 hover:text-white" data-tab="options">
                    ⚡ Nifty Options
                </button>
                <button type="button" class="alarm-tab-btn flex-1 py-1.5 px-2 rounded-md transition text-center cursor-pointer text-gray-400 hover:text-white" data-tab="confluence">
                    🔥 Combined &amp; 0Click
                </button>
            </div>

            <!-- ════════════ TAB 1: NIFTY 50 STOCKS BREADTH ALERT ════════════ -->
            <div id="alarm-pane-stocks" class="alarm-pane space-y-3">
                <div class="flex items-center justify-between p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800">
                    <div>
                        <div class="text-xs font-bold text-white flex items-center gap-1.5">
                            <span>NIFTY 50 Stocks Breadth Alert</span>
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-blue-950 text-blue-300 border border-blue-600/40">50 CONSTITUENTS</span>
                        </div>
                        <span class="text-[10px] text-gray-400 block mt-0.5">Triggers on extreme stock breadth surge or decline</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="stocks-alert-enabled" class="sr-only peer" checked>
                        <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <!-- Stocks UP (Green) Threshold Slider -->
                <div class="p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 space-y-1.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-emerald-400 flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            <span>Stocks UP (Green) Threshold</span>
                        </span>
                        <div class="flex items-center gap-1">
                            <input type="number" id="stocks-up-input" min="50" max="95" value="80" class="w-14 bg-[#131722] border border-gray-700 rounded px-1.5 py-0.5 text-right font-mono font-bold text-emerald-400 text-xs focus:outline-none">
                            <span class="text-gray-400 font-bold">%</span>
                        </div>
                    </div>
                    <input type="range" id="stocks-up-slider" min="50" max="95" value="80" class="w-full h-1 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-emerald-500">
                    <div class="flex justify-between text-[9px] text-gray-500 font-mono">
                        <span>50%</span>
                        <span>80% (Default)</span>
                        <span>95%</span>
                    </div>
                </div>

                <!-- Stocks DOWN (Red) Threshold Slider -->
                <div class="p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 space-y-1.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-rose-400 flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                            <span>Stocks DOWN (Red) Threshold</span>
                        </span>
                        <div class="flex items-center gap-1">
                            <input type="number" id="stocks-down-input" min="50" max="95" value="80" class="w-14 bg-[#131722] border border-gray-700 rounded px-1.5 py-0.5 text-right font-mono font-bold text-rose-400 text-xs focus:outline-none">
                            <span class="text-gray-400 font-bold">%</span>
                        </div>
                    </div>
                    <input type="range" id="stocks-down-slider" min="50" max="95" value="80" class="w-full h-1 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-rose-500">
                    <div class="flex justify-between text-[9px] text-gray-500 font-mono">
                        <span>50%</span>
                        <span>80% (Default)</span>
                        <span>95%</span>
                    </div>
                </div>

                <!-- Trigger Metric Criteria for Stocks -->
                <div class="p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 space-y-1 text-xs">
                    <span class="font-bold text-gray-300 block mb-1">Nifty 50 Stocks Trigger Criteria:</span>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5">
                        <label class="flex items-center gap-1.5 cursor-pointer text-[11px] text-white font-bold p-1.5 rounded bg-blue-950/60 border border-blue-600/40 hover:bg-blue-900/60">
                            <input type="radio" name="stocks-trigger-by" value="both" checked class="text-blue-500 accent-blue-500 cursor-pointer">
                            <span>Both (Count &amp; Wt)</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer text-[11px] text-gray-300 p-1.5 rounded hover:bg-gray-800/80">
                            <input type="radio" name="stocks-trigger-by" value="either" class="text-blue-500 accent-blue-500 cursor-pointer">
                            <span>Either (Count or Wt)</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer text-[11px] text-gray-300 p-1.5 rounded hover:bg-gray-800/80">
                            <input type="radio" name="stocks-trigger-by" value="count" class="text-blue-500 accent-blue-500 cursor-pointer">
                            <span>Stock Count %</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer text-[11px] text-gray-300 p-1.5 rounded hover:bg-gray-800/80">
                            <input type="radio" name="stocks-trigger-by" value="weight" class="text-blue-500 accent-blue-500 cursor-pointer">
                            <span>Weight % Only</span>
                        </label>
                    </div>
                </div>

                <!-- Stocks Chart Markers Toggle -->
                <div class="flex items-center justify-between p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 text-xs">
                    <div>
                        <span class="font-bold text-white block">Stock Chart Breach Markers</span>
                        <span class="text-[10px] text-gray-400">Strictly mark candles where threshold criteria are fully satisfied</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="stocks-show-markers-toggle" class="sr-only peer" checked>
                        <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <!-- Stocks Dedicated Alarm Sound -->
                <div class="p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 space-y-1.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-white flex items-center gap-1">
                            <span>🎵 Stocks Alert Sound:</span>
                        </span>
                        <button type="button" id="btn-test-stocks-sound" title="Preview audio tone" class="px-2 py-0.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded flex items-center gap-1 text-[11px] transition shadow cursor-pointer">
                            <span>▶ Tone</span>
                        </button>
                    </div>
                    <select id="stocks-sound-select" class="w-full bg-[#131722] border border-gray-700 rounded px-2.5 py-1.5 text-xs text-white focus:outline-none cursor-pointer font-medium">
                        <option value="bull_horn" selected>1. 🚀 Bull Horn Fanfare (Ascending Major Chord)</option>
                        <option value="chime">2. 🔔 Classic Bell Chime (Harmonic Twin Bells)</option>
                        <option value="pulse">3. ⚡ Digital Cyber Pulse (High-Tech Double Beep)</option>
                        <option value="gong">4. 💥 Deep Resonant Gong (Sub-Bass Strike)</option>
                        <option value="siren">5. 🚨 Siren Warning (Emergency Warble)</option>
                        <option value="bear_drop">6. ⚠️ Bear Drop Klaxon (Descending Warning)</option>
                        <option value="sonar">7. 🎵 Radar Sonar Ping (Submarine Echo)</option>
                        <option value="rapid_beeps">8. 🔥 Rapid Fire Trader Pips (Fast 4-Tone Alert)</option>
                        <option value="fanfare">9. 🎺 Trumpet Breakout (Bright Brass Fanfare)</option>
                        <option value="caution_klaxon">10. 🛑 Urgent Caution Buzzer (Industrial Sawtooth)</option>
                    </select>
                </div>
            </div>

            <!-- ════════════ TAB 2: NIFTY OPTIONS BREADTH ALERT ════════════ -->
            <div id="alarm-pane-options" class="alarm-pane hidden space-y-3">
                <div class="flex items-center justify-between p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800">
                    <div>
                        <div class="text-xs font-bold text-white flex items-center gap-1.5">
                            <span>NIFTY Options Breadth Alert</span>
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-purple-950 text-purple-300 border border-purple-600/40">OPTIONS FLOW</span>
                        </div>
                        <span class="text-[10px] text-gray-400 block mt-0.5">Triggers when basket options flow shifts heavily Bullish or Bearish</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="options-alert-enabled" class="sr-only peer" checked>
                        <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-purple-600"></div>
                    </label>
                </div>

                <!-- Options UP (Calls Bullish) Threshold Slider -->
                <div class="p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 space-y-1.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-emerald-400 flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            <span>Options Bull Flow (Calls UP) Threshold</span>
                        </span>
                        <div class="flex items-center gap-1">
                            <input type="number" id="options-up-input" min="50" max="95" value="70" class="w-14 bg-[#131722] border border-gray-700 rounded px-1.5 py-0.5 text-right font-mono font-bold text-emerald-400 text-xs focus:outline-none">
                            <span class="text-gray-400 font-bold">%</span>
                        </div>
                    </div>
                    <input type="range" id="options-up-slider" min="50" max="95" value="70" class="w-full h-1 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-emerald-500">
                    <div class="flex justify-between text-[9px] text-gray-500 font-mono">
                        <span>50%</span>
                        <span>70% (Default)</span>
                        <span>95%</span>
                    </div>
                </div>

                <!-- Options DOWN (Puts Bearish) Threshold Slider -->
                <div class="p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 space-y-1.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-rose-400 flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                            <span>Options Bear Flow (Puts DOWN) Threshold</span>
                        </span>
                        <div class="flex items-center gap-1">
                            <input type="number" id="options-down-input" min="50" max="95" value="70" class="w-14 bg-[#131722] border border-gray-700 rounded px-1.5 py-0.5 text-right font-mono font-bold text-rose-400 text-xs focus:outline-none">
                            <span class="text-gray-400 font-bold">%</span>
                        </div>
                    </div>
                    <input type="range" id="options-down-slider" min="50" max="95" value="70" class="w-full h-1 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-rose-500">
                    <div class="flex justify-between text-[9px] text-gray-500 font-mono">
                        <span>50%</span>
                        <span>70% (Default)</span>
                        <span>95%</span>
                    </div>
                </div>

                <!-- Options Chart Markers Toggle -->
                <div class="flex items-center justify-between p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 text-xs">
                    <div>
                        <span class="font-bold text-white block">Options Chart Breach Markers</span>
                        <span class="text-[10px] text-gray-400">Mark candles where options flow exceeds threshold</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="options-show-markers-toggle" class="sr-only peer" checked>
                        <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-purple-600"></div>
                    </label>
                </div>

                <!-- Options Dedicated Alarm Sound -->
                <div class="p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 space-y-1.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-white flex items-center gap-1">
                            <span>🎵 Options Alert Sound:</span>
                        </span>
                        <button type="button" id="btn-test-options-sound" title="Preview audio tone" class="px-2 py-0.5 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded flex items-center gap-1 text-[11px] transition shadow cursor-pointer">
                            <span>▶ Tone</span>
                        </button>
                    </div>
                    <select id="options-sound-select" class="w-full bg-[#131722] border border-gray-700 rounded px-2.5 py-1.5 text-xs text-white focus:outline-none cursor-pointer font-medium">
                        <option value="pulse" selected>1. ⚡ Digital Cyber Pulse (High-Tech Double Beep)</option>
                        <option value="sonar">2. 🎵 Radar Sonar Ping (Submarine Echo)</option>
                        <option value="rapid_beeps">3. 🔥 Rapid Fire Trader Pips (Fast 4-Tone Alert)</option>
                        <option value="chime">4. 🔔 Classic Bell Chime (Harmonic Twin Bells)</option>
                        <option value="gong">5. 💥 Deep Resonant Gong (Sub-Bass Strike)</option>
                        <option value="bull_horn">6. 🚀 Bull Horn Fanfare (Ascending Major Chord)</option>
                        <option value="bear_drop">7. ⚠️ Bear Drop Klaxon (Descending Warning)</option>
                        <option value="siren">8. 🚨 Siren Warning (Emergency Warble)</option>
                        <option value="fanfare">9. 🎺 Trumpet Breakout (Bright Brass Fanfare)</option>
                        <option value="caution_klaxon">10. 🛑 Urgent Caution Buzzer (Industrial Sawtooth)</option>
                    </select>
                </div>
            </div>

            <!-- ════════════ TAB 3: HIGH-PROBABILITY CONFLUENCE & 0CLICK ════════════ -->
            <div id="alarm-pane-confluence" class="alarm-pane hidden space-y-3">
                <div class="p-2.5 rounded-lg bg-gradient-to-r from-amber-950/40 to-rose-950/40 border border-amber-600/40 space-y-1.5 text-xs">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xs font-bold text-amber-300 flex items-center gap-1.5">
                                <span>🔥 High-Probability Confluence Alert</span>
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-amber-500/20 text-amber-200 border border-amber-400/40">HIGH CONVICTION</span>
                            </div>
                            <span class="text-[10px] text-amber-200/80 block mt-0.5">Triggers ONLY when Nifty 50 Stocks &amp; Options Breadth agree in the SAME direction</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="cfg-confluence-enabled" class="sr-only peer" checked>
                            <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-600"></div>
                        </label>
                    </div>
                </div>

                <!-- Confluence Dedicated Sound -->
                <div class="p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 space-y-1.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-white flex items-center gap-1">
                            <span>🎵 Confluence Alert Sound (High Priority):</span>
                        </span>
                        <button type="button" id="btn-test-confluence-sound" title="Preview audio tone" class="px-2 py-0.5 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded flex items-center gap-1 text-[11px] transition shadow cursor-pointer">
                            <span>▶ Tone</span>
                        </button>
                    </div>
                    <select id="confluence-sound-select" class="w-full bg-[#131722] border border-gray-700 rounded px-2.5 py-1.5 text-xs text-white focus:outline-none cursor-pointer font-medium">
                        <option value="siren" selected>1. 🚨 Siren Warning (Emergency Warble - High Priority)</option>
                        <option value="bear_drop">2. ⚠️ Bear Drop Klaxon (Descending Warning)</option>
                        <option value="caution_klaxon">3. 🛑 Urgent Caution Buzzer (Industrial Sawtooth)</option>
                        <option value="fanfare">4. 🎺 Trumpet Breakout (Bright Brass Fanfare)</option>
                        <option value="rapid_beeps">5. 🔥 Rapid Fire Trader Pips (Fast 4-Tone Alert)</option>
                        <option value="bull_horn">6. 🚀 Bull Horn Fanfare (Ascending Major Chord)</option>
                        <option value="gong">7. 💥 Deep Resonant Gong (Sub-Bass Strike)</option>
                        <option value="pulse">8. ⚡ Digital Cyber Pulse (High-Tech Double Beep)</option>
                        <option value="chime">9. 🔔 Classic Bell Chime (Harmonic Twin Bells)</option>
                        <option value="sonar">10. 🎵 Radar Sonar Ping (Submarine Echo)</option>
                    </select>
                </div>

                <!-- ⚡ 0Click.in Trade Webhook & Logs [Contract v1.0] -->
                <div class="p-3 rounded-lg bg-[#1a1e29] border border-sky-900/50 space-y-2.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-sky-400 flex items-center gap-1.5">
                            <span>⚡ 0Click.in Trade Webhook &amp; Logs</span>
                            <span class="text-[10px] bg-sky-950 text-sky-300 border border-sky-700/60 px-1.5 py-0.2 rounded font-mono">Contract v1.0</span>
                        </span>
                        <label class="flex items-center gap-1.5 cursor-pointer text-[11px] font-bold text-gray-200">
                            <input type="checkbox" id="zc-auto-log-toggle" checked class="accent-sky-500 w-3.5 h-3.5 cursor-pointer">
                            <span>Auto-Log Internally</span>
                        </label>
                    </div>

                    <!-- Forward Webhook Toggle (Off = Save logs internally only; On = Auto-dispatch) -->
                    <div class="flex items-center justify-between p-2 rounded bg-[#131722] border border-gray-800">
                        <div>
                            <div class="text-[11px] font-bold text-gray-200 flex items-center gap-1.5">
                                <span>Dispatch to 0click.in Webhook</span>
                                <span id="zc-mode-badge" class="px-1.5 py-0.2 rounded text-[9px] font-mono bg-purple-500/20 text-purple-300 border border-purple-500/40">SIMULATION</span>
                            </div>
                            <div class="text-[10px] text-gray-400">Off = Save logs internally only; On = Auto-dispatch to 0click.in API</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="zc-forward-toggle" class="sr-only peer">
                            <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-600"></div>
                        </label>
                    </div>

                    <!-- Webhook Connection Details -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div>
                            <label class="text-[10px] text-gray-400 block mb-0.5">0Click Webhook URL</label>
                            <input type="text" id="zc-webhook-url" value="https://0click.in/api/webhook/tradingview" class="w-full bg-[#131722] border border-gray-700 rounded px-2 py-1 text-xs text-white focus:outline-none font-mono">
                        </div>
                        <div>
                            <label class="text-[10px] text-amber-300 font-semibold block mb-0.5">🔑 Security Token ("X-Webhook-Token")</label>
                            <div class="relative flex items-center">
                                <input type="password" id="zc-webhook-token" placeholder="Paste your 0click token (tv_sec_...)" class="w-full bg-[#131722] border border-gray-700 rounded px-2 py-1 pr-8 text-xs text-amber-200 focus:outline-none font-mono focus:border-amber-400">
                                <button type="button" id="btn-toggle-zc-token" title="Show / Hide Token" class="absolute right-2 text-gray-400 hover:text-white text-xs cursor-pointer">👁️</button>
                            </div>
                        </div>
                    </div>

                    <!-- Trade Order Sizing & SL / TP -->
                    <div class="grid grid-cols-4 gap-2 text-[11px]">
                        <div>
                            <label class="text-[10px] text-gray-400 block mb-0.5">Execution Mode</label>
                            <select id="zc-mode-select" class="w-full bg-[#131722] border border-gray-700 rounded px-1.5 py-1 text-xs text-white focus:outline-none">
                                <option value="SIMULATION" selected>SIMULATION</option>
                                <option value="LIVE">LIVE</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-400 block mb-0.5">Lots</label>
                            <input type="number" id="zc-lots-input" min="1" max="50" value="1" class="w-full bg-[#131722] border border-gray-700 rounded px-2 py-1 text-xs text-white text-center font-mono focus:outline-none">
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-400 block mb-0.5">SL Offset (Pts)</label>
                            <input type="number" id="zc-sl-pts-input" min="10" max="500" value="100" class="w-full bg-[#131722] border border-gray-700 rounded px-2 py-1 text-xs text-rose-300 text-center font-mono focus:outline-none">
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-400 block mb-0.5">TP Offset (Pts)</label>
                            <input type="number" id="zc-tp-pts-input" min="20" max="1000" value="200" class="w-full bg-[#131722] border border-gray-700 rounded px-2 py-1 text-xs text-emerald-300 text-center font-mono focus:outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer Actions -->
            <div class="flex items-center justify-between pt-2 border-t border-gray-800 text-xs">
                <span class="text-[11px] text-gray-400">Settings saved locally in browser</span>
                <button type="button" id="btn-save-alarm-settings" class="px-5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded shadow transition cursor-pointer">
                    Save &amp; Apply
                </button>
            </div>
        </div>
    </div>

    <!-- ════════════ ACTIVE TRIGGERED ALARM POPUP (CONTINUOUS AUDIO LOOP) ════════════ -->
    <div id="active-alarm-overlay" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md hidden flex items-center justify-center p-4">
        <div id="active-alarm-card" class="rounded-3xl p-6 sm:p-8 max-w-md w-full text-center space-y-4 shadow-2xl relative transition-all duration-300 bg-[#131722] border-2 border-amber-500">
            <!-- Top Pill Badge -->
            <div class="flex justify-center">
                <div id="alarm-banner-badge" class="px-4 py-1 rounded-full font-black text-xs uppercase tracking-widest text-white shadow-md bg-black/40 border border-white/20 flex items-center gap-2">
                    <span id="alarm-badge-left-icon">🐂</span>
                    <span id="alarm-badge-title">BULLISH BREADTH EXTREME</span>
                    <span id="alarm-badge-right-icon">🐂</span>
                </div>
            </div>

            <!-- Distinct Sentiment Icon (Bull 🐂 or Bear 🐻) -->
            <div class="flex justify-center">
                <div id="alarm-sentiment-icon" class="w-20 h-20 rounded-full flex items-center justify-center text-4xl sm:text-5xl shadow-xl border-2 select-none bg-emerald-500/20 border-emerald-400 text-emerald-300">
                    🐂
                </div>
            </div>

            <!-- Metrics & Breadth Breakdown (Both Count & Weight explicitly highlighted) -->
            <div class="space-y-1">
                <div class="text-5xl sm:text-6xl font-black font-mono tracking-tight text-white drop-shadow-md" id="alarm-active-pct">
                    84%
                </div>
                <div class="text-base sm:text-lg font-black uppercase tracking-wider text-white drop-shadow-sm" id="alarm-active-direction">
                    BULLISH SURGE DETECTED
                </div>
                <p class="text-xs sm:text-sm text-white/90 font-medium max-w-sm mx-auto leading-relaxed pt-1" id="alarm-active-details">
                    At 09:55 - 42 stocks Green (87.2% weight) &bull; Threshold 80% breached!
                </p>
                <div id="alarm-active-confluence-note" class="hidden text-[11px] font-bold text-amber-300 bg-amber-950/60 border border-amber-500/40 px-2 py-0.5 rounded-full inline-block">
                    🌟 Confluence: Options Breadth agrees in the same direction!
                </div>
            </div>

            <!-- 0Click Option Contract Strip (Option Selling 1-ITM 100s) -->
            <div class="p-3 rounded-2xl bg-black/50 border border-white/20 text-left text-xs space-y-2 backdrop-blur-sm shadow-inner">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-white/95 flex items-center gap-1.5 text-[11px]">
                        <span>⚡ 0Click Option Contract (Option Selling):</span>
                        <span id="alarm-zeroclick-status-pill" class="px-1.5 py-0.2 rounded text-[10px] font-mono bg-sky-500/25 text-sky-200 border border-sky-400/50">💾 LOGGED INTERNALLY</span>
                    </span>
                    <button type="button" id="btn-toggle-payload-preview" class="text-[10px] text-sky-300 hover:text-white underline cursor-pointer font-semibold">
                        View JSON Payload
                    </button>
                </div>
                <div class="font-mono text-[11px] text-white flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span id="alarm-trade-action-badge" class="font-black px-1.5 py-0.2 rounded bg-rose-500 text-white text-[10px] shadow-sm">SELL</span>
                    <span id="alarm-trade-strike-badge" class="font-black px-2 py-0.5 rounded bg-amber-400 text-black text-[11px] shadow-sm">25900 PE (1-ITM)</span>
                    <span>Spot: <strong id="alarm-trade-price" class="text-white">25,850.50</strong></span>
                    <span class="text-white/60">&bull;</span>
                    <span>SL: <span id="alarm-trade-sl" class="text-rose-200 font-bold">25,750</span></span>
                    <span class="text-white/60">&bull;</span>
                    <span>TP: <span id="alarm-trade-tp" class="text-emerald-200 font-bold">26,050</span></span>
                    <span class="text-white/60">&bull;</span>
                    <span id="alarm-trade-lots-badge" class="text-white/80">1 Lot (SIMULATION)</span>
                </div>
                <div id="alarm-trade-payload-box" class="hidden pt-1 border-t border-white/10 font-mono text-[10px] text-white/90 overflow-x-auto max-h-24 bg-black/70 p-2 rounded">
                    <pre id="alarm-trade-payload-code">{}</pre>
                </div>
            </div>

            <!-- Actions: Manual 0Click Dispatch & Stop Audio Alarm -->
            <div class="pt-1 flex flex-wrap items-center justify-center gap-2">
                <button type="button" id="btn-dispatch-active-alarm" class="px-5 py-2.5 bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs sm:text-sm rounded-xl shadow-lg border border-sky-400/60 transition inline-flex items-center gap-1.5 cursor-pointer">
                    <span>🚀</span>
                    <span>Send to 0click.in</span>
                </button>
                <button type="button" id="btn-stop-active-alarm" class="px-5 py-2.5 bg-gray-950/80 hover:bg-black text-white font-bold text-xs sm:text-sm rounded-xl border border-white/30 hover:border-white/60 shadow-xl transition inline-flex items-center gap-2 cursor-pointer">
                    <span class="text-base">🔕</span>
                    <span>Stop Alarm</span>
                    <span class="text-[10px] text-gray-300 font-mono bg-white/10 px-1.5 py-0.5 rounded border border-white/20">ESC</span>
                </button>
            </div>

            <p class="text-[11px] text-white/75 font-medium">
                Audio loops continuously until stopped or dismissed
            </p>
        </div>
    </div>

    <!-- ════════════ 0CLICK.IN SIGNAL EXECUTION & AUDIT LOGS MODAL ════════════ -->
    <div id="zeroclick-logs-modal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4">
        <div class="bg-[#131722] border border-gray-700 rounded-2xl shadow-2xl w-full max-w-5xl flex flex-col max-h-[90vh] overflow-hidden">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-4 border-b border-gray-800 bg-[#1a1e29]">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl">📋</span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold text-white">0Click.in Signal Execution &amp; Internal Audit Logs</h3>
                            <span id="modal-log-count-badge" class="px-2 py-0.2 bg-sky-950 text-sky-400 border border-sky-700/60 rounded-full font-mono text-[11px]">0 Signals</span>
                        </div>
                        <p class="text-[11px] text-gray-400">Internal records of Nifty breadth extreme alerts formatted for 0click.in trade webhook</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="btn-create-test-log" title="Log a simulated test signal right now" class="px-2.5 py-1 bg-emerald-700 hover:bg-emerald-600 text-white font-semibold text-xs rounded transition flex items-center gap-1 cursor-pointer">
                        <span>🧪</span>
                        <span>Test Log</span>
                    </button>
                    <button type="button" id="btn-refresh-logs" title="Refresh logs from database" class="px-2.5 py-1 bg-gray-800 hover:bg-gray-700 text-gray-200 font-semibold text-xs rounded transition flex items-center gap-1 border border-gray-700 cursor-pointer">
                        <span>🔄</span>
                        <span>Refresh</span>
                    </button>
                    <button type="button" id="btn-clear-all-logs" title="Clear recorded logs" class="px-2.5 py-1 bg-rose-950/80 hover:bg-rose-900 text-rose-300 font-semibold text-xs rounded transition flex items-center gap-1 border border-rose-800/60 cursor-pointer">
                        <span>🗑️</span>
                        <span>Clear</span>
                    </button>
                    <button type="button" id="btn-close-logs-modal" class="p-1 text-gray-400 hover:text-white rounded hover:bg-gray-800 text-lg leading-none cursor-pointer">
                        &times;
                    </button>
                </div>
            </div>

            <!-- Filter Bar & Token Config Status -->
            <div class="px-4 py-2.5 border-b border-gray-800 bg-[#151923] flex flex-wrap items-center justify-between gap-2 text-xs">
                <div class="flex items-center gap-2">
                    <span class="text-gray-400 text-[11px]">Filter Signal:</span>
                    <select id="log-filter-action" class="bg-[#1a1e29] border border-gray-700 rounded px-2 py-1 text-xs text-white focus:outline-none cursor-pointer">
                        <option value="ALL">All Signals</option>
                        <option value="BUY">Bullish Signals (Sell ITM PE)</option>
                        <option value="SELL">Bearish Signals (Sell ITM CE)</option>
                    </select>

                    <span class="text-gray-400 text-[11px] ml-2">Status:</span>
                    <select id="log-filter-status" class="bg-[#1a1e29] border border-gray-700 rounded px-2 py-1 text-xs text-white focus:outline-none cursor-pointer">
                        <option value="ALL">All Statuses</option>
                        <option value="LOGGED">LOGGED (Internal)</option>
                        <option value="SENT">SENT (Dispatched)</option>
                        <option value="FAILED">FAILED</option>
                    </select>
                </div>

                <div class="flex items-center gap-3 text-[11px]">
                    <div class="hidden sm:flex items-center gap-1 text-gray-400">
                        <span>Endpoint:</span>
                        <code class="text-sky-400 font-mono text-[10px]" id="label-current-endpoint">https://0click.in/api/webhook/tradingview</code>
                    </div>
                </div>
            </div>

            <!-- Table Container -->
            <div class="overflow-y-auto flex-1 p-4">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-gray-800 text-[10px] text-gray-400 uppercase tracking-wider font-mono">
                            <th class="pb-2">Time / Date</th>
                            <th class="pb-2">Signal</th>
                            <th class="pb-2">Option Contract (1-ITM)</th>
                            <th class="pb-2 text-right">Spot Price</th>
                            <th class="pb-2 text-right">SL</th>
                            <th class="pb-2 text-right">TP</th>
                            <th class="pb-2 text-center">Lots / Mode</th>
                            <th class="pb-2 text-center">Status</th>
                            <th class="pb-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody id="logs-table-body" class="divide-y divide-gray-800/60 font-mono text-xs">
                        <!-- Populated by JavaScript -->
                    </tbody>
                </table>
                <div id="logs-empty-state" class="py-12 text-center text-gray-500 text-xs hidden">
                    No signal execution logs found for the selected filter.
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
(function() {
    'use strict';

    // ── Application State ──────────────────────────────────────────────────────────
    let selectedDate = "{{ $selectedDate }}";
    let selectedExpiry = "{{ $selectedExpiry }}";
    let activeCandleIndex = 0;
    let timestampsList = [];
    let isPlaying = false;
    let playIntervalId = null;
    let autoRefreshTimerId = null;
    let autoRefreshCountdown = 300;
    let activeRefreshSeconds = 300;
    let currentChartHeight = parseInt(localStorage.getItem('nifty_unified_chart_height') || '420', 10);

    // Audio & Alarm System
    let audioCtx = null;
    let alarmLoopInterval = null;
    let isAlarmRinging = false;
    let alertedCandlesSet = new Set();
    let currentActiveLogId = null;
    let currentActivePayload = null;
    let cachedLogsList = [];
    let loggedSignalsSet = new Set();

    // 0Click.in Trade Webhook Settings
    const defaultZeroClickSettings = {
        autoLog: true,
        forwardToZeroClick: false,
        webhookUrl: 'https://0click.in/api/webhook/tradingview',
        webhookToken: '',
        mode: 'SIMULATION',
        lots: 1,
        slOffset: 100,
        tpOffset: 200,
        product: 'MIS'
    };
    let zeroClickSettings = { ...defaultZeroClickSettings };
    try {
        const savedZc = localStorage.getItem('nifty_zeroclick_settings');
        if (savedZc) zeroClickSettings = { ...zeroClickSettings, ...JSON.parse(savedZc) };
    } catch(e) {}

    // Breadth Extreme & Confluence Alarm Settings (Detailed Split Configuration)
    const defaultAlarmSettings = {
        masterEnabled: true,
        volume: 1.0,

        // 1. NIFTY 50 Stocks Breadth Alert
        stocksAlertEnabled: true,
        stocksUpThreshold: 80,
        stocksDownThreshold: 80,
        stocksTriggerBy: 'both', // 'both' (Count & Weight), 'either', 'count', 'weight'
        stocksShowMarkers: true,
        stocksSound: 'bull_horn',

        // 2. NIFTY 50 Options Breadth Alert
        optionsAlertEnabled: true,
        optionsUpThreshold: 70,
        optionsDownThreshold: 70,
        optionsShowMarkers: true,
        optionsSound: 'pulse',

        // 3. High-Probability Confluence Alert (Combined)
        confluenceEnabled: true,
        confluenceSound: 'siren'
    };

    let alarmSettings = { ...defaultAlarmSettings };
    try {
        const saved = localStorage.getItem('nifty_unified_alarm_settings');
        if (saved) alarmSettings = { ...alarmSettings, ...JSON.parse(saved) };
    } catch (e) {
        console.warn('Could not read alarm settings:', e);
    }

    // Data stores
    let weightageData = null;
    let breadthData = null;
    let trendingOiData = null;
    let activeBuildupTf = '5m';

    // Chart instances
    let wChart = null;
    let wCandleSeries = null;
    let oChart = null;
    let oCandleSeries = null;
    let toiChartInstance = null;
    let toiSentimentChartInstance = null;
    let buildupChartInstance = null;

    function fmt(val) {
        if (val === null || val === undefined || isNaN(val)) return '0';
        return Number(val).toLocaleString('en-IN');
    }

    // ── 1. Web Audio Synthesizer (10 Rich Procedural Tones) ──────────────────────
    function getAudioContext() {
        if (!audioCtx) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (AudioContextClass) audioCtx = new AudioContextClass();
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        return audioCtx;
    }

    function playSoundByName(name, volume = 1.0) {
        const ctx = getAudioContext();
        if (!ctx) return;
        const now = ctx.currentTime;
        const masterGain = ctx.createGain();
        masterGain.gain.setValueAtTime(volume, now);
        masterGain.connect(ctx.destination);

        switch(name) {
            case 'siren': {
                // 1. Siren Warning (Emergency Warble)
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(880, now);
                osc.frequency.linearRampToValueAtTime(580, now + 0.35);
                osc.frequency.linearRampToValueAtTime(880, now + 0.7);
                gain.gain.setValueAtTime(0.4, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.75);
                osc.connect(gain);
                gain.connect(masterGain);
                osc.start(now);
                osc.stop(now + 0.75);
                break;
            }
            case 'chime': {
                // 2. Classic Bell Chime (Harmonic Twin Bells)
                [1318.5, 1760.0].forEach((freq, i) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, now + (i * 0.1));
                    gain.gain.setValueAtTime(0.5, now + (i * 0.1));
                    gain.gain.exponentialRampToValueAtTime(0.001, now + (i * 0.1) + 0.85);
                    osc.connect(gain);
                    gain.connect(masterGain);
                    osc.start(now + (i * 0.1));
                    osc.stop(now + (i * 0.1) + 0.85);
                });
                break;
            }
            case 'pulse': {
                // 3. Digital Cyber Pulse (High-Tech Double Beep)
                [1500, 1900].forEach((freq, i) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, now + (i * 0.12));
                    gain.gain.setValueAtTime(0.45, now + (i * 0.12));
                    gain.gain.exponentialRampToValueAtTime(0.01, now + (i * 0.12) + 0.09);
                    osc.connect(gain);
                    gain.connect(masterGain);
                    osc.start(now + (i * 0.12));
                    osc.stop(now + (i * 0.12) + 0.09);
                });
                break;
            }
            case 'gong': {
                // 4. Deep Resonant Gong (Sub-Bass Strike)
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(175, now);
                osc.frequency.exponentialRampToValueAtTime(75, now + 1.2);
                gain.gain.setValueAtTime(0.7, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 1.2);
                osc.connect(gain);
                gain.connect(masterGain);
                osc.start(now);
                osc.stop(now + 1.2);
                break;
            }
            case 'bull_horn': {
                // 5. Bull Horn Fanfare (Ascending Major Chord)
                [523.25, 659.25, 783.99, 1046.50].forEach((freq, i) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(freq, now + (i * 0.08));
                    gain.gain.setValueAtTime(0.4, now + (i * 0.08));
                    gain.gain.exponentialRampToValueAtTime(0.001, now + (i * 0.08) + 0.45);
                    osc.connect(gain);
                    gain.connect(masterGain);
                    osc.start(now + (i * 0.08));
                    osc.stop(now + (i * 0.08) + 0.45);
                });
                break;
            }
            case 'bear_drop': {
                // 6. Bear Drop Klaxon (Descending Warning)
                [783.99, 622.25, 523.25].forEach((freq, i) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(freq, now + (i * 0.12));
                    gain.gain.setValueAtTime(0.35, now + (i * 0.12));
                    gain.gain.exponentialRampToValueAtTime(0.001, now + (i * 0.12) + 0.38);
                    osc.connect(gain);
                    gain.connect(masterGain);
                    osc.start(now + (i * 0.12));
                    osc.stop(now + (i * 0.12) + 0.38);
                });
                break;
            }
            case 'sonar': {
                // 7. Radar Sonar Ping (Submarine Echo)
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(1046.5, now);
                gain.gain.setValueAtTime(0.55, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 1.1);
                osc.connect(gain);
                gain.connect(masterGain);
                osc.start(now);
                osc.stop(now + 1.1);
                break;
            }
            case 'rapid_beeps': {
                // 8. Rapid Fire Trader Pips (Fast 4-Tone Alert)
                [1200, 1400, 1600, 1800].forEach((freq, i) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'square';
                    osc.frequency.setValueAtTime(freq, now + (i * 0.07));
                    gain.gain.setValueAtTime(0.25, now + (i * 0.07));
                    gain.gain.exponentialRampToValueAtTime(0.001, now + (i * 0.07) + 0.05);
                    osc.connect(gain);
                    gain.connect(masterGain);
                    osc.start(now + (i * 0.07));
                    osc.stop(now + (i * 0.07) + 0.05);
                });
                break;
            }
            case 'fanfare': {
                // 9. Trumpet Breakout (Bright Brass Fanfare)
                [587.33, 739.99, 880.00, 1174.66].forEach((freq, i) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(freq, now + (i * 0.09));
                    gain.gain.setValueAtTime(0.4, now + (i * 0.09));
                    gain.gain.exponentialRampToValueAtTime(0.001, now + (i * 0.09) + 0.45);
                    osc.connect(gain);
                    gain.connect(masterGain);
                    osc.start(now + (i * 0.09));
                    osc.stop(now + (i * 0.09) + 0.45);
                });
                break;
            }
            case 'caution_klaxon': {
                // 10. Urgent Caution Buzzer (Industrial Sawtooth)
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(440, now);
                osc.frequency.linearRampToValueAtTime(880, now + 0.2);
                osc.frequency.linearRampToValueAtTime(440, now + 0.4);
                gain.gain.setValueAtTime(0.4, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.45);
                osc.connect(gain);
                gain.connect(masterGain);
                osc.start(now);
                osc.stop(now + 0.45);
                break;
            }
        }
    }

    // ── 2. 0Click.in Trade Webhook Calculation & Dispatch Integration ─────────────
    // Bullish (UP/BUY alert): SELL nearest ITM PE in 100s (Strike > Spot) -> Math.floor(spot/100 + 1) * 100
    // Bearish (DOWN/SELL alert): SELL nearest ITM CE in 100s (Strike < Spot) -> Math.ceil(spot/100 - 1) * 100
    function calculateOptionSellContract(direction, spotPrice) {
        const isBull = (direction === 'UP' || direction === 'BULL' || direction === 'BUY');
        const signal = isBull ? 'BUY' : 'SELL';
        const instrument = isBull ? 'PE' : 'CE';
        const strike = isBull
            ? (Math.floor(spotPrice / 100.0) + 1) * 100
            : (Math.ceil(spotPrice / 100.0) - 1) * 100;
        const strikeName = `NIFTY ${strike} ${instrument}`;
        const sl = isBull
            ? +(spotPrice - zeroClickSettings.slOffset).toFixed(2)
            : +(spotPrice + zeroClickSettings.slOffset).toFixed(2);
        const tp = isBull
            ? +(spotPrice + zeroClickSettings.tpOffset).toFixed(2)
            : +(spotPrice - zeroClickSettings.tpOffset).toFixed(2);

        return {
            isBull,
            signal,
            action: 'SELL',
            instrument,
            strike,
            strikeName,
            sl,
            tp,
            payload: {
                category: 'LOCAL_SERVER',
                signal: signal,
                action: 'SELL',
                trade_type: 'SELL',
                symbol: 'NIFTY',
                instrument: instrument,
                option_type: instrument,
                strike: strike,
                strike_name: strikeName,
                trading_symbol: strikeName,
                price: spotPrice,
                sl: sl,
                tp: tp,
                lots: zeroClickSettings.lots,
                mode: zeroClickSettings.mode,
                product: zeroClickSettings.product
            }
        };
    }

    function logAlertInternally(alertData, spotPrice, payload, dispatchNow = false, callback = null, contractObj = null) {
        const dateStr = selectedDate || new Date().toISOString().slice(0, 10);
        const isBull = (alertData.direction === 'UP' || alertData.direction === 'BULL' || alertData.signal === 'BUY');
        const marketSignal = isBull ? 'BUY' : 'SELL';
        const contract = contractObj || calculateOptionSellContract(marketSignal, spotPrice);
        const logKey = `${dateStr}_${alertData.time}_${marketSignal}`;

        if (loggedSignalsSet.has(logKey) && !dispatchNow) {
            return;
        }
        loggedSignalsSet.add(logKey);

        fetch('/api/nifty-weightage-monitor/alert-log', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({
                signal: contract.signal,
                action: 'SELL',
                instrument: contract.instrument,
                strike: contract.strike,
                symbol: 'NIFTY',
                price: spotPrice,
                sl: contract.sl,
                tp: contract.tp,
                date: dateStr,
                time: alertData.time,
                sl_offset: zeroClickSettings.slOffset,
                tp_offset: zeroClickSettings.tpOffset,
                lots: zeroClickSettings.lots,
                mode: zeroClickSettings.mode,
                product: zeroClickSettings.product,
                category: 'LOCAL_SERVER',
                breadth_pct: alertData.score || alertData.countPct || 80,
                breadth_weight_pct: alertData.weight || 80,
                breadth_count: alertData.count || 40,
                net_points: alertData.pts || 0,
                top_contributor: 'NIFTY 50',
                dispatch_now: dispatchNow,
                webhook_url: zeroClickSettings.webhookUrl,
                webhook_token: zeroClickSettings.webhookToken,
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                currentActiveLogId = data.log.id;
                const pill = document.getElementById('alarm-zeroclick-status-pill');
                if (pill) {
                    pill.textContent = data.log.status === 'SENT' ? '🚀 DISPATCHED (200 OK)' : `💾 LOGGED INTERNALLY (#${data.log.id})`;
                    pill.className = data.log.status === 'SENT'
                        ? 'px-1.5 py-0.2 rounded text-[10px] font-mono bg-emerald-500/25 text-emerald-200 border border-emerald-400/50'
                        : 'px-1.5 py-0.2 rounded text-[10px] font-mono bg-sky-500/25 text-sky-200 border border-sky-400/50';
                }
                fetchAlertLogs(false);
                if (callback) callback(data);
            }
        })
        .catch(err => console.warn('Could not log alert internally:', err));
    }

    function dispatchLogToZeroClick(logId, triggerBtn = null) {
        if (triggerBtn) {
            triggerBtn.disabled = true;
            triggerBtn.textContent = 'Sending...';
        }

        fetch(`/api/nifty-weightage-monitor/alert-logs/${logId}/dispatch`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({
                webhook_url: zeroClickSettings.webhookUrl,
                webhook_token: zeroClickSettings.webhookToken
            })
        })
        .then(res => res.json())
        .then(data => {
            if (triggerBtn) {
                triggerBtn.disabled = false;
                triggerBtn.textContent = '🚀 Send';
            }
            alert(`0Click Response [${data.status_code || 200}]: ${data.message || 'Dispatched'}`);
            fetchAlertLogs(true);
        })
        .catch(err => {
            if (triggerBtn) {
                triggerBtn.disabled = false;
                triggerBtn.textContent = '🚀 Send';
            }
            alert('Connection error while sending to 0click.in: ' + err.message);
            fetchAlertLogs(true);
        });
    }

    function fetchAlertLogs(renderTable = true) {
        fetch('/api/nifty-weightage-monitor/alert-logs?limit=100', {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                cachedLogsList = data.logs || [];
                loggedSignalsSet = new Set(cachedLogsList.map(l => `${l.trade_date}_${l.candle_time}_${l.signal || (l.action === 'BUY' ? 'BUY' : 'SELL')}`));
                const logCountBadge = document.getElementById('log-count-badge');
                const modalLogCountBadge = document.getElementById('modal-log-count-badge');
                if (logCountBadge) logCountBadge.textContent = cachedLogsList.length;
                if (modalLogCountBadge) modalLogCountBadge.textContent = `${cachedLogsList.length} Signal${cachedLogsList.length === 1 ? '' : 's'}`;
                if (renderTable) renderLogsTable();
            }
        })
        .catch(err => console.warn('Could not fetch alert logs:', err));
    }

    function renderLogsTable() {
        const logsTableBody = document.getElementById('logs-table-body');
        const logsEmptyState = document.getElementById('logs-empty-state');
        if (!logsTableBody) return;

        let filtered = [...cachedLogsList];
        const actionVal = document.getElementById('log-filter-action')?.value || 'ALL';
        const statusVal = document.getElementById('log-filter-status')?.value || 'ALL';

        if (actionVal !== 'ALL') {
            filtered = filtered.filter(l => {
                const sig = l.signal || (l.action === 'BUY' ? 'BUY' : 'SELL');
                return sig === actionVal;
            });
        }
        if (statusVal !== 'ALL') filtered = filtered.filter(l => l.status === statusVal);

        if (filtered.length === 0) {
            logsTableBody.innerHTML = '';
            if (logsEmptyState) logsEmptyState.classList.remove('hidden');
            return;
        }

        if (logsEmptyState) logsEmptyState.classList.add('hidden');

        logsTableBody.innerHTML = filtered.map(log => {
            const isBull = (log.signal === 'BUY' || (!log.signal && log.action === 'BUY') || log.instrument === 'PE');
            const signalBadge = isBull
                ? `<span class="px-2 py-0.5 rounded font-black text-xs bg-emerald-500/20 text-emerald-300 border border-emerald-500/50">🐂 BULL</span>`
                : `<span class="px-2 py-0.5 rounded font-black text-xs bg-rose-500/20 text-rose-300 border border-rose-500/50">🐻 BEAR</span>`;

            const instrument = log.instrument || (isBull ? 'PE' : 'CE');
            const strikeVal = log.strike || (isBull
                ? (Math.floor(log.spot_price / 100) + 1) * 100
                : (Math.ceil(log.spot_price / 100) - 1) * 100);

            const optionContractBadge = `
                <div class="flex items-center gap-1.5">
                    <span class="px-1.5 py-0.5 rounded font-black text-[10px] bg-rose-950/80 text-rose-200 border border-rose-600/60 font-mono">SELL</span>
                    <span class="font-bold text-white text-xs">${strikeVal} ${instrument}</span>
                    <span class="px-1 py-0.2 rounded text-[9px] font-mono ${isBull ? 'bg-emerald-950 text-emerald-300 border border-emerald-600/40' : 'bg-rose-950 text-rose-300 border border-rose-600/40'}">1-ITM</span>
                </div>
            `;

            let statusBadge = '';
            if (log.status === 'SENT') {
                statusBadge = `<span class="px-1.5 py-0.2 rounded font-bold text-[10px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">SENT (${log.api_status_code || 200})</span>`;
            } else if (log.status === 'FAILED') {
                statusBadge = `<span class="px-1.5 py-0.2 rounded font-bold text-[10px] bg-rose-500/20 text-rose-300 border border-rose-500/40">FAILED (${log.api_status_code || 'ERR'})</span>`;
            } else {
                statusBadge = `<span class="px-1.5 py-0.2 rounded font-bold text-[10px] bg-sky-500/20 text-sky-300 border border-sky-500/40">LOGGED</span>`;
            }

            return `
                <tr class="hover:bg-white/[0.02] transition">
                    <td class="py-2.5">
                        <div class="font-bold text-white">${log.candle_time}</div>
                        <div class="text-[10px] text-gray-500">${log.trade_date}</div>
                    </td>
                    <td class="py-2.5">${signalBadge}</td>
                    <td class="py-2.5">${optionContractBadge}</td>
                    <td class="py-2.5 text-right font-bold text-white">${Number(log.spot_price).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                    <td class="py-2.5 text-right text-rose-300 font-semibold">${log.sl_price ? Number(log.sl_price).toLocaleString('en-IN') : '-'}</td>
                    <td class="py-2.5 text-right text-emerald-300 font-semibold">${log.tp_price ? Number(log.tp_price).toLocaleString('en-IN') : '-'}</td>
                    <td class="py-2.5 text-center text-gray-300 text-[11px]">${log.lots || 1}L (${log.execution_mode || 'SIM'})</td>
                    <td class="py-2.5 text-center">${statusBadge}</td>
                    <td class="py-2.5 text-right">
                        <button type="button" class="btn-dispatch-single px-2.5 py-1 bg-sky-700 hover:bg-sky-600 text-white font-bold rounded text-xs transition cursor-pointer" data-id="${log.id}">
                            🚀 Send
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        logsTableBody.querySelectorAll('.btn-dispatch-single').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-id');
                dispatchLogToZeroClick(id, btn);
            });
        });
    }

    // ── 3. Breadth Extreme & High-Probability Confluence Trigger Engine ───────────
    function checkAlarmConditions(timeStr) {
        if (!alarmSettings.masterEnabled) return;
        if (alertedCandlesSet.has(timeStr)) return;

        const wBreadth = weightageData?.breadth_by_time?.[timeStr];
        const oBreadth = breadthData?.breadth_by_time?.[timeStr];
        if (!wBreadth && !oBreadth) return;

        // Evaluate Stocks Bull/Bear breaches
        const upTh = alarmSettings.stocksUpThreshold || 80;
        const downTh = alarmSettings.stocksDownThreshold || 80;
        const trig = alarmSettings.stocksTriggerBy || 'both';

        let wBull = false;
        let wBear = false;
        let stocksScore = 0;

        if (wBreadth) {
            if (trig === 'both') {
                wBull = (wBreadth.green_count_pct >= upTh && wBreadth.green_weight >= upTh);
                wBear = (wBreadth.red_count_pct >= downTh && wBreadth.red_weight >= downTh);
                stocksScore = wBull ? Math.min(wBreadth.green_count_pct, Math.round(wBreadth.green_weight))
                                    : Math.min(wBreadth.red_count_pct, Math.round(wBreadth.red_weight));
            } else if (trig === 'either') {
                wBull = (wBreadth.green_count_pct >= upTh || wBreadth.green_weight >= upTh);
                wBear = (wBreadth.red_count_pct >= downTh || wBreadth.red_weight >= downTh);
                stocksScore = wBull ? Math.max(wBreadth.green_count_pct, Math.round(wBreadth.green_weight))
                                    : Math.max(wBreadth.red_count_pct, Math.round(wBreadth.red_weight));
            } else if (trig === 'count') {
                wBull = (wBreadth.green_count_pct >= upTh);
                wBear = (wBreadth.red_count_pct >= downTh);
                stocksScore = wBull ? wBreadth.green_count_pct : wBreadth.red_count_pct;
            } else if (trig === 'weight') {
                wBull = (wBreadth.green_weight >= upTh);
                wBear = (wBreadth.red_weight >= downTh);
                stocksScore = wBull ? Math.round(wBreadth.green_weight) : Math.round(wBreadth.red_weight);
            }
        }

        // Evaluate Options Bull/Bear breaches
        const oUpTh = alarmSettings.optionsUpThreshold || 70;
        const oDownTh = alarmSettings.optionsDownThreshold || 70;
        const oBull = oBreadth ? (oBreadth.up_weight >= oUpTh) : false;
        const oBear = oBreadth ? (oBreadth.down_weight >= oDownTh) : false;
        const optionsScore = oBull ? Math.round(oBreadth.up_weight) : (oBear ? Math.round(oBreadth.down_weight) : 0);

        // 1. COMBINED HIGH-PROBABILITY CONFLUENCE (Both Weightage and Options Agree in Same Direction!)
        if (alarmSettings.confluenceEnabled && ((wBull && oBull) || (wBear && oBear))) {
            const isBull = (wBull && oBull);
            alertedCandlesSet.add(timeStr);
            triggerAlarmModal({
                type: 'CONFLUENCE',
                direction: isBull ? 'BULL' : 'BEAR',
                title: isBull ? '🔥 BULLISH CONFLUENCE DETECTED' : '🔥 BEARISH CONFLUENCE DETECTED',
                score: stocksScore,
                threshold: isBull ? upTh : downTh,
                time: timeStr,
                count: isBull ? wBreadth?.green_count : wBreadth?.red_count,
                countPct: isBull ? wBreadth?.green_count_pct : wBreadth?.red_count_pct,
                weight: isBull ? wBreadth?.green_weight : wBreadth?.red_weight,
                pts: wBreadth?.nifty_move_pts || 0,
                oStat: isBull ? `${Math.round(oBreadth?.up_weight || 0)}% Bull Flow` : `${Math.round(oBreadth?.down_weight || 0)}% Bear Flow`,
                sound: alarmSettings.confluenceSound || 'siren',
                volume: alarmSettings.volume,
                isConfluence: true
            });
            return;
        }

        // 2. Individual Nifty 50 Stocks Breadth Alert
        if (alarmSettings.stocksAlertEnabled && (wBull || wBear)) {
            const isBull = wBull;
            alertedCandlesSet.add(timeStr);
            triggerAlarmModal({
                type: 'STOCKS',
                direction: isBull ? 'BULL' : 'BEAR',
                title: isBull ? '📊 BULLISH STOCK BREADTH SURGE' : '📊 BEARISH STOCK BREADTH PLUNGE',
                score: stocksScore,
                threshold: isBull ? upTh : downTh,
                time: timeStr,
                count: isBull ? wBreadth?.green_count : wBreadth?.red_count,
                countPct: isBull ? wBreadth?.green_count_pct : wBreadth?.red_count_pct,
                weight: isBull ? wBreadth?.green_weight : wBreadth?.red_weight,
                pts: wBreadth?.nifty_move_pts || 0,
                oStat: oBreadth ? `${Math.round(oBreadth.up_weight)}% Bull / ${Math.round(oBreadth.down_weight)}% Bear` : '---',
                sound: alarmSettings.stocksSound || 'bull_horn',
                volume: alarmSettings.volume,
                isConfluence: false
            });
            return;
        }

        // 3. Individual Nifty Options Breadth Alert
        if (alarmSettings.optionsAlertEnabled && (oBull || oBear)) {
            const isBull = oBull;
            alertedCandlesSet.add(timeStr);
            triggerAlarmModal({
                type: 'OPTIONS',
                direction: isBull ? 'BULL' : 'BEAR',
                title: isBull ? '⚡ OPTIONS BULL FLOW SURGE' : '⚡ OPTIONS BEAR FLOW SURGE',
                score: optionsScore,
                threshold: isBull ? oUpTh : oDownTh,
                time: timeStr,
                count: 0,
                countPct: 0,
                weight: 0,
                pts: wBreadth?.nifty_move_pts || 0,
                oStat: isBull ? `${optionsScore}% Bullish Call Flow` : `${optionsScore}% Bearish Put Flow`,
                sound: alarmSettings.optionsSound || 'pulse',
                volume: alarmSettings.volume,
                isConfluence: false
            });
            return;
        }
    }

    function triggerAlarmModal(data) {
        if (isAlarmRinging) return;
        isAlarmRinging = true;

        // Continuous audio alarm loop until manually stopped or ESC pressed
        playSoundByName(data.sound, data.volume);
        if (alarmLoopInterval) clearInterval(alarmLoopInterval);
        alarmLoopInterval = setInterval(() => {
            playSoundByName(data.sound, data.volume);
        }, 1600);

        // Spot Price from active candle
        const currentCandle = weightageData?.candles?.[activeCandleIndex] || weightageData?.candles?.slice(-1)[0];
        const spotPrice = currentCandle ? currentCandle.close : 25850.50;

        // Calculate Option Sell Contract (1-ITM 100s)
        const contract = calculateOptionSellContract(data.direction, spotPrice);
        currentActivePayload = contract.payload;

        // Populate Card UI
        const isBull = (data.direction === 'BULL' || data.direction === 'UP');
        const badge = document.getElementById('alarm-banner-badge');
        const icon = document.getElementById('alarm-sentiment-icon');
        const card = document.getElementById('active-alarm-card');

        if (badge) {
            badge.innerHTML = `
                <span>${isBull ? '🐂' : '🐻'}</span>
                <span>${data.isConfluence ? '🌟 HIGH PROBABILITY CONFLUENCE' : (isBull ? 'BULLISH BREADTH EXTREME' : 'BEARISH BREADTH EXTREME')}</span>
                <span>${isBull ? '🐂' : '🐻'}</span>
            `;
        }

        if (icon) {
            icon.textContent = isBull ? '🐂' : '🐻';
            icon.className = isBull
                ? 'w-20 h-20 rounded-full flex items-center justify-center text-4xl sm:text-5xl shadow-xl border-2 select-none bg-emerald-500/20 border-emerald-400 text-emerald-300'
                : 'w-20 h-20 rounded-full flex items-center justify-center text-4xl sm:text-5xl shadow-xl border-2 select-none bg-rose-500/20 border-rose-400 text-rose-300';
        }

        if (card) {
            card.className = isBull
                ? 'rounded-3xl p-6 sm:p-8 max-w-md w-full text-center space-y-4 shadow-2xl relative transition-all duration-300 bg-[#131722] border-2 border-emerald-500 shadow-emerald-950/80'
                : 'rounded-3xl p-6 sm:p-8 max-w-md w-full text-center space-y-4 shadow-2xl relative transition-all duration-300 bg-[#131722] border-2 border-rose-500 shadow-rose-950/80';
        }

        document.getElementById('alarm-active-pct').textContent = `${data.score}%`;
        document.getElementById('alarm-active-direction').textContent = data.title;
        document.getElementById('alarm-active-details').innerHTML = `
            At ${data.time} &bull; <strong>${data.count || 40} stocks ${isBull ? 'Green' : 'Red'}</strong> (${(data.weight || 80).toFixed(1)}% weight &bull; ${data.countPct || 80}% count) &bull; Threshold ${data.threshold}% breached!
        `;

        const confluenceNote = document.getElementById('alarm-active-confluence-note');
        if (confluenceNote) {
            if (data.isConfluence) {
                confluenceNote.classList.remove('hidden');
                confluenceNote.textContent = `🌟 Confluence: Options Breadth agrees (${data.oStat})`;
            } else {
                confluenceNote.classList.add('hidden');
            }
        }

        // Populate 0Click Option Contract Strip
        const actionBadge = document.getElementById('alarm-trade-action-badge');
        if (actionBadge) {
            actionBadge.textContent = 'SELL';
            actionBadge.className = 'font-black px-1.5 py-0.2 rounded bg-rose-500 text-white text-[10px] shadow-sm';
        }

        const strikeBadge = document.getElementById('alarm-trade-strike-badge');
        if (strikeBadge) {
            strikeBadge.textContent = `${contract.strike} ${contract.instrument} (1-ITM)`;
            strikeBadge.className = isBull
                ? 'font-black px-2 py-0.5 rounded bg-emerald-400 text-black text-[11px] shadow-sm'
                : 'font-black px-2 py-0.5 rounded bg-amber-400 text-black text-[11px] shadow-sm';
        }

        const priceEl = document.getElementById('alarm-trade-price');
        if (priceEl) priceEl.textContent = spotPrice.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        const slEl = document.getElementById('alarm-trade-sl');
        if (slEl) slEl.textContent = contract.sl.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        const tpEl = document.getElementById('alarm-trade-tp');
        if (tpEl) tpEl.textContent = contract.tp.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        const lotsBadge = document.getElementById('alarm-trade-lots-badge');
        if (lotsBadge) lotsBadge.textContent = `${zeroClickSettings.lots} Lot${zeroClickSettings.lots > 1 ? 's' : ''} (${zeroClickSettings.mode})`;

        const payloadCode = document.getElementById('alarm-trade-payload-code');
        if (payloadCode) payloadCode.textContent = JSON.stringify(contract.payload, null, 2);

        // Internal audit logging & optional auto-dispatch
        if (zeroClickSettings.autoLog) {
            logAlertInternally(data, spotPrice, contract.payload, zeroClickSettings.forwardToZeroClick, null, contract);
        }

        document.getElementById('active-alarm-overlay')?.classList.remove('hidden');
    }

    function stopAlarm() {
        if (alarmLoopInterval) clearInterval(alarmLoopInterval);
        isAlarmRinging = false;
        document.getElementById('active-alarm-overlay')?.classList.add('hidden');
    }

    // ── 3. Apply Dynamic Chart Height (User Requested) ─────────────────────────────
    function applyChartHeight(h) {
        currentChartHeight = h;
        localStorage.setItem('nifty_unified_chart_height', h);

        const select = document.getElementById('chart-height-select');
        if (select && select.value !== String(h)) {
            select.value = String(h);
        }

        // Apply height to Column 1 (Weightage) and Column 2 (Options Breadth)
        const wContainer = document.getElementById('w-chart-container');
        if (wContainer) wContainer.style.height = `${h}px`;
        if (wChart && wContainer) {
            wChart.applyOptions({ height: h, width: wContainer.clientWidth });
        }

        const oContainer = document.getElementById('o-chart-container');
        if (oContainer) oContainer.style.height = `${h}px`;
        if (oChart && oContainer) {
            oChart.applyOptions({ height: h, width: oContainer.clientWidth });
        }

        // Column 2 (Right 25%): 3 Rows evenly distributed
        const toiH = Math.max(180, Math.round((h * 2 - 20) / 3));
        const toiWrapper = document.getElementById('toi-chart-wrapper');
        const bldWrapper = document.getElementById('bld-chart-wrapper');
        const sentWrapper = document.getElementById('toi-sentiment-wrapper');

        if (toiWrapper) toiWrapper.style.height = `${toiH}px`;
        if (bldWrapper) bldWrapper.style.height = `${toiH}px`;
        if (sentWrapper) sentWrapper.style.height = `${toiH}px`;

        if (toiChartInstance) toiChartInstance.resize();
        if (buildupChartInstance) buildupChartInstance.resize();
        if (toiSentimentChartInstance) toiSentimentChartInstance.resize();
    }

    // ── 4. Initialize LightweightCharts ───────────────────────────────────────────
    function initLightweightCharts() {
        const chartOptions = {
            layout: {
                background: { color: '#131722' },
                textColor: '#9ca3af',
                fontSize: 10,
            },
            grid: {
                vertLines: { color: 'rgba(31, 41, 55, 0.5)' },
                horzLines: { color: 'rgba(31, 41, 55, 0.5)' },
            },
            crosshair: {
                mode: LightweightCharts.CrosshairMode.Normal,
                vertLine: { color: '#38bdf8', width: 1, style: 3 },
                horzLine: { color: '#38bdf8', width: 1, style: 3 },
            },
            timeScale: {
                borderColor: '#374151',
                timeVisible: true,
                secondsVisible: false,
            },
            rightPriceScale: {
                borderColor: '#374151',
                scaleMargins: { top: 0.08, bottom: 0.08 },
            },
            handleScroll: { mouseWheel: true, pressedMouseMove: true },
            handleScale: { axisPressedMouseMove: true, mouseWheel: true, pinch: true },
        };

        // Row 1: Weightage Candlestick Chart (75% width)
        const wContainer = document.getElementById('w-chart-container');
        if (wContainer) {
            wContainer.style.height = `${currentChartHeight}px`;
            wContainer.innerHTML = '';
            wChart = LightweightCharts.createChart(wContainer, {
                ...chartOptions,
                width: wContainer.clientWidth,
                height: currentChartHeight,
            });

            wCandleSeries = wChart.addCandlestickSeries({
                upColor: '#10b981',
                downColor: '#ef4444',
                borderUpColor: '#10b981',
                borderDownColor: '#ef4444',
                wickUpColor: '#10b981',
                wickDownColor: '#ef4444',
            });

            wChart.subscribeClick(param => {
                const raw = weightageData?.candles || weightageData?.nifty_candles;
                if (!param || !param.time || !raw) return;
                const idx = raw.findIndex(c => (c.time || c.timestamp) === param.time);
                if (idx !== -1) syncActiveCandleIndex(idx);
            });
        }

        // Row 2: Options Breadth Candlestick Chart (75% width)
        const oContainer = document.getElementById('o-chart-container');
        if (oContainer) {
            oContainer.style.height = `${currentChartHeight}px`;
            oContainer.innerHTML = '';
            oChart = LightweightCharts.createChart(oContainer, {
                ...chartOptions,
                width: oContainer.clientWidth,
                height: currentChartHeight,
            });

            oCandleSeries = oChart.addCandlestickSeries({
                upColor: '#10b981',
                downColor: '#ef4444',
                borderUpColor: '#10b981',
                borderDownColor: '#ef4444',
                wickUpColor: '#10b981',
                wickDownColor: '#ef4444',
            });

            oChart.subscribeClick(param => {
                const raw = breadthData?.nifty_candles;
                if (!param || !param.time || !raw) return;
                const idx = raw.findIndex(c => (c.time || c.timestamp) === param.time);
                if (idx !== -1) syncActiveCandleIndex(idx);
            });
        }

        window.addEventListener('resize', () => {
            if (wChart && wContainer) wChart.applyOptions({ width: wContainer.clientWidth });
            if (oChart && oContainer) oChart.applyOptions({ width: oContainer.clientWidth });
            if (toiChartInstance) toiChartInstance.resize();
            if (buildupChartInstance) buildupChartInstance.resize();
            if (toiSentimentChartInstance) toiSentimentChartInstance.resize();
        });
    }

    // ── 5. Unified Data Fetcher ───────────────────────────────────────────────────
    async function loadAllData(isSilent = false) {
        if (!isSilent) {
            document.getElementById('w-chart-loading')?.classList.remove('hidden');
            document.getElementById('o-chart-loading')?.classList.remove('hidden');
            document.getElementById('toi-chart-loading')?.classList.remove('hidden');
            const spinner = document.getElementById('reload-spinner');
            if (spinner) spinner.classList.add('animate-spin');
        }

        const todayStr = "{{ \Illuminate\Support\Carbon::now('Asia/Kolkata')->toDateString() }}";
        let toiMode = (selectedDate === todayStr) ? 'live' : 'history';

        // 1. Fetch Options Breadth independently & render immediately upon arrival
        const pBreadth = fetch(`/api/options-breadth-monitor/data?symbol=NIFTY&date=${selectedDate}&range=5&expiry=${selectedExpiry}&_t=${Date.now()}`)
            .then(r => r.json())
            .then(data => {
                if (data && !data.error) {
                    breadthData = data;
                    if (breadthData.nifty_candles) {
                        breadthData.nifty_candles.forEach(c => {
                            if (!c.time && c.timestamp) c.time = c.timestamp;
                        });
                    }
                    renderOptionsBreadthColumn();
                    buildMasterTimeline();
                }
            })
            .catch(e => console.error('Error fetching Options Breadth:', e))
            .finally(() => {
                document.getElementById('o-chart-loading')?.classList.add('hidden');
            });

        // 2. Fetch Trending OI independently & render immediately upon arrival
        const pTrendingOi = (async () => {
            try {
                let resToi = await fetch(`/api/trending-oi/data?mode=${toiMode}&expiry=${selectedExpiry}&date=${selectedDate}&underlying=NSE_INDEX|Nifty 50&interval=5&lookback=auto&skip_open=0`).then(r => r.json());
                if (toiMode === 'live' && (!resToi || !resToi.chart || !resToi.chart.labels || resToi.chart.labels.length === 0)) {
                    resToi = await fetch(`/api/trending-oi/data?mode=history&expiry=${selectedExpiry}&date=${selectedDate}&underlying=NSE_INDEX|Nifty 50&interval=5&lookback=auto&skip_open=0`).then(r => r.json());
                }
                if (resToi && !resToi.error) {
                    trendingOiData = resToi;
                    renderTrendingOiRows();
                    buildMasterTimeline();
                }
            } catch (e) {
                console.error('Error fetching Trending OI:', e);
            } finally {
                document.getElementById('toi-chart-loading')?.classList.add('hidden');
            }
        })();

        // 3. Fetch Nifty Weightage independently & render immediately upon arrival
        const pWeightage = fetch(`/api/nifty-weightage-monitor/data?date=${selectedDate}&_t=${Date.now()}`)
            .then(r => r.json())
            .then(data => {
                if (data && !data.error) {
                    weightageData = data;
                    if (!weightageData.candles && weightageData.nifty_candles) {
                        weightageData.candles = weightageData.nifty_candles;
                    }
                    if (weightageData.candles) {
                        weightageData.candles.forEach(c => {
                            if (!c.time && c.timestamp) c.time = c.timestamp;
                        });
                    }
                    renderWeightageColumn();
                    buildMasterTimeline();
                }
            })
            .catch(e => console.error('Error fetching Weightage:', e))
            .finally(() => {
                document.getElementById('w-chart-loading')?.classList.add('hidden');
            });

        try {
            await Promise.allSettled([pBreadth, pTrendingOi, pWeightage]);
            applyChartHeight(currentChartHeight);
            if (timestampsList && timestampsList.length > 0) {
                checkAlarmConditions(timestampsList[timestampsList.length - 1]);
                updateOverallAndTriggerStats(activeCandleIndex >= 0 ? activeCandleIndex : timestampsList.length - 1);
            }
        } finally {
            const spinner = document.getElementById('reload-spinner');
            if (spinner) spinner.classList.remove('animate-spin');
        }
    }

    // ── 6. Render Weightage Row ───────────────────────────────────────────────────
    function renderWeightageColumn() {
        const rawCandles = weightageData?.candles || weightageData?.nifty_candles;
        if (!rawCandles || rawCandles.length === 0) return;
        if (!weightageData.candles) weightageData.candles = rawCandles;

        const chartCandles = rawCandles.map(c => ({
            time: c.time || c.timestamp,
            open: Number(c.open),
            high: Number(c.high),
            low: Number(c.low),
            close: Number(c.close),
        })).filter(c => c.time && !isNaN(c.time));

        if (wCandleSeries && chartCandles.length > 0) {
            wCandleSeries.setData(chartCandles);

            if (!alarmSettings.stocksShowMarkers) {
                wCandleSeries.setMarkers([]);
            } else {
                const markers = [];
                const upTh = alarmSettings.stocksUpThreshold || 80;
                const downTh = alarmSettings.stocksDownThreshold || 80;
                const trig = alarmSettings.stocksTriggerBy || 'both';

                rawCandles.forEach(c => {
                    const candleTime = c.time || c.timestamp;
                    if (!candleTime) return;
                    const b = weightageData.breadth_by_time?.[c.time_str];
                    if (!b) return;

                    let isGreenBreach = false;
                    let isRedBreach = false;

                    if (trig === 'both') {
                        isGreenBreach = (b.green_count_pct >= upTh && b.green_weight >= upTh);
                        isRedBreach   = (b.red_count_pct >= downTh && b.red_weight >= downTh);
                    } else if (trig === 'either') {
                        isGreenBreach = (b.green_count_pct >= upTh || b.green_weight >= upTh);
                        isRedBreach   = (b.red_count_pct >= downTh || b.red_weight >= downTh);
                    } else if (trig === 'count') {
                        isGreenBreach = (b.green_count_pct >= upTh);
                        isRedBreach   = (b.red_count_pct >= downTh);
                    } else if (trig === 'weight') {
                        isGreenBreach = (b.green_weight >= upTh);
                        isRedBreach   = (b.red_weight >= downTh);
                    }

                    if (isGreenBreach) {
                        markers.push({
                            time: candleTime,
                            position: 'belowBar',
                            color: '#10b981',
                            shape: 'arrowUp',
                            text: `${b.green_count_pct}%`,
                        });
                    } else if (isRedBreach) {
                        markers.push({
                            time: candleTime,
                            position: 'aboveBar',
                            color: '#ef4444',
                            shape: 'arrowDown',
                            text: `${b.red_count_pct}%`,
                        });
                    }
                });
                wCandleSeries.setMarkers(markers);
            }
            if (wChart) wChart.timeScale().fitContent();
        }

        const wSlider = document.getElementById('w-slider');
        if (wSlider && rawCandles.length > 0) {
            wSlider.max = rawCandles.length - 1;
            const startLbl = document.getElementById('w-slider-start');
            const endLbl = document.getElementById('w-slider-end');
            if (startLbl) startLbl.textContent = rawCandles[0].time_str;
            if (endLbl) endLbl.textContent = rawCandles[rawCandles.length - 1].time_str;
        }
    }

    // ── 7. Render Options Breadth Row ─────────────────────────────────────────────
    function renderOptionsBreadthColumn() {
        const rawCandles = breadthData?.nifty_candles;
        if (!rawCandles || rawCandles.length === 0) return;

        const chartCandles = rawCandles.map(c => ({
            time: c.time || c.timestamp,
            open: Number(c.open),
            high: Number(c.high),
            low: Number(c.low),
            close: Number(c.close),
        })).filter(c => c.time && !isNaN(c.time));

        if (oCandleSeries && chartCandles.length > 0) {
            oCandleSeries.setData(chartCandles);

            if (!alarmSettings.optionsShowMarkers) {
                oCandleSeries.setMarkers([]);
            } else {
                const markers = [];
                const upTh = alarmSettings.optionsUpThreshold || 70;
                const downTh = alarmSettings.optionsDownThreshold || 70;

                rawCandles.forEach(c => {
                    const candleTime = c.time || c.timestamp;
                    if (!candleTime) return;
                    const b = breadthData.breadth_by_time?.[c.time_str];
                    if (!b) return;

                    if (b.up_weight >= upTh) {
                        markers.push({
                            time: candleTime,
                            position: 'belowBar',
                            color: '#10b981',
                            shape: 'arrowUp',
                            text: `${Math.round(b.up_weight)}%`,
                        });
                    } else if (b.down_weight >= downTh) {
                        markers.push({
                            time: candleTime,
                            position: 'aboveBar',
                            color: '#ef4444',
                            shape: 'arrowDown',
                            text: `${Math.round(b.down_weight)}%`,
                        });
                    }
                });
                oCandleSeries.setMarkers(markers);
            }
            if (oChart) oChart.timeScale().fitContent();
        }

        const oSlider = document.getElementById('o-slider');
        if (oSlider && rawCandles.length > 0) {
            oSlider.max = rawCandles.length - 1;
            const startLbl = document.getElementById('o-slider-start');
            const endLbl = document.getElementById('o-slider-end');
            if (startLbl) startLbl.textContent = rawCandles[0].time_str;
            if (endLbl) endLbl.textContent = rawCandles[rawCandles.length - 1].time_str;
        }
    }

    // ── 8. Render Column 2 (Right 25%): 3 Rows ────────────────────────────────────
    function renderTrendingOiRows() {
        if (!trendingOiData || trendingOiData.error) return;

        // Spot Readout
        if (trendingOiData.underlying_data) {
            const u = trendingOiData.underlying_data;
            const spotEl = document.getElementById('spot-price-display');
            const chgEl = document.getElementById('spot-change-display');
            if (spotEl) spotEl.textContent = Number(u.spot).toFixed(2);
            if (chgEl) {
                const sign = u.change >= 0 ? '+' : '';
                chgEl.textContent = `(${sign}${u.change} / ${sign}${u.change_pct}%)`;
                chgEl.className = u.change >= 0 ? 'font-semibold text-emerald-400 text-[10px]' : 'font-semibold text-rose-400 text-[10px]';
            }
        }

        const chartData = trendingOiData.chart || {};
        const labels = chartData.labels || [];
        const callOi = chartData.call_oi || [];
        const putOi = chartData.put_oi || [];
        const sentiment = chartData.sentiment || [];
        const spot = chartData.spot || [];

        // ROW 1: Trending OI (Call vs Put vs Spot)
        const toiCtx = document.getElementById('chart-trending-oi');
        if (toiCtx) {
            if (toiChartInstance) toiChartInstance.destroy();
            toiChartInstance = new Chart(toiCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Change in Call OI',
                            data: callOi,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.05)',
                            borderWidth: 2,
                            pointRadius: 1.5,
                            tension: 0.2,
                            yAxisID: 'y',
                        },
                        {
                            label: 'Change in Put OI',
                            data: putOi,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.05)',
                            borderWidth: 2,
                            pointRadius: 1.5,
                            tension: 0.2,
                            yAxisID: 'y',
                        },
                        {
                            label: 'Spot Price',
                            data: spot,
                            borderColor: '#06b6d4',
                            borderDash: [3, 3],
                            borderWidth: 1.5,
                            pointRadius: 0,
                            tension: 0.1,
                            yAxisID: 'y1',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#111827',
                            borderColor: '#374151',
                            borderWidth: 1,
                            callbacks: {
                                label: function(ctx) {
                                    if (ctx.dataset.yAxisID === 'y1') return `Spot: ${Number(ctx.parsed.y).toFixed(2)}`;
                                    return `${ctx.dataset.label}: ${fmt(ctx.parsed.y)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 8, family: 'monospace' }, color: '#9ca3af', maxTicksLimit: 6 }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            grid: { color: 'rgba(55, 65, 81, 0.3)' },
                            ticks: {
                                font: { size: 8, family: 'monospace' },
                                color: '#9ca3af',
                                callback: val => (val / 100000).toFixed(0) + 'L'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            ticks: {
                                font: { size: 8, family: 'monospace' },
                                color: '#06b6d4',
                                callback: val => Number(val).toFixed(0)
                            }
                        }
                    }
                }
            });
        }

        // ROW 3: Sentiment Chart (Diff OI vs Spot)
        const sentimentCtx = document.getElementById('chart-trending-sentiment');
        if (sentimentCtx) {
            if (toiSentimentChartInstance) toiSentimentChartInstance.destroy();
            toiSentimentChartInstance = new Chart(sentimentCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Sentiment (Diff in OI)',
                            data: sentiment,
                            borderColor: '#f59e0b',
                            backgroundColor: 'rgba(245, 158, 11, 0.05)',
                            borderWidth: 2,
                            pointRadius: 1.5,
                            tension: 0.2,
                            yAxisID: 'y',
                        },
                        {
                            label: 'Spot Price',
                            data: spot,
                            borderColor: '#06b6d4',
                            borderDash: [3, 3],
                            borderWidth: 1.5,
                            pointRadius: 0,
                            tension: 0.1,
                            yAxisID: 'y1',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#111827',
                            borderColor: '#374151',
                            borderWidth: 1,
                            callbacks: {
                                label: function(ctx) {
                                    if (ctx.dataset.yAxisID === 'y1') return `Spot: ${Number(ctx.parsed.y).toFixed(2)}`;
                                    return `Sentiment: ${fmt(ctx.parsed.y)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 8, family: 'monospace' }, color: '#9ca3af', maxTicksLimit: 6 }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            grid: { color: 'rgba(55, 65, 81, 0.3)' },
                            ticks: {
                                font: { size: 8, family: 'monospace' },
                                color: '#9ca3af',
                                callback: val => (val / 100000).toFixed(0) + 'L'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            ticks: {
                                font: { size: 8, family: 'monospace' },
                                color: '#06b6d4',
                                callback: val => Number(val).toFixed(0)
                            }
                        }
                    }
                }
            });
        }

        // ROW 2: OI Buildup
        renderStrikeBuildupChart();
    }

    // ── 9. Render OI Buildup (Row 2 of Column 2) ──────────────────────────────────
    function renderStrikeBuildupChart() {
        if (!trendingOiData?.strike_buildup) return;
        const bldData = trendingOiData.strike_buildup;
        const tfData = bldData[activeBuildupTf] || bldData['5m'] || bldData;

        const windowBadge = document.getElementById('bld-window-badge');
        if (windowBadge) windowBadge.textContent = tfData.window_label || '09:15 - 15:25';

        if (tfData.summary) {
            const ceNet = document.getElementById('bld-ce-net');
            const peNet = document.getElementById('bld-pe-net');
            const dom = document.getElementById('bld-dominant');
            if (ceNet) ceNet.textContent = tfData.summary.total_ce_chg_lakh || '+0.0 L';
            if (peNet) peNet.textContent = tfData.summary.total_pe_chg_lakh || '+0.0 L';
            if (dom) dom.textContent = tfData.summary.dominant || '---';
        }

        const tfLabels = { '5m': '5M', '15m': '15M', '30m': '30M', 'today': 'Today' };
        const actDesc = document.getElementById('bld-activity-desc');
        if (actDesc) actDesc.textContent = `Top 8 Activity (${tfLabels[activeBuildupTf] || '5M'})`;

        const bldCtx = document.getElementById('chart-oi-buildup');
        if (!bldCtx) return;

        if (buildupChartInstance) {
            buildupChartInstance.destroy();
            buildupChartInstance = null;
        }

        let items = tfData.top_items || tfData.top_buildup || [];
        if (items.length === 0) return;

        const labels = items.map(x => x.label || `${x.strike} ${x.option_type}`);
        const dataVals = items.map(x => x.diff_oi_val_lakh);
        const colors = items.map(x => x.bar_color || '#dc2626');
        const formattedLabels = items.map(x => x.diff_oi_lakh);

        const inBarLabelsPlugin = {
            id: 'inBarLabelsPlugin',
            afterDatasetsDraw(chart) {
                const { ctx } = chart;
                chart.data.datasets.forEach((dataset, i) => {
                    const meta = chart.getDatasetMeta(i);
                    meta.data.forEach((bar, index) => {
                        const valStr = dataset.formattedLabels ? dataset.formattedLabels[index] : null;
                        if (!valStr) return;
                        ctx.save();
                        ctx.font = 'bold 9.5px monospace';
                        const textWidth = ctx.measureText(valStr).width;
                        const barWidth = Math.abs(bar.x - bar.base);

                        if (barWidth > textWidth + 12) {
                            ctx.fillStyle = '#ffffff';
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'middle';
                            ctx.fillText(valStr, (bar.base + bar.x) / 2, bar.y);
                        } else {
                            ctx.fillStyle = '#9ca3af';
                            ctx.textAlign = 'left';
                            ctx.textBaseline = 'middle';
                            ctx.fillText(valStr, bar.x + 4, bar.y);
                        }
                        ctx.restore();
                    });
                });
            }
        };

        buildupChartInstance = new Chart(bldCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: dataVals,
                    backgroundColor: colors,
                    borderRadius: 3,
                    barThickness: 15,
                    formattedLabels: formattedLabels,
                    itemsMeta: items,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { right: 30 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#111827',
                        borderColor: '#374151',
                        borderWidth: 1,
                        callbacks: {
                            label: function(ctx) {
                                const item = ctx.dataset.itemsMeta ? ctx.dataset.itemsMeta[ctx.dataIndex] : null;
                                if (!item) return '';
                                return [
                                    `${item.buildup_name} (${item.buildup_type})`,
                                    `ΔOI: ${item.diff_oi_lakh}`,
                                    `ΔLTP: ${item.diff_ltp}`
                                ];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(55, 65, 81, 0.3)' },
                        ticks: { font: { size: 8, family: 'monospace' }, color: '#9ca3af', callback: v => v + 'L' }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { font: { size: 9, family: 'monospace', weight: 'bold' }, color: '#d1d5db' }
                    }
                }
            },
            plugins: [inBarLabelsPlugin]
        });
    }

    // ── 10. Synchronized Master Timeline ──────────────────────────────────────────
    function buildMasterTimeline() {
        const timeSet = new Set();
        if (weightageData?.candles && weightageData.candles.length > 0) {
            weightageData.candles.forEach(c => { if (c.time_str) timeSet.add(c.time_str); });
        }
        if (breadthData?.timestamps && breadthData.timestamps.length > 0) {
            breadthData.timestamps.forEach(t => timeSet.add(t));
        }
        if (trendingOiData?.chart?.labels && trendingOiData.chart.labels.length > 0) {
            trendingOiData.chart.labels.forEach(t => timeSet.add(t));
        }

        if (timeSet.size > 0) {
            timestampsList = Array.from(timeSet).sort();
        }

        if (timestampsList.length === 0) return;

        const masterSlider = document.getElementById('master-slider');
        if (masterSlider) {
            masterSlider.max = timestampsList.length - 1;
        }

        syncActiveCandleIndex(timestampsList.length - 1);
    }

    function syncActiveCandleIndex(index) {
        if (!timestampsList || index < 0 || index >= timestampsList.length) return;
        activeCandleIndex = index;
        const timeStr = timestampsList[index];

        const masterBadge = document.getElementById('master-time-badge');
        if (masterBadge) masterBadge.textContent = timeStr;
        const masterSlider = document.getElementById('master-slider');
        if (masterSlider) masterSlider.value = index;

        const wSlider = document.getElementById('w-slider');
        if (wSlider) wSlider.value = index;
        const oSlider = document.getElementById('o-slider');
        if (oSlider) oSlider.value = index;

        syncWeightageUI(index, timeStr);
        syncOptionsBreadthUI(index, timeStr);
        syncTrendingOiCursor(index, timeStr);

        // Check alarms on selected candle
        checkAlarmConditions(timeStr);

        // Update overall Bull/Bear picture and Trigger Strength
        updateOverallAndTriggerStats(index);
    }

    function syncWeightageUI(index, timeStr) {
        if (!weightageData) return;

        const candle = (weightageData.candles || []).find(c => c.time_str === timeStr) || weightageData.candles?.[index] || null;
        if (candle) {
            document.getElementById('w-chart-time').textContent = candle.time_str;
            document.getElementById('w-chart-open').textContent = candle.open.toFixed(2);
            document.getElementById('w-chart-close').textContent = candle.close.toFixed(2);
            const chgEl = document.getElementById('w-chart-chg');
            const sign = candle.move_pts >= 0 ? '+' : '';
            chgEl.textContent = `${sign}${candle.move_pts.toFixed(2)} (${sign}${candle.move_pct.toFixed(2)}%)`;
            chgEl.className = candle.move_pts >= 0 ? 'font-bold text-emerald-400' : 'font-bold text-rose-400';
        }

        const b = weightageData.breadth_by_time?.[timeStr];
        if (b) {
            document.getElementById('w-stat-time').textContent = `${timeStr} - 5-min candle`;
            const ptsEl = document.getElementById('w-stat-pts');
            const sign = b.nifty_move_pts >= 0 ? '+' : '';
            ptsEl.textContent = `${sign}${b.nifty_move_pts.toFixed(2)} pts (${sign}${b.nifty_move_pct.toFixed(2)}%)`;
            ptsEl.className = b.nifty_move_pts >= 0 ? 'text-xs font-black font-mono text-emerald-400' : 'text-xs font-black font-mono text-rose-400';

            document.getElementById('w-stat-green-pct').textContent = `${b.green_count_pct}%`;
            document.getElementById('w-stat-green-wt').textContent = `${b.green_weight.toFixed(1)}%`;
            document.getElementById('w-stat-green-cnt').textContent = b.green_count;
            document.getElementById('w-stat-green-pts').textContent = `+${b.net_green_pts.toFixed(1)}`;

            document.getElementById('w-stat-red-pct').textContent = `${b.red_count_pct}%`;
            document.getElementById('w-stat-red-wt').textContent = `${b.red_weight.toFixed(1)}%`;
            document.getElementById('w-stat-red-cnt').textContent = b.red_count;
            document.getElementById('w-stat-red-pts').textContent = `${b.net_red_pts.toFixed(1)}`;

            document.getElementById('w-bar-green').style.width = `${b.green_weight}%`;
            document.getElementById('w-bar-red').style.width = `${b.red_weight}%`;
            document.getElementById('w-stat-split').textContent = `${b.green_weight.toFixed(0)}% G / ${b.red_weight.toFixed(0)}% R`;

            if (b.insight) {
                document.getElementById('w-insight-badge').innerHTML = b.insight.badge || '<span>Institutional Conviction</span>';
                document.getElementById('w-insight-desc').textContent = b.insight.message || '';
            }

            document.getElementById('w-movers-time').textContent = timeStr;
            const driversEl = document.getElementById('w-top-drivers');
            const draggersEl = document.getElementById('w-top-draggers');

            const topDrivers = (b.top_contributors || []).filter(s => s.pts > 0).slice(0, 3);
            if (driversEl) {
                driversEl.innerHTML = topDrivers.length === 0 ? '<div class="text-gray-500 italic text-[8.5px]">None</div>' : topDrivers.map(s => `
                    <div class="flex items-center justify-between px-1.5 py-0.5 rounded bg-emerald-950/40 border border-emerald-800/40 text-[9px]">
                        <span class="font-semibold text-gray-200 truncate pr-1" title="${s.symbol}">
                            ${s.symbol} <span class="text-[8px] text-gray-400">(${s.weight.toFixed(1)}%)</span>
                        </span>
                        <span class="text-emerald-400 font-bold whitespace-nowrap">+${s.pts.toFixed(1)}</span>
                    </div>
                `).join('');
            }

            const topDraggers = (b.top_draggers || []).filter(s => s.pts < 0).slice(0, 3);
            if (draggersEl) {
                draggersEl.innerHTML = topDraggers.length === 0 ? '<div class="text-gray-500 italic text-[8.5px]">None</div>' : topDraggers.map(s => `
                    <div class="flex items-center justify-between px-1.5 py-0.5 rounded bg-rose-950/40 border border-rose-800/40 text-[9px]">
                        <span class="font-semibold text-gray-200 truncate pr-1" title="${s.symbol}">
                            ${s.symbol} <span class="text-[8px] text-gray-400">(${s.weight.toFixed(1)}%)</span>
                        </span>
                        <span class="text-rose-400 font-bold whitespace-nowrap">${s.pts.toFixed(1)}</span>
                    </div>
                `).join('');
            }
        }
    }

    function syncOptionsBreadthUI(index, timeStr) {
        if (!breadthData) return;

        const candle = (breadthData.nifty_candles || []).find(c => c.time_str === timeStr) || breadthData.nifty_candles?.[index] || null;
        if (candle) {
            document.getElementById('o-chart-time').textContent = candle.time_str;
            document.getElementById('o-chart-open').textContent = candle.open.toFixed(2);
            document.getElementById('o-chart-close').textContent = candle.close.toFixed(2);
            const moveEl = document.getElementById('o-chart-move');
            const sign = candle.move_pts >= 0 ? '+' : '';
            moveEl.textContent = `${sign}${candle.move_pts.toFixed(2)} (${sign}${candle.move_pct.toFixed(2)}%)`;
            moveEl.className = candle.move_pts >= 0 ? 'font-bold text-emerald-400' : 'font-bold text-rose-400';
        }

        const b = breadthData.breadth_by_time?.[timeStr];
        if (b) {
            document.getElementById('o-stat-time').textContent = `${timeStr} - 5-min candle`;
            const impEl = document.getElementById('o-stat-impulse');
            const sign = b.net_points >= 0 ? '+' : '';
            impEl.textContent = `${sign}${Number(b.net_points).toFixed(2)} pts`;
            impEl.className = b.net_points >= 0 ? 'text-xs font-black font-mono text-emerald-400' : 'text-xs font-black font-mono text-rose-400';

            document.getElementById('o-stat-green-ratio').textContent = `${b.up_weight}%`;
            document.getElementById('o-stat-green-wt').textContent = `${b.up_weight}% Wt`;
            document.getElementById('o-stat-green-cnt').textContent = b.up_count;

            document.getElementById('o-stat-red-ratio').textContent = `${b.down_weight}%`;
            document.getElementById('o-stat-red-wt').textContent = `${b.down_weight}% Wt`;
            document.getElementById('o-stat-red-cnt').textContent = b.down_count;

            document.getElementById('o-stat-split').textContent = `${b.up_weight}% / ${b.down_weight}%`;
            document.getElementById('o-bar-green').style.width = `${b.up_weight}%`;
            document.getElementById('o-bar-red').style.width = `${b.down_weight}%`;

            document.getElementById('o-verdict-desc').textContent = b.verdict || '';

            if (b.greeks) {
                document.getElementById('o-greek-delta').textContent = b.greeks.net_delta;
                document.getElementById('o-greek-ce-vega').textContent = Number(b.greeks.avg_ce_vega).toFixed(2);
                document.getElementById('o-greek-pe-vega').textContent = Number(b.greeks.avg_pe_vega).toFixed(2);
                document.getElementById('o-greek-pcr').textContent = Number(b.greeks.pcr).toFixed(2);
            }
        }
    }

    function syncTrendingOiCursor(index, timeStr) {
        if (!trendingOiData) return;
        if (activeBuildupTf === '5m') {
            const badge = document.getElementById('bld-window-badge');
            if (badge) badge.textContent = `${timeStr} (5M)`;
        }
    }

    // ── 10b. Trigger Strength Analysis (Stocks, Options & Combined) ─────────────
    function updateOverallAndTriggerStats(upToIndex = -1) {
        if (!timestampsList || timestampsList.length === 0) return;

        const maxIdx = (upToIndex >= 0 && upToIndex < timestampsList.length)
            ? upToIndex
            : (timestampsList.length - 1);
        const activeTimes = timestampsList.slice(0, maxIdx + 1);
        const currentTimeStr = timestampsList[maxIdx] || '';

        // Active thresholds & criteria
        const stocksUpTh = alarmSettings.stocksUpThreshold || 80;
        const stocksDownTh = alarmSettings.stocksDownThreshold || 80;
        const stocksTrig = alarmSettings.stocksTriggerBy || 'both';
        const optsUpTh = alarmSettings.optionsUpThreshold || 70;
        const optsDownTh = alarmSettings.optionsDownThreshold || 70;

        // Update threshold badges
        const stkValsBadge = document.getElementById('stk-trigger-val-badge');
        if (stkValsBadge) {
            const trigModeShort = stocksTrig === 'both' ? 'Both' : (stocksTrig === 'either' ? 'Either' : (stocksTrig === 'count' ? 'Count' : 'Wt'));
            stkValsBadge.textContent = `(≥${stocksUpTh}% • ${trigModeShort})`;
        }
        const optValsBadge = document.getElementById('opt-trigger-val-badge');
        if (optValsBadge) optValsBadge.textContent = `(≥${optsUpTh}%)`;

        // ════════════════ 1. NIFTY 50 STOCKS TRIGGER ANALYSIS ════════════════
        let stkPosHits = 0, stkNegHits = 0;
        let stkPosMax = 0, stkPosMaxTime = '';
        let stkNegMax = 0, stkNegMaxTime = '';
        let sumGreenWt = 0, sumRedWt = 0, wCount = 0;

        activeTimes.forEach(t => {
            const wb = weightageData?.breadth_by_time?.[t];
            if (wb) {
                const gw = parseFloat(wb.green_weight) || 0;
                const rw = parseFloat(wb.red_weight) || 0;
                const gcp = parseFloat(wb.green_count_pct) || 0;
                const rcp = parseFloat(wb.red_count_pct) || 0;

                sumGreenWt += gw;
                sumRedWt += rw;
                wCount++;

                let isGreenBreach = false;
                let isRedBreach = false;

                if (stocksTrig === 'both') {
                    isGreenBreach = (gcp >= stocksUpTh && gw >= stocksUpTh);
                    isRedBreach   = (rcp >= stocksDownTh && rw >= stocksDownTh);
                } else if (stocksTrig === 'either') {
                    isGreenBreach = (gcp >= stocksUpTh || gw >= stocksUpTh);
                    isRedBreach   = (rcp >= stocksDownTh || rw >= stocksDownTh);
                } else if (stocksTrig === 'count') {
                    isGreenBreach = (gcp >= stocksUpTh);
                    isRedBreach   = (rcp >= stocksDownTh);
                } else if (stocksTrig === 'weight') {
                    isGreenBreach = (gw >= stocksUpTh);
                    isRedBreach   = (rw >= stocksDownTh);
                }

                if (isGreenBreach) {
                    stkPosHits++;
                    const peakVal = Math.max(gcp, gw);
                    if (peakVal > stkPosMax) { stkPosMax = peakVal; stkPosMaxTime = t; }
                }
                if (isRedBreach) {
                    stkNegHits++;
                    const peakVal = Math.max(rcp, rw);
                    if (peakVal > stkNegMax) { stkNegMax = peakVal; stkNegMaxTime = t; }
                }
            }
        });

        const avgGreenWt = wCount > 0 ? (sumGreenWt / wCount) : 50;
        const avgRedWt = wCount > 0 ? (sumRedWt / wCount) : 50;

        // Populate Stocks UI
        const stkPosCntEl = document.getElementById('stk-pos-triggers-count');
        if (stkPosCntEl) stkPosCntEl.textContent = stkPosHits;
        const stkNegCntEl = document.getElementById('stk-neg-triggers-count');
        if (stkNegCntEl) stkNegCntEl.textContent = stkNegHits;

        const stkPosPkEl = document.getElementById('stk-pos-peak');
        if (stkPosPkEl) stkPosPkEl.textContent = stkPosMax > 0 ? `${stkPosMax.toFixed(0)}%` : '0%';
        const stkPosPkTimeEl = document.getElementById('stk-pos-peak-time');
        if (stkPosPkTimeEl) stkPosPkTimeEl.textContent = stkPosMaxTime ? `@${stkPosMaxTime}` : '';

        const stkNegPkEl = document.getElementById('stk-neg-peak');
        if (stkNegPkEl) stkNegPkEl.textContent = stkNegMax > 0 ? `${stkNegMax.toFixed(0)}%` : '0%';
        const stkNegPkTimeEl = document.getElementById('stk-neg-peak-time');
        if (stkNegPkTimeEl) stkNegPkTimeEl.textContent = stkNegMaxTime ? `@${stkNegMaxTime}` : '';

        const stkSessionBull = document.getElementById('stk-session-bull');
        if (stkSessionBull) stkSessionBull.textContent = `${Math.round(avgGreenWt)}%`;
        const stkSessionBear = document.getElementById('stk-session-bear');
        if (stkSessionBear) stkSessionBear.textContent = `${Math.round(avgRedWt)}%`;

        const stkVerdictBadge = document.getElementById('stk-trigger-verdict-badge');
        const stkLeadText = document.getElementById('stk-trigger-lead-text');

        if (stkPosHits === 0 && stkNegHits === 0) {
            if (stkVerdictBadge) {
                stkVerdictBadge.textContent = 'Nothing happened';
                stkVerdictBadge.className = 'px-1.5 py-0.2 rounded text-[8.5px] font-mono font-bold bg-gray-800 text-gray-400 border border-gray-700';
            }
            if (stkLeadText) {
                stkLeadText.textContent = 'Nothing happened (No triggers reached)';
                stkLeadText.className = 'text-gray-400 font-medium truncate';
            }
        } else if (stkPosHits > stkNegHits) {
            const margin = stkPosHits - stkNegHits;
            if (stkVerdictBadge) {
                stkVerdictBadge.textContent = `🐂 BULLS STRONGER (+${margin})`;
                stkVerdictBadge.className = 'px-1.5 py-0.2 rounded text-[8.5px] font-mono font-bold bg-emerald-950 text-emerald-300 border border-emerald-600/50 shadow-xs';
            }
            if (stkLeadText) {
                stkLeadText.textContent = `Bulls lead by +${margin} trigger (${stkPosHits} Pos vs ${stkNegHits} Neg)`;
                stkLeadText.className = 'text-emerald-300 font-bold truncate';
            }
        } else if (stkNegHits > stkPosHits) {
            const margin = stkNegHits - stkPosHits;
            if (stkVerdictBadge) {
                stkVerdictBadge.textContent = `🐻 BEARS STRONGER (+${margin})`;
                stkVerdictBadge.className = 'px-1.5 py-0.2 rounded text-[8.5px] font-mono font-bold bg-rose-950 text-rose-300 border border-rose-600/50 shadow-xs';
            }
            if (stkLeadText) {
                stkLeadText.textContent = `Bears lead by +${margin} trigger (${stkNegHits} Neg vs ${stkPosHits} Pos)`;
                stkLeadText.className = 'text-rose-300 font-bold truncate';
            }
        } else {
            if (stkVerdictBadge) {
                stkVerdictBadge.textContent = `⚖️ TIED (${stkPosHits} Each)`;
                stkVerdictBadge.className = 'px-1.5 py-0.2 rounded text-[8.5px] font-mono font-bold bg-amber-950 text-amber-300 border border-amber-600/50 shadow-xs';
            }
            if (stkLeadText) {
                stkLeadText.textContent = `Tied: ${stkPosHits} positive vs ${stkNegHits} negative`;
                stkLeadText.className = 'text-amber-300 font-bold truncate';
            }
        }

        // ════════════════ 2. NIFTY OPTIONS TRIGGER ANALYSIS ════════════════
        let optPosHits = 0, optNegHits = 0;
        let optPosMax = 0, optPosMaxTime = '';
        let optNegMax = 0, optNegMaxTime = '';
        let sumOBull = 0, sumOBear = 0, oCount = 0;

        activeTimes.forEach(t => {
            const ob = breadthData?.breadth_by_time?.[t];
            if (ob) {
                const uw = parseFloat(ob.up_weight) || 0;
                const dw = parseFloat(ob.down_weight) || 0;

                sumOBull += uw;
                sumOBear += dw;
                oCount++;

                if (uw >= optsUpTh) {
                    optPosHits++;
                    if (uw > optPosMax) { optPosMax = uw; optPosMaxTime = t; }
                }
                if (dw >= optsDownTh) {
                    optNegHits++;
                    if (dw > optNegMax) { optNegMax = dw; optNegMaxTime = t; }
                }
            }
        });

        const avgOBull = oCount > 0 ? (sumOBull / oCount) : 50;
        const avgOBear = oCount > 0 ? (sumOBear / oCount) : 50;

        // Populate Options UI
        const optPosCntEl = document.getElementById('opt-pos-triggers-count');
        if (optPosCntEl) optPosCntEl.textContent = optPosHits;
        const optNegCntEl = document.getElementById('opt-neg-triggers-count');
        if (optNegCntEl) optNegCntEl.textContent = optNegHits;

        const optPosPkEl = document.getElementById('opt-pos-peak');
        if (optPosPkEl) optPosPkEl.textContent = optPosMax > 0 ? `${optPosMax.toFixed(0)}%` : '0%';
        const optPosPkTimeEl = document.getElementById('opt-pos-peak-time');
        if (optPosPkTimeEl) optPosPkTimeEl.textContent = optPosMaxTime ? `@${optPosMaxTime}` : '';

        const optNegPkEl = document.getElementById('opt-neg-peak');
        if (optNegPkEl) optNegPkEl.textContent = optNegMax > 0 ? `${optNegMax.toFixed(0)}%` : '0%';
        const optNegPkTimeEl = document.getElementById('opt-neg-peak-time');
        if (optNegPkTimeEl) optNegPkTimeEl.textContent = optNegMaxTime ? `@${optNegMaxTime}` : '';

        const optSessionBull = document.getElementById('opt-session-bull');
        if (optSessionBull) optSessionBull.textContent = `${Math.round(avgOBull)}%`;
        const optSessionBear = document.getElementById('opt-session-bear');
        if (optSessionBear) optSessionBear.textContent = `${Math.round(avgOBear)}%`;

        const optVerdictBadge = document.getElementById('opt-trigger-verdict-badge');
        const optLeadText = document.getElementById('opt-trigger-lead-text');

        if (optPosHits === 0 && optNegHits === 0) {
            if (optVerdictBadge) {
                optVerdictBadge.textContent = 'Nothing happened';
                optVerdictBadge.className = 'px-1.5 py-0.2 rounded text-[8.5px] font-mono font-bold bg-gray-800 text-gray-400 border border-gray-700';
            }
            if (optLeadText) {
                optLeadText.textContent = 'Nothing happened (No triggers reached)';
                optLeadText.className = 'text-gray-400 font-medium truncate';
            }
        } else if (optPosHits > optNegHits) {
            const margin = optPosHits - optNegHits;
            if (optVerdictBadge) {
                optVerdictBadge.textContent = `🐂 BULLS STRONGER (+${margin})`;
                optVerdictBadge.className = 'px-1.5 py-0.2 rounded text-[8.5px] font-mono font-bold bg-emerald-950 text-emerald-300 border border-emerald-600/50 shadow-xs';
            }
            if (optLeadText) {
                optLeadText.textContent = `Bulls lead by +${margin} trigger (${optPosHits} Pos vs ${optNegHits} Neg)`;
                optLeadText.className = 'text-emerald-300 font-bold truncate';
            }
        } else if (optNegHits > optPosHits) {
            const margin = optNegHits - optPosHits;
            if (optVerdictBadge) {
                optVerdictBadge.textContent = `🐻 BEARS STRONGER (+${margin})`;
                optVerdictBadge.className = 'px-1.5 py-0.2 rounded text-[8.5px] font-mono font-bold bg-rose-950 text-rose-300 border border-rose-600/50 shadow-xs';
            }
            if (optLeadText) {
                optLeadText.textContent = `Bears lead by +${margin} trigger (${optNegHits} Neg vs ${optPosHits} Pos)`;
                optLeadText.className = 'text-rose-300 font-bold truncate';
            }
        } else {
            if (optVerdictBadge) {
                optVerdictBadge.textContent = `⚖️ TIED (${optPosHits} Each)`;
                optVerdictBadge.className = 'px-1.5 py-0.2 rounded text-[8.5px] font-mono font-bold bg-amber-950 text-amber-300 border border-amber-600/50 shadow-xs';
            }
            if (optLeadText) {
                optLeadText.textContent = `Tied: ${optPosHits} positive vs ${optNegHits} negative`;
                optLeadText.className = 'text-amber-300 font-bold truncate';
            }
        }

        // ════════════════ 3. COMBINED CONFLUENCE ANALYSIS ════════════════
        const combPosHits = stkPosHits + optPosHits;
        const combNegHits = stkNegHits + optNegHits;
        const combBadge = document.getElementById('comb-trigger-badge');

        if (combBadge) {
            if (combPosHits === 0 && combNegHits === 0) {
                combBadge.textContent = 'Nothing happened';
                combBadge.className = 'font-bold text-gray-400';
            } else if (combPosHits > combNegHits) {
                const margin = combPosHits - combNegHits;
                combBadge.textContent = `🐂 BULLS STRONGER (+${margin}) • ${combPosHits} Pos vs ${combNegHits} Neg`;
                combBadge.className = 'font-bold text-emerald-400';
            } else if (combNegHits > combPosHits) {
                const margin = combNegHits - combPosHits;
                combBadge.textContent = `🐻 BEARS STRONGER (+${margin}) • ${combNegHits} Neg vs ${combPosHits} Pos`;
                combBadge.className = 'font-bold text-rose-400';
            } else {
                combBadge.textContent = `⚖️ TIED (${combPosHits} Pos & ${combNegHits} Neg)`;
                combBadge.className = 'font-bold text-amber-300';
            }
        }
    }

    // ── 11. Event Handlers & Settings Sync ────────────────────────────────────────
    function setupControls() {
        // Height Dropdown
        const heightSelect = document.getElementById('chart-height-select');
        if (heightSelect) {
            heightSelect.value = String(currentChartHeight);
            heightSelect.addEventListener('change', (e) => {
                applyChartHeight(parseInt(e.target.value, 10));
            });
        }

        // Date Flatpickr
        const dateInput = document.getElementById('date-select');
        if (dateInput && window.flatpickr) {
            flatpickr(dateInput, {
                dateFormat: 'Y-m-d',
                defaultDate: selectedDate,
                onChange: function(selectedDates, dateStr) {
                    if (dateStr && dateStr !== selectedDate) {
                        selectedDate = dateStr;
                        updateExpiriesForDate(dateStr);
                    }
                }
            });
        }

        async function updateExpiriesForDate(dStr) {
            try {
                const expRes = await fetch(`/api/trending-oi/expiries?date=${dStr}&mode=history&underlying=NSE_INDEX|Nifty 50`).then(r => r.json());
                if (expRes.expiries && expRes.expiries.length > 0) {
                    const expSelect = document.getElementById('expiry-select');
                    if (expSelect) {
                        expSelect.innerHTML = expRes.expiries.map(exp => `<option value="${exp}" class="bg-[#131722] text-white">${exp}</option>`).join('');
                        selectedExpiry = expRes.expiries[0];
                        expSelect.value = selectedExpiry;
                    }
                }
            } catch (e) {
                console.warn('Could not auto-fetch expiries for date:', e);
            }
            loadAllData();
        }

        document.getElementById('btn-prev-day')?.addEventListener('click', () => {
            const curDate = new Date(selectedDate);
            curDate.setDate(curDate.getDate() - 1);
            if (curDate.getDay() === 0) curDate.setDate(curDate.getDate() - 2);
            if (curDate.getDay() === 6) curDate.setDate(curDate.getDate() - 1);
            selectedDate = curDate.toISOString().split('T')[0];
            if (dateInput) dateInput.value = selectedDate;
            updateExpiriesForDate(selectedDate);
        });

        document.getElementById('btn-next-day')?.addEventListener('click', () => {
            const curDate = new Date(selectedDate);
            curDate.setDate(curDate.getDate() + 1);
            if (curDate.getDay() === 6) curDate.setDate(curDate.getDate() + 2);
            if (curDate.getDay() === 0) curDate.setDate(curDate.getDate() + 1);
            selectedDate = curDate.toISOString().split('T')[0];
            if (dateInput) dateInput.value = selectedDate;
            updateExpiriesForDate(selectedDate);
        });

        document.getElementById('expiry-select')?.addEventListener('change', (e) => {
            selectedExpiry = e.target.value;
            loadAllData();
        });

        // Sliders
        document.getElementById('master-slider')?.addEventListener('input', (e) => {
            syncActiveCandleIndex(parseInt(e.target.value, 10));
        });
        document.getElementById('w-slider')?.addEventListener('input', (e) => {
            syncActiveCandleIndex(parseInt(e.target.value, 10));
        });
        document.getElementById('o-slider')?.addEventListener('input', (e) => {
            syncActiveCandleIndex(parseInt(e.target.value, 10));
        });

        document.getElementById('btn-step-start')?.addEventListener('click', () => syncActiveCandleIndex(0));
        document.getElementById('btn-snap-live')?.addEventListener('click', () => {
            if (timestampsList && timestampsList.length > 0) {
                syncActiveCandleIndex(timestampsList.length - 1);
            }
        });

        document.getElementById('btn-step-prev')?.addEventListener('click', () => {
            if (activeCandleIndex > 0) syncActiveCandleIndex(activeCandleIndex - 1);
        });
        document.getElementById('btn-step-next')?.addEventListener('click', () => {
            if (activeCandleIndex < timestampsList.length - 1) syncActiveCandleIndex(activeCandleIndex + 1);
        });

        // Play / Pause Replay
        const playBtn = document.getElementById('btn-play-pause');
        if (playBtn) {
            playBtn.addEventListener('click', () => {
                isPlaying = !isPlaying;
                const icon = document.getElementById('play-icon');
                const text = document.getElementById('play-text');
                if (isPlaying) {
                    if (icon) icon.textContent = '⏸';
                    if (text) text.textContent = 'Pause';
                    if (activeCandleIndex >= timestampsList.length - 1) syncActiveCandleIndex(0);

                    playIntervalId = setInterval(() => {
                        if (activeCandleIndex < timestampsList.length - 1) {
                            syncActiveCandleIndex(activeCandleIndex + 1);
                        } else {
                            clearInterval(playIntervalId);
                            isPlaying = false;
                            if (icon) icon.textContent = '▶';
                            if (text) text.textContent = 'Play';
                        }
                    }, 1200);
                } else {
                    if (icon) icon.textContent = '▶';
                    if (text) text.textContent = 'Play';
                    if (playIntervalId) clearInterval(playIntervalId);
                }
            });
        }

        // Keyboard Shortcuts
        window.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                if (activeCandleIndex > 0) syncActiveCandleIndex(activeCandleIndex - 1);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                if (activeCandleIndex < timestampsList.length - 1) syncActiveCandleIndex(activeCandleIndex + 1);
            } else if (e.key === ' ' || e.code === 'Space') {
                e.preventDefault();
                playBtn?.click();
            } else if (e.key === 'Escape') {
                dismissAlarm();
            }
        });

        // Auto Refresh
        document.getElementById('auto-refresh-select')?.addEventListener('change', (e) => {
            activeRefreshSeconds = parseInt(e.target.value, 10);
            autoRefreshCountdown = activeRefreshSeconds;
            setupAutoRefresh();
        });

        document.getElementById('btn-reload')?.addEventListener('click', () => loadAllData(false));

        // Zen Mode
        document.getElementById('btn-zen')?.addEventListener('click', () => {
            document.body.classList.toggle('zen-mode');
            setTimeout(() => {
                applyChartHeight(currentChartHeight);
            }, 100);
        });

        // Buildup Timeframe Switcher Tabs
        document.querySelectorAll('.bld-tf-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.bld-tf-btn').forEach(b => {
                    b.classList.remove('bg-blue-600', 'text-white');
                    b.classList.add('text-gray-400');
                });
                btn.classList.add('bg-blue-600', 'text-white');
                btn.classList.remove('text-gray-400');
                activeBuildupTf = btn.dataset.tf;
                renderStrikeBuildupChart();
            });
        });



        // ── Alarm Modal & 0Click Settings Handlers ─────────────────────────────────
        const alarmModal = document.getElementById('alarm-settings-modal');
        document.getElementById('btn-open-alarm-settings')?.addEventListener('click', () => {
            syncModalFieldsWithSettings();
            alarmModal?.classList.remove('hidden');
        });

        document.getElementById('btn-close-alarm-modal')?.addEventListener('click', () => {
            alarmModal?.classList.add('hidden');
        });

        // Quick Alarm Toggle in toolbar
        document.getElementById('btn-quick-alarm')?.addEventListener('click', () => {
            alarmSettings.masterEnabled = !alarmSettings.masterEnabled;
            saveAlarmSettings();
            syncAlarmToolbarUI();
        });

        // Stop Active Alarm (Audio Loop & Modal Dismiss)
        document.getElementById('btn-stop-active-alarm')?.addEventListener('click', stopAlarm);

        // Manual Dispatch Button in Active Alarm Modal
        document.getElementById('btn-dispatch-active-alarm')?.addEventListener('click', () => {
            const btn = document.getElementById('btn-dispatch-active-alarm');
            if (currentActiveLogId) {
                dispatchLogToZeroClick(currentActiveLogId, btn);
            } else {
                // If not logged yet, log & dispatch immediately
                const currentCandle = weightageData?.candles?.[activeCandleIndex] || weightageData?.candles?.slice(-1)[0];
                const spot = currentCandle ? currentCandle.close : 25850.50;
                const dir = document.getElementById('alarm-trade-strike-badge')?.textContent.includes('PE') ? 'UP' : 'DOWN';
                const contract = calculateOptionSellContract(dir, spot);
                logAlertInternally({
                    direction: dir,
                    time: currentCandle ? currentCandle.time_str : '12:00',
                    score: 80,
                    weight: 80,
                    count: 40,
                    pts: 0
                }, spot, contract.payload, true, () => {
                    alert('Dispatched to 0click.in successfully!');
                }, contract);
            }
        });

        // Payload Inspector in Active Card
        document.getElementById('btn-toggle-payload-preview')?.addEventListener('click', () => {
            document.getElementById('alarm-trade-payload-box')?.classList.toggle('hidden');
        });

        // Toggle Token Visibility
        document.getElementById('btn-toggle-zc-token')?.addEventListener('click', () => {
            const tokenInput = document.getElementById('zc-webhook-token');
            if (tokenInput) {
                tokenInput.type = tokenInput.type === 'password' ? 'text' : 'password';
            }
        });

        // Modal Tab Switching
        document.querySelectorAll('.alarm-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.getAttribute('data-tab');
                document.querySelectorAll('.alarm-tab-btn').forEach(b => {
                    b.className = 'alarm-tab-btn flex-1 py-1.5 px-2 rounded-md transition text-center cursor-pointer text-gray-400 hover:text-white';
                });
                btn.className = 'alarm-tab-btn flex-1 py-1.5 px-2 rounded-md transition text-center cursor-pointer bg-blue-600 text-white font-bold';

                document.querySelectorAll('.alarm-pane').forEach(p => p.classList.add('hidden'));
                document.getElementById(`alarm-pane-${tab}`)?.classList.remove('hidden');
            });
        });

        // Two-way synchronization for threshold sliders and number inputs
        ['stocks-up', 'stocks-down', 'options-up', 'options-down'].forEach(prefix => {
            const input = document.getElementById(`${prefix}-input`);
            const slider = document.getElementById(`${prefix}-slider`);
            if (input && slider) {
                slider.addEventListener('input', () => { input.value = slider.value; });
                input.addEventListener('change', () => {
                    let v = parseInt(input.value, 10);
                    if (isNaN(v) || v < 50) v = 50;
                    if (v > 95) v = 95;
                    input.value = v;
                    slider.value = v;
                });
            }
        });

        // Master Sound volume slider
        const volSlider = document.getElementById('alarm-volume-slider');
        const volLabel = document.getElementById('alarm-vol-label');
        volSlider?.addEventListener('input', (e) => {
            if (volLabel) volLabel.textContent = `${e.target.value}%`;
        });

        // Independent Sound Preview Buttons
        document.getElementById('btn-test-stocks-sound')?.addEventListener('click', () => {
            const sound = document.getElementById('stocks-sound-select')?.value || 'bull_horn';
            const vol = (parseInt(volSlider?.value || '100', 10)) / 100.0;
            playSoundByName(sound, vol);
        });

        document.getElementById('btn-test-options-sound')?.addEventListener('click', () => {
            const sound = document.getElementById('options-sound-select')?.value || 'pulse';
            const vol = (parseInt(volSlider?.value || '100', 10)) / 100.0;
            playSoundByName(sound, vol);
        });

        document.getElementById('btn-test-confluence-sound')?.addEventListener('click', () => {
            const sound = document.getElementById('confluence-sound-select')?.value || 'siren';
            const vol = (parseInt(volSlider?.value || '100', 10)) / 100.0;
            playSoundByName(sound, vol);
        });

        // 0Click Logs Modal Open / Close
        const logsModal = document.getElementById('zeroclick-logs-modal');
        document.getElementById('btn-open-logs')?.addEventListener('click', () => {
            fetchAlertLogs(true);
            logsModal?.classList.remove('hidden');
        });
        document.getElementById('btn-close-logs-modal')?.addEventListener('click', () => {
            logsModal?.classList.add('hidden');
        });

        document.getElementById('btn-create-test-log')?.addEventListener('click', () => {
            const currentCandle = weightageData?.candles?.[activeCandleIndex] || weightageData?.candles?.slice(-1)[0];
            const spot = currentCandle ? currentCandle.close : 25850.50;
            const testDir = Math.random() > 0.5 ? 'UP' : 'DOWN';
            const testData = {
                direction: testDir,
                score: 86,
                threshold: 80,
                time: currentCandle ? currentCandle.time_str : '11:45',
                count: 43,
                weight: 88.4,
                pts: testDir === 'UP' ? 142.5 : -142.5
            };
            const contract = calculateOptionSellContract(testDir, spot);
            logAlertInternally(testData, spot, contract.payload, false, () => fetchAlertLogs(true), contract);
        });

        document.getElementById('btn-refresh-logs')?.addEventListener('click', () => fetchAlertLogs(true));

        document.getElementById('btn-clear-all-logs')?.addEventListener('click', () => {
            if (!confirm('Are you sure you want to clear all internal alert logs?')) return;
            fetch('/api/nifty-weightage-monitor/alert-logs', {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            })
            .then(res => res.json())
            .then(() => {
                cachedLogsList = [];
                loggedSignalsSet.clear();
                fetchAlertLogs(true);
            })
            .catch(err => alert('Error clearing logs: ' + err.message));
        });

        document.getElementById('log-filter-action')?.addEventListener('change', renderLogsTable);
        document.getElementById('log-filter-status')?.addEventListener('change', renderLogsTable);

        // Save Settings on Save & Apply Click
        document.getElementById('btn-save-alarm-settings')?.addEventListener('click', () => {
            alarmSettings.masterEnabled = document.getElementById('alarm-active-toggle').checked;
            alarmSettings.volume = (parseInt(document.getElementById('alarm-volume-slider').value, 10)) / 100.0;

            // 1. Stocks settings
            alarmSettings.stocksAlertEnabled = document.getElementById('stocks-alert-enabled').checked;
            alarmSettings.stocksUpThreshold = parseInt(document.getElementById('stocks-up-input').value, 10) || 80;
            alarmSettings.stocksDownThreshold = parseInt(document.getElementById('stocks-down-input').value, 10) || 80;
            const selStocksTrig = document.querySelector('input[name="stocks-trigger-by"]:checked');
            if (selStocksTrig) alarmSettings.stocksTriggerBy = selStocksTrig.value;
            alarmSettings.stocksShowMarkers = document.getElementById('stocks-show-markers-toggle').checked;
            alarmSettings.stocksSound = document.getElementById('stocks-sound-select').value;

            // 2. Options settings
            alarmSettings.optionsAlertEnabled = document.getElementById('options-alert-enabled').checked;
            alarmSettings.optionsUpThreshold = parseInt(document.getElementById('options-up-input').value, 10) || 70;
            alarmSettings.optionsDownThreshold = parseInt(document.getElementById('options-down-input').value, 10) || 70;
            alarmSettings.optionsShowMarkers = document.getElementById('options-show-markers-toggle').checked;
            alarmSettings.optionsSound = document.getElementById('options-sound-select').value;

            // 3. Confluence settings
            alarmSettings.confluenceEnabled = document.getElementById('cfg-confluence-enabled').checked;
            alarmSettings.confluenceSound = document.getElementById('confluence-sound-select').value;

            // Save 0Click settings
            zeroClickSettings.autoLog = document.getElementById('zc-auto-log-toggle').checked;
            zeroClickSettings.forwardToZeroClick = document.getElementById('zc-forward-toggle').checked;
            zeroClickSettings.webhookUrl = document.getElementById('zc-webhook-url').value.trim() || 'https://0click.in/api/webhook/tradingview';
            zeroClickSettings.webhookToken = document.getElementById('zc-webhook-token').value.trim();
            zeroClickSettings.mode = document.getElementById('zc-mode-select').value;
            zeroClickSettings.lots = parseInt(document.getElementById('zc-lots-input').value, 10) || 1;
            zeroClickSettings.slOffset = parseInt(document.getElementById('zc-sl-pts-input').value, 10) || 100;
            zeroClickSettings.tpOffset = parseInt(document.getElementById('zc-tp-pts-input').value, 10) || 200;

            saveAlarmSettings();
            syncAlarmToolbarUI();
            alarmModal?.classList.add('hidden');

            // Immediately re-render markers on both charts with newly saved criteria!
            renderWeightageColumn();
            renderOptionsBreadthColumn();
        });
    }

    function syncModalFieldsWithSettings() {
        const activeToggle = document.getElementById('alarm-active-toggle');
        if (activeToggle) activeToggle.checked = alarmSettings.masterEnabled;
        const statusBadge = document.getElementById('alarm-status-badge');
        if (statusBadge) {
            statusBadge.textContent = alarmSettings.masterEnabled ? 'ENABLED' : 'MUTED';
            statusBadge.className = alarmSettings.masterEnabled
                ? 'px-1.5 py-0.2 rounded text-[10px] font-mono font-bold bg-emerald-950 text-emerald-400 border border-emerald-600/40'
                : 'px-1.5 py-0.2 rounded text-[10px] font-mono font-bold bg-gray-800 text-gray-400 border border-gray-600/40';
        }

        // 1. Stocks settings
        const stocksToggle = document.getElementById('stocks-alert-enabled');
        if (stocksToggle) stocksToggle.checked = alarmSettings.stocksAlertEnabled !== false;

        const sUpIn = document.getElementById('stocks-up-input');
        const sUpSl = document.getElementById('stocks-up-slider');
        if (sUpIn) sUpIn.value = alarmSettings.stocksUpThreshold || 80;
        if (sUpSl) sUpSl.value = alarmSettings.stocksUpThreshold || 80;

        const sDownIn = document.getElementById('stocks-down-input');
        const sDownSl = document.getElementById('stocks-down-slider');
        if (sDownIn) sDownIn.value = alarmSettings.stocksDownThreshold || 80;
        if (sDownSl) sDownSl.value = alarmSettings.stocksDownThreshold || 80;

        const sRad = document.querySelector(`input[name="stocks-trigger-by"][value="${alarmSettings.stocksTriggerBy || 'both'}"]`);
        if (sRad) sRad.checked = true;

        const sMarkers = document.getElementById('stocks-show-markers-toggle');
        if (sMarkers) sMarkers.checked = alarmSettings.stocksShowMarkers !== false;

        const sSnd = document.getElementById('stocks-sound-select');
        if (sSnd) sSnd.value = alarmSettings.stocksSound || 'bull_horn';

        // 2. Options settings
        const optToggle = document.getElementById('options-alert-enabled');
        if (optToggle) optToggle.checked = alarmSettings.optionsAlertEnabled !== false;

        const oUpIn = document.getElementById('options-up-input');
        const oUpSl = document.getElementById('options-up-slider');
        if (oUpIn) oUpIn.value = alarmSettings.optionsUpThreshold || 70;
        if (oUpSl) oUpSl.value = alarmSettings.optionsUpThreshold || 70;

        const oDownIn = document.getElementById('options-down-input');
        const oDownSl = document.getElementById('options-down-slider');
        if (oDownIn) oDownIn.value = alarmSettings.optionsDownThreshold || 70;
        if (oDownSl) oDownSl.value = alarmSettings.optionsDownThreshold || 70;

        const oMarkers = document.getElementById('options-show-markers-toggle');
        if (oMarkers) oMarkers.checked = alarmSettings.optionsShowMarkers !== false;

        const oSnd = document.getElementById('options-sound-select');
        if (oSnd) oSnd.value = alarmSettings.optionsSound || 'pulse';

        // 3. Confluence settings
        const confToggle = document.getElementById('cfg-confluence-enabled');
        if (confToggle) confToggle.checked = alarmSettings.confluenceEnabled !== false;

        const confSnd = document.getElementById('confluence-sound-select');
        if (confSnd) confSnd.value = alarmSettings.confluenceSound || 'siren';

        const volSlider = document.getElementById('alarm-volume-slider');
        const volLabel = document.getElementById('alarm-vol-label');
        const volPct = Math.round((alarmSettings.volume !== undefined ? alarmSettings.volume : 1.0) * 100);
        if (volSlider) volSlider.value = volPct;
        if (volLabel) volLabel.textContent = `${volPct}%`;

        // 0Click settings
        const zcAutoLog = document.getElementById('zc-auto-log-toggle');
        if (zcAutoLog) zcAutoLog.checked = zeroClickSettings.autoLog;

        const zcForward = document.getElementById('zc-forward-toggle');
        if (zcForward) zcForward.checked = zeroClickSettings.forwardToZeroClick;

        const zcModeBadge = document.getElementById('zc-mode-badge');
        if (zcModeBadge) {
            zcModeBadge.textContent = zeroClickSettings.mode;
            zcModeBadge.className = zeroClickSettings.mode === 'LIVE'
                ? 'px-1.5 py-0.2 rounded text-[9px] font-mono bg-rose-500/20 text-rose-300 border border-rose-500/40'
                : 'px-1.5 py-0.2 rounded text-[9px] font-mono bg-purple-500/20 text-purple-300 border border-purple-500/40';
        }

        const zcUrl = document.getElementById('zc-webhook-url');
        if (zcUrl) zcUrl.value = zeroClickSettings.webhookUrl;

        const zcTok = document.getElementById('zc-webhook-token');
        if (zcTok) zcTok.value = zeroClickSettings.webhookToken;

        const zcMode = document.getElementById('zc-mode-select');
        if (zcMode) zcMode.value = zeroClickSettings.mode;

        const zcLots = document.getElementById('zc-lots-input');
        if (zcLots) zcLots.value = zeroClickSettings.lots;

        const zcSl = document.getElementById('zc-sl-pts-input');
        if (zcSl) zcSl.value = zeroClickSettings.slOffset;

        const zcTp = document.getElementById('zc-tp-pts-input');
        if (zcTp) zcTp.value = zeroClickSettings.tpOffset;
    }

    function saveAlarmSettings() {
        localStorage.setItem('nifty_unified_alarm_settings', JSON.stringify(alarmSettings));
        localStorage.setItem('nifty_zeroclick_settings', JSON.stringify(zeroClickSettings));
        updateOverallAndTriggerStats(activeCandleIndex);
    }

    function syncAlarmToolbarUI() {
        const label = document.getElementById('alarm-toolbar-label');
        const icon = document.getElementById('alarm-bell-icon');
        if (!label) return;

        if (alarmSettings.masterEnabled) {
            const trig = alarmSettings.stocksTriggerBy || 'both';
            const modeText = trig === 'both' ? 'Both (Ct&Wt)' : (trig === 'count' ? 'Count' : (trig === 'weight' ? 'Weight' : 'Either'));
            label.textContent = `Alarms ON (Stocks: ${alarmSettings.stocksUpThreshold || 80}% &bull; Options: ${alarmSettings.optionsUpThreshold || 70}%)`;
            label.className = 'text-amber-400 font-bold';
            if (icon) icon.textContent = '🔔';
        } else {
            label.textContent = 'Alarms MUTED';
            label.className = 'text-gray-500 font-semibold';
            if (icon) icon.textContent = '🔕';
        }
    }

    // ── 12. Auto-Refresh Engine ───────────────────────────────────────────────────
    function setupAutoRefresh() {
        if (autoRefreshTimerId) clearInterval(autoRefreshTimerId);
        autoRefreshCountdown = activeRefreshSeconds;
        updateCountdownUI();

        if (activeRefreshSeconds <= 0) return;

        autoRefreshTimerId = setInterval(() => {
            autoRefreshCountdown--;
            updateCountdownUI();

            if (autoRefreshCountdown <= 0) {
                autoRefreshCountdown = activeRefreshSeconds;
                loadAllData(true);
            }
        }, 1000);
    }

    function updateCountdownUI() {
        const badge = document.getElementById('countdown-badge');
        if (!badge) return;
        if (activeRefreshSeconds <= 0) {
            badge.textContent = 'OFF';
            return;
        }
        const m = Math.floor(autoRefreshCountdown / 60);
        const s = autoRefreshCountdown % 60;
        badge.textContent = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    }

    // ── App Bootstrapper ──────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        initLightweightCharts();
        setupControls();
        syncAlarmToolbarUI();
        fetchAlertLogs(false);
        loadAllData();
        setupAutoRefresh();
    });

})();
</script>
@endpush
