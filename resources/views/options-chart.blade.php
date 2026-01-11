{{-- resources/views/options-chart.blade.php --}}
@extends('layouts.app')

@section('title')
    Chart
@endsection
@section('content')
    <div class="max-w-full mx-auto p-4 space-y-4">
        <div class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-sm mb-1">Underlying</label>
                <input id="underlying" type="text"
                    value="NIFTY"
                    class="border rounded px-3 py-1 text-sm">
            </div>

            <div>
                <label class="block text-sm mb-1">Date</label>
                <input id="date" type="date"
                    class="border rounded px-3 py-1 text-sm">
            </div>

            <div>
                <label class="block text-sm mb-1">Expiry</label>
                <select id="expiry"
                    class="border rounded px-3 py-1 text-sm">
                    <option value="">Select date first</option>
                </select>
            </div>

            <div>
                <label class="block text-sm mb-1">CE Strike</label>
                <input id="ce_instrument_key" type="text"
                    class="border rounded px-3 py-1 text-sm"
                    placeholder="CE Strike">
            </div>

            <div>
                <label class="block text-sm mb-1">PE Strike</label>
                <input id="pe_instrument_key" type="text"
                    class="border rounded px-3 py-1 text-sm"
                    placeholder="PE Strike">
            </div>

            <div>
                <button id="loadChartBtn"
                    class="bg-blue-600 text-white px-4 py-2 rounded text-sm">
                    Load chart
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
            <div>
                <h2 class="text-sm font-medium mb-1">CE</h2>
                <div id="ce-chart-container" class="border rounded h-[700px]"></div>
            </div>
            <div>
                <h2 class="text-sm font-medium mb-1">PE</h2>
                <div id="pe-chart-container" class="border rounded h-[700px]"></div>
            </div>
        </div>
    </div>

    {{-- Loader overlay --}}
    <div id="loader" class="fixed inset-0 bg-gray bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-8 shadow-lg text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
            <p class="text-gray-700 font-medium">Loading...</p>
        </div>
    </div>


    {{-- Lightweight Charts v5 --}}
    <script src="https://unpkg.com/lightweight-charts/dist/lightweight-charts.standalone.production.js"></script>
    <script src="https://unpkg.com/lightweight-charts-line-tools@1.0.5/dist/lightweight-charts-line-tools.umd.js"></script>

    <script>

        function showLoader() {
            const loader = document.getElementById('loader');
            if (loader) loader.classList.remove('hidden');
        }

        function hideLoader() {
            const loader = document.getElementById('loader');
            if (loader) loader.classList.add('hidden');
        }
        document.addEventListener('DOMContentLoaded', () => {
            const underlyingEl = document.getElementById('underlying');
            const dateEl       = document.getElementById('date');
            const expiryEl     = document.getElementById('expiry');
            const ceKeyEl      = document.getElementById('ce_instrument_key');
            const peKeyEl      = document.getElementById('pe_instrument_key');
            const loadBtn      = document.getElementById('loadChartBtn');

            const ceContainer  = document.getElementById('ce-chart-container');
            const peContainer  = document.getElementById('pe-chart-container');

            let ceChart = null, ceSeries = null;
            let peChart = null, peSeries = null;

            // line series for previous-day OHLC (created lazily)
            let cePrevLines = null;
            let pePrevLines = null;

            let ceMirrorPeLines = null; // PE prev OHLC on CE chart (grey)
            let peMirrorCeLines = null; // CE prev OHLC on PE chart (grey)

            function createChart(container, upColor) {
                const rect = container.getBoundingClientRect();

                const chart = LightweightCharts.createChart(container, {
                    width: rect.width,
                    height: rect.height,
                    layout: { background: { color: '#ffffff' }, textColor: '#111827' },
                    rightPriceScale: { borderColor: '#e5e7eb' },
                    timeScale: {
                        borderColor: '#e5e7eb',
                        timeVisible: true,
                        secondsVisible: false,
                        timezone: 'Asia/Kolkata',
                    },
                    grid: {
                        vertLines:  { color: '#ffffff', visible: false },  // hide vertical grid
                        horzLines:  { color: '#ffffff', visible: false },  // hide horizontal grid
                    },
                    crosshair: { mode: LightweightCharts.CrosshairMode.Normal },
                });

                const series = chart.addSeries(LightweightCharts.CandlestickSeries, {
                    upColor: upColor,
                    downColor: '#dc2626',
                    borderUpColor: upColor,
                    borderDownColor: '#dc2626',
                    wickUpColor: upColor,
                    wickDownColor: '#dc2626',
                });

                new ResizeObserver(entries => {
                    if (!entries.length) return;
                    const cr = entries[0].contentRect;
                    chart.applyOptions({ width: cr.width, height: cr.height });
                }).observe(container);

                return { chart, series };
            }

            function timeToLocal(originalTime) {
                const d = new Date(originalTime * 1000);
                return Date.UTC(
                    d.getFullYear(),
                    d.getMonth(),
                    d.getDate(),
                    d.getHours(),
                    d.getMinutes(),
                    d.getSeconds(),
                    d.getMilliseconds()
                ) / 1000;
            }

            function normalize(data) {
                if (!Array.isArray(data)) return [];
                const out = data
                    .filter(c => c && c.time != null)
                    .map(c => ({
                        time: timeToLocal(c.time),
                        open: Number(c.open),
                        high: Number(c.high),
                        low:  Number(c.low),
                        close:Number(c.close),
                    }))
                    .sort((a, b) => a.time - b.time);
                return out;
            }

            function ensurePrevLines() {
                if (!cePrevLines && ceChart) {
                    cePrevLines = {
                        open:  ceChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#22c55e',
                            lineWidth: 3,
                            lineStyle: LightweightCharts.LineStyle.Solid,
                            priceLineVisible: false,
                        }),
                        high:  ceChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#000',
                            lineWidth: 3,
                            lineStyle: LightweightCharts.LineStyle.Solid,
                            priceLineVisible: false,
                        }),
                        low:   ceChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#000',
                            lineWidth: 3,
                            lineStyle: LightweightCharts.LineStyle.Solid,
                            priceLineVisible: false,
                        }),
                        close: ceChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#000',
                            lineWidth: 3,
                            lineStyle: LightweightCharts.LineStyle.Solid,
                            priceLineVisible: false,
                        }),
                    };
                }
                if (!pePrevLines && peChart) {
                    pePrevLines = {
                        open:  peChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#22c55e',
                            lineWidth: 3,
                            lineStyle: LightweightCharts.LineStyle.Solid,
                            priceLineVisible: false,
                        }),
                        high:  peChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#000',
                            lineWidth: 3,
                            lineStyle: LightweightCharts.LineStyle.Solid,
                            priceLineVisible: false,
                        }),
                        low:   peChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#000',
                            lineWidth: 3,
                            lineStyle: LightweightCharts.LineStyle.Solid,
                            priceLineVisible: false,
                        }),
                        close: peChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#000',
                            lineWidth: 3,
                            lineStyle: LightweightCharts.LineStyle.Solid,
                            priceLineVisible: false,
                        }),
                    };
                }
                // NEW: mirrored grey lines
                if (!ceMirrorPeLines) {
                    ceMirrorPeLines = {
                        high:  ceChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#1265e7',              // grey
                            lineWidth: 1,
                            lineStyle: LightweightCharts.LineStyle.Solid,
                            priceLineVisible: false,
                        }),
                        low:   ceChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#1265e7',
                            lineWidth: 1,
                            lineStyle: LightweightCharts.LineStyle.Solid,
                            priceLineVisible: false,
                        }),
                        close: ceChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#1265e7',
                            lineWidth: 1,
                            lineStyle: LightweightCharts.LineStyle.Solid,
                            priceLineVisible: false,
                        }),
                    };
                }

                if (!peMirrorCeLines) {
                    peMirrorCeLines = {
                        high:  peChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#1265e7',
                            lineWidth: 1,
                            lineStyle: LightweightCharts.LineStyle.Solid,
                            priceLineVisible: false,
                        }),
                        low:   peChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#1265e7',
                            lineWidth: 1,
                            lineStyle: LightweightCharts.LineStyle.Solid,
                            priceLineVisible: false,
                        }),
                        close: peChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#1265e7',
                            lineWidth: 1,
                            lineStyle: LightweightCharts.LineStyle.Solid,
                            priceLineVisible: false,
                        }),
                    };
                }
            }

            // Auto-load expiries when date changes
            dateEl.addEventListener('change', async () => {
                const date       = dateEl.value;
                const underlying = underlyingEl.value;

                if (!date || !underlying) return;

                showLoader();

                const url = new URL("{{ route('api.expiries') }}", window.location.origin);
                url.searchParams.set('underlying_symbol', underlying);
                url.searchParams.set('date', date);

                try {
                    const res  = await fetch(url);
                    const json = await res.json();

                    expiryEl.innerHTML = '';

                    if (!json.expiries || !json.expiries.length) {
                        const opt = document.createElement('option');
                        opt.value = '';
                        opt.textContent = 'No expiries';
                        expiryEl.appendChild(opt);
                       // hideLoader();  // <<< HIDE LOADER
                        return;
                    }

                    json.expiries.forEach(exp => {
                        const opt = document.createElement('option');
                        opt.value = exp;
                        opt.textContent = exp;
                        expiryEl.appendChild(opt);
                    });
                    expiryEl.value = json.expiries[0];

                    // prefill ATM strikes
                    if (json.atm_strike) {
                        ceKeyEl.value = json.atm_strike;
                        peKeyEl.value = json.atm_strike;
                        hideLoader();
                        loadBtn.click();
                    }

                    console.log('Spot (prev day close):', json.spot);
                } catch (error) {
                    console.error('Error fetching expiries:', error);
                } finally {
                   //hideLoader();
                }
            });





            // Load CE + PE candles (+ previous day)
            loadBtn.addEventListener('click', async () => {
                const underlying = underlyingEl.value;
                const date       = dateEl.value;
                const expiry     = expiryEl.value;
                const ceKey      = ceKeyEl.value;
                const peKey      = peKeyEl.value;

                if (!underlying || !date || !expiry || !ceKey || !peKey) {
                    alert('Please fill underlying, date, expiry, CE key & PE key.');
                    return;
                }

                showLoader();

                const url = new URL("{{ route('api.ohlc') }}", window.location.origin);
                url.searchParams.set('underlying_symbol', underlying);
                url.searchParams.set('date', date);
                url.searchParams.set('expiry', expiry);
                url.searchParams.set('ce_instrument_key', ceKey);
                url.searchParams.set('pe_instrument_key', peKey);

                try {
                    const res  = await fetch(url);
                    const json = await res.json();

                    if (!ceChart) {
                        const ce = createChart(ceContainer, '#16a34a');
                        ceChart  = ce.chart;
                        ceSeries = ce.series;
                    }
                    if (!peChart) {
                        const pe = createChart(peContainer, '#16a34a');
                        peChart  = pe.chart;
                        peSeries = pe.series;
                    }

                    const cePrev  = normalize(json.ce_prev);
                    const ceToday = normalize(json.ce_today);
                    const pePrev  = normalize(json.pe_prev);
                    const peToday = normalize(json.pe_today);

                    const ceAll = [...cePrev, ...ceToday];
                    const peAll = [...pePrev, ...peToday];

                    ceSeries.setData(ceAll);
                    peSeries.setData(peAll);

                    if (ceAll.length) {
                        ceChart.timeScale().setVisibleRange({
                            from: ceAll[0].time,
                            to:   ceAll[ceAll.length - 1].time,
                        });
                    }
                    if (peAll.length) {
                        peChart.timeScale().setVisibleRange({
                            from: peAll[0].time,
                            to:   peAll[peAll.length - 1].time,
                        });
                    }

                    // previous-day OHLC lines
                    ensurePrevLines();

                    const cePrevOhlc = json.ce_prev_ohlc || null;
                    const pePrevOhlc = json.pe_prev_ohlc || null;

                    const todayStart = ceToday.length ? ceToday[0].time : null;
                    const todayEnd   = ceToday.length ? ceToday[ceToday.length - 1].time : null;

                    if (todayStart && todayEnd && cePrevOhlc && cePrevLines) {
                        const times = [todayStart, todayEnd];
                        //cePrevLines.open.setData(times.map(t => ({ time: t, value: cePrevOhlc.open })));
                        cePrevLines.high.setData(times.map(t => ({ time: t, value: cePrevOhlc.high })));
                        cePrevLines.low.setData(times.map(t => ({ time: t, value: cePrevOhlc.low })));
                        cePrevLines.close.setData(times.map(t => ({ time: t, value: cePrevOhlc.close })));
                    }

                    if (todayStart && todayEnd && pePrevOhlc && pePrevLines) {
                        const times = [todayStart, todayEnd];
                        //pePrevLines.open.setData(times.map(t => ({ time: t, value: pePrevOhlc.open })));
                        pePrevLines.high.setData(times.map(t => ({ time: t, value: pePrevOhlc.high })));
                        pePrevLines.low.setData(times.map(t => ({ time: t, value: pePrevOhlc.low })));
                        pePrevLines.close.setData(times.map(t => ({ time: t, value: pePrevOhlc.close })));
                    }

                    if (todayStart && todayEnd && pePrevOhlc && ceMirrorPeLines) {
                        const times = [todayStart, todayEnd];
                        ceMirrorPeLines.high.setData(times.map(t => ({ time: t, value: pePrevOhlc.high })));
                        ceMirrorPeLines.low.setData(times.map(t => ({ time: t, value: pePrevOhlc.low })));
                        ceMirrorPeLines.close.setData(times.map(t => ({ time: t, value: pePrevOhlc.close })));
                    }

                    if (todayStart && todayEnd && cePrevOhlc && peMirrorCeLines) {
                        const times = [todayStart, todayEnd];
                        peMirrorCeLines.high.setData(times.map(t => ({ time: t, value: cePrevOhlc.high })));
                        peMirrorCeLines.low.setData(times.map(t => ({ time: t, value: cePrevOhlc.low })));
                        peMirrorCeLines.close.setData(times.map(t => ({ time: t, value: cePrevOhlc.close })));
                    }
                } catch (error) {
                    console.error('Error loading chart:', error);
                } finally {
                    hideLoader();
                }
            });
        });
    </script>

@endsection
