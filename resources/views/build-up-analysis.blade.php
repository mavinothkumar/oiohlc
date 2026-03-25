@extends('layouts.app')

@section('title', 'Build-Up Analysis')

@section('content')
    <div class="min-h-screen bg-gray-950">

        {{-- ══════════════════════════════════════════════
             FILTER BAR
        ══════════════════════════════════════════════ --}}
        <div class="sticky top-0 z-30 bg-gray-950/95 backdrop-blur border-b border-gray-800 shadow-lg shadow-black/50">
            <form method="GET" action="{{ route('build-up.index') }}"
                class="px-6 py-3 flex flex-wrap items-end gap-4">

                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-semibold text-gray-100 uppercase tracking-widest">Date</label>
                    <input type="date" name="date" value="{{ $date }}"
                        class="bg-gray-900 border border-gray-700 hover:border-gray-600 rounded-lg
                          px-3 py-2 text-sm text-gray-100 focus:outline-none focus:ring-2
                          focus:ring-emerald-500 focus:border-transparent transition-all" />
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-semibold text-gray-100 uppercase tracking-widest">Strikes ±</label>
                    <select name="strikes"
                        class="bg-gray-900 border border-gray-700 hover:border-gray-600 rounded-lg
                           px-3 py-2 text-sm text-gray-100 focus:outline-none focus:ring-2
                           focus:ring-emerald-500 focus:border-transparent transition-all">
                        @foreach ([1, 2, 3, 4, 5] as $s)
                            <option value="{{ $s }}" {{ $strikes == $s ? 'selected' : '' }}>
                                ± {{ $s }} &nbsp;({{ $s * 2 + 1 }} strikes)
                            </option>
                        @endforeach
                    </select>
                </div>

                <button type="submit"
                    class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 active:scale-95
                       rounded-lg text-sm font-semibold shadow-md shadow-emerald-900/40
                       transition-all duration-150 self-end">
                    Apply
                </button>

                {{-- Info chips inline in filter bar --}}
                @isset($spotPrice)
                    <div class="ml-auto flex items-center gap-3 flex-wrap">
                        @php
                            $chips = [
                                ['label' => 'Expiry',     'value' => $expiryDate,                    'color' => 'text-yellow-400'],
                                ['label' => 'Spot',       'value' => number_format($spotPrice, 2),   'color' => 'text-sky-400'],
                                ['label' => 'ATM Strike', 'value' => number_format($nearestStrike, 0),'color' => 'text-emerald-400'],
                            ];
                        @endphp
                        @foreach ($chips as $chip)
                            <div class="flex items-center gap-1.5 bg-gray-900 border border-gray-800
                            rounded-lg px-2.5 py-1.5">
                                <span class="text-[10px] text-gray-100">{{ $chip['label'] }}</span>
                                <span class="text-xs font-bold {{ $chip['color'] }}">{{ $chip['value'] }}</span>
                            </div>
                        @endforeach
                        {{-- Strike badges --}}
                        <div class="flex items-center gap-1.5 flex-wrap">
                            @foreach ($strikeList as $s)
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono
                        {{ $s == $nearestStrike
                            ? 'bg-emerald-500/20 text-emerald-300 ring-1 ring-emerald-600'
                            : 'bg-gray-800 text-gray-100 ring-1 ring-gray-700' }}">
                        {{ number_format($s, 0) }}
                    </span>
                            @endforeach
                        </div>
                    </div>
                @endisset

            </form>
        </div>

        {{-- ══════════════════════════════════════════════
             EMPTY STATE
        ══════════════════════════════════════════════ --}}
        @isset($emptyState)
            <div class="flex flex-col items-center justify-center min-h-[60vh] px-6 text-center">
                <div class="relative mb-6">
                    <div class="w-24 h-24 rounded-full bg-gray-900 flex items-center justify-center
                    text-5xl ring-2 ring-gray-700 shadow-xl">
                        {{ $emptyState['icon'] }}
                    </div>
                    <span class="absolute inset-0 rounded-full ring-4 ring-yellow-500/20 animate-ping"></span>
                </div>
                <h2 class="text-2xl font-bold text-white mb-2">{{ $emptyState['title'] }}</h2>
                <p class="text-gray-100 max-w-md mb-1">{{ $emptyState['message'] }}</p>
                <p class="text-sm text-gray-100 max-w-sm mb-8">{{ $emptyState['hint'] }}</p>
                <div class="flex items-center gap-2 px-4 py-2 bg-gray-900 border border-gray-700
                rounded-full text-sm text-gray-100 mb-8">
                    <span class="w-2 h-2 rounded-full bg-yellow-400 animate-pulse inline-block"></span>
                    Market opens at <span class="text-yellow-300 font-semibold ml-1">09:15 AM IST</span>
                </div>
                <form method="GET" action="{{ route('build-up.index') }}"
                    class="flex items-end gap-3 bg-gray-900 border border-gray-700 rounded-xl px-5 py-4 shadow-lg">
                    <div class="flex flex-col gap-1 text-left">
                        <label class="text-[10px] text-gray-100 uppercase tracking-widest">Try a different date</label>
                        <input type="date" name="date" value="{{ $date }}"
                            class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm
                          text-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition" />
                    </div>
                    <input type="hidden" name="strikes" value="{{ $strikes }}" />
                    <button type="submit"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-sm
                       font-semibold transition self-end">
                        Reload
                    </button>
                </form>
            </div>

        @else
            {{-- ══════════════════════════════════════════════
                 ① FLOATING SENTIMENT PILL
            ══════════════════════════════════════════════ --}}
            @php
                $biasConfig = match($bias) {
                    'Bullish' => [
                        'pillBg'     => 'bg-emerald-950 border-emerald-700',
                        'gaugeFill'  => 'from-emerald-500 to-emerald-400',
                        'needleGlow' => 'shadow-emerald-400/60',
                        'scoreColor' => 'text-emerald-400',
                        'labelColor' => 'text-emerald-300',
                        'icon'       => '▲',
                        'iconColor'  => 'text-emerald-400',
                        'barBull'    => 'bg-emerald-500',
                    ],
                    'Bearish' => [
                        'pillBg'     => 'bg-red-950 border-red-700',
                        'gaugeFill'  => 'from-red-500 to-red-400',
                        'needleGlow' => 'shadow-red-400/60',
                        'scoreColor' => 'text-red-400',
                        'labelColor' => 'text-red-300',
                        'icon'       => '▼',
                        'iconColor'  => 'text-red-400',
                        'barBull'    => 'bg-red-500',
                    ],
                    default => [
                        'pillBg'     => 'bg-yellow-950 border-yellow-700',
                        'gaugeFill'  => 'from-yellow-500 to-yellow-400',
                        'needleGlow' => 'shadow-yellow-400/60',
                        'scoreColor' => 'text-yellow-400',
                        'labelColor' => 'text-yellow-300',
                        'icon'       => '↔',
                        'iconColor'  => 'text-yellow-400',
                        'barBull'    => 'bg-yellow-500',
                    ],
                };

                $gaugeFill  = (($biasScore + 100) / 200) * 100;
                $totalWOI   = $bullishOI + $bearishOI;
                $bullishPct = $totalWOI > 0 ? round(($bullishOI / $totalWOI) * 100) : 50;
                $bearishPct = 100 - $bullishPct;
            @endphp

            <div class="flex justify-center px-6 pt-4 pb-1">
                <div class="inline-flex items-center gap-4 px-5 py-2.5 rounded-2xl border {{ $biasConfig['pillBg'] }} shadow-xl ring-1 ring-white/5 backdrop-blur-sm min-w-0 w-full">

                    {{-- Bias label --}}
                    <div class="flex items-center gap-2 shrink-0">
            <span class="text-base {{ $biasConfig['iconColor'] }} font-bold leading-none">
                {{ $biasConfig['icon'] }}
            </span>
                        <div>
                            <div class="text-[9px] text-gray-100 uppercase tracking-widest leading-none mb-0.5">
                                Full-Day Bias
                            </div>
                            <div class="text-sm font-extrabold {{ $biasConfig['labelColor'] }} leading-tight">
                                {{ $biasStrength }} {{ $bias }}
                            </div>
                        </div>
                    </div>

                    <div class="w-px h-8 bg-gray-700 shrink-0"></div>

                    {{-- Gauge bar --}}
                    <div class="flex-1 min-w-[140px]">
                        <div class="flex justify-between text-[9px] font-semibold uppercase tracking-wider mb-1">
                            <span class="text-red-500">◀ Bear</span>
                            <span class="text-gray-100">Sentiment</span>
                            <span class="text-emerald-500">Bull ▶</span>
                        </div>
                        <div class="relative h-3 rounded-full overflow-visible
                        bg-gradient-to-r from-red-700/70 via-gray-700/50 to-emerald-700/70
                        ring-1 ring-white/5">
                            <div class="absolute top-1/2 -translate-y-1/2 w-3.5 h-3.5 rounded-full
                            bg-white ring-2 ring-gray-900 shadow-lg {{ $biasConfig['needleGlow'] }}
                            transition-all duration-700 z-10"
                                style="left: calc({{ $gaugeFill }}% - 7px)"></div>
                        </div>
                    </div>

                    {{-- Score --}}
                    <div class="shrink-0 text-center">
                        <div class="text-[9px] text-gray-100 uppercase tracking-widest mb-0.5">Score</div>
                        <div class="text-sm font-mono font-bold {{ $biasConfig['scoreColor'] }}">
                            {{ $biasScore > 0 ? '+' : '' }}{{ $biasScore }}
                        </div>
                    </div>

                    <div class="w-px h-8 bg-gray-700 shrink-0"></div>

                    {{-- OI split --}}
                    <div class="shrink-0 min-w-[120px]">
                        <div class="text-[9px] text-gray-100 uppercase tracking-widest mb-1.5">OI Split</div>
                        @foreach ([['Bullish','emerald',$bullishPct],['Bearish','red',$bearishPct]] as [$lbl,$clr,$pct])
                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="text-[10px] text-{{ $clr }}-400 w-10">{{ $lbl }}</span>
                                <div class="flex-1 h-1.5 bg-gray-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-{{ $clr }}-500 rounded-full transition-all duration-700"
                                        style="width:{{ $pct }}%"></div>
                                </div>
                                <span class="text-[10px] font-mono text-{{ $clr }}-400 w-6 text-right">{{ $pct }}%</span>
                            </div>
                        @endforeach
                    </div>

                </div>
            </div>

            <p class="text-center text-[10px] text-gray-100 mt-1 mb-3">
                ⚠ Indicative only — not financial advice
            </p>


            {{-- ══════════════════════════════════════════════
        ② RECENT ACTIVITY WINDOWS (15 min / 30 min)
        One row: 15min card | 30min card
        Each card: OI section + Volume section
        Each section: 1/3 table | 2/3 chart
   ══════════════════════════════════════════════ --}}
            @if(!empty($recentWindows))
                <div class="px-6 mb-6">
                    <h2 class="text-[10px] font-bold text-gray-100 uppercase tracking-widest mb-3 flex items-center gap-2">
                        <span class="w-3 h-px bg-gray-700 inline-block"></span>
                        Recent Activity Windows
                        <span class="flex-1 h-px bg-gray-800 inline-block"></span>
                    </h2>

                    {{-- 15 min and 30 min side by side --}}
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                        @foreach ($recentWindows as $windowLabel => $w)
                            @php
                                $wConfig = match($w['bias']) {
                                    'Bullish' => ['border'=>'border-emerald-700/50','bg'=>'bg-emerald-950/30','score'=>'text-emerald-400','label'=>'text-emerald-300','icon'=>'▲','dot'=>'bg-emerald-500'],
                                    'Bearish' => ['border'=>'border-red-700/50',    'bg'=>'bg-red-950/30',    'score'=>'text-red-400',    'label'=>'text-red-300',    'icon'=>'▼','dot'=>'bg-red-500'],
                                    default   => ['border'=>'border-yellow-700/50', 'bg'=>'bg-yellow-950/20', 'score'=>'text-yellow-400', 'label'=>'text-yellow-300', 'icon'=>'↔','dot'=>'bg-yellow-500'],
                                };
                                $cid = 'w_' . Str::slug($windowLabel);
                            @endphp

                            <div class="rounded-2xl border {{ $wConfig['border'] }} {{ $wConfig['bg'] }}
                    shadow-lg ring-1 ring-white/5 overflow-hidden">

                                {{-- Window header --}}
                                <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-800/60">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-2 h-2 rounded-full {{ $wConfig['dot'] }} inline-block"></span>
                                        <div>
                                            <div class="text-[9px] text-gray-100 uppercase tracking-widest leading-none">{{ $windowLabel }}</div>
                                            <div class="text-sm font-extrabold {{ $wConfig['label'] }} leading-tight">
                                                {{ $w['strength'] }} {{ $w['bias'] }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-[9px] text-gray-100 uppercase tracking-widest leading-none mb-0.5">Bias Score</div>
                                        <div class="text-sm font-mono font-bold {{ $wConfig['score'] }}">
                                            {{ $w['score'] > 0 ? '+' : '' }}{{ $w['score'] }}
                                        </div>
                                    </div>
                                </div>

                                {{-- OI row: 1/3 table + 2/3 chart --}}
                                <div class="border-b border-gray-800/60">
                                    <div class="px-4 pt-3 pb-1 flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500 inline-block"></span>
                                        <span class="text-[9px] font-bold text-gray-100 uppercase tracking-widest">Open Interest</span>
                                    </div>
                                    <div class="grid grid-cols-3 gap-0 px-3 pb-3">

                                        {{-- OI table: 1/3 --}}
                                        <div class="col-span-1 pr-3 flex flex-col gap-1.5">
                                            @foreach (['CE' => 'sky', 'PE' => 'pink'] as $optType => $typeClr)
                                                <div class="bg-gray-900/70 rounded-lg ring-1 ring-gray-800 p-2">
                                                    <div class="text-[8px] font-bold text-{{ $typeClr }}-400 uppercase
                                        tracking-widest mb-1.5">{{ $optType }}</div>
                                                    @foreach ($w['totals'][$optType] as $lbl => $vals)
                                                        <div class="flex justify-between text-[10px] mb-0.5">
                                                            <span class="text-gray-100 truncate mr-1">{{ $lbl }}</span>
                                                            <span class="font-mono text-gray-100 shrink-0">
                                    {{ format_inr_compact($vals['oi']) }}
                                </span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endforeach
                                        </div>

                                        {{-- OI chart: 2/3 --}}
                                        <div class="col-span-2 relative" style="height: 168px;">
                                            <canvas id="{{ $cid }}_oi"></canvas>
                                        </div>

                                    </div>
                                </div>

                                {{-- Volume row: 1/3 table + 2/3 chart --}}
                                <div>
                                    <div class="px-4 pt-3 pb-1 flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-purple-500 inline-block"></span>
                                        <span class="text-[9px] font-bold text-gray-100 uppercase tracking-widest">Volume</span>
                                    </div>
                                    <div class="grid grid-cols-3 gap-0 px-3 pb-3">

                                        {{-- Volume table: 1/3 --}}
                                        <div class="col-span-1 pr-3 flex flex-col gap-1.5">
                                            @foreach (['CE' => 'sky', 'PE' => 'pink'] as $optType => $typeClr)
                                                <div class="bg-gray-900/70 rounded-lg ring-1 ring-gray-800 p-2">
                                                    <div class="text-[8px] font-bold text-{{ $typeClr }}-400 uppercase
                                        tracking-widest mb-1.5">{{ $optType }}</div>
                                                    @foreach ($w['totals'][$optType] as $lbl => $vals)
                                                        <div class="flex justify-between text-[10px] mb-0.5">
                                                            <span class="text-gray-100 truncate mr-1">{{ $lbl }}</span>
                                                            <span class="font-mono text-gray-100 shrink-0">
                                    {{ format_inr_compact($vals['volume']) }}
                                </span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endforeach
                                        </div>

                                        {{-- Volume chart: 2/3 --}}
                                        <div class="col-span-2 relative" style="height: 168px;">
                                            <canvas id="{{ $cid }}_vol"></canvas>
                                        </div>

                                    </div>
                                </div>

                            </div>
                        @endforeach
                    </div>
                </div>
            @endif


            {{-- ══════════════════════════════════════════════
                 ③ FULL-DAY OI + VOLUME in one row
            ══════════════════════════════════════════════ --}}
            <div class="px-6 mb-6">
                <h2 class="text-[10px] font-bold text-gray-100 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <span class="w-3 h-px bg-gray-700 inline-block"></span>
                    Full-Day Overview
                    <span class="flex-1 h-px bg-gray-800 inline-block"></span>
                </h2>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

                    {{-- OI Chart --}}
                    <div class="bg-gray-900 border border-gray-700 rounded-2xl shadow-lg ring-1 ring-white/5 p-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-[10px] font-bold text-gray-100 uppercase tracking-widest flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-sky-500 inline-block"></span>
                                Open Interest · Full Day
                            </h3>
                            <span class="text-[9px] text-gray-100 bg-gray-800 px-2 py-0.5 rounded ring-1 ring-gray-700">
                    CE vs PE
                </span>
                        </div>
                        <div class="relative h-56">
                            <canvas id="oiChart"></canvas>
                        </div>
                    </div>

                    {{-- Volume Chart --}}
                    <div class="bg-gray-900 border border-gray-700 rounded-2xl shadow-lg ring-1 ring-white/5 p-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-[10px] font-bold text-gray-100 uppercase tracking-widest flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-purple-500 inline-block"></span>
                                Volume · Full Day
                            </h3>
                            <span class="text-[9px] text-gray-100 bg-gray-800 px-2 py-0.5 rounded ring-1 ring-gray-700">
                    CE vs PE
                </span>
                        </div>
                        <div class="relative h-56">
                            <canvas id="volChart"></canvas>
                        </div>
                    </div>

                </div>
            </div>

            {{-- ══════════════════════════════════════════════
                 ④ BUILD-UP CARDS — CE then PE
            ══════════════════════════════════════════════ --}}
            <div class="px-6 pb-8">
                <h2 class="text-[10px] font-bold text-gray-100 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <span class="w-3 h-px bg-gray-700 inline-block"></span>
                    Build-Up Breakdown
                    <span class="flex-1 h-px bg-gray-800 inline-block"></span>
                </h2>

                <div class="flex flex-col gap-6">
                    @foreach ([
                        'CE' => ['badge' => 'text-sky-300 border-sky-600 bg-sky-500/10',   'bull' => ['Long Build','Short Cover']],
                        'PE' => ['badge' => 'text-pink-300 border-pink-600 bg-pink-500/10', 'bull' => ['Short Build','Long Unwind']],
                    ] as $type => $meta)

                        <div>
                            {{-- Type header --}}
                            <div class="flex items-center gap-3 mb-3">
                <span class="px-3 py-0.5 rounded-full text-xs font-bold border
                             {{ $meta['badge'] }} uppercase tracking-widest">
                    {{ $type }}
                </span>
                                <div class="flex-1 h-px bg-gray-800"></div>
                                <span class="text-[10px] text-gray-100 bg-gray-900 px-2 py-1 rounded
                             ring-1 ring-gray-800 font-medium">
                    @if ($type === 'CE')
                                        Bullish: Long Build, Short Cover &nbsp;·&nbsp; Bearish: Short Build, Long Unwind
                                    @else
                                        Bullish: Short Build, Long Unwind &nbsp;·&nbsp; Bearish: Long Build, Short Cover
                                    @endif
                </span>
                            </div>

                            {{-- 4 Cards --}}
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                                @php
                                    $cardMeta = [
                                        'Long Build'  => ['icon' => '▲', 'desc' => 'OI↑ LTP↑'],
                                        'Short Build' => ['icon' => '▼', 'desc' => 'OI↑ LTP↓'],
                                        'Short Cover' => ['icon' => '↑', 'desc' => 'OI↓ LTP↑'],
                                        'Long Unwind' => ['icon' => '↓', 'desc' => 'OI↓ LTP↓'],
                                    ];
                                @endphp
                                @foreach ($buildUpTotals[$type] as $label => $vals)
                                    @php
                                        $isBull = in_array($label, $meta['bull']);
                                        $cc = $isBull
                                            ? ['border'=>'border-emerald-700/60','bg'=>'bg-emerald-950/50',
                                               'text'=>'text-emerald-300','badge'=>'bg-emerald-900/60 text-emerald-400 ring-1 ring-emerald-700/50',
                                               'sub'=>'bg-gray-950/80']
                                            : ['border'=>'border-red-700/60','bg'=>'bg-red-950/50',
                                               'text'=>'text-red-300','badge'=>'bg-red-900/60 text-red-400 ring-1 ring-red-700/50',
                                               'sub'=>'bg-gray-950/80'];
                                    @endphp
                                    <div class="rounded-xl border {{ $cc['border'] }} {{ $cc['bg'] }}
                                shadow-md ring-1 ring-white/5 p-3 flex flex-col gap-2.5">
                                        {{-- Card header --}}
                                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold {{ $cc['text'] }}">
                                {{ $cardMeta[$label]['icon'] }} {{ $label }}
                            </span>
                                            <span class="text-[10px] px-1.5 py-0.5 rounded {{ $cc['badge'] }} font-mono">
                                {{ $cardMeta[$label]['desc'] }}
                            </span>
                                        </div>
                                        {{-- OI + Vol --}}
                                        <div class="grid grid-cols-2 gap-1.5 text-center">
                                            <div class="{{ $cc['sub'] }} rounded-lg py-2 ring-1 ring-white/5">
                                                <div class="text-[9px] text-gray-100 uppercase tracking-wider mb-0.5">OI</div>
                                                <div class="text-sm font-bold {{ $cc['text'] }}">
                                                    {{ format_inr_compact($vals['oi']) }}
                                                </div>
                                            </div>
                                            <div class="{{ $cc['sub'] }} rounded-lg py-2 ring-1 ring-white/5">
                                                <div class="text-[9px] text-gray-100 uppercase tracking-wider mb-0.5">Vol</div>
                                                <div class="text-sm font-bold {{ $cc['text'] }}">
                                                    {{ format_inr_compact($vals['volume']) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                    @endforeach
                </div>
            </div>

        @endisset

    </div><!-- /min-h-screen -->

    {{-- ══════════════════════════════════════════════
         CHART.JS
    ══════════════════════════════════════════════ --}}
    @isset($chartLabels)
        @if(count($chartLabels))
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                const chartLabels = @json($chartLabels);
                const ceOI   = @json($chartCE_OI);
                const peOI   = @json($chartPE_OI);
                const ceVol  = @json($chartCE_Vol);
                const peVol  = @json($chartPE_Vol);

                // ── Shared chart options ─────────────────────────────────
                function makeOptions(minY = 0) {
                    return {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 500, easing: 'easeOutQuart' },
                        plugins: {
                            legend: {
                                display: true,
                                labels: { color: '#64748b', font: { size: 10 }, boxWidth: 10, padding: 12 },
                            },
                            tooltip: {
                                backgroundColor: 'rgba(2,6,23,0.96)',
                                borderColor: 'rgba(255,255,255,0.07)',
                                borderWidth: 1,
                                titleColor: '#64748b',
                                bodyColor: '#e2e8f0',
                                padding: 10,
                                callbacks: {
                                    label: ctx =>
                                        ' ' + ctx.dataset.label + ': ' +
                                        ctx.parsed.y.toLocaleString('en-IN'),
                                },
                            },
                        },
                        scales: {
                            x: {
                                ticks: { color: '#475569', font: { size: 10 } },
                                grid:  { color: 'rgba(255,255,255,0.03)' },
                                border:{ color: 'rgba(255,255,255,0.05)' },
                            },
                            y: {
                                min: minY,
                                ticks: {
                                    color: '#475569',
                                    font: { size: 9 },
                                    callback: v =>
                                        v >= 1e7 ? (v/1e7).toFixed(1)+'Cr' :
                                            v >= 1e5 ? (v/1e5).toFixed(1)+'L'  :
                                                v >= 1e3 ? (v/1e3).toFixed(0)+'K'  : v,
                                },
                                grid:  { color: 'rgba(255,255,255,0.03)' },
                                border:{ color: 'rgba(255,255,255,0.05)' },
                            },
                        },
                    };
                }

                // ── Dataset factories ────────────────────────────────────
                const ceDs = (data, label) => ({
                    label, data,
                    backgroundColor: 'rgba(56,189,248,0.60)',
                    borderColor:     'rgba(56,189,248,0.90)',
                    borderWidth: 1.5, borderRadius: 5, borderSkipped: false,
                });
                const peDs = (data, label) => ({
                    label, data,
                    backgroundColor: 'rgba(244,114,182,0.60)',
                    borderColor:     'rgba(244,114,182,0.90)',
                    borderWidth: 1.5, borderRadius: 5, borderSkipped: false,
                });

                // ── Full-day charts ──────────────────────────────────────
                new Chart(document.getElementById('oiChart'), {
                    type: 'bar',
                    data: { labels: chartLabels, datasets: [ceDs(ceOI,'CE OI'), peDs(peOI,'PE OI')] },
                    options: makeOptions(),
                });
                new Chart(document.getElementById('volChart'), {
                    type: 'bar',
                    data: { labels: chartLabels, datasets: [ceDs(ceVol,'CE Volume'), peDs(peVol,'PE Volume')] },
                    options: makeOptions(),
                });

                // ── Window charts ────────────────────────────────────────
                @if(!empty($recentWindows))
                @foreach ($recentWindows as $windowLabel => $w)
                @php $cid = 'w_' . Str::slug($windowLabel); @endphp

                new Chart(document.getElementById('{{ $cid }}_oi'), {
                    type: 'bar',
                    data: {
                        labels: chartLabels,
                        datasets: [
                            ceDs(@json(array_values($w['ce_oi'])), 'CE OI'),
                            peDs(@json(array_values($w['pe_oi'])), 'PE OI'),
                        ],
                    },
                    options: (() => {
                        const o = makeOptions();
                        o.plugins.legend.display = false;
                        return o;
                    })(),
                });

                new Chart(document.getElementById('{{ $cid }}_vol'), {
                    type: 'bar',
                    data: {
                        labels: chartLabels,
                        datasets: [
                            ceDs(@json(array_values($w['ce_vol'])), 'CE Vol'),
                            peDs(@json(array_values($w['pe_vol'])), 'PE Vol'),
                        ],
                    },
                    options: (() => {
                        const o = makeOptions();
                        o.plugins.legend.display = false;
                        return o;
                    })(),
                });
                @endforeach
                @endif

            </script>
        @endif
    @endisset

@endsection
