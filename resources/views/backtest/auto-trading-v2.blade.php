@extends('layouts.app')

@section('title', 'NIFTY Automated Trading Backtest & Performance Intelligence')

@section('content')
<div class="min-h-screen bg-[#070b14] text-gray-200 p-3 sm:p-4 font-sans">
    <div class="max-w-[1780px] mx-auto space-y-4">

        <!-- 1. Strategy Command Header & Navigation -->
        <div class="bg-[#0e1424] border border-gray-800 rounded-xl p-3.5 shadow-xl flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <div class="flex items-center gap-2.5 flex-wrap">
                    <span class="text-xl sm:text-2xl font-black tracking-tight text-white flex items-center gap-2">
                        <span>🚀</span>
                        <span class="bg-gradient-to-r from-sky-400 via-indigo-300 to-emerald-400 bg-clip-text text-transparent">AUTOMATED TRADING ENGINE BACKTEST</span>
                    </span>
                    <span class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-sky-950 text-sky-300 border border-sky-800 shadow-xs">
                        v2 INSTITUTIONAL RULES
                    </span>
                    <span class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-amber-950 text-amber-300 border border-amber-800 shadow-xs">
                        CRON SYNCHRONIZED
                    </span>
                </div>
                <div class="text-xs text-gray-400 mt-1 flex items-center gap-2 flex-wrap font-mono">
                    <span>⏰ Session Window: <strong class="text-sky-300">09:35 – 14:45</strong></span>
                    <span>&bull;</span>
                    <span>📊 Breadth: <strong class="text-emerald-400">≥80% Surge</strong></span>
                    <span>&bull;</span>
                    <span>🌊 Flow: <strong class="text-emerald-400">≥70% Wt</strong></span>
                    <span>&bull;</span>
                    <span>🛡️ Veto Wall: <strong class="text-rose-400">≥25%</strong></span>
                    <span>&bull;</span>
                    <span>🚀 Squeeze: <strong class="text-amber-400">≤15%</strong></span>
                    <span>&bull;</span>
                    <span>⏱️ Time-Stop: <strong class="text-amber-300">45m 1:1 Trail</strong></span>
                    <span>&bull;</span>
                    <span>📦 Sizing: <strong class="text-cyan-300">3 Lots (195 QTY)</strong></span>
                </div>
            </div>

            <!-- Header Quick Actions -->
            <div class="flex items-center gap-2 shrink-0 flex-wrap">
                <a href="{{ route('nifty.multi.timeframe.monitor.v2') }}" class="px-3 py-1.5 rounded-lg bg-[#141b2e] hover:bg-[#1a233c] text-sky-300 border border-sky-800/80 text-xs font-mono font-bold flex items-center gap-1.5 transition shadow-xs">
                    <span>📺 Live Bar Replay V2</span>
                </a>
                <a href="{{ route('nifty.trade.telemetry.v2') }}" class="px-3 py-1.5 rounded-lg bg-[#141b2e] hover:bg-[#1a233c] text-purple-300 border border-purple-800/80 text-xs font-mono font-bold flex items-center gap-1.5 transition shadow-xs">
                    <span>📑 5M Telemetry Report</span>
                </a>
                <button type="button" onclick="openRecalculateModal()" class="px-3 py-1.5 rounded-lg bg-emerald-950 hover:bg-emerald-900 text-emerald-300 border border-emerald-700/80 text-xs font-mono font-bold flex items-center gap-1.5 transition shadow-xs">
                    <span>⚡ Run Backend Recalculation</span>
                </button>
            </div>
        </div>

        <!-- 2. Global KPI Performance Cards (Top Row) -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5">
            <!-- Net P&L Rupees -->
            <div class="bg-[#0e1424] border border-gray-800 rounded-xl p-3 shadow-lg flex flex-col justify-between">
                <span class="text-[10px] font-mono text-gray-400 uppercase tracking-wider block">Net Realized P&amp;L</span>
                <div class="my-1">
                    <span class="text-xl sm:text-2xl font-black font-mono {{ $netPnlRupees >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                        {{ $netPnlRupees >= 0 ? '+' : '' }}₹{{ number_format($netPnlRupees, 0) }}
                    </span>
                    <span class="text-xs font-mono block font-bold {{ $netPnlPoints >= 0 ? 'text-emerald-300/80' : 'text-rose-300/80' }}">
                        {{ $netPnlPoints >= 0 ? '+' : '' }}{{ number_format($netPnlPoints, 1) }} pts
                    </span>
                </div>
                <span class="text-[9.5px] text-gray-500 font-mono">Gross: +₹{{ number_format($grossProfit, 0) }} / -₹{{ number_format($grossLoss, 0) }}</span>
            </div>

            <!-- Win Rate -->
            <div class="bg-[#0e1424] border border-gray-800 rounded-xl p-3 shadow-lg flex flex-col justify-between">
                <span class="text-[10px] font-mono text-gray-400 uppercase tracking-wider block">Trade Win Rate</span>
                <div class="my-1">
                    <span class="text-xl sm:text-2xl font-black font-mono {{ $winRate >= 50 ? 'text-emerald-400' : 'text-amber-400' }}">
                        {{ $winRate }}%
                    </span>
                    <span class="text-xs font-mono text-gray-300 block">
                        <strong class="text-emerald-400">{{ $winTrades }}W</strong> &bull; <strong class="text-rose-400">{{ $lossTrades }}L</strong>
                    </span>
                </div>
                <span class="text-[9.5px] text-gray-500 font-mono">Total Trades: {{ $totalTrades }}</span>
            </div>

            <!-- Day Win Rate -->
            <div class="bg-[#0e1424] border border-gray-800 rounded-xl p-3 shadow-lg flex flex-col justify-between">
                <span class="text-[10px] font-mono text-gray-400 uppercase tracking-wider block">Day Win Rate</span>
                <div class="my-1">
                    <span class="text-xl sm:text-2xl font-black font-mono {{ ($winDays / max(1, $tradedDays)) >= 0.5 ? 'text-emerald-400' : 'text-amber-400' }}">
                        {{ $tradedDays > 0 ? round(($winDays / $tradedDays) * 100, 1) : 0 }}%
                    </span>
                    <span class="text-xs font-mono text-gray-300 block">
                        <strong class="text-emerald-400">{{ $winDays }} Green</strong> &bull; <strong class="text-rose-400">{{ $lossDays }} Red</strong>
                    </span>
                </div>
                <span class="text-[9.5px] text-gray-500 font-mono">{{ $totalDays }} Days ({{ $noTradeDays }} Flat/Standby)</span>
            </div>

            <!-- Profit Factor -->
            <div class="bg-[#0e1424] border border-gray-800 rounded-xl p-3 shadow-lg flex flex-col justify-between">
                <span class="text-[10px] font-mono text-gray-400 uppercase tracking-wider block">Profit Factor</span>
                <div class="my-1">
                    <span class="text-xl sm:text-2xl font-black font-mono {{ $profitFactor >= 1.5 ? 'text-emerald-400' : ($profitFactor >= 1.0 ? 'text-amber-400' : 'text-rose-400') }}">
                        {{ $profitFactor }}
                    </span>
                    <span class="text-xs font-mono text-gray-300 block">
                        W/L Ratio: {{ $winLossRatio }}
                    </span>
                </div>
                <span class="text-[9.5px] text-gray-500 font-mono">Avg Win: +₹{{ number_format($avgWinRs) }} / Loss: -₹{{ number_format($avgLossRs) }}</span>
            </div>

            <!-- Max Drawdown -->
            <div class="bg-[#0e1424] border border-gray-800 rounded-xl p-3 shadow-lg flex flex-col justify-between">
                <span class="text-[10px] font-mono text-gray-400 uppercase tracking-wider block">Max Drawdown</span>
                <div class="my-1">
                    <span class="text-xl sm:text-2xl font-black font-mono text-rose-400">
                        -₹{{ number_format($maxDrawdown) }}
                    </span>
                    <span class="text-xs font-mono text-gray-300 block">
                        Capital Peak to Trough
                    </span>
                </div>
                <span class="text-[9.5px] text-emerald-400 font-mono">Risk Strictly Managed</span>
            </div>

            <!-- Strategy Setups -->
            <div class="bg-[#0e1424] border border-gray-800 rounded-xl p-3 shadow-lg flex flex-col justify-between">
                <span class="text-[10px] font-mono text-gray-400 uppercase tracking-wider block">Tactical Breakdown</span>
                <div class="my-1 space-y-0.5 text-xs font-mono">
                    <div class="flex justify-between">
                        <span class="text-gray-400">🚀 Squeeze:</span>
                        <span class="font-bold text-amber-300">{{ $squeezeTrades }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">🌟 Confluence:</span>
                        <span class="font-bold text-cyan-300">{{ $confluenceTrades }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">⏱️ 1:1 Trailed:</span>
                        <span class="font-bold text-purple-300">{{ $stagnationExits }}</span>
                    </div>
                </div>
                <span class="text-[9.5px] text-gray-500 font-mono">Strict Noise &amp; Wall Filters</span>
            </div>
        </div>

        <!-- 3. Filter Controls Bar -->
        <div class="bg-[#0e1424] border border-gray-800 rounded-xl p-3 shadow-md">
            <form action="{{ route('automated.trading.backtest') }}" method="GET" class="flex flex-wrap items-center gap-2.5 text-xs font-mono">
                <!-- Preset Quick Buttons -->
                <div class="flex items-center gap-1 bg-[#141b2e] p-0.5 rounded border border-gray-800">
                    <a href="{{ route('automated.trading.backtest', ['range' => 'all']) }}" class="px-2 py-1 rounded {{ $rangePreset === 'all' && !$fromDate ? 'bg-sky-600 text-white font-bold' : 'text-gray-400 hover:text-white' }}">All ({{ $totalDays }} Days)</a>
                    <a href="{{ route('automated.trading.backtest', ['range' => '1y']) }}" class="px-2 py-1 rounded {{ $rangePreset === '1y' ? 'bg-sky-600 text-white font-bold' : 'text-gray-400 hover:text-white' }}">1 Year</a>
                    <a href="{{ route('automated.trading.backtest', ['range' => '90d']) }}" class="px-2 py-1 rounded {{ $rangePreset === '90d' ? 'bg-sky-600 text-white font-bold' : 'text-gray-400 hover:text-white' }}">90D</a>
                    <a href="{{ route('automated.trading.backtest', ['range' => '30d']) }}" class="px-2 py-1 rounded {{ $rangePreset === '30d' ? 'bg-sky-600 text-white font-bold' : 'text-gray-400 hover:text-white' }}">30D</a>
                    <a href="{{ route('automated.trading.backtest', ['range' => '7d']) }}" class="px-2 py-1 rounded {{ $rangePreset === '7d' ? 'bg-sky-600 text-white font-bold' : 'text-gray-400 hover:text-white' }}">7D</a>
                    <a href="{{ route('automated.trading.backtest', ['range' => 'this_month']) }}" class="px-2 py-1 rounded {{ $rangePreset === 'this_month' ? 'bg-sky-600 text-white font-bold' : 'text-gray-400 hover:text-white' }}">This Month</a>
                </div>

                <!-- Custom Dates -->
                <div class="flex items-center gap-1.5">
                    <span class="text-gray-400">From:</span>
                    <input type="date" name="from" value="{{ $fromDate }}" class="bg-[#141b2e] border border-gray-700 rounded px-2 py-1 text-white text-xs">
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="text-gray-400">To:</span>
                    <input type="date" name="to" value="{{ $toDate }}" class="bg-[#141b2e] border border-gray-700 rounded px-2 py-1 text-white text-xs">
                </div>

                <!-- Outcome Filter -->
                <div class="flex items-center gap-1.5">
                    <span class="text-gray-400">Outcome:</span>
                    <select name="status" class="bg-[#141b2e] border border-gray-700 rounded px-2 py-1 text-white text-xs">
                        <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>All Outcomes</option>
                        <option value="WIN" {{ $statusFilter === 'WIN' ? 'selected' : '' }}>🟢 Winning Days Only</option>
                        <option value="LOSS" {{ $statusFilter === 'LOSS' ? 'selected' : '' }}>🔴 Losing Days Only</option>
                        <option value="NO_TRADE" {{ $statusFilter === 'NO_TRADE' ? 'selected' : '' }}>⚪ Standby / Flat Days Only</option>
                    </select>
                </div>

                <!-- Setup Filter -->
                <div class="flex items-center gap-1.5">
                    <span class="text-gray-400">Setup:</span>
                    <select name="setup" class="bg-[#141b2e] border border-gray-700 rounded px-2 py-1 text-white text-xs">
                        <option value="all" {{ $setupFilter === 'all' ? 'selected' : '' }}>All Setups</option>
                        <option value="SQUEEZE_BREAKOUT" {{ $setupFilter === 'SQUEEZE_BREAKOUT' ? 'selected' : '' }}>🚀 Squeeze Breakout</option>
                        <option value="GRADE_A_CONFLUENCE" {{ $setupFilter === 'GRADE_A_CONFLUENCE' ? 'selected' : '' }}>🌟 Grade A+ Confluence</option>
                        <option value="STANDARD" {{ $setupFilter === 'STANDARD' ? 'selected' : '' }}>🎯 Standard Breadth</option>
                    </select>
                </div>

                <button type="submit" class="px-3 py-1 rounded bg-sky-600 hover:bg-sky-500 text-white font-bold transition">Apply Filters</button>
                <a href="{{ route('automated.trading.backtest') }}" class="px-2.5 py-1 rounded bg-gray-800 hover:bg-gray-700 text-gray-300 transition">Reset</a>
            </form>
        </div>

        <!-- 4. Monthly & Weekly Performance Rollups -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
            <!-- Monthly Aggregation (7 cols) -->
            <div class="lg:col-span-7 bg-[#0e1424] border border-gray-800 rounded-xl p-3.5 shadow-md flex flex-col">
                <div class="flex items-center justify-between pb-2 border-b border-gray-800 mb-2.5">
                    <span class="text-xs font-black tracking-wide text-sky-400 font-mono flex items-center gap-1.5">
                        <span>📅 MONTHLY PERFORMANCE ROLLUP</span>
                        <span class="px-1.5 py-0.2 rounded text-[9.5px] bg-sky-950 text-sky-300 border border-sky-800">{{ count($monthlyStats) }} Months</span>
                    </span>
                    <span class="text-[10px] text-gray-500 font-mono">Aggregated Net P&amp;L</span>
                </div>
                <div class="overflow-y-auto max-h-[310px] pr-1.5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                        @forelse($monthlyStats as $mKey => $mStat)
                            <div class="bg-[#141b2e] border border-gray-800 rounded-lg p-2.5 font-mono text-xs flex flex-col justify-between shadow-xs hover:border-gray-700 transition">
                                <div class="flex items-center justify-between pb-1 border-b border-gray-800">
                                    <span class="font-bold text-white text-[11.5px]">{{ $mStat['month_name'] }}</span>
                                    <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold {{ $mStat['net_rupees'] >= 0 ? 'bg-emerald-950 text-emerald-300 border border-emerald-700' : 'bg-rose-950 text-rose-300 border border-rose-700' }}">
                                        {{ $mStat['win_rate'] }}% Win
                                    </span>
                                </div>
                                <div class="my-2">
                                    <span class="text-base font-black block {{ $mStat['net_rupees'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                        {{ $mStat['net_rupees'] >= 0 ? '+' : '' }}₹{{ number_format($mStat['net_rupees'], 0) }}
                                    </span>
                                    <span class="text-[10.5px] text-gray-400">
                                        {{ $mStat['net_points'] >= 0 ? '+' : '' }}{{ number_format($mStat['net_points'], 1) }} pts
                                    </span>
                                </div>
                                <div class="text-[10px] text-gray-500 flex justify-between border-t border-gray-800/60 pt-1">
                                    <span>{{ $mStat['total_trades'] }} Trades ({{ $mStat['traded_days'] }}D)</span>
                                    <span><strong class="text-emerald-400">{{ $mStat['win_days'] }}W</strong> / <strong class="text-rose-400">{{ $mStat['loss_days'] }}L</strong></span>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-3 text-center py-4 text-gray-500 font-mono text-xs">No monthly data available.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Weekly Aggregation (5 cols) -->
            <div class="lg:col-span-5 bg-[#0e1424] border border-gray-800 rounded-xl p-3.5 shadow-md flex flex-col">
                <div class="flex items-center justify-between pb-2 border-b border-gray-800 mb-2.5">
                    <span class="text-xs font-black tracking-wide text-indigo-400 font-mono flex items-center gap-1.5">
                        <span>🗓️ WEEKLY DRILL-DOWN</span>
                        <span class="px-1.5 py-0.2 rounded text-[9.5px] bg-indigo-950 text-indigo-300 border border-indigo-800">{{ count($weeklyStats) }} Weeks</span>
                    </span>
                    <span class="text-[10px] text-gray-500 font-mono">Week-by-Week Progress</span>
                </div>
                <div class="overflow-y-auto max-h-[310px] pr-1.5 space-y-1.5 font-mono text-xs">
                    @forelse($weeklyStats as $wKey => $wStat)
                        <div class="bg-[#141b2e] border border-gray-800/90 hover:border-gray-700/80 rounded-lg p-2.5 flex items-center justify-between shadow-xs transition">
                            <div class="space-y-0.5">
                                <span class="text-white font-bold block text-[11.5px]">{{ $wStat['week_label'] }}</span>
                                <span class="text-[10px] text-gray-400">
                                    {{ $wStat['total_trades'] }} trades &bull; 
                                    <strong class="text-emerald-400">{{ $wStat['win_days'] }}W</strong> / 
                                    <strong class="text-rose-400">{{ $wStat['loss_days'] }}L</strong>
                                </span>
                            </div>
                            <div class="text-right">
                                <span class="font-black text-[12px] block {{ $wStat['net_rupees'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $wStat['net_rupees'] >= 0 ? '+' : '' }}₹{{ number_format($wStat['net_rupees'], 0) }}
                                </span>
                                <span class="text-[10px] text-gray-400 font-medium">
                                    {{ $wStat['net_points'] >= 0 ? '+' : '' }}{{ number_format($wStat['net_points'], 1) }} pts
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-gray-500">No weekly data.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- 5. Day-by-Day Historical Log & Forensics Table -->
        <div class="bg-[#0e1424] border border-gray-800 rounded-xl p-3.5 shadow-xl">
            <div class="flex items-center justify-between pb-2.5 border-b border-gray-800 mb-3 flex-wrap gap-2">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-black tracking-wide text-white font-mono flex items-center gap-1.5">
                        <span>📜 DAY-BY-DAY TRADE FORENSICS &amp; REASONING</span>
                    </span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-sky-950 text-sky-300 border border-sky-800">
                        {{ $days->count() }} Sessions Listed
                    </span>
                </div>
                <span class="text-[10.5px] text-gray-400 font-mono">
                    Click <strong>"🔍 5M Forensics"</strong> on any session to inspect candle-by-candle criteria &amp; decision reasons.
                </span>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left font-mono text-xs">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-800 text-[10.5px] uppercase bg-[#141b2e]/60">
                            <th class="py-2 px-2.5">Date</th>
                            <th class="py-2 px-2.5">Nifty Spot (O/H/L/C)</th>
                            <th class="py-2 px-2.5 text-center">Trades</th>
                            <th class="py-2 px-2.5">Executions &amp; Setup Forensics</th>
                            <th class="py-2 px-2.5 text-right">Net Points</th>
                            <th class="py-2 px-2.5 text-right">Net P&amp;L (₹)</th>
                            <th class="py-2 px-2.5 text-center">Day Status</th>
                            <th class="py-2 px-2.5 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/80">
                        @forelse($days as $day)
                            <tr class="{{ $loop->even ? 'bg-[#0a0f1d]' : 'bg-[#101728]' }} hover:bg-[#162036] transition-colors border-l-4 {{ $day->day_status === 'WIN' ? 'border-l-emerald-500' : ($day->day_status === 'LOSS' ? 'border-l-rose-500' : ($day->day_status === 'SCRATCH' ? 'border-l-amber-500' : 'border-l-slate-700')) }}">
                                <!-- Date & Day of Week -->
                                <td class="py-3 px-3 font-bold whitespace-nowrap">
                                    <div class="inline-flex flex-col items-start px-2 py-1 rounded bg-[#151e32] border border-gray-700/70 shadow-xs">
                                        <div class="text-[13px] font-black text-white tracking-wide">{{ $day->date->format('Y-m-d') }}</div>
                                        <div class="text-[10px] text-sky-400 font-semibold uppercase tracking-wider">{{ $day->date->format('l') }}</div>
                                    </div>
                                </td>

                                <!-- Nifty Spot -->
                                <td class="py-3 px-2.5 text-gray-300 text-[11px] whitespace-nowrap">
                                    @if($day->nifty_open)
                                        <div class="space-y-0.5">
                                            <div><span class="text-gray-500 font-medium">O:</span> <span class="font-bold text-gray-200">{{ number_format($day->nifty_open, 1) }}</span> &bull; <span class="text-gray-500 font-medium">C:</span> <span class="font-bold text-gray-200">{{ number_format($day->nifty_close, 1) }}</span></div>
                                            <div><span class="text-gray-500 font-medium">H:</span> <span class="text-emerald-400 font-medium">{{ number_format($day->nifty_high, 1) }}</span> &bull; <span class="text-gray-500 font-medium">L:</span> <span class="text-rose-400 font-medium">{{ number_format($day->nifty_low, 1) }}</span></div>
                                        </div>
                                    @else
                                        <span class="text-gray-500">--</span>
                                    @endif
                                </td>

                                <!-- Total Trades -->
                                <td class="py-3 px-2.5 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-black shadow-xs {{ $day->total_trades > 0 ? 'bg-sky-950/80 text-sky-300 border border-sky-700' : 'bg-gray-900/60 text-gray-500 border border-gray-800' }}">
                                        {{ $day->total_trades }}
                                    </span>
                                </td>

                                <!-- Executions & Setup Forensics -->
                                <td class="py-3 px-2.5 min-w-[360px]">
                                    @if($day->trades->isNotEmpty())
                                        <div class="space-y-2">
                                            @foreach($day->trades as $tr)
                                                <div class="bg-[#141d30] border {{ $tr->pnl_rupees >= 0 ? 'border-emerald-900/60' : 'border-rose-900/60' }} rounded-md p-2 text-[11px] space-y-1.5 shadow-sm">
                                                    <div class="flex items-center justify-between flex-wrap gap-1.5">
                                                        <div class="flex items-center gap-1.5 flex-wrap">
                                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-black bg-gray-800 text-gray-300 border border-gray-700">
                                                                #{{ $loop->iteration }}
                                                            </span>
                                                            <span class="px-1.5 py-0.2 rounded text-[9.5px] font-black {{ $tr->direction === 'BULL' ? 'bg-emerald-950 text-emerald-300 border border-emerald-700' : 'bg-rose-950 text-rose-300 border border-rose-700' }}">
                                                                {{ $tr->direction === 'BULL' ? '🟢 BULL' : '🔴 BEAR' }} {{ $tr->action }}
                                                            </span>
                                                            <span class="font-black text-white text-[11.5px]">{{ $tr->instrument }}</span>
                                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-cyan-950 text-cyan-300 border border-cyan-800">
                                                                {{ $tr->setup_type }}
                                                            </span>
                                                        </div>
                                                        <span class="font-black font-mono text-[11.5px] px-1.5 py-0.5 rounded {{ $tr->pnl_rupees >= 0 ? 'bg-emerald-950/60 text-emerald-300 border border-emerald-800/80' : 'bg-rose-950/60 text-rose-300 border border-rose-800/80' }}">
                                                            {{ $tr->pnl_rupees >= 0 ? '+' : '' }}₹{{ number_format($tr->pnl_rupees) }} ({{ $tr->pnl_points >= 0 ? '+' : '' }}{{ $tr->pnl_points }}p)
                                                        </span>
                                                    </div>
                                                    <div class="text-[10px] text-gray-300 flex items-center justify-between flex-wrap gap-1 bg-[#0f1626] px-2 py-1 rounded border border-gray-800/60">
                                                        <span><strong class="text-sky-400">Entry:</strong> {{ $tr->entry_time }} @ {{ number_format($tr->entry_spot, 1) }}</span>
                                                        <span><strong class="text-amber-400">Exit:</strong> {{ $tr->exit_time }} @ {{ number_format($tr->exit_spot, 1) }}</span>
                                                    </div>
                                                    <div class="text-[10px] text-gray-400 pt-0.5">
                                                        <span class="text-amber-300/90 font-bold">Exit Reason:</span> {{ $tr->exit_reason }}
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-gray-500 text-[11px] italic py-1">
                                            {{ $day->summary_reason ?: 'No high-probability trigger met. Standby.' }}
                                        </div>
                                    @endif
                                </td>

                                <!-- Net Points -->
                                <td class="py-3 px-2.5 text-right font-black {{ $day->net_points >= 0 ? 'text-emerald-400' : 'text-rose-400' }} text-sm whitespace-nowrap">
                                    {{ $day->net_points >= 0 ? '+' : '' }}{{ number_format($day->net_points, 1) }}
                                </td>

                                <!-- Net P&L (₹) -->
                                <td class="py-3 px-2.5 text-right font-black {{ $day->net_pnl_rupees >= 0 ? 'text-emerald-400' : 'text-rose-400' }} text-sm whitespace-nowrap">
                                    {{ $day->net_pnl_rupees >= 0 ? '+' : '' }}₹{{ number_format($day->net_pnl_rupees) }}
                                </td>

                                <!-- Day Status -->
                                <td class="py-2.5 px-2.5 text-center">
                                    @if($day->day_status === 'WIN')
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-950 text-emerald-300 border border-emerald-700 shadow-xs">🟢 WIN</span>
                                    @elseif($day->day_status === 'LOSS')
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-950 text-rose-300 border border-rose-700 shadow-xs">🔴 LOSS</span>
                                    @elseif($day->day_status === 'SCRATCH')
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-950 text-amber-300 border border-amber-700 shadow-xs">🟡 SCRATCH</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-900 text-gray-500 border border-gray-800">⚪ STANDBY</span>
                                    @endif
                                </td>

                                <!-- Actions -->
                                <td class="py-2.5 px-2.5 text-center whitespace-nowrap">
                                    <button type="button" onclick="loadDayForensics('{{ $day->date->format('Y-m-d') }}')" class="px-2 py-1 rounded bg-[#162035] hover:bg-[#1f2d4a] text-sky-300 border border-sky-700/60 text-[10.5px] font-bold transition shadow-xs cursor-pointer">
                                        🔍 5M Forensics
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-8 text-gray-500 font-mono text-sm">
                                    No trading days found matching the selected filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- 6. 5-Minute Candle Decision Timeline Slide-Over Modal -->
<div id="forensic-modal" class="fixed inset-0 bg-black/80 backdrop-blur-xs z-50 hidden flex items-center justify-center p-3 sm:p-5">
    <div class="bg-[#0b101c] border border-gray-700 rounded-xl w-full max-w-4xl max-h-[90vh] flex flex-col shadow-2xl overflow-hidden font-mono text-xs">
        <!-- Header -->
        <div class="p-3 bg-[#111728] border-b border-gray-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-base font-black text-sky-400">🔍 5-MINUTE CANDLE DECISION TIMELINE</span>
                <span id="modal-date-badge" class="px-2 py-0.5 rounded text-[11px] font-bold bg-sky-950 text-sky-300 border border-sky-700">2026-11-20</span>
            </div>
            <button type="button" onclick="closeForensicModal()" class="text-gray-400 hover:text-white text-base px-2 py-0.5 rounded cursor-pointer">&times;</button>
        </div>

        <!-- Session Highlights Bar -->
        <div id="modal-session-bar" class="p-2.5 bg-[#0e1424] border-b border-gray-800 flex items-center justify-between flex-wrap gap-2 text-xs">
            <div>
                <span class="text-gray-400">Total Trades:</span> <span id="modal-trades-count" class="font-bold text-white">1</span>
                <span class="mx-1.5 text-gray-600">&bull;</span>
                <span class="text-gray-400">Net P&amp;L:</span> <span id="modal-net-pnl" class="font-bold text-emerald-400">+₹13,065</span>
            </div>
            <div class="text-[10px] text-gray-400">
                Evaluation of every 5-minute candle against entry &amp; exit rules.
            </div>
        </div>

        <!-- Telemetry Table Body -->
        <div class="p-2 overflow-y-auto flex-1 divide-y divide-gray-800/60" id="modal-telemetry-body">
            <!-- Populated via JS -->
        </div>

        <!-- Footer -->
        <div class="p-2.5 bg-[#0e1424] border-t border-gray-800 flex justify-end">
            <button type="button" onclick="closeForensicModal()" class="px-4 py-1.5 rounded bg-gray-800 hover:bg-gray-700 text-white font-bold cursor-pointer">
                Close
            </button>
        </div>
    </div>
</div>

<!-- 7. Backend Recalculation Modal -->
<div id="recalculate-modal" class="fixed inset-0 bg-black/80 backdrop-blur-xs z-50 hidden flex items-center justify-center p-3 sm:p-5">
    <div class="bg-[#0b101c] border border-gray-700 rounded-xl w-full max-w-md p-4 shadow-2xl font-mono text-xs">
        <div class="flex items-center justify-between pb-2 border-b border-gray-800 mb-3">
            <span class="text-sm font-black text-emerald-400">⚡ RUN BACKEND BACKTEST</span>
            <button type="button" onclick="closeRecalculateModal()" class="text-gray-400 hover:text-white text-lg">&times;</button>
        </div>
        <div class="space-y-3">
            <div>
                <label class="block text-gray-400 mb-1">Days to Process:</label>
                <select id="recalc-days" class="w-full bg-[#141b2e] border border-gray-700 rounded px-2.5 py-1.5 text-white">
                    <option value="5">Last 5 Trading Days (Quick ~5s)</option>
                    <option value="30" selected>Last 30 Trading Days (~15s)</option>
                    <option value="60">Last 60 Trading Days (~30s)</option>
                    <option value="120">Last 120 Trading Days (~60s)</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-gray-400 mb-1">Start Time:</label>
                    <input type="text" id="recalc-start-time" value="09:35" class="w-full bg-[#141b2e] border border-gray-700 rounded px-2.5 py-1.5 text-white text-center">
                </div>
                <div>
                    <label class="block text-gray-400 mb-1">End Time:</label>
                    <input type="text" id="recalc-end-time" value="14:45" class="w-full bg-[#141b2e] border border-gray-700 rounded px-2.5 py-1.5 text-white text-center">
                </div>
            </div>
            <div class="text-[10px] text-gray-400 italic">
                Simulates exact 09:35–14:45 V2 rules and updates unique rows in database.
            </div>
            <div id="recalc-status-msg" class="hidden text-center py-1 font-bold"></div>
            <div class="pt-2 flex justify-end gap-2">
                <button type="button" onclick="closeRecalculateModal()" class="px-3 py-1.5 rounded bg-gray-800 hover:bg-gray-700 text-gray-300">Cancel</button>
                <button type="button" id="recalc-submit-btn" onclick="executeBackendRecalculation()" class="px-4 py-1.5 rounded bg-emerald-600 hover:bg-emerald-500 text-white font-bold">
                    Start Simulation
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function openRecalculateModal() {
    document.getElementById('recalculate-modal').classList.remove('hidden');
}
function closeRecalculateModal() {
    document.getElementById('recalculate-modal').classList.add('hidden');
}

function executeBackendRecalculation() {
    const btn = document.getElementById('recalc-submit-btn');
    const statusMsg = document.getElementById('recalc-status-msg');
    const days = document.getElementById('recalc-days').value;
    const startTime = document.getElementById('recalc-start-time').value;
    const endTime = document.getElementById('recalc-end-time').value;

    btn.disabled = true;
    btn.textContent = 'Processing Backend Engine...';
    statusMsg.classList.remove('hidden');
    statusMsg.className = 'text-center py-1 font-bold text-amber-300';
    statusMsg.textContent = '⏳ Simulating trading days in background...';

    fetch('{{ route("api.automated.trading.run") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ days: parseInt(days, 10), startTime, endTime })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            statusMsg.className = 'text-center py-1 font-bold text-emerald-400';
            statusMsg.textContent = '✓ ' + data.message + ' Reloading dashboard...';
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            statusMsg.className = 'text-center py-1 font-bold text-rose-400';
            statusMsg.textContent = 'Failed: ' + (data.message || 'Error executing engine');
            btn.disabled = false;
            btn.textContent = 'Start Simulation';
        }
    })
    .catch(err => {
        statusMsg.className = 'text-center py-1 font-bold text-rose-400';
        statusMsg.textContent = 'Error: ' + err.message;
        btn.disabled = false;
        btn.textContent = 'Start Simulation';
    });
}

function loadDayForensics(date) {
    const modal = document.getElementById('forensic-modal');
    const dateBadge = document.getElementById('modal-date-badge');
    const body = document.getElementById('modal-telemetry-body');
    const tradesCount = document.getElementById('modal-trades-count');
    const netPnl = document.getElementById('modal-net-pnl');

    dateBadge.textContent = date;
    body.innerHTML = '<div class="text-center py-8 text-gray-400 font-mono">⏳ Loading 5M candle forensics for ' + date + '...</div>';
    modal.classList.remove('hidden');

    fetch('/api/automated-trading-backtest/forensics/' + date)
        .then(r => r.json())
        .then(res => {
            if (!res.success || !res.day) {
                body.innerHTML = '<div class="text-center py-8 text-rose-400">Failed to load forensics for this day.</div>';
                return;
            }

            tradesCount.textContent = res.day.total_trades;
            netPnl.textContent = (res.day.net_pnl_rupees >= 0 ? '+' : '') + '₹' + Number(res.day.net_pnl_rupees).toLocaleString('en-IN') + ' (' + (res.day.net_points >= 0 ? '+' : '') + res.day.net_points + ' pts)';
            netPnl.className = 'font-bold ' + (res.day.net_pnl_rupees >= 0 ? 'text-emerald-400' : 'text-rose-400');

            const telemetry = res.telemetry || [];
            if (telemetry.length === 0) {
                body.innerHTML = '<div class="text-center py-8 text-gray-400">No candle telemetry recorded for this session.</div>';
                return;
            }

            body.innerHTML = telemetry.map(t => {
                let badgeClass = 'bg-slate-900 text-gray-400 border border-slate-700';
                if (t.badge.includes('WIN') || t.badge.includes('PROFIT') || t.badge.includes('TARGET')) {
                    badgeClass = 'bg-emerald-950 text-emerald-300 border border-emerald-700';
                } else if (t.badge.includes('STOPPED') || t.badge.includes('CUTOFF')) {
                    badgeClass = 'bg-rose-950 text-rose-300 border border-rose-700';
                } else if (t.badge.includes('STAGNATION') || t.badge.includes('TRAIL') || t.badge.includes('NEAR-MISS') || t.badge.includes('VETO')) {
                    badgeClass = 'bg-amber-950 text-amber-300 border border-amber-700';
                } else if (t.badge.includes('PRE-09:35') || t.badge.includes('NOISE')) {
                    badgeClass = 'bg-indigo-950 text-indigo-300 border border-indigo-700';
                } else if (t.badge.includes('ENTRY') || t.badge.includes('SQUEEZE') || t.badge.includes('GRADE')) {
                    badgeClass = 'bg-sky-950 text-sky-300 border border-sky-700 font-bold';
                }

                return `
                    <div class="py-2 px-2 flex items-start justify-between gap-3 text-[11px] hover:bg-[#121828] transition">
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="font-bold text-sky-300">${t.time}</span>
                            <span class="text-gray-400">@ ${Number(t.spot).toFixed(1)}</span>
                        </div>
                        <div class="flex-1 text-gray-300">
                            ${t.diagnostic || ''}
                        </div>
                        <div class="shrink-0">
                            <span class="px-2 py-0.5 rounded text-[9.5px] font-bold ${badgeClass}">
                                ${t.badge || t.state}
                            </span>
                        </div>
                    </div>
                `;
            }).join('');
        })
        .catch(err => {
            body.innerHTML = '<div class="text-center py-8 text-rose-400">Error loading forensics: ' + err.message + '</div>';
        });
}

function closeForensicModal() {
    document.getElementById('forensic-modal').classList.add('hidden');
}
</script>
@endsection
