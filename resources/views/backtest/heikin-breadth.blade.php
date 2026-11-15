@extends('layouts.app')

@section('title', 'Heikin Ashi & Nifty 50 Breadth Backtest Engine')

@push('styles')
    <!-- Chart.js 4.4 -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #0f172a; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #475569; }
    </style>
@endpush

@section('content')
<div class="min-h-screen bg-[#0b0f19] text-slate-100 p-3 sm:p-5 font-sans antialiased">

    <!-- ════════════════════════════════════════════════════════════════════════════════
         TOP HEADER & STRATEGY BADGES
    ════════════════════════════════════════════════════════════════════════════════ -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-5 pb-4 border-b border-slate-800">
        <div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="inline-flex items-center justify-center p-2 rounded-xl bg-gradient-to-br from-emerald-500/20 to-teal-500/20 border border-emerald-500/30 text-emerald-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </span>
                <h1 class="text-xl sm:text-2xl font-black tracking-tight text-white flex items-center gap-2">
                    Heikin Ashi & Nifty 50 Breadth Backtest
                    <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">NIFTY SPOT</span>
                </h1>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                Intraday High-Conviction Trend Confirmation • Heikin Ashi Flat-Wick Filters (No Bottom Wick for Bull / No Top Wick for Bear) • Normal Candle Close SL Execution • 15:00 Square-Off
            </p>
        </div>

        <!-- Strategy Metadata Badges -->
        <div class="flex items-center gap-2 flex-wrap text-[11px] font-mono">
            <span class="px-2.5 py-1 rounded-lg bg-slate-800/80 border border-slate-700/80 text-slate-300">
                📦 <strong class="text-white">1 Lot (65 Qty)</strong>
            </span>
            <span class="px-2.5 py-1 rounded-lg bg-slate-800/80 border border-slate-700/80 text-slate-300">
                🛑 Max <strong class="text-white">2 Trades/Day</strong>
            </span>
            <span class="px-2.5 py-1 rounded-lg bg-slate-800/80 border border-slate-700/80 text-emerald-300" title="Trade entries allowed starting at this time">
                🟢 Start: <strong id="header-min-entry-label" class="text-white">09:15</strong>
            </span>
            <span class="px-2.5 py-1 rounded-lg bg-slate-800/80 border border-slate-700/80 text-rose-300" title="No trade entries taken at or after this time">
                🚫 Cutoff: <strong id="header-cutoff-label" class="text-white">14:30</strong>
            </span>
            <span class="px-2.5 py-1 rounded-lg bg-slate-800/80 border border-slate-700/80 text-amber-300">
                ⏰ Exit at <strong class="text-amber-200">15:00</strong>
            </span>
            <span class="px-2.5 py-1 rounded-lg bg-slate-800/80 border border-slate-700/80 text-cyan-300">
                ⏳ Max Duration: <strong id="header-max-dur-label" class="text-white">Off (Unlimited)</strong>
            </span>
            <span id="badge-discarded-losses" onclick="showDiscardedTrades()" class="hidden px-2.5 py-1 rounded-lg bg-purple-900/40 hover:bg-purple-900/80 border border-purple-500/50 text-purple-300 font-bold cursor-pointer transition flex items-center gap-1.5" title="Click to view all discarded stagnant loss trades in the table below">
                <span>✂️ Discarded Losses (&gt; <span id="header-discard-mins">45</span>m):</span>
                <strong id="header-discard-count" class="text-white bg-purple-600 px-1.5 py-0.2 rounded-full text-[10px]">0</strong>
                <span class="text-[10px] text-purple-300 underline ml-0.5">View</span>
            </span>
            <span class="px-2.5 py-1 rounded-lg bg-slate-800/80 border border-slate-700/80 text-indigo-300">
                🔄 Reversal On Opposite
            </span>
            <button id="btn-export-csv" onclick="exportTradesCsv()" class="px-3 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 hover:text-white transition flex items-center gap-1.5 cursor-pointer">
                <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                CSV Export
            </button>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════════════════
         CONTROL PANEL & PARAMETER SELECTION
    ════════════════════════════════════════════════════════════════════════════════ -->
    <div class="bg-[#131b2e] border border-slate-800 rounded-2xl p-4 sm:p-5 mb-6 shadow-xl">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">

            <!-- 1. Timeframe (5, 15, 30 min) -->
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">
                    ⏱️ Timeframe
                </label>
                <div class="grid grid-cols-3 gap-1.5 bg-slate-900 p-1 rounded-xl border border-slate-800">
                    <button type="button" onclick="setTimeframe(5)" id="btn-tf-5" class="tf-btn py-1.5 text-xs font-bold rounded-lg transition text-slate-400 hover:text-white">5m</button>
                    <button type="button" onclick="setTimeframe(15)" id="btn-tf-15" class="tf-btn py-1.5 text-xs font-bold rounded-lg transition bg-indigo-600 text-white shadow-sm">15m</button>
                    <button type="button" onclick="setTimeframe(30)" id="btn-tf-30" class="tf-btn py-1.5 text-xs font-bold rounded-lg transition text-slate-400 hover:text-white">30m</button>
                </div>
                <input type="hidden" id="input-interval" value="15">
            </div>

            <!-- 2. Breadth Threshold % (70, 75, 80, 85, 90) -->
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">
                    🎯 Breadth Threshold
                </label>
                <div class="grid grid-cols-5 gap-1 bg-slate-900 p-1 rounded-xl border border-slate-800">
                    <button type="button" onclick="setThreshold(70)" id="btn-th-70" class="th-btn py-1.5 text-[11px] font-bold rounded-lg transition text-slate-400 hover:text-white">70%</button>
                    <button type="button" onclick="setThreshold(75)" id="btn-th-75" class="th-btn py-1.5 text-[11px] font-bold rounded-lg transition bg-emerald-600 text-white shadow-sm">75%</button>
                    <button type="button" onclick="setThreshold(80)" id="btn-th-80" class="th-btn py-1.5 text-[11px] font-bold rounded-lg transition text-slate-400 hover:text-white">80%</button>
                    <button type="button" onclick="setThreshold(85)" id="btn-th-85" class="th-btn py-1.5 text-[11px] font-bold rounded-lg transition text-slate-400 hover:text-white">85%</button>
                    <button type="button" onclick="setThreshold(90)" id="btn-th-90" class="th-btn py-1.5 text-[11px] font-bold rounded-lg transition text-slate-400 hover:text-white">90%</button>
                </div>
                <input type="hidden" id="input-threshold" value="75">
            </div>

            <!-- 3. Max Holding Duration Filter (0, 30m, 45m, 60m, 90m) -->
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5" title="Filter out prolonged losing trades held > X mins (treated as Cost-to-Cost / Avoided). Winning trades run unlimited!">
                    ⏳ Filter Stagnant Losses
                </label>
                <div class="grid grid-cols-5 gap-1 bg-slate-900 p-1 rounded-xl border border-slate-800">
                    <button type="button" onclick="setMaxDuration(0)" id="btn-dur-0" class="dur-btn py-1.5 text-[11px] font-bold rounded-lg transition bg-cyan-600 text-white shadow-sm" title="Off: Keep all loss trades">Off</button>
                    <button type="button" onclick="setMaxDuration(30)" id="btn-dur-30" class="dur-btn py-1.5 text-[11px] font-bold rounded-lg transition text-slate-400 hover:text-white" title="Discard loss trades held > 30 mins (Cost-to-Cost / Avoided). Profits run unlimited!">30m</button>
                    <button type="button" onclick="setMaxDuration(45)" id="btn-dur-45" class="dur-btn py-1.5 text-[11px] font-bold rounded-lg transition text-slate-400 hover:text-white" title="Discard loss trades held > 45 mins (Cost-to-Cost / Avoided). Profits run unlimited!">45m</button>
                    <button type="button" onclick="setMaxDuration(60)" id="btn-dur-60" class="dur-btn py-1.5 text-[11px] font-bold rounded-lg transition text-slate-400 hover:text-white" title="Discard loss trades held > 60 mins (Cost-to-Cost / Avoided). Profits run unlimited!">60m</button>
                    <button type="button" onclick="setMaxDuration(90)" id="btn-dur-90" class="dur-btn py-1.5 text-[11px] font-bold rounded-lg transition text-slate-400 hover:text-white" title="Discard loss trades held > 90 mins (Cost-to-Cost / Avoided). Profits run unlimited!">90m</button>
                </div>
                <input type="hidden" id="input-max-duration" value="0">
            </div>

            <!-- 4. Breadth Mode (Both, Count, Weight) -->
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5" title="User specification: Both Count and Weightage should be >= Threshold">
                    ⚖️ Breadth Mode
                </label>
                <select id="input-breadth-mode" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3 py-2 text-xs font-medium text-white focus:outline-none focus:border-indigo-500">
                    <option value="both" selected>Both Count &amp; Weight &ge; % (Default)</option>
                    <option value="count">Stock Count % &ge; X</option>
                    <option value="weight">Market-Cap Weight % &ge; X</option>
                    <option value="either">Either Count OR Weight &ge; %</option>
                </select>
            </div>

            <!-- 5. SL Candle Reference (Normal vs Heikin Ashi) -->
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5" title="Stop Loss level taken from Confirmation Candle. Normal close triggers the exit.">
                    🛡️ SL Level Reference
                </label>
                <div class="grid grid-cols-2 gap-1.5 bg-slate-900 p-1 rounded-xl border border-slate-800">
                    <button type="button" onclick="setSlMode('normal')" id="btn-sl-normal" class="sl-btn py-1.5 text-xs font-bold rounded-lg transition bg-amber-600 text-white shadow-sm" title="Confirmation Candle Normal Low/High">Normal (Default)</button>
                    <button type="button" onclick="setSlMode('ha')" id="btn-sl-ha" class="sl-btn py-1.5 text-xs font-bold rounded-lg transition text-slate-400 hover:text-white" title="Confirmation Candle Heikin Ashi Low/High">Heikin Ashi</button>
                </div>
                <input type="hidden" id="input-sl-mode" value="normal">
            </div>

            <!-- 6. Date Range (From & To) -->
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">
                    📅 Date Range
                </label>
                <div class="flex items-center gap-1.5">
                    <input type="date" id="input-from-date" value="{{ $defaultFromDate }}" min="{{ $minDate }}" max="{{ $maxDate }}" class="w-1/2 bg-slate-900 border border-slate-700/80 rounded-xl px-2 py-1.5 text-[11px] text-white focus:outline-none focus:border-indigo-500 font-mono">
                    <span class="text-xs text-slate-500">-</span>
                    <input type="date" id="input-to-date" value="{{ $defaultToDate }}" min="{{ $minDate }}" max="{{ $maxDate }}" class="w-1/2 bg-slate-900 border border-slate-700/80 rounded-xl px-2 py-1.5 text-[11px] text-white focus:outline-none focus:border-indigo-500 font-mono">
                </div>
                <div class="flex items-center gap-1.5 mt-1.5">
                    <button type="button" onclick="setDatePreset(30)" class="text-[10px] text-slate-400 hover:text-indigo-400 hover:underline">30D</button>
                    <span class="text-slate-600">•</span>
                    <button type="button" onclick="setDatePreset(90)" class="text-[10px] text-slate-400 hover:text-indigo-400 hover:underline">90D</button>
                    <span class="text-slate-600">•</span>
                    <button type="button" onclick="setDatePreset('ytd')" class="text-[10px] text-slate-400 hover:text-indigo-400 hover:underline">YTD</button>
                    <span class="text-slate-600">•</span>
                    <button type="button" onclick="setDatePreset('all')" class="text-[10px] text-slate-400 hover:text-indigo-400 hover:underline">All</button>
                </div>
            </div>

        </div>

        <!-- Action Buttons Row -->
        <div class="mt-4 pt-4 border-t border-slate-800/80 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-4 text-xs text-slate-400 flex-wrap">
                <div>
                    <label class="text-[11px] text-slate-400 mr-1.5">Lot Size (Qty):</label>
                    <input type="number" id="input-lot-size" value="65" class="w-16 bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 text-xs text-white font-mono text-center">
                </div>
                <div>
                    <label class="text-[11px] text-slate-400 mr-1.5">Max Trades/Day:</label>
                    <input type="number" id="input-max-trades" value="2" class="w-14 bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 text-xs text-white font-mono text-center">
                </div>
                <div>
                    <label class="text-[11px] text-slate-400 mr-1.5" title="Trade only after this time (e.g. 09:15, 09:30, 09:45, 10:00)">🟢 Trade Only After:</label>
                    <div class="inline-flex items-center gap-1 bg-slate-900 border border-slate-700 rounded-lg p-0.5">
                        <button type="button" onclick="setMinEntryTime('09:15')" id="btn-min-entry-0915" class="min-entry-time-btn px-2 py-0.5 text-[11px] font-mono font-bold rounded bg-indigo-600 text-white shadow-sm">09:15</button>
                        <button type="button" onclick="setMinEntryTime('09:30')" id="btn-min-entry-0930" class="min-entry-time-btn px-2 py-0.5 text-[11px] font-mono font-bold rounded text-slate-400 hover:text-white">09:30</button>
                        <button type="button" onclick="setMinEntryTime('09:45')" id="btn-min-entry-0945" class="min-entry-time-btn px-2 py-0.5 text-[11px] font-mono font-bold rounded text-slate-400 hover:text-white">09:45</button>
                        <button type="button" onclick="setMinEntryTime('10:00')" id="btn-min-entry-1000" class="min-entry-time-btn px-2 py-0.5 text-[11px] font-mono font-bold rounded text-slate-400 hover:text-white">10:00</button>
                        <input type="text" id="input-min-entry-time" value="09:15" placeholder="HH:mm" class="w-14 bg-slate-800 border border-slate-600 rounded px-1.5 py-0.5 text-[11px] text-white font-mono text-center focus:outline-none focus:border-indigo-500" onchange="updateCustomMinEntryTime(this.value)">
                    </div>
                </div>
                <div>
                    <label class="text-[11px] text-slate-400 mr-1.5" title="No new trade entries will be taken at or after this time (e.g. 13:30 or 14:30)">🚫 No Trade After:</label>
                    <div class="inline-flex items-center gap-1 bg-slate-900 border border-slate-700 rounded-lg p-0.5">
                        <button type="button" onclick="setMaxEntryTime('13:30')" id="btn-entry-1330" class="entry-time-btn px-2 py-0.5 text-[11px] font-mono font-bold rounded text-slate-400 hover:text-white">13:30</button>
                        <button type="button" onclick="setMaxEntryTime('14:00')" id="btn-entry-1400" class="entry-time-btn px-2 py-0.5 text-[11px] font-mono font-bold rounded text-slate-400 hover:text-white">14:00</button>
                        <button type="button" onclick="setMaxEntryTime('14:30')" id="btn-entry-1430" class="entry-time-btn px-2 py-0.5 text-[11px] font-mono font-bold rounded bg-indigo-600 text-white shadow-sm">14:30</button>
                        <button type="button" onclick="setMaxEntryTime('14:45')" id="btn-entry-1445" class="entry-time-btn px-2 py-0.5 text-[11px] font-mono font-bold rounded text-slate-400 hover:text-white">14:45</button>
                        <button type="button" onclick="setMaxEntryTime('15:00')" id="btn-entry-1500" class="entry-time-btn px-2 py-0.5 text-[11px] font-mono font-bold rounded text-slate-400 hover:text-white">15:00</button>
                        <input type="text" id="input-max-entry-time" value="14:30" placeholder="HH:mm" class="w-14 bg-slate-800 border border-slate-600 rounded px-1.5 py-0.5 text-[11px] text-white font-mono text-center focus:outline-none focus:border-indigo-500" onchange="updateCustomEntryTime(this.value)">
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto">
                <!-- Single Run -->
                <button type="button" id="btn-run-backtest" onclick="runSingleBacktest()" class="flex-1 sm:flex-initial px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow-lg shadow-emerald-900/30 transition flex items-center justify-center gap-2 cursor-pointer">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/></svg>
                    <span>Run Backtest</span>
                </button>

                <!-- Priority Matrix Run -->
                <button type="button" id="btn-run-matrix" onclick="runPriorityMatrix()" class="flex-1 sm:flex-initial px-5 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-bold text-xs shadow-lg shadow-indigo-900/30 transition flex items-center justify-center gap-2 cursor-pointer" title="Test all high priorities: 15m (75,80,85,90), 5m (75,80,85,90), 30m (70,75,80,85)">
                    <svg class="w-4 h-4 text-amber-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>
                    <span>Run Priority Matrix (12 Sets)</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════════════════
         LOADER SPINNER
    ════════════════════════════════════════════════════════════════════════════════ -->
    <div id="loading-indicator" class="hidden my-8 p-8 bg-slate-900/60 border border-slate-800 rounded-2xl text-center">
        <div class="inline-block animate-spin rounded-full h-10 w-10 border-4 border-indigo-500 border-t-transparent mb-3"></div>
        <h4 class="text-sm font-bold text-white" id="loading-title">Executing High-Speed Historical Backtest...</h4>
        <p class="text-xs text-slate-400 mt-1" id="loading-subtitle">Resampling candles, synthesizing Nifty 50 constituents, evaluating Heikin Ashi structure & normal SL triggers.</p>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════════════════
         SECTION A: PRIORITY COMPARISON MATRIX LEADERBOARD (VISIBLE WHEN MATRIX RUN)
    ════════════════════════════════════════════════════════════════════════════════ -->
    <div id="matrix-section" class="mb-8 hidden">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <span>🏆</span> High-Priority Strategy Comparison Matrix
                    <span id="matrix-badge" class="text-xs font-mono px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">12 Configurations Tested</span>
                </h2>
                <p class="text-xs text-slate-400">Comparing 15m (75, 80, 85, 90%), 5m (75, 80, 85, 90%), and 30m (70, 75, 80, 85%) across the identical time period.</p>
            </div>
            <span class="text-xs text-slate-400 font-mono" id="matrix-exec-time"></span>
        </div>

        <div class="bg-[#131b2e] border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-900/90 text-slate-300 uppercase tracking-wider font-mono text-[11px] border-b border-slate-800">
                            <th class="py-3 px-3">Rank / Config</th>
                            <th class="py-3 px-3">Priority</th>
                            <th class="py-3 px-3 text-right">Trades</th>
                            <th class="py-3 px-3 text-right">Win Rate</th>
                            <th class="py-3 px-3 text-right">Profit Factor</th>
                            <th class="py-3 px-3 text-right">Net Points</th>
                            <th class="py-3 px-3 text-right">Net P&L (INR)</th>
                            <th class="py-3 px-3 text-right">Avg Duration</th>
                            <th class="py-3 px-3 text-right">Max Drawdown</th>
                            <th class="py-3 px-3 text-center">Exits (SL / Time / Rev)</th>
                            <th class="py-3 px-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="matrix-tbody" class="divide-y divide-slate-800/80 font-mono">
                        <!-- Populated by JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════════════════
         SECTION B: PERFORMANCE KPIS (FOR CURRENT ACTIVE CONFIGURATION)
    ════════════════════════════════════════════════════════════════════════════════ -->
    <div id="stats-section" class="space-y-6">

        <!-- Active Configuration Banner -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-3 bg-slate-900/70 border border-slate-800 rounded-xl text-xs">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-slate-400">Viewing Results For:</span>
                <span id="active-config-badge" class="font-bold text-white px-2 py-0.5 rounded bg-indigo-600/30 border border-indigo-500/40 font-mono">15m @ 75% Breadth</span>
                <span id="active-sl-badge" class="text-amber-300 px-2 py-0.5 rounded bg-amber-500/10 border border-amber-500/30 font-mono">SL: Normal Candle Low/High</span>
                <span id="active-date-badge" class="text-slate-300 font-mono"></span>
            </div>
            <div class="text-slate-400 font-mono" id="stat-exec-time"></div>
        </div>

        <!-- 8 Metric Cards Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">

            <!-- 1. Net P&L (INR) -->
            <div class="bg-[#131b2e] border border-slate-800 rounded-xl p-3">
                <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Total Net P&L</div>
                <div class="text-lg font-black mt-1 font-mono" id="kpi-net-pnl">₹0.00</div>
                <div class="text-[10px] text-slate-400 mt-0.5" id="kpi-lot-calc">1 Lot (65 Qty)</div>
            </div>

            <!-- 2. Net Points -->
            <div class="bg-[#131b2e] border border-slate-800 rounded-xl p-3">
                <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Net Points</div>
                <div class="text-lg font-black mt-1 font-mono" id="kpi-net-pts">0.00 pts</div>
                <div class="text-[10px] text-slate-400 mt-0.5" id="kpi-avg-pts">Avg 0.0 pts/tr</div>
            </div>

            <!-- 3. Win Rate -->
            <div class="bg-[#131b2e] border border-slate-800 rounded-xl p-3">
                <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Win Rate</div>
                <div class="text-lg font-black mt-1 font-mono text-emerald-400" id="kpi-win-rate">0.0%</div>
                <div class="text-[10px] text-slate-400 mt-0.5" id="kpi-win-loss-count">0W / 0L</div>
            </div>

            <!-- 4. Profit Factor -->
            <div class="bg-[#131b2e] border border-slate-800 rounded-xl p-3">
                <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Profit Factor</div>
                <div class="text-lg font-black mt-1 font-mono text-indigo-400" id="kpi-profit-factor">0.00</div>
                <div class="text-[10px] text-slate-400 mt-0.5" id="kpi-risk-reward">R:R 0.00</div>
            </div>

            <!-- 5. Avg Duration -->
            <div class="bg-[#131b2e] border border-slate-800 rounded-xl p-3">
                <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Avg Duration</div>
                <div class="text-lg font-black mt-1 font-mono text-amber-400" id="kpi-duration">0 min</div>
                <div class="text-[10px] text-slate-400 mt-0.5" id="kpi-candles-held">0 candles held</div>
            </div>

            <!-- 6. Total Trades -->
            <div class="bg-[#131b2e] border border-slate-800 rounded-xl p-3">
                <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Total Trades</div>
                <div class="text-lg font-black mt-1 font-mono text-white" id="kpi-total-trades">0</div>
                <div class="text-[10px] text-slate-400 mt-0.5" id="kpi-trades-per-day">0.0 / day</div>
            </div>

            <!-- 7. Max Drawdown -->
            <div class="bg-[#131b2e] border border-slate-800 rounded-xl p-3">
                <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Max Drawdown</div>
                <div class="text-lg font-black mt-1 font-mono text-rose-400" id="kpi-max-dd">0.0 pts</div>
                <div class="text-[10px] text-slate-400 mt-0.5" id="kpi-max-dd-inr">₹0.00</div>
            </div>

            <!-- 8. Avg Peak MFE vs MAE -->
            <div class="bg-[#131b2e] border border-slate-800 rounded-xl p-3">
                <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">MFE / MAE Avg</div>
                <div class="text-sm font-black mt-1.5 font-mono text-emerald-400" id="kpi-mfe">+0.0 pts</div>
                <div class="text-[10px] font-mono text-rose-400 mt-0.5" id="kpi-mae">-0.0 pts</div>
            </div>

        </div>

        <!-- ════════════════════════════════════════════════════════════════════════════════
             SECTION C: INTERACTIVE CHARTS
        ════════════════════════════════════════════════════════════════════════════════ -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

            <!-- Equity Curve Chart (2 cols) -->
            <div class="lg:col-span-2 bg-[#131b2e] border border-slate-800 rounded-2xl p-4 shadow-xl">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300 flex items-center gap-2">
                        <span>📈</span> Cumulative Equity Curve (Index Points &amp; INR)
                    </h3>
                    <div class="flex items-center gap-1.5 text-[11px] font-mono">
                        <span class="w-3 h-3 rounded-full bg-emerald-500 inline-block"></span>
                        <span class="text-slate-400">Equity Curve</span>
                    </div>
                </div>
                <div class="h-64 sm:h-72 w-full">
                    <canvas id="chart-equity"></canvas>
                </div>
            </div>

            <!-- Trade Duration vs Net Points Scatter (1 col) -->
            <div class="bg-[#131b2e] border border-slate-800 rounded-2xl p-4 shadow-xl">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300 flex items-center gap-2">
                        <span>⏱️</span> Duration vs Points Captured
                    </h3>
                    <span class="text-[10px] font-mono text-slate-400">Holding Efficiency</span>
                </div>
                <div class="h-64 sm:h-72 w-full">
                    <canvas id="chart-duration"></canvas>
                </div>
            </div>

        </div>

        <!-- ════════════════════════════════════════════════════════════════════════════════
             SECTION D: DETAILED TRADE LOG TABLE
        ════════════════════════════════════════════════════════════════════════════════ -->
        <div class="bg-[#131b2e] border border-slate-800 rounded-2xl overflow-hidden shadow-xl">

            <!-- Table Header Bar & Filter Tabs -->
            <div class="p-4 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <span>📋</span> Trade Execution Log
                        <span id="trades-count-badge" class="px-2 py-0.5 rounded text-xs font-mono bg-slate-800 text-slate-300">0 Trades</span>
                    </h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">Every confirmed entry, stop loss trigger, 15:00 square off, holding duration, and MFE/MAE excursions.</p>
                </div>

                <!-- Direction & Outcome Filter Pills -->
                <div class="flex items-center gap-1 bg-slate-900 p-1 rounded-xl border border-slate-800 text-[11px] font-mono flex-wrap">
                    <button type="button" onclick="filterTrades('ALL')" id="filter-all" class="trade-filter-btn px-2.5 py-1 rounded-lg bg-indigo-600 text-white font-bold">RETAINED TRADES</button>
                    <button type="button" onclick="filterTrades('WIN')" id="filter-win" class="trade-filter-btn px-2.5 py-1 rounded-lg text-slate-400 hover:text-emerald-400">WINS</button>
                    <button type="button" onclick="filterTrades('LOSS')" id="filter-loss" class="trade-filter-btn px-2.5 py-1 rounded-lg text-slate-400 hover:text-rose-400">LOSSES</button>
                    <button type="button" onclick="filterTrades('DISCARDED')" id="filter-discarded" class="trade-filter-btn px-2.5 py-1 rounded-lg text-purple-300 hover:text-white border border-purple-500/30 bg-purple-950/40 font-bold flex items-center gap-1" title="View all prolonged losing trades that were moved to Cost-to-Cost / discarded">
                        <span>✂️ DISCARDED LOSSES</span>
                        <span id="btn-discarded-count" class="px-1.5 py-0.2 rounded-full bg-purple-600 text-white text-[10px]">0</span>
                    </button>
                    <button type="button" onclick="filterTrades('EVERYTHING')" id="filter-everything" class="trade-filter-btn px-2.5 py-1 rounded-lg text-slate-400 hover:text-white" title="Show all trades including both retained and discarded stagnant losses">SHOW ALL (INC. DISCARDED)</button>
                    <button type="button" onclick="filterTrades('LONG')" id="filter-long" class="trade-filter-btn px-2.5 py-1 rounded-lg text-slate-400 hover:text-white">LONG ONLY</button>
                    <button type="button" onclick="filterTrades('SHORT')" id="filter-short" class="trade-filter-btn px-2.5 py-1 rounded-lg text-slate-400 hover:text-white">SHORT ONLY</button>
                </div>
            </div>

            <!-- Table Content -->
            <div class="overflow-x-auto custom-scrollbar max-h-[580px]">
                <table class="w-full text-left text-xs border-collapse font-mono">
                    <thead class="sticky top-0 bg-slate-900 text-slate-300 uppercase tracking-wider text-[11px] z-10 border-b border-slate-800">
                        <tr>
                            <th class="py-2.5 px-3">#</th>
                            <th class="py-2.5 px-3">Date</th>
                            <th class="py-2.5 px-3">Direction</th>
                            <th class="py-2.5 px-3">Signal Time &amp; Breadth</th>
                            <th class="py-2.5 px-3">Entry (Time @ Px)</th>
                            <th class="py-2.5 px-3">SL Level (Ref)</th>
                            <th class="py-2.5 px-3">Exit (Time @ Px)</th>
                            <th class="py-2.5 px-3">Exit Reason</th>
                            <th class="py-2.5 px-3 text-right">Duration</th>
                            <th class="py-2.5 px-3 text-right">MFE / MAE</th>
                            <th class="py-2.5 px-3 text-right">Net Pts</th>
                            <th class="py-2.5 px-3 text-right">Net P&amp;L (INR)</th>
                        </tr>
                    </thead>
                    <tbody id="trades-tbody" class="divide-y divide-slate-800/80">
                        <tr>
                            <td colspan="12" class="py-10 text-center text-slate-500 font-sans">
                                Select parameters above and click <strong>"Run Backtest"</strong> or <strong>"Run Priority Matrix"</strong> to inspect trades.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

    </div>

</div>

<!-- ════════════════════════════════════════════════════════════════════════════════
     JAVASCRIPT LOGIC & VISUALIZATION ENGINE
════════════════════════════════════════════════════════════════════════════════ -->
<script>
    // Global state
    let currentTrades = [];
    let discardedTrades = [];
    let currentFilter = 'ALL';
    let equityChartInstance = null;
    let durationChartInstance = null;

    function showDiscardedTrades() {
        filterTrades('DISCARDED');
        const tableSection = document.getElementById('trades-tbody');
        if (tableSection) {
            tableSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    // ── Parameter Selection Helpers ──
    function setTimeframe(mins) {
        document.getElementById('input-interval').value = mins;
        document.querySelectorAll('.tf-btn').forEach(btn => {
            btn.className = 'tf-btn py-1.5 text-xs font-bold rounded-lg transition text-slate-400 hover:text-white';
        });
        const activeBtn = document.getElementById(`btn-tf-${mins}`);
        if (activeBtn) {
            activeBtn.className = 'tf-btn py-1.5 text-xs font-bold rounded-lg transition bg-indigo-600 text-white shadow-sm';
        }
    }

    function setThreshold(pct) {
        document.getElementById('input-threshold').value = pct;
        document.querySelectorAll('.th-btn').forEach(btn => {
            btn.className = 'th-btn py-1.5 text-[11px] font-bold rounded-lg transition text-slate-400 hover:text-white';
        });
        const activeBtn = document.getElementById(`btn-th-${pct}`);
        if (activeBtn) {
            activeBtn.className = 'th-btn py-1.5 text-[11px] font-bold rounded-lg transition bg-emerald-600 text-white shadow-sm';
        }
    }

    function setMaxDuration(mins) {
        document.getElementById('input-max-duration').value = mins;
        document.querySelectorAll('.dur-btn').forEach(btn => {
            btn.className = 'dur-btn py-1.5 text-[11px] font-bold rounded-lg transition text-slate-400 hover:text-white';
        });
        const activeBtn = document.getElementById(`btn-dur-${mins}`);
        if (activeBtn) {
            activeBtn.className = 'dur-btn py-1.5 text-[11px] font-bold rounded-lg transition bg-cyan-600 text-white shadow-sm';
        }
        const lbl = document.getElementById('header-max-dur-label');
        if (lbl) {
            lbl.textContent = mins > 0 ? `${mins} mins` : 'Off (Unlimited)';
        }
    }

    function setMinEntryTime(timeStr) {
        document.getElementById('input-min-entry-time').value = timeStr;
        document.querySelectorAll('.min-entry-time-btn').forEach(btn => {
            btn.className = 'min-entry-time-btn px-2 py-0.5 text-[11px] font-mono font-bold rounded text-slate-400 hover:text-white';
        });
        const idMap = { '09:15': 'btn-min-entry-0915', '09:30': 'btn-min-entry-0930', '09:45': 'btn-min-entry-0945', '10:00': 'btn-min-entry-1000' };
        if (idMap[timeStr]) {
            const activeBtn = document.getElementById(idMap[timeStr]);
            if (activeBtn) activeBtn.className = 'min-entry-time-btn px-2 py-0.5 text-[11px] font-mono font-bold rounded bg-indigo-600 text-white shadow-sm';
        }
        const lbl = document.getElementById('header-min-entry-label');
        if (lbl) lbl.textContent = timeStr;
    }

    function updateCustomMinEntryTime(val) {
        const lbl = document.getElementById('header-min-entry-label');
        if (lbl) lbl.textContent = val || '09:15';
        document.querySelectorAll('.min-entry-time-btn').forEach(btn => {
            btn.className = 'min-entry-time-btn px-2 py-0.5 text-[11px] font-mono font-bold rounded text-slate-400 hover:text-white';
        });
    }

    function setMaxEntryTime(timeStr) {
        document.getElementById('input-max-entry-time').value = timeStr;
        document.querySelectorAll('.entry-time-btn').forEach(btn => {
            btn.className = 'entry-time-btn px-2 py-0.5 text-[11px] font-mono font-bold rounded text-slate-400 hover:text-white';
        });
        const idMap = { '13:30': 'btn-entry-1330', '14:00': 'btn-entry-1400', '14:30': 'btn-entry-1430', '14:45': 'btn-entry-1445', '15:00': 'btn-entry-1500' };
        if (idMap[timeStr]) {
            const activeBtn = document.getElementById(idMap[timeStr]);
            if (activeBtn) activeBtn.className = 'entry-time-btn px-2 py-0.5 text-[11px] font-mono font-bold rounded bg-indigo-600 text-white shadow-sm';
        }
        const lbl = document.getElementById('header-cutoff-label');
        if (lbl) lbl.textContent = timeStr;
    }

    function updateCustomEntryTime(val) {
        const lbl = document.getElementById('header-cutoff-label');
        if (lbl) lbl.textContent = val || '14:30';
        document.querySelectorAll('.entry-time-btn').forEach(btn => {
            btn.className = 'entry-time-btn px-2 py-0.5 text-[11px] font-mono font-bold rounded text-slate-400 hover:text-white';
        });
    }

    function setSlMode(mode) {
        document.getElementById('input-sl-mode').value = mode;
        const btnNorm = document.getElementById('btn-sl-normal');
        const btnHa = document.getElementById('btn-sl-ha');
        if (mode === 'normal') {
            btnNorm.className = 'sl-btn py-1.5 text-xs font-bold rounded-lg transition bg-amber-600 text-white shadow-sm';
            btnHa.className = 'sl-btn py-1.5 text-xs font-bold rounded-lg transition text-slate-400 hover:text-white';
        } else {
            btnNorm.className = 'sl-btn py-1.5 text-xs font-bold rounded-lg transition text-slate-400 hover:text-white';
            btnHa.className = 'sl-btn py-1.5 text-xs font-bold rounded-lg transition bg-amber-600 text-white shadow-sm';
        }
    }

    function setDatePreset(preset) {
        const toInput = document.getElementById('input-to-date');
        const fromInput = document.getElementById('input-from-date');
        const maxD = toInput.max;
        toInput.value = maxD;

        const d = new Date(maxD);
        if (preset === 'all') {
            fromInput.value = fromInput.min;
        } else if (preset === 'ytd') {
            fromInput.value = '2026-01-01';
        } else {
            d.setDate(d.getDate() - Number(preset));
            fromInput.value = d.toISOString().split('T')[0];
        }
    }

    // ── Single Backtest Execution ──
    async function runSingleBacktest() {
        const interval = document.getElementById('input-interval').value;
        const threshold = document.getElementById('input-threshold').value;
        const maxDuration = document.getElementById('input-max-duration').value;
        const minEntryTime = document.getElementById('input-min-entry-time').value;
        const maxEntryTime = document.getElementById('input-max-entry-time').value;
        const slMode = document.getElementById('input-sl-mode').value;
        const breadthMode = document.getElementById('input-breadth-mode').value;
        const lotSize = document.getElementById('input-lot-size').value;
        const maxTrades = document.getElementById('input-max-trades').value;
        const fromDate = document.getElementById('input-from-date').value;
        const toDate = document.getElementById('input-to-date').value;

        showLoader(true, `Running Backtest: ${interval}m @ ${threshold}% Breadth...`);

        try {
            const resp = await fetch('{{ route("api.backtests.heikin-breadth.run") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    interval,
                    threshold,
                    max_duration_mins: maxDuration,
                    min_entry_time: minEntryTime,
                    max_entry_time: maxEntryTime,
                    sl_mode: slMode,
                    breadth_mode: breadthMode,
                    lot_size: lotSize,
                    max_trades_per_day: maxTrades,
                    from_date: fromDate,
                    to_date: toDate
                })
            });

            if (!resp.ok) {
                const errText = await resp.text();
                showLoader(false);
                let msg = `Server Error (${resp.status})`;
                try {
                    const parsed = JSON.parse(errText);
                    if (parsed.message) msg += `: ${parsed.message}`;
                } catch(e) {
                    msg += `: ${errText.substring(0, 250)}`;
                }
                alert(msg);
                return;
            }

            const data = await resp.json();
            showLoader(false);

            if (!data.success) {
                alert(data.message || 'Error executing backtest.');
                return;
            }

            renderBacktestResults(data);

        } catch (err) {
            showLoader(false);
            console.error(err);
            alert('Failed to execute backtest. Please check the network console.');
        }
    }

    // ── Priority Matrix Execution (12 Configurations) ──
    async function runPriorityMatrix() {
        const maxDuration = document.getElementById('input-max-duration').value;
        const minEntryTime = document.getElementById('input-min-entry-time').value;
        const maxEntryTime = document.getElementById('input-max-entry-time').value;
        const slMode = document.getElementById('input-sl-mode').value;
        const breadthMode = document.getElementById('input-breadth-mode').value;
        const lotSize = document.getElementById('input-lot-size').value;
        const maxTrades = document.getElementById('input-max-trades').value;
        const fromDate = document.getElementById('input-from-date').value;
        const toDate = document.getElementById('input-to-date').value;

        showLoader(true, 'Running 12-Configuration Priority Matrix...', 'Benchmarking 15m (75,80,85,90%), 5m (75,80,85,90%), 30m (70,75,80,85%)...');

        try {
            const resp = await fetch('{{ route("api.backtests.heikin-breadth.matrix") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    max_duration_mins: maxDuration,
                    min_entry_time: minEntryTime,
                    max_entry_time: maxEntryTime,
                    sl_mode: slMode,
                    breadth_mode: breadthMode,
                    lot_size: lotSize,
                    max_trades_per_day: maxTrades,
                    from_date: fromDate,
                    to_date: toDate
                })
            });

            if (!resp.ok) {
                const errText = await resp.text();
                showLoader(false);
                let msg = `Server Error (${resp.status})`;
                try {
                    const parsed = JSON.parse(errText);
                    if (parsed.message) msg += `: ${parsed.message}`;
                } catch(e) {
                    msg += `: ${errText.substring(0, 250)}`;
                }
                alert(msg);
                return;
            }

            const data = await resp.json();
            showLoader(false);

            if (!data.success) {
                alert(data.message || 'Error executing matrix.');
                return;
            }

            renderMatrixTable(data);

            // Also auto-load the top-performing configuration in the matrix into the detailed trade view!
            if (data.matrix && data.matrix.length > 0) {
                const sorted = [...data.matrix].sort((a, b) => b.net_pts - a.net_pts);
                const best = sorted[0];
                setTimeframe(best.interval);
                setThreshold(best.threshold);
                runSingleBacktest();
            }

        } catch (err) {
            showLoader(false);
            console.error(err);
            alert('Failed to execute priority matrix.');
        }
    }

    // ── Render Priority Matrix Leaderboard ──
    function renderMatrixTable(data) {
        const matrixSection = document.getElementById('matrix-section');
        const tbody = document.getElementById('matrix-tbody');
        const execTimeEl = document.getElementById('matrix-exec-time');

        matrixSection.classList.remove('hidden');
        execTimeEl.textContent = `Completed in ${data.execution_time}s across ${data.total_trading_days} days`;

        // Sort by Net Points descending
        const rows = [...data.matrix].sort((a, b) => b.net_pts - a.net_pts);

        let html = '';
        rows.forEach((row, idx) => {
            const isWinner = (idx === 0);
            const netColor = row.net_pts >= 0 ? 'text-emerald-400 font-bold' : 'text-rose-400 font-bold';
            const pfColor = row.profit_factor >= 1.5 ? 'text-emerald-400 font-bold' : (row.profit_factor >= 1.0 ? 'text-slate-200' : 'text-rose-400');
            const winColor = row.win_rate >= 50 ? 'text-emerald-400 font-bold' : 'text-slate-300';

            const priorityBadge = (row.priority === 1)
                ? '<span class="px-1.5 py-0.5 rounded text-[10px] bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">Priority 1 (15m)</span>'
                : ((row.priority === 2)
                    ? '<span class="px-1.5 py-0.5 rounded text-[10px] bg-teal-500/20 text-teal-300 border border-teal-500/30">Priority 2 (5m)</span>'
                    : '<span class="px-1.5 py-0.5 rounded text-[10px] bg-amber-500/20 text-amber-300 border border-amber-500/30">Priority 3 (30m)</span>');

            html += `
                <tr class="hover:bg-slate-800/50 transition ${isWinner ? 'bg-emerald-950/20 border-l-4 border-l-emerald-500' : ''}">
                    <td class="py-2.5 px-3">
                        <div class="flex items-center gap-1.5">
                            ${isWinner ? '<span class="text-amber-400 text-sm">👑</span>' : `<span class="text-slate-500 font-mono">#${idx + 1}</span>`}
                            <strong class="text-white">${row.label}</strong>
                        </div>
                    </td>
                    <td class="py-2.5 px-3">${priorityBadge}</td>
                    <td class="py-2.5 px-3 text-right">${row.total_trades}</td>
                    <td class="py-2.5 px-3 text-right ${winColor}">${row.win_rate}%</td>
                    <td class="py-2.5 px-3 text-right ${pfColor}">${row.profit_factor}</td>
                    <td class="py-2.5 px-3 text-right ${netColor}">${row.net_pts >= 0 ? '+' : ''}${row.net_pts.toFixed(2)} pts</td>
                    <td class="py-2.5 px-3 text-right ${netColor}">₹${row.net_pnl.toLocaleString('en-IN', {maximumFractionDigits: 0})}</td>
                    <td class="py-2.5 px-3 text-right text-amber-300">${row.avg_duration}m</td>
                    <td class="py-2.5 px-3 text-right text-rose-400">-${row.max_drawdown_pts.toFixed(1)} pts</td>
                    <td class="py-2.5 px-3 text-center text-[10px] text-slate-400">
                        <span class="text-rose-400 font-bold">${row.sl_exits} SL</span> / 
                        <span class="text-amber-400 font-bold">${row.time_exits} Time</span> / 
                        <span class="text-purple-400 font-bold">${row.reversals} Rev</span>
                        ${row.discarded_losses ? `<div class="text-[10px] text-purple-400 font-mono mt-0.5">(-${row.discarded_losses} stagnant loss)</div>` : ''}
                    </td>
                    <td class="py-2.5 px-3 text-center">
                        <button type="button" onclick="inspectConfig(${row.interval}, ${row.threshold})" class="px-2 py-1 rounded bg-indigo-600/30 hover:bg-indigo-600 text-indigo-300 hover:text-white border border-indigo-500/40 text-[10px] transition cursor-pointer">
                            Inspect
                        </button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        matrixSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function inspectConfig(interval, threshold) {
        setTimeframe(interval);
        setThreshold(threshold);
        runSingleBacktest();
    }

    // ── Render Single Run Results (KPIs, Charts, Trades) ──
    function renderBacktestResults(data) {
        const stats = data.stats || {};
        const config = data.config || {};
        currentTrades = data.trades || [];
        discardedTrades = data.discarded_trades || [];
        const btnDiscCount = document.getElementById('btn-discarded-count');
        if (btnDiscCount) btnDiscCount.textContent = discardedTrades.length;

        // Update active banner
        const durText = config.max_duration_mins > 0 ? `Cut Losses > ${config.max_duration_mins}m` : 'Loss Duration Filter: Off';
        const windowText = `Window: ${config.min_entry_time || '09:15'} – ${config.max_entry_time || '14:30'}`;
        document.getElementById('active-config-badge').textContent = `${config.interval}m @ ${config.threshold}% Breadth (${durText} | ${windowText})`;
        document.getElementById('header-min-entry-label').textContent = config.min_entry_time || '09:15';
        document.getElementById('header-cutoff-label').textContent = config.max_entry_time || '14:30';
        document.getElementById('active-sl-badge').textContent = `SL: Confirmation Candle ${config.sl_mode === 'ha' ? 'Heikin Ashi' : 'Normal'} Low/High`;
        document.getElementById('active-date-badge').textContent = `${config.from_date} to ${config.to_date} (${config.total_trading_days} Days)`;
        document.getElementById('stat-exec-time').textContent = `Computed in ${data.execution_time}s`;

        // Discarded Stagnant Losses Badge
        const discardedCount = stats.discarded_losses_count || 0;
        const discardBadge = document.getElementById('badge-discarded-losses');
        if (discardBadge) {
            if (config.max_duration_mins > 0 && discardedCount > 0) {
                discardBadge.classList.remove('hidden');
                document.getElementById('header-discard-mins').textContent = config.max_duration_mins;
                document.getElementById('header-discard-count').textContent = discardedCount;
            } else {
                discardBadge.classList.add('hidden');
            }
        }

        // KPIs
        const netPnlEl = document.getElementById('kpi-net-pnl');
        const netPtsEl = document.getElementById('kpi-net-pts');

        netPnlEl.textContent = (stats.net_pnl >= 0 ? '+₹' : '-₹') + Math.abs(stats.net_pnl).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        netPnlEl.className = 'text-lg font-black mt-1 font-mono ' + (stats.net_pnl >= 0 ? 'text-emerald-400' : 'text-rose-400');

        netPtsEl.textContent = (stats.net_pts >= 0 ? '+' : '') + stats.net_pts.toFixed(2) + ' pts';
        netPtsEl.className = 'text-lg font-black mt-1 font-mono ' + (stats.net_pts >= 0 ? 'text-emerald-400' : 'text-rose-400');

        document.getElementById('kpi-avg-pts').textContent = `Avg ${stats.avg_pts >= 0 ? '+' : ''}${stats.avg_pts} pts/tr`;
        document.getElementById('kpi-win-rate').textContent = `${stats.win_rate}%`;
        const beText = stats.breakeven > 0 ? ` / ${stats.breakeven}BE` : '';
        const discText = discardedCount > 0 ? ` • ${discardedCount} cut` : '';
        document.getElementById('kpi-win-loss-count').textContent = `${stats.wins}W / ${stats.losses}L${beText}${discText}`;
        document.getElementById('kpi-profit-factor').textContent = stats.profit_factor;
        document.getElementById('kpi-risk-reward').textContent = `R:R 1:${stats.risk_reward_ratio}`;
        document.getElementById('kpi-duration').textContent = `${stats.avg_duration_mins} min`;
        document.getElementById('kpi-candles-held').textContent = `${stats.avg_candles_held} candles held`;
        document.getElementById('kpi-total-trades').textContent = stats.total_trades;
        document.getElementById('kpi-trades-per-day').textContent = `${stats.trades_per_day} / day`;
        document.getElementById('kpi-max-dd').textContent = `-${stats.max_drawdown_pts.toFixed(2)} pts`;
        document.getElementById('kpi-max-dd-inr').textContent = `-₹${Math.abs(stats.max_drawdown_pnl).toLocaleString('en-IN', {maximumFractionDigits: 0})}`;
        document.getElementById('kpi-mfe').textContent = `Peak +${stats.avg_mfe_pts} pts`;
        document.getElementById('kpi-mae').textContent = `Heat -${stats.avg_mae_pts} pts`;

        // Render Charts
        renderEquityChart(data.charts || {});
        renderDurationScatter(data.charts || {});

        // Render Trades Table
        renderTradesTable();
    }

    // ── Render Equity Curve Chart ──
    function renderEquityChart(charts) {
        const ctx = document.getElementById('chart-equity').getContext('2d');
        if (equityChartInstance) {
            equityChartInstance.destroy();
        }

        const ptsData = charts.equity_points || [];
        const labels = ptsData.map(p => p.x);
        const values = ptsData.map(p => p.y);

        equityChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Cumulative Net Points',
                    data: values,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.2,
                    pointRadius: values.length > 80 ? 0 : 2,
                    pointHoverRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const pts = context.parsed.y;
                                const inr = pts * 65;
                                return [`Net Points: ${pts >= 0 ? '+' : ''}${pts.toFixed(2)} pts`, `Realized P&L: ₹${inr.toLocaleString('en-IN', {maximumFractionDigits: 0})}`];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(51, 65, 85, 0.3)' },
                        ticks: { color: '#94a3b8', font: { size: 9 }, maxTicksLimit: 12 }
                    },
                    y: {
                        grid: { color: 'rgba(51, 65, 85, 0.3)' },
                        ticks: { color: '#94a3b8', font: { size: 10 } }
                    }
                }
            }
        });
    }

    // ── Render Duration Scatter Chart ──
    function renderDurationScatter(charts) {
        const ctx = document.getElementById('chart-duration').getContext('2d');
        if (durationChartInstance) {
            durationChartInstance.destroy();
        }

        const durations = charts.durations || [];
        const winPts = durations.filter(d => d.status === 'WIN').map(d => ({ x: d.duration, y: d.points }));
        const lossPts = durations.filter(d => d.status !== 'WIN').map(d => ({ x: d.duration, y: d.points }));

        durationChartInstance = new Chart(ctx, {
            type: 'scatter',
            data: {
                datasets: [
                    {
                        label: 'Winning Trades',
                        data: winPts,
                        backgroundColor: '#10b981',
                        pointRadius: 4
                    },
                    {
                        label: 'Losing Trades',
                        data: lossPts,
                        backgroundColor: '#f43f5e',
                        pointRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: '#cbd5e1', font: { size: 10 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Duration: ${context.parsed.x} mins | Points: ${context.parsed.y} pts`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        title: { display: true, text: 'Holding Duration (Minutes)', color: '#94a3b8', font: { size: 10 } },
                        grid: { color: 'rgba(51, 65, 85, 0.3)' },
                        ticks: { color: '#94a3b8', font: { size: 9 } }
                    },
                    y: {
                        title: { display: true, text: 'Points Captured', color: '#94a3b8', font: { size: 10 } },
                        grid: { color: 'rgba(51, 65, 85, 0.3)' },
                        ticks: { color: '#94a3b8', font: { size: 9 } }
                    }
                }
            }
        });
    }

    // ── Filter Trades Log ──
    function filterTrades(filter) {
        currentFilter = filter;
        document.querySelectorAll('.trade-filter-btn').forEach(btn => {
            btn.className = 'trade-filter-btn px-2.5 py-1 rounded-lg text-slate-400 hover:text-white';
        });
        const active = document.getElementById(`filter-${filter.toLowerCase()}`);
        if (active) {
            if (filter === 'DISCARDED') {
                active.className = 'trade-filter-btn px-2.5 py-1 rounded-lg bg-purple-600 text-white font-bold flex items-center gap-1 shadow-sm';
            } else {
                active.className = 'trade-filter-btn px-2.5 py-1 rounded-lg bg-indigo-600 text-white font-bold flex items-center gap-1 shadow-sm';
            }
        }
        renderTradesTable();
    }

    // ── Render Trades Log Table ──
    function renderTradesTable() {
        const tbody = document.getElementById('trades-tbody');
        const badge = document.getElementById('trades-count-badge');

        let filtered = [];
        if (currentFilter === 'ALL') filtered = currentTrades;
        else if (currentFilter === 'LONG') filtered = currentTrades.filter(t => t.direction === 'LONG');
        else if (currentFilter === 'SHORT') filtered = currentTrades.filter(t => t.direction === 'SHORT');
        else if (currentFilter === 'WIN') filtered = currentTrades.filter(t => t.status === 'WIN');
        else if (currentFilter === 'LOSS') filtered = currentTrades.filter(t => t.status === 'LOSS');
        else if (currentFilter === 'DISCARDED') filtered = discardedTrades;
        else if (currentFilter === 'EVERYTHING') filtered = [...currentTrades, ...discardedTrades];

        const totalAvailable = (currentFilter === 'DISCARDED') 
            ? discardedTrades.length 
            : (currentFilter === 'EVERYTHING' ? (currentTrades.length + discardedTrades.length) : currentTrades.length);
        
        badge.textContent = `${filtered.length} Trades (${currentFilter === 'ALL' ? 'Retained' : currentFilter})`;

        if (filtered.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="12" class="py-8 text-center text-slate-500 font-sans">
                        No trades found matching filter "${currentFilter}".
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        filtered.forEach(t => {
            const isDiscarded = Boolean(t.is_discarded);
            const isLong = (t.direction === 'LONG');
            const dirBadge = isLong
                ? '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">LONG 🟢</span>'
                : '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30">SHORT 🔴</span>';

            const pts = Number(t.points);
            const inr = Number(t.pnl);
            const isWin = (pts > 0);
            const isLoss = (pts < 0);
            const ptsColor = isDiscarded 
                ? 'text-purple-300 line-through' 
                : (isWin ? 'text-emerald-400 font-bold' : (isLoss ? 'text-rose-400 font-bold' : 'text-slate-400'));

            let reasonBadge = '';
            if (isDiscarded) {
                reasonBadge = `<span class="px-1.5 py-0.5 rounded text-[10px] bg-purple-500/20 text-purple-300 border border-purple-500/40 font-bold" title="${t.discard_reason || 'Stagnant Loss Cut'}">✂️ DISCARDED (${t.duration_mins}m &gt; limit)</span>`;
            } else if (t.exit_reason === 'STOP_LOSS') {
                reasonBadge = '<span class="px-1.5 py-0.5 rounded text-[10px] bg-rose-500/20 text-rose-300 border border-rose-500/30">STOP LOSS 🛑</span>';
            } else if (t.exit_reason === 'TIME_EXIT_1500') {
                reasonBadge = '<span class="px-1.5 py-0.5 rounded text-[10px] bg-amber-500/20 text-amber-300 border border-amber-500/30">15:00 TIME ⏰</span>';
            } else {
                reasonBadge = '<span class="px-1.5 py-0.5 rounded text-[10px] bg-purple-500/20 text-purple-300 border border-purple-500/30">REVERSAL 🔄</span>';
            }

            const rowClass = isDiscarded ? 'bg-purple-950/20 border-l-4 border-l-purple-500 hover:bg-purple-950/30' : 'hover:bg-slate-800/40';
            const tradeIdText = isDiscarded 
                ? `<span class="text-purple-400 font-bold">#D${t.id}</span> <span class="text-[9px] text-purple-400 font-sans uppercase">Cost-to-Cost</span>` 
                : `#${t.id} <span class="text-[10px] text-slate-500 font-sans">(${t.trade_no_in_day}/2)</span>`;

            const ptsDisplay = isDiscarded
                ? `<div><span class="text-purple-300 line-through font-mono">${pts.toFixed(2)} pts</span><div class="text-[9px] text-purple-400 font-sans font-bold">AVOIDED LOSS</div></div>`
                : `${pts >= 0 ? '+' : ''}${pts.toFixed(2)}`;

            const inrDisplay = isDiscarded
                ? `<div><span class="text-purple-300 line-through font-mono">-₹${Math.abs(inr).toLocaleString('en-IN', {maximumFractionDigits: 0})}</span><div class="text-[9px] text-purple-400 font-sans font-bold">COST-TO-COST</div></div>`
                : `${inr >= 0 ? '+₹' : '-₹'}${Math.abs(inr).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

            html += `
                <tr class="${rowClass} transition">
                    <td class="py-2 px-3 text-slate-400 font-mono">
                        ${tradeIdText}
                    </td>
                    <td class="py-2 px-3 text-white font-mono">${t.date}</td>
                    <td class="py-2 px-3">${dirBadge}</td>
                    <td class="py-2 px-3">
                        <span class="text-white font-mono font-bold">${t.signal_time}</span>
                        <div class="text-[10px] text-slate-400">${t.signal_breadth_count}% cnt • ${t.signal_breadth_weight}% wt</div>
                    </td>
                    <td class="py-2 px-3">
                        <span class="text-slate-300">${t.entry_time}</span> @ <strong class="text-white">${t.entry_price.toFixed(2)}</strong>
                    </td>
                    <td class="py-2 px-3 font-mono">
                        <span class="text-amber-300">${t.sl_price.toFixed(2)}</span>
                        <span class="text-[9px] text-slate-500 uppercase ml-1">(${t.sl_mode})</span>
                    </td>
                    <td class="py-2 px-3">
                        <span class="text-slate-300">${t.exit_time}</span> @ <strong class="text-white">${t.exit_price.toFixed(2)}</strong>
                    </td>
                    <td class="py-2 px-3">${reasonBadge}</td>
                    <td class="py-2 px-3 text-right">
                        <span class="text-amber-300 font-bold">${t.duration_mins}m</span>
                        <div class="text-[10px] text-slate-500">${t.candles_held} candles</div>
                    </td>
                    <td class="py-2 px-3 text-right font-mono text-[11px]">
                        <span class="text-emerald-400">+${t.mfe_pts.toFixed(1)}</span> / 
                        <span class="text-rose-400">-${t.mae_pts.toFixed(1)}</span>
                    </td>
                    <td class="py-2 px-3 text-right font-mono ${ptsColor}">
                        ${ptsDisplay}
                    </td>
                    <td class="py-2 px-3 text-right font-mono ${ptsColor}">
                        ${inrDisplay}
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    // ── Export CSV ──
    function exportTradesCsv() {
        const interval = document.getElementById('input-interval').value;
        const threshold = document.getElementById('input-threshold').value;
        const maxDuration = document.getElementById('input-max-duration').value;
        const minEntryTime = document.getElementById('input-min-entry-time').value;
        const maxEntryTime = document.getElementById('input-max-entry-time').value;
        const slMode = document.getElementById('input-sl-mode').value;
        const breadthMode = document.getElementById('input-breadth-mode').value;
        const lotSize = document.getElementById('input-lot-size').value;
        const maxTrades = document.getElementById('input-max-trades').value;
        const fromDate = document.getElementById('input-from-date').value;
        const toDate = document.getElementById('input-to-date').value;

        const params = new URLSearchParams({
            interval,
            threshold,
            max_duration_mins: maxDuration,
            min_entry_time: minEntryTime,
            max_entry_time: maxEntryTime,
            sl_mode: slMode,
            breadth_mode: breadthMode,
            lot_size: lotSize,
            max_trades_per_day: maxTrades,
            from_date: fromDate,
            to_date: toDate
        });

        window.location.href = `{{ route('api.backtests.heikin-breadth.export') }}?${params.toString()}`;
    }

    // ── Loader Helper ──
    function showLoader(show, title = null, subtitle = null) {
        const el = document.getElementById('loading-indicator');
        const btnRun = document.getElementById('btn-run-backtest');
        const btnMatrix = document.getElementById('btn-run-matrix');

        if (show) {
            el.classList.remove('hidden');
            if (title) document.getElementById('loading-title').textContent = title;
            if (subtitle) document.getElementById('loading-subtitle').textContent = subtitle;
            btnRun.disabled = true;
            btnMatrix.disabled = true;
            btnRun.classList.add('opacity-50', 'cursor-not-allowed');
            btnMatrix.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            el.classList.add('hidden');
            btnRun.disabled = false;
            btnMatrix.disabled = false;
            btnRun.classList.remove('opacity-50', 'cursor-not-allowed');
            btnMatrix.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }

    // ── Auto-run single backtest on page load ──
    document.addEventListener('DOMContentLoaded', () => {
        runSingleBacktest();
    });
</script>
@endsection
