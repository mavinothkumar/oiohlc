@extends('layouts.app')

@section('title', 'S4 + OI 3-Bar Runner Lab | 6-Month Forensics')

@section('content')
<div class="min-h-screen bg-[#090d16] text-gray-200 px-3 sm:px-5 py-4 space-y-4">
    <!-- Header & Controls -->
    <div class="bg-[#0f1422] border border-gray-800 rounded-xl p-4 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-3 shadow-lg">
        <div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="text-xl">🔬</span>
                <h1 class="text-lg sm:text-xl font-black tracking-wide text-amber-300">S4 + OI 3-BAR RUNNER LAB</h1>
                <span class="px-2.5 py-0.5 rounded text-xs font-mono font-bold bg-emerald-950 text-emerald-300 border border-emerald-700/70">88.4% CLEAN 3-BAR STUDY</span>
            </div>
            <p class="text-xs sm:text-sm text-gray-400 mt-1">
                Inspect every <strong class="text-white">S4 + OI</strong> signal (Fresh OPT ≥ 70% + VWAP + STK ≥ 60% + Trend OI Aligned, 09:50–14:35) alongside the next 3 candles (<strong class="text-cyan-300">Bar +1, +2, +3</strong>) to discover hidden levels &amp; clues.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 font-mono text-xs">
            <div class="flex items-center gap-1.5 bg-[#141b2d] border border-gray-700 rounded-lg px-2.5 py-1">
                <span class="text-gray-400">From:</span>
                <input type="date" id="lab-from" min="{{ $minDate }}" max="{{ $today }}" value="{{ $defaultFrom }}" class="bg-transparent text-white font-bold focus:outline-none">
                <span class="text-gray-400">To:</span>
                <input type="date" id="lab-to" min="{{ $minDate }}" max="{{ $today }}" value="{{ $today }}" class="bg-transparent text-white font-bold focus:outline-none">
            </div>

            <div class="flex items-center gap-1.5 bg-[#141b2d] border border-gray-700 rounded-lg px-2.5 py-1">
                <label class="text-gray-400">Tgt:</label>
                <input type="number" id="lab-tgt" value="25" min="10" max="60" class="w-11 bg-[#090d16] border border-gray-700 rounded px-1 py-0.5 text-center text-emerald-300 font-bold">
                <label class="text-gray-400">SL:</label>
                <input type="number" id="lab-sl" value="25" min="10" max="60" class="w-11 bg-[#090d16] border border-gray-700 rounded px-1 py-0.5 text-center text-rose-300 font-bold">
                <label class="text-gray-400">RevOPT≥</label>
                <input type="number" id="lab-rev" value="60" min="40" max="95" class="w-11 bg-[#090d16] border border-gray-700 rounded px-1 py-0.5 text-center text-amber-300 font-bold">
                <button type="button" onclick="loadLabData()" class="px-2.5 py-1 rounded bg-sky-700 hover:bg-sky-600 text-white font-bold cursor-pointer">Scan</button>
            </div>

            <button type="button" onclick="exportLabCsv()" class="px-2.5 py-1.5 rounded bg-emerald-950 hover:bg-emerald-900 text-emerald-300 border border-emerald-700 font-bold cursor-pointer">📥 CSV</button>
        </div>
    </div>

    <!-- KPI Strip -->
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 font-mono">
        <div class="bg-[#0f1422] border border-gray-800 rounded-xl p-3">
            <div class="text-[11px] text-gray-400 uppercase font-bold">Days / Signals</div>
            <div id="lab-kpi-total" class="text-xl font-black text-white mt-1">--</div>
        </div>
        <div class="bg-[#0f1422] border border-emerald-800/60 rounded-xl p-3">
            <div class="text-[11px] text-emerald-300 uppercase font-bold">🏆 Clean 3-Bar Win %</div>
            <div id="lab-kpi-clean" class="text-xl font-black text-emerald-400 mt-1">--</div>
        </div>
        <div class="bg-[#0f1422] border border-rose-800/60 rounded-xl p-3">
            <div class="text-[11px] text-rose-300 uppercase font-bold">🩸 Reversed ≤3 Bars</div>
            <div id="lab-kpi-rev" class="text-xl font-black text-rose-400 mt-1">--</div>
        </div>
        <div class="bg-[#0f1422] border border-cyan-800/60 rounded-xl p-3">
            <div class="text-[11px] text-cyan-300 uppercase font-bold">Filtered Win Rate</div>
            <div id="lab-kpi-flt-wr" class="text-xl font-black text-cyan-300 mt-1">--</div>
        </div>
        <div class="bg-[#0f1422] border border-purple-800/60 rounded-xl p-3">
            <div class="text-[11px] text-purple-300 uppercase font-bold">Filtered Net Pts</div>
            <div id="lab-kpi-flt-net" class="text-xl font-black text-purple-300 mt-1">--</div>
        </div>
        <div class="bg-[#0f1422] border border-gray-800 rounded-xl p-3">
            <div class="text-[11px] text-gray-400 uppercase font-bold">Avg 60m MFE / MAE</div>
            <div id="lab-kpi-mfe" class="text-lg font-black text-white mt-1">--</div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-[#0f1422] border border-gray-800 rounded-xl p-3 flex flex-wrap items-center justify-between gap-2 font-mono text-xs">
        <div class="flex flex-wrap items-center gap-1.5">
            <button type="button" onclick="setLabFilter('CLEAN')" class="lab-flt-btn px-3 py-1 rounded bg-emerald-950 text-emerald-200 border border-emerald-600 font-black cursor-pointer" data-flt="CLEAN">🏆 Clean 3-Bar (No Rev OPT)</button>
            <button type="button" onclick="setLabFilter('REVERSED')" class="lab-flt-btn px-3 py-1 rounded bg-slate-900 text-gray-300 border border-slate-700 font-bold cursor-pointer" data-flt="REVERSED">🩸 Reversed in 1–3 Bars</button>
            <button type="button" onclick="setLabFilter('ALL')" class="lab-flt-btn px-3 py-1 rounded bg-slate-900 text-gray-300 border border-slate-700 font-bold cursor-pointer" data-flt="ALL">All S4+OI</button>
            <button type="button" onclick="setLabFilter('LOSSES')" class="lab-flt-btn px-3 py-1 rounded bg-slate-900 text-rose-300 border border-rose-800/60 font-bold cursor-pointer" data-flt="LOSSES">❌ Losses Only</button>
        </div>
        <div class="flex items-center gap-2">
            <input type="text" id="lab-search" onkeyup="renderLabTable()" placeholder="Search date (2026-11), time (12:55), BULL/BEAR..." class="bg-[#090d16] border border-gray-700 rounded px-2.5 py-1 text-xs text-white w-64 focus:outline-none">
            <span id="lab-loading" class="hidden text-sky-300 animate-pulse font-bold">Scanning 6 months...</span>
        </div>
    </div>

    <!-- Signals + 3-Bar Timeline Table -->
    <div class="bg-[#0f1422] border border-gray-800 rounded-xl p-3 shadow-lg">
        <div class="overflow-x-auto max-h-[68vh] overflow-y-auto">
            <table class="w-full text-left font-mono text-xs border-collapse">
                <thead class="sticky top-0 bg-[#131a2c] text-gray-400 text-[10.5px] uppercase z-10 border-b border-gray-800">
                    <tr>
                        <th class="py-2 px-2.5">Date &amp; Time</th>
                        <th class="py-2 px-2.5">Dir</th>
                        <th class="py-2 px-2.5 text-right">Entry Spot</th>
                        <th class="py-2 px-2.5 text-right">OPT / STK / OI</th>
                        <th class="py-2 px-2.5 text-center">Bar +1 (5m)</th>
                        <th class="py-2 px-2.5 text-center">Bar +2 (10m)</th>
                        <th class="py-2 px-2.5 text-center">Bar +3 (15m)</th>
                        <th class="py-2 px-2.5 text-right">60m MFE/MAE</th>
                        <th class="py-2 px-2.5 text-right">Outcome</th>
                        <th class="py-2 px-2.5 text-right">Chart</th>
                    </tr>
                </thead>
                <tbody id="lab-tbody" class="divide-y divide-gray-800/70">
                    <tr><td colspan="10" class="py-8 text-center text-gray-400">Loading 6-month S4+OI forensics...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    'use strict';
    let allSignals = [];
    let currentFilter = 'CLEAN';
    let lastFiltered = [];

    window.loadLabData = async function() {
        const loading = document.getElementById('lab-loading');
        if (loading) loading.classList.remove('hidden');
        try {
            const qs = new URLSearchParams({
                from: document.getElementById('lab-from').value,
                to: document.getElementById('lab-to').value,
                tgt: document.getElementById('lab-tgt').value,
                sl: document.getElementById('lab-sl').value,
                rev_th: document.getElementById('lab-rev').value,
            }).toString();
            const res = await fetch(`/api/scalper-s4-lab/data?${qs}`).then(r => r.json());
            allSignals = res.signals || [];
            document.getElementById('lab-kpi-total').textContent = `${res.days_scanned || 0}d / ${allSignals.length}`;

            const calcStats = (arr) => {
                const w = arr.filter(s => s.outcome === 'WIN').length;
                const l = arr.filter(s => s.outcome === 'LOSS').length;
                const wr = (w + l) > 0 ? Math.round((w / (w + l)) * 1000) / 10 : 0;
                return { w, l, wr, n: arr.length };
            };
            const clean = calcStats(allSignals.filter(s => s.clean_3bar));
            const rev = calcStats(allSignals.filter(s => !s.clean_3bar));
            document.getElementById('lab-kpi-clean').textContent = `${clean.wr}% (${clean.w}W/${clean.l}L)`;
            document.getElementById('lab-kpi-rev').textContent = `${rev.wr}% (${rev.w}W/${rev.l}L)`;

            renderLabTable();
        } catch (e) {
            document.getElementById('lab-tbody').innerHTML = `<tr><td colspan="10" class="py-8 text-center text-rose-400">Error: ${e.message}</td></tr>`;
        } finally {
            if (loading) loading.classList.add('hidden');
        }
    };

    window.setLabFilter = function(flt) {
        currentFilter = flt;
        document.querySelectorAll('.lab-flt-btn').forEach(b => {
            const active = b.dataset.flt === flt;
            b.className = active
                ? 'lab-flt-btn px-3 py-1 rounded bg-emerald-950 text-emerald-200 border border-emerald-500 font-black cursor-pointer'
                : 'lab-flt-btn px-3 py-1 rounded bg-slate-900 text-gray-300 border border-slate-700 font-bold cursor-pointer';
        });
        renderLabTable();
    };

    window.renderLabTable = function() {
        const q = (document.getElementById('lab-search')?.value || '').trim().toLowerCase();
        const sigs = allSignals.filter(s => {
            if (currentFilter === 'CLEAN' && !s.clean_3bar) return false;
            if (currentFilter === 'REVERSED' && s.clean_3bar) return false;
            if (currentFilter === 'LOSSES' && s.outcome !== 'LOSS') return false;
            if (q && !(`${s.date} ${s.time} ${s.dir} ${s.outcome}`.toLowerCase().includes(q))) return false;
            return true;
        });
        lastFiltered = sigs;

        const w = sigs.filter(s => s.outcome === 'WIN').length;
        const l = sigs.filter(s => s.outcome === 'LOSS').length;
        const wr = (w + l) > 0 ? Math.round((w / (w + l)) * 1000) / 10 : 0;
        const net = Math.round(sigs.reduce((a, s) => a + Number(s.net_pts || 0), 0) * 10) / 10;
        const avgMfe = sigs.length ? (sigs.reduce((a, s) => a + Number(s.mfe || 0), 0) / sigs.length).toFixed(1) : '0.0';
        const avgMae = sigs.length ? (sigs.reduce((a, s) => a + Number(s.mae || 0), 0) / sigs.length).toFixed(1) : '0.0';

        document.getElementById('lab-kpi-flt-wr').textContent = `${wr}% (${w}W/${l}L)`;
        const netEl = document.getElementById('lab-kpi-flt-net');
        netEl.textContent = `${net >= 0 ? '+' : ''}${net}p`;
        netEl.className = `text-xl font-black mt-1 ${net >= 0 ? 'text-emerald-400' : 'text-rose-400'}`;
        document.getElementById('lab-kpi-mfe').innerHTML = `<span class="text-emerald-400">+${avgMfe}p</span> / <span class="text-rose-400">-${avgMae}p</span>`;

        const tbody = document.getElementById('lab-tbody');
        if (sigs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" class="py-8 text-center text-gray-400">No matching signals found.</td></tr>';
            return;
        }

        const fmtBar = (b) => {
            if (!b) return '<span class="text-gray-600">--</span>';
            const mvCls = b.move >= 0 ? 'text-emerald-300' : 'text-rose-300';
            const revCls = b.is_rev ? 'bg-rose-950/70 border-rose-600/80 text-rose-200' : 'bg-[#12192b] border-gray-800 text-gray-300';
            return `<div class="rounded border px-1.5 py-0.5 text-[10.5px] ${revCls}">
                <div class="flex justify-between gap-1"><span class="text-gray-400">${b.time}</span><span class="${mvCls} font-bold">${b.move >= 0 ? '+' : ''}${b.move}p</span></div>
                <div class="text-[9.5px] text-gray-400">Same ${b.same_opt}% | <span class="${b.is_rev ? 'text-rose-300 font-black' : ''}">Rev ${b.opp_opt}%</span></div>
            </div>`;
        };

        tbody.innerHTML = sigs.map(s => {
            const dBadge = s.dir === 'BULL'
                ? '<span class="px-1.5 py-0.5 rounded bg-emerald-950 text-emerald-300 border border-emerald-700 font-black">▲ BULL</span>'
                : '<span class="px-1.5 py-0.5 rounded bg-rose-950 text-rose-300 border border-rose-700 font-black">▼ BEAR</span>';
            const oBadge = s.outcome === 'WIN'
                ? `<span class="px-2 py-0.5 rounded bg-emerald-950 text-emerald-300 border border-emerald-600 font-black">✅ +${s.net_pts}p</span>`
                : (s.outcome === 'LOSS'
                    ? `<span class="px-2 py-0.5 rounded bg-rose-950 text-rose-300 border border-rose-600 font-black">❌ ${s.net_pts}p</span>`
                    : `<span class="px-2 py-0.5 rounded bg-amber-950 text-amber-300 border border-amber-600 font-bold">⏱️ ${s.net_pts >= 0 ? '+' : ''}${s.net_pts}p</span>`);
            return `<tr class="hover:bg-white/[0.02]">
                <td class="py-2 px-2.5 whitespace-nowrap"><span class="font-bold text-white">${s.date}</span> <span class="text-sky-300 font-black">${s.time}</span></td>
                <td class="py-2 px-2.5">${dBadge}</td>
                <td class="py-2 px-2.5 text-right font-bold text-white">${s.spot.toFixed(2)}</td>
                <td class="py-2 px-2.5 text-right whitespace-nowrap text-[11px]"><span class="text-amber-300">${s.opt_pct}%</span> / <span class="text-sky-300">${s.stk_pct}%</span> / <span class="text-cyan-300">${s.oi_diff_l >= 0 ? '+' : ''}${s.oi_diff_l}L</span></td>
                <td class="py-1.5 px-2">${fmtBar(s.bars3?.[0])}</td>
                <td class="py-1.5 px-2">${fmtBar(s.bars3?.[1])}</td>
                <td class="py-1.5 px-2">${fmtBar(s.bars3?.[2])}</td>
                <td class="py-2 px-2.5 text-right whitespace-nowrap"><span class="text-emerald-400">+${s.mfe}p</span> / <span class="text-rose-400">-${s.mae}p</span></td>
                <td class="py-2 px-2.5 text-right whitespace-nowrap">${oBadge}</td>
                <td class="py-2 px-2.5 text-right"><a href="/nifty-multi-timeframe-monitor-v2?date=${s.date}&expiry=${s.expiry}" target="_blank" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-sky-300 border border-slate-700 text-[10.5px] font-bold">Chart ↗</a></td>
            </tr>`;
        }).join('');
    };

    window.exportLabCsv = function() {
        if (!lastFiltered.length) return;
        const headers = ['Date','Time','Dir','Spot','OPT%','STK%','OI_Diff_L','Clean_3Bar','Bar1_Move','Bar1_RevOPT','Bar2_Move','Bar2_RevOPT','Bar3_Move','Bar3_RevOPT','MFE','MAE','Outcome','NetPts'];
        const rows = lastFiltered.map(s => [
            s.date, s.time, s.dir, s.spot, s.opt_pct, s.stk_pct, s.oi_diff_l, s.clean_3bar ? 'YES' : 'NO',
            s.bars3?.[0]?.move ?? '', s.bars3?.[0]?.opp_opt ?? '',
            s.bars3?.[1]?.move ?? '', s.bars3?.[1]?.opp_opt ?? '',
            s.bars3?.[2]?.move ?? '', s.bars3?.[2]?.opp_opt ?? '',
            s.mfe, s.mae, s.outcome, s.net_pts
        ]);
        const csv = [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        a.download = `s4_3bar_lab_${currentFilter.toLowerCase()}.csv`;
        a.click();
    };

    document.addEventListener('DOMContentLoaded', () => { loadLabData(); });
})();
</script>
@endpush
