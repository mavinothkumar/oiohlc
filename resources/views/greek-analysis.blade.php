@extends('layouts.app')

@section('title')
    HLC
@endsection

@section('content')
    {{-- Include Chart.js and Select2 --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <div class="bg-gray-50 text-gray-800 font-sans p-4 md:p-6">
        {{-- Full‑width container --}}
        <div class="w-full">
            <h1 class="text-3xl font-bold mb-6">📊 Option Greek Monitor – Short Strangle / Straddle</h1>

            {{-- Filter row (single line) --}}
            <form method="GET" class="bg-white rounded-xl shadow border border-gray-200 p-4 mb-8">
                <div class="flex flex-wrap items-end gap-3">
                    {{-- Expiry as date picker --}}
                    <div class="flex flex-col">
                        <label class="text-xs text-gray-600 mb-1">Expiry</label>
                        <input type="date" name="expiry" value="{{ $selectedExpiry }}"
                            class="border border-gray-300 rounded px-3 py-2 text-sm w-36 bg-white">
                    </div>

                    {{-- Date --}}
                    <div class="flex flex-col">
                        <label class="text-xs text-gray-600 mb-1">Date</label>
                        <input type="date" name="date" value="{{ $selectedDate }}"
                            class="border border-gray-300 rounded px-3 py-2 text-sm w-32 bg-white">
                    </div>

                    {{-- Put Strike with search --}}
                    <div class="flex flex-col">
                        <label class="text-xs text-gray-600 mb-1">Put Strike (PE)</label>
                        <select name="put_strike" class="searchable border border-gray-300 rounded px-3 py-2 text-sm w-40 bg-white">
                            <option value="">-- Select --</option>
                            @foreach($strikes as $s)
                                <option value="{{ $s }}" {{ $putStrike == $s ? 'selected' : '' }}>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Call Strike with search --}}
                    <div class="flex flex-col">
                        <label class="text-xs text-gray-600 mb-1">Call Strike (CE)</label>
                        <select name="call_strike" class="searchable border border-gray-300 rounded px-3 py-2 text-sm w-40 bg-white">
                            <option value="">-- Select --</option>
                            @foreach($strikes as $s)
                                <option value="{{ $s }}" {{ $callStrike == $s ? 'selected' : '' }}>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Entry Premium --}}
                    <div class="flex flex-col">
                        <label class="text-xs text-gray-600 mb-1">Entry Premium</label>
                        <input type="number" step="0.01" name="enter_price" value="{{ $enterPrice }}"
                            placeholder="e.g. 120.50"
                            class="border border-gray-300 rounded px-3 py-2 text-sm w-36 bg-white">
                    </div>

                    {{-- Chart View Selector --}}
                    <div class="flex flex-col">
                        <label class="text-xs text-gray-600 mb-1">Chart View</label>
                        <select name="chart_view" class="border border-gray-300 rounded px-3 py-2 text-sm bg-white">
                            <option value="all" {{ $chartView == 'all' ? 'selected' : '' }}>All (Indiv + Combined)</option>
                            <option value="combined_only" {{ $chartView == 'combined_only' ? 'selected' : '' }}>Combined Only</option>
                            <option value="individual_only" {{ $chartView == 'individual_only' ? 'selected' : '' }}>Individual Only</option>
                        </select>
                    </div>

                    {{-- Submit --}}
                    <div class="flex items-end">
                        <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded transition text-sm">
                            Load Data
                        </button>
                    </div>
                </div>
            </form>

            {{-- No data warning --}}
            @if($putStrike && $callStrike && $data->isEmpty())
                <div class="bg-yellow-100 border border-yellow-300 text-yellow-800 p-4 rounded-lg">
                    No data found for the selected filters.
                </div>
            @endif

            {{-- Charts --}}
            @if($data->isNotEmpty())
                {{-- Combined Premium --}}
                <div class="bg-white rounded-xl shadow border border-gray-200 p-6 mb-8">
                    <h2 class="text-xl font-semibold mb-2">💵 Combined Premium</h2>
                    <p class="text-sm text-gray-500 mb-4">PE LTP + CE LTP (based on selected view).</p>
                    <canvas id="combinedChart" height="80"></canvas>
                </div>

                {{-- Greeks grid --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Vega --}}
                    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
                        <h2 class="text-lg font-semibold mb-1">📈 Vega</h2>
                        <p class="text-xs text-gray-500 mb-4">Individual PE/CE Vega (positive) and Net Vega (short = negative).</p>
                        <canvas id="vegaChart" height="80"></canvas>
                    </div>
                    {{-- Theta --}}
                    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
                        <h2 class="text-lg font-semibold mb-1">⏳ Theta</h2>
                        <p class="text-xs text-gray-500 mb-4">Individual PE/CE Theta and Net Theta (short = positive).</p>
                        <canvas id="thetaChart" height="80"></canvas>
                    </div>
                    {{-- Gamma --}}
                    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
                        <h2 class="text-lg font-semibold mb-1">🎢 Gamma</h2>
                        <p class="text-xs text-gray-500 mb-4">Individual PE/CE Gamma and Net Gamma (short = negative).</p>
                        <canvas id="gammaChart" height="80"></canvas>
                    </div>
                    {{-- Delta --}}
                    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
                        <h2 class="text-lg font-semibold mb-1">🎯 Delta</h2>
                        <p class="text-xs text-gray-500 mb-4">Individual PE/CE Delta and Net Delta (should stay near zero).</p>
                        <canvas id="deltaChart" height="80"></canvas>
                    </div>
                    {{-- IV --}}
                    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
                        <h2 class="text-lg font-semibold mb-1">🌡️ Implied Volatility (IV)</h2>
                        <p class="text-xs text-gray-500 mb-4">Individual IV of the put and call options.</p>
                        <canvas id="ivChart" height="80"></canvas>
                    </div>
                    {{-- POP --}}
                    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
                        <h2 class="text-lg font-semibold mb-1">🎲 Probability of Profit (POP) %</h2>
                        <p class="text-xs text-gray-500 mb-4">Individual POP of the put and call options.</p>
                        <canvas id="popChart" height="80"></canvas>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Chart.js & Select2 initialisation --}}
    @if($data->isNotEmpty())
        <script>
            // ----- Make strike selects searchable with Select2 -----
            $(document).ready(function() {
                $('.searchable').select2({
                    placeholder: "Search strike...",
                    allowClear: true,
                    width: '100%'
                });
            });

            const labels = @json($labels);
            const chartView = @json($chartView);  // 'all', 'combined_only', 'individual_only'

            // Helper: decide which datasets to show based on chartView
            function getCombinedPremiumDatasets() {
                let datasets = [];
                if (chartView === 'all' || chartView === 'individual_only') {
                    datasets.push({
                        label: 'PE LTP',
                        data: @json($putLtp),
                        borderColor: '#ef4444',
                        backgroundColor: 'transparent',
                        tension: 0.2,
                        pointRadius: 0,
                    });
                    datasets.push({
                        label: 'CE LTP',
                        data: @json($callLtp),
                        borderColor: '#3b82f6',
                        backgroundColor: 'transparent',
                        tension: 0.2,
                        pointRadius: 0,
                    });
                }
                if (chartView === 'all' || chartView === 'combined_only') {
                    datasets.push({
                        label: 'Combined Premium',
                        data: @json($combinedLtp),
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245,158,11,0.1)',
                        tension: 0.2,
                        fill: true,
                        pointRadius: 0,
                    });
                }

                @if(!empty($vwap))
                datasets.push({
                    label: 'VWAP (Combined)',
                    data: @json($vwap),
                    borderColor: '#ff0000',
                    borderWidth: 3,
                    borderDash: [2, 2],
                    pointRadius: 1,
                    fill: false,
                });
                @endif
                @if($enterPrice)
                datasets.push({
                    label: 'Entry ({{ $enterPrice }})',
                    data: Array(labels.length).fill({{ $enterPrice }}),
                    borderColor: '#10b981',
                    borderWidth: 2,
                    borderDash: [5,5],
                    pointRadius: 0,
                    fill: false,
                });
                @endif
                    return datasets;
            }

            // Generic tooltip options to show time + all values
            const tooltipOptions = {
                mode: 'index',
                intersect: false,
                callbacks: {
                    title: function(context) {
                        return 'Time: ' + context[0].label;
                    },
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.y;
                    }
                }
            };

            // ---------- Combined Premium Chart ----------
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
                        legend: { labels: { color: '#374151', usePointStyle: true } }
                    },
                    scales: {
                        x: { ticks: { color: '#6b7280', maxTicksLimit: 10 }, grid: { color: '#e5e7eb' } },
                        y: { ticks: { color: '#6b7280' }, grid: { color: '#e5e7eb' } }
                    }
                }
            });

            // Helper for three‑line charts (PE, CE, Net) respecting chartView
            function threeLineChart(canvasId, peLabel, peData, peColor, ceLabel, ceData, ceColor, netLabel, netData, netColor) {
                let datasets = [];
                if (chartView === 'all' || chartView === 'individual_only') {
                    datasets.push({
                        label: peLabel,
                        data: peData,
                        borderColor: peColor,
                        backgroundColor: 'transparent',
                        tension: 0.2,
                        pointRadius: 0,
                    });
                    datasets.push({
                        label: ceLabel,
                        data: ceData,
                        borderColor: ceColor,
                        backgroundColor: 'transparent',
                        tension: 0.2,
                        pointRadius: 0,
                    });
                }
                if (chartView === 'all' || chartView === 'combined_only') {
                    datasets.push({
                        label: netLabel,
                        data: netData,
                        borderColor: netColor,
                        borderWidth: 2,
                        backgroundColor: 'transparent',
                        tension: 0.2,
                        pointRadius: 0,
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
                            legend: { labels: { color: '#374151', usePointStyle: true } }
                        },
                        scales: {
                            x: { ticks: { color: '#6b7280', maxTicksLimit: 10 }, grid: { color: '#e5e7eb' } },
                            y: { ticks: { color: '#6b7280' }, grid: { color: '#e5e7eb' } }
                        }
                    }
                });
            }

            threeLineChart('vegaChart',
                'PE Vega', @json($putVega), '#ef4444',
                'CE Vega', @json($callVega), '#3b82f6',
                'Net Vega (Short)', @json($netVega), '#f97316');

            threeLineChart('thetaChart',
                'PE Theta', @json($putTheta), '#ef4444',
                'CE Theta', @json($callTheta), '#3b82f6',
                'Net Theta (Short)', @json($netTheta), '#22c55e');

            threeLineChart('gammaChart',
                'PE Gamma', @json($putGamma), '#ef4444',
                'CE Gamma', @json($callGamma), '#3b82f6',
                'Net Gamma (Short)', @json($netGamma), '#f97316');

            threeLineChart('deltaChart',
                'PE Delta', @json($putDelta), '#ef4444',
                'CE Delta', @json($callDelta), '#3b82f6',
                'Net Delta (Short)', @json($netDelta), '#8b5cf6');

            // IV and POP are always individual (no combined), so ignore chartView (always show both)
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
                                pointRadius: 0,
                            },
                            {
                                label: label2,
                                data: data2,
                                borderColor: color2,
                                tension: 0.2,
                                pointRadius: 0,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            tooltip: tooltipOptions,
                            legend: { labels: { color: '#374151', usePointStyle: true } }
                        },
                        scales: {
                            x: { ticks: { color: '#6b7280', maxTicksLimit: 10 }, grid: { color: '#e5e7eb' } },
                            y: { ticks: { color: '#6b7280' }, grid: { color: '#e5e7eb' } }
                        }
                    }
                });
            }

            dualLineChart('ivChart', 'Put IV', @json($putIv), '#a78bfa', 'Call IV', @json($callIv), '#f472b6');
            dualLineChart('popChart', 'Put POP %', @json($putPop), '#a78bfa', 'Call POP %', @json($callPop), '#f472b6');
        </script>
    @endif
@endsection
