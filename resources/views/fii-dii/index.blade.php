@extends('layouts.app')

@section('title', 'FII & DII Activity — Institutional Flows')

@push('styles')
<style>
/* ── Reset & Base ─────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }

/* ── Page wrapper ─────────────────────────────────────── */
.fii-page { background: #f0f2f5; min-height: 100vh; }

/* ── Page Hero Banner ─────────────────────────────────── */
.page-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #1e3a5f 100%);
    padding: 1.25rem 1.5rem 1rem;
    margin: -0.5rem -0.5rem 1.25rem;
    border-bottom: 1px solid #334155;
}
.page-hero h1 { font-size: 1.25rem; font-weight: 800; color: #f1f5f9; letter-spacing: -.02em; }
.page-hero p  { font-size: .75rem; color: #94a3b8; margin-top: .15rem; }

/* ── Filter Bar ───────────────────────────────────────── */
.filter-bar { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; margin-top: .75rem; }
.filter-bar select,
.filter-bar input[type=date] {
    background: #1e293b; color: #e2e8f0; border: 1px solid #334155;
    border-radius: .5rem; padding: .35rem .75rem; font-size: .78rem;
    outline: none; transition: border-color .15s;
}
.filter-bar select:focus,
.filter-bar input[type=date]:focus { border-color: #6366f1; }
.btn-refresh {
    background: #6366f1; color: #fff; border: none; border-radius: .5rem;
    padding: .35rem 1rem; font-size: .78rem; font-weight: 700; cursor: pointer;
    transition: background .15s; letter-spacing: .02em;
}
.btn-refresh:hover { background: #4f46e5; }

/* ── KPI Cards ────────────────────────────────────────── */
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: .875rem; margin-bottom: 1.25rem; }
@media (max-width: 900px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 500px) { .kpi-grid { grid-template-columns: 1fr; } }

.kpi-card {
    background: #fff;
    border-radius: .875rem;
    padding: 1rem 1.25rem;
    box-shadow: 0 1px 4px rgba(0,0,0,.08), 0 4px 16px rgba(0,0,0,.04);
    border: 1px solid #e8ecf0;
    position: relative; overflow: hidden;
    transition: box-shadow .2s, transform .2s;
}
.kpi-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.12); transform: translateY(-2px); }
.kpi-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    border-radius: .875rem .875rem 0 0;
}
.kpi-card.fii::before  { background: linear-gradient(90deg, #6366f1, #8b5cf6); }
.kpi-card.dii::before  { background: linear-gradient(90deg, #f59e0b, #f97316); }
.kpi-card.comb::before { background: linear-gradient(90deg, #10b981, #059669); }
.kpi-card.mood::before { background: linear-gradient(90deg, #ec4899, #be185d); }

.kpi-label { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #94a3b8; }
.kpi-value { font-size: 1.5rem; font-weight: 900; color: #0f172a; margin: .25rem 0 .1rem; line-height: 1; }
.kpi-value.pos { color: #059669; }
.kpi-value.neg { color: #dc2626; }
.kpi-sub   { font-size: .72rem; color: #64748b; }
.kpi-icon  { position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); font-size: 2rem; opacity: .12; }

/* ── Section Card ─────────────────────────────────────── */
.section-card {
    background: #fff;
    border-radius: .875rem;
    box-shadow: 0 1px 4px rgba(0,0,0,.07), 0 4px 12px rgba(0,0,0,.04);
    border: 1px solid #e8ecf0;
    margin-bottom: 1.25rem;
    overflow: hidden;
}
.section-header {
    padding: .875rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; justify-content: space-between;
    background: #fafbfc;
}
.section-title { font-size: .825rem; font-weight: 800; color: #1e293b; letter-spacing: -.01em; }
.section-sub   { font-size: .68rem; color: #94a3b8; }

/* ── Charts ───────────────────────────────────────────── */
.chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .875rem; margin-bottom: 1.25rem; }
@media (max-width: 768px) { .chart-grid { grid-template-columns: 1fr; } }
.chart-wrap { position: relative; height: 260px; padding: 1rem; }

/* ── Pro Table ────────────────────────────────────────── */
.pro-table-wrap { overflow-x: auto; max-height: 480px; overflow-y: auto; }
.pro-table-wrap::-webkit-scrollbar { width: 5px; height: 5px; }
.pro-table-wrap::-webkit-scrollbar-track { background: #f8fafc; }
.pro-table-wrap::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

.pro-table { width: 100%; border-collapse: collapse; font-size: .775rem; }
.pro-table thead th {
    position: sticky; top: 0; z-index: 5;
    background: #1e293b; color: #94a3b8;
    font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em;
    padding: .6rem .9rem; white-space: nowrap;
    border-bottom: 2px solid #334155;
}
.pro-table thead th:first-child { color: #e2e8f0; border-radius: 0; }
.pro-table tbody td {
    padding: .5rem .9rem; border-bottom: 1px solid #f1f5f9;
    color: #334155; white-space: nowrap; transition: background .1s;
}
.pro-table tbody tr:hover td { background: #f8faff; }
.pro-table tbody tr:last-child td { border-bottom: none; }
.td-date { font-weight: 700; color: #0f172a !important; font-size: .76rem; }
.td-pos  { color: #059669 !important; font-weight: 700; }
.td-neg  { color: #dc2626 !important; font-weight: 700; }
.td-num  { text-align: right; font-variant-numeric: tabular-nums; }
.td-center { text-align: center; }

/* ── Badges ───────────────────────────────────────────── */
.badge {
    display: inline-block; font-size: .62rem; font-weight: 800;
    padding: .15rem .55rem; border-radius: 4px; letter-spacing: .04em; text-transform: uppercase;
}
.badge-bull { background: #d1fae5; color: #065f46; }
.badge-bear { background: #fee2e2; color: #991b1b; }
.badge-neut { background: #e2e8f0; color: #475569; }

/* ── Tabs ─────────────────────────────────────────────── */
.tab-strip { display: flex; gap: 0; border-bottom: 2px solid #f1f5f9; padding: 0 1.25rem; background: #fafbfc; overflow-x: auto; }
.tab-strip::-webkit-scrollbar { height: 3px; }
.tab-btn {
    padding: .7rem 1rem; font-size: .76rem; font-weight: 600; color: #64748b;
    border-bottom: 2px solid transparent; margin-bottom: -2px; cursor: pointer;
    white-space: nowrap; background: none; border-top: none; border-left: none; border-right: none;
    transition: color .15s, border-color .15s;
}
.tab-btn:hover { color: #6366f1; }
.tab-btn.active { color: #6366f1; border-bottom-color: #6366f1; font-weight: 800; }
.tab-panel { display: none; }
.tab-panel.active { display: block; }

/* ── Mini-stat grid ───────────────────────────────────── */
.mini-stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: .6rem; padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9; background: #fafbfc; }
.mini-stat { background: #fff; border: 1px solid #e8ecf0; border-radius: .625rem; padding: .625rem .875rem; }
.mini-stat-label { font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; margin-bottom: .25rem; }
.mini-stat-value { font-size: 1rem; font-weight: 900; color: #0f172a; }
.mini-stat-value.pos { color: #059669; }
.mini-stat-value.neg { color: #dc2626; }
.mini-stat-value.indigo { color: #6366f1; }
.mini-stat-value.amber  { color: #d97706; }
.mini-stat-value.violet { color: #7c3aed; }
.mini-stat-value.pink   { color: #be185d; }
.mini-stat-value.sky    { color: #0284c7; }

/* ── Flow bars ────────────────────────────────────────── */
.flow-section { padding: 1rem 1.25rem; }
.flow-row { margin-bottom: .875rem; }
.flow-date { font-size: .7rem; font-weight: 700; color: #64748b; margin-bottom: .3rem; text-transform: uppercase; letter-spacing: .04em; }
.flow-line { display: flex; align-items: center; gap: .5rem; margin-bottom: .2rem; }
.flow-label { font-size: .68rem; font-weight: 800; width: 28px; text-align: right; }
.flow-label.fii { color: #6366f1; }
.flow-label.dii { color: #d97706; }
.flow-track { flex: 1; background: #f1f5f9; border-radius: 4px; height: 14px; overflow: hidden; }
.flow-fill  { height: 100%; border-radius: 4px; transition: width .5s ease; }
.flow-amt   { font-size: .68rem; font-weight: 700; width: 90px; text-align: right; }

/* ── Divider ──────────────────────────────────────────── */
.divider { height: 1px; background: #f1f5f9; margin: 0 1.25rem; }

/* ── Error box ────────────────────────────────────────── */
.error-box { background: #fff5f5; border: 1px solid #fecaca; border-radius: .625rem; padding: .875rem 1.25rem; margin-bottom: 1rem; font-size: .8rem; color: #b91c1c; }
.error-box code { background: #fee2e2; padding: .1rem .3rem; border-radius: 3px; font-size: .72rem; }
</style>
@endpush

@section('content')
@php
    use Carbon\Carbon;

    // ── Amounts from Upstox FII/DII API are already in Crores ──
    function fmtCr($v, $sign = true) {
        $prefix = $sign ? ($v >= 0 ? '+' : '−') : '';
        return $prefix . '₹' . number_format(round(abs($v)), 0) . ' Cr';
    }
    function fmtCrPlain($v) {
        return '₹' . number_format(abs($v), 2) . ' Cr';
    }
    function fmtContracts($v) {
        $v = abs((int)$v);
        if ($v >= 1e7)  return number_format($v / 1e7, 2) . 'Cr';
        if ($v >= 1e5)  return number_format($v / 1e5, 2) . 'L';
        if ($v >= 1000) return number_format($v / 1000, 1) . 'K';
        return number_format($v);
    }

    $segLabels = [
        'NSE_FO|INDEX_FUTURES' => 'Index Futures',
        'NSE_FO|STOCK_FUTURES' => 'Stock Futures',
        'NSE_FO|INDEX_OPTIONS' => 'Index Options',
        'NSE_FO|STOCK_OPTIONS' => 'Stock Options',
        'NSE_EQ|CASH'          => 'Cash (Equity)',
    ];
    $segColors = [
        'NSE_FO|INDEX_FUTURES' => '#6366f1',
        'NSE_FO|STOCK_FUTURES' => '#8b5cf6',
        'NSE_FO|INDEX_OPTIONS' => '#ec4899',
        'NSE_FO|STOCK_OPTIONS' => '#f59e0b',
        'NSE_EQ|CASH'          => '#10b981',
    ];

    $latestFiiNet  = $latestFiiNet  ?? 0;
    $latestDiiNet  = $latestDiiNet  ?? 0;
    $combinedNet   = $latestFiiNet + $latestDiiNet;
    $fiiSentiment  = $latestFiiNet  > 0 ? 'Buying' : ($latestFiiNet  < 0 ? 'Selling' : 'Neutral');
    $diiSentiment  = $latestDiiNet  > 0 ? 'Buying' : ($latestDiiNet  < 0 ? 'Selling' : 'Neutral');
    $marketMood    = $combinedNet   > 0 ? 'Bullish' : ($combinedNet   < 0 ? 'Bearish' : 'Neutral');
    $moodEmoji     = $marketMood === 'Bullish' ? '🐂' : ($marketMood === 'Bearish' ? '🐻' : '⚖️');
@endphp

<div class="fii-page">

{{-- ═══ PAGE HERO ══════════════════════════════════════════════════════ --}}
<div class="page-hero">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:.75rem;">
        <div>
            <h1>
                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#22d3ee;margin-right:.5rem;animation:pulse-live 1.5s infinite;vertical-align:middle;"></span>
                FII &amp; DII Institutional Flows
            </h1>
            <p>Foreign &amp; Domestic Institutional Investor activity across all NSE segments · Powered by Upstox API</p>
        </div>
        <form method="GET" action="{{ route('fii-dii.index') }}" class="filter-bar">
            <select name="interval" onchange="this.form.submit()">
                <option value="1D" {{ $interval == '1D' ? 'selected' : '' }}>Daily (1D)</option>
                <option value="1M" {{ $interval == '1M' ? 'selected' : '' }}>Monthly (1M)</option>
            </select>
            <input type="date" name="from" value="{{ $from ?? '' }}" placeholder="From date">
            <button type="submit" class="btn-refresh">↻ Refresh</button>
        </form>
    </div>
    @if($latestDate)
    <div style="margin-top:.5rem;font-size:.7rem;color:#64748b;">
        Showing data as of <strong style="color:#94a3b8;">{{ $latestDate }}</strong>
    </div>
    @endif
</div>

{{-- ═══ ERRORS ══════════════════════════════════════════════════════════ --}}
@if($fiiError || $diiError)
<div class="error-box">
    @if($fiiError)<p><strong>FII API:</strong> {{ $fiiError }}</p>@endif
    @if($diiError)<p><strong>DII API:</strong> {{ $diiError }}</p>@endif
    <p style="margin-top:.4rem;font-size:.7rem;color:#9ca3af;">Check your token in <code>config/services.php → upstox.analytics_token</code></p>
</div>
@endif

{{-- ═══ KPI CARDS ═══════════════════════════════════════════════════════ --}}
<div class="kpi-grid">
    {{-- FII --}}
    <div class="kpi-card fii">
        <div class="kpi-icon">🌐</div>
        <div class="kpi-label">FII Net — All Segments</div>
        <div class="kpi-value {{ $latestFiiNet >= 0 ? 'pos' : 'neg' }}">{{ fmtCr($latestFiiNet) }}</div>
        <div class="kpi-sub">{{ $latestDate ?? '—' }} &nbsp;·&nbsp; {{ $fiiSentiment }}</div>
    </div>
    {{-- DII --}}
    <div class="kpi-card dii">
        <div class="kpi-icon">🏦</div>
        <div class="kpi-label">DII Net — Cash Segment</div>
        <div class="kpi-value {{ $latestDiiNet >= 0 ? 'pos' : 'neg' }}">{{ fmtCr($latestDiiNet) }}</div>
        <div class="kpi-sub">{{ $latestDate ?? '—' }} &nbsp;·&nbsp; {{ $diiSentiment }}</div>
    </div>
    {{-- Combined --}}
    <div class="kpi-card comb">
        <div class="kpi-icon">⚡</div>
        <div class="kpi-label">Combined Net Flow</div>
        <div class="kpi-value {{ $combinedNet >= 0 ? 'pos' : 'neg' }}">{{ fmtCr($combinedNet) }}</div>
        <div class="kpi-sub">FII + DII &nbsp;·&nbsp; {{ $latestDate ?? '—' }}</div>
    </div>
    {{-- Mood --}}
    <div class="kpi-card mood">
        <div class="kpi-icon">{{ $moodEmoji }}</div>
        <div class="kpi-label">Market Mood</div>
        <div class="kpi-value" style="color:{{ $marketMood === 'Bullish' ? '#059669' : ($marketMood === 'Bearish' ? '#dc2626' : '#64748b') }}">
            {{ $marketMood }}
        </div>
        <div class="kpi-sub">Based on institutional net flows</div>
    </div>
</div>

{{-- ═══ CHARTS ═══════════════════════════════════════════════════════════ --}}
<div class="chart-grid">
    {{-- Net Flow Bar --}}
    <div class="section-card">
        <div class="section-header">
            <div>
                <div class="section-title">Net Flow — FII vs DII</div>
                <div class="section-sub">Last 20 trading days (₹ Crores)</div>
            </div>
        </div>
        <div class="chart-wrap"><canvas id="netFlowChart"></canvas></div>
    </div>

    {{-- Segment Doughnut --}}
    <div class="section-card">
        <div class="section-header">
            <div>
                <div class="section-title">FII Buy Volume — Segment Split</div>
                <div class="section-sub">Latest day · proportion by segment</div>
            </div>
        </div>
        <div class="chart-wrap"><canvas id="segmentChart"></canvas></div>
    </div>
</div>

{{-- ═══ DAILY SUMMARY TABLE ═══════════════════════════════════════════════ --}}
<div class="section-card">
    <div class="section-header">
        <div>
            <div class="section-title">Daily FII + DII Summary</div>
            <div class="section-sub">All amounts in ₹ Crores &nbsp;·&nbsp; Net = Buy − Sell</div>
        </div>
        <span class="badge" style="background:#ede9fe;color:#6d28d9;font-size:.65rem;">{{ count($dailySummary) }} Days</span>
    </div>
    <div class="pro-table-wrap">
        <table class="pro-table">
            <thead>
                <tr>
                    <th style="text-align:left;">Date</th>
                    <th class="td-num" style="color:#a5b4fc;">FII Buy ₹Cr</th>
                    <th class="td-num" style="color:#fca5a5;">FII Sell ₹Cr</th>
                    <th class="td-num">FII Net</th>
                    <th class="td-num" style="color:#fcd34d;">DII Buy ₹Cr</th>
                    <th class="td-num" style="color:#fca5a5;">DII Sell ₹Cr</th>
                    <th class="td-num">DII Net</th>
                    <th class="td-num">Combined Net</th>
                    <th class="td-center">Mood</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dailySummary as $dk => $row)
                @php
                    $fn  = $row['fii_net'];
                    $dn  = $row['dii_net'];
                    $cn  = $row['combined_net'];
                    $badgeClass = $cn > 0 ? 'badge-bull' : ($cn < 0 ? 'badge-bear' : 'badge-neut');
                    $moodText   = $cn > 0 ? 'Bullish' : ($cn < 0 ? 'Bearish' : 'Neutral');
                @endphp
                <tr>
                    <td class="td-date">{{ $row['date'] }}</td>
                    <td class="td-num" style="color:#4f46e5;">{{ fmtCrPlain($row['fii_buy']) }}</td>
                    <td class="td-num" style="color:#dc2626;">{{ fmtCrPlain($row['fii_sell']) }}</td>
                    <td class="td-num {{ $fn >= 0 ? 'td-pos' : 'td-neg' }}">{{ fmtCr($fn) }}</td>
                    <td class="td-num" style="color:#d97706;">{{ fmtCrPlain($row['dii_buy']) }}</td>
                    <td class="td-num" style="color:#dc2626;">{{ fmtCrPlain($row['dii_sell']) }}</td>
                    <td class="td-num {{ $dn >= 0 ? 'td-pos' : 'td-neg' }}">{{ fmtCr($dn) }}</td>
                    <td class="td-num" style="font-size:.825rem;font-weight:900;{{ $cn >= 0 ? 'color:#059669' : 'color:#dc2626' }}">{{ fmtCr($cn) }}</td>
                    <td class="td-center"><span class="badge {{ $badgeClass }}">{{ $moodText }}</span></td>
                </tr>
                @empty
                <tr><td colspan="9" style="text-align:center;padding:2rem;color:#94a3b8;font-size:.8rem;">No data available. Data is available from 1st April 2026 onwards.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ═══ FII SEGMENT DEEP DIVE ═══════════════════════════════════════════ --}}
<div class="section-card">
    <div class="section-header">
        <div>
            <div class="section-title">🌐 FII — Segment Detail</div>
            <div class="section-sub">Switch tabs to view each segment independently</div>
        </div>
    </div>

    <div class="tab-strip" id="fiiTabStrip">
        @foreach($fiiData as $segment => $rows)
        @php $slug = Str::slug($segment); @endphp
        <button class="tab-btn {{ $loop->first ? 'active' : '' }}"
            id="ftab-{{ $slug }}"
            onclick="switchTab('{{ $slug }}')">
            {{ $segLabels[$segment] ?? $segment }}
        </button>
        @endforeach
    </div>

    @foreach($fiiData as $segment => $rows)
    @php
        $slug    = Str::slug($segment);
        $latest  = $rows[0] ?? null;
        $hasLong = $latest && isset($latest['total_long_contracts']) && ($latest['total_long_contracts'] > 0 || $latest['total_short_contracts'] > 0);
        $hasCall = $latest && isset($latest['total_call_long_contracts']) && $latest['total_call_long_contracts'] > 0;
    @endphp
    <div class="tab-panel {{ $loop->first ? 'active' : '' }}" id="fpanel-{{ $slug }}">

        {{-- Mini stats for latest day --}}
        @if($latest)
        <div class="mini-stats">
            <div class="mini-stat">
                <div class="mini-stat-label">Buy Amount</div>
                <div class="mini-stat-value indigo">{{ fmtCrPlain($latest['buy_amount'] ?? 0) }}</div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-label">Sell Amount</div>
                <div class="mini-stat-value neg">{{ fmtCrPlain($latest['sell_amount'] ?? 0) }}</div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-label">Net Amount</div>
                <div class="mini-stat-value {{ ($latest['net_amount'] ?? 0) >= 0 ? 'pos' : 'neg' }}">
                    {{ fmtCr($latest['net_amount'] ?? 0) }}
                </div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-label">OI Amount</div>
                <div class="mini-stat-value amber">{{ fmtCrPlain($latest['oi_amount'] ?? 0) }}</div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-label">Buy Contracts</div>
                <div class="mini-stat-value indigo">{{ fmtContracts($latest['buy_contracts'] ?? 0) }}</div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-label">Sell Contracts</div>
                <div class="mini-stat-value neg">{{ fmtContracts($latest['sell_contracts'] ?? 0) }}</div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-label">OI Contracts</div>
                <div class="mini-stat-value amber">{{ fmtContracts($latest['oi_contracts'] ?? 0) }}</div>
            </div>
            @if($hasLong)
            <div class="mini-stat">
                <div class="mini-stat-label">Long Contracts</div>
                <div class="mini-stat-value violet">{{ fmtContracts($latest['total_long_contracts']) }}</div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-label">Short Contracts</div>
                <div class="mini-stat-value pink">{{ fmtContracts($latest['total_short_contracts'] ?? 0) }}</div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-label">L/S Ratio</div>
                <div class="mini-stat-value sky">
                    {{ $latest['long_short_ratio'] !== null ? number_format($latest['long_short_ratio'], 2) : '—' }}
                </div>
            </div>
            @endif
            @if($hasCall)
            <div class="mini-stat">
                <div class="mini-stat-label">Call Long</div>
                <div class="mini-stat-value pos">{{ fmtContracts($latest['total_call_long_contracts']) }}</div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-label">Put Long</div>
                <div class="mini-stat-value indigo">{{ fmtContracts($latest['total_put_long_contracts'] ?? 0) }}</div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-label">Call Short</div>
                <div class="mini-stat-value neg">{{ fmtContracts($latest['total_call_short_contracts'] ?? 0) }}</div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-label">Put Short</div>
                <div class="mini-stat-value amber">{{ fmtContracts($latest['total_put_short_contracts'] ?? 0) }}</div>
            </div>
            @endif
        </div>
        @endif

        {{-- Detail table --}}
        <div class="pro-table-wrap">
            <table class="pro-table">
                <thead>
                    <tr>
                        <th style="text-align:left;">Date</th>
                        <th class="td-num">Buy ₹Cr</th>
                        <th class="td-num">Sell ₹Cr</th>
                        <th class="td-num">Net ₹Cr</th>
                        <th class="td-num">Buy Lots</th>
                        <th class="td-num">Sell Lots</th>
                        <th class="td-num">Net Lots</th>
                        <th class="td-num">OI Lots</th>
                        <th class="td-num">OI ₹Cr</th>
                        @if(isset($rows[0]['total_long_contracts']))
                        <th class="td-num">Long</th>
                        <th class="td-num">Short</th>
                        <th class="td-num">L/S</th>
                        @endif
                        @if(isset($rows[0]['total_call_long_contracts']) && ($rows[0]['total_call_long_contracts'] ?? 0) > 0)
                        <th class="td-num">Call L</th>
                        <th class="td-num">Put L</th>
                        <th class="td-num">Call S</th>
                        <th class="td-num">Put S</th>
                        @endif
                        <th class="td-center">Signal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    @php
                        $net = $row['net_amount'] ?? 0;
                        $netLots = $row['net_contracts'] ?? 0;
                        $showCall = isset($row['total_call_long_contracts']) && ($row['total_call_long_contracts'] ?? 0) > 0;
                    @endphp
                    <tr>
                        <td class="td-date">{{ $row['date'] }}</td>
                        <td class="td-num" style="color:#4f46e5;">{{ fmtCrPlain($row['buy_amount'] ?? 0) }}</td>
                        <td class="td-num" style="color:#dc2626;">{{ fmtCrPlain($row['sell_amount'] ?? 0) }}</td>
                        <td class="td-num {{ $net >= 0 ? 'td-pos' : 'td-neg' }}">{{ fmtCr($net) }}</td>
                        <td class="td-num">{{ fmtContracts($row['buy_contracts'] ?? 0) }}</td>
                        <td class="td-num">{{ fmtContracts($row['sell_contracts'] ?? 0) }}</td>
                        <td class="td-num {{ $netLots >= 0 ? 'td-pos' : 'td-neg' }}">
                            {{ ($netLots >= 0 ? '+' : '−') . fmtContracts($netLots) }}
                        </td>
                        <td class="td-num" style="color:#64748b;">{{ fmtContracts($row['oi_contracts'] ?? 0) }}</td>
                        <td class="td-num" style="color:#d97706;">{{ fmtCrPlain($row['oi_amount'] ?? 0) }}</td>
                        @if(isset($row['total_long_contracts']))
                        <td class="td-num" style="color:#7c3aed;">{{ fmtContracts($row['total_long_contracts']) }}</td>
                        <td class="td-num" style="color:#be185d;">{{ fmtContracts($row['total_short_contracts'] ?? 0) }}</td>
                        <td class="td-num" style="color:#0284c7;">{{ $row['long_short_ratio'] !== null ? number_format($row['long_short_ratio'], 2) : '—' }}</td>
                        @endif
                        @if($showCall)
                        <td class="td-num td-pos">{{ fmtContracts($row['total_call_long_contracts']) }}</td>
                        <td class="td-num" style="color:#4f46e5;">{{ fmtContracts($row['total_put_long_contracts'] ?? 0) }}</td>
                        <td class="td-num td-neg">{{ fmtContracts($row['total_call_short_contracts'] ?? 0) }}</td>
                        <td class="td-num" style="color:#d97706;">{{ fmtContracts($row['total_put_short_contracts'] ?? 0) }}</td>
                        @endif
                        <td class="td-center">
                            <span class="badge {{ $row['sentiment'] === 'bullish' ? 'badge-bull' : ($row['sentiment'] === 'bearish' ? 'badge-bear' : 'badge-neut') }}">
                                {{ $row['sentiment'] === 'bullish' ? 'Buy' : ($row['sentiment'] === 'bearish' ? 'Sell' : 'Flat') }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="17" style="text-align:center;padding:2rem;color:#94a3b8;font-size:.8rem;">No data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
</div>

{{-- ═══ DII DETAIL ═══════════════════════════════════════════════════════ --}}
<div class="section-card">
    <div class="section-header">
        <div>
            <div class="section-title">🏦 DII — Cash Segment Detail</div>
            <div class="section-sub">NSE_EQ|CASH · Domestic Institutional Activity</div>
        </div>
    </div>

    @foreach($diiData as $segment => $rows)
    @php $dLatest = $rows[0] ?? null; @endphp
    @if($dLatest)
    <div class="mini-stats">
        <div class="mini-stat">
            <div class="mini-stat-label">Buy Amount</div>
            <div class="mini-stat-value indigo">{{ fmtCrPlain($dLatest['buy_amount'] ?? 0) }}</div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-label">Sell Amount</div>
            <div class="mini-stat-value neg">{{ fmtCrPlain($dLatest['sell_amount'] ?? 0) }}</div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-label">Net Amount</div>
            <div class="mini-stat-value {{ ($dLatest['net_amount'] ?? 0) >= 0 ? 'pos' : 'neg' }}">
                {{ fmtCr($dLatest['net_amount'] ?? 0) }}
            </div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-label">Today's Signal</div>
            <div class="mini-stat-value {{ $dLatest['sentiment'] === 'bullish' ? 'pos' : ($dLatest['sentiment'] === 'bearish' ? 'neg' : '') }}">
                {{ $dLatest['sentiment'] === 'bullish' ? '🐂 Buying' : ($dLatest['sentiment'] === 'bearish' ? '🐻 Selling' : '⚖️ Neutral') }}
            </div>
        </div>
    </div>
    @endif

    <div class="pro-table-wrap">
        <table class="pro-table">
            <thead>
                <tr>
                    <th style="text-align:left;">Date</th>
                    <th class="td-num">Buy ₹Cr</th>
                    <th class="td-num">Sell ₹Cr</th>
                    <th class="td-num">Net ₹Cr</th>
                    <th class="td-num">Buy Contracts</th>
                    <th class="td-num">Sell Contracts</th>
                    <th class="td-num">OI ₹Cr</th>
                    <th class="td-center">Signal</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                @php $net = $row['net_amount'] ?? 0; @endphp
                <tr>
                    <td class="td-date">{{ $row['date'] }}</td>
                    <td class="td-num" style="color:#4f46e5;">{{ fmtCrPlain($row['buy_amount'] ?? 0) }}</td>
                    <td class="td-num" style="color:#dc2626;">{{ fmtCrPlain($row['sell_amount'] ?? 0) }}</td>
                    <td class="td-num {{ $net >= 0 ? 'td-pos' : 'td-neg' }}">{{ fmtCr($net) }}</td>
                    <td class="td-num" style="color:#64748b;">{{ fmtContracts($row['buy_contracts'] ?? 0) }}</td>
                    <td class="td-num" style="color:#64748b;">{{ fmtContracts($row['sell_contracts'] ?? 0) }}</td>
                    <td class="td-num" style="color:#d97706;">{{ fmtCrPlain($row['oi_amount'] ?? 0) }}</td>
                    <td class="td-center">
                        <span class="badge {{ $row['sentiment'] === 'bullish' ? 'badge-bull' : ($row['sentiment'] === 'bearish' ? 'badge-bear' : 'badge-neut') }}">
                            {{ $row['sentiment'] === 'bullish' ? 'Buy' : ($row['sentiment'] === 'bearish' ? 'Sell' : 'Flat') }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;padding:2rem;color:#94a3b8;font-size:.8rem;">No data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endforeach
</div>

{{-- ═══ VISUAL FLOW BARS ═══════════════════════════════════════════════════ --}}
@if(count($dailySummary) > 0)
<div class="section-card">
    <div class="section-header">
        <div>
            <div class="section-title">Flow Intensity — FII vs DII</div>
            <div class="section-sub">Last 15 sessions · bar width = relative magnitude · amount = net ₹Cr</div>
        </div>
    </div>
    <div class="flow-section">
        @php
            $maxAbs = collect($dailySummary)->map(fn($r) => max(abs($r['fii_net']), abs($r['dii_net'])))->max() ?: 1;
        @endphp
        @foreach(array_slice($dailySummary, 0, 15) as $dk => $row)
        @php
            $fPct   = min(100, round(abs($row['fii_net']) / $maxAbs * 100));
            $dPct   = min(100, round(abs($row['dii_net']) / $maxAbs * 100));
            $fColor = $row['fii_net'] >= 0 ? '#6366f1' : '#ef4444';
            $dColor = $row['dii_net'] >= 0 ? '#f59e0b' : '#f87171';
        @endphp
        <div class="flow-row">
            <div class="flow-date">{{ $row['date'] }}</div>
            <div class="flow-line">
                <span class="flow-label fii">FII</span>
                <div class="flow-track">
                    <div class="flow-fill" style="width:{{ $fPct }}%;background:{{ $fColor }};"></div>
                </div>
                <span class="flow-amt {{ $row['fii_net'] >= 0 ? 'td-pos' : 'td-neg' }}">{{ fmtCr($row['fii_net']) }}</span>
            </div>
            <div class="flow-line">
                <span class="flow-label dii">DII</span>
                <div class="flow-track">
                    <div class="flow-fill" style="width:{{ $dPct }}%;background:{{ $dColor }};"></div>
                </div>
                <span class="flow-amt {{ $row['dii_net'] >= 0 ? 'td-pos' : 'td-neg' }}">{{ fmtCr($row['dii_net']) }}</span>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

</div>{{-- /fii-page --}}
@endsection

@push('scripts')
<style>
@keyframes pulse-live {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: .4; transform: scale(1.3); }
}
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const dailySummary = @json(array_values($dailySummary));
const fiiData      = @json($fiiData);
const segColors    = @json($segColors);
const segLabels    = @json($segLabels);

Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
Chart.defaults.color = '#64748b';

/* ── 1. Net Flow Bar Chart ─────────────────────────────────────────── */
(function () {
    const slice   = dailySummary.slice(0, 20).reverse();
    const labels  = slice.map(r => r.date);
    const fiiNets = slice.map(r => +parseFloat(r.fii_net).toFixed(2));
    const diiNets = slice.map(r => +parseFloat(r.dii_net).toFixed(2));

    new Chart(document.getElementById('netFlowChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'FII Net (₹Cr)',
                    data: fiiNets,
                    backgroundColor: fiiNets.map(v => v >= 0 ? 'rgba(99,102,241,.85)' : 'rgba(239,68,68,.85)'),
                    borderRadius: 3,
                    borderSkipped: false,
                    barPercentage: .7,
                },
                {
                    label: 'DII Net (₹Cr)',
                    data: diiNets,
                    backgroundColor: diiNets.map(v => v >= 0 ? 'rgba(245,158,11,.85)' : 'rgba(248,113,113,.85)'),
                    borderRadius: 3,
                    borderSkipped: false,
                    barPercentage: .7,
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { boxWidth: 10, font: { size: 11 } } },
                tooltip: {
                    backgroundColor: '#1e293b', titleColor: '#e2e8f0', bodyColor: '#94a3b8',
                    padding: 10, cornerRadius: 8,
                    callbacks: { label: ctx => ` ${ctx.dataset.label}: ₹${ctx.parsed.y.toLocaleString()} Cr` }
                }
            },
            scales: {
                x: {
                    ticks: { font: { size: 10 }, maxRotation: 45 },
                    grid: { display: false }
                },
                y: {
                    ticks: { font: { size: 10 }, callback: v => '₹' + v.toLocaleString() + ' Cr' },
                    grid: { color: 'rgba(0,0,0,.05)' }
                }
            }
        }
    });
})();

/* ── 2. Segment Doughnut ────────────────────────────────────────────── */
(function () {
    const segs   = Object.keys(fiiData);
    const values = segs.map(s => {
        const rows = fiiData[s];
        return rows.length ? Math.abs(parseFloat(rows[0].buy_amount)) : 0;
    });
    const colors = segs.map(s => segColors[s] || '#6366f1');
    const names  = segs.map(s => segLabels[s] || s);
    const total  = values.reduce((a, b) => a + b, 0);

    new Chart(document.getElementById('segmentChart'), {
        type: 'doughnut',
        data: {
            labels: names,
            datasets: [{
                data: values,
                backgroundColor: colors,
                borderWidth: 3,
                borderColor: '#ffffff',
                hoverOffset: 8,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'right',
                    labels: { boxWidth: 12, font: { size: 11 }, padding: 12 }
                },
                tooltip: {
                    backgroundColor: '#1e293b', titleColor: '#e2e8f0', bodyColor: '#94a3b8',
                    padding: 10, cornerRadius: 8,
                    callbacks: {
                        label: ctx => {
                            const pct = total > 0 ? ((ctx.raw / total) * 100).toFixed(1) : 0;
                            return ` ₹${parseFloat(ctx.raw).toFixed(0)} Cr (${pct}%)`;
                        }
                    }
                }
            }
        }
    });
})();

/* ── Tab switcher ───────────────────────────────────────────────────── */
function switchTab(slug) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    const panel = document.getElementById('fpanel-' + slug);
    const btn   = document.getElementById('ftab-' + slug);
    if (panel) panel.classList.add('active');
    if (btn)   btn.classList.add('active');
}
</script>
@endpush
