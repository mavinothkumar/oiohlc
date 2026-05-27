@extends('layouts.app')

@section('title')
    HLC
@endsection

@section('content')

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div class="bg-gray-900 text-white font-sans p-4 md:p-8">
        <div class="max-w-full mx-auto">
            <h1 class="text-3xl font-bold mb-6">📊 Option Greek Monitor – Short Strangle / Straddle</h1>

            {{-- Filter Card --}}
            <form method="GET" class="bg-gray-800 rounded-2xl p-6 mb-8 shadow-lg">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 items-end">
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Expiry</label>
                        <select name="expiry" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white">
                            @foreach($expiries as $exp)
                                <option value="{{ $exp }}" {{ $selectedExpiry == $exp ? 'selected' : '' }}>{{ $exp }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Date</label>
                        <input type="date" name="date" value="{{ $selectedDate }}" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Put Strike (PE)</label>
                        <select name="put_strike" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white">
                            <option value="">-- Select --</option>
                            @foreach($strikes as $s)
                                <option value="{{ $s }}" {{ $putStrike == $s ? 'selected' : '' }}>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Call Strike (CE)</label>
                        <select name="call_strike" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white">
                            <option value="">-- Select --</option>
                            @foreach($strikes as $s)
                                <option value="{{ $s }}" {{ $callStrike == $s ? 'selected' : '' }}>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Entry Premium (Combined)</label>
                        <input type="number" step="0.01" name="enter_price" value="{{ $enterPrice }}" placeholder="eg 120.50"
                            class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white">
                    </div>
                    <div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded-lg font-semibold transition">Load Data</button>
                    </div>
                </div>

            </form>

            @if($data->isEmpty())
                @if($putStrike && $callStrike)
                    <div class="bg-yellow-600 text-white p-4 rounded-lg">No data found for the selected filters.</div>
                @endif
            @else
                {{-- Combined Premium Chart --}}
                <div class="bg-gray-800 rounded-2xl p-6 mb-8 shadow-lg">
                    <h2 class="text-xl font-semibold mb-4">💵 Combined Premium (Put LTP + Call LTP)</h2>
                    <canvas id="combinedChart" height="100"></canvas>
                </div>

                {{-- Greeks Grid --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Net Vega --}}
                    <div class="bg-gray-800 rounded-2xl p-6 shadow-lg">
                        <h2 class="text-lg font-semibold mb-2">📈 Net Vega
                            <span class="text-sm text-red-400">(Short = negative)</span>
                        </h2>
                        <p class="text-xs text-gray-400 mb-3">Rise in IV hurts the position. Fall in IV helps.</p>
                        <canvas id="vegaChart" height="100"></canvas>
                    </div>
                    {{-- Net Theta --}}
                    <div class="bg-gray-800 rounded-2xl p-6 shadow-lg">
                        <h2 class="text-lg font-semibold mb-2">⏳ Net Theta
                            <span class="text-sm text-green-400">(Short = positive)</span>
                        </h2>
                        <p class="text-xs text-gray-400 mb-3">Time decay works in your favour.</p>
                        <canvas id="thetaChart" height="100"></canvas>
                    </div>
                    {{-- Net Gamma --}}
                    <div class="bg-gray-800 rounded-2xl p-6 shadow-lg">
                        <h2 class="text-lg font-semibold mb-2">🎢 Net Gamma
                            <span class="text-sm text-red-400">(Short = negative)</span>
                        </h2>
                        <p class="text-xs text-gray-400 mb-3">Large moves accelerate losses.</p>
                        <canvas id="gammaChart" height="100"></canvas>
                    </div>
                    {{-- Net Delta --}}
                    <div class="bg-gray-800 rounded-2xl p-6 shadow-lg">
                        <h2 class="text-lg font-semibold mb-2">🎯 Net Delta</h2>
                        <p class="text-xs text-gray-400 mb-3">Directional exposure (should stay near zero).</p>
                        <canvas id="deltaChart" height="100"></canvas>
                    </div>
                    {{-- IV (individual options) --}}
                    <div class="bg-gray-800 rounded-2xl p-6 shadow-lg">
                        <h2 class="text-lg font-semibold mb-2">🌡️ Implied Volatility (IV)</h2>
                        <canvas id="ivChart" height="100"></canvas>
                    </div>
                    {{-- POP (individual options) --}}
                    <div class="bg-gray-800 rounded-2xl p-6 shadow-lg">
                        <h2 class="text-lg font-semibold mb-2">🎲 Probability of Profit (POP) %</h2>
                        <canvas id="popChart" height="100"></canvas>
                    </div>
                </div>
            @endif
        </div>

        @if($data->isNotEmpty())
            <script>
                // Common labels (time)
                const labels = @json($labels);

                // Combined premium with entry line
                new Chart(document.getElementById('combinedChart'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Combined Premium',
                            data: @json($combinedLtp),
                            borderColor: '#fbbf24',
                            backgroundColor: 'rgba(251,191,36,0.1)',
                            tension: 0.2,
                            fill: true,
                            pointRadius: 0
                        },
                                @if($enterPrice)
                            {
                                label: 'Entry Premium ({{ $enterPrice }})',
                                data: Array(labels.length).fill({{ $enterPrice }}),
                                borderColor: '#34d399',
                                borderWidth: 2,
                                borderDash: [5, 5],
                                pointRadius: 0,
                                fill: false
                            }
                            @endif
                        ]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { labels: { color: '#ccc' } } },
                        scales: {
                            x: { ticks: { color: '#aaa', maxTicksLimit: 10 } },
                            y: { ticks: { color: '#aaa' }, grid: { color: '#374151' } }
                        }
                    }
                });

                // Helper to draw a single-line chart
                function simpleLine (canvasId, label, data, color) {
                    new Chart(document.getElementById(canvasId), {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: label,
                                data: data,
                                borderColor: color,
                                backgroundColor: 'transparent',
                                tension: 0.2,
                                pointRadius: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: { legend: { labels: { color: '#ccc' } } },
                            scales: {
                                x: { ticks: { color: '#aaa', maxTicksLimit: 10 } },
                                y: { ticks: { color: '#aaa' }, grid: { color: '#374151' } }
                            }
                        }
                    });
                }

                // Dual-line chart for IV / POP
                function dualLine (canvasId, label1, data1, color1, label2, data2, color2) {
                    new Chart(document.getElementById(canvasId), {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [
                                { label: label1, data: data1, borderColor: color1, tension: 0.2, pointRadius: 0 },
                                { label: label2, data: data2, borderColor: color2, tension: 0.2, pointRadius: 0 }
                            ]
                        },
                        options: {
                            responsive: true,
                            plugins: { legend: { labels: { color: '#ccc' } } },
                            scales: {
                                x: { ticks: { color: '#aaa', maxTicksLimit: 10 } },
                                y: { ticks: { color: '#aaa' }, grid: { color: '#374151' } }
                            }
                        }
                    });
                }

                // Draw Greeks
                simpleLine('vegaChart', 'Net Vega', @json($netVega), '#f87171');
                simpleLine('thetaChart', 'Net Theta', @json($netTheta), '#4ade80');
                simpleLine('gammaChart', 'Net Gamma', @json($netGamma), '#f97316');
                simpleLine('deltaChart', 'Net Delta', @json($netDelta), '#60a5fa');

                dualLine('ivChart', 'Put IV', @json($putIv), '#a78bfa', 'Call IV', @json($callIv), '#f472b6');
                dualLine('popChart', 'Put POP%', @json($putPop), '#a78bfa', 'Call POP%', @json($callPop), '#f472b6');
            </script>
        @endif
    </div>
@endsection
