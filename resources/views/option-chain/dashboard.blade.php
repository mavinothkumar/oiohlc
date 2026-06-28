@extends('layouts.app')

@section('title')
    HLC
@endsection

@section('content')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <body class="bg-gray-900 text-gray-100 min-h-screen font-sans">

    <div class="container mx-auto p-4 space-y-6">
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center bg-gray-800 p-4 rounded-xl border border-gray-700 gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-white">Intraday Option Chain Scanner</h1>
                <p class="text-sm text-gray-400">Target Interval Monitoring: 09:15 - 10:15 Momentum Capture</p>
            </div>
            <div class="bg-gray-950 px-4 py-2 rounded-lg border border-gray-800 text-center">
                <span class="text-xs text-gray-500 uppercase font-semibold">Underlying Spot Price</span>
                <div class="text-xl font-mono text-emerald-400 font-bold">₹{{ number_format($spotPrice, 2) }}</div>
            </div>
        </header>

        <form method="GET" action="{{ route('options.analysis') }}" class="bg-gray-800 p-4 rounded-xl border border-gray-700 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
            <div>
                <label class="block text-xs font-semibold text-gray-400 mb-1">Trading Date</label>
                <input type="date" name="date" value="{{ $selectedDate }}"
                    class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-400 mb-1">Expiry Date</label>
                <input type="date" name="expiry" value="{{ $selectedExpiry }}"
                    class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-400 mb-1">Start Observation</label>
                <input type="time" name="start_time" value="{{ $startTime }}"
                    class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-400 mb-1">End Observation</label>
                <input type="time" name="end_time" value="{{ $endTime }}"
                    class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 transition-colors py-2 px-4 rounded-lg font-bold text-white shadow-md">
                    Filter & Analyze
                </button>
            </div>
        </form>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-gray-800 p-4 rounded-xl border border-gray-700">
                <h3 class="text-md font-bold text-gray-200 mb-3">Dominant OI Concentration Walls</h3>
                <div id="oiBarChart"></div>
            </div>

            <div class="bg-gray-800 p-4 rounded-xl border border-gray-700">
                <h3 class="text-md font-bold text-gray-200 mb-3">Recent Net Change Additions (Δ OI)</h3>
                <div id="oiDeltaChart"></div>
            </div>
        </div>

        <div class="bg-gray-800 p-5 rounded-xl border border-gray-700 overflow-hidden">
            <div class="mb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                <div>
                    <h3 class="text-md font-bold text-gray-200">Continuous Buildup Timeline Matrix</h3>
                    <p class="text-xs text-gray-400">Scan columns downwards to identify continuous matching blocks between 09:30 and 10:00.</p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs font-medium">
                    <span class="px-2 py-1 bg-emerald-950 text-emerald-400 rounded border border-emerald-800">Long Build</span>
                    <span class="px-2 py-1 bg-rose-950 text-rose-400 rounded border border-rose-800">Short Build</span>
                    <span class="px-2 py-1 bg-teal-950 text-teal-300 rounded border border-teal-800">Short Cover</span>
                    <span class="px-2 py-1 bg-amber-950 text-amber-400 rounded border border-amber-800">Long Unwind</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[800px] border-collapse text-xs font-mono text-center">
                    <thead>
                    <tr class="bg-gray-900 text-gray-400 border-b border-gray-700">
                        <th class="p-2 text-left sticky left-0 bg-gray-900 z-10 w-24">Strike Price</th>
                        <th class="p-2 border-r border-gray-800 w-12">Type</th>
                        @foreach($timeSeries as $time)
                            <th class="p-2 min-w-[55px]">{{ $time }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach(array_reverse($strikes) as $strike)
                        @foreach(['CE', 'PE'] as $type)
                            <tr class="border-b border-gray-800 hover:bg-gray-750 transition-colors">
                                @if($loop->first)
                                    <td rowspan="2" class="p-2 text-left font-bold text-indigo-300 bg-gray-900 sticky left-0 border-r border-gray-700">
                                        {{ $strike }}
                                    </td>
                                @endif
                                <td class="p-1 font-bold border-r border-gray-800 {{ $type === 'CE' ? 'text-orange-400' : 'text-blue-400' }}">
                                    {{ $type }}
                                </td>
                                @foreach($timeSeries as $time)
                                    @php
                                        $cell = $matrix[$strike][$type][$time] ?? null;
                                        $buildup = $cell['build_up'] ?? '';
                                        $colorClass = 'bg-gray-900 text-gray-600';

                                        if($buildup == 'Long Build') $colorClass = 'bg-emerald-950 text-emerald-400 font-bold border border-emerald-800/40';
                                        if($buildup == 'Short Build') $colorClass = 'bg-rose-950 text-rose-400 font-bold border border-rose-800/40';
                                        if($buildup == 'Short Cover') $colorClass = 'bg-teal-950 text-teal-300 font-bold border border-teal-700';
                                        if($buildup == 'Long Unwind') $colorClass = 'bg-amber-950 text-amber-500 border border-amber-800/40';
                                    @endphp
                                    <td class="p-1 p-2 {{ $colorClass }}" title="Strike: {{ $strike }} | Time: {{ $time }} | Buildup: {{ $buildup ?: 'No Data' }}">
                                        @if($buildup == 'Long Build')
                                            LB
                                        @elseif($buildup == 'Short Build')
                                            SB
                                        @elseif($buildup == 'Short Cover')
                                            SC
                                        @elseif($buildup == 'Long Unwind')
                                            LU
                                        @else
                                            -
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const strikesData = @json($strikes);
        const timeLabels = @json($timeSeries);
        const completeMatrix = @json($matrix);

        // Compute Latest Context Values for standard charts
        const latestTime = timeLabels[ timeLabels.length - 1 ];

        let ceOIValues = [], peOIValues = [];
        let ceDeltaValues = [], peDeltaValues = [];

        strikesData.forEach(strike => {
            // Extraction logic for Total Cumulative Open Interest
            ceOIValues.push(completeMatrix[ strike ]?.[ 'CE' ]?.[ latestTime ]?.[ 'oi' ] || 0);
            peOIValues.push(completeMatrix[ strike ]?.[ 'PE' ]?.[ latestTime ]?.[ 'oi' ] || 0);

            // Extraction logic for Recent Delta Intraday Changes
            ceDeltaValues.push(completeMatrix[ strike ]?.[ 'CE' ]?.[ latestTime ]?.[ 'diff_oi' ] || 0);
            peDeltaValues.push(completeMatrix[ strike ]?.[ 'PE' ]?.[ latestTime ]?.[ 'diff_oi' ] || 0);
        });

        // 1. Total OI Wall Presentation Initialization
        new ApexCharts(document.querySelector('#oiBarChart'), {
            series: [{ name: 'Call OI (CE)', data: ceOIValues }, { name: 'Put OI (PE)', data: peOIValues }],
            chart: { type: 'bar', height: 280, toolbar: { show: false }, background: 'transparent' },
            colors: ['#f97316', '#3b82f6'],
            theme: { mode: 'dark' },
            plotOptions: { bar: { horizontal: false, columnWidth: '55%' } },
            dataLabels: { enabled: false },
            xaxis: { categories: strikesData, title: { text: 'Strike Prices' } },
            yaxis: { title: { text: 'Contracts Outstanding' } }
        }).render();

        // 2. Net Incremental Difference Chart Initialization
        new ApexCharts(document.querySelector('#oiDeltaChart'), {
            series: [{ name: 'CE Change', data: ceDeltaValues }, { name: 'PE Change', data: peDeltaValues }],
            chart: { type: 'bar', height: 280, toolbar: { show: false }, background: 'transparent' },
            colors: ['#ea580c', '#2563eb'],
            theme: { mode: 'dark' },
            plotOptions: { bar: { horizontal: false, colors: { ranges: [{ from: -10000000, to: 0, color: '#ef4444' }] } } },
            dataLabels: { enabled: false },
            xaxis: { categories: strikesData },
            yaxis: { title: { text: 'Net Difference Change' } }
        }).render();
    </script>
@endsection
