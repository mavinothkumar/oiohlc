@extends('layouts.app')

@section('title', 'NIFTY OI & Volume Difference')

@section('content')
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


    <div class="bg-gray-900 text-gray-100 min-h-screen">

        <div class="container mx-auto px-4 py-6">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold bg-gradient-to-r from-blue-400 to-purple-500 bg-clip-text text-transparent">
                        📊 Nifty Option Chain Analysis
                    </h1>
                    <p class="text-gray-400 text-sm">Real-time OI & Volume Analysis</p>
                </div>
                <div class="flex items-center space-x-2">
                <span class="px-3 py-1 bg-gray-800 rounded-full text-xs font-medium" id="current-time">
                    {{ now()->format('H:i:s') }}
                </span>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="bg-gray-800 rounded-xl p-4 mb-6 border border-gray-700 shadow-lg">
                <form id="filter-form" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">Date</label>
                        <input type="date" name="date" id="filter-date"
                            value="{{ $currentDate }}"
                            class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">Expiry</label>
                        <input type="date" name="expiry" id="filter-expiry"
                            value="{{ $currentExpiry }}"
                            class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">From Time</label>
                        <input type="time" name="from_time" id="filter-from"
                            value="09:15"
                            class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">To Time</label>
                        <input type="time" name="to_time" id="filter-to"
                            value="15:30"
                            class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </form>
                <div class="mt-3 flex justify-end">
                    <button onclick="fetchData()"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                        <i class="fas fa-sync-alt mr-2"></i>
                        Analyze
                    </button>
                </div>
            </div>

            <!-- Signal Card -->
            <div id="signal-card" class="mb-6 hidden">
                <!-- Signal content will be injected here -->
            </div>

            <!-- Main Content -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left: OI Chart -->
                <div class="lg:col-span-2 bg-gray-800 rounded-xl p-4 border border-gray-700 shadow-lg">
                    <h2 class="text-lg font-semibold mb-3">Open Interest Distribution</h2>
                    <div class="h-80">
                        <canvas id="oiChart"></canvas>
                    </div>
                </div>

                <!-- Right: Summary Stats -->
                <div class="bg-gray-800 rounded-xl p-4 border border-gray-700 shadow-lg">
                    <h2 class="text-lg font-semibold mb-3">Market Summary</h2>
                    <div id="summary-stats" class="space-y-3">
                        <!-- Stats will be injected here -->
                    </div>
                </div>
            </div>

            <!-- Detailed OI Table - FILTERED to ±400 strikes -->
            <div class="mt-6 bg-gray-800 rounded-xl p-4 border border-gray-700 shadow-lg overflow-x-auto">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="text-lg font-semibold">Active Strikes Analysis (±400 points)</h2>
                    <span class="text-xs text-gray-400">Showing strikes within 400 points of current spot</span>
                </div>
                <div id="oi-table-container">
                    <table class="w-full text-sm">
                        <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left py-2 px-3">Strike</th>
                            <th class="text-right py-2 px-3">Put OI</th>
                            <th class="text-right py-2 px-3">Put Δ</th>
                            <th class="text-right py-2 px-3">Put Build</th>
                            <th class="text-right py-2 px-3">Call OI</th>
                            <th class="text-right py-2 px-3">Call Δ</th>
                            <th class="text-right py-2 px-3">Call Build</th>
                            <th class="text-right py-2 px-3">PCR</th>
                        </tr>
                        </thead>
                        <tbody id="oi-table-body">
                        <!-- Table rows will be injected here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
            let oiChart = null;
            let currentSpotPrice = null;

            document.addEventListener('DOMContentLoaded', function () {
                // Auto-refresh time
                setInterval(() => {
                    document.getElementById('current-time').textContent = new Date().toLocaleTimeString('en-IN', { hour12: false });
                }, 1000);

                // Initial fetch
                fetchData();
            });

            function fetchData () {
                const form = document.getElementById('filter-form');
                const formData = new FormData(form);

                // Convert to query parameters
                const params = new URLSearchParams();
                for (const [key, value] of formData.entries()) {
                    params.append(key, value);
                }

                // Show loading state
                document.getElementById('signal-card').classList.add('hidden');
                document.getElementById('summary-stats').innerHTML = '<div class="flex justify-center"><i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i></div>';

                fetch(`/api/option-data?${ params.toString() }`)
                    .then(response => response.json())
                    .then(data => {
                        // Store current spot price for filtering
                        if (data.summary && data.summary.details && data.summary.details.end_price) {
                            currentSpotPrice = data.summary.details.end_price;
                        }

                        updateSignalCard(data.summary);
                        updateSummaryStats(data.summary);
                        updateTable(data.raw_data);
                        updateChart(data.chart_data);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('summary-stats').innerHTML = '<div class="text-red-500 text-center">Error loading data</div>';
                    });
            }

            function updateSignalCard (summary) {
                const card = document.getElementById('signal-card');
                card.classList.remove('hidden');

                let signalColor = 'bg-yellow-600';
                let signalIcon = 'fa-robot';
                let borderColor = 'border-yellow-400';

                if (summary.signal === 'BUY' || summary.signal === 'STRONG BUY') {
                    signalColor = 'bg-green-600';
                    signalIcon = 'fa-arrow-up';
                    borderColor = 'border-green-400';
                } else if (summary.signal === 'SELL' || summary.signal === 'STRONG SELL') {
                    signalColor = 'bg-red-600';
                    signalIcon = 'fa-arrow-down';
                    borderColor = 'border-red-400';
                }

                // Calculate entry and target
                let entry = summary.support_strike ? summary.support_strike + 20 : 'N/A';
                let target = summary.resistance_strike ? summary.resistance_strike : 'N/A';
                let stopLoss = summary.support_strike ? summary.support_strike - 30 : 'N/A';

                card.innerHTML = `
                <div class="border-l-4 ${ borderColor } bg-gray-800 rounded-xl p-4 shadow-lg border border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 ${ signalColor } rounded-full flex items-center justify-center text-white">
                                <i class="fas ${ signalIcon } text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold">${ summary.signal } SIGNAL</h3>
                                <div class="flex items-center space-x-4 text-sm text-gray-400">
                                    <span>Sentiment: <strong class="${ summary.sentiment === 'BULLISH' || summary.sentiment === 'BULLISH BREAKOUT' || summary.sentiment === 'BULLISH CONSOLIDATION' ? 'text-green-400' : summary.sentiment === 'BEARISH' || summary.sentiment === 'BEARISH BREAKDOWN' || summary.sentiment === 'BEARISH CONSOLIDATION' ? 'text-red-400' : 'text-yellow-400' }">${ summary.sentiment }</strong></span>
                                    <span>Confidence: <strong class="text-blue-400">${ summary.confidence_score }%</strong></span>
                                    <span>PCR Trend: <strong class="${ summary.pcr_trend === 'UP' ? 'text-green-400' : summary.pcr_trend === 'DOWN' ? 'text-red-400' : 'text-gray-400' }">${ summary.pcr_trend }</strong></span>
                                </div>
                            </div>
                        </div>
                        <div class="flex space-x-6 text-sm">
                            <div class="text-center">
                                <div class="text-gray-400">Support</div>
                                <div class="text-green-400 font-bold text-lg">${ summary.support_strike || 'N/A' }</div>
                            </div>
                            <div class="text-center">
                                <div class="text-gray-400">Resistance</div>
                                <div class="text-red-400 font-bold text-lg">${ summary.resistance_strike || 'N/A' }</div>
                            </div>
                            <div class="text-center">
                                <div class="text-gray-400">Target</div>
                                <div class="text-blue-400 font-bold text-lg">${ target }</div>
                            </div>
                        </div>
                    </div>
                    ${ summary.signal !== 'WAIT' ? `
                    <div class="mt-3 pt-3 border-t border-gray-700 grid grid-cols-3 gap-4 text-sm">
                        <div>
                            <span class="text-gray-400">Entry:</span>
                            <span class="font-medium text-green-400">${ entry }</span>
                        </div>
                        <div>
                            <span class="text-gray-400">Stop Loss:</span>
                            <span class="font-medium text-red-400">${ stopLoss }</span>
                        </div>
                        <div>
                            <span class="text-gray-400">Volume Spike:</span>
                            <span class="font-medium text-yellow-400">${ summary.volume_spike_strike || 'None' }</span>
                        </div>
                    </div>
                    ` : '' }
                </div>
            `;
            }

            function updateSummaryStats (summary) {
                const container = document.getElementById('summary-stats');

                container.innerHTML = `
                <div class="bg-gray-700 rounded-lg p-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400 text-sm">Sentiment</span>
                        <span class="font-bold ${ summary.sentiment === 'BULLISH' || summary.sentiment === 'BULLISH BREAKOUT' || summary.sentiment === 'BULLISH CONSOLIDATION' ? 'text-green-400' : summary.sentiment === 'BEARISH' || summary.sentiment === 'BEARISH BREAKDOWN' || summary.sentiment === 'BEARISH CONSOLIDATION' ? 'text-red-400' : 'text-yellow-400' }">
                            ${ summary.sentiment }
                        </span>
                    </div>
                </div>
                <div class="bg-gray-700 rounded-lg p-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400 text-sm">Signal</span>
                        <span class="font-bold ${ summary.signal === 'BUY' || summary.signal === 'STRONG BUY' ? 'text-green-400' : summary.signal === 'SELL' || summary.signal === 'STRONG SELL' ? 'text-red-400' : 'text-gray-400' }">
                            ${ summary.signal }
                        </span>
                    </div>
                </div>
                <div class="bg-gray-700 rounded-lg p-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400 text-sm">Price Change</span>
                        <span class="font-bold ${ summary.price_change > 0 ? 'text-green-400' : summary.price_change < 0 ? 'text-red-400' : 'text-gray-400' }">
                            ${ summary.price_change > 0 ? '+' : '' }${ summary.price_change || 0 }
                        </span>
                    </div>
                </div>
                <div class="bg-gray-700 rounded-lg p-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400 text-sm">Support</span>
                        <span class="font-bold text-green-400">${ summary.support_strike || 'N/A' }</span>
                    </div>
                </div>
                <div class="bg-gray-700 rounded-lg p-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400 text-sm">Resistance</span>
                        <span class="font-bold text-red-400">${ summary.resistance_strike || 'N/A' }</span>
                    </div>
                </div>
                <div class="bg-gray-700 rounded-lg p-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400 text-sm">PCR Trend</span>
                        <span class="font-bold ${ summary.pcr_trend === 'UP' ? 'text-green-400' : summary.pcr_trend === 'DOWN' ? 'text-red-400' : 'text-gray-400' }">
                            ${ summary.pcr_trend }
                        </span>
                    </div>
                </div>
                <div class="bg-gray-700 rounded-lg p-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400 text-sm">Max Put Δ</span>
                        <span class="font-bold text-green-400">
                            ${ summary.max_put_oi_change_strike || 'N/A' } (${ summary.max_put_oi_change_value ? summary.max_put_oi_change_value.toLocaleString() : '0' })
                        </span>
                    </div>
                </div>
                <div class="bg-gray-700 rounded-lg p-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400 text-sm">Max Call Δ</span>
                        <span class="font-bold text-red-400">
                            ${ summary.max_call_oi_change_strike || 'N/A' } (${ summary.max_call_oi_change_value ? summary.max_call_oi_change_value.toLocaleString() : '0' })
                        </span>
                    </div>
                </div>
                <div class="bg-gray-700 rounded-lg p-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400 text-sm">Volume Spike</span>
                        <span class="font-bold text-yellow-400">${ summary.volume_spike_strike || 'None' }</span>
                    </div>
                </div>
            `;
            }

            function updateTable (rawData) {
                const tbody = document.getElementById('oi-table-body');

                // Sort strikes
                const strikes = Object.keys(rawData).sort((a, b) => parseFloat(a) - parseFloat(b));

                // Filter strikes to ±400 points from current spot price
                let filteredStrikes = strikes;
                if (currentSpotPrice) {
                    const lowerBound = currentSpotPrice - 400;
                    const upperBound = currentSpotPrice + 400;
                    filteredStrikes = strikes.filter(strike => {
                        const strikeNum = parseFloat(strike);
                        return strikeNum >= lowerBound && strikeNum <= upperBound;
                    });
                }

                // If no strikes in range, show all (fallback)
                if (filteredStrikes.length === 0) {
                    filteredStrikes = strikes;
                }

                let html = '';
                filteredStrikes.forEach(strike => {
                    const put = rawData[ strike ][ 'PE' ] || { oi: 0, oi_change: 0, build_up: null };
                    const call = rawData[ strike ][ 'CE' ] || { oi: 0, oi_change: 0, build_up: null };

                    const putChangeColor = put.oi_change > 0 ? 'text-green-400' : put.oi_change < 0 ? 'text-red-400' : 'text-gray-400';
                    const callChangeColor = call.oi_change > 0 ? 'text-red-400' : call.oi_change < 0 ? 'text-green-400' : 'text-gray-400';

                    // Build-up badges
                    const putBadge = put.build_up ? `<span class="px-2 py-0.5 text-xs rounded-full ${ getBuildUpColor(put.build_up) }">${ put.build_up }</span>` : '';
                    const callBadge = call.build_up ? `<span class="px-2 py-0.5 text-xs rounded-full ${ getBuildUpColor(call.build_up) }">${ call.build_up }</span>` : '';

                    // Highlight strike if it's support or resistance
                    const isSupport = currentSpotPrice ? Math.abs(parseFloat(strike) - currentSpotPrice) < 50 : false;
                    const rowClass = isSupport ? 'bg-gray-750 border-l-4 border-green-500' : 'border-b border-gray-700 hover:bg-gray-750';

                    html += `
                    <tr class="${ rowClass } transition-colors">
                        <td class="py-2 px-3 font-medium">${ strike }</td>
                        <td class="py-2 px-3 text-right font-medium text-green-400">${ put.oi.toLocaleString() }</td>
                        <td class="py-2 px-3 text-right ${ putChangeColor }">${ put.oi_change > 0 ? '+' : '' }${ put.oi_change.toLocaleString() }</td>
                        <td class="py-2 px-3 text-right">${ putBadge }</td>
                        <td class="py-2 px-3 text-right font-medium text-red-400">${ call.oi.toLocaleString() }</td>
                        <td class="py-2 px-3 text-right ${ callChangeColor }">${ call.oi_change > 0 ? '+' : '' }${ call.oi_change.toLocaleString() }</td>
                        <td class="py-2 px-3 text-right">${ callBadge }</td>
                        <td class="py-2 px-3 text-right">
                            ${ put.oi > 0 ? ( call.oi / put.oi ).toFixed(3) : 'N/A' }
                        </td>
                    </tr>
                `;
                });

                tbody.innerHTML = html;
            }

            function getBuildUpColor (buildUp) {
                const colors = {
                    'Long Build': 'bg-green-900 text-green-300',
                    'Short Build': 'bg-red-900 text-red-300',
                    'Short Cover': 'bg-yellow-900 text-yellow-300',
                    'Long Unwind': 'bg-blue-900 text-blue-300'
                };
                return colors[ buildUp ] || 'bg-gray-800 text-gray-400';
            }

            function updateChart (chartData) {
                const ctx = document.getElementById('oiChart').getContext('2d');

                if (oiChart) {
                    oiChart.destroy();
                }

                // Filter chart data to ±400 points for better visualization
                let filteredData = chartData;
                if (currentSpotPrice) {
                    const lowerBound = currentSpotPrice - 400;
                    const upperBound = currentSpotPrice + 400;
                    filteredData = chartData.filter(item => {
                        return item.strike >= lowerBound && item.strike <= upperBound;
                    });
                }

                const labels = filteredData.map(item => item.strike);
                const putData = filteredData.map(item => item.put_oi);
                const callData = filteredData.map(item => item.call_oi);

                oiChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Put OI',
                                data: putData,
                                backgroundColor: 'rgba(52, 211, 153, 0.6)',
                                borderColor: 'rgba(52, 211, 153, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Call OI',
                                data: callData,
                                backgroundColor: 'rgba(248, 113, 113, 0.6)',
                                borderColor: 'rgba(248, 113, 113, 1)',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                labels: {
                                    color: '#e2e8f0'
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: {
                                    color: '#94a3b8'
                                },
                                grid: {
                                    color: 'rgba(255, 255, 255, 0.05)'
                                }
                            },
                            y: {
                                ticks: {
                                    color: '#94a3b8',
                                    callback: function (value) {
                                        return ( value / 1000000 ).toFixed(1) + 'L';
                                    }
                                },
                                grid: {
                                    color: 'rgba(255, 255, 255, 0.05)'
                                }
                            }
                        }
                    }
                });
            }
        </script>
    </div>
@endsection
