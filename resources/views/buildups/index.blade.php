@extends('layouts.app')

@section('title','Build Up')

@section('content')
    <div class="max-w mx-auto py-6">

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

        {{-- ─── Results ─── --}}
        {{-- ─── Results ─── --}}
        @if(!empty($result))

            <h2 class="text-lg font-semibold mb-3">
                {{ $result['meta']['symbol'] }} — Expiry {{ $result['meta']['expiry'] }}
                — Range ±{{ $result['meta']['range_used'] }}
                — {{ $result['meta']['from'] }} → {{ $result['meta']['to'] }}
                — Sorted by: {{ ucfirst(str_replace('_',' ',$result['meta']['sort'])) }} (Total ΔOI)
            </h2>

            {{-- Tab Buttons --}}
            <div class="flex gap-0 mb-0 border-b border-gray-300">
                <button id="tab-btn-chart"
                    onclick="switchTab('chart')"
                    class="px-5 py-2 text-sm font-medium border-b-2 border-blue-600 text-blue-600 -mb-px bg-white focus:outline-none">
                    📊 Chart
                </button>
                <button id="tab-btn-table"
                    onclick="switchTab('table')"
                    class="px-5 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 -mb-px bg-white hover:text-gray-700 focus:outline-none">
                    📋 Table
                </button>
            </div>

            {{-- ─── CHART TAB ─── --}}
            <div id="tab-panel-chart" class="py-4">

                {{-- Pass PHP data to JS --}}
                @php
                    $chartData = [];
                    foreach($result['strikes'] as $s) {
                        $chartData[] = [
                            'strike' => $s['strike'],
                            'ce_lb'  => $s['CE']['Long Build'],
                            'ce_sb'  => $s['CE']['Short Build'],
                            'ce_lu'  => $s['CE']['Long Unwind'],
                            'ce_sc'  => $s['CE']['Short Cover'],
                            'pe_lb'  => $s['PE']['Long Build'],
                            'pe_sb'  => $s['PE']['Short Build'],
                            'pe_lu'  => $s['PE']['Long Unwind'],
                            'pe_sc'  => $s['PE']['Short Cover'],
                        ];
                    }
                    usort($chartData, fn($a,$b) => $a['strike'] <=> $b['strike']);
                @endphp
                <script>window.__buildupChartData = @json($chartData);</script>

                {{-- CE / PE / Combined toggle — default: both --}}
                <div class="flex gap-4 mb-5 items-center">
                    <span class="text-sm font-medium text-gray-600">View:</span>
                    <label class="flex items-center gap-1 text-sm cursor-pointer">
                        <input type="radio" name="chartView" value="ce" onchange="switchChartView('ce')"> CE
                    </label>
                    <label class="flex items-center gap-1 text-sm cursor-pointer">
                        <input type="radio" name="chartView" value="pe" onchange="switchChartView('pe')"> PE
                    </label>
                    <label class="flex items-center gap-1 text-sm cursor-pointer">
                        <input type="radio" name="chartView" value="both" checked onchange="switchChartView('both')"> CE + PE Combined
                    </label>
                </div>

                {{-- ─── Combined Overview Chart ─── --}}
                <div class="bg-white border rounded p-4 mb-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">📈 Build-Up Overview — All Strikes (LB / SB / LU / SC)</h3>
                    <div style="position:relative; height:1500px;">
                        <canvas id="chart-overview"></canvas>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white border rounded p-4">
                        <h3 class="text-sm font-semibold text-green-700 mb-2">🟢 Long Build (LB) — Bullish Accumulation</h3>
                        <div style="position:relative; height:400px;">
                            <canvas id="chart-lb"></canvas>
                        </div>
                    </div>
                    <div class="bg-white border rounded p-4">
                        <h3 class="text-sm font-semibold text-red-700 mb-2">🔴 Short Build (SB) — Bearish Accumulation</h3>
                        <div style="position:relative; height:400px;">
                            <canvas id="chart-sb"></canvas>
                        </div>
                    </div>
                    <div class="bg-white border rounded p-4">
                        <h3 class="text-sm font-semibold text-orange-600 mb-2">🟠 Long Unwind (LU) — Bullish Exit</h3>
                        <div style="position:relative; height:400px;">
                            <canvas id="chart-lu"></canvas>
                        </div>
                    </div>
                    <div class="bg-white border rounded p-4">
                        <h3 class="text-sm font-semibold text-blue-600 mb-2">🔵 Short Cover (SC) — Bearish Exit</h3>
                        <div style="position:relative; height:400px;">
                            <canvas id="chart-sc"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ─── TABLE TAB ─── --}}
            <div id="tab-panel-table" class="py-4" style="display:none;">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs border">
                        <thead class="bg-gray-200">
                        <tr>
                            <th class="px-2 py-1 border text-left cursor-pointer" data-key="strike">Strike</th>
                            <th class="px-2 py-1 border text-left">Type</th>
                            <th class="px-2 py-1 border text-left cursor-pointer" data-key="lblu">LB−LU</th>
                            <th class="px-2 py-1 border text-left cursor-pointer" data-key="lbtotal">LB Total ΔOI</th>
                            <th class="px-2 py-1 border text-left cursor-pointer" data-key="lb5">LB 5m</th>
                            <th class="px-2 py-1 border text-left cursor-pointer" data-key="lb15">LB 15m</th>
                            <th class="px-2 py-1 border text-left cursor-pointer" data-key="lutotal">LU Total ΔOI</th>
                            <th class="px-2 py-1 border text-left cursor-pointer" data-key="lu5">LU 5m</th>
                            <th class="px-2 py-1 border text-left cursor-pointer" data-key="lu15">LU 15m</th>
                            <th class="px-2 py-1 border text-left cursor-pointer" data-key="sbsc">SB−SC</th>
                            <th class="px-2 py-1 border text-left cursor-pointer" data-key="sbtotal">SB Total ΔOI</th>
                            <th class="px-2 py-1 border text-left cursor-pointer" data-key="sb5">SB 5m</th>
                            <th class="px-2 py-1 border text-left cursor-pointer" data-key="sb15">SB 15m</th>
                            <th class="px-2 py-1 border text-left cursor-pointer" data-key="sctotal">SC Total ΔOI</th>
                            <th class="px-2 py-1 border text-left cursor-pointer" data-key="sc5">SC 5m</th>
                            <th class="px-2 py-1 border text-left cursor-pointer" data-key="sc15">SC 15m</th>
                        </tr>
                        </thead>
                        <tbody id="buildups-body">
                        @php $pairIndex = 0; @endphp
                        @foreach($result['strikes'] as $s)
                            @php
                                $rowBg  = $pairIndex % 2 ? 'bg-gray-100' : '';
                                $pairId = $pairIndex;
                                $ce     = $s['CE'];
                                $pe     = $s['PE'];
                                $celblu = $ce['Long Build'] + $ce['Long Unwind'];
                                $cesbsc = $ce['Short Build'] + $ce['Short Cover'];
                                $pelblu = $pe['Long Build'] + $pe['Long Unwind'];
                                $pesbsc = $pe['Short Build'] + $pe['Short Cover'];
                            @endphp

                            {{-- CE row --}}
                            <tr class="{{ $rowBg }}" data-pair="{{ $pairId }}">
                                <td class="px-2 py-1 border" rowspan="2" data-sort="strike" data-value="{{ $s['strike'] }}">{{ $s['strike'] }}</td>
                                <td class="px-2 py-1 border">CE</td>
                                @php $v = $celblu; $c = in_array($v,$result['top3_diff']['lb_lu'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="lblu" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $ce['Long Build']; $c = in_array($v,$result['top3_total']['Long Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="lbtotal" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['CE_5']['Long Build'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_5']['Long Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' }}" data-sort="lb5" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['CE_15']['Long Build'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_15']['Long Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' }}" data-sort="lb15" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $ce['Long Unwind']; $c = in_array($v,$result['top3_total']['Long Unwind'],true)?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="lutotal" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['CE_5']['Long Unwind'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_5']['Long Unwind'],true)?'bg-yellow-200 font-semibold':'' }}" data-sort="lu5" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['CE_15']['Long Unwind'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_15']['Long Unwind'],true)?'bg-yellow-200 font-semibold':'' }}" data-sort="lu15" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $cesbsc; $c = in_array($v,$result['top3_diff']['sb_sc'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="sbsc" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $ce['Short Build']; $c = in_array($v,$result['top3_total']['Short Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="sbtotal" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['CE_5']['Short Build'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_5']['Short Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' }}" data-sort="sb5" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['CE_15']['Short Build'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_15']['Short Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' }}" data-sort="sb15" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $ce['Short Cover']; $c = in_array($v,$result['top3_total']['Short Cover'],true)?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="sctotal" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['CE_5']['Short Cover'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_5']['Short Cover'],true)?'bg-yellow-200 font-semibold':'' }}" data-sort="sc5" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['CE_15']['Short Cover'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_15']['Short Cover'],true)?'bg-yellow-200 font-semibold':'' }}" data-sort="sc15" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                            </tr>

                            {{-- PE row --}}
                            <tr class="{{ $rowBg }}" data-pair="{{ $pairId }}" data-second="1">
                                <td class="px-2 py-1 border">PE</td>
                                @php $v = $pelblu; $c = in_array($v,$result['top3_diff']['lb_lu'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="lblu" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $pe['Long Build']; $c = in_array($v,$result['top3_total']['Long Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="lbtotal" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['PE_5']['Long Build'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_5']['Long Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' }}" data-sort="lb5" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['PE_15']['Long Build'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_15']['Long Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' }}" data-sort="lb15" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $pe['Long Unwind']; $c = in_array($v,$result['top3_total']['Long Unwind'],true)?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="lutotal" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['PE_5']['Long Unwind'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_5']['Long Unwind'],true)?'bg-yellow-200 font-semibold':'' }}" data-sort="lu5" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['PE_15']['Long Unwind'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_15']['Long Unwind'],true)?'bg-yellow-200 font-semibold':'' }}" data-sort="lu15" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $pesbsc; $c = in_array($v,$result['top3_diff']['sb_sc'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="sbsc" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $pe['Short Build']; $c = in_array($v,$result['top3_total']['Short Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="sbtotal" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['PE_5']['Short Build'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_5']['Short Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' }}" data-sort="sb5" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['PE_15']['Short Build'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_15']['Short Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' }}" data-sort="sb15" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $pe['Short Cover']; $c = in_array($v,$result['top3_total']['Short Cover'],true)?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="sctotal" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['PE_5']['Short Cover'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_5']['Short Cover'],true)?'bg-yellow-200 font-semibold':'' }}" data-sort="sc5" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['PE_15']['Short Cover'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_15']['Short Cover'],true)?'bg-yellow-200 font-semibold':'' }}" data-sort="sc15" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                            </tr>
                            @php $pairIndex++; @endphp
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        @else
            <div class="border p-4 bg-white">No data for the selected inputs.</div>
        @endif


    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        (function() {
            const tbody = document.getElementById('buildups-body');
            if (!tbody) return;

            // Map: key -> function(pairId) => numeric value to sort by (max of CE/PE for the key)
            function valueFor(pairId, key) {
                const rows = [...tbody.querySelectorAll(`tr[data-pair="${pairId}"]`)];
                const vals = rows.map(r => {
                    const td = r.querySelector(`td[data-sort="${key}"]`);
                    return td ? parseFloat(td.getAttribute('data-value') || '0') : 0;
                });
                if (key === 'strike') return vals[0] || 0; // strike lives on first row
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
                    current.dir = (current.key === key && current.dir === 'desc') ? 'asc' : 'desc';
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
                            return current.dir === 'desc' ? (sb - sa) : (sa - sb);
                        }
                        return current.dir === 'desc' ? (b.val - a.val) : (a.val - b.val);
                    });

                    // re-append as grouped pairs
                    const frag = document.createDocumentFragment();
                    pairs.forEach(p => {
                        const rows = [...tbody.querySelectorAll(`tr[data-pair="${p.pid}"]`)];
                        rows.forEach(r => frag.appendChild(r));
                    });
                    tbody.appendChild(frag);

                    // simple visual cue
                    document.querySelectorAll('th[data-key]').forEach(h => h.classList.remove('underline'));
                    th.classList.add('underline');
                });
            });
        })();

        /* ── Tab Switcher ── */
        function switchTab(name) {
            ['chart', 'table'].forEach(t => {
                document.getElementById('tab-panel-' + t).style.display = (t === name) ? '' : 'none';
                const btn = document.getElementById('tab-btn-' + t);
                if (t === name) {
                    btn.classList.add('border-blue-600', 'text-blue-600');
                    btn.classList.remove('border-transparent', 'text-gray-500');
                } else {
                    btn.classList.remove('border-blue-600', 'text-blue-600');
                    btn.classList.add('border-transparent', 'text-gray-500');
                }
            });
            if (name === 'chart') initBuildupCharts();
        }

        /* ── Table Sort ── */
        (function () {
            const tbody = document.getElementById('buildups-body');
            if (!tbody) return;

            function valueFor(pairId, key) {
                const rows = [...tbody.querySelectorAll(`tr[data-pair="${pairId}"]`)];
                const vals = rows.map(r => {
                    const td = r.querySelector(`td[data-sort="${key}"]`);
                    return td ? parseFloat(td.getAttribute('data-value')) || 0 : 0;
                });
                if (key === 'strike') return vals[0] || 0;
                return Math.max(...vals);
            }

            let current = { key: null, dir: 'desc' };

            document.querySelectorAll('th[data-key]').forEach(th => {
                th.addEventListener('click', () => {
                    const key = th.getAttribute('data-key');
                    current.dir = (key === current.key && current.dir === 'desc') ? 'asc' : 'desc';
                    current.key = key;
                    const firstRows = [...tbody.querySelectorAll('tr[data-pair]:not([data-second])')];
                    const pairs = firstRows.map(fr => ({ pid: fr.getAttribute('data-pair'), val: valueFor(fr.getAttribute('data-pair'), key) }));
                    pairs.sort((a, b) => {
                        if (a.val === b.val) {
                            const sa = valueFor(a.pid, 'strike'), sb = valueFor(b.pid, 'strike');
                            return current.dir === 'desc' ? sb - sa : sa - sb;
                        }
                        return current.dir === 'desc' ? b.val - a.val : a.val - b.val;
                    });
                    const frag = document.createDocumentFragment();
                    pairs.forEach(p => tbody.querySelectorAll(`tr[data-pair="${p.pid}"]`).forEach(r => frag.appendChild(r)));
                    tbody.appendChild(frag);
                    document.querySelectorAll('th[data-key]').forEach(h => h.classList.remove('underline'));
                    th.classList.add('underline');
                });
            });
        })();

        /* ── Charts ── */
        let _buildupCharts = {};
        let _currentChartView = 'both'; // default CE + PE Combined

        function switchChartView(view) {
            _currentChartView = view;
            initBuildupCharts();
        }

        function initBuildupCharts() {
            const data = window.__buildupChartData || [];
            if (!data.length) return;

            const labels = data.map(d => d.strike);
            const configs = {
                lb: { canvasId:'chart-lb', ceKey:'ce_lb', peKey:'pe_lb', ceColor:'rgba(34,197,94,0.75)',  peColor:'rgba(134,239,172,0.75)' },
                sb: { canvasId:'chart-sb', ceKey:'ce_sb', peKey:'pe_sb', ceColor:'rgba(239,68,68,0.75)',   peColor:'rgba(252,165,165,0.75)' },
                lu: { canvasId:'chart-lu', ceKey:'ce_lu', peKey:'pe_lu', ceColor:'rgba(249,115,22,0.75)',  peColor:'rgba(253,186,116,0.75)' },
                sc: { canvasId:'chart-sc', ceKey:'ce_sc', peKey:'pe_sc', ceColor:'rgba(59,130,246,0.75)',  peColor:'rgba(147,197,253,0.75)' },
            };

            /* ── Overview chart: CE & PE bars per strike, all 4 build types ── */
            (function () {
                const canvas = document.getElementById('chart-overview');
                if (!canvas) return;
                if (_buildupCharts['overview']) { _buildupCharts['overview'].destroy(); }

                const view = _currentChartView;

                // Build flat labels: ["24500 CE", "24500 PE", "24550 CE", "24550 PE", ...]
                const flatLabels = [];
                const vals = { lb: [], sb: [], lu: [], sc: [] };

                data.forEach(d => {
                    if (view === 'ce' || view === 'both') {
                        flatLabels.push(d.strike + ' CE');
                        vals.lb.push(d.ce_lb);
                        vals.sb.push(d.ce_sb);
                        vals.lu.push(Math.abs(d.ce_lu));  // flip to positive side
                        vals.sc.push(Math.abs(d.ce_sc));  // flip to positive side
                    }
                    if (view === 'pe' || view === 'both') {
                        flatLabels.push(d.strike + ' PE');
                        vals.lb.push(d.pe_lb);
                        vals.sb.push(d.pe_sb);
                        vals.lu.push(Math.abs(d.pe_lu));  // flip to positive side
                        vals.sc.push(Math.abs(d.pe_sc));  // flip to positive side
                    }
                });

                const datasets = [
                    {
                        label           : 'Long Build',
                        data            : vals.lb,
                        backgroundColor : 'rgba(34,197,94,0.85)',
                        borderColor     : 'rgba(22,163,74,1)',
                        borderWidth     : 1,
                        borderSkipped   : false,
                    },
                    {
                        label           : 'Short Build',
                        data            : vals.sb,
                        backgroundColor : 'rgba(239,68,68,0.85)',
                        borderColor     : 'rgba(220,38,38,1)',
                        borderWidth     : 1,
                        borderSkipped   : false,
                    },
                    {
                        label           : 'Long Unwind',
                        data            : vals.lu,
                        backgroundColor : 'rgba(249,115,22,0.85)',
                        borderColor     : 'rgba(234,88,12,1)',
                        borderWidth     : 1,
                        borderSkipped   : false,
                    },
                    {
                        label           : 'Short Cover',
                        data            : vals.sc,
                        backgroundColor : 'rgba(59,130,246,0.85)',
                        borderColor     : 'rgba(37,99,235,1)',
                        borderWidth     : 1,
                        borderSkipped   : false,
                    },
                ];

                // Dynamically size height: ~28px per label row
                const rowHeight  = 40;
                const chartH     = Math.max(1400, flatLabels.length * rowHeight * datasets.length * 0.38);
                canvas.parentElement.style.height = chartH + 'px';

                _buildupCharts['overview'] = new Chart(canvas, {
                    type : 'bar',
                    data : { labels: flatLabels, datasets },
                    options : {
                        animation           : false,
                        indexAxis           : 'y',
                        responsive          : true,
                        maintainAspectRatio : false,
                        plugins : {
                            legend : {
                                position : 'top',
                                labels   : { font: { size: 12 }, padding: 16 }
                            },
                            tooltip : {
                                mode      : 'index',
                                intersect : false,
                                callbacks : {
                                    label: ctx => {
                                        const v = ctx.raw, abs = Math.abs(v);
                                        if (abs === 0) return null;
                                        const fmt = abs >= 1e7 ? (v/1e7).toFixed(2)+'Cr'
                                            : abs >= 1e5 ? (v/1e5).toFixed(2)+'L'
                                                : v.toLocaleString('en-IN');
                                        return ` ${ctx.dataset.label}: ${fmt}`;
                                    },
                                    filter: item => item.raw !== 0
                                }
                            }
                        },
                        scales : {
                            x : {
                                grid  : { color: 'rgba(0,0,0,0.06)' },
                                ticks : { font: { size: 10 } },
                                title : { display: true, text: 'ΔOI', font: { size: 11 } }
                            },
                            y : {
                                grid  : { display: false },
                                ticks : {
                                    font      : { size: 10 },
                                    // Bold the strike+type label for CE rows to visually separate pairs
                                    callback  : function(val, idx) {
                                        return flatLabels[idx];
                                    }
                                },
                            }
                        }
                    }
                });
            })();


            Object.entries(configs).forEach(([key, cfg]) => {
                const canvas = document.getElementById(cfg.canvasId);
                if (!canvas) return;
                if (_buildupCharts[key]) { _buildupCharts[key].destroy(); }

                const datasets = [];
                if (_currentChartView === 'ce' || _currentChartView === 'both') {
                    datasets.push({ label:'CE', data: data.map(d => d[cfg.ceKey]), backgroundColor: cfg.ceColor, borderWidth: 1 });
                }
                if (_currentChartView === 'pe' || _currentChartView === 'both') {
                    datasets.push({ label:'PE', data: data.map(d => d[cfg.peKey]), backgroundColor: cfg.peColor, borderWidth: 1 });
                }

                _buildupCharts[key] = new Chart(canvas, {
                    type: 'bar',
                    data: { labels, datasets },
                    options: {
                        animation: false,
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position:'top', labels:{ font:{ size:11 } } },
                            tooltip: {
                                callbacks: {
                                    label: ctx => {
                                        const v = ctx.raw, abs = Math.abs(v);
                                        const fmt = abs >= 1e7 ? (v/1e7).toFixed(2)+'Cr' : abs >= 1e5 ? (v/1e5).toFixed(2)+'L' : v.toLocaleString('en-IN');
                                        return ` ${ctx.dataset.label}: ${fmt}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { grid:{ color:'rgba(0,0,0,0.05)' }, ticks:{ font:{ size:10 } } },
                            y: { grid:{ display:false }, ticks:{ font:{ size:10 } } }
                        }
                    }
                });
            });
        }

        /* ── Init on page load: show chart tab by default ── */
        document.addEventListener('DOMContentLoaded', function () {
            switchTab('chart');
        });
    </script>
@endsection
