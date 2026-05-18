@extends('layouts.app')

@section('title', 'NIFTY OI & Volume Difference')

@section('content')
    <div class="container mx-auto px-4 py-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold bg-gradient-to-r from-blue-400 to-purple-500 bg-clip-text text-transparent">
                    🔍 Strike Analysis: OI & Volume
                </h1>
                <p class="text-gray-400 text-sm">Detailed 5-minute OI changes for a specific strike</p>
            </div>
            <div class="flex items-center space-x-2">
            <span class="px-3 py-1 bg-gray-800 rounded-full text-xs font-medium text-white" id="current-time">
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
                    <label class="block text-sm font-medium text-gray-400 mb-1">Strike Price</label>
                    <input type="number" name="strike" id="filter-strike"
                        value="{{ $atmStrike }}"
                        step="50"
                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div class="flex items-end">
                    <button onclick="fetchStrikeData()"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors w-full">
                        <i class="fas fa-sync-alt mr-2"></i> Load Data
                    </button>
                </div>
            </form>

            <!-- Quick ATM Strikes -->
            <div class="mt-3 flex flex-wrap gap-2">
                <span class="text-xs text-gray-400 mr-2">Quick ATM:</span>
                @foreach([-200, -150, -100, -50, 0, 50, 100, 150, 200] as $offset)
                    <button onclick="setStrike({{ $atmStrike + $offset }})"
                        class="px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 rounded-md text-gray-300 transition-colors">
                        {{ $atmStrike + $offset }}
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Summary Cards -->
        <div id="summary-cards" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 hidden">
            <!-- Will be populated by JS -->
        </div>

        <!-- Main Table -->
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700 shadow-lg overflow-x-auto">
            <div class="flex justify-between items-center mb-3">
                <h2 class="text-lg font-semibold text-white">5-Minute OI & Volume Analysis</h2>
                <span class="text-xs text-gray-400 text-white" id="table-info">Loading...</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[1200px]">
                    <thead>
                    <tr class="border-b border-gray-700 bg-gray-750 text-white">
                        <th class="sticky left-0 bg-gray-800 py-2 px-3 text-left font-semibold">Time</th>

                        <!-- CE Section -->
                        <th class="py-2 px-3 text-right font-semibold text-green-400">CE OI</th>
                        <th class="py-2 px-3 text-right font-semibold text-green-400">CE Δ (Current)</th>
                        <th class="py-2 px-3 text-right font-semibold text-green-400">CE Δ (Cumulative)</th>
                        <th class="py-2 px-3 text-right font-semibold text-green-400">CE % (Current)</th>
                        <th class="py-2 px-3 text-right font-semibold text-green-400">CE % (Cumulative)</th>

                        <!-- Strike -->
                        <th class="py-2 px-3 text-center font-semibold text-yellow-400">Strike</th>

                        <!-- PE Section -->
                        <th class="py-2 px-3 text-right font-semibold text-red-400">PE OI</th>
                        <th class="py-2 px-3 text-right font-semibold text-red-400">PE Δ (Current)</th>
                        <th class="py-2 px-3 text-right font-semibold text-red-400">PE Δ (Cumulative)</th>
                        <th class="py-2 px-3 text-right font-semibold text-red-400">PE % (Current)</th>
                        <th class="py-2 px-3 text-right font-semibold text-red-400">PE % (Cumulative)</th>
                    </tr>
                    </thead>
                    <tbody id="table-body">
                    <!-- Data will be populated by JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Volume Chart -->
        <div class="mt-6 bg-gray-800 rounded-xl p-4 border border-gray-700 shadow-lg">
            <h2 class="text-lg font-semibold mb-3">Volume Comparison</h2>
            <div class="h-64">
                <canvas id="volumeChart"></canvas>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let volumeChart = null;

        document.addEventListener('DOMContentLoaded', function() {
            // Auto-refresh time
            setInterval(() => {
                document.getElementById('current-time').textContent = new Date().toLocaleTimeString('en-IN', { hour12: false });
            }, 1000);

            // Initial fetch
            fetchStrikeData();
        });

        function setStrike(value) {
            document.getElementById('filter-strike').value = value;
            fetchStrikeData();
        }

        function fetchStrikeData() {
            const form = document.getElementById('filter-form');
            const formData = new FormData(form);

            // Convert to query parameters
            const params = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                params.append(key, value);
            }

            // Show loading state
            document.getElementById('table-body').innerHTML = '<tr><td colspan="12" class="text-center py-4"><i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i></td></tr>';
            document.getElementById('summary-cards').classList.add('hidden');
            document.getElementById('table-info').textContent = 'Loading...';

            fetch(`/api/strike-data?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    updateTable(data.data, data.strike);
                    updateSummaryCards(data.data, data.strike);
                    updateVolumeChart(data.data);
                    document.getElementById('table-info').textContent = `Strike: ${data.strike} | Expiry: ${data.expiry} | ${data.data.length} intervals`;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('table-body').innerHTML = '<tr><td colspan="12" class="text-center py-4 text-red-500">Error loading data</td></tr>';
                });
        }

        function updateTable(data, strike) {
            const tbody = document.getElementById('table-body');

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="12" class="text-center py-4 text-gray-400">No data available for this strike</td></tr>';
                return;
            }

            let html = '';
            data.forEach(row => {
                // Color coding for current differences
                const ceCurrentColor = row.ce_current_diff_oi > 0 ? 'text-green-400' : (row.ce_current_diff_oi < 0 ? 'text-red-400' : 'text-gray-400');
                const peCurrentColor = row.pe_current_diff_oi > 0 ? 'text-red-400' : (row.pe_current_diff_oi < 0 ? 'text-green-400' : 'text-gray-400');

                // Color coding for cumulative differences
                const ceCumulativeColor = row.ce_cumulative_diff_oi > 0 ? 'text-green-400' : (row.ce_cumulative_diff_oi < 0 ? 'text-red-400' : 'text-gray-400');
                const peCumulativeColor = row.pe_cumulative_diff_oi > 0 ? 'text-red-400' : (row.pe_cumulative_diff_oi < 0 ? 'text-green-400' : 'text-gray-400');

                // Color coding for percentages
                const ceCurrentPercentColor = row.ce_current_percent > 0 ? 'text-green-400' : (row.ce_current_percent < 0 ? 'text-red-400' : 'text-gray-400');
                const peCurrentPercentColor = row.pe_current_percent > 0 ? 'text-red-400' : (row.pe_current_percent < 0 ? 'text-green-400' : 'text-gray-400');
                const ceCumulativePercentColor = row.ce_cumulative_percent > 0 ? 'text-green-400' : (row.ce_cumulative_percent < 0 ? 'text-red-400' : 'text-gray-400');
                const peCumulativePercentColor = row.pe_cumulative_percent > 0 ? 'text-red-400' : (row.pe_cumulative_percent < 0 ? 'text-green-400' : 'text-gray-400');

                html += `
                <tr class="border-b border-gray-700 hover:bg-gray-750 transition-colors">
                    <td class="sticky left-0 bg-gray-800 py-2 px-3 text-left font-medium text-white">${row.time}</td>

                    <!-- CE Data -->
                    <td class="py-2 px-3 text-right font-medium text-green-400">${row.ce_oi ? row.ce_oi.toLocaleString() : '-'}</td>
                    <td class="py-2 px-3 text-right ${ceCurrentColor}">${row.ce_current_diff_oi > 0 ? '+' : ''}${row.ce_current_diff_oi.toLocaleString()}</td>
                    <td class="py-2 px-3 text-right ${ceCumulativeColor}">${row.ce_cumulative_diff_oi > 0 ? '+' : ''}${row.ce_cumulative_diff_oi.toLocaleString()}</td>
                    <td class="py-2 px-3 text-right ${ceCurrentPercentColor}">${row.ce_current_percent > 0 ? '+' : ''}${row.ce_current_percent}%</td>
                    <td class="py-2 px-3 text-right ${ceCumulativePercentColor}">${row.ce_cumulative_percent > 0 ? '+' : ''}${row.ce_cumulative_percent}%</td>

                    <!-- Strike -->
                    <td class="py-2 px-3 text-center font-bold text-yellow-400">${strike}</td>

                    <!-- PE Data -->
                    <td class="py-2 px-3 text-right font-medium text-red-400">${row.pe_oi ? row.pe_oi.toLocaleString() : '-'}</td>
                    <td class="py-2 px-3 text-right ${peCurrentColor}">${row.pe_current_diff_oi > 0 ? '+' : ''}${row.pe_current_diff_oi.toLocaleString()}</td>
                    <td class="py-2 px-3 text-right ${peCumulativeColor}">${row.pe_cumulative_diff_oi > 0 ? '+' : ''}${row.pe_cumulative_diff_oi.toLocaleString()}</td>
                    <td class="py-2 px-3 text-right ${peCurrentPercentColor}">${row.pe_current_percent > 0 ? '+' : ''}${row.pe_current_percent}%</td>
                    <td class="py-2 px-3 text-right ${peCumulativePercentColor}">${row.pe_cumulative_percent > 0 ? '+' : ''}${row.pe_cumulative_percent}%</td>
                </tr>
            `;
            });

            tbody.innerHTML = html;
        }

        function updateSummaryCards(data, strike) {
            if (data.length === 0) return;

            const container = document.getElementById('summary-cards');
            container.classList.remove('hidden');

            const first = data[0];
            const last = data[data.length - 1];

            // Calculate total changes
            const totalCEChange = last.ce_cumulative_diff_oi;
            const totalPEChange = last.pe_cumulative_diff_oi;
            const totalCEPercent = last.ce_cumulative_percent;
            const totalPEPercent = last.pe_cumulative_percent;

            // Current interval changes
            const currentCEChange = last.ce_current_diff_oi;
            const currentPEChange = last.pe_current_diff_oi;
            const currentCEPercent = last.ce_current_percent;
            const currentPEPercent = last.pe_current_percent;

            container.innerHTML = `
            <div class="bg-gray-700 rounded-lg p-3">
                <div class="text-xs text-gray-400">Strike</div>
                <div class="text-2xl font-bold text-yellow-400">${strike}</div>
                <div class="text-xs text-gray-500">${data.length} intervals</div>
            </div>

            <div class="bg-gray-700 rounded-lg p-3">
                <div class="text-xs text-gray-400">CE Total Δ</div>
                <div class="text-xl font-bold ${totalCEChange > 0 ? 'text-green-400' : totalCEChange < 0 ? 'text-red-400' : 'text-gray-400'}">
                    ${totalCEChange > 0 ? '+' : ''}${totalCEChange.toLocaleString()}
                </div>
                <div class="text-xs ${totalCEPercent > 0 ? 'text-green-400' : totalCEPercent < 0 ? 'text-red-400' : 'text-gray-400'}">
                    ${totalCEPercent > 0 ? '+' : ''}${totalCEPercent}%
                </div>
            </div>

            <div class="bg-gray-700 rounded-lg p-3">
                <div class="text-xs text-gray-400">PE Total Δ</div>
                <div class="text-xl font-bold ${totalPEChange > 0 ? 'text-red-400' : totalPEChange < 0 ? 'text-green-400' : 'text-gray-400'}">
                    ${totalPEChange > 0 ? '+' : ''}${totalPEChange.toLocaleString()}
                </div>
                <div class="text-xs ${totalPEPercent > 0 ? 'text-red-400' : totalPEPercent < 0 ? 'text-green-400' : 'text-gray-400'}">
                    ${totalPEPercent > 0 ? '+' : ''}${totalPEPercent}%
                </div>
            </div>

            <div class="bg-gray-700 rounded-lg p-3">
                <div class="text-xs text-gray-400">Last Interval Δ</div>
                <div class="flex justify-between">
                    <div>
                        <span class="text-xs text-green-400">CE:</span>
                        <span class="text-sm font-bold ${currentCEChange > 0 ? 'text-green-400' : currentCEChange < 0 ? 'text-red-400' : 'text-gray-400'}">
                            ${currentCEChange > 0 ? '+' : ''}${currentCEChange.toLocaleString()}
                        </span>
                    </div>
                    <div>
                        <span class="text-xs text-red-400">PE:</span>
                        <span class="text-sm font-bold ${currentPEChange > 0 ? 'text-red-400' : currentPEChange < 0 ? 'text-green-400' : 'text-gray-400'}">
                            ${currentPEChange > 0 ? '+' : ''}${currentPEChange.toLocaleString()}
                        </span>
                    </div>
                </div>
            </div>
        `;
        }

        function updateVolumeChart(data) {
            const ctx = document.getElementById('volumeChart').getContext('2d');

            if (volumeChart) {
                volumeChart.destroy();
            }

            const labels = data.map(row => row.time);
            const ceVolume = data.map(row => row.ce_volume || 0);
            const peVolume = data.map(row => row.pe_volume || 0);

            volumeChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'CE Volume',
                            data: ceVolume,
                            backgroundColor: 'rgba(52, 211, 153, 0.5)',
                            borderColor: 'rgba(52, 211, 153, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'PE Volume',
                            data: peVolume,
                            backgroundColor: 'rgba(248, 113, 113, 0.5)',
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
                                color: '#94a3b8',
                                maxRotation: 45,
                                autoSkip: true,
                                maxTicksLimit: 15
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.05)'
                            }
                        },
                        y: {
                            ticks: {
                                color: '#94a3b8',
                                callback: function(value) {
                                    return (value / 1000000).toFixed(1) + 'L';
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
@endsection
