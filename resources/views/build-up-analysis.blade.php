@extends('layouts.app')

@section('title', 'Build up Analysis')

@section('content')
    <div class="bg-gray-950 text-gray-100 min-h-screen font-sans antialiased">
        <div class="px-6 py-4 border-b border-gray-800 bg-gray-900/50">
            <form method="GET" action="{{ route('build-up.index') }}"
                class="flex flex-wrap items-end gap-4">

                {{-- Date --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-200 uppercase tracking-wider">Date</label>
                    <input type="date" name="date" value="{{ $date }}"
                        class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm
                          text-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500
                          focus:border-transparent transition"/>
                </div>

                {{-- Strikes --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-200 uppercase tracking-wider">Strikes ±</label>
                    <select name="strikes"
                        class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm
                           text-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500
                           focus:border-transparent transition">
                        @foreach ([1, 2, 3, 4, 5] as $s)
                            <option value="{{ $s }}" {{ $strikes == $s ? 'selected' : '' }}>
                                {{ $s }} ({{ $s * 2 + 1 }} strikes)
                            </option>
                        @endforeach
                    </select>
                </div>

                <button type="submit"
                    class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-sm
                       font-medium transition-colors duration-150 self-end">
                    Apply
                </button>
            </form>
        </div>

        {{-- ════════════════════════════════════════════════════════ --}}
        {{-- ── Empty State (no expiry / no data) ── --}}
        {{-- ════════════════════════════════════════════════════════ --}}
        @isset($emptyState)

            <div class="flex flex-col items-center justify-center min-h-[60vh] px-6 text-center">

                {{-- Animated pulse ring --}}
                <div class="relative mb-6">
                    <div class="w-24 h-24 rounded-full bg-gray-800 flex items-center justify-center
                        text-5xl ring-4 ring-gray-700">
                        {{ $emptyState['icon'] }}
                    </div>
                    <span class="absolute inset-0 rounded-full ring-4 ring-yellow-500/30 animate-ping"></span>
                </div>

                <h2 class="text-2xl font-bold text-gray-200 mb-2">
                    {{ $emptyState['title'] }}
                </h2>

                <p class="text-gray-200 max-w-md mb-1">
                    {{ $emptyState['message'] }}
                </p>

                <p class="text-sm text-gray-200 max-w-sm mb-8">
                    {{ $emptyState['hint'] }}
                </p>

                {{-- Market hours badge --}}
                <div class="flex items-center gap-2 px-4 py-2 bg-gray-800/60 border border-gray-700
                    rounded-full text-sm text-gray-200 mb-8">
                    <span class="w-2 h-2 rounded-full bg-yellow-400 animate-pulse inline-block"></span>
                    Market opens at
                    <span class="text-yellow-300 font-semibold">09:15 AM IST</span>
                    on trading days
                </div>

                {{-- Try another date --}}
                <form method="GET" action="{{ route('build-up.index') }}"
                    class="flex items-end gap-3 bg-gray-900 border border-gray-700 rounded-xl px-5 py-4">
                    <div class="flex flex-col gap-1 text-left">
                        <label class="text-xs text-gray-200 uppercase tracking-wider">
                            Try a different date
                        </label>
                        <input type="date" name="date" value="{{ $date }}"
                            class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm
                              text-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500
                              focus:border-transparent transition"/>
                    </div>
                    <input type="hidden" name="strikes" value="{{ $strikes }}"/>
                    <button type="submit"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-sm
                           font-medium transition-colors duration-150 self-end">
                        Reload
                    </button>
                </form>

            </div>

        @else
            {{-- ════════════════════════════════════════════════════════ --}}
            {{-- ── Normal Page ── --}}
            {{-- ════════════════════════════════════════════════════════ --}}

            {{-- ── Info Strip ── --}}
            <div class="px-6 pt-5 pb-2 flex flex-wrap gap-6 text-sm">
                <div class="flex items-center gap-2">
                    <span class="text-gray-200">Expiry</span>
                    <span class="font-semibold text-yellow-400">{{ $expiryDate }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-gray-200">Spot</span>
                    <span class="font-semibold text-blue-400">{{ number_format($spotPrice, 2) }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-gray-200">ATM Strike</span>
                    <span class="font-semibold text-emerald-400">{{ number_format($nearestStrike, 0) }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-gray-200">Selected Strikes</span>
                    <div class="flex gap-1 flex-wrap">
                        @foreach ($strikeList as $s)
                            <span class="px-2 py-0.5 rounded text-xs font-mono
                    {{ $s == $nearestStrike
                        ? 'bg-emerald-600/30 text-emerald-300 border border-emerald-600'
                        : 'bg-gray-800 text-gray-300' }}">
                    {{ number_format($s, 0) }}
                </span>
                        @endforeach
                    </div>
                </div>
            </div>




            {{-- ── Market Bias Prediction ── --}}
            @php
                $biasConfig = match($bias) {
                    'Bullish' => [
                        'gradient'   => 'from-emerald-900/60 to-emerald-800/20',
                        'border'     => 'border-emerald-600/50',
                        'iconBg'     => 'bg-emerald-500/20',
                        'icon'       => '▲',
                        'iconColor'  => 'text-emerald-400',
                        'labelColor' => 'text-emerald-300',
                        'barColor'   => 'bg-emerald-500',
                        'scoreColor' => 'text-emerald-400',
                    ],
                    'Bearish' => [
                        'gradient'   => 'from-red-900/60 to-red-800/20',
                        'border'     => 'border-red-600/50',
                        'iconBg'     => 'bg-red-500/20',
                        'icon'       => '▼',
                        'iconColor'  => 'text-red-400',
                        'labelColor' => 'text-red-300',
                        'barColor'   => 'bg-red-500',
                        'scoreColor' => 'text-red-400',
                    ],
                    default => [
                        'gradient'   => 'from-yellow-900/40 to-yellow-800/10',
                        'border'     => 'border-yellow-600/50',
                        'iconBg'     => 'bg-yellow-500/20',
                        'icon'       => '↔',
                        'iconColor'  => 'text-yellow-400',
                        'labelColor' => 'text-yellow-300',
                        'barColor'   => 'bg-yellow-500',
                        'scoreColor' => 'text-yellow-400',
                    ],
                };

                $gaugeFill  = (($biasScore + 100) / 200) * 100;
                $totalWOI   = $bullishOI + $bearishOI;
                $bullishPct = $totalWOI > 0 ? round(($bullishOI / $totalWOI) * 100) : 50;
                $bearishPct = 100 - $bullishPct;
            @endphp

            <div class="mx-6 my-4 rounded-2xl border {{ $biasConfig['border'] }}
            bg-gradient-to-r {{ $biasConfig['gradient'] }} p-5">

                <div class="flex flex-wrap items-center gap-6">

                    {{-- Icon + Label --}}
                    <div class="flex items-center gap-4 min-w-[160px]">
                        <div class="w-14 h-14 rounded-full {{ $biasConfig['iconBg'] }} flex items-center
                        justify-center text-2xl {{ $biasConfig['iconColor'] }} font-bold
                        ring-2 ring-current ring-offset-2 ring-offset-gray-950">
                            {{ $biasConfig['icon'] }}
                        </div>
                        <div>
                            <div class="text-xs text-gray-200 uppercase tracking-widest mb-0.5">
                                Market Bias
                            </div>
                            <div class="text-2xl font-extrabold {{ $biasConfig['labelColor'] }} leading-tight">
                                {{ $biasStrength }} {{ $bias }}
                            </div>
                        </div>
                    </div>

                    <div class="hidden md:block w-px h-14 bg-gray-700"></div>

                    {{-- Sentiment Gauge Bar --}}
                    <div class="flex-1 min-w-[220px]">
                        <div class="flex justify-between text-xs text-gray-200 mb-1.5">
                            <span class="text-red-400 font-medium">◀ Bearish</span>
                            <span class="text-gray-200">Sentiment Gauge</span>
                            <span class="text-emerald-400 font-medium">Bullish ▶</span>
                        </div>
                        <div class="relative h-4 rounded-full
                        bg-gradient-to-r from-red-700/60 via-yellow-600/40 to-emerald-700/60
                        border border-gray-700 overflow-visible">
                            <div class="absolute top-1/2 -translate-y-1/2 w-3 h-3 rounded-full
                            bg-white shadow-lg ring-2 ring-gray-900 transition-all duration-700"
                                style="left: calc({{ $gaugeFill }}% - 6px)">
                            </div>
                        </div>
                        <div class="text-center mt-1.5">
                <span class="text-xs font-mono {{ $biasConfig['scoreColor'] }} font-semibold">
                    Score: {{ $biasScore > 0 ? '+' : '' }}{{ $biasScore }}
                </span>
                        </div>
                    </div>

                    <div class="hidden md:block w-px h-14 bg-gray-700"></div>

                    {{-- Weighted OI Split --}}
                    <div class="min-w-[180px]">
                        <div class="text-xs text-gray-200 uppercase tracking-wider mb-2">
                            Weighted OI Split
                        </div>
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="text-xs text-emerald-400 w-16">Bullish</span>
                            <div class="flex-1 h-2.5 bg-gray-800 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500 rounded-full transition-all duration-700"
                                    style="width: {{ $bullishPct }}%"></div>
                            </div>
                            <span class="text-xs font-mono text-emerald-400 w-8 text-right">
                    {{ $bullishPct }}%
                </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-red-400 w-16">Bearish</span>
                            <div class="flex-1 h-2.5 bg-gray-800 rounded-full overflow-hidden">
                                <div class="h-full bg-red-500 rounded-full transition-all duration-700"
                                    style="width: {{ $bearishPct }}%"></div>
                            </div>
                            <span class="text-xs font-mono text-red-400 w-8 text-right">
                    {{ $bearishPct }}%
                </span>
                        </div>
                    </div>

                    <div class="hidden lg:block w-px h-14 bg-gray-700"></div>

                    {{-- Contributing Signals --}}
                    <div class="min-w-[220px]">
                        <div class="text-xs text-gray-200 uppercase tracking-wider mb-2">
                            Contributing Signals
                        </div>
                        <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs">
                            {{-- CE signals --}}
                            <div class="col-span-2 text-gray-200 uppercase tracking-widest text-[10px] mb-0.5">
                                CE
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-emerald-400">▲ Long Build</span>
                                <span class="font-mono text-gray-300">
                        {{ format_inr_compact($buildUpTotals['CE']['Long Build']['oi']) }}
                    </span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-red-400">▼ Short Build</span>
                                <span class="font-mono text-gray-300">
                        {{ format_inr_compact($buildUpTotals['CE']['Short Build']['oi']) }}
                    </span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-sky-400">↑ Short Cover</span>
                                <span class="font-mono text-gray-300">
                        {{ format_inr_compact($buildUpTotals['CE']['Short Cover']['oi']) }}
                    </span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-orange-400">↓ Long Unwind</span>
                                <span class="font-mono text-gray-300">
                        {{ format_inr_compact($buildUpTotals['CE']['Long Unwind']['oi']) }}
                    </span>
                            </div>
                            {{-- PE signals --}}
                            <div class="col-span-2 text-gray-200 uppercase tracking-widest text-[10px] mt-1 mb-0.5">
                                PE
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-red-400">▲ Long Build</span>
                                <span class="font-mono text-gray-300">
                        {{ format_inr_compact($buildUpTotals['PE']['Long Build']['oi']) }}
                    </span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-emerald-400">▼ Short Build</span>
                                <span class="font-mono text-gray-300">
                        {{ format_inr_compact($buildUpTotals['PE']['Short Build']['oi']) }}
                    </span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-red-400">↑ Short Cover</span>
                                <span class="font-mono text-gray-300">
                        {{ format_inr_compact($buildUpTotals['PE']['Short Cover']['oi']) }}
                    </span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-emerald-400">↓ Long Unwind</span>
                                <span class="font-mono text-gray-300">
                        {{ format_inr_compact($buildUpTotals['PE']['Long Unwind']['oi']) }}
                    </span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- ── Main Grid ── --}}
            <div class="px-6 py-4 grid grid-cols-1 xl:grid-cols-2 gap-6">

                {{-- ── Build-Up Summary Cards (CE + PE) ── --}}
                <div class="flex flex-col gap-4">

                    @foreach (['CE' => 'text-blue-400 border-blue-700/50 bg-blue-900/10',
                               'PE' => 'text-pink-400 border-pink-700/50 bg-pink-900/10']
                              as $type => $typeStyle)
                        @php
                            $bullishKeys = $type === 'CE'
                                ? ['Long Build', 'Short Cover']
                                : ['Short Build', 'Long Unwind'];

                            $cardMeta = [
                                'Long Build'  => ['icon' => '▲', 'desc' => 'OI↑ LTP↑'],
                                'Short Build' => ['icon' => '▼', 'desc' => 'OI↑ LTP↓'],
                                'Short Cover' => ['icon' => '↑', 'desc' => 'OI↓ LTP↑'],
                                'Long Unwind' => ['icon' => '↓', 'desc' => 'OI↓ LTP↓'],
                            ];
                        @endphp

                        {{-- Type header --}}
                        <div class="flex items-center gap-3">
                <span class="px-3 py-0.5 rounded-full text-xs font-bold border
                             {{ $typeStyle }} uppercase tracking-widest">
                    {{ $type }}
                </span>
                            <div class="flex-1 h-px bg-gray-800"></div>
                            <span class="text-[10px] text-gray-200">
                    @if ($type === 'CE')
                                    Bullish: Long Build, Short Cover &nbsp;·&nbsp; Bearish: Short Build, Long Unwind
                                @else
                                    Bullish: Short Build, Long Unwind &nbsp;·&nbsp; Bearish: Long Build, Short Cover
                                @endif
                </span>
                        </div>

                        {{-- 4 Cards --}}
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                            @foreach ($buildUpTotals[$type] as $label => $vals)
                                @php
                                    $isBullish = in_array($label, $bullishKeys);
                                    $cardColor = $isBullish
                                        ? ['border' => 'border-emerald-700/50', 'bg' => 'bg-emerald-900/20',
                                           'text'   => 'text-emerald-300',
                                           'badge'  => 'bg-emerald-900/40 text-emerald-400']
                                        : ['border' => 'border-red-700/50',     'bg' => 'bg-red-900/20',
                                           'text'   => 'text-red-300',
                                           'badge'  => 'bg-red-900/40 text-red-400'];
                                @endphp
                                <div class="rounded-xl border {{ $cardColor['border'] }}
                                {{ $cardColor['bg'] }} p-3">
                                    <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-semibold {{ $cardColor['text'] }}">
                                {{ $cardMeta[$label]['icon'] }} {{ $label }}
                            </span>
                                        <span class="text-xs px-1.5 py-0.5 rounded {{ $cardColor['badge'] }}">
                                {{ $cardMeta[$label]['desc'] }}
                            </span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-1.5 text-center">
                                        <div class="bg-gray-900/60 rounded-lg p-1.5">
                                            <div class="text-xs text-gray-200 mb-0.5">OI</div>
                                            <div class="text-sm font-bold {{ $cardColor['text'] }}">
                                                {{ format_inr_compact($vals['oi']) }}
                                            </div>
                                        </div>
                                        <div class="bg-gray-900/60 rounded-lg p-1.5">
                                            <div class="text-xs text-gray-200 mb-0.5">Vol</div>
                                            <div class="text-sm font-bold {{ $cardColor['text'] }}">
                                                {{ format_inr_compact($vals['volume']) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                    @endforeach



                        {{-- Market Prediction Panel --}}
                        <div class="bg-gray-900 border border-gray-700/50 rounded-xl p-4 mt-4">

                            <h3 class="text-xs font-semibold text-gray-200 uppercase tracking-wider mb-3">
                                📡 Market Prediction
                            </h3>

                            {{-- Aggregate Signal --}}
                            @php
                                $predConfig = match($prediction['signal']) {
                                    'BULLISH'  => [
                                        'border'  => 'border-emerald-700/50',
                                        'bg'      => 'bg-emerald-900/20',
                                        'text'    => 'text-emerald-300',
                                        'badge'   => 'bg-emerald-900/40 text-emerald-400 ring-1 ring-emerald-700/50',
                                    ],
                                    'BEARISH'  => [
                                        'border'  => 'border-red-700/50',
                                        'bg'      => 'bg-red-900/20',
                                        'text'    => 'text-red-300',
                                        'badge'   => 'bg-red-900/40 text-red-400 ring-1 ring-red-700/50',
                                    ],
                                    default    => [
                                        'border'  => 'border-yellow-700/50',
                                        'bg'      => 'bg-yellow-900/20',
                                        'text'    => 'text-yellow-300',
                                        'badge'   => 'bg-yellow-900/40 text-yellow-400 ring-1 ring-yellow-700/50',
                                    ],
                                };
                            @endphp

                            <div class="rounded-xl border {{ $predConfig['border'] }} {{ $predConfig['bg'] }} px-4 py-3 mb-4
                flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <div class="text-xl font-extrabold {{ $predConfig['text'] }} leading-tight">
                                        {{ $prediction['label'] }}
                                    </div>
                                    <div class="text-xs text-gray-200 mt-0.5">
                                        {{ $prediction['reason'] }}
                                    </div>
                                </div>

                                {{-- Confidence Badge --}}
                                <div class="flex flex-col items-end gap-1">
                                    <span class="text-[10px] text-gray-200 uppercase tracking-widest">Confidence</span>
                                    <span class="px-3 py-1 rounded-full text-sm font-bold {{ $predConfig['badge'] }}">
                {{ $prediction['confidence'] }}%
            </span>
                                </div>
                            </div>

                            {{-- Individual Strategy Cards --}}
                            @if(collect($strategies)->where('triggered', true)->isEmpty())
                                <p class="text-xs text-gray-200 text-center py-3">
                                    No strategies triggered yet — snapshots are saved every 5 minutes during market hours.
                                </p>
                            @else
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach($strategies as $s)
                                        @if($s['triggered'])
                                            @php
                                                $sConfig = match($s['signal']) {
                                                    'BULLISH' => [
                                                        'border' => 'border-emerald-700/40',
                                                        'bg'     => 'bg-emerald-900/20',
                                                        'title'  => 'text-emerald-300',
                                                        'text'   => 'text-emerald-400/70',
                                                        'conf'   => 'text-emerald-400',
                                                    ],
                                                    'BEARISH' => [
                                                        'border' => 'border-red-700/40',
                                                        'bg'     => 'bg-red-900/20',
                                                        'title'  => 'text-red-300',
                                                        'text'   => 'text-red-400/70',
                                                        'conf'   => 'text-red-400',
                                                    ],
                                                    default   => [
                                                        'border' => 'border-yellow-700/40',
                                                        'bg'     => 'bg-yellow-900/20',
                                                        'title'  => 'text-yellow-300',
                                                        'text'   => 'text-yellow-400/70',
                                                        'conf'   => 'text-yellow-400',
                                                    ],
                                                };
                                            @endphp
                                            <div class="rounded-lg border {{ $sConfig['border'] }} {{ $sConfig['bg'] }} px-3 py-2">
                                                <div class="text-xs font-semibold {{ $sConfig['title'] }}">
                                                    {{ $s['label'] }}
                                                </div>
                                                <div class="text-[11px] {{ $sConfig['text'] }} mt-0.5 leading-snug">
                                                    {{ $s['reason'] }}
                                                </div>
                                                <div class="text-xs font-bold {{ $sConfig['conf'] }} mt-1.5">
                                                    {{ $s['confidence'] }}% confidence
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            <p class="mt-3 text-[10px] border-t border-gray-800 pt-2">
                                ⚠ Predictions are based on OI snapshot history and are indicative only — not financial advice.
                            </p>
                        </div>





                </div>

                {{-- ── Charts ── --}}
                <div class="flex flex-col gap-6">

                    {{-- OI Chart --}}
                    <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
                        <h3 class="text-sm font-medium text-gray-200 mb-3 uppercase tracking-wider">
                            Open Interest by Build-Up
                        </h3>
                        <div class="relative h-52">
                            <canvas id="oiChart"></canvas>
                        </div>
                    </div>

                    {{-- Volume Chart --}}
                    <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
                        <h3 class="text-sm font-medium text-gray-200 mb-3 uppercase tracking-wider">
                            Volume by Build-Up
                        </h3>
                        <div class="relative h-52">
                            <canvas id="volChart"></canvas>
                        </div>
                    </div>

                </div>
            </div>

        @endisset
        {{-- ════ End of @isset/$emptyState check ════ --}}
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    {{-- ── Chart.js ── --}}
    @isset($chartLabels)
        @if(count($chartLabels))
            <script>
                const labels = @json($chartLabels);
                const ceOI = @json($chartCE_OI);
                const peOI = @json($chartPE_OI);
                const ceVol = @json($chartCE_Vol);
                const peVol = @json($chartPE_Vol);

                const sharedOptions = {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            labels: { color: '#94a3b8', font: { size: 11 }, boxWidth: 12 }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15,23,42,0.95)',
                            borderColor: 'rgba(255,255,255,0.1)',
                            borderWidth: 1,
                            titleColor: '#94a3b8',
                            bodyColor: '#f1f5f9',
                            callbacks: {
                                label: ctx =>
                                    ' ' + ctx.dataset.label + ': ' +
                                    ctx.parsed.y.toLocaleString('en-IN')
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: '#94a3b8', font: { size: 11 } },
                            grid: { color: 'rgba(255,255,255,0.05)' }
                        },
                        y: {
                            ticks: {
                                color: '#94a3b8',
                                font: { size: 10 },
                                callback: v =>
                                    v >= 1e6 ? ( v / 1e6 ).toFixed(1) + 'M' :
                                        v >= 1e3 ? ( v / 1e3 ).toFixed(0) + 'K' : v
                            },
                            grid: { color: 'rgba(255,255,255,0.05)' }
                        }
                    }
                };

                const ceDataset = (data, label) => ( {
                    label,
                    data,
                    backgroundColor: 'rgba(96, 165, 250, 0.75)',
                    borderColor: 'rgb(96, 165, 250)',
                    borderWidth: 1.5, borderRadius: 5, borderSkipped: false
                } );

                const peDataset = (data, label) => ( {
                    label,
                    data,
                    backgroundColor: 'rgba(244, 114, 182, 0.75)',
                    borderColor: 'rgb(244, 114, 182)',
                    borderWidth: 1.5, borderRadius: 5, borderSkipped: false
                } );

                new Chart(document.getElementById('oiChart'), {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [ceDataset(ceOI, 'CE OI'), peDataset(peOI, 'PE OI')]
                    },
                    options: sharedOptions
                });

                new Chart(document.getElementById('volChart'), {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [ceDataset(ceVol, 'CE Volume'), peDataset(peVol, 'PE Volume')]
                    },
                    options: sharedOptions
                });
            </script>
        @endif
    @endisset
@endsection
