{{-- resources/views/options/prev-level-match.blade.php --}}
@extends('layouts.app')

@section('content')
    <div class="min-h-screen bg-slate-100 text-slate-900">
        <div class="mx-auto max-w-[1600px] px-4 py-6 sm:px-6 lg:px-8">

            <div class="mb-4 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-600">Trading</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Previous Day Level Match</h1>
                    <div class="mt-3 flex flex-wrap gap-4 text-sm text-slate-600">
                        <span><span class="font-semibold text-slate-900">Expiry:</span> {{ $meta['expiry_date'] ?? '-' }}</span>
                        <span><span class="font-semibold text-slate-900">Current:</span> {{ $meta['current_date'] ?? '-' }}</span>
                        <span><span class="font-semibold text-slate-900">Previous:</span> {{ $meta['previous_date'] ?? '-' }}</span>
                        <span><span class="font-semibold text-slate-900">ATM:</span> {{ $meta['atm_strike'] ?? '-' }}</span>
                    </div>
                </div>

                <form method="GET" action="{{ route('trading.prev-level-match') }}" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label for="limit" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Strike Range</label>
                        <select id="limit" name="limit" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-sky-500 focus:outline-none">
                            @foreach([3, 5, 7, 10, 15, 20, 25, 30] as $rangeLimit)
                                <option value="{{ $rangeLimit }}" @selected(($meta['limit'] ?? 10) == $rangeLimit)>
                                    ±{{ $rangeLimit }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                        Apply
                    </button>

                    <div class="rounded-xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200">
                        <p class="text-[11px] uppercase tracking-wider text-slate-500">Updated</p>
                        <p id="updatedAt" class="mt-1 text-sm font-semibold text-emerald-600">{{ $meta['updated_at'] ?? '-' }}</p>
                    </div>
                </form>
            </div>

            <div id="pageMessage" class="{{ empty($message) ? 'hidden' : '' }} mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ $message }}
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50">
                        <tr class="text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <th class="sticky left-0 z-20 bg-slate-50 px-4 py-3">Strike</th>
                            <th class="sticky left-[90px] z-20 bg-slate-50 px-4 py-3">Type</th>
                            <th class="px-4 py-3">P.H</th>
                            <th class="px-4 py-3">P.L</th>
                            <th class="px-4 py-3">BU</th>
                            <th class="px-4 py-3">C.H</th>
                            <th class="px-4 py-3">C.L</th>
                            <th class="px-4 py-3">Price</th>
                            <th class="px-4 py-3">Live Time</th>
                            <th class="px-4 py-3 min-w-[300px]">Message</th>
                        </tr>
                        </thead>
                        <tbody id="levelMatchBody" class="divide-y divide-slate-200">
                        @forelse($rows as $row)
                            @foreach(['CE', 'PE'] as $side)
                                @php
                                    $item = $row[$side];
                                    $rowStripe = $row['stripe'] === 'odd' ? 'bg-white' : 'bg-slate-50/70';
                                @endphp
                                <tr class="{{ $rowStripe }} hover:bg-sky-50/60">
                                    @if($loop->first)
                                        <td rowspan="2" class="sticky left-0 z-10 border-r border-slate-200 px-4 py-4 align-top text-base font-bold text-slate-900 {{ $rowStripe }}">
                                            <span class="inline-flex rounded-lg px-3 py-1 {{ $row['stripe'] === 'odd' ? 'bg-slate-100' : 'bg-blue-100' }}">
                                                {{ $row['strike'] }}
                                            </span>
                                        </td>
                                    @endif

                                    <td class="sticky left-[90px] z-10 border-r border-slate-200 px-4 py-4 {{ $rowStripe }}">
                                        <span class="inline-flex min-w-[52px] items-center justify-center rounded-full px-2.5 py-1 text-xs font-bold {{ $side === 'CE' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                            {{ $side }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-4">{!! levelCell($item, 'prev_high', $item['prev_high']) !!}</td>
                                    <td class="px-4 py-4">{!! levelCell($item, 'prev_low', $item['prev_low']) !!}</td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">
                                            {{ $item['build_up'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">{!! levelCell($item, 'curr_high', $item['curr_high']) !!}</td>
                                    <td class="px-4 py-4">{!! levelCell($item, 'curr_low', $item['curr_low']) !!}</td>
                                    <td class="px-4 py-4 font-semibold text-slate-900">
                                        {{ $item['price'] !== null ? number_format($item['price'], 2) : '-' }}
                                    </td>
                                    <td class="px-4 py-4 text-xs text-slate-500">
                                        {{ $item['ts_at'] ?? '-' }}
                                    </td>
                                    <td class="px-4 py-4">
                                        @if($item['has_notification'])
                                            <div class="rounded-xl border border-orange-200 bg-orange-50 px-3 py-2 text-sm font-medium text-orange-800">
                                                {{ $item['notification'] }}
                                            </div>
                                        @else
                                            <span class="text-slate-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No rows available.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @php
        function levelCell($item, $key, $fallback) {
            $match = $item['matches'][$key] ?? null;

            $classes = [
                'prev_high' => 'bg-amber-50 text-amber-700 ring-amber-200',
                'prev_low'  => 'bg-cyan-50 text-cyan-700 ring-cyan-200',
                'curr_high' => 'bg-fuchsia-50 text-fuchsia-700 ring-fuchsia-200',
                'curr_low'  => 'bg-teal-50 text-teal-700 ring-teal-200',
            ];

            if ($match) {
                return '<div class="inline-flex flex-col rounded-lg px-2.5 py-1.5 ring-1 '.$classes[$key].'">
                            <span class="font-semibold">'.number_format($match['value'], 2).'</span>
                            <span class="text-[10px] uppercase tracking-wide">'.$match['label'].'</span>
                        </div>';
            }

            return '<span class="text-slate-700">'.($fallback !== null ? number_format($fallback, 2) : '-').'</span>';
        }
    @endphp

    <script>
        const liveUrl = @json(route('trading.prev-level-match.live', ['limit' => $meta['limit'] ?? 10]));
        let refreshTimer = null;
        let isRefreshing = false;

        function setPageMessage(message) {
            const box = document.getElementById('pageMessage');
            if (!box) return;

            if (message) {
                box.textContent = message;
                box.classList.remove('hidden');
            } else {
                box.textContent = '';
                box.classList.add('hidden');
            }
        }

        function num(v) {
            return v === null || v === undefined || v === '' ? '-' : Number(v).toFixed(2);
        }

        function sideBadge(side) {
            return side === 'CE'
                ? 'bg-emerald-100 text-emerald-700'
                : 'bg-rose-100 text-rose-700';
        }

        function matchClass(type) {
            return {
                prev_high: 'bg-amber-50 text-amber-700 ring-amber-200',
                prev_low:  'bg-cyan-50 text-cyan-700 ring-cyan-200',
                curr_high: 'bg-fuchsia-50 text-fuchsia-700 ring-fuchsia-200',
                curr_low:  'bg-teal-50 text-teal-700 ring-teal-200',
            }[type];
        }

        function renderLevelCell(item, key, fallback) {
            if (item.matches?.[key]) {
                return `
            <div class="inline-flex flex-col rounded-lg px-2.5 py-1.5 ring-1 ${matchClass(key)}">
                <span class="font-semibold">${num(item.matches[key].value)}</span>
                <span class="text-[10px] uppercase tracking-wide">${item.matches[key].label}</span>
            </div>
        `;
            }

            return `<span class="text-slate-700">${num(fallback)}</span>`;
        }

        function renderRows(rows) {
            const tbody = document.getElementById('levelMatchBody');
            if (!tbody) return;

            let html = '';

            rows.forEach((row) => {
                ['CE', 'PE'].forEach((side, index) => {
                    const item = row[side];
                    const rowStripe = row.stripe === 'odd' ? 'bg-white' : 'bg-slate-50/70';
                    const strikeBadge = row.stripe === 'odd' ? 'bg-slate-100' : 'bg-blue-100';

                    html += `
                <tr class="${rowStripe} hover:bg-sky-50/60">
                    ${index === 0 ? `
                        <td rowspan="2" class="sticky left-0 z-10 border-r border-slate-200 px-4 py-4 align-top text-base font-bold text-slate-900 ${rowStripe}">
                            <span class="inline-flex rounded-lg px-3 py-1 ${strikeBadge}">
                                ${row.strike}
                            </span>
                        </td>
                    ` : ''}

                    <td class="sticky left-[90px] z-10 border-r border-slate-200 px-4 py-4 ${rowStripe}">
                        <span class="inline-flex min-w-[52px] items-center justify-center rounded-full px-2.5 py-1 text-xs font-bold ${sideBadge(side)}">
                            ${side}
                        </span>
                    </td>

                    <td class="px-4 py-4">${renderLevelCell(item, 'prev_high', item.prev_high)}</td>
                    <td class="px-4 py-4">${renderLevelCell(item, 'prev_low', item.prev_low)}</td>
                    <td class="px-4 py-4">
                        <span class="inline-flex rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">
                            ${item.build_up ?? '--'}
                        </span>
                    </td>
                    <td class="px-4 py-4">${renderLevelCell(item, 'curr_high', item.curr_high)}</td>
                    <td class="px-4 py-4">${renderLevelCell(item, 'curr_low', item.curr_low)}</td>
                    <td class="px-4 py-4 font-semibold text-slate-900">${num(item.price)}</td>
                    <td class="px-4 py-4 text-xs text-slate-500">${item.ts_at ?? '-'}</td>
                    <td class="px-4 py-4">
                        ${item.has_notification
                        ? `<div class="rounded-xl border border-orange-200 bg-orange-50 px-3 py-2 text-sm font-medium text-orange-800">${item.notification}</div>`
                        : `<span class="text-slate-400">-</span>`
                    }
                    </td>
                </tr>
            `;
                });
            });

            tbody.innerHTML = html;
        }

        async function refreshLiveData() {
            if (isRefreshing) return;

            isRefreshing = true;

            try {
                const response = await fetch(liveUrl, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    }
                });

                if (!response.ok) {
                    setPageMessage('Live refresh failed.');
                    return;
                }

                const data = await response.json();

                setPageMessage(data.message ?? null);

                const updatedAt = document.getElementById('updatedAt');
                if (updatedAt && data.updated_at) {
                    updatedAt.textContent = data.updated_at;
                }

                if (Array.isArray(data.rows)) {
                    renderRows(data.rows);
                }
            } catch (error) {
                setPageMessage('Unable to refresh live data right now.');
            } finally {
                isRefreshing = false;
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            refreshLiveData();              // start immediately
            refreshTimer = setInterval(refreshLiveData, 10000); // every 09 seconds
        });
    </script>
@endsection
