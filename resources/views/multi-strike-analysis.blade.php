@extends('layouts.app')

@section('title')
    Multi-Strike Analysis – Short Strangle
@endsection

@section('content')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <div class="bg-gray-50 text-gray-800 font-sans p-4 md:p-6">
        <div class="w-full">
            <h1 class="text-3xl font-bold mb-2">📊 Multi-Strike Analysis – Short Strangle / Straddle</h1>
            <p class="text-sm text-gray-600 mb-6">Compare 4 strike width combinations simultaneously</p>

            {{-- Filter Row --}}
            <form method="GET" class="bg-white rounded-xl shadow border border-gray-200 p-4 mb-6">
                <div class="flex flex-wrap items-end gap-3">
                    {{-- Expiry --}}
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

                    {{-- Put Strike --}}
                    <div class="flex flex-col">
                        <label class="text-xs text-gray-600 mb-1">Put Strike (PE)</label>
                        <select name="put_strike" class="searchable border border-gray-300 rounded px-3 py-2 text-sm w-40 bg-white">
                            <option value="">-- Select --</option>
                            @foreach($strikes as $s)
                                <option value="{{ $s }}" {{ $putStrike == $s ? 'selected' : '' }}>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Call Strike --}}
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
                            placeholder="Base entry"
                            class="border border-gray-300 rounded px-3 py-2 text-sm w-36 bg-white">
                    </div>

                    {{-- Chart View --}}
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

            {{-- Grid Layout Controls --}}
            @if(!empty($chartData))
                <div class="flex items-center justify-between mb-4">
                    <div class="text-sm text-gray-600">
                        Showing {{ count($chartData) }} strike combinations
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-500 mr-2">Layout:</span>
                        @foreach([1, 2, 3, 4] as $cols)
                            <a href="{{ request()->fullUrlWithQuery(['grid_columns' => $cols]) }}"
                                class="w-8 h-8 flex items-center justify-center rounded border text-xs
                              {{ $gridColumns == $cols ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-100' }}">
                                {{ $cols }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Charts Grid --}}
            @if(!empty($chartData))
                @php
                    $gridClass = match((int)$gridColumns) {
                        1 => 'grid-cols-1',
                        2 => 'grid-cols-1 lg:grid-cols-2',
                        3 => 'grid-cols-1 lg:grid-cols-2 xl:grid-cols-3',
                        4 => 'grid-cols-1 lg:grid-cols-2 xl:grid-cols-4',
                        default => 'grid-cols-1 lg:grid-cols-2'
                    };

                    $chartHeight = match((int)$gridColumns) {
                        1 => '400px',
                        2 => '320px',
                        3 => '280px',
                        4 => '250px',
                        default => '320px'
                    };
                @endphp

                <div class="grid {{ $gridClass }} gap-6">
                    @foreach($chartData as $key => $data)
                        <div class="bg-white rounded-xl shadow border border-gray-200 p-4 flex flex-col">
                            <h3 class="text-lg font-semibold mb-1">
                                {{ $data['put_strike'] }} PE + {{ $data['call_strike'] }} CE
                            </h3>
                            <div class="text-xs text-gray-500 mb-2">
                                Width: {{ $data['call_strike'] - $data['put_strike'] }} pts
                                <span class="ml-4">
                    <span class="px-1.5 py-0.5 rounded text-xs
                        {{ Str::contains($data['putBuildUp']->last(), 'Short') ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                        P: {{ $data['putBuildUp']->last() ?? 'N/A' }}
                    </span>
                    <span class="px-1.5 py-0.5 rounded text-xs ml-1
                        {{ Str::contains($data['callBuildUp']->last(), 'Short') ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                        C: {{ $data['callBuildUp']->last() ?? 'N/A' }}
                    </span>
                </span>
                            </div>
                            <div class="relative" style="height: {{ $chartHeight }};">
                                <canvas id="chart_{{ $loop->index }}"></canvas>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                @if($putStrike && $callStrike)
                    <div class="bg-yellow-100 border border-yellow-300 text-yellow-800 p-4 rounded-lg">
                        No data found for the selected filters.
                    </div>
                @else
                    <div class="bg-blue-50 border border-blue-200 text-blue-800 p-4 rounded-lg">
                        Select Put and Call strikes to view the multi-strike comparison.
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Charts Initialization --}}
    @if(!empty($chartData))
        <script>
            $(document).ready(function() {
                $('.searchable').select2({
                    placeholder: "Search strike...",
                    allowClear: true,
                    width: '100%'
                });
            });

            const chartView = @json($chartView);
            const enterPrice = @json($enterPrice);

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

            // Custom plugin: Show latest value at the end of each line (like TradingView)
            const endOfLineValuePlugin = {
                id: 'endOfLineValues',
                afterDraw(chart) {
                    const ctx = chart.ctx;
                    const chartArea = chart.chartArea;

                    chart.data.datasets.forEach((dataset, i) => {
                        const meta = chart.getDatasetMeta(i);
                        if (!meta || !meta.data || meta.data.length === 0) return;
                        if (!dataset.data || dataset.data.length === 0) return;

                        // Get the last visible point
                        const lastPoint = meta.data[meta.data.length - 1];
                        const lastValue = dataset.data[dataset.data.length - 1];

                        if (lastValue === null || lastValue === undefined) return;

                        const x = lastPoint.x;
                        const y = lastPoint.y;

                        // Only draw if the point is within chart area
                        if (x < chartArea.left || x > chartArea.right ||
                            y < chartArea.top || y > chartArea.bottom) return;

                        ctx.save();

                        // Format the value
                        const displayValue = typeof lastValue === 'number'
                            ? (Math.abs(lastValue) >= 1000 ? lastValue.toFixed(0) : lastValue.toFixed(2))
                            : lastValue;

                        // Get shortened label
                        let label = '';
                        if (dataset.label) {
                            const parts = dataset.label.split(' ');
                            label = parts[0]; // First word only
                        }

                        // Set text style
                        ctx.font = 'bold 10px system-ui, -apple-system, sans-serif';
                        ctx.textAlign = 'left';
                        ctx.textBaseline = 'middle';

                        // Calculate background width
                        const text = label ? `${label} ${displayValue}` : displayValue;
                        const textWidth = ctx.measureText(text).width;
                        const padding = 6;
                        const bgWidth = textWidth + (padding * 2);
                        const bgHeight = 18;

                        // Position the label just after the last point
                        let bgX = x + 8;
                        let bgY = y;

                        // If label would go off chart, place it before the point
                        if (bgX + bgWidth > chartArea.right) {
                            bgX = x - bgWidth - 8;
                        }

                        // Keep within vertical bounds
                        if (bgY - bgHeight/2 < chartArea.top) {
                            bgY = chartArea.top + bgHeight/2;
                        }
                        if (bgY + bgHeight/2 > chartArea.bottom) {
                            bgY = chartArea.bottom - bgHeight/2;
                        }

                        // Draw background pill
                        ctx.fillStyle = dataset.borderColor || '#000';
                        ctx.globalAlpha = 0.9;
                        ctx.beginPath();
                        ctx.roundRect(bgX, bgY - bgHeight/2, bgWidth, bgHeight, 4);
                        ctx.fill();

                        // Draw text
                        ctx.globalAlpha = 1;
                        ctx.fillStyle = '#ffffff';
                        ctx.fillText(text, bgX + padding, bgY);

                        ctx.restore();
                    });
                }
            };

            // Register the plugin
            Chart.register(endOfLineValuePlugin);

            // Chart data from controller
            const allChartData = @json($chartData);

            // Draw each chart
            Object.keys(allChartData).forEach((key, index) => {
                const data = allChartData[key];
                const labels = data.labels;

                const datasets = [];

                // PE & CE LTP (individual)
                if (chartView === 'all' || chartView === 'individual_only') {
                    datasets.push({
                        label: 'PE',
                        data: data.putLtp,
                        borderColor: '#ef4444',
                        backgroundColor: 'transparent',
                        tension: 0.2,
                        pointRadius: 0,
                        borderWidth: 1.5,
                        yAxisID: 'y'
                    });
                    datasets.push({
                        label: 'CE',
                        data: data.callLtp,
                        borderColor: '#3b82f6',
                        backgroundColor: 'transparent',
                        tension: 0.2,
                        pointRadius: 0,
                        borderWidth: 1.5,
                        yAxisID: 'y'
                    });
                }

                // Combined Premium
                if (chartView === 'all' || chartView === 'combined_only') {
                    datasets.push({
                        label: 'Combined',
                        data: data.combinedLtp,
                        borderColor: '#6aea37',
                        backgroundColor: 'rgba(126,228,121,0.1)',
                        tension: 0.2,
                        fill: true,
                        pointRadius: 0,
                        borderWidth: 2.5,
                        yAxisID: 'y'
                    });
                }

                // Entry Line
                if (enterPrice) {
                    datasets.push({
                        label: 'Entry',
                        data: Array(labels.length).fill(enterPrice),
                        borderColor: '#2e9772',
                        borderWidth: 1.5,
                        borderDash: [5,5],
                        pointRadius: 0,
                        fill: false,
                        yAxisID: 'y'
                    });
                }

                // VWAP
                if (data.vwap && data.vwap.length > 0) {
                    datasets.push({
                        label: 'VWAP',
                        data: data.vwap,
                        borderColor: '#f97316',
                        borderWidth: 5,
                        borderDash: [1, 1],
                        pointRadius: 1,
                        fill: false,
                        yAxisID: 'y'
                    });
                }

                // Net OI Change
                if (data.netOIChange && data.netOIChange.length > 0) {
                    datasets.push({
                        label: 'Net OI',
                        data: data.netOIChange,
                        borderColor: '#ec4899',
                        borderWidth: 4,
                        pointRadius: 1,
                        fill: false,
                        yAxisID: 'y1'
                    });
                }

                new Chart(document.getElementById('chart_' + index), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            tooltip: tooltipOptions,
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    color: '#374151',
                                    usePointStyle: true,
                                    boxWidth: 8,
                                    font: { size: 10 },
                                    padding: 15
                                }
                            },
                            endOfLineValues: {}
                        },
                        layout: {
                            padding: {
                                right: 10,  // Small padding, labels will auto-adjust
                                left: 5,
                                top: 5,
                                bottom: 5
                            }
                        },
                        scales: {
                            x: {
                                ticks: { color: '#6b7280', maxTicksLimit: 8, font: { size: 9 } },
                                grid: { color: '#e5e7eb' }
                            },
                            y: {
                                type: 'linear',
                                position: 'left',
                                title: { display: true, text: 'Premium (₹)', font: { size: 10 } },
                                ticks: { color: '#6b7280', font: { size: 9 } },
                                grid: { color: '#e5e7eb' },
                                beginAtZero: false,
                                grace: '5%'
                            },
                            y1: {
                                type: 'linear',
                                position: 'right',
                                title: { display: true, text: 'Net OI', font: { size: 10 } },
                                grid: { drawOnChartArea: false },
                                ticks: { color: '#ec4899', font: { size: 9 } },
                                display: data.netOIChange && data.netOIChange.length > 0,
                                grace: '5%'
                            }
                        }
                    }
                });
            });
        </script>
    @endif
@endsection
