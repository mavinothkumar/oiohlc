@extends('layouts.app')

@section('title', 'Market Operations & Cron Status Dashboard')

@section('content')
<div class="max-w-[1760px] mx-auto px-2 sm:px-4 lg:px-6 pb-12 space-y-6" id="dashboard-app">

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-start justify-between shadow-xs">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <h4 class="font-semibold text-sm">{{ session('success') }}</h4>
                    @if(session('command_output'))
                        <pre class="mt-2 text-xs bg-emerald-950 text-emerald-100 p-3 rounded-lg overflow-x-auto font-mono max-h-48 leading-relaxed">{{ session('command_output') }}</pre>
                    @endif
                </div>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700 text-lg font-bold">&times;</button>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 flex items-start justify-between shadow-xs">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-rose-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                    <h4 class="font-semibold text-sm">{{ session('error') }}</h4>
                </div>
            </div>
            <button onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700 text-lg font-bold">&times;</button>
        </div>
    @endif

    <!-- Top Hero Section: System Header & Market Session -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            
            <!-- Left Info -->
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider
                        @if($sessionColor === 'emerald') bg-emerald-100 text-emerald-800 border border-emerald-300
                        @elseif($sessionColor === 'amber') bg-amber-100 text-amber-800 border border-amber-300
                        @elseif($sessionColor === 'indigo') bg-indigo-100 text-indigo-800 border border-indigo-300
                        @else bg-slate-100 text-slate-700 border border-slate-300 @endif">
                        <span class="w-2 h-2 rounded-full @if($sessionColor === 'emerald') bg-emerald-500 animate-ping @elseif($sessionColor === 'amber') bg-amber-500 @else bg-slate-400 @endif"></span>
                        {{ $sessionLabel }}
                    </span>

                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Trading Day: <strong class="text-slate-900 ml-1">{{ $now->format('l, d M Y') }}</strong>
                    </span>

                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-mono font-medium bg-slate-100 text-slate-700 border border-slate-200" id="live-clock-pill">
                        <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        IST: <span id="clock-display" class="font-bold text-slate-900 ml-1">{{ $now->format('h:i:s A') }}</span>
                    </span>
                </div>

                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight flex items-center gap-3">
                    Daily Cron & Operations Readiness
                    @if($preMarketAllReady)
                        <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            All Pre-Market Tasks Ready
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-lg text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200 animate-pulse">
                            <svg class="w-3.5 h-3.5 text-rose-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            Attention Required: Cron Missed
                        </span>
                    @endif
                </h1>

                <p class="text-xs sm:text-sm text-slate-500 max-w-3xl leading-relaxed">
                    Automated pre-market verification matrix for NSE trading days. Verifies calendar flags, instrument keys, weekly expiries, previous OHLC, daily trend earth levels, pre-market discovered open, and automated strategy panels before regular trading starts at 09:15 AM.
                </p>
            </div>

            <!-- Right Fast Action Buttons -->
            <div class="flex flex-col sm:flex-row lg:flex-col xl:flex-row items-stretch sm:items-center gap-3 shrink-0">
                <!-- 1-Click Complete Pipeline Fix -->
                <form action="{{ route('cron.run') }}" method="POST" class="inline" onsubmit="return handlePipelineSubmit(this);">
                    @csrf
                    <input type="hidden" name="command" value="app:run-trading-pipeline">
                    <input type="hidden" name="skip_instruments" value="1">
                    <button type="submit" 
                            id="btn-run-pipeline"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider text-white bg-gradient-to-r from-indigo-600 via-indigo-700 to-indigo-800 hover:from-indigo-700 hover:to-indigo-900 shadow-sm hover:shadow transition-all transform active:scale-95">
                        <svg class="w-4 h-4 text-amber-300" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                        </svg>
                        <span>Run All Pre-Market Tasks</span>
                    </button>
                </form>

                <!-- Refresh / Auto-refresh -->
                <div class="flex items-center gap-2">
                    <button onclick="window.location.reload();" 
                            title="Refresh Dashboard Status"
                            class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition-colors">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>Refresh</span>
                    </button>

                    <button type="button" 
                            id="btn-auto-refresh"
                            onclick="toggleAutoRefresh()" 
                            class="inline-flex items-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 transition-colors">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span id="auto-refresh-label">Auto (30s)</span>
                    </button>
                </div>
            </div>

        </div>

        <!-- Quick Summary Strip -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 pt-5 mt-5 border-t border-slate-100">
            <div class="p-3 rounded-xl bg-slate-50/80 border border-slate-100">
                <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">Current Expiry</span>
                <span class="text-sm font-bold text-slate-900 mt-0.5 block truncate">{{ $metrics['currentExpiry'] }}</span>
            </div>

            <div class="p-3 rounded-xl bg-slate-50/80 border border-slate-100">
                <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">NIFTY Open</span>
                <span class="text-sm font-bold text-slate-900 mt-0.5 block">
                    @if($metrics['niftyOpen'])
                        {{ number_format($metrics['niftyOpen'], 2) }}
                    @else
                        <span class="text-rose-500 font-semibold">Not Set</span>
                    @endif
                </span>
            </div>

            <div class="p-3 rounded-xl bg-slate-50/80 border border-slate-100">
                <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">ATM Strike</span>
                <span class="text-sm font-bold text-indigo-700 mt-0.5 block">
                    @if($metrics['atmStrike'])
                        {{ number_format($metrics['atmStrike'], 0) }}
                    @else
                        <span class="text-slate-400">Pending</span>
                    @endif
                </span>
            </div>

            <div class="p-3 rounded-xl bg-slate-50/80 border border-slate-100">
                <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">Earth High / Low</span>
                <span class="text-sm font-bold text-slate-900 mt-0.5 block truncate">
                    @if($metrics['earthHigh'] && $metrics['earthLow'])
                        <span class="text-emerald-700">{{ number_format($metrics['earthHigh'], 0) }}</span> / <span class="text-rose-700">{{ number_format($metrics['earthLow'], 0) }}</span>
                    @else
                        <span class="text-slate-400">Pending</span>
                    @endif
                </span>
            </div>

            <div class="p-3 rounded-xl bg-slate-50/80 border border-slate-100">
                <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">Daily Strategy Panels</span>
                <span class="text-sm font-bold text-slate-900 mt-0.5 block">
                    @if($metrics['panelsCount'] > 0)
                        <span class="text-emerald-700">{{ $metrics['panelsCount'] }} Panels</span> <span class="text-xs text-slate-500 font-normal">({{ $metrics['legsCount'] }} legs)</span>
                    @else
                        <span class="text-rose-600 font-bold">0 Panels</span>
                    @endif
                </span>
            </div>

            <div class="p-3 rounded-xl bg-slate-50/80 border border-slate-100">
                <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">1m Live Stream</span>
                <span class="text-sm font-bold text-slate-900 mt-0.5 block truncate">
                    @if($metrics['candlesCount'] > 0)
                        <span class="text-emerald-700">{{ number_format($metrics['candlesCount']) }}</span> <span class="text-xs text-slate-500 font-normal">({{ $metrics['latestCandleTime'] }})</span>
                    @else
                        <span class="text-slate-400">Waiting 09:15</span>
                    @endif
                </span>
            </div>
        </div>
    </div>

    <!-- Alert Banner when Pre-Market Jobs are Missed or Incomplete -->
    @if(!$preMarketAllReady)
        <div class="p-5 rounded-2xl bg-gradient-to-r from-rose-50 via-rose-100/70 to-amber-50 border-2 border-rose-300 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="flex items-start gap-3.5">
                <div class="w-10 h-10 rounded-xl bg-rose-600 text-white flex items-center justify-center shrink-0 shadow-sm mt-0.5">
                    <svg class="w-6 h-6 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-rose-950">
                        Trading Risk: Pre-Market Cron Pipeline Incomplete ({{ $preMarketSuccessCount }}/{{ $preMarketCriticalTotal }} Ready)
                    </h3>
                    <p class="text-xs sm:text-sm text-rose-800 mt-0.5 max-w-3xl">
                        Critical data scheduled to be generated before market opens at 09:15 AM is missing. Dependent screens (Order Flow Cockpit, Daily Trend, Strategy Panels) cannot function properly until these jobs finish.
                    </p>
                </div>
            </div>

            <form action="{{ route('cron.run') }}" method="POST" onsubmit="return handlePipelineSubmit(this);">
                @csrf
                <input type="hidden" name="command" value="app:run-trading-pipeline">
                <input type="hidden" name="skip_instruments" value="1">
                <button type="submit" 
                        class="px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider text-white bg-rose-600 hover:bg-rose-700 shadow-md hover:shadow-lg transition-all transform active:scale-95 shrink-0 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" /></svg>
                    <span>Execute 1-Click Fix Now</span>
                </button>
            </form>
        </div>
    @else
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 shadow-xs flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-emerald-950">Pre-Market Pipeline Fully Synchronized</h4>
                    <p class="text-xs text-emerald-700">All 7 pre-market cron tasks completed successfully. Working days, benchmarks, OHLC, Earth levels, Index Open, and Strategy Panels are ready.</p>
                </div>
            </div>
            <span class="text-xs font-mono font-bold px-3 py-1 bg-emerald-100 text-emerald-800 rounded-lg border border-emerald-300">
                7 / 7 VERIFIED
            </span>
        </div>
    @endif

    <!-- SECTION 1: Pre-Market Cron Timeline (08:50 AM to 09:14 AM) -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                    <h2 class="text-lg font-bold text-slate-900 tracking-tight">Pre-Market Cron Jobs (Runs Before 09:15 AM)</h2>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Scheduled in sequence before trade start. If Windows Task Scheduler / Cron was missed in the morning, use the action buttons below to run individually or all at once.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-slate-500">Pipeline Status:</span>
                <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $preMarketAllReady ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                    {{ $preMarketSuccessCount }} of {{ $preMarketCriticalTotal }} Complete
                </span>
            </div>
        </div>

        <!-- Table of Pre-Market Tasks -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/75 border-b border-slate-200/80 text-[11px] font-bold uppercase tracking-wider text-slate-600">
                        <th class="py-3 px-4 w-28">Scheduled</th>
                        <th class="py-3 px-4">Task & Command</th>
                        <th class="py-3 px-4 w-32">Status</th>
                        <th class="py-3 px-4">Today Verified Data</th>
                        <th class="py-3 px-4 w-36 text-center">Log Snippet</th>
                        <th class="py-3 px-4 w-36 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @foreach($preMarketJobs as $job)
                        <tr class="hover:bg-slate-50/50 transition-colors {{ $job['status'] === 'danger' ? 'bg-rose-50/30' : '' }}">
                            
                            <!-- Scheduled Time -->
                            <td class="py-3.5 px-4 font-mono font-bold text-slate-800 whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-slate-100 border border-slate-200">
                                    <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ $job['time'] }}
                                </div>
                            </td>

                            <!-- Task & Command -->
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900 text-sm flex items-center gap-2">
                                    {{ $job['name'] }}
                                    @if($job['critical'])
                                        <span class="text-[9px] font-bold uppercase px-1.5 py-0.2 rounded bg-rose-100 text-rose-700 border border-rose-200">Critical</span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-slate-500 mt-0.5">{{ $job['description'] }}</div>
                                <code class="inline-block mt-1 text-[10px] font-mono text-indigo-700 bg-indigo-50/80 px-1.5 py-0.5 rounded border border-indigo-100">
                                    php artisan {{ $job['command'] }}
                                </code>
                            </td>

                            <!-- Status Badge -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @if($job['status'] === 'success')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        Verified
                                    </span>
                                @elseif($job['status'] === 'danger')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200 animate-pulse">
                                        <svg class="w-3.5 h-3.5 text-rose-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                        Missed / Missing
                                    </span>
                                @elseif($job['status'] === 'warning')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                        <svg class="w-3.5 h-3.5 text-amber-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                        Attention
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        Pending Time
                                    </span>
                                @endif
                            </td>

                            <!-- Today Verified Value -->
                            <td class="py-3.5 px-4">
                                <div class="font-mono text-xs font-semibold text-slate-800">
                                    {{ $job['verified_value'] }}
                                </div>
                                <div class="text-[11px] text-slate-400 mt-0.5">
                                    {{ $job['status_label'] }}
                                </div>
                            </td>

                            <!-- Log Modal Trigger -->
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <button type="button" 
                                        onclick="openLogViewer('{{ $job['log_file'] }}', '{{ $job['name'] }}')"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 hover:text-slate-900 border border-slate-200 transition-colors">
                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    View Log
                                </button>
                            </td>

                            <!-- Manual Run Action -->
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <form action="{{ route('cron.run') }}" method="POST" class="inline" onsubmit="return handleSingleRun(this, '{{ $job['name'] }}');">
                                    @csrf
                                    <input type="hidden" name="command" value="{{ $job['command'] }}">
                                    <button type="submit" 
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-2xs
                                            {{ $job['status'] === 'danger' 
                                                ? 'bg-rose-600 hover:bg-rose-700 text-white animate-pulse' 
                                                : 'bg-white hover:bg-slate-100 text-slate-700 border border-slate-300' }}">
                                        <svg class="w-3.5 h-3.5 {{ $job['status'] === 'danger' ? 'text-white' : 'text-slate-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>Run Now</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- SECTION 2: Intraday Market Operations & EOD Archival (09:15 - 15:40) -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    <h2 class="text-lg font-bold text-slate-900 tracking-tight">Intraday Live Stream & EOD Archival Jobs</h2>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Continuous real-time collection during market hours (09:15 - 15:35) and post-market 5m candle aggregation.
                </p>
            </div>
            
            <div class="flex items-center gap-2 text-xs text-slate-500">
                <span>Active Collector:</span>
                <span class="font-mono font-bold text-slate-800">ohlc:collect-1min</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/75 border-b border-slate-200/80 text-[11px] font-bold uppercase tracking-wider text-slate-600">
                        <th class="py-3 px-4 w-44">Schedule Frequency</th>
                        <th class="py-3 px-4">Task & Command</th>
                        <th class="py-3 px-4 w-32">Status</th>
                        <th class="py-3 px-4">Today Live Metrics</th>
                        <th class="py-3 px-4 w-36 text-center">Log Snippet</th>
                        <th class="py-3 px-4 w-36 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @foreach($intradayJobs as $job)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="py-3.5 px-4 font-mono font-medium text-slate-700 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-xs">
                                    {{ $job['schedule'] }}
                                </span>
                            </td>

                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900 text-sm">{{ $job['name'] }}</div>
                                <div class="text-[11px] text-slate-500 mt-0.5">{{ $job['description'] }}</div>
                                <code class="inline-block mt-1 text-[10px] font-mono text-indigo-700 bg-indigo-50/80 px-1.5 py-0.5 rounded border border-indigo-100">
                                    php artisan {{ $job['command'] }}
                                </code>
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @if($job['status'] === 'success')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Active
                                    </span>
                                @elseif($job['status'] === 'danger')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                        <svg class="w-3.5 h-3.5 text-rose-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                        Stalled / Error
                                    </span>
                                @elseif($job['status'] === 'warning')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                        <svg class="w-3.5 h-3.5 text-amber-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                        Check Delay
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        Scheduled
                                    </span>
                                @endif
                            </td>

                            <td class="py-3.5 px-4 font-mono text-xs font-semibold text-slate-800">
                                <div>{{ $job['verified_value'] }}</div>
                                <div class="text-[11px] text-slate-400 font-sans mt-0.5">{{ $job['status_label'] }}</div>
                            </td>

                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <button type="button" 
                                        onclick="openLogViewer('{{ $job['log_file'] }}', '{{ $job['name'] }}')"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 hover:text-slate-900 border border-slate-200 transition-colors">
                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    View Log
                                </button>
                            </td>

                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <form action="{{ route('cron.run') }}" method="POST" class="inline" onsubmit="return handleSingleRun(this, '{{ $job['name'] }}');">
                                    @csrf
                                    <input type="hidden" name="command" value="{{ $job['command'] }}">
                                    <button type="submit" 
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 transition-colors shadow-2xs">
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>Run Once</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- SECTION 3: Dependent Trading Pages Readiness Matrix -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 mb-4 border-b border-slate-100">
            <div>
                <h3 class="text-base font-bold text-slate-900 tracking-tight flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    Trading Pages & Application Features Readiness
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    Real-time sanity check of each trading cockpit based on current day database completeness.
                </p>
            </div>
            <span class="text-xs text-slate-400">Click any card to launch</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($pageReadiness as $page)
                <div class="p-4 rounded-xl border transition-all hover:shadow-md flex flex-col justify-between
                    {{ $page['is_ready'] ? 'bg-slate-50/60 border-slate-200/80 hover:border-indigo-300' : 'bg-rose-50/40 border-rose-200 hover:border-rose-300' }}">
                    
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded
                                {{ $page['is_ready'] ? 'bg-slate-200/80 text-slate-700' : 'bg-rose-100 text-rose-700' }}">
                                {{ $page['category'] }}
                            </span>

                            @if($page['is_ready'])
                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                                    <svg class="w-3 h-3 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    Ready
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-rose-700 bg-rose-100 px-2 py-0.5 rounded-full border border-rose-300 animate-pulse">
                                    <svg class="w-3 h-3 text-rose-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                    Impaired
                                </span>
                            @endif
                        </div>

                        <h4 class="text-sm font-bold text-slate-900 mt-2">
                            <a href="{{ route($page['route']) }}" class="hover:text-indigo-600 transition-colors inline-flex items-center gap-1">
                                {{ $page['name'] }}
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                        </h4>

                        <p class="text-xs mt-1 leading-relaxed {{ $page['is_ready'] ? 'text-slate-500' : 'text-rose-700 font-medium' }}">
                            {{ $page['reason'] }}
                        </p>
                    </div>

                    <div class="pt-3 mt-3 border-t {{ $page['is_ready'] ? 'border-slate-100' : 'border-rose-200/60' }} flex items-center justify-between gap-2">
                        <a href="{{ route($page['route']) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors inline-flex items-center gap-1">
                            <span>Open Page</span>
                            &rarr;
                        </a>

                        @if(!$page['is_ready'] && !empty($page['fix_command']))
                            <form action="{{ route('cron.run') }}" method="POST" class="inline" onsubmit="return handleSingleRun(this, 'Fix {{ $page['name'] }}');">
                                @csrf
                                <input type="hidden" name="command" value="{{ $page['fix_command'] }}">
                                <button type="submit" class="px-2.5 py-1 text-[11px] font-bold rounded-lg text-white bg-rose-600 hover:bg-rose-700 transition-colors">
                                    Run Fix
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

</div>

<!-- Log Viewer Modal -->
<div id="log-modal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6">
    <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-4xl max-h-[85vh] flex flex-col overflow-hidden animate-in fade-in duration-150">
        <!-- Modal Header -->
        <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-700 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div>
                    <h3 id="modal-log-title" class="font-bold text-sm text-slate-900">Log File Viewer</h3>
                    <p id="modal-log-file" class="text-xs font-mono text-slate-500">filename.log</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" onclick="refreshCurrentLog()" title="Refresh Log" class="p-2 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-200 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </button>
                <button type="button" onclick="closeLogViewer()" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <!-- Modal Body -->
        <div class="p-4 sm:p-5 flex-1 overflow-y-auto bg-slate-950 font-mono text-xs leading-relaxed text-slate-200">
            <div id="modal-log-updated" class="text-[11px] text-slate-500 mb-2 pb-2 border-b border-slate-800"></div>
            <pre id="modal-log-content" class="whitespace-pre-wrap break-all">Loading log content...</pre>
        </div>

        <!-- Modal Footer -->
        <div class="p-3 sm:p-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between text-xs text-slate-500">
            <span>Showing latest lines from <code id="modal-footer-path" class="text-indigo-600">storage/logs/</code></span>
            <button type="button" onclick="closeLogViewer()" class="px-4 py-1.5 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-800 font-semibold transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Execution Progress Toast -->
<div id="action-toast" class="fixed bottom-6 right-6 z-50 hidden max-w-md w-full bg-slate-900 text-white rounded-2xl shadow-2xl p-4 border border-slate-700 animate-in slide-in-from-bottom duration-200">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-indigo-400 animate-spin mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <div class="flex-1">
            <h4 id="action-toast-title" class="font-bold text-sm text-white">Running Task...</h4>
            <p id="action-toast-msg" class="text-xs text-slate-300 mt-0.5">Please wait while the command executes...</p>
        </div>
    </div>
</div>

<script>
    let activeLogFile = null;
    let autoRefreshTimer = null;
    let autoRefreshCountdown = 30;
    let isAutoRefreshActive = true;

    // Real-time IST Clock display
    setInterval(() => {
        const now = new Date();
        const options = { timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
        const el = document.getElementById('clock-display');
        if (el) {
            el.textContent = now.toLocaleTimeString('en-US', options);
        }
    }, 1000);

    // Auto-refresh countdown
    function startAutoRefresh() {
        if (autoRefreshTimer) clearInterval(autoRefreshTimer);
        autoRefreshCountdown = 30;

        autoRefreshTimer = setInterval(() => {
            if (!isAutoRefreshActive) return;
            autoRefreshCountdown--;

            const label = document.getElementById('auto-refresh-label');
            if (label) {
                label.textContent = `Auto (${autoRefreshCountdown}s)`;
            }

            if (autoRefreshCountdown <= 0) {
                window.location.reload();
            }
        }, 1000);
    }

    function toggleAutoRefresh() {
        isAutoRefreshActive = !isAutoRefreshActive;
        const btn = document.getElementById('btn-auto-refresh');
        const label = document.getElementById('auto-refresh-label');

        if (isAutoRefreshActive) {
            btn.className = "inline-flex items-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 transition-colors";
            startAutoRefresh();
        } else {
            btn.className = "inline-flex items-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold text-slate-600 bg-slate-100 border border-slate-200 hover:bg-slate-200 transition-colors";
            label.textContent = "Auto (Paused)";
            if (autoRefreshTimer) clearInterval(autoRefreshTimer);
        }
    }

    // Single Task execution via AJAX
    function handleSingleRun(form, taskName) {
        showToast(`Executing ${taskName}...`, 'Running artisan command in background...');
        
        const formData = new FormData(form);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        fetch("{{ route('cron.run') }}", {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken || '',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(`Success: ${taskName}`, data.message, true);
                setTimeout(() => window.location.reload(), 1200);
            } else {
                showToast(`Failed: ${taskName}`, data.message || 'Error occurred.', false);
            }
        })
        .catch(err => {
            // Fallback to normal form submit if fetch fails
            form.submit();
        });

        return false;
    }

    // Pipeline submission
    function handlePipelineSubmit(form) {
        if (!confirm('Run complete Pre-Market Trading Pipeline now? This will execute working days, expiries, previous OHLC, daily trend, index open, and strategies in sequence.')) {
            return false;
        }

        showToast('Running Pre-Market Pipeline...', 'Executing all pre-market tasks in sequence. This may take 5-15 seconds...');
        return true;
    }

    function showToast(title, msg, isSuccess = null) {
        const toast = document.getElementById('action-toast');
        const titleEl = document.getElementById('action-toast-title');
        const msgEl = document.getElementById('action-toast-msg');

        titleEl.textContent = title;
        msgEl.textContent = msg;
        toast.classList.remove('hidden');

        if (isSuccess !== null) {
            setTimeout(() => {
                toast.classList.add('hidden');
            }, 5000);
        }
    }

    // Log Viewer Modal
    function openLogViewer(filename, taskName) {
        activeLogFile = filename;
        document.getElementById('modal-log-title').textContent = `${taskName} — Log Viewer`;
        document.getElementById('modal-log-file').textContent = `storage/logs/${filename}`;
        document.getElementById('modal-footer-path').textContent = `storage/logs/${filename}`;
        document.getElementById('modal-log-content').textContent = 'Fetching latest log lines...';
        document.getElementById('modal-log-updated').textContent = '';
        document.getElementById('log-modal').classList.remove('hidden');

        fetchLogContent(filename);
    }

    function refreshCurrentLog() {
        if (activeLogFile) {
            fetchLogContent(activeLogFile);
        }
    }

    function fetchLogContent(filename) {
        fetch(`{{ route('api.cron.log') }}?file=${encodeURIComponent(filename)}`)
            .then(res => res.json())
            .then(data => {
                const contentEl = document.getElementById('modal-log-content');
                const updatedEl = document.getElementById('modal-log-updated');

                if (data.error) {
                    contentEl.textContent = data.error;
                } else {
                    contentEl.textContent = data.content || '(Log is empty)';
                    updatedEl.textContent = data.updated ? `Last modified: ${data.updated}` : '';
                }
            })
            .catch(err => {
                document.getElementById('modal-log-content').textContent = 'Failed to load log file: ' + err;
            });
    }

    function closeLogViewer() {
        document.getElementById('log-modal').classList.add('hidden');
        activeLogFile = null;
    }

    // Keyboard escape to close modal
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeLogViewer();
    });

    // Start auto-refresh on load
    startAutoRefresh();
</script>
@endsection
