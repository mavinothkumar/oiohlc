@extends('layouts.app')

@section('title')
    Matching Strike Analysis – Current & Next Week Expiry
@endsection

@section('content')
    <div class="bg-slate-50 text-slate-800 font-sans px-2 sm:px-4 py-3 min-h-screen w-full">
        <div class="w-full">
            {{-- Header Row --}}
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
                        <span>⚖️</span>
                        <span>Matching Strike Analysis</span>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 border border-indigo-200">
                            Current vs Next Week Expiry
                        </span>
                    </h1>
                    <p class="text-xs text-slate-600 mt-0.5">
                        Matches CE and PE strikes in target price range (&#8377;{{ number_format($minPrice, 0) }} - &#8377;{{ number_format($maxPrice, 0) }}) with max price difference &le; &#8377;{{ number_format($maxPriceDiff, 1) }}
                    </p>
                </div>

                {{-- Quick Presets --}}
                <div class="flex items-center gap-1.5 flex-wrap text-xs font-mono-num">
                    <span class="text-[11px] font-bold text-slate-500 uppercase mr-1">Quick Range:</span>
                    <a href="{{ request()->fullUrlWithQuery(['min_price' => 30, 'max_price' => 60, 'max_delta' => 3.0]) }}"
                       class="px-2 py-0.5 rounded border text-[11px] font-semibold transition {{ ($minPrice == 30 && $maxPrice == 60) ? 'bg-indigo-600 text-white border-indigo-700 shadow-xs' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100' }}">
                        &#8377;30 - &#8377;60 (Default)
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['min_price' => 20, 'max_price' => 50, 'max_delta' => 3.0]) }}"
                       class="px-2 py-0.5 rounded border text-[11px] font-semibold transition {{ ($minPrice == 20 && $maxPrice == 50) ? 'bg-indigo-600 text-white border-indigo-700 shadow-xs' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100' }}">
                        &#8377;20 - &#8377;50
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['min_price' => 50, 'max_price' => 100, 'max_delta' => 4.0]) }}"
                       class="px-2 py-0.5 rounded border text-[11px] font-semibold transition {{ ($minPrice == 50 && $maxPrice == 100) ? 'bg-indigo-600 text-white border-indigo-700 shadow-xs' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100' }}">
                        &#8377;50 - &#8377;100
                    </a>
                </div>
            </div>

            {{-- Filter Form --}}
            <form method="GET" class="bg-white rounded-xl shadow-xs border border-slate-200 p-3 mb-4">
                <div class="flex flex-wrap items-end gap-2.5">
                    {{-- Date --}}
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Trading Date</label>
                        <select name="date" class="w-36 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs bg-white font-semibold text-slate-800 shadow-xs focus:ring-indigo-500 focus:border-indigo-500" onchange="this.form.submit()">
                            @foreach($availableDates as $ad)
                                <option value="{{ $ad }}" {{ $selectedDate == $ad ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::parse($ad)->format('d M Y') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Min Price --}}
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Min Price (&#8377;)</label>
                        <input type="number" step="1" name="min_price" value="{{ $minPrice }}"
                               class="w-24 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs bg-white font-mono font-bold text-slate-800 shadow-xs focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    {{-- Max Price --}}
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Max Price (&#8377;)</label>
                        <input type="number" step="1" name="max_price" value="{{ $maxPrice }}"
                               class="w-24 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs bg-white font-mono font-bold text-slate-800 shadow-xs focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    {{-- Max Price Diff / Delta Tolerance --}}
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1" title="Maximum price difference between CE and PE">
                            Max Price Diff (&#8377;)
                        </label>
                        <input type="number" step="0.5" name="max_delta" value="{{ $maxPriceDiff }}"
                               class="w-28 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs bg-white font-mono font-bold text-slate-800 shadow-xs focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    {{-- ATM Override --}}
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Custom ATM (Optional)</label>
                        <input type="text" name="custom_atm" value="{{ $customAtm ?? '' }}" placeholder="Auto Spot"
                               class="w-28 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs bg-white font-mono text-slate-800 shadow-xs focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex items-center gap-1.5">
                        <button type="submit"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 py-1.5 rounded-lg transition text-xs h-[32px] shadow-xs flex items-center gap-1 active:scale-95">
                            🔍 Filter
                        </button>

                        <a href="{{ route('matching.strike.analysis') }}"
                           class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold px-3 py-1.5 rounded-lg transition text-xs h-[32px] border border-slate-300 flex items-center">
                            Reset
                        </a>
                    </div>

                    {{-- Snapshot Timestamp Indicator --}}
                    @if($actualTs)
                        <div class="ml-auto text-xs font-mono-num text-slate-500 self-center">
                            Captured: <span class="text-slate-800 font-bold">{{ \Carbon\Carbon::parse($actualTs)->format('d M Y, H:i') }}</span>
                        </div>
                    @endif
                </div>
            </form>

            {{-- ═══════════════════════════════════════════════════════════════════ --}}
            {{-- TWO COLUMNS: 1st Current Week Expiry, 2nd Next Week Expiry           --}}
            {{-- ═══════════════════════════════════════════════════════════════════ --}}
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                @forelse($columnsData as $colIndex => $col)
                    <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden flex flex-col">
                        {{-- Column Card Header --}}
                        <div class="p-3 border-b border-slate-200 bg-slate-50/90 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-base">{{ $col['is_current'] ? '⚡' : '📅' }}</span>
                                    <h2 class="text-sm sm:text-base font-black text-slate-900 tracking-tight">
                                        {{ $col['label'] }}:
                                        <span class="text-indigo-700 font-mono-num ml-1">
                                            {{ \Carbon\Carbon::parse($col['expiry'])->format('d M Y') }}
                                        </span>
                                    </h2>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold font-mono-num {{ $col['is_current'] ? 'bg-amber-100 text-amber-900 border border-amber-200' : 'bg-blue-100 text-blue-900 border border-blue-200' }}">
                                        {{ $col['is_current'] ? 'CURRENT' : 'NEXT' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 text-xs font-mono-num text-slate-500 mt-0.5">
                                    <span>Spot: <strong class="text-slate-800">&#8377;{{ number_format($col['spot_price'], 2) }}</strong></span>
                                    <span>&bull;</span>
                                    <span>ATM: <strong class="text-indigo-800 font-black">{{ $col['atm_strike'] }}</strong></span>
                                    <span>&bull;</span>
                                    <span>In-Range: <strong class="text-slate-700">{{ $col['ce_count'] }} CE / {{ $col['pe_count'] }} PE</strong></span>
                                </div>
                            </div>

                            {{-- Matched Badges Count --}}
                            <div class="flex items-center gap-1.5 text-xs font-mono-num">
                                <span class="px-2.5 py-1 rounded-lg font-bold border {{ count($col['matched_pairs']) > 0 ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-slate-100 text-slate-600 border-slate-200' }}">
                                    🎯 {{ count($col['matched_pairs']) }} Matched Pair{{ count($col['matched_pairs']) === 1 ? '' : 's' }}
                                </span>
                                @if(count($col['matched_pairs']) > 0)
                                    <span class="px-2 py-1 rounded-lg bg-slate-100 text-slate-700 border border-slate-200 font-semibold" title="Average combined strangle premium">
                                        Avg: &#8377;{{ number_format($col['avg_combined'], 2) }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Table of Matched Pairs --}}
                        <div class="overflow-x-auto custom-scrollbar flex-1">
                            @if(count($col['matched_pairs']) > 0)
                                <table class="w-full text-xs border-collapse">
                                    <thead class="bg-slate-100/90 border-b border-slate-200 text-[10px] uppercase font-bold text-slate-600 tracking-tight select-none">
                                    <tr>
                                        {{-- 1. Delta (CE) --}}
                                        <th class="px-2 py-2 text-right whitespace-nowrap text-blue-800 bg-blue-50/40" title="Call Delta (Δ)">
                                            CE &Delta;
                                        </th>
                                        {{-- 2. Price (CE) --}}
                                        <th class="px-2 py-2 text-right whitespace-nowrap text-blue-900 bg-blue-50/60 font-black" title="Call LTP Price">
                                            CE Price
                                        </th>
                                        {{-- 3. CE Strike --}}
                                        <th class="px-2.5 py-2 text-center whitespace-nowrap text-blue-950 font-black bg-blue-100/50" title="Call Strike Price">
                                            CE Strike
                                        </th>
                                        {{-- 4. Diff between ATM to CE Strike --}}
                                        <th class="px-2 py-2 text-center whitespace-nowrap text-slate-600 bg-slate-50" title="Difference from ATM to CE Strike (OTM distance)">
                                            CE Dist
                                        </th>
                                        {{-- 5. ATM --}}
                                        <th class="px-2.5 py-2 text-center whitespace-nowrap text-slate-900 font-black bg-amber-50/80 border-x border-amber-200/80" title="At The Money Strike">
                                            ATM
                                        </th>
                                        {{-- 6. Diff between ATM to PE Strike --}}
                                        <th class="px-2 py-2 text-center whitespace-nowrap text-slate-600 bg-slate-50" title="Difference from ATM to PE Strike (OTM distance)">
                                            PE Dist
                                        </th>
                                        {{-- 7. PE Strike --}}
                                        <th class="px-2.5 py-2 text-center whitespace-nowrap text-rose-950 font-black bg-rose-100/50" title="Put Strike Price">
                                            PE Strike
                                        </th>
                                        {{-- 8. Price (PE) --}}
                                        <th class="px-2 py-2 text-right whitespace-nowrap text-rose-900 bg-rose-50/60 font-black" title="Put LTP Price">
                                            PE Price
                                        </th>
                                        {{-- 9. Delta (PE) --}}
                                        <th class="px-2 py-2 text-right whitespace-nowrap text-rose-800 bg-rose-50/40" title="Put Delta (Δ)">
                                            PE &Delta;
                                        </th>
                                        {{-- Helper: Price Difference --}}
                                        <th class="px-2 py-2 text-center whitespace-nowrap text-slate-700 font-bold" title="|CE Price - PE Price|">
                                            Diff &#8377;
                                        </th>
                                        {{-- Helper: Combined Premium --}}
                                        <th class="px-2 py-2 text-right whitespace-nowrap text-slate-700 font-bold" title="Total Strangle Premium (CE + PE)">
                                            Combined
                                        </th>
                                        {{-- Helper: Action View --}}
                                        <th class="px-2 py-2 text-center whitespace-nowrap text-slate-600 font-bold">
                                            Action
                                        </th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 font-mono-num text-[11px]">
                                    @foreach($col['matched_pairs'] as $pIndex => $p)
                                        @php
                                            $isTightMatch = $p['price_diff'] <= 1.0;
                                            $cpaQuery = http_build_query([
                                                'put_strikes' => [$p['pe_strike']],
                                                'call_strikes' => [$p['ce_strike']],
                                                'expiry' => $col['expiry'],
                                                'date' => $actualTs ?? $selectedDate,
                                                'chart_view' => 'combined',
                                            ]);
                                            $spaQuery = http_build_query([
                                                'mode' => 'manual',
                                                'call_strikes' => [$p['ce_strike']],
                                                'call_lots' => [1],
                                                'put_strikes' => [$p['pe_strike']],
                                                'put_lots' => [1],
                                                'expiry' => $col['expiry'],
                                                'date' => $selectedDate,
                                            ]);
                                        @endphp
                                        <tr class="{{ $isTightMatch ? 'bg-emerald-50/40 hover:bg-emerald-50/70 font-semibold' : 'hover:bg-slate-50' }} transition-colors">
                                            {{-- CE Delta --}}
                                            <td class="px-2 py-1.5 text-right font-semibold text-blue-700 bg-blue-50/20 whitespace-nowrap">
                                                {{ $p['ce_delta'] >= 0 ? '+' : '' }}{{ number_format($p['ce_delta'], 3) }}
                                            </td>
                                            {{-- CE Price --}}
                                            <td class="px-2 py-1.5 text-right font-black text-blue-900 bg-blue-50/30 whitespace-nowrap">
                                                &#8377;{{ number_format($p['ce_price'], 2) }}
                                            </td>
                                            {{-- CE Strike --}}
                                            <td class="px-2.5 py-1.5 text-center font-black text-blue-950 bg-blue-50/50 whitespace-nowrap">
                                                <span class="px-1.5 py-0.5 rounded bg-blue-100 border border-blue-200 text-blue-900">
                                                    {{ number_format($p['ce_strike']) }} CE
                                                </span>
                                            </td>
                                            {{-- Diff between ATM to CE Strike --}}
                                            <td class="px-2 py-1.5 text-center text-slate-600 font-semibold whitespace-nowrap bg-slate-50/50">
                                                +{{ $p['ce_dist'] }}
                                            </td>
                                            {{-- ATM --}}
                                            <td class="px-2.5 py-1.5 text-center font-black text-slate-900 bg-amber-50/50 border-x border-amber-200/60 whitespace-nowrap">
                                                {{ number_format($p['atm']) }}
                                            </td>
                                            {{-- Diff between ATM to PE Strike --}}
                                            <td class="px-2 py-1.5 text-center text-slate-600 font-semibold whitespace-nowrap bg-slate-50/50">
                                                -{{ $p['pe_dist'] }}
                                            </td>
                                            {{-- PE Strike --}}
                                            <td class="px-2.5 py-1.5 text-center font-black text-rose-950 bg-rose-50/50 whitespace-nowrap">
                                                <span class="px-1.5 py-0.5 rounded bg-rose-100 border border-rose-200 text-rose-900">
                                                    {{ number_format($p['pe_strike']) }} PE
                                                </span>
                                            </td>
                                            {{-- PE Price --}}
                                            <td class="px-2 py-1.5 text-right font-black text-rose-900 bg-rose-50/30 whitespace-nowrap">
                                                &#8377;{{ number_format($p['pe_price'], 2) }}
                                            </td>
                                            {{-- PE Delta --}}
                                            <td class="px-2 py-1.5 text-right font-semibold text-rose-700 bg-rose-50/20 whitespace-nowrap">
                                                {{ number_format($p['pe_delta'], 3) }}
                                            </td>
                                            {{-- Price Difference --}}
                                            <td class="px-2 py-1.5 text-center font-black whitespace-nowrap">
                                                <span class="px-1.5 py-0.5 rounded text-[10px] {{ $p['price_diff'] <= 1.0 ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' : 'bg-slate-100 text-slate-800' }}">
                                                    &#8377;{{ number_format($p['price_diff'], 2) }}
                                                </span>
                                            </td>
                                            {{-- Combined Price --}}
                                            <td class="px-2 py-1.5 text-right font-black text-slate-800 whitespace-nowrap">
                                                &#8377;{{ number_format($p['combined_price'], 2) }}
                                            </td>
                                            {{-- Actions --}}
                                            <td class="px-2 py-1.5 text-center whitespace-nowrap">
                                                <div class="flex items-center gap-1 justify-center">
                                                    <a target="_blank"
                                                       href="{{ route('strategy.premium.analytics') . '?' . $spaQuery }}"
                                                       class="px-2 py-0.5 rounded text-[10px] font-bold bg-teal-600 hover:bg-teal-700 text-white shadow-xs transition"
                                                       title="Open in Strategy Matrix Chart">
                                                        Matrix
                                                    </a>
                                                    <a target="_blank"
                                                       href="{{ url('/combined-premium-analysis') . '?' . $cpaQuery }}"
                                                       class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-700 hover:bg-slate-800 text-slate-100 shadow-xs transition"
                                                       title="Open in Combined Premium Chart">
                                                        CPA
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            @else
                                {{-- Empty State for this expiry --}}
                                <div class="p-6 text-center space-y-2">
                                    <span class="text-3xl">🔍</span>
                                    <p class="font-bold text-slate-700 text-sm">No pairs matched within &#8377;{{ number_format($maxPriceDiff, 1) }} tolerance</p>
                                    <p class="text-xs text-slate-500 max-w-sm mx-auto">
                                        Found {{ $col['ce_count'] }} CE and {{ $col['pe_count'] }} PE options in price range &#8377;{{ $minPrice }} - &#8377;{{ $maxPrice }}. Try expanding the tolerance or price range.
                                    </p>
                                    <div class="pt-2">
                                        <a href="{{ request()->fullUrlWithQuery(['max_delta' => $maxPriceDiff + 2.0]) }}"
                                           class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition">
                                            Widen Max Diff to &#8377;{{ number_format($maxPriceDiff + 2.0, 1) }} &rarr;
                                        </a>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-2 bg-yellow-50 border border-yellow-300 text-yellow-800 p-6 rounded-xl text-center space-y-2">
                        <span class="text-3xl">⚠️</span>
                        <p class="font-bold text-base">No Option Chain Data Found</p>
                        <p class="text-xs text-yellow-700">No records found for date {{ $selectedDate }}. Please verify if data exists for this trading day.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
