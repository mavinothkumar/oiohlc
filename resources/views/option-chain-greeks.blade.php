@extends('layouts.app')

@section('content')
    <div class="min-h-screen bg-slate-950 text-slate-100">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-medium uppercase tracking-[0.2em] text-cyan-400">NIFTY Short Strangle Monitor</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-white">Option Chain Greeks Dashboard</h1>
                    <p class="mt-2 max-w-3xl text-sm text-slate-400">
                        Track two selected strikes as a straddle or strangle, monitor combined premium decay, and read how Vega, Theta, Gamma, Delta, IV, and POP are affecting your short option structure.
                    </p>
                </div>
            </div>

            <form method="GET" action="{{ route('option-chain-greeks') }}" class="mb-6 rounded-2xl border border-slate-800 bg-slate-900/70 p-4 shadow-2xl shadow-black/20 backdrop-blur">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-7">
                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-400">Date</label>
                        <input type="date" name="date" value="{{ $date }}" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-slate-100 outline-none ring-0 transition focus:border-cyan-500" />
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-400">Expiry</label>
                        <select name="expiry" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-slate-100 outline-none focus:border-cyan-500">
                            @foreach($expiries as $item)
                                <option value="{{ $item->expiry_date }}" @selected($expiry == $item->expiry_date)>
                                    {{ \Carbon\Carbon::parse($item->expiry_date)->format('d M Y') }}
                                    @if($item->is_current) - Current @endif
                                    @if($item->is_next) - Next @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-400">Left Strike</label>
                        <select name="strike_left" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-slate-100 outline-none focus:border-cyan-500">
                            <option value="">Select strike</option>
                            @foreach($strikes as $strike)
                                <option value="{{ $strike }}" @selected((string)$strikeLeft === (string)$strike)>{{ number_format($strike, 2) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-400">Right Strike</label>
                        <select name="strike_right" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-slate-100 outline-none focus:border-cyan-500">
                            <option value="">Select strike</option>
                            @foreach($strikes as $strike)
                                <option value="{{ $strike }}" @selected((string)$strikeRight === (string)$strike)>{{ number_format($strike, 2) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-400">Entry Price</label>
                        <input type="number" step="0.01" name="enter_price" value="{{ $enterPrice }}" placeholder="e.g. 184.50" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-slate-100 outline-none focus:border-cyan-500" />
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-400">Greek Mode</label>
                        <select name="view_mode" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-slate-100 outline-none focus:border-cyan-500">
                            <option value="combined" @selected($viewMode === 'combined')>Combined</option>
                            <option value="individual" @selected($viewMode === 'individual')>Individual</option>
                        </select>
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="w-full rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-400">Apply Filter</button>
                    </div>
                </div>
            </form>

            @php
                $hasData = !empty($combinedPriceData['times']);
            @endphp

            @if(!$hasData)
                <div class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/70 px-6 py-12 text-center">
                    <h2 class="text-xl font-semibold text-white">Select date, expiry, and two strikes</h2>
                    <p class="mx-auto mt-3 max-w-2xl text-sm leading-6 text-slate-400">
                        Once you pick your straddle or strangle legs, the dashboard will plot the combined premium and each Greek so you can judge whether decay is working in your favor or whether directional or volatility risk is building against the short premium position.
                    </p>
                </div>
            @else
                <div class="grid grid-cols-1 gap-6">
                    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                        <h2 class="text-xl font-semibold text-white">Combined Premium Decay</h2>
                        <p class="mt-1 text-sm text-slate-400">This line adds both selected strike prices. For a short strangle or straddle, a falling line generally means premium decay is helping the trade.</p>
                        <div class="h-[360px] mt-4"><canvas id="combinedPriceChart"></canvas></div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                            <h3 class="text-lg font-semibold text-white">Vega</h3>
                            <p class="mt-1 text-sm text-slate-400">If Vega rises, option premiums usually expand and a short strangle can face MTM pressure. Falling Vega usually helps premium sellers.</p>
                            <div class="mt-4 h-[320px]"><canvas id="vegaChart"></canvas></div>
                        </div>

                        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                            <h3 class="text-lg font-semibold text-white">Theta</h3>
                            <p class="mt-1 text-sm text-slate-400">Theta is the decay engine. More negative net Theta in your sold options usually benefits the strategy as time passes without a strong move.</p>
                            <div class="mt-4 h-[320px]"><canvas id="thetaChart"></canvas></div>
                        </div>

                        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                            <h3 class="text-lg font-semibold text-white">Gamma</h3>
                            <p class="mt-1 text-sm text-slate-400">Higher Gamma means Delta can change quickly when spot moves. This is the risk that can suddenly accelerate losses for short option structures.</p>
                            <div class="mt-4 h-[320px]"><canvas id="gammaChart"></canvas></div>
                        </div>

                        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                            <h3 class="text-lg font-semibold text-white">Delta</h3>
                            <p class="mt-1 text-sm text-slate-400">Delta shows directional bias. A short strangle works best when combined Delta stays balanced and does not drift hard in one direction.</p>
                            <div class="mt-4 h-[320px]"><canvas id="deltaChart"></canvas></div>
                        </div>

                        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                            <h3 class="text-lg font-semibold text-white">Implied Volatility</h3>
                            <p class="mt-1 text-sm text-slate-400">IV expansion can increase premium even if spot is quiet. IV contraction usually supports premium decay and improves short option positions.</p>
                            <div class="mt-4 h-[320px]"><canvas id="ivChart"></canvas></div>
                        </div>

                        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                            <h3 class="text-lg font-semibold text-white">Probability of Profit</h3>
                            <p class="mt-1 text-sm text-slate-400">POP gives a quick read of how favorable the structure is at each timestamp. Use it as context, not as a standalone trade trigger.</p>
                            <div class="mt-4 h-[320px]"><canvas id="popChart"></canvas></div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    @if($hasData)
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const mode = @json($viewMode);
            const leftLabel = 'Strike {{ $strikeLeft }}';
            const rightLabel = 'Strike {{ $strikeRight }}';
            const labels = @json($combinedPriceData['times'] ?? []);
            const entryPrice = {{ $enterPrice !== null && $enterPrice !== '' ? (float)$enterPrice : 'null' }};
            const left = @json($leftData);
            const right = @json($rightData);
            const combined = @json($combinedPriceData);

            const colors = {
                cyan: '#22d3ee',
                emerald: '#34d399',
                amber: '#f59e0b',
                rose: '#fb7185',
                violet: '#a78bfa',
                slate: '#94a3b8',
                grid: 'rgba(148,163,184,0.14)',
                text: '#e2e8f0',
            };

            function lineOptions() {
                return {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { labels: { color: colors.text, usePointStyle: true } },
                    },
                    scales: {
                        x: {
                            ticks: { color: colors.slate, maxTicksLimit: 10 },
                            grid: { color: colors.grid }
                        },
                        y: {
                            ticks: { color: colors.slate },
                            grid: { color: colors.grid }
                        }
                    }
                };
            }

            function metricDatasets(metric, colorA, colorB) {
                if (mode === 'combined') {
                    return [{
                        label: `Combined ${metric.toUpperCase()}`,
                        data: labels.map((_, i) => Number((left[metric]?.[i] || 0)) + Number((right[metric]?.[i] || 0))),
                        borderColor: colorA,
                        backgroundColor: colorA,
                        tension: 0.28,
                        pointRadius: 0,
                        borderWidth: 2
                    }];
                }

                return [
                    {
                        label: `${leftLabel} ${metric.toUpperCase()}`,
                        data: left[metric] || [],
                        borderColor: colorA,
                        backgroundColor: colorA,
                        tension: 0.28,
                        pointRadius: 0,
                        borderWidth: 2
                    },
                    {
                        label: `${rightLabel} ${metric.toUpperCase()}`,
                        data: right[metric] || [],
                        borderColor: colorB,
                        backgroundColor: colorB,
                        tension: 0.28,
                        pointRadius: 0,
                        borderWidth: 2
                    }
                ];
            }

            new Chart(document.getElementById('combinedPriceChart'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Combined LTP',
                            data: combined.combined || [],
                            borderColor: colors.cyan,
                            backgroundColor: colors.cyan,
                            tension: 0.28,
                            pointRadius: 0,
                            borderWidth: 2.5
                        },
                        ...(entryPrice !== null ? [{
                            label: 'Entry Price',
                            data: labels.map(() => entryPrice),
                            borderColor: colors.rose,
                            backgroundColor: colors.rose,
                            borderDash: [8, 6],
                            tension: 0,
                            pointRadius: 0,
                            borderWidth: 1.5
                        }] : [])
                    ]
                },
                options: lineOptions()
            });

            new Chart(document.getElementById('vegaChart'), {
                type: 'line',
                data: { labels, datasets: metricDatasets('vega', colors.violet, colors.emerald) },
                options: lineOptions()
            });

            new Chart(document.getElementById('thetaChart'), {
                type: 'line',
                data: { labels, datasets: metricDatasets('theta', colors.amber, colors.cyan) },
                options: lineOptions()
            });

            new Chart(document.getElementById('gammaChart'), {
                type: 'line',
                data: { labels, datasets: metricDatasets('gamma', colors.rose, colors.emerald) },
                options: lineOptions()
            });

            new Chart(document.getElementById('deltaChart'), {
                type: 'line',
                data: { labels, datasets: metricDatasets('delta', colors.cyan, colors.amber) },
                options: lineOptions()
            });

            new Chart(document.getElementById('ivChart'), {
                type: 'line',
                data: { labels, datasets: metricDatasets('iv', colors.violet, colors.rose) },
                options: lineOptions()
            });

            new Chart(document.getElementById('popChart'), {
                type: 'line',
                data: { labels, datasets: metricDatasets('pop', colors.emerald, colors.amber) },
                options: lineOptions()
            });
        </script>
    @endif
@endpush
