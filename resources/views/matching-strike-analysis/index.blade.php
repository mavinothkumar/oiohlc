@extends('layouts.app')

@section('title')
    Matching Strike Analysis – Current & Next Week Expiry
@endsection

@section('content')
    <div class="bg-slate-50 text-slate-800 font-sans px-2 sm:px-4 py-3 min-h-screen w-full">
        <div class="w-full max-w-[1700px] mx-auto space-y-4">

            {{-- ═══════════════════════════════════════════════════════════════════ --}}
            {{-- 1. TOP HEADER & FILTER BAR (SLEEK, CLEAN, 1-ROW DESIGN)            --}}
            {{-- ═══════════════════════════════════════════════════════════════════ --}}
            <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-3">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-2.5 pb-2.5 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">⚖️</span>
                        <div>
                            <h1 class="text-base sm:text-lg font-black text-slate-900 flex items-center gap-2">
                                <span>Matching Strike Analysis</span>
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    Current &amp; Next Week Expiry
                                </span>
                            </h1>
                            <p class="text-xs text-slate-500">
                                Matching CE and PE strikes in target price range (&#8377;{{ number_format($minPrice, 0) }} – &#8377;{{ number_format($maxPrice, 0) }}) with max price difference &le; &#8377;{{ number_format($maxPriceDiff, 1) }}
                            </p>
                        </div>
                    </div>

                    {{-- Quick Range Presets & Snapshot Indicator --}}
                    <div class="flex items-center gap-2 flex-wrap text-xs">
                        <span class="text-[11px] font-bold text-slate-400 uppercase">Presets:</span>
                        <a href="{{ request()->fullUrlWithQuery(['min_price' => 30, 'max_price' => 60, 'max_delta' => 3.0]) }}"
                           class="px-2 py-1 rounded text-xs font-semibold transition {{ ($minPrice == 30 && $maxPrice == 60) ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200' }}">
                            &#8377;30 – &#8377;60 (Default)
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['min_price' => 20, 'max_price' => 50, 'max_delta' => 3.0]) }}"
                           class="px-2 py-1 rounded text-xs font-semibold transition {{ ($minPrice == 20 && $maxPrice == 50) ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200' }}">
                            &#8377;20 – &#8377;50
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['min_price' => 50, 'max_price' => 100, 'max_delta' => 4.0]) }}"
                           class="px-2 py-1 rounded text-xs font-semibold transition {{ ($minPrice == 50 && $maxPrice == 100) ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200' }}">
                            &#8377;50 – &#8377;100
                        </a>

                        @if($actualTs)
                            <span class="text-slate-300">|</span>
                            <span class="text-slate-500 font-mono-num text-[11px]">
                                Captured: <strong class="text-slate-700">{{ \Carbon\Carbon::parse($actualTs)->format('d M Y, H:i') }}</strong>
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Compact Filter Form --}}
                <form method="GET" class="flex flex-wrap items-end gap-2 text-xs">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Trading Date</label>
                        <select name="date" class="w-32 border border-slate-300 rounded-lg px-2.5 py-1 text-xs bg-white font-semibold text-slate-800 shadow-xs focus:ring-indigo-500" onchange="this.form.submit()">
                            @foreach($availableDates as $ad)
                                <option value="{{ $ad }}" {{ $selectedDate == $ad ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::parse($ad)->format('d M Y') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Min (&#8377;)</label>
                        <input type="number" step="1" name="min_price" value="{{ $minPrice }}"
                               class="w-20 border border-slate-300 rounded-lg px-2 py-1 text-xs bg-white font-mono font-bold text-slate-800 shadow-xs focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Max (&#8377;)</label>
                        <input type="number" step="1" name="max_price" value="{{ $maxPrice }}"
                               class="w-20 border border-slate-300 rounded-lg px-2 py-1 text-xs bg-white font-mono font-bold text-slate-800 shadow-xs focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1" title="Max Price Difference between CE and PE">
                            Max Diff (&#8377;)
                        </label>
                        <input type="number" step="0.5" name="max_delta" value="{{ $maxPriceDiff }}"
                               class="w-24 border border-slate-300 rounded-lg px-2 py-1 text-xs bg-white font-mono font-bold text-slate-800 shadow-xs focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Custom ATM</label>
                        <input type="text" name="custom_atm" value="{{ $customAtm ?? '' }}" placeholder="Auto Spot"
                               class="w-24 border border-slate-300 rounded-lg px-2 py-1 text-xs bg-white font-mono text-slate-800 shadow-xs focus:ring-indigo-500">
                    </div>

                    <div class="flex items-center gap-1.5 ml-auto">
                        <button type="submit"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-3.5 py-1 rounded-lg transition text-xs h-[29px] shadow-xs cursor-pointer flex items-center gap-1">
                            🔍 Filter
                        </button>
                        <a href="{{ route('matching.strike.analysis') }}"
                           class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold px-2.5 py-1 rounded-lg transition text-xs h-[29px] border border-slate-300 flex items-center">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            {{-- ═══════════════════════════════════════════════════════════════════ --}}
            {{-- 2. CURRENT WEEK & NEXT WEEK TABLES (SIDE BY SIDE)                   --}}
            {{-- ═══════════════════════════════════════════════════════════════════ --}}
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                @forelse($columnsData as $colIndex => $col)
                    <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden flex flex-col">
                        {{-- Column Card Header --}}
                        <div class="px-3.5 py-2.5 border-b border-slate-200 bg-slate-50 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm">{{ $col['is_current'] ? '⚡' : '📅' }}</span>
                                    <h2 class="text-xs sm:text-sm font-black text-slate-900 tracking-tight">
                                        {{ $col['label'] }}:
                                        <span class="text-indigo-700 font-mono-num ml-1">
                                            {{ \Carbon\Carbon::parse($col['expiry'])->format('d M Y') }}
                                        </span>
                                    </h2>
                                    <span class="px-1.5 py-0.2 rounded text-[10px] font-bold font-mono-num {{ $col['is_current'] ? 'bg-amber-100 text-amber-900 border border-amber-200' : 'bg-blue-100 text-blue-900 border border-blue-200' }}">
                                        {{ $col['is_current'] ? 'CURRENT' : 'NEXT' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 text-[11px] font-mono-num text-slate-500 mt-0.5">
                                    <span>Spot: <strong class="text-slate-800">&#8377;{{ number_format($col['spot_price'], 2) }}</strong></span>
                                    <span>&bull;</span>
                                    <span>ATM: <strong class="text-indigo-800 font-black">{{ $col['atm_strike'] }}</strong></span>
                                    <span>&bull;</span>
                                    <span>In-Range: <strong class="text-slate-700">{{ $col['ce_count'] }} CE / {{ $col['pe_count'] }} PE</strong></span>
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5 text-xs font-mono-num">
                                <span class="px-2 py-0.5 rounded-md font-bold text-[11px] border {{ count($col['matched_pairs']) > 0 ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-slate-100 text-slate-600 border-slate-200' }}">
                                    🎯 {{ count($col['matched_pairs']) }} Pairs
                                </span>
                            </div>
                        </div>

                        {{-- Table of Matched Pairs --}}
                        <div class="overflow-x-auto custom-scrollbar flex-1 max-h-[420px]">
                            @if(count($col['matched_pairs']) > 0)
                                <table class="w-full text-xs border-collapse">
                                    <thead class="bg-slate-100 border-b border-slate-200 text-[10px] uppercase font-bold text-slate-600 tracking-tight select-none sticky top-0 z-10 shadow-xs">
                                    <tr>
                                        <th class="px-2 py-2 text-right whitespace-nowrap text-blue-800 bg-blue-50/50">CE &Delta;</th>
                                        <th class="px-2 py-2 text-right whitespace-nowrap text-blue-900 bg-blue-50/80 font-black">CE Price</th>
                                        <th class="px-2 py-2 text-center whitespace-nowrap text-blue-950 font-black bg-blue-100/60">CE Strike</th>
                                        <th class="px-2 py-2 text-center whitespace-nowrap text-slate-600">CE Dist</th>
                                        <th class="px-2 py-2 text-center whitespace-nowrap text-slate-900 font-black bg-amber-50 border-x border-amber-200">ATM</th>
                                        <th class="px-2 py-2 text-center whitespace-nowrap text-slate-600">PE Dist</th>
                                        <th class="px-2 py-2 text-center whitespace-nowrap text-rose-950 font-black bg-rose-100/60">PE Strike</th>
                                        <th class="px-2 py-2 text-right whitespace-nowrap text-rose-900 bg-rose-50/80 font-black">PE Price</th>
                                        <th class="px-2 py-2 text-right whitespace-nowrap text-rose-800 bg-rose-50/50">PE &Delta;</th>
                                        <th class="px-2 py-2 text-center whitespace-nowrap text-slate-700 font-bold">Diff</th>
                                        <th class="px-2 py-2 text-right whitespace-nowrap text-slate-800 font-black">Combined</th>
                                        <th class="px-2 py-2 text-center whitespace-nowrap text-slate-600 font-bold">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 font-mono-num text-[11px]">
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
                                        <tr class="pair-row hover:bg-indigo-50/40 transition-colors {{ $isTightMatch ? 'bg-emerald-50/20' : '' }}">
                                            <td class="px-2 py-1.5 text-right font-semibold text-blue-700 whitespace-nowrap">
                                                {{ $p['ce_delta'] >= 0 ? '+' : '' }}{{ number_format($p['ce_delta'], 3) }}
                                            </td>
                                            <td class="px-2 py-1.5 text-right font-black text-blue-900 bg-blue-50/30 whitespace-nowrap">
                                                &#8377;{{ number_format($p['ce_price'], 2) }}
                                            </td>
                                            <td class="px-2 py-1.5 text-center font-black text-blue-950 whitespace-nowrap">
                                                <span class="px-1.5 py-0.5 rounded bg-blue-100/70 border border-blue-200 text-blue-900">
                                                    {{ number_format($p['ce_strike']) }} CE
                                                </span>
                                            </td>
                                            <td class="px-2 py-1.5 text-center text-slate-500 whitespace-nowrap">
                                                +{{ $p['ce_dist'] }}
                                            </td>
                                            <td class="px-2 py-1.5 text-center font-bold text-slate-800 bg-amber-50/60 border-x border-amber-200/50 whitespace-nowrap">
                                                {{ number_format($p['atm']) }}
                                            </td>
                                            <td class="px-2 py-1.5 text-center text-slate-500 whitespace-nowrap">
                                                -{{ $p['pe_dist'] }}
                                            </td>
                                            <td class="px-2 py-1.5 text-center font-black text-rose-950 whitespace-nowrap">
                                                <span class="px-1.5 py-0.5 rounded bg-rose-100/70 border border-rose-200 text-rose-900">
                                                    {{ number_format($p['pe_strike']) }} PE
                                                </span>
                                            </td>
                                            <td class="px-2 py-1.5 text-right font-black text-rose-900 bg-rose-50/30 whitespace-nowrap">
                                                &#8377;{{ number_format($p['pe_price'], 2) }}
                                            </td>
                                            <td class="px-2 py-1.5 text-right font-semibold text-rose-700 whitespace-nowrap">
                                                {{ number_format($p['pe_delta'], 3) }}
                                            </td>
                                            <td class="px-2 py-1.5 text-center font-bold whitespace-nowrap">
                                                <span class="px-1 rounded text-[10px] {{ $p['price_diff'] <= 1.0 ? 'bg-emerald-100 text-emerald-900' : 'text-slate-600' }}">
                                                    &#8377;{{ number_format($p['price_diff'], 2) }}
                                                </span>
                                            </td>
                                            <td class="px-2 py-1.5 text-right font-black text-slate-900 whitespace-nowrap">
                                                &#8377;{{ number_format($p['combined_price'], 2) }}
                                            </td>
                                            <td class="px-2 py-1.5 text-center whitespace-nowrap">
                                                <div class="flex items-center gap-1 justify-center">
                                                    <button type="button"
                                                            onclick='selectPairForPlaybook(@json($p), "{{ $col['expiry'] }}", "{{ $col['label'] }}", {{ $col['spot_price'] }})'
                                                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition cursor-pointer"
                                                            title="Load this pair into Position Tracker">
                                                        🎯 Select
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
                                <div class="p-6 text-center space-y-1.5 text-slate-500">
                                    <span class="text-2xl">🔍</span>
                                    <p class="font-bold text-slate-700 text-xs">No pairs matched within &#8377;{{ number_format($maxPriceDiff, 1) }} tolerance</p>
                                    <p class="text-[11px] text-slate-400">
                                        Found {{ $col['ce_count'] }} CE and {{ $col['pe_count'] }} PE options in price range &#8377;{{ $minPrice }} – &#8377;{{ $maxPrice }}.
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-2 bg-yellow-50 border border-yellow-300 text-yellow-800 p-6 rounded-xl text-center space-y-2">
                        <span class="text-2xl">⚠️</span>
                        <p class="font-bold text-sm">No Option Chain Data Found</p>
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
                {{-- ═══════════════════════════════════════════════════════════════════ --}}
                {{-- 3. POSITION TRACKER & MULTI-TRANCHE AVERAGING ENGINE               --}}
                {{-- ═══════════════════════════════════════════════════════════════════ --}}
                <div id="playbook-section" class="bg-white rounded-xl shadow-xs border border-slate-200 p-4 space-y-4">
                    
                    {{-- Tracker Header --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">💼</span>
                            <div>
                                <h3 class="text-sm sm:text-base font-black text-slate-900 tracking-tight">
                                    Position Tracker &amp; Multi-Tranche Averaging Engine
                                </h3>
                                <p class="text-xs text-slate-500">
                                    Lot Multiplier: <strong class="text-indigo-700">65 Qty</strong> / Lot &bull; Track blended averages, real-time P&amp;L across tranches, and rule alerts.
                                </p>
                            </div>
                        </div>

                        {{-- Action Controls & Show/Hide Rules Button --}}
                        <div class="flex items-center gap-2 flex-wrap text-xs">
                            <span id="storage-status-badge" class="px-2 py-1 rounded-md text-[11px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-300 flex items-center gap-1 shadow-xs" title="Changes are automatically saved in your browser">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                                <span>💾 Saved in Browser</span>
                            </span>

                            <button type="button" onclick="toggleAddTrancheForm()"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition cursor-pointer flex items-center gap-1 shadow-xs">
                                <span>➕</span>
                                <span>Add Average Tranche</span>
                            </button>

                            <button type="button" onclick="syncFromCurrentPair()"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition cursor-pointer">
                                🔄 Sync Table Pair
                            </button>

                            <button type="button" onclick="clearSavedTrade()"
                                    class="px-2 py-1 rounded-lg text-xs font-bold text-rose-600 hover:bg-rose-50 border border-rose-200 transition cursor-pointer">
                                🗑️ Reset
                            </button>

                            {{-- Show/Hide Strategy Rules Toggle Button (Retained in localStorage!) --}}
                            <button type="button" id="toggle-rules-btn" onclick="toggleRulesGuide()"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-800 hover:bg-slate-900 text-white transition cursor-pointer flex items-center gap-1 shadow-xs ml-1">
                                <span>👁️</span>
                                <span id="toggle-rules-text">Hide Strategy Rules</span>
                            </button>
                        </div>
                    </div>

                    {{-- Collapsible Add Tranche Form (Opens on demand) --}}
                    <div id="add-tranche-form" class="hidden bg-slate-50 p-3.5 rounded-xl border border-indigo-200 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-indigo-950 uppercase">➕ Add Averaging Tranche / Rolled Leg</span>
                            <button type="button" onclick="toggleAddTrancheForm()" class="text-slate-400 hover:text-slate-600 text-xs font-bold">✕ Close</button>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-6 gap-2 text-xs font-mono-num">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Option Type / Leg</label>
                                <select id="new-tranche-type" class="w-full px-2 py-1 border border-slate-300 rounded bg-white text-xs font-bold">
                                    <option value="both">Both (CE + PE Strangle)</option>
                                    <option value="CE">Call Only (CE)</option>
                                    <option value="PE">Put Only (PE)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Time of Entry</label>
                                <input type="time" id="new-tranche-time" value="11:30" class="w-full px-2 py-1 border border-slate-300 rounded bg-white text-xs">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-blue-700 uppercase mb-1">CE Strike &amp; Price</label>
                                <div class="flex gap-1">
                                    <input type="number" step="50" id="new-tranche-ce-strike" placeholder="Strike" class="w-1/2 px-1.5 py-1 border border-blue-300 rounded text-xs font-bold">
                                    <input type="number" step="0.05" id="new-tranche-ce-price" placeholder="Price" class="w-1/2 px-1.5 py-1 border border-blue-300 rounded text-xs font-bold">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-rose-700 uppercase mb-1">PE Strike &amp; Price</label>
                                <div class="flex gap-1">
                                    <input type="number" step="50" id="new-tranche-pe-strike" placeholder="Strike" class="w-1/2 px-1.5 py-1 border border-rose-300 rounded text-xs font-bold">
                                    <input type="number" step="0.05" id="new-tranche-pe-price" placeholder="Price" class="w-1/2 px-1.5 py-1 border border-rose-300 rounded text-xs font-bold">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-indigo-700 uppercase mb-1">Lots (65x Qty)</label>
                                <input type="number" min="1" step="1" id="new-tranche-lots" value="3" class="w-full px-2 py-1 border border-indigo-300 rounded text-xs font-bold">
                            </div>
                            <div class="flex items-end">
                                <button type="button" onclick="submitNewTranche()"
                                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-1 px-3 rounded text-xs shadow-xs cursor-pointer">
                                    Add Tranche
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Multi-Tranche List Table --}}
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs font-mono-num border-collapse">
                            <thead>
                            <tr class="bg-slate-100 text-slate-600 text-[10px] uppercase font-bold border-b border-slate-200">
                                <th class="px-3 py-2 text-left">Tranche #</th>
                                <th class="px-3 py-2 text-left">Time</th>
                                <th class="px-3 py-2 text-left">Call Leg (CE)</th>
                                <th class="px-3 py-2 text-left">Put Leg (PE)</th>
                                <th class="px-3 py-2 text-center">Lots</th>
                                <th class="px-3 py-2 text-center">Qty (65x)</th>
                                <th class="px-3 py-2 text-right">Net P&amp;L</th>
                                <th class="px-3 py-2 text-center">Action</th>
                            </tr>
                            </thead>
                            <tbody id="tranches-table-body" class="divide-y divide-slate-100">
                                {{-- Populated dynamically by JavaScript --}}
                            </tbody>
                            <tfoot id="tranches-table-footer" class="bg-slate-50 font-black text-xs border-t-2 border-slate-300">
                                {{-- Blended Weighted Average Summary Row --}}
                            </tfoot>
                        </table>
                    </div>

                </div>

                {{-- ═══════════════════════════════════════════════════════════════════ --}}
                {{-- 4. COLLAPSIBLE STRATEGY COMMENTARY & 4-RULES GUIDE                  --}}
                {{-- ═══════════════════════════════════════════════════════════════════ --}}
                <div id="rules-guide-wrapper" class="space-y-4">

                    {{-- Strategy Commentary Box (Where We Are Right Now) --}}
                    <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white rounded-xl p-4 shadow-sm border border-slate-800">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2 pb-2 border-b border-slate-800">
                            <div class="flex items-center gap-2">
                                <span class="text-base">📍</span>
                                <h4 class="text-xs sm:text-sm font-black text-white uppercase tracking-wider">
                                    Current Situation &bull; Real-Time Strategy Commentary
                                </h4>
                            </div>
                            <div id="pb-diagnosis-badge" class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-emerald-500 text-white flex items-center gap-1.5 shadow-xs">
                                <span class="h-1.5 w-1.5 rounded-full bg-white animate-ping"></span>
                                <span>SAFE &amp; DECAYING</span>
                            </div>
                        </div>

                        <div class="bg-slate-950/80 rounded-lg p-3 text-xs sm:text-[13px] text-slate-200 leading-relaxed font-sans border border-slate-800">
                            <p id="pb-commentary-text">
                                Analyzing live positions and strategy health...
                            </p>
                        </div>

                        {{-- 4-Rule Quick Gauges --}}
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 mt-3 text-xs font-mono-num">
                            <div class="bg-slate-800/80 p-2 rounded-lg border border-slate-700">
                                <div class="text-[10px] font-bold uppercase text-indigo-300">Rule 1: Staggered Averaging</div>
                                <div class="font-bold text-slate-100 text-[11px]" id="diag-rule1-status">🟢 In Comfort Zone</div>
                            </div>
                            <div class="bg-slate-800/80 p-2 rounded-lg border border-slate-700">
                                <div class="text-[10px] font-bold uppercase text-emerald-300">Rule 2: Roll Winning Leg</div>
                                <div class="font-bold text-slate-100 text-[11px]" id="diag-rule2-status">🟢 Decaying Normally</div>
                            </div>
                            <div class="bg-slate-800/80 p-2 rounded-lg border border-slate-700">
                                <div class="text-[10px] font-bold uppercase text-amber-300">Rule 3: Wing Hedge</div>
                                <div class="font-bold text-amber-200 text-[11px]" id="diag-rule3-status">🛡️ Buy Wings Before 15:15</div>
                            </div>
                            <div class="bg-slate-800/80 p-2 rounded-lg border border-slate-700">
                                <div class="text-[10px] font-bold uppercase text-rose-300">Rule 4: Hard Stop Loss</div>
                                <div class="font-bold text-slate-100 text-[11px]" id="diag-rule4-status">🟢 Safe (&lt; 2.0x SL)</div>
                            </div>
                        </div>
                    </div>

                    {{-- The 4 Rules Visual Grid --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                        {{-- RULE 1 --}}
                        <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-3.5 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-1.5">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black bg-indigo-600 text-white">RULE 01</span>
                                        <span class="text-xs font-bold text-slate-900">Staggered Capital Deployment</span>
                                    </div>
                                    <span class="text-[10px] font-bold text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-200">
                                        Phase: Averaging
                                    </span>
                                </div>
                                <p class="text-xs text-slate-600 mb-2">
                                    <strong>Trigger:</strong> Spot moves &plusmn;150–200 pts. <strong>Action:</strong> Do NOT average same losing strike. Deploy Tranche 2 on a higher/lower OTM strike trading around &#8377;40–&#8377;45.
                                </p>
                            </div>
                            <div class="bg-slate-50 p-2 rounded text-[11px] text-slate-500 font-mono-num">
                                Next Step: Set price alert at Spot &plusmn;175 pts. Elevates breakeven corridor while keeping delta neutral.
                            </div>
                        </div>

                        {{-- RULE 2 --}}
                        <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-3.5 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-1.5">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black bg-emerald-600 text-white">RULE 02</span>
                                        <span class="text-xs font-bold text-slate-900">Roll The Winning Leg</span>
                                    </div>
                                    <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">
                                        Phase: Delta Shift
                                    </span>
                                </div>
                                <p class="text-xs text-slate-600 mb-2">
                                    <strong>Trigger:</strong> Either leg decays &ge;65% (LTP &le; &#8377;15.00). <strong>Action:</strong> Square off decayed leg to lock in ~&#8377;28 profit. Roll strike 200–300 pts closer to spot at &#8377;35–&#8377;40.
                                </p>
                            </div>
                            <div class="bg-slate-50 p-2 rounded text-[11px] text-slate-500 font-mono-num">
                                Next Step: Re-balances net delta to 0 and widens tested side's breakeven cushion by 30+ points.
                            </div>
                        </div>

                        {{-- RULE 3 --}}
                        <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-3.5 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-1.5">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black bg-amber-600 text-white">RULE 03</span>
                                        <span class="text-xs font-bold text-slate-900">Defined Risk Wing Hedge</span>
                                    </div>
                                    <span class="text-[10px] font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">
                                        Phase: Tail Shield
                                    </span>
                                </div>
                                <p class="text-xs text-slate-600 mb-2">
                                    <strong>Trigger:</strong> Before 15:15 IST when holding overnight. <strong>Action:</strong> Buy deep OTM wings (+300 pts above CE, -300 pts below PE) @ &#8377;4.50–&#8377;6.00.
                                </p>
                            </div>
                            <div class="bg-slate-50 p-2 rounded text-[11px] text-slate-500 font-mono-num">
                                Next Step: Converts strangle into an Iron Condor, cutting margin by 50% and capping gap disaster.
                            </div>
                        </div>

                        {{-- RULE 4 --}}
                        <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-3.5 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-1.5">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black bg-rose-600 text-white">RULE 04</span>
                                        <span class="text-xs font-bold text-slate-900">Hard Stop Loss (Fire Exit)</span>
                                    </div>
                                    <span class="text-[10px] font-bold text-rose-700 bg-rose-50 px-1.5 py-0.5 rounded border border-rose-200">
                                        Phase: Risk Exit
                                    </span>
                                </div>
                                <p class="text-xs text-slate-600 mb-2">
                                    <strong>Trigger:</strong> Any leg reaches 2.0&times; Entry Price. <strong>Action:</strong> Exit the losing leg immediately at market order without emotion. Leave the winning leg open.
                                </p>
                            </div>
                            <div class="bg-slate-50 p-2 rounded text-[11px] text-slate-500 font-mono-num">
                                Next Step: Keep SL-M orders active in broker terminal. Total portfolio loss remains strictly &le; 1.5%.
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
        lotSize: 65, // NIFTY LOT SIZE IS 65!

        // Multi-Tranche Array
        tranches: [
            {
                id: 1,
                name: 'Tranche 1 (Initial)',
                time: '09:30',
                ceStrike: {{ (int)($defaultPair['ce_strike'] ?? 23000) }},
                cePrice: {{ (float)($defaultPair['ce_price'] ?? 43.65) }},
                peStrike: {{ (int)($defaultPair['pe_strike'] ?? 22000) }},
                pePrice: {{ (float)($defaultPair['pe_price'] ?? 42.95) }},
                lots: 4
            }
        ],

        tickCount: 0,
        ws: null,
        protobufRoot: null,
    };

    // Show / Hide Rules Guide Toggle (Retained in localStorage!)
    window.toggleRulesGuide = function() {
        const wrapper = document.getElementById('rules-guide-wrapper');
        const textEl = document.getElementById('toggle-rules-text');
        if (!wrapper) return;

        const isCurrentlyHidden = wrapper.classList.contains('hidden');
        if (isCurrentlyHidden) {
            wrapper.classList.remove('hidden');
            localStorage.setItem('strike_matcher_show_rules', 'true');
            if (textEl) textEl.textContent = 'Hide Strategy Rules';
        } else {
            wrapper.classList.add('hidden');
            localStorage.setItem('strike_matcher_show_rules', 'false');
            if (textEl) textEl.textContent = 'Show Strategy Rules';
        }
    };

    function applyShowHidePreference() {
        const pref = localStorage.getItem('strike_matcher_show_rules');
        const wrapper = document.getElementById('rules-guide-wrapper');
        const textEl = document.getElementById('toggle-rules-text');

        if (pref === 'false') {
            if (wrapper) wrapper.classList.add('hidden');
            if (textEl) textEl.textContent = 'Show Strategy Rules';
        } else {
            if (wrapper) wrapper.classList.remove('hidden');
            if (textEl) textEl.textContent = 'Hide Strategy Rules';
        }
    }

    // Toggle Add Tranche Form
    window.toggleAddTrancheForm = function() {
        const form = document.getElementById('add-tranche-form');
        if (!form) return;
        form.classList.toggle('hidden');

        // Pre-fill defaults based on current state
        if (!form.classList.contains('hidden')) {
            const ceStr = document.getElementById('new-tranche-ce-strike');
            const cePr = document.getElementById('new-tranche-ce-price');
            const peStr = document.getElementById('new-tranche-pe-strike');
            const pePr = document.getElementById('new-tranche-pe-price');

            if (ceStr && !ceStr.value) ceStr.value = (state.tranches[0]?.ceStrike || 23000) + 150;
            if (cePr && !cePr.value) cePr.value = 42.00;
            if (peStr && !peStr.value) peStr.value = (state.tranches[0]?.peStrike || 22000) - 150;
            if (pePr && !pePr.value) pePr.value = 42.00;
        }
    };

    // Submit New Averaging Tranche
    window.submitNewTranche = function() {
        const typeEl = document.getElementById('new-tranche-type');
        const timeEl = document.getElementById('new-tranche-time');
        const ceStrEl = document.getElementById('new-tranche-ce-strike');
        const cePrEl = document.getElementById('new-tranche-ce-price');
        const peStrEl = document.getElementById('new-tranche-pe-strike');
        const pePrEl = document.getElementById('new-tranche-pe-price');
        const lotsEl = document.getElementById('new-tranche-lots');

        const optType = typeEl ? typeEl.value : 'both';
        const entryTime = timeEl ? timeEl.value : '11:30';
        const lots = parseInt(lotsEl ? lotsEl.value : 3) || 3;

        const ceStrike = (optType === 'both' || optType === 'CE') ? (parseFloat(ceStrEl?.value) || null) : null;
        const cePrice = (optType === 'both' || optType === 'CE') ? (parseFloat(cePrEl?.value) || 0) : null;
        const peStrike = (optType === 'both' || optType === 'PE') ? (parseFloat(peStrEl?.value) || null) : null;
        const pePrice = (optType === 'both' || optType === 'PE') ? (parseFloat(pePrEl?.value) || 0) : null;

        const trancheNum = state.tranches.length + 1;
        state.tranches.push({
            id: Date.now(),
            name: `Tranche ${trancheNum} (Average)`,
            time: entryTime,
            ceStrike: ceStrike,
            cePrice: cePrice,
            peStrike: peStrike,
            pePrice: pePrice,
            lots: lots
        });

        saveToLocalStorage();
        renderTranchesTable();
        toggleAddTrancheForm();
    };

    // Delete Tranche
    window.deleteTranche = function(id) {
        if (state.tranches.length <= 1) {
            alert('Cannot delete the initial primary tranche. Use Reset to start fresh.');
            return;
        }
        state.tranches = state.tranches.filter(t => t.id !== id);
        saveToLocalStorage();
        renderTranchesTable();
    };

    // Local Storage Persistence
    function saveToLocalStorage() {
        try {
            const dataToSave = {
                tranches: state.tranches,
                currentPair: state.currentPair,
                currentCol: state.currentCol,
                initialSpot: state.initialSpot,
                timestamp: Date.now()
            };
            localStorage.setItem('strike_matcher_tranches_data', JSON.stringify(dataToSave));
            updateStorageStatusBadge(true);
        } catch (e) {
            console.warn('localStorage save failed:', e);
        }
    }

    function loadFromLocalStorage() {
        try {
            const raw = localStorage.getItem('strike_matcher_tranches_data');
            if (!raw) {
                updateStorageStatusBadge(false);
                return false;
            }
            const data = JSON.parse(raw);
            if (!data || !Array.isArray(data.tranches) || data.tranches.length === 0) return false;

            state.tranches = data.tranches;
            if (data.currentPair) state.currentPair = data.currentPair;
            if (data.currentCol) state.currentCol = data.currentCol;
            if (data.initialSpot) state.initialSpot = data.initialSpot;

            updateStorageStatusBadge(true);
            return true;
        } catch (e) {
            console.warn('localStorage load failed:', e);
            return false;
        }
    }

    window.clearSavedTrade = function() {
        try {
            localStorage.removeItem('strike_matcher_tranches_data');
            window.location.reload();
        } catch (e) {
            window.location.reload();
        }
    };

    function updateStorageStatusBadge(saved) {
        const badge = document.getElementById('storage-status-badge');
        if (badge) {
            if (saved) {
                badge.className = 'px-2 py-1 rounded-md text-[11px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-300 flex items-center gap-1 shadow-xs transition';
                badge.innerHTML = '<span class="h-1.5 w-1.5 rounded-full bg-emerald-600 animate-pulse"></span><span>💾 Saved in Browser</span>';
            } else {
                badge.className = 'px-2 py-1 rounded-md text-[11px] font-bold bg-slate-100 text-slate-600 border border-slate-300 flex items-center gap-1 shadow-xs transition';
                badge.innerHTML = '<span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span><span>Default (Not Saved)</span>';
            }
        }
    }

    // Sync from Table Selection
    window.syncFromCurrentPair = function() {
        if (!state.currentPair) return;
        const p = state.currentPair;

        state.tranches = [
            {
                id: 1,
                name: 'Tranche 1 (Initial)',
                time: '09:30',
                ceStrike: parseFloat(p.ce_strike),
                cePrice: parseFloat(p.ce_price),
                peStrike: parseFloat(p.pe_strike),
                pePrice: parseFloat(p.pe_price),
                lots: 4
            }
        ];

        saveToLocalStorage();
        renderTranchesTable();
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

        state.tranches = [
            {
                id: 1,
                name: 'Tranche 1 (Initial)',
                time: '09:30',
                ceStrike: parseFloat(pair.ce_strike),
                cePrice: parseFloat(pair.ce_price),
                peStrike: parseFloat(pair.pe_strike),
                pePrice: parseFloat(pair.pe_price),
                lots: 4
            }
        ];

        document.querySelectorAll('.pair-row').forEach(r => r.classList.remove('ring-2', 'ring-indigo-500', 'bg-indigo-50/50'));

        saveToLocalStorage();
        renderTranchesTable();
        subscribeLiveKeys();

        const pbEl = document.getElementById('playbook-section');
        if (pbEl) pbEl.scrollIntoView({ behavior: 'smooth' });
    };

    // Render Tranches Table & Weighted Average Row
    function renderTranchesTable() {
        const tbody = document.getElementById('tranches-table-body');
        const tfoot = document.getElementById('tranches-table-footer');
        if (!tbody || !tfoot) return;

        tbody.innerHTML = '';

        let totalCeLots = 0;
        let totalPeLots = 0;
        let weightedCeSum = 0;
        let weightedPeSum = 0;
        let totalNetPnl = 0;
        let totalLotsCount = 0;

        state.tranches.forEach((tr, index) => {
            const hasCe = tr.ceStrike !== null && tr.cePrice !== null;
            const hasPe = tr.peStrike !== null && tr.pePrice !== null;

            if (hasCe) {
                totalCeLots += tr.lots;
                weightedCeSum += (tr.cePrice * tr.lots);
            }
            if (hasPe) {
                totalPeLots += tr.lots;
                weightedPeSum += (tr.pePrice * tr.lots);
            }
            totalLotsCount += tr.lots;

            const trancheQty = tr.lots * state.lotSize; // 65x

            // Tranche P&L
            let tranchePnl = 0;
            if (hasCe) tranchePnl += (tr.cePrice - state.ceLtp) * trancheQty;
            if (hasPe) tranchePnl += (tr.pePrice - state.peLtp) * trancheQty;
            totalNetPnl += tranchePnl;

            const trRow = document.createElement('tr');
            trRow.className = 'hover:bg-slate-50 transition-colors';
            trRow.innerHTML = `
                <td class="px-3 py-2 text-slate-800 font-bold">
                    <span class="inline-block w-2 h-2 rounded-full ${index === 0 ? 'bg-indigo-600' : 'bg-amber-500'} mr-1"></span>
                    ${tr.name}
                </td>
                <td class="px-3 py-2 text-slate-500">${tr.time}</td>
                <td class="px-3 py-2 text-blue-900 font-bold">
                    ${hasCe ? `${Math.round(tr.ceStrike)} CE @ ₹${tr.cePrice.toFixed(2)}` : '<span class="text-slate-400">—</span>'}
                </td>
                <td class="px-3 py-2 text-rose-900 font-bold">
                    ${hasPe ? `${Math.round(tr.peStrike)} PE @ ₹${tr.pePrice.toFixed(2)}` : '<span class="text-slate-400">—</span>'}
                </td>
                <td class="px-3 py-2 text-center font-bold text-slate-900">${tr.lots}</td>
                <td class="px-3 py-2 text-center text-slate-600">${trancheQty}</td>
                <td class="px-3 py-2 text-right font-black ${tranchePnl >= 0 ? 'text-emerald-600' : 'text-rose-600'}">
                    ${tranchePnl >= 0 ? '+₹' : '-₹'}${Math.abs(Math.round(tranchePnl)).toLocaleString('en-IN')}
                </td>
                <td class="px-3 py-2 text-center">
                    ${index > 0 ? `<button type="button" onclick="deleteTranche(${tr.id})" class="text-rose-500 hover:text-rose-700 font-bold px-1.5 py-0.5 rounded cursor-pointer" title="Delete Tranche">✕</button>` : '<span class="text-slate-300 text-[10px]">Base</span>'}
                </td>
            `;
            tbody.appendChild(trRow);
        });

        // Blended Summary Calculations
        const weightedCePrice = totalCeLots > 0 ? (weightedCeSum / totalCeLots) : 0;
        const weightedPePrice = totalPeLots > 0 ? (weightedPeSum / totalPeLots) : 0;
        const totalQtyAll = totalLotsCount * state.lotSize;

        tfoot.innerHTML = `
            <tr>
                <td class="px-3 py-2.5 text-indigo-950 font-black">
                    WEIGHTED TOTAL
                </td>
                <td class="px-3 py-2.5 text-slate-500 font-bold">${state.tranches.length} Tranche(s)</td>
                <td class="px-3 py-2.5 text-blue-950 font-black">
                    ${totalCeLots > 0 ? `Avg: ₹${weightedCePrice.toFixed(2)} (${totalCeLots}L)` : '—'}
                </td>
                <td class="px-3 py-2.5 text-rose-950 font-black">
                    ${totalPeLots > 0 ? `Avg: ₹${weightedPePrice.toFixed(2)} (${totalPeLots}L)` : '—'}
                </td>
                <td class="px-3 py-2.5 text-center text-indigo-950 font-black">${totalLotsCount} Lots</td>
                <td class="px-3 py-2.5 text-center text-slate-800 font-black">${totalQtyAll} Qty</td>
                <td class="px-3 py-2.5 text-right font-black text-sm ${totalNetPnl >= 0 ? 'text-emerald-700' : 'text-rose-700'}">
                    ${totalNetPnl >= 0 ? '+₹' : '-₹'}${Math.abs(Math.round(totalNetPnl)).toLocaleString('en-IN')}
                </td>
                <td class="px-3 py-2.5 text-center text-[10px] text-slate-400">Total</td>
            </tr>
        `;

        updateCommentary(totalNetPnl, totalLotsCount, totalQtyAll, weightedCePrice, weightedPePrice);
    }

    // Dynamic Strategy Commentary
    function updateCommentary(totalNetPnl, totalLotsCount, totalQtyAll, weightedCePrice, weightedPePrice) {
        const commentaryEl = document.getElementById('pb-commentary-text');
        const badgeEl = document.getElementById('pb-diagnosis-badge');
        if (!commentaryEl) return;

        const spot = state.spotPrice;
        const initSpot = state.initialSpot;
        const spotDiff = spot - initSpot;
        const moveSign = spotDiff >= 0 ? '+' : '';
        const pnlSign = totalNetPnl >= 0 ? '+' : '';

        const dR1 = document.getElementById('diag-rule1-status');
        const dR2 = document.getElementById('diag-rule2-status');
        const dR3 = document.getElementById('diag-rule3-status');
        const dR4 = document.getElementById('diag-rule4-status');

        const isAveragingHit = Math.abs(spotDiff) >= 175;
        const isRollHit = (state.peLtp <= 15.0 || state.ceLtp <= 15.0);
        const ceSl = weightedCePrice * 2.0;
        const peSl = weightedPePrice * 2.0;
        const isSlHit = (state.ceLtp >= ceSl || state.peLtp >= peSl);

        if (dR1) dR1.textContent = isAveragingHit ? "🟡 Triggered (Deploy Tranche 2)" : "🟢 In Comfort Zone";
        if (dR2) dR2.textContent = isRollHit ? (state.peLtp <= 15 ? "🎯 Roll PE (≤ ₹15)" : "🎯 Roll CE (≤ ₹15)") : "🟢 Decaying Normally";
        if (dR3) dR3.textContent = "🛡️ Buy Wings Before 15:15";
        if (dR4) dR4.textContent = isSlHit ? "🔴 STOP LOSS HIT" : "🟢 Safe (< 2.0x SL)";

        if (isSlHit) {
            if (badgeEl) {
                badgeEl.className = "px-2.5 py-0.5 rounded-full text-[11px] font-black bg-rose-600 text-white flex items-center gap-1.5 shadow-xs animate-pulse";
                badgeEl.innerHTML = '<span class="h-1.5 w-1.5 rounded-full bg-white animate-ping"></span><span>STOP LOSS HIT</span>';
            }
            commentaryEl.innerHTML = `<span class="text-rose-300 font-bold">🚨 Rule 04 Fire Exit Triggered:</span> One of your active legs has crossed its 2.0x risk limit. Net P&L across all tranches is <strong class="${totalNetPnl >= 0 ? 'text-emerald-400' : 'text-rose-400'}">${pnlSign}₹${Math.round(totalNetPnl).toLocaleString('en-IN')}</strong>. <strong>Next Step:</strong> Exit the losing leg at market order immediately to contain drawdown.`;
        } else if (isRollHit) {
            if (badgeEl) {
                badgeEl.className = "px-2.5 py-0.5 rounded-full text-[11px] font-black bg-emerald-600 text-white flex items-center gap-1.5 shadow-xs animate-pulse";
                badgeEl.innerHTML = '<span class="h-1.5 w-1.5 rounded-full bg-white animate-ping"></span><span>ROLL TRIGGER HIT</span>';
            }
            const leg = state.peLtp <= 15 ? "Put leg (PE)" : "Call leg (CE)";
            commentaryEl.innerHTML = `<span class="text-emerald-300 font-bold">🎯 Rule 02 Theta Harvesting Triggered:</span> Your ${leg} has melted below ₹15.00! Total Net P&L is <strong class="text-emerald-400">+₹${Math.round(totalNetPnl).toLocaleString('en-IN')}</strong> on ${totalLotsCount} Lots (${totalQtyAll} Qty). <strong>Next Step:</strong> Square off the decayed leg to lock in profit, then roll 200–300 pts closer to spot at ₹35–₹40.`;
        } else if (isAveragingHit) {
            if (badgeEl) {
                badgeEl.className = "px-2.5 py-0.5 rounded-full text-[11px] font-black bg-amber-500 text-slate-900 flex items-center gap-1.5 shadow-xs animate-pulse";
                badgeEl.innerHTML = '<span class="h-1.5 w-1.5 rounded-full bg-slate-900 animate-ping"></span><span>MARKET EXPANSION</span>';
            }
            commentaryEl.innerHTML = `<span class="text-amber-300 font-bold">⚡ Rule 01 Market Expansion:</span> Spot moved ${moveSign}${spotDiff.toFixed(1)} pts to ₹${spot.toLocaleString('en-IN', {minimumFractionDigits:2})}. Total P&L is <strong class="${totalNetPnl >= 0 ? 'text-emerald-400' : 'text-rose-400'}">${pnlSign}₹${Math.round(totalNetPnl).toLocaleString('en-IN')}</strong>. <strong>Next Step:</strong> Click '➕ Add Average Tranche' to sell a higher/lower OTM strike at ₹40–₹45 without averaging the same challenged strike.`;
        } else {
            if (badgeEl) {
                badgeEl.className = "px-2.5 py-0.5 rounded-full text-[11px] font-black bg-emerald-500 text-white flex items-center gap-1.5 shadow-xs";
                badgeEl.innerHTML = '<span class="h-1.5 w-1.5 rounded-full bg-white animate-ping"></span><span>SAFE &amp; DECAYING</span>';
            }
            commentaryEl.innerHTML = `<span>Spot is at <strong>₹${spot.toLocaleString('en-IN', {minimumFractionDigits:2})}</strong> (${moveSign}${spotDiff.toFixed(1)} pts from entry). Both legs are decaying peacefully. Total Net P&L is <strong class="${totalNetPnl >= 0 ? 'text-emerald-400' : 'text-rose-400'}">${pnlSign}₹${Math.round(totalNetPnl).toLocaleString('en-IN')}</strong> across ${state.tranches.length} tranche(s) (${totalLotsCount} Lots / ${totalQtyAll} Qty). <strong>Next Step:</strong> No defense action needed. Hold and harvest theta decay.</span>`;
        }
    }

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
                subscribeLiveKeys();
            };

            state.ws.onmessage = (event) => {
                decodeFeed(event.data);
            };
        } catch (e) {
            // fallback
        }
    }

    function decodeFeed(buffer) {
        if (!state.protobufRoot) return;
        try {
            const arr = new Uint8Array(buffer);
            if (arr.length > 0 && arr[0] === 123) return;
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
                    renderTranchesTable();
                }
            }
        } catch (err) {}
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', () => {
        loadFromLocalStorage();
        applyShowHidePreference();
        renderTranchesTable();
        initWebSocket();
    });
})();
</script>
@endpush
