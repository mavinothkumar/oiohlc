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
                                                    <button type="button"
                                                            onclick='selectPairForPlaybook(@json($p), "{{ $col['expiry'] }}", "{{ $col['label'] }}", {{ $col['spot_price'] }})'
                                                            class="playbook-select-btn px-1.5 py-0.5 rounded text-[10px] font-black bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition flex items-center gap-0.5 cursor-pointer"
                                                            title="Load this pair into the 4-Rules Playbook below">
                                                        <span>🎯</span>
                                                        <span>Playbook</span>
                                                    </button>
                                                    <a target="_blank"
                                                       href="{{ route('strategy.premium.analytics') . '?' . $spaQuery }}"
                                                       class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-teal-600 hover:bg-teal-700 text-white shadow-xs transition"
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

            @php
                $defaultCol = null;
                $defaultPair = null;
                if (isset($columnsData[1]) && count($columnsData[1]['matched_pairs']) > 0) {
                    $defaultCol = $columnsData[1];
                    $defaultPair = $columnsData[1]['matched_pairs'][0];
                } elseif (isset($columnsData[0]) && count($columnsData[0]['matched_pairs']) > 0) {
                    $defaultCol = $columnsData[0];
                    $defaultPair = $columnsData[0]['matched_pairs'][0];
                }
            @endphp

            @if($defaultPair)
                {{-- ═══════════════════════════════════════════════════════════════════
                     4-RULES STRANGLE EXECUTION PLAYBOOK & STREAM ENGINE
                ═══════════════════════════════════════════════════════════════════ --}}
                <div id="playbook-section" class="mt-8 pt-6 border-t-2 border-slate-300">
                    
                    {{-- Header Banner & Simulation Controls --}}
                    <div class="bg-gradient-to-r from-slate-950 via-indigo-950 to-slate-900 text-white rounded-2xl p-4 sm:p-5 shadow-xl border border-slate-800 mb-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-2xl">🎯</span>
                                    <h2 class="text-lg sm:text-xl font-black tracking-tight text-white">
                                        Strangle Execution Playbook &amp; 4-Rules Adjustment Engine
                                    </h2>
                                    <span id="pb-expiry-tag" class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-400 text-slate-900 border border-amber-300">
                                        {{ $defaultCol['label'] ?? 'Selected Pair' }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-300 mt-1 max-w-2xl">
                                    Step-by-step mathematical guide: Staggered initial entry, delta-neutral rolling, overnight wing hedges, and automated fire-exit stop triggers.
                                </p>
                            </div>

                            {{-- Stream Status & Simulation Controls --}}
                            <div class="flex items-center flex-wrap gap-2 text-xs">
                                {{-- WebSocket / Live Status --}}
                                <div class="flex items-center gap-1.5 bg-slate-900/90 border border-slate-700 px-3 py-1.5 rounded-xl shadow-xs">
                                    <span id="pb-ws-dot" class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-400 shadow-sm animate-pulse"></span>
                                    <span id="pb-ws-text" class="text-slate-200 text-xs font-semibold">Live Feed Ready</span>
                                    <span class="text-slate-500">|</span>
                                    <span id="pb-tick-counter" class="font-mono text-[11px] text-slate-400">0 ticks</span>
                                </div>

                                {{-- Quick Simulation Triggers --}}
                                <div class="flex items-center gap-1 bg-slate-900/90 border border-slate-700 p-1 rounded-xl">
                                    <span class="text-[10px] uppercase font-bold text-slate-400 px-1.5">Simulate:</span>
                                    <button type="button" onclick="simulateScenario('normal_decay')"
                                            class="px-2 py-1 rounded bg-slate-800 hover:bg-slate-700 text-emerald-300 text-[11px] font-semibold transition cursor-pointer"
                                            title="Simulate normal decay: both legs decay down to ₹15">
                                        Decay (₹15)
                                    </button>
                                    <button type="button" onclick="simulateScenario('rally_180')"
                                            class="px-2 py-1 rounded bg-slate-800 hover:bg-slate-700 text-amber-300 text-[11px] font-semibold transition cursor-pointer"
                                            title="Nifty rallies +180 pts: triggers Rule 1 (averaging) and Rule 2 (roll PE)">
                                        +180 pt Rally
                                    </button>
                                    <button type="button" onclick="simulateScenario('drop_200')"
                                            class="px-2 py-1 rounded bg-slate-800 hover:bg-slate-700 text-rose-300 text-[11px] font-semibold transition cursor-pointer"
                                            title="Nifty drops -200 pts: triggers Rule 1 and Rule 2 (roll CE)">
                                        -200 pt Drop
                                    </button>
                                    <button type="button" onclick="simulateScenario('ce_sl_hit')"
                                            class="px-2 py-1 rounded bg-red-950 hover:bg-red-900 text-red-200 border border-red-800 text-[11px] font-bold transition cursor-pointer"
                                            title="CE surges to 2x entry price: triggers Rule 4 (Hard Stop Loss)">
                                        CE SL Hit
                                    </button>
                                    <button type="button" onclick="resetToInitialPair()"
                                            class="px-2 py-1 rounded bg-indigo-700 hover:bg-indigo-600 text-white text-[11px] font-bold transition cursor-pointer"
                                            title="Reset back to initial entry values">
                                        Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Live Monitor Strip (5 Key Metric Cards) --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
                        
                        {{-- 1. Index Spot & Corridor --}}
                        <div class="bg-white rounded-xl p-3.5 border border-slate-200 shadow-xs flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between text-[11px] font-bold uppercase text-slate-500 mb-1">
                                    <span>Nifty Spot</span>
                                    <span id="pb-spot-diff" class="text-slate-600 font-mono">+0.00</span>
                                </div>
                                <div class="text-xl font-black font-mono-num text-slate-900" id="pb-live-spot">
                                    &#8377;{{ number_format($defaultCol['spot_price'], 2) }}
                                </div>
                            </div>
                            <div class="mt-2 pt-2 border-t border-slate-100 text-[11px] font-mono-num text-slate-600">
                                <div class="flex justify-between">
                                    <span>Corridor:</span>
                                    <span class="font-bold text-indigo-700" id="pb-corridor-span">
                                        {{ number_format((float)$defaultPair['pe_strike'] - (float)$defaultPair['combined_price'], 0) }} &harr; {{ number_format((float)$defaultPair['ce_strike'] + (float)$defaultPair['combined_price'], 0) }}
                                    </span>
                                </div>
                                <div class="text-[10px] text-slate-400 text-right mt-0.5">
                                    Buffer: <strong class="text-slate-700" id="pb-total-corridor-pts">{{ number_format(((float)$defaultPair['ce_strike'] + (float)$defaultPair['combined_price']) - ((float)$defaultPair['pe_strike'] - (float)$defaultPair['combined_price']), 0) }} pts</strong>
                                </div>
                            </div>
                        </div>

                        {{-- 2. Call Leg (CE) --}}
                        <div class="bg-white rounded-xl p-3.5 border border-blue-200 shadow-xs flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between text-[11px] font-bold uppercase text-blue-700 mb-1">
                                    <span id="pb-ce-strike-title">{{ number_format($defaultPair['ce_strike']) }} CE</span>
                                    <span class="text-[10px] px-1.5 py-0.2 rounded bg-blue-100 text-blue-900" id="pb-ce-delta-badge">&Delta; {{ number_format($defaultPair['ce_delta'], 3) }}</span>
                                </div>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-xl font-black font-mono-num text-blue-950" id="pb-live-ce">
                                        &#8377;{{ number_format($defaultPair['ce_price'], 2) }}
                                    </span>
                                    <span class="text-xs text-slate-400 font-mono-num" id="pb-ce-entry-lbl">
                                        Entry: &#8377;{{ number_format($defaultPair['ce_price'], 2) }}
                                    </span>
                                </div>
                            </div>
                            <div class="mt-2 pt-2 border-t border-blue-50 text-[11px] font-mono-num flex justify-between">
                                <span class="text-slate-500">2x SL Trigger:</span>
                                <span class="font-bold text-rose-700" id="pb-ce-sl-val">
                                    &#8377;{{ number_format($defaultPair['ce_price'] * 2.0, 2) }}
                                </span>
                            </div>
                        </div>

                        {{-- 3. Put Leg (PE) --}}
                        <div class="bg-white rounded-xl p-3.5 border border-rose-200 shadow-xs flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between text-[11px] font-bold uppercase text-rose-700 mb-1">
                                    <span id="pb-pe-strike-title">{{ number_format($defaultPair['pe_strike']) }} PE</span>
                                    <span class="text-[10px] px-1.5 py-0.2 rounded bg-rose-100 text-rose-900" id="pb-pe-delta-badge">&Delta; {{ number_format($defaultPair['pe_delta'], 3) }}</span>
                                </div>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-xl font-black font-mono-num text-rose-950" id="pb-live-pe">
                                        &#8377;{{ number_format($defaultPair['pe_price'], 2) }}
                                    </span>
                                    <span class="text-xs text-slate-400 font-mono-num" id="pb-pe-entry-lbl">
                                        Entry: &#8377;{{ number_format($defaultPair['pe_price'], 2) }}
                                    </span>
                                </div>
                            </div>
                            <div class="mt-2 pt-2 border-t border-rose-50 text-[11px] font-mono-num flex justify-between">
                                <span class="text-slate-500">2x SL Trigger:</span>
                                <span class="font-bold text-rose-700" id="pb-pe-sl-val">
                                    &#8377;{{ number_format($defaultPair['pe_price'] * 2.0, 2) }}
                                </span>
                            </div>
                        </div>

                        {{-- 4. Combined Premium & Harvest Goal --}}
                        <div class="bg-white rounded-xl p-3.5 border border-slate-200 shadow-xs flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between text-[11px] font-bold uppercase text-slate-500 mb-1">
                                    <span>Combined Strangle</span>
                                    <span id="pb-combined-pnl" class="text-xs font-bold text-emerald-600 font-mono">0.00 pts</span>
                                </div>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-xl font-black font-mono-num text-slate-900" id="pb-live-combined">
                                        &#8377;{{ number_format($defaultPair['combined_price'], 2) }}
                                    </span>
                                    <span class="text-xs text-slate-400 font-mono-num" id="pb-combined-entry-lbl">
                                        Entry: &#8377;{{ number_format($defaultPair['combined_price'], 2) }}
                                    </span>
                                </div>
                            </div>
                            <div class="mt-2 pt-2 border-t border-slate-100 text-[11px] font-mono-num flex justify-between">
                                <span class="text-slate-500">50% Profit Goal:</span>
                                <span class="font-bold text-emerald-700" id="pb-harvest-target">
                                    &#8377;{{ number_format($defaultPair['combined_price'] * 0.5, 2) }}
                                </span>
                            </div>
                        </div>

                        {{-- 5. Lot Sizer & Capital Allocation --}}
                        <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl p-3.5 border border-indigo-200 shadow-xs flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between text-[11px] font-bold uppercase text-indigo-900 mb-1">
                                    <span>Lot Allocation</span>
                                    <span class="text-[10px] text-indigo-700 font-bold bg-indigo-100 px-1.5 py-0.5 rounded">Lot: 65 Qty (Nifty)</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <label class="text-xs font-bold text-slate-700">Total Lots:</label>
                                    <input type="number" id="pb-total-lots" value="10" min="2" max="200" step="2"
                                           onchange="updateLotCalculations()" onkeyup="updateLotCalculations()"
                                           class="w-16 px-2 py-0.5 border border-indigo-300 rounded font-mono font-bold text-xs bg-white text-indigo-950">
                                </div>
                            </div>
                            <div class="mt-2 pt-2 border-t border-indigo-100 text-[11px] font-mono-num space-y-0.5">
                                <div class="flex justify-between">
                                    <span class="text-slate-600">Tranche 1 (35%):</span>
                                    <strong class="text-emerald-700" id="pb-tranche1-lots">4 Lots (260 Qty)</strong>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-600">Reserve (65%):</span>
                                    <strong class="text-indigo-700" id="pb-tranche2-lots">6 Lots (390 Qty)</strong>
                                </div>
                            </div>
                        </div>

                    </div>

                    {{-- ═══════════════════════════════════════════════════════════════════
                         INTERACTIVE MY TRADE SETUP & REAL-TIME P&L CALCULATOR
                    ═══════════════════════════════════════════════════════════════════ --}}
                    <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-sm mb-6">
                        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 mb-4 border-b border-slate-100">
                            <div class="flex items-center gap-2">
                                <span class="text-xl">💼</span>
                                <div>
                                    <h3 class="text-sm sm:text-base font-black text-slate-900 tracking-tight">
                                        My Trade Position &amp; Real-Time P&amp;L Calculator
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        Customize your entry prices, time of entry, and lot sizes to track real-time profit/loss and dynamic rule alerts.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 font-mono-num">
                                    Lot Multiplier: <strong>65 Qty</strong> / Lot
                                </span>
                                <button type="button" onclick="syncFromCurrentPair()"
                                        class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 transition cursor-pointer flex items-center gap-1 shadow-xs"
                                        title="Reset inputs to match the table selection">
                                    🔄 Sync from Selection
                                </button>
                            </div>
                        </div>

                        {{-- User Entry Inputs Grid --}}
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-2.5 mb-4 text-xs font-mono-num">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Expiry Date</label>
                                <input type="text" id="user-trade-expiry" value="{{ $defaultCol['expiry'] ?? '' }}"
                                       onchange="updateUserTradeValues()"
                                       class="w-full px-2.5 py-1.5 border border-slate-300 rounded-lg bg-slate-50 text-slate-800 font-semibold text-xs focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Time of Entry</label>
                                <input type="time" id="user-trade-time" value="09:30"
                                       onchange="updateUserTradeValues()"
                                       class="w-full px-2.5 py-1.5 border border-slate-300 rounded-lg bg-white text-slate-800 font-semibold text-xs focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-blue-700 uppercase mb-1">CE Strike</label>
                                <input type="number" step="50" id="user-trade-ce-strike" value="{{ (int)$defaultPair['ce_strike'] }}"
                                       onchange="updateUserTradeValues()" onkeyup="updateUserTradeValues()"
                                       class="w-full px-2.5 py-1.5 border border-blue-300 rounded-lg bg-blue-50/50 text-blue-950 font-bold text-xs focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-blue-700 uppercase mb-1">CE Entry Price (&#8377;)</label>
                                <input type="number" step="0.05" id="user-trade-ce-price" value="{{ (float)$defaultPair['ce_price'] }}"
                                       onchange="updateUserTradeValues()" onkeyup="updateUserTradeValues()"
                                       class="w-full px-2.5 py-1.5 border border-blue-300 rounded-lg bg-white text-blue-950 font-black text-xs focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-rose-700 uppercase mb-1">PE Strike</label>
                                <input type="number" step="50" id="user-trade-pe-strike" value="{{ (int)$defaultPair['pe_strike'] }}"
                                       onchange="updateUserTradeValues()" onkeyup="updateUserTradeValues()"
                                       class="w-full px-2.5 py-1.5 border border-rose-300 rounded-lg bg-rose-50/50 text-rose-950 font-bold text-xs focus:ring-rose-500">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-rose-700 uppercase mb-1">PE Entry Price (&#8377;)</label>
                                <input type="number" step="0.05" id="user-trade-pe-price" value="{{ (float)$defaultPair['pe_price'] }}"
                                       onchange="updateUserTradeValues()" onkeyup="updateUserTradeValues()"
                                       class="w-full px-2.5 py-1.5 border border-rose-300 rounded-lg bg-white text-rose-950 font-black text-xs focus:ring-rose-500">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-indigo-700 uppercase mb-1">Trade Lots (65x)</label>
                                <input type="number" min="1" step="1" id="user-trade-lots" value="4"
                                       onchange="updateUserTradeValues()" onkeyup="updateUserTradeValues()"
                                       class="w-full px-2.5 py-1.5 border border-indigo-300 rounded-lg bg-indigo-50/50 text-indigo-950 font-black text-xs focus:ring-indigo-500">
                            </div>
                        </div>

                        {{-- Real-Time Live Profit & Loss Bar --}}
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3.5 bg-slate-900 text-white rounded-xl font-mono-num shadow-inner">
                            {{-- CE Leg Live P&L --}}
                            <div class="flex items-center justify-between border-b sm:border-b-0 sm:border-r border-slate-800 pb-2 sm:pb-0 sm:pr-3">
                                <div>
                                    <span class="text-[10px] font-bold uppercase text-blue-300 tracking-wider">Call Leg (CE) P&amp;L</span>
                                    <div class="text-xs text-slate-400 mt-0.5">
                                        Live: <strong class="text-white" id="pnl-ce-ltp">&#8377;{{ number_format($defaultPair['ce_price'], 2) }}</strong> &bull; Entry: <span id="pnl-ce-entry">&#8377;{{ number_format($defaultPair['ce_price'], 2) }}</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-black text-slate-200" id="pnl-ce-amount">+&#8377;0.00</div>
                                    <div class="text-[10px] font-semibold text-slate-400" id="pnl-ce-pts">+0.00 pts</div>
                                </div>
                            </div>

                            {{-- PE Leg Live P&L --}}
                            <div class="flex items-center justify-between border-b sm:border-b-0 sm:border-r border-slate-800 pb-2 sm:pb-0 sm:pr-3">
                                <div>
                                    <span class="text-[10px] font-bold uppercase text-rose-300 tracking-wider">Put Leg (PE) P&amp;L</span>
                                    <div class="text-xs text-slate-400 mt-0.5">
                                        Live: <strong class="text-white" id="pnl-pe-ltp">&#8377;{{ number_format($defaultPair['pe_price'], 2) }}</strong> &bull; Entry: <span id="pnl-pe-entry">&#8377;{{ number_format($defaultPair['pe_price'], 2) }}</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-black text-slate-200" id="pnl-pe-amount">+&#8377;0.00</div>
                                    <div class="text-[10px] font-semibold text-slate-400" id="pnl-pe-pts">+0.00 pts</div>
                                </div>
                            </div>

                            {{-- Combined Strangle Net P&L --}}
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-[10px] font-bold uppercase text-amber-300 tracking-wider">Total Net Strangle P&amp;L</span>
                                    <div class="text-xs text-slate-400 mt-0.5">
                                        <span id="pnl-total-qty">4 Lots (260 Qty)</span> &bull; Harvest: <span class="text-emerald-400 font-bold" id="pnl-harvest-pct">0.0%</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-black text-emerald-400" id="pnl-net-amount">+&#8377;0.00</div>
                                    <div class="text-xs font-bold text-emerald-400" id="pnl-net-pts">+0.00 pts</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ═══════════════════════════════════════════════════════════════════
                         CURRENT POSITION SITUATION & STRATEGY COMMENTARY (ABOVE 4 RULES)
                    ═══════════════════════════════════════════════════════════════════ --}}
                    <div class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white rounded-2xl p-4 sm:p-5 shadow-lg border-2 border-indigo-400/40 mb-6 relative overflow-hidden">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-3 pb-3 border-b border-indigo-800/60">
                            <div class="flex items-center gap-2">
                                <span class="text-2xl animate-pulse">📍</span>
                                <div>
                                    <h3 class="text-base sm:text-lg font-black tracking-tight text-white flex items-center gap-2">
                                        <span>Where We Are Right Now &bull; Strategy Diagnosis</span>
                                    </h3>
                                    <p class="text-xs text-indigo-200 mt-0.5">
                                        Real-time situational commentary guiding what is happening and the exact next step onwards.
                                    </p>
                                </div>
                            </div>
                            <div id="pb-diagnosis-badge" class="px-3 py-1 rounded-full text-xs font-black bg-emerald-500 text-white shadow-md flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full bg-white animate-ping"></span>
                                <span>✅ POSITION HEALTHY: SAFE &amp; DECAYING</span>
                            </div>
                        </div>

                        {{-- Main Narrative Text --}}
                        <div class="bg-slate-950/70 border border-indigo-500/30 rounded-xl p-3.5 mb-4 text-xs sm:text-sm text-slate-100 leading-relaxed font-sans shadow-inner">
                            <p id="pb-commentary-text">
                                Loading real-time position diagnosis...
                            </p>
                        </div>

                        {{-- 4-Rule Status Gauges (Instant Quick-Check) --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5 text-xs font-mono-num">
                            <div class="bg-slate-900/80 border border-slate-700 p-2.5 rounded-xl">
                                <div class="text-[10px] font-bold uppercase text-indigo-300">Rule 1: Staggered Averaging</div>
                                <div class="font-bold text-slate-200 mt-0.5" id="diag-rule1-status">🟢 In Comfort Zone</div>
                                <div class="text-[10px] text-slate-400 mt-0.5" id="diag-rule1-sub">Spot within &plusmn;175 pts</div>
                            </div>
                            <div class="bg-slate-900/80 border border-slate-700 p-2.5 rounded-xl">
                                <div class="text-[10px] font-bold uppercase text-emerald-300">Rule 2: Roll Winning Leg</div>
                                <div class="font-bold text-slate-200 mt-0.5" id="diag-rule2-status">🟢 Decaying Normally</div>
                                <div class="text-[10px] text-slate-400 mt-0.5" id="diag-rule2-sub">Need decay &le; &#8377;15.00</div>
                            </div>
                            <div class="bg-slate-900/80 border border-slate-700 p-2.5 rounded-xl">
                                <div class="text-[10px] font-bold uppercase text-amber-300">Rule 3: Wing Hedge</div>
                                <div class="font-bold text-amber-200 mt-0.5" id="diag-rule3-status">🛡️ Buy Wings Before 15:15</div>
                                <div class="text-[10px] text-slate-400 mt-0.5" id="diag-rule3-sub">Iron Condor protection</div>
                            </div>
                            <div class="bg-slate-900/80 border border-slate-700 p-2.5 rounded-xl">
                                <div class="text-[10px] font-bold uppercase text-rose-300">Rule 4: Hard Stop Loss</div>
                                <div class="font-bold text-slate-200 mt-0.5" id="diag-rule4-status">🟢 Safe (&lt; 2.0x SL)</div>
                                <div class="text-[10px] text-slate-400 mt-0.5" id="diag-rule4-sub">SL triggers at 2x price</div>
                            </div>
                        </div>
                    </div>

                    {{-- ═══════════════════════════════════════════════════════════════════
                         THE 4 RULES VISUAL GRID (RULE NUMBERS, ACTIONS & NEXT STEPS)
                    ═══════════════════════════════════════════════════════════════════ --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">

                        {{-- ── RULE 1 ── --}}
                        <div class="bg-white rounded-2xl border-2 border-indigo-200 shadow-xs overflow-hidden flex flex-col">
                            {{-- Header --}}
                            <div class="p-3.5 bg-indigo-50/80 border-b border-indigo-100 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-black bg-indigo-600 text-white shadow-xs">
                                        RULE 01
                                    </span>
                                    <div>
                                        <h3 class="text-sm font-black text-slate-900">
                                            Staggered Capital Deployment
                                        </h3>
                                        <p class="text-[11px] font-semibold text-indigo-800">
                                            Smart Averaging &amp; Position Laddering
                                        </p>
                                    </div>
                                </div>
                                <span id="r1-badge" class="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    🟢 COMFORT ZONE
                                </span>
                            </div>

                            {{-- Content --}}
                            <div class="p-4 space-y-3 flex-1 text-xs">
                                {{-- Initial Entry --}}
                                <div class="bg-slate-50 rounded-xl p-2.5 border border-slate-200">
                                    <div class="text-[11px] font-bold uppercase text-slate-500 mb-1">Step 1: Initial Entry Allocation</div>
                                    <div class="font-mono-num text-xs space-y-0.5">
                                        <div>&bull; Sell <strong class="text-indigo-900" id="r1-init-lots">4 Lots</strong> of <strong class="text-blue-900" id="r1-ce-strike">{{ number_format($defaultPair['ce_strike']) }} CE</strong> @ &#8377;<span id="r1-ce-price">{{ number_format($defaultPair['ce_price'], 2) }}</span></div>
                                        <div>&bull; Sell <strong class="text-indigo-900" id="r1-init-lots-pe">4 Lots</strong> of <strong class="text-rose-900" id="r1-pe-strike">{{ number_format($defaultPair['pe_strike']) }} PE</strong> @ &#8377;<span id="r1-pe-price">{{ number_format($defaultPair['pe_price'], 2) }}</span></div>
                                        <div>&bull; Keep <strong class="text-slate-800" id="r1-res-lots">6 Lots (65%)</strong> in cash reserve.</div>
                                    </div>
                                </div>

                                {{-- Trigger Condition --}}
                                <div>
                                    <span class="font-bold text-slate-800">Trigger Condition:</span>
                                    <p class="text-slate-600 mt-0.5">
                                        If Spot moves by <strong class="text-slate-800">&plusmn;150 to 200 points</strong> (Spot reaches &ge; <span class="font-mono font-bold text-indigo-700" id="r1-up-trigger">{{ number_format($defaultCol['spot_price'] + 175, 0) }}</span> or &le; <span class="font-mono font-bold text-indigo-700" id="r1-down-trigger">{{ number_format($defaultCol['spot_price'] - 175, 0) }}</span>).
                                    </p>
                                </div>

                                {{-- Action To Take --}}
                                <div class="bg-amber-50/70 border border-amber-200 rounded-xl p-2.5 text-amber-950">
                                    <div class="font-bold flex items-center gap-1 text-[11px] uppercase text-amber-900">
                                        <span>⚡ Action To Take:</span>
                                    </div>
                                    <ul class="list-disc list-inside mt-1 space-y-0.5 text-slate-800">
                                        <li><strong class="text-rose-700">NEVER average down</strong> on the same losing strike.</li>
                                        <li>Deploy Tranche 2 (3 lots) by selling a <strong class="text-slate-900">fresh higher/lower OTM strike</strong> trading around &#8377;40 – &#8377;45.</li>
                                        <li>If market rallied up: Sell <strong class="text-blue-900" id="r1-ladder-ce">{{ number_format($defaultPair['ce_strike'] + 150) }} CE</strong>.</li>
                                    </ul>
                                </div>

                                {{-- Next Step To Do --}}
                                <div class="bg-indigo-50/60 border border-indigo-200 rounded-xl p-2.5">
                                    <span class="font-bold text-indigo-950 text-[11px] uppercase">📌 Next Step To Do:</span>
                                    <p class="text-slate-700 mt-0.5">
                                        Set broker price alerts at Spot <strong id="r1-alert-up">{{ number_format($defaultCol['spot_price'] + 175, 0) }}</strong> and <strong id="r1-alert-down">{{ number_format($defaultCol['spot_price'] - 175, 0) }}</strong>. Elevates your breakeven while maintaining delta neutrality.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- ── RULE 2 ── --}}
                        <div class="bg-white rounded-2xl border-2 border-emerald-200 shadow-xs overflow-hidden flex flex-col">
                            {{-- Header --}}
                            <div class="p-3.5 bg-emerald-50/80 border-b border-emerald-100 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-black bg-emerald-600 text-white shadow-xs">
                                        RULE 02
                                    </span>
                                    <div>
                                        <h3 class="text-sm font-black text-slate-900">
                                            Roll The Winning Leg
                                        </h3>
                                        <p class="text-[11px] font-semibold text-emerald-800">
                                            Delta Shifter &amp; Theta Harvesting
                                        </p>
                                    </div>
                                </div>
                                <span id="r2-badge" class="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-300">
                                    ⏳ WAITING FOR 65% DECAY
                                </span>
                            </div>

                            {{-- Content --}}
                            <div class="p-4 space-y-3 flex-1 text-xs">
                                {{-- Trigger Condition --}}
                                <div>
                                    <span class="font-bold text-slate-800">Trigger Condition:</span>
                                    <p class="text-slate-600 mt-0.5">
                                        Either leg decays by <strong class="text-emerald-700">&ge;65%</strong> (LTP drops to &le; <strong class="font-mono text-emerald-800">&#8377;15.00</strong>):
                                    </p>
                                    <div class="mt-1 font-mono-num text-[11px] text-slate-500 bg-slate-50 p-2 rounded border border-slate-200">
                                        <div>&bull; If Nifty rallies &rarr; PE drops to <strong class="text-emerald-700">&le; &#8377;15.00</strong> (Locks in ~&#8377;{{ number_format($defaultPair['pe_price'] - 15, 2) }}/share profit)</div>
                                        <div>&bull; If Nifty drops &rarr; CE drops to <strong class="text-emerald-700">&le; &#8377;15.00</strong> (Locks in ~&#8377;{{ number_format($defaultPair['ce_price'] - 15, 2) }}/share profit)</div>
                                    </div>
                                </div>

                                {{-- Action To Take --}}
                                <div class="bg-emerald-50/70 border border-emerald-200 rounded-xl p-2.5 text-emerald-950">
                                    <div class="font-bold flex items-center gap-1 text-[11px] uppercase text-emerald-900">
                                        <span>⚡ Action To Take:</span>
                                    </div>
                                    <ul class="list-disc list-inside mt-1 space-y-0.5 text-slate-800">
                                        <li><strong class="text-emerald-800">Square off</strong> the decayed leg to lock in ~65% profit.</li>
                                        <li><strong class="text-slate-900">Roll strike 200–300 points closer</strong> to current spot to sell a new strike trading at &#8377;35 – &#8377;40.</li>
                                        <li>Example: Roll <strong id="r2-roll-from">{{ number_format($defaultPair['pe_strike']) }} PE</strong> up to <strong class="text-rose-900" id="r2-roll-to">{{ number_format($defaultPair['pe_strike'] + 300) }} PE</strong>.</li>
                                    </ul>
                                </div>

                                {{-- Next Step To Do --}}
                                <div class="bg-slate-50 border border-slate-200 rounded-xl p-2.5">
                                    <span class="font-bold text-slate-900 text-[11px] uppercase">📌 Next Step To Do:</span>
                                    <p class="text-slate-700 mt-0.5">
                                        By booking &#8377;28 profit and collecting fresh &#8377;35–&#8377;40 premium, total collected premium rises to <strong class="text-emerald-800">&#8377;115+</strong>, expanding the tested side's breakeven by 30+ points while resetting net delta to zero.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- ── RULE 3 ── --}}
                        <div class="bg-white rounded-2xl border-2 border-amber-200 shadow-xs overflow-hidden flex flex-col">
                            {{-- Header --}}
                            <div class="p-3.5 bg-amber-50/80 border-b border-amber-100 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-black bg-amber-600 text-white shadow-xs">
                                        RULE 03
                                    </span>
                                    <div>
                                        <h3 class="text-sm font-black text-slate-900">
                                            Defined Risk Wing Hedge
                                        </h3>
                                        <p class="text-[11px] font-semibold text-amber-800">
                                            Iron Condor Conversion &amp; Margin Saver
                                        </p>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                                    🛡️ OVERNIGHT SHIELD
                                </span>
                            </div>

                            {{-- Content --}}
                            <div class="p-4 space-y-3 flex-1 text-xs">
                                {{-- Trigger Condition --}}
                                <div>
                                    <span class="font-bold text-slate-800">Trigger Condition:</span>
                                    <p class="text-slate-600 mt-0.5">
                                        Approaching <strong class="text-slate-900">15:15 IST</strong> before carrying position overnight, or before weekend / RBI policy events.
                                    </p>
                                </div>

                                {{-- Action To Take --}}
                                <div class="bg-amber-50/70 border border-amber-200 rounded-xl p-2.5 text-amber-950">
                                    <div class="font-bold flex items-center gap-1 text-[11px] uppercase text-amber-900">
                                        <span>⚡ Action To Take (Buy Cheap Wings):</span>
                                    </div>
                                    <div class="mt-1 font-mono-num text-[11px] space-y-0.5 text-slate-800">
                                        <div>&bull; Buy <strong class="text-blue-900" id="r3-wing-ce">{{ number_format($defaultPair['ce_strike'] + 300) }} CE</strong> (+300 pts) @ ~&#8377;4.50 – &#8377;6.00</div>
                                        <div>&bull; Buy <strong class="text-rose-900" id="r3-wing-pe">{{ number_format($defaultPair['pe_strike'] - 300) }} PE</strong> (-300 pts) @ ~&#8377;4.50 – &#8377;6.00</div>
                                    </div>
                                </div>

                                {{-- Next Step To Do --}}
                                <div class="bg-slate-50 border border-slate-200 rounded-xl p-2.5">
                                    <span class="font-bold text-slate-900 text-[11px] uppercase">📌 Next Step To Do:</span>
                                    <p class="text-slate-700 mt-0.5">
                                        Converts naked strangle into an <strong class="text-slate-900">Iron Condor</strong>. Slashes broker margin requirement by <strong class="text-emerald-700">40% – 60%</strong> and completely eliminates black-swan gap disaster risk.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- ── RULE 4 ── --}}
                        <div class="bg-white rounded-2xl border-2 border-rose-200 shadow-xs overflow-hidden flex flex-col">
                            {{-- Header --}}
                            <div class="p-3.5 bg-rose-50/80 border-b border-rose-100 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-black bg-rose-600 text-white shadow-xs">
                                        RULE 04
                                    </span>
                                    <div>
                                        <h3 class="text-sm font-black text-slate-900">
                                            Hard Stop Loss (Fire Exit)
                                        </h3>
                                        <p class="text-[11px] font-semibold text-rose-800">
                                            Capital Preservation &amp; Drawdown Cap
                                        </p>
                                    </div>
                                </div>
                                <span id="r4-badge" class="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    🟢 SAFE
                                </span>
                            </div>

                            {{-- Content --}}
                            <div class="p-4 space-y-3 flex-1 text-xs">
                                {{-- Trigger Condition --}}
                                <div>
                                    <span class="font-bold text-slate-800">Trigger Condition:</span>
                                    <p class="text-slate-600 mt-0.5">
                                        Any single leg reaches <strong class="text-rose-700">2.0&times; Entry Price</strong>:
                                    </p>
                                    <div class="mt-1 font-mono-num text-[11px] text-slate-800 bg-rose-50/60 p-2 rounded border border-rose-200 space-y-0.5">
                                        <div>&bull; CE Stop Loss: <strong class="text-rose-900" id="r4-ce-sl">&#8377;{{ number_format($defaultPair['ce_price'] * 2.0, 2) }}</strong> (2x of &#8377;<span id="r4-ce-entry">{{ number_format($defaultPair['ce_price'], 2) }}</span>)</div>
                                        <div>&bull; PE Stop Loss: <strong class="text-rose-900" id="r4-pe-sl">&#8377;{{ number_format($defaultPair['pe_price'] * 2.0, 2) }}</strong> (2x of &#8377;<span id="r4-pe-entry">{{ number_format($defaultPair['pe_price'], 2) }}</span>)</div>
                                    </div>
                                </div>

                                {{-- Action To Take --}}
                                <div class="bg-rose-50/70 border border-rose-200 rounded-xl p-2.5 text-rose-950">
                                    <div class="font-bold flex items-center gap-1 text-[11px] uppercase text-rose-900">
                                        <span>⚡ Action To Take:</span>
                                    </div>
                                    <ul class="list-disc list-inside mt-1 space-y-0.5 text-slate-800">
                                        <li><strong class="text-rose-700">EXIT the breached losing leg immediately</strong> at market price. Never hope or pray.</li>
                                        <li>Leave the opposite winning leg open (it has collapsed to &#8377;10–&#8377;15, buffering &gt;60% of the stop loss hit).</li>
                                    </ul>
                                </div>

                                {{-- Next Step To Do --}}
                                <div class="bg-slate-50 border border-slate-200 rounded-xl p-2.5">
                                    <span class="font-bold text-slate-900 text-[11px] uppercase">📌 Next Step To Do:</span>
                                    <p class="text-slate-700 mt-0.5">
                                        Place GTT / SL-M orders directly in your broker terminal at entry. Total portfolio loss remains strictly capped at <strong class="text-rose-700">&le; 1.0% – 1.5%</strong> of allocated capital.
                                    </p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            @endif

        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/protobufjs@7.2.5/dist/protobuf.min.js"></script>
<script>
(function() {
    // Playbook Active State (Nifty Lot Size = 65)
    const state = {
        symbol: @json($symbol ?? 'NIFTY'),
        currentPair: @json($defaultPair),
        currentCol: @json($defaultCol),
        initialPair: @json($defaultPair),
        spotPrice: {{ $defaultCol['spot_price'] ?? 22535.45 }},
        initialSpot: {{ $defaultCol['spot_price'] ?? 22535.45 }},
        ceLtp: {{ $defaultPair['ce_price'] ?? 43.65 }},
        peLtp: {{ $defaultPair['pe_price'] ?? 42.95 }},

        // User Trade Position Inputs (Nifty Lot Size = 65 Qty)
        userExpiry: '{{ $defaultCol['expiry'] ?? '' }}',
        entryTime: '09:30',
        ceStrike: {{ (int)($defaultPair['ce_strike'] ?? 23000) }},
        ceEntryPrice: {{ (float)($defaultPair['ce_price'] ?? 43.65) }},
        peStrike: {{ (int)($defaultPair['pe_strike'] ?? 22000) }},
        peEntryPrice: {{ (float)($defaultPair['pe_price'] ?? 42.95) }},
        initialLots: 4,
        totalLots: 10,
        lotSize: 65, // NIFTY LOT SIZE IS 65!

        lowerBreakeven: {{ ((float)($defaultPair['pe_strike'] ?? 22000)) - ((float)($defaultPair['combined_price'] ?? 86.60)) }},
        upperBreakeven: {{ ((float)($defaultPair['ce_strike'] ?? 23000)) + ((float)($defaultPair['combined_price'] ?? 86.60)) }},
        totalCorridor: {{ (((float)($defaultPair['ce_strike'] ?? 23000)) + ((float)($defaultPair['combined_price'] ?? 86.60))) - (((float)($defaultPair['pe_strike'] ?? 22000)) - ((float)($defaultPair['combined_price'] ?? 86.60))) }},

        tickCount: 0,
        ws: null,
        protobufRoot: null,
    };

    // User edits their trade inputs in real time
    window.updateUserTradeValues = function() {
        const expEl = document.getElementById('user-trade-expiry');
        const timeEl = document.getElementById('user-trade-time');
        const ceStrEl = document.getElementById('user-trade-ce-strike');
        const cePrEl = document.getElementById('user-trade-ce-price');
        const peStrEl = document.getElementById('user-trade-pe-strike');
        const pePrEl = document.getElementById('user-trade-pe-price');
        const lotsEl = document.getElementById('user-trade-lots');

        if (expEl) state.userExpiry = expEl.value;
        if (timeEl) state.entryTime = timeEl.value;
        if (ceStrEl) state.ceStrike = parseFloat(ceStrEl.value) || 23000;
        if (cePrEl) state.ceEntryPrice = parseFloat(cePrEl.value) || 43.65;
        if (peStrEl) state.peStrike = parseFloat(peStrEl.value) || 22000;
        if (pePrEl) state.peEntryPrice = parseFloat(pePrEl.value) || 42.95;
        if (lotsEl) {
            state.initialLots = parseInt(lotsEl.value) || 4;
            if (state.initialLots < 1) state.initialLots = 1;
        }

        renderPlaybook();
    };

    // Sync input fields from current selected pair
    window.syncFromCurrentPair = function() {
        if (!state.currentPair) return;
        const p = state.currentPair;
        state.ceStrike = parseFloat(p.ce_strike);
        state.ceEntryPrice = parseFloat(p.ce_price);
        state.peStrike = parseFloat(p.pe_strike);
        state.peEntryPrice = parseFloat(p.pe_price);

        const expEl = document.getElementById('user-trade-expiry');
        const ceStrEl = document.getElementById('user-trade-ce-strike');
        const cePrEl = document.getElementById('user-trade-ce-price');
        const peStrEl = document.getElementById('user-trade-pe-strike');
        const pePrEl = document.getElementById('user-trade-pe-price');

        if (expEl && state.currentCol) expEl.value = state.currentCol.expiry;
        if (ceStrEl) ceStrEl.value = state.ceStrike;
        if (cePrEl) cePrEl.value = state.ceEntryPrice;
        if (peStrEl) peStrEl.value = state.peStrike;
        if (pePrEl) pePrEl.value = state.peEntryPrice;

        renderPlaybook();
    };

    // Global selector for table row Playbook buttons
    window.selectPairForPlaybook = function(pair, expiry, label, spot) {
        state.currentPair = pair;
        state.initialPair = JSON.parse(JSON.stringify(pair));
        state.currentCol = { expiry: expiry, label: label, spot_price: spot };
        state.spotPrice = spot;
        state.initialSpot = spot;
        state.ceLtp = parseFloat(pair.ce_price);
        state.peLtp = parseFloat(pair.pe_price);

        // Update trade user inputs
        state.ceStrike = parseFloat(pair.ce_strike);
        state.ceEntryPrice = parseFloat(pair.ce_price);
        state.peStrike = parseFloat(pair.pe_strike);
        state.peEntryPrice = parseFloat(pair.pe_price);

        const expEl = document.getElementById('user-trade-expiry');
        const ceStrEl = document.getElementById('user-trade-ce-strike');
        const cePrEl = document.getElementById('user-trade-ce-price');
        const peStrEl = document.getElementById('user-trade-pe-strike');
        const pePrEl = document.getElementById('user-trade-pe-price');

        if (expEl) expEl.value = expiry;
        if (ceStrEl) ceStrEl.value = state.ceStrike;
        if (cePrEl) cePrEl.value = state.ceEntryPrice;
        if (peStrEl) peStrEl.value = state.peStrike;
        if (pePrEl) pePrEl.value = state.peEntryPrice;

        // Highlight selected row in table
        document.querySelectorAll('.pair-row').forEach(r => r.classList.remove('ring-2', 'ring-indigo-500', 'bg-indigo-50/50'));

        renderPlaybook();
        updateLotCalculations();
        subscribeLiveKeys();

        // Smooth scroll to playbook
        const pbEl = document.getElementById('playbook-section');
        if (pbEl) {
            pbEl.scrollIntoView({ behavior: 'smooth' });
        }
    };

    window.updateLotCalculations = function() {
        const input = document.getElementById('pb-total-lots');
        let totalLots = parseInt(input ? input.value : 10) || 10;
        if (totalLots < 2) totalLots = 2;
        state.totalLots = totalLots;

        const tranche1Lots = Math.max(1, Math.round(totalLots * 0.35));
        const tranche2Lots = totalLots - tranche1Lots;
        const tranche1Qty = tranche1Lots * state.lotSize; // 65 QTY PER LOT
        const tranche2Qty = tranche2Lots * state.lotSize; // 65 QTY PER LOT

        const t1El = document.getElementById('pb-tranche1-lots');
        const t2El = document.getElementById('pb-tranche2-lots');
        const r1InitLots = document.getElementById('r1-init-lots');
        const r1InitLotsPe = document.getElementById('r1-init-lots-pe');
        const r1ResLots = document.getElementById('r1-res-lots');

        if (t1El) t1El.textContent = `${tranche1Lots} Lots (${tranche1Qty} Qty)`;
        if (t2El) t2El.textContent = `${tranche2Lots} Lots (${tranche2Qty} Qty)`;
        if (r1InitLots) r1InitLots.textContent = `${tranche1Lots} Lots (${tranche1Qty} Qty)`;
        if (r1InitLotsPe) r1InitLotsPe.textContent = `${tranche1Lots} Lots (${tranche1Qty} Qty)`;
        if (r1ResLots) r1ResLots.textContent = `${tranche2Lots} Lots (${tranche2Qty} Qty)`;
    };

    function renderPlaybook() {
        if (!state.currentPair) return;
        const col = state.currentCol;

        const ceStrike = state.ceStrike;
        const peStrike = state.peStrike;
        const ceEntry = state.ceEntryPrice;
        const peEntry = state.peEntryPrice;
        const combinedEntry = ceEntry + peEntry;

        const ceLtp = state.ceLtp;
        const peLtp = state.peLtp;
        const combinedLtp = ceLtp + peLtp;

        const upperBreakeven = ceStrike + combinedEntry;
        const lowerBreakeven = peStrike - combinedEntry;
        const totalCorridor = upperBreakeven - lowerBreakeven;
        state.upperBreakeven = upperBreakeven;
        state.lowerBreakeven = lowerBreakeven;
        state.totalCorridor = totalCorridor;

        const ceSl = (ceEntry * 2.0).toFixed(2);
        const peSl = (peEntry * 2.0).toFixed(2);

        // Header Expiry
        const expTag = document.getElementById('pb-expiry-tag');
        if (expTag && col) expTag.textContent = `${col.label}: ${state.userExpiry || col.expiry}`;

        // Metric Cards
        const spotEl = document.getElementById('pb-live-spot');
        if (spotEl) spotEl.textContent = `₹${state.spotPrice.toLocaleString('en-IN', { minimumFractionDigits: 2 })}`;

        const spotDiffEl = document.getElementById('pb-spot-diff');
        if (spotDiffEl) {
            const diff = state.spotPrice - state.initialSpot;
            spotDiffEl.textContent = (diff >= 0 ? '+' : '') + diff.toFixed(2);
            spotDiffEl.className = diff >= 0 ? 'text-emerald-600 font-mono font-bold' : 'text-rose-600 font-mono font-bold';
        }

        const corridorSpan = document.getElementById('pb-corridor-span');
        if (corridorSpan) corridorSpan.textContent = `${Math.round(lowerBreakeven).toLocaleString()} ↔ ${Math.round(upperBreakeven).toLocaleString()}`;

        const totalCorridorPts = document.getElementById('pb-total-corridor-pts');
        if (totalCorridorPts) totalCorridorPts.textContent = `${Math.round(totalCorridor).toLocaleString()} pts`;

        // CE Metric
        const ceTitle = document.getElementById('pb-ce-strike-title');
        if (ceTitle) ceTitle.textContent = `${Math.round(ceStrike).toLocaleString()} CE`;

        const ceDeltaBadge = document.getElementById('pb-ce-delta-badge');
        if (ceDeltaBadge && state.currentPair) ceDeltaBadge.textContent = `Δ ${(state.currentPair.ce_delta >= 0 ? '+' : '') + parseFloat(state.currentPair.ce_delta).toFixed(3)}`;

        const liveCe = document.getElementById('pb-live-ce');
        if (liveCe) liveCe.textContent = `₹${ceLtp.toFixed(2)}`;

        const ceEntryLbl = document.getElementById('pb-ce-entry-lbl');
        if (ceEntryLbl) ceEntryLbl.textContent = `Entry: ₹${ceEntry.toFixed(2)}`;

        const ceSlVal = document.getElementById('pb-ce-sl-val');
        if (ceSlVal) ceSlVal.textContent = `₹${ceSl}`;

        // PE Metric
        const peTitle = document.getElementById('pb-pe-strike-title');
        if (peTitle) peTitle.textContent = `${Math.round(peStrike).toLocaleString()} PE`;

        const peDeltaBadge = document.getElementById('pb-pe-delta-badge');
        if (peDeltaBadge && state.currentPair) peDeltaBadge.textContent = `Δ ${parseFloat(state.currentPair.pe_delta).toFixed(3)}`;

        const livePe = document.getElementById('pb-live-pe');
        if (livePe) livePe.textContent = `₹${peLtp.toFixed(2)}`;

        const peEntryLbl = document.getElementById('pb-pe-entry-lbl');
        if (peEntryLbl) peEntryLbl.textContent = `Entry: ₹${peEntry.toFixed(2)}`;

        const peSlVal = document.getElementById('pb-pe-sl-val');
        if (peSlVal) peSlVal.textContent = `₹${peSl}`;

        // Combined Metric
        const liveComb = document.getElementById('pb-live-combined');
        if (liveComb) liveComb.textContent = `₹${combinedLtp.toFixed(2)}`;

        const combEntryLbl = document.getElementById('pb-combined-entry-lbl');
        if (combEntryLbl) combEntryLbl.textContent = `Entry: ₹${combinedEntry.toFixed(2)}`;

        const harvestTarget = document.getElementById('pb-harvest-target');
        if (harvestTarget) harvestTarget.textContent = `₹${(combinedEntry * 0.5).toFixed(2)}`;

        const combPnl = document.getElementById('pb-combined-pnl');
        if (combPnl) {
            const ptsDecayed = combinedEntry - combinedLtp;
            combPnl.textContent = (ptsDecayed >= 0 ? '+' : '') + ptsDecayed.toFixed(2) + ' pts';
            combPnl.className = ptsDecayed >= 0 ? 'text-xs font-bold text-emerald-600 font-mono' : 'text-xs font-bold text-rose-600 font-mono';
        }

        // Real-Time P&L Calculations (Nifty Multiplier = 65)
        const totalUserQty = state.initialLots * state.lotSize; // e.g. 4 * 65 = 260
        const cePtsDiff = ceEntry - ceLtp; // option seller gains when LTP falls
        const pePtsDiff = peEntry - peLtp; // option seller gains when LTP falls
        const netPtsDiff = cePtsDiff + pePtsDiff;

        const cePnlAmount = cePtsDiff * totalUserQty;
        const pePnlAmount = pePtsDiff * totalUserQty;
        const netPnlAmount = netPtsDiff * totalUserQty;
        const harvestPct = combinedEntry > 0 ? ((netPtsDiff / combinedEntry) * 100).toFixed(1) : 0;

        // Update P&L Monitor DOM
        const pnlCeLtp = document.getElementById('pnl-ce-ltp');
        const pnlCeEntry = document.getElementById('pnl-ce-entry');
        const pnlCePts = document.getElementById('pnl-ce-pts');
        const pnlCeAmount = document.getElementById('pnl-ce-amount');

        if (pnlCeLtp) pnlCeLtp.textContent = `₹${ceLtp.toFixed(2)}`;
        if (pnlCeEntry) pnlCeEntry.textContent = `₹${ceEntry.toFixed(2)}`;
        if (pnlCePts) {
            pnlCePts.textContent = (cePtsDiff >= 0 ? '+' : '') + cePtsDiff.toFixed(2) + ' pts';
            pnlCePts.className = cePtsDiff >= 0 ? 'text-[10px] font-semibold text-emerald-400' : 'text-[10px] font-semibold text-rose-400';
        }
        if (pnlCeAmount) {
            pnlCeAmount.textContent = (cePnlAmount >= 0 ? '+₹' : '-₹') + Math.abs(Math.round(cePnlAmount)).toLocaleString('en-IN');
            pnlCeAmount.className = cePnlAmount >= 0 ? 'text-sm font-black text-emerald-400' : 'text-sm font-black text-rose-400';
        }

        const pnlPeLtp = document.getElementById('pnl-pe-ltp');
        const pnlPeEntry = document.getElementById('pnl-pe-entry');
        const pnlPePts = document.getElementById('pnl-pe-pts');
        const pnlPeAmount = document.getElementById('pnl-pe-amount');

        if (pnlPeLtp) pnlPeLtp.textContent = `₹${peLtp.toFixed(2)}`;
        if (pnlPeEntry) pnlPeEntry.textContent = `₹${peEntry.toFixed(2)}`;
        if (pnlPePts) {
            pnlPePts.textContent = (pePtsDiff >= 0 ? '+' : '') + pePtsDiff.toFixed(2) + ' pts';
            pnlPePts.className = pePtsDiff >= 0 ? 'text-[10px] font-semibold text-emerald-400' : 'text-[10px] font-semibold text-rose-400';
        }
        if (pnlPeAmount) {
            pnlPeAmount.textContent = (pePnlAmount >= 0 ? '+₹' : '-₹') + Math.abs(Math.round(pePnlAmount)).toLocaleString('en-IN');
            pnlPeAmount.className = pePnlAmount >= 0 ? 'text-sm font-black text-emerald-400' : 'text-sm font-black text-rose-400';
        }

        const pnlTotalQty = document.getElementById('pnl-total-qty');
        const pnlHarvestPct = document.getElementById('pnl-harvest-pct');
        const pnlNetPts = document.getElementById('pnl-net-pts');
        const pnlNetAmount = document.getElementById('pnl-net-amount');

        if (pnlTotalQty) pnlTotalQty.textContent = `${state.initialLots} Lots (${totalUserQty} Qty)`;
        if (pnlHarvestPct) pnlHarvestPct.textContent = `${harvestPct}%`;
        if (pnlNetPts) {
            pnlNetPts.textContent = (netPtsDiff >= 0 ? '+' : '') + netPtsDiff.toFixed(2) + ' pts';
            pnlNetPts.className = netPtsDiff >= 0 ? 'text-xs font-bold text-emerald-400' : 'text-xs font-bold text-rose-400';
        }
        if (pnlNetAmount) {
            pnlNetAmount.textContent = (netPnlAmount >= 0 ? '+₹' : '-₹') + Math.abs(Math.round(netPnlAmount)).toLocaleString('en-IN');
            pnlNetAmount.className = netPnlAmount >= 0 ? 'text-lg font-black text-emerald-400' : 'text-lg font-black text-rose-400';
        }

        // Rule 1 Card
        const r1CeStrike = document.getElementById('r1-ce-strike');
        if (r1CeStrike) r1CeStrike.textContent = `${Math.round(ceStrike).toLocaleString()} CE`;

        const r1CePrice = document.getElementById('r1-ce-price');
        if (r1CePrice) r1CePrice.textContent = ceEntry.toFixed(2);

        const r1PeStrike = document.getElementById('r1-pe-strike');
        if (r1PeStrike) r1PeStrike.textContent = `${Math.round(peStrike).toLocaleString()} PE`;

        const r1PePrice = document.getElementById('r1-pe-price');
        if (r1PePrice) r1PePrice.textContent = peEntry.toFixed(2);

        const r1UpTrig = document.getElementById('r1-up-trigger');
        const r1AlertUp = document.getElementById('r1-alert-up');
        const upTrigVal = Math.round(state.initialSpot + 175);
        if (r1UpTrig) r1UpTrig.textContent = upTrigVal.toLocaleString();
        if (r1AlertUp) r1AlertUp.textContent = upTrigVal.toLocaleString();

        const r1DownTrig = document.getElementById('r1-down-trigger');
        const r1AlertDown = document.getElementById('r1-alert-down');
        const downTrigVal = Math.round(state.initialSpot - 175);
        if (r1DownTrig) r1DownTrig.textContent = downTrigVal.toLocaleString();
        if (r1AlertDown) r1AlertDown.textContent = downTrigVal.toLocaleString();

        const r1LadderCe = document.getElementById('r1-ladder-ce');
        if (r1LadderCe) r1LadderCe.textContent = `${Math.round(ceStrike + 150).toLocaleString()} CE`;

        // Rule 1 Dynamic Status
        const r1Badge = document.getElementById('r1-badge');
        const spotMove = Math.abs(state.spotPrice - state.initialSpot);
        if (r1Badge) {
            if (spotMove >= 175) {
                r1Badge.className = 'px-2 py-0.5 rounded text-[11px] font-bold bg-amber-400 text-slate-900 border border-amber-500 animate-pulse';
                r1Badge.textContent = '🟡 TRIGGERED: DEPLOY TRANCHE 2';
            } else {
                r1Badge.className = 'px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300';
                r1Badge.textContent = '🟢 COMFORT ZONE';
            }
        }

        // Rule 2 Card
        const r2RollFrom = document.getElementById('r2-roll-from');
        if (r2RollFrom) r2RollFrom.textContent = `${Math.round(peStrike).toLocaleString()} PE`;

        const r2RollTo = document.getElementById('r2-roll-to');
        if (r2RollTo) r2RollTo.textContent = `${Math.round(peStrike + 300).toLocaleString()} PE`;

        // Rule 2 Dynamic Status
        const r2Badge = document.getElementById('r2-badge');
        if (r2Badge) {
            if (peLtp <= 15.0 || ceLtp <= 15.0) {
                r2Badge.className = 'px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-500 text-white border border-emerald-600 animate-pulse';
                r2Badge.textContent = peLtp <= 15.0 ? '🎯 ROLL PE: HIT ₹15 DECAY' : '🎯 ROLL CE: HIT ₹15 DECAY';
            } else {
                r2Badge.className = 'px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-300';
                r2Badge.textContent = '⏳ WAITING FOR 65% DECAY';
            }
        }

        // Rule 3 Card
        const r3WingCe = document.getElementById('r3-wing-ce');
        if (r3WingCe) r3WingCe.textContent = `${Math.round(ceStrike + 300).toLocaleString()} CE`;

        const r3WingPe = document.getElementById('r3-wing-pe');
        if (r3WingPe) r3WingPe.textContent = `${Math.round(peStrike - 300).toLocaleString()} PE`;

        // Rule 4 Card
        const r4CeSl = document.getElementById('r4-ce-sl');
        if (r4CeSl) r4CeSl.textContent = `₹${ceSl}`;

        const r4CeEntry = document.getElementById('r4-ce-entry');
        if (r4CeEntry) r4CeEntry.textContent = ceEntry.toFixed(2);

        const r4PeSl = document.getElementById('r4-pe-sl');
        if (r4PeSl) r4PeSl.textContent = `₹${peSl}`;

        const r4PeEntry = document.getElementById('r4-pe-entry');
        if (r4PeEntry) r4PeEntry.textContent = peEntry.toFixed(2);

        // Rule 4 Dynamic Status
        const r4Badge = document.getElementById('r4-badge');
        if (r4Badge) {
            if (ceLtp >= parseFloat(ceSl) || peLtp >= parseFloat(peSl)) {
                r4Badge.className = 'px-2 py-0.5 rounded text-[11px] font-black bg-rose-600 text-white border border-rose-700 animate-pulse';
                r4Badge.textContent = '🔴 FIRE EXIT: STOP LOSS HIT!';
            } else {
                r4Badge.className = 'px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300';
                r4Badge.textContent = '🟢 SAFE';
            }
        }

        // Generate Human-Readable Commentary Above the 4 Rules
        generateCommentary(spotMove, netPtsDiff, netPnlAmount, totalUserQty, ceSl, peSl);
    }

    // Dynamic Situation & Strategy Commentary Generator
    function generateCommentary(spotMove, netPtsDiff, netPnlAmount, totalUserQty, ceSl, peSl) {
        const ceLtp = state.ceLtp;
        const peLtp = state.peLtp;
        const spot = state.spotPrice;
        const initSpot = state.initialSpot;
        const rawSpotDiff = spot - initSpot;
        const moveSign = rawSpotDiff >= 0 ? "+" : "";
        const pnlSign = netPnlAmount >= 0 ? "+" : "";

        const commentaryTextEl = document.getElementById('pb-commentary-text');
        const diagnosisBadgeEl = document.getElementById('pb-diagnosis-badge');

        const dR1Status = document.getElementById('diag-rule1-status');
        const dR1Sub = document.getElementById('diag-rule1-sub');
        const dR2Status = document.getElementById('diag-rule2-status');
        const dR2Sub = document.getElementById('diag-rule2-sub');
        const dR3Status = document.getElementById('diag-rule3-status');
        const dR3Sub = document.getElementById('diag-rule3-sub');
        const dR4Status = document.getElementById('diag-rule4-status');
        const dR4Sub = document.getElementById('diag-rule4-sub');

        let narrative = "";

        // Check Trigger Statuses
        const isSlHit = (ceLtp >= parseFloat(ceSl) || peLtp >= parseFloat(peSl));
        const isRollHit = (peLtp <= 15.0 || ceLtp <= 15.0);
        const isAveragingHit = (spotMove >= 175);

        // Update Gauges
        if (dR1Status) {
            dR1Status.textContent = isAveragingHit ? "🟡 Triggered: Deploy Tranche 2" : "🟢 In Comfort Zone";
            dR1Status.className = isAveragingHit ? "font-bold text-amber-300 mt-0.5" : "font-bold text-slate-200 mt-0.5";
            if (dR1Sub) dR1Sub.textContent = isAveragingHit ? `Spot moved ${moveSign}${rawSpotDiff.toFixed(1)} pts` : `Spot move: ${moveSign}${rawSpotDiff.toFixed(1)} pts (limit ±175)`;
        }

        if (dR2Status) {
            dR2Status.textContent = isRollHit ? (peLtp <= 15.0 ? "🎯 Roll PE: Decayed to ₹" + peLtp.toFixed(2) : "🎯 Roll CE: Decayed to ₹" + ceLtp.toFixed(2)) : "🟢 Decaying Normally";
            dR2Status.className = isRollHit ? "font-bold text-emerald-300 mt-0.5 animate-pulse" : "font-bold text-slate-200 mt-0.5";
            if (dR2Sub) dR2Sub.textContent = isRollHit ? "Lock ~₹28 profit and roll closer" : `PE: ₹${peLtp.toFixed(2)} | CE: ₹${ceLtp.toFixed(2)} (Target ≤ ₹15)`;
        }

        if (dR3Status) {
            dR3Status.textContent = "🛡️ Buy Wings Before 15:15";
            if (dR3Sub) dR3Sub.textContent = `Buy ${Math.round(state.ceStrike + 300)} CE & ${Math.round(state.peStrike - 300)} PE`;
        }

        if (dR4Status) {
            dR4Status.textContent = isSlHit ? "🔴 STOP LOSS BREACHED!" : "🟢 Safe (< 2.0x SL)";
            dR4Status.className = isSlHit ? "font-bold text-rose-400 mt-0.5 animate-pulse" : "font-bold text-slate-200 mt-0.5";
            if (dR4Sub) dR4Sub.textContent = isSlHit ? "Execute market exit on breached leg" : `SL Limits: CE ₹${ceSl} | PE ₹${peSl}`;
        }

        // 1. Critical Hard SL Triggered
        if (isSlHit) {
            if (diagnosisBadgeEl) {
                diagnosisBadgeEl.className = "px-3 py-1 rounded-full text-xs font-black bg-rose-600 text-white shadow-md animate-pulse flex items-center gap-1.5";
                diagnosisBadgeEl.innerHTML = '<span class="h-2 w-2 rounded-full bg-white animate-ping"></span><span>🚨 CRITICAL: HARD STOP LOSS HIT</span>';
            }
            const breachedLeg = ceLtp >= parseFloat(ceSl) ? `Call leg (${state.ceStrike} CE @ ₹${ceLtp.toFixed(2)} vs SL ₹${ceSl})` : `Put leg (${state.peStrike} PE @ ₹${peLtp.toFixed(2)} vs SL ₹${peSl})`;
            narrative = `🚨 <strong class="text-rose-300">FIRE EXIT TRIGGERED (Rule 04 Active):</strong> Your ${breachedLeg} has reached its 2.0x risk limit! 
            <br><span class="text-white font-semibold">&bull; Current Situation:</span> Directional momentum has pushed this leg into stop-loss territory. Net position P&L is currently <span class="${netPnlAmount >= 0 ? 'text-emerald-400 font-bold' : 'text-rose-400 font-bold'}">${pnlSign}₹${Math.round(netPnlAmount).toLocaleString('en-IN')} (${netPtsDiff.toFixed(2)} pts)</span> on ${state.initialLots} lots (${totalUserQty} Qty).
            <br><span class="text-amber-300 font-semibold">&bull; Action Needed Right Now:</span> <strong>Exit and cut the losing leg immediately at market price!</strong> Do not average down or hope for reversal. Leave the opposite winning leg open (it has collapsed to buffer >60% of the stop loss hit).`;
        }
        // 2. Roll Winning Leg Triggered
        else if (isRollHit) {
            if (diagnosisBadgeEl) {
                diagnosisBadgeEl.className = "px-3 py-1 rounded-full text-xs font-black bg-emerald-600 text-white shadow-md animate-pulse flex items-center gap-1.5";
                diagnosisBadgeEl.innerHTML = '<span class="h-2 w-2 rounded-full bg-white animate-ping"></span><span>🎯 ACTION: ROLL WINNING LEG (RULE 2)</span>';
            }
            const rollLegName = peLtp <= 15.0 ? `Put leg (${state.peStrike} PE @ ₹${peLtp.toFixed(2)})` : `Call leg (${state.ceStrike} CE @ ₹${ceLtp.toFixed(2)})`;
            const profitPerShare = peLtp <= 15.0 ? (state.peEntryPrice - peLtp).toFixed(2) : (state.ceEntryPrice - ceLtp).toFixed(2);
            narrative = `🎯 <strong class="text-emerald-300">THETA HARVESTING ACTIVE (Rule 02 Triggered):</strong> Your ${rollLegName} has decayed by over 65% below the ₹15.00 threshold!
            <br><span class="text-white font-semibold">&bull; Current Situation:</span> You have captured <strong class="text-emerald-400">+₹${profitPerShare} / share</strong> of profit on this leg. Total net P&L is currently <strong class="text-emerald-400">+₹${Math.round(netPnlAmount).toLocaleString('en-IN')} (+${netPtsDiff.toFixed(2)} pts)</strong> on ${state.initialLots} lots (${totalUserQty} Qty).
            <br><span class="text-amber-300 font-semibold">&bull; Action Needed Right Now:</span> <strong>Square off this decayed leg now to lock in profit.</strong> Then roll your strike 200–300 points closer to current spot to sell a new strike trading at ₹35–₹40. This expands your breakeven buffer by another 35+ points and resets your net delta back to zero.`;
        }
        // 3. Staggered Average Triggered
        else if (isAveragingHit) {
            if (diagnosisBadgeEl) {
                diagnosisBadgeEl.className = "px-3 py-1 rounded-full text-xs font-black bg-amber-500 text-slate-900 shadow-md animate-pulse flex items-center gap-1.5";
                diagnosisBadgeEl.innerHTML = '<span class="h-2 w-2 rounded-full bg-slate-900 animate-ping"></span><span>⚡ ACTION: DEPLOY TRANCHE 2 (RULE 1)</span>';
            }
            const dir = rawSpotDiff > 0 ? "rallied upwards" : "dropped downwards";
            const newStrike = rawSpotDiff > 0 ? (state.ceStrike + 150) : (state.peStrike - 150);
            const optType = rawSpotDiff > 0 ? "CE" : "PE";
            narrative = `⚡ <strong class="text-amber-300">MARKET EXPANSION ALERT (Rule 01 Triggered):</strong> Nifty has ${dir} by <strong>${Math.abs(rawSpotDiff).toFixed(1)} points</strong> to ₹${spot.toLocaleString('en-IN', {minimumFractionDigits:2})}, crossing your ±175 pt comfort limit.
            <br><span class="text-white font-semibold">&bull; Current Situation:</span> Spot is testing your outer corridor boundary. Net P&L is <span class="${netPnlAmount >= 0 ? 'text-emerald-400 font-bold' : 'text-rose-400 font-bold'}">${pnlSign}₹${Math.round(netPnlAmount).toLocaleString('en-IN')} (${netPtsDiff.toFixed(2)} pts)</span> on ${state.initialLots} lots (${totalUserQty} Qty).
            <br><span class="text-amber-300 font-semibold">&bull; Action Needed Right Now:</span> <strong>DO NOT average down on the challenged strike!</strong> Deploy Tranche 2 (3 lots) by selling a fresh higher/lower strike (${newStrike} ${optType}) currently trading at ₹40–₹45. This averages your entry safely while shifting your overall breakeven higher.`;
        }
        // 4. Normal Safe Decay State
        else {
            if (diagnosisBadgeEl) {
                diagnosisBadgeEl.className = "px-3 py-1 rounded-full text-xs font-black bg-emerald-500 text-white shadow-md flex items-center gap-1.5";
                diagnosisBadgeEl.innerHTML = '<span class="h-2 w-2 rounded-full bg-white animate-ping"></span><span>✅ POSITION HEALTHY: SAFE &amp; DECAYING</span>';
            }
            narrative = `📍 <strong class="text-indigo-200">CURRENT POSITION SITUATION:</strong> Spot is currently at <strong>₹${spot.toLocaleString('en-IN', {minimumFractionDigits:2})}</strong> (${moveSign}${rawSpotDiff.toFixed(1)} pts from your ${state.entryTime} entry spot of ₹${initSpot.toLocaleString('en-IN', {minimumFractionDigits:2})}). 
            <br><span class="text-white font-semibold">&bull; Safe Range:</span> Market is floating safely inside your <strong>${Math.round(state.lowerBreakeven).toLocaleString()} ↔ ${Math.round(state.upperBreakeven).toLocaleString()}</strong> corridor with <strong>${Math.round(state.totalCorridor).toLocaleString()} points total cushion</strong>.
            <br><span class="text-white font-semibold">&bull; Live P&amp;L:</span> You have captured <strong class="text-emerald-400">${moveSign}${netPtsDiff.toFixed(2)} pts</strong> of net theta decay. Current profit/loss is <strong class="${netPnlAmount >= 0 ? 'text-emerald-400' : 'text-rose-400'}">${pnlSign}₹${Math.round(netPnlAmount).toLocaleString('en-IN')}</strong> on ${state.initialLots} Lots (${totalUserQty} Qty).
            <br><span class="text-emerald-300 font-semibold">&bull; Next Step Onwards:</span> <strong>No defense action needed right now.</strong> All 4 rules are monitoring in the background. Simply hold and let theta decay work. Watch for Rule 2 when either leg touches ≤ ₹15.00, or Rule 1 if Spot tests ${Math.round(initSpot + 175).toLocaleString()} / ${Math.round(initSpot - 175).toLocaleString()}.`;
        }

        if (commentaryTextEl) {
            commentaryTextEl.innerHTML = narrative;
        }
    }

    // Simulation Handlers
    window.simulateScenario = function(scenario) {
        if (!state.currentPair) return;
        const ceEntry = state.ceEntryPrice;
        const peEntry = state.peEntryPrice;

        if (scenario === 'normal_decay') {
            state.ceLtp = 15.00;
            state.peLtp = 15.00;
        } else if (scenario === 'rally_180') {
            state.spotPrice = state.initialSpot + 180;
            state.ceLtp = parseFloat((ceEntry * 1.6).toFixed(2));
            state.peLtp = 14.50; // decayed below 15
        } else if (scenario === 'drop_200') {
            state.spotPrice = state.initialSpot - 200;
            state.ceLtp = 13.80; // decayed below 15
            state.peLtp = parseFloat((peEntry * 1.7).toFixed(2));
        } else if (scenario === 'ce_sl_hit') {
            state.spotPrice = state.initialSpot + 260;
            state.ceLtp = parseFloat((ceEntry * 2.05).toFixed(2)); // hit 2x SL
            state.peLtp = 9.50;
        }

        state.tickCount++;
        const tickEl = document.getElementById('pb-tick-counter');
        if (tickEl) tickEl.textContent = `${state.tickCount} sim ticks`;

        renderPlaybook();
    };

    window.resetToInitialPair = function() {
        if (!state.initialPair) return;
        state.spotPrice = state.initialSpot;
        state.ceLtp = state.ceEntryPrice;
        state.peLtp = state.peEntryPrice;
        state.tickCount = 0;
        const tickEl = document.getElementById('pb-tick-counter');
        if (tickEl) tickEl.textContent = '0 ticks';
        renderPlaybook();
    };

    // WebSocket Upstox Connection
    function subscribeLiveKeys() {
        if (!state.ws || state.ws.readyState !== WebSocket.OPEN) return;
        const keys = [];
        if (state.currentPair) {
            if (state.currentPair.ce_instrument_key) keys.push(state.currentPair.ce_instrument_key);
            if (state.currentPair.pe_instrument_key) keys.push(state.currentPair.pe_instrument_key);
        }
        keys.push('NSE_INDEX|Nifty 50');

        if (keys.length > 0) {
            const subMessage = {
                guid: 'strike_matcher_' + Date.now(),
                method: 'sub',
                data: { mode: 'full', instrumentKeys: keys }
            };
            state.ws.send(new TextEncoder().encode(JSON.stringify(subMessage)));
        }
    }

    async function initWebSocket() {
        try {
            if (typeof protobuf === 'undefined') return;
            state.protobufRoot = await protobuf.load('/MarketDataFeed.proto');

            const res = await fetch('/api/matching-strike-analysis/ws-url');
            if (!res.ok) throw new Error('WS Token unavailable');
            const data = await res.json();
            const wsUrl = data?.data?.authorizedRedirectUri;
            if (!wsUrl) throw new Error('No redirect URI');

            state.ws = new WebSocket(wsUrl);
            state.ws.binaryType = 'arraybuffer';

            state.ws.onopen = () => {
                const dot = document.getElementById('pb-ws-dot');
                const txt = document.getElementById('pb-ws-text');
                if (dot) dot.className = 'inline-block h-2.5 w-2.5 rounded-full bg-emerald-400 shadow-sm animate-pulse';
                if (txt) txt.textContent = 'Live Feed Connected';
                subscribeLiveKeys();
            };

            state.ws.onmessage = (event) => {
                decodeFeed(event.data);
            };

            state.ws.onclose = () => {
                const dot = document.getElementById('pb-ws-dot');
                const txt = document.getElementById('pb-ws-text');
                if (dot) dot.className = 'inline-block h-2.5 w-2.5 rounded-full bg-amber-400';
                if (txt) txt.textContent = 'Stream Offline (Static)';
            };
        } catch (e) {
            const dot = document.getElementById('pb-ws-dot');
            const txt = document.getElementById('pb-ws-text');
            if (dot) dot.className = 'inline-block h-2.5 w-2.5 rounded-full bg-slate-400';
            if (txt) txt.textContent = 'Sim Ready / Static';
        }
    }

    function decodeFeed(buffer) {
        if (!state.protobufRoot) return;
        try {
            const arr = new Uint8Array(buffer);
            if (arr.length > 0 && arr[0] === 123) return; // JSON text message
            const FeedResponse = state.protobufRoot.lookupType('com.upstox.marketdatafeederv3udapi.rpc.proto.FeedResponse');
            const message = FeedResponse.decode(arr);
            const obj = FeedResponse.toObject(message, { enums: String, bytes: String });

            if (obj && obj.feeds) {
                let updated = false;
                for (const [key, feed] of Object.entries(obj.feeds)) {
                    let ltp = null;
                    if (feed.fullFeed?.marketFF?.ltpc?.ltp) ltp = feed.fullFeed.marketFF.ltpc.ltp;
                    else if (feed.fullFeed?.indexFF?.ltpc?.ltp) ltp = feed.fullFeed.indexFF.ltpc.ltp;

                    if (ltp !== null) {
                        if (key.includes('Nifty 50')) {
                            state.spotPrice = ltp;
                            updated = true;
                        } else if (state.currentPair && key === state.currentPair.ce_instrument_key) {
                            state.ceLtp = ltp;
                            updated = true;
                        } else if (state.currentPair && key === state.currentPair.pe_instrument_key) {
                            state.peLtp = ltp;
                            updated = true;
                        }
                    }
                }
                if (updated) {
                    state.tickCount++;
                    const tickEl = document.getElementById('pb-tick-counter');
                    if (tickEl) tickEl.textContent = `${state.tickCount} ticks`;
                    renderPlaybook();
                }
            }
        } catch (err) {
            // ignore malformed buffer
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', () => {
        renderPlaybook();
        updateLotCalculations();
        initWebSocket();
    });
})();
</script>
@endpush

