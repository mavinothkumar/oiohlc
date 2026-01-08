@extends('layouts.app')

@section('content')
    <div class="max-w-7xl mx-auto px-4 py-6">
        <h1 class="text-2xl font-semibold text-gray-900 mb-6">
            OI Buildup Scanner
        </h1>

        {{-- Filters --}}
        <form method="GET" action="{{ route('oi-buildup.index') }}" class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-4 gap-4 mb-4">
{{--            <div>--}}
{{--                <label class="block text-sm font-medium text-gray-700">Underlying</label>--}}
{{--                <input type="text" name="underlying_symbol"--}}
{{--                    value="{{ $filters['underlying_symbol'] }}"--}}
{{--                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">--}}
{{--            </div>--}}

            <div>
                <label class="block text-sm font-medium text-gray-700">Expiry</label>
                <input type="date" name="expiry"
                    value="{{ $filters['expiry'] }}"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">At</label>
                <input
                    type="datetime-local"
                    name="at"
                    value="{{ $filters['at']
        ? \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $filters['at'])->format('Y-m-d\TH:i')
        : now()->format('Y-m-d\TH:i') }}"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                />
            </div>


            <div>
                <label class="block text-sm font-medium text-gray-700">Top N</label>
                <input type="number" name="limit" min="1" max="100"
                    value="{{ $filters['limit'] }}"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
            </div>

            <div class="md:col-span-3 lg:col-span-6 flex items-end justify-end">
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Apply Filters
                </button>
            </div>
        </form>

        @isset($no_filter)
            No Proper filter
        @endif

        {{-- Results --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
            @foreach([5, 10, 15, 30] as $i)
                <div class="bg-white shadow rounded-lg p-4 flex flex-col">
                    <div class="flex items-center justify-between mb-2">
                        <h2 class="text-sm font-semibold text-gray-800">
                            OI Buildup {{ $i }} min
                        </h2>
                        <span class="text-xs text-gray-500">
                    Top {{ $filters['limit'] }}
                </span>
                    </div>

                    <div class="flex-1">
                        @php $rows = $datasets[$i] ?? []; @endphp

                        @if(empty($rows))
                            <p class="text-xs text-gray-400 italic">
                                No data for this interval.
                            </p>
                        @else
                            <div id="chart-{{ $i }}" class="h-64"></div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>


    </div>

    <script>
        window.oiBuildupData = @json($datasets);
    </script>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        (function () {
            const datasets = window.oiBuildupData || {};

            const colorByType = {
                Long:   '#16a34a', // green 600
                Short:  '#dc2626', // red 600
                Cover:  '#1d4ed8', // blue 700 (navy-ish)
                Unwind: '#facc15', // yellow 400
                Neutral: '#6b7280'
            };

            [5, 10, 15, 30].forEach(interval => {
                const rows = datasets[interval] || [];
                if (!rows.length) {
                    return;
                }

                const categories = rows.map(r => `${r.strike} ${r.instrument_type}`);

// use absolute value so everything is plotted to the right
                const values     = rows.map(r => Math.abs(r.delta_oi));

                const colors     = rows.map(r => colorByType[r.buildup] || colorByType.Neutral);

                const options = {
                    chart: {
                        type: 'bar',
                        height: 260,
                        toolbar: { show: false }
                    },
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            distributed: true, // color each bar individually
                            barHeight: '70%'
                        }
                    },
                    dataLabels: {
                        enabled: false
                    },
                    xaxis: {
                        categories: categories,
                        labels: {
                            formatter: val => Number(val).toLocaleString()
                        },
                        title: {
                            text: 'ΔOI'
                        }
                    },
                    yaxis: {
                        labels: {
                            style: { fontSize: '10px' }
                        }
                    },
                    series: [{
                        name: 'ΔOI',
                        data: values
                    }],
                    colors: colors,
                    tooltip: {
                        y: {
                            formatter: (val, opts) => {
                                const row = rows[opts.dataPointIndex];
                                const signed = row.delta_oi; // original signed value
                                return [
                                    `ΔOI: ${signed.toLocaleString()}`,
                                    `ΔPx: ${row.delta_price.toFixed(2)}`,
                                    `Type: ${row.buildup}`
                                ].join(' | ');
                            }
                        }
                    },
                    grid: {
                        borderColor: '#e5e7eb'
                    }
                };

                const el = document.querySelector(`#chart-${interval}`);
                if (el) {
                    const chart = new ApexCharts(el, options);
                    chart.render();
                }
            });
        })();
    </script>

@endsection
