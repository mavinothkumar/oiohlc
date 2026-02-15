{{-- resources/views/index-futures-chart.blade.php --}}
@extends('layouts.app')

@section('content')
    <div class="max-w-full mx-auto p-4 space-y-4">
        <div class="flex flex-wrap gap-4" >
            <div>
                <h1 class="text-xl font-semibold">Index & Futures Daily Chart</h1>
            </div>
            <div class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-sm mb-1">Symbol</label>
                    <input id="symbol" type="text"
                        value="NIFTY"
                        class="border rounded px-3 py-1 text-sm">
                </div>

                <div>
                    <label class="block text-sm mb-1">Date</label>
                    <input id="quote_date" type="date"
                        class="border rounded px-3 py-1 text-sm">
                </div>

                <button id="loadChartBtn"
                    class="bg-blue-600 text-white px-4 py-2 rounded text-sm">
                    Load Chart
                </button>
            </div>
        </div>




        <div id="trend-container"
            class="flex gap-2 p-2 overflow-x-auto whitespace-nowrap bg-gray-50 rounded shadow-sm border">

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
{{--        <div class="gap-4 mt-4">--}}
            <div>
                <h2 class="text-sm font-medium mb-1">Index</h2>
                <div id="index-chart-container" class="border rounded h-[600px]"></div>
            </div>
            <div>
                <h2 class="text-sm font-medium mb-1">Futures</h2>
                <div id="future-chart-container" class="border rounded h-[600px]"></div>
            </div>
        </div>
    </div>

    {{-- Loader overlay --}}
    <div id="loader" class="fixed inset-0 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-8 shadow-lg text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
            <p class="text-gray-700 font-medium">Loading...</p>
        </div>
    </div>

    {{-- Lightweight Charts v5 --}}
    <script src="https://unpkg.com/lightweight-charts/dist/lightweight-charts.standalone.production.js"></script>

    <script>
        function showLoader () {
            const loader = document.getElementById('loader');
            if (loader) loader.classList.remove('hidden');
        }

        function hideLoader () {
            const loader = document.getElementById('loader');
            if (loader) loader.classList.add('hidden');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const symbolEl = document.getElementById('symbol');
            const dateEl = document.getElementById('quote_date');
            const loadBtn = document.getElementById('loadChartBtn');
            const indexContainer = document.getElementById('index-chart-container');
            const futureContainer = document.getElementById('future-chart-container');

            let indexChart = null, indexSeries = null;
            let futureChart = null, futureSeries = null;

            // trend lines for index and future
            let indexTrendLines = null;
            let futureTrendLines = null;

            dateEl.addEventListener('change', function () {
                loadBtn.click();
            });

            function createChart (container, color) {
                const rect = container.getBoundingClientRect();

                // OHLC tooltip element
                const tooltip = document.createElement('div');
                tooltip.className = 'absolute bg-black text-white text-xs px-2 py-1 rounded opacity-0 transition-opacity z-50 pointer-events-none';
                tooltip.style.fontFamily = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                container.style.position = 'relative';
                container.appendChild(tooltip);

                const chart = LightweightCharts.createChart(container, {
                    width: rect.width,
                    height: rect.height,
                    layout: { background: { color: '#ffffff' }, textColor: '#111827' },
                    rightPriceScale: { borderColor: '#e5e7eb' },
                    timeScale: {
                        borderColor: '#e5e7eb',
                        timeVisible: true,
                        secondsVisible: false,
                        timezone: 'Asia/Kolkata'
                    },
                    grid: {
                        vertLines: { color: '#ffffff', visible: false },
                        horzLines: { color: '#ffffff', visible: false }
                    },
                    crosshair: { mode: LightweightCharts.CrosshairMode.Normal }
                });

                // v5+ API
                const series = chart.addSeries(LightweightCharts.CandlestickSeries, {
                    upColor: color,
                    downColor: '#dc2626',
                    borderUpColor: color,
                    borderDownColor: '#dc2626',
                    wickUpColor: color,
                    wickDownColor: '#dc2626'
                });

                // OHLC tooltip on hover
                chart.subscribeCrosshairMove((param) => {
                    if (
                        !param.time ||
                        !param.point ||
                        param.point.x < 0 ||
                        param.point.y < 0 ||
                        param.point.x > container.clientWidth ||
                        param.point.y > container.clientHeight
                    ) {
                        tooltip.style.opacity = '0';
                        return;
                    }

                    const data = param.seriesData.get(series);
                    if (!data) {
                        tooltip.style.opacity = '0';
                        return;
                    }

                    const { open, high, low, close } = data;

                    tooltip.innerHTML = `
            <div>O: ${open.toFixed(2)}</div>
            <div>H: ${high.toFixed(2)}</div>
            <div>L: ${low.toFixed(2)}</div>
            <div>C: ${close.toFixed(2)}</div>
        `;

                    tooltip.style.left = (param.point.x + 10) + 'px';
                    tooltip.style.top  = (param.point.y + 10) + 'px';
                    tooltip.style.opacity = '1';
                });

                new ResizeObserver(entries => {
                    if (!entries.length) return;
                    const cr = entries[0].contentRect;
                    chart.applyOptions({ width: cr.width, height: cr.height });
                }).observe(container);

                return { chart, series };
            }

            function timeToLocal (originalTime) {
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

            function normalize (data) {
                if ( ! Array.isArray(data)) return [];
                return data
                    .filter(c => c && c.time != null)
                    .map(c => ( {
                        time: timeToLocal(c.time),
                        open: Number(c.open),
                        high: Number(c.high),
                        low: Number(c.low),
                        close: Number(c.close)
                    } ))
                    .sort((a, b) => a.time - b.time);
            }

            function ensureTrendLines () {
                if ( ! indexTrendLines && indexChart) {
                    indexTrendLines = {
                        // index_high: indexChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#ff0000', lineWidth: 2, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }),
                        // index_low: indexChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#02b843', lineWidth: 2, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }),
                        index_close: indexChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#3b82f6', lineWidth: 2, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        }),
                        current_day_index_open: indexChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#9ca3af', lineWidth: 2, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        }),
                        // earth_high: indexChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#f97316', lineWidth: 3, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }),
                        // earth_low: indexChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#f97316', lineWidth: 3, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }),
                        // min_r: indexChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#ec4899', lineWidth: 4, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }),
                        // min_s: indexChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#ec4899', lineWidth: 4, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }),
                        max_r: indexChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#000000', lineWidth: 4, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        }),
                        max_s: indexChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#000000', lineWidth: 4, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        }),
                        atm_ce: indexChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#810aff', lineWidth: 7, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        }),
                        atm_pe: indexChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#810aff', lineWidth: 7, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        }),
                        atm_r: indexChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#ff0202', lineWidth: 5, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        }),
                        atm_r_1: indexChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#ff0202', lineWidth: 5, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        }), atm_r_2: indexChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#ff0202', lineWidth: 5, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        }), atm_r_3: indexChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#ff0202', lineWidth: 5, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        }),atm_s: indexChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#278100', lineWidth: 5, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        }), atm_s_1: indexChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#278100', lineWidth: 5, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        }), atm_s_2: indexChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#278100', lineWidth: 5, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        }), atm_s_3: indexChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#278100', lineWidth: 5, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        }), atm_index_open: indexChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#cdcdcd', lineWidth: 7, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        })
                        // , atm_r_avg: indexChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#ed08f6', lineWidth: 5, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }), atm_s_avg: indexChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#ed08f6', lineWidth: 5, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // })
                    };
                }

                if ( ! futureTrendLines && futureChart) {
                    futureTrendLines = {
                        // index_high: futureChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#ff0000', lineWidth: 2, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }),
                        // index_low: futureChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#02b843', lineWidth: 2, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }),
                        future_close: futureChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#3b82f6', lineWidth: 2, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        }),
                        future_open: futureChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#9ca3af', lineWidth: 2, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        }),
                        // earth_high: futureChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#f97316', lineWidth: 3, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }),
                        // earth_low: futureChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#f97316', lineWidth: 3, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }),
                        // min_r: futureChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#ec4899', lineWidth: 4, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }),
                        // min_s: futureChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#ec4899', lineWidth: 4, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }),
                        // max_r: futureChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#000000', lineWidth: 4, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }),
                        // max_s: futureChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#000000', lineWidth: 4, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }),
                        // atm_ce: futureChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#810aff', lineWidth: 7, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }),
                        // atm_pe: futureChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#810aff', lineWidth: 7, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }),atm_r: futureChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#ff0202', lineWidth: 5, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }),
                        // atm_r_1: futureChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#ff0202', lineWidth: 5, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }), atm_r_2: futureChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#ff0202', lineWidth: 5, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }), atm_r_3: futureChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#ff0202', lineWidth: 5, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }), atm_s_1: futureChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#278100', lineWidth: 5, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }), atm_s_2: futureChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#278100', lineWidth: 5, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }), atm_s_3: futureChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#278100', lineWidth: 5, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }),atm_s: futureChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#278100', lineWidth: 5, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        //}),
                    future_atm: futureChart.addSeries(LightweightCharts.LineSeries, {
                            color: '#cdcdcd', lineWidth: 7, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        })
                        // , atm_r_avg: futureChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#ed08f6', lineWidth: 5, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // }), atm_s_avg: futureChart.addSeries(LightweightCharts.LineSeries, {
                        //     color: '#ed08f6', lineWidth: 5, lineStyle: LightweightCharts.LineStyle.Solid, priceLineVisible: false
                        // })
                    };
                }
            }

            function setTrendLines (chart, trendLines, trendData, timeRange) {
                if ( ! trendData || ! timeRange || ! trendLines) return;

                const [startTime, endTime] = timeRange;
                const times = [startTime, endTime];

                Object.keys(trendData).forEach(key => {
                    if (trendLines[ key ] && trendData[ key ] != null) {
                        trendLines[ key ].setData(
                            times.map(t => ( { time: t, value: trendData[ key ] } ))
                        );
                    }
                });
            }

            loadBtn.addEventListener('click', async () => {
                const symbol = symbolEl.value;
                const date = dateEl.value;

                if ( ! symbol || ! date) {
                    alert('Please fill symbol and date.');
                    return;
                }

                showLoader();

                const url = new URL("{{ route('api.index.futures.daily') }}", window.location.origin);
                url.searchParams.set('symbol_name', symbol);
                url.searchParams.set('quote_date', date);

                try {
                    const res = await fetch(url);
                    const json = await res.json();

                    // Update trend data rows
                    const trendContainer = document.getElementById('trend-container');
                    trendContainer.innerHTML = '';

                    const show = json.trend_data.show;

// normalize to numbers (in case they come as strings)
                    const prevAtm  = Number(show.previous_day_atm);
                    const atmCe    = Number(show.atm_ce);
                    const atmPe    = Number(show.atm_pe);

// check if all three are equal and valid numbers
                    const allEqual =
                        !Number.isNaN(prevAtm) &&
                        prevAtm === atmCe &&
                        prevAtm === atmPe;

                    Object.entries(show).forEach(([label, value]) => {
                        if (value === null) return;

                        const isTriple =
                            allEqual &&
                            (label === 'previous_day_atm' ||
                                label === 'atm_ce' ||
                                label === 'atm_pe');

                        const card = document.createElement('div');
                        card.className =
                            'inline-flex flex-col items-center justify-center px-3 py-2 border rounded ' +
                            'shadow-sm hover:shadow-md transition-shadow min-w-[110px] ' +
                            (isTriple ? 'bg-green-100' : 'bg-white'); // Tailwind example

                        card.innerHTML = `
        <div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 text-center">
            ${label.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
        </div>
        <div class="text-sm font-bold text-blue-600 text-center">

            ${typeof value === 'number'
                            ? value.toLocaleString('en-IN', { maximumFractionDigits: 2 })
                            : value}
        </div>
    `;

                        trendContainer.appendChild(card);
                    });



                    if ( ! indexChart) {
                        const idx = createChart(indexContainer, '#16a34a');
                        indexChart = idx.chart;
                        indexSeries = idx.series;
                    }
                    if ( ! futureChart) {
                        const fut = createChart(futureContainer, '#16a34a');
                        futureChart = fut.chart;
                        futureSeries = fut.series;
                    }

                    const indexData = normalize(json.index_data || []);
                    const futureData = normalize(json.future_data || []);

                    indexSeries.setData(indexData);
                    futureSeries.setData(futureData);

                    indexChart.timeScale().fitContent();
                    futureChart.timeScale().fitContent();

                    // set visible range
                    if (indexData.length) {
                        const first = indexData[0].time;
                        const last  = indexData[indexData.length - 1].time;
                        const pad   = Math.round((last - first) * 0.1); // 10% padding on each side

                        indexChart.timeScale().setVisibleRange({
                            from: first - pad,
                            to:   last + pad,
                        });
                    }

                    if (futureData.length) {
                        const first = futureData[0].time;
                        const last  = futureData[futureData.length - 1].time;
                        const pad   = Math.round((last - first) * 0.1);

                        futureChart.timeScale().setVisibleRange({
                            from: first - pad,
                            to:   last + pad,
                        });
                    }


                    // ensure trend lines exist
                    ensureTrendLines();

                    const trendData = json.trend_data;

                    // set trend lines on both charts
                    if (indexData.length && trendData) {
                        const indexTimeRange = [indexData[ 0 ].time, indexData[ indexData.length - 1 ].time];
                        setTrendLines(indexChart, indexTrendLines, trendData, indexTimeRange);
                    }

                    if (futureData.length && trendData) {
                        const futureTimeRange = [futureData[ 0 ].time, futureData[ futureData.length - 1 ].time];
                        setTrendLines(futureChart, futureTrendLines, trendData, futureTimeRange);
                    }

                } catch (error) {
                    console.error('Error loading chart:', error);
                    alert('Error loading chart data');
                } finally {
                    hideLoader();
                }
            });
        });
    </script>
@endsection
