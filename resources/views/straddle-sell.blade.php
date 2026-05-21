@extends('layouts.app')

@section('title', 'Straddle Premium Decay Strategy')

@section('content')
    <div class="container mx-auto px-4 py-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-blue-600">
                    🎯 Straddle Premium Decay Strategy
                </h1>
                <p class="text-gray-600 text-sm">Sell premium when market moves significantly away from straddle strike</p>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Straddle Strike</label>
                    <input type="number" name="straddle_strike" id="filter-strike"
                        value="23800"
                        step="50"
                        class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Range</label>
                    <select name="range" id="filter-range"
                        class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="200">±200 points</option>
                        <option value="250">±250 points</option>
                        <option value="300" selected>±300 points</option>
                        <option value="350">±350 points</option>
                        <option value="400">±400 points</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button onclick="fetchStraddleData()"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors w-full">
                        <i class="fas fa-sync-alt mr-2"></i> Analyze
                    </button>
                </div>
            </form>

            <div class="mt-3 text-xs text-gray-500 flex flex-wrap gap-4">
                <span>Current Spot: <strong id="current-spot" class="text-blue-600">Loading...</strong></span>
                <span>Straddle: <strong id="straddle-strike" class="text-purple-600">23800</strong></span>
                <span>PE Strike: <strong id="pe-strike" class="text-red-600">23500</strong></span>
                <span>CE Strike: <strong id="ce-strike" class="text-green-600">24100</strong></span>
            </div>
        </div>

        <!-- Straddle Opportunity Card -->
        <div id="straddle-opportunity" class="mb-6 hidden">
            <!-- Will be populated by JS -->
        </div>

        <!-- Premium Comparison -->
        <div id="premium-comparison" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 hidden">
            <!-- Will be populated by JS -->
        </div>

        <!-- Additional Strike Pairs -->
        <div id="additional-pairs" class="mt-6 hidden">
            <h2 class="text-lg font-semibold text-gray-800 mb-3">Additional Strike Pairs for Comparison</h2>
            <div class="grid grid-cols-1 gap-4">
                <div id="pairs-container" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Will be populated by JS -->
                </div>
            </div>
        </div>

        <!-- Market Context -->
        <div id="market-context" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 hidden mt-6">
            <!-- Will be populated by JS -->
        </div>

        <!-- Detailed Table -->
        <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm overflow-x-auto">
            <div class="flex justify-between items-center mb-3">
                <h2 class="text-lg font-semibold text-gray-800">Candle Analysis</h2>
                <span class="text-xs text-gray-500" id="table-info">Loading...</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[800px]">
                    <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="py-2 px-3 text-left font-semibold text-gray-700">Time</th>
                        <th class="py-2 px-3 text-right font-semibold text-gray-700">Price</th>
                        <th class="py-2 px-3 text-right font-semibold text-green-600">CE Premium</th>
                        <th class="py-2 px-3 text-right font-semibold text-red-600">PE Premium</th>
                        <th class="py-2 px-3 text-right font-semibold text-gray-700">Premium Diff</th>
                        <th class="py-2 px-3 text-center font-semibold text-gray-700">Direction</th>
                        <th class="py-2 px-3 text-center font-semibold text-gray-700">Status</th>
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
            fetchStraddleData();
        });

        function fetchStraddleData() {
            const form = document.getElementById('filter-form');
            const formData = new FormData(form);
            const params = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                params.append(key, value);
            }

            document.getElementById('table-body').innerHTML = '<tr><td colspan="7" class="text-center py-4"><i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i></td></tr>';
            document.getElementById('straddle-opportunity').classList.add('hidden');
            document.getElementById('premium-comparison').classList.add('hidden');
            document.getElementById('market-context').classList.add('hidden');
            document.getElementById('table-info').textContent = 'Loading...';

            fetch(`/trading/api/straddle-data?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    updateStraddleOpportunity(data.straddle_data);
                    updatePremiumComparison(data.straddle_data);
                    updateMarketContext(data.market_data);
                    updateTable(data.market_data, data.straddle_data);
                    updateAdditionalPairs(data.additional_pairs);

                    document.getElementById('current-spot').textContent = data.current_spot;
                    document.getElementById('straddle-strike').textContent = data.straddle_data.straddle_strike;
                    document.getElementById('pe-strike').textContent = data.straddle_data.pe_strike;
                    document.getElementById('ce-strike').textContent = data.straddle_data.ce_strike;
                    document.getElementById('table-info').textContent = `Expiry: ${data.expiry} | Data updated`;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('table-body').innerHTML = '<tr><td colspan="7" class="text-center py-4 text-red-500">Error loading data</td></tr>';
                });
        }

        function updateAdditionalPairs(pairs) {
            const container = document.getElementById('additional-pairs');
            const pairsContainer = document.getElementById('pairs-container');

            if (!pairs || pairs.length === 0) {
                container.classList.add('hidden');
                return;
            }

            container.classList.remove('hidden');

            let html = '';
            pairs.forEach(pair => {
                const riskColor = pair.risk_score < 30 ? 'text-green-600' :
                    pair.risk_score < 50 ? 'text-yellow-600' : 'text-red-600';

                html += `
            <div class="bg-white rounded-lg p-3 border ${pair.is_balanced ? 'border-green-300' : 'border-gray-200'} shadow-sm">
                <div class="flex justify-between items-center">
                    <div class="text-xs font-bold ${pair.is_balanced ? 'text-green-600' : 'text-gray-700'}">
                        ${pair.ce_strike} CE / ${pair.pe_strike} PE
                    </div>
                    <div class="text-xs ${riskColor}">
                        Risk: ${pair.risk_score}
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 mt-1 text-xs">
                    <div>
                        <span class="text-gray-500">CE Premium:</span>
                        <span class="font-bold text-green-600">${pair.ce_premium}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">PE Premium:</span>
                        <span class="font-bold text-red-600">${pair.pe_premium}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">CE Delta:</span>
                        <span class="font-bold">${pair.ce_delta !== undefined ? pair.ce_delta.toFixed(4) : 'N/A'}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">PE Delta:</span>
                        <span class="font-bold">${pair.pe_delta !== undefined ? pair.pe_delta.toFixed(4) : 'N/A'}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">CE Theta:</span>
                        <span class="font-bold">${pair.ce_theta !== undefined ? pair.ce_theta.toFixed(4) : 'N/A'}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">PE Theta:</span>
                        <span class="font-bold">${pair.pe_theta !== undefined ? pair.pe_theta.toFixed(4) : 'N/A'}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Premium Diff:</span>
                        <span class="font-bold text-purple-600">${pair.premium_diff}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Status:</span>
                        <span class="font-bold ${pair.is_balanced ? 'text-green-600' : 'text-yellow-600'}">
                            ${pair.is_balanced ? '✓ Balanced' : '⚠️ Imbalanced'}
                        </span>
                    </div>
                </div>
            </div>
        `;
            });

            pairsContainer.innerHTML = html;
        }

        function updateStraddleOpportunity(straddleData) {
            const container = document.getElementById('straddle-opportunity');
            container.classList.remove('hidden');

            const opportunity = straddleData.opportunity;
            const detectedAt = new Date(straddleData.opportunity.detected_at);
            const timeStr = detectedAt.toLocaleTimeString('en-IN', { hour12: false });

            let cardClass = 'bg-gray-50 border-gray-300';
            let title = 'No Opportunity';
            let message = 'Waiting for significant move and premium mismatch';
            let icon = '⏳';

            if (opportunity.type && opportunity.type !== 'NONE') {
                if (opportunity.type.includes('STRONG')) {
                    cardClass = 'bg-green-50 border-green-300';
                    title = '🔥 STRONG ENTRY OPPORTUNITY';
                    message = opportunity.type;
                    icon = '🚀';
                } else {
                    cardClass = 'bg-yellow-50 border-yellow-300';
                    title = '⚡ ENTRY OPPORTUNITY';
                    message = opportunity.type;
                    icon = '⚡';
                }
            }

            container.innerHTML = `
        <div class="border-l-4 ${cardClass} rounded-xl p-4 shadow-sm border border-gray-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="text-3xl">${icon}</div>
                    <div>
                        <h3 class="text-xl font-bold ${opportunity.type && opportunity.type !== 'NONE' ? 'text-green-700' : 'text-gray-700'}">${title}</h3>
                        <div class="flex items-center space-x-4 text-sm text-gray-600">
                            <span>${message}</span>
                            <span class="text-xs text-gray-500">Detected at: ${timeStr}</span>
                        </div>
                    </div>
                </div>
                <div class="flex space-x-4 text-xs">
                    <div class="text-center">
                        <div class="text-gray-500">Move from Open</div>
                        <div class="font-bold ${opportunity.move_from_open > 0 ? 'text-green-600' : 'text-red-600'}">${opportunity.move_from_open > 0 ? '+' : ''}${opportunity.move_from_open}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-gray-500">Premium Diff</div>
                        <div class="font-bold text-purple-600">${straddleData.premium_diff}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-gray-500">Avg Diff</div>
                        <div class="font-bold text-gray-600">${straddleData.avg_premium_diff}</div>
                    </div>
                </div>
            </div>
            <div class="mt-2 pt-2 border-t border-gray-200 grid grid-cols-4 gap-4 text-xs">
                <div>
                    <span class="text-gray-500">Significant Move:</span>
                    <span class="${opportunity.is_significant_move ? 'text-green-600' : 'text-red-600'}">${opportunity.is_significant_move ? '✓ Yes' : '✗ No'}</span>
                </div>
                <div>
                    <span class="text-gray-500">Premium Mismatch:</span>
                    <span class="${opportunity.is_significant_diff ? 'text-green-600' : 'text-red-600'}">${opportunity.is_significant_diff ? '✓ Yes' : '✗ No'}</span>
                </div>
                <div>
                    <span class="text-gray-500">Near Wall:</span>
                    <span class="${opportunity.is_near_wall ? 'text-green-600' : 'text-red-600'}">${opportunity.is_near_wall ? '✓ Yes' : '✗ No'}</span>
                </div>
                <div>
                    <span class="text-gray-500">Slowing Down:</span>
                    <span class="${opportunity.is_slowing_down ? 'text-green-600' : 'text-red-600'}">${opportunity.is_slowing_down ? '✓ Yes' : '✗ No'}</span>
                </div>
            </div>
        </div>
    `;
        }

        function updatePremiumComparison(straddleData) {
            const container = document.getElementById('premium-comparison');
            container.classList.remove('hidden');

            container.innerHTML = `
        <div class="bg-white rounded-lg p-3 border border-green-200 shadow-sm">
            <div class="text-xs text-gray-500">🟢 CE Premium (${straddleData.ce_strike})</div>
            <div class="text-2xl font-bold text-green-600">${straddleData.ce_premium}</div>
            <div class="grid grid-cols-2 gap-2 mt-1 text-xs">
                <div>
                    <span class="text-gray-500">Delta:</span>
                    <span class="font-bold">${straddleData.ce_delta !== undefined ? straddleData.ce_delta.toFixed(4) : 'N/A'}</span>
                </div>
                <div>
                    <span class="text-gray-500">Theta:</span>
                    <span class="font-bold">${straddleData.ce_theta !== undefined ? straddleData.ce_theta.toFixed(4) : 'N/A'}</span>
                </div>
                <div>
                    <span class="text-gray-500">Gamma:</span>
                    <span class="font-bold">${straddleData.ce_gamma !== undefined ? straddleData.ce_gamma.toFixed(4) : 'N/A'}</span>
                </div>
                <div>
                    <span class="text-gray-500">Vega:</span>
                    <span class="font-bold">${straddleData.ce_vega !== undefined ? straddleData.ce_vega.toFixed(4) : 'N/A'}</span>
                </div>
                <div>
                    <span class="text-gray-500">IV:</span>
                    <span class="font-bold">${straddleData.ce_iv !== undefined ? straddleData.ce_iv.toFixed(2) : 'N/A'}</span>
                </div>
                <div>
                    <span class="text-gray-500">OI:</span>
                    <span class="font-bold">${straddleData.ce_oi ? straddleData.ce_oi.toLocaleString() : 'N/A'}</span>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg p-3 border border-red-200 shadow-sm">
            <div class="text-xs text-gray-500">🔴 PE Premium (${straddleData.pe_strike})</div>
            <div class="text-2xl font-bold text-red-600">${straddleData.pe_premium}</div>
            <div class="grid grid-cols-2 gap-2 mt-1 text-xs">
                <div>
                    <span class="text-gray-500">Delta:</span>
                    <span class="font-bold">${straddleData.pe_delta !== undefined ? straddleData.pe_delta.toFixed(4) : 'N/A'}</span>
                </div>
                <div>
                    <span class="text-gray-500">Theta:</span>
                    <span class="font-bold">${straddleData.pe_theta !== undefined ? straddleData.pe_theta.toFixed(4) : 'N/A'}</span>
                </div>
                <div>
                    <span class="text-gray-500">Gamma:</span>
                    <span class="font-bold">${straddleData.pe_gamma !== undefined ? straddleData.pe_gamma.toFixed(4) : 'N/A'}</span>
                </div>
                <div>
                    <span class="text-gray-500">Vega:</span>
                    <span class="font-bold">${straddleData.pe_vega !== undefined ? straddleData.pe_vega.toFixed(4) : 'N/A'}</span>
                </div>
                <div>
                    <span class="text-gray-500">IV:</span>
                    <span class="font-bold">${straddleData.pe_iv !== undefined ? straddleData.pe_iv.toFixed(2) : 'N/A'}</span>
                </div>
                <div>
                    <span class="text-gray-500">OI:</span>
                    <span class="font-bold">${straddleData.pe_oi ? straddleData.pe_oi.toLocaleString() : 'N/A'}</span>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg p-3 border border-purple-200 shadow-sm">
            <div class="text-xs text-gray-500">📊 Premium Difference</div>
            <div class="text-2xl font-bold text-purple-600">${straddleData.premium_diff}</div>
            <div class="flex justify-between text-sm mt-1">
                <span class="text-xs text-gray-500">Avg Diff: <span class="font-bold">${straddleData.avg_premium_diff}</span></span>
                <span class="text-xs ${straddleData.is_mismatch ? 'text-green-600' : 'text-gray-600'}">
                    ${straddleData.is_mismatch ? '✓ Mismatch' : 'Normal'}
                </span>
            </div>
            <div class="grid grid-cols-2 gap-2 mt-1 text-xs">
                <div>
                    <span class="text-gray-500">CE OI:</span>
                    <span class="font-bold">${straddleData.ce_oi ? straddleData.ce_oi.toLocaleString() : 'N/A'}</span>
                </div>
                <div>
                    <span class="text-gray-500">PE OI:</span>
                    <span class="font-bold">${straddleData.pe_oi ? straddleData.pe_oi.toLocaleString() : 'N/A'}</span>
                </div>
            </div>
        </div>
    `;
        }

        function updateMarketContext(marketData) {
            const container = document.getElementById('market-context');
            container.classList.remove('hidden');

            const direction = marketData.candles.length >= 3 ?
                (marketData.candles[marketData.candles.length-1].close > marketData.candles[marketData.candles.length-3].close ? 'UP' : 'DOWN') :
                'NEUTRAL';

            const directionColor = direction === 'UP' ? 'text-green-600' : direction === 'DOWN' ? 'text-red-600' : 'text-gray-600';

            container.innerHTML = `
            <div class="bg-white rounded-lg p-3 border border-gray-200 shadow-sm">
                <div class="text-xs text-gray-500">Current Price</div>
                <div class="text-2xl font-bold text-gray-800">${marketData.current_price}</div>
                <div class="text-xs text-gray-500">Latest: ${marketData.candles.length > 0 ? marketData.candles[marketData.candles.length-1].time : 'N/A'}</div>
            </div>
            <div class="bg-white rounded-lg p-3 border border-gray-200 shadow-sm">
                <div class="text-xs text-gray-500">Market Direction</div>
                <div class="text-xl font-bold ${directionColor}">${direction}</div>
                <div class="text-xs text-gray-500">${marketData.candles.length} candles analyzed</div>
            </div>
            <div class="bg-white rounded-lg p-3 border border-gray-200 shadow-sm">
                <div class="text-xs text-gray-500">Day's Range</div>
                <div class="text-xl font-bold text-gray-800">${(marketData.high_price - marketData.low_price).toFixed(2)}</div>
                <div class="flex justify-between text-xs">
                    <span class="text-green-600">H: ${marketData.high_price}</span>
                    <span class="text-red-600">L: ${marketData.low_price}</span>
                </div>
            </div>
            <div class="bg-white rounded-lg p-3 border border-gray-200 shadow-sm">
                <div class="text-xs text-gray-500">Open vs Current</div>
                <div class="text-xl font-bold ${(marketData.current_price - marketData.open_price) > 0 ? 'text-green-600' : 'text-red-600'}">
                    ${(marketData.current_price - marketData.open_price).toFixed(2)}
                </div>
                <div class="text-xs text-gray-500">Open: ${marketData.open_price}</div>
            </div>
        `;
        }

        function updateTable(marketData, straddleData) {
            const tbody = document.getElementById('table-body');

            if (!marketData.candles || marketData.candles.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-gray-500">No candle data available</td></tr>';
                return;
            }

            let html = '';
            const lastCandles = marketData.candles.slice(-15);

            // For demo, we'll simulate premium data for each candle
            // In production, you'd fetch actual premium data for each time
            let pePremium = straddleData.pe_premium;
            let cePremium = straddleData.ce_premium;

            lastCandles.forEach((candle, index) => {
                const isGreen = candle.close > candle.open;
                const colorClass = isGreen ? 'text-green-600' : 'text-red-600';
                const bgClass = isGreen ? 'bg-green-50' : 'bg-red-50';

                // Simulate premium movement (inverse of price movement)
                const priceMove = candle.close - candle.open;
                cePremium += priceMove * 0.1; // CE moves with price
                pePremium -= priceMove * 0.1; // PE moves inverse to price

                const premiumDiff = cePremium - pePremium;
                const diffColor = premiumDiff > 0 ? 'text-green-600' : 'text-red-600';

                html += `
                <tr class="${bgClass} border-b border-gray-100 hover:bg-gray-50 transition-colors">
                    <td class="py-2 px-3 text-left text-gray-700">${candle.time}</td>
                    <td class="py-2 px-3 text-right font-medium ${colorClass}">${candle.close}</td>
                    <td class="py-2 px-3 text-right text-green-600">${cePremium.toFixed(2)}</td>
                    <td class="py-2 px-3 text-right text-red-600">${pePremium.toFixed(2)}</td>
                    <td class="py-2 px-3 text-right ${diffColor}">${premiumDiff.toFixed(2)}</td>
                    <td class="py-2 px-3 text-center ${colorClass}">${isGreen ? '▲' : '▼'}</td>
                    <td class="py-2 px-3 text-center">
                        ${isGreen ? '🟢 Bullish' : '🔴 Bearish'}
                    </td>
                </tr>
            `;
            });

            tbody.innerHTML = html;
        }
    </script>
@endsection
