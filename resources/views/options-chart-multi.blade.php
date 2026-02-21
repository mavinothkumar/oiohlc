{{-- resources/views/options-chart-multi.blade.php --}}

@extends('layouts.app')

@section('title', 'Options Multi Chart')

@section('content')
    <div class="w-full mx-auto px-4 py-4">
        {{-- Filters header --}}
        <form method="GET" action="{{ route('options.multi.chart') }}" class="mb-4">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Symbol</label>
                    <input type="text" name="symbol" value="{{ $symbol ?? 'NIFTY' }}"
                        class="mt-1 block w-28 rounded-md border-gray-300 shadow-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Quote date</label>
                    <input type="date" name="quote_date" value="{{ $quoteDate }}"
                        class="mt-1 block rounded-md border-gray-300 shadow-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Expiry</label>
                    <input type="date" name="expiry_date" value="{{ $expiryDate }}"
                        class="mt-1 block rounded-md border-gray-300 shadow-sm">
                </div>

                {{-- Customizable strikes row (CE + PE in single row) --}}
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700">CE strikes / PE strikes</label>

                    {{-- CE row --}}
                    <div class="mt-1 grid grid-cols-7 gap-2 text-xs">
                        @for($i = 0; $i < 7; $i++)
                            @php
                                $strike = isset($ceStrikes[$i]) ? $ceStrikes[$i] : '';
                            @endphp
                            <input type="number"
                                name="ce_strikes[]"
                                value="{{ $strike }}"
                                class="rounded-md border-gray-300 shadow-sm text-center"
                                placeholder="CE {{ $i+1 }}">
                        @endfor
                    </div>

                    {{-- PE row --}}
                    <div class="mt-1 grid grid-cols-7 gap-2 text-xs">
                        @for($i = 0; $i < 7; $i++)
                            @php
                                $strike = isset($peStrikes[$i]) ? $peStrikes[$i] : '';
                            @endphp
                            <input type="number"
                                name="pe_strikes[]"
                                value="{{ $strike }}"
                                class="rounded-md border-gray-300 shadow-sm text-center"
                                placeholder="PE {{ $i+1 }}">
                        @endfor
                    </div>
                </div>


                <div>
                    <button type="submit" id="submit_multi_strike"
                        class="inline-flex items-center px-4 py-2 border border-transparent
                               text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600
                               hover:bg-indigo-700">
                        Apply
                    </button>
                </div>
            </div>
        </form>

        {{-- ATM and average info (guard for nulls) --}}
        @if(!is_null($atmIndexOpen))
            <div class="mb-4 text-sm text-gray-700 flex gap-4">
                <div>
                    ATM index open:
                    <span class="font-semibold">
                    {{ number_format($atmIndexOpen, 2) }}
                </span>
                </div>
                <div>Current Day Mid Point:
                    <span class="font-semibold text-green-600">
                    {{ number_format($avgAll, 2) }}
                </span>
                </div>
                <div>
                    Prev Day Mid Points:
                    <span class="font-semibold">
        @if(is_array($prevMidPoints) && count($prevMidPoints) > 0)
                            @foreach(array_slice($prevMidPoints, 0, 5) as $index => $point)
                                @php
                                    $colors = ['text-[#FFB3B3]', 'text-[#FF9999]', 'text-[#FF6666]', 'text-[#FF3333]', 'text-[#CC0000]'];
                                @endphp
                                <span class="{{ $colors[$index] ?? 'text-red-600' }}">
                    {{ number_format($point, 2) }}
                </span>
                                @if(!$loop->last)
                                    <span class="mx-1">|</span>
                                @endif
                            @endforeach
                        @else
                            <span class="text-red-600">First Day of the Expiry</span>
                        @endif
    </span>
                </div>

            </div>
        @endif

        <div class="flex flex-col gap-4">
            {{-- LEFT 70%: filters + multi chart --}}
            <div class="w-full bg-white border border-gray-200 rounded-lg shadow-sm p-3 md:p-4">
                @if(!empty($ceStrikes) && !empty($peStrikes))

                    <div class="flex justify-end mb-2">
                        <button
                            id="toggle-saturation-btn"
                            type="button"
                            class="px-2 py-1 text-xs rounded bg-gray-200 hover:bg-gray-300"
                        >
                            Show saturation grid
                        </button>
                    </div>

                    <div  id="charts-grid" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @foreach($ceStrikes as $strike)
                            {{-- CE --}}
                            <div class="chart-card bg-white rounded-lg shadow p-2" data-strike="{{ $strike }}" data-kind="ce">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="text-xs font-semibold">
                                        CE {{ $strike }}
                                    </div>
                                    <button
                                        type="button"
                                        class="chart-fullscreen-btn px-2 py-1 text-[10px] rounded bg-gray-200 hover:bg-gray-300"
                                        data-target="chart-ce-{{ $strike }}"
                                    >
                                        Full
                                    </button>
                                </div>
                                <div id="chart-ce-{{ $strike }}" class="h-64"></div>
                            </div>

                            {{-- PE --}}
                            <div class="chart-card bg-white rounded-lg shadow p-2" data-strike="{{ $strike }}" data-kind="pe">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="text-xs font-semibold">
                                        PE {{ $strike }}
                                    </div>
                                    <button
                                        type="button"
                                        class="chart-fullscreen-btn px-2 py-1 text-[10px] rounded bg-gray-200 hover:bg-gray-300"
                                        data-target="chart-pe-{{ $strike }}"
                                    >
                                        Full
                                    </button>
                                </div>
                                <div id="chart-pe-{{ $strike }}" class="h-64"></div>
                            </div>

                            {{-- Combined --}}
                            <div class="chart-card bg-white rounded-lg shadow p-2" data-strike="{{ $strike }}" data-kind="combo">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="text-xs font-semibold">
                                        CE + PE {{ $strike }}
                                    </div>
                                    <button
                                        type="button"
                                        class="chart-fullscreen-btn px-2 py-1 text-[10px] rounded bg-gray-200 hover:bg-gray-300"
                                        data-target="combo-line-chart-{{ (int)$strike }}"
                                    >
                                        Full
                                    </button>
                                </div>
                                <div id="combo-line-chart-{{ (int)$strike }}" class="h-64"></div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            <div
                id="saturation-wrapper"
                class="hidden fixed inset-y-0 right-0 z-40 min-w-[600px] max-w-[600px] h-screen bg-white border border-gray-200 rounded-lg shadow-lg p-3 md:p-4 overflow-y-auto"
            >
                {{-- existing content stays the same --}}
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-sm font-semibold text-gray-700">
                        CE–PE Saturation Grid (5 min)
                    </h2>
                    <button
                        type="button"
                        id="close-saturation-btn"
                        class="text-xs px-2 py-0.5 rounded bg-gray-200 hover:bg-gray-300"
                    >
                        ✕
                    </button>
                </div>
                <div class="max-h-[calc(100vh-6rem)] overflow-y-auto">
                    {{-- small form to tweak saturation threshold --}}
                    @if($symbol && $quoteDate && $expiryDate)
                        <form method="GET" action="{{ route('options.multi.chart') }}" class="flex items-center gap-1 text-xs">
                            <input type="hidden" name="symbol" value="{{ $symbol }}">
                            <input type="hidden" name="quote_date" value="{{ $quoteDate }}">
                            <input type="hidden" name="expiry_date" value="{{ $expiryDate }}">
                            @foreach($ceStrikes as $s)
                                <input type="hidden" name="ce_strikes[]" value="{{ $s }}">
                            @endforeach
                            @foreach($peStrikes as $s)
                                <input type="hidden" name="pe_strikes[]" value="{{ $s }}">
                            @endforeach

                            <span class="text-gray-500">Snipper Sat.</span>
                            <input
                                type="number"
                                name="snipper_saturation"
                                class="w-14 border-gray-300 rounded px-1 py-0.5 text-right text-xs"
                                value="{{ $snipperSaturation ?? 10 }}"
                                step="1"
                            >

                            <span class="text-gray-500">Sat.</span>

                            <input
                                type="number"
                                name="saturation"
                                class="w-14 border-gray-300 rounded px-1 py-0.5 text-right text-xs"
                                value="{{ $saturation ?? 5 }}"
                                step="1"
                            >
                            <button
                                type="submit"
                                class="px-2 py-0.5 bg-indigo-600 text-white text-xs rounded hover:bg-indigo-700"
                            >
                                Apply
                            </button>
                        </form>
                    @endif
                </div>

                @if(!empty($diffMatrix) && !empty($timeSlots) && !empty($allStrikes))

                    <div class="overflow-x-auto border border-gray-100 rounded">
                        <table class="min-w-full text-[10px] md:text-xs">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                            <tr>
                                <th class="px-2 py-1 border-b border-gray-200 text-left font-semibold text-gray-600 w-14">
                                    Time
                                </th>
                                @foreach($allStrikes as $strike)
                                    <th class="px-2 py-1 border-b border-gray-200 text-center font-semibold text-gray-600">
                                        {{ (int)$strike }}
                                    </th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($timeSlots as $time)
                                @php
                                    $row = $diffMatrix[$time] ?? [];
                                @endphp
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="px-2 py-1 text-gray-600 font-medium">
                                        {{ $time }}
                                    </td>
                                    @foreach($allStrikes as $strike)
                                        @php
                                            $cell = $row[(float)$strike] ?? null;
                                        @endphp
                                        <td class="px-1 py-1 text-center align-top">
                                            @if($cell)
                                                @php
                                                    if (($cell['direction'] ?? null) === 'CE_SELL') {
                                                        $boxClass = 'bg-red-100 border-red-300 text-red-800';
                                                        $signal   = 'CE Sell';
                                                    } elseif (($cell['direction'] ?? null) === 'PE_SELL') {
                                                        $boxClass = 'bg-green-100 border-green-300 text-green-800';
                                                        $signal   = 'PE Sell';
                                                    } else {
                                                        $boxClass = 'bg-yellow-100 border-yellow-300 text-yellow-800';
                                                        $signal   = '';
                                                    }

                                                    $leftSrc  = $cell['left_src']  ?? '';
                                                    $rightSrc = $cell['right_src'] ?? '';
                                                    $leftVal  = $cell['left_val']  ?? null;
                                                    $rightVal = $cell['right_val'] ?? null;
                                                @endphp

                                                <div class="inline-flex flex-col px-1.5 py-1 rounded border {{ $boxClass }}">
                                                    <div class="flex items-center justify-between gap-1">
            <span class="font-semibold text-[11px]">
                {{ number_format($cell['diff'], 2) }}
            </span>
                                                        @if($signal)
                                                            <span class="text-[10px] font-semibold uppercase">
                    {{ $signal }}
                </span>
                                                        @endif
                                                    </div>
                                                    <span class="text-[9px]">
            {{ $leftSrc }}({{ number_format($leftVal, 1) }})
            vs
            {{ $rightSrc }}({{ number_format($rightVal, 1) }})
        </span>
                                                    <span class="text-[9px] text-gray-700">
            CE: {{ $cell['ce_side'] }} | PE: {{ $cell['pe_side'] }}
        </span>
                                                    @if(isset($cell['left_snipper'], $cell['right_snipper']))
                                                        <span class="text-[9px] text-gray-500">
                S: {{ number_format($cell['left_snipper'], 1) }}
                /
                {{ number_format($cell['right_snipper'], 1) }}
            </span>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-[10px] text-gray-500 leading-tight">
                        Each cell shows the lowest CE/PE high‑low difference for that strike and 5‑minute candle within
                        ±{{ $saturation }}. Values closer to zero are highlighted more strongly.
                    </p>
                @else
                    <p class="text-xs text-gray-500">
                        No CE/PE saturation data for the current selection. Adjust strikes or date and try again.
                    </p>
                @endif
            </div>
        </div>
    </div>
    </div>

    {{-- Loader overlay --}}
    <div id="loader"
        class="fixed inset-0 bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 shadow-lg text-center">
            <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600 mx-auto mb-3"></div>
            <p class="text-gray-700 text-sm font-medium">Loading charts...</p>
        </div>
    </div>

    {{-- Lightweight Charts --}}
    <script src="https://unpkg.com/lightweight-charts@4.2.1/dist/lightweight-charts.standalone.production.js"></script>

    <script>
        const expiriesUrl = "{{ route('api.multi-chart-expiries') }}";
        const ohlcUrl = "{{ route('api.ohlc') }}";

        const symbolInput = document.querySelector('input[name="symbol"]');
        const quoteDateInput = document.querySelector('input[name="quote_date"]');
        const expiryDateInput = document.querySelector('input[name="expiry_date"]');

        function getStrikeInputs (type) {
            return Array.from(document.querySelectorAll(`input[name="${ type }_strikes[]"]`));
        }

        function showLoader () {
            const loader = document.getElementById('loader');
            if (loader) loader.classList.remove('hidden');
        }

        function hideLoader () {
            const loader = document.getElementById('loader');
            if (loader) loader.classList.add('hidden');
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

        function normalizeData (arr) {
            if ( ! Array.isArray(arr)) return [];
            return arr
                .filter(c => c.time != null)
                .map(c => ( {
                    time: timeToLocal(c.time),
                    open: Number(c.open),
                    high: Number(c.high),
                    low: Number(c.low),
                    close: Number(c.close)
                } ))
                .sort((a, b) => a.time - b.time);
        }

        // NEW: combined CE+PE line chart per strike (expects normalized data)
        // expects normalized ceData / peData, plus avgAtm (array or number) and avgAll (number)
        function renderComboLineChart(strike, ceData, peData, avgAtm, avgAll) {
            const containerId = 'combo-line-chart-' + strike;
            const container = document.getElementById(containerId);
            if (!container) return;

            const comboChart = LightweightCharts.createChart(container, {
                height: 260,
                layout: { background: { color: '#ffffff' }, textColor: '#333' },
                rightPriceScale: { borderVisible: false },
                timeScale: {
                    borderVisible: false,
                    timeVisible: true,
                    secondsVisible: false
                },
                grid: {
                    vertLines: { visible: false },
                    horzLines: { visible: false }
                }
            });

            container._chartInstance = comboChart;

            // main CE/PE lines
            const ceLineData = ceData.map(row => ({
                time: row.time,
                value: row.close,
            }));

            const peLineData = peData.map(row => ({
                time: row.time,
                value: row.close,
            }));

            const ceLineSeries = comboChart.addLineSeries({
                color: '#2563eb',
                lineWidth: 2,
                title: 'CE',
            });
            ceLineSeries.setData(ceLineData);

            const peLineSeries = comboChart.addLineSeries({
                color: '#f97316',
                lineWidth: 2,
                title: 'PE',
            });
            peLineSeries.setData(peLineData);

            if (!ceLineData.length) return;

            const tFirst = ceLineData[0].time;
            const tLast  = ceLineData[ceLineData.length - 1].time;

            // same avgAtm logic as loadSingleChart
            if (Array.isArray(avgAtm) && avgAtm.length > 0) {
                const redShades = [
                    '#FFB3B3',
                    '#FF9999',
                    '#FF6666',
                    '#FF3333',
                    '#CC0000'
                ];

                avgAtm.slice(0, 5).forEach((avgValue, index) => {
                    if (avgValue != null && !isNaN(avgValue)) {
                        const atmLine = comboChart.addLineSeries({
                            color: redShades[index] || '#FF0000',
                            lineWidth: 2,
                            priceLineVisible: false
                        });
                        atmLine.setData([
                            { time: tFirst, value: avgValue },
                            { time: tLast,  value: avgValue }
                        ]);
                    }
                });
            } else if (avgAtm != null && !isNaN(avgAtm)) {
                const atmLine = comboChart.addLineSeries({
                    color: 'red',
                    lineWidth: 2,
                    priceLineVisible: false
                });
                atmLine.setData([
                    { time: tFirst, value: avgAtm },
                    { time: tLast,  value: avgAtm }
                ]);
            }

            if (avgAll != null && !isNaN(avgAll)) {
                const allLine = comboChart.addLineSeries({
                    color: 'green',
                    lineWidth: 2,
                    priceLineVisible: false
                });
                allLine.setData([
                    { time: tFirst, value: avgAll },
                    { time: tLast,  value: avgAll }
                ]);
            }

            comboChart.timeScale().fitContent();
        }

        async function onQuoteDateChange () {
            const symbol = symbolInput.value;
            const date = quoteDateInput.value;
            if ( ! symbol || ! date) return;

            showLoader();
            try {
                const params = new URLSearchParams({
                    underlying_symbol: symbol,
                    date: date
                });

                const res = await fetch(expiriesUrl + '?' + params.toString());
                if ( ! res.ok) {
                    console.error('expiries request failed', res.status);
                    return;
                }
                const data = await res.json();

                const expiry = ( data.expiries && data.expiries.length )
                    ? data.expiries[ 0 ]
                    : null;
                if ( ! expiry) return;
                expiryDateInput.value = expiry.substring(0, 10);

                const atm = parseFloat(data.open_atm_strike || data.atm_strike);
                if ( ! atm) return;

                const step = 50;
                const strikes = [
                    atm - 3 * step,
                    atm - 2 * step,
                    atm - 1 * step,
                    atm,
                    atm + 1 * step,
                    atm + 2 * step,
                    atm + 3 * step
                ];

                const ceInputs = getStrikeInputs('ce');
                const peInputs = getStrikeInputs('pe');

                ceInputs.forEach((el, i) => el.value = strikes[ i ] ?? '');
                peInputs.forEach((el, i) => el.value = strikes[ i ] ?? '');

                const btn = document.getElementById('submit_multi_strike');
                console.log('s');
                if (btn){
                    console.log('t');
                    btn.click();
                }

            } finally {
                hideLoader();
            }
        }

        // NEW: keep CE & PE data per strike so we can render combo chart
        const ceCache = {};
        const peCache = {};

        // NEW: helper to load both CE and PE for one strike and then draw combo
        async function loadStrikeCharts (symbol, expiry, date, strike, avgAtm, avgAll) {
            await Promise.all([
                loadSingleChart(symbol, expiry, date, strike, 'CE', avgAtm, avgAll),
                loadSingleChart(symbol, expiry, date, strike, 'PE', avgAtm, avgAll)
            ]);

            const ceData = ceCache[ strike ];
            const peData = peCache[ strike ];
            if (ceData && peData) {
                renderComboLineChart(strike, ceData, peData, avgAtm, avgAll);
            }
        }

        async function loadAllCharts (symbol, expiry, date, strikes, avgAtm, avgAll) {
            showLoader();
            try {
                for (const strike of strikes) {
                    await loadStrikeCharts(symbol, expiry, date, strike, avgAtm, avgAll);
                }
            } finally {
                hideLoader();
            }
        }

        async function loadSingleChart (symbol, expiry, date, strike, type, avgAtm, avgAll) {
            const params = new URLSearchParams({
                underlying_symbol: symbol,
                expiry: expiry,
                date: date,
                ce_instrument_key: strike,
                pe_instrument_key: strike
            });

            const res = await fetch(ohlcUrl + '?' + params.toString());
            if ( ! res.ok) {
                console.error('OHLC request failed', res.status, strike, type);
                return;
            }
            const data = await res.json();

            const containerId = ( type === 'CE' ? 'chart-ce-' : 'chart-pe-' ) + strike;
            const container = document.getElementById(containerId);
            if ( ! container) {
                console.warn('Missing container', containerId);
                return;
            }

            container.style.position = 'relative';

            const tooltip = document.createElement('div');
            tooltip.className = 'absolute bg-black text-white text-xs px-2 py-1 rounded opacity-0 transition-opacity z-50 pointer-events-none';
            tooltip.style.fontFamily = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            container.appendChild(tooltip);

            const chart = LightweightCharts.createChart(container, {
                width: container.clientWidth || 350,
                height: container.clientHeight || 250,
                layout: { background: { color: 'white' }, textColor: '#000' },
                timeScale: { timeVisible: true, secondsVisible: false },
                crosshair: { mode: LightweightCharts.CrosshairMode.Normal },
                grid: {
                    vertLines: { visible: false },
                    horzLines: { visible: false }
                }
            });

            container._chartInstance = chart;

            const raw = type === 'CE' ? data.ce_today : data.pe_today;
            const seriesData = normalizeData(raw);

            // cache normalized data for combo line chart
            if (type === 'CE') {
                ceCache[ strike ] = seriesData;
            } else {
                peCache[ strike ] = seriesData;
            }

            const series = chart.addCandlestickSeries();
            series.setData(seriesData);

            if ( ! seriesData.length) return;

            const tFirst = seriesData[ 0 ].time;
            const tLast = seriesData[ seriesData.length - 1 ].time;

            if (Array.isArray(avgAtm) && avgAtm.length > 0) {
                const redShades = [
                    '#FFB3B3',
                    '#FF9999',
                    '#FF6666',
                    '#FF3333',
                    '#CC0000'
                ];

                avgAtm.slice(0, 5).forEach((avgValue, index) => {
                    if (avgValue != null && ! isNaN(avgValue)) {
                        const atmLine = chart.addLineSeries({
                            color: redShades[ index ] || '#FF0000',
                            lineWidth: 2,
                            priceLineVisible: false
                        });
                        atmLine.setData([
                            { time: tFirst, value: avgValue },
                            { time: tLast, value: avgValue }
                        ]);
                    }
                });
            } else if (avgAtm != null && ! isNaN(avgAtm)) {
                const atmLine = chart.addLineSeries({
                    color: 'red',
                    lineWidth: 2,
                    priceLineVisible: false
                });
                atmLine.setData([
                    { time: tFirst, value: avgAtm },
                    { time: tLast, value: avgAtm }
                ]);
            }

            const allLine = chart.addLineSeries({
                color: 'green',
                lineWidth: 2,
                priceLineVisible: false
            });
            allLine.setData([
                { time: tFirst, value: avgAll },
                { time: tLast, value: avgAll }
            ]);

            chart.subscribeCrosshairMove((param) => {
                if (
                    ! param.time ||
                    ! param.point ||
                    param.point.x < 0 ||
                    param.point.y < 0 ||
                    param.point.x > container.clientWidth ||
                    param.point.y > container.clientHeight
                ) {
                    tooltip.style.opacity = '0';
                    return;
                }

                const dataPoint = param.seriesData.get(series);
                if ( ! dataPoint) {
                    tooltip.style.opacity = '0';
                    return;
                }

                const { open, high, low, close } = dataPoint;

                tooltip.innerHTML = `
        <div>O: ${ open.toFixed(2) }</div>
        <div>H: ${ high.toFixed(2) }</div>
        <div>L: ${ low.toFixed(2) }</div>
        <div>C: ${ close.toFixed(2) }</div>
    `;

                tooltip.style.left = ( param.point.x + 10 ) + 'px';
                tooltip.style.top = ( param.point.y + 10 ) + 'px';
                tooltip.style.opacity = '1';
            });

            chart.timeScale().fitContent();
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (quoteDateInput) {
                quoteDateInput.addEventListener('change', onQuoteDateChange);
            }

            const symbol = symbolInput ? symbolInput.value : null;
            const date = quoteDateInput ? quoteDateInput.value : null;
            const expiry = expiryDateInput ? expiryDateInput.value : null;

            const ceStrikesPhp = @json($ceStrikes ?? []);
            const avgAtmPhp = @json($avgAtm);
            const avgAllPhp = @json($avgAll);
            const prevMidPoints = @json($prevMidPoints);

            if (symbol && date && expiry && ceStrikesPhp.length && prevMidPoints && avgAllPhp) {
                // avgAtm is prevMidPoints, avgAll is avgAllPhp
                loadAllCharts(symbol, expiry, date, ceStrikesPhp, prevMidPoints, avgAllPhp);
            }

            const toggleBtn = document.getElementById('toggle-saturation-btn');
            const satWrapper = document.getElementById('saturation-wrapper');
            const closeBtn = document.getElementById('close-saturation-btn');

            if (toggleBtn && satWrapper) {
                const hidePanel = () => {
                    satWrapper.classList.add('hidden');
                    toggleBtn.textContent = 'Show saturation grid';
                };

                const showPanel = () => {
                    satWrapper.classList.remove('hidden');
                    toggleBtn.textContent = 'Hide saturation grid';
                };

                toggleBtn.addEventListener('click', () => {
                    const isHidden = satWrapper.classList.contains('hidden');
                    if (isHidden) {
                        showPanel();
                    } else {
                        hidePanel();
                    }
                });

                if (closeBtn) {
                    closeBtn.addEventListener('click', hidePanel);
                }
            }


            const body = document.body;
            let currentFullscreenCard = null;

            function resizeChartInCard(card) {
                const chartDiv = card.querySelector('div[id^="chart-"], div[id^="combo-line-chart-"]');
                if (!chartDiv || !window.LightweightCharts) return;

                const chart = chartDiv._chartInstance;
                if (!chart) return;

                const width  = card.clientWidth  || window.innerWidth;
                const height = card.clientHeight ? card.clientHeight - 60 : window.innerHeight * 0.85;

                chart.applyOptions({ width, height });
                chart.timeScale().fitContent();   // <=== important so data fills the width
            }

            function enterFullscreen(card) {
                if (currentFullscreenCard) {
                    exitFullscreen(currentFullscreenCard);
                }

                currentFullscreenCard = card;

                card.classList.add('fixed', 'inset-0', 'z-50', 'bg-white', 'p-4', 'overflow-auto');
                card.style.width  = '100vw';
                card.style.height = '100vh';

                document.body.classList.add('overflow-hidden');

                const chartDiv = card.querySelector('div[id^="chart-"], div[id^="combo-line-chart-"]');
                if (chartDiv) {
                    chartDiv.classList.remove('h-64');
                    chartDiv.classList.add('h-[85vh]');
                }

                resizeChartInCard(card);
            }


            function exitFullscreen(card) {
                card.classList.remove(
                    'fixed', 'inset-0', 'z-50',
                    'bg-white', 'p-4', 'overflow-auto'
                );
                card.style.width = '';
                card.style.height = '';

                body.classList.remove('overflow-hidden');

                const chartDiv = card.querySelector('div[id^="chart-"], div[id^="combo-line-chart-"]');
                if (chartDiv) {
                    chartDiv.classList.remove('h-[80vh]');
                    chartDiv.classList.add('h-64');
                }

                resizeChartInCard(card);
                currentFullscreenCard = null;
            }

            document.querySelectorAll('.chart-fullscreen-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const card = btn.closest('.chart-card');
                    if (!card) return;

                    const isFull = card.classList.contains('fixed');

                    if (isFull) {
                        exitFullscreen(card);
                        btn.textContent = 'Full';
                    } else {
                        enterFullscreen(card);
                        btn.textContent = 'Close';
                    }
                });
            });

            window.addEventListener('resize', () => {
                if (currentFullscreenCard) {
                    resizeChartInCard(currentFullscreenCard);
                }
            });
        });
    </script>

@endsection
