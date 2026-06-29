@extends('layouts.app')

@section('title')
    HLC
@endsection

@section('content')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <body class="bg-gray-900 text-gray-100 min-h-screen font-sans">

    <div class="mx-auto p-4 space-y-6">
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

        <div class="bg-gray-800 p-5 rounded-xl border border-gray-700">
            <div class="mb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                <div>
                    <h3 class="text-md font-bold text-gray-200">Convergent Option Chain Buildup Matrix</h3>
                </div>
                <div class="flex flex-wrap gap-2 text-xs font-medium">
                    <span class="px-2 py-1 bg-emerald-600 text-white rounded shadow-sm">Long Build</span>
                    <span class="px-2 py-1 bg-rose-600 text-white rounded shadow-sm">Short Build</span>
                    <span class="px-2 py-1 bg-teal-500 text-white rounded shadow-sm">Short Cover</span>
                    <span class="px-2 py-1 bg-amber-500 text-white rounded shadow-sm">Long Unwind</span>
                </div>
            </div>

            <div class="grid grid-cols-[1fr_112px_1fr] text-center font-bold text-xs tracking-wider uppercase mb-1">
                <div class="text-orange-400 bg-orange-950/30 py-1.5 rounded-l-lg border-l border-y border-orange-900/50">Calls (CE) Momentum</div>
                <div class="text-indigo-400 bg-gray-900 py-1.5 border-y border-gray-700">Axis</div>
                <div class="text-blue-400 bg-blue-950/30 py-1.5 rounded-r-lg border-r border-y border-blue-900/50">Puts (PE) Momentum</div>
            </div>

            <div class="grid grid-cols-[1fr_112px_1fr] bg-gray-950 rounded-xl border border-gray-700 overflow-hidden shadow-2xl">

                <div id="ce-scroll-container" class="overflow-x-auto scrollbar-thin border-r border-gray-800 select-none flex justify-end">
                    <table class="w-max border-collapse text-[11px] font-mono text-center table-fixed">
                        <thead>
                        <tr class="bg-gray-900/80 text-gray-400 border-b border-gray-800 h-10">
                            @foreach($timeSeries as $time)
                                <th class="p-1 w-16 min-w-[64px] max-w-[64px] border-r border-gray-800/30 font-semibold tracking-tight whitespace-nowrap text-center">
                                    {{ $time }}
                                </th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody>
                        @foreach(($strikes) as $strike)
                            <tr class="border-b border-gray-900 hover:bg-gray-800/40 h-10 transition-colors">
                                @foreach($timeSeries as $time)
                                    @php
                                        $cell = $matrix[$strike]['CE'][$time] ?? null;
                                        $buildup = $cell['build_up'] ?? '';
                                        $rawValue = $cell['oi'] ?? 0;
                                        $formattedValue = $rawValue != 0 ? round($rawValue / 100000, 1) : '-';

                                        $colorClass = 'bg-gray-950 text-gray-600';
                                        if($buildup == 'Long Build')  $colorClass = 'bg-emerald-600 text-white font-bold border border-gray-200';
                                        if($buildup == 'Short Build') $colorClass = 'bg-rose-600 text-white font-bold border border-gray-200';
                                        if($buildup == 'Short Cover') $colorClass = 'bg-blue-900 text-white font-bold border border-gray-200';
                                        if($buildup == 'Long Unwind') $colorClass = 'bg-amber-500 text-white font-bold border border-gray-200';
                                    @endphp
                                    <td class="p-1 w-16 min-w-[64px] max-w-[64px] {{ $colorClass }} whitespace-nowrap border-r border-gray-900/40 text-center"
                                        title="CE | Strike: {{ $strike }} | Time: {{ $time }} | Buildup: {{ $buildup ?: 'None' }} | Raw: {{ $rawValue }}">
                                        {{ $formattedValue }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="w-[112px] min-w-[112px] max-w-[112px] bg-gray-900 z-10 text-center border-r border-gray-800 shadow-[0_0_15px_rgba(0,0,0,0.7)]">
                    <table class="w-full border-collapse text-xs font-mono text-center table-fixed">
                        <thead>
                        <tr class="bg-gray-950 text-indigo-400 font-bold border-b border-gray-800 h-10">
                            <th class="p-1 tracking-wider text-center">STRIKE</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach(($strikes) as $strike)
                            <tr class="h-10 flex items-center justify-center {{$trend->atm_index_open ===$strike ? 'border-b border-white bg-white text-black-400' : 'border-b border-gray-800/60 bg-gray-900/90'}}">
                                <td class="p-1 font-extrabold text-indigo-300 flex items-center justify-center h-full w-full text-center">
                                    {{ (int) $strike }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div id="pe-scroll-container" class="overflow-x-auto scrollbar-thin select-none">
                    @php $reversedTimeSeries = array_reverse($timeSeries); @endphp
                    <table class="w-max border-collapse text-[11px] font-mono text-center table-fixed">
                        <thead>
                        <tr class="bg-gray-900/80 text-gray-400 border-b border-gray-800 h-10">
                            @foreach($reversedTimeSeries as $time)
                                <th class="p-1 w-16 min-w-[64px] max-w-[64px] border-r border-gray-800/30 font-semibold tracking-tight whitespace-nowrap text-center">
                                    {{ $time }}
                                </th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody>
                        @foreach(($strikes) as $strike)
                            <tr class="border-b border-gray-900 hover:bg-gray-800/40 h-10 transition-colors">
                                @foreach($reversedTimeSeries as $time)
                                    @php
                                        $cell = $matrix[$strike]['PE'][$time] ?? null;
                                        $buildup = $cell['build_up'] ?? '';
                                        $rawValue = $cell['oi'] ?? 0;
                                        $formattedValue = $rawValue != 0 ? round($rawValue / 100000, 1) : '-';

                                        $colorClass = 'bg-gray-950 text-gray-600';
                                        if($buildup == 'Long Build')  $colorClass = 'bg-emerald-600 text-white font-bold border border-gray-200';
                                        if($buildup == 'Short Build') $colorClass = 'bg-rose-600 text-white font-bold border border-gray-200';
                                        if($buildup == 'Short Cover') $colorClass = 'bg-blue-900 text-white font-bold border border-gray-200';
                                        if($buildup == 'Long Unwind') $colorClass = 'bg-amber-500 text-white font-bold border border-gray-200';
                                    @endphp
                                    <td class="p-1 w-16 min-w-[64px] max-w-[64px] {{ $colorClass }} whitespace-nowrap border-r border-gray-900/40 text-center"
                                        title="PE | Strike: {{ $strike }} | Time: {{ $time }} | Buildup: {{ $buildup ?: 'None' }} | Raw: {{ $rawValue }}">
                                        {{ $formattedValue }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

            </div>
        </div>


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

        document.addEventListener("DOMContentLoaded", () => {
            const ceContainer = document.getElementById('ce-scroll-container');
            if (ceContainer) {
                // Automatically scroll CE layout rightward to anchor recent timelines to the strike axis
                ceContainer.scrollLeft = ceContainer.scrollWidth;
            }
        });
    </script>
@endsection
