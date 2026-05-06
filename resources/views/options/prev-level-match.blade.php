@extends('layouts.app')

@section('content')
    <div class="min-h-screen bg-slate-950 text-slate-100">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">

            @if (session('error'))
                <div class="mb-4 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                    {{ session('error') }}
                </div>
            @endif

            <div class="mb-6 rounded-2xl border border-slate-800 bg-slate-900/80 p-5 shadow-2xl shadow-slate-950/30">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-400">NIFTY Options Monitor</p>
                        <h1 class="mt-2 text-2xl font-bold tracking-tight text-white">
                            Previous Day High / Low Match
                        </h1>
                        <p class="mt-2 text-sm text-slate-400">
                            Same strike CE / PE match checker using previous-day levels and selected live candle.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-wider text-slate-500">Base Strike</p>
                            <p class="mt-1 text-lg font-semibold text-white">
                                {{ $meta['base_strike'] ? number_format($meta['base_strike']) : '-' }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-wider text-slate-500">Index Open</p>
                            <p class="mt-1 text-lg font-semibold text-white">
                                {{ $meta['current_day_index_open'] ? number_format($meta['current_day_index_open'], 2) : '-' }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-wider text-slate-500">Prev Rows</p>
                            <p class="mt-1 text-lg font-semibold text-white">{{ $meta['prev_count'] ?? 0 }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-wider text-slate-500">Live Rows</p>
                            <p class="mt-1 text-lg font-semibold text-white">{{ $meta['live_count'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-3 text-sm text-slate-400">
                    <span>Expiry: <span class="font-semibold text-slate-200">{{ $meta['expiry_date'] ?? '-' }}</span></span>
                    <span>Current day: <span class="font-semibold text-slate-200">{{ $meta['current_date'] ?? '-' }}</span></span>
                    <span>Previous day: <span class="font-semibold text-slate-200">{{ $meta['previous_date'] ?? '-' }}</span></span>
                    <span>Time: <span class="font-semibold text-amber-300">{{ $meta['requested_time'] ?? 'LIVE' }}</span></span>
                    <span>Cutoff: <span class="font-semibold text-slate-200">{{ $meta['time_cutoff'] ?? '-' }}</span></span>
                    <span>Updated: <span id="updatedAt" class="font-semibold text-emerald-400">{{ $meta['generated_at'] ?? '-' }}</span></span>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/80 shadow-2xl shadow-slate-950/30">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-800 text-sm">
                        <thead class="bg-slate-950/90">
                        <tr class="text-left text-xs uppercase tracking-wider text-slate-400">
                            <th class="sticky left-0 z-20 bg-slate-950/95 px-4 py-3">Strike</th>
                            <th class="sticky left-[96px] z-20 bg-slate-950/95 px-4 py-3">Type</th>
                            <th class="px-4 py-3">P.H</th>
                            <th class="px-4 py-3">P.L</th>
                            <th class="px-4 py-3">BU</th>
                            <th class="px-4 py-3">C.H</th>
                            <th class="px-4 py-3">C.L</th>
                            <th class="px-4 py-3">Price</th>
                            <th class="px-4 py-3">Vol</th>
                            <th class="px-4 py-3">OI</th>
                            <th class="px-4 py-3">Live TS</th>
                        </tr>
                        </thead>

                        <tbody id="matchTableBody" class="divide-y divide-slate-800">
                        @forelse($rows as $row)
                            @foreach (['CE', 'PE'] as $side)
                                @php
                                    $item = $row[$side];
                                    $isCe = $side === 'CE';
                                @endphp

                                <tr class="hover:bg-slate-800/50">
                                    @if($loop->first)
                                        <td rowspan="2" class="sticky left-0 z-10 border-r border-slate-800 bg-slate-900/95 px-4 py-4 align-top text-base font-bold text-white">
                                            {{ number_format($row['strike']) }}
                                        </td>
                                    @endif

                                    <td class="sticky left-[96px] z-10 border-r border-slate-800 bg-slate-900/95 px-4 py-4">
                                        <span class="inline-flex min-w-14 items-center justify-center rounded-full px-3 py-1 text-xs font-bold tracking-wide {{ $isCe ? 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/30' : 'bg-rose-500/15 text-rose-300 ring-1 ring-rose-500/30' }}">
                                            {{ $side }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-4">
                                        <span class="@if(!empty($item['matches']['prev_high'])) rounded-lg bg-amber-400/20 px-2.5 py-1 font-semibold text-amber-300 ring-1 ring-amber-400/30 @else text-slate-200 @endif">
                                            {{ !empty($item['matches']['prev_high'])
                                                ? number_format($item['matches']['prev_high']['value'], 2)
                                                : ($item['prev_high'] !== null ? number_format($item['prev_high'], 2) : '-') }}
                                        </span>
                                        @if(!empty($item['matches']['prev_high']))
                                            <div class="mt-1 text-[11px] text-amber-300/80">
                                                {{ $item['matches']['prev_high']['label'] }}
                                            </div>
                                        @endif
                                    </td>

                                    <td class="px-4 py-4">
                                        <span class="@if(!empty($item['matches']['prev_low'])) rounded-lg bg-sky-400/20 px-2.5 py-1 font-semibold text-sky-300 ring-1 ring-sky-400/30 @else text-slate-200 @endif">
                                            {{ !empty($item['matches']['prev_low'])
                                                ? number_format($item['matches']['prev_low']['value'], 2)
                                                : ($item['prev_low'] !== null ? number_format($item['prev_low'], 2) : '-') }}
                                        </span>
                                        @if(!empty($item['matches']['prev_low']))
                                            <div class="mt-1 text-[11px] text-sky-300/80">
                                                {{ $item['matches']['prev_low']['label'] }}
                                            </div>
                                        @endif
                                    </td>

                                    <td class="px-4 py-4">
                                        <span class="inline-flex rounded-md bg-slate-800 px-2.5 py-1 font-semibold text-cyan-300">
                                            {{ $item['build_up'] ?? '--' }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-4">
                                        <span class="@if(!empty($item['matches']['curr_high'])) rounded-lg bg-fuchsia-400/20 px-2.5 py-1 font-semibold text-fuchsia-300 ring-1 ring-fuchsia-400/30 @else text-slate-200 @endif">
                                            {{ !empty($item['matches']['curr_high'])
                                                ? number_format($item['matches']['curr_high']['value'], 2)
                                                : ($item['curr_high'] !== null ? number_format($item['curr_high'], 2) : '-') }}
                                        </span>
                                        @if(!empty($item['matches']['curr_high']))
                                            <div class="mt-1 text-[11px] text-fuchsia-300/80">
                                                {{ $item['matches']['curr_high']['label'] }}
                                            </div>
                                        @endif
                                    </td>

                                    <td class="px-4 py-4">
                                        <span class="@if(!empty($item['matches']['curr_low'])) rounded-lg bg-teal-400/20 px-2.5 py-1 font-semibold text-teal-300 ring-1 ring-teal-400/30 @else text-slate-200 @endif">
                                            {{ !empty($item['matches']['curr_low'])
                                                ? number_format($item['matches']['curr_low']['value'], 2)
                                                : ($item['curr_low'] !== null ? number_format($item['curr_low'], 2) : '-') }}
                                        </span>
                                        @if(!empty($item['matches']['curr_low']))
                                            <div class="mt-1 text-[11px] text-teal-300/80">
                                                {{ $item['matches']['curr_low']['label'] }}
                                            </div>
                                        @endif
                                    </td>

                                    <td class="px-4 py-4 font-semibold text-white">
                                        {{ $item['price'] !== null ? number_format($item['price'], 2) : '-' }}
                                    </td>

                                    <td class="px-4 py-4 text-slate-300">
                                        {{ $item['volume'] !== null ? number_format($item['volume']) : '-' }}
                                    </td>

                                    <td class="px-4 py-4 text-slate-300">
                                        {{ $item['open_interest'] !== null ? number_format($item['open_interest']) : '-' }}
                                    </td>

                                    <td class="px-4 py-4 text-xs text-slate-400">
                                        {{ $item['ts_at'] ? \Carbon\Carbon::parse($item['ts_at'])->format('d M h:i:s A') : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-10 text-center text-sm text-slate-400">
                                    No data available for the selected date/time or market context.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const liveRoute = @json(route('trading.options.prev-level-match.live'));
        const currentParams = new URLSearchParams(window.location.search);
        const refreshUrl = new URL(liveRoute, window.location.origin);

        if (currentParams.get('time')) {
            refreshUrl.searchParams.set('time', currentParams.get('time'));
        }

        if (currentParams.get('limit')) {
            refreshUrl.searchParams.set('limit', currentParams.get('limit'));
        }


        let pollTimer = null;
        let refreshing = false;

        function fmt(value, decimals = 2) {
            if (value === null || value === undefined || value === '') return '-';
            return Number(value).toFixed(decimals);
        }

        function fmtInt(value) {
            if (value === null || value === undefined || value === '') return '-';
            return Number(value).toLocaleString();
        }

        function badgeClasses(side) {
            return side === 'CE'
                ? 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/30'
                : 'bg-rose-500/15 text-rose-300 ring-1 ring-rose-500/30';
        }

        function matchClass(type, match) {
            if (!match) return 'text-slate-200';

            const classes = {
                prev_high: 'rounded-lg bg-amber-400/20 px-2.5 py-1 font-semibold text-amber-300 ring-1 ring-amber-400/30',
                prev_low: 'rounded-lg bg-sky-400/20 px-2.5 py-1 font-semibold text-sky-300 ring-1 ring-sky-400/30',
                curr_high: 'rounded-lg bg-fuchsia-400/20 px-2.5 py-1 font-semibold text-fuchsia-300 ring-1 ring-fuchsia-400/30',
                curr_low: 'rounded-lg bg-teal-400/20 px-2.5 py-1 font-semibold text-teal-300 ring-1 ring-teal-400/30',
            };

            return classes[type] ?? 'text-slate-200';
        }

        function renderValueCell(item, key, rawKey) {
            const match = item.matches?.[key] ?? null;
            const raw = item[rawKey];

            return `
            <td class="px-4 py-4">
                <span class="${matchClass(key, match)}">
                    ${match ? fmt(match.value) : (raw !== null && raw !== undefined ? fmt(raw) : '-')}
                </span>
                ${match ? `<div class="mt-1 text-[11px] text-slate-300/80">${match.label}</div>` : ''}
            </td>
        `;
        }

        function renderRows(rows) {
            const tbody = document.getElementById('matchTableBody');

            if (!rows || !rows.length) {
                tbody.innerHTML = `
                <tr>
                    <td colspan="11" class="px-4 py-10 text-center text-sm text-slate-400">
                        No data available for the selected date/time or market context.
                    </td>
                </tr>
            `;
                return;
            }

            let html = '';

            rows.forEach((row) => {
                ['CE', 'PE'].forEach((side, index) => {
                    const item = row[side];

                    html += `
                    <tr class="hover:bg-slate-800/50">
                        ${index === 0 ? `
                            <td rowspan="2" class="sticky left-0 z-10 border-r border-slate-800 bg-slate-900/95 px-4 py-4 align-top text-base font-bold text-white">
                                ${fmtInt(row.strike)}
                            </td>
                        ` : ''}

                        <td class="sticky left-[96px] z-10 border-r border-slate-800 bg-slate-900/95 px-4 py-4">
                            <span class="inline-flex min-w-14 items-center justify-center rounded-full px-3 py-1 text-xs font-bold tracking-wide ${badgeClasses(side)}">
                                ${side}
                            </span>
                        </td>

                        ${renderValueCell(item, 'prev_high', 'prev_high')}
                        ${renderValueCell(item, 'prev_low', 'prev_low')}

                        <td class="px-4 py-4">
                            <span class="inline-flex rounded-md bg-slate-800 px-2.5 py-1 font-semibold text-cyan-300">
                                ${item.build_up ?? '--'}
                            </span>
                        </td>

                        ${renderValueCell(item, 'curr_high', 'curr_high')}
                        ${renderValueCell(item, 'curr_low', 'curr_low')}

                        <td class="px-4 py-4 font-semibold text-white">${item.price !== null && item.price !== undefined ? fmt(item.price) : '-'}</td>
                        <td class="px-4 py-4 text-slate-300">${item.volume !== null && item.volume !== undefined ? fmtInt(item.volume) : '-'}</td>
                        <td class="px-4 py-4 text-slate-300">${item.open_interest !== null && item.open_interest !== undefined ? fmtInt(item.open_interest) : '-'}</td>
                        <td class="px-4 py-4 text-xs text-slate-400">${item.ts_at ?? '-'}</td>
                    </tr>
                `;
                });
            });

            tbody.innerHTML = html;
        }

        async function refreshTable() {
            if (refreshing) return;
            refreshing = true;

            try {
                const response = await fetch(refreshUrl.toString(), {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    cache: 'no-store',
                });

                const data = await response.json();

                if (!response.ok || data.success === false) {
                    console.error(data.message || 'Refresh failed');
                    return;
                }

                renderRows(data.rows);
                document.getElementById('updatedAt').textContent = data.updated_at;
            } catch (error) {
                console.error('Live refresh failed:', error);
            } finally {
                refreshing = false;
                scheduleNextRefreshAtSecond9();
            }
        }

        function msUntilNextSecond9() {
            const now = new Date();
            const next = new Date(now);

            next.setMilliseconds(0);
            next.setSeconds(9);

            if (now.getSeconds() > 9 || (now.getSeconds() === 9 && now.getMilliseconds() > 0)) {
                next.setMinutes(next.getMinutes() + 1);
            }

            return next.getTime() - now.getTime();
        }

        function scheduleNextRefreshAtSecond9() {
            if (pollTimer) {
                clearTimeout(pollTimer);
            }

            const delay = msUntilNextSecond9();
            pollTimer = setTimeout(refreshTable, delay);
        }

        const isSnapshotMode = currentParams.has('time');

        if (!isSnapshotMode) {
            scheduleNextRefreshAtSecond9();
        }
    </script>
@endsection
