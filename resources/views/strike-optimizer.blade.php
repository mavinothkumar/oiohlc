@extends('layouts.app')

@section('title')
    Strike Optimizer – Based on Nifty Open Price
@endsection

@section('content')
    <div class="bg-gray-50 text-gray-800 font-sans p-4 md:p-6">
        <div class="w-full mx-auto">
            <h1 class="text-3xl font-bold mb-6">🎯 Strike Optimizer – Based on Nifty Open</h1>

            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">📊 Analysis Based on Nifty Open Price</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p><strong>Open Price:</strong> {{ number_format($openPrice, 2) }}</p>
                            <p><strong>ATM Strike:</strong> <span class="font-bold text-blue-800">{{ $atmStrike }}</span></p>
                            <p><strong>Strikes Analyzed:</strong> {{ implode(', ', $strikes) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Filter Form --}}
            <form method="GET" class="bg-white rounded-xl shadow border border-gray-200 p-4 mb-8">
                <div class="flex flex-wrap gap-4 items-end">
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Expiry</label>
                        <input type="date" name="expiry" value="{{ $selectedExpiry }}"
                            class="w-full border border-gray-300 rounded px-2 py-2 text-sm bg-white">
                    </div>

                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Date & Time</label>
                        <input type="datetime-local" name="date" value="{{ $selectedDateTime }}"
                            class="w-full border border-gray-300 rounded px-2 py-2 text-sm bg-white"
                            step="60">
                    </div>

                    <div class="flex-none">
                        <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded transition text-sm h-[38px]">
                            🔍 Analyze
                        </button>
                    </div>
                </div>
            </form>

            {{-- Results Table --}}
            @if(count($topResults) > 0)
                @php
                    // Find the highest values in each column using array_column
                    $ceVolValues = array_column($topResults, 'call_volume');
                    $peVolValues = array_column($topResults, 'put_volume');
                    $ceOIValues = array_column($topResults, 'call_oi');
                    $peOIValues = array_column($topResults, 'put_oi');

                    $maxCEVol = !empty($ceVolValues) ? max($ceVolValues) : 0;
                    $maxPEVol = !empty($peVolValues) ? max($peVolValues) : 0;
                    $maxCEOI = !empty($ceOIValues) ? max($ceOIValues) : 0;
                    $maxPEOI = !empty($peOIValues) ? max($peOIValues) : 0;
                @endphp

                <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden mb-6">
                    <div class="p-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold">📊 Strike Combinations Performance</h2>
                        <p class="text-sm text-gray-500">Showing 3 PE strikes (ATM, ATM-100, ATM-200) and 3 CE strikes (ATM, ATM+100, ATM+200)</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">#</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">ATM Strike</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">CE Strikes</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">PE Strikes</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">CE Vol</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">PE Vol</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">CE OI</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">PE OI</th>
                                <th class="px-3 py-2 text-right font-semibold text-gray-600">Start ₹</th>
                                <th class="px-3 py-2 text-right font-semibold text-gray-600">End ₹</th>
                                <th class="px-3 py-2 text-right font-semibold text-gray-600">Return ₹</th>
                                <th class="px-3 py-2 text-right font-semibold text-gray-600">Return %</th>
                                <th class="px-3 py-2 text-right font-semibold text-gray-600">Max DD ₹</th>
                                <th class="px-3 py-2 text-center font-semibold text-gray-600">Cross VWAP</th>
                                <th class="px-3 py-2 text-center font-semibold text-gray-600">Stability</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                            @foreach($topResults as $index => $result)
                                @php
                                    $isAtm = $result['atm_strike'] == $atmStrike;
                                    $isMaxCEVol = $result['call_volume'] == $maxCEVol && $maxCEVol > 0;
                                    $isMaxPEVol = $result['put_volume'] == $maxPEVol && $maxPEVol > 0;
                                    $isMaxCEOI = $result['call_oi'] == $maxCEOI && $maxCEOI > 0;
                                    $isMaxPEOI = $result['put_oi'] == $maxPEOI && $maxPEOI > 0;
                                @endphp
                                <tr class="{{ $isAtm ? 'bg-blue-50 border-2 border-blue-300' : ($index < 5 ? 'bg-green-50' : ($result['crossed_vwap'] ? 'bg-red-50' : '')) }}">
                                    <td class="px-3 py-2 font-medium text-gray-800">{{ $index + 1 }}</td>
                                    <td class="px-3 py-2 font-medium {{ $isAtm ? 'text-blue-800 font-bold' : 'text-gray-800' }}">
                                        {{ $result['atm_strike'] }}
                                        @if($isAtm)
                                            <span class="ml-1 text-xs bg-blue-200 px-1 rounded">ATM</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($result['call_strikes'] as $strike)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ $strike == $result['atm_strike'] ? 'bg-blue-800 text-white' : 'bg-blue-100 text-blue-800' }}">
                                        {{ $strike }}
                                                    @if($strike == $result['atm_strike'])
                                                        <span class="ml-0.5 text-[10px]">★</span>
                                                    @endif
                                    </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($result['put_strikes'] as $strike)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ $strike == $result['atm_strike'] ? 'bg-red-800 text-white' : 'bg-red-100 text-red-800' }}">
                                        {{ $strike }}
                                                    @if($strike == $result['atm_strike'])
                                                        <span class="ml-0.5 text-[10px]">★</span>
                                                    @endif
                                    </span>
                                            @endforeach
                                        </div>
                                    </td>

                                    {{-- CE Vol with box if it's the highest --}}
                                    <td class="px-3 py-2 text-xs text-gray-600">
                                        @if($isMaxCEVol)
                                            <span class="inline-block border-2 border-orange-500 rounded px-1 py-0.5 bg-orange-50 font-bold">
                                    {{ $result['call_volume_formatted'] }}
                                </span>
                                        @else
                                            {{ $result['call_volume_formatted'] }}
                                        @endif
                                    </td>

                                    {{-- PE Vol with box if it's the highest --}}
                                    <td class="px-3 py-2 text-xs text-gray-600">
                                        @if($isMaxPEVol)
                                            <span class="inline-block border-2 border-orange-500 rounded px-1 py-0.5 bg-orange-50 font-bold">
                                    {{ $result['put_volume_formatted'] }}
                                </span>
                                        @else
                                            {{ $result['put_volume_formatted'] }}
                                        @endif
                                    </td>

                                    {{-- CE OI with box if it's the highest --}}
                                    <td class="px-3 py-2 text-xs text-gray-600">
                                        @if($isMaxCEOI)
                                            <span class="inline-block border-2 border-orange-500 rounded px-1 py-0.5 bg-orange-50 font-bold">
                                    {{ $result['call_oi_formatted'] }}
                                </span>
                                        @else
                                            {{ $result['call_oi_formatted'] }}
                                        @endif
                                    </td>

                                    {{-- PE OI with box if it's the highest --}}
                                    <td class="px-3 py-2 text-xs text-gray-600">
                                        @if($isMaxPEOI)
                                            <span class="inline-block border-2 border-orange-500 rounded px-1 py-0.5 bg-orange-50 font-bold">
                                    {{ $result['put_oi_formatted'] }}
                                </span>
                                        @else
                                            {{ $result['put_oi_formatted'] }}
                                        @endif
                                    </td>

                                    <td class="px-3 py-2 text-right font-medium text-gray-800">₹{{ number_format($result['starting_premium'], 2) }}</td>
                                    <td class="px-3 py-2 text-right font-medium text-gray-800">₹{{ number_format($result['ending_premium'], 2) }}</td>
                                    <td class="px-3 py-2 text-right font-medium {{ $result['total_return'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                                        ₹{{ number_format($result['total_return'], 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-right font-medium {{ $result['return_percent'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $result['return_percent'] }}%
                                    </td>
                                    <td class="px-3 py-2 text-right font-medium text-orange-600">
                                        ₹{{ number_format($result['max_drawdown'], 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        @if($result['crossed_vwap'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">❌</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">✅</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-center font-medium">
                            <span class="{{ $result['stability_score'] > 80 ? 'text-green-600' : ($result['stability_score'] > 50 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ $result['stability_score'] }}%
                            </span>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Three Charts: ATM-100, ATM, ATM+100 --}}
                @if(isset($chartData['atm']) && isset($chartData['atm_minus_100']) && isset($chartData['atm_plus_100']))
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        @php
                            $chartConfigs = [
                                [
                                    'key' => 'atm_minus_100',
                                    'label' => 'ATM-100 (' . ($atmStrike - 100) . ')',
                                    'strikes' => $chartData['atm_minus_100']['put_strikes']
                                ],
                                [
                                    'key' => 'atm',
                                    'label' => 'ATM (' . $atmStrike . ')',
                                    'strikes' => [$atmStrike]
                                ],
                                [
                                    'key' => 'atm_plus_100',
                                    'label' => 'ATM+100 (' . ($atmStrike + 100) . ')',
                                    'strikes' => $chartData['atm_plus_100']['call_strikes']
                                ]
                            ];
                        @endphp

                        @foreach($chartConfigs as $config)
                            @php
                                $result = $chartData[$config['key']];
                            @endphp
                            <div class="bg-white rounded-xl shadow border border-gray-200 p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h3 class="font-semibold">{{ $config['label'] }}</h3>
                                        <p class="text-xs text-gray-500">
                                            @if($config['key'] == 'atm_minus_100')
                                                PE: {{ implode(', ', $result['put_strikes']) }}
                                            @elseif($config['key'] == 'atm')
                                                ATM: {{ $atmStrike }}
                                            @else
                                                CE: {{ implode(', ', $result['call_strikes']) }}
                                            @endif
                                        </p>
                                    </div>
                                    <span class="text-xs font-medium {{ $result['return_percent'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $result['return_percent'] }}%
                                    </span>
                                </div>
                                <div class="h-20">
                                    <canvas id="chart_{{ $config['key'] }}" height="60"></canvas>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Start: ₹{{ number_format($result['starting_premium'], 2) }}</span>
                                    <span>End: ₹{{ number_format($result['ending_premium'], 2) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

            @else
                <div class="bg-yellow-100 border border-yellow-300 text-yellow-800 p-4 rounded-lg mb-6">
                    No data found for the selected date. Please check if option chain data exists for {{ $selectedDate }}.
                </div>
            @endif
        </div>
    </div>

    @if(isset($chartData['atm']))
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const chartData = @json($chartData);
                const chartKeys = ['atm_minus_100', 'atm', 'atm_plus_100'];

                chartKeys.forEach((key) => {
                    const result = chartData[key];
                    if (!result) return;

                    new Chart(document.getElementById('chart_' + key), {
                        type: 'line',
                        data: {
                            labels: result.timestamps.map(t => t.substring(11, 16)),
                            datasets: [
                                {
                                    label: 'Premium',
                                    data: result.premium_data,
                                    borderColor: '#35ec9e',
                                    backgroundColor: 'rgba(7,223,60,0.1)',
                                    tension: 0.2,
                                    fill: true,
                                    pointRadius: 0,
                                    yAxisID: 'y'
                                },
                                {
                                    label: 'VWAP',
                                    data: result.vwap_data,
                                    borderColor: '#ff0000',
                                    borderWidth: 1,
                                    borderDash: [3, 3],
                                    pointRadius: 0,
                                    fill: false,
                                    yAxisID: 'y'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                x: { display: false },
                                y: { display: false }
                            }
                        }
                    });
                });
            });
        </script>
    @endif
@endsection
