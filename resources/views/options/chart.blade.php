@extends('layouts.app')
@section('title', 'Options Chart Step Reader')

@section('content')
    <div class="min-h-screen bg-gray-950 text-gray-100 p-4 md:p-6">

        {{-- PAGE HEADER --}}
        <div class="mb-6 flex flex-col gap-1">
            <h1 class="text-xl font-semibold tracking-tight text-white">Options Chart Step Reader</h1>
            <p class="text-sm text-gray-400">CE &amp; PE plotted on the same chart per strike · Colour = Build-Up type</p>
        </div>

        {{-- FILTER BAR --}}
        <div class="rounded-xl border border-gray-800 bg-gray-900 p-4 mb-6 flex flex-col sm:flex-row gap-4 items-end">
            <div class="flex-1 min-w-[200px]">
                <label for="expiry_select" class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wide">Expiry Date</label>
                <select id="expiry_select"
                    class="w-full rounded-lg bg-gray-800 border border-gray-700 text-white text-sm px-3 py-2
                       focus:outline-none focus:ring-2 focus:ring-indigo-500 appearance-none cursor-pointer transition">
                    <option value="">— Select Expiry —</option>
                    @foreach($expiries as $expiry)
                        <option value="{{ $expiry }}">{{ \Carbon\Carbon::parse($expiry)->format('d M Y') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[150px]">
                <label for="interval_select" class="block text-xs font-medium text-gray-400 mb-1.5 uppercase tracking-wide">Interval</label>
                <select id="interval_select"
                    class="w-full rounded-lg bg-gray-800 border border-gray-700 text-white text-sm px-3 py-2
                       focus:outline-none focus:ring-2 focus:ring-indigo-500 appearance-none cursor-pointer transition">
                    <option value="5minute">5 Min</option>
                    <option value="day">Day</option>
                </select>
            </div>
            <div class="shrink-0">
                <button id="load_btn" disabled
                    class="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500
                       active:bg-indigo-700 text-white text-sm font-medium transition disabled:opacity-40
                       disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582M20 20v-5h-.581M5.635 19A9 9 0 104.582 9H4"/>
                    </svg>
                    Load Charts
                </button>
            </div>
        </div>

        {{-- INFO BAR --}}
        <div id="info_bar" class="hidden mb-5 rounded-xl border border-gray-800 bg-gray-900 px-4 py-3">
            <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
            <span class="text-gray-400">Period:
                <span id="info_from" class="font-medium text-gray-100">—</span>
                <span class="text-gray-600 mx-1">→</span>
                <span id="info_to" class="font-medium text-gray-100">—</span>
            </span>
                <span class="text-gray-400">NIFTY Low: <span id="info_low" class="font-medium text-emerald-400">—</span></span>
                <span class="text-gray-400">NIFTY High: <span id="info_high" class="font-medium text-rose-400">—</span></span>
                <span class="text-gray-400">Strikes: <span id="info_strikes" class="font-medium text-amber-400">—</span></span>
            </div>
        </div>

        {{-- LEGEND --}}
        <div class="mb-5 flex flex-wrap gap-4 text-xs text-gray-300 items-center">
            <span class="font-medium text-gray-500 uppercase tracking-wide mr-1">Build-Up:</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm inline-block" style="background:#10b981"></span>Long Build</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm inline-block" style="background:#ef4444"></span>Short Build</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm inline-block" style="background:#fbbf24"></span>Long Unwind</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm inline-block" style="background:#1e3a8a;border:1px solid #60a5fa"></span>Short Cover</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm inline-block" style="background:#6b7280"></span>Neutral</span>
            <span class="ml-auto text-gray-600 text-[11px] italic">Scroll to zoom · Drag to pan · Click ⛶ for fullscreen</span>
        </div>

        {{-- SPINNER --}}
        <div id="loading_state" class="hidden flex flex-col items-center justify-center py-24 gap-3">
            <svg class="animate-spin h-8 w-8 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            <span class="text-sm text-gray-400">Fetching chart data…</span>
        </div>

        {{-- ERROR --}}
        <div id="error_banner" class="hidden mb-4 rounded-lg border border-rose-800 bg-rose-950/60 px-4 py-3 text-sm text-rose-300"></div>

        {{-- CHART GRID --}}
        <div id="chart_grid" class="grid grid-cols-1 xl:grid-cols-2 gap-5"></div>

        {{-- FULLSCREEN MODAL --}}
        <div id="fs_modal" class="fixed inset-0 z-50 hidden bg-gray-950 flex flex-col p-4 md:p-6">
            <div class="flex items-center justify-between mb-3 shrink-0">
                <div>
                    <h2 id="fs_title" class="text-base font-semibold text-white"></h2>
                    <p class="text-[11px] text-gray-500 mt-0.5">CE &amp; PE on the same chart · Scroll to zoom · Drag to pan</p>
                </div>
                <div class="flex items-center gap-2">
                    <button id="fs_fit" class="px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs transition">Fit All</button>
                    <button id="fs_close" class="p-2 rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-300 hover:text-white transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div id="fs_legend" class="flex flex-wrap gap-3 text-xs text-gray-400 mb-3 shrink-0"></div>
            <div id="fs_container" class="flex-1 rounded-xl border border-gray-800 overflow-hidden min-h-0"></div>
        </div>

    </div>
@endsection

@push('scripts')
    <script src="https://unpkg.com/lightweight-charts@4.2.0/dist/lightweight-charts.standalone.production.js"></script>
    <script>
        /* ═══════════════════════════════════════════════════════
           ROUTES
        ═══════════════════════════════════════════════════════ */
        const ROUTES = {
            expiryRange : '{{ route("options.chart.expiry.range") }}',
            chartData   : '{{ route("options.chart.chart.data") }}',
        };

        /* ═══════════════════════════════════════════════════════
           IST TIMEZONE FIX
           DB timestamps are stored as IST strings with no timezone
           marker, e.g. "2024-01-15 09:15:00".
           new Date("2024-01-15 09:15:00") → browser parses as LOCAL
           time (IST) → internally 03:45 UTC → LW shows 03:45. Wrong.
           Fix: append 'Z' so the string is forced to parse as UTC.
           LW then receives 09:15 UTC and displays 09:15. Correct.
        ═══════════════════════════════════════════════════════ */

        /* ═══════════════════════════════════════════════════════
           BUILD-UP COLOR MAP
        ═══════════════════════════════════════════════════════ */
        const BUILD_COLOR = {
            'Long Build'  : { up: '#10b981', down: '#059669', wick: '#10b981', border: '#059669' },
            'Short Build' : { up: '#ef4444', down: '#dc2626', wick: '#ef4444', border: '#dc2626' },
            'Long Unwind' : { up: '#fbbf24', down: '#d97706', wick: '#fbbf24', border: '#d97706' },
            'Short Cover' : { up: '#3b82f6', down: '#1e3a8a', wick: '#60a5fa', border: '#3b82f6' },
            'Neutral'     : { up: '#6b7280', down: '#4b5563', wick: '#6b7280', border: '#6b7280' },
        };

        /* ═══════════════════════════════════════════════════════
           BASE CHART OPTIONS
        ═══════════════════════════════════════════════════════ */
        const BASE_CHART_OPTS = {
            layout: {
                background : { type: 'solid', color: '#0f172a' },
                textColor  : '#94a3b8',
                fontSize   : 11,
            },
            grid: {
                vertLines : { color: 'rgba(255,255,255,0.04)' },
                horzLines : { color: 'rgba(255,255,255,0.04)' },
            },
            crosshair: {
                mode     : LightweightCharts.CrosshairMode.Normal,
                vertLine : { color: '#818cf8', width: 1, style: 0, labelBackgroundColor: '#4f46e5' },
                horzLine : { color: '#818cf8', width: 1, style: 0, labelBackgroundColor: '#4f46e5' },
            },
            rightPriceScale : {
                borderColor  : '#1e293b',
                scaleMargins : { top: 0.08, bottom: 0.08 },
            },
            timeScale : {
                borderColor    : '#1e293b',
                timeVisible    : true,
                secondsVisible : false,
                rightOffset    : 8,
                barSpacing     : 14,
                minBarSpacing  : 3,
            },
            handleScroll : { mouseWheel: true, pressedMouseMove: true, horzTouchDrag: true, vertTouchDrag: false },
            handleScale  : { mouseWheel: true, pinch: true, axisPressedMouseMove: { time: true, price: true } },
        };

        /* ═══════════════════════════════════════════════════════
           STATE
        ═══════════════════════════════════════════════════════ */
        let chartInstances   = {};
        let fsChart          = null;
        let fsRo             = null;
        let currentChartData = {};
        let currentInterval  = 'day';

        /* ═══════════════════════════════════════════════════════
           DOM HELPERS
        ═══════════════════════════════════════════════════════ */
        const $  = id => document.getElementById(id);
        const el = (tag, cls, html) => {
            const n = document.createElement(tag);
            if (cls) n.className = cls;
            if (html !== undefined) n.innerHTML = html;
            return n;
        };
        const showError   = msg => { $('error_banner').textContent = msg; $('error_banner').classList.remove('hidden'); };
        const hideError   = ()  => $('error_banner').classList.add('hidden');
        const showLoading = v   => {
            $('loading_state').classList.toggle('hidden', !v);
            $('chart_grid').classList.toggle('hidden', v);
        };
        const fmtDate = d => {
            const dt = new Date(d);
            return isNaN(dt) ? d : dt.toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric' });
        };
        const numFmt = n => Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        /* ═══════════════════════════════════════════════════════
           CANDLE CONVERSION
           - day interval  → 'YYYY-MM-DD' string (LW handles as date)
           - intraday      → unix seconds adjusted for IST so the
                             chart axis shows 09:15 instead of 03:45
        ═══════════════════════════════════════════════════════ */
        function toColoredCandles(candles, interval) {
            return candles
                .map(c => {
                    const col = BUILD_COLOR[c.build_up] ?? BUILD_COLOR['Neutral'];

                    let time;
                    if (interval === 'day') {
                        // Date-only string — LW renders just the date, no timezone issue
                        time = c.x.substring(0, 10);
                    } else {
                        // DB value is "YYYY-MM-DD HH:MM:SS" in IST with no TZ marker.
                        // Appending 'Z' forces JS to parse it as UTC, preventing the
                        // browser from applying the local (IST) offset a second time.
                        // Result: LW receives and displays the correct IST time (09:15).
                        const normalized = c.x;
                        time = Math.floor(new Date(normalized).getTime() / 1000);
                    }

                    return {
                        time,
                        open        : parseFloat(c.open),
                        high        : parseFloat(c.high),
                        low         : parseFloat(c.low),
                        close       : parseFloat(c.close),
                        color       : parseFloat(c.close) >= parseFloat(c.open) ? col.up   : col.down,
                        wickColor   : col.wick,
                        borderColor : col.border,
                        // kept for tooltip only
                        _build_up   : c.build_up ?? 'Neutral',
                    };
                })
                .sort((a, b) => (a.time > b.time ? 1 : -1));
        }

        /* ═══════════════════════════════════════════════════════
           TOOLTIP — shows only O/H/L/C + Build-Up for CE & PE
           Positioned to never overlap the crosshair time label
        ═══════════════════════════════════════════════════════ */
        function attachTooltip(chart, ceSeries, peSeries, container) {
            const tip = el('div',
                'absolute z-30 pointer-events-none hidden rounded-xl border border-gray-700/80 ' +
                'bg-gray-900/95 shadow-2xl text-xs leading-relaxed',
                '');
            tip.style.cssText += 'min-width:160px;padding:10px 12px;';
            container.style.position = 'relative';
            container.appendChild(tip);

            chart.subscribeCrosshairMove(param => {
                if (
                    !param.time ||
                    !param.point ||
                    param.point.x < 0 ||
                    param.point.y < 0
                ) {
                    tip.classList.add('hidden');
                    return;
                }

                const ceBar = ceSeries ? param.seriesData.get(ceSeries) : null;
                const peBar = peSeries ? param.seriesData.get(peSeries) : null;
                if (!ceBar && !peBar) { tip.classList.add('hidden'); return; }

                const renderBar = (bar, label) => {
                    if (!bar) return '';
                    const col = BUILD_COLOR[bar._build_up] ?? BUILD_COLOR['Neutral'];
                    const isUp = bar.close >= bar.open;
                    return `
            <div class="mb-2 last:mb-0">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="font-bold tracking-wide" style="color:${col.up}">${label}</span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded font-medium"
                          style="background:${col.up}22;color:${col.up}">${bar._build_up}</span>
                </div>
                <div class="grid grid-cols-2 gap-x-3 gap-y-0.5 text-gray-300">
                    <span class="text-gray-500">O</span><span class="text-right">${numFmt(bar.open)}</span>
                    <span class="text-gray-500">H</span><span class="text-right text-emerald-400">${numFmt(bar.high)}</span>
                    <span class="text-gray-500">L</span><span class="text-right text-rose-400">${numFmt(bar.low)}</span>
                    <span class="text-gray-500">C</span>
                    <span class="text-right font-semibold ${isUp ? 'text-emerald-400' : 'text-rose-400'}">${numFmt(bar.close)}</span>
                </div>
            </div>`;
                };

                let html = renderBar(ceBar, 'CE');
                if (ceBar && peBar) html += '<div class="my-2 border-t border-gray-800"></div>';
                html += renderBar(peBar, 'PE');
                tip.innerHTML = html;
                tip.classList.remove('hidden');

                // Keep tooltip visible and away from the right-side price axis (last 60px)
                const cW  = container.clientWidth;
                const cH  = container.clientHeight;
                const tW  = 180;
                const tH  = 160;
                let left  = param.point.x + 16;
                let top   = param.point.y - tH / 2;

                // Flip left if too close to right edge or price scale
                if (left + tW > cW - 65) left = param.point.x - tW - 12;
                // Clamp vertically
                top = Math.max(8, Math.min(top, cH - tH - 8));

                tip.style.left = left + 'px';
                tip.style.top  = top  + 'px';
            });
        }

        /* ═══════════════════════════════════════════════════════
           RESIZE OBSERVER
        ═══════════════════════════════════════════════════════ */
        function watchResize(container, chart) {
            const ro = new ResizeObserver(entries => {
                for (const e of entries) {
                    chart.resize(e.contentRect.width, e.contentRect.height);
                }
            });
            ro.observe(container);
            return ro;
        }

        /* ═══════════════════════════════════════════════════════
           CORE: one LW chart, two CandlestickSeries (CE + PE)
        ═══════════════════════════════════════════════════════ */
        function buildStrikeChart(container, data, interval, height) {
            const chart = LightweightCharts.createChart(container, {
                ...BASE_CHART_OPTS,
                width  : container.clientWidth || 600,
                height : height,
            });

            const ceSeries = data.CE?.length
                ? (() => {
                    const s = chart.addCandlestickSeries({
                        upColor: '#10b981', downColor: '#ef4444',
                        borderVisible: true, wickVisible: true,
                        priceLineVisible: false, lastValueVisible: true,
                        title: 'CE',
                    });
                    s.setData(toColoredCandles(data.CE, interval));
                    return s;
                })()
                : null;

            const peSeries = data.PE?.length
                ? (() => {
                    const s = chart.addCandlestickSeries({
                        upColor: '#10b981', downColor: '#ef4444',
                        borderVisible: true, wickVisible: true,
                        priceLineVisible: false, lastValueVisible: true,
                        title: 'PE',
                    });
                    s.setData(toColoredCandles(data.PE, interval));
                    return s;
                })()
                : null;

            chart.timeScale().fitContent();
            attachTooltip(chart, ceSeries, peSeries, container);

            return { chart, ceSeries, peSeries };
        }

        /* ═══════════════════════════════════════════════════════
           FULLSCREEN
        ═══════════════════════════════════════════════════════ */
        function openFullscreen(key) {
            const data = currentChartData[key];
            if (!data) return;

            if (fsChart) { fsChart.remove(); fsChart = null; }
            if (fsRo)    { fsRo.disconnect(); fsRo = null; }

            $('fs_title').textContent = `Strike ${data.strike} — CE & PE`;
            $('fs_legend').innerHTML = `
        <span class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-sm inline-block bg-indigo-400"></span>CE · ${data.CE?.length ?? 0} candles
        </span>
        <span class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-sm inline-block bg-orange-400"></span>PE · ${data.PE?.length ?? 0} candles
        </span>`;

            $('fs_modal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';

            const cont = $('fs_container');
            cont.innerHTML = '';

            const { chart } = buildStrikeChart(cont, data, currentInterval, cont.clientHeight || 600);
            fsChart = chart;
            fsRo    = watchResize(cont, chart);

            $('fs_fit').onclick = () => chart.timeScale().fitContent();
        }

        function closeFullscreen() {
            if (fsChart) { fsChart.remove(); fsChart = null; }
            if (fsRo)    { fsRo.disconnect(); fsRo = null; }
            $('fs_modal').classList.add('hidden');
            document.body.style.overflow = '';
        }
        $('fs_close').addEventListener('click', closeFullscreen);
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeFullscreen(); });

        /* ═══════════════════════════════════════════════════════
           RENDER ALL STRIKE CARDS
        ═══════════════════════════════════════════════════════ */
        function renderCharts(chartData, interval) {
            const grid = $('chart_grid');
            grid.innerHTML = '';

            Object.values(chartInstances).forEach(inst => {
                inst.chart?.remove();
                inst.ro?.disconnect();
            });
            chartInstances   = {};
            currentChartData = {};

            if (!chartData?.length) {
                grid.innerHTML = `<div class="col-span-full rounded-xl border border-gray-800 bg-gray-900
            p-10 text-center text-gray-500 text-sm">
            No OHLC data found for the selected expiry and strike range.</div>`;
                return;
            }

            chartData.forEach(item => {
                const key     = 'strike_' + item.strike;
                currentChartData[key] = item;
                const ceCount = item.CE?.length || 0;
                const peCount = item.PE?.length || 0;

                const card = el('div', 'rounded-xl border border-gray-800 bg-gray-900 p-3 flex flex-col gap-2');

                /* Header */
                const head = el('div', 'flex items-center justify-between shrink-0');
                head.innerHTML = `
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-bold text-white">${item.strike}</span>
                <span class="text-xs px-2 py-0.5 rounded bg-indigo-900/40 text-indigo-300">CE ${ceCount}</span>
                <span class="text-xs px-2 py-0.5 rounded bg-orange-900/40 text-orange-300">PE ${peCount}</span>
            </div>`;

                const btnGroup = el('div', 'flex items-center gap-1.5');

                const fitBtn = el('button',
                    'px-2.5 py-1 rounded bg-gray-800 hover:bg-gray-700 text-gray-400 text-xs transition', 'Fit');

                const fsBtn = el('button',
                    'p-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-400 hover:text-white transition',
                    `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor" stroke-width="2">
               <path stroke-linecap="round" stroke-linejoin="round"
                 d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5v-4m0 4h-4m4 0l-5-5"/>
             </svg>`);
                fsBtn.title = 'Fullscreen';
                fsBtn.addEventListener('click', () => openFullscreen(key));

                btnGroup.appendChild(fitBtn);
                btnGroup.appendChild(fsBtn);
                head.appendChild(btnGroup);
                card.appendChild(head);

                /* Chart container */
                const chartCont = el('div', 'w-full rounded-lg overflow-hidden border border-gray-800/60');
                chartCont.style.height = '300px';
                card.appendChild(chartCont);
                grid.appendChild(card);

                requestAnimationFrame(() => {
                    const { chart, ceSeries, peSeries } = buildStrikeChart(chartCont, item, interval, 300);
                    const ro = watchResize(chartCont, chart);
                    fitBtn.addEventListener('click', () => chart.timeScale().fitContent());
                    chartInstances[key] = { chart, ceSeries, peSeries, ro };
                });
            });
        }

        /* ═══════════════════════════════════════════════════════
           FETCH PIPELINE
        ═══════════════════════════════════════════════════════ */
        async function loadData() {
            hideError();
            const expiry   = $('expiry_select').value;
            const interval = $('interval_select').value;
            if (!expiry) return showError('Please select an expiry date.');
            currentInterval = interval;

            showLoading(true);
            $('info_bar').classList.add('hidden');
            $('load_btn').disabled = true;

            try {
                const rr = await fetch(
                    `${ROUTES.expiryRange}?expiry_date=${encodeURIComponent(expiry)}`,
                    { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
                );
                const rj = await rr.json();
                if (!rr.ok || !rj.success) throw new Error(rj.message || 'Failed to fetch expiry range');

                const { from_date, to_date, min_low, max_high, strikes } = rj;
                $('info_from').textContent    = fmtDate(from_date);
                $('info_to').textContent      = fmtDate(to_date);
                $('info_low').textContent     = numFmt(min_low);
                $('info_high').textContent    = numFmt(max_high);
                $('info_strikes').textContent = `${strikes[0]} – ${strikes[strikes.length - 1]} (${strikes.length} strikes)`;
                $('info_bar').classList.remove('hidden');

                const params = new URLSearchParams({ expiry_date: expiry, from_date, to_date, interval });
                strikes.forEach(s => params.append('strikes[]', s));

                const dr = await fetch(
                    `${ROUTES.chartData}?${params}`,
                    { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
                );
                const dj = await dr.json();
                if (!dr.ok || !dj.success) throw new Error(dj.message || 'Failed to fetch chart data');

                showLoading(false);
                renderCharts(dj.chart_data, interval);

            } catch (err) {
                showLoading(false);
                showError('Error: ' + err.message);
            } finally {
                $('load_btn').disabled = !$('expiry_select').value;
            }
        }

        /* ═══════════════════════════════════════════════════════
           EVENTS
        ═══════════════════════════════════════════════════════ */
        $('expiry_select').addEventListener('change', () => {
            $('load_btn').disabled = !$('expiry_select').value;
        });
        $('load_btn').addEventListener('click', loadData);
    </script>
@endpush
