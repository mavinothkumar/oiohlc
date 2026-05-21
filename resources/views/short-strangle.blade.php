@extends('layouts.app')

@section('title', 'Short Strangle Intraday')

@section('content')
    <div class="container mx-auto px-4 py-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-blue-600">
                    📉 Short Strangle Intraday
                </h1>
                <p class="text-gray-600 text-sm">Sell OTM options for premium decay with defined targets</p>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Open Price</label>
                    <input type="number" name="open_price" id="filter-open"
                        value="{{ $openPrice }}"
                        step="0.05"
                        class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div class="flex items-end">
                    <button onclick="fetchStrangleData()"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors w-full">
                        <i class="fas fa-sync-alt mr-2"></i> Analyze
                    </button>
                </div>
            </form>

            <div class="mt-3 text-xs text-gray-500 flex flex-wrap gap-4">
                <span>Current Price: <strong id="current-price" class="text-blue-600">Loading...</strong></span>
                <span>Open Price: <strong id="open-price" class="text-gray-800">-</strong></span>
                <span>Base Strike: <strong id="base-strike" class="text-purple-600">-</strong></span>
            </div>
        </div>

        <!-- Entry Signal Card -->
        <div id="entry-signal" class="mb-6 hidden">
            <!-- Will be populated by JS -->
        </div>

        <!-- Strangle Legs Table -->
        <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm overflow-x-auto">
            <div class="flex justify-between items-center mb-3">
                <h2 class="text-lg font-semibold text-gray-800">Strangle Legs (based on Open Price)</h2>
                <span class="text-xs text-gray-500" id="table-info">Loading...</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[1200px]">
                    <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="py-2 px-3 text-left font-semibold text-gray-700">Distance</th>
                        <th class="py-2 px-3 text-right font-semibold text-purple-600">PE Theta</th>
                        <th class="py-2 px-3 text-right font-semibold text-red-600">PE Delta</th>
                        <th class="py-2 px-3 text-right font-semibold text-red-600">PE Premium</th>
                        <th class="py-2 px-3 text-center font-semibold text-gray-700">Strike</th>
                        <th class="py-2 px-3 text-right font-semibold text-green-600">CE Premium</th>
                        <th class="py-2 px-3 text-right font-semibold text-green-600">CE Delta</th>
                        <th class="py-2 px-3 text-right font-semibold text-green-600">CE Theta</th>
                        <th class="py-2 px-3 text-right font-semibold text-blue-600">Total Premium</th>
                        <th class="py-2 px-3 text-right font-semibold text-orange-500">Premium Diff</th>
                        <th class="py-2 px-3 text-right font-semibold text-purple-600">Total Theta</th>
                        <th class="py-2 px-3 text-center font-semibold text-gray-700">Safety</th>
                    </tr>
                    </thead>
                    <tbody id="table-body">
                    <!-- Data will be populated by JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setInterval(() => {
                document.getElementById('current-time').textContent = new Date().toLocaleTimeString('en-IN', { hour12: false });
            }, 1000);
            fetchStrangleData();
        });

        function fetchStrangleData() {
            const form = document.getElementById('filter-form');
            const formData = new FormData(form);
            const params = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                params.append(key, value);
            }

            document.getElementById('table-body').innerHTML = '<tr><td colspan="12" class="text-center py-4"><i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i></td></tr>';
            document.getElementById('entry-signal').classList.add('hidden');
            document.getElementById('table-info').textContent = 'Loading...';

            fetch(`/trading/api/short-strangle-data?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    updateEntrySignal(data.best_entry, data);
                    updateTable(data.strangle_legs);

                    document.getElementById('current-price').textContent = data.current_price;
                    document.getElementById('open-price').textContent = data.open_price;
                    document.getElementById('base-strike').textContent = data.strangle_legs.length > 0 ? data.strangle_legs[0].ce_strike - 50 : '-';
                    document.getElementById('table-info').textContent = `Expiry: ${data.expiry} | ${data.strangle_legs.length} legs analyzed`;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('table-body').innerHTML = '<tr><td colspan="12" class="text-center py-4 text-red-500">Error loading data</td></tr>';
                });
        }

        function updateEntrySignal(entry, data) {
            const container = document.getElementById('entry-signal');
            container.classList.remove('hidden');

            const confidenceColor = entry.confidence >= 75 ? 'text-green-600' :
                entry.confidence >= 60 ? 'text-yellow-600' : 'text-gray-600';

            const hasBestStrikes = entry.best_ce_strike && entry.best_pe_strike;

            container.innerHTML = `
            <div class="border-l-4 ${entry.confidence >= 75 ? 'border-green-500' : entry.confidence >= 60 ? 'border-yellow-500' : 'border-gray-500'} bg-gray-50 rounded-xl p-4 shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="text-3xl">${entry.confidence >= 75 ? '✅' : '⏳'}</div>
                        <div>
                            <h3 class="text-xl font-bold ${entry.confidence >= 75 ? 'text-green-700' : 'text-yellow-700'}">
                                ${entry.confidence >= 75 ? 'ENTRY SIGNAL READY' : 'WAITING FOR ENTRY'}
                            </h3>
                            <div class="flex items-center space-x-4 text-sm text-gray-600">
                                <span>Entry Time: <strong>${entry.entry_time}</strong></span>
                                <span>Confidence: <strong class="${confidenceColor}">${entry.confidence}%</strong></span>
                                <span>Reason: ${entry.reason}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex space-x-6 text-sm">
                        <div class="text-center">
                            <div class="text-gray-500">Target</div>
                            <div class="font-bold text-green-600">15 pts</div>
                        </div>
                        <div class="text-center">
                            <div class="text-gray-500">Stop Loss</div>
                            <div class="font-bold text-red-600">30 pts</div>
                        </div>
                        <div class="text-center">
                            <div class="text-gray-500">Current Price</div>
                            <div class="font-bold text-blue-600">${data.current_price}</div>
                        </div>
                    </div>
                </div>

                ${hasBestStrikes ? `
                <div class="mt-3 pt-3 border-t border-gray-200 grid grid-cols-3 gap-4 text-sm">
                    <div class="bg-green-50 p-2 rounded border border-green-200">
                        <div class="text-xs text-gray-500">🟢 SELL CE</div>
                        <div class="font-bold text-green-600">${entry.best_ce_strike}</div>
                        <div class="text-xs text-gray-500">Premium: <strong>${entry.best_ce_premium}</strong></div>
                    </div>
                    <div class="bg-red-50 p-2 rounded border border-red-200">
                        <div class="text-xs text-gray-500">🔴 SELL PE</div>
                        <div class="font-bold text-red-600">${entry.best_pe_strike}</div>
                        <div class="text-xs text-gray-500">Premium: <strong>${entry.best_pe_premium}</strong></div>
                    </div>
                    <div class="bg-blue-50 p-2 rounded border border-blue-200">
                        <div class="text-xs text-gray-500">📊 Total Credit</div>
                        <div class="font-bold text-blue-600">${entry.best_total_premium}</div>
                        <div class="text-xs text-gray-500">Safety: <strong>${entry.best_safety}%</strong></div>
                    </div>
                </div>
                ` : ''}
            </div>
        `;
        }

        function updateTable(legs) {
            const tbody = document.getElementById('table-body');

            if (!legs || legs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="12" class="text-center py-4 text-gray-500">No data available</td></tr>';
                return;
            }

            let html = '';
            let rowIndex = 0;

            legs.forEach(leg => {
                const isEven = rowIndex % 2 === 0;
                const bgClass = isEven ? 'bg-white' : 'bg-gray-50';

                // Highlight the base row (50 distance)
                const rowClass = leg.is_base ? bgClass + ' border-l-4 border-blue-500 font-bold' : bgClass;

                const safetyColor = leg.safety_score >= 70 ? 'text-green-600' :
                    leg.safety_score >= 50 ? 'text-yellow-600' : 'text-red-600';

                html += `
                <tr class="${rowClass} hover:bg-gray-100 transition-colors">
                    <td class="py-2 px-3 text-left text-gray-700">${leg.distance}</td>
                    <td class="py-2 px-3 text-right text-purple-600">${leg.pe_theta !== undefined ? leg.pe_theta.toFixed(4) : 'N/A'}</td>
                    <td class="py-2 px-3 text-right text-red-600">${leg.pe_delta !== undefined ? leg.pe_delta.toFixed(4) : 'N/A'}</td>
                    <td class="py-2 px-3 text-right font-medium text-red-600">${leg.pe_premium}</td>
                    <td class="py-2 px-3 text-center font-bold text-gray-800">${leg.ce_strike} / ${leg.pe_strike}</td>
                    <td class="py-2 px-3 text-right font-medium text-green-600">${leg.ce_premium}</td>
                    <td class="py-2 px-3 text-right text-green-600">${leg.ce_delta !== undefined ? leg.ce_delta.toFixed(4) : 'N/A'}</td>
                    <td class="py-2 px-3 text-right text-green-600">${leg.ce_theta !== undefined ? leg.ce_theta.toFixed(4) : 'N/A'}</td>
                    <td class="py-2 px-3 text-right font-bold text-blue-600">${leg.total_premium}</td>
                    <td class="py-2 px-3 text-right font-bold text-orange-500">${leg.premium_diff}</td>
                    <td class="py-2 px-3 text-right font-bold text-purple-600">${leg.total_theta}</td>
                    <td class="py-2 px-3 text-center font-bold ${safetyColor}">${leg.safety_score}%</td>
                </tr>
            `;
                rowIndex++;
            });

            tbody.innerHTML = html;
        }
    </script>
@endsection
