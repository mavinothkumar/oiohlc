@extends('layouts.app')

@section('title', 'NIFTY Options Greek & Breadth Monitor – 5m Tug-of-War')

@push('styles')
    <!-- TradingView Lightweight Charts -->
    <script src="https://cdn.jsdelivr.net/npm/lightweight-charts@4.2.3/dist/lightweight-charts.standalone.production.js"></script>
    <style>
        body {
            background-color: #0b0e14 !important;
            color: #d1d5db;
        }
        /* Custom dark scrollbars */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
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

        /* Matrix table layout */
        .matrix-table {
            border-collapse: separate;
            border-spacing: 1px;
        }
        .matrix-sticky-col {
            position: sticky;
            left: 0;
            z-index: 20;
            background-color: #131722;
        }
        .matrix-cell {
            min-width: 14px;
            width: 14px;
            height: 22px;
            cursor: pointer;
            transition: transform 0.1s ease, filter 0.15s ease;
        }
        .matrix-cell:hover {
            transform: scale(1.3);
            z-index: 30;
            filter: brightness(1.3);
            box-shadow: 0 0 6px rgba(255, 255, 255, 0.4);
        }
        .matrix-col-active {
            box-shadow: inset 0 0 0 1px #38bdf8, 0 0 8px rgba(56, 189, 248, 0.4);
            filter: brightness(1.2);
        }

        /* Custom glow animations */
        @keyframes pulse-subtle {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        .live-dot {
            animation: pulse-subtle 2s infinite ease-in-out;
        }

        /* Fullscreen Zen Mode */
        body.zen-mode header,
        body.zen-mode #page-updated-time {
            display: none !important;
        }
        body.zen-mode main {
            padding: 0 !important;
            margin: 0 !important;
            max-width: 100vw !important;
        }
    </style>
@endpush

@section('content')
<div class="w-full px-2 sm:px-4 py-2 space-y-3" id="options-breadth-app">

    <!-- Top Action / Navigation Bar -->
    <div class="bg-[#131722] border border-gray-800 rounded-lg p-3 shadow-lg flex flex-wrap items-center justify-between gap-3">
        <!-- Title & Session Badge -->
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
                <span class="p-1.5 bg-indigo-900/40 border border-indigo-500/30 rounded text-indigo-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-base font-bold text-white tracking-wide flex items-center gap-2">
                        NIFTY Options Greek &amp; Breadth Monitor
                        <span id="session-badge" class="px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-950 text-emerald-400 border border-emerald-600/40 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 live-dot"></span>
                            ACTIVE
                        </span>
                    </h1>
                    <p class="text-xs text-gray-400">
                        ATM Basket Greek Flow, 5m Candlestick Breadth, OI &amp; Options Buy/Sell Tug-of-War
                    </p>
                </div>
            </div>
        </div>

        <!-- Controls: Date Picker, Playback, Filters & Settings -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- Date Selector -->
            <div class="flex items-center bg-[#1a1e29] border border-gray-700 rounded px-2.5 py-1 text-xs">
                <span class="text-gray-400 mr-2">📅 Session:</span>
                <select id="date-select" class="bg-transparent text-white font-medium focus:outline-none cursor-pointer">
                    @foreach($availableDates as $dt)
                        @php $isCurrentDay = ($dt === $todayDate); @endphp
                        <option value="{{ $dt }}" {{ $selectedDate === $dt ? 'selected' : '' }} class="bg-[#131722] text-white font-mono">
                            {{ $isCurrentDay ? '● Current Date: ' : '' }}{{ \Carbon\Carbon::parse($dt)->format('d M Y (D)') }}{{ $isCurrentDay ? ' (Today)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Basket Range Selector -->
            <div class="flex items-center bg-[#1a1e29] border border-gray-700 rounded px-2.5 py-1 text-xs">
                <span class="text-gray-400 mr-1.5">🎯 Basket:</span>
                <select id="range-select" class="bg-transparent text-indigo-400 font-bold focus:outline-none cursor-pointer">
                    <option value="3" class="bg-[#131722]">±3 Strikes (7 Avg)</option>
                    <option value="5" selected class="bg-[#131722]">±5 Strikes (11 Avg)</option>
                    <option value="7" class="bg-[#131722]">±7 Strikes (15 Avg)</option>
                    <option value="0" class="bg-[#131722]">Single Strike (ATM)</option>
                </select>
            </div>

            <!-- Quick Timeline Scrub / Playback -->
            <div class="flex items-center bg-[#1a1e29] border border-gray-700 rounded px-2 py-1 gap-1.5 text-xs">
                <button id="btn-step-prev" title="Previous 5m candle (← Key)" class="p-1 hover:bg-gray-700 rounded text-gray-300">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button id="btn-play-pause" title="Play / Pause Replay" class="px-2 py-0.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded flex items-center gap-1">
                    <span id="play-icon">▶</span>
                    <span id="play-text">Play</span>
                </button>
                <button id="btn-step-next" title="Next 5m candle (→ Key)" class="p-1 hover:bg-gray-700 rounded text-gray-300">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
                <select id="playback-speed" class="bg-transparent text-gray-300 focus:outline-none border-l border-gray-700 pl-1 cursor-pointer" title="Playback Speed">
                    <option value="1500" class="bg-[#131722]">1x</option>
                    <option value="800" class="bg-[#131722]">2x</option>
                    <option value="400" selected class="bg-[#131722]">4x</option>
                    <option value="150" class="bg-[#131722]">8x</option>
                </select>
            </div>

            <!-- Live Auto Refresh (Configurable) -->
            <div class="flex items-center bg-[#1a1e29] border border-gray-700 rounded px-2.5 py-1 text-xs">
                <span class="text-gray-400 mr-1.5 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 live-dot"></span>
                    Auto:
                </span>
                <select id="auto-refresh-select" class="bg-transparent text-white focus:outline-none cursor-pointer font-bold">
                    <option value="5" class="bg-[#131722]">5s</option>
                    <option value="15" class="bg-[#131722]">15s</option>
                    <option value="30" selected class="bg-[#131722]">30s (Default)</option>
                    <option value="60" class="bg-[#131722]">1m</option>
                    <option value="180" class="bg-[#131722]">3m</option>
                    <option value="300" class="bg-[#131722]">5m</option>
                    <option value="0" class="bg-[#131722]">Off</option>
                </select>
                <span id="countdown-badge" class="ml-1.5 px-1.5 py-0.2 bg-gray-800 text-gray-300 font-mono text-[10px] rounded">30s</span>
            </div>

            <!-- Breadth Alarm Controls (Configurable Threshold) -->
            <div class="flex items-center bg-[#1a1e29] border border-gray-700 rounded text-xs overflow-hidden">
                <button id="btn-quick-toggle-alarm" title="Click to Quick Enable / Disable Alarm" class="flex items-center gap-1.5 px-2.5 py-1 text-amber-400 hover:bg-amber-950/50 font-semibold transition cursor-pointer">
                    <span id="alarm-bell-icon">🔔</span>
                    <span id="alarm-btn-label">Alarm ON (75%)</span>
                </button>
                <button id="btn-open-alarm" title="Configure Alarm Threshold %" class="px-2 py-1 border-l border-gray-700 text-gray-400 hover:text-white hover:bg-gray-800 transition cursor-pointer">
                    ⚙️
                </button>
            </div>

            <!-- Reload Button -->
            <button id="btn-reload" title="Refresh Data" class="p-1.5 bg-[#1a1e29] hover:bg-gray-700 border border-gray-700 rounded text-gray-300 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </button>

            <!-- Zen Mode Toggle -->
            <button id="btn-zen" title="Toggle Fullscreen Zen Mode" class="p-1.5 bg-[#1a1e29] hover:bg-gray-700 border border-gray-700 rounded text-gray-300 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Top Section: Nifty 5m Candlestick Chart (Left 8 cols) + Selected Candle Options Breadth Ratio & Seriousness (Right 4 cols) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
        
        <!-- Left: Nifty 50 5m Candlestick Chart (8 cols) -->
        <div class="lg:col-span-8 bg-[#131722] border border-gray-800 rounded-lg p-3 shadow-md flex flex-col">
            <div class="flex items-center justify-between pb-2 mb-2 border-b border-gray-800/80">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-bold text-white flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                        NIFTY (5m Candles &amp; Parity)
                    </span>
                    <span class="text-xs text-gray-400 hidden sm:inline">
                        Click any candle or scrub slider below to inspect options flow at that exact moment
                    </span>
                </div>
                <!-- Chart Info / Legend -->
                <div class="flex items-center gap-2 sm:gap-3 text-xs">
                    <span class="text-gray-400">Selected: <strong id="chart-selected-time" class="text-sky-400 font-mono">09:15</strong></span>
                    <span class="text-gray-400 hidden sm:inline">Open: <span id="chart-open" class="text-gray-200 font-mono font-semibold">-</span></span>
                    <span class="text-gray-400 hidden sm:inline">Close: <span id="chart-close" class="text-gray-200 font-mono font-semibold">-</span></span>
                    <span class="text-gray-400">Move: <span id="chart-move" class="font-mono font-bold">-</span></span>
                </div>
            </div>

            <!-- Candlestick Chart Canvas -->
            <div id="nifty-chart-container" class="w-full h-[280px] sm:h-[310px] relative rounded overflow-hidden">
                <div id="chart-loading" class="absolute inset-0 bg-[#131722] flex items-center justify-center text-sm text-gray-400 z-10">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    Loading Options Candlestick Chart...
                </div>
            </div>

            <!-- Candle Scrubber Slider with Dynamic Floating Time Bubble Above Dot -->
            <div class="mt-4 pt-3 border-t border-gray-800/80 flex items-center gap-3 text-xs text-gray-400">
                <span class="font-mono text-[11px]" id="slider-start-label">09:15</span>
                
                <div class="relative flex-1 flex items-center py-2" id="slider-track-wrap">
                    <div id="slider-time-bubble" class="absolute -top-6 transform -translate-x-1/2 pointer-events-none transition-all duration-75 ease-out z-20 flex flex-col items-center" style="left: 0%;">
                        <div class="px-2 py-0.5 bg-sky-500 text-white font-mono font-black text-[11px] rounded shadow-lg border border-sky-300 flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-white live-dot"></span>
                            <span id="slider-bubble-time">09:15</span>
                        </div>
                        <div class="w-0 h-0 border-x-4 border-x-transparent border-t-4 border-t-sky-500 -mt-[1px]"></div>
                    </div>

                    <input id="candle-slider" type="range" min="0" max="74" value="0" class="w-full h-2 bg-gray-700/80 rounded-lg appearance-none cursor-pointer accent-sky-400 focus:outline-none">
                </div>

                <span class="font-mono text-[11px]" id="slider-end-label">15:25</span>
            </div>
        </div>

        <!-- Right: Options Breadth Tug-of-War Ratio & Greeks (4 cols) -->
        <div class="lg:col-span-4 bg-[#131722] border border-gray-800 rounded-lg p-3 shadow-md flex flex-col justify-between">
            <div>
                <!-- Selected Time Header & Point Change -->
                <div class="flex items-center justify-between pb-2 mb-2 border-b border-gray-800">
                    <div>
                        <span class="text-xs uppercase tracking-wider text-gray-400 font-medium">Selected Candle Interval</span>
                        <div class="text-sm font-bold text-sky-400 font-mono flex items-center gap-1.5" id="stat-time-label">
                            09:15 - 5-min candle
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-xs text-gray-400">Option Net Impulse</span>
                        <div class="text-sm font-bold font-mono" id="stat-net-pts">+0.00 pts</div>
                    </div>
                </div>

                <!-- Green vs Red Big Ratio Cards -->
                <div class="grid grid-cols-2 gap-2 mb-3">
                    <!-- BUY / BULLISH FLOW -->
                    <div class="bg-emerald-950/40 border border-emerald-600/40 rounded-lg p-2.5 text-center">
                        <div class="text-[11px] font-bold text-emerald-400 uppercase tracking-wider mb-0.5">BUY (Bull Flow)</div>
                        <div class="text-2xl font-black text-emerald-300 font-mono" id="stat-green-ratio">50%</div>
                        <div class="text-[10px] text-gray-400 mt-0.5">
                            <span id="stat-green-weight">50.0% Wt</span> • <span id="stat-green-count">5</span> strikes
                        </div>
                    </div>

                    <!-- SELL / BEARISH FLOW -->
                    <div class="bg-rose-950/40 border border-rose-600/40 rounded-lg p-2.5 text-center">
                        <div class="text-[11px] font-bold text-rose-400 uppercase tracking-wider mb-0.5">SELL (Bear Flow)</div>
                        <div class="text-2xl font-black text-rose-300 font-mono" id="stat-red-ratio">50%</div>
                        <div class="text-[10px] text-gray-400 mt-0.5">
                            <span id="stat-red-weight">50.0% Wt</span> • <span id="stat-red-count">5</span> strikes
                        </div>
                    </div>
                </div>

                <!-- Tug-of-War Balance Bar -->
                <div class="mb-3">
                    <div class="flex justify-between text-[11px] font-medium text-gray-400 mb-1">
                        <span>Options Open Interest &amp; Volume Weight Tug</span>
                        <span id="stat-balance-ratio">50% / 50%</span>
                    </div>
                    <div class="w-full h-2 bg-gray-800 rounded-full overflow-hidden flex">
                        <div id="stat-bar-green" class="h-full bg-emerald-500 transition-all duration-200" style="width: 50%;"></div>
                        <div id="stat-bar-red" class="h-full bg-rose-500 transition-all duration-200" style="width: 50%;"></div>
                    </div>
                </div>

                <!-- Market Seriousness & Divergence Verdict -->
                <div class="p-2.5 rounded bg-[#181d2a] border border-gray-800 text-xs">
                    <div class="font-bold text-white flex items-center gap-1.5 mb-1" id="stat-verdict-title">
                        <span>⚖️</span>
                        <span>Balanced Options Equilibrium</span>
                    </div>
                    <p class="text-[11px] text-gray-400 leading-relaxed" id="stat-verdict-desc">
                        Options flow is balanced between Call buying and Put shorting.
                    </p>
                </div>
            </div>

            <!-- Greek Snapshot Footer -->
            <div class="mt-3 pt-2.5 border-t border-gray-800/80 grid grid-cols-4 gap-1.5 text-center font-mono">
                <div class="bg-[#181d2a] p-1.5 rounded">
                    <div class="text-[9px] text-gray-400">Net Delta</div>
                    <div class="text-xs font-bold text-sky-400" id="greek-delta">0.0k</div>
                </div>
                <div class="bg-[#181d2a] p-1.5 rounded">
                    <div class="text-[9px] text-gray-400">CE Vega</div>
                    <div class="text-xs font-bold text-emerald-400" id="greek-ce-v">0.00</div>
                </div>
                <div class="bg-[#181d2a] p-1.5 rounded">
                    <div class="text-[9px] text-gray-400">PE Vega</div>
                    <div class="text-xs font-bold text-rose-400" id="greek-pe-v">0.00</div>
                </div>
                <div class="bg-[#181d2a] p-1.5 rounded">
                    <div class="text-[9px] text-gray-400">Basket PCR</div>
                    <div class="text-xs font-bold text-amber-400" id="greek-pcr">1.00</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Section: Options Constituents 5m Matrix (Left 8 cols) + Strike Contribution Bar Chart (Right 4 cols) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
        
        <!-- Left: Options Heatmap Matrix (8 cols) -->
        <div class="lg:col-span-8 bg-[#131722] border border-gray-800 rounded-lg p-3 shadow-md flex flex-col">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-2 mb-2 border-b border-gray-800/80">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-bold text-white">Options Constituents 5m Matrix</span>
                    <span class="text-xs text-gray-400" id="matrix-strikes-count">(11 Strike Pairs)</span>
                </div>

                <!-- Legend & Quick Filters -->
                <div class="flex items-center gap-2 text-xs">
                    <span class="flex items-center gap-1 text-emerald-400"><span class="w-2.5 h-2.5 rounded-sm bg-emerald-500"></span> Buy/Up</span>
                    <span class="flex items-center gap-1 text-rose-400"><span class="w-2.5 h-2.5 rounded-sm bg-rose-500"></span> Sell/Down</span>
                    <span class="flex items-center gap-1 text-gray-400"><span class="w-2.5 h-2.5 rounded-sm bg-gray-700"></span> Flat</span>
                </div>
            </div>

            <!-- Heatmap Scroll Container -->
            <div class="w-full overflow-x-auto max-h-[460px] overflow-y-auto border border-gray-800/80 rounded bg-[#0b0e14]">
                <table class="matrix-table w-full text-xs text-left" id="matrix-table">
                    <thead class="sticky top-0 bg-[#131722] z-30 shadow">
                        <tr class="border-b border-gray-800 text-gray-400 text-[11px] uppercase">
                            <th class="matrix-sticky-col px-3 py-2 bg-[#131722] min-w-[140px] font-semibold border-r border-gray-800">
                                Strike Pair
                            </th>
                            <th class="px-2 py-2 bg-[#131722] text-right min-w-[55px] font-semibold">
                                Weight
                            </th>
                            <th class="px-2 py-2 bg-[#131722] text-right min-w-[65px] font-semibold border-r border-gray-800">
                                Signal
                            </th>
                            <!-- 75 5-minute candle timestamp columns -->
                            <th class="p-0 bg-[#131722]">
                                <div class="flex items-center" id="heatmap-time-header">
                                    <!-- Populated via JS -->
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-900/60 font-mono" id="matrix-tbody">
                        <!-- Populated via JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Right: Strike Contributions Leaderboard Bar Chart (4 cols) -->
        <div class="lg:col-span-4 bg-[#131722] border border-gray-800 rounded-lg p-3 shadow-md flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-2 mb-2 border-b border-gray-800">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        <span class="text-sm font-bold text-white" id="contrib-header-time">09:15 Contribution</span>
                    </div>
                    <span class="text-[11px] text-gray-400">Net Points Influence</span>
                </div>

                <!-- Contribution Bars Container -->
                <div id="contrib-bars-container" class="space-y-1.5 overflow-y-auto max-h-[400px] pr-1">
                    <!-- Populated via JS -->
                </div>
            </div>

            <!-- Footer Tip -->
            <div class="mt-3 pt-2 border-t border-gray-800 text-[10px] text-gray-500 text-center">
                Click any row to jump or highlight on chart
            </div>
        </div>
    </div>

</div>

<!-- Configurable Alarm Modal -->
<div id="alarm-modal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-[#181d2a] border border-gray-700 rounded-xl p-5 max-w-md w-full shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-gray-700 pb-3">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <span>🔔</span> Configure Breadth Alarm
            </h3>
            <button id="btn-close-alarm-modal" class="text-gray-400 hover:text-white text-lg font-bold">&times;</button>
        </div>

        <div class="space-y-3 text-xs">
            <div>
                <label class="block text-gray-300 font-semibold mb-1">Trigger Alarm When Buy or Sell Reaches:</label>
                <div class="grid grid-cols-4 gap-2">
                    <button class="alarm-thresh-btn px-3 py-2 bg-gray-800 hover:bg-gray-700 rounded font-bold border border-gray-700 text-white" data-val="70">70%</button>
                    <button class="alarm-thresh-btn px-3 py-2 bg-indigo-600 text-white rounded font-bold border border-indigo-500" data-val="75">75%</button>
                    <button class="alarm-thresh-btn px-3 py-2 bg-gray-800 hover:bg-gray-700 rounded font-bold border border-gray-700 text-white" data-val="80">80%</button>
                    <button class="alarm-thresh-btn px-3 py-2 bg-gray-800 hover:bg-gray-700 rounded font-bold border border-gray-700 text-white" data-val="85">85%</button>
                </div>
            </div>

            <div class="pt-2">
                <label class="flex items-center gap-2 text-gray-300 cursor-pointer">
                    <input type="checkbox" id="alarm-sound-toggle" checked class="rounded bg-gray-800 border-gray-700 text-indigo-600 focus:ring-0">
                    <span>Play Audio Chime on Alert Trigger</span>
                </label>
            </div>

            <div class="p-3 rounded bg-gray-900 border border-gray-800 text-[11px] text-gray-400 leading-relaxed">
                When either <strong>% BUY</strong> or <strong>% SELL</strong> exceeds your threshold, the system triggers an audio-visual alert informing you that institutional option flow has reached an extreme imbalance.
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-gray-700">
            <button id="btn-save-alarm" class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded text-xs">
                Save Settings
            </button>
        </div>
    </div>
</div>

{{-- App Scripts --}}
<script>
(function() {
    let appData = null;
    let chart = null;
    let candleSeries = null;
    let currentIdx = 0;
    let isPlaying = false;
    let playTimer = null;
    let alarmThreshold = 75;
    let alarmSoundEnabled = true;
    let audioCtx = null;
    let autoRefreshTimer = null;
    let countdownSeconds = 30;
    const autoRefreshSelect = document.getElementById('auto-refresh-select');
    const countdownBadge = document.getElementById('countdown-badge');

    // Load saved auto-refresh preference from localStorage
    try {
        const savedInterval = localStorage.getItem('options_breadth_auto_refresh');
        if (savedInterval !== null && autoRefreshSelect) {
            autoRefreshSelect.value = savedInterval;
        }
    } catch(e) {}

    function setupAutoRefresh() {
        if (autoRefreshTimer) clearInterval(autoRefreshTimer);
        if (!autoRefreshSelect) return;

        const intervalSecs = parseInt(autoRefreshSelect.value, 10);

        if (intervalSecs <= 0) {
            if (countdownBadge) countdownBadge.textContent = 'Off';
            return;
        }

        countdownSeconds = intervalSecs;
        if (countdownBadge) countdownBadge.textContent = `${countdownSeconds}s`;

        autoRefreshTimer = setInterval(() => {
            // Do not disrupt active replay playback
            if (isPlaying) return;

            countdownSeconds--;
            if (countdownSeconds <= 0) {
                countdownSeconds = intervalSecs;
                loadData(true); // Background silent refresh
            }
            if (countdownBadge) countdownBadge.textContent = `${countdownSeconds}s`;
        }, 1000);
    }

    // Web Audio chime
    function playBeep(isBullish) {
        if (!alarmSoundEnabled) return;
        try {
            if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.frequency.value = isBullish ? 880 : 330; // High tone for Buy, low for Sell
            gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.4);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.4);
        } catch(e) {}
    }

    // Initialize Lightweight Candlestick Chart
    function initChart() {
        const container = document.getElementById('nifty-chart-container');
        container.innerHTML = '';

        chart = LightweightCharts.createChart(container, {
            layout: {
                background: { color: '#131722' },
                textColor: '#9ca3af',
                fontSize: 11,
            },
            grid: {
                vertLines: { color: 'rgba(31, 41, 55, 0.4)' },
                horzLines: { color: 'rgba(31, 41, 55, 0.4)' },
            },
            crosshair: {
                mode: LightweightCharts.CrosshairMode.Normal,
            },
            rightPriceScale: {
                borderColor: '#374151',
                autoScale: true,
            },
            timeScale: {
                borderColor: '#374151',
                timeVisible: true,
                secondsVisible: false,
            },
        });

        candleSeries = chart.addCandlestickSeries({
            upColor: '#10b981',
            downColor: '#ef4444',
            borderUpColor: '#10b981',
            borderDownColor: '#ef4444',
            wickUpColor: '#10b981',
            wickDownColor: '#ef4444',
        });

        // Click on chart to scrub
        chart.subscribeClick(param => {
            if (!param.time || !appData) return;
            const idx = appData.lw_chart_candles.findIndex(c => c.time === param.time);
            if (idx !== -1) {
                applyCandleIndex(idx);
            }
        });

        window.addEventListener('resize', () => {
            if (chart && container) {
                chart.applyOptions({ width: container.clientWidth });
            }
        });
    }

    // Fetch Data from Controller API (Supports background auto-refresh)
    async function loadData(isAuto = false) {
        isAuto = (isAuto === true);
        const date = document.getElementById('date-select').value;
        const range = document.getElementById('range-select').value;
        const loading = document.getElementById('chart-loading');
        if (!isAuto && loading) loading.style.display = 'flex';

        const prevLength = appData && appData.timestamps ? appData.timestamps.length : 0;
        const wasAtLatest = (currentIdx >= prevLength - 1);

        try {
            const url = `{{ route('api.options.breadth.monitor.data') }}?date=${date}&range=${range}`;
            const res = await fetch(url);
            appData = await res.json();

            if (!appData.success) {
                if (!isAuto) alert('No options snapshot data found for selected session.');
                return;
            }

            // Update Session Status Badge & Server Time
            const badge = document.getElementById('session-badge');
            if (badge) {
                if (appData.is_live) {
                    badge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full bg-emerald-400 live-dot"></span> LIVE MARKET (${appData.server_time})`;
                    badge.className = 'px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-950 text-emerald-400 border border-emerald-600/40 flex items-center gap-1.5';
                } else if (appData.is_today) {
                    badge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full bg-sky-400"></span> TODAY SESSION (${appData.server_time})`;
                    badge.className = 'px-2 py-0.5 text-xs font-medium rounded-full bg-sky-950/60 text-sky-400 border border-sky-600/30 flex items-center gap-1.5';
                } else {
                    badge.innerHTML = `HISTORICAL (${appData.date})`;
                    badge.className = 'px-2 py-0.5 text-xs font-medium rounded-full bg-gray-800 text-gray-300 border border-gray-700 flex items-center gap-1.5';
                }
            }

            const pageClock = document.getElementById('page-updated-time');
            if (pageClock && appData.server_time) {
                pageClock.textContent = `${appData.date}, ${appData.server_time}`;
            }

            // Populate Chart
            if (!chart) initChart();
            candleSeries.setData(appData.lw_chart_candles);
            if (!isAuto) {
                chart.timeScale().fitContent();
            } else if (wasAtLatest) {
                chart.timeScale().scrollToRealtime();
            }

            // Populate Matrix & Timeline Headers
            buildTimelineHeader(appData.timestamps);
            buildMatrixRows(appData.matrix, appData.timestamps);

            // Configure Slider
            const slider = document.getElementById('candle-slider');
            slider.max = Math.max(0, appData.timestamps.length - 1);
            document.getElementById('slider-start-label').textContent = appData.timestamps[0] || '09:15';
            document.getElementById('slider-end-label').textContent = appData.timestamps[appData.timestamps.length - 1] || '15:25';

            // If user was tracking the latest live candle, snap forward; otherwise keep their inspected position
            if (wasAtLatest || !isAuto) {
                slider.value = appData.timestamps.length - 1;
                applyCandleIndex(appData.timestamps.length - 1);
            } else {
                applyCandleIndex(Math.min(currentIdx, appData.timestamps.length - 1));
            }

        } catch (err) {
            console.error(err);
        } finally {
            if (!isAuto && loading) loading.style.display = 'none';
        }
    }

    // Build timeline hour marks on the Matrix header
    function buildTimelineHeader(timestamps) {
        const header = document.getElementById('heatmap-time-header');
        header.innerHTML = '';
        timestamps.forEach(t => {
            const isHour = (t.endsWith(':00') || t === '09:15');
            const th = document.createElement('div');
            th.className = `matrix-cell text-center text-[10px] flex items-center justify-center font-mono ${isHour ? 'text-white font-bold border-l border-gray-700/80' : 'text-gray-500'}`;
            th.style.width = '14px';
            th.style.minWidth = '14px';
            th.textContent = isHour ? t.substring(0, 2) : '·';
            th.title = t;
            header.appendChild(th);
        });
    }

    // Build Matrix Rows
    function buildMatrixRows(matrix, timestamps) {
        const tbody = document.getElementById('matrix-tbody');
        tbody.innerHTML = '';
        document.getElementById('matrix-strikes-count').textContent = `(${matrix.length} Strike Pairs)`;

        matrix.forEach(row => {
            const tr = document.createElement('tr');
            tr.className = row.is_master ? 'bg-indigo-950/40 font-bold border-b-2 border-indigo-500/40' : 'hover:bg-gray-900/50';

            // Strike title cell
            const tdTitle = document.createElement('td');
            tdTitle.className = `matrix-sticky-col px-3 py-1.5 ${row.is_master ? 'bg-[#181d2c] text-indigo-300 font-extrabold' : 'bg-[#131722] text-white'} border-r border-gray-800 whitespace-nowrap`;
            tdTitle.innerHTML = `<div>${row.symbol}</div><div class="text-[10px] text-gray-400 font-normal">${row.name}</div>`;
            tr.appendChild(tdTitle);

            // Weight cell
            const tdWt = document.createElement('td');
            tdWt.className = `px-2 py-1.5 text-right font-mono text-[11px] ${row.is_master ? 'bg-[#181d2c] text-indigo-400 font-bold' : 'text-gray-300'}`;
            tdWt.textContent = `${row.weight}%`;
            tr.appendChild(tdWt);

            // Signal cell
            const tdSig = document.createElement('td');
            tdSig.className = `px-2 py-1.5 text-right font-mono text-[10px] border-r border-gray-800 ${row.is_master ? 'bg-[#181d2c] text-indigo-400' : 'text-gray-400'}`;
            tdSig.id = `row-sig-${row.id}`;
            tdSig.textContent = row.is_master ? 'COMP' : 'ACTIVE';
            tr.appendChild(tdSig);

            // 75 Heatmap cells
            const tdCellsWrap = document.createElement('td');
            tdCellsWrap.className = 'p-0';
            const flex = document.createElement('div');
            flex.className = 'flex items-center';

            timestamps.forEach(t => {
                const cell = row.cells[t] || { color: 'gray' };
                const cDiv = document.createElement('div');
                let bg = 'bg-gray-800';
                if (cell.color === 'green') bg = 'bg-emerald-500';
                if (cell.color === 'red')   bg = 'bg-rose-500';

                cDiv.className = `matrix-cell ${bg} rounded-xs`;
                cDiv.id = `cell-${row.id}-${t.replace(':', '')}`;
                cDiv.title = `${t} | ${row.symbol}: ${cell.d_ce ? 'ΔCE:'+cell.d_ce+' ΔPE:'+cell.d_pe : ''}`;
                cDiv.addEventListener('click', () => {
                    const idx = timestamps.indexOf(t);
                    if (idx !== -1) applyCandleIndex(idx);
                });
                flex.appendChild(cDiv);
            });

            tdCellsWrap.appendChild(flex);
            tr.appendChild(tdCellsWrap);
            tbody.appendChild(tr);
        });
    }

    // Apply Candle Index (Scrubber / Playback core function)
    function applyCandleIndex(idx) {
        if (!appData || !appData.timestamps || idx < 0 || idx >= appData.timestamps.length) return;
        currentIdx = idx;
        const timeStr = appData.timestamps[idx];

        // Update Slider Position & Floating Bubble
        const slider = document.getElementById('candle-slider');
        slider.value = idx;
        const pct = (idx / (appData.timestamps.length - 1)) * 100;
        const bubble = document.getElementById('slider-time-bubble');
        bubble.style.left = `${pct}%`;
        document.getElementById('slider-bubble-time').textContent = timeStr;

        // Update Chart Header Info
        const candle = appData.nifty_candles[idx] || {};
        document.getElementById('chart-selected-time').textContent = timeStr;
        document.getElementById('chart-open').textContent = candle.open ? candle.open.toFixed(2) : '-';
        document.getElementById('chart-close').textContent = candle.close ? candle.close.toFixed(2) : '-';
        const moveEl = document.getElementById('chart-move');
        if (candle.move_pts !== undefined) {
            moveEl.textContent = `${candle.move_pts >= 0 ? '+' : ''}${candle.move_pts.toFixed(2)} (${candle.move_pct}%)`;
            moveEl.className = `font-mono font-bold ${candle.move_pts >= 0 ? 'text-emerald-400' : 'text-rose-400'}`;
        }

        // Update Breadth Tug-of-War Stats
        const breadth = appData.breadth_by_time[timeStr];
        if (breadth) {
            document.getElementById('stat-time-label').textContent = `${timeStr} - 5-min candle`;
            const netPtsEl = document.getElementById('stat-net-pts');
            netPtsEl.textContent = `${breadth.net_points >= 0 ? '+' : ''}${breadth.net_points.toFixed(2)} pts`;
            netPtsEl.className = `text-sm font-bold font-mono ${breadth.net_points >= 0 ? 'text-emerald-400' : 'text-rose-400'}`;

            document.getElementById('stat-green-ratio').textContent = `${breadth.up_weight}%`;
            document.getElementById('stat-green-weight').textContent = `${breadth.up_weight}% Wt`;
            document.getElementById('stat-green-count').textContent = breadth.up_count;

            document.getElementById('stat-red-ratio').textContent = `${breadth.down_weight}%`;
            document.getElementById('stat-red-weight').textContent = `${breadth.down_weight}% Wt`;
            document.getElementById('stat-red-count').textContent = breadth.down_count;

            document.getElementById('stat-balance-ratio').textContent = `${breadth.up_weight}% / ${breadth.down_weight}%`;
            document.getElementById('stat-bar-green').style.width = `${breadth.up_weight}%`;
            document.getElementById('stat-bar-red').style.width = `${breadth.down_weight}%`;

            document.getElementById('stat-verdict-desc').textContent = breadth.verdict;

            // Greek stats
            if (breadth.greeks) {
                document.getElementById('greek-delta').textContent = breadth.greeks.net_delta;
                document.getElementById('greek-ce-v').textContent = breadth.greeks.avg_ce_vega.toFixed(2);
                document.getElementById('greek-pe-v').textContent = breadth.greeks.avg_pe_vega.toFixed(2);
                document.getElementById('greek-pcr').textContent = breadth.greeks.pcr.toFixed(2);
            }

            // Check Alarm Threshold
            if (breadth.up_weight >= alarmThreshold) {
                playBeep(true);
            } else if (breadth.down_weight >= alarmThreshold) {
                playBeep(false);
            }

            // Update individual strike signal labels on active candle
            if (appData.matrix) {
                appData.matrix.forEach(r => {
                    const cell = r.cells[timeStr];
                    const sigEl = document.getElementById(`row-sig-${r.id}`);
                    if (sigEl && cell) {
                        if (r.is_master) {
                            sigEl.textContent = `${cell.up_pct}% / ${cell.down_pct}%`;
                            sigEl.className = `px-2 py-1.5 text-right font-mono text-[10px] border-r border-gray-800 font-bold ${cell.up_pct >= 54 ? 'text-emerald-400' : (cell.up_pct <= 46 ? 'text-rose-400' : 'text-gray-400')}`;
                        } else {
                            sigEl.textContent = `${cell.bull_pct}% Bull`;
                            sigEl.className = `px-2 py-1.5 text-right font-mono text-[10px] border-r border-gray-800 ${cell.bull_pct >= 54 ? 'text-emerald-400 font-bold' : (cell.bull_pct <= 46 ? 'text-rose-400 font-bold' : 'text-gray-400')}`;
                        }
                    }
                });
            }

            // Update Strike Contribution Bar Chart
            renderContributions(breadth.contributions, timeStr);
        }

        // Highlight Active Matrix Column
        document.querySelectorAll('.matrix-col-active').forEach(el => el.classList.remove('matrix-col-active'));
        const cleanT = timeStr.replace(':', '');
        document.querySelectorAll(`[id$="-${cleanT}"]`).forEach(el => el.classList.add('matrix-col-active'));
    }

    // Render Leaders & Draggers Horizontal Bar Chart
    function renderContributions(contributions, timeStr) {
        document.getElementById('contrib-header-time').textContent = `${timeStr} Strike Pressure`;
        const container = document.getElementById('contrib-bars-container');
        container.innerHTML = '';

        if (!contributions || !contributions.length) return;

        const maxAbs = Math.max(...contributions.map(c => Math.abs(c.pts)), 1);

        contributions.forEach(c => {
            const row = document.createElement('div');
            row.className = 'flex items-center justify-between gap-2 text-xs py-0.5';

            const barW = Math.min(100, Math.round((Math.abs(c.pts) / maxAbs) * 100));
            const isPos = (c.pts >= 0);

            row.innerHTML = `
                <div class="w-24 text-[11px] font-bold text-gray-300 font-mono truncate" title="${c.name}">
                    ${c.name}
                </div>
                <div class="flex-1 bg-gray-900 h-2.5 rounded overflow-hidden flex items-center">
                    <div class="h-full ${isPos ? 'bg-emerald-500' : 'bg-rose-500'} transition-all duration-200" style="width: ${barW}%;"></div>
                </div>
                <div class="w-14 text-right text-[11px] font-bold font-mono ${isPos ? 'text-emerald-400' : 'text-rose-400'}">
                    ${isPos ? '+' : ''}${c.pts.toFixed(1)}
                </div>
            `;
            container.appendChild(row);
        });
    }

    // Playback loop
    function startPlayback() {
        if (!appData || !appData.timestamps) return;
        isPlaying = true;
        document.getElementById('play-icon').textContent = '⏸';
        document.getElementById('play-text').textContent = 'Pause';

        const speed = parseInt(document.getElementById('playback-speed').value, 10) || 400;

        playTimer = setInterval(() => {
            let next = currentIdx + 1;
            if (next >= appData.timestamps.length) {
                stopPlayback();
                return;
            }
            applyCandleIndex(next);
        }, speed);
    }

    function stopPlayback() {
        isPlaying = false;
        clearInterval(playTimer);
        document.getElementById('play-icon').textContent = '▶';
        document.getElementById('play-text').textContent = 'Play';
    }

    // App Initialization & Event listeners
    function initApp() {
        loadData(false);

        // Auto-refresh timer listener & loop start
        if (autoRefreshSelect) {
            autoRefreshSelect.addEventListener('change', () => {
                try {
                    localStorage.setItem('options_breadth_auto_refresh', autoRefreshSelect.value);
                } catch(e) {}
                setupAutoRefresh();
            });
        }
        setupAutoRefresh();

        // Date & Range Changes (Explicitly call with isAuto = false)
        document.getElementById('date-select').addEventListener('change', () => loadData(false));
        document.getElementById('range-select').addEventListener('change', () => loadData(false));
        document.getElementById('btn-reload').addEventListener('click', () => loadData(false));

        // Slider
        document.getElementById('candle-slider').addEventListener('input', e => {
            if (isPlaying) stopPlayback();
            applyCandleIndex(parseInt(e.target.value, 10));
        });

        // Prev & Next Buttons
        document.getElementById('btn-step-prev').addEventListener('click', () => {
            if (isPlaying) stopPlayback();
            applyCandleIndex(Math.max(0, currentIdx - 1));
        });
        document.getElementById('btn-step-next').addEventListener('click', () => {
            if (isPlaying) stopPlayback();
            applyCandleIndex(Math.min(appData.timestamps.length - 1, currentIdx + 1));
        });

        // Play/Pause
        document.getElementById('btn-play-pause').addEventListener('click', () => {
            if (isPlaying) stopPlayback();
            else startPlayback();
        });

        // Zen Mode
        document.getElementById('btn-zen').addEventListener('click', () => {
            document.body.classList.toggle('zen-mode');
            if (chart) {
                setTimeout(() => {
                    const c = document.getElementById('nifty-chart-container');
                    chart.applyOptions({ width: c.clientWidth });
                }, 100);
            }
        });

        // Alarm Modal & Quick Toggle
        document.getElementById('btn-quick-toggle-alarm').addEventListener('click', () => {
            alarmSoundEnabled = !alarmSoundEnabled;
            const btn = document.getElementById('alarm-btn-label');
            btn.textContent = alarmSoundEnabled ? `Alarm ON (${alarmThreshold}%)` : 'Alarm OFF';
            btn.parentElement.classList.toggle('text-amber-400', alarmSoundEnabled);
            btn.parentElement.classList.toggle('text-gray-500', !alarmSoundEnabled);
        });

        document.getElementById('btn-open-alarm').addEventListener('click', () => {
            document.getElementById('alarm-modal').classList.remove('hidden');
        });
        document.getElementById('btn-close-alarm-modal').addEventListener('click', () => {
            document.getElementById('alarm-modal').classList.add('hidden');
        });
        document.querySelectorAll('.alarm-thresh-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                document.querySelectorAll('.alarm-thresh-btn').forEach(b => {
                    b.classList.remove('bg-indigo-600', 'border-indigo-500');
                    b.classList.add('bg-gray-800', 'border-gray-700');
                });
                btn.classList.remove('bg-gray-800', 'border-gray-700');
                btn.classList.add('bg-indigo-600', 'border-indigo-500');
                alarmThreshold = parseInt(btn.dataset.val, 10);
            });
        });
        document.getElementById('btn-save-alarm').addEventListener('click', () => {
            alarmSoundEnabled = document.getElementById('alarm-sound-toggle').checked;
            document.getElementById('alarm-btn-label').textContent = alarmSoundEnabled ? `Alarm ON (${alarmThreshold}%)` : 'Alarm OFF';
            document.getElementById('alarm-modal').classList.add('hidden');
        });

        // Keyboard navigation (Left / Right arrow keys)
        document.addEventListener('keydown', e => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;
            if (e.key === 'ArrowLeft') {
                applyCandleIndex(Math.max(0, currentIdx - 1));
            } else if (e.key === 'ArrowRight') {
                applyCandleIndex(Math.min(appData.timestamps.length - 1, currentIdx + 1));
            } else if (e.key === ' ') {
                e.preventDefault();
                if (isPlaying) stopPlayback();
                else startPlayback();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initApp);
    } else {
        initApp();
    }
})();
</script>
@endsection
