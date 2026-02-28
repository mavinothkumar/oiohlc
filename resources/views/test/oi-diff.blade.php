@extends('layouts.app')

@section('title', 'NIFTY OI & Volume Difference')

@section('content')
    <div class="min-h-screen bg-slate-50 text-slate-800 p-4 md:p-6">

        {{-- ── PAGE HEADER ── --}}
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-slate-800">
                    NIFTY — OI &amp; Volume Diff
                    <span class="ml-2 text-sm font-normal text-slate-400">(3-minute candles)</span>
                </h1>
                <p class="text-slate-400 text-xs mt-0.5">
                    Track open-interest and volume changes across 6 strike prices.
                    Top-3 positive <span class="inline-block w-2.5 h-2.5 rounded-sm bg-emerald-500 align-middle"></span>
                    and negative <span class="inline-block w-2.5 h-2.5 rounded-sm bg-red-500 align-middle"></span> diffs are highlighted.
                </p>
            </div>
            <div class="flex items-center gap-4 text-xs text-slate-400">
                <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-emerald-500"></span> Top-3 OI +</span>
                <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-red-500"></span> Top-3 OI −</span>
                <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-sky-500"></span> Top-3 Vol +</span>
                <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-orange-500"></span> Top-3 Vol −</span>
            </div>
        </div>

        {{-- ── FILTER BAR ── --}}
        <form method="GET" action="{{ route('test.oi.diff') }}" id="filterForm"
            class="bg-white border border-slate-200 rounded-xl shadow-sm mb-4">

            {{-- Always-visible single row --}}
            <div class="flex flex-wrap items-end gap-3 px-4 py-3">

                {{-- Date --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Date</label>
                    <input type="date" name="date" id="dateInput"
                        value="{{ $date ?? '' }}"
                        class="border border-slate-300 rounded-lg px-3 py-2 text-sm text-slate-800
                              focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent
                              bg-white cursor-pointer w-36"/>
                </div>

                {{-- Expiry --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Expiry</label>
                    <select name="expiry" id="expirySelect"
                        class="border border-slate-300 rounded-lg px-3 py-2 text-sm text-slate-800
                               focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent
                               bg-white w-36">
                        <option value="">— auto —</option>
                        @if($expiry)
                            <option value="{{ $expiry }}" selected>{{ $expiry }}</option>
                        @endif
                    </select>
                </div>

                {{-- Toggle strikes --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider opacity-0">·</label>
                    <button type="button" id="toggleStrikes"
                        class="border border-slate-300 rounded-lg px-3 py-2 text-sm text-slate-600
                               bg-slate-50 hover:bg-slate-100 transition-colors duration-150 whitespace-nowrap">
                        ⚙ Strikes
                        <span id="strikeCount" class="ml-1 text-xs text-indigo-500 font-semibold"></span>
                    </button>
                </div>

                {{-- Load Table --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider opacity-0">·</label>
                    <button type="submit" id="load-table"
                        class="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm
                               px-5 py-2 rounded-lg transition-colors duration-150 shadow whitespace-nowrap">
                        Load Table
                    </button>
                </div>

                {{-- Reset --}}
                <div class="flex flex-col gap-1 ml-auto">
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider opacity-0">·</label>
                    <a href="{{ route('test.oi.diff') }}"
                        class="text-slate-400 hover:text-slate-600 text-sm transition-colors duration-150 py-2">
                        Reset
                    </a>
                </div>
            </div>

            {{-- Collapsible strikes panel --}}
            <div id="strikesPanel" class="hidden border-t border-slate-100 px-4 py-3 bg-slate-50 rounded-b-xl">
                <div class="flex flex-wrap items-center gap-3">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider whitespace-nowrap">
                    Strikes
                </span>
                    <div class="grid grid-cols-6 gap-2">
                        @for($i = 0; $i < 6; $i++)
                            <input type="number" name="strikes[]"
                                value="{{ $strikes[$i] ?? '' }}"
                                placeholder="{{ $i + 1 }}"
                                class="strike-input border border-slate-300 rounded-lg px-2 py-1.5 text-sm
                                  text-slate-800 text-center font-mono bg-white w-24
                                  focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent
                                  placeholder-slate-300"/>
                        @endfor
                    </div>
                </div>

                {{-- Suggestion pills --}}
                <div id="strikeSuggestions" class="hidden mt-2 flex flex-wrap gap-1.5">
                    <span class="text-xs text-slate-400">Quick fill:</span>
                </div>
            </div>
        </form>

        {{-- ── TABLE ── --}}
        @if(! empty($timestamps) && ! empty($allStrikes))

            <div class="overflow-x-auto overflow-y-auto max-h-[78vh] rounded-xl border border-slate-200 shadow-sm">
                <table class="min-w-max w-full text-xs border-collapse">

                    {{-- ===== THEAD ===== --}}
                    <thead>
                    {{-- Row 1: Strike numbers --}}
                    <tr class="sticky top-0 z-30 bg-slate-700 border-b border-slate-600">
                        <th class="sticky left-0 z-40 bg-slate-700 px-3 py-2.5 text-left text-slate-200
                               font-semibold uppercase tracking-widest whitespace-nowrap min-w-[90px]"
                            rowspan="2">
                            Time
                        </th>
                        @foreach($allStrikes as $strike)
                            <th colspan="6"
                                class="px-2 py-2 text-center font-bold text-white tracking-widest
                               border-l border-slate-600 bg-slate-700">
                                {{ number_format((int)$strike) }}
                            </th>
                        @endforeach
                    </tr>

                    {{-- Row 2: CE / PE --}}
                    <tr class="sticky z-30 bg-slate-600 border-b-2 border-slate-500">
                        @foreach($allStrikes as $strike)
                            @foreach(['CE','PE'] as $type)
                                <th colspan="3"
                                    class="px-2 py-1.5 text-center font-bold border-l border-slate-500 whitespace-nowrap bg-slate-600
                                   {{ $type === 'CE' ? 'text-sky-300' : 'text-rose-300' }}">
                                    {{ $type }}
                                </th>
                            @endforeach
                        @endforeach
                    </tr>

                    {{-- Row 3: Column labels --}}
                    <tr class="sticky top-[73px] z-30 bg-slate-100 border-b border-slate-300 text-slate-500">
                        @foreach($allStrikes as $_)
                            @foreach(['CE','PE'] as $__)
                                <th class="px-2 py-1.5 text-center border-l border-slate-200 whitespace-nowrap
                                   font-semibold bg-slate-100 text-slate-500">Close Δ</th>
                                <th class="px-2 py-1.5 text-center border-l border-slate-200 whitespace-nowrap
                                   font-semibold bg-slate-100 text-slate-500">OI Δ</th>
                                <th class="px-2 py-1.5 text-center border-l border-slate-200 whitespace-nowrap
                                   font-semibold bg-slate-100 text-slate-500">Vol Δ</th>
                            @endforeach
                        @endforeach
                    </tr>
                    </thead>

                    {{-- ===== TBODY ===== --}}
                    <tbody class="divide-y divide-slate-100">
                    @foreach($timestamps as $rowIdx => $ts)
                        @php
                            $rowBg = $rowIdx % 2 === 0 ? 'bg-white' : 'bg-slate-50';
                        @endphp
                        <tr class="{{ $rowBg }} hover:bg-indigo-50 transition-colors duration-75">

                            {{-- Time --}}
                            <td class="sticky left-0 z-10 {{ $rowBg }} px-3 py-2 font-mono font-semibold
                               text-slate-600 whitespace-nowrap border-r border-slate-200 text-xs">
                                {{ \Carbon\Carbon::parse($ts)->format('H:i') }}
                            </td>

                            @foreach($allStrikes as $strike)
                                @foreach(['CE','PE'] as $type)
                                    @php
                                        $cell    = $tableData[$ts][$strike][$type] ?? [];
                                        $cellKey = "{$ts}|{$strike}|{$type}";

                                        // Close diff
                                        $closeDiff  = $cell['close_diff'] ?? null;
                                        $closeClass = $closeDiff === null ? 'text-slate-300'
                                            : ($closeDiff > 0 ? 'text-emerald-600 font-semibold'
                                            : ($closeDiff < 0 ? 'text-red-500 font-semibold' : 'text-slate-400'));

                                        // OI diff
                                        $oiDiff = $cell['oi_diff'] ?? null;
                                        if (isset($highlight['oi_pos'][$cellKey]))
                                            $oiBg = 'bg-emerald-100 text-emerald-700 font-bold ring-1 ring-emerald-400';
                                        elseif (isset($highlight['oi_neg'][$cellKey]))
                                            $oiBg = 'bg-red-100 text-red-700 font-bold ring-1 ring-red-400';
                                        else
                                            $oiBg = $oiDiff === null ? 'text-slate-300'
                                                : ($oiDiff > 0 ? 'text-emerald-600' : ($oiDiff < 0 ? 'text-red-500' : 'text-slate-400'));

                                        // Vol diff
                                        $volDiff = $cell['vol_diff'] ?? null;
                                        if (isset($highlight['vol_pos'][$cellKey]))
                                            $volBg = 'bg-sky-100 text-sky-700 font-bold ring-1 ring-sky-400';
                                        elseif (isset($highlight['vol_neg'][$cellKey]))
                                            $volBg = 'bg-orange-100 text-orange-700 font-bold ring-1 ring-orange-400';
                                        else
                                            $volBg = $volDiff === null ? 'text-slate-300'
                                                : ($volDiff > 0 ? 'text-sky-600' : ($volDiff < 0 ? 'text-orange-500' : 'text-slate-400'));
                                    @endphp

                                    {{-- Close Δ --}}
                                    <td class="px-2 py-2 text-center border-l border-slate-100 whitespace-nowrap
                                   tabular-nums {{ $closeClass }}">
                                        @if($closeDiff !== null)
                                            {{ $closeDiff > 0 ? '+' : '' }}{{ number_format($closeDiff, 2) }}
                                        @else
                                            <span class="text-slate-200">—</span>
                                        @endif
                                    </td>

                                    {{-- OI Δ --}}
                                    <td class="px-1.5 py-1.5 text-center border-l border-slate-100 whitespace-nowrap tabular-nums">
                            <span class="inline-block px-1.5 py-0.5 rounded text-xs {{ $oiBg }}">
                                @if($oiDiff !== null)
                                    {{ $oiDiff > 0 ? '+' : '' }}{{ number_format($oiDiff) }}
                                @else
                                    <span class="text-slate-200">—</span>
                                @endif
                            </span>
                                    </td>

                                    {{-- Vol Δ --}}
                                    <td class="px-1.5 py-1.5 text-center border-l border-slate-100 whitespace-nowrap tabular-nums">
                            <span class="inline-block px-1.5 py-0.5 rounded text-xs {{ $volBg }}">
                                @if($volDiff !== null)
                                    {{ $volDiff > 0 ? '+' : '' }}{{ number_format($volDiff) }}
                                @else
                                    <span class="text-slate-200">—</span>
                                @endif
                            </span>
                                    </td>

                                @endforeach
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

        @elseif(request()->has('date'))
            <div class="bg-white border border-slate-200 rounded-xl p-10 text-center shadow-sm">
                <p class="text-slate-400 text-sm">No data found for the selected filters. Try a different date, expiry, or strikes.</p>
            </div>
        @else
            <div class="bg-white border border-slate-200 rounded-xl p-10 text-center shadow-sm">
                <div class="text-4xl mb-3">📊</div>
                <p class="text-slate-500 font-medium">Select a date to load the table</p>
                <p class="text-slate-400 text-xs mt-1">Expiry and strikes will be auto-populated.</p>
            </div>
        @endif

    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const dateInput    = document.getElementById('dateInput');
            const expirySelect = document.getElementById('expirySelect');
            const strikeInputs = document.querySelectorAll('.strike-input');
            const suggestions  = document.getElementById('strikeSuggestions');
            const toggleBtn    = document.getElementById('toggleStrikes');
            const strikesPanel = document.getElementById('strikesPanel');
            const strikeCount  = document.getElementById('strikeCount');

            // ── Helper: build URL with query params ──────────────────────────────────
            function buildUrl(base, params) {
                const url = new URL(base, window.location.origin);
                Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
                return url.toString();
            }

            // ── Strike count badge ────────────────────────────────────────────────────
            function updateStrikeCount() {
                const filled = Array.from(strikeInputs).filter(i => i.value.trim() !== '').length;
                strikeCount.textContent = filled ? `(${filled})` : '';
            }
            strikeInputs.forEach(input => input.addEventListener('input', updateStrikeCount));
            updateStrikeCount();

            // ── Toggle strikes panel ──────────────────────────────────────────────────
            toggleBtn.addEventListener('click', function () {
                strikesPanel.classList.toggle('hidden');
            });

            // ── On date change → fetch expiry + strikes + ATM in one call ────────────
            dateInput.addEventListener('change', function () {
                const date = this.value;
                if (!date) return;

                expirySelect.innerHTML = '<option value="">Loading…</option>';
                suggestions.classList.add('hidden');
                suggestions.querySelectorAll('button.strike-pill').forEach(el => el.remove());
                strikeInputs.forEach(input => input.value = '');
                updateStrikeCount();

                fetch(buildUrl("{{ route('test.oi.diff.expiries') }}", { date }))
                    .then(res => res.json())
                    .then(function (data) {
                        expirySelect.innerHTML = '';

                        if (!data.expiry) {
                            expirySelect.innerHTML = '<option value="">— no expiry found —</option>';
                            return;
                        }

                        // Populate single expiry and auto-select
                        const option = document.createElement('option');
                        option.value       = data.expiry;
                        option.textContent = data.expiry;
                        option.selected    = true;
                        expirySelect.appendChild(option);

                        const strikes = (data.strikes || []).map(s => String(Math.round(Number(s))));
                        const atm     = data.atm ? String(Math.round(Number(data.atm))) : null;

                        // Auto-fill 6 boxes around ATM
                        if (atm && strikes.length) {
                            const atmIdx = strikes.indexOf(atm);
                            console.log('atmIdx after round:', atmIdx); // should now be >= 0

                            const start = atmIdx >= 0 ? Math.max(0, atmIdx - 2) : 0;
                            const slice = strikes.slice(start, start + 6);
                            strikeInputs.forEach(function (input, i) {
                                input.value = slice[i] !== undefined ? slice[i] : '';
                            });
                            updateStrikeCount();
                        }

                        // Suggestion pills
                        strikes.slice(0, 40).forEach(function (s) {
                            const pill     = document.createElement('button');
                            pill.type      = 'button';
                            pill.className = 'strike-pill bg-white border border-slate-300 hover:border-indigo-400 ' +
                                'hover:bg-indigo-50 text-slate-600 hover:text-indigo-700 ' +
                                'text-xs px-2 py-1 rounded transition-colors duration-100 font-mono';
                            pill.textContent = s;
                            pill.addEventListener('click', function () {
                                for (const input of strikeInputs) {
                                    if (!input.value) {
                                        input.value = s;
                                        updateStrikeCount();
                                        break;
                                    }
                                }
                            });
                            suggestions.appendChild(pill);
                        });
                        suggestions.classList.remove('hidden');

                        // Auto-submit
                        setTimeout(function () {
                            document.getElementById('load-table').click();
                        }, 50);
                    })
                    .catch(function () {
                        expirySelect.innerHTML = '<option value="">Error loading expiry</option>';
                    });
            });
        });
    </script>
@endsection




