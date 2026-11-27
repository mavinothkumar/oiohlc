@extends('layouts.app')

@section('title', 'Breadth Impulse Calendar | STK & OPT Signal Forensics')

@section('content')
<div class="min-h-screen bg-[#090d16] text-gray-200 px-3 sm:px-5 py-4 space-y-4">
    <!-- Top Header & Controls -->
    <div class="bg-[#0f1422] border border-gray-800 rounded-xl p-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 shadow-lg">
        <div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="text-xl">📅</span>
                <h1 class="text-lg sm:text-xl font-black tracking-wide text-cyan-300">BREADTH IMPULSE CALENDAR</h1>
                <span class="px-2.5 py-0.5 rounded text-xs font-mono font-bold bg-cyan-950 text-cyan-200 border border-cyan-700/70">STK + OPT + BOTH</span>
            </div>
            <p class="text-xs sm:text-sm text-gray-400 mt-1">
                Day-wise Upside (Bull) vs Downside (Bear) impulse counts, Open-to-High / Open-to-Low / Total Range, and 5-minute signal worthiness forensics.
            </p>
        </div>

        <!-- Filters: Lookback, Calendar Date, STK/OPT Thresholds -->
        <div class="flex flex-wrap items-center gap-2 font-mono text-xs">
            <div class="flex items-center bg-[#141b2d] border border-gray-700 rounded-lg p-1 gap-1">
                <button type="button" onclick="loadSummaryDays(10)" id="btn-days-10" class="px-2.5 py-1 rounded bg-cyan-950 text-cyan-200 border border-cyan-600 font-bold cursor-pointer">Last 10d</button>
                <button type="button" onclick="loadSummaryDays(20)" id="btn-days-20" class="px-2.5 py-1 rounded text-gray-300 hover:text-white cursor-pointer">20d</button>
                <button type="button" onclick="loadSummaryDays(30)" id="btn-days-30" class="px-2.5 py-1 rounded text-gray-300 hover:text-white cursor-pointer">30d</button>
            </div>

            <div class="flex items-center gap-1.5 bg-[#141b2d] border border-gray-700 rounded-lg px-2.5 py-1">
                <span class="text-gray-400">Day:</span>
                <input type="date" id="bic-single-date" min="{{ $minDate }}" max="{{ $maxDate }}" value="{{ $today }}" class="bg-transparent text-white font-bold focus:outline-none cursor-pointer">
                <button type="button" onclick="loadSingleDate()" class="px-2 py-0.5 rounded bg-sky-700 hover:bg-sky-600 text-white font-bold cursor-pointer">Inspect</button>
            </div>

            <div class="flex items-center gap-1.5 bg-[#141b2d] border border-gray-700 rounded-lg px-2.5 py-1">
                <label class="text-gray-400">STK≥</label>
                <input type="number" id="bic-stk-th" value="80" min="50" max="100" class="w-12 bg-[#090d16] border border-gray-700 rounded px-1.5 py-0.5 text-center text-sky-300 font-bold">
                <label class="text-gray-400">OPT≥</label>
                <input type="number" id="bic-opt-th" value="70" min="50" max="100" class="w-12 bg-[#090d16] border border-gray-700 rounded px-1.5 py-0.5 text-center text-amber-300 font-bold">
                <button type="button" onclick="reloadCurrentView()" class="px-2 py-0.5 rounded bg-emerald-800 hover:bg-emerald-700 text-white font-bold cursor-pointer">Apply</button>
            </div>
        </div>
    </div>

    <!-- Aggregate KPI Strip across Loaded Days -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 font-mono">
        <div class="bg-[#0f1422] border border-gray-800 rounded-xl p-3">
            <div class="text-[11px] text-gray-400 uppercase font-bold">Days Loaded</div>
            <div id="kpi-days-cnt" class="text-2xl font-black text-white mt-1">--</div>
        </div>
        <div class="bg-[#0f1422] border border-emerald-900/50 rounded-xl p-3">
            <div class="text-[11px] text-emerald-300 uppercase font-bold">▲ Total Upside Calls</div>
            <div id="kpi-bull-cnt" class="text-2xl font-black text-emerald-400 mt-1">--</div>
        </div>
        <div class="bg-[#0f1422] border border-rose-900/50 rounded-xl p-3">
            <div class="text-[11px] text-rose-300 uppercase font-bold">▼ Total Selling Calls</div>
            <div id="kpi-bear-cnt" class="text-2xl font-black text-rose-400 mt-1">--</div>
        </div>
        <div class="bg-[#0f1422] border border-purple-900/50 rounded-xl p-3">
            <div class="text-[11px] text-purple-300 uppercase font-bold">⚡ Avg Day Range</div>
            <div id="kpi-avg-range" class="text-2xl font-black text-purple-300 mt-1">--</div>
        </div>
        <div class="bg-[#0f1422] border border-cyan-900/50 rounded-xl p-3 col-span-2 lg:col-span-1">
            <div class="text-[11px] text-cyan-300 uppercase font-bold">✅ Overall Worth Rate</div>
            <div id="kpi-worth-rate" class="text-2xl font-black text-cyan-300 mt-1">--</div>
        </div>
    </div>

    <!-- Day-Wise Summary Table -->
    <div class="bg-[#0f1422] border border-gray-800 rounded-xl p-3.5 shadow-lg space-y-2.5">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <h2 class="text-sm sm:text-base font-black text-white tracking-wide">📊 DAY-WISE IMPULSE &amp; EXCURSION SUMMARY <span class="text-xs font-normal text-gray-400">(Click any day or count to inspect 5M signals &amp; chart)</span></h2>
            <span id="bic-loading-badge" class="hidden px-2.5 py-0.5 rounded text-xs font-mono bg-sky-950 text-sky-300 border border-sky-700 animate-pulse">Loading...</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left font-mono text-xs sm:text-sm border-collapse">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-800 text-[11px] uppercase bg-[#131a2c]">
                        <th class="py-2.5 px-3">Date</th>
                        <th class="py-2.5 px-3 text-right">Open ➔ Close</th>
                        <th class="py-2.5 px-3 text-right text-emerald-300">Open ➔ High</th>
                        <th class="py-2.5 px-3 text-right text-rose-300">Open ➔ Low</th>
                        <th class="py-2.5 px-3 text-right">Total Range</th>
                        <th class="py-2.5 px-3 text-center text-emerald-300">▲ Upside (STK / OPT / BOTH)</th>
                        <th class="py-2.5 px-3 text-center text-rose-300">▼ Selling (STK / OPT / BOTH)</th>
                        <th class="py-2.5 px-3 text-center">Pressure</th>
                        <th class="py-2.5 px-3 text-center text-amber-300">🏆 S4 (15p/25p)</th>
                        <th class="py-2.5 px-3 text-right">Worthiness</th>
                    </tr>
                </thead>
                <tbody id="bic-summary-tbody" class="divide-y divide-gray-800/70">
                    <tr><td colspan="10" class="py-8 text-center text-gray-400">Loading last 10 working days...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Detailed Day Drill-Down Section (Visible when a Day or Count is clicked) -->
    <div id="bic-detail-section" class="hidden bg-[#0f1422] border border-cyan-500/40 rounded-xl p-4 shadow-2xl space-y-4">
        <div class="flex items-center justify-between flex-wrap gap-2 pb-2.5 border-b border-gray-800">
            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="text-lg">🔍</span>
                <h3 id="detail-title" class="text-base sm:text-lg font-black text-cyan-300">--</h3>
                <span id="detail-pressure-badge" class="px-2.5 py-0.5 rounded text-xs font-mono font-black">--</span>
                <a id="detail-open-v2-link" href="#" target="_blank" class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-sky-300 border border-slate-700 text-xs font-bold">📈 Open in Cockpit V2 ↗</a>
            </div>
            <div class="flex items-center gap-1.5 font-mono text-xs flex-wrap">
                <button type="button" onclick="setDetailFilter('ALL')" class="bic-flt-btn px-2.5 py-1 rounded bg-cyan-950 text-cyan-200 border border-cyan-600 font-bold cursor-pointer" data-flt="ALL">All</button>
                <button type="button" onclick="setDetailFilter('BULL')" class="bic-flt-btn px-2.5 py-1 rounded bg-slate-900 text-gray-300 border border-slate-700 font-bold cursor-pointer" data-flt="BULL">▲ Upside</button>
                <button type="button" onclick="setDetailFilter('BEAR')" class="bic-flt-btn px-2.5 py-1 rounded bg-slate-900 text-gray-300 border border-slate-700 font-bold cursor-pointer" data-flt="BEAR">▼ Selling</button>
                <button type="button" onclick="setDetailFilter('BOTH')" class="bic-flt-btn px-2.5 py-1 rounded bg-slate-900 text-gray-300 border border-slate-700 font-bold cursor-pointer" data-flt="BOTH">BOTH</button>
                <button type="button" onclick="setDetailFilter('STK')" class="bic-flt-btn px-2.5 py-1 rounded bg-slate-900 text-gray-300 border border-slate-700 font-bold cursor-pointer" data-flt="STK">STK</button>
                <button type="button" onclick="setDetailFilter('OPT')" class="bic-flt-btn px-2.5 py-1 rounded bg-slate-900 text-gray-300 border border-slate-700 font-bold cursor-pointer" data-flt="OPT">OPT</button>
                <button type="button" onclick="setDetailFilter('S4')" class="bic-flt-btn px-2.5 py-1 rounded bg-amber-950/70 text-amber-300 border border-amber-600/70 font-black cursor-pointer" data-flt="S4" title="Fresh OPT ≥70% + With VWAP + STK ≥60% + 09:50–14:35 (+15p / -25p SL)">🏆 S4 (73%)</button>
            </div>
        </div>

        <!-- Day Excursion Strip -->
        <div id="detail-excursion-strip" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5 font-mono text-xs"></div>

        <!-- Filter-Reactive Worth / Scalp / Failed Summary Strip -->
        <div id="detail-outcome-strip" class="grid grid-cols-2 sm:grid-cols-5 gap-2.5 font-mono text-xs"></div>

        <!-- Interactive 5M Spot & STK/OPT Impulse Chart -->
        <div class="bg-[#0a0f1c] border border-gray-800 rounded-xl p-3">
            <div class="text-xs font-mono font-bold text-gray-300 mb-2">📈 5-Minute Spot Trajectory &amp; Impulse Signals (Green ▲ Bull / Red ▼ Bear)</div>
            <div class="h-64 w-full">
                <canvas id="bic-day-chart"></canvas>
            </div>
        </div>

        <!-- Detailed 5-Minute Signal Timeline Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left font-mono text-xs sm:text-sm border-collapse">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-800 text-[11px] uppercase bg-[#131a2c]">
                        <th class="py-2 px-3">Time</th>
                        <th class="py-2 px-3">Direction</th>
                        <th class="py-2 px-3">Type</th>
                        <th class="py-2 px-3 text-right">Spot</th>
                        <th class="py-2 px-3 text-right">STK % (Cnt/Wt)</th>
                        <th class="py-2 px-3 text-right">OPT %</th>
                        <th class="py-2 px-3 text-right">+15m</th>
                        <th class="py-2 px-3 text-right">+30m</th>
                        <th class="py-2 px-3 text-right">60m MFE / MAE</th>
                        <th class="py-2 px-3">Worthiness &amp; Justification</th>
                    </tr>
                </thead>
                <tbody id="bic-detail-tbody" class="divide-y divide-gray-800/70"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function() {
    'use strict';
    let activeDaysCount = 10;
    let activeSingleDate = null;
    let currentDetailData = null;
    let currentDetailFilter = 'ALL';
    let dayChartInstance = null;

    function getThresholds() {
        const stk = Number(document.getElementById('bic-stk-th')?.value || 80);
        const opt = Number(document.getElementById('bic-opt-th')?.value || 70);
        return { stk, opt };
    }

    function pressureBadgeHtml(p) {
        if (p === 'HEAVY_BUYING') return '<span class="px-2 py-0.5 rounded text-[11px] font-black bg-emerald-950 text-emerald-300 border border-emerald-700">🔥 HEAVY BUYING</span>';
        if (p === 'HEAVY_SELLING') return '<span class="px-2 py-0.5 rounded text-[11px] font-black bg-rose-950 text-rose-300 border border-rose-700">🩸 HEAVY SELLING</span>';
        if (p === 'TWO_WAY_CHOP') return '<span class="px-2 py-0.5 rounded text-[11px] font-black bg-amber-950 text-amber-300 border border-amber-700">⚔️ TWO-WAY CHOP</span>';
        return '<span class="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-800 text-gray-400 border border-slate-700">⚪ QUIET</span>';
    }

    async function fetchSummary(params) {
        const badge = document.getElementById('bic-loading-badge');
        if (badge) badge.classList.remove('hidden');
        try {
            const qs = new URLSearchParams(params).toString();
            const res = await fetch(`/api/breadth-impulse-calendar/summary?${qs}`).then(r => r.json());
            renderSummaryTable(res.days || []);
        } catch (e) {
            document.getElementById('bic-summary-tbody').innerHTML = `<tr><td colspan="9" class="py-8 text-center text-rose-400">Failed to load summary: ${e.message}</td></tr>`;
        } finally {
            if (badge) badge.classList.add('hidden');
        }
    }

    function renderSummaryTable(days) {
        const tbody = document.getElementById('bic-summary-tbody');
        if (!days || days.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="py-8 text-center text-amber-300">No 5-minute data recorded for the selected period.</td></tr>';
            return;
        }

        let totBull = 0, totBear = 0, totRange = 0, totSigs = 0, totWorth = 0;
        days.forEach(d => {
            totBull += d.counts.bull_total;
            totBear += d.counts.bear_total;
            totRange += d.total_range;
            totSigs += d.counts.total_sigs;
            totWorth += d.counts.worth_hits;
        });
        document.getElementById('kpi-days-cnt').textContent = days.length;
        document.getElementById('kpi-bull-cnt').textContent = totBull;
        document.getElementById('kpi-bear-cnt').textContent = totBear;
        document.getElementById('kpi-avg-range').textContent = `${(totRange / days.length).toFixed(1)}p`;
        document.getElementById('kpi-worth-rate').textContent = totSigs > 0 ? `${Math.round((totWorth / totSigs) * 100)}%` : '--';

        tbody.innerHTML = days.map(d => {
            const netSign = d.net_move >= 0 ? '+' : '';
            const netCls = d.net_move >= 0 ? 'text-emerald-400' : 'text-rose-400';
            const c = d.counts;
            return `
                <tr data-date="${d.date}" onclick="openDayDetail('${d.date}', 'ALL')" class="bic-day-row hover:bg-white/[0.03] cursor-pointer transition">
                    <td class="py-2.5 px-3 font-bold text-white whitespace-nowrap">
                        <span>${d.date}</span> <span class="text-gray-400 text-xs">(${d.day_name})</span>
                        <span class="bic-sel-badge hidden ml-1.5 px-1.5 py-0.5 rounded text-[10px] font-black bg-cyan-500/20 text-cyan-300 border border-cyan-500/60">ACTIVE</span>
                    </td>
                    <td class="py-2.5 px-3 text-right whitespace-nowrap">
                        <span class="text-gray-300">${d.open.toFixed(1)} ➔ ${d.close.toFixed(1)}</span>
                        <span class="${netCls} font-black ml-1">(${netSign}${d.net_move.toFixed(1)}p)</span>
                    </td>
                    <td class="py-2.5 px-3 text-right font-bold text-emerald-400 whitespace-nowrap">+${d.open_to_high.toFixed(1)}p</td>
                    <td class="py-2.5 px-3 text-right font-bold text-rose-400 whitespace-nowrap">${d.open_to_low.toFixed(1)}p</td>
                    <td class="py-2.5 px-3 text-right font-black text-purple-300 whitespace-nowrap">${d.total_range.toFixed(1)}p</td>
                    <td class="py-2.5 px-3 text-center whitespace-nowrap">
                        <button type="button" onclick="event.stopPropagation(); openDayDetail('${d.date}', 'BULL')" class="px-2.5 py-0.5 rounded bg-emerald-950/70 hover:bg-emerald-900 text-emerald-300 border border-emerald-700/70 font-black cursor-pointer">
                            ▲ ${c.bull_total} <span class="text-[10.5px] font-normal text-gray-300">(${c.bull_stk}/${c.bull_opt}/${c.bull_both})</span>
                        </button>
                    </td>
                    <td class="py-2.5 px-3 text-center whitespace-nowrap">
                        <button type="button" onclick="event.stopPropagation(); openDayDetail('${d.date}', 'BEAR')" class="px-2.5 py-0.5 rounded bg-rose-950/70 hover:bg-rose-900 text-rose-300 border border-rose-700/70 font-black cursor-pointer">
                            ▼ ${c.bear_total} <span class="text-[10.5px] font-normal text-gray-300">(${c.bear_stk}/${c.bear_opt}/${c.bear_both})</span>
                        </button>
                    </td>
                    <td class="py-2.5 px-3 text-center whitespace-nowrap">${pressureBadgeHtml(d.pressure)}</td>
                    <td class="py-2.5 px-3 text-center whitespace-nowrap">
                        <button type="button" onclick="event.stopPropagation(); openDayDetail('${d.date}', 'S4')" class="px-2 py-0.5 rounded bg-amber-950/60 hover:bg-amber-900 text-amber-200 border border-amber-700/60 font-bold cursor-pointer text-xs">
                            ${(c.s4_total || 0) > 0 ? `${c.s4_wins}W/${c.s4_losses}L (${Math.round((c.s4_wins / Math.max(1, c.s4_wins + c.s4_losses)) * 100)}%)` : '0 sigs'}
                        </button>
                    </td>
                    <td class="py-2.5 px-3 text-right whitespace-nowrap">
                        <span class="font-black ${d.worth_rate >= 60 ? 'text-emerald-400' : (d.worth_rate >= 40 ? 'text-amber-300' : 'text-rose-400')}">${d.worth_rate}%</span>
                        <span class="text-xs text-gray-400 ml-1">(+${d.avg_mfe}p)</span>
                    </td>
                </tr>
            `;
        }).join('');
    }

    window.loadSummaryDays = function(n) {
        activeDaysCount = n;
        activeSingleDate = null;
        [10, 20, 30].forEach(k => {
            const btn = document.getElementById(`btn-days-${k}`);
            if (btn) btn.className = (k === n)
                ? 'px-2.5 py-1 rounded bg-cyan-950 text-cyan-200 border border-cyan-600 font-bold cursor-pointer'
                : 'px-2.5 py-1 rounded text-gray-300 hover:text-white cursor-pointer';
        });
        const { stk, opt } = getThresholds();
        fetchSummary({ days: n, stk_th: stk, opt_th: opt });
    };

    window.loadSingleDate = function() {
        const d = document.getElementById('bic-single-date')?.value;
        if (!d) return;
        activeSingleDate = d;
        const { stk, opt } = getThresholds();
        fetchSummary({ date: d, stk_th: stk, opt_th: opt });
        openDayDetail(d, 'ALL');
    };

    window.reloadCurrentView = function() {
        if (activeSingleDate) {
            loadSingleDate();
        } else {
            loadSummaryDays(activeDaysCount);
        }
        if (currentDetailData) {
            openDayDetail(currentDetailData.date, currentDetailFilter);
        }
    };

    function highlightSelectedSummaryRow(date) {
        document.querySelectorAll('.bic-day-row').forEach(tr => {
            const isSel = tr.dataset.date === date;
            tr.className = isSel
                ? 'bic-day-row bg-cyan-950/45 ring-1 ring-inset ring-cyan-400/80 cursor-pointer transition'
                : 'bic-day-row hover:bg-white/[0.03] cursor-pointer transition';
            const badge = tr.querySelector('.bic-sel-badge');
            if (badge) badge.classList.toggle('hidden', !isSel);
        });
    }

    window.openDayDetail = async function(date, filter = 'ALL') {
        const sec = document.getElementById('bic-detail-section');
        sec.classList.remove('hidden');
        currentDetailFilter = filter;
        highlightSelectedSummaryRow(date);
        const { stk, opt } = getThresholds();
        const res = await fetch(`/api/breadth-impulse-calendar/day?date=${date}&stk_th=${stk}&opt_th=${opt}`).then(r => r.json());
        if (!res.success) {
            document.getElementById('bic-detail-tbody').innerHTML = `<tr><td colspan="10" class="py-6 text-center text-rose-400">${res.message || 'No data'}</td></tr>`;
            return;
        }
        currentDetailData = res.detail;
        renderDetailSection();
        sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    window.setDetailFilter = function(flt) {
        currentDetailFilter = flt;
        document.querySelectorAll('.bic-flt-btn').forEach(b => {
            const active = b.dataset.flt === flt;
            b.className = active
                ? 'bic-flt-btn px-2.5 py-1 rounded bg-cyan-950 text-cyan-200 border border-cyan-600 font-bold cursor-pointer'
                : 'bic-flt-btn px-2.5 py-1 rounded bg-slate-900 text-gray-300 border border-slate-700 font-bold cursor-pointer';
        });
        renderDetailTable();
    };

    function renderDetailSection() {
        const d = currentDetailData;
        if (!d) return;
        document.getElementById('detail-title').textContent = `${d.date} (${d.day_name}) — 5M Impulse Forensics`;
        document.getElementById('detail-pressure-badge').outerHTML = `<span id="detail-pressure-badge">${pressureBadgeHtml(d.pressure)}</span>`;
        document.getElementById('detail-open-v2-link').href = `/nifty-unified-v2?date=${d.date}`;

        const cards = [
            { lbl: 'Open', val: d.open.toFixed(2), cls: 'text-white' },
            { lbl: 'High', val: d.high.toFixed(2), cls: 'text-emerald-300' },
            { lbl: 'Low', val: d.low.toFixed(2), cls: 'text-rose-300' },
            { lbl: 'Open ➔ High', val: `+${d.open_to_high.toFixed(1)}p`, cls: 'text-emerald-400' },
            { lbl: 'Open ➔ Low', val: `${d.open_to_low.toFixed(1)}p`, cls: 'text-rose-400' },
            { lbl: 'Total Range', val: `${d.total_range.toFixed(1)}p`, cls: 'text-purple-300' },
        ];
        document.getElementById('detail-excursion-strip').innerHTML = cards.map(c =>
            `<div class="rounded-lg bg-[#141b2d] border border-gray-800 p-2.5"><div class="text-[10.5px] text-gray-400 uppercase">${c.lbl}</div><div class="text-base font-black ${c.cls} mt-0.5">${c.val}</div></div>`
        ).join('');

        setDetailFilter(currentDetailFilter);
        renderDayChart(d);
    }

    function renderDetailTable() {
        const d = currentDetailData;
        const tbody = document.getElementById('bic-detail-tbody');
        if (!d) return;
        const sigs = (d.signals || []).filter(s => {
            if (currentDetailFilter === 'ALL') return true;
            if (currentDetailFilter === 'S4') return !!s.is_s4;
            if (currentDetailFilter === 'BULL' || currentDetailFilter === 'BEAR') return s.direction === currentDetailFilter;
            return s.type === currentDetailFilter;
        });

        // Update filter-reactive Worth / Scalp / Failed KPI strip
        const outcomeStrip = document.getElementById('detail-outcome-strip');
        if (outcomeStrip) {
            const total = sigs.length;
            const worthCnt = sigs.filter(s => s.verdict === 'WORTH_IT').length;
            const scalpCnt = sigs.filter(s => s.verdict === 'SCALP_CHOP').length;
            const failedCnt = sigs.filter(s => s.verdict === 'FAILED').length;
            const avgMfe = total > 0 ? (sigs.reduce((a, s) => a + Number(s.mfe || 0), 0) / total).toFixed(1) : '0.0';
            const avgMae = total > 0 ? (sigs.reduce((a, s) => a + Number(s.mae || 0), 0) / total).toFixed(1) : '0.0';
            const pct = (c) => total > 0 ? Math.round((c / total) * 100) : 0;

            outcomeStrip.innerHTML = `
                <div class="rounded-lg bg-[#141b2d] border border-cyan-800/60 p-2.5">
                    <div class="text-[10.5px] text-cyan-300 uppercase font-bold">Filtered (${currentDetailFilter})</div>
                    <div class="text-lg font-black text-white mt-0.5">${total} <span class="text-xs text-gray-400 font-normal">Signals</span></div>
                </div>
                <div class="rounded-lg bg-emerald-950/30 border border-emerald-700/60 p-2.5">
                    <div class="text-[10.5px] text-emerald-300 uppercase font-bold">✅ Total Worth</div>
                    <div class="text-lg font-black text-emerald-400 mt-0.5">${worthCnt} <span class="text-xs font-bold">(${pct(worthCnt)}%)</span></div>
                </div>
                <div class="rounded-lg bg-amber-950/30 border border-amber-700/60 p-2.5">
                    <div class="text-[10.5px] text-amber-300 uppercase font-bold">⚠️ Scalp / Chop</div>
                    <div class="text-lg font-black text-amber-300 mt-0.5">${scalpCnt} <span class="text-xs font-bold">(${pct(scalpCnt)}%)</span></div>
                </div>
                <div class="rounded-lg bg-rose-950/30 border border-rose-700/60 p-2.5">
                    <div class="text-[10.5px] text-rose-300 uppercase font-bold">❌ Failed</div>
                    <div class="text-lg font-black text-rose-400 mt-0.5">${failedCnt} <span class="text-xs font-bold">(${pct(failedCnt)}%)</span></div>
                </div>
                <div class="rounded-lg bg-[#141b2d] border border-gray-800 p-2.5 col-span-2 sm:col-span-1">
                    <div class="text-[10.5px] text-gray-400 uppercase font-bold">Avg 60m MFE / MAE</div>
                    <div class="text-base font-black mt-0.5"><span class="text-emerald-400">+${avgMfe}p</span> <span class="text-gray-500">/</span> <span class="text-rose-400">-${avgMae}p</span></div>
                </div>
            `;
        }

        if (sigs.length === 0) {
            tbody.innerHTML = `<tr><td colspan="10" class="py-8 text-center text-gray-400">No signals matched filter "${currentDetailFilter}" on ${d.date}.</td></tr>`;
            return;
        }

        tbody.innerHTML = sigs.map(s => {
            const isBull = s.direction === 'BULL';
            const dirBadge = isBull
                ? '<span class="px-2 py-0.5 rounded bg-emerald-950 text-emerald-300 border border-emerald-700 font-black text-xs">▲ BULL</span>'
                : '<span class="px-2 py-0.5 rounded bg-rose-950 text-rose-300 border border-rose-700 font-black text-xs">▼ BEAR</span>';
            const typeBadge = s.type === 'BOTH'
                ? '<span class="px-2 py-0.5 rounded bg-amber-950 text-amber-300 border border-amber-600 font-black text-xs">⚡ BOTH</span>'
                : (s.type === 'STK'
                    ? '<span class="px-2 py-0.5 rounded bg-sky-950 text-sky-300 border border-sky-700 font-bold text-xs">STK</span>'
                    : '<span class="px-2 py-0.5 rounded bg-purple-950 text-purple-300 border border-purple-700 font-bold text-xs">OPT</span>');
            const fmtMv = (v) => v === null ? '<span class="text-gray-500">--</span>' : `<span class="font-bold ${v >= 0 ? 'text-emerald-400' : 'text-rose-400'}">${v >= 0 ? '+' : ''}${v}p</span>`;
            const vBadge = s.verdict === 'WORTH_IT'
                ? '<span class="px-2 py-0.5 rounded bg-emerald-950 text-emerald-300 border border-emerald-700 font-black text-xs">✅ WORTH IT</span>'
                : (s.verdict === 'SCALP_CHOP'
                    ? '<span class="px-2 py-0.5 rounded bg-amber-950 text-amber-300 border border-amber-700 font-bold text-xs">⚠️ SCALP</span>'
                    : (s.verdict === 'LIVE_BAR'
                        ? '<span class="px-2 py-0.5 rounded bg-sky-950 text-sky-300 border border-sky-700 font-bold text-xs">⏳ LIVE</span>'
                        : '<span class="px-2 py-0.5 rounded bg-rose-950 text-rose-300 border border-rose-700 font-bold text-xs">❌ FAILED</span>'));

            return `
                <tr class="hover:bg-white/[0.02]">
                    <td class="py-2 px-3 font-black text-sky-300">${s.time}</td>
                    <td class="py-2 px-3">${dirBadge}</td>
                    <td class="py-2 px-3">${typeBadge}</td>
                    <td class="py-2 px-3 text-right font-bold text-white">${s.spot.toFixed(2)}</td>
                    <td class="py-2 px-3 text-right text-sky-300">${s.stk_cnt}% / ${s.stk_wt}%</td>
                    <td class="py-2 px-3 text-right text-amber-300">${s.opt_pct}%</td>
                    <td class="py-2 px-3 text-right">${fmtMv(s.move_15m)}</td>
                    <td class="py-2 px-3 text-right">${fmtMv(s.move_30m)}</td>
                    <td class="py-2 px-3 text-right whitespace-nowrap"><span class="text-emerald-400 font-bold">+${s.mfe}p</span> / <span class="text-rose-400">-${s.mae}p</span></td>
                    <td class="py-2 px-3"><div class="flex items-center gap-2 flex-wrap">${vBadge}${s.is_s4 ? `<span class="px-1.5 py-0.5 rounded text-[10.5px] font-black border ${s.s4_result === 'WIN' ? 'bg-emerald-950 text-emerald-300 border-emerald-600' : (s.s4_result === 'LOSS' ? 'bg-rose-950 text-rose-300 border-rose-600' : 'bg-amber-950 text-amber-300 border-amber-600')}">🏆 S4 ${s.s4_result || ''}</span>` : ''}<span class="text-xs text-gray-300">${s.reason}</span></div></td>
                </tr>
            `;
        }).join('');
    }

    function renderDayChart(d) {
        const ctx = document.getElementById('bic-day-chart')?.getContext('2d');
        if (!ctx || typeof Chart === 'undefined') return;
        if (dayChartInstance) dayChartInstance.destroy();

        const bars = d.chart_bars || [];
        const labels = bars.map(b => b.time);
        const closes = bars.map(b => b.close);
        const bullPts = bars.map(b => b.bull_sig ? b.close : null);
        const bearPts = bars.map(b => b.bear_sig ? b.close : null);

        dayChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'NIFTY Spot (5m Close)',
                        data: closes,
                        borderColor: '#38bdf8',
                        backgroundColor: 'rgba(56, 189, 248, 0.08)',
                        borderWidth: 2,
                        pointRadius: 0,
                        tension: 0.15,
                        fill: true,
                    },
                    {
                        label: '▲ Bull Impulse',
                        data: bullPts,
                        borderColor: '#10b981',
                        backgroundColor: '#10b981',
                        pointStyle: 'triangle',
                        pointRadius: 7,
                        showLine: false,
                    },
                    {
                        label: '▼ Bear Impulse',
                        data: bearPts,
                        borderColor: '#f43f5e',
                        backgroundColor: '#f43f5e',
                        pointStyle: 'triangle',
                        rotation: 180,
                        pointRadius: 7,
                        showLine: false,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { labels: { color: '#cbd5e1', font: { family: 'monospace', size: 11 } } }
                },
                scales: {
                    x: { ticks: { color: '#94a3b8', maxTicksLimit: 14 }, grid: { color: 'rgba(148,163,184,0.08)' } },
                    y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.08)' } }
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadSummaryDays(10);
    });
})();
</script>
@endpush
