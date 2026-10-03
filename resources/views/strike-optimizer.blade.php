@extends('layouts.app')

@section('title')
    Strike Optimizer – Strategy Combinations
@endsection

@section('content')
    <div class="bg-gray-50 text-gray-800 font-sans px-2 sm:px-3 py-3 min-h-screen w-full">
        <div class="w-full">
            {{-- Top Header --}}
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2.5">
                <h1 class="text-xl sm:text-2xl font-bold flex items-center gap-2 text-slate-900">
                    <span>🎯</span>
                    <span>Strike Optimizer – Strategy Combinations</span>
                </h1>
                
                @if(!empty($selectedStrategy))
                    <div class="flex items-center gap-1.5 text-xs font-mono-num">
                        <span class="px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-900 font-bold border border-blue-200">
                            {{ count($topResults[0]['strategy_legs'] ?? []) }} Legs Configured
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-800 font-bold border border-slate-300">
                            Total Lots: {{ collect($topResults[0]['strategy_legs'] ?? [])->sum('lots') }}L
                        </span>
                    </div>
                @endif
            </div>

            {{-- Compact Info Banner --}}
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-2 mb-3 shadow-xs">
                <div class="flex items-center flex-wrap gap-2 text-xs text-blue-900 font-mono-num">
                    <span class="font-bold text-blue-950 flex items-center gap-1">
                        <span>📊</span>
                        <span>Strategy: <strong class="text-blue-700">{{ $selectedStrategy->name ?? 'Custom' }}</strong></span>
                    </span>
                    <span class="text-slate-300">|</span>
                    <span><strong>Open Spot:</strong> {{ number_format($openPrice, 2) }}</span>
                    <span class="text-slate-300">|</span>
                    <span><strong>ATM Center:</strong> <span class="font-bold text-blue-800">{{ $atmStrike }}</span></span>
                    <span class="text-slate-300">|</span>
                    <span><strong>15 Variations:</strong> <span class="text-slate-600">{{ implode(', ', $strikes) }}</span></span>
                </div>
            </div>

            {{-- Compact Filter Form with Strategy Dropdown --}}
            <form method="GET" class="bg-white rounded-lg shadow-xs border border-gray-200 p-2.5 mb-3">
                <div class="flex flex-wrap gap-2 items-end">
                    {{-- Expiry --}}
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-0.5">Expiry</label>
                        <input type="date" name="expiry" value="{{ $selectedExpiry }}"
                            class="w-36 border border-gray-300 rounded px-2 py-1 text-xs bg-white font-medium focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    {{-- Start Date & Time --}}
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-0.5">Start Time</label>
                        <input type="datetime-local" name="date" value="{{ \Carbon\Carbon::parse($selectedDateTime)->format('Y-m-d\TH:i') }}"
                            class="w-44 border border-gray-300 rounded px-2 py-1 text-xs bg-white font-medium focus:ring-blue-500 focus:border-blue-500" step="60">
                    </div>

                    {{-- End Date & Time --}}
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-0.5">End Time</label>
                        <input type="datetime-local" name="end_date" value="{{ \Carbon\Carbon::parse($selectedEndDateTime)->format('Y-m-d\TH:i') }}"
                            class="w-44 border border-gray-300 rounded px-2 py-1 text-xs bg-white font-medium focus:ring-blue-500 focus:border-blue-500" step="60">
                    </div>

                    {{-- Strategy Dropdown (/backtest/strategies) --}}
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-0.5">Strategy (/backtest/strategies)</label>
                        <select name="strategy_id" class="w-56 border border-gray-300 rounded px-2 py-1 text-xs bg-white font-bold text-slate-800 shadow-xs focus:ring-blue-500 focus:border-blue-500" onchange="this.form.submit()">
                            @foreach($backtestStrategies as $strat)
                                <option value="{{ $strat->id }}" {{ $selectedStrategyId == $strat->id ? 'selected' : '' }}>
                                    {{ $strat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- ATM / Strike Override --}}
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-0.5">ATM Center (Optional)</label>
                        <input type="text" name="selected_strike" value="{{ $selectedStrike ?? '' }}" placeholder="Auto (Open)"
                            class="w-32 border border-gray-300 rounded px-2 py-1 text-xs bg-white focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    {{-- Strike Step --}}
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-0.5">Strike Step</label>
                        <input type="text" name="strike_step" value="{{ $strikeStep ?? '100' }}"
                            class="w-20 border border-gray-300 rounded px-2 py-1 text-xs bg-white focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-3.5 py-1 rounded transition text-xs h-[30px] shadow-xs flex items-center gap-1 active:scale-95">
                            🔍 Analyze
                        </button>
                    </div>
                </div>
            </form>

            {{-- Results Table: Selected Strategy (15 ATM Strikes Variations) --}}
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

                <div class="bg-white rounded-xl shadow-xs border border-gray-200 overflow-hidden mb-6">
                    <div class="p-2.5 px-3 border-b border-gray-200 bg-gray-50/80 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 class="text-sm sm:text-base font-bold text-gray-900 flex items-center gap-1.5">
                                <span>🎯</span>
                                <span>Strike Combinations Performance – {{ $selectedStrategy->name ?? 'Strategy Matrix' }}</span>
                            </h2>
                            <p class="text-[11px] text-gray-500">
                                15 ATM variations computed using <strong>{{ $selectedStrategy->name ?? 'selected strategy' }}</strong> legs across market session
                            </p>
                        </div>
                    </div>

                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full text-xs border-collapse">
                            <thead class="bg-slate-100/90 border-b border-slate-200 text-[10px] uppercase font-bold text-slate-600 tracking-tight select-none">
                            <tr>
                                <th class="px-2 py-2 text-center w-8 whitespace-nowrap">#</th>
                                <th class="px-2.5 py-2 text-left whitespace-nowrap">ATM Center</th>
                                <th class="px-2.5 py-2 text-left min-w-[140px]">CE Strikes</th>
                                <th class="px-2.5 py-2 text-left min-w-[140px]">PE Strikes</th>
                                <th class="px-2.5 py-2 text-right whitespace-nowrap">CE Vol</th>
                                <th class="px-2.5 py-2 text-right whitespace-nowrap">PE Vol</th>
                                <th class="px-2.5 py-2 text-right whitespace-nowrap">CE OI</th>
                                <th class="px-2.5 py-2 text-right whitespace-nowrap">PE OI</th>
                                <th class="px-2.5 py-2 text-right whitespace-nowrap">Start ₹</th>
                                <th class="px-2.5 py-2 text-right whitespace-nowrap">End ₹</th>
                                <th class="px-2.5 py-2 text-right whitespace-nowrap">Return ₹</th>
                                <th class="px-2.5 py-2 text-right whitespace-nowrap">Return %</th>
                                <th class="px-2.5 py-2 text-right whitespace-nowrap">Max Profit</th>
                                <th class="px-2.5 py-2 text-right whitespace-nowrap">Max Loss</th>
                                <th class="px-2.5 py-2 text-center whitespace-nowrap">VWAP</th>
                                <th class="px-2.5 py-2 text-center whitespace-nowrap">Stability</th>
                                <th class="px-2.5 py-2 text-center whitespace-nowrap">Actions</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 font-mono-num text-[11px]">
                            @foreach($topResults as $index => $result)
                                @php
                                    $isAtm = $result['atm_strike'] == $atmStrike;
                                    $isMaxCEVol = $result['call_volume'] == $maxCEVol && $maxCEVol > 0;
                                    $isMaxPEVol = $result['put_volume'] == $maxPEVol && $maxPEVol > 0;
                                    $isMaxCEOI = $result['call_oi'] == $maxCEOI && $maxCEOI > 0;
                                    $isMaxPEOI = $result['put_oi'] == $maxPEOI && $maxPEOI > 0;

                                    $cpaQuery = http_build_query([
                                        'put_strikes' => $result['put_strikes'],
                                        'call_strikes' => $result['call_strikes'],
                                        'expiry' => $selectedExpiry,
                                        'date' => $selectedDateTime,
                                        'chart_view' => 'combined',
                                    ]);
                                    $spaQuery = http_build_query([
                                        'strategy_id' => $selectedStrategyId,
                                        'custom_atm' => $result['atm_strike'],
                                        'expiry' => $selectedExpiry,
                                        'date' => $selectedDate,
                                        'start_time' => \Carbon\Carbon::parse($selectedDateTime)->format('H:i'),
                                        'end_time' => \Carbon\Carbon::parse($selectedEndDateTime)->format('H:i'),
                                    ]);

                                    $latestPremium = $result['premium_data'][count($result['premium_data']) - 1] ?? 0;
                                    $latestVWAP = $result['vwap_data'][count($result['vwap_data']) - 1] ?? 0;
                                    $vwapStatus = $latestPremium < $latestVWAP ? 'below' : 'above';
                                @endphp
                                <tr class="{{ $isAtm ? 'bg-blue-50/90 font-bold ring-1 ring-inset ring-blue-300' : ($index < 5 ? 'bg-emerald-50/25 hover:bg-emerald-50/50' : 'hover:bg-slate-50/80') }} transition-colors">
                                    <td class="px-2 py-1.5 text-center font-bold text-slate-500 whitespace-nowrap">{{ $index + 1 }}</td>
                                    <td class="px-2.5 py-1.5 font-bold {{ $isAtm ? 'text-blue-900' : 'text-slate-900' }} whitespace-nowrap">
                                        {{ $result['atm_strike'] }}
                                        @if($isAtm)
                                            <span class="ml-1 text-[9px] bg-blue-200 text-blue-950 px-1 py-0.2 rounded font-black border border-blue-300">ATM</span>
                                        @endif
                                    </td>
                                    
                                    {{-- Ultra-compact CE Strikes chips --}}
                                    <td class="px-2.5 py-1.5">
                                        <div class="flex flex-wrap items-center gap-1 min-w-[140px] max-w-[260px]">
                                            @forelse($result['call_strikes'] as $strike)
                                                @php
                                                    $legMatch = collect($result['strategy_legs'] ?? [])->firstWhere('strike', $strike);
                                                    $isAtmStrike = $strike == $result['atm_strike'];
                                                    $lots = $legMatch['lots'] ?? 1;
                                                @endphp
                                                <span class="inline-flex items-center gap-0.5 px-1 py-0.5 rounded text-[10px] font-mono leading-none whitespace-nowrap {{ $isAtmStrike ? 'bg-blue-800 text-white font-bold ring-1 ring-blue-900 shadow-xs' : 'bg-blue-50 text-blue-700 border border-blue-200 font-semibold' }}">
                                                    <span>{{ $strike }}</span>
                                                    @if($lots > 1)
                                                        <span class="px-0.5 rounded bg-blue-900/30 text-[9px] font-bold">{{ $lots }}L</span>
                                                    @endif
                                                    @if($isAtmStrike)
                                                        <span class="text-amber-300 text-[8px]">★</span>
                                                    @endif
                                                </span>
                                            @empty
                                                <span class="text-[10px] text-slate-400">—</span>
                                            @endforelse
                                        </div>
                                    </td>

                                    {{-- Ultra-compact PE Strikes chips --}}
                                    <td class="px-2.5 py-1.5">
                                        <div class="flex flex-wrap items-center gap-1 min-w-[140px] max-w-[260px]">
                                            @forelse($result['put_strikes'] as $strike)
                                                @php
                                                    $legMatch = collect($result['strategy_legs'] ?? [])->firstWhere('strike', $strike);
                                                    $isAtmStrike = $strike == $result['atm_strike'];
                                                    $lots = $legMatch['lots'] ?? 1;
                                                @endphp
                                                <span class="inline-flex items-center gap-0.5 px-1 py-0.5 rounded text-[10px] font-mono leading-none whitespace-nowrap {{ $isAtmStrike ? 'bg-rose-800 text-white font-bold ring-1 ring-rose-900 shadow-xs' : 'bg-rose-50 text-rose-700 border border-rose-200 font-semibold' }}">
                                                    <span>{{ $strike }}</span>
                                                    @if($lots > 1)
                                                        <span class="px-0.5 rounded bg-rose-900/30 text-[9px] font-bold">{{ $lots }}L</span>
                                                    @endif
                                                    @if($isAtmStrike)
                                                        <span class="text-amber-300 text-[8px]">★</span>
                                                    @endif
                                                </span>
                                            @empty
                                                <span class="text-[10px] text-slate-400">—</span>
                                            @endforelse
                                        </div>
                                    </td>

                                    {{-- CE Vol --}}
                                    <td class="px-2.5 py-1.5 text-right whitespace-nowrap">
                                        @if($isMaxCEVol)
                                            <span class="inline-block border border-orange-400 rounded px-1.5 py-0.5 bg-orange-50 font-bold text-orange-950 whitespace-nowrap leading-tight">
                                                {{ $result['call_volume_formatted'] }}
                                            </span>
                                        @else
                                            <span class="text-slate-600 font-medium whitespace-nowrap">{{ $result['call_volume_formatted'] }}</span>
                                        @endif
                                    </td>

                                    {{-- PE Vol --}}
                                    <td class="px-2.5 py-1.5 text-right whitespace-nowrap">
                                        @if($isMaxPEVol)
                                            <span class="inline-block border border-orange-400 rounded px-1.5 py-0.5 bg-orange-50 font-bold text-orange-950 whitespace-nowrap leading-tight">
                                                {{ $result['put_volume_formatted'] }}
                                            </span>
                                        @else
                                            <span class="text-slate-600 font-medium whitespace-nowrap">{{ $result['put_volume_formatted'] }}</span>
                                        @endif
                                    </td>

                                    {{-- CE OI --}}
                                    <td class="px-2.5 py-1.5 text-right whitespace-nowrap">
                                        @if($isMaxCEOI)
                                            <span class="inline-block border border-orange-400 rounded px-1.5 py-0.5 bg-orange-50 font-bold text-orange-950 whitespace-nowrap leading-tight">
                                                {{ $result['call_oi_formatted'] }}
                                            </span>
                                        @else
                                            <span class="text-slate-600 font-medium whitespace-nowrap">{{ $result['call_oi_formatted'] }}</span>
                                        @endif
                                    </td>

                                    {{-- PE OI --}}
                                    <td class="px-2.5 py-1.5 text-right whitespace-nowrap">
                                        @if($isMaxPEOI)
                                            <span class="inline-block border border-orange-400 rounded px-1.5 py-0.5 bg-orange-50 font-bold text-orange-950 whitespace-nowrap leading-tight">
                                                {{ $result['put_oi_formatted'] }}
                                            </span>
                                        @else
                                            <span class="text-slate-600 font-medium whitespace-nowrap">{{ $result['put_oi_formatted'] }}</span>
                                        @endif
                                    </td>

                                    {{-- Premiums & Returns --}}
                                    <td class="px-2.5 py-1.5 text-right text-slate-700 font-medium whitespace-nowrap">&#8377;{{ number_format($result['starting_premium'], 1) }}</td>
                                    <td class="px-2.5 py-1.5 text-right text-slate-700 font-medium whitespace-nowrap">&#8377;{{ number_format($result['ending_premium'], 1) }}</td>
                                    <td class="px-2.5 py-1.5 text-right font-bold {{ $result['total_return'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }} whitespace-nowrap">
                                        {{ $result['total_return'] >= 0 ? '+' : '' }}&#8377;{{ number_format($result['total_return'], 1) }}
                                    </td>
                                    <td class="px-2.5 py-1.5 text-right font-bold {{ $result['return_percent'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }} whitespace-nowrap">
                                        {{ $result['return_percent'] >= 0 ? '+' : '' }}{{ number_format($result['return_percent'], 2) }}%
                                    </td>
                                    <td class="px-2.5 py-1.5 text-right font-bold text-emerald-600 whitespace-nowrap">
                                        &#8377;{{ number_format($result['max_profit'], 1) }}
                                    </td>
                                    <td class="px-2.5 py-1.5 text-right font-bold text-rose-600 whitespace-nowrap">
                                        &#8377;{{ number_format($result['max_loss'], 1) }}
                                    </td>

                                    {{-- VWAP --}}
                                    <td class="px-2.5 py-1.5 text-center whitespace-nowrap">
                                        @if($vwapStatus === 'below')
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 leading-none">
                                                &darr; Below
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200 leading-none">
                                                &uarr; Above
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Stability --}}
                                    <td class="px-2.5 py-1.5 text-center font-bold whitespace-nowrap">
                                        <span class="{{ $result['stability_score'] > 80 ? 'text-emerald-600' : ($result['stability_score'] > 50 ? 'text-amber-600' : 'text-rose-600') }}">
                                            {{ number_format($result['stability_score'], 1) }}%
                                        </span>
                                    </td>

                                    {{-- Actions --}}
                                    <td class="px-2.5 py-1.5 text-center whitespace-nowrap">
                                        <div class="flex items-center gap-1 justify-center">
                                            <a
                                                target="_blank"
                                                href="{{ route('strategy.premium.analytics') . '?' . $spaQuery }}"
                                                class="inline-flex items-center justify-center rounded bg-teal-600 hover:bg-teal-700 px-2 py-0.5 text-[10px] font-bold text-white transition shadow-xs active:scale-95"
                                                title="View in Strategy Matrix (Unified Big Chart with Greeks & Health Audit)"
                                            >
                                                Matrix
                                            </a>
                                            <a
                                                target="_blank"
                                                href="{{ url('/combined-premium-analysis') . '?' . $cpaQuery }}"
                                                class="inline-flex items-center justify-center rounded bg-slate-700 hover:bg-slate-800 px-1.5 py-0.5 text-[10px] font-medium text-slate-100 transition shadow-xs active:scale-95"
                                                title="View in Combined Premium Analysis"
                                            >
                                                CPA
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 p-4 rounded-xl mb-6 shadow-sm">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">⚠️</span>
                        <div>
                            <p class="font-bold">No data found for the selected configuration.</p>
                            <p class="text-xs text-yellow-700 mt-0.5">Please check if option chain records exist for expiry <strong>{{ $selectedExpiry }}</strong> on date <strong>{{ $selectedDate }}</strong>.</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
