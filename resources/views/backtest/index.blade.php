@extends('layouts.app')

@section('title')
    Straddle and Strangle
@endsection

@section('content')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
        {{-- Filters Toggle --}}
        <div x-data="{ open: false }" class="mb-6">
            <button @click="open = !open"
                class="w-full flex items-center justify-between bg-gray-900 border border-gray-800 rounded-xl px-5 py-3 text-sm font-semibold text-gray-300 hover:bg-gray-800 transition-colors">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                    </svg>
                    <span>Filters</span>
                    {{-- Show active filter count as a badge when collapsed --}}
                    @php $activeCount = collect(['strategy','symbol','outcome','pnldir','peakfilter','from','to','skipexpiry','entry_time'])->filter(fn($k) => request()->filled($k))->count(); @endphp
                    @if($activeCount > 0)
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-600 text-white text-xs font-bold">
                    {{ $activeCount }}
                </span>
                    @endif
                </div>
                <svg class="w-4 h-4 text-gray-500 transition-transform duration-200" :class="open ? 'rotate-180' : ''"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="open" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-2"
                class="mt-1">
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

                        {{-- Entry Time From --}}
                        @if (!empty($availableEntryTimes))
                        <div class="flex flex-col gap-1">
                            <label class="text-xs text-gray-400 uppercase tracking-wider font-medium">Entry Time</label>
                            <div class="relative">
                                <select name="entry_time"
                                    class="w-full bg-gray-800 border {{ request('entry_time') ? 'border-indigo-500' : 'border-gray-600' }}
                   rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-400 appearance-none pr-8">
                                    <option value="">All Times</option>
                                    @foreach($availableEntryTimes as $rawTime => $label)
                                        <option  value="{{ $rawTime }}" {{ request('entry_time') == $rawTime ? 'selected' : '' }}>
                                            {{ $label }}
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
                        @endif
                        {{-- Exit Time --}}
                        <div class="flex flex-col gap-1">
                            <label class="text-xs text-gray-400 uppercase tracking-wider font-medium">Exit Time</label>
                            <div class="relative">
                                <select name="exit_time"
                                    class="w-full bg-gray-800 border {{ request('exit_time') ? 'border-indigo-500' : 'border-gray-600' }}
                   rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-400 appearance-none pr-8">
                                    <option value="">Any / Open</option>
                                    @foreach($availableExitTimes as $t)
                                        <option value="{{ $t }}" {{ request('exit_time') === $t ? 'selected' : '' }}>
                                            {{ \Carbon\Carbon::createFromFormat('H:i', $t)->format('h:i A') }}
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

                        <div class="flex flex-col gap-1">
                            <label class="text-xs text-gray-400 uppercase tracking-wider">Prev Day Range</label>
                            <div class="flex items-center gap-1">
                                <select name="rangedir" class="bg-gray-800 border border-gray-700 rounded-lg px-2 py-2 text-sm text-white w-20">
                                    <option value="">Any</option>
                                    <option value="gte" {{ request('rangedir') === 'gte' ? 'selected' : '' }}>&gt;=</option>
                                    <option value="lte" {{ request('rangedir') === 'lte' ? 'selected' : '' }}>&lt;=</option>
                                </select>
                                <input type="number" step="0.01" name="rangevalue" value="{{ request('rangevalue') }}"
                                    placeholder="e.g. 250"
                                    class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white w-32">
                            </div>
                        </div>

                        <div class="flex flex-col gap-1">
                            <label class="text-xs text-gray-400 uppercase tracking-wider">Gap % / Range</label>
                            <div class="flex items-center gap-1">
                                <select name="gap_pct_dir" class="bg-gray-800 border border-gray-700 rounded-lg px-2 py-2 text-sm text-white w-20">
                                    <option value="">Any</option>
                                    <option value="gte" {{ request('gap_pct_dir') === 'gte' ? 'selected' : '' }}>&gt;=</option>
                                    <option value="lte" {{ request('gap_pct_dir') === 'lte' ? 'selected' : '' }}>&lt;=</option>
                                </select>
                                <input type="number" step="0.01" name="gap_pct_value" value="{{ request('gap_pct_value') }}"
                                    placeholder="e.g. 60"
                                    class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white w-32">
                            </div>
                        </div>

                        {{-- Gap Filter --}}
                        <div class="flex flex-col gap-1">
                            <label class="text-xs text-gray-400 uppercase tracking-wider">Gap Filter</label>
                            <div class="flex items-center gap-1">
                                <select name="gap_dir" class="bg-gray-800 border border-gray-700 rounded-lg px-2 py-2 text-sm text-white focus:outline-none focus:border-indigo-500 w-20">
                                    <option value="Any">Any</option>
                                    <option value="gte" {{ request('gap_dir') == 'gte' ? 'selected' : '' }}>≥</option>
                                    <option value="lte" {{ request('gap_dir') == 'lte' ? 'selected' : '' }}>&le;</option>
                                </select>
                                <input type="number" name="gap_value" value="{{ request('gap_value') }}" placeholder="e.g. 100"
                                    class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500 w-32">
                            </div>
                        </div>

                        {{-- Peak P&L Filter --}}
                        {{-- Peak PL Filter --}}
                        <div class="flex flex-col gap-1">
                            <label class="text-xs text-gray-400 uppercase tracking-wider">Peak P&L Filter</label>
                            <div class="flex items-center gap-1 flex-wrap">

                                {{-- Dropdown: what kind of peak --}}
                                <select name="peak_filter"
                                    class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500 w-52">
                                    <option value="">All</option>
                                    <optgroup label="Peak Profit">
                                        <option value="has_peak_profit" {{ request('peak_filter') === 'has_peak_profit'      ? 'selected' : '' }}>Has Peak Profit (> 0)</option>
                                        <option value="no_peak_profit" {{ request('peak_filter') === 'no_peak_profit'       ? 'selected' : '' }}>No Peak Profit (null/0)</option>
                                        <option value="peak_profit_reversed" {{ request('peak_filter') === 'peak_profit_reversed' ? 'selected' : '' }}>Peak Profit → Reversed to Loss</option>
                                    </optgroup>
                                    <optgroup label="Peak Loss">
                                        <option value="has_peak_loss" {{ request('peak_filter') === 'has_peak_loss'        ? 'selected' : '' }}>Has Peak Loss (< 0)</option>
                                        <option value="no_peak_loss" {{ request('peak_filter') === 'no_peak_loss'         ? 'selected' : '' }}>No Peak Loss (null/0)</option>
                                        <option value="peak_loss_recovered" {{ request('peak_filter') === 'peak_loss_recovered'  ? 'selected' : '' }}>Peak Loss → Recovered to Profit</option>
                                    </optgroup>
                                </select>

                                {{-- Operator: >= or <= --}}
                                <select name="peak_dir"
                                    class="bg-gray-800 border border-gray-700 rounded-lg px-2 py-2 text-sm text-white focus:outline-none focus:border-indigo-500 w-20">
                                    <option value="">Any</option>
                                    <option value="gte" {{ request('peak_dir') === 'gte' ? 'selected' : '' }}>&gt;=</option>
                                    <option value="lte" {{ request('peak_dir') === 'lte' ? 'selected' : '' }}>&lt;=</option>
                                </select>

                                {{-- Value textbox --}}
                                <input type="number"
                                    name="peak_value"
                                    value="{{ request('peak_value') }}"
                                    placeholder="e.g. 2000"
                                    class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500 w-28"/>

                            </div>
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

                        {{-- Skip Days — toggle buttons --}}
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs text-gray-400 uppercase tracking-wider">Skip Days</label>
                            <div class="flex items-center gap-1.5">
                                @foreach(['Monday' => 'M', 'Tuesday' => 'T', 'Wednesday' => 'W', 'Thursday' => 'T', 'Friday' => 'F'] as $day => $letter)
                                    @php $isActive = in_array($day, (array) request('skip_days', [])); @endphp
                                    <label
                                        title="{{ $day }}"
                                        class="relative cursor-pointer select-none">
                                        <input
                                            type="checkbox"
                                            name="skip_days[]"
                                            value="{{ $day }}"
                                            {{ $isActive ? 'checked' : '' }}
                                            class="peer sr-only"
                                            onchange="this.closest('form').submit()">
                                        <span class="flex items-center justify-center w-8 h-8 rounded-lg text-xs font-bold
                    border transition-all duration-150
                    peer-checked:bg-red-500/20 peer-checked:border-red-500 peer-checked:text-red-400
                    peer-not-checked:bg-gray-800 peer-not-checked:border-gray-700 peer-not-checked:text-gray-400
                    hover:border-gray-500 hover:text-gray-200">
                    {{ $letter }}
                </span>
                                    </label>
                                @endforeach
                            </div>
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
                    @if(request()->hasAny(['strategy','symbol','outcome','pnl_dir','peak_filter','from','to', 'skip_expiry', 'entry_time', 'exit_time', 'gap_dir']))
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

                            @if($entryTime = request('entry_time'))
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-cyan-900/60 text-cyan-300 rounded-full text-xs">
        ⏱ Entry {{ substr($entryTime,0,2) }}:{{ substr($entryTime,2) }}
        ({{ substr($entryTime,0,2)%12 ?: 12 }}:{{ substr($entryTime,2) }} {{ substr($entryTime,0,2)>=12 ? 'PM' : 'AM' }})
    </span>
                            @endif
                        </div>
                    @endif

                    @if(request('peak_filter') && request('peak_dir') && request('peak_value') !== null)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-purple-900/60 text-purple-300 rounded-full text-xs">
        Peak
        {{ ucwords(str_replace('_', ' ', request('peak_filter'))) }}
                            {{ request('peak_dir') === 'gte' ? '≥' : '≤' }}
        ₹{{ number_format(request('peak_value'), 0) }}
    </span>
                    @endif


                    @foreach((array) request('skip_days', []) as $skippedDay)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-orange-900/60 text-orange-300 rounded-full text-xs">
        ⏭ {{ $skippedDay }} skipped
    </span>
                    @endforeach

                    @if(request('exit_time'))
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-orange-900/60 text-orange-300 rounded-full text-xs">
        🚪 Exit @ {{ \Carbon\Carbon::createFromFormat('H:i', request('exit_time'))->format('h:i A') }}
    </span>
                    @endif

                    @if(request('gap_dir') && request('gap_value') != null)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-teal-900/60 text-teal-300 rounded-full text-xs">
        Gap {{ request('gap_dir') == 'gte' ? '≥' : '≤' }} {{ number_format(request('gap_value'), 0) }}
    </span>
                    @endif

                    @if(request('rangedir') && request('rangevalue') !== null)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-slate-800 text-slate-300 rounded-full text-xs">
        Prev Range {{ request('rangedir') === 'gte' ? '>=' : '<=' }} {{ number_format((float) request('rangevalue'), 2) }}
    </span>
                    @endif

                    @if(request('gap_pct_dir') && request('gap_pct_value') !== null)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-violet-900/60 text-violet-300 rounded-full text-xs">
        Gap % {{ request('gap_pct_dir') === 'gte' ? '>=' : '<=' }} {{ number_format((float) request('gap_pct_value'), 2) }}%
    </span>
                    @endif
                </form>
            </div>
        </div>

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

            {{-- ── Monthly P&L Grid ──────────────────────────────────────────── --}}
            @if($monthlyStats->isNotEmpty())
                <div x-data="{ open: false }" class="mb-6">
                    <button @click="open = !open"
                        class="w-full flex items-center justify-between bg-gray-900 border border-gray-800 rounded-xl px-5 py-3 text-sm font-semibold text-gray-300 hover:bg-gray-800 transition-colors">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>Monthly Performance</span>
                        </div>
                        <svg class="w-4 h-4 text-gray-500 transition-transform duration-200" :class="open ? 'rotate-180' : ''"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="open" x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 -translate-y-2"
                        class="mt-1">
                        @php
                            $monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                            $allYears   = $monthlyStats->keys()->sort()->values();
                        @endphp

                        <div class="bg-gray-900 border border-gray-800 rounded-xl p-5 mb-6">

                            {{-- Title --}}
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">
                                    📅 Monthly Performance
                                </h2>
                                <span class="text-xs text-gray-600">P&L · Win Rate · Days</span>
                            </div>

                            {{-- One table per year --}}
                            @foreach($allYears as $year)
                                @php
                                    $yearMonths  = $monthlyStats->get($year)->keyBy('month');
                                    $yearPnl     = $yearMonths->sum('total_pnl');
                                    $yearProfit  = $yearMonths->sum('profit_days');
                                    $yearLoss    = $yearMonths->sum('loss_days');
                                    $yearTotal   = $yearMonths->sum('total_days');
                                    $yearWinRate = $yearTotal > 0 ? round($yearProfit / $yearTotal * 100) : 0;
                                @endphp

                                <div class="mb-5 last:mb-0">

                                    {{-- Year header --}}
                                    <div class="flex items-center gap-4 mb-2">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-widest w-8">
                {{ $year }}
            </span>
                                        <div class="flex items-center gap-3">
                <span class="text-sm font-bold font-mono
                    {{ $yearPnl >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                    {{ $yearPnl >= 0 ? '+' : '' }}₹{{ number_format($yearPnl, 0) }}
                </span>
                                            <span class="text-xs px-2 py-0.5 rounded-full
                    {{ $yearWinRate >= 60 ? 'bg-emerald-900/50 text-emerald-300'
                     : ($yearWinRate >= 45 ? 'bg-yellow-900/50 text-yellow-300'
                     : 'bg-red-900/50 text-red-300') }}">
                    {{ $yearWinRate }}% WR
                </span>
                                            <span class="text-xs text-gray-600">
                    {{ $yearProfit }}W / {{ $yearLoss }}L / {{ $yearTotal }} days
                </span>
                                        </div>
                                        <div class="flex-1 h-px bg-gray-800"></div>
                                    </div>

                                    {{-- Month cells --}}
                                    <div class="grid grid-cols-6 md:grid-cols-12 gap-1.5">
                                        @for($m = 1; $m <= 12; $m++)
                                            @php
                                                $month    = $yearMonths->get($m);
                                                $pnl      = $month?->total_pnl ?? null;
                                                $profit   = $month?->profit_days ?? 0;
                                                $loss     = $month?->loss_days ?? 0;
                                                $total    = $month?->total_days ?? 0;
                                                $winRate  = $total > 0 ? round($profit / $total * 100) : null;

                                                $bgClass  = match(true) {
                                                    $pnl === null              => 'bg-gray-800/40 border-gray-800',
                                                    $pnl > 10000               => 'bg-emerald-900/80 border-emerald-700/50',
                                                    $pnl > 5000                => 'bg-emerald-900/60 border-emerald-800/50',
                                                    $pnl > 0                   => 'bg-emerald-900/30 border-emerald-900/50',
                                                    $pnl < -10000              => 'bg-red-900/80 border-red-700/50',
                                                    $pnl < -5000               => 'bg-red-900/60 border-red-800/50',
                                                    default                    => 'bg-red-900/30 border-red-900/50',
                                                };

                                                $pnlColor = match(true) {
                                                    $pnl === null => 'text-gray-700',
                                                    $pnl >= 0    => 'text-emerald-300',
                                                    default      => 'text-red-300',
                                                };
                                            @endphp

                                            <div class="relative group border rounded-lg p-2 cursor-default
                        transition-all duration-150 hover:scale-105 {{ $bgClass }}">

                                                {{-- Month name --}}
                                                <p class="text-xs font-semibold text-gray-400 mb-1">
                                                    {{ $monthNames[$m - 1] }}
                                                </p>

                                                @if($pnl !== null)
                                                    {{-- P&L --}}
                                                    <p class="text-xs font-bold font-mono leading-tight {{ $pnlColor }}">
                                                        {{ $pnl >= 0 ? '+' : '' }}{{ number_format($pnl / 1000, 1) }}k
                                                    </p>

                                                    {{-- Win rate bar --}}
                                                    <div class="mt-1.5 h-1 rounded-full bg-gray-700/50 overflow-hidden">
                                                        <div class="h-full rounded-full transition-all
                            {{ $winRate >= 60 ? 'bg-emerald-500' : ($winRate >= 45 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                                            style="width: {{ $winRate }}%">
                                                        </div>
                                                    </div>

                                                    <p class="text-xs text-gray-500 mt-1">
                                                        {{ $winRate }}%
                                                        <span class="text-gray-600">· {{ $total }}d</span>
                                                    </p>
                                                @else
                                                    <p class="text-xs text-gray-700 mt-1">—</p>
                                                @endif

                                                {{-- Hover tooltip --}}
                                                @if($pnl !== null)
                                                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 z-20
                            hidden group-hover:block pointer-events-none">
                                                        <div class="bg-gray-800 border border-gray-700 rounded-lg
                                px-3 py-2 text-xs whitespace-nowrap shadow-xl">
                                                            <p class="font-semibold text-white mb-1">
                                                                {{ $monthNames[$m - 1] }} {{ $year }}
                                                            </p>
                                                            <p class="{{ $pnlColor }} font-mono font-bold">
                                                                {{ $pnl >= 0 ? '+' : '' }}₹{{ number_format($pnl, 0) }}
                                                            </p>
                                                            <p class="text-gray-400 mt-0.5">
                                                                ✓ {{ $profit }} profit · ✗ {{ $loss }} loss
                                                            </p>
                                                            <p class="text-gray-500">Win rate: {{ $winRate }}%</p>
                                                            <div class="absolute top-full left-1/2 -translate-x-1/2
                                    border-4 border-transparent border-t-gray-700">
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif

                                            </div>
                                        @endfor
                                    </div>

                                </div>
                            @endforeach

                        </div>
                    </div>
                </div>
            @endif


            {{-- Weekday Performance --}}
            @if(isset($dowStats) && $dowStats->isNotEmpty())
                <div x-data="{ open: false }" class="mb-6">
                    <button @click="open = !open"
                        class="w-full flex items-center justify-between bg-gray-900 border border-gray-800 rounded-xl px-5 py-3 text-sm font-semibold text-gray-300 hover:bg-gray-800 transition-colors">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            <span>Weekday Performance</span>
                        </div>
                        <svg class="w-4 h-4 text-gray-500 transition-transform duration-200" :class="open ? 'rotate-180' : ''"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="open" x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 -translate-y-2"
                        class="mt-1">
                        <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden mb-6">
                            <div class="px-5 py-3 border-b border-gray-800 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">
                                    📅 Weekday Performance
                                </h3>
                                <span class="text-xs text-gray-600">avg P&amp;L and win rate per trading day</span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                    <tr class="border-b border-gray-800 bg-gray-950/40">
                                        <th class="text-left py-2.5 px-4 text-xs text-gray-500 uppercase tracking-wider">Day</th>
                                        <th class="text-right py-2.5 px-4 text-xs text-gray-500 uppercase tracking-wider">Days</th>
                                        <th class="text-right py-2.5 px-4 text-xs text-gray-500 uppercase tracking-wider">Profit</th>
                                        <th class="text-right py-2.5 px-4 text-xs text-gray-500 uppercase tracking-wider">Loss</th>
                                        <th class="text-left py-2.5 px-4 text-xs text-gray-500 uppercase tracking-wider w-40">Win Rate</th>
                                        <th class="text-right py-2.5 px-4 text-xs text-gray-500 uppercase tracking-wider">Avg P&amp;L</th>
                                        <th class="text-right py-2.5 px-4 text-xs text-gray-500 uppercase tracking-wider">Total P&amp;L</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-800/60">
                                    @php
                                        $dowOrder = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
                                        $dowMap   = $dowStats->keyBy('dow');
                                    @endphp
                                    @foreach($dowOrder as $dayName)
                                        @php $d = $dowMap->get($dayName); @endphp
                                        @if($d)
                                            <tr class="hover:bg-gray-800/30 transition-colors">

                                                {{-- Day name + colour dot --}}
                                                <td class="py-3 px-4 font-medium text-gray-200">
                                                    <div class="flex items-center gap-2">
                                                        @php
                                                            $dotColor = match($dayName) {
                                                                'Monday'    => 'bg-blue-500',
                                                                'Tuesday'   => 'bg-indigo-500',
                                                                'Wednesday' => 'bg-purple-500',
                                                                'Thursday'  => 'bg-amber-500',
                                                                'Friday'    => 'bg-red-500',
                                                                default     => 'bg-gray-500',
                                                            };
                                                        @endphp
                                                        <span class="w-2 h-2 rounded-full {{ $dotColor }}"></span>
                                                        {{ $dayName }}
                                                    </div>
                                                </td>

                                                {{-- Days traded --}}
                                                <td class="py-3 px-4 text-right text-gray-400">
                                                    {{ $d->total_days }}
                                                </td>

                                                {{-- Profit days --}}
                                                <td class="py-3 px-4 text-right text-emerald-400">
                                                    {{ $d->profit_days }}
                                                </td>

                                                {{-- Loss days --}}
                                                <td class="py-3 px-4 text-right text-red-400">
                                                    {{ $d->loss_days }}
                                                </td>

                                                {{-- Win rate bar --}}
                                                <td class="py-3 px-4">
                                                    <div class="flex items-center gap-2">
                                                        <div class="flex-1 bg-gray-800 rounded-full h-1.5 w-24">
                                                            <div
                                                                class="h-1.5 rounded-full {{ $d->win_rate >= 55 ? 'bg-emerald-500' : ($d->win_rate >= 45 ? 'bg-amber-500' : 'bg-red-500') }}"
                                                                style="width: {{ min($d->win_rate, 100) }}%">
                                                            </div>
                                                        </div>
                                                        <span class="text-xs font-mono w-12 text-right
                                    {{ $d->win_rate >= 55 ? 'text-emerald-400' : ($d->win_rate >= 45 ? 'text-amber-400' : 'text-red-400') }}">
                                    {{ $d->win_rate }}%
                                </span>
                                                    </div>
                                                </td>

                                                {{-- Avg P&L --}}
                                                <td class="py-3 px-4 text-right font-mono font-semibold
                            {{ $d->avg_pnl >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                                    {{ ($d->avg_pnl >= 0 ? '+₹' : '-₹') . number_format(abs($d->avg_pnl), 0) }}
                                                </td>

                                                {{-- Total P&L --}}
                                                <td class="py-3 px-4 text-right font-mono
                            {{ $d->total_pnl >= 0 ? 'text-emerald-300' : 'text-red-300' }}">
                                                    {{ ($d->total_pnl >= 0 ? '+₹' : '-₹') . number_format(abs($d->total_pnl), 0) }}
                                                </td>

                                            </tr>
                                        @endif
                                    @endforeach

                                    {{-- Totals row --}}
                                    <tr class="bg-gray-800/40 border-t-2 border-gray-700">
                                        <td class="py-3 px-4 text-xs text-gray-500 uppercase font-semibold">Total</td>
                                        <td class="py-3 px-4 text-right text-gray-300 font-semibold">
                                            {{ $dowStats->sum('total_days') }}
                                        </td>
                                        <td class="py-3 px-4 text-right text-emerald-400 font-semibold">
                                            {{ $dowStats->sum('profit_days') }}
                                        </td>
                                        <td class="py-3 px-4 text-right text-red-400 font-semibold">
                                            {{ $dowStats->sum('loss_days') }}
                                        </td>
                                        <td class="py-3 px-4">
                                            @php
                                                $totalDow   = $dowStats->sum('total_days');
                                                $totalWins  = $dowStats->sum('profit_days');
                                                $overallWr  = $totalDow > 0 ? round($totalWins / $totalDow * 100, 1) : 0;
                                            @endphp
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 bg-gray-800 rounded-full h-1.5 w-24">
                                                    <div class="h-1.5 rounded-full bg-indigo-500"
                                                        style="width: {{ $overallWr }}%"></div>
                                                </div>
                                                <span class="text-xs font-mono w-12 text-right text-indigo-400">
                                {{ $overallWr }}%
                            </span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 text-right font-mono font-semibold text-gray-300">
                                            @php $overallAvg = $dowStats->sum('total_days') > 0
                            ? round($dowStats->sum('total_pnl') / $dowStats->sum('total_days'))
                            : 0; @endphp
                                            {{ ($overallAvg >= 0 ? '+₹' : '-₹') . number_format(abs($overallAvg), 0) }}
                                        </td>
                                        <td class="py-3 px-4 text-right font-mono font-semibold
                        {{ $dowStats->sum('total_pnl') >= 0 ? 'text-emerald-300' : 'text-red-300' }}">
                                            @php $gt = $dowStats->sum('total_pnl'); @endphp
                                            {{ ($gt >= 0 ? '+₹' : '-₹') . number_format(abs($gt), 0) }}
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif


            {{-- Summary Stats --}}
            @if($statsQuery)
                @php
                    $totalDays = $statsQuery->profit_days + $statsQuery->loss_days;
                    $winRate   = $totalDays > 0 ? round($statsQuery->profit_days / $totalDays * 100, 1) : 0;
                @endphp
                <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-13 gap-3 mb-6">

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
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Avg Gap</p>
                        <p class="text-xl font-bold text-teal-400">
                            {{ number_format($statsQuery->avgmaxprofit ?? 0, 1) }} <!-- Wait, use statsQuery->AVG(gap_used) -->
                        </p>
                    </div>

                    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Avg Peak Profit</p>
                        <p class="text-xl font-bold text-emerald-300">
                            +₹{{ number_format($statsQuery->avg_max_profit ?? 0, 0) }}
                        </p>
                    </div>

                    {{-- Avg Profit P&L --}}
                    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Avg Profit P&L</p>
                        <p class="text-xl font-bold text-emerald-400">
                            {{ $statsQuery->avg_profit_pnl
                                ? '₹' . number_format(round($statsQuery->avg_profit_pnl), 0)
                                : '—' }}
                        </p>
                        <p class="text-xs text-gray-600 mt-1">per profit day</p>
                    </div>

                    {{-- Avg Loss P&L --}}
                    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Avg Loss P&L</p>
                        <p class="text-xl font-bold text-red-400">
                            {{ $statsQuery->avg_loss_pnl
                                ? '₹' . number_format(round($statsQuery->avg_loss_pnl), 0)
                                : '—' }}
                        </p>
                        <p class="text-xs text-gray-600 mt-1">per loss day</p>
                    </div>

                    {{-- Weekly Avg Win Rate --}}
                    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Weekly Avg Win Rate</p>
                        <p class="text-xl font-bold {{ ($weeklyAvgWinRate ?? 0) >= 50 ? 'text-emerald-400' : 'text-red-400' }}">
                            {{ $weeklyAvgWinRate ? number_format($weeklyAvgWinRate, 1) . '%' : '—' }}
                        </p>
                        <p class="text-xs text-gray-600 mt-1">avg across all weeks</p>
                    </div>

                    {{-- Weekly Avg P&L --}}
                    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Weekly Avg P&L</p>
                        <p class="text-xl font-bold {{ ($weeklyAvgPnl ?? 0) >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                            {{ $weeklyAvgPnl
                                ? (($weeklyAvgPnl >= 0 ? '+₹' : '-₹') . number_format(abs(round($weeklyAvgPnl)), 0))
                                : '—' }}
                        </p>
                        <p class="text-xs text-gray-600 mt-1">per week avg</p>
                    </div>

                    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Avg Gap</p>
                        <p class="text-xl font-bold text-teal-400">
                            {{ $statsQuery->avg_gap ? number_format($statsQuery->avg_gap, 1) : '-' }}
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
                            <th class="px-4 py-3 text-right">Gap</th>
                            <th class="px-4 py-3 text-right">Prev Range</th>
                            <th class="px-4 py-3 text-right">Gap %</th>
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



                                $atm   = null;
                                $upper = null;
                                $lower = null;

                                // Only compute offset-based strikes for strategies that use strike_offset
                                $offsetStrategies = ['fixed_offset', 'smart_balanced'];

                                if (in_array($day->strategy, $offsetStrategies) && $day->index_price_at_entry && $day->strike_offset) {
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
                                    @switch($day->strategy)

                                        @case('atm_straddle')
                                            {{-- Single ATM strike, both CE+PE --}}
                                            @php $atm = (int)(round($day->index_price_at_entry / 100) * 100); @endphp
                                            <div class="flex items-center justify-center gap-1 text-xs font-mono">
                <span class="px-1.5 py-0.5 bg-purple-900/50 text-purple-300 rounded">
                    {{ number_format($atm) }}
                </span>
                                                <span class="text-gray-600 text-xs">CE+PE</span>
                                            </div>
                                            @break

                                        @case('near_straddle')
                                            {{-- ATM±100 strikes --}}
                                            @php
                                                $atm   = (int)(round($day->index_price_at_entry / 100) * 100);
                                                $upper = $atm + 100;
                                                $lower = $atm - 100;
                                            @endphp
                                            <div class="flex items-center justify-center gap-1 text-xs font-mono">
                <span class="px-1.5 py-0.5 bg-purple-900/50 text-purple-300 rounded">
                    {{ number_format($lower) }}
                </span>
                                                <span class="text-gray-600">&</span>
                                                <span class="px-1.5 py-0.5 bg-purple-900/50 text-purple-300 rounded">
                    {{ number_format($upper) }}
                </span>
                                            </div>
                                            @break

                                        @case('first_candle_breakout')
                                            {{-- Single directional strike --}}
                                            <div class="flex items-center justify-center gap-1 text-xs font-mono">
                <span class="px-1.5 py-0.5 rounded
                    {{ $day->day_outcome === 'profit' ? 'bg-emerald-900/50 text-emerald-300' : 'bg-red-900/50 text-red-300' }}">
                    {{ number_format($day->strike) }}
                </span>
                                                <span class="px-1.5 py-0.5 rounded text-xs font-bold
                    {{ $day->instrument_type === 'CE' ? 'bg-blue-900/50 text-blue-300' : 'bg-orange-900/50 text-orange-300' }}">
                    {{ $day->instrument_type }}
                </span>
                                            </div>
                                            @break
                                        @case('strangle_straddle')
                                            @php
                                                $atm = (int)(round($day->index_price_at_entry / 100) * 100);

                                                // ATM straddle legs
                                                $straddleCE = $atm;
                                                $straddlePE = $atm;

                                                // OTM strangle legs — pulled from DB aggregated strikes
                                                $strangleCE = $day->ce_strike  ?? null;
                                                $stranglePE = $day->pe_strike  ?? null;

                                                // If ce_strike == ATM, the MAX picked the straddle strike.
                                                // The strangle OTM strike is stored via strike_offset
                                                $offset      = $day->strike_offset ?? 200;
                                                $strangleCE  = $atm + $offset;
                                                $stranglePE  = $atm - $offset;
                                            @endphp

                                            <div class="flex flex-col items-center gap-1.5 text-xs font-mono">

                                                {{-- Straddle row (ATM CE + PE) --}}
                                                <div class="flex items-center gap-1">
                                                    <span class="px-1.5 py-0.5 bg-purple-900/50 text-purple-300 rounded">
                                                        {{ number_format($straddleCE) }}
                                                    </span>
                                                    <span class="text-gray-500 text-xs">CE+PE</span>
                                                    <span class="text-gray-600 text-xs italic">(ATM)</span>
                                                </div>

                                                <div class="text-gray-700 text-xs leading-none">+</div>

                                                {{-- Strangle row (OTM CE / PE) --}}
                                                <div class="flex items-center gap-1">
                                                    <span class="px-1.5 py-0.5 bg-blue-900/50 text-blue-300 rounded">
                                                        {{ number_format($strangleCE) }}
                                                    </span>
                                                    <span class="text-gray-500 text-xs">CE</span>
                                                    <span class="text-gray-600 mx-0.5">/</span>
                                                    <span class="px-1.5 py-0.5 bg-orange-900/50 text-orange-300 rounded">
                                                        {{ number_format($stranglePE) }}
                                                    </span>
                                                    <span class="text-gray-500 text-xs">PE</span>
                                                </div>

                                            </div>
                                            @break

                                        @case('otm_strangle')
                                            @php
                                                $ce = $day->ce_strike ?? $day->strike;
                                                $pe = $day->pe_strike ?? $day->strike;
                                                $symmetric = $ce === $pe;
                                            @endphp

                                            <div class="flex items-center justify-center gap-1 text-xs font-mono flex-wrap">
                                                @if($symmetric)
                                                    {{-- Same strike both sides (rare, but possible) --}}
                                                    <span class="px-1.5 py-0.5 bg-indigo-900/50 text-indigo-300 rounded">
                {{ number_format($ce) }}
            </span>
                                                    <span class="text-gray-600 text-xs">CE+PE</span>
                                                @else
                                                    {{-- Asymmetric — show CE and PE separately --}}
                                                    <span class="px-1.5 py-0.5 bg-blue-900/50 text-blue-300 rounded">
                {{ number_format($ce) }}
            </span>
                                                    <span class="text-gray-500 text-xs">CE</span>
                                                    <span class="text-gray-600 mx-0.5">/</span>
                                                    <span class="px-1.5 py-0.5 bg-orange-900/50 text-orange-300 rounded">
                {{ number_format($pe) }}
            </span>
                                                    <span class="text-gray-500 text-xs">PE</span>
                                                @endif
                                            </div>
                                            @break

                                        @case('15min_breakout')
                                            @php
                                                $ce = $day->ce_strike ?? null;
                                                $pe = $day->pe_strike ?? null;
                                                // Single leg — use instrument_type to know which side
                                                $singleStrike = $day->strike ?? null;
                                                $singleType   = $day->instrument_type ?? null;
                                            @endphp

                                            <div class="flex items-center justify-center gap-1 text-xs font-mono">
                                                @if($singleStrike && $singleType)
                                                    <span class="px-1.5 py-0.5 rounded
                {{ $singleType === 'CE' ? 'bg-blue-900/50 text-blue-300' : 'bg-orange-900/50 text-orange-300' }}">
                {{ number_format($singleStrike) }}
            </span>
                                                    <span class="px-1.5 py-0.5 rounded text-xs font-bold
                {{ $singleType === 'CE' ? 'bg-blue-900/30 text-blue-400' : 'bg-orange-900/30 text-orange-400' }}">
                {{ $singleType }}
            </span>
                                                @else
                                                    <span class="text-gray-600 text-xs">—</span>
                                                @endif
                                            </div>
                                            @break

                                        @case('iron_condor_ladder')
                                            @php
                                                $atm = (int)(round($day->index_price_at_entry / 100) * 100);
                                            @endphp
                                            <div class="flex flex-col items-center gap-0.5 text-xs font-mono">
                                                {{-- ATM --}}
                                                <div class="flex items-center gap-1">
            <span class="px-1.5 py-0.5 bg-purple-900/50 text-purple-300 rounded">
                {{ number_format($atm) }}
            </span>
                                                    <span class="text-gray-600 text-xs">ATM</span>
                                                </div>
                                                {{-- ±100 --}}
                                                <div class="flex items-center gap-1">
            <span class="px-1.5 py-0.5 bg-indigo-900/50 text-indigo-300 rounded">
                {{ number_format($atm - 100) }}
            </span>
                                                    <span class="text-gray-500 text-xs">±100</span>
                                                    <span class="px-1.5 py-0.5 bg-indigo-900/50 text-indigo-300 rounded">
                {{ number_format($atm + 100) }}
            </span>
                                                </div>
                                                {{-- ±300 --}}
                                                <div class="flex items-center gap-1">
            <span class="px-1.5 py-0.5 bg-blue-900/50 text-blue-300 rounded">
                {{ number_format($atm - 300) }}
            </span>
                                                    <span class="text-gray-500 text-xs">±300</span>
                                                    <span class="px-1.5 py-0.5 bg-blue-900/50 text-blue-300 rounded">
                {{ number_format($atm + 300) }}
            </span>
                                                </div>
                                            </div>
                                            @break

                                        @default
                                            {{-- fixed_offset / smart_balanced — ATM ± offset --}}
                                            @if($lower && $upper)
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

                                    @endswitch
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

                                {{-- Gap --}}
                                <td class="px-4 py-3 text-right font-mono text-cyan-300 text-xs">
                                    {{ $day->gap_used !== null ? number_format($day->gap_used, 2) : '—' }}
                                </td>

                                <td class="px-4 py-3 text-right font-mono text-gray-300 text-xs">
                                    {{ $day->previous_day_range !== null ? number_format($day->previous_day_range, 2) : '—' }}
                                </td>

                                <td class="px-4 py-3 text-right font-mono text-violet-300 text-xs">
                                    {{ $day->gap_pct_prev_range !== null ? number_format($day->gap_pct_prev_range, 2) . '%' : '—' }}
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
                                {{-- Params --}}
                                <td class="px-4 py-3 text-center">
                                    <div class="flex flex-wrap justify-center gap-1">
                                        @if(!in_array($day->strategy, ['15min_breakout', 'first_candle_breakout']))
                                            <span class="px-1.5 py-0.5 bg-gray-800 rounded text-xs font-mono text-indigo-300">
                {{ $day->strike_offset }}
            </span>
                                        @endif
                                        <span class="px-1.5 py-0.5 bg-emerald-900/40 rounded text-xs font-mono text-emerald-400">
            T {{ number_format($day->target, 0) }}
        </span>
                                        <span class="px-1.5 py-0.5 bg-red-900/40 rounded text-xs font-mono text-red-400">
            SL {{ number_format($day->stoploss, 0) }}
        </span>
                                    </div>
                                </td>

                                {{-- Action --}}
                                <td class="px-4 py-3 text-center" onclick="event.stopPropagation()">
                                    <a href="{{ route('backtest.trades', ['group_id' => $day->day_group_id]) }}"
                                        class="inline-flex items-center gap-1 bg-indigo-600 hover:bg-indigo-500
                                      text-white text-xs font-medium px-3 py-1.5 rounded-lg
                                      transition-colors whitespace-nowrap">
                                        View More
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
                            <td colspan="6" class="px-4 py-2.5 text-gray-500 uppercase tracking-wider">
                                Page total ({{ $days->count() }} days)
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono font-bold text-teal-400">
                                {{ number_format($days->avg('gap_used') ?? 0, 1) }}
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
