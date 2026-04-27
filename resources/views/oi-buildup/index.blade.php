@extends('layouts.app')

@section('title')
    OI Buildup Backtest
@endsection

@section('content')

    @if(!empty($datasets[5]))
        @php
            $threshold = $oiThreshold ?? 1500000;
            $triggerRows = collect($datasets[5])->filter(function ($row) use ($threshold) {
                return abs($row['delta_oi']) >= $threshold;
            });
        @endphp

        @if($triggerRows->isNotEmpty())
            <div id="oi-alert-data"
                data-threshold="{{ $threshold }}"
                data-count="{{ $triggerRows->count() }}"
                data-timestamp="{{ $triggerRows->first()['timestamp'] }}"
                data-details='@json($triggerRows->values())'>
            </div>
        @endif
    @endif

    <div class="max-w-full mx-auto px-4">

        {{-- Filters --}}
        <form method="GET" action="{{ route('test.oi-buildup.index') }}" id="oi_filter_form"
            class="grid grid-cols-1 md:grid-cols-5 lg:grid-cols-5 gap-4 mb-4">

            <div>
                <h1 class="text-xl font-semibold text-gray-900 mb-6">OI Backtest Buildup</h1>
                <input type="hidden" name="underlying_symbol" id="underlying_symbol" value="NIFTY">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">At</label>
                <div class="mt-1 flex items-center gap-1">
                    <input
                        id="at_input"
                        type="datetime-local"
                        name="at"
                        value="{{ $filters['at']
                            ? \Carbon\Carbon::parse($filters['at'])->format('Y-m-d\TH:i')
                            : now()->format('Y-m-d') . 'T09:15' }}"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                    />
                    <button type="button" id="time_up"
                        title="Forward 5 minutes"
                        class="flex-shrink-0 inline-flex items-center justify-center w-7 h-7 rounded-md bg-green-500 hover:bg-green-600 text-white shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-green-400">
                        ▲
                    </button>
                    <button type="button" id="time_down"
                        title="Back 5 minutes"
                        class="flex-shrink-0 inline-flex items-center justify-center w-7 h-7 rounded-md bg-red-500 hover:bg-red-600 text-white shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-red-400">
                        ▼
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Expiry</label>
                <input
                    id="expiry_input"
                    type="date"
                    name="expiry"
                    value="{{ $filters['expiry'] }}"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Top N</label>
                <input type="number" name="limit" min="1" max="100"
                    value="{{ $filters['limit'] }}"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
            </div>

            <div class="flex items-end">
                <button type="submit" id="buildup_submit"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Apply Filters
                </button>
            </div>

        </form>

        @isset($no_filter)
            <p class="text-sm text-gray-500 italic">No filter applied. Please select a date &amp; time.</p>
        @endisset

        {{-- Loader overlay --}}
        <div id="oi-loader" class="hidden fixed inset-0 z-40 flex items-center justify-center bg-white/60 backdrop-blur-sm">
            <div class="flex flex-col items-center gap-3">
                <svg class="animate-spin h-10 w-10 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span class="text-sm font-medium text-indigo-600">Loading...</span>
            </div>
        </div>

        {{-- Results — AJAX swaps this entire container --}}
        <div id="oi-data-container">
            <script>
                window.oiBuildupData = @json($datasets);
            </script>

            <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-3 gap-2">
                @foreach([5, 10, 15, 30, 60, 375] as $i)
                    <div class="bg-white shadow rounded-lg p-4 flex flex-col">
                        <div class="flex items-center justify-between mb-2">
                            <h2 class="text-sm font-semibold text-gray-800">OI Buildup {{ $i }} min</h2>
                            <span class="text-xs text-gray-500">Top {{ $filters['limit'] }}</span>
                        </div>
                        <div class="flex-1">
                            @php $rows = $datasets[$i] ?? []; @endphp
                            @if(empty($rows))
                                <p class="text-xs text-gray-400 italic">No data for this interval.</p>
                            @else
                                <div id="chart-{{ $i }}" class="h-75"></div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>

    {{-- OI Alert Modal --}}
    <div id="oiAlertModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-4">
            <div class="flex justify-between items-center mb-2">
                <h2 class="text-lg font-semibold">OI Buildup Alert</h2>
                <button id="oiAlertClose" class="text-gray-500 hover:text-gray-800">&times;</button>
            </div>
            <div id="oiAlertContent" class="text-sm text-gray-800 space-y-2">
                {{-- Filled by JS --}}
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    {{-- ═══════════════════════════════════════════════════════════════
         Chart renderer — global so AJAX can call renderAllCharts()
    ════════════════════════════════════════════════════════════════ --}}
    <script>
        function renderAllCharts() {
            const datasets = window.oiBuildupData || {};

            // Destroy existing chart instances before re-rendering
            [5, 10, 15, 30, 60, 375].forEach(function (interval) {
                const el = document.querySelector('#chart-' + interval);
                if (el && el._apexcharts) {
                    el._apexcharts.destroy();
                    el._apexcharts = null;
                    el.innerHTML = '';
                }
            });

            function formatIndianNumber(num) {
                const n = Math.abs(num);
                if (n >= 1e7) return (num / 1e7).toFixed(1).replace(/\.0$/, '') + ' C';
                if (n >= 1e5) return (num / 1e5).toFixed(1).replace(/\.0$/, '') + ' L';
                if (n >= 1e3) return Math.round(num).toString();
                return num.toString();
            }

            const colorByType = {
                Long:    '#16a34a',
                Short:   '#dc2626',
                Cover:   '#0d2a7c',
                Unwind:  '#facc15',
                Neutral: '#6b7280'
            };

            [5, 10, 15, 30, 60, 375].forEach(function (interval) {
                const rows = datasets[interval] || [];
                if (!rows.length) return;

                const categories = rows.map(r => parseInt(r.strike) + ' ' + r.instrument_type);
                const values     = rows.map(r => Math.abs(r.delta_oi));
                const colors     = rows.map(r => colorByType[r.buildup] || colorByType.Neutral);

                const options = {
                    chart: { type: 'bar', height: 350, toolbar: { show: true } },
                    plotOptions: {
                        bar: { horizontal: true, distributed: true, barHeight: '70%' }
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: val => formatIndianNumber(val)
                    },
                    xaxis: {
                        categories: categories,
                        labels: { formatter: val => formatIndianNumber(val) },
                        title: { text: 'ΔOI' }
                    },
                    yaxis: {
                        labels: { style: { fontSize: '14px', fontWeight: 'bold' } }
                    },
                    series: [{ name: 'ΔOI', data: values }],
                    colors: colors,
                    tooltip: {
                        y: {
                            formatter: (val, opts) => {
                                const row = rows[opts.dataPointIndex];
                                return [
                                    'ΔOI: ' + row.delta_oi,
                                    'ΔPx: ' + row.delta_price.toFixed(2),
                                    'Type: ' + row.buildup
                                ].join(' | ');
                            }
                        }
                    },
                    grid: { borderColor: '#e5e7eb' }
                };

                const el = document.querySelector('#chart-' + interval);
                if (el) {
                    const chart = new ApexCharts(el, options);
                    chart.render();
                    el._apexcharts = chart;
                }
            });
        }

        // Initial render on page load
        renderAllCharts();
    </script>

    {{-- ═══════════════════════════════════════════════════════════════
         AJAX loader + arrow navigation
    ════════════════════════════════════════════════════════════════ --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const STEP_MINUTES   = 5;
            const MARKET_START   = '09:15';
            const MARKET_END     = '15:25';

            const atInput      = document.getElementById('at_input');
            const expiryInput  = document.getElementById('expiry_input');
            const underlyingEl = document.getElementById('underlying_symbol');
            const btnUp        = document.getElementById('time_up');
            const btnDown      = document.getElementById('time_down');
            const form         = document.getElementById('oi_filter_form');

            if (!atInput || !expiryInput || !underlyingEl) return;

            // ── Helpers ──────────────────────────────────────────────
            const toMinutes = hhmm => {
                const [h, m] = hhmm.split(':').map(Number);
                return h * 60 + m;
            };
            const toHHMM = mins => {
                const h = String(Math.floor(mins / 60)).padStart(2, '0');
                const m = String(mins % 60).padStart(2, '0');
                return h + ':' + m;
            };

            const marketStartMins = toMinutes(MARKET_START);
            const marketEndMins   = toMinutes(MARKET_END);

            // ── Loader helpers ───────────────────────────────────
            const loader = document.getElementById('oi-loader');
            const showLoader = () => loader && loader.classList.remove('hidden');
            const hideLoader = () => loader && loader.classList.add('hidden');

            // ── Core AJAX loader ─────────────────────────────────────
            function loadData(at) {
                const sym = underlyingEl.value;
                if (!at || !sym) return;

                showLoader();

                // Step 1: fetch correct expiry for this at + symbol
                fetch(`{{ route('oi-buildup.expiries') }}?underlying_symbol=${encodeURIComponent(sym)}&at=${encodeURIComponent(at)}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.expiry) {
                            expiryInput.value = data.expiry;
                        } else {
                            expiryInput.value = '';
                        }

                        // Step 2: load OI data with updated params
                        if (!form) return;

                        const params = new URLSearchParams(new FormData(form));
                        params.set('at', at);
                        if (data.expiry) params.set('expiry', data.expiry);

                        fetch(form.action + '?' + params.toString(), {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        })
                            .then(r => r.text())
                            .then(html => {
                                const parser  = new DOMParser();
                                const doc     = parser.parseFromString(html, 'text/html');

                                // Swap the data container HTML
                                const newContainer = doc.getElementById('oi-data-container');
                                const curContainer = document.getElementById('oi-data-container');
                                if (newContainer && curContainer) {
                                    curContainer.innerHTML = newContainer.innerHTML;
                                }

                                // Re-execute the oiBuildupData script from the response
                                // (innerHTML injection does NOT run <script> tags)
                                doc.querySelectorAll('script').forEach(function (script) {
                                    if (script.textContent.includes('oiBuildupData')) {
                                        try {
                                            new Function(script.textContent)();
                                        } catch (e) {
                                            console.error('Failed to update oiBuildupData', e);
                                        }
                                    }
                                });

                                // Re-render charts with fresh data
                                renderAllCharts();
                                hideLoader();
                            })
                            .catch(err => { console.error('OI load error', err); hideLoader(); });
                    })
                    .catch(err => { console.error('Expiry fetch error', err); hideLoader(); });
            }

            // ── Manual at input change ───────────────────────────────
            atInput.addEventListener('change', () => loadData(atInput.value));

            // ── Arrow buttons ────────────────────────────────────────
            function shiftTime(delta) {
                const current = atInput.value;
                if (!current) return;

                const [datePart, timePart] = current.split('T');
                let timeMins = toMinutes(timePart);
                timeMins = Math.min(Math.max(timeMins + delta, marketStartMins), marketEndMins);
                atInput.value = datePart + 'T' + toHHMM(timeMins);

                loadData(atInput.value);
            }

            if (btnUp)   btnUp.addEventListener('click',   () => shiftTime(+STEP_MINUTES));
            if (btnDown) btnDown.addEventListener('click', () => shiftTime(-STEP_MINUTES));
        });
    </script>

    {{-- ═══════════════════════════════════════════════════════════════
         OI Alert Modal
    ════════════════════════════════════════════════════════════════ --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const alertDataEl = document.getElementById('oi-alert-data');
            if (!alertDataEl) return;

            const threshold    = parseInt(alertDataEl.dataset.threshold, 10);
            const rows         = JSON.parse(alertDataEl.dataset.details || '[]');
            const barTimestamp = alertDataEl.dataset.timestamp;

            if (!rows.length || !barTimestamp) return;

            const symbol = "{{ $filters['underlying_symbol'] ?? '' }}";
            const expiry = "{{ $filters['expiry'] ?? '' }}";
            const key    = `oiAlert:${symbol}:${expiry}:${barTimestamp}:thr:${threshold}`;

            if (window.localStorage.getItem(key) === '1') return;

            const modal    = document.getElementById('oiAlertModal');
            const closeBtn = document.getElementById('oiAlertClose');
            const contentEl = document.getElementById('oiAlertContent');

            let html = `<p>Found ${rows.length} contracts with |ΔOI| ≥ ${threshold.toLocaleString()} in 5-minute data.</p>`;
            html += '<ul class="list-disc pl-5 space-y-1">';
            rows.slice(0, 5).forEach(r => {
                html += `<li>
                    <span class="font-semibold">${r.strike} ${r.instrument_type}</span>
                    &nbsp;(${r.buildup}) |
                    ΔOI: ${r.delta_oi.toLocaleString()} |
                    ΔPrice: ${r.delta_price.toFixed(2)}
                </li>`;
            });
            html += '</ul>';
            contentEl.innerHTML = html;

            modal.classList.remove('hidden');
            modal.classList.add('flex');

            const hideModal = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                window.localStorage.setItem(key, '1');
            };

            closeBtn.addEventListener('click', hideModal);
            modal.addEventListener('click', e => { if (e.target === modal) hideModal(); });
        });
    </script>

@endsection
