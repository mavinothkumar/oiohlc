@extends('layouts.app')

@section('title', 'Build up Analysis')

@section('content')
    <div class="bg-gray-950 text-gray-100 min-h-screen font-sans antialiased">
        {{-- ── Filter Bar ── --}}
        <div class="px-6 py-4 border-b border-gray-800 bg-gray-900/50">
            <form method="GET" action="{{ route('test.build-up.index') }}"
                class="flex flex-wrap items-end gap-4">

                {{-- Date --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-400 uppercase tracking-wider">Date</label>
                    <input type="date" name="date" value="{{ $date }}"
                        class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm
                          text-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500
                          focus:border-transparent transition"/>
                </div>

                {{-- Strikes --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-gray-400 uppercase tracking-wider">
                        Strikes ±
                    </label>
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

        {{-- ── Error ── --}}
        @if (session('error'))
            <div class="mx-6 mt-4 px-4 py-3 bg-red-900/40 border border-red-700 rounded-lg text-red-300 text-sm">
                {{ session('error') }}
            </div>
        @endif

        {{-- ── Info Strip ── --}}
        <div class="px-6 pt-5 pb-2 flex flex-wrap gap-6 text-sm">
            <div class="flex items-center gap-2">
                <span class="text-gray-500">Expiry</span>
                <span class="font-semibold text-yellow-400">{{ $expiryDate }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-gray-500">Spot</span>
                <span class="font-semibold text-blue-400">{{ number_format($spotPrice, 2) }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-gray-500">ATM Strike</span>
                <span class="font-semibold text-emerald-400">{{ number_format($nearestStrike, 0) }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-gray-500">Selected Strikes</span>
                <div class="flex gap-1">
                    @foreach ($strikeList as $s)
                        <span class="px-2 py-0.5 rounded {{ $s == $nearestStrike
                    ? 'bg-emerald-600/30 text-emerald-300 border border-emerald-600'
                    : 'bg-gray-800 text-gray-300' }} text-xs font-mono">
                    {{ number_format($s, 0) }}
                </span>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── Market Bias Prediction ── --}}
        @php
            $biasConfig = match($bias) {
                'Bullish'  => [
                    'gradient'   => 'from-emerald-900/60 to-emerald-800/20',
                    'border'     => 'border-emerald-600/50',
                    'iconBg'     => 'bg-emerald-500/20',
                    'icon'       => '▲',
                    'iconColor'  => 'text-emerald-400',
                    'labelColor' => 'text-emerald-300',
                    'barColor'   => 'bg-emerald-500',
                    'scoreColor' => 'text-emerald-400',
                ],
                'Bearish'  => [
                    'gradient'   => 'from-red-900/60 to-red-800/20',
                    'border'     => 'border-red-600/50',
                    'iconBg'     => 'bg-red-500/20',
                    'icon'       => '▼',
                    'iconColor'  => 'text-red-400',
                    'labelColor' => 'text-red-300',
                    'barColor'   => 'bg-red-500',
                    'scoreColor' => 'text-red-400',
                ],
                default    => [
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

            // Gauge: map biasScore (-100 to +100) → percentage fill (0 to 100)
            $gaugeFill    = (($biasScore + 100) / 200) * 100;
            $bullishPct   = $bullishOI + $bearishOI > 0
                              ? round(($bullishOI / ($bullishOI + $bearishOI)) * 100)
                              : 50;
            $bearishPct   = 100 - $bullishPct;
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
                        <div class="text-xs text-gray-500 uppercase tracking-widest mb-0.5">
                            Market Bias
                        </div>
                        <div class="text-2xl font-extrabold {{ $biasConfig['labelColor'] }} leading-tight">
                            {{ $biasStrength }} {{ $bias }}
                        </div>
                    </div>
                </div>

                {{-- Divider --}}
                <div class="hidden md:block w-px h-14 bg-gray-700"></div>

                {{-- Sentiment Gauge Bar --}}
                <div class="flex-1 min-w-[220px]">
                    <div class="flex justify-between text-xs text-gray-500 mb-1.5">
                        <span class="text-red-400 font-medium">◀ Bearish</span>
                        <span class="text-gray-400">Sentiment Gauge</span>
                        <span class="text-emerald-400 font-medium">Bullish ▶</span>
                    </div>
                    {{-- Track --}}
                    <div class="relative h-4 rounded-full bg-gradient-to-r
                        from-red-700/60 via-yellow-600/40 to-emerald-700/60
                        border border-gray-700 overflow-visible">
                        {{-- Needle --}}
                        <div class="absolute top-1/2 -translate-y-1/2 w-3 h-3 rounded-full
                            bg-white shadow-lg ring-2 ring-gray-900 transition-all duration-700"
                            style="left: calc({{ $gaugeFill }}% - 6px)">
                        </div>
                    </div>
                    {{-- Score label --}}
                    <div class="text-center mt-1.5">
                <span class="text-xs font-mono {{ $biasConfig['scoreColor'] }} font-semibold">
                    Score: {{ $biasScore > 0 ? '+' : '' }}{{ $biasScore }}
                </span>
                    </div>
                </div>

                {{-- Divider --}}
                <div class="hidden md:block w-px h-14 bg-gray-700"></div>

                {{-- Bull vs Bear OI Breakdown --}}
                <div class="min-w-[180px]">
                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-2">
                        Weighted OI Split
                    </div>
                    {{-- Bullish row --}}
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
                    {{-- Bearish row --}}
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

                {{-- Divider --}}
                <div class="hidden lg:block w-px h-14 bg-gray-700"></div>

                {{-- Build-Up Mini Breakdown --}}
                <div class="min-w-[200px]">
                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-2">
                        Contributing Signals
                    </div>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                        <div class="flex justify-between gap-2">
                            <span class="text-emerald-400">▲ Long Build</span>
                            <span class="font-mono text-gray-300">
                        {{ format_inr_compact($buildUpTotals['Long Build']['oi']) }}
                    </span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-red-400">▼ Short Build</span>
                            <span class="font-mono text-gray-300">
                        {{ format_inr_compact($buildUpTotals['Short Build']['oi']) }}
                    </span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-sky-400">↑ Short Cover</span>
                            <span class="font-mono text-gray-300">
                        {{ format_inr_compact($buildUpTotals['Short Cover']['oi']) }}
                    </span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-orange-400">↓ Long Unwind</span>
                            <span class="font-mono text-gray-300">
                        {{ format_inr_compact($buildUpTotals['Long Unwind']['oi']) }}
                    </span>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Disclaimer --}}
            <p class="mt-4 text-xs text-gray-600 border-t border-gray-700/50 pt-3">
                ⚠ This bias is derived purely from OI build-up data for the selected strikes and date.
                It is indicative only and not financial advice.
            </p>
        </div>


        {{-- ── Main Grid ── --}}
        <div class="px-6 py-4 grid grid-cols-1 xl:grid-cols-2 gap-6">

            {{-- Build-Up Summary Cards --}}
            <div class="grid grid-cols-2 gap-4 content-start">

                @php
                    $cardMeta = [
                        'Long Build'  => ['icon' => '▲', 'color' => 'emerald', 'desc' => 'OI ↑ | LTP ↑'],
                        'Short Build' => ['icon' => '▼', 'color' => 'red',     'desc' => 'OI ↑ | LTP ↓'],
                        'Short Cover' => ['icon' => '↑', 'color' => 'sky',     'desc' => 'OI ↓ | LTP ↑'],
                        'Long Unwind' => ['icon' => '↓', 'color' => 'orange',  'desc' => 'OI ↓ | LTP ↓'],
                    ];
                    $colorMap = [
                        'emerald' => ['bg' => 'bg-emerald-900/30', 'border' => 'border-emerald-700',
                                      'text' => 'text-emerald-300', 'badge' => 'bg-emerald-600/20 text-emerald-400'],
                        'red'     => ['bg' => 'bg-red-900/30',     'border' => 'border-red-700',
                                      'text' => 'text-red-300',     'badge' => 'bg-red-600/20 text-red-400'],
                        'sky'     => ['bg' => 'bg-sky-900/30',     'border' => 'border-sky-700',
                                      'text' => 'text-sky-300',     'badge' => 'bg-sky-600/20 text-sky-400'],
                        'orange'  => ['bg' => 'bg-orange-900/30',  'border' => 'border-orange-700',
                                      'text' => 'text-orange-300',  'badge' => 'bg-orange-600/20 text-orange-400'],
                    ];
                @endphp

                @foreach ($buildUpTotals as $label => $vals)
                    @php
                        $meta  = $cardMeta[$label];
                        $clr   = $colorMap[$meta['color']];
                    @endphp
                    <div class="rounded-xl border {{ $clr['border'] }} {{ $clr['bg'] }} p-4 flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                    <span class="text-base font-semibold {{ $clr['text'] }}">
                        {{ $meta['icon'] }} {{ $label }}
                    </span>
                            <span class="text-xs px-2 py-0.5 rounded {{ $clr['badge'] }}">
                        {{ $meta['desc'] }}
                    </span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-center">
                            <div class="bg-gray-900/60 rounded-lg p-2">
                                <div class="text-xs text-gray-500 mb-1">Total OI</div>
                                <div class="text-lg font-bold {{ $clr['text'] }}">
                                    {{ format_inr_compact($vals['oi']) }}
                                </div>
                            </div>
                            <div class="bg-gray-900/60 rounded-lg p-2">
                                <div class="text-xs text-gray-500 mb-1">Total Vol</div>
                                <div class="text-lg font-bold {{ $clr['text'] }}">
                                    {{ format_inr_compact($vals['volume']) }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Charts --}}
            <div class="flex flex-col gap-6">

                {{-- OI Chart --}}
                <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
                    <h3 class="text-sm font-medium text-gray-400 mb-3 uppercase tracking-wider">
                        Open Interest by Build-Up
                    </h3>
                    <div class="relative h-52">
                        <canvas id="oiChart"></canvas>
                    </div>
                </div>

                {{-- Volume Chart --}}
                <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
                    <h3 class="text-sm font-medium text-gray-400 mb-3 uppercase tracking-wider">
                        Volume by Build-Up
                    </h3>
                    <div class="relative h-52">
                        <canvas id="volChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    {{-- ── Chart.js Init ── --}}
    <script>
        const labels = @json($chartLabels);
        const oiData = @json($chartOI);
        const volData = @json($chartVolume);

        const barColors = [
            'rgba(52, 211, 153, 0.8)',   // emerald  – Long Build
            'rgba(248, 113, 113, 0.8)',  // red      – Short Build
            'rgba(56,  189, 248, 0.8)',  // sky       – Short Cover
            'rgba(251, 146, 60,  0.8)'  // orange   – Long Unwind
        ];
        const borderColors = [
            'rgb(52, 211, 153)',
            'rgb(248, 113, 113)',
            'rgb(56,  189, 248)',
            'rgb(251, 146, 60)'
        ];

        const sharedOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15,23,42,0.95)',
                    borderColor: 'rgba(255,255,255,0.1)',
                    borderWidth: 1,
                    titleColor: '#94a3b8',
                    bodyColor: '#f1f5f9',
                    callbacks: {
                        label: ctx => ' ' + ctx.parsed.y.toLocaleString('en-IN')
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
                        callback: v => v >= 1e6
                            ? ( v / 1e6 ).toFixed(1) + 'M'
                            : v >= 1e3 ? ( v / 1e3 ).toFixed(0) + 'K' : v
                    },
                    grid: { color: 'rgba(255,255,255,0.05)' }
                }
            }
        };

        // OI Chart
        new Chart(document.getElementById('oiChart'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    data: oiData,
                    backgroundColor: barColors,
                    borderColor: borderColors,
                    borderWidth: 1.5,
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: sharedOptions
        });

        // Volume Chart
        new Chart(document.getElementById('volChart'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    data: volData,
                    backgroundColor: barColors,
                    borderColor: borderColors,
                    borderWidth: 1.5,
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: sharedOptions
        });
    </script>

@endsection
