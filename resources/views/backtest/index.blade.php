@extends('layouts.app')

@section('title')
    Straddle and Strangle
@endsection

@section('content')
    <div class="min-h-screen bg-gray-950 text-gray-100 p-6">

        {{-- Header --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-white">📊 Backtest Days</h1>
                <p class="text-gray-500 text-sm mt-1">Each row is one trading day · click to see its 4 legs</p>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-lg px-4 py-3">
                <p class="text-xs text-gray-500 mb-1 uppercase tracking-wider">Run a new backtest</p>
                <code class="text-xs text-indigo-300 font-mono">
                    php artisan backtest:strangle {strategy} --from=YYYY-MM-DD --to=YYYY-MM-DD
                </code>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('backtest.index') }}"
            class="bg-gray-900 border border-gray-800 rounded-xl p-5 mb-6">

            <div class="flex flex-wrap items-end gap-4">

                {{-- Strategy — Primary filter --}}
                <div class="flex flex-col gap-1 min-w-[180px]">
                    <label class="text-xs text-gray-400 uppercase tracking-wider font-medium">
                        Strategy
                        <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <select name="strategy"
                            class="w-full bg-gray-800 border
                                   {{ request('strategy') ? 'border-indigo-500' : 'border-gray-600' }}
                                   rounded-lg px-3 py-2 text-sm text-white
                                   focus:outline-none focus:border-indigo-400 appearance-none pr-8">
                            <option value="">— Select Strategy —</option>
                            @foreach($availableStrategies as $s)
                                <option value="{{ $s }}" @selected(request('strategy') === $s)>
                                    {{ ucwords(str_replace('_', ' ', $s)) }}
                                </option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-2 flex items-center">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- Symbol --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-400 uppercase tracking-wider">Symbol</label>
                    <input type="text" name="symbol" value="{{ request('symbol') }}"
                        placeholder="NIFTY"
                        class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm
                              text-white focus:outline-none focus:border-indigo-500 w-28">
                </div>

                {{-- Outcome --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-400 uppercase tracking-wider">Day Outcome</label>
                    <select name="outcome"
                        class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm
                               text-white focus:outline-none focus:border-indigo-500 w-36">
                        <option value="">All Outcomes</option>
                        <option value="profit" @selected(request('outcome') === 'profit')>✓ Profit Days</option>
                        <option value="loss" @selected(request('outcome') === 'loss')>✗ Loss Days</option>
                    </select>
                </div>

                {{-- Day P&L Filter --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-400 uppercase tracking-wider">Day P&L</label>
                    <div class="flex items-center gap-1">
                        <select name="pnl_dir"
                            class="bg-gray-800 border border-gray-700 rounded-lg px-2 py-2 text-sm
                                   text-white focus:outline-none focus:border-indigo-500 w-20">
                            <option value="">Any</option>
                            <option value="gte" @selected(request('pnl_dir') === 'gte')>≥</option>
                            <option value="lte" @selected(request('pnl_dir') === 'lte')>≤</option>
                        </select>
                        <input type="number" name="pnl_value"
                            value="{{ request('pnl_value') }}"
                            placeholder="e.g. 2000"
                            class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm
                                  text-white focus:outline-none focus:border-indigo-500 w-32">
                    </div>
                </div>

                {{-- Peak P&L Filter --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-400 uppercase tracking-wider">Peak P&L</label>
                    <select name="peak_filter"
                        class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm
                               text-white focus:outline-none focus:border-indigo-500 w-52">
                        <option value="">All</option>
                        <optgroup label="─── Peak Profit ───">
                            <option value="has_peak_profit"
                                @selected(request('peak_filter') === 'has_peak_profit')>
                                Has Peak Profit (> ₹0)
                            </option>
                            <option value="no_peak_profit"
                                @selected(request('peak_filter') === 'no_peak_profit')>
                                No Peak Profit (null / ₹0)
                            </option>
                            <option value="peak_profit_reversed"
                                @selected(request('peak_filter') === 'peak_profit_reversed')>
                                Peak Profit → Reversed to Loss
                            </option>
                        </optgroup>
                        <optgroup label="─── Peak Loss ───">
                            <option value="has_peak_loss"
                                @selected(request('peak_filter') === 'has_peak_loss')>
                                Has Peak Loss (&lt; ₹0)
                            </option>
                            <option value="no_peak_loss"
                                @selected(request('peak_filter') === 'no_peak_loss')>
                                No Peak Loss (null / ₹0)
                            </option>
                            <option value="peak_loss_recovered"
                                @selected(request('peak_filter') === 'peak_loss_recovered')>
                                Peak Loss → Recovered to Profit
                            </option>
                        </optgroup>
                    </select>
                </div>

                {{-- Date Range --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-400 uppercase tracking-wider">From</label>
                    <input type="date" name="from" value="{{ request('from') }}"
                        class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm
                              text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-400 uppercase tracking-wider">To</label>
                    <input type="date" name="to" value="{{ request('to') }}"
                        class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm
                              text-white focus:outline-none focus:border-indigo-500">
                </div>

                {{-- Skip Expiry Days --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-400 uppercase tracking-wider">Expiry Days</label>
                    <label class="inline-flex items-center gap-2 cursor-pointer mt-1">
                        <input type="hidden" name="skip_expiry" value="0">
                        <input type="checkbox" name="skip_expiry" value="1"
                            @checked(request('skip_expiry') == '1')
                            class="w-4 h-4 rounded bg-gray-800 border-gray-600
                      text-indigo-500 focus:ring-indigo-500 cursor-pointer">
                        <span class="text-sm text-gray-300">Skip Expiry Days</span>
                    </label>
                </div>

                {{-- Buttons --}}
                <div class="flex gap-2 pb-0.5">
                    <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm px-4 py-2
                               rounded-lg transition-colors font-medium">
                        Apply
                    </button>
                    <a href="{{ route('backtest.index') }}"
                        class="bg-gray-700 hover:bg-gray-600 text-white text-sm px-4 py-2
                          rounded-lg transition-colors">
                        Reset
                    </a>
                </div>

            </div>

            {{-- Active filter chips --}}
            @if(request()->hasAny(['strategy','symbol','outcome','pnl_dir','peak_filter','from','to', 'skip_expiry']))
                <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-800">
                    <span class="text-xs text-gray-500 self-center">Active filters:</span>

                    @if(request('strategy'))
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-indigo-900/60
                             text-indigo-300 rounded-full text-xs">
                    Strategy: {{ ucwords(str_replace('_', ' ', request('strategy'))) }}
                </span>
                    @endif
                    @if(request('symbol'))
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-800
                             text-gray-300 rounded-full text-xs">
                    Symbol: {{ request('symbol') }}
                </span>
                    @endif
                    @if(request('outcome'))
                        <span class="inline-flex items-center gap-1 px-2 py-0.5
                             {{ request('outcome') === 'profit' ? 'bg-emerald-900/60 text-emerald-300' : 'bg-red-900/60 text-red-300' }}
                             rounded-full text-xs">
                    {{ ucfirst(request('outcome')) }} Days
                </span>
                    @endif
                    @if(request('pnl_dir') && request('pnl_value') !== null)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-yellow-900/60
                             text-yellow-300 rounded-full text-xs">
                    P&L {{ request('pnl_dir') === 'gte' ? '≥' : '≤' }} ₹{{ number_format(request('pnl_value'), 0) }}
                </span>
                    @endif
                    @if(request('peak_filter'))
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-purple-900/60
                             text-purple-300 rounded-full text-xs">
                    Peak: {{ ucwords(str_replace('_', ' ', request('peak_filter'))) }}
                </span>
                    @endif
                    @if(request('from') || request('to'))
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-800
                             text-gray-300 rounded-full text-xs">
                    {{ request('from') ?: '—' }} → {{ request('to') ?: '—' }}
                </span>
                    @endif

                    @if(request('skip_expiry') == '1')
                        @php
                            $totalExpiriesInRange = collect($expiryDates)->filter(function($v, $date) {
                                $from = request('from');
                                $to   = request('to');
                                return (!$from || $date >= $from)
                                    && (!$to   || $date <= $to);
                            })->count();
                        @endphp
                        @if($totalExpiriesInRange > 0)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-900/60
                 text-amber-300 rounded-full text-xs">
                                <span>⚡</span>
                                <span>
                {{ $totalExpiriesInRange }} expiry
                {{ \Illuminate\Support\Str::plural('day', $totalExpiriesInRange) }} hidden from results
            </span>
                                <a href="{{ request()->fullUrlWithQuery(['skip_expiry' => '0']) }}"
                                    class="underline hover:text-amber-300 ml-1">
                                    Show them
                                </a>
                            </span>
                        @endif
                    @endif
                    @if(request('skip_expiry') == '1')
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-900/60
                 text-amber-300 rounded-full text-xs">
            ⚡ Expiry Days Hidden
             </span>
                    @endif
                </div>
            @endif


        </form>

        {{-- No strategy selected — empty state --}}
        @if(!request('strategy'))
            <div class="bg-gray-900 rounded-xl border border-gray-800 px-6 py-24 text-center">
                <span class="text-5xl block mb-4">🎯</span>
                <p class="text-gray-300 text-lg font-semibold mb-2">Select a Strategy to Begin</p>
                <p class="text-gray-600 text-sm mb-6">
                    Choose a strategy from the filter above to view backtest results.
                </p>
                <div class="flex flex-wrap justify-center gap-2">
                    @foreach($availableStrategies as $s)
                        <a href="{{ route('backtest.index', ['strategy' => $s]) }}"
                            class="px-4 py-2 bg-indigo-700 hover:bg-indigo-600 text-white
                              text-sm rounded-lg transition-colors font-medium">
                            {{ ucwords(str_replace('_', ' ', $s)) }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Strategy selected but no results --}}
        @elseif($days->isEmpty())
            <div class="bg-gray-900 rounded-xl border border-gray-800 px-6 py-20 text-center">
                <span class="text-5xl block mb-4">📭</span>
                <p class="text-gray-400 text-lg font-medium mb-2">No results found</p>
                <p class="text-gray-600 text-sm">Try adjusting your filters.</p>
            </div>

        @else

            {{-- Summary Stats --}}
            @if($statsQuery)
                @php
                    $totalDays = $statsQuery->profit_days + $statsQuery->loss_days;
                    $winRate   = $totalDays > 0 ? round($statsQuery->profit_days / $totalDays * 100, 1) : 0;
                @endphp
                <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-9 gap-3 mb-6">

                    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4 col-span-2 md:col-span-1">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Total P&L</p>
                        <p class="text-2xl font-bold
                {{ $statsQuery->total_pnl >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                            {{ $statsQuery->total_pnl >= 0 ? '+' : '' }}₹{{ number_format($statsQuery->total_pnl, 0) }}
                        </p>
                        <p class="text-xs text-gray-600 mt-1">{{ $statsQuery->total_days }} days</p>
                    </div>

                    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Profit Days</p>
                        <p class="text-2xl font-bold text-emerald-400">{{ $statsQuery->profit_days }}</p>
                    </div>

                    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Loss Days</p>
                        <p class="text-2xl font-bold text-red-400">{{ $statsQuery->loss_days }}</p>
                    </div>

                    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Win Rate</p>
                        <p class="text-2xl font-bold
                {{ $winRate >= 60 ? 'text-emerald-400' : ($winRate >= 45 ? 'text-yellow-400' : 'text-red-400') }}">
                            {{ $winRate }}%
                        </p>
                    </div>

                    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Best Day</p>
                        <p class="text-xl font-bold text-emerald-400">
                            +₹{{ number_format($statsQuery->best_day ?? 0, 0) }}
                        </p>
                    </div>

                    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Worst Day</p>
                        <p class="text-xl font-bold text-red-400">
                            ₹{{ number_format($statsQuery->worst_day ?? 0, 0) }}
                        </p>
                    </div>

                    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Avg Profit Wait</p>
                        <p class="text-xl font-bold text-emerald-400">
                            @if($statsQuery->avg_profit_min)
                                @php $m = round($statsQuery->avg_profit_min); @endphp
                                {{ $m >= 60 ? intdiv($m,60).'h '.($m%60).'m' : $m.' min' }}
                            @else
                                —
                            @endif
                        </p>
                    </div>

                    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Avg Loss Wait</p>
                        <p class="text-xl font-bold text-red-400">
                            @if($statsQuery->avg_loss_min)
                                @php $m = round($statsQuery->avg_loss_min); @endphp
                                {{ $m >= 60 ? intdiv($m,60).'h '.($m%60).'m' : $m.' min' }}
                            @else
                                —
                            @endif
                        </p>
                    </div>

                    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Avg Peak Profit</p>
                        <p class="text-xl font-bold text-emerald-300">
                            +₹{{ number_format($statsQuery->avg_max_profit ?? 0, 0) }}
                        </p>
                    </div>

                </div>
            @endif



            {{-- Days Table --}}
            <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                        <tr class="bg-gray-800/80 text-gray-400 uppercase text-xs tracking-wider">
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Symbol</th>
                            <th class="px-4 py-3 text-right">Index @ Entry</th>
                            <th class="px-4 py-3 text-center">Strikes</th>
                            <th class="px-4 py-3 text-right">Entry</th>
                            <th class="px-4 py-3 text-right">Exit</th>
                            <th class="px-4 py-3 text-right">Duration</th>
                            <th class="px-4 py-3 text-right">Day P&L</th>
                            <th class="px-4 py-3 text-right">
                                <span class="text-emerald-400">Peak +</span>
                            </th>
                            <th class="px-4 py-3 text-right">
                                <span class="text-red-400">Peak −</span>
                            </th>
                            <th class="px-4 py-3 text-center">Outcome</th>
                            <th class="px-4 py-3 text-center">Params</th>
                            <th class="px-4 py-3 text-center">Action</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800">
                        @foreach($days as $day)
                            @php
                                $isProfit = $day->day_outcome === 'profit';
                                $isExpiry = isset($expiryDates[\Carbon\Carbon::parse($day->trade_date)->toDateString()]);

                                // Only compute ATM strikes for strategies that use offset
                                $atm   = null;
                                $upper = null;
                                $lower = null;
                                if ($day->strategy !== 'first_candle_breakout' && $day->index_price_at_entry && $day->strike_offset) {
                                    $atm   = (int)(round($day->index_price_at_entry / 100) * 100);
                                    $upper = $atm + $day->strike_offset;
                                    $lower = $atm - $day->strike_offset;
                                }

                                $dur    = $day->trade_time_duration;
                                $durFmt = $dur
                                    ? (intdiv($dur, 60) > 0 ? intdiv($dur, 60).'h ' : '').($dur % 60).'m'
                                    : '—';

                                $hasPeakProfit  = !is_null($day->day_max_profit) && $day->day_max_profit > 0;
                                $hasPeakLoss    = !is_null($day->day_max_loss)   && $day->day_max_loss   < 0;
                                $reversedToLoss = $hasPeakProfit && $day->day_outcome === 'loss';
                                $recoveredToWin = $hasPeakLoss   && $day->day_outcome === 'profit';
                            @endphp
                            <tr class="hover:bg-gray-800/40 transition-colors cursor-pointer  {{ $isExpiry
               ? 'bg-amber-950/20 hover:bg-amber-900/30 border-l-2 border-l-amber-500'
               : 'hover:bg-gray-800/40 border-l-2 border-l-transparent' }}"

                                {{--                                onclick="window.location='{{ route('backtest.trades', ['group_id' => $day->day_group_id]) }}'"--}}
                            >

                                {{-- Date --}}
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <p class="font-semibold text-white">
                                        {{ \Carbon\Carbon::parse($day->trade_date)->format('d M Y') }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ \Carbon\Carbon::parse($day->trade_date)->format('D') }}
                                    </p>
                                </td>

                                {{-- Symbol --}}
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-white">{{ $day->underlying_symbol }}</p>
                                    <p class="text-xs text-gray-500">{{ $day->exchange }}</p>
                                </td>

                                {{-- Index --}}
                                <td class="px-4 py-3 text-right font-mono text-cyan-300">
                                    {{ $day->index_price_at_entry
                                        ? number_format($day->index_price_at_entry, 2)
                                        : '—' }}
                                </td>

                                {{-- Strikes --}}
                                <td class="px-4 py-3 text-center">
                                    @if($day->strategy === 'first_candle_breakout')
                                        {{-- Single strike with direction indicator --}}
                                        <div class="flex items-center justify-center gap-1 text-xs font-mono">
            <span class="px-1.5 py-0.5 rounded
                {{ $day->day_outcome === 'profit' ? 'bg-emerald-900/50 text-emerald-300' : 'bg-red-900/50 text-red-300' }}">
                {{ number_format($day->strike) }}
            </span>
                                            {{-- Show CE or PE badge --}}
                                            <span class="px-1.5 py-0.5 rounded text-xs font-bold
                {{ $day->instrument_type === 'CE' ? 'bg-blue-900/50 text-blue-300' : 'bg-orange-900/50 text-orange-300' }}">
                {{ $day->instrument_type }}
            </span>
                                        </div>
                                    @elseif($lower && $upper)
                                        {{-- Original 4-leg display --}}
                                        <div class="flex items-center justify-center gap-1 text-xs font-mono">
            <span class="px-1.5 py-0.5 bg-indigo-900/50 text-indigo-300 rounded">
                {{ number_format($lower) }}
            </span>
                                            <span class="text-gray-600">&</span>
                                            <span class="px-1.5 py-0.5 bg-indigo-900/50 text-indigo-300 rounded">
                {{ number_format($upper) }}
            </span>
                                        </div>
                                    @else
                                        <span class="text-gray-600 text-xs">—</span>
                                    @endif
                                </td>

                                {{-- Entry Time --}}
                                <td class="px-4 py-3 text-right font-mono text-gray-300 text-xs">
                                    {{ $day->entry_time
                                        ? \Carbon\Carbon::parse($day->entry_time)->format('H:i')
                                        : '—' }}
                                </td>

                                {{-- Exit Time --}}
                                <td class="px-4 py-3 text-right font-mono text-gray-300 text-xs">
                                    {{ $day->exit_time
                                        ? \Carbon\Carbon::parse($day->exit_time)->format('H:i')
                                        : '—' }}
                                </td>

                                {{-- Duration --}}
                                <td class="px-4 py-3 text-right font-mono text-yellow-400 text-xs">
                                    {{ $durFmt }}
                                </td>

                                {{-- Day P&L --}}
                                <td class="px-4 py-3 text-right">
                            <span class="font-bold font-mono
                                {{ $isProfit ? 'text-emerald-400' : 'text-red-400' }}">
                                {{ $day->day_total_pnl >= 0 ? '+' : '' }}₹{{ number_format($day->day_total_pnl, 0) }}
                            </span>
                                </td>

                                {{-- Peak Profit --}}
                                <td class="px-4 py-3 text-right text-xs">
                                    @if($hasPeakProfit)
                                        <span class="font-mono text-emerald-300 font-semibold">
                                    +₹{{ number_format($day->day_max_profit, 0) }}
                                </span>
                                        <span class="block text-gray-500 font-mono">
                                    {{ $day->day_max_profit_time
                                        ? \Carbon\Carbon::parse($day->day_max_profit_time)->format('H:i')
                                        : '' }}
                                </span>
                                        @if($reversedToLoss)
                                            <span class="block text-yellow-500 text-xs">↓ reversed</span>
                                        @endif
                                    @else
                                        <span class="text-gray-700">—</span>
                                    @endif
                                </td>

                                {{-- Peak Loss --}}
                                <td class="px-4 py-3 text-right text-xs">
                                    @if($hasPeakLoss)
                                        <span class="font-mono text-red-300 font-semibold">
                                    ₹{{ number_format($day->day_max_loss, 0) }}
                                </span>
                                        <span class="block text-gray-500 font-mono">
                                    {{ $day->day_max_loss_time
                                        ? \Carbon\Carbon::parse($day->day_max_loss_time)->format('H:i')
                                        : '' }}
                                </span>
                                        @if($recoveredToWin)
                                            <span class="block text-yellow-500 text-xs">↑ recovered</span>
                                        @endif
                                    @else
                                        <span class="text-gray-700">—</span>
                                    @endif
                                </td>

                                {{-- Outcome --}}
                                <td class="px-4 py-3 text-center">
                                    @if($isProfit)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full
                                             text-xs font-semibold bg-emerald-900/70 text-emerald-300">
                                    ✓ Profit
                                </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full
                                             text-xs font-semibold bg-red-900/70 text-red-300">
                                    ✗ Loss
                                </span>
                                    @endif
                                </td>

                                {{-- Params --}}
                                <td class="px-4 py-3 text-center">
                                    <div class="flex flex-wrap justify-center gap-1">
                                <span class="px-1.5 py-0.5 bg-gray-800 rounded text-xs
                                             font-mono text-indigo-300">
                                    ±{{ $day->strike_offset }}
                                </span>
                                        <span class="px-1.5 py-0.5 bg-emerald-900/40 rounded text-xs
                                             font-mono text-emerald-400">
                                    T:{{ number_format($day->target, 0) }}
                                </span>
                                        <span class="px-1.5 py-0.5 bg-red-900/40 rounded text-xs
                                             font-mono text-red-400">
                                    SL:{{ number_format($day->stoploss, 0) }}
                                </span>
                                    </div>
                                </td>

                                {{-- Action --}}
                                <td class="px-4 py-3 text-center" onclick="event.stopPropagation()">
                                    <a href="{{ route('backtest.trades', ['group_id' => $day->day_group_id]) }}"
                                        class="inline-flex items-center gap-1 bg-indigo-600 hover:bg-indigo-500
                                      text-white text-xs font-medium px-3 py-1.5 rounded-lg
                                      transition-colors whitespace-nowrap">
                                        4 Legs
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </a>
                                </td>

                            </tr>
                        @endforeach
                        </tbody>

                        {{-- Footer --}}
                        <tfoot>
                        <tr class="bg-gray-800/60 border-t-2 border-gray-700 text-xs font-semibold">
                            <td colspan="7" class="px-4 py-2.5 text-gray-500 uppercase tracking-wider">
                                Page total ({{ $days->count() }} days)
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono font-bold
                            {{ $days->sum('day_total_pnl') >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                {{ $days->sum('day_total_pnl') >= 0 ? '+' : '' }}₹{{ number_format($days->sum('day_total_pnl'), 0) }}
                            </td>
                            <td colspan="5"></td>
                        </tr>
                        </tfoot>

                    </table>
                </div>
            </div>

            {{-- Pagination --}}
            <div class="mt-4 flex items-center justify-between">
                <p class="text-xs text-gray-500">
                    Showing {{ $days->firstItem() }}–{{ $days->lastItem() }}
                    of {{ $days->total() }} trading days
                </p>
                {{ $days->links() }}
            </div>

        @endif

    </div>
@endsection
