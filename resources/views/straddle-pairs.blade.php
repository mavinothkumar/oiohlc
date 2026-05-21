@extends('layouts.app')

@section('title', 'OTM Straddle Pairs')

@section('content')
    <div class="container mx-auto px-4 py-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-blue-600">
                    🎯 OTM Straddle Pairs
                </h1>
                <p class="text-gray-600 text-sm">Find the best OTM pairs for premium selling</p>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Round Strike</label>
                    <input type="number" name="round_strike" id="filter-strike"
                        value="{{ $roundStrike }}"
                        step="50"
                        class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div class="flex items-end">
                    <button onclick="fetchPairsData()"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors w-full">
                        <i class="fas fa-sync-alt mr-2"></i> Analyze
                    </button>
                </div>
            </form>

            <div class="mt-3 text-xs text-gray-500 flex flex-wrap gap-4">
                <span>Current Price: <strong id="current-price" class="text-blue-600">Loading...</strong></span>
                <span>Open Price: <strong id="open-price" class="text-gray-800">-</strong></span>
                <span>Round Strike: <strong id="round-strike" class="text-purple-600">{{ $roundStrike }}</strong></span>
                <span>Expiry: <strong id="expiry" class="text-gray-600">{{ $currentExpiry }}</strong></span>
            </div>
        </div>

        <!-- Best Entry Card -->
        <div id="best-entry" class="mb-6 hidden">
            <!-- Will be populated by JS -->
        </div>

        <!-- Pairs Table -->
        <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm overflow-x-auto">
            <div class="flex justify-between items-center mb-3">
                <h2 class="text-lg font-semibold text-gray-800">OTM Pairs (±50 to ±500)</h2>
                <span class="text-xs text-gray-500" id="table-info">Loading...</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[1000px]">
                    <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="py-2 px-3 text-left font-semibold text-gray-700">PE Strike</th>
                        <th class="py-2 px-3 text-right font-semibold text-red-600">PE Premium</th>
                        <th class="py-2 px-3 text-right font-semibold text-red-600">PE Delta</th>
                        <th class="py-2 px-3 text-center font-semibold text-gray-700">|</th>
                        <th class="py-2 px-3 text-left font-semibold text-gray-700">CE Strike</th>
                        <th class="py-2 px-3 text-right font-semibold text-green-600">CE Premium</th>
                        <th class="py-2 px-3 text-right font-semibold text-green-600">CE Delta</th>
                        <th class="py-2 px-3 text-right font-semibold text-purple-600">Difference</th>
                        <th class="py-2 px-3 text-right font-semibold text-blue-600">Sum</th>
                        <th class="py-2 px-3 text-center font-semibold text-gray-700">Win %</th>
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
            fetchPairsData();
        });

        function fetchPairsData() {
            const form = document.getElementById('filter-form');
            const formData = new FormData(form);
            const params = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                params.append(key, value);
            }

            document.getElementById('table-body').innerHTML = '<tr><td colspan="10" class="text-center py-4"><i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i></td></tr>';
            document.getElementById('best-entry').classList.add('hidden');
            document.getElementById('table-info').textContent = 'Loading...';

            fetch(`/trading/api/straddle-pairs-data?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    updateBestEntry(data.best_entry);
                    updateTable(data.pairs);

                    document.getElementById('current-price').textContent = data.current_price;
                    document.getElementById('open-price').textContent = data.open_price;
                    document.getElementById('round-strike').textContent = data.round_strike;
                    document.getElementById('expiry').textContent = data.expiry;
                    document.getElementById('table-info').textContent = `${data.pairs.length} pairs analyzed`;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('table-body').innerHTML = '<tr><td colspan="10" class="text-center py-4 text-red-500">Error loading data</td></tr>';
                });
        }

        function updateBestEntry(entry) {
            const container = document.getElementById('best-entry');

            if (!entry || !entry.pair) {
                container.classList.add('hidden');
                return;
            }

            container.classList.remove('hidden');

            const side = entry.side;
            const sideColor = side === 'CE' ? 'text-green-600' : 'text-red-600';
            const sideBg = side === 'CE' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';
            const pair = entry.pair;
            const strike = side === 'CE' ? pair.ce_strike : pair.pe_strike;
            const premium = side === 'CE' ? pair.ce_premium : pair.pe_premium;

            container.innerHTML = `
            <div class="border-l-4 ${side === 'CE' ? 'border-green-500' : 'border-red-500'} ${sideBg} rounded-xl p-4 shadow-sm border">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="text-3xl">${entry.confidence >= 75 ? '🚀' : '⚡'}</div>
                        <div>
                            <h3 class="text-xl font-bold ${sideColor}">🎯 SELL ${side} @ ${strike}</h3>
                            <div class="flex items-center space-x-4 text-sm text-gray-600">
                                <span>Premium: <strong class="${sideColor}">${premium}</strong></span>
                                <span>Stop Loss: <strong class="text-red-600">${entry.stop_loss}</strong></span>
                                <span>Target: <strong class="text-green-600">${entry.target}</strong></span>
                            </div>
                        </div>
                    </div>
                    <div class="flex space-x-6 text-sm">
                        <div class="text-center">
                            <div class="text-gray-500">Win Probability</div>
                            <div class="text-2xl font-bold ${entry.confidence >= 75 ? 'text-green-600' : entry.confidence >= 60 ? 'text-yellow-600' : 'text-gray-600'}">
                                ${entry.confidence}%
                            </div>
                        </div>
                        <div class="text-center">
                            <div class="text-gray-500">Market Direction</div>
                            <div class="font-bold ${entry.market_direction === 'UP' ? 'text-green-600' : 'text-red-600'}">
                                ${entry.market_direction}
                            </div>
                        </div>
                        <div class="text-center">
                            <div class="text-gray-500">Risk/Reward</div>
                            <div class="font-bold text-blue-600">1:2</div>
                        </div>
                    </div>
                </div>
                <div class="mt-2 pt-2 border-t border-gray-200 grid grid-cols-4 gap-4 text-xs">
                    <div>
                        <span class="text-gray-500">CE Premium:</span>
                        <span class="font-bold text-green-600">${pair.ce_premium}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">PE Premium:</span>
                        <span class="font-bold text-red-600">${pair.pe_premium}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Premium Diff:</span>
                        <span class="font-bold text-purple-600">${pair.difference}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Total Premium:</span>
                        <span class="font-bold text-blue-600">${pair.sum}</span>
                    </div>
                </div>
            </div>
        `;
        }

        function updateTable(pairs) {
            const tbody = document.getElementById('table-body');

            if (!pairs || pairs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-gray-500">No data available</td></tr>';
                return;
            }

            let html = '';
            let rowIndex = 0;

            pairs.forEach(pair => {
                const isEven = rowIndex % 2 === 0;
                const bgClass = isEven ? 'bg-white' : 'bg-gray-50';
                const isBest = pair.is_best;
                const rowClass = isBest ? bgClass + ' border-l-4 border-green-500' : bgClass;

                const winColor = pair.win_probability >= 75 ? 'text-green-600' :
                    pair.win_probability >= 60 ? 'text-yellow-600' : 'text-gray-600';

                html += `
                <tr class="${rowClass} hover:bg-gray-100 transition-colors">
                    <td class="py-2 px-3 text-left font-bold text-red-600">${pair.pe_strike}</td>
                    <td class="py-2 px-3 text-right font-medium text-red-600">${pair.pe_premium}</td>
                    <td class="py-2 px-3 text-right text-gray-600">${pair.pe_delta !== undefined ? pair.pe_delta.toFixed(4) : 'N/A'}</td>
                    <td class="py-2 px-3 text-center text-gray-300">|</td>
                    <td class="py-2 px-3 text-left font-bold text-green-600">${pair.ce_strike}</td>
                    <td class="py-2 px-3 text-right font-medium text-green-600">${pair.ce_premium}</td>
                    <td class="py-2 px-3 text-right text-gray-600">${pair.ce_delta !== undefined ? pair.ce_delta.toFixed(4) : 'N/A'}</td>
                    <td class="py-2 px-3 text-right font-bold text-purple-600">${pair.difference}</td>
                    <td class="py-2 px-3 text-right font-bold text-blue-600">${pair.sum}</td>
                    <td class="py-2 px-3 text-center font-bold ${winColor}">${pair.win_probability}%</td>
                </tr>
            `;
                rowIndex++;
            });

            tbody.innerHTML = html;
        }
    </script>
@endsection
