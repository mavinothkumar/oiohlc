@extends('layouts.app')

@section('title', 'Vega & Options Breadth Monitor – Enterprise Volatility Matrix')

@push('styles')
<style>
    /* Dark institutional theme for Matrix */
    .vega-matrix-table {
        border-collapse: separate;
        border-spacing: 1.5px;
    }
    .vega-sticky-col {
        position: sticky;
        left: 0;
        z-index: 20;
    }
    .matrix-cell {
        min-width: 14px;
        width: 15px;
        height: 22px;
        cursor: pointer;
        transition: transform 0.1s ease, filter 0.15s ease;
    }
    .matrix-cell:hover {
        transform: scale(1.25);
        z-index: 30;
        filter: brightness(1.35);
        box-shadow: 0 0 6px rgba(255, 255, 255, 0.4);
    }
    .matrix-col-active {
        box-shadow: inset 0 0 0 1px #38bdf8, 0 0 8px rgba(56, 189, 248, 0.4);
        filter: brightness(1.2);
    }
    /* Subtle live dot pulse */
    @keyframes pulse-subtle {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    .live-dot {
        animation: pulse-subtle 2s infinite ease-in-out;
    }
</style>
@endpush

@section('content')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<div class="w-full px-2 sm:px-4 py-2 space-y-3">

    {{-- 1. SLIM TOP FILTER SECTION (First section after nav menu) --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-3 sm:p-4">
        <form method="GET" action="{{ route('vega.analysis') }}" id="vegaFilterForm">
            <input type="hidden" name="mode" value="{{ $mode }}">
            <input type="hidden" name="metric" value="{{ $matrixMetric }}">

            <div class="flex flex-wrap items-center justify-between gap-3">
                
                {{-- Left Filter Controls --}}
                <div class="flex flex-wrap items-center gap-2.5">
                    {{-- Symbol --}}
                    <div class="flex items-center bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs">
                        <span class="text-slate-500 font-bold mr-1.5">Symbol:</span>
                        <select name="symbol" class="bg-transparent font-bold text-slate-800 focus:outline-none cursor-pointer">
                            <option value="NIFTY" {{ $symbol === 'NIFTY' ? 'selected' : '' }}>NIFTY</option>
                            <option value="BANKNIFTY" {{ $symbol === 'BANKNIFTY' ? 'selected' : '' }}>BANKNIFTY</option>
                            <option value="FINNIFTY" {{ $symbol === 'FINNIFTY' ? 'selected' : '' }}>FINNIFTY</option>
                        </select>
                    </div>

                    {{-- Expiry --}}
                    <div class="flex items-center bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs">
                        <span class="text-slate-500 font-bold mr-1.5">Expiry:</span>
                        <select name="expiry" class="bg-transparent font-bold text-slate-800 focus:outline-none cursor-pointer">
                            @foreach($expiries as $exp)
                                <option value="{{ $exp }}" {{ $selectedExpiry === $exp ? 'selected' : '' }}>
                                    {{ Carbon\Carbon::parse($exp)->format('d M Y') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Trading Date --}}
                    <div class="flex items-center bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs">
                        <span class="text-slate-500 font-bold mr-1.5">Date:</span>
                        <input type="date" name="date" value="{{ $selectedDate }}"
                               class="bg-transparent font-bold text-slate-800 focus:outline-none cursor-pointer text-xs">
                    </div>

                    {{-- Center ATM Strike --}}
                    <div class="flex items-center bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs">
                        <span class="text-slate-500 font-bold mr-1.5">Center Strike:</span>
                        <select name="strike" class="bg-transparent font-bold text-slate-800 focus:outline-none cursor-pointer">
                            @foreach($allStrikes as $s)
                                <option value="{{ $s }}" {{ (int)$centerStrike === (int)$s ? 'selected' : '' }}>
                                    {{ $s }} {{ (int)$s === (int)$atmStrike ? '(ATM)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Basket Range: +/- 3, +/- 5, +/- 7 --}}
                    <div class="flex items-center bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs">
                        <span class="text-slate-500 font-bold mr-1.5">Basket:</span>
                        <select name="range" class="bg-transparent font-bold text-indigo-700 focus:outline-none cursor-pointer">
                            <option value="0" {{ $strikeRange === 0 ? 'selected' : '' }}>Single Strike (CE+PE)</option>
                            <option value="3" {{ $strikeRange === 3 ? 'selected' : '' }}>±3 Strikes (7 Avg)</option>
                            <option value="5" {{ $strikeRange === 5 ? 'selected' : '' }}>±5 Strikes (11 Avg)</option>
                            <option value="7" {{ $strikeRange === 7 ? 'selected' : '' }}>±7 Strikes (15 Avg)</option>
                        </select>
                    </div>

                    <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-1.5 px-3 rounded-lg text-xs shadow-sm transition">
                        Update
                    </button>
                    @if($atmStrike)
                        <button type="button" id="quickAtmBtn"
                                class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-1.5 px-2.5 rounded-lg text-xs border border-slate-300 transition"
                                title="Reset Center Strike to current ATM">
                            ATM ({{ $atmStrike }})
                        </button>
                    @endif
                </div>

                {{-- Right Controls: Mode Toggle & Slim Action Signal Badge --}}
                <div class="flex items-center gap-2.5">
                    {{-- Slim Regime Pill --}}
                    @php
                        $isDualDecay = ($currentRegime === 'BOTH_BELOW_ZERO');
                        $isDualExp   = ($currentRegime === 'BOTH_ABOVE_ZERO');
                        $isBullSkew  = ($currentRegime === 'CALL_ABOVE_PUT_BELOW');
                    @endphp
                    <div class="px-3 py-1.5 rounded-lg font-black text-xs flex items-center gap-1.5 shadow-sm {{ 
                        $isDualDecay ? 'bg-rose-600 text-white ring-2 ring-rose-200' : 
                        ($isDualExp ? 'bg-emerald-600 text-white ring-2 ring-emerald-200' : 
                        ($isBullSkew ? 'bg-blue-600 text-white ring-2 ring-blue-200' : 'bg-amber-600 text-white ring-2 ring-amber-200')) 
                    }}">
                        <span class="w-2 h-2 rounded-full bg-white live-dot"></span>
                        <span>
                            @if($isDualDecay)
                                PREFER: SELL OPTIONS (Dual Decay)
                            @elseif($isDualExp)
                                PREFER: BUY OPTIONS (Dual Exp)
                            @elseif($isBullSkew)
                                PREFER: BUY CE / SELL PE
                            @else
                                PREFER: BUY PE / SELL CE
                            @endif
                        </span>
                    </div>

                    {{-- Mode Toggle Tabs --}}
                    <div class="inline-flex rounded-lg p-0.5 bg-slate-100 border border-slate-300">
                        <a href="{{ request()->fullUrlWithQuery(['mode' => 'change']) }}"
                           class="px-2.5 py-1 rounded text-xs font-bold transition {{ $mode === 'change' ? 'bg-white text-indigo-700 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                            Zero-Line Δ
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['mode' => 'absolute']) }}"
                           class="px-2.5 py-1 rounded text-xs font-bold transition {{ $mode === 'absolute' ? 'bg-white text-indigo-700 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                            Absolute
                        </a>
                    </div>
                </div>

            </div>
        </form>
    </div>

    {{-- 2. CHART (LEFT 78-80%) & 4 WIDGETS (RIGHT 20-22%) IN SAME ROW --}}
    <div class="flex flex-col lg:flex-row gap-3 w-full items-stretch">
        
        {{-- Left: Vega Chart (~78% on desktop) --}}
        <div class="lg:w-[78%] bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex flex-col justify-between">
            <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                        <span>Vega Sensitivity &amp; Synthetic Future</span>
                        <span class="text-[11px] font-mono font-semibold px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200">
                            {{ $strikeRange > 0 ? "±{$strikeRange} Strikes Basket Average" : "Strike {$centerStrike}" }}
                        </span>
                    </h2>
                </div>

                {{-- Chart Legend Pills --}}
                <div class="flex items-center gap-2.5 text-xs font-semibold">
                    <span class="inline-flex items-center gap-1 text-emerald-700">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Call Vega
                    </span>
                    <span class="inline-flex items-center gap-1 text-rose-700">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Put Vega
                    </span>
                    <span class="inline-flex items-center gap-1 text-slate-700">
                        <span class="w-3 border-t-2 border-dashed border-slate-600"></span> Synth Future (Left Axis)
                    </span>
                </div>
            </div>

            @if(empty($labels))
                <div class="py-24 text-center">
                    <div class="text-3xl mb-2">🔍</div>
                    <div class="text-sm font-bold text-slate-700">No snapshot records found for selected filters</div>
                </div>
            @else
                <div class="relative w-full" style="height: 420px;">
                    <canvas id="vegaEnterpriseChart"></canvas>
                </div>
            @endif
        </div>

        {{-- Right: 4 Widgets in 2x2 Grid (~22% on desktop) --}}
        <div class="lg:w-[22%] flex flex-col gap-2.5 justify-between">
            <div class="grid grid-cols-2 gap-2.5 flex-1">
                
                {{-- Widget 1: Synthetic Future --}}
                <div class="bg-white rounded-xl p-3 shadow-sm border border-slate-200 flex flex-col justify-between">
                    <div class="text-[11px] text-slate-500 font-bold uppercase tracking-wider">Synth Future</div>
                    <div class="text-lg sm:text-xl font-black text-slate-900 font-mono my-1">
                        {{ number_format($stats['synth'], 2) }}
                    </div>
                    <div class="text-[11px] font-bold {{ $stats['synth_change'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ $stats['synth_change'] >= 0 ? '▲ +' : '▼ ' }}{{ number_format($stats['synth_change'], 2) }}
                    </div>
                </div>

                {{-- Widget 2: Net Short Vega --}}
                <div class="bg-white rounded-xl p-3 shadow-sm border border-slate-200 flex flex-col justify-between">
                    <div class="text-[11px] text-slate-500 font-bold uppercase tracking-wider">Short Vega</div>
                    <div class="text-lg sm:text-xl font-black text-slate-900 font-mono my-1">
                        {{ number_format($stats['net_vega_short'], 2) }}
                    </div>
                    <div class="text-[11px] text-slate-500 font-semibold">
                        Gain/1% IV crush
                    </div>
                </div>

                {{-- Widget 3: Call Vega & Delta --}}
                <div class="bg-white rounded-xl p-3 shadow-sm border border-slate-200 flex flex-col justify-between">
                    <div class="flex items-center justify-between text-[11px] text-slate-500 font-bold">
                        <span class="flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span> CE Vega
                        </span>
                    </div>
                    <div class="text-lg sm:text-xl font-black text-slate-900 font-mono my-1">
                        {{ number_format($stats['call_vega'], 2) }}
                    </div>
                    <div class="text-[11px] font-bold {{ $stats['call_vega_change'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ $stats['call_vega_change'] >= 0 ? '▲ +' : '▼ ' }}{{ number_format($stats['call_vega_change'], 2) }} Δ
                    </div>
                </div>

                {{-- Widget 4: Put Vega & Delta --}}
                <div class="bg-white rounded-xl p-3 shadow-sm border border-slate-200 flex flex-col justify-between">
                    <div class="flex items-center justify-between text-[11px] text-slate-500 font-bold">
                        <span class="flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-rose-500"></span> PE Vega
                        </span>
                    </div>
                    <div class="text-lg sm:text-xl font-black text-slate-900 font-mono my-1">
                        {{ number_format($stats['put_vega'], 2) }}
                    </div>
                    <div class="text-[11px] font-bold {{ $stats['put_vega_change'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ $stats['put_vega_change'] >= 0 ? '▲ +' : '▼ ' }}{{ number_format($stats['put_vega_change'], 2) }} Δ
                    </div>
                </div>

            </div>

            {{-- Summary Regime Explainer Card below 2x2 --}}
            <div class="bg-slate-900 rounded-xl p-3 text-white border border-slate-800 text-xs shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between mb-1">
                    <span class="font-extrabold text-amber-400 uppercase text-[10px] tracking-wider">Regime Insight</span>
                    @if($regimeStartTime)
                        <span class="text-[10px] text-slate-400">Since {{ $regimeStartTime }}</span>
                    @endif
                </div>
                <div class="font-bold text-slate-200 text-xs leading-snug">
                    @if($isDualDecay)
                        Both Call &amp; Put Vega are below 0. Volatility crush is accelerating decay. Favorable for Option Selling.
                    @elseif($isDualExp)
                        Both Call &amp; Put Vega are expanding above 0. Implied Volatility is spiking. Favorable for Option Buying.
                    @elseif($isBullSkew)
                        Call Vega is expanding while Put Vega collapses. Bullish upside positioning.
                    @else
                        Put Vega is expanding while Call Vega collapses. Bearish downside hedging.
                    @endif
                </div>
            </div>
        </div>

    </div>

    {{-- 3. NIFTY 50 OPTIONS 5-MINUTE BREADTH MATRIX (Like NIFTY Weightage Monitor Attachment) --}}
    <div class="bg-[#131722] border border-gray-800 rounded-xl p-3 shadow-lg text-gray-200">
        
        {{-- Matrix Header & Metric Tabs --}}
        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-gray-800">
            <div class="flex items-center gap-2.5">
                <span class="p-1.5 bg-blue-900/40 border border-blue-500/30 rounded text-blue-400 text-xs">📊</span>
                <div>
                    <h3 class="text-sm font-bold text-white tracking-wide flex items-center gap-2">
                        <span>Options 5m Greek &amp; Breadth Matrix</span>
                        <span class="text-[11px] font-normal text-gray-400">({{ count($matrixRows) }} Strike Rows)</span>
                    </h3>
                    <p class="text-[11px] text-gray-400">
                        Tracks 5-minute candle volatility expansion vs contraction across ±{{ $strikeRange }} strikes around ATM
                    </p>
                </div>
            </div>

            {{-- Matrix Metric Filters --}}
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-400">Metric:</span>
                <div class="inline-flex rounded-lg p-0.5 bg-gray-900 border border-gray-700 text-xs">
                    <a href="{{ request()->fullUrlWithQuery(['metric' => 'vega']) }}"
                       class="px-2.5 py-1 rounded font-bold transition {{ $matrixMetric === 'vega' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white' }}">
                        Vega Expansion / Decay
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['metric' => 'premium']) }}"
                       class="px-2.5 py-1 rounded font-bold transition {{ $matrixMetric === 'premium' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white' }}">
                        Premium Momentum
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['metric' => 'signal']) }}"
                       class="px-2.5 py-1 rounded font-bold transition {{ $matrixMetric === 'signal' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white' }}">
                        Buy / Sell Signal
                    </a>
                </div>

                {{-- Legend --}}
                <div class="hidden sm:flex items-center gap-2 text-[11px] text-gray-400 ml-2">
                    <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-emerald-500"></span> Exp / Up</span>
                    <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-rose-500"></span> Decay / Down</span>
                    <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-blue-500"></span> Bull Skew</span>
                </div>
            </div>
        </div>

        {{-- Scrollable Heatmap Table Container --}}
        <div class="w-full overflow-x-auto max-h-[460px] overflow-y-auto border border-gray-800/80 rounded-lg bg-[#0b0e14] mt-3">
            <table class="vega-matrix-table w-full text-xs text-left" id="vega-matrix-table">
                <thead class="sticky top-0 bg-[#131722] z-30 shadow">
                    <tr class="border-b border-gray-800 text-gray-400 text-[11px] uppercase">
                        <th class="vega-sticky-col px-3 py-2 bg-[#131722] min-w-[160px] font-semibold border-r border-gray-800">
                            Option Strike
                        </th>
                        <th class="px-2 py-2 bg-[#131722] text-center min-w-[70px] font-semibold border-r border-gray-800">
                            Type
                        </th>
                        {{-- 5m Timestamps --}}
                        @foreach($matrixTimestamps as $t)
                            <th class="px-0.5 py-2 text-center text-[10px] font-mono text-gray-400 min-w-[15px] {{ substr($t, 3, 2) === '00' ? 'border-l border-gray-700/80 text-white font-bold' : '' }}" title="{{ $t }}">
                                @if(substr($t, 3, 2) === '00')
                                    {{ substr($t, 0, 2) }}
                                @else
                                    ·
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-900/60 font-mono">
                    @foreach($matrixRows as $mRow)
                        <tr class="{{ $mRow['is_master'] ? 'bg-indigo-950/40 font-bold border-b-2 border-indigo-500/40' : 'hover:bg-gray-900/50' }}">
                            {{-- Sticky Strike Column --}}
                            <td class="vega-sticky-col px-3 py-1.5 {{ $mRow['is_master'] ? 'bg-[#181d2c] text-indigo-300 font-extrabold' : 'bg-[#131722] text-white' }} border-r border-gray-800 whitespace-nowrap">
                                <div>{{ $mRow['title'] }}</div>
                                <div class="text-[10px] text-gray-400 font-normal">{{ $mRow['subtitle'] }}</div>
                            </td>
                            <td class="px-2 py-1.5 text-center text-[10px] {{ $mRow['is_master'] ? 'bg-[#181d2c] text-indigo-400 font-black' : 'text-gray-400' }} border-r border-gray-800">
                                {{ $mRow['is_master'] ? 'BASKET' : ($mRow['is_atm'] ? 'ATM' : 'OTM') }}
                            </td>

                            {{-- 5m Heatmap Cells --}}
                            @foreach($matrixTimestamps as $t)
                                @php
                                    $cell = $mRow['cells'][$t] ?? null;
                                    $cType = $cell['type'] ?? 'neutral';
                                    
                                    if ($cType === 'bull') {
                                        $cellBg = 'bg-emerald-500';
                                    } elseif ($cType === 'bear') {
                                        $cellBg = 'bg-rose-500';
                                    } elseif ($cType === 'skew_bull') {
                                        $cellBg = 'bg-blue-500';
                                    } elseif ($cType === 'skew_bear') {
                                        $cellBg = 'bg-amber-500';
                                    } else {
                                        $cellBg = 'bg-gray-800';
                                    }
                                @endphp
                                <td class="p-0">
                                    <div class="matrix-cell {{ $cellBg }} rounded-xs"
                                         data-time="{{ $t }}"
                                         data-strike="{{ $mRow['title'] }}"
                                         data-val="{{ $cell['value'] ?? '' }}"
                                         title="{{ $t }} | {{ $mRow['title'] }}: {{ $cell['value'] ?? '' }}">
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- 4. BOTTOM SECTION: DECISION MATRIX & RISK MANAGEMENT (Moved to the last section) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
        
        {{-- Decision Matrix --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
            <h4 class="text-sm font-extrabold text-slate-900 mb-2.5 flex items-center gap-1.5">
                <span>📘 Vega Zero-Line Decision Matrix</span>
            </h4>
            <div class="space-y-2 text-xs">
                <div class="p-2.5 rounded-lg border border-rose-200 bg-rose-50/70">
                    <div class="flex items-center justify-between font-bold text-rose-900">
                        <span>1. Both Below Zero Line (Dual Contraction)</span>
                        <span class="px-2 py-0.5 rounded bg-rose-600 text-white text-[10px] uppercase font-black">Sell Options</span>
                    </div>
                    <p class="text-[11px] text-rose-800 mt-1">
                        Call Vega and Put Vega are both deflating below open. Double decay (Theta + Vega drop) suppresses premiums. Favor Short Straddles, Strangles, or Credit Spreads.
                    </p>
                </div>

                <div class="p-2.5 rounded-lg border border-emerald-200 bg-emerald-50/70">
                    <div class="flex items-center justify-between font-bold text-emerald-900">
                        <span>2. Both Above Zero Line (Dual Expansion)</span>
                        <span class="px-2 py-0.5 rounded bg-emerald-600 text-white text-[10px] uppercase font-black">Buy Options</span>
                    </div>
                    <p class="text-[11px] text-emerald-800 mt-1">
                        Volatility is surging on both sides. High gamma risk for sellers. Favor Breakout Option Buying or Long Straddles.
                    </p>
                </div>

                <div class="p-2.5 rounded-lg border border-slate-200 bg-slate-50">
                    <div class="flex items-center justify-between font-bold text-slate-900">
                        <span>3. Divergence (Call &gt; 0, Put &lt; 0 or vice versa)</span>
                        <span class="px-2 py-0.5 rounded bg-indigo-600 text-white text-[10px] uppercase font-black">Directional Skew</span>
                    </div>
                    <p class="text-[11px] text-slate-700 mt-1">
                        One-sided demand: Bullish skew when Call Vega expands &amp; Put decays; Bearish skew when Put Vega expands &amp; Call decays.
                    </p>
                </div>
            </div>
        </div>

        {{-- Execution Rules --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex flex-col justify-between">
            <div>
                <h4 class="text-sm font-extrabold text-slate-900 mb-2.5 flex items-center gap-1.5">
                    <span>⚠️ Execution &amp; Risk Management Playbook</span>
                </h4>
                <ul class="space-y-2 text-xs text-slate-700">
                    <li class="flex items-start gap-1.5">
                        <span class="text-rose-500 font-bold">●</span>
                        <span><strong>Never sell naked options:</strong> Even on decay days, sudden headline reversals can spike Gamma and cause rapid drawdowns. Use stop losses or credit spread wings.</span>
                    </li>
                    <li class="flex items-start gap-1.5">
                        <span class="text-indigo-500 font-bold">●</span>
                        <span><strong>Combine with Synthetic Future:</strong> If Synthetic Future breaks day support while Vega is below 0, prefer Bear Call credit spreads over neutral straddles.</span>
                    </li>
                    <li class="flex items-start gap-1.5">
                        <span class="text-amber-500 font-bold">●</span>
                        <span><strong>ATM Basket Smoothing:</strong> Using ±3 or ±5 strikes filters out single-strike liquidity noise and reflects the true institutional volatility tone.</span>
                    </li>
                </ul>
            </div>

            <div class="mt-3 p-2.5 rounded-lg bg-slate-900 text-white text-[11px] font-mono">
                <span class="text-amber-400 font-bold">CORE RULE:</span> "Below zero = decay edge for sellers. Above zero = expansion edge for buyers."
            </div>
        </div>

    </div>

</div>

{{-- Scripts for Chart.js & Select2 --}}
@if(!empty($labels))
<script>
$(document).ready(function() {
    $('#quickAtmBtn').on('click', function() {
        const atm = "{{ $atmStrike }}";
        if (atm) {
            $('select[name="strike"]').val(atm);
            $('#vegaFilterForm').submit();
        }
    });

    const labels = @json($labels);
    const mode = "{{ $mode }}";
    
    const callData  = (mode === 'change') ? @json($callVegaChange) : @json($callVega);
    const putData   = (mode === 'change') ? @json($putVegaChange)  : @json($putVega);
    const synthData = @json($synthFuture);

    const callLabel = (mode === 'change') ? 'Call Vega (Δ Open)' : 'Call Vega';
    const putLabel  = (mode === 'change') ? 'Put Vega (Δ Open)'  : 'Put Vega';

    const ctx = document.getElementById('vegaEnterpriseChart').getContext('2d');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: callLabel,
                    data: callData,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.15)',
                    borderWidth: 2.2,
                    fill: (mode === 'change') ? { target: 'origin', above: 'rgba(16, 185, 129, 0.20)', below: 'rgba(239, 68, 68, 0.08)' } : false,
                    tension: 0.25,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    yAxisID: 'y1'
                },
                {
                    label: putLabel,
                    data: putData,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.20)',
                    borderWidth: 2.2,
                    fill: (mode === 'change') ? { target: '-1', below: 'rgba(239, 68, 68, 0.25)', above: 'rgba(239, 68, 68, 0.08)' } : false,
                    tension: 0.25,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    yAxisID: 'y1'
                },
                {
                    label: 'Synthetic Future',
                    data: synthData,
                    borderColor: '#475569',
                    borderWidth: 1.5,
                    borderDash: [3, 3],
                    backgroundColor: 'transparent',
                    tension: 0.15,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    yAxisID: 'y'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.94)',
                    titleColor: '#f8fafc',
                    bodyColor: '#e2e8f0',
                    borderColor: '#334155',
                    borderWidth: 1,
                    padding: 10,
                    callbacks: {
                        title: function(items) {
                            return '⏰ ' + items[0].label;
                        },
                        label: function(context) {
                            const val = context.parsed.y;
                            if (context.dataset.label.includes('Synthetic Future')) {
                                return '⚡ Synth Future: ' + val.toLocaleString('en-IN', { minimumFractionDigits: 2 });
                            }
                            const sign = (mode === 'change' && val > 0) ? '+' : '';
                            return '• ' + context.dataset.label + ': ' + sign + val.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(226, 232, 240, 0.6)' },
                    ticks: { color: '#64748b', font: { size: 10 }, maxTicksLimit: 12 }
                },
                y: {
                    type: 'linear',
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Synthetic Future Price (₹)',
                        color: '#334155',
                        font: { weight: 'bold', size: 11 }
                    },
                    grid: { color: 'rgba(226, 232, 240, 0.4)' },
                    ticks: {
                        color: '#334155',
                        font: { size: 10 },
                        callback: function(val) { return val.toLocaleString('en-IN'); }
                    }
                },
                y1: {
                    type: 'linear',
                    position: 'right',
                    title: {
                        display: true,
                        text: (mode === 'change') ? 'Vega Change (Δ Zero-Line)' : 'Vega',
                        color: '#475569',
                        font: { weight: 'bold', size: 11 }
                    },
                    grid: {
                        color: function(context) {
                            if (context.tick && context.tick.value === 0) return '#0f172a';
                            return 'rgba(226, 232, 240, 0.6)';
                        },
                        lineWidth: function(context) {
                            return (context.tick && context.tick.value === 0) ? 2 : 1;
                        }
                    },
                    ticks: {
                        color: '#475569',
                        font: { size: 10 },
                        callback: function(val) { return (mode === 'change' && val > 0 ? '+' : '') + val; }
                    }
                }
            }
        }
    });
});
</script>
@endif
@endsection
