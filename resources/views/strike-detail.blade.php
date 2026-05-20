@extends('layouts.app')

@section('title', 'NIFTY OI & Volume - Multi Strike Analysis')

@section('content')
    <div class="mx-auto px-4 py-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-blue-600">
                    📊 Multi-Strike OI Analysis
                </h1>
                <p class="text-gray-600 text-sm">5-Strike Profile (ATM ±2) with Build-Up Signals</p>
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
                <span class="flex items-center"><span class="w-3 h-3 bg-green-500 rounded-full mr-1"></span> Top 3% CE Positive</span>
                <span class="flex items-center"><span class="w-3 h-3 bg-red-500 rounded-full mr-1"></span> Top 3% CE Negative</span>
                <span class="flex items-center"><span class="w-3 h-3 bg-blue-500 rounded-full mr-1"></span> Top 3% PE Positive</span>
                <span class="flex items-center"><span class="w-3 h-3 bg-orange-500 rounded-full mr-1"></span> Top 3% PE Negative</span>
                <span class="flex items-center"><span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium">Long Build</span></span>
                <span class="flex items-center"><span class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs font-medium">Short Build</span></span>
                <span class="flex items-center"><span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded text-xs font-medium">Short Cover</span></span>
                <span class="flex items-center"><span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-medium">Long Unwind</span></span>
                <span class="flex items-center"><span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium">STRONG BUY</span></span>
                <span class="flex items-center"><span class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs font-medium">STRONG SELL</span></span>
            </div>

            <div class="overflow-x-auto" style="max-height: 800px; overflow-y: auto;">
                <table class="w-full text-sm min-w-[1500px] border-collapse">
                    <thead class="sticky top-0 z-10 bg-gray-50 shadow-sm">
                    <tr class="border-b border-gray-200">
                        <th class="sticky left-0 z-20 bg-gray-50 py-2 px-3 text-left font-semibold text-gray-700 border-r border-gray-200">Time</th>
                        <th class="py-2 px-3 text-center font-semibold text-gray-700">Strike</th>
                        <th class="py-2 px-3 text-right font-semibold text-green-600">CE OI</th>
                        <th class="py-2 px-3 text-right font-semibold text-green-600">CE Δ (Cur)</th>
                        <th class="py-2 px-3 text-right font-semibold text-green-600">CE Δ (Cum)</th>
                        <th class="py-2 px-3 text-right font-semibold text-green-600">CE % (Cur)</th>
                        <th class="py-2 px-3 text-right font-semibold text-green-600">CE % (Cum)</th>
                        <th class="py-2 px-3 text-center font-semibold text-gray-700">CE Build</th>
                        <th class="py-2 px-3 text-right font-semibold text-red-600">PE OI</th>
                        <th class="py-2 px-3 text-right font-semibold text-red-600">PE Δ (Cur)</th>
                        <th class="py-2 px-3 text-right font-semibold text-red-600">PE Δ (Cum)</th>
                        <th class="py-2 px-3 text-right font-semibold text-red-600">PE % (Cur)</th>
                        <th class="py-2 px-3 text-right font-semibold text-red-600">PE % (Cum)</th>
                        <th class="py-2 px-3 text-center font-semibold text-gray-700">PE Build</th>
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
        let currentStrikeData = [];

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

            document.getElementById('table-body').innerHTML = '<tr><td colspan="15" class="text-center py-4"><i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i></td></tr>';
            document.getElementById('consolidated-signal').classList.add('hidden');
            document.getElementById('summary-cards').classList.add('hidden');
            document.getElementById('table-info').textContent = 'Loading...';

            fetch(`/api/strike-data?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    currentStrikeData = data.data;
                    updateTable(data.data, data.strikes, data.atm_strike);
                    updateConsolidatedSignal(data.data, data.atm_strike);
                    updateSummaryCards(data.data, data.strikes);
                    document.getElementById('table-info').textContent = `ATM: ${data.atm_strike} | Expiry: ${data.expiry} | ${Object.keys(data.data).length} intervals`;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('table-body').innerHTML = '<tr><td colspan="15" class="text-center py-4 text-red-500">Error loading data</td></tr>';
                });
        }

        function getBuildUpBadge(buildUp) {
            if (!buildUp) return '<span class="text-gray-400">-</span>';

            let colorClass = '';
            let label = buildUp;

            switch(buildUp) {
                case 'Long Build':
                    colorClass = 'bg-green-100 text-green-700';
                    break;
                case 'Short Build':
                    colorClass = 'bg-red-100 text-red-700';
                    break;
                case 'Short Cover':
                    colorClass = 'bg-yellow-100 text-yellow-700';
                    break;
                case 'Long Unwind':
                    colorClass = 'bg-blue-100 text-blue-700';
                    break;
                default:
                    colorClass = 'bg-gray-100 text-gray-700';
            }

            return `<span class="px-2 py-0.5 text-xs rounded-full ${colorClass} font-medium">${label}</span>`;
        }

        function getTop3Box(value, allValues, type, isPositive) {
            // Sort all values
            const sorted = [...allValues].sort((a, b) => b - a);

            // Get top 3 positive or bottom 3 negative
            let top3 = [];
            if (isPositive) {
                top3 = sorted.slice(0, 3);
            } else {
                top3 = sorted.slice(-3).reverse();
            }

            // Check if this value is in top 3
            if (top3.includes(value) && value !== 0) {
                return `<span class="px-1 py-0.5 border-2 ${type === 'CE' ? 'border-green-500' : 'border-red-500'} rounded font-bold">${value > 0 ? '+' : ''}${value}%</span>`;
            }

            return `<span>${value > 0 ? '+' : ''}${value}%</span>`;
        }

        function updateTable(data, strikes, atmStrike) {
            const tbody = document.getElementById('table-body');
            const startTimeForTop3 = '09:30';

            if (Object.keys(data).length === 0) {
                tbody.innerHTML = '<tr><td colspan="15" class="text-center py-4 text-gray-500">No data available</td></tr>';
                return;
            }

            // Define baseline time (09:20 or the earliest time after 09:15)
            const baselineTime = '09:20'; // Adjust if your baseline is different

            // Collect all percentage values for Top 3 detection - EXCLUDE baseline
            let allCE_Current = [];
            let allPE_Current = [];
            let allCE_Cumulative = [];
            let allPE_Cumulative = [];

            const times = Object.keys(data).sort().reverse();
            times.forEach(time => {
                // Skip times before 09:30 for Top 3 calculation
                if (time <= startTimeForTop3) return;

                const timeData = data[time];
                strikes.forEach(strike => {
                    const row = timeData.strikes[strike];
                    if (row) {
                        allCE_Current.push(row.ce_current_percent);
                        allPE_Current.push(row.pe_current_percent);
                        allCE_Cumulative.push(row.ce_cumulative_percent);
                        allPE_Cumulative.push(row.pe_cumulative_percent);
                    }
                });
            });

            let html = '';
            let rowIndex = 0;

            times.forEach(time => {
                const timeData = data[time];
                const isEven = rowIndex % 2 === 0;
                const bgClass = isEven ? 'bg-white' : 'bg-gray-50';

                if (rowIndex > 0) {
                    html += `<tr class="border-t-2 border-gray-300"><td colspan="15" class="py-1"></td></tr>`;
                }

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

                    // Top 3 boxes - Check if this is baseline time
                    let ceCurrentBox, ceCumulativeBox, peCurrentBox, peCumulativeBox;

                    if (time === baselineTime) {
                        // Baseline - show normal display without box
                        ceCurrentBox = `<span>${row.ce_current_percent > 0 ? '+' : ''}${row.ce_current_percent}%</span>`;
                        ceCumulativeBox = `<span>${row.ce_cumulative_percent > 0 ? '+' : ''}${row.ce_cumulative_percent}%</span>`;
                        peCurrentBox = `<span>${row.pe_current_percent > 0 ? '+' : ''}${row.pe_current_percent}%</span>`;
                        peCumulativeBox = `<span>${row.pe_cumulative_percent > 0 ? '+' : ''}${row.pe_cumulative_percent}%</span>`;
                    } else {
                        // Normal - apply Top 3 detection
                        ceCurrentBox = getTop3Box(row.ce_current_percent, allCE_Current, 'CE', row.ce_current_percent > 0);
                        ceCumulativeBox = getTop3Box(row.ce_cumulative_percent, allCE_Cumulative, 'CE', row.ce_cumulative_percent > 0);
                        peCurrentBox = getTop3Box(row.pe_current_percent, allPE_Current, 'PE', row.pe_current_percent > 0);
                        peCumulativeBox = getTop3Box(row.pe_cumulative_percent, allPE_Cumulative, 'PE', row.pe_current_percent > 0);
                    }

                    // Build-up badges
                    const ceBuildBadge = getBuildUpBadge(row.ce_build_up);
                    const peBuildBadge = getBuildUpBadge(row.pe_build_up);

                    // Action badge - show only for ATM strike
                    let actionBadge = '';
                    if (isATM) {
                        const action = timeData.consolidated_action || 'WAIT';
                        switch(action) {
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
                    <td class="sticky left-0 ${bgClass} py-2 px-3 text-left font-medium text-gray-800 border-r border-gray-200">${time}</td>
                    <td class="py-2 px-3 text-center ${strikeClass}">${strike}</td>
                    <td class="py-2 px-3 text-right font-medium text-green-600">${row.ce_oi ? formatIndianCompact(row.ce_oi.toLocaleString()) : '-'}</td>
                    <td class="py-2 px-3 text-right ${ceCurrentColor}">${row.ce_current_diff_oi > 0 ? '+' : ''}${formatIndianCompact(row.ce_current_diff_oi.toLocaleString())}</td>
                    <td class="py-2 px-3 text-right ${ceCumulativeColor}">${row.ce_cumulative_diff_oi > 0 ? '+' : ''}${formatIndianCompact(row.ce_cumulative_diff_oi.toLocaleString())}</td>
                    <td class="py-2 px-3 text-right">${ceCurrentBox}</td>
                    <td class="py-2 px-3 text-right">${ceCumulativeBox}</td>
                    <td class="py-2 px-3 text-center">${ceBuildBadge}</td>
                    <td class="py-2 px-3 text-right font-medium text-red-600">${row.pe_oi ? formatIndianCompact(row.pe_oi.toLocaleString()) : '-'}</td>
                    <td class="py-2 px-3 text-right ${peCurrentColor}">${row.pe_current_diff_oi > 0 ? '+' : ''}${formatIndianCompact(row.pe_current_diff_oi.toLocaleString())}</td>
                    <td class="py-2 px-3 text-right ${peCumulativeColor}">${row.pe_cumulative_diff_oi > 0 ? '+' : ''}${formatIndianCompact(row.pe_cumulative_diff_oi.toLocaleString())}</td>
                    <td class="py-2 px-3 text-right">${peCurrentBox}</td>
                    <td class="py-2 px-3 text-right">${peCumulativeBox}</td>
                    <td class="py-2 px-3 text-center">${peBuildBadge}</td>
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

            const action = latest.consolidated_action || 'WAIT';
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

        function formatIndianCompact(value) {
            if (value === null || value === undefined || value === '') return '-';

            const num = Number(String(value).replace(/,/g, ''));
            if (isNaN(num)) return '-';

            const absNum = Math.abs(num);
            const sign = num < 0 ? '-' : '';

            if (absNum >= 10000000) {
                return sign + (absNum / 10000000).toFixed(1).replace(/\.00$/, '') + 'C';
            }

            if (absNum >= 100000) {
                return sign + (absNum / 100000).toFixed(1).replace(/\.00$/, '') + 'L';
            }

            if (absNum >= 1000) {
                return sign + (absNum / 1000).toFixed(1).replace(/\.00$/, '') + 'T';
            }

            return sign + absNum.toString();
        }

        function updateSummaryCards(data, strikes) {
            const container = document.getElementById('summary-cards');
            container.classList.remove('hidden');

            const times = Object.keys(data).sort().reverse();
            const latest = times.length > 0 ? data[times[0]] : null;

            if (!latest) return;

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
                <div class="text-xl font-bold ${latest.consolidated_action?.includes('BUY') ? 'text-green-600' : latest.consolidated_action?.includes('SELL') ? 'text-red-600' : 'text-gray-600'}">
                    ${latest.consolidated_action || 'WAIT'}
                </div>
            </div>
        `;
        }
    </script>
@endsection
