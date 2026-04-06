@extends('layouts.app')
@section('title', 'Options Chart Step Reader')

@section('content')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <div class="max-w-full mx-auto px-4 py-6">

        <div class="mb-4">
            <h1 class="text-xl font-bold text-gray-800 dark:text-gray-100">Options Chart Step Reader</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Step through each {{ $interval }} candle. Charts grow with each Next click.
            </p>
        </div>

        {{-- Filter Form --}}
        <form id="filterForm" class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 mb-6 flex flex-wrap gap-3 items-end">
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Symbol</label>
                <input type="text" id="symbolInput" value="{{ $symbol }}"
                    class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 w-24 focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Date</label>
                <input type="date" id="dateInput" value="{{ $quote_date }}"
                    class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Expiry</label>
                <input type="text" id="expiryInput" value="{{ $expiry }}" readonly
                    class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 w-32" />
            </div>
            @for($i = 0; $i < 6; $i++)
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Strike {{ $i+1 }}</label>
                    <input type="number" step="50" value="{{ $strikes[$i] ?? '' }}"
                        class="strike-input border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 w-28 focus:ring-2 focus:ring-blue-500 outline-none" />
                </div>
            @endfor
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Saturation ±</label>
                <input type="number" id="saturationInput" value="{{ $saturation }}" step="0.5" min="0"
                    class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 w-20 focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
            <button type="button" id="loadBtn"
                class="self-end px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition">
                Load
            </button>
        </form>

        {{-- Status bar --}}
        <div id="statusBar" class="hidden mb-4 flex items-center gap-3 text-sm">
            <span class="text-gray-500 dark:text-gray-400">Candle</span>
            <span id="slotLabel" class="font-bold text-blue-600 dark:text-blue-400">—</span>
            <span class="text-gray-500 dark:text-gray-400">(</span>
            <span id="slotCounter" class="font-semibold">0</span>
            <span class="text-gray-500 dark:text-gray-400">/</span>
            <span id="totalSlotsEl" class="font-semibold">—</span>
            <span class="text-gray-500 dark:text-gray-400">)</span>
        </div>

        {{-- 2-column chart grid --}}
        <div id="chartsGrid" class="hidden grid grid-cols-1 md:grid-cols-2 gap-6"></div>

    </div>

    {{-- ── Full-screen overlay ── --}}
    {{-- ── Full-screen overlay ── --}}
    <div id="fullscreenOverlay"
        class="hidden fixed inset-0 z-[60] bg-white dark:bg-gray-900 flex flex-col">

        {{-- Header with its own nav buttons --}}
        <div class="flex items-center gap-3 px-5 py-3 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">

            {{-- Strike title --}}
            <span id="fsTitle" class="font-bold text-gray-800 dark:text-gray-100 text-base min-w-[80px]"></span>

            {{-- Badge --}}
            <div id="fsBadge" class="text-xs font-semibold px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-500 whitespace-nowrap"></div>

            {{-- Candle label --}}
            <span class="text-gray-400 dark:text-gray-500 text-sm">Candle:</span>
            <span id="fsSlotLabel" class="font-bold text-blue-600 dark:text-blue-400 text-sm min-w-[40px]">—</span>
            <span id="fsSlotCounter" class="text-gray-500 dark:text-gray-400 text-xs"></span>

            <div class="flex-1"></div>

            {{-- Navigation buttons --}}
            <button id="fsResetBtn" title="Reset to 09:15"
                class="w-8 h-8 rounded-full bg-gray-400 hover:bg-gray-500 text-white flex items-center justify-center transition text-xs">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582M20 20v-5h-.582M5.635 19A9 9 0 104.582 9H4"/>
                </svg>
            </button>

            <button id="fsPrevBtn" title="Previous candle" disabled
                class="w-8 h-8 rounded-full bg-yellow-500 hover:bg-yellow-600 text-white flex items-center justify-center transition disabled:opacity-40">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                </svg>
            </button>

            <button id="fsNextBtn" title="Next candle"
                class="w-8 h-8 rounded-full bg-green-600 hover:bg-green-700 text-white flex items-center justify-center transition disabled:opacity-40">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <button id="fsClose"
                class="ml-2 px-3 py-1.5 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm hover:bg-gray-300 transition">
                ✕ Close
            </button>
        </div>

        <div id="fsChartContainer" class="flex-1 w-full"></div>
    </div>


    {{-- ── Floating buttons ── --}}
    <div id="fab" class="hidden fixed top-4 right-4 z-50 flex flex-col gap-2">
        <button id="resetBtn" title="Reset to 09:15"
            class="w-10 h-10 rounded-full bg-gray-500 hover:bg-gray-600 text-white shadow-lg flex items-center justify-center transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582M20 20v-5h-.582M5.635 19A9 9 0 104.582 9H4"/>
            </svg>
        </button>
        <button id="prevBtn" title="Previous candle" disabled
            class="w-10 h-10 rounded-full bg-yellow-500 hover:bg-yellow-600 text-white shadow-lg flex items-center justify-center transition disabled:opacity-40">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
            </svg>
        </button>
        <button id="nextBtn" title="Next candle"
            class="w-10 h-10 rounded-full bg-green-600 hover:bg-green-700 text-white shadow-lg flex items-center justify-center transition disabled:opacity-40">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
    </div>
@endsection

@push('scripts')
    <script src="https://unpkg.com/lightweight-charts@4.2.0/dist/lightweight-charts.standalone.production.js"></script>
    <script>
        (function () {

            // ── State ─────────────────────────────────────────────────────────────
            const slots = @json($slots);
            let state = {
                symbol: '', date: '', expiry: '',
                strikes: [], saturation: 5,
                currentIndex: -1,
                totalSlots: slots.length,
                lastData: null,           // last API response, for fullscreen sync
                charts: {},               // strike → { chart, ceSeries, peSeries, lines[] }
                fsChart: null,            // fullscreen LWC instance
                fsStrike: null,           // which strike is in fullscreen
                initialized: false,
                fsInitialized: false,
            };

            // ── DOM refs ─────────────────────────────────────────────────────────
            const $ = id => document.getElementById(id);
            const symbolInput     = $('symbolInput');
            const dateInput       = $('dateInput');
            const expiryInput     = $('expiryInput');
            const saturationInput = $('saturationInput');
            const loadBtn         = $('loadBtn');
            const nextBtn         = $('nextBtn');
            const prevBtn         = $('prevBtn');
            const resetBtn        = $('resetBtn');
            const fab             = $('fab');
            const chartsGrid      = $('chartsGrid');
            const statusBar       = $('statusBar');
            const slotLabel       = $('slotLabel');
            const slotCounter     = $('slotCounter');
            const totalSlotsEl    = $('totalSlotsEl');
            const fsOverlay       = $('fullscreenOverlay');
            const fsTitle         = $('fsTitle');
            const fsBadge         = $('fsBadge');
            const fsClose         = $('fsClose');
            const fsContainer     = $('fsChartContainer');

            // ── Fullscreen nav DOM refs ────────────────────────────────────────────
            const fsNextBtn  = $('fsNextBtn');
            const fsPrevBtn  = $('fsPrevBtn');
            const fsResetBtn = $('fsResetBtn');
            const fsSlotLabel   = $('fsSlotLabel');
            const fsSlotCounter = $('fsSlotCounter');


            // Color pairs per strike index [CE, PE]
            const COLORS = [
                ['#2563eb','#dc2626'],['#16a34a','#ea580c'],
                ['#7c3aed','#db2777'],['#0891b2','#d97706'],
                ['#065f46','#9333ea'],['#1e3a8a','#991b1b'],
            ];

            // LWC expects UTC unix seconds. Your DB timestamps are IST (UTC+5:30).
// This converts an "HH:MM" slot label on a given date to correct UTC unix seconds.
            function slotToUtc(dateStr, timeStr) {
                // e.g. dateStr = "2024-01-15", timeStr = "09:15"
                // IST = UTC+5:30, so subtract 5h30m = 19800 seconds
                const [h, m] = timeStr.split(':').map(Number);
                const [y, mo, d] = dateStr.split('-').map(Number);
                // Build UTC time manually: IST HH:MM → UTC = HH:MM - 05:30
                const totalMinutesIST = h * 60 + m;
                const totalMinutesUTC = totalMinutesIST - 330; // 5*60+30
                const utcH = Math.floor(totalMinutesUTC / 60);
                const utcM = totalMinutesUTC % 60;
                return Date.UTC(y, mo - 1, d, utcH, utcM, 0) / 1000;
            }

            function timeToLocal(originalTime) {
                const d = new Date(originalTime * 1000);
                return Date.UTC(
                    d.getFullYear(), d.getMonth(), d.getDate(),
                    d.getHours(), d.getMinutes(), d.getSeconds(),
                    d.getMilliseconds()
                ) / 1000;
            }

            function normalizeCandles(arr) {
                if (!Array.isArray(arr)) return [];
                return arr
                    .filter(c => c.time != null)
                    .map(c => ({
                        time:  timeToLocal(c.time),
                        open:  Number(c.open),
                        high:  Number(c.high),
                        low:   Number(c.low),
                        close: Number(c.close),
                    }))
                    .sort((a, b) => a.time - b.time);
            }




            // ── Shared chart options ──────────────────────────────────────────────
            function chartOpts(container) {
                return {
                    width:  container.clientWidth,
                    height: container.clientHeight || container.clientWidth * 0.45,
                    layout: {
                        background: { color: '#ffffff' },
                        textColor:  '#374151',
                    },
                    grid: {
                        vertLines: false,
                        horzLines: false,
                    },
                    timeScale: {
                        timeVisible:    true,
                        secondsVisible: false,
                        fixLeftEdge:  true,
                        fixRightEdge: false,
                    },
                    crosshair: { mode: LightweightCharts.CrosshairMode.Normal },
                    handleScroll: true,
                    handleScale:  true,
                };
            }

            // ── Auto-fill expiry on date change ───────────────────────────────────
            dateInput.addEventListener('change', async function () {
                const sym = symbolInput.value.trim() || 'NIFTY';
                expiryInput.value = 'Loading…';
                const res  = await fetch(`/api/multi-chart-expiries?underlying_symbol=${sym}&date=${this.value}`);
                const json = await res.json();
                expiryInput.value = json.expiry ?? json.expiries?.[0] ?? '';

                const atm = parseInt(json.open_atm_strike ?? json.atm_strike ?? 0);
                if (atm) {
                    const defaults = [-150,-100,-50,0,50,100].map(o => atm + o);
                    document.querySelectorAll('.strike-input').forEach((el, i) => {
                        el.value = defaults[i] ?? '';
                    });
                }
            });

            // ── Load button ───────────────────────────────────────────────────────
            loadBtn.addEventListener('click', function () {
                const strikes = [...document.querySelectorAll('.strike-input')]
                    .map(el => parseFloat(el.value)).filter(v => !isNaN(v));

                if (!dateInput.value || !expiryInput.value || strikes.length === 0) {
                    alert('Please fill Date, Expiry and at least one Strike.');
                    return;
                }

                state.symbol        = symbolInput.value.trim() || 'NIFTY';
                state.date          = dateInput.value;
                state.expiry        = expiryInput.value;
                state.strikes       = strikes;
                state.saturation    = parseFloat(saturationInput.value) || 5;
                state.currentIndex  = -1;
                state.lastData      = null;
                state.initialized  = false;   // ← reset so next render calls fitContent
                state.fsInitialized = false;

                buildChartCards(strikes);

                fab.classList.remove('hidden');
                statusBar.classList.remove('hidden');
                chartsGrid.classList.remove('hidden');
                totalSlotsEl.textContent = state.totalSlots;
                slotCounter.textContent  = '0';
                slotLabel.textContent    = '—';

                updateButtons();
                loadNext();
            });

            // ── Navigation ────────────────────────────────────────────────────────
            nextBtn.addEventListener('click', loadNext);

            async function loadNext() {
                if (state.currentIndex + 1 >= state.totalSlots) return;
                const data = await fetchSlot(state.currentIndex + 1);
                if (!data) return;
                state.currentIndex++;
                state.lastData = data;
                renderCharts(data);
                updateStatus(data);
                updateButtons();
            }

            prevBtn.addEventListener('click', async function () {
                if (state.currentIndex <= 0) return;
                state.currentIndex--;
                const data = await fetchSlot(state.currentIndex);
                if (!data) return;
                state.lastData = data;
                renderCharts(data);
                updateStatus(data);
                updateButtons();
            });

            resetBtn.addEventListener('click', async function () {
                state.currentIndex  = -1;
                state.initialized   = false;   // ← re-fit after reset
                state.fsInitialized = false;
                const data = await fetchSlot(0);
                if (!data) return;
                state.currentIndex = 0;
                state.lastData = data;
                renderCharts(data);
                updateStatus(data);
                updateButtons();
                updateFsStatus();
                updateFsButtons();
            });


            // ── Fetch slot ────────────────────────────────────────────────────────
            async function fetchSlot(index) {
                const params = new URLSearchParams({
                    symbol: state.symbol, quote_date: state.date,
                    expiry: state.expiry, slot_index: index,
                    saturation: state.saturation,
                });
                state.strikes.forEach(s => params.append('strikes[]', s));
                const res  = await fetch(`{{ route('test.api.chart.step.slot') }}?${params}`);
                const json = await res.json();
                if (json.error) { alert(json.error); return null; }
                return json;
            }

            // ── Build chart cards (once on Load) ─────────────────────────────────
            function buildChartCards(strikes) {
                Object.values(state.charts).forEach(c => c.chart?.remove());
                state.charts = {};
                chartsGrid.innerHTML = '';

                strikes.forEach((strike, i) => {
                    const [ceColor, peColor] = COLORS[i % COLORS.length];
                    const card = document.createElement('div');
                    card.className = 'bg-white rounded-xl shadow p-3';

                    card.innerHTML = `
    <div class="flex items-center justify-between mb-2">
        <div class="flex items-center gap-2">
            <span class="font-bold text-gray-800 text-sm">${parseInt(strike).toLocaleString('en-IN')}</span>
            <span class="inline-flex items-center gap-1.5 text-[10px] text-gray-500">
                <span class="inline-flex items-center gap-0.5">
                    <span class="inline-block w-2.5 h-2.5 rounded-sm" style="background:#16a34a"></span>
                    <span class="inline-block w-2.5 h-2.5 rounded-sm" style="background:#dc2626"></span>
                    CE
                </span>
                <span class="inline-flex items-center gap-0.5 ml-1">
                    <span class="inline-block w-2.5 h-2.5 rounded-sm" style="background:#0ea5e9"></span>
                    <span class="inline-block w-2.5 h-2.5 rounded-sm" style="background:#f97316"></span>
                    PE
                </span>
            </span>
        </div>
        <div class="flex items-center gap-2">
            <div id="cell-${strike}" class="text-xs font-semibold px-2 py-0.5 rounded bg-gray-100 text-gray-500 whitespace-nowrap">—</div>
            <button data-strike="${strike}" data-ci="${i}"
                class="fs-btn text-xs px-2 py-0.5 rounded bg-blue-50 hover:bg-blue-100 text-blue-600 border border-blue-200 transition whitespace-nowrap">
                ⛶ Full
            </button>
        </div>
    </div>
    <div id="chart-${strike}" class="w-full" style="height:420px"></div>
`;

                    chartsGrid.appendChild(card);

                    const container = card.querySelector(`#chart-${strike}`);
                    const chart = LightweightCharts.createChart(container, {
                        width:  container.clientWidth,
                        height: 420,
                        layout: {
                            background: { color: '#ffffff' },
                            textColor:  '#374151',
                        },
                        grid: {
                            vertLines: false,
                            horzLines: false,
                        },
                        timeScale: {
                            timeVisible:    true,
                            secondsVisible: false,
                            rightOffset:    5,
                            barSpacing:     8,
                            // ── No fixLeftEdge / fixRightEdge — allow free zoom and scroll ──
                        },
                        crosshair: { mode: LightweightCharts.CrosshairMode.Normal },
                        handleScroll: true,
                        handleScale:  true,
                    });



                    // CE series — blue up candle, red down candle
                    const ceSeries = chart.addCandlestickSeries({
                        upColor:          '#16a34a',   // green body — bullish CE
                        downColor:        '#dc2626',   // red body   — bearish CE
                        borderUpColor:    '#16a34a',
                        borderDownColor:  '#dc2626',
                        wickUpColor:      '#16a34a',
                        wickDownColor:    '#dc2626',
                    });

// PE series — blue up candle, orange down candle
// Use a different wick/border color so you can tell CE from PE
                    const peSeries = chart.addCandlestickSeries({
                        upColor:          '#0ea5e9',   // sky-blue body  — bullish PE
                        downColor:        '#f97316',   // orange body    — bearish PE
                        borderUpColor:    '#0ea5e9',
                        borderDownColor:  '#f97316',
                        wickUpColor:      '#0ea5e9',
                        wickDownColor:    '#f97316',
                    });

                    state.charts[strike] = { chart, ceSeries, peSeries, lines: [] };

                    new ResizeObserver(() => chart.applyOptions({ width: container.clientWidth }))
                        .observe(container);
                });

                // Full-screen button listeners
                document.querySelectorAll('.fs-btn').forEach(btn => {
                    btn.addEventListener('click', function () {
                        openFullscreen(parseFloat(this.dataset.strike), parseInt(this.dataset.ci));
                    });
                });
            }

            // ── Render OHLC + lines into each card chart ──────────────────────────
            function renderCharts(data) {
                const isFirst = !state.initialized;

                state.strikes.forEach(strike => {
                    const entry = state.charts[strike];
                    if (!entry) return;

                    // ── Normalize timestamps before passing to LWC ───────────────────
                    const ceCandles = normalizeCandles(data.ohlc?.[strike]?.['CE'] ?? []);
                    const peCandles = normalizeCandles(data.ohlc?.[strike]?.['PE'] ?? []);

                    entry.ceSeries.setData(ceCandles);
                    entry.peSeries.setData(peCandles);

                    entry.lines.forEach(l => {
                        try { entry.ceSeries.removePriceLine(l); } catch(e) {}
                    });
                    entry.lines = [];
                    applyPriceLines(entry, strike, data, false);

                    fitOnce(entry.chart, isFirst, false);

                    updateBadge(`cell-${strike}`, data.cells?.[strike]);
                });

                state.initialized = true;

                if (state.fsChart && state.fsStrike !== null) {
                    renderFullscreenChart(data, state.fsStrike, state.fsColorIndex, isFirst);
                }
            }




            // ── Price lines: avgAtm + first-candle CE/PE high/low ─────────────────
            function applyPriceLines(entry, strike, data, isFs) {
                const series = entry.ceSeries;
                const fcl    = data.first_candle_lines?.[strike] ?? {};
                const ceFC   = fcl['CE'] ?? null;
                const peFC   = fcl['PE'] ?? null;
                const avgAtm = data.avg_atm;

                const defs = [
                    // avgATM — orange dashed, thickest
                    avgAtm !== null ? {
                        price: avgAtm,
                        color: '#f59e0b',
                        lineWidth: 4,
                        lineStyle: LightweightCharts.LineStyle.Solid,
                        axisLabelVisible: true,
                        title: `avgATM ${avgAtm}`,
                    } : null,

                    // CE first-candle HIGH — blue solid
                    ceFC ? {
                        price: ceFC.high,
                        color: '#2563eb',
                        lineWidth: 2,
                        lineStyle: LightweightCharts.LineStyle.Solid,
                        axisLabelVisible: true,
                        title: `CE-H ${ceFC.high}`,
                    } : null,

                    // CE first-candle LOW — light blue solid
                    ceFC ? {
                        price: ceFC.low,
                        color: '#93c5fd',
                        lineWidth: 2,
                        lineStyle: LightweightCharts.LineStyle.Solid,
                        axisLabelVisible: true,
                        title: `CE-L ${ceFC.low}`,
                    } : null,

                    // PE first-candle HIGH — red solid
                    peFC ? {
                        price: peFC.high,
                        color: '#dc2626',
                        lineWidth: 2,
                        lineStyle: LightweightCharts.LineStyle.Solid,
                        axisLabelVisible: true,
                        title: `PE-H ${peFC.high}`,
                    } : null,

                    // PE first-candle LOW — light red solid
                    peFC ? {
                        price: peFC.low,
                        color: '#fca5a5',
                        lineWidth: 2,
                        lineStyle: LightweightCharts.LineStyle.Solid,
                        axisLabelVisible: true,
                        title: `PE-L ${peFC.low}`,
                    } : null,

                ].filter(Boolean);

                defs.forEach(def => {
                    const line = series.createPriceLine(def);
                    entry.lines.push(line);
                });
            }


            // ── Full-screen ───────────────────────────────────────────────────────
            function openFullscreen(strike, colorIndex) {
                state.fsStrike     = strike;
                state.fsColorIndex = colorIndex;
                state.fsInitialized = false;

                fsOverlay.classList.remove('hidden');
                fsTitle.textContent = `Strike ${parseInt(strike).toLocaleString('en-IN')}`;

                // Destroy previous FS chart
                if (state.fsChart) {
                    state.fsChart.chart?.remove();
                    state.fsChart = null;
                }

                // Build FS chart
                fsContainer.innerHTML = '';
                const [ceColor, peColor] = COLORS[colorIndex % COLORS.length];
                const chart = LightweightCharts.createChart(fsContainer, {
                    ...chartOpts(fsContainer),
                    width:  fsContainer.clientWidth,
                    height: fsContainer.clientHeight || window.innerHeight - 80,
                    timeScale: { timeVisible: true, secondsVisible: false },
                    crosshair: { mode: LightweightCharts.CrosshairMode.Normal },
                    grid: {
                        vertLines: { visible: false },
                        horzLines: { visible: false }
                    }
                });

                chart.timeScale().fitContent();
                chart.timeScale().applyOptions({
                    barSpacing:  12,
                    rightOffset: 30,  // fullscreen
                });

                const ceSeries = chart.addCandlestickSeries({
                    upColor:          '#16a34a',   // green body — bullish CE
                    downColor:        '#dc2626',   // red body   — bearish CE
                    borderUpColor:    '#16a34a',
                    borderDownColor:  '#dc2626',
                    wickUpColor:      '#16a34a',
                    wickDownColor:    '#dc2626',
                });
                const peSeries = chart.addCandlestickSeries({
                    upColor:          '#0ea5e9',   // sky-blue body  — bullish PE
                    downColor:        '#f97316',   // orange body    — bearish PE
                    borderUpColor:    '#0ea5e9',
                    borderDownColor:  '#f97316',
                    wickUpColor:      '#0ea5e9',
                    wickDownColor:    '#f97316',
                });

                state.fsChart = { chart, ceSeries, peSeries, lines: [] };

                new ResizeObserver(() => {
                    chart.applyOptions({
                        width:  fsContainer.clientWidth,
                        height: fsContainer.clientHeight,
                    });
                }).observe(fsContainer);

                if (state.lastData) {
                    renderFullscreenChart(state.lastData, strike, colorIndex);
                }

                updateFsStatus();
                updateFsButtons();
            }

            function renderFullscreenChart(data, strike, colorIndex, isFirst = false) {
                const entry = state.fsChart;
                if (!entry?.chart) return;

                const ceCandles = normalizeCandles(data.ohlc?.[strike]?.['CE'] ?? []);
                const peCandles = normalizeCandles(data.ohlc?.[strike]?.['PE'] ?? []);

                entry.ceSeries.setData(ceCandles);
                entry.peSeries.setData(peCandles);

                entry.lines.forEach(l => {
                    try { entry.ceSeries.removePriceLine(l); } catch (e) {}
                });
                entry.lines = [];

                applyPriceLines(entry, strike, data, true);

                if (isFirst || !state.fsInitialized) {
                    entry.chart.timeScale().fitContent();
                    entry.chart.timeScale().applyOptions({
                        barSpacing: 12,
                        rightOffset: 30,
                    });
                    fitOnce(entry.chart, isFirst || !state.fsInitialized, true);
                    state.fsInitialized = true;
                }

                updateBadge('fsBadge', data.cells?.[strike]);
            }



            // function fitOnce(chart, isFirst) {
            //     if (isFirst) {
            //         // Show last N candles instead of all 76 squeezed in
            //         chart.timeScale().fitContent();
            //         chart.timeScale().applyOptions({
            //             barSpacing: 12,   // wider candles — increase for thicker
            //             rightOffset: 10,
            //         });
            //     }
            // }
            function fitOnce(chart, isFirst, isFullscreen = false) {
                if (isFirst) {
                    chart.timeScale().fitContent();
                    chart.timeScale().applyOptions({
                        barSpacing:  12,
                        rightOffset: isFullscreen ? 30 : 10,
                    });
                }
            }

            function forceVisibleRange(chart, data) {
                const allCandles = [];

                // Gather all candle times across all strikes to find true range
                state.strikes.forEach(strike => {
                    const ce = data.ohlc?.[strike]?.['CE'] ?? [];
                    const pe = data.ohlc?.[strike]?.['PE'] ?? [];
                    ce.forEach(c => allCandles.push(c.time));
                    pe.forEach(c => allCandles.push(c.time));
                });

                if (allCandles.length === 0) {
                    chart.timeScale().fitContent();
                    return;
                }

                const minTime = Math.min(...allCandles);
                const maxTime = Math.max(...allCandles);

                // Add padding: one candle width on each side (STEP_MINUTES * 60 seconds)
                const candleSeconds = {{ \App\Http\Controllers\OhlcChartController::STEP_MINUTES }} * 60;
                const paddedFrom    = minTime - candleSeconds;
                const paddedTo      = maxTime + candleSeconds;

                chart.timeScale().setVisibleRange({
                    from: paddedFrom,
                    to:   paddedTo,
                });
            }



            fsClose.addEventListener('click', function () {
                fsOverlay.classList.add('hidden');
            });

            // ── Cell badge helper ──────────────────────────────────────────────────
            function updateBadge(elId, cell) {
                const badge = $(elId);
                if (!badge) return;
                if (cell) {
                    const dir   = cell.direction === 'CE_SELL' ? 'CE Sell' : 'PE Sell';
                    const color = cell.direction === 'CE_SELL'
                        ? 'bg-red-100 text-red-700'
                        : 'bg-green-100 text-green-700';
                    const diff  = cell.in_band
                        ? ` <span class="opacity-70">Δ${cell.diff > 0 ? '+' : ''}${cell.diff.toFixed(2)}</span>` : '';
                    badge.className = `text-xs font-semibold px-2 py-0.5 rounded whitespace-nowrap ${color}`;
                    badge.innerHTML = dir + diff;
                } else {
                    badge.className = 'text-xs font-semibold px-2 py-0.5 rounded bg-gray-100 text-gray-500 whitespace-nowrap';
                    badge.innerHTML = '—';
                }
            }

            // ── Helpers ───────────────────────────────────────────────────────────
            function updateStatus(data) {
                slotLabel.textContent   = data.label;
                slotCounter.textContent = state.currentIndex + 1;
            }

            function updateButtons() {
                prevBtn.disabled = state.currentIndex <= 0;
                nextBtn.disabled = state.currentIndex + 1 >= state.totalSlots;
            }

            // ── Fullscreen nav listeners ───────────────────────────────────────────
            fsNextBtn.addEventListener('click', async function () {
                if (state.currentIndex + 1 >= state.totalSlots) return;
                state.currentIndex++;
                const data = await fetchSlot(state.currentIndex);
                if (!data) return;
                state.lastData = data;
                renderCharts(data);          // keep grid charts in sync too
                updateStatus(data);
                updateFsStatus();
                updateButtons();
                updateFsButtons();
            });

            fsPrevBtn.addEventListener('click', async function () {
                if (state.currentIndex <= 0) return;
                state.currentIndex--;
                const data = await fetchSlot(state.currentIndex);
                if (!data) return;
                state.lastData = data;
                renderCharts(data);
                updateStatus(data);
                updateFsStatus();
                updateButtons();
                updateFsButtons();
            });

            fsResetBtn.addEventListener('click', async function () {
                state.currentIndex = 0;
                const data = await fetchSlot(0);
                if (!data) return;
                state.lastData = data;
                renderCharts(data);
                updateStatus(data);
                updateFsStatus();
                updateButtons();
                updateFsButtons();
            });

            function updateFsStatus() {
                if (fsSlotLabel)   fsSlotLabel.textContent   = state.lastData?.label ?? '—';
                if (fsSlotCounter) fsSlotCounter.textContent = `(${state.currentIndex + 1} / ${state.totalSlots})`;
            }

            function updateFsButtons() {
                if (fsPrevBtn) fsPrevBtn.disabled = state.currentIndex <= 0;
                if (fsNextBtn) fsNextBtn.disabled = state.currentIndex + 1 >= state.totalSlots;
            }


        })();
    </script>
@endpush
