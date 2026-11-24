@extends('layouts.app')

@section('title', 'NIFTY 50 Unified Command Center | 75/25 Confluence Cockpit')

@push('styles')
    <!-- TradingView Lightweight Charts -->
    <script src="https://cdn.jsdelivr.net/npm/lightweight-charts@4.2.3/dist/lightweight-charts.standalone.production.js"></script>
    <!-- Chart.js 4.4 -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <!-- Protobuf JS -->
    <script src="https://cdn.jsdelivr.net/npm/protobufjs@7.2.5/dist/protobuf.min.js"></script>
    <!-- Flatpickr (Calendar Date Picker) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <!-- Screenshot Libraries for 1-Click Page Export -->
    <script src="/html-to-image.js"></script>
    <script src="/html2canvas.min.js?v=20261118"></script>

    <style>
        body {
            background-color: #0b0e14 !important;
            color: #d1d5db;
        }

        /* Tighten layout spacing between top navigation header and cockpit */
        header {
            padding: 0.5rem 1rem !important;
        }
        main {
            padding: 0.25rem !important;
        }
        main > div.mt-4 {
            margin-top: 0.25rem !important;
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

        /* Scalable Cockpit Data Panels (User Customizable Font/Scale) */
        .cockpit-data-panel {
            zoom: var(--cockpit-scale, 1.15);
            transition: zoom 0.15s ease-in-out;
        }

        /* Dark Theme Select Dropdown Options */
        #trade-drawer-height-select,
        #trade-drawer-height-select option,
        #trade-cfg-strategy-mode option,
        #trade-cfg-lots option {
            background-color: #101626 !important;
            color: #e2e8f0 !important;
        }
    </style>
@endpush

@section('content')
<div class="w-full flex flex-col p-1.5 space-y-1.5 select-none" id="nifty-unified-root">

    <!-- ════════════ TOP UNIFIED COMMAND BAR (COMPACT SINGLE ROW) ════════════ -->
    <div class="cockpit-card px-2.5 py-1 shadow-md flex items-center justify-between gap-1.5 shrink-0 border-gray-800 text-xs flex-nowrap overflow-x-auto scrollbar-none min-h-[38px]">
        
        <!-- Left: Brand Icon & Spot Price -->
        <div class="flex items-center gap-1.5 shrink-0">
            <span class="px-1.5 py-0.5 bg-gradient-to-r from-cyan-600 to-blue-600 rounded text-white font-extrabold text-[11px] flex items-center gap-1 shadow-xs tracking-wide">
                ⚡ <span>NIFTY 50</span>
            </span>

            <!-- Spot price pill -->
            <div class="flex items-center gap-1 bg-[#1a1e29] border border-gray-700/80 px-1.5 py-0.5 rounded text-[11px] font-mono">
                <span class="text-gray-400 text-[10px]">SPOT:</span>
                <span id="spot-price-display" class="font-bold text-cyan-400">---</span>
                <span id="spot-change-display" class="font-semibold text-gray-400 text-[10px]"></span>
            </div>



            <!-- Real-Time Institutional Driver Diagnosis Pill (Resolving Stock vs Options Conflicts) -->
            <div id="driver-diagnosis-pill" class="hidden sm:flex items-center gap-1.5 px-2 py-0.5 rounded border text-[10.5px] font-black tracking-wider transition-all duration-200 cursor-help bg-slate-900 text-slate-400 border-slate-700 shrink-0 shadow-xs" title="Institutional Driver Diagnosis: Resolving conflicts between Options Flow, Heavyweight Cash Breadth, and Futures">
                <span id="driver-diagnosis-icon">⚖️</span>
                <span id="driver-diagnosis-text">CALCULATING DRIVER...</span>
            </div>
        </div>

        <!-- High-Probability Trade Engine & Dynamic Exit Cockpit -->
        <div class="flex items-center bg-[#1a1e29] border border-emerald-500/50 hover:border-emerald-400 rounded px-1.5 py-0.5 shadow-inner shrink-0 transition" title="Toggle High-Probability Trade Cockpit & Dynamic Exit Matrix">
            <button id="btn-toggle-trade-drawer" type="button" onclick="toggleTradeDrawer()" class="px-2 py-0.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 active:scale-95 text-white font-bold rounded text-[9.5px] flex items-center gap-1.5 transition shadow-xs cursor-pointer tracking-wide">
                <span>🎯</span>
                <span>Trade Cockpit</span>
                <span id="header-trade-status-badge" class="px-1 py-0.2 rounded text-[8.5px] bg-slate-900/80 text-emerald-300 font-mono">STANDBY</span>
            </button>
        </div>

        <!-- Default Settings Reset Section -->
        <div class="flex items-center bg-[#1a1e29] border border-indigo-500/40 hover:border-indigo-500/70 rounded px-1.5 py-0.5 shadow-inner shrink-0 transition" title="Default Cockpit Layout & Thresholds">
            <button id="btn-reset-default" type="button" onclick="resetToDefaultSettings()" title="Reset to Default Settings (5m Stocks, 15m Options HA, OF Fut Sup/Rest, Breach, Wick Off, Live)" class="px-2 py-0.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 active:scale-95 text-white font-bold rounded text-[9.5px] flex items-center gap-1 transition shadow-xs cursor-pointer tracking-wide">
                <span id="btn-reset-default-icon">🔄</span>
                <span id="btn-reset-default-text">Default</span>
            </button>
        </div>

        <!-- AI Snapshot Export Section -->
        <div class="flex items-center bg-[#1a1e29] border border-emerald-500/40 hover:border-emerald-500/70 rounded px-1.5 py-0.5 shadow-inner shrink-0 transition" title="1-Click Copy Market Structure & Order Flow Snapshot JSON for AI Prediction">
            <button id="btn-ai-snapshot" type="button" onclick="copyAiMarketSnapshot()" title="Copy Instant Market Structure JSON Snapshot for AI Prediction" class="px-2 py-0.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 active:scale-95 text-white font-bold rounded text-[9.5px] flex items-center gap-1 transition shadow-xs cursor-pointer tracking-wide">
                <span id="btn-ai-snapshot-icon">📋</span>
                <span id="btn-ai-snapshot-text">AI Snapshot</span>
            </button>
        </div>

        <!-- Whole Day AI Diagnosis Download Section -->
        <div class="relative flex items-center bg-[#1a1e29] border border-purple-500/50 hover:border-purple-400 rounded px-1 py-0.5 shadow-inner shrink-0 transition" title="Download Whole Day Market Data & Forensic Diagnosis for AI (ChatGPT / Claude / Gemini)">
            <button id="btn-day-ai-download" type="button" onclick="downloadWholeDayAiData('md')" title="Download Whole Day Market Autopsy (.md) for AI Analysis" class="px-2 py-0.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 active:scale-95 text-white font-bold rounded text-[9.5px] flex items-center gap-1 transition shadow-xs cursor-pointer tracking-wide">
                <span id="btn-day-ai-icon">📥</span>
                <span id="btn-day-ai-text">Day AI Data</span>
            </button>
            <button id="btn-day-ai-dropdown" type="button" onclick="toggleDayAiMenu(event)" title="Options: Markdown (.md), JSON (.json), Copy to Clipboard, or On-Screen Preview" class="px-1.5 py-0.5 text-purple-300 hover:text-white border-l border-purple-500/40 text-[9px] font-bold cursor-pointer">
                ▼
            </button>
        </div>

        <!-- Print / Screenshot Export Section -->
        <div class="flex items-center bg-[#1a1e29] border border-amber-500/40 hover:border-amber-500/70 rounded px-1.5 py-0.5 shadow-inner shrink-0 transition" title="Save full page screenshot (PNG) to D:\OneDrive - Buffercode\0iohlc">
            <button id="btn-print-page" type="button" onclick="captureAndSavePageScreenshot()" title="Take full page screenshot & save to D:\OneDrive - Buffercode\0iohlc" class="px-2 py-0.5 bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-500 hover:to-orange-500 active:scale-95 text-white font-bold rounded text-[9.5px] flex items-center gap-1 transition shadow-xs cursor-pointer tracking-wide">
                <span id="btn-print-page-icon">📸</span>
                <span id="btn-print-page-text">Print</span>
            </button>
        </div>

        <!-- Right: Date, Expiry, Chart Height, Alarm, Auto-Refresh & Controls (Strict 1-Row) -->
        <div class="flex items-center gap-1.5 shrink-0 flex-nowrap">
            <!-- Calendar Date Picker -->
            <div class="flex items-center bg-[#1a1e29] border border-gray-700 rounded px-1.5 py-0.5 text-xs gap-0.5 shrink-0">
                <button id="btn-prev-day" type="button" title="Previous Trading Day" class="p-0.5 hover:bg-gray-700 rounded text-gray-400 hover:text-white transition cursor-pointer">
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <input type="text" id="date-select" readonly
                       class="bg-transparent text-white font-mono font-medium focus:outline-none cursor-pointer w-20 text-[11px] selection:bg-transparent text-center"
                       value="{{ $selectedDate }}">
                <button id="btn-next-day" type="button" title="Next Trading Day" class="p-0.5 hover:bg-gray-700 rounded text-gray-400 hover:text-white transition cursor-pointer">
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>

            <!-- Expiry Selector -->
            <div class="flex items-center bg-[#1a1e29] border border-gray-700 rounded px-1.5 py-0.5 text-xs gap-1 shrink-0">
                <span class="text-gray-400 text-[10px]">EXP:</span>
                <select id="expiry-select" class="bg-transparent text-amber-400 font-mono font-bold focus:outline-none cursor-pointer text-[11px]">
                    @foreach($expiries as $exp)
                        <option value="{{ $exp }}" {{ $exp === $selectedExpiry ? 'selected' : '' }} class="bg-[#131722] text-white">
                            {{ \Carbon\Carbon::parse($exp)->format('d-M') }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Dynamic Font Size / Cockpit Scale Selector -->
            <div class="flex items-center bg-[#1a1e29] border border-gray-700 rounded px-1.5 py-0.5 text-xs gap-0.5 shrink-0" title="Adjust Font Size / Scale for Your Monitor">
                <span class="text-gray-400 text-[10px] flex items-center gap-0.5 font-bold">
                    <span class="text-amber-400 text-[10px]">A</span><span class="text-amber-300 text-[12px] font-black">A:</span>
                </span>
                <select id="cockpit-font-scale-select" class="bg-transparent text-amber-300 font-mono font-bold focus:outline-none cursor-pointer text-[11px]" title="Scale Font & Data Panels to fit your Monitor">
                    <option value="100" class="bg-[#131722] text-white">100%</option>
                    <option value="105" class="bg-[#131722] text-white">105%</option>
                    <option value="110" class="bg-[#131722] text-white">110%</option>
                    <option value="115" selected class="bg-[#131722] text-white">115%</option>
                    <option value="120" class="bg-[#131722] text-white">120%</option>
                    <option value="125" class="bg-[#131722] text-white">125%</option>
                    <option value="130" class="bg-[#131722] text-white">130%</option>
                    <option value="140" class="bg-[#131722] text-white">140%</option>
                </select>
            </div>

            <!-- Confluence & Alarm Controls -->
            <div class="flex items-center bg-[#1a1e29] border border-gray-700 rounded text-xs overflow-hidden shrink-0">
                <button id="btn-quick-alarm" title="Click to Toggle Alarm On/Off" class="flex items-center gap-1 px-1.5 py-0.5 text-amber-400 hover:bg-amber-950/50 font-bold transition cursor-pointer">
                    <span id="alarm-bell-icon">🔔</span>
                    <span id="alarm-toolbar-label" class="text-[11px]">80%</span>
                </button>
                <button id="btn-open-alarm-settings" title="Configure NIFTY Breadth Extreme & 0Click.in Webhook" class="px-1 py-0.5 border-l border-gray-700 text-gray-400 hover:text-white hover:bg-gray-800 transition cursor-pointer font-bold">
                    ⚙️
                </button>
            </div>

            <!-- 5 Quant Edge Boosters Status Pill -->
            <button type="button" onclick="openQuantEdgeModalTab()" id="btn-quant-edge-status" title="5 Quant Edge & Accuracy Boosters • Click to Configure" class="hidden sm:flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-sky-950/80 text-sky-300 border border-sky-500/40 hover:bg-sky-900 transition cursor-pointer shrink-0">
                <span>🎯</span>
                <span id="quant-edge-status-label">Edge 5/5</span>
            </button>

            <!-- 0Click Signal Logs Button -->
            <button id="btn-open-logs" title="View 0Click.in Signal Execution & Internal Audit Logs" class="flex items-center gap-1 px-1.5 py-0.5 bg-[#1a1e29] hover:bg-gray-800 border border-gray-700 text-sky-400 hover:text-sky-300 rounded text-xs font-semibold transition cursor-pointer shrink-0">
                <span>📋</span>
                <span class="hidden xl:inline text-[11px]">Logs</span>
                <span id="log-count-badge" class="ml-0.5 px-1 py-0.2 bg-sky-950/90 border border-sky-500/50 text-sky-300 font-mono text-[9.5px] rounded-full">0</span>
            </button>

            <!-- Auto-Refresh Selector -->
            <div class="flex items-center bg-[#1a1e29] border border-gray-700 rounded px-1.5 py-0.5 text-xs gap-1 shrink-0" title="Smart Candle Sync & Auto-Refresh">
                <span class="text-gray-400 text-[10px] flex items-center gap-0.5 font-bold">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 live-dot"></span>
                    <span class="hidden sm:inline">Sync:</span>
                </span>
                <select id="auto-refresh-select" class="bg-transparent text-emerald-300 font-bold focus:outline-none cursor-pointer text-[11px]" title="Auto-Refresh Sync Mode">
                    <option value="candle_5m" selected class="bg-[#131722] text-emerald-400 font-bold">5m (+8s)</option>
                    <option value="stock_1m" class="bg-[#131722] text-sky-400">1m (+5s)</option>
                    <option value="15" class="bg-[#131722] text-white">15s</option>
                    <option value="30" class="bg-[#131722] text-white">30s</option>
                    <option value="60" class="bg-[#131722] text-white">1m</option>
                    <option value="300" class="bg-[#131722] text-white">5m</option>
                    <option value="0" class="bg-[#131722] text-gray-400">Off</option>
                </select>
                <div class="flex items-center gap-0.5 border-l border-gray-700/80 pl-1">
                    <span id="countdown-badge" class="px-1.5 py-0.2 bg-gray-800 text-sky-300 font-mono text-[10px] rounded font-bold cursor-pointer transition hover:bg-gray-700 select-none" title="Next 5m Candle Sync (Click to Hide)">04:52</span>
                    <button type="button" id="btn-toggle-timer-vis" class="text-gray-400 hover:text-white text-[11px] px-0.5 rounded cursor-pointer transition leading-none" title="Toggle Candle Timer Display">
                        👁️
                    </button>
                </div>
                <!-- Last Sync Time Badge (Only Time: HH:mm:ss) -->
                <div class="flex items-center gap-1 border-l border-gray-700/80 pl-1" title="Last Synchronized Time (Only Time)">
                    <span class="text-gray-400 text-[10px] font-bold hidden md:inline">Synced:</span>
                    <span id="last-sync-time-badge" class="px-1.5 py-0.2 bg-gray-800 text-emerald-300 font-mono text-[10px] rounded font-bold select-none cursor-default" title="Last Synchronized Time">{{ \Carbon\Carbon::now('Asia/Kolkata')->format('H:i:s') }}</span>
                </div>
            </div>

            <!-- Manual Reload -->
            <button id="btn-reload" title="Refresh All Data Now" class="p-1 bg-[#1a1e29] hover:bg-gray-700 border border-gray-700 rounded text-gray-300 hover:text-white transition cursor-pointer shrink-0">
                <svg id="reload-spinner" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </button>

            <!-- Zen Mode -->
            <button id="btn-zen" title="Toggle Fullscreen Zen Mode" class="p-1 bg-[#1a1e29] hover:bg-gray-700 border border-gray-700 rounded text-gray-300 hover:text-white transition cursor-pointer shrink-0">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- ════════════ INSTITUTIONAL QUANT LEVELS (NIFTY 50 SPOT BENCHMARK) ════════════ --}}
    @php
        $lvlR3 = $quantPlaybook['levels'][0]['price'] ?? null;
        $lvlR2 = $quantPlaybook['levels'][1]['price'] ?? null;
        $lvlR1 = $quantPlaybook['levels'][2]['price'] ?? null;
        $lvlP  = $quantPlaybook['levels'][3]['price'] ?? $quantPlaybook['central_pivot'] ?? null;
        $lvlS1 = $quantPlaybook['levels'][4]['price'] ?? null;
        $lvlS2 = $quantPlaybook['levels'][5]['price'] ?? null;
        $lvlS3 = $quantPlaybook['levels'][6]['price'] ?? null;
        $hasQuant = !empty($quantPlaybook) && !empty($quantPlaybook['levels']);
    @endphp
    <div id="quant-strip-container" class="cockpit-card px-2.5 py-1 shadow-md flex items-center justify-between gap-3 overflow-x-auto custom-scrollbar border-indigo-900/40 bg-[#0d121f] text-[11px] font-mono shrink-0 min-h-[40px]">
        <!-- 1. Left: Master Unified Timeline Dropdown & Replay Controller (Anchored to Left) -->
        <div class="flex items-center gap-1.5 bg-[#141b2d] border border-sky-500/40 rounded px-2 py-0.5 shadow-inner shrink-0">
            <!-- Timeline Candle Time Dropdown -->
            <div class="flex items-center bg-sky-950/90 border border-sky-500/60 rounded px-2 py-0.5 text-xs gap-1 shrink-0" title="Select Candle Time">
                <span class="text-sky-400 font-bold text-xs">⏰</span>
                <select id="master-time-select" onchange="syncActiveCandleIndex(parseInt(this.value, 10))" class="bg-transparent text-sky-300 font-mono font-bold text-xs focus:outline-none cursor-pointer">
                    <option value="0">09:15</option>
                </select>
                <span id="master-time-badge" class="hidden">09:15</span>
                <input id="master-slider" type="range" min="0" max="74" value="0" class="hidden">
            </div>

            <!-- Stepper Navigation Controls with Safe Margins & Sizing -->
            <div class="flex items-center border-l border-gray-700/80 pl-1.5 gap-1.5">
                <!-- Jump to First Candle (09:15) -->
                <button id="btn-step-start" title="First candle (09:15)" class="p-1 hover:bg-gray-700 rounded text-gray-400 hover:text-white transition cursor-pointer border border-transparent hover:border-gray-600">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
                </button>

                <!-- Backward Button (Comfortable size, clear separation) -->
                <button id="btn-step-prev" title="Previous 5m candle (← Key)" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 active:scale-95 text-sky-300 hover:text-white font-bold rounded border border-slate-600/90 shadow-xs flex items-center gap-1 transition cursor-pointer text-xs select-none">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                    <span class="hidden sm:inline font-mono">Prev</span>
                </button>

                <!-- Dedicated Buffer Gap to Prevent Accidental Play Clicks (12px) -->
                <div class="w-3"></div>

                <!-- Play / Pause Button (Centrally isolated) -->
                <button id="btn-play-pause" title="Play / Pause Replay (Spacebar)" class="px-3.5 py-1 bg-blue-600 hover:bg-blue-500 active:scale-95 text-white font-bold rounded shadow-xs flex items-center gap-1.5 transition cursor-pointer text-xs select-none">
                    <span id="play-icon">▶</span>
                    <span id="play-text">Play</span>
                </button>

                <!-- Dedicated Buffer Gap to Prevent Accidental Play Clicks (12px) -->
                <div class="w-3"></div>

                <!-- Forward Button (Comfortable size, clear separation) -->
                <button id="btn-step-next" title="Next 5m candle (→ Key)" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 active:scale-95 text-sky-300 hover:text-white font-bold rounded border border-slate-600/90 shadow-xs flex items-center gap-1 transition cursor-pointer text-xs select-none">
                    <span class="hidden sm:inline font-mono">Next</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                </button>

                <!-- Divider -->
                <div class="border-l border-gray-700/80 h-5 mx-1"></div>

                <!-- Snap to Live -->
                <button id="btn-snap-live" title="Snap to Latest Live Candle" class="px-2 py-1 bg-emerald-600 hover:bg-emerald-500 active:scale-95 text-white font-bold rounded text-[10px] flex items-center gap-1.5 transition cursor-pointer shadow-xs">
                    <span class="w-2 h-2 rounded-full bg-white live-dot"></span>
                    <span>LIVE</span>
                </button>

                <!-- Bar Replay Toggle -->
                <button id="btn-toggle-bar-replay" type="button" onclick="toggleBarReplayMode()" title="Toggle True Bar Replay: Hides future candles and steps bar-by-bar [Hotkey: B]" class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white font-bold rounded text-[10px] flex items-center gap-1 transition cursor-pointer border border-slate-600 shadow-xs">
                    <span id="bar-replay-icon">⏪</span>
                    <span class="hidden md:inline">Bar Replay:</span>
                    <span id="bar-replay-badge" class="font-mono text-gray-400">OFF</span>
                </button>
            </div>
        </div>

        <!-- 2. Center: Candle Close Remaining Time Countdown Badge (Dual Chart 1 & Chart 2 Remaining Time) -->
        <div class="flex items-center gap-2 sm:gap-3 shrink-0 flex-nowrap">
            <div id="candle-timer-container" class="flex items-center gap-1.5 px-2.5 py-0.5 rounded bg-gradient-to-r from-amber-950/70 via-slate-900 to-amber-950/70 border border-amber-500/60 shadow-md shrink-0 cursor-default" title="Time remaining until active candle closes for Chart 1 & Chart 2">
                <span class="flex h-2 w-2 relative shrink-0">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                </span>
                <div class="flex items-center gap-1.5 whitespace-nowrap">
                    <div class="flex items-center gap-1" title="Chart 1 (Top) Active Candle Countdown">
                        <span id="candle-timer-tf-label" class="text-sky-400 font-black text-[10px] tracking-wider uppercase whitespace-nowrap">C1 (5M):</span>
                        <span id="candle-timer-countdown" class="font-mono font-black text-sky-200 text-xs sm:text-sm tracking-wider leading-none select-none drop-shadow">--:--</span>
                    </div>
                    <span id="candle-timer-divider" class="text-slate-600 font-bold text-xs">|</span>
                    <div class="flex items-center gap-1" title="Chart 2 (Bottom) Active Candle Countdown">
                        <span id="candle-timer-tf2-label" class="text-purple-400 font-black text-[10px] tracking-wider uppercase whitespace-nowrap">C2 (15M):</span>
                        <span id="candle-timer-countdown2" class="font-mono font-black text-purple-200 text-xs sm:text-sm tracking-wider leading-none select-none drop-shadow">--:--</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Right: Institutional Quant Levels -->
        <div class="flex items-center gap-2 shrink-0">
            <span class="px-1.5 py-0.2 rounded text-[10px] font-black uppercase bg-indigo-500/20 text-indigo-300 border border-indigo-500/40 tracking-wider">
                📐 QUANT LEVELS (SPOT)
            </span>
            <button type="button" onclick="toggleQuantPriceLines()" class="px-1.5 py-0.2 rounded text-[10px] font-mono font-bold border transition flex items-center gap-1 bg-indigo-950/90 border-indigo-500/60 hover:bg-indigo-900 cursor-pointer text-indigo-200" title="Toggle Quant Price Lines on both Charts">
                <span class="text-[9.5px]">Chart Lines:</span>
                <span id="strip-quant-lines-status-badge" class="font-mono text-emerald-400 font-extrabold">ON</span>
            </button>
            <div class="flex items-center gap-1.5 text-[10px] text-slate-400 font-sans hidden lg:flex">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                <span id="quant-lbl-bull-above" class="text-emerald-300 font-semibold">Bull Above {{ $lvlP ? '₹' . number_format($lvlP, 0) : '--' }}</span>
                <span class="text-slate-600">·</span>
                <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                <span id="quant-lbl-bear-below" class="text-rose-300 font-semibold">Bear Below {{ $lvlP ? '₹' . number_format($lvlP, 0) : '--' }}</span>
                <span class="text-slate-600">·</span>
                <span class="text-slate-400 text-[10px]">Sync:</span>
                <span id="quant-last-sync-time" class="font-mono text-sky-300 font-bold text-[10px]" title="Quant Levels Last Sync Time">{{ \Carbon\Carbon::now('Asia/Kolkata')->format('H:i:s') }}</span>
                <a id="quant-link-report" href="{{ route('order-flow-depth.report', ['date' => $selectedDate]) }}" target="_blank" class="ml-1 text-sky-400 hover:text-sky-300 underline font-mono text-[9.5px]">Full Report &rarr;</a>
            </div>
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
                        <div class="flex items-center justify-between gap-1 pb-1 mb-1 border-b border-gray-800 flex-nowrap overflow-hidden">
                            <div class="flex items-center gap-1 flex-nowrap shrink-0 overflow-x-auto scrollbar-none">
                                <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                                <span id="w-chart-title" class="text-xs font-bold text-white tracking-wide shrink-0 whitespace-nowrap">NIFTY 50</span>
                                <!-- Chart 1 Dedicated Timeframe Selector -->
                                <div class="flex items-center bg-[#151924] border border-sky-500/40 rounded p-0.5 gap-0.5 shrink-0 shadow-inner ml-1" id="w-tf-group" title="Nifty 50 Stocks Timeframe">
                                    <button type="button" onclick="setChart1Timeframe('1m')" data-wtf="1m" class="w-tf-btn px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800">1m</button>
                                    <button type="button" onclick="setChart1Timeframe('3m')" data-wtf="3m" class="w-tf-btn px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800">3m</button>
                                    <button type="button" onclick="setChart1Timeframe('5m')" data-wtf="5m" class="w-tf-btn px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800">5m</button>
                                    <button type="button" onclick="setChart1Timeframe('15m')" data-wtf="15m" class="w-tf-btn px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800">15m</button>
                                    <button type="button" onclick="setChart1Timeframe('30m')" data-wtf="30m" class="w-tf-btn px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800">30m</button>
                                    <button type="button" onclick="setChart1Timeframe('1h')" data-wtf="1h" class="w-tf-btn px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800">1h</button>
                                    <button type="button" onclick="setChart1Timeframe('1D')" data-wtf="1D" class="w-tf-btn px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800">1D</button>
                                </div>
                                <!-- Chart 1 Candlestick Mode (SPOT vs FUT) -->
                                <div class="flex items-center bg-[#151924] border border-sky-500/40 rounded p-0.5 gap-0.5 shrink-0 shadow-inner ml-1" title="Chart 1 Candlestick Mode: NIFTY 50 Spot Index vs Near-Month Futures">
                                    <button type="button" id="w-basis-spot-btn" onclick="setChart1CandleBasis('spot')" class="px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer bg-sky-600 text-white shadow-xs">SPOT</button>
                                    <button type="button" id="w-basis-fut-btn" onclick="setChart1CandleBasis('future')" class="px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800">FUT</button>
                                </div>
                                <!-- Chart 1 Dedicated Candle Close Countdown Badge -->
                                <div id="w-candle-timer-badge" class="flex items-center gap-1 px-1.5 py-0.5 rounded bg-sky-950/80 border border-sky-500/50 shadow-xs shrink-0 cursor-default ml-1" title="Top Chart Active Candle Countdown">
                                    <span class="flex h-1.5 w-1.5 relative shrink-0">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-sky-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-sky-500"></span>
                                    </span>
                                    <span id="w-candle-timer-label" class="text-sky-300 font-black text-[10px] tracking-wider uppercase whitespace-nowrap">5M:</span>
                                    <span id="w-candle-timer-countdown" class="font-mono font-black text-sky-200 text-xs tracking-wider leading-none select-none">--:--</span>
                                </div>
                                <button type="button" id="w-candle-style-btn" onclick="toggleWeightageCandleStyle()"
                                    class="ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 bg-gray-800 border-gray-600 text-gray-300 hover:text-white cursor-pointer"
                                    title="Toggle Candle Style: Regular Candlestick / Heikin Ashi (Row 1)">
                                    <span id="w-candle-style-icon">🕯️</span>
                                    <span id="w-candle-style-badge" class="font-mono text-emerald-400 font-extrabold">CANDLE</span>
                                </button>

                                <button type="button" id="w-of-menu-btn" onclick="toggleHistOfDropdown('w', this)" 
                                    class="ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 bg-teal-950/80 border-teal-500/50 text-teal-300 hover:bg-teal-900 cursor-pointer select-none"
                                    title="Order Flow Levels Menu (Live Today & 5D Historical S/R, Basis & Filters)">
                                    <span>🌊 OF:</span>
                                    <span id="w-of-sr-status-badge" class="font-mono text-emerald-400 font-extrabold">ON</span>
                                    <span id="w-of-calib-badge" class="px-1 py-0 rounded text-[8.5px] bg-cyan-950 text-cyan-300 font-mono font-extrabold border border-cyan-500/30">SPOT & FUT</span>
                                    <span id="w-hist-of-count-badge" class="px-1 py-0 rounded text-[8.5px] bg-amber-500 text-black font-extrabold font-mono">0</span>
                                    <span class="text-[8px] text-gray-400">▾</span>
                                </button>
                                <button type="button" id="w-marker-toggle-btn" onclick="cycleStocksMarkerMode()"
                                    class="ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 bg-gray-800 border-gray-600 text-gray-300 hover:text-white cursor-pointer"
                                    title="Cycle Signals on Weightage Chart: Breach Only (≥80%) / Every 5M Candle / OFF">
                                    <span>🎯 Sig:</span>
                                    <span id="w-marker-status-badge" class="font-mono text-cyan-300 font-extrabold">BREACH</span>
                                </button>
                                <button type="button" id="w-ha-wick-filter-btn" onclick="toggleStocksHaWickFilter()"
                                    class="ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 bg-amber-950/60 border-amber-500/50 text-amber-300 hover:bg-amber-900 cursor-pointer"
                                    title="Heikin Ashi Single-Wick Entry Filter: STRICT (Entry only on zero opposite wick; both wicks ignored) / OFF (All breaches)">
                                    <span>🎯 Wick:</span>
                                    <span id="w-ha-wick-filter-badge" class="font-mono text-emerald-400 font-extrabold">STRICT</span>
                                </button>
                                <button type="button" id="w-stk-signal-btn" onclick="toggleChart1StocksSignal()"
                                    class="ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 cursor-pointer bg-blue-950/70 border-blue-500/50 text-blue-300 hover:bg-blue-900"
                                    title="Toggle Nifty 50 Stock Alerts on Chart 1: ON (Display stock breach & entries) / OFF">
                                    <span>📊 STK:</span>
                                    <span id="w-stk-signal-badge" class="font-mono text-emerald-400 font-extrabold">ON</span>
                                </button>
                                <button type="button" id="w-opt-signal-btn" onclick="toggleChart1OptionsSignal()"
                                    class="ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 cursor-pointer bg-gray-800 border-gray-600 text-gray-400 hover:text-white"
                                    title="Toggle Option Flow Alerts on Chart 1: ON (Display option parity alerts here too) / OFF">
                                    <span>⚡ OPT:</span>
                                    <span id="w-opt-signal-badge" class="font-mono text-gray-400 font-bold">OFF</span>
                                </button>
                                <button type="button" id="w-candle-hl-btn" onclick="toggleActiveCandleHighlight()"
                                    class="ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 cursor-pointer bg-gray-800 border-gray-600 text-gray-400 hover:text-white"
                                    title="Toggle Active Candle Highlight Beacon: OFF (Default - Clean live chart) / ON (Highlight current timeline candle on chart with beam & reticle) [Hotkey: H]">
                                    <span>🔦 HL:</span>
                                    <span id="w-candle-hl-badge" class="font-mono text-gray-400 font-bold">OFF</span>
                                </button>
                                <div class="flex items-center bg-[#151924] border border-gray-700/80 rounded px-1.5 py-0.5 text-xs gap-0.5 shrink-0 ml-1" title="Chart 1 & Data Panel Height">
                                    <span class="text-gray-400 text-[10px] font-bold">H:</span>
                                    <select id="w-height-select" onchange="setChart1Height(this.value)" class="bg-transparent text-sky-400 font-mono font-bold focus:outline-none cursor-pointer text-[11px]" title="Customize Chart 1 Height">
                                        <option value="300" class="bg-[#131722] text-white">300px</option>
                                        <option value="350" class="bg-[#131722] text-white">350px</option>
                                        <option value="380" class="bg-[#131722] text-white">380px</option>
                                        <option value="420" class="bg-[#131722] text-white">420px</option>
                                        <option value="460" class="bg-[#131722] text-white">460px</option>
                                        <option value="500" class="bg-[#131722] text-white">500px</option>
                                        <option value="550" class="bg-[#131722] text-white">550px</option>
                                        <option value="600" class="bg-[#131722] text-white">600px</option>
                                    </select>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 text-[10px] shrink-0 whitespace-nowrap ml-2">
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
                            <!-- Session Break Vertical Line -->
                            <div id="w-day-separator" class="day-separator pointer-events-none absolute top-0 bottom-[26px] z-10 hidden" style="width: 1px;">
                                <div class="h-full border-l-2 border-dashed border-sky-400/80 shadow-[0_0_8px_rgba(56,189,248,0.5)]"></div>
                                <div class="absolute top-2 -translate-x-1/2 flex items-center gap-1.5 bg-[#0d1322]/95 text-sky-300 text-[9px] font-mono font-bold tracking-wider px-2 py-0.5 rounded-full border border-sky-500/50 shadow-xl whitespace-nowrap select-none backdrop-blur-xs">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    <span id="w-day-sep-label">TODAY</span>
                                </div>
                            </div>

                            <!-- Enterprise Active Candle Highlight Overlay (Beacon & Reticle) -->
                            <div id="w-candle-highlight" class="pointer-events-none absolute top-0 bottom-[26px] z-[15] hidden" style="left: 0;">
                                <div id="w-candle-highlight-beam" class="absolute top-0 bottom-0 pointer-events-none"></div>
                                <div id="w-candle-highlight-laser" class="absolute top-0 bottom-0 pointer-events-none"></div>
                                <div id="w-candle-highlight-reticle" class="absolute pointer-events-none"></div>
                                <div id="w-candle-highlight-pill" class="absolute top-1.5 pointer-events-none"></div>
                                <div id="w-candle-highlight-bot" class="absolute bottom-1 pointer-events-none"></div>
                            </div>

                            <!-- Floating TradingView Style Dock Toolbar -->
                            <div class="tv-dock absolute left-2 top-2 z-20 flex flex-col items-center bg-[#151924]/90 backdrop-blur-md border border-gray-700/80 rounded shadow-2xl p-0.5 gap-1 select-none">
                                <button type="button" onclick="setActiveWeightageTvTool('pointer')" id="w-tv-pointer" class="tv-tool-btn active w-6 h-6 flex items-center justify-center rounded transition text-white bg-sky-600 shadow-xs cursor-pointer" title="Pointer / Select Candle">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/></svg>
                                </button>
                                <button type="button" onclick="toggleWeightageHLineTool()" id="w-tv-hline" class="tv-tool-btn w-6 h-6 flex items-center justify-center rounded transition text-gray-400 hover:text-white hover:bg-gray-700 cursor-pointer" title="Draw Horizontal Support/Resistance Line (Click price on chart)">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12h18"/></svg>
                                </button>
                                <button type="button" onclick="cycleWeightageFibMode()" id="w-tv-fib" class="tv-tool-btn w-6 h-6 flex items-center justify-center rounded transition text-gray-400 hover:text-white hover:bg-gray-700 font-mono text-[9px] font-black cursor-pointer" title="Fibonacci Retracement (Auto / Manual / Off)">
                                    Fib
                                </button>
                                <button type="button" onclick="cycleWeightageSuperTrendMode()" id="w-tv-st" class="tv-tool-btn w-6 h-6 flex items-center justify-center rounded transition text-gray-400 hover:text-white hover:bg-gray-700 font-mono text-[9px] font-black cursor-pointer" title="SuperTrend Indicator (10,3 / 10,2 / Off)">
                                    ST
                                </button>
                                <div class="w-3.5 h-px bg-gray-700 my-0.5"></div>
                                <button type="button" onclick="clearWeightageManualDrawings()" class="tv-tool-btn w-6 h-6 flex items-center justify-center rounded transition text-gray-400 hover:text-rose-300 hover:bg-rose-950/60 cursor-pointer" title="Clear all drawings on Weightage Chart">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>

                            <!-- Interactive Tool Action Notification Hint -->
                            <div id="w-tv-hint" class="hidden absolute top-2 right-12 z-20 px-2.5 py-1 rounded bg-sky-950/95 border border-sky-400 text-sky-200 text-[10px] font-mono shadow-lg items-center gap-1.5 animate-pulse">
                                <span>💡</span>
                                <span id="w-tv-hint-text">Click price on chart</span>
                                <button type="button" onclick="setActiveWeightageTvTool('pointer')" class="ml-1 text-slate-400 hover:text-white font-bold cursor-pointer">✕</button>
                            </div>

                            <div id="w-chart-loading" class="absolute inset-0 bg-[#0d1017]/90 backdrop-blur-sm flex flex-col items-center justify-center gap-2.5 text-xs text-gray-300 font-medium z-30 transition-all duration-200">
                                <div class="relative w-8 h-8">
                                    <div class="w-8 h-8 border-2 border-blue-500/20 border-t-blue-500 rounded-full animate-spin"></div>
                                    <div class="absolute inset-0 flex items-center justify-center text-[10px] text-blue-400 font-bold font-mono">50</div>
                                </div>
                                <span id="w-loading-msg" class="text-xs text-blue-300 font-mono font-medium tracking-wide">Loading NIFTY 50 Weightage...</span>
                            </div>
                        </div>

                        <!-- Scrubber Slider -->
                        <div class="pt-1.5 flex items-center gap-2 text-[10px] text-gray-400">
                            <span class="font-mono text-[9px]" id="w-slider-start">09:15</span>
                            <input id="w-slider" type="range" min="0" max="74" value="0" class="w-full h-1 bg-gray-700 rounded appearance-none cursor-pointer accent-blue-400 focus:outline-none">
                            <span class="font-mono text-[9px]" id="w-slider-end">15:25</span>
                        </div>
                    </div>

                    <!-- Right: 25% Dual-Deck Mini Cockpit (Stocks & Options for Chart 1 Timeframe) -->
                    <div id="w-cockpit-panel" class="lg:col-span-3 flex flex-col justify-between border-t lg:border-t-0 lg:border-l border-gray-800 lg:pl-2.5 pt-2 lg:pt-0 cockpit-data-panel overflow-y-auto scrollbar-none" style="height: 420px; max-height: 420px;">
                        <!-- Unified Candle Header & Moves -->
                        <div class="flex items-center justify-between pb-1 border-b border-gray-800 shrink-0">
                            <div>
                                <span class="text-[9.5px] uppercase tracking-wider text-gray-400 font-medium">SELECTED CANDLE</span>
                                <div class="text-xs sm:text-sm font-bold text-sky-400 font-mono" id="w-stat-time">
                                    09:15 - {{ $selectedTimeframe === '1D' ? '1-Day candle' : $selectedTimeframe . ' candle' }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-right">
                                <div>
                                    <span class="text-[9px] text-gray-400 block leading-tight">Index Move</span>
                                    <div id="w-stat-pts" class="text-xs sm:text-sm font-black font-mono text-emerald-400">+0.0 pts</div>
                                </div>
                                <div class="border-l border-gray-700/80 pl-2">
                                    <span class="text-[9px] text-gray-400 block leading-tight">Opt Impulse</span>
                                    <div id="w-opt-stat-impulse" class="text-xs sm:text-sm font-black font-mono text-emerald-400">+0.00 pts</div>
                                </div>
                            </div>
                        </div>

                        <!-- ══ DECK 1: NIFTY 50 STOCKS (CHART 1 TIMEFRAME) ══ -->
                        <div class="flex flex-col space-y-1.5 rounded-lg bg-[#131722]/95 border border-sky-900/60 p-2 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-sky-300 flex items-center gap-1.5">
                                    <span>📊</span> <span>NIFTY 50 STOCKS</span>
                                </span>
                                <span id="stk-trigger-verdict-badge" class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-gray-800 text-gray-400 border border-gray-700">
                                    Normal
                                </span>
                            </div>

                            <!-- UP vs DOWN Compact Grid -->
                            <div class="grid grid-cols-2 gap-1.5">
                                <div id="w-box-up" class="bg-gradient-to-br from-emerald-950/70 to-emerald-900/30 border border-emerald-500/40 rounded-md px-2 py-1 shadow-inner flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-base sm:text-lg font-black text-emerald-400 font-mono leading-none" id="w-stat-green-pct">0%</span>
                                            <span class="px-1.5 py-0.5 bg-emerald-500/20 text-emerald-300 font-bold text-[8.5px] rounded uppercase">UP</span>
                                        </div>
                                        <div class="text-[9.5px] text-emerald-200/90 font-medium flex justify-between mt-1">
                                            <span>Wt: <strong id="w-stat-green-wt" class="text-white">0%</strong></span>
                                            <span><strong id="w-stat-green-cnt" class="text-white">0</strong> stk &bull; <span id="w-stat-green-pts" class="text-emerald-300">+0</span></span>
                                        </div>
                                    </div>
                                    <!-- Previous Candle Micro-Footer -->
                                    <div class="pt-0.5 mt-1 border-t border-emerald-500/20 flex items-center justify-between text-[8px] font-mono text-emerald-300/60 leading-none">
                                        <span>Prev: <span id="w-prev-green-val" class="text-gray-300 font-semibold">--</span></span>
                                        <span id="w-prev-green-delta" class="font-bold">--</span>
                                    </div>
                                </div>

                                <div id="w-box-down" class="bg-gradient-to-br from-rose-950/70 to-rose-900/30 border border-rose-500/40 rounded-md px-2 py-1 shadow-inner flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-base sm:text-lg font-black text-rose-400 font-mono leading-none" id="w-stat-red-pct">0%</span>
                                            <span class="px-1.5 py-0.5 bg-rose-500/20 text-rose-300 font-bold text-[8.5px] rounded uppercase">DN</span>
                                        </div>
                                        <div class="text-[9.5px] text-rose-200/90 font-medium flex justify-between mt-1">
                                            <span>Wt: <strong id="w-stat-red-wt" class="text-white">0%</strong></span>
                                            <span><strong id="w-stat-red-cnt" class="text-white">0</strong> stk &bull; <span id="w-stat-red-pts" class="text-rose-300">-0</span></span>
                                        </div>
                                    </div>
                                    <!-- Previous Candle Micro-Footer -->
                                    <div class="pt-0.5 mt-1 border-t border-rose-500/20 flex items-center justify-between text-[8px] font-mono text-rose-300/60 leading-none">
                                        <span>Prev: <span id="w-prev-red-val" class="text-gray-300 font-semibold">--</span></span>
                                        <span id="w-prev-red-delta" class="font-bold">--</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Weight Influence Split Bar -->
                            <div class="space-y-0.5">
                                <div class="flex justify-between items-center text-[9px] text-gray-400">
                                    <span>Weight Split</span>
                                    <span id="w-stat-split" class="font-mono text-gray-300 font-bold">50% G / 50% R</span>
                                </div>
                                <div class="w-full h-1.5 bg-gray-800 rounded-full overflow-hidden flex">
                                    <div id="w-bar-green" class="h-full bg-emerald-500 transition-all duration-150" style="width: 50%;"></div>
                                    <div id="w-bar-red" class="h-full bg-rose-500 transition-all duration-150" style="width: 50%;"></div>
                                </div>
                            </div>

                            <!-- Institutional Verdict Badge -->
                            <div id="w-insight-card" class="px-2 py-1 rounded bg-gray-800/50 border border-gray-700/70 text-[9.5px]">
                                <div class="font-bold text-white truncate" id="w-insight-badge">🚀 Broad Institutional Buying</div>
                                <div id="w-divergence-banner" class="hidden"></div>
                            </div>

                            <!-- Key Movers Compact Ticker -->
                            <div class="flex items-center justify-between gap-1 text-[9px] font-mono px-0.5">
                                <div id="w-top-drivers" class="truncate text-emerald-300">
                                    <span class="text-gray-500 italic">Drivers: --</span>
                                </div>
                                <div id="w-top-draggers" class="truncate text-rose-300 text-right">
                                    <span class="text-gray-500 italic">Draggers: --</span>
                                </div>
                            </div>

                            <!-- Stock Trigger Strength Mini-Pill -->
                            <div class="flex items-center justify-between text-[8.5px] font-mono text-gray-400 pt-0.5 border-t border-gray-800/60">
                                <span class="flex items-center gap-1">
                                    <span>🎯 STK</span>
                                    <span id="stk-trigger-val-badge" class="text-[8px] text-sky-400 cursor-pointer" onclick="cycleStocksTriggerThreshold()">(≥80%)</span>
                                </span>
                                <span>▲ <strong id="stk-pos-triggers-count" class="text-emerald-400">0</strong> &bull; ▼ <strong id="stk-neg-triggers-count" class="text-rose-400">0</strong></span>
                            </div>
                        </div>

                        <!-- ══ DECK 2: OPTIONS FLOW & PARITY (CHART 1 TIMEFRAME) ══ -->
                        <div class="flex flex-col space-y-1.5 rounded-lg bg-[#131722]/95 border border-purple-900/60 p-2 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-purple-300 flex items-center gap-1.5">
                                    <span>⚡</span> <span>OPTIONS FLOW</span>
                                </span>
                                <div class="flex items-center gap-1 text-[9px] font-mono">
                                    <span class="text-gray-400">ATM:</span>
                                    <strong id="w-opt-stat-atm-val" class="text-amber-300">₹24,150</strong>
                                </div>
                            </div>

                            <!-- BUY vs SELL Compact Grid -->
                            <div class="grid grid-cols-2 gap-1.5">
                                <div class="bg-gradient-to-br from-emerald-950/70 to-emerald-900/30 border border-emerald-500/40 rounded-md px-2 py-1 shadow-inner flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-base sm:text-lg font-black text-emerald-300 font-mono leading-none" id="w-opt-stat-green-ratio">50%</span>
                                            <span class="px-1.5 py-0.5 bg-emerald-500/20 text-emerald-300 font-bold text-[8.5px] rounded uppercase">BUY</span>
                                        </div>
                                        <div class="text-[9.5px] text-gray-300 flex justify-between mt-1">
                                            <span id="w-opt-stat-green-wt">50% Wt</span>
                                            <span><strong id="w-opt-stat-green-cnt" class="text-white">0</strong> strk</span>
                                        </div>
                                    </div>
                                    <!-- Previous Candle Micro-Footer -->
                                    <div class="pt-0.5 mt-1 border-t border-emerald-500/20 flex items-center justify-between text-[8px] font-mono text-emerald-300/60 leading-none">
                                        <span>Prev: <span id="w-opt-prev-green-val" class="text-gray-300 font-semibold">--</span></span>
                                        <span id="w-opt-prev-green-delta" class="font-bold">--</span>
                                    </div>
                                </div>

                                <div class="bg-gradient-to-br from-rose-950/70 to-rose-900/30 border border-rose-500/40 rounded-md px-2 py-1 shadow-inner flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-base sm:text-lg font-black text-rose-300 font-mono leading-none" id="w-opt-stat-red-ratio">50%</span>
                                            <span class="px-1.5 py-0.5 bg-rose-500/20 text-rose-300 font-bold text-[8.5px] rounded uppercase">SELL</span>
                                        </div>
                                        <div class="text-[9.5px] text-gray-300 flex justify-between mt-1">
                                            <span id="w-opt-stat-red-wt">50% Wt</span>
                                            <span><strong id="w-opt-stat-red-cnt" class="text-white">0</strong> strk</span>
                                        </div>
                                    </div>
                                    <!-- Previous Candle Micro-Footer -->
                                    <div class="pt-0.5 mt-1 border-t border-rose-500/20 flex items-center justify-between text-[8px] font-mono text-rose-300/60 leading-none">
                                        <span>Prev: <span id="w-opt-prev-red-val" class="text-gray-300 font-semibold">--</span></span>
                                        <span id="w-opt-prev-red-delta" class="font-bold">--</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Options Balance Bar -->
                            <div class="space-y-0.5">
                                <div class="flex justify-between items-center text-[9px] text-gray-400">
                                    <span>Tug-of-War</span>
                                    <span id="w-opt-stat-split" class="font-mono text-gray-300 font-bold">50% / 50%</span>
                                </div>
                                <div class="w-full h-1.5 bg-gray-800 rounded-full overflow-hidden flex">
                                    <div id="w-opt-bar-green" class="h-full bg-emerald-500 transition-all duration-150" style="width: 50%;"></div>
                                    <div id="w-opt-bar-red" class="h-full bg-rose-500 transition-all duration-150" style="width: 50%;"></div>
                                </div>
                            </div>

                            <!-- Options Verdict Badge -->
                            <div class="px-2 py-1 rounded bg-gray-800/50 border border-gray-700/70 text-[9.5px]">
                                <div class="font-bold text-white truncate" id="w-opt-verdict-title">⚖️ Balanced Options Equilibrium</div>
                                <div id="w-opt-divergence-banner" class="hidden"></div>
                            </div>

                            <!-- Greeks 4-Grid Micro Bar -->
                            <div class="grid grid-cols-4 gap-1 text-center font-mono">
                                <div class="bg-[#181d2a] p-1 rounded border border-gray-800/80">
                                    <span class="text-[8px] text-gray-400 block leading-tight">Delta</span>
                                    <span class="font-bold text-[9.5px] text-sky-400" id="w-opt-greek-delta">0k</span>
                                </div>
                                <div class="bg-[#181d2a] p-1 rounded border border-gray-800/80">
                                    <span class="text-[8px] text-gray-400 block leading-tight">CE Veg</span>
                                    <span class="font-bold text-[9.5px] text-emerald-400" id="w-opt-greek-ce-vega">0.0</span>
                                </div>
                                <div class="bg-[#181d2a] p-1 rounded border border-gray-800/80">
                                    <span class="text-[8px] text-gray-400 block leading-tight">PE Veg</span>
                                    <span class="font-bold text-[9.5px] text-rose-400" id="w-opt-greek-pe-vega">0.0</span>
                                </div>
                                <div class="bg-[#181d2a] p-1 rounded border border-gray-800/80">
                                    <span class="text-[8px] text-gray-400 block leading-tight">PCR</span>
                                    <span class="font-bold text-[9.5px] text-amber-400" id="w-opt-greek-pcr">1.0</span>
                                </div>
                            </div>

                            <!-- Options Trigger Mini-Pill -->
                            <div class="flex items-center justify-between text-[8.5px] font-mono text-gray-400 pt-0.5 border-t border-gray-800/60">
                                <span class="flex items-center gap-1">
                                    <span>🎯 OPT</span>
                                    <span id="opt-trigger-val-badge" class="text-[8px] text-purple-400 cursor-pointer" onclick="cycleOptionsTriggerThreshold()">(≥70%)</span>
                                </span>
                                <span>▲ <strong id="w-opt-pos-triggers-count" class="text-emerald-400">0</strong> &bull; ▼ <strong id="w-opt-neg-triggers-count" class="text-rose-400">0</strong></span>
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
                        <div class="flex items-center justify-between gap-1 pb-1 mb-1 border-b border-gray-800 flex-nowrap overflow-hidden">
                            <div class="flex items-center gap-1 flex-nowrap shrink-0 overflow-x-auto scrollbar-none">
                                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                                <span id="o-chart-title" class="text-xs font-bold text-white tracking-wide shrink-0 whitespace-nowrap">OPTIONS</span>
                                <!-- Chart 2 Dedicated Timeframe Selector -->
                                <div class="flex items-center bg-[#151924] border border-purple-500/40 rounded p-0.5 gap-0.5 shrink-0 shadow-inner ml-1" id="o-tf-group" title="Options Flow Parity Timeframe">
                                    <button type="button" onclick="setChart2Timeframe('1m')" data-otf="1m" class="o-tf-btn px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800">1m</button>
                                    <button type="button" onclick="setChart2Timeframe('3m')" data-otf="3m" class="o-tf-btn px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800">3m</button>
                                    <button type="button" onclick="setChart2Timeframe('5m')" data-otf="5m" class="o-tf-btn px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800">5m</button>
                                    <button type="button" onclick="setChart2Timeframe('15m')" data-otf="15m" class="o-tf-btn px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800">15m</button>
                                    <button type="button" onclick="setChart2Timeframe('30m')" data-otf="30m" class="o-tf-btn px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800">30m</button>
                                    <button type="button" onclick="setChart2Timeframe('1h')" data-otf="1h" class="o-tf-btn px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800">1h</button>
                                    <button type="button" onclick="setChart2Timeframe('1D')" data-otf="1D" class="o-tf-btn px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800">1D</button>
                                </div>
                                <!-- Chart 2 Candlestick Mode (SPOT vs FUT) -->
                                <div class="flex items-center bg-[#151924] border border-purple-500/40 rounded p-0.5 gap-0.5 shrink-0 shadow-inner ml-1" title="Chart 2 Candlestick Mode: NIFTY 50 Spot Index vs Near-Month Futures">
                                    <button type="button" id="o-basis-spot-btn" onclick="setChart2CandleBasis('spot')" class="px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer bg-purple-600 text-white shadow-xs">SPOT</button>
                                    <button type="button" id="o-basis-fut-btn" onclick="setChart2CandleBasis('future')" class="px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800">FUT</button>
                                </div>
                                <!-- Chart 2 Dedicated Candle Close Countdown Badge -->
                                <div id="o-candle-timer-badge" class="flex items-center gap-1 px-1.5 py-0.5 rounded bg-purple-950/80 border border-purple-500/50 shadow-xs shrink-0 cursor-default ml-1" title="Bottom Chart Active Candle Countdown">
                                    <span class="flex h-1.5 w-1.5 relative shrink-0">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-purple-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-purple-500"></span>
                                    </span>
                                    <span id="o-candle-timer-label" class="text-purple-300 font-black text-[10px] tracking-wider uppercase whitespace-nowrap">15M:</span>
                                    <span id="o-candle-timer-countdown" class="font-mono font-black text-purple-200 text-xs tracking-wider leading-none select-none">--:--</span>
                                </div>
                                <button type="button" id="o-candle-style-btn" onclick="toggleOptionsCandleStyle()"
                                    class="ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 bg-gray-800 border-gray-600 text-gray-300 hover:text-white cursor-pointer"
                                    title="Toggle Candle Style: Regular Candlestick / Heikin Ashi (Row 2)">
                                    <span id="o-candle-style-icon">🕯️</span>
                                    <span id="o-candle-style-badge" class="font-mono text-emerald-400 font-extrabold">CANDLE</span>
                                </button>

                                <button type="button" id="o-of-menu-btn" onclick="toggleHistOfDropdown('o', this)" 
                                    class="ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 bg-teal-950/80 border-teal-500/50 text-teal-300 hover:bg-teal-900 cursor-pointer select-none"
                                    title="Order Flow Levels Menu (Live Today & 5D Historical S/R, Basis & Filters)">
                                    <span>🌊 OF:</span>
                                    <span id="o-of-sr-status-badge" class="font-mono text-emerald-400 font-extrabold">ON</span>
                                    <span id="o-of-calib-badge" class="px-1 py-0 rounded text-[8.5px] bg-cyan-950 text-cyan-300 font-mono font-extrabold border border-cyan-500/30">SPOT & FUT</span>
                                    <span id="o-hist-of-count-badge" class="px-1 py-0 rounded text-[8.5px] bg-amber-500 text-black font-extrabold font-mono">0</span>
                                    <span class="text-[8px] text-gray-400">▾</span>
                                </button>
                                <button type="button" id="o-marker-toggle-btn" onclick="cycleOptionsMarkerMode()"
                                    class="ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 bg-gray-800 border-gray-600 text-gray-300 hover:text-white cursor-pointer"
                                    title="Cycle Signals on Options Chart: Breach Only (≥70%) / Every 5M Candle / OFF">
                                    <span>🎯 Sig:</span>
                                    <span id="o-marker-status-badge" class="font-mono text-cyan-300 font-extrabold">BREACH</span>
                                </button>
                                <button type="button" id="o-ha-wick-filter-btn" onclick="toggleOptionsHaWickFilter()"
                                    class="ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 bg-amber-950/60 border-amber-500/50 text-amber-300 hover:bg-amber-900 cursor-pointer"
                                    title="Heikin Ashi Single-Wick Entry Filter: STRICT (Entry only on zero opposite wick; both wicks ignored) / OFF (All breaches)">
                                    <span>🎯 Wick:</span>
                                    <span id="o-ha-wick-filter-badge" class="font-mono text-emerald-400 font-extrabold">STRICT</span>
                                </button>
                                <button type="button" id="o-stk-signal-btn" onclick="toggleChart2StocksSignal()"
                                    class="ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 cursor-pointer bg-gray-800 border-gray-600 text-gray-400 hover:text-white"
                                    title="Toggle Nifty 50 Stock Alerts on Chart 2: ON (Display stock alerts here too) / OFF">
                                    <span>📊 STK:</span>
                                    <span id="o-stk-signal-badge" class="font-mono text-gray-400 font-bold">OFF</span>
                                </button>
                                <button type="button" id="o-opt-signal-btn" onclick="toggleChart2OptionsSignal()"
                                    class="ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 cursor-pointer bg-purple-950/70 border-purple-500/50 text-purple-300 hover:bg-purple-900"
                                    title="Toggle Option Flow Alerts on Chart 2: ON (Display option parity alerts) / OFF">
                                    <span>⚡ OPT:</span>
                                    <span id="o-opt-signal-badge" class="font-mono text-purple-400 font-extrabold">ON</span>
                                </button>
                                <button type="button" id="o-candle-hl-btn" onclick="toggleActiveCandleHighlight()"
                                    class="ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 cursor-pointer bg-gray-800 border-gray-600 text-gray-400 hover:text-white"
                                    title="Toggle Active Candle Highlight Beacon: OFF (Default - Clean live chart) / ON (Highlight active timeline candle on chart with beam & reticle) [Hotkey: H]">
                                    <span>🔦 HL:</span>
                                    <span id="o-candle-hl-badge" class="font-mono text-gray-400 font-bold">OFF</span>
                                </button>
                                <div class="flex items-center bg-[#151924] border border-gray-700/80 rounded px-1.5 py-0.5 text-xs gap-0.5 shrink-0 ml-1" title="Chart 2 & Data Panel Height">
                                    <span class="text-gray-400 text-[10px] font-bold">H:</span>
                                    <select id="o-height-select" onchange="setChart2Height(this.value)" class="bg-transparent text-purple-400 font-mono font-bold focus:outline-none cursor-pointer text-[11px]" title="Customize Chart 2 Height">
                                        <option value="300" class="bg-[#131722] text-white">300px</option>
                                        <option value="350" class="bg-[#131722] text-white">350px</option>
                                        <option value="380" class="bg-[#131722] text-white">380px</option>
                                        <option value="420" class="bg-[#131722] text-white">420px</option>
                                        <option value="460" class="bg-[#131722] text-white">460px</option>
                                        <option value="500" class="bg-[#131722] text-white">500px</option>
                                        <option value="550" class="bg-[#131722] text-white">550px</option>
                                        <option value="600" class="bg-[#131722] text-white">600px</option>
                                    </select>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 text-[10px] shrink-0 whitespace-nowrap ml-2">
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
                            <!-- Session Break Vertical Line -->
                            <div id="o-day-separator" class="day-separator pointer-events-none absolute top-0 bottom-[26px] z-10 hidden" style="width: 1px;">
                                <div class="h-full border-l-2 border-dashed border-purple-400/80 shadow-[0_0_8px_rgba(168,85,247,0.5)]"></div>
                                <div class="absolute top-2 -translate-x-1/2 flex items-center gap-1.5 bg-[#0d1322]/95 text-purple-300 text-[9px] font-mono font-bold tracking-wider px-2 py-0.5 rounded-full border border-purple-500/50 shadow-xl whitespace-nowrap select-none backdrop-blur-xs">
                                    <span class="w-1.5 h-1.5 rounded-full bg-purple-400 animate-pulse"></span>
                                    <span id="o-day-sep-label">TODAY</span>
                                </div>
                            </div>

                            <!-- Enterprise Active Candle Highlight Overlay (Beacon & Reticle) -->
                            <div id="o-candle-highlight" class="pointer-events-none absolute top-0 bottom-[26px] z-[15] hidden" style="left: 0;">
                                <div id="o-candle-highlight-beam" class="absolute top-0 bottom-0 pointer-events-none"></div>
                                <div id="o-candle-highlight-laser" class="absolute top-0 bottom-0 pointer-events-none"></div>
                                <div id="o-candle-highlight-reticle" class="absolute pointer-events-none"></div>
                                <div id="o-candle-highlight-pill" class="absolute top-1.5 pointer-events-none"></div>
                                <div id="o-candle-highlight-bot" class="absolute bottom-1 pointer-events-none"></div>
                            </div>

                            <!-- Floating TradingView Style Dock Toolbar -->
                            <div class="tv-dock absolute left-2 top-2 z-20 flex flex-col items-center bg-[#151924]/90 backdrop-blur-md border border-gray-700/80 rounded shadow-2xl p-0.5 gap-1 select-none">
                                <button type="button" onclick="setActiveOptionsTvTool('pointer')" id="o-tv-pointer" class="tv-tool-btn active w-6 h-6 flex items-center justify-center rounded transition text-white bg-sky-600 shadow-xs cursor-pointer" title="Pointer / Select Candle">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/></svg>
                                </button>
                                <button type="button" onclick="toggleOptionsHLineTool()" id="o-tv-hline" class="tv-tool-btn w-6 h-6 flex items-center justify-center rounded transition text-gray-400 hover:text-white hover:bg-gray-700 cursor-pointer" title="Draw Horizontal Support/Resistance Line (Click price on chart)">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12h18"/></svg>
                                </button>
                                <button type="button" onclick="cycleOptionsFibMode()" id="o-tv-fib" class="tv-tool-btn w-6 h-6 flex items-center justify-center rounded transition text-gray-400 hover:text-white hover:bg-gray-700 font-mono text-[9px] font-black cursor-pointer" title="Fibonacci Retracement (Auto / Manual / Off)">
                                    Fib
                                </button>
                                <button type="button" onclick="cycleOptionsSuperTrendMode()" id="o-tv-st" class="tv-tool-btn w-6 h-6 flex items-center justify-center rounded transition text-gray-400 hover:text-white hover:bg-gray-700 font-mono text-[9px] font-black cursor-pointer" title="SuperTrend Indicator (10,3 / 10,2 / Off)">
                                    ST
                                </button>
                                <div class="w-3.5 h-px bg-gray-700 my-0.5"></div>
                                <button type="button" onclick="clearOptionsManualDrawings()" class="tv-tool-btn w-6 h-6 flex items-center justify-center rounded transition text-gray-400 hover:text-rose-300 hover:bg-rose-950/60 cursor-pointer" title="Clear all drawings on Options Chart">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>

                            <!-- Interactive Tool Action Notification Hint -->
                            <div id="o-tv-hint" class="hidden absolute top-2 right-12 z-20 px-2.5 py-1 rounded bg-sky-950/95 border border-sky-400 text-sky-200 text-[10px] font-mono shadow-lg items-center gap-1.5 animate-pulse">
                                <span>💡</span>
                                <span id="o-tv-hint-text">Click price on chart</span>
                                <button type="button" onclick="setActiveOptionsTvTool('pointer')" class="ml-1 text-slate-400 hover:text-white font-bold cursor-pointer">✕</button>
                            </div>

                            <div id="o-chart-loading" class="absolute inset-0 bg-[#0d1017]/90 backdrop-blur-sm flex flex-col items-center justify-center gap-2.5 text-xs text-gray-300 font-medium z-30 transition-all duration-200">
                                <div class="relative w-8 h-8">
                                    <div class="w-8 h-8 border-2 border-indigo-500/20 border-t-indigo-500 rounded-full animate-spin"></div>
                                    <div class="absolute inset-0 flex items-center justify-center text-[10px] text-indigo-400 font-bold font-mono">OPT</div>
                                </div>
                                <span id="o-loading-msg" class="text-xs text-indigo-300 font-mono font-medium tracking-wide">Loading Options Parity &amp; Breadth...</span>
                            </div>
                        </div>

                        <!-- Scrubber Slider -->
                        <div class="pt-1.5 flex items-center gap-2 text-[10px] text-gray-400">
                            <span class="font-mono text-[9px]" id="o-slider-start">09:15</span>
                            <input id="o-slider" type="range" min="0" max="74" value="0" class="w-full h-1 bg-gray-700 rounded appearance-none cursor-pointer accent-indigo-400 focus:outline-none">
                            <span class="font-mono text-[9px]" id="o-slider-end">15:25</span>
                        </div>
                    </div>

                    <!-- Right: 25% Dual-Deck Mini Cockpit (Stocks & Options for Chart 2 Timeframe) -->
                    <div id="o-cockpit-panel" class="lg:col-span-3 flex flex-col justify-between border-t lg:border-t-0 lg:border-l border-gray-800 lg:pl-2.5 pt-2 lg:pt-0 cockpit-data-panel overflow-y-auto scrollbar-none" style="height: 420px; max-height: 420px;">
                        <!-- Unified Candle Header & Moves -->
                        <div class="flex items-center justify-between pb-1 border-b border-gray-800 shrink-0">
                            <div>
                                <span class="text-[9.5px] uppercase tracking-wider text-gray-400 font-medium">SELECTED CANDLE</span>
                                <div class="text-xs sm:text-sm font-bold text-sky-400 font-mono" id="o-stat-time">
                                    09:15 - {{ $selectedTimeframe === '1D' ? '1-Day candle' : $selectedTimeframe . ' candle' }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-right">
                                <div>
                                    <span class="text-[9px] text-gray-400 block leading-tight">Index Move</span>
                                    <div id="o-stk-stat-pts" class="text-xs sm:text-sm font-black font-mono text-emerald-400">+0.0 pts</div>
                                </div>
                                <div class="border-l border-gray-700/80 pl-2">
                                    <span class="text-[9px] text-gray-400 block leading-tight">Opt Impulse</span>
                                    <div id="o-stat-impulse" class="text-xs sm:text-sm font-black font-mono text-emerald-400">+0.00 pts</div>
                                </div>
                            </div>
                        </div>

                        <!-- ══ DECK 1: NIFTY 50 STOCKS (CHART 2 TIMEFRAME) ══ -->
                        <div class="flex flex-col space-y-1.5 rounded-lg bg-[#131722]/95 border border-sky-900/60 p-2 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-sky-300 flex items-center gap-1.5">
                                    <span>📊</span> <span>NIFTY 50 STOCKS</span>
                                </span>
                                <span id="o-stk-trigger-verdict-badge" class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-gray-800 text-gray-400 border border-gray-700">
                                    Normal
                                </span>
                            </div>

                            <!-- UP vs DOWN Compact Grid -->
                            <div class="grid grid-cols-2 gap-1.5">
                                <div id="o-stk-box-up" class="bg-gradient-to-br from-emerald-950/70 to-emerald-900/30 border border-emerald-500/40 rounded-md px-2 py-1 shadow-inner flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-base sm:text-lg font-black text-emerald-400 font-mono leading-none" id="o-stk-stat-green-pct">0%</span>
                                            <span class="px-1.5 py-0.5 bg-emerald-500/20 text-emerald-300 font-bold text-[8.5px] rounded uppercase">UP</span>
                                        </div>
                                        <div class="text-[9.5px] text-emerald-200/90 font-medium flex justify-between mt-1">
                                            <span>Wt: <strong id="o-stk-stat-green-wt" class="text-white">0%</strong></span>
                                            <span><strong id="o-stk-stat-green-cnt" class="text-white">0</strong> stk &bull; <span id="o-stk-stat-green-pts" class="text-emerald-300">+0</span></span>
                                        </div>
                                    </div>
                                    <!-- Previous Candle Micro-Footer -->
                                    <div class="pt-0.5 mt-1 border-t border-emerald-500/20 flex items-center justify-between text-[8px] font-mono text-emerald-300/60 leading-none">
                                        <span>Prev: <span id="o-stk-prev-green-val" class="text-gray-300 font-semibold">--</span></span>
                                        <span id="o-stk-prev-green-delta" class="font-bold">--</span>
                                    </div>
                                </div>

                                <div id="o-stk-box-down" class="bg-gradient-to-br from-rose-950/70 to-rose-900/30 border border-rose-500/40 rounded-md px-2 py-1 shadow-inner flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-base sm:text-lg font-black text-rose-400 font-mono leading-none" id="o-stk-stat-red-pct">0%</span>
                                            <span class="px-1.5 py-0.5 bg-rose-500/20 text-rose-300 font-bold text-[8.5px] rounded uppercase">DN</span>
                                        </div>
                                        <div class="text-[9.5px] text-rose-200/90 font-medium flex justify-between mt-1">
                                            <span>Wt: <strong id="o-stk-stat-red-wt" class="text-white">0%</strong></span>
                                            <span><strong id="o-stk-stat-red-cnt" class="text-white">0</strong> stk &bull; <span id="o-stk-stat-red-pts" class="text-rose-300">-0</span></span>
                                        </div>
                                    </div>
                                    <!-- Previous Candle Micro-Footer -->
                                    <div class="pt-0.5 mt-1 border-t border-rose-500/20 flex items-center justify-between text-[8px] font-mono text-rose-300/60 leading-none">
                                        <span>Prev: <span id="o-stk-prev-red-val" class="text-gray-300 font-semibold">--</span></span>
                                        <span id="o-stk-prev-red-delta" class="font-bold">--</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Weight Influence Split Bar -->
                            <div class="space-y-0.5">
                                <div class="flex justify-between items-center text-[9px] text-gray-400">
                                    <span>Weight Split</span>
                                    <span id="o-stk-stat-split" class="font-mono text-gray-300 font-bold">50% G / 50% R</span>
                                </div>
                                <div class="w-full h-1.5 bg-gray-800 rounded-full overflow-hidden flex">
                                    <div id="o-stk-bar-green" class="h-full bg-emerald-500 transition-all duration-150" style="width: 50%;"></div>
                                    <div id="o-stk-bar-red" class="h-full bg-rose-500 transition-all duration-150" style="width: 50%;"></div>
                                </div>
                            </div>

                            <!-- Institutional Verdict Badge -->
                            <div id="o-stk-insight-card" class="px-2 py-1 rounded bg-gray-800/50 border border-gray-700/70 text-[9.5px]">
                                <div class="font-bold text-white truncate" id="o-stk-insight-badge">🚀 Broad Institutional Buying</div>
                                <div id="o-stk-divergence-banner" class="hidden"></div>
                            </div>

                            <!-- Key Movers Compact Ticker -->
                            <div class="flex items-center justify-between gap-1 text-[9px] font-mono px-0.5">
                                <div id="o-stk-top-drivers" class="truncate text-emerald-300">
                                    <span class="text-gray-500 italic">Drivers: --</span>
                                </div>
                                <div id="o-stk-top-draggers" class="truncate text-rose-300 text-right">
                                    <span class="text-gray-500 italic">Draggers: --</span>
                                </div>
                            </div>

                            <!-- Stock Trigger Strength Mini-Pill -->
                            <div class="flex items-center justify-between text-[8.5px] font-mono text-gray-400 pt-0.5 border-t border-gray-800/60">
                                <span>🎯 STK (Chart 2):</span>
                                <span>▲ <strong id="o-stk-pos-triggers-count" class="text-emerald-400">0</strong> &bull; ▼ <strong id="o-stk-neg-triggers-count" class="text-rose-400">0</strong></span>
                            </div>
                        </div>

                        <!-- ══ DECK 2: OPTIONS FLOW & PARITY (CHART 2 TIMEFRAME) ══ -->
                        <div class="flex flex-col space-y-1.5 rounded-lg bg-[#131722]/95 border border-purple-900/60 p-2 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-purple-300 flex items-center gap-1.5">
                                    <span>⚡</span> <span>OPTIONS FLOW</span>
                                </span>
                                <div class="flex items-center gap-1 text-[9px] font-mono">
                                    <span class="text-gray-400">ATM:</span>
                                    <strong id="o-stat-atm-val" class="text-amber-300">₹24,150</strong>
                                </div>
                            </div>

                            <!-- BUY vs SELL Compact Grid -->
                            <div class="grid grid-cols-2 gap-1.5">
                                <div class="bg-gradient-to-br from-emerald-950/70 to-emerald-900/30 border border-emerald-500/40 rounded-md px-2 py-1 shadow-inner flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-base sm:text-lg font-black text-emerald-300 font-mono leading-none" id="o-stat-green-ratio">50%</span>
                                            <span class="px-1.5 py-0.5 bg-emerald-500/20 text-emerald-300 font-bold text-[8.5px] rounded uppercase">BUY</span>
                                        </div>
                                        <div class="text-[9.5px] text-gray-300 flex justify-between mt-1">
                                            <span id="o-stat-green-wt">50% Wt</span>
                                            <span><strong id="o-stat-green-cnt" class="text-white">0</strong> strk</span>
                                        </div>
                                    </div>
                                    <!-- Previous Candle Micro-Footer -->
                                    <div class="pt-0.5 mt-1 border-t border-emerald-500/20 flex items-center justify-between text-[8px] font-mono text-emerald-300/60 leading-none">
                                        <span>Prev: <span id="o-opt-prev-green-val" class="text-gray-300 font-semibold">--</span></span>
                                        <span id="o-opt-prev-green-delta" class="font-bold">--</span>
                                    </div>
                                </div>

                                <div class="bg-gradient-to-br from-rose-950/70 to-rose-900/30 border border-rose-500/40 rounded-md px-2 py-1 shadow-inner flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-base sm:text-lg font-black text-rose-300 font-mono leading-none" id="o-stat-red-ratio">50%</span>
                                            <span class="px-1.5 py-0.5 bg-rose-500/20 text-rose-300 font-bold text-[8.5px] rounded uppercase">SELL</span>
                                        </div>
                                        <div class="text-[9.5px] text-gray-300 flex justify-between mt-1">
                                            <span id="o-stat-red-wt">50% Wt</span>
                                            <span><strong id="o-stat-red-cnt" class="text-white">0</strong> strk</span>
                                        </div>
                                    </div>
                                    <!-- Previous Candle Micro-Footer -->
                                    <div class="pt-0.5 mt-1 border-t border-rose-500/20 flex items-center justify-between text-[8px] font-mono text-rose-300/60 leading-none">
                                        <span>Prev: <span id="o-opt-prev-red-val" class="text-gray-300 font-semibold">--</span></span>
                                        <span id="o-opt-prev-red-delta" class="font-bold">--</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Options Balance Bar -->
                            <div class="space-y-0.5">
                                <div class="flex justify-between items-center text-[9px] text-gray-400">
                                    <span>Tug-of-War</span>
                                    <span id="o-stat-split" class="font-mono text-gray-300 font-bold">50% / 50%</span>
                                </div>
                                <div class="w-full h-1.5 bg-gray-800 rounded-full overflow-hidden flex">
                                    <div id="o-bar-green" class="h-full bg-emerald-500 transition-all duration-150" style="width: 50%;"></div>
                                    <div id="o-bar-red" class="h-full bg-rose-500 transition-all duration-150" style="width: 50%;"></div>
                                </div>
                            </div>

                            <!-- Options Verdict Badge -->
                            <div class="px-2 py-1 rounded bg-gray-800/50 border border-gray-700/70 text-[9.5px]">
                                <div class="font-bold text-white truncate" id="o-verdict-title">⚖️ Balanced Options Equilibrium</div>
                                <div id="o-divergence-banner" class="hidden"></div>
                            </div>

                            <!-- Greeks 4-Grid Micro Bar -->
                            <div class="grid grid-cols-4 gap-1 text-center font-mono">
                                <div class="bg-[#181d2a] p-1 rounded border border-gray-800/80">
                                    <span class="text-[8px] text-gray-400 block leading-tight">Delta</span>
                                    <span class="font-bold text-[9.5px] text-sky-400" id="o-greek-delta">0k</span>
                                </div>
                                <div class="bg-[#181d2a] p-1 rounded border border-gray-800/80">
                                    <span class="text-[8px] text-gray-400 block leading-tight">CE Veg</span>
                                    <span class="font-bold text-[9.5px] text-emerald-400" id="o-greek-ce-vega">0.0</span>
                                </div>
                                <div class="bg-[#181d2a] p-1 rounded border border-gray-800/80">
                                    <span class="text-[8px] text-gray-400 block leading-tight">PE Veg</span>
                                    <span class="font-bold text-[9.5px] text-rose-400" id="o-greek-pe-vega">0.0</span>
                                </div>
                                <div class="bg-[#181d2a] p-1 rounded border border-gray-800/80">
                                    <span class="text-[8px] text-gray-400 block leading-tight">PCR</span>
                                    <span class="font-bold text-[9.5px] text-amber-400" id="o-greek-pcr">1.0</span>
                                </div>
                            </div>

                            <!-- Options Trigger Mini-Pill -->
                            <div class="flex items-center justify-between text-[8.5px] font-mono text-gray-400 pt-0.5 border-t border-gray-800/60">
                                <span>🎯 OPT (Chart 2):</span>
                                <span>▲ <strong id="opt-pos-triggers-count" class="text-emerald-400">0</strong> &bull; ▼ <strong id="opt-neg-triggers-count" class="text-rose-400">0</strong></span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>

        <!-- COLUMN 2 (25% WIDTH): TWO ROWS (TRENDING OI & SENTIMENT, OI BUILDUP)          -->
        <!-- ──────────────────────────────────────────────────────────────────────────── -->
        <div class="xl:col-span-3 flex flex-col space-y-2 w-full cockpit-data-panel">

            <!-- ── ROW 1: TRENDING OI & SENTIMENT CHART (ALIGNED WITH ROW 1 WEIGHTAGE) ── -->
            <div class="cockpit-card p-2 flex flex-col border-rose-900/40 shadow-md">
                <div class="flex items-center justify-between pb-1 mb-1 border-b border-gray-800 gap-1 flex-wrap">
                    <div class="flex items-center gap-1 flex-nowrap shrink-0 overflow-x-auto scrollbar-none">
                        <h3 class="text-xs font-bold text-white flex items-center gap-1">
                            📈 Trending OI &amp; Sentiment
                        </h3>

                        {{-- ⚡ Stream Mode Toggle (Live Stream vs 5 Min) --}}
                        <div class="flex items-center bg-slate-900/90 p-0.5 rounded border border-slate-700/80 text-[8.5px] font-bold select-none shrink-0">
                            <button type="button" id="toi-mode-live" onclick="setToiStreamMode('live')" class="px-1.5 py-0.5 rounded transition flex items-center gap-1 cursor-pointer bg-emerald-600 text-white shadow-xs" title="Live Webstream via Upstox WebSocket (Real-time OI ticks)">
                                <span id="toi-stream-dot" class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                                <span id="toi-stream-label">⚡ Live Stream</span>
                            </button>
                            <button type="button" id="toi-mode-5m" onclick="setToiStreamMode('5m')" class="px-1.5 py-0.5 rounded transition flex items-center gap-1 cursor-pointer text-slate-400 hover:text-white" title="5-Minute Sampled Candles &amp; Polling">
                                <span>⏱ 5 Min</span>
                            </button>
                        </div>

                        {{-- 🎯 ATM Strike Basket Mode Quick Toggle: Static (Morning Open) vs Dynamic (Live Spot) --}}
                        <div class="flex items-center bg-slate-900/90 p-0.5 rounded border border-slate-700/80 text-[8.5px] font-bold select-none shrink-0" title="ATM Strike Basket Calculation Mode">
                            <button type="button" id="toi-basket-fixed" onclick="setStrikeBasketMode('fixed')" class="px-1.5 py-0.5 rounded transition flex items-center gap-0.5 cursor-pointer bg-amber-600 text-white shadow-xs" title="Static Morning ATM (Default): Locks strikes to 9:15 AM market opening price all day (Shows true cumulative OI build/unwind)">
                                <span>⚓ Static</span>
                            </button>
                            <button type="button" id="toi-basket-dynamic" onclick="setStrikeBasketMode('dynamic')" class="px-1.5 py-0.5 rounded transition flex items-center gap-0.5 cursor-pointer text-slate-400 hover:text-white" title="Dynamic Floating ATM: Shifts ATM strikes dynamically as market spot rallies/falls">
                                <span>🚀 Dynamic</span>
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center gap-1 sm:gap-1.5 text-[9px] select-none">
                        <button type="button" id="toi-toggle-call" onclick="toggleToiSeries('call')" class="px-1.5 py-0.5 rounded border transition flex items-center gap-1 cursor-pointer bg-emerald-950/60 border-emerald-500/50 text-emerald-300 font-semibold hover:brightness-125" title="Call OI Change (Click to toggle)">
                            <span id="toi-dot-call" class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            <span>Call</span>
                        </button>
                        <button type="button" id="toi-toggle-put" onclick="toggleToiSeries('put')" class="px-1.5 py-0.5 rounded border transition flex items-center gap-1 cursor-pointer bg-rose-950/60 border-rose-500/50 text-rose-300 font-semibold hover:brightness-125" title="Put OI Change (Click to toggle)">
                            <span id="toi-dot-put" class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                            <span>Put</span>
                        </button>
                        <button type="button" id="toi-toggle-sentiment" onclick="toggleToiSeries('sentiment')" class="px-1.5 py-0.5 rounded border transition flex items-center gap-1 cursor-pointer bg-amber-950/60 border-amber-500/50 text-amber-300 font-semibold hover:brightness-125" title="Sentiment Diff OI (Click to toggle)">
                            <span id="toi-dot-sentiment" class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                            <span>Sentiment</span>
                        </button>
                        <button type="button" id="toi-toggle-spot" onclick="toggleToiSeries('spot')" class="px-1.5 py-0.5 rounded border transition flex items-center gap-1 cursor-pointer bg-cyan-950/60 border-cyan-500/50 text-cyan-300 font-semibold hover:brightness-125" title="Spot Price (Click to toggle)">
                            <span id="toi-dot-spot" class="w-2 h-0.5 border-t border-dashed border-cyan-400"></span>
                            <span>Spot</span>
                        </button>
                        <div class="flex items-center bg-[#151922] border border-gray-700/80 rounded px-1.5 py-0.5 text-xs gap-0.5 shrink-0 ml-1" title="Trending OI Chart Height">
                            <span class="text-gray-400 text-[10px] font-bold">H:</span>
                            <select id="toi-height-select" onchange="setToiChartHeight(this.value)" class="bg-transparent text-amber-300 font-mono font-bold focus:outline-none cursor-pointer text-[11px]" title="Customize Trending OI Chart Height">
                                <option value="180" class="bg-[#131722] text-white">180px</option>
                                <option value="220" class="bg-[#131722] text-white">220px</option>
                                <option value="250" class="bg-[#131722] text-white">250px</option>
                                <option value="280" class="bg-[#131722] text-white">280px</option>
                                <option value="320" class="bg-[#131722] text-white">320px</option>
                                <option value="380" class="bg-[#131722] text-white">380px</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Active Basket Strikes Ribbon with ATM Highlight & Manual Selection --}}
                <div id="toi-strikes-container" class="flex items-center gap-1.5 py-1 px-1.5 mb-1 bg-[#0e131d]/90 rounded border border-gray-800/70 text-[9.5px] font-mono select-none overflow-hidden">
                    <div class="flex items-center gap-1 shrink-0 text-slate-400 font-sans font-semibold text-[9px]">
                        <span class="text-amber-400">🎯</span>
                        <span>Strikes:</span>
                        <span id="toi-strikes-count" class="text-slate-500 font-mono text-[8.5px]">(15)</span>
                        <span id="toi-atm-mode-indicator" class="hidden"></span>
                    </div>
                    <button type="button" onclick="stepManualAtmStrike(-1)" class="px-1 py-0.2 rounded bg-slate-900 border border-slate-700 hover:border-amber-400 text-slate-300 hover:text-amber-300 text-[9px] font-bold shrink-0 cursor-pointer transition select-none" title="Step ATM 1 strike down (-50 pts)">◀</button>
                    <div id="toi-strikes-list" class="flex items-center gap-1 overflow-x-auto scrollbar-none py-0.5 flex-1 min-w-0">
                        <span class="text-gray-500 italic text-[9px]">Loading strikes...</span>
                    </div>
                    <button type="button" onclick="stepManualAtmStrike(1)" class="px-1 py-0.2 rounded bg-slate-900 border border-slate-700 hover:border-amber-400 text-slate-300 hover:text-amber-300 text-[9px] font-bold shrink-0 cursor-pointer transition select-none" title="Step ATM 1 strike up (+50 pts)">▶</button>
                </div>

                <div class="relative w-full overflow-hidden" id="toi-chart-wrapper" style="height: 250px;">
                    <div id="toi-chart-loading" class="absolute inset-0 bg-[#0d1017]/90 backdrop-blur-sm flex flex-col items-center justify-center gap-2 text-xs text-gray-300 font-medium z-30 transition-all duration-200">
                        <div class="w-6 h-6 border-2 border-amber-500/20 border-t-amber-500 rounded-full animate-spin"></div>
                        <span id="toi-loading-msg" class="text-[11px] text-amber-300 font-mono font-medium tracking-wide">Loading Trending OI &amp; Sentiment...</span>
                    </div>
                    <canvas id="chart-trending-oi"></canvas>
                </div>

                <!-- ── SEPARATE DECISION WIDGET (NET DIFF HERO & OI METRICS) ── -->
                <div id="toi-decision-widget" class="mt-1.5 pt-1.5 border-t border-gray-800/80 flex flex-col gap-1 select-none">
                    <!-- Top Hero: Net Diff (OI Skew) & Direction Conviction -->
                    <div class="flex items-center justify-between bg-gradient-to-r from-[#121622] via-[#161d2d] to-[#121622] px-2.5 py-1.5 rounded-lg border border-slate-700/60 shadow-inner flex-wrap gap-2">
                        <div class="flex items-baseline gap-2">
                            <span class="text-[9.5px] uppercase font-bold tracking-wider text-slate-400">NET DIFF (OI SKEW):</span>
                            <span id="toi-bottom-diff-hero" class="text-2xl sm:text-3xl font-black font-mono tracking-tight leading-none text-emerald-400 drop-shadow-[0_0_12px_rgba(52,211,153,0.35)]">
                                +0.0 L
                            </span>
                            <span id="toi-bottom-diff-pct" class="text-xs sm:text-sm font-black font-mono tracking-tight px-1.5 py-0.5 rounded border transition-colors cursor-help shadow-xs bg-slate-900 border-slate-700 text-slate-400" title="% Difference between CE and PE">
                                (0.0%)
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span id="toi-bottom-direction-pill" class="px-2.5 py-1 rounded-md font-mono font-black text-[10.5px] uppercase border shadow-md tracking-wider bg-slate-900 border-slate-700 text-slate-300">
                                ◆ NEUTRAL
                            </span>
                            <span id="toi-bottom-time" class="text-[9.5px] font-mono text-slate-400 px-1.5 py-0.5 rounded bg-slate-900/80 border border-slate-800">
                                --:--
                            </span>
                        </div>
                    </div>

                    <!-- Bottom Metrics Grid: PE, CE, PE/CE Ratio, Spot Price -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 font-mono">
                        <!-- PUT OI (PE) -->
                        <div class="bg-[#151922] p-1.5 rounded border border-rose-950/70 text-left">
                            <div class="text-[8.5px] uppercase font-bold text-rose-400 flex items-center justify-between">
                                <span>PUT OI (PE)</span>
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                            </div>
                            <div id="toi-bottom-pe-val" class="text-sm sm:text-base font-black text-rose-300 mt-0.5 leading-tight">
                                0.0 L
                            </div>
                            <div id="toi-bottom-pe-raw" class="text-[8px] text-gray-500 truncate mt-0.5">
                                0 contracts
                            </div>
                        </div>

                        <!-- CALL OI (CE) -->
                        <div class="bg-[#151922] p-1.5 rounded border border-emerald-950/70 text-left">
                            <div class="text-[8.5px] uppercase font-bold text-emerald-400 flex items-center justify-between">
                                <span>CALL OI (CE)</span>
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            </div>
                            <div id="toi-bottom-ce-val" class="text-sm sm:text-base font-black text-emerald-300 mt-0.5 leading-tight">
                                0.0 L
                            </div>
                            <div id="toi-bottom-ce-raw" class="text-[8px] text-gray-500 truncate mt-0.5">
                                0 contracts
                            </div>
                        </div>

                        <!-- PE/CE RATIO (PCR) -->
                        <div class="bg-[#151922] p-1.5 rounded border border-amber-950/70 text-left">
                            <div class="text-[8.5px] uppercase font-bold text-amber-400 flex items-center justify-between">
                                <span>PE/CE RATIO</span>
                                <span class="text-[8px] text-amber-500/80 font-normal">PCR</span>
                            </div>
                            <div id="toi-bottom-ratio-val" class="text-sm sm:text-base font-black text-amber-300 mt-0.5 leading-tight">
                                1.00x
                            </div>
                            <div id="toi-bottom-bias" class="text-[8px] text-amber-400/80 truncate mt-0.5">
                                Balanced
                            </div>
                        </div>

                        <!-- SPOT PRICE -->
                        <div class="bg-[#151922] p-1.5 rounded border border-cyan-950/70 text-left">
                            <div class="text-[8.5px] uppercase font-bold text-cyan-400 flex items-center justify-between">
                                <span>SPOT PRICE</span>
                                <span class="w-2 h-0.5 border-t border-dashed border-cyan-400"></span>
                            </div>
                            <div id="toi-bottom-spot-val" class="text-sm sm:text-base font-black text-cyan-300 mt-0.5 leading-tight">
                                ₹--
                            </div>
                            <div id="toi-bottom-spot-time" class="text-[8px] text-cyan-400/70 truncate mt-0.5">
                                Nifty 50 Spot
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── ROW 2: FLOW SYNERGY & OI BUILDUP (TABBED COCKPIT) ── -->
            <div class="cockpit-card p-2 flex flex-col border-indigo-900/40 shadow-md">
                <!-- Header with Tab Switcher & Dynamic Controls (Single Line) -->
                <div class="flex items-center justify-between gap-1 pb-1 mb-1 border-b border-gray-800 flex-nowrap select-none text-[9px] relative z-20">
                    <!-- Left group: Tabs + Style + Metric + Threshold in one single row -->
                    <div class="flex items-center gap-1 sm:gap-1.5 shrink-0">
                        <!-- Tab Switcher (Flow Synergy vs OI Buildup) -->
                        <div class="flex items-center gap-0.5 bg-[#131722] p-0.5 rounded border border-gray-700/80 shrink-0">
                            <button type="button" id="tab-btn-synergy" onclick="switchBuildupTab('synergy')" 
                                class="px-1.5 py-0.5 rounded text-[9.5px] font-bold flex items-center gap-1 transition cursor-pointer bg-indigo-600 text-white shadow-xs">
                                <span>⚡</span>
                                <span>Synergy</span>
                            </button>
                            <button type="button" id="tab-btn-buildup" onclick="switchBuildupTab('buildup')" 
                                class="px-1.5 py-0.5 rounded text-[9.5px] font-bold flex items-center gap-1 transition cursor-pointer text-gray-400 hover:text-white">
                                <span>🔥</span>
                                <span>Buildup</span>
                            </button>
                        </div>

                        <!-- Dynamic Controls for Flow Synergy Tab -->
                        <div id="synergy-controls" class="flex items-center gap-1 shrink-0">
                            <!-- Visual Style Toggle: 📊 Bar | ▦ Matrix -->
                            <div class="flex items-center bg-[#151922] p-0.5 rounded border border-gray-700/80 text-[8.5px] font-bold" title="Synergy Visual Style">
                                <button type="button" id="synergy-style-hist" onclick="setSynergyChartStyle('hist')" class="px-1.5 py-0.5 rounded transition cursor-pointer bg-sky-600 text-white" title="Dual Bars around 0 (Default)">📊 Bar</button>
                                <button type="button" id="synergy-style-matrix" onclick="setSynergyChartStyle('matrix')" class="px-1.5 py-0.5 rounded transition cursor-pointer text-gray-400 hover:text-white" title="Timeline Barcode Matrix">▦ Matrix</button>
                            </div>

                            <!-- Metrics Toggle: Both (Weight & Count) | Weight | Count -->
                            <div class="flex items-center bg-[#151922] p-0.5 rounded border border-gray-700/80 text-[8px] font-bold" title="Stocks Metrics to Display">
                                <button type="button" id="synergy-metric-both" onclick="setSynergyMetricMode('both')" class="px-1.5 py-0.5 rounded transition cursor-pointer bg-indigo-600 text-white" title="Show Both Weight % and Count %">Wt+Cnt</button>
                                <button type="button" id="synergy-metric-weight" onclick="setSynergyMetricMode('weight')" class="px-1.5 py-0.5 rounded transition cursor-pointer text-gray-400 hover:text-white" title="Show Weight % Only">Wt</button>
                                <button type="button" id="synergy-metric-count" onclick="setSynergyMetricMode('count')" class="px-1.5 py-0.5 rounded transition cursor-pointer text-gray-400 hover:text-white" title="Show Count % Only">Cnt</button>
                            </div>

                            <!-- Custom Alert Threshold Selector Popover (Stk Wt, Stk Cnt & Opt Flow) -->
                            <div class="relative shrink-0" id="synergy-threshold-dropdown-wrap">
                                <button type="button" id="synergy-threshold-btn" onclick="toggleSynergyThresholdMenu()" 
                                    class="flex items-center bg-[#151922] hover:bg-[#1a202c] border border-amber-500/50 hover:border-amber-400 rounded px-1.5 py-0.5 gap-1.5 transition cursor-pointer text-[9px] shadow-xs" 
                                    title="Click to customize Confluence Alert Criteria for Stk Wt, Stk Cnt & Opt Flow">
                                    <span class="text-amber-400 text-[10px]">⚙️</span>
                                    <span class="flex items-center gap-1 font-mono text-[9px] font-bold">
                                        <span class="text-sky-300" title="Strike Weightage Alert Threshold">W:<strong id="th-btn-val-wt">80</strong>%</span>
                                        <span class="text-teal-300" title="Strike Count Alert Threshold">C:<strong id="th-btn-val-cnt">80</strong>%</span>
                                        <span class="text-purple-300" title="Option Flow Alert Threshold">O:<strong id="th-btn-val-opt">70</strong>%</span>
                                    </span>
                                    <span id="synergy-th-arrow" class="text-amber-300 text-[8px] transition-transform duration-200">▼</span>
                                </button>

                                <!-- Floating Dropdown Popover Panel -->
                                <div id="synergy-threshold-popover" class="hidden absolute right-0 top-full mt-1.5 w-72 bg-[#10141e]/98 border border-amber-500/60 rounded-xl shadow-[0_10px_25px_-5px_rgba(0,0,0,0.8),0_0_15px_rgba(245,158,11,0.15)] p-3 z-50 space-y-2.5 backdrop-blur-md">
                                    {{-- Header --}}
                                    <div class="flex items-center justify-between pb-1.5 border-b border-gray-800 text-[10px]">
                                        <div class="flex items-center gap-1.5 font-bold text-amber-300">
                                            <span>⚙️</span>
                                            <span>Confluence Alert Criteria (±Threshold)</span>
                                        </div>
                                        <button type="button" onclick="toggleSynergyThresholdMenu(false)" class="text-gray-400 hover:text-white font-bold text-xs px-1 py-0.5 rounded hover:bg-slate-800 cursor-pointer">✕</button>
                                    </div>

                                    {{-- Explanatory hint --}}
                                    <div class="text-[8.5px] text-gray-400 font-sans leading-tight">
                                        Set individual criteria for each metric. Enter manually or click <span class="text-amber-300 font-bold">- / +</span> to step by 1 point.
                                    </div>

                                    {{-- 1. Strike Weightage (Stk Wt) --}}
                                    <div class="flex items-center justify-between gap-2 p-1.5 rounded bg-[#151922] border border-sky-500/30">
                                        <div class="flex items-center gap-1.5 text-sky-400 font-bold text-[9.5px] shrink-0" title="Nifty 50 Stocks Weight Dominance">
                                            <span class="w-2 h-2 rounded-full bg-sky-400 shrink-0"></span>
                                            <span>Stk Wt (±)</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button type="button" onclick="stepSynergyThreshold('wt', -1)" class="w-6 h-6 flex items-center justify-center rounded bg-slate-800 hover:bg-slate-700 active:bg-sky-700 text-sky-300 font-bold text-xs transition cursor-pointer select-none" title="Decrease Stk Wt by 1 point">-</button>
                                            <div class="flex items-center bg-[#0d1117] border border-gray-700 rounded px-1.5 py-0.5 focus-within:border-sky-400">
                                                <input type="number" id="th-input-wt" min="10" max="99" step="1" value="80" oninput="onSynergyThresholdInput('wt', this.value)" class="w-7 text-center bg-transparent text-sky-300 font-mono font-bold text-[11px] focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                                <span class="text-gray-400 text-[9px] font-mono">%</span>
                                            </div>
                                            <button type="button" onclick="stepSynergyThreshold('wt', 1)" class="w-6 h-6 flex items-center justify-center rounded bg-slate-800 hover:bg-slate-700 active:bg-sky-700 text-sky-300 font-bold text-xs transition cursor-pointer select-none" title="Increase Stk Wt by 1 point">+</button>
                                        </div>
                                    </div>

                                    {{-- 2. Strike Count (Stk Cnt) --}}
                                    <div class="flex items-center justify-between gap-2 p-1.5 rounded bg-[#151922] border border-teal-500/30">
                                        <div class="flex items-center gap-1.5 text-teal-400 font-bold text-[9.5px] shrink-0" title="Nifty 50 Stocks Count Dominance">
                                            <span class="w-2 h-2 rounded-full bg-teal-400 shrink-0"></span>
                                            <span>Stk Cnt (±)</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button type="button" onclick="stepSynergyThreshold('cnt', -1)" class="w-6 h-6 flex items-center justify-center rounded bg-slate-800 hover:bg-slate-700 active:bg-teal-700 text-teal-300 font-bold text-xs transition cursor-pointer select-none" title="Decrease Stk Cnt by 1 point">-</button>
                                            <div class="flex items-center bg-[#0d1117] border border-gray-700 rounded px-1.5 py-0.5 focus-within:border-teal-400">
                                                <input type="number" id="th-input-cnt" min="10" max="99" step="1" value="80" oninput="onSynergyThresholdInput('cnt', this.value)" class="w-7 text-center bg-transparent text-teal-300 font-mono font-bold text-[11px] focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                                <span class="text-gray-400 text-[9px] font-mono">%</span>
                                            </div>
                                            <button type="button" onclick="stepSynergyThreshold('cnt', 1)" class="w-6 h-6 flex items-center justify-center rounded bg-slate-800 hover:bg-slate-700 active:bg-teal-700 text-teal-300 font-bold text-xs transition cursor-pointer select-none" title="Increase Stk Cnt by 1 point">+</button>
                                        </div>
                                    </div>

                                    {{-- 3. Option Flow (Opt Flow) --}}
                                    <div class="flex items-center justify-between gap-2 p-1.5 rounded bg-[#151922] border border-purple-500/30">
                                        <div class="flex items-center gap-1.5 text-purple-400 font-bold text-[9.5px] shrink-0" title="Options Flow Dominance">
                                            <span class="w-2 h-2 rounded-full bg-purple-400 shrink-0"></span>
                                            <span>Opt Flow (±)</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button type="button" onclick="stepSynergyThreshold('opt', -1)" class="w-6 h-6 flex items-center justify-center rounded bg-slate-800 hover:bg-slate-700 active:bg-purple-700 text-purple-300 font-bold text-xs transition cursor-pointer select-none" title="Decrease Opt Flow by 1 point">-</button>
                                            <div class="flex items-center bg-[#0d1117] border border-gray-700 rounded px-1.5 py-0.5 focus-within:border-purple-400">
                                                <input type="number" id="th-input-opt" min="10" max="99" step="1" value="70" oninput="onSynergyThresholdInput('opt', this.value)" class="w-7 text-center bg-transparent text-purple-300 font-mono font-bold text-[11px] focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                                <span class="text-gray-400 text-[9px] font-mono">%</span>
                                            </div>
                                            <button type="button" onclick="stepSynergyThreshold('opt', 1)" class="w-6 h-6 flex items-center justify-center rounded bg-slate-800 hover:bg-slate-700 active:bg-purple-700 text-purple-300 font-bold text-xs transition cursor-pointer select-none" title="Increase Opt Flow by 1 point">+</button>
                                        </div>
                                    </div>

                                    {{-- Quick Presets --}}
                                    <div class="pt-1.5 border-t border-gray-800 flex items-center justify-between gap-1 text-[8.5px]">
                                        <span class="text-gray-400 font-bold">Presets:</span>
                                        <div class="flex items-center gap-1">
                                            <button type="button" onclick="setSynergyThresholdPreset(80, 80, 70)" class="px-1.5 py-0.5 rounded bg-amber-500/20 hover:bg-amber-500/35 text-amber-300 border border-amber-500/40 font-mono font-bold transition cursor-pointer" title="Recommended: 80% Stk Wt, 80% Stk Cnt, 70% Opt Flow">80/80/70</button>
                                            <button type="button" onclick="setSynergyThresholdPreset(75, 75, 75)" class="px-1.5 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-gray-300 border border-gray-700 font-mono font-bold transition cursor-pointer" title="Equal 75% for all">75/75/75</button>
                                            <button type="button" onclick="setSynergyThresholdPreset(85, 85, 75)" class="px-1.5 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-gray-300 border border-gray-700 font-mono font-bold transition cursor-pointer" title="Strict: 85% Stk, 75% Opt">85/85/75</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic Controls for Buildup Tab (Hidden by Default) -->
                        <div id="buildup-controls" class="hidden flex items-center gap-1 shrink-0 flex-nowrap">
                            <span id="bld-window-badge" class="text-[9px] bg-slate-800 text-sky-300 font-mono font-bold px-1.5 py-0.5 rounded border border-gray-700 whitespace-nowrap shrink-0">
                                15:20 - 15:25
                            </span>
                            <div class="flex items-center gap-0.5 bg-[#1a1e29] p-0.5 rounded text-[9px] font-bold shrink-0">
                                <button type="button" data-tf="5m" class="bld-tf-btn px-1.5 py-0.5 rounded bg-blue-600 text-white cursor-pointer transition">5M</button>
                                <button type="button" data-tf="15m" class="bld-tf-btn px-1.5 py-0.5 rounded text-gray-400 hover:text-white cursor-pointer transition">15M</button>
                                <button type="button" data-tf="30m" class="bld-tf-btn px-1.5 py-0.5 rounded text-gray-400 hover:text-white cursor-pointer transition">30M</button>
                                <button type="button" data-tf="today" class="bld-tf-btn px-1.5 py-0.5 rounded text-gray-400 hover:text-white cursor-pointer transition" title="Today">T</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ════ TAB 1 CONTENT: FLOW SYNERGY (STOCKS VS OPTIONS TIMELINE) ════ -->
                <div id="panel-flow-synergy" class="flex flex-col flex-1">
                    <!-- Sub-header Micro-Legend & Live Active Verdict (Verdict on Left for Full Visibility) -->
                    <div class="flex items-center justify-between text-[9px] py-0.5 px-0.5 text-gray-400 border-b border-gray-800/60 mb-1 flex-nowrap overflow-x-auto scrollbar-none gap-2">
                        <!-- Active Candle Institutional Confluence Verdict (Left Side, Full Space) -->
                        <div id="synergy-live-verdict" class="flex items-center gap-1 font-mono font-bold text-[9px] text-gray-300 shrink-0" title="Active Candle Institutional Confluence">
                            <span>Syncing Flow...</span>
                        </div>

                        <!-- Micro-Legend & Bar View Mode Toggle (Right Side) -->
                        <div class="flex items-center gap-2 shrink-0 text-[8.5px] ml-auto">
                            <!-- Bar View Mode Toggle: Scroll vs Fit -->
                            <div id="synergy-bar-viewmode-container" class="flex items-center bg-[#151922] p-0.5 rounded border border-gray-700/80 mr-1 text-[8px] font-bold">
                                <button type="button" id="btn-synergy-bar-scroll" onclick="setSynergyBarViewMode('scroll')" class="px-1.5 py-0.2 rounded transition cursor-pointer bg-sky-600 text-white shadow-xs" title="Horizontal Scroll Mode (Generous detailed bars)">↔ Scroll</button>
                                <button type="button" id="btn-synergy-bar-fit" onclick="setSynergyBarViewMode('fit')" class="px-1.5 py-0.2 rounded transition cursor-pointer text-gray-400 hover:text-white" title="Fit Mode (Squash all candles into current width)">↕ Fit</button>
                            </div>

                            <!-- Height Selector (Moved down from header to avoid layout overflow) -->
                            <div class="flex items-center bg-[#151922] border border-gray-700/80 rounded px-1.5 py-0.5 text-xs gap-0.5 shrink-0" title="Card Height">
                                <span class="text-gray-400 text-[8.5px] font-bold">H:</span>
                                <select id="bld-height-select" onchange="setBldChartHeight(this.value)" class="bld-height-select bg-transparent text-amber-300 font-mono font-bold focus:outline-none cursor-pointer text-[9.5px]" title="Customize Chart Height">
                                    <option value="150" class="bg-[#131722] text-white">150px</option>
                                    <option value="175" class="bg-[#131722] text-white">175px</option>
                                    <option value="195" class="bg-[#131722] text-white">195px</option>
                                    <option value="220" class="bg-[#131722] text-white">220px</option>
                                    <option value="260" class="bg-[#131722] text-white">260px</option>
                                    <option value="300" class="bg-[#131722] text-white">300px</option>
                                </select>
                            </div>

                            <span class="flex items-center gap-1 text-sky-400 font-semibold" title="Nifty 50 Stocks Weight Dominance (% green if up, % red if down)">
                                <span class="w-1.5 h-1.5 rounded-xs bg-sky-400 inline-block"></span> Stk Wt
                            </span>
                            <span id="synergy-legend-cnt" class="flex items-center gap-1 text-teal-400 font-semibold" title="Nifty 50 Stocks Count Dominance (% green count if up, % red count if down)">
                                <span class="w-1.5 h-1.5 rounded-xs bg-teal-400 inline-block"></span> Stk Cnt
                            </span>
                            <span class="flex items-center gap-1 text-purple-400 font-semibold" title="Options Flow Dominance (% BUY if up, % SELL if down)">
                                <span class="w-1.5 h-1.5 rounded-xs bg-purple-400 inline-block"></span> Opt Flow
                            </span>
                        </div>
                    </div>

                    <!-- Chart / Matrix Canvas Wrapper -->
                    <div id="synergy-chart-wrapper" class="relative w-full flex items-stretch overflow-hidden bg-[#0c1017] rounded-md border border-gray-800/80" style="height: 195px;">
                        <!-- Fixed Y-Axis Scale Rail on Left (Pinned, Never Scrolls Away) -->
                        <div id="synergy-yaxis-rail" class="w-10 shrink-0 h-full relative select-none border-r border-gray-800/80 bg-[#0c1017] z-10 block">
                            <canvas id="chart-synergy-yaxis" class="w-full h-full block"></canvas>
                        </div>

                        <!-- Horizontally Scrollable Chart Area -->
                        <div id="synergy-chart-scroll-wrapper" class="flex-1 h-full overflow-x-auto overflow-y-hidden custom-scrollbar relative select-none">
                            <div id="synergy-canvas-inner-wrapper" class="h-full relative" style="min-width: 100%;">
                                <canvas id="chart-flow-synergy" class="h-full block"></canvas>
                            </div>
                        </div>

                        <!-- Matrix / Barcode Strip (Displayed when mode is 'matrix') -->
                        <div id="synergy-matrix-container" class="hidden absolute inset-0 overflow-x-auto overflow-y-auto scrollbar-thin p-1 bg-[#0f131c] z-20"></div>
                    </div>

                    <!-- Flow Synergy Session Summary Footer -->
                    <div class="pt-1 mt-1 border-t border-gray-800 bg-[#181d2a]/80 p-1.5 rounded text-[9.5px] flex items-center justify-between select-none">
                        <div class="flex items-center gap-2 font-mono text-[9px]">
                            <span title="Candles where BOTH Stocks & Options broke the Bull Alert Threshold (≥ +75%)">
                                🔥 Bull Sync: <strong id="synergy-stat-bull" class="text-emerald-400 font-bold">0</strong>
                            </span>
                            <span title="Candles where BOTH Stocks & Options broke the Bear Alert Threshold (≤ -75%)">
                                ⚡ Bear Sync: <strong id="synergy-stat-bear" class="text-rose-400 font-bold">0</strong>
                            </span>
                            <span title="Candles with opposing directions (Trap / Divergence)">
                                ⚠️ Divergent: <strong id="synergy-stat-div" class="text-amber-400 font-bold">0</strong>
                            </span>
                        </div>
                        <span id="synergy-active-time-badge" class="font-mono text-[9px] text-sky-300 font-bold bg-slate-800 px-1.5 py-0.2 rounded border border-gray-700" title="Active Selected Candle Time">09:15</span>
                    </div>
                </div>

                <!-- ════ TAB 2 CONTENT: OI BUILDUP (HORIZONTAL STRIKE BAR CHART) ════ -->
                <div id="panel-oi-buildup" class="hidden flex flex-col flex-1">
                    <div class="flex items-center justify-between text-[9px] py-0.5 text-gray-400">
                        <span id="bld-activity-desc" class="font-semibold text-gray-300">Top 8 Activity (5M)</span>
                        <div class="flex items-center gap-1.5 text-[8.5px]">
                            <!-- Height Selector for Buildup Tab -->
                            <div class="flex items-center bg-[#151922] border border-gray-700/80 rounded px-1.5 py-0.5 text-xs gap-0.5 shrink-0 mr-1" title="Card Height">
                                <span class="text-gray-400 text-[8.5px] font-bold">H:</span>
                                <select onchange="setBldChartHeight(this.value)" class="bld-height-select bg-transparent text-amber-300 font-mono font-bold focus:outline-none cursor-pointer text-[9.5px]" title="Customize Chart Height">
                                    <option value="150" class="bg-[#131722] text-white">150px</option>
                                    <option value="175" class="bg-[#131722] text-white">175px</option>
                                    <option value="195" class="bg-[#131722] text-white">195px</option>
                                    <option value="220" class="bg-[#131722] text-white">220px</option>
                                    <option value="260" class="bg-[#131722] text-white">260px</option>
                                    <option value="300" class="bg-[#131722] text-white">300px</option>
                                </select>
                            </div>
                            <span class="text-rose-400" title="Short Buildup"><span class="w-1.5 h-1.5 rounded-xs bg-[#dc2626] inline-block"></span> SB</span>
                            <span class="text-blue-400" title="Short Covering"><span class="w-1.5 h-1.5 rounded-xs bg-[#1e3a8a] inline-block"></span> SC</span>
                            <span class="text-emerald-400" title="Long Buildup"><span class="w-1.5 h-1.5 rounded-xs bg-[#16a34a] inline-block"></span> LB</span>
                            <span class="text-yellow-400" title="Long Unwinding"><span class="w-1.5 h-1.5 rounded-xs bg-[#eab308] inline-block"></span> LU</span>
                        </div>
                    </div>

                    <div id="bld-chart-wrapper" class="relative w-full overflow-hidden" style="height: 195px;">
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

            <!-- Tab Switcher (5 Split Sections) -->
            <div class="flex items-center gap-1.5 p-1 bg-[#131722] rounded-lg border border-gray-800 text-xs font-semibold">
                <button type="button" class="alarm-tab-btn flex-1 py-1.5 px-2 rounded-md transition text-center cursor-pointer bg-blue-600 text-white font-bold" data-tab="stocks">
                    📊 Stocks
                </button>
                <button type="button" class="alarm-tab-btn flex-1 py-1.5 px-2 rounded-md transition text-center cursor-pointer text-gray-400 hover:text-white" data-tab="options">
                    ⚡ Options
                </button>
                <button type="button" class="alarm-tab-btn flex-1 py-1.5 px-2 rounded-md transition text-center cursor-pointer text-gray-400 hover:text-white" data-tab="confluence">
                    🔥 Combined
                </button>
                <button type="button" class="alarm-tab-btn flex-1 py-1.5 px-2 rounded-md transition text-center cursor-pointer text-gray-400 hover:text-white" data-tab="quant-edge">
                    🎯 Quant Edge (5)
                </button>
                <button type="button" class="alarm-tab-btn flex-1 py-1.5 px-2 rounded-md transition text-center cursor-pointer text-gray-400 hover:text-white" data-tab="order-flow">
                    🌊 OF S/R
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

                <!-- Zero-Repaint: Trigger on Candle Close Only -->
                <div class="flex items-center justify-between p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 text-xs">
                    <div>
                        <span class="font-bold text-white block">Trigger On Candle Close Only (Zero-Repaint)</span>
                        <span class="text-[10px] text-gray-400">Lock alarms only after candle officially closes (prevents in-progress erasure)</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="alarm-trigger-on-close-toggle" class="sr-only peer" checked>
                        <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                    </label>
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

                <!-- Chart 1 Alerts Visibility (STK vs OPT) -->
                <div class="p-2.5 rounded-lg bg-blue-950/20 border border-blue-900/40 space-y-2 text-xs">
                    <span class="font-bold text-blue-300 block">Top Chart (Chart 1) Signal Display:</span>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-gray-200">Show Nifty 50 Stock Alerts (📊 STK)</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="modal-c1-show-stocks-toggle" class="sr-only peer" checked>
                            <div class="w-8 h-4 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-gray-200">Show Option Flow Alerts (⚡ OPT)</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="modal-c1-show-options-toggle" class="sr-only peer">
                            <div class="w-8 h-4 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-purple-600"></div>
                        </label>
                    </div>
                </div>

                <!-- Heikin Ashi Single-Wick Entry Filter -->
                <div class="flex items-center justify-between p-2.5 rounded-lg bg-indigo-950/30 border border-indigo-700/50 text-xs">
                    <div>
                        <span class="font-bold text-emerald-300 flex items-center gap-1.5">
                            <span>🎯 Stocks Heikin Ashi Single-Wick Filter</span>
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">HIGH CONVICTION</span>
                        </span>
                        <span class="text-[10px] text-gray-300 block mt-0.5">Alert &amp; mark ONLY when candle has NO opposite wick (Flat Top for Sell, Flat Bottom for Buy). Ignore candles with wicks on both sides.</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                        <input type="checkbox" id="stocks-ha-wick-filter-toggle" class="sr-only peer" checked>
                        <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-500"></div>
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

                <!-- Chart 2 Alerts Visibility (OPT vs STK) -->
                <div class="p-2.5 rounded-lg bg-purple-950/20 border border-purple-900/40 space-y-2 text-xs">
                    <span class="font-bold text-purple-300 block">Bottom Chart (Chart 2) Signal Display:</span>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-gray-200">Show Option Flow Alerts (⚡ OPT)</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="modal-c2-show-options-toggle" class="sr-only peer" checked>
                            <div class="w-8 h-4 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-purple-600"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-gray-200">Show Nifty 50 Stock Alerts (📊 STK)</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="modal-c2-show-stocks-toggle" class="sr-only peer">
                            <div class="w-8 h-4 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>

                <!-- Options Heikin Ashi Single-Wick Entry Filter -->
                <div class="flex items-center justify-between p-2.5 rounded-lg bg-purple-950/30 border border-purple-700/50 text-xs">
                    <div>
                        <span class="font-bold text-purple-300 flex items-center gap-1.5">
                            <span>🎯 Options Heikin Ashi Single-Wick Filter</span>
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-purple-500/20 text-purple-300 border border-purple-500/30">HIGH CONVICTION</span>
                        </span>
                        <span class="text-[10px] text-gray-300 block mt-0.5">Alert &amp; mark ONLY when candle has NO opposite wick (Flat Top for Sell, Flat Bottom for Buy). Ignore candles with wicks on both sides.</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer ml-3 shrink-0">
                        <input type="checkbox" id="options-ha-wick-filter-toggle" class="sr-only peer" checked>
                        <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-purple-600"></div>
                    </label>
                </div>

                <!-- Options Strike Basket Mode Setting -->
                <div class="p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 space-y-1.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-white flex items-center gap-1">
                            <span>🎯 Strike Basket Mode:</span>
                        </span>
                        <span id="cfg-basket-mode-pill" class="text-[9px] px-1.5 py-0.2 rounded font-mono bg-cyan-950 text-cyan-300 border border-cyan-500/40">Dynamic Floating</span>
                    </div>
                    <select id="cfg-options-basket-mode" class="w-full bg-[#131722] border border-gray-700 rounded px-2.5 py-1.5 text-xs text-white focus:outline-none cursor-pointer font-medium">
                        <option value="fixed" selected>1. ⚓ Fixed Morning ATM (Locks to 9:15 AM opening strike all day - Default)</option>
                        <option value="dynamic">2. 🚀 Dynamic Floating ATM (Auto-tracks Spot ±5 strikes - Recommended for rallies)</option>
                    </select>
                    <p class="text-[10px] text-gray-400">
                        Dynamic mode shifts the 11 strikes as NIFTY moves, ensuring ITM/OTM strikes don't skew the breadth score during big trends.
                    </p>
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

                    <!-- Trade Order Sizing & SL / TP Restructure -->
                    <div class="space-y-2 pt-2 border-t border-gray-800">
                        <div class="flex items-center justify-between text-[11px] font-bold text-gray-200">
                            <span class="flex items-center gap-1.5">
                                <span>🎯 Stop Loss (SL) &amp; Profit Target (TP) Restructure</span>
                            </span>
                            <span class="text-[10px] text-emerald-400 font-mono">Candle Extreme ± Buffer</span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-[11px]">
                            <!-- Execution Mode -->
                            <div>
                                <label class="text-[10px] text-gray-400 block mb-0.5">Execution Mode</label>
                                <select id="zc-mode-select" class="w-full bg-[#131722] border border-gray-700 rounded px-1.5 py-1 text-xs text-white focus:outline-none">
                                    <option value="SIMULATION" selected>SIMULATION</option>
                                    <option value="LIVE">LIVE</option>
                                </select>
                            </div>

                            <!-- Lots -->
                            <div>
                                <label class="text-[10px] text-gray-400 block mb-0.5">Lots</label>
                                <input type="number" id="zc-lots-input" min="1" max="50" value="1" class="w-full bg-[#131722] border border-gray-700 rounded px-2 py-1 text-xs text-white text-center font-mono focus:outline-none">
                            </div>

                            <!-- SL Calculation Method -->
                            <div>
                                <label class="text-[10px] text-gray-400 block mb-0.5">SL Calculation</label>
                                <select id="zc-sl-mode-select" class="w-full bg-[#131722] border border-gray-700 rounded px-1 py-1 text-[11px] text-rose-300 focus:outline-none font-semibold">
                                    <option value="candle_hl" selected>Candle High/Low</option>
                                    <option value="fixed_offset">Fixed Points</option>
                                </select>
                            </div>

                            <!-- SL Buffer (Pts) -->
                            <div>
                                <label id="zc-sl-label" class="text-[10px] text-rose-300 block mb-0.5 font-semibold">SL Buffer (Pts)</label>
                                <input type="number" id="zc-sl-pts-input" min="0" max="300" value="15" class="w-full bg-[#131722] border border-gray-700 rounded px-2 py-1 text-xs text-rose-300 text-center font-mono focus:outline-none" title="Buffer added to Candle High (Bear) or subtracted from Candle Low (Bull)">
                            </div>

                            <!-- Target 1 (TP1) -->
                            <div class="col-span-2 sm:col-span-1">
                                <label class="text-[10px] text-emerald-300 block mb-0.5 font-semibold">Target TP1 (Pts)</label>
                                <input type="number" id="zc-tp-pts-input" min="10" max="1000" value="100" class="w-full bg-[#131722] border border-gray-700 rounded px-2 py-1 text-xs text-emerald-300 text-center font-mono focus:outline-none" title="Primary Target in points (default: 100 pts)">
                            </div>
                        </div>

                        <!-- Secondary Target TP2 & Rule Notes -->
                        <div class="flex items-center justify-between p-2 rounded bg-[#131722] border border-gray-800 text-[11px]">
                            <div class="flex items-center gap-2">
                                <span class="text-xs">🚀</span>
                                <div>
                                    <span class="font-bold text-gray-200">Secondary Runner Target (TP2):</span>
                                    <span class="text-[10px] text-gray-400 block">Optional extended target for trend runners (default: 150 pts)</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 font-mono">
                                <input type="number" id="zc-tp2-pts-input" min="20" max="1000" value="150" class="w-20 bg-[#1a1e29] border border-gray-700 rounded px-2 py-0.5 text-xs text-emerald-200 text-center focus:outline-none" title="Secondary Target in points (default: 150 pts)">
                                <span class="text-[10px] text-gray-400">pts</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ════════════ TAB 4: QUANT EDGE & ACCURACY FILTERS (5 TOGGLES) ════════════ -->
            <div id="alarm-pane-quant-edge" class="alarm-pane hidden space-y-3">
                <div class="p-2.5 rounded-lg bg-gradient-to-r from-sky-950/40 via-indigo-950/40 to-purple-950/40 border border-sky-600/40 space-y-1 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-sky-300 flex items-center gap-1.5">
                            <span>🎯 5 Quantitative Edge &amp; Accuracy Filters</span>
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-mono bg-sky-950 text-sky-300 border border-sky-500/40">RESEARCH ENHANCEMENTS</span>
                        </span>
                        <span class="text-[10px] text-gray-400">Independent Toggle Controls</span>
                    </div>
                    <p class="text-[10px] text-gray-400 leading-relaxed">
                        Toggle any of these 5 institutional statistical edge rules to filter out whipsaws, exhaustion breakouts, and low reward-to-risk trap signals.
                    </p>
                </div>

                <!-- Filter 1: Heikin-Ashi Adaptive Stop Loss -->
                <div class="p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 space-y-1 text-xs">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-bold text-emerald-400 flex items-center gap-1.5">
                                <span>1. Heikin-Ashi Adaptive Stop Loss</span>
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-mono bg-emerald-950 text-emerald-300 border border-emerald-600/40">+132 PTS EDGE</span>
                            </div>
                            <span class="text-[10px] text-gray-400 block mt-0.5">
                                Dynamically anchors stop losses to synthetic trend lows/highs to reduce premature stop outs during intraday market noise.
                            </span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="cfg-filter-ha-adaptive-sl" class="sr-only peer" checked>
                            <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                        </label>
                    </div>
                </div>

                <!-- Filter 2: Breadth Velocity Guard -->
                <div class="p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 space-y-1 text-xs">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-bold text-sky-400 flex items-center gap-1.5">
                                <span>2. Breadth Velocity (ΔBreadth ≥ 0)</span>
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-mono bg-sky-950 text-sky-300 border border-sky-600/40">MOMENTUM CONFIRM</span>
                            </div>
                            <span class="text-[10px] text-gray-400 block mt-0.5">
                                Blocks entries when breadth is decaying (e.g., dropping from 90% to 80%), requiring expansion or holding power.
                            </span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="cfg-filter-breadth-velocity" class="sr-only peer" checked>
                            <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-600"></div>
                        </label>
                    </div>
                </div>

                <!-- Filter 3: Higher Timeframe (HTF) Alignment -->
                <div class="p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 space-y-1 text-xs">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-bold text-indigo-400 flex items-center gap-1.5">
                                <span>3. Higher Timeframe Alignment (15m Supertrend / VWAP)</span>
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-mono bg-indigo-950 text-indigo-300 border border-indigo-600/40">TREND FILTER</span>
                            </div>
                            <span class="text-[10px] text-gray-400 block mt-0.5">
                                On fast intraday charts (1m/3m/5m), ensures entries align with macro 15m trend structure and VWAP side.
                            </span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="cfg-filter-htf-alignment" class="sr-only peer" checked>
                            <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                        </label>
                    </div>
                </div>

                <!-- Filter 4: Quant S/R Proximity Guard -->
                <div class="p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 space-y-1 text-xs">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-bold text-amber-400 flex items-center gap-1.5">
                                <span>4. Quant S/R Proximity Guard (Min 1.2x R:R)</span>
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-mono bg-amber-950 text-amber-300 border border-amber-600/40">RISK/REWARD</span>
                            </div>
                            <span class="text-[10px] text-gray-400 block mt-0.5">
                                Filters out BUY signals firing directly under R1/R2/R3 or SELL signals directly above S1/S2/S3.
                            </span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="cfg-filter-sr-proximity" class="sr-only peer" checked>
                            <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-600"></div>
                        </label>
                    </div>
                </div>

                <!-- Filter 5: Options OI Delta & Fresh Writing Confirmation -->
                <div class="p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 space-y-1 text-xs">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-bold text-purple-400 flex items-center gap-1.5">
                                <span>5. Options OI Fresh Writing &amp; Delta Filter</span>
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-mono bg-purple-950 text-purple-300 border border-purple-600/40">FLOW CONFIRM</span>
                            </div>
                            <span class="text-[10px] text-gray-400 block mt-0.5">
                                Cross-verifies with Trending OI that high Put/Call breadth represents fresh writing rather than institutional panic unwinding.
                            </span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="cfg-filter-oi-delta-confirmation" class="sr-only peer" checked>
                            <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-purple-600"></div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- ════════════ TAB 5: ORDER FLOW DEPTH S/R (YESTERDAY & TODAY) ════════════ -->
            <div id="alarm-pane-order-flow" class="alarm-pane hidden space-y-3">
                <div class="p-2.5 rounded-lg bg-gradient-to-r from-teal-950/40 via-cyan-950/40 to-blue-950/40 border border-teal-600/40 space-y-1 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-teal-300 flex items-center gap-1.5">
                            <span>🌊 Order Flow Depth Institutional Support &amp; Resistance</span>
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-mono bg-teal-950 text-teal-300 border border-teal-500/40">ORDER FLOW ENGINE</span>
                        </span>
                        <span class="text-[10px] text-gray-400">Microstructure Traded Volume Clustering</span>
                    </div>
                    <p class="text-[10px] text-gray-400 leading-relaxed">
                        Extracts high-conviction institutional limit order blocks from the Order Flow Depth page. Clustered volume-weighted price levels for top <strong class="text-emerald-400">BIDs (Support Walls)</strong> and top <strong class="text-rose-400">ASKs (Resistance Ceilings)</strong>.
                    </p>
                </div>

                <!-- 1. Yesterday Institutional S/R Toggle -->
                <div class="flex items-center justify-between p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800">
                    <div>
                        <div class="text-xs font-bold text-white flex items-center gap-1.5">
                            <span>1. Yesterday Institutional Anchors (Static S/R)</span>
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-amber-950 text-amber-300 border border-amber-600/40">PREV SESSION</span>
                        </div>
                        <span class="text-[10px] text-gray-400 block mt-0.5">
                            Draws Yesterday's top Bid (Support - Green) and Ask (Resistance - Red) volume clusters.
                        </span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="cfg-of-show-yesterday" class="sr-only peer" checked>
                        <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-600"></div>
                    </label>
                </div>

                <!-- 2. Today Live Order Flow S/R Toggle -->
                <div class="flex items-center justify-between p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800">
                    <div>
                        <div class="text-xs font-bold text-white flex items-center gap-1.5">
                            <span>2. Today Live Liquidity Blocks (Dynamic S/R)</span>
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-emerald-950 text-emerald-300 border border-emerald-600/40">TODAY LIVE</span>
                        </div>
                        <span class="text-[10px] text-gray-400 block mt-0.5">
                            Draws Current day live traded volume top Bid (Support - Emerald) and Ask (Resistance - Rose) clusters.
                        </span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="cfg-of-show-today" class="sr-only peer" checked>
                        <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                    </label>
                </div>

                <!-- 3. Number of S/R Levels (N / X) and Line Thickness -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-gray-200">Top N Levels (Bid &amp; Ask)</span>
                            <span class="text-[10px] text-teal-400 font-mono">1 to 5 Lines</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="number" id="cfg-of-level-count" min="1" max="5" value="1" class="w-16 px-2 py-1 bg-[#131722] border border-gray-700 text-white font-mono text-center rounded text-xs focus:border-teal-500 focus:outline-none">
                            <div class="flex items-center gap-1 flex-1">
                                <button type="button" onclick="document.getElementById('cfg-of-level-count').value=1" class="flex-1 py-1 px-1 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded text-[10px] font-mono font-bold cursor-pointer">1 (Top)</button>
                                <button type="button" onclick="document.getElementById('cfg-of-level-count').value=2" class="flex-1 py-1 px-1 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded text-[10px] font-mono font-bold cursor-pointer">2 Levels</button>
                                <button type="button" onclick="document.getElementById('cfg-of-level-count').value=3" class="flex-1 py-1 px-1 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded text-[10px] font-mono font-bold cursor-pointer">3 Levels</button>
                            </div>
                        </div>
                    </div>

                    <div class="p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-gray-200">Line Thickness</span>
                            <span class="text-[10px] text-teal-400 font-mono">1px to 4px</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="number" id="cfg-of-line-width" min="1" max="4" value="3" class="w-16 px-2 py-1 bg-[#131722] border border-gray-700 text-white font-mono text-center rounded text-xs focus:border-teal-500 focus:outline-none">
                            <div class="flex items-center gap-1 flex-1">
                                <button type="button" onclick="document.getElementById('cfg-of-line-width').value=2" class="flex-1 py-1 px-1 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded text-[10px] font-mono font-bold cursor-pointer">2px</button>
                                <button type="button" onclick="document.getElementById('cfg-of-line-width').value=3" class="flex-1 py-1 px-1 bg-teal-900 hover:bg-teal-800 text-teal-200 rounded text-[10px] font-mono font-bold cursor-pointer">3px (Def)</button>
                                <button type="button" onclick="document.getElementById('cfg-of-line-width').value=4" class="flex-1 py-1 px-1 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded text-[10px] font-mono font-bold cursor-pointer">4px</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Line Style Selection -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 space-y-1.5 text-xs">
                        <label for="cfg-of-line-style-yesterday" class="text-gray-300 font-bold block">Yesterday Line Style</label>
                        <select id="cfg-of-line-style-yesterday" class="w-full bg-[#131722] border border-gray-700 text-white text-xs rounded px-2.5 py-1.5 focus:border-teal-500 focus:outline-none">
                            <option value="solid" selected>Solid Line ──────</option>
                            <option value="dashed">Dashed Line - - - -</option>
                            <option value="dotted">Dotted Line · · · ·</option>
                        </select>
                    </div>

                    <div class="p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 space-y-1.5 text-xs">
                        <label for="cfg-of-line-style-today" class="text-gray-300 font-bold block">Today Line Style</label>
                        <select id="cfg-of-line-style-today" class="w-full bg-[#131722] border border-gray-700 text-white text-xs rounded px-2.5 py-1.5 focus:border-teal-500 focus:outline-none">
                            <option value="dashed" selected>Dashed Line - - - -</option>
                            <option value="solid">Solid Line ──────</option>
                            <option value="dotted">Dotted Line · · · ·</option>
                        </select>
                    </div>
                </div>

                <!-- Ranking Criteria (Cumulative Volume vs Peak Single Block) -->
                <div class="p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800 space-y-1.5 text-xs">
                    <div class="flex items-center justify-between">
                        <label for="cfg-of-rank-by" class="text-gray-300 font-bold block">Level Ranking Criteria</label>
                        <span class="text-[10px] text-teal-400 font-mono">Microstructure Logic</span>
                    </div>
                    <select id="cfg-of-rank-by" class="w-full bg-[#131722] border border-gray-700 text-white text-xs rounded px-2.5 py-1.5 focus:border-teal-500 focus:outline-none">
                        <option value="cumulative" selected>Cumulative Clustered Volume (Total Execution Depth)</option>
                        <option value="peak">Peak Single Order Block (Matches Order Flow Depth Top Prints, e.g. ₹25,100)</option>
                    </select>
                    <span class="text-[9.5px] text-gray-400 block leading-relaxed">
                        • <strong>Cumulative</strong>: Merges all executions at price level (e.g. ₹25,175 has 400k total volume; ₹25,100 has 340k).<br>
                        • <strong>Peak Single Order</strong>: Prioritizes levels with the single largest institutional block trade print (e.g. ₹25,100 had 27k peak print).
                    </span>
                </div>

                <!-- Price Basis Calibration (Spot vs Futures) -->
                <div class="flex items-center justify-between p-2.5 rounded-lg bg-[#1a1e29] border border-gray-800">
                    <div>
                        <div class="text-xs font-bold text-white flex items-center gap-1.5">
                            <span>🎯 Price Basis Calibration (Spot vs Futures)</span>
                            <span id="cfg-of-calib-badge" class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-teal-950 text-teal-300 border border-teal-500/40">SPOT CALIBRATED</span>
                        </div>
                        <span class="text-[10px] text-gray-400 block mt-0.5">
                            Calibrate Order Flow Future levels to Spot candles by deducting the Spot-Future basis spread.
                        </span>
                    </div>
                    <select id="cfg-of-calibration" onchange="setOfCalibration(this.value)" class="bg-[#131722] border border-gray-700 text-teal-300 font-bold font-mono text-xs rounded px-2.5 py-1.5 focus:border-teal-500 focus:outline-none cursor-pointer">
                        <option value="spot" selected>🎯 Spot-Calibrated (Aligned to Spot)</option>
                        <option value="future">🚀 Raw Future Levels (Contract Execution Prices)</option>
                        <option value="both">⚡ Spot & Fut (Both Overlaid)</option>
                    </select>
                </div>

                <!-- 5. Live Clustered Order Flow Preview Card -->
                <div class="p-2.5 rounded-lg bg-[#131722] border border-teal-900/60 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-gray-300 flex items-center gap-1">
                            <span>🔍 Active Clustered S/R Levels</span>
                            <span id="of-preview-date-badge" class="px-1.5 py-0.2 rounded text-[9px] font-mono bg-gray-800 text-gray-300">--</span>
                        </span>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="toggleHistOfDropdown('modal', this)" class="text-amber-400 hover:text-amber-300 font-bold text-[10px] cursor-pointer flex items-center gap-0.5">
                                <span>🎯</span> 5-Day Historical Levels
                            </button>
                            <button type="button" onclick="fetchOrderFlowLevels()" class="text-teal-400 hover:text-teal-300 font-bold text-[10px] cursor-pointer flex items-center gap-0.5">
                                <span>🔄</span> Refresh Levels
                            </button>
                        </div>
                    </div>
                    <div id="of-levels-preview-content" class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px] font-mono">
                        <div class="p-2 rounded bg-[#1a1e29] border border-gray-800 text-gray-400">Loading Order Flow Levels...</div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer Actions -->
            <div class="flex items-center justify-between pt-2 border-t border-gray-800 text-xs">
                <div class="flex items-center gap-2">
                    <span class="text-[11px] text-gray-400">Settings saved locally in browser</span>
                    <button type="button" id="btn-preview-bull-alarm" class="px-2.5 py-1 bg-emerald-950/80 hover:bg-emerald-900 text-emerald-300 font-bold rounded border border-emerald-600/50 text-[10px] cursor-pointer">Preview Bull Call</button>
                    <button type="button" id="btn-preview-bear-alarm" class="px-2.5 py-1 bg-rose-950/80 hover:bg-rose-900 text-rose-300 font-bold rounded border border-rose-600/50 text-[10px] cursor-pointer">Preview Bear Call</button>
                </div>
                <button type="button" id="btn-save-alarm-settings" class="px-5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded shadow transition cursor-pointer">
                    Save &amp; Apply
                </button>
            </div>
        </div>
    </div>

    <!-- ════════════ UNIFIED ORDER FLOW S/R LEVELS DROPDOWN ════════════ -->
    <div id="hist-of-levels-dropdown" class="fixed z-50 hidden bg-[#131722]/98 backdrop-blur-md border border-teal-500/60 rounded-xl shadow-2xl p-3 w-[94vw] max-w-[480px] sm:w-[480px] text-xs text-gray-200 font-sans transition-all select-none">
        <div class="flex items-center justify-between pb-2 border-b border-gray-800">
            <div class="flex items-center gap-1.5">
                <span class="text-sm">🌊</span>
                <span class="font-bold text-teal-300 text-[11px] uppercase tracking-wider">Order Flow S/R Levels</span>
                <span id="of-menu-chart-target" class="px-1.5 py-0.2 rounded text-[8.5px] font-mono bg-gray-800 text-gray-300">Chart 1</span>
            </div>
            <button type="button" onclick="closeHistOfDropdown()" class="text-gray-400 hover:text-white hover:bg-gray-800 px-1.5 py-0.5 rounded text-sm font-bold cursor-pointer transition leading-none" title="Close (ESC)">✕</button>
        </div>

        <!-- Master Visibility & Basis Controls -->
        <div class="py-2 border-b border-gray-800/80 space-y-2">
            <!-- Master Toggle -->
            <div class="flex items-center justify-between">
                <span class="text-[10px] text-gray-300 font-bold flex items-center gap-1">
                    <span>Chart Lines:</span>
                    <span id="of-menu-master-badge" class="px-1.5 py-0.2 rounded text-[8.5px] font-mono font-bold bg-emerald-950 text-emerald-400 border border-emerald-500/40">VISIBLE</span>
                </span>
                <button type="button" id="of-menu-master-btn" onclick="toggleActiveChartOfLines(event)" 
                    class="px-2 py-0.5 rounded text-[9.5px] font-bold border transition cursor-pointer bg-emerald-950/80 border-emerald-500/50 text-emerald-300 hover:bg-emerald-900">
                    <span>● Lines: ON</span>
                </button>
            </div>

            <!-- Basis Segmented Control -->
            <div class="flex items-center justify-between text-[10px]">
                <span class="text-gray-400 text-[9.5px] font-bold">Price Basis:</span>
                <div class="inline-flex rounded-md p-0.5 bg-gray-900 border border-gray-800 text-[9px] font-mono font-bold">
                    <button type="button" onclick="setOfCalibrationWithEvent('spot', event)" id="of-basis-btn-spot" class="px-2 py-0.5 rounded transition cursor-pointer text-gray-400 hover:text-white">SPOT</button>
                    <button type="button" onclick="setOfCalibrationWithEvent('future', event)" id="of-basis-btn-fut" class="px-2 py-0.5 rounded transition cursor-pointer text-gray-400 hover:text-white">FUT</button>
                    <button type="button" onclick="setOfCalibrationWithEvent('both', event)" id="of-basis-btn-both" class="px-2 py-0.5 rounded transition cursor-pointer text-gray-400 hover:text-white">SPOT & FUT</button>
                </div>
            </div>

            <!-- Quick Action Filters -->
            <div class="flex items-center justify-between pt-0.5 text-[10px]">
                <span class="text-gray-400 text-[9.5px]">Quick Filters:</span>
                <div class="flex items-center gap-1">
                    <button type="button" onclick="selectAllHistOf('all', event)" class="px-1.5 py-0.5 rounded bg-gray-800 hover:bg-gray-700 text-gray-300 font-semibold cursor-pointer">All</button>
                    <button type="button" onclick="selectAllHistOf('none', event)" class="px-1.5 py-0.5 rounded bg-gray-800 hover:bg-gray-700 text-gray-300 font-semibold cursor-pointer">Clear</button>
                    <button type="button" onclick="selectAllHistOf('res', event)" class="px-1.5 py-0.5 rounded bg-rose-950/70 hover:bg-rose-900 border border-rose-600/40 text-rose-300 font-semibold cursor-pointer">Only RES</button>
                    <button type="button" onclick="selectAllHistOf('sup', event)" class="px-1.5 py-0.5 rounded bg-emerald-950/70 hover:bg-emerald-900 border border-emerald-600/40 text-emerald-300 font-semibold cursor-pointer">Only SUP</button>
                </div>
            </div>
        </div>

        <!-- Days List Container (TODAY LIVE + PAST 5 DAYS) -->
        <div id="hist-of-dropdown-list" class="py-1.5 space-y-2 max-h-96 overflow-y-auto custom-scrollbar">
            <div class="text-gray-400 text-center py-4 text-[10.5px]">Loading Order Flow levels...</div>
        </div>

        <!-- Footer Spread & Mode Info -->
        <div class="pt-1.5 border-t border-gray-800 flex items-center justify-between text-[9.5px] text-gray-400 font-mono">
            <span id="hist-of-footer-calib">Basis: Spot Calibrated</span>
            <span id="hist-of-footer-rank">Rank: Accumulated Volume</span>
        </div>
    </div>

    <!-- ════════════ ACTIVE TRIGGERED ALARM POPUP (CONTINUOUS AUDIO LOOP) ════════════ -->
    <div id="active-alarm-overlay" class="fixed inset-0 z-50 bg-black/85 backdrop-blur-md hidden flex items-center justify-center p-3 sm:p-4">
        <div id="active-alarm-card" class="rounded-2xl p-5 sm:p-6 max-w-xl w-full text-center space-y-3.5 shadow-2xl relative transition-all duration-300 bg-[#131722] border-2 border-emerald-500 shadow-emerald-950/70">
            <!-- Top Direction & Time Bar -->
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                <div id="alarm-banner-badge" class="px-3.5 py-1 rounded-full font-black text-xs uppercase tracking-wider text-emerald-200 shadow-md bg-emerald-950/80 border border-emerald-500/60 flex items-center gap-2">
                    <span id="alarm-badge-left-icon">🟢</span>
                    <span id="alarm-badge-title">STRONG BULL CALL (LONG BIAS)</span>
                    <span id="alarm-badge-right-icon">🐂</span>
                </div>
                <div class="flex items-center gap-2">
                    <span id="alarm-candle-time-pill" class="text-[11px] font-mono text-gray-300 bg-white/5 px-2.5 py-0.5 rounded-md border border-white/10 font-bold">⏰ 10:50 Candle</span>
                    <button type="button" id="btn-close-alarm-x" class="text-gray-400 hover:text-white text-xl font-bold px-1.5 py-0 rounded transition cursor-pointer leading-none" title="Close (ESC)">&times;</button>
                </div>
            </div>

            <!-- Hero Section: Sentiment Avatar + Metric + Spot Price -->
            <div class="flex items-center justify-between gap-3 p-3.5 rounded-xl bg-black/40 border border-white/10 text-left">
                <div class="flex items-center gap-3.5">
                    <div id="alarm-sentiment-icon" class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl flex items-center justify-center text-3xl sm:text-4xl shadow-lg border-2 select-none bg-emerald-500/20 border-emerald-400 text-emerald-300 shrink-0">
                        🐂
                    </div>
                    <div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl sm:text-4xl font-black font-mono tracking-tight text-white drop-shadow-md" id="alarm-active-pct">84%</span>
                            <span class="text-xs sm:text-sm font-black uppercase tracking-wider text-emerald-400" id="alarm-active-direction">BULLISH SURGE DETECTED</span>
                        </div>
                        <div class="text-[11px] sm:text-xs text-gray-300 font-medium leading-tight pt-0.5" id="alarm-active-details">
                            42 stocks Green (92.8% weight &bull; 84% count) &bull; Threshold 80% breached!
                        </div>
                    </div>
                </div>
                <div class="text-right shrink-0 font-mono">
                    <div class="text-[10px] uppercase tracking-wider text-gray-400 font-bold">Nifty Spot</div>
                    <div class="text-base sm:text-lg font-black text-white" id="alarm-trade-price">22,962.45</div>
                    <div class="text-[10px] font-bold text-emerald-400" id="alarm-spot-pts">+42.15 pts</div>
                </div>
            </div>

            <!-- Market Alignment: Confluence vs Divergence Alert Banner -->
            <div id="alarm-alignment-card" class="p-2.5 rounded-xl border text-left text-xs transition flex items-center gap-2.5 bg-amber-950/40 border-amber-500/50 text-amber-200">
                <span id="alarm-alignment-icon" class="text-xl">⚠️</span>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span id="alarm-alignment-badge" class="px-1.5 py-0.2 rounded text-[10px] font-black tracking-wide uppercase bg-amber-500/30 text-amber-200 border border-amber-500/50">DIVERGENCE WARNING</span>
                        <span id="alarm-alignment-headline" class="text-[11px] font-bold text-white">Options Breadth opposes Stock Surge</span>
                    </div>
                    <p id="alarm-alignment-desc" class="text-[11px] text-gray-300 leading-tight pt-0.5">
                        Stocks are 92.8% Bullish, but Options show 80% Put Buying. Watch for volatility or quick scalps.
                    </p>
                </div>
            </div>

            <!-- Hidden original confluence note element for compatibility -->
            <div id="alarm-active-confluence-note" class="hidden"></div>

            <!-- Trade Recommendations: Dual Option Buying vs Option Selling Display -->
            <div class="space-y-2 text-left">
                <div class="flex items-center justify-between text-[11px] font-bold text-gray-300 px-0.5">
                    <span class="flex items-center gap-1.5">
                        <span>🎯 Trade Recommendations</span>
                        <span class="text-[10px] font-normal text-gray-400">(Choose your execution mode)</span>
                    </span>
                    <button type="button" id="btn-toggle-payload-preview" class="text-[10px] text-sky-400 hover:text-white underline cursor-pointer font-semibold">
                        View 0Click JSON
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <!-- Option Buyers Box (CE for Bull / PE for Bear) -->
                    <div id="alarm-buyer-card" class="p-3 rounded-xl bg-black/40 border border-emerald-500/30 hover:border-emerald-500/60 transition flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span id="alarm-buyer-title" class="text-[10px] font-bold uppercase tracking-wider text-emerald-300 flex items-center gap-1">
                                <span>📈</span>
                                <span>Option Buyers</span>
                            </span>
                            <span id="alarm-buyer-momentum-badge" class="px-1.5 py-0.2 rounded text-[9px] font-mono bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">MOMENTUM</span>
                        </div>
                        <div class="my-1.5 flex items-center gap-2">
                            <span class="px-1.5 py-0.5 rounded font-black text-xs bg-emerald-600 text-white shadow-sm font-mono" id="alarm-buyer-action-badge">BUY</span>
                            <span class="font-black text-sm text-white font-mono" id="alarm-buyer-strike-badge">22950 CE</span>
                            <span class="text-[10px] text-gray-400 font-mono" id="alarm-buyer-itm-label">(1-ITM)</span>
                        </div>
                        <div class="text-[10px] text-gray-400 flex items-center justify-between border-t border-white/5 pt-1">
                            <span id="alarm-buyer-strategy-note">Call Scalp / Directional</span>
                            <span id="alarm-buyer-fastmove-label" class="text-emerald-400 font-bold">Fast Move</span>
                        </div>
                    </div>

                    <!-- Option Sellers Box (PE for Bull / CE for Bear - 0Click Default) -->
                    <div id="alarm-seller-card" class="p-3 rounded-xl bg-black/40 border border-sky-500/30 hover:border-sky-500/60 transition flex flex-col justify-between">
                        <div class="flex items-center justify-between gap-1">
                            <span id="alarm-seller-title" class="text-[10px] font-bold uppercase tracking-wider text-sky-300 flex items-center gap-1 whitespace-nowrap shrink-0">
                                <span>📉</span>
                                <span class="whitespace-nowrap">Option Sellers</span>
                            </span>
                            <span id="alarm-zeroclick-status-pill" class="px-1.5 py-0.2 rounded text-[9px] font-mono bg-sky-500/20 text-sky-200 border border-sky-400/40 whitespace-nowrap">0CLICK DEFAULT</span>
                        </div>
                        <div class="my-1.5 flex items-center gap-2">
                            <span id="alarm-trade-action-badge" class="px-1.5 py-0.5 rounded font-black text-xs bg-amber-500 text-black shadow-sm font-mono">SELL</span>
                            <span id="alarm-trade-strike-badge" class="font-black text-sm text-white font-mono">23000 PE</span>
                            <span class="text-[10px] text-gray-400 font-mono" id="alarm-seller-itm-label">(1-ITM)</span>
                        </div>
                        <div class="text-[10px] text-gray-400 flex items-center justify-between border-t border-white/5 pt-1">
                            <span id="alarm-seller-strategy-note">Theta Premium Decay</span>
                            <span class="text-sky-300 font-bold">High Probability</span>
                        </div>
                    </div>
                </div>

                <!-- Levels Strip (SL / TP1 / TP2 / Risk-Reward / Lots) -->
                <div id="alarm-levels-strip" class="p-2.5 rounded-xl bg-black/50 border border-white/10 font-mono text-[11px] flex flex-wrap items-center justify-between gap-y-1.5 gap-x-2 text-white">
                    <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                        <span class="flex items-center gap-1">
                            <span class="text-rose-400 font-bold">🛑 SL:</span>
                            <strong id="alarm-trade-sl" class="text-rose-300 font-bold">22,862.45</strong>
                            <span id="alarm-trade-sl-note" class="text-[9px] text-gray-400 font-sans bg-white/5 px-1 py-0.2 rounded border border-white/10">(Low -15)</span>
                        </span>
                        <span class="text-white/30">&bull;</span>
                        <span class="flex items-center gap-1">
                            <span id="alarm-trade-tp1-label" class="text-emerald-400 font-bold">🎯 TP1:</span>
                            <strong id="alarm-trade-tp" class="text-emerald-300 font-bold">23,062.45</strong>
                            <span id="alarm-trade-tp1-pts" class="text-[9px] text-emerald-400 font-sans">(100 pts)</span>
                        </span>
                        <span class="text-white/30">&bull;</span>
                        <span class="flex items-center gap-1">
                            <span id="alarm-trade-tp2-label" class="text-teal-300 font-bold">🚀 TP2:</span>
                            <strong id="alarm-trade-tp2" class="text-teal-200 font-bold">23,112.45</strong>
                            <span id="alarm-trade-tp2-pts" class="text-[9px] text-teal-300 font-sans">(150 pts)</span>
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span id="alarm-trade-rr-badge" class="px-1.5 py-0.2 rounded text-[9px] font-mono bg-purple-500/20 text-purple-300 border border-purple-500/40" title="Risk-to-Reward Ratio">1:2.5 R:R</span>
                        <span id="alarm-trade-lots-badge" class="text-gray-300 font-bold text-[10px] bg-white/10 px-2 py-0.5 rounded border border-white/10">1 Lot (SIMULATION)</span>
                    </div>
                </div>

                <!-- 0Click Payload Inspector Box -->
                <div id="alarm-trade-payload-box" class="hidden pt-1 border-t border-white/10 font-mono text-[10px] text-white/90 overflow-x-auto max-h-24 bg-black/80 p-2.5 rounded-lg border border-white/10">
                    <pre id="alarm-trade-payload-code">{}</pre>
                </div>
            </div>

            <!-- Action Buttons: Send to 0Click & Stop Alarm -->
            <div class="pt-1.5 flex flex-wrap items-center justify-center gap-3">
                <button type="button" id="btn-dispatch-active-alarm" class="px-6 py-2.5 bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-500 hover:to-blue-500 text-white font-bold text-xs sm:text-sm rounded-xl shadow-lg border border-sky-400/50 transition inline-flex items-center gap-2 cursor-pointer active:scale-95">
                    <span>🚀</span>
                    <span>Send to 0click.in</span>
                </button>
                <button type="button" id="btn-stop-active-alarm" class="px-6 py-2.5 bg-gray-900/90 hover:bg-black text-white font-bold text-xs sm:text-sm rounded-xl border border-white/30 hover:border-white/60 shadow-xl transition inline-flex items-center gap-2 cursor-pointer active:scale-95">
                    <span class="text-base">🔕</span>
                    <span>Stop Alarm</span>
                    <span class="text-[10px] text-gray-300 font-mono bg-white/15 px-1.5 py-0.5 rounded border border-white/20">ESC</span>
                </button>
            </div>

            <p class="text-[10px] text-gray-400 font-medium">
                Audio alert loops continuously until dismissed or ESC is pressed
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

    <!-- Fixed Dropdown Menu for Day AI Data (Positioned dynamically via getBoundingClientRect, immune to overflow) -->
    <div id="day-ai-menu" class="hidden fixed z-[999999] bg-[#131722] border border-purple-500/70 rounded-md shadow-2xl py-1 px-1 text-[11px] font-mono min-w-[220px] backdrop-blur-md">
        <button type="button" onclick="downloadWholeDayAiData('md')" class="w-full text-left px-2.5 py-1.5 hover:bg-purple-950/70 text-purple-200 rounded flex items-center gap-2 cursor-pointer transition">
            <span>📄</span> <span>Download Markdown (.md)</span>
        </button>
        <button type="button" onclick="downloadWholeDayAiData('json')" class="w-full text-left px-2.5 py-1.5 hover:bg-purple-950/70 text-purple-200 rounded flex items-center gap-2 cursor-pointer transition">
            <span>📦</span> <span>Download Full JSON (.json)</span>
        </button>
        <button type="button" onclick="copyDayAiDataToClipboard()" class="w-full text-left px-2.5 py-1.5 hover:bg-purple-950/70 text-purple-200 rounded flex items-center gap-2 cursor-pointer transition border-t border-gray-800 mt-1 pt-1">
            <span>📋</span> <span>Copy Full MD to Clipboard</span>
        </button>
        <button type="button" onclick="openDayAiDiagnosisModal()" class="w-full text-left px-2.5 py-1.5 hover:bg-indigo-950/70 text-indigo-200 rounded flex items-center gap-2 cursor-pointer transition border-t border-gray-800 mt-1 pt-1 font-bold">
            <span>👁️</span> <span>Preview Market Autopsy</span>
        </button>
    </div>

    <!-- On-Screen Market Autopsy & Diagnosis Modal -->
    <div id="day-ai-modal" class="hidden fixed inset-0 z-[999999] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
        <div class="bg-[#131722] border border-purple-500/60 rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col overflow-hidden text-gray-200 font-sans">
            <!-- Header -->
            <div class="px-4 py-3 bg-[#1a1e29] border-b border-gray-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-xl">📊</span>
                    <div>
                        <h3 class="font-bold text-sm text-purple-300">NIFTY 50 Whole Day Market Autopsy & AI Data</h3>
                        <p class="text-[10px] text-gray-400 font-mono" id="day-ai-modal-date-subtitle">Session Diagnosis</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="copyDayAiDataToClipboard()" class="px-2.5 py-1 bg-purple-700 hover:bg-purple-600 text-white rounded text-xs font-mono font-bold flex items-center gap-1 cursor-pointer">
                        <span>📋</span> <span>Copy MD</span>
                    </button>
                    <button type="button" onclick="downloadWholeDayAiData('md')" class="px-2.5 py-1 bg-indigo-700 hover:bg-indigo-600 text-white rounded text-xs font-mono font-bold flex items-center gap-1 cursor-pointer">
                        <span>📥</span> <span>Download .md</span>
                    </button>
                    <button type="button" onclick="downloadWholeDayAiData('json')" class="px-2.5 py-1 bg-emerald-700 hover:bg-emerald-600 text-white rounded text-xs font-mono font-bold flex items-center gap-1 cursor-pointer">
                        <span>📦</span> <span>JSON</span>
                    </button>
                    <button type="button" onclick="closeDayAiDiagnosisModal()" class="p-1 text-gray-400 hover:text-white rounded cursor-pointer text-base leading-none">
                        &times;
                    </button>
                </div>
            </div>
            <!-- Body -->
            <div class="p-4 overflow-y-auto space-y-4 font-mono text-xs select-text bg-[#0b0e14]" id="day-ai-modal-content">
                <div class="text-center py-8 text-gray-400">Loading comprehensive session data...</div>
            </div>
        </div>
    </div>

</div>

<script>
// Standalone Bulletproof Day AI Data Controller (Global Scope)
(function() {
    function getActiveDate() {
        const dateInput = document.getElementById('date-select');
        if (dateInput && dateInput.value && dateInput.value.trim() !== '') {
            return dateInput.value.trim();
        }
        return window.selectedTradingDate || '{{ $selectedDate }}';
    }

    function getActiveExpiry() {
        const expirySelect = document.getElementById('expiry-select');
        if (expirySelect && expirySelect.value && expirySelect.value.trim() !== '') {
            return expirySelect.value.trim();
        }
        return '';
    }

    function escapeHtml(str) {
        return (str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showDayAiToast(msg) {
        let toast = document.getElementById('day-ai-toast-notification');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'day-ai-toast-notification';
            toast.className = 'fixed bottom-5 right-5 z-[9999999] flex items-center gap-2.5 px-4 py-2.5 bg-[#0e131f]/95 border border-purple-500/70 rounded-lg shadow-2xl text-xs font-mono text-gray-200 backdrop-blur-md transition-all duration-300 transform translate-y-10 opacity-0';
            document.body.appendChild(toast);
        }

        toast.innerHTML = `
            <span class="text-lg">🤖</span>
            <div class="flex flex-col">
                <span class="font-bold text-purple-400">Day AI Diagnosis</span>
                <span class="text-[10px] text-gray-300">${escapeHtml(msg)}</span>
            </div>
            <button type="button" onclick="this.parentElement.classList.add('opacity-0', 'translate-y-10')" class="ml-2 text-gray-400 hover:text-white cursor-pointer">&times;</button>
        `;

        requestAnimationFrame(() => {
            toast.classList.remove('opacity-0', 'translate-y-10');
            toast.classList.add('opacity-100', 'translate-y-0');
        });

        setTimeout(() => {
            if (toast) {
                toast.classList.remove('opacity-100', 'translate-y-0');
                toast.classList.add('opacity-0', 'translate-y-10');
            }
        }, 5000);
    }
    window.showDayAiToast = showDayAiToast;

    window.toggleDayAiMenu = function(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        const menu = document.getElementById('day-ai-menu');
        const btn = document.getElementById('btn-day-ai-dropdown') || document.getElementById('btn-day-ai-download');
        if (!menu || !btn) return;

        if (menu.classList.contains('hidden')) {
            const rect = btn.getBoundingClientRect();
            menu.style.top = (rect.bottom + 6) + 'px';
            menu.style.left = Math.max(10, (rect.right - 220)) + 'px';
            menu.classList.remove('hidden');
        } else {
            menu.classList.add('hidden');
        }
    };

    document.addEventListener('click', function(e) {
        const menu = document.getElementById('day-ai-menu');
        const btnDropdown = document.getElementById('btn-day-ai-dropdown');
        if (menu && !menu.classList.contains('hidden')) {
            if (!menu.contains(e.target) && e.target !== btnDropdown && (!btnDropdown || !btnDropdown.contains(e.target))) {
                menu.classList.add('hidden');
            }
        }
    });

    window.downloadWholeDayAiData = function(format = 'md') {
        const menu = document.getElementById('day-ai-menu');
        if (menu) menu.classList.add('hidden');

        const activeDate = getActiveDate();
        const activeExpiry = getActiveExpiry();

        const btn = document.getElementById('btn-day-ai-download');
        const btnIcon = document.getElementById('btn-day-ai-icon');
        const btnText = document.getElementById('btn-day-ai-text');

        if (btnText) btnText.textContent = 'Downloading...';
        if (btnIcon) btnIcon.textContent = '⏳';

        const url = `/api/multi-timeframe/day-ai-diagnosis?date=${encodeURIComponent(activeDate)}&expiry=${encodeURIComponent(activeExpiry)}&format=${format}&download=1`;
        
        window.location.href = url;

        setTimeout(() => {
            if (btnText) btnText.textContent = 'Day AI Data';
            if (btnIcon) btnIcon.textContent = '📥';
            showDayAiToast(`Downloading ${format.toUpperCase()} day autopsy data for ${activeDate}...`);
        }, 1200);
    };

    window.copyDayAiDataToClipboard = async function() {
        const menu = document.getElementById('day-ai-menu');
        if (menu) menu.classList.add('hidden');

        const activeDate = getActiveDate();
        const activeExpiry = getActiveExpiry();

        const btn = document.getElementById('btn-day-ai-download');
        const btnIcon = document.getElementById('btn-day-ai-icon');
        const btnText = document.getElementById('btn-day-ai-text');

        if (btnText) btnText.textContent = 'Fetching...';
        if (btnIcon) btnIcon.textContent = '⏳';

        try {
            const res = await fetch(`/api/multi-timeframe/day-ai-diagnosis?date=${encodeURIComponent(activeDate)}&expiry=${encodeURIComponent(activeExpiry)}&format=md&download=0`);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const md = await res.text();

            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(md);
            } else {
                const ta = document.createElement('textarea');
                ta.value = md;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            }

            if (btnText) btnText.textContent = 'Copied MD ✓';
            if (btnIcon) btnIcon.textContent = '📋';
            showDayAiToast('Whole Day Markdown copied to clipboard! Paste directly into ChatGPT, Claude, or Gemini.');
        } catch (err) {
            console.error('Failed to copy MD:', err);
            showDayAiToast('Failed to copy. Try clicking Download instead.');
        } finally {
            setTimeout(() => {
                if (btnText) btnText.textContent = 'Day AI Data';
                if (btnIcon) btnIcon.textContent = '📥';
            }, 2000);
        }
    };

    window.openDayAiDiagnosisModal = async function() {
        const menu = document.getElementById('day-ai-menu');
        if (menu) menu.classList.add('hidden');

        const modal = document.getElementById('day-ai-modal');
        const modalContent = document.getElementById('day-ai-modal-content');
        const dateSubtitle = document.getElementById('day-ai-modal-date-subtitle');
        if (!modal) return;

        modal.classList.remove('hidden');

        const activeDate = getActiveDate();
        const activeExpiry = getActiveExpiry();

        if (dateSubtitle) {
            dateSubtitle.textContent = `Date: ${activeDate} | Expiry: ${activeExpiry || 'Auto-Detected'}`;
        }

        if (modalContent) {
            modalContent.innerHTML = '<div class="text-center py-12 text-purple-300 font-mono"><span class="inline-block animate-spin text-2xl mr-2">⏳</span> Loading Market Autopsy & Data Engine...</div>';
        }

        try {
            const res = await fetch(`/api/multi-timeframe/day-ai-diagnosis?date=${encodeURIComponent(activeDate)}&expiry=${encodeURIComponent(activeExpiry)}&format=md&download=0`);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const md = await res.text();
            
            if (modalContent) {
                modalContent.innerHTML = `<pre class="whitespace-pre-wrap font-mono text-[11.5px] leading-relaxed text-gray-200 select-all p-2 bg-[#080b11] rounded border border-gray-800">${escapeHtml(md)}</pre>`;
            }
        } catch (err) {
            if (modalContent) {
                modalContent.innerHTML = `<div class="p-4 bg-rose-950/50 border border-rose-500/50 rounded text-rose-300">Error loading diagnosis data: ${escapeHtml(err.message)}</div>`;
            }
        }
    };

    window.closeDayAiDiagnosisModal = function() {
        const modal = document.getElementById('day-ai-modal');
        if (modal) modal.classList.add('hidden');
    };
})();
</script>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- HIGH-PROBABILITY TRADE ENGINE & DYNAMIC MULTI-FACTOR EXIT COCKPIT (v2)      -->
<!-- Docked at bottom by default; Undockable into draggable & resizable modal    -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="trade-cockpit-drawer" class="fixed bottom-0 left-0 right-0 z-[60] bg-[#0b0f19]/95 backdrop-blur-md border-t border-sky-500/40 shadow-2xl transition-all duration-200 flex flex-col font-sans">
    <!-- Top Drag-to-Resize Bar (Docked Mode) -->
    <div id="trade-drawer-resize-bar" class="w-full h-2 bg-gradient-to-r from-transparent via-sky-500/40 to-transparent hover:via-sky-400 active:via-sky-300 cursor-ns-resize transition flex items-center justify-center select-none group" title="Click & Drag vertically to resize Trade Cockpit Drawer Height (Saved automatically)">
        <div class="w-16 h-1 bg-sky-400/60 group-hover:bg-white rounded-full transition-all"></div>
    </div>

    <!-- Header Handle / Toolbar -->
    <div id="trade-drawer-header" class="flex items-center justify-between px-3 py-1.5 bg-[#101626] border-b border-gray-800 select-none cursor-default">
        <!-- Left: Status & Trade Pill -->
        <div class="flex items-center gap-2 shrink-0">
            <span id="trade-live-dot" class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span class="text-xs font-black tracking-wider text-sky-300 flex items-center gap-1">
                <span>🎯 TRADE COCKPIT</span>
                <span class="text-[9px] px-1 py-0.2 bg-sky-950 text-sky-400 rounded border border-sky-800">v2 AUTO</span>
            </span>
            <div id="trade-header-pill" class="hidden sm:flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-900 border border-slate-700 text-slate-300">
                <span id="trade-header-pill-text">STANDBY: Scanning 80%+ Breadth Signals...</span>
            </div>
        </div>

        <!-- Center: 3-Target Progress Stepper -->
        <div id="trade-targets-header-stepper" class="hidden md:flex items-center gap-2 text-[10px] font-mono">
            <span class="text-gray-400">TARGETS:</span>
            <span id="step-t1" class="px-1.5 py-0.2 rounded bg-slate-800 text-gray-400 border border-slate-700">T1: --</span>
            <span class="text-gray-600">&rarr;</span>
            <span id="step-t2" class="px-1.5 py-0.2 rounded bg-slate-800 text-gray-400 border border-slate-700">T2: --</span>
            <span class="text-gray-600">&rarr;</span>
            <span id="step-t3" class="px-1.5 py-0.2 rounded bg-slate-800 text-gray-400 border border-slate-700">T3: --</span>
            <span class="text-gray-600">|</span>
            <span id="step-sl" class="px-1.5 py-0.2 rounded bg-rose-950/60 text-rose-300 border border-rose-800/60">SL: --</span>
        </div>

        <!-- Right: Actions (Height Presets, Pop-out, History, Settings, Minimize, Close) -->
        <div class="flex items-center gap-1.5 shrink-0 text-xs">
            <!-- Height Presets Dropdown -->
            <div class="flex items-center bg-[#151c2e] border border-gray-700 hover:border-sky-500/60 rounded px-1.5 py-0.5 text-[9.5px] font-mono gap-1 transition" title="Adjust Drawer Height (Persisted in Local Storage)">
                <span class="text-sky-400 font-bold">↕ H:</span>
                <select id="trade-drawer-height-select" onchange="setTradeDrawerHeight(this.value)" class="bg-[#101626] text-sky-200 font-bold focus:outline-none cursor-pointer text-[10px] rounded px-0.5 border border-gray-700/60">
                    <option value="280" class="bg-[#101626] text-white">280px</option>
                    <option value="340" class="bg-[#101626] text-white">340px</option>
                    <option value="400" class="bg-[#101626] text-white">400px</option>
                    <option value="440" selected class="bg-[#101626] text-white">440px</option>
                    <option value="500" class="bg-[#101626] text-white">500px</option>
                    <option value="580" class="bg-[#101626] text-white">580px</option>
                    <option value="660" class="bg-[#101626] text-white">660px</option>
                    <option value="760" class="bg-[#101626] text-white">760px</option>
                    <option value="850" class="bg-[#101626] text-white">850px</option>
                    <option value="custom" class="bg-[#101626] text-sky-300">Custom</option>
                </select>
            </div>

            <!-- Show All vs Active Only Toggle -->
            <button id="btn-toggle-trade-history-mode" type="button" onclick="toggleTradeHistoryMode()" title="Toggle: Show Recent Active Trade Only vs Show All Trades of the Day" class="px-1.5 py-0.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-[9.5px] font-semibold border border-slate-700 flex items-center gap-1 cursor-pointer transition">
                <span id="trade-history-mode-icon">🔄</span>
                <span id="trade-history-mode-text">Active Only</span>
            </button>

            <!-- Settings Button -->
            <button id="btn-toggle-trade-settings" type="button" onclick="toggleTradeSettingsPanel()" title="Configure Strategy (Auto / Sell / Buy) & Exit Factors" class="px-1.5 py-0.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-[9.5px] font-semibold border border-slate-700 flex items-center gap-1 cursor-pointer transition">
                <span>⚙️</span>
                <span class="hidden sm:inline">Settings</span>
            </button>

            <!-- Pop Out / Undock Button -->
            <button id="btn-trade-undock" type="button" onclick="toggleTradeDrawerUndock()" title="Undock into Floating Draggable & Resizable Window / Dock Back to Bottom" class="px-1.5 py-0.5 bg-sky-950 hover:bg-sky-900 text-sky-300 rounded text-[9.5px] font-bold border border-sky-700/60 flex items-center gap-1 cursor-pointer transition">
                <span id="trade-undock-icon">⧉</span>
                <span id="trade-undock-text">Pop Out</span>
            </button>

            <!-- Expand / Collapse Button -->
            <button id="btn-trade-expand" type="button" onclick="toggleTradeDrawerExpand()" title="Expand / Minimize Cockpit Drawer" class="px-1.5 py-0.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded text-[9.5px] font-bold border border-slate-700 flex items-center gap-1 cursor-pointer transition">
                <span id="trade-expand-icon">▲</span>
                <span id="trade-expand-text">Expand</span>
            </button>

            <!-- Close Button -->
            <button type="button" onclick="hideTradeDrawer()" title="Hide Trade Cockpit Drawer" class="p-0.5 text-gray-400 hover:text-white rounded hover:bg-slate-800 transition cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    <!-- Quick Settings Dropdown Overlay -->
    <div id="trade-settings-panel" class="hidden bg-[#0e1424] border-b border-gray-700 p-2 text-xs">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-2.5 max-w-6xl mx-auto">
            <!-- Strategy Selection -->
            <div class="bg-[#141b2d] p-2 rounded border border-gray-800">
                <div class="font-bold text-sky-400 mb-1 flex items-center gap-1">
                    <span>⚡ Strategy Preference:</span>
                </div>
                <select id="trade-cfg-strategy-mode" onchange="updateTradeConfig('strategyMode', this.value)" class="w-full bg-[#0a0e18] border border-gray-700 rounded px-2 py-1 text-xs text-white focus:outline-none">
                    <option value="auto" selected>Auto (Strike only ➔ Option Sell | Confluence ➔ Option Buy)</option>
                    <option value="force_sell">Always Option Selling (99% Sell OTM/ATM PE/CE)</option>
                    <option value="force_buy">Always Option Buying (Buy ATM/OTM CE/PE)</option>
                </select>
                <div class="text-[10px] text-gray-400 mt-1">Recommended: Auto ensures highest statistical edge.</div>
            </div>

            <!-- Lots & Sizing Setup -->
            <div class="bg-[#141b2d] p-2 rounded border border-gray-800">
                <div class="font-bold text-amber-400 mb-1 flex items-center justify-between">
                    <span class="flex items-center gap-1">📦 Lots &amp; Quantity:</span>
                    <span id="cfg-total-qty-badge" class="text-[9.5px] font-mono px-1 py-0.2 bg-amber-950 text-amber-300 rounded border border-amber-700/60 font-bold">195 QTY</span>
                </div>
                <div class="grid grid-cols-2 gap-1.5 text-[11px] font-mono">
                    <div>
                        <span class="text-gray-400 text-[9.5px]">Entry Lots:</span>
                        <select id="trade-cfg-lots" onchange="updateTradeConfig('entryLots', parseInt(this.value, 10))" class="w-full bg-[#0a0e18] border border-gray-700 rounded px-1.5 py-0.5 text-white text-xs">
                            <option value="3" selected>3 Lots (T1, T2, T3)</option>
                            <option value="2">2 Lots (T1, T2)</option>
                            <option value="1">1 Lot (Trail)</option>
                        </select>
                    </div>
                    <div>
                        <span class="text-gray-400 text-[9.5px]">Lot Size (Qty):</span>
                        <input id="trade-cfg-lot-size" type="number" min="1" value="65" onchange="updateTradeConfig('lotSize', parseInt(this.value, 10))" class="w-full bg-[#0a0e18] border border-gray-700 rounded px-1.5 py-0.5 text-center text-white text-xs">
                    </div>
                </div>
                <div class="text-[9.5px] text-gray-400 mt-1">Nifty Lot Size is 65. 3 Lots: 1 lot booked at T1, 1 at T2, 1 at T3.</div>
            </div>

            <!-- Target R:R Multipliers -->
            <div class="bg-[#141b2d] p-2 rounded border border-gray-800">
                <div class="font-bold text-emerald-400 mb-1 flex items-center gap-1">
                    <span>🎯 Multi-Target Partial Exits:</span>
                </div>
                <div class="grid grid-cols-3 gap-1 text-[11px] font-mono">
                    <div>
                        <span class="text-gray-400 text-[9.5px]">T1:</span>
                        <input id="trade-cfg-t1-rr" type="number" step="0.1" value="1.2" onchange="updateTradeConfig('t1Rr', parseFloat(this.value))" class="w-full bg-[#0a0e18] border border-gray-700 rounded px-1 py-0.5 text-center text-white">
                    </div>
                    <div>
                        <span class="text-gray-400 text-[9.5px]">T2:</span>
                        <input id="trade-cfg-t2-rr" type="number" step="0.1" value="2.2" onchange="updateTradeConfig('t2Rr', parseFloat(this.value))" class="w-full bg-[#0a0e18] border border-gray-700 rounded px-1 py-0.5 text-center text-white">
                    </div>
                    <div>
                        <span class="text-gray-400 text-[9.5px]">T3:</span>
                        <input id="trade-cfg-t3-rr" type="number" step="0.1" value="3.2" onchange="updateTradeConfig('t3Rr', parseFloat(this.value))" class="w-full bg-[#0a0e18] border border-gray-700 rounded px-1 py-0.5 text-center text-white">
                    </div>
                </div>
                <div class="text-[10px] text-gray-400 mt-1">T1 trails SL to Cost; T2 trails SL to T1.</div>
            </div>

            <!-- Exit Factors Toggles -->
            <div class="bg-[#141b2d] p-2 rounded border border-gray-800">
                <div class="font-bold text-purple-400 mb-1 flex items-center gap-1">
                    <span>🛡️ Active Exit Factor Checks:</span>
                </div>
                <div class="grid grid-cols-2 gap-1 text-[10.5px]">
                    <label class="flex items-center gap-1 cursor-pointer">
                        <input type="checkbox" id="cfg-factor-sl" checked onchange="updateTradeConfig('f1_sl', this.checked)" class="accent-sky-500">
                        <span>F1: Signal Candle SL</span>
                    </label>
                    <label class="flex items-center gap-1 cursor-pointer">
                        <input type="checkbox" id="cfg-factor-yd-sr" checked onchange="updateTradeConfig('f2_yd_sr', this.checked)" class="accent-sky-500">
                        <span>F2: Yesterday S/R</span>
                    </label>
                    <label class="flex items-center gap-1 cursor-pointer">
                        <input type="checkbox" id="cfg-factor-td-sr" checked onchange="updateTradeConfig('f3_td_sr', this.checked)" class="accent-sky-500">
                        <span>F3: Today S/R &amp; H/L</span>
                    </label>
                    <label class="flex items-center gap-1 cursor-pointer">
                        <input type="checkbox" id="cfg-factor-reverse" checked onchange="updateTradeConfig('f4_reverse', this.checked)" class="accent-sky-500">
                        <span>F4: Reverse Cross</span>
                    </label>
                    <label class="flex items-center gap-1 cursor-pointer">
                        <input type="checkbox" id="cfg-factor-depth" checked onchange="updateTradeConfig('f5_depth', this.checked)" class="accent-sky-500">
                        <span>F5: 30-Depth Flow</span>
                    </label>
                    <label class="flex items-center gap-1 cursor-pointer">
                        <input type="checkbox" id="cfg-factor-greeks" checked onchange="updateTradeConfig('f6_greeks', this.checked)" class="accent-sky-500">
                        <span>F6: Theta &amp; Greeks</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- Drawer Body (Collapsible / Expandable with Customizable Height) -->
    <div id="trade-drawer-body" class="p-2 overflow-y-auto bg-[#090d16] flex flex-col gap-2" style="max-height: 440px;">
        <!-- Main Top Row: Active Trade Card & Exit Matrix -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-2">
            <!-- Left Panel (5 cols): Active Trade & M2M P&L Cockpit -->
            <div class="lg:col-span-5 bg-[#0f1422] border border-gray-800 rounded-lg p-2.5 flex flex-col justify-between shadow-inner">
                <div id="active-trade-card">
                    <!-- Trade Action & Strategy Header -->
                    <div class="flex items-center justify-between pb-1.5 border-b border-gray-800">
                        <div class="flex items-center gap-1.5">
                            <span id="at-action-badge" class="px-2 py-0.5 rounded text-[11px] font-black tracking-wide bg-emerald-950 text-emerald-300 border border-emerald-600/60">
                                🟢 BUY CALL
                            </span>
                            <span id="at-instrument" class="font-mono font-bold text-xs text-white">NIFTY 24200 CE</span>
                        </div>
                        <span id="at-status-badge" class="px-1.5 py-0.2 rounded text-[9.5px] font-mono font-bold bg-sky-950 text-sky-300 border border-sky-800">
                            ACTIVE
                        </span>
                    </div>

                    <!-- Entry & Spot Snapshot Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 my-2 text-[10.5px] font-mono">
                        <div class="bg-[#151c2e] p-1.5 rounded border border-gray-800">
                            <span class="text-gray-400 block text-[9px]">ENTRY TIME</span>
                            <span id="at-entry-time" class="font-bold text-sky-300">09:45</span>
                        </div>
                        <div class="bg-[#151c2e] p-1.5 rounded border border-gray-800">
                            <span class="text-gray-400 block text-[9px]">ENTRY SPOT</span>
                            <span id="at-entry-spot" class="font-bold text-white">24,185.00</span>
                        </div>
                        <div class="bg-[#151c2e] p-1.5 rounded border border-gray-800">
                            <span class="text-gray-400 block text-[9px]">OPT ENTRY</span>
                            <span id="at-entry-opt" class="font-bold text-amber-300">₹112.50</span>
                        </div>
                        <div class="bg-[#151c2e] p-1.5 rounded border border-gray-800">
                            <span class="text-gray-400 block text-[9px]">OPT CURRENT</span>
                            <span id="at-curr-opt" class="font-bold text-emerald-400">₹148.00</span>
                        </div>
                    </div>

                    <!-- Live M2M P&L Big Display -->
                    <div class="bg-gradient-to-r from-[#12192c] to-[#18233c] p-2.5 rounded-lg border border-sky-500/30 flex items-center justify-between mb-2 shadow-xs">
                        <div>
                            <span id="at-lots-summary" class="text-[10px] text-gray-400 block font-semibold tracking-wider">NET P&amp;L (3 LOTS / 195 QTY @ 65/LOT)</span>
                            <div class="flex items-baseline gap-2">
                                <span id="at-pnl-rupees" class="text-xl font-black text-emerald-400 font-mono">+₹0.00</span>
                                <span id="at-pnl-points" class="text-xs font-bold text-emerald-300 font-mono">(+0.0 pts)</span>
                            </div>
                            <div class="text-[9.5px] font-mono text-gray-400 mt-0.5 flex items-center gap-2">
                                <span>Booked: <span id="at-pnl-booked" class="text-emerald-300 font-bold">₹0</span> (<span id="at-lots-booked">0</span> Lots)</span>
                                <span>&bull;</span>
                                <span>Open: <span id="at-pnl-open" class="text-sky-300 font-bold">₹0</span> (<span id="at-lots-open">3</span> Lots)</span>
                            </div>
                        </div>
                        <div class="text-right text-[10px] font-mono space-y-0.5">
                            <div><span class="text-gray-400">MFE (Peak):</span> <span id="at-mfe" class="text-emerald-300 font-bold">+0.0 pts</span></div>
                            <div><span class="text-gray-400">MAE (Drawdown):</span> <span id="at-mae" class="text-rose-300 font-bold">-0.0 pts</span></div>
                            <div><span class="text-gray-400">Current Spot:</span> <span id="at-curr-spot" class="text-cyan-300 font-bold">--</span></div>
                        </div>
                    </div>

                    <!-- 3 Targets Breakdown & Trailing SL -->
                    <div class="bg-[#141b2c] p-2 rounded border border-gray-800 text-[10.5px] font-mono space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-400">Stop Loss:</span>
                            <div class="flex items-center gap-1.5">
                                <span id="at-sl-val" class="font-bold text-rose-400">24,142.50</span>
                                <span id="at-sl-badge" class="px-1 py-0.2 rounded text-[8.5px] bg-rose-950 text-rose-300">Base SL</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-400">Target 1:</span>
                            <div class="flex items-center gap-1.5">
                                <span id="at-t1-val" class="font-bold text-white">--</span>
                                <span id="at-t1-badge" class="px-1 py-0.2 rounded text-[8.5px] bg-slate-800 text-gray-400">Book 1 Lot</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-400">Target 2:</span>
                            <div class="flex items-center gap-1.5">
                                <span id="at-t2-val" class="font-bold text-white">--</span>
                                <span id="at-t2-badge" class="px-1 py-0.2 rounded text-[8.5px] bg-slate-800 text-gray-400">Book 1 Lot</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-400">Target 3:</span>
                            <div class="flex items-center gap-1.5">
                                <span id="at-t3-val" class="font-bold text-white">--</span>
                                <span id="at-t3-badge" class="px-1 py-0.2 rounded text-[8.5px] bg-slate-800 text-gray-400">Runner Lot</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State when no active trade -->
                <div id="no-active-trade-card" class="hidden text-center py-8 text-gray-400 font-mono text-xs">
                    <span class="text-2xl block mb-2">🔭</span>
                    <div>No active trade running.</div>
                    <div class="text-[10px] text-gray-500 mt-1">Scanning market data for 80%+ stock &amp; option breadth triggers...</div>
                </div>
            </div>

            <!-- Right Panel (7 cols): Dynamic Multi-Factor Exit Matrix Checklist Table -->
            <div class="lg:col-span-7 bg-[#0f1422] border border-gray-800 rounded-lg p-2.5 shadow-inner flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-1.5 border-b border-gray-800 mb-1.5">
                        <span class="text-xs font-black tracking-wide text-purple-300 flex items-center gap-1">
                            <span>🛡️ DYNAMIC MULTI-FACTOR EXIT MATRIX</span>
                        </span>
                        <span class="text-[9.5px] text-gray-400 font-mono">Real-Time Risk &amp; S/R Protection</span>
                    </div>

                    <!-- Checklist Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left font-mono text-[10.5px]">
                            <thead>
                                <tr class="text-gray-400 border-b border-gray-800 text-[9.5px] uppercase">
                                    <th class="py-1 px-1.5">Factor</th>
                                    <th class="py-1 px-1.5">Trigger / Anchor Level</th>
                                    <th class="py-1 px-1.5">Current Distance / State</th>
                                    <th class="py-1 px-1.5">Status</th>
                                    <th class="py-1 px-1.5 text-right">Recommendation</th>
                                </tr>
                            </thead>
                            <tbody id="exit-matrix-tbody" class="divide-y divide-gray-800/60">
                                <!-- Factor 1: SL -->
                                <tr id="row-factor-sl">
                                    <td class="py-1.5 px-1.5 font-bold text-white flex items-center gap-1">
                                        <span class="text-xs">1️⃣</span>
                                        <span>Signal Candle SL</span>
                                    </td>
                                    <td class="py-1.5 px-1.5 text-rose-300" id="fm-f1-anchor">24,142.50</td>
                                    <td class="py-1.5 px-1.5 text-gray-300" id="fm-f1-dist">+97.5 pts safe</td>
                                    <td class="py-1.5 px-1.5" id="fm-f1-status">
                                        <span class="px-1.5 py-0.2 rounded text-[9px] bg-emerald-950 text-emerald-300 border border-emerald-800">🟢 SAFE</span>
                                    </td>
                                    <td class="py-1.5 px-1.5 text-right font-bold text-emerald-400" id="fm-f1-action">HOLD / SAFE</td>
                                </tr>

                                <!-- Factor 2: Yesterday S/R -->
                                <tr id="row-factor-yd-sr">
                                    <td class="py-1.5 px-1.5 font-bold text-white flex items-center gap-1">
                                        <span class="text-xs">2️⃣</span>
                                        <span>Yesterday S/R Wall</span>
                                    </td>
                                    <td class="py-1.5 px-1.5 text-amber-300" id="fm-f2-anchor">24,280 [YD RES]</td>
                                    <td class="py-1.5 px-1.5 text-gray-300" id="fm-f2-dist">40 pts away</td>
                                    <td class="py-1.5 px-1.5" id="fm-f2-status">
                                        <span class="px-1.5 py-0.2 rounded text-[9px] bg-emerald-950 text-emerald-300 border border-emerald-800">🟢 CLEAR</span>
                                    </td>
                                    <td class="py-1.5 px-1.5 text-right font-bold text-emerald-400" id="fm-f2-action">CLEAR PATH</td>
                                </tr>

                                <!-- Factor 3: Today S/R -->
                                <tr id="row-factor-td-sr">
                                    <td class="py-1.5 px-1.5 font-bold text-white flex items-center gap-1">
                                        <span class="text-xs">3️⃣</span>
                                        <span>Today S/R &amp; H/L</span>
                                    </td>
                                    <td class="py-1.5 px-1.5 text-sky-300" id="fm-f3-anchor">24,260 [TD RES]</td>
                                    <td class="py-1.5 px-1.5 text-gray-300" id="fm-f3-dist">20 pts away</td>
                                    <td class="py-1.5 px-1.5" id="fm-f3-status">
                                        <span class="px-1.5 py-0.2 rounded text-[9px] bg-emerald-950 text-emerald-300 border border-emerald-800">🟢 CLEAR</span>
                                    </td>
                                    <td class="py-1.5 px-1.5 text-right font-bold text-emerald-400" id="fm-f3-action">WATCH AT 24,260</td>
                                </tr>

                                <!-- Factor 4: Reverse Signal Cross -->
                                <tr id="row-factor-reverse">
                                    <td class="py-1.5 px-1.5 font-bold text-white flex items-center gap-1">
                                        <span class="text-xs">4️⃣</span>
                                        <span>Reverse Signal Cross</span>
                                    </td>
                                    <td class="py-1.5 px-1.5 text-purple-300" id="fm-f4-anchor">No Opposing Signal</td>
                                    <td class="py-1.5 px-1.5 text-gray-300" id="fm-f4-dist">Clean Bull Trend</td>
                                    <td class="py-1.5 px-1.5" id="fm-f4-status">
                                        <span class="px-1.5 py-0.2 rounded text-[9px] bg-emerald-950 text-emerald-300 border border-emerald-800">🟢 CLEAN</span>
                                    </td>
                                    <td class="py-1.5 px-1.5 text-right font-bold text-emerald-400" id="fm-f4-action">NO REVERSE</td>
                                </tr>

                                <!-- Factor 5: Futures 30-Depth Flow -->
                                <tr id="row-factor-depth">
                                    <td class="py-1.5 px-1.5 font-bold text-white flex items-center gap-1">
                                        <span class="text-xs">5️⃣</span>
                                        <span>30-Depth Volume Flow</span>
                                    </td>
                                    <td class="py-1.5 px-1.5 text-cyan-300" id="fm-f5-anchor">Basis: +12.5 pts</td>
                                    <td class="py-1.5 px-1.5 text-gray-300" id="fm-f5-dist">Buyer Delta Leading</td>
                                    <td class="py-1.5 px-1.5" id="fm-f5-status">
                                        <span class="px-1.5 py-0.2 rounded text-[9px] bg-emerald-950 text-emerald-300 border border-emerald-800">🟢 SUPPORTIVE</span>
                                    </td>
                                    <td class="py-1.5 px-1.5 text-right font-bold text-emerald-400" id="fm-f5-action">FLOW BULLISH</td>
                                </tr>

                                <!-- Factor 6: Theta & Greeks -->
                                <tr id="row-factor-greeks">
                                    <td class="py-1.5 px-1.5 font-bold text-white flex items-center gap-1">
                                        <span class="text-xs">6️⃣</span>
                                        <span>Greeks &amp; Theta Decay</span>
                                    </td>
                                    <td class="py-1.5 px-1.5 text-indigo-300" id="fm-f6-anchor">Theta: -₹12.0/lot</td>
                                    <td class="py-1.5 px-1.5 text-gray-300" id="fm-f6-dist">Delta: 0.54 &bull; IV 13.8%</td>
                                    <td class="py-1.5 px-1.5" id="fm-f6-status">
                                        <span class="px-1.5 py-0.2 rounded text-[9px] bg-emerald-950 text-emerald-300 border border-emerald-800">🟢 OPTIMAL</span>
                                    </td>
                                    <td class="py-1.5 px-1.5 text-right font-bold text-emerald-400" id="fm-f6-action">ACTIVE EXPANSION</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-2 pt-1.5 border-t border-gray-800 flex items-center justify-between text-[9.5px] font-mono text-gray-400">
                    <span>⚡ Trade Engine dynamically re-checks all 6 factors at every candle close &amp; replay step.</span>
                    <span id="exit-engine-summary-badge" class="px-2 py-0.5 rounded font-bold bg-emerald-950 text-emerald-300 border border-emerald-700">POSITION IN GOOD HEALTH</span>
                </div>
            </div>
        </div>

        <!-- Bottom Row: Day Trade History Table (When "Show All Trades" is toggled or for forensic review) -->
        <div id="trade-history-section" class="bg-[#0f1422] border border-gray-800 rounded-lg p-2 shadow-inner">
            <div class="flex items-center justify-between pb-1 border-b border-gray-800 mb-1">
                <span class="text-xs font-bold text-gray-300 flex items-center gap-1">
                    <span>📜 Session Trade Logs &amp; Forensics:</span>
                    <span id="trade-history-count" class="text-[9.5px] font-mono px-1 py-0.2 rounded bg-slate-800 text-sky-400">0 Trades</span>
                </span>
                <span class="text-[9.5px] text-gray-500 font-mono">Full Trade Progression History</span>
            </div>
            <div class="overflow-x-auto max-h-[140px] overflow-y-auto">
                <table class="w-full text-left font-mono text-[10px]">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-800 uppercase text-[9px]">
                            <th class="py-1 px-1">ID</th>
                            <th class="py-1 px-1">Type</th>
                            <th class="py-1 px-1">Strike</th>
                            <th class="py-1 px-1">Entry Time</th>
                            <th class="py-1 px-1">Entry Spot</th>
                            <th class="py-1 px-1">Exit Time</th>
                            <th class="py-1 px-1">Exit Spot</th>
                            <th class="py-1 px-1">Points</th>
                            <th class="py-1 px-1">P&amp;L (₹)</th>
                            <th class="py-1 px-1">Exit Reason</th>
                            <th class="py-1 px-1 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody id="trade-history-tbody" class="divide-y divide-gray-800/40">
                        <tr>
                            <td colspan="11" class="text-center py-2 text-gray-500">No trades completed yet in this session.</td>
                        </tr>
                    </tbody>
                </table>
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
    let currentUnifiedTimeframe = "{{ $selectedTimeframe }}";
    let currentTfMinutes = {{ $tfMinutes }};

    const TF_MINUTES_MAP = {
        '1m': 1,
        '3m': 3,
        '5m': 5,
        '15m': 15,
        '30m': 30,
        '60m': 60,
        '1h': 60,
        '1D': 375
    };

    function getTfMinutes(tf) {
        return TF_MINUTES_MAP[tf] || 5;
    }

    // Independent Timeframes for Chart 1 (Stocks) and Chart 2 (Options)
    // Priority: Explicit URL timeframe parameter -> currentUnifiedTimeframe -> localStorage -> default 5m
    const urlParamsObj = new URLSearchParams(window.location.search);
    const hasExplicitUrlTf = urlParamsObj.has('timeframe') || urlParamsObj.has('interval');
    let initialTf = hasExplicitUrlTf ? currentUnifiedTimeframe : (localStorage.getItem('nifty_unified_w_tf') || currentUnifiedTimeframe || '5m');

    let wTimeframe = initialTf;
    let oTimeframe = hasExplicitUrlTf ? currentUnifiedTimeframe : (localStorage.getItem('nifty_unified_o_tf') || initialTf);

    if (hasExplicitUrlTf) {
        try {
            localStorage.setItem('nifty_unified_w_tf', currentUnifiedTimeframe);
            localStorage.setItem('nifty_unified_o_tf', currentUnifiedTimeframe);
        } catch(e){}
    }

    // Chart Candlestick Mode (SPOT Index vs Near-Month FUTURES)
    const isBankNiftyPage = window.location.pathname.includes('banknifty');
    const wBasisStorageKey = isBankNiftyPage ? 'banknifty_unified_w_basis' : 'nifty_unified_w_basis';
    const oBasisStorageKey = isBankNiftyPage ? 'banknifty_unified_o_basis' : 'nifty_unified_o_basis';
    let wChartBasis = localStorage.getItem(wBasisStorageKey) || 'spot'; // 'spot' or 'future'
    let oChartBasis = localStorage.getItem(oBasisStorageKey) || 'spot'; // 'spot' or 'future'

    function setChart1CandleBasis(basis) {
        if (wChartBasis === basis) return;
        wChartBasis = (basis === 'future') ? 'future' : 'spot';
        try { localStorage.setItem(wBasisStorageKey, wChartBasis); } catch(e){}
        updateChartBasisUI();
        drawWeightageOrderFlowLines();
        drawMultiDayOrderFlowLines();
        reloadChart1Weightage();
    }
    window.setChart1CandleBasis = setChart1CandleBasis;

    function setChart2CandleBasis(basis) {
        if (oChartBasis === basis) return;
        oChartBasis = (basis === 'future') ? 'future' : 'spot';
        try { localStorage.setItem(oBasisStorageKey, oChartBasis); } catch(e){}
        updateChartBasisUI();
        drawOptionsOrderFlowLines();
        drawMultiDayOrderFlowLines();
        reloadChart2Options();
    }
    window.setChart2CandleBasis = setChart2CandleBasis;

    function updateChartBasisUI() {
        const wSpotBtn = document.getElementById('w-basis-spot-btn');
        const wFutBtn = document.getElementById('w-basis-fut-btn');
        if (wSpotBtn && wFutBtn) {
            if (wChartBasis === 'future') {
                wSpotBtn.className = 'px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800';
                wFutBtn.className = 'px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer bg-amber-500 text-black font-extrabold shadow-xs';
            } else {
                wSpotBtn.className = 'px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer bg-sky-600 text-white font-extrabold shadow-xs';
                wFutBtn.className = 'px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800';
            }
        }
        const wTitle = document.getElementById('w-chart-title');
        if (wTitle) {
            const baseName = isBankNiftyPage ? 'BANK NIFTY' : 'NIFTY 50';
            wTitle.textContent = (wChartBasis === 'future') ? `${baseName} FUT` : baseName;
        }

        const oSpotBtn = document.getElementById('o-basis-spot-btn');
        const oFutBtn = document.getElementById('o-basis-fut-btn');
        if (oSpotBtn && oFutBtn) {
            if (oChartBasis === 'future') {
                oSpotBtn.className = 'px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800';
                oFutBtn.className = 'px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer bg-amber-500 text-black font-extrabold shadow-xs';
            } else {
                oSpotBtn.className = 'px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer bg-purple-600 text-white font-extrabold shadow-xs';
                oFutBtn.className = 'px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800';
            }
        }
        const oTitle = document.getElementById('o-chart-title');
        if (oTitle) {
            oTitle.textContent = (oChartBasis === 'future') ? 'OPTIONS (FUT)' : 'OPTIONS';
        }
    }
    window.updateChartBasisUI = updateChartBasisUI;

    function setChart1Timeframe(tf) {
        if (wTimeframe === tf) return;
        wTimeframe = tf;
        try { localStorage.setItem('nifty_unified_w_tf', tf); } catch(e){}
        updateTimeframeButtonsUI();
        if (typeof updateCandleCloseCountdown === 'function') updateCandleCloseCountdown();
        reloadChart1Weightage();
    }
    window.setChart1Timeframe = setChart1Timeframe;

    function setChart2Timeframe(tf) {
        if (oTimeframe === tf) return;
        oTimeframe = tf;
        try { localStorage.setItem('nifty_unified_o_tf', tf); } catch(e){}
        updateTimeframeButtonsUI();
        if (typeof updateCandleCloseCountdown === 'function') updateCandleCloseCountdown();
        reloadChart2Options();
    }
    window.setChart2Timeframe = setChart2Timeframe;

    function updateTimeframeButtonsUI() {
        document.querySelectorAll('.w-tf-btn').forEach(btn => {
            const tf = btn.getAttribute('data-wtf');
            if (tf === wTimeframe) {
                btn.className = 'w-tf-btn px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer bg-sky-600 text-white shadow-xs';
            } else {
                btn.className = 'w-tf-btn px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800';
            }
        });

        document.querySelectorAll('.o-tf-btn').forEach(btn => {
            const tf = btn.getAttribute('data-otf');
            if (tf === oTimeframe) {
                btn.className = 'o-tf-btn px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer bg-purple-600 text-white shadow-xs';
            } else {
                btn.className = 'o-tf-btn px-1.5 py-0.2 rounded font-mono font-bold text-[10px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800';
            }
        });

        document.querySelectorAll('.tf-selector-btn').forEach(btn => {
            if (wTimeframe === oTimeframe && btn.dataset.tf === wTimeframe) {
                btn.className = 'tf-selector-btn px-1.5 py-0.5 rounded font-mono font-bold text-[10.5px] transition cursor-pointer bg-sky-600 text-white shadow-xs';
            } else {
                btn.className = 'tf-selector-btn px-1.5 py-0.5 rounded font-mono font-bold text-[10.5px] transition cursor-pointer text-gray-400 hover:text-white hover:bg-gray-800';
            }
        });

        updateCandleStyleUI();
        updateChartBasisUI();
        syncBuildupTabUI();
        syncSynergyControlsUI();
    }
    window.updateTimeframeButtonsUI = updateTimeframeButtonsUI;

    window.setUnifiedTimeframe = function(tfKey, minutes) {
        wTimeframe = tfKey;
        oTimeframe = tfKey;
        currentUnifiedTimeframe = tfKey;
        currentTfMinutes = minutes || getTfMinutes(tfKey);

        try {
            localStorage.setItem('nifty_unified_w_tf', tfKey);
            localStorage.setItem('nifty_unified_o_tf', tfKey);
            const url = new URL(window.location);
            url.searchParams.set('timeframe', tfKey);
            window.history.replaceState({}, '', url);
        } catch(e) {}

        updateTimeframeButtonsUI();

        if (typeof updateCandleCloseCountdown === 'function') {
            updateCandleCloseCountdown();
        }

        loadAllData(false);
    };

    let activeCandleIndex = 0;
    let timestampsList = [];
    let isPlaying = false;
    let playIntervalId = null;
    let autoRefreshTimerId = null;
    let autoRefreshCountdown = 300;
    let activeRefreshSeconds = 300;
    let autoRefreshMode = localStorage.getItem('nifty_unified_refresh_mode') || 'candle_5m';
    let isTimerVisible = localStorage.getItem('nifty_unified_timer_visible') !== 'false';
        let currentChart1Height = parseInt(localStorage.getItem('nifty_unified_chart1_height') || localStorage.getItem('nifty_unified_chart_height') || '420', 10);
    let currentChart2Height = parseInt(localStorage.getItem('nifty_unified_chart2_height') || localStorage.getItem('nifty_unified_chart_height') || '420', 10);
    let currentToiHeight = parseInt(localStorage.getItem('nifty_unified_toi_height') || '250', 10);
    let currentBldHeight = parseInt(localStorage.getItem('nifty_unified_buildup_height') || '195', 10);
    let currentChartHeight = currentChart1Height; // Fallback backward compatibility
    let currentCockpitFontScale = parseInt(localStorage.getItem('nifty_unified_font_scale') || '115', 10);

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
        slMode: 'candle_hl', // 'candle_hl' (Candle High/Low ± Buffer) or 'fixed_offset'
        slBuffer: 15,        // points buffer from candle low/high (default: 15, e.g. 10 or 20)
        slOffset: 100,       // fallback fixed points
        tpOffset: 100,       // Target 1 (TP1): default 100 pts
        tp2Offset: 150,      // Target 2 (TP2): default 150 pts
        product: 'MIS'
    };
    let zeroClickSettings = { ...defaultZeroClickSettings };
    try {
        const savedZc = localStorage.getItem('nifty_zeroclick_settings');
        if (savedZc) {
            zeroClickSettings = { ...zeroClickSettings, ...JSON.parse(savedZc) };
            // Sanitize / migrate legacy saved settings where TP1 was 200 and TP2 was 150
            if (zeroClickSettings.tpOffset === 200 && (!zeroClickSettings.tp2Offset || zeroClickSettings.tp2Offset === 150)) {
                zeroClickSettings.tpOffset = 100;
                zeroClickSettings.tp2Offset = 150;
            }
        }
    } catch(e) {}

    // Breadth Extreme & Confluence Alarm Settings (Detailed Split Configuration)
    const defaultAlarmSettings = {
        masterEnabled: true,
        volume: 1.0,
        triggerOnClose: true, // Only trigger permanent alarm on confirmed closed candles (zero-repaint)

        // 1. NIFTY 50 Stocks Breadth Alert
        stocksAlertEnabled: true,
        stocksUpThreshold: 80,
        stocksDownThreshold: 80,
        stocksTriggerBy: 'both', // 'both' (Count & Weight), 'either', 'count', 'weight'
        stocksShowMarkers: true,
        stocksMarkerMode: 'breach', // 'breach' (≥80% only), 'all' (every candle status), 'off'
        stocksSound: 'bull_horn',

        // 2. NIFTY 50 Options Breadth Alert
        optionsAlertEnabled: true,
        optionsUpThreshold: 70,
        optionsDownThreshold: 70,
        optionsShowMarkers: true,
        optionsMarkerMode: 'breach', // 'breach' (≥70% only), 'all' (every candle status), 'off'
        optionsSound: 'pulse',
        optionsBasketMode: 'fixed', // 'fixed' (9:15 AM anchor - Default) or 'dynamic' (auto-floating ATM ±5 strikes)

        // 3. High-Probability Confluence Alert (Combined)
        confluenceEnabled: true,
        confluenceSound: 'siren',
        // 4. Heikin Ashi Single-Wick Entry Filter
        stocksHaWickFilter: true,
        optionsHaWickFilter: true,
        chart1ShowStocks: true,
        chart1ShowOptions: false,
        chart2ShowStocks: false,
        chart2ShowOptions: true,

        // 5. Quantitative Edge & Accuracy Filters (5 Toggleable Boosters)
        filterHaAdaptiveSl: true,       // 1. Heikin-Ashi Adaptive Stop Loss (+132 pts backtest gain)
        filterBreadthVelocity: true,    // 2. Breadth Velocity (ΔBreadth ≥ 0, blocks exhaustion entries)
        filterHtfAlignment: true,       // 3. Higher Timeframe Alignment (15m ST & VWAP on 1m/3m/5m)
        filterSrProximity: true,        // 4. Quant S/R Proximity Guard (Min 1.2x Risk-to-Reward)
        filterOiDeltaConfirmation: true,// 5. Options OI Delta & Fresh Writing Confirmation

        // 6. Order Flow Depth S/R Settings (Yesterday Institutional Anchors & Today Live Liquidity Blocks)
        ofShowYesterday: true,          // Toggle Yesterday S/R lines
        ofShowToday: true,              // Toggle Today Live S/R lines
        ofLevelCount: 1,                // Top N levels from Bid and Ask (default 1)
        ofLineWidth: 3,                 // Line thickness (default 3)
        ofLineStyleYesterday: 'solid',  // 'solid', 'dashed', 'dotted'
        ofLineStyleToday: 'dashed',     // 'dashed', 'solid', 'dotted'
        ofRankBy: 'cumulative',         // 'cumulative' (default) or 'peak'
        ofCalibration: 'spot'           // 'spot' (calibrated to spot index) or 'future' (raw future levels)
    };

    let alarmSettings = { ...defaultAlarmSettings };
    try {
        const saved = localStorage.getItem('nifty_unified_alarm_settings');
        if (saved) {
            alarmSettings = { ...alarmSettings, ...JSON.parse(saved) };
            if (!alarmSettings.optionsBasketMode) alarmSettings.optionsBasketMode = 'fixed';
            // Auto-heal unreachable options threshold if set > 80% (Options surface rarely hits 80% on 15m+)
            if (alarmSettings.optionsUpThreshold > 80) alarmSettings.optionsUpThreshold = 70;
            if (alarmSettings.optionsDownThreshold > 80) alarmSettings.optionsDownThreshold = 70;
        }
    } catch (e) {
        console.warn('Could not read alarm settings:', e);
    }

    // Data stores (Per-Chart Dual Deck for Chart 1 & Chart 2)
    let weightageData = null; // Chart 1 Stocks active reference
    let breadthData = null;   // Chart 2 Options active reference
    let wWeightageData = null; // Stocks data for Chart 1 (wTimeframe)
    let wBreadthData = null;   // Options data for Chart 1 (wTimeframe)
    let oWeightageData = null; // Stocks data for Chart 2 (oTimeframe)
    let oBreadthData = null;   // Options data for Chart 2 (oTimeframe)
    let trendingOiData = null;
    let activeBuildupTf = '5m';

    // Chart instances
    let wChart = null;
    let wCandleSeries = null;
    let wStBullSeries = null;
    let wStBearSeries = null;
    let oChart = null;
    let oCandleSeries = null;
    let oStBullSeries = null;
    let oStBearSeries = null;
    let toiChartInstance = null;
    let buildupChartInstance = null;

    // Flow Synergy State (Tabbed Row 2 Cockpit)
    let currentBuildupTab = localStorage.getItem('nifty_unified_bld_tab') || 'synergy'; // 'synergy' | 'buildup'
    let synergyChartStyle = localStorage.getItem('nifty_unified_synergy_style') || 'hist'; // 'hist' | 'matrix'
    if (synergyChartStyle === 'ribbon') {
        synergyChartStyle = 'hist';
        try { localStorage.setItem('nifty_unified_synergy_style', 'hist'); } catch (e) {}
    }
    let synergyThStkWt = parseInt(localStorage.getItem('nifty_unified_synergy_th_stk_wt') || '80', 10);
    let synergyThStkCnt = parseInt(localStorage.getItem('nifty_unified_synergy_th_stk_cnt') || '80', 10);
    let synergyThOptFlow = parseInt(localStorage.getItem('nifty_unified_synergy_th_opt_flow') || '70', 10);
    let synergyThreshold = synergyThStkWt;
    let synergyMetricMode = localStorage.getItem('nifty_unified_synergy_metric') || 'both'; // 'both' | 'weight' | 'count'
    let synergyBarViewMode = localStorage.getItem('nifty_unified_synergy_bar_mode') || 'scroll'; // 'scroll' | 'fit'
    let flowSynergyChartInstance = null;

    // TradingView Style Overlay Settings & Price Lines
    let wFibPriceLines = [];
    let oFibPriceLines = [];
    let wManualPriceLines = [];
    let oManualPriceLines = [];

    // Decoupled TradingView Dock & Overlay Settings per chart
    let wTvSettings = {
        superTrend: localStorage.getItem('nifty_unified_w_st_mode') || 'off', // 'off', '10_3', '10_2'
        fibMode: localStorage.getItem('nifty_unified_w_fib_mode') || 'off', // 'off', 'auto', 'manual'
        manualFibPoints: JSON.parse(localStorage.getItem('nifty_unified_w_manual_fib') || 'null'),
        manualHLines: JSON.parse(localStorage.getItem('nifty_unified_w_manual_hlines') || '[]'),
    };
    let oTvSettings = {
        superTrend: localStorage.getItem('nifty_unified_o_st_mode') || 'off', // 'off', '10_3', '10_2'
        fibMode: localStorage.getItem('nifty_unified_o_fib_mode') || 'off', // 'off', 'auto', 'manual'
        manualFibPoints: JSON.parse(localStorage.getItem('nifty_unified_o_manual_fib') || 'null'),
        manualHLines: JSON.parse(localStorage.getItem('nifty_unified_o_manual_hlines') || '[]'),
    };

    let wActiveTvTool = 'pointer'; // 'pointer', 'hline', 'fib_manual'
    let oActiveTvTool = 'pointer'; // 'pointer', 'hline', 'fib_manual'
    let wManualFibClickStep = 0;
    let wManualFibTempP1 = null;
    let oManualFibClickStep = 0;
    let oManualFibTempP1 = null;

    // Decoupled Candle Style per chart (regular 'candle' vs 'heikin_ashi')
    let wCandleStyle = localStorage.getItem('nifty_unified_w_candle_style') || 'candle';
    let oCandleStyle = localStorage.getItem('nifty_unified_o_candle_style') || 'candle';

    window._unifiedAllCandles = [];
    window._unifiedHaCandles = [];
    window._unifiedOptionsCandles = [];
    window._unifiedOptionsHaCandles = [];
    window._wCandleMarkers = [];
    window._oCandleMarkers = [];

    // Decoupled Quant S/R Lines Data & Instances per chart
    let quantPlaybookData = @json($quantPlaybook ?? null);
    let wShowQuantLines = false; // Quant S/R chart lines removed
    let oShowQuantLines = false; // Quant S/R chart lines removed
    let quantPriceLinesOptions = [];
    let quantPriceLinesWeightage = [];

    // Order Flow S/R Lines Data & Instances per chart
    let orderFlowLevelsData = null;
    let ofPriceLinesWeightage = [];
    let ofPriceLinesOptions = [];
    let wShowOfSrLines = localStorage.getItem('nifty_unified_w_of_sr_lines') !== 'false'; // default true
    let oShowOfSrLines = localStorage.getItem('nifty_unified_o_of_sr_lines') !== 'false'; // default true

    // Multi-Day Top 1 Order Flow S/R Levels (5 Days)
    let multiDayOfData = null;
    let multiDayOfSelected = {};
    try {
        multiDayOfSelected = JSON.parse(localStorage.getItem('nifty_unified_multiday_of_selected') || '{}');
    } catch (e) {
        multiDayOfSelected = {};
    }
    let histOfPriceLinesWeightage = [];
    let histOfPriceLinesOptions = [];
    let histOfDropdownTrigger = null;

    function updateQuantLinesButtonUI() {
        const badgeO = document.getElementById('quant-lines-status-badge');
        if (badgeO) {
            badgeO.textContent = oShowQuantLines ? 'ON' : 'OFF';
            badgeO.className = oShowQuantLines ? 'font-mono text-emerald-400 font-extrabold' : 'font-mono text-slate-400 font-bold';
        }
        const badgeW = document.getElementById('w-quant-lines-status-badge');
        if (badgeW) {
            badgeW.textContent = wShowQuantLines ? 'ON' : 'OFF';
            badgeW.className = wShowQuantLines ? 'font-mono text-emerald-400 font-extrabold' : 'font-mono text-slate-400 font-bold';
        }
        const badgeStrip = document.getElementById('strip-quant-lines-status-badge');
        if (badgeStrip) {
            const anyOn = (wShowQuantLines || oShowQuantLines);
            badgeStrip.textContent = anyOn ? 'ON' : 'OFF';
            badgeStrip.className = anyOn ? 'font-mono text-emerald-400 font-extrabold' : 'font-mono text-slate-400 font-bold';
        }
    }

    function toggleWeightageQuantLines() {
        wShowQuantLines = !wShowQuantLines;
        try {
            localStorage.setItem('nifty_unified_w_quant_lines', wShowQuantLines ? 'true' : 'false');
        } catch(e){}
        updateQuantLinesButtonUI();
        drawWeightageQuantLines();
    }
    window.toggleWeightageQuantLines = toggleWeightageQuantLines;

    function toggleOptionsQuantLines() {
        oShowQuantLines = !oShowQuantLines;
        try {
            localStorage.setItem('nifty_unified_o_quant_lines', oShowQuantLines ? 'true' : 'false');
        } catch(e){}
        updateQuantLinesButtonUI();
        drawOptionsQuantLines();
    }
    window.toggleOptionsQuantLines = toggleOptionsQuantLines;

    function toggleQuantPriceLines() {
        const next = !(wShowQuantLines || oShowQuantLines);
        wShowQuantLines = next;
        oShowQuantLines = next;
        try {
            localStorage.setItem('nifty_unified_w_quant_lines', next ? 'true' : 'false');
            localStorage.setItem('nifty_unified_o_quant_lines', next ? 'true' : 'false');
        } catch(e){}
        updateQuantLinesButtonUI();
        drawWeightageQuantLines();
        drawOptionsQuantLines();
    }
    window.toggleQuantPriceLines = toggleQuantPriceLines;

    // ── Order Flow S/R Functions (Yesterday Anchors & Today Live Liquidity) ──
    let ofCalibrationMode = localStorage.getItem('nifty_unified_of_calibration') || 'spot';
    if (ofCalibrationMode !== 'spot' && ofCalibrationMode !== 'future' && ofCalibrationMode !== 'both') {
        ofCalibrationMode = 'spot';
    }

    function updateOfCalibrationUI() {
        ['w-of-calib-badge', 'o-of-calib-badge'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                if (ofCalibrationMode === 'both') {
                    el.textContent = 'SPOT & FUT';
                    el.className = 'font-mono text-cyan-300 font-extrabold';
                } else if (ofCalibrationMode === 'future') {
                    el.textContent = 'FUT';
                    el.className = 'font-mono text-amber-400 font-extrabold';
                } else {
                    el.textContent = 'SPOT';
                    el.className = 'font-mono text-teal-300 font-extrabold';
                }
            }
        });
        const cfgSelect = document.getElementById('cfg-of-calibration');
        if (cfgSelect) cfgSelect.value = ofCalibrationMode;
        const cfgBadge = document.getElementById('cfg-of-calib-badge');
        if (cfgBadge) {
            if (ofCalibrationMode === 'both') {
                cfgBadge.textContent = 'SPOT & FUT OVERLAY';
                cfgBadge.className = 'px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-cyan-950 text-cyan-300 border border-cyan-500/40';
            } else if (ofCalibrationMode === 'future') {
                cfgBadge.textContent = 'RAW FUTURES';
                cfgBadge.className = 'px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-amber-950 text-amber-300 border border-amber-500/40';
            } else {
                cfgBadge.textContent = 'SPOT CALIBRATED';
                cfgBadge.className = 'px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-teal-950 text-teal-300 border border-teal-500/40';
            }
        }
    }
    window.updateOfCalibrationUI = updateOfCalibrationUI;

    function toggleOfCalibration() {
        if (ofCalibrationMode === 'spot') {
            setOfCalibration('future');
        } else if (ofCalibrationMode === 'future') {
            setOfCalibration('both');
        } else {
            setOfCalibration('spot');
        }
    }
    window.toggleOfCalibration = toggleOfCalibration;

    function setOfCalibration(mode) {
        ofCalibrationMode = (mode === 'future') ? 'future' : ((mode === 'both') ? 'both' : 'spot');
        try {
            localStorage.setItem('nifty_unified_of_calibration', ofCalibrationMode);
        } catch (e) {}
        alarmSettings.ofCalibration = ofCalibrationMode;
        updateOfCalibrationUI();
        drawWeightageOrderFlowLines();
        drawOptionsOrderFlowLines();
        drawMultiDayOrderFlowLines();
        renderOfLevelsPreview();
        renderHistOfDropdownContent();
    }
    window.setOfCalibration = setOfCalibration;

    function updateOfSrButtonUI() {
        const badgeW = document.getElementById('w-of-sr-status-badge');
        if (badgeW) {
            badgeW.textContent = wShowOfSrLines ? 'ON' : 'OFF';
            badgeW.className = wShowOfSrLines ? 'font-mono text-emerald-400 font-extrabold' : 'font-mono text-slate-400 font-bold';
        }
        const badgeO = document.getElementById('o-of-sr-status-badge');
        if (badgeO) {
            badgeO.textContent = oShowOfSrLines ? 'ON' : 'OFF';
            badgeO.className = oShowOfSrLines ? 'font-mono text-emerald-400 font-extrabold' : 'font-mono text-slate-400 font-bold';
        }
        updateOfCalibrationUI();
    }

    function toggleWeightageOfSrLines() {
        wShowOfSrLines = !wShowOfSrLines;
        try {
            localStorage.setItem('nifty_unified_w_of_sr_lines', wShowOfSrLines ? 'true' : 'false');
        } catch(e){}
        updateOfSrButtonUI();
        drawWeightageOrderFlowLines();
        drawMultiDayOrderFlowLines();
    }
    window.toggleWeightageOfSrLines = toggleWeightageOfSrLines;

    function toggleOptionsOfSrLines() {
        oShowOfSrLines = !oShowOfSrLines;
        try {
            localStorage.setItem('nifty_unified_o_of_sr_lines', oShowOfSrLines ? 'true' : 'false');
        } catch(e){}
        updateOfSrButtonUI();
        drawOptionsOrderFlowLines();
        drawMultiDayOrderFlowLines();
    }
    window.toggleOptionsOfSrLines = toggleOptionsOfSrLines;

    function clearWeightageOrderFlowLines() {
        if (wCandleSeries && ofPriceLinesWeightage.length > 0) {
            ofPriceLinesWeightage.forEach(line => {
                try { wCandleSeries.removePriceLine(line); } catch (e) {}
            });
        }
        ofPriceLinesWeightage = [];
    }
    window.clearWeightageOrderFlowLines = clearWeightageOrderFlowLines;

    function clearOptionsOrderFlowLines() {
        if (oCandleSeries && ofPriceLinesOptions.length > 0) {
            ofPriceLinesOptions.forEach(line => {
                try { oCandleSeries.removePriceLine(line); } catch (e) {}
            });
        }
        ofPriceLinesOptions = [];
    }
    window.clearOptionsOrderFlowLines = clearOptionsOrderFlowLines;

    function createSpecificOfLine(series, item, type, isFut, dateLabel, colors, container, isToday) {
        if (!series || !item) return;
        const spotPrice = parseFloat(item.calibrated_price ?? item.price);
        const rawPrice = parseFloat(item.raw_price ?? item.price);
        const price = isFut ? rawPrice : spotPrice;
        if (isNaN(price)) return;

        const lineWidth = parseInt(alarmSettings.ofLineWidth || (isToday ? 3 : 2), 10);
        const styleYd = alarmSettings.ofLineStyleYesterday === 'dashed' ? 2 : (alarmSettings.ofLineStyleYesterday === 'dotted' ? 1 : 0);
        const styleTd = alarmSettings.ofLineStyleToday === 'solid' ? 0 : (alarmSettings.ofLineStyleToday === 'dotted' ? 1 : 2);

        const baseColor = isToday 
            ? (type === 'ask' ? '#f43f5e' : '#34d399') 
            : ((colors && (type === 'ask' ? colors.res : colors.sup)) || (type === 'ask' ? '#ef4444' : '#10b981'));

        const baseStyle = isToday ? styleTd : (colors?.style ?? styleYd);
        // Fut lines use dashed / contrasting style (style 2 or 1) so trader can easily distinguish Spot vs Fut
        const lineStyle = isFut ? ((baseStyle === 2) ? 1 : 2) : baseStyle;

        const typeStr = (type === 'ask') ? 'RES' : 'SUP';
        const basisTag = isFut ? '(FUT)' : '(SPOT)';
        const defaultTitle = `${dateLabel} ${typeStr} ${basisTag} ₹${price.toLocaleString('en-IN', {maximumFractionDigits: 1})}`;
        const title = isFut ? (item.raw_label || defaultTitle) : (item.label ? item.label.replace(/^([A-Za-z0-9]+\s+(?:RES|SUP))(?!\s*\(SPOT\))/, '$1 (SPOT)') : defaultTitle);

        try {
            const line = series.createPriceLine({
                price: price,
                color: baseColor,
                lineWidth: lineWidth,
                lineStyle: lineStyle,
                axisLabelVisible: true,
                title: title,
            });
            container.push(line);
        } catch (e) {}
    }

    function drawWeightageOrderFlowLines() {
        clearWeightageOrderFlowLines();
        if (!wShowOfSrLines || !orderFlowLevelsData || !wCandleSeries) return;

        // Today Lines (gated by isLevelSelected for spot_ask, fut_ask, spot_bid, fut_bid)
        if (alarmSettings.ofShowToday !== false && orderFlowLevelsData.today) {
            const asks = orderFlowLevelsData.today.asks || [];
            const bids = orderFlowLevelsData.today.bids || [];
            asks.forEach(item => {
                if (isLevelSelected('today', 'spot_ask', wChartBasis)) createSpecificOfLine(wCandleSeries, item, 'ask', false, 'TD', null, ofPriceLinesWeightage, true);
                if (isLevelSelected('today', 'fut_ask', wChartBasis))  createSpecificOfLine(wCandleSeries, item, 'ask', true, 'TD', null, ofPriceLinesWeightage, true);
            });
            bids.forEach(item => {
                if (isLevelSelected('today', 'spot_bid', wChartBasis)) createSpecificOfLine(wCandleSeries, item, 'bid', false, 'TD', null, ofPriceLinesWeightage, true);
                if (isLevelSelected('today', 'fut_bid', wChartBasis))  createSpecificOfLine(wCandleSeries, item, 'bid', true, 'TD', null, ofPriceLinesWeightage, true);
            });
        }
    }
    window.drawWeightageOrderFlowLines = drawWeightageOrderFlowLines;

    function drawOptionsOrderFlowLines() {
        clearOptionsOrderFlowLines();
        if (!oShowOfSrLines || !orderFlowLevelsData || !oCandleSeries) return;

        // Today Lines (gated by isLevelSelected for spot_ask, fut_ask, spot_bid, fut_bid)
        if (alarmSettings.ofShowToday !== false && orderFlowLevelsData.today) {
            const asks = orderFlowLevelsData.today.asks || [];
            const bids = orderFlowLevelsData.today.bids || [];
            asks.forEach(item => {
                if (isLevelSelected('today', 'spot_ask', oChartBasis)) createSpecificOfLine(oCandleSeries, item, 'ask', false, 'TD', null, ofPriceLinesOptions, true);
                if (isLevelSelected('today', 'fut_ask', oChartBasis))  createSpecificOfLine(oCandleSeries, item, 'ask', true, 'TD', null, ofPriceLinesOptions, true);
            });
            bids.forEach(item => {
                if (isLevelSelected('today', 'spot_bid', oChartBasis)) createSpecificOfLine(oCandleSeries, item, 'bid', false, 'TD', null, ofPriceLinesOptions, true);
                if (isLevelSelected('today', 'fut_bid', oChartBasis))  createSpecificOfLine(oCandleSeries, item, 'bid', true, 'TD', null, ofPriceLinesOptions, true);
            });
        }
    }
    window.drawOptionsOrderFlowLines = drawOptionsOrderFlowLines;

    async function fetchOrderFlowLevels() {
        const symbol = 'NIFTY';
        const limit = parseInt(alarmSettings.ofLevelCount || 1, 10);
        const rankBy = alarmSettings.ofRankBy || 'cumulative';

        const spotEl = document.getElementById('spot-price-display');
        let curSpot = 0;
        if (spotEl && spotEl.textContent) {
            const raw = parseFloat(spotEl.textContent.replace(/[^0-9.]/g, ''));
            if (!isNaN(raw) && raw > 0) curSpot = raw;
        }
        if (!curSpot && typeof trendingOiData !== 'undefined' && trendingOiData?.underlying_data?.spot) {
            curSpot = parseFloat(trendingOiData.underlying_data.spot);
        }

        try {
            const spotParam = curSpot > 0 ? `&spot=${curSpot}` : '';
            const res = await fetch(`/api/multi-timeframe/order-flow-levels?date=${selectedDate}&symbol=${symbol}&limit=${limit}&rank_by=${rankBy}${spotParam}&_t=${Date.now()}`);
            const data = await res.json();
            if (data && data.success) {
                orderFlowLevelsData = data;
                drawWeightageOrderFlowLines();
                drawOptionsOrderFlowLines();
                renderOfLevelsPreview();
                updateOfSrButtonUI();
            }
        } catch (e) {
            console.warn('Could not fetch Order Flow S/R levels:', e);
        }
    }
    window.fetchOrderFlowLevels = fetchOrderFlowLevels;

    function renderOfLevelsPreview() {
        const container = document.getElementById('of-levels-preview-content');
        const dateBadge = document.getElementById('of-preview-date-badge');
        if (!container) return;
        if (dateBadge && orderFlowLevelsData) {
            const spreadInfo = (orderFlowLevelsData.basis_spread !== undefined && orderFlowLevelsData.basis_spread !== null) 
                ? ` | Basis: ${orderFlowLevelsData.basis_spread >= 0 ? '+' : ''}${orderFlowLevelsData.basis_spread} pts` 
                : '';
            dateBadge.textContent = `${orderFlowLevelsData.prev_date || 'YD'} / ${orderFlowLevelsData.date || 'TD'}${spreadInfo}`;
        }
        if (!orderFlowLevelsData || (!orderFlowLevelsData.yesterday && !orderFlowLevelsData.today)) {
            container.innerHTML = `<div class="p-2 rounded bg-[#1a1e29] border border-gray-800 text-gray-400 col-span-2">No Order Flow Traded Volume levels recorded for this date.</div>`;
            return;
        }

        const isSpot = (ofCalibrationMode === 'spot');
        const isBoth = (ofCalibrationMode === 'both');

        const renderItemRow = (item, side, isYesterday) => {
            const spotP = Number(item.calibrated_price || item.price);
            const rawP = Number(item.raw_price || item.price);
            const k = item.qty >= 1000 ? (item.qty / 1000).toFixed(1) + 'k' : item.qty;
            const isAsk = (side === 'ask');
            const colorClass = isAsk ? 'text-rose-400 bg-rose-950/30 border-rose-900/40' : 'text-emerald-400 bg-emerald-950/30 border-emerald-900/40';
            const labelStr = isAsk ? 'RES (Ask):' : 'SUP (Bid):';

            let valHtml = '';
            if (isBoth) {
                valHtml = `<span>₹${spotP.toLocaleString()} <span class="text-[9px] text-cyan-300 font-normal">(Spot)</span> · ₹${rawP.toLocaleString()} <span class="text-[9px] text-amber-300 font-normal">(Fut)</span> <span class="text-[9.5px] text-gray-300 font-mono">[${k}]</span></span>`;
            } else {
                const dispP = isSpot ? spotP : rawP;
                const altP = isSpot ? `Fut: ₹${rawP.toLocaleString()}` : `Spot: ₹${spotP.toLocaleString()}`;
                const tag = isSpot ? 'Spot' : 'Fut';
                valHtml = `<span title="${altP}">₹${dispP.toLocaleString()} [${k}] <span class="text-[9px] text-gray-400 font-normal">(${tag})</span></span>`;
            }

            return `<div class="flex items-center justify-between ${colorClass} px-1.5 py-0.5 rounded border font-bold">
                <span>${labelStr}</span>
                ${valHtml}
            </div>`;
        };

        let html = '';
        const ydAsks = orderFlowLevelsData.yesterday?.asks || [];
        const ydBids = orderFlowLevelsData.yesterday?.bids || [];
        html += `
            <div class="p-2 rounded bg-[#1a1e29] border border-gray-800 space-y-1">
                <div class="text-[10px] font-bold text-amber-300 flex items-center justify-between">
                    <span>PREVIOUS DAY (STATIC)</span>
                    <span class="text-gray-400 font-mono text-[9px]">${orderFlowLevelsData.prev_date || ''}</span>
                </div>
                ${ydAsks.length > 0 ? ydAsks.map(a => renderItemRow(a, 'ask', true)).join('') : '<div class="text-gray-500 text-[10px]">No YD Resistance</div>'}
                ${ydBids.length > 0 ? ydBids.map(b => renderItemRow(b, 'bid', true)).join('') : '<div class="text-gray-500 text-[10px]">No YD Support</div>'}
            </div>
        `;

        const tdAsks = orderFlowLevelsData.today?.asks || [];
        const tdBids = orderFlowLevelsData.today?.bids || [];
        html += `
            <div class="p-2 rounded bg-[#1a1e29] border border-gray-800 space-y-1">
                <div class="text-[10px] font-bold text-emerald-300 flex items-center justify-between">
                    <span>TODAY LIVE (DYNAMIC)</span>
                    <span class="text-gray-400 font-mono text-[9px]">${orderFlowLevelsData.date || ''}</span>
                </div>
                ${tdAsks.length > 0 ? tdAsks.map(a => renderItemRow(a, 'ask', false)).join('') : '<div class="text-gray-500 text-[10px]">No TD Resistance yet</div>'}
                ${tdBids.length > 0 ? tdBids.map(b => renderItemRow(b, 'bid', false)).join('') : '<div class="text-gray-500 text-[10px]">No TD Support yet</div>'}
            </div>
        `;

        container.innerHTML = html;
    }
    window.renderOfLevelsPreview = renderOfLevelsPreview;

    // ── Multi-Day Top 1 Order Flow Support & Resistance Levels (Past 5 Days) ──
    const HIST_OF_COLORS = {
        1: { res: '#ef4444', sup: '#10b981', style: 2, labelStyle: 'Dashed' }, // YD (D-1)
        2: { res: '#fb923c', sup: '#2dd4bf', style: 1, labelStyle: 'Dotted' }, // D-2 (Orange / Teal)
        3: { res: '#f59e0b', sup: '#06b6d4', style: 1, labelStyle: 'Dotted' }, // D-3 (Amber / Cyan)
        4: { res: '#eab308', sup: '#38bdf8', style: 1, labelStyle: 'Dotted' }, // D-4 (Yellow / Sky)
        5: { res: '#d97706', sup: '#0ea5e9', style: 1, labelStyle: 'Dotted' }, // D-5 (Dark Amber / Deep Sky)
    };

    function clearMultiDayOrderFlowLines() {
        if (wCandleSeries && histOfPriceLinesWeightage.length > 0) {
            histOfPriceLinesWeightage.forEach(line => {
                try { wCandleSeries.removePriceLine(line); } catch (e) {}
            });
            histOfPriceLinesWeightage = [];
        }
        if (oCandleSeries && histOfPriceLinesOptions.length > 0) {
            histOfPriceLinesOptions.forEach(line => {
                try { oCandleSeries.removePriceLine(line); } catch (e) {}
            });
            histOfPriceLinesOptions = [];
        }
    }
    window.clearMultiDayOrderFlowLines = clearMultiDayOrderFlowLines;

    function isLevelSelected(date, type, chartBasis = null) {
        const key = `${date}_${type}`;
        if (multiDayOfSelected[key] !== undefined) {
            return !!multiDayOfSelected[key];
        }
        // Backward compatibility migration from old key format: `${date}_ask` / `${date}_bid`
        const isSpot = type.startsWith('spot');
        const side = type.endsWith('ask') ? 'ask' : 'bid';
        const oldKey = `${date}_${side}`;
        const activeMode = chartBasis || ofCalibrationMode;
        if (multiDayOfSelected[oldKey] !== undefined) {
            const wasOn = !!multiDayOfSelected[oldKey];
            if (!wasOn) return false;
            if (activeMode === 'spot') return isSpot;
            if (activeMode === 'future') return !isSpot;
            return true;
        }

        // Defaults if no record exists:
        if (date === 'today') {
            if (activeMode === 'spot') return isSpot;
            if (activeMode === 'future') return !isSpot;
            return true;
        }
        if (multiDayOfData?.days?.length && multiDayOfData.days[0].date === date) {
            if (activeMode === 'spot') return isSpot;
            if (activeMode === 'future') return !isSpot;
            return true;
        }
        return false;
    }
    window.isLevelSelected = isLevelSelected;

    function saveMultiDayOfSelected() {
        try {
            const storageKey = window.location.pathname.includes('banknifty') ? 'banknifty_unified_multiday_of_selected' : 'nifty_unified_multiday_of_selected';
            localStorage.setItem(storageKey, JSON.stringify(multiDayOfSelected));
        } catch (err) {}
    }

    function syncCalibrationModeFromSelections() {
        let hasSpot = false;
        let hasFut = false;
        const check = (d) => {
            if (isLevelSelected(d, 'spot_ask') || isLevelSelected(d, 'spot_bid')) hasSpot = true;
            if (isLevelSelected(d, 'fut_ask') || isLevelSelected(d, 'fut_bid')) hasFut = true;
        };
        check('today');
        if (multiDayOfData?.days) multiDayOfData.days.forEach(day => check(day.date));

        if (hasSpot && hasFut) {
            ofCalibrationMode = 'both';
        } else if (hasSpot) {
            ofCalibrationMode = 'spot';
        } else if (hasFut) {
            ofCalibrationMode = 'future';
        }
        updateOfBasisButtonsUI();
    }

    function drawMultiDayOrderFlowLines() {
        clearMultiDayOrderFlowLines();
        if (!multiDayOfData || !multiDayOfData.days || !Array.isArray(multiDayOfData.days)) {
            updateHistOfBadgeCount();
            return;
        }

        multiDayOfData.days.forEach(day => {
            const colors = HIST_OF_COLORS[day.day_offset] || HIST_OF_COLORS[5];
            const askItem = day.ask;
            const bidItem = day.bid;

            if (askItem) {
                // Spot Ask (RES)
                if (isLevelSelected(day.date, 'spot_ask', wChartBasis) && wShowOfSrLines && wCandleSeries) {
                    createSpecificOfLine(wCandleSeries, askItem, 'ask', false, day.formatted_date, colors, histOfPriceLinesWeightage, false);
                }
                if (isLevelSelected(day.date, 'spot_ask', oChartBasis) && oShowOfSrLines && oCandleSeries) {
                    createSpecificOfLine(oCandleSeries, askItem, 'ask', false, day.formatted_date, colors, histOfPriceLinesOptions, false);
                }
                // Fut Ask (RES)
                if (isLevelSelected(day.date, 'fut_ask', wChartBasis) && wShowOfSrLines && wCandleSeries) {
                    createSpecificOfLine(wCandleSeries, askItem, 'ask', true, day.formatted_date, colors, histOfPriceLinesWeightage, false);
                }
                if (isLevelSelected(day.date, 'fut_ask', oChartBasis) && oShowOfSrLines && oCandleSeries) {
                    createSpecificOfLine(oCandleSeries, askItem, 'ask', true, day.formatted_date, colors, histOfPriceLinesOptions, false);
                }
            }

            if (bidItem) {
                // Spot Bid (SUP)
                if (isLevelSelected(day.date, 'spot_bid', wChartBasis) && wShowOfSrLines && wCandleSeries) {
                    createSpecificOfLine(wCandleSeries, bidItem, 'bid', false, day.formatted_date, colors, histOfPriceLinesWeightage, false);
                }
                if (isLevelSelected(day.date, 'spot_bid', oChartBasis) && oShowOfSrLines && oCandleSeries) {
                    createSpecificOfLine(oCandleSeries, bidItem, 'bid', false, day.formatted_date, colors, histOfPriceLinesOptions, false);
                }
                // Fut Bid (SUP)
                if (isLevelSelected(day.date, 'fut_bid', wChartBasis) && wShowOfSrLines && wCandleSeries) {
                    createSpecificOfLine(wCandleSeries, bidItem, 'bid', true, day.formatted_date, colors, histOfPriceLinesWeightage, false);
                }
                if (isLevelSelected(day.date, 'fut_bid', oChartBasis) && oShowOfSrLines && oCandleSeries) {
                    createSpecificOfLine(oCandleSeries, bidItem, 'bid', true, day.formatted_date, colors, histOfPriceLinesOptions, false);
                }
            }
        });

        updateHistOfBadgeCount();
    }
    window.drawMultiDayOrderFlowLines = drawMultiDayOrderFlowLines;

    function updateHistOfBadgeCount() {
        let count = 0;
        let hasSpot = false;
        let hasFut = false;

        const countDay = (date) => {
            if (isLevelSelected(date, 'spot_ask')) { count++; hasSpot = true; }
            if (isLevelSelected(date, 'spot_bid')) { count++; hasSpot = true; }
            if (isLevelSelected(date, 'fut_ask')) { count++; hasFut = true; }
            if (isLevelSelected(date, 'fut_bid')) { count++; hasFut = true; }
        };

        countDay('today');
        if (multiDayOfData && multiDayOfData.days) {
            multiDayOfData.days.forEach(d => countDay(d.date));
        }

        ['w-hist-of-count-badge', 'o-hist-of-count-badge'].forEach(id => {
            const b = document.getElementById(id);
            if (b) {
                b.textContent = count;
                b.className = count > 0 
                    ? 'px-1 py-0 rounded text-[8.5px] bg-amber-500 text-black font-extrabold font-mono'
                    : 'px-1 py-0 rounded text-[8.5px] bg-amber-500/30 text-amber-200 font-mono';
            }
        });

        const basisText = (hasSpot && hasFut) ? 'SPOT & FUT' : (hasFut ? 'FUT' : (hasSpot ? 'SPOT' : 'OFF'));
        ['w-of-calib-badge', 'o-of-calib-badge'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = basisText;
        });
    }

    async function fetchMultiDayOrderFlowLevels() {
        const symbol = typeof SYMBOL !== 'undefined' ? SYMBOL : (window.location.pathname.includes('banknifty') ? 'BANKNIFTY' : 'NIFTY');
        const spotEl = document.getElementById('spot-price-display');
        let curSpot = 0;
        if (spotEl && spotEl.textContent) {
            const raw = parseFloat(spotEl.textContent.replace(/[^0-9.]/g, ''));
            if (!isNaN(raw) && raw > 0) curSpot = raw;
        }
        if (!curSpot && typeof trendingOiData !== 'undefined' && trendingOiData?.underlying_data?.spot) {
            curSpot = parseFloat(trendingOiData.underlying_data.spot);
        }

        try {
            const spotParam = curSpot > 0 ? `&spot=${curSpot}` : '';
            const res = await fetchJsonSafe(`/api/multi-timeframe/multi-day-order-flow-levels?date=${selectedDate}&symbol=${symbol}&days=5${spotParam}&_t=${Date.now()}`);
            if (res && res.success && res.days) {
                multiDayOfData = res;
                // Default selections if completely empty
                if (Object.keys(multiDayOfSelected).length === 0 && res.days.length > 0) {
                    const yd = res.days[0];
                    multiDayOfSelected['today_spot_ask'] = true;
                    multiDayOfSelected['today_spot_bid'] = true;
                    multiDayOfSelected['today_fut_ask'] = (ofCalibrationMode === 'both' || ofCalibrationMode === 'future');
                    multiDayOfSelected['today_fut_bid'] = (ofCalibrationMode === 'both' || ofCalibrationMode === 'future');
                    multiDayOfSelected[`${yd.date}_spot_ask`] = true;
                    multiDayOfSelected[`${yd.date}_spot_bid`] = true;
                    multiDayOfSelected[`${yd.date}_fut_ask`] = (ofCalibrationMode === 'both' || ofCalibrationMode === 'future');
                    multiDayOfSelected[`${yd.date}_fut_bid`] = (ofCalibrationMode === 'both' || ofCalibrationMode === 'future');
                    saveMultiDayOfSelected();
                }
                renderHistOfDropdownContent();
                drawMultiDayOrderFlowLines();
            }
        } catch (e) {
            console.warn('Could not fetch Multi-Day Order Flow levels:', e);
        }
    }
    window.fetchMultiDayOrderFlowLevels = fetchMultiDayOrderFlowLevels;

    function toggleHistOfLevel(date, type, e) {
        if (e && e.stopPropagation) e.stopPropagation();
        const current = isLevelSelected(date, type);
        multiDayOfSelected[`${date}_${type}`] = !current;
        saveMultiDayOfSelected();
        syncCalibrationModeFromSelections();

        renderHistOfDropdownContent();
        drawWeightageOrderFlowLines();
        drawOptionsOrderFlowLines();
        drawMultiDayOrderFlowLines();
        updateHistOfBadgeCount();
    }
    window.toggleHistOfLevel = toggleHistOfLevel;

    function toggleHistOfDay(date, e) {
        if (e && e.stopPropagation) e.stopPropagation();
        const anyOn = isLevelSelected(date, 'spot_ask') || isLevelSelected(date, 'spot_bid') || isLevelSelected(date, 'fut_ask') || isLevelSelected(date, 'fut_bid');
        const newState = !anyOn;

        if (!newState) {
            multiDayOfSelected[`${date}_spot_ask`] = false;
            multiDayOfSelected[`${date}_spot_bid`] = false;
            multiDayOfSelected[`${date}_fut_ask`] = false;
            multiDayOfSelected[`${date}_fut_bid`] = false;
        } else {
            const enableSpot = (ofCalibrationMode === 'spot' || ofCalibrationMode === 'both');
            const enableFut = (ofCalibrationMode === 'future' || ofCalibrationMode === 'both');
            multiDayOfSelected[`${date}_spot_ask`] = enableSpot;
            multiDayOfSelected[`${date}_spot_bid`] = enableSpot;
            multiDayOfSelected[`${date}_fut_ask`] = enableFut;
            multiDayOfSelected[`${date}_fut_bid`] = enableFut;
        }
        saveMultiDayOfSelected();
        syncCalibrationModeFromSelections();

        renderHistOfDropdownContent();
        drawWeightageOrderFlowLines();
        drawOptionsOrderFlowLines();
        drawMultiDayOrderFlowLines();
        updateHistOfBadgeCount();
    }
    window.toggleHistOfDay = toggleHistOfDay;

    function selectAllHistOf(mode, e) {
        if (e && e.stopPropagation) e.stopPropagation();
        const setDayLevels = (date) => {
            if (mode === 'all') {
                multiDayOfSelected[`${date}_spot_ask`] = true;
                multiDayOfSelected[`${date}_spot_bid`] = true;
                multiDayOfSelected[`${date}_fut_ask`] = true;
                multiDayOfSelected[`${date}_fut_bid`] = true;
            } else if (mode === 'none') {
                multiDayOfSelected[`${date}_spot_ask`] = false;
                multiDayOfSelected[`${date}_spot_bid`] = false;
                multiDayOfSelected[`${date}_fut_ask`] = false;
                multiDayOfSelected[`${date}_fut_bid`] = false;
            } else if (mode === 'res') {
                const enableSpot = (ofCalibrationMode !== 'future');
                const enableFut = (ofCalibrationMode !== 'spot');
                multiDayOfSelected[`${date}_spot_ask`] = enableSpot;
                multiDayOfSelected[`${date}_fut_ask`] = enableFut;
                multiDayOfSelected[`${date}_spot_bid`] = false;
                multiDayOfSelected[`${date}_fut_bid`] = false;
            } else if (mode === 'sup') {
                const enableSpot = (ofCalibrationMode !== 'future');
                const enableFut = (ofCalibrationMode !== 'spot');
                multiDayOfSelected[`${date}_spot_ask`] = false;
                multiDayOfSelected[`${date}_fut_ask`] = false;
                multiDayOfSelected[`${date}_spot_bid`] = enableSpot;
                multiDayOfSelected[`${date}_fut_bid`] = enableFut;
            }
        };

        setDayLevels('today');
        if (multiDayOfData?.days) {
            multiDayOfData.days.forEach(day => setDayLevels(day.date));
        }
        saveMultiDayOfSelected();
        syncCalibrationModeFromSelections();

        renderHistOfDropdownContent();
        drawWeightageOrderFlowLines();
        drawOptionsOrderFlowLines();
        drawMultiDayOrderFlowLines();
        updateHistOfBadgeCount();
    }
    window.selectAllHistOf = selectAllHistOf;

    function updateOfBasisButtonsUI() {
        ['spot', 'fut', 'both'].forEach(b => {
            const btn = document.getElementById(`of-basis-btn-${b}`);
            if (!btn) return;
            const isMatch = (b === 'fut' && ofCalibrationMode === 'future') || (b === ofCalibrationMode);
            if (isMatch) {
                if (b === 'spot') {
                    btn.className = 'px-2 py-0.5 rounded bg-teal-950 text-teal-300 border border-teal-500/50 shadow-xs font-bold';
                } else if (b === 'fut') {
                    btn.className = 'px-2 py-0.5 rounded bg-amber-950 text-amber-300 border border-amber-500/50 shadow-xs font-bold';
                } else {
                    btn.className = 'px-2 py-0.5 rounded bg-cyan-950 text-cyan-300 border border-cyan-500/50 shadow-xs font-bold';
                }
            } else {
                btn.className = 'px-2 py-0.5 rounded text-gray-400 hover:text-white border border-transparent font-medium';
            }
        });
    }
    window.updateOfBasisButtonsUI = updateOfBasisButtonsUI;

    function updateOfMenuMasterUI() {
        const masterBadge = document.getElementById('of-menu-master-badge');
        const masterBtn = document.getElementById('of-menu-master-btn');
        const isChart1 = (histOfDropdownTrigger === 'w');
        const isVisible = isChart1 ? wShowOfSrLines : oShowOfSrLines;

        if (masterBadge) {
            masterBadge.textContent = isVisible ? 'VISIBLE' : 'HIDDEN';
            masterBadge.className = isVisible 
                ? 'px-1.5 py-0.2 rounded text-[8.5px] font-mono font-bold bg-emerald-950 text-emerald-400 border border-emerald-500/40' 
                : 'px-1.5 py-0.2 rounded text-[8.5px] font-mono font-bold bg-slate-900 text-slate-400 border border-slate-700';
        }
        if (masterBtn) {
            masterBtn.innerHTML = isVisible ? '<span>● Lines: ON</span>' : '<span>○ Lines: OFF</span>';
            masterBtn.className = isVisible 
                ? 'px-2 py-0.5 rounded text-[9.5px] font-bold border transition cursor-pointer bg-emerald-950/80 border-emerald-500/50 text-emerald-300 hover:bg-emerald-900' 
                : 'px-2 py-0.5 rounded text-[9.5px] font-bold border transition cursor-pointer bg-gray-800 border-gray-700 text-gray-400 hover:text-white';
        }
    }
    window.updateOfMenuMasterUI = updateOfMenuMasterUI;

    function toggleActiveChartOfLines(e) {
        if (e && e.stopPropagation) e.stopPropagation();
        if (histOfDropdownTrigger === 'o') {
            oShowOfSrLines = !oShowOfSrLines;
            try { localStorage.setItem('nifty_unified_o_of_sr_lines', oShowOfSrLines ? 'true' : 'false'); } catch (err) {}
            updateOfSrButtonUI();
            drawOptionsOrderFlowLines();
            drawMultiDayOrderFlowLines();
        } else {
            wShowOfSrLines = !wShowOfSrLines;
            try { localStorage.setItem('nifty_unified_w_of_sr_lines', wShowOfSrLines ? 'true' : 'false'); } catch (err) {}
            updateOfSrButtonUI();
            drawWeightageOrderFlowLines();
            drawMultiDayOrderFlowLines();
        }
        updateOfMenuMasterUI();
        updateHistOfBadgeCount();
    }
    window.toggleActiveChartOfLines = toggleActiveChartOfLines;

    function setOfCalibrationWithEvent(mode, e) {
        if (e && e.stopPropagation) e.stopPropagation();
        ofCalibrationMode = mode;
        try {
            const calibKey = window.location.pathname.includes('banknifty') ? 'banknifty_unified_of_calibration' : 'nifty_unified_of_calibration';
            localStorage.setItem(calibKey, mode);
        } catch (err) {}

        const applyModeToDay = (date) => {
            const askActive = isLevelSelected(date, 'spot_ask') || isLevelSelected(date, 'fut_ask');
            const bidActive = isLevelSelected(date, 'spot_bid') || isLevelSelected(date, 'fut_bid');
            if (askActive) {
                multiDayOfSelected[`${date}_spot_ask`] = (mode === 'spot' || mode === 'both');
                multiDayOfSelected[`${date}_fut_ask`] = (mode === 'future' || mode === 'both');
            }
            if (bidActive) {
                multiDayOfSelected[`${date}_spot_bid`] = (mode === 'spot' || mode === 'both');
                multiDayOfSelected[`${date}_fut_bid`] = (mode === 'future' || mode === 'both');
            }
        };
        applyModeToDay('today');
        if (multiDayOfData?.days) {
            multiDayOfData.days.forEach(d => applyModeToDay(d.date));
        }
        saveMultiDayOfSelected();

        updateOfBasisButtonsUI();
        renderHistOfDropdownContent();
        drawWeightageOrderFlowLines();
        drawOptionsOrderFlowLines();
        drawMultiDayOrderFlowLines();
        updateHistOfBadgeCount();
    }
    window.setOfCalibrationWithEvent = setOfCalibrationWithEvent;

    function renderHistOfDropdownContent() {
        const listEl = document.getElementById('hist-of-dropdown-list');
        if (!listEl) return;

        const calibFooter = document.getElementById('hist-of-footer-calib');
        if (calibFooter) {
            const spread = (multiDayOfData?.basis_spread !== undefined) ? multiDayOfData.basis_spread : (orderFlowLevelsData?.basis_spread || 0);
            calibFooter.textContent = `Basis Spread: ${spread >= 0 ? '+' : ''}${spread} pts`;
        }

        updateOfBasisButtonsUI();
        updateOfMenuMasterUI();

        const renderLevelPill = (date, side, isFut, item) => {
            const type = isFut ? `fut_${side}` : `spot_${side}`;
            const selected = isLevelSelected(date, type);
            const isAsk = (side === 'ask');
            const label = isAsk ? 'RES' : 'SUP';

            if (!item) {
                return `
                    <div class="px-1.5 py-1 rounded border border-gray-800/60 bg-gray-900/30 opacity-40 text-left font-mono">
                        <span class="text-[8px] text-gray-500 font-bold">${label}</span>
                        <div class="text-[9px] text-gray-600">--</div>
                    </div>
                `;
            }

            const rawVal = isFut ? (item.raw_price ?? item.price) : (item.calibrated_price ?? item.price);
            const priceStr = Number(rawVal).toLocaleString('en-IN', {minimumFractionDigits: 1, maximumFractionDigits: 1});
            const qtyStr = item.qty ? (item.qty >= 1000 ? (item.qty / 1000).toFixed(1) + 'k' : item.qty) : '--';
            const timeStr = item.time || '';

            let pillClass = '';
            let badgeClass = '';
            if (selected) {
                if (isAsk) {
                    pillClass = 'bg-rose-950/70 border-rose-500 text-rose-100 shadow-xs ring-1 ring-rose-500/40';
                    badgeClass = 'bg-rose-500 text-white font-bold';
                } else {
                    pillClass = 'bg-emerald-950/70 border-emerald-500 text-emerald-100 shadow-xs ring-1 ring-emerald-500/40';
                    badgeClass = 'bg-emerald-500 text-white font-bold';
                }
            } else {
                pillClass = 'bg-[#121620] border-gray-800 text-gray-400 hover:border-gray-700 hover:text-gray-200';
                badgeClass = 'bg-gray-800 text-gray-500 font-semibold';
            }

            const pillTitle = `${date} ${isFut ? 'FUT' : 'SPOT'} ${label}: ₹${priceStr} [${qtyStr}]`;

            return `
                <button type="button" onclick="toggleHistOfLevel('${date}', '${type}', event)"
                    class="px-1.5 py-1 rounded border text-left transition cursor-pointer flex flex-col justify-between ${pillClass}"
                    title="${pillTitle}">
                    <div class="flex items-center justify-between gap-1 w-full">
                        <span class="text-[8.5px] font-bold ${isAsk ? 'text-rose-400' : 'text-emerald-400'}">${label}</span>
                        <span class="text-[7.5px] font-mono px-1 py-0.2 rounded leading-none ${badgeClass}">${selected ? 'ON' : 'OFF'}</span>
                    </div>
                    <div class="font-bold text-[10px] mt-0.5 tracking-tight text-inherit">₹${priceStr}</div>
                    <div class="text-[7.5px] text-gray-400 mt-0.5 truncate flex items-center justify-between w-full font-mono">
                        <span>${qtyStr}</span>
                        ${timeStr ? `<span class="text-gray-500 text-[7px]">${timeStr}</span>` : ''}
                    </div>
                </button>
            `;
        };

        const renderDayCard = (date, title, badgeHtml, rightBadgeHtml, askItem, bidItem, isToday) => {
            const spotAskSelected = isLevelSelected(date, 'spot_ask');
            const spotBidSelected = isLevelSelected(date, 'spot_bid');
            const futAskSelected = isLevelSelected(date, 'fut_ask');
            const futBidSelected = isLevelSelected(date, 'fut_bid');
            const anySelected = (spotAskSelected || spotBidSelected || futAskSelected || futBidSelected);

            const cardBorder = anySelected 
                ? (isToday ? 'border-teal-500/60 bg-teal-950/20' : 'border-amber-500/50 bg-amber-950/15') 
                : 'border-gray-800 bg-[#171b24]';

            return `
                <div class="p-1.5 rounded-xl border transition-all ${cardBorder} space-y-1.5">
                    <!-- Day Header -->
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-1.5 cursor-pointer select-none" onclick="event.stopPropagation()">
                            <input type="checkbox" ${anySelected ? 'checked' : ''} onchange="toggleHistOfDay('${date}', event)" class="rounded accent-teal-500 cursor-pointer">
                            <span class="font-bold text-[11px] ${isToday ? 'text-teal-300' : 'text-gray-200'}">${title}</span>
                            ${badgeHtml}
                        </label>
                        <span class="text-[9px] text-gray-400 font-mono flex items-center gap-1">
                            ${rightBadgeHtml}
                        </span>
                    </div>

                    <!-- Granular 4-Way Controls: Spot & Fut -->
                    <div class="grid grid-cols-2 gap-1.5">
                        <!-- SPOT Sub-panel -->
                        <div class="p-1 rounded-lg bg-[#0e121a] border border-teal-900/40 space-y-1">
                            <div class="flex items-center justify-between px-0.5">
                                <span class="text-[8.5px] font-extrabold text-teal-400 tracking-wider flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-teal-400 inline-block"></span> SPOT
                                </span>
                                <span class="text-[7.5px] text-gray-500">Calibrated</span>
                            </div>
                            <div class="grid grid-cols-2 gap-1 font-mono">
                                ${renderLevelPill(date, 'ask', false, askItem)}
                                ${renderLevelPill(date, 'bid', false, bidItem)}
                            </div>
                        </div>

                        <!-- FUT Sub-panel -->
                        <div class="p-1 rounded-lg bg-[#0e121a] border border-amber-900/40 space-y-1">
                            <div class="flex items-center justify-between px-0.5">
                                <span class="text-[8.5px] font-extrabold text-amber-400 tracking-wider flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400 inline-block"></span> FUT
                                </span>
                                <span class="text-[7.5px] text-gray-500">Raw Futures</span>
                            </div>
                            <div class="grid grid-cols-2 gap-1 font-mono">
                                ${renderLevelPill(date, 'ask', true, askItem)}
                                ${renderLevelPill(date, 'bid', true, bidItem)}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        };

        let html = '';

        // 1. TODAY LIVE DYNAMIC CARD
        const tdAskItem = orderFlowLevelsData?.today?.asks?.[0];
        const tdBidItem = orderFlowLevelsData?.today?.bids?.[0];
        const tdDateStr = orderFlowLevelsData?.date || 'TODAY';
        html += renderDayCard(
            'today', 
            'TODAY LIVE (DYNAMIC)', 
            '<span class="px-1 py-0 rounded text-[7.5px] bg-emerald-500/20 text-emerald-400 font-mono font-bold border border-emerald-500/30">LIVE</span>', 
            `<span class="w-2 h-0.5 inline-block bg-teal-400"></span><span>${tdDateStr}</span>`, 
            tdAskItem, 
            tdBidItem, 
            true
        );

        // 2. PAST 5 DAYS HISTORICAL CARDS
        if (multiDayOfData && multiDayOfData.days && multiDayOfData.days.length > 0) {
            multiDayOfData.days.forEach(day => {
                const colors = HIST_OF_COLORS[day.day_offset] || HIST_OF_COLORS[5];
                const isYd = (day.day_offset === 1);
                const ydBadge = isYd ? '<span class="px-1 py-0 rounded text-[7.5px] bg-amber-500/20 text-amber-300 font-mono font-bold">YD</span>' : '';
                const rightBadge = `<span class="w-2 h-0.5 inline-block" style="background-color: ${colors.res};"></span><span>D-${day.day_offset}</span>`;
                html += renderDayCard(
                    day.date, 
                    `${day.formatted_date} (${day.day_code})`, 
                    ydBadge, 
                    rightBadge, 
                    day.ask, 
                    day.bid, 
                    false
                );
            });
        }

        listEl.innerHTML = html;
        updateHistOfBadgeCount();
    }
    window.renderHistOfDropdownContent = renderHistOfDropdownContent;

    function toggleHistOfDropdown(source, btnEl) {
        const dd = document.getElementById('hist-of-levels-dropdown');
        if (!dd) return;

        if (!dd.classList.contains('hidden') && histOfDropdownTrigger === source) {
            closeHistOfDropdown();
            return;
        }

        histOfDropdownTrigger = source;
        const targetBadge = document.getElementById('of-menu-chart-target');
        if (targetBadge) {
            targetBadge.textContent = (source === 'w') ? 'Chart 1 (5M)' : (source === 'o' ? 'Chart 2 (15M)' : 'Settings Modal');
        }

        updateOfMenuMasterUI();
        updateOfBasisButtonsUI();

        const rect = btnEl.getBoundingClientRect();
        dd.style.top = `${rect.bottom + window.scrollY + 4}px`;
        dd.style.left = `${Math.max(10, Math.min(window.innerWidth - 490, rect.left + window.scrollX))}px`;
        dd.classList.remove('hidden');

        if (!multiDayOfData) {
            fetchMultiDayOrderFlowLevels();
        } else {
            renderHistOfDropdownContent();
        }
    }
    window.toggleHistOfDropdown = toggleHistOfDropdown;

    function closeHistOfDropdown() {
        const dd = document.getElementById('hist-of-levels-dropdown');
        if (dd) dd.classList.add('hidden');
        histOfDropdownTrigger = null;
    }
    window.closeHistOfDropdown = closeHistOfDropdown;

    document.addEventListener('click', function(e) {
        const dd = document.getElementById('hist-of-levels-dropdown');
        if (!dd || dd.classList.contains('hidden')) return;
        const btnW = document.getElementById('w-of-menu-btn');
        const btnO = document.getElementById('o-of-menu-btn');

        // 1. If click is on trigger buttons, ignore (handled by their onclick)
        if ((btnW && btnW.contains(e.target)) || (btnO && btnO.contains(e.target))) {
            return;
        }
        // 2. If click is inside the dropdown or its event path, DO NOT CLOSE
        if (dd.contains(e.target) || (e.composedPath && e.composedPath().includes(dd))) {
            return;
        }
        // 3. Fallback: check bounding client rect of dropdown to guard against replaced DOM elements
        const rect = dd.getBoundingClientRect();
        if (rect && e.clientX >= rect.left && e.clientX <= rect.right && e.clientY >= rect.top && e.clientY <= rect.bottom) {
            return;
        }
        closeHistOfDropdown();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeHistOfDropdown();
        }
    });

    // ── Trending OI & Sentiment Series Visibility (Call, Put, Sentiment, Spot) ───
    let toiSeriesVisibility = {
        call: true,
        put: true,
        sentiment: true,
        spot: true,
    };
    try {
        const savedToiVis = localStorage.getItem('nifty_unified_toi_visibility');
        if (savedToiVis) {
            toiSeriesVisibility = Object.assign(toiSeriesVisibility, JSON.parse(savedToiVis));
        }
    } catch (e) {
        console.warn('Error reading saved TOI visibility:', e);
    }

    function updateToiLegendUI() {
        const configs = [
            { key: 'call', btnId: 'toi-toggle-call', dotId: 'toi-dot-call', activeBtn: 'bg-emerald-950/60 border-emerald-500/50 text-emerald-300 font-semibold hover:brightness-125', activeDot: 'w-1.5 h-1.5 rounded-full bg-emerald-400', label: 'Call OI Change' },
            { key: 'put', btnId: 'toi-toggle-put', dotId: 'toi-dot-put', activeBtn: 'bg-rose-950/60 border-rose-500/50 text-rose-300 font-semibold hover:brightness-125', activeDot: 'w-1.5 h-1.5 rounded-full bg-rose-400', label: 'Put OI Change' },
            { key: 'sentiment', btnId: 'toi-toggle-sentiment', dotId: 'toi-dot-sentiment', activeBtn: 'bg-amber-950/60 border-amber-500/50 text-amber-300 font-semibold hover:brightness-125', activeDot: 'w-1.5 h-1.5 rounded-full bg-amber-400', label: 'Sentiment (Diff)' },
            { key: 'spot', btnId: 'toi-toggle-spot', dotId: 'toi-dot-spot', activeBtn: 'bg-cyan-950/60 border-cyan-500/50 text-cyan-300 font-semibold hover:brightness-125', activeDot: 'w-2 h-0.5 border-t border-dashed border-cyan-400', label: 'Spot Price' },
        ];

        configs.forEach(cfg => {
            const btn = document.getElementById(cfg.btnId);
            const dot = document.getElementById(cfg.dotId);
            const isVisible = toiSeriesVisibility[cfg.key] !== false;
            if (btn) {
                if (isVisible) {
                    btn.className = `px-1.5 py-0.5 rounded border transition flex items-center gap-1 cursor-pointer select-none ${cfg.activeBtn}`;
                    btn.title = `${cfg.label}: Visible (Click to Hide)`;
                } else {
                    btn.className = 'px-1.5 py-0.5 rounded border transition flex items-center gap-1 cursor-pointer select-none opacity-40 text-gray-500 line-through bg-gray-900/60 border-gray-700/60 hover:opacity-75';
                    btn.title = `${cfg.label}: Hidden (Click to Show)`;
                }
            }
            if (dot) {
                if (isVisible) {
                    dot.className = cfg.activeDot;
                } else {
                    dot.className = cfg.key === 'spot'
                        ? 'w-2 h-0.5 border-t border-dashed border-gray-500'
                        : 'w-1.5 h-1.5 rounded-full bg-gray-600';
                }
            }
        });
    }

    function toggleToiSeries(key) {
        if (!toiSeriesVisibility.hasOwnProperty(key)) return;
        toiSeriesVisibility[key] = !toiSeriesVisibility[key];
        try {
            localStorage.setItem('nifty_unified_toi_visibility', JSON.stringify(toiSeriesVisibility));
        } catch (e) {}
        updateToiLegendUI();
        if (toiChartInstance) {
            const keyMap = { call: 0, put: 1, sentiment: 2, spot: 3 };
            const dsIdx = keyMap[key];
            if (dsIdx !== undefined && toiChartInstance.data.datasets && toiChartInstance.data.datasets[dsIdx]) {
                const isVisible = toiSeriesVisibility[key] !== false;
                if (typeof toiChartInstance.setDatasetVisibility === 'function') {
                    toiChartInstance.setDatasetVisibility(dsIdx, isVisible);
                }
                toiChartInstance.data.datasets[dsIdx].hidden = !isVisible;
                if (key === 'spot' && toiChartInstance.options.scales && toiChartInstance.options.scales.y1) {
                    toiChartInstance.options.scales.y1.display = isVisible;
                }
                toiChartInstance.update();
            }
        }
    }
    window.toggleToiSeries = toggleToiSeries;

    // ── Active Basket Strikes Strip (with ATM Highlight & Manual Selection) ───
    let lastRenderedAtmStrike = null;
    let manualToiAtmStrike = null;

    function renderTrendingOiStrikes(activeSpot = null) {
        const listEl = document.getElementById('toi-strikes-list');
        if (!listEl) return;
        if (!trendingOiData || trendingOiData.error) {
            listEl.innerHTML = '<span class="text-gray-500 italic text-[9px]">Strikes unavailable</span>';
            return;
        }

        const rawStrikes = trendingOiData.selected_strikes || [];
        if (!rawStrikes || rawStrikes.length === 0) {
            listEl.innerHTML = '<span class="text-gray-500 italic text-[9px]">Strikes unavailable</span>';
            return;
        }

        const strikes = [...rawStrikes].map(Number).sort((a, b) => a - b);
        const countEl = document.getElementById('toi-strikes-count');
        if (countEl) countEl.textContent = `(${strikes.length})`;

        // Determine ATM strike based on manual override, spot, or API data
        const strikeStep = 50;
        let spotVal = (activeSpot !== null && Number(activeSpot) > 0)
            ? Number(activeSpot)
            : (trendingOiData.underlying_data?.spot ? Number(trendingOiData.underlying_data.spot) : null);

        const bMode = alarmSettings.optionsBasketMode || 'fixed';

        let atmStrike = null;
        if (manualToiAtmStrike !== null && manualToiAtmStrike > 0) {
            atmStrike = manualToiAtmStrike;
        } else if (bMode === 'fixed' && trendingOiData.atm_strike) {
            // Static mode: Always anchor to the 9:15 AM morning opening ATM strike returned by API
            atmStrike = Number(trendingOiData.atm_strike);
        } else if (spotVal && spotVal > 0) {
            // Dynamic mode: calculate current floating ATM based on live spot
            atmStrike = Math.round(spotVal / strikeStep) * strikeStep;
        } else if (trendingOiData.atm_strike) {
            atmStrike = Number(trendingOiData.atm_strike);
        }

        // If atmStrike not in strikes, find closest in strikes
        if (atmStrike !== null && !strikes.includes(atmStrike)) {
            let closest = strikes[0];
            let minDiff = Math.abs(closest - atmStrike);
            for (let i = 1; i < strikes.length; i++) {
                const diff = Math.abs(strikes[i] - atmStrike);
                if (diff < minDiff) {
                    minDiff = diff;
                    closest = strikes[i];
                }
            }
            atmStrike = closest;
        }

        // Update ATM Mode Indicator (Auto vs Manual)
        const indicator = document.getElementById('toi-atm-mode-indicator');
        if (indicator) {
            if (manualToiAtmStrike !== null) {
                indicator.className = 'flex items-center gap-1';
                indicator.innerHTML = `
                    <span class="px-1 py-0.2 rounded bg-amber-500/20 text-amber-300 border border-amber-500/40 text-[8px] font-mono font-bold" title="Manual ATM strike active">MANUAL</span>
                    <button type="button" onclick="resetToiManualAtm()" class="px-1 py-0.2 rounded bg-cyan-950 text-cyan-300 hover:bg-cyan-900 border border-cyan-500/40 text-[8px] font-mono font-bold cursor-pointer" title="Reset to Auto Spot ATM">🔄 AUTO</button>
                `;
            } else {
                indicator.className = 'hidden';
                indicator.innerHTML = '';
            }
        }

        // Avoid unnecessary re-rendering if ATM hasn't changed and list is already populated
        if (atmStrike === lastRenderedAtmStrike && listEl.children.length === strikes.length && !listEl.querySelector('.text-gray-500')) {
            return;
        }
        lastRenderedAtmStrike = atmStrike;

        listEl.innerHTML = strikes.map(s => {
            const isAtm = (atmStrike !== null && s === atmStrike);
            const isManual = (manualToiAtmStrike !== null && s === manualToiAtmStrike);
            if (isAtm) {
                const atmBadge = isManual ? 'MANUAL' : (bMode === 'fixed' ? 'STATIC' : 'DYNAMIC');
                const atmBadgeBg = isManual ? 'bg-amber-300 text-slate-950 font-black' : (bMode === 'fixed' ? 'bg-amber-400 text-slate-950 font-black' : 'bg-cyan-400 text-slate-950 font-black');
                return `<button type="button" onclick="selectManualAtmStrike(${s})" class="toi-atm-pill px-1.5 py-0.5 rounded bg-amber-500/20 border border-amber-400 text-amber-300 font-black text-[9px] font-mono shadow-[0_0_8px_rgba(251,191,36,0.35)] flex items-center gap-1 shrink-0 ring-1 ring-amber-400/40 whitespace-nowrap cursor-pointer hover:bg-amber-500/30 transition-all" title="${isManual ? 'Manual ATM active. Click to reset to Auto' : (bMode === 'fixed' ? 'Static 9:15 AM Market Opening ATM Strike (Locked all day)' : 'Dynamic Floating ATM Strike (Auto-tracks live spot)')}"><span class="text-amber-400">★</span> ${s.toLocaleString('en-IN')} <span class="text-[7px] ${atmBadgeBg} px-1 rounded uppercase tracking-wider">${atmBadge}</span></button>`;
            } else {
                return `<button type="button" onclick="selectManualAtmStrike(${s})" class="px-1.5 py-0.5 rounded bg-slate-900/90 border border-slate-700/60 text-slate-300 hover:text-amber-300 hover:border-amber-500/60 hover:bg-slate-800 font-semibold text-[9px] font-mono shrink-0 select-none transition-all whitespace-nowrap cursor-pointer" title="Click to set ₹${s.toLocaleString('en-IN')} as ATM Strike & recalibrate Trending OI">${s.toLocaleString('en-IN')}</button>`;
            }
        }).join('');

        // Smoothly center the ATM pill within the horizontal strip
        requestAnimationFrame(() => {
            const atmPill = listEl.querySelector('.toi-atm-pill');
            if (atmPill) {
                atmPill.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
            }
        });
    }
    window.renderTrendingOiStrikes = renderTrendingOiStrikes;

    function selectManualAtmStrike(strike) {
        if (manualToiAtmStrike === Number(strike)) {
            resetToiManualAtm();
            return;
        }
        setManualToiAtmStrike(strike);
    }
    window.selectManualAtmStrike = selectManualAtmStrike;

    function setManualToiAtmStrike(strike) {
        manualToiAtmStrike = Number(strike);
        lastRenderedAtmStrike = null;
        reloadTrendingOiWithInterval(currentTfMinutes || 5);
    }
    window.setManualToiAtmStrike = setManualToiAtmStrike;

    function resetToiManualAtm() {
        manualToiAtmStrike = null;
        lastRenderedAtmStrike = null;
        reloadTrendingOiWithInterval(currentTfMinutes || 5);
    }
    window.resetToiManualAtm = resetToiManualAtm;

    function stepManualAtmStrike(dir) {
        const strikeStep = 50;
        const currentAtm = (manualToiAtmStrike !== null) 
            ? manualToiAtmStrike 
            : (lastRenderedAtmStrike || (trendingOiData?.underlying_data?.spot ? Math.round(trendingOiData.underlying_data.spot / strikeStep) * strikeStep : null));
        if (!currentAtm) return;
        const newAtm = currentAtm + (dir * strikeStep);
        setManualToiAtmStrike(newAtm);
    }
    window.stepManualAtmStrike = stepManualAtmStrike;

    function setStrikeBasketMode(mode) {
        alarmSettings.optionsBasketMode = mode;
        try {
            localStorage.setItem('nifty_unified_alarm_settings', JSON.stringify(alarmSettings));
        } catch (e) {}

        syncStrikeBasketModeUI();

        // Clear manual strike override so mode takes effect
        manualToiAtmStrike = null;
        lastRenderedAtmStrike = null;

        // Immediately reload Trending OI and Options Breadth
        reloadTrendingOiWithInterval(currentTfMinutes || 5);
        if (typeof reloadChart1Weightage === 'function') reloadChart1Weightage(true);
        if (typeof reloadChart2Options === 'function') reloadChart2Options(true);
        if (typeof renderFlowSynergy === 'function') renderFlowSynergy();
    }
    window.setStrikeBasketMode = setStrikeBasketMode;

    function syncStrikeBasketModeUI() {
        const mode = alarmSettings.optionsBasketMode || 'fixed';
        const btnFixed = document.getElementById('toi-basket-fixed');
        const btnDynamic = document.getElementById('toi-basket-dynamic');
        if (btnFixed && btnDynamic) {
            if (mode === 'fixed') {
                btnFixed.className = 'px-1.5 py-0.5 rounded transition flex items-center gap-0.5 cursor-pointer bg-amber-600 text-white shadow-xs';
                btnDynamic.className = 'px-1.5 py-0.5 rounded transition flex items-center gap-0.5 cursor-pointer text-slate-400 hover:text-white';
            } else {
                btnFixed.className = 'px-1.5 py-0.5 rounded transition flex items-center gap-0.5 cursor-pointer text-slate-400 hover:text-white';
                btnDynamic.className = 'px-1.5 py-0.5 rounded transition flex items-center gap-0.5 cursor-pointer bg-cyan-600 text-white shadow-xs';
            }
        }

        const oBasketSelect = document.getElementById('cfg-options-basket-mode');
        if (oBasketSelect && oBasketSelect.value !== mode) {
            oBasketSelect.value = mode;
        }

        const oBasketPill = document.getElementById('cfg-basket-mode-pill');
        if (oBasketPill) {
            const isDyn = (mode === 'dynamic');
            oBasketPill.textContent = isDyn ? 'Dynamic Floating' : 'Fixed Morning';
            oBasketPill.className = isDyn
                ? 'text-[9px] px-1.5 py-0.2 rounded font-mono bg-cyan-950 text-cyan-300 border border-cyan-500/40'
                : 'text-[9px] px-1.5 py-0.2 rounded font-mono bg-amber-950 text-amber-300 border border-amber-600/40';
        }
    }
    window.syncStrikeBasketModeUI = syncStrikeBasketModeUI;

    function updateTrendingOiDiffBadge(targetIndex = -1) {
        if (!trendingOiData || !trendingOiData.chart) return;
        const chart = trendingOiData.chart;
        const labels = chart.labels || [];
        if (labels.length === 0) return;

        let idx = targetIndex;
        if (idx < 0 || idx >= labels.length) {
            idx = labels.length - 1;
        }

        const ceVal = (chart.call_oi && chart.call_oi[idx] !== undefined) ? Number(chart.call_oi[idx]) : 0;
        const peVal = (chart.put_oi && chart.put_oi[idx] !== undefined) ? Number(chart.put_oi[idx]) : 0;
        const diff = (chart.sentiment && chart.sentiment[idx] !== undefined) ? Number(chart.sentiment[idx]) : (peVal - ceVal);
        const spotVal = (chart.spot && chart.spot[idx] !== undefined && Number(chart.spot[idx]) > 0)
            ? Number(chart.spot[idx])
            : 0;

        const bMode = alarmSettings.optionsBasketMode || 'fixed';
        renderTrendingOiStrikes((bMode === 'dynamic' && spotVal > 0) ? spotVal : null);

        const diffHero = document.getElementById('toi-bottom-diff-hero');
        const diffPctEl = document.getElementById('toi-bottom-diff-pct') || document.getElementById('toi-bottom-diff-raw');
        const dirPill = document.getElementById('toi-bottom-direction-pill');
        const timeEl = document.getElementById('toi-bottom-time');
        const peValEl = document.getElementById('toi-bottom-pe-val');
        const peRawEl = document.getElementById('toi-bottom-pe-raw');
        const ceValEl = document.getElementById('toi-bottom-ce-val');
        const ceRawEl = document.getElementById('toi-bottom-ce-raw');
        const ratioValEl = document.getElementById('toi-bottom-ratio-val');
        const biasEl = document.getElementById('toi-bottom-bias');
        const spotValEl = document.getElementById('toi-bottom-spot-val');

        // Format in Lakhs
        const diffInLakh = (diff / 100000).toFixed(1);
        const peInLakh = (peVal / 100000).toFixed(1);
        const ceInLakh = (ceVal / 100000).toFixed(1);
        const sign = diff > 0 ? '+' : '';

        // % Difference between CE and PE
        // Symmetric margin of dominance relative to the dominant strike pool:
        const maxOi = Math.max(Math.abs(peVal), Math.abs(ceVal));
        const diffPctVal = maxOi > 0 ? ((diff / maxOi) * 100) : 0;
        const diffPctSign = diffPctVal > 0 ? '+' : '';
        const diffPctStr = `${diffPctSign}${diffPctVal.toFixed(1)}%`;

        // Total activity skew % (strength)
        const totalOi = Math.abs(peVal) + Math.abs(ceVal);
        const skewPctVal = totalOi > 0 ? ((diff / totalOi) * 100) : 0;
        const skewPctSign = skewPctVal > 0 ? '+' : '';
        const skewPctStr = `${skewPctSign}${skewPctVal.toFixed(1)}%`;

        // Direction & conviction calculation
        const absDiff = Math.abs(diff);
        let dirText = '◆ NEUTRAL CHOP';
        let dirClass = 'bg-slate-900 border-slate-700 text-slate-300';
        let heroColorClass = 'text-slate-300';
        let pctBadgeClass = 'bg-slate-900 border-slate-700 text-slate-400';
        let biasText = 'Balanced';

        if (diff > 0) {
            // PE > CE: Bullish (Put writing dominates -> support / long momentum)
            heroColorClass = 'text-emerald-400 drop-shadow-[0_0_14px_rgba(52,211,153,0.4)]';
            pctBadgeClass = 'bg-emerald-950/80 border-emerald-500/50 text-emerald-300 shadow-[0_0_8px_rgba(16,185,129,0.2)]';
            if (absDiff >= 7500000 || (ceVal > 0 && peVal / ceVal >= 1.6)) {
                dirText = '▲ STRONG BULL';
                dirClass = 'bg-emerald-500/25 border-emerald-500/80 text-emerald-200 font-extrabold ring-1 ring-emerald-500/50';
                biasText = 'Heavy Put Writing';
            } else if (absDiff >= 1500000) {
                dirText = '▲ MILD BULL';
                dirClass = 'bg-emerald-950/70 border-emerald-500/50 text-emerald-300 font-bold';
                biasText = 'Bullish Skew';
            } else {
                dirText = '◆ LEAN BULL';
                dirClass = 'bg-emerald-950/40 border-emerald-600/30 text-emerald-400/80';
                biasText = 'Mild Skew';
            }
        } else if (diff < 0) {
            // CE > PE: Bearish (Call writing dominates -> resistance / short momentum)
            heroColorClass = 'text-rose-400 drop-shadow-[0_0_14px_rgba(251,113,133,0.4)]';
            pctBadgeClass = 'bg-rose-950/80 border-rose-500/50 text-rose-300 shadow-[0_0_8px_rgba(244,63,94,0.2)]';
            if (absDiff >= 7500000 || (peVal > 0 && ceVal / peVal >= 1.6)) {
                dirText = '▼ STRONG BEAR';
                dirClass = 'bg-rose-500/25 border-rose-500/80 text-rose-200 font-extrabold ring-1 ring-rose-500/50';
                biasText = 'Heavy Call Writing';
            } else if (absDiff >= 1500000) {
                dirText = '▼ MILD BEAR';
                dirClass = 'bg-rose-950/70 border-rose-500/50 text-rose-300 font-bold';
                biasText = 'Bearish Skew';
            } else {
                dirText = '◆ LEAN BEAR';
                dirClass = 'bg-rose-950/40 border-rose-600/30 text-rose-400/80';
                biasText = 'Mild Skew';
            }
        }

        const timeLabel = labels[idx] || '';
        const ratioStr = ceVal > 0 ? (peVal / ceVal).toFixed(2) + 'x' : '--';

        if (diffHero) {
            diffHero.textContent = `${sign}${diffInLakh} L`;
            diffHero.className = `text-2xl sm:text-3xl font-black font-mono tracking-tight leading-none ${heroColorClass}`;
            diffHero.title = `[${timeLabel}] Net OI Diff: ${sign}${fmt(diff)} contracts (${diffPctStr}) | Put OI: ${peInLakh}L vs Call OI: ${ceInLakh}L`;
        }
        if (diffPctEl) {
            diffPctEl.textContent = `(${diffPctStr})`;
            diffPctEl.className = `text-xs sm:text-sm font-black font-mono tracking-tight px-1.5 py-0.5 rounded border transition-all cursor-help shadow-xs ${pctBadgeClass}`;
            diffPctEl.title = `[${timeLabel}] % Difference: ${diffPctStr} vs dominant side (${sign}${fmt(diff)} net contracts) | Total OI Skew: ${skewPctStr} | Put: ${peInLakh}L vs Call: ${ceInLakh}L`;
        }
        if (dirPill) {
            dirPill.textContent = dirText;
            dirPill.className = `px-2.5 py-1 rounded-md font-mono font-black text-[10.5px] uppercase border shadow-md tracking-wider ${dirClass}`;
        }
        if (timeEl) {
            timeEl.textContent = timeLabel ? `[${timeLabel}]` : '--:--';
        }
        if (peValEl) peValEl.textContent = `${peInLakh} L`;
        if (peRawEl) peRawEl.textContent = `${fmt(peVal)}`;
        if (ceValEl) ceValEl.textContent = `${ceInLakh} L`;
        if (ceRawEl) ceRawEl.textContent = `${fmt(ceVal)}`;
        if (ratioValEl) ratioValEl.textContent = ratioStr;
        if (biasEl) biasEl.textContent = biasText;
        if (spotValEl) {
            spotValEl.textContent = spotVal > 0 ? `₹${spotVal.toLocaleString('en-IN', {minimumFractionDigits: 1, maximumFractionDigits: 2})}` : '₹--';
        }
    }
    window.updateTrendingOiDiffBadge = updateTrendingOiDiffBadge;

    // ── TRENDING OI REAL-TIME WEBSOCKET STREAMING ENGINE ──
    let toiStreamMode = localStorage.getItem('nifty_unified_toi_stream_mode') || 'live';
    let toiProtobufRoot = null;
    let toiWs = null;
    let toiWsConnecting = false;
    let toiStreamConfig = null;
    let toiTickCount = 0;
    let toiLastTickTime = null;

    async function initToiProtobuf() {
        if (toiProtobufRoot) return;
        try {
            toiProtobufRoot = await protobuf.load('/MarketDataFeed_v3.proto');
            console.log('Trending OI Protobuf loaded successfully.');
        } catch (e) {
            console.error('Failed to load MarketDataFeed_v3.proto for Trending OI:', e);
        }
    }

    function setToiStreamMode(mode) {
        toiStreamMode = mode;
        try {
            localStorage.setItem('nifty_unified_toi_stream_mode', mode);
        } catch(e) {}

        updateToiStreamToggleUI();

        if (mode === 'live') {
            reloadTrendingOiWithInterval(1);
        } else {
            reloadTrendingOiWithInterval(5);
        }
        const todayStr = "{{ \Illuminate\Support\Carbon::now('Asia/Kolkata')->toDateString() }}";
        if (typeof selectedDate !== 'undefined' && selectedDate === todayStr) {
            connectToiWebSocket();
        }
    }
    window.setToiStreamMode = setToiStreamMode;

    function updateToiStreamToggleUI() {
        const btnLive = document.getElementById('toi-mode-live');
        const btn5m = document.getElementById('toi-mode-5m');
        const dot = document.getElementById('toi-stream-dot');
        const label = document.getElementById('toi-stream-label');

        if (toiStreamMode === 'live') {
            if (btnLive) {
                btnLive.className = 'px-1.5 py-0.5 rounded transition flex items-center gap-1 cursor-pointer bg-emerald-600 text-white shadow-xs';
            }
            if (btn5m) {
                btn5m.className = 'px-1.5 py-0.5 rounded transition flex items-center gap-1 cursor-pointer text-slate-400 hover:text-white';
            }
            if (dot) {
                dot.classList.remove('hidden');
                dot.className = (toiWs && toiWs.readyState === WebSocket.OPEN) 
                    ? 'w-1.5 h-1.5 rounded-full bg-white animate-pulse'
                    : 'w-1.5 h-1.5 rounded-full bg-amber-400';
            }
            if (label) {
                label.textContent = (toiTickCount > 0) ? `⚡ Live (${toiTickCount})` : '⚡ Live Stream';
            }
        } else {
            if (btnLive) {
                btnLive.className = 'px-1.5 py-0.5 rounded transition flex items-center gap-1 cursor-pointer text-slate-400 hover:text-white';
            }
            if (btn5m) {
                btn5m.className = 'px-1.5 py-0.5 rounded transition flex items-center gap-1 cursor-pointer bg-blue-600 text-white shadow-xs';
            }
            if (dot) {
                dot.classList.remove('hidden');
                dot.className = (toiWs && toiWs.readyState === WebSocket.OPEN)
                    ? 'w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse'
                    : 'w-1.5 h-1.5 rounded-full bg-amber-400';
            }
            if (label) {
                label.textContent = (toiTickCount > 0) ? `5M (${toiTickCount})` : '5M Stream';
            }
        }
    }
    window.updateToiStreamToggleUI = updateToiStreamToggleUI;

    async function connectToiWebSocket() {
        const todayStr = "{{ \Illuminate\Support\Carbon::now('Asia/Kolkata')->toDateString() }}";
        if (typeof selectedDate !== 'undefined' && selectedDate !== todayStr) return;
        if (toiWsConnecting) return;
        if (toiWs && (toiWs.readyState === WebSocket.OPEN || toiWs.readyState === WebSocket.CONNECTING)) return;
        toiWsConnecting = true;

        await initToiProtobuf();

        try {
            const res = await fetch('/api/trending-oi/ws-url').then(r => r.json());
            const wsUrl = res.authorizedRedirectUri || res.data?.authorizedRedirectUri;
            if (!wsUrl) throw new Error('Authorized WebSocket URI not returned');

            if (toiWs) {
                toiWs.onclose = null;
                toiWs.close();
            }

            toiWs = new WebSocket(wsUrl);
            toiWs.binaryType = 'arraybuffer';

            toiWs.onopen = () => {
                toiWsConnecting = false;
                console.log('Trending OI WebSocket connected.');
                updateToiStreamToggleUI();
                subscribeToiInstruments();
            };

            toiWs.onclose = (e) => {
                toiWsConnecting = false;
                console.warn('Trending OI WebSocket closed, retrying in 5s...', e);
                updateToiStreamToggleUI();
                const todayStr = "{{ \Illuminate\Support\Carbon::now('Asia/Kolkata')->toDateString() }}";
                if (typeof selectedDate !== 'undefined' && selectedDate === todayStr) {
                    setTimeout(connectToiWebSocket, 5000);
                }
            };

            toiWs.onerror = (err) => {
                toiWsConnecting = false;
                console.error('Trending OI WebSocket error:', err);
            };

            toiWs.onmessage = (event) => {
                if (typeof event.data === 'string') return;
                handleToiProtobufFeed(event.data);
            };

        } catch (err) {
            toiWsConnecting = false;
            console.error('Error connecting Trending OI WebSocket:', err);
            updateToiStreamToggleUI();
            const todayStr = "{{ \Illuminate\Support\Carbon::now('Asia/Kolkata')->toDateString() }}";
            if (typeof selectedDate !== 'undefined' && selectedDate === todayStr) {
                setTimeout(connectToiWebSocket, 6000);
            }
        }
    }

    function disconnectToiWebSocket() {
        if (toiWs) {
            toiWs.onclose = null;
            toiWs.close();
            toiWs = null;
        }
        toiWsConnecting = false;
        updateToiStreamToggleUI();
    }

    function subscribeToiInstruments() {
        if (!toiWs || toiWs.readyState !== WebSocket.OPEN) return;
        if (!toiStreamConfig || !toiStreamConfig.subscription_keys || toiStreamConfig.subscription_keys.length === 0) return;

        const subMessage = {
            guid: 'toi_live_' + Date.now(),
            method: 'sub',
            data: {
                mode: 'full',
                instrumentKeys: toiStreamConfig.subscription_keys
            }
        };
        toiWs.send(new TextEncoder().encode(JSON.stringify(subMessage)));
        console.log(`Subscribed ${toiStreamConfig.subscription_keys.length} instruments for Trending OI live stream.`);
    }

    function handleToiProtobufFeed(buffer) {
        if (!toiProtobufRoot || !toiStreamConfig) return;

        try {
            const arr = new Uint8Array(buffer);
            if (arr.length > 0 && arr[0] === 123) return; // JSON text ping

            const FeedResponse = toiProtobufRoot.lookupType('com.upstox.marketdatafeederv3udapi.rpc.proto.FeedResponse');
            const message = FeedResponse.decode(arr);
            const obj = FeedResponse.toObject(message, { enums: String, bytes: String });

            if (!obj || !obj.feeds) return;

            let oiUpdated = false;
            const strikeKeys = toiStreamConfig.strike_keys || {};
            const uKey = toiStreamConfig.underlying_key;

            for (const [key, feed] of Object.entries(obj.feeds)) {
                let feedOi = null;
                let feedLtp = null;

                if (feed.fullFeed && feed.fullFeed.marketFF) {
                    const mFF = feed.fullFeed.marketFF;
                    feedOi = mFF.oi;
                    if (mFF.ltpc) feedLtp = mFF.ltpc.ltp;
                } else if (feed.fullFeed && feed.fullFeed.indexFF) {
                    const iFF = feed.fullFeed.indexFF;
                    if (iFF.ltpc) feedLtp = iFF.ltpc.ltp;
                } else if (feed.indexFF) {
                    if (feed.indexFF.ltpc) feedLtp = feed.indexFF.ltpc.ltp;
                } else if (feed.firstLevelWithGreeks) {
                    feedOi = feed.firstLevelWithGreeks.oi;
                    if (feed.firstLevelWithGreeks.ltpc) feedLtp = feed.firstLevelWithGreeks.ltpc.ltp;
                } else if (feed.ltpc) {
                    feedLtp = feed.ltpc.ltp;
                }

                // Spot index update
                const isSpot = (key === uKey || key === 'NSE_INDEX|Nifty 50' || key.includes('Nifty 50') || key.includes('NIFTY 50'));
                if (isSpot && feedLtp) {
                    if (toiStreamConfig.latest_totals) {
                        toiStreamConfig.latest_totals.spot = feedLtp;
                    }
                    const spotEl = document.getElementById('spot-price-display');
                    if (spotEl) spotEl.textContent = Number(feedLtp).toFixed(2);
                    oiUpdated = true;
                    updateLiveCandleFromTick(feedLtp);
                    renderTrendingOiStrikes(feedLtp);
                }

                // Option strike update
                if (strikeKeys[key]) {
                    const sk = strikeKeys[key];
                    if (feedOi !== null && feedOi !== undefined && feedOi > 0) {
                        sk.oi = feedOi;
                        oiUpdated = true;
                    }
                    if (feedLtp !== null && feedLtp !== undefined && feedLtp > 0) {
                        sk.ltp = feedLtp;
                    }
                }
            }

            if (oiUpdated) {
                toiTickCount++;
                toiLastTickTime = Date.now();
                updateToiStreamLiveValues();
            }

        } catch (e) {
            console.warn('Trending OI protobuf decode error:', e);
        }
    }

    function updateToiStreamLiveValues() {
        if (!toiStreamConfig || !toiStreamConfig.strike_keys || !toiChartInstance) return;

        const strikeKeys = toiStreamConfig.strike_keys;
        const baseTotals = toiStreamConfig.base_totals || { ce_oi: 0, pe_oi: 0 };

        let totalCeOi = 0;
        let totalPeOi = 0;

        for (const k in strikeKeys) {
            const item = strikeKeys[k];
            // Only aggregate ATM strikes that are selected for the Trending OI line chart
            if (item.is_selected !== undefined && !item.is_selected) continue;
            if (item.type === 'CE') {
                totalCeOi += Number(item.oi || 0);
            } else if (item.type === 'PE') {
                totalPeOi += Number(item.oi || 0);
            }
        }

        const liveCallOiChg = totalCeOi - baseTotals.ce_oi;
        const livePutOiChg = totalPeOi - baseTotals.pe_oi;
        const liveSentiment = livePutOiChg - liveCallOiChg;
        const liveSpot = toiStreamConfig.latest_totals?.spot || 0;

        // Update Chart data on active candle
        const chart = toiChartInstance.data;
        if (chart && chart.labels && chart.labels.length > 0) {
            const now = new Date();
            const curTimeStr = now.toLocaleTimeString('en-IN', { hour12: false, hour: '2-digit', minute: '2-digit' });
            const lastIdx = chart.labels.length - 1;
            const lastLabel = chart.labels[lastIdx];

            let targetIdx = lastIdx;
            const lastParts = String(lastLabel || '').split(':');
            const lastMins = (parseInt(lastParts[0], 10) || 0) * 60 + (parseInt(lastParts[1], 10) || 0);
            const curMins = now.getHours() * 60 + now.getMinutes();

            const bucketThreshold = (toiStreamMode === '5m') ? 5 : 1;
            if (curMins > lastMins && (curMins - lastMins) >= bucketThreshold) {
                chart.labels.push(curTimeStr);
                chart.datasets[0].data.push(liveCallOiChg);
                chart.datasets[1].data.push(livePutOiChg);
                chart.datasets[2].data.push(liveSentiment);
                chart.datasets[3].data.push(liveSpot);
                targetIdx = chart.labels.length - 1;

                // When a new 5-minute candle pushes onto the chart, roll forward baseline
                if (bucketThreshold === 5) {
                    for (const k in strikeKeys) {
                        strikeKeys[k].prev_oi = strikeKeys[k].oi;
                        strikeKeys[k].prev_ltp = strikeKeys[k].ltp;
                    }
                }
            } else {
                chart.datasets[0].data[lastIdx] = liveCallOiChg;
                chart.datasets[1].data[lastIdx] = livePutOiChg;
                chart.datasets[2].data[lastIdx] = liveSentiment;
                if (liveSpot > 0) chart.datasets[3].data[lastIdx] = liveSpot;
            }

            // Keep trendingOiData.chart in sync
            if (trendingOiData && trendingOiData.chart) {
                trendingOiData.chart.labels = chart.labels;
                trendingOiData.chart.call_oi = chart.datasets[0].data;
                trendingOiData.chart.put_oi = chart.datasets[1].data;
                trendingOiData.chart.sentiment = chart.datasets[2].data;
                trendingOiData.chart.spot = chart.datasets[3].data;
            }

            toiChartInstance.update('none');

            // Update header badges
            updateTrendingOiDiffBadge(targetIdx);

            // Note: OI Buildup is NOT updated on live ticks anymore.
            // As requested, it strictly updates every 5 minutes based on the completed 5m candle buildup.
            // updateLiveStrikeBuildup();

            // Update label count in toggle
            const lbl = document.getElementById('toi-stream-label');
            if (lbl && toiStreamMode === 'live') {
                lbl.textContent = `⚡ Live (${toiTickCount})`;
            }
        }
    }

    function updateLiveStrikeBuildup() {
        // Disabled: OI Buildup updates strictly every 5 minutes on completed 5m candle, not on live ticks
        return;
    }

    // ── LIVE STREAM CANDLE ENGINE & MARKER DENSITY CONTROLS ──
    function updateLiveCandleFromTick(feedLtp) {
        if (!feedLtp || isNaN(feedLtp) || feedLtp <= 0) return;
        const todayStr = "{{ \Illuminate\Support\Carbon::now('Asia/Kolkata')->toDateString() }}";
        if (typeof selectedDate !== 'undefined' && selectedDate !== todayStr) return;

        const ltp = Number(feedLtp);

        // Update Spot % Change relative to first candle open
        const firstOpen = Number(weightageData?.candles?.[0]?.open || 0);
        if (firstOpen > 0) {
            const diff = ltp - firstOpen;
            const pct = (diff / firstOpen) * 100;
            const sign = diff >= 0 ? '+' : '';
            const chgEl = document.getElementById('spot-change-display');
            if (chgEl) {
                chgEl.textContent = `(${sign}${diff.toFixed(2)} / ${sign}${pct.toFixed(2)}%)`;
                chgEl.className = diff >= 0 ? 'font-semibold text-emerald-400 text-[10px]' : 'font-semibold text-rose-400 text-[10px]';
            }
        }

        // Shift nowSec by +19800 seconds (5.5 hrs) to align with backend's IST-as-UTC timestamp coordinates
        const IST_OFFSET_SEC = 19800;
        const nowSec = Math.floor(Date.now() / 1000) + IST_OFFSET_SEC;
        const currentBucketTime = nowSec - (nowSec % 300);

        // 1. Live stream forming 5m candle on Weightage chart
        if (wCandleSeries && weightageData && weightageData.candles && weightageData.candles.length > 0) {
            const raw = weightageData.candles;
            let lastCandle = raw[raw.length - 1];
            if (lastCandle) {
                const candleTime = Number(lastCandle.time || lastCandle.timestamp);

                // If interval rolled over into a new 5-minute bucket
                if (candleTime > 0 && currentBucketTime > candleTime) {
                    const d = new Date(currentBucketTime * 1000);
                    const timeStr = d.toLocaleTimeString('en-IN', { hour12: false, hour: '2-digit', minute: '2-digit', timeZone: 'UTC' });

                    const newCandle = {
                        ts_at: d.toISOString().replace('T', ' ').substring(0, 19),
                        time_str: timeStr,
                        time: currentBucketTime,
                        timestamp: currentBucketTime,
                        open: ltp,
                        high: ltp,
                        low: ltp,
                        close: ltp,
                        volume: 0,
                        move_pts: 0,
                        move_pct: 0
                    };
                    raw.push(newCandle);

                    // Carry forward previous breadth stats for seamless continuity
                    if (weightageData.breadth_by_time && !weightageData.breadth_by_time[timeStr]) {
                        const prevTime = raw[raw.length - 2]?.time_str;
                        if (prevTime && weightageData.breadth_by_time[prevTime]) {
                            weightageData.breadth_by_time[timeStr] = {
                                ...weightageData.breadth_by_time[prevTime],
                                time: timeStr,
                                nifty_move_pts: 0
                            };
                        }
                    }

                    if (!timestampsList.includes(timeStr)) {
                        timestampsList.push(timeStr);
                        const slider = document.getElementById('master-slider');
                        if (slider) {
                            slider.max = timestampsList.length - 1;
                            if (activeCandleIndex >= timestampsList.length - 2) {
                                slider.value = timestampsList.length - 1;
                                activeCandleIndex = timestampsList.length - 1;
                            }
                        }
                        const masterSelect = document.getElementById('master-time-select');
                        if (masterSelect) {
                            const opt = document.createElement('option');
                            opt.value = timestampsList.length - 1;
                            opt.textContent = timeStr;
                            opt.className = 'bg-[#131722] text-white';
                            masterSelect.appendChild(opt);
                            if (activeCandleIndex >= timestampsList.length - 2) {
                                masterSelect.value = timestampsList.length - 1;
                            }
                        }
                        const wSlider = document.getElementById('w-slider');
                        if (wSlider) {
                            wSlider.max = timestampsList.length - 1;
                            if (activeCandleIndex >= timestampsList.length - 2) wSlider.value = timestampsList.length - 1;
                        }
                        const oSlider = document.getElementById('o-slider');
                        if (oSlider) {
                            oSlider.max = timestampsList.length - 1;
                            if (activeCandleIndex >= timestampsList.length - 2) oSlider.value = timestampsList.length - 1;
                        }
                    }
                    lastCandle = newCandle;
                }

                lastCandle.high = Math.max(Number(lastCandle.high || ltp), ltp);
                lastCandle.low = Math.min(Number(lastCandle.low || ltp), ltp);
                lastCandle.close = ltp;
                lastCandle.move_pts = Number((lastCandle.close - lastCandle.open).toFixed(2));
                lastCandle.move_pct = Number(((lastCandle.move_pts / lastCandle.open) * 100).toFixed(2));

                const liveBar = {
                    time: Number(lastCandle.time || lastCandle.timestamp),
                    open: Number(lastCandle.open),
                    high: Number(lastCandle.high),
                    low: Number(lastCandle.low),
                    close: Number(lastCandle.close),
                };

                if (wCandleStyle === 'heikin_ashi') {
                    if (window._unifiedAllCandles && window._unifiedAllCandles.length > 0) {
                        window._unifiedHaCandles = calculateHeikinAshi(window._unifiedAllCandles);
                        const lastHa = window._unifiedHaCandles[window._unifiedHaCandles.length - 1];
                        if (lastHa) {
                            wCandleSeries.update({
                                time: lastHa.time,
                                open: lastHa.open,
                                high: lastHa.high,
                                low: lastHa.low,
                                close: lastHa.close
                            });
                        }
                    }
                } else {
                    wCandleSeries.update(liveBar);
                }

                if (activeCandleIndex >= timestampsList.length - 1) {
                    const closeEl = document.getElementById('w-chart-close');
                    const chgEl = document.getElementById('w-chart-chg');
                    if (closeEl) closeEl.textContent = ltp.toFixed(2);
                    if (chgEl) {
                        const sign = lastCandle.move_pts >= 0 ? '+' : '';
                        chgEl.textContent = `${sign}${lastCandle.move_pts.toFixed(2)} (${sign}${lastCandle.move_pct.toFixed(2)}%)`;
                        chgEl.className = lastCandle.move_pts >= 0 ? 'font-bold text-emerald-400' : 'font-bold text-rose-400';
                    }
                }
            }
        }

        // 2. Live stream forming 5m candle on Options Breadth chart
        if (oCandleSeries && breadthData && breadthData.nifty_candles && breadthData.nifty_candles.length > 0) {
            const raw = breadthData.nifty_candles;
            let lastCandle = raw[raw.length - 1];
            if (lastCandle) {
                const candleTime = Number(lastCandle.time || lastCandle.timestamp);

                if (candleTime > 0 && currentBucketTime > candleTime) {
                    const d = new Date(currentBucketTime * 1000);
                    const timeStr = d.toLocaleTimeString('en-IN', { hour12: false, hour: '2-digit', minute: '2-digit', timeZone: 'UTC' });

                    const newCandle = {
                        ts_at: d.toISOString().replace('T', ' ').substring(0, 19),
                        time_str: timeStr,
                        time: currentBucketTime,
                        timestamp: currentBucketTime,
                        open: ltp,
                        high: ltp,
                        low: ltp,
                        close: ltp,
                        volume: 0,
                        move_pts: 0,
                        move_pct: 0
                    };
                    raw.push(newCandle);

                    if (breadthData.breadth_by_time && !breadthData.breadth_by_time[timeStr]) {
                        const prevTime = raw[raw.length - 2]?.time_str;
                        if (prevTime && breadthData.breadth_by_time[prevTime]) {
                            breadthData.breadth_by_time[timeStr] = {
                                ...breadthData.breadth_by_time[prevTime],
                                time_str: timeStr,
                                net_points: 0
                            };
                        }
                    }

                    lastCandle = newCandle;
                }

                lastCandle.high = Math.max(Number(lastCandle.high || ltp), ltp);
                lastCandle.low = Math.min(Number(lastCandle.low || ltp), ltp);
                lastCandle.close = ltp;
                lastCandle.move_pts = Number((lastCandle.close - lastCandle.open).toFixed(2));
                lastCandle.move_pct = Number(((lastCandle.move_pts / lastCandle.open) * 100).toFixed(2));

                const liveBar = {
                    time: Number(lastCandle.time || lastCandle.timestamp),
                    open: Number(lastCandle.open),
                    high: Number(lastCandle.high),
                    low: Number(lastCandle.low),
                    close: Number(lastCandle.close),
                };

                if (oCandleStyle === 'heikin_ashi') {
                    if (window._unifiedOptionsCandles && window._unifiedOptionsCandles.length > 0) {
                        window._unifiedOptionsHaCandles = calculateHeikinAshi(window._unifiedOptionsCandles);
                        const lastHa = window._unifiedOptionsHaCandles[window._unifiedOptionsHaCandles.length - 1];
                        if (lastHa) {
                            oCandleSeries.update({
                                time: lastHa.time,
                                open: lastHa.open,
                                high: lastHa.high,
                                low: lastHa.low,
                                close: lastHa.close
                            });
                        }
                    }
                } else {
                    oCandleSeries.update(liveBar);
                }

                if (activeCandleIndex >= timestampsList.length - 1) {
                    const closeEl = document.getElementById('o-chart-close');
                    const moveEl = document.getElementById('o-chart-move');
                    if (closeEl) closeEl.textContent = ltp.toFixed(2);
                    if (moveEl) {
                        const sign = lastCandle.move_pts >= 0 ? '+' : '';
                        moveEl.textContent = `${sign}${lastCandle.move_pts.toFixed(2)} (${sign}${lastCandle.move_pct.toFixed(2)}%)`;
                        moveEl.className = lastCandle.move_pts >= 0 ? 'font-bold text-emerald-400' : 'font-bold text-rose-400';
                    }
                }
            }
        }
    }

    // ── Heikin Ashi Calculation Engine ───────────────────────────────────────────
    function calculateHeikinAshi(candles) {
        if (!candles || candles.length === 0) return [];
        const haCandles = [];
        let prevHa = null;

        for (let i = 0; i < candles.length; i++) {
            const c = candles[i];
            const open = Number(c.open);
            const high = Number(c.high);
            const low = Number(c.low);
            const close = Number(c.close);

            const haClose = (open + high + low + close) / 4;
            let haOpen;
            if (i === 0 || !prevHa) {
                haOpen = (open + close) / 2;
            } else {
                haOpen = (prevHa.open + prevHa.close) / 2;
            }

            const haHigh = Math.max(high, haOpen, haClose);
            const haLow = Math.min(low, haOpen, haClose);

            const haCandle = {
                time: Number(c.time || c.timestamp),
                open: Math.round(haOpen * 100) / 100,
                high: Math.round(haHigh * 100) / 100,
                low: Math.round(haLow * 100) / 100,
                close: Math.round(haClose * 100) / 100,
                time_str: c.time_str,
                real_open: open,
                real_high: high,
                real_low: low,
                real_close: close,
            };

            haCandles.push(haCandle);
            prevHa = haCandle;
        }

        return haCandles;
    }
    window.calculateHeikinAshi = calculateHeikinAshi;

    /**
     * Analyzes Heikin Ashi candle wick structure for high-conviction entries:
     * - Bullish Entry: Green candle with Flat Bottom (Zero Lower Wick: Open == Low)
     * - Bearish Entry: Red candle with Flat Top (Zero Upper Wick: Open == High)
     * - Indecision / Pause: Wicks on BOTH sides (should be ignored)
     */
    function getCandleHaWickAnalysis(candleTime, isOptionsChart = false, timeStr = null) {
        const haList = isOptionsChart ? (window._unifiedOptionsHaCandles || []) : (window._unifiedHaCandles || []);
        if (!haList || haList.length === 0) return null;

        const numTime = Number(candleTime);
        let ha = null;

        // 1. Search backwards (from latest/today's candles to oldest) by exact timestamp
        if (numTime && !isNaN(numTime)) {
            for (let i = haList.length - 1; i >= 0; i--) {
                if (Number(haList[i].time) === numTime) {
                    ha = haList[i];
                    break;
                }
            }
        }

        // 2. Fallback: Search backwards by time_str (HH:mm) so today's candle is matched, not yesterday's
        if (!ha && timeStr) {
            for (let i = haList.length - 1; i >= 0; i--) {
                if (haList[i].time_str === timeStr) {
                    ha = haList[i];
                    break;
                }
            }
        }

        if (!ha) return null;

        const isHaBull = ha.close >= ha.open;
        const bodyTop = Math.max(ha.open, ha.close);
        const bodyBottom = Math.min(ha.open, ha.close);

        const topWick = Number((ha.high - bodyTop).toFixed(2));
        const bottomWick = Number((bodyBottom - ha.low).toFixed(2));

        // Tolerance of 0.15 points (handles floating point precision on index prices)
        const hasTopWick = topWick > 0.15;
        const hasBottomWick = bottomWick > 0.15;
        const hasBothWicks = hasTopWick && hasBottomWick;

        // Clean Bull entry: green Heikin Ashi candle with FLAT BOTTOM (open == low, no lower wick)
        const isCleanBullEntry = isHaBull && !hasBottomWick;

        // Clean Bear entry: red Heikin Ashi candle with FLAT TOP (open == high, no upper wick)
        const isCleanBearEntry = !isHaBull && !hasTopWick;

        return {
            ha,
            isHaBull,
            topWick,
            bottomWick,
            hasTopWick,
            hasBottomWick,
            hasBothWicks,
            isCleanBullEntry,
            isCleanBearEntry
        };
    }
    window.getCandleHaWickAnalysis = getCandleHaWickAnalysis;

    function toggleChart1StocksSignal() {
        alarmSettings.chart1ShowStocks = (alarmSettings.chart1ShowStocks === false);
        saveAlarmSettings();
        updateChartSignalButtonsUI();
        renderWeightageColumn();
    }
    window.toggleChart1StocksSignal = toggleChart1StocksSignal;

    function toggleChart1OptionsSignal() {
        alarmSettings.chart1ShowOptions = !alarmSettings.chart1ShowOptions;
        saveAlarmSettings();
        updateChartSignalButtonsUI();
        renderWeightageColumn();
    }
    window.toggleChart1OptionsSignal = toggleChart1OptionsSignal;

    function toggleChart2StocksSignal() {
        alarmSettings.chart2ShowStocks = !alarmSettings.chart2ShowStocks;
        saveAlarmSettings();
        updateChartSignalButtonsUI();
        renderOptionsBreadthColumn();
    }
    window.toggleChart2StocksSignal = toggleChart2StocksSignal;

    function toggleChart2OptionsSignal() {
        alarmSettings.chart2ShowOptions = (alarmSettings.chart2ShowOptions === false);
        saveAlarmSettings();
        updateChartSignalButtonsUI();
        renderOptionsBreadthColumn();
    }
    window.toggleChart2OptionsSignal = toggleChart2OptionsSignal;

    function cycleOptionsTriggerThreshold() {
        const current = alarmSettings.optionsUpThreshold || 70;
        const next = (current === 65) ? 70 : ((current === 70) ? 75 : ((current === 75) ? 80 : 65));
        alarmSettings.optionsUpThreshold = next;
        alarmSettings.optionsDownThreshold = next;
        const inUp = document.getElementById('options-up-input');
        const inDown = document.getElementById('options-down-input');
        const slUp = document.getElementById('options-up-slider');
        const slDown = document.getElementById('options-down-slider');
        if (inUp) inUp.value = next;
        if (inDown) inDown.value = next;
        if (slUp) slUp.value = next;
        if (slDown) slDown.value = next;
        saveAlarmSettings();
        renderOptionsBreadthColumn();
        renderWeightageColumn();
    }
    window.cycleOptionsTriggerThreshold = cycleOptionsTriggerThreshold;

    function cycleStocksTriggerThreshold() {
        const current = alarmSettings.stocksUpThreshold || 80;
        const next = (current === 75) ? 80 : ((current === 80) ? 85 : 75);
        alarmSettings.stocksUpThreshold = next;
        alarmSettings.stocksDownThreshold = next;
        const inUp = document.getElementById('stocks-up-input');
        const inDown = document.getElementById('stocks-down-input');
        const slUp = document.getElementById('stocks-up-slider');
        const slDown = document.getElementById('stocks-down-slider');
        if (inUp) inUp.value = next;
        if (inDown) inDown.value = next;
        if (slUp) slUp.value = next;
        if (slDown) slDown.value = next;
        saveAlarmSettings();
        renderOptionsBreadthColumn();
        renderWeightageColumn();
    }
    window.cycleStocksTriggerThreshold = cycleStocksTriggerThreshold;

    function updateChartSignalButtonsUI() {
        const c1Stk = (alarmSettings.chart1ShowStocks !== false);
        const c1Opt = (alarmSettings.chart1ShowOptions === true);
        const c2Stk = (alarmSettings.chart2ShowStocks === true);
        const c2Opt = (alarmSettings.chart2ShowOptions !== false);

        // Chart 1 buttons
        const b1Stk = document.getElementById('w-stk-signal-badge');
        const btn1Stk = document.getElementById('w-stk-signal-btn');
        if (b1Stk) {
            b1Stk.textContent = c1Stk ? 'ON' : 'OFF';
            b1Stk.className = c1Stk ? 'font-mono text-emerald-400 font-extrabold' : 'font-mono text-gray-500 font-bold';
        }
        if (btn1Stk) {
            btn1Stk.className = c1Stk 
                ? 'ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 cursor-pointer bg-blue-950/70 border-blue-500/50 text-blue-300 hover:bg-blue-900'
                : 'ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 cursor-pointer bg-gray-800 border-gray-600 text-gray-400 hover:text-white';
        }

        const b1Opt = document.getElementById('w-opt-signal-badge');
        const btn1Opt = document.getElementById('w-opt-signal-btn');
        if (b1Opt) {
            b1Opt.textContent = c1Opt ? 'ON' : 'OFF';
            b1Opt.className = c1Opt ? 'font-mono text-purple-400 font-extrabold' : 'font-mono text-gray-500 font-bold';
        }
        if (btn1Opt) {
            btn1Opt.className = c1Opt 
                ? 'ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 cursor-pointer bg-purple-950/70 border-purple-500/50 text-purple-300 hover:bg-purple-900'
                : 'ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 cursor-pointer bg-gray-800 border-gray-600 text-gray-400 hover:text-white';
        }

        // Chart 2 buttons
        const b2Stk = document.getElementById('o-stk-signal-badge');
        const btn2Stk = document.getElementById('o-stk-signal-btn');
        if (b2Stk) {
            b2Stk.textContent = c2Stk ? 'ON' : 'OFF';
            b2Stk.className = c2Stk ? 'font-mono text-emerald-400 font-extrabold' : 'font-mono text-gray-500 font-bold';
        }
        if (btn2Stk) {
            btn2Stk.className = c2Stk 
                ? 'ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 cursor-pointer bg-blue-950/70 border-blue-500/50 text-blue-300 hover:bg-blue-900'
                : 'ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 cursor-pointer bg-gray-800 border-gray-600 text-gray-400 hover:text-white';
        }

        const b2Opt = document.getElementById('o-opt-signal-badge');
        const btn2Opt = document.getElementById('o-opt-signal-btn');
        if (b2Opt) {
            b2Opt.textContent = c2Opt ? 'ON' : 'OFF';
            b2Opt.className = c2Opt ? 'font-mono text-purple-400 font-extrabold' : 'font-mono text-gray-500 font-bold';
        }
        if (btn2Opt) {
            btn2Opt.className = c2Opt 
                ? 'ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 cursor-pointer bg-purple-950/70 border-purple-500/50 text-purple-300 hover:bg-purple-900'
                : 'ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 cursor-pointer bg-gray-800 border-gray-600 text-gray-400 hover:text-white';
        }
    }
    window.updateChartSignalButtonsUI = updateChartSignalButtonsUI;

    function toggleStocksHaWickFilter() {
        alarmSettings.stocksHaWickFilter = (alarmSettings.stocksHaWickFilter === false);
        saveAlarmSettings();
        updateHaWickFilterUI();
        renderWeightageColumn();
    }
    window.toggleStocksHaWickFilter = toggleStocksHaWickFilter;

    function toggleOptionsHaWickFilter() {
        alarmSettings.optionsHaWickFilter = (alarmSettings.optionsHaWickFilter === false);
        saveAlarmSettings();
        updateHaWickFilterUI();
        renderOptionsBreadthColumn();
    }
    window.toggleOptionsHaWickFilter = toggleOptionsHaWickFilter;

    function updateHaWickFilterUI() {
        const isStocksStrict = (alarmSettings.stocksHaWickFilter !== false);
        const isOptionsStrict = (alarmSettings.optionsHaWickFilter !== false);

        const badgeW = document.getElementById('w-ha-wick-filter-badge');
        if (badgeW) {
            badgeW.textContent = isStocksStrict ? 'STRICT' : 'OFF';
            badgeW.className = isStocksStrict ? 'font-mono text-emerald-400 font-extrabold' : 'font-mono text-slate-400 font-bold';
        }
        const badgeO = document.getElementById('o-ha-wick-filter-badge');
        if (badgeO) {
            badgeO.textContent = isOptionsStrict ? 'STRICT' : 'OFF';
            badgeO.className = isOptionsStrict ? 'font-mono text-purple-400 font-extrabold' : 'font-mono text-slate-400 font-bold';
        }

        const toggleS = document.getElementById('stocks-ha-wick-filter-toggle');
        if (toggleS) toggleS.checked = isStocksStrict;

        const toggleO = document.getElementById('options-ha-wick-filter-toggle');
        if (toggleO) toggleO.checked = isOptionsStrict;
    }
    window.updateHaWickFilterUI = updateHaWickFilterUI;

    function updateCandleStyleUI() {
        const isWHa = wCandleStyle === 'heikin_ashi';
        const isOHa = oCandleStyle === 'heikin_ashi';

        // 1. Top select
        const sel = document.getElementById('candle-style-select');
        if (sel) {
            if (isWHa && isOHa) sel.value = 'heikin_ashi';
            else if (!isWHa && !isOHa) sel.value = 'candle';
        }

        // 2. Row 1 Button & Title
        const wBadge = document.getElementById('w-candle-style-badge');
        const wIcon = document.getElementById('w-candle-style-icon');
        const wTitle = document.getElementById('w-chart-title');
        if (wBadge) {
            wBadge.textContent = isWHa ? 'HEIKIN ASHI' : 'CANDLE';
            wBadge.className = isWHa ? 'font-mono text-purple-400 font-extrabold' : 'font-mono text-emerald-400 font-extrabold';
        }
        if (wIcon) wIcon.textContent = isWHa ? '📊' : '🕯️';
        if (wBadge) {
            wBadge.textContent = isWHa ? 'HA' : 'CANDLE';
            wBadge.className = isWHa ? 'font-mono text-purple-400 font-extrabold' : 'font-mono text-emerald-400 font-extrabold';
        }
        if (wTitle) {
            wTitle.innerHTML = isWHa 
                ? `NIFTY 50 <span class="ml-1 px-1 py-0.2 text-[9px] font-mono bg-purple-950/90 border border-purple-500/60 text-purple-300 rounded font-bold">HA</span>`
                : `NIFTY 50`;
        }

        // 3. Row 2 Button & Title
        const oBadge = document.getElementById('o-candle-style-badge');
        const oIcon = document.getElementById('o-candle-style-icon');
        const oTitle = document.getElementById('o-chart-title');
        if (oBadge) {
            oBadge.textContent = isOHa ? 'HA' : 'CANDLE';
            oBadge.className = isOHa ? 'font-mono text-purple-400 font-extrabold' : 'font-mono text-emerald-400 font-extrabold';
        }
        if (oIcon) oIcon.textContent = isOHa ? '📊' : '🕯️';
        if (oTitle) {
            oTitle.innerHTML = isOHa 
                ? `OPTIONS <span class="ml-1 px-1 py-0.2 text-[9px] font-mono bg-purple-950/90 border border-purple-500/60 text-purple-300 rounded font-bold">HA</span>`
                : `OPTIONS`;
        }
    }
    window.updateCandleStyleUI = updateCandleStyleUI;

    function setWeightageCandleStyle(style) {
        wCandleStyle = (style === 'heikin_ashi') ? 'heikin_ashi' : 'candle';
        try {
            localStorage.setItem('nifty_unified_w_candle_style', wCandleStyle);
        } catch(e) {}
        updateCandleStyleUI();
        updateChartBasisUI();
        syncBuildupTabUI();
        syncSynergyControlsUI();
        renderWeightageColumn();
        renderWeightageSuperTrend();
        if (typeof activeCandleIndex !== 'undefined' && typeof timestampsList !== 'undefined' && timestampsList[activeCandleIndex]) {
            syncWeightageUI(activeCandleIndex, timestampsList[activeCandleIndex]);
        }
    }
    window.setWeightageCandleStyle = setWeightageCandleStyle;

    function toggleWeightageCandleStyle() {
        const next = (wCandleStyle === 'candle') ? 'heikin_ashi' : 'candle';
        setWeightageCandleStyle(next);
    }
    window.toggleWeightageCandleStyle = toggleWeightageCandleStyle;

    function setOptionsCandleStyle(style) {
        oCandleStyle = (style === 'heikin_ashi') ? 'heikin_ashi' : 'candle';
        try {
            localStorage.setItem('nifty_unified_o_candle_style', oCandleStyle);
        } catch(e) {}
        updateCandleStyleUI();
        updateChartBasisUI();
        syncBuildupTabUI();
        syncSynergyControlsUI();
        renderOptionsBreadthColumn();
        renderOptionsSuperTrend();
        if (typeof activeCandleIndex !== 'undefined' && typeof timestampsList !== 'undefined' && timestampsList[activeCandleIndex]) {
            syncOptionsBreadthUI(activeCandleIndex, timestampsList[activeCandleIndex]);
        }
    }
    window.setOptionsCandleStyle = setOptionsCandleStyle;

    function toggleOptionsCandleStyle() {
        const next = (oCandleStyle === 'candle') ? 'heikin_ashi' : 'candle';
        setOptionsCandleStyle(next);
    }
    window.toggleOptionsCandleStyle = toggleOptionsCandleStyle;

    // Master / Backward Compatibility toggle
    function setCandleStyle(style) {
        setWeightageCandleStyle(style);
        setOptionsCandleStyle(style);
    }
    window.setCandleStyle = setCandleStyle;

    function toggleCandleStyle() {
        const next = (wCandleStyle === 'candle' || oCandleStyle === 'candle') ? 'heikin_ashi' : 'candle';
        setCandleStyle(next);
    }
    window.toggleCandleStyle = toggleCandleStyle;

    function refreshCandleSeriesData() {
        renderWeightageColumn();
        renderOptionsBreadthColumn();
        drawWeightageQuantLines();
        drawOptionsQuantLines();
        drawWeightageOrderFlowLines();
        drawOptionsOrderFlowLines();
        renderAllChartOverlays();
    }
    window.refreshCandleSeriesData = refreshCandleSeriesData;

    function setGlobalSignalsMode(mode) {
        alarmSettings.stocksMarkerMode = mode;
        alarmSettings.stocksShowMarkers = (mode !== 'off');
        alarmSettings.optionsMarkerMode = mode;
        alarmSettings.optionsShowMarkers = (mode !== 'off');
        saveAlarmSettings();
        updateMarkerButtonsUI();
        updateTvToolsUI();
        renderWeightageColumn();
        renderOptionsBreadthColumn();
    }
    window.setGlobalSignalsMode = setGlobalSignalsMode;

    function cycleStocksMarkerMode() {
        const current = alarmSettings.stocksMarkerMode || (alarmSettings.stocksShowMarkers ? 'breach' : 'off');
        let next = 'breach';
        if (current === 'breach') next = 'all';
        else if (current === 'all') next = 'off';
        else next = 'breach';

        alarmSettings.stocksMarkerMode = next;
        alarmSettings.stocksShowMarkers = (next !== 'off');
        saveAlarmSettings();
        updateMarkerButtonsUI();
        renderWeightageColumn();
    }
    window.cycleStocksMarkerMode = cycleStocksMarkerMode;

    function cycleOptionsMarkerMode() {
        const current = alarmSettings.optionsMarkerMode || (alarmSettings.optionsShowMarkers ? 'breach' : 'off');
        let next = 'breach';
        if (current === 'breach') next = 'all';
        else if (current === 'all') next = 'off';
        else next = 'breach';

        alarmSettings.optionsMarkerMode = next;
        alarmSettings.optionsShowMarkers = (next !== 'off');
        saveAlarmSettings();
        updateMarkerButtonsUI();
        renderOptionsBreadthColumn();
    }
    window.cycleOptionsMarkerMode = cycleOptionsMarkerMode;

    function updateMarkerButtonsUI() {
        updateHaWickFilterUI();
        const topSelect = document.getElementById('signals-mode-select');
        const sMode = alarmSettings.stocksMarkerMode || (alarmSettings.stocksShowMarkers ? 'breach' : 'off');
        const oMode = alarmSettings.optionsMarkerMode || (alarmSettings.optionsShowMarkers ? 'breach' : 'off');
        if (topSelect) {
            if (sMode === oMode) topSelect.value = sMode;
        }

        const sBadge = document.getElementById('w-marker-status-badge');
        if (sBadge) {
            if (sMode === 'all') {
                sBadge.textContent = 'EVERY 5M';
                sBadge.className = 'font-mono text-emerald-300 font-extrabold';
            } else if (sMode === 'breach') {
                sBadge.textContent = 'BREACH';
                sBadge.className = 'font-mono text-cyan-300 font-extrabold';
            } else {
                sBadge.textContent = 'OFF';
                sBadge.className = 'font-mono text-gray-500 font-extrabold';
            }
        }

        const oBadge = document.getElementById('o-marker-status-badge');
        if (oBadge) {
            if (oMode === 'all') {
                oBadge.textContent = 'EVERY 5M';
                oBadge.className = 'font-mono text-emerald-300 font-extrabold';
            } else if (oMode === 'breach') {
                oBadge.textContent = 'BREACH';
                oBadge.className = 'font-mono text-cyan-300 font-extrabold';
            } else {
                oBadge.textContent = 'OFF';
                oBadge.className = 'font-mono text-gray-500 font-extrabold';
            }
        }
    }
    window.updateMarkerButtonsUI = updateMarkerButtonsUI;

    // ══════════════════════════════════════════════════════════════════════════════
    // TRADINGVIEW TOOLS: SUPERTREND, FIBONACCI RETRACEMENT & MANUAL HORIZONTAL LINES
    // ══════════════════════════════════════════════════════════════════════════════
    function cycleWeightageSuperTrendMode() {
        let cur = wTvSettings.superTrend;
        let next = 'off';
        if (cur === 'off') next = '10_3';
        else if (cur === '10_3') next = '10_2';
        else next = 'off';

        wTvSettings.superTrend = next;
        try {
            localStorage.setItem('nifty_unified_w_st_mode', next);
        } catch(e){}
        updateTvToolsUI();
        renderWeightageSuperTrend();
    }
    window.cycleWeightageSuperTrendMode = cycleWeightageSuperTrendMode;

    function cycleOptionsSuperTrendMode() {
        let cur = oTvSettings.superTrend;
        let next = 'off';
        if (cur === 'off') next = '10_3';
        else if (cur === '10_3') next = '10_2';
        else next = 'off';

        oTvSettings.superTrend = next;
        try {
            localStorage.setItem('nifty_unified_o_st_mode', next);
        } catch(e){}
        updateTvToolsUI();
        renderOptionsSuperTrend();
    }
    window.cycleOptionsSuperTrendMode = cycleOptionsSuperTrendMode;

    function cycleSuperTrendMode() {
        cycleWeightageSuperTrendMode();
        cycleOptionsSuperTrendMode();
    }
    window.cycleSuperTrendMode = cycleSuperTrendMode;

    function cycleWeightageFibMode() {
        let cur = wTvSettings.fibMode;
        let next = 'off';
        if (cur === 'off') next = 'auto';
        else if (cur === 'auto') next = 'manual';
        else next = 'off';

        wTvSettings.fibMode = next;
        try {
            localStorage.setItem('nifty_unified_w_fib_mode', next);
        } catch(e){}
        if (next === 'manual') {
            wManualFibClickStep = 1;
            wManualFibTempP1 = null;
            setActiveWeightageTvTool('fib_manual');
        } else {
            setActiveWeightageTvTool('pointer');
        }
        updateTvToolsUI();
        renderWeightageFibonacci();
    }
    window.cycleWeightageFibMode = cycleWeightageFibMode;

    function cycleOptionsFibMode() {
        let cur = oTvSettings.fibMode;
        let next = 'off';
        if (cur === 'off') next = 'auto';
        else if (cur === 'auto') next = 'manual';
        else next = 'off';

        oTvSettings.fibMode = next;
        try {
            localStorage.setItem('nifty_unified_o_fib_mode', next);
        } catch(e){}
        if (next === 'manual') {
            oManualFibClickStep = 1;
            oManualFibTempP1 = null;
            setActiveOptionsTvTool('fib_manual');
        } else {
            setActiveOptionsTvTool('pointer');
        }
        updateTvToolsUI();
        renderOptionsFibonacci();
    }
    window.cycleOptionsFibMode = cycleOptionsFibMode;

    function cycleFibMode() {
        cycleWeightageFibMode();
        cycleOptionsFibMode();
    }
    window.cycleFibMode = cycleFibMode;

    function toggleWeightageHLineTool() {
        if (wActiveTvTool === 'hline') {
            setActiveWeightageTvTool('pointer');
        } else {
            setActiveWeightageTvTool('hline');
        }
    }
    window.toggleWeightageHLineTool = toggleWeightageHLineTool;

    function toggleOptionsHLineTool() {
        if (oActiveTvTool === 'hline') {
            setActiveOptionsTvTool('pointer');
        } else {
            setActiveOptionsTvTool('hline');
        }
    }
    window.toggleOptionsHLineTool = toggleOptionsHLineTool;

    function toggleHLineTool() {
        toggleWeightageHLineTool();
        toggleOptionsHLineTool();
    }
    window.toggleHLineTool = toggleHLineTool;

    function setActiveWeightageTvTool(tool) {
        wActiveTvTool = tool;
        updateTvToolsUI();
    }
    window.setActiveWeightageTvTool = setActiveWeightageTvTool;

    function setActiveOptionsTvTool(tool) {
        oActiveTvTool = tool;
        updateTvToolsUI();
    }
    window.setActiveOptionsTvTool = setActiveOptionsTvTool;

    function setActiveTvTool(tool) {
        setActiveWeightageTvTool(tool);
        setActiveOptionsTvTool(tool);
    }
    window.setActiveTvTool = setActiveTvTool;

    function handleWeightageManualFibClick(price) {
        if (wManualFibClickStep === 1) {
            wManualFibTempP1 = price;
            wManualFibClickStep = 2;
            updateTvHint(`Point 1: ₹${price.toFixed(1)}. Now click Point 2 on Weightage chart...`, 'w');
        } else if (wManualFibClickStep === 2) {
            wTvSettings.manualFibPoints = { p1: wManualFibTempP1, p2: price };
            try {
                localStorage.setItem('nifty_unified_w_manual_fib', JSON.stringify(wTvSettings.manualFibPoints));
            } catch(e){}
            wManualFibClickStep = 0;
            wManualFibTempP1 = null;
            setActiveWeightageTvTool('pointer');
            renderWeightageFibonacci();
        }
    }

    function handleOptionsManualFibClick(price) {
        if (oManualFibClickStep === 1) {
            oManualFibTempP1 = price;
            oManualFibClickStep = 2;
            updateTvHint(`Point 1: ₹${price.toFixed(1)}. Now click Point 2 on Options chart...`, 'o');
        } else if (oManualFibClickStep === 2) {
            oTvSettings.manualFibPoints = { p1: oManualFibTempP1, p2: price };
            try {
                localStorage.setItem('nifty_unified_o_manual_fib', JSON.stringify(oTvSettings.manualFibPoints));
            } catch(e){}
            oManualFibClickStep = 0;
            oManualFibTempP1 = null;
            setActiveOptionsTvTool('pointer');
            renderOptionsFibonacci();
        }
    }

    function handleManualFibClick(price) {
        handleWeightageManualFibClick(price);
        handleOptionsManualFibClick(price);
    }

    function addWeightageManualHLine(price) {
        if (!wTvSettings.manualHLines) wTvSettings.manualHLines = [];
        wTvSettings.manualHLines.push(price);
        try {
            localStorage.setItem('nifty_unified_w_manual_hlines', JSON.stringify(wTvSettings.manualHLines));
        } catch(e){}
        renderWeightageManualHLines();
    }
    window.addWeightageManualHLine = addWeightageManualHLine;

    function addOptionsManualHLine(price) {
        if (!oTvSettings.manualHLines) oTvSettings.manualHLines = [];
        oTvSettings.manualHLines.push(price);
        try {
            localStorage.setItem('nifty_unified_o_manual_hlines', JSON.stringify(oTvSettings.manualHLines));
        } catch(e){}
        renderOptionsManualHLines();
    }
    window.addOptionsManualHLine = addOptionsManualHLine;

    function addManualHLine(price) {
        addWeightageManualHLine(price);
        addOptionsManualHLine(price);
    }

    function clearWeightageManualDrawings() {
        wTvSettings.manualHLines = [];
        wTvSettings.manualFibPoints = null;
        try {
            localStorage.removeItem('nifty_unified_w_manual_hlines');
            localStorage.removeItem('nifty_unified_w_manual_fib');
        } catch(e){}
        clearWeightageManualHLines();
        if (wTvSettings.fibMode === 'manual') {
            wTvSettings.fibMode = 'off';
            localStorage.setItem('nifty_unified_w_fib_mode', 'off');
        }
        clearWeightageFibPriceLines();
        setActiveWeightageTvTool('pointer');
        updateTvToolsUI();
    }
    window.clearWeightageManualDrawings = clearWeightageManualDrawings;

    function clearOptionsManualDrawings() {
        oTvSettings.manualHLines = [];
        oTvSettings.manualFibPoints = null;
        try {
            localStorage.removeItem('nifty_unified_o_manual_hlines');
            localStorage.removeItem('nifty_unified_o_manual_fib');
        } catch(e){}
        clearOptionsManualHLines();
        if (oTvSettings.fibMode === 'manual') {
            oTvSettings.fibMode = 'off';
            localStorage.setItem('nifty_unified_o_fib_mode', 'off');
        }
        clearOptionsFibPriceLines();
        setActiveOptionsTvTool('pointer');
        updateTvToolsUI();
    }
    window.clearOptionsManualDrawings = clearOptionsManualDrawings;

    function clearAllManualDrawings() {
        clearWeightageManualDrawings();
        clearOptionsManualDrawings();
    }
    window.clearAllManualDrawings = clearAllManualDrawings;

    function updateTvHint(customText = null, targetChart = null) {
        const wHint = document.getElementById('w-tv-hint');
        const oHint = document.getElementById('o-tv-hint');
        const wText = document.getElementById('w-tv-hint-text');
        const oText = document.getElementById('o-tv-hint-text');

        if (wHint) {
            let isWVis = false;
            let tW = '';
            if (wActiveTvTool === 'hline') {
                isWVis = true;
                tW = 'Click any price on Weightage chart to place Horizontal Support/Resistance';
            } else if (wActiveTvTool === 'fib_manual') {
                isWVis = true;
                tW = (targetChart === 'w' && customText) ? customText : 'Click Swing Point 1 (High/Low) on Weightage chart...';
            }
            wHint.className = isWVis ? 'flex absolute top-2 right-12 z-20 px-2.5 py-1 rounded bg-sky-950/95 border border-sky-400 text-sky-200 text-[10px] font-mono shadow-lg items-center gap-1.5 animate-pulse' : 'hidden';
            if (wText && isWVis) wText.textContent = tW;
        }

        if (oHint) {
            let isOVis = false;
            let tO = '';
            if (oActiveTvTool === 'hline') {
                isOVis = true;
                tO = 'Click any price on Options chart to place Horizontal Support/Resistance';
            } else if (oActiveTvTool === 'fib_manual') {
                isOVis = true;
                tO = (targetChart === 'o' && customText) ? customText : 'Click Swing Point 1 (High/Low) on Options chart...';
            }
            oHint.className = isOVis ? 'flex absolute top-2 right-12 z-20 px-2.5 py-1 rounded bg-sky-950/95 border border-sky-400 text-sky-200 text-[10px] font-mono shadow-lg items-center gap-1.5 animate-pulse' : 'hidden';
            if (oText && isOVis) oText.textContent = tO;
        }
    }

    function updateTvToolsUI() {
        // 1. SuperTrend Badges
        const wStBadge = document.getElementById('w-st-status-badge');
        if (wStBadge) {
            if (wTvSettings.superTrend === '10_3') {
                wStBadge.textContent = '10, 3';
                wStBadge.className = 'font-mono text-emerald-400 font-extrabold';
            } else if (wTvSettings.superTrend === '10_2') {
                wStBadge.textContent = '10, 2';
                wStBadge.className = 'font-mono text-cyan-300 font-extrabold';
            } else {
                wStBadge.textContent = 'OFF';
                wStBadge.className = 'font-mono text-gray-500 font-extrabold';
            }
        }

        const oStBadge = document.getElementById('o-st-status-badge');
        if (oStBadge) {
            if (oTvSettings.superTrend === '10_3') {
                oStBadge.textContent = '10, 3';
                oStBadge.className = 'font-mono text-emerald-400 font-extrabold';
            } else if (oTvSettings.superTrend === '10_2') {
                oStBadge.textContent = '10, 2';
                oStBadge.className = 'font-mono text-cyan-300 font-extrabold';
            } else {
                oStBadge.textContent = 'OFF';
                oStBadge.className = 'font-mono text-gray-500 font-extrabold';
            }
        }

        // 2. Fibonacci Badges
        const wFibBadge = document.getElementById('w-fib-status-badge');
        if (wFibBadge) {
            if (wTvSettings.fibMode === 'auto') {
                wFibBadge.textContent = 'AUTO';
                wFibBadge.className = 'font-mono text-violet-300 font-extrabold';
            } else if (wTvSettings.fibMode === 'manual') {
                wFibBadge.textContent = 'MANUAL';
                wFibBadge.className = 'font-mono text-amber-300 font-extrabold';
            } else {
                wFibBadge.textContent = 'OFF';
                wFibBadge.className = 'font-mono text-gray-500 font-extrabold';
            }
        }

        const oFibBadge = document.getElementById('o-fib-status-badge');
        if (oFibBadge) {
            if (oTvSettings.fibMode === 'auto') {
                oFibBadge.textContent = 'AUTO';
                oFibBadge.className = 'font-mono text-violet-300 font-extrabold';
            } else if (oTvSettings.fibMode === 'manual') {
                oFibBadge.textContent = 'MANUAL';
                oFibBadge.className = 'font-mono text-amber-300 font-extrabold';
            } else {
                oFibBadge.textContent = 'OFF';
                oFibBadge.className = 'font-mono text-gray-500 font-extrabold';
            }
        }

        // 3. Floating Toolbar active styles
        const toolClasses = {
            active: 'tv-tool-btn active w-6 h-6 flex items-center justify-center rounded transition text-white bg-sky-600 shadow-xs cursor-pointer',
            inactive: 'tv-tool-btn w-6 h-6 flex items-center justify-center rounded transition text-gray-400 hover:text-white hover:bg-gray-700 cursor-pointer',
        };

        // Row 1 dock
        const wBtnPointer = document.getElementById('w-tv-pointer');
        const wBtnHline = document.getElementById('w-tv-hline');
        const wBtnFib = document.getElementById('w-tv-fib');
        const wBtnSt = document.getElementById('w-tv-st');

        if (wBtnPointer) wBtnPointer.className = (wActiveTvTool === 'pointer') ? toolClasses.active : toolClasses.inactive;
        if (wBtnHline) wBtnHline.className = (wActiveTvTool === 'hline') ? toolClasses.active : toolClasses.inactive;
        if (wBtnFib) wBtnFib.className = (wActiveTvTool === 'fib_manual' || wTvSettings.fibMode !== 'off') ? 'tv-tool-btn active w-6 h-6 flex items-center justify-center rounded transition text-violet-200 bg-violet-900/80 border border-violet-500/50 font-mono text-[9px] font-black cursor-pointer' : toolClasses.inactive;
        if (wBtnSt) wBtnSt.className = (wTvSettings.superTrend !== 'off') ? 'tv-tool-btn active w-6 h-6 flex items-center justify-center rounded transition text-amber-200 bg-amber-900/80 border border-amber-500/50 font-mono text-[9px] font-black cursor-pointer' : toolClasses.inactive;

        // Row 2 dock
        const oBtnPointer = document.getElementById('o-tv-pointer');
        const oBtnHline = document.getElementById('o-tv-hline');
        const oBtnFib = document.getElementById('o-tv-fib');
        const oBtnSt = document.getElementById('o-tv-st');

        if (oBtnPointer) oBtnPointer.className = (oActiveTvTool === 'pointer') ? toolClasses.active : toolClasses.inactive;
        if (oBtnHline) oBtnHline.className = (oActiveTvTool === 'hline') ? toolClasses.active : toolClasses.inactive;
        if (oBtnFib) oBtnFib.className = (oActiveTvTool === 'fib_manual' || oTvSettings.fibMode !== 'off') ? 'tv-tool-btn active w-6 h-6 flex items-center justify-center rounded transition text-violet-200 bg-violet-900/80 border border-violet-500/50 font-mono text-[9px] font-black cursor-pointer' : toolClasses.inactive;
        if (oBtnSt) oBtnSt.className = (oTvSettings.superTrend !== 'off') ? 'tv-tool-btn active w-6 h-6 flex items-center justify-center rounded transition text-amber-200 bg-amber-900/80 border border-amber-500/50 font-mono text-[9px] font-black cursor-pointer' : toolClasses.inactive;

        updateTvHint();
    }
    window.updateTvToolsUI = updateTvToolsUI;

    // ── SuperTrend Calculation (Wilder RMA ATR + Bands) ──
    function calculateSuperTrend(candles, period = 10, multiplier = 3) {
        if (!candles || candles.length < period) return { bull: [], bear: [], flips: [] };

        const tr = [];
        for (let i = 0; i < candles.length; i++) {
            const c = candles[i];
            if (i === 0) {
                tr.push(c.high - c.low);
            } else {
                const prev = candles[i - 1];
                tr.push(Math.max(c.high - c.low, Math.abs(c.high - prev.close), Math.abs(c.low - prev.close)));
            }
        }

        const atr = [];
        let sumTr = 0;
        for (let i = 0; i < period; i++) sumTr += tr[i];
        let prevAtr = sumTr / period;
        atr[period - 1] = prevAtr;
        for (let i = period; i < candles.length; i++) {
            prevAtr = (prevAtr * (period - 1) + tr[i]) / period;
            atr[i] = prevAtr;
        }

        const bull = [];
        const bear = [];
        const flips = [];

        let prevUpper = 0, prevLower = 0, prevTrend = 1;

        for (let i = 0; i < candles.length; i++) {
            const c = candles[i];
            const t = Number(c.time || c.timestamp);
            if (i < period - 1) continue;

            const curAtr = atr[i];
            const hl2 = (c.high + c.low) / 2;
            const basicUpper = hl2 + (multiplier * curAtr);
            const basicLower = hl2 - (multiplier * curAtr);

            let upper = basicUpper;
            let lower = basicLower;

            if (i > 0) {
                const prevC = candles[i - 1];
                if (prevC.close <= prevUpper) {
                    upper = Math.min(basicUpper, prevUpper);
                }
                if (prevC.close >= prevLower) {
                    lower = Math.max(basicLower, prevLower);
                }
            }

            let trend = prevTrend;
            if (c.close > prevUpper) {
                trend = 1;
            } else if (c.close < prevLower) {
                trend = -1;
            }

            if (trend !== prevTrend && i > period) {
                flips.push({
                    time: t,
                    position: trend === 1 ? 'belowBar' : 'aboveBar',
                    color: trend === 1 ? '#10b981' : '#ef4444',
                    shape: trend === 1 ? 'arrowUp' : 'arrowDown',
                    text: trend === 1 ? `BUY ST(${period},${multiplier})` : `SELL ST(${period},${multiplier})`,
                });
            }

            const val = Number(((trend === 1) ? lower : upper).toFixed(2));
            if (trend === 1) {
                bull.push({ time: t, value: val });
            } else {
                bear.push({ time: t, value: val });
            }

            prevUpper = upper;
            prevLower = lower;
            prevTrend = trend;
        }

        return { bull, bear, flips };
    }

    function renderWeightageSuperTrend() {
        if (!wStBullSeries || !wStBearSeries) return;
        const mode = wTvSettings.superTrend;
        if (mode === 'off') {
            wStBullSeries.setData([]);
            wStBearSeries.setData([]);
            return;
        }
        const mult = (mode === '10_2') ? 2 : 3;
        const allCandles = (wCandleStyle === 'heikin_ashi' && window._unifiedHaCandles && window._unifiedHaCandles.length > 0)
            ? window._unifiedHaCandles
            : (window._unifiedAllCandles || []);
        if (allCandles.length < 10) return;

        const st = calculateSuperTrend(allCandles, 10, mult);
        wStBullSeries.setData(st.bull);
        wStBearSeries.setData(st.bear);
    }

    function renderOptionsSuperTrend() {
        if (!oStBullSeries || !oStBearSeries) return;
        const mode = oTvSettings.superTrend;
        if (mode === 'off') {
            oStBullSeries.setData([]);
            oStBearSeries.setData([]);
            return;
        }
        const mult = (mode === '10_2') ? 2 : 3;
        const allCandles = (oCandleStyle === 'heikin_ashi' && window._unifiedOptionsHaCandles && window._unifiedOptionsHaCandles.length > 0)
            ? window._unifiedOptionsHaCandles
            : (window._unifiedOptionsCandles || []);
        if (allCandles.length < 10) return;

        const st = calculateSuperTrend(allCandles, 10, mult);
        oStBullSeries.setData(st.bull);
        oStBearSeries.setData(st.bear);
    }

    function renderSuperTrendOverlays() {
        renderWeightageSuperTrend();
        renderOptionsSuperTrend();
    }

    // ── Fibonacci Retracement Rendering ──
    const FIB_RATIOS = [
        { ratio: 1.0,   label: 'Fib 1.0 (High)',       color: '#f43f5e', lineStyle: 0 },
        { ratio: 0.786, label: 'Fib 0.786',            color: '#38bdf8', lineStyle: 2 },
        { ratio: 0.618, label: 'Fib 0.618 (Golden)',   color: '#f59e0b', lineStyle: 1 },
        { ratio: 0.500, label: 'Fib 0.500 (Mid)',      color: '#06b6d4', lineStyle: 0 },
        { ratio: 0.382, label: 'Fib 0.382',            color: '#a855f7', lineStyle: 2 },
        { ratio: 0.0,   label: 'Fib 0.0 (Low)',        color: '#10b981', lineStyle: 0 },
    ];

    function clearWeightageFibPriceLines() {
        if (wCandleSeries && wFibPriceLines.length > 0) {
            wFibPriceLines.forEach(pl => {
                try { wCandleSeries.removePriceLine(pl); } catch(e){}
            });
            wFibPriceLines = [];
        }
    }

    function clearOptionsFibPriceLines() {
        if (oCandleSeries && oFibPriceLines.length > 0) {
            oFibPriceLines.forEach(pl => {
                try { oCandleSeries.removePriceLine(pl); } catch(e){}
            });
            oFibPriceLines = [];
        }
    }

    function clearFibPriceLines() {
        clearWeightageFibPriceLines();
        clearOptionsFibPriceLines();
    }

    function renderWeightageFibonacci() {
        clearWeightageFibPriceLines();
        const mode = wTvSettings.fibMode;
        if (mode === 'off' || !wCandleSeries) return;

        let high = 0, low = 0;
        if (mode === 'auto') {
            const raw = weightageData?.candles || weightageData?.nifty_candles || [];
            if (!raw || raw.length === 0) return;
            high = Math.max(...raw.map(c => Number(c.high)));
            low = Math.min(...raw.map(c => Number(c.low)));
        } else if (mode === 'manual') {
            if (!wTvSettings.manualFibPoints || !wTvSettings.manualFibPoints.p1 || !wTvSettings.manualFibPoints.p2) return;
            high = Math.max(wTvSettings.manualFibPoints.p1, wTvSettings.manualFibPoints.p2);
            low = Math.min(wTvSettings.manualFibPoints.p1, wTvSettings.manualFibPoints.p2);
        }

        if (high <= 0 || low <= 0 || high <= low) return;
        const range = high - low;

        FIB_RATIOS.forEach(lvl => {
            const price = Number((low + range * lvl.ratio).toFixed(2));
            const pl = wCandleSeries.createPriceLine({
                price: price,
                color: lvl.color,
                lineWidth: (lvl.ratio === 0.618 || lvl.ratio === 0.5) ? 2 : 1,
                lineStyle: lvl.lineStyle,
                axisLabelVisible: true,
                title: `${lvl.label} ${price.toFixed(1)}`,
            });
            wFibPriceLines.push(pl);
        });
    }

    function renderOptionsFibonacci() {
        clearOptionsFibPriceLines();
        const mode = oTvSettings.fibMode;
        if (mode === 'off' || !oCandleSeries) return;

        let high = 0, low = 0;
        if (mode === 'auto') {
            const raw = breadthData?.nifty_candles || [];
            if (!raw || raw.length === 0) return;
            high = Math.max(...raw.map(c => Number(c.high)));
            low = Math.min(...raw.map(c => Number(c.low)));
        } else if (mode === 'manual') {
            if (!oTvSettings.manualFibPoints || !oTvSettings.manualFibPoints.p1 || !oTvSettings.manualFibPoints.p2) return;
            high = Math.max(oTvSettings.manualFibPoints.p1, oTvSettings.manualFibPoints.p2);
            low = Math.min(oTvSettings.manualFibPoints.p1, oTvSettings.manualFibPoints.p2);
        }

        if (high <= 0 || low <= 0 || high <= low) return;
        const range = high - low;

        FIB_RATIOS.forEach(lvl => {
            const price = Number((low + range * lvl.ratio).toFixed(2));
            const pl = oCandleSeries.createPriceLine({
                price: price,
                color: lvl.color,
                lineWidth: (lvl.ratio === 0.618 || lvl.ratio === 0.5) ? 2 : 1,
                lineStyle: lvl.lineStyle,
                axisLabelVisible: true,
                title: `${lvl.label} ${price.toFixed(1)}`,
            });
            oFibPriceLines.push(pl);
        });
    }

    function renderFibonacciOverlays() {
        renderWeightageFibonacci();
        renderOptionsFibonacci();
    }

    // ── Manual Horizontal Support/Resistance Lines ──
    function clearWeightageManualHLines() {
        if (wCandleSeries && wManualPriceLines.length > 0) {
            wManualPriceLines.forEach(pl => {
                try { wCandleSeries.removePriceLine(pl); } catch(e){}
            });
            wManualPriceLines = [];
        }
    }

    function clearOptionsManualHLines() {
        if (oCandleSeries && oManualPriceLines.length > 0) {
            oManualPriceLines.forEach(pl => {
                try { oCandleSeries.removePriceLine(pl); } catch(e){}
            });
            oManualPriceLines = [];
        }
    }

    function clearManualHLines() {
        clearWeightageManualHLines();
        clearOptionsManualHLines();
    }

    function renderWeightageManualHLines() {
        clearWeightageManualHLines();
        if (!wCandleSeries) return;
        const lines = wTvSettings.manualHLines || [];
        lines.forEach((price) => {
            const pl = wCandleSeries.createPriceLine({
                price: price,
                color: '#38bdf8',
                lineWidth: 1,
                lineStyle: LightweightCharts.LineStyle.Dashed,
                axisLabelVisible: true,
                title: `H-Line ${price.toFixed(1)}`,
            });
            wManualPriceLines.push(pl);
        });
    }

    function renderOptionsManualHLines() {
        clearOptionsManualHLines();
        if (!oCandleSeries) return;
        const lines = oTvSettings.manualHLines || [];
        lines.forEach((price) => {
            const pl = oCandleSeries.createPriceLine({
                price: price,
                color: '#38bdf8',
                lineWidth: 1,
                lineStyle: LightweightCharts.LineStyle.Dashed,
                axisLabelVisible: true,
                title: `H-Line ${price.toFixed(1)}`,
            });
            oManualPriceLines.push(pl);
        });
    }

    function renderManualHLines() {
        renderWeightageManualHLines();
        renderOptionsManualHLines();
    }

    function renderWeightageOverlays() {
        renderWeightageSuperTrend();
        renderWeightageFibonacci();
        renderWeightageManualHLines();
    }

    function renderOptionsOverlays() {
        renderOptionsSuperTrend();
        renderOptionsFibonacci();
        renderOptionsManualHLines();
    }

    function renderAllChartOverlays() {
        renderWeightageOverlays();
        renderOptionsOverlays();
    }

    async function reloadTrendingOiWithInterval(interval = 5) {
        try {
            document.getElementById('toi-chart-loading')?.classList.remove('hidden');
            const todayStr = "{{ \Illuminate\Support\Carbon::now('Asia/Kolkata')->toDateString() }}";
            const currentToiMode = (typeof selectedDate !== 'undefined' && selectedDate === todayStr) ? 'live' : 'history';
            const atmParam = (manualToiAtmStrike !== null && manualToiAtmStrike > 0) ? `&atm_strike=${manualToiAtmStrike}` : '';
            const bMode = alarmSettings.optionsBasketMode || 'fixed';
            let resToi = await fetch(`/api/trending-oi/data?mode=${currentToiMode}&expiry=${selectedExpiry}&date=${selectedDate}&underlying=NSE_INDEX|Nifty 50&interval=${interval}&lookback=auto&skip_open=0${atmParam}&basket_mode=${bMode}`).then(r => r.json());
            if (currentToiMode === 'live' && (!resToi || !resToi.chart || !resToi.chart.labels || resToi.chart.labels.length === 0)) {
                resToi = await fetch(`/api/trending-oi/data?mode=history&expiry=${selectedExpiry}&date=${selectedDate}&underlying=NSE_INDEX|Nifty 50&interval=${interval}&lookback=auto&skip_open=0${atmParam}&basket_mode=${bMode}`).then(r => r.json());
            }
            if (resToi && !resToi.error) {
                trendingOiData = resToi;
                if (resToi.stream_config) {
                    toiStreamConfig = resToi.stream_config;
                }
                renderTrendingOiRows();
                if (typeof renderStrikeBuildupChart === 'function') renderStrikeBuildupChart();
                buildMasterTimeline();
            }
        } catch(e) {
            console.error('Error reloading Trending OI:', e);
        } finally {
            document.getElementById('toi-chart-loading')?.classList.add('hidden');
        }
    }

    function updateQuantLevelsUI(playbook) {
        if (!playbook || !playbook.levels || !Array.isArray(playbook.levels)) return;
        quantPlaybookData = playbook;

        const stripContainer = document.getElementById('quant-strip-container');
        if (stripContainer) stripContainer.classList.remove('hidden');

        const findLevel = (code) => {
            const found = playbook.levels.find(l => l.code === code);
            return found ? parseFloat(found.price) : null;
        };

        const r3 = findLevel('R3');
        const r2 = findLevel('R2');
        const r1 = findLevel('R1');
        const pivot = findLevel('PIVOT') || (playbook.central_pivot ? parseFloat(playbook.central_pivot) : null);
        const s1 = findLevel('S1');
        const s2 = findLevel('S2');
        const s3 = findLevel('S3');

        const setEl = (id, val) => {
            const el = document.getElementById(id);
            if (!el) return;
            if (val !== null && val !== undefined && !isNaN(val)) {
                el.textContent = '₹' + Number(val).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            } else {
                el.textContent = '--';
            }
        };

        setEl('quant-val-r3', r3);
        setEl('quant-val-r2', r2);
        setEl('quant-val-r1', r1);
        setEl('quant-val-pivot', pivot);
        setEl('quant-val-s1', s1);
        setEl('quant-val-s2', s2);
        setEl('quant-val-s3', s3);

        const bullEl = document.getElementById('quant-lbl-bull-above');
        if (bullEl && pivot) bullEl.textContent = `Bull Above ₹${Math.round(pivot).toLocaleString('en-IN')}`;
        const bearEl = document.getElementById('quant-lbl-bear-below');
        if (bearEl && pivot) bearEl.textContent = `Bear Below ₹${Math.round(pivot).toLocaleString('en-IN')}`;

        const reportLink = document.getElementById('quant-link-report');
        if (reportLink && typeof selectedDate !== 'undefined') {
            reportLink.href = `/order-flow-depth/report?date=${selectedDate}`;
        }

        const quantSyncEl = document.getElementById('quant-last-sync-time');
        if (quantSyncEl) {
            const now = new Date();
            quantSyncEl.textContent = String(now.getHours()).padStart(2, '0') + ':' +
                                      String(now.getMinutes()).padStart(2, '0') + ':' +
                                      String(now.getSeconds()).padStart(2, '0');
        }

        // Redraw price lines on both charts with updated levels
        updateQuantLinesButtonUI();
        drawQuantPriceLines();
    }
    window.updateQuantLevelsUI = updateQuantLevelsUI;

    function getQuantLevelConfig(code, price) {
        let color = '#f59e0b';
        let lineWidth = 1;
        let lineStyle = 2; // Dashed

        if (code === 'R3') {
            color = '#ef4444';
            lineStyle = 2;
        } else if (code === 'R2') {
            color = '#f87171';
            lineStyle = 2;
        } else if (code === 'R1') {
            color = '#fca5a5';
            lineStyle = 3; // Dotted
        } else if (code === 'PIVOT') {
            color = '#f59e0b';
            lineWidth = 2;
            lineStyle = 0; // Solid
        } else if (code === 'S1') {
            color = '#6ee7b7';
            lineStyle = 3; // Dotted
        } else if (code === 'S2') {
            color = '#34d399';
            lineStyle = 2;
        } else if (code === 'S3') {
            color = '#10b981';
            lineStyle = 2;
        }

        return {
            color,
            lineWidth,
            lineStyle,
            title: `${code}: ₹${Math.round(price).toLocaleString()}`,
        };
    }

    function clearWeightageQuantLines() {
        if (wCandleSeries && quantPriceLinesWeightage.length > 0) {
            quantPriceLinesWeightage.forEach(line => {
                try { wCandleSeries.removePriceLine(line); } catch (e) {}
            });
        }
        quantPriceLinesWeightage = [];
    }
    window.clearWeightageQuantLines = clearWeightageQuantLines;

    function drawWeightageQuantLines() {
        clearWeightageQuantLines();
        if (!wShowQuantLines || !quantPlaybookData || !quantPlaybookData.levels || !wCandleSeries) return;

        quantPlaybookData.levels.forEach(lvl => {
            const price = parseFloat(lvl.price);
            if (!price || isNaN(price)) return;
            const cfg = getQuantLevelConfig(lvl.code, price);
            try {
                const line = wCandleSeries.createPriceLine({
                    price: price,
                    color: cfg.color,
                    lineWidth: cfg.lineWidth,
                    lineStyle: cfg.lineStyle,
                    axisLabelVisible: true,
                    title: cfg.title,
                });
                quantPriceLinesWeightage.push(line);
            } catch (e) {
                console.warn('Could not draw price line on Weightage chart for', lvl.code, e);
            }
        });
    }
    window.drawWeightageQuantLines = drawWeightageQuantLines;

    function clearOptionsQuantLines() {
        if (oCandleSeries && quantPriceLinesOptions.length > 0) {
            quantPriceLinesOptions.forEach(line => {
                try { oCandleSeries.removePriceLine(line); } catch (e) {}
            });
        }
        quantPriceLinesOptions = [];
    }
    window.clearOptionsQuantLines = clearOptionsQuantLines;

    function drawOptionsQuantLines() {
        clearOptionsQuantLines();
        if (!oShowQuantLines || !quantPlaybookData || !quantPlaybookData.levels || !oCandleSeries) return;

        quantPlaybookData.levels.forEach(lvl => {
            const price = parseFloat(lvl.price);
            if (!price || isNaN(price)) return;
            const cfg = getQuantLevelConfig(lvl.code, price);
            try {
                const line = oCandleSeries.createPriceLine({
                    price: price,
                    color: cfg.color,
                    lineWidth: cfg.lineWidth,
                    lineStyle: cfg.lineStyle,
                    axisLabelVisible: true,
                    title: cfg.title,
                });
                quantPriceLinesOptions.push(line);
            } catch (e) {
                console.warn('Could not draw price line on Options chart for', lvl.code, e);
            }
        });
    }
    window.drawOptionsQuantLines = drawOptionsQuantLines;

    function drawQuantPriceLines() {
        drawWeightageQuantLines();
        drawOptionsQuantLines();
    }
    window.drawQuantPriceLines = drawQuantPriceLines;

    function clearQuantPriceLines() {
        clearWeightageQuantLines();
        clearOptionsQuantLines();
    }
    window.clearQuantPriceLines = clearQuantPriceLines;

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
            case 'bear_drop':
            case 'bear_growl': {
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
            default: {
                // Defensive fallback tone if an unmapped sound name is passed
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
        }
    }

    // ── 2. 0Click.in Trade Webhook Calculation & Dispatch Integration ─────────────
    // Dynamic Stop Loss (Candle High/Low ± Buffer) & Multi-Target Logic (TP1: 100 pts, TP2: 150 pts)
    function calculateTradeLevels(direction, spotPrice, candle = null) {
        const isBull = (direction === 'UP' || direction === 'BULL' || direction === 'BUY');
        const slMode = zeroClickSettings.slMode || 'candle_hl';
        const slBuffer = parseInt(zeroClickSettings.slBuffer !== undefined ? zeroClickSettings.slBuffer : 15, 10);
        let tpPts = parseInt(zeroClickSettings.tpOffset || 100, 10);
        let tp2Pts = parseInt(zeroClickSettings.tp2Offset || 150, 10);
        // Safety guard: TP1 is always the primary / closer target, TP2 is the runner / extended target
        if (tpPts > tp2Pts) {
            const temp = tpPts;
            tpPts = tp2Pts;
            tp2Pts = temp;
        }

        let sl = 0;
        let slNote = '';
        let riskPts = 0;

        if (slMode === 'candle_hl' && candle && (candle.low !== undefined || candle.high !== undefined)) {
            const candleLow = Number(candle.low !== undefined ? candle.low : (spotPrice - 30));
            const candleHigh = Number(candle.high !== undefined ? candle.high : (spotPrice + 30));

            if (isBull) {
                // Bull move: Invalidation is below the alarm candle Low minus buffer
                sl = +(candleLow - slBuffer).toFixed(2);
                riskPts = +(spotPrice - sl).toFixed(2);
                slNote = `Low -${slBuffer}`;
            } else {
                // Bear move: Invalidation is above the alarm candle High plus buffer
                sl = +(candleHigh + slBuffer).toFixed(2);
                riskPts = +(sl - spotPrice).toFixed(2);
                slNote = `High +${slBuffer}`;
            }
        } else {
            // Fixed offset mode
            const fixedOffset = parseInt(zeroClickSettings.slOffset || 100, 10);
            sl = isBull ? +(spotPrice - fixedOffset).toFixed(2) : +(spotPrice + fixedOffset).toFixed(2);
            riskPts = fixedOffset;
            slNote = `Fixed ${fixedOffset}`;
        }

        if (riskPts <= 0) riskPts = 20;

        // Targets: TP1 (Primary) and TP2 (Extended)
        const tp1 = isBull ? +(spotPrice + tpPts).toFixed(2) : +(spotPrice - tpPts).toFixed(2);
        const tp2 = isBull ? +(spotPrice + tp2Pts).toFixed(2) : +(spotPrice - tp2Pts).toFixed(2);

        // Risk-to-Reward Ratio based on TP1 and Risk
        const rr = +(tpPts / riskPts).toFixed(1);

        return {
            isBull,
            spotPrice,
            sl,
            slNote,
            riskPts,
            tp1,
            tp2,
            tpPts,
            tp2Pts,
            rr
        };
    }

    // Bullish (UP/BUY alert): SELL nearest ITM PE in 100s (Strike > Spot) -> Math.floor(spot/100 + 1) * 100
    // Bearish (DOWN/SELL alert): SELL nearest ITM CE in 100s (Strike < Spot) -> Math.ceil(spot/100 - 1) * 100
    function calculateOptionSellContract(direction, spotPrice, candle = null) {
        const isBull = (direction === 'UP' || direction === 'BULL' || direction === 'BUY');
        const signal = isBull ? 'BUY' : 'SELL';
        const instrument = isBull ? 'PE' : 'CE';
        const strike = isBull
            ? (Math.floor(spotPrice / 100.0) + 1) * 100
            : (Math.ceil(spotPrice / 100.0) - 1) * 100;
        const strikeName = `NIFTY ${strike} ${instrument}`;

        // Calculate dynamic SL and TP (Candle High/Low ± Buffer and Fixed 100/150 Targets)
        const levels = calculateTradeLevels(direction, spotPrice, candle);

        return {
            isBull,
            signal,
            action: 'SELL',
            instrument,
            strike,
            strikeName,
            sl: levels.sl,
            slNote: levels.slNote,
            tp: levels.tp1,
            tp1: levels.tp1,
            tp2: levels.tp2,
            tpPts: levels.tpPts,
            tp2Pts: levels.tp2Pts,
            riskPts: levels.riskPts,
            rr: levels.rr,
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
                sl: levels.sl,
                tp: levels.tp1,
                tp2: levels.tp2,
                sl_mode: zeroClickSettings.slMode || 'candle_hl',
                sl_buffer: zeroClickSettings.slBuffer || 15,
                risk_pts: levels.riskPts,
                rr_ratio: `1:${levels.rr}`,
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
                    const isAlertBull = (alertData.direction === 'UP' || alertData.direction === 'BULL' || alertData.direction === 'BUY');
                    pill.textContent = data.log.status === 'SENT' ? '🚀 Dispatched' : `💾 Log(#${data.log.id})`;
                    pill.className = data.log.status === 'SENT'
                        ? (isAlertBull
                            ? 'px-1.5 py-0.2 rounded text-[9px] font-mono bg-emerald-500/25 text-emerald-200 border border-emerald-400/50 whitespace-nowrap'
                            : 'px-1.5 py-0.2 rounded text-[9px] font-mono bg-rose-500/25 text-rose-200 border border-rose-400/50 whitespace-nowrap')
                        : 'px-1.5 py-0.2 rounded text-[9px] font-mono bg-sky-500/25 text-sky-200 border border-sky-400/50 whitespace-nowrap';
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
        .then(res => {
            if (!res.ok) return null;
            return res.json();
        })
        .then(data => {
            if (data && data.status === 'success') {
                cachedLogsList = data.logs || [];
                loggedSignalsSet = new Set(cachedLogsList.map(l => `${l.trade_date}_${l.candle_time}_${l.signal || (l.action === 'BUY' ? 'BUY' : 'SELL')}`));
                const logCountBadge = document.getElementById('log-count-badge');
                const modalLogCountBadge = document.getElementById('modal-log-count-badge');
                if (logCountBadge) logCountBadge.textContent = cachedLogsList.length;
                if (modalLogCountBadge) modalLogCountBadge.textContent = `${cachedLogsList.length} Signal${cachedLogsList.length === 1 ? '' : 's'}`;
                if (renderTable) renderLogsTable();
            }
        })
        .catch(err => console.warn('Could not fetch alert logs (non-fatal):', err));
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

        // If triggerOnClose is enabled, do not fire alarm modal/audio on an uncompleted forming candle!
        // Wait until candle closes to guarantee zero repaint & confirmed 50-stock data.
        if (alarmSettings.triggerOnClose !== false && isLiveMarketOpen()) {
            const isStkForming = (wBreadth && wBreadth.is_complete === false);
            const isOptForming = (oBreadth && oBreadth.is_complete === false);
            if (isStkForming || isOptForming) {
                return;
            }
        }

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

        // Find the matching candle for precise Low/High invalidation levels
        const triggerCandle = weightageData?.candles?.find(c => (c.time_str === timeStr || c.time === timeStr))
            || breadthData?.nifty_candles?.find(c => (c.time_str === timeStr || c.time === timeStr))
            || weightageData?.candles?.[activeCandleIndex]
            || breadthData?.nifty_candles?.[activeCandleIndex];

        const candleTime = triggerCandle ? Number(triggerCandle.time || triggerCandle.timestamp) : null;
        const wHaAnalysis = candleTime ? getCandleHaWickAnalysis(candleTime, false, timeStr) : null;
        const oHaAnalysis = candleTime ? getCandleHaWickAnalysis(candleTime, true, timeStr) : null;

        // 1. COMBINED HIGH-PROBABILITY CONFLUENCE (Both Weightage and Options Agree in Same Direction!)
        if (alarmSettings.confluenceEnabled && ((wBull && oBull) || (wBear && oBear))) {
            const isBull = (wBull && oBull);
            const useStocksHa = (alarmSettings.stocksHaWickFilter !== false);
            const useOptionsHa = (alarmSettings.optionsHaWickFilter !== false);
            if (isBull) {
                if (useStocksHa && (!wHaAnalysis || wHaAnalysis.hasBothWicks || !wHaAnalysis.isCleanBullEntry)) return;
                if (useOptionsHa && (!oHaAnalysis || oHaAnalysis.hasBothWicks || !oHaAnalysis.isCleanBullEntry)) return;
            } else {
                if (useStocksHa && (!wHaAnalysis || wHaAnalysis.hasBothWicks || !wHaAnalysis.isCleanBearEntry)) return;
                if (useOptionsHa && (!oHaAnalysis || oHaAnalysis.hasBothWicks || !oHaAnalysis.isCleanBearEntry)) return;
            }
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
                isConfluence: true,
                confluenceState: 'CONFLUENCE',
                confluenceText: isBull
                    ? `Stocks (${Math.round(wBreadth?.green_weight || 0)}% Green) and Options (${Math.round(oBreadth?.up_weight || 0)}% Call Flow) in 100% Alignment.`
                    : `Stocks (${Math.round(wBreadth?.red_weight || 0)}% Red) and Options (${Math.round(oBreadth?.down_weight || 0)}% Put Flow) in 100% Alignment.`,
                candle: triggerCandle
            });
            return;
        }

        // 2. Individual Nifty 50 Stocks Breadth Alert (Independent Chart 1 Settings)
        if (alarmSettings.stocksAlertEnabled && (wBull || wBear)) {
            const useStocksHaFilter = (alarmSettings.stocksHaWickFilter !== false);
            if (useStocksHaFilter) {
                if (!wHaAnalysis || wHaAnalysis.hasBothWicks) return;
                if (wBear && !wHaAnalysis.isCleanBearEntry) return;
                if (wBull && !wHaAnalysis.isCleanBullEntry) return;
            }
            const isBull = wBull;
            let confState = 'NEUTRAL';
            let confText = '';
            if (oBreadth) {
                if (isBull && oBear) {
                    confState = 'DIVERGENCE';
                    confText = `Stocks Bullish (${(wBreadth?.green_weight || 0).toFixed(0)}%), but Options show ${Math.round(oBreadth.down_weight)}% Bear Flow.`;
                } else if (!isBull && oBull) {
                    confState = 'DIVERGENCE';
                    confText = `Stocks Bearish (${(wBreadth?.red_weight || 0).toFixed(0)}%), but Options show ${Math.round(oBreadth.up_weight)}% Bull Flow.`;
                } else if ((isBull && oBreadth.up_weight >= 60) || (!isBull && oBreadth.down_weight >= 60)) {
                    confState = 'CONFLUENCE';
                    confText = isBull
                        ? `Options confirms move with ${Math.round(oBreadth.up_weight)}% Bull Flow.`
                        : `Options confirms move with ${Math.round(oBreadth.down_weight)}% Bear Flow.`;
                } else {
                    confState = 'NEUTRAL';
                    confText = `Options breadth is neutral (${Math.round(oBreadth.up_weight)}% Bull vs ${Math.round(oBreadth.down_weight)}% Bear).`;
                }
            }
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
                isConfluence: (confState === 'CONFLUENCE'),
                confluenceState: confState,
                confluenceText: confText,
                candle: triggerCandle
            });
            return;
        }

        // 3. Individual Nifty Options Breadth Alert (Independent Chart 2 Settings)
        if (alarmSettings.optionsAlertEnabled && (oBull || oBear)) {
            const useOptionsHaFilter = (alarmSettings.optionsHaWickFilter !== false);
            if (useOptionsHaFilter) {
                if (!oHaAnalysis || oHaAnalysis.hasBothWicks) return;
                if (oBear && !oHaAnalysis.isCleanBearEntry) return;
                if (oBull && !oHaAnalysis.isCleanBullEntry) return;
            }
            const isBull = oBull;
            let confState = 'NEUTRAL';
            let confText = '';
            if (wBreadth) {
                if (isBull && wBreadth.red_weight >= 60) {
                    confState = 'DIVERGENCE';
                    confText = `Options Bull Flow (${optionsScore}%), but Stocks show ${(wBreadth.red_weight).toFixed(0)}% Red Weight.`;
                } else if (!isBull && wBreadth.green_weight >= 60) {
                    confState = 'DIVERGENCE';
                    confText = `Options Bear Flow (${optionsScore}%), but Stocks show ${(wBreadth.green_weight).toFixed(0)}% Green Weight.`;
                } else if ((isBull && wBreadth.green_weight >= 65) || (!isBull && wBreadth.red_weight >= 65)) {
                    confState = 'CONFLUENCE';
                    confText = isBull
                        ? `Stocks confirms move with ${(wBreadth.green_weight).toFixed(0)}% Green Weight.`
                        : `Stocks confirms move with ${(wBreadth.red_weight).toFixed(0)}% Red Weight.`;
                } else {
                    confState = 'NEUTRAL';
                    confText = `Stocks breadth is balanced (${(wBreadth.green_weight).toFixed(0)}% G / ${(wBreadth.red_weight).toFixed(0)}% R).`;
                }
            }
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
                isConfluence: (confState === 'CONFLUENCE'),
                confluenceState: confState,
                confluenceText: confText,
                candle: triggerCandle
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

        // Spot Price from active candle or trigger candle
        const currentCandle = data.candle
            || weightageData?.candles?.[activeCandleIndex]
            || weightageData?.candles?.slice(-1)[0];
        const spotPrice = currentCandle ? Number(currentCandle.close) : 22928.10;
        const isBull = (data.direction === 'BULL' || data.direction === 'UP');

        // Strikes calculation:
        // Nifty strike interval is 50 pts
        const atmStrike = Math.round(spotPrice / 50.0) * 50;
        const buyerInstrument = isBull ? 'CE' : 'PE';
        // 1-ITM: for Bull CE strike <= spot (atmStrike - 50); for Bear PE strike >= spot (atmStrike + 50)
        const buyerStrike = isBull ? (atmStrike - 50) : (atmStrike + 50);

        // Option Sell Contract (1-ITM 100s, 0Click compatible with Candle Low/High SL and TP1/TP2 targets)
        const contract = calculateOptionSellContract(data.direction, spotPrice, currentCandle);
        currentActivePayload = contract.payload;

        // Banner Badge & Styling
        const bannerBadge = document.getElementById('alarm-banner-badge');
        const badgeLeftIcon = document.getElementById('alarm-badge-left-icon');
        const badgeTitle = document.getElementById('alarm-badge-title');
        const badgeRightIcon = document.getElementById('alarm-badge-right-icon');
        const card = document.getElementById('active-alarm-card');
        const icon = document.getElementById('alarm-sentiment-icon');

        if (bannerBadge) {
            bannerBadge.className = isBull
                ? 'px-3.5 py-1 rounded-full font-black text-xs uppercase tracking-wider text-emerald-200 shadow-md bg-emerald-950/80 border border-emerald-500/60 flex items-center gap-2'
                : 'px-3.5 py-1 rounded-full font-black text-xs uppercase tracking-wider text-rose-200 shadow-md bg-rose-950/80 border border-rose-500/60 flex items-center gap-2';
        }
        if (badgeLeftIcon) badgeLeftIcon.textContent = isBull ? '🟢' : '🔴';
        if (badgeTitle) badgeTitle.textContent = isBull ? 'STRONG BULL CALL (LONG BIAS)' : 'STRONG BEAR CALL (SHORT BIAS)';
        if (badgeRightIcon) badgeRightIcon.textContent = isBull ? '🐂' : '🐻';

        if (icon) {
            icon.textContent = isBull ? '🐂' : '🐻';
            icon.className = isBull
                ? 'w-14 h-14 sm:w-16 sm:h-16 rounded-2xl flex items-center justify-center text-3xl sm:text-4xl shadow-lg border-2 select-none bg-emerald-500/20 border-emerald-400 text-emerald-300 shrink-0'
                : 'w-14 h-14 sm:w-16 sm:h-16 rounded-2xl flex items-center justify-center text-3xl sm:text-4xl shadow-lg border-2 select-none bg-rose-500/20 border-rose-400 text-rose-300 shrink-0';
        }

        if (card) {
            card.className = isBull
                ? 'rounded-2xl p-5 sm:p-6 max-w-xl w-full text-center space-y-3.5 shadow-2xl relative transition-all duration-300 bg-[#131722] border-2 border-emerald-500 shadow-emerald-950/70'
                : 'rounded-2xl p-5 sm:p-6 max-w-xl w-full text-center space-y-3.5 shadow-2xl relative transition-all duration-300 bg-[#131722] border-2 border-rose-500 shadow-rose-950/70';
        }

        // Time pill
        const timePill = document.getElementById('alarm-candle-time-pill');
        if (timePill) timePill.textContent = `⏰ ${data.time} Candle`;

        // Active Pct & Direction
        const pctEl = document.getElementById('alarm-active-pct');
        if (pctEl) {
            pctEl.textContent = `${data.score}%`;
            pctEl.className = `text-3xl sm:text-4xl font-black font-mono tracking-tight drop-shadow-md ${isBull ? 'text-emerald-400' : 'text-rose-400'}`;
        }
        const dirEl = document.getElementById('alarm-active-direction');
        if (dirEl) {
            dirEl.textContent = data.title;
            dirEl.className = `text-xs sm:text-sm font-black uppercase tracking-wider ${isBull ? 'text-emerald-300' : 'text-rose-300'}`;
        }

        // Details text
        const detailsEl = document.getElementById('alarm-active-details');
        if (detailsEl) {
            if (data.type === 'STOCKS' || data.type === 'CONFLUENCE') {
                detailsEl.innerHTML = `<strong>${data.count || 40} stocks ${isBull ? 'Green' : 'Red'}</strong> (${(data.weight || 80).toFixed(1)}% weight &bull; ${data.countPct || 80}% count) &bull; Threshold ${data.threshold}% breached!`;
            } else {
                detailsEl.innerHTML = `Options Flow surged to <strong>${data.score}% ${isBull ? 'Bullish' : 'Bearish'}</strong> &bull; Threshold ${data.threshold}% breached!`;
            }
        }

        // Spot Price & Net Points
        const priceEl = document.getElementById('alarm-trade-price');
        if (priceEl) priceEl.textContent = spotPrice.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        const ptsEl = document.getElementById('alarm-spot-pts');
        if (ptsEl) {
            const pts = data.pts || 0;
            ptsEl.textContent = `${pts >= 0 ? '+' : ''}${pts.toFixed(2)} pts`;
            ptsEl.className = `text-[10px] font-bold ${isBull ? 'text-emerald-400' : 'text-rose-400'}`;
        }

        // Market Alignment Card (Confluence vs Divergence)
        const alignCard = document.getElementById('alarm-alignment-card');
        const alignIcon = document.getElementById('alarm-alignment-icon');
        const alignBadge = document.getElementById('alarm-alignment-badge');
        const alignHead = document.getElementById('alarm-alignment-headline');
        const alignDesc = document.getElementById('alarm-alignment-desc');

        if (alignCard && alignBadge && alignHead && alignDesc) {
            if (data.confluenceState === 'CONFLUENCE' || data.isConfluence) {
                alignCard.className = isBull
                    ? 'p-2.5 rounded-xl border text-left text-xs transition flex items-center gap-2.5 bg-emerald-950/40 border-emerald-500/50 text-emerald-200'
                    : 'p-2.5 rounded-xl border text-left text-xs transition flex items-center gap-2.5 bg-rose-950/40 border-rose-500/50 text-rose-200';
                alignIcon.textContent = '🔥';
                alignBadge.className = isBull
                    ? 'px-1.5 py-0.2 rounded text-[10px] font-black tracking-wide uppercase bg-emerald-500/30 text-emerald-200 border border-emerald-500/50'
                    : 'px-1.5 py-0.2 rounded text-[10px] font-black tracking-wide uppercase bg-rose-500/30 text-rose-200 border border-rose-500/50';
                alignBadge.textContent = 'HIGH CONFLUENCE';
                alignHead.textContent = 'Stocks & Options Breadth in Strong Agreement!';
                alignHead.className = `text-[11px] font-bold ${isBull ? 'text-emerald-100' : 'text-rose-100'}`;
                alignDesc.textContent = data.confluenceText || `Both Weightage (${data.score}%) and Options (${data.oStat || 'Aligned'}) confirm the move. High probability setup.`;
            } else if (data.confluenceState === 'DIVERGENCE') {
                alignCard.className = 'p-2.5 rounded-xl border text-left text-xs transition flex items-center gap-2.5 bg-amber-950/40 border-amber-500/50 text-amber-200';
                alignIcon.textContent = '⚠️';
                alignBadge.className = 'px-1.5 py-0.2 rounded text-[10px] font-black tracking-wide uppercase bg-amber-500/30 text-amber-200 border border-amber-500/50';
                alignBadge.textContent = 'DIVERGENCE WARNING';
                alignHead.textContent = 'Options Breadth Opposes Stock Breadth!';
                alignHead.className = 'text-[11px] font-bold text-amber-100';
                alignDesc.textContent = `${data.confluenceText || 'Options and stocks flows are in opposing directions.'} Watch for traps or quick scalps only.`;
            } else {
                alignCard.className = isBull
                    ? 'p-2.5 rounded-xl border text-left text-xs transition flex items-center gap-2.5 bg-sky-950/30 border-sky-500/40 text-sky-200'
                    : 'p-2.5 rounded-xl border text-left text-xs transition flex items-center gap-2.5 bg-rose-950/30 border-rose-500/40 text-rose-200';
                alignIcon.textContent = '📊';
                alignBadge.className = isBull
                    ? 'px-1.5 py-0.2 rounded text-[10px] font-black tracking-wide uppercase bg-sky-500/30 text-sky-200 border border-sky-500/50'
                    : 'px-1.5 py-0.2 rounded text-[10px] font-black tracking-wide uppercase bg-rose-500/30 text-rose-200 border border-rose-500/50';
                alignBadge.textContent = 'SINGLE TRIGGER';
                alignHead.textContent = data.type === 'STOCKS' ? 'Stock Breadth Surge (Options Neutral)' : 'Options Flow Surge (Stocks Neutral)';
                alignHead.className = `text-[11px] font-bold ${isBull ? 'text-sky-100' : 'text-rose-100'}`;
                alignDesc.textContent = `${data.confluenceText || data.oStat || 'Relying primarily on single-market breadth momentum.'}`;
            }
        }

        // Trade Box: Option Buyer
        const buyerCard = document.getElementById('alarm-buyer-card');
        const buyerTitle = document.getElementById('alarm-buyer-title');
        const buyerMomentumBadge = document.getElementById('alarm-buyer-momentum-badge');
        const buyerFastMoveLabel = document.getElementById('alarm-buyer-fastmove-label');
        const buyerActionBadge = document.getElementById('alarm-buyer-action-badge');
        const buyerStrikeBadge = document.getElementById('alarm-buyer-strike-badge');
        const buyerItmLabel = document.getElementById('alarm-buyer-itm-label');
        const buyerStrategyNote = document.getElementById('alarm-buyer-strategy-note');

        if (buyerCard) {
            buyerCard.className = isBull
                ? 'p-3 rounded-xl bg-black/40 border border-emerald-500/30 hover:border-emerald-500/60 transition flex flex-col justify-between'
                : 'p-3 rounded-xl bg-black/40 border border-rose-500/30 hover:border-rose-500/60 transition flex flex-col justify-between';
        }
        if (buyerTitle) {
            buyerTitle.className = `text-[10px] font-bold uppercase tracking-wider flex items-center gap-1 ${isBull ? 'text-emerald-300' : 'text-rose-300'}`;
        }
        if (buyerMomentumBadge) {
            buyerMomentumBadge.className = isBull
                ? 'px-1.5 py-0.2 rounded text-[9px] font-mono bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                : 'px-1.5 py-0.2 rounded text-[9px] font-mono bg-rose-500/20 text-rose-300 border border-rose-500/40';
        }
        if (buyerFastMoveLabel) {
            buyerFastMoveLabel.className = `font-bold ${isBull ? 'text-emerald-400' : 'text-rose-400'}`;
        }
        if (buyerActionBadge) {
            buyerActionBadge.textContent = 'BUY';
            buyerActionBadge.className = isBull
                ? 'px-1.5 py-0.5 rounded font-black text-xs bg-emerald-600 text-white shadow-sm font-mono'
                : 'px-1.5 py-0.5 rounded font-black text-xs bg-rose-600 text-white shadow-sm font-mono';
        }
        if (buyerStrikeBadge) {
            buyerStrikeBadge.textContent = `${buyerStrike} ${buyerInstrument}`;
        }
        if (buyerItmLabel) {
            buyerItmLabel.textContent = '(1-ITM)';
        }
        if (buyerStrategyNote) {
            buyerStrategyNote.textContent = isBull ? 'Call Scalp / Directional' : 'Put Scalp / Directional';
        }

        // Trade Box: Option Seller (0Click Default)
        const sellerActionBadge = document.getElementById('alarm-trade-action-badge');
        const sellerStrikeBadge = document.getElementById('alarm-trade-strike-badge');
        const sellerItmLabel = document.getElementById('alarm-seller-itm-label');
        const sellerStrategyNote = document.getElementById('alarm-seller-strategy-note');

        if (sellerActionBadge) {
            sellerActionBadge.textContent = 'SELL';
            sellerActionBadge.className = isBull
                ? 'px-1.5 py-0.5 rounded font-black text-xs bg-emerald-600 text-white shadow-sm font-mono'
                : 'px-1.5 py-0.5 rounded font-black text-xs bg-amber-500 text-black shadow-sm font-mono';
        }
        if (sellerStrikeBadge) {
            sellerStrikeBadge.textContent = `${contract.strike} ${contract.instrument}`;
        }
        if (sellerItmLabel) {
            sellerItmLabel.textContent = '(1-ITM)';
        }
        if (sellerStrategyNote) {
            sellerStrategyNote.textContent = isBull ? 'Theta Put Decay' : 'Theta Call Decay';
        }

        // Levels (Dynamic SL with Candle Low/High Note, TP1, TP2, R:R Ratio)
        const levelsStrip = document.getElementById('alarm-levels-strip');
        if (levelsStrip) {
            levelsStrip.className = isBull
                ? 'p-2.5 rounded-xl bg-black/50 border border-emerald-500/30 font-mono text-[11px] flex flex-wrap items-center justify-between gap-y-1.5 gap-x-2 text-white'
                : 'p-2.5 rounded-xl bg-black/50 border border-rose-500/30 font-mono text-[11px] flex flex-wrap items-center justify-between gap-y-1.5 gap-x-2 text-white';
        }

        const slEl = document.getElementById('alarm-trade-sl');
        if (slEl) slEl.textContent = contract.sl.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        const slNoteEl = document.getElementById('alarm-trade-sl-note');
        if (slNoteEl) slNoteEl.textContent = `(${contract.slNote})`;

        const tp1Label = document.getElementById('alarm-trade-tp1-label');
        if (tp1Label) {
            tp1Label.className = isBull ? 'text-emerald-400 font-bold' : 'text-rose-400 font-bold';
        }
        const tpEl = document.getElementById('alarm-trade-tp');
        if (tpEl) {
            tpEl.textContent = contract.tp1.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            tpEl.className = isBull ? 'text-emerald-300 font-bold' : 'text-rose-300 font-bold';
        }
        const tp1PtsEl = document.getElementById('alarm-trade-tp1-pts');
        if (tp1PtsEl) {
            const tp1PtsVal = contract.tpPts || zeroClickSettings.tpOffset || 100;
            tp1PtsEl.textContent = `(${tp1PtsVal} pts)`;
            tp1PtsEl.className = `text-[9px] font-sans ${isBull ? 'text-emerald-400' : 'text-rose-400'}`;
        }

        const tp2Label = document.getElementById('alarm-trade-tp2-label');
        if (tp2Label) {
            tp2Label.className = isBull ? 'text-teal-300 font-bold' : 'text-red-400 font-bold';
        }
        const tp2El = document.getElementById('alarm-trade-tp2');
        if (tp2El) {
            tp2El.textContent = contract.tp2.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            tp2El.className = isBull ? 'text-teal-200 font-bold' : 'text-red-300 font-bold';
        }
        const tp2PtsEl = document.getElementById('alarm-trade-tp2-pts');
        if (tp2PtsEl) {
            const tp2PtsVal = contract.tp2Pts || zeroClickSettings.tp2Offset || 150;
            tp2PtsEl.textContent = `(${tp2PtsVal} pts)`;
            tp2PtsEl.className = `text-[9px] font-sans ${isBull ? 'text-teal-300' : 'text-red-400'}`;
        }

        const rrBadge = document.getElementById('alarm-trade-rr-badge');
        if (rrBadge) rrBadge.textContent = `1:${contract.rr} R:R`;

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

    function dismissAlarm() {
        stopAlarm();
    }

    // ── 3. Apply Independent Section Heights (User Customizable & Persistent) ──
    function setChart1Height(val) {
        const h = parseInt(val, 10);
        if (!h || isNaN(h)) return;
        currentChart1Height = h;
        localStorage.setItem('nifty_unified_chart1_height', h);

        const wContainer = document.getElementById('w-chart-container');
        if (wContainer) wContainer.style.height = `${h}px`;
        if (wChart && wContainer) {
            wChart.applyOptions({ height: h, width: wContainer.clientWidth });
        }
        const wPanel = document.getElementById('w-cockpit-panel');
        if (wPanel) {
            wPanel.style.height = `${h}px`;
            wPanel.style.maxHeight = `${h}px`;
        }
        const sel = document.getElementById('w-height-select');
        if (sel && sel.value !== String(h)) sel.value = String(h);

        if (typeof updateDaySeparators === 'function') updateDaySeparators();
        if (typeof updateWeightageTvDrawings === 'function') updateWeightageTvDrawings();
    }
    window.setChart1Height = setChart1Height;

    function setChart2Height(val) {
        const h = parseInt(val, 10);
        if (!h || isNaN(h)) return;
        currentChart2Height = h;
        localStorage.setItem('nifty_unified_chart2_height', h);

        const oContainer = document.getElementById('o-chart-container');
        if (oContainer) oContainer.style.height = `${h}px`;
        if (oChart && oContainer) {
            oChart.applyOptions({ height: h, width: oContainer.clientWidth });
        }
        const oPanel = document.getElementById('o-cockpit-panel');
        if (oPanel) {
            oPanel.style.height = `${h}px`;
            oPanel.style.maxHeight = `${h}px`;
        }
        const sel = document.getElementById('o-height-select');
        if (sel && sel.value !== String(h)) sel.value = String(h);

        if (typeof updateDaySeparators === 'function') updateDaySeparators();
        if (typeof updateOptionsTvDrawings === 'function') updateOptionsTvDrawings();
    }
    window.setChart2Height = setChart2Height;

    function setToiChartHeight(val) {
        const h = parseInt(val, 10);
        if (!h || isNaN(h)) return;
        currentToiHeight = h;
        localStorage.setItem('nifty_unified_toi_height', h);

        const el = document.getElementById('toi-chart-wrapper');
        if (el) el.style.height = `${h}px`;
        const sel = document.getElementById('toi-height-select');
        if (sel && sel.value !== String(h)) sel.value = String(h);

        if (toiChartInstance) toiChartInstance.resize();
    }
    window.setToiChartHeight = setToiChartHeight;

    function setBldChartHeight(val) {
        const h = parseInt(val, 10);
        if (!h || isNaN(h)) return;
        currentBldHeight = h;
        localStorage.setItem('nifty_unified_buildup_height', h);

        const el = document.getElementById('bld-chart-wrapper');
        if (el) el.style.height = `${h}px`;
        const elSyn = document.getElementById('synergy-chart-wrapper');
        if (elSyn) elSyn.style.height = `${h}px`;
        document.querySelectorAll('.bld-height-select').forEach(sel => {
            if (sel && sel.value !== String(h)) sel.value = String(h);
        });
        const sel = document.getElementById('bld-height-select');
        if (sel && sel.value !== String(h)) sel.value = String(h);

        if (buildupChartInstance) buildupChartInstance.resize();
        if (flowSynergyChartInstance) flowSynergyChartInstance.resize();
    }
    window.setBldChartHeight = setBldChartHeight;

    function applyAllSectionHeights() {
        setChart1Height(currentChart1Height);
        setChart2Height(currentChart2Height);
        setToiChartHeight(currentToiHeight);
        setBldChartHeight(currentBldHeight);
    }
    window.applyAllSectionHeights = applyAllSectionHeights;

    function applyChartHeight(h) {
        setChart1Height(h);
        setChart2Height(h);
    }
    window.applyChartHeight = applyChartHeight;

        // ── 3.1 Apply Dynamic Cockpit Font Scale (User Requested) ──────────────────────
    function applyCockpitFontScale(scale) {
        currentCockpitFontScale = scale;
        localStorage.setItem('nifty_unified_font_scale', scale);

        const select = document.getElementById('cockpit-font-scale-select');
        if (select && select.value !== String(scale)) {
            select.value = String(scale);
        }

        const scaleFactor = (scale / 100).toFixed(2);
        document.documentElement.style.setProperty('--cockpit-scale', scaleFactor);

        // Apply zoom to all cockpit data panels
        const panels = document.querySelectorAll('.cockpit-data-panel');
        panels.forEach(p => {
            p.style.zoom = scaleFactor;
        });
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
                barSpacing: 10,
                minBarSpacing: 3,
                fixLeftEdge: false,
                fixRightEdge: false,
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
            wContainer.style.height = `${currentChart1Height}px`;
            if (wChart) {
                try { wChart.remove(); } catch(e){}
                wChart = null;
            } else {
                const oldLightweight = wContainer.querySelectorAll('.tv-lightweight-charts');
                oldLightweight.forEach(c => c.remove());
            }
            wChart = LightweightCharts.createChart(wContainer, {
                ...chartOptions,
                width: wContainer.clientWidth,
                height: currentChart1Height,
            });

            wCandleSeries = wChart.addCandlestickSeries({
                upColor: '#10b981',
                downColor: '#ef4444',
                borderUpColor: '#10b981',
                borderDownColor: '#ef4444',
                wickUpColor: '#10b981',
                wickDownColor: '#ef4444',
            });

            // SuperTrend Line Series (Green Bull Support & Red Bear Resistance)
            wStBullSeries = wChart.addLineSeries({
                color: '#10b981',
                lineWidth: 2,
                priceLineVisible: false,
                lastValueVisible: false,
                crosshairMarkerVisible: false,
            });
            wStBearSeries = wChart.addLineSeries({
                color: '#ef4444',
                lineWidth: 2,
                priceLineVisible: false,
                lastValueVisible: false,
                crosshairMarkerVisible: false,
            });

            wChart.subscribeClick(param => {
                if (wActiveTvTool === 'hline') {
                    if (param && param.point) {
                        const price = wCandleSeries.coordinateToPrice(param.point.y);
                        if (price && !isNaN(price)) {
                            addWeightageManualHLine(Math.round(price * 10) / 10);
                            setActiveWeightageTvTool('pointer');
                        }
                    }
                    return;
                }
                if (wActiveTvTool === 'fib_manual') {
                    if (param && param.point) {
                        const price = wCandleSeries.coordinateToPrice(param.point.y);
                        if (price && !isNaN(price)) {
                            handleWeightageManualFibClick(Math.round(price * 10) / 10);
                        }
                    }
                    return;
                }

                const raw = weightageData?.candles || weightageData?.nifty_candles;
                if (!param || !param.time || !raw) return;
                const pTime = Number(param.time);
                const candle = raw.find(c => Number(c.time || c.timestamp) === pTime);
                if (candle && candle.time_str) {
                    const idx = timestampsList.indexOf(candle.time_str);
                    if (idx !== -1) syncActiveCandleIndex(idx);
                } else {
                    const idx = raw.findIndex(c => Number(c.time || c.timestamp) === pTime);
                    if (idx !== -1) syncActiveCandleIndex(idx);
                }
            });
        }

        // Row 2: Options Breadth Candlestick Chart (75% width)
        const oContainer = document.getElementById('o-chart-container');
        if (oContainer) {
            oContainer.style.height = `${currentChart2Height}px`;
            if (oChart) {
                try { oChart.remove(); } catch(e){}
                oChart = null;
            } else {
                const oldLightweight = oContainer.querySelectorAll('.tv-lightweight-charts');
                oldLightweight.forEach(c => c.remove());
            }
            oChart = LightweightCharts.createChart(oContainer, {
                ...chartOptions,
                width: oContainer.clientWidth,
                height: currentChart2Height,
            });

            oCandleSeries = oChart.addCandlestickSeries({
                upColor: '#10b981',
                downColor: '#ef4444',
                borderUpColor: '#10b981',
                borderDownColor: '#ef4444',
                wickUpColor: '#10b981',
                wickDownColor: '#ef4444',
            });

            // SuperTrend Line Series (Green Bull Support & Red Bear Resistance)
            oStBullSeries = oChart.addLineSeries({
                color: '#10b981',
                lineWidth: 2,
                priceLineVisible: false,
                lastValueVisible: false,
                crosshairMarkerVisible: false,
            });
            oStBearSeries = oChart.addLineSeries({
                color: '#ef4444',
                lineWidth: 2,
                priceLineVisible: false,
                lastValueVisible: false,
                crosshairMarkerVisible: false,
            });

            oChart.subscribeClick(param => {
                if (oActiveTvTool === 'hline') {
                    if (param && param.point) {
                        const price = oCandleSeries.coordinateToPrice(param.point.y);
                        if (price && !isNaN(price)) {
                            addOptionsManualHLine(Math.round(price * 10) / 10);
                            setActiveOptionsTvTool('pointer');
                        }
                    }
                    return;
                }
                if (oActiveTvTool === 'fib_manual') {
                    if (param && param.point) {
                        const price = oCandleSeries.coordinateToPrice(param.point.y);
                        if (price && !isNaN(price)) {
                            handleOptionsManualFibClick(Math.round(price * 10) / 10);
                        }
                    }
                    return;
                }

                const raw = breadthData?.nifty_candles;
                if (!param || !param.time || !raw) return;
                const pTime = Number(param.time);
                const candle = raw.find(c => Number(c.time || c.timestamp) === pTime);
                if (candle && candle.time_str) {
                    const idx = timestampsList.indexOf(candle.time_str);
                    if (idx !== -1) syncActiveCandleIndex(idx);
                } else {
                    const idx = raw.findIndex(c => Number(c.time || c.timestamp) === pTime);
                    if (idx !== -1) syncActiveCandleIndex(idx);
                }
            });
        }

        if (wChart) {
            wChart.timeScale().subscribeVisibleTimeRangeChange(updateDaySeparators);
            wChart.timeScale().subscribeVisibleLogicalRangeChange(updateDaySeparators);
        }
        if (oChart) {
            oChart.timeScale().subscribeVisibleTimeRangeChange(updateDaySeparators);
            oChart.timeScale().subscribeVisibleLogicalRangeChange(updateDaySeparators);
        }

        window.addEventListener('resize', () => {
            if (wChart && wContainer) wChart.applyOptions({ width: wContainer.clientWidth });
            if (oChart && oContainer) oChart.applyOptions({ width: oContainer.clientWidth });
            if (toiChartInstance) toiChartInstance.resize();
            if (buildupChartInstance) buildupChartInstance.resize();
            updateDaySeparators();
        });

        updateQuantLinesButtonUI();
        updateOfSrButtonUI();
        drawQuantPriceLines();
        drawWeightageOrderFlowLines();
        drawOptionsOrderFlowLines();
        updateTvToolsUI();
        updateCandleStyleUI();
        updateChartBasisUI();
        syncBuildupTabUI();
        syncSynergyControlsUI();
        renderAllChartOverlays();
    }

    // ── 5. Independent Chart Data Fetchers (Fetches Both Stocks & Options for Respective Timeframe) ──
    async function reloadChart1Weightage(isSilent = false) {
        if (!isSilent) document.getElementById('w-chart-loading')?.classList.remove('hidden');
        const minutes = getTfMinutes(wTimeframe);
        const bMode = alarmSettings.optionsBasketMode || 'fixed';
        try {
            const [stkRes, optRes] = await Promise.all([
                fetch(`/api/nifty-weightage-monitor/data?date=${selectedDate}&interval=${minutes}&basis=${wChartBasis}&_t=${Date.now()}`).then(r => r.json()),
                fetch(`/api/options-breadth-monitor/data?symbol=NIFTY&date=${selectedDate}&range=5&expiry=${selectedExpiry}&basket_mode=${bMode}&interval=${minutes}&basis=${wChartBasis}&_t=${Date.now()}`).then(r => r.json())
            ]);

            if (stkRes && !stkRes.error) {
                wWeightageData = stkRes;
                if (!wWeightageData.candles && wWeightageData.nifty_candles) {
                    wWeightageData.candles = wWeightageData.nifty_candles;
                }
                if (wWeightageData.candles) {
                    wWeightageData.candles.forEach(c => {
                        if (!c.time && c.timestamp) c.time = c.timestamp;
                    });
                }
                weightageData = wWeightageData;
            }

            if (optRes && optRes.success !== false && !optRes.error) {
                wBreadthData = optRes;
                if (wBreadthData.nifty_candles) {
                    wBreadthData.nifty_candles.forEach(c => {
                        if (!c.time && c.timestamp) c.time = c.timestamp;
                    });
                }
            }

            renderWeightageColumn();
            if (breadthData) renderOptionsBreadthColumn();
            buildMasterTimeline();
            updateCandleStyleUI();
        updateChartBasisUI();
        syncBuildupTabUI();
        syncSynergyControlsUI();
            if (typeof syncActiveCandleIndex === 'function' && typeof activeCandleIndex !== 'undefined') {
                syncActiveCandleIndex(activeCandleIndex);
            }
        } catch (e) {
            console.error('Error reloading Chart 1 data:', e);
        } finally {
            document.getElementById('w-chart-loading')?.classList.add('hidden');
        }
    }
    window.reloadChart1Weightage = reloadChart1Weightage;

    async function reloadChart2Options(isSilent = false) {
        if (!isSilent) document.getElementById('o-chart-loading')?.classList.remove('hidden');
        const minutes = getTfMinutes(oTimeframe);
        const bMode = alarmSettings.optionsBasketMode || 'fixed';
        try {
            const [stkRes, optRes] = await Promise.all([
                fetch(`/api/nifty-weightage-monitor/data?date=${selectedDate}&interval=${minutes}&basis=${oChartBasis}&_t=${Date.now()}`).then(r => r.json()),
                fetch(`/api/options-breadth-monitor/data?symbol=NIFTY&date=${selectedDate}&range=5&expiry=${selectedExpiry}&basket_mode=${bMode}&interval=${minutes}&basis=${oChartBasis}&_t=${Date.now()}`).then(r => r.json())
            ]);

            if (stkRes && !stkRes.error) {
                oWeightageData = stkRes;
                if (!oWeightageData.candles && oWeightageData.nifty_candles) {
                    oWeightageData.candles = oWeightageData.nifty_candles;
                }
                if (oWeightageData.candles) {
                    oWeightageData.candles.forEach(c => {
                        if (!c.time && c.timestamp) c.time = c.timestamp;
                    });
                }
            }

            if (optRes && optRes.success !== false && !optRes.error) {
                oBreadthData = optRes;
                breadthData = oBreadthData;
                if (breadthData.expiry && breadthData.expiry !== selectedExpiry) {
                    selectedExpiry = breadthData.expiry;
                    const expSelect = document.getElementById('expiry-select');
                    if (expSelect) expSelect.value = selectedExpiry;
                }
                if (breadthData.nifty_candles) {
                    breadthData.nifty_candles.forEach(c => {
                        if (!c.time && c.timestamp) c.time = c.timestamp;
                    });
                }
            }

            renderOptionsBreadthColumn();
            if (weightageData) renderWeightageColumn();
            buildMasterTimeline();
            updateCandleStyleUI();
        updateChartBasisUI();
        syncBuildupTabUI();
        syncSynergyControlsUI();
            if (typeof syncActiveCandleIndex === 'function' && typeof activeCandleIndex !== 'undefined') {
                syncActiveCandleIndex(activeCandleIndex);
            }
        } catch (e) {
            console.error('Error reloading Chart 2 data:', e);
        } finally {
            document.getElementById('o-chart-loading')?.classList.add('hidden');
        }
    }
    window.reloadChart2Options = reloadChart2Options;

    // ── 5. Unified Data Fetcher ───────────────────────────────────────────────────
    function updateDaySeparators() {
        const isLiveDate = (typeof isLiveToday === 'function' ? isLiveToday() : (selectedDate === new Date().toISOString().slice(0, 10)));
        const labelText = isLiveDate ? 'TODAY' : selectedDate;

        const sepW = document.getElementById('w-day-separator');
        const containerW = document.getElementById('w-chart-container');
        if (wChart && sepW && containerW) {
            try {
                const timeTd = window._wTodayFirstTime;
                const timeYd = window._wYesterdayLastTime;

                let x = null;
                const barSpacing = (typeof wChart.timeScale?.().options === 'function' ? wChart.timeScale().options().barSpacing : 6) || 6;

                if (timeTd && timeYd) {
                    const xTd = wChart.timeScale().timeToCoordinate(timeTd);
                    const xYd = wChart.timeScale().timeToCoordinate(timeYd);
                    if (xTd !== null && xYd !== null) {
                        x = (xTd + xYd) / 2;
                    } else if (xTd !== null) {
                        x = xTd - (barSpacing / 2);
                    } else if (xYd !== null) {
                        x = xYd + (barSpacing / 2);
                    }
                } else if (timeTd) {
                    const xTd = wChart.timeScale().timeToCoordinate(timeTd);
                    if (xTd !== null) x = xTd - (barSpacing / 2);
                } else if (timeYd) {
                    const xYd = wChart.timeScale().timeToCoordinate(timeYd);
                    if (xYd !== null) x = xYd + (barSpacing / 2);
                }

                const maxW = containerW.clientWidth || 99999;
                if (x !== null && !isNaN(x) && x >= 0 && x <= maxW) {
                    sepW.style.left = `${Math.round(x)}px`;
                    const lblW = document.getElementById('w-day-sep-label');
                    if (lblW) lblW.textContent = labelText;
                    sepW.classList.remove('hidden');
                } else {
                    sepW.classList.add('hidden');
                }
            } catch (e) {
                sepW.classList.add('hidden');
            }
        } else if (sepW) {
            sepW.classList.add('hidden');
        }

        const sepO = document.getElementById('o-day-separator');
        const containerO = document.getElementById('o-chart-container');
        if (oChart && sepO && containerO) {
            try {
                const timeTd = window._oTodayFirstTime;
                const timeYd = window._oYesterdayLastTime;

                let x = null;
                const barSpacing = (typeof oChart.timeScale?.().options === 'function' ? oChart.timeScale().options().barSpacing : 6) || 6;

                if (timeTd && timeYd) {
                    const xTd = oChart.timeScale().timeToCoordinate(timeTd);
                    const xYd = oChart.timeScale().timeToCoordinate(timeYd);
                    if (xTd !== null && xYd !== null) {
                        x = (xTd + xYd) / 2;
                    } else if (xTd !== null) {
                        x = xTd - (barSpacing / 2);
                    } else if (xYd !== null) {
                        x = xYd + (barSpacing / 2);
                    }
                } else if (timeTd) {
                    const xTd = oChart.timeScale().timeToCoordinate(timeTd);
                    if (xTd !== null) x = xTd - (barSpacing / 2);
                } else if (timeYd) {
                    const xYd = oChart.timeScale().timeToCoordinate(timeYd);
                    if (xYd !== null) x = xYd + (barSpacing / 2);
                }

                const maxW = containerO.clientWidth || 99999;
                if (x !== null && !isNaN(x) && x >= 0 && x <= maxW) {
                    sepO.style.left = `${Math.round(x)}px`;
                    const lblO = document.getElementById('o-day-sep-label');
                    if (lblO) lblO.textContent = labelText;
                    sepO.classList.remove('hidden');
                } else {
                    sepO.classList.add('hidden');
                }
            } catch (e) {
                sepO.classList.add('hidden');
            }
        } else if (sepO) {
            sepO.classList.add('hidden');
        }

        if (typeof updateActiveCandleHighlight === 'function') {
            updateActiveCandleHighlight();
        }
    }
    window.updateDaySeparators = updateDaySeparators;

    // ── Dedicated Enterprise Active Candle Highlighter ──────────────────────────
    let isCandleHighlightEnabled = false; // Default: hidden (clean live market viewing)
    let currentActiveCandleTimeStr = null;

    function toggleActiveCandleHighlight(forceState = null) {
        if (typeof forceState === 'boolean') {
            isCandleHighlightEnabled = forceState;
        } else {
            isCandleHighlightEnabled = !isCandleHighlightEnabled;
        }
        updateCandleHighlightButtonsUI();
        if (isCandleHighlightEnabled) {
            updateActiveCandleHighlight();
        } else {
            document.getElementById('w-candle-highlight')?.classList.add('hidden');
            document.getElementById('o-candle-highlight')?.classList.add('hidden');
        }
    }
    window.toggleActiveCandleHighlight = toggleActiveCandleHighlight;

    function updateCandleHighlightButtonsUI() {
        const btns = [
            { btn: document.getElementById('w-candle-hl-btn'), badge: document.getElementById('w-candle-hl-badge') },
            { btn: document.getElementById('o-candle-hl-btn'), badge: document.getElementById('o-candle-hl-badge') }
        ];

        btns.forEach(({ btn, badge }) => {
            if (!btn || !badge) return;
            if (isCandleHighlightEnabled) {
                btn.className = "ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 cursor-pointer bg-cyan-950/70 border-cyan-500/50 text-cyan-300 hover:bg-cyan-900 shadow-xs";
                badge.className = "font-mono text-cyan-400 font-extrabold";
                badge.textContent = "ON";
            } else {
                btn.className = "ml-1 px-1.5 py-0.2 rounded text-[10px] font-bold border transition flex items-center gap-1 cursor-pointer bg-gray-800 border-gray-600 text-gray-400 hover:text-white";
                badge.className = "font-mono text-gray-400 font-bold";
                badge.textContent = "OFF";
            }
        });
    }
    window.updateCandleHighlightButtonsUI = updateCandleHighlightButtonsUI;

    // Quick keyboard shortcut: press 'H' to toggle highlight on/off
    document.addEventListener('keydown', (e) => {
        if ((e.key === 'h' || e.key === 'H') && !e.ctrlKey && !e.metaKey && !e.altKey) {
            const tag = document.activeElement?.tagName;
            if (tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA') return;
            toggleActiveCandleHighlight();
        }
    });

    function updateActiveCandleHighlight(targetTimeStr = null) {
        if (targetTimeStr) {
            currentActiveCandleTimeStr = targetTimeStr;
        }
        if (!isCandleHighlightEnabled) {
            document.getElementById('w-candle-highlight')?.classList.add('hidden');
            document.getElementById('o-candle-highlight')?.classList.add('hidden');
            return;
        }
        const timeStr = currentActiveCandleTimeStr || (timestampsList && activeCandleIndex >= 0 ? timestampsList[activeCandleIndex] : null);
        if (!timeStr) {
            document.getElementById('w-candle-highlight')?.classList.add('hidden');
            document.getElementById('o-candle-highlight')?.classList.add('hidden');
            return;
        }

        // Chart 1 (Weightage / Stocks)
        renderSingleChartHighlight({
            prefix: 'w',
            chart: wChart,
            series: wCandleSeries,
            container: document.getElementById('w-chart-container'),
            candles: window._unifiedAllCandles,
            timeStr: timeStr,
            isOptionsChart: false
        });

        // Chart 2 (Options Flow) - resolved across today's candles first
        const oCandles = window._unifiedOptionsTodayCandles || window._unifiedOptionsCandles;
        const matchingOCandle = (typeof findCandleForTime === 'function' && oCandles) 
            ? findCandleForTime(oCandles, timeStr) 
            : null;

        renderSingleChartHighlight({
            prefix: 'o',
            chart: oChart,
            series: oCandleSeries,
            container: document.getElementById('o-chart-container'),
            candles: oCandles,
            forcedCandle: matchingOCandle,
            timeStr: matchingOCandle?.time_str || timeStr,
            isOptionsChart: true
        });
    }
    window.updateActiveCandleHighlight = updateActiveCandleHighlight;

    function renderSingleChartHighlight({ prefix, chart, series, container, candles, forcedCandle, timeStr, isOptionsChart }) {
        const rootEl = document.getElementById(`${prefix}-candle-highlight`);
        if (!rootEl || !chart || !series || !container) {
            if (rootEl) rootEl.classList.add('hidden');
            return;
        }

        const todayList = isOptionsChart ? window._unifiedOptionsTodayCandles : window._unifiedTodayCandles;
        let candle = forcedCandle || (todayList || []).find(c => c.time_str === timeStr);
        if (!candle && typeof activeCandleIndex === 'number' && todayList && todayList[activeCandleIndex]) {
            candle = todayList[activeCandleIndex];
        }
        if (!candle) {
            candle = (candles || []).find(c => c.time_str === timeStr);
        }

        if (!candle || !candle.time) {
            rootEl.classList.add('hidden');
            return;
        }

        const timeScale = chart.timeScale();
        let x = timeScale.timeToCoordinate(candle.time);
        const containerWidth = container.clientWidth || 99999;

        // Auto-scroll into view if candle is off-screen (skip during bar replay so logical range is never collapsed)
        if ((typeof isBarReplayMode === 'undefined' || !isBarReplayMode) && (x === null || isNaN(x) || x < 25 || x > (containerWidth - 55))) {
            try {
                const visibleRange = timeScale.getVisibleRange();
                if (visibleRange && (candle.time < visibleRange.from || candle.time > visibleRange.to)) {
                    const span = visibleRange.to - visibleRange.from;
                    if (span >= 3600) {
                        timeScale.setVisibleRange({
                            from: candle.time - (span * 0.45),
                            to: candle.time + (span * 0.55)
                        });
                        x = timeScale.timeToCoordinate(candle.time);
                    }
                }
            } catch (e) {}
        }

        // Re-check coordinate after possible range shift
        if (x === null || isNaN(x) || x < 0 || x > (containerWidth - 45)) {
            rootEl.classList.add('hidden');
            return;
        }

        const barSpacing = (typeof timeScale.options === 'function' ? timeScale.options().barSpacing : 10) || 10;
        const colWidth = Math.min(Math.max(Math.round(barSpacing * 1.5), 16), 36);
        const isBull = (candle.close >= candle.open);

        const candleColor = isBull ? '#10b981' : '#f43f5e';
        const candleGlow = isBull ? 'rgba(16, 185, 129, 0.6)' : 'rgba(244, 63, 94, 0.6)';
        const themeColor = isOptionsChart ? '#a855f7' : '#38bdf8';
        const themeBg = isOptionsChart 
            ? 'linear-gradient(180deg, rgba(168, 85, 247, 0.35) 0%, rgba(168, 85, 247, 0.08) 50%, rgba(168, 85, 247, 0.35) 100%)'
            : 'linear-gradient(180deg, rgba(56, 189, 248, 0.35) 0%, rgba(56, 189, 248, 0.08) 50%, rgba(56, 189, 248, 0.35) 100%)';
        const themeBorder = isOptionsChart ? 'rgba(168, 85, 247, 0.85)' : 'rgba(56, 189, 248, 0.85)';

        // Position root container
        rootEl.style.left = `${Math.round(x)}px`;
        rootEl.style.top = '0px';
        rootEl.style.bottom = '26px';
        rootEl.classList.remove('hidden');

        // 1. Translucent Spotlight Beam
        const beamEl = document.getElementById(`${prefix}-candle-highlight-beam`);
        if (beamEl) {
            beamEl.style.position = 'absolute';
            beamEl.style.top = '0px';
            beamEl.style.bottom = '0px';
            beamEl.style.left = `${-Math.round(colWidth / 2)}px`;
            beamEl.style.width = `${colWidth}px`;
            beamEl.style.background = themeBg;
            beamEl.style.borderLeft = `1.5px dashed ${themeBorder}`;
            beamEl.style.borderRight = `1.5px dashed ${themeBorder}`;
            beamEl.style.boxShadow = `0 0 15px ${isOptionsChart ? 'rgba(168, 85, 247, 0.25)' : 'rgba(56, 189, 248, 0.25)'}`;
        }

        // 2. High-Tech Center Laser Line
        const laserEl = document.getElementById(`${prefix}-candle-highlight-laser`);
        if (laserEl) {
            laserEl.style.position = 'absolute';
            laserEl.style.top = '0px';
            laserEl.style.bottom = '0px';
            laserEl.style.left = '0px';
            laserEl.style.width = '2px';
            laserEl.style.background = themeColor;
            laserEl.style.boxShadow = `0 0 10px ${themeColor}, 0 0 20px ${themeColor}`;
        }

        // 3. Viewfinder Reticle Frame around Candle
        const reticleEl = document.getElementById(`${prefix}-candle-highlight-reticle`);
        if (reticleEl) {
            const yH = series.priceToCoordinate(candle.high);
            const yL = series.priceToCoordinate(candle.low);
            if (yH !== null && yL !== null && !isNaN(yH) && !isNaN(yL)) {
                const top = Math.min(yH, yL) - 4;
                const height = Math.max(Math.abs(yL - yH) + 8, 8);
                const rWidth = colWidth + 6;
                const rLeft = -Math.round(rWidth / 2);

                reticleEl.style.position = 'absolute';
                reticleEl.style.top = `${Math.round(top)}px`;
                reticleEl.style.left = `${rLeft}px`;
                reticleEl.style.width = `${rWidth}px`;
                reticleEl.style.height = `${Math.round(height)}px`;
                reticleEl.style.border = `2px solid ${candleColor}`;
                reticleEl.style.borderRadius = '4px';
                reticleEl.style.boxShadow = `0 0 16px ${candleGlow}, inset 0 0 8px ${candleGlow}`;
                reticleEl.style.display = 'block';

                // Military/HUD Corner Targeting Brackets
                reticleEl.innerHTML = `
                    <div style="position: absolute; top: -3px; left: -3px; width: 6px; height: 6px; border-top: 2px solid #ffffff; border-left: 2px solid #ffffff;"></div>
                    <div style="position: absolute; top: -3px; right: -3px; width: 6px; height: 6px; border-top: 2px solid #ffffff; border-right: 2px solid #ffffff;"></div>
                    <div style="position: absolute; bottom: -3px; left: -3px; width: 6px; height: 6px; border-bottom: 2px solid #ffffff; border-left: 2px solid #ffffff;"></div>
                    <div style="position: absolute; bottom: -3px; right: -3px; width: 6px; height: 6px; border-bottom: 2px solid #ffffff; border-right: 2px solid #ffffff;"></div>
                `;
            } else {
                reticleEl.style.display = 'none';
            }
        }

        // 4. Floating Top Beacon Badge
        const pillEl = document.getElementById(`${prefix}-candle-highlight-pill`);
        if (pillEl) {
            const movePts = (candle.close - candle.open);
            const moveText = `${movePts >= 0 ? '+' : ''}${movePts.toFixed(1)}`;
            pillEl.style.left = '0px';
            pillEl.style.top = '4px';
            pillEl.style.transform = 'translateX(-50%)';
            pillEl.className = 'absolute flex items-center gap-1.5 px-2.5 py-0.5 rounded-full shadow-2xl text-[10px] font-mono font-bold tracking-wider select-none whitespace-nowrap z-20 backdrop-blur-md border';

            if (isOptionsChart) {
                pillEl.style.background = 'rgba(15, 23, 42, 0.95)';
                pillEl.style.borderColor = '#c084fc';
                pillEl.style.color = '#ffffff';
                pillEl.style.boxShadow = '0 0 15px rgba(168, 85, 247, 0.6)';
            } else {
                pillEl.style.background = 'rgba(15, 23, 42, 0.95)';
                pillEl.style.borderColor = '#38bdf8';
                pillEl.style.color = '#ffffff';
                pillEl.style.boxShadow = '0 0 15px rgba(56, 189, 248, 0.6)';
            }

            pillEl.innerHTML = `
                <span class="w-1.5 h-1.5 rounded-full ${isBull ? 'bg-emerald-400' : 'bg-rose-400'} animate-pulse"></span>
                <span>${candle.time_str || timeStr}</span>
                <span class="${isBull ? 'text-emerald-400' : 'text-rose-400'} font-bold">(${moveText})</span>
            `;
        }

        // 5. Snapped Bottom Time Badge on Time Scale
        const botEl = document.getElementById(`${prefix}-candle-highlight-bot`);
        if (botEl) {
            botEl.style.left = '0px';
            botEl.style.bottom = '2px';
            botEl.style.transform = 'translateX(-50%)';
            botEl.className = 'absolute flex items-center px-2 py-0.5 rounded shadow-lg text-[9px] font-mono font-bold tracking-wider select-none whitespace-nowrap z-20 backdrop-blur-md border';

            if (isOptionsChart) {
                botEl.style.background = '#581c87';
                botEl.style.borderColor = '#c084fc';
                botEl.style.color = '#ffffff';
                botEl.style.boxShadow = '0 0 10px rgba(168, 85, 247, 0.5)';
            } else {
                botEl.style.background = '#0369a1';
                botEl.style.borderColor = '#38bdf8';
                botEl.style.color = '#ffffff';
                botEl.style.boxShadow = '0 0 10px rgba(56, 189, 248, 0.5)';
            }

            botEl.innerHTML = `<span>▲ ${candle.time_str || timeStr}</span>`;
        }
    }

    function showChartLoaders(dateStr = selectedDate) {
        const wLoader = document.getElementById('w-chart-loading');
        const oLoader = document.getElementById('o-chart-loading');
        const toiLoader = document.getElementById('toi-chart-loading');

        if (wLoader) {
            wLoader.classList.remove('hidden');
            const msg = document.getElementById('w-loading-msg');
            if (msg) msg.textContent = `Loading NIFTY 50 Stocks for ${dateStr}...`;
        }
        if (oLoader) {
            oLoader.classList.remove('hidden');
            const msg = document.getElementById('o-loading-msg');
            if (msg) msg.textContent = `Loading Options Flow for ${dateStr}...`;
        }
        if (toiLoader) {
            toiLoader.classList.remove('hidden');
            const msg = document.getElementById('toi-loading-msg');
            if (msg) msg.textContent = `Loading Trending OI for ${dateStr}...`;
        }
        const spinner = document.getElementById('reload-spinner');
        if (spinner) spinner.classList.add('animate-spin');
    }

    async function fetchJsonSafe(url, options = {}, retries = 2, delay = 800) {
        try {
            const res = await fetch(url, options);
            if (!res.ok) {
                if ((res.status === 502 || res.status === 503 || res.status === 504) && retries > 0) {
                    await new Promise(r => setTimeout(r, delay));
                    return fetchJsonSafe(url, options, retries - 1, delay * 1.5);
                }
                throw new Error(`HTTP ${res.status}: ${res.statusText || 'Server Error'}`);
            }
            return await res.json();
        } catch (err) {
            if (retries > 0 && (err.name === 'TypeError' || String(err.message).includes('fetch') || String(err.message).includes('503'))) {
                await new Promise(r => setTimeout(r, delay));
                return fetchJsonSafe(url, options, retries - 1, delay * 1.5);
            }
            throw err;
        }
    }

    async function loadAllData(isSilent = false) {
        if (!isSilent) {
            showChartLoaders(selectedDate);
        }

        const todayStr = "{{ \Illuminate\Support\Carbon::now('Asia/Kolkata')->toDateString() }}";
        let toiMode = (selectedDate === todayStr) ? 'live' : 'history';

        const wMin = getTfMinutes(wTimeframe);
        const oMin = getTfMinutes(oTimeframe);
        const bMode = alarmSettings.optionsBasketMode || 'fixed';

        // 1. Fetch Chart 1 Stocks & Options (wTimeframe with wChartBasis)
        const pChart1Stk = fetchJsonSafe(`/api/nifty-weightage-monitor/data?date=${selectedDate}&interval=${wMin}&basis=${wChartBasis}&_t=${Date.now()}`)
            .then(data => {
                if (data && !data.error) {
                    wWeightageData = data;
                    if (!wWeightageData.candles && wWeightageData.nifty_candles) wWeightageData.candles = wWeightageData.nifty_candles;
                    if (wWeightageData.candles) {
                        wWeightageData.candles.forEach(c => { if (!c.time && c.timestamp) c.time = c.timestamp; });
                    }
                    weightageData = wWeightageData;
                    renderWeightageColumn();
                    if (breadthData) renderOptionsBreadthColumn();
                    buildMasterTimeline();
                }
            })
            .catch(e => console.error('Error fetching Chart 1 Weightage:', e))
            .finally(() => document.getElementById('w-chart-loading')?.classList.add('hidden'));

        const pChart1Opt = fetchJsonSafe(`/api/options-breadth-monitor/data?symbol=NIFTY&date=${selectedDate}&range=5&expiry=${selectedExpiry}&basket_mode=${bMode}&interval=${wMin}&basis=${wChartBasis}&_t=${Date.now()}`)
            .then(data => {
                if (data && data.success !== false && !data.error) {
                    wBreadthData = data;
                    if (wBreadthData.nifty_candles) {
                        wBreadthData.nifty_candles.forEach(c => { if (!c.time && c.timestamp) c.time = c.timestamp; });
                    }
                }
            })
            .catch(e => console.error('Error fetching Chart 1 Options:', e));

        // 2. Fetch Chart 2 Stocks & Options (oTimeframe with oChartBasis)
        let pChart2Stk, pChart2Opt;
        if (oMin === wMin && oChartBasis === wChartBasis) {
            pChart2Stk = pChart1Stk.then(() => { oWeightageData = wWeightageData; });
            pChart2Opt = pChart1Opt.then(() => {
                oBreadthData = wBreadthData;
                breadthData = oBreadthData;
                if (breadthData?.expiry && breadthData.expiry !== selectedExpiry) {
                    selectedExpiry = breadthData.expiry;
                    const expSelect = document.getElementById('expiry-select');
                    if (expSelect) expSelect.value = selectedExpiry;
                }
                renderOptionsBreadthColumn();
            }).catch(e => {
                console.error('Error syncing Chart 2 Options:', e);
            }).finally(() => {
                document.getElementById('o-chart-loading')?.classList.add('hidden');
            });
        } else {
            pChart2Stk = fetchJsonSafe(`/api/nifty-weightage-monitor/data?date=${selectedDate}&interval=${oMin}&basis=${oChartBasis}&_t=${Date.now()}`)
                .then(data => {
                    if (data && !data.error) {
                        oWeightageData = data;
                        if (!oWeightageData.candles && oWeightageData.nifty_candles) oWeightageData.candles = oWeightageData.nifty_candles;
                        if (oWeightageData.candles) {
                            oWeightageData.candles.forEach(c => { if (!c.time && c.timestamp) c.time = c.timestamp; });
                        }
                    }
                })
                .catch(e => console.error('Error fetching Chart 2 Weightage:', e));

            pChart2Opt = fetchJsonSafe(`/api/options-breadth-monitor/data?symbol=NIFTY&date=${selectedDate}&range=5&expiry=${selectedExpiry}&basket_mode=${bMode}&interval=${oMin}&basis=${oChartBasis}&_t=${Date.now()}`)
                .then(data => {
                    if (data && data.success !== false && !data.error) {
                        oBreadthData = data;
                        breadthData = oBreadthData;
                        if (breadthData.expiry && breadthData.expiry !== selectedExpiry) {
                            selectedExpiry = breadthData.expiry;
                            const expSelect = document.getElementById('expiry-select');
                            if (expSelect) expSelect.value = selectedExpiry;
                        }
                        if (breadthData.nifty_candles) {
                            breadthData.nifty_candles.forEach(c => { if (!c.time && c.timestamp) c.time = c.timestamp; });
                        }
                        renderOptionsBreadthColumn();
                        if (weightageData) renderWeightageColumn();
                        buildMasterTimeline();
                    } else {
                        const errText = data?.error || 'Options data unavailable for this date';
                        const msg = document.getElementById('o-loading-msg');
                        if (msg) {
                            msg.innerHTML = `<span class="text-rose-400 font-semibold">${errText}</span> <button type="button" onclick="loadAllData()" class="underline text-sky-400 ml-2 cursor-pointer font-bold">Retry</button>`;
                        }
                    }
                })
                .catch(e => {
                    console.error('Error fetching Options Breadth:', e);
                    const msg = document.getElementById('o-loading-msg');
                    if (msg) {
                        msg.innerHTML = `<span class="text-rose-400 font-semibold">Failed to load Options data</span> <button type="button" onclick="loadAllData()" class="underline text-sky-400 ml-2 cursor-pointer font-bold">Retry</button>`;
                    }
                })
                .finally(() => {
                    document.getElementById('o-chart-loading')?.classList.add('hidden');
                });
        }

        // 3. Fetch Trending OI independently & render immediately upon arrival
        const pTrendingOi = (async () => {
            try {
                const reqInterval = currentTfMinutes;
                const atmParam = (manualToiAtmStrike !== null && manualToiAtmStrike > 0) ? `&atm_strike=${manualToiAtmStrike}` : '';
                let resToi = await fetch(`/api/trending-oi/data?mode=${toiMode}&expiry=${selectedExpiry}&date=${selectedDate}&underlying=NSE_INDEX|Nifty 50&interval=${reqInterval}&lookback=auto&skip_open=0${atmParam}`).then(r => r.json());
                if (toiMode === 'live' && (!resToi || !resToi.chart || !resToi.chart.labels || resToi.chart.labels.length === 0)) {
                    resToi = await fetch(`/api/trending-oi/data?mode=history&expiry=${selectedExpiry}&date=${selectedDate}&underlying=NSE_INDEX|Nifty 50&interval=${reqInterval}&lookback=auto&skip_open=0${atmParam}`).then(r => r.json());
                }
                if (resToi && !resToi.error) {
                    trendingOiData = resToi;
                    if (resToi.stream_config) {
                        toiStreamConfig = resToi.stream_config;
                    }
                    renderTrendingOiRows();
                    if (typeof renderStrikeBuildupChart === 'function') renderStrikeBuildupChart();
                    buildMasterTimeline();
                    if (selectedDate === todayStr) {
                        connectToiWebSocket();
                    } else {
                        disconnectToiWebSocket();
                    }
                }
            } catch (e) {
                console.error('Error fetching Trending OI:', e);
            } finally {
                document.getElementById('toi-chart-loading')?.classList.add('hidden');
            }
        })();

        // 4. Fetch Quant S/R Levels independently & update UI/chart price lines
        const pQuant = fetch(`/api/nifty-unified/quant-levels?date=${selectedDate}&symbol=NIFTY&_t=${Date.now()}`)
            .then(r => r.json())
            .then(data => {
                if (data && data.success && data.playbook) {
                    updateQuantLevelsUI(data.playbook);
                }
            })
            .catch(e => console.warn('Could not auto-update Quant S/R levels:', e));

        // 5. Fetch Order Flow S/R Levels (Top Bid Support & Ask Resistance + 5-Day Historical Anchors)
        const pOrderFlow = fetchOrderFlowLevels();
        const pMultiDayOf = fetchMultiDayOrderFlowLevels();

        try {
            await Promise.allSettled([pChart1Stk, pChart1Opt, pChart2Stk, pChart2Opt, pTrendingOi, pQuant, pOrderFlow, pMultiDayOf]);
            renderWeightageColumn();
            renderOptionsBreadthColumn();
            applyAllSectionHeights();
            if (typeof renderFlowSynergy === 'function') {
                renderFlowSynergy();
            }
            if (timestampsList && timestampsList.length > 0) {
                if (isLiveMarketOpen() && !isPlaying) {
                    snapToLiveCandle();
                } else {
                    const targetIdx = (activeCandleIndex >= 0 && activeCandleIndex < timestampsList.length)
                        ? activeCandleIndex
                        : (timestampsList.length - 1);
                    syncActiveCandleIndex(targetIdx);
                }
                // Check alarms: when market is live, check the last completed candle to confirm signals upon close!
                if (isLiveMarketOpen() && timestampsList.length >= 2) {
                    checkAlarmConditions(timestampsList[timestampsList.length - 2]);
                }
                checkAlarmConditions(timestampsList[timestampsList.length - 1]);
                updateOverallAndTriggerStats(activeCandleIndex >= 0 ? activeCandleIndex : timestampsList.length - 1);
            }

            // Update Last Sync Time (Only Time: HH:mm:ss)
            const syncNow = new Date();
            const timeOnlyStr = String(syncNow.getHours()).padStart(2, '0') + ':' +
                                String(syncNow.getMinutes()).padStart(2, '0') + ':' +
                                String(syncNow.getSeconds()).padStart(2, '0');
            const syncBadge = document.getElementById('last-sync-time-badge');
            if (syncBadge) {
                syncBadge.textContent = timeOnlyStr;
                syncBadge.classList.add('bg-emerald-500/30', 'text-emerald-200');
                setTimeout(() => syncBadge.classList.remove('bg-emerald-500/30', 'text-emerald-200'), 800);
            }
            const quantSyncEl = document.getElementById('quant-last-sync-time');
            if (quantSyncEl) {
                quantSyncEl.textContent = timeOnlyStr;
            }
        } finally {
            const spinner = document.getElementById('reload-spinner');
            if (spinner) spinner.classList.remove('animate-spin');
        }
    }

    // ── Helper: Adjust Chart TimeScale for Natural Zoomed-Out View & Day Anchoring ──
    function adjustChartTimeScale(chart, todayCandles, yesterdayCandles = [], tf = '5m') {
        if (!chart || !todayCandles || todayCandles.length === 0) return;
        if (tf === '1D') {
            requestAnimationFrame(() => {
                try {
                    chart.timeScale().applyOptions({
                        minBarSpacing: 6,
                        fixLeftEdge: false,
                        fixRightEdge: false,
                    });
                    chart.timeScale().fitContent();
                    updateDaySeparators();
                } catch (e) {}
            });
            return;
        }
        const yCount = Array.isArray(yesterdayCandles) ? yesterdayCandles.length : 0;
        const tCount = todayCandles.length;
        const tfBarsMap = { '1m': 375, '3m': 125, '5m': 75, '15m': 25, '30m': 13, '60m': 7, '1h': 7, '1D': 1 };
        const sessionBars = tfBarsMap[tf] || 75;

        const applyRange = () => {
            try {
                chart.timeScale().applyOptions({
                    minBarSpacing: 3,
                    fixLeftEdge: false,
                    fixRightEdge: false,
                });

                // Anchor Today's 09:15 open candle (at logical index yCount) with at least 10 candle widths of spacing to the right of the left edge
                // This ensures the left floating tool dock does not override or overlap the start of the day candles and alert badges
                const leftOffsetBars = 10;
                const fromIndex = (yCount > 0 ? yCount : 0) - leftOffsetBars;
                // Always display a full trading day window plus the left margin so candles never stretch or zoom
                const toIndex = fromIndex + Math.max(sessionBars + leftOffsetBars, tCount + leftOffsetBars + 5);

                chart.timeScale().setVisibleLogicalRange({
                    from: fromIndex,
                    to: toIndex,
                });
                updateDaySeparators();
            } catch (e) {
                console.warn('adjustChartTimeScale warning:', e);
            }
        };

        requestAnimationFrame(applyRange);
    }

    // ── 6. Render Weightage Row ───────────────────────────────────────────────────
    function renderWeightageColumn() {
        const rawCandles = weightageData?.candles || weightageData?.nifty_candles;
        if (!rawCandles || rawCandles.length === 0) return;
        if (!weightageData.candles) weightageData.candles = rawCandles;

        const todayCandles = rawCandles.map(c => ({
            time: Number(c.time || c.timestamp),
            open: Number(c.open),
            high: Number(c.high),
            low: Number(c.low),
            close: Number(c.close),
            time_str: c.time_str,
        })).filter(c => c.time && !isNaN(c.time));

        const firstTodayTime = todayCandles[0]?.time || 0;

        // Preload Yesterday's candles (if available) before Today's open
        const yesterdayRaw = weightageData?.yesterday_candles || [];
        const yesterdayCandles = yesterdayRaw.map(c => ({
            time: Number(c.time || c.timestamp),
            open: Number(c.open),
            high: Number(c.high),
            low: Number(c.low),
            close: Number(c.close),
            time_str: c.time_str,
        })).filter(c => c.time && !isNaN(c.time) && (firstTodayTime === 0 || c.time < firstTodayTime));

        const allCandles = [...yesterdayCandles, ...todayCandles];
        window._unifiedAllCandles = allCandles;
        window._unifiedTodayCandles = todayCandles;
        window._unifiedYesterdayCandles = yesterdayCandles;
        window._unifiedHaCandles = calculateHeikinAshi(allCandles);

        const displayCandles = (wCandleStyle === 'heikin_ashi') ? window._unifiedHaCandles : allCandles;

        if (wCandleSeries && displayCandles.length > 0) {
            wCandleSeries.setData(displayCandles);

            const markerMode = alarmSettings.stocksMarkerMode || (alarmSettings.stocksShowMarkers ? 'breach' : 'off');
            const markers = [];

            const showStocks = (alarmSettings.chart1ShowStocks !== false);
            const showOptions = (alarmSettings.chart1ShowOptions === true);

            if (markerMode !== 'off' && (showStocks || showOptions)) {
                const sUpTh = alarmSettings.stocksUpThreshold || 80;
                const sDownTh = alarmSettings.stocksDownThreshold || 80;
                const sTrig = alarmSettings.stocksTriggerBy || 'both';

                const oUpTh = alarmSettings.optionsUpThreshold || 70;
                const oDownTh = alarmSettings.optionsDownThreshold || 70;

                let lastStkPos = null;
                let lastStkMarkerIdx = -999;
                let lastStkPeakPct = 0;

                let lastOptPos = null;
                let lastOptMarkerIdx = -999;
                let lastOptPeakPct = 0;

                rawCandles.forEach((c, cIdx) => {
                    const candleTime = Number(c.time || c.timestamp);
                    if (!candleTime) return;
                    const b = weightageData.breadth_by_time?.[c.time_str];

                    let oB = null;
                    if (showOptions && breadthData) {
                        oB = breadthData.breadth_by_time?.[c.time_str];
                        if (!oB && breadthData.nifty_candles) {
                            const oCandle = (typeof findCandleForTime === 'function') ? findCandleForTime(breadthData.nifty_candles, c.time_str) : null;
                            if (oCandle && oCandle.time_str) {
                                oB = breadthData.breadth_by_time?.[oCandle.time_str];
                            }
                        }
                    }

                    let sBull = false, sBear = false, sPct = 0;
                    if (showStocks && b) {
                        if (sTrig === 'both') {
                            sBull = (b.green_count_pct >= sUpTh && b.green_weight >= sUpTh);
                            sBear = (b.red_count_pct >= sDownTh && b.red_weight >= sDownTh);
                        } else if (sTrig === 'either') {
                            sBull = (b.green_count_pct >= sUpTh || b.green_weight >= sUpTh);
                            sBear = (b.red_count_pct >= sDownTh || b.red_weight >= sDownTh);
                        } else if (sTrig === 'count') {
                            sBull = (b.green_count_pct >= sUpTh);
                            sBear = (b.red_count_pct >= sDownTh);
                        } else if (sTrig === 'weight') {
                            sBull = (b.green_weight >= sUpTh);
                            sBear = (b.red_weight >= sDownTh);
                        }
                        sPct = sBull ? Math.round(b.green_count_pct) : (sBear ? Math.round(b.red_count_pct) : 0);
                    }

                    let oBull = false, oBear = false, oPct = 0;
                    let oUw = 0, oDw = 0;
                    if (showOptions && oB) {
                        oUw = Number(oB.up_weight || 0);
                        oDw = Number(oB.down_weight || 0);
                        oBull = (oUw >= oUpTh);
                        oBear = (oDw >= oDownTh);
                        oPct = oBull ? Math.round(oUw) : (oBear ? Math.round(oDw) : 0);
                    }

                    const isLastCandle  = (cIdx === rawCandles.length - 1);
                    const isCandleLive  = isLastCandle && (isLiveMarketOpen() || (b && b.is_complete === false) || (oB && oB.is_complete === false));
                    const isOpenCandle  = (cIdx === 0 && yesterdayCandles.length > 0);
                    const openTag       = isOpenCandle ? ' [OPEN]' : '';
                    const liveTag       = isCandleLive ? ' ⏳LIVE' : '';

                    const wHaAnalysis = getCandleHaWickAnalysis(candleTime, false, c.time_str);
                    const useStocksHa = (alarmSettings.stocksHaWickFilter !== false);

                    const oHaAnalysis = showOptions ? getCandleHaWickAnalysis(candleTime, true, c.time_str) : null;
                    const useOptionsHa = (alarmSettings.optionsHaWickFilter !== false);

                    // 1. Determine visual candle color on Chart 1:
                    const isRegularGreen = Number(c.close) >= Number(c.open);
                    const isHaGreen = wHaAnalysis ? wHaAnalysis.isHaBull : isRegularGreen;
                    const isCandleGreen = (wCandleStyle === 'heikin_ashi') ? isHaGreen : isRegularGreen;

                    // 2. Strict Wick Validation for entries:
                    let cleanStkBull = sBull, cleanStkBear = sBear;
                    if (useStocksHa && wHaAnalysis) {
                        if (wHaAnalysis.hasBothWicks || !wHaAnalysis.isCleanBullEntry) cleanStkBull = false;
                        if (wHaAnalysis.hasBothWicks || !wHaAnalysis.isCleanBearEntry) cleanStkBear = false;
                    }
                    let cleanOptBull = oBull, cleanOptBear = oBear;
                    if (useOptionsHa) {
                        const oHa = oHaAnalysis || wHaAnalysis;
                        if (oHa) {
                            if (oHa.hasBothWicks || !oHa.isCleanBullEntry) cleanOptBull = false;
                            if (oHa.hasBothWicks || !oHa.isCleanBearEntry) cleanOptBear = false;
                        }
                    }

                    // 3. Independent Signal Resolution on Chart 1 (Stocks Chart)
                    // Stock candidate (Primary)
                    let stkSig = null;
                    if (showStocks) {
                        if (cleanStkBull && isCandleGreen) {
                            const isDiv = showOptions && oBear;
                            stkSig = { type: 'stk', pos: 'belowBar', shape: 'arrowUp', color: isDiv ? '#38bdf8' : '#10b981', text: isDiv ? `STK ${sPct}% ⚡DIV` : `STK ${sPct}%`, pct: sPct, isDiv };
                        } else if (cleanStkBear && !isCandleGreen) {
                            const isDiv = showOptions && oBull;
                            stkSig = { type: 'stk', pos: 'aboveBar', shape: 'arrowDown', color: isDiv ? '#fb7185' : '#ef4444', text: isDiv ? `STK ${sPct}% ⚡DIV` : `STK ${sPct}%`, pct: sPct, isDiv };
                        } else if (sBear && isCandleGreen) {
                            // Bear Trap (Breadth red, candle green)
                            stkSig = { type: 'stk_trap', pos: 'aboveBar', shape: 'arrowDown', color: '#f59e0b', text: `⚠️ TRAP ${sPct}%`, pct: sPct };
                        } else if (sBull && !isCandleGreen) {
                            // Bull Trap (Breadth green, candle red)
                            stkSig = { type: 'stk_trap', pos: 'belowBar', shape: 'arrowUp', color: '#06b6d4', text: `⚡ TRAP ${sPct}%`, pct: sPct };
                        }
                    }

                    // Option candidate (Secondary on Chart 1)
                    let optSig = null;
                    if (showOptions) {
                        if (cleanOptBull && isCandleGreen) {
                            optSig = { type: 'opt', pos: 'belowBar', shape: 'arrowUp', color: '#a855f7', text: `OPT ${oPct}%`, pct: oPct };
                        } else if (cleanOptBear && !isCandleGreen) {
                            optSig = { type: 'opt', pos: 'aboveBar', shape: 'arrowDown', color: '#ec4899', text: `OPT ${oPct}%`, pct: oPct };
                        } else if (oBear && isCandleGreen) {
                            optSig = { type: 'opt_trap', pos: 'aboveBar', shape: 'arrowDown', color: '#f59e0b', text: `⚠️ TRAP ${oPct}%`, pct: oPct };
                        } else if (oBull && !isCandleGreen) {
                            optSig = { type: 'opt_trap', pos: 'belowBar', shape: 'arrowUp', color: '#06b6d4', text: `⚡ TRAP ${oPct}%`, pct: oPct };
                        }
                    }

                    // Always preserve the signal whenever STK or OPT criteria are met
                    const applyFilters = (sig) => {
                        return sig;
                    };

                    stkSig = applyFilters(stkSig);
                    optSig = applyFilters(optSig);

                    // Check Confluence first (Both agree with same candle direction)
                    let confSig = null;
                    if (stkSig && optSig && stkSig.pos === optSig.pos) {
                        const isBull = (stkSig.pos === 'belowBar');
                        if (stkSig.type === 'stk' && optSig.type === 'opt') {
                            confSig = {
                                pos: stkSig.pos,
                                shape: stkSig.shape,
                                color: isBull ? '#10b981' : '#ef4444',
                                text: `🔥 CONF ${sPct}%`,
                                pct: sPct,
                            };
                        } else if (stkSig.type === 'stk_trap' && optSig.type === 'opt_trap') {
                            const p = Math.max(sPct, oPct);
                            confSig = {
                                pos: stkSig.pos,
                                shape: stkSig.shape,
                                color: isBull ? '#06b6d4' : '#f59e0b',
                                text: isBull ? `⚡ CONF TRAP ${p}%` : `⚠️ CONF TRAP ${p}%`,
                                pct: p,
                            };
                        }
                    }

                    let candleHasMarker = false;

                    // Display every triggered STK or OPT signal whenever triggered and enabled
                    if (confSig) {
                        markers.push({
                            time: candleTime,
                            position: confSig.pos,
                            color: confSig.color,
                            shape: confSig.shape,
                            text: confSig.text + openTag + liveTag,
                            size: 1,
                        });
                        candleHasMarker = true;
                    } else {
                        // Independent STK signal evaluation (Primary on Chart 1)
                        let pushedStk = false;
                        if (stkSig) {
                            markers.push({
                                time: candleTime,
                                position: stkSig.pos,
                                color: stkSig.color,
                                shape: stkSig.shape,
                                text: stkSig.text + openTag + liveTag,
                                size: 1,
                            });
                            pushedStk = true;
                            candleHasMarker = true;
                        }

                        // Independent OPT signal evaluation (Secondary on Chart 1)
                        if (optSig) {
                            const sameSideConflict = pushedStk && stkSig && (stkSig.pos === optSig.pos);
                            if (!sameSideConflict) {
                                markers.push({
                                    time: candleTime,
                                    position: optSig.pos,
                                    color: optSig.color,
                                    shape: optSig.shape,
                                    text: optSig.text + openTag + liveTag,
                                    size: 1,
                                });
                                candleHasMarker = true;
                            }
                        }
                    }

                    if (!candleHasMarker) {
                        if (isOpenCandle) {
                            markers.push({
                                time: candleTime,
                                position: 'aboveBar',
                                color: '#38bdf8',
                                shape: 'arrowDown',
                                text: 'TODAY',
                                size: 1,
                            });
                        } else if (isCandleLive) {
                            markers.push({
                                time: candleTime,
                                position: 'aboveBar',
                                color: '#38bdf8',
                                shape: 'arrowDown',
                                text: '⏳ LIVE',
                                size: 1,
                            });
                        }
                    }
                });
            } else {
                if (yesterdayCandles.length > 0 && todayCandles.length > 0) {
                    markers.push({
                        time: todayCandles[0].time,
                        position: 'aboveBar',
                        color: '#38bdf8',
                        shape: 'arrowDown',
                        text: 'TODAY',
                        size: 1,
                    });
                }
            }
            markers.sort((a, b) => a.time - b.time);
            window._wCandleMarkers = markers;
            wCandleSeries.setMarkers(markers);
            window._wTodayFirstTime = todayCandles.length > 0 ? todayCandles[0].time : null;
            window._wYesterdayLastTime = yesterdayCandles.length > 0 ? yesterdayCandles[yesterdayCandles.length - 1].time : null;
            if (wChart) adjustChartTimeScale(wChart, todayCandles, yesterdayCandles, wTimeframe);
            drawWeightageQuantLines();
            drawWeightageOrderFlowLines();
            renderWeightageOverlays();
            setTimeout(updateDaySeparators, 50);
            setTimeout(updateDaySeparators, 200);
        }

        const wSlider = document.getElementById('w-slider');
        if (wSlider && rawCandles.length > 0) {
            wSlider.max = rawCandles.length - 1;
            const startLbl = document.getElementById('w-slider-start');
            const endLbl = document.getElementById('w-slider-end');
            if (startLbl) startLbl.textContent = rawCandles[0].time_str;
            if (endLbl) endLbl.textContent = rawCandles[rawCandles.length - 1].time_str;
        }

        if (typeof isBarReplayMode !== 'undefined' && isBarReplayMode && typeof applyBarReplaySlice === 'function') {
            applyBarReplaySlice(activeCandleIndex);
        }
    }

    // ── 7. Render Options Breadth Row ─────────────────────────────────────────────
    function renderOptionsBreadthColumn() {
        const rawCandles = breadthData?.nifty_candles;
        if (!rawCandles || rawCandles.length === 0) return;

        const todayCandles = rawCandles.map(c => ({
            time: Number(c.time || c.timestamp),
            open: Number(c.open),
            high: Number(c.high),
            low: Number(c.low),
            close: Number(c.close),
            time_str: c.time_str,
        })).filter(c => c.time && !isNaN(c.time));

        const firstTodayTime = todayCandles[0]?.time || 0;

        // Preload Yesterday's candles (if available) before Today's open
        const yesterdayRaw = breadthData?.yesterday_candles || weightageData?.yesterday_candles || [];
        const yesterdayCandles = yesterdayRaw.map(c => ({
            time: Number(c.time || c.timestamp),
            open: Number(c.open),
            high: Number(c.high),
            low: Number(c.low),
            close: Number(c.close),
            time_str: c.time_str,
        })).filter(c => c.time && !isNaN(c.time) && (firstTodayTime === 0 || c.time < firstTodayTime));

        const allCandles = [...yesterdayCandles, ...todayCandles];
        window._unifiedOptionsCandles = allCandles;
        window._unifiedOptionsTodayCandles = todayCandles;
        window._unifiedOptionsYesterdayCandles = yesterdayCandles;
        window._unifiedOptionsHaCandles = calculateHeikinAshi(allCandles);

        const displayCandles = (oCandleStyle === 'heikin_ashi') ? window._unifiedOptionsHaCandles : allCandles;

        if (oCandleSeries && displayCandles.length > 0) {
            oCandleSeries.setData(displayCandles);

            const markerMode = alarmSettings.optionsMarkerMode || (alarmSettings.optionsShowMarkers ? 'breach' : 'off');
            const markers = [];

            const showOptions = (alarmSettings.chart2ShowOptions !== false);
            const showStocks = (alarmSettings.chart2ShowStocks === true);

            if (markerMode !== 'off' && (showOptions || showStocks)) {
                const oUpTh = alarmSettings.optionsUpThreshold || 70;
                const oDownTh = alarmSettings.optionsDownThreshold || 70;

                const sUpTh = alarmSettings.stocksUpThreshold || 80;
                const sDownTh = alarmSettings.stocksDownThreshold || 80;
                const sTrig = alarmSettings.stocksTriggerBy || 'both';

                let lastOptPos = null;
                let lastOptMarkerIdx = -999;
                let lastOptPeakPct = 0;

                let lastStkPos = null;
                let lastStkMarkerIdx = -999;
                let lastStkPeakPct = 0;

                rawCandles.forEach((c, cIdx) => {
                    const candleTime = Number(c.time || c.timestamp);
                    if (!candleTime) return;

                    const ob = breadthData.breadth_by_time?.[c.time_str];

                    let sB = null;
                    if (showStocks && weightageData) {
                        sB = weightageData.breadth_by_time?.[c.time_str];
                        if (!sB && weightageData.candles) {
                            const sCandle = (typeof findCandleForTime === 'function') ? findCandleForTime(weightageData.candles, c.time_str) : null;
                            if (sCandle && sCandle.time_str) {
                                sB = weightageData.breadth_by_time?.[sCandle.time_str];
                            }
                        }
                    }

                    let oBull = false, oBear = false, oPct = 0;
                    let uw = 0, dw = 0;
                    if (showOptions && ob) {
                        uw = Number(ob.up_weight) || 0;
                        dw = Number(ob.down_weight) || 0;
                        oBull = (uw >= oUpTh);
                        oBear = (dw >= oDownTh);
                        oPct = oBull ? Math.round(uw) : (oBear ? Math.round(dw) : 0);
                    }

                    let sBull = false, sBear = false, sPct = 0;
                    if (showStocks && sB) {
                        if (sTrig === 'both') {
                            sBull = (sB.green_count_pct >= sUpTh && sB.green_weight >= sUpTh);
                            sBear = (sB.red_count_pct >= sDownTh && sB.red_weight >= sDownTh);
                        } else if (sTrig === 'either') {
                            sBull = (sB.green_count_pct >= sUpTh || sB.green_weight >= sUpTh);
                            sBear = (sB.red_count_pct >= sDownTh || sB.red_weight >= sDownTh);
                        } else if (sTrig === 'count') {
                            sBull = (sB.green_count_pct >= sUpTh);
                            sBear = (sB.red_count_pct >= sDownTh);
                        } else if (sTrig === 'weight') {
                            sBull = (sB.green_weight >= sUpTh);
                            sBear = (sB.red_weight >= sDownTh);
                        }
                        sPct = sBull ? Math.round(sB.green_count_pct) : (sBear ? Math.round(sB.red_count_pct) : 0);
                    }

                    const oHaAnalysis = getCandleHaWickAnalysis(candleTime, true, c.time_str);
                    const useOptionsHa = (alarmSettings.optionsHaWickFilter !== false);

                    const isLastCandle  = (cIdx === rawCandles.length - 1);
                    const isCandleLive  = isLastCandle && (isLiveMarketOpen() || (ob && ob.is_complete === false) || (sB && sB.is_complete === false));
                    const isOpenCandle  = (cIdx === 0 && yesterdayCandles.length > 0);
                    const openTag       = isOpenCandle ? ' [OPEN]' : '';
                    const liveTag       = isCandleLive ? ' ⏳LIVE' : '';

                    const wHaAnalysis = showStocks ? getCandleHaWickAnalysis(candleTime, false, c.time_str) : null;
                    const useStocksHa = (alarmSettings.stocksHaWickFilter !== false);

                    // 1. Determine visual candle color on Chart 2:
                    const isRegularGreen = Number(c.close) >= Number(c.open);
                    const isHaGreen = oHaAnalysis ? oHaAnalysis.isHaBull : isRegularGreen;
                    const isCandleGreen = (oCandleStyle === 'heikin_ashi') ? isHaGreen : isRegularGreen;

                    // 2. Strict Wick Validation:
                    let cleanOptBull = oBull, cleanOptBear = oBear;
                    if (useOptionsHa && oHaAnalysis) {
                        if (oHaAnalysis.hasBothWicks || !oHaAnalysis.isCleanBullEntry) cleanOptBull = false;
                        if (oHaAnalysis.hasBothWicks || !oHaAnalysis.isCleanBearEntry) cleanOptBear = false;
                    }
                    let cleanStkBull = sBull, cleanStkBear = sBear;
                    if (useStocksHa) {
                        const sHa = wHaAnalysis || oHaAnalysis;
                        if (sHa) {
                            if (sHa.hasBothWicks || !sHa.isCleanBullEntry) cleanStkBull = false;
                            if (sHa.hasBothWicks || !sHa.isCleanBearEntry) cleanStkBear = false;
                        }
                    }

                    // 3. Independent Signal Candidates on Chart 2 (Options Chart)
                    // Option candidate (Primary on Chart 2)
                    let optSig = null;
                    if (showOptions) {
                        if (cleanOptBull && isCandleGreen) {
                            const isDiv = showStocks && sBear;
                            optSig = { type: 'opt', pos: 'belowBar', shape: 'arrowUp', color: isDiv ? '#a855f7' : '#10b981', text: isDiv ? `OPT ${oPct}% 📊DIV` : `OPT ${oPct}%`, pct: oPct, isDiv };
                        } else if (cleanOptBear && !isCandleGreen) {
                            const isDiv = showStocks && sBull;
                            optSig = { type: 'opt', pos: 'aboveBar', shape: 'arrowDown', color: isDiv ? '#ec4899' : '#ef4444', text: isDiv ? `OPT ${oPct}% 📊DIV` : `OPT ${oPct}%`, pct: oPct, isDiv };
                        } else if (oBear && isCandleGreen) {
                            optSig = { type: 'opt_trap', pos: 'aboveBar', shape: 'arrowDown', color: '#f59e0b', text: `⚠️ TRAP ${oPct}%`, pct: oPct };
                        } else if (oBull && !isCandleGreen) {
                            optSig = { type: 'opt_trap', pos: 'belowBar', shape: 'arrowUp', color: '#06b6d4', text: `⚡ TRAP ${oPct}%`, pct: oPct };
                        }
                    }

                    // Stock candidate (Secondary on Chart 2)
                    let stkSig = null;
                    if (showStocks) {
                        if (cleanStkBull && isCandleGreen) {
                            stkSig = { type: 'stk', pos: 'belowBar', shape: 'arrowUp', color: '#38bdf8', text: `STK ${sPct}%`, pct: sPct };
                        } else if (cleanStkBear && !isCandleGreen) {
                            stkSig = { type: 'stk', pos: 'aboveBar', shape: 'arrowDown', color: '#fb7185', text: `STK ${sPct}%`, pct: sPct };
                        } else if (sBear && isCandleGreen) {
                            stkSig = { type: 'stk_trap', pos: 'aboveBar', shape: 'arrowDown', color: '#f59e0b', text: `⚠️ TRAP ${sPct}%`, pct: sPct };
                        } else if (sBull && !isCandleGreen) {
                            stkSig = { type: 'stk_trap', pos: 'belowBar', shape: 'arrowUp', color: '#06b6d4', text: `⚡ TRAP ${sPct}%`, pct: sPct };
                        }
                    }

                    // Always preserve the signal whenever OPT or STK criteria are met
                    const applyFilters = (sig) => {
                        return sig;
                    };

                    optSig = applyFilters(optSig);
                    stkSig = applyFilters(stkSig);

                    // Check Confluence first (Both agree with same candle direction)
                    let confSig = null;
                    if (optSig && stkSig && optSig.pos === stkSig.pos) {
                        const isBull = (optSig.pos === 'belowBar');
                        if (optSig.type === 'opt' && stkSig.type === 'stk') {
                            confSig = {
                                pos: optSig.pos,
                                shape: optSig.shape,
                                color: isBull ? '#10b981' : '#ef4444',
                                text: `🔥 CONF ${oPct}%`,
                                pct: oPct,
                            };
                        } else if (optSig.type === 'opt_trap' && stkSig.type === 'stk_trap') {
                            const p = Math.max(oPct, sPct);
                            confSig = {
                                pos: optSig.pos,
                                shape: optSig.shape,
                                color: isBull ? '#06b6d4' : '#f59e0b',
                                text: isBull ? `⚡ CONF TRAP ${p}%` : `⚠️ CONF TRAP ${p}%`,
                                pct: p,
                            };
                        }
                    }

                    let candleHasMarker = false;

                    // Display every triggered OPT or STK signal whenever triggered and enabled
                    if (confSig) {
                        markers.push({
                            time: candleTime,
                            position: confSig.pos,
                            color: confSig.color,
                            shape: confSig.shape,
                            text: confSig.text + openTag + liveTag,
                            size: 1,
                        });
                        candleHasMarker = true;
                    } else {
                        // Independent OPT signal evaluation (Primary on Chart 2)
                        let pushedOpt = false;
                        if (optSig) {
                            markers.push({
                                time: candleTime,
                                position: optSig.pos,
                                color: optSig.color,
                                shape: optSig.shape,
                                text: optSig.text + openTag + liveTag,
                                size: 1,
                            });
                            pushedOpt = true;
                            candleHasMarker = true;
                        }

                        // Independent STK signal evaluation (Secondary on Chart 2)
                        if (stkSig) {
                            const sameSideConflict = pushedOpt && optSig && (optSig.pos === stkSig.pos);
                            if (!sameSideConflict) {
                                markers.push({
                                    time: candleTime,
                                    position: stkSig.pos,
                                    color: stkSig.color,
                                    shape: stkSig.shape,
                                    text: stkSig.text + openTag + liveTag,
                                    size: 1,
                                });
                                candleHasMarker = true;
                            }
                        }
                    }

                    if (!candleHasMarker) {
                        if (isOpenCandle) {
                            markers.push({
                                time: candleTime,
                                position: 'aboveBar',
                                color: '#818cf8',
                                shape: 'arrowDown',
                                text: 'TODAY',
                                size: 1,
                            });
                        } else if (isCandleLive) {
                            markers.push({
                                time: candleTime,
                                position: 'aboveBar',
                                color: '#a855f7',
                                shape: 'arrowDown',
                                text: '⏳ LIVE',
                                size: 1,
                            });
                        }
                    }
                });
            } else {
                if (yesterdayCandles.length > 0 && todayCandles.length > 0) {
                    markers.push({
                        time: todayCandles[0].time,
                        position: 'aboveBar',
                        color: '#818cf8',
                        shape: 'arrowDown',
                        text: 'TODAY',
                        size: 1,
                    });
                }
            }
            markers.sort((a, b) => a.time - b.time);
            window._oCandleMarkers = markers;
            oCandleSeries.setMarkers(markers);
            window._oTodayFirstTime = todayCandles.length > 0 ? todayCandles[0].time : null;
            window._oYesterdayLastTime = yesterdayCandles.length > 0 ? yesterdayCandles[yesterdayCandles.length - 1].time : null;
            if (oChart) adjustChartTimeScale(oChart, todayCandles, yesterdayCandles, oTimeframe);
            drawOptionsQuantLines();
            drawOptionsOrderFlowLines();
            renderOptionsOverlays();
            setTimeout(updateDaySeparators, 50);
            setTimeout(updateDaySeparators, 200);
        }

        const oSlider = document.getElementById('o-slider');
        if (oSlider && rawCandles.length > 0) {
            oSlider.max = rawCandles.length - 1;
            const startLbl = document.getElementById('o-slider-start');
            const endLbl = document.getElementById('o-slider-end');
            if (startLbl) startLbl.textContent = rawCandles[0].time_str;
            if (endLbl) endLbl.textContent = rawCandles[rawCandles.length - 1].time_str;
        }

        if (typeof isBarReplayMode !== 'undefined' && isBarReplayMode && typeof applyBarReplaySlice === 'function') {
            applyBarReplaySlice(activeCandleIndex);
        }
    }

    // ── 8. Render Column 2 (Right 25%): 3 Rows ────────────────────────────────────
    function renderTrendingOiRows() {
        if (!trendingOiData || trendingOiData.error) return;
        lastRenderedAtmStrike = null;
        renderTrendingOiStrikes();

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

        // ROW 1: Combined Trending OI & Sentiment (Call vs Put vs Sentiment vs Spot)
        const toiCtx = document.getElementById('chart-trending-oi');
        if (toiCtx) {
            if (toiChartInstance) toiChartInstance.destroy();
            toiChartInstance = new Chart(toiCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Call OI Change',
                            data: callOi,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.05)',
                            borderWidth: 2,
                            pointRadius: 1.5,
                            tension: 0.2,
                            yAxisID: 'y',
                            hidden: toiSeriesVisibility.call === false,
                        },
                        {
                            label: 'Put OI Change',
                            data: putOi,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.05)',
                            borderWidth: 2,
                            pointRadius: 1.5,
                            tension: 0.2,
                            yAxisID: 'y',
                            hidden: toiSeriesVisibility.put === false,
                        },
                        {
                            label: 'Sentiment (Diff in OI)',
                            data: sentiment,
                            borderColor: '#f59e0b',
                            backgroundColor: 'rgba(245, 158, 11, 0.05)',
                            borderWidth: 2.5,
                            pointRadius: 1.5,
                            tension: 0.2,
                            yAxisID: 'y',
                            hidden: toiSeriesVisibility.sentiment === false,
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
                            hidden: toiSeriesVisibility.spot === false,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    onHover: (event, activeElements) => {
                        if (activeElements && activeElements.length > 0) {
                            const idx = activeElements[0].index;
                            updateTrendingOiDiffBadge(idx);
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#111827',
                            borderColor: '#374151',
                            borderWidth: 1,
                            callbacks: {
                                label: function(ctx) {
                                    if (ctx.dataset.yAxisID === 'y1') return `Spot Price: ${Number(ctx.parsed.y).toFixed(2)}`;
                                    if (ctx.dataset.label.includes('Sentiment')) return `Sentiment (Diff): ${fmt(ctx.parsed.y)}`;
                                    return `${ctx.dataset.label}: ${fmt(ctx.parsed.y)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 8, family: 'monospace' }, color: '#9ca3af', maxTicksLimit: 7 }
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
                            display: toiSeriesVisibility.spot !== false,
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
            updateToiLegendUI();
        }

        updateTrendingOiDiffBadge();

        // ROW 2: OI Buildup
        renderStrikeBuildupChart();
    }

    // ── 9a. Resolve 5-Minute Strike OI Buildup for Any Given Timestamp ─────────────
    function get5mBuildupForTime(timeStr) {
        if (!trendingOiData) return null;
        const cleanTime = timeStr ? String(timeStr).slice(0, 5) : '';
        const bld5m = trendingOiData.strike_buildup?.['5m'];
        const matrix = trendingOiData.buildup_matrix;

        if (!matrix || !matrix.columns || !matrix.rows || matrix.rows.length === 0) {
            return bld5m;
        }

        // If no cleanTime or it matches the latest column, prefer pre-computed bld5m
        if ((!cleanTime || cleanTime === matrix.columns[0]) && bld5m && bld5m.top_items && bld5m.top_items.length > 0) {
            return bld5m;
        }

        // Locate exact or closest prior 5m candle column in matrix (columns are sorted recent to old)
        let targetCol = null;
        if (matrix.columns.includes(cleanTime)) {
            targetCol = cleanTime;
        } else {
            targetCol = matrix.columns.find(c => c <= cleanTime);
        }

        if (!targetCol) targetCol = matrix.columns[matrix.columns.length - 1];
        if (!targetCol) return bld5m;

        if (targetCol === matrix.columns[0] && bld5m && bld5m.top_items && bld5m.top_items.length > 0) {
            return bld5m;
        }

        const items = [];
        let totalCeChg = 0;
        let totalPeChg = 0;

        matrix.rows.forEach(r => {
            const cell = r.cells ? r.cells[targetCol] : null;
            if (cell && cell.diff_oi !== undefined && cell.diff_oi !== 0) {
                const diffOi = Number(cell.diff_oi || 0);
                const diffLtp = Number(cell.diff_ltp || 0);
                const isCe = (r.option_type === 'CE' || (cell.strike && cell.strike.includes('CE')));
                if (isCe) {
                    totalCeChg += diffOi;
                } else {
                    totalPeChg += diffOi;
                }

                const absDiffLakh = Math.abs(diffOi / 100000);
                const diffLakh = (diffOi / 100000).toFixed(1);

                items.push({
                    strike: r.strike_price,
                    option_type: r.option_type,
                    label: cell.strike || `${r.strike_price} ${r.option_type}`,
                    buildup_type: cell.type,
                    buildup_name: cell.name,
                    diff_oi: diffOi,
                    abs_diff_oi: Math.abs(diffOi),
                    diff_oi_val_lakh: Number(absDiffLakh.toFixed(2)),
                    diff_oi_lakh: cell.diff_oi_lakh || ((diffOi >= 0 ? '+' : '') + diffLakh + ' L'),
                    diff_ltp: (diffLtp >= 0 ? '+' : '') + diffLtp,
                    bar_color: cell.color || '#6b7280'
                });
            }
        });

        if (items.length === 0) {
            return bld5m;
        }

        items.sort((a, b) => b.abs_diff_oi - a.abs_diff_oi);

        let dominant = 'Dual Flow (Rangebound Momentum)';
        if (totalCeChg > totalPeChg * 1.35) {
            dominant = 'Call Writing / CE Buildup Dominant (Ceiling Resistance)';
        } else if (totalPeChg > totalCeChg * 1.35) {
            dominant = 'Put Writing / PE Buildup Dominant (Floor Support)';
        }

        const ceLakh = (totalCeChg / 100000).toFixed(1);
        const peLakh = (totalPeChg / 100000).toFixed(1);

        return {
            window_label: `${targetCol} (5M)`,
            top_items: items.slice(0, 8),
            summary: {
                total_ce_chg_lakh: (totalCeChg >= 0 ? '+' : '') + ceLakh + ' L',
                total_pe_chg_lakh: (totalPeChg >= 0 ? '+' : '') + peLakh + ' L',
                dominant: dominant
            }
        };
    }
    window.get5mBuildupForTime = get5mBuildupForTime;

    // ── 9. Render OI Buildup (Row 2 of Column 2) ──────────────────────────────────
    function renderStrikeBuildupChart(targetTime = null) {
        if (!trendingOiData?.strike_buildup) return;
        const bldData = trendingOiData.strike_buildup;
        let tfData = bldData[activeBuildupTf] || bldData['5m'] || bldData;

        // If 5m view, resolve the completed 5-min candle buildup (latest or selected time)
        if (activeBuildupTf === '5m') {
            const reqTime = targetTime || ((timestampsList && activeCandleIndex >= 0 && activeCandleIndex < timestampsList.length) ? timestampsList[activeCandleIndex] : null);
            if (reqTime) {
                const histData = get5mBuildupForTime(reqTime);
                if (histData) {
                    tfData = histData;
                }
            }
        }

        const windowBadge = document.getElementById('bld-window-badge');
        if (windowBadge) windowBadge.textContent = tfData.window_label || '09:15 - 15:25';

        if (tfData.summary) {
            const ceNet = document.getElementById('bld-ce-net');
            const peNet = document.getElementById('bld-pe-net');
            const dom = document.getElementById('bld-dominant');
            if (ceNet) {
                ceNet.textContent = tfData.summary.total_ce_chg_lakh || '+0.0 L';
                const ceVal = parseFloat(tfData.summary.total_ce_chg_lakh);
                ceNet.className = ceVal >= 0 ? 'font-mono font-bold text-rose-400' : 'font-mono font-bold text-sky-400';
            }
            if (peNet) {
                peNet.textContent = tfData.summary.total_pe_chg_lakh || '+0.0 L';
                const peVal = parseFloat(tfData.summary.total_pe_chg_lakh);
                peNet.className = peVal >= 0 ? 'font-mono font-bold text-emerald-400' : 'font-mono font-bold text-amber-400';
            }
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
                    barThickness: 13,
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

    // ── 9b. FLOW SYNERGY ENGINE (TAB 1: STOCKS VS OPTIONS TIMELINE CONFLUENCE) ────
    function switchBuildupTab(tab) {
        currentBuildupTab = tab;
        try { localStorage.setItem('nifty_unified_bld_tab', tab); } catch (e) {}
        syncBuildupTabUI();
        if (tab === 'synergy') {
            renderFlowSynergy();
        } else {
            renderStrikeBuildupChart();
        }
    }
    window.switchBuildupTab = switchBuildupTab;

    function syncBuildupTabUI() {
        const btnSyn = document.getElementById('tab-btn-synergy');
        const btnBld = document.getElementById('tab-btn-buildup');
        const panSyn = document.getElementById('panel-flow-synergy');
        const panBld = document.getElementById('panel-oi-buildup');
        const ctrlSyn = document.getElementById('synergy-controls');
        const ctrlBld = document.getElementById('buildup-controls');

        if (currentBuildupTab === 'synergy') {
            if (btnSyn) btnSyn.className = 'px-1.5 py-0.5 rounded text-[9.5px] font-bold flex items-center gap-1 transition cursor-pointer bg-indigo-600 text-white shadow-xs';
            if (btnBld) btnBld.className = 'px-1.5 py-0.5 rounded text-[9.5px] font-bold flex items-center gap-1 transition cursor-pointer text-gray-400 hover:text-white';
            if (panSyn) panSyn.classList.remove('hidden');
            if (panBld) panBld.classList.add('hidden');
            if (ctrlSyn) { ctrlSyn.classList.remove('hidden'); ctrlSyn.classList.add('flex'); }
            if (ctrlBld) { ctrlBld.classList.add('hidden'); ctrlBld.classList.remove('flex'); }
        } else {
            if (btnSyn) btnSyn.className = 'px-1.5 py-0.5 rounded text-[9.5px] font-bold flex items-center gap-1 transition cursor-pointer text-gray-400 hover:text-white';
            if (btnBld) btnBld.className = 'px-1.5 py-0.5 rounded text-[9.5px] font-bold flex items-center gap-1 transition cursor-pointer bg-amber-600 text-white shadow-xs';
            if (panSyn) panSyn.classList.add('hidden');
            if (panBld) panBld.classList.remove('hidden');
            if (ctrlSyn) { ctrlSyn.classList.add('hidden'); ctrlSyn.classList.remove('flex'); }
            if (ctrlBld) { ctrlBld.classList.remove('hidden'); ctrlBld.classList.add('flex'); }
        }
    }
    window.syncBuildupTabUI = syncBuildupTabUI;

    function setSynergyChartStyle(style) {
        synergyChartStyle = style;
        try { localStorage.setItem('nifty_unified_synergy_style', style); } catch (e) {}
        syncSynergyControlsUI();
        renderFlowSynergy();
    }
    window.setSynergyChartStyle = setSynergyChartStyle;

    function setSynergyMetricMode(mode) {
        synergyMetricMode = mode;
        try { localStorage.setItem('nifty_unified_synergy_metric', mode); } catch (e) {}
        syncSynergyControlsUI();
        renderFlowSynergy();
    }
    window.setSynergyMetricMode = setSynergyMetricMode;

    function toggleSynergyThresholdMenu(forceState = null) {
        const popover = document.getElementById('synergy-threshold-popover');
        const arrow = document.getElementById('synergy-th-arrow');
        if (!popover) return;
        const isHidden = popover.classList.contains('hidden');
        const shouldOpen = forceState !== null ? forceState : isHidden;
        if (shouldOpen) {
            popover.classList.remove('hidden');
            if (arrow) arrow.style.transform = 'rotate(180deg)';
            syncSynergyThresholdInputs();
        } else {
            popover.classList.add('hidden');
            if (arrow) arrow.style.transform = 'rotate(0deg)';
        }
    }
    window.toggleSynergyThresholdMenu = toggleSynergyThresholdMenu;

    document.addEventListener('click', function(e) {
        const wrap = document.getElementById('synergy-threshold-dropdown-wrap');
        const popover = document.getElementById('synergy-threshold-popover');
        if (!popover || popover.classList.contains('hidden')) return;
        if (wrap && !wrap.contains(e.target) && (!e.composedPath || !e.composedPath().includes(wrap))) {
            toggleSynergyThresholdMenu(false);
        }
    });

    function syncSynergyThresholdInputs() {
        const inWt = document.getElementById('th-input-wt');
        const inCnt = document.getElementById('th-input-cnt');
        const inOpt = document.getElementById('th-input-opt');
        const btnWt = document.getElementById('th-btn-val-wt');
        const btnCnt = document.getElementById('th-btn-val-cnt');
        const btnOpt = document.getElementById('th-btn-val-opt');

        if (inWt) inWt.value = synergyThStkWt;
        if (inCnt) inCnt.value = synergyThStkCnt;
        if (inOpt) inOpt.value = synergyThOptFlow;

        if (btnWt) btnWt.textContent = synergyThStkWt;
        if (btnCnt) btnCnt.textContent = synergyThStkCnt;
        if (btnOpt) btnOpt.textContent = synergyThOptFlow;
    }
    window.syncSynergyThresholdInputs = syncSynergyThresholdInputs;

    function onSynergyThresholdInput(type, val) {
        let num = parseInt(val, 10);
        if (isNaN(num)) return;
        num = Math.max(10, Math.min(99, num));
        applySynergyThresholdVal(type, num);
    }
    window.onSynergyThresholdInput = onSynergyThresholdInput;

    function stepSynergyThreshold(type, step) {
        let current = 75;
        if (type === 'wt') current = synergyThStkWt;
        else if (type === 'cnt') current = synergyThStkCnt;
        else if (type === 'opt') current = synergyThOptFlow;

        const nextVal = Math.max(10, Math.min(99, current + step));
        applySynergyThresholdVal(type, nextVal);
    }
    window.stepSynergyThreshold = stepSynergyThreshold;

    function applySynergyThresholdVal(type, val) {
        if (type === 'wt') {
            synergyThStkWt = val;
            synergyThreshold = val;
            try { localStorage.setItem('nifty_unified_synergy_th_stk_wt', val); } catch (e) {}
        } else if (type === 'cnt') {
            synergyThStkCnt = val;
            try { localStorage.setItem('nifty_unified_synergy_th_stk_cnt', val); } catch (e) {}
        } else if (type === 'opt') {
            synergyThOptFlow = val;
            try { localStorage.setItem('nifty_unified_synergy_th_opt_flow', val); } catch (e) {}
        }
        syncSynergyThresholdInputs();
        renderFlowSynergy();
    }

    function setSynergyThresholdPreset(wt, cnt, opt) {
        synergyThStkWt = wt;
        synergyThStkCnt = cnt;
        synergyThOptFlow = opt;
        synergyThreshold = wt;
        try {
            localStorage.setItem('nifty_unified_synergy_th_stk_wt', wt);
            localStorage.setItem('nifty_unified_synergy_th_stk_cnt', cnt);
            localStorage.setItem('nifty_unified_synergy_th_opt_flow', opt);
        } catch (e) {}
        syncSynergyThresholdInputs();
        renderFlowSynergy();
    }
    window.setSynergyThresholdPreset = setSynergyThresholdPreset;

    function setSynergyThreshold(val) {
        const num = parseInt(val, 10) || 75;
        setSynergyThresholdPreset(num, num, num);
    }
    window.setSynergyThreshold = setSynergyThreshold;

    function setSynergyBarViewMode(mode) {
        synergyBarViewMode = mode;
        try { localStorage.setItem('nifty_unified_synergy_bar_mode', mode); } catch (e) {}
        syncSynergyControlsUI();
        renderFlowSynergy();
    }
    window.setSynergyBarViewMode = setSynergyBarViewMode;

    function syncSynergyControlsUI() {
        const styles = ['hist', 'matrix'];
        styles.forEach(s => {
            const btn = document.getElementById(`synergy-style-${s}`);
            if (btn) {
                if (synergyChartStyle === s) {
                    btn.className = 'px-1.5 py-0.5 rounded transition cursor-pointer bg-sky-600 text-white shadow-xs';
                } else {
                    btn.className = 'px-1.5 py-0.5 rounded transition cursor-pointer text-gray-400 hover:text-white';
                }
            }
        });

        const metrics = ['both', 'weight', 'count'];
        metrics.forEach(m => {
            const btn = document.getElementById(`synergy-metric-${m}`);
            if (btn) {
                if (synergyMetricMode === m) {
                    btn.className = 'px-1.5 py-0.5 rounded transition cursor-pointer bg-indigo-600 text-white shadow-xs';
                } else {
                    btn.className = 'px-1.5 py-0.5 rounded transition cursor-pointer text-gray-400 hover:text-white';
                }
            }
        });

        // Bar view mode toggle: Scroll vs Fit
        const btnScroll = document.getElementById('btn-synergy-bar-scroll');
        const btnFit = document.getElementById('btn-synergy-bar-fit');
        const vmContainer = document.getElementById('synergy-bar-viewmode-container');
        if (vmContainer) {
            vmContainer.style.display = (synergyChartStyle === 'hist') ? 'flex' : 'none';
        }
        if (btnScroll && btnFit) {
            if (synergyBarViewMode === 'scroll') {
                btnScroll.className = 'px-1.5 py-0.2 rounded transition cursor-pointer bg-sky-600 text-white shadow-xs';
                btnFit.className = 'px-1.5 py-0.2 rounded transition cursor-pointer text-gray-400 hover:text-white';
            } else {
                btnScroll.className = 'px-1.5 py-0.2 rounded transition cursor-pointer text-gray-400 hover:text-white';
                btnFit.className = 'px-1.5 py-0.2 rounded transition cursor-pointer bg-sky-600 text-white shadow-xs';
            }
        }

        syncSynergyThresholdInputs();

        const legendCnt = document.getElementById('synergy-legend-cnt');
        if (legendCnt) {
            legendCnt.style.display = (synergyMetricMode === 'weight') ? 'none' : 'flex';
        }
    }
    window.syncSynergyControlsUI = syncSynergyControlsUI;

    function getSynergyDataPoints() {
        let times = (timestampsList && timestampsList.length > 0)
            ? [...timestampsList]
            : Object.keys(weightageData?.breadth_by_time || {}).sort();

        if (!times || times.length === 0) {
            const wKeys = Object.keys(weightageData?.breadth_by_time || {});
            const oKeys = Object.keys(breadthData?.breadth_by_time || {});
            times = Array.from(new Set([...wKeys, ...oKeys])).sort();
        }

        if (!times || times.length === 0) return [];

        const points = [];

        times.forEach((t, idx) => {
            const s = weightageData?.breadth_by_time?.[t];
            const o = breadthData?.breadth_by_time?.[t] || wBreadthData?.breadth_by_time?.[t];

            // 1. NIFTY 50 STOCKS: Dominant value (highest either green or red)
            let stkWt = 0;
            let stkCnt = 0;
            let stkDominant = 'NEUTRAL';

            if (s) {
                const gw = Number(s.green_weight || 0);
                const rw = Number(s.red_weight || 0);
                const gc = Number(s.green_count_pct || 0);
                const rc = Number(s.red_count_pct || 0);

                if (gw >= rw) {
                    stkWt = Math.round(gw);
                    stkCnt = Math.round(gc);
                    stkDominant = 'BULL';
                } else {
                    stkWt = -Math.round(rw);
                    stkCnt = -Math.round(rc);
                    stkDominant = 'BEAR';
                }
            }

            // 2. OPTIONS FLOW: Dominant value (highest either BUY or SELL)
            let optVal = 0;
            let optDominant = 'NEUTRAL';

            if (o) {
                const uw = Number(o.up_weight || 0);
                const dw = Number(o.down_weight || 0);
                if (uw >= dw) {
                    optVal = Math.round(uw);
                    optDominant = 'BULL';
                } else {
                    optVal = -Math.round(dw);
                    optDominant = 'BEAR';
                }
            }

            // 3. Confluence / Divergence Classification with Item-Specific Criteria
            let isStkBull = false;
            let isStkBear = false;
            if (synergyMetricMode === 'both') {
                isStkBull = (stkWt >= synergyThStkWt && stkCnt >= synergyThStkCnt);
                isStkBear = (stkWt <= -synergyThStkWt && stkCnt <= -synergyThStkCnt);
            } else if (synergyMetricMode === 'weight') {
                isStkBull = (stkWt >= synergyThStkWt);
                isStkBear = (stkWt <= -synergyThStkWt);
            } else if (synergyMetricMode === 'count') {
                isStkBull = (stkCnt >= synergyThStkCnt);
                isStkBear = (stkCnt <= -synergyThStkCnt);
            }

            const isOptBull = (optVal >= synergyThOptFlow);
            const isOptBear = (optVal <= -synergyThOptFlow);

            let status = 'NEUTRAL';
            if (isStkBull && isOptBull) {
                status = 'BULL_SYNC'; // Both passing Bull Alert Thresholds
            } else if (isStkBear && isOptBear) {
                status = 'BEAR_SYNC'; // Both passing Bear Alert Thresholds
            } else if ((stkWt >= 25 && optVal <= -25) || (stkWt <= -25 && optVal >= 25)) {
                status = 'DIVERGENT'; // Opposite directions
            }

            points.push({
                index: idx,
                time: t,
                stkWt,
                stkCnt,
                optVal,
                stkDominant,
                optDominant,
                status,
                sRaw: s,
                oRaw: o
            });
        });

        return points;
    }
    window.getSynergyDataPoints = getSynergyDataPoints;

    function renderFlowSynergy() {
        const points = getSynergyDataPoints();
        const canvas = document.getElementById('chart-flow-synergy');
        const matrixContainer = document.getElementById('synergy-matrix-container');
        if (!canvas && !matrixContainer) return;

        // 1. Session Confluence Summary Counts
        let bullSyncCount = 0;
        let bearSyncCount = 0;
        let divergentCount = 0;

        points.forEach(p => {
            if (p.status === 'BULL_SYNC') bullSyncCount++;
            else if (p.status === 'BEAR_SYNC') bearSyncCount++;
            else if (p.status === 'DIVERGENT') divergentCount++;
        });

        const bullEl = document.getElementById('synergy-stat-bull');
        if (bullEl) bullEl.textContent = bullSyncCount;
        const bearEl = document.getElementById('synergy-stat-bear');
        if (bearEl) bearEl.textContent = bearSyncCount;
        const divEl = document.getElementById('synergy-stat-div');
        if (divEl) divEl.textContent = divergentCount;

        // 2. Active Candle Verdict Header
        const activeTime = (timestampsList && activeCandleIndex >= 0 && activeCandleIndex < timestampsList.length)
            ? timestampsList[activeCandleIndex]
            : (points.length > 0 ? points[points.length - 1].time : '09:15');

        const activePoint = points.find(p => p.time === activeTime) || points[points.length - 1];

        const activeBadge = document.getElementById('synergy-active-time-badge');
        if (activeBadge) activeBadge.textContent = activeTime;

        const verdictEl = document.getElementById('synergy-live-verdict');
        if (verdictEl && activePoint) {
            const sSign = activePoint.stkWt >= 0 ? '+' : '';
            const oSign = activePoint.optVal >= 0 ? '+' : '';
            let vText = '';
            let vClass = 'text-gray-300';

            if (activePoint.status === 'BULL_SYNC') {
                vText = `🔥 BULL SYNC`;
                vClass = 'text-emerald-400 font-extrabold animate-pulse';
            } else if (activePoint.status === 'BEAR_SYNC') {
                vText = `⚡ BEAR SYNC`;
                vClass = 'text-rose-400 font-extrabold animate-pulse';
            } else if (activePoint.status === 'DIVERGENT') {
                vText = `⚠️ DIVERGENT`;
                vClass = 'text-amber-400 font-bold';
            } else {
                vText = `⚖️ Equilibrium`;
                vClass = 'text-slate-400';
            }

            verdictEl.innerHTML = `<span class="text-gray-400">${activeTime}:</span> <span class="text-sky-300">Stk ${sSign}${activePoint.stkWt}%</span> · <span class="text-purple-300">Opt ${oSign}${activePoint.optVal}%</span> · <span class="${vClass}">${vText}</span>`;
        }

        if (points.length === 0) return;

        // 3. Dispatch to Selected Visual Style
        if (synergyChartStyle === 'matrix') {
            if (canvas) canvas.classList.add('hidden');
            if (matrixContainer) {
                matrixContainer.classList.remove('hidden');
                renderSynergyMatrix(points, activeTime);
            }
            if (flowSynergyChartInstance) {
                flowSynergyChartInstance.destroy();
                flowSynergyChartInstance = null;
            }
            return;
        }

        if (matrixContainer) matrixContainer.classList.add('hidden');
        if (canvas) canvas.classList.remove('hidden');

        renderSynergyChartJs(points, activeTime);
    }
    window.renderFlowSynergy = renderFlowSynergy;

    function renderSynergyChartJs(points, activeTime) {
        const canvas = document.getElementById('chart-flow-synergy');
        if (!canvas) return;

        if (flowSynergyChartInstance) {
            flowSynergyChartInstance.destroy();
            flowSynergyChartInstance = null;
        }

        // Show latest candle first on the left, oldest later on the right
        const displayPoints = [...points].reverse();
        const labels = displayPoints.map(p => (p.time && p.time.length === 10 && p.time.includes('-')) ? p.time.slice(5) : p.time);
        const datasets = [];

        // Dynamic Sizing for Horizontal Scroll vs Fit View
        const scrollWrapper = document.getElementById('synergy-chart-scroll-wrapper');
        const innerWrapper = document.getElementById('synergy-canvas-inner-wrapper');
        const yAxisRail = document.getElementById('synergy-yaxis-rail');
        const isScroll = (synergyBarViewMode !== 'fit');

        if (scrollWrapper && innerWrapper) {
            if (isScroll) {
                if (yAxisRail) yAxisRail.classList.remove('hidden');
                const availWidth = scrollWrapper.clientWidth || 700;
                // 40px per candle in Wt+Cnt mode (gives each bar ~8.5px with clean 10px spacing between candles)
                const candleWidth = (synergyMetricMode === 'both') ? 40 : 34;
                const requiredWidth = Math.max(availWidth, displayPoints.length * candleWidth + 24);
                innerWrapper.style.width = `${requiredWidth}px`;
                innerWrapper.style.minWidth = '100%';
                scrollWrapper.style.overflowX = 'auto';
            } else {
                if (yAxisRail) yAxisRail.classList.add('hidden');
                innerWrapper.style.width = '100%';
                innerWrapper.style.minWidth = '100%';
                scrollWrapper.style.overflowX = 'hidden';
            }

            // Bind horizontal mouse-wheel glide
            if (!scrollWrapper._wheelBound) {
                scrollWrapper._wheelBound = true;
                scrollWrapper.addEventListener('wheel', (evt) => {
                    if (evt.deltaY !== 0 && !evt.shiftKey) {
                        evt.preventDefault();
                        scrollWrapper.scrollLeft += evt.deltaY;
                    }
                }, { passive: false });
            }
        }

        // Dataset 1: Stocks Weight %
        if (synergyMetricMode === 'both' || synergyMetricMode === 'weight') {
            const stkWtData = displayPoints.map(p => p.stkWt);
            const stkWtColors = displayPoints.map(p => p.stkWt >= 0 ? '#38bdf8' : '#f43f5e');
            datasets.push({
                type: 'bar',
                label: 'Stocks Wt %',
                data: stkWtData,
                backgroundColor: stkWtColors,
                borderColor: stkWtColors,
                borderWidth: 1,
                borderRadius: 2,
                barPercentage: (synergyMetricMode === 'both') ? 0.92 : 0.85,
                categoryPercentage: 0.82,
            });
        }

        // Dataset 2: Stocks Count %
        if (synergyMetricMode === 'both' || synergyMetricMode === 'count') {
            const stkCntData = displayPoints.map(p => p.stkCnt);
            const stkCntColors = displayPoints.map(p => p.stkCnt >= 0 ? '#2dd4bf' : '#fb923c');
            datasets.push({
                type: 'bar',
                label: 'Stocks Cnt %',
                data: stkCntData,
                backgroundColor: stkCntColors,
                borderColor: stkCntColors,
                borderWidth: 1,
                borderRadius: 2,
                barPercentage: (synergyMetricMode === 'both') ? 0.92 : 0.85,
                categoryPercentage: 0.82,
            });
        }

        // Dataset 3: Options Flow %
        const optData = displayPoints.map(p => p.optVal);
        const optColors = displayPoints.map(p => p.optVal >= 0 ? '#a855f7' : '#e11d48');
        datasets.push({
            type: 'bar',
            label: 'Options Flow %',
            data: optData,
            backgroundColor: optColors,
            borderColor: optColors,
            borderWidth: 1,
            borderRadius: 2,
            barPercentage: (synergyMetricMode === 'both') ? 0.92 : 0.85,
            categoryPercentage: 0.82,
        });

        // Custom Plugin for Fixed Left Y-Axis Rail, Threshold Zones, and Prominent Glyphs
        const synergyPlugin = {
            id: 'synergyPlugin',
            afterDraw(chart) {
                const { ctx, chartArea: { top, bottom, left, right }, scales: { x, y } } = chart;
                if (!y || !x) return;

                // 1. Render Fixed Left Y-Axis Rail Canvas
                if (isScroll) {
                    const yAxisCanvas = document.getElementById('chart-synergy-yaxis');
                    if (yAxisCanvas && chart.scales.y) {
                        const dpr = window.devicePixelRatio || 1;
                        const rect = yAxisCanvas.getBoundingClientRect();
                        if (rect.width > 0 && rect.height > 0) {
                            if (yAxisCanvas.width !== Math.round(rect.width * dpr) || yAxisCanvas.height !== Math.round(rect.height * dpr)) {
                                yAxisCanvas.width = Math.round(rect.width * dpr);
                                yAxisCanvas.height = Math.round(rect.height * dpr);
                            }
                            const yCtx = yAxisCanvas.getContext('2d');
                            yCtx.save();
                            yCtx.scale(dpr, dpr);
                            yCtx.clearRect(0, 0, rect.width, rect.height);

                            const yTicks = [100, 50, 0, -50, -100];
                            yTicks.forEach(val => {
                                const yPos = chart.scales.y.getPixelForValue(val);
                                if (yPos >= top - 2 && yPos <= bottom + 2) {
                                    yCtx.fillStyle = (val === 0) ? '#94a3b8' : (val > 0 ? '#34d399' : '#f43f5e');
                                    yCtx.font = (val === 0 || Math.abs(val) === 100) ? 'bold 8.5px monospace' : '8px monospace';
                                    yCtx.textAlign = 'right';
                                    yCtx.textBaseline = 'middle';
                                    yCtx.fillText(`${val > 0 ? '+' : ''}${val}%`, rect.width - 4, yPos);
                                }
                            });

                            // Threshold indicators on fixed rail
                            if (synergyThStkWt) {
                                const yStkBull = chart.scales.y.getPixelForValue(synergyThStkWt);
                                if (yStkBull >= top && yStkBull <= bottom) {
                                    yCtx.fillStyle = '#38bdf8';
                                    yCtx.font = 'bold 7.5px monospace';
                                    yCtx.textAlign = 'right';
                                    yCtx.textBaseline = 'middle';
                                    yCtx.fillText(`W${synergyThStkWt}`, rect.width - 4, yStkBull);
                                }
                                const yStkBear = chart.scales.y.getPixelForValue(-synergyThStkWt);
                                if (yStkBear >= top && yStkBear <= bottom) {
                                    yCtx.fillStyle = '#f43f5e';
                                    yCtx.font = 'bold 7.5px monospace';
                                    yCtx.textAlign = 'right';
                                    yCtx.textBaseline = 'middle';
                                    yCtx.fillText(`-W${synergyThStkWt}`, rect.width - 4, yStkBear);
                                }
                            }
                            if (synergyThOptFlow && synergyThOptFlow !== synergyThStkWt) {
                                const yOptBull = chart.scales.y.getPixelForValue(synergyThOptFlow);
                                if (yOptBull >= top && yOptBull <= bottom) {
                                    yCtx.fillStyle = '#c084fc';
                                    yCtx.font = 'bold 7.5px monospace';
                                    yCtx.textAlign = 'right';
                                    yCtx.textBaseline = 'middle';
                                    yCtx.fillText(`O${synergyThOptFlow}`, rect.width - 4, yOptBull);
                                }
                                const yOptBear = chart.scales.y.getPixelForValue(-synergyThOptFlow);
                                if (yOptBear >= top && yOptBear <= bottom) {
                                    yCtx.fillStyle = '#e11d48';
                                    yCtx.font = 'bold 7.5px monospace';
                                    yCtx.textAlign = 'right';
                                    yCtx.textBaseline = 'middle';
                                    yCtx.fillText(`-O${synergyThOptFlow}`, rect.width - 4, yOptBear);
                                }
                            }
                            yCtx.restore();
                        }
                    }
                }

                ctx.save();

                // 2. Faint Zero Center Line
                const yZero = y.getPixelForValue(0);
                if (yZero >= top && yZero <= bottom) {
                    ctx.beginPath();
                    ctx.lineWidth = 1;
                    ctx.strokeStyle = 'rgba(148, 163, 184, 0.25)';
                    ctx.moveTo(left, yZero);
                    ctx.lineTo(right, yZero);
                    ctx.stroke();
                }

                // 3. Bull & Bear Threshold Lines / Zones
                if (synergyThStkWt === synergyThOptFlow) {
                    const yBull = y.getPixelForValue(synergyThStkWt);
                    if (yBull >= top && yBull <= bottom) {
                        ctx.fillStyle = 'rgba(16, 185, 129, 0.05)';
                        ctx.fillRect(left, top, right - left, yBull - top);

                        ctx.beginPath();
                        ctx.setLineDash([3, 3]);
                        ctx.lineWidth = 1;
                        ctx.strokeStyle = 'rgba(52, 211, 153, 0.35)';
                        ctx.moveTo(left, yBull);
                        ctx.lineTo(right, yBull);
                        ctx.stroke();

                        ctx.fillStyle = 'rgba(52, 211, 153, 0.85)';
                        ctx.font = 'bold 8px monospace';
                        ctx.textAlign = 'right';
                        ctx.fillText(`+${synergyThStkWt}%`, right - 4, yBull - 3);
                    }

                    const yBear = y.getPixelForValue(-synergyThStkWt);
                    if (yBear >= top && yBear <= bottom) {
                        ctx.fillStyle = 'rgba(244, 63, 94, 0.05)';
                        ctx.fillRect(left, yBear, right - left, bottom - yBear);

                        ctx.beginPath();
                        ctx.setLineDash([3, 3]);
                        ctx.lineWidth = 1;
                        ctx.strokeStyle = 'rgba(244, 63, 94, 0.35)';
                        ctx.moveTo(left, yBear);
                        ctx.lineTo(right, yBear);
                        ctx.stroke();

                        ctx.fillStyle = 'rgba(244, 63, 94, 0.85)';
                        ctx.font = 'bold 8px monospace';
                        ctx.textAlign = 'right';
                        ctx.fillText(`-${synergyThStkWt}%`, right - 4, yBear + 9);
                    }
                } else {
                    // Stocks Bull Line
                    const yStkBull = y.getPixelForValue(synergyThStkWt);
                    if (yStkBull >= top && yStkBull <= bottom) {
                        ctx.beginPath();
                        ctx.setLineDash([3, 3]);
                        ctx.lineWidth = 1;
                        ctx.strokeStyle = 'rgba(56, 189, 248, 0.45)';
                        ctx.moveTo(left, yStkBull);
                        ctx.lineTo(right, yStkBull);
                        ctx.stroke();

                        ctx.fillStyle = 'rgba(56, 189, 248, 0.9)';
                        ctx.font = 'bold 8px monospace';
                        ctx.textAlign = 'right';
                        ctx.fillText(`Stk +${synergyThStkWt}%`, right - 4, yStkBull - 3);
                    }

                    // Options Bull Line
                    const yOptBull = y.getPixelForValue(synergyThOptFlow);
                    if (yOptBull >= top && yOptBull <= bottom) {
                        ctx.beginPath();
                        ctx.setLineDash([3, 3]);
                        ctx.lineWidth = 1;
                        ctx.strokeStyle = 'rgba(192, 132, 252, 0.45)';
                        ctx.moveTo(left, yOptBull);
                        ctx.lineTo(right, yOptBull);
                        ctx.stroke();

                        ctx.fillStyle = 'rgba(192, 132, 252, 0.9)';
                        ctx.font = 'bold 8px monospace';
                        ctx.textAlign = 'right';
                        ctx.fillText(`Opt +${synergyThOptFlow}%`, right - 4, yOptBull - 3);
                    }

                    // Stocks Bear Line
                    const yStkBear = y.getPixelForValue(-synergyThStkWt);
                    if (yStkBear >= top && yStkBear <= bottom) {
                        ctx.beginPath();
                        ctx.setLineDash([3, 3]);
                        ctx.lineWidth = 1;
                        ctx.strokeStyle = 'rgba(244, 63, 94, 0.4)';
                        ctx.moveTo(left, yStkBear);
                        ctx.lineTo(right, yStkBear);
                        ctx.stroke();

                        ctx.fillStyle = 'rgba(244, 63, 94, 0.9)';
                        ctx.font = 'bold 8px monospace';
                        ctx.textAlign = 'right';
                        ctx.fillText(`Stk -${synergyThStkWt}%`, right - 4, yStkBear + 9);
                    }

                    // Options Bear Line
                    const yOptBear = y.getPixelForValue(-synergyThOptFlow);
                    if (yOptBear >= top && yOptBear <= bottom) {
                        ctx.beginPath();
                        ctx.setLineDash([3, 3]);
                        ctx.lineWidth = 1;
                        ctx.strokeStyle = 'rgba(225, 29, 72, 0.4)';
                        ctx.moveTo(left, yOptBear);
                        ctx.lineTo(right, yOptBear);
                        ctx.stroke();

                        ctx.fillStyle = 'rgba(225, 29, 72, 0.9)';
                        ctx.font = 'bold 8px monospace';
                        ctx.textAlign = 'right';
                        ctx.fillText(`Opt -${synergyThOptFlow}%`, right - 4, yOptBear + 9);
                    }
                }
                ctx.setLineDash([]);

                // 4. Confluence Glyphs (Bull Sync in Green, Bear Sync in Red, Divergent with amber badge)
                displayPoints.forEach((p, i) => {
                    const xPos = x.getPixelForTick(i);
                    if (xPos < left || xPos > right) return;

                    if (p.status === 'BULL_SYNC') {
                        // Green Badge + Green Up Triangle at top
                        ctx.fillStyle = 'rgba(16, 185, 129, 0.25)';
                        ctx.beginPath();
                        ctx.arc(xPos, top + 11, 7.5, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.strokeStyle = '#10b981';
                        ctx.lineWidth = 1.2;
                        ctx.stroke();

                        ctx.fillStyle = '#10b981';
                        ctx.font = 'bold 10px sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillText('▲', xPos, top + 11);
                    } else if (p.status === 'BEAR_SYNC') {
                        // Red Badge + Red Down Triangle at bottom
                        ctx.fillStyle = 'rgba(244, 63, 94, 0.25)';
                        ctx.beginPath();
                        ctx.arc(xPos, bottom - 11, 7.5, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.strokeStyle = '#f43f5e';
                        ctx.lineWidth = 1.2;
                        ctx.stroke();

                        ctx.fillStyle = '#f43f5e';
                        ctx.font = 'bold 10px sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillText('▼', xPos, bottom - 11);
                    } else if (p.status === 'DIVERGENT') {
                        // Amber badge placed at bottom area without colliding with bars
                        const yDiv = bottom - 11;
                        ctx.fillStyle = 'rgba(245, 158, 11, 0.2)';
                        ctx.beginPath();
                        ctx.arc(xPos, yDiv, 7, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.strokeStyle = '#f59e0b';
                        ctx.lineWidth = 1;
                        ctx.stroke();

                        ctx.fillStyle = '#f59e0b';
                        ctx.font = 'bold 8.5px sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillText('⚠️', xPos, yDiv);
                    }
                });

                // 5. Vertical Indicator Line & Highlight Pill for Active/Current Time
                const activeIdx = displayPoints.findIndex(p => p.time === activeTime);
                if (activeIdx !== -1) {
                    const xPos = x.getPixelForTick(activeIdx);
                    if (xPos >= left && xPos <= right) {
                        // Soft highlight column band
                        const colWidth = isScroll ? 34 : Math.max(12, Math.min(26, (right - left) / displayPoints.length));
                        ctx.fillStyle = 'rgba(56, 189, 248, 0.15)';
                        ctx.fillRect(xPos - colWidth / 2, top, colWidth, bottom - top);

                        // Sharp luminous vertical indicator line
                        ctx.beginPath();
                        ctx.lineWidth = 2;
                        ctx.strokeStyle = '#38bdf8';
                        ctx.moveTo(xPos, top);
                        ctx.lineTo(xPos, bottom);
                        ctx.stroke();

                        // Top Active Time Pill Badge
                        const pillText = `▼ ${activeTime}`;
                        ctx.font = 'bold 8.5px monospace';
                        const pillW = ctx.measureText(pillText).width + 8;
                        const pillH = 14;
                        const pillX = Math.max(left, Math.min(right - pillW, xPos - pillW / 2));
                        const pillY = top + 1;

                        ctx.fillStyle = '#0284c7';
                        ctx.beginPath();
                        if (ctx.roundRect) {
                            ctx.roundRect(pillX, pillY, pillW, pillH, 3);
                        } else {
                            ctx.rect(pillX, pillY, pillW, pillH);
                        }
                        ctx.fill();

                        ctx.fillStyle = '#ffffff';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(pillText, pillX + pillW / 2, pillY + pillH / 2);
                    }
                }

                ctx.restore();
            }
        };

        flowSynergyChartInstance = new Chart(canvas, {
            data: {
                labels: labels,
                datasets: datasets
            },
            plugins: [synergyPlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 9, family: 'monospace', weight: isScroll ? 'bold' : 'normal' },
                            color: (ctx) => {
                                const idx = ctx.index;
                                const p = displayPoints[idx];
                                return (p && p.time === activeTime) ? '#38bdf8' : '#9ca3af';
                            },
                            maxRotation: 0,
                            autoSkip: !isScroll,
                            maxTicksLimit: isScroll ? undefined : 14
                        }
                    },
                    y: {
                        min: -100,
                        max: 100,
                        grid: {
                            color: 'rgba(55, 65, 81, 0.25)',
                            drawBorder: false,
                            drawTicks: false
                        },
                        ticks: {
                            display: !isScroll,
                            font: { size: 8, family: 'monospace' },
                            color: '#9ca3af',
                            stepSize: 50,
                            callback: v => `${v}%`
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#111827',
                        borderColor: '#374151',
                        borderWidth: 1,
                        titleFont: { size: 10, weight: 'bold', family: 'monospace' },
                        bodyFont: { size: 9, family: 'monospace' },
                        callbacks: {
                            title: (items) => {
                                const p = displayPoints[items[0].dataIndex];
                                if (!p) return '';
                                const vLabel = p.status === 'BULL_SYNC' ? '🔥 Bull Sync' : p.status === 'BEAR_SYNC' ? '⚡ Bear Sync' : p.status === 'DIVERGENT' ? '⚠️ Divergent (Trap)' : 'Equilibrium';
                                return `${p.time} • ${vLabel}`;
                            },
                            label: (context) => {
                                const val = context.raw;
                                const sign = val >= 0 ? '+' : '';
                                return ` ${context.dataset.label}: ${sign}${val}%`;
                            }
                        }
                    }
                },
                onClick: (evt, elements) => {
                    if (elements && elements.length > 0) {
                        const displayIdx = elements[0].index;
                        const targetPoint = displayPoints[displayIdx];
                        if (targetPoint && typeof syncActiveCandleIndex === 'function') {
                            syncActiveCandleIndex(targetPoint.index);
                        }
                    }
                }
            }
        });
    }

    function renderSynergyMatrix(points, activeTime) {
        const container = document.getElementById('synergy-matrix-container');
        if (!container) return;

        // Show latest candle first on the left, oldest later on the right
        const displayPoints = [...points].reverse();

        let html = `
            <table class="min-w-full text-center border-collapse font-mono select-none">
                <thead>
                    <tr class="text-[8.5px] text-gray-400 border-b border-gray-800">
                        <th class="p-1 text-left sticky left-0 bg-[#0f131c] z-10 w-14">Time</th>
        `;

        displayPoints.forEach(p => {
            const isAct = (p.time === activeTime);
            const colLabel = (p.time && p.time.length === 10 && p.time.includes('-')) ? p.time.slice(5) : p.time;
            html += `<th class="px-1.5 py-0.5 cursor-pointer hover:bg-slate-800 ${isAct ? 'bg-sky-950 text-sky-300 font-black border-x border-sky-500/50' : ''}" onclick="syncActiveCandleIndex(${p.index})" title="${p.time}">${colLabel}</th>`;
        });

        html += `</tr></thead><tbody>`;

        // Row 1: Stock Weight %
        html += `<tr class="border-b border-gray-850 text-[9px]"><td class="p-1 text-left sticky left-0 bg-[#0f131c] z-10 font-bold text-sky-400">Stk Wt</td>`;
        displayPoints.forEach(p => {
            const isAct = (p.time === activeTime);
            const sign = p.stkWt >= 0 ? '+' : '';
            const cls = p.stkWt >= 0 ? 'text-emerald-400' : 'text-rose-400';
            html += `<td class="px-1 py-0.5 cursor-pointer hover:bg-slate-800 ${cls} ${isAct ? 'bg-sky-950/50 font-bold border-x border-sky-500/50' : ''}" onclick="syncActiveCandleIndex(${p.index})">${sign}${p.stkWt}%</td>`;
        });
        html += `</tr>`;

        // Row 2: Stock Count %
        if (synergyMetricMode !== 'weight') {
            html += `<tr class="border-b border-gray-850 text-[9px]"><td class="p-1 text-left sticky left-0 bg-[#0f131c] z-10 font-bold text-teal-400">Stk Cnt</td>`;
            displayPoints.forEach(p => {
                const isAct = (p.time === activeTime);
                const sign = p.stkCnt >= 0 ? '+' : '';
                const cls = p.stkCnt >= 0 ? 'text-emerald-400' : 'text-orange-400';
                html += `<td class="px-1 py-0.5 cursor-pointer hover:bg-slate-800 ${cls} ${isAct ? 'bg-sky-950/50 font-bold border-x border-sky-500/50' : ''}" onclick="syncActiveCandleIndex(${p.index})">${sign}${p.stkCnt}%</td>`;
            });
            html += `</tr>`;
        }

        // Row 3: Options Flow %
        html += `<tr class="border-b border-gray-850 text-[9px]"><td class="p-1 text-left sticky left-0 bg-[#0f131c] z-10 font-bold text-purple-400">Opt Flow</td>`;
        displayPoints.forEach(p => {
            const isAct = (p.time === activeTime);
            const sign = p.optVal >= 0 ? '+' : '';
            const cls = p.optVal >= 0 ? 'text-purple-300' : 'text-rose-400';
            html += `<td class="px-1 py-0.5 cursor-pointer hover:bg-slate-800 ${cls} ${isAct ? 'bg-sky-950/50 font-bold border-x border-sky-500/50' : ''}" onclick="syncActiveCandleIndex(${p.index})">${sign}${p.optVal}%</td>`;
        });
        html += `</tr>`;

        // Row 4: Synergy Verdict Icon (Bigger, Bull in Green, Bear in Red)
        html += `<tr class="text-[9px]"><td class="p-1 text-left sticky left-0 bg-[#0f131c] z-10 font-bold text-gray-400">Verdict</td>`;
        displayPoints.forEach(p => {
            const isAct = (p.time === activeTime);
            let badge = '<span class="text-slate-500 text-[10px]" title="Equilibrium">⚖️</span>';
            if (p.status === 'BULL_SYNC') {
                badge = `<span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-950/80 border border-emerald-500/60 shadow-[0_0_6px_rgba(16,185,129,0.4)]" title="Bull Sync (StkWt ≥+${synergyThStkWt}%, StkCnt ≥+${synergyThStkCnt}%, Opt ≥+${synergyThOptFlow}%)">
                    <svg class="w-3.5 h-3.5 text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.527.82-1.17 1.942-1.632 3.31a8.47 8.47 0 00-.73 2.766c-.143.19-.313.364-.51.514-.99.754-1.25 1.956-1.25 3.037 0 3.298 2.684 5.975 6 5.975s6-2.677 6-5.975c0-2.316-1.31-4.225-2.73-5.59a11.2 11.2 0 00-2.876-2.096zm-1.898 9.387c.22.25.474.469.754.65.626.405 1.344.61 2.072.61 1.706 0 3.09-1.384 3.09-3.09 0-1.096-.582-2.07-1.472-2.92a9.664 9.664 0 00-2.228-1.585 1 1 0 00-1.378.895c0 .324-.038.647-.113.96a6.83 6.83 0 01-1.314 2.48z" clip-rule="evenodd"/>
                    </svg>
                </span>`;
            } else if (p.status === 'BEAR_SYNC') {
                badge = `<span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-rose-950/80 border border-rose-500/60 shadow-[0_0_6px_rgba(244,63,94,0.4)]" title="Bear Sync (StkWt ≤-${synergyThStkWt}%, StkCnt ≤-${synergyThStkCnt}%, Opt ≤-${synergyThOptFlow}%)">
                    <svg class="w-3.5 h-3.5 text-rose-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/>
                    </svg>
                </span>`;
            } else if (p.status === 'DIVERGENT') {
                badge = `<span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-950/60 border border-amber-500/40" title="Divergent">
                    <svg class="w-3 h-3 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                    </svg>
                </span>`;
            }
            html += `<td class="px-1 py-1 cursor-pointer hover:bg-slate-800 ${isAct ? 'bg-sky-950/50 border-x border-sky-500/50' : ''}" onclick="syncActiveCandleIndex(${p.index})">${badge}</td>`;
        });
        html += `</tr></tbody></table>`;

        container.innerHTML = html;
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
        // Fallback only if no 5m candle data exists yet: strictly filter trendingOiData labels to 5m intervals
        if (timeSet.size === 0 && trendingOiData?.chart?.labels && trendingOiData.chart.labels.length > 0) {
            trendingOiData.chart.labels.forEach(t => {
                const parts = String(t).split(':');
                if (parts.length >= 2) {
                    const min = parseInt(parts[1], 10);
                    if (!isNaN(min) && min % 5 === 0) {
                        timeSet.add(parts[0].padStart(2, '0') + ':' + parts[1].padStart(2, '0'));
                    }
                }
            });
        }

        if (timeSet.size > 0) {
            timestampsList = Array.from(timeSet).sort();
        }

        if (timestampsList.length === 0) return;

        const masterSlider = document.getElementById('master-slider');
        if (masterSlider) {
            masterSlider.max = timestampsList.length - 1;
        }

        const masterSelect = document.getElementById('master-time-select');
        if (masterSelect) {
            masterSelect.innerHTML = timestampsList.map((t, idx) => `<option value="${idx}" class="bg-[#131722] text-white">${t}</option>`).join('');
        }

        let targetIdx;
        if (isLiveMarketOpen() && !isPlaying) {
            targetIdx = timestampsList.length - 1;
        } else {
            targetIdx = (activeCandleIndex >= 0 && activeCandleIndex < timestampsList.length)
                ? activeCandleIndex
                : (timestampsList.length - 1);
        }
        syncActiveCandleIndex(targetIdx);
    }

    // ── Live Market Hours Detection & Live Snapping ────────────────────────────
    function isLiveMarketOpen() {
        const now = new Date();
        const istOffset = 5.5 * 3600 * 1000;
        const utcMs = now.getTime() + (now.getTimezoneOffset() * 60000);
        const istDate = new Date(utcMs + istOffset);
        const dayOfWeek = istDate.getDay(); // 0 = Sun, 6 = Sat
        const isWeekday = (dayOfWeek >= 1 && dayOfWeek <= 5);
        const todayIstStr = istDate.toISOString().slice(0, 10);
        const currentSecsOfDay = istDate.getHours() * 3600 + istDate.getMinutes() * 60 + istDate.getSeconds();
        const marketOpenSecs = 9 * 3600 + 15 * 60; // 09:15:00 = 33300
        const marketCloseSecs = 15 * 3600 + 30 * 60; // 15:30:00 = 55800

        return (typeof selectedDate !== 'undefined' && selectedDate === todayIstStr && isWeekday && currentSecsOfDay >= marketOpenSecs && currentSecsOfDay < marketCloseSecs);
    }
    window.isLiveMarketOpen = isLiveMarketOpen;

    function snapToLiveCandle() {
        if (!timestampsList || timestampsList.length === 0) return;
        syncActiveCandleIndex(timestampsList.length - 1);
        if (wChart) {
            try { wChart.timeScale().scrollToRealTime(); } catch (e) {}
        }
        if (oChart) {
            try { oChart.timeScale().scrollToRealTime(); } catch (e) {}
        }
    }
    window.snapToLiveCandle = snapToLiveCandle;

    /**
     * Reset Cockpit to User's Default Settings:
     * - Nifty 50 stocks: 5min, SPOT, CANDLE, OF to Today & Yesterday Fut Sup & Rest, Sig: Breach, Wick: Off, STK: ON, OPT: OFF
     * - Options chart: 15min, SPOT, HA (Heikin Ashi), OF line OFF, Sig: Breach, Wick: Off, STK: ON, OPT: ON
     * - Both Alarms enabled: Stocks to 80/80, Options to 75/75
     * - Market in Live (at the top) Current candle
     */
    function resetToDefaultSettings() {
        const btn = document.getElementById('btn-reset-default');
        const btnIcon = document.getElementById('btn-reset-default-icon');
        const btnText = document.getElementById('btn-reset-default-text');
        if (btnText) btnText.textContent = 'Resetting...';
        if (btnIcon) btnIcon.textContent = '⏳';

        // Reset Active Candle Highlight: OFF by default
        if (typeof toggleActiveCandleHighlight === 'function') {
            toggleActiveCandleHighlight(false);
        }

        // 1. Timeframe & Candle Basis: Stocks 5min spot, Options 15min spot
        const targetWTf = '5m';
        const targetWBasis = 'spot';
        const targetOTf = '15m';
        const targetOBasis = 'spot';

        const needReload1 = (wTimeframe !== targetWTf || wChartBasis !== targetWBasis);
        const needReload2 = (oTimeframe !== targetOTf || oChartBasis !== targetOBasis);

        wTimeframe = targetWTf;
        wChartBasis = targetWBasis;
        try { localStorage.setItem('nifty_unified_w_tf', targetWTf); } catch(e){}
        try { localStorage.setItem(wBasisStorageKey, targetWBasis); } catch(e){}

        oTimeframe = targetOTf;
        oChartBasis = targetOBasis;
        try { localStorage.setItem('nifty_unified_o_tf', targetOTf); } catch(e){}
        try { localStorage.setItem(oBasisStorageKey, targetOBasis); } catch(e){}

        updateTimeframeButtonsUI();
        updateChartBasisUI();
        if (typeof updateCandleCloseCountdown === 'function') updateCandleCloseCountdown();

        // 2. Candle Style: Stocks CANDLE, Options HA
        wCandleStyle = 'candle';
        oCandleStyle = 'heikin_ashi';
        try { localStorage.setItem('nifty_unified_w_candle_style', 'candle'); } catch(e){}
        try { localStorage.setItem('nifty_unified_o_candle_style', 'heikin_ashi'); } catch(e){}
        updateCandleStyleUI();

        // 3. Order Flow (OF) Settings:
        // Stocks: OF ON
        wShowOfSrLines = true;
        try { localStorage.setItem('nifty_unified_w_of_sr_lines', 'true'); } catch(e){}

        // Options: OF line OFF
        oShowOfSrLines = false;
        try { localStorage.setItem('nifty_unified_o_of_sr_lines', 'false'); } catch(e){}

        // OF Levels: Today and Yesterday Fut Sup & Rest
        alarmSettings.ofShowToday = true;
        alarmSettings.ofShowYesterday = true;

        multiDayOfSelected = {};
        multiDayOfSelected['today_fut_ask'] = true; // TD Fut Resistance
        multiDayOfSelected['today_fut_bid'] = true; // TD Fut Support
        multiDayOfSelected['today_spot_ask'] = false;
        multiDayOfSelected['today_spot_bid'] = false;

        const ydDate = (multiDayOfData?.days?.find(d => d.day_offset === 1)?.date)
            || (multiDayOfData?.days?.[0]?.date)
            || (orderFlowLevelsData?.prev_date);

        if (ydDate) {
            multiDayOfSelected[`${ydDate}_fut_ask`] = true; // YD Fut Resistance
            multiDayOfSelected[`${ydDate}_fut_bid`] = true; // YD Fut Support
            multiDayOfSelected[`${ydDate}_spot_ask`] = false;
            multiDayOfSelected[`${ydDate}_spot_bid`] = false;
        }

        if (multiDayOfData?.days) {
            multiDayOfData.days.forEach(d => {
                if (d.date !== ydDate) {
                    multiDayOfSelected[`${d.date}_fut_ask`] = false;
                    multiDayOfSelected[`${d.date}_fut_bid`] = false;
                    multiDayOfSelected[`${d.date}_spot_ask`] = false;
                    multiDayOfSelected[`${d.date}_spot_bid`] = false;
                }
            });
        }

        saveMultiDayOfSelected();
        syncCalibrationModeFromSelections();
        updateOfSrButtonUI();
        updateHistOfBadgeCount();
        if (typeof renderHistOfDropdownContent === 'function') {
            renderHistOfDropdownContent();
        }

        // 4. Signals, Wick Filters, and STK/OPT Badges:
        // Sig: Breach on both
        alarmSettings.stocksMarkerMode = 'breach';
        alarmSettings.stocksShowMarkers = true;
        alarmSettings.optionsMarkerMode = 'breach';
        alarmSettings.optionsShowMarkers = true;

        // Wick :Off on both
        alarmSettings.stocksHaWickFilter = false;
        alarmSettings.optionsHaWickFilter = false;

        // Stocks Chart: STK: ON, OPT: OFF
        alarmSettings.chart1ShowStocks = true;
        alarmSettings.chart1ShowOptions = false;

        // Options Chart: STK: ON, OPT: ON
        alarmSettings.chart2ShowStocks = true;
        alarmSettings.chart2ShowOptions = true;

        // 5. Alarms: Both enabled with Stock to 80 and 80, option to 75 and 75
        alarmSettings.masterEnabled = true;
        alarmSettings.stocksAlertEnabled = true;
        alarmSettings.stocksUpThreshold = 80;
        alarmSettings.stocksDownThreshold = 80;
        alarmSettings.optionsAlertEnabled = true;
        alarmSettings.optionsUpThreshold = 75;
        alarmSettings.optionsDownThreshold = 75;

        saveAlarmSettings();
        syncAlarmToolbarUI();
        syncModalFieldsWithSettings();
        updateChartSignalButtonsUI();
        updateHaWickFilterUI();
        updateMarkerButtonsUI();

        // 6. Market should be in Live (at the top) Current candle
        if (isPlaying) {
            isPlaying = false;
            if (playIntervalId) clearInterval(playIntervalId);
            const icon = document.getElementById('play-icon');
            const text = document.getElementById('play-text');
            if (icon) icon.textContent = '▶';
            if (text) text.textContent = 'Play';
        }

        // 6b. OI Buildup: Reset to 5M
        activeBuildupTf = '5m';
        document.querySelectorAll('.bld-tf-btn').forEach(b => {
            b.classList.remove('bg-blue-600', 'text-white');
            b.classList.add('text-gray-400');
            if (b.dataset.tf === '5m') {
                b.classList.add('bg-blue-600', 'text-white');
                b.classList.remove('text-gray-400');
            }
        });
        if (typeof renderStrikeBuildupChart === 'function') {
            renderStrikeBuildupChart();
        }

        // 7. Redraw and Reload Execution
        const finishReset = () => {
            renderWeightageColumn();
            renderOptionsBreadthColumn();
            renderWeightageSuperTrend();
            renderOptionsSuperTrend();
            drawWeightageOrderFlowLines();
            drawOptionsOrderFlowLines();
            drawMultiDayOrderFlowLines();
            snapToLiveCandle();

            // Active Candle Highlight: OFF by default
            if (typeof toggleActiveCandleHighlight === 'function') {
                toggleActiveCandleHighlight(false);
            }

            if (btnText) btnText.textContent = 'Default ✓';
            if (btnIcon) btnIcon.textContent = '✅';
            setTimeout(() => {
                if (btnText) btnText.textContent = 'Default';
                if (btnIcon) btnIcon.textContent = '🔄';
            }, 1500);
        };

        if (needReload1 && needReload2) {
            Promise.all([reloadChart1Weightage(), reloadChart2Options()]).then(finishReset).catch(finishReset);
        } else if (needReload1) {
            reloadChart1Weightage().then(finishReset).catch(finishReset);
        } else if (needReload2) {
            reloadChart2Options().then(finishReset).catch(finishReset);
        } else {
            finishReset();
        }
    }
    window.resetToDefaultSettings = resetToDefaultSettings;

    /**
     * Generate 1-Click AI Market Structure & Order Flow Snapshot JSON
     * Aggregates live price, basis spread, 5m Heikin Ashi wick characteristics,
     * Cash Breadth (advance/decline, weight, top drivers), Options Surface (call/put flow, PCR, net delta),
     * Order Flow Liquidity walls (nearest Ask/Bid distances), Institutional Conflict Diagnosis, and Quant Pivots.
     */
    function generateAiMarketSnapshot() {
        const symbol = typeof SYMBOL !== 'undefined' ? SYMBOL : (window.location.pathname.includes('banknifty') ? 'BANKNIFTY' : 'NIFTY');
        const timeStr = (typeof timestampsList !== 'undefined' && timestampsList[activeCandleIndex]) ? timestampsList[activeCandleIndex] : 'LIVE';

        // Spot & Futures
        const spotText = document.getElementById('spot-price-display')?.textContent || '';
        const spotPrice = parseFloat(spotText.replace(/[^0-9.]/g, '')) || 0;
        const spotChange = document.getElementById('spot-change-display')?.textContent?.trim() || '';

        // Active Candle (wTimeframe, e.g. 5m)
        const stkData = wWeightageData || weightageData;
        const candles = stkData?.candles || stkData?.nifty_candles || [];
        const candle = (timeStr ? (typeof findCandleForTime === 'function' ? findCandleForTime(candles, timeStr) : null) : null) || candles[activeCandleIndex] || null;

        // HA Wick analysis for active candle
        let haAnalysis = null;
        if (typeof getCandleHaWickAnalysis === 'function' && candle) {
            haAnalysis = getCandleHaWickAnalysis(Number(candle.time || candle.timestamp), false, candle.time_str);
        }

        // Stocks Cash Breadth
        const bStk = stkData?.breadth_by_time?.[timeStr] || stkData?.breadth_by_time?.[candle?.time_str] || null;

        // Options Flow Breadth
        const optData = oBreadthData || breadthData;
        const bOpt = optData?.breadth_by_time?.[timeStr] || optData?.breadth_by_time?.[candle?.time_str] || null;

        // Driver Diagnosis
        const diagText = document.getElementById('driver-diagnosis-text')?.textContent?.trim() || 'BALANCED MARKET EQUILIBRIUM';

        // Order Flow Nearest S/R
        const ofTodayAsks = orderFlowLevelsData?.today?.asks || [];
        const ofTodayBids = orderFlowLevelsData?.today?.bids || [];
        const ofYdAsks = orderFlowLevelsData?.yesterday?.asks || [];
        const ofYdBids = orderFlowLevelsData?.yesterday?.bids || [];

        let nearestRes = null;
        let nearestSup = null;
        const allOfLevels = [];
        ofTodayAsks.forEach(a => allOfLevels.push({ price: a.price, qty: a.qty, type: 'RES', source: 'TODAY_FUT_ASK' }));
        ofYdAsks.forEach(a => allOfLevels.push({ price: a.price, qty: a.qty, type: 'RES', source: 'YD_FUT_ASK' }));
        ofTodayBids.forEach(b => allOfLevels.push({ price: b.price, qty: b.qty, type: 'SUP', source: 'TODAY_FUT_BID' }));
        ofYdBids.forEach(b => allOfLevels.push({ price: b.price, qty: b.qty, type: 'SUP', source: 'YD_FUT_BID' }));

        if (spotPrice > 0) {
            const resistances = allOfLevels.filter(l => l.price > spotPrice).sort((a, b) => a.price - b.price);
            const supports = allOfLevels.filter(l => l.price < spotPrice).sort((a, b) => b.price - a.price);
            if (resistances.length > 0) {
                nearestRes = {
                    price: resistances[0].price,
                    source: resistances[0].source,
                    qty: resistances[0].qty || null,
                    distance_pts: Number((resistances[0].price - spotPrice).toFixed(1))
                };
            }
            if (supports.length > 0) {
                nearestSup = {
                    price: supports[0].price,
                    source: supports[0].source,
                    qty: supports[0].qty || null,
                    distance_pts: Number((spotPrice - supports[0].price).toFixed(1))
                };
            }
        }

        // Top heavyweight contributors / draggers
        const topContributors = (bStk?.top_contributors || []).slice(0, 5).map(c => ({
            symbol: c.symbol,
            pts: c.pts_impact ?? c.pts,
            pct: c.pct_chg ?? c.pct
        }));
        const topDraggers = (bStk?.top_draggers || []).slice(0, 5).map(d => ({
            symbol: d.symbol,
            pts: d.pts_impact ?? d.pts,
            pct: d.pct_chg ?? d.pct
        }));

        // Quant playbook pivots
        let quantSnapshot = null;
        if (quantPlaybookData) {
            quantSnapshot = {
                central_pivot: quantPlaybookData.central_pivot || null,
                vwap: quantPlaybookData.vwap || null,
                levels: (quantPlaybookData.levels || []).map(l => ({ name: l.name, price: l.price }))
            };
        }

        return {
            metadata: {
                tool: "nifty-multi-timeframe-monitor",
                symbol: symbol,
                date: selectedDate,
                time: timeStr,
                generated_at: new Date().toLocaleTimeString('en-IN', { timeZone: 'Asia/Kolkata' }) + ' IST'
            },
            market_price: {
                spot: spotPrice,
                change: spotChange,
                basis_spread: (orderFlowLevelsData?.basis_spread !== undefined) ? orderFlowLevelsData.basis_spread : null,
                candle: candle ? {
                    timeframe: wTimeframe,
                    open: Number(candle.open),
                    high: Number(candle.high),
                    low: Number(candle.low),
                    close: Number(candle.close),
                    move_pts: candle.move_pts !== undefined ? Number(candle.move_pts) : Number((candle.close - candle.open).toFixed(2))
                } : null,
                ha_wick_analysis: haAnalysis ? {
                    candle_color: haAnalysis.isHaBull ? 'GREEN' : 'RED',
                    top_wick: haAnalysis.topWick,
                    bottom_wick: haAnalysis.bottomWick,
                    has_both_wicks: haAnalysis.hasBothWicks,
                    is_clean_bull_entry: haAnalysis.isCleanBullEntry,
                    is_clean_bear_entry: haAnalysis.isCleanBearEntry
                } : null
            },
            institutional_driver_diagnosis: diagText,
            cash_equities_breadth: bStk ? {
                timeframe: wTimeframe,
                green_weight_pct: Number(bStk.green_weight),
                red_weight_pct: Number(bStk.red_weight),
                green_count_pct: Number(bStk.green_count_pct),
                red_count_pct: Number(bStk.red_count_pct),
                advances: bStk.green_count,
                declines: bStk.red_count,
                net_green_pts: bStk.net_green_pts,
                net_red_pts: bStk.net_red_pts,
                top_contributors: topContributors,
                top_draggers: topDraggers
            } : null,
            options_flow_breadth: bOpt ? {
                timeframe: oTimeframe,
                call_weight_pct: Number(bOpt.up_weight),
                put_weight_pct: Number(bOpt.down_weight),
                atm_strike: bOpt.atm_strike,
                basket_range: `${bOpt.basket_min} - ${bOpt.basket_max}`,
                verdict: bOpt.verdict,
                pcr: bOpt.greeks?.pcr ?? null,
                net_delta: bOpt.greeks?.net_delta ?? null,
                avg_ce_vega: bOpt.greeks?.avg_ce_vega ?? null,
                avg_pe_vega: bOpt.greeks?.avg_pe_vega ?? null
            } : null,
            order_flow_liquidity_walls: {
                nearest_resistance: nearestRes,
                nearest_support: nearestSup
            },
            oi_buildup_5m: (function() {
                const bld = (typeof get5mBuildupForTime === 'function' && timeStr) 
                    ? get5mBuildupForTime(timeStr) 
                    : trendingOiData?.strike_buildup?.['5m'];
                if (!bld || !bld.top_items || bld.top_items.length === 0) return null;
                return {
                    window: bld.window_label,
                    ce_net_chg: bld.summary?.total_ce_chg_lakh,
                    pe_net_chg: bld.summary?.total_pe_chg_lakh,
                    dominant_flow: bld.summary?.dominant,
                    top_strikes: bld.top_items.slice(0, 8).map(it => ({
                        strike: it.label || `${it.strike} ${it.option_type}`,
                        buildup: `${it.buildup_name} (${it.buildup_type})`,
                        delta_oi: it.diff_oi_lakh,
                        delta_ltp: it.diff_ltp
                    }))
                };
            })(),
            quant_levels: quantSnapshot
        };
    }
    window.generateAiMarketSnapshot = generateAiMarketSnapshot;

    async function copyAiMarketSnapshot() {
        const btn = document.getElementById('btn-ai-snapshot');
        const btnIcon = document.getElementById('btn-ai-snapshot-icon');
        const btnText = document.getElementById('btn-ai-snapshot-text');

        try {
            const snapshot = generateAiMarketSnapshot();
            const jsonStr = JSON.stringify(snapshot, null, 2);

            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(jsonStr);
            } else {
                const ta = document.createElement('textarea');
                ta.value = jsonStr;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            }

            if (btnText) btnText.textContent = 'Copied ✓';
            if (btnIcon) btnIcon.textContent = '✅';
            if (btn) btn.className = 'px-2 py-0.5 bg-emerald-600 text-white font-bold rounded text-[9.5px] flex items-center gap-1 transition shadow-xs cursor-pointer tracking-wide ring-2 ring-emerald-400';

            setTimeout(() => {
                if (btnText) btnText.textContent = 'AI Snapshot';
                if (btnIcon) btnIcon.textContent = '📋';
                if (btn) btn.className = 'px-2 py-0.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 active:scale-95 text-white font-bold rounded text-[9.5px] flex items-center gap-1 transition shadow-xs cursor-pointer tracking-wide';
            }, 2000);
        } catch (err) {
            console.error('Failed to copy snapshot:', err);
            if (btnText) btnText.textContent = 'Error!';
            setTimeout(() => {
                if (btnText) btnText.textContent = 'AI Snapshot';
            }, 1500);
        }
    }
    window.copyAiMarketSnapshot = copyAiMarketSnapshot;

    /**
     * Display a sleek floating notification when a screenshot is successfully saved.
     */
    function showScreenshotNotification(filename, fullPath) {
        let toast = document.getElementById('screenshot-toast-notification');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'screenshot-toast-notification';
            toast.className = 'fixed bottom-5 right-5 z-50 flex items-center gap-2.5 px-3 py-2 bg-[#0e131f]/95 border border-emerald-500/60 rounded-lg shadow-2xl text-xs font-mono text-gray-200 backdrop-blur-md transition-all duration-300 transform translate-y-10 opacity-0';
            document.body.appendChild(toast);
        }

        toast.innerHTML = `
            <span class="text-base">📸</span>
            <div class="flex flex-col">
                <span class="font-bold text-emerald-400">Screenshot Saved (PNG)</span>
                <span class="text-[10px] text-gray-300 break-all select-all font-mono">${fullPath}</span>
            </div>
            <button type="button" onclick="this.parentElement.classList.add('opacity-0', 'translate-y-10')" class="ml-2 text-gray-400 hover:text-white cursor-pointer">&times;</button>
        `;

        requestAnimationFrame(() => {
            toast.classList.remove('opacity-0', 'translate-y-10');
            toast.classList.add('opacity-100', 'translate-y-0');
        });

        setTimeout(() => {
            if (toast) {
                toast.classList.remove('opacity-100', 'translate-y-0');
                toast.classList.add('opacity-0', 'translate-y-10');
            }
        }, 4500);
    }
    window.showScreenshotNotification = showScreenshotNotification;

    /**
     * Capture full page / cockpit as PNG and save to D:\OneDrive - Buffercode\0iohlc\{date}\{timestamp}.png
     */
    async function captureAndSavePageScreenshot() {
        const btn = document.getElementById('btn-print-page');
        const btnIcon = document.getElementById('btn-print-page-icon');
        const btnText = document.getElementById('btn-print-page-text');

        const origIcon = btnIcon ? btnIcon.textContent : '📸';
        const origText = btnText ? btnText.textContent : 'Print';

        if (btnIcon) btnIcon.textContent = '⏳';
        if (btnText) btnText.textContent = 'Capturing...';
        if (btn) btn.disabled = true;

        // Temporary style to hide native scrollbars in screenshot
        const scrollbarStyle = document.createElement('style');
        scrollbarStyle.id = 'temp-hide-scrollbars';
        scrollbarStyle.innerHTML = `
            *::-webkit-scrollbar { display: none !important; width: 0 !important; height: 0 !important; }
            * { scrollbar-width: none !important; -ms-overflow-style: none !important; }
        `;
        document.head.appendChild(scrollbarStyle);

        try {
            // Target the entire cockpit root or document body
            const target = document.getElementById('nifty-unified-root') || document.body;
            let dataUrl = null;

            // In the snapshot, keep the Print button clean (not in capturing state)
            if (btnIcon) btnIcon.textContent = origIcon;
            if (btnText) btnText.textContent = origText;

            // 1. Primary Engine: htmlToImage (Pixel-perfect SVG foreignObject rendered by Chromium)
            if (typeof htmlToImage !== 'undefined') {
                dataUrl = await htmlToImage.toPng(target, {
                    backgroundColor: '#0b0e14',
                    pixelRatio: 1.5, // Ultra-sharp 1.5x resolution
                    skipFonts: true,
                    cacheBust: true
                });
            } else if (typeof html2canvas !== 'undefined') {
                // 2. Secondary Fallback: html2canvas
                const canvas = await html2canvas(target, {
                    backgroundColor: '#0b0e14',
                    useCORS: true,
                    scale: 1.5,
                    logging: false,
                    windowWidth: target.scrollWidth,
                    windowHeight: target.scrollHeight,
                    onclone: (clonedDoc) => {
                        const origCanvases = target.querySelectorAll('canvas');
                        const clonedCanvases = clonedDoc.querySelectorAll('canvas');
                        origCanvases.forEach((orig, idx) => {
                            const clone = clonedCanvases[idx];
                            if (clone && orig && orig.width > 0 && orig.height > 0) {
                                const cloneCtx = clone.getContext('2d');
                                if (cloneCtx) {
                                    try { cloneCtx.drawImage(orig, 0, 0); } catch (e) {}
                                }
                            }
                        });
                    }
                });
                dataUrl = canvas.toDataURL('image/png');
            } else {
                throw new Error('Screenshot library not loaded');
            }

            // Immediately reflect saving progress in UI
            if (btnIcon) btnIcon.textContent = '⏳';
            if (btnText) btnText.textContent = 'Saving...';

            // Format current wall-clock timestamp (HH-mm-ss)
            const now = new Date();
            const pad = (n) => String(n).padStart(2, '0');
            const timeStr = `${pad(now.getHours())}-${pad(now.getMinutes())}-${pad(now.getSeconds())}`;
            const dateStr = (typeof selectedDate !== 'undefined' && selectedDate) ? selectedDate : `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;

            const response = await fetch('/api/multi-timeframe/save-screenshot', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    image: dataUrl,
                    date: dateStr,
                    time: timeStr
                })
            });

            const result = await response.json();

            if (result && result.success) {
                if (btnIcon) btnIcon.textContent = '✅';
                if (btnText) btnText.textContent = 'Saved ✓';
                if (btn) {
                    btn.className = 'px-2 py-0.5 bg-emerald-600 text-white font-bold rounded text-[9.5px] flex items-center gap-1 transition shadow-xs cursor-pointer tracking-wide ring-2 ring-emerald-400';
                    btn.title = `Saved: ${result.path}`;
                }

                showScreenshotNotification(result.filename, result.path);

                setTimeout(() => {
                    if (btnIcon) btnIcon.textContent = origIcon;
                    if (btnText) btnText.textContent = origText;
                    if (btn) {
                        btn.className = 'px-2 py-0.5 bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-500 hover:to-orange-500 active:scale-95 text-white font-bold rounded text-[9.5px] flex items-center gap-1 transition shadow-xs cursor-pointer tracking-wide';
                        btn.disabled = false;
                        btn.title = 'Take full page screenshot & save to D:\\OneDrive - Buffercode\\0iohlc';
                    }
                }, 3000);
            } else {
                throw new Error(result?.error || 'Failed to save screenshot on server');
            }
        } catch (err) {
            console.error('Screenshot error:', err);
            if (btnIcon) btnIcon.textContent = '⚠️';
            if (btnText) btnText.textContent = 'Failed';
            alert('Failed to save screenshot: ' + (err.message || err));
            setTimeout(() => {
                if (btnIcon) btnIcon.textContent = origIcon;
                if (btnText) btnText.textContent = origText;
                if (btn) btn.disabled = false;
            }, 2000);
        } finally {
            if (scrollbarStyle && scrollbarStyle.parentElement) {
                scrollbarStyle.remove();
            }
        }
    }
    window.captureAndSavePageScreenshot = captureAndSavePageScreenshot;

    function syncActiveCandleIndex(index) {
        if (!timestampsList || index < 0 || index >= timestampsList.length) return;
        activeCandleIndex = index;
        const timeStr = timestampsList[index];

        const isLive = (index === timestampsList.length - 1);
        const liveBtn = document.getElementById('btn-snap-live');
        if (liveBtn) {
            if (isLive) {
                liveBtn.className = 'px-1.5 py-0.2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded text-[9.5px] flex items-center gap-1 transition cursor-pointer shadow-[0_0_8px_rgba(16,185,129,0.4)]';
                liveBtn.title = 'Active: Latest Live Candle';
            } else {
                liveBtn.className = 'px-1.5 py-0.2 bg-slate-800 hover:bg-emerald-600 text-slate-300 hover:text-white font-bold rounded text-[9.5px] flex items-center gap-1 transition cursor-pointer border border-slate-700';
                liveBtn.title = `Viewing Historical Candle [${timeStr}] • Click to Snap to Live`;
            }
        }

        const masterBadge = document.getElementById('master-time-badge');
        if (masterBadge) masterBadge.textContent = timeStr;
        const masterSlider = document.getElementById('master-slider');
        if (masterSlider) masterSlider.value = index;
        const masterSelect = document.getElementById('master-time-select');
        if (masterSelect) masterSelect.value = index;

        const wSlider = document.getElementById('w-slider');
        if (wSlider) wSlider.value = index;
        const oSlider = document.getElementById('o-slider');
        if (oSlider) oSlider.value = index;

        syncWeightageUI(index, timeStr);
        syncOptionsBreadthUI(index, timeStr);
        syncTrendingOiCursor(index, timeStr);

        // Highlight active selected candle on charts
        if (typeof updateActiveCandleHighlight === 'function') {
            updateActiveCandleHighlight(timeStr);
        }

        // Check alarms on selected candle
        checkAlarmConditions(timeStr);

        // Update overall Bull/Bear picture and Trigger Strength
        updateOverallAndTriggerStats(index);
        updateDriverDiagnosis(timeStr);

        // Update Flow Synergy Cockpit Tab
        if (typeof renderFlowSynergy === 'function') {
            renderFlowSynergy();
        }

        // True Bar Replay Slicing in v2
        if (typeof isBarReplayMode !== 'undefined' && isBarReplayMode) {
            applyBarReplaySlice(index, timeStr);
        }

        // Run High-Probability Trade Engine & Dynamic Exit Evaluator
        if (typeof runHighProbabilityTradeEngine === 'function') {
            runHighProbabilityTradeEngine(index, timeStr);
        }
    }
    window.syncActiveCandleIndex = syncActiveCandleIndex;

    /**
     * Finds the candle matching or containing timeStr across different timeframes.
     * E.g. When Chart 1 is on 5m (11:25) and Chart 2 is on 15m (11:15-11:30), smoothly resolves to 11:15.
     */
    function findCandleForTime(candles, timeStr) {
        if (!candles || candles.length === 0) return null;
        if (!timeStr) return candles[candles.length - 1];
        const exact = candles.find(c => c.time_str === timeStr);
        if (exact) return exact;

        let candidate = null;
        for (const c of candles) {
            if (c.time_str <= timeStr) {
                candidate = c;
            } else {
                break;
            }
        }
        return candidate || candles[0];
    }
    window.findCandleForTime = findCandleForTime;

    function formatDeltaMicroFooter(currentVal, prevVal, isBullishBox) {
        if (prevVal === null || prevVal === undefined || isNaN(prevVal)) {
            return { deltaText: '--', deltaClass: 'text-gray-500' };
        }
        const diff = Math.round(Number(currentVal) - Number(prevVal));
        if (diff === 0) {
            return { deltaText: '0%', deltaClass: 'text-gray-400' };
        }
        const sign = diff > 0 ? '+' : '';
        const arrow = diff > 0 ? '▲ ' : '▼ ';
        const deltaText = `${arrow}${sign}${diff}%`;
        let deltaClass = 'text-gray-400';
        if (isBullishBox) {
            deltaClass = diff > 0 ? 'text-emerald-400' : 'text-rose-400';
        } else {
            deltaClass = diff > 0 ? 'text-rose-400' : 'text-emerald-400';
        }
        return { deltaText, deltaClass };
    }

    function syncWeightageUI(index, timeStr) {
        const stkData = wWeightageData || weightageData;
        const optData = wBreadthData || breadthData;
        if (!stkData) return;

        // 1. Resolve Chart 1 Candlestick details
        const candles = stkData.candles || stkData.nifty_candles || [];
        const candle = (timeStr ? findCandleForTime(candles, timeStr) : null) || candles[index] || null;
        const resolvedTime = candle?.time_str || timeStr;

        if (candle) {
            document.getElementById('w-chart-time').textContent = candle.time_str;
            const wTfSuffix = (wTimeframe === '1D') ? '1-Day candle' : `${wTimeframe} candle`;
            const wStatTimeEl = document.getElementById('w-stat-time');
            if (wStatTimeEl) wStatTimeEl.textContent = `${candle.time_str} - ${wTfSuffix}`;

            if (wCandleStyle === 'heikin_ashi' && window._unifiedHaCandles && window._unifiedHaCandles.length > 0) {
                const cTime = Number(candle.time || candle.timestamp);
                let haCandle = window._unifiedHaCandles.find(h => Number(h.time) === cTime || h.time_str === candle.time_str);
                if (haCandle) {
                    document.getElementById('w-chart-open').textContent = Number(haCandle.open).toFixed(2);
                    document.getElementById('w-chart-close').textContent = Number(haCandle.close).toFixed(2);
                    const chgEl = document.getElementById('w-chart-chg');
                    const haMove = haCandle.close - haCandle.open;
                    const haMovePct = haCandle.open > 0 ? (haMove / haCandle.open) * 100 : 0;
                    const sign = haMove >= 0 ? '+' : '';
                    chgEl.textContent = `${sign}${haMove.toFixed(2)} (${sign}${haMovePct.toFixed(2)}%) [HA]`;
                    chgEl.className = haMove >= 0 ? 'font-bold text-emerald-400' : 'font-bold text-rose-400';
                }
            } else {
                document.getElementById('w-chart-open').textContent = Number(candle.open).toFixed(2);
                document.getElementById('w-chart-close').textContent = Number(candle.close).toFixed(2);
                const chgEl = document.getElementById('w-chart-chg');
                const sign = candle.move_pts >= 0 ? '+' : '';
                chgEl.textContent = `${sign}${Number(candle.move_pts).toFixed(2)} (${sign}${Number(candle.move_pct).toFixed(2)}%)`;
                chgEl.className = candle.move_pts >= 0 ? 'font-bold text-emerald-400' : 'font-bold text-rose-400';
            }
        }

        // 2. Chart 1 Deck 1: NIFTY 50 STOCKS (wTimeframe)
        const bStk = (resolvedTime ? stkData.breadth_by_time?.[resolvedTime] : null)
                  || (timeStr ? stkData.breadth_by_time?.[timeStr] : null);

        if (candle) {
            const wTfSuffix = (wTimeframe === '1D') ? '1-Day candle' : `${wTimeframe} candle`;
            const wStatTimeEl = document.getElementById('w-stat-time');
            if (wStatTimeEl) {
                const isForming = (bStk && bStk.is_complete === false);
                const tag = isForming
                    ? '<span class="ml-1.5 px-1 py-0.2 text-[8.5px] rounded bg-amber-500/20 text-amber-300 font-bold border border-amber-500/30 animate-pulse">LIVE FORMING</span>'
                    : '<span class="ml-1.5 px-1 py-0.2 text-[8.5px] rounded bg-emerald-500/15 text-emerald-300 font-bold border border-emerald-500/20">CONFIRMED</span>';
                wStatTimeEl.innerHTML = `${candle.time_str} - ${wTfSuffix} ${tag}`;
            }
        }

        if (bStk) {
            const ptsEl = document.getElementById('w-stat-pts');
            if (ptsEl) {
                const sign = bStk.nifty_move_pts >= 0 ? '+' : '';
                ptsEl.textContent = `${sign}${Number(bStk.nifty_move_pts).toFixed(1)} pts (${sign}${Number(bStk.nifty_move_pct).toFixed(2)}%)`;
                ptsEl.className = bStk.nifty_move_pts >= 0 ? 'text-xs font-black font-mono text-emerald-400' : 'text-xs font-black font-mono text-rose-400';
            }

            const gPctEl = document.getElementById('w-stat-green-pct');
            if (gPctEl) gPctEl.textContent = `${bStk.green_count_pct}%`;
            const gWtEl = document.getElementById('w-stat-green-wt');
            if (gWtEl) gWtEl.textContent = `${Number(bStk.green_weight).toFixed(0)}%`;
            const gCntEl = document.getElementById('w-stat-green-cnt');
            if (gCntEl) gCntEl.textContent = bStk.green_count;
            const gPtsEl = document.getElementById('w-stat-green-pts');
            if (gPtsEl) gPtsEl.textContent = `+${Number(bStk.net_green_pts).toFixed(0)}`;

            const rPctEl = document.getElementById('w-stat-red-pct');
            if (rPctEl) rPctEl.textContent = `${bStk.red_count_pct}%`;
            const rWtEl = document.getElementById('w-stat-red-wt');
            if (rWtEl) rWtEl.textContent = `${Number(bStk.red_weight).toFixed(0)}%`;
            const rCntEl = document.getElementById('w-stat-red-cnt');
            if (rCntEl) rCntEl.textContent = bStk.red_count;
            const rPtsEl = document.getElementById('w-stat-red-pts');
            if (rPtsEl) rPtsEl.textContent = `${Number(bStk.net_red_pts).toFixed(0)}`;

            // Previous Candle Micro-Footer for Stocks (Deck 1)
            let bStkPrev = null;
            if (candles && candles.length > 0) {
                let cIdx = -1;
                if (candle) {
                    cIdx = candles.findIndex(c => (c.time && candle.time && c.time === candle.time) || c.time_str === candle.time_str);
                } else if (resolvedTime) {
                    cIdx = candles.findIndex(c => c.time_str === resolvedTime);
                }
                if (cIdx < 0 && index !== undefined && index !== null) cIdx = Number(index);
                if (cIdx > 0 && candles[cIdx - 1]) {
                    const prevCandle = candles[cIdx - 1];
                    bStkPrev = stkData.breadth_by_time?.[prevCandle.time_str] || null;
                }
            }

            const prevGreenValEl = document.getElementById('w-prev-green-val');
            const prevGreenDeltaEl = document.getElementById('w-prev-green-delta');
            const prevRedValEl = document.getElementById('w-prev-red-val');
            const prevRedDeltaEl = document.getElementById('w-prev-red-delta');

            if (bStkPrev) {
                if (prevGreenValEl) prevGreenValEl.textContent = `${bStkPrev.green_count_pct}% (${bStkPrev.green_count} stk)`;
                if (prevGreenDeltaEl) {
                    const d = formatDeltaMicroFooter(bStk.green_count_pct, bStkPrev.green_count_pct, true);
                    prevGreenDeltaEl.textContent = d.deltaText;
                    prevGreenDeltaEl.className = `font-bold ${d.deltaClass}`;
                }
                if (prevRedValEl) prevRedValEl.textContent = `${bStkPrev.red_count_pct}% (${bStkPrev.red_count} stk)`;
                if (prevRedDeltaEl) {
                    const d = formatDeltaMicroFooter(bStk.red_count_pct, bStkPrev.red_count_pct, false);
                    prevRedDeltaEl.textContent = d.deltaText;
                    prevRedDeltaEl.className = `font-bold ${d.deltaClass}`;
                }
            } else {
                if (prevGreenValEl) prevGreenValEl.textContent = '--';
                if (prevGreenDeltaEl) { prevGreenDeltaEl.textContent = '--'; prevGreenDeltaEl.className = 'font-bold text-gray-500'; }
                if (prevRedValEl) prevRedValEl.textContent = '--';
                if (prevRedDeltaEl) { prevRedDeltaEl.textContent = '--'; prevRedDeltaEl.className = 'font-bold text-gray-500'; }
            }

            const barG = document.getElementById('w-bar-green');
            if (barG) barG.style.width = `${bStk.green_weight}%`;
            const barR = document.getElementById('w-bar-red');
            if (barR) barR.style.width = `${bStk.red_weight}%`;
            const splitEl = document.getElementById('w-stat-split');
            if (splitEl) splitEl.textContent = `${Number(bStk.green_weight).toFixed(0)}% G / ${Number(bStk.red_weight).toFixed(0)}% R`;

            if (bStk.insight) {
                const insEl = document.getElementById('w-insight-badge');
                if (insEl) insEl.innerHTML = bStk.insight.badge || '<span>Institutional Conviction</span>';
            }

            // Divergence warning banner
            const divEl = document.getElementById('w-divergence-banner');
            if (divEl) {
                const isGreen = candle ? Number(candle.close) >= Number(candle.open) : false;
                const isRed = candle ? Number(candle.close) < Number(candle.open) : false;
                const upTh = alarmSettings.stocksUpThreshold || 80;
                const downTh = alarmSettings.stocksDownThreshold || 80;
                if (isGreen && bStk.red_count_pct >= downTh && bStk.red_weight >= downTh) {
                    divEl.classList.remove('hidden');
                    divEl.className = 'px-1 py-0.5 rounded bg-amber-950/70 border border-amber-500/60 text-[8px] text-amber-200 mt-0.5 leading-tight';
                    divEl.innerHTML = `⚠️ Bull Trap: ${bStk.red_count_pct}% stocks dragging down!`;
                } else if (isRed && bStk.green_count_pct >= upTh && bStk.green_weight >= upTh) {
                    divEl.classList.remove('hidden');
                    divEl.className = 'px-1 py-0.5 rounded bg-cyan-950/70 border border-cyan-500/60 text-[8px] text-cyan-200 mt-0.5 leading-tight';
                    divEl.innerHTML = `⚡ Bear Trap: ${bStk.green_count_pct}% stocks advancing!`;
                } else {
                    divEl.classList.add('hidden');
                }
            }

            // Top Movers
            const topDrivers = (bStk.top_contributors || []).filter(s => s.pts > 0).slice(0, 2);
            const driversEl = document.getElementById('w-top-drivers');
            if (driversEl) {
                driversEl.innerHTML = topDrivers.length === 0 ? '<span class="text-gray-500 italic">None</span>' : topDrivers.map(s => `
                    <span class="inline-flex items-center gap-0.5 px-1 py-0.2 rounded bg-emerald-950/60 border border-emerald-800/40 text-[8px] font-mono text-emerald-300">
                        <strong class="text-gray-200">${s.symbol}</strong> +${s.pts.toFixed(1)}
                    </span>
                `).join(' ');
            }

            const topDraggers = (bStk.top_draggers || []).filter(s => s.pts < 0).slice(0, 2);
            const draggersEl = document.getElementById('w-top-draggers');
            if (draggersEl) {
                draggersEl.innerHTML = topDraggers.length === 0 ? '<span class="text-gray-500 italic">None</span>' : topDraggers.map(s => `
                    <span class="inline-flex items-center gap-0.5 px-1 py-0.2 rounded bg-rose-950/60 border border-rose-800/40 text-[8px] font-mono text-rose-300">
                        <strong class="text-gray-200">${s.symbol}</strong> ${s.pts.toFixed(1)}
                    </span>
                `).join(' ');
            }
        }

        // 3. Chart 1 Deck 2: OPTIONS FLOW (wTimeframe)
        if (optData) {
            const optCandles = optData.nifty_candles || [];
            const optCandle = (resolvedTime ? findCandleForTime(optCandles, resolvedTime) : null) || (timeStr ? findCandleForTime(optCandles, timeStr) : null);
            const bOpt = (resolvedTime ? optData.breadth_by_time?.[resolvedTime] : null)
                      || (timeStr ? optData.breadth_by_time?.[timeStr] : null);

            if (bOpt) {
                const impEl = document.getElementById('w-opt-stat-impulse');
                if (impEl) {
                    const sign = bOpt.net_points >= 0 ? '+' : '';
                    impEl.textContent = `${sign}${Number(bOpt.net_points).toFixed(1)} pts`;
                    impEl.className = bOpt.net_points >= 0 ? 'text-xs font-black font-mono text-emerald-400' : 'text-xs font-black font-mono text-rose-400';
                }

                const atmEl = document.getElementById('w-opt-stat-atm-val');
                if (atmEl) {
                    const atm = bOpt.atm_strike || (optData.atm_strike || (candle ? Math.round(Number(candle.close) / 50) * 50 : 25000));
                    atmEl.textContent = '₹' + Number(atm).toLocaleString('en-IN');
                }

                const totOptBsk = bOpt.basket_count || ((bOpt.up_count || 0) + (bOpt.down_count || 0) + (bOpt.flat_count || 0)) || 11;
                const optUpPct = (bOpt.up_count_pct !== undefined) ? bOpt.up_count_pct : Math.round(((bOpt.up_count || 0) / totOptBsk) * 100);
                const optDnPct = (bOpt.down_count_pct !== undefined) ? bOpt.down_count_pct : Math.round(((bOpt.down_count || 0) / totOptBsk) * 100);

                const optGRatio = document.getElementById('w-opt-stat-green-ratio');
                if (optGRatio) optGRatio.textContent = `${optUpPct}%`;
                const optGWt = document.getElementById('w-opt-stat-green-wt');
                if (optGWt) optGWt.textContent = `${bOpt.up_weight}% Wt`;
                const optGCnt = document.getElementById('w-opt-stat-green-cnt');
                if (optGCnt) optGCnt.textContent = bOpt.up_count;

                const optRRatio = document.getElementById('w-opt-stat-red-ratio');
                if (optRRatio) optRRatio.textContent = `${optDnPct}%`;
                const optRWt = document.getElementById('w-opt-stat-red-wt');
                if (optRWt) optRWt.textContent = `${bOpt.down_weight}% Wt`;
                const optRCnt = document.getElementById('w-opt-stat-red-cnt');
                if (optRCnt) optRCnt.textContent = bOpt.down_count;

                // Previous Candle Micro-Footer for Options Flow (Deck 2)
                let bOptPrev = null;
                if (optCandles && optCandles.length > 0) {
                    let oIdx = -1;
                    if (optCandle) {
                        oIdx = optCandles.findIndex(c => (c.time && optCandle.time && c.time === optCandle.time) || c.time_str === optCandle.time_str);
                    } else if (resolvedTime) {
                        oIdx = optCandles.findIndex(c => c.time_str === resolvedTime);
                    }
                    if (oIdx > 0 && optCandles[oIdx - 1]) {
                        const prevCandle = optCandles[oIdx - 1];
                        bOptPrev = optData.breadth_by_time?.[prevCandle.time_str] || null;
                    }
                }

                const optPrevGVal = document.getElementById('w-opt-prev-green-val');
                const optPrevGDelta = document.getElementById('w-opt-prev-green-delta');
                const optPrevRVal = document.getElementById('w-opt-prev-red-val');
                const optPrevRDelta = document.getElementById('w-opt-prev-red-delta');

                if (bOptPrev) {
                    const prevTotBsk = bOptPrev.basket_count || ((bOptPrev.up_count || 0) + (bOptPrev.down_count || 0) + (bOptPrev.flat_count || 0)) || 11;
                    const prevUpPct = (bOptPrev.up_count_pct !== undefined) ? bOptPrev.up_count_pct : Math.round(((bOptPrev.up_count || 0) / prevTotBsk) * 100);
                    const prevDnPct = (bOptPrev.down_count_pct !== undefined) ? bOptPrev.down_count_pct : Math.round(((bOptPrev.down_count || 0) / prevTotBsk) * 100);

                    if (optPrevGVal) optPrevGVal.textContent = `${prevUpPct}% (${bOptPrev.up_count} strk)`;
                    if (optPrevGDelta) {
                        const d = formatDeltaMicroFooter(optUpPct, prevUpPct, true);
                        optPrevGDelta.textContent = d.deltaText;
                        optPrevGDelta.className = `font-bold ${d.deltaClass}`;
                    }
                    if (optPrevRVal) optPrevRVal.textContent = `${prevDnPct}% (${bOptPrev.down_count} strk)`;
                    if (optPrevRDelta) {
                        const d = formatDeltaMicroFooter(optDnPct, prevDnPct, false);
                        optPrevRDelta.textContent = d.deltaText;
                        optPrevRDelta.className = `font-bold ${d.deltaClass}`;
                    }
                } else {
                    if (optPrevGVal) optPrevGVal.textContent = '--';
                    if (optPrevGDelta) { optPrevGDelta.textContent = '--'; optPrevGDelta.className = 'font-bold text-gray-500'; }
                    if (optPrevRVal) optPrevRVal.textContent = '--';
                    if (optPrevRDelta) { optPrevRDelta.textContent = '--'; optPrevRDelta.className = 'font-bold text-gray-500'; }
                }

                const optSplit = document.getElementById('w-opt-stat-split');
                if (optSplit) optSplit.textContent = `${bOpt.up_weight}% / ${bOpt.down_weight}%`;
                const optBarG = document.getElementById('w-opt-bar-green');
                if (optBarG) optBarG.style.width = `${bOpt.up_weight}%`;
                const optBarR = document.getElementById('w-opt-bar-red');
                if (optBarR) optBarR.style.width = `${bOpt.down_weight}%`;

                const vTitle = document.getElementById('w-opt-verdict-title');
                if (vTitle) vTitle.textContent = bOpt.verdict || 'Balanced Options Equilibrium';

                if (bOpt.greeks) {
                    const gD = document.getElementById('w-opt-greek-delta');
                    if (gD) gD.textContent = bOpt.greeks.net_delta;
                    const gCE = document.getElementById('w-opt-greek-ce-vega');
                    if (gCE) gCE.textContent = Number(bOpt.greeks.avg_ce_vega).toFixed(1);
                    const gPE = document.getElementById('w-opt-greek-pe-vega');
                    if (gPE) gPE.textContent = Number(bOpt.greeks.avg_pe_vega).toFixed(1);
                    const gPCR = document.getElementById('w-opt-greek-pcr');
                    if (gPCR) gPCR.textContent = Number(bOpt.greeks.pcr).toFixed(2);
                }
            }
        }
    }

    function syncOptionsBreadthUI(index, timeStr) {
        const stkData = oWeightageData || weightageData;
        const optData = oBreadthData || breadthData;
        if (!optData) return;

        // 1. Resolve Chart 2 Candlestick details
        const candles = optData.nifty_candles || [];
        const candle = (timeStr ? findCandleForTime(candles, timeStr) : null) || candles[index] || null;
        const resolvedTime = candle?.time_str || timeStr;

        if (candle) {
            document.getElementById('o-chart-time').textContent = candle.time_str;
            const oTfSuffix = (oTimeframe === '1D') ? '1-Day candle' : `${oTimeframe} candle`;
            const oStatTimeEl = document.getElementById('o-stat-time');
            if (oStatTimeEl) {
                const bOptCheck = (resolvedTime ? optData.breadth_by_time?.[resolvedTime] : null)
                               || (timeStr ? optData.breadth_by_time?.[timeStr] : null);
                const isForming = (bOptCheck && bOptCheck.is_complete === false);
                const tag = isForming
                    ? '<span class="ml-1.5 px-1 py-0.2 text-[8.5px] rounded bg-amber-500/20 text-amber-300 font-bold border border-amber-500/30 animate-pulse">LIVE FORMING</span>'
                    : '<span class="ml-1.5 px-1 py-0.2 text-[8.5px] rounded bg-emerald-500/15 text-emerald-300 font-bold border border-emerald-500/20">CONFIRMED</span>';
                oStatTimeEl.innerHTML = `${candle.time_str} - ${oTfSuffix} ${tag}`;
            }

            if (oCandleStyle === 'heikin_ashi' && window._unifiedOptionsHaCandles && window._unifiedOptionsHaCandles.length > 0) {
                const cTime = Number(candle.time || candle.timestamp);
                let haCandle = window._unifiedOptionsHaCandles.find(h => Number(h.time) === cTime || h.time_str === candle.time_str);
                if (haCandle) {
                    document.getElementById('o-chart-open').textContent = Number(haCandle.open).toFixed(2);
                    document.getElementById('o-chart-close').textContent = Number(haCandle.close).toFixed(2);
                    const moveEl = document.getElementById('o-chart-move');
                    const haMove = haCandle.close - haCandle.open;
                    const haMovePct = haCandle.open > 0 ? (haMove / haCandle.open) * 100 : 0;
                    const sign = haMove >= 0 ? '+' : '';
                    if (moveEl) {
                        moveEl.textContent = `${sign}${haMove.toFixed(2)} (${sign}${haMovePct.toFixed(2)}%) [HA]`;
                        moveEl.className = haMove >= 0 ? 'font-bold text-emerald-400' : 'font-bold text-rose-400';
                    }
                }
            } else {
                document.getElementById('o-chart-open').textContent = Number(candle.open).toFixed(2);
                document.getElementById('o-chart-close').textContent = Number(candle.close).toFixed(2);
                const moveEl = document.getElementById('o-chart-move');
                const sign = candle.move_pts >= 0 ? '+' : '';
                if (moveEl) {
                    moveEl.textContent = `${sign}${Number(candle.move_pts).toFixed(2)} (${sign}${Number(candle.move_pct).toFixed(2)}%)`;
                    moveEl.className = candle.move_pts >= 0 ? 'font-bold text-emerald-400' : 'font-bold text-rose-400';
                }
            }
        }

        // 2. Chart 2 Deck 1: NIFTY 50 STOCKS (oTimeframe)
        if (stkData) {
            const stkCandles = stkData.candles || stkData.nifty_candles || [];
            const stkCandle = (resolvedTime ? findCandleForTime(stkCandles, resolvedTime) : null) || (timeStr ? findCandleForTime(stkCandles, timeStr) : null);
            const bStk = (resolvedTime ? stkData.breadth_by_time?.[resolvedTime] : null)
                      || (timeStr ? stkData.breadth_by_time?.[timeStr] : null);

            if (bStk) {
                const ptsEl = document.getElementById('o-stk-stat-pts');
                if (ptsEl) {
                    const sign = bStk.nifty_move_pts >= 0 ? '+' : '';
                    ptsEl.textContent = `${sign}${Number(bStk.nifty_move_pts).toFixed(1)} pts`;
                    ptsEl.className = bStk.nifty_move_pts >= 0 ? 'text-xs font-black font-mono text-emerald-400' : 'text-xs font-black font-mono text-rose-400';
                }

                const gPctEl = document.getElementById('o-stk-stat-green-pct');
                if (gPctEl) gPctEl.textContent = `${bStk.green_count_pct}%`;
                const gWtEl = document.getElementById('o-stk-stat-green-wt');
                if (gWtEl) gWtEl.textContent = `${Number(bStk.green_weight).toFixed(0)}%`;
                const gCntEl = document.getElementById('o-stk-stat-green-cnt');
                if (gCntEl) gCntEl.textContent = bStk.green_count;
                const gPtsEl = document.getElementById('o-stk-stat-green-pts');
                if (gPtsEl) gPtsEl.textContent = `+${Number(bStk.net_green_pts).toFixed(0)}`;

                const rPctEl = document.getElementById('o-stk-stat-red-pct');
                if (rPctEl) rPctEl.textContent = `${bStk.red_count_pct}%`;
                const rWtEl = document.getElementById('o-stk-stat-red-wt');
                if (rWtEl) rWtEl.textContent = `${Number(bStk.red_weight).toFixed(0)}%`;
                const rCntEl = document.getElementById('o-stk-stat-red-cnt');
                if (rCntEl) rCntEl.textContent = bStk.red_count;
                const rPtsEl = document.getElementById('o-stk-stat-red-pts');
                if (rPtsEl) rPtsEl.textContent = `${Number(bStk.net_red_pts).toFixed(0)}`;

                // Previous Candle Micro-Footer for Chart 2 Stocks (Deck 1)
                let bStkPrev = null;
                if (stkCandles && stkCandles.length > 0) {
                    let sIdx = -1;
                    if (stkCandle) {
                        sIdx = stkCandles.findIndex(c => (c.time && stkCandle.time && c.time === stkCandle.time) || c.time_str === stkCandle.time_str);
                    } else if (resolvedTime) {
                        sIdx = stkCandles.findIndex(c => c.time_str === resolvedTime);
                    }
                    if (sIdx > 0 && stkCandles[sIdx - 1]) {
                        const prevCandle = stkCandles[sIdx - 1];
                        bStkPrev = stkData.breadth_by_time?.[prevCandle.time_str] || null;
                    }
                }

                const oStkPrevGVal = document.getElementById('o-stk-prev-green-val');
                const oStkPrevGDelta = document.getElementById('o-stk-prev-green-delta');
                const oStkPrevRVal = document.getElementById('o-stk-prev-red-val');
                const oStkPrevRDelta = document.getElementById('o-stk-prev-red-delta');

                if (bStkPrev) {
                    if (oStkPrevGVal) oStkPrevGVal.textContent = `${bStkPrev.green_count_pct}% (${bStkPrev.green_count} stk)`;
                    if (oStkPrevGDelta) {
                        const d = formatDeltaMicroFooter(bStk.green_count_pct, bStkPrev.green_count_pct, true);
                        oStkPrevGDelta.textContent = d.deltaText;
                        oStkPrevGDelta.className = `font-bold ${d.deltaClass}`;
                    }
                    if (oStkPrevRVal) oStkPrevRVal.textContent = `${bStkPrev.red_count_pct}% (${bStkPrev.red_count} stk)`;
                    if (oStkPrevRDelta) {
                        const d = formatDeltaMicroFooter(bStk.red_count_pct, bStkPrev.red_count_pct, false);
                        oStkPrevRDelta.textContent = d.deltaText;
                        oStkPrevRDelta.className = `font-bold ${d.deltaClass}`;
                    }
                } else {
                    if (oStkPrevGVal) oStkPrevGVal.textContent = '--';
                    if (oStkPrevGDelta) { oStkPrevGDelta.textContent = '--'; oStkPrevGDelta.className = 'font-bold text-gray-500'; }
                    if (oStkPrevRVal) oStkPrevRVal.textContent = '--';
                    if (oStkPrevRDelta) { oStkPrevRDelta.textContent = '--'; oStkPrevRDelta.className = 'font-bold text-gray-500'; }
                }

                const barG = document.getElementById('o-stk-bar-green');
                if (barG) barG.style.width = `${bStk.green_weight}%`;
                const barR = document.getElementById('o-stk-bar-red');
                if (barR) barR.style.width = `${bStk.red_weight}%`;
                const splitEl = document.getElementById('o-stk-stat-split');
                if (splitEl) splitEl.textContent = `${Number(bStk.green_weight).toFixed(0)}% G / ${Number(bStk.red_weight).toFixed(0)}% R`;

                if (bStk.insight) {
                    const insEl = document.getElementById('o-stk-insight-badge');
                    if (insEl) insEl.innerHTML = bStk.insight.badge || '<span>Institutional Conviction</span>';
                }

                // Top Movers for Chart 2
                const topDrivers = (bStk.top_contributors || []).filter(s => s.pts > 0).slice(0, 2);
                const driversEl = document.getElementById('o-stk-top-drivers');
                if (driversEl) {
                    driversEl.innerHTML = topDrivers.length === 0 ? '<span class="text-gray-500 italic">None</span>' : topDrivers.map(s => `
                        <span class="inline-flex items-center gap-0.5 px-1 py-0.2 rounded bg-emerald-950/60 border border-emerald-800/40 text-[8px] font-mono text-emerald-300">
                            <strong class="text-gray-200">${s.symbol}</strong> +${s.pts.toFixed(1)}
                        </span>
                    `).join(' ');
                }

                const topDraggers = (bStk.top_draggers || []).filter(s => s.pts < 0).slice(0, 2);
                const draggersEl = document.getElementById('o-stk-top-draggers');
                if (draggersEl) {
                    draggersEl.innerHTML = topDraggers.length === 0 ? '<span class="text-gray-500 italic">None</span>' : topDraggers.map(s => `
                        <span class="inline-flex items-center gap-0.5 px-1 py-0.2 rounded bg-rose-950/60 border border-rose-800/40 text-[8px] font-mono text-rose-300">
                            <strong class="text-gray-200">${s.symbol}</strong> ${s.pts.toFixed(1)}
                        </span>
                    `).join(' ');
                }
            }
        }

        // 3. Chart 2 Deck 2: OPTIONS FLOW (oTimeframe)
        const bOpt = (resolvedTime ? optData.breadth_by_time?.[resolvedTime] : null)
                  || (timeStr ? optData.breadth_by_time?.[timeStr] : null);
        if (bOpt) {
            const impEl = document.getElementById('o-stat-impulse');
            if (impEl) {
                const sign = bOpt.net_points >= 0 ? '+' : '';
                impEl.textContent = `${sign}${Number(bOpt.net_points).toFixed(1)} pts`;
                impEl.className = bOpt.net_points >= 0 ? 'text-xs font-black font-mono text-emerald-400' : 'text-xs font-black font-mono text-rose-400';
            }

            const atmValEl = document.getElementById('o-stat-atm-val');
            if (atmValEl) {
                const atm = bOpt.atm_strike || (optData.atm_strike || (candle ? Math.round(Number(candle.close) / 50) * 50 : 25000));
                atmValEl.textContent = '₹' + Number(atm).toLocaleString('en-IN');
            }

            const totOptBsk = bOpt.basket_count || ((bOpt.up_count || 0) + (bOpt.down_count || 0) + (bOpt.flat_count || 0)) || 11;
            const optUpPct = (bOpt.up_count_pct !== undefined) ? bOpt.up_count_pct : Math.round(((bOpt.up_count || 0) / totOptBsk) * 100);
            const optDnPct = (bOpt.down_count_pct !== undefined) ? bOpt.down_count_pct : Math.round(((bOpt.down_count || 0) / totOptBsk) * 100);

            const gRatio = document.getElementById('o-stat-green-ratio');
            if (gRatio) gRatio.textContent = `${optUpPct}%`;
            const gWt = document.getElementById('o-stat-green-wt');
            if (gWt) gWt.textContent = `${bOpt.up_weight}% Wt`;
            const gCnt = document.getElementById('o-stat-green-cnt');
            if (gCnt) gCnt.textContent = bOpt.up_count;

            const rRatio = document.getElementById('o-stat-red-ratio');
            if (rRatio) rRatio.textContent = `${optDnPct}%`;
            const rWt = document.getElementById('o-stat-red-wt');
            if (rWt) rWt.textContent = `${bOpt.down_weight}% Wt`;
            const rCnt = document.getElementById('o-stat-red-cnt');
            if (rCnt) rCnt.textContent = bOpt.down_count;

            // Previous Candle Micro-Footer for Chart 2 Options (Deck 2)
            let bOptPrev = null;
            if (candles && candles.length > 0) {
                let oIdx = -1;
                if (candle) {
                    oIdx = candles.findIndex(c => (c.time && candle.time && c.time === candle.time) || c.time_str === candle.time_str);
                } else if (resolvedTime) {
                    oIdx = candles.findIndex(c => c.time_str === resolvedTime);
                }
                if (oIdx < 0 && index !== undefined && index !== null) oIdx = Number(index);
                if (oIdx > 0 && candles[oIdx - 1]) {
                    const prevCandle = candles[oIdx - 1];
                    bOptPrev = optData.breadth_by_time?.[prevCandle.time_str] || null;
                }
            }

            const oOptPrevGVal = document.getElementById('o-opt-prev-green-val');
            const oOptPrevGDelta = document.getElementById('o-opt-prev-green-delta');
            const oOptPrevRVal = document.getElementById('o-opt-prev-red-val');
            const oOptPrevRDelta = document.getElementById('o-opt-prev-red-delta');

            if (bOptPrev) {
                const prevTotBsk = bOptPrev.basket_count || ((bOptPrev.up_count || 0) + (bOptPrev.down_count || 0) + (bOptPrev.flat_count || 0)) || 11;
                const prevUpPct = (bOptPrev.up_count_pct !== undefined) ? bOptPrev.up_count_pct : Math.round(((bOptPrev.up_count || 0) / prevTotBsk) * 100);
                const prevDnPct = (bOptPrev.down_count_pct !== undefined) ? bOptPrev.down_count_pct : Math.round(((bOptPrev.down_count || 0) / prevTotBsk) * 100);

                if (oOptPrevGVal) oOptPrevGVal.textContent = `${prevUpPct}% (${bOptPrev.up_count} strk)`;
                if (oOptPrevGDelta) {
                    const d = formatDeltaMicroFooter(optUpPct, prevUpPct, true);
                    oOptPrevGDelta.textContent = d.deltaText;
                    oOptPrevGDelta.className = `font-bold ${d.deltaClass}`;
                }
                if (oOptPrevRVal) oOptPrevRVal.textContent = `${prevDnPct}% (${bOptPrev.down_count} strk)`;
                if (oOptPrevRDelta) {
                    const d = formatDeltaMicroFooter(optDnPct, prevDnPct, false);
                    oOptPrevRDelta.textContent = d.deltaText;
                    oOptPrevRDelta.className = `font-bold ${d.deltaClass}`;
                }
            } else {
                if (oOptPrevGVal) oOptPrevGVal.textContent = '--';
                if (oOptPrevGDelta) { oOptPrevGDelta.textContent = '--'; oOptPrevGDelta.className = 'font-bold text-gray-500'; }
                if (oOptPrevRVal) oOptPrevRVal.textContent = '--';
                if (oOptPrevRDelta) { oOptPrevRDelta.textContent = '--'; oOptPrevRDelta.className = 'font-bold text-gray-500'; }
            }

            const splitEl = document.getElementById('o-stat-split');
            if (splitEl) splitEl.textContent = `${bOpt.up_weight}% / ${bOpt.down_weight}%`;
            const barG = document.getElementById('o-bar-green');
            if (barG) barG.style.width = `${bOpt.up_weight}%`;
            const barR = document.getElementById('o-bar-red');
            if (barR) barR.style.width = `${bOpt.down_weight}%`;

            const vTitle = document.getElementById('o-verdict-title');
            if (vTitle) vTitle.textContent = bOpt.verdict || 'Balanced Options Equilibrium';

            if (bOpt.greeks) {
                const gD = document.getElementById('o-greek-delta');
                if (gD) gD.textContent = bOpt.greeks.net_delta;
                const gCE = document.getElementById('o-greek-ce-vega');
                if (gCE) gCE.textContent = Number(bOpt.greeks.avg_ce_vega).toFixed(1);
                const gPE = document.getElementById('o-greek-pe-vega');
                if (gPE) gPE.textContent = Number(bOpt.greeks.avg_pe_vega).toFixed(1);
                const gPCR = document.getElementById('o-greek-pcr');
                if (gPCR) gPCR.textContent = Number(bOpt.greeks.pcr).toFixed(2);
            }
        }
    }

    function syncTrendingOiCursor(index, timeStr) {
        if (!trendingOiData) return;
        if (activeBuildupTf === '5m') {
            const badge = document.getElementById('bld-window-badge');
            if (badge) badge.textContent = `${timeStr} (5M)`;
            renderStrikeBuildupChart(timeStr);
        }
        if (trendingOiData.chart && trendingOiData.chart.labels) {
            const labels = trendingOiData.chart.labels;
            const cleanTime = timeStr ? String(timeStr).slice(0, 5) : '';
            let matchIdx = -1;
            for (let i = labels.length - 1; i >= 0; i--) {
                const l = String(labels[i] || '').slice(0, 5);
                if (l === cleanTime) {
                    matchIdx = i;
                    break;
                }
            }
            if (matchIdx !== -1) {
                updateTrendingOiDiffBadge(matchIdx);
            } else if (index >= 0 && index < labels.length) {
                updateTrendingOiDiffBadge(index);
            } else {
                updateTrendingOiDiffBadge();
            }
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
                stkVerdictBadge.className = 'px-2 py-0.5 rounded text-[9.5px] font-mono font-bold bg-gray-800 text-gray-400 border border-gray-700';
            }
            if (stkLeadText) {
                stkLeadText.textContent = 'Nothing happened (No triggers reached)';
                stkLeadText.className = 'text-gray-400 font-medium truncate text-[9px]';
            }
        } else if (stkPosHits > stkNegHits) {
            const margin = stkPosHits - stkNegHits;
            if (stkVerdictBadge) {
                stkVerdictBadge.textContent = `🐂 BULLS STRONGER (+${margin})`;
                stkVerdictBadge.className = 'px-2 py-0.5 rounded text-[9.5px] font-mono font-bold bg-emerald-950 text-emerald-300 border border-emerald-600/50 shadow-xs';
            }
            if (stkLeadText) {
                stkLeadText.textContent = `Bulls lead by +${margin} trigger (${stkPosHits} Pos vs ${stkNegHits} Neg)`;
                stkLeadText.className = 'text-emerald-300 font-bold truncate text-[9px]';
            }
        } else if (stkNegHits > stkPosHits) {
            const margin = stkNegHits - stkPosHits;
            if (stkVerdictBadge) {
                stkVerdictBadge.textContent = `🐻 BEARS STRONGER (+${margin})`;
                stkVerdictBadge.className = 'px-2 py-0.5 rounded text-[9.5px] font-mono font-bold bg-rose-950 text-rose-300 border border-rose-600/50 shadow-xs';
            }
            if (stkLeadText) {
                stkLeadText.textContent = `Bears lead by +${margin} trigger (${stkNegHits} Neg vs ${stkPosHits} Pos)`;
                stkLeadText.className = 'text-rose-300 font-bold truncate text-[9px]';
            }
        } else {
            if (stkVerdictBadge) {
                stkVerdictBadge.textContent = `⚖️ TIED (${stkPosHits} Each)`;
                stkVerdictBadge.className = 'px-2 py-0.5 rounded text-[9.5px] font-mono font-bold bg-amber-950 text-amber-300 border border-amber-600/50 shadow-xs';
            }
            if (stkLeadText) {
                stkLeadText.textContent = `Tied: ${stkPosHits} positive vs ${stkNegHits} negative`;
                stkLeadText.className = 'text-amber-300 font-bold truncate text-[9px]';
            }
        }

        // ════════════════ 2. NIFTY OPTIONS TRIGGER ANALYSIS ════════════════
        let optPosHits = 0, optNegHits = 0;
        let optPosMax = 0, optPosMaxTime = '';
        let optNegMax = 0, optNegMaxTime = '';
        let sumOBull = 0, sumOBear = 0, oCount = 0;
        let optBullTraps = 0, optBearTraps = 0;
        const trapTimes = [];

        activeTimes.forEach(t => {
            const ob = breadthData?.breadth_by_time?.[t];
            const oCandle = (breadthData?.nifty_candles || []).find(c => c.time_str === t);
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

                if (oCandle) {
                    const isGreen = Number(oCandle.close) >= Number(oCandle.open);
                    const isRed   = Number(oCandle.close) < Number(oCandle.open);
                    if (isGreen && dw >= optsDownTh) {
                        optBullTraps++;
                        trapTimes.push(`${t} (${Math.round(dw)}%)`);
                    } else if (isRed && uw >= optsUpTh) {
                        optBearTraps++;
                        trapTimes.push(`${t} (${Math.round(uw)}%)`);
                    }
                }
            }
        });

        const trapStrip = document.getElementById('opt-trap-tracker-strip');
        const trapBadge = document.getElementById('opt-trap-summary-badge');
        const trapTimesEl = document.getElementById('opt-trap-times-badge');
        if (trapStrip && trapBadge) {
            const totalTraps = optBullTraps + optBearTraps;
            if (totalTraps > 0) {
                trapStrip.classList.remove('hidden');
                trapBadge.textContent = `${totalTraps} Trap${totalTraps > 1 ? 's' : ''} (${optBullTraps} Bull, ${optBearTraps} Bear)`;
                if (trapTimesEl) trapTimesEl.textContent = trapTimes.join(', ');
            } else {
                trapStrip.classList.add('hidden');
            }
        }

        const avgOBull = oCount > 0 ? (sumOBull / oCount) : 50;
        const avgOBear = oCount > 0 ? (sumOBear / oCount) : 50;

        // Populate Options UI (Chart 2)
        const optPosCntEl = document.getElementById('opt-pos-triggers-count');
        if (optPosCntEl) optPosCntEl.textContent = optPosHits;
        const optNegCntEl = document.getElementById('opt-neg-triggers-count');
        if (optNegCntEl) optNegCntEl.textContent = optNegHits;

        // Chart 1 Options Triggers (wBreadthData)
        if (wBreadthData) {
            let wOptPos = 0, wOptNeg = 0;
            activeTimes.forEach(t => {
                const ob = wBreadthData?.breadth_by_time?.[t];
                if (ob) {
                    if ((parseFloat(ob.up_weight) || 0) >= optsUpTh) wOptPos++;
                    if ((parseFloat(ob.down_weight) || 0) >= optsDownTh) wOptNeg++;
                }
            });
            const wOptPosEl = document.getElementById('w-opt-pos-triggers-count');
            if (wOptPosEl) wOptPosEl.textContent = wOptPos;
            const wOptNegEl = document.getElementById('w-opt-neg-triggers-count');
            if (wOptNegEl) wOptNegEl.textContent = wOptNeg;
        } else {
            const wOptPosEl = document.getElementById('w-opt-pos-triggers-count');
            if (wOptPosEl) wOptPosEl.textContent = optPosHits;
            const wOptNegEl = document.getElementById('w-opt-neg-triggers-count');
            if (wOptNegEl) wOptNegEl.textContent = optNegHits;
        }

        // Chart 2 Stocks Triggers (oWeightageData)
        if (oWeightageData) {
            let oStkPos = 0, oStkNeg = 0;
            activeTimes.forEach(t => {
                const wb = oWeightageData?.breadth_by_time?.[t];
                if (wb) {
                    const gw = parseFloat(wb.green_weight) || 0;
                    const rw = parseFloat(wb.red_weight) || 0;
                    const gcp = parseFloat(wb.green_count_pct) || 0;
                    const rcp = parseFloat(wb.red_count_pct) || 0;
                    let isG = false, isR = false;
                    if (stocksTrig === 'both') { isG = (gcp >= stocksUpTh && gw >= stocksUpTh); isR = (rcp >= stocksDownTh && rw >= stocksDownTh); }
                    else if (stocksTrig === 'either') { isG = (gcp >= stocksUpTh || gw >= stocksUpTh); isR = (rcp >= stocksDownTh || rw >= stocksDownTh); }
                    else if (stocksTrig === 'count') { isG = (gcp >= stocksUpTh); isR = (rcp >= stocksDownTh); }
                    else if (stocksTrig === 'weight') { isG = (gw >= stocksUpTh); isR = (rw >= stocksDownTh); }
                    if (isG) oStkPos++;
                    if (isR) oStkNeg++;
                }
            });
            const oStkPosEl = document.getElementById('o-stk-pos-triggers-count');
            if (oStkPosEl) oStkPosEl.textContent = oStkPos;
            const oStkNegEl = document.getElementById('o-stk-neg-triggers-count');
            if (oStkNegEl) oStkNegEl.textContent = oStkNeg;
        } else {
            const oStkPosEl = document.getElementById('o-stk-pos-triggers-count');
            if (oStkPosEl) oStkPosEl.textContent = stkPosHits;
            const oStkNegEl = document.getElementById('o-stk-neg-triggers-count');
            if (oStkNegEl) oStkNegEl.textContent = stkNegHits;
        }

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
                optVerdictBadge.className = 'px-2 py-0.5 rounded text-[9.5px] font-mono font-bold bg-gray-800 text-gray-400 border border-gray-700';
            }
            if (optLeadText) {
                if (optsUpTh >= 80) {
                    optLeadText.innerHTML = `No triggers at ≥${optsUpTh}% • <span class="text-sky-400 font-bold underline cursor-pointer hover:text-white" onclick="cycleOptionsTriggerThreshold()" title="Click to set recommended 70% threshold">Click here to set 70%</span>`;
                } else {
                    optLeadText.textContent = 'Nothing happened (No triggers reached)';
                }
                optLeadText.className = 'text-gray-400 font-medium truncate text-[9px]';
            }
        } else if (optPosHits > optNegHits) {
            const margin = optPosHits - optNegHits;
            if (optVerdictBadge) {
                optVerdictBadge.textContent = `🐂 BULLS STRONGER (+${margin})`;
                optVerdictBadge.className = 'px-2 py-0.5 rounded text-[9.5px] font-mono font-bold bg-emerald-950 text-emerald-300 border border-emerald-600/50 shadow-xs';
            }
            if (optLeadText) {
                optLeadText.textContent = `Bulls lead by +${margin} trigger (${optPosHits} Pos vs ${optNegHits} Neg)`;
                optLeadText.className = 'text-emerald-300 font-bold truncate text-[9px]';
            }
        } else if (optNegHits > optPosHits) {
            const margin = optNegHits - optPosHits;
            if (optVerdictBadge) {
                optVerdictBadge.textContent = `🐻 BEARS STRONGER (+${margin})`;
                optVerdictBadge.className = 'px-2 py-0.5 rounded text-[9.5px] font-mono font-bold bg-rose-950 text-rose-300 border border-rose-600/50 shadow-xs';
            }
            if (optLeadText) {
                optLeadText.textContent = `Bears lead by +${margin} trigger (${optNegHits} Neg vs ${optPosHits} Pos)`;
                optLeadText.className = 'text-rose-300 font-bold truncate text-[9px]';
            }
        } else {
            if (optVerdictBadge) {
                optVerdictBadge.textContent = `⚖️ TIED (${optPosHits} Each)`;
                optVerdictBadge.className = 'px-2 py-0.5 rounded text-[9.5px] font-mono font-bold bg-amber-950 text-amber-300 border border-amber-600/50 shadow-xs';
            }
            if (optLeadText) {
                optLeadText.textContent = `Tied: ${optPosHits} positive vs ${optNegHits} negative`;
                optLeadText.className = 'text-amber-300 font-bold truncate text-[9px]';
            }
        }

        // ════════════════ 3. COMBINED CONFLUENCE ANALYSIS ════════════════
        const combPosHits = stkPosHits + optPosHits;
        const combNegHits = stkNegHits + optNegHits;
        const combBadge = document.getElementById('comb-trigger-badge');

        if (combBadge) {
            if (combPosHits === 0 && combNegHits === 0) {
                combBadge.textContent = 'Nothing happened';
                combBadge.className = 'font-bold text-gray-400 text-[10px]';
            } else if (combPosHits > combNegHits) {
                const margin = combPosHits - combNegHits;
                combBadge.textContent = `🐂 BULLS STRONGER (+${margin}) • ${combPosHits} Pos vs ${combNegHits} Neg`;
                combBadge.className = 'font-bold text-emerald-400 text-[10px]';
            } else if (combNegHits > combPosHits) {
                const margin = combNegHits - combPosHits;
                combBadge.textContent = `🐻 BEARS STRONGER (+${margin}) • ${combNegHits} Neg vs ${combPosHits} Pos`;
                combBadge.className = 'font-bold text-rose-400 text-[10px]';
            } else {
                combBadge.textContent = `⚖️ TIED (${combPosHits} Pos & ${combNegHits} Neg)`;
                combBadge.className = 'font-bold text-amber-300 text-[10px]';
            }
        }

        // Also update driver diagnosis on trigger stats refresh
        const activeTimeStr = timestampsList?.[maxIdx] || '';
        if (activeTimeStr) updateDriverDiagnosis(activeTimeStr);
    }

    // ── 10c. Institutional Driver Diagnosis & Conflict Resolver ──────────────────
    function updateDriverDiagnosis(timeStr) {
        const pill = document.getElementById('driver-diagnosis-pill');
        const icon = document.getElementById('driver-diagnosis-icon');
        const text = document.getElementById('driver-diagnosis-text');
        if (!pill || !text) return;

        const wb = weightageData?.breadth_by_time?.[timeStr];
        const ob = breadthData?.breadth_by_time?.[timeStr];

        const gw = wb ? (parseFloat(wb.green_weight) || 0) : 50;
        const rw = wb ? (parseFloat(wb.red_weight) || 0) : 50;
        const gcp = wb ? (parseFloat(wb.green_count_pct) || 0) : 50;
        const rcp = wb ? (parseFloat(wb.red_count_pct) || 0) : 50;

        const uw = ob ? (parseFloat(ob.up_weight) || 0) : 50;
        const dw = ob ? (parseFloat(ob.down_weight) || 0) : 50;

        let label = 'BALANCED MARKET EQUILIBRIUM';
        let iconChar = '⚖️';
        let badgeClass = 'bg-[#1a1e29] text-gray-400 border-gray-700';
        let tooltip = 'Two-way auction in equilibrium. No single institutional driver dominating.';

        // Scenario 1: Full Institutional Trend (Both Stocks & Options aligned)
        if (gw >= 75 && gcp >= 70 && uw >= 70) {
            label = 'FULL INSTITUTIONAL TREND (BULL)';
            iconChar = '🚀';
            badgeClass = 'bg-emerald-950/90 text-emerald-300 border-emerald-500/60 shadow-[0_0_10px_rgba(16,185,129,0.25)]';
            tooltip = 'High Conviction: Both Cash Equities (Stocks) & Options Flow are in unanimous agreement. Trend continuation expected.';
        } else if (rw >= 75 && rcp >= 70 && dw >= 70) {
            label = 'FULL INSTITUTIONAL TREND (BEAR)';
            iconChar = '🩸';
            badgeClass = 'bg-rose-950/90 text-rose-300 border-rose-500/60 shadow-[0_0_10px_rgba(244,63,94,0.25)]';
            tooltip = 'High Conviction: Heavy cash selling & Bearish Options flow aligned. Downside continuation expected.';
        }
        // Scenario 2: Options-Led Squeeze (Options strongly Bullish >=70%, but Stocks mixed <65%) -> Exact User Case!
        else if (uw >= 70 && gw < 65) {
            label = 'OPTIONS-LED SQUEEZE (HEAVYWEIGHTS LAGGING)';
            iconChar = '⚡';
            badgeClass = 'bg-indigo-950/90 text-indigo-300 border-indigo-400/60 shadow-[0_0_10px_rgba(99,102,241,0.25)] animate-pulse';
            tooltip = 'Institutional Put Writing & Call buying driving index UP while core heavyweights rest. Expect sharp pullbacks; trade with Options tide, do not short into Futures Ask walls.';
        }
        // Scenario 3: Options-Led Downside (Options strongly Bearish >=70%, but Stocks mixed <65%)
        else if (dw >= 70 && rw < 65) {
            label = 'OPTIONS-LED DOWNSIDE (CALL WRITING)';
            iconChar = '⚡';
            badgeClass = 'bg-purple-950/90 text-purple-300 border-purple-400/60 shadow-[0_0_10px_rgba(168,85,247,0.25)] animate-pulse';
            tooltip = 'Heavy Call Writing overhead capping index while cash equities hold steady. Trade with Options flow.';
        }
        // Scenario 4: Cash Equities Heavyweights Driving (Stocks strong >=75%, Options slower <65%)
        else if (gw >= 75 && uw < 65) {
            label = 'CASH HEAVYWEIGHT DRIVEN RALLY';
            iconChar = '🏛️';
            badgeClass = 'bg-cyan-950/90 text-cyan-300 border-cyan-500/60 shadow-xs';
            tooltip = 'Institutions accumulating core heavyweight stocks (Reliance, HDFC Bank, etc.). Options delta will catch up soon.';
        } else if (rw >= 75 && dw < 65) {
            label = 'CASH HEAVYWEIGHT SELLOFF';
            iconChar = '🏛️';
            badgeClass = 'bg-amber-950/90 text-amber-300 border-amber-500/60 shadow-xs';
            tooltip = 'Heavyweight equities dragging index down. Options chain expected to price in downside risk.';
        }

        pill.className = `hidden sm:flex items-center gap-1.5 px-2.5 py-0.5 rounded border text-[10px] font-black tracking-wider transition-all duration-200 cursor-help shrink-0 ${badgeClass}`;
        pill.title = `Market Driver Diagnosis (${timeStr || 'Current'}): ${tooltip}`;
        if (icon) icon.textContent = iconChar;
        if (text) text.textContent = label;
    }

    // ── 11. Event Handlers & Settings Sync ────────────────────────────────────────
    function setupControls() {
        // Height Dropdown
                const wHeightSel = document.getElementById('w-height-select');
        if (wHeightSel) wHeightSel.value = String(currentChart1Height);

        const oHeightSel = document.getElementById('o-height-select');
        if (oHeightSel) oHeightSel.value = String(currentChart2Height);

        const toiHeightSel = document.getElementById('toi-height-select');
        if (toiHeightSel) toiHeightSel.value = String(currentToiHeight);

        document.querySelectorAll('.bld-height-select').forEach(sel => {
            if (sel) sel.value = String(currentBldHeight);
        });
        const bldHeightSel = document.getElementById('bld-height-select');
        if (bldHeightSel) bldHeightSel.value = String(currentBldHeight);

        // Candle Style Dropdown (Regular vs Heikin Ashi)
        const candleStyleSelect = document.getElementById('candle-style-select');
        if (candleStyleSelect) {
            candleStyleSelect.value = (wCandleStyle === oCandleStyle) ? wCandleStyle : 'candle';
            candleStyleSelect.addEventListener('change', (e) => {
                setCandleStyle(e.target.value);
            });
        }

        // Signals Mode Dropdown (Breach / Every 5M / Off)
        const signalsModeSelect = document.getElementById('signals-mode-select');
        if (signalsModeSelect) {
            signalsModeSelect.value = alarmSettings.stocksMarkerMode || 'breach';
            signalsModeSelect.addEventListener('change', (e) => {
                setGlobalSignalsMode(e.target.value);
            });
        }

        // Font Size / Cockpit Scale Dropdown (User Requested)
        const fontScaleSelect = document.getElementById('cockpit-font-scale-select');
        if (fontScaleSelect) {
            fontScaleSelect.value = String(currentCockpitFontScale);
            fontScaleSelect.addEventListener('change', (e) => {
                applyCockpitFontScale(parseInt(e.target.value, 10));
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
            // Immediately show prominent chart loaders with target date
            showChartLoaders(dStr);

            const expSelect = document.getElementById('expiry-select');
            if (expSelect) {
                expSelect.innerHTML = `<option value="${selectedExpiry}" class="bg-[#131722] text-amber-300">Loading Expiries...</option>`;
            }

            try {
                const expRes = await fetch(`/api/trending-oi/expiries?date=${dStr}&mode=history&underlying=NSE_INDEX|Nifty 50`).then(r => r.json());
                if (expRes.expiries && expRes.expiries.length > 0) {
                    // Pick nearest expiry >= dStr
                    let matchedExp = expRes.expiries.find(x => x >= dStr) || expRes.expiries[0];
                    selectedExpiry = matchedExp;

                    if (expSelect) {
                        expSelect.innerHTML = expRes.expiries.map(exp => {
                            let label = exp;
                            try {
                                const parts = exp.split('-');
                                if (parts.length === 3) {
                                    const mNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                                    const mIdx = parseInt(parts[1], 10) - 1;
                                    label = `${parts[2]}-${mNames[mIdx] || parts[1]}`;
                                }
                            } catch(err){}
                            return `<option value="${exp}" ${exp === selectedExpiry ? 'selected' : ''} class="bg-[#131722] text-white">
                                ${label}
                            </option>`;
                        }).join('');
                        expSelect.value = selectedExpiry;
                    }
                }
            } catch (e) {
                console.warn('Could not auto-fetch expiries for date:', e);
            }
            loadAllData(false);
        }

        document.getElementById('btn-prev-day')?.addEventListener('click', () => {
            const curDate = new Date(selectedDate);
            curDate.setDate(curDate.getDate() - 1);
            if (curDate.getDay() === 0) curDate.setDate(curDate.getDate() - 2);
            if (curDate.getDay() === 6) curDate.setDate(curDate.getDate() - 1);
            selectedDate = curDate.toISOString().split('T')[0];
            if (dateInput) {
                if (dateInput._flatpickr) {
                    dateInput._flatpickr.setDate(selectedDate, false);
                } else {
                    dateInput.value = selectedDate;
                }
            }
            updateExpiriesForDate(selectedDate);
        });

        document.getElementById('btn-next-day')?.addEventListener('click', () => {
            const curDate = new Date(selectedDate);
            curDate.setDate(curDate.getDate() + 1);
            if (curDate.getDay() === 6) curDate.setDate(curDate.getDate() + 2);
            if (curDate.getDay() === 0) curDate.setDate(curDate.getDate() + 1);
            selectedDate = curDate.toISOString().split('T')[0];
            if (dateInput) {
                if (dateInput._flatpickr) {
                    dateInput._flatpickr.setDate(selectedDate, false);
                } else {
                    dateInput.value = selectedDate;
                }
            }
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
        document.getElementById('btn-snap-live')?.addEventListener('click', snapToLiveCandle);

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
            } else if (e.key === 'h' || e.key === 'H') {
                e.preventDefault();
                toggleCandleStyle();
            } else if (e.key === 'Escape') {
                dismissAlarm();
            }
        });

        // Auto Refresh Selector & Smart Sync
        const autoRefreshSelect = document.getElementById('auto-refresh-select');
        if (autoRefreshSelect) {
            autoRefreshSelect.value = autoRefreshMode;
            autoRefreshSelect.addEventListener('change', (e) => {
                autoRefreshMode = e.target.value;
                localStorage.setItem('nifty_unified_refresh_mode', autoRefreshMode);
                setupAutoRefresh();
            });
        }

        // Toggle Timer Visibility Button & Badge Click
        document.getElementById('btn-toggle-timer-vis')?.addEventListener('click', toggleTimerVisibility);
        document.getElementById('countdown-badge')?.addEventListener('click', toggleTimerVisibility);

        document.getElementById('btn-reload')?.addEventListener('click', () => loadAllData(false));

        // Zen Mode
        document.getElementById('btn-zen')?.addEventListener('click', () => {
            document.body.classList.toggle('zen-mode');
            setTimeout(() => {
                applyAllSectionHeights();
            if (typeof renderFlowSynergy === 'function') {
                renderFlowSynergy();
            }
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
                const currentTime = (timestampsList && activeCandleIndex >= 0) ? timestampsList[activeCandleIndex] : null;
                renderStrikeBuildupChart(currentTime);
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
        document.getElementById('btn-close-alarm-x')?.addEventListener('click', stopAlarm);

        // Preview Bull / Bear Alarms for Testing
        document.getElementById('btn-preview-bull-alarm')?.addEventListener('click', () => {
            const currentCandle = weightageData?.candles?.[activeCandleIndex] || weightageData?.candles?.slice(-1)[0];
            const spot = currentCandle ? Number(currentCandle.close) : 22962.45;
            const sampleCandle = {
                open: currentCandle?.open || (spot - 10),
                high: currentCandle?.high || (spot + 15),
                low: currentCandle?.low || (spot - 25),
                close: spot,
                time_str: currentCandle?.time_str || '10:50'
            };
            triggerAlarmModal({
                type: 'STOCKS',
                direction: 'BULL',
                title: '📊 BULLISH STOCK BREADTH SURGE',
                score: 84,
                threshold: 80,
                time: sampleCandle.time_str,
                count: 42,
                countPct: 84,
                weight: 92.8,
                pts: 42.15,
                oStat: '20% Bull / 80% Bear',
                sound: alarmSettings.stocksSound || 'bull_horn',
                volume: alarmSettings.volume || 1.0,
                isConfluence: false,
                confluenceState: 'DIVERGENCE',
                confluenceText: 'Stocks Bullish (93%), but Options show 80% Bear Flow (Divergent).',
                candle: sampleCandle
            });
        });

        document.getElementById('btn-preview-bear-alarm')?.addEventListener('click', () => {
            const currentCandle = weightageData?.candles?.[activeCandleIndex] || weightageData?.candles?.slice(-1)[0];
            const spot = currentCandle ? Number(currentCandle.close) : 22928.10;
            const sampleCandle = {
                open: currentCandle?.open || (spot + 12),
                high: currentCandle?.high || (spot + 22),
                low: currentCandle?.low || (spot - 18),
                close: spot,
                time_str: currentCandle?.time_str || '10:45'
            };
            triggerAlarmModal({
                type: 'CONFLUENCE',
                direction: 'BEAR',
                title: '🔥 BEARISH CONFLUENCE DETECTED',
                score: 88,
                threshold: 75,
                time: sampleCandle.time_str,
                count: 44,
                countPct: 88,
                weight: 90.1,
                pts: -27.55,
                oStat: '88% Bear Flow',
                sound: alarmSettings.confluenceSound || 'bear_drop',
                volume: alarmSettings.volume || 1.0,
                isConfluence: true,
                confluenceState: 'CONFLUENCE',
                confluenceText: 'Stocks (90% Red) and Options (88% Put Flow) in 100% Agreement.',
                candle: sampleCandle
            });
        });

        // Dynamic label switch when SL mode changes
        document.getElementById('zc-sl-mode-select')?.addEventListener('change', (e) => {
            const mode = e.target.value;
            const label = document.getElementById('zc-sl-label');
            const input = document.getElementById('zc-sl-pts-input');
            if (mode === 'candle_hl') {
                if (label) label.textContent = 'SL Buffer (Pts)';
                if (input && (parseInt(input.value, 10) > 50 || !input.value)) input.value = 15;
            } else {
                if (label) label.textContent = 'Fixed SL (Pts)';
                if (input && parseInt(input.value, 10) < 50) input.value = 100;
            }
        });

        // Manual Dispatch Button in Active Alarm Modal
        document.getElementById('btn-dispatch-active-alarm')?.addEventListener('click', () => {
            const btn = document.getElementById('btn-dispatch-active-alarm');
            if (currentActiveLogId) {
                dispatchLogToZeroClick(currentActiveLogId, btn);
            } else {
                // If not logged yet, log & dispatch immediately
                const currentCandle = weightageData?.candles?.[activeCandleIndex] || weightageData?.candles?.slice(-1)[0];
                const spot = currentCandle ? currentCandle.close : 22928.10;
                const dir = document.getElementById('alarm-trade-strike-badge')?.textContent.includes('PE') ? 'UP' : 'DOWN';
                const contract = calculateOptionSellContract(dir, spot, currentCandle);
                logAlertInternally({
                    direction: dir,
                    time: currentCandle ? currentCandle.time_str : '12:00',
                    score: 80,
                    weight: 80,
                    count: 40,
                    pts: 0,
                    candle: currentCandle
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
            alarmSettings.triggerOnClose = document.getElementById('alarm-trigger-on-close-toggle')?.checked !== false;

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
            const prevBasketMode = alarmSettings.optionsBasketMode || 'fixed';
            alarmSettings.optionsBasketMode = document.getElementById('cfg-options-basket-mode')?.value || 'fixed';
            const basketModeChanged = (prevBasketMode !== alarmSettings.optionsBasketMode);
            if (typeof syncStrikeBasketModeUI === 'function') syncStrikeBasketModeUI();

            // 3. Confluence settings
            alarmSettings.confluenceEnabled = document.getElementById('cfg-confluence-enabled').checked;
            alarmSettings.confluenceSound = document.getElementById('confluence-sound-select').value;
            const haToggleS = document.getElementById('stocks-ha-wick-filter-toggle');
            if (haToggleS) alarmSettings.stocksHaWickFilter = haToggleS.checked;
            const haToggleO = document.getElementById('options-ha-wick-filter-toggle');
            if (haToggleO) alarmSettings.optionsHaWickFilter = haToggleO.checked;

            const c1StkToggle = document.getElementById('modal-c1-show-stocks-toggle');
            if (c1StkToggle) alarmSettings.chart1ShowStocks = c1StkToggle.checked;
            const c1OptToggle = document.getElementById('modal-c1-show-options-toggle');
            if (c1OptToggle) alarmSettings.chart1ShowOptions = c1OptToggle.checked;

            const c2StkToggle = document.getElementById('modal-c2-show-stocks-toggle');
            if (c2StkToggle) alarmSettings.chart2ShowStocks = c2StkToggle.checked;
            const c2OptToggle = document.getElementById('modal-c2-show-options-toggle');
            if (c2OptToggle) alarmSettings.chart2ShowOptions = c2OptToggle.checked;

            // 4. Quant Edge Filters (5 Toggleable Boosters)
            const fHaSlInput = document.getElementById('cfg-filter-ha-adaptive-sl');
            if (fHaSlInput) alarmSettings.filterHaAdaptiveSl = fHaSlInput.checked;
            const fBVelInput = document.getElementById('cfg-filter-breadth-velocity');
            if (fBVelInput) alarmSettings.filterBreadthVelocity = fBVelInput.checked;
            const fHtfInput = document.getElementById('cfg-filter-htf-alignment');
            if (fHtfInput) alarmSettings.filterHtfAlignment = fHtfInput.checked;
            const fSrInput = document.getElementById('cfg-filter-sr-proximity');
            if (fSrInput) alarmSettings.filterSrProximity = fSrInput.checked;
            const fOiInput = document.getElementById('cfg-filter-oi-delta-confirmation');
            if (fOiInput) alarmSettings.filterOiDeltaConfirmation = fOiInput.checked;

            // 5. Order Flow S/R Settings
            const ofYdToggle = document.getElementById('cfg-of-show-yesterday');
            if (ofYdToggle) alarmSettings.ofShowYesterday = ofYdToggle.checked;
            const ofTdToggle = document.getElementById('cfg-of-show-today');
            if (ofTdToggle) alarmSettings.ofShowToday = ofTdToggle.checked;
            const ofLvlInput = document.getElementById('cfg-of-level-count');
            if (ofLvlInput) {
                const v = parseInt(ofLvlInput.value, 10);
                alarmSettings.ofLevelCount = (!isNaN(v) && v >= 1 && v <= 5) ? v : 1;
            }
            const ofWidthInput = document.getElementById('cfg-of-line-width');
            if (ofWidthInput) {
                const w = parseInt(ofWidthInput.value, 10);
                alarmSettings.ofLineWidth = (!isNaN(w) && w >= 1 && w <= 4) ? w : 3;
            }
            const ofStyleYd = document.getElementById('cfg-of-line-style-yesterday');
            if (ofStyleYd) alarmSettings.ofLineStyleYesterday = ofStyleYd.value;
            const ofStyleTd = document.getElementById('cfg-of-line-style-today');
            if (ofStyleTd) alarmSettings.ofLineStyleToday = ofStyleTd.value;
            const ofRankBy = document.getElementById('cfg-of-rank-by');
            if (ofRankBy) alarmSettings.ofRankBy = ofRankBy.value;
            const ofCalib = document.getElementById('cfg-of-calibration');
            if (ofCalib) {
                alarmSettings.ofCalibration = ofCalib.value;
                setOfCalibration(ofCalib.value);
            }

            fetchOrderFlowLevels();

            if (typeof updateChartSignalButtonsUI === 'function') updateChartSignalButtonsUI();

            // Save 0Click settings
            zeroClickSettings.autoLog = document.getElementById('zc-auto-log-toggle').checked;
            zeroClickSettings.forwardToZeroClick = document.getElementById('zc-forward-toggle').checked;
            zeroClickSettings.webhookUrl = document.getElementById('zc-webhook-url').value.trim() || 'https://0click.in/api/webhook/tradingview';
            zeroClickSettings.webhookToken = document.getElementById('zc-webhook-token').value.trim();
            zeroClickSettings.mode = document.getElementById('zc-mode-select').value;
            zeroClickSettings.lots = parseInt(document.getElementById('zc-lots-input').value, 10) || 1;
            zeroClickSettings.slMode = document.getElementById('zc-sl-mode-select')?.value || 'candle_hl';
            const slInputVal = parseInt(document.getElementById('zc-sl-pts-input')?.value, 10);
            if (zeroClickSettings.slMode === 'candle_hl') {
                zeroClickSettings.slBuffer = !isNaN(slInputVal) ? slInputVal : 15;
            } else {
                zeroClickSettings.slOffset = !isNaN(slInputVal) ? slInputVal : 100;
            }
            let tp1Input = parseInt(document.getElementById('zc-tp-pts-input')?.value, 10) || 100;
            let tp2Input = parseInt(document.getElementById('zc-tp2-pts-input')?.value, 10) || 150;
            if (tp1Input > tp2Input) {
                const tmp = tp1Input;
                tp1Input = tp2Input;
                tp2Input = tmp;
            }
            zeroClickSettings.tpOffset = tp1Input;
            zeroClickSettings.tp2Offset = tp2Input;

            saveAlarmSettings();
            syncAlarmToolbarUI();
            alarmModal?.classList.add('hidden');

            if (basketModeChanged) {
                loadAllData();
            } else {
                // Immediately re-render markers on both charts with newly saved criteria!
                renderWeightageColumn();
                renderOptionsBreadthColumn();
            }
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

        
        const haToggleS = document.getElementById('stocks-ha-wick-filter-toggle');
        if (haToggleS) haToggleS.checked = (alarmSettings.stocksHaWickFilter !== false);
        const haToggleO = document.getElementById('options-ha-wick-filter-toggle');
        if (haToggleO) haToggleO.checked = (alarmSettings.optionsHaWickFilter !== false);

        const mC1Stk = document.getElementById('modal-c1-show-stocks-toggle');
        if (mC1Stk) mC1Stk.checked = (alarmSettings.chart1ShowStocks !== false);
        const mC1Opt = document.getElementById('modal-c1-show-options-toggle');
        if (mC1Opt) mC1Opt.checked = (alarmSettings.chart1ShowOptions === true);

        const mC2Stk = document.getElementById('modal-c2-show-stocks-toggle');
        if (mC2Stk) mC2Stk.checked = (alarmSettings.chart2ShowStocks === true);
        const mC2Opt = document.getElementById('modal-c2-show-options-toggle');
        if (mC2Opt) mC2Opt.checked = (alarmSettings.chart2ShowOptions !== false);
        const trigCloseToggle = document.getElementById('alarm-trigger-on-close-toggle');
        if (trigCloseToggle) trigCloseToggle.checked = alarmSettings.triggerOnClose !== false;

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

        const oBasketSelect = document.getElementById('cfg-options-basket-mode');
        if (oBasketSelect) oBasketSelect.value = alarmSettings.optionsBasketMode || 'fixed';
        const oBasketPill = document.getElementById('cfg-basket-mode-pill');
        if (oBasketPill) {
            const isDyn = (alarmSettings.optionsBasketMode === 'dynamic');
            oBasketPill.textContent = isDyn ? 'Dynamic Floating' : 'Fixed Morning';
            oBasketPill.className = isDyn
                ? 'text-[9px] px-1.5 py-0.2 rounded font-mono bg-cyan-950 text-cyan-300 border border-cyan-500/40'
                : 'text-[9px] px-1.5 py-0.2 rounded font-mono bg-gray-800 text-gray-300 border border-gray-600';
        }
        if (typeof syncStrikeBasketModeUI === 'function') syncStrikeBasketModeUI();

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

        const zcSlMode = document.getElementById('zc-sl-mode-select');
        if (zcSlMode) zcSlMode.value = zeroClickSettings.slMode || 'candle_hl';

        const zcSlLabel = document.getElementById('zc-sl-label');
        if (zcSlLabel) {
            zcSlLabel.textContent = (zeroClickSettings.slMode === 'fixed_offset') ? 'Fixed SL (Pts)' : 'SL Buffer (Pts)';
        }

        const zcSl = document.getElementById('zc-sl-pts-input');
        if (zcSl) {
            zcSl.value = (zeroClickSettings.slMode === 'fixed_offset')
                ? (zeroClickSettings.slOffset || 100)
                : (zeroClickSettings.slBuffer !== undefined ? zeroClickSettings.slBuffer : 15);
        }

        const zcTp = document.getElementById('zc-tp-pts-input');
        if (zcTp) zcTp.value = zeroClickSettings.tpOffset !== undefined ? zeroClickSettings.tpOffset : 100;

        const zcTp2 = document.getElementById('zc-tp2-pts-input');
        if (zcTp2) zcTp2.value = zeroClickSettings.tp2Offset !== undefined ? zeroClickSettings.tp2Offset : 150;

        // 5 Quant Edge Filters
        const fHaSl = document.getElementById('cfg-filter-ha-adaptive-sl');
        if (fHaSl) fHaSl.checked = (alarmSettings.filterHaAdaptiveSl !== false);
        const fBVel = document.getElementById('cfg-filter-breadth-velocity');
        if (fBVel) fBVel.checked = (alarmSettings.filterBreadthVelocity !== false);
        const fHtf = document.getElementById('cfg-filter-htf-alignment');
        if (fHtf) fHtf.checked = (alarmSettings.filterHtfAlignment !== false);
        const fSr = document.getElementById('cfg-filter-sr-proximity');
        if (fSr) fSr.checked = (alarmSettings.filterSrProximity !== false);
        const fOi = document.getElementById('cfg-filter-oi-delta-confirmation');
        if (fOi) fOi.checked = (alarmSettings.filterOiDeltaConfirmation !== false);

        // Order Flow S/R Settings
        const ofYd = document.getElementById('cfg-of-show-yesterday');
        if (ofYd) ofYd.checked = (alarmSettings.ofShowYesterday !== false);
        const ofTd = document.getElementById('cfg-of-show-today');
        if (ofTd) ofTd.checked = (alarmSettings.ofShowToday !== false);
        const ofLvl = document.getElementById('cfg-of-level-count');
        if (ofLvl) ofLvl.value = alarmSettings.ofLevelCount || 1;
        const ofWidth = document.getElementById('cfg-of-line-width');
        if (ofWidth) ofWidth.value = alarmSettings.ofLineWidth || 3;
        const ofStyleYd = document.getElementById('cfg-of-line-style-yesterday');
        if (ofStyleYd) ofStyleYd.value = alarmSettings.ofLineStyleYesterday || 'solid';
        const ofStyleTd = document.getElementById('cfg-of-line-style-today');
        if (ofStyleTd) ofStyleTd.value = alarmSettings.ofLineStyleToday || 'dashed';
        const ofRankBy = document.getElementById('cfg-of-rank-by');
        if (ofRankBy) ofRankBy.value = alarmSettings.ofRankBy || 'cumulative';
        const ofCalib = document.getElementById('cfg-of-calibration');
        if (ofCalib) ofCalib.value = alarmSettings.ofCalibration || ofCalibrationMode || 'spot';
        updateOfCalibrationUI();
        renderOfLevelsPreview();
    }

    function saveAlarmSettings() {
        localStorage.setItem('nifty_unified_alarm_settings', JSON.stringify(alarmSettings));
        localStorage.setItem('nifty_zeroclick_settings', JSON.stringify(zeroClickSettings));
        updateOverallAndTriggerStats(activeCandleIndex);
        updateMarkerButtonsUI();
        updateTvToolsUI();
    }

    function syncAlarmToolbarUI() {
        if (typeof syncStrikeBasketModeUI === 'function') syncStrikeBasketModeUI();
        const label = document.getElementById('alarm-toolbar-label');
        const icon = document.getElementById('alarm-bell-icon');
        const btn = document.getElementById('btn-quick-alarm');
        if (!label) return;

        if (alarmSettings.masterEnabled) {
            const sTh = alarmSettings.stocksUpThreshold || 80;
            const oTh = alarmSettings.optionsUpThreshold || 70;
            label.innerHTML = `<span class="hidden xl:inline">Alarm </span><span>${sTh}%/${oTh}%</span>`;
            label.className = 'text-amber-400 font-bold text-[10.5px]';
            if (icon) icon.textContent = '🔔';
            if (btn) btn.title = `Alarms Active (Stocks: ${sTh}%, Options: ${oTh}%) • Click to Mute`;
        } else {
            label.textContent = 'Muted';
            label.className = 'text-gray-500 font-semibold text-[10.5px]';
            if (icon) icon.textContent = '🔕';
            if (btn) btn.title = 'Alarms Muted • Click to Enable';
        }
    }

    // ── 12. Smart Candle Sync & Auto-Refresh Engine ──────────────────────────────
    function setupAutoRefresh() {
        if (autoRefreshTimerId) clearInterval(autoRefreshTimerId);

        const mode = autoRefreshMode;
        if (mode === '0') {
            updateCountdownUI('OFF', 'Auto-Refresh is OFF (Manual refresh only)');
            return;
        }

        const isFixed = !isNaN(parseInt(mode, 10)) && mode !== 'candle_5m' && mode !== 'stock_1m';
        let fixedRemaining = isFixed ? (parseInt(mode, 10) || 60) : 0;
        let lastCandleSyncBucket = -1;
        let lastStockSyncBucket = -1;

        const tick = () => {
            const now = new Date();
            const currentSeconds = now.getSeconds();
            const currentMinutes = now.getMinutes();

            let remaining = 0;
            let tooltip = '';

            if (mode === 'candle_5m') {
                const tfM = (typeof currentTfMinutes !== 'undefined' && currentTfMinutes > 0 && currentTfMinutes < 375) ? currentTfMinutes : 5;
                const bufferSec = 8; // 8s backend buffer for NSE fetch & DB write
                const minuteInTf = currentMinutes % tfM;
                const secondsIntoTf = minuteInTf * 60 + currentSeconds;
                remaining = bufferSec - secondsIntoTf;
                if (remaining <= 0) remaining += (tfM * 60);

                const targetDate = new Date(now.getTime() + remaining * 1000);
                const targetTimeStr = targetDate.toTimeString().split(' ')[0];
                tooltip = `Next ${tfM}m Candle Sync: ${targetTimeStr} (${tfM}m close + 8s buffer)`;

                const curBucketIdx = Math.floor((currentMinutes * 60 + currentSeconds - bufferSec) / (tfM * 60));
                if (lastCandleSyncBucket !== -1 && curBucketIdx !== lastCandleSyncBucket) {
                    lastCandleSyncBucket = curBucketIdx;
                    loadAllData(true);
                } else if (lastCandleSyncBucket === -1) {
                    lastCandleSyncBucket = curBucketIdx;
                }
            } else if (mode === 'stock_1m') {
                const bufferSec = 5; // 5s backend buffer
                remaining = bufferSec - currentSeconds;
                if (remaining <= 0) remaining += 60;

                const targetDate = new Date(now.getTime() + remaining * 1000);
                const targetTimeStr = targetDate.toTimeString().split(' ')[0];
                tooltip = `Next 1m Stock Sync: ${targetTimeStr} (1m close + 5s buffer)`;

                const curMinIdx = Math.floor((Math.floor(now.getTime() / 1000) - bufferSec) / 60);
                if (lastStockSyncBucket !== -1 && curMinIdx !== lastStockSyncBucket) {
                    lastStockSyncBucket = curMinIdx;
                    loadAllData(true);
                } else if (lastStockSyncBucket === -1) {
                    lastStockSyncBucket = curMinIdx;
                }
            } else {
                // Fixed seconds mode
                fixedRemaining--;
                if (fixedRemaining <= 0) {
                    fixedRemaining = parseInt(mode, 10) || 60;
                    loadAllData(true);
                }
                remaining = fixedRemaining;
                tooltip = `Fixed ${mode}s Polling Interval`;
            }

            const m = Math.floor(remaining / 60);
            const s = remaining % 60;
            updateCountdownUI(`${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`, tooltip);
        };

        tick();
        autoRefreshTimerId = setInterval(tick, 1000);
    }

    function updateCountdownUI(text, title = '') {
        const badge = document.getElementById('countdown-badge');
        if (!badge) return;
        badge.textContent = text;
        if (title) badge.title = `${title} • Click to Hide/Show Timer`;
    }

    function toggleTimerVisibility() {
        isTimerVisible = !isTimerVisible;
        localStorage.setItem('nifty_unified_timer_visible', isTimerVisible ? 'true' : 'false');
        applyTimerVisibility();
    }

    function applyTimerVisibility() {
        const badge = document.getElementById('countdown-badge');
        const btn = document.getElementById('btn-toggle-timer-vis');
        if (badge) {
            if (isTimerVisible) {
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
        if (btn) {
            btn.innerHTML = isTimerVisible ? '👁️' : '🕶️';
            btn.title = isTimerVisible ? 'Hide Countdown Timer' : 'Show Countdown Timer';
        }
    }

    // ── Active Candle Close Countdown Timer (Per-Chart & Dual Summary) ──
    let candleCloseTimerId = null;

    function calcTfRemainingSecs(tfKey, currentSecsOfDay, marketOpenSecs, marketCloseSecs) {
        const tfMinutes = (typeof getTfMinutes === 'function') ? getTfMinutes(tfKey) : (TF_MINUTES_MAP[tfKey] || 5);
        if (tfMinutes >= 375 || tfKey === '1D') {
            return Math.max(0, marketCloseSecs - currentSecsOfDay);
        }
        const durationSecs = tfMinutes * 60;
        const elapsedSinceOpen = currentSecsOfDay - marketOpenSecs;
        const lastCandleBoundary = 15 * 3600 + 15 * 60; // 15:15:00
        if ((tfMinutes === 30 || tfMinutes === 60) && currentSecsOfDay >= lastCandleBoundary) {
            return Math.max(0, marketCloseSecs - currentSecsOfDay);
        }
        let rem = durationSecs - (elapsedSinceOpen % durationSecs);
        if (rem === 0) rem = durationSecs;
        return rem;
    }

    function formatCountdownTime(remSecs) {
        if (remSecs == null || remSecs < 0) return '--:--';
        const m = Math.floor(remSecs / 60);
        const s = remSecs % 60;
        if (m >= 60) {
            const h = Math.floor(m / 60);
            const remM = m % 60;
            return `${String(h).padStart(2, '0')}:${String(remM).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        }
        return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    }

    function updateCandleCloseCountdown() {
        // Elements for Top Toolbar dual badge
        const countdown1El = document.getElementById('candle-timer-countdown');
        const label1El = document.getElementById('candle-timer-tf-label');
        const countdown2El = document.getElementById('candle-timer-countdown2');
        const label2El = document.getElementById('candle-timer-tf2-label');

        // Elements for Chart 1 Header Badge
        const wCountdownEl = document.getElementById('w-candle-timer-countdown');
        const wLabelEl = document.getElementById('w-candle-timer-label');

        // Elements for Chart 2 Header Badge
        const oCountdownEl = document.getElementById('o-candle-timer-countdown');
        const oLabelEl = document.getElementById('o-candle-timer-label');

        const tf1Key = (typeof wTimeframe !== 'undefined' && wTimeframe) ? wTimeframe : (typeof currentUnifiedTimeframe !== 'undefined' && currentUnifiedTimeframe ? currentUnifiedTimeframe : '5m');
        const tf2Key = (typeof oTimeframe !== 'undefined' && oTimeframe) ? oTimeframe : (typeof currentUnifiedTimeframe !== 'undefined' && currentUnifiedTimeframe ? currentUnifiedTimeframe : '15m');

        // Update labels
        const tf1LabelStr = (tf1Key === '1D') ? '1D' : tf1Key.toUpperCase();
        const tf2LabelStr = (tf2Key === '1D') ? '1D' : tf2Key.toUpperCase();

        if (label1El) label1El.textContent = `C1 (${tf1LabelStr}):`;
        if (label2El) label2El.textContent = `C2 (${tf2LabelStr}):`;
        if (wLabelEl) wLabelEl.textContent = `${tf1LabelStr}:`;
        if (oLabelEl) oLabelEl.textContent = `${tf2LabelStr}:`;

        const now = new Date();
        const istParts = new Intl.DateTimeFormat('en-CA', {
            timeZone: 'Asia/Kolkata',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        }).formatToParts(now);

        const getPart = (type) => istParts.find(p => p.type === type)?.value;
        const istYear = getPart('year');
        const istMonth = getPart('month');
        const istDay = getPart('day');
        const todayIstStr = `${istYear}-${istMonth}-${istDay}`;

        const hours = (parseInt(getPart('hour'), 10) || 0) % 24;
        const minutes = parseInt(getPart('minute'), 10) || 0;
        const seconds = parseInt(getPart('second'), 10) || 0;
        const currentSecsOfDay = hours * 3600 + minutes * 60 + seconds;

        const marketOpenSecs = 9 * 3600 + 15 * 60; // 09:15:00 = 33300
        const marketCloseSecs = 15 * 3600 + 30 * 60; // 15:30:00 = 55800

        // If viewing a historical date other than today:
        if (typeof selectedDate !== 'undefined' && selectedDate && selectedDate !== todayIstStr) {
            const closedText = 'CLOSED';
            const closedClass = 'font-mono font-black text-slate-400 text-xs sm:text-sm tracking-wider leading-none select-none';
            if (countdown1El) { countdown1El.textContent = closedText; countdown1El.className = closedClass; }
            if (countdown2El) { countdown2El.textContent = closedText; countdown2El.className = closedClass; }
            if (wCountdownEl) { wCountdownEl.textContent = closedText; wCountdownEl.className = 'font-mono font-black text-slate-400 text-xs tracking-wider leading-none select-none'; }
            if (oCountdownEl) { oCountdownEl.textContent = closedText; oCountdownEl.className = 'font-mono font-black text-slate-400 text-xs tracking-wider leading-none select-none'; }
            if (countdown1El?.parentElement?.parentElement) {
                countdown1El.parentElement.parentElement.title = `Viewing historical session (${selectedDate}). Market closed.`;
            }
            return;
        }

        // Pre-market (before 09:15:00 IST)
        if (currentSecsOfDay < marketOpenSecs) {
            const remPre = marketOpenSecs - currentSecsOfDay;
            const text = formatCountdownTime(remPre);
            const preClass = 'font-mono font-black text-sky-300 text-xs sm:text-sm tracking-wider leading-none select-none';
            if (countdown1El) { countdown1El.textContent = text; countdown1El.className = preClass; }
            if (countdown2El) { countdown2El.textContent = text; countdown2El.className = preClass; }
            if (wCountdownEl) { wCountdownEl.textContent = text; wCountdownEl.className = 'font-mono font-black text-sky-300 text-xs leading-none select-none'; }
            if (oCountdownEl) { oCountdownEl.textContent = text; oCountdownEl.className = 'font-mono font-black text-purple-300 text-xs leading-none select-none'; }
            if (countdown1El?.parentElement?.parentElement) {
                countdown1El.parentElement.parentElement.title = `Pre-market: Session opens at 09:15:00 IST (${text} remaining)`;
            }
            return;
        }

        // Post-market (after 15:30:00 IST)
        if (currentSecsOfDay >= marketCloseSecs) {
            const closedText = 'CLOSED';
            const closedClass = 'font-mono font-black text-slate-400 text-xs sm:text-sm tracking-wider leading-none select-none';
            if (countdown1El) { countdown1El.textContent = closedText; countdown1El.className = closedClass; }
            if (countdown2El) { countdown2El.textContent = closedText; countdown2El.className = closedClass; }
            if (wCountdownEl) { wCountdownEl.textContent = closedText; wCountdownEl.className = 'font-mono font-black text-slate-400 text-xs tracking-wider leading-none select-none'; }
            if (oCountdownEl) { oCountdownEl.textContent = closedText; oCountdownEl.className = 'font-mono font-black text-slate-400 text-xs tracking-wider leading-none select-none'; }
            if (countdown1El?.parentElement?.parentElement) {
                countdown1El.parentElement.parentElement.title = `Market session ended at 15:30:00 IST.`;
            }
            return;
        }

        // Live Market Hours (09:15 to 15:30)
        const rem1 = calcTfRemainingSecs(tf1Key, currentSecsOfDay, marketOpenSecs, marketCloseSecs);
        const rem2 = calcTfRemainingSecs(tf2Key, currentSecsOfDay, marketOpenSecs, marketCloseSecs);

        const text1 = formatCountdownTime(rem1);
        const text2 = formatCountdownTime(rem2);

        // Chart 1 (Top) styling
        const isUrgent1 = rem1 <= 30 && rem1 > 0;
        if (countdown1El) {
            countdown1El.textContent = text1;
            countdown1El.className = isUrgent1 
                ? 'font-mono font-black text-rose-400 text-xs sm:text-sm tracking-wider leading-none select-none drop-shadow animate-pulse'
                : 'font-mono font-black text-sky-200 text-xs sm:text-sm tracking-wider leading-none select-none drop-shadow';
        }
        if (wCountdownEl) {
            wCountdownEl.textContent = text1;
            wCountdownEl.className = isUrgent1
                ? 'font-mono font-black text-rose-400 text-xs tracking-wider leading-none select-none animate-pulse'
                : 'font-mono font-black text-sky-200 text-xs tracking-wider leading-none select-none';
            if (wCountdownEl.parentElement) {
                wCountdownEl.parentElement.title = `Top Chart (${tf1LabelStr}) candle closes in ${text1} (Wall-clock IST)`;
            }
        }

        // Chart 2 (Bottom) styling
        const isUrgent2 = rem2 <= 30 && rem2 > 0;
        if (countdown2El) {
            countdown2El.textContent = text2;
            countdown2El.className = isUrgent2
                ? 'font-mono font-black text-rose-400 text-xs sm:text-sm tracking-wider leading-none select-none drop-shadow animate-pulse'
                : 'font-mono font-black text-purple-200 text-xs sm:text-sm tracking-wider leading-none select-none drop-shadow';
        }
        if (oCountdownEl) {
            oCountdownEl.textContent = text2;
            oCountdownEl.className = isUrgent2
                ? 'font-mono font-black text-rose-400 text-xs tracking-wider leading-none select-none animate-pulse'
                : 'font-mono font-black text-purple-200 text-xs tracking-wider leading-none select-none';
            if (oCountdownEl.parentElement) {
                oCountdownEl.parentElement.title = `Bottom Chart (${tf2LabelStr}) candle closes in ${text2} (Wall-clock IST)`;
            }
        }

        if (countdown1El?.parentElement?.parentElement) {
            countdown1El.parentElement.parentElement.title = `Active candle remaining: Top Chart (${tf1LabelStr}) ${text1} · Bottom Chart (${tf2LabelStr}) ${text2}`;
        }
    }

    function setupCandleCloseCountdown() {
        if (candleCloseTimerId) clearInterval(candleCloseTimerId);
        updateCandleCloseCountdown();
        candleCloseTimerId = setInterval(updateCandleCloseCountdown, 1000);
    }

    // ── Dedicated 5-Minute OI Buildup Sync Engine ──────────────────────────
    // Ensures OI Buildup updates strictly every 5 minutes based on the last completed 5m candle
    let oiBuildupSyncTimerId = null;
    let lastOiBuildupSyncMin = -1;
    function setupOiBuildup5mSync() {
        if (oiBuildupSyncTimerId) clearInterval(oiBuildupSyncTimerId);
        oiBuildupSyncTimerId = setInterval(() => {
            const now = new Date();
            const todayStr = "{{ \Illuminate\Support\Carbon::now('Asia/Kolkata')->toDateString() }}";
            if (typeof selectedDate !== 'undefined' && selectedDate !== todayStr) return;
            if (!isLiveMarketOpen()) return;

            const curMin = now.getMinutes();
            const curSec = now.getSeconds();

            // 8-20 seconds after every 5m boundary (e.g. at :00:08, :05:08, :10:08, etc.)
            if (curMin % 5 === 0 && curSec >= 8 && curSec <= 20 && lastOiBuildupSyncMin !== curMin) {
                lastOiBuildupSyncMin = curMin;
                console.log(`[OI Buildup] 5-minute candle closed at ${curMin}m. Refreshing completed 5m candle buildup...`);
                reloadTrendingOiWithInterval(5);
            }
        }, 3000);
    }

    // ══════════════════════════════════════════════════════════════════════════════
    // TRUE BAR REPLAY & STEP-BY-STEP BACKTEST ENGINE (v2)
    // ══════════════════════════════════════════════════════════════════════════════
    let isBarReplayMode = false;

    function toggleBarReplayMode(forceState = null) {
        if (typeof forceState === 'boolean') {
            isBarReplayMode = forceState;
        } else {
            isBarReplayMode = !isBarReplayMode;
        }
        updateBarReplayUI();

        if (isBarReplayMode) {
            // Also turn on Active Candle Beacon highlight for maximum visual clarity
            if (typeof toggleActiveCandleHighlight === 'function') {
                toggleActiveCandleHighlight(true);
            }
            if (timestampsList && timestampsList.length > 0 && activeCandleIndex >= 0) {
                applyBarReplaySlice(activeCandleIndex, timestampsList[activeCandleIndex]);
                runHighProbabilityTradeEngine(activeCandleIndex, timestampsList[activeCandleIndex]);
            }
        } else {
            restoreFullDayCandles();
            if (timestampsList && timestampsList.length > 0 && activeCandleIndex >= 0) {
                runHighProbabilityTradeEngine(timestampsList.length - 1, timestampsList[timestampsList.length - 1]);
            }
        }
    }
    window.toggleBarReplayMode = toggleBarReplayMode;

    function updateBarReplayUI() {
        const btn = document.getElementById('btn-toggle-bar-replay');
        const badge = document.getElementById('bar-replay-badge');
        if (!btn || !badge) return;

        if (isBarReplayMode) {
            btn.className = 'px-1.5 py-0.2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold rounded text-[9.5px] flex items-center gap-1 transition cursor-pointer shadow-[0_0_10px_rgba(16,185,129,0.5)] border border-emerald-400';
            badge.textContent = 'ON';
            badge.className = 'font-mono text-emerald-200 font-extrabold';
        } else {
            btn.className = 'px-1.5 py-0.2 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white font-bold rounded text-[9.5px] flex items-center gap-1 transition cursor-pointer border border-slate-700';
            badge.textContent = 'OFF';
            badge.className = 'font-mono text-gray-400 font-bold';
        }
    }

    function maintainReplayTimeScale(chart, activeIdx, tf, yesterdayCandles = []) {
        if (!chart) return;
        const timeScale = chart.timeScale();
        const yCount = Array.isArray(yesterdayCandles) ? yesterdayCandles.length : 0;
        const tfBarsMap = { '1m': 375, '3m': 125, '5m': 75, '15m': 25, '30m': 13, '60m': 7, '1h': 7, '1D': 1 };
        const sessionBars = tfBarsMap[tf] || 75;
        const leftOffsetBars = 10;
        const defaultFrom = (yCount > 0 ? yCount : 0) - leftOffsetBars;
        const defaultTo = defaultFrom + sessionBars + leftOffsetBars + 5;

        let targetFrom = defaultFrom;
        let targetTo = defaultTo;

        const curRange = timeScale.getVisibleLogicalRange();
        if (curRange) {
            const span = curRange.to - curRange.from;
            // Only preserve user range if it's a realistic trading chart span (between 20 and 500 bars)
            if (span >= 20 && span <= 500) {
                targetFrom = curRange.from;
                targetTo = curRange.to;

                // If active candle moves past right boundary, scroll view smoothly forward
                const candleLogicalIndex = yCount + Math.max(0, activeIdx);
                if (candleLogicalIndex > targetTo - 5) {
                    const shift = candleLogicalIndex - (targetTo - 5);
                    targetFrom += shift;
                    targetTo += shift;
                }
            }
        }

        const applyTargetRange = () => {
            try {
                timeScale.applyOptions({
                    minBarSpacing: 3,
                    fixLeftEdge: false,
                    fixRightEdge: false,
                });
                timeScale.setVisibleLogicalRange({
                    from: targetFrom,
                    to: targetTo,
                });
                updateDaySeparators();
            } catch (e) {}
        };

        applyTargetRange();
        requestAnimationFrame(applyTargetRange);
    }

    function applyBarReplaySlice(index, timeStr = null) {
        if (!timestampsList || index < 0 || index >= timestampsList.length) return;
        const curTimeStr = timeStr || timestampsList[index];

        // 1. Chart 1 (Stocks / Weightage) Slicing
        if (wCandleSeries && window._unifiedTodayCandles && window._unifiedTodayCandles.length > 0) {
            const visibleToday = window._unifiedTodayCandles.slice(0, index + 1);
            const visibleAll = [...(window._unifiedYesterdayCandles || []), ...visibleToday];
            const displayCandles = (wCandleStyle === 'heikin_ashi') ? calculateHeikinAshi(visibleAll) : visibleAll;
            try {
                wCandleSeries.setData(displayCandles);
            } catch (e) {
                console.warn('wCandleSeries setData slice error:', e);
            }

            // Slice markers: only show markers on or before the current visible candle timestamp
            if (window._wCandleMarkers && window._wCandleMarkers.length > 0) {
                const curTimestamp = (visibleToday.length > 0) ? visibleToday[visibleToday.length - 1].time : 0;
                const visibleMarkers = window._wCandleMarkers.filter(m => Number(m.time) <= curTimestamp);
                try {
                    wCandleSeries.setMarkers(visibleMarkers);
                } catch (e) {}
            }

            // Lock time scale to natural session width so candles never stretch or zoom out of control
            if (wChart) {
                maintainReplayTimeScale(wChart, index, wTimeframe, window._unifiedYesterdayCandles);
            }
        }

        // 2. Chart 2 (Options Breadth) Slicing
        if (oCandleSeries && window._unifiedOptionsTodayCandles && window._unifiedOptionsTodayCandles.length > 0) {
            const rawOptCandles = window._unifiedOptionsTodayCandles;
            let optSliceCount = 0;
            for (let i = 0; i < rawOptCandles.length; i++) {
                if (rawOptCandles[i].time_str <= curTimeStr) {
                    optSliceCount = i + 1;
                } else {
                    break;
                }
            }
            if (optSliceCount > 0) {
                const visibleOptToday = rawOptCandles.slice(0, optSliceCount);
                const visibleOptAll = [...(window._unifiedOptionsYesterdayCandles || []), ...visibleOptToday];
                const displayOptCandles = (oCandleStyle === 'heikin_ashi') ? calculateHeikinAshi(visibleOptAll) : visibleOptAll;
                try {
                    oCandleSeries.setData(displayOptCandles);
                } catch (e) {
                    console.warn('oCandleSeries setData slice error:', e);
                }

                if (window._oCandleMarkers && window._oCandleMarkers.length > 0) {
                    const curOptTimestamp = visibleOptToday[visibleOptToday.length - 1].time;
                    const visibleOptMarkers = window._oCandleMarkers.filter(m => Number(m.time) <= curOptTimestamp);
                    try {
                        oCandleSeries.setMarkers(visibleOptMarkers);
                    } catch (e) {}
                }

                if (oChart) {
                    maintainReplayTimeScale(oChart, optSliceCount - 1, oTimeframe, window._unifiedOptionsYesterdayCandles);
                }
            }
        }
    }

    function restoreFullDayCandles() {
        if (wCandleSeries && window._unifiedAllCandles) {
            const displayCandles = (wCandleStyle === 'heikin_ashi') ? (window._unifiedHaCandles || calculateHeikinAshi(window._unifiedAllCandles)) : window._unifiedAllCandles;
            try {
                wCandleSeries.setData(displayCandles);
                if (window._wCandleMarkers) wCandleSeries.setMarkers(window._wCandleMarkers);
                if (wChart) adjustChartTimeScale(wChart, window._unifiedTodayCandles, window._unifiedYesterdayCandles, wTimeframe);
            } catch (e) {}
        }
        if (oCandleSeries && window._unifiedOptionsCandles) {
            const displayOptCandles = (oCandleStyle === 'heikin_ashi') ? (window._unifiedOptionsHaCandles || calculateHeikinAshi(window._unifiedOptionsCandles)) : window._unifiedOptionsCandles;
            try {
                oCandleSeries.setData(displayOptCandles);
                if (window._oCandleMarkers) oCandleSeries.setMarkers(window._oCandleMarkers);
                if (oChart) adjustChartTimeScale(oChart, window._unifiedOptionsTodayCandles, window._unifiedOptionsYesterdayCandles, oTimeframe);
            } catch (e) {}
        }
    }

    // ══════════════════════════════════════════════════════════════════════════════
    // HIGH-PROBABILITY TRADE ENGINE & DYNAMIC EXIT MATRIX (v2)
    // ══════════════════════════════════════════════════════════════════════════════
    let tradeEngineConfig = {
        strategyMode: 'auto', // 'auto' | 'force_sell' | 'force_buy'
        entryLots: 3,         // 1, 2, or 3 lots
        lotSize: 65,          // Nifty lot size is 65
        t1Rr: 1.2,
        t2Rr: 2.2,
        t3Rr: 3.2,
        f1_sl: true,
        f2_yd_sr: true,
        f3_td_sr: true,
        f4_reverse: true,
        f5_depth: true,
        f6_greeks: true,
        historyMode: 'active', // 'active' | 'all'
    };

    let activeTrade = null;
    let sessionTrades = [];
    let tradePriceLines = [];

    function clearTradePriceLines() {
        if (!wCandleSeries || tradePriceLines.length === 0) return;
        tradePriceLines.forEach(line => {
            try { wCandleSeries.removePriceLine(line); } catch (e) {}
        });
        tradePriceLines = [];
    }

    function updateTradeSettingsBadges() {
        const totalQtyBadge = document.getElementById('cfg-total-qty-badge');
        const lots = Number(tradeEngineConfig.entryLots) || 3;
        const lotSize = Number(tradeEngineConfig.lotSize) || 65;
        if (totalQtyBadge) {
            totalQtyBadge.textContent = `${lots * lotSize} QTY`;
        }
    }

    function syncTradeSettingsInputs() {
        try {
            const saved = localStorage.getItem('trade_engine_v2_cfg');
            if (saved) {
                const parsed = JSON.parse(saved);
                tradeEngineConfig = { ...tradeEngineConfig, ...parsed };
            }
        } catch (e) {}

        const modeSel = document.getElementById('trade-cfg-strategy-mode');
        if (modeSel && tradeEngineConfig.strategyMode) modeSel.value = tradeEngineConfig.strategyMode;
        const lotsSel = document.getElementById('trade-cfg-lots');
        if (lotsSel && tradeEngineConfig.entryLots) lotsSel.value = String(tradeEngineConfig.entryLots);
        const lotSizeInp = document.getElementById('trade-cfg-lot-size');
        if (lotSizeInp && tradeEngineConfig.lotSize) lotSizeInp.value = String(tradeEngineConfig.lotSize);
        const t1Inp = document.getElementById('trade-cfg-t1-rr');
        if (t1Inp && tradeEngineConfig.t1Rr) t1Inp.value = String(tradeEngineConfig.t1Rr);
        const t2Inp = document.getElementById('trade-cfg-t2-rr');
        if (t2Inp && tradeEngineConfig.t2Rr) t2Inp.value = String(tradeEngineConfig.t2Rr);
        const t3Inp = document.getElementById('trade-cfg-t3-rr');
        if (t3Inp && tradeEngineConfig.t3Rr) t3Inp.value = String(tradeEngineConfig.t3Rr);

        const factorMap = {
            'cfg-factor-sl': 'f1_sl',
            'cfg-factor-yd-sr': 'f2_yd_sr',
            'cfg-factor-td-sr': 'f3_td_sr',
            'cfg-factor-reverse': 'f4_reverse',
            'cfg-factor-depth': 'f5_depth',
            'cfg-factor-greeks': 'f6_greeks',
        };
        Object.entries(factorMap).forEach(([id, key]) => {
            const el = document.getElementById(id);
            if (el && typeof tradeEngineConfig[key] === 'boolean') {
                el.checked = tradeEngineConfig[key];
            }
        });

        const histTxt = document.getElementById('trade-history-mode-text');
        if (histTxt) histTxt.textContent = (tradeEngineConfig.historyMode === 'all') ? 'All Trades' : 'Active Only';

        updateTradeSettingsBadges();
    }
    window.syncTradeSettingsInputs = syncTradeSettingsInputs;

    function updateTradeConfig(key, value) {
        tradeEngineConfig[key] = value;
        try {
            localStorage.setItem('trade_engine_v2_cfg', JSON.stringify(tradeEngineConfig));
        } catch (e) {}
        updateTradeSettingsBadges();
        if (timestampsList && timestampsList.length > 0 && activeCandleIndex >= 0) {
            runHighProbabilityTradeEngine(activeCandleIndex, timestampsList[activeCandleIndex]);
        }
    }
    window.updateTradeConfig = updateTradeConfig;

    function toggleTradeHistoryMode() {
        tradeEngineConfig.historyMode = (tradeEngineConfig.historyMode === 'active') ? 'all' : 'active';
        const txt = document.getElementById('trade-history-mode-text');
        if (txt) txt.textContent = (tradeEngineConfig.historyMode === 'all') ? 'All Trades' : 'Active Only';
        updateTradeConfig('historyMode', tradeEngineConfig.historyMode);
    }
    window.toggleTradeHistoryMode = toggleTradeHistoryMode;

    function calcOptPriceAndPoints(trade, spotPrice, candleIdx) {
        const isBull = (trade.direction === 'BULL');
        const favorableSpotMove = isBull ? (spotPrice - trade.entrySpot) : (trade.entrySpot - spotPrice);
        const deltaEst = (trade.action === 'BUY') ? 0.52 : 0.35;
        const elapsedCandles = Math.max(0, candleIdx - trade.entryIndex);
        const thetaDecay = Math.min(25.0, elapsedCandles * 0.35);

        let curOptPrice, ptsGain;
        if (trade.action === 'BUY') {
            ptsGain = (favorableSpotMove * deltaEst) - thetaDecay;
            curOptPrice = Math.max(1.0, Math.round((trade.entryOptPrice + ptsGain) * 10) / 10);
            ptsGain = Math.round((curOptPrice - trade.entryOptPrice) * 10) / 10;
        } else {
            // Option Selling: Premium decays as market moves favorably and theta passes
            ptsGain = (favorableSpotMove * deltaEst) + thetaDecay;
            curOptPrice = Math.max(1.0, Math.round((trade.entryOptPrice - ptsGain) * 10) / 10);
            ptsGain = Math.round((trade.entryOptPrice - curOptPrice) * 10) / 10;
        }
        return { optPrice: curOptPrice, pointsGain: ptsGain };
    }

    function runHighProbabilityTradeEngine(upToIndex, curTimeStr) {
        const rawCandles = weightageData?.candles || weightageData?.nifty_candles || window._unifiedTodayCandles;
        if (!rawCandles || rawCandles.length === 0) return;

        const sUpTh = alarmSettings.stocksUpThreshold || 80;
        const sDownTh = alarmSettings.stocksDownThreshold || 80;
        const sTrig = alarmSettings.stocksTriggerBy || 'both';
        const oUpTh = alarmSettings.optionsUpThreshold || 70;
        const oDownTh = alarmSettings.optionsDownThreshold || 70;

        sessionTrades = [];
        let currentTrade = null;

        // Traverse session from open candle (0) up to current replay/live boundary
        for (let i = 0; i <= upToIndex; i++) {
            const candle = rawCandles[i];
            if (!candle) continue;
            const tStr = candle.time_str;

            // 1. If currently in a trade, evaluate progression on candle i
            if (currentTrade) {
                const cHigh = Number(candle.high);
                const cLow = Number(candle.low);
                const cClose = Number(candle.close);
                const isBull = (currentTrade.direction === 'BULL');

                // Check Multi-Target Hits & Partial Bookings
                // Target 1
                if (!currentTrade.t1Hit) {
                    if ((isBull && cHigh >= currentTrade.t1) || (!isBull && cLow <= currentTrade.t1)) {
                        currentTrade.t1Hit = true;
                        // Partial book 1 lot if user entered with >= 2 lots
                        if (currentTrade.openLots > 1) {
                            const t1Calc = calcOptPriceAndPoints(currentTrade, currentTrade.t1, i);
                            const t1GainRs = Math.round(1 * t1Calc.pointsGain * currentTrade.lotSize);
                            currentTrade.bookedLots += 1;
                            currentTrade.openLots -= 1;
                            currentTrade.bookedPnlRupees += t1GainRs;
                            currentTrade.t1PnlRs = t1GainRs;
                        }
                        currentTrade.currentSl = currentTrade.entrySpot; // Trail SL to Cost Breakeven
                        currentTrade.slType = 'COST_BREAKEVEN';
                    }
                }

                // Target 2
                if (currentTrade.t1Hit && !currentTrade.t2Hit) {
                    if ((isBull && cHigh >= currentTrade.t2) || (!isBull && cLow <= currentTrade.t2)) {
                        currentTrade.t2Hit = true;
                        // Partial book 1 lot if still > 1 lot open
                        if (currentTrade.openLots > 1) {
                            const t2Calc = calcOptPriceAndPoints(currentTrade, currentTrade.t2, i);
                            const t2GainRs = Math.round(1 * t2Calc.pointsGain * currentTrade.lotSize);
                            currentTrade.bookedLots += 1;
                            currentTrade.openLots -= 1;
                            currentTrade.bookedPnlRupees += t2GainRs;
                            currentTrade.t2PnlRs = t2GainRs;
                        }
                        currentTrade.currentSl = currentTrade.t1; // Trail SL to T1
                        currentTrade.slType = 'TRAILED_TO_T1';
                    }
                }

                // Target 3
                if (currentTrade.t2Hit && !currentTrade.t3Hit) {
                    if ((isBull && cHigh >= currentTrade.t3) || (!isBull && cLow <= currentTrade.t3)) {
                        currentTrade.t3Hit = true;
                        // Book remaining runner lots
                        const t3Calc = calcOptPriceAndPoints(currentTrade, currentTrade.t3, i);
                        const t3GainRs = Math.round(currentTrade.openLots * t3Calc.pointsGain * currentTrade.lotSize);
                        currentTrade.bookedPnlRupees += t3GainRs;
                        currentTrade.t3PnlRs = t3GainRs;
                        currentTrade.bookedLots += currentTrade.openLots;
                        currentTrade.openLots = 0;
                        currentTrade.openPnlRupees = 0;
                        currentTrade.pnlRupees = currentTrade.bookedPnlRupees;
                        currentTrade.pnlPoints = Math.round((currentTrade.pnlRupees / (currentTrade.entryLots * currentTrade.lotSize)) * 10) / 10;
                        currentTrade.currentSpot = currentTrade.t3;
                        currentTrade.currentOptPrice = t3Calc.optPrice;

                        currentTrade.status = 'TARGET_HIT';
                        currentTrade.exitTime = tStr;
                        currentTrade.exitSpot = currentTrade.t3;
                        currentTrade.exitReason = 'Target 3 Hit! Complete profit booked.';
                        sessionTrades.push({ ...currentTrade });
                        currentTrade = null;
                        continue;
                    }
                }

                // M2M update on candle close
                currentTrade.currentSpot = cClose;
                const m2mCalc = calcOptPriceAndPoints(currentTrade, cClose, i);
                currentTrade.currentOptPrice = m2mCalc.optPrice;
                currentTrade.openPnlRupees = Math.round(currentTrade.openLots * m2mCalc.pointsGain * currentTrade.lotSize);
                currentTrade.pnlRupees = currentTrade.bookedPnlRupees + currentTrade.openPnlRupees;
                currentTrade.pnlPoints = Math.round((currentTrade.pnlRupees / (currentTrade.entryLots * currentTrade.lotSize)) * 10) / 10;
                currentTrade.mfePoints = Math.max(currentTrade.mfePoints, m2mCalc.pointsGain);
                currentTrade.maePoints = Math.min(currentTrade.maePoints, m2mCalc.pointsGain);

                // Exit Factor Checks
                let exitTriggered = false;
                let exitReason = null;
                let exitPrice = cClose;

                // F1: Signal Candle SL or Trailed SL
                if (tradeEngineConfig.f1_sl) {
                    if ((isBull && cLow <= currentTrade.currentSl) || (!isBull && cHigh >= currentTrade.currentSl)) {
                        exitTriggered = true;
                        exitPrice = currentTrade.currentSl;
                        exitReason = currentTrade.t2Hit ? 'Trailed SL at T1 Hit (Profit Secured)' : (currentTrade.t1Hit ? 'Trailed SL at Cost Hit (Breakeven Locked)' : 'Signal Candle Base SL Hit');
                    }
                }

                // F4: Reverse Signal Cross
                if (!exitTriggered && tradeEngineConfig.f4_reverse && i > 0) {
                    const prevC = rawCandles[i - 1];
                    const b = weightageData?.breadth_by_time?.[tStr];
                    if (b) {
                        const oppStk = isBull
                            ? (b.red_count_pct >= sDownTh && b.red_weight >= sDownTh)
                            : (b.green_count_pct >= sUpTh && b.green_weight >= sUpTh);
                        if (oppStk) {
                            const crossedOver = isBull ? (cClose < Number(prevC.low)) : (cClose > Number(prevC.high));
                            if (crossedOver) {
                                exitTriggered = true;
                                exitPrice = cClose;
                                exitReason = isBull ? 'Opposing Bear Signal Crossover Exit' : 'Opposing Bull Signal Crossover Exit';
                            }
                        }
                    }
                }

                if (exitTriggered) {
                    if (currentTrade.openLots > 0) {
                        const exitCalc = calcOptPriceAndPoints(currentTrade, exitPrice, i);
                        const exitGainRs = Math.round(currentTrade.openLots * exitCalc.pointsGain * currentTrade.lotSize);
                        currentTrade.bookedPnlRupees += exitGainRs;
                        currentTrade.bookedLots += currentTrade.openLots;
                        currentTrade.openLots = 0;
                        currentTrade.openPnlRupees = 0;
                        currentTrade.pnlRupees = currentTrade.bookedPnlRupees;
                        currentTrade.pnlPoints = Math.round((currentTrade.pnlRupees / (currentTrade.entryLots * currentTrade.lotSize)) * 10) / 10;
                        currentTrade.currentOptPrice = exitCalc.optPrice;
                    }
                    currentTrade.status = currentTrade.t1Hit ? 'PARTIAL_PROFIT_EXIT' : 'STOPPED_OUT';
                    currentTrade.exitTime = tStr;
                    currentTrade.exitSpot = exitPrice;
                    currentTrade.exitReason = exitReason;
                    sessionTrades.push({ ...currentTrade });
                    currentTrade = null;
                    continue;
                }
            }

            // 2. If NO active trade, scan for entry signals formed on candle i
            if (!currentTrade && i < rawCandles.length - 1) {
                const b = weightageData?.breadth_by_time?.[tStr];
                const oB = breadthData?.breadth_by_time?.[tStr];

                let sBull = false, sBear = false;
                if (b) {
                    if (sTrig === 'both') {
                        sBull = (b.green_count_pct >= sUpTh && b.green_weight >= sUpTh);
                        sBear = (b.red_count_pct >= sDownTh && b.red_weight >= sDownTh);
                    } else {
                        sBull = (b.green_count_pct >= sUpTh || b.green_weight >= sUpTh);
                        sBear = (b.red_count_pct >= sDownTh || b.red_weight >= sDownTh);
                    }
                }

                let oBull = false, oBear = false;
                if (oB) {
                    oBull = (Number(oB.up_weight || 0) >= oUpTh);
                    oBear = (Number(oB.down_weight || 0) >= oDownTh);
                }

                const isConfBull = sBull && oBull;
                const isConfBear = sBear && oBear;
                const isStkBull = sBull && !oBear;
                const isStkBear = sBear && !oBull;

                let triggeredDirection = null;
                let isConf = false;

                if (isConfBull) { triggeredDirection = 'BULL'; isConf = true; }
                else if (isConfBear) { triggeredDirection = 'BEAR'; isConf = true; }
                else if (isStkBull) { triggeredDirection = 'BULL'; isConf = false; }
                else if (isStkBear) { triggeredDirection = 'BEAR'; isConf = false; }

                if (triggeredDirection) {
                    // Entry executes on the OPEN of NEXT candle (i + 1)!
                    const nextCandle = rawCandles[i + 1];
                    const entrySpot = Number(nextCandle.open);
                    const mode = tradeEngineConfig.strategyMode || 'auto';

                    let strategy = null;
                    let action = null;
                    let optType = null;

                    if (triggeredDirection === 'BULL') {
                        if (mode === 'force_sell') { strategy = 'SELL_PE'; action = 'SELL'; optType = 'PE'; }
                        else if (mode === 'force_buy') { strategy = 'BUY_CE'; action = 'BUY'; optType = 'CE'; }
                        else {
                            // Auto: Strike only -> Sell PE; Confluence -> Buy CE
                            strategy = isConf ? 'BUY_CE' : 'SELL_PE';
                            action = isConf ? 'BUY' : 'SELL';
                            optType = isConf ? 'CE' : 'PE';
                        }
                    } else {
                        if (mode === 'force_sell') { strategy = 'SELL_CE'; action = 'SELL'; optType = 'CE'; }
                        else if (mode === 'force_buy') { strategy = 'BUY_PE'; action = 'BUY'; optType = 'PE'; }
                        else {
                            // Auto: Strike only -> Sell CE; Confluence -> Buy PE
                            strategy = isConf ? 'BUY_PE' : 'SELL_CE';
                            action = isConf ? 'BUY' : 'SELL';
                            optType = isConf ? 'PE' : 'CE';
                        }
                    }

                    // Strike Selection (Round 100 multiples)
                    const atmStrike = Math.round(entrySpot / 100) * 100;
                    let strike = atmStrike;
                    if (strategy === 'SELL_PE') strike = atmStrike - 100;
                    if (strategy === 'SELL_CE') strike = atmStrike + 100;

                    // Base SL = Signal Candle Low (for Bull) or High (for Bear)
                    const baseSl = (triggeredDirection === 'BULL') ? Number(candle.low) : Number(candle.high);
                    const riskPts = Math.max(Math.abs(entrySpot - baseSl), 20.0);

                    // Targets (1.2R, 2.2R, 3.2R)
                    const t1Pts = Math.round(riskPts * (tradeEngineConfig.t1Rr || 1.2) * 10) / 10;
                    const t2Pts = Math.round(riskPts * (tradeEngineConfig.t2Rr || 2.2) * 10) / 10;
                    const t3Pts = Math.round(riskPts * (tradeEngineConfig.t3Rr || 3.2) * 10) / 10;

                    const t1 = (triggeredDirection === 'BULL') ? Math.round((entrySpot + t1Pts) * 10) / 10 : Math.round((entrySpot - t1Pts) * 10) / 10;
                    const t2 = (triggeredDirection === 'BULL') ? Math.round((entrySpot + t2Pts) * 10) / 10 : Math.round((entrySpot - t2Pts) * 10) / 10;
                    const t3 = (triggeredDirection === 'BULL') ? Math.round((entrySpot + t3Pts) * 10) / 10 : Math.round((entrySpot - t3Pts) * 10) / 10;

                    const entryOptPrice = (action === 'BUY') ? 135.0 : 85.0;
                    const entryLots = Number(tradeEngineConfig.entryLots) || 3;
                    const lotSize = Number(tradeEngineConfig.lotSize) || 65;

                    currentTrade = {
                        id: `T_${nextCandle.time_str}`,
                        status: 'ACTIVE',
                        direction: triggeredDirection,
                        isConf: isConf,
                        strategy: strategy,
                        action: action,
                        optType: optType,
                        strike: strike,
                        signalTime: candle.time_str,
                        entryTime: nextCandle.time_str,
                        entryIndex: i + 1,
                        entrySpot: entrySpot,
                        entryOptPrice: entryOptPrice,
                        currentSpot: entrySpot,
                        currentOptPrice: entryOptPrice,
                        entryLots: entryLots,
                        lotSize: lotSize,
                        openLots: entryLots,
                        bookedLots: 0,
                        bookedPnlRupees: 0,
                        openPnlRupees: 0,
                        baseSl: baseSl,
                        currentSl: baseSl,
                        slType: 'SIGNAL_CANDLE_SL',
                        riskPts: riskPts,
                        t1: t1,
                        t1Hit: false,
                        t1PnlRs: 0,
                        t2: t2,
                        t2Hit: false,
                        t2PnlRs: 0,
                        t3: t3,
                        t3Hit: false,
                        t3PnlRs: 0,
                        pnlPoints: 0,
                        pnlRupees: 0,
                        mfePoints: 0,
                        maePoints: 0,
                        exitTime: null,
                        exitSpot: null,
                        exitReason: null,
                    };

                    // If entry is executed at this exact replay step, register it
                    if (i + 1 > upToIndex) {
                        // Entry scheduled for next candle not yet reached in replay
                        currentTrade = null;
                    }
                }
            }
        }

        activeTrade = currentTrade;

        // Update UI Cockpit, Matrix & Chart Price Lines
        updateTradeCockpitUI(activeTrade, sessionTrades);
        updateExitMatrixUI(activeTrade);
        updateTradePriceLines(activeTrade);
    }
    window.runHighProbabilityTradeEngine = runHighProbabilityTradeEngine;

    function updateTradePriceLines(trade) {
        clearTradePriceLines();
        if (!wCandleSeries) return;

        if (trade && trade.status === 'ACTIVE') {
            const isBull = (trade.direction === 'BULL');
            // 1. Entry Line (Cyan)
            const entryLine = wCandleSeries.createPriceLine({
                price: trade.entrySpot,
                color: '#06b6d4',
                lineWidth: 2,
                lineStyle: 0, // Solid
                axisLabelVisible: true,
                title: `ENTRY: ${trade.action} ${trade.strike} ${trade.optType} (${trade.entryLots}L) @ ${trade.entrySpot}`,
            });
            tradePriceLines.push(entryLine);

            // 2. SL Line (Rose)
            const slLabel = (trade.slType === 'TRAILED_TO_T1') ? `TRAILED SL: ${trade.currentSl} (at T1)` : (trade.t1Hit ? `TRAILED SL: ${trade.currentSl} (Cost Breakeven)` : `SL: ${trade.currentSl} (Signal Low/High)`);
            const slLine = wCandleSeries.createPriceLine({
                price: trade.currentSl,
                color: '#f43f5e',
                lineWidth: 2,
                lineStyle: 2, // Dashed
                axisLabelVisible: true,
                title: slLabel,
            });
            tradePriceLines.push(slLine);

            // 3. Targets (Emerald)
            const t1Line = wCandleSeries.createPriceLine({
                price: trade.t1,
                color: trade.t1Hit ? '#10b981' : '#34d399',
                lineWidth: trade.t1Hit ? 2 : 1,
                lineStyle: trade.t1Hit ? 0 : 1, // Dotted
                axisLabelVisible: true,
                title: `T1: ${trade.t1} (1 Lot Partial) ${trade.t1Hit ? '🎯 HIT' : ''}`,
            });
            tradePriceLines.push(t1Line);

            const t2Line = wCandleSeries.createPriceLine({
                price: trade.t2,
                color: trade.t2Hit ? '#10b981' : '#34d399',
                lineWidth: trade.t2Hit ? 2 : 1,
                lineStyle: trade.t2Hit ? 0 : 1,
                axisLabelVisible: true,
                title: `T2: ${trade.t2} (1 Lot Partial) ${trade.t2Hit ? '🎯 HIT' : ''}`,
            });
            tradePriceLines.push(t2Line);

            const t3Line = wCandleSeries.createPriceLine({
                price: trade.t3,
                color: '#10b981',
                lineWidth: 1,
                lineStyle: 1,
                axisLabelVisible: true,
                title: `T3: ${trade.t3} (Runner Lot) ${trade.t3Hit ? '🎯 HIT' : ''}`,
            });
            tradePriceLines.push(t3Line);
        }
    }

    function updateTradeCockpitUI(trade, allTrades) {
        const headerBadge = document.getElementById('header-trade-status-badge');
        const headerPill = document.getElementById('trade-header-pill');
        const headerPillText = document.getElementById('trade-header-pill-text');
        const activeCard = document.getElementById('active-trade-card');
        const noActiveCard = document.getElementById('no-active-trade-card');

        // Header Steppers
        const sT1 = document.getElementById('step-t1');
        const sT2 = document.getElementById('step-t2');
        const sT3 = document.getElementById('step-t3');
        const sSl = document.getElementById('step-sl');

        if (trade && trade.status === 'ACTIVE') {
            const isBull = (trade.direction === 'BULL');
            const pnlColor = (trade.pnlPoints >= 0) ? 'text-emerald-400' : 'text-rose-400';
            const pnlSign = (trade.pnlPoints >= 0) ? '+' : '';

            if (headerBadge) {
                headerBadge.textContent = `${trade.action} ${trade.strike} ${trade.optType} (${trade.openLots}/${trade.entryLots}L)`;
                headerBadge.className = isBull
                    ? 'px-1 py-0.2 rounded text-[8.5px] bg-emerald-950 text-emerald-300 font-mono font-bold border border-emerald-600/60'
                    : 'px-1 py-0.2 rounded text-[8.5px] bg-rose-950 text-rose-300 font-mono font-bold border border-rose-600/60';
            }

            if (headerPillText) {
                headerPillText.textContent = `${trade.action} ${trade.strike} ${trade.optType} @ ${trade.entryTime} | P&L: ${pnlSign}₹${trade.pnlRupees.toLocaleString('en-IN')} (${pnlSign}${trade.pnlPoints} pts)`;
            }

            if (sT1) {
                sT1.textContent = `T1: ${trade.t1} ${trade.t1Hit ? '🎯' : ''}`;
                sT1.className = trade.t1Hit
                    ? 'px-1.5 py-0.2 rounded bg-emerald-950 text-emerald-300 border border-emerald-600 font-bold'
                    : 'px-1.5 py-0.2 rounded bg-slate-800 text-gray-300 border border-slate-700';
            }
            if (sT2) {
                sT2.textContent = `T2: ${trade.t2} ${trade.t2Hit ? '🎯' : ''}`;
                sT2.className = trade.t2Hit
                    ? 'px-1.5 py-0.2 rounded bg-emerald-950 text-emerald-300 border border-emerald-600 font-bold'
                    : 'px-1.5 py-0.2 rounded bg-slate-800 text-gray-300 border border-slate-700';
            }
            if (sT3) sT3.textContent = `T3: ${trade.t3}`;
            if (sSl) sSl.textContent = `SL: ${trade.currentSl} ${(trade.slType === 'TRAILED_TO_T1') ? '(T1)' : (trade.t1Hit ? '(Cost)' : '')}`;

            if (activeCard) activeCard.classList.remove('hidden');
            if (noActiveCard) noActiveCard.classList.add('hidden');

            // Populate Active Card
            const actBadge = document.getElementById('at-action-badge');
            if (actBadge) {
                actBadge.textContent = `${isBull ? '🟢' : '🔴'} ${trade.action} ${trade.optType}`;
                actBadge.className = isBull
                    ? 'px-2 py-0.5 rounded text-[11px] font-black tracking-wide bg-emerald-950 text-emerald-300 border border-emerald-600/60'
                    : 'px-2 py-0.5 rounded text-[11px] font-black tracking-wide bg-rose-950 text-rose-300 border border-rose-600/60';
            }

            const atInst = document.getElementById('at-instrument');
            if (atInst) atInst.textContent = `NIFTY ${trade.strike} ${trade.optType}`;

            const atEntryTime = document.getElementById('at-entry-time');
            if (atEntryTime) atEntryTime.textContent = trade.entryTime;

            const atEntrySpot = document.getElementById('at-entry-spot');
            if (atEntrySpot) atEntrySpot.textContent = Number(trade.entrySpot).toFixed(2);

            const atEntryOpt = document.getElementById('at-entry-opt');
            if (atEntryOpt) atEntryOpt.textContent = `₹${trade.entryOptPrice.toFixed(2)}`;

            const atCurrOpt = document.getElementById('at-curr-opt');
            if (atCurrOpt) atCurrOpt.textContent = `₹${trade.currentOptPrice.toFixed(2)}`;

            // Lots breakdown & Net P&L
            const atLotsSummary = document.getElementById('at-lots-summary');
            if (atLotsSummary) {
                atLotsSummary.textContent = `NET P&L (${trade.entryLots} LOTS / ${trade.entryLots * trade.lotSize} QTY @ ${trade.lotSize}/LOT)`;
            }

            const atPnlRs = document.getElementById('at-pnl-rupees');
            if (atPnlRs) {
                atPnlRs.textContent = `${pnlSign}₹${trade.pnlRupees.toLocaleString('en-IN')}`;
                atPnlRs.className = `text-xl font-black font-mono ${pnlColor}`;
            }

            const atPnlPts = document.getElementById('at-pnl-points');
            if (atPnlPts) {
                atPnlPts.textContent = `(${pnlSign}${trade.pnlPoints.toFixed(1)} pts)`;
                atPnlPts.className = `text-xs font-bold font-mono ${pnlColor}`;
            }

            const atPnlBooked = document.getElementById('at-pnl-booked');
            if (atPnlBooked) {
                atPnlBooked.textContent = `₹${trade.bookedPnlRupees.toLocaleString('en-IN')}`;
                atPnlBooked.className = (trade.bookedPnlRupees >= 0) ? 'text-emerald-300 font-bold' : 'text-rose-300 font-bold';
            }

            const atLotsBooked = document.getElementById('at-lots-booked');
            if (atLotsBooked) atLotsBooked.textContent = trade.bookedLots;

            const atPnlOpen = document.getElementById('at-pnl-open');
            if (atPnlOpen) {
                const openSign = (trade.openPnlRupees >= 0) ? '+' : '';
                atPnlOpen.textContent = `${openSign}₹${trade.openPnlRupees.toLocaleString('en-IN')}`;
                atPnlOpen.className = (trade.openPnlRupees >= 0) ? 'text-sky-300 font-bold' : 'text-rose-300 font-bold';
            }

            const atLotsOpen = document.getElementById('at-lots-open');
            if (atLotsOpen) atLotsOpen.textContent = trade.openLots;

            const atMfe = document.getElementById('at-mfe');
            if (atMfe) atMfe.textContent = `+${trade.mfePoints.toFixed(1)} pts`;

            const atMae = document.getElementById('at-mae');
            if (atMae) atMae.textContent = `${trade.maePoints.toFixed(1)} pts`;

            const atCurrSpot = document.getElementById('at-curr-spot');
            if (atCurrSpot) atCurrSpot.textContent = Number(trade.currentSpot).toFixed(2);

            const atSlVal = document.getElementById('at-sl-val');
            if (atSlVal) atSlVal.textContent = trade.currentSl.toFixed(2);

            const atSlBadge = document.getElementById('at-sl-badge');
            if (atSlBadge) {
                atSlBadge.textContent = (trade.slType === 'TRAILED_TO_T1') ? 'Trailed to T1' : (trade.t1Hit ? 'Cost Breakeven' : 'Base SL');
                atSlBadge.className = trade.t1Hit ? 'px-1 py-0.2 rounded text-[8.5px] bg-emerald-950 text-emerald-300 border border-emerald-700 font-bold' : 'px-1 py-0.2 rounded text-[8.5px] bg-rose-950 text-rose-300';
            }

            const atT1Val = document.getElementById('at-t1-val');
            if (atT1Val) atT1Val.textContent = `${trade.t1.toFixed(2)} (+${Math.abs(trade.t1 - trade.entrySpot).toFixed(1)} pts)`;

            const atT1Badge = document.getElementById('at-t1-badge');
            if (atT1Badge) {
                if (trade.t1Hit) {
                    atT1Badge.textContent = trade.entryLots > 1 ? `🎯 1 LOT BOOKED (+₹${trade.t1PnlRs.toLocaleString('en-IN')})` : '🎯 T1 HIT • SL to Cost';
                    atT1Badge.className = 'px-1 py-0.2 rounded text-[8.5px] bg-emerald-950 text-emerald-300 border border-emerald-700 font-bold';
                } else {
                    atT1Badge.textContent = trade.entryLots > 1 ? 'Book 1 Lot (SL to Cost)' : 'SL to Cost';
                    atT1Badge.className = 'px-1 py-0.2 rounded text-[8.5px] bg-slate-800 text-gray-400';
                }
            }

            const atT2Val = document.getElementById('at-t2-val');
            if (atT2Val) atT2Val.textContent = `${trade.t2.toFixed(2)} (+${Math.abs(trade.t2 - trade.entrySpot).toFixed(1)} pts)`;

            const atT2Badge = document.getElementById('at-t2-badge');
            if (atT2Badge) {
                if (trade.t2Hit) {
                    atT2Badge.textContent = trade.entryLots > 1 ? `🎯 1 LOT BOOKED (+₹${trade.t2PnlRs.toLocaleString('en-IN')})` : '🎯 T2 HIT • SL to T1';
                    atT2Badge.className = 'px-1 py-0.2 rounded text-[8.5px] bg-emerald-950 text-emerald-300 border border-emerald-700 font-bold';
                } else {
                    atT2Badge.textContent = trade.entryLots >= 3 ? 'Book 1 Lot (SL to T1)' : (trade.entryLots === 2 ? 'Book 1 Lot (Exit)' : 'SL to T1');
                    atT2Badge.className = 'px-1 py-0.2 rounded text-[8.5px] bg-slate-800 text-gray-400';
                }
            }

            const atT3Val = document.getElementById('at-t3-val');
            if (atT3Val) atT3Val.textContent = `${trade.t3.toFixed(2)} (+${Math.abs(trade.t3 - trade.entrySpot).toFixed(1)} pts)`;

            const atT3Badge = document.getElementById('at-t3-badge');
            if (atT3Badge) {
                if (trade.t3Hit) {
                    atT3Badge.textContent = `🎯 RUNNER BOOKED (+₹${trade.t3PnlRs.toLocaleString('en-IN')})`;
                    atT3Badge.className = 'px-1 py-0.2 rounded text-[8.5px] bg-emerald-950 text-emerald-300 border border-emerald-700 font-bold';
                } else {
                    atT3Badge.textContent = 'Runner Lot';
                    atT3Badge.className = 'px-1 py-0.2 rounded text-[8.5px] bg-slate-800 text-gray-400';
                }
            }

        } else {
            // Standby / No Active Trade
            if (headerBadge) {
                headerBadge.textContent = 'STANDBY';
                headerBadge.className = 'px-1 py-0.2 rounded text-[8.5px] bg-slate-900/80 text-emerald-300 font-mono';
            }
            if (headerPillText) {
                headerPillText.textContent = 'STANDBY: Scanning 80%+ Breadth Signals...';
            }
            if (sT1) sT1.textContent = 'T1: --';
            if (sT2) sT2.textContent = 'T2: --';
            if (sT3) sT3.textContent = 'T3: --';
            if (sSl) sSl.textContent = 'SL: --';

            if (activeCard) activeCard.classList.add('hidden');
            if (noActiveCard) noActiveCard.classList.remove('hidden');
        }

        // Populate Trade History Table
        const histCount = document.getElementById('trade-history-count');
        const histTbody = document.getElementById('trade-history-tbody');
        if (histCount) histCount.textContent = `${allTrades.length} Trades`;

        if (histTbody) {
            if (allTrades.length === 0) {
                histTbody.innerHTML = `<tr><td colspan="11" class="text-center py-2 text-gray-500">No completed trades yet in this session.</td></tr>`;
            } else {
                histTbody.innerHTML = allTrades.map(tr => {
                    const isWin = tr.pnlPoints >= 0;
                    const pnlClass = isWin ? 'text-emerald-400 font-bold' : 'text-rose-400 font-bold';
                    const sign = isWin ? '+' : '';
                    return `
                        <tr class="hover:bg-slate-800/40">
                            <td class="py-1 px-1 text-gray-400">${tr.id}</td>
                            <td class="py-1 px-1 font-bold ${tr.direction === 'BULL' ? 'text-emerald-300' : 'text-rose-300'}">${tr.action} ${tr.optType} (${tr.entryLots}L)</td>
                            <td class="py-1 px-1 text-white">${tr.strike}</td>
                            <td class="py-1 px-1 text-gray-300">${tr.entryTime}</td>
                            <td class="py-1 px-1 text-gray-200">${Number(tr.entrySpot).toFixed(1)}</td>
                            <td class="py-1 px-1 text-gray-300">${tr.exitTime || '--'}</td>
                            <td class="py-1 px-1 text-gray-200">${tr.exitSpot ? Number(tr.exitSpot).toFixed(1) : '--'}</td>
                            <td class="py-1 px-1 ${pnlClass}">${sign}${tr.pnlPoints.toFixed(1)}</td>
                            <td class="py-1 px-1 ${pnlClass}">${sign}₹${tr.pnlRupees.toLocaleString('en-IN')}</td>
                            <td class="py-1 px-1 text-gray-300">${tr.exitReason || tr.status}</td>
                            <td class="py-1 px-1 text-right">
                                <span class="px-1 py-0.2 rounded text-[8.5px] ${isWin ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : 'bg-rose-950 text-rose-300 border border-rose-800'}">${tr.status}</span>
                            </td>
                        </tr>
                    `;
                }).join('');
            }
        }
    }

    function updateExitMatrixUI(trade) {
        if (!trade || trade.status !== 'ACTIVE') {
            const summaryBadge = document.getElementById('exit-engine-summary-badge');
            if (summaryBadge) {
                summaryBadge.textContent = 'SCANNING MARKET CONDITIONS';
                summaryBadge.className = 'px-2 py-0.5 rounded font-bold bg-slate-900 text-gray-400 border border-slate-700';
            }
            return;
        }

        const isBull = (trade.direction === 'BULL');
        const spot = trade.currentSpot;

        // F1: Signal Candle SL
        const slDist = isBull ? (spot - trade.currentSl) : (trade.currentSl - spot);
        const f1Anchor = document.getElementById('fm-f1-anchor');
        const f1Dist = document.getElementById('fm-f1-dist');
        const f1Status = document.getElementById('fm-f1-status');
        const f1Action = document.getElementById('fm-f1-action');

        if (f1Anchor) f1Anchor.textContent = `${trade.currentSl.toFixed(2)} (${(trade.slType === 'TRAILED_TO_T1') ? 'T1' : (trade.t1Hit ? 'Cost' : 'Base')})`;
        if (f1Dist) f1Dist.textContent = (slDist >= 0) ? `+${slDist.toFixed(1)} pts safe` : `${slDist.toFixed(1)} pts breached!`;
        if (f1Status) {
            f1Status.innerHTML = (slDist >= 0)
                ? `<span class="px-1.5 py-0.2 rounded text-[9px] bg-emerald-950 text-emerald-300 border border-emerald-800">🟢 SAFE</span>`
                : `<span class="px-1.5 py-0.2 rounded text-[9px] bg-rose-950 text-rose-300 border border-rose-800 animate-pulse">🔴 BREACHED</span>`;
        }
        if (f1Action) {
            f1Action.textContent = (slDist >= 0) ? (trade.t1Hit ? 'PROFIT LOCKED' : 'HOLD / SAFE') : 'EXIT POSITION';
            f1Action.className = (slDist >= 0) ? 'py-1.5 px-1.5 text-right font-bold text-emerald-400' : 'py-1.5 px-1.5 text-right font-bold text-rose-400';
        }

        // F2: Yesterday S/R
        const ydLevels = orderFlowLevelsData?.yesterday;
        let nearestYd = null;
        let ydDist = 999;
        if (ydLevels) {
            const targets = isBull ? (ydLevels.asks || []) : (ydLevels.bids || []);
            targets.forEach(lvl => {
                const diff = isBull ? (lvl.price - spot) : (spot - lvl.price);
                if (diff > 0 && diff < ydDist) {
                    ydDist = diff;
                    nearestYd = lvl;
                }
            });
        }
        const f2Anchor = document.getElementById('fm-f2-anchor');
        const f2Dist = document.getElementById('fm-f2-dist');
        const f2Status = document.getElementById('fm-f2-status');
        const f2Action = document.getElementById('fm-f2-action');
        if (f2Anchor) f2Anchor.textContent = nearestYd ? `${nearestYd.price} [YD ${isBull ? 'RES' : 'SUP'}]` : 'No YD Wall in Range';
        if (f2Dist) f2Dist.textContent = nearestYd ? `${ydDist.toFixed(1)} pts away` : 'Clear Path';
        if (f2Status) {
            f2Status.innerHTML = (ydDist <= 10)
                ? `<span class="px-1.5 py-0.2 rounded text-[9px] bg-amber-950 text-amber-300 border border-amber-800 animate-pulse">🟡 NEAR WALL</span>`
                : `<span class="px-1.5 py-0.2 rounded text-[9px] bg-emerald-950 text-emerald-300 border border-emerald-800">🟢 CLEAR</span>`;
        }
        if (f2Action) {
            f2Action.textContent = (ydDist <= 10) ? 'TRAIL SL TIGHT' : 'CLEAR PATH';
            f2Action.className = (ydDist <= 10) ? 'py-1.5 px-1.5 text-right font-bold text-amber-400' : 'py-1.5 px-1.5 text-right font-bold text-emerald-400';
        }

        // F3: Today S/R
        const tdLevels = orderFlowLevelsData?.today;
        let nearestTd = null;
        let tdDist = 999;
        if (tdLevels) {
            const targets = isBull ? (tdLevels.asks || []) : (tdLevels.bids || []);
            targets.forEach(lvl => {
                const diff = isBull ? (lvl.price - spot) : (spot - lvl.price);
                if (diff > 0 && diff < tdDist) {
                    tdDist = diff;
                    nearestTd = lvl;
                }
            });
        }
        const f3Anchor = document.getElementById('fm-f3-anchor');
        const f3Dist = document.getElementById('fm-f3-dist');
        const f3Status = document.getElementById('fm-f3-status');
        const f3Action = document.getElementById('fm-f3-action');
        if (f3Anchor) f3Anchor.textContent = nearestTd ? `${nearestTd.price} [TD ${isBull ? 'RES' : 'SUP'}]` : 'No TD Wall in Range';
        if (f3Dist) f3Dist.textContent = nearestTd ? `${tdDist.toFixed(1)} pts away` : 'Clear Path';
        if (f3Status) {
            f3Status.innerHTML = (tdDist <= 10)
                ? `<span class="px-1.5 py-0.2 rounded text-[9px] bg-amber-950 text-amber-300 border border-amber-800 animate-pulse">🟡 AT BARRIER</span>`
                : `<span class="px-1.5 py-0.2 rounded text-[9px] bg-emerald-950 text-emerald-300 border border-emerald-800">🟢 CLEAR</span>`;
        }
        if (f3Action) {
            f3Action.textContent = (tdDist <= 10) ? `WATCH AT ${nearestTd?.price}` : 'CLEAR PATH';
            f3Action.className = (tdDist <= 10) ? 'py-1.5 px-1.5 text-right font-bold text-amber-400' : 'py-1.5 px-1.5 text-right font-bold text-emerald-400';
        }

        // F4: Reverse Signal Cross
        const f4Anchor = document.getElementById('fm-f4-anchor');
        const f4Dist = document.getElementById('fm-f4-dist');
        const f4Status = document.getElementById('fm-f4-status');
        const f4Action = document.getElementById('fm-f4-action');
        if (f4Anchor) f4Anchor.textContent = 'Opposing Signal Barrier';
        if (f4Dist) f4Dist.textContent = isBull ? 'Clean Bull Trend' : 'Clean Bear Trend';
        if (f4Status) {
            f4Status.innerHTML = `<span class="px-1.5 py-0.2 rounded text-[9px] bg-emerald-950 text-emerald-300 border border-emerald-800">🟢 CLEAR</span>`;
        }
        if (f4Action) {
            f4Action.textContent = 'NO REVERSE';
            f4Action.className = 'py-1.5 px-1.5 text-right font-bold text-emerald-400';
        }

        // F5: 30-Depth Volume Flow
        const spread = orderFlowLevelsData?.basis_spread || 0;
        const f5Anchor = document.getElementById('fm-f5-anchor');
        const f5Dist = document.getElementById('fm-f5-dist');
        const f5Status = document.getElementById('fm-f5-status');
        const f5Action = document.getElementById('fm-f5-action');
        const spreadSupportive = isBull ? (spread >= 0) : (spread <= 0);
        if (f5Anchor) f5Anchor.textContent = `Basis Spread: ${spread > 0 ? '+' : ''}${spread} pts`;
        if (f5Dist) f5Dist.textContent = spreadSupportive ? 'Institutional Flow Aligned' : 'Spread Contraction Warning';
        if (f5Status) {
            f5Status.innerHTML = spreadSupportive
                ? `<span class="px-1.5 py-0.2 rounded text-[9px] bg-emerald-950 text-emerald-300 border border-emerald-800">🟢 SUPPORTIVE</span>`
                : `<span class="px-1.5 py-0.2 rounded text-[9px] bg-amber-950 text-amber-300 border border-amber-800">🟡 WATCH</span>`;
        }
        if (f5Action) {
            f5Action.textContent = spreadSupportive ? 'FLOW ALIGNED' : 'MONITOR BASIS';
            f5Action.className = spreadSupportive ? 'py-1.5 px-1.5 text-right font-bold text-emerald-400' : 'py-1.5 px-1.5 text-right font-bold text-amber-400';
        }

        // F6: Theta & Greeks
        const f6Anchor = document.getElementById('fm-f6-anchor');
        const f6Dist = document.getElementById('fm-f6-dist');
        const f6Status = document.getElementById('fm-f6-status');
        const f6Action = document.getElementById('fm-f6-action');
        const isSell = (trade.action === 'SELL');
        if (f6Anchor) f6Anchor.textContent = isSell ? `Theta: +₹${(trade.lotSize * 0.35).toFixed(1)}/lot/5m` : `Theta: -₹${(trade.lotSize * 0.35).toFixed(1)}/lot/5m`;
        if (f6Dist) f6Dist.textContent = isSell ? 'Delta: ~0.35 • Theta Decay Favorable' : 'Delta: ~0.52 • Momentum Required';
        if (f6Status) {
            f6Status.innerHTML = isSell
                ? `<span class="px-1.5 py-0.2 rounded text-[9px] bg-emerald-950 text-emerald-300 border border-emerald-800">🟢 THETA GAIN</span>`
                : `<span class="px-1.5 py-0.2 rounded text-[9px] bg-sky-950 text-sky-300 border border-sky-800">🔵 ACTIVE DELTA</span>`;
        }
        if (f6Action) {
            f6Action.textContent = isSell ? 'TIME DECAY BENEFIT' : 'TARGET MOMENTUM';
            f6Action.className = isSell ? 'py-1.5 px-1.5 text-right font-bold text-emerald-400' : 'py-1.5 px-1.5 text-right font-bold text-sky-400';
        }

        // Summary Badge
        const summaryBadge = document.getElementById('exit-engine-summary-badge');
        if (summaryBadge) {
            if (slDist < 0) {
                summaryBadge.textContent = 'CRITICAL: STOP LOSS BREACHED';
                summaryBadge.className = 'px-2 py-0.5 rounded font-bold bg-rose-950 text-rose-300 border border-rose-600 animate-pulse';
            } else if (ydDist <= 10 || tdDist <= 10) {
                summaryBadge.textContent = 'APPROACHING RESISTANCE WALL';
                summaryBadge.className = 'px-2 py-0.5 rounded font-bold bg-amber-950 text-amber-300 border border-amber-600';
            } else {
                summaryBadge.textContent = 'POSITION IN GOOD HEALTH & DIRECTION';
                summaryBadge.className = 'px-2 py-0.5 rounded font-bold bg-emerald-950 text-emerald-300 border border-emerald-700';
            }
        }
    }

    // ══════════════════════════════════════════════════════════════════════════════
    // DOCKED & UNDOCKED (DRAGGABLE & RESIZABLE) DRAWER CONTROLS
    // ══════════════════════════════════════════════════════════════════════════════
    let isTradeDrawerUndocked = false;
    let isTradeDrawerExpanded = false;
    let isTradeDrawerVisible = true;

    function toggleTradeDrawer() {
        const drawer = document.getElementById('trade-cockpit-drawer');
        if (!drawer) return;
        isTradeDrawerVisible = !isTradeDrawerVisible;
        drawer.style.display = isTradeDrawerVisible ? 'flex' : 'none';
        if (isTradeDrawerVisible && !isTradeDrawerExpanded) {
            toggleTradeDrawerExpand(true);
        }
    }
    window.toggleTradeDrawer = toggleTradeDrawer;

    function hideTradeDrawer() {
        const drawer = document.getElementById('trade-cockpit-drawer');
        if (!drawer) return;
        isTradeDrawerVisible = false;
        drawer.style.display = 'none';
    }
    window.hideTradeDrawer = hideTradeDrawer;

    function toggleTradeDrawerExpand(forceState = null) {
        const body = document.getElementById('trade-drawer-body');
        const icon = document.getElementById('trade-expand-icon');
        const text = document.getElementById('trade-expand-text');
        if (!body) return;

        if (typeof forceState === 'boolean') {
            isTradeDrawerExpanded = forceState;
        } else {
            isTradeDrawerExpanded = !isTradeDrawerExpanded;
        }

        if (isTradeDrawerExpanded) {
            body.classList.remove('hidden');
            if (icon) icon.textContent = '▼';
            if (text) text.textContent = 'Collapse';
        } else {
            body.classList.add('hidden');
            if (icon) icon.textContent = '▲';
            if (text) text.textContent = 'Expand';
        }
    }
    window.toggleTradeDrawerExpand = toggleTradeDrawerExpand;

    function toggleTradeDrawerUndock() {
        const drawer = document.getElementById('trade-cockpit-drawer');
        const undockIcon = document.getElementById('trade-undock-icon');
        const undockText = document.getElementById('trade-undock-text');
        const header = document.getElementById('trade-drawer-header');
        if (!drawer) return;

        isTradeDrawerUndocked = !isTradeDrawerUndocked;

        if (isTradeDrawerUndocked) {
            // Undock into floating modal
            drawer.className = 'fixed z-[70] bg-[#0b0f19]/98 backdrop-blur-xl border border-sky-500/60 shadow-2xl rounded-lg resize overflow-hidden flex flex-col font-sans';
            drawer.style.left = '60px';
            drawer.style.top = '100px';
            drawer.style.width = '880px';
            drawer.style.height = '480px';
            drawer.style.minWidth = '460px';
            drawer.style.minHeight = '280px';
            drawer.style.bottom = 'auto';
            drawer.style.right = 'auto';

            if (header) header.style.cursor = 'move';
            if (undockIcon) undockIcon.textContent = '⏬';
            if (undockText) undockText.textContent = 'Dock Back';
            toggleTradeDrawerExpand(true);
        } else {
            // Dock back to bottom
            drawer.className = 'fixed bottom-0 left-0 right-0 z-[60] bg-[#0b0f19]/95 backdrop-blur-md border-t border-sky-500/40 shadow-2xl transition-all duration-200 flex flex-col font-sans';
            drawer.style.left = '0';
            drawer.style.right = '0';
            drawer.style.bottom = '0';
            drawer.style.top = 'auto';
            drawer.style.width = 'auto';
            drawer.style.height = 'auto';
            drawer.style.minWidth = 'auto';
            drawer.style.minHeight = 'auto';

            if (header) header.style.cursor = 'default';
            if (undockIcon) undockIcon.textContent = '⧉';
            if (undockText) undockText.textContent = 'Pop Out';
        }
    }
    window.toggleTradeDrawerUndock = toggleTradeDrawerUndock;

    function toggleTradeSettingsPanel() {
        const panel = document.getElementById('trade-settings-panel');
        if (panel) panel.classList.toggle('hidden');
    }
    window.toggleTradeSettingsPanel = toggleTradeSettingsPanel;

    // ══════════════════════════════════════════════════════════════════════════════
    // DRAWER HEIGHT CONTROLS & PERSISTENCE
    // ══════════════════════════════════════════════════════════════════════════════
    function setTradeDrawerHeight(heightVal, isUserAction = true) {
        const body = document.getElementById('trade-drawer-body');
        const heightSelect = document.getElementById('trade-drawer-height-select');
        if (!body) return;

        const numVal = parseInt(heightVal, 10);
        if (isNaN(numVal) || numVal < 150) return;

        body.style.maxHeight = `${numVal}px`;

        if (heightSelect) {
            let matched = false;
            for (let i = 0; i < heightSelect.options.length; i++) {
                if (parseInt(heightSelect.options[i].value, 10) === numVal) {
                    heightSelect.selectedIndex = i;
                    matched = true;
                    break;
                }
            }
            if (!matched) {
                heightSelect.value = 'custom';
            }
        }

        if (isUserAction) {
            try {
                localStorage.setItem('trade_drawer_docked_height', String(numVal));
            } catch (e) {}
        }
    }
    window.setTradeDrawerHeight = setTradeDrawerHeight;

    function initTradeDrawerHeight() {
        let savedHeight = '440';
        try {
            const stored = localStorage.getItem('trade_drawer_docked_height');
            if (stored && parseInt(stored, 10) >= 150) savedHeight = stored;
        } catch (e) {}
        setTradeDrawerHeight(savedHeight, false);
    }

    function setupTradeDrawerResizeDrag() {
        const resizeBar = document.getElementById('trade-drawer-resize-bar');
        const body = document.getElementById('trade-drawer-body');
        if (!resizeBar || !body) return;

        let isResizing = false;
        let startY = 0;
        let startHeight = 0;

        resizeBar.addEventListener('mousedown', (e) => {
            if (isTradeDrawerUndocked) return;
            isResizing = true;
            startY = e.clientY;
            startHeight = body.offsetHeight || parseInt(body.style.maxHeight, 10) || 440;
            document.body.style.userSelect = 'none';
            document.body.style.cursor = 'ns-resize';
            if (!isTradeDrawerExpanded) toggleTradeDrawerExpand(true);
        });

        document.addEventListener('mousemove', (e) => {
            if (!isResizing || isTradeDrawerUndocked) return;
            const deltaY = startY - e.clientY; // dragging up increases height
            const maxAllowed = Math.max(300, window.innerHeight - 100);
            const newHeight = Math.min(Math.max(200, Math.round(startHeight + deltaY)), maxAllowed);
            setTradeDrawerHeight(newHeight, false);
        });

        document.addEventListener('mouseup', () => {
            if (isResizing) {
                isResizing = false;
                document.body.style.userSelect = '';
                document.body.style.cursor = '';
                const finalH = body.offsetHeight || parseInt(body.style.maxHeight, 10) || 440;
                try {
                    localStorage.setItem('trade_drawer_docked_height', String(finalH));
                } catch (e) {}
            }
        });
    }

    // Draggable Window Handler for Undocked Mode
    let isDraggingTradeModal = false;
    let dragStartX = 0, dragStartY = 0;
    let modalStartX = 0, modalStartY = 0;

    function setupTradeModalDrag() {
        const header = document.getElementById('trade-drawer-header');
        const drawer = document.getElementById('trade-cockpit-drawer');
        if (!header || !drawer) return;

        header.addEventListener('mousedown', (e) => {
            if (!isTradeDrawerUndocked) return;
            if (e.target.closest('button') || e.target.closest('select') || e.target.closest('input')) return;
            isDraggingTradeModal = true;
            dragStartX = e.clientX;
            dragStartY = e.clientY;
            modalStartX = drawer.offsetLeft;
            modalStartY = drawer.offsetTop;
            document.body.style.userSelect = 'none';
        });

        document.addEventListener('mousemove', (e) => {
            if (!isDraggingTradeModal || !isTradeDrawerUndocked) return;
            const deltaX = e.clientX - dragStartX;
            const deltaY = e.clientY - dragStartY;
            drawer.style.left = `${Math.max(10, modalStartX + deltaX)}px`;
            drawer.style.top = `${Math.max(40, modalStartY + deltaY)}px`;
        });

        document.addEventListener('mouseup', () => {
            if (isDraggingTradeModal) {
                isDraggingTradeModal = false;
                document.body.style.userSelect = '';
            }
        });
    }

    // Keyboard Shortcuts: 'B' for Bar Replay, 'T' for Trade Cockpit
    document.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA') return;
        if (e.key === 'b' || e.key === 'B') {
            toggleBarReplayMode();
        } else if (e.key === 't' || e.key === 'T') {
            toggleTradeDrawer();
        }
    });

    // ── App Bootstrapper ──────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        applyCockpitFontScale(currentCockpitFontScale);
        applyTimerVisibility();
        setupCandleCloseCountdown();
        setupOiBuildup5mSync();
        setupTradeModalDrag();
        initTradeDrawerHeight();
        setupTradeDrawerResizeDrag();
        syncTradeSettingsInputs();
        updateBarReplayUI();
        toggleTradeDrawerExpand(true);
        updateToiLegendUI();
        updateToiStreamToggleUI();
        initLightweightCharts();
        applyAllSectionHeights();
        setupControls();
        syncAlarmToolbarUI();
        updateMarkerButtonsUI();
        updateTvToolsUI();
        updateCandleStyleUI();
        updateChartBasisUI();
        syncBuildupTabUI();
        syncSynergyControlsUI();
        const toiStrikesList = document.getElementById('toi-strikes-list');
        if (toiStrikesList) {
            toiStrikesList.addEventListener('wheel', (evt) => {
                if (evt.deltaY !== 0) {
                    evt.preventDefault();
                    toiStrikesList.scrollLeft += evt.deltaY;
                }
            }, { passive: false });
        }
        fetchAlertLogs(false);
        loadAllData();
        setupAutoRefresh();
    });

})();
</script>
@endpush
