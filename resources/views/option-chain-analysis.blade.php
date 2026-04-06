@extends('layouts.app')

@section('title', 'NIFTY OI & Volume Difference')

@section('content')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>



    <div class="max-w-full mx-auto px-4 py-6 space-y-6 bg-slate-50 min-h-screen">
        <header class="sticky top-0 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="max-w-screen-2xl mx-auto px-4 py-3 flex items-center justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-3">
                    <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
                        <rect width="28" height="28" rx="7" fill="#0f766e" fill-opacity=".15"/>
                        <path d="M6 21 L10 15 L14 18 L18 10 L22 14" stroke="#0f766e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="22" cy="14" r="2.5" fill="#0f766e"/>
                    </svg>
                    <div>
                        <span class="font-bold text-[15px] text-slate-900">OI Pulse</span>
                        <span class="ml-2 text-[11px] font-mono text-slate-500">Option Chain Analysis</span>
                    </div>
                </div>

                <div class="flex items-center gap-3 flex-wrap">
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-slate-500">Date</label>
                        <input type="date" id="inp-date"
                            class="text-xs px-3 py-1.5 rounded-md font-mono bg-slate-100 border border-slate-300 text-slate-900">
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-slate-500">Strikes ±</label>
                        <input type="number" id="inp-strikes" value="3" min="1" max="15"
                            class="w-16 text-xs px-3 py-1.5 rounded-md font-mono text-center bg-slate-100 border border-slate-300 text-slate-900">
                    </div>
                    <button id="btn-load"
                        class="flex items-center gap-1.5 px-4 py-1.5 rounded-md text-xs font-semibold transition-all bg-teal-700 hover:bg-teal-800 text-white shadow-sm">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M3 12a9 9 0 019-9 9.75 9.75 0 016.74 2.74L21 8"/>
                            <path d="M21 3v5h-5"/>
                            <path d="M21 12a9 9 0 01-9 9 9.75 9.75 0 01-6.74-2.74L3 16"/>
                            <path d="M8 16H3v5"/>
                        </svg>
                        Load
                    </button>
                </div>
            </div>
        </header>

        <main class="max-w-screen-2xl mx-auto px-4 py-5 space-y-5">

            <div id="info-bar" class="card px-5 py-3 flex flex-wrap items-center justify-between gap-5 hidden">
                <div class="flex flex-wrap gap-7">
                    @foreach(['expiry'=>'EXPIRY','spot'=>'SPOT','atm'=>'ATM STRIKE','strikes-lbl'=>'STRIKES'] as $id=>$label)
                        <div>
                            <p class="text-[10px] font-semibold tracking-widest uppercase text-slate-500">{{ $label }}</p>
                            <p id="lbl-{{ $id }}" class="font-mono font-semibold text-sm mt-0.5 text-slate-900">—</p>
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center gap-5">
                    <div>
                        <p class="text-[10px] font-semibold tracking-widest uppercase mb-1 text-slate-500">OVERALL SIGNAL</p>
                        <span id="overall-signal" class="font-bold text-xl px-4 py-1 rounded-lg">—</span>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold tracking-widest uppercase mb-1 text-slate-500">BUILD UP</p>
                        <span id="overall-buildup" class="badge">—</span>
                    </div>
                    <div class="text-center">
                        <p class="text-[10px] font-semibold tracking-widest uppercase mb-1 text-slate-500">BUY / SELL</p>
                        <span id="buy-sell-counts" class="font-mono text-sm text-slate-900">—</span>
                    </div>
                </div>
            </div>

            <section id="sec-cum" class="card p-5 reveal hidden">
                <div class="flex items-start justify-between mb-4 gap-4">
                    <div>
                        <h2 class="font-semibold text-[13px] text-slate-900">Cumulative OI &amp; Price Signal — 09:20 → Latest</h2>
                        <p class="text-xs mt-0.5 text-slate-500">
                            Running total of all selected strikes · bar colour = build-up · line = price momentum
                        </p>
                    </div>
                </div>
                <div class="h-[270px] relative">
                    <canvas id="chart-cum"></canvas>
                </div>
            </section>

            <section id="sec-tabs" class="card p-4 hidden">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold text-[13px] text-slate-900">5-Minute Candle Navigator</h2>
                    <p class="text-xs text-slate-500">Recent → Old · tap a candle to drill down</p>
                </div>
                <div id="ts-tabs" class="flex gap-2 overflow-x-auto pb-1 flex-wrap"></div>
            </section>

            <div id="sec-candle" class="grid grid-cols-1 lg:grid-cols-2 gap-5 hidden">
                <section class="card p-5 reveal">
                    <div class="mb-4">
                        <h2 class="font-semibold text-[13px] text-slate-900">
                            OI &amp; Volume Change
                            <span id="lbl-ts-a" class="font-mono ml-1 text-teal-700">—</span>
                        </h2>
                        <p class="text-xs mt-0.5 text-slate-500">CE vs PE diff_oi &amp; diff_volume stacked per strike</p>
                    </div>
                    <div class="h-[265px] relative">
                        <canvas id="chart-oi"></canvas>
                    </div>
                </section>

                <section class="card p-5 reveal">
                    <div class="mb-4">
                        <h2 class="font-semibold text-[13px] text-slate-900">
                            Price Δ &amp; Build-Up
                            <span id="lbl-ts-b" class="font-mono ml-1 text-teal-700">—</span>
                        </h2>
                        <p class="text-xs mt-0.5 text-slate-500">CE+PE diff_price bars · combined line coloured by build-up</p>
                    </div>
                    <div class="h-[265px] relative">
                        <canvas id="chart-price"></canvas>
                    </div>
                </section>
            </div>

            <section id="sec-table" class="card p-5 reveal hidden">
                <div class="flex items-center justify-between mb-3 flex-wrap gap-3">
                    <h2 class="font-semibold text-[13px] text-slate-900">
                        Strike Detail —
                        <span id="lbl-ts-tbl" class="font-mono text-teal-700">—</span>
                    </h2>
                    <div id="tbl-signals" class="flex gap-2"></div>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table w-full">
                        <thead>
                        <tr>
                            <th>Strike</th>
                            <th>CE Δ OI</th>
                            <th>PE Δ OI</th>
                            <th>CE Δ Vol</th>
                            <th>PE Δ Vol</th>
                            <th>CE Δ Price</th>
                            <th>PE Δ Price</th>
                            <th>CE Build</th>
                            <th>PE Build</th>
                            <th>Comb OI</th>
                            <th>Comb Build</th>
                        </tr>
                        </thead>
                        <tbody id="tbl-body"></tbody>
                    </table>
                </div>
                <div id="tbl-summary" class="mt-4 pt-4 flex flex-wrap gap-7 border-t border-slate-200 text-sm"></div>
            </section>

            <section class="card px-5 py-4">
                <p class="text-[11px] font-semibold uppercase tracking-widest mb-3 text-slate-500">Build-Up Legend</p>
                <div class="flex flex-wrap gap-5 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="badge badge-lb">LB</span>
                        <span class="text-slate-500">Long Build — OI↑ Price↑ → <strong class="pos">Bullish</strong></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="badge badge-sb">SB</span>
                        <span class="text-slate-500">Short Build — OI↑ Price↓ → <strong class="neg">Bearish</strong></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="badge badge-sc">SC</span>
                        <span class="text-slate-500">Short Cover — OI↓ Price↑ → <strong class="pos">Bullish</strong></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="badge badge-lu">LU</span>
                        <span class="text-slate-500">Long Unwind — OI↓ Price↓ → <strong class="neg">Bearish</strong></span>
                    </div>
                </div>
            </section>

            <div id="empty-state" class="card p-16 flex flex-col items-center text-center gap-4">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.2">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 8v4l3 3"/>
                </svg>
                <div>
                    <h3 class="font-semibold text-base text-slate-900">Awaiting data</h3>
                    <p class="text-xs mt-1 text-slate-500" id="empty-msg">Select a date and press Load.</p>
                </div>
                <button id="btn-retry"
                    class="px-4 py-2 rounded-md text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-300 hover:bg-slate-200">
                    Retry
                </button>
            </div>

            <div id="loading" class="hidden fixed inset-0 flex items-center justify-center bg-black/55 backdrop-blur-sm">
                <div class="card p-7 flex flex-col items-center gap-3 shadow-lg">
                    <svg class="spin" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#0f766e" stroke-width="2.5">
                        <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                    </svg>
                    <p class="text-sm font-medium text-slate-900">Fetching option chain…</p>
                </div>
            </div>

        </main>

        <style>
            .card {
                border-radius: 0.75rem;
                border: 1px solid #e5e7eb;
                background: #ffffff;
            }

            .badge {
                display: inline-flex;
                align-items: center;
                padding: 2px 8px;
                border-radius: 9999px;
                font-size: 11px;
                font-weight: 600;
                letter-spacing: .03em;
                text-transform: uppercase;
            }

            .badge-lb {
                background: rgba(63, 185, 80, .15);
                color: #15803d;
            }

            .badge-sb {
                background: rgba(248, 81, 73, .15);
                color: #dc2626;
            }

            .badge-sc {
                background: rgba(88, 166, 255, .15);
                color: #2563eb;
            }

            .badge-lu {
                background: rgba(210, 153, 34, .15);
                color: #a16207;
            }

            .badge-buy {
                background: rgba(63, 185, 80, .15);
                color: #15803d;
            }

            .badge-sell {
                background: rgba(248, 81, 73, .15);
                color: #dc2626;
            }

            .ts-tab.active {
                background: #0f766e !important;
                color: #ffffff !important;
                border-color: #0f766e !important;
            }

            .data-table th {
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: #6b7280;
                padding: 8px 12px;
                text-align: right;
                border-bottom: 1px solid #e5e7eb;
                white-space: nowrap;
            }

            .data-table th:first-child,
            .data-table td:first-child {
                text-align: left;
            }

            .data-table td {
                padding: 7px 12px;
                font-size: 12px;
                font-variant-numeric: tabular-nums;
                text-align: right;
                border-bottom: 1px solid #e5e7eb;
                white-space: nowrap;
            }

            .data-table tr:last-child td {
                border-bottom: none;
            }

            .data-table tr:hover td {
                background: #f9fafb;
            }

            .pos {
                color: #15803d;
            }

            .neg {
                color: #dc2626;
            }

            .dim {
                color: #6b7280;
            }

            @keyframes spin {
                to {
                    transform: rotate(360deg);
                }
            }

            .spin {
                animation: spin .8s linear infinite;
            }

            .reveal {
                transform: translateY(6px);
                transition: transform .35s ease;
            }

            .reveal.on {
                transform: none;
            }
        </style>
        <script>
            const COLORS = {
                primary: '#0f766e',
                text: '#0f172a',
                muted: '#6b7280',
                buy: '#15803d',
                sell: '#dc2626',
                lb: '#15803d',
                sb: '#dc2626',
                sc: '#2563eb',
                lu: '#a16207',
                surface: '#ffffff',
                grid: 'rgba(0,0,0,.05)'
            };

            const fmt = (n, d = 0) => {
                if (n === null || n === undefined) return '—';
                const a = Math.abs(n);
                return a >= 1e7 ? ( n / 1e7 ).toFixed(2) + ' Cr'
                    : a >= 1e5 ? ( n / 1e5 ).toFixed(2) + ' L'
                        : Number(n).toLocaleString('en-IN', {
                            minimumFractionDigits: d,
                            maximumFractionDigits: d
                        });
            };

            const cfmt = (n, d = 0) => {
                const cls = n > 0 ? 'pos' : n < 0 ? 'neg' : 'dim';
                return `<span class="${ cls }">${ n > 0 ? '+' : '' }${ fmt(n, d) }</span>`;
            };

            const buBadge = bu => {
                if ( ! bu) return '<span class="dim">—</span>';
                const m = {
                    'Long Build': 'badge-lb',
                    'Short Build': 'badge-sb',
                    'Short Cover': 'badge-sc',
                    'Long Unwind': 'badge-lu'
                };
                const s = {
                    'Long Build': 'LB',
                    'Short Build': 'SB',
                    'Short Cover': 'SC',
                    'Long Unwind': 'LU'
                };
                return `<span class="badge ${ m[ bu ] || '' }" title="${ bu }">${ s[ bu ] || bu }</span>`;
            };

            const buColor = bu => ( {
                'Long Build': COLORS.lb,
                'Short Build': COLORS.sb,
                'Short Cover': COLORS.sc,
                'Long Unwind': COLORS.lu
            }[ bu ] || COLORS.muted );

            const charts = {};
            const destroyChart = id => {
                if (charts[ id ]) {
                    charts[ id ].destroy();
                    delete charts[ id ];
                }
            };

            let DATA = null, activeTs = null;

            async function load () {
                const date = document.getElementById('inp-date').value;
                const strikes = document.getElementById('inp-strikes').value;
                const url = `{{ route('option-chain.analysis.data') }}?strikes=${ strikes }&date=${ date }`;
                document.getElementById('loading').classList.remove('hidden');
                hideAll();

                try {
                    const r = await fetch(url);
                    if ( ! r.ok) throw new Error(( await r.json() ).error || 'Server error');
                    DATA = await r.json();
                    showAll();
                    renderAll();
                } catch (e) {
                    document.getElementById('empty-state').classList.remove('hidden');
                    document.getElementById('empty-msg').textContent = e.message;
                } finally {
                    document.getElementById('loading').classList.add('hidden');
                }
            }

            function hideAll () {
                ['info-bar', 'sec-cum', 'sec-tabs', 'sec-candle', 'sec-table'].forEach(id => {
                    document.getElementById(id)?.classList.add('hidden');
                });
                document.getElementById('empty-state').classList.add('hidden');
            }

            function showAll () {
                ['info-bar', 'sec-cum', 'sec-tabs', 'sec-candle', 'sec-table'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.classList.remove('hidden');
                        requestAnimationFrame(() => el.classList.add('on'));
                    }
                });
            }

            function renderAll () {
                if ( ! DATA) return;
                document.getElementById('lbl-expiry').textContent = DATA.expiry || '—';
                document.getElementById('lbl-spot').textContent = DATA.spot ? '₹' + DATA.spot.toLocaleString('en-IN') : '—';
                document.getElementById('lbl-atm').textContent = DATA.atm_strike || '—';
                document.getElementById('lbl-strikes-lbl').textContent = ( DATA.selected_strikes || [] ).join(', ');

                renderTabs();
                renderCumChart();

                const latest = DATA.ts_data[ DATA.ts_data.length - 1 ];
                if (latest) updateSignal(latest);
                const lastTs = DATA.timestamps[ DATA.timestamps.length - 1 ];
                selectTs(lastTs);
            }

            function updateSignal (row) {
                const sig = row.signal, bu = row.overall_build_up;
                const el = document.getElementById('overall-signal');
                el.textContent = sig;
                el.className = sig === 'Buy'
                    ? 'font-bold text-xl px-4 py-1 rounded-lg bg-green-100 text-green-700'
                    : 'font-bold text-xl px-4 py-1 rounded-lg bg-red-100 text-red-700';

                const buEl = document.getElementById('overall-buildup');
                buEl.className = 'badge ' + ( {
                    'Long Build': 'badge-lb',
                    'Short Build': 'badge-sb',
                    'Short Cover': 'badge-sc',
                    'Long Unwind': 'badge-lu'
                }[ bu ] || '' );
                buEl.textContent = bu || '—';

                document.getElementById('buy-sell-counts').innerHTML =
                    `<span class="pos font-bold">${ row.buy_count }B</span> / <span class="neg font-bold">${ row.sell_count }S</span>`;
            }

            function renderTabs () {
                const con = document.getElementById('ts-tabs');
                con.innerHTML = '';

                [...DATA.timestamps].reverse().forEach(ts => {
                    const row = DATA.ts_data.find(r => r.ts === ts);
                    const sig = row ? row.signal : '';
                    const dot = `<span class="w-2 h-2 rounded-full inline-block flex-shrink-0 ${ sig === 'Buy' ? 'bg-green-600' : 'bg-red-600' }"></span>`;

                    const btn = document.createElement('button');
                    btn.className = 'ts-tab flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-mono font-medium transition-all whitespace-nowrap bg-slate-100 text-slate-800 border border-slate-300 hover:bg-slate-200';
                    btn.setAttribute('data-ts', ts);
                    btn.innerHTML = `${ dot }${ ts }`;
                    btn.addEventListener('click', () => selectTs(ts));
                    con.appendChild(btn);
                });
            }

            function selectTs (ts) {
                activeTs = ts;
                document.querySelectorAll('.ts-tab').forEach(b => {
                    const active = b.getAttribute('data-ts') === ts;
                    b.classList.toggle('active', active);
                });

                const row = DATA.ts_data.find(r => r.ts === ts);
                if ( ! row) return;

                ['lbl-ts-a', 'lbl-ts-b', 'lbl-ts-tbl'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = ts;
                });

                updateSignal(row);
                renderOIChart(row);
                renderPriceChart(row);
                renderTable(row);
            }

            function renderCumChart () {
                destroyChart('cum');
                const labels = DATA.cumulative.map(r => r.ts);
                const bgColors = DATA.cumulative.map(r => ( {
                    'Long Build': 'rgba(63,185,80,.2)',
                    'Short Build': 'rgba(248,81,73,.2)',
                    'Short Cover': 'rgba(88,166,255,.2)',
                    'Long Unwind': 'rgba(210,153,34,.2)'
                }[ r.build_up ] || 'rgba(125,133,144,.15)' ));
                const bdColors = DATA.cumulative.map(r => buColor(r.build_up));

                const ctx = document.getElementById('chart-cum').getContext('2d');
                charts[ 'cum' ] = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: 'Cum. Δ OI',
                                data: DATA.cumulative.map(r => r.cum_diff_oi),
                                backgroundColor: bgColors,
                                borderColor: bdColors,
                                borderWidth: 1.5,
                                borderRadius: 3,
                                yAxisID: 'y',
                                order: 2
                            },
                            {
                                label: 'Cum. Price Signal',
                                data: DATA.cumulative.map(r => r.cum_price),
                                type: 'line',
                                borderColor: COLORS.primary,
                                backgroundColor: 'transparent',
                                borderWidth: 2,
                                pointRadius: 3,
                                pointBackgroundColor: COLORS.primary,
                                tension: 0.35,
                                yAxisID: 'y2',
                                order: 1
                            }
                        ]
                    },
                    options: makeOpts({
                        scales: {
                            y: { position: 'left', title: { display: true, text: 'Cumulative OI Δ', color: COLORS.muted, font: { size: 10 } } },
                            y2: { position: 'right', title: { display: true, text: 'Price Signal', color: COLORS.muted, font: { size: 10 } }, grid: { drawOnChartArea: false } }
                        }
                    })
                });
            }

            function renderOIChart (row) {
                destroyChart('oi');
                const ctx = document.getElementById('chart-oi').getContext('2d');
                charts[ 'oi' ] = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: row.strike_details.map(s => s.strike),
                        datasets: [
                            {
                                label: 'CE Δ OI',
                                data: row.strike_details.map(s => s.ce_diff_oi),
                                backgroundColor: 'rgba(248,81,73,.55)',
                                borderColor: 'rgba(248,81,73,.85)',
                                borderWidth: 1,
                                borderRadius: 3,
                                stack: 'oi'
                            },
                            {
                                label: 'PE Δ OI',
                                data: row.strike_details.map(s => s.pe_diff_oi),
                                backgroundColor: 'rgba(63,185,80,.55)',
                                borderColor: 'rgba(63,185,80,.85)',
                                borderWidth: 1,
                                borderRadius: 3,
                                stack: 'oi'
                            },
                            {
                                label: 'CE Δ Vol',
                                data: row.strike_details.map(s => s.ce_diff_volume),
                                backgroundColor: 'rgba(248,81,73,.2)',
                                borderColor: 'rgba(248,81,73,.5)',
                                borderWidth: 1,
                                borderRadius: 3,
                                stack: 'vol',
                                yAxisID: 'y2'
                            },
                            {
                                label: 'PE Δ Vol',
                                data: row.strike_details.map(s => s.pe_diff_volume),
                                backgroundColor: 'rgba(63,185,80,.2)',
                                borderColor: 'rgba(63,185,80,.5)',
                                borderWidth: 1,
                                borderRadius: 3,
                                stack: 'vol',
                                yAxisID: 'y2'
                            }
                        ]
                    },
                    options: makeOpts({
                        scales: {
                            y: { stacked: true, position: 'left', title: { display: true, text: 'OI Change', color: COLORS.muted, font: { size: 10 } } },
                            y2: {
                                stacked: true,
                                position: 'right',
                                title: { display: true, text: 'Volume Change', color: COLORS.muted, font: { size: 10 } },
                                grid: { drawOnChartArea: false }
                            }
                        }
                    })
                });
            }

            function renderPriceChart (row) {
                destroyChart('price');
                const lineColors = row.strike_details.map(s => buColor(s.combined_build_up));
                const ctx = document.getElementById('chart-price').getContext('2d');
                charts[ 'price' ] = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: row.strike_details.map(s => s.strike),
                        datasets: [
                            {
                                label: 'CE Δ Price',
                                data: row.strike_details.map(s => s.ce_diff_price),
                                backgroundColor: 'rgba(248,81,73,.5)',
                                borderColor: 'rgba(248,81,73,.8)',
                                borderWidth: 1.5,
                                borderRadius: 3,
                                stack: 'p'
                            },
                            {
                                label: 'PE Δ Price',
                                data: row.strike_details.map(s => s.pe_diff_price),
                                backgroundColor: 'rgba(63,185,80,.5)',
                                borderColor: 'rgba(63,185,80,.8)',
                                borderWidth: 1.5,
                                borderRadius: 3,
                                stack: 'p'
                            },
                            {
                                label: 'Combined Δ Price',
                                data: row.strike_details.map(s => s.ce_diff_price + s.pe_diff_price),
                                type: 'line',
                                borderColor: lineColors,
                                segment: { borderColor: ctx2 => lineColors[ ctx2.p0DataIndex ] || COLORS.muted },
                                backgroundColor: 'transparent',
                                borderWidth: 2,
                                pointRadius: 7,
                                pointBackgroundColor: lineColors,
                                pointBorderColor: '#ffffff',
                                pointBorderWidth: 2,
                                tension: 0.3,
                                order: 0
                            }
                        ]
                    },
                    options: makeOpts({
                        scales: {
                            y: { stacked: false, position: 'left', title: { display: true, text: 'Price Change (₹)', color: COLORS.muted, font: { size: 10 } } }
                        }
                    })
                });
            }

            function makeOpts (extra = {}) {
                const tickCb = v => Math.abs(v) >= 1e5 ? ( v / 1e5 ).toFixed(1) + 'L' : Math.abs(v) >= 1e3 ? ( v / 1e3 ).toFixed(0) + 'K' : v;

                return {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'end',
                            labels: {
                                color: COLORS.muted,
                                font: { size: 11, family: 'Inter, sans-serif' },
                                boxWidth: 12,
                                padding: 12
                            }
                        },
                        tooltip: {
                            backgroundColor: '#ffffff',
                            borderColor: 'rgba(0,0,0,.1)',
                            borderWidth: 1,
                            titleColor: COLORS.text,
                            bodyColor: COLORS.muted,
                            padding: 10,
                            titleFont: { family: 'Inter, sans-serif', size: 12, weight: '600' },
                            bodyFont: { family: 'Inter, sans-serif', size: 11 },
                            callbacks: {
                                label: c => ' ' + c.dataset.label + ': ' + ( c.raw > 0 ? '+' : '' ) + Number(c.raw).toLocaleString('en-IN', { maximumFractionDigits: 2 })
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: COLORS.grid },
                            ticks: { color: COLORS.muted, font: { size: 10, family: 'JetBrains Mono, monospace' }, maxRotation: 0 }
                        },
                        y: {
                            grid: { color: COLORS.grid },
                            ticks: { color: COLORS.muted, font: { size: 10, family: 'JetBrains Mono, monospace' }, callback: tickCb },
                            ...( extra.scales?.y || {} )
                        },
                        ...( extra.scales?.y2 ? {
                            y2: {
                                grid: { color: COLORS.grid },
                                ticks: { color: COLORS.muted, font: { size: 10, family: 'JetBrains Mono, monospace' }, callback: tickCb },
                                ...extra.scales.y2
                            }
                        } : {} )
                    },
                    animation: { duration: 500, easing: 'easeOutQuart' }
                };
            }

            function renderTable (row) {
                const tbody = document.getElementById('tbl-body');
                tbody.innerHTML = '';

                row.strike_details.forEach(s => {
                    const isAtm = s.strike === DATA.atm_strike;
                    const tr = document.createElement('tr');
                    if (isAtm) tr.className = 'bg-teal-50';

                    tr.innerHTML = `
                        <td class="font-mono font-semibold ${ isAtm ? 'text-teal-700' : '' }">
                            ${ s.strike }
                            ${ isAtm ? '<span class="badge ml-1" style="background:rgba(15,118,110,.12);color:#0f766e;font-size:9px;">ATM</span>' : '' }
                        </td>
                        <td>${ cfmt(s.ce_diff_oi) }</td>
                        <td>${ cfmt(s.pe_diff_oi) }</td>
                        <td>${ cfmt(s.ce_diff_volume) }</td>
                        <td>${ cfmt(s.pe_diff_volume) }</td>
                        <td>${ cfmt(s.ce_diff_price, 2) }</td>
                        <td>${ cfmt(s.pe_diff_price, 2) }</td>
                        <td>${ buBadge(s.ce_build_up) }</td>
                        <td>${ buBadge(s.pe_build_up) }</td>
                        <td class="font-mono font-semibold">${ cfmt(s.combined_diff_oi) }</td>
                        <td>${ buBadge(s.combined_build_up) }</td>
                    `;
                    tbody.appendChild(tr);
                });

                document.getElementById('tbl-signals').innerHTML = `
                    <span class="badge ${ row.signal === 'Buy' ? 'badge-buy' : 'badge-sell' }">${ row.signal }</span>
                    ${ buBadge(row.overall_build_up) }
                `;

                document.getElementById('tbl-summary').innerHTML = `
                    <div class="flex flex-col"><span class="text-[10px] uppercase tracking-widest font-semibold text-slate-500">Total Δ OI</span><span class="font-mono font-semibold text-sm">${ cfmt(row.total_diff_oi) }</span></div>
                    <div class="flex flex-col"><span class="text-[10px] uppercase tracking-widest font-semibold text-slate-500">Total Δ Price</span><span class="font-mono font-semibold text-sm">${ cfmt(row.total_diff_price, 2) }</span></div>
                    <div class="flex flex-col"><span class="text-[10px] uppercase tracking-widest font-semibold text-slate-500">Build Up</span>${ buBadge(row.overall_build_up) }</div>
                    <div class="flex flex-col"><span class="text-[10px] uppercase tracking-widest font-semibold text-slate-500">Signal</span><span class="badge ${ row.signal === 'Buy' ? 'badge-buy' : 'badge-sell' }">${ row.signal }</span></div>
                    <div class="flex flex-col"><span class="text-[10px] uppercase tracking-widest font-semibold text-slate-500">Buy Signals</span><span class="font-mono font-bold pos">${ row.buy_count }</span></div>
                    <div class="flex flex-col"><span class="text-[10px] uppercase tracking-widest font-semibold text-slate-500">Sell Signals</span><span class="font-mono font-bold neg">${ row.sell_count }</span></div>
                `;
            }

            document.addEventListener('DOMContentLoaded', () => {
                document.getElementById('inp-date').value = new Date().toISOString().split('T')[ 0 ];
                document.getElementById('btn-load').addEventListener('click', load);
                document.getElementById('btn-retry').addEventListener('click', load);
                load();
            });
        </script>
    </div>
@endsection
