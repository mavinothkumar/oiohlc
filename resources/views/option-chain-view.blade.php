@extends('layouts.app')

@section('title', 'Option Chain View')

@section('content')
<div class="w-full" id="oc-root">

    {{-- ══════════ TOP CONTROLS ══════════ --}}
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm shadow-sm">

        {{-- Mode --}}
        <div class="flex items-center gap-3 mr-2">
            <label class="flex items-center gap-1 cursor-pointer font-semibold">
                <input type="radio" name="mode" value="live" id="mode-live" class="accent-red-600">
                <span class="text-red-600">● Live data</span>
            </label>
            <label class="flex items-center gap-1 cursor-pointer">
                <input type="radio" name="mode" value="history" id="mode-history">
                <span class="text-gray-600">Historical</span>
            </label>
        </div>

        {{-- Select Name --}}
        <div class="flex items-center gap-1">
            <label class="text-gray-500 text-xs font-semibold">Select Name</label>
            <select id="oc-underlying" class="border border-gray-300 rounded px-2 py-1 text-xs bg-white min-w-[120px]">
                <option value="NSE_INDEX|Nifty 50">NIFTY</option>
            </select>
        </div>

        {{-- Select Date (history mode only) --}}
        <div class="flex items-center gap-1" id="date-wrapper">
            <label class="text-gray-500 text-xs font-semibold">Select Date</label>
            <input type="date" id="oc-date" value="{{ $selectedDate }}"
                   class="border border-gray-300 rounded px-2 py-1 text-xs bg-white">
        </div>

        {{-- Expiry Date --}}
        <div class="flex items-center gap-1">
            <label class="text-gray-500 text-xs font-semibold">Expiry Date</label>
            <select id="oc-expiry" class="border border-gray-300 rounded px-2 py-1 text-xs bg-white min-w-[110px]">
                @foreach($expiries as $exp)
                    <option value="{{ $exp }}" {{ $exp === $selectedExpiry ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::parse($exp)->format('d-M-Y') }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Interval --}}
        <div class="flex items-center gap-1">
            <label class="text-gray-500 text-xs font-semibold">Interval</label>
            <select id="oc-interval" class="border border-gray-300 rounded px-2 py-1 text-xs bg-white">
                <option value="custom">Start &amp; End Both Custom time</option>
            </select>
        </div>

        {{-- Start Time --}}
        <div class="flex items-center gap-1">
            <label class="text-gray-500 text-xs font-semibold">Start Time</label>
            <input type="time" id="oc-start" value="{{ $startTime }}"
                   class="border border-gray-300 rounded px-2 py-1 text-xs bg-white">
        </div>

        {{-- End Time --}}
        <div class="flex items-center gap-1">
            <label class="text-gray-500 text-xs font-semibold">End Time</label>
            <input type="time" id="oc-end" value="{{ $endTime }}"
                   class="border border-gray-300 rounded px-2 py-1 text-xs bg-white">
        </div>

        {{-- Strikes each side --}}
        <div class="flex items-center gap-1">
            <label class="text-gray-500 text-xs font-semibold">± Strikes</label>
            <input type="number" id="oc-strikes" value="10" min="1" max="50"
                   class="border border-gray-300 rounded px-2 py-1 text-xs bg-white w-16 text-center"
                   title="Strikes to show above and below ATM">
        </div>

        {{-- Go --}}
        <button id="oc-go" class="bg-red-700 hover:bg-red-800 text-white font-bold px-4 py-1.5 rounded text-xs shadow">
            Go
        </button>

        {{-- Column Setting --}}
        <button id="oc-col-btn"
                class="border border-red-700 text-red-700 hover:bg-red-50 font-semibold px-4 py-1.5 rounded text-xs">
            Column Setting
        </button>

        {{-- Jump to ATM --}}
        <button id="oc-atm-btn"
                class="border border-amber-500 text-amber-700 hover:bg-amber-50 font-semibold px-4 py-1.5 rounded text-xs hidden">
            ⬛ Jump ATM
        </button>
    </div>

    {{-- ══════════ INFO BAR ══════════ --}}
    <div class="bg-gray-50 border-b border-gray-200 px-4 py-1 flex items-center justify-between text-xs">
        <div class="flex items-center gap-4">
            <div>
                <span class="font-semibold text-gray-600">Total PCR:</span>
                <span id="bar-pcr" class="font-bold text-gray-800">—</span>
                <span id="bar-change-pcr" class="text-gray-500 ml-1"></span>
            </div>
        </div>
        <div id="bar-spot" class="font-semibold text-gray-700">
            Underlying: <span id="bar-spot-val" class="text-indigo-700">—</span>
        </div>
        <div id="bar-snapshot" class="text-gray-400 italic hidden">
            Snapshot: <span id="bar-snapshot-val">—</span>
        </div>
    </div>

    {{-- ══════════ LOADING INDICATOR ══════════ --}}
    <div id="oc-loading" class="text-center py-10 text-gray-500 text-sm hidden">
        <svg class="animate-spin inline h-5 w-5 mr-2 text-red-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        Loading option chain…
    </div>

    {{-- ══════════ ERROR BAR ══════════ --}}
    <div id="oc-error" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-2 text-sm rounded mx-4 mt-2"></div>

    {{-- ══════════ TABLE WRAPPER ══════════ --}}
    <div class="overflow-x-auto" id="oc-table-wrap" style="max-height: calc(100vh - 120px); overflow-y: auto;">
        <table id="oc-table" class="w-full border-collapse text-xs font-mono" style="min-width:1400px">
            <thead id="oc-thead" class="sticky top-0 z-10">
            <tr id="oc-group-header">
                {{-- CALL group header --}}
                <th id="gh-call" colspan="9" class="text-center py-1.5 bg-orange-100 text-orange-800 font-bold border border-orange-300 text-xs tracking-wider">
                    CALL
                </th>
                {{-- Strike --}}
                <th class="bg-indigo-900 text-white text-center font-bold py-1.5 px-3 border border-indigo-700 w-20 text-xs">
                    STRIKE
                </th>
                {{-- PUT group header --}}
                <th id="gh-put" colspan="9" class="text-center py-1.5 bg-blue-100 text-blue-800 font-bold border border-blue-300 text-xs tracking-wider">
                    PUT
                </th>
            </tr>
            <tr id="oc-col-header" class="bg-gray-100 text-gray-600 border-b-2 border-gray-400">
                {{-- CE columns (draggable) --}}
                <th data-col="ce_delta"     data-side="CE" draggable="true" class="oc-th ce-col drag-handle text-center py-1 px-2 border border-gray-300 bg-orange-50 cursor-grab select-none">Delta</th>
                <th data-col="ce_oi_int"    data-side="CE" draggable="true" class="oc-th ce-col drag-handle text-center py-1 px-2 border border-gray-300 bg-orange-50 cursor-grab select-none">OI Int.</th>
                <th data-col="ce_oi"        data-side="CE" draggable="true" class="oc-th ce-col drag-handle text-center py-1 px-2 border border-gray-300 bg-orange-50 cursor-grab select-none">OI</th>
                <th data-col="ce_diff_oi"   data-side="CE" draggable="true" class="oc-th ce-col drag-handle text-center py-1 px-2 border border-gray-300 bg-orange-50 cursor-grab select-none">OI Chng.</th>
                <th data-col="ce_volume"    data-side="CE" draggable="true" class="oc-th ce-col drag-handle text-center py-1 px-2 border border-gray-300 bg-orange-50 cursor-grab select-none">Volume</th>
                <th data-col="ce_iv"        data-side="CE" draggable="true" class="oc-th ce-col drag-handle text-center py-1 px-2 border border-gray-300 bg-orange-50 cursor-grab select-none">IV</th>
                <th data-col="ce_ltp"       data-side="CE" draggable="true" class="oc-th ce-col drag-handle text-center py-1 px-2 border border-gray-300 bg-orange-50 cursor-grab select-none">LTP</th>
                <th data-col="ce_ltp_pct"   data-side="CE" draggable="true" class="oc-th ce-col drag-handle text-center py-1 px-2 border border-gray-300 bg-orange-50 cursor-grab select-none">LTP %</th>
                <th data-col="ce_premium"   data-side="CE" draggable="true" class="oc-th ce-col drag-handle text-center py-1 px-2 border border-gray-300 bg-orange-50 cursor-grab select-none">Premium</th>
                {{-- Hidden-by-default CE columns — must exist so body ordering works --}}
                <th data-col="ce_ltp_chg"   data-side="CE" draggable="true" class="oc-th ce-col drag-handle text-center py-1 px-2 border border-gray-300 bg-orange-50 cursor-grab select-none" style="display:none">LTP Chg</th>
                <th data-col="ce_diff_vol"  data-side="CE" draggable="true" class="oc-th ce-col drag-handle text-center py-1 px-2 border border-gray-300 bg-orange-50 cursor-grab select-none" style="display:none">Diff Vol</th>
                {{-- Strike col header (fixed, not draggable) --}}
                <th class="bg-indigo-900 text-white text-center py-1 px-3 border border-indigo-700 w-20"></th>
                {{-- PE columns (draggable) --}}
                <th data-col="pe_premium"   data-side="PE" draggable="true" class="oc-th pe-col drag-handle text-center py-1 px-2 border border-gray-300 bg-blue-50 cursor-grab select-none">Premium</th>
                <th data-col="pe_ltp_pct"   data-side="PE" draggable="true" class="oc-th pe-col drag-handle text-center py-1 px-2 border border-gray-300 bg-blue-50 cursor-grab select-none">LTP %</th>
                <th data-col="pe_ltp"       data-side="PE" draggable="true" class="oc-th pe-col drag-handle text-center py-1 px-2 border border-gray-300 bg-blue-50 cursor-grab select-none">LTP</th>
                <th data-col="pe_iv"        data-side="PE" draggable="true" class="oc-th pe-col drag-handle text-center py-1 px-2 border border-gray-300 bg-blue-50 cursor-grab select-none">IV</th>
                <th data-col="pe_volume"    data-side="PE" draggable="true" class="oc-th pe-col drag-handle text-center py-1 px-2 border border-gray-300 bg-blue-50 cursor-grab select-none">Volume</th>
                <th data-col="pe_diff_oi"   data-side="PE" draggable="true" class="oc-th pe-col drag-handle text-center py-1 px-2 border border-gray-300 bg-blue-50 cursor-grab select-none">OI Chng.</th>
                <th data-col="pe_oi"        data-side="PE" draggable="true" class="oc-th pe-col drag-handle text-center py-1 px-2 border border-gray-300 bg-blue-50 cursor-grab select-none">OI</th>
                <th data-col="pe_oi_int"    data-side="PE" draggable="true" class="oc-th pe-col drag-handle text-center py-1 px-2 border border-gray-300 bg-blue-50 cursor-grab select-none">OI Int.</th>
                <th data-col="pe_delta"     data-side="PE" draggable="true" class="oc-th pe-col drag-handle text-center py-1 px-2 border border-gray-300 bg-blue-50 cursor-grab select-none">Delta</th>
                {{-- Hidden-by-default PE columns — must exist so body ordering works --}}
                <th data-col="pe_ltp_chg"   data-side="PE" draggable="true" class="oc-th pe-col drag-handle text-center py-1 px-2 border border-gray-300 bg-blue-50 cursor-grab select-none" style="display:none">LTP Chg</th>
                <th data-col="pe_diff_vol"  data-side="PE" draggable="true" class="oc-th pe-col drag-handle text-center py-1 px-2 border border-gray-300 bg-blue-50 cursor-grab select-none" style="display:none">Diff Vol</th>
            </tr>

            </thead>
            <tbody id="oc-tbody">
            <tr>
                <td colspan="19" class="text-center py-8 text-gray-400 text-sm">
                    Select filters and press <strong>Go</strong> to load data.
                </td>
            </tr>
            </tbody>
        </table>
    </div>

</div>

{{-- ══════════ COLUMN SETTINGS MODAL ══════════ --}}
<div id="oc-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-lg shadow-2xl w-[640px] max-h-[90vh] overflow-y-auto p-6 relative">
        <button id="oc-modal-close" class="absolute top-3 right-4 text-gray-400 hover:text-gray-700 text-xl font-bold">✕</button>
        <h2 class="text-lg font-bold mb-1">Select Columns</h2>
        <p class="text-gray-500 text-sm mb-4">Select the column you want to show or hide in option chain</p>

        <div class="mb-4">
            <p class="font-semibold text-sm mb-2">Select CALL side columns:</p>
            <div id="col-grid-ce" class="grid grid-cols-3 gap-y-2 gap-x-4 text-sm"></div>
        </div>
        <hr class="my-4">
        <div class="mb-4">
            <p class="font-semibold text-sm mb-2">Select PUT side columns:</p>
            <div id="col-grid-pe" class="grid grid-cols-3 gap-y-2 gap-x-4 text-sm"></div>
        </div>

        <div class="flex justify-end gap-3 mt-4">
            <button id="col-clear" class="border border-gray-300 text-gray-700 hover:bg-gray-100 px-4 py-1.5 rounded text-sm">Clear all</button>
            <button id="col-reset" class="border border-gray-300 text-gray-700 hover:bg-gray-100 px-4 py-1.5 rounded text-sm">Reset all</button>
            <button id="col-save"  class="border border-indigo-500 text-indigo-700 hover:bg-indigo-50 px-4 py-1.5 rounded text-sm font-semibold">Save Selection</button>
            <button id="col-ok"   class="bg-red-700 hover:bg-red-800 text-white px-5 py-1.5 rounded text-sm font-bold">Ok</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    // ── Column definitions ──────────────────────────────────────────────────
    const ALL_COLS = [
        { key: 'ce_delta',    label: 'Delta',    side: 'CE', default: true  },
        { key: 'ce_oi_int',   label: 'OI Int.',  side: 'CE', default: true  },
        { key: 'ce_oi',       label: 'OI',       side: 'CE', default: true  },
        { key: 'ce_diff_oi',  label: 'OI Chng.', side: 'CE', default: true  },
        { key: 'ce_volume',   label: 'Volume',   side: 'CE', default: true  },
        { key: 'ce_iv',       label: 'IV',       side: 'CE', default: true  },
        { key: 'ce_ltp',      label: 'LTP',      side: 'CE', default: true  },
        { key: 'ce_ltp_pct',  label: 'LTP %',    side: 'CE', default: true  },
        { key: 'ce_premium',  label: 'Premium',  side: 'CE', default: true  },
        { key: 'ce_ltp_chg',  label: 'LTP Chg',  side: 'CE', default: false },
        { key: 'ce_diff_vol', label: 'Diff Vol', side: 'CE', default: false },

        { key: 'pe_premium',  label: 'Premium',  side: 'PE', default: true  },
        { key: 'pe_ltp_pct',  label: 'LTP %',    side: 'PE', default: true  },
        { key: 'pe_ltp',      label: 'LTP',      side: 'PE', default: true  },
        { key: 'pe_iv',       label: 'IV',       side: 'PE', default: true  },
        { key: 'pe_volume',   label: 'Volume',   side: 'PE', default: true  },
        { key: 'pe_diff_oi',  label: 'OI Chng.', side: 'PE', default: true  },
        { key: 'pe_oi',       label: 'OI',       side: 'PE', default: true  },
        { key: 'pe_oi_int',   label: 'OI Int.',  side: 'PE', default: true  },
        { key: 'pe_delta',    label: 'Delta',    side: 'PE', default: true  },
        { key: 'pe_ltp_chg',  label: 'LTP Chg',  side: 'PE', default: false },
        { key: 'pe_diff_vol', label: 'Diff Vol', side: 'PE', default: false },
    ];

    const LS_VIS   = 'oc_view_columns_v2';
    const LS_ORDER = 'oc_view_col_order_v3';

    // ── Visibility ──────────────────────────────────────────────────────────
    function loadVisibility() {
        try {
            const stored = JSON.parse(localStorage.getItem(LS_VIS) || '{}');
            const vis = {};
            ALL_COLS.forEach(c => { vis[c.key] = c.key in stored ? stored[c.key] : c.default; });
            return vis;
        } catch { const vis = {}; ALL_COLS.forEach(c => vis[c.key] = c.default); return vis; }
    }
    function saveVisibility(vis) { localStorage.setItem(LS_VIS, JSON.stringify(vis)); }
    let colVis = loadVisibility();

    // ── Column Order ────────────────────────────────────────────────────────
    // Stored as ordered array of col keys for CE side and PE side separately
    function defaultOrder(side) {
        return ALL_COLS.filter(c => c.side === side).map(c => c.key);
    }
    function loadOrder() {
        try {
            const stored = JSON.parse(localStorage.getItem(LS_ORDER) || '{}');
            const ce = stored.CE && stored.CE.length ? stored.CE : defaultOrder('CE');
            const pe = stored.PE && stored.PE.length ? stored.PE : defaultOrder('PE');
            return { CE: ce, PE: pe };
        } catch { return { CE: defaultOrder('CE'), PE: defaultOrder('PE') }; }
    }
    function saveOrder(order) { localStorage.setItem(LS_ORDER, JSON.stringify(order)); }
    let colOrder = loadOrder();

    // Apply DOM column order — reorder <th> elements and update group header colspan
    function applyColumnOrder() {
        const headerRow = document.getElementById('oc-col-header');
        const strikeThInHeader = headerRow.querySelector('th:not([data-col])');

        // Reorder CE ths
        const ceThs = colOrder.CE.map(key => headerRow.querySelector(`th[data-col="${key}"]`)).filter(Boolean);
        ceThs.forEach(th => headerRow.insertBefore(th, strikeThInHeader));

        // Reorder PE ths (after strike)
        const peThs = colOrder.PE.map(key => headerRow.querySelector(`th[data-col="${key}"]`)).filter(Boolean);
        peThs.forEach(th => headerRow.appendChild(th));

        // Update group header colspans
        document.getElementById('gh-call').colSpan = ceThs.length;
        document.getElementById('gh-put').colSpan  = peThs.length;
    }

    // Reorder cells in each body row to match header order
    function applyBodyOrder() {
        const rows = document.querySelectorAll('#oc-tbody tr[data-strike]');
        rows.forEach(tr => {
            const strikeCell = tr.querySelector('td.strike-cell');

            const ceCells = colOrder.CE.map(key => tr.querySelector(`td[data-col="${key}"]`)).filter(Boolean);
            ceCells.forEach(td => tr.insertBefore(td, strikeCell));

            const peCells = colOrder.PE.map(key => tr.querySelector(`td[data-col="${key}"]`)).filter(Boolean);
            peCells.forEach(td => tr.appendChild(td));
        });
    }

    // ── State ───────────────────────────────────────────────────────────────
    let lastData = null;
    let autoRefreshTimer = null;
    let atmStrikeGlobal = null;

    // ── DOM Refs ────────────────────────────────────────────────────────────
    const modeLive       = document.getElementById('mode-live');
    const modeHist       = document.getElementById('mode-history');
    const dateWrapper    = document.getElementById('date-wrapper');
    const dateInput      = document.getElementById('oc-date');
    const expiryInput    = document.getElementById('oc-expiry');
    const startInput     = document.getElementById('oc-start');
    const endInput       = document.getElementById('oc-end');
    const goBtn          = document.getElementById('oc-go');
    const colBtn         = document.getElementById('oc-col-btn');
    const atmBtn         = document.getElementById('oc-atm-btn');
    const loading        = document.getElementById('oc-loading');
    const errBar         = document.getElementById('oc-error');
    const tbody          = document.getElementById('oc-tbody');
    const tableWrap      = document.getElementById('oc-table-wrap');
    const barPcr         = document.getElementById('bar-pcr');
    const barChgPcr      = document.getElementById('bar-change-pcr');
    const barSpotVal     = document.getElementById('bar-spot-val');
    const barSnapshot    = document.getElementById('bar-snapshot');
    const barSnapshotVal = document.getElementById('bar-snapshot-val');
    const modal          = document.getElementById('oc-modal');

    // ── Mode toggle ─────────────────────────────────────────────────────────
    function getMode() { return modeLive.checked ? 'live' : 'history'; }
    function applyModeUI() {
        const isLive = getMode() === 'live';
        dateWrapper.style.opacity = isLive ? '0.4' : '1';
        dateWrapper.style.pointerEvents = isLive ? 'none' : '';
    }
    modeLive.addEventListener('change', () => { applyModeUI(); reloadExpiries(); });
    modeHist.addEventListener('change', () => { applyModeUI(); reloadExpiries(); });

    @if($mode === 'history') modeHist.checked = true;
    @else modeLive.checked = true;
    @endif
    applyModeUI();

    // ── Reload expiries ─────────────────────────────────────────────────────
    function reloadExpiries() {
        const url = `{{ route('api.option-chain-view.expiries') }}?mode=${getMode()}&date=${dateInput.value}`;
        fetch(url).then(r => r.json()).then(data => {
            const expiries = data.expiries || [];
            const cur = expiryInput.value;
            expiryInput.innerHTML = '';
            expiries.forEach(exp => {
                const opt = document.createElement('option');
                opt.value = exp;
                const d = new Date(exp);
                opt.textContent = d.toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric' });
                if (exp === cur) opt.selected = true;
                expiryInput.appendChild(opt);
            });
            if (expiries.length && !expiries.includes(cur)) expiryInput.value = expiries[0];
        }).catch(() => {});
    }
    dateInput.addEventListener('change', reloadExpiries);

    // ── Fetch data ──────────────────────────────────────────────────────────
    function fetchData() {
        clearAutoRefresh();
        const expiry = expiryInput.value;
        if (!expiry) { showError('Please select an expiry date.'); return; }
        showLoading(true); hideError();

        const params = new URLSearchParams({
            mode: getMode(), expiry,
            date: dateInput.value,
            start_time: startInput.value,
            end_time: endInput.value,
            underlying: document.getElementById('oc-underlying').value,
            strikes: document.getElementById('oc-strikes').value,
        });

        fetch(`{{ route('api.option-chain-view.data') }}?${params}`)
            .then(r => r.json())
            .then(data => {
                showLoading(false);
                if (data.error) { showError(data.error); return; }
                lastData = data;
                renderTable(data);
                applyColumnOrder();
                applyBodyOrder();
                applyColumnVisibility();
                scrollToAtm();
                if (getMode() === 'live') scheduleAutoRefresh();
            })
            .catch(err => { showLoading(false); showError('Failed to load data: ' + err.message); });
    }
    goBtn.addEventListener('click', fetchData);

    // ── Auto-refresh ─────────────────────────────────────────────────────────
    function scheduleAutoRefresh() {
        clearAutoRefresh();
        autoRefreshTimer = setTimeout(() => { if (getMode() === 'live') fetchData(); }, 60000);
    }
    function clearAutoRefresh() {
        if (autoRefreshTimer) { clearTimeout(autoRefreshTimer); autoRefreshTimer = null; }
    }

    // ── Scroll ATM row to vertical center of viewport ────────────────────────
    function scrollToAtm() {
        requestAnimationFrame(() => {
            const atmRow = tbody.querySelector('tr.atm-row');
            if (!atmRow) return;
            const wrap = tableWrap;
            const wrapRect  = wrap.getBoundingClientRect();
            const rowRect   = atmRow.getBoundingClientRect();
            const offset    = (rowRect.top - wrapRect.top) + wrap.scrollTop;
            const center    = offset - (wrap.clientHeight / 2) + (atmRow.offsetHeight / 2);
            wrap.scrollTo({ top: center, behavior: 'smooth' });
            atmBtn.classList.remove('hidden');
        });
    }
    atmBtn.addEventListener('click', scrollToAtm);

    // ── Formatters ───────────────────────────────────────────────────────────
    function fmt(n, d = 0) {
        if (n === null || n === undefined) return '—';
        return Number(n).toLocaleString('en-IN', { minimumFractionDigits: d, maximumFractionDigits: d });
    }
    function fmtSigned(n, d = 0) {
        if (n === null || n === undefined) return '—';
        const v = Number(n);
        return (v >= 0 ? '+' : '') + v.toLocaleString('en-IN', { minimumFractionDigits: d, maximumFractionDigits: d });
    }

    // ── Build-up badges ──────────────────────────────────────────────────────
    const BUILD_UP_BADGE = {
        'Long Build':  '<span class="build-badge lb-badge">L.B.</span>',
        'Short Build': '<span class="build-badge sb-badge">S.B.</span>',
        'Short Cover': '<span class="build-badge sc-badge">S.C.</span>',
        'Long Unwind': '<span class="build-badge lu-badge">L.U.</span>',
    };
    function buildupBadge(v) { return BUILD_UP_BADGE[v] || '<span class="build-badge neutral-badge">—</span>'; }

    // ── Highlight class resolvers ─────────────────────────────────────────────
    function hlClass(hl, strike, side, field) {
        if (!hl?.[side]?.[field]) return '';
        const h = hl[side][field];
        if (h.max_strike === strike && h.max_val !== null && h.max_val > 0) return 'hl-max';
        if (h.min_strike === strike && h.min_val !== null && h.min_val < 0) return 'hl-min';
        return '';
    }
    function volHlClass(hl, strike, side) {
        if (!hl?.[side]?.volume) return '';
        return hl[side].volume.max_strike === strike ? 'hl-vol' : '';
    }
    function oiHlClass(hl, strike, side) {
        if (!hl?.[side]?.oi) return '';
        return hl[side].oi.max_strike === strike ? 'hl-oi' : '';
    }

    // ── Render table ─────────────────────────────────────────────────────────
    function renderTable(data) {
        const rows = data.rows || [];
        const hl   = data.highlights || {};
        atmStrikeGlobal = data.atm_strike;

        // Info bar
        barPcr.textContent     = data.total_pcr != null ? data.total_pcr : '—';
        barChgPcr.textContent  = data.change_pcr != null ? `(${data.change_pcr})` : '';
        barSpotVal.textContent = data.spot ? `NIFTY 50 at ${fmt(data.spot, 2)}` : '—';
        if (data.snapshot_at) {
            barSnapshot.classList.remove('hidden');
            const d = new Date(data.snapshot_at.replace(' ', 'T'));
            barSnapshotVal.textContent = d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
        }

        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="19" class="text-center py-8 text-gray-400">No data found for the selected filters.</td></tr>`;
            return;
        }

        let html = '';
        rows.forEach((row, idx) => {
            const ce = row.CE, pe = row.PE, strike = row.strike, isAtm = row.is_atm;

            // Row base class
            const rowBase = isAtm ? 'atm-row' : (idx % 2 === 0 ? 'row-even' : 'row-odd');

            // Highlight classes
            const ceOiCls     = oiHlClass(hl, strike, 'CE');
            const ceDiffOiCls = hlClass(hl, strike, 'CE', 'diff_oi');
            const ceVolCls    = volHlClass(hl, strike, 'CE');
            const peOiCls     = oiHlClass(hl, strike, 'PE');
            const peDiffOiCls = hlClass(hl, strike, 'PE', 'diff_oi');
            const peVolCls    = volHlClass(hl, strike, 'PE');

            html += `<tr class="${rowBase} oc-row border-b border-gray-100 transition-colors h-8" data-strike="${strike}">`;

            // ── CE cells ──────────────────────────────────────────────────
            html += `<td data-col="ce_delta"    class="oc-td border-r border-gray-100 text-center text-gray-600">${ce ? fmt(ce.delta, 4) : '—'}</td>`;
            html += `<td data-col="ce_oi_int"   class="oc-td border-r border-gray-100 text-center">${ce ? buildupBadge(ce.build_up) : '—'}</td>`;
            html += `<td data-col="ce_oi"       class="oc-td border-r border-gray-100 text-right font-bold ${ceOiCls}">${ce ? fmt(ce.oi) : '—'}</td>`;
            html += `<td data-col="ce_diff_oi"  class="oc-td border-r border-gray-100 text-right font-bold ${ceDiffOiCls}">${ce ? fmtSigned(ce.diff_oi) : '—'}</td>`;
            html += `<td data-col="ce_volume"   class="oc-td border-r border-gray-100 text-right font-bold ${ceVolCls}">${ce ? fmt(ce.volume) : '—'}</td>`;
            html += `<td data-col="ce_iv"       class="oc-td border-r border-gray-100 text-center text-gray-500">${ce ? fmt(ce.iv, 2) : '—'}</td>`;
            html += `<td data-col="ce_ltp"      class="oc-td border-r border-gray-100 text-right font-semibold text-orange-700">${ce ? fmt(ce.ltp, 2) : '—'}</td>`;
            html += `<td data-col="ce_ltp_pct"  class="oc-td border-r border-gray-100 text-center ${ce && ce.ltp_pct >= 0 ? 'text-green-700' : 'text-red-600'}">${ce ? fmtSigned(ce.ltp_pct, 2) + '%' : '—'}</td>`;
            html += `<td data-col="ce_premium"  class="oc-td border-r border-gray-100 text-right text-indigo-600">${ce ? fmt(ce.intrinsic, 2) : '—'}</td>`;
            html += `<td data-col="ce_ltp_chg"  class="oc-td border-r border-gray-100 text-right ${ce && ce.diff_ltp >= 0 ? 'text-green-700' : 'text-red-600'}">${ce ? fmtSigned(ce.diff_ltp, 2) : '—'}</td>`;
            html += `<td data-col="ce_diff_vol" class="oc-td border-r border-gray-100 text-right text-gray-500">${ce ? fmtSigned(ce.diff_volume) : '—'}</td>`;

            // ── Strike cell (fixed center, not draggable) ──────────────────
            html += `<td class="strike-cell text-center font-extrabold border-l-2 border-r-2 px-2 py-0.5 whitespace-nowrap text-sm w-20 ${isAtm ? 'atm-strike-cell' : 'bg-indigo-50 border-indigo-200 text-indigo-900'}">`;
            if (isAtm) {
                html += `<div class="flex items-center justify-center gap-1">`;
                html += `<span class="atm-arrow">▶</span>`;
                html += `<span>${fmt(strike)}</span>`;
                html += `<span class="text-[9px] bg-amber-600 text-white px-1 py-0.5 rounded font-black ml-1 tracking-wider">ATM</span>`;
                html += `</div>`;
            } else {
                html += fmt(strike);
            }
            html += `</td>`;

            // ── PE cells ──────────────────────────────────────────────────
            html += `<td data-col="pe_premium"  class="oc-td border-r border-gray-100 text-left text-indigo-600">${pe ? fmt(pe.intrinsic, 2) : '—'}</td>`;
            html += `<td data-col="pe_ltp_pct"  class="oc-td border-r border-gray-100 text-center ${pe && pe.ltp_pct >= 0 ? 'text-green-700' : 'text-red-600'}">${pe ? fmtSigned(pe.ltp_pct, 2) + '%' : '—'}</td>`;
            html += `<td data-col="pe_ltp"      class="oc-td border-r border-gray-100 text-left font-semibold text-blue-700">${pe ? fmt(pe.ltp, 2) : '—'}</td>`;
            html += `<td data-col="pe_iv"       class="oc-td border-r border-gray-100 text-center text-gray-500">${pe ? fmt(pe.iv, 2) : '—'}</td>`;
            html += `<td data-col="pe_volume"   class="oc-td border-r border-gray-100 text-right font-bold ${peVolCls}">${pe ? fmt(pe.volume) : '—'}</td>`;
            html += `<td data-col="pe_diff_oi"  class="oc-td border-r border-gray-100 text-right font-bold ${peDiffOiCls}">${pe ? fmtSigned(pe.diff_oi) : '—'}</td>`;
            html += `<td data-col="pe_oi"       class="oc-td border-r border-gray-100 text-right font-bold ${peOiCls}">${pe ? fmt(pe.oi) : '—'}</td>`;
            html += `<td data-col="pe_oi_int"   class="oc-td border-r border-gray-100 text-center">${pe ? buildupBadge(pe.build_up) : '—'}</td>`;
            html += `<td data-col="pe_delta"    class="oc-td border-r border-gray-100 text-center text-gray-600">${pe ? fmt(pe.delta, 4) : '—'}</td>`;
            html += `<td data-col="pe_ltp_chg"  class="oc-td border-r border-gray-100 text-right ${pe && pe.diff_ltp >= 0 ? 'text-green-700' : 'text-red-600'}">${pe ? fmtSigned(pe.diff_ltp, 2) : '—'}</td>`;
            html += `<td data-col="pe_diff_vol" class="oc-td border-r border-gray-100 text-right text-gray-500">${pe ? fmtSigned(pe.diff_volume) : '—'}</td>`;

            html += '</tr>';
        });

        tbody.innerHTML = html;
    }

    // ── Apply column visibility ──────────────────────────────────────────────
    function applyColumnVisibility() {
        document.querySelectorAll('#oc-col-header th[data-col]').forEach(th => {
            th.style.display = colVis[th.dataset.col] !== false ? '' : 'none';
        });
        document.querySelectorAll('#oc-tbody td[data-col]').forEach(td => {
            td.style.display = colVis[td.dataset.col] !== false ? '' : 'none';
        });
        // Update group colspans based on visible CE/PE ths
        const visibleCe = document.querySelectorAll('#oc-col-header th[data-side="CE"]:not([style*="display: none"])').length;
        const visiblePe = document.querySelectorAll('#oc-col-header th[data-side="PE"]:not([style*="display: none"])').length;
        document.getElementById('gh-call').colSpan = visibleCe || 1;
        document.getElementById('gh-put').colSpan  = visiblePe || 1;
    }

    // ── Drag-and-Drop column reordering ──────────────────────────────────────
    let dragSrcCol = null;

    function initDrag() {
        const ths = document.querySelectorAll('#oc-col-header th[data-col]');
        ths.forEach(th => {
            th.addEventListener('dragstart', e => {
                dragSrcCol = th;
                th.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', th.dataset.col);
            });
            th.addEventListener('dragend', () => {
                th.classList.remove('dragging');
                document.querySelectorAll('#oc-col-header th.drag-over').forEach(t => t.classList.remove('drag-over'));
            });
            th.addEventListener('dragover', e => {
                e.preventDefault();
                if (dragSrcCol && dragSrcCol !== th && dragSrcCol.dataset.side === th.dataset.side) {
                    document.querySelectorAll('#oc-col-header th.drag-over').forEach(t => t.classList.remove('drag-over'));
                    th.classList.add('drag-over');
                    e.dataTransfer.dropEffect = 'move';
                }
            });
            th.addEventListener('drop', e => {
                e.preventDefault();
                if (!dragSrcCol || dragSrcCol === th) return;
                const srcSide = dragSrcCol.dataset.side;
                const dstSide = th.dataset.side;
                if (srcSide !== dstSide) return; // can't cross CE↔PE

                // Reorder in the header DOM
                const headerRow = document.getElementById('oc-col-header');
                const allThs = [...headerRow.querySelectorAll('th')];
                const srcIdx = allThs.indexOf(dragSrcCol);
                const dstIdx = allThs.indexOf(th);
                if (srcIdx < dstIdx) {
                    th.parentNode.insertBefore(dragSrcCol, th.nextSibling);
                } else {
                    th.parentNode.insertBefore(dragSrcCol, th);
                }

                // Rebuild colOrder from DOM
                colOrder.CE = [...headerRow.querySelectorAll('th[data-side="CE"]')].map(t => t.dataset.col);
                colOrder.PE = [...headerRow.querySelectorAll('th[data-side="PE"]')].map(t => t.dataset.col);
                saveOrder(colOrder);

                // Reorder body rows
                applyBodyOrder();
                applyColumnVisibility();
                th.classList.remove('drag-over');
            });
        });
    }

    // ── Column Settings Modal ────────────────────────────────────────────────
    function buildModalGrid() {
        ['CE', 'PE'].forEach(side => {
            const grid = document.getElementById(`col-grid-${side.toLowerCase()}`);
            grid.innerHTML = '';
            ALL_COLS.filter(c => c.side === side).forEach(col => {
                const checked = colVis[col.key] !== false ? 'checked' : '';
                const el = document.createElement('label');
                el.className = 'flex items-center gap-2 cursor-pointer text-sm text-gray-700';
                el.innerHTML = `<input type="checkbox" data-col-key="${col.key}" ${checked} class="accent-red-600"> ${col.label}`;
                grid.appendChild(el);
            });
        });
    }

    function getModalValues() {
        const vis = {};
        document.querySelectorAll('#oc-modal input[type=checkbox][data-col-key]').forEach(c => {
            vis[c.dataset.colKey] = c.checked;
        });
        return vis;
    }

    colBtn.addEventListener('click', () => { buildModalGrid(); modal.classList.replace('hidden', 'flex'); });
    document.getElementById('oc-modal-close').addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    function closeModal() { modal.classList.replace('flex', 'hidden'); }

    document.getElementById('col-clear').addEventListener('click', () => {
        document.querySelectorAll('#oc-modal input[type=checkbox]').forEach(c => c.checked = false);
    });
    document.getElementById('col-reset').addEventListener('click', () => {
        ALL_COLS.forEach(col => {
            const chk = document.querySelector(`#oc-modal input[data-col-key="${col.key}"]`);
            if (chk) chk.checked = col.default;
        });
    });
    document.getElementById('col-save').addEventListener('click', () => {
        colVis = getModalValues(); saveVisibility(colVis); applyColumnVisibility();
    });
    document.getElementById('col-ok').addEventListener('click', () => {
        colVis = getModalValues(); saveVisibility(colVis); applyColumnVisibility(); closeModal();
    });

    // ── Helpers ──────────────────────────────────────────────────────────────
    function showLoading(on) {
        loading.classList.toggle('hidden', !on);
        tbody.classList.toggle('hidden', on);
    }
    function showError(msg) { errBar.textContent = msg; errBar.classList.remove('hidden'); }
    function hideError()    { errBar.classList.add('hidden'); }

    // ── Init ──────────────────────────────────────────────────────────────────
    applyColumnOrder();   // restore saved order in header
    initDrag();           // attach drag listeners
    applyColumnVisibility();
    fetchData();          // auto-load on open

})();
</script>

<style>
/* ── Build-up badges ─────────────────────────────────────────────── */
.build-badge     { display:inline-block; padding:1px 5px; border-radius:4px; font-size:10px; font-weight:700; font-family:sans-serif; }
.lb-badge        { background:#16a34a; color:#fff; }
.sb-badge        { background:#dc2626; color:#fff; }
.sc-badge        { background:#2563eb; color:#fff; }
.lu-badge        { background:#f59e0b; color:#fff; }
.neutral-badge   { color:#9ca3af; }

/* ── Row base ────────────────────────────────────────────────────── */
.row-even        { background:#ffffff; }
.row-odd         { background:#f9fafb; }
.oc-row:hover    { background:#fefce8 !important; }

/* ── ATM full-row highlight ──────────────────────────────────────── */
.atm-row         { background:#fffbeb !important; border-left:4px solid #f59e0b !important; }
.atm-row td.oc-td { background: rgba(251,191,36,0.12); }
.atm-row:hover   { background:#fef3c7 !important; }
.atm-row:hover td.oc-td { background: rgba(251,191,36,0.18); }

/* ── ATM strike cell ─────────────────────────────────────────────── */
.atm-strike-cell {
    background: linear-gradient(135deg,#f59e0b,#d97706) !important;
    color: #fff !important;
    border-color: #b45309 !important;
    border-width: 2px !important;
    font-size: 13px;
}
.atm-arrow {
    color: #fff;
    font-size: 10px;
    animation: pulse-arrow 1s infinite;
}
@keyframes pulse-arrow {
    0%,100% { opacity:1; transform:translateX(0); }
    50%      { opacity:.5; transform:translateX(2px); }
}

/* ── Highlights (Top Precedence over ATM & Hover) ────────────────── */
/* Highest OI (Solid Deep Emerald Green) */
.oc-td.hl-oi,
.atm-row td.oc-td.hl-oi,
.atm-row:hover td.oc-td.hl-oi {
    background: #047857 !important;
    color: #ffffff !important;
    font-weight: 800 !important;
    box-shadow: inset 0 0 0 2px #065f46, 0 1px 3px rgba(0,0,0,0.3) !important;
    border-radius: 3px !important;
}

/* Top OI Chng + (Vivid Green) */
.oc-td.hl-max,
.atm-row td.oc-td.hl-max,
.atm-row:hover td.oc-td.hl-max {
    background: #16a34a !important;
    color: #ffffff !important;
    font-weight: 800 !important;
    box-shadow: inset 0 0 0 2px #15803d, 0 1px 3px rgba(0,0,0,0.3) !important;
    border-radius: 3px !important;
}

/* Top OI Chng - (Vivid Red) */
.oc-td.hl-min,
.atm-row td.oc-td.hl-min,
.atm-row:hover td.oc-td.hl-min {
    background: #dc2626 !important;
    color: #ffffff !important;
    font-weight: 800 !important;
    box-shadow: inset 0 0 0 2px #991b1b, 0 1px 3px rgba(0,0,0,0.3) !important;
    border-radius: 3px !important;
}

/* Top Volume (Navy Blue) */
.oc-td.hl-vol,
.atm-row td.oc-td.hl-vol,
.atm-row:hover td.oc-td.hl-vol {
    background: #1e3a8a !important;
    color: #ffffff !important;
    font-weight: 800 !important;
    box-shadow: inset 0 0 0 2px #172554, 0 1px 3px rgba(0,0,0,0.3) !important;
    border-radius: 3px !important;
}

/* ── Dragging state ──────────────────────────────────────────────── */
.drag-handle     { user-select: none; }
.drag-handle:hover { background: #fef9c3 !important; cursor: grab; }
.dragging        { opacity: .4; background: #dbeafe !important; }
.drag-over       { background: #fef08a !important; border: 2px dashed #f59e0b !important; }

/* ── Column cells ────────────────────────────────────────────────── */
.oc-td           { white-space: nowrap; padding: 2px 6px; }

/* ── Scrollbar ───────────────────────────────────────────────────── */
#oc-table-wrap   { scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }
#oc-table-wrap::-webkit-scrollbar       { width: 6px; height: 6px; }
#oc-table-wrap::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:3px; }
</style>
@endpush

@endsection
