@extends('layouts.app')

@section('title')
    Combined Premium Analysis – Multi-Strike
@endsection

@section('content')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <div class="bg-gray-50 text-gray-800 font-sans p-4 md:p-6">
        <div class="w-full mx-auto">
            <h1 class="text-3xl font-bold mb-6">📊 Combined Premium Analysis – Multi-Strike</h1>

            {{-- Filter Form --}}
            <form method="GET" class="bg-white rounded-xl shadow border border-gray-200 p-4 mb-8">
                <div class="flex flex-col lg:flex-row gap-4">
                    {{-- Left Section: Strike Selects (50-60% space) --}}
                    <div class="w-full lg:w-[55%] flex flex-col lg:flex-row gap-4">
                        {{-- Put Strikes --}}
                        <div class="w-full lg:w-1/2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Put Strikes (PE)</label>
                            <select name="put_strikes[]" id="put-strikes" class="w-full border border-gray-300 rounded px-3 py-2 text-sm bg-white" multiple="multiple" style="width: 100%">
                                @foreach($allStrikes as $s)
                                    <option value="{{ $s }}" {{ in_array($s, $putStrikes) ? 'selected' : '' }}>{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Call Strikes --}}
                        <div class="w-full lg:w-1/2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Call Strikes (CE)</label>
                            <select name="call_strikes[]" id="call-strikes" class="w-full border border-gray-300 rounded px-3 py-2 text-sm bg-white" multiple="multiple" style="width: 100%">
                                @foreach($allStrikes as $s)
                                    <option value="{{ $s }}" {{ in_array($s, $callStrikes) ? 'selected' : '' }}>{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Right Section: Other Filters (40-45% space) --}}
                    <div class="w-full lg:w-[45%] flex flex-wrap items-end gap-2">
                        {{-- Expiry --}}
                        <div class="flex-1 min-w-[120px]">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Expiry</label>
                            <input type="date" name="expiry" value="{{ $selectedExpiry }}"
                                class="w-full border border-gray-300 rounded px-2 py-2 text-sm bg-white">
                        </div>

                        {{-- Date --}}
                        <div class="flex-1 min-w-[120px]">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Date</label>
                            <input type="date" name="date" value="{{ $selectedDate }}"
                                class="w-full border border-gray-300 rounded px-2 py-2 text-sm bg-white">
                        </div>

                        {{-- Entry Premium --}}
                        <div class="flex-1 min-w-[100px]">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Entry</label>
                            <input type="number" step="0.01" name="enter_price" value="{{ $enterPrice }}"
                                placeholder="120.50"
                                class="w-full border border-gray-300 rounded px-2 py-2 text-sm bg-white">
                        </div>

                        {{-- Chart View --}}
                        <div class="flex-1 min-w-[100px]">
                            <label class="block text-xs font-medium text-gray-600 mb-1">View</label>
                            <select name="chart_view" id="chart-view" class="w-full border border-gray-300 rounded px-2 py-2 text-sm bg-white">
                                <option value="combined" {{ ($chartView ?? 'combined') == 'combined' ? 'selected' : '' }}>Combined</option>
                                <option value="individual" {{ ($chartView ?? 'combined') == 'individual' ? 'selected' : '' }}>Individual</option>
                                <option value="all" {{ ($chartView ?? 'combined') == 'all' ? 'selected' : '' }}>All</option>
                            </select>
                        </div>

                        {{-- Submit Button --}}
                        <div class="flex-none">
                            <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded transition text-sm h-[38px] whitespace-nowrap">
                                Load
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            {{-- Selected Strikes Info --}}
            @if(!empty($putStrikes) || !empty($callStrikes))
                <div class="bg-white rounded-xl shadow border border-gray-200 p-4 mb-6">
                    <div class="flex flex-wrap gap-6">
                        <div>
                            <span class="text-xs text-gray-500">Selected PE Strikes:</span>
                            <div class="flex flex-wrap gap-1 mt-1">
                                @foreach($putStrikes as $strike)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                        {{ $strike }}
                                    </span>
                                @endforeach
                                @if(empty($putStrikes))
                                    <span class="text-gray-400 text-sm">None</span>
                                @endif
                            </div>
                        </div>
                        <div>
                            <span class="text-xs text-gray-500">Selected CE Strikes:</span>
                            <div class="flex flex-wrap gap-1 mt-1">
                                @foreach($callStrikes as $strike)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $strike }}
                                    </span>
                                @endforeach
                                @if(empty($callStrikes))
                                    <span class="text-gray-400 text-sm">None</span>
                                @endif
                            </div>
                        </div>
                        <div>
                            <span class="text-xs text-gray-500">Total Strikes:</span>
                            <span class="font-semibold text-lg">{{ count($putStrikes) + count($callStrikes) }}</span>
                        </div>
                    </div>
                </div>
            @endif

            {{-- No data warning --}}
            @if(!empty($putStrikes) && !empty($callStrikes) && $data->isEmpty())
                <div class="bg-yellow-100 border border-yellow-300 text-yellow-800 p-4 rounded-lg mb-6">
                    No data found for the selected filters.
                </div>
            @endif

            {{-- Charts Section --}}
            @if($data->isNotEmpty())
                {{-- Combined Premium Chart --}}
                <div class="bg-white rounded-xl shadow border border-gray-200 p-6 mb-8">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h2 class="text-xl font-semibold">💵 Premium Chart</h2>
                            <p class="text-sm text-gray-500">
                                @if(($chartView ?? 'combined') == 'combined' || ($chartView ?? 'combined') == 'all')
                                    Total PE + CE LTP. VWAP (orange dashed) and OI-weighted average (purple dashed) shown.
                                    Net OI Change on right axis.
                                @endif
                                @if(($chartView ?? 'combined') == 'individual')
                                    Individual Total PE and Total CE premiums.
                                @endif
                            </p>
                        </div>
                        <div class="text-right">
                            @if(($chartView ?? 'combined') == 'combined' || ($chartView ?? 'combined') == 'all')
                                <div class="text-sm text-gray-600">
                                    <span class="font-semibold">Latest Combined:</span>
                                    <span class="ml-2 text-lg font-bold text-green-600">
                                        ₹{{ number_format($combinedLtp->last() ?? 0, 2) }}
                                    </span>
                                </div>
                                <div class="text-sm text-gray-600">
                                    <span class="font-semibold">Latest VWAP:</span>
                                    <span class="ml-2 text-lg font-bold text-orange-600">
                                        ₹{{ number_format(end($vwap) ?? 0, 2) }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Build-up status (only for combined view) --}}
                    @if(($chartView ?? 'combined') == 'combined' || ($chartView ?? 'combined') == 'all')
                        <div class="text-sm text-gray-600 mb-4">
                            <span class="font-semibold">Put Build-Up:</span>
                            <span class="ml-1 px-2 py-0.5 rounded
                                {{ Str::contains($putBuildUp->last() ?? '', 'Short') ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                {{ $putBuildUp->last() ?? 'N/A' }}
                            </span>
                            <span class="ml-4 font-semibold">Call Build-Up:</span>
                            <span class="ml-1 px-2 py-0.5 rounded
                                {{ Str::contains($callBuildUp->last() ?? '', 'Short') ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                {{ $callBuildUp->last() ?? 'N/A' }}
                            </span>
                        </div>
                    @endif

                    <canvas id="combinedChart" height="80"></canvas>
                </div>

                {{-- Greeks Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Vega --}}
                    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
                        <h2 class="text-lg font-semibold mb-1">📈 Vega (Avg per strike)</h2>
                        <p class="text-xs text-gray-500 mb-4">
                            @if(($chartView ?? 'combined') == 'combined' || ($chartView ?? 'combined') == 'all')
                                Short = negative. Falling IV helps the position.
                                Net Vega shown for combined view.
                            @else
                                Individual PE and CE Vega shown.
                            @endif
                        </p>
                        <canvas id="vegaChart" height="80"></canvas>
                    </div>

                    {{-- Theta --}}
                    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
                        <h2 class="text-lg font-semibold mb-1">⏳ Theta (Avg per strike)</h2>
                        <p class="text-xs text-gray-500 mb-4">
                            @if(($chartView ?? 'combined') == 'combined' || ($chartView ?? 'combined') == 'all')
                                Short = positive. Time decay works in your favour.
                                Net Theta shown for combined view.
                            @else
                                Individual PE and CE Theta shown.
                            @endif
                        </p>
                        <canvas id="thetaChart" height="80"></canvas>
                    </div>

                    {{-- Gamma --}}
                    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
                        <h2 class="text-lg font-semibold mb-1">🎢 Gamma (Avg per strike)</h2>
                        <p class="text-xs text-gray-500 mb-4">
                            @if(($chartView ?? 'combined') == 'combined' || ($chartView ?? 'combined') == 'all')
                                Short = negative. Large moves hurt.
                                Net Gamma shown for combined view.
                            @else
                                Individual PE and CE Gamma shown.
                            @endif
                        </p>
                        <canvas id="gammaChart" height="80"></canvas>
                    </div>

                    {{-- Delta --}}
                    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
                        <h2 class="text-lg font-semibold mb-1">🎯 Delta (Avg per strike)</h2>
                        <p class="text-xs text-gray-500 mb-4">
                            @if(($chartView ?? 'combined') == 'combined' || ($chartView ?? 'combined') == 'all')
                                Should stay near zero for a neutral position.
                                Net Delta shown for combined view.
                            @else
                                Individual PE and CE Delta shown.
                            @endif
                        </p>
                        <canvas id="deltaChart" height="80"></canvas>
                    </div>

                    {{-- IV --}}
                    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
                        <h2 class="text-lg font-semibold mb-1">🌡️ Implied Volatility (Avg)</h2>
                        <p class="text-xs text-gray-500 mb-4">Average IV of selected put and call options.</p>
                        <canvas id="ivChart" height="80"></canvas>
                    </div>

                    {{-- POP --}}
                    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
                        <h2 class="text-lg font-semibold mb-1">🎲 Probability of Profit (Avg %)</h2>
                        <p class="text-xs text-gray-500 mb-4">Average POP of selected put and call options.</p>
                        <canvas id="popChart" height="80"></canvas>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Initialize Select2 for multi-select with better UX
            $('#put-strikes').select2({
                placeholder: 'Select PE strikes...',
                allowClear: true,
                width: '100%',
                closeOnSelect: false,
                templateResult: formatStrike,
                templateSelection: formatStrikeSelection
            });

            $('#call-strikes').select2({
                placeholder: 'Select CE strikes...',
                allowClear: true,
                width: '100%',
                closeOnSelect: false,
                templateResult: formatStrike,
                templateSelection: formatStrikeSelection
            });

            function formatStrike(strike) {
                if (strike.loading) {
                    return strike.text;
                }
                return $('<span>' + strike.text + '</span>');
            }

            function formatStrikeSelection(strike) {
                return strike.text;
            }
        });
    </script>

    @if($data->isNotEmpty())
        <script>
            $(document).ready(function() {
                const chartView = '{{ $chartView ?? 'combined' }}';
                console.log('Chart View:', chartView); // Debug line

                const labels = @json($labels);
                const totalPutLtp = @json($totalPutLtp);
                const totalCallLtp = @json($totalCallLtp);
                const combinedLtp = @json($combinedLtp);
                const vwap = @json($vwap);
                const oiVwap = @json($oiVwap);
                const netOIChange = @json($netOIChange);
                const enterPrice = @json($enterPrice);

                // Common tooltip
                const tooltipOptions = {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        title: function(context) {
                            return 'Time: ' + context[0].label;
                        },
                        label: function(context) {
                            return context.dataset.label + ': ₹' + context.parsed.y.toFixed(2);
                        }
                    }
                };

                // ------------------ Combined Premium Chart ------------------
                function getCombinedPremiumDatasets() {
                    let datasets = [];

                    // Individual PE and CE totals
                    if (chartView === 'individual' || chartView === 'all') {
                        datasets.push({
                            label: 'Total PE Premium',
                            data: totalPutLtp,
                            borderColor: '#ef4444',
                            backgroundColor: 'transparent',
                            tension: 0.2,
                            pointRadius: 0,
                            yAxisID: 'y'
                        });

                        datasets.push({
                            label: 'Total CE Premium',
                            data: totalCallLtp,
                            borderColor: '#3b82f6',
                            backgroundColor: 'transparent',
                            tension: 0.2,
                            pointRadius: 0,
                            yAxisID: 'y'
                        });
                    }

                    // Combined datasets
                    if (chartView === 'combined' || chartView === 'all') {
                        // Combined premium
                        datasets.push({
                            label: 'Combined Premium',
                            data: combinedLtp,
                            borderColor: '#35ec9e',
                            backgroundColor: 'rgba(7,223,60,0.1)',
                            tension: 0.2,
                            fill: true,
                            pointRadius: 0,
                            yAxisID: 'y'
                        });

                        // Entry line
                        if (enterPrice) {
                            datasets.push({
                                label: 'Entry (₹' + enterPrice + ')',
                                data: Array(labels.length).fill(parseFloat(enterPrice)),
                                borderColor: '#10b981',
                                borderWidth: 2,
                                borderDash: [5, 5],
                                pointRadius: 0,
                                fill: false,
                                yAxisID: 'y'
                            });
                        }

                        // VWAP
                        if (vwap && vwap.length > 0) {
                            datasets.push({
                                label: 'VWAP (Vol)',
                                data: vwap,
                                borderColor: '#ff0000',
                                borderWidth: 6,
                                borderDash: [1, 1],
                                pointRadius: 1,
                                fill: false,
                                yAxisID: 'y'
                            });
                        }

                        // OI-VWAP
                        if (oiVwap && oiVwap.length > 0) {
                            datasets.push({
                                label: 'OI-VWAP (Additions)',
                                data: oiVwap,
                                borderColor: '#d5d5d5',
                                fill: false,
                                yAxisID: 'y'
                            });
                        }

                        // Net OI Change
                        if (netOIChange && netOIChange.length > 0) {
                            datasets.push({
                                label: 'Net OI Change',
                                data: netOIChange,
                                borderColor: '#ec4899',
                                borderWidth: 5,
                                pointRadius: 2,
                                fill: false,
                                yAxisID: 'y1'
                            });
                        }
                    }

                    return datasets;
                }

                new Chart(document.getElementById('combinedChart'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: getCombinedPremiumDatasets()
                    },
                    options: {
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            tooltip: tooltipOptions,
                            legend: {
                                labels: { color: '#374151', usePointStyle: true, font: { size: 11 } }
                            }
                        },
                        scales: {
                            x: {
                                ticks: { color: '#6b7280', maxTicksLimit: 10 },
                                grid: { color: '#e5e7eb' }
                            },
                            y: {
                                type: 'linear',
                                position: 'left',
                                title: { display: true, text: 'Premium (₹)' },
                                ticks: { color: '#6b7280' },
                                grid: { color: '#e5e7eb' }
                            },
                            y1: {
                                type: 'linear',
                                position: 'right',
                                title: { display: true, text: 'Net OI Change' },
                                grid: { drawOnChartArea: false },
                                ticks: { color: '#ec4899' },
                                display: (chartView === 'combined' || chartView === 'all') && netOIChange && netOIChange.length > 0
                            }
                        }
                    }
                });

                // ------------------ Helper for Greek charts ------------------
                function threeLineChart(canvasId, peLabel, peData, peColor, ceLabel, ceData, ceColor, netLabel, netData, netColor) {
                    let datasets = [];

                    // Individual datasets
                    if (chartView === 'individual' || chartView === 'all') {
                        datasets.push({
                            label: peLabel,
                            data: peData,
                            borderColor: peColor,
                            backgroundColor: 'transparent',
                            tension: 0.2,
                            pointRadius: 0
                        });
                        datasets.push({
                            label: ceLabel,
                            data: ceData,
                            borderColor: ceColor,
                            backgroundColor: 'transparent',
                            tension: 0.2,
                            pointRadius: 0
                        });
                    }

                    // Combined dataset
                    if (chartView === 'combined' || chartView === 'all') {
                        datasets.push({
                            label: netLabel,
                            data: netData,
                            borderColor: netColor,
                            borderWidth: 2,
                            backgroundColor: 'transparent',
                            tension: 0.2,
                            pointRadius: 0
                        });
                    }

                    // If no datasets (shouldn't happen), add at least one
                    if (datasets.length === 0) {
                        datasets.push({
                            label: 'No Data',
                            data: [],
                            borderColor: '#cccccc',
                            backgroundColor: 'transparent'
                        });
                    }

                    new Chart(document.getElementById(canvasId), {
                        type: 'line',
                        data: { labels: labels, datasets: datasets },
                        options: {
                            responsive: true,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                tooltip: tooltipOptions,
                                legend: { labels: { color: '#374151', usePointStyle: true, font: { size: 11 } } }
                            },
                            scales: {
                                x: { ticks: { color: '#6b7280', maxTicksLimit: 10 }, grid: { color: '#e5e7eb' } },
                                y: { ticks: { color: '#6b7280' }, grid: { color: '#e5e7eb' } }
                            }
                        }
                    });
                }

                threeLineChart('vegaChart',
                    'PE Vega (Avg)', @json($putVega), '#ef4444',
                    'CE Vega (Avg)', @json($callVega), '#3b82f6',
                    'Net Vega (Short)', @json($netVega), '#f97316');

                threeLineChart('thetaChart',
                    'PE Theta (Avg)', @json($putTheta), '#ef4444',
                    'CE Theta (Avg)', @json($callTheta), '#3b82f6',
                    'Net Theta (Short)', @json($netTheta), '#22c55e');

                threeLineChart('gammaChart',
                    'PE Gamma (Avg)', @json($putGamma), '#ef4444',
                    'CE Gamma (Avg)', @json($callGamma), '#3b82f6',
                    'Net Gamma (Short)', @json($netGamma), '#f97316');

                threeLineChart('deltaChart',
                    'PE Delta (Avg)', @json($putDelta), '#ef4444',
                    'CE Delta (Avg)', @json($callDelta), '#3b82f6',
                    'Net Delta (Short)', @json($netDelta), '#8b5cf6');

                // ------------------ Dual line charts (IV & POP) ------------------
                function dualLineChart(canvasId, label1, data1, color1, label2, data2, color2) {
                    new Chart(document.getElementById(canvasId), {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [
                                {
                                    label: label1,
                                    data: data1,
                                    borderColor: color1,
                                    tension: 0.2,
                                    pointRadius: 0
                                },
                                {
                                    label: label2,
                                    data: data2,
                                    borderColor: color2,
                                    tension: 0.2,
                                    pointRadius: 0
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                tooltip: tooltipOptions,
                                legend: { labels: { color: '#374151', usePointStyle: true, font: { size: 11 } } }
                            },
                            scales: {
                                x: { ticks: { color: '#6b7280', maxTicksLimit: 10 }, grid: { color: '#e5e7eb' } },
                                y: { ticks: { color: '#6b7280' }, grid: { color: '#e5e7eb' } }
                            }
                        }
                    });
                }

                dualLineChart('ivChart', 'Put IV (Avg)', @json($putIv), '#a78bfa', 'Call IV (Avg)', @json($callIv), '#f472b6');
                dualLineChart('popChart', 'Put POP % (Avg)', @json($putPop), '#a78bfa', 'Call POP % (Avg)', @json($callPop), '#f472b6');
            });
        </script>
    @endif
@endsection
