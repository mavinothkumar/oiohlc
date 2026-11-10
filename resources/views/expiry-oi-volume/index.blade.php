@extends('layouts.app')

@section('title', 'NIFTY Options Expiry Day-over-Day OI & Volume Analysis | Institutional Radar')

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
<style>
    [x-cloak] { display: none !important; }
    /* Heatmap bar gauge */
    .oi-bar-fill-ce {
        background: linear-gradient(90deg, rgba(239, 68, 68, 0.15) 0%, rgba(239, 68, 68, 0.45) 100%);
    }
    .oi-bar-fill-pe {
        background: linear-gradient(90deg, rgba(16, 185, 129, 0.45) 0%, rgba(16, 185, 129, 0.15) 100%);
    }
    .atm-row {
        background-color: #f8fafc !important;
        border-top: 2px solid #6366f1 !important;
        border-bottom: 2px solid #6366f1 !important;
    }
    .atm-pulse {
        animation: pulse-border 2s infinite;
    }
    @keyframes pulse-border {
        0%, 100% { border-color: rgba(99, 102, 241, 0.8); }
        50% { border-color: rgba(99, 102, 241, 0.3); }
    }
    /* Custom scrollbar */
    .custom-scrollbar::-webkit-scrollbar {
        height: 6px;
        width: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f1f5f9;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
    }
</style>
@endpush

@section('content')
<div class="w-full px-2 sm:px-4 pb-12 font-sans antialiased text-slate-800"
     x-data="expiryAnalysisApp()">

    <!-- ════════════════════════════════════════════════════════════════════════
         TOP APP BAR & SELECTORS
    ════════════════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-4 mb-4">
        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
            
            <!-- Left: Title & Live Context -->
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-indigo-600 to-violet-700 flex items-center justify-center text-white shadow-md shadow-indigo-100">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M3 3v18h18"/>
                        <path d="M18 17V9"/>
                        <path d="M13 17V5"/>
                        <path d="M8 17v-3"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-lg font-bold text-slate-900 tracking-tight">Option Expiry Day-over-Day Dynamics</h1>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-200">OI &amp; Volume Radar</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">Backtest Ready</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Comparing strike volume, open interest, price drift &amp; moneyness buildup from previous day vs current day for the chosen expiry.
                    </p>
                </div>
            </div>

            <!-- Right: Expiry & Underlying Selector Form -->
            <form method="GET" action="{{ route('expiry.oi.volume.analysis') }}" class="flex flex-wrap items-center gap-2.5 w-full lg:w-auto">
                <!-- Symbol -->
                <div class="flex items-center gap-1.5 bg-slate-50 p-1 rounded-lg border border-slate-200 text-xs">
                    <span class="text-slate-400 font-medium px-1.5">Symbol:</span>
                    <select name="symbol" onchange="this.form.submit()" class="bg-white font-semibold text-slate-800 rounded px-2 py-1 border border-slate-300 focus:outline-indigo-500 text-xs">
                        @foreach($symbols as $sym)
                            <option value="{{ $sym }}" {{ $symbol === $sym ? 'selected' : '' }}>{{ $sym }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Expiry Date -->
                <div class="flex items-center gap-1.5 bg-slate-50 p-1 rounded-lg border border-slate-200 text-xs">
                    <span class="text-slate-400 font-medium px-1.5">Expiry:</span>
                    <select name="expiry" onchange="this.form.submit()" class="bg-white font-bold text-indigo-900 rounded px-2.5 py-1 border border-slate-300 focus:outline-indigo-500 text-xs">
                        @foreach($expiries as $exp)
                            <option value="{{ $exp['expiry_date'] }}" {{ $selected_expiry === $exp['expiry_date'] ? 'selected' : '' }}>
                                {{ $exp['formatted'] }} ({{ $exp['day_count'] }} days)
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Hidden filters to preserve -->
                <input type="hidden" name="strike_range" value="{{ $filters['strike_range'] }}">
                <input type="hidden" name="moneyness" value="{{ $filters['moneyness'] }}">
                <input type="hidden" name="sort_by" value="{{ $filters['sort_by'] }}">
                @if(!empty($filters['min_strike']))
                    <input type="hidden" name="min_strike" value="{{ $filters['min_strike'] }}">
                @endif
                @if(!empty($filters['max_strike']))
                    <input type="hidden" name="max_strike" value="{{ $filters['max_strike'] }}">
                @endif
            </form>
        </div>

        <!-- ════════════════════════════════════════════════════════════════════════
             TRADING DAY STEPPER / TIMELINE
        ════════════════════════════════════════════════════════════════════════ -->
        @if(!empty($trading_dates) && count($trading_dates) > 0)
        <div class="mt-4 pt-3 border-t border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Expiry Lifecycle Days:
                </span>
                <span class="text-xs font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-full">
                    Total {{ count($trading_dates) }} Sessions Recorded
                </span>
            </div>

            <!-- Date pills -->
            <div class="flex items-center gap-1.5 overflow-x-auto custom-scrollbar pb-1">
                @foreach($trading_dates as $idx => $dateStr)
                    @php
                        $isActive = ($dateStr === $selected_date);
                        $dNum = $idx + 1;
                        $formattedDate = \Carbon\Carbon::parse($dateStr)->format('d M');
                    @endphp
                    <a href="{{ route('expiry.oi.volume.analysis', array_merge(request()->query(), ['date' => $dateStr])) }}"
                       class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all whitespace-nowrap {{ $isActive ? 'bg-indigo-600 text-white shadow-sm ring-2 ring-indigo-300' : 'bg-slate-100 hover:bg-slate-200 text-slate-700' }}">
                        <span>Day {{ $dNum }}</span>
                        <span class="text-[10px] {{ $isActive ? 'text-indigo-100' : 'text-slate-400' }}">({{ $formattedDate }})</span>
                        @if($idx === count($trading_dates) - 1)
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 ml-0.5" title="Latest day / Expiry"></span>
                        @endif
                    </a>
                @endforeach
            </div>

            <!-- Day comparison indicator -->
            <div class="text-right whitespace-nowrap">
                <span class="text-[11px] text-slate-400">Comparing:</span>
                <span class="text-xs font-bold text-slate-900 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200">
                    {{ \Carbon\Carbon::parse($selected_date)->format('d M Y') }}
                </span>
                <span class="text-xs font-medium text-slate-400 mx-1">vs</span>
                @if($previous_date)
                    <span class="text-xs font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                        {{ \Carbon\Carbon::parse($previous_date)->format('d M Y') }}
                    </span>
                @else
                    <span class="text-xs font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                        Inaugural Day 1
                    </span>
                @endif
            </div>
        </div>
        @endif
    </div>

    <!-- ════════════════════════════════════════════════════════════════════════
         SPOT KPI SUMMARY BAR & MARKET METRICS
    ════════════════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
        
        <!-- 1. Spot Price & Day Delta -->
        <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
            <div class="flex items-center justify-between text-[11px] text-slate-400 font-medium">
                <span>{{ $symbol }} SPOT CLOSE</span>
                <span class="text-[9px] font-bold text-slate-500 bg-slate-100 px-1 rounded">EOD</span>
            </div>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-xl font-black text-slate-900">
                    {{ $spot['current'] ? number_format($spot['current'], 2) : 'N/A' }}
                </span>
                @if($spot['diff'] != 0)
                    <span class="text-xs font-bold {{ $spot['diff'] > 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ $spot['diff'] > 0 ? '+' : '' }}{{ number_format($spot['diff'], 2) }} ({{ $spot['diff_pct'] > 0 ? '+' : '' }}{{ $spot['diff_pct'] }}%)
                    </span>
                @endif
            </div>
            <div class="mt-1 text-[11px] text-slate-500 flex items-center justify-between">
                <span>ATM Strike:</span>
                <span class="font-bold text-indigo-600 font-mono">{{ $spot['atm_strike'] }}</span>
            </div>
        </div>

        <!-- 2. Total Open Interest Shift -->
        <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
            <div class="flex items-center justify-between text-[11px] text-slate-400 font-medium">
                <span>TOTAL OPEN INTEREST</span>
                <span class="text-[9px] font-bold text-indigo-600 bg-indigo-50 px-1 rounded">CE vs PE</span>
            </div>
            <div class="mt-1 flex items-baseline justify-between">
                <div>
                    <span class="text-xs font-bold text-rose-600">CE: {{ number_format($macro['ce_oi_curr'] / 100000, 2) }}L</span>
                    <span class="text-[10px] {{ $macro['ce_oi_diff'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                        ({{ $macro['ce_oi_diff'] >= 0 ? '+' : '' }}{{ $macro['ce_oi_pct'] }}%)
                    </span>
                </div>
                <div>
                    <span class="text-xs font-bold text-emerald-600">PE: {{ number_format($macro['pe_oi_curr'] / 100000, 2) }}L</span>
                    <span class="text-[10px] {{ $macro['pe_oi_diff'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                        ({{ $macro['pe_oi_diff'] >= 0 ? '+' : '' }}{{ $macro['pe_oi_pct'] }}%)
                    </span>
                </div>
            </div>
            <div class="mt-1 text-[11px] text-slate-500 flex items-center justify-between">
                <span>Net OI Shift:</span>
                <span class="font-bold {{ ($macro['pe_oi_diff'] - $macro['ce_oi_diff']) >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ ($macro['pe_oi_diff'] - $macro['ce_oi_diff']) >= 0 ? 'Bullish PE Lean' : 'Bearish CE Lean' }}
                </span>
            </div>
        </div>

        <!-- 3. Total Volume Shift -->
        <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
            <div class="flex items-center justify-between text-[11px] text-slate-400 font-medium">
                <span>TOTAL VOLUME</span>
                <span class="text-[9px] font-bold text-slate-500 bg-slate-100 px-1 rounded">Activity</span>
            </div>
            <div class="mt-1 flex items-baseline justify-between">
                <div>
                    <span class="text-xs font-bold text-slate-700">CE: {{ number_format($macro['ce_vol_curr'] / 100000, 1) }}L</span>
                    <span class="text-[10px] {{ $macro['ce_vol_diff'] >= 0 ? 'text-indigo-600' : 'text-slate-400' }}">
                        ({{ $macro['ce_vol_diff'] >= 0 ? '+' : '' }}{{ $macro['ce_vol_pct'] }}%)
                    </span>
                </div>
                <div>
                    <span class="text-xs font-bold text-slate-700">PE: {{ number_format($macro['pe_vol_curr'] / 100000, 1) }}L</span>
                    <span class="text-[10px] {{ $macro['pe_vol_diff'] >= 0 ? 'text-indigo-600' : 'text-slate-400' }}">
                        ({{ $macro['pe_vol_diff'] >= 0 ? '+' : '' }}{{ $macro['pe_vol_pct'] }}%)
                    </span>
                </div>
            </div>
            <div class="mt-1 text-[11px] text-slate-500 flex items-center justify-between">
                <span>Volume PCR:</span>
                <span class="font-bold text-slate-800 font-mono">{{ $macro['pcr_vol'] }}</span>
            </div>
        </div>

        <!-- 4. Put-Call Ratio (PCR) -->
        <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
            <div class="flex items-center justify-between text-[11px] text-slate-400 font-medium">
                <span>PCR (OPEN INTEREST)</span>
                <span class="text-[9px] font-bold text-slate-500 bg-slate-100 px-1 rounded">Sentiment</span>
            </div>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-xl font-black {{ $macro['pcr_oi'] >= 1.0 ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ number_format($macro['pcr_oi'], 3) }}
                </span>
                @if($macro['pcr_oi_diff'] != 0)
                    <span class="text-xs font-semibold {{ $macro['pcr_oi_diff'] > 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ $macro['pcr_oi_diff'] > 0 ? '↑ +' : '↓ ' }}{{ number_format($macro['pcr_oi_diff'], 3) }}
                    </span>
                @endif
            </div>
            <div class="mt-1 text-[11px] text-slate-500 flex items-center justify-between">
                <span>Signal:</span>
                <span class="font-bold {{ $macro['pcr_oi'] > 1.2 ? 'text-emerald-600' : ($macro['pcr_oi'] < 0.8 ? 'text-rose-600' : 'text-amber-600') }}">
                    {{ $macro['pcr_oi'] > 1.2 ? 'Bullish / Overbought' : ($macro['pcr_oi'] < 0.8 ? 'Bearish / Oversold' : 'Neutral Corridor') }}
                </span>
            </div>
        </div>

        <!-- 5. Max Pain Strike -->
        <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
            <div class="flex items-center justify-between text-[11px] text-slate-400 font-medium">
                <span>MAX PAIN STRIKE</span>
                <span class="text-[9px] font-bold text-purple-700 bg-purple-50 px-1 rounded">Options Theory</span>
            </div>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-xl font-black text-purple-800 font-mono">
                    {{ $macro['max_pain'] ?? 'N/A' }}
                </span>
                @if($spot['current'] && $macro['max_pain'])
                    @php $painDiff = $macro['max_pain'] - $spot['current']; @endphp
                    <span class="text-xs font-bold {{ $painDiff > 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                        ({{ $painDiff > 0 ? '+' : '' }}{{ round($painDiff) }} pts)
                    </span>
                @endif
            </div>
            <div class="mt-1 text-[11px] text-slate-500 flex items-center justify-between">
                <span>Magnet Direction:</span>
                <span class="font-bold text-slate-700">
                    {{ ($macro['max_pain'] ?? 0) > ($spot['current'] ?? 0) ? 'Pull Upwards ↑' : 'Pull Downwards ↓' }}
                </span>
            </div>
        </div>

        <!-- 6. Major Institutional Walls -->
        <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
            <div class="flex items-center justify-between text-[11px] text-slate-400 font-medium">
                <span>KEY SUPPORT &amp; RESISTANCE</span>
                <span class="text-[9px] font-bold text-slate-500 bg-slate-100 px-1 rounded">OI Peak</span>
            </div>
            <div class="mt-1 space-y-0.5">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-rose-600 font-semibold">Res (CE Wall):</span>
                    <span class="font-bold text-slate-900 font-mono">{{ $macro['highest_ce_oi_strike'] ?? '-' }}</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-emerald-600 font-semibold">Sup (PE Wall):</span>
                    <span class="font-bold text-slate-900 font-mono">{{ $macro['highest_pe_oi_strike'] ?? '-' }}</span>
                </div>
            </div>
            <div class="mt-1 text-[11px] text-slate-400 flex items-center justify-between">
                <span>Range:</span>
                <span class="font-bold text-indigo-600 font-mono">
                    {{ ($macro['highest_ce_oi_strike'] ?? 0) - ($macro['highest_pe_oi_strike'] ?? 0) }} pts
                </span>
            </div>
        </div>

    </div>

    <!-- ════════════════════════════════════════════════════════════════════════
         INSTITUTIONAL RADAR & NEXT-DAY BACKTEST VERIFICATION
    ════════════════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-4">
        
        <!-- Left: Institutional Hypothesis Engine (2 Cols) -->
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-xs p-4 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-2 border-b border-slate-100 mb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full {{ $hypothesis['color'] === 'emerald' ? 'bg-emerald-500' : ($hypothesis['color'] === 'rose' ? 'bg-rose-500' : 'bg-indigo-500') }} animate-ping"></span>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">Institutional Positioning &amp; Moneyness Hypothesis</h2>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-xs font-black uppercase tracking-wider 
                        {{ $hypothesis['color'] === 'emerald' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 
                           ($hypothesis['color'] === 'rose' ? 'bg-rose-100 text-rose-800 border border-rose-300' : 'bg-indigo-100 text-indigo-800 border border-indigo-300') }}">
                        {{ $hypothesis['bias'] }}
                    </span>
                </div>

                <p class="text-xs font-medium text-slate-700 leading-relaxed mb-3">
                    {{ $hypothesis['summary'] }}
                </p>
                <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-600 leading-normal">
                    <strong class="text-slate-800 font-semibold">Quantitative Thesis:</strong> {{ $hypothesis['thesis'] }}
                </div>
            </div>

            <!-- Moneyness Shifts Breakdown Ticker -->
            <div class="grid grid-cols-3 gap-2 mt-4 pt-3 border-t border-slate-100">
                <!-- ATM -->
                <div class="p-2 rounded-lg bg-slate-50 border border-slate-100 text-center">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">ATM Shift</div>
                    <div class="text-xs font-bold mt-1">
                        <span class="text-rose-600">CE: {{ $moneyness_shifts['atm']['ce_oi_diff'] >= 0 ? '+' : '' }}{{ number_format($moneyness_shifts['atm']['ce_oi_diff'] / 1000) }}k</span>
                        <span class="text-slate-300 mx-1">|</span>
                        <span class="text-emerald-600">PE: {{ $moneyness_shifts['atm']['pe_oi_diff'] >= 0 ? '+' : '' }}{{ number_format($moneyness_shifts['atm']['pe_oi_diff'] / 1000) }}k</span>
                    </div>
                </div>

                <!-- OTM (Calls Ceiling vs Puts Floor) -->
                <div class="p-2 rounded-lg bg-indigo-50/50 border border-indigo-100 text-center">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-indigo-600">OTM Net Additions</div>
                    <div class="text-xs font-bold mt-1">
                        <span class="text-rose-600" title="OTM CE Call Writing">CE: {{ $moneyness_shifts['otm']['ce_oi_diff'] >= 0 ? '+' : '' }}{{ number_format($moneyness_shifts['otm']['ce_oi_diff'] / 1000) }}k</span>
                        <span class="text-slate-300 mx-1">|</span>
                        <span class="text-emerald-600" title="OTM PE Put Writing">PE: {{ $moneyness_shifts['otm']['pe_oi_diff'] >= 0 ? '+' : '' }}{{ number_format($moneyness_shifts['otm']['pe_oi_diff'] / 1000) }}k</span>
                    </div>
                </div>

                <!-- ITM -->
                <div class="p-2 rounded-lg bg-slate-50 border border-slate-100 text-center">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">ITM Shift</div>
                    <div class="text-xs font-bold mt-1">
                        <span class="text-rose-600">CE: {{ $moneyness_shifts['itm']['ce_oi_diff'] >= 0 ? '+' : '' }}{{ number_format($moneyness_shifts['itm']['ce_oi_diff'] / 1000) }}k</span>
                        <span class="text-slate-300 mx-1">|</span>
                        <span class="text-emerald-600">PE: {{ $moneyness_shifts['itm']['pe_oi_diff'] >= 0 ? '+' : '' }}{{ number_format($moneyness_shifts['itm']['pe_oi_diff'] / 1000) }}k</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Next Day Market Outcome (Backtesting Verification Card) -->
        <div class="bg-gradient-to-br from-slate-900 to-indigo-950 text-white rounded-xl shadow-md p-4 flex flex-col justify-between border border-slate-800">
            <div>
                <div class="flex items-center justify-between pb-2 border-b border-slate-800/80 mb-2">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300">Next-Day Backtest Outcome</h3>
                    </div>
                    @if(!empty($hypothesis['verdict']))
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $hypothesis['verdict']['color'] === 'emerald' ? 'bg-emerald-900/60 text-emerald-300 border border-emerald-600' : 'bg-rose-900/60 text-rose-300 border border-rose-600' }}">
                            {{ $hypothesis['verdict']['badge'] }}
                        </span>
                    @endif
                </div>

                @if($next_day_outcome)
                    <div class="mt-2 space-y-2">
                        <div class="flex items-baseline justify-between">
                            <span class="text-xs text-slate-400">Date: {{ $next_day_outcome['formatted_date'] }}</span>
                            <span class="text-xs font-mono font-bold {{ $next_day_outcome['change_pts'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                {{ $next_day_outcome['change_pts'] >= 0 ? '+' : '' }}{{ $next_day_outcome['change_pts'] }} pts ({{ $next_day_outcome['change_pct'] }}%)
                            </span>
                        </div>

                        <div class="grid grid-cols-4 gap-1.5 p-2 bg-slate-800/60 rounded-lg text-center font-mono text-xs">
                            <div>
                                <span class="text-[9px] text-slate-400 block font-sans">Open</span>
                                <span class="font-bold">{{ number_format($next_day_outcome['open'], 1) }}</span>
                            </div>
                            <div>
                                <span class="text-[9px] text-slate-400 block font-sans">High</span>
                                <span class="font-bold text-emerald-300">{{ number_format($next_day_outcome['high'], 1) }}</span>
                            </div>
                            <div>
                                <span class="text-[9px] text-slate-400 block font-sans">Low</span>
                                <span class="font-bold text-rose-300">{{ number_format($next_day_outcome['low'], 1) }}</span>
                            </div>
                            <div>
                                <span class="text-[9px] text-slate-400 block font-sans">Close</span>
                                <span class="font-bold text-amber-300">{{ number_format($next_day_outcome['close'], 1) }}</span>
                            </div>
                        </div>

                        <div class="text-[11px] text-slate-300 flex items-center justify-between">
                            <span>Open Structure:</span>
                            <span class="font-semibold text-cyan-300">{{ $next_day_outcome['gap_type'] }} ({{ $next_day_outcome['gap_pts'] > 0 ? '+' : '' }}{{ $next_day_outcome['gap_pts'] }} pts)</span>
                        </div>
                    </div>
                @else
                    <div class="py-6 text-center text-xs text-slate-400">
                        <p>This is the final recorded session or future day is pending.</p>
                        <span class="text-[10px] text-slate-500">Select earlier dates in the timeline above to backtest outcomes.</span>
                    </div>
                @endif
            </div>

            @if(!empty($hypothesis['verdict']))
                <div class="mt-3 p-2 rounded bg-slate-800/80 border border-slate-700/60 text-[11px] leading-tight text-slate-200">
                    {{ $hypothesis['verdict']['message'] }}
                </div>
            @endif
        </div>

    </div>

    <!-- ════════════════════════════════════════════════════════════════════════
         DISCREPANCIES & SUDDEN SURGE ANOMALIES RADAR
    ════════════════════════════════════════════════════════════════════════ -->
    @if(!empty($anomalies) && count($anomalies) > 0)
    <div class="bg-amber-50/60 border border-amber-200 rounded-xl p-3 mb-4">
        <div class="flex items-center gap-2 mb-2">
            <span class="text-amber-600 font-bold text-xs uppercase tracking-wider flex items-center gap-1">
                <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                Sudden Vol &amp; OI Anomalies Detected:
            </span>
            <span class="text-[10px] text-amber-700 font-semibold bg-amber-100 px-2 py-0.5 rounded-full">
                {{ count($anomalies) }} Strike Discrepancies
            </span>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach($anomalies as $anomaly)
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-white border border-amber-200 text-slate-800 shadow-2xs hover:border-amber-400 transition cursor-pointer"
                     @click="openStrikeHistory({{ $anomaly['strike'] }})">
                    <span class="font-bold font-mono">{{ $anomaly['strike'] }} {{ $anomaly['option_type'] }}</span>
                    <span class="px-1.5 py-0.2 rounded text-[10px] font-bold 
                        {{ $anomaly['type'] === 'volume_spike' ? 'bg-sky-100 text-sky-800' : 
                           ($anomaly['type'] === 'oi_surge' ? 'bg-orange-100 text-orange-800' : 'bg-rose-100 text-rose-800') }}">
                        {{ $anomaly['badge'] }}
                    </span>
                    <span class="text-[10px] text-slate-400" title="{{ $anomaly['message'] }}">🔍</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- ════════════════════════════════════════════════════════════════════════
         FILTER TOOLBAR & VIEW CONTROLS
    ════════════════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-3 mb-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
        
        <!-- Left: Quick Strike Range Presets -->
        <div class="flex flex-wrap items-center gap-1.5">
            <span class="text-xs font-semibold text-slate-500 mr-1">Strike Range:</span>
            @foreach(['5' => '±5 (11)', '10' => '±10 (21)', '15' => '±15 (31)', '20' => '±20 (41)', 'all' => 'All Strikes'] as $val => $lbl)
                <a href="{{ route('expiry.oi.volume.analysis', array_merge(request()->query(), ['strike_range' => $val, 'min_strike' => null, 'max_strike' => null])) }}"
                   class="px-2.5 py-1 rounded-md text-xs font-medium transition {{ $filters['strike_range'] === (string)$val ? 'bg-indigo-600 text-white font-bold shadow-2xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700' }}">
                    {{ $lbl }}
                </a>
            @endforeach
        </div>

        <!-- Middle: Moneyness filter -->
        <div class="flex items-center gap-1.5 text-xs">
            <span class="text-slate-500 font-semibold">Filter:</span>
            <div class="inline-flex rounded-lg p-0.5 bg-slate-100 border border-slate-200">
                @foreach(['all' => 'All', 'atm' => 'ATM Only', 'otm' => 'OTM Only', 'itm' => 'ITM Only', 'surges' => 'Surges Only'] as $mKey => $mLbl)
                    <a href="{{ route('expiry.oi.volume.analysis', array_merge(request()->query(), ['moneyness' => $mKey])) }}"
                       class="px-2 py-0.5 rounded-md text-xs font-medium transition {{ $filters['moneyness'] === $mKey ? 'bg-white text-indigo-700 font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900' }}">
                        {{ $mLbl }}
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Right: View Switcher (Matrix vs Multi-Day Dynamics vs Visual Charts) & CSV Export -->
        <div class="flex items-center gap-2">
            <div class="inline-flex rounded-lg p-0.5 bg-slate-100 border border-slate-200">
                <button type="button" @click="activeTab = 'matrix'"
                        :class="activeTab === 'matrix' ? 'bg-white text-indigo-700 font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                        class="px-3 py-1 rounded-md text-xs font-medium flex items-center gap-1.5 transition">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h18v18H3zM3 9h18M3 15h18M9 3v18M15 3v18"/></svg>
                    <span>Dual Matrix</span>
                </button>
                <button type="button" @click="activeTab = 'multiday'; $nextTick(() => renderMultiDayChart())"
                        :class="activeTab === 'multiday' ? 'bg-indigo-600 text-white font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                        class="px-3 py-1 rounded-md text-xs font-medium flex items-center gap-1.5 transition">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 16l4-8 4 4 6-9"/></svg>
                    <span>±10 Multi-Day Dynamics</span>
                </button>
                <button type="button" @click="activeTab = 'charts'; $nextTick(() => renderCharts())"
                        :class="activeTab === 'charts' ? 'bg-white text-indigo-700 font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                        class="px-3 py-1 rounded-md text-xs font-medium flex items-center gap-1.5 transition">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
                    <span>Visual Charts</span>
                </button>
            </div>

            <!-- Export to CSV -->
            <button type="button" @click="exportToCsv()"
                    class="px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 flex items-center gap-1 transition"
                    title="Export table data to CSV file">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                CSV
            </button>
        </div>

    </div>

    <!-- ════════════════════════════════════════════════════════════════════════
         TAB 1: DUAL OPTION MATRIX (CALLS | STRIKE | PUTS)
    ════════════════════════════════════════════════════════════════════════ -->
    <div x-show="activeTab === 'matrix'" class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
        
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-xs text-left border-collapse select-text">
                
                <!-- TOP HEADER CATEGORIES -->
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-slate-600">
                        <th colspan="7" class="py-2.5 px-3 text-center border-r border-slate-200 font-black text-rose-700 bg-rose-50/50 uppercase tracking-wider text-xs">
                            ← CALL OPTIONS (CE)
                        </th>
                        <th colspan="3" class="py-2.5 px-3 text-center border-r border-slate-200 font-black text-slate-800 bg-indigo-50/50 uppercase tracking-wider text-xs">
                            STRIKE &amp; STRADDLE
                        </th>
                        <th colspan="7" class="py-2.5 px-3 text-center font-black text-emerald-700 bg-emerald-50/50 uppercase tracking-wider text-xs">
                            PUT OPTIONS (PE) →
                        </th>
                    </tr>

                    <!-- COLUMN SUBHEADERS -->
                    <tr class="border-b border-slate-200 bg-slate-100/70 text-[11px] font-bold text-slate-600">
                        <!-- CE Side -->
                        <th class="py-2 px-2 text-center">Alert / Buildup</th>
                        <th class="py-2 px-2 text-center">Type</th>
                        <th class="py-2 px-2 text-right">LTP (Close)</th>
                        <th class="py-2 px-2 text-right">Day Δ Pts (%)</th>
                        <th class="py-2 px-2 text-right">Volume (Δ %)</th>
                        <th class="py-2 px-2 text-right">Open Interest</th>
                        <th class="py-2 px-2 text-right border-r border-slate-200">OI Δ (%)</th>

                        <!-- Center Strike -->
                        <th class="py-2 px-2 text-center bg-slate-200/50">STRIKE</th>
                        <th class="py-2 px-2 text-center bg-slate-200/50">Dist / Spot</th>
                        <th class="py-2 px-2 text-center bg-slate-200/50 border-r border-slate-200">Straddle Δ</th>

                        <!-- PE Side -->
                        <th class="py-2 px-2 text-left">OI Δ (%)</th>
                        <th class="py-2 px-2 text-left">Open Interest</th>
                        <th class="py-2 px-2 text-left">Volume (Δ %)</th>
                        <th class="py-2 px-2 text-left">Day Δ Pts (%)</th>
                        <th class="py-2 px-2 text-left">LTP (Close)</th>
                        <th class="py-2 px-2 text-center">Type</th>
                        <th class="py-2 px-2 text-center">Alert / Buildup</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 font-mono">
                    @forelse($rows as $r)
                        @php
                            $isAtm = $r['is_atm'];
                            $ceDepthPct = min(100, round(($r['ce']['oi'] / max(1, $max_depths['ce_oi'])) * 100));
                            $peDepthPct = min(100, round(($r['pe']['oi'] / max(1, $max_depths['pe_oi'])) * 100));
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors {{ $isAtm ? 'atm-row atm-pulse' : '' }}">
                            
                            <!-- 1. CE Buildup & Alerts -->
                            <td class="py-2 px-2 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1 font-sans">
                                    @if(!empty($r['ce']['alerts']))
                                        @foreach($r['ce']['alerts'] as $al)
                                            <span class="px-1 py-0.2 rounded text-[9px] font-black uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-300" title="{{ $al['message'] }}">
                                                {{ $al['badge'] }}
                                            </span>
                                        @endforeach
                                    @endif
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold 
                                        {{ $r['ce']['buildup']['color'] === 'emerald' ? 'bg-emerald-100 text-emerald-800' : 
                                           ($r['ce']['buildup']['color'] === 'rose' ? 'bg-rose-100 text-rose-800' : 
                                           ($r['ce']['buildup']['color'] === 'sky' ? 'bg-sky-100 text-sky-800' : 
                                           ($r['ce']['buildup']['color'] === 'amber' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'))) }}"
                                          title="{{ $r['ce']['buildup']['desc'] }}">
                                        {{ $r['ce']['buildup']['code'] }}
                                    </span>
                                </div>
                            </td>

                            <!-- 2. CE Moneyness -->
                            <td class="py-2 px-2 text-center font-sans">
                                <span class="px-1.5 py-0.2 rounded text-[10px] font-bold 
                                    {{ $r['ce']['moneyness'] === 'ATM' ? 'bg-indigo-600 text-white font-black' : 
                                       ($r['ce']['moneyness'] === 'ITM' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-slate-50 text-slate-500') }}">
                                    {{ $r['ce']['moneyness'] }}
                                </span>
                            </td>

                            <!-- 3. CE LTP -->
                            <td class="py-2 px-2 text-right font-bold text-slate-800">
                                {{ number_format($r['ce']['close'], 2) }}
                            </td>

                            <!-- 4. CE Price Δ -->
                            <td class="py-2 px-2 text-right whitespace-nowrap {{ $r['ce']['price_diff'] >= 0 ? 'text-emerald-600 font-semibold' : 'text-rose-600 font-semibold' }}">
                                {{ $r['ce']['price_diff'] >= 0 ? '+' : '' }}{{ number_format($r['ce']['price_diff'], 2) }}
                                <span class="text-[10px] text-slate-400">({{ $r['ce']['price_pct'] >= 0 ? '+' : '' }}{{ $r['ce']['price_pct'] }}%)</span>
                            </td>

                            <!-- 5. CE Volume & Δ % -->
                            <td class="py-2 px-2 text-right whitespace-nowrap">
                                <div class="text-slate-800 font-medium">{{ number_format($r['ce']['volume']) }}</div>
                                <div class="text-[10px] {{ $r['ce']['volume_diff'] >= 0 ? 'text-indigo-600 font-bold' : 'text-slate-400' }}">
                                    {{ $r['ce']['volume_diff'] >= 0 ? '+' : '' }}{{ number_format($r['ce']['volume_diff']) }} ({{ $r['ce']['volume_pct'] }}%)
                                </div>
                            </td>

                            <!-- 6. CE Open Interest -->
                            <td class="py-2 px-2 text-right font-bold text-slate-900 relative">
                                <div class="relative z-10">{{ number_format($r['ce']['oi']) }}</div>
                                <!-- Background Depth Fill Bar -->
                                <div class="absolute inset-y-1 right-0 oi-bar-fill-ce rounded-l z-0 pointer-events-none" style="width: {{ $ceDepthPct }}%;"></div>
                            </td>

                            <!-- 7. CE OI Δ (%) -->
                            <td class="py-2 px-2 text-right border-r border-slate-200 whitespace-nowrap font-bold {{ $r['ce']['oi_diff'] >= 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                <span>{{ $r['ce']['oi_diff'] >= 0 ? '+' : '' }}{{ number_format($r['ce']['oi_diff']) }}</span>
                                <span class="text-[10px] px-1 py-0.2 rounded {{ $r['ce']['oi_diff'] >= 0 ? 'bg-rose-50' : 'bg-emerald-50' }}">
                                    {{ $r['ce']['oi_pct'] >= 0 ? '+' : '' }}{{ $r['ce']['oi_pct'] }}%
                                </span>
                            </td>

                            <!-- ════════ CENTER: STRIKE ════════ -->
                            <td class="py-2 px-2.5 text-center font-black text-sm bg-slate-50/80 cursor-pointer group hover:bg-indigo-50 transition"
                                @click="openStrikeHistory({{ $r['strike'] }})"
                                title="Click to view full Day-by-Day evolution of strike {{ $r['strike'] }}">
                                <div class="flex items-center justify-center gap-1">
                                    <span class="{{ $isAtm ? 'text-indigo-700 text-base underline decoration-indigo-500 underline-offset-2' : 'text-slate-900' }}">
                                        {{ $r['strike'] }}
                                    </span>
                                    <span class="text-[9px] opacity-0 group-hover:opacity-100 text-indigo-500 font-sans transition">🔍</span>
                                </div>
                            </td>

                            <!-- Distance from Spot -->
                            <td class="py-2 px-2 text-center text-[11px] bg-slate-50/80 text-slate-500 font-sans">
                                @if($isAtm)
                                    <span class="px-1.5 py-0.5 rounded font-black text-[10px] bg-indigo-600 text-white">ATM</span>
                                @else
                                    <span>{{ $r['distance_from_spot'] > 0 ? '+' : '' }}{{ round($r['distance_from_spot']) }} pts</span>
                                @endif
                            </td>

                            <!-- Straddle Premium & Bias -->
                            <td class="py-2 px-2 text-center border-r border-slate-200 bg-slate-50/80 whitespace-nowrap">
                                <div class="font-bold text-slate-800">{{ number_format($r['straddle']['close'], 2) }}</div>
                                <div class="text-[10px] {{ $r['straddle']['diff'] >= 0 ? 'text-emerald-600 font-semibold' : 'text-rose-600 font-semibold' }}">
                                    {{ $r['straddle']['diff'] >= 0 ? '+' : '' }}{{ $r['straddle']['diff'] }}
                                </div>
                            </td>

                            <!-- ════════ PE SIDE ════════ -->

                            <!-- 8. PE OI Δ (%) -->
                            <td class="py-2 px-2 text-left whitespace-nowrap font-bold {{ $r['pe']['oi_diff'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                <span>{{ $r['pe']['oi_diff'] >= 0 ? '+' : '' }}{{ number_format($r['pe']['oi_diff']) }}</span>
                                <span class="text-[10px] px-1 py-0.2 rounded {{ $r['pe']['oi_diff'] >= 0 ? 'bg-emerald-50' : 'bg-rose-50' }}">
                                    {{ $r['pe']['oi_pct'] >= 0 ? '+' : '' }}{{ $r['pe']['oi_pct'] }}%
                                </span>
                            </td>

                            <!-- 9. PE Open Interest -->
                            <td class="py-2 px-2 text-left font-bold text-slate-900 relative">
                                <div class="relative z-10">{{ number_format($r['pe']['oi']) }}</div>
                                <!-- Background Depth Fill Bar -->
                                <div class="absolute inset-y-1 left-0 oi-bar-fill-pe rounded-r z-0 pointer-events-none" style="width: {{ $peDepthPct }}%;"></div>
                            </td>

                            <!-- 10. PE Volume & Δ % -->
                            <td class="py-2 px-2 text-left whitespace-nowrap">
                                <div class="text-slate-800 font-medium">{{ number_format($r['pe']['volume']) }}</div>
                                <div class="text-[10px] {{ $r['pe']['volume_diff'] >= 0 ? 'text-indigo-600 font-bold' : 'text-slate-400' }}">
                                    {{ $r['pe']['volume_diff'] >= 0 ? '+' : '' }}{{ number_format($r['pe']['volume_diff']) }} ({{ $r['pe']['volume_pct'] }}%)
                                </div>
                            </td>

                            <!-- 11. PE Price Δ -->
                            <td class="py-2 px-2 text-left whitespace-nowrap {{ $r['pe']['price_diff'] >= 0 ? 'text-emerald-600 font-semibold' : 'text-rose-600 font-semibold' }}">
                                {{ $r['pe']['price_diff'] >= 0 ? '+' : '' }}{{ number_format($r['pe']['price_diff'], 2) }}
                                <span class="text-[10px] text-slate-400">({{ $r['pe']['price_pct'] >= 0 ? '+' : '' }}{{ $r['pe']['price_pct'] }}%)</span>
                            </td>

                            <!-- 12. PE LTP -->
                            <td class="py-2 px-2 text-left font-bold text-slate-800">
                                {{ number_format($r['pe']['close'], 2) }}
                            </td>

                            <!-- 13. PE Moneyness -->
                            <td class="py-2 px-2 text-center font-sans">
                                <span class="px-1.5 py-0.2 rounded text-[10px] font-bold 
                                    {{ $r['pe']['moneyness'] === 'ATM' ? 'bg-indigo-600 text-white font-black' : 
                                       ($r['pe']['moneyness'] === 'ITM' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-50 text-slate-500') }}">
                                    {{ $r['pe']['moneyness'] }}
                                </span>
                            </td>

                            <!-- 14. PE Buildup & Alerts -->
                            <td class="py-2 px-2 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1 font-sans">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold 
                                        {{ $r['pe']['buildup']['color'] === 'emerald' ? 'bg-emerald-100 text-emerald-800' : 
                                           ($r['pe']['buildup']['color'] === 'rose' ? 'bg-rose-100 text-rose-800' : 
                                           ($r['pe']['buildup']['color'] === 'sky' ? 'bg-sky-100 text-sky-800' : 
                                           ($r['pe']['buildup']['color'] === 'amber' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'))) }}"
                                          title="{{ $r['pe']['buildup']['desc'] }}">
                                        {{ $r['pe']['buildup']['code'] }}
                                    </span>
                                    @if(!empty($r['pe']['alerts']))
                                        @foreach($r['pe']['alerts'] as $al)
                                            <span class="px-1 py-0.2 rounded text-[9px] font-black uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-300" title="{{ $al['message'] }}">
                                                {{ $al['badge'] }}
                                            </span>
                                        @endforeach
                                    @endif
                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="17" class="py-12 text-center text-slate-400 font-sans">
                                No option strikes matched the selected filter criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Table Footer / Legend -->
        <div class="p-3 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-between text-[11px] text-slate-500 gap-2">
            <div class="flex items-center gap-3">
                <span class="font-bold text-slate-700">Buildup Legend:</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-emerald-500"></span> <strong>LB:</strong> Long Buildup (Price ↑ OI ↑)</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-rose-500"></span> <strong>SB:</strong> Short Buildup / Writing (Price ↓ OI ↑)</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-amber-500"></span> <strong>LU:</strong> Long Unwinding (Price ↓ OI ↓)</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-sky-500"></span> <strong>SC:</strong> Short Covering (Price ↑ OI ↓)</span>
            </div>
            <div>
                <span class="text-slate-400">Tip: Click any Strike number to open its multi-day lifecycle history.</span>
            </div>
        </div>

    </div>

    <!-- ════════════════════════════════════════════════════════════════════════
         TAB 2: MULTI-DAY DYNAMICS (±10 STRIKES ALL DAYS IN ONE VIEW)
    ════════════════════════════════════════════════════════════════════════ -->
    <div x-show="activeTab === 'multiday'" class="space-y-4" style="display: none;">

        <!-- 1. Top Corridor KPI Summary Strip -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-4">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">ATM Strike & Net Straddle Decay</div>
                <div class="flex items-baseline gap-2 mt-1">
                    <span class="text-xl font-black font-mono text-indigo-950" x-text="getAtmMorphSummary().strike"></span>
                    <span class="text-xs font-bold font-mono"
                          :class="getAtmMorphSummary().decayPts <= 0 ? 'text-emerald-600' : 'text-rose-600'"
                          x-text="(getAtmMorphSummary().decayPts <= 0 ? '' : '+') + getAtmMorphSummary().decayPts + ' pts (' + getAtmMorphSummary().decayPct + '%)'"></span>
                </div>
                <div class="text-[11px] text-slate-500 mt-1">
                    Latest: ₹<span class="font-bold text-slate-800 font-mono" x-text="getAtmMorphSummary().latestStraddle"></span> (Day 1: ₹<span class="font-mono" x-text="getAtmMorphSummary().firstStraddle"></span>)
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-4">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Corridor Strikes Monitored</div>
                <div class="flex items-baseline gap-2 mt-1">
                    <span class="text-xl font-black font-mono text-slate-900" x-text="multiDayStrikes.length + ' Strikes'"></span>
                    <span class="text-xs font-bold text-indigo-600 font-mono">±10 Range</span>
                </div>
                <div class="text-[11px] text-slate-500 mt-1">
                    <span class="font-mono font-semibold" x-text="multiDayStrikes[0]?.strike"></span> to <span class="font-mono font-semibold" x-text="multiDayStrikes[multiDayStrikes.length - 1]?.strike"></span>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-4">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Expiry Lifespan Sessions</div>
                <div class="flex items-baseline gap-2 mt-1">
                    <span class="text-xl font-black font-mono text-slate-900" x-text="tradingDays.length + ' Days Recorded'"></span>
                </div>
                <div class="text-[11px] text-slate-500 mt-1">
                    From <span class="font-semibold text-slate-700" x-text="tradingDays[0]?.formatted"></span> to <span class="font-semibold text-slate-700" x-text="tradingDays[tradingDays.length - 1]?.formatted"></span>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-4">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">ATM Net OI Addition</div>
                <div class="flex items-baseline gap-2 mt-1">
                    <span class="text-xl font-black font-mono text-slate-900" x-text="formatNumberCompact(getAtmMorphSummary().latestOi)"></span>
                    <span class="text-xs font-bold font-mono"
                          :class="getAtmMorphSummary().oiDiff >= 0 ? 'text-indigo-600' : 'text-slate-500'"
                          x-text="(getAtmMorphSummary().oiDiff >= 0 ? '+' : '') + formatNumberCompact(getAtmMorphSummary().oiDiff) + ' (' + getAtmMorphSummary().oiPct + '%)'"></span>
                </div>
                <div class="text-[11px] text-slate-500 mt-1">
                    Cumulative contracts across CE + PE
                </div>
            </div>
        </div>

        <!-- 2. Interactive Multi-Day Chart Card -->
        <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-4 sm:p-5">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-100">
                <div>
                    <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                        </svg>
                        <span>Multi-Day Progression Curve (±10 Strikes across Available Sessions)</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Each curve represents an available trading day. Compare curve displacement to instantly observe Straddle decay / volatility crush and Open Interest accumulation across strikes.
                    </p>
                </div>

                <!-- Metric Toggle Buttons for the Chart -->
                <div class="flex items-center gap-1.5 p-1 bg-slate-100 rounded-lg border border-slate-200 self-start md:self-auto">
                    <button type="button"
                            @click="setMultiDayMetric('straddle')"
                            :class="multiDayMetric === 'straddle' ? 'bg-white text-indigo-700 font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                            class="px-3 py-1 rounded-md text-xs font-medium transition cursor-pointer">
                        Straddle Price (CE+PE)
                    </button>
                    <button type="button"
                            @click="setMultiDayMetric('oi')"
                            :class="multiDayMetric === 'oi' ? 'bg-white text-indigo-700 font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                            class="px-3 py-1 rounded-md text-xs font-medium transition cursor-pointer">
                        Total Strike OI
                    </button>
                    <button type="button"
                            @click="setMultiDayMetric('vol')"
                            :class="multiDayMetric === 'vol' ? 'bg-white text-indigo-700 font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                            class="px-3 py-1 rounded-md text-xs font-medium transition cursor-pointer">
                        Total Volume
                    </button>
                </div>
            </div>

            <!-- Canvas Container -->
            <div class="relative h-80 w-full">
                <canvas id="multiDayStrikesChart"></canvas>
            </div>
        </div>

        <!-- 3. All-in-One Multi-Day Strike Matrix Table -->
        <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
            
            <!-- Table Sub-header Controls -->
            <div class="p-3.5 bg-slate-50/80 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Multi-Day Strike Matrix:</span>
                    <span class="text-xs text-slate-500 font-medium">Session-by-session comparative progression table</span>
                </div>

                <!-- Matrix Columns Filter -->
                <div class="flex items-center gap-1 text-xs">
                    <span class="text-slate-500 font-medium mr-1">Display:</span>
                    <div class="inline-flex rounded-lg p-0.5 bg-white border border-slate-200 shadow-2xs">
                        <button type="button" @click="multiDayTableFilter = 'all'"
                                :class="multiDayTableFilter === 'all' ? 'bg-indigo-600 text-white font-bold' : 'text-slate-600 hover:text-slate-900'"
                                class="px-2.5 py-1 rounded-md text-xs font-medium transition cursor-pointer">
                            All Metrics
                        </button>
                        <button type="button" @click="multiDayTableFilter = 'straddle'"
                                :class="multiDayTableFilter === 'straddle' ? 'bg-indigo-600 text-white font-bold' : 'text-slate-600 hover:text-slate-900'"
                                class="px-2.5 py-1 rounded-md text-xs font-medium transition cursor-pointer">
                            Straddle Only
                        </button>
                        <button type="button" @click="multiDayTableFilter = 'oi'"
                                :class="multiDayTableFilter === 'oi' ? 'bg-indigo-600 text-white font-bold' : 'text-slate-600 hover:text-slate-900'"
                                class="px-2.5 py-1 rounded-md text-xs font-medium transition cursor-pointer">
                            OI Only
                        </button>
                        <button type="button" @click="multiDayTableFilter = 'vol'"
                                :class="multiDayTableFilter === 'vol' ? 'bg-indigo-600 text-white font-bold' : 'text-slate-600 hover:text-slate-900'"
                                class="px-2.5 py-1 rounded-md text-xs font-medium transition cursor-pointer">
                            Volume Only
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-xs text-left border-collapse select-text">
                    <thead>
                        <tr class="bg-slate-100 text-[11px] font-bold text-slate-700 border-b border-slate-200">
                            <th class="py-3 px-3 text-center sticky left-0 bg-slate-100 z-10 border-r border-slate-200 min-w-[130px]">
                                Strike &amp; Moneyness
                            </th>
                            <template x-for="(day, dIdx) in tradingDays" :key="day.quote_date">
                                <th class="py-3 px-3 text-center border-r border-slate-200 min-w-[150px]"
                                    :class="dIdx === tradingDays.length - 1 ? 'bg-indigo-50/70 text-indigo-950 font-black' : ''">
                                    <div class="flex items-center justify-center gap-1">
                                        <span x-text="'Day ' + day.day_number"></span>
                                        <template x-if="dIdx === tradingDays.length - 1">
                                            <span class="px-1 py-0.2 rounded text-[9px] bg-indigo-600 text-white uppercase font-bold">Latest</span>
                                        </template>
                                    </div>
                                    <div class="text-[10px] font-medium text-slate-500 font-sans mt-0.5" x-text="day.formatted"></div>
                                </th>
                            </template>
                            <th class="py-3 px-3 text-center bg-slate-50 font-black text-slate-800 border-r border-slate-200 min-w-[170px]">
                                Net Expiry Shift (Day 1 → Latest)
                            </th>
                            <th class="py-3 px-2 text-center bg-slate-50 min-w-[70px]">
                                Drilldown
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-mono">
                        <template x-for="row in multiDayStrikes" :key="row.strike">
                            <tr :class="row.is_atm ? 'bg-indigo-50/50 border-y-2 border-indigo-400 font-semibold' : 'hover:bg-slate-50 transition'">
                                
                                <!-- Sticky Left: Strike & Moneyness -->
                                <td class="py-2.5 px-3 sticky left-0 z-10 border-r border-slate-200 whitespace-nowrap text-center"
                                    :class="row.is_atm ? 'bg-indigo-100/70' : 'bg-white'">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <span class="text-sm font-black text-slate-900" x-text="row.strike"></span>
                                        <template x-if="row.is_atm">
                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase bg-indigo-600 text-white">ATM</span>
                                        </template>
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-sans mt-0.5" x-text="row.moneyness"></div>
                                </td>

                                <!-- Day-by-Day Columns -->
                                <template x-for="(day, dIdx) in row.days" :key="day.quote_date">
                                    <td class="py-2.5 px-3 border-r border-slate-100 whitespace-nowrap text-right"
                                        :class="dIdx === tradingDays.length - 1 ? 'bg-indigo-50/20' : ''">

                                        <!-- VIEW MODE: ALL METRICS -->
                                        <template x-if="multiDayTableFilter === 'all'">
                                            <div>
                                                <!-- Straddle Line -->
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-[10px] text-slate-400 font-sans">Straddle:</span>
                                                    <div>
                                                        <template x-if="day.straddle !== null && day.straddle !== undefined">
                                                            <div>
                                                                <span class="font-bold text-slate-900" x-text="'₹' + Number(day.straddle).toFixed(1)"></span>
                                                                <template x-if="dIdx > 0 && day.straddle_diff !== null && day.straddle_diff !== undefined">
                                                                    <span class="text-[10px] ml-1 font-semibold"
                                                                          :class="day.straddle_diff <= 0 ? 'text-emerald-600' : 'text-rose-600'"
                                                                          x-text="(day.straddle_diff <= 0 ? '↓' : '↑') + Math.abs(day.straddle_diff).toFixed(1)"></span>
                                                                </template>
                                                            </div>
                                                        </template>
                                                        <template x-if="day.straddle === null || day.straddle === undefined">
                                                            <span class="text-slate-400 font-sans italic text-[11px]" title="Untraded on this session (One leg had 0 trades)">—</span>
                                                        </template>
                                                    </div>
                                                </div>
                                                <!-- OI Line -->
                                                <div class="flex items-center justify-between gap-2 text-[11px] mt-0.5">
                                                    <span class="text-[10px] text-slate-400 font-sans">OI:</span>
                                                    <div>
                                                        <span class="font-semibold text-slate-700" x-text="formatNumberCompact(day.oi || 0)"></span>
                                                        <template x-if="dIdx > 0">
                                                            <span class="text-[9px] ml-1 font-semibold"
                                                                  :class="(day.oi_diff || 0) >= 0 ? 'text-indigo-600' : 'text-slate-400'"
                                                                  x-text="((day.oi_diff || 0) >= 0 ? '+' : '') + (day.oi_pct || 0) + '%'"></span>
                                                        </template>
                                                    </div>
                                                </div>
                                                <!-- Volume Line -->
                                                <div class="flex items-center justify-between gap-2 text-[10px] text-slate-500 mt-0.5">
                                                    <span class="text-[10px] text-slate-400 font-sans">Vol:</span>
                                                    <div>
                                                        <span x-text="formatNumberCompact(day.vol || 0)"></span>
                                                        <template x-if="dIdx > 0">
                                                            <span class="text-[9px] ml-1"
                                                                  :class="(day.vol_diff || 0) >= 0 ? 'text-purple-600' : 'text-slate-400'"
                                                                  x-text="((day.vol_diff || 0) >= 0 ? '+' : '') + (day.vol_pct || 0) + '%'"></span>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- VIEW MODE: STRADDLE ONLY -->
                                        <template x-if="multiDayTableFilter === 'straddle'">
                                            <div class="py-1">
                                                <template x-if="day.straddle !== null && day.straddle !== undefined">
                                                    <div>
                                                        <div class="text-sm font-black text-slate-900" x-text="'₹' + Number(day.straddle).toFixed(2)"></div>
                                                        <div class="flex items-center justify-end gap-1 text-[10px] mt-0.5">
                                                            <template x-if="dIdx > 0 && day.straddle_diff !== null && day.straddle_diff !== undefined">
                                                                <span class="px-1.5 py-0.2 rounded font-semibold"
                                                                      :class="day.straddle_diff <= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                                                      x-text="(day.straddle_diff <= 0 ? 'Decay ' : 'Expansion ') + (day.straddle_diff <= 0 ? '' : '+') + Number(day.straddle_diff).toFixed(1) + ' (' + (day.straddle_pct || 0) + '%)'"></span>
                                                            </template>
                                                            <template x-if="dIdx === 0">
                                                                <span class="text-slate-400 font-sans">Day 1 Base</span>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </template>
                                                <template x-if="day.straddle === null || day.straddle === undefined">
                                                    <div class="text-slate-400 text-xs italic font-sans py-1">Untraded (0 Vol)</div>
                                                </template>
                                            </div>
                                        </template>

                                        <!-- VIEW MODE: OI ONLY -->
                                        <template x-if="multiDayTableFilter === 'oi'">
                                            <div class="py-1">
                                                <div class="text-sm font-black text-slate-900" x-text="Number(day.oi || 0).toLocaleString()"></div>
                                                <div class="flex items-center justify-end gap-1 text-[10px] mt-0.5">
                                                    <template x-if="dIdx > 0">
                                                        <span class="px-1.5 py-0.2 rounded font-semibold"
                                                              :class="(day.oi_diff || 0) >= 0 ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-700'"
                                                              x-text="((day.oi_diff || 0) >= 0 ? '+' : '') + formatNumberCompact(day.oi_diff || 0) + ' (' + (day.oi_pct || 0) + '%)'"></span>
                                                    </template>
                                                    <template x-if="dIdx === 0">
                                                        <span class="text-slate-400 font-sans">Day 1 Base</span>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- VIEW MODE: VOLUME ONLY -->
                                        <template x-if="multiDayTableFilter === 'vol'">
                                            <div class="py-1">
                                                <div class="text-sm font-black text-slate-900" x-text="Number(day.vol || 0).toLocaleString()"></div>
                                                <div class="flex items-center justify-end gap-1 text-[10px] mt-0.5">
                                                    <template x-if="dIdx > 0">
                                                        <span class="px-1.5 py-0.2 rounded font-semibold"
                                                              :class="(day.vol_diff || 0) >= 0 ? 'bg-purple-100 text-purple-800' : 'bg-slate-100 text-slate-700'"
                                                              x-text="((day.vol_diff || 0) >= 0 ? '+' : '') + formatNumberCompact(day.vol_diff || 0) + ' (' + (day.vol_pct || 0) + '%)'"></span>
                                                    </template>
                                                    <template x-if="dIdx === 0">
                                                        <span class="text-slate-400 font-sans">Day 1 Base</span>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>

                                    </td>
                                </template>

                                <!-- Net Expiry Shift Column -->
                                <td class="py-2.5 px-3 border-r border-slate-200 text-right whitespace-nowrap bg-slate-50/70">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-[10px] text-slate-500 font-sans font-semibold">Straddle Net:</span>
                                        <template x-if="row.net_straddle_diff !== null && row.net_straddle_diff !== undefined">
                                            <span class="font-bold text-xs"
                                                  :class="row.net_straddle_diff <= 0 ? 'text-emerald-700' : 'text-rose-700'"
                                                  x-text="(row.net_straddle_diff <= 0 ? '' : '+') + Number(row.net_straddle_diff).toFixed(1) + ' (' + (row.net_straddle_pct || 0) + '%)'"></span>
                                        </template>
                                        <template x-if="row.net_straddle_diff === null || row.net_straddle_diff === undefined">
                                            <span class="text-slate-400 text-xs italic font-sans">—</span>
                                        </template>
                                    </div>
                                    <div class="flex items-center justify-between gap-2 text-[11px] mt-1">
                                        <span class="text-[10px] text-slate-500 font-sans font-semibold">OI Growth:</span>
                                        <span class="font-bold text-xs"
                                              :class="(row.net_oi_diff || 0) >= 0 ? 'text-indigo-700' : 'text-slate-600'"
                                              x-text="((row.net_oi_diff || 0) >= 0 ? '+' : '') + formatNumberCompact(row.net_oi_diff || 0) + ' (' + (row.net_oi_pct || 0) + '%)'"></span>
                                    </div>
                                </td>

                                <!-- Drilldown Action -->
                                <td class="py-2.5 px-2 text-center whitespace-nowrap bg-slate-50/50">
                                    <button type="button"
                                            @click="openStrikeHistory(row.strike)"
                                            class="px-2 py-1 rounded bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 font-sans text-[11px] font-bold transition cursor-pointer">
                                        Inspect
                                    </button>
                                </td>

                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Table Footer -->
            <div class="p-3 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between text-xs text-slate-500 gap-2">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="flex items-center gap-1 font-sans"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> <strong>↓ Straddle Decay:</strong> Premium crushed (Decay favorable for sellers)</span>
                    <span class="flex items-center gap-1 font-sans"><span class="w-2 h-2 rounded-full bg-rose-500"></span> <strong>↑ Straddle Expansion:</strong> Premium inflated (Volatility / breakout)</span>
                    <span class="flex items-center gap-1 font-sans"><span class="w-2 h-2 rounded-full bg-indigo-500"></span> <strong>+ OI Growth:</strong> Position accumulation</span>
                </div>
                <div class="font-sans">
                    Showing all recorded days for expiry {{ \Carbon\Carbon::parse($selected_expiry)->format('d M Y') }}
                </div>
            </div>

        </div>

    </div>
    <div x-show="activeTab === 'charts'" class="space-y-4" style="display: none;">
        
        <!-- Chart 1: Strike Open Interest Difference (CE vs PE Δ) -->
        <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-4">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Strike-wise Day-over-Day OI Net Additions / Unwinding</h3>
                    <p class="text-[11px] text-slate-400">Comparing contract changes (+ additions / - unwinding) across strikes for {{ \Carbon\Carbon::parse($selected_date)->format('d M Y') }}</p>
                </div>
                <div class="flex items-center gap-3 text-xs">
                    <span class="flex items-center gap-1 text-rose-600 font-bold"><span class="w-3 h-3 rounded bg-rose-500"></span> CE OI Diff</span>
                    <span class="flex items-center gap-1 text-emerald-600 font-bold"><span class="w-3 h-3 rounded bg-emerald-500"></span> PE OI Diff</span>
                </div>
            </div>
            <div class="h-80 w-full">
                <canvas id="strikeOiDiffChart"></canvas>
            </div>
        </div>

        <!-- Charts Grid (2 columns): Volume Profile & Expiry Progression -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            
            <!-- Chart 2: Total Strike Volume Profile -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Strike Liquidity Profile (Volume Distribution)</h3>
                    <span class="text-[10px] text-slate-400">CE vs PE Volume</span>
                </div>
                <div class="h-72 w-full">
                    <canvas id="strikeVolumeChart"></canvas>
                </div>
            </div>

            <!-- Chart 3: Multi-Day Expiry Timeline (Spot vs PCR vs Total OI) -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Expiry Lifespan Progression (Spot &amp; PCR)</h3>
                    <span class="text-[10px] text-slate-400">Evolution from Day 1 to Expiry</span>
                </div>
                <div class="h-72 w-full">
                    <canvas id="expiryTimelineChart"></canvas>
                </div>
            </div>

        </div>

    </div>

    <!-- ════════════════════════════════════════════════════════════════════════
         MODAL / DRAWER: SINGLE STRIKE MULTI-DAY LIFESPAN INSPECTOR
    ════════════════════════════════════════════════════════════════════════ -->
    <div x-show="strikeModalOpen"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6"
         @keydown.escape.window="closeStrikeModal()">
        
        <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-6xl overflow-hidden my-auto"
             @click.outside="closeStrikeModal()">
            
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center font-mono font-black text-white text-lg shadow-inner">
                        <span x-text="selectedStrikeForModal"></span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold tracking-tight">
                                Strike <span x-text="selectedStrikeForModal"></span> Lifecycle Dynamics & Straddle Chart
                            </h3>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-indigo-500/30 text-indigo-200 border border-indigo-400/30">
                                Expiry: {{ \Carbon\Carbon::parse($selected_expiry)->format('d M Y') }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Multi-day progression of Straddle premium, Call/Put decays, underlying spot, and Open Interest.
                        </p>
                    </div>
                </div>

                <button type="button" @click="closeStrikeModal()" class="text-slate-400 hover:text-white transition p-1.5 rounded-lg hover:bg-slate-800">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 max-h-[82vh] overflow-y-auto custom-scrollbar">
                
                <div x-show="loadingStrikeHistory" class="py-16 text-center text-slate-400">
                    <svg class="animate-spin h-7 w-7 text-indigo-600 mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    <span class="text-sm font-medium">Loading session history for strike <span x-text="selectedStrikeForModal" class="font-bold font-mono"></span>...</span>
                </div>

                <div x-show="!loadingStrikeHistory && errorMessage" class="py-16 text-center text-rose-500 font-medium">
                    <p x-text="errorMessage"></p>
                </div>

                <div x-show="!loadingStrikeHistory && !errorMessage && strikeHistoryData.length > 0">
                    
                    <!-- 1. Top KPI Summary Strip -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                        <!-- Straddle Decay Card -->
                        <div class="bg-indigo-50/70 border border-indigo-100 rounded-xl p-3.5">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-700">Straddle Premium</span>
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-indigo-200/70 text-indigo-900">CE + PE</span>
                            </div>
                            <div class="flex items-baseline gap-2 mt-1.5">
                                <span class="text-xl font-black text-indigo-950 font-mono" x-text="'₹' + Number(getLatestRecord()?.straddle_close || 0).toFixed(2)"></span>
                                <span class="text-xs font-bold font-mono" 
                                      :class="getStraddleDecay().pts <= 0 ? 'text-emerald-700' : 'text-rose-700'"
                                      x-text="(getStraddleDecay().pts <= 0 ? '' : '+') + getStraddleDecay().pts + ' (' + getStraddleDecay().pct + '%)'"></span>
                            </div>
                            <div class="text-[10px] text-indigo-600 mt-1 flex items-center justify-between">
                                <span>Day 1 Open: ₹<span class="font-mono font-semibold" x-text="Number(getFirstRecord()?.straddle_close || 0).toFixed(2)"></span></span>
                                <span class="font-semibold" :class="getStraddleDecay().pts <= 0 ? 'text-emerald-700' : 'text-rose-700'" x-text="getStraddleDecay().pts <= 0 ? 'Decayed' : 'Expanded'"></span>
                            </div>
                        </div>

                        <!-- Call CE LTP -->
                        <div class="bg-rose-50/70 border border-rose-100 rounded-xl p-3.5">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-rose-700">Call LTP (CE)</span>
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase" 
                                      :class="getLatestRecord()?.moneyness_ce === 'ITM' ? 'bg-rose-200 text-rose-900' : (getLatestRecord()?.moneyness_ce === 'ATM' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-200 text-slate-700')"
                                      x-text="getLatestRecord()?.moneyness_ce || '-'"></span>
                            </div>
                            <div class="flex items-baseline gap-2 mt-1.5">
                                <span class="text-xl font-black text-rose-950 font-mono" x-text="'₹' + Number(getLatestRecord()?.ce?.close || 0).toFixed(2)"></span>
                                <span class="text-xs font-bold font-mono" 
                                      :class="(getLatestRecord()?.ce?.price_diff || 0) >= 0 ? 'text-emerald-600' : 'text-rose-600'"
                                      x-text="((getLatestRecord()?.ce?.price_diff || 0) >= 0 ? '+' : '') + Number(getLatestRecord()?.ce?.price_diff || 0).toFixed(1)"></span>
                            </div>
                            <div class="text-[10px] text-rose-600 mt-1 flex items-center justify-between">
                                <span>OI: <span class="font-mono font-semibold" x-text="Number(getLatestRecord()?.ce?.oi || 0).toLocaleString()"></span></span>
                                <span class="font-semibold uppercase" x-text="getLatestRecord()?.ce?.buildup?.code || '-'"></span>
                            </div>
                        </div>

                        <!-- Put PE LTP -->
                        <div class="bg-emerald-50/70 border border-emerald-100 rounded-xl p-3.5">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Put LTP (PE)</span>
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase"
                                      :class="getLatestRecord()?.moneyness_pe === 'ITM' ? 'bg-emerald-200 text-emerald-900' : (getLatestRecord()?.moneyness_pe === 'ATM' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-200 text-slate-700')"
                                      x-text="getLatestRecord()?.moneyness_pe || '-'"></span>
                            </div>
                            <div class="flex items-baseline gap-2 mt-1.5">
                                <span class="text-xl font-black text-emerald-950 font-mono" x-text="'₹' + Number(getLatestRecord()?.pe?.close || 0).toFixed(2)"></span>
                                <span class="text-xs font-bold font-mono" 
                                      :class="(getLatestRecord()?.pe?.price_diff || 0) >= 0 ? 'text-emerald-600' : 'text-rose-600'"
                                      x-text="((getLatestRecord()?.pe?.price_diff || 0) >= 0 ? '+' : '') + Number(getLatestRecord()?.pe?.price_diff || 0).toFixed(1)"></span>
                            </div>
                            <div class="text-[10px] text-emerald-600 mt-1 flex items-center justify-between">
                                <span>OI: <span class="font-mono font-semibold" x-text="Number(getLatestRecord()?.pe?.oi || 0).toLocaleString()"></span></span>
                                <span class="font-semibold uppercase" x-text="getLatestRecord()?.pe?.buildup?.code || '-'"></span>
                            </div>
                        </div>

                        <!-- Total Strike OI & Spot -->
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-3.5">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-600">Total Strike OI</span>
                                <span class="text-[10px] font-semibold text-slate-500 font-mono" x-text="strikeHistoryData.length + ' Sessions'"></span>
                            </div>
                            <div class="flex items-baseline gap-2 mt-1.5">
                                <span class="text-xl font-black text-slate-900 font-mono" x-text="Number(getLatestRecord()?.total_oi || 0).toLocaleString()"></span>
                                <span class="text-xs font-bold font-mono"
                                      :class="getOiChange().diff >= 0 ? 'text-indigo-600' : 'text-slate-500'"
                                      x-text="(getOiChange().diff >= 0 ? '+' : '') + getOiChange().diffFormatted"></span>
                            </div>
                            <div class="text-[10px] text-slate-500 mt-1">
                                Spot Close: <span class="font-mono font-bold text-slate-700" x-text="Number(getLatestRecord()?.spot_close || 0).toLocaleString()"></span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Interactive Chart Card -->
                    <div class="bg-slate-50/80 border border-slate-200 rounded-xl p-4 mb-5 shadow-xs">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-3 pb-3 border-b border-slate-200">
                            <div>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>
                                    <span>Strike Trajectory Chart across Expiry Days</span>
                                </h4>
                                <p class="text-[11px] text-slate-500 mt-0.5">
                                    Straddle line is shown by default. Click series pills to toggle CE Close, PE Close, Spot Index, or Total OI.
                                </p>
                            </div>

                            <!-- Interactive Quick Toggle Pills -->
                            <div class="flex flex-wrap items-center gap-1.5">
                                <button type="button"
                                        @click="toggleModalDataset('straddle', 0)"
                                        class="px-2.5 py-1 rounded-md text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs"
                                        :class="modalSeriesVisible.straddle ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'">
                                    <span class="w-2 h-2 rounded-full" :class="modalSeriesVisible.straddle ? 'bg-white' : 'bg-indigo-600'"></span>
                                    <span>Straddle (CE+PE)</span>
                                </button>

                                <button type="button"
                                        @click="toggleModalDataset('ce', 1)"
                                        class="px-2.5 py-1 rounded-md text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs"
                                        :class="modalSeriesVisible.ce ? 'bg-rose-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'">
                                    <span class="w-2 h-2 rounded-full" :class="modalSeriesVisible.ce ? 'bg-white' : 'bg-rose-500'"></span>
                                    <span>CE Close</span>
                                </button>

                                <button type="button"
                                        @click="toggleModalDataset('pe', 2)"
                                        class="px-2.5 py-1 rounded-md text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs"
                                        :class="modalSeriesVisible.pe ? 'bg-emerald-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'">
                                    <span class="w-2 h-2 rounded-full" :class="modalSeriesVisible.pe ? 'bg-white' : 'bg-emerald-500'"></span>
                                    <span>PE Close</span>
                                </button>

                                <button type="button"
                                        @click="toggleModalDataset('spot', 3)"
                                        class="px-2.5 py-1 rounded-md text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs"
                                        :class="modalSeriesVisible.spot ? 'bg-sky-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'">
                                    <span class="w-2 h-2 rounded-full" :class="modalSeriesVisible.spot ? 'bg-white' : 'bg-sky-500'"></span>
                                    <span>NIFTY Spot</span>
                                </button>

                                <button type="button"
                                        @click="toggleModalDataset('oi', 4)"
                                        class="px-2.5 py-1 rounded-md text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs"
                                        :class="modalSeriesVisible.oi ? 'bg-slate-700 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'">
                                    <span class="w-2 h-2 rounded-full" :class="modalSeriesVisible.oi ? 'bg-white' : 'bg-slate-400'"></span>
                                    <span>Total OI</span>
                                </button>
                            </div>
                        </div>

                        <!-- Canvas Container -->
                        <div class="relative h-[255px] w-full">
                            <canvas id="strikeLifecycleChart"></canvas>
                        </div>
                    </div>

                    <!-- 3. Day-by-Day Historical Evolution Table -->
                    <div class="border border-slate-200 rounded-xl overflow-hidden shadow-xs">
                        <table class="w-full text-xs text-left border-collapse select-text">
                            <thead>
                                <tr class="bg-slate-100 text-[11px] font-bold text-slate-700 border-b border-slate-200">
                                    <th class="py-2.5 px-2.5 text-center">Day #</th>
                                    <th class="py-2.5 px-2.5">Date</th>
                                    <th class="py-2.5 px-2.5 text-right">Spot Close</th>
                                    <th class="py-2.5 px-2.5 text-center">ATM</th>
                                    <th class="py-2.5 px-2.5 text-right border-l border-slate-200 text-rose-700 bg-rose-50/50">CE Close (Δ)</th>
                                    <th class="py-2.5 px-2.5 text-right text-rose-700 bg-rose-50/50">CE Vol (Δ%)</th>
                                    <th class="py-2.5 px-2.5 text-right text-rose-700 bg-rose-50/50">CE OI (Δ%)</th>
                                    <th class="py-2.5 px-2.5 text-center text-rose-700 bg-rose-50/50">CE Buildup</th>
                                    <th class="py-2.5 px-2.5 text-right border-l border-slate-200 text-emerald-700 bg-emerald-50/50">PE Close (Δ)</th>
                                    <th class="py-2.5 px-2.5 text-right text-emerald-700 bg-emerald-50/50">PE Vol (Δ%)</th>
                                    <th class="py-2.5 px-2.5 text-right text-emerald-700 bg-emerald-50/50">PE OI (Δ%)</th>
                                    <th class="py-2.5 px-2.5 text-center text-emerald-700 bg-emerald-50/50">PE Buildup</th>
                                    <th class="py-2.5 px-2.5 text-right border-l border-slate-200 font-bold bg-indigo-50/40 text-indigo-950">Straddle Close</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-mono">
                                <template x-for="day in strikeHistoryData" :key="day.quote_date">
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="py-2 px-2.5 text-center font-bold text-slate-500" x-text="'Day ' + (day.day_number || '-')"></td>
                                        <td class="py-2 px-2.5 font-semibold text-slate-900 whitespace-nowrap" x-text="day.quote_date_formatted || day.quote_date"></td>
                                        <td class="py-2 px-2.5 text-right font-bold text-slate-700" x-text="day.spot_close ? Number(day.spot_close).toFixed(1) : '-'"></td>
                                        <td class="py-2 px-2.5 text-center" x-text="day.atm_strike || '-'"></td>

                                        <!-- CE Details -->
                                        <td class="py-2 px-2.5 text-right border-l border-slate-100 whitespace-nowrap">
                                            <span class="font-bold text-slate-800" x-text="Number(day.ce?.close || 0).toFixed(2)"></span>
                                            <span class="text-[10px] ml-1 font-semibold" :class="(day.ce?.price_diff || 0) >= 0 ? 'text-emerald-600' : 'text-rose-600'" x-text="((day.ce?.price_diff || 0) >= 0 ? '+' : '') + Number(day.ce?.price_diff || 0).toFixed(1)"></span>
                                        </td>
                                        <td class="py-2 px-2.5 text-right whitespace-nowrap">
                                            <div x-text="Number(day.ce?.volume || 0).toLocaleString()"></div>
                                            <div class="text-[10px]" :class="(day.ce?.volume_diff || 0) >= 0 ? 'text-indigo-600' : 'text-slate-400'" x-text="((day.ce?.volume_diff || 0) >= 0 ? '+' : '') + (day.ce?.volume_pct || 0) + '%'"></div>
                                        </td>
                                        <td class="py-2 px-2.5 text-right whitespace-nowrap">
                                            <div class="font-bold" x-text="Number(day.ce?.oi || 0).toLocaleString()"></div>
                                            <div class="text-[10px]" :class="(day.ce?.oi_diff || 0) >= 0 ? 'text-rose-600' : 'text-emerald-600'" x-text="((day.ce?.oi_diff || 0) >= 0 ? '+' : '') + (day.ce?.oi_pct || 0) + '%'"></div>
                                        </td>
                                        <td class="py-2 px-2.5 text-center font-sans">
                                            <span class="px-1.5 py-0.2 rounded text-[10px] font-bold"
                                                  :class="{
                                                      'bg-emerald-100 text-emerald-800': day.ce?.buildup?.color === 'emerald',
                                                      'bg-rose-100 text-rose-800': day.ce?.buildup?.color === 'rose',
                                                      'bg-sky-100 text-sky-800': day.ce?.buildup?.color === 'sky',
                                                      'bg-amber-100 text-amber-800': day.ce?.buildup?.color === 'amber',
                                                      'bg-slate-100 text-slate-600': !day.ce?.buildup?.color || day.ce?.buildup?.color === 'slate'
                                                  }"
                                                  x-text="day.ce?.buildup?.code || '-'" :title="day.ce?.buildup?.desc || ''"></span>
                                        </td>

                                        <!-- PE Details -->
                                        <td class="py-2 px-2.5 text-right border-l border-slate-100 whitespace-nowrap">
                                            <span class="font-bold text-slate-800" x-text="Number(day.pe?.close || 0).toFixed(2)"></span>
                                            <span class="text-[10px] ml-1 font-semibold" :class="(day.pe?.price_diff || 0) >= 0 ? 'text-emerald-600' : 'text-rose-600'" x-text="((day.pe?.price_diff || 0) >= 0 ? '+' : '') + Number(day.pe?.price_diff || 0).toFixed(1)"></span>
                                        </td>
                                        <td class="py-2 px-2.5 text-right whitespace-nowrap">
                                            <div x-text="Number(day.pe?.volume || 0).toLocaleString()"></div>
                                            <div class="text-[10px]" :class="(day.pe?.volume_diff || 0) >= 0 ? 'text-indigo-600' : 'text-slate-400'" x-text="((day.pe?.volume_diff || 0) >= 0 ? '+' : '') + (day.pe?.volume_pct || 0) + '%'"></div>
                                        </td>
                                        <td class="py-2 px-2.5 text-right whitespace-nowrap">
                                            <div class="font-bold" x-text="Number(day.pe?.oi || 0).toLocaleString()"></div>
                                            <div class="text-[10px]" :class="(day.pe?.oi_diff || 0) >= 0 ? 'text-emerald-600' : 'text-rose-600'" x-text="((day.pe?.oi_diff || 0) >= 0 ? '+' : '') + (day.pe?.oi_pct || 0) + '%'"></div>
                                        </td>
                                        <td class="py-2 px-2.5 text-center font-sans">
                                            <span class="px-1.5 py-0.2 rounded text-[10px] font-bold"
                                                  :class="{
                                                      'bg-emerald-100 text-emerald-800': day.pe?.buildup?.color === 'emerald',
                                                      'bg-rose-100 text-rose-800': day.pe?.buildup?.color === 'rose',
                                                      'bg-sky-100 text-sky-800': day.pe?.buildup?.color === 'sky',
                                                      'bg-amber-100 text-amber-800': day.pe?.buildup?.color === 'amber',
                                                      'bg-slate-100 text-slate-600': !day.pe?.buildup?.color || day.pe?.buildup?.color === 'slate'
                                                  }"
                                                  x-text="day.pe?.buildup?.code || '-'" :title="day.pe?.buildup?.desc || ''"></span>
                                        </td>

                                        <!-- Straddle -->
                                        <td class="py-2 px-2.5 text-right border-l border-slate-100 font-bold bg-indigo-50/30 text-indigo-950" x-text="Number(day.straddle_close || 0).toFixed(2)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                </div>

            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
                <span class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span>Multi-day verified lifecycle history from end-of-day quotes</span>
                </span>
                <button type="button" @click="closeStrikeModal()" class="px-4 py-1.5 rounded-lg bg-slate-200 hover:bg-slate-300 font-semibold text-slate-800 transition cursor-pointer">
                    Close
                </button>
            </div>

        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
function expiryAnalysisApp() {
    return {
        activeTab: 'matrix',
        strikeModalOpen: false,
        selectedStrikeForModal: null,
        loadingStrikeHistory: false,
        errorMessage: null,
        strikeHistoryData: [],
        chartsInitialized: false,
        strikeChartInstance: null,
        modalSeriesVisible: {
            straddle: true,
            ce: false,
            pe: false,
            spot: false,
            oi: false,
        },

        // Multi-Day Dynamics (±10 strikes across all days)
        multiDayStrikes: @json($multiday_strikes),
        tradingDays: @json($trading_days),
        multiDayMetric: 'straddle', // 'straddle', 'oi', 'vol'
        multiDayTableFilter: 'all',  // 'all', 'straddle', 'oi', 'vol'
        multiDayChartInstance: null,
        dayColors: ['#94a3b8', '#0284c7', '#f59e0b', '#a855f7', '#4f46e5', '#10b981', '#f43f5e', '#ec4899'],

        // Server-injected data
        tableRows: @json($rows),
        timelineData: @json($timeline),
        symbol: '{{ $symbol }}',
        expiry: '{{ $selected_expiry }}',

        init() {
            // Hotkeys: 'c' charts, 'm' matrix, 'd' multiday dynamics
            window.addEventListener('keydown', (e) => {
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;
                if (e.key === 'c' || e.key === 'C') {
                    this.activeTab = 'charts';
                    this.$nextTick(() => this.renderCharts());
                } else if (e.key === 'm' || e.key === 'M') {
                    this.activeTab = 'matrix';
                } else if (e.key === 'd' || e.key === 'D') {
                    this.activeTab = 'multiday';
                    this.$nextTick(() => this.renderMultiDayChart());
                }
            });
        },

        setMultiDayMetric(metric) {
            this.multiDayMetric = metric;
            this.$nextTick(() => this.renderMultiDayChart());
        },

        formatNumberCompact(val) {
            val = Number(val || 0);
            const absVal = Math.abs(val);
            if (absVal >= 10000000) return (val / 10000000).toFixed(2) + 'Cr';
            if (absVal >= 100000) return (val / 100000).toFixed(2) + 'L';
            if (absVal >= 1000) return (val / 1000).toFixed(1) + 'k';
            return val.toLocaleString();
        },

        getAtmMorphSummary() {
            const atm = this.multiDayStrikes ? (this.multiDayStrikes.find(s => s.is_atm) || this.multiDayStrikes[0]) : null;
            if (!atm) return { strike: '-', firstStraddle: '0', latestStraddle: '0', decayPts: 0, decayPct: '0.0', latestOi: 0, oiDiff: 0, oiPct: '0.0' };
            const firstValidDay = atm.days ? atm.days.find(d => d.straddle !== null && d.straddle !== undefined) : null;
            const latestDay = atm.days ? atm.days[atm.days.length - 1] : null;
            const firstStraddle = firstValidDay ? Number(firstValidDay.straddle) : 0;
            const latestStraddle = (latestDay && latestDay.straddle !== null && latestDay.straddle !== undefined) ? Number(latestDay.straddle) : 0;
            const decayPts = (firstStraddle > 0 && latestStraddle > 0) ? Number((latestStraddle - firstStraddle).toFixed(2)) : 0;
            const decayPct = firstStraddle > 0 ? ((decayPts / firstStraddle) * 100).toFixed(1) : '0.0';
            const firstOi = Number(atm.days && atm.days.length > 0 ? (atm.days[0]?.oi || 0) : 0);
            const latestOi = Number(latestDay?.oi || 0);
            const oiDiff = latestOi - firstOi;
            const oiPct = firstOi > 0 ? ((oiDiff / firstOi) * 100).toFixed(1) : '0.0';
            return {
                strike: atm.strike,
                firstStraddle: firstStraddle.toFixed(1),
                latestStraddle: latestStraddle.toFixed(1),
                decayPts,
                decayPct,
                latestOi,
                oiDiff,
                oiPct,
            };
        },

        renderMultiDayChart() {
            if (!this.multiDayStrikes || this.multiDayStrikes.length === 0) return;

            const canvas = document.getElementById('multiDayStrikesChart');
            if (!canvas) return;

            if (this.multiDayChartInstance) {
                this.multiDayChartInstance.destroy();
                this.multiDayChartInstance = null;
            }

            const labels = this.multiDayStrikes.map(s => s.strike);
            const datasets = this.tradingDays.map((d, dIdx) => {
                const isLatest = (dIdx === this.tradingDays.length - 1);
                const color = this.dayColors[dIdx % this.dayColors.length] || '#4f46e5';

                const data = this.multiDayStrikes.map(s => {
                    const dayObj = (s.days && s.days[dIdx]) ? s.days[dIdx] : null;
                    if (!dayObj) return null;
                    if (this.multiDayMetric === 'straddle') {
                        return (dayObj.straddle !== null && dayObj.straddle !== undefined) ? Number(dayObj.straddle) : null;
                    }
                    if (this.multiDayMetric === 'oi') return Number(dayObj.oi || 0);
                    return Number(dayObj.vol || 0);
                });

                return {
                    label: 'Day ' + d.day_number + ' (' + d.formatted + ')' + (isLatest ? ' [Latest]' : ''),
                    data: data,
                    borderColor: color,
                    backgroundColor: isLatest ? 'rgba(79, 70, 229, 0.08)' : 'transparent',
                    fill: isLatest && (this.multiDayMetric === 'straddle'),
                    borderWidth: isLatest ? 3.5 : 2,
                    borderDash: (dIdx === 0 && !isLatest) ? [5, 5] : [],
                    pointRadius: isLatest ? 4 : 2.5,
                    pointHoverRadius: 6,
                    pointBackgroundColor: color,
                    tension: 0.25,
                    spanGaps: true,
                };
            });

            const metricName = (this.multiDayMetric === 'straddle') ? 'Straddle Premium (CE+PE) (₹)' : (this.multiDayMetric === 'oi' ? 'Total Strike Open Interest (Contracts)' : 'Total Strike Volume (Contracts)');

            const ctx = canvas.getContext('2d');
            this.multiDayChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: datasets,
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                boxWidth: 8,
                                padding: 14,
                                font: { size: 11, weight: 'bold' },
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.94)',
                            titleFont: { size: 12, weight: 'bold' },
                            bodyFont: { size: 11 },
                            padding: 10,
                            callbacks: {
                                label: (context) => {
                                    let label = context.dataset.label || '';
                                    if (label) label += ': ';
                                    if (context.parsed.y !== null && context.parsed.y !== undefined) {
                                        if (this.multiDayMetric === 'straddle') {
                                            label += '₹' + Number(context.parsed.y).toFixed(2);
                                        } else if (this.multiDayMetric === 'oi' || this.multiDayMetric === 'vol') {
                                            const val = context.parsed.y;
                                            label += (val >= 100000) ? (val / 100000).toFixed(2) + 'L' : Number(val).toLocaleString();
                                        }
                                    } else {
                                        label += 'Untraded (0 Vol)';
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(226, 232, 240, 0.5)' },
                            ticks: { font: { size: 11, weight: 600 }, color: '#475569' }
                        },
                        y: {
                            title: {
                                display: true,
                                text: metricName,
                                color: '#4f46e5',
                                font: { size: 11, weight: 'bold' }
                            },
                            grid: { color: 'rgba(226, 232, 240, 0.6)' },
                            ticks: {
                                color: '#4f46e5',
                                font: { weight: 600 },
                                callback: (v) => {
                                    if (this.multiDayMetric === 'straddle') return '₹' + v.toFixed(0);
                                    return (v >= 100000) ? (v / 100000).toFixed(1) + 'L' : (v / 1000).toFixed(0) + 'k';
                                }
                            }
                        }
                    }
                }
            });
        },

        openStrikeHistory(strike) {
            this.selectedStrikeForModal = strike;
            this.strikeModalOpen = true;
            this.loadingStrikeHistory = true;
            this.errorMessage = null;
            this.strikeHistoryData = [];
            this.modalSeriesVisible = {
                straddle: true,
                ce: false,
                pe: false,
                spot: false,
                oi: false,
            };

            if (this.strikeChartInstance) {
                this.strikeChartInstance.destroy();
                this.strikeChartInstance = null;
            }

            const baseUrl = '{{ route('api.expiry.oi.volume.analysis.strike-history') }}';
            const url = `${baseUrl}?symbol=${encodeURIComponent(this.symbol)}&expiry=${encodeURIComponent(this.expiry)}&strike=${encodeURIComponent(strike)}`;

            fetch(url)
                .then(res => {
                    if (!res.ok) throw new Error('Server returned HTTP ' + res.status);
                    return res.json();
                })
                .then(data => {
                    this.loadingStrikeHistory = false;
                    if (data && data.ok && Array.isArray(data.history) && data.history.length > 0) {
                        this.strikeHistoryData = data.history;
                        this.$nextTick(() => {
                            this.renderStrikeModalChart();
                        });
                    } else {
                        this.errorMessage = (data && data.message) ? data.message : 'No historical daily records found for strike ' + strike + ' in this expiry.';
                    }
                })
                .catch(err => {
                    this.loadingStrikeHistory = false;
                    this.errorMessage = 'Failed to load strike history: ' + err.message;
                    console.error('Failed to load strike history', err);
                });
        },

        closeStrikeModal() {
            this.strikeModalOpen = false;
            if (this.strikeChartInstance) {
                this.strikeChartInstance.destroy();
                this.strikeChartInstance = null;
            }
        },

        toggleModalDataset(key, index) {
            if (!this.strikeChartInstance) return;
            const isVisible = !this.modalSeriesVisible[key];
            this.modalSeriesVisible[key] = isVisible;
            this.strikeChartInstance.setDatasetVisibility(index, isVisible);
            this.strikeChartInstance.update();
        },

        getFirstRecord() {
            return (this.strikeHistoryData && this.strikeHistoryData.length > 0) ? this.strikeHistoryData[0] : null;
        },

        getLatestRecord() {
            return (this.strikeHistoryData && this.strikeHistoryData.length > 0) ? this.strikeHistoryData[this.strikeHistoryData.length - 1] : null;
        },

        getStraddleDecay() {
            const first = this.getFirstRecord();
            const latest = this.getLatestRecord();
            if (!first || !latest) return { pts: 0, pct: '0.0' };
            const p1 = Number(first.straddle_close || 0);
            const p2 = Number(latest.straddle_close || 0);
            const pts = Number((p2 - p1).toFixed(2));
            const pct = p1 > 0 ? ((pts / p1) * 100).toFixed(1) : '0.0';
            return { pts, pct };
        },

        getOiChange() {
            const first = this.getFirstRecord();
            const latest = this.getLatestRecord();
            if (!first || !latest) return { diff: 0, diffFormatted: '0' };
            const o1 = Number(first.total_oi || 0);
            const o2 = Number(latest.total_oi || 0);
            const diff = o2 - o1;
            const diffFormatted = (Math.abs(diff) >= 100000) ? (diff / 100000).toFixed(1) + 'L' : diff.toLocaleString();
            return { diff, diffFormatted };
        },

        renderStrikeModalChart() {
            if (!this.strikeHistoryData || this.strikeHistoryData.length === 0) return;

            const canvas = document.getElementById('strikeLifecycleChart');
            if (!canvas) return;

            if (this.strikeChartInstance) {
                this.strikeChartInstance.destroy();
                this.strikeChartInstance = null;
            }

            const labels = this.strikeHistoryData.map(d => {
                const dateParts = d.quote_date_formatted ? d.quote_date_formatted.split(' ') : [d.quote_date];
                const dateStr = (dateParts.length >= 2) ? (dateParts[0] + ' ' + dateParts[1]) : d.quote_date;
                return `Day ${d.day_number || ''} (${dateStr})`;
            });

            const straddleData = this.strikeHistoryData.map(d => Number(d.straddle_close || 0));
            const ceData = this.strikeHistoryData.map(d => Number(d.ce?.close || 0));
            const peData = this.strikeHistoryData.map(d => Number(d.pe?.close || 0));
            const spotData = this.strikeHistoryData.map(d => d.spot_close ? Number(d.spot_close) : null);
            const oiData = this.strikeHistoryData.map(d => Number(d.total_oi || 0));

            const ctx = canvas.getContext('2d');

            this.strikeChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Straddle Premium (CE+PE)',
                            data: straddleData,
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99, 102, 241, 0.12)',
                            fill: true,
                            tension: 0.25,
                            borderWidth: 3,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            pointBackgroundColor: '#6366f1',
                            yAxisID: 'y',
                            hidden: !this.modalSeriesVisible.straddle,
                        },
                        {
                            label: 'CE Close',
                            data: ceData,
                            borderColor: '#f43f5e',
                            backgroundColor: 'rgba(244, 63, 94, 0.08)',
                            fill: false,
                            tension: 0.25,
                            borderWidth: 2,
                            borderDash: [5, 4],
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#f43f5e',
                            yAxisID: 'y',
                            hidden: !this.modalSeriesVisible.ce,
                        },
                        {
                            label: 'PE Close',
                            data: peData,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.08)',
                            fill: false,
                            tension: 0.25,
                            borderWidth: 2,
                            borderDash: [5, 4],
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#10b981',
                            yAxisID: 'y',
                            hidden: !this.modalSeriesVisible.pe,
                        },
                        {
                            label: 'NIFTY Spot',
                            data: spotData,
                            borderColor: '#0284c7',
                            backgroundColor: 'transparent',
                            fill: false,
                            tension: 0.2,
                            borderWidth: 2,
                            borderDash: [2, 2],
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: '#0284c7',
                            yAxisID: 'ySpot',
                            hidden: !this.modalSeriesVisible.spot,
                        },
                        {
                            label: 'Total Strike OI',
                            type: 'bar',
                            data: oiData,
                            backgroundColor: 'rgba(148, 163, 184, 0.25)',
                            borderColor: 'rgba(148, 163, 184, 0.6)',
                            borderWidth: 1,
                            borderRadius: 4,
                            yAxisID: 'yOi',
                            hidden: !this.modalSeriesVisible.oi,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                boxWidth: 8,
                                padding: 12,
                                font: { size: 11, weight: 'bold' }
                            },
                            onClick: (e, legendItem, legend) => {
                                const index = legendItem.datasetIndex;
                                const ci = legend.chart;
                                const willBeVisible = !ci.isDatasetVisible(index);
                                ci.setDatasetVisibility(index, willBeVisible);
                                ci.update();

                                const keys = ['straddle', 'ce', 'pe', 'spot', 'oi'];
                                if (keys[index]) {
                                    this.modalSeriesVisible[keys[index]] = willBeVisible;
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.94)',
                            titleFont: { size: 12, weight: 'bold' },
                            bodyFont: { size: 11 },
                            padding: 10,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) label += ': ';
                                    if (context.parsed.y !== null && context.parsed.y !== undefined) {
                                        if (context.dataset.yAxisID === 'y') {
                                            label += '₹' + Number(context.parsed.y).toFixed(2);
                                        } else if (context.dataset.yAxisID === 'ySpot') {
                                            label += Number(context.parsed.y).toLocaleString() + ' pts';
                                        } else if (context.dataset.yAxisID === 'yOi') {
                                            const val = context.parsed.y;
                                            label += (val >= 100000) ? (val / 100000).toFixed(2) + 'L' : Number(val).toLocaleString();
                                        } else {
                                            label += context.parsed.y;
                                        }
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 11, weight: 600 }, color: '#475569' }
                        },
                        y: {
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Option / Straddle Premium (₹)',
                                color: '#6366f1',
                                font: { size: 11, weight: 'bold' }
                            },
                            grid: { color: 'rgba(226, 232, 240, 0.6)' },
                            ticks: {
                                color: '#6366f1',
                                font: { weight: 600 },
                                callback: (v) => '₹' + v.toFixed(0)
                            }
                        },
                        ySpot: {
                            position: 'right',
                            display: 'auto',
                            grid: { drawOnChartArea: false },
                            title: {
                                display: true,
                                text: 'NIFTY Spot',
                                color: '#0284c7',
                                font: { size: 11, weight: 'bold' }
                            },
                            ticks: {
                                color: '#0284c7',
                                callback: (v) => v.toLocaleString()
                            }
                        },
                        yOi: {
                            position: 'right',
                            display: 'auto',
                            grid: { drawOnChartArea: false },
                            title: {
                                display: true,
                                text: 'Total OI',
                                color: '#64748b',
                                font: { size: 11, weight: 'bold' }
                            },
                            ticks: {
                                color: '#64748b',
                                callback: (v) => (v >= 100000) ? (v / 100000).toFixed(1) + 'L' : (v / 1000).toFixed(0) + 'k'
                            }
                        }
                    }
                }
            });
        },

        renderCharts() {
            if (this.chartsInitialized) return;
            this.chartsInitialized = true;

            const strikes = this.tableRows.map(r => r.strike);
            const ceOiDiffs = this.tableRows.map(r => r.ce.oi_diff);
            const peOiDiffs = this.tableRows.map(r => r.pe.oi_diff);
            const ceVols = this.tableRows.map(r => r.ce.volume);
            const peVols = this.tableRows.map(r => r.pe.volume);

            // Chart 1: Strike OI Diff Bar Chart
            const ctx1 = document.getElementById('strikeOiDiffChart');
            if (ctx1) {
                new Chart(ctx1, {
                    type: 'bar',
                    data: {
                        labels: strikes,
                        datasets: [
                            {
                                label: 'CE OI Diff',
                                data: ceOiDiffs,
                                backgroundColor: 'rgba(239, 68, 68, 0.75)',
                                borderColor: 'rgb(239, 68, 68)',
                                borderWidth: 1
                            },
                            {
                                label: 'PE OI Diff',
                                data: peOiDiffs,
                                backgroundColor: 'rgba(16, 185, 129, 0.75)',
                                borderColor: 'rgb(16, 185, 129)',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            x: { grid: { display: false } },
                            y: {
                                grid: { color: 'rgba(0,0,0,0.05)' },
                                ticks: {
                                    callback: function(val) {
                                        return (val >= 100000 || val <= -100000) ? (val / 100000).toFixed(1) + 'L' : (val / 1000).toFixed(0) + 'k';
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Chart 2: Volume Distribution
            const ctx2 = document.getElementById('strikeVolumeChart');
            if (ctx2) {
                new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: strikes,
                        datasets: [
                            {
                                label: 'CE Volume',
                                data: ceVols,
                                backgroundColor: 'rgba(244, 63, 94, 0.65)'
                            },
                            {
                                label: 'PE Volume',
                                data: peVols,
                                backgroundColor: 'rgba(16, 185, 129, 0.65)'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: { stacked: true, grid: { display: false } },
                            y: {
                                stacked: true,
                                ticks: {
                                    callback: function(val) {
                                        return (val / 1000000).toFixed(1) + 'M';
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Chart 3: Expiry Timeline (Spot & PCR)
            const ctx3 = document.getElementById('expiryTimelineChart');
            if (ctx3 && this.timelineData.length > 0) {
                const dates = this.timelineData.map(d => d.formatted_date);
                const spots = this.timelineData.map(d => d.spot_close);
                const pcrs = this.timelineData.map(d => d.pcr_oi);

                new Chart(ctx3, {
                    type: 'line',
                    data: {
                        labels: dates,
                        datasets: [
                            {
                                label: 'Spot Close',
                                data: spots,
                                borderColor: '#6366f1',
                                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                                yAxisID: 'ySpot',
                                tension: 0.25,
                                fill: true
                            },
                            {
                                label: 'OI PCR',
                                data: pcrs,
                                borderColor: '#10b981',
                                backgroundColor: 'transparent',
                                borderDash: [5, 5],
                                yAxisID: 'yPcr',
                                tension: 0.25
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            x: { grid: { display: false } },
                            ySpot: {
                                type: 'linear',
                                position: 'left',
                                title: { display: true, text: 'Spot Close' }
                            },
                            yPcr: {
                                type: 'linear',
                                position: 'right',
                                title: { display: true, text: 'OI PCR' },
                                grid: { drawOnChartArea: false }
                            }
                        }
                    }
                });
            }
        },

        exportToCsv() {
            if (!this.tableRows || this.tableRows.length === 0) return;

            let csv = 'Strike,Moneyness_CE,CE_LTP,CE_Price_Diff,CE_Price_Pct,CE_Volume,CE_Vol_Diff,CE_Vol_Pct,CE_OI,CE_OI_Diff,CE_OI_Pct,CE_Buildup,' +
                      'Straddle_LTP,Straddle_Diff,' +
                      'Moneyness_PE,PE_LTP,PE_Price_Diff,PE_Price_Pct,PE_Volume,PE_Vol_Diff,PE_Vol_Pct,PE_OI,PE_OI_Diff,PE_OI_Pct,PE_Buildup\n';

            this.tableRows.forEach(r => {
                csv += [
                    r.strike,
                    r.ce.moneyness,
                    r.ce.close,
                    r.ce.price_diff,
                    r.ce.price_pct,
                    r.ce.volume,
                    r.ce.volume_diff,
                    r.ce.volume_pct,
                    r.ce.oi,
                    r.ce.oi_diff,
                    r.ce.oi_pct,
                    r.ce.buildup.type,
                    r.straddle.close,
                    r.straddle.diff,
                    r.pe.moneyness,
                    r.pe.close,
                    r.pe.price_diff,
                    r.pe.price_pct,
                    r.pe.volume,
                    r.pe.volume_diff,
                    r.pe.volume_pct,
                    r.pe.oi,
                    r.pe.oi_diff,
                    r.pe.oi_pct,
                    r.pe.buildup.type
                ].join(',') + '\n';
            });

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `${this.symbol}_expiry_${this.expiry}_analysis.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    };
}

document.addEventListener('alpine:init', () => {
    Alpine.data('expiryAnalysisApp', expiryAnalysisApp);
});
</script>
@endpush
