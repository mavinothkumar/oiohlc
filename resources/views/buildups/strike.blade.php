@extends('layouts.app')

@section('title','Build Up')

@section('content')
    <div class="max-w mx-auto py-6">
        <style>
            #snapshot-table thead th {
                position: sticky;
                top: 0;
                z-index: 20;
                background-color: #f3f4f6;
            }
        </style>
        {{-- Filter Bar --}}
        <form method="GET" action="{{ url('/buildups') }}" class="mb-6 border p-4 bg-white" lang="en-GB">
            <div class="grid grid-cols-1 md:grid-cols-7 gap-4 text-sm md:auto-rows-min">
                <div>
                    <label class="block mb-1">Symbol</label>
                    <select name="symbol" class="w-full border px-2 py-1">
                        @foreach ($allowed as $sym)
                            <option value="{{ $sym }}" {{ ($filters['symbol'] ?? 'NIFTY') === $sym ? 'selected' : '' }}>
                                {{ $sym }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block mb-1">Expiry (override)</label>
                    <input type="date" name="expiry" class="w-full border px-2 py-1"
                        value="{{ old('expiry', $filters['expiry'] ?? '') }}">
                    <p class="text-[11px] text-gray-500 mt-1">Leave blank to use current expiry.</p>
                </div>

                <div>
                    <label class="block mb-1">From (24-hour, IST)</label>
                    <input type="datetime-local"
                        name="from"
                        class="w-full border px-2 py-1"
                        lang="en-GB"
                        step="60"
                        value="{{ old('from', $filters['from'] ?? '') }}">
                </div>

                <div>
                    <label class="block mb-1">To (24-hour, IST)</label>
                    <input type="datetime-local"
                        name="to"
                        class="w-full border px-2 py-1"
                        lang="en-GB"
                        step="60"
                        value="{{ old('to', $filters['to'] ?? '') }}">
                </div>

                <div>
                    <label class="block mb-1">± Strike Range</label>
                    <input type="number" min="0" name="range" class="w-full border px-2 py-1"
                        placeholder="auto (NIFTY 200 / others 500)"
                        value="{{ old('range', $filters['range'] ?? '') }}">
                </div>

                <div>
                    <label class="block mb-1">Sort by (Total ΔOI)</label>
                    @php $sort = $filters['sort'] ?? 'Long Build'; @endphp
                    <select name="sort" class="w-full border px-2 py-1">
                        <option value="Long Build" {{ $sort==='Long Build'  ? 'selected':'' }}>Long Build</option>
                        <option value="Long Unwind" {{ $sort==='Long Unwind' ? 'selected':'' }}>Long Unwind</option>
                        <option value="Short Build" {{ $sort==='Short Build' ? 'selected':'' }}>Short Build</option>
                        <option value="Short Cover" {{ $sort==='Short Cover' ? 'selected':'' }}>Short Cover</option>
                    </select>
                    <p class="text-[11px] text-gray-500 mt-1">Rows sorted by max(CE, PE) of this column.</p>
                </div>

                <div class="flex md:col-span-1">
                    <div class="flex gap-3 w-full md:w-auto">
                        <button type="submit" class="px-3 py-2 text-white" style="background:#2271b1">Apply</button>
                        <a href="{{ url('/buildups') }}" class="inline-flex items-center justify-center border px-3">Reset</a>
                    </div>
                </div>
            </div>
        </form>

        {{-- Results --}}
        @if (!empty($result))
            <h2 class="text-lg font-semibold mb-2">
                {{ $result['meta']['symbol'] }}
                — Expiry {{ $result['meta']['expiry'] }}
                — Range ±{{ $result['meta']['range_used'] }}
                — {{ $result['meta']['from'] }} → {{ $result['meta']['to'] }}
                — Sorted by: {{ ucfirst(str_replace('_',' ',$result['meta']['sort'])) }} (Total ΔOI)
            </h2>

            <div class="rounded-lg border border-gray-200 shadow-sm">
                <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
                    <table class="min-w-full text-sm text-gray-700 border-separate border-spacing-0">
                        <thead>
                        <tr>
                            <th class="sticky top-0 z-20 bg-gray-100 px-4 py-3 border-b border-gray-200 text-center">Time</th>
                            <th class="sticky top-0 z-20 bg-gray-100 px-4 py-3 border-b border-gray-200 text-center">Strike</th>
                            <th class="sticky top-0 z-20 bg-gray-100 px-4 py-3 border-b border-gray-200 text-center">Type</th>
                            <th class="sticky top-0 z-20 bg-gray-100 px-4 py-3 border-b border-gray-200 text-center">Long Build</th>
                            <th class="sticky top-0 z-20 bg-gray-100 px-4 py-3 border-b border-gray-200 text-center">Short Build</th>
                            <th class="sticky top-0 z-20 bg-gray-100 px-4 py-3 border-b border-gray-200 text-center">Long Unwind</th>
                            <th class="sticky top-0 z-20 bg-gray-100 px-4 py-3 border-b border-gray-200 text-center">Short Cover</th>
                        </tr>
                        </thead>

                        <tbody id="buildups-body">
                        @php $pairIndex = 0; @endphp
                        @foreach ($result['strikes'] as $s)
                            @php
                                $rowBg   = $pairIndex % 2 ? 'bg-gray-100' : '';
                                $pairId  = 'p'.$pairIndex++;
                                $ce      = $s['CE'];      $pe      = $s['PE'];
                                $ce_lb_lu = $ce['Long Build']  + $ce['Long Unwind'];
                                $ce_sb_sc = $ce['Short Build'] + $ce['Short Cover'];
                                $pe_lb_lu = $pe['Long Build']  + $pe['Long Unwind'];
                                $pe_sb_sc = $pe['Short Build'] + $pe['Short Cover'];
                            @endphp

                            {{-- ───────── CE row ───────── --}}
                            <tr class="{{ $rowBg }}" data-pair="{{ $pairId }}">
                                <td class="px-2 py-1 border" rowspan="2"
                                    data-sort="strike" data-value="{{ $s['strike'] }}">{{ $s['strike'] }}</td>
                                <td class="px-2 py-1 border">CE</td>

                                {{-- LB-LU diff --}}
                                @php
                                    $v = $ce_lb_lu;
                                    $c = in_array($v, $result['top3_diff']['lb_lu'], true) && $v!=0
                                         ? 'bg-yellow-200 font-semibold' : '';
                                @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="lb_lu"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>

                                {{-- SB-SC diff --}}
                                @php
                                    $v = $ce_sb_sc;
                                    $c = in_array($v, $result['top3_diff']['sb_sc'], true) && $v!=0
                                         ? 'bg-yellow-200 font-semibold' : '';
                                @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="sb_sc"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>

                                {{-- LB TOTAL --}}
                                @php
                                    $v = $ce['Long Build'];
                                    $c = in_array($v, $result['top3_total']['Long Build'], true) && $v!=0
                                         ? 'bg-yellow-200 font-semibold' : '';
                                @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="lb_total"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>

                                {{-- LU TOTAL --}}
                                @php
                                    $v = $ce['Long Unwind'];
                                    $c = in_array($v, $result['top3_total']['Long Unwind'], true)
                                         ? 'bg-yellow-200 font-semibold' : '';
                                @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="lu_total"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>


                                {{-- SB TOTAL --}}
                                @php
                                    $v = $ce['Short Build'];
                                    $c = in_array($v, $result['top3_total']['Short Build'], true) && $v!=0
                                         ? 'bg-yellow-200 font-semibold' : '';
                                @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="sb_total"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>


                                {{-- SC TOTAL --}}
                                @php
                                    $v = $ce['Short Cover'];
                                    $c = in_array($v, $result['top3_total']['Short Cover'], true)
                                         ? 'bg-yellow-200 font-semibold' : '';
                                @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="sc_total"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>

                            </tr>

                            {{-- ───────── PE row ───────── --}}
                            <tr class="{{ $rowBg }}" data-pair="{{ $pairId }}" data-second="1">
                                <td class="px-2 py-1 border">PE</td>

                                {{-- identical structure as CE row but with $pe / $pe_* variables --}}
                                @php
                                    $v = $pe_lb_lu;
                                    $c = in_array($v, $result['top3_diff']['lb_lu'], true) && $v!=0
                                         ? 'bg-yellow-200 font-semibold' : '';
                                @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="lb_lu"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>

                                @php
                                    $v = $pe_sb_sc;
                                    $c = in_array($v, $result['top3_diff']['sb_sc'], true) && $v!=0
                                         ? 'bg-yellow-200 font-semibold' : '';
                                @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="sb_sc"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>


                                @php
                                    $v = $pe['Long Build'];
                                    $c = in_array($v, $result['top3_total']['Long Build'], true) && $v!=0
                                         ? 'bg-yellow-200 font-semibold' : '';
                                @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="lb_total"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>


                                @php
                                    $v = $pe['Long Unwind'];
                                    $c = in_array($v, $result['top3_total']['Long Unwind'], true)
                                         ? 'bg-yellow-200 font-semibold' : '';
                                @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="lu_total"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>


                                @php
                                    $v = $pe['Short Build'];
                                    $c = in_array($v, $result['top3_total']['Short Build'], true) && $v!=0
                                         ? 'bg-yellow-200 font-semibold' : '';
                                @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="sb_total"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>

                                @php
                                    $v = $pe['Short Cover'];
                                    $c = in_array($v, $result['top3_total']['Short Cover'], true)
                                         ? 'bg-yellow-200 font-semibold' : '';
                                @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="sc_total"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>

                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="border p-4 bg-white">No data for the selected inputs.</div>
        @endif
    </div>
    <script>
        ( function () {
            const tbody = document.getElementById('buildups-body');
            if ( ! tbody) return;

            // Map: key -> function(pairId) => numeric value to sort by (max of CE/PE for the key)
            function valueFor (pairId, key) {
                const rows = [...tbody.querySelectorAll(`tr[data-pair="${ pairId }"]`)];
                const vals = rows.map(r => {
                    const td = r.querySelector(`td[data-sort="${ key }"]`);
                    return td ? parseFloat(td.getAttribute('data-value') || '0') : 0;
                });
                if (key === 'strike') return vals[ 0 ] || 0; // strike lives on first row
                // default: use max(CE, PE) so a strike pair is ranked by its higher activity
                return Math.max(...vals);
            }

            // Current sort state
            let current = { key: null, dir: 'desc' };

            // Click handlers on headers
            document.querySelectorAll('th[data-key]').forEach(th => {
                th.addEventListener('click', () => {
                    const key = th.getAttribute('data-key');
                    // toggle direction if same key; else default desc
                    current.dir = ( current.key === key && current.dir === 'desc' ) ? 'asc' : 'desc';
                    current.key = key;

                    // collect pair ids in order they appear
                    const firstRows = [...tbody.querySelectorAll('tr[data-pair]:not([data-second])')];
                    const pairs = firstRows.map(fr => {
                        const pid = fr.getAttribute('data-pair');
                        return { pid, val: valueFor(pid, key) };
                    });

                    // sort them
                    pairs.sort((a, b) => {
                        if (a.val === b.val) {
                            // tie-break: higher strike first
                            const sa = valueFor(a.pid, 'strike');
                            const sb = valueFor(b.pid, 'strike');
                            return current.dir === 'desc' ? ( sb - sa ) : ( sa - sb );
                        }
                        return current.dir === 'desc' ? ( b.val - a.val ) : ( a.val - b.val );
                    });

                    // re-append as grouped pairs
                    const frag = document.createDocumentFragment();
                    pairs.forEach(p => {
                        const rows = [...tbody.querySelectorAll(`tr[data-pair="${ p.pid }"]`)];
                        rows.forEach(r => frag.appendChild(r));
                    });
                    tbody.appendChild(frag);

                    // simple visual cue
                    document.querySelectorAll('th[data-key]').forEach(h => h.classList.remove('underline'));
                    th.classList.add('underline');
                });
            });
        } )();
    </script>
@endsection
