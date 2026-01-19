@extends('layouts.app')

@section('title')
    OI Builup
@endsection
{{--{{ $filters['at']--}}
{{--                ? \Carbon\Carbon::parse($filters['at'])->format('Y-m-d\TH:i')--}}
{{--                : now()->format('Y-m-d\TH:i') }}--}}
@section('content')

    @if(!empty($datasets[3]))
        @php
            $threshold = $oiThreshold ?? 1500000; // fallback
            $triggerRows = collect($datasets[3])->filter(function ($row) use ($threshold) {
                return abs($row['delta_oi']) >= $threshold;
            });
        @endphp

        @if($triggerRows->isNotEmpty())
            {{-- Hidden element to pass data to JS --}}
            <div id="oi-alert-data"
                data-threshold="{{ $threshold }}"
                data-count="{{ $triggerRows->count() }}"
                data-details='@json($triggerRows->values())'>
            </div>
        @endif
    @endif


    <div class="max-w-full mx-auto px-4">
        {{-- Filters --}}
        <form method="GET" action="{{ route('oi-buildup.live') }}" class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-4 gap-4 mb-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 mb-6">
                    OI Live Buildup ({{ $filters['date'] }})
                </h1>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">At</label>
                <input
                    id="at_input"
                    type="datetime-local"
                    name="at"
                    value=""
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                />
            </div>


            <div>
                <label class="block text-sm font-medium text-gray-700">Top N</label>
                <input type="number" name="limit" min="1" max="100"
                    value="{{ $filters['limit'] }}"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
            </div>

            <div>
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
        <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-3 gap-2">
            @foreach([15, 3, 6,9, 30, 375] as $i)
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
                            <div id="chart-{{ $i }}" class="h-75"></div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>


    </div>

    {{-- Simple popup modal --}}
    <div id="oiAlertModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-4">
            <div class="flex justify-between items-center mb-2">
                <h2 class="text-lg font-semibold">OI Buildup Alert</h2>
                <button id="oiAlertClose" class="text-gray-500 hover:text-gray-800">&times;</button>
            </div>
            <div id="oiAlertContent" class="text-sm text-gray-800 space-y-2">
                {{-- Filled by JS --}}
            </div>
        </div>
    </div>

    <audio id="oiAlertSound">
        <source src="{{ asset('sounds/beep.mp3') }}" type="audio/mpeg">
    </audio>


    <script>
        window.oiBuildupData = @json($datasets);
    </script>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        ( function () {
            const datasets = window.oiBuildupData || {};

            // Indian number formatter (K/L/C)
            function formatIndianNumber (num) {
                const n = Math.abs(num);
                if (n >= 1e7) {           // crore
                    return ( num / 1e7 ).toFixed(1).replace(/\.0$/, '') + ' C';
                } else if (n >= 1e5) {    // lakh
                    return ( num / 1e5 ).toFixed(1).replace(/\.0$/, '') + ' L';
                } else if (n >= 1e3) {    // thousand -> full value
                    return Math.round(num).toString();
                }
                return num.toString();
            }

            const colorByType = {
                Long: '#16a34a', // green 600
                Short: '#dc2626', // red 600
                Cover: '#0d2a7c', // blue 700 (navy-ish)
                Unwind: '#facc15', // yellow 400
                Neutral: '#6b7280'
            };

            [3, 6, 9, 15, 30, 375].forEach(interval => {
                const rows = datasets[ interval ] || [];
                if ( ! rows.length) {
                    return;
                }

                const categories = rows.map(r => `${ parseInt(r.strike) } ${ r.option_type }`);

// use absolute value so everything is plotted to the right
                const values = rows.map(r => Math.abs(r.delta_oi));

                const colors = rows.map(r => colorByType[ r.buildup ] || colorByType.Neutral);

                const options = {
                    chart: {
                        type: 'bar',
                        height: 350,
                        toolbar: { show: true }
                    },
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            distributed: true, // color each bar individually
                            barHeight: '70%'
                        }
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: (val) => formatIndianNumber(val)  // Inside bar labels
                    },
                    xaxis: {
                        categories: categories,
                        labels: {
                            formatter: (val) => formatIndianNumber(val)  // Axis scale
                        },
                        title: {
                            text: 'ΔOI'
                        }
                    },
                    yaxis: {
                        labels: {
                            style: { fontSize: '14px', fontWeight: 'bold' }
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
                                const row = rows[ opts.dataPointIndex ];
                                const signed = row.delta_oi;
                                return [
                                    `ΔOI: ${ signed }`,
                                    `ΔPx: ${ row.delta_price.toFixed(2) }`,
                                    `Type: ${ row.buildup }`
                                ].join(' | ');
                            }
                        }
                    },
                    grid: {
                        borderColor: '#e5e7eb'
                    }
                };

                const el = document.querySelector(`#chart-${ interval }`);
                if (el) {
                    const chart = new ApexCharts(el, options);
                    chart.render();
                }
            });
        } )();
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const alertDataEl = document.getElementById('oi-alert-data');
            if (!alertDataEl) {
                return;
            }

            const threshold = parseInt(alertDataEl.dataset.threshold, 10);
            const rows = JSON.parse(alertDataEl.dataset.details || '[]');

            if (!rows.length) {
                return;
            }

            const modal = document.getElementById('oiAlertModal');
            const closeBtn = document.getElementById('oiAlertClose');
            const contentEl = document.getElementById('oiAlertContent');
            const audio = document.getElementById('oiAlertSound');

            console.log(rows);

            // Build details HTML
            let html = `<p>Found ${rows.length} contracts with |ΔOI| ≥ ${threshold.toLocaleString()} in 3‑minute data.</p>`;
            html += '<ul class="list-disc pl-5 space-y-1">';
            rows.slice(0, 5).forEach(r => {
                html += `<li>
            <span class="font-semibold">${r.strike} ${r.option_type}</span>
            &nbsp;(${r.buildup}) |
            ΔOI: ${r.delta_oi.toLocaleString()} |
            ΔPrice: ${r.delta_price}
        </li>`;
            });
            html += '</ul>';

            contentEl.innerHTML = html;

            // Show modal
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            // Play sound (may be blocked until user interacts at least once)
            if (audio) {
                audio.play().catch(() => {});
            }

            const hideModal = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            };

            closeBtn.addEventListener('click', hideModal);
            modal.addEventListener('click', function (e) {
                if (e.target === modal) hideModal();
            });
        });
    </script>

@endsection
