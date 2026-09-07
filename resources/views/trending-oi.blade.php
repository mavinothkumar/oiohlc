@extends('layouts.app')

@section('title', 'Trending OI - Options Analysis')

@section('content')
<div class="w-full text-gray-800" id="trending-oi-root">

    {{-- ══════════ TOP CONTROLS ══════════ --}}
    <div class="bg-white border-b border-gray-200 px-4 py-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 text-sm shadow-sm">

        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            {{-- Mode --}}
            <div class="flex items-center gap-3 mr-1">
                <label class="flex items-center gap-1.5 cursor-pointer font-semibold text-xs">
                    <input type="radio" name="mode" value="live" id="toi-mode-live" class="accent-red-600">
                    <span class="text-red-600 flex items-center gap-1">● Live data</span>
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer text-xs">
                    <input type="radio" name="mode" value="history" id="toi-mode-history">
                    <span class="text-gray-600">Historical</span>
                </label>
            </div>

            {{-- Name / Underlying --}}
            <div class="flex items-center gap-1">
                <label class="text-gray-500 text-xs font-semibold">Name</label>
                <select id="toi-underlying" class="border border-gray-300 rounded px-2 py-1 text-xs bg-white min-w-[100px]">
                    <option value="NSE_INDEX|Nifty 50">NIFTY</option>
                </select>
            </div>

            {{-- Date (Historical Mode) --}}
            <div class="flex items-center gap-1" id="toi-date-wrapper">
                <label class="text-gray-500 text-xs font-semibold">Date</label>
                <input type="date" id="toi-date" value="{{ $selectedDate }}"
                       class="border border-gray-300 rounded px-2 py-1 text-xs bg-white">
            </div>

            {{-- Expiry Date --}}
            <div class="flex items-center gap-1">
                <label class="text-gray-500 text-xs font-semibold">Expiry Date</label>
                <select id="toi-expiry" class="border border-gray-300 rounded px-2 py-1 text-xs bg-white min-w-[110px]">
                    @foreach($expiries as $exp)
                        <option value="{{ $exp }}" {{ $exp === $selectedExpiry ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::parse($exp)->format('d-M-Y') }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Time Interval --}}
            <div class="flex items-center gap-1">
                <label class="text-gray-500 text-xs font-semibold">Time Interval</label>
                <select id="toi-interval" class="border border-gray-300 rounded px-2 py-1 text-xs bg-white">
                    <option value="1">1 min</option>
                    <option value="3">3 min</option>
                    <option value="5" selected>5 min</option>
                    <option value="15">15 min</option>
                </select>
            </div>

            {{-- Go Button --}}
            <button id="toi-go" class="bg-red-700 hover:bg-red-800 text-white font-bold px-4 py-1.5 rounded text-xs shadow transition-colors">
                Go
            </button>

            {{-- Change Strike Prices Button --}}
            <button id="toi-strikes-btn"
                    class="border border-red-700 text-red-700 hover:bg-red-50 font-semibold px-3 py-1.5 rounded text-xs transition-colors">
                Change Strike Prices
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-4 text-xs font-medium text-gray-700">
            {{-- Show Graph View Checkbox --}}
            <label class="flex items-center gap-1.5 cursor-pointer select-none font-semibold text-gray-800">
                <input type="checkbox" id="toi-toggle-graph" class="accent-red-600 rounded">
                <span>Show Graph View</span>
            </label>

            {{-- Graph Height Selector (visible when graph is enabled) --}}
            <div id="toi-graph-height-wrapper" class="hidden items-center gap-1">
                <label class="text-gray-500 text-[11px] font-semibold">Graph Size</label>
                <select id="toi-graph-height" class="border border-gray-300 rounded px-1.5 py-0.5 text-xs bg-white">
                    <option value="420">Medium (420px)</option>
                    <option value="520" selected>Large (520px)</option>
                    <option value="650">Extra Large (650px)</option>
                </select>
            </div>

            {{-- Show / Hide Table Checkbox --}}
            <label class="flex items-center gap-1.5 cursor-pointer select-none font-semibold text-gray-800">
                <input type="checkbox" id="toi-toggle-table" checked class="accent-red-600 rounded">
                <span>Show Table</span>
            </label>

            {{-- Rows Limit Selector --}}
            <div id="toi-rows-limit-wrapper" class="flex items-center gap-1">
                <label class="text-gray-500 text-[11px] font-semibold">Rows</label>
                <select id="toi-rows-limit" class="border border-gray-300 rounded px-1.5 py-0.5 text-xs bg-white">
                    <option value="15">15 rows</option>
                    <option value="30">30 rows</option>
                    <option value="50">50 rows</option>
                    <option value="100">100 rows</option>
                    <option value="all" selected>All rows</option>
                </select>
            </div>
        </div>
    </div>

    {{-- ══════════ SELECTED STRIKES & SPOT HEADER ══════════ --}}
    <div class="bg-gray-50 border-b border-gray-200 px-4 py-2 flex flex-col md:flex-row md:items-center justify-between gap-2 text-xs">
        <div class="text-gray-700 leading-relaxed font-mono">
            <span class="font-bold text-gray-900 font-sans">Selected Strike Prices:</span>
            <span id="toi-selected-strikes-display" class="text-gray-600 font-medium ml-1">Loading...</span>
        </div>
        <div id="toi-spot-info" class="font-semibold text-gray-800 text-right whitespace-nowrap">
            Underlying: <span id="toi-spot-val" class="text-indigo-700 font-bold">—</span>
        </div>
    </div>

    {{-- ══════════ DUAL GRAPHS (SHOW GRAPH VIEW) ══════════ --}}
    <div id="toi-graph-container" class="hidden p-4 bg-gray-100 border-b border-gray-300">
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            {{-- Graph 1: Trending OI --}}
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between mb-3 border-b border-gray-100 pb-2">
                    <h3 class="text-sm font-bold text-gray-800 tracking-wide flex items-center gap-1.5">
                        📈 Trending OI
                    </h3>
                    <div class="flex flex-wrap items-center gap-3 text-[11px]">
                        <span class="flex items-center gap-1 text-green-700 font-semibold"><span class="w-2.5 h-2.5 rounded-full bg-green-500 inline-block"></span> Change in Call OI</span>
                        <span class="flex items-center gap-1 text-red-600 font-semibold"><span class="w-2.5 h-2.5 rounded-full bg-red-500 inline-block"></span> Change in Put OI</span>
                        <span class="flex items-center gap-1 text-cyan-600 font-semibold"><span class="w-3 h-0.5 border-t-2 border-dashed border-cyan-500 inline-block"></span> Spot Price</span>
                    </div>
                </div>
                <div id="toi-chart-oi-wrapper" class="relative w-full h-[520px]">
                    <canvas id="toi-chart-oi"></canvas>
                </div>
            </div>

            {{-- Graph 2: Trending OI Sentiment --}}
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between mb-3 border-b border-gray-100 pb-2">
                    <h3 class="text-sm font-bold text-gray-800 tracking-wide flex items-center gap-1.5">
                        📊 Trending OI Sentiment
                    </h3>
                    <div class="flex flex-wrap items-center gap-3 text-[11px]">
                        <span class="flex items-center gap-1 text-red-600 font-semibold"><span class="w-2.5 h-2.5 rounded-full bg-red-500 inline-block"></span> Sentiment (Diff in OI)</span>
                        <span class="flex items-center gap-1 text-cyan-600 font-semibold"><span class="w-3 h-0.5 border-t-2 border-dashed border-cyan-500 inline-block"></span> Spot Price</span>
                    </div>
                </div>
                <div id="toi-chart-sentiment-wrapper" class="relative w-full h-[520px]">
                    <canvas id="toi-chart-sentiment"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════ LOADING INDICATOR ══════════ --}}
    <div id="toi-loading" class="text-center py-12 text-gray-500 text-sm hidden">
        <svg class="animate-spin inline h-6 w-6 mr-2 text-red-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        Fetching Trending OI data…
    </div>

    {{-- ══════════ ERROR BAR ══════════ --}}
    <div id="toi-error" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-2 text-sm rounded mx-4 my-2"></div>

    {{-- ══════════ DATA TABLE CONTAINER ══════════ --}}
    <div id="toi-table-container" class="w-full">
        <div class="overflow-x-auto w-full" id="toi-table-wrap" style="max-height: calc(100vh - 170px); overflow-y: auto;">
            <table id="toi-table" class="w-full border-collapse text-[11px] font-mono whitespace-nowrap text-right" style="min-width: 1450px;">
                <thead class="sticky top-0 z-20 bg-gray-50 text-gray-600 font-bold border-b border-gray-300 shadow-sm">
                    <tr class="h-9">
                        <th class="py-1 px-2 text-center border-r border-gray-200 w-10">#</th>
                        <th class="py-1 px-2 text-center border-r border-gray-200 w-24">Date</th>
                        <th class="py-1 px-2 text-center border-r border-gray-200 w-20">Time</th>
                        <th class="py-1 px-2 text-center border-r border-gray-200 w-28">Day H/L Break</th>
                        <th class="py-1 px-2 text-right border-r border-gray-200">Chng. In Call OI</th>
                        <th class="py-1 px-2 text-right border-r border-gray-200">Chng. In Put OI</th>
                        <th class="py-1 px-2 text-right border-r border-gray-200">Diff. in OI</th>
                        <th class="py-1 px-2 text-center border-r border-gray-200 w-20">Strength</th>
                        <th class="py-1 px-2 text-center border-r border-gray-200 w-24">Direction of chng.</th>
                        <th class="py-1 px-2 text-right border-r border-gray-200">Chng. In Direction</th>
                        <th class="py-1 px-2 text-right border-r border-gray-200">Total Call Ltp</th>
                        <th class="py-1 px-2 text-right border-r border-gray-200">Call ltp chng.</th>
                        <th class="py-1 px-2 text-right border-r border-gray-200">CE + PE ltp Chng.</th>
                        <th class="py-1 px-2 text-right border-r border-gray-200">Put ltp chng.</th>
                        <th class="py-1 px-2 text-right border-r border-gray-200">Total Put Ltp</th>
                        <th class="py-1 px-2 text-center border-r border-gray-200 w-16">Net PCR</th>
                        <th class="py-1 px-2 text-center border-r border-gray-200 w-24">Day H/L Diff. in OI</th>
                        <th class="py-1 px-2 text-center w-20">Sentiment</th>
                    </tr>
                </thead>
                <tbody id="toi-tbody">
                    <tr>
                        <td colspan="18" class="text-center py-10 text-gray-400 font-sans text-xs">
                            Loading Trending OI data…
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="px-4 py-2 bg-gray-50 border-t border-gray-200 text-gray-500 text-xs flex justify-between items-center font-sans">
            <span id="toi-table-count-summary">Showing 0 rows</span>
            <span class="text-[11px] text-gray-400">Trending OI Table</span>
        </div>
    </div>

</div>

{{-- ══════════ STRIKE PRICES SELECTION MODAL ══════════ --}}
<div id="toi-strike-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-lg shadow-2xl w-[680px] max-w-full max-h-[90vh] flex flex-col relative overflow-hidden">
        {{-- Header --}}
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-base font-bold text-gray-800">Select strike prices</h2>
            <button id="toi-modal-close" class="text-gray-400 hover:text-gray-700 text-xl font-bold leading-none">&times;</button>
        </div>

        {{-- Body --}}
        <div class="p-6 overflow-y-auto flex-1 space-y-4 text-xs">
            <div>
                <span class="font-semibold text-gray-700">Total Strike Prices:</span>
                <span id="toi-modal-total-count" class="font-bold text-red-700 ml-1">0</span>
            </div>

            <div>
                <span class="font-semibold text-gray-700 block mb-1">Currently Selected Strike Prices:</span>
                <div id="toi-modal-selected-summary" class="text-gray-600 font-mono text-[11px] leading-relaxed bg-gray-50 p-2 rounded border border-gray-200 max-h-20 overflow-y-auto">
                    —
                </div>
            </div>

            {{-- Quick action buttons --}}
            <div class="flex flex-wrap gap-2 pt-1">
                <button id="toi-modal-clear" class="bg-red-700 hover:bg-red-800 text-white font-semibold px-3 py-1.5 rounded text-xs transition-colors shadow-sm">
                    Clear all selection
                </button>
                <button id="toi-modal-reset" class="bg-red-700 hover:bg-red-800 text-white font-semibold px-3 py-1.5 rounded text-xs transition-colors shadow-sm">
                    Reset strike prices (15 ATM)
                </button>
                <button id="toi-modal-select-all" class="bg-red-700 hover:bg-red-800 text-white font-semibold px-3 py-1.5 rounded text-xs transition-colors shadow-sm">
                    Select All strike prices
                </button>
            </div>

            <hr class="border-gray-200">

            {{-- Checkbox grid --}}
            <div>
                <p class="font-semibold text-gray-700 mb-2">Select Strike Prices:</p>
                <div id="toi-modal-grid" class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-2 max-h-60 overflow-y-auto p-1 font-mono text-[11px]">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="px-6 py-3 border-t border-gray-200 flex justify-end gap-3 bg-gray-50">
            <button id="toi-modal-cancel" class="border border-gray-300 text-gray-700 hover:bg-gray-100 px-4 py-1.5 rounded text-xs font-semibold">
                Cancel
            </button>
            <button id="toi-modal-apply" class="bg-red-700 hover:bg-red-800 text-white px-5 py-1.5 rounded text-xs font-bold shadow-sm">
                OK
            </button>
        </div>
    </div>
</div>

@push('styles')
<style>
/* ── Badges ──────────────────────────────────────────────────────── */
.badge-dlb { background: #dc2626; color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 10px; display: inline-block; }
.badge-dhb { background: #16a34a; color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 10px; display: inline-block; }

.badge-sentiment-bearish { background: #dc2626; color: #fff; padding: 3px 8px; border-radius: 4px; font-weight: 800; font-size: 10px; display: inline-block; }
.badge-sentiment-bullish { background: #16a34a; color: #fff; padding: 3px 8px; border-radius: 4px; font-weight: 800; font-size: 10px; display: inline-block; }

.badge-strength-neg { background: #dc2626; color: #fff; padding: 2px 8px; border-radius: 12px; font-weight: 700; font-size: 10px; display: inline-flex; align-items: center; gap: 4px; }
.badge-strength-pos { background: #16a34a; color: #fff; padding: 2px 8px; border-radius: 12px; font-weight: 700; font-size: 10px; display: inline-flex; align-items: center; gap: 4px; }

.badge-direction-down { background: #dc2626; color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 10px; display: inline-block; }
.badge-direction-up   { background: #16a34a; color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 10px; display: inline-block; }

/* ── Table rows ─────────────────────────────────────────────────── */
.toi-row:hover { background-color: #fefce8 !important; }
.toi-row-even  { background-color: #ffffff; }
.toi-row-odd   { background-color: #f9fafb; }
.toi-td        { padding: 4px 8px; border-right: 1px solid #f1f5f9; white-space: nowrap; }

/* ── Custom Scrollbar ────────────────────────────────────────────── */
#toi-table-wrap { scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }
#toi-table-wrap::-webkit-scrollbar       { width: 6px; height: 6px; }
#toi-table-wrap::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';

    // ── State ───────────────────────────────────────────────────────────────
    let currentSelectedStrikes = [];
    let currentAllStrikes = [];
    let currentAtmStrike = null;
    let oiChartInstance = null;
    let sentimentChartInstance = null;
    let autoRefreshTimer = null;
    let lastRawRows = [];

    // ── DOM References ──────────────────────────────────────────────────────
    const modeLive           = document.getElementById('toi-mode-live');
    const modeHist           = document.getElementById('toi-mode-history');
    const dateWrapper        = document.getElementById('toi-date-wrapper');
    const dateInput          = document.getElementById('toi-date');
    const expirySelect       = document.getElementById('toi-expiry');
    const intervalSelect     = document.getElementById('toi-interval');
    const underlyingSelect   = document.getElementById('toi-underlying');
    const goBtn              = document.getElementById('toi-go');
    const strikesBtn         = document.getElementById('toi-strikes-btn');
    const toggleGraph        = document.getElementById('toi-toggle-graph');
    const graphContainer     = document.getElementById('toi-graph-container');
    const graphHeightWrapper = document.getElementById('toi-graph-height-wrapper');
    const graphHeightSelect  = document.getElementById('toi-graph-height');
    const toggleTable        = document.getElementById('toi-toggle-table');
    const tableContainer     = document.getElementById('toi-table-container');
    const rowsLimitSelect    = document.getElementById('toi-rows-limit');
    const tableCountSummary  = document.getElementById('toi-table-count-summary');
    const loadingEl          = document.getElementById('toi-loading');
    const errorEl            = document.getElementById('toi-error');
    const tbody              = document.getElementById('toi-tbody');
    const strikesDisplay     = document.getElementById('toi-selected-strikes-display');
    const spotVal            = document.getElementById('toi-spot-val');

    // Modal DOM
    const modal            = document.getElementById('toi-strike-modal');
    const modalClose       = document.getElementById('toi-modal-close');
    const modalCancel      = document.getElementById('toi-modal-cancel');
    const modalApply       = document.getElementById('toi-modal-apply');
    const modalClear       = document.getElementById('toi-modal-clear');
    const modalReset       = document.getElementById('toi-modal-reset');
    const modalSelectAll   = document.getElementById('toi-modal-select-all');
    const modalTotalCount  = document.getElementById('toi-modal-total-count');
    const modalSummary     = document.getElementById('toi-modal-selected-summary');
    const modalGrid        = document.getElementById('toi-modal-grid');

    // ── Mode Toggle ─────────────────────────────────────────────────────────
    function getMode() { return modeLive.checked ? 'live' : 'history'; }

    function applyModeUI() {
        const isLive = getMode() === 'live';
        dateWrapper.style.opacity = isLive ? '0.4' : '1';
        dateWrapper.style.pointerEvents = isLive ? 'none' : '';
    }

    modeLive.addEventListener('change', () => { applyModeUI(); reloadExpiries(); });
    modeHist.addEventListener('change', () => { applyModeUI(); reloadExpiries(); });

    @if($mode === 'history')
    modeHist.checked = true;
    @else
    modeLive.checked = true;
    @endif
    applyModeUI();

    // ── Graph Height & Persistence ──────────────────────────────────────────
    const LS_GRAPH_HEIGHT_KEY = 'toi_graph_height_v2';
    const savedGraphHeight = localStorage.getItem(LS_GRAPH_HEIGHT_KEY);
    if (savedGraphHeight) {
        graphHeightSelect.value = savedGraphHeight;
    }

    function applyGraphHeight() {
        const h = (graphHeightSelect.value || '520') + 'px';
        const oiWrap = document.getElementById('toi-chart-oi-wrapper');
        const senWrap = document.getElementById('toi-chart-sentiment-wrapper');
        if (oiWrap) oiWrap.style.height = h;
        if (senWrap) senWrap.style.height = h;
        if (oiChartInstance) oiChartInstance.resize();
        if (sentimentChartInstance) sentimentChartInstance.resize();
    }

    graphHeightSelect.addEventListener('change', () => {
        localStorage.setItem(LS_GRAPH_HEIGHT_KEY, graphHeightSelect.value);
        applyGraphHeight();
    });

    // ── Graph View Toggle & Persistence ─────────────────────────────────────
    const LS_GRAPH_KEY = 'toi_show_graph_view_v2';
    const isGraphSaved = localStorage.getItem(LS_GRAPH_KEY);
    // If user previously checked or not, honor it (default is open if saved true)
    if (isGraphSaved === 'true') {
        toggleGraph.checked = true;
        graphContainer.classList.remove('hidden');
        graphHeightWrapper.classList.remove('hidden');
        graphHeightWrapper.classList.add('flex');
    }

    toggleGraph.addEventListener('change', () => {
        const isChecked = toggleGraph.checked;
        graphContainer.classList.toggle('hidden', !isChecked);
        graphHeightWrapper.classList.toggle('hidden', !isChecked);
        graphHeightWrapper.classList.toggle('flex', isChecked);
        localStorage.setItem(LS_GRAPH_KEY, isChecked ? 'true' : 'false');
        if (isChecked) {
            setTimeout(() => {
                applyGraphHeight();
            }, 50);
        }
    });

    // ── Show / Hide Table Persistence ───────────────────────────────────────
    const LS_TABLE_KEY = 'toi_show_table_v2';
    const isTableSaved = localStorage.getItem(LS_TABLE_KEY);
    if (isTableSaved !== null) {
        toggleTable.checked = isTableSaved === 'true';
        tableContainer.classList.toggle('hidden', !toggleTable.checked);
    }

    toggleTable.addEventListener('change', () => {
        const isChecked = toggleTable.checked;
        tableContainer.classList.toggle('hidden', !isChecked);
        localStorage.setItem(LS_TABLE_KEY, isChecked ? 'true' : 'false');
    });

    // ── Rows Limit Persistence ──────────────────────────────────────────────
    const LS_ROWS_LIMIT_KEY = 'toi_rows_limit_v2';
    const savedRowsLimit = localStorage.getItem(LS_ROWS_LIMIT_KEY);
    if (savedRowsLimit) {
        rowsLimitSelect.value = savedRowsLimit;
    }

    rowsLimitSelect.addEventListener('change', () => {
        localStorage.setItem(LS_ROWS_LIMIT_KEY, rowsLimitSelect.value);
        renderTable(lastRawRows);
    });

    // ── Reload Expiries ─────────────────────────────────────────────────────
    function reloadExpiries() {
        const url = `{{ route('api.trending-oi.expiries') }}?mode=${getMode()}&date=${dateInput.value}&underlying=${underlyingSelect.value}`;
        fetch(url).then(r => r.json()).then(data => {
            const expiries = data.expiries || [];
            const currentExp = expirySelect.value;
            expirySelect.innerHTML = '';
            expiries.forEach(exp => {
                const opt = document.createElement('option');
                opt.value = exp;
                const d = new Date(exp);
                opt.textContent = d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
                if (exp === currentExp) opt.selected = true;
                expirySelect.appendChild(opt);
            });
            if (expiries.length && !expiries.includes(currentExp)) {
                expirySelect.value = expiries[0];
            }
        }).catch(() => {});
    }
    dateInput.addEventListener('change', reloadExpiries);

    // ── Fetch Data ──────────────────────────────────────────────────────────
    function fetchData() {
        clearAutoRefresh();
        const expiry = expirySelect.value;
        if (!expiry) { showError('Please select an expiry date.'); return; }

        showLoading(true);
        hideError();

        const params = new URLSearchParams({
            mode: getMode(),
            expiry: expiry,
            date: dateInput.value,
            underlying: underlyingSelect.value,
            interval: intervalSelect.value,
        });

        if (currentSelectedStrikes && currentSelectedStrikes.length > 0) {
            params.append('strikes', currentSelectedStrikes.join(','));
        }

        fetch(`{{ route('api.trending-oi.data') }}?${params}`)
            .then(r => r.json())
            .then(data => {
                showLoading(false);
                if (data.error) { showError(data.error); return; }

                currentSelectedStrikes = data.selected_strikes || [];
                currentAllStrikes      = data.all_strikes || [];
                currentAtmStrike       = data.atm_strike;

                renderHeaderInfo(data);
                renderTable(data.rows || []);
                renderCharts(data.chart || {});

                if (getMode() === 'live') scheduleAutoRefresh();
            })
            .catch(err => {
                showLoading(false);
                showError('Failed to load Trending OI data: ' + err.message);
            });
    }

    goBtn.addEventListener('click', fetchData);
    intervalSelect.addEventListener('change', fetchData);

    // ── Auto Refresh ─────────────────────────────────────────────────────────
    function scheduleAutoRefresh() {
        clearAutoRefresh();
        autoRefreshTimer = setTimeout(() => {
            if (getMode() === 'live') fetchData();
        }, 60000);
    }
    function clearAutoRefresh() {
        if (autoRefreshTimer) { clearTimeout(autoRefreshTimer); autoRefreshTimer = null; }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────
    function fmt(n, decimals = 0) {
        if (n === null || n === undefined) return '—';
        return Number(n).toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    }

    function fmtSigned(n, decimals = 0) {
        if (n === null || n === undefined) return '—';
        const v = Number(n);
        return (v > 0 ? '+' : '') + v.toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    }

    function showLoading(on) {
        loadingEl.classList.toggle('hidden', !on);
        tbody.classList.toggle('hidden', on);
    }
    function showError(msg) {
        errorEl.textContent = msg;
        errorEl.classList.remove('hidden');
    }
    function hideError() { errorEl.classList.add('hidden'); }

    // ── Render Header Info ───────────────────────────────────────────────────
    function renderHeaderInfo(data) {
        // Selected Strikes display
        if (currentSelectedStrikes && currentSelectedStrikes.length > 0) {
            strikesDisplay.textContent = currentSelectedStrikes.join(', ');
        } else {
            strikesDisplay.textContent = 'None';
        }

        // Spot info
        const u = data.underlying_data;
        if (u) {
            const chgSign = u.change >= 0 ? '+' : '';
            const chgColor = u.change >= 0 ? 'text-green-600' : 'text-red-600';
            spotVal.innerHTML = `<span class="text-gray-900">${u.name}</span> at <span class="text-indigo-700">${fmt(u.spot, 2)}</span>, Chg: <span class="${chgColor}">${chgSign}${fmt(u.change, 2)} (${chgSign}${u.change_pct}%)</span> as on <span class="text-gray-500 font-normal">${u.time}</span>`;
        } else {
            spotVal.textContent = '—';
        }
    }

    // ── Render Table Rows (with limit slicing) ──────────────────────────────
    function renderTable(rows) {
        lastRawRows = rows || [];
        if (!rows || rows.length === 0) {
            tbody.innerHTML = `<tr><td colspan="18" class="text-center py-10 text-gray-400 font-sans text-xs">No records found for the selected parameters.</td></tr>`;
            if (tableCountSummary) tableCountSummary.textContent = 'Showing 0 rows';
            return;
        }

        let displayRows = rows;
        const limitVal = rowsLimitSelect.value;
        if (limitVal !== 'all') {
            const limitNum = parseInt(limitVal, 10);
            displayRows = rows.slice(0, limitNum);
        }

        if (tableCountSummary) {
            tableCountSummary.textContent = `Showing ${displayRows.length} of ${rows.length} rows (latest first)`;
        }

        let html = '';
        displayRows.forEach((r, idx) => {
            const rowCls = (idx % 2 === 0) ? 'toi-row-even' : 'toi-row-odd';

            // Day H/L Break badge
            let dayHlBadge = '-';
            if (r.day_hl_break && r.day_hl_break !== '-') {
                const isHigh = r.day_hl_break.startsWith('D.H.B');
                dayHlBadge = `<span class="${isHigh ? 'badge-dhb' : 'badge-dlb'}">${r.day_hl_break}</span>`;
            }

            // Strength Badge
            const strengthVal = r.strength;
            const strengthCls = strengthVal >= 0 ? 'badge-strength-pos' : 'badge-strength-neg';
            const strengthBadge = `<span class="${strengthCls}">${strengthVal}% <span class="opacity-75">•••</span></span>`;

            // Direction of Change
            const dirVal = r.direction_pct;
            let dirBadge = '—';
            if (dirVal !== 0) {
                const isUp = dirVal > 0;
                dirBadge = `<span class="${isUp ? 'badge-direction-up' : 'badge-direction-down'}">${isUp ? '↑' : '↓'} ${fmt(Math.abs(dirVal), 2)}%</span>`;
            }

            // Day H/L Diff in OI Badge
            let dayHlDiffBadge = '-';
            if (r.day_hl_diff_oi && r.day_hl_diff_oi !== '-') {
                const isHigh = r.day_hl_diff_oi === 'D.H.B';
                dayHlDiffBadge = `<span class="${isHigh ? 'badge-dhb' : 'badge-dlb'}">${r.day_hl_diff_oi}</span>`;
            }

            // Sentiment Badge
            const isBullish = r.sentiment === 'Bullish';
            const sentimentBadge = `<span class="${isBullish ? 'badge-sentiment-bullish' : 'badge-sentiment-bearish'}">${r.sentiment}</span>`;

            // Diff in OI text color
            const diffColor = r.diff_oi >= 0 ? 'text-green-700' : 'text-red-600 font-semibold';
            const callLtpColor = r.call_ltp_chg >= 0 ? 'text-green-700' : 'text-red-600';
            const putLtpColor = r.put_ltp_chg >= 0 ? 'text-green-700' : 'text-red-600';
            const cePeLtpColor = r.ce_pe_ltp_chg >= 0 ? 'text-green-700' : 'text-red-600';

            html += `<tr class="${rowCls} toi-row border-b border-gray-200 transition-colors h-8">
                <td class="toi-td text-center text-gray-500 font-sans">${idx + 1}</td>
                <td class="toi-td text-center text-gray-700">${r.date}</td>
                <td class="toi-td text-center font-bold text-gray-800">${r.time}</td>
                <td class="toi-td text-center">${dayHlBadge}</td>
                <td class="toi-td text-gray-800 font-semibold">${fmt(r.chng_call_oi)}</td>
                <td class="toi-td text-gray-800 font-semibold">${fmt(r.chng_put_oi)}</td>
                <td class="toi-td ${diffColor}">${fmtSigned(r.diff_oi)}</td>
                <td class="toi-td text-center">${strengthBadge}</td>
                <td class="toi-td text-center">${dirBadge}</td>
                <td class="toi-td font-semibold ${r.chng_in_direction >= 0 ? 'text-green-700' : 'text-red-600'}">${fmtSigned(r.chng_in_direction)}</td>
                <td class="toi-td text-gray-700">${fmt(r.total_call_ltp, 2)}</td>
                <td class="toi-td ${callLtpColor}">${fmtSigned(r.call_ltp_chng, 2)}</td>
                <td class="toi-td ${cePeLtpColor} font-bold">${fmtSigned(r.ce_pe_ltp_chng, 2)}</td>
                <td class="toi-td ${putLtpColor}">${fmtSigned(r.put_ltp_chng, 2)}</td>
                <td class="toi-td text-gray-700">${fmt(r.total_put_ltp, 2)}</td>
                <td class="toi-td text-center font-bold text-indigo-900">${fmt(r.net_pcr, 2)}</td>
                <td class="toi-td text-center">${dayHlDiffBadge}</td>
                <td class="toi-td text-center">${sentimentBadge}</td>
            </tr>`;
        });

        tbody.innerHTML = html;
    }

    // ── Render Charts ────────────────────────────────────────────────────────
    function renderCharts(chartData) {
        applyGraphHeight();
        const labels    = chartData.labels || [];
        const callOi    = chartData.call_oi || [];
        const putOi     = chartData.put_oi || [];
        const sentiment = chartData.sentiment || [];
        const spot      = chartData.spot || [];

        // 1. Trending OI Chart
        const oiCtx = document.getElementById('toi-chart-oi');
        if (oiChartInstance) oiChartInstance.destroy();

        oiChartInstance = new Chart(oiCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Change in Call OI',
                        data: callOi,
                        borderColor: '#16a34a', // Green
                        backgroundColor: 'rgba(22, 163, 74, 0.05)',
                        borderWidth: 2,
                        pointRadius: 2,
                        tension: 0.2,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Change in Put OI',
                        data: putOi,
                        borderColor: '#dc2626', // Red
                        backgroundColor: 'rgba(220, 38, 38, 0.05)',
                        borderWidth: 2,
                        pointRadius: 2,
                        tension: 0.2,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Spot Price',
                        data: spot,
                        borderColor: '#06b6d4', // Cyan
                        borderDash: [4, 4],
                        borderWidth: 1.5,
                        pointRadius: 0,
                        tension: 0.1,
                        yAxisID: 'y1',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.dataset.yAxisID === 'y1') {
                                    label += Number(context.parsed.y).toFixed(2);
                                } else {
                                    label += fmt(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10, family: 'monospace' } }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        ticks: {
                            font: { size: 10, family: 'monospace' },
                            callback: function(value) { return fmt(value); }
                        },
                        grid: { color: 'rgba(226, 232, 240, 0.6)' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: {
                            font: { size: 10, family: 'monospace' },
                            callback: function(value) { return Number(value).toFixed(0); }
                        }
                    }
                }
            }
        });

        // 2. Trending OI Sentiment Chart
        const sentimentCtx = document.getElementById('toi-chart-sentiment');
        if (sentimentChartInstance) sentimentChartInstance.destroy();

        sentimentChartInstance = new Chart(sentimentCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Sentiment (Diff in OI)',
                        data: sentiment,
                        borderColor: '#dc2626',
                        backgroundColor: 'rgba(220, 38, 38, 0.05)',
                        borderWidth: 2,
                        pointRadius: 2,
                        tension: 0.2,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Spot Price',
                        data: spot,
                        borderColor: '#06b6d4',
                        borderDash: [4, 4],
                        borderWidth: 1.5,
                        pointRadius: 0,
                        tension: 0.1,
                        yAxisID: 'y1',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.dataset.yAxisID === 'y1') {
                                    label += Number(context.parsed.y).toFixed(2);
                                } else {
                                    label += fmt(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10, family: 'monospace' } }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        ticks: {
                            font: { size: 10, family: 'monospace' },
                            callback: function(value) { return fmt(value); }
                        },
                        grid: { color: 'rgba(226, 232, 240, 0.6)' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: {
                            font: { size: 10, family: 'monospace' },
                            callback: function(value) { return Number(value).toFixed(0); }
                        }
                    }
                }
            }
        });

        if (toggleGraph.checked) {
            setTimeout(() => {
                if (oiChartInstance) oiChartInstance.resize();
                if (sentimentChartInstance) sentimentChartInstance.resize();
            }, 50);
        }
    }

    // ── Strike Selection Modal ───────────────────────────────────────────────
    function buildModalGrid() {
        modalGrid.innerHTML = '';
        currentAllStrikes.forEach(s => {
            const isChecked = currentSelectedStrikes.includes(s);
            const label = document.createElement('label');
            label.className = 'flex items-center gap-1.5 cursor-pointer bg-white p-1 rounded border border-gray-200 hover:bg-red-50 text-gray-700 select-none';
            label.innerHTML = `<input type="checkbox" value="${s}" ${isChecked ? 'checked' : ''} class="accent-red-600 toi-modal-chk"> <span class="${s === currentAtmStrike ? 'font-bold text-amber-600' : ''}">${s}</span>`;
            modalGrid.appendChild(label);
        });
        updateModalSummary();
    }

    function updateModalSummary() {
        const checked = [...modalGrid.querySelectorAll('.toi-modal-chk:checked')].map(c => parseInt(c.value)).sort((a,b)=>a-b);
        modalTotalCount.textContent = checked.length;
        modalSummary.textContent = checked.length > 0 ? checked.join(', ') : 'None selected';
    }

    modalGrid.addEventListener('change', updateModalSummary);

    strikesBtn.addEventListener('click', () => {
        buildModalGrid();
        modal.classList.replace('hidden', 'flex');
    });

    modalClose.addEventListener('click', closeModal);
    modalCancel.addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

    function closeModal() {
        modal.classList.replace('flex', 'hidden');
    }

    modalClear.addEventListener('click', () => {
        modalGrid.querySelectorAll('.toi-modal-chk').forEach(c => c.checked = false);
        updateModalSummary();
    });

    modalSelectAll.addEventListener('click', () => {
        modalGrid.querySelectorAll('.toi-modal-chk').forEach(c => c.checked = true);
        updateModalSummary();
    });

    modalReset.addEventListener('click', () => {
        // Reset to 15 ATM strikes
        if (currentAtmStrike && currentAllStrikes.length > 0) {
            let atmIdx = currentAllStrikes.indexOf(currentAtmStrike);
            if (atmIdx === -1) atmIdx = Math.floor(currentAllStrikes.length / 2);
            const startIdx = Math.max(0, atmIdx - 7);
            const default15 = currentAllStrikes.slice(startIdx, startIdx + 15);

            modalGrid.querySelectorAll('.toi-modal-chk').forEach(c => {
                const s = parseInt(c.value);
                c.checked = default15.includes(s);
            });
            updateModalSummary();
        }
    });

    modalApply.addEventListener('click', () => {
        const checked = [...modalGrid.querySelectorAll('.toi-modal-chk:checked')].map(c => parseInt(c.value)).sort((a,b)=>a-b);
        if (checked.length === 0) {
            alert('Please select at least 1 strike price.');
            return;
        }
        currentSelectedStrikes = checked;
        closeModal();
        fetchData();
    });

    // ── Initial Data Fetch on Page Load ──────────────────────────────────────
    fetchData();

})();
</script>
@endpush

@endsection
