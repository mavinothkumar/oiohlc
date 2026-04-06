@extends('layouts.app')

@section('title', 'OI & Volume Step Chart')

@section('content')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">

    <style>
        /* ── Heatmap cells ─────────────────────────────────────────────────────── */
        .hm-cell {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            font-weight: 600;
            border-radius: 3px;
            height: 22px;
            min-width: 38px;
            color: #fff;
            letter-spacing: 0.02em;
        }
        .hm-lb  { background: #16a34a; }
        .hm-sb  { background: #dc2626; }
        .hm-sc  { background: #2563eb; }
        .hm-lu  { background: #ea580c; }
        .hm-neu { background: #6b7280; }
        .hm-nil { background: #e5e7eb; color: #9ca3af; }
        dark .hm-nil { background: #374151; color: #6b7280; }

        /* ── OI Delta zero-line ────────────────────────────────────────────────── */
        .delta-label {
            font-size: 10px;
            font-weight: 600;
        }
    </style>

    <div class="max-w-full mx-auto px-4 py-6 space-y-6">

        {{-- ── Page Header ───────────────────────────────────────────────── --}}
        <div>
            <h1 class="text-xl font-bold text-gray-800 dark:text-gray-100">Market Direction Dashboard</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                OI Dominance · Build-Up Heatmap · OI Delta · Volume · Index — step through each 5-min candle
            </p>
        </div>

        {{-- ── Filter Form ────────────────────────────────────────────────── --}}
        <form id="filterForm"
            class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 flex flex-wrap gap-3 items-end">

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Symbol</label>
                <input type="text" id="symbolInput" value="{{ $symbol ?? 'NIFTY' }}"
                    class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm
                          bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 w-28
                          focus:ring-2 focus:ring-blue-500 outline-none uppercase">
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 dark:text-gray-300">
                    Date <span class="text-gray-400 font-normal">(NSE working day)</span>
                </label>
                <input type="text" id="dateInput" placeholder="YYYY-MM-DD"
                    value="{{ $quoteDate ?? '' }}" autocomplete="off"
                    class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm
                          bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 w-36
                          focus:ring-2 focus:ring-blue-500 outline-none">
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Expiry</label>
                <input type="text" id="expiryInput" placeholder="Auto-fill" readonly
                    value="{{ $expiry ?? '' }}"
                    class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm
                          bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300
                          w-32 cursor-not-allowed">
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Strikes</label>
                <select id="numStrikesInput"
                    class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm
                           bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 w-20
                           focus:ring-2 focus:ring-blue-500 outline-none">
                    @foreach ([3,5,7,9] as $n)
                        <option value="{{ $n }}" {{ ($numStrikes ?? 3) == $n ? 'selected' : '' }}>{{ $n }}</option>
                    @endforeach
                </select>
            </div>

            <button type="button" id="loadBtn"
                class="self-end px-5 py-2 bg-blue-600 hover:bg-blue-700
                       text-white text-sm font-semibold rounded-lg transition">
                Load
            </button>
        </form>

        {{-- ── Status bar ──────────────────────────────────────────────────── --}}
        <div id="statusBar"
            class="hidden flex flex-wrap items-center gap-3 text-sm
                bg-white dark:bg-gray-800 rounded-xl shadow px-4 py-2">
            <span class="text-gray-400">Candle</span>
            <span id="slotLabel" class="font-bold text-blue-600 dark:text-blue-400 text-base">—</span>
            <span class="text-gray-300">|</span>
            <span id="slotCounter" class="font-semibold text-gray-700 dark:text-gray-200"></span>
            <span class="text-gray-300">/</span>
            <span id="totalSlotsEl" class="font-semibold text-gray-700 dark:text-gray-200"></span>
            <span class="text-gray-300">|</span>
            <span id="atmLabel" class="font-bold text-orange-500"></span>
            <span class="text-gray-300">|</span>
            {{-- Live market bias pill --}}
            <span id="biasPill" class="px-3 py-0.5 rounded-full text-xs font-bold hidden"></span>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════
             SECTION 1 — Per-Strike panels (OI Dominance + Heatmap + Delta)
        ══════════════════════════════════════════════════════════════════ --}}
        <div id="strikeSection" class="hidden space-y-3">
            <h2 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-widest">
                Per-Strike Analysis
            </h2>
            <div id="strikeGrid" class="grid grid-cols-1 xl:grid-cols-3 gap-4"></div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════
             SECTION 2 — Combined Volume grouped bar
        ══════════════════════════════════════════════════════════════════ --}}
        <div id="volumeSection" class="hidden bg-white dark:bg-gray-800 rounded-xl shadow p-4 space-y-2">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                Volume — CE <span class="text-green-600">█</span> vs PE <span class="text-red-500">█</span>
                <span class="ml-2 text-xs font-normal text-gray-400">all strikes combined per candle</span>
            </p>
            <div class="relative" style="height:200px"><canvas id="volBarChart"></canvas></div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════
             SECTION 3 — Index candlestick
        ══════════════════════════════════════════════════════════════════ --}}
        <div id="indexSection" class="hidden bg-white dark:bg-gray-800 rounded-xl shadow p-4">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3" id="indexChartTitle">
                Index — 5-min Candles
            </p>
            <div id="indexChartContainer" style="height:300px;width:100%;"></div>
        </div>

        {{-- ── Legend card ─────────────────────────────────────────────────── --}}
        <div id="legendCard"
            class="hidden bg-white dark:bg-gray-800 rounded-xl shadow p-4 flex flex-wrap gap-4 text-xs">
            <p class="w-full text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1">
                Build-Up Legend
            </p>
            <span class="flex items-center gap-1.5">
            <span class="hm-cell hm-lb px-2">LB</span>
            <span class="text-gray-600 dark:text-gray-300">Long Build — OI↑ Price↑ &nbsp;Bullish accumulation</span>
        </span>
            <span class="flex items-center gap-1.5">
            <span class="hm-cell hm-sb px-2">SB</span>
            <span class="text-gray-600 dark:text-gray-300">Short Build — OI↑ Price↓ &nbsp;Bearish accumulation</span>
        </span>
            <span class="flex items-center gap-1.5">
            <span class="hm-cell hm-sc px-2">SC</span>
            <span class="text-gray-600 dark:text-gray-300">Short Cover — OI↓ Price↑ &nbsp;Bears covering (reversal signal)</span>
        </span>
            <span class="flex items-center gap-1.5">
            <span class="hm-cell hm-lu px-2">LU</span>
            <span class="text-gray-600 dark:text-gray-300">Long Unwind — OI↓ Price↓ &nbsp;Bulls exiting (reversal signal)</span>
        </span>
            <span class="flex items-center gap-1.5">
            <span class="hm-cell hm-neu px-2">N</span>
            <span class="text-gray-600 dark:text-gray-300">Neutral</span>
        </span>
        </div>

    </div>{{-- /container --}}

    {{-- ── Fixed floating nav (icon-only circles) ────────────────────────── --}}
    <div id="fabNav" class="hidden fixed top-4 right-4 z-50 flex flex-col gap-2">
        <button id="refreshBtn" title="Refresh"
            class="w-10 h-10 rounded-full bg-gray-500 hover:bg-gray-600 text-white
                   shadow-lg flex items-center justify-center transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 4v5h.582M20 20v-5h-.582M5.635 19A9 9 0 104.582 9H4"/>
            </svg>
        </button>
        <button id="backwardBtn" disabled title="Backward"
            class="w-10 h-10 rounded-full bg-yellow-500 hover:bg-yellow-600 text-white
                   shadow-lg flex items-center justify-center transition
                   disabled:opacity-40 disabled:cursor-not-allowed">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>
        <button id="forwardBtn" disabled title="Forward"
            class="w-10 h-10 rounded-full bg-green-600 hover:bg-green-700 text-white
                   shadow-lg flex items-center justify-center transition
                   disabled:opacity-40 disabled:cursor-not-allowed">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </button>
    </div>

@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/lightweight-charts@4.2.0/dist/lightweight-charts.standalone.production.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        (function () {
            'use strict';

            /* ── Blade-injected ────────────────────────────────────────────────── */
            const SLOTS      = @json($slots);
            const NSE_DATES  = @json($dates);
            const ROUTE_EXP  = "{{ route('test.api.oi.volume.expiries') }}";
            const ROUTE_SLOT = "{{ route('test.api.oi.volume.slot') }}";

            /* ── State ─────────────────────────────────────────────────────────── */
            const state = {
                symbol:'NIFTY', date:'', expiry:'', numStrikes:3,
                currentIndex:-1, totalSlots:SLOTS.length, lastData:null,
                // per-strike charts: { strike: { oiBar, deltaLine } }
                strikeCharts: {},
                volBarChart:  null,
                lwcChart:     null,
                lwcSeries:    null,
            };

            /* ── Build-up colour map ────────────────────────────────────────────── */
            const BU_CSS   = { 'Long Build':'hm-lb', 'Short Build':'hm-sb',
                'Short Cover':'hm-sc', 'Long Unwind':'hm-lu',
                'Neutral':'hm-neu' };
            const BU_SHORT = { 'Long Build':'LB', 'Short Build':'SB',
                'Short Cover':'SC', 'Long Unwind':'LU', 'Neutral':'N' };
            const BU_TYPES = ['Long Build','Short Build','Short Cover','Long Unwind'];

            // OI Dominance stacked bar: CE=green, PE=red
            const STRIKE_PALETTE = ['#2563eb','#16a34a','#dc2626','#ea580c','#7c3aed','#0891b2'];

            /* ── DOM ─────────────────────────────────────────────────────────────  */
            const $ = id => document.getElementById(id);
            const el = {
                symbolInput:    $('symbolInput'),
                dateInput:      $('dateInput'),
                expiryInput:    $('expiryInput'),
                numStrikesInput:$('numStrikesInput'),
                loadBtn:        $('loadBtn'),
                refreshBtn:     $('refreshBtn'),
                backwardBtn:    $('backwardBtn'),
                forwardBtn:     $('forwardBtn'),
                fabNav:         $('fabNav'),
                statusBar:      $('statusBar'),
                slotLabel:      $('slotLabel'),
                slotCounter:    $('slotCounter'),
                totalSlotsEl:   $('totalSlotsEl'),
                atmLabel:       $('atmLabel'),
                biasPill:       $('biasPill'),
                strikeSection:  $('strikeSection'),
                strikeGrid:     $('strikeGrid'),
                volumeSection:  $('volumeSection'),
                indexSection:   $('indexSection'),
                indexChartTitle:$('indexChartTitle'),
                indexContainer: $('indexChartContainer'),
                legendCard:     $('legendCard'),
            };

            /* ── Flatpickr ──────────────────────────────────────────────────────── */
            const latestDate   = NSE_DATES[0] ?? null;
            const enabledDates = NSE_DATES.slice();

            flatpickr('#dateInput', {
                dateFormat:'Y-m-d', disableMobile:true,
                defaultDate: latestDate ?? null,
                enable: enabledDates, maxDate:'today',
                onDayCreate(_, __, ___, dayElem) {
                    const d   = dayElem.dateObj;
                    const ymd = d.getFullYear() + '-'
                        + String(d.getMonth()+1).padStart(2,'0') + '-'
                        + String(d.getDate()).padStart(2,'0');
                    if (ymd === latestDate) {
                        dayElem.style.position = 'relative';
                        dayElem.innerHTML += '<span style="position:absolute;bottom:2px;left:50%;transform:translateX(-50%);width:4px;height:4px;border-radius:50%;background:#60a5fa;display:block"></span>';
                    }
                },
            });

            el.dateInput.addEventListener('change', async () => {
                if (el.dateInput.value) await autoFillExpiry(el.dateInput.value);
            });
            el.numStrikesInput.addEventListener('change', async () => {
                if (el.dateInput.value) await autoFillExpiry(el.dateInput.value);
            });

            async function autoFillExpiry(date) {
                el.expiryInput.value = 'Loading…';
                const sym = (el.symbolInput.value.trim()||'NIFTY').toUpperCase();
                try {
                    const p   = new URLSearchParams({ symbol:sym, quote_date:date, num_strikes:el.numStrikesInput.value });
                    const res  = await fetch(`${ROUTE_EXP}?${p}`);
                    const json = await res.json();
                    el.expiryInput.value    = json.expiry ?? '';
                    el.atmLabel.textContent = json.atm_strike
                        ? `ATM: ${Number(json.atm_strike).toLocaleString('en-IN')}` : '';
                } catch { el.expiryInput.value = ''; }
            }
            if (latestDate && !el.expiryInput.value) autoFillExpiry(latestDate);

            /* ── Load ───────────────────────────────────────────────────────────── */
            el.loadBtn.addEventListener('click', async () => {
                if (!el.dateInput.value || !el.expiryInput.value) {
                    alert('Select a Date — Expiry auto-fills.'); return;
                }
                state.symbol      = (el.symbolInput.value.trim()||'NIFTY').toUpperCase();
                state.date        = el.dateInput.value;
                state.expiry      = el.expiryInput.value;
                state.numStrikes  = parseInt(el.numStrikesInput.value,10);
                state.currentIndex= -1;
                state.lastData    = null;

                [el.fabNav, el.statusBar, el.strikeSection,
                    el.volumeSection, el.indexSection, el.legendCard]
                    .forEach(e => e.classList.remove('hidden'));
                el.indexChartTitle.textContent = `${state.symbol} Index – 5-min Candles`;
                el.totalSlotsEl.textContent    = state.totalSlots;

                destroyAll();
                await loadSlot(0);
            });

            /* ── Navigation ─────────────────────────────────────────────────────── */
            el.forwardBtn.addEventListener('click',  async () => { if (state.currentIndex+1 < state.totalSlots) await loadSlot(state.currentIndex+1); });
            el.backwardBtn.addEventListener('click', async () => { if (state.currentIndex > 0) await loadSlot(state.currentIndex-1); });
            el.refreshBtn.addEventListener('click',  async () => { if (state.currentIndex >= 0) await loadSlot(state.currentIndex); });

            /* ── Fetch ───────────────────────────────────────────────────────────── */
            async function loadSlot(idx) {
                const p = new URLSearchParams({
                    symbol:state.symbol, quote_date:state.date, expiry:state.expiry,
                    num_strikes:state.numStrikes, slot_index:idx,
                });
                try {
                    const res  = await fetch(`${ROUTE_SLOT}?${p}`);
                    const json = await res.json();
                    if (json.error) { alert(json.error); return; }
                    state.currentIndex = idx;
                    state.lastData     = json;
                    renderStrikeCards(json);
                    renderVolumeBar(json);
                    renderIndexCandles(json);
                    updateStatus(json);
                    updateButtons();
                } catch(e) { console.error(e); alert('Failed to load slot.'); }
            }

            /* ── Destroy ─────────────────────────────────────────────────────────── */
            function destroyAll() {
                Object.values(state.strikeCharts).forEach(c => {
                    c.oiBar?.destroy(); c.deltaLine?.destroy();
                });
                state.strikeCharts = {};
                el.strikeGrid.innerHTML = '';
                state.volBarChart?.destroy(); state.volBarChart = null;
                state.lwcChart?.remove(); state.lwcChart = null; state.lwcSeries = null;
                el.indexContainer.innerHTML = '';
            }

            /* ══════════════════════════════════════════════════════════════════════
               SECTION 1 — Per-strike cards
               Each card has 3 sub-panels:
                 A) OI Dominance stacked bar  (CE OI green | PE OI red)
                 B) Build-Up heatmap rows     (CE row + PE row, one cell per candle)
                 C) OI Delta line             (CE OI − PE OI, crosses zero)
            ══════════════════════════════════════════════════════════════════════ */
            function renderStrikeCards(data) {
                const labels     = data.time_labels  ?? [];
                const strikes    = data.strikes      ?? [];
                const timeSeries = data.time_series  ?? {};
                const buData     = data.build_up_data ?? {};
                const atmIdx     = Math.floor(strikes.length / 2);

                strikes.forEach((strike, si) => {
                    const cardId      = `sk-card-${strike}`;
                    const oiBarId     = `sk-oi-${strike}`;
                    const deltaId     = `sk-delta-${strike}`;
                    const hmCeId      = `sk-hm-ce-${strike}`;
                    const hmPeId      = `sk-hm-pe-${strike}`;

                    /* ── Create card shell once ──────────────────────────────── */
                    if (!$(cardId)) {
                        const atmBadge = si === atmIdx
                            ? '<span class="px-1.5 py-0.5 rounded bg-orange-100 dark:bg-orange-900 text-orange-600 dark:text-orange-300 text-xs font-bold">ATM</span>'
                            : (si < atmIdx
                                ? '<span class="px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-900 text-indigo-500 text-xs">ITM CE</span>'
                                : '<span class="px-1.5 py-0.5 rounded bg-purple-50 dark:bg-purple-900 text-purple-500 text-xs">OTM CE</span>');

                        const card = document.createElement('div');
                        card.id        = cardId;
                        card.className = 'bg-white dark:bg-gray-800 rounded-xl shadow p-3 space-y-3';
                        card.innerHTML = `
                  <div class="flex items-center gap-2">
                    <span class="font-bold text-gray-800 dark:text-gray-100">
                        ${Number(strike).toLocaleString('en-IN')}
                    </span>
                    ${atmBadge}
                    <span id="sk-bias-${strike}" class="ml-auto px-2 py-0.5 rounded-full text-xs font-bold hidden"></span>
                  </div>

                  {{-- A: OI Dominance stacked bar --}}
                        <div>
                          <p class="text-xs text-gray-400 mb-1 font-medium">
                              OI Dominance
                              <span class="ml-1 text-green-600 font-bold">CE</span>
                              vs
                              <span class="ml-1 text-red-500 font-bold">PE</span>
                          </p>
                          <div class="relative" style="height:120px">
                              <canvas id="${oiBarId}"></canvas>
                    </div>
                  </div>

                  {{-- B: Build-Up Heatmap --}}
                        <div>
                          <p class="text-xs text-gray-400 mb-1 font-medium">Build-Up Heatmap</p>
                          <div class="space-y-1">
                            <div class="flex items-center gap-1 flex-wrap">
                              <span class="text-xs font-bold text-green-600 w-5">CE</span>
                              <div id="${hmCeId}" class="flex gap-0.5 flex-wrap"></div>
                      </div>
                      <div class="flex items-center gap-1 flex-wrap">
                        <span class="text-xs font-bold text-red-500 w-5">PE</span>
                        <div id="${hmPeId}" class="flex gap-0.5 flex-wrap"></div>
                      </div>
                    </div>
                  </div>

                  {{-- C: OI Delta line --}}
                        <div>
                          <p class="text-xs text-gray-400 mb-1 font-medium">
                              OI Delta
                              <span class="text-green-600">(CE − PE)</span>
                              — above 0 = CE dominates (bearish) · below = PE dominates (bullish)
                          </p>
                          <div class="relative" style="height:100px">
                              <canvas id="${deltaId}"></canvas>
                    </div>
                  </div>
                `;
                        el.strikeGrid.appendChild(card);

                        /* Init OI Dominance stacked bar */
                        state.strikeCharts[strike] = {
                            oiBar: new Chart($(oiBarId), {
                                type: 'bar',
                                data: { labels:[], datasets:[] },
                                options: {
                                    responsive:true, maintainAspectRatio:false,
                                    animation:{ duration:300 },
                                    plugins: {
                                        legend:{ position:'top', labels:{ font:{size:10}, boxWidth:12, padding:6 }},
                                        tooltip:{ mode:'index', intersect:false,
                                            callbacks:{ label: ctx => `${ctx.dataset.label}: ${Number(ctx.raw).toLocaleString('en-IN')}` }},
                                    },
                                    scales: {
                                        x:{ stacked:true, ticks:{ font:{size:9}, maxRotation:45 }, grid:{ display:false } },
                                        y:{ stacked:true, ticks:{ font:{size:9},
                                                callback: v => v >= 1e6 ? (v/1e6).toFixed(1)+'M' : v >= 1e3 ? (v/1e3).toFixed(0)+'K' : v },
                                            grid:{ color:'rgba(156,163,175,0.12)' } },
                                    },
                                    interaction:{ mode:'index', intersect:false },
                                },
                            }),
                            deltaLine: new Chart($(deltaId), {
                                type:'line',
                                data:{ labels:[], datasets:[] },
                                options:{
                                    responsive:true, maintainAspectRatio:false,
                                    animation:{ duration:300 },
                                    plugins:{
                                        legend:{ display:false },
                                        tooltip:{ callbacks:{
                                                label: ctx => {
                                                    const v = ctx.raw;
                                                    return v > 0
                                                        ? `CE leads by ${Number(v).toLocaleString('en-IN')} (Bearish pressure)`
                                                        : `PE leads by ${Number(Math.abs(v)).toLocaleString('en-IN')} (Bullish pressure)`;
                                                }
                                            }},
                                    },
                                    scales:{
                                        x:{ ticks:{ font:{size:9}, maxRotation:45 }, grid:{ display:false }},
                                        y:{
                                            ticks:{ font:{size:9},
                                                callback: v => v >= 1e6?(v/1e6).toFixed(1)+'M': v >= 1e3?(v/1e3).toFixed(0)+'K': v },
                                            grid:{ color:'rgba(156,163,175,0.12)' },
                                            // zero line emphasized
                                        },
                                    },
                                    interaction:{ mode:'nearest', axis:'x', intersect:false },
                                },
                            }),
                        };
                    }

                    /* ── Prepare data ──────────────────────────────────────────── */
                    const ceRows  = timeSeries[strike]?.CE ?? [];
                    const peRows  = timeSeries[strike]?.PE ?? [];
                    const ceOiMap = {}, peOiMap = {};
                    ceRows.forEach(p => ceOiMap[p.time] = p.open_interest);
                    peRows.forEach(p => peOiMap[p.time] = p.open_interest);

                    const ceOiArr   = labels.map(t => ceOiMap[t] ?? 0);
                    const peOiArr   = labels.map(t => peOiMap[t] ?? 0);
                    const deltaArr  = labels.map((t, i) => (ceOiArr[i]||0) - (peOiArr[i]||0));

                    /* ── A: OI Dominance stacked bar ───────────────────────────── */
                    const oiChart = state.strikeCharts[strike].oiBar;
                    oiChart.data.labels   = labels;
                    oiChart.data.datasets = [
                        {
                            label:'CE OI', data: ceOiArr,
                            backgroundColor:'rgba(22,163,74,0.75)',
                            borderColor:'#16a34a', borderWidth:1,
                            borderRadius:2,
                        },
                        {
                            label:'PE OI', data: peOiArr,
                            backgroundColor:'rgba(220,38,38,0.75)',
                            borderColor:'#dc2626', borderWidth:1,
                            borderRadius:2,
                        },
                    ];
                    oiChart.update();

                    /* ── B: Build-Up Heatmap ────────────────────────────────────── */
                    ['CE','PE'].forEach(type => {
                        const hmEl = $(type === 'CE' ? hmCeId : hmPeId);
                        if (!hmEl) return;
                        hmEl.innerHTML = '';
                        labels.forEach(tl => {
                            // Find the build_up type active at this time for this strike/type
                            // We read it from build_up_data: first non-null buType wins
                            let activeBu = 'Neutral';
                            for (const buType of BU_TYPES) {
                                const arr = data.build_up_data?.[strike]?.[type]?.[buType];
                                if (!arr) continue;
                                const idx = labels.indexOf(tl);
                                if (idx >= 0 && arr[idx] !== null) { activeBu = buType; break; }
                            }
                            const css   = BU_CSS[activeBu]   ?? 'hm-nil';
                            const short = BU_SHORT[activeBu] ?? '?';
                            const cell  = document.createElement('span');
                            cell.className = `hm-cell ${css}`;
                            cell.title     = `${tl} — ${activeBu}`;
                            cell.textContent = short;
                            hmEl.appendChild(cell);
                        });
                    });

                    /* ── C: OI Delta line ───────────────────────────────────────── */
                    const lastDelta = deltaArr[deltaArr.length - 1] ?? 0;
                    const deltaChart = state.strikeCharts[strike].deltaLine;

                    // Color each segment: positive = bearish (red tint), negative = bullish (green tint)
                    const deltaColors = deltaArr.map(v => v > 0 ? 'rgba(220,38,38,0.8)' : 'rgba(22,163,74,0.8)');

                    deltaChart.data.labels   = labels;
                    deltaChart.data.datasets = [{
                        label:          'OI Delta',
                        data:            deltaArr,
                        borderColor:     deltaColors,
                        backgroundColor: deltaArr.map(v => v > 0 ? 'rgba(220,38,38,0.08)' : 'rgba(22,163,74,0.08)'),
                        borderWidth:     2,
                        pointRadius:     2,
                        pointBackgroundColor: deltaColors,
                        tension:         0.3,
                        fill:            'origin',   // fill to zero line
                        segment: {
                            borderColor: ctx => ctx.p0.parsed.y >= 0 ? 'rgba(220,38,38,0.85)' : 'rgba(22,163,74,0.85)',
                            backgroundColor: ctx => ctx.p0.parsed.y >= 0 ? 'rgba(220,38,38,0.07)' : 'rgba(22,163,74,0.07)',
                        },
                        spanGaps: true,
                    }];
                    deltaChart.update();

                    /* ── Per-strike bias pill ──────────────────────────────────── */
                    const biasPill = $(`sk-bias-${strike}`);
                    if (biasPill) {
                        if (Math.abs(lastDelta) < 50000) {
                            biasPill.className = 'ml-auto px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 dark:bg-gray-700 text-gray-500';
                            biasPill.textContent = 'Neutral';
                        } else if (lastDelta > 0) {
                            biasPill.className = 'ml-auto px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 dark:bg-red-900 text-red-600 dark:text-red-300';
                            biasPill.textContent = '↓ Bearish';
                        } else {
                            biasPill.className = 'ml-auto px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 dark:bg-green-900 text-green-600 dark:text-green-300';
                            biasPill.textContent = '↑ Bullish';
                        }
                        biasPill.classList.remove('hidden');
                    }
                });

                /* ── Global bias pill (ATM strike delta) ─────────────────────── */
                const atmStrike  = strikes[Math.floor(strikes.length/2)];
                const atmCeRows  = data.time_series?.[atmStrike]?.CE ?? [];
                const atmPeRows  = data.time_series?.[atmStrike]?.PE ?? [];
                const atmCeOi    = atmCeRows.length ? atmCeRows[atmCeRows.length-1].open_interest : 0;
                const atmPeOi    = atmPeRows.length ? atmPeRows[atmPeRows.length-1].open_interest : 0;
                const atmDelta   = atmCeOi - atmPeOi;
                if (el.biasPill) {
                    el.biasPill.classList.remove('hidden');
                    if (Math.abs(atmDelta) < 50000) {
                        el.biasPill.className = 'px-3 py-0.5 rounded-full text-xs font-bold bg-gray-200 text-gray-600';
                        el.biasPill.textContent = 'Neutral';
                    } else if (atmDelta > 0) {
                        el.biasPill.className = 'px-3 py-0.5 rounded-full text-xs font-bold bg-red-500 text-white';
                        el.biasPill.textContent = '↓ Bearish (CE > PE at ATM)';
                    } else {
                        el.biasPill.className = 'px-3 py-0.5 rounded-full text-xs font-bold bg-green-600 text-white';
                        el.biasPill.textContent = '↑ Bullish (PE > CE at ATM)';
                    }
                }
            }

            /* ══════════════════════════════════════════════════════════════════════
               SECTION 2 — Combined Volume grouped bar (all strikes)
            ══════════════════════════════════════════════════════════════════════ */
            function renderVolumeBar(data) {
                const labels     = data.time_labels ?? [];
                const strikes    = data.strikes     ?? [];
                const timeSeries = data.time_series ?? {};

                // Sum CE and PE volume across all strikes at each time
                const totalCeVol = labels.map(t => {
                    return strikes.reduce((sum, s) => {
                        const row = (timeSeries[s]?.CE ?? []).find(r => r.time === t);
                        return sum + (row?.volume ?? 0);
                    }, 0);
                });
                const totalPeVol = labels.map(t => {
                    return strikes.reduce((sum, s) => {
                        const row = (timeSeries[s]?.PE ?? []).find(r => r.time === t);
                        return sum + (row?.volume ?? 0);
                    }, 0);
                });

                if (!state.volBarChart) {
                    state.volBarChart = new Chart($('volBarChart'), {
                        type: 'bar',
                        data: { labels:[], datasets:[] },
                        options: {
                            responsive:true, maintainAspectRatio:false,
                            animation:{ duration:300 },
                            plugins:{
                                legend:{ position:'top', labels:{ font:{size:11}, boxWidth:14 }},
                                tooltip:{ mode:'index', intersect:false,
                                    callbacks:{ label: ctx => `${ctx.dataset.label}: ${Number(ctx.raw).toLocaleString('en-IN')}` }},
                            },
                            scales:{
                                x:{ ticks:{ font:{size:10}, maxRotation:45 }, grid:{ display:false }},
                                y:{ ticks:{ font:{size:10},
                                        callback: v => v>=1e6?(v/1e6).toFixed(1)+'M': v>=1e3?(v/1e3).toFixed(0)+'K': v },
                                    grid:{ color:'rgba(156,163,175,0.12)' }},
                            },
                            interaction:{ mode:'index', intersect:false },
                        },
                    });
                }

                state.volBarChart.data.labels   = labels;
                state.volBarChart.data.datasets = [
                    { label:'CE Volume', data:totalCeVol, backgroundColor:'rgba(22,163,74,0.75)', borderColor:'#16a34a', borderWidth:1, borderRadius:2 },
                    { label:'PE Volume', data:totalPeVol, backgroundColor:'rgba(220,38,38,0.75)', borderColor:'#dc2626', borderWidth:1, borderRadius:2 },
                ];
                state.volBarChart.update();
            }

            /* ══════════════════════════════════════════════════════════════════════
               SECTION 3 — Index Candlestick (LWC)
            ══════════════════════════════════════════════════════════════════════ */
            function tsToLocal(ts) {
                const d = new Date(ts * 1000);
                return Date.UTC(d.getFullYear(), d.getMonth(), d.getDate(),
                    d.getHours(), d.getMinutes(), 0, 0) / 1000;
            }

            function renderIndexCandles(data) {
                const container = el.indexContainer;
                if (!state.lwcChart) {
                    state.lwcChart = LightweightCharts.createChart(container, {
                        width:  container.clientWidth, height:300,
                        layout: { background:{ color:'transparent' }, textColor:'#6b7280' },
                        grid: { vertLines:{ visible:false }, horzLines:{ color:'rgba(156,163,175,0.15)' }},
                        timeScale:{ timeVisible:true, secondsVisible:false, fixLeftEdge:true },
                        crosshair:{ mode:LightweightCharts.CrosshairMode.Normal },
                        handleScroll:true, handleScale:true,
                    });
                    state.lwcSeries = state.lwcChart.addCandlestickSeries({
                        upColor:'#16a34a', downColor:'#dc2626',
                        borderUpColor:'#16a34a', borderDownColor:'#dc2626',
                        wickUpColor:'#16a34a',  wickDownColor:'#dc2626',
                    });
                    new ResizeObserver(() => state.lwcChart.applyOptions({ width:container.clientWidth }))
                        .observe(container);
                }

                const candles = (data.index_candles ?? [])
                    .filter(c => {
                        if (!c?.time) return false;
                        return [c.open,c.high,c.low,c.close].map(parseFloat).every(v => !isNaN(v) && v > 0);
                    })
                    .map(c => ({
                        time:  tsToLocal(Math.floor(new Date(c.time).getTime()/1000)),
                        open:  parseFloat(c.open), high: parseFloat(c.high),
                        low:   parseFloat(c.low),  close:parseFloat(c.close),
                    }))
                    .sort((a,b) => a.time - b.time)
                    .filter((c,i,arr) => i===0 || c.time !== arr[i-1].time);

                if (candles.length) {
                    state.lwcSeries.setData(candles);
                    state.lwcChart.timeScale().fitContent();
                    state.lwcChart.timeScale().applyOptions({ barSpacing:12, rightOffset:5 });
                }
            }

            /* ── Status & buttons ────────────────────────────────────────────────── */
            function updateStatus(data) {
                el.slotLabel.textContent   = data.label ?? '—';
                el.slotCounter.textContent = state.currentIndex + 1;
                if (data.atm)
                    el.atmLabel.textContent = `ATM: ${Number(data.atm).toLocaleString('en-IN')}`;
            }
            function updateButtons() {
                el.backwardBtn.disabled = state.currentIndex <= 0;
                el.forwardBtn.disabled  = state.currentIndex + 1 >= state.totalSlots;
            }

        })();
    </script>
@endpush
