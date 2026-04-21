@extends('layouts.app')

@section('title', 'NIFTY Strike Charts')

@push('styles')
    <style>
        /* Chart host needs explicit pixel height for LightweightCharts to initialise */
        .chart-box { height: calc(58vh - 64px); min-height: 420px; width: 100%; }

        /* font-variant-numeric not in Tailwind v3 */
        .tabular-nums { font-variant-numeric: tabular-nums; }

        .full-screen-chart {
            position: fixed !important;
            inset: 0 !important;
            z-index: 9999;
            border-radius: 0 !important;
            background: #fff;
            display: flex !important;
            flex-direction: column;
            width: 100vw !important;
            height: 100vh !important;
            margin: 0 !important;
        }

        .full-screen-chart > .px-2.py-2 {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .full-screen-chart .chart-box {
            flex: 1 1 auto;
            width: 100% !important;
            height: 100% !important;
            min-height: 0 !important;
        }

        /* Filter panel slide transition */
        #filterPanel {
            overflow: hidden;
            transition: max-height 0.35s ease, opacity 0.35s ease, padding 0.35s ease;
        }
        #filterPanel.collapsed {
            max-height: 0 !important;
            opacity: 0;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }

        /* Live pulse ring */
        .live-ring::after {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 9999px;
            background: rgba(34,197,94,.18);
            animation: live-pulse 2s ease-in-out infinite;
        }
        .chart-summary {
            border-top: 1px solid #e2e8f0;
            margin-top: 8px;
            padding: 8px 4px 0;
        }

        .chart-summary-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 6px;
        }

        .chart-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border-radius: 9999px;
            padding: 3px 8px;
            font-size: 11px;
            line-height: 1;
            font-weight: 600;
            border: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .chart-chip--ceoi  { background: #ecfeff; color: #0f766e; }
        .chart-chip--cevol { background: #eff6ff; color: #1d4ed8; }
        .chart-chip--peoi  { background: #faf5ff; color: #7c3aed; }
        .chart-chip--pevol { background: #fff7ed; color: #9a3412; }

        .chart-tooltip {
            position: absolute;
            z-index: 30;
            pointer-events: none;
            min-width: 210px;
            max-width: 260px;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            background: rgba(255,255,255,.96);
            box-shadow: 0 10px 30px rgba(15,23,42,.12);
            color: #0f172a;
            font-size: 12px;
            line-height: 1.35;
            display: none;
        }

        .chart-tooltip-grid {
            display: grid;
            grid-template-columns: auto auto;
            gap: 3px 10px;
        }

        .chart-tooltip-label {
            color: #64748b;
        }

        .chart-tooltip-value {
            text-align: right;
            font-variant-numeric: tabular-nums;
            font-weight: 600;
        }

        @keyframes live-pulse {
            0%,100% { opacity:1; transform:scale(1); }
            50%      { opacity:.4; transform:scale(1.5); }
        }
    </style>
@endpush

@section('content')
    <script src="https://cdn.jsdelivr.net/npm/lightweight-charts@4.2.3/dist/lightweight-charts.standalone.production.js"></script>

    @php
        $routeChartData = route('trading.chart.data');
        // Pass PHP data to JS as JSON
        $jsConfig = json_encode([
            'symbol'     => $symbol,
            'expiry'     => $expiry,
            'tradeDate'  => $tradeDate,
            'midPoint'   => $midPoint,
            'strikes'    => $strikes,
            'chartDataUrl' => $routeChartData,
        ]);
    @endphp

    {{-- ── Top bar: minimal, just status + toggle ────────────────────────── --}}
    <div class="mx-auto max-w-[1800px] px-3 pt-3 pb-1">
        <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-2.5 shadow-sm">
            <div class="flex items-center gap-3">
            <span class="relative flex h-2.5 w-2.5">
                <span class="live-ring relative inline-flex h-2.5 w-2.5 rounded-full bg-green-500"></span>
            </span>
                <span class="text-sm font-semibold text-slate-800">NIFTY Strike Charts</span>
                <span id="statusBadge" class="rounded-full bg-teal-50 px-2.5 py-0.5 text-xs font-medium text-teal-700">Loading…</span>
            </div>
            <div class="flex items-center gap-2">
                {{-- Build-up legend pills --}}
                <div class="hidden items-center gap-1.5 sm:flex">
                    @foreach([['#dc2626','Short Build'],['#16a34a','Long Build'],['#eab308','Long Unwind'],['#1e3a8a','Short Cover']] as [$c,$l])
                        <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] text-slate-500">
                    <span class="h-2 w-2 rounded-full" style="background:{{$c}}"></span>{{$l}}
                </span>
                    @endforeach
                </div>
                <span class="hidden text-slate-300 sm:inline">|</span>
                <span class="text-[11px] text-slate-500">Last: <span id="lastUpdatedText" class="tabular-nums font-semibold text-slate-700">–</span></span>
                <button id="refreshNowBtn" title="Refresh now"
                    class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>Refresh
                </button>
                <button id="toggleFilterBtn"
                    class="inline-flex items-center gap-1 rounded-xl border border-teal-200 bg-teal-50 px-2.5 py-1.5 text-xs font-semibold text-teal-700 transition hover:bg-teal-100">
                    <svg id="toggleFilterIcon" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18M7 8h10M10 12h4"/>
                    </svg>
                    <span id="toggleFilterLabel">Filters</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ── Filter panel (collapsed by default) ───────────────────────────── --}}
    <div class="mx-auto max-w-[1800px] px-3">
        <div id="filterPanel" class="collapsed rounded-2xl border border-slate-200 bg-white shadow-sm"
            style="max-height: 600px;">
            <form id="chartFilterForm" class="space-y-4 px-5 py-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-slate-600">Symbol</label>
                        <input id="underlying_symbol" name="underlying_symbol" type="text" readonly
                            value="{{ $symbol }}"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-700 outline-none">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-slate-600">Expiry Date</label>
                        <input id="expiry_date" name="expiry_date" type="text" readonly
                            value="{{ $expiry }}"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-700 outline-none">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-slate-600">Trade Date</label>
                        <input type="date" id="trade_date" name="trade_date"
                            value="{{ $tradeDate }}"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none transition focus:border-teal-400 focus:ring-2 focus:ring-teal-400/10">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-slate-600">MidPoint</label>
                        <input type="number" step="0.01" id="midpoint" name="midpoint"
                            value="{{ $midPoint }}"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none transition focus:border-teal-400 focus:ring-2 focus:ring-teal-400/10">
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-xs font-semibold text-slate-600">Strikes (ATM ±3 pre-filled)</p>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
                        @for($i = 0; $i < 7; $i++)
                            <input type="number" step="1" name="strikes[]"
                                value="{{ $strikes[$i] ?? '' }}"
                                placeholder="Strike {{ $i+1 }}"
                                class="strike-field w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none transition focus:border-teal-400 focus:ring-2 focus:ring-teal-400/10">
                        @endfor
                    </div>
                </div>

                <div class="flex gap-2 pt-1">
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-br from-teal-700 to-teal-500 px-4 py-2.5 text-sm font-semibold text-white shadow-md transition hover:-translate-y-px hover:shadow-lg active:translate-y-0">
                        Generate Charts
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Charts area ── --}}
    <div class="mx-auto max-w-[1800px] px-3 pb-3 pt-2">
        <div id="emptyState" class="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-teal-50 text-teal-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 14l3-3 3 2 5-6"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-slate-700">Initialising charts…</p>
        </div>

        {{-- Two charts per row on xl --}}
        <div id="chartsGrid" class="hidden grid-cols-1 gap-3 xl:grid-cols-2"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            'use strict';

            // ── Config injected by PHP ───────────────────────────────────────────────
            var CFG = {!! $jsConfig !!};

            // ── Constants ────────────────────────────────────────────────────────────
            var BUILDUP_COLORS = {
                'Short Build': '#dc2626',
                'Long Build':  '#16a34a',
                'Long Unwind': '#eab308',
                'Short Cover': '#1e3a8a',
            };
            var CE_COLOR  = '#8f0cbd';   // Blue  – CE first-candle lines
            var PE_COLOR  = '#07ffab';   // Purple – PE first-candle lines
            var MID_COLOR = '#f97316';   // Orange – midpoint

            var SERIES_COLORS = {
                ce: {
                    line: '#2563eb',
                    up: 'rgba(37,99,235,0.75)',
                    down: 'rgba(37,99,235,0.75)',
                    border: 'rgba(37,99,235,0.95)',
                    wick: 'rgba(37,99,235,0.95)'
                },
                pe: {
                    line: '#7c3aed',
                    up: 'rgba(124,58,237,0.35)',
                    down: 'rgba(124,58,237,0.35)',
                    border: 'rgba(124,58,237,0.90)',
                    wick: 'rgba(124,58,237,0.90)'
                },
                midpoint: '#f97316'
            };

            // ── State ────────────────────────────────────────────────────────────────
            var autoRefreshTimer = null;
            var autoRefreshTimeout = null;
            var latestPayload    = null;
            var chartRegistry    = {};

            // ── DOM refs ─────────────────────────────────────────────────────────────
            var form            = document.getElementById('chartFilterForm');
            var tradeDateInput  = document.getElementById('trade_date');
            var midpointInput   = document.getElementById('midpoint');
            var chartsGrid      = document.getElementById('chartsGrid');
            var emptyState      = document.getElementById('emptyState');
            var refreshNowBtn   = document.getElementById('refreshNowBtn');
            var lastUpdatedText = document.getElementById('lastUpdatedText');
            var statusBadge     = document.getElementById('statusBadge');
            var filterPanel     = document.getElementById('filterPanel');
            var toggleFilterBtn = document.getElementById('toggleFilterBtn');
            var toggleLabel     = document.getElementById('toggleFilterLabel');

            var csrfMeta  = document.querySelector('meta[name="csrf-token"]');
            var csrfInput = document.querySelector('input[name="_token"]');
            var csrfToken = csrfMeta  ? csrfMeta.getAttribute('content') :
                csrfInput ? csrfInput.value : '';

            // ── Filter panel toggle (default: collapsed) ─────────────────────────────
            toggleFilterBtn.addEventListener('click', function () {
                var collapsed = filterPanel.classList.contains('collapsed');
                if (collapsed) {
                    filterPanel.style.maxHeight = filterPanel.scrollHeight + 'px';
                    filterPanel.classList.remove('collapsed');
                    toggleLabel.textContent = 'Hide Filters';
                } else {
                    filterPanel.style.maxHeight = '0';
                    filterPanel.classList.add('collapsed');
                    toggleLabel.textContent = 'Filters';
                }
            });

            // ── Form submit ──────────────────────────────────────────────────────────
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                loadCharts();
                startAutoRefresh();
            });

            refreshNowBtn.addEventListener('click', function () {
                loadCharts();
            });

            // ── Build payload from form ───────────────────────────────────────────────
            function buildPayload() {
                var strikes = Array.from(document.querySelectorAll('.strike-field'))
                    .map(function (el) { return el.value.trim(); })
                    .filter(Boolean);

                return {
                    underlying_symbol: CFG.symbol,
                    expiry_date:       document.getElementById('expiry_date').value,
                    trade_date:        tradeDateInput.value,
                    midpoint:          midpointInput.value || null,
                    strikes:           strikes,
                };
            }

            function clearAutoRefresh() {
                if (autoRefreshTimer) clearInterval(autoRefreshTimer);
                if (autoRefreshTimeout) clearTimeout(autoRefreshTimeout);
                autoRefreshTimer = null;
                autoRefreshTimeout = null;
            }

            function msUntilNextFiveMinuteMarkAt09() {
                var now = new Date();
                var next = new Date(now);

                next.setSeconds(9, 0);

                var minute = now.getMinutes();
                var nextMinute = Math.ceil((minute + (now.getSeconds() >= 9 ? 0.0001 : 0)) / 5) * 5;

                if (nextMinute >= 60) {
                    next.setHours(now.getHours() + 1);
                    next.setMinutes(0);
                } else {
                    next.setMinutes(nextMinute);
                }

                next.setSeconds(9, 0);

                if (next <= now) {
                    next = new Date(next.getTime() + 5 * 60 * 1000);
                }

                return next.getTime() - now.getTime();
            }



            // ── Fetch candle data ─────────────────────────────────────────────────────
            function loadCharts() {
                var payload = buildPayload();

                if (!payload.expiry_date || !payload.trade_date || payload.strikes.length === 0) {
                    statusBadge.textContent = 'Missing required fields';
                    return;
                }

                latestPayload = payload;
                statusBadge.textContent = 'Loading…';

                fetch(CFG.chartDataUrl, {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload),
                })
                    .then(function (res) {
                        if (!res.ok) {
                            return res.text().then(function (t) { throw new Error(res.status + ': ' + t); });
                        }
                        return res.json();
                    })
                    .then(function (result) {
                        if (!result.success) { throw new Error('Server returned success:false'); }
                        renderCharts(result);
                        lastUpdatedText.textContent = new Date().toLocaleTimeString('en-IN');
                        statusBadge.textContent     = 'Live';
                    })
                    .catch(function (err) {
                        statusBadge.textContent = 'Error – see console';
                        console.error('[Chart] fetch error:', err);
                    });
            }

            function startAutoRefresh() {
                clearAutoRefresh();

                autoRefreshTimeout = setTimeout(function () {
                    if (latestPayload) loadCharts();

                    autoRefreshTimer = setInterval(function () {
                        if (latestPayload) loadCharts();
                    }, 5 * 60 * 1000);
                }, msUntilNextFiveMinuteMarkAt09());
            }

            // ── Render charts ─────────────────────────────────────────────────────────
            function renderCharts(result) {
                var strikes = Object.keys(result.data || {});
                var existing = Object.keys(chartRegistry);

                if (strikes.length === 0) {
                    destroyAllCharts();
                    chartsGrid.innerHTML = '';
                    emptyState.classList.remove('hidden');
                    chartsGrid.classList.remove('grid');
                    chartsGrid.classList.add('hidden');
                    emptyState.querySelector('p').textContent = 'No CE/PE data for selected date & strikes.';
                    return;
                }

                emptyState.classList.add('hidden');
                chartsGrid.classList.remove('hidden');
                chartsGrid.classList.add('grid');

                existing.forEach(function (strike) {
                    if (!strikes.includes(strike)) {
                        destroyChart(strike);
                    }
                });

                strikes.forEach(function (strike, index) {
                    if (!chartRegistry[strike]) {
                        mountStrikeChart(
                            strike,
                            index,
                            result.data[strike],
                            result.firstCandle[strike] || {},
                            result.topMarkers[strike] || {},
                            result.midpoint
                        );
                    } else {
                        updateStrikeChart(
                            strike,
                            result.data[strike],
                            result.firstCandle[strike] || {},
                            result.topMarkers[strike] || {},
                            result.midpoint
                        );
                    }
                });
            }

            function mountStrikeChart(strike, index, strikeData, firstCandle, topMarkers, midpoint) {
                var card = buildChartCard(strike, index);
                chartsGrid.appendChild(card);

                createStrikeChart(strike, strikeData, firstCandle, topMarkers, midpoint);

                var entry = chartRegistry[strike];
                if (entry) {
                    entry.card = card;
                    entry.summaryEl = document.getElementById('summary-' + sanitizeStrike(strike));
                    entry.priceLines = {
                        ceHigh: null,
                        ceLow: null,
                        peHigh: null,
                        peLow: null,
                        midpoint: null
                    };
                    entry.userInteracted = false;

                    bindChartInteraction(entry);
                    updatePriceLines(entry, firstCandle, midpoint);
                    renderTopSummary(strike, strikeData, topMarkers);
                }
            }

            function bindChartInteraction(entry) {
                var markInteracted = function () {
                    entry.userInteracted = true;
                };

                entry.container.addEventListener('wheel', markInteracted, { passive: true });
                entry.container.addEventListener('mousedown', markInteracted);
                entry.container.addEventListener('touchstart', markInteracted, { passive: true });
            }

            function updatePriceLines(entry, firstCandle, midpoint) {
                if (!entry.priceLines) {
                    entry.priceLines = { ceHigh: null, ceLow: null, peHigh: null, peLow: null, midpoint: null };
                }

                if (entry.priceLines.ceHigh) entry.ceSeries.removePriceLine(entry.priceLines.ceHigh);
                if (entry.priceLines.ceLow) entry.ceSeries.removePriceLine(entry.priceLines.ceLow);
                if (entry.priceLines.peHigh) entry.peSeries.removePriceLine(entry.priceLines.peHigh);
                if (entry.priceLines.peLow) entry.peSeries.removePriceLine(entry.priceLines.peLow);
                if (entry.priceLines.midpoint) entry.ceSeries.removePriceLine(entry.priceLines.midpoint);

                entry.priceLines.ceHigh = null;
                entry.priceLines.ceLow = null;
                entry.priceLines.peHigh = null;
                entry.priceLines.peLow = null;
                entry.priceLines.midpoint = null;

                if (firstCandle.CE) {
                    entry.priceLines.ceHigh = addPriceLine(entry.ceSeries, firstCandle.CE.high, SERIES_COLORS.ce.line, 'CE H', 2, LightweightCharts.LineStyle.Dashed);
                    entry.priceLines.ceLow  = addPriceLine(entry.ceSeries, firstCandle.CE.low,  SERIES_COLORS.ce.line, 'CE L', 1, LightweightCharts.LineStyle.Dashed);
                }

                if (firstCandle.PE) {
                    entry.priceLines.peHigh = addPriceLine(entry.peSeries, firstCandle.PE.high, SERIES_COLORS.pe.line, 'PE H', 2, LightweightCharts.LineStyle.Dashed);
                    entry.priceLines.peLow  = addPriceLine(entry.peSeries, firstCandle.PE.low,  SERIES_COLORS.pe.line, 'PE L', 1, LightweightCharts.LineStyle.Dashed);
                }

                if (midpoint !== null && midpoint !== undefined && midpoint !== '') {
                    entry.priceLines.midpoint = addPriceLine(entry.ceSeries, midpoint, SERIES_COLORS.midpoint, 'Mid', 3, LightweightCharts.LineStyle.Solid);
                }
            }

            function updateStrikeChart(strike, strikeData, firstCandle, topMarkers, midpoint) {
                var entry = chartRegistry[strike];
                if (!entry) return;

                entry.ceSeries.setData((strikeData.CE || []).map(candleToSeries));
                entry.peSeries.setData((strikeData.PE || []).map(candleToSeries));

                entry.ceSeries.setMarkers(buildMarkers(strikeData.CE || [], topMarkers.CE || { oi: [], volume: [] }, 'CE'));
                entry.peSeries.setMarkers(buildMarkers(strikeData.PE || [], topMarkers.PE || { oi: [], volume: [] }, 'PE'));

                updatePriceLines(entry, firstCandle, midpoint);
                renderTopSummary(strike, strikeData, topMarkers);

                if (!entry.userInteracted) {
                    entry.chart.timeScale().fitContent();
                }
            }

            function destroyChart(strike) {
                var entry = chartRegistry[strike];
                if (!entry) return;

                window.removeEventListener('resize', entry.resizeHandler);

                if (entry.card && entry.card.parentNode) {
                    entry.card.parentNode.removeChild(entry.card);
                }

                entry.chart.remove();
                delete chartRegistry[strike];
            }

            // ── Build chart card ──────────────────────────────────────────────────────
            function buildChartCard(strike, index) {
                var wrapper = document.createElement('article');
                wrapper.className = 'overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm';

                wrapper.innerHTML =
                    '<div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/60 px-4 py-2">' +
                    '<div class="flex items-center gap-2">' +
                    '<span class="tabular-nums text-sm font-bold text-slate-900">Strike ' + strike + '</span>' +
                    '<span class="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-medium text-slate-500">Panel ' + (index+1) + '</span>' +
                    '</div>' +
                    '<div class="flex items-center gap-2">' +
                    '<span class="inline-flex items-center gap-1 text-[11px] text-slate-400">' +
                    '<span class="h-1.5 w-1.5 rounded-full" style="background:' + SERIES_COLORS.ce.line + '"></span>CE right axis' +
                    '</span>' +
                    '<span class="inline-flex items-center gap-1 text-[11px] text-slate-400">' +
                    '<span class="h-1.5 w-1.5 rounded-full" style="background:' + SERIES_COLORS.pe.line + '"></span>PE left axis' +
                    '</span>' +
                    '<button type="button" class="fullscreen-btn rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50" data-strike="' + strike + '">&#x26F6; Full</button>' +
                    '</div>' +
                    '</div>' +
                    '<div class="px-2 py-2">' +
                    '<div id="chart-' + sanitizeStrike(strike) + '" class="chart-box rounded-xl"></div>' +
                    '<div class="mt-1 flex flex-wrap gap-3 px-1 text-[10px] text-slate-400">' +
                    '<span>OI1/OI2/OI3 = top OI candles</span>' +
                    '<span>V1/V2/V3 = top volume candles</span>' +
                    '<span style="color:' + SERIES_COLORS.midpoint + '">&#8212; MidPoint</span>' +
                    '<span style="color:' + SERIES_COLORS.ce.line + '">&#8212; CE 1st candle</span>' +
                    '<span style="color:' + SERIES_COLORS.pe.line + '">&#8212; PE 1st candle</span>' +
                    '</div>' +
                    '<div class="chart-summary" id="summary-' + sanitizeStrike(strike) + '"></div>'+
                    '</div>';

                wrapper.querySelector('.fullscreen-btn').addEventListener('click', function () {
                    toggleFullscreen(wrapper, strike);
                });

                return wrapper;
            }

            // ── Create LightweightChart ───────────────────────────────────────────────
            function createStrikeChart(strike, strikeData, firstCandle, topMarkers, midpoint) {
                var containerId = 'chart-' + sanitizeStrike(strike);
                var container   = document.getElementById(containerId);
                if (!container) return;

                var chart = LightweightCharts.createChart(container, {
                    layout: {
                        background: { color: '#ffffff' },
                        textColor: '#334155',
                        fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
                    },
                    grid: {
                        vertLines: { color: '#ffffff' },
                        horzLines: { color: '#ffffff' },
                    },
                    width: container.clientWidth,
                    height: container.clientHeight,
                    crosshair: {
                        mode: LightweightCharts.CrosshairMode.Normal,
                    },
                    rightPriceScale: {
                        borderColor: '#e2e8f0',
                        scaleMargins: {
                            top: 0.08,
                            bottom: 0.08,
                        },
                        autoScale: true,
                    },
                    leftPriceScale: {
                        visible: false,
                    },
                    timeScale: {
                        borderColor: '#e2e8f0',
                        timeVisible: true,
                        secondsVisible: false,
                        rightOffset: 20,
                        barSpacing: 20,
                        minBarSpacing: 6,
                        tickMarkFormatter: function (time) {
                            var d = new Date(time * 1000);
                            var h = d.getHours().toString().padStart(2, '0');
                            var m = d.getMinutes().toString().padStart(2, '0');
                            return h + ':' + m;
                        },
                    },
                });

                // Both CE and PE use 'right' scale → single axis, candles overlay directly
                var ceSeries = chart.addCandlestickSeries({
                    title: 'CE',
                    priceScaleId: 'right',
                    upColor: SERIES_COLORS.ce.up,
                    downColor: SERIES_COLORS.ce.down,
                    borderColor: SERIES_COLORS.ce.border,
                    wickColor: SERIES_COLORS.ce.wick,
                    borderVisible: true,
                    wickVisible: true,
                    priceLineVisible: false,
                });

                var peSeries = chart.addCandlestickSeries({
                    title: 'PE',
                    priceScaleId: 'right',
                    upColor: SERIES_COLORS.pe.up,
                    downColor: SERIES_COLORS.pe.down,
                    borderColor: SERIES_COLORS.pe.border,
                    wickColor: SERIES_COLORS.pe.wick,
                    borderVisible: true,
                    wickVisible: true,
                    priceLineVisible: false,
                });

                ceSeries.setData((strikeData.CE || []).map(candleToSeries));
                peSeries.setData((strikeData.PE || []).map(candleToSeries));

                renderTopSummary(strike, strikeData, topMarkers);

                var tooltip = document.createElement('div');
                tooltip.className = 'chart-tooltip';
                container.style.position = 'relative';
                container.appendChild(tooltip);

                var ceMap = {};
                (strikeData.CE || []).forEach(function(c) { ceMap[c.time] = candleToSeries(c); });

                var peMap = {};
                (strikeData.PE || []).forEach(function(c) { peMap[c.time] = candleToSeries(c); });

                chart.subscribeCrosshairMove(function(param) {
                    if (!param || !param.point || param.point.x < 0 || param.point.y < 0 || !param.time) {
                        tooltip.style.display = 'none';
                        return;
                    }

                    var ceBar = ceMap[param.time] || null;
                    var peBar = peMap[param.time] || null;

                    if (!ceBar && !peBar) {
                        tooltip.style.display = 'none';
                        return;
                    }

                    function blockHtml(label, bar, tone) {
                        if (!bar) return '';
                        return `
            <div style="margin-bottom:8px;">
                <div style="font-weight:700; color:${tone}; margin-bottom:4px;">${label}</div>
                <div class="chart-tooltip-grid">
                    <div class="chart-tooltip-label">Time</div><div class="chart-tooltip-value">${bar._rawTime}</div>
                    <div class="chart-tooltip-label">O</div><div class="chart-tooltip-value">${bar.open}</div>
                    <div class="chart-tooltip-label">H</div><div class="chart-tooltip-value">${bar.high}</div>
                    <div class="chart-tooltip-label">L</div><div class="chart-tooltip-value">${bar.low}</div>
                    <div class="chart-tooltip-label">C</div><div class="chart-tooltip-value">${bar.close}</div>
                    <div class="chart-tooltip-label">OI</div><div class="chart-tooltip-value">${bar._oi.toLocaleString('en-IN')}</div>
                    <div class="chart-tooltip-label">Vol</div><div class="chart-tooltip-value">${bar._volume.toLocaleString('en-IN')}</div>
                    <div class="chart-tooltip-label">dOI</div><div class="chart-tooltip-value">${bar._diffOi.toLocaleString('en-IN')}</div>
                    <div class="chart-tooltip-label">dVol</div><div class="chart-tooltip-value">${bar._diffVol.toLocaleString('en-IN')}</div>
                    <div class="chart-tooltip-label">Build</div><div class="chart-tooltip-value">${bar._buildUp}</div>
                </div>
            </div>
        `;
                    }

                    tooltip.innerHTML =
                        blockHtml('CE', ceBar, SERIES_COLORS.ce.line) +
                        blockHtml('PE', peBar, SERIES_COLORS.pe.line);

                    tooltip.style.display = 'block';

                    var left = param.point.x + 14;
                    var top  = param.point.y + 14;

                    if (left + 240 > container.clientWidth) left = param.point.x - 250;
                    if (top + tooltip.offsetHeight > container.clientHeight) top = param.point.y - tooltip.offsetHeight - 14;

                    tooltip.style.left = left + 'px';
                    tooltip.style.top  = top + 'px';
                });

                // Top OI / Volume markers
                ceSeries.setMarkers(buildMarkers(strikeData.CE || [], topMarkers.CE || {}, 'CE'));
                peSeries.setMarkers(buildMarkers(strikeData.PE || [], topMarkers.PE || {}, 'PE'));

                chart.timeScale().fitContent();

                var allTimes = []
                    .concat((strikeData.CE || []).map(function(c){ return c.time; }))
                    .concat((strikeData.PE || []).map(function(c){ return c.time; }))
                    .sort();

                chart.timeScale().fitContent();

                var resizeHandler = function () {
                    if (container) {
                        chart.applyOptions({ width: container.clientWidth, height: container.clientHeight });
                    }
                };
                window.addEventListener('resize', resizeHandler);

                chartRegistry[strike] = {
                    chart: chart,
                    ceSeries: ceSeries,
                    peSeries: peSeries,
                    resizeHandler: resizeHandler,
                    container: container,
                    userInteracted: false,
                    priceLines: {
                        ceHigh: null,
                        ceLow: null,
                        peHigh: null,
                        peLow: null,
                        midpoint: null
                    }
                };
            }

            // ── Helpers ───────────────────────────────────────────────────────────────
            function candleToSeries(candle) {
                var color = BUILDUP_COLORS[candle.build_up] || '#94a3b8';
                return {
                    time: candle.time,
                    open: candle.open,
                    high: candle.high,
                    low: candle.low,
                    close: candle.close,
                    color: color,
                    borderColor: color,
                    wickColor: color,
                    _rawTime: candle.x || candle.time,
                    _oi: Number(candle.oi || 0),
                    _volume: Number(candle.volume || 0),
                    _diffOi: Number(candle.diff_oi || 0),
                    _diffVol: Number(candle.diff_vol || 0),
                    _buildUp: candle.build_up || 'Neutral'
                };
            }

            function addPriceLine(series, price, color, title, lineWidth, lineStyle) {
                return series.createPriceLine({
                    price: Number(price),
                    color: color,
                    lineWidth: lineWidth || 2,
                    lineStyle: lineStyle !== undefined ? lineStyle : LightweightCharts.LineStyle.Solid,
                    axisLabelVisible: true,
                    title: title,
                });
            }

            function buildMarkers(candles, meta, label) {
                var oiTimes  = meta.oi || [];
                var volTimes = meta.volume || [];
                var oiRank   = {};
                var volRank  = {};

                oiTimes.forEach(function(t, i)  { oiRank[t] = i + 1; });
                volTimes.forEach(function(t, i) { volRank[t] = i + 1; });

                var markers = [];
                candles.forEach(function(c) {
                    if (oiRank[c.time]) {
                        markers.push({
                            time: c.time,
                            position: 'aboveBar',
                            color: label === 'CE' ? '#0f766e' : '#7c3aed',
                            shape: 'circle',
                            text: String(oiRank[c.time])
                        });
                    }
                    if (volRank[c.time]) {
                        markers.push({
                            time: c.time,
                            position: 'belowBar',
                            color: label === 'CE' ? '#2563eb' : '#9a3412',
                            shape: 'circle',
                            text: String(volRank[c.time])
                        });
                    }
                });

                return markers;
            }

            function renderTopSummary(strike, strikeData, topMarkers) {
                var el = document.getElementById('summary-' + sanitizeStrike(strike));
                if (!el) return;

                function asMap(arr) {
                    var map = {};
                    arr.forEach(function(c) { map[c.time] = c; });
                    return map;
                }

                var ceMap = asMap(strikeData.CE || []);
                var peMap = asMap(strikeData.PE || []);

                function chips(times, map, cls, prefix, field) {
                    return (times || []).slice(0, 3).map(function(t, i) {
                        var row = map[t];
                        var stamp = row && row.x ? row.x.split(' ')[1] : t;
                        var value = row ? Number(row[field] || 0).toLocaleString('en-IN') : '-';
                        return '<span class="chart-chip ' + cls + '">' + prefix + (i + 1) + ' ' + stamp + ' · ' + value + '</span>';
                    }).join('');
                }
            }

            function destroyAllCharts() {
                Object.keys(chartRegistry).forEach(function (strike) {
                    destroyChart(strike);
                });
                chartRegistry = {};
            }

            function sanitizeStrike(s) { return String(s).replace(/\./g, '-'); }

            // FIX: Full-screen uses inset:0 and flex layout so chart fills 100% viewport
            function toggleFullscreen(card, strike) {
                var wasFS = card.classList.contains('full-screen-chart');

                card.classList.toggle('full-screen-chart');
                document.body.classList.toggle('overflow-hidden');
                document.documentElement.classList.toggle('overflow-hidden');

                if (!wasFS) {
                    card.style.width = '100vw';
                    card.style.height = '100vh';
                } else {
                    card.style.width = '';
                    card.style.height = '';
                }

                var btn = card.querySelector('.fullscreen-btn');
                btn.textContent = wasFS ? '⛶ Full' : '✕ Exit';

                var entry = chartRegistry[strike];
                if (entry) {
                    setTimeout(function () {
                        var rect = entry.container.getBoundingClientRect();
                        entry.chart.resize(rect.width, rect.height);
                        if (!entry.userInteracted) {
                            entry.chart.timeScale().fitContent();
                        }
                    }, 180);
                }
            }

            // ── Auto-load on page ready ───────────────────────────────────────────────
            // Trigger form submission automatically once DOM is ready
            setTimeout(function () {
                form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
                startAutoRefresh();
            }, 100);

        });
    </script>
@endsection
