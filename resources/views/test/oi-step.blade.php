@extends('layouts.app')
@section('title', 'NIFTY OI Step Reader')

@section('content')
    <div class="max-w-full mx-auto px-4 py-6">

        {{-- ── Page header ── --}}
        <div class="mb-4">
            <h1 class="text-xl font-bold text-gray-800 dark:text-gray-100">NIFTY OI Step Reader</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Step through each {{ $interval }} candle one at a time. Use Next / Previous to navigate.
            </p>
        </div>

        {{-- ── Filter form ── --}}
        <form id="filterForm" class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 mb-6 flex flex-wrap gap-3 items-end">
            @csrf

            {{-- Date --}}
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Date</label>
                <input type="date" id="dateInput" name="date" value="{{ $date }}"
                    class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>

            {{-- Expiry (auto-filled) --}}
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Expiry</label>
                <input type="text" id="expiryInput" name="expiry" value="{{ $expiry }}" readonly
                    class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 w-32" />
            </div>

            {{-- 6 Strike inputs --}}
            @for($i = 0; $i < 6; $i++)
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Strike {{ $i+1 }}</label>
                    <input type="number" name="strikes[]" value="{{ $strikes[$i] ?? '' }}" step="50"
                        class="strike-input border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 w-28 focus:ring-2 focus:ring-blue-500 outline-none" />
                </div>
            @endfor

            {{-- Load button --}}
            <button type="button" id="loadBtn"
                class="self-end px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition">
                Load
            </button>
        </form>

        {{-- ── Status bar ── --}}
        <div id="statusBar" class="hidden mb-4 flex items-center gap-3 text-sm">
            <span class="text-gray-500 dark:text-gray-400">Showing candle</span>
            <span id="slotCounter" class="font-bold text-blue-600 dark:text-blue-400">—</span>
            <span class="text-gray-500 dark:text-gray-400">of</span>
            <span id="totalSlots" class="font-semibold text-gray-700 dark:text-gray-300">—</span>
        </div>

        {{-- ── Table container — rows are appended here ── --}}
        <div id="tableWrapper" class="overflow-x-auto hidden">
            <table class="min-w-full text-xs border-collapse">
                <thead id="tableHead" class="sticky top-0 z-10 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200"></thead>
                <tbody id="tableBody"></tbody>
            </table>
        </div>

        {{-- ── Empty state ── --}}
        <div id="emptyState" class="hidden text-center py-16 text-gray-400 dark:text-gray-500 text-sm">
            No data found for this slot. Try adjusting your filters.
        </div>

    </div>

    {{-- ── Floating action buttons (top-right) ── --}}
    <div id="fab" class="hidden fixed top-4 right-4 z-50 flex flex-col gap-2">

        {{-- Reset --}}
        <button id="resetBtn" title="Reset to first candle"
            class="w-10 h-10 rounded-full bg-gray-500 hover:bg-gray-600 text-white shadow-lg flex items-center justify-center transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582M20 20v-5h-.582M5.635 19A9 9 0 104.582 9H4"/>
            </svg>
        </button>

        {{-- Previous --}}
        <button id="prevBtn" title="Remove last candle"
            class="w-10 h-10 rounded-full bg-yellow-500 hover:bg-yellow-600 text-white shadow-lg flex items-center justify-center transition disabled:opacity-40"
            disabled>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
            </svg>
        </button>

        {{-- Next --}}
        <button id="nextBtn" title="Load next candle"
            class="w-10 h-10 rounded-full bg-green-600 hover:bg-green-700 text-white shadow-lg flex items-center justify-center transition disabled:opacity-40">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

    </div>

    <script>
        (function () {
            console.log('test');
            // ── State ─────────────────────────────────────────────────────────────
            const INTERVAL_LABEL = '{{ $interval }}';
            let state = {
                date: '',
                expiry: '',
                strikes: [],
                slots: @json($slots),         // ["09:15","09:20",...] from PHP
                currentIndex: -1,             // last loaded slot index
                totalSlots: 0,
                loadedRows: [],               // [{slotIndex, label, data}]
            };

            // ── DOM refs ─────────────────────────────────────────────────────────
            const dateInput    = document.getElementById('dateInput');
            const expiryInput  = document.getElementById('expiryInput');
            const loadBtn      = document.getElementById('loadBtn');
            const nextBtn      = document.getElementById('nextBtn');
            const prevBtn      = document.getElementById('prevBtn');
            const resetBtn     = document.getElementById('resetBtn');
            const fab          = document.getElementById('fab');
            const tableWrapper = document.getElementById('tableWrapper');
            const tableHead    = document.getElementById('tableHead');
            const tableBody    = document.getElementById('tableBody');
            const statusBar    = document.getElementById('statusBar');
            const slotCounter  = document.getElementById('slotCounter');
            const totalSlotsEl = document.getElementById('totalSlots');
            const emptyState   = document.getElementById('emptyState');

            // Strike color palette (matches original view)
            const STRIKE_COLORS = [
                'bg-blue-50   dark:bg-blue-950',
                'bg-green-50  dark:bg-green-950',
                'bg-purple-50 dark:bg-purple-950',
                'bg-orange-50 dark:bg-orange-950',
                'bg-pink-50   dark:bg-pink-950',
                'bg-teal-50   dark:bg-teal-950',
            ];

            // ── Auto-load expiry on date change ───────────────────────────────────
            dateInput.addEventListener('change', async function () {
                expiryInput.value = 'Loading…';
                console.log('change');
                const res = await fetch(`{{ route('test.oi.step.expiries') }}?date=${this.value}`);
                // Re-use OiDiffController expiries endpoint if available, otherwise add route:
                // Route::get('/oi-step/expiries', [OiStepController::class, 'fetchExpiries'])->name('oi.step.expiries');
                const json = await res.json();
                expiryInput.value = json.expiry ?? '';

                if (json.atm && json.strikes) {
                    const atm = parseInt(json.atm);
                    const nearStrikes = json.strikes
                        .map(s => parseFloat(s))
                        .sort((a, b) => Math.abs(a - atm) - Math.abs(b - atm))  // closest to ATM first for auto-fill
                        .slice(0, 6)
                        .sort((a, b) => a - b);  // ← add this — then re-sort asc for display

                    document.querySelectorAll('.strike-input').forEach((el, i) => {
                        el.value = nearStrikes[i] ?? '';
                    });

                    document.querySelectorAll('.strike-input').forEach((el, i) => {
                        el.value = nearStrikes[i] ?? '';
                    });
                }
            });

            // ── Load button — initialise session ─────────────────────────────────
            loadBtn.addEventListener('click', function () {
                const strikes = [...document.querySelectorAll('.strike-input')]
                    .map(el => el.value.trim())
                    .filter(v => v !== '');

                if (!dateInput.value || !expiryInput.value || strikes.length === 0) {
                    alert('Please fill Date, Expiry and at least one Strike.');
                    return;
                }

                state.date    = dateInput.value;
                state.expiry  = expiryInput.value;
                state.strikes = strikes.map(parseFloat);
                state.currentIndex = -1;
                state.loadedRows   = [];
                state.totalSlots   = state.slots.length;

                // Clear table
                tableBody.innerHTML = '';
                buildTableHeader(state.strikes);
                tableWrapper.classList.remove('hidden');
                emptyState.classList.add('hidden');
                fab.classList.remove('hidden');
                statusBar.classList.remove('hidden');
                totalSlotsEl.textContent = state.totalSlots;
                slotCounter.textContent  = '0';

                updateButtons();
                // Auto-load the first candle
                loadNext();
            });

            // ── Next ─────────────────────────────────────────────────────────────
            nextBtn.addEventListener('click', loadNext);

            async function loadNext() {
                if (state.currentIndex + 1 >= state.totalSlots) return;
                const nextIdx = state.currentIndex + 1;
                const slotData = await fetchSlot(nextIdx);
                if (!slotData) return;

                state.currentIndex = nextIdx;
                state.loadedRows.push(slotData);
                prependRow(slotData);       // newest candle at the TOP
                updateStatus();
                updateButtons();
            }

            // ── Previous — removes the last loaded row ────────────────────────────
            prevBtn.addEventListener('click', function () {
                if (state.loadedRows.length === 0) return;
                state.loadedRows.pop();
                state.currentIndex--;

                // Remove first <tr> group from tbody (newest row is on top)
                const firstRow = tableBody.querySelector('tr');
                if (firstRow) firstRow.remove();

                updateStatus();
                updateButtons();
            });

            // ── Reset ─────────────────────────────────────────────────────────────
            resetBtn.addEventListener('click', function () {
                state.currentIndex = -1;
                state.loadedRows   = [];
                tableBody.innerHTML = '';
                slotCounter.textContent = '0';
                updateButtons();
            });

            // ── Fetch a single slot via AJAX ──────────────────────────────────────
            async function fetchSlot(index) {
                const params = new URLSearchParams({
                    date:       state.date,
                    expiry:     state.expiry,
                    slot_index: index,
                });
                state.strikes.forEach(s => params.append('strikes[]', s));

                const res  = await fetch(`{{ route('test.oi.step.slot') }}?${params}`);
                const json = await res.json();
                if (json.error) { alert(json.error); return null; }
                return json;
            }

            // ── Build table header ────────────────────────────────────────────────
            function buildTableHeader(strikes) {
                tableHead.innerHTML = '';

                // Row 1 — Time + Strike groups
                let r1 = '<tr class="text-center">';
                r1 += '<th rowspan="2" class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-left whitespace-nowrap sticky left-0 z-20">Time</th>';
                strikes.forEach((s, i) => {
                    const col = STRIKE_COLORS[i % STRIKE_COLORS.length];
                    r1 += `<th colspan="8" class="px-2 py-1 border border-gray-300 dark:border-gray-600 font-bold ${col}">${numberFormat(s)}</th>`;
                });
                r1 += '</tr>';

                // Row 2 — CE / PE sub-headers (4 cols each)
                let r2 = '<tr class="text-center text-gray-500 dark:text-gray-400">';
                strikes.forEach((s, i) => {
                    const col = STRIKE_COLORS[i % STRIKE_COLORS.length];
                    ['CE', 'PE'].forEach(type => {
                        r2 += `<th colspan="4" class="px-2 py-1 border border-gray-300 dark:border-gray-600 ${col}">${type}</th>`;
                    });
                });
                r2 += '</tr>';

                // Row 3 — column labels
                let r3 = '<tr class="text-center text-gray-400 dark:text-gray-500 text-[10px]">';
                r3 += '<th class="px-3 py-1 border border-gray-200 dark:border-gray-700 sticky left-0 z-20"></th>';
                strikes.forEach(() => {
                    ['CE', 'PE'].forEach(() => {
                        ['Close Δ', 'OI Δ', 'Vol Δ', 'Build'].forEach(col => {
                            r3 += `<th class="px-2 py-1 border border-gray-200 dark:border-gray-700 whitespace-nowrap">${col}</th>`;
                        });
                    });
                });
                r3 += '</tr>';

                tableHead.innerHTML = r1 + r2 + r3;
            }

            // ── Prepend a data row to tbody ───────────────────────────────────────
            function prependRow(slotData) {
                const timeLabel = slotData.label;
                let html = `<tr class="text-center hover:bg-gray-50 dark:hover:bg-gray-800 transition">`;
                html += `<td class="px-3 py-2 border border-gray-200 dark:border-gray-700 font-semibold text-gray-700 dark:text-gray-200 whitespace-nowrap sticky left-0 z-10 bg-white dark:bg-gray-900">${timeLabel}</td>`;

                state.strikes.forEach((strike, si) => {
                    const col = STRIKE_COLORS[si % STRIKE_COLORS.length];
                    ['CE', 'PE'].forEach(type => {
                        const d = slotData.data?.[strike]?.[type] ?? {};

                        // Close Δ  — keep decimal format
                        html += cellDiff(d.close_diff, col, 2);

// OI Δ     — compact Indian format
                        html += cellDiff(d.oi_diff, col, 0, formatCompact);

// Vol Δ    — compact Indian format
                        html += cellDiff(d.vol_diff, col, 0, formatCompact);
                        html += buildUpCell(d.build_up, col);
                    });
                });

                html += '</tr>';

                // Insert at top
                tableBody.insertAdjacentHTML('afterbegin', html);
            }

            // ── Cell helpers ──────────────────────────────────────────────────────
            function cellDiff(val, bgClass, decimals, formatter = null) {
                if (val === null || val === undefined) {
                    return `<td class="px-2 py-1 border border-gray-200 dark:border-gray-700 ${bgClass} text-gray-400 text-center whitespace-nowrap">—</td>`;
                }
                const sign  = val > 0 ? '+' : '';
                const color = val > 0
                    ? 'text-green-600 dark:text-green-400'
                    : val < 0
                        ? 'text-red-500 dark:text-red-400'
                        : 'text-gray-500';

                const fmt = formatter
                    ? sign + formatter(val)
                    : sign + (decimals > 0
                    ? parseFloat(val).toFixed(decimals)
                    : numberFormat(val));

                return `<td class="px-2 py-1 border border-gray-200 dark:border-gray-700 ${bgClass} ${color} font-medium text-center whitespace-nowrap">${fmt}</td>`;
            }



            // ── Indian compact number format (matches PHP format_inr_compact) ─────────
            function formatCompact(number) {
                const abs  = Math.abs(parseFloat(number));
                const sign = number < 0 ? '-' : '';

                if (abs >= 10000000) return sign + (abs / 10000000).toFixed(2) + ' C';
                if (abs >= 100000)   return sign + (abs / 100000).toFixed(2)   + ' L';
                if (abs >= 1000)     return sign + (abs / 1000).toFixed(2)     + ' T';

                return sign + abs.toFixed(2);
            }


            function buildUpCell(buildUp, bgClass) {
                const map = {
                    LB: ['Long Build',   'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'],
                    SB: ['Short Build',  'bg-red-100   text-red-800   dark:bg-red-900   dark:text-red-300'],
                    LU: ['Long Unwind',  'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300'],
                    SC: ['Short Cover',  'bg-blue-100  text-blue-800  dark:bg-blue-900  dark:text-blue-300'],
                };
                if (!buildUp || !map[buildUp]) {
                    return `<td class="px-2 py-1 border border-gray-200 dark:border-gray-700 ${bgClass} text-gray-400">—</td>`;
                }
                const [label, cls] = map[buildUp];
                return `<td class="px-2 py-1 border border-gray-200 dark:border-gray-700 ${bgClass}">
            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold ${cls}">${buildUp}</span>
        </td>`;
            }

            // ── Utility ───────────────────────────────────────────────────────────
            function numberFormat(n) {
                return parseInt(n).toLocaleString('en-IN');
            }

            function updateStatus() {
                slotCounter.textContent = state.loadedRows.length;
            }

            function updateButtons() {
                prevBtn.disabled = state.loadedRows.length === 0;
                nextBtn.disabled = state.currentIndex + 1 >= state.totalSlots;
            }
        })();
    </script>
@endsection




