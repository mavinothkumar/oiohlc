@extends('layouts.app')

@section('title', 'NIFTY Strike Charts')

@push('styles')
    <style>
        /* Chart host needs explicit pixel height for LightweightCharts to initialise */
        .chart-box { height: calc(50vh - 80px); min-height: 320px; width: 100%; }

        /* font-variant-numeric not in Tailwind v3 */
        .tabular-nums { font-variant-numeric: tabular-nums; }

        /* Full-screen overlay */
        .full-screen-chart {
            position: fixed !important;
            inset: 0 !important;          /* true full-screen – no gap */
            z-index: 9999;
            border-radius: 0 !important;
            background: #fff;
            display: flex !important;
            flex-direction: column;
        }
        .full-screen-chart .chart-box {
            flex: 1;
            height: 0 !important;         /* flex child fills remaining space */
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
            var CE_COLOR  = '#2563eb';   // Blue  – CE first-candle lines
            var PE_COLOR  = '#7c3aed';   // Purple – PE first-candle lines
            var MID_COLOR = '#f97316';   // Orange – midpoint

            // ── State ────────────────────────────────────────────────────────────────
            var autoRefreshTimer = null;
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
                clearInterval(autoRefreshTimer);
                autoRefreshTimer = setInterval(function () {
                    if (latestPayload) loadCharts();
                }, 300000);
            }

            // ── Render charts ─────────────────────────────────────────────────────────
            function renderCharts(result) {
                destroyAllCharts();
                chartsGrid.innerHTML = '';

                var strikes = Object.keys(result.data);

                if (strikes.length === 0) {
                    emptyState.classList.remove('hidden');
                    chartsGrid.classList.remove('grid');
                    chartsGrid.classList.add('hidden');
                    emptyState.querySelector('p').textContent = 'No CE/PE data for selected date & strikes.';
                    return;
                }

                emptyState.classList.add('hidden');
                chartsGrid.classList.remove('hidden');
                chartsGrid.classList.add('grid');

                strikes.forEach(function (strike, index) {
                    var card = buildChartCard(strike, index);
                    chartsGrid.appendChild(card);
                    createStrikeChart(
                        strike,
                        result.data[strike],
                        result.firstCandle[strike]  || {},
                        result.topMarkers[strike]   || {},
                        result.midpoint
                    );
                });
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
                    '<span class="h-1.5 w-1.5 rounded-full" style="background:' + CE_COLOR + '"></span>CE right axis' +
                    '</span>' +
                    '<span class="inline-flex items-center gap-1 text-[11px] text-slate-400">' +
                    '<span class="h-1.5 w-1.5 rounded-full" style="background:' + PE_COLOR + '"></span>PE left axis' +
                    '</span>' +
                    '<button type="button" class="fullscreen-btn rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50" data-strike="' + strike + '">&#x26F6; Full</button>' +
                    '</div>' +
                    '</div>' +
                    '<div class="px-2 py-2">' +
                    '<div id="chart-' + sanitizeStrike(strike) + '" class="chart-box rounded-xl"></div>' +
                    '<div class="mt-1 flex flex-wrap gap-3 px-1 text-[10px] text-slate-400">' +
                    '<span>&#9660; teal = top 5 OI</span>' +
                    '<span>&#9650; brown = top 5 volume</span>' +
                    '<span style="color:' + MID_COLOR + '">&#8212; MidPoint</span>' +
                    '<span style="color:' + CE_COLOR + '">&#8212; CE 1st candle</span>' +
                    '<span style="color:' + PE_COLOR + '">&#8212; PE 1st candle</span>' +
                    '</div>' +
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
                        textColor:  '#334155',
                        fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
                    },
                    grid: {
                        vertLines: { color: '#f1f5f9' },
                        horzLines: { color: '#f1f5f9' },
                    },
                    width:     container.clientWidth,
                    height:    container.clientHeight,
                    crosshair: { mode: LightweightCharts.CrosshairMode.Normal },

                    // FIX: Single shared price scale – CE and PE on the SAME axis
                    // so candles overlap and you can directly compare price levels.
                    rightPriceScale: {
                        borderColor:  '#e2e8f0',
                        scaleMargins: { top: 0.06, bottom: 0.06 },
                    },
                    leftPriceScale: {
                        visible: false,   // hide left axis entirely
                    },
                    timeScale: {
                        borderColor:    '#e2e8f0',
                        timeVisible:    true,
                        secondsVisible: false,
                        // Force IST timezone offset (+05:30 = 330 minutes)
                        tickMarkFormatter: function(time) {
                            var d = new Date(time * 1000);
                            var ist = new Date(d.getTime() + (5.5 * 3600 * 1000));
                            var h = ist.getUTCHours().toString().padStart(2,'0');
                            var m = ist.getUTCMinutes().toString().padStart(2,'0');
                            return h + ':' + m;
                        },
                    },
                });

                // Both CE and PE use 'right' scale → single axis, candles overlay directly
                var ceSeries = chart.addCandlestickSeries({
                    title:            'CE',
                    priceScaleId:     'right',
                    lastValueVisible: true,
                    priceLineVisible: false,
                    upColor:          '#16a34a',
                    downColor:        '#dc2626',
                    borderVisible:    true,
                    wickVisible:      true,
                    priceFormat:      { type: 'price', precision: 2, minMove: 0.05 },
                });

                var peSeries = chart.addCandlestickSeries({
                    title:            'PE',
                    priceScaleId:     'right',   // same scale as CE
                    lastValueVisible: true,
                    priceLineVisible: false,
                    upColor:          '#16a34a',
                    downColor:        '#dc2626',
                    borderVisible:    true,
                    wickVisible:      true,
                    priceFormat:      { type: 'price', precision: 2, minMove: 0.05 },
                });

                ceSeries.setData((strikeData.CE || []).map(candleToSeries));
                peSeries.setData((strikeData.PE || []).map(candleToSeries));

                // FIX: First 5-min candle lines drawn as price lines on their own series ONLY
                // (no cross-series bleeding). Use dashed style so they don't look like
                // regular candle wicks or grid lines.
                if (firstCandle.CE) {
                    addPriceLine(ceSeries, firstCandle.CE.high, CE_COLOR, 'CE H', 2, LightweightCharts.LineStyle.Dashed);
                    addPriceLine(ceSeries, firstCandle.CE.low,  CE_COLOR, 'CE L', 1, LightweightCharts.LineStyle.Dashed);
                }
                if (firstCandle.PE) {
                    addPriceLine(peSeries, firstCandle.PE.high, PE_COLOR, 'PE H', 2, LightweightCharts.LineStyle.Dashed);
                    addPriceLine(peSeries, firstCandle.PE.low,  PE_COLOR, 'PE L', 1, LightweightCharts.LineStyle.Dashed);
                }

                // MidPoint – solid thick orange line
                if (midpoint !== null && midpoint !== undefined && midpoint !== '') {
                    addPriceLine(ceSeries, midpoint, MID_COLOR, 'Mid', 3, LightweightCharts.LineStyle.Solid);
                }

                // Top OI / Volume markers
                ceSeries.setMarkers(buildMarkers(strikeData.CE || [], topMarkers.CE || {}, 'CE'));
                peSeries.setMarkers(buildMarkers(strikeData.PE || [], topMarkers.PE || {}, 'PE'));

                chart.timeScale().fitContent();

                var resizeHandler = function () {
                    if (container) {
                        chart.applyOptions({ width: container.clientWidth, height: container.clientHeight });
                    }
                };
                window.addEventListener('resize', resizeHandler);

                chartRegistry[strike] = { chart: chart, ceSeries: ceSeries, peSeries: peSeries, resizeHandler: resizeHandler, container: container };
            }

            // ── Helpers ───────────────────────────────────────────────────────────────
            function candleToSeries(candle) {
                var color = BUILDUP_COLORS[candle.build_up] || '#94a3b8';
                return { time: candle.time, open: candle.open, high: candle.high, low: candle.low, close: candle.close, color: color, borderColor: color, wickColor: color };
            }

            function addPriceLine(series, price, color, title, lineWidth, lineStyle) {
                series.createPriceLine({
                    price:            Number(price),
                    color:            color,
                    lineWidth:        lineWidth || 2,
                    lineStyle:        lineStyle !== undefined ? lineStyle : LightweightCharts.LineStyle.Solid,
                    axisLabelVisible: true,
                    title:            title,
                });
            }

            function buildMarkers(candles, meta, label) {
                var oiTimes  = new Set(meta.oi     || []);
                var volTimes = new Set(meta.volume || []);
                var markers  = [];
                candles.forEach(function (c) {
                    if (oiTimes.has(c.time))  markers.push({ time: c.time, position: 'aboveBar', color: '#0f766e', shape: 'arrowDown', text: label + ' OI'  });
                    if (volTimes.has(c.time)) markers.push({ time: c.time, position: 'belowBar', color: '#7c2d12', shape: 'arrowUp',   text: label + ' Vol' });
                });
                return markers;
            }

            function destroyAllCharts() {
                Object.values(chartRegistry).forEach(function (e) {
                    window.removeEventListener('resize', e.resizeHandler);
                    e.chart.remove();
                });
                chartRegistry = {};
            }

            function sanitizeStrike(s) { return String(s).replace(/\./g, '-'); }

            // FIX: Full-screen uses inset:0 and flex layout so chart fills 100% viewport
            function toggleFullscreen(card, strike) {
                var wasFS = card.classList.contains('full-screen-chart');
                card.classList.toggle('full-screen-chart');
                document.body.classList.toggle('overflow-hidden');
                var btn = card.querySelector('.fullscreen-btn');
                btn.textContent = wasFS ? '⛶ Full' : '✕ Exit';

                var entry = chartRegistry[strike];
                if (entry) {
                    setTimeout(function () {
                        entry.chart.applyOptions({ width: entry.container.clientWidth, height: entry.container.clientHeight });
                        entry.chart.timeScale().fitContent();
                    }, 50);
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
