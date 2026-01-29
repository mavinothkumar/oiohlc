{{-- resources/views/options-chart-multi.blade.php --}}

@extends('layouts.app')

@section('title', 'Options Multi Chart')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
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
                    <div class="mt-1 grid grid-cols-5 gap-2 text-xs">
                        @for($i = 0; $i < 5; $i++)
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
                    <div class="mt-1 grid grid-cols-5 gap-2 text-xs">
                        @for($i = 0; $i < 5; $i++)
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
                    <button type="submit"
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
            <div class="mb-4 text-sm text-gray-700">
                <div>ATM index open:
                    <span class="font-semibold">
                    {{ number_format($atmIndexOpen, 2) }}
                </span>
                </div>
                <div>Avg (ATM CE + ATM PE) / 2:
                    <span class="font-semibold text-red-600">
                    {{ number_format($avgAtm, 2) }}
                </span>
                </div>
                <div>Avg (CE close + PE close) / 2:
                    <span class="font-semibold text-green-600">
                    {{ number_format($avgAll, 2) }}
                </span>
                </div>
            </div>
        @endif

        {{-- 10 charts: top 5 CE, bottom 5 PE --}}
        @if(!empty($ceStrikes) && !empty($peStrikes))
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($ceStrikes as $strike)
                    <div class="bg-white rounded-lg shadow p-2">
                        <div class="text-xs font-semibold mb-1">
                            CE {{ $strike }}
                        </div>
                        <div id="chart-ce-{{ $strike }}" class="h-64"></div>
                    </div>

                    <div class="bg-white rounded-lg shadow p-2">
                        <div class="text-xs font-semibold mb-1">
                            PE {{ $strike }}
                        </div>
                        <div id="chart-pe-{{ $strike }}" class="h-64"></div>
                    </div>
                @endforeach
            </div>
        @endif
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
        const expiriesUrl = "{{ route('api.multi-chart-expiries') }}"; // same endpoint as single-chart page [file:2]
        const ohlcUrl     = "{{ route('api.ohlc') }}";     // same OHLC JSON route       [file:1]

        const symbolInput     = document.querySelector('input[name="symbol"]');
        const quoteDateInput  = document.querySelector('input[name="quote_date"]');
        const expiryDateInput = document.querySelector('input[name="expiry_date"]');

        function getStrikeInputs(type) {
            // type must be 'ce' or 'pe'
            return Array.from(document.querySelectorAll(`input[name="${type}_strikes[]"]`));
        }

        function showLoader() {
            const loader = document.getElementById('loader');
            if (loader) loader.classList.remove('hidden');
        }

        function hideLoader() {
            const loader = document.getElementById('loader');
            if (loader) loader.classList.add('hidden');
        }

        function timeToLocal(originalTime) {
            const d = new Date(originalTime * 1000); // originalTime = UNIX seconds
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

        function normalizeData(arr) {
            if (!Array.isArray(arr)) return [];
            return arr
                .filter(c => c.time != null)
                .map(c => ({
                    time: timeToLocal(c.time),
                    open: Number(c.open),
                    high: Number(c.high),
                    low: Number(c.low),
                    close: Number(c.close),
                }))
                .sort((a, b) => a.time - b.time);
        }

        async function onQuoteDateChange() {
            const symbol = symbolInput.value;
            const date   = quoteDateInput.value;
            if (!symbol || !date) return;

            showLoader();
            try {
                const params = new URLSearchParams({
                    underlying_symbol: symbol,
                    date: date,
                });

                const res  = await fetch(expiriesUrl + '?' + params.toString());
                if (!res.ok) {
                    console.error('expiries request failed', res.status);
                    return;
                }
                const data = await res.json();

                const expiry = (data.expiries && data.expiries.length)
                    ? data.expiries[0]
                    : null;
                if (!expiry) return;
                expiryDateInput.value = expiry.substring(0, 10);

                const atm = parseFloat(data.open_atm_strike || data.atm_strike);
                if (!atm) return;

                const step = 50;
                const strikes = [
                    atm - 2 * step,
                    atm - 1 * step,
                    atm,
                    atm + 1 * step,
                    atm + 2 * step,
                ];

                const ceInputs = getStrikeInputs('ce');
                const peInputs = getStrikeInputs('pe');

                ceInputs.forEach((el, i) => el.value = strikes[i] ?? '');
                peInputs.forEach((el, i) => el.value = strikes[i] ?? '');
            } finally {
                hideLoader();
            }
        }

        async function loadAllCharts(symbol, expiry, date, strikes, avgAtm, avgAll) {
            showLoader();
            try {
                for (const strike of strikes) {
                    await Promise.all([
                        loadSingleChart(symbol, expiry, date, strike, 'CE', avgAtm, avgAll),
                        loadSingleChart(symbol, expiry, date, strike, 'PE', avgAtm, avgAll),
                    ]);
                }
            } finally {
                hideLoader();
            }
        }


        async function loadSingleChart(symbol, expiry, date, strike, type, avgAtm, avgAll) {
            const params = new URLSearchParams({
                underlying_symbol: symbol,
                expiry: expiry,
                date: date,
                ce_instrument_key: strike,
                pe_instrument_key: strike,
            });

            const res = await fetch(ohlcUrl + '?' + params.toString());
            if (!res.ok) {
                console.error('OHLC request failed', res.status, strike, type);
                return;
            }
            const data = await res.json();

            const containerId = (type === 'CE' ? 'chart-ce-' : 'chart-pe-') + strike;
            const container = document.getElementById(containerId);
            if (!container) {
                console.warn('Missing container', containerId);
                return;
            }

            const chart = LightweightCharts.createChart(container, {
                width: container.clientWidth || 400,
                height: container.clientHeight || 250,
                layout: { background: { color: 'white' }, textColor: '#000' },
                timeScale: { timeVisible: true, secondsVisible: false },
            });

            const seriesData = normalizeData(type === 'CE' ? data.ce_today : data.pe_today);
            const series = chart.addCandlestickSeries();   // v4 API
            series.setData(seriesData);

            if (!seriesData.length) return;

            const tFirst = seriesData[0].time;
            const tLast  = seriesData[seriesData.length - 1].time;

            const atmLine = chart.addLineSeries({        // v4 API
                color: 'red',
                lineWidth: 2,
                priceLineVisible: false,
            });
            atmLine.setData([
                { time: tFirst, value: avgAtm },
                { time: tLast,  value: avgAtm },
            ]);

            const allLine = chart.addLineSeries({        // v4 API
                color: 'green',
                lineWidth: 2,
                priceLineVisible: false,
            });
            allLine.setData([
                { time: tFirst, value: avgAll },
                { time: tLast,  value: avgAll },
            ]);
        }


        document.addEventListener('DOMContentLoaded', () => {
            if (quoteDateInput) {
                quoteDateInput.addEventListener('change', onQuoteDateChange);
            }

            const symbol = symbolInput ? symbolInput.value : null;
            const date   = quoteDateInput ? quoteDateInput.value : null;
            const expiry = expiryDateInput ? expiryDateInput.value : null;

            const ceStrikesPhp = @json($ceStrikes ?? []);
            const avgAtmPhp    = @json($avgAtm);
            const avgAllPhp    = @json($avgAll);

            if (symbol && date && expiry && ceStrikesPhp.length && avgAtmPhp && avgAllPhp) {
                loadAllCharts(symbol, expiry, date, ceStrikesPhp, avgAtmPhp, avgAllPhp);
            }
        });
    </script>
@endsection
