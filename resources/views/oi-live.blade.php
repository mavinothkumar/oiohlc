@extends('layouts.app')

@section('title', 'NIFTY OI & Volume Difference')

@section('content')
    <div class="px-4 py-3 space-y-3">

        {{-- Title Bar --}}
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h1 class="text-base font-bold tracking-wide" id="page-title">
                    NIFTY — OI &amp; Volume Diff
                    <span class="text-gray-400 font-normal text-xs">(3-minute candles)</span>
                </h1>
                <p class="text-gray-500 text-[11px] mt-0.5">
                    Track open-interest and volume changes across strike prices.
                    Top-3 positive <span class="text-green-400">●</span> and negative <span class="text-red-400">●</span> diffs are highlighted.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-[11px]">
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span> Top-3 OI +</span>
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span> Top-3 OI −</span>
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-400 inline-block"></span> Top-3 Vol +</span>
                <span id="last-updated" class="text-gray-400 ml-2"></span>
            </div>
        </div>

        {{-- Controls --}}
        <div class="flex flex-wrap items-end gap-4 bg-gray-900 border border-gray-700 rounded-xl px-4 py-3">

            <div class="flex flex-col gap-1">
                <label class="text-gray-400 text-[11px] font-semibold uppercase tracking-wider">Symbol</label>
                <select id="symbolSelect" class="bg-gray-800 border border-gray-600 text-white rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="NIFTY">NIFTY</option>
                    <option value="BANKNIFTY">BANKNIFTY</option>
                    <option value="FINNIFTY">FINNIFTY</option>
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-gray-400 text-[11px] font-semibold uppercase tracking-wider">Date</label>
                <input type="date" id="dateInput"
                    class="bg-gray-800 border border-gray-600 text-white rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-blue-500 outline-none"
                    value="{{ date('Y-m-d') }}"/>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-gray-400 text-[11px] font-semibold uppercase tracking-wider">Expiry</label>
                <select id="expirySelect" class="bg-gray-800 border border-gray-600 text-white rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">-- Auto --</option>
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-gray-400 text-[11px] font-semibold uppercase tracking-wider">Strikes <span id="strikes-count" class="text-blue-400">(6)</span></label>
                <select id="strikesSelect" class="bg-gray-800 border border-gray-600 text-white rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="2" selected>2 each side (5 total)</option>
                    <option value="3">3 each side (7 total)</option>
                    <option value="4">4 each side (9 total)</option>
                    <option value="5">5 each side (11 total)</option>
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-gray-400 text-11px font-semibold uppercase tracking-wider">Timeframe</label>
                <select id="tfSelect"
                    class="bg-gray-800 border border-gray-600 text-white rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="3" selected>3 min</option>
                    <option value="6">6 min</option>
                    <option value="15">15 min</option>
                    <option value="30">30 min</option>
                </select>
            </div>


            <button id="loadBtn"
                class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-semibold rounded-lg px-5 py-1.5 text-xs transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582M20 20v-5h-.581M5.635 19A9 9 0 1 0 4.582 9"/>
                </svg>
                Load Table
            </button>

            <button id="resetBtn" class="text-gray-400 hover:text-white text-xs underline ml-auto transition-colors">Reset</button>
        </div>

        {{-- Error --}}
        <div id="error-banner" class="hidden bg-red-900/50 border border-red-600 text-red-300 px-4 py-2 rounded-lg text-xs"></div>

        {{-- Table wrapper --}}
        <div class="overflow-auto rounded-xl border border-gray-700 max-h-[78vh]" id="table-wrapper">
            <table class="chain-table w-full text-[11px]" id="chain-table">
                <thead id="chain-thead" class="sticky top-0"></thead>
                <tbody id="chain-tbody">
                <tr><td colspan="99" class="py-10 text-center text-gray-500">Select options and click Load Table.</td></tr>
                </tbody>
            </table>
        </div>

        {{-- BU Legend --}}
        <div class="flex flex-wrap gap-3 text-[11px] text-gray-400 px-1 pb-2">
            <span><span class="bu-lb px-1.5 py-0.5 rounded font-bold text-[10px]">LB</span> Long Build</span>
            <span><span class="bu-sb px-1.5 py-0.5 rounded font-bold text-[10px]">SB</span> Short Build</span>
            <span><span class="bu-sc px-1.5 py-0.5 rounded font-bold text-[10px]">SC</span> Short Cover</span>
            <span><span class="bu-lu px-1.5 py-0.5 rounded font-bold text-[10px]">LU</span> Long Unwind</span>
        </div>

    </div>

    <script>
        const DATA_URL = "{{ route('oi-diff-live.data') }}";
        let refreshTimer = null;
        let lastPayload = null;

        // ── Formatters ──────────────────────────────────────────────────────────────
        const fmtLtp = v => (v === null || v === undefined) ? '—' : parseFloat(v).toFixed(2);
        const fmtNum = v => v !== null && v !== undefined ? parseInt(v).toLocaleString('en-IN') : '—';
        const numVal = v => (v === null || v === undefined) ? null : parseFloat(v);

        function formatINRCompact(number) {
            if (number === null || number === undefined || number === '—') return '—';

            const n = Number(number);
            if (!Number.isFinite(n)) return '—';

            const abs = Math.abs(n);
            const sign = n < 0 ? '-' : '';

            const round2 = (x) => Number(x.toFixed(2)); // rounds to 2 decimals, drops trailing zeros [web:7]

            if (abs >= 10_000_000) return `${sign}${round2(abs / 10_000_000)} C`;
            if (abs >= 100_000)    return `${sign}${round2(abs / 100_000)} L`;
            if (abs >= 1_000)      return `${sign}${round2(abs / 1_000)} T`;

            // "number_format($abs, 2)" equivalent: always show 2 decimals, no grouping needed
            return sign + new Intl.NumberFormat('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
                useGrouping: false
            }).format(abs); // Intl.NumberFormat formatting options [web:2]
        }

        function buClass(bu) {
            if (!bu) return 'bu-blank';
            const map = { 'Long Build': 'bu-lb', 'Short Build': 'bu-sb', 'Short Cover': 'bu-sc', 'Long Unwind': 'bu-lu' };
            return map[bu] ?? 'bu-blank';
        }
        function buLabel(bu) {
            if (!bu) return '—';
            const map = { 'Long Build': 'LB', 'Short Build': 'SB', 'Short Cover': 'SC', 'Long Unwind': 'LU' };
            return map[bu] ?? '—';
        }

        // ── Highlight resolver ───────────────────────────────────────────────────────
        function inTop3(val, arr) {
            const v = numVal(val);
            if (v === null || !arr || !arr.length) return false;
            return arr.some(t => Math.abs(Number(t) - v) < 0.01);
        }
        function oiCellClass(val, top3pos, top3neg) {
            const v = numVal(val);
            if (v === null) return 'text-gray-600';
            if (inTop3(val, top3pos)) return 'text-green-300 font-bold ring-1 ring-green-500 rounded px-0.5';
            if (inTop3(val, top3neg)) return 'text-red-300 font-bold ring-1 ring-red-500 rounded px-0.5';
            return v > 0 ? 'text-green-400' : v < 0 ? 'text-red-400' : 'text-gray-400';
        }
        function volCellClass(val, top3vol) {
            const v = numVal(val);
            if (v === null) return 'text-gray-600';
            if (inTop3(val, top3vol)) return 'text-blue-300 font-bold ring-1 ring-blue-400 rounded px-0.5';
            return v > 0 ? 'text-green-400' : v < 0 ? 'text-red-400' : 'text-gray-400';
        }

        function globalOiClass(val, top3pos, top3neg) {
            const v = numVal(val);
            if (v === null) return 'text-gray-600';
            if (inTop3(val, top3pos)) return 'bg-green-700/60 text-green-100 font-bold rounded px-1 ring-yellow-400 ring-2 ';
            if (inTop3(val, top3neg)) return 'bg-red-700/60 text-red-100 font-bold rounded px-1 ring-yellow-400 ring-2 ';
            return v > 0 ? 'text-green-400' : v < 0 ? 'text-red-400' : 'text-gray-400';
        }

        function globalVolClass(val, top3pos, top3neg) {
            const v = numVal(val);
            if (v === null) return 'text-gray-600';
            if (inTop3(val, top3pos)) return 'bg-green-700/60 text-green-100 font-bold rounded px-1 ring-yellow-400 ring-2 ';
            if (inTop3(val, top3neg)) return 'bg-red-700/60 text-red-100 font-bold rounded px-1 ring-yellow-400 ring-2 ';
            return v > 0 ? 'text-green-400' : v < 0 ? 'text-red-400' : 'text-gray-400';
        }

        // ── Build thead ──────────────────────────────────────────────────────────────
        function buildHead(strikes, atm) {
            const strikeCols = strikes.length; // each strike = 8 cols (CE:4 + PE:4)

            let r1 = `<tr class="bg-gray-900 border-b border-gray-700">
        <th rowspan="3" class="col-time px-3 py-2 text-gray-400 font-semibold text-left border-r border-gray-700 bg-gray-900">TIME</th>`;

            strikes.forEach(s => {
                const isAtm = s === atm;
                r1 += `<th colspan="8" class="px-2 py-2 text-center font-bold tracking-wider border-r border-gray-600
            ${isAtm ? 'text-yellow-300 bg-yellow-900/20' : 'text-gray-200 bg-gray-800'}">
            ${Number(s).toLocaleString('en-IN')}${isAtm ? ' <span class="text-yellow-400 text-[9px]">ATM</span>' : ''}
        </th>`;
            });
            r1 += '</tr>';

            let r2 = `<tr class="bg-gray-900 border-b border-gray-700">`;
            strikes.forEach(() => {
                r2 += `
            <th colspan="4" class="py-1.5 text-center text-green-400 font-semibold text-[10px] tracking-widest border-r border-gray-700 bg-green-950/20">CE</th>
            <th colspan="4" class="py-1.5 text-center text-red-400 font-semibold text-[10px] tracking-widest border-r border-gray-600 bg-red-950/20">PE</th>`;
            });
            r2 += '</tr>';

            let r3 = `<tr class="bg-gray-800 border-b border-gray-700 text-gray-400">`;
            strikes.forEach(() => {
                ['CE','PE'].forEach(type => {
                    const bgBase = type === 'CE' ? 'bg-green-950/10' : 'bg-red-950/10';
                    const border = type === 'PE' ? 'border-r border-gray-600' : '';
                    r3 += `
                <th class="px-2 py-1.5 ${bgBase}">Close Δ</th>
                <th class="px-2 py-1.5 ${bgBase}">OI Δ</th>
                <th class="px-2 py-1.5 ${bgBase}">Vol Δ</th>
                <th class="px-2 py-1.5 ${bgBase} ${border} border-r border-gray-700">BU</th>`;
                });
            });
            r3 += '</tr>';

            document.getElementById('chain-thead').innerHTML = r1 + r2 + r3;
        }

        // ── Build tbody ──────────────────────────────────────────────────────────────
        function buildBody(rows, strikes, top3OiPos, top3OiNeg, top3VolPos, top3VolNeg, perStrikeHighlights) {
            const tbody = document.getElementById('chain-tbody');

            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="99" class="py-10 text-center text-gray-500">No data for selected date/expiry.</td></tr>';
                return;
            }

            let html = '';

            rows.forEach((row, idx) => {
                const rowBg = idx % 2 === 0 ? 'bg-gray-900' : 'bg-gray-950';
                html += `<tr class="${rowBg} border-b border-gray-800 hover:bg-gray-800/60 transition-colors">`;

                // TIME column
                html += `<td class="col-time px-3 py-1.5 font-semibold text-gray-300 border-r border-gray-700 text-center">${row.time}</td>`;

                strikes.forEach(strike => {
                    const sd  = row.strike_data[String(strike)] ?? {};
                    const ce  = sd.ce;
                    const pe  = sd.pe;
                    const psh = perStrikeHighlights[String(strike)] ?? { oi_pos:[], oi_neg:[], vol_pos:[], vol_neg:[] };

                    ['CE', 'PE'].forEach(type => {
                        const d       = type === 'CE' ? ce : pe;
                        const bgBase  = type === 'CE' ? 'bg-green-950/10' : 'bg-red-950/10';
                        const borderR = type === 'PE'  ? 'border-r border-gray-600' : 'border-r border-gray-700';

                        const diffLtp = (d?.diff_ltp ?? d?.ltp) ?? null;
                        const diffOi  = (d?.diff_oi ?? d?.oi) ?? null;
                        const diffVol = (d?.diff_volume ?? d?.volume) ?? null;
                        const bu      = d?.build_up    ?? null;

                        // OI: global BG highlight takes priority, then per-strike border
                        // Vol: global BG highlight takes priority, then per-strike border
                        const oiGlobal  = (inTop3(diffOi,  top3OiPos) || inTop3(diffOi,  top3OiNeg));
                        const volGlobal = (inTop3(diffVol, top3VolPos) || inTop3(diffVol, top3VolNeg));

                        const oiClass  = oiGlobal  ? globalOiClass(diffOi,  top3OiPos,  top3OiNeg)
                            : strikeOiClass(diffOi,  psh);
                        const volClass = volGlobal ? globalVolClass(diffVol, top3VolPos, top3VolNeg)
                            : strikeVolClass(diffVol, psh);

                        html += `
                    <td class="px-2 py-1.5 text-right ${bgBase} ${ltpClass(diffLtp)}">${fmtLtp(diffLtp)}</td>
                    <td class="px-2 py-1.5 text-right ${bgBase}">
                        <span class="${oiClass}">${formatINRCompact(diffOi)}</span>
                    </td>
                    <td class="px-2 py-1.5 text-right ${bgBase}">
                        <span class="${volClass}">${formatINRCompact(diffVol)}</span>
                    </td>
                    <td class="px-2 py-1.5 text-center ${bgBase} ${borderR}">
                        <span class="${buClass(bu)} px-1.5 py-0.5 rounded text-[10px] font-bold">${buLabel(bu)}</span>
                    </td>`;
                    });
                });

                html += '</tr>';
            });

            tbody.innerHTML = html;
        }


        // ── Populate expiry dropdown ─────────────────────────────────────────────────
        function populateExpiries(expiries, selected) {
            const sel = document.getElementById('expirySelect');
            sel.innerHTML = '<option value="">-- Auto --</option>';
            expiries.forEach(e => {
                const opt = document.createElement('option');
                opt.value = e;
                opt.textContent = e;
                if (e === selected) opt.selected = true;
                sel.appendChild(opt);
            });
        }
        /**
         * PER-STRIKE highlight — border only (no background)
         * Green border for top-3 positive, Red border for top-3 negative within that strike
         */
        function strikeOiClass(val, psHighlight) {
            const v = numVal(val);
            if (v === null) return 'text-gray-600';
            if (inTop3(val, psHighlight.oi_pos)) return 'ring-2 ring-green-400 text-green-300 font-semibold rounded px-1';
            if (inTop3(val, psHighlight.oi_neg)) return 'ring-2 ring-red-400 text-red-300 font-semibold rounded px-1';
            return v > 0 ? 'text-green-400' : v < 0 ? 'text-red-400' : 'text-gray-400';
        }

        function strikeVolClass(val, psHighlight) {
            const v = numVal(val);
            if (v === null) return 'text-gray-600';
            if (inTop3(val, psHighlight.vol_pos)) return 'ring-2 ring-green-400 text-green-300 font-semibold rounded px-1';
            if (inTop3(val, psHighlight.vol_neg)) return 'ring-2 ring-red-400 text-red-300 font-semibold rounded px-1';
            return v > 0 ? 'text-green-400' : v < 0 ? 'text-red-400' : 'text-gray-400';
        }

        function ltpClass(val) {
            const v = numVal(val);
            if (v === null) return 'text-gray-600';
            return v > 0 ? 'text-green-300' : v < 0 ? 'text-red-300' : 'text-gray-400';
        }

        // ── Main fetch ───────────────────────────────────────────────────────────────
        function loadData() {
            const symbol  = document.getElementById('symbolSelect').value;
            const date    = document.getElementById('dateInput').value;
            const expiry  = document.getElementById('expirySelect').value;
            const sides   = document.getElementById('strikesSelect').value;


            document.getElementById('loadBtn').disabled = true;
            document.getElementById('loadBtn').textContent = 'Loading…';

            const params = new URLSearchParams({
                symbol,
                strikes_each_side: sides
            });
            const tf = document.getElementById('tfSelect').value;
            params.append('tf', tf);

            if (date) params.append('date', date);
            if (expiry) params.append('expiry', expiry);

            fetch(`${DATA_URL}?${params}`)
                .then(r => r.json())
                .then(data => {
                    document.getElementById('loadBtn').disabled = false;
                    document.getElementById('loadBtn').innerHTML = `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582M20 20v-5h-.581M5.635 19A9 9 0 1 0 4.582 9"/></svg> Load Table`;

                    if (data.error) {
                        document.getElementById('error-banner').textContent = data.error;
                        document.getElementById('error-banner').classList.remove('hidden');
                        return;
                    }

                    document.getElementById('error-banner').classList.add('hidden');
                    document.getElementById('last-updated').textContent = data.last_updated;
                    const tfLabel = data.tf ? `${data.tf}-minute` : '3-minute';
                    document.getElementById('page-title').innerHTML =
                        `${data.symbol} OI & Volume Diff <span class="text-gray-400 font-normal text-xs">${tfLabel} candles</span>`;

                    document.getElementById('strikes-count').textContent = `(${data.strikes.length})`;

                    populateExpiries(data.expiries, data.expiry);

                    const top3OiPos  = (data.top3_oi_pos  || []).map(Number);
                    const top3OiNeg  = (data.top3_oi_neg  || []).map(Number);
                    const top3VolPos = (data.top3_vol_pos || []).map(Number);

                    buildHead(data.strikes, data.atm);
                    buildBody(
                        data.rows,
                        data.strikes,
                        data.top3_oi_pos,
                        data.top3_oi_neg,
                        data.top3_vol_pos,
                        data.top3_vol_neg,          // NEW
                        data.per_strike_highlights  // NEW
                    );

                    lastPayload = data;
                })
                .catch(err => {
                    document.getElementById('loadBtn').disabled = false;
                    document.getElementById('loadBtn').textContent = 'Load Table';
                    document.getElementById('error-banner').textContent = 'Request failed: ' + err.message;
                    document.getElementById('error-banner').classList.remove('hidden');
                });
        }

        // ── Auto-refresh every 3 mins ────────────────────────────────────────────────
        function startTimer() {
            clearInterval(refreshTimer);
            refreshTimer = setInterval(loadData, 180_000);
        }

        // ── Countdown badge ──────────────────────────────────────────────────────────
        let countdown = 180;
        setInterval(() => {
            countdown--;
            if (countdown <= 0) countdown = 180;
            const badge = document.getElementById('last-updated');
            if (lastPayload) {
                badge.textContent = lastPayload.last_updated + ` · next in ${countdown}s`;
            }
        }, 1000);

        // ── Events ───────────────────────────────────────────────────────────────────
        document.getElementById('loadBtn').addEventListener('click', () => {
            loadData();
            startTimer();
            countdown = 180;
        });

        document.getElementById('resetBtn').addEventListener('click', () => {
            document.getElementById('symbolSelect').value  = 'NIFTY';
            document.getElementById('dateInput').value     = new Date().toISOString().slice(0, 10);
            document.getElementById('strikesSelect').value = '2';
            document.getElementById('expirySelect').innerHTML = '<option value="">-- Auto --</option>';
            document.getElementById('chain-thead').innerHTML = '';
            document.getElementById('chain-tbody').innerHTML =
                '<tr><td colspan="99" class="py-10 text-center text-gray-500">Select options and click Load Table.</td></tr>';
            clearInterval(refreshTimer);
            lastPayload = null;
            countdown = 180;
        });

        // Initial load
        loadData();
        startTimer();
    </script>
    <style>
        /* Compact scrollable table */
        .chain-table { border-collapse: collapse; }
        .chain-table th, .chain-table td { white-space: nowrap; }
        /* Freeze TIME column */
        .chain-table .col-time { position: sticky; left: 0; z-index: 10; background: #111827; }
        /* Build-up badge colors */
        .bu-lb { background:#16a34a; color:#fff; }
        .bu-sb { background:#dc2626; color:#fff; }
        .bu-sc { background:#1e3a5f; color:#fff; }
        .bu-lu { background:#ffcc00; color: #000; }
        .bu-blank { background:#374151; color:#9ca3af; }
    </style>
@endsection




