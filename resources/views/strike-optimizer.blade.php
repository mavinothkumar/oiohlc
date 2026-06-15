@extends('layouts.app')

@section('title')
    Strike Optimizer – Based on Nifty Open Price
@endsection

@section('content')
    <div class="bg-gray-50 text-gray-800 font-sans p-2 md:p-4">
        <div class="w-full mx-auto">
            <h1 class="text-2xl font-bold mb-4">🎯 Strike Optimizer – Based on Nifty Open</h1>

            {{-- Compact Info Banner --}}
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-2 mb-4">
                <div class="flex items-center flex-wrap gap-2 text-sm text-blue-700">
                    <span class="font-medium text-blue-800">📊 Analysis Based on Nifty Open</span>
                    <span class="text-gray-400">|</span>
                    <span><strong>Open:</strong> {{ number_format($openPrice, 2) }}</span>
                    <span class="text-gray-400">|</span>
                    <span><strong>ATM:</strong> <span class="font-bold text-blue-800">{{ $atmStrike }}</span></span>
                    <span class="text-gray-400">|</span>
                    <span><strong>Strikes:</strong> {{ implode(', ', $strikes) }}</span>
                </div>
            </div>

            {{-- Compact Filter Form --}}
            <form method="GET" class="bg-white rounded-lg shadow border border-gray-200 p-3 mb-4">
                <div class="flex flex-wrap gap-2 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Expiry</label>
                        <input type="date" name="expiry" value="{{ $selectedExpiry }}"
                            class="w-40 border border-gray-300 rounded px-2 py-1.5 text-sm bg-white">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Date & Time</label>
                        <input type="datetime-local" name="date" value="{{ \Carbon\Carbon::parse($selectedDateTime)->format('Y-m-d\TH:i') }}"
                            class="w-48 border border-gray-300 rounded px-2 py-1.5 text-sm bg-white" step="60">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Date & Time</label>
                        <input type="datetime-local" name="end_date" value="{{ $selectedEndDateTime }}"
                            class="w-48 border border-gray-300 rounded px-2 py-1.5 text-sm bg-white" step="60">
                    </div>

                    <div>
                        <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-1.5 rounded transition text-sm h-[34px]">
                            🔍 Analyze
                        </button>
                    </div>
                </div>
            </form>

            {{-- Results Table: Standard (ATM strikes) --}}
            @if(count($topResults) > 0)
                @php
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
                    <div class="p-3 border-b border-gray-200">
                        <h2 class="text-lg font-semibold">📊 Strike Combinations Performance (ATM Strikes)</h2>
                        <p class="text-xs text-gray-500">PE: ATM, ATM-100, ATM-200 | CE: ATM, ATM+100, ATM+200</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">#</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">ATM</th>
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
                                <th class="px-3 py-2 text-center font-semibold text-gray-600">VWAP</th>
                                <th class="px-3 py-2 text-center font-semibold text-gray-600">Stability</th>
                                <th class="px-3 py-2 text-center font-semibold text-gray-600">View</th>
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
                                    $date = $selectedDateTime;
                                    $query = http_build_query([
                                        'put_strikes' => $result['put_strikes'],
                                        'call_strikes' => $result['call_strikes'],
                                        'expiry' => $selectedExpiry,
                                        'date' => $date,
                                        'chart_view' => 'combined',
                                    ]);
                                    // VWAP status: Green if price below VWAP (good), Red if above VWAP (bad)
                                    $latestPremium = $result['premium_data'][count($result['premium_data']) - 1] ?? 0;
                                    $latestVWAP = $result['vwap_data'][count($result['vwap_data']) - 1] ?? 0;
                                    $vwapStatus = $latestPremium < $latestVWAP ? 'below' : 'above';
                                    $vwapColor = $vwapStatus === 'below' ? 'green' : 'red';
                                @endphp
                                <tr class="{{ $isAtm ? 'bg-blue-50 border-2 border-blue-300' : ($index < 5 ? 'bg-green-50' : '') }}">
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
                                                <span
                                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ $strike == $result['atm_strike'] ? 'bg-blue-800 text-white' : 'bg-blue-100 text-blue-800' }}">
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
                                                <span
                                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ $strike == $result['atm_strike'] ? 'bg-red-800 text-white' : 'bg-red-100 text-red-800' }}">
                                                    {{ $strike }}
                                                    @if($strike == $result['atm_strike'])
                                                        <span class="ml-0.5 text-[10px]">★</span>
                                                    @endif
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>

                                    <td class="px-3 py-2 text-xs text-gray-600">
                                        @if($isMaxCEVol)
                                            <span class="inline-block border-2 border-orange-500 rounded px-1 py-0.5 bg-orange-50 font-bold">
                                                {{ $result['call_volume_formatted'] }}
                                            </span>
                                        @else
                                            {{ $result['call_volume_formatted'] }}
                                        @endif
                                    </td>

                                    <td class="px-3 py-2 text-xs text-gray-600">
                                        @if($isMaxPEVol)
                                            <span class="inline-block border-2 border-orange-500 rounded px-1 py-0.5 bg-orange-50 font-bold">
                                                {{ $result['put_volume_formatted'] }}
                                            </span>
                                        @else
                                            {{ $result['put_volume_formatted'] }}
                                        @endif
                                    </td>

                                    <td class="px-3 py-2 text-xs text-gray-600">
                                        @if($isMaxCEOI)
                                            <span class="inline-block border-2 border-orange-500 rounded px-1 py-0.5 bg-orange-50 font-bold">
                                                {{ $result['call_oi_formatted'] }}
                                            </span>
                                        @else
                                            {{ $result['call_oi_formatted'] }}
                                        @endif
                                    </td>

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
                                        @if($vwapStatus === 'below')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                                </svg>
                                                Below
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                                                </svg>
                                                Above
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-center font-medium">
                                        <span class="{{ $result['stability_score'] > 80 ? 'text-green-600' : ($result['stability_score'] > 50 ? 'text-yellow-600' : 'text-red-600') }}">
                                            {{ $result['stability_score'] }}%
                                        </span>
                                    </td>
                                    <td>
                                        <a
                                            target="_blank"
                                            href="{{ url('/combined-premium-analysis') . '?' . $query }}"
                                            class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 py-1 text-xs font-medium text-white transition hover:bg-emerald-700"
                                        >
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Results Table: OTM (No ATM strike) --}}
                @if(count($topResultsOTM) > 0)
                    @php
                        $ceVolValuesOTM = array_column($topResultsOTM, 'call_volume');
                        $peVolValuesOTM = array_column($topResultsOTM, 'put_volume');
                        $ceOIValuesOTM = array_column($topResultsOTM, 'call_oi');
                        $peOIValuesOTM = array_column($topResultsOTM, 'put_oi');
                        $maxCEVolOTM = !empty($ceVolValuesOTM) ? max($ceVolValuesOTM) : 0;
                        $maxPEVolOTM = !empty($peVolValuesOTM) ? max($peVolValuesOTM) : 0;
                        $maxCEOIOTM = !empty($ceOIValuesOTM) ? max($ceOIValuesOTM) : 0;
                        $maxPEOIOTM = !empty($peOIValuesOTM) ? max($peOIValuesOTM) : 0;
                    @endphp

                    <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden mb-6">
                        <div class="p-3 border-b border-gray-200">
                            <h2 class="text-lg font-semibold">📊 Strike Combinations Performance (OTM Only – No ATM)</h2>
                            <p class="text-xs text-gray-500">PE: ATM-300, ATM-200, ATM-100 | CE: ATM+100, ATM+200, ATM+300</p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-600">#</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-600">ATM Base</th>
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
                                    <th class="px-3 py-2 text-center font-semibold text-gray-600">VWAP</th>
                                    <th class="px-3 py-2 text-center font-semibold text-gray-600">Stability</th>
                                    <th class="px-3 py-2 text-center font-semibold text-gray-600">View</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                @foreach($topResultsOTM as $index => $result)
                                    @php
                                        $isAtm = $result['atm_strike'] == $atmStrike;
                                        $isMaxCEVol = $result['call_volume'] == $maxCEVolOTM && $maxCEVolOTM > 0;
                                        $isMaxPEVol = $result['put_volume'] == $maxPEVolOTM && $maxPEVolOTM > 0;
                                        $isMaxCEOI = $result['call_oi'] == $maxCEOIOTM && $maxCEOIOTM > 0;
                                        $isMaxPEOI = $result['put_oi'] == $maxPEOIOTM && $maxPEOIOTM > 0;
                                        $date = $selectedDateTime;
                                        $query = http_build_query([
                                            'put_strikes' => $result['put_strikes'],
                                            'call_strikes' => $result['call_strikes'],
                                            'expiry' => $selectedExpiry,
                                            'date' => $date,
                                            'chart_view' => 'combined',
                                        ]);
                                        $latestPremium = $result['premium_data'][count($result['premium_data']) - 1] ?? 0;
                                        $latestVWAP = $result['vwap_data'][count($result['vwap_data']) - 1] ?? 0;
                                        $vwapStatus = $latestPremium < $latestVWAP ? 'below' : 'above';
                                        $vwapColor = $vwapStatus === 'below' ? 'green' : 'red';
                                    @endphp
                                    <tr class="{{ $index < 3 ? 'bg-purple-50' : '' }}">
                                        <td class="px-3 py-2 font-medium text-gray-800">{{ $index + 1 }}</td>
                                        <td class="px-3 py-2 font-medium text-gray-800">
                                            {{ $result['atm_strike'] }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($result['call_strikes'] as $strike)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                        {{ $strike }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($result['put_strikes'] as $strike)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                        {{ $strike }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </td>

                                        <td class="px-3 py-2 text-xs text-gray-600">
                                            @if($isMaxCEVol)
                                                <span class="inline-block border-2 border-orange-500 rounded px-1 py-0.5 bg-orange-50 font-bold">
                                                    {{ $result['call_volume_formatted'] }}
                                                </span>
                                            @else
                                                {{ $result['call_volume_formatted'] }}
                                            @endif
                                        </td>

                                        <td class="px-3 py-2 text-xs text-gray-600">
                                            @if($isMaxPEVol)
                                                <span class="inline-block border-2 border-orange-500 rounded px-1 py-0.5 bg-orange-50 font-bold">
                                                    {{ $result['put_volume_formatted'] }}
                                                </span>
                                            @else
                                                {{ $result['put_volume_formatted'] }}
                                            @endif
                                        </td>

                                        <td class="px-3 py-2 text-xs text-gray-600">
                                            @if($isMaxCEOI)
                                                <span class="inline-block border-2 border-orange-500 rounded px-1 py-0.5 bg-orange-50 font-bold">
                                                    {{ $result['call_oi_formatted'] }}
                                                </span>
                                            @else
                                                {{ $result['call_oi_formatted'] }}
                                            @endif
                                        </td>

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
                                            @if($vwapStatus === 'below')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                                    </svg>
                                                    Below
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                                                    </svg>
                                                    Above
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-center font-medium">
                                            <span class="{{ $result['stability_score'] > 80 ? 'text-green-600' : ($result['stability_score'] > 50 ? 'text-yellow-600' : 'text-red-600') }}">
                                                {{ $result['stability_score'] }}%
                                            </span>
                                        </td>
                                        <td>
                                            <a
                                                target="_blank"
                                                href="{{ url('/combined-premium-analysis') . '?' . $query }}"
                                                class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 py-1 text-xs font-medium text-white transition hover:bg-emerald-700"
                                            >
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

            @else
                <div class="bg-yellow-100 border border-yellow-300 text-yellow-800 p-4 rounded-lg mb-6">
                    No data found for the selected date. Please check if option chain data exists for {{ $selectedDate }}.
                </div>
            @endif
        </div>
    </div>
@endsection
