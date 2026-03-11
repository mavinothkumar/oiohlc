@extends('layouts.app')

@section('title','Build Up')

@section('content')
    <div class="max-w mx-auto">

        {{-- Collapsible Filter Bar --}}
        <div class="mb-6">
            <button
                onclick="toggleFilters()"
                id="filter-toggle-btn"
                class="flex items-center gap-2 text-sm font-medium text-white px-4 py-2 rounded"
                style="background:#2271b1">
                <span id="filter-toggle-icon">▼</span>
                <span id="filter-toggle-label">Show Filters</span>
            </button>

            <div id="filter-panel" class="hidden mt-2 border p-4 bg-white rounded">
                <form method="GET" action="{{ url('buildups') }}" lang="en-GB">
                    <div class="grid grid-cols-1 md:grid-cols-7 gap-4 text-sm md:auto-rows-min">
                        <div>
                            <label class="block mb-1">Symbol</label>
                            <select name="symbol" class="w-full border px-2 py-1">
                                @foreach($allowed as $sym)
                                    <option value="{{ $sym }}" {{ ($filters['symbol'] ?? 'NIFTY') === $sym ? 'selected' : '' }}>
                                        {{ $sym }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block mb-1">Expiry (override)</label>
                            <input type="date" name="expiry" class="w-full border px-2 py-1" value="{{ old('expiry', $filters['expiry'] ?? '') }}">
                            <p class="text-[11px] text-gray-500 mt-1">Leave blank to use current expiry.</p>
                        </div>

                        <div>
                            <label class="block mb-1">From (24-hour, IST)</label>
                            <input type="datetime-local" name="from" class="w-full border px-2 py-1" lang="en-GB" step="60" value="{{ old('from', $filters['from'] ?? '') }}">
                        </div>

                        <div>
                            <label class="block mb-1">To (24-hour, IST)</label>
                            <input type="datetime-local" name="to" class="w-full border px-2 py-1" lang="en-GB" step="60" value="{{ old('to', $filters['to'] ?? '') }}">
                        </div>

                        <div>
                            <label class="block mb-1">± Strike Range</label>
                            <input type="number" min="0" name="range" class="w-full border px-2 py-1" placeholder="auto (NIFTY 200 / others 500)" value="{{ old('range', $filters['range'] ?? '') }}">
                        </div>

                        <div>
                            <label class="block mb-1">Sort by (Total ΔOI)</label>
                            @php $sort = $filters['sort'] ?? 'Long Build'; @endphp
                            <select name="sort" class="w-full border px-2 py-1">
                                <option value="Long Build"   {{ $sort === 'Long Build'   ? 'selected' : '' }}>Long Build</option>
                                <option value="Long Unwind"  {{ $sort === 'Long Unwind'  ? 'selected' : '' }}>Long Unwind</option>
                                <option value="Short Build"  {{ $sort === 'Short Build'  ? 'selected' : '' }}>Short Build</option>
                                <option value="Short Cover"  {{ $sort === 'Short Cover'  ? 'selected' : '' }}>Short Cover</option>
                            </select>
                            <p class="text-[11px] text-gray-500 mt-1">Rows sorted by max(CE, PE) of this column.</p>
                        </div>

                        <div class="flex md:col-span-1">
                            <div class="flex gap-3 w-full md:w-auto">
                                <button type="submit" class="px-3 py-2 text-white" style="background:#2271b1">Apply</button>
                                <a href="{{ url('buildups') }}" class="inline-flex items-center justify-center border px-3">Reset</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>


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
                            'celb'  => $s['CE']['Long Build'],
                            'cesb'  => $s['CE']['Short Build'],
                            'celu'  => $s['CE']['Long Unwind'],
                            'cesc'  => $s['CE']['Short Cover'],
                            'pelb'  => $s['PE']['Long Build'],
                            'pesb'  => $s['PE']['Short Build'],
                            'pelu'  => $s['PE']['Long Unwind'],
                            'pesc'  => $s['PE']['Short Cover'],
                        ];
                    }
                    usort($chartData, fn($a,$b) => $a['strike'] <=> $b['strike']);
                @endphp
                <script>window.buildupChartData = @json($chartData);</script>

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

                {{-- Overview 3 History Table --}}
                <div class="bg-white border rounded p-4 mb-6" id="net-pressure-history-section">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-700">
                            Net Pressure History — [LB−LU] vs [SB−SC] per 3-min Bucket
                        </h3>
                        <div class="flex gap-2 items-center">
                            <select id="npm-bucket" class="border text-xs px-2 py-1 rounded">
                                <option value="3" selected>3 min</option>
                                <option value="5">5 min</option>
                                <option value="15">15 min</option>
                            </select>
                            <select id="npm-type" class="border text-xs px-2 py-1 rounded">
                                <option value="both">CE + PE</option>
                                <option value="ce">CE only</option>
                                <option value="pe">PE only</option>
                            </select>
                            <button onclick="loadNetPressureHistory()"
                                class="px-3 py-1 text-xs text-white rounded"
                                style="background:#2271b1">Load</button>
                        </div>
                    </div>
                    <div id="npm-loading" class="text-xs text-gray-400 hidden">Loading...</div>
                    <div id="npm-table-wrap" class="overflow-x-auto overflow-y-auto" style="height:300px;"></div>
                </div>


                {{-- Overview 2: Combined totals --}}
                <div class="bg-white border rounded p-4 mb-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">
                        Overview 2 — Combined Long (LB + LU) vs Short (SB + SC)
                    </h3>
                    <div style="position:relative; height:400px;">
                        <canvas id="chart-overview-2"></canvas>
                    </div>
                </div>

                {{-- Overview 3: Net remaining pressure --}}
                <div class="bg-white border rounded p-4 mb-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">
                        Overview 3 — Net Remaining Pressure [LB - LU] vs [SB - SC]
                    </h3>
                    <div style="position:relative; height:400px;">
                        <canvas id="chart-overview-3"></canvas>
                    </div>
                </div>



                {{-- ─── Combined Overview Chart ─── --}}
                <div class="bg-white border rounded p-4 mb-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">📈 Build-Up Overview — All Strikes (LB / SB / LU / SC)</h3>
                    <div style="position:relative; height:400px;">
                        <canvas id="chart-overview"></canvas>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 hidden">
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
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_5']['Long Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' }}" data-sort="lb5"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['CE_15']['Long Build'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_15']['Long Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' }}" data-sort="lb15"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $ce['Long Unwind']; $c = in_array($v,$result['top3_total']['Long Unwind'],true)?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="lutotal" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['CE_5']['Long Unwind'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_5']['Long Unwind'],true)?'bg-yellow-200 font-semibold':'' }}" data-sort="lu5"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['CE_15']['Long Unwind'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_15']['Long Unwind'],true)?'bg-yellow-200 font-semibold':'' }}" data-sort="lu15"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $cesbsc; $c = in_array($v,$result['top3_diff']['sb_sc'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="sbsc" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $ce['Short Build']; $c = in_array($v,$result['top3_total']['Short Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="sbtotal" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['CE_5']['Short Build'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_5']['Short Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' }}" data-sort="sb5"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['CE_15']['Short Build'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_15']['Short Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' }}" data-sort="sb15"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $ce['Short Cover']; $c = in_array($v,$result['top3_total']['Short Cover'],true)?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="sctotal" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['CE_5']['Short Cover'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_5']['Short Cover'],true)?'bg-yellow-200 font-semibold':'' }}" data-sort="sc5"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['CE_15']['Short Cover'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_15']['Short Cover'],true)?'bg-yellow-200 font-semibold':'' }}" data-sort="sc15"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                            </tr>

                            {{-- PE row --}}
                            <tr class="{{ $rowBg }}" data-pair="{{ $pairId }}" data-second="1">
                                <td class="px-2 py-1 border">PE</td>
                                @php $v = $pelblu; $c = in_array($v,$result['top3_diff']['lb_lu'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="lblu" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $pe['Long Build']; $c = in_array($v,$result['top3_total']['Long Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="lbtotal" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['PE_5']['Long Build'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_5']['Long Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' }}" data-sort="lb5"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['PE_15']['Long Build'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_15']['Long Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' }}" data-sort="lb15"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $pe['Long Unwind']; $c = in_array($v,$result['top3_total']['Long Unwind'],true)?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="lutotal" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['PE_5']['Long Unwind'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_5']['Long Unwind'],true)?'bg-yellow-200 font-semibold':'' }}" data-sort="lu5"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['PE_15']['Long Unwind'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_15']['Long Unwind'],true)?'bg-yellow-200 font-semibold':'' }}" data-sort="lu15"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $pesbsc; $c = in_array($v,$result['top3_diff']['sb_sc'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="sbsc" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $pe['Short Build']; $c = in_array($v,$result['top3_total']['Short Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="sbtotal" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['PE_5']['Short Build'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_5']['Short Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' }}" data-sort="sb5"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['PE_15']['Short Build'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_15']['Short Build'],true)&&$v!=0?'bg-yellow-200 font-semibold':'' }}" data-sort="sb15"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $pe['Short Cover']; $c = in_array($v,$result['top3_total']['Short Cover'],true)?'bg-yellow-200 font-semibold':'' @endphp
                                <td class="px-2 py-1 border {{ $c }}" data-sort="sctotal" data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['PE_5']['Short Cover'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_5']['Short Cover'],true)?'bg-yellow-200 font-semibold':'' }}" data-sort="sc5"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
                                @php $v = $s['PE_15']['Short Cover'] @endphp
                                <td class="px-2 py-1 border {{ in_array($v,$result['top3_15']['Short Cover'],true)?'bg-yellow-200 font-semibold':'' }}" data-sort="sc15"
                                    data-value="{{ $v }}">{{ format_inr_compact($v) }}</td>
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
        // ── Tab Switcher ──────────────────────────────────────────────────────────
        function switchTab(name) {
            ['chart', 'table'].forEach(t => {
                const panel = document.getElementById('tab-panel-' + t);
                const btn   = document.getElementById('tab-btn-' + t);
                if (panel) panel.style.display = (t === name) ? '' : 'none';
                if (btn) {
                    if (t === name) {
                        btn.classList.add('border-blue-600', 'text-blue-600');
                        btn.classList.remove('border-transparent', 'text-gray-500');
                    } else {
                        btn.classList.remove('border-blue-600', 'text-blue-600');
                        btn.classList.add('border-transparent', 'text-gray-500');
                    }
                }
            });
            if (name === 'chart') initBuildupCharts();
        }

        // ── Table Sort ────────────────────────────────────────────────────────────
        function initBuildupTableSort() {
            const tbody = document.getElementById('buildups-body');
            if (!tbody) return;

            function valueFor(pairId, key) {
                const rows = [...tbody.querySelectorAll(`tr[data-pair="${pairId}"]`)];
                const vals = rows.map(r => {
                    const td = r.querySelector(`td[data-sort="${key}"]`);
                    return td ? parseFloat(td.getAttribute('data-value') || 0) : 0;
                });
                if (key === 'strike') return vals[0] || 0;
                return Math.max(...vals);
            }

            let current = { key: null, dir: 'desc' };

            document.querySelectorAll('th[data-key]').forEach(th => {
                th.addEventListener('click', () => {
                    const key = th.getAttribute('data-key');
                    current.dir = (current.key === key && current.dir === 'desc') ? 'asc' : 'desc';
                    current.key = key;

                    const firstRows = [...tbody.querySelectorAll('tr[data-pair]:not([data-second])')];
                    const pairs = firstRows.map(fr => ({
                        pid: fr.getAttribute('data-pair'),
                        val: valueFor(fr.getAttribute('data-pair'), key)
                    }));

                    pairs.sort((a, b) => {
                        if (a.val === b.val) {
                            const sa = valueFor(a.pid, 'strike');
                            const sb = valueFor(b.pid, 'strike');
                            return current.dir === 'desc' ? sb - sa : sa - sb;
                        }
                        return current.dir === 'desc' ? b.val - a.val : a.val - b.val;
                    });

                    const frag = document.createDocumentFragment();
                    pairs.forEach(p => {
                        tbody.querySelectorAll(`tr[data-pair="${p.pid}"]`)
                            .forEach(r => frag.appendChild(r));
                    });
                    tbody.appendChild(frag);

                    document.querySelectorAll('th[data-key]').forEach(h => h.classList.remove('underline'));
                    th.classList.add('underline');
                });
            });
        }

        // ── Chart state ───────────────────────────────────────────────────────────
        let buildupCharts    = {};
        let currentChartView = 'both';

        function switchChartView(view) {
            currentChartView = view;
            initBuildupCharts();
        }

        // ── Helpers ───────────────────────────────────────────────────────────────
        function fmtOI(v) {
            const abs = Math.abs(Number(v) || 0);
            if (abs >= 1e7) return (abs / 1e7).toFixed(2) + 'Cr';
            if (abs >= 1e5) return (abs / 1e5).toFixed(2) + 'L';
            return abs.toLocaleString('en-IN');
        }

        function destroyIfExists(key) {
            if (buildupCharts[key]) { buildupCharts[key].destroy(); buildupCharts[key] = null; }
        }

        // ── Main chart renderer ───────────────────────────────────────────────────
        function initBuildupCharts() {
            const data = window.buildupChartData || [];
            if (!Array.isArray(data) || !data.length || typeof Chart === 'undefined') return;

            const view         = currentChartView;
            const strikeLabels = data.map(d => d.strike);

            // helper: build flat CE/PE label list + parallel value arrays
            function buildFlat(ceFn, peFn) {
                const labels = [], ceVals = [], peVals = [];
                data.forEach(d => {
                    if (view === 'ce' || view === 'both') { labels.push(`${d.strike} CE`); ceVals.push(ceFn(d)); }
                    if (view === 'pe' || view === 'both') { labels.push(`${d.strike} PE`); peVals.push(peFn(d)); }
                });
                // merge into single value array in label order
                const vals = [];
                let ci = 0, pi = 0;
                data.forEach(d => {
                    if (view === 'ce' || view === 'both') vals.push(ceVals[ci++]);
                    if (view === 'pe' || view === 'both') vals.push(peVals[pi++]);
                });
                return { labels, vals };
            }

            function baseOpts(extraY = {}) {
                return {
                    animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { font: { size: 12 }, padding: 16 } },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: ctx => {
                                    const v = ctx.raw || 0;
                                    if (v === 0) return null;
                                    const sign = (v > 0) ? '+' : '';
                                    return `${ctx.dataset.label}: ${sign}${fmtOI(v)}`;
                                }
                            },
                            filter: item => item.raw !== 0
                        }
                    },
                    scales: {
                        x: { grid: { color: 'rgba(0,0,0,0.06)' }, ticks: { font: { size: 10 } } },
                        y: {
                            grid: { color: 'rgba(0,0,0,0.06)' },
                            ticks: { font: { size: 10 }, callback: v => fmtOI(v) },
                            ...extraY
                        }
                    }
                };
            }

            function autoHeight(canvas, labelCount, datasetsCount) {
                const h = Math.max(400, labelCount * 15 * datasetsCount * 0.45);
                canvas.parentElement.style.height = h + 'px';
            }

            // ── Overview 1: LB / SB / LU / SC ────────────────────────────────────
            (function () {
                destroyIfExists('overview');
                const canvas = document.getElementById('chart-overview');
                if (!canvas) return;

                const flatLabels = [];
                const lb = [], sb = [], lu = [], sc = [];
                data.forEach(d => {
                    if (view === 'ce' || view === 'both') {
                        flatLabels.push(`${d.strike} CE`);
                        lb.push(Number(d.celb) || 0);
                        sb.push(Number(d.cesb) || 0);
                        lu.push(Math.abs(Number(d.celu) || 0));
                        sc.push(Math.abs(Number(d.cesc) || 0));
                    }
                    if (view === 'pe' || view === 'both') {
                        flatLabels.push(`${d.strike} PE`);
                        lb.push(Number(d.pelb) || 0);
                        sb.push(Number(d.pesb) || 0);
                        lu.push(Math.abs(Number(d.pelu) || 0));
                        sc.push(Math.abs(Number(d.pesc) || 0));
                    }
                });

                autoHeight(canvas, flatLabels.length, 4);

                buildupCharts.overview = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: flatLabels,
                        datasets: [
                            { label: 'Long Build',  data: lb, backgroundColor: 'rgba(34,197,94,0.85)',  borderColor: 'rgba(22,163,74,1)',  borderWidth: 1 },
                            { label: 'Short Build', data: sb, backgroundColor: 'rgba(239,68,68,0.85)',  borderColor: 'rgba(220,38,38,1)', borderWidth: 1 },
                            { label: 'Long Unwind', data: lu, backgroundColor: 'rgba(249,115,22,0.85)', borderColor: 'rgba(234,88,12,1)', borderWidth: 1 },
                            { label: 'Short Cover', data: sc, backgroundColor: 'rgba(59,130,246,0.85)', borderColor: 'rgba(37,99,235,1)', borderWidth: 1 },
                        ]
                    },
                    options: baseOpts({ beginAtZero: true })
                });
            })();

            // ── Overview 2: Combined Long (LB+LU) vs Short (SB+SC) ───────────────
            // ── Overview 2: Stacked Long (LB+LU) vs Short (SB+SC) ─────────────────
            (function () {
                destroyIfExists('overview2');
                const canvas = document.getElementById('chart-overview-2');
                if (!canvas) return;

                const flatLabels = [], lb = [], lu = [], sb = [], sc = [];

                data.forEach(d => {
                    if (view === 'ce' || view === 'both') {
                        flatLabels.push(`${d.strike} CE`);
                        lb.push(Number(d.celb) || 0);
                        lu.push(Math.abs(Number(d.celu) || 0));
                        sb.push(Number(d.cesb) || 0);
                        sc.push(Math.abs(Number(d.cesc) || 0));
                    }
                    if (view === 'pe' || view === 'both') {
                        flatLabels.push(`${d.strike} PE`);
                        lb.push(Number(d.pelb) || 0);
                        lu.push(Math.abs(Number(d.pelu) || 0));
                        sb.push(Number(d.pesb) || 0);
                        sc.push(Math.abs(Number(d.pesc) || 0));
                    }
                });

                autoHeight(canvas, flatLabels.length, 2);

                buildupCharts.overview2 = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: flatLabels,
                        datasets: [
                            {
                                label: 'Long Build (LB)',
                                data: lb,
                                backgroundColor: 'rgba(22,163,74,0.9)',
                                borderColor: 'rgba(21,128,61,1)',
                                borderWidth: 1,
                                stack: 'long',
                            },
                            {
                                label: 'Long Unwind (LU)',
                                data: lu,
                                backgroundColor: 'rgba(234,179,8,0.85)',
                                borderColor: 'rgba(161,98,7,1)',
                                borderWidth: 1,
                                stack: 'long',
                            },
                            {
                                label: 'Short Build (SB)',
                                data: sb,
                                backgroundColor: 'rgba(239,68,68,0.9)',
                                borderColor: 'rgba(220,38,38,1)',
                                borderWidth: 1,
                                stack: 'short',
                            },
                            {
                                label: 'Short Cover (SC)',
                                data: sc,
                                backgroundColor: 'rgba(30,64,175,0.85)',
                                borderColor: 'rgba(30,27,75,1)',
                                borderWidth: 1,
                                stack: 'short',
                            },
                        ]
                    },
                    options: {
                        animation: false,
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: { font: { size: 12 }, padding: 16 }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    label: function(ctx) {
                                        const v = ctx.raw || 0;
                                        if (v === 0) return null;
                                        return `${ctx.dataset.label}: ${fmtOI(v)}`;
                                    },
                                    afterBody: function(ctxArr) {
                                        const lbVal = ctxArr.find(c => c.dataset.label.includes('Long Build'))?.raw || 0;
                                        const luVal = ctxArr.find(c => c.dataset.label.includes('Long Unwind'))?.raw || 0;
                                        const sbVal = ctxArr.find(c => c.dataset.label.includes('Short Build'))?.raw || 0;
                                        const scVal = ctxArr.find(c => c.dataset.label.includes('Short Cover'))?.raw || 0;
                                        return [
                                            `─────────────────`,
                                            `Total Long : ${fmtOI(lbVal + luVal)}`,
                                            `Total Short: ${fmtOI(sbVal + scVal)}`,
                                        ];
                                    }
                                },
                                filter: item => item.raw !== 0
                            }
                        },
                        scales: {
                            x: {
                                stacked: true,
                                grid: { color: 'rgba(0,0,0,0.06)' },
                                ticks: { font: { size: 10 } }
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                grid: { color: 'rgba(0,0,0,0.06)' },
                                ticks: {
                                    font: { size: 10 },
                                    callback: function(value) { return fmtOI(value); }
                                }
                            }
                        }
                    }
                });
            })();


            // ── Overview 3: Net pressure (LB-LU) vs (SB-SC) ──────────────────────
            (function () {
                destroyIfExists('overview3');
                const canvas = document.getElementById('chart-overview-3');
                if (!canvas) return;

                const flatLabels = [], longNet = [], shortNet = [];
                data.forEach(d => {
                    if (view === 'ce' || view === 'both') {
                        flatLabels.push(`${d.strike} CE`);
                        longNet.push((Number(d.celb) || 0) - Math.abs(Number(d.celu) || 0));
                        shortNet.push((Number(d.cesb) || 0) - Math.abs(Number(d.cesc) || 0));
                    }
                    if (view === 'pe' || view === 'both') {
                        flatLabels.push(`${d.strike} PE`);
                        longNet.push((Number(d.pelb) || 0) - Math.abs(Number(d.pelu) || 0));
                        shortNet.push((Number(d.pesb) || 0) - Math.abs(Number(d.pesc) || 0));
                    }
                });

                autoHeight(canvas, flatLabels.length, 2);

                buildupCharts.overview3 = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: flatLabels,
                        datasets: [
                            { label: 'Net Long Pressure (LB − LU)',  data: longNet,  backgroundColor: 'rgba(22,163,74,0.85)',  borderColor: 'rgba(21,128,61,1)',  borderWidth: 1 },
                            { label: 'Net Short Pressure (SB − SC)', data: shortNet, backgroundColor: 'rgba(220,38,38,0.85)',  borderColor: 'rgba(185,28,28,1)', borderWidth: 1 },
                        ]
                    },
                    options: baseOpts()
                });
            })();

            // ── Individual LB / SB / LU / SC charts (horizontal) ─────────────────
            const configs = {
                lb: { canvasId: 'chart-lb', ceKey: 'celb', peKey: 'pelb', ceColor: 'rgba(34,197,94,0.75)',   peColor: 'rgba(134,239,172,0.75)' },
                sb: { canvasId: 'chart-sb', ceKey: 'cesb', peKey: 'pesb', ceColor: 'rgba(239,68,68,0.75)',   peColor: 'rgba(252,165,165,0.75)' },
                lu: { canvasId: 'chart-lu', ceKey: 'celu', peKey: 'pelu', ceColor: 'rgba(249,115,22,0.75)',  peColor: 'rgba(253,186,116,0.75)' },
                sc: { canvasId: 'chart-sc', ceKey: 'cesc', peKey: 'pesc', ceColor: 'rgba(59,130,246,0.75)',  peColor: 'rgba(147,197,253,0.75)' },
            };

            Object.entries(configs).forEach(([key, cfg]) => {
                destroyIfExists(key);
                const canvas = document.getElementById(cfg.canvasId);
                if (!canvas) return;

                const datasets = [];
                if (view === 'ce' || view === 'both') {
                    datasets.push({ label: 'CE', data: data.map(d => Number(d[cfg.ceKey]) || 0), backgroundColor: cfg.ceColor, borderWidth: 1 });
                }
                if (view === 'pe' || view === 'both') {
                    datasets.push({ label: 'PE', data: data.map(d => Number(d[cfg.peKey]) || 0), backgroundColor: cfg.peColor, borderWidth: 1 });
                }

                buildupCharts[key] = new Chart(canvas, {
                    type: 'bar',
                    data: { labels: strikeLabels, datasets },
                    options: {
                        animation: false,
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top', labels: { font: { size: 11 } } },
                            tooltip: {
                                callbacks: {
                                    label: ctx => `${ctx.dataset.label}: ${fmtOI(ctx.raw)}`
                                }
                            }
                        },
                        scales: {
                            x: { grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 10 }, callback: v => fmtOI(v) } },
                            y: { grid: { display: false }, ticks: { font: { size: 10 } } }
                        }
                    }
                });
            });
        }

        function loadNetPressureHistory() {
            const wrap    = document.getElementById('npm-table-wrap');
            const loading = document.getElementById('npm-loading');
            const bucket  = document.getElementById('npm-bucket').value;
            const typeFilter = document.getElementById('npm-type').value;

            // Reuse filters from the current page URL
            const params  = new URLSearchParams(window.location.search);
            params.set('bucket', bucket);

            wrap.innerHTML = '';
            loading.classList.remove('hidden');

            fetch(`/buildups/net-pressure-history?${params.toString()}`)
                .then(r => r.json())
                .then(data => {
                    loading.classList.add('hidden');
                    if (data.error) { wrap.innerHTML = `<p class="text-red-500">${data.error}</p>`; return; }

                    const { times, strikes, rows } = data;

                    // Build column headers: one col per strike per type (CE/PE), two sub-cols (net long / net short)
                    let html = '<table class="min-w-full border text-xs whitespace-nowrap">';
                    html += '<thead class="bg-gray-100"><tr>';
                    html += '<th class="border px-2 py-1 sticky left-0 bg-gray-100 z-10">Time</th>';

                    strikes.forEach(k => {
                        const types = typeFilter === 'both' ? ['CE', 'PE']
                            : typeFilter === 'ce'   ? ['CE']
                                : ['PE'];
                        types.forEach(tp => {
                            html += `<th class="border px-2 py-1 text-center" colspan="2">${k} ${tp}</th>`;
                        });
                    });
                    html += '</tr>';

                    // Sub-header: LB-LU / SB-SC
                    html += '<tr>';
                    html += '<th class="border px-2 py-1 sticky left-0 bg-gray-100 z-10"></th>';
                    strikes.forEach(k => {
                        const types = typeFilter === 'both' ? ['CE', 'PE']
                            : typeFilter === 'ce'   ? ['CE'] : ['PE'];
                        types.forEach(() => {
                            html += `<th class="border px-2 py-1 text-green-700">LB−LU</th>`;
                            html += `<th class="border px-2 py-1 text-red-700">SB−SC</th>`;
                        });
                    });
                    html += '</tr></thead><tbody>';

                    // Rows
                    rows.forEach(row => {
                        html += '<tr class="hover:bg-yellow-50">';
                        html += `<td class="border px-2 py-1 font-mono sticky left-0 bg-white z-10">${row.time}</td>`;

                        strikes.forEach(k => {
                            const types = typeFilter === 'both' ? ['CE', 'PE']
                                : typeFilter === 'ce'   ? ['CE'] : ['PE'];
                            types.forEach(tp => {
                                const nl = row[`${k}_${tp}_net_long`]  || 0;
                                const ns = row[`${k}_${tp}_net_short`] || 0;

                                const nlFmt  = fmtOI(Math.abs(nl));
                                const nsFmt  = fmtOI(Math.abs(ns));
                                const nlCls  = nl > 0 ? 'text-green-700 font-semibold' : nl < 0 ? 'text-red-500' : 'text-gray-400';
                                const nsCls  = ns > 0 ? 'text-red-700 font-semibold'   : ns < 0 ? 'text-green-600' : 'text-gray-400';

                                html += `<td class="border px-2 py-1 text-right ${nlCls}">${nl !== 0 ? (nl > 0 ? '+' : '−') + nlFmt : '—'}</td>`;
                                html += `<td class="border px-2 py-1 text-right ${nsCls}">${ns !== 0 ? (ns > 0 ? '+' : '−') + nsFmt : '—'}</td>`;
                            });
                        });
                        html += '</tr>';
                    });

                    html += '</tbody></table>';
                    wrap.innerHTML = html;
                })
                .catch(() => {
                    loading.classList.add('hidden');
                    wrap.innerHTML = '<p class="text-red-500 text-xs">Failed to load history.</p>';
                });
        }


        // ── Boot ──────────────────────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function () {
            initBuildupTableSort();
            switchTab('chart');
            loadNetPressureHistory();
        });

        function toggleFilters() {
            const panel  = document.getElementById('filter-panel');
            const icon   = document.getElementById('filter-toggle-icon');
            const label  = document.getElementById('filter-toggle-label');
            const isOpen = !panel.classList.contains('hidden');

            if (isOpen) {
                panel.classList.add('hidden');
                icon.textContent  = '▼';
                label.textContent = 'Show Filters';
            } else {
                panel.classList.remove('hidden');
                icon.textContent  = '▲';
                label.textContent = 'Hide Filters';
            }
        }

    </script>

@endsection
