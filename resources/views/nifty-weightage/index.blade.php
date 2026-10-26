@extends('layouts.app')

@section('title', 'NIFTY 50 Weightage & Candle Breadth Monitor')

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
<div class="w-full px-2 sm:px-4 py-2 space-y-3" id="weightage-app">

    <!-- Top Action / Navigation Bar -->
    <div class="bg-[#131722] border border-gray-800 rounded-lg p-3 shadow-lg flex flex-wrap items-center justify-between gap-3">
        <!-- Title & Session Badge -->
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
                <span class="p-1.5 bg-blue-900/40 border border-blue-500/30 rounded text-blue-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-base font-bold text-white tracking-wide flex items-center gap-2">
                        NIFTY 50 Weightage &amp; Breadth Monitor
                        <span id="session-badge" class="px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-950 text-emerald-400 border border-emerald-600/40 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 live-dot"></span>
                            ACTIVE
                        </span>
                    </h1>
                    <p class="text-xs text-gray-400">
                        Every 5-minute candle breadth, stock points contribution &amp; heavyweight divergence analysis
                    </p>
                </div>
            </div>
        </div>

        <!-- Controls: Date Picker, Playback, Filters & Settings -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- Date Selector (Default: Current Date) -->
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

            <!-- Quick Timeline Scrub / Playback -->
            <div class="flex items-center bg-[#1a1e29] border border-gray-700 rounded px-2 py-1 gap-1.5 text-xs">
                <button id="btn-step-prev" title="Previous 5m candle (← Key)" class="p-1 hover:bg-gray-700 rounded text-gray-300">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button id="btn-play-pause" title="Play / Pause Replay" class="px-2 py-0.5 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded flex items-center gap-1">
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

            <!-- Live Auto Refresh -->
            <div class="flex items-center bg-[#1a1e29] border border-gray-700 rounded px-2.5 py-1 text-xs">
                <span class="text-gray-400 mr-1.5 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 live-dot"></span>
                    Auto:
                </span>
                <select id="auto-refresh-select" class="bg-transparent text-white focus:outline-none cursor-pointer">
                    <option value="5" class="bg-[#131722]">5s</option>
                    <option value="15" class="bg-[#131722]">15s</option>
                    <option value="30" selected class="bg-[#131722]">30s</option>
                    <option value="60" class="bg-[#131722]">1m</option>
                    <option value="0" class="bg-[#131722]">Off</option>
                </select>
                <span id="countdown-badge" class="ml-1.5 px-1.5 py-0.2 bg-gray-800 text-gray-300 font-mono text-[10px] rounded">30s</span>
            </div>

            <!-- Breadth Alarm Controls (Quick 1-Click Enable/Disable Toggle + Settings) -->
            <div class="flex items-center bg-[#1a1e29] border border-gray-700 rounded text-xs overflow-hidden">
                <button id="btn-quick-toggle-alarm" title="Click to Quick Enable / Disable Alarm (For Testing)" class="flex items-center gap-1.5 px-2.5 py-1 text-amber-400 hover:bg-amber-950/50 font-semibold transition cursor-pointer">
                    <span id="alarm-bell-icon">🔔</span>
                    <span id="alarm-btn-label">Alarm ON (80%)</span>
                </button>
                <button id="btn-open-alarm" title="Configure Alarm Sounds & Threshold %" class="px-2 py-1 border-l border-gray-700 text-gray-400 hover:text-white hover:bg-gray-800 transition cursor-pointer">
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

    <!-- Top Section: Nifty 5m Candlestick Chart (Left) + Selected Candle Breadth Ratio & Seriousness (Right) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
        
        <!-- Left: Nifty 50 5m Candlestick Chart (8 cols) -->
        <div class="lg:col-span-8 bg-[#131722] border border-gray-800 rounded-lg p-3 shadow-md flex flex-col">
            <div class="flex items-center justify-between pb-2 mb-2 border-b border-gray-800/80">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-bold text-white flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                        NIFTY 50 (5m Candles)
                    </span>
                    <span class="text-xs text-gray-400 hidden sm:inline">
                        Click any candle to inspect breadth at that exact 5-min interval
                    </span>
                </div>
                <!-- Chart Info / Legend -->
                <div class="flex items-center gap-2 sm:gap-3 text-xs">
                    <span class="text-gray-400">Selected: <strong id="chart-selected-time" class="text-sky-400 font-mono">09:15</strong></span>
                    <button id="btn-snap-live" title="Snap to latest candle" class="hidden px-2 py-0.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded text-[10px] items-center gap-1 transition">
                        <span class="w-1.5 h-1.5 rounded-full bg-white live-dot"></span>
                        Snap to Live
                    </button>
                    <span class="text-gray-400 hidden sm:inline">Open: <span id="chart-open" class="text-gray-200 font-mono font-semibold">-</span></span>
                    <span class="text-gray-400 hidden sm:inline">Close: <span id="chart-close" class="text-gray-200 font-mono font-semibold">-</span></span>
                    <span class="text-gray-400">Move: <span id="chart-move" class="font-mono font-bold">-</span></span>
                </div>
            </div>

            <!-- Candlestick Chart Canvas -->
            <div id="nifty-chart-container" class="w-full h-[280px] sm:h-[310px] relative rounded overflow-hidden">
                <div id="chart-loading" class="absolute inset-0 bg-[#131722] flex items-center justify-center text-sm text-gray-400 z-10">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    Loading Nifty 5m Candlestick Chart...
                </div>
            </div>

            <!-- Candle Scrubber Slider with Dynamic Time Bubble Above Dot -->
            <div class="mt-4 pt-3 border-t border-gray-800/80 flex items-center gap-3 text-xs text-gray-400">
                <span class="font-mono text-[11px]" id="slider-start-label">09:15</span>
                
                <div class="relative flex-1 flex items-center py-2" id="slider-track-wrap">
                    <!-- Floating Time Pill Directly Above the Slider Dot -->
                    <div id="slider-time-bubble" class="absolute -top-6 transform -translate-x-1/2 pointer-events-none transition-all duration-75 ease-out z-20 flex flex-col items-center" style="left: 0%;">
                        <div class="px-2 py-0.5 bg-sky-500 text-white font-mono font-black text-[11px] rounded shadow-lg border border-sky-300 flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-white live-dot"></span>
                            <span id="slider-bubble-time">09:15</span>
                        </div>
                        <!-- Little triangle caret pointing directly to the slider dot -->
                        <div class="w-0 h-0 border-x-4 border-x-transparent border-t-4 border-t-sky-500 -mt-[1px]"></div>
                    </div>

                    <input id="candle-slider" type="range" min="0" max="74" value="0" class="w-full h-2 bg-gray-700/80 rounded-lg appearance-none cursor-pointer accent-sky-400 focus:outline-none">
                </div>

                <span class="font-mono text-[11px]" id="slider-end-label">15:25</span>
            </div>
        </div>

        <!-- Right: Ratio of Green and Red Candles & Weightage Seriousness (4 cols) -->
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
                    <!-- Net Points Move in this candle -->
                    <div class="text-right">
                        <span class="text-xs text-gray-400">Index Move</span>
                        <div id="stat-net-pts" class="text-xl font-black font-mono text-emerald-400">
                            +0.0 pts
                        </div>
                    </div>
                </div>

                <!-- Green vs Red Ratio Big Blocks (Sample Image Layout) -->
                <div class="grid grid-cols-2 gap-2.5 my-2">
                    <!-- GREEN (UP) Box -->
                    <div id="box-up" class="bg-gradient-to-br from-emerald-950/80 to-emerald-900/40 border border-emerald-500/40 rounded-lg p-3 flex flex-col items-center justify-center text-center shadow-inner relative overflow-hidden group hover:border-emerald-400 transition cursor-pointer" onclick="filterTableByColor('green')">
                        <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-emerald-500/10 rounded-full blur-xl pointer-events-none"></div>
                        <span class="text-3xl font-black text-emerald-400 font-mono tracking-tight" id="stat-green-pct">
                            0%
                        </span>
                        <span class="px-2 py-0.5 mt-1 bg-emerald-500/20 text-emerald-300 font-bold text-xs rounded uppercase tracking-wider">
                            UP
                        </span>
                        <div class="mt-2 text-xs text-emerald-200/90 font-medium space-y-0.5">
                            <div>Weight: <strong id="stat-green-weight" class="text-white">0.0%</strong></div>
                            <div><strong id="stat-green-count" class="text-white">0</strong> stocks</div>
                            <div class="text-[11px] text-emerald-300/80">Net: <span id="stat-green-pts">+0.0 pts</span></div>
                        </div>
                    </div>

                    <!-- RED (DOWN) Box -->
                    <div id="box-down" class="bg-gradient-to-br from-rose-950/80 to-rose-900/40 border border-rose-500/40 rounded-lg p-3 flex flex-col items-center justify-center text-center shadow-inner relative overflow-hidden group hover:border-rose-400 transition cursor-pointer" onclick="filterTableByColor('red')">
                        <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-rose-500/10 rounded-full blur-xl pointer-events-none"></div>
                        <span class="text-3xl font-black text-rose-400 font-mono tracking-tight" id="stat-red-pct">
                            0%
                        </span>
                        <span class="px-2 py-0.5 mt-1 bg-rose-500/20 text-rose-300 font-bold text-xs rounded uppercase tracking-wider">
                            DOWN
                        </span>
                        <div class="mt-2 text-xs text-rose-200/90 font-medium space-y-0.5">
                            <div>Weight: <strong id="stat-red-weight" class="text-white">0.0%</strong></div>
                            <div><strong id="stat-red-count" class="text-white">0</strong> stocks</div>
                            <div class="text-[11px] text-rose-300/80">Net: <span id="stat-red-pts">-0.0 pts</span></div>
                        </div>
                    </div>
                </div>

                <!-- Weight Breadth vs Count Breadth Bar -->
                <div class="mt-2 bg-[#1a1e29] border border-gray-800 rounded p-2 text-xs space-y-1.5">
                    <div class="flex justify-between items-center text-[11px] text-gray-400">
                        <span>Weight Influence (100% Index)</span>
                        <span id="stat-weight-split" class="font-mono text-gray-300">50% G / 50% R</span>
                    </div>
                    <!-- Dual-color weight bar -->
                    <div class="w-full h-2 bg-gray-800 rounded-full overflow-hidden flex">
                        <div id="stat-bar-green" class="h-full bg-emerald-500 transition-all duration-300" style="width: 50%;"></div>
                        <div id="stat-bar-red" class="h-full bg-rose-500 transition-all duration-300" style="width: 50%;"></div>
                    </div>
                </div>

                <!-- Institutional Seriousness / Heavyweight Divergence Alert -->
                <div id="stat-insight-card" class="mt-2.5 p-2.5 rounded border border-gray-700 bg-gray-800/40 text-xs">
                    <div class="flex items-center gap-1.5 font-bold" id="stat-insight-badge">
                        <span>⚖️ Analyzing Breadth Conviction...</span>
                    </div>
                    <p class="mt-1 text-gray-300 text-[11px] leading-relaxed" id="stat-insight-message">
                        Select any 5m candle to evaluate if heavyweights confirm or oppose the broader stock count.
                    </p>
                </div>
            </div>

            <!-- Quick Filter Buttons -->
            <div class="mt-3 pt-2 border-t border-gray-800 flex flex-wrap items-center justify-between gap-1 text-xs">
                <span class="text-gray-400 text-[11px]">Filter View:</span>
                <div class="flex flex-wrap gap-1">
                    <button class="filter-pill px-2 py-0.5 rounded bg-blue-600 text-white font-medium" data-filter="all">All 50</button>
                    <button class="filter-pill px-2 py-0.5 rounded bg-[#1a1e29] hover:bg-gray-700 text-gray-300 font-medium" data-filter="top50weight">Top 50% Wt</button>
                    <button class="filter-pill px-2 py-0.5 rounded bg-[#1a1e29] hover:bg-gray-700 text-gray-300 font-medium" data-filter="top10">Top 10</button>
                    <button class="filter-pill px-2 py-0.5 rounded bg-[#1a1e29] hover:bg-emerald-950 text-emerald-400 font-medium" data-filter="green">Green Only</button>
                    <button class="filter-pill px-2 py-0.5 rounded bg-[#1a1e29] hover:bg-rose-950 text-rose-400 font-medium" data-filter="red">Red Only</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Section: Stock Table with 5-Minute Heatmap Matrix (Left/Center) + Stock Points Waterfall (Right) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">

        <!-- Left/Center: Stock Table & 5-Min Heatmap Timeline Matrix (8 cols) -->
        <div class="lg:col-span-8 bg-[#131722] border border-gray-800 rounded-lg p-3 shadow-md flex flex-col">
            
            <!-- Table Controls: Search, Sector Filter, Sort -->
            <div class="flex flex-wrap items-center justify-between gap-2 pb-2 mb-2 border-b border-gray-800 text-xs">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-white text-sm">NIFTY 50 Constituents 5m Matrix</span>
                    <span id="filtered-stocks-count" class="px-1.5 py-0.5 bg-gray-800 text-gray-300 rounded text-[11px]">50 stocks</span>
                </div>
                
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Search Input -->
                    <div class="relative">
                        <input id="stock-search" type="text" placeholder="Search stock (e.g. HDFC)..." class="bg-[#1a1e29] border border-gray-700 rounded pl-7 pr-2 py-1 text-xs text-white focus:outline-none focus:border-blue-500 w-36 sm:w-44">
                        <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>

                    <!-- Sector Filter -->
                    <select id="sector-filter" class="bg-[#1a1e29] border border-gray-700 rounded px-2 py-1 text-xs text-gray-300 focus:outline-none">
                        <option value="all">All Sectors</option>
                        @foreach($sectors as $sec)
                            <option value="{{ $sec }}">{{ $sec }}</option>
                        @endforeach
                    </select>

                    <!-- Sort Dropdown -->
                    <select id="sort-select" class="bg-[#1a1e29] border border-gray-700 rounded px-2 py-1 text-xs text-gray-300 focus:outline-none">
                        <option value="weight_desc">Weight (High → Low)</option>
                        <option value="pts_desc">Contribution (Bullish First)</option>
                        <option value="pts_asc">Contribution (Bearish First)</option>
                        <option value="move_desc">5m Move % (Highest)</option>
                        <option value="color">Green First</option>
                        <option value="symbol_asc">Symbol (A-Z)</option>
                    </select>
                </div>
            </div>

            <!-- Heatmap Matrix Scroll Container -->
            <div class="w-full overflow-x-auto max-h-[580px] overflow-y-auto border border-gray-800/80 rounded bg-[#0b0e14]">
                <table class="matrix-table w-full text-xs text-left" id="matrix-table">
                    <thead class="sticky top-0 bg-[#131722] z-30 shadow">
                        <tr class="border-b border-gray-800 text-gray-400 text-[11px] uppercase">
                            <th class="matrix-sticky-col px-3 py-2 bg-[#131722] min-w-[120px] font-semibold border-r border-gray-800">
                                Stock
                            </th>
                            <th class="px-2 py-2 bg-[#131722] text-right min-w-[55px] font-semibold">
                                Weight
                            </th>
                            <th class="px-2 py-2 bg-[#131722] text-right min-w-[70px] font-semibold" id="th-pts-label">
                                09:15 Pts
                            </th>
                            <th class="px-2 py-2 bg-[#131722] text-right min-w-[60px] font-semibold border-r border-gray-800">
                                Move %
                            </th>
                            <!-- 75 5-minute candle timestamps columns -->
                            <th class="p-0 bg-[#131722]">
                                <div class="flex items-center" id="heatmap-time-header">
                                    <!-- Populated dynamically via JS -->
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="matrix-tbody" class="divide-y divide-gray-900/60 font-mono">
                        <!-- Populated dynamically via JS -->
                        <tr>
                            <td colspan="5" class="py-12 text-center text-gray-400 font-sans">
                                Loading Nifty 50 constituents matrix...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-2 flex items-center justify-between text-xs text-gray-400">
                <div class="flex items-center gap-3">
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded bg-emerald-500"></span> Green Candle (Bullish Close ≥ Open)</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded bg-rose-500"></span> Red Candle (Bearish Close &lt; Open)</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded bg-gray-600"></span> Flat / Doji</span>
                </div>
                <span>Click any heatmap cell or column to jump to that 5-minute time</span>
            </div>
        </div>

        <!-- Right: Stock-by-Stock Points Contribution Waterfall (4 cols) -->
        <div class="lg:col-span-4 bg-[#131722] border border-gray-800 rounded-lg p-3 shadow-md flex flex-col justify-between">
            <div>
                <!-- Header -->
                <div class="flex items-center justify-between pb-2 mb-2 border-b border-gray-800">
                    <div>
                        <span class="text-xs font-bold text-white flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            <span id="contrib-time-label">09:15</span> Contribution
                        </span>
                        <p class="text-[11px] text-gray-400">
                            Bar length = index points added / subtracted to Nifty
                        </p>
                    </div>
                    <div class="flex items-center gap-1">
                        <button id="contrib-tab-all" class="px-2 py-0.5 text-xs rounded bg-blue-600 text-white font-medium">All</button>
                        <button id="contrib-tab-leaders" class="px-2 py-0.5 text-xs rounded bg-[#1a1e29] hover:bg-gray-700 text-gray-300 font-medium">Leaders</button>
                        <button id="contrib-tab-draggers" class="px-2 py-0.5 text-xs rounded bg-[#1a1e29] hover:bg-gray-700 text-gray-300 font-medium">Draggers</button>
                    </div>
                </div>

                <!-- Stock Contribution Bars List (Scrollable) -->
                <div id="contrib-list" class="max-h-[520px] overflow-y-auto space-y-1.5 pr-1 font-mono text-xs">
                    <!-- Populated dynamically via JS -->
                    <div class="py-12 text-center text-gray-500 font-sans">
                        Awaiting candle selection...
                    </div>
                </div>
            </div>

            <!-- Bottom Sector Impact Summary -->
            <div class="mt-3 pt-2.5 border-t border-gray-800">
                <span class="text-xs font-bold text-gray-300 uppercase tracking-wider block mb-1">
                    Sector Leadership at <span id="sector-time-label" class="text-sky-400 font-mono">09:15</span>
                </span>
                <div id="sector-summary-list" class="grid grid-cols-2 gap-1.5 text-[11px]">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Micro Popover for Matrix Cell Hover -->
<div id="cell-popover" class="fixed hidden z-50 bg-[#1f2937] border border-gray-600 rounded-md p-2.5 shadow-xl text-xs font-mono pointer-events-none transform -translate-x-1/2 -translate-y-full mb-2">
    <div class="font-bold text-white text-sm mb-1" id="pop-symbol">HDFCBANK</div>
    <div class="text-gray-300" id="pop-time">09:15 (5m)</div>
    <div class="grid grid-cols-2 gap-x-3 gap-y-0.5 mt-1 text-gray-300 text-[11px]">
        <div>Open: <span id="pop-open" class="text-white"></span></div>
        <div>Close: <span id="pop-close" class="text-white"></span></div>
        <div>Move: <span id="pop-move" class="font-bold"></span></div>
        <div>Contrib: <span id="pop-pts" class="font-bold"></span></div>
    </div>
</div>

<!-- ========================================== -->
<!-- BREADTH EXTREME ALARM SETTINGS MODAL       -->
<!-- ========================================== -->
<div id="alarm-settings-modal" class="fixed inset-0 z-50 bg-black/75 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-[#131722] border border-gray-700 rounded-xl shadow-2xl w-full max-w-lg p-5 space-y-4">
        <!-- Modal Header -->
        <div class="flex items-center justify-between pb-3 border-b border-gray-800">
            <div class="flex items-center gap-2">
                <span class="text-xl">🔔</span>
                <div>
                    <h3 class="text-sm font-bold text-white">NIFTY 50 Breadth Extreme Alarm</h3>
                    <p class="text-xs text-gray-400">Continuous audio alarm until manually stopped or ESC pressed</p>
                </div>
            </div>
            <button id="btn-close-alarm-modal" class="p-1 text-gray-400 hover:text-white rounded hover:bg-gray-800">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Alarm Enable Toggle -->
        <div class="flex items-center justify-between p-3 rounded-lg bg-[#1a1e29] border border-gray-800">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-white block">Alarm Active Status</span>
                    <span id="alarm-status-badge" class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-950 text-emerald-400 border border-emerald-600/40">ENABLED</span>
                </div>
                <span class="text-[11px] text-gray-400 block mt-0.5">Toggle OFF to mute all sounds and popups during testing</span>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" id="alarm-enabled-toggle" class="sr-only peer" checked>
                <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
            </label>
        </div>

        <!-- Threshold Adjustments (Up % and Down %) -->
        <div class="space-y-3">
            <!-- UP / Green Threshold -->
            <div class="p-3 rounded-lg bg-[#1a1e29] border border-emerald-900/40 space-y-1.5">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-bold text-emerald-400 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        UP (Green) Threshold
                    </span>
                    <div class="flex items-center gap-1">
                        <input type="number" id="alarm-up-num" min="50" max="98" value="80" class="w-16 bg-[#131722] border border-emerald-700/60 rounded px-2 py-0.5 text-center font-mono font-bold text-emerald-400 text-xs focus:outline-none">
                        <span class="text-emerald-400 font-bold">%</span>
                    </div>
                </div>
                <input type="range" id="alarm-up-slider" min="50" max="98" value="80" class="w-full h-1.5 bg-gray-800 rounded-lg appearance-none cursor-pointer accent-emerald-500">
                <div class="flex justify-between text-[10px] text-gray-500 font-mono">
                    <span>50%</span>
                    <span>80% (Default)</span>
                    <span>95%</span>
                </div>
            </div>

            <!-- DOWN / Red Threshold -->
            <div class="p-3 rounded-lg bg-[#1a1e29] border border-rose-900/40 space-y-1.5">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-bold text-rose-400 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                        DOWN (Red) Threshold
                    </span>
                    <div class="flex items-center gap-1">
                        <input type="number" id="alarm-down-num" min="50" max="98" value="80" class="w-16 bg-[#131722] border border-rose-700/60 rounded px-2 py-0.5 text-center font-mono font-bold text-rose-400 text-xs focus:outline-none">
                        <span class="text-rose-400 font-bold">%</span>
                    </div>
                </div>
                <input type="range" id="alarm-down-slider" min="50" max="98" value="80" class="w-full h-1.5 bg-gray-800 rounded-lg appearance-none cursor-pointer accent-rose-500">
                <div class="flex justify-between text-[10px] text-gray-500 font-mono">
                    <span>50%</span>
                    <span>80% (Default)</span>
                    <span>95%</span>
                </div>
            </div>
        </div>

        <!-- Trigger Calculation Metric -->
        <div class="p-3 rounded-lg bg-[#1a1e29] border border-gray-800 space-y-1 text-xs">
            <span class="font-bold text-gray-300 block mb-1">Trigger Metric Criteria:</span>
            <div class="grid grid-cols-3 gap-2">
                <label class="flex items-center gap-1.5 cursor-pointer text-[11px] text-gray-300 p-1.5 rounded hover:bg-gray-800/80">
                    <input type="radio" name="trigger-by" value="either" checked class="text-blue-500 accent-blue-500">
                    <span>Either (Count or Wt)</span>
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer text-[11px] text-gray-300 p-1.5 rounded hover:bg-gray-800/80">
                    <input type="radio" name="trigger-by" value="count" class="text-blue-500 accent-blue-500">
                    <span>Stock Count %</span>
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer text-[11px] text-gray-300 p-1.5 rounded hover:bg-gray-800/80">
                    <input type="radio" name="trigger-by" value="weight" class="text-blue-500 accent-blue-500">
                    <span>Weight % Only</span>
                </label>
            </div>
        </div>

        <!-- 10 Selectable Sounds & Live Test Preview -->
        <div class="p-3 rounded-lg bg-[#1a1e29] border border-gray-800 space-y-2 text-xs">
            <div class="flex items-center justify-between">
                <span class="font-bold text-white flex items-center gap-1">
                    <span>🎵 Alarm Sound:</span>
                    <span class="text-[10px] font-normal text-gray-400">(10 Tones)</span>
                </span>
                <div class="flex items-center gap-1.5">
                    <button id="btn-test-sound" title="Preview audio tone" class="px-2 py-0.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded flex items-center gap-1 text-[11px] transition shadow">
                        <span>▶ Tone</span>
                    </button>
                    <button id="btn-test-bull-modal" title="Test Bull Alarm Modal" class="px-2 py-0.5 bg-emerald-950/80 hover:bg-emerald-900 border border-emerald-600/60 text-emerald-300 font-bold rounded flex items-center gap-1 text-[11px] transition shadow">
                        <span>🐂 Bull</span>
                    </button>
                    <button id="btn-test-bear-modal" title="Test Bear Alarm Modal" class="px-2 py-0.5 bg-rose-950/80 hover:bg-rose-900 border border-rose-600/60 text-rose-300 font-bold rounded flex items-center gap-1 text-[11px] transition shadow">
                        <span>🐻 Bear</span>
                    </button>
                </div>
            </div>
            
            <select id="alarm-sound-select" class="w-full bg-[#131722] border border-gray-700 rounded px-2.5 py-1.5 text-xs text-white focus:outline-none cursor-pointer font-medium">
                <option value="siren">1. 🚨 Siren Warning (Emergency Warble)</option>
                <option value="chime">2. 🔔 Classic Bell Chime (Harmonic Twin Bells)</option>
                <option value="pulse">3. ⚡ Digital Cyber Pulse (High-Tech Double Beep)</option>
                <option value="gong">4. 💥 Deep Resonant Gong (Sub-Bass Strike)</option>
                <option value="bull_horn">5. 🚀 Bull Horn Fanfare (Ascending Major Chord)</option>
                <option value="bear_drop">6. ⚠️ Bear Drop Klaxon (Descending Warning)</option>
                <option value="sonar">7. 🎵 Radar Sonar Ping (Submarine Echo)</option>
                <option value="rapid_beeps">8. 🔥 Rapid Fire Trader Pips (Fast 4-Tone Alert)</option>
                <option value="fanfare">9. 🎺 Trumpet Breakout (Bright Brass Fanfare)</option>
                <option value="caution_klaxon">10. 🛑 Urgent Caution Buzzer (Industrial Sawtooth)</option>
            </select>

            <!-- Volume Slider -->
            <div class="flex items-center justify-between pt-1 text-[11px] text-gray-400">
                <span>Sound Volume:</span>
                <div class="flex items-center gap-2 w-48">
                    <input type="range" id="alarm-volume-slider" min="10" max="100" value="80" class="w-full h-1 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-blue-500">
                    <span id="alarm-vol-label" class="font-mono text-white text-[10px] w-6 text-right">80%</span>
                </div>
            </div>
        </div>

        <!-- Modal Footer Actions -->
        <div class="flex items-center justify-between pt-2 border-t border-gray-800 text-xs">
            <span class="text-[11px] text-gray-400">Preferences saved locally in browser</span>
            <div class="flex items-center gap-2">
                <button id="btn-save-alarm-settings" class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded shadow transition">
                    Save &amp; Apply
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- ACTIVE TRIGGERED ALARM POPUP (CONTINUOUS)  -->
<!-- ========================================== -->
<div id="active-alarm-overlay" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md hidden flex items-center justify-center p-4">
    <div id="active-alarm-card" class="rounded-3xl p-6 sm:p-8 max-w-md w-full text-center space-y-4 shadow-2xl relative transition-all duration-300">
        <!-- Top Pill Badge (Cleanly inside card, no overlap) -->
        <div class="flex justify-center">
            <div id="alarm-banner-badge" class="px-4 py-1 rounded-full font-black text-xs uppercase tracking-widest text-white shadow-md bg-black/35 border border-white/20 flex items-center gap-2">
                <span id="alarm-badge-left-icon">🐂</span>
                <span id="alarm-badge-title">BULLISH BREADTH EXTREME</span>
                <span id="alarm-badge-right-icon">🐂</span>
            </div>
        </div>

        <!-- Distinct Sentiment Icon (Bull 🐂 or Bear 🐻) -->
        <div class="flex justify-center">
            <div id="alarm-sentiment-icon" class="w-20 h-20 rounded-full flex items-center justify-center text-4xl sm:text-5xl shadow-xl border-2 select-none">
                🐂
            </div>
        </div>

        <!-- Metrics & Breadth Breakdown -->
        <div class="space-y-1">
            <div class="text-5xl sm:text-6xl font-black font-mono tracking-tight text-white drop-shadow-md" id="alarm-active-pct">
                84%
            </div>
            <div class="text-base sm:text-lg font-black uppercase tracking-wider text-white drop-shadow-sm" id="alarm-active-direction">
                BULLISH SURGE DETECTED
            </div>
            <p class="text-xs sm:text-sm text-white/90 font-medium max-w-sm mx-auto leading-relaxed pt-1" id="alarm-active-details">
                At 09:55 - 42 stocks Green (87.2% weight) · Threshold 80% breached!
            </p>
        </div>

        <!-- Clean Dark/Bordered Stop Alarm Button -->
        <div class="pt-2 flex justify-center">
            <button id="btn-stop-active-alarm" class="px-6 py-2.5 bg-gray-950/80 hover:bg-black text-white font-bold text-xs sm:text-sm rounded-xl border border-white/30 hover:border-white/60 shadow-xl transition inline-flex items-center gap-2 cursor-pointer">
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
@endsection

@push('scripts')
<script>
    (function() {
        // App State
        let currentData = null;
        let selectedCandleIndex = 0;
        let selectedTime = "09:15";
        let isPlaying = false;
        let playInterval = null;
        let activeFilter = 'all';
        let activeContribTab = 'all';
        let searchQuery = '';
        let sectorFilter = 'all';
        let sortMode = 'weight_desc';

        // Breadth Extreme Alarm State
        const defaultAlarmSettings = {
            enabled: true,
            upThreshold: 80,
            downThreshold: 80,
            triggerBy: 'either', // 'either', 'count', 'weight'
            sound: 'siren',
            volume: 0.8
        };

        let alarmSettings = { ...defaultAlarmSettings };
        let alarmAudioLoopInterval = null;
        let isAlarmRinging = false;
        let lastAlertedCandleKey = null;

        // Load saved alarm settings from localStorage
        try {
            const saved = localStorage.getItem('nifty_breadth_alarm_settings');
            if (saved) {
                alarmSettings = { ...alarmSettings, ...JSON.parse(saved) };
            }
        } catch (e) {
            console.warn('Could not read alarm settings:', e);
        }

        // Lightweight Charts instance
        let chart = null;
        let candleSeries = null;

        // Elements
        const dateSelect = document.getElementById('date-select');
        const autoRefreshSelect = document.getElementById('auto-refresh-select');
        const btnReload = document.getElementById('btn-reload');
        const btnPlayPause = document.getElementById('btn-play-pause');
        const btnStepPrev = document.getElementById('btn-step-prev');
        const btnStepNext = document.getElementById('btn-step-next');
        const playbackSpeed = document.getElementById('playback-speed');
        const candleSlider = document.getElementById('candle-slider');
        const stockSearch = document.getElementById('stock-search');
        const sectorFilterEl = document.getElementById('sector-filter');
        const sortSelect = document.getElementById('sort-select');
        const btnZen = document.getElementById('btn-zen');

        // Alarm UI Elements
        const btnOpenAlarm = document.getElementById('btn-open-alarm');
        const alarmBtnLabel = document.getElementById('alarm-btn-label');
        const alarmSettingsModal = document.getElementById('alarm-settings-modal');
        const btnCloseAlarmModal = document.getElementById('btn-close-alarm-modal');
        const btnSaveAlarmSettings = document.getElementById('btn-save-alarm-settings');
        const btnTestSound = document.getElementById('btn-test-sound');
        const btnTestBullModal = document.getElementById('btn-test-bull-modal');
        const btnTestBearModal = document.getElementById('btn-test-bear-modal');
        const alarmEnabledToggle = document.getElementById('alarm-enabled-toggle');
        const alarmUpNum = document.getElementById('alarm-up-num');
        const alarmUpSlider = document.getElementById('alarm-up-slider');
        const alarmDownNum = document.getElementById('alarm-down-num');
        const alarmDownSlider = document.getElementById('alarm-down-slider');
        const alarmSoundSelect = document.getElementById('alarm-sound-select');
        const alarmVolumeSlider = document.getElementById('alarm-volume-slider');
        const alarmVolLabel = document.getElementById('alarm-vol-label');
        const activeAlarmOverlay = document.getElementById('active-alarm-overlay');
        const btnStopActiveAlarm = document.getElementById('btn-stop-active-alarm');

        // Sync Alarm UI with loaded settings
        syncAlarmSettingsUI();

        // Init App
        initChart();
        loadData();

        // Quick 1-Click Alarm Mute / Unmute in Top Toolbar (Convenient for Testing)
        const btnQuickToggleAlarm = document.getElementById('btn-quick-toggle-alarm');
        if (btnQuickToggleAlarm) {
            btnQuickToggleAlarm.addEventListener('click', () => {
                alarmSettings.enabled = !alarmSettings.enabled;
                if (!alarmSettings.enabled) {
                    stopAlarm();
                }
                alarmEnabledToggle.checked = alarmSettings.enabled;
                syncAlarmStatusBadge();
                updateAlarmToolbarButton();
                try {
                    localStorage.setItem('nifty_breadth_alarm_settings', JSON.stringify(alarmSettings));
                } catch (e) {}
            });
        }

        // Alarm Enable Toggle inside Settings Modal
        alarmEnabledToggle.addEventListener('change', (e) => {
            alarmSettings.enabled = e.target.checked;
            if (!alarmSettings.enabled) {
                stopAlarm();
            }
            syncAlarmStatusBadge();
            updateAlarmToolbarButton();
            try {
                localStorage.setItem('nifty_breadth_alarm_settings', JSON.stringify(alarmSettings));
            } catch (err) {}
        });

        // Alarm Settings Listeners
        btnOpenAlarm.addEventListener('click', () => {
            syncAlarmSettingsUI();
            alarmSettingsModal.classList.remove('hidden');
        });
        btnCloseAlarmModal.addEventListener('click', () => {
            alarmSettingsModal.classList.add('hidden');
        });
        alarmSettingsModal.addEventListener('click', (e) => {
            if (e.target === alarmSettingsModal) alarmSettingsModal.classList.add('hidden');
        });

        // Sliders & Number inputs sync
        alarmUpSlider.addEventListener('input', (e) => {
            alarmUpNum.value = e.target.value;
        });
        alarmUpNum.addEventListener('input', (e) => {
            let val = parseInt(e.target.value) || 50;
            val = Math.max(50, Math.min(98, val));
            alarmUpSlider.value = val;
        });

        alarmDownSlider.addEventListener('input', (e) => {
            alarmDownNum.value = e.target.value;
        });
        alarmDownNum.addEventListener('input', (e) => {
            let val = parseInt(e.target.value) || 50;
            val = Math.max(50, Math.min(98, val));
            alarmDownSlider.value = val;
        });

        alarmVolumeSlider.addEventListener('input', (e) => {
            alarmVolLabel.textContent = `${e.target.value}%`;
        });

        // Test Sound Button
        btnTestSound.addEventListener('click', () => {
            const chosen = alarmSoundSelect.value;
            const vol = parseInt(alarmVolumeSlider.value) / 100;
            playSoundByName(chosen, vol);
        });

        // Test Bull / Bear Alarm Modal Previews
        if (btnTestBullModal) {
            btnTestBullModal.addEventListener('click', () => {
                triggerAlarm({
                    direction: 'UP',
                    score: 86,
                    threshold: alarmSettings.upThreshold,
                    time: '11:45',
                    count: 43,
                    weight: 88.4,
                    pts: 142.5,
                    topStock: 'RELIANCE'
                });
            });
        }
        if (btnTestBearModal) {
            btnTestBearModal.addEventListener('click', () => {
                triggerAlarm({
                    direction: 'DOWN',
                    score: 84,
                    threshold: alarmSettings.downThreshold,
                    time: '14:20',
                    count: 41,
                    weight: 85.2,
                    pts: -168.0,
                    topStock: 'HDFCBANK'
                });
            });
        }

        // Save Alarm Settings
        btnSaveAlarmSettings.addEventListener('click', () => {
            alarmSettings.enabled = alarmEnabledToggle.checked;
            alarmSettings.upThreshold = parseInt(alarmUpNum.value) || 80;
            alarmSettings.downThreshold = parseInt(alarmDownNum.value) || 80;
            alarmSettings.sound = alarmSoundSelect.value;
            alarmSettings.volume = parseInt(alarmVolumeSlider.value) / 100;

            const selectedTrigger = document.querySelector('input[name="trigger-by"]:checked');
            if (selectedTrigger) {
                alarmSettings.triggerBy = selectedTrigger.value;
            }

            try {
                localStorage.setItem('nifty_breadth_alarm_settings', JSON.stringify(alarmSettings));
            } catch (e) {}

            updateAlarmToolbarButton();
            alarmSettingsModal.classList.add('hidden');
        });

        // Stop Active Alarm Button & ESC key
        btnStopActiveAlarm.addEventListener('click', stopAlarm);
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isAlarmRinging) {
                stopAlarm();
            }
        });

        // Event Listeners
        dateSelect.addEventListener('change', () => loadData());
        btnReload.addEventListener('click', () => loadData());
        
        candleSlider.addEventListener('input', (e) => {
            const idx = parseInt(e.target.value);
            updateSliderBubble(idx);
            selectCandleByIndex(idx);
        });

        btnStepPrev.addEventListener('click', () => stepCandle(-1));
        btnStepNext.addEventListener('click', () => stepCandle(1));

        btnPlayPause.addEventListener('click', togglePlayback);

        stockSearch.addEventListener('input', (e) => {
            searchQuery = e.target.value.trim().toUpperCase();
            renderMatrixTable();
        });

        sectorFilterEl.addEventListener('change', (e) => {
            sectorFilter = e.target.value;
            renderMatrixTable();
        });

        sortSelect.addEventListener('change', (e) => {
            sortMode = e.target.value;
            renderMatrixTable();
        });

        // Quick filter pills
        document.querySelectorAll('.filter-pill').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.filter-pill').forEach(b => {
                    b.classList.remove('bg-blue-600', 'text-white');
                    b.classList.add('bg-[#1a1e29]', 'text-gray-300');
                });
                btn.classList.remove('bg-[#1a1e29]', 'text-gray-300');
                btn.classList.add('bg-blue-600', 'text-white');
                activeFilter = btn.dataset.filter;
                renderMatrixTable();
            });
        });

        // Contrib tabs
        document.getElementById('contrib-tab-all').addEventListener('click', () => switchContribTab('all'));
        document.getElementById('contrib-tab-leaders').addEventListener('click', () => switchContribTab('leaders'));
        document.getElementById('contrib-tab-draggers').addEventListener('click', () => switchContribTab('draggers'));

        // Snap to Live Button
        const btnSnapLive = document.getElementById('btn-snap-live');
        if (btnSnapLive) {
            btnSnapLive.addEventListener('click', () => {
                if (currentData && currentData.timestamps.length > 0) {
                    selectCandleByIndex(currentData.timestamps.length - 1);
                }
            });
        }

        // Zen mode toggle
        btnZen.addEventListener('click', () => {
            document.body.classList.toggle('zen-mode');
            setTimeout(() => {
                if (chart) {
                    chart.applyOptions({
                        width: document.getElementById('nifty-chart-container').clientWidth,
                    });
                }
            }, 250);
        });

        // Keyboard navigation (Left / Right arrow keys)
        window.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                stepCandle(-1);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                stepCandle(1);
            } else if (e.key === ' ') {
                e.preventDefault();
                togglePlayback();
            }
        });

        // Window resize handler
        window.addEventListener('resize', () => {
            if (chart) {
                chart.applyOptions({
                    width: document.getElementById('nifty-chart-container').clientWidth,
                });
            }
        });

        // Auto Refresh Timer with Live Countdown & 5-Min Candle Sync
        let autoRefreshTimer = null;
        let countdownSeconds = 30;
        const countdownBadge = document.getElementById('countdown-badge');

        autoRefreshSelect.addEventListener('change', setupAutoRefresh);
        setupAutoRefresh();

        function setupAutoRefresh() {
            if (autoRefreshTimer) clearInterval(autoRefreshTimer);
            const intervalSecs = parseInt(autoRefreshSelect.value);

            if (intervalSecs <= 0) {
                if (countdownBadge) countdownBadge.textContent = 'Off';
                return;
            }

            countdownSeconds = intervalSecs;
            if (countdownBadge) countdownBadge.textContent = `${countdownSeconds}s`;

            autoRefreshTimer = setInterval(() => {
                countdownSeconds--;
                if (countdownSeconds <= 0) {
                    countdownSeconds = intervalSecs;
                    loadData(true); // Silent background auto-refresh
                }
                if (countdownBadge) countdownBadge.textContent = `${countdownSeconds}s`;
            }, 1000);
        }

        // Initialize TradingView Lightweight Chart
        function initChart() {
            const container = document.getElementById('nifty-chart-container');
            chart = LightweightCharts.createChart(container, {
                width: container.clientWidth,
                height: container.clientHeight,
                layout: {
                    background: { color: '#131722' },
                    textColor: '#9ca3af',
                    fontFamily: 'Inter, system-ui, -apple-system, sans-serif',
                },
                grid: {
                    vertLines: { color: '#1f2937' },
                    horzLines: { color: '#1f2937' },
                },
                crosshair: {
                    mode: LightweightCharts.CrosshairMode.Normal,
                    vertLine: {
                        color: '#38bdf8',
                        width: 1,
                        style: LightweightCharts.LineStyle.Dashed,
                        labelBackgroundColor: '#0284c7',
                    },
                    horzLine: {
                        color: '#38bdf8',
                        width: 1,
                        style: LightweightCharts.LineStyle.Dashed,
                        labelBackgroundColor: '#0284c7',
                    },
                },
                timeScale: {
                    borderColor: '#374151',
                    timeVisible: true,
                    secondsVisible: false,
                },
                rightPriceScale: {
                    borderColor: '#374151',
                    scaleMargins: {
                        top: 0.1,
                        bottom: 0.1,
                    },
                },
            });

            candleSeries = chart.addCandlestickSeries({
                upColor: '#10b981',
                downColor: '#ef4444',
                borderVisible: false,
                wickUpColor: '#10b981',
                wickDownColor: '#ef4444',
            });

            // Handle clicking directly on candles in the chart!
            chart.subscribeClick((param) => {
                if (!param || !param.time || !currentData) return;
                const clickedCandle = currentData.nifty_candles.find(c => c.timestamp === param.time);
                if (clickedCandle) {
                    const idx = currentData.nifty_candles.indexOf(clickedCandle);
                    if (idx !== -1) {
                        selectCandleByIndex(idx);
                    }
                }
            });

            // Handle crosshair move for instant status readout
            chart.subscribeCrosshairMove((param) => {
                if (!param || !param.time || !currentData) return;
                const candle = currentData.nifty_candles.find(c => c.timestamp === param.time);
                if (candle) {
                    updateChartHeaderReadout(candle);
                }
            });
        }

        // Fetch Data from Server
        function loadData(isSilent = false) {
            const date = dateSelect.value;
            const loadingEl = document.getElementById('chart-loading');
            if (!isSilent) loadingEl.style.display = 'flex';

            fetch(`/api/nifty-weightage-monitor/data?date=${date}`)
                .then(res => res.json())
                .then(data => {
                    loadingEl.style.display = 'none';
                    if (!data.success) {
                        alert('Could not load data for date: ' + date);
                        return;
                    }

                    // Update Live/Historical status badge
                    const badge = document.getElementById('session-badge');
                    if (data.is_live) {
                        badge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full bg-emerald-400 live-dot"></span> LIVE MARKET (${data.server_time})`;
                        badge.className = 'px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-950 text-emerald-400 border border-emerald-600/40 flex items-center gap-1.5';
                    } else if (data.is_today) {
                        badge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> TODAY SESSION`;
                        badge.className = 'px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-950/60 text-emerald-400 border border-emerald-600/30 flex items-center gap-1.5';
                    } else {
                        badge.innerHTML = `HISTORICAL (${data.date})`;
                        badge.className = 'px-2 py-0.5 text-xs font-medium rounded-full bg-gray-800 text-gray-300 border border-gray-700 flex items-center gap-1.5';
                    }

                    const isFirstLoad = !currentData;
                    const prevCount = currentData ? currentData.timestamps.length : 0;
                    const wasAtLatest = isFirstLoad || (selectedCandleIndex >= prevCount - 1);

                    currentData = data;

                    // Populate chart data
                    candleSeries.setData(data.lw_chart_candles);
                    chart.timeScale().fitContent();

                    // Update slider range & boundary time labels
                    candleSlider.max = Math.max(0, data.timestamps.length - 1);
                    if (data.timestamps.length > 0) {
                        const startLbl = document.getElementById('slider-start-label');
                        const endLbl = document.getElementById('slider-end-label');
                        if (startLbl) startLbl.textContent = data.timestamps[0];
                        if (endLbl) endLbl.textContent = data.timestamps[data.timestamps.length - 1];
                    }

                    // If user was tracking the latest candle, advance with the live stream
                    if (wasAtLatest) {
                        selectedCandleIndex = data.timestamps.length - 1;
                    } else if (selectedCandleIndex >= data.timestamps.length) {
                        selectedCandleIndex = data.timestamps.length - 1;
                    }

                    // Render heatmap timeline header
                    renderHeatmapTimeHeader();

                    // Select candle
                    selectCandleByIndex(selectedCandleIndex);
                })
                .catch(err => {
                    loadingEl.style.display = 'none';
                    console.error('Error fetching data:', err);
                });
        }

        // Update Floating Time Bubble Directly Above the Slider Dot
        function updateSliderBubble(index) {
            if (!currentData || !currentData.timestamps[index]) return;
            const timeStr = currentData.timestamps[index];
            const bubbleTime = document.getElementById('slider-bubble-time');
            const bubble = document.getElementById('slider-time-bubble');
            if (bubbleTime) bubbleTime.textContent = timeStr;

            const min = parseInt(candleSlider.min) || 0;
            const max = parseInt(candleSlider.max) || 1;
            const pct = max > min ? ((index - min) / (max - min)) * 100 : 0;

            if (bubble) {
                // Compensates for browser range slider thumb width (~16px)
                bubble.style.left = `calc(${pct}% + (${8 - pct * 0.16}px))`;
            }
        }

        // Select a candle by index (0 to 74)
        function selectCandleByIndex(index) {
            if (!currentData || !currentData.timestamps[index]) return;
            selectedCandleIndex = index;
            selectedTime = currentData.timestamps[index];
            candleSlider.value = index;

            // Move the floating time bubble directly above the slider dot!
            updateSliderBubble(index);

            const nCandle = currentData.nifty_candles[index];
            if (nCandle) {
                updateChartHeaderReadout(nCandle);

                // Anchor a prominent arrow marker & label directly on this candle on the chart!
                // Remains pinned to this exact candle even when zooming in/out or panning!
                if (candleSeries) {
                    candleSeries.setMarkers([
                        {
                            time: nCandle.timestamp,
                            position: 'aboveBar',
                            color: '#38bdf8',
                            shape: 'arrowDown',
                            text: `${nCandle.time_str} (${nCandle.move_pts >= 0 ? '+' : ''}${nCandle.move_pts})`,
                        }
                    ]);
                }
            }

            // Toggle snap-to-live button if looking at earlier candles
            const btnSnapLive = document.getElementById('btn-snap-live');
            if (btnSnapLive) {
                if (index < currentData.timestamps.length - 1) {
                    btnSnapLive.classList.remove('hidden');
                    btnSnapLive.classList.add('inline-flex');
                } else {
                    btnSnapLive.classList.remove('inline-flex');
                    btnSnapLive.classList.add('hidden');
                }
            }

            // Update Right Side Breadth Stats
            updateBreadthStats();

            // Update Heatmap Highlights & Table values
            renderMatrixTable();

            // Update Stock Points Waterfall
            renderStockContributions();

            // Highlight Active Column in Heatmap Header
            document.querySelectorAll('.time-header-cell').forEach((el, idx) => {
                if (idx === index) {
                    el.classList.add('bg-sky-500', 'text-white', 'font-bold');
                    el.classList.remove('text-gray-400');
                    el.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                } else {
                    el.classList.remove('bg-sky-500', 'text-white', 'font-bold');
                    el.classList.add('text-gray-400');
                }
            });
        }

        // Step candle by delta (-1 or +1)
        function stepCandle(delta) {
            if (!currentData) return;
            let nextIdx = selectedCandleIndex + delta;
            if (nextIdx < 0) nextIdx = 0;
            if (nextIdx >= currentData.timestamps.length) nextIdx = currentData.timestamps.length - 1;
            selectCandleByIndex(nextIdx);
        }

        // Play / Pause Replay
        function togglePlayback() {
            isPlaying = !isPlaying;
            const icon = document.getElementById('play-icon');
            const text = document.getElementById('play-text');

            if (isPlaying) {
                icon.textContent = '⏸';
                text.textContent = 'Pause';
                const speed = parseInt(playbackSpeed.value);
                playInterval = setInterval(() => {
                    if (selectedCandleIndex >= currentData.timestamps.length - 1) {
                        selectedCandleIndex = 0; // Loop or stop
                    } else {
                        selectedCandleIndex++;
                    }
                    selectCandleByIndex(selectedCandleIndex);
                }, speed);
            } else {
                icon.textContent = '▶';
                text.textContent = 'Play';
                if (playInterval) clearInterval(playInterval);
            }
        }

        // Update Chart Header Readout
        function updateChartHeaderReadout(candle) {
            document.getElementById('chart-selected-time').textContent = candle.time_str;
            document.getElementById('chart-open').textContent = candle.open.toFixed(2);
            document.getElementById('chart-close').textContent = candle.close.toFixed(2);

            const moveEl = document.getElementById('chart-move');
            const sign = candle.move_pts >= 0 ? '+' : '';
            moveEl.textContent = `${sign}${candle.move_pts.toFixed(2)} (${sign}${candle.move_pct.toFixed(2)}%)`;
            moveEl.className = candle.move_pts >= 0 ? 'font-mono font-bold text-emerald-400' : 'font-mono font-bold text-rose-400';
        }

        // Update Right Side Breadth & Weightage Sentiment Card
        function updateBreadthStats() {
            const b = currentData.breadth_by_time[selectedTime];
            if (!b) return;

            document.getElementById('stat-time-label').textContent = `${selectedTime} - 5-min candle`;

            // Net Points
            const netPtsEl = document.getElementById('stat-net-pts');
            const sign = b.nifty_move_pts >= 0 ? '+' : '';
            netPtsEl.textContent = `${sign}${b.nifty_move_pts.toFixed(2)} pts (${sign}${b.nifty_move_pct.toFixed(2)}%)`;
            netPtsEl.className = b.nifty_move_pts >= 0 ? 'text-xl font-black font-mono text-emerald-400' : 'text-xl font-black font-mono text-rose-400';

            // Green / Red percentage and counts
            document.getElementById('stat-green-pct').textContent = `${b.green_count_pct}%`;
            document.getElementById('stat-red-pct').textContent = `${b.red_count_pct}%`;

            document.getElementById('stat-green-count').textContent = b.green_count;
            document.getElementById('stat-red-count').textContent = b.red_count;

            document.getElementById('stat-green-weight').textContent = `${b.green_weight.toFixed(1)}%`;
            document.getElementById('stat-red-weight').textContent = `${b.red_weight.toFixed(1)}%`;

            document.getElementById('stat-green-pts').textContent = `+${b.net_green_pts.toFixed(1)} pts`;
            document.getElementById('stat-red-pts').textContent = `${b.net_red_pts.toFixed(1)} pts`;

            // Dual color weight bar
            document.getElementById('stat-bar-green').style.width = `${b.green_weight}%`;
            document.getElementById('stat-bar-red').style.width = `${b.red_weight}%`;
            document.getElementById('stat-weight-split').textContent = `${b.green_weight.toFixed(0)}% G / ${b.red_weight.toFixed(0)}% R`;

            // Insight card
            const ins = b.insight;
            const badgeEl = document.getElementById('stat-insight-badge');
            const msgEl = document.getElementById('stat-insight-message');
            const cardEl = document.getElementById('stat-insight-card');

            badgeEl.innerHTML = ins.badge;
            msgEl.textContent = ins.message;

            // Insight styling
            if (ins.type === 'DIVERGENCE_BEARISH') {
                cardEl.className = 'mt-2.5 p-2.5 rounded border border-amber-500/50 bg-amber-950/30 text-amber-200 text-xs';
            } else if (ins.type === 'DIVERGENCE_BULLISH') {
                cardEl.className = 'mt-2.5 p-2.5 rounded border border-cyan-500/50 bg-cyan-950/30 text-cyan-200 text-xs';
            } else if (ins.type === 'STRONG_BULLISH') {
                cardEl.className = 'mt-2.5 p-2.5 rounded border border-emerald-500/50 bg-emerald-950/30 text-emerald-200 text-xs';
            } else if (ins.type === 'STRONG_BEARISH') {
                cardEl.className = 'mt-2.5 p-2.5 rounded border border-rose-500/50 bg-rose-950/30 text-rose-200 text-xs';
            } else {
                cardEl.className = 'mt-2.5 p-2.5 rounded border border-gray-700 bg-gray-800/40 text-gray-300 text-xs';
            }

            // Update Sector Leadership list
            renderSectorSummary(b.sector_summary);

            // Check if Breadth Alarm conditions are met!
            checkAlarmConditions(b);
        }

        // Render Heatmap Time Header (Dual-Deck: Hour Groups + 5m Candle Notches)
        function renderHeatmapTimeHeader() {
            const container = document.getElementById('heatmap-time-header');
            container.innerHTML = '';
            container.className = 'flex flex-col';

            const timestamps = currentData.timestamps;
            if (!timestamps || timestamps.length === 0) return;

            // 1. Top Deck: Hour Bands
            const topRow = document.createElement('div');
            topRow.className = 'flex items-center border-b border-gray-800 text-[10px] text-gray-400 font-mono';
            
            // Group timestamps by hour
            let hourGroups = [];
            let curHour = null;
            let curCount = 0;
            
            timestamps.forEach((t) => {
                const hour = t.substring(0, 2);
                if (hour !== curHour) {
                    if (curHour !== null) {
                        hourGroups.push({ hour: curHour, count: curCount });
                    }
                    curHour = hour;
                    curCount = 1;
                } else {
                    curCount++;
                }
            });
            if (curHour !== null) {
                hourGroups.push({ hour: curHour, count: curCount });
            }

            hourGroups.forEach(grp => {
                const hourDiv = document.createElement('div');
                hourDiv.className = 'py-0.5 border-r border-gray-800/80 bg-[#161b26] text-center font-bold text-gray-300 truncate';
                hourDiv.style.width = `${grp.count * 14}px`;
                hourDiv.textContent = `${grp.hour}:00`;
                hourDiv.title = `Hour ${grp.hour}:00 (${grp.count} five-min candles)`;
                topRow.appendChild(hourDiv);
            });

            // 2. Bottom Deck: 5-Minute Candle markers (Every single column has a clear tick/marker)
            const bottomRow = document.createElement('div');
            bottomRow.className = 'flex items-center';

            timestamps.forEach((t, idx) => {
                const col = document.createElement('div');
                col.className = 'time-header-cell text-[9px] text-gray-400 font-mono flex items-center justify-center cursor-pointer hover:bg-gray-700 transition flex-shrink-0';
                col.style.width = '14px';
                col.style.height = '20px';

                const minute = t.substring(3, 5);
                if (minute === '00') {
                    col.textContent = '00';
                    col.className += ' font-bold text-gray-200 bg-gray-800/40';
                } else if (minute === '30') {
                    col.textContent = '30';
                    col.className += ' font-semibold text-gray-300 bg-gray-800/20';
                } else if (minute === '15' || minute === '45') {
                    col.textContent = '|';
                    col.className += ' text-gray-400 font-bold';
                } else {
                    col.textContent = '·';
                    col.className += ' text-gray-500 font-black';
                }

                col.title = `Time: ${t} (5m candle - Click to inspect)`;
                col.addEventListener('click', () => selectCandleByIndex(idx));
                bottomRow.appendChild(col);
            });

            container.appendChild(topRow);
            container.appendChild(bottomRow);
        }

        // Render Matrix Table of All 50 Stocks with Heatmap Cells
        function renderMatrixTable() {
            if (!currentData) return;
            const tbody = document.getElementById('matrix-tbody');
            const thPtsLabel = document.getElementById('th-pts-label');
            thPtsLabel.textContent = `${selectedTime} Pts`;

            // Filter constituents
            let filtered = [...currentData.constituents];

            if (searchQuery) {
                filtered = filtered.filter(s => s.symbol.includes(searchQuery) || s.name.toUpperCase().includes(searchQuery));
            }

            if (sectorFilter !== 'all') {
                filtered = filtered.filter(s => s.sector === sectorFilter);
            }

            // Breadth filter pills
            if (activeFilter === 'top50weight') {
                // Top ~8 heavyweights that make up 50% of the index
                filtered = filtered.slice(0, 8);
            } else if (activeFilter === 'top10') {
                filtered = filtered.slice(0, 10);
            } else if (activeFilter === 'green') {
                filtered = filtered.filter(s => {
                    const rowCandles = currentData.matrix[s.symbol];
                    const cur = rowCandles ? rowCandles[selectedCandleIndex] : null;
                    return cur && cur.color === 'green';
                });
            } else if (activeFilter === 'red') {
                filtered = filtered.filter(s => {
                    const rowCandles = currentData.matrix[s.symbol];
                    const cur = rowCandles ? rowCandles[selectedCandleIndex] : null;
                    return cur && cur.color === 'red';
                });
            }

            // Sorting
            filtered.sort((a, b) => {
                const aCandle = currentData.matrix[a.symbol] ? currentData.matrix[a.symbol][selectedCandleIndex] : null;
                const bCandle = currentData.matrix[b.symbol] ? currentData.matrix[b.symbol][selectedCandleIndex] : null;

                const aPts = aCandle ? aCandle.pts : 0;
                const bPts = bCandle ? bCandle.pts : 0;
                const aMove = aCandle ? aCandle.move_pct : 0;
                const bMove = bCandle ? bCandle.move_pct : 0;

                if (sortMode === 'weight_desc') return b.weight - a.weight;
                if (sortMode === 'pts_desc') return bPts - aPts;
                if (sortMode === 'pts_asc') return aPts - bPts;
                if (sortMode === 'move_desc') return bMove - aMove;
                if (sortMode === 'color') {
                    const aCol = aCandle?.color === 'green' ? 1 : (aCandle?.color === 'red' ? -1 : 0);
                    const bCol = bCandle?.color === 'green' ? 1 : (bCandle?.color === 'red' ? -1 : 0);
                    return bCol - aCol;
                }
                if (sortMode === 'symbol_asc') return a.symbol.localeCompare(b.symbol);
                return 0;
            });

            document.getElementById('filtered-stocks-count').textContent = `${filtered.length} stocks`;

            if (filtered.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="py-8 text-center text-gray-500 font-sans">No matching stocks found.</td></tr>`;
                return;
            }

            let html = '';
            filtered.forEach(stock => {
                const sym = stock.symbol;
                const rowCandles = currentData.matrix[sym] || [];
                const curCandle = rowCandles[selectedCandleIndex] || { pts: 0, move_pct: 0, color: 'flat' };

                const ptsSign = curCandle.pts >= 0 ? '+' : '';
                const moveSign = curCandle.move_pct >= 0 ? '+' : '';

                const ptsColor = curCandle.pts > 0 ? 'text-emerald-400 font-bold' : (curCandle.pts < 0 ? 'text-rose-400 font-bold' : 'text-gray-400');
                const moveColor = curCandle.move_pct > 0 ? 'text-emerald-400' : (curCandle.move_pct < 0 ? 'text-rose-400' : 'text-gray-400');

                // Build heatmap cells for this stock
                let cellsHtml = '';
                rowCandles.forEach((c, idx) => {
                    const isActive = (idx === selectedCandleIndex);
                    let bg = '#374151'; // Flat/doji
                    if (c.color === 'green') {
                        // Color intensity based on magnitude
                        bg = Math.abs(c.move_pct) > 0.4 ? '#10b981' : '#059669';
                    } else if (c.color === 'red') {
                        bg = Math.abs(c.move_pct) > 0.4 ? '#ef4444' : '#dc2626';
                    }

                    const activeClass = isActive ? 'matrix-col-active' : '';

                    cellsHtml += `
                        <div class="matrix-cell flex-shrink-0 ${activeClass}" 
                             style="background-color: ${bg};" 
                             data-symbol="${sym}" 
                             data-time="${c.time}"
                             data-idx="${idx}"
                             data-open="${c.open}"
                             data-close="${c.close}"
                             data-move="${c.move_pct}"
                             data-pts="${c.pts}">
                        </div>
                    `;
                });

                html += `
                    <tr class="hover:bg-gray-800/40 transition">
                        <td class="matrix-sticky-col px-3 py-1.5 border-r border-gray-800 bg-[#131722]">
                            <div class="font-bold text-white flex items-center gap-1.5">
                                <span>${sym}</span>
                                <span class="text-[9px] px-1 py-0.2 bg-gray-800 text-gray-400 rounded font-normal">${stock.sector}</span>
                            </div>
                        </td>
                        <td class="px-2 py-1.5 text-right font-medium text-gray-300">
                            ${stock.weight.toFixed(2)}%
                        </td>
                        <td class="px-2 py-1.5 text-right font-mono ${ptsColor}">
                            ${ptsSign}${curCandle.pts.toFixed(1)}
                        </td>
                        <td class="px-2 py-1.5 text-right font-mono border-r border-gray-800 ${moveColor}">
                            ${moveSign}${curCandle.move_pct.toFixed(2)}%
                        </td>
                        <td class="p-0">
                            <div class="flex items-center">
                                ${cellsHtml}
                            </div>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;

            // Attach hover and click listeners to matrix cells
            setupMatrixCellListeners();
        }

        // Setup Hover Popover & Click on Matrix Cells
        function setupMatrixCellListeners() {
            const popover = document.getElementById('cell-popover');

            document.querySelectorAll('.matrix-cell').forEach(cell => {
                cell.addEventListener('mouseenter', (e) => {
                    const rect = cell.getBoundingClientRect();
                    const sym = cell.dataset.symbol;
                    const time = cell.dataset.time;
                    const open = parseFloat(cell.dataset.open).toFixed(2);
                    const close = parseFloat(cell.dataset.close).toFixed(2);
                    const move = parseFloat(cell.dataset.move).toFixed(2);
                    const pts = parseFloat(cell.dataset.pts).toFixed(2);

                    document.getElementById('pop-symbol').textContent = sym;
                    document.getElementById('pop-time').textContent = `${time} (5m candle)`;
                    document.getElementById('pop-open').textContent = open;
                    document.getElementById('pop-close').textContent = close;

                    const moveEl = document.getElementById('pop-move');
                    const ptsEl = document.getElementById('pop-pts');
                    const sign = move >= 0 ? '+' : '';

                    moveEl.textContent = `${sign}${move}%`;
                    moveEl.className = move >= 0 ? 'font-bold text-emerald-400' : 'font-bold text-rose-400';

                    ptsEl.textContent = `${sign}${pts} pts`;
                    ptsEl.className = pts >= 0 ? 'font-bold text-emerald-400' : 'font-bold text-rose-400';

                    popover.style.left = `${rect.left + (rect.width / 2)}px`;
                    popover.style.top = `${rect.top}px`;
                    popover.classList.remove('hidden');
                });

                cell.addEventListener('mouseleave', () => {
                    popover.classList.add('hidden');
                });

                cell.addEventListener('click', () => {
                    const idx = parseInt(cell.dataset.idx);
                    selectCandleByIndex(idx);
                });
            });
        }

        // Render Stock Points Contribution Waterfall (Bottom Right)
        function renderStockContributions() {
            const b = currentData.breadth_by_time[selectedTime];
            if (!b) return;

            document.getElementById('contrib-time-label').textContent = selectedTime;
            const container = document.getElementById('contrib-list');

            let items = [...b.all_contributions];

            if (activeContribTab === 'leaders') {
                items = items.filter(s => s.pts > 0).slice(0, 15);
            } else if (activeContribTab === 'draggers') {
                items = items.filter(s => s.pts < 0).reverse().slice(0, 15);
            }

            // Max points magnitude for relative bar width
            const maxPts = items.reduce((max, s) => Math.max(max, Math.abs(s.pts)), 1.0);

            let html = '';
            items.forEach(stock => {
                const isPositive = stock.pts >= 0;
                const sign = isPositive ? '+' : '';
                const barWidthPct = Math.min(100, (Math.abs(stock.pts) / maxPts) * 100);

                const barColor = isPositive ? 'bg-emerald-500' : 'bg-rose-500';
                const textColor = isPositive ? 'text-emerald-400' : 'text-rose-400';

                html += `
                    <div class="flex items-center justify-between py-1 px-1.5 rounded hover:bg-gray-800/60 transition group">
                        <div class="w-24 flex items-center justify-between pr-2">
                            <span class="font-bold text-white group-hover:text-blue-400">${stock.symbol}</span>
                            <span class="text-[10px] text-gray-500 font-sans">${stock.weight.toFixed(1)}%</span>
                        </div>
                        
                        <!-- Horizontal Bar -->
                        <div class="flex-1 h-3.5 bg-gray-900 rounded overflow-hidden flex items-center px-1 mx-2 relative">
                            <div class="h-2 rounded ${barColor} transition-all duration-300" style="width: ${barWidthPct}%;"></div>
                        </div>

                        <!-- Numeric Value -->
                        <div class="w-24 text-right ${textColor} font-bold text-[11px]">
                            ${sign}${stock.pts.toFixed(1)} <span class="text-[10px] font-normal text-gray-400">(${sign}${stock.move_pct.toFixed(1)}%)</span>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Render Sector Impact Summary
        function renderSectorSummary(sectors) {
            document.getElementById('sector-time-label').textContent = selectedTime;
            const container = document.getElementById('sector-summary-list');

            let html = '';
            (sectors || []).slice(0, 6).forEach(sec => {
                const isPositive = sec.pts >= 0;
                const sign = isPositive ? '+' : '';
                const color = isPositive ? 'text-emerald-400' : 'text-rose-400';
                const bg = isPositive ? 'bg-emerald-950/40 border-emerald-800/30' : 'bg-rose-950/40 border-rose-800/30';

                html += `
                    <div class="p-1.5 rounded border ${bg} flex items-center justify-between">
                        <span class="text-gray-300 font-sans truncate pr-1" title="${sec.sector}">${sec.sector}</span>
                        <span class="${color} font-mono font-bold">${sign}${sec.pts.toFixed(1)} pts</span>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Helper: Switch Contrib Tab
        function switchContribTab(tab) {
            activeContribTab = tab;
            ['all', 'leaders', 'draggers'].forEach(t => {
                const el = document.getElementById(`contrib-tab-${t}`);
                if (t === tab) {
                    el.className = 'px-2 py-0.5 text-xs rounded bg-blue-600 text-white font-medium';
                } else {
                    el.className = 'px-2 py-0.5 text-xs rounded bg-[#1a1e29] hover:bg-gray-700 text-gray-300 font-medium';
                }
            });
            renderStockContributions();
        }

        // Global function for filter by color from UP / DOWN box click
        window.filterTableByColor = function(color) {
            activeFilter = color;
            document.querySelectorAll('.filter-pill').forEach(b => {
                if (b.dataset.filter === color) {
                    b.classList.remove('bg-[#1a1e29]', 'text-gray-300');
                    b.classList.add('bg-blue-600', 'text-white');
                } else {
                    b.classList.remove('bg-blue-600', 'text-white');
                    b.classList.add('bg-[#1a1e29]', 'text-gray-300');
                }
            });
            renderMatrixTable();
        };

        // ==========================================
        // BREADTH EXTREME ALARM & AUDIO SYNTHESIS
        // ==========================================

        function syncAlarmSettingsUI() {
            alarmEnabledToggle.checked = alarmSettings.enabled;
            alarmUpNum.value = alarmSettings.upThreshold;
            alarmUpSlider.value = alarmSettings.upThreshold;
            alarmDownNum.value = alarmSettings.downThreshold;
            alarmDownSlider.value = alarmSettings.downThreshold;
            alarmSoundSelect.value = alarmSettings.sound;
            alarmVolumeSlider.value = Math.round(alarmSettings.volume * 100);
            alarmVolLabel.textContent = `${Math.round(alarmSettings.volume * 100)}%`;

            const triggerRadio = document.querySelector(`input[name="trigger-by"][value="${alarmSettings.triggerBy}"]`);
            if (triggerRadio) triggerRadio.checked = true;

            syncAlarmStatusBadge();
            updateAlarmToolbarButton();
        }

        function syncAlarmStatusBadge() {
            const badge = document.getElementById('alarm-status-badge');
            if (badge) {
                if (alarmSettings.enabled) {
                    badge.textContent = 'ENABLED';
                    badge.className = 'px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-950 text-emerald-400 border border-emerald-600/40';
                } else {
                    badge.textContent = 'DISABLED (MUTED)';
                    badge.className = 'px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-gray-800 text-gray-400 border border-gray-700';
                }
            }
        }

        function updateAlarmToolbarButton() {
            const bellIcon = document.getElementById('alarm-bell-icon');
            const quickBtn = document.getElementById('btn-quick-toggle-alarm');
            if (alarmSettings.enabled) {
                alarmBtnLabel.textContent = `Alarm ON (${alarmSettings.upThreshold}%)`;
                if (bellIcon) bellIcon.textContent = '🔔';
                if (quickBtn) {
                    quickBtn.className = 'flex items-center gap-1.5 px-2.5 py-1 text-amber-400 hover:bg-amber-950/50 font-semibold transition cursor-pointer';
                }
            } else {
                alarmBtnLabel.textContent = 'Alarm OFF (Muted)';
                if (bellIcon) bellIcon.textContent = '🔕';
                if (quickBtn) {
                    quickBtn.className = 'flex items-center gap-1.5 px-2.5 py-1 text-gray-400 hover:bg-gray-800 font-semibold transition cursor-pointer opacity-75';
                }
            }
        }

        // Web Audio API Sound Engine (10 Custom Synthesized Sounds)
        let audioCtx = null;

        function getAudioContext() {
            if (!audioCtx) {
                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (AudioContextClass) {
                    audioCtx = new AudioContextClass();
                }
            }
            if (audioCtx && audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            return audioCtx;
        }

        function playSoundByName(name, volume = 0.8) {
            const ctx = getAudioContext();
            if (!ctx) return;
            const now = ctx.currentTime;
            
            const masterGain = ctx.createGain();
            masterGain.gain.setValueAtTime(volume, now);
            masterGain.connect(ctx.destination);

            switch(name) {
                case 'siren': {
                    // 1. Dual frequency rising/falling emergency warble siren
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
                    // 2. Harmonic twin bells chime
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
                    // 3. Digital cyber double beep
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
                    // 4. Deep resonant strike gong
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
                    // 5. Ascending major chord (C5, E5, G5, C6)
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
                    // 6. Descending warning buzzer (G4, Eb4, C4)
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
                    // 7. Submarine sonar ping
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
                    // 8. Rapid fire 4-pip alert
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
                    // 9. Trumpet breakout fanfare
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
                    // 10. Urgent sawtooth klaxon
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

        // Check Breadth Extreme Conditions & Trigger Alarm
        function checkAlarmConditions(breadth) {
            if (!alarmSettings.enabled || !breadth) return;

            const candleKey = `${currentData.date}_${breadth.time}`;
            if (lastAlertedCandleKey === candleKey) return; // Don't re-trigger on same candle while inspecting

            let greenScore = breadth.green_count_pct;
            let redScore   = breadth.red_count_pct;

            if (alarmSettings.triggerBy === 'weight') {
                greenScore = Math.round(breadth.green_weight);
                redScore   = Math.round(breadth.red_weight);
            } else if (alarmSettings.triggerBy === 'either') {
                greenScore = Math.max(breadth.green_count_pct, Math.round(breadth.green_weight));
                redScore   = Math.max(breadth.red_count_pct, Math.round(breadth.red_weight));
            }

            if (greenScore >= alarmSettings.upThreshold) {
                lastAlertedCandleKey = candleKey;
                triggerAlarm({
                    direction: 'UP',
                    score: greenScore,
                    threshold: alarmSettings.upThreshold,
                    time: breadth.time,
                    count: breadth.green_count,
                    weight: breadth.green_weight,
                    pts: breadth.net_green_pts,
                    topStock: breadth.top_contributors[0]?.symbol || 'NIFTY'
                });
            } else if (redScore >= alarmSettings.downThreshold) {
                lastAlertedCandleKey = candleKey;
                triggerAlarm({
                    direction: 'DOWN',
                    score: redScore,
                    threshold: alarmSettings.downThreshold,
                    time: breadth.time,
                    count: breadth.red_count,
                    weight: breadth.red_weight,
                    pts: breadth.net_red_pts,
                    topStock: breadth.top_draggers[0]?.symbol || 'NIFTY'
                });
            }
        }

        // Trigger Alarm (Continuous Looping Audio + Fullscreen Alert Modal)
        function triggerAlarm(alertData) {
            if (isAlarmRinging) return;
            isAlarmRinging = true;

            // 1. Play sound immediately
            playSoundByName(alarmSettings.sound, alarmSettings.volume);

            // 2. Loop continuously every 1.5 seconds until manually closed!
            if (alarmAudioLoopInterval) clearInterval(alarmAudioLoopInterval);
            alarmAudioLoopInterval = setInterval(() => {
                playSoundByName(alarmSettings.sound, alarmSettings.volume);
            }, 1500);

            // 3. Show Active Alarm Modal
            const activeCard = document.getElementById('active-alarm-card');
            const pctEl = document.getElementById('alarm-active-pct');
            const dirEl = document.getElementById('alarm-active-direction');
            const detEl = document.getElementById('alarm-active-details');
            const bannerBadge = document.getElementById('alarm-banner-badge');
            const sentimentIcon = document.getElementById('alarm-sentiment-icon');

            pctEl.textContent = `${alertData.score}%`;

            if (alertData.direction === 'UP') {
                activeCard.className = 'rounded-3xl p-6 sm:p-8 max-w-md w-full text-center space-y-4 shadow-2xl relative transition-all duration-300 bg-gradient-to-b from-emerald-700 via-emerald-800 to-teal-950 border-2 border-emerald-400 text-white shadow-emerald-950/80 animate-bounce-subtle';
                bannerBadge.className = 'px-4 py-1 rounded-full font-black text-xs uppercase tracking-widest text-white shadow-md bg-black/35 border border-white/20 flex items-center gap-2';
                const bTitle = document.getElementById('alarm-badge-title');
                const bLeft = document.getElementById('alarm-badge-left-icon');
                const bRight = document.getElementById('alarm-badge-right-icon');
                if (bTitle) bTitle.textContent = 'BULLISH BREADTH EXTREME';
                if (bLeft) bLeft.textContent = '🐂';
                if (bRight) bRight.textContent = '🐂';
                if (sentimentIcon) {
                    sentimentIcon.textContent = '🐂';
                    sentimentIcon.className = 'w-20 h-20 rounded-full flex items-center justify-center text-4xl sm:text-5xl shadow-xl border-2 border-emerald-300/60 bg-emerald-500/25 text-white select-none animate-pulse';
                }
                pctEl.className = 'text-5xl sm:text-6xl font-black font-mono tracking-tight text-white drop-shadow-md';
                dirEl.textContent = `BULLISH SURGE DETECTED (≥ ${alertData.threshold}%)`;
                dirEl.className = 'text-base sm:text-lg font-black uppercase tracking-wider text-emerald-100 drop-shadow-sm';
                detEl.className = 'text-xs sm:text-sm text-white/90 font-medium max-w-sm mx-auto leading-relaxed pt-1';
                detEl.textContent = `At ${alertData.time} · ${alertData.count} stocks Green (${alertData.weight.toFixed(1)}% Weight) · Net: +${alertData.pts.toFixed(1)} pts · Led by ${alertData.topStock}`;
            } else {
                activeCard.className = 'rounded-3xl p-6 sm:p-8 max-w-md w-full text-center space-y-4 shadow-2xl relative transition-all duration-300 bg-gradient-to-b from-red-700 via-red-800 to-rose-950 border-2 border-red-400 text-white shadow-rose-950/80 animate-bounce-subtle';
                bannerBadge.className = 'px-4 py-1 rounded-full font-black text-xs uppercase tracking-widest text-white shadow-md bg-black/35 border border-white/20 flex items-center gap-2';
                const bTitle = document.getElementById('alarm-badge-title');
                const bLeft = document.getElementById('alarm-badge-left-icon');
                const bRight = document.getElementById('alarm-badge-right-icon');
                if (bTitle) bTitle.textContent = 'BEARISH BREADTH EXTREME';
                if (bLeft) bLeft.textContent = '🐻';
                if (bRight) bRight.textContent = '🐻';
                if (sentimentIcon) {
                    sentimentIcon.textContent = '🐻';
                    sentimentIcon.className = 'w-20 h-20 rounded-full flex items-center justify-center text-4xl sm:text-5xl shadow-xl border-2 border-rose-300/60 bg-rose-500/25 text-white select-none animate-pulse';
                }
                pctEl.className = 'text-5xl sm:text-6xl font-black font-mono tracking-tight text-white drop-shadow-md';
                dirEl.textContent = `BEARISH DUMP DETECTED (≥ ${alertData.threshold}%)`;
                dirEl.className = 'text-base sm:text-lg font-black uppercase tracking-wider text-rose-100 drop-shadow-sm';
                detEl.className = 'text-xs sm:text-sm text-white/90 font-medium max-w-sm mx-auto leading-relaxed pt-1';
                detEl.textContent = `At ${alertData.time} · ${alertData.count} stocks Red (${alertData.weight.toFixed(1)}% Weight) · Net: ${alertData.pts.toFixed(1)} pts · Dragged by ${alertData.topStock}`;
            }

            activeAlarmOverlay.classList.remove('hidden');
        }

        // Stop Alarm Function (Stops audio loop and closes modal)
        function stopAlarm() {
            if (alarmAudioLoopInterval) {
                clearInterval(alarmAudioLoopInterval);
                alarmAudioLoopInterval = null;
            }
            isAlarmRinging = false;
            activeAlarmOverlay.classList.add('hidden');
        }

    })();
</script>
@endpush
