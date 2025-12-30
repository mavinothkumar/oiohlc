{{-- resources/views/options-chart.blade.php --}}
@extends('layouts.app')

@section('content')
    <div class="max-w-full mx-auto p-4 space-y-4">
        <h1 class="text-xl font-semibold">Options 5m Chart</h1>

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
                <input id="ce_instrument_key" type="text" class="border rounded px-3 py-1 text-sm" placeholder="CE Strike">
            </div>
            <div>
                <label class="block text-sm mb-1">PE Strike</label>
                <input id="pe_instrument_key" type="text" class="border rounded px-3 py-1 text-sm" placeholder="PE Strike">
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
                <div id="ce-chart-container" class="border rounded h-[600px]"></div>
            </div>
            <div>
                <h2 class="text-sm font-medium mb-1">PE</h2>
                <div id="pe-chart-container" class="border rounded h-[600px]"></div>
            </div>
        </div>
    </div>

    {{-- TradingView Lightweight Charts --}}
    {{-- put this at the bottom of resources/views/options-chart.blade.php --}}

    {{-- Lightweight Charts v4 (has addCandlestickSeries) --}}
    <script src="https://unpkg.com/lightweight-charts/dist/lightweight-charts.standalone.production.js"></script>

    <script src="https://unpkg.com/lightweight-charts-line-tools@1.0.5/dist/lightweight-charts-line-tools.umd.js"></script>



    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const underlyingEl = document.getElementById('underlying');
            const dateEl = document.getElementById('date');
            const expiryEl = document.getElementById('expiry');
            const ceKeyEl = document.getElementById('ce_instrument_key');
            const peKeyEl = document.getElementById('pe_instrument_key');
            const loadBtn = document.getElementById('loadChartBtn');

            const ceContainer = document.getElementById('ce-chart-container');
            const peContainer = document.getElementById('pe-chart-container');

            let ceChart = null, ceSeries = null;
            let peChart = null, peSeries = null;

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

            function cleanOhlc (data) {
                const map = {};
                for (const c of ( data || [] )) {
                    if ( ! c) continue;
                    if (c.time == null) continue;
                    if ([c.open, c.high, c.low, c.close].some(v => v == null)) continue;

                    const t = Number(c.time);
                    map[ t ] = {
                        time: t,
                        open: Number(c.open),
                        high: Number(c.high),
                        low: Number(c.low),
                        close: Number(c.close)
                    };
                }

                // IMPORTANT: ascending by time
                return Object.values(map).sort((a, b) => a.time - b.time);
            }

            function timeToLocal (originalTime) {
                const d = new Date(originalTime * 1000);
                return Date.UTC(d.getFullYear(), d.getMonth(), d.getDate(), d.getHours(), d.getMinutes(), d.getSeconds(), d.getMilliseconds()) / 1000;
            }

            // Auto-load expiries when date changes
            dateEl.addEventListener('change', async () => {
                const date = dateEl.value;
                const underlying = underlyingEl.value;

                if ( ! date || ! underlying) return;

                const url = new URL("{{ route('api.expiries') }}", window.location.origin);
                url.searchParams.set('underlying_symbol', underlying);
                url.searchParams.set('date', date);

                const res = await fetch(url);
                const json = await res.json();

                expiryEl.innerHTML = '';

                if ( ! json.expiries || ! json.expiries.length) {
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = 'No expiries';
                    expiryEl.appendChild(opt);
                    return;
                }

                json.expiries.forEach(exp => {
                    const opt = document.createElement('option');
                    opt.value = exp;
                    opt.textContent = exp;
                    expiryEl.appendChild(opt);
                });

                // auto-select first expiry
                expiryEl.value = json.expiries[ 0 ];
            });

            // Load CE + PE candles
            loadBtn.addEventListener('click', async () => {
                const underlying = underlyingEl.value;
                const date = dateEl.value;
                const expiry = expiryEl.value;
                const ceKey = ceKeyEl.value;
                const peKey = peKeyEl.value;

                if ( ! underlying || ! date || ! expiry || ! ceKey || ! peKey) {
                    alert('Please fill underlying, date, expiry, CE key & PE key.');
                    return;
                }

                const url = new URL("{{ route('api.ohlc') }}", window.location.origin);
                url.searchParams.set('underlying_symbol', underlying);
                url.searchParams.set('date', date);
                url.searchParams.set('expiry', expiry);
                url.searchParams.set('ce_instrument_key', ceKey);
                url.searchParams.set('pe_instrument_key', peKey);

                const res = await fetch(url);
                const json = await res.json();

                if ( ! ceChart) {
                    const ce = createChart(ceContainer, '#16a34a');
                    ceChart = ce.chart;
                    ceSeries = ce.series;
                }
                if ( ! peChart) {
                    const pe = createChart(peContainer, '#3b82f6');
                    peChart = pe.chart;
                    peSeries = pe.series;
                }

                const normalizeAndSort = (data) => {
                    if ( ! Array.isArray(data)) return [];

                    const out = data
                        .filter(c => c && c.time != null)
                        .map(c => ( {
                            time: timeToLocal(c.time),                 // 1734320700, 1734321000, ...
                            open: Number(c.open),
                            high: Number(c.high),
                            low: Number(c.low),
                            close: Number(c.close)
                        } ))
                        .sort((a, b) => a.time - b.time);        // ASCENDING

                    console.log('FIRST:', out[ 0 ]);
                    console.log('LAST :', out[ out.length - 1 ]);
                    return out;
                };

                const ceData = normalizeAndSort(json.ce);
                const peData = normalizeAndSort(json.pe);

                ceSeries.setData(ceData);
                peSeries.setData(peData);

                const ceFirst = ceData[ 0 ]?.time;
                const ceLast = ceData[ ceData.length - 1 ]?.time;
                if (ceFirst && ceLast) {
                    ceChart.timeScale().setVisibleRange({ from: ceFirst, to: ceLast });
                }

                console.log('First 5 CE times:', ceData.slice(0, 5).map(c => c.time));
                console.log('Last 5  CE times:', ceData.slice(-5).map(c => c.time));

                //ceChart.timeScale().fitContent();
                //peChart.timeScale().fitContent();
            });
        });
    </script>

@endsection
