@extends('layouts.app')

@section('title', 'FII & DII Institutional Flows')

@push('styles')
<style>
    /* Only non-Tailwind things: sticky table header bg, scrollbar, animation */
    .tbl-wrap { max-height: 460px; overflow-y: auto; overflow-x: auto; }
    .tbl-wrap::-webkit-scrollbar { width: 4px; height: 4px; }
    .tbl-wrap::-webkit-scrollbar-track { background: #f8fafc; }
    .tbl-wrap::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .th-sticky { position: sticky; top: 0; z-index: 10; }
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }
    @keyframes live-pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50%       { opacity: .3; transform: scale(1.5); }
    }
    .live-dot { animation: live-pulse 1.6s infinite; }
    .bar-fill { transition: width .5s ease; }
    .kpi-card-top::before {
        content: ''; display: block; height: 3px; border-radius: 12px 12px 0 0;
        margin: -1px -1px 0;
    }
    .kpi-fii::before   { background: linear-gradient(90deg,#6366f1,#8b5cf6); }
    .kpi-dii::before   { background: linear-gradient(90deg,#f59e0b,#f97316); }
    .kpi-comb::before  { background: linear-gradient(90deg,#10b981,#059669); }
    .kpi-mood::before  { background: linear-gradient(90deg,#ec4899,#be185d); }
</style>
@endpush

@section('content')
@php
    use Carbon\Carbon;

    // API amounts are already in Crores — NO division needed
    function fmtCr($v, bool $sign = true): string {
        $s = $sign ? ($v >= 0 ? '+' : '−') : '';
        return $s . '₹' . number_format((int)round(abs($v))) . ' Cr';
    }
    function fmtCrPlain($v): string {
        return '₹' . number_format(abs((float)$v), 2) . ' Cr';
    }
    function fmtLots($v): string {
        $v = abs((int)$v);
        if ($v >= 1_00_00_000) return number_format($v / 1_00_00_000, 2) . ' Cr';
        if ($v >= 1_00_000)    return number_format($v / 1_00_000, 2) . ' L';
        if ($v >= 1_000)       return number_format($v / 1_000, 1) . ' K';
        return number_format($v);
    }

    $segLabels = [
        'NSE_FO|INDEX_FUTURES' => 'Index Fut',
        'NSE_FO|STOCK_FUTURES' => 'Stock Fut',
        'NSE_FO|INDEX_OPTIONS' => 'Index Opt',
        'NSE_FO|STOCK_OPTIONS' => 'Stock Opt',
        'NSE_EQ|CASH'          => 'Cash',
    ];
    $segColors = [
        'NSE_FO|INDEX_FUTURES' => '#6366f1',
        'NSE_FO|STOCK_FUTURES' => '#8b5cf6',
        'NSE_FO|INDEX_OPTIONS' => '#ec4899',
        'NSE_FO|STOCK_OPTIONS' => '#f59e0b',
        'NSE_EQ|CASH'          => '#10b981',
    ];

    $latestFiiNet = $latestFiiNet ?? 0;
    $latestDiiNet = $latestDiiNet ?? 0;
    $combinedNet  = $latestFiiNet + $latestDiiNet;
    $marketMood   = $combinedNet > 0 ? 'Bullish' : ($combinedNet < 0 ? 'Bearish' : 'Neutral');
    $moodEmoji    = match($marketMood) { 'Bullish' => '🐂', 'Bearish' => '🐻', default => '⚖️' };
@endphp

{{-- ═══════════ HERO BANNER ═══════════════════════════════════════════ --}}
<div class="bg-slate-900 -mx-2 -mt-2 px-6 py-4 mb-5" style="border-bottom:1px solid #1e293b;">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-lg font-extrabold text-slate-100 flex items-center gap-2 tracking-tight">
                <span class="live-dot inline-block w-2.5 h-2.5 rounded-full bg-cyan-400"></span>
                FII &amp; DII Institutional Flows
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Foreign &amp; Domestic Institutional activity · All NSE segments · Upstox API
            </p>
        </div>

        {{-- Filter --}}
        <form method="GET" action="{{ route('fii-dii.index') }}" class="flex flex-wrap items-center gap-2">
            <select name="interval" onchange="this.form.submit()"
                class="bg-slate-800 text-slate-300 border border-slate-700 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:border-indigo-500">
                <option value="1D" {{ $interval == '1D' ? 'selected' : '' }}>Daily (1D)</option>
                <option value="1M" {{ $interval == '1M' ? 'selected' : '' }}>Monthly (1M)</option>
            </select>
            <input type="date" name="from" value="{{ $from ?? '' }}"
                class="bg-slate-800 text-slate-300 border border-slate-700 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:border-indigo-500">
            <button type="submit"
                class="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold px-4 py-1.5 rounded-lg transition-colors">
                ↻ Refresh
            </button>
        </form>
    </div>
    @if($latestDate)
    <p class="text-xs text-slate-600 mt-2">As of <span class="text-slate-400 font-semibold">{{ $latestDate }}</span></p>
    @endif
</div>

{{-- ═══════════ ERRORS ════════════════════════════════════════════════ --}}
@if($fiiError || $diiError)
<div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-4 text-xs text-red-700">
    @if($fiiError)<p><span class="font-bold">FII API:</span> {{ $fiiError }}</p>@endif
    @if($diiError)<p><span class="font-bold">DII API:</span> {{ $diiError }}</p>@endif
    <p class="mt-1 text-red-400">Verify <code class="bg-red-100 px-1 rounded">config/services.php → upstox.analytics_token</code></p>
</div>
@endif

{{-- ═══════════ KPI CARDS ══════════════════════════════════════════════ --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    @php
        $kpis = [
            ['class'=>'kpi-fii',  'label'=>'FII Net — All Segments', 'val'=>$latestFiiNet, 'sub'=>($latestDate??'—').' · '.($latestFiiNet>=0?'Buying':'Selling'), 'icon'=>'🌐'],
            ['class'=>'kpi-dii',  'label'=>'DII Net — Cash',         'val'=>$latestDiiNet, 'sub'=>($latestDate??'—').' · '.($latestDiiNet>=0?'Buying':'Selling'), 'icon'=>'🏦'],
            ['class'=>'kpi-comb', 'label'=>'Combined Net Flow',       'val'=>$combinedNet,  'sub'=>'FII + DII · '.($latestDate??'—'),                              'icon'=>'⚡'],
            ['class'=>'kpi-mood', 'label'=>'Market Mood',             'val'=>null,          'sub'=>'Based on net institutional flows',                              'icon'=>$moodEmoji, 'text'=>$marketMood],
        ];
    @endphp
    @foreach($kpis as $kpi)
    <div class="kpi-card-top {{ $kpi['class'] }} bg-white rounded-xl border border-slate-200 shadow-sm p-4 relative overflow-hidden hover:-translate-y-0.5 hover:shadow-md transition-all">
        <div class="absolute right-3 top-1/2 -translate-y-1/2 text-3xl opacity-10 select-none">{{ $kpi['icon'] }}</div>
        <p class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-1">{{ $kpi['label'] }}</p>
        @if(isset($kpi['text']))
            <p class="text-2xl font-black {{ $marketMood==='Bullish'?'text-emerald-600':($marketMood==='Bearish'?'text-red-600':'text-slate-500') }}">{{ $kpi['text'] }}</p>
        @else
            <p class="text-2xl font-black {{ $kpi['val']>=0?'text-emerald-600':'text-red-600' }}">{{ fmtCr($kpi['val']) }}</p>
        @endif
        <p class="text-xs text-slate-400 mt-0.5">{{ $kpi['sub'] }}</p>
    </div>
    @endforeach
</div>

{{-- ═══════════ CHARTS ═════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-5">
    {{-- Bar Chart --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 bg-slate-50">
            <div>
                <p class="text-sm font-bold text-slate-800">Net Flow — FII vs DII</p>
                <p class="text-xs text-slate-400">Last 20 sessions · ₹ Crores</p>
            </div>
        </div>
        <div class="relative p-4" style="height:270px;">
            <canvas id="netFlowChart"></canvas>
        </div>
    </div>

    {{-- Doughnut --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 bg-slate-50">
            <div>
                <p class="text-sm font-bold text-slate-800">FII Buy Volume — Segment Split</p>
                <p class="text-xs text-slate-400">Latest day · proportion by segment</p>
            </div>
        </div>
        <div class="relative p-4" style="height:270px;">
            <canvas id="segmentChart"></canvas>
        </div>
    </div>
</div>

{{-- ═══════════ DAILY SUMMARY TABLE ═══════════════════════════════════ --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm mb-5 overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 bg-slate-50">
        <div>
            <p class="text-sm font-bold text-slate-800">📅 Daily FII + DII Summary</p>
            <p class="text-xs text-slate-400">All amounts in ₹ Crores · Net = Buy − Sell</p>
        </div>
        <span class="inline-block bg-violet-100 text-violet-700 text-xs font-bold px-2 py-0.5 rounded-full">{{ count($dailySummary) }} days</span>
    </div>
    <div class="tbl-wrap">
        <table class="w-full text-xs border-collapse">
            <thead>
                <tr class="th-sticky bg-slate-800 text-slate-400">
                    <th class="text-left px-4 py-2.5 font-bold uppercase tracking-wider text-slate-300 whitespace-nowrap">Date</th>
                    <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap text-indigo-400">FII Buy</th>
                    <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap text-red-400">FII Sell</th>
                    <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap">FII Net</th>
                    <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap text-amber-400">DII Buy</th>
                    <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap text-red-400">DII Sell</th>
                    <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap">DII Net</th>
                    <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap">Combined Net</th>
                    <th class="text-center px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap">Mood</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($dailySummary as $dk => $row)
                @php
                    $fn = $row['fii_net']; $dn = $row['dii_net']; $cn = $row['combined_net'];
                    $moodCls = $cn > 0 ? 'bg-emerald-100 text-emerald-700' : ($cn < 0 ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-500');
                    $moodTxt = $cn > 0 ? 'Bullish' : ($cn < 0 ? 'Bearish' : 'Neutral');
                @endphp
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-4 py-2 font-bold text-slate-800 whitespace-nowrap">{{ $row['date'] }}</td>
                    <td class="px-3 py-2 text-right text-indigo-600 whitespace-nowrap tabular-nums">{{ fmtCrPlain($row['fii_buy']) }}</td>
                    <td class="px-3 py-2 text-right text-red-500 whitespace-nowrap tabular-nums">{{ fmtCrPlain($row['fii_sell']) }}</td>
                    <td class="px-3 py-2 text-right font-bold whitespace-nowrap tabular-nums {{ $fn>=0?'text-emerald-600':'text-red-600' }}">{{ fmtCr($fn) }}</td>
                    <td class="px-3 py-2 text-right text-amber-600 whitespace-nowrap tabular-nums">{{ fmtCrPlain($row['dii_buy']) }}</td>
                    <td class="px-3 py-2 text-right text-red-500 whitespace-nowrap tabular-nums">{{ fmtCrPlain($row['dii_sell']) }}</td>
                    <td class="px-3 py-2 text-right font-bold whitespace-nowrap tabular-nums {{ $dn>=0?'text-emerald-600':'text-red-600' }}">{{ fmtCr($dn) }}</td>
                    <td class="px-3 py-2 text-right font-black text-sm whitespace-nowrap tabular-nums {{ $cn>=0?'text-emerald-600':'text-red-600' }}">{{ fmtCr($cn) }}</td>
                    <td class="px-3 py-2 text-center whitespace-nowrap">
                        <span class="inline-block text-xs font-bold px-2 py-0.5 rounded {{ $moodCls }}">{{ $moodTxt }}</span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center py-12 text-slate-400">No data available. Data starts from 1st April 2026.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ═══════════ FII SEGMENT DEEP DIVE ═══════════════════════════════════ --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm mb-5 overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 bg-slate-50">
        <p class="text-sm font-bold text-slate-800">🌐 FII — Segment Detail</p>
        <p class="text-xs text-slate-400">Click a segment tab to explore</p>
    </div>

    {{-- Tabs --}}
    <div class="flex overflow-x-auto border-b border-slate-100 bg-white">
        @foreach($fiiData as $segment => $rows)
        @php $slug = Str::slug($segment); @endphp
        <button id="ftab-{{ $slug }}"
            onclick="switchTab('{{ $slug }}')"
            class="tab-btn flex-shrink-0 px-4 py-2.5 text-xs font-semibold text-slate-500 border-b-2 border-transparent hover:text-indigo-600 hover:border-indigo-300 transition-all whitespace-nowrap focus:outline-none {{ $loop->first ? 'text-indigo-600 border-indigo-600 font-bold' : '' }}">
            {{ $segLabels[$segment] ?? $segment }}
        </button>
        @endforeach
    </div>

    {{-- Panels --}}
    @foreach($fiiData as $segment => $rows)
    @php
        $slug    = Str::slug($segment);
        $latest  = $rows[0] ?? null;
        $hasLong = $latest && (($latest['total_long_contracts'] ?? 0) > 0 || ($latest['total_short_contracts'] ?? 0) > 0);
        $hasCall = $latest && ($latest['total_call_long_contracts'] ?? 0) > 0;
    @endphp
    <div id="fpanel-{{ $slug }}" class="tab-panel {{ $loop->first ? 'active' : '' }}">

        {{-- Mini stats --}}
        @if($latest)
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2 p-4 border-b border-slate-100 bg-slate-50">
            @php
                $stats = [
                    ['label'=>'Buy Amount',    'val'=>fmtCrPlain($latest['buy_amount']??0),            'color'=>'text-indigo-600'],
                    ['label'=>'Sell Amount',   'val'=>fmtCrPlain($latest['sell_amount']??0),           'color'=>'text-red-600'],
                    ['label'=>'Net Amount',    'val'=>fmtCr($latest['net_amount']??0),                 'color'=>($latest['net_amount']??0)>=0?'text-emerald-600':'text-red-600'],
                    ['label'=>'OI Amount',     'val'=>fmtCrPlain($latest['oi_amount']??0),             'color'=>'text-amber-600'],
                    ['label'=>'Buy Lots',      'val'=>fmtLots($latest['buy_contracts']??0),            'color'=>'text-indigo-600'],
                    ['label'=>'Sell Lots',     'val'=>fmtLots($latest['sell_contracts']??0),           'color'=>'text-red-600'],
                    ['label'=>'OI Lots',       'val'=>fmtLots($latest['oi_contracts']??0),             'color'=>'text-amber-600'],
                ];
                if ($hasLong) {
                    $stats[] = ['label'=>'Long Contracts', 'val'=>fmtLots($latest['total_long_contracts']??0),  'color'=>'text-violet-600'];
                    $stats[] = ['label'=>'Short Contracts','val'=>fmtLots($latest['total_short_contracts']??0), 'color'=>'text-pink-600'];
                    $stats[] = ['label'=>'L/S Ratio',      'val'=>$latest['long_short_ratio']!==null?number_format($latest['long_short_ratio'],2):'—','color'=>'text-sky-600'];
                }
                if ($hasCall) {
                    $stats[] = ['label'=>'Call Long',  'val'=>fmtLots($latest['total_call_long_contracts']??0),  'color'=>'text-emerald-600'];
                    $stats[] = ['label'=>'Put Long',   'val'=>fmtLots($latest['total_put_long_contracts']??0),   'color'=>'text-indigo-600'];
                    $stats[] = ['label'=>'Call Short', 'val'=>fmtLots($latest['total_call_short_contracts']??0), 'color'=>'text-red-600'];
                    $stats[] = ['label'=>'Put Short',  'val'=>fmtLots($latest['total_put_short_contracts']??0),  'color'=>'text-amber-600'];
                }
            @endphp
            @foreach($stats as $st)
            <div class="bg-white border border-slate-200 rounded-lg px-3 py-2.5">
                <p class="text-xs text-slate-400 font-semibold uppercase tracking-wider mb-1">{{ $st['label'] }}</p>
                <p class="text-sm font-extrabold {{ $st['color'] }} tabular-nums">{{ $st['val'] }}</p>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Table --}}
        <div class="tbl-wrap">
            <table class="w-full text-xs border-collapse">
                <thead>
                    <tr class="th-sticky bg-slate-800 text-slate-400">
                        <th class="text-left px-4 py-2.5 font-bold uppercase tracking-wider text-slate-300 whitespace-nowrap">Date</th>
                        <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap text-indigo-400">Buy ₹Cr</th>
                        <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap text-red-400">Sell ₹Cr</th>
                        <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap">Net ₹Cr</th>
                        <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap">Buy Lots</th>
                        <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap">Sell Lots</th>
                        <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap">Net Lots</th>
                        <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap text-amber-400">OI Lots</th>
                        <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap text-amber-400">OI ₹Cr</th>
                        @if(isset($rows[0]['total_long_contracts']))
                        <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap text-violet-400">Long</th>
                        <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap text-pink-400">Short</th>
                        <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap text-sky-400">L/S</th>
                        @endif
                        @if(isset($rows[0]['total_call_long_contracts']) && ($rows[0]['total_call_long_contracts']??0) > 0)
                        <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap">CL</th>
                        <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap">PL</th>
                        <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap">CS</th>
                        <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap">PS</th>
                        @endif
                        <th class="text-center px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap">Signal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                    @php
                        $net  = $row['net_amount'] ?? 0;
                        $nLot = $row['net_contracts'] ?? 0;
                        $sc   = isset($row['total_call_long_contracts']) && ($row['total_call_long_contracts']??0) > 0;
                        $sentCls = $row['sentiment']==='bullish'?'bg-emerald-100 text-emerald-700':($row['sentiment']==='bearish'?'bg-red-100 text-red-700':'bg-slate-100 text-slate-500');
                        $sentTxt = $row['sentiment']==='bullish'?'Buy':($row['sentiment']==='bearish'?'Sell':'Flat');
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-2 font-bold text-slate-800 whitespace-nowrap">{{ $row['date'] }}</td>
                        <td class="px-3 py-2 text-right text-indigo-600 tabular-nums whitespace-nowrap">{{ fmtCrPlain($row['buy_amount']??0) }}</td>
                        <td class="px-3 py-2 text-right text-red-500 tabular-nums whitespace-nowrap">{{ fmtCrPlain($row['sell_amount']??0) }}</td>
                        <td class="px-3 py-2 text-right font-bold tabular-nums whitespace-nowrap {{ $net>=0?'text-emerald-600':'text-red-600' }}">{{ fmtCr($net) }}</td>
                        <td class="px-3 py-2 text-right text-slate-600 tabular-nums whitespace-nowrap">{{ fmtLots($row['buy_contracts']??0) }}</td>
                        <td class="px-3 py-2 text-right text-slate-600 tabular-nums whitespace-nowrap">{{ fmtLots($row['sell_contracts']??0) }}</td>
                        <td class="px-3 py-2 text-right font-semibold tabular-nums whitespace-nowrap {{ $nLot>=0?'text-emerald-600':'text-red-600' }}">{{ ($nLot>=0?'+':'−').fmtLots($nLot) }}</td>
                        <td class="px-3 py-2 text-right text-amber-600 tabular-nums whitespace-nowrap">{{ fmtLots($row['oi_contracts']??0) }}</td>
                        <td class="px-3 py-2 text-right text-amber-600 tabular-nums whitespace-nowrap">{{ fmtCrPlain($row['oi_amount']??0) }}</td>
                        @if(isset($row['total_long_contracts']))
                        <td class="px-3 py-2 text-right text-violet-600 tabular-nums whitespace-nowrap">{{ fmtLots($row['total_long_contracts']) }}</td>
                        <td class="px-3 py-2 text-right text-pink-600 tabular-nums whitespace-nowrap">{{ fmtLots($row['total_short_contracts']??0) }}</td>
                        <td class="px-3 py-2 text-right text-sky-600 font-semibold tabular-nums whitespace-nowrap">{{ $row['long_short_ratio']!==null?number_format($row['long_short_ratio'],2):'—' }}</td>
                        @endif
                        @if($sc)
                        <td class="px-3 py-2 text-right text-emerald-600 tabular-nums whitespace-nowrap">{{ fmtLots($row['total_call_long_contracts']) }}</td>
                        <td class="px-3 py-2 text-right text-indigo-600 tabular-nums whitespace-nowrap">{{ fmtLots($row['total_put_long_contracts']??0) }}</td>
                        <td class="px-3 py-2 text-right text-red-600 tabular-nums whitespace-nowrap">{{ fmtLots($row['total_call_short_contracts']??0) }}</td>
                        <td class="px-3 py-2 text-right text-amber-600 tabular-nums whitespace-nowrap">{{ fmtLots($row['total_put_short_contracts']??0) }}</td>
                        @endif
                        <td class="px-3 py-2 text-center whitespace-nowrap">
                            <span class="inline-block text-xs font-bold px-2 py-0.5 rounded {{ $sentCls }}">{{ $sentTxt }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="20" class="text-center py-10 text-slate-400">No data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
</div>

{{-- ═══════════ DII DETAIL ═══════════════════════════════════════════ --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm mb-5 overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 bg-slate-50">
        <p class="text-sm font-bold text-slate-800">🏦 DII — Cash Segment Detail</p>
        <p class="text-xs text-slate-400">NSE_EQ|CASH · Domestic Institutional flows</p>
    </div>

    @foreach($diiData as $segment => $rows)
    @php $dl = $rows[0] ?? null; @endphp

    @if($dl)
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 p-4 border-b border-slate-100 bg-slate-50">
        @php
            $dStats = [
                ['label'=>'Buy Amount',  'val'=>fmtCrPlain($dl['buy_amount']??0),  'color'=>'text-indigo-600'],
                ['label'=>'Sell Amount', 'val'=>fmtCrPlain($dl['sell_amount']??0), 'color'=>'text-red-600'],
                ['label'=>'Net Amount',  'val'=>fmtCr($dl['net_amount']??0),       'color'=>($dl['net_amount']??0)>=0?'text-emerald-600':'text-red-600'],
                ['label'=>'Today Signal','val'=>$dl['sentiment']==='bullish'?'🐂 Buying':($dl['sentiment']==='bearish'?'🐻 Selling':'⚖️ Neutral'), 'color'=>$dl['sentiment']==='bullish'?'text-emerald-600':($dl['sentiment']==='bearish'?'text-red-600':'text-slate-500')],
            ];
        @endphp
        @foreach($dStats as $st)
        <div class="bg-white border border-slate-200 rounded-lg px-3 py-2.5">
            <p class="text-xs text-slate-400 font-semibold uppercase tracking-wider mb-1">{{ $st['label'] }}</p>
            <p class="text-sm font-extrabold {{ $st['color'] }} tabular-nums">{{ $st['val'] }}</p>
        </div>
        @endforeach
    </div>
    @endif

    <div class="tbl-wrap">
        <table class="w-full text-xs border-collapse">
            <thead>
                <tr class="th-sticky bg-slate-800 text-slate-400">
                    <th class="text-left px-4 py-2.5 font-bold uppercase tracking-wider text-slate-300 whitespace-nowrap">Date</th>
                    <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap text-indigo-400">Buy ₹Cr</th>
                    <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap text-red-400">Sell ₹Cr</th>
                    <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap">Net ₹Cr</th>
                    <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap">Buy Lots</th>
                    <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap">Sell Lots</th>
                    <th class="text-right px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap text-amber-400">OI ₹Cr</th>
                    <th class="text-center px-3 py-2.5 font-bold uppercase tracking-wider whitespace-nowrap">Signal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($rows as $row)
                @php
                    $net = $row['net_amount'] ?? 0;
                    $sc  = $row['sentiment']==='bullish'?'bg-emerald-100 text-emerald-700':($row['sentiment']==='bearish'?'bg-red-100 text-red-700':'bg-slate-100 text-slate-500');
                    $st  = $row['sentiment']==='bullish'?'Buy':($row['sentiment']==='bearish'?'Sell':'Flat');
                @endphp
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-4 py-2 font-bold text-slate-800 whitespace-nowrap">{{ $row['date'] }}</td>
                    <td class="px-3 py-2 text-right text-indigo-600 tabular-nums whitespace-nowrap">{{ fmtCrPlain($row['buy_amount']??0) }}</td>
                    <td class="px-3 py-2 text-right text-red-500 tabular-nums whitespace-nowrap">{{ fmtCrPlain($row['sell_amount']??0) }}</td>
                    <td class="px-3 py-2 text-right font-bold tabular-nums whitespace-nowrap {{ $net>=0?'text-emerald-600':'text-red-600' }}">{{ fmtCr($net) }}</td>
                    <td class="px-3 py-2 text-right text-slate-600 tabular-nums whitespace-nowrap">{{ fmtLots($row['buy_contracts']??0) }}</td>
                    <td class="px-3 py-2 text-right text-slate-600 tabular-nums whitespace-nowrap">{{ fmtLots($row['sell_contracts']??0) }}</td>
                    <td class="px-3 py-2 text-right text-amber-600 tabular-nums whitespace-nowrap">{{ fmtCrPlain($row['oi_amount']??0) }}</td>
                    <td class="px-3 py-2 text-center whitespace-nowrap">
                        <span class="inline-block text-xs font-bold px-2 py-0.5 rounded {{ $sc }}">{{ $st }}</span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center py-10 text-slate-400">No data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endforeach
</div>

{{-- ═══════════ FLOW INTENSITY BARS ══════════════════════════════════ --}}
@if(count($dailySummary) > 0)
@php $maxAbs = collect($dailySummary)->map(fn($r)=>max(abs($r['fii_net']),abs($r['dii_net'])))->max() ?: 1; @endphp
<div class="bg-white rounded-xl border border-slate-200 shadow-sm mb-5 overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 bg-slate-50">
        <p class="text-sm font-bold text-slate-800">📊 Flow Intensity — FII vs DII</p>
        <p class="text-xs text-slate-400">Last 15 sessions · bar width = relative magnitude</p>
    </div>
    <div class="p-4 space-y-3">
        @foreach(array_slice($dailySummary, 0, 15) as $dk => $row)
        @php
            $fPct = min(100, round(abs($row['fii_net']) / $maxAbs * 100));
            $dPct = min(100, round(abs($row['dii_net']) / $maxAbs * 100));
            $fBg  = $row['fii_net'] >= 0 ? 'bg-indigo-500' : 'bg-red-400';
            $dBg  = $row['dii_net'] >= 0 ? 'bg-amber-400'  : 'bg-red-300';
            $fTxt = $row['fii_net'] >= 0 ? 'text-emerald-600' : 'text-red-600';
            $dTxt = $row['dii_net'] >= 0 ? 'text-emerald-600' : 'text-red-600';
        @endphp
        <div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">{{ $row['date'] }}</p>
            {{-- FII --}}
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-extrabold text-indigo-500 w-7 text-right">FII</span>
                <div class="flex-1 bg-slate-100 rounded h-3 overflow-hidden">
                    <div class="bar-fill h-full rounded {{ $fBg }}" style="width:{{ $fPct }}%"></div>
                </div>
                <span class="text-xs font-bold {{ $fTxt }} w-24 text-right tabular-nums">{{ fmtCr($row['fii_net']) }}</span>
            </div>
            {{-- DII --}}
            <div class="flex items-center gap-2">
                <span class="text-xs font-extrabold text-amber-500 w-7 text-right">DII</span>
                <div class="flex-1 bg-slate-100 rounded h-3 overflow-hidden">
                    <div class="bar-fill h-full rounded {{ $dBg }}" style="width:{{ $dPct }}%"></div>
                </div>
                <span class="text-xs font-bold {{ $dTxt }} w-24 text-right tabular-nums">{{ fmtCr($row['dii_net']) }}</span>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const dailySummary = @json(array_values($dailySummary));
const fiiData      = @json($fiiData);
const segColors    = @json($segColors);
const segLabels    = @json($segLabels);

Chart.defaults.font.family = 'system-ui, -apple-system, sans-serif';
Chart.defaults.color       = '#64748b';

/* ── Bar chart ──────────────────────────────────────────────────── */
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
                    backgroundColor: fiiNets.map(v => v >= 0 ? 'rgba(99,102,241,.85)' : 'rgba(239,68,68,.8)'),
                    borderRadius: 3, borderSkipped: false, barPercentage: .65,
                },
                {
                    label: 'DII Net (₹Cr)',
                    data: diiNets,
                    backgroundColor: diiNets.map(v => v >= 0 ? 'rgba(245,158,11,.85)' : 'rgba(248,113,113,.8)'),
                    borderRadius: 3, borderSkipped: false, barPercentage: .65,
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { boxWidth: 10, padding: 12, font: { size: 11, weight: '600' } } },
                tooltip: {
                    backgroundColor: '#1e293b', titleColor: '#e2e8f0', bodyColor: '#94a3b8',
                    padding: 10, cornerRadius: 8, borderColor: '#334155', borderWidth: 1,
                    callbacks: { label: ctx => ` ${ctx.dataset.label}: ₹${ctx.parsed.y.toLocaleString('en-IN')} Cr` }
                }
            },
            scales: {
                x: { ticks: { font: { size: 10 }, maxRotation: 45 }, grid: { display: false } },
                y: {
                    ticks: { font: { size: 10 }, callback: v => '₹' + v.toLocaleString('en-IN') },
                    grid: { color: 'rgba(0,0,0,.04)' }
                }
            }
        }
    });
})();

/* ── Doughnut chart ─────────────────────────────────────────────── */
(function () {
    const segs   = Object.keys(fiiData);
    const values = segs.map(s => fiiData[s].length ? Math.abs(parseFloat(fiiData[s][0].buy_amount)) : 0);
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
                hoverOffset: 10,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '65%',
            plugins: {
                legend: { position: 'right', labels: { boxWidth: 12, padding: 14, font: { size: 11 } } },
                tooltip: {
                    backgroundColor: '#1e293b', titleColor: '#e2e8f0', bodyColor: '#94a3b8',
                    padding: 10, cornerRadius: 8,
                    callbacks: {
                        label: ctx => {
                            const pct = total > 0 ? ((ctx.raw / total) * 100).toFixed(1) : 0;
                            return ` ₹${parseFloat(ctx.raw).toFixed(0)} Cr  (${pct}%)`;
                        }
                    }
                }
            }
        }
    });
})();

/* ── Segment tab switcher ────────────────────────────────────────── */
function switchTab(slug) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('text-indigo-600', 'border-indigo-600', 'font-bold');
        b.classList.add('text-slate-500', 'border-transparent');
    });
    const panel = document.getElementById('fpanel-' + slug);
    const btn   = document.getElementById('ftab-' + slug);
    if (panel) panel.classList.add('active');
    if (btn) {
        btn.classList.remove('text-slate-500', 'border-transparent');
        btn.classList.add('text-indigo-600', 'border-indigo-600', 'font-bold');
    }
}
</script>
@endpush
