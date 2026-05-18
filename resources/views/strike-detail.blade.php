@extends('layouts.app')

@section('title', 'NIFTY OI & Volume - Multi Strike Analysis')

@section('content')
    <div class="container mx-auto px-4 py-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-blue-600">
                    📊 Multi-Strike OI Analysis
                </h1>
                <p class="text-gray-600 text-sm">5-Strike Profile (ATM ±2) with Consolidated Action</p>
            </div>
            <div class="flex items-center space-x-2">
            <span class="px-3 py-1 bg-gray-100 rounded-full text-xs font-medium text-gray-700" id="current-time">
                {{ now()->format('H:i:s') }}
            </span>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="bg-white rounded-xl p-4 mb-6 border border-gray-200 shadow-sm">
            <form id="filter-form" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <input type="date" name="date" id="filter-date"
                        value="{{ $currentDate }}"
                        class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Expiry</label>
                    <input type="date" name="expiry" id="filter-expiry"
                        value="{{ $currentExpiry }}"
                        class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ATM Strike</label>
                    <input type="number" name="strike" id="filter-strike"
                        value="{{ $atmStrike }}"
                        step="50"
                        class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div class="flex items-end">
                    <button onclick="fetchStrikeData()"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors w-full">
                        <i class="fas fa-sync-alt mr-2"></i> Analyze
                    </button>
                </div>
            </form>

            <!-- Quick ATM Strikes -->
            <div class="mt-3 flex flex-wrap gap-2">
                <span class="text-xs text-gray-500 mr-2">Quick ATM:</span>
                @foreach([-200, -150, -100, -50, 0, 50, 100, 150, 200] as $offset)
                    <button onclick="setStrike({{ $atmStrike + $offset }})"
                        class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded-md text-gray-700 transition-colors">
                        {{ $atmStrike + $offset }}
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Consolidated Signal Card -->
        <div id="consolidated-signal" class="mb-6 hidden">
            <!-- Will be populated by JS -->
        </div>

        <!-- Summary Cards -->
        <div id="summary-cards" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 hidden">
            <!-- Will be populated by JS -->
        </div>

        <!-- Main Table -->
        <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm overflow-x-auto">
            <div class="flex justify-between items-center mb-3">
                <h2 class="text-lg font-semibold text-gray-800">5-Strike Profile (ATM ±2)</h2>
                <span class="text-xs text-gray-500" id="table-info">Loading...</span>
            </div>

            <!-- Legend -->
            <div class="flex flex-wrap gap-4 mb-3 text-xs text-gray-600">
                <span class="flex items-center"><span class="w-3 h-3 bg-green-500 rounded-full mr-1"></span> Top 5% CE Positive</span>
                <span class="flex items-center"><span class="w-3 h-3 bg-red-500 rounded-full mr-1"></span> Top 5% CE Negative</span>
                <span class="flex items-center"><span class="w-3 h-3 bg-blue-500 rounded-full mr-1"></span> Top 5% PE Positive</span>
                <span class="flex items-center"><span class="w-3 h-3 bg-orange-500 rounded-full mr-1"></span> Top 5% PE Negative</span>
                <span class="flex items-center"><span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium">STRONG BUY</span></span>
                <span class="flex items-center"><span class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs font-medium">STRONG SELL</span></span>
                <span class="flex items-center"><span class="px-2 py-0.5 bg-gray-100 text-gray-700 rounded text-xs font-medium">WAIT</span></span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[1400px]">
                    <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="sticky left-0 bg-gray-50 py-2 px-3 text-left font-semibold text-gray-700">Time</th>
                        <th class="py-2 px-3 text-center font-semibold text-gray-700">Strike</th>
                        <th class="py-2 px-3 text-right font-semibold text-green-600">CE OI</th>
                        <th class="py-2 px-3 text-right font-semibold text-green-600">CE Δ (Cur)</th>
                        <th class="py-2 px-3 text-right font-semibold text-green-600">CE Δ (Cum)</th>
                        <th class="py-2 px-3 text-right font-semibold text-green-600">CE % (Cur)</th>
                        <th class="py-2 px-3 text-right font-semibold text-green-600">CE % (Cum)</th>
                        <th class="py-2 px-3 text-right font-semibold text-red-600">PE OI</th>
                        <th class="py-2 px-3 text-right font-semibold text-red-600">PE Δ (Cur)</th>
                        <th class="py-2 px-3 text-right font-semibold text-red-600">PE Δ (Cum)</th>
                        <th class="py-2 px-3 text-right font-semibold text-red-600">PE % (Cur)</th>
                        <th class="py-2 px-3 text-right font-semibold text-red-600">PE % (Cum)</th>
                        <th class="py-2 px-3 text-center font-semibold text-blue-600">Action</th>
                    </tr>
                    </thead>
                    <tbody id="table-body">
                    <!-- Data will be populated by JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setInterval(() => {
                document.getElementById('current-time').textContent = new Date().toLocaleTimeString('en-IN', { hour12: false });
            }, 1000);
            fetchStrikeData();
        });

        function setStrike(value) {
            document.getElementById('filter-strike').value = value;
            fetchStrikeData();
        }

        function fetchStrikeData() {
            const form = document.getElementById('filter-form');
            const formData = new FormData(form);
            const params = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                params.append(key, value);
            }

            document.getElementById('table-body').innerHTML = '<tr><td colspan="13" class="text-center py-4"><i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i></td></tr>';
            document.getElementById('consolidated-signal').classList.add('hidden');
            document.getElementById('summary-cards').classList.add('hidden');
            document.getElementById('table-info').textContent = 'Loading...';

            fetch(`/api/strike-data?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    updateTable(data.data, data.strikes, data.atm_strike);
                    updateConsolidatedSignal(data.data, data.atm_strike);
                    updateSummaryCards(data.data, data.strikes);
                    document.getElementById('table-info').textContent = `ATM: ${data.atm_strike} | Expiry: ${data.expiry} | ${Object.keys(data.data).length} intervals`;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('table-body').innerHTML = '<tr><td colspan="13" class="text-center py-4 text-red-500">Error loading data</td></tr>';
                });
        }

        function updateTable(data, strikes, atmStrike) {
            const tbody = document.getElementById('table-body');

            if (Object.keys(data).length === 0) {
                tbody.innerHTML = '<tr><td colspan="13" class="text-center py-4 text-gray-500">No data available</td></tr>';
                return;
            }

            let html = '';
            let rowIndex = 0;

            // Sort times descending
            const times = Object.keys(data).sort().reverse();

            times.forEach(time => {
                const timeData = data[time];
                const isEven = rowIndex % 2 === 0;
                const bgClass = isEven ? 'bg-white' : 'bg-gray-50';

                // Add separator between different time batches
                if (rowIndex > 0) {
                    html += `<tr class="border-t-2 border-gray-300"><td colspan="13" class="py-1"></td></tr>`;
                }

                // For each strike in this time
                strikes.forEach(strike => {
                    const row = timeData.strikes[strike];
                    if (!row) return;

                    const isATM = strike === atmStrike;
                    const strikeClass = isATM ? 'font-bold text-blue-600' : 'text-gray-700';

                    // Color coding
                    const ceCurrentColor = row.ce_current_diff_oi > 0 ? 'text-green-600' : (row.ce_current_diff_oi < 0 ? 'text-red-600' : 'text-gray-500');
                    const peCurrentColor = row.pe_current_diff_oi > 0 ? 'text-red-600' : (row.pe_current_diff_oi < 0 ? 'text-green-600' : 'text-gray-500');
                    const ceCumulativeColor = row.ce_cumulative_diff_oi > 0 ? 'text-green-600' : (row.ce_cumulative_diff_oi < 0 ? 'text-red-600' : 'text-gray-500');
                    const peCumulativeColor = row.pe_cumulative_diff_oi > 0 ? 'text-red-600' : (row.pe_cumulative_diff_oi < 0 ? 'text-green-600' : 'text-gray-500');

                    // Top 5 highlights
                    let ceCurrentPercentClass = row.ce_current_percent > 0 ? 'text-green-600' : (row.ce_current_percent < 0 ? 'text-red-600' : 'text-gray-500');
                    let peCurrentPercentClass = row.pe_current_percent > 0 ? 'text-red-600' : (row.pe_current_percent < 0 ? 'text-green-600' : 'text-gray-500');
                    let ceCumulativePercentClass = row.ce_cumulative_percent > 0 ? 'text-green-600' : (row.ce_cumulative_percent < 0 ? 'text-red-600' : 'text-gray-500');
                    let peCumulativePercentClass = row.pe_cumulative_percent > 0 ? 'text-red-600' : (row.pe_cumulative_percent < 0 ? 'text-green-600' : 'text-gray-500');

                    if (row.is_top5_ce_positive) ceCurrentPercentClass = 'text-green-700 font-bold bg-green-100 px-1 rounded';
                    if (row.is_top5_ce_negative) ceCurrentPercentClass = 'text-red-700 font-bold bg-red-100 px-1 rounded';
                    if (row.is_top5_pe_positive) peCurrentPercentClass = 'text-red-700 font-bold bg-red-100 px-1 rounded';
                    if (row.is_top5_pe_negative) peCurrentPercentClass = 'text-green-700 font-bold bg-green-100 px-1 rounded';

                    // Action badge - show only for ATM strike
                    let actionBadge = '';
                    if (isATM) {
                        switch(timeData.consolidated_action) {
                            case 'STRONG BUY':
                                actionBadge = '<span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-bold">▲ STRONG BUY</span>';
                                break;
                            case 'BUY':
                                actionBadge = '<span class="px-2 py-1 bg-green-50 text-green-600 rounded text-xs font-bold">▲ BUY</span>';
                                break;
                            case 'STRONG SELL':
                                actionBadge = '<span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-bold">▼ STRONG SELL</span>';
                                break;
                            case 'SELL':
                                actionBadge = '<span class="px-2 py-1 bg-red-50 text-red-600 rounded text-xs font-bold">▼ SELL</span>';
                                break;
                            default:
                                actionBadge = '<span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs font-bold">⏸ WAIT</span>';
                        }
                    }

                    html += `
                    <tr class="${bgClass} hover:bg-gray-100 transition-colors">
                        <td class="sticky left-0 ${bgClass} py-2 px-3 text-left font-medium text-gray-800">${time}</td>
                        <td class="py-2 px-3 text-center ${strikeClass}">${strike}</td>
                        <td class="py-2 px-3 text-right font-medium text-green-600">${row.ce_oi ? row.ce_oi.toLocaleString() : '-'}</td>
                        <td class="py-2 px-3 text-right ${ceCurrentColor}">${row.ce_current_diff_oi > 0 ? '+' : ''}${row.ce_current_diff_oi.toLocaleString()}</td>
                        <td class="py-2 px-3 text-right ${ceCumulativeColor}">${row.ce_cumulative_diff_oi > 0 ? '+' : ''}${row.ce_cumulative_diff_oi.toLocaleString()}</td>
                        <td class="py-2 px-3 text-right ${ceCurrentPercentClass}">${row.ce_current_percent > 0 ? '+' : ''}${row.ce_current_percent}%</td>
                        <td class="py-2 px-3 text-right ${ceCumulativePercentClass}">${row.ce_cumulative_percent > 0 ? '+' : ''}${row.ce_cumulative_percent}%</td>
                        <td class="py-2 px-3 text-right font-medium text-red-600">${row.pe_oi ? row.pe_oi.toLocaleString() : '-'}</td>
                        <td class="py-2 px-3 text-right ${peCurrentColor}">${row.pe_current_diff_oi > 0 ? '+' : ''}${row.pe_current_diff_oi.toLocaleString()}</td>
                        <td class="py-2 px-3 text-right ${peCumulativeColor}">${row.pe_cumulative_diff_oi > 0 ? '+' : ''}${row.pe_cumulative_diff_oi.toLocaleString()}</td>
                        <td class="py-2 px-3 text-right ${peCurrentPercentClass}">${row.pe_current_percent > 0 ? '+' : ''}${row.pe_current_percent}%</td>
                        <td class="py-2 px-3 text-right ${peCumulativePercentClass}">${row.pe_cumulative_percent > 0 ? '+' : ''}${row.pe_cumulative_percent}%</td>
                        <td class="py-2 px-3 text-center">${actionBadge}</td>
                    </tr>
                `;
                });

                rowIndex++;
            });

            tbody.innerHTML = html;
        }

        function updateConsolidatedSignal(data, atmStrike) {
            const container = document.getElementById('consolidated-signal');
            container.classList.remove('hidden');

            const times = Object.keys(data).sort().reverse();
            const latest = times.length > 0 ? data[times[0]] : null;

            if (!latest) return;

            const action = latest.consolidated_action;
            let bgColor = 'bg-gray-50';
            let borderColor = 'border-gray-300';
            let textColor = 'text-gray-700';
            let icon = '⏸';

            if (action === 'STRONG BUY') {
                bgColor = 'bg-green-50';
                borderColor = 'border-green-300';
                textColor = 'text-green-700';
                icon = '🚀';
            } else if (action === 'BUY') {
                bgColor = 'bg-green-50';
                borderColor = 'border-green-200';
                textColor = 'text-green-600';
                icon = '📈';
            } else if (action === 'STRONG SELL') {
                bgColor = 'bg-red-50';
                borderColor = 'border-red-300';
                textColor = 'text-red-700';
                icon = '🔻';
            } else if (action === 'SELL') {
                bgColor = 'bg-red-50';
                borderColor = 'border-red-200';
                textColor = 'text-red-600';
                icon = '📉';
            }

            container.innerHTML = `
            <div class="border-l-4 ${borderColor} ${bgColor} rounded-xl p-4 shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="text-3xl">${icon}</div>
                        <div>
                            <h3 class="text-xl font-bold ${textColor}">${action}</h3>
                            <div class="flex items-center space-x-4 text-sm text-gray-600">
                                <span>ATM: <strong class="text-blue-600">${atmStrike}</strong></span>
                                <span>Latest: <strong class="text-gray-800">${times[0]}</strong></span>
                                <span>Intervals: <strong class="text-gray-800">${times.length}</strong></span>
                            </div>
                        </div>
                    </div>
                    <div class="flex space-x-4 text-sm">
                        <div class="text-center">
                            <div class="text-gray-500">Total CE OI</div>
                            <div class="font-bold text-green-600">${latest.total_ce_oi ? latest.total_ce_oi.toLocaleString() : '-'}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-gray-500">Total PE OI</div>
                            <div class="font-bold text-red-600">${latest.total_pe_oi ? latest.total_pe_oi.toLocaleString() : '-'}</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        }

        function updateSummaryCards(data, strikes) {
            const container = document.getElementById('summary-cards');
            container.classList.remove('hidden');

            const times = Object.keys(data).sort().reverse();
            const latest = times.length > 0 ? data[times[0]] : null;

            if (!latest) return;

            // Calculate totals across all strikes
            let totalCE = 0, totalPE = 0;
            strikes.forEach(strike => {
                if (latest.strikes[strike]) {
                    totalCE += latest.strikes[strike].ce_oi || 0;
                    totalPE += latest.strikes[strike].pe_oi || 0;
                }
            });

            container.innerHTML = `
            <div class="bg-white rounded-lg p-3 border border-gray-200 shadow-sm">
                <div class="text-xs text-gray-500">Total CE OI (5 strikes)</div>
                <div class="text-xl font-bold text-green-600">${totalCE.toLocaleString()}</div>
            </div>
            <div class="bg-white rounded-lg p-3 border border-gray-200 shadow-sm">
                <div class="text-xs text-gray-500">Total PE OI (5 strikes)</div>
                <div class="text-xl font-bold text-red-600">${totalPE.toLocaleString()}</div>
            </div>
            <div class="bg-white rounded-lg p-3 border border-gray-200 shadow-sm">
                <div class="text-xs text-gray-500">PCR (Total)</div>
                <div class="text-xl font-bold text-blue-600">${totalPE > 0 ? (totalCE / totalPE).toFixed(3) : 'N/A'}</div>
            </div>
            <div class="bg-white rounded-lg p-3 border border-gray-200 shadow-sm">
                <div class="text-xs text-gray-500">Current Action</div>
                <div class="text-xl font-bold ${latest.consolidated_action.includes('BUY') ? 'text-green-600' : latest.consolidated_action.includes('SELL') ? 'text-red-600' : 'text-gray-600'}">
                    ${latest.consolidated_action}
                </div>
            </div>
        `;
        }
    </script>
@endsection
