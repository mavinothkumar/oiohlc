@extends('layouts.app')

@section('title', 'NIFTY 50 Participation Engine | Directional Strength & Trade Decision Desk')

@push('styles')
    <!-- TradingView Lightweight Charts -->
    <script src="https://cdn.jsdelivr.net/npm/lightweight-charts@4.2.3/dist/lightweight-charts.standalone.production.js"></script>
    <!-- Flatpickr (Calendar Date Picker) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        body {
            background-color: #0b0e14 !important;
            color: #d1d5db;
        }
        header {
            padding: 0.35rem 1rem !important;
        }
        main {
            padding: 0.2rem !important;
        }
        main > div.mt-4 {
            margin-top: 0.2rem !important;
        }
        body.zen-mode header,
        body.zen-mode #page-updated-time {
            display: none !important;
        }
        body.zen-mode main {
            padding: 0 !important;
            margin: 0 !important;
            max-width: 100vw !important;
        }
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        ::-webkit-scrollbar-track {
            background: #0b0e14;
        }
        ::-webkit-scrollbar-thumb {
            background: #2a2e39;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #3b4252;
        }
        .cockpit-card {
            background-color: #131722;
            border: 1px solid #1f293d;
            border-radius: 0.5rem;
        }
        @keyframes pulse-live {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.9); }
        }
        .live-dot {
            animation: pulse-live 1.8s infinite;
        }
        .glow-bull {
            box-shadow: 0 0 16px rgba(16, 185, 129, 0.3);
            border-color: rgba(16, 185, 129, 0.7) !important;
        }
        .glow-bear {
            box-shadow: 0 0 16px rgba(244, 63, 94, 0.3);
            border-color: rgba(244, 63, 94, 0.7) !important;
        }
        .glow-amber {
            box-shadow: 0 0 16px rgba(245, 158, 11, 0.3);
            border-color: rgba(245, 158, 11, 0.7) !important;
        }
    </style>
@endpush

@section('content')
<div class="w-full px-1 sm:px-2 py-1 space-y-2 select-none" id="participation-app">

    <!-- ════════════ TOP UNIFIED COMMAND BAR ════════════ -->
    <div class="cockpit-card px-3 py-1.5 flex flex-wrap items-center justify-between gap-2 shadow-lg border-b border-gray-800">
        
        <!-- Left: Branding, Session Badge, Nifty Spot Quote -->
        <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
            <div class="flex items-center gap-2">
                <span class="p-1 rounded bg-indigo-500/20 text-indigo-400 font-black text-sm">⚡</span>
                <div>
                    <h1 class="text-xs sm:text-sm font-black text-white tracking-wide uppercase flex items-center gap-1.5 leading-none">
                        <span>NIFTY 50 PARTICIPATION ENGINE</span>
                        <span class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-indigo-950 text-indigo-300 border border-indigo-700/60">CONFLUENCE DESK</span>
                    </h1>
                    <span class="text-[10px] text-gray-400 font-medium">Directional Quality &bull; Historical Trace &bull; High-Confidence Signals</span>
                </div>
            </div>

            <!-- Spot Hero Badge -->
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded bg-gray-900 border border-gray-700 text-xs font-mono ml-1">
                <span class="text-gray-400 text-[10px] uppercase font-bold">NIFTY:</span>
                <span id="hero-spot-price" class="text-white font-black text-sm">--</span>
                <span id="hero-spot-change" class="text-[11px] font-bold text-gray-400">(--)</span>
            </div>

            <!-- Live Status Dot -->
            <div id="live-indicator-wrap" class="flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-gray-800 text-gray-400 border border-gray-700">
                <span id="live-dot" class="w-2 h-2 rounded-full bg-gray-500"></span>
                <span id="live-status-text">INITIALIZING</span>
            </div>
        </div>

        <!-- Center & Right: Controls, Threshold Settings Modal Button, Playback -->
        <div class="flex items-center gap-2 flex-wrap text-xs">
            
            <!-- Calendar Date Picker -->
            <div class="flex items-center bg-gray-900 border border-gray-700 rounded px-1.5 py-1 gap-1">
                <button type="button" id="btn-prev-day" title="Previous Trading Day" class="p-0.5 hover:bg-gray-800 text-gray-400 hover:text-white rounded transition">◀</button>
                <input type="text" id="date-picker" readonly class="bg-transparent text-gray-200 font-mono text-xs w-24 text-center cursor-pointer focus:outline-none" value="{{ $selectedDate }}">
                <button type="button" id="btn-next-day" title="Next Trading Day" class="p-0.5 hover:bg-gray-800 text-gray-400 hover:text-white rounded transition">▶</button>
                <button type="button" id="btn-today" title="Jump to Today" class="px-1.5 py-0.5 bg-blue-900/60 hover:bg-blue-800 text-blue-300 rounded text-[10px] font-bold border border-blue-700/60 transition">Today</button>
            </div>

            <!-- Primary & Secondary Timeframes -->
            <div class="flex items-center bg-gray-900 border border-gray-700 rounded px-2 py-1 gap-1.5">
                <span class="text-[10px] text-gray-400 font-bold uppercase">TF1:</span>
                <select id="tf-primary-select" class="bg-transparent text-sky-400 font-mono font-bold text-xs focus:outline-none cursor-pointer">
                    @foreach($availableTimeframesPrimary as $m => $lbl)
                        <option value="{{ $m }}" {{ $primaryTimeframe === $m ? 'selected' : '' }} class="bg-gray-900 text-white">{{ $lbl }}</option>
                    @endforeach
                </select>
                <span class="text-gray-600">vs</span>
                <span class="text-[10px] text-gray-400 font-bold uppercase">TF2:</span>
                <select id="tf-secondary-select" class="bg-transparent text-purple-400 font-mono font-bold text-xs focus:outline-none cursor-pointer">
                    @foreach($availableTimeframesSecondary as $m => $lbl)
                        <option value="{{ $m }}" {{ $secondaryTimeframe === $m ? 'selected' : '' }} class="bg-gray-900 text-white">{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Spot / Futures Basis Toggle -->
            <div class="flex items-center bg-gray-900 border border-gray-700 rounded p-0.5">
                <button type="button" id="btn-basis-spot" class="px-2 py-0.5 rounded text-[11px] font-bold {{ $basis === 'spot' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white' }}">SPOT</button>
                <button type="button" id="btn-basis-future" class="px-2 py-0.5 rounded text-[11px] font-bold {{ $basis === 'future' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white' }}">FUT</button>
            </div>

            <!-- ⚙️ Customizable Filter & Alert Settings Button -->
            <button type="button" id="btn-open-settings" title="Configure High-Confidence Thresholds & Alerts" class="flex items-center gap-1.5 px-2.5 py-1 bg-indigo-950/80 hover:bg-indigo-900 border border-indigo-500/50 text-indigo-300 hover:text-white rounded font-bold text-xs shadow-sm transition">
                <span>⚙️</span>
                <span>Filters &amp; Alerts</span>
                <span id="active-threshold-badge" class="px-1 py-0.2 rounded bg-indigo-500/30 text-[9px] font-mono text-indigo-200">≥75 / ≤25</span>
            </button>

            <!-- Playback Stepper Controls -->
            <div class="flex items-center bg-gray-900 border border-gray-700 rounded px-1.5 py-1 gap-1">
                <button type="button" id="btn-step-prev" title="Step Previous Candle (←)" class="px-1.5 py-0.5 hover:bg-gray-800 text-gray-300 rounded font-bold">⏮</button>
                <button type="button" id="btn-play-pause" title="Play / Pause Replay (Space)" class="px-2 py-0.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded font-bold text-[11px] flex items-center gap-1">
                    <span id="play-icon">▶</span>
                    <span id="play-text">Play</span>
                </button>
                <button type="button" id="btn-step-next" title="Step Next Candle (→)" class="px-1.5 py-0.5 hover:bg-gray-800 text-gray-300 rounded font-bold">⏭</button>
                <select id="playback-speed" class="bg-transparent text-gray-300 font-mono text-[10px] focus:outline-none border-l border-gray-700 pl-1 cursor-pointer">
                    <option value="1" class="bg-gray-900">1x</option>
                    <option value="2" selected class="bg-gray-900">2x</option>
                    <option value="4" class="bg-gray-900">4x</option>
                </select>
            </div>

            <!-- Reload / Auto-Refresh -->
            <div class="flex items-center gap-1.5">
                <!-- Auto-Refresh Selector & Live Countdown Badge -->
                <div class="flex items-center bg-gray-900 border border-gray-700 rounded px-2 py-1 gap-1.5 text-xs font-mono" title="Auto-Refresh Interval (Pulls fresh 5m/15m candles & participation data)">
                    <span class="flex items-center gap-1 text-[10px] text-gray-400 font-bold uppercase">
                        <span id="auto-refresh-dot" class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        Auto:
                    </span>
                    <select id="auto-refresh-select" class="bg-transparent text-emerald-400 font-mono font-bold text-xs focus:outline-none cursor-pointer">
                        <option value="15" selected class="bg-gray-900 text-emerald-300">15s</option>
                        <option value="30" class="bg-gray-900 text-emerald-300">30s</option>
                        <option value="60" class="bg-gray-900 text-emerald-300">1m</option>
                        <option value="0" class="bg-gray-900 text-gray-400">Off</option>
                    </select>
                    <span id="auto-refresh-countdown" class="text-[10px] font-bold text-emerald-400 min-w-[20px] text-right">15s</span>
                </div>

                <button type="button" id="btn-reload" title="Refresh Engine Data" class="p-1.5 bg-gray-900 hover:bg-gray-800 border border-gray-700 rounded text-gray-300 hover:text-white transition">
                    <svg id="reload-spinner" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </button>
                <button type="button" id="btn-zen" title="Toggle Zen Fullscreen Mode" class="p-1.5 bg-gray-900 hover:bg-gray-800 border border-gray-700 rounded text-gray-300 hover:text-white transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                </button>
            </div>

        </div>
    </div>

    <!-- ════════════ MAIN SPLIT WORKSPACE: 3 CHARTS (67%) vs COCKPIT (33%) ════════════ -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-2">

        <!-- ──────────────────────────────────────────────────────────── -->
        <!-- LEFT COLUMN (67% WIDTH): 5M & 15M BOTH FULL-WIDTH CHARTS     -->
        <!-- ──────────────────────────────────────────────────────────── -->
        <div class="lg:col-span-8 flex flex-col space-y-2">

            <!-- Row 1: Primary Candlestick Chart (5m Full Width with Dynamic Height) -->
            <div class="cockpit-card p-2 flex flex-col shadow-md relative">
                <div class="flex items-center justify-between pb-1.5 mb-1 border-b border-gray-800 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-sky-400"></span>
                        <span class="font-black text-white uppercase text-xs" id="chart1-label">NIFTY 50 (5m Primary Chart)</span>
                        <span id="chart1-status-badge" class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-emerald-950 text-emerald-300 border border-emerald-600/40">CONFIRMED</span>
                        <span class="text-[10px] text-gray-400 hidden xl:inline">&bull; Signals plotted directly on price bars</span>
                    </div>
                    <div class="flex items-center gap-2 font-mono text-[11px]">
                        <span class="text-gray-400">Bar: <strong id="chart1-candle-time" class="text-sky-400">09:15</strong></span>
                        <span class="text-gray-500">|</span>
                        <span class="text-gray-400">O: <span id="chart1-open" class="text-gray-200">--</span></span>
                        <span class="text-gray-400">C: <span id="chart1-close" class="text-gray-200">--</span></span>
                        <span class="text-gray-400">Chg: <span id="chart1-move" class="font-bold">--</span></span>

                        <!-- Dynamic Height Selector for 5m -->
                        <div class="flex items-center gap-1 bg-gray-900 border border-gray-700/80 rounded px-1.5 py-0.5 ml-1">
                            <span class="text-[9px] text-gray-400 uppercase font-semibold">Height:</span>
                            <select id="select-height-5m" class="bg-transparent text-sky-300 font-bold text-[10.5px] focus:outline-none cursor-pointer">
                                <option value="380" class="bg-[#131722] text-white">380px</option>
                                <option value="420" class="bg-[#131722] text-white">420px</option>
                                <option value="460" class="bg-[#131722] text-white">460px</option>
                                <option value="480" class="bg-[#131722] text-white" selected>480px</option>
                                <option value="500" class="bg-[#131722] text-white">500px</option>
                                <option value="550" class="bg-[#131722] text-white">550px</option>
                                <option value="600" class="bg-[#131722] text-white">600px</option>
                                <option value="700" class="bg-[#131722] text-white">700px</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Canvas Wrapper -->
                <div id="chart-primary-container" class="w-full relative rounded overflow-hidden" style="height: 480px;">
                    <!-- Session Break Vertical Line -->
                    <div id="chart1-day-separator" class="day-separator pointer-events-none absolute top-0 bottom-[26px] z-10 hidden" style="width: 1px;">
                        <div class="h-full border-l-2 border-dashed border-sky-400/80 shadow-[0_0_8px_rgba(56,189,248,0.5)]"></div>
                        <div class="absolute top-2 -translate-x-1/2 flex items-center gap-1.5 bg-[#0d1322]/95 text-sky-300 text-[9px] font-mono font-bold tracking-wider px-2 py-0.5 rounded-full border border-sky-500/50 shadow-xl whitespace-nowrap select-none">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span id="chart1-day-sep-label">TODAY</span>
                        </div>
                    </div>
                    <div id="chart-primary-loading" class="absolute inset-0 bg-[#131722] flex items-center justify-center text-xs text-gray-400 z-10">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-sky-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        Loading Primary Candlestick Chart...
                    </div>
                </div>
            </div>

            <!-- Row 2: Secondary Timeframe (15m Broader Structure - FULL WIDTH MATCHING 5M) -->
            <div class="cockpit-card p-2 flex flex-col shadow-md relative">
                <div class="flex items-center justify-between pb-1.5 mb-1 border-b border-gray-800 text-xs">
                    <div class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-purple-400"></span>
                        <span class="font-black text-white uppercase text-xs" id="chart2-label">NIFTY 50 (15m Broader Structure)</span>
                        <span id="chart2-status-badge" class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-purple-950 text-purple-300 border border-purple-600/40">15M TF</span>
                        <span class="text-[10px] text-gray-400 hidden xl:inline">&bull; 15m signals plotted directly on bars</span>
                    </div>
                    <div class="flex items-center gap-2 font-mono text-[11px]">
                        <span class="text-gray-400">Bar: <strong id="chart2-candle-time" class="text-purple-400">09:15</strong></span>
                        <span class="text-gray-500">|</span>
                        <span class="text-gray-400">C: <span id="chart2-close" class="text-gray-200">--</span></span>
                        <span id="chart2-move" class="font-bold">--</span>

                        <!-- Dynamic Height Selector for 15m -->
                        <div class="flex items-center gap-1 bg-gray-900 border border-gray-700/80 rounded px-1.5 py-0.5 ml-1">
                            <span class="text-[9px] text-gray-400 uppercase font-semibold">Height:</span>
                            <select id="select-height-15m" class="bg-transparent text-purple-300 font-bold text-[10.5px] focus:outline-none cursor-pointer">
                                <option value="300" class="bg-[#131722] text-white">300px</option>
                                <option value="360" class="bg-[#131722] text-white">360px</option>
                                <option value="420" class="bg-[#131722] text-white">420px</option>
                                <option value="480" class="bg-[#131722] text-white" selected>480px</option>
                                <option value="500" class="bg-[#131722] text-white">500px</option>
                                <option value="550" class="bg-[#131722] text-white">550px</option>
                                <option value="600" class="bg-[#131722] text-white">600px</option>
                                <option value="700" class="bg-[#131722] text-white">700px</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Canvas Wrapper -->
                <div id="chart-secondary-container" class="w-full relative rounded overflow-hidden" style="height: 480px;">
                    <!-- Session Break Vertical Line -->
                    <div id="chart2-day-separator" class="day-separator pointer-events-none absolute top-0 bottom-[26px] z-10 hidden" style="width: 1px;">
                        <div class="h-full border-l-2 border-dashed border-purple-400/80 shadow-[0_0_8px_rgba(168,85,247,0.5)]"></div>
                        <div class="absolute top-2 -translate-x-1/2 flex items-center gap-1.5 bg-[#0d1322]/95 text-purple-300 text-[9px] font-mono font-bold tracking-wider px-2 py-0.5 rounded-full border border-purple-500/50 shadow-xl whitespace-nowrap select-none">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span id="chart2-day-sep-label">TODAY</span>
                        </div>
                    </div>
                    <div id="chart-secondary-loading" class="absolute inset-0 bg-[#131722] flex items-center justify-center text-xs text-gray-400 z-10">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-purple-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        Loading 15m Candlestick Chart...
                    </div>
                </div>
            </div>

            <!-- Row 3: Interactive Time-Scrubber Slider -->
            <div class="cockpit-card px-3 py-1.5 flex items-center gap-3 text-xs border border-gray-800">
                <span id="slider-start-time" class="font-mono text-gray-400 text-[11px]">09:15</span>
                <div class="relative flex-1 flex items-center py-1">
                    <div id="slider-time-bubble" class="absolute -top-7 transform -translate-x-1/2 pointer-events-none transition-all duration-75 z-20 flex flex-col items-center" style="left: 0%;">
                        <div class="px-2 py-0.5 bg-indigo-600 text-white font-mono font-bold text-[10px] rounded shadow-lg border border-indigo-400 flex items-center gap-1">
                            <span id="bubble-time-text">09:15</span>
                        </div>
                        <div class="w-0 h-0 border-x-4 border-x-transparent border-t-4 border-t-indigo-600 -mt-[1px]"></div>
                    </div>
                    <input id="timeline-slider" type="range" min="0" max="74" value="0" class="w-full h-2 bg-gray-800 rounded-lg appearance-none cursor-pointer accent-indigo-500 focus:outline-none">
                </div>
                <span id="slider-end-time" class="font-mono text-gray-400 text-[11px]">15:25</span>
            </div>

        </div>

        <!-- ──────────────────────────────────────────────────────────── -->
        <!-- RIGHT COLUMN (33% WIDTH): DECISION DESK & OSCILLATOR & TABS  -->
        <!-- ──────────────────────────────────────────────────────────── -->
        <div class="lg:col-span-4 flex flex-col space-y-2">

            <!-- ════════════ 1. ACTIONABLE TRADE DECISION HERO CARD ════════════ -->
            <div id="trade-decision-hero-card" class="cockpit-card p-3 shadow-xl border-2 transition-all duration-300 bg-gradient-to-br from-[#131722] via-[#151c2e] to-[#111624]">
                <div class="flex items-center justify-between pb-1.5 border-b border-gray-800">
                    <div class="flex items-center gap-1.5">
                        <span class="text-sm">🎯</span>
                        <span class="text-xs font-black text-white uppercase tracking-wider">ACTIONABLE TRADE DECISION</span>
                    </div>
                    <span id="decision-confidence-badge" class="px-2 py-0.5 rounded text-[10px] font-mono font-black bg-indigo-950 text-indigo-300 border border-indigo-600/50">
                        MONITORING
                    </span>
                </div>

                <!-- Big Glowing Action Verdict -->
                <div class="mt-2 p-2.5 rounded-lg bg-gray-900/90 border border-gray-800 flex items-center justify-between">
                    <div>
                        <span class="text-[9.5px] uppercase font-bold text-gray-400 block tracking-wider">Recommended Signal</span>
                        <div id="decision-signal-action" class="text-xl font-black font-mono tracking-tight text-gray-300 flex items-center gap-1.5 mt-0.5">
                            <span>WAIT / EQUILIBRIUM</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-[9.5px] uppercase font-bold text-gray-400 block tracking-wider">Suggested Contract</span>
                        <div id="decision-contract-text" class="text-sm font-black font-mono text-indigo-300 mt-0.5">
                            NO TRIGGER
                        </div>
                    </div>
                </div>

                <!-- Dynamic Trade Execution Levels (SL, TP1, TP2, R:R) -->
                <div class="grid grid-cols-4 gap-1.5 mt-2 text-center font-mono">
                    <div class="p-1.5 rounded bg-gray-900/70 border border-gray-800">
                        <span class="text-[8.5px] text-gray-400 uppercase font-semibold block">Entry Spot</span>
                        <strong id="trade-entry-spot" class="text-xs text-white block mt-0.5">--</strong>
                    </div>
                    <div class="p-1.5 rounded bg-gray-900/70 border border-rose-900/40">
                        <span class="text-[8.5px] text-rose-300 uppercase font-semibold block">Stop Loss</span>
                        <strong id="trade-sl-val" class="text-xs text-rose-400 block mt-0.5">--</strong>
                    </div>
                    <div class="p-1.5 rounded bg-gray-900/70 border border-emerald-900/40">
                        <span class="text-[8.5px] text-emerald-300 uppercase font-semibold block">Target 1 (+50)</span>
                        <strong id="trade-tp1-val" class="text-xs text-emerald-400 block mt-0.5">--</strong>
                    </div>
                    <div class="p-1.5 rounded bg-gray-900/70 border border-indigo-900/40">
                        <span class="text-[8.5px] text-indigo-300 uppercase font-semibold block">Target 2 (+80)</span>
                        <strong id="trade-tp2-val" class="text-xs text-indigo-300 block mt-0.5">--</strong>
                    </div>
                </div>

                <!-- Key Reasons & Confluence Checklist -->
                <div class="mt-2 pt-1.5 border-t border-gray-800/80 space-y-1">
                    <div class="text-[9.5px] text-gray-400 uppercase font-bold flex justify-between">
                        <span>Confluence Checklist (5 Pillars)</span>
                        <span id="checklist-summary" class="text-gray-300 font-mono">0 / 5 Met</span>
                    </div>
                    <div id="confluence-bullets" class="grid grid-cols-2 gap-1 text-[10px] font-mono">
                        <div id="check-breadth" class="flex items-center gap-1 text-gray-500">⚪ Breadth Extreme</div>
                        <div id="check-vwap" class="flex items-center gap-1 text-gray-500">⚪ VWAP Dominance</div>
                        <div id="check-tf" class="flex items-center gap-1 text-gray-500">⚪ 5m &amp; 15m Aligned</div>
                        <div id="check-div" class="flex items-center gap-1 text-gray-500">⚪ Divergence Clean</div>
                        <div id="check-of" class="flex items-center gap-1 text-gray-500 col-span-2">⚪ 30-Depth Order Flow Clean</div>
                    </div>
                </div>
            </div>

            <!-- ════════════ 2. THE HISTORICAL TRACE & PARTICIPATION OSCILLATOR (MOVED HERE!) ════════════ -->
            <div class="cockpit-card p-2 flex flex-col shadow-md relative border border-indigo-900/50">
                <div class="flex items-center justify-between pb-1.5 mb-1 border-b border-gray-800 text-xs">
                    <div class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-400"></span>
                        <span class="font-black text-indigo-300 uppercase text-xs">OSCILLATOR TRACE TIMELINE</span>
                    </div>
                    <!-- Legend Indicators -->
                    <div class="flex items-center gap-2 font-mono text-[9.5px]">
                        <span class="flex items-center gap-1 text-indigo-300 font-bold" title="Composite Participation Score (0-100)">
                            <span class="w-2 h-0.5 bg-indigo-400 inline-block"></span> Score(S)
                        </span>
                        <span class="flex items-center gap-1 text-emerald-400 font-bold" title="Net Breadth P-N (Scaled 0-100)">
                            <span class="w-2 h-0.5 bg-emerald-400 inline-block"></span> Net(P−N)
                        </span>
                        <span class="flex items-center gap-1 text-amber-300 font-bold" title="Percentage of constituents above VWAP">
                            <span class="w-2 h-0.5 bg-amber-400 inline-block"></span> VWAP%
                        </span>
                    </div>
                </div>

                <!-- Canvas Wrapper (210px Height) -->
                <div id="chart-oscillator-container" class="w-full relative rounded overflow-hidden" style="height: 210px;">
                    <div id="chart-oscillator-loading" class="absolute inset-0 bg-[#131722] flex items-center justify-center text-xs text-gray-400 z-10">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-indigo-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        Building Participation Trace...
                    </div>
                </div>
            </div>

            <!-- ════════════ 3. TABBED PARTICIPATION COCKPIT (ALL RIGHT SIDE DATA) ════════════ -->
            <div class="cockpit-card shadow-md flex-1 flex flex-col overflow-hidden">
                
                <!-- 5 Tab Buttons Header -->
                <div class="flex items-center border-b border-gray-800 px-2 bg-gray-900/80 overflow-x-auto">
                    <button type="button" id="tab-btn-pillars" onclick="switchCockpitTab('pillars')" class="px-2.5 py-2 text-[11px] font-black border-b-2 border-indigo-500 text-indigo-300 whitespace-nowrap transition">
                        📊 4 Pillars
                    </button>
                    <button type="button" id="tab-btn-alignment" onclick="switchCockpitTab('alignment')" class="px-2.5 py-2 text-[11px] font-bold text-gray-400 hover:text-white border-b-2 border-transparent whitespace-nowrap transition">
                        ⏱️ Alignment &amp; Div
                    </button>
                    <button type="button" id="tab-btn-inspector" onclick="switchCockpitTab('inspector')" class="px-2.5 py-2 text-[11px] font-bold text-gray-400 hover:text-white border-b-2 border-transparent whitespace-nowrap transition">
                        📋 50 Stocks (<span id="tab-stocks-count">50</span>)
                    </button>
                    <button type="button" id="tab-btn-sectors" onclick="switchCockpitTab('sectors')" class="px-2.5 py-2 text-[11px] font-bold text-gray-400 hover:text-white border-b-2 border-transparent whitespace-nowrap transition">
                        🏢 Sectors &amp; Leaders
                    </button>
                    <button type="button" id="tab-btn-playbook" onclick="switchCockpitTab('playbook')" class="px-2.5 py-2 text-[11px] font-bold text-emerald-400 hover:text-emerald-300 border-b-2 border-transparent whitespace-nowrap transition">
                        📖 Options Playbook
                    </button>
                </div>

                <!-- TAB 1: 4 PILLARS DETAILED BREAKDOWN -->
                <div id="tab-pane-pillars" class="p-3 space-y-2.5 overflow-y-auto max-h-[580px]">
                    
                    <!-- Composite Score Gauge Mini -->
                    <div class="p-2 rounded bg-gray-900/80 border border-gray-800 flex items-center justify-between">
                        <div>
                            <span class="text-[9.5px] uppercase font-bold text-gray-400 block">Composite Participation Score (S)</span>
                            <div class="flex items-baseline gap-1 mt-0.5">
                                <span id="pane-composite-val" class="text-2xl font-black font-mono text-indigo-400">--</span>
                                <span class="text-[10px] text-gray-500 font-mono font-bold">/ 100</span>
                                <span id="pane-composite-badge" class="ml-2 px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-gray-800 text-gray-300">--</span>
                            </div>
                        </div>
                        <div class="w-32">
                            <div class="w-full h-2 bg-gray-800 rounded-full overflow-hidden flex">
                                <div id="pane-composite-bar" class="h-full bg-indigo-500 transition-all duration-200" style="width: 50%;"></div>
                            </div>
                            <div class="flex justify-between text-[8px] font-mono text-gray-500 mt-0.5">
                                <span>Bear &le;25</span>
                                <span>Bull &ge;75</span>
                            </div>
                        </div>
                    </div>

                    <!-- Breadth 3-Way Card & Net Breadth Bar -->
                    <div class="p-2.5 rounded bg-gray-900/80 border border-sky-900/40 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-sky-300 uppercase flex items-center gap-1">
                                <span>1.</span> <span>Breadth Participation</span>
                            </span>
                            <div class="flex items-center gap-1 font-mono text-[10px]">
                                <span>Net:</span>
                                <strong id="pane-net-breadth" class="text-white font-bold">0</strong>
                            </div>
                        </div>

                        <!-- 3-Block Participation Grid -->
                        <div class="grid grid-cols-3 gap-1.5 text-center font-mono">
                            <div class="p-1.5 rounded bg-emerald-950/60 border border-emerald-500/30">
                                <span class="text-[8.5px] text-emerald-400 uppercase font-bold block">Bullish</span>
                                <div class="text-base font-black text-emerald-300" id="pane-cnt-bull">0</div>
                                <span class="text-[8.5px] text-emerald-200/80" id="pane-pct-bull">0%</span>
                            </div>
                            <div class="p-1.5 rounded bg-gray-800/60 border border-gray-700">
                                <span class="text-[8.5px] text-gray-400 uppercase font-bold block">Neutral</span>
                                <div class="text-base font-black text-gray-300" id="pane-cnt-neu">0</div>
                                <span class="text-[8.5px] text-gray-400" id="pane-pct-neu">0%</span>
                            </div>
                            <div class="p-1.5 rounded bg-rose-950/60 border border-rose-500/30">
                                <span class="text-[8.5px] text-rose-400 uppercase font-bold block">Bearish</span>
                                <div class="text-base font-black text-rose-300" id="pane-cnt-bear">0</div>
                                <span class="text-[8.5px] text-rose-200/80" id="pane-pct-bear">0%</span>
                            </div>
                        </div>

                        <!-- Net Breadth Bi-directional Bar -->
                        <div class="w-full h-1.5 bg-gray-800 rounded-full overflow-hidden flex relative mt-1">
                            <div class="absolute left-1/2 top-0 bottom-0 w-[1px] bg-gray-600 z-10"></div>
                            <div id="pane-net-bar-neg" class="h-full bg-rose-500 transition-all duration-200 ml-auto" style="width: 0%;"></div>
                            <div id="pane-net-bar-pos" class="h-full bg-emerald-500 transition-all duration-200 mr-auto" style="width: 0%;"></div>
                        </div>
                    </div>

                    <!-- Strength: VWAP & RS Gauges -->
                    <div class="p-2.5 rounded bg-gray-900/80 border border-emerald-900/40 space-y-1.5">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-emerald-300 uppercase flex items-center gap-1">
                                <span>2.</span> <span>Directional Quality</span>
                            </span>
                            <span id="pane-strength-time" class="text-[10px] font-mono text-gray-400">09:15</span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs font-mono">
                            <div class="p-2 rounded bg-gray-900 border border-gray-800">
                                <div class="flex justify-between items-center text-[9px] text-gray-400">
                                    <span>Above VWAP</span>
                                    <strong id="pane-vwap-pct" class="text-emerald-400 font-bold">0%</strong>
                                </div>
                                <div class="text-sm font-black text-white mt-0.5" id="pane-vwap-cnt">0 / 50</div>
                                <div class="w-full h-1 bg-gray-800 rounded mt-1 overflow-hidden">
                                    <div id="pane-vwap-bar" class="h-full bg-emerald-400" style="width: 0%;"></div>
                                </div>
                            </div>
                            <div class="p-2 rounded bg-gray-900 border border-gray-800">
                                <div class="flex justify-between items-center text-[9px] text-gray-400">
                                    <span>Beating Nifty (RS)</span>
                                    <strong id="pane-rs-pct" class="text-sky-400 font-bold">0%</strong>
                                </div>
                                <div class="text-sm font-black text-white mt-0.5" id="pane-rs-cnt">0 / 50</div>
                                <div class="w-full h-1 bg-gray-800 rounded mt-1 overflow-hidden">
                                    <div id="pane-rs-bar" class="h-full bg-sky-400" style="width: 0%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Momentum & Acceleration -->
                    <div class="p-2.5 rounded bg-gray-900/80 border border-amber-900/40 flex items-center justify-between text-xs font-mono">
                        <div>
                            <span class="text-[9.5px] uppercase font-bold text-amber-300 block">3. Momentum Dynamics</span>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-gray-400">ΔBt: <strong id="pane-mom-delta" class="text-white font-bold">0%</strong></span>
                                <span class="text-gray-500">|</span>
                                <span class="text-gray-400">Accel: <strong id="pane-mom-accel" class="text-white font-bold">0%</strong></span>
                            </div>
                        </div>
                        <span id="pane-mom-badge" class="px-2 py-0.5 rounded text-[9px] font-bold bg-amber-950 text-amber-300 border border-amber-600/50">
                            EQUILIBRIUM
                        </span>
                    </div>

                </div>

                <!-- TAB 2: ALIGNMENT & DIVERGENCE -->
                <div id="tab-pane-alignment" class="p-3 space-y-2.5 hidden overflow-y-auto max-h-[580px]">
                    <div class="p-3 rounded bg-gray-900/80 border border-purple-900/50 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-purple-300 uppercase">4. 5m vs 15m Timeframe Matrix</span>
                            <span id="pane-tf-score" class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-purple-950 text-purple-300">SCORE: 50</span>
                        </div>

                        <div class="flex items-center justify-between p-2 rounded bg-gray-900 border border-gray-800">
                            <div class="flex items-center gap-2 font-mono text-[11px]">
                                <span class="px-2 py-1 rounded bg-gray-800 font-bold" id="pane-15m-box">15M: NEUTRAL</span>
                                <span class="text-gray-500 font-bold">+</span>
                                <span class="px-2 py-1 rounded bg-gray-800 font-bold" id="pane-5m-box">5M: NEUTRAL</span>
                            </div>
                            <div class="text-right">
                                <span class="text-[9px] text-gray-400 block font-semibold">VERDICT</span>
                                <strong id="pane-tf-verdict" class="text-xs text-amber-300 font-black block mt-0.5">Indecisive Chop</strong>
                            </div>
                        </div>
                    </div>

                    <div id="pane-div-card" class="p-3 rounded bg-gray-900/80 border border-gray-800 space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-white flex items-center gap-1.5">
                                <span id="pane-div-icon">🛡️</span>
                                <span id="pane-div-badge">Confirmed Agreement</span>
                            </span>
                            <span id="pane-div-slope" class="text-[9.5px] font-mono text-gray-400">ΔPrice: +0 | ΔScore: +0</span>
                        </div>
                        <p id="pane-div-desc" class="text-[10px] text-gray-300 leading-relaxed mt-1">
                            Price and constituent participation are moving synchronously in the same direction.
                        </p>
                    </div>

                    <!-- 30-Depth Order Flow Microstructure Card -->
                    <div id="pane-of-card" class="p-3 rounded bg-gray-900/80 border border-indigo-900/40 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-indigo-300 flex items-center gap-1.5">
                                <span>🌊</span>
                                <span>30-Depth Futures Microstructure</span>
                            </span>
                            <span id="pane-of-dynamics-badge" class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-gray-800 text-gray-300">
                                NORMAL
                            </span>
                        </div>

                        <!-- 3-Way Order Flow Metrics (Delta, Buy Qty, Sell Qty, CVD) -->
                        <div class="grid grid-cols-3 gap-1.5 text-center font-mono text-[10px]">
                            <div class="p-1.5 rounded bg-gray-900 border border-gray-800">
                                <span class="text-[8.5px] text-gray-400 uppercase block">5m Delta</span>
                                <strong id="pane-of-delta" class="text-xs font-black text-gray-200">0</strong>
                            </div>
                            <div class="p-1.5 rounded bg-gray-900 border border-gray-800">
                                <span class="text-[8.5px] text-gray-400 uppercase block">Buy vs Sell</span>
                                <div class="text-[9.5px] font-bold text-gray-300 truncate">
                                    <span id="pane-of-buy" class="text-emerald-400">0</span> / <span id="pane-of-sell" class="text-rose-400">0</span>
                                </div>
                            </div>
                            <div class="p-1.5 rounded bg-gray-900 border border-gray-800">
                                <span class="text-[8.5px] text-gray-400 uppercase block">Session CVD</span>
                                <strong id="pane-of-cvd" class="text-xs font-black text-indigo-300">0</strong>
                            </div>
                        </div>

                        <p id="pane-of-desc" class="text-[10px] text-gray-400 leading-relaxed font-mono">
                            Aggressive order flow &amp; passive limit absorption tracking.
                        </p>
                    </div>
                </div>

                <!-- TAB 3: 50 CONSTITUENTS REAL-TIME INSPECTOR TABLE (MOVED HERE!) -->
                <div id="tab-pane-inspector" class="p-2 space-y-2 hidden overflow-y-auto max-h-[580px]">
                    <div class="flex flex-wrap items-center justify-between gap-1.5 text-xs">
                        <input id="stock-search" type="text" placeholder="Search stock..." class="bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-white focus:outline-none focus:border-indigo-500 w-28 sm:w-36">
                        
                        <select id="state-filter" class="bg-gray-900 border border-gray-700 rounded px-1.5 py-1 text-[11px] text-gray-300 focus:outline-none">
                            <option value="ALL">All States</option>
                            <option value="BULLISH">Bullish Only</option>
                            <option value="BEARISH">Bearish Only</option>
                            <option value="NEUTRAL">Neutral Only</option>
                            <option value="ABOVE_VWAP">Above VWAP</option>
                            <option value="OUTPERFORM">Beating Nifty</option>
                        </select>

                        <select id="sort-filter" class="bg-gray-900 border border-gray-700 rounded px-1.5 py-1 text-[11px] text-gray-300 focus:outline-none">
                            <option value="WEIGHT_DESC">Weight (High→Low)</option>
                            <option value="SCORE_DESC">Score (High→Low)</option>
                            <option value="RS_DESC">RS (High→Low)</option>
                            <option value="PTS_DESC">Pts (High→Low)</option>
                        </select>
                    </div>

                    <div class="w-full overflow-x-auto max-h-[500px] overflow-y-auto border border-gray-800 rounded bg-[#0b0e14]">
                        <table class="w-full text-xs text-left divide-y divide-gray-800 font-mono">
                            <thead class="sticky top-0 bg-[#131722] text-gray-400 uppercase text-[9px] z-20">
                                <tr>
                                    <th class="px-2 py-1.5">Stock</th>
                                    <th class="px-1.5 py-1.5 text-right">Wt%</th>
                                    <th class="px-1.5 py-1.5 text-right">Move%</th>
                                    <th class="px-1.5 py-1.5 text-right">VWAP</th>
                                    <th class="px-1.5 py-1.5 text-right">RS%</th>
                                    <th class="px-1.5 py-1.5 text-right">Pts</th>
                                    <th class="px-1.5 py-1.5 text-center">State</th>
                                </tr>
                            </thead>
                            <tbody id="constituents-tbody" class="divide-y divide-gray-900 text-[11px]">
                                <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500 italic">Loading stocks...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 4: SECTORS & LEADERS -->
                <div id="tab-pane-sectors" class="p-3 space-y-2.5 hidden overflow-y-auto max-h-[580px]">
                    <div class="text-xs font-bold text-gray-300 uppercase pb-1 border-b border-gray-800 flex justify-between">
                        <span>Top Driving &amp; Dragging Sectors</span>
                        <span class="text-[10px] text-gray-500 font-mono">Net Points Contribution</span>
                    </div>
                    <div id="sectors-cockpit-list" class="space-y-1 text-xs font-mono">
                        <div class="text-gray-500 italic py-2">Loading sector breakdown...</div>
                    </div>
                </div>

                <!-- TAB 5: 📖 NIFTY 50 OPTIONS TRADE PLAYBOOK (ACTION GUIDE) -->
                <div id="tab-pane-playbook" class="p-3 space-y-3 hidden overflow-y-auto max-h-[580px] text-xs leading-relaxed">
                    
                    <div class="p-2.5 rounded bg-emerald-950/40 border border-emerald-500/50 space-y-1">
                        <h4 class="font-black text-emerald-300 uppercase flex items-center gap-1.5">
                            <span>🚀</span> <span>Rule 1: High-Confidence CALL (CE) Setup</span>
                        </h4>
                        <ul class="list-disc list-inside space-y-0.5 text-gray-300 text-[11px]">
                            <li><strong>Composite Score &ge; 75</strong> with <strong>Net Breadth &ge; +20</strong>.</li>
                            <li><strong>Above VWAP &ge; 65%</strong>: Confirms broad institutional buyers control price.</li>
                            <li><strong>15m Timeframe is Bullish or Neutral</strong> (No higher-timeframe resistance).</li>
                            <li><strong>No Bearish Divergence</strong> (Price highs supported by breadth highs).</li>
                            <li><span class="text-emerald-300 font-bold">Action:</span> Buy ATM CE (or 1 strike ITM). SL: Trigger Bar Low &minus; 15 pts. TP1: +60 pts, TP2: +120 pts.</li>
                        </ul>
                    </div>

                    <div class="p-2.5 rounded bg-rose-950/40 border border-rose-500/50 space-y-1">
                        <h4 class="font-black text-rose-300 uppercase flex items-center gap-1.5">
                            <span>💥</span> <span>Rule 2: High-Confidence PUT (PE) Setup</span>
                        </h4>
                        <ul class="list-disc list-inside space-y-0.5 text-gray-300 text-[11px]">
                            <li><strong>Composite Score &le; 25</strong> with <strong>Net Breadth &le; -20</strong>.</li>
                            <li><strong>Above VWAP &le; 35%</strong>: Confirms broad institutional sellers dominance.</li>
                            <li><strong>15m Timeframe is Bearish or Neutral</strong>.</li>
                            <li><strong>No Bullish Divergence</strong>.</li>
                            <li><span class="text-rose-300 font-bold">Action:</span> Buy ATM PE. SL: Trigger Bar High + 15 pts. TP1: &minus;60 pts, TP2: &minus;120 pts.</li>
                        </ul>
                    </div>

                    <div class="p-2.5 rounded bg-amber-950/40 border border-amber-500/50 space-y-1">
                        <h4 class="font-black text-amber-300 uppercase flex items-center gap-1.5">
                            <span>⚠️</span> <span>Rule 3: Divergence Traps (WHEN TO AVOID)</span>
                        </h4>
                        <ul class="list-disc list-inside space-y-0.5 text-gray-300 text-[11px]">
                            <li><strong>Exhaustion Warning:</strong> Price makes a higher high, but Composite Score drops below 60 $\implies$ <strong class="text-rose-300">DO NOT BUY CALL!</strong> Heavyweight masking.</li>
                            <li><strong>Absorption Warning:</strong> Price makes a lower low, but Breadth improves $\implies$ <strong class="text-emerald-300">DO NOT SHORT!</strong> Institutional defense.</li>
                        </ul>
                    </div>

                    <div class="p-2.5 rounded bg-gray-900 border border-gray-800 space-y-1">
                        <h4 class="font-black text-white uppercase flex items-center gap-1.5">
                            <span>⚖️</span> <span>Rule 4: Equilibrium / Chop (40–60 Score)</span>
                        </h4>
                        <p class="text-[11px] text-gray-400">
                            When Score is in the 40–60 zone, market is balanced. Option buying loses value to theta decay. Wait for a breakout beyond 75 or breakdown below 25.
                        </p>
                    </div>

                </div>

            </div>

        </div>
    </div>

</div>

<!-- ════════════ CUSTOMIZABLE FILTER & ALERT SETTINGS MODAL ════════════ -->
<div id="settings-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-xs hidden p-4">
    <div class="cockpit-card w-full max-w-lg p-5 shadow-2xl border border-indigo-700/60 space-y-4">
        
        <div class="flex items-center justify-between pb-2 border-b border-gray-800">
            <div class="flex items-center gap-2">
                <span class="text-base">⚙️</span>
                <h3 class="text-sm font-black text-white uppercase tracking-wider">High-Confidence Trigger &amp; Alert Settings</h3>
            </div>
            <button type="button" id="btn-close-settings" class="text-gray-400 hover:text-white p-1 rounded hover:bg-gray-800 text-lg font-bold">✕</button>
        </div>

        <p class="text-xs text-gray-400 leading-relaxed">
            Customize the quantitative thresholds required to trigger high-probability Long (Call) or Short (Put) signals. Modifying these immediately updates all charts and historical markers live.
        </p>

        <div class="space-y-3.5 text-xs font-mono">
            
            <!-- Bullish Composite Score Threshold -->
            <div>
                <div class="flex justify-between items-center text-gray-300">
                    <span>Bullish Score Threshold (BUY CALL):</span>
                    <strong id="setting-bull-score-val" class="text-emerald-400 font-bold">&ge; 75</strong>
                </div>
                <input id="slider-bull-score" type="range" min="60" max="90" value="75" step="1" class="w-full h-2 bg-gray-800 rounded accent-emerald-500 mt-1 cursor-pointer">
                <span class="text-[9.5px] text-gray-500">Requires Composite Score (S) to be at or above this value.</span>
            </div>

            <!-- Bearish Composite Score Threshold -->
            <div>
                <div class="flex justify-between items-center text-gray-300">
                    <span>Bearish Score Threshold (BUY PUT):</span>
                    <strong id="setting-bear-score-val" class="text-rose-400 font-bold">&le; 25</strong>
                </div>
                <input id="slider-bear-score" type="range" min="10" max="40" value="25" step="1" class="w-full h-2 bg-gray-800 rounded accent-rose-500 mt-1 cursor-pointer">
                <span class="text-[9.5px] text-gray-500">Requires Composite Score (S) to be at or below this value.</span>
            </div>

            <!-- Net Breadth Minimum -->
            <div>
                <div class="flex justify-between items-center text-gray-300">
                    <span>Minimum Net Breadth (|P − N|):</span>
                    <strong id="setting-net-breadth-val" class="text-indigo-300 font-bold">&plusmn; 20</strong>
                </div>
                <input id="slider-net-breadth" type="range" min="10" max="40" value="20" step="1" class="w-full h-2 bg-gray-800 rounded accent-indigo-500 mt-1 cursor-pointer">
                <span class="text-[9.5px] text-gray-500">Requires at least +20 net stocks for Call, -20 net stocks for Put.</span>
            </div>

            <!-- VWAP Participation Minimum -->
            <div>
                <div class="flex justify-between items-center text-gray-300">
                    <span>VWAP Participation Minimum (%):</span>
                    <strong id="setting-vwap-pct-val" class="text-sky-300 font-bold">&ge; 65%</strong>
                </div>
                <input id="slider-vwap-pct" type="range" min="50" max="85" value="65" step="5" class="w-full h-2 bg-gray-800 rounded accent-sky-500 mt-1 cursor-pointer">
                <span class="text-[9.5px] text-gray-500">Requires at least 65% constituents trading above VWAP.</span>
            </div>

            <!-- Signal Clarity & Chart Marker Display Mode -->
            <div class="p-2.5 rounded bg-gray-900 border border-gray-800 space-y-1.5">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-gray-200 text-xs">🎯 Chart Marker Display Mode:</span>
                </div>
                <select id="select-display-mode" class="w-full bg-gray-800 border border-gray-700 rounded px-2 py-1.5 text-xs text-indigo-300 font-bold focus:outline-none">
                    <option value="high_conviction">🛡️ Trend Entries + High-Conviction Structural Traps (Recommended)</option>
                    <option value="trend_only">🎯 Actionable Trend Entries Only (BUY CE / BUY PE Only - Zero Noise)</option>
                    <option value="all_signals">🔬 Full Diagnostic (Show All Signals &amp; Micro Delta)</option>
                </select>
                <span class="text-[9.5px] text-gray-500 block">Switch to "Actionable Trend Entries Only" to trade clean 50–80 pt trend moves with zero trap dots on the chart.</span>
            </div>

            <!-- Signal Cooldown / Holding Period -->
            <div>
                <div class="flex justify-between items-center text-gray-300 text-xs">
                    <span>Signal Cooldown / Holding Period:</span>
                    <strong id="setting-cooldown-val" class="text-indigo-300 font-bold">8 Bars (40 mins)</strong>
                </div>
                <input id="slider-cooldown-bars" type="range" min="4" max="14" value="8" step="1" class="w-full h-2 bg-gray-800 rounded accent-indigo-500 mt-1 cursor-pointer">
                <span class="text-[9.5px] text-gray-500">Locks signal after an entry to let the 50–80 pt trend run without triggering noise on every bar.</span>
            </div>

            <!-- 6 SELECTABLE AUDIO SOUND PRESETS -->
            <div class="p-2.5 rounded bg-gray-900 border border-gray-800 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-gray-300">🔔 Select Alert Sound Preset (6 Sounds):</span>
                    <button type="button" id="btn-test-sound" class="px-2 py-0.5 rounded bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[10px] flex items-center gap-1">
                        <span>▶</span> Test Sound
                    </button>
                </div>
                <select id="select-audio-sound" class="w-full bg-gray-800 border border-gray-700 rounded px-2 py-1.5 text-xs text-white focus:outline-none">
                    <option value="chime">1. Crisp Chime (Harmonic Bell)</option>
                    <option value="sonar">2. Radar Sonar Ping</option>
                    <option value="chirp">3. Terminal Double Chirp</option>
                    <option value="fanfare">4. Ascending Tri-Tone Fanfare</option>
                    <option value="siren">5. Alert Siren Sweep</option>
                    <option value="click">6. Subtle Clack Tick</option>
                </select>

                <div class="flex items-center justify-between text-[11px] pt-1">
                    <span class="text-gray-400">Alert Volume:</span>
                    <input id="slider-audio-volume" type="range" min="10" max="100" value="50" class="w-32 h-1.5 bg-gray-700 rounded accent-indigo-500 cursor-pointer">
                </div>
            </div>

            <!-- Toggles: Timeframe Confirmation, Divergence Filter, Audio -->
            <div class="pt-2 border-t border-gray-800 space-y-2">
                <label class="flex items-center justify-between cursor-pointer">
                    <span class="text-gray-300">Require 15m Timeframe Alignment Confirmation:</span>
                    <input id="toggle-tf-confirm" type="checkbox" checked class="w-4 h-4 rounded bg-gray-800 accent-indigo-600 cursor-pointer">
                </label>
                <label class="flex items-center justify-between cursor-pointer">
                    <span class="text-gray-300">Block Signals during Divergence Traps:</span>
                    <input id="toggle-div-filter" type="checkbox" checked class="w-4 h-4 rounded bg-gray-800 accent-indigo-600 cursor-pointer">
                </label>
                <label class="flex items-center justify-between cursor-pointer">
                    <div>
                        <span class="text-gray-300 font-medium block">Plot Fresh Trigger Entries Only:</span>
                        <span class="text-[10px] text-gray-500">Only signals on initial entry / state transition (suppresses spamming every continuous bar)</span>
                    </div>
                    <input id="toggle-fresh-triggers" type="checkbox" checked class="w-4 h-4 rounded bg-gray-800 accent-indigo-600 cursor-pointer">
                </label>
                <label class="flex items-center justify-between cursor-pointer">
                    <span class="text-gray-300">Audio Chime on High-Confidence Alert:</span>
                    <input id="toggle-audio-alert" type="checkbox" checked class="w-4 h-4 rounded bg-gray-800 accent-indigo-600 cursor-pointer">
                </label>
            </div>

        </div>

        <div class="pt-2 border-t border-gray-800 flex items-center justify-between">
            <button type="button" id="btn-reset-settings" class="px-3 py-1.5 rounded bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs font-bold transition">
                Reset to Default
            </button>
            <button type="button" id="btn-save-settings" class="px-4 py-1.5 rounded bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition shadow-md">
                Apply &amp; Save Settings
            </button>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    // ════════════ GLOBAL ENGINE & THRESHOLD STATE ════════════
    let selectedDate = "{{ $selectedDate }}";
    const TODAY_DATE = "{{ $todayStr }}";
    let primaryTf = {{ $primaryTimeframe }};
    let secondaryTf = {{ $secondaryTimeframe }};
    let activeBasis = "{{ $basis }}";
    let engineData = null;
    let activeTimeIndex = -1;
    let timestampsList = [];
    let isPlaying = false;
    let playbackTimer = null;
    let playbackSpeed = 2;

    // Customizable Threshold Settings (Loaded from localStorage)
    const DEFAULT_SETTINGS = {
        bullScoreTh: 75,
        bearScoreTh: 25,
        minNetBreadth: 20,
        minVwapPct: 65,
        requireTfConfirm: true,
        blockDivergence: true,
        onlyFreshTriggers: true,
        chartDisplayMode: 'high_conviction',
        signalCooldownBars: 8,
        enableAudio: true,
        audioSound: 'chime',
        audioVolume: 50,
    };

    let userSettings = { ...DEFAULT_SETTINGS };
    try {
        const saved = localStorage.getItem('nifty_participation_settings');
        if (saved) userSettings = Object.assign(userSettings, JSON.parse(saved));
    } catch (e) {}

    // Charts references
    let chartPrimary = null;
    let chartOscillator = null;
    let chartSecondary = null;

    let candleSeriesPrimary = null;
    let candleSeriesSecondary = null;

    // Oscillator lines
    let oscLineScore = null;
    let oscLineNetBreadth = null;
    let oscLineVwap = null;

    // ════════════ DYNAMIC CHART HEIGHT MANAGEMENT (PERSISTED IN LOCALSTORAGE) ════════════
    const HEIGHT_STORAGE_KEY_5M = 'nifty_participation_chart_5m_height';
    const HEIGHT_STORAGE_KEY_15M = 'nifty_participation_chart_15m_height';

    let currentHeight5m = parseInt(localStorage.getItem(HEIGHT_STORAGE_KEY_5M)) || 480;
    let currentHeight15m = parseInt(localStorage.getItem(HEIGHT_STORAGE_KEY_15M)) || 480;

    function applyChartHeight(chartType, height) {
        height = parseInt(height) || 480;
        if (chartType === '5m') {
            currentHeight5m = height;
            localStorage.setItem(HEIGHT_STORAGE_KEY_5M, height);
            const pContainer = document.getElementById('chart-primary-container');
            if (pContainer) pContainer.style.height = `${height}px`;
            const sel = document.getElementById('select-height-5m');
            if (sel) sel.value = height;
            if (chartPrimary && pContainer) {
                chartPrimary.resize(pContainer.clientWidth, height);
            }
        } else if (chartType === '15m') {
            currentHeight15m = height;
            localStorage.setItem(HEIGHT_STORAGE_KEY_15M, height);
            const sContainer = document.getElementById('chart-secondary-container');
            if (sContainer) sContainer.style.height = `${height}px`;
            const sel = document.getElementById('select-height-15m');
            if (sel) sel.value = height;
            if (chartSecondary && sContainer) {
                chartSecondary.resize(sContainer.clientWidth, height);
            }
        }
    }

    // ════════════ INITIALIZATION ════════════
    document.addEventListener('DOMContentLoaded', () => {
        initDatePicker();
        initControls();
        initSettingsModal();
        initLightweightCharts();
        initAutoRefresh();
        loadEngineData();
    });

    // ════════════ DATE PICKER & CONTROLS ════════════
    function initDatePicker() {
        const dateInput = document.getElementById('date-picker');
        if (dateInput && typeof flatpickr !== 'undefined') {
            flatpickr(dateInput, {
                dateFormat: 'Y-m-d',
                defaultDate: selectedDate,
                maxDate: 'today',
                onChange: function(selectedDates, dateStr) {
                    if (dateStr && dateStr !== selectedDate) {
                        selectedDate = dateStr;
                        loadEngineData();
                    }
                }
            });
        }
    }

    function initControls() {
        document.getElementById('btn-prev-day')?.addEventListener('click', () => stepDay(-1));
        document.getElementById('btn-next-day')?.addEventListener('click', () => stepDay(1));
        document.getElementById('btn-today')?.addEventListener('click', () => {
            const todayStr = "{{ \Illuminate\Support\Carbon::now('Asia/Kolkata')->toDateString() }}";
            if (selectedDate !== todayStr) {
                selectedDate = todayStr;
                document.getElementById('date-picker').value = selectedDate;
                loadEngineData();
            }
        });

        document.getElementById('tf-primary-select')?.addEventListener('change', (e) => {
            primaryTf = parseInt(e.target.value);
            loadEngineData();
        });

        document.getElementById('tf-secondary-select')?.addEventListener('change', (e) => {
            secondaryTf = parseInt(e.target.value);
            loadEngineData();
        });

        document.getElementById('btn-basis-spot')?.addEventListener('click', () => {
            if (activeBasis !== 'spot') {
                activeBasis = 'spot';
                updateBasisUI();
                loadEngineData();
            }
        });

        document.getElementById('btn-basis-future')?.addEventListener('click', () => {
            if (activeBasis !== 'future') {
                activeBasis = 'future';
                updateBasisUI();
                loadEngineData();
            }
        });

        document.getElementById('btn-reload')?.addEventListener('click', () => loadEngineData(true));
        document.getElementById('btn-zen')?.addEventListener('click', () => document.body.classList.toggle('zen-mode'));

        // Playback controls
        document.getElementById('btn-step-prev')?.addEventListener('click', () => stepCandle(-1));
        document.getElementById('btn-step-next')?.addEventListener('click', () => stepCandle(1));
        document.getElementById('btn-play-pause')?.addEventListener('click', togglePlayPause);
        document.getElementById('playback-speed')?.addEventListener('change', (e) => {
            playbackSpeed = parseInt(e.target.value) || 2;
            if (isPlaying) {
                stopPlayback();
                startPlayback();
            }
        });

        // Timeline slider
        const slider = document.getElementById('timeline-slider');
        slider?.addEventListener('input', (e) => {
            const idx = parseInt(e.target.value);
            syncActiveIndex(idx);
        });

        // Table filters
        document.getElementById('stock-search')?.addEventListener('input', renderConstituentsTable);
        document.getElementById('state-filter')?.addEventListener('change', renderConstituentsTable);
        document.getElementById('sort-filter')?.addEventListener('change', renderConstituentsTable);

        // Keyboard navigation (Arrow keys + Space)
        window.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                stepCandle(-1);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                stepCandle(1);
            } else if (e.code === 'Space') {
                e.preventDefault();
                togglePlayPause();
            }
        });
    }

    function updateBasisUI() {
        const btnSpot = document.getElementById('btn-basis-spot');
        const btnFut = document.getElementById('btn-basis-future');
        if (activeBasis === 'spot') {
            btnSpot.className = 'px-2 py-0.5 rounded text-[11px] font-bold bg-indigo-600 text-white';
            btnFut.className = 'px-2 py-0.5 rounded text-[11px] font-bold text-gray-400 hover:text-white';
        } else {
            btnFut.className = 'px-2 py-0.5 rounded text-[11px] font-bold bg-indigo-600 text-white';
            btnSpot.className = 'px-2 py-0.5 rounded text-[11px] font-bold text-gray-400 hover:text-white';
        }
    }

    function stepDay(offset) {
        const availableDates = @json($availableDates);
        const curIdx = availableDates.indexOf(selectedDate);
        if (curIdx >= 0) {
            const nextIdx = curIdx - offset;
            if (nextIdx >= 0 && nextIdx < availableDates.length) {
                selectedDate = availableDates[nextIdx];
                document.getElementById('date-picker').value = selectedDate;
                loadEngineData();
            }
        }
    }

    // ════════════ AUDIO SYNTHESIZER (6 SELECTABLE PRESETS) ════════════
    function playAudioPreset(presetName, volumePercent = 50) {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            const now = ctx.currentTime;
            const gain = ctx.createGain();
            const volFactor = (volumePercent / 100) * 0.4;
            gain.connect(ctx.destination);
            gain.gain.setValueAtTime(volFactor, now);

            if (presetName === 'sonar') {
                // Radar Sonar Ping
                const o = ctx.createOscillator();
                o.type = 'sine';
                o.frequency.setValueAtTime(1760, now);
                o.frequency.exponentialRampToValueAtTime(440, now + 0.45);
                o.connect(gain);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
                o.start(now); o.stop(now + 0.45);
            } else if (presetName === 'chirp') {
                // High-tech Terminal Double Chirp
                const o = ctx.createOscillator();
                o.type = 'square';
                o.frequency.setValueAtTime(987, now);
                o.frequency.setValueAtTime(1318, now + 0.08);
                o.connect(gain);
                gain.gain.setValueAtTime(volFactor * 0.4, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.22);
                o.start(now); o.stop(now + 0.22);
            } else if (presetName === 'fanfare') {
                // Ascending Tri-Tone Fanfare (C5 -> E5 -> G5)
                const o = ctx.createOscillator();
                o.type = 'triangle';
                o.frequency.setValueAtTime(523.25, now);
                o.frequency.setValueAtTime(659.25, now + 0.12);
                o.frequency.setValueAtTime(783.99, now + 0.24);
                o.connect(gain);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.5);
                o.start(now); o.stop(now + 0.5);
            } else if (presetName === 'siren') {
                // Alert Siren Sweep
                const o = ctx.createOscillator();
                o.type = 'sawtooth';
                o.frequency.setValueAtTime(600, now);
                o.frequency.linearRampToValueAtTime(1200, now + 0.15);
                o.frequency.linearRampToValueAtTime(600, now + 0.3);
                o.connect(gain);
                gain.gain.setValueAtTime(volFactor * 0.3, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
                o.start(now); o.stop(now + 0.35);
            } else if (presetName === 'click') {
                // Subtle Clack / Woodblock
                const o = ctx.createOscillator();
                o.type = 'sine';
                o.frequency.setValueAtTime(350, now);
                o.connect(gain);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.08);
                o.start(now); o.stop(now + 0.08);
            } else {
                // Default: Crisp Chime (Harmonic Dual Bell)
                const o1 = ctx.createOscillator();
                const o2 = ctx.createOscillator();
                o1.type = 'sine'; o2.type = 'sine';
                o1.frequency.setValueAtTime(880, now);
                o2.frequency.setValueAtTime(1320, now);
                o1.connect(gain); o2.connect(gain);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.55);
                o1.start(now); o2.start(now);
                o1.stop(now + 0.55); o2.stop(now + 0.55);
            }
        } catch (e) {
            console.warn('Audio synthesis warning:', e);
        }
    }

    let lastAudioTime = 0;
    function triggerAlertAudio() {
        if (!userSettings.enableAudio) return;
        const now = Date.now();
        if (now - lastAudioTime < 10000) return; // Prevent repeated beeps within 10 seconds
        lastAudioTime = now;
        playAudioPreset(userSettings.audioSound || 'chime', userSettings.audioVolume || 50);
    }

    // ════════════ SETTINGS MODAL & CUSTOMIZATION ════════════
    function initSettingsModal() {
        const modal = document.getElementById('settings-modal');
        const btnOpen = document.getElementById('btn-open-settings');
        const btnClose = document.getElementById('btn-close-settings');
        const btnSave = document.getElementById('btn-save-settings');
        const btnReset = document.getElementById('btn-reset-settings');
        const btnTestSound = document.getElementById('btn-test-sound');

        const slBull = document.getElementById('slider-bull-score');
        const slBear = document.getElementById('slider-bear-score');
        const slNet = document.getElementById('slider-net-breadth');
        const slVwap = document.getElementById('slider-vwap-pct');
        const selDispMode = document.getElementById('select-display-mode');
        const slCooldown = document.getElementById('slider-cooldown-bars');
        const selSound = document.getElementById('select-audio-sound');
        const slVol = document.getElementById('slider-audio-volume');
        const chkTf = document.getElementById('toggle-tf-confirm');
        const chkDiv = document.getElementById('toggle-div-filter');
        const chkFresh = document.getElementById('toggle-fresh-triggers');
        const chkAudio = document.getElementById('toggle-audio-alert');

        function syncSettingsUI() {
            if (slBull) slBull.value = userSettings.bullScoreTh;
            if (slBear) slBear.value = userSettings.bearScoreTh;
            if (slNet) slNet.value = userSettings.minNetBreadth;
            if (slVwap) slVwap.value = userSettings.minVwapPct;
            if (selDispMode) {
                let modeVal = userSettings.chartDisplayMode || 'high_conviction';
                if (modeVal === 'all_micro') modeVal = 'all_signals';
                selDispMode.value = modeVal;
            }
            if (slCooldown) slCooldown.value = userSettings.signalCooldownBars || 8;
            if (selSound) selSound.value = userSettings.audioSound || 'chime';
            if (slVol) slVol.value = userSettings.audioVolume || 50;
            if (chkTf) chkTf.checked = userSettings.requireTfConfirm;
            if (chkDiv) chkDiv.checked = userSettings.blockDivergence;
            if (chkFresh) chkFresh.checked = userSettings.onlyFreshTriggers !== false;
            if (chkAudio) chkAudio.checked = userSettings.enableAudio;

            document.getElementById('setting-bull-score-val').textContent = `≥ ${userSettings.bullScoreTh}`;
            document.getElementById('setting-bear-score-val').textContent = `≤ ${userSettings.bearScoreTh}`;
            document.getElementById('setting-net-breadth-val').textContent = `± ${userSettings.minNetBreadth}`;
            document.getElementById('setting-vwap-pct-val').textContent = `≥ ${userSettings.minVwapPct}%`;
            document.getElementById('setting-cooldown-val').textContent = `${userSettings.signalCooldownBars || 8} Bars (${(userSettings.signalCooldownBars || 8) * 5} mins)`;

            let modeBadge = '🛡️ High-Conviction';
            const curMode = userSettings.chartDisplayMode || 'high_conviction';
            if (curMode === 'trend_only') modeBadge = '🎯 Zero Noise';
            else if (curMode === 'all_signals' || curMode === 'all_micro') modeBadge = '🔬 Full Diagnostic';
            document.getElementById('active-threshold-badge').textContent = `≥${userSettings.bullScoreTh} / ≤${userSettings.bearScoreTh} • ${modeBadge}`;
        }

        btnOpen?.addEventListener('click', () => {
            syncSettingsUI();
            modal.classList.remove('hidden');
        });

        btnClose?.addEventListener('click', () => modal.classList.add('hidden'));

        slBull?.addEventListener('input', (e) => document.getElementById('setting-bull-score-val').textContent = `≥ ${e.target.value}`);
        slBear?.addEventListener('input', (e) => document.getElementById('setting-bear-score-val').textContent = `≤ ${e.target.value}`);
        slNet?.addEventListener('input', (e) => document.getElementById('setting-net-breadth-val').textContent = `± ${e.target.value}`);
        slVwap?.addEventListener('input', (e) => document.getElementById('setting-vwap-pct-val').textContent = `≥ ${e.target.value}%`);
        slCooldown?.addEventListener('input', (e) => {
            const val = parseInt(e.target.value);
            document.getElementById('setting-cooldown-val').textContent = `${val} Bars (${val * 5} mins)`;
        });

        btnTestSound?.addEventListener('click', () => {
            const snd = selSound ? selSound.value : 'chime';
            const vol = slVol ? parseInt(slVol.value) : 50;
            playAudioPreset(snd, vol);
        });

        btnSave?.addEventListener('click', () => {
            userSettings.bullScoreTh = parseInt(slBull.value);
            userSettings.bearScoreTh = parseInt(slBear.value);
            userSettings.minNetBreadth = parseInt(slNet.value);
            userSettings.minVwapPct = parseInt(slVwap.value);
            userSettings.chartDisplayMode = selDispMode ? selDispMode.value : 'high_conviction';
            userSettings.signalCooldownBars = slCooldown ? parseInt(slCooldown.value) : 8;
            userSettings.audioSound = selSound ? selSound.value : 'chime';
            userSettings.audioVolume = slVol ? parseInt(slVol.value) : 50;
            userSettings.requireTfConfirm = chkTf.checked;
            userSettings.blockDivergence = chkDiv.checked;
            userSettings.onlyFreshTriggers = chkFresh ? chkFresh.checked : true;
            userSettings.enableAudio = chkAudio.checked;

            localStorage.setItem('nifty_participation_settings', JSON.stringify(userSettings));
            syncSettingsUI();
            modal.classList.add('hidden');

            // Instantly re-evaluate markers and decision engine
            evaluateAndRenderSignalMarkers();
            if (activeTimeIndex >= 0) syncActiveIndex(activeTimeIndex);
        });

        btnReset?.addEventListener('click', () => {
            userSettings = { ...DEFAULT_SETTINGS };
            localStorage.removeItem('nifty_participation_settings');
            syncSettingsUI();
        });

        syncSettingsUI();
    }

    // ════════════ LIGHTWEIGHT CHARTS SETUP (3 FULL-HEIGHT CHARTS) ════════════
    function initLightweightCharts() {
        const pContainer = document.getElementById('chart-primary-container');
        const oContainer = document.getElementById('chart-oscillator-container');
        const sContainer = document.getElementById('chart-secondary-container');
        if (!pContainer || !oContainer || !sContainer) return;

        // Apply saved heights to containers and select dropdowns
        pContainer.style.height = `${currentHeight5m}px`;
        sContainer.style.height = `${currentHeight15m}px`;

        const sel5m = document.getElementById('select-height-5m');
        if (sel5m) {
            sel5m.value = currentHeight5m;
            sel5m.addEventListener('change', (e) => applyChartHeight('5m', e.target.value));
        }
        const sel15m = document.getElementById('select-height-15m');
        if (sel15m) {
            sel15m.value = currentHeight15m;
            sel15m.addEventListener('change', (e) => applyChartHeight('15m', e.target.value));
        }

        // 1. Primary Candlestick Chart (5m Full Width)
        chartPrimary = LightweightCharts.createChart(pContainer, {
            height: currentHeight5m,
            layout: { background: { color: '#131722' }, textColor: '#9ca3af' },
            grid: { vertLines: { color: '#1a2234' }, horzLines: { color: '#1a2234' } },
            crosshair: { mode: LightweightCharts.CrosshairMode.Normal },
            rightPriceScale: { borderColor: '#1f293d' },
            timeScale: {
                borderColor: '#1f293d',
                timeVisible: true,
                secondsVisible: false,
                barSpacing: 10,
                minBarSpacing: 3,
                fixLeftEdge: false,
                fixRightEdge: false,
            },
            handleScroll: { mouseWheel: true, pressedMouseMove: true },
            handleScale: { axisPressedMouseMove: true, mouseWheel: true, pinch: true },
        });

        candleSeriesPrimary = chartPrimary.addCandlestickSeries({
            upColor: '#10b981', downColor: '#ef4444', borderVisible: false,
            wickUpColor: '#10b981', wickDownColor: '#ef4444'
        });

        chartPrimary.timeScale().subscribeVisibleTimeRangeChange(updateDaySeparators);
        chartPrimary.timeScale().subscribeVisibleLogicalRangeChange(updateDaySeparators);

        // 2. Secondary Timeframe Candlestick Chart (15m Full Width)
        chartSecondary = LightweightCharts.createChart(sContainer, {
            height: currentHeight15m,
            layout: { background: { color: '#131722' }, textColor: '#9ca3af' },
            grid: { vertLines: { color: '#1a2234' }, horzLines: { color: '#1a2234' } },
            crosshair: { mode: LightweightCharts.CrosshairMode.Normal },
            rightPriceScale: { borderColor: '#1f293d' },
            timeScale: {
                borderColor: '#1f293d',
                timeVisible: true,
                secondsVisible: false,
                barSpacing: 10,
                minBarSpacing: 3,
                fixLeftEdge: false,
                fixRightEdge: false,
            },
            handleScroll: { mouseWheel: true, pressedMouseMove: true },
            handleScale: { axisPressedMouseMove: true, mouseWheel: true, pinch: true },
        });

        candleSeriesSecondary = chartSecondary.addCandlestickSeries({
            upColor: '#a855f7', downColor: '#f43f5e', borderVisible: false,
            wickUpColor: '#a855f7', wickDownColor: '#f43f5e'
        });

        chartSecondary.timeScale().subscribeVisibleTimeRangeChange(updateDaySeparators);
        chartSecondary.timeScale().subscribeVisibleLogicalRangeChange(updateDaySeparators);

        // 3. Participation Oscillator & Historical Trace Chart (Right Column, Height: 210px)
        chartOscillator = LightweightCharts.createChart(oContainer, {
            height: 210,
            layout: { background: { color: '#131722' }, textColor: '#9ca3af' },
            grid: { vertLines: { color: '#1a2234' }, horzLines: { color: '#1a2234' } },
            crosshair: { mode: LightweightCharts.CrosshairMode.Normal },
            rightPriceScale: { borderColor: '#1f293d', scaleMargins: { top: 0.1, bottom: 0.1 } },
            timeScale: {
                borderColor: '#1f293d',
                timeVisible: true,
                secondsVisible: false,
                barSpacing: 10,
                minBarSpacing: 3,
                fixLeftEdge: false,
                fixRightEdge: false,
            },
            handleScroll: { mouseWheel: true, pressedMouseMove: true },
            handleScale: { axisPressedMouseMove: true, mouseWheel: true, pinch: true },
        });

        // Add Extreme Bands as horizontal price lines on Oscillator
        oscLineScore = chartOscillator.addAreaSeries({
            topColor: 'rgba(99, 102, 241, 0.4)',
            bottomColor: 'rgba(99, 102, 241, 0.02)',
            lineColor: '#818cf8',
            lineWidth: 2,
            priceScaleId: 'right',
        });

        oscLineNetBreadth = chartOscillator.addLineSeries({
            color: '#34d399',
            lineWidth: 1.5,
            priceScaleId: 'right',
        });

        oscLineVwap = chartOscillator.addLineSeries({
            color: '#fbbf24',
            lineWidth: 1.5,
            lineStyle: LightweightCharts.LineStyle.Dotted,
            priceScaleId: 'right',
        });

        // Visual threshold price lines on Oscillator
        oscLineScore.createPriceLine({
            price: 75,
            color: '#10b981',
            lineWidth: 1,
            lineStyle: LightweightCharts.LineStyle.Dashed,
            axisLabelVisible: true,
            title: 'BULL EXTREME (≥75)',
        });
        oscLineScore.createPriceLine({
            price: 25,
            color: '#f43f5e',
            lineWidth: 1,
            lineStyle: LightweightCharts.LineStyle.Dashed,
            axisLabelVisible: true,
            title: 'BEAR EXTREME (≤25)',
        });
        oscLineScore.createPriceLine({
            price: 50,
            color: '#64748b',
            lineWidth: 1,
            lineStyle: LightweightCharts.LineStyle.Dotted,
            axisLabelVisible: false,
            title: 'EQUILIBRIUM (50)',
        });

        // Click handler on Primary Chart to sync time across all charts
        chartPrimary.subscribeClick((param) => {
            if (param && param.time && timestampsList && timestampsList.length > 0) {
                const candle = engineData?.nifty_candles_p?.find(c => Number(c.timestamp) === Number(param.time));
                if (candle) {
                    const idx = timestampsList.indexOf(candle.time_str);
                    if (idx >= 0) syncActiveIndex(idx);
                }
            }
        });

        // Click handler on Secondary 15m Chart to sync time
        chartSecondary.subscribeClick((param) => {
            if (param && param.time && timestampsList && timestampsList.length > 0) {
                const sCandle = engineData?.nifty_candles_s?.find(c => Number(c.timestamp) === Number(param.time));
                if (sCandle) {
                    const idx = timestampsList.findIndex(t => t >= sCandle.time_str);
                    if (idx >= 0) syncActiveIndex(idx);
                }
            }
        });

        window.addEventListener('resize', () => {
            if (chartPrimary && pContainer) chartPrimary.resize(pContainer.clientWidth, currentHeight5m);
            if (chartSecondary && sContainer) chartSecondary.resize(sContainer.clientWidth, currentHeight15m);
            if (chartOscillator && oContainer) chartOscillator.resize(oContainer.clientWidth, 210);
        });
    }

    // ════════════ AUTO-REFRESH & POLLING ════════════
    let autoRefreshSec = parseInt(localStorage.getItem('nifty_pe_autorefresh_sec') || '15');
    let autoRefreshRemaining = autoRefreshSec;
    let autoRefreshTimer = null;

    function initAutoRefresh() {
        const select = document.getElementById('auto-refresh-select');
        if (select) {
            select.value = autoRefreshSec.toString();
            select.addEventListener('change', (e) => {
                autoRefreshSec = parseInt(e.target.value) || 0;
                localStorage.setItem('nifty_pe_autorefresh_sec', autoRefreshSec.toString());
                autoRefreshRemaining = autoRefreshSec;
                updateAutoRefreshUI();
            });
        }

        updateAutoRefreshUI();

        if (autoRefreshTimer) clearInterval(autoRefreshTimer);
        autoRefreshTimer = setInterval(() => {
            // Only count down if auto-refresh is active, viewing today, and not playing historical replay
            if (autoRefreshSec <= 0 || isPlaying || (selectedDate && selectedDate !== TODAY_DATE)) {
                updateAutoRefreshUI();
                return;
            }

            autoRefreshRemaining--;
            if (autoRefreshRemaining <= 0) {
                autoRefreshRemaining = autoRefreshSec;
                loadEngineData(false, true);
            }
            updateAutoRefreshUI();
        }, 1000);
    }

    function updateAutoRefreshUI() {
        const countdown = document.getElementById('auto-refresh-countdown');
        const dot = document.getElementById('auto-refresh-dot');
        if (!countdown || !dot) return;

        if (selectedDate && selectedDate !== TODAY_DATE) {
            countdown.textContent = 'Hist';
            countdown.className = 'text-[10px] font-bold text-gray-500 min-w-[20px] text-right';
            dot.className = 'w-1.5 h-1.5 rounded-full bg-gray-600';
        } else if (autoRefreshSec <= 0) {
            countdown.textContent = 'Off';
            countdown.className = 'text-[10px] font-bold text-gray-500 min-w-[20px] text-right';
            dot.className = 'w-1.5 h-1.5 rounded-full bg-gray-600';
        } else if (isPlaying) {
            countdown.textContent = 'Pause';
            countdown.className = 'text-[10px] font-bold text-amber-400 min-w-[20px] text-right';
            dot.className = 'w-1.5 h-1.5 rounded-full bg-amber-400';
        } else {
            countdown.textContent = `${autoRefreshRemaining}s`;
            countdown.className = 'text-[10px] font-bold text-emerald-400 min-w-[20px] text-right';
            dot.className = 'w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse';
        }
    }

    // ════════════ FETCH DATA FROM API ════════════
    async function loadEngineData(isForceRefresh = false, isBackgroundAuto = false) {
        if (!isBackgroundAuto) {
            document.getElementById('chart-primary-loading')?.classList.remove('hidden');
            document.getElementById('chart-oscillator-loading')?.classList.remove('hidden');
            document.getElementById('chart-secondary-loading')?.classList.remove('hidden');
        }
        const spinner = document.getElementById('reload-spinner');
        if (spinner) spinner.classList.add('animate-spin');

        try {
            const refreshParam = (isForceRefresh || isBackgroundAuto) ? '&refresh=1' : '';
            const res = await fetch(`/api/nifty-participation-engine/data?date=${selectedDate}&tf1=${primaryTf}&tf2=${secondaryTf}&basis=${activeBasis}${refreshParam}&_t=${Date.now()}`);
            const data = await res.json();

            if (data && data.success) {
                const wasAtLatest = timestampsList.length === 0 || activeTimeIndex >= timestampsList.length - 1;
                engineData = data;
                renderSessionHeader();
                renderAllCharts(isBackgroundAuto);
                evaluateAndRenderSignalMarkers();

                timestampsList = Object.keys(data.primary?.timeline || {});
                const slider = document.getElementById('timeline-slider');
                if (slider && timestampsList.length > 0) {
                    slider.max = timestampsList.length - 1;
                    document.getElementById('slider-start-time').textContent = timestampsList[0];
                    document.getElementById('slider-end-time').textContent = timestampsList[timestampsList.length - 1];
                }

                // If user was viewing latest candle or initial load, snap to newest candle
                if (wasAtLatest || !isBackgroundAuto) {
                    const targetIdx = timestampsList.length > 0 ? timestampsList.length - 1 : 0;
                    syncActiveIndex(targetIdx);
                } else {
                    syncActiveIndex(activeTimeIndex);
                }
            }
        } catch (err) {
            console.error('Failed to load participation engine data:', err);
        } finally {
            if (!isBackgroundAuto) {
                document.getElementById('chart-primary-loading')?.classList.add('hidden');
                document.getElementById('chart-oscillator-loading')?.classList.add('hidden');
                document.getElementById('chart-secondary-loading')?.classList.add('hidden');
            }
            if (spinner) spinner.classList.remove('animate-spin');
        }
    }

    function renderSessionHeader() {
        if (!engineData) return;
        const sum = engineData.session_summary;
        const heroSpot = document.getElementById('hero-spot-price');
        const heroChg = document.getElementById('hero-spot-change');

        if (heroSpot) heroSpot.textContent = sum.latest_price ? Number(sum.latest_price).toFixed(2) : '--';
        if (heroChg) {
            const sign = sum.change_pts >= 0 ? '+' : '';
            heroChg.textContent = `${sign}${sum.change_pts} (${sign}${sum.change_pct}%)`;
            heroChg.className = sum.change_pts >= 0 ? 'text-[11px] font-bold text-emerald-400' : 'text-[11px] font-bold text-rose-400';
        }

        const liveDot = document.getElementById('live-dot');
        const liveText = document.getElementById('live-status-text');
        if (engineData.is_live) {
            liveDot.className = 'w-2 h-2 rounded-full bg-emerald-400 live-dot';
            liveText.textContent = 'LIVE TRADING';
            liveText.className = 'text-emerald-400';
        } else if (engineData.is_pre_market) {
            liveDot.className = 'w-2 h-2 rounded-full bg-amber-400 live-dot';
            liveText.textContent = 'PRE-MARKET';
            liveText.className = 'text-amber-400';
        } else {
            liveDot.className = 'w-2 h-2 rounded-full bg-gray-500';
            liveText.textContent = 'MARKET CLOSED';
            liveText.className = 'text-gray-400';
        }

        document.getElementById('chart1-label').textContent = `NIFTY 50 (${primaryTf}m Primary Chart)`;
        document.getElementById('chart2-label').textContent = `NIFTY 50 (${secondaryTf}m Broader Structure)`;
    }

    // ════════════ HELPER: ADJUST CHART TIMESCALE (MATCHING NIFTY MULTI-TF) ════════════
    function adjustChartTimeScale(chart, todayCandles, yesterdayCandles = [], tf = 5) {
        if (!chart || !todayCandles || todayCandles.length === 0) return;

        const yCount = Array.isArray(yesterdayCandles) ? yesterdayCandles.length : 0;
        const tCount = todayCandles.length;
        const tfNum = parseInt(tf) || 5;
        const tfBarsMap = { 1: 375, 3: 125, 5: 75, 15: 25, 30: 13, 60: 7, 375: 1 };
        const sessionBars = tfBarsMap[tfNum] || (tfNum === 15 ? 25 : 75);

        // Anchor start at 0 so yesterday's afternoon context sits on the left
        const fromIndex = 0;
        // Total range shows yesterday's bars + today's full session (sessionBars) + 5 bars buffer
        const toIndex = yCount + Math.max(sessionBars, tCount + 5);

        requestAnimationFrame(() => {
            try {
                chart.timeScale().applyOptions({
                    minBarSpacing: 3,
                    fixLeftEdge: false,
                    fixRightEdge: false,
                });

                chart.timeScale().setVisibleLogicalRange({
                    from: fromIndex,
                    to: toIndex,
                });
                updateDaySeparators();
            } catch (e) {
                console.warn('adjustChartTimeScale warning:', e);
            }
        });
    }

    function updateDaySeparators() {
        const isLiveDate = (selectedDate === TODAY_DATE);
        const labelText = isLiveDate ? 'TODAY' : selectedDate;

        // Chart 1 Day Separator
        const sep1 = document.getElementById('chart1-day-separator');
        const cont1 = document.getElementById('chart-primary-container');
        if (chartPrimary && sep1 && cont1) {
            try {
                const timeTd = window._chart1TodayFirstTime;
                const timeYd = window._chart1YesterdayLastTime;
                let x = null;
                const barSpacing = (typeof chartPrimary.timeScale?.().options === 'function' ? chartPrimary.timeScale().options().barSpacing : 10) || 10;

                if (timeTd && timeYd) {
                    const xTd = chartPrimary.timeScale().timeToCoordinate(timeTd);
                    const xYd = chartPrimary.timeScale().timeToCoordinate(timeYd);
                    if (xTd !== null && xYd !== null) x = (xTd + xYd) / 2;
                    else if (xTd !== null) x = xTd - (barSpacing / 2);
                    else if (xYd !== null) x = xYd + (barSpacing / 2);
                } else if (timeTd) {
                    const xTd = chartPrimary.timeScale().timeToCoordinate(timeTd);
                    if (xTd !== null) x = xTd - (barSpacing / 2);
                }

                const maxW = cont1.clientWidth || 99999;
                if (x !== null && !isNaN(x) && x >= 0 && x <= maxW) {
                    sep1.style.left = `${Math.round(x)}px`;
                    const lbl = document.getElementById('chart1-day-sep-label');
                    if (lbl) lbl.textContent = labelText;
                    sep1.classList.remove('hidden');
                } else {
                    sep1.classList.add('hidden');
                }
            } catch (e) {
                sep1.classList.add('hidden');
            }
        }

        // Chart 2 Day Separator
        const sep2 = document.getElementById('chart2-day-separator');
        const cont2 = document.getElementById('chart-secondary-container');
        if (chartSecondary && sep2 && cont2) {
            try {
                const timeTd = window._chart2TodayFirstTime;
                const timeYd = window._chart2YesterdayLastTime;
                let x = null;
                const barSpacing = (typeof chartSecondary.timeScale?.().options === 'function' ? chartSecondary.timeScale().options().barSpacing : 10) || 10;

                if (timeTd && timeYd) {
                    const xTd = chartSecondary.timeScale().timeToCoordinate(timeTd);
                    const xYd = chartSecondary.timeScale().timeToCoordinate(timeYd);
                    if (xTd !== null && xYd !== null) x = (xTd + xYd) / 2;
                    else if (xTd !== null) x = xTd - (barSpacing / 2);
                    else if (xYd !== null) x = xYd + (barSpacing / 2);
                } else if (timeTd) {
                    const xTd = chartSecondary.timeScale().timeToCoordinate(timeTd);
                    if (xTd !== null) x = xTd - (barSpacing / 2);
                }

                const maxW = cont2.clientWidth || 99999;
                if (x !== null && !isNaN(x) && x >= 0 && x <= maxW) {
                    sep2.style.left = `${Math.round(x)}px`;
                    const lbl = document.getElementById('chart2-day-sep-label');
                    if (lbl) lbl.textContent = labelText;
                    sep2.classList.remove('hidden');
                } else {
                    sep2.classList.add('hidden');
                }
            } catch (e) {
                sep2.classList.add('hidden');
            }
        }
    }

    // ════════════ RENDER CHARTS & THE HISTORICAL TRACE ════════════
    function renderAllCharts(isBackgroundAuto = false) {
        if (!engineData) return;

        // 1. Primary Candlestick Series (combining yesterday context + today)
        const todayCandlesP = (engineData.nifty_candles_p || []).map(c => ({
            time: Number(c.timestamp || c.time),
            open: Number(c.open),
            high: Number(c.high),
            low: Number(c.low),
            close: Number(c.close),
            time_str: c.time_str,
        })).filter(c => c.time && !isNaN(c.time));

        const firstTodayTimeP = todayCandlesP[0]?.time || 0;
        const rawYesterdayP = (engineData.yesterday_candles_p || []).map(c => ({
            time: Number(c.timestamp || c.time),
            open: Number(c.open),
            high: Number(c.high),
            low: Number(c.low),
            close: Number(c.close),
            time_str: c.time_str,
        })).filter(c => c.time && !isNaN(c.time) && (firstTodayTimeP === 0 || c.time < firstTodayTimeP));

        // Keep last 10 candles of yesterday for clean afternoon context (matching multi-timeframe monitor)
        const yesterdayCandlesP = rawYesterdayP.slice(-10);

        window._chart1TodayFirstTime = todayCandlesP[0]?.time;
        window._chart1YesterdayLastTime = yesterdayCandlesP[yesterdayCandlesP.length - 1]?.time;

        const allPrimaryCandles = [...yesterdayCandlesP, ...todayCandlesP];

        if (candleSeriesPrimary && allPrimaryCandles.length > 0) {
            candleSeriesPrimary.setData(allPrimaryCandles);
            if (!isBackgroundAuto) {
                adjustChartTimeScale(chartPrimary, todayCandlesP, yesterdayCandlesP, primaryTf);
            }
        }

        // 2. Secondary Candlestick Series (combining yesterday context + today)
        const todayCandlesS = (engineData.nifty_candles_s || []).map(c => ({
            time: Number(c.timestamp || c.time),
            open: Number(c.open),
            high: Number(c.high),
            low: Number(c.low),
            close: Number(c.close),
            time_str: c.time_str,
        })).filter(c => c.time && !isNaN(c.time));

        const firstTodayTimeS = todayCandlesS[0]?.time || 0;
        const rawYesterdayS = (engineData.yesterday_candles_s || []).map(c => ({
            time: Number(c.timestamp || c.time),
            open: Number(c.open),
            high: Number(c.high),
            low: Number(c.low),
            close: Number(c.close),
            time_str: c.time_str,
        })).filter(c => c.time && !isNaN(c.time) && (firstTodayTimeS === 0 || c.time < firstTodayTimeS));

        // Keep last 6 candles of yesterday for 15m
        const yesterdayCandlesS = rawYesterdayS.slice(-6);

        window._chart2TodayFirstTime = todayCandlesS[0]?.time;
        window._chart2YesterdayLastTime = yesterdayCandlesS[yesterdayCandlesS.length - 1]?.time;

        const allSecondaryCandles = [...yesterdayCandlesS, ...todayCandlesS];

        if (candleSeriesSecondary && allSecondaryCandles.length > 0) {
            candleSeriesSecondary.setData(allSecondaryCandles);
            if (!isBackgroundAuto) {
                adjustChartTimeScale(chartSecondary, todayCandlesS, yesterdayCandlesS, secondaryTf);
            }
        }

        // 3. Historical Trace Oscillator Series (Using today's candles)
        const timeline = engineData.primary?.timeline || {};
        const alignment = engineData.consistency?.alignment_timeline || {};

        const scorePoints = [];
        const netBreadthPoints = [];
        const vwapPoints = [];

        todayCandlesP.forEach(c => {
            const t = c.time_str;
            const b = timeline[t];
            const a = alignment[t];
            const ts = Number(c.time || c.timestamp);
            if (!ts || isNaN(ts)) return;

            const score = a ? Number(a.composite_score) : (b ? Math.round(50 + b.net_breadth) : 50);
            scorePoints.push({ time: ts, value: score });

            const netBreadthScaled = b ? Math.round(50 + Number(b.net_breadth)) : 50;
            netBreadthPoints.push({ time: ts, value: netBreadthScaled });

            const vwapPct = b ? Number(b.vwap_participation_pct) : 50;
            vwapPoints.push({ time: ts, value: vwapPct });
        });

        if (oscLineScore && scorePoints.length > 0) {
            oscLineScore.setData(scorePoints);
        }
        if (oscLineNetBreadth && netBreadthPoints.length > 0) {
            oscLineNetBreadth.setData(netBreadthPoints);
        }
        if (oscLineVwap && vwapPoints.length > 0) {
            oscLineVwap.setData(vwapPoints);
        }
        if (chartOscillator && scorePoints.length > 0) {
            chartOscillator.timeScale().fitContent();
        }

        setTimeout(updateDaySeparators, 60);
    }

    // ════════════ EVALUATE & PLOT HIGH-CONFIDENCE SIGNAL MARKERS (5M & 15M) ════════════
    function evaluateAndRenderSignalMarkers() {
        if (!engineData) return;

        const timeline = engineData.primary?.timeline || {};
        const alignment = engineData.consistency?.alignment_timeline || {};
        const divergence = engineData.divergence?.divergence_timeline || {};
        const primaryCandles = engineData.nifty_candles_p || [];

        // 1. Plot Primary 5m Chart Markers
        if (candleSeriesPrimary) {
            const markers = [];
            let lastTradeDirection = null; // 'BULL' or 'BEAR'
            let barsSinceLastEntry = 999;
            let barsSinceLastDiv = 999;

            const cooldownBars = Number(userSettings.signalCooldownBars) || 8;
            const displayMode = userSettings.chartDisplayMode || 'high_conviction';
            const isFullDiagnostic = (displayMode === 'all_signals' || displayMode === 'all_micro');
            const isTrendOnly = (displayMode === 'trend_only');
            // In Full Diagnostic, allow tight 2-bar spacing so user can inspect every qualified pulse
            const effectiveCooldown = isFullDiagnostic ? 2 : cooldownBars;

            primaryCandles.forEach(c => {
                const t = c.time_str;
                const b = timeline[t];
                const a = alignment[t];
                const div = divergence[t];
                if (!b || !a) return;

                const score = a.composite_score;
                const net = b.net_breadth;
                const vwap = b.vwap_participation_pct;

                // Timeframe confirmation directional checks
                const tfBullOk = !userSettings.requireTfConfirm || (a.s_state !== 'BEARISH');
                const tfBearOk = !userSettings.requireTfConfirm || (a.s_state !== 'BULLISH');

                // Divergence Trap specific filtering
                const isBullDivTrap = userSettings.blockDivergence && div && (div.type === 'BEARISH_DIVERGENCE_WARNING');
                const isBearDivTrap = userSettings.blockDivergence && div && (div.type === 'BULLISH_DIVERGENCE_WARNING');

                const isBullSignal = (score >= userSettings.bullScoreTh) && (net >= userSettings.minNetBreadth) && (vwap >= userSettings.minVwapPct) && tfBullOk && !isBullDivTrap;
                const isBearSignal = (score <= userSettings.bearScoreTh) && (net <= -userSettings.minNetBreadth) && (vwap <= (100 - userSettings.minVwapPct)) && tfBearOk && !isBearDivTrap;

                barsSinceLastEntry++;
                barsSinceLastDiv++;

                // Determine if a divergence qualifies as a High-Conviction Structural event
                let isHighConvictionDiv = false;
                if (div) {
                    const hasOf = Boolean(div.is_of_confirmed);
                    const ofDelta = Math.abs(div.order_flow?.delta || 0);
                    const priceSlope = Math.abs(div.price_slope || 0);
                    isHighConvictionDiv = hasOf || (ofDelta >= 25000 && priceSlope >= 20.0);
                }

                if (isBullSignal) {
                    const canEnter = (lastTradeDirection !== 'BULL') || (barsSinceLastEntry >= effectiveCooldown);
                    if (!userSettings.onlyFreshTriggers || canEnter) {
                        markers.push({
                            time: Number(c.timestamp),
                            position: 'belowBar',
                            color: '#10b981',
                            shape: 'arrowUp',
                            text: `BUY CE (${Math.round(score)})`,
                        });
                        barsSinceLastEntry = 0;
                        lastTradeDirection = 'BULL';
                    }
                } else if (isBearSignal) {
                    const canEnter = (lastTradeDirection !== 'BEAR') || (barsSinceLastEntry >= effectiveCooldown);
                    if (!userSettings.onlyFreshTriggers || canEnter) {
                        markers.push({
                            time: Number(c.timestamp),
                            position: 'aboveBar',
                            color: '#ef4444',
                            shape: 'arrowDown',
                            text: `BUY PE (${Math.round(score)})`,
                        });
                        barsSinceLastEntry = 0;
                        lastTradeDirection = 'BEAR';
                    }
                }

                // Plot Traps / Absorptions according to Display Mode
                if (!isTrendOnly) {
                    if (isBullDivTrap) {
                        const shouldShow = isFullDiagnostic || (isHighConvictionDiv && barsSinceLastDiv >= 4);
                        if (shouldShow) {
                            const deltaK = (div && div.order_flow && div.order_flow.delta) ? Math.round(div.order_flow.delta / 1000) : 0;
                            const deltaTag = Math.abs(deltaK) >= 10 ? ` (+${deltaK}k Δ)` : '';
                            markers.push({
                                time: Number(c.timestamp),
                                position: 'aboveBar',
                                color: '#f59e0b',
                                shape: 'circle',
                                text: `TRAP ⚠️${deltaTag}`,
                            });
                            barsSinceLastDiv = 0;
                        }
                    } else if (isBearDivTrap) {
                        const shouldShow = isFullDiagnostic || (isHighConvictionDiv && barsSinceLastDiv >= 4);
                        if (shouldShow) {
                            const deltaK = (div && div.order_flow && div.order_flow.delta) ? Math.round(div.order_flow.delta / 1000) : 0;
                            const deltaTag = Math.abs(deltaK) >= 10 ? ` (${deltaK}k Δ)` : '';
                            markers.push({
                                time: Number(c.timestamp),
                                position: 'belowBar',
                                color: '#06b6d4',
                                shape: 'circle',
                                text: `ABSORB ⚡${deltaTag}`,
                            });
                            barsSinceLastDiv = 0;
                        }
                    }
                }
            });

            candleSeriesPrimary.setMarkers(markers);
        }

        // 2. Plot Secondary 15m Chart Markers (Higher-Timeframe Alignment & Signals)
        if (candleSeriesSecondary) {
            const secondaryCandles = engineData.nifty_candles_s || [];
            const secondaryTimeline = engineData.secondary?.timeline || {};
            const secondaryMarkers = [];
            let last15mTradeDirection = null; // 'BULL' or 'BEAR'
            let barsSince15mEntry = 999;

            secondaryCandles.forEach(sc => {
                const st = sc.time_str;
                const sb = secondaryTimeline[st];
                if (!sb) return;

                const sNet = sb.net_breadth;
                const sVwap = sb.vwap_participation_pct;
                const sTs = Number(sc.timestamp);
                if (!sTs) return;

                // High-conviction threshold evaluation for 15M broader structure
                const is15mBull = (sNet >= userSettings.minNetBreadth) && (sVwap >= userSettings.minVwapPct);
                const is15mBear = (sNet <= -userSettings.minNetBreadth) && (sVwap <= (100 - userSettings.minVwapPct));

                barsSince15mEntry++;

                // On 15M broader structure, prevent arrow spam by enforcing higher cooldown (8 bars = 2 hrs) or pure trend reversal
                const cooldown15m = Math.max(8, Number(userSettings.signalCooldownBars) || 8);

                if (is15mBull) {
                    const canEnter = (last15mTradeDirection !== 'BULL') || (barsSince15mEntry >= cooldown15m);
                    if (!userSettings.onlyFreshTriggers || canEnter) {
                        secondaryMarkers.push({
                            time: sTs,
                            position: 'belowBar',
                            color: '#10b981',
                            shape: 'arrowUp',
                            text: `15M BUY CE (+${sNet})`,
                        });
                        barsSince15mEntry = 0;
                        last15mTradeDirection = 'BULL';
                    }
                } else if (is15mBear) {
                    const canEnter = (last15mTradeDirection !== 'BEAR') || (barsSince15mEntry >= cooldown15m);
                    if (!userSettings.onlyFreshTriggers || canEnter) {
                        secondaryMarkers.push({
                            time: sTs,
                            position: 'aboveBar',
                            color: '#ef4444',
                            shape: 'arrowDown',
                            text: `15M BUY PE (${sNet})`,
                        });
                        barsSince15mEntry = 0;
                        last15mTradeDirection = 'BEAR';
                    }
                }
            });

            candleSeriesSecondary.setMarkers(secondaryMarkers);
        }
    }

    // ════════════ SYNCHRONIZE ACTIVE CANDLE OBSERVATION & ACTION DESK ════════════
    function syncActiveIndex(index) {
        if (!engineData || !timestampsList || timestampsList.length === 0) return;
        if (index < 0) index = 0;
        if (index >= timestampsList.length) index = timestampsList.length - 1;

        activeTimeIndex = index;
        const activeTime = timestampsList[index];

        // Update Slider UI
        const slider = document.getElementById('timeline-slider');
        if (slider) slider.value = index;

        const bubble = document.getElementById('slider-time-bubble');
        const bubbleText = document.getElementById('bubble-time-text');
        if (bubble && bubbleText && timestampsList.length > 1) {
            const pct = (index / (timestampsList.length - 1)) * 100;
            bubble.style.left = `${pct}%`;
            bubbleText.textContent = activeTime;
        }

        // Active primary candle
        const primaryCandles = engineData.nifty_candles_p || [];
        const pCandle = primaryCandles.find(c => c.time_str === activeTime) || primaryCandles[index];
        if (pCandle) {
            document.getElementById('chart1-candle-time').textContent = pCandle.time_str;
            document.getElementById('chart1-open').textContent = Number(pCandle.open).toFixed(2);
            document.getElementById('chart1-close').textContent = Number(pCandle.close).toFixed(2);
            const moveEl = document.getElementById('chart1-move');
            const sign = pCandle.move_pts >= 0 ? '+' : '';
            moveEl.textContent = `${sign}${Number(pCandle.move_pts).toFixed(2)} (${sign}${Number(pCandle.move_pct).toFixed(2)}%)`;
            moveEl.className = pCandle.move_pts >= 0 ? 'font-bold text-emerald-400' : 'font-bold text-rose-400';
        }

        // Active secondary 15m candle sync
        const secondaryCandles = engineData.nifty_candles_s || [];
        const sCandle = secondaryCandles.filter(c => c.time_str <= activeTime).slice(-1)[0] || secondaryCandles[0];
        if (sCandle) {
            const sTimeEl = document.getElementById('chart2-candle-time');
            if (sTimeEl) sTimeEl.textContent = sCandle.time_str;
            const sCloseEl = document.getElementById('chart2-close');
            if (sCloseEl) sCloseEl.textContent = Number(sCandle.close).toFixed(2);
            const sMoveEl = document.getElementById('chart2-move');
            if (sMoveEl) {
                const sSign = sCandle.move_pts >= 0 ? '+' : '';
                sMoveEl.textContent = `${sSign}${Number(sCandle.move_pts).toFixed(2)} (${sSign}${Number(sCandle.move_pct).toFixed(2)}%)`;
                sMoveEl.className = sCandle.move_pts >= 0 ? 'font-bold text-emerald-400' : 'font-bold text-rose-400';
            }
        }

        const b = engineData.primary?.timeline?.[activeTime];
        const a = engineData.consistency?.alignment_timeline?.[activeTime];
        const div = engineData.divergence?.divergence_timeline?.[activeTime];

        // ════════════ 1. ACTIONABLE TRADE DECISION DESK RESOLUTION ════════════
        if (b && a && pCandle) {
            resolveTradeDecision(b, a, div, pCandle);
        }

        // ════════════ 2. POPULATE COCKPIT TABS METRICS ════════════
        if (b) {
            document.getElementById('pane-net-breadth').textContent = b.net_breadth > 0 ? `+${b.net_breadth}` : b.net_breadth;
            document.getElementById('pane-cnt-bull').textContent = b.bullish_count;
            document.getElementById('pane-pct-bull').textContent = `${b.bullish_pct}%`;
            document.getElementById('pane-cnt-neu').textContent = b.neutral_count;
            document.getElementById('pane-pct-neu').textContent = `${b.neutral_pct}%`;
            document.getElementById('pane-cnt-bear').textContent = b.bearish_count;
            document.getElementById('pane-pct-bear').textContent = `${b.bearish_pct}%`;

            const barPos = document.getElementById('pane-net-bar-pos');
            const barNeg = document.getElementById('pane-net-bar-neg');
            if (b.net_breadth >= 0) {
                const w = Math.min(50, (b.net_breadth / 50) * 50);
                if (barPos) barPos.style.width = `${w}%`;
                if (barNeg) barNeg.style.width = '0%';
            } else {
                const w = Math.min(50, (Math.abs(b.net_breadth) / 50) * 50);
                if (barNeg) barNeg.style.width = `${w}%`;
                if (barPos) barPos.style.width = '0%';
            }

            // Strength
            document.getElementById('pane-strength-time').textContent = activeTime;
            document.getElementById('pane-vwap-pct').textContent = `${b.vwap_participation_pct}%`;
            document.getElementById('pane-vwap-cnt').textContent = `${b.vwap_above_count} / 50`;
            document.getElementById('pane-vwap-bar').style.width = `${b.vwap_participation_pct}%`;

            document.getElementById('pane-rs-pct').textContent = `${b.rs_participation_pct}%`;
            document.getElementById('pane-rs-cnt').textContent = `${b.rs_outperform_count} / 50`;
            document.getElementById('pane-rs-bar').style.width = `${b.rs_participation_pct}%`;

            // Momentum
            const dSign = b.breadth_delta > 0 ? '+' : '';
            const aSign = b.breadth_acceleration > 0 ? '+' : '';
            document.getElementById('pane-mom-delta').textContent = `${dSign}${b.breadth_delta}%`;
            document.getElementById('pane-mom-accel').textContent = `${aSign}${b.breadth_acceleration}%`;

            const momBadge = document.getElementById('pane-mom-badge');
            if (momBadge) {
                momBadge.textContent = b.momentum_regime.replace(/_/g, ' ');
                momBadge.className = b.momentum_regime.includes('EXPANSION')
                    ? 'px-2 py-0.5 rounded text-[9px] font-bold bg-emerald-950 text-emerald-300 border border-emerald-600/50'
                    : (b.momentum_regime.includes('CONTRACTION')
                        ? 'px-2 py-0.5 rounded text-[9px] font-bold bg-rose-950 text-rose-300 border border-rose-600/50'
                        : 'px-2 py-0.5 rounded text-[9px] font-bold bg-gray-800 text-gray-300');
            }

            // Sectors Breakdown List
            renderSectorsList(b.sector_summary || []);
        }

        if (a) {
            document.getElementById('pane-composite-val').textContent = a.composite_score;
            document.getElementById('pane-composite-bar').style.width = `${a.composite_score}%`;
            const compBadge = document.getElementById('pane-composite-badge');
            if (a.composite_score >= 70) {
                compBadge.textContent = 'BULLISH BIAS';
                compBadge.className = 'ml-2 px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-emerald-950 text-emerald-300 border border-emerald-600/40';
            } else if (a.composite_score <= 30) {
                compBadge.textContent = 'BEARISH BIAS';
                compBadge.className = 'ml-2 px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-rose-950 text-rose-300 border border-rose-600/40';
            } else {
                compBadge.textContent = 'NEUTRAL CHOP';
                compBadge.className = 'ml-2 px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-gray-800 text-gray-400';
            }

            document.getElementById('pane-15m-box').textContent = `15M: ${a.s_state}`;
            document.getElementById('pane-5m-box').textContent = `5M: ${a.p_state}`;
            document.getElementById('pane-tf-verdict').textContent = a.verdict_label;
            document.getElementById('pane-tf-score').textContent = `SCORE: ${a.consistency_score}`;
        }

        if (div) {
            document.getElementById('pane-div-badge').textContent = div.badge;
            document.getElementById('pane-div-desc').textContent = div.description;
            document.getElementById('pane-div-slope').textContent = `ΔPrice: ${div.price_slope > 0 ? '+' : ''}${div.price_slope} | ΔScore: ${div.score_slope > 0 ? '+' : ''}${div.score_slope}`;
            const divCard = document.getElementById('pane-div-card');
            if (div.type.includes('DIVERGENCE')) {
                divCard.className = 'p-3 rounded bg-amber-950/40 border border-amber-500/60 space-y-1';
            } else if (div.type.includes('CONFIRMED')) {
                divCard.className = 'p-3 rounded bg-emerald-950/30 border border-emerald-500/50 space-y-1';
            } else {
                divCard.className = 'p-3 rounded bg-gray-900/80 border border-gray-800 space-y-1';
            }

            // Populate 30-Depth Microstructure Card
            if (div.order_flow) {
                const of = div.order_flow;
                const deltaEl = document.getElementById('pane-of-delta');
                const buyEl = document.getElementById('pane-of-buy');
                const sellEl = document.getElementById('pane-of-sell');
                const cvdEl = document.getElementById('pane-of-cvd');
                const dynEl = document.getElementById('pane-of-dynamics-badge');
                const descEl = document.getElementById('pane-of-desc');

                if (deltaEl) {
                    const sign = of.delta > 0 ? '+' : '';
                    deltaEl.textContent = `${sign}${of.delta.toLocaleString()} Δ`;
                    deltaEl.className = of.delta > 0 ? 'text-xs font-black text-emerald-400' : (of.delta < 0 ? 'text-xs font-black text-rose-400' : 'text-xs font-black text-gray-300');
                }
                if (buyEl) buyEl.textContent = of.buy_qty.toLocaleString();
                if (sellEl) sellEl.textContent = of.sell_qty.toLocaleString();
                if (cvdEl) {
                    const cvdSign = of.cvd > 0 ? '+' : '';
                    cvdEl.textContent = `${cvdSign}${of.cvd.toLocaleString()}`;
                    cvdEl.className = of.cvd > 0 ? 'text-xs font-black text-emerald-400' : (of.cvd < 0 ? 'text-xs font-black text-rose-400' : 'text-xs font-black text-indigo-300');
                }
                if (dynEl) {
                    dynEl.textContent = of.dynamics.replace(/_/g, ' ');
                    if (of.dynamics === 'BULL_ABSORPTION') {
                        dynEl.className = 'px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-cyan-950 text-cyan-300 border border-cyan-500/60 animate-pulse';
                    } else if (of.dynamics === 'BEAR_TRAP') {
                        dynEl.className = 'px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-amber-950 text-amber-300 border border-amber-500/60 animate-pulse';
                    } else if (of.dynamics === 'BULL_EXPANSION') {
                        dynEl.className = 'px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-emerald-950 text-emerald-300 border border-emerald-500/60';
                    } else if (of.dynamics === 'BEAR_EXPANSION') {
                        dynEl.className = 'px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-rose-950 text-rose-300 border border-rose-500/60';
                    } else {
                        dynEl.className = 'px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-gray-800 text-gray-400';
                    }
                }
                if (descEl) {
                    if (of.dynamics === 'BULL_ABSORPTION') {
                        descEl.innerHTML = `<span class="text-cyan-300 font-bold">Passive Limit Bids Absorbing Market Sellers:</span> Smart money accumulating every sell order at bid depth.`;
                    } else if (of.dynamics === 'BEAR_TRAP') {
                        descEl.innerHTML = `<span class="text-amber-300 font-bold">Passive Limit Asks Absorbing Market Buyers:</span> Institutional ask walls absorbing retail breakout chase.`;
                    } else {
                        descEl.textContent = `Order Flow tracking: Total volume ${of.total_qty.toLocaleString()} contracts.`;
                    }
                }
            }
        }

        renderConstituentsTable();
    }

    // ════════════ RESOLVE ACTIONABLE TRADE DECISION & LEVELS ════════════
    function resolveTradeDecision(b, a, div, candle) {
        const heroCard = document.getElementById('trade-decision-hero-card');
        const confBadge = document.getElementById('decision-confidence-badge');
        const actionEl = document.getElementById('decision-signal-action');
        const contractEl = document.getElementById('decision-contract-text');
        const spotPrice = Number(candle.close || candle.open);

        const score = a.composite_score;
        const net = b.net_breadth;
        const vwap = b.vwap_participation_pct;

        const isBreadthBull = (score >= userSettings.bullScoreTh && net >= userSettings.minNetBreadth);
        const isBreadthBear = (score <= userSettings.bearScoreTh && net <= -userSettings.minNetBreadth);
        const isVwapBull = (vwap >= userSettings.minVwapPct);
        const isVwapBear = (vwap <= (100 - userSettings.minVwapPct));
        const isTfAlignedBull = (a.s_state !== 'BEARISH');
        const isTfAlignedBear = (a.s_state !== 'BULLISH');
        const isBullDivClean = !div || div.type !== 'BEARISH_DIVERGENCE_WARNING';
        const isBearDivClean = !div || div.type !== 'BULLISH_DIVERGENCE_WARNING';

        // 30-Depth Order Flow Microstructure check
        const of = (div && div.order_flow) ? div.order_flow : null;
        const isOfClean = !of || (score >= 50 ? of.dynamics !== 'BEAR_TRAP' : of.dynamics !== 'BULL_ABSORPTION');

        let metCount = 0;
        let isBullTrigger = false;
        let isBearTrigger = false;

        if (score >= 50) {
            if (isBreadthBull) metCount++;
            if (isVwapBull) metCount++;
            if (isTfAlignedBull) metCount++;
            if (isBullDivClean) metCount++;
            if (isOfClean) metCount++;
            isBullTrigger = (metCount >= 4 && isBreadthBull && isBullDivClean);
        } else {
            if (isBreadthBear) metCount++;
            if (isVwapBear) metCount++;
            if (isTfAlignedBear) metCount++;
            if (isBearDivClean) metCount++;
            if (isOfClean) metCount++;
            isBearTrigger = (metCount >= 4 && isBreadthBear && isBearDivClean);
        }

        document.getElementById('checklist-summary').textContent = `${metCount} / 5 Met`;
        updateCheckItem('check-breadth', (isBreadthBull || isBreadthBear), 'Breadth Extreme Met');
        updateCheckItem('check-vwap', (isVwapBull || isVwapBear), 'VWAP Dominance Met');
        updateCheckItem('check-tf', (isTfAlignedBull || isTfAlignedBear), '5m & 15m Aligned');
        updateCheckItem('check-div', (score >= 50 ? isBullDivClean : isBearDivClean), 'Divergence Clean');
        
        let ofLabel = '30-Depth Clean';
        if (of && of.has_data) {
            if (of.dynamics === 'BULL_ABSORPTION') ofLabel = '30-Depth Bull Absorption ⚡';
            else if (of.dynamics === 'BEAR_TRAP') ofLabel = '30-Depth Ask Wall Trap ⚠️';
            else if (of.dynamics === 'BULL_EXPANSION' || of.dynamics === 'BEAR_EXPANSION') ofLabel = '30-Depth Flow Aligned 🚀';
        }
        updateCheckItem('check-of', isOfClean, ofLabel);

        const atmStrike = Math.round(spotPrice / 50) * 50;

        if (isBullTrigger) {
            heroCard.className = 'cockpit-card p-3 shadow-xl border-2 transition-all duration-300 bg-gradient-to-br from-[#131722] via-[#0f241d] to-[#111624] glow-bull';
            confBadge.textContent = 'HIGH CONFIDENCE (BULL)';
            confBadge.className = 'px-2 py-0.5 rounded text-[10px] font-mono font-black bg-emerald-950 text-emerald-300 border border-emerald-500/80 animate-pulse';
            actionEl.innerHTML = `<span class="text-emerald-400">🚀 BUY CALL (CE)</span>`;
            contractEl.textContent = `NIFTY ${atmStrike} CE`;

            const candleLow = Number(candle.low || (spotPrice - 20));
            const sl = Math.round(candleLow - 5);
            const riskPts = Math.max(10, Math.round(spotPrice - sl));
            const tp1 = Math.round(spotPrice + 50);
            const tp2 = Math.round(spotPrice + 80);

            document.getElementById('trade-entry-spot').textContent = `₹${spotPrice.toFixed(1)}`;
            document.getElementById('trade-sl-val').textContent = `₹${sl} (-${riskPts})`;
            document.getElementById('trade-tp1-val').textContent = `₹${tp1} (+50)`;
            document.getElementById('trade-tp2-val').textContent = `₹${tp2} (+80)`;

            triggerAlertAudio();
        } else if (isBearTrigger) {
            heroCard.className = 'cockpit-card p-3 shadow-xl border-2 transition-all duration-300 bg-gradient-to-br from-[#131722] via-[#291118] to-[#111624] glow-bear';
            confBadge.textContent = 'HIGH CONFIDENCE (BEAR)';
            confBadge.className = 'px-2 py-0.5 rounded text-[10px] font-mono font-black bg-rose-950 text-rose-300 border border-rose-500/80 animate-pulse';
            actionEl.innerHTML = `<span class="text-rose-400">💥 BUY PUT (PE)</span>`;
            contractEl.textContent = `NIFTY ${atmStrike} PE`;

            const candleHigh = Number(candle.high || (spotPrice + 20));
            const sl = Math.round(candleHigh + 5);
            const riskPts = Math.max(10, Math.round(sl - spotPrice));
            const tp1 = Math.round(spotPrice - 50);
            const tp2 = Math.round(spotPrice - 80);

            document.getElementById('trade-entry-spot').textContent = `₹${spotPrice.toFixed(1)}`;
            document.getElementById('trade-sl-val').textContent = `₹${sl} (-${riskPts})`;
            document.getElementById('trade-tp1-val').textContent = `₹${tp1} (-50)`;
            document.getElementById('trade-tp2-val').textContent = `₹${tp2} (-80)`;

            triggerAlertAudio();
        } else if (div && div.type === 'BULLISH_DIVERGENCE_WARNING') {
            heroCard.className = 'cockpit-card p-3 shadow-xl border-2 transition-all duration-300 bg-gradient-to-br from-[#131722] via-[#09222b] to-[#111624] glow-cyan';
            confBadge.textContent = (div.is_of_confirmed || (of && of.dynamics === 'BULL_ABSORPTION')) ? '30-DEPTH BULL ABSORPTION' : 'INST ABSORPTION';
            confBadge.className = 'px-2 py-0.5 rounded text-[10px] font-mono font-black bg-cyan-950 text-cyan-300 border border-cyan-500/80 animate-pulse';
            actionEl.innerHTML = `<span class="text-cyan-400">⚡ INSTITUTIONAL ABSORPTION</span>`;
            contractEl.textContent = 'DO NOT SHORT / PREPARE CE';

            document.getElementById('trade-entry-spot').textContent = `₹${spotPrice.toFixed(1)}`;
            document.getElementById('trade-sl-val').textContent = '--';
            document.getElementById('trade-tp1-val').textContent = '--';
            document.getElementById('trade-tp2-val').textContent = '--';
        } else if (div && div.type === 'BEARISH_DIVERGENCE_WARNING') {
            heroCard.className = 'cockpit-card p-3 shadow-xl border-2 transition-all duration-300 bg-gradient-to-br from-[#131722] via-[#261e10] to-[#111624] glow-amber';
            confBadge.textContent = (div.is_of_confirmed || (of && of.dynamics === 'BEAR_TRAP')) ? '30-DEPTH ASK WALL TRAP' : 'DIVERGENCE TRAP';
            confBadge.className = 'px-2 py-0.5 rounded text-[10px] font-mono font-black bg-amber-950 text-amber-300 border border-amber-500/80';
            actionEl.innerHTML = `<span class="text-amber-400">⚠️ CAUTION / TRAP</span>`;
            contractEl.textContent = 'DO NOT CHASE CE';

            document.getElementById('trade-entry-spot').textContent = `₹${spotPrice.toFixed(1)}`;
            document.getElementById('trade-sl-val').textContent = '--';
            document.getElementById('trade-tp1-val').textContent = '--';
            document.getElementById('trade-tp2-val').textContent = '--';
        } else {
            heroCard.className = 'cockpit-card p-3 shadow-xl border border-gray-800 transition-all duration-300 bg-gradient-to-br from-[#131722] via-[#151c2e] to-[#111624]';
            confBadge.textContent = 'MONITORING';
            confBadge.className = 'px-2 py-0.5 rounded text-[10px] font-mono font-black bg-gray-800 text-gray-400 border border-gray-700';
            actionEl.innerHTML = `<span class="text-gray-400">WAIT / EQUILIBRIUM</span>`;
            contractEl.textContent = 'NO TRIGGER';

            document.getElementById('trade-entry-spot').textContent = `₹${spotPrice.toFixed(1)}`;
            document.getElementById('trade-sl-val').textContent = '--';
            document.getElementById('trade-tp1-val').textContent = '--';
            document.getElementById('trade-tp2-val').textContent = '--';
        }
    }

    function updateCheckItem(id, isPass, label) {
        const el = document.getElementById(id);
        if (!el) return;
        if (isPass) {
            el.innerHTML = `🟢 <span class="text-emerald-300">${label}</span>`;
        } else {
            el.innerHTML = `⚪ <span class="text-gray-500">${label}</span>`;
        }
    }

    // ════════════ COCKPIT TABS SWITCHING ════════════
    function switchCockpitTab(tabKey) {
        const tabs = ['pillars', 'alignment', 'inspector', 'sectors', 'playbook'];
        tabs.forEach(t => {
            const btn = document.getElementById(`tab-btn-${t}`);
            const pane = document.getElementById(`tab-pane-${t}`);
            if (t === tabKey) {
                btn?.classList.remove('border-transparent', 'text-gray-400');
                btn?.classList.add('border-indigo-500', 'text-indigo-300', 'font-black');
                pane?.classList.remove('hidden');
            } else {
                btn?.classList.remove('border-indigo-500', 'text-indigo-300', 'font-black');
                btn?.classList.add('border-transparent', 'text-gray-400');
                pane?.classList.add('hidden');
            }
        });
    }
    window.switchCockpitTab = switchCockpitTab;

    function renderSectorsList(sectors) {
        const container = document.getElementById('sectors-cockpit-list');
        if (!container) return;
        if (!sectors || sectors.length === 0) {
            container.innerHTML = '<div class="text-gray-500 italic py-2">No sector data available.</div>';
            return;
        }

        container.innerHTML = sectors.map(sec => {
            const sign = sec.pts > 0 ? '+' : '';
            const colorClass = sec.pts > 0 ? 'text-emerald-400' : (sec.pts < 0 ? 'text-rose-400' : 'text-gray-400');
            return `
                <div class="flex items-center justify-between p-1.5 rounded bg-gray-900/60 border border-gray-800/80">
                    <div>
                        <div class="text-white font-bold leading-tight">${sec.sector}</div>
                        <div class="text-[9px] text-gray-500">${sec.weight}% Wt &bull; ▲${sec.bull_cnt} ▼${sec.bear_cnt}</div>
                    </div>
                    <div class="font-black ${colorClass}">${sign}${sec.pts} pts</div>
                </div>
            `;
        }).join('');
    }

    // ════════════ CONSTITUENT MATRIX TABLE ════════════
    function renderConstituentsTable() {
        if (!engineData || activeTimeIndex < 0 || !timestampsList) return;
        const activeTime = timestampsList[activeTimeIndex];
        const tbody = document.getElementById('constituents-tbody');
        if (!tbody) return;

        const matrix = engineData.primary?.matrix || {};
        const query = document.getElementById('stock-search')?.value.toLowerCase().trim() || '';
        const stateFilter = document.getElementById('state-filter')?.value || 'ALL';
        const sortFilter = document.getElementById('sort-filter')?.value || 'WEIGHT_DESC';

        const stocksList = [];
        Object.keys(matrix).forEach(sym => {
            const arr = matrix[sym] || [];
            const candle = arr[activeTimeIndex] || arr[arr.length - 1];
            if (candle) {
                stocksList.push(candle);
            }
        });

        let filtered = stocksList.filter(s => {
            if (query && !s.symbol.toLowerCase().includes(query) && !s.name.toLowerCase().includes(query)) return false;
            if (stateFilter === 'BULLISH' && s.state !== 'BULLISH') return false;
            if (stateFilter === 'BEARISH' && s.state !== 'BEARISH') return false;
            if (stateFilter === 'NEUTRAL' && s.state !== 'NEUTRAL') return false;
            if (stateFilter === 'ABOVE_VWAP' && !s.is_above_vwap) return false;
            if (stateFilter === 'OUTPERFORM' && !s.is_outperform) return false;
            return true;
        });

        filtered.sort((a, b) => {
            if (sortFilter === 'WEIGHT_DESC') return b.weight - a.weight;
            if (sortFilter === 'SCORE_DESC') return b.score - a.score;
            if (sortFilter === 'RS_DESC') return b.rs_spread - a.rs_spread;
            if (sortFilter === 'PTS_DESC') return b.pts - a.pts;
            return 0;
        });

        const countBadge = document.getElementById('tab-stocks-count');
        if (countBadge) countBadge.textContent = filtered.length;

        if (filtered.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="px-3 py-6 text-center text-gray-500 italic">No constituents match.</td></tr>`;
            return;
        }

        tbody.innerHTML = filtered.map(s => {
            const moveSign = s.candle_move_pct > 0 ? '+' : '';
            const rsSign = s.rs_spread > 0 ? '+' : '';
            const ptsSign = s.pts > 0 ? '+' : '';

            const moveClass = s.candle_move_pct > 0 ? 'text-emerald-400' : (s.candle_move_pct < 0 ? 'text-rose-400' : 'text-gray-400');
            const rsClass = s.rs_spread > 0 ? 'text-emerald-400 font-bold' : (s.rs_spread < 0 ? 'text-rose-400' : 'text-gray-400');
            const ptsClass = s.pts > 0 ? 'text-emerald-400 font-bold' : (s.pts < 0 ? 'text-rose-400' : 'text-gray-400');

            const stateBadge = s.state === 'BULLISH'
                ? '<span class="px-1 py-0.2 rounded bg-emerald-950 text-emerald-300 font-bold border border-emerald-600/40 text-[8.5px]">BULL</span>'
                : (s.state === 'BEARISH'
                    ? '<span class="px-1 py-0.2 rounded bg-rose-950 text-rose-300 font-bold border border-rose-600/40 text-[8.5px]">BEAR</span>'
                    : '<span class="px-1 py-0.2 rounded bg-gray-800 text-gray-400 font-bold text-[8.5px]">NEU</span>');

            return `
                <tr class="hover:bg-gray-800/40 transition">
                    <td class="px-2 py-1">
                        <div class="font-bold text-white leading-tight">${s.symbol}</div>
                        <div class="text-[8.5px] text-gray-500 truncate max-w-[90px]">₹${s.close}</div>
                    </td>
                    <td class="px-1.5 py-1 text-right text-gray-300">${s.weight}%</td>
                    <td class="px-1.5 py-1 text-right ${moveClass}">${moveSign}${s.candle_move_pct}%</td>
                    <td class="px-1.5 py-1 text-right ${s.is_above_vwap ? 'text-emerald-400' : 'text-rose-400'} font-semibold">₹${s.vwap}</td>
                    <td class="px-1.5 py-1 text-right ${rsClass}">${rsSign}${s.rs_spread}%</td>
                    <td class="px-1.5 py-1 text-right ${ptsClass}">${ptsSign}${s.pts}</td>
                    <td class="px-1.5 py-1 text-center">${stateBadge}</td>
                </tr>
            `;
        }).join('');
    }

    // ════════════ PLAYBACK CONTROLS ════════════
    function stepCandle(delta) {
        if (!timestampsList || timestampsList.length === 0) return;
        let nextIdx = activeTimeIndex + delta;
        if (nextIdx < 0) nextIdx = 0;
        if (nextIdx >= timestampsList.length) nextIdx = timestampsList.length - 1;
        syncActiveIndex(nextIdx);
    }

    function togglePlayPause() {
        if (isPlaying) {
            stopPlayback();
        } else {
            startPlayback();
        }
    }

    function startPlayback() {
        if (!timestampsList || timestampsList.length === 0) return;
        if (activeTimeIndex >= timestampsList.length - 1) {
            syncActiveIndex(0);
        }
        isPlaying = true;
        document.getElementById('play-icon').textContent = '⏸';
        document.getElementById('play-text').textContent = 'Pause';

        const delay = Math.max(250, 1000 / playbackSpeed);
        playbackTimer = setInterval(() => {
            if (activeTimeIndex < timestampsList.length - 1) {
                syncActiveIndex(activeTimeIndex + 1);
            } else {
                stopPlayback();
            }
        }, delay);
    }

    function stopPlayback() {
        isPlaying = false;
        clearInterval(playbackTimer);
        playbackTimer = null;
        document.getElementById('play-icon').textContent = '▶';
        document.getElementById('play-text').textContent = 'Play';
    }
</script>
@endpush
