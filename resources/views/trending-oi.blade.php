@extends('layouts.app')

@section('title', 'Trending OI - Options Analysis')

@section('content')
<div class="w-full text-gray-800" id="trending-oi-root">

    {{-- ══════════ TOP CONTROLS (SPACIOUS SINGLE LINE) ══════════ --}}
    <div class="bg-white border-b border-gray-200 px-5 py-2.5 flex items-center justify-between gap-x-6 text-xs shadow-xs overflow-x-auto whitespace-nowrap">
        <div class="flex items-center gap-x-5">
            {{-- Mode --}}
            <div class="flex items-center gap-3 pr-3 border-r border-gray-200">
                <label class="flex items-center gap-1.5 cursor-pointer font-bold text-xs">
                    <input type="radio" name="mode" value="live" id="toi-mode-live" class="accent-red-600">
                    <span class="text-red-600 flex items-center gap-0.5">● Live</span>
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer text-xs text-gray-600">
                    <input type="radio" name="mode" value="history" id="toi-mode-history">
                    <span>Hist</span>
                </label>
            </div>

            {{-- Underlying --}}
            <div class="flex items-center">
                <select id="toi-underlying" class="border border-gray-300 rounded-md px-2.5 py-1.5 text-xs bg-white font-semibold text-gray-700 focus:outline-hidden focus:ring-1 focus:ring-red-600">
                    <option value="NSE_INDEX|Nifty 50">NIFTY</option>
                </select>
            </div>

            {{-- Date (Historical Mode only) --}}
            <div class="flex items-center gap-2" id="toi-date-wrapper">
                <input type="date" id="toi-date" value="{{ $selectedDate }}"
                       class="border border-gray-300 rounded-md px-2.5 py-1.5 text-xs bg-white focus:outline-hidden focus:ring-1 focus:ring-red-600">
            </div>

            {{-- Expiry Date --}}
            <div class="flex items-center gap-2">
                <label class="text-gray-500 text-[11px] font-medium">Expiry</label>
                <select id="toi-expiry" class="border border-gray-300 rounded-md px-2.5 py-1.5 text-xs bg-white min-w-[110px] focus:outline-hidden focus:ring-1 focus:ring-red-600">
                    @foreach($expiries as $exp)
                        <option value="{{ $exp }}" {{ $exp === $selectedExpiry ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::parse($exp)->format('d-M-Y') }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Time Interval --}}
            <div class="flex items-center gap-2">
                <label class="text-gray-500 text-[11px] font-medium">Interval</label>
                <select id="toi-interval" class="border border-gray-300 rounded-md px-2.5 py-1.5 text-xs bg-white focus:outline-hidden focus:ring-1 focus:ring-red-600">
                    <option value="1">1m</option>
                    <option value="3">3m</option>
                    <option value="5" selected>5m</option>
                    <option value="15">15m</option>
                </select>
            </div>

            {{-- Lookback Trigger --}}
            <div class="flex items-center gap-2">
                <label class="text-gray-500 text-[11px] font-medium">Lookback</label>
                <select id="toi-lookback" class="border border-gray-300 rounded-md px-2.5 py-1.5 text-xs bg-white focus:outline-hidden focus:ring-1 focus:ring-red-600">
                    <option value="auto" selected>Auto</option>
                    <option value="3">3 Bars</option>
                    <option value="4">4 Bars</option>
                    <option value="5">5 Bars</option>
                    <option value="8">8 Bars</option>
                </select>
            </div>

            {{-- Go Button --}}
            <button id="toi-go" class="bg-red-700 hover:bg-red-800 text-white font-bold px-5 py-1.5 rounded-md text-xs shadow-xs transition-colors cursor-pointer">
                Go
            </button>

            {{-- Change Strike Prices Button --}}
            <button id="toi-strikes-btn"
                    class="border border-red-700 text-red-700 hover:bg-red-50 font-semibold px-3.5 py-1.5 rounded-md text-xs transition-colors cursor-pointer flex items-center gap-1.5">
                <span>⚙ Strikes</span>
            </button>
        </div>

        <div class="flex items-center gap-4 text-xs font-medium text-gray-700 pl-4 border-l border-gray-200">
            {{-- Show Graph View Checkbox --}}
            <label class="flex items-center gap-1.5 cursor-pointer select-none font-semibold text-gray-800">
                <input type="checkbox" id="toi-toggle-graph" class="accent-red-600 rounded">
                <span>Graph</span>
            </label>

            {{-- Graph Height Selector (visible when graph is enabled) --}}
            <div id="toi-graph-height-wrapper" class="hidden items-center gap-1.5">
                <select id="toi-graph-height" class="border border-gray-300 rounded-md px-2 py-1 text-xs bg-white">
                    <option value="420">420px</option>
                    <option value="520" selected>520px</option>
                    <option value="650">650px</option>
                </select>
            </div>

            {{-- Show / Hide Table Checkbox --}}
            <label class="flex items-center gap-1.5 cursor-pointer select-none font-semibold text-gray-800">
                <input type="checkbox" id="toi-toggle-table" checked class="accent-red-600 rounded">
                <span>Table</span>
            </label>

            {{-- Rows Limit Selector --}}
            <div id="toi-rows-limit-wrapper" class="flex items-center gap-1.5">
                <select id="toi-rows-limit" class="border border-gray-300 rounded-md px-2 py-1 text-xs bg-white">
                    <option value="15">15 r</option>
                    <option value="30">30 r</option>
                    <option value="50">50 r</option>
                    <option value="100">100 r</option>
                    <option value="all" selected>All</option>
                </select>
            </div>
        </div>
    </div>

    {{-- ══════════ DAILY HIGH-CONVICTION STRATEGY STATION (TOP RIBBON COCKPIT) ══════════ --}}
    <div id="toi-signal-banner" class="bg-slate-900 border-b border-slate-700 text-white shadow-xs transition-all duration-200">
        {{-- Clean Single-Line Summary Row --}}
        <div class="px-3 py-1.5 flex items-center justify-between gap-x-2.5 text-xs whitespace-nowrap overflow-x-auto">
            
            {{-- Left Group: Status Pill, Strategy & Anchor, Safe Band, Spot, Strikes Range, Target/Loss --}}
            <div class="flex items-center gap-2 min-w-0">
                {{-- Trade Status Badge --}}
                <div id="toi-station-badge" class="px-2 py-0.5 rounded-full text-[10.5px] font-bold tracking-wide uppercase flex items-center gap-1.5 shadow-xs bg-slate-700 text-slate-200 whitespace-nowrap">
                    <span id="toi-station-dot" class="w-2 h-2 rounded-full bg-slate-400 animate-pulse"></span>
                    <span id="toi-station-status-text">Scanning Edge...</span>
                </div>

                {{-- Strategy & Recommended Anchor Strike --}}
                <div id="toi-station-strat-pill" class="flex items-center gap-1.5 bg-slate-800/95 border border-slate-700 px-2 py-0.5 rounded text-[11px] whitespace-nowrap">
                    <span id="toi-station-strat-name" class="font-bold text-amber-400">Daily OAI V2</span>
                    <span class="text-slate-600">|</span>
                    <span id="toi-station-anchor-text" class="font-mono text-emerald-300 font-bold">Anchor: —</span>
                </div>

                {{-- Safe Spot Band --}}
                <div class="flex items-center gap-1 bg-slate-800/80 border border-slate-700/80 px-2 py-0.5 rounded text-[11px] text-slate-300 whitespace-nowrap">
                    <span class="text-slate-400 text-[10.5px]">Safe:</span>
                    <span id="toi-station-safe-band" class="font-mono font-semibold text-emerald-400">—</span>
                </div>

                {{-- Spot Info --}}
                <div id="toi-spot-pill" class="flex items-center gap-1 bg-slate-800/80 border border-slate-700/80 px-2 py-0.5 rounded text-[11px] whitespace-nowrap">
                    <span class="text-slate-400 text-[10.5px]">Spot:</span>
                    <span id="toi-spot-val" class="font-mono font-bold text-cyan-300">—</span>
                </div>

                {{-- Selected Strikes Summary --}}
                <div id="toi-strikes-pill" class="flex items-center gap-1 bg-slate-800/80 border border-slate-700/80 px-2 py-0.5 rounded text-[11px] whitespace-nowrap cursor-help" title="Selected Strikes">
                    <span class="text-slate-400 text-[10.5px]">Strikes:</span>
                    <span id="toi-selected-strikes-display" class="font-mono font-bold text-amber-200">—</span>
                </div>

                {{-- Target & Stop Loss --}}
                <div id="toi-station-pnl-chip" class="hidden xl:flex items-center gap-1.5 bg-slate-800/80 border border-slate-700 px-2 py-0.5 rounded text-[11px] whitespace-nowrap">
                    <span class="text-emerald-400 font-semibold font-mono" id="toi-station-target">🎯 Min: —</span>
                    <span class="text-slate-600">|</span>
                    <span class="text-rose-400 font-semibold font-mono" id="toi-station-stop">🛑 Max: —</span>
                </div>
            </div>

            {{-- Right Group: Action Buttons --}}
            <div class="flex items-center gap-1.5 ml-auto shrink-0">
                <button id="toi-station-logs-btn" class="flex items-center gap-1 bg-slate-800 hover:bg-slate-700 text-cyan-300 hover:text-cyan-200 border border-slate-700 hover:border-cyan-400 px-2 py-0.5 rounded text-[10.5px] font-bold transition-colors cursor-pointer select-none shadow-xs" title="View recorded strategy setup calls audit logs">
                    <span>📜 Signal Logs</span>
                    <span id="toi-calls-count-badge" class="ml-0.5 bg-cyan-950 text-cyan-300 text-[9px] px-1 py-0.2 rounded-full font-mono border border-cyan-800">0</span>
                </button>
                <button id="toi-station-buildup-btn" class="flex items-center gap-1 bg-slate-800 hover:bg-slate-700 text-amber-300 hover:text-amber-200 border border-slate-700 hover:border-amber-400 px-2 py-0.5 rounded text-[10.5px] font-bold transition-colors cursor-pointer select-none shadow-xs">
                    <span>🔥 OI Buildup</span>
                </button>
                <button id="toi-station-basket-btn" class="flex items-center gap-1 bg-emerald-700 hover:bg-emerald-600 text-white font-bold px-2 py-0.5 rounded text-[10.5px] shadow-xs transition-colors cursor-pointer select-none">
                    <span>👁️ 16-Leg Basket</span>
                </button>
                <button id="toi-signal-details-btn" class="flex items-center gap-1 bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white border border-slate-600 hover:border-slate-500 px-2 py-0.5 rounded text-[10.5px] font-medium transition-colors cursor-pointer select-none">
                    <span id="toi-signal-btn-text">Details</span>
                    <span id="toi-signal-btn-icon" class="text-[8px]">▼</span>
                </button>
            </div>
        </div>

        {{-- Expandable Detailed Drawer (Hidden by default) --}}
        <div id="toi-signal-details-drawer" class="hidden border-t border-slate-800 bg-slate-950/90 px-4 py-3 text-xs">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                {{-- Column 1: Institutional Rationale, Option Chain Walls & Full Strikes --}}
                <div class="space-y-1.5">
                    <div class="text-[11px] font-bold text-slate-300 flex items-center gap-1.5 uppercase tracking-wider">
                        <span>🔍 Flow Breakdown & Option Chain Walls</span>
                    </div>
                    <p id="toi-station-drawer-rationale" class="text-slate-200 text-xs font-medium leading-relaxed font-sans">
                        —
                    </p>
                    <div class="bg-slate-900/90 p-2 rounded border border-slate-800 text-[11px] space-y-1 font-mono">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Major PE Support:</span>
                            <span id="toi-drawer-support-wall" class="font-bold text-emerald-400">—</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Major CE Resistance:</span>
                            <span id="toi-drawer-resistance-wall" class="font-bold text-rose-400">—</span>
                        </div>
                        <div class="flex justify-between pt-1 border-t border-slate-800 text-slate-400 text-[10px]">
                            <span>Daily Trades Policy:</span>
                            <span class="text-amber-300 font-semibold">Max 1–2 (Selective / Sit in Cash)</span>
                        </div>
                    </div>
                    <div class="mt-2 text-[10.5px]">
                        <span class="text-slate-400 font-semibold">All Active Strikes:</span>
                        <div id="toi-selected-strikes-full" class="text-slate-200 font-mono mt-0.5 break-words bg-slate-900/70 p-1.5 rounded border border-slate-800 text-[10px] max-h-16 overflow-y-auto">—</div>
                    </div>
                </div>

                {{-- Column 2: Strategy Execution Guide --}}
                <div class="bg-slate-900/90 p-2.5 rounded-lg border border-slate-800 space-y-2">
                    <div class="flex items-center justify-between text-[11px]">
                        <span class="text-amber-400 font-bold uppercase tracking-wider flex items-center gap-1">
                            🎯 Option Seller Playbook
                        </span>
                        <span id="toi-station-drawer-badge" class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-800 text-slate-200 border border-slate-700">—</span>
                    </div>
                    <div class="space-y-1 text-[11px] font-sans">
                        <div class="text-slate-300">Action: <strong id="toi-station-drawer-action" class="text-emerald-400 font-semibold">—</strong></div>
                        <div class="text-slate-300">Anchor Skew: <span id="toi-station-drawer-anchor" class="text-amber-200 font-mono font-bold">—</span></div>
                        <div class="text-slate-300">Holding Duration: <span id="toi-station-drawer-duration" class="text-slate-200">—</span></div>
                    </div>
                    <div class="pt-1.5 border-t border-slate-800/80 text-[10px] text-slate-400 flex items-center justify-between">
                        <span>Target: <strong id="toi-drawer-target-text" class="text-emerald-400 font-semibold">—</strong></span>
                        <span>Max Stop: <strong id="toi-drawer-stop-text" class="text-rose-400 font-semibold">—</strong></span>
                    </div>
                </div>

                {{-- Column 3: Live Delta Momentum Matrix & Cutoff Clock --}}
                <div class="bg-slate-900/90 p-2.5 rounded-lg border border-slate-800 text-[11px] space-y-1.5">
                    <div class="flex items-center justify-between font-bold text-slate-300 uppercase tracking-wider text-[10px]">
                        <span>📊 3-Bar Delta Matrix</span>
                        <span class="text-amber-300 font-mono">Cutoff: 13:20 IST</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-[11px] pt-0.5 font-mono">
                        <div class="bg-slate-950/60 p-1.5 rounded border border-slate-800/80">
                            <div class="text-[9px] text-slate-400 uppercase">CE 3-Bar Delta</div>
                            <div id="toi-metric-ce-delta" class="font-bold text-slate-200">—</div>
                        </div>
                        <div class="bg-slate-950/60 p-1.5 rounded border border-slate-800/80">
                            <div class="text-[9px] text-slate-400 uppercase">PE 3-Bar Delta</div>
                            <div id="toi-metric-pe-delta" class="font-bold text-slate-200">—</div>
                        </div>
                        <div class="bg-slate-950/60 p-1.5 rounded border border-slate-800/80">
                            <div class="text-[9px] text-slate-400 uppercase">PCR Momentum</div>
                            <div id="toi-metric-pcr-mom" class="font-bold text-slate-200">—</div>
                        </div>
                        <div class="bg-slate-950/60 p-1.5 rounded border border-slate-800/80">
                            <div class="text-[9px] text-slate-400 uppercase">Rule #1</div>
                            <div class="font-bold text-emerald-400">Capital Guard</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════ GRAPHS SECTION (SHOW GRAPH VIEW) ══════════ --}}
    <div id="toi-graph-container" class="hidden p-4 bg-gray-100 border-b border-gray-300">
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4" id="toi-charts-grid">
            {{-- Graph 1: Trending OI --}}
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3 border-b border-gray-100 pb-2">
                        <h3 class="text-sm font-bold text-gray-800 tracking-wide flex items-center gap-1.5">
                            📈 Trending OI
                        </h3>
                        <div class="flex flex-wrap items-center gap-2 text-[10.5px]">
                            <span class="flex items-center gap-1 text-green-700 font-semibold"><span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span> Call OI</span>
                            <span class="flex items-center gap-1 text-red-600 font-semibold"><span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span> Put OI</span>
                            <span class="flex items-center gap-1 text-cyan-600 font-semibold"><span class="w-2.5 h-0.5 border-t-2 border-dashed border-cyan-500 inline-block"></span> Spot</span>
                        </div>
                    </div>
                    <div id="toi-chart-oi-wrapper" class="relative w-full h-[520px]">
                        <canvas id="toi-chart-oi"></canvas>
                    </div>
                </div>
            </div>

            {{-- Graph 2: Trending OI Sentiment --}}
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3 border-b border-gray-100 pb-2">
                        <h3 class="text-sm font-bold text-gray-800 tracking-wide flex items-center gap-1.5">
                            📊 Trending OI Sentiment
                        </h3>
                        <div class="flex flex-wrap items-center gap-2 text-[10.5px]">
                            <span class="flex items-center gap-1 text-red-600 font-semibold"><span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span> Sentiment</span>
                            <span class="flex items-center gap-1 text-cyan-600 font-semibold"><span class="w-2.5 h-0.5 border-t-2 border-dashed border-cyan-500 inline-block"></span> Spot</span>
                        </div>
                    </div>
                    <div id="toi-chart-sentiment-wrapper" class="relative w-full h-[520px]">
                        <canvas id="toi-chart-sentiment"></canvas>
                    </div>
                </div>
            </div>

            {{-- Graph 3: OI Buildup (Top 10 CE & PE - SB, LB, LU, SC across 5M, 15M, 30M, Today) --}}
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 flex flex-col justify-between" id="toi-buildup-card">
                <div>
                    <div class="flex items-center justify-between mb-2 border-b border-gray-100 pb-2">
                        <div class="flex items-center gap-1.5">
                            <h3 class="text-sm font-bold text-gray-800 tracking-wide flex items-center gap-1">
                                🔥 OI Buildup
                            </h3>
                            <span id="toi-buildup-window" class="text-[10px] bg-slate-100 text-slate-700 font-mono font-bold px-1.5 py-0.5 rounded border border-slate-200">
                                —
                            </span>
                        </div>
                        {{-- Timeframe Tabs (5M, 15M, 30M, Today) --}}
                        <div class="flex items-center gap-0.5 bg-gray-100 p-0.5 rounded text-[10.5px] font-semibold">
                            <button type="button" data-tf="5m" id="toi-buildup-tab-5m" class="toi-tf-tab px-2 py-0.5 rounded bg-white text-gray-900 shadow-xs cursor-pointer font-bold transition-colors">5M</button>
                            <button type="button" data-tf="15m" id="toi-buildup-tab-15m" class="toi-tf-tab px-2 py-0.5 rounded text-gray-500 hover:text-gray-900 cursor-pointer transition-colors">15M</button>
                            <button type="button" data-tf="30m" id="toi-buildup-tab-30m" class="toi-tf-tab px-2 py-0.5 rounded text-gray-500 hover:text-gray-900 cursor-pointer transition-colors">30M</button>
                            <button type="button" data-tf="today" id="toi-buildup-tab-today" class="toi-tf-tab px-2 py-0.5 rounded text-gray-500 hover:text-gray-900 cursor-pointer transition-colors">Today</button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-[11px] mb-1.5 px-1 text-slate-600">
                        <span id="toi-buildup-mode-desc" class="font-semibold text-slate-700">Top 8 Activity (5M)</span>
                        <div class="flex items-center gap-2.5 text-[10px] font-medium text-slate-600">
                            <span class="flex items-center gap-1" title="Short Buildup (Call/Put Writing)"><span class="w-2.5 h-2.5 rounded-xs bg-red-600 inline-block"></span> SB</span>
                            <span class="flex items-center gap-1" title="Short Covering (Short Exit)"><span class="w-2.5 h-2.5 rounded-xs bg-[#1e3a8a] inline-block"></span> SC</span>
                            <span class="flex items-center gap-1" title="Long Buildup (Call/Put Buying)"><span class="w-2.5 h-2.5 rounded-xs bg-green-600 inline-block"></span> LB</span>
                            <span class="flex items-center gap-1" title="Long Unwinding (Long Exit)"><span class="w-2.5 h-2.5 rounded-xs bg-yellow-500 inline-block"></span> LU</span>
                        </div>
                    </div>

                    <div id="toi-chart-buildup-wrapper" class="relative w-full h-[520px]">
                        <canvas id="toi-chart-buildup"></canvas>
                    </div>
                </div>

                {{-- Summary Footer --}}
                <div class="mt-2 pt-2 border-t border-gray-100 bg-gray-50/90 p-2 rounded text-[10.5px] flex flex-col gap-1 font-sans">
                    <div class="flex justify-between items-center text-slate-700 font-mono text-[10.5px]">
                        <span>CE Net Chg: <strong id="toi-buildup-ce-sum" class="text-red-600 font-bold">+0.0 L</strong></span>
                        <span>PE Net Chg: <strong id="toi-buildup-pe-sum" class="text-blue-600 font-bold">+0.0 L</strong></span>
                    </div>
                    <div class="text-[10px] font-medium text-slate-500 flex justify-between items-center">
                        <span>Dominant Flow:</span>
                        <span id="toi-buildup-dominant" class="font-bold text-slate-800 text-[10px] truncate max-w-[210px] text-right">—</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════ 5-MINUTE TOP OI BUILDUP MATRIX SECTION ══════════ --}}
        <div class="mt-4 bg-white rounded-lg shadow-sm border border-gray-200 p-4" id="toi-buildup-matrix-card">
            {{-- Header & Controls --}}
            <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-bold text-gray-800 tracking-wide flex items-center gap-1.5">
                        ⚡ 5-Minute Top OI Buildup Matrix
                    </h3>
                    <span id="toi-matrix-count" class="text-[11px] bg-slate-100 text-slate-700 font-mono font-bold px-2 py-0.5 rounded border border-slate-200">
                        0 Strikes Active
                    </span>
                    <span class="text-[11px] text-gray-500 font-sans hidden sm:inline">
                        (Top 5 Buildups per 5-min interval, Recent → Old)
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    {{-- Search Strike Filter --}}
                    <div class="relative">
                        <input type="text" id="toi-matrix-search" placeholder="Filter strike (e.g. 25000, CE)..." 
                               class="text-xs bg-gray-50 border border-gray-300 rounded px-2.5 py-1 text-gray-700 focus:outline-none focus:ring-1 focus:ring-red-500 w-44">
                    </div>

                    {{-- Type Filter Tabs --}}
                    <div class="flex items-center gap-0.5 bg-gray-100 p-0.5 rounded text-[11px] font-semibold">
                        <button type="button" data-matrix-filter="ALL" class="toi-matrix-tab px-2.5 py-0.5 rounded bg-white text-gray-900 shadow-xs cursor-pointer font-bold transition-colors">ALL</button>
                        <button type="button" data-matrix-filter="CE" class="toi-matrix-tab px-2.5 py-0.5 rounded text-gray-500 hover:text-gray-900 cursor-pointer transition-colors">CE Only</button>
                        <button type="button" data-matrix-filter="PE" class="toi-matrix-tab px-2.5 py-0.5 rounded text-gray-500 hover:text-gray-900 cursor-pointer transition-colors">PE Only</button>
                    </div>

                    {{-- Datewise Highlight Threshold Selector --}}
                    <div class="flex items-center gap-1.5 bg-amber-50 border border-amber-300 rounded px-2 py-0.5 shadow-2xs">
                        <span class="text-[10.5px] font-bold text-amber-900 flex items-center gap-1">
                            ⚡ Highlight:
                        </span>
                        <select id="toi-matrix-threshold-select" class="text-[11px] bg-transparent border-none font-bold text-amber-900 focus:outline-none cursor-pointer py-0.5 pr-1">
                            <option value="auto">Auto (Day Rule)</option>
                            <option value="10">Wed (≥ 10L)</option>
                            <option value="15">Thu (≥ 15L)</option>
                            <option value="20">Fri/Mon (≥ 20L)</option>
                            <option value="25">Tue Expiry (≥ 25L)</option>
                            <option value="5">Low (≥ 5L)</option>
                            <option value="30">High (≥ 30L)</option>
                        </select>
                        <span id="toi-matrix-threshold-badge" class="text-[10px] font-extrabold px-1.5 py-0.2 rounded bg-amber-200/80 text-amber-900 border border-amber-300 font-mono">
                            Auto
                        </span>
                    </div>

                    {{-- CE / PE Row Color Indicators --}}
                    <div class="flex items-center gap-2 text-[10.5px] font-bold text-slate-700 bg-slate-50 border border-slate-200 px-2.5 py-1 rounded">
                        <span class="flex items-center gap-1.5"><span class="w-3 h-2 rounded-xs bg-[#edfcf2] border-l-3 border-emerald-600 inline-block"></span> CE Rows</span>
                        <span class="flex items-center gap-1.5 ml-1"><span class="w-3 h-2 rounded-xs bg-[#eef4ff] border-l-3 border-blue-600 inline-block"></span> PE Rows</span>
                    </div>

                    {{-- Color Legend Pills --}}
                    <div class="flex items-center gap-2 text-[10.5px] font-bold text-slate-700 bg-slate-50 border border-slate-200 px-2.5 py-1 rounded">
                        <span class="flex items-center gap-1" title="Short Buildup (Call/Put Writing)"><span class="w-2.5 h-2.5 rounded-xs bg-[#dc2626] inline-block"></span> SB (Short Buildup)</span>
                        <span class="flex items-center gap-1" title="Short Covering (Short Exit)"><span class="w-2.5 h-2.5 rounded-xs bg-[#1e3a8a] inline-block"></span> SC (Short Covering)</span>
                        <span class="flex items-center gap-1" title="Long Buildup (Call/Put Buying)"><span class="w-2.5 h-2.5 rounded-xs bg-[#16a34a] inline-block"></span> LB (Long Buildup)</span>
                        <span class="flex items-center gap-1" title="Long Unwinding (Long Exit)"><span class="w-2.5 h-2.5 rounded-xs bg-[#eab308] inline-block"></span> LU (Long Unwinding)</span>
                        <span class="flex items-center gap-1 border-l border-slate-200 pl-1.5 ml-0.5 text-amber-800" title="Threshold met (Cell ring + Time bar highlight)"><span class="w-2.5 h-2.5 rounded-xs ring-2 ring-amber-400 bg-red-600 inline-block"></span> High OI (★)</span>
                    </div>
                </div>
            </div>

            {{-- Table Container with Sticky Strike & Total OI columns --}}
            <div class="overflow-x-auto w-full mt-3 rounded border border-gray-200" style="max-height: 520px; overflow-y: auto;">
                <table id="toi-matrix-table" class="w-full border-collapse text-[11px] font-mono whitespace-nowrap text-center">
                    <thead class="sticky top-0 z-20 bg-gray-100 text-gray-700 font-bold border-b border-gray-300 shadow-sm">
                        <tr id="toi-matrix-header-row" class="h-9">
                            <th class="sticky left-0 top-0 z-30 bg-gray-100 border-r border-gray-300 px-3 py-2 text-left font-bold text-slate-800 min-w-[100px] w-[100px] shadow-sm">Strike</th>
                            <th class="sticky left-[100px] top-0 z-30 bg-gray-100 border-r-2 border-slate-300 px-3 py-2 text-right font-bold text-slate-800 min-w-[90px] w-[90px] shadow-sm">Total OI</th>
                        </tr>
                    </thead>
                    <tbody id="toi-matrix-tbody" class="divide-y divide-gray-100 bg-white">
                        <tr>
                            <td colspan="10" class="text-center py-8 text-gray-400 font-sans text-xs">
                                Loading 5-minute buildup matrix…
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ══════════ LOADING INDICATOR ══════════ --}}
    <div id="toi-loading" class="text-center py-12 text-gray-500 text-sm hidden">
        <svg class="animate-spin inline h-6 w-6 mr-2 text-red-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        Fetching Trending OI data…
    </div>

    {{-- ══════════ ERROR BAR ══════════ --}}
    <div id="toi-error" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-2 text-sm rounded mx-4 my-2"></div>

    {{-- ══════════ DATA TABLE CONTAINER ══════════ --}}
    <div id="toi-table-container" class="w-full">
        <div class="overflow-x-auto w-full" id="toi-table-wrap" style="max-height: calc(100vh - 170px); overflow-y: auto;">
            <table id="toi-table" class="w-full border-collapse text-[11px] font-mono whitespace-nowrap text-right" style="min-width: 1550px;">
                <thead class="sticky top-0 z-20 bg-gray-50 text-gray-600 font-bold border-b border-gray-300 shadow-sm">
                    <tr class="h-9">
                        <th class="py-1 px-2 text-center border-r border-gray-200 w-10">#</th>
                        <th class="py-1 px-2 text-center border-r border-gray-200 w-24">Date</th>
                        <th class="py-1 px-2 text-center border-r border-gray-200 w-20">Time</th>
                        <th class="py-1 px-2 text-center border-r border-gray-200 w-28">Day H/L Break</th>
                        <th class="py-1 px-2 text-right border-r border-gray-200">Chng. In Call OI</th>
                        <th class="py-1 px-2 text-right border-r border-gray-200">Chng. In Put OI</th>
                        <th class="py-1 px-2 text-right border-r border-gray-200">Diff. in OI</th>
                        <th class="py-1 px-2 text-center border-r border-gray-200 w-20">Strength</th>
                        <th class="py-1 px-2 text-center border-r border-gray-200 w-24">Direction of chng.</th>
                        <th class="py-1 px-2 text-right border-r border-gray-200">Chng. In Direction</th>
                        <th class="py-1 px-2 text-center border-r border-gray-200 w-36">OI Trigger / Tag</th>
                        <th class="py-1 px-2 text-center border-r border-gray-200 w-44">Sentiment / Outlook (30m–1h)</th>
                        <th class="py-1 px-2 text-right border-r border-gray-200">Total Call Ltp</th>
                        <th class="py-1 px-2 text-right border-r border-gray-200">Call ltp chng.</th>
                        <th class="py-1 px-2 text-right border-r border-gray-200">CE + PE ltp Chng.</th>
                        <th class="py-1 px-2 text-right border-r border-gray-200">Put ltp chng.</th>
                        <th class="py-1 px-2 text-right border-r border-gray-200">Total Put Ltp</th>
                        <th class="py-1 px-2 text-center border-r border-gray-200 w-16">Net PCR</th>
                        <th class="py-1 px-2 text-center w-24">Day H/L Diff. in OI</th>
                    </tr>
                </thead>
                <tbody id="toi-tbody">
                    <tr>
                        <td colspan="19" class="text-center py-10 text-gray-400 font-sans text-xs">
                            Loading Trending OI data…
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="px-4 py-2 bg-gray-50 border-t border-gray-200 text-gray-500 text-xs flex justify-between items-center font-sans">
            <span id="toi-table-count-summary">Showing 0 rows</span>
            <span class="text-[11px] text-gray-400">Trending OI Table</span>
        </div>
    </div>

    {{-- ══════════ FLOATING LIVE ADVISOR (BOTTOM-RIGHT HUD) ══════════ --}}
    <div id="toi-floating-hud" class="fixed bottom-5 right-5 z-40 bg-slate-900/95 backdrop-blur border border-slate-700 text-white rounded-xl shadow-2xl p-3 w-80 transition-all duration-300 text-xs select-none">
        <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-800">
            <div class="flex items-center gap-2 font-bold text-xs text-gray-100">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                <span>Live OI Assistant</span>
            </div>
            <div class="flex items-center gap-2">
                <span id="toi-hud-timer" class="text-[10px] font-mono text-gray-400">Next: 60s</span>
                <button id="toi-hud-toggle" class="text-gray-400 hover:text-white text-xs px-1 rounded bg-slate-800 hover:bg-slate-700" title="Minimize / Expand">_</button>
            </div>
        </div>

        <div id="toi-hud-body" class="space-y-2">
            <div>
                <div class="text-[9px] text-gray-400 uppercase tracking-wider font-semibold">Live Market Flow</div>
                <div id="toi-hud-state" class="font-bold text-emerald-400 text-xs mt-0.5">Analyzing...</div>
            </div>
            
            <div class="bg-slate-800/90 p-2 rounded border border-slate-700">
                <div class="text-[9px] text-amber-400 font-bold uppercase tracking-wider">Seller Strategy</div>
                <div id="toi-hud-seller-action" class="font-semibold text-gray-100 text-xs mt-0.5">—</div>
                <div id="toi-hud-strikes" class="text-[11px] font-mono text-amber-300 mt-1 font-semibold">—</div>
            </div>

            <p id="toi-hud-reason" class="text-[10px] text-gray-300 leading-snug line-clamp-2 font-sans">
                Monitoring rolling high/low OI breaks and PE absorption...
            </p>
        </div>
    </div>

</div>

{{-- ══════════ STRIKE PRICES SELECTION MODAL ══════════ --}}
<div id="toi-strike-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-lg shadow-2xl w-[680px] max-w-full max-h-[90vh] flex flex-col relative overflow-hidden">
        {{-- Header --}}
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-base font-bold text-gray-800">Select strike prices</h2>
            <button id="toi-modal-close" class="text-gray-400 hover:text-gray-700 text-xl font-bold leading-none">&times;</button>
        </div>

        {{-- Body --}}
        <div class="p-6 overflow-y-auto flex-1 space-y-4 text-xs">
            <div>
                <span class="font-semibold text-gray-700">Total Strike Prices:</span>
                <span id="toi-modal-total-count" class="font-bold text-red-700 ml-1">0</span>
            </div>

            <div>
                <span class="font-semibold text-gray-700 block mb-1">Currently Selected Strike Prices:</span>
                <div id="toi-modal-selected-summary" class="text-gray-600 font-mono text-[11px] leading-relaxed bg-gray-50 p-2 rounded border border-gray-200 max-h-20 overflow-y-auto">
                    —
                </div>
            </div>

            {{-- Quick action buttons --}}
            <div class="flex flex-wrap gap-2 pt-1">
                <button id="toi-modal-clear" class="bg-red-700 hover:bg-red-800 text-white font-semibold px-3 py-1.5 rounded text-xs transition-colors shadow-sm">
                    Clear all selection
                </button>
                <button id="toi-modal-reset" class="bg-red-700 hover:bg-red-800 text-white font-semibold px-3 py-1.5 rounded text-xs transition-colors shadow-sm">
                    Reset strike prices (15 ATM)
                </button>
                <button id="toi-modal-select-all" class="bg-red-700 hover:bg-red-800 text-white font-semibold px-3 py-1.5 rounded text-xs transition-colors shadow-sm">
                    Select All strike prices
                </button>
            </div>

            <hr class="border-gray-200">

            {{-- Checkbox grid --}}
            <div>
                <p class="font-semibold text-gray-700 mb-2">Select Strike Prices:</p>
                <div id="toi-modal-grid" class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-2 max-h-60 overflow-y-auto p-1 font-mono text-[11px]">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="px-6 py-3 border-t border-gray-200 flex justify-end gap-3 bg-gray-50">
            <button id="toi-modal-cancel" class="border border-gray-300 text-gray-700 hover:bg-gray-100 px-4 py-1.5 rounded text-xs font-semibold">
                Cancel
            </button>
            <button id="toi-modal-apply" class="bg-red-700 hover:bg-red-800 text-white px-5 py-1.5 rounded text-xs font-bold shadow-sm">
                OK
            </button>
        </div>
    </div>
</div>

{{-- ══════════ 16-LEG STRATEGY BASKET MODAL ══════════ --}}
<div id="toi-basket-modal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl border border-slate-200 w-full max-w-3xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div class="px-5 py-3.5 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
            <div class="flex items-center gap-2">
                <span class="text-emerald-400 font-bold text-sm">🎯 Execution Basket:</span>
                <span id="toi-modal-strat-name" class="font-bold text-white text-sm">Daily OAI V2</span>
                <span id="toi-modal-anchor-badge" class="bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 px-2 py-0.5 rounded text-xs font-mono">Anchor: —</span>
            </div>
            <button id="toi-modal-basket-close" class="text-slate-400 hover:text-white text-lg font-bold px-2 py-0.5 rounded hover:bg-slate-800 transition-colors cursor-pointer">&times;</button>
        </div>
        
        <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
            {{-- Quick Summary Bar --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 bg-slate-50 p-3 rounded-lg border border-slate-200 text-xs font-sans">
                <div>
                    <span class="text-slate-500 block text-[10px] uppercase">Setup</span>
                    <strong id="toi-modal-setup" class="text-slate-800">—</strong>
                </div>
                <div>
                    <span class="text-slate-500 block text-[10px] uppercase">Safe Spot Band</span>
                    <strong id="toi-modal-safe-band" class="text-emerald-700 font-mono">—</strong>
                </div>
                <div>
                    <span class="text-slate-500 block text-[10px] uppercase">Min Target / Max Stop</span>
                    <strong id="toi-modal-pnl" class="text-indigo-700">+₹3,200 / -₹1,900</strong>
                </div>
                <div>
                    <span class="text-slate-500 block text-[10px] uppercase">Hard Cutoff</span>
                    <strong class="text-rose-600">13:20 IST</strong>
                </div>
            </div>

            {{-- 2 Column CE & PE Split --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- CE Table --}}
                <div class="border border-emerald-200 rounded-lg p-3 bg-emerald-50/20">
                    <div class="flex items-center justify-between pb-2 mb-2 border-b border-emerald-200 text-xs font-bold text-emerald-800">
                        <span>Call Options (CE Sold)</span>
                        <span id="toi-modal-ce-lots" class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded font-mono">8 Lots</span>
                    </div>
                    <table class="w-full text-xs font-mono">
                        <thead>
                            <tr class="text-slate-500 border-b border-emerald-100 text-[10px]">
                                <th class="text-left py-1">Leg</th>
                                <th class="text-right py-1">Strike</th>
                                <th class="text-right py-1">Lots</th>
                                <th class="text-right py-1">Side</th>
                            </tr>
                        </thead>
                        <tbody id="toi-modal-ce-tbody" class="divide-y divide-emerald-100">
                            <!-- Injected by JS -->
                        </tbody>
                    </table>
                </div>

                {{-- PE Table --}}
                <div class="border border-rose-200 rounded-lg p-3 bg-rose-50/20">
                    <div class="flex items-center justify-between pb-2 mb-2 border-b border-rose-200 text-xs font-bold text-rose-800">
                        <span>Put Options (PE Sold)</span>
                        <span id="toi-modal-pe-lots" class="bg-rose-100 text-rose-800 px-2 py-0.5 rounded font-mono">8 Lots</span>
                    </div>
                    <table class="w-full text-xs font-mono">
                        <thead>
                            <tr class="text-slate-500 border-b border-rose-100 text-[10px]">
                                <th class="text-left py-1">Leg</th>
                                <th class="text-right py-1">Strike</th>
                                <th class="text-right py-1">Lots</th>
                                <th class="text-right py-1">Side</th>
                            </tr>
                        </thead>
                        <tbody id="toi-modal-pe-tbody" class="divide-y divide-rose-100">
                            <!-- Injected by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
            <span class="text-[11px] text-slate-500">Execute on your broker terminal with these exact strikes & lot ratios.</span>
            <div class="flex items-center gap-2">
                <a id="toi-modal-builder-link" href="{{ route('backtests.basket-builder') }}" target="_blank" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs px-3 py-1.5 rounded-lg shadow-sm transition-colors flex items-center gap-1">
                    <span>Open in Basket Builder</span>
                    <span>↗</span>
                </a>
                <button id="toi-modal-footer-close" class="border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors cursor-pointer">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ══════════ STRATEGY CALL LOGS (VERTICAL TIMELINE & AUDIT JOURNAL) MODAL ══════════ --}}
<div id="toi-calls-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-3 sm:p-5" style="background-color: rgba(15, 23, 42, 0.75); backdrop-filter: blur(4px);">
    <div class="bg-white rounded-xl shadow-2xl border border-slate-300 w-full max-w-4xl flex flex-col font-sans overflow-hidden" style="height: 88vh; max-height: 88vh; display: flex; flex-direction: column;">
        {{-- Header (Fixed top) --}}
        <div class="px-5 py-3.5 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800 shrink-0">
            <div class="flex items-center gap-2.5">
                <span class="text-xl">📜</span>
                <div>
                    <h3 class="text-sm font-bold text-slate-100 flex items-center gap-2">
                        Strategy Signal Audit & Wave Journal
                        <span id="toi-calls-modal-count" class="bg-cyan-900 text-cyan-300 text-[10px] px-2 py-0.5 rounded-full font-mono font-bold">0 Signal Waves</span>
                    </h3>
                    <p class="text-[11px] text-slate-400">
                        Consolidated setup waves • Distinct trigger timestamps • Anchor skew & spot evolution • Anti-whip-saw audit
                    </p>
                </div>
            </div>
            <button id="toi-calls-modal-close" class="text-slate-400 hover:text-white text-lg font-bold p-1 hover:bg-slate-800 rounded transition-colors cursor-pointer">&times;</button>
        </div>

        {{-- Vertical Timeline Container (Smooth Internal Scroll) --}}
        <div class="flex-1 p-4 sm:p-5 bg-slate-100/90" style="min-height: 0; overflow-y: auto; -webkit-overflow-scrolling: touch;">
            <div id="toi-calls-timeline-list" class="space-y-4">
                <div class="py-16 text-center text-slate-400 font-sans">
                    <svg class="animate-spin inline h-6 w-6 mr-2 text-cyan-600 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <div class="text-xs font-medium">Loading consolidated signal wave journal...</div>
                </div>
            </div>
        </div>

        {{-- Pagination & Actions Footer (Fixed bottom) --}}
        <div class="px-5 py-3 bg-white border-t border-slate-200 flex flex-wrap items-center justify-between gap-3 text-xs shrink-0">
            <div class="flex items-center gap-2">
                <span id="toi-calls-page-info" class="text-slate-600 font-medium">Showing 0 of 0 Signals</span>
                <span class="text-slate-300">|</span>
                <span class="text-slate-400 text-[11px]">Hard Cutoff 13:20 IST</span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" id="toi-calls-prev-btn" disabled class="px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold rounded-md text-xs shadow-2xs disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors flex items-center gap-1">
                    <span>◀</span>
                    <span>Prev</span>
                </button>
                <span id="toi-calls-page-num" class="text-xs font-mono font-bold text-slate-800 px-2">Page 1 / 1</span>
                <button type="button" id="toi-calls-next-btn" disabled class="px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold rounded-md text-xs shadow-2xs disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors flex items-center gap-1">
                    <span>Next</span>
                    <span>▶</span>
                </button>
                <button type="button" id="toi-calls-modal-footer-close" class="border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-semibold px-4 py-1.5 rounded-md transition-colors cursor-pointer ml-2">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
/* ── Badges ──────────────────────────────────────────────────────── */
.badge-dlb { background: #dc2626; color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 10px; display: inline-block; }
.badge-dhb { background: #16a34a; color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 10px; display: inline-block; }

/* ── Predictive Sentiment & Outlook Badges ────────────────────────── */
.badge-outlook-squeeze    { background: linear-gradient(135deg, #059669, #0d9488); color: #fff; padding: 2px 7px; border-radius: 4px; font-weight: 800; font-size: 10px; box-shadow: 0 1px 2px rgba(5,150,105,0.25); display: inline-block; }
.badge-outlook-breakdown  { background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff; padding: 2px 7px; border-radius: 4px; font-weight: 800; font-size: 10px; box-shadow: 0 1px 2px rgba(220,38,38,0.25); display: inline-block; }
.badge-outlook-bullish    { background: #16a34a; color: #fff; padding: 2px 7px; border-radius: 4px; font-weight: 700; font-size: 10px; display: inline-block; }
.badge-outlook-bearish    { background: #dc2626; color: #fff; padding: 2px 7px; border-radius: 4px; font-weight: 700; font-size: 10px; display: inline-block; }
.badge-outlook-absorption { background: #d97706; color: #fff; padding: 2px 7px; border-radius: 4px; font-weight: 700; font-size: 10px; display: inline-block; }
.badge-outlook-strangle   { background: #4f46e5; color: #fff; padding: 2px 7px; border-radius: 4px; font-weight: 700; font-size: 10px; display: inline-block; }
.badge-outlook-unwind     { background: #64748b; color: #fff; padding: 2px 7px; border-radius: 4px; font-weight: 700; font-size: 10px; display: inline-block; }
.badge-outlook-decay      { background: #334155; color: #f8fafc; padding: 2px 7px; border-radius: 4px; font-weight: 600; font-size: 10px; display: inline-block; }

.badge-sentiment-bearish { background: #dc2626; color: #fff; padding: 3px 8px; border-radius: 4px; font-weight: 800; font-size: 10px; display: inline-block; }
.badge-sentiment-bullish { background: #16a34a; color: #fff; padding: 3px 8px; border-radius: 4px; font-weight: 800; font-size: 10px; display: inline-block; }

.badge-strength-neg { background: #dc2626; color: #fff; padding: 2px 8px; border-radius: 12px; font-weight: 700; font-size: 10px; display: inline-block; }
.badge-strength-pos { background: #16a34a; color: #fff; padding: 2px 8px; border-radius: 12px; font-weight: 700; font-size: 10px; display: inline-block; }

.badge-direction-down { background: #dc2626; color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 10px; display: inline-block; }
.badge-direction-up   { background: #16a34a; color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 10px; display: inline-block; }

/* ── Pattern Tags (Support = Green, Resistance = Red) ─────────────── */
.badge-pattern-support    { background: #15803d; color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 10px; display: inline-block; }
.badge-pattern-resistance { background: #dc2626; color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 10px; display: inline-block; }
.badge-pattern-default    { background: #475569; color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 600; font-size: 10px; display: inline-block; }

/* ── Floating HUD ────────────────────────────────────────────────── */
#toi-floating-hud.minimized { width: auto !important; padding: 6px 12px !important; }
#toi-floating-hud.minimized #toi-hud-body { display: none !important; }
#toi-floating-hud.minimized #toi-hud-timer { display: none !important; }

/* ── Table rows ─────────────────────────────────────────────────── */
.toi-row:hover { background-color: #fefce8 !important; }
.toi-row-even  { background-color: #ffffff; }
.toi-row-odd   { background-color: #f9fafb; }
.toi-td        { padding: 4px 8px; border-right: 1px solid #f1f5f9; white-space: nowrap; }

/* ── Custom Scrollbar ────────────────────────────────────────────── */
#toi-table-wrap { scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }
#toi-table-wrap::-webkit-scrollbar       { width: 6px; height: 6px; }
#toi-table-wrap::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }

/* ── Buildup Matrix Table CE / PE Distinction ───────────────────── */
.toi-matrix-row-ce { background-color: #f7fdf9; }
.toi-matrix-row-ce.toi-matrix-alt { background-color: #edfcf2; }
.toi-matrix-row-ce:hover, .toi-matrix-row-ce:hover td.sticky { background-color: #dcfce7 !important; }

.toi-matrix-row-pe { background-color: #f8faff; }
.toi-matrix-row-pe.toi-matrix-alt { background-color: #eef4ff; }
.toi-matrix-row-pe:hover, .toi-matrix-row-pe:hover td.sticky { background-color: #dbeafe !important; }

/* ── Matrix High Volume Highlight Styles ────────────────────────── */
.toi-matrix-th-highlight {
    background: linear-gradient(180deg, #fef3c7 0%, #fde68a 100%) !important;
    color: #78350f !important;
    border-bottom: 2px solid #f59e0b !important;
    box-shadow: inset 0 -2px 0 #d97706;
}
.toi-matrix-cell-highlight {
    background-color: rgba(254, 243, 199, 0.45) !important;
}
.toi-matrix-badge-highlight {
    box-shadow: 0 0 0 2px #f59e0b, 0 2px 4px rgba(0, 0, 0, 0.18) !important;
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';

    // ── State ───────────────────────────────────────────────────────────────
    let currentSelectedStrikes = [];
    let currentAllStrikes = [];
    let currentAtmStrike = null;
    let oiChartInstance = null;
    let sentimentChartInstance = null;
    let buildupChartInstance = null;
    let currentBuildupData = null;
    let currentBuildupTf = '5m'; // '5m', '15m', '30m', 'today'
    let autoRefreshTimer = null;
    let countdownInterval = null;
    let countdownRemaining = 60;
    let lastRawRows = [];

    // ── DOM References ──────────────────────────────────────────────────────
    const modeLive           = document.getElementById('toi-mode-live');
    const modeHist           = document.getElementById('toi-mode-history');
    const dateWrapper        = document.getElementById('toi-date-wrapper');
    const dateInput          = document.getElementById('toi-date');
    const expirySelect       = document.getElementById('toi-expiry');
    const intervalSelect     = document.getElementById('toi-interval');
    const lookbackSelect     = document.getElementById('toi-lookback');
    const underlyingSelect   = document.getElementById('toi-underlying');
    const goBtn              = document.getElementById('toi-go');
    const strikesBtn         = document.getElementById('toi-strikes-btn');
    const toggleGraph        = document.getElementById('toi-toggle-graph');
    const graphContainer     = document.getElementById('toi-graph-container');
    const graphHeightWrapper = document.getElementById('toi-graph-height-wrapper');
    const graphHeightSelect  = document.getElementById('toi-graph-height');
    const toggleTable        = document.getElementById('toi-toggle-table');
    const tableContainer     = document.getElementById('toi-table-container');
    const rowsLimitSelect    = document.getElementById('toi-rows-limit');
    const tableCountSummary  = document.getElementById('toi-table-count-summary');
    const loadingEl          = document.getElementById('toi-loading');
    const errorEl            = document.getElementById('toi-error');
    const tbody              = document.getElementById('toi-tbody');
    const strikesDisplay     = document.getElementById('toi-selected-strikes-display');
    const strikesFullDisplay = document.getElementById('toi-selected-strikes-full');
    const spotVal            = document.getElementById('toi-spot-val');

    // Strategy Station DOM References
    const stationBadge        = document.getElementById('toi-station-badge');
    const stationDot          = document.getElementById('toi-station-dot');
    const stationStatusText   = document.getElementById('toi-station-status-text');
    const stationStratPill    = document.getElementById('toi-station-strat-pill');
    const stationStratName    = document.getElementById('toi-station-strat-name');
    const stationAnchorText   = document.getElementById('toi-station-anchor-text');
    const stationSafeBand     = document.getElementById('toi-station-safe-band');
    const stationWalls        = document.getElementById('toi-station-walls');
    const stationPnlChip      = document.getElementById('toi-station-pnl-chip');
    const stationTarget       = document.getElementById('toi-station-target');
    const stationStop         = document.getElementById('toi-station-stop');
    const stationBasketBtn    = document.getElementById('toi-station-basket-btn');
    const stationDrawerRationale = document.getElementById('toi-station-drawer-rationale');
    const drawerSupportWall   = document.getElementById('toi-drawer-support-wall');
    const drawerResistanceWall= document.getElementById('toi-drawer-resistance-wall');
    const stationDrawerBadge  = document.getElementById('toi-station-drawer-badge');
    const stationDrawerAction = document.getElementById('toi-station-drawer-action');
    const stationDrawerAnchor = document.getElementById('toi-station-drawer-anchor');
    const stationDrawerDuration = document.getElementById('toi-station-drawer-duration');
    const drawerTargetText    = document.getElementById('toi-drawer-target-text');
    const drawerStopText      = document.getElementById('toi-drawer-stop-text');

    // 16-Leg Strategy Basket Modal DOM References
    const basketModal         = document.getElementById('toi-basket-modal');
    const modalStratName      = document.getElementById('toi-modal-strat-name');
    const modalAnchorBadge    = document.getElementById('toi-modal-anchor-badge');
    const modalBasketClose    = document.getElementById('toi-modal-basket-close');
    const modalFooterClose    = document.getElementById('toi-modal-footer-close');
    const modalSetup          = document.getElementById('toi-modal-setup');
    const modalSafeBand       = document.getElementById('toi-modal-safe-band');
    const modalPnl            = document.getElementById('toi-modal-pnl');
    const modalCeLots         = document.getElementById('toi-modal-ce-lots');
    const modalPeLots         = document.getElementById('toi-modal-pe-lots');
    const modalCeTbody        = document.getElementById('toi-modal-ce-tbody');
    const modalPeTbody        = document.getElementById('toi-modal-pe-tbody');
    const modalBuilderLink    = document.getElementById('toi-modal-builder-link');

    // Strategy Call Logs Modal DOM References
    const stationLogsBtn       = document.getElementById('toi-station-logs-btn');
    const callsCountBadge      = document.getElementById('toi-calls-count-badge');
    const callsModal           = document.getElementById('toi-calls-modal');
    const callsModalClose      = document.getElementById('toi-calls-modal-close');
    const callsModalFooterClose= document.getElementById('toi-calls-modal-footer-close');
    const callsModalCount      = document.getElementById('toi-calls-modal-count');
    const callsTimelineList    = document.getElementById('toi-calls-timeline-list');
    const callsPrevBtn         = document.getElementById('toi-calls-prev-btn');
    const callsNextBtn         = document.getElementById('toi-calls-next-btn');
    const callsPageInfo        = document.getElementById('toi-calls-page-info');
    const callsPageNum         = document.getElementById('toi-calls-page-num');

    // Drawer Toggle Elements
    const signalDetailsBtn    = document.getElementById('toi-signal-details-btn');
    const signalDetailsDrawer = document.getElementById('toi-signal-details-drawer');
    const signalBtnText       = document.getElementById('toi-signal-btn-text');
    const signalBtnIcon       = document.getElementById('toi-signal-btn-icon');
    const metricCeDelta       = document.getElementById('toi-metric-ce-delta');
    const metricPeDelta       = document.getElementById('toi-metric-pe-delta');
    const metricPcrMom        = document.getElementById('toi-metric-pcr-mom');

    // OI Buildup (Multi-Timeframe 5M, 15M, 30M, Today) DOM References
    const stationBuildupBtn   = document.getElementById('toi-station-buildup-btn');
    const buildupCard         = document.getElementById('toi-buildup-card');
    const buildupWindow       = document.getElementById('toi-buildup-window');
    const buildupTfTabs       = document.querySelectorAll('.toi-tf-tab');
    const buildupModeDesc     = document.getElementById('toi-buildup-mode-desc');
    const buildupCeSum        = document.getElementById('toi-buildup-ce-sum');
    const buildupPeSum        = document.getElementById('toi-buildup-pe-sum');
    const buildupDominant     = document.getElementById('toi-buildup-dominant');

    // 5-Minute Top OI Buildup Matrix DOM References
    const matrixCard             = document.getElementById('toi-buildup-matrix-card');
    const matrixCountBadge       = document.getElementById('toi-matrix-count');
    const matrixSearchInput      = document.getElementById('toi-matrix-search');
    const matrixFilterTabs       = document.querySelectorAll('.toi-matrix-tab');
    const matrixThresholdSelect  = document.getElementById('toi-matrix-threshold-select');
    const matrixThresholdBadge   = document.getElementById('toi-matrix-threshold-badge');
    const matrixHeaderRow        = document.getElementById('toi-matrix-header-row');
    const matrixTbody            = document.getElementById('toi-matrix-tbody');

    let currentMatrixData        = null;
    let currentMatrixFilter      = 'ALL';
    let currentMatrixSearch      = '';
    let currentMatrixThreshold   = 'auto';

    // Floating HUD DOM References
    const floatingHud         = document.getElementById('toi-floating-hud');
    const hudToggle           = document.getElementById('toi-hud-toggle');
    const hudTimer            = document.getElementById('toi-hud-timer');
    const hudState            = document.getElementById('toi-hud-state');
    const hudSellerAction     = document.getElementById('toi-hud-seller-action');
    const hudStrikes          = document.getElementById('toi-hud-strikes');
    const hudReason           = document.getElementById('toi-hud-reason');

    let currentStationData    = null;

    // Modal DOM
    const modal            = document.getElementById('toi-strike-modal');
    const modalClose       = document.getElementById('toi-modal-close');
    const modalCancel      = document.getElementById('toi-modal-cancel');
    const modalApply       = document.getElementById('toi-modal-apply');
    const modalClear       = document.getElementById('toi-modal-clear');
    const modalReset       = document.getElementById('toi-modal-reset');
    const modalSelectAll   = document.getElementById('toi-modal-select-all');
    const modalTotalCount  = document.getElementById('toi-modal-total-count');
    const modalSummary     = document.getElementById('toi-modal-selected-summary');
    const modalGrid        = document.getElementById('toi-modal-grid');

    // ── Signal Drawer Toggle ─────────────────────────────────────────────────
    const LS_DRAWER_KEY = 'toi_signal_drawer_open_v2';
    if (signalDetailsBtn && signalDetailsDrawer) {
        const isDrawerSaved = localStorage.getItem(LS_DRAWER_KEY) === 'true';
        if (isDrawerSaved) {
            signalDetailsDrawer.classList.remove('hidden');
            if (signalBtnText) signalBtnText.textContent = 'Less';
            if (signalBtnIcon) signalBtnIcon.textContent = '▲';
        }

        signalDetailsBtn.addEventListener('click', () => {
            const isHidden = signalDetailsDrawer.classList.toggle('hidden');
            const isOpen = !isHidden;
            if (signalBtnText) signalBtnText.textContent = isOpen ? 'Less' : 'Details';
            if (signalBtnIcon) signalBtnIcon.textContent = isOpen ? '▲' : '▼';
            localStorage.setItem(LS_DRAWER_KEY, isOpen ? 'true' : 'false');
        });
    }

    // ── Floating HUD Toggle ──────────────────────────────────────────────────
    if (hudToggle && floatingHud) {
        hudToggle.addEventListener('click', () => {
            const isMin = floatingHud.classList.toggle('minimized');
            hudToggle.textContent = isMin ? '▲' : '_';
        });
    }

    // ── Mode Toggle ─────────────────────────────────────────────────────────
    function getMode() { return modeLive.checked ? 'live' : 'history'; }

    function applyModeUI() {
        const isLive = getMode() === 'live';
        dateWrapper.style.opacity = isLive ? '0.4' : '1';
        dateWrapper.style.pointerEvents = isLive ? 'none' : '';
        if (floatingHud) {
            floatingHud.style.display = isLive ? 'block' : 'none';
        }
    }

    modeLive.addEventListener('change', () => { applyModeUI(); reloadExpiries(); });
    modeHist.addEventListener('change', () => { applyModeUI(); reloadExpiries(); });

    @if($mode === 'history')
    modeHist.checked = true;
    @else
    modeLive.checked = true;
    @endif
    applyModeUI();

    // ── Graph Height Persistence ────────────────────────────────────────────
    const LS_GRAPH_HEIGHT_KEY = 'toi_graph_height_v2';
    const savedGraphHeight = localStorage.getItem(LS_GRAPH_HEIGHT_KEY);
    if (savedGraphHeight) {
        graphHeightSelect.value = savedGraphHeight;
    }

    function applyGraphHeight() {
        const h = (graphHeightSelect.value || '520') + 'px';
        const oiWrap = document.getElementById('toi-chart-oi-wrapper');
        const senWrap = document.getElementById('toi-chart-sentiment-wrapper');
        const bldWrap = document.getElementById('toi-chart-buildup-wrapper');
        if (oiWrap) oiWrap.style.height = h;
        if (senWrap) senWrap.style.height = h;
        if (bldWrap) bldWrap.style.height = h;
        if (oiChartInstance) oiChartInstance.resize();
        if (sentimentChartInstance) sentimentChartInstance.resize();
        if (buildupChartInstance) buildupChartInstance.resize();
    }

    graphHeightSelect.addEventListener('change', () => {
        localStorage.setItem(LS_GRAPH_HEIGHT_KEY, graphHeightSelect.value);
        applyGraphHeight();
    });

    // ── Graph View Toggle & Persistence ─────────────────────────────────────
    const LS_GRAPH_KEY = 'toi_show_graph_view_v2';
    const isGraphSaved = localStorage.getItem(LS_GRAPH_KEY);
    if (isGraphSaved === 'true') {
        toggleGraph.checked = true;
        graphContainer.classList.remove('hidden');
        graphHeightWrapper.classList.remove('hidden');
        graphHeightWrapper.classList.add('flex');
    }

    toggleGraph.addEventListener('change', () => {
        const isChecked = toggleGraph.checked;
        graphContainer.classList.toggle('hidden', !isChecked);
        graphHeightWrapper.classList.toggle('hidden', !isChecked);
        graphHeightWrapper.classList.toggle('flex', isChecked);
        localStorage.setItem(LS_GRAPH_KEY, isChecked ? 'true' : 'false');
        if (isChecked) {
            setTimeout(() => {
                applyGraphHeight();
            }, 50);
        }
    });

    // ── Show / Hide Table Persistence ───────────────────────────────────────
    const LS_TABLE_KEY = 'toi_show_table_v2';
    const isTableSaved = localStorage.getItem(LS_TABLE_KEY);
    if (isTableSaved !== null) {
        toggleTable.checked = isTableSaved === 'true';
        tableContainer.classList.toggle('hidden', !toggleTable.checked);
    }

    toggleTable.addEventListener('change', () => {
        const isChecked = toggleTable.checked;
        tableContainer.classList.toggle('hidden', !isChecked);
        localStorage.setItem(LS_TABLE_KEY, isChecked ? 'true' : 'false');
    });

    // ── Rows Limit Persistence ──────────────────────────────────────────────
    const LS_ROWS_LIMIT_KEY = 'toi_rows_limit_v2';
    const savedRowsLimit = localStorage.getItem(LS_ROWS_LIMIT_KEY);
    if (savedRowsLimit) {
        rowsLimitSelect.value = savedRowsLimit;
    }

    rowsLimitSelect.addEventListener('change', () => {
        localStorage.setItem(LS_ROWS_LIMIT_KEY, rowsLimitSelect.value);
        renderTable(lastRawRows);
    });

    // ── Lookback Persistence ────────────────────────────────────────────────
    const LS_LOOKBACK_KEY = 'toi_lookback_v2';
    const savedLookback = localStorage.getItem(LS_LOOKBACK_KEY);
    if (savedLookback && lookbackSelect) {
        lookbackSelect.value = savedLookback;
    }
    if (lookbackSelect) {
        lookbackSelect.addEventListener('change', () => {
            localStorage.setItem(LS_LOOKBACK_KEY, lookbackSelect.value);
            fetchData();
        });
    }

    // ── Reload Expiries ─────────────────────────────────────────────────────
    function reloadExpiries() {
        const url = `{{ route('api.trending-oi.expiries') }}?mode=${getMode()}&date=${dateInput.value}&underlying=${underlyingSelect.value}`;
        fetch(url).then(r => r.json()).then(data => {
            const expiries = data.expiries || [];
            const currentExp = expirySelect.value;
            expirySelect.innerHTML = '';
            expiries.forEach(exp => {
                const opt = document.createElement('option');
                opt.value = exp;
                const d = new Date(exp);
                opt.textContent = d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
                if (exp === currentExp) opt.selected = true;
                expirySelect.appendChild(opt);
            });
            if (expiries.length && !expiries.includes(currentExp)) {
                expirySelect.value = expiries[0];
            }
        }).catch(() => {});
    }
    dateInput.addEventListener('change', reloadExpiries);

    // ── Fetch Data ──────────────────────────────────────────────────────────
    function fetchData() {
        clearAutoRefresh();
        const expiry = expirySelect.value;
        if (!expiry) { showError('Please select an expiry date.'); return; }

        showLoading(true);
        hideError();

        const params = new URLSearchParams({
            mode: getMode(),
            expiry: expiry,
            date: dateInput.value,
            underlying: underlyingSelect.value,
            interval: intervalSelect.value,
            lookback: lookbackSelect ? lookbackSelect.value : 'auto',
        });

        if (currentSelectedStrikes && currentSelectedStrikes.length > 0) {
            params.append('strikes', currentSelectedStrikes.join(','));
        }

        fetch(`{{ route('api.trending-oi.data') }}?${params}`)
            .then(r => r.json())
            .then(data => {
                showLoading(false);
                if (data.error) { showError(data.error); return; }

                currentSelectedStrikes = data.selected_strikes || [];
                currentAllStrikes      = data.all_strikes || [];
                currentAtmStrike       = data.atm_strike;

                renderHeaderInfo(data);
                renderStrategyStation(data.daily_strategy_station, data.signal_data);
                renderTable(data.rows || []);
                renderCharts(data.chart || {});
                renderStrikeBuildup(data.strike_buildup || { '5m': data.strike_buildup_5m });
                render5mBuildupMatrix(data.buildup_matrix || null);

                if (callsCountBadge && data.strategy_calls_count !== undefined) {
                    callsCountBadge.textContent = data.strategy_calls_count;
                }

                if (getMode() === 'live') scheduleAutoRefresh();
            })
            .catch(err => {
                showLoading(false);
                showError('Failed to load Trending OI data: ' + err.message);
            });
    }

    goBtn.addEventListener('click', fetchData);
    intervalSelect.addEventListener('change', fetchData);

    // ── Auto Refresh with Countdown ──────────────────────────────────────────
    function scheduleAutoRefresh() {
        clearAutoRefresh();
        countdownRemaining = 60;
        if (hudTimer) hudTimer.textContent = `Next: ${countdownRemaining}s`;

        countdownInterval = setInterval(() => {
            countdownRemaining--;
            if (countdownRemaining >= 0 && hudTimer) {
                hudTimer.textContent = `Next: ${countdownRemaining}s`;
            }
        }, 1000);

        autoRefreshTimer = setTimeout(() => {
            if (getMode() === 'live') fetchData();
        }, 60000);
    }

    function clearAutoRefresh() {
        if (autoRefreshTimer) { clearTimeout(autoRefreshTimer); autoRefreshTimer = null; }
        if (countdownInterval) { clearInterval(countdownInterval); countdownInterval = null; }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────
    function fmt(n, decimals = 0) {
        if (n === null || n === undefined) return '—';
        return Number(n).toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    }

    function fmtSigned(n, decimals = 0) {
        if (n === null || n === undefined) return '—';
        const v = Number(n);
        return (v > 0 ? '+' : '') + v.toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    }

    function showLoading(on) {
        loadingEl.classList.toggle('hidden', !on);
        tbody.classList.toggle('hidden', on);
    }
    function showError(msg) {
        errorEl.textContent = msg;
        errorEl.classList.remove('hidden');
    }
    function hideError() { errorEl.classList.add('hidden'); }

    // ── Render Header Info ───────────────────────────────────────────────────
    function renderHeaderInfo(data) {
        // Selected Strikes compact display for ribbon
        if (currentSelectedStrikes && currentSelectedStrikes.length > 0) {
            const sorted = [...currentSelectedStrikes].map(Number).sort((a, b) => a - b);
            const minS = sorted[0];
            const maxS = sorted[sorted.length - 1];
            const count = sorted.length;
            if (strikesDisplay) {
                strikesDisplay.textContent = `${fmt(minS)}–${fmt(maxS)} (${count})`;
                strikesDisplay.title = sorted.join(', ');
            }
            if (strikesFullDisplay) {
                strikesFullDisplay.textContent = sorted.join(', ');
            }
        } else {
            if (strikesDisplay) strikesDisplay.textContent = 'None';
            if (strikesFullDisplay) strikesFullDisplay.textContent = 'None';
        }

        // Spot info formatted cleanly for the single-line ribbon
        const u = data.underlying_data;
        if (u && spotVal) {
            const chgSign = u.change >= 0 ? '+' : '';
            const chgColor = u.change >= 0 ? 'text-emerald-400' : 'text-rose-400';
            spotVal.innerHTML = `<span class="text-cyan-300 font-bold">${fmt(u.spot, 2)}</span> <span class="${chgColor} font-semibold">(${chgSign}${fmt(u.change, 2)})</span>`;
            spotVal.title = `${u.name} spot ${fmt(u.spot, 2)}, Chg: ${chgSign}${fmt(u.change, 2)} (${chgSign}${u.change_pct}%) as on ${u.time}`;
        } else if (spotVal) {
            spotVal.textContent = '—';
        }
    }

    // ── Render Daily Strategy Station (Top Ribbon Cockpit) ───────────────────
    function renderStrategyStation(st, s) {
        if (!st) return;
        currentStationData = st;

        const colorMap = {
            emerald: { bg: 'bg-emerald-600 text-white', dot: 'bg-white', text: 'text-emerald-400' },
            rose:    { bg: 'bg-rose-600 text-white', dot: 'bg-white', text: 'text-rose-400' },
            blue:    { bg: 'bg-sky-600 text-white', dot: 'bg-white', text: 'text-sky-400' },
            amber:   { bg: 'bg-amber-600 text-white', dot: 'bg-amber-200', text: 'text-amber-400' },
            slate:   { bg: 'bg-slate-700 text-slate-200', dot: 'bg-slate-400', text: 'text-slate-300' },
        };

        const theme = colorMap[st.badge_color] || colorMap.slate;

        if (stationBadge) {
            stationBadge.className = `px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase flex items-center gap-1.5 shadow-sm whitespace-nowrap ${theme.bg}`;
        }
        if (stationDot) {
            stationDot.className = `w-2 h-2 rounded-full ${theme.dot} animate-pulse`;
        }
        if (stationStatusText) {
            stationStatusText.textContent = st.status_badge || 'Scanning Edge...';
        }

        if (stationStratName) {
            stationStratName.textContent = st.strategy_name || 'Daily OAI V2';
        }
        if (stationAnchorText) {
            if (st.recommended_anchor) {
                stationAnchorText.textContent = `Anchor: ${fmt(st.recommended_anchor)} (${st.anchor_skew || ''})`;
                stationAnchorText.className = 'font-mono text-emerald-300 font-bold';
            } else {
                stationAnchorText.textContent = 'Anchor: — (Flat)';
                stationAnchorText.className = 'font-mono text-slate-400';
            }
        }

        if (stationSafeBand) {
            stationSafeBand.textContent = st.safe_range_text || '—';
        }
        if (stationWalls) {
            stationWalls.textContent = `Supp: ${fmt(st.support_strike)} / Res: ${fmt(st.resistance_strike)}`;
        }

        if (stationTarget) {
            stationTarget.textContent = `🎯 Min: ${st.target_pnl || '—'}`;
        }
        if (stationStop) {
            stationStop.textContent = `🛑 Max: ${st.stop_loss_pnl || '—'}`;
        }

        // Toggle visibility of Basket button depending on recommended anchor
        if (stationBasketBtn) {
            if (st.recommended_anchor && st.basket_legs && st.basket_legs.length > 0) {
                stationBasketBtn.classList.remove('hidden');
            } else {
                stationBasketBtn.classList.add('hidden');
            }
        }

        // Drawer elements
        if (stationDrawerRationale) {
            stationDrawerRationale.textContent = st.rationale || st.headline || '—';
        }
        if (drawerSupportWall) {
            drawerSupportWall.textContent = `${fmt(st.support_strike)} PE Wall`;
        }
        if (drawerResistanceWall) {
            drawerResistanceWall.textContent = `${fmt(st.resistance_strike)} CE Wall`;
        }
        if (stationDrawerBadge) {
            stationDrawerBadge.textContent = st.setup_title || '—';
        }
        if (stationDrawerAction) {
            stationDrawerAction.textContent = st.action_label || '—';
        }
        if (stationDrawerAnchor) {
            stationDrawerAnchor.textContent = st.recommended_anchor ? `${fmt(st.recommended_anchor)} (${st.anchor_skew})` : 'None (Flat)';
        }
        if (stationDrawerDuration) {
            stationDrawerDuration.textContent = st.expected_duration || 'Flat';
        }
        if (drawerTargetText) {
            drawerTargetText.textContent = st.target_pnl || '—';
        }
        if (drawerStopText) {
            drawerStopText.textContent = st.stop_loss_pnl || '—';
        }

        // 3-Bar Delta Momentum in Drawer
        if (s && s.metrics) {
            if (metricCeDelta) {
                const v = s.metrics.ce_delta_3bar || 0;
                metricCeDelta.textContent = fmtSigned(v);
                metricCeDelta.className = `font-bold ${v >= 0 ? 'text-green-400' : 'text-red-400'}`;
            }
            if (metricPeDelta) {
                const v = s.metrics.pe_delta_3bar || 0;
                metricPeDelta.textContent = fmtSigned(v);
                metricPeDelta.className = `font-bold ${v >= 0 ? 'text-green-400' : 'text-red-400'}`;
            }
            if (metricPcrMom) {
                const v = s.metrics.pcr_momentum || 0;
                metricPcrMom.textContent = (v > 0 ? '+' : '') + v;
                metricPcrMom.className = `font-bold ${v >= 0 ? 'text-green-400' : 'text-red-400'}`;
            }
        }

        // Floating HUD Elements
        if (hudState) {
            hudState.textContent = st.setup_title || (s ? s.state_title : 'Active');
            hudState.className = `font-bold text-xs mt-0.5 ${theme.text}`;
        }
        if (hudSellerAction) {
            hudSellerAction.textContent = st.action_label || (s ? s.seller_action : '—');
        }
        if (hudStrikes) {
            hudStrikes.textContent = st.recommended_anchor ? `Anchor ATM: ${fmt(st.recommended_anchor)}` : (s ? s.seller_strikes : '—');
        }
        if (hudReason) {
            hudReason.textContent = st.rationale || (s ? s.headline : '—');
        }
    }

    // ── Open 16-Leg Basket Modal ─────────────────────────────────────────────
    function openBasketModal() {
        if (!currentStationData || !basketModal) return;
        const st = currentStationData;

        if (modalStratName) modalStratName.textContent = st.strategy_name || 'Daily OAI V2';
        if (modalAnchorBadge) modalAnchorBadge.textContent = st.recommended_anchor ? `Anchor: ${fmt(st.recommended_anchor)}` : 'Anchor: —';
        if (modalSetup) modalSetup.textContent = st.setup_title || '—';
        if (modalSafeBand) modalSafeBand.textContent = st.safe_range_text || '—';
        if (modalPnl) modalPnl.textContent = `${st.target_pnl || '—'} / ${st.stop_loss_pnl || '—'}`;

        const legs = st.basket_legs || [];
        const ceLegs = legs.filter(l => l.option_type === 'CE');
        const peLegs = legs.filter(l => l.option_type === 'PE');

        const totalCeLots = ceLegs.reduce((acc, l) => acc + (l.lots || 0), 0);
        const totalPeLots = peLegs.reduce((acc, l) => acc + (l.lots || 0), 0);

        if (modalCeLots) modalCeLots.textContent = `${totalCeLots} Lots`;
        if (modalPeLots) modalPeLots.textContent = `${totalPeLots} Lots`;

        if (modalCeTbody) {
            modalCeTbody.innerHTML = ceLegs.map(l => `
                <tr class="hover:bg-emerald-50/50">
                    <td class="py-1.5 font-medium text-slate-700">#${l.leg_number}</td>
                    <td class="py-1.5 text-right font-bold text-emerald-800">${fmt(l.strike)}</td>
                    <td class="py-1.5 text-right font-semibold text-slate-800">${l.lots}</td>
                    <td class="py-1.5 text-right text-[10px] font-bold text-rose-600">${l.side}</td>
                </tr>
            `).join('');
        }

        if (modalPeTbody) {
            modalPeTbody.innerHTML = peLegs.map(l => `
                <tr class="hover:bg-rose-50/50">
                    <td class="py-1.5 font-medium text-slate-700">#${l.leg_number}</td>
                    <td class="py-1.5 text-right font-bold text-rose-800">${fmt(l.strike)}</td>
                    <td class="py-1.5 text-right font-semibold text-slate-800">${l.lots}</td>
                    <td class="py-1.5 text-right text-[10px] font-bold text-rose-600">${l.side}</td>
                </tr>
            `).join('');
        }

        if (modalBuilderLink) {
            modalBuilderLink.href = `{{ route('backtests.basket-builder') }}?strategy_id=${st.strategy_id || 3}&atm_strike=${st.recommended_anchor || ''}`;
        }

        basketModal.classList.remove('hidden');
    }

    if (stationBasketBtn) stationBasketBtn.addEventListener('click', openBasketModal);
    if (modalBasketClose) modalBasketClose.addEventListener('click', () => basketModal.classList.add('hidden'));
    if (modalFooterClose) modalFooterClose.addEventListener('click', () => basketModal.classList.add('hidden'));
    if (basketModal) {
        basketModal.addEventListener('click', (e) => {
            if (e.target === basketModal) basketModal.classList.add('hidden');
        });
    }

    // ── Strategy Call Logs Modal Handlers ────────────────────────────────────
    let currentCallsPage = 1;
    const callsPerPage = 5;

    function openCallsModal() {
        if (!callsModal) return;
        callsModal.classList.remove('hidden');
        callsModal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        currentCallsPage = 1;
        loadStrategyCalls(1);
    }

    function closeCallsModal() {
        if (!callsModal) return;
        callsModal.classList.add('hidden');
        callsModal.classList.remove('flex');
        document.body.style.overflow = '';
    }

    function loadStrategyCalls(page = 1) {
        if (!callsTimelineList) return;
        currentCallsPage = page;
        callsTimelineList.innerHTML = `
            <div class="py-16 text-center text-slate-400 font-sans">
                <svg class="animate-spin inline h-6 w-6 mr-2 text-cyan-600 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <div class="text-xs">Loading consolidated signal wave journal...</div>
            </div>
        `;

        const dateVal = dateInput ? dateInput.value : '';
        const undVal = underlyingSelect ? underlyingSelect.value : 'NSE_INDEX|Nifty 50';

        fetch(`{{ route('api.trending-oi.strategy-calls') }}?date=${dateVal}&underlying=${encodeURIComponent(undVal)}&page=${page}&per_page=${callsPerPage}`)
            .then(r => r.json())
            .then(data => {
                const calls = data.calls || [];
                const total = data.total_count || 0;
                const totalPages = data.total_pages || 1;

                if (callsModalCount) callsModalCount.textContent = `${total} Signal Waves`;
                if (callsCountBadge) callsCountBadge.textContent = total;

                if (callsPageInfo) {
                    callsPageInfo.textContent = total > 0 
                        ? `Showing ${data.from}–${data.to} of ${total} Signal Waves`
                        : '0 Signal Waves';
                }
                if (callsPageNum) {
                    callsPageNum.textContent = `Page ${data.current_page} / ${totalPages}`;
                }

                if (callsPrevBtn) {
                    callsPrevBtn.disabled = (data.current_page <= 1);
                }
                if (callsNextBtn) {
                    callsNextBtn.disabled = (data.current_page >= totalPages);
                }

                if (calls.length === 0) {
                    callsTimelineList.innerHTML = `
                        <div class="bg-white rounded-xl border border-slate-200 p-12 text-center text-slate-400 font-sans shadow-2xs">
                            <div class="text-3xl mb-2">☕</div>
                            <div class="text-sm font-semibold text-slate-700">No Signal Waves Recorded Yet</div>
                            <div class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Market is accumulating flow or operating in Capital Preservation Mode. No high-conviction trade setups triggered for this session.</div>
                        </div>
                    `;
                    return;
                }

                callsTimelineList.innerHTML = calls.map((c) => {
                    const borderCls = c.badge_color === 'emerald' ? 'border-l-emerald-500'
                                    : c.badge_color === 'rose'    ? 'border-l-rose-500'
                                    : c.badge_color === 'blue'    ? 'border-l-sky-500'
                                    : 'border-l-amber-500';

                    const badgeBg = c.badge_color === 'emerald' ? 'bg-emerald-500 text-white'
                                  : c.badge_color === 'rose'    ? 'bg-rose-500 text-white'
                                  : c.badge_color === 'blue'    ? 'bg-sky-600 text-white'
                                  : 'bg-amber-500 text-white';

                    const spotDelta = c.spot_change || 0;
                    const spotDeltaColor = spotDelta > 0 ? 'text-emerald-600' : (spotDelta < 0 ? 'text-rose-600' : 'text-slate-600');
                    const legsCount = c.basket_legs_count || 0;
                    const collapseId = `toi-call-legs-${c.id}`;

                    return `
                        <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden border-l-4 ${borderCls} transition-all hover:shadow-md">
                            {{-- Top Bar: Trigger Time, Active Window, Outcome Badge --}}
                            <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2 text-xs">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5 font-mono font-bold text-slate-900 bg-white px-2.5 py-0.5 rounded border border-slate-200 shadow-2xs">
                                        <span class="text-amber-500">⚡</span>
                                        <span>Signal Raised:</span>
                                        <span class="text-indigo-700 font-extrabold">${c.signal_time} IST</span>
                                    </span>
                                    <span class="text-slate-400 font-mono text-[11px]">${c.trade_date}</span>
                                    <span class="inline-flex items-center gap-1.5 font-mono text-[11px] bg-slate-200/80 text-slate-700 px-2 py-0.5 rounded">
                                        <span>⏱ Active Window:</span>
                                        <strong class="font-bold text-slate-800">${c.active_time_range}</strong>
                                        <span class="text-slate-500 font-semibold">(${c.duration_text})</span>
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    ${c.outcome_status === 'ACTIVE' ? `
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 animate-pulse">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            ACTIVE NOW
                                        </span>
                                    ` : `
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                            CONCLUDED
                                        </span>
                                    `}
                                </div>
                            </div>

                            {{-- Setup Title & Recommended Anchor Skew --}}
                            <div class="px-4 py-2 bg-slate-900 text-white flex flex-wrap items-center justify-between gap-2 text-xs font-sans">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold tracking-wide uppercase ${badgeBg}">
                                        ${c.setup_title}
                                    </span>
                                    <span class="text-slate-300 font-medium text-xs">${c.action_label}</span>
                                </div>
                                <div class="flex items-center gap-2 font-mono text-xs">
                                    <span class="text-slate-400 text-[11px]">Recommended Anchor:</span>
                                    <span class="font-bold text-amber-300 bg-slate-800 px-2 py-0.5 rounded border border-slate-700">
                                        ${c.recommended_anchor ? fmt(c.recommended_anchor) : '—'}
                                    </span>
                                    <span class="text-amber-200/80 text-[11px] font-sans">(${c.anchor_skew || 'ATM Center'})</span>
                                </div>
                            </div>

                            {{-- 4 Metric Tiles: Entry Spot, Last Spot, Spot Movement, Safe Band & Risk --}}
                            <div class="p-4 grid grid-cols-2 md:grid-cols-4 gap-3 bg-white border-b border-slate-100 text-xs">
                                <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-200/80">
                                    <span class="text-slate-500 text-[10px] uppercase block font-semibold">Entry Spot (At Signal)</span>
                                    <strong class="font-mono text-sm text-slate-800">${fmt(c.entry_spot, 2)}</strong>
                                </div>
                                <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-200/80">
                                    <span class="text-slate-500 text-[10px] uppercase block font-semibold">Exit / Latest Spot</span>
                                    <strong class="font-mono text-sm text-slate-800">${fmt(c.last_spot, 2)}</strong>
                                </div>
                                <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-200/80">
                                    <span class="text-slate-500 text-[10px] uppercase block font-semibold">Spot Movement</span>
                                    <strong class="font-mono text-sm ${spotDeltaColor}">${c.spot_change_text} pts</strong>
                                </div>
                                <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-200/80">
                                    <span class="text-slate-500 text-[10px] uppercase block font-semibold">Safe Band | Target</span>
                                    <div class="font-mono text-[11px] text-emerald-700 font-bold truncate" title="${c.safe_range_text}">${c.safe_range_text}</div>
                                    <div class="font-mono text-[10px] text-slate-500 truncate">${c.target_pnl || '—'} / ${c.stop_loss_pnl || '—'}</div>
                                </div>
                            </div>

                            {{-- Flow Rationale & Delta Metrics --}}
                            <div class="px-4 py-3 bg-slate-50/50 text-xs font-sans space-y-2">
                                <div class="flex items-start gap-2">
                                    <span class="text-indigo-600 font-bold shrink-0 mt-0.5">🧠 Flow Rationale:</span>
                                    <p class="text-slate-700 leading-relaxed font-normal">${c.rationale || 'Rule-based setup triggered based on institutional change in OI flows.'}</p>
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-slate-200/60">
                                    ${c.flow_metrics && Object.keys(c.flow_metrics).length > 0 ? `
                                        <div class="flex flex-wrap items-center gap-1.5 text-[10.5px] font-mono text-slate-600">
                                            <span class="bg-white px-2 py-0.5 rounded border border-slate-200 shadow-2xs">Net PCR: <strong class="text-indigo-900">${fmt(c.flow_metrics.net_pcr, 2)}</strong></span>
                                            <span class="bg-white px-2 py-0.5 rounded border border-slate-200 shadow-2xs">Direction: <strong class="text-slate-800">${c.flow_metrics.direction_pct}%</strong></span>
                                            <span class="bg-white px-2 py-0.5 rounded border border-slate-200 shadow-2xs">CE 3-Bar Δ: <strong class="${c.flow_metrics.ce_delta_3bar >= 0 ? 'text-green-600':'text-red-600'}">${fmtSigned(c.flow_metrics.ce_delta_3bar)}</strong></span>
                                            <span class="bg-white px-2 py-0.5 rounded border border-slate-200 shadow-2xs">PE 3-Bar Δ: <strong class="${c.flow_metrics.pe_delta_3bar >= 0 ? 'text-green-600':'text-red-600'}">${fmtSigned(c.flow_metrics.pe_delta_3bar)}</strong></span>
                                        </div>
                                    ` : '<div></div>'}

                                    ${legsCount > 0 ? `
                                        <button type="button" onclick="const el = document.getElementById('${collapseId}'); el.classList.toggle('hidden'); this.querySelector('span.arrow').textContent = el.classList.contains('hidden') ? '▼' : '▲';" class="text-indigo-600 hover:text-indigo-800 text-[11px] font-semibold flex items-center gap-1 cursor-pointer bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1 rounded transition-colors shadow-2xs">
                                            <span>👁️ View ${legsCount}-Leg Basket</span>
                                            <span class="arrow text-[8px]">▼</span>
                                        </button>
                                    ` : ''}
                                </div>

                                {{-- Collapsible 16-Leg Structure --}}
                                ${legsCount > 0 ? `
                                    <div id="${collapseId}" class="hidden mt-2 pt-2 border-t border-slate-200 animate-in fade-in duration-100">
                                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Pre-Calculated Execution Legs (Anchor ${c.recommended_anchor})</div>
                                        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-8 gap-1.5 font-mono text-[10.5px]">
                                            ${c.basket_legs.map(l => `
                                                <div class="p-1.5 rounded border ${l.option_type === 'CE' ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-rose-50 border-rose-200 text-rose-900'} text-center shadow-2xs">
                                                    <div class="font-extrabold">${l.strike} ${l.option_type}</div>
                                                    <div class="text-[9px] text-slate-500">${l.lots} Lots • ${l.side}</div>
                                                </div>
                                            `).join('')}
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                }).join('');
            })
            .catch(err => {
                callsTimelineList.innerHTML = `
                    <div class="bg-rose-50 text-rose-700 p-6 rounded-xl border border-rose-200 text-center text-xs">
                        Failed to load strategy call logs: ${err.message}
                    </div>
                `;
            });
    }

    if (callsPrevBtn) {
        callsPrevBtn.addEventListener('click', () => {
            if (currentCallsPage > 1) {
                loadStrategyCalls(currentCallsPage - 1);
            }
        });
    }
    if (callsNextBtn) {
        callsNextBtn.addEventListener('click', () => {
            loadStrategyCalls(currentCallsPage + 1);
        });
    }

    if (stationLogsBtn) stationLogsBtn.addEventListener('click', openCallsModal);
    if (callsModalClose) callsModalClose.addEventListener('click', closeCallsModal);
    if (callsModalFooterClose) callsModalFooterClose.addEventListener('click', closeCallsModal);
    if (callsModal) {
        callsModal.addEventListener('click', (e) => {
            if (e.target === callsModal) closeCallsModal();
        });
    }

    // ── Render Table Rows (with limit slicing) ──────────────────────────────
    function renderTable(rows) {
        lastRawRows = rows || [];
        if (!rows || rows.length === 0) {
            tbody.innerHTML = `<tr><td colspan="19" class="text-center py-10 text-gray-400 font-sans text-xs">No records found for the selected parameters.</td></tr>`;
            if (tableCountSummary) tableCountSummary.textContent = 'Showing 0 rows';
            return;
        }

        let displayRows = rows;
        const limitVal = rowsLimitSelect.value;
        if (limitVal !== 'all') {
            const limitNum = parseInt(limitVal, 10);
            displayRows = rows.slice(0, limitNum);
        }

        if (tableCountSummary) {
            tableCountSummary.textContent = `Showing ${displayRows.length} of ${rows.length} rows (latest first)`;
        }

        let html = '';
        displayRows.forEach((r, idx) => {
            const rowCls = (idx % 2 === 0) ? 'toi-row-even' : 'toi-row-odd';

            // Day H/L Break badge
            let dayHlBadge = '-';
            if (r.day_hl_break && r.day_hl_break !== '-') {
                const isHigh = r.day_hl_break.startsWith('D.H.B');
                dayHlBadge = `<span class="${isHigh ? 'badge-dhb' : 'badge-dlb'}">${r.day_hl_break}</span>`;
            }

            // Strength Badge
            const strengthVal = r.strength;
            const strengthCls = strengthVal >= 0 ? 'badge-strength-pos' : 'badge-strength-neg';
            const strengthBadge = `<span class="${strengthCls}">${strengthVal}%</span>`;

            // Direction of Change
            const dirVal = r.direction_pct;
            let dirBadge = '—';
            if (dirVal !== 0) {
                const isUp = dirVal > 0;
                dirBadge = `<span class="${isUp ? 'badge-direction-up' : 'badge-direction-down'}">${isUp ? '↑' : '↓'} ${fmt(Math.abs(dirVal), 2)}%</span>`;
            }

            // Day H/L Diff in OI Badge
            let dayHlDiffBadge = '-';
            if (r.day_hl_diff_oi && r.day_hl_diff_oi !== '-') {
                const isHigh = r.day_hl_diff_oi === 'D.H.B';
                dayHlDiffBadge = `<span class="${isHigh ? 'badge-dhb' : 'badge-dlb'}">${r.day_hl_diff_oi}</span>`;
            }

            // Pattern Tag Badge: Support in Green, Resistance in Red
            let patternBadge = '<span class="text-gray-400 font-sans text-[10px]">—</span>';
            if (r.pattern_tag && r.pattern_tag !== '-') {
                const tags = r.pattern_tag.split(' | ');
                patternBadge = tags.map(t => {
                    let cls = 'badge-pattern-default';
                    // Support in Green (Floor / Bullish): Any CE Low Break, Any PE High Break, PE New High
                    if ((t.includes('Low Break') && t.includes('CE')) ||
                        (t.includes('High Break') && t.includes('PE')) ||
                        t.includes('PE New High')) {
                        cls = 'badge-pattern-support';
                    }
                    // Resistance in Red (Ceiling / Bearish): Any CE High Break, Any PE Low Break, CE New High
                    else if ((t.includes('High Break') && t.includes('CE')) ||
                             (t.includes('Low Break') && t.includes('PE')) ||
                             t.includes('CE New High')) {
                        cls = 'badge-pattern-resistance';
                    }
                    return `<span class="${cls}">${t}</span>`;
                }).join(' ');
            }

            // Outlook / Forward Sentiment Badge (30m-1h)
            const badgeKey = r.forward_sentiment_badge || 'neutral_decay';
            let outlookCls = 'badge-outlook-decay';
            if (badgeKey === 'squeeze_up') outlookCls = 'badge-outlook-squeeze';
            else if (badgeKey === 'breakdown_down') outlookCls = 'badge-outlook-breakdown';
            else if (badgeKey === 'bullish_sell_pe') outlookCls = 'badge-outlook-bullish';
            else if (badgeKey === 'bearish_sell_ce') outlookCls = 'badge-outlook-bearish';
            else if (badgeKey === 'absorption') outlookCls = 'badge-outlook-absorption';
            else if (badgeKey === 'dual_writing') outlookCls = 'badge-outlook-strangle';
            else if (badgeKey === 'dual_unwind') outlookCls = 'badge-outlook-unwind';

            const tooltipText = `30m–1h Outlook: ${r.forward_sentiment || r.sentiment || 'Neutral'}\nAction: ${r.forward_sentiment_action || 'Observe'}\nReason: ${r.forward_sentiment_reason || 'Balanced market activity'}`;
            const outlookBadge = `<span class="${outlookCls} cursor-help" title="${tooltipText.replace(/"/g, '&quot;')}">${r.forward_sentiment || r.sentiment}</span>`;

            // Diff in OI text color
            const diffColor = r.diff_oi >= 0 ? 'text-green-700' : 'text-red-600 font-semibold';
            const callLtpColor = r.call_ltp_chng > 0 ? 'text-green-700 font-semibold' : (r.call_ltp_chng < 0 ? 'text-red-600 font-semibold' : 'text-gray-600 font-semibold');
            const putLtpColor  = r.put_ltp_chng > 0 ? 'text-green-700 font-semibold' : (r.put_ltp_chng < 0 ? 'text-red-600 font-semibold' : 'text-gray-600 font-semibold');
            const cePeLtpColor = r.ce_pe_ltp_chng > 0 ? 'text-green-700 font-bold' : (r.ce_pe_ltp_chng < 0 ? 'text-red-600 font-bold' : 'text-gray-600 font-bold');

            html += `<tr class="${rowCls} toi-row border-b border-gray-200 transition-colors h-8">
                <td class="toi-td text-center text-gray-500 font-sans">${idx + 1}</td>
                <td class="toi-td text-center text-gray-700">${r.date}</td>
                <td class="toi-td text-center font-bold text-gray-800">${r.time}</td>
                <td class="toi-td text-center">${dayHlBadge}</td>
                <td class="toi-td text-gray-800 font-semibold">${fmt(r.chng_call_oi)}</td>
                <td class="toi-td text-gray-800 font-semibold">${fmt(r.chng_put_oi)}</td>
                <td class="toi-td ${diffColor}">${fmtSigned(r.diff_oi)}</td>
                <td class="toi-td text-center">${strengthBadge}</td>
                <td class="toi-td text-center">${dirBadge}</td>
                <td class="toi-td font-semibold ${r.chng_in_direction >= 0 ? 'text-green-700' : 'text-red-600'}">${fmtSigned(r.chng_in_direction)}</td>
                <td class="toi-td text-center">${patternBadge}</td>
                <td class="toi-td text-center">${outlookBadge}</td>
                <td class="toi-td text-gray-700">${fmt(r.total_call_ltp, 2)}</td>
                <td class="toi-td ${callLtpColor}">${fmtSigned(r.call_ltp_chng, 2)}</td>
                <td class="toi-td ${cePeLtpColor}">${fmtSigned(r.ce_pe_ltp_chng, 2)}</td>
                <td class="toi-td ${putLtpColor}">${fmtSigned(r.put_ltp_chng, 2)}</td>
                <td class="toi-td text-gray-700">${fmt(r.total_put_ltp, 2)}</td>
                <td class="toi-td text-center font-bold text-indigo-900">${fmt(r.net_pcr, 2)}</td>
                <td class="toi-td text-center">${dayHlDiffBadge}</td>
            </tr>`;
        });

        tbody.innerHTML = html;
    }

    // ── Render Charts ────────────────────────────────────────────────────────
    function renderCharts(chartData) {
        applyGraphHeight();
        const labels    = chartData.labels || [];
        const callOi    = chartData.call_oi || [];
        const putOi     = chartData.put_oi || [];
        const sentiment = chartData.sentiment || [];
        const spot      = chartData.spot || [];

        // 1. Trending OI Chart
        const oiCtx = document.getElementById('toi-chart-oi');
        if (oiChartInstance) oiChartInstance.destroy();

        oiChartInstance = new Chart(oiCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Change in Call OI',
                        data: callOi,
                        borderColor: '#16a34a', // Green
                        backgroundColor: 'rgba(22, 163, 74, 0.05)',
                        borderWidth: 2,
                        pointRadius: 2,
                        tension: 0.2,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Change in Put OI',
                        data: putOi,
                        borderColor: '#dc2626', // Red
                        backgroundColor: 'rgba(220, 38, 38, 0.05)',
                        borderWidth: 2,
                        pointRadius: 2,
                        tension: 0.2,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Spot Price',
                        data: spot,
                        borderColor: '#06b6d4', // Cyan
                        borderDash: [4, 4],
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
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.dataset.yAxisID === 'y1') {
                                    label += Number(context.parsed.y).toFixed(2);
                                } else {
                                    label += fmt(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10, family: 'monospace' } }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        ticks: {
                            font: { size: 10, family: 'monospace' },
                            callback: function(value) { return fmt(value); }
                        },
                        grid: { color: 'rgba(226, 232, 240, 0.6)' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: {
                            font: { size: 10, family: 'monospace' },
                            callback: function(value) { return Number(value).toFixed(0); }
                        }
                    }
                }
            }
        });

        // 2. Trending OI Sentiment Chart
        const sentimentCtx = document.getElementById('toi-chart-sentiment');
        if (sentimentChartInstance) sentimentChartInstance.destroy();

        sentimentChartInstance = new Chart(sentimentCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Sentiment (Diff in OI)',
                        data: sentiment,
                        borderColor: '#dc2626',
                        backgroundColor: 'rgba(220, 38, 38, 0.05)',
                        borderWidth: 2,
                        pointRadius: 2,
                        tension: 0.2,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Spot Price',
                        data: spot,
                        borderColor: '#06b6d4',
                        borderDash: [4, 4],
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
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.dataset.yAxisID === 'y1') {
                                    label += Number(context.parsed.y).toFixed(2);
                                } else {
                                    label += fmt(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10, family: 'monospace' } }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        ticks: {
                            font: { size: 10, family: 'monospace' },
                            callback: function(value) { return fmt(value); }
                        },
                        grid: { color: 'rgba(226, 232, 240, 0.6)' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: {
                            font: { size: 10, family: 'monospace' },
                            callback: function(value) { return Number(value).toFixed(0); }
                        }
                    }
                }
            }
        });

        if (toggleGraph.checked) {
            setTimeout(() => {
                if (oiChartInstance) oiChartInstance.resize();
                if (sentimentChartInstance) sentimentChartInstance.resize();
                if (buildupChartInstance) buildupChartInstance.resize();
            }, 50);
        }
    }

    // ── Render Strike OI Buildup (Multi-Timeframe 5M, 15M, 30M, Today) ─────────
    function renderStrikeBuildup(bldData) {
        if (!bldData) return;
        currentBuildupData = bldData;
        updateBuildupChartView();
    }

    function updateBuildupChartView() {
        if (!currentBuildupData) return;
        const bldCtx = document.getElementById('toi-chart-buildup');
        if (!bldCtx) return;

        // Extract dataset for active timeframe (5m, 15m, 30m, today)
        const tfData = (currentBuildupData[currentBuildupTf]) 
                    ? currentBuildupData[currentBuildupTf] 
                    : (currentBuildupData['5m'] || currentBuildupData);

        // Window label & summary text
        if (buildupWindow) {
            buildupWindow.textContent = tfData.window_label || '—';
        }
        if (tfData.summary) {
            if (buildupCeSum) buildupCeSum.textContent = tfData.summary.total_ce_chg_lakh || tfData.summary.total_ce_added_lakh || '+0.0 L';
            if (buildupPeSum) buildupPeSum.textContent = tfData.summary.total_pe_chg_lakh || tfData.summary.total_pe_added_lakh || '+0.0 L';
            if (buildupDominant) buildupDominant.textContent = tfData.summary.dominant || '—';
        }

        const tfLabels = { '5m': '5M', '15m': '15M', '30m': '30M', 'today': 'Today' };
        if (buildupModeDesc) {
            buildupModeDesc.textContent = `Top 8 Activity (${tfLabels[currentBuildupTf] || currentBuildupTf.toUpperCase()})`;
        }

        if (buildupChartInstance) {
            buildupChartInstance.destroy();
            buildupChartInstance = null;
        }

        let items = tfData.top_items || [];
        // Fallback for legacy single-buildup structure
        if (items.length === 0 && tfData.top_buildup) {
            items = tfData.top_buildup;
        }

        if (items.length === 0) {
            return;
        }

        const labels = items.map(x => x.label || `${x.strike} ${x.option_type}`);
        const dataVals = items.map(x => x.diff_oi_val_lakh);
        const colors = items.map(x => x.bar_color || '#dc2626');
        const formattedLabels = items.map(x => x.diff_oi_lakh);
        const rawDiffs = items.map(x => x.diff_oi);

        const barValueLabelsPlugin = {
            id: 'barValueLabels',
            afterDatasetsDraw(chart) {
                const { ctx } = chart;
                chart.data.datasets.forEach((dataset, i) => {
                    const meta = chart.getDatasetMeta(i);
                    meta.data.forEach((bar, index) => {
                        const valStr = dataset.formattedLabels ? dataset.formattedLabels[index] : null;
                        if (!valStr) return;
                        ctx.save();
                        ctx.font = 'bold 11px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                        const textWidth = ctx.measureText(valStr).width;
                        const barWidth = Math.abs(bar.x - bar.base);

                        // If bar width is sufficient, place white bold text centered inside the bar
                        if (barWidth > textWidth + 18) {
                            ctx.fillStyle = '#ffffff';
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'middle';
                            ctx.fillText(valStr, (bar.base + bar.x) / 2, bar.y);
                        } else {
                            // Otherwise place dark bold text outside the bar
                            ctx.fillStyle = '#1e293b';
                            ctx.textAlign = 'left';
                            ctx.textBaseline = 'middle';
                            ctx.fillText(valStr, bar.x + 6, bar.y);
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
                    borderRadius: 4,
                    barThickness: 28,
                    formattedLabels: formattedLabels,
                    rawDiffs: rawDiffs,
                    itemsMeta: items,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: { right: 40 }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const item = context.dataset.itemsMeta ? context.dataset.itemsMeta[context.dataIndex] : null;
                                if (!item) return '';
                                const sign = item.diff_oi >= 0 ? '+' : '';
                                const ltpSign = item.diff_ltp >= 0 ? '+' : '';
                                return [
                                    `${item.buildup_name} (${item.buildup_type})`,
                                    `ΔOI: ${item.diff_oi_lakh} (${sign}${fmt(item.diff_oi)} contracts)`,
                                    `ΔLTP: ${ltpSign}${item.diff_ltp}`
                                ];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: '|ΔOI| (Lakh contracts)',
                            font: { weight: 'bold', size: 11 },
                            color: '#475569'
                        },
                        grid: { color: 'rgba(226, 232, 240, 0.6)' },
                        ticks: {
                            font: { size: 11, family: 'monospace' },
                            callback: function(val) {
                                return val + ' L';
                            }
                        }
                    },
                    y: {
                        grid: { display: false },
                        ticks: {
                            font: { weight: 'bold', size: 11 },
                            color: '#1e293b'
                        }
                    }
                }
            },
            plugins: [barValueLabelsPlugin]
        });
    }

    // Timeframe tabs event listeners (5M, 15M, 30M, Today)
    if (buildupTfTabs && buildupTfTabs.length > 0) {
        buildupTfTabs.forEach(tabBtn => {
            tabBtn.addEventListener('click', () => {
                const tf = tabBtn.getAttribute('data-tf');
                if (!tf || tf === currentBuildupTf) return;
                currentBuildupTf = tf;
                buildupTfTabs.forEach(t => {
                    t.className = 'toi-tf-tab px-2 py-0.5 rounded text-gray-500 hover:text-gray-900 cursor-pointer transition-colors';
                });
                tabBtn.className = 'toi-tf-tab px-2 py-0.5 rounded bg-white text-gray-900 shadow-xs cursor-pointer font-bold transition-colors';
                updateBuildupChartView();
            });
        });
    }

    // Quick jump button from Top Ribbon
    if (stationBuildupBtn) {
        stationBuildupBtn.addEventListener('click', () => {
            if (!toggleGraph.checked) {
                toggleGraph.checked = true;
                toggleGraph.dispatchEvent(new Event('change'));
            }
            setTimeout(() => {
                const target = document.getElementById('toi-buildup-card') || graphContainer;
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, 100);
        });
    }

    // ── 5-Minute Top OI Buildup Matrix Renderer ──────────────────────────────
    function render5mBuildupMatrix(matrixData) {
        currentMatrixData = matrixData;
        updateMatrixTableView();
    }

    function updateMatrixTableView() {
        if (!matrixTbody || !matrixHeaderRow) return;

        if (!currentMatrixData || !currentMatrixData.rows || currentMatrixData.rows.length === 0) {
            matrixHeaderRow.innerHTML = `
                <th class="sticky left-0 top-0 z-30 bg-gray-100 border-r border-gray-300 px-3 py-2 text-left font-bold text-slate-800 min-w-[100px] w-[100px] shadow-sm">Strike</th>
                <th class="sticky left-[100px] top-0 z-30 bg-gray-100 border-r-2 border-slate-300 px-3 py-2 text-right font-bold text-slate-800 min-w-[90px] w-[90px] shadow-sm">Total OI</th>
            `;
            matrixTbody.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center py-8 text-gray-400 font-sans text-xs">
                        No 5-minute buildup activity recorded for this session.
                    </td>
                </tr>
            `;
            if (matrixCountBadge) matrixCountBadge.textContent = '0 Strikes Active';
            return;
        }

        const { columns, rows, total_strikes } = currentMatrixData;

        // Resolve effective datewise threshold in Lakh contracts
        const effectiveTh = getEffectiveThreshold();
        if (matrixThresholdBadge) {
            let dayText = '';
            if (currentMatrixThreshold === 'auto') {
                dayText = (currentMatrixData && currentMatrixData.day_desc) 
                    ? currentMatrixData.day_desc 
                    : `${effectiveTh}L (Day Auto)`;
            } else {
                dayText = `Manual: ${effectiveTh}L`;
            }
            matrixThresholdBadge.textContent = `${effectiveTh}L`;
            matrixThresholdBadge.title = `Active threshold: ${dayText}`;
        }

        // Pre-scan all strikes to identify time columns that meet/exceed datewise threshold
        const highVolTimesMap = {};
        rows.forEach(r => {
            if (!r.cells) return;
            columns.forEach(t => {
                const c = r.cells[t];
                if (c && Math.abs(c.diff_oi) >= (effectiveTh * 100000)) {
                    highVolTimesMap[t] = (highVolTimesMap[t] || 0) + 1;
                }
            });
        });

        // 1. Render Header Row (Strike, Total OI, and time columns from recent to old with high-vol highlight)
        let headerHtml = `
            <th class="sticky left-0 top-0 z-30 bg-gray-100 border-r border-gray-300 px-3 py-2 text-left font-bold text-slate-800 min-w-[100px] w-[100px] shadow-sm">Strike</th>
            <th class="sticky left-[100px] top-0 z-30 bg-gray-100 border-r-2 border-slate-300 px-3 py-2 text-right font-bold text-slate-800 min-w-[90px] w-[90px] shadow-sm">Total OI</th>
        `;
        columns.forEach(time => {
            const highCount = highVolTimesMap[time] || 0;
            if (highCount > 0) {
                headerHtml += `
                    <th class="sticky top-0 z-20 toi-matrix-th-highlight border-r border-amber-300 px-2 py-2 text-center font-mono font-black min-w-[85px] cursor-help shadow-xs"
                        title="⚡ High OI Bar: ${highCount} strike(s) with |ΔOI| ≥ ${effectiveTh}L at ${time}">
                        <div class="flex items-center justify-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-600 inline-block animate-pulse"></span>
                            <span>${time}</span>
                        </div>
                    </th>
                `;
            } else {
                headerHtml += `<th class="sticky top-0 z-20 bg-gray-50 border-r border-gray-200 px-2.5 py-2 text-center font-mono font-bold text-slate-700 min-w-[85px]">${time}</th>`;
            }
        });
        matrixHeaderRow.innerHTML = headerHtml;

        // 2. Filter Rows based on Option Type (ALL, CE, PE) and Search query
        const filteredRows = rows.filter(r => {
            if (currentMatrixFilter === 'CE' && r.option_type !== 'CE') return false;
            if (currentMatrixFilter === 'PE' && r.option_type !== 'PE') return false;

            if (currentMatrixSearch) {
                const q = currentMatrixSearch.toLowerCase();
                const strikeText = (r.strike || '').toLowerCase();
                const priceText = String(r.strike_price || '');
                if (!strikeText.includes(q) && !priceText.includes(q)) return false;
            }
            return true;
        });

        if (matrixCountBadge) {
            matrixCountBadge.textContent = `${filteredRows.length} of ${total_strikes} Strikes`;
        }

        if (filteredRows.length === 0) {
            matrixTbody.innerHTML = `
                <tr>
                    <td colspan="${columns.length + 2}" class="text-center py-8 text-gray-400 font-sans text-xs">
                        No strikes match the filter "${currentMatrixSearch || currentMatrixFilter}".
                    </td>
                </tr>
            `;
            return;
        }

        // 3. Render Table Rows with Distinct CE and PE Styling & Datewise OI Highlighting
        let tbodyHtml = '';
        filteredRows.forEach((row, idx) => {
            const isCe = row.option_type === 'CE';
            const rowClass = isCe ? 'toi-matrix-row-ce' : 'toi-matrix-row-pe';
            const altClass = (idx % 2 === 1) ? 'toi-matrix-alt' : '';

            // Sticky column classes with distinct left border and background tint
            const stickyStrikeClass = isCe
                ? 'border-l-4 border-l-emerald-600 bg-[#edfcf2] text-slate-900'
                : 'border-l-4 border-l-blue-600 bg-[#eef4ff] text-slate-900';

            const stickyOiClass = isCe
                ? 'bg-[#edfcf2] text-emerald-950 border-r-2 border-slate-300'
                : 'bg-[#eef4ff] text-blue-950 border-r-2 border-slate-300';

            const typeBadge = isCe
                ? `<span class="bg-emerald-100 text-emerald-800 border border-emerald-300 text-[10px] px-1.5 py-0.5 rounded font-extrabold ml-1">CE</span>`
                : `<span class="bg-blue-100 text-blue-800 border border-blue-300 text-[10px] px-1.5 py-0.5 rounded font-extrabold ml-1">PE</span>`;

            // Micro-tag on every cell badge so user immediately knows CE vs PE in middle & late columns
            const optTag = isCe
                ? `<span class="text-[9px] font-black uppercase px-1 py-0.5 rounded bg-black/25 mr-1 leading-none tracking-wider text-emerald-100">CE</span>`
                : `<span class="text-[9px] font-black uppercase px-1 py-0.5 rounded bg-black/25 mr-1 leading-none tracking-wider text-blue-100">PE</span>`;

            const emptyDash = isCe
                ? `<span class="text-emerald-300/80 font-mono text-xs select-none">—</span>`
                : `<span class="text-blue-300/80 font-mono text-xs select-none">—</span>`;

            tbodyHtml += `<tr class="${rowClass} ${altClass} transition-colors">`;

            // Col 1: Strike (Sticky left-0)
            tbodyHtml += `
                <td class="sticky left-0 z-10 ${stickyStrikeClass} border-r border-gray-200 px-3 py-1.5 text-left font-mono font-bold min-w-[100px] w-[100px] shadow-xs">
                    <span>${row.strike_price}</span>${typeBadge}
                </td>
            `;

            // Col 2: Total OI (Sticky left-[100px])
            tbodyHtml += `
                <td class="sticky left-[100px] z-10 ${stickyOiClass} px-3 py-1.5 text-right font-mono font-bold min-w-[90px] w-[90px] shadow-xs">
                    ${row.total_oi_lakh}
                </td>
            `;

            // Dynamic 5-min intervals (Recent to Old)
            columns.forEach(time => {
                const cell = row.cells ? row.cells[time] : null;
                if (cell) {
                    const ltpPrefix = cell.diff_ltp > 0 ? '+' : '';
                    const isHighVol = Math.abs(cell.diff_oi) >= (effectiveTh * 100000);

                    const cellHighlightClass = isHighVol ? 'toi-matrix-cell-highlight' : '';
                    const badgeHighlightClass = isHighVol ? 'toi-matrix-badge-highlight ring-2 ring-amber-400 ring-offset-1 ring-offset-white' : '';
                    const highVolStar = isHighVol ? `<span class="ml-1 text-[9px] text-amber-200 font-extrabold" title="High OI Bar (≥ ${effectiveTh}L)">★</span>` : '';
                    const highVolNotice = isHighVol ? `&#10;⚡ HIGH VOLUME BUILDUP: |ΔOI| ≥ ${effectiveTh}L` : '';

                    const tooltip = `${cell.strike} | ${cell.name} (${cell.type})&#10;ΔOI: ${cell.diff_oi_lakh}${highVolNotice}&#10;ΔLTP: ${ltpPrefix}${cell.diff_ltp}`;

                    tbodyHtml += `
                        <td class="border-r border-gray-100 p-1 text-center font-mono ${cellHighlightClass}" title="${tooltip}">
                            <div class="px-2 py-0.5 rounded text-[10.5px] font-bold shadow-xs whitespace-nowrap inline-flex items-center ${badgeHighlightClass}" 
                                 style="background-color: ${cell.color}; color: ${cell.text_color};">
                                ${optTag}<span>${cell.diff_oi_lakh}</span>${highVolStar}
                            </div>
                        </td>
                    `;
                } else {
                    tbodyHtml += `
                        <td class="border-r border-gray-100 px-2 py-1 text-center">
                            ${emptyDash}
                        </td>
                    `;
                }
            });

            tbodyHtml += `</tr>`;
        });

        matrixTbody.innerHTML = tbodyHtml;
    }

    // Helper: Determine effective datewise OI highlight threshold (Lakh)
    function getEffectiveThreshold() {
        if (currentMatrixThreshold !== 'auto') {
            return parseFloat(currentMatrixThreshold);
        }
        if (currentMatrixData && currentMatrixData.default_threshold) {
            return currentMatrixData.default_threshold;
        }
        // Fallback calculation by date input
        const dStr = dateInput ? dateInput.value : '';
        if (dStr) {
            const d = new Date(dStr + 'T12:00:00');
            const day = d.getDay(); // 0: Sun, 1: Mon, 2: Tue, 3: Wed, 4: Thu, 5: Fri, 6: Sat
            const map = { 3: 10, 4: 15, 5: 20, 1: 20, 2: 25, 6: 20, 0: 20 };
            return map[day] || 20;
        }
        return 20;
    }

    // Buildup Matrix Threshold selector
    if (matrixThresholdSelect) {
        matrixThresholdSelect.addEventListener('change', (e) => {
            currentMatrixThreshold = e.target.value;
            updateMatrixTableView();
        });
    }

    // Buildup Matrix Filter tab buttons (ALL, CE, PE)
    if (matrixFilterTabs && matrixFilterTabs.length > 0) {
        matrixFilterTabs.forEach(tabBtn => {
            tabBtn.addEventListener('click', () => {
                const filter = tabBtn.getAttribute('data-matrix-filter');
                if (!filter || filter === currentMatrixFilter) return;
                currentMatrixFilter = filter;
                matrixFilterTabs.forEach(t => {
                    t.className = 'toi-matrix-tab px-2.5 py-0.5 rounded text-gray-500 hover:text-gray-900 cursor-pointer transition-colors';
                });
                tabBtn.className = 'toi-matrix-tab px-2.5 py-0.5 rounded bg-white text-gray-900 shadow-xs cursor-pointer font-bold transition-colors';
                updateMatrixTableView();
            });
        });
    }

    // Buildup Matrix Search Filter Input
    if (matrixSearchInput) {
        matrixSearchInput.addEventListener('input', (e) => {
            currentMatrixSearch = e.target.value.trim();
            updateMatrixTableView();
        });
    }

    // ── Strike Selection Modal ───────────────────────────────────────────────
    function buildModalGrid() {
        modalGrid.innerHTML = '';
        currentAllStrikes.forEach(s => {
            const isChecked = currentSelectedStrikes.includes(s);
            const label = document.createElement('label');
            label.className = 'flex items-center gap-1.5 cursor-pointer bg-white p-1 rounded border border-gray-200 hover:bg-red-50 text-gray-700 select-none';
            label.innerHTML = `<input type="checkbox" value="${s}" ${isChecked ? 'checked' : ''} class="accent-red-600 toi-modal-chk"> <span class="${s === currentAtmStrike ? 'font-bold text-amber-600' : ''}">${s}</span>`;
            modalGrid.appendChild(label);
        });
        updateModalSummary();
    }

    function updateModalSummary() {
        const checked = [...modalGrid.querySelectorAll('.toi-modal-chk:checked')].map(c => parseInt(c.value)).sort((a,b)=>a-b);
        modalTotalCount.textContent = checked.length;
        modalSummary.textContent = checked.length > 0 ? checked.join(', ') : 'None selected';
    }

    modalGrid.addEventListener('change', updateModalSummary);

    strikesBtn.addEventListener('click', () => {
        buildModalGrid();
        modal.classList.replace('hidden', 'flex');
    });

    modalClose.addEventListener('click', closeModal);
    modalCancel.addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

    function closeModal() {
        modal.classList.replace('flex', 'hidden');
    }

    modalClear.addEventListener('click', () => {
        modalGrid.querySelectorAll('.toi-modal-chk').forEach(c => c.checked = false);
        updateModalSummary();
    });

    modalSelectAll.addEventListener('click', () => {
        modalGrid.querySelectorAll('.toi-modal-chk').forEach(c => c.checked = true);
        updateModalSummary();
    });

    modalReset.addEventListener('click', () => {
        // Reset to 15 ATM strikes
        if (currentAtmStrike && currentAllStrikes.length > 0) {
            let atmIdx = currentAllStrikes.indexOf(currentAtmStrike);
            if (atmIdx === -1) atmIdx = Math.floor(currentAllStrikes.length / 2);
            const startIdx = Math.max(0, atmIdx - 7);
            const default15 = currentAllStrikes.slice(startIdx, startIdx + 15);

            modalGrid.querySelectorAll('.toi-modal-chk').forEach(c => {
                const s = parseInt(c.value);
                c.checked = default15.includes(s);
            });
            updateModalSummary();
        }
    });

    modalApply.addEventListener('click', () => {
        const checked = [...modalGrid.querySelectorAll('.toi-modal-chk:checked')].map(c => parseInt(c.value)).sort((a,b)=>a-b);
        if (checked.length === 0) {
            alert('Please select at least 1 strike price.');
            return;
        }
        currentSelectedStrikes = checked;
        closeModal();
        fetchData();
    });

    // ── Initial Data Fetch on Page Load ──────────────────────────────────────
    fetchData();

})();
</script>
@endpush

@endsection
