@extends('layouts.app')

@section('title', 'Strategy-Agnostic Options Analytics & Risk Management Terminal')

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
    [x-cloak] { display: none !important; }
    .font-mono-num { font-variant-numeric: tabular-nums; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
</style>
@endpush

@section('content')
<div class="w-full max-w-[1720px] mx-auto px-2 sm:px-4 py-3 space-y-4 text-slate-100 font-sans"
     x-data="optionsAnalyticsApp()"
     id="analytics-app">

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- 1. TOP TERMINAL HEADER & METRICS BAR                                  --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div class="bg-slate-900/95 border border-slate-800 rounded-2xl p-3.5 shadow-2xl backdrop-blur-md">
        <div class="flex flex-wrap items-center justify-between gap-3">
            
            {{-- Left: Identity, Position Switcher, Strategy & Underlying --}}
            <div class="flex items-center flex-wrap gap-3">
                <div class="flex items-center gap-2 pr-3 border-r border-slate-800">
                    <span class="flex h-3 w-3 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cyan-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-cyan-500"></span>
                    </span>
                    <h1 class="text-base sm:text-lg font-black tracking-tight bg-gradient-to-r from-cyan-400 via-teal-300 to-emerald-400 bg-clip-text text-transparent">
                        OPTIONS RISK TERMINAL
                    </h1>
                </div>

                {{-- Position Switcher Dropdown --}}
                <div class="flex items-center gap-1.5 bg-slate-800/90 border border-slate-700/80 rounded-xl px-2.5 py-1">
                    <span class="text-[11px] font-semibold text-slate-400">POSITION:</span>
                    <select onchange="window.location.href='/options-analytics/' + this.value"
                            class="bg-transparent text-xs font-bold text-cyan-300 focus:outline-none cursor-pointer">
                        @foreach($allPositions as $pos)
                            <option value="{{ $pos->id }}" class="bg-slate-900 text-slate-200" {{ $activePosition && $activePosition->id === $pos->id ? 'selected' : '' }}>
                                {{ $pos->name }} ({{ $pos->underlying?->symbol ?? 'NIFTY' }} &bull; {{ $pos->status }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Strategy Badge --}}
                @if($analytics && $analytics['strategy'])
                <div class="flex items-center gap-2 bg-slate-800/80 border border-slate-700 rounded-xl px-3 py-1">
                    <span class="text-[10px] uppercase tracking-wider text-slate-400 font-bold">STRATEGY</span>
                    <span class="text-xs font-black text-white">{{ $analytics['strategy']['name'] }}</span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded font-bold uppercase
                        {{ $analytics['strategy']['category'] === 'INCOME' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : '' }}
                        {{ $analytics['strategy']['category'] === 'VOLATILITY' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : '' }}
                        {{ $analytics['strategy']['category'] === 'BULLISH' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' : '' }}
                        {{ $analytics['strategy']['category'] === 'BEARISH' ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30' : '' }}
                        {{ $analytics['strategy']['category'] === 'CUSTOM' ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : '' }}
                    ">
                        {{ $analytics['strategy']['category'] }}
                    </span>
                    <span class="text-[11px] font-mono-num text-slate-400 border-l border-slate-700 pl-2">
                        {{ $analytics['strategy']['characteristics']['leg_count'] ?? count($analytics['legs']) }} Legs
                    </span>
                </div>
                @endif

                {{-- Underlying Spot Badge --}}
                <div class="flex items-center gap-2 bg-slate-800/80 border border-slate-700 rounded-xl px-3 py-1">
                    <span class="text-[11px] font-bold text-slate-300">{{ $quote->symbol }} SPOT:</span>
                    <span class="text-sm font-black font-mono-num text-emerald-400">{{ number_format($quote->spot, 2) }}</span>
                    <span class="text-[11px] font-semibold font-mono-num {{ $quote->change >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                        {{ $quote->change >= 0 ? '+' : '' }}{{ number_format($quote->change, 2) }} ({{ number_format($quote->changePct, 2) }}%)
                    </span>
                </div>

                {{-- DTE & Regime Badges --}}
                @if($analytics)
                <div class="hidden lg:flex items-center gap-2 bg-slate-800/80 border border-slate-700 rounded-xl px-3 py-1">
                    <span class="text-[11px] text-slate-400">DTE:</span>
                    <span class="text-xs font-black font-mono-num text-amber-300">{{ $analytics['meta']['dte'] ?? 3.2 }} Days</span>
                    <span class="text-slate-600">|</span>
                    <span class="text-[11px] text-slate-400">REGIME:</span>
                    <span class="text-[11px] font-bold text-cyan-300">{{ $analytics['regime']['regime'] ?? 'UNKNOWN' }}</span>
                </div>
                @endif
            </div>

            {{-- Right: Actions & Integrity Metadata --}}
            <div class="flex items-center gap-2.5">
                <button type="button"
                        @click="showNewStrategyModal = true"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl font-bold text-xs bg-gradient-to-r from-cyan-600 to-teal-600 hover:from-cyan-500 hover:to-teal-500 text-white shadow-lg transition active:scale-95">
                    <span>⚡</span>
                    <span>Preset Strategies</span>
                </button>

                <div class="hidden md:flex flex-col text-right text-[10px] text-slate-400 font-mono-num">
                    <span>FEED: <span class="text-slate-300">{{ $quote->source }}</span></span>
                    <span>UPDATED: <span class="text-slate-300">{{ $analytics['meta']['market_data_timestamp'] ?? $quote->timestamp }}</span></span>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- 2. EXPLAINABLE RISK STATE BANNER (Section 19 & 40)                   --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if($analytics && isset($analytics['risk_state']))
    @php
        $rState = $analytics['risk_state']['state'] ?? 'NORMAL';
        $stateStyles = [
            'NORMAL' => ['bg' => 'bg-emerald-950/40 border-emerald-500/40 text-emerald-400', 'badge' => 'bg-emerald-500 text-slate-950', 'icon' => '🛡️'],
            'CAUTION' => ['bg' => 'bg-amber-950/40 border-amber-500/40 text-amber-300', 'badge' => 'bg-amber-400 text-slate-950', 'icon' => '⚠️'],
            'STRESS' => ['bg' => 'bg-rose-950/40 border-rose-500/40 text-rose-300', 'badge' => 'bg-rose-500 text-white', 'icon' => '🚨'],
            'RISK_REVIEW' => ['bg' => 'bg-purple-950/40 border-purple-500/40 text-purple-300', 'badge' => 'bg-purple-500 text-white', 'icon' => '🛑'],
            'CLOSED' => ['bg' => 'bg-slate-900 border-slate-700 text-slate-400', 'badge' => 'bg-slate-600 text-white', 'icon' => '🔒'],
        ];
        $activeStyle = $stateStyles[$rState] ?? $stateStyles['NORMAL'];
    @endphp
    <div class="border rounded-2xl p-4 shadow-xl backdrop-blur-md {{ $activeStyle['bg'] }}">
        <div class="flex flex-col md:flex-row md:items-start justify-between gap-3">
            <div class="space-y-1.5 flex-1">
                <div class="flex items-center gap-2">
                    <span class="text-lg">{{ $activeStyle['icon'] }}</span>
                    <span class="text-xs uppercase tracking-wider font-extrabold text-slate-300">POSITION STATE:</span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black uppercase tracking-wide {{ $activeStyle['badge'] }}">
                        {{ $rState }}
                    </span>
                    <span class="text-xs text-slate-300 font-medium ml-2">
                        {{ $analytics['risk_state']['summary'] }}
                    </span>
                </div>

                {{-- Explainability: Why is this state active? --}}
                <div class="mt-2 pt-2 border-t border-slate-800/80">
                    <div class="text-[11px] font-extrabold tracking-wider text-slate-300 uppercase mb-1">
                        Explainability Trace &bull; Contributing Signals:
                    </div>
                    <ul class="grid grid-cols-1 md:grid-cols-2 gap-1.5 text-xs text-slate-200">
                        @foreach($analytics['risk_state']['reasons'] as $reason)
                        <li class="flex items-start gap-2 bg-slate-900/60 border border-slate-800/60 rounded-lg px-2.5 py-1">
                            <span class="text-cyan-400 font-bold">&bull;</span>
                            <span>{{ $reason }}</span>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Original Assumption Quick Drift Summary --}}
            @if(isset($analytics['assumptions_comparison']))
            <div class="flex-shrink-0 bg-slate-900/80 border border-slate-800 rounded-xl p-2.5 text-xs font-mono-num space-y-1 min-w-[240px]">
                <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">DRIFT FROM INCEPTION:</div>
                <div class="flex justify-between text-slate-300">
                    <span>Spot Drift:</span>
                    <span class="{{ $analytics['assumptions_comparison']['spot']['change'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }} font-bold">
                        {{ $analytics['assumptions_comparison']['spot']['change'] >= 0 ? '+' : '' }}{{ $analytics['assumptions_comparison']['spot']['change'] }} pts
                    </span>
                </div>
                <div class="flex justify-between text-slate-300">
                    <span>IV Shift:</span>
                    <span class="{{ $analytics['assumptions_comparison']['iv']['change'] >= 0 ? 'text-amber-400' : 'text-cyan-400' }} font-bold">
                        {{ $analytics['assumptions_comparison']['iv']['change'] >= 0 ? '+' : '' }}{{ $analytics['assumptions_comparison']['iv']['change'] }} pts
                    </span>
                </div>
                <div class="flex justify-between text-slate-300">
                    <span>Delta Drift:</span>
                    <span class="text-purple-300 font-bold">
                        {{ $analytics['assumptions_comparison']['delta']['change'] >= 0 ? '+' : '' }}{{ $analytics['assumptions_comparison']['delta']['change'] }}
                    </span>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- 3. CORE METRICS STRIP (P&L, Breakevens, Value, Max Profit/Loss)       --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if($analytics)
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 font-mono-num">
        {{-- Card 1: Net P&L --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 shadow-lg">
            <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">NET P&amp;L</span>
            <div class="text-xl sm:text-2xl font-black mt-1 {{ $analytics['pnl']['net_pnl'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                {{ $analytics['pnl']['net_pnl'] >= 0 ? '+' : '' }}&#8377;{{ number_format($analytics['pnl']['net_pnl'], 2) }}
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5 flex justify-between">
                <span>Gross: {{ number_format($analytics['pnl']['gross_pnl'], 2) }}</span>
                <span>Fees: &#8377;{{ number_format($analytics['pnl']['estimated_costs'], 2) }}</span>
            </div>
        </div>

        {{-- Card 2: Position Value / Outlay --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 shadow-lg">
            <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">OUTLAY / CREDIT</span>
            <div class="text-xl font-bold mt-1 text-slate-200">
                &#8377;{{ number_format(abs($analytics['pnl']['initial_outlay']), 2) }}
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">
                {{ $analytics['pnl']['initial_outlay'] < 0 ? 'Net Credit Received' : 'Net Debit Deployed' }}
            </div>
        </div>

        {{-- Card 3: Breakevens --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 shadow-lg">
            <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">BREAKEVENS</span>
            <div class="text-sm font-bold mt-1 text-cyan-300 truncate">
                @if(!empty($analytics['breakevens']['breakevens']))
                    {{ implode(' &bull; ', $analytics['breakevens']['breakevens']) }}
                @else
                    <span class="text-slate-500">None in Range</span>
                @endif
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">
                {{ count($analytics['breakevens']['breakevens']) }} Zero-crossing points
            </div>
        </div>

        {{-- Card 4: Max Profit / Max Loss --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 shadow-lg">
            <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">MAX PROFIT / LOSS</span>
            <div class="text-xs font-bold mt-1 flex justify-between">
                <span class="text-emerald-400">Max+: {{ $analytics['breakevens']['max_profit'] !== null ? '₹'.number_format($analytics['breakevens']['max_profit']) : 'Unlimited' }}</span>
            </div>
            <div class="text-xs font-bold mt-0.5 flex justify-between">
                <span class="text-rose-400">Max-: {{ $analytics['breakevens']['max_loss'] !== null ? '₹'.number_format($analytics['breakevens']['max_loss']) : 'Unlimited' }}</span>
            </div>
        </div>

        {{-- Card 5: Net Delta & Gamma --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 shadow-lg">
            <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">POSITION DELTA / GAMMA</span>
            <div class="text-lg font-black mt-1 {{ abs($analytics['greeks']['net']['delta']) < 15 ? 'text-emerald-300' : 'text-amber-400' }}">
                &Delta; {{ $analytics['greeks']['net']['delta'] >= 0 ? '+' : '' }}{{ number_format($analytics['greeks']['net']['delta'], 2) }}
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">
                &Gamma;: {{ number_format($analytics['greeks']['net']['gamma'], 5) }}
            </div>
        </div>

        {{-- Card 6: Theta & Vega --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 shadow-lg">
            <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">THETA / VEGA</span>
            <div class="text-lg font-black mt-1 {{ $analytics['greeks']['net']['theta'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                &Theta;: {{ $analytics['greeks']['net']['theta'] >= 0 ? '+' : '' }}&#8377;{{ number_format($analytics['greeks']['net']['theta'], 1) }}/d
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">
                &nu;: &#8377;{{ number_format($analytics['greeks']['net']['vega'], 1) }}/1% IV
            </div>
        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- 4. MAIN ANALYTICS WORKSPACE: PAYOFF CHART & ASSUMPTION COMPARISON     --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        
        {{-- Left 2 cols: Interactive Payoff Diagram (Section 14 & 39) --}}
        <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                <div class="flex items-center gap-2">
                    <span class="text-base">📈</span>
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-200">STRATEGY PAYOFF DIAGRAM</h2>
                    <span class="text-[11px] px-2 py-0.5 rounded bg-slate-800 text-cyan-300 font-mono-num font-bold">
                        Spot: {{ number_format($quote->spot, 2) }}
                    </span>
                </div>
                <div class="flex items-center gap-3 text-xs">
                    <span class="flex items-center gap-1.5 text-slate-300">
                        <span class="inline-block w-3 h-1 bg-emerald-400 rounded"></span> Expiry P&amp;L
                    </span>
                    <span class="flex items-center gap-1.5 text-slate-300">
                        <span class="inline-block w-3 h-1 bg-cyan-400 rounded border border-cyan-400 border-dashed"></span> T+0 Model
                    </span>
                </div>
            </div>

            {{-- Canvas for Chart.js --}}
            <div class="mt-4 relative h-[360px] w-full">
                <canvas id="payoffChart"></canvas>
            </div>

            {{-- Breakeven Scale Visualization Bar --}}
            @if($analytics && !empty($analytics['breakevens']['breakevens']))
            <div class="mt-4 pt-3 border-t border-slate-800/80">
                <div class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-1.5">
                    Breakeven Safety Corridor vs Spot:
                </div>
                <div class="flex items-center justify-between text-xs font-mono-num px-2 py-1.5 bg-slate-950/60 rounded-xl border border-slate-800">
                    <span class="text-rose-400 font-bold">Lower BE: {{ $analytics['breakevens']['breakevens'][0] }}</span>
                    <span class="text-emerald-400 font-black">&bull; SPOT: {{ number_format($quote->spot, 2) }} &bull;</span>
                    <span class="text-rose-400 font-bold">Upper BE: {{ end($analytics['breakevens']['breakevens']) }}</span>
                </div>
            </div>
            @endif
        </div>

        {{-- Right 1 col: Original-Assumption Tracking (Section 20) --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="text-base">🧭</span>
                        <h2 class="text-sm font-black uppercase tracking-wider text-slate-200">INCEPTION VS CURRENT</h2>
                    </div>
                    <span class="text-[10px] uppercase px-2 py-0.5 rounded bg-slate-800 text-slate-400 font-bold">Thesis Tracker</span>
                </div>

                <div class="mt-3 text-xs text-slate-300">
                    <p class="text-[11px] text-slate-400 mb-2">
                        Transparent audit answering: <span class="text-cyan-300 font-bold">"What has changed since I entered?"</span>
                    </p>

                    @if(isset($analytics['assumptions_comparison']))
                    <div class="overflow-x-auto">
                        <table class="w-full text-left font-mono-num text-xs">
                            <thead>
                                <tr class="text-[10px] uppercase text-slate-500 border-b border-slate-800">
                                    <th class="py-1.5 font-bold">Metric</th>
                                    <th class="py-1.5 font-bold">Entry</th>
                                    <th class="py-1.5 font-bold">Current</th>
                                    <th class="py-1.5 font-bold text-right">Drift</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60">
                                <tr>
                                    <td class="py-1.5 font-sans font-semibold text-slate-300">Underlying Spot</td>
                                    <td class="py-1.5 text-slate-400">{{ $analytics['assumptions_comparison']['spot']['entry'] }}</td>
                                    <td class="py-1.5 text-slate-200 font-bold">{{ $analytics['assumptions_comparison']['spot']['current'] }}</td>
                                    <td class="py-1.5 text-right font-bold {{ $analytics['assumptions_comparison']['spot']['change'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                        {{ $analytics['assumptions_comparison']['spot']['change'] >= 0 ? '+' : '' }}{{ $analytics['assumptions_comparison']['spot']['change'] }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="py-1.5 font-sans font-semibold text-slate-300">Implied Vol (IV)</td>
                                    <td class="py-1.5 text-slate-400">{{ $analytics['assumptions_comparison']['iv']['entry'] }}%</td>
                                    <td class="py-1.5 text-slate-200 font-bold">{{ $analytics['assumptions_comparison']['iv']['current'] }}%</td>
                                    <td class="py-1.5 text-right font-bold {{ $analytics['assumptions_comparison']['iv']['change'] >= 0 ? 'text-amber-400' : 'text-cyan-400' }}">
                                        {{ $analytics['assumptions_comparison']['iv']['change'] >= 0 ? '+' : '' }}{{ $analytics['assumptions_comparison']['iv']['change'] }}%
                                    </td>
                                </tr>
                                <tr>
                                    <td class="py-1.5 font-sans font-semibold text-slate-300">Net Delta (&Delta;)</td>
                                    <td class="py-1.5 text-slate-400">{{ $analytics['assumptions_comparison']['delta']['entry'] }}</td>
                                    <td class="py-1.5 text-slate-200 font-bold">{{ $analytics['assumptions_comparison']['delta']['current'] }}</td>
                                    <td class="py-1.5 text-right font-bold text-purple-300">
                                        {{ $analytics['assumptions_comparison']['delta']['change'] >= 0 ? '+' : '' }}{{ $analytics['assumptions_comparison']['delta']['change'] }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="py-1.5 font-sans font-semibold text-slate-300">Gamma (&Gamma;)</td>
                                    <td class="py-1.5 text-slate-400">{{ $analytics['assumptions_comparison']['gamma']['entry'] }}</td>
                                    <td class="py-1.5 text-slate-200 font-bold">{{ $analytics['assumptions_comparison']['gamma']['current'] }}</td>
                                    <td class="py-1.5 text-right font-bold text-slate-400">
                                        {{ $analytics['assumptions_comparison']['gamma']['change'] }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="py-1.5 font-sans font-semibold text-slate-300">Theta Decay (&Theta;)</td>
                                    <td class="py-1.5 text-slate-400">{{ $analytics['assumptions_comparison']['theta']['entry'] }}</td>
                                    <td class="py-1.5 text-slate-200 font-bold">{{ $analytics['assumptions_comparison']['theta']['current'] }}</td>
                                    <td class="py-1.5 text-right font-bold {{ $analytics['assumptions_comparison']['theta']['change'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                        {{ $analytics['assumptions_comparison']['theta']['change'] >= 0 ? '+' : '' }}{{ $analytics['assumptions_comparison']['theta']['change'] }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="py-1.5 font-sans font-semibold text-slate-300">1-&sigma; Expected Move</td>
                                    <td class="py-1.5 text-slate-400">±{{ $analytics['assumptions_comparison']['expected_move']['entry'] }}</td>
                                    <td class="py-1.5 text-slate-200 font-bold">±{{ $analytics['assumptions_comparison']['expected_move']['current'] }}</td>
                                    <td class="py-1.5 text-right font-bold text-cyan-300">
                                        {{ $analytics['assumptions_comparison']['expected_move']['change'] >= 0 ? '+' : '' }}{{ $analytics['assumptions_comparison']['expected_move']['change'] }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Volatility Risk Premium Callout --}}
            @if(isset($analytics['volatility']))
            <div class="mt-4 pt-3 border-t border-slate-800 bg-slate-950/40 rounded-xl p-2.5 text-[11px]">
                <div class="text-slate-400 font-semibold">VRP CONTEXT:</div>
                <div class="text-slate-200 mt-0.5">
                    {{ $analytics['volatility']['context']['vrp_status'] ?? '' }}
                </div>
                <div class="text-[10px] text-slate-500 mt-1">
                    Ref: {{ $analytics['volatility']['context']['reference_methodology'] ?? '' }}
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- 5. STRATEGY-AGNOSTIC LEGS & GREEK CONTRIBUTIONS (Section 6 & 21)       --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <div class="flex items-center gap-2">
                <span class="text-base">🧩</span>
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-200">STRATEGY LEGS &amp; GREEK EXPOSURE</h2>
            </div>
            <div class="text-xs text-slate-400 font-mono-num">
                Absolute Delta Exposure: <span class="text-cyan-300 font-bold">{{ $analytics['exposure']['absolute_delta'] ?? 0 }}</span>
            </div>
        </div>

        <div class="overflow-x-auto mt-3">
            <table class="w-full text-left font-mono-num text-xs">
                <thead>
                    <tr class="text-[10px] uppercase text-slate-400 border-b border-slate-800 bg-slate-950/40">
                        <th class="py-2 px-3">Leg</th>
                        <th class="py-2 px-3">Side</th>
                        <th class="py-2 px-3">Type</th>
                        <th class="py-2 px-3">Strike</th>
                        <th class="py-2 px-3">Qty</th>
                        <th class="py-2 px-3">Entry</th>
                        <th class="py-2 px-3">LTP</th>
                        <th class="py-2 px-3">Leg P&amp;L</th>
                        <th class="py-2 px-3">&Delta; Delta</th>
                        <th class="py-2 px-3">&Delta; % Share</th>
                        <th class="py-2 px-3">&Gamma; Gamma</th>
                        <th class="py-2 px-3">&Theta; Theta/d</th>
                        <th class="py-2 px-3">&nu; Vega/1%</th>
                        <th class="py-2 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @if(isset($analytics['exposure']['leg_contributions']))
                    @foreach($analytics['exposure']['leg_contributions'] as $idx => $leg)
                    @php
                        $rawLeg = $analytics['legs'][$leg['index']] ?? null;
                        $legPnl = ($rawLeg && isset($rawLeg['current_price']) && isset($rawLeg['entry_price']))
                            ? ($leg['side'] === 'BUY'
                                ? ($rawLeg['current_price'] - $rawLeg['entry_price']) * $leg['quantity']
                                : ($rawLeg['entry_price'] - $rawLeg['current_price']) * $leg['quantity'])
                            : 0;
                    @endphp
                    <tr class="hover:bg-slate-800/40 transition">
                        <td class="py-2.5 px-3 text-slate-400 font-bold">#{{ $idx + 1 }}</td>
                        <td class="py-2.5 px-3">
                            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase
                                {{ $leg['side'] === 'BUY' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30' }}">
                                {{ $leg['side'] }}
                            </span>
                        </td>
                        <td class="py-2.5 px-3">
                            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase
                                {{ $leg['option_type'] === 'CE' ? 'bg-cyan-500/20 text-cyan-300' : 'bg-blue-500/20 text-blue-300' }}">
                                {{ $leg['option_type'] }}
                            </span>
                        </td>
                        <td class="py-2.5 px-3 font-bold text-white">{{ number_format($leg['strike'], 0) }}</td>
                        <td class="py-2.5 px-3 text-slate-300">{{ $leg['quantity'] }}</td>
                        <td class="py-2.5 px-3 text-slate-400">&#8377;{{ number_format($rawLeg['entry_price'] ?? 0, 2) }}</td>
                        <td class="py-2.5 px-3 font-bold text-slate-200">&#8377;{{ number_format($rawLeg['current_price'] ?? 0, 2) }}</td>
                        <td class="py-2.5 px-3 font-bold {{ $legPnl >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ $legPnl >= 0 ? '+' : '' }}&#8377;{{ number_format($legPnl, 2) }}
                        </td>
                        <td class="py-2.5 px-3 font-bold {{ $leg['delta'] >= 0 ? 'text-cyan-400' : 'text-rose-400' }}">
                            {{ $leg['delta'] >= 0 ? '+' : '' }}{{ $leg['delta'] }}
                        </td>
                        <td class="py-2.5 px-3 text-slate-400">
                            {{ $leg['delta_contribution_pct'] }}%
                        </td>
                        <td class="py-2.5 px-3 text-slate-400">{{ $leg['gamma'] }}</td>
                        <td class="py-2.5 px-3 {{ $leg['theta'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ $leg['theta'] >= 0 ? '+' : '' }}{{ $leg['theta'] }}
                        </td>
                        <td class="py-2.5 px-3 text-slate-400">{{ $leg['vega'] }}</td>
                        <td class="py-2.5 px-3 text-right">
                            <button type="button"
                                    @click="openSimulatorWithLeg({{ $leg['index'] }}, {{ $leg['strike'] }})"
                                    class="text-[11px] font-sans font-semibold text-cyan-400 hover:text-cyan-300 underline">
                                Simulate Roll
                            </button>
                        </td>
                    </tr>
                    @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- 6. INTERACTIVE SCENARIO ANALYSIS MATRIX (Section 15)                  --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if(isset($analytics['scenarios']))
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl">
        <div class="flex flex-wrap items-center justify-between pb-3 border-b border-slate-800 gap-2">
            <div class="flex items-center gap-2">
                <span class="text-base">🎲</span>
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-200">SCENARIO MATRIX (SPOT &times; IV SHIFT)</h2>
                <span class="text-[10px] text-slate-400">Decision-Support Stress Simulation</span>
            </div>
            
            {{-- Metric Display Mode Toggle --}}
            <div class="flex items-center gap-1 bg-slate-950 p-1 rounded-xl border border-slate-800 text-xs">
                <button type="button"
                        @click="scenarioMetric = 'pnl'"
                        :class="scenarioMetric === 'pnl' ? 'bg-cyan-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                        class="px-2.5 py-1 rounded-lg transition">
                    P&amp;L (₹)
                </button>
                <button type="button"
                        @click="scenarioMetric = 'delta'"
                        :class="scenarioMetric === 'delta' ? 'bg-cyan-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                        class="px-2.5 py-1 rounded-lg transition">
                    Net Delta (&Delta;)
                </button>
                <button type="button"
                        @click="scenarioMetric = 'vega'"
                        :class="scenarioMetric === 'vega' ? 'bg-cyan-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                        class="px-2.5 py-1 rounded-lg transition">
                    Vega (&nu;)
                </button>
            </div>
        </div>

        <div class="overflow-x-auto mt-4 font-mono-num">
            <table class="w-full text-center text-xs border-collapse">
                <thead>
                    <tr class="text-[11px] text-slate-400 border-b border-slate-800 bg-slate-950/60">
                        <th class="py-2.5 px-3 text-left font-bold text-slate-300">Spot Shift \ IV Shift</th>
                        @foreach($analytics['scenarios']['iv_changes'] as $ivCol)
                        <th class="py-2.5 px-3 font-bold">{{ $ivCol }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @foreach($analytics['scenarios']['matrix'] as $spotShift => $row)
                    @php
                        $simSpotVal = $quote->spot + (float)$spotShift;
                    @endphp
                    <tr class="hover:bg-slate-800/30 transition">
                        <td class="py-2.5 px-3 text-left font-bold text-slate-200 bg-slate-950/30">
                            {{ (float)$spotShift >= 0 ? '+' : '' }}{{ $spotShift }} pts
                            <span class="text-[10px] text-slate-500 font-normal">({{ number_format($simSpotVal, 0) }})</span>
                        </td>
                        @foreach($row as $ivShift => $cell)
                        <td class="py-2.5 px-3">
                            {{-- PnL Metric --}}
                            <template x-if="scenarioMetric === 'pnl'">
                                <span class="px-2 py-1 rounded-md font-bold block
                                    {{ $cell['pnl'] > 200 ? 'bg-emerald-950/80 text-emerald-400 border border-emerald-500/30' : '' }}
                                    {{ $cell['pnl'] < -200 ? 'bg-rose-950/80 text-rose-400 border border-rose-500/30' : '' }}
                                    {{ abs($cell['pnl']) <= 200 ? 'text-slate-300' : '' }}
                                ">
                                    {{ $cell['pnl'] >= 0 ? '+' : '' }}₹{{ number_format($cell['pnl'], 0) }}
                                </span>
                            </template>

                            {{-- Delta Metric --}}
                            <template x-if="scenarioMetric === 'delta'">
                                <span class="font-bold text-cyan-300">
                                    {{ $cell['delta'] >= 0 ? '+' : '' }}{{ $cell['delta'] }}
                                </span>
                            </template>

                            {{-- Vega Metric --}}
                            <template x-if="scenarioMetric === 'vega'">
                                <span class="text-amber-300">
                                    {{ $cell['vega'] }}
                                </span>
                            </template>
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- 7. ACTION SIMULATOR SANDBOX (Section 24)                              --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl">
        <div class="flex flex-wrap items-center justify-between pb-3 border-b border-slate-800 gap-2">
            <div class="flex items-center gap-2">
                <span class="text-base">🧪</span>
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-200">ACTION SIMULATOR SANDBOX</h2>
                <span class="text-[10px] px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 border border-amber-500/30 font-bold">
                    Hypothetical Only &bull; Zero Auto-Execution
                </span>
            </div>
            <div class="text-xs text-slate-400">
                Simulate Before vs After risk transitions prior to taking market actions.
            </div>
        </div>

        {{-- Simulator Control Bar --}}
        <div class="mt-4 p-3 bg-slate-950/60 rounded-xl border border-slate-800 grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
                <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block mb-1">Action Type</label>
                <select x-model="simAction.type"
                        class="w-full bg-slate-800 border border-slate-700 rounded-xl px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-cyan-500 font-medium">
                    <option value="roll_strike">Roll Leg Strike</option>
                    <option value="add_hedge">Add Protective Hedge Wing</option>
                    <option value="change_quantity">Change Leg Quantity</option>
                    <option value="close_leg">Square-off / Close Single Leg</option>
                    <option value="close_position">Close Entire Position</option>
                </select>
            </div>

            {{-- Dynamic parameters based on action type --}}
            <template x-if="simAction.type === 'roll_strike' || simAction.type === 'change_quantity' || simAction.type === 'close_leg'">
                <div>
                    <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block mb-1">Target Leg</label>
                    <select x-model.number="simAction.leg_index"
                            class="w-full bg-slate-800 border border-slate-700 rounded-xl px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-cyan-500 font-medium">
                        @if(isset($analytics['legs']))
                        @foreach($analytics['legs'] as $lIdx => $l)
                        <option value="{{ $lIdx }}">Leg #{{ $lIdx + 1 }} ({{ $l['side'] }} {{ $l['option_type'] }} {{ $l['strike'] }})</option>
                        @endforeach
                        @endif
                    </select>
                </div>
            </template>

            <template x-if="simAction.type === 'roll_strike'">
                <div>
                    <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block mb-1">New Strike</label>
                    <input type="number" x-model.number="simAction.new_strike" step="50"
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-cyan-500 font-mono-num font-bold">
                </div>
            </template>

            <template x-if="simAction.type === 'add_hedge'">
                <div>
                    <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block mb-1">Hedge Type &amp; Strike</label>
                    <div class="flex gap-2">
                        <select x-model="simAction.hedge_type" class="bg-slate-800 border border-slate-700 rounded-xl px-2 py-1.5 text-xs text-slate-200">
                            <option value="PE">BUY PE</option>
                            <option value="CE">BUY CE</option>
                        </select>
                        <input type="number" x-model.number="simAction.hedge_strike" step="50"
                               class="w-full bg-slate-800 border border-slate-700 rounded-xl px-2 py-1.5 text-xs text-white font-mono-num font-bold">
                    </div>
                </div>
            </template>

            <template x-if="simAction.type === 'change_quantity'">
                <div>
                    <label class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block mb-1">New Quantity</label>
                    <input type="number" x-model.number="simAction.quantity" step="65"
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl px-2.5 py-1.5 text-xs text-white font-mono-num font-bold">
                </div>
            </template>

            <div class="flex items-end">
                <button type="button"
                        @click="runSimulation()"
                        :disabled="isSimulating"
                        class="w-full py-1.5 px-3 rounded-xl font-bold text-xs bg-cyan-600 hover:bg-cyan-500 text-white transition active:scale-95 disabled:opacity-50">
                    <span x-text="isSimulating ? 'Computing...' : 'Simulate Adjustment'"></span>
                </button>
            </div>
        </div>

        {{-- Simulation Results Box (Rendered when simulated) --}}
        <template x-if="simResult">
            <div class="mt-4 pt-4 border-t border-slate-800 space-y-3 font-mono-num">
                <div class="text-xs font-black uppercase text-cyan-400 flex items-center gap-2">
                    <span>🔬</span>
                    <span x-text="simResult.action"></span>
                </div>

                {{-- Before vs After vs Change Table --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-[10px] uppercase text-slate-500 border-b border-slate-800 bg-slate-950/40">
                                <th class="py-2 px-3">Metric</th>
                                <th class="py-2 px-3">Before</th>
                                <th class="py-2 px-3">After</th>
                                <th class="py-2 px-3 text-right">Net Change</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr>
                                <td class="py-2 px-3 font-sans font-semibold text-slate-300">Net Delta (&Delta;)</td>
                                <td class="py-2 px-3 text-slate-400" x-text="simResult.before.delta"></td>
                                <td class="py-2 px-3 font-bold text-cyan-300" x-text="simResult.after.delta"></td>
                                <td class="py-2 px-3 text-right font-bold" x-text="simResult.change.delta_change"></td>
                            </tr>
                            <tr>
                                <td class="py-2 px-3 font-sans font-semibold text-slate-300">Gamma (&Gamma;)</td>
                                <td class="py-2 px-3 text-slate-400" x-text="simResult.before.gamma"></td>
                                <td class="py-2 px-3 font-bold text-slate-200" x-text="simResult.after.gamma"></td>
                                <td class="py-2 px-3 text-right font-bold" x-text="simResult.change.gamma_change"></td>
                            </tr>
                            <tr>
                                <td class="py-2 px-3 font-sans font-semibold text-slate-300">Theta Decay (&Theta;/d)</td>
                                <td class="py-2 px-3 text-slate-400" x-text="simResult.before.theta"></td>
                                <td class="py-2 px-3 font-bold text-emerald-400" x-text="simResult.after.theta"></td>
                                <td class="py-2 px-3 text-right font-bold text-emerald-400" x-text="simResult.change.theta_change"></td>
                            </tr>
                            <tr>
                                <td class="py-2 px-3 font-sans font-semibold text-slate-300">Vega Exposure (&nu;)</td>
                                <td class="py-2 px-3 text-slate-400" x-text="simResult.before.vega"></td>
                                <td class="py-2 px-3 font-bold text-slate-200" x-text="simResult.after.vega"></td>
                                <td class="py-2 px-3 text-right font-bold" x-text="simResult.change.vega_change"></td>
                            </tr>
                            <tr>
                                <td class="py-2 px-3 font-sans font-semibold text-slate-300">Max Profit</td>
                                <td class="py-2 px-3 text-slate-400" x-text="simResult.before.max_profit ?? 'Unlimited'"></td>
                                <td class="py-2 px-3 font-bold text-emerald-400" x-text="simResult.after.max_profit ?? 'Unlimited'"></td>
                                <td class="py-2 px-3 text-right text-slate-400">&bull;</td>
                            </tr>
                            <tr>
                                <td class="py-2 px-3 font-sans font-semibold text-slate-300">Max Loss</td>
                                <td class="py-2 px-3 text-slate-400" x-text="simResult.before.max_loss ?? 'Unlimited'"></td>
                                <td class="py-2 px-3 font-bold text-rose-400" x-text="simResult.after.max_loss ?? 'Unlimited'"></td>
                                <td class="py-2 px-3 text-right text-slate-400">&bull;</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Explainability: Improved vs Increased Risks --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                    <div class="bg-emerald-950/30 border border-emerald-500/30 rounded-xl p-3 font-sans text-xs">
                        <div class="text-[11px] font-black text-emerald-400 uppercase tracking-wider mb-1">
                            ✅ IMPROVED / DERISKED:
                        </div>
                        <template x-if="simResult.improved.length > 0">
                            <ul class="space-y-1 text-slate-200">
                                <template x-for="imp in simResult.improved" :key="imp">
                                    <li class="flex items-start gap-1.5">
                                        <span class="text-emerald-400">&bull;</span>
                                        <span x-text="imp"></span>
                                    </li>
                                </template>
                            </ul>
                        </template>
                        <template x-if="simResult.improved.length === 0">
                            <span class="text-slate-500 italic">No significant risk improvements identified.</span>
                        </template>
                    </div>

                    <div class="bg-amber-950/30 border border-amber-500/30 rounded-xl p-3 font-sans text-xs">
                        <div class="text-[11px] font-black text-amber-400 uppercase tracking-wider mb-1">
                            ⚠️ INCREASED EXPOSURES / TRADEOFFS:
                        </div>
                        <template x-if="simResult.increased_risks.length > 0">
                            <ul class="space-y-1 text-slate-200">
                                <template x-for="rsk in simResult.increased_risks" :key="rsk">
                                    <li class="flex items-start gap-1.5">
                                        <span class="text-amber-400">&bull;</span>
                                        <span x-text="rsk"></span>
                                    </li>
                                </template>
                            </ul>
                        </template>
                        <template x-if="simResult.increased_risks.length === 0">
                            <span class="text-slate-500 italic">No additional risk expansions noted.</span>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- 8. EXPECTED MOVE & MARKET REGIME & TIMELINE (Sections 16, 18, 45)      --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        
        {{-- Expected Move Engine Comparison --}}
        @if(isset($analytics['expected_move']))
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                <div class="flex items-center gap-2">
                    <span class="text-base">📏</span>
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-200">EXPECTED MOVE ENGINE</h2>
                </div>
                <span class="text-[10px] text-slate-400 uppercase font-mono-num font-bold">Multi-Model</span>
            </div>

            <div class="mt-3 space-y-2 text-xs font-mono-num">
                @foreach($analytics['expected_move']['all'] as $emKey => $em)
                <div class="bg-slate-950/50 border border-slate-800/80 rounded-xl p-2.5 flex justify-between items-center">
                    <div>
                        <div class="font-sans font-bold text-slate-200">{{ $em['methodology'] }}</div>
                        <div class="text-[11px] text-slate-400">
                            Range: [{{ $em['lower_bound'] }} &mdash; {{ $em['upper_bound'] }}]
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-black text-cyan-300">±{{ $em['expected_move'] }} pts</div>
                        <div class="text-[11px] text-slate-400">±{{ $em['expected_move_pct'] }}%</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Audit Timeline & Risk Events --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                <div class="flex items-center gap-2">
                    <span class="text-base">📜</span>
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-200">AUDIT LOG &amp; RISK TIMELINE</h2>
                </div>
                <span class="text-[10px] text-slate-400 font-mono-num font-bold">Provenance Log</span>
            </div>

            <div class="mt-3 space-y-2 text-xs">
                @forelse($timelineEvents as $evt)
                <div class="bg-slate-950/40 border border-slate-800/70 rounded-xl p-2 flex justify-between items-center font-mono-num">
                    <div class="space-y-0.5">
                        <div class="font-sans font-bold text-slate-200">{{ $evt->action }}</div>
                        <div class="text-[11px] text-slate-400">{{ $evt->reason ?? 'System event' }}</div>
                    </div>
                    <span class="text-[10px] text-slate-500 font-bold">
                        {{ \Carbon\Carbon::parse($evt->created_at)->format('H:i:s') }}
                    </span>
                </div>
                @empty
                <div class="text-slate-500 text-xs italic py-4 text-center">
                    Position initialized with clean audit logs.
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- MODAL: PRESET STRATEGY LAUNCHER (Proves Strategy Independence)         --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div x-show="showNewStrategyModal"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full p-5 shadow-2xl space-y-4"
             @click.away="showNewStrategyModal = false">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                <div class="flex items-center gap-2">
                    <span class="text-xl">🚀</span>
                    <h3 class="text-base font-black text-white">Preset Strategy Launcher</h3>
                </div>
                <button type="button" @click="showNewStrategyModal = false" class="text-slate-400 hover:text-white font-bold">&times;</button>
            </div>

            <p class="text-xs text-slate-300">
                Select any strategy definition below. The framework dynamically models all legs, Greeks, breakevens, and risk profiles using strategy-agnostic engines:
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 max-h-[420px] overflow-y-auto pr-1">
                @foreach($registeredStrategies as $strat)
                <div class="bg-slate-950 border border-slate-800 hover:border-cyan-500/50 rounded-xl p-3 transition flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-white">{{ $strat['name'] }}</span>
                            <span class="text-[9px] uppercase px-1.5 py-0.5 rounded font-black bg-slate-800 text-cyan-300">
                                {{ $strat['category'] }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1 line-clamp-2">
                            {{ $strat['description'] }}
                        </p>
                    </div>

                    <div class="mt-3 pt-2 border-t border-slate-800/80 flex items-center justify-between text-[11px]">
                        <span class="text-slate-500 font-mono-num">{{ $strat['characteristics']['leg_count'] }} Legs</span>
                        <button type="button"
                                @click="createFromPreset('{{ $strat['key'] }}')"
                                class="px-2.5 py-1 rounded-lg text-xs font-bold bg-cyan-600 hover:bg-cyan-500 text-white transition">
                            Load Strategy &rarr;
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
function optionsAnalyticsApp() {
    return {
        showNewStrategyModal: false,
        scenarioMetric: 'pnl',
        isSimulating: false,
        simResult: null,
        simAction: {
            type: 'roll_strike',
            leg_index: 0,
            new_strike: {{ (float)($quote->spot ?? 25250) }},
            hedge_type: 'PE',
            hedge_strike: {{ (float)($quote->spot ?? 25250) - 200 }},
            quantity: 65,
        },

        init() {
            this.renderPayoffChart();
        },

        openSimulatorWithLeg(idx, strike) {
            this.simAction.type = 'roll_strike';
            this.simAction.leg_index = idx;
            this.simAction.new_strike = strike;
            window.location.hash = 'analytics-app';
        },

        async runSimulation() {
            this.isSimulating = true;
            try {
                const response = await fetch('/api/options-analytics/{{ $activePosition?->id ?? 1 }}/simulate-action', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ action: this.simAction })
                });
                const res = await response.json();
                if (res.success) {
                    this.simResult = res.data;
                }
            } catch (err) {
                console.error(err);
            } finally {
                this.isSimulating = false;
            }
        },

        async createFromPreset(stratKey) {
            try {
                const response = await fetch('/api/options-analytics/create-from-strategy', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        strategy_key: stratKey,
                        underlying_symbol: '{{ $quote->symbol }}'
                    })
                });
                const res = await response.json();
                if (res.success && res.redirect_url) {
                    window.location.href = res.redirect_url;
                }
            } catch (err) {
                console.error(err);
            }
        },

        renderPayoffChart() {
            const canvas = document.getElementById('payoffChart');
            if (!canvas) return;

            const existingChart = typeof Chart !== 'undefined' ? Chart.getChart(canvas) : null;
            if (existingChart) {
                existingChart.destroy();
            }

            const payoffData = @json($analytics['payoff'] ?? []);
            if (!payoffData.points || payoffData.points.length === 0) return;

            const labels = payoffData.points.map(p => p.spot);
            const expiryPnl = payoffData.points.map(p => p.pnl_expiry);
            const t0Pnl = payoffData.points.map(p => p.pnl_t0);
            const currentSpot = payoffData.current_spot || {{ $quote->spot }};
            const breakevens = payoffData.breakevens?.breakevens || [];

            new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Expiry P&L',
                            data: expiryPnl,
                            borderColor: '#34d399', // emerald
                            backgroundColor: (context) => {
                                const chart = context.chart;
                                const {ctx, chartArea} = chart;
                                if (!chartArea) return null;
                                return 'rgba(52, 211, 153, 0.08)';
                            },
                            fill: true,
                            borderWidth: 2.5,
                            tension: 0.1,
                            pointRadius: 0,
                            pointHoverRadius: 5,
                        },
                        {
                            label: 'T+0 Model P&L',
                            data: t0Pnl,
                            borderColor: '#22d3ee', // cyan
                            borderDash: [5, 4],
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            tension: 0.3,
                            pointRadius: 0,
                            pointHoverRadius: 5,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            borderColor: '#334155',
                            borderWidth: 1,
                            titleColor: '#e2e8f0',
                            bodyColor: '#94a3b8',
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ₹' + Number(context.raw).toLocaleString('en-IN', {maximumFractionDigits: 1});
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(51, 65, 85, 0.3)' },
                            ticks: {
                                color: '#94a3b8',
                                font: { family: 'monospace', size: 10 },
                                maxTicksLimit: 12,
                            }
                        },
                        y: {
                            grid: { color: 'rgba(51, 65, 85, 0.4)' },
                            ticks: {
                                color: '#94a3b8',
                                font: { family: 'monospace', size: 10 },
                                callback: function(val) {
                                    return '₹' + val.toLocaleString('en-IN');
                                }
                            }
                        }
                    }
                }
            });
        }
    };
}
</script>
@endpush
@endsection
