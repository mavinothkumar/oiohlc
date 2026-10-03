@extends('layouts.app')

@section('title', 'Strategy Premium & Greek Velocity Analytics')

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
    [x-cloak] { display: none !important; }
    .font-mono-num { font-variant-numeric: tabular-nums; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
    .custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #090d16; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #334155; }
</style>
@endpush

@section('content')
<div class="w-full max-w-[1780px] mx-auto px-2 sm:px-4 py-2 space-y-2.5 text-slate-100 font-sans"
     x-data="strategyPremiumApp()"
     id="strategy-premium-root">

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- 1. SLEEK ULTRA-COMPACT SINGLE-ROW FILTER LINE                          --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <form method="GET" action="{{ route('strategy.premium.analytics') }}"
          class="bg-slate-900/95 border border-slate-800 rounded-xl px-3 py-2 shadow-lg backdrop-blur-md">
        <input type="hidden" name="symbol" value="NIFTY">

        <div class="flex items-center justify-between gap-2 overflow-x-auto custom-scrollbar py-0.5 text-xs">
            
            {{-- Left Controls: Mode & Strategy/Strikes Selection --}}
            <div class="flex items-center gap-2 flex-shrink-0">
                {{-- Compact Mode Switch (Strategy vs Manual) --}}
                <div class="inline-flex bg-slate-950 p-0.5 rounded-lg border border-slate-800 font-bold shadow-inner">
                    <label class="px-2.5 py-1 rounded cursor-pointer transition text-[11px] {{ $mode === 'strategy' ? 'bg-teal-600 text-white shadow' : 'text-slate-400 hover:text-white' }}">
                        <input type="radio" name="mode" value="strategy" class="hidden" onchange="this.form.submit()" {{ $mode === 'strategy' ? 'checked' : '' }}>
                        <span>⚡ Strategy</span>
                    </label>
                    <label class="px-2.5 py-1 rounded cursor-pointer transition text-[11px] {{ $mode === 'manual' ? 'bg-teal-600 text-white shadow' : 'text-slate-400 hover:text-white' }}">
                        <input type="radio" name="mode" value="manual" class="hidden" onchange="this.form.submit()" {{ $mode === 'manual' ? 'checked' : '' }}>
                        <span>🎯 Manual</span>
                    </label>
                </div>

                @if($mode === 'strategy')
                    {{-- Strategy Selector --}}
                    <div class="flex items-center gap-1.5 bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1 focus-within:border-teal-500 transition">
                        <span class="text-[10px] uppercase font-bold text-slate-400">Strategy:</span>
                        <select name="strategy_id" onchange="this.form.submit()"
                                class="bg-transparent text-xs font-bold text-white focus:outline-none cursor-pointer">
                            @foreach($backtestStrategies as $bStrat)
                                <option value="{{ $bStrat->id }}" class="bg-slate-900 text-white" {{ $selectedStrategyId == $bStrat->id ? 'selected' : '' }}>
                                    {{ $bStrat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- ATM Strike Selector with Custom Badge --}}
                    <div class="flex items-center gap-1.5 bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1 focus-within:border-teal-500 transition"
                         title="Strategy ATM Center Point. Auto uses market open spot price. Selecting a custom strike shifts all strategy legs proportionally.">
                        <span class="text-[10px] text-slate-400 font-bold uppercase">ATM:</span>
                        <select name="custom_atm" onchange="this.form.submit()"
                                class="bg-transparent text-xs font-mono-num font-extrabold text-cyan-300 focus:outline-none cursor-pointer">
                            <option value="" class="bg-slate-900 text-slate-300">Auto ({{ $calculatedAtm ?? $analytics['atm_strike'] }})</option>
                            @foreach($allStrikes as $stk)
                                <option value="{{ $stk }}" class="bg-slate-900 text-white" {{ ($customAtm !== null && (float)$customAtm === (float)$stk) ? 'selected' : '' }}>
                                    {{ $stk }} {{ ($calculatedAtm == $stk) ? '★' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @if(!empty($analytics['is_custom_atm']))
                            <span class="text-[9px] text-amber-300 uppercase font-black px-1.5 py-0.2 rounded bg-amber-950/80 border border-amber-800">Custom</span>
                        @endif
                    </div>
                @else
                    {{-- Manual Mode Trigger Button --}}
                    <button type="button" @click="showManualModal = true"
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-slate-950 border border-slate-800 text-teal-300 hover:border-teal-500 font-bold transition">
                        <span>🎯 Strikes:</span>
                        <span class="text-white font-mono-num">{{ count($manualCallStrikes) + count($manualPutStrikes) }} Selected</span>
                        <span class="text-[10px] text-slate-400">&rarr;</span>
                    </button>
                @endif
            </div>

            {{-- Middle Controls: Date, Expiry, Time, Height --}}
            <div class="flex items-center gap-2 flex-shrink-0">
                {{-- Date --}}
                <div class="flex items-center gap-1.5 bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1 focus-within:border-teal-500 transition">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Date:</span>
                    <select name="date" onchange="this.form.submit()"
                            class="bg-transparent text-xs font-mono-num font-bold text-white focus:outline-none cursor-pointer">
                        @foreach($availableDates as $ad)
                            <option value="{{ $ad }}" class="bg-slate-900 text-white" {{ $selectedDate == $ad ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::parse($ad)->format('d M Y') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Expiry --}}
                <div class="flex items-center gap-1.5 bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1 focus-within:border-teal-500 transition">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Expiry:</span>
                    <select name="expiry" onchange="this.form.submit()"
                            class="bg-transparent text-xs font-mono-num font-bold text-emerald-300 focus:outline-none cursor-pointer">
                        @foreach($availableExpiries as $exp)
                            <option value="{{ $exp }}" class="bg-slate-900 text-emerald-300" {{ $selectedExpiry == $exp ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::parse($exp)->format('d M Y') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Time Range --}}
                <div class="flex items-center gap-1 bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1 font-mono-num">
                    <span class="text-[10px] text-slate-400 font-bold uppercase mr-1">Time:</span>
                    <span class="text-slate-400 text-xs">{{ $startTime }} &rarr;</span>
                    <input type="text" name="end_time" value="{{ $endTime }}" placeholder="15:30"
                           class="w-12 bg-slate-900 border border-slate-700 rounded px-1 py-0.5 text-xs text-amber-300 font-mono-num text-center focus:outline-none focus:border-teal-500">
                </div>

                {{-- Height --}}
                <div class="flex items-center gap-1 bg-slate-950 border border-slate-800 rounded-lg px-2 py-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Height:</span>
                    <select name="chart_height" x-model="chartHeight" @change="resizeChart()"
                            class="bg-transparent text-xs font-bold text-cyan-300 focus:outline-none cursor-pointer">
                        <option value="450" class="bg-slate-900 text-white" {{ $chartHeight == 450 ? 'selected' : '' }}>450px</option>
                        <option value="550" class="bg-slate-900 text-white" {{ $chartHeight == 550 ? 'selected' : '' }}>550px</option>
                        <option value="650" class="bg-slate-900 text-white" {{ $chartHeight == 650 ? 'selected' : '' }}>650px</option>
                        <option value="800" class="bg-slate-900 text-white" {{ $chartHeight == 800 ? 'selected' : '' }}>800px</option>
                    </select>
                </div>
            </div>

            {{-- Right: Submit CTA --}}
            <div class="flex items-center flex-shrink-0">
                <button type="submit"
                        class="px-4 py-1.5 rounded-lg font-black text-xs bg-gradient-to-r from-teal-500 via-emerald-500 to-cyan-500 hover:from-teal-400 hover:to-cyan-400 text-slate-950 shadow-md shadow-teal-500/10 transition active:scale-95 flex items-center gap-1 whitespace-nowrap">
                    <span>⚡ Load Analysis</span>
                </button>
            </div>
        </div>

        {{-- Hidden fields for manual mode if active --}}
        @if($mode === 'manual')
            @foreach($manualCallStrikes as $i => $cs)
                <input type="hidden" name="call_strikes[]" value="{{ $cs }}">
                <input type="hidden" name="call_lots[]" value="{{ $manualCallLots[$i] ?? 1 }}">
            @endforeach
            @foreach($manualPutStrikes as $i => $ps)
                <input type="hidden" name="put_strikes[]" value="{{ $ps }}">
                <input type="hidden" name="put_lots[]" value="{{ $manualPutLots[$i] ?? 1 }}">
            @endforeach
        @endif
    </form>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- 2. SLEEK COLLAPSIBLE STRATEGY HEALTH & BASKET BAR (RELOAD-PERSISTENT)  --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if(!empty($analytics['summary']))
    @php
        $j = $analytics['judgment'];
        $statusBorderColors = [
            'emerald' => 'border-l-4 border-l-emerald-500',
            'amber' => 'border-l-4 border-l-amber-500',
            'rose' => 'border-l-4 border-l-rose-500',
            'cyan' => 'border-l-4 border-l-cyan-500',
            'slate' => 'border-l-4 border-l-slate-500',
        ];
        $badgeColors = [
            'emerald' => 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30',
            'amber' => 'bg-amber-500/10 text-amber-400 border border-amber-500/30',
            'rose' => 'bg-rose-500/10 text-rose-400 border border-rose-500/30',
            'cyan' => 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30',
            'slate' => 'bg-slate-800 text-slate-300 border border-slate-700',
        ];
        $accentClass = $statusBorderColors[$j['status_color']] ?? $statusBorderColors['emerald'];
        $badgeClass = $badgeColors[$j['status_color']] ?? $badgeColors['emerald'];
    @endphp
    <div class="bg-slate-900 border border-slate-800 rounded-xl shadow-lg backdrop-blur-md overflow-hidden transition-all duration-300 {{ $accentClass }}">
        
        {{-- ALWAYS-VISIBLE SLEEK STRATEGY HEALTH SHOW/HIDE BAR --}}
        <div class="p-2 sm:px-3 flex flex-wrap items-center justify-between gap-2.5 cursor-pointer select-none hover:bg-slate-850 transition"
             @click="toggleHealth()">
            
            {{-- Left: Status & Key Headline --}}
            <div class="flex items-center flex-wrap gap-2">
                <span class="text-[10px] uppercase font-extrabold tracking-wider text-slate-400">STRATEGY HEALTH:</span>
                
                {{-- Status Badge --}}
                <span class="px-2.5 py-0.5 rounded-full text-xs font-black uppercase tracking-wide {{ $badgeClass }} flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full {{ $j['status_color'] === 'emerald' ? 'bg-emerald-400' : ($j['status_color'] === 'amber' ? 'bg-amber-400' : 'bg-rose-400') }}"></span>
                    {{ $j['status'] }}
                </span>

                {{-- Headline --}}
                <span class="text-xs sm:text-sm font-extrabold text-white">
                    &bull; {{ $j['headline'] }}
                </span>
            </div>

            {{-- Right: Essential Metrics & Show/Hide Toggle Button --}}
            <div class="flex items-center flex-wrap gap-2 text-xs font-mono-num ml-auto">
                {{-- Quick Metric: Net Decay --}}
                <span class="px-2 py-0.5 rounded-lg bg-slate-950 border border-slate-800 text-[11px] font-bold">
                    <span class="text-slate-400 uppercase text-[9px] mr-1">Decay:</span>
                    <span class="{{ $analytics['summary']['net_decay_points'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                        {{ $analytics['summary']['net_decay_points'] >= 0 ? '+' : '' }}{{ $analytics['summary']['net_decay_points'] }} pts
                    </span>
                    <span class="text-slate-500 text-[10px]">({{ $analytics['summary']['decay_pct'] }}%)</span>
                </span>

                {{-- Quick Metric: Velocity --}}
                <span class="px-2 py-0.5 rounded-lg bg-slate-950 border border-slate-800 text-[11px] font-bold text-cyan-300">
                    <span class="text-slate-400 uppercase text-[9px] mr-1">Velocity:</span>
                    {{ $analytics['summary']['decay_velocity'] }} pts/h
                </span>

                {{-- Quick Metric: Net Delta --}}
                <span class="px-2 py-0.5 rounded-lg bg-slate-950 border border-slate-800 text-[11px] font-bold {{ abs($analytics['summary']['latest_delta']) < 20 ? 'text-emerald-400' : 'text-amber-400' }}">
                    <span class="text-slate-400 uppercase text-[9px] mr-1">Net &Delta;:</span>
                    {{ $analytics['summary']['latest_delta'] >= 0 ? '+' : '' }}{{ $analytics['summary']['latest_delta'] }}
                </span>

                {{-- Basket Summary --}}
                <span class="px-2 py-0.5 rounded-lg bg-slate-950 border border-slate-800 text-[11px] font-semibold text-slate-300">
                    {{ $analytics['strategy_name'] }} ({{ $analytics['total_lots'] }}L)
                </span>

                {{-- Show / Hide Toggle Button --}}
                <button type="button"
                        class="px-2.5 py-1 rounded-lg font-bold text-xs bg-slate-950 hover:bg-slate-800 border border-slate-700 text-teal-300 transition flex items-center gap-1 active:scale-95 shadow">
                    <span x-text="showHealth ? 'Hide Details' : 'Show Details'"></span>
                    <span class="text-[9px]" x-text="showHealth ? '▲' : '▼'"></span>
                </button>
            </div>
        </div>

        {{-- COLLAPSIBLE BODY (DETAILED GUIDANCE, CHIPS, BASKET LEGS & BREAKDOWN) --}}
        <div x-show="showHealth"
             x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="p-3 pt-0 border-t border-slate-800/80 space-y-3">
            
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 pt-3">
                {{-- Actionable Suggestion & Drivers (Left 8 cols) --}}
                <div class="lg:col-span-8 space-y-2">
                    <div class="bg-slate-950/90 border border-slate-800/90 rounded-xl p-3 flex items-start gap-2.5">
                        <span class="text-base flex-shrink-0 mt-0.5">💡</span>
                        <div class="space-y-0.5">
                            <span class="text-[10px] uppercase font-black tracking-wider text-teal-400 block">ACTIONABLE RECOMMENDATION</span>
                            <p class="text-xs sm:text-sm font-semibold text-slate-100 leading-relaxed">
                                {{ $j['actionable_suggestion'] }}
                            </p>
                        </div>
                    </div>

                    {{-- Contributing Factor Chips --}}
                    <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                        @foreach($j['reasons'] as $r)
                            <span class="px-2.5 py-1 rounded-lg bg-slate-950/60 border border-slate-800 text-[11px] font-medium text-slate-300 flex items-center gap-1.5">
                                <span class="text-teal-400 font-bold">&bull;</span>
                                <span>{{ $r }}</span>
                            </span>
                        @endforeach
                    </div>
                </div>

                {{-- Detailed Execution KPIs & Audit Trigger (Right 4 cols) --}}
                <div class="lg:col-span-4 flex flex-col justify-between gap-2.5 border-t lg:border-t-0 lg:border-l border-slate-800 pt-2 lg:pt-0 lg:pl-3">
                    <div class="grid grid-cols-3 gap-2 font-mono-num text-xs text-center">
                        <div class="bg-slate-950 border border-slate-800 rounded-xl p-2">
                            <span class="text-[9px] uppercase font-bold text-slate-400 block">NET DECAY</span>
                            <span class="text-sm font-black {{ $analytics['summary']['net_decay_points'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                {{ $analytics['summary']['net_decay_points'] >= 0 ? '+' : '' }}{{ $analytics['summary']['net_decay_points'] }}
                            </span>
                        </div>
                        <div class="bg-slate-950 border border-slate-800 rounded-xl p-2">
                            <span class="text-[9px] uppercase font-bold text-slate-400 block">VELOCITY</span>
                            <span class="text-sm font-black text-cyan-300">
                                {{ $analytics['summary']['decay_velocity'] }}
                            </span>
                        </div>
                        <div class="bg-slate-950 border border-slate-800 rounded-xl p-2">
                            <span class="text-[9px] uppercase font-bold text-slate-400 block">THETA</span>
                            <span class="text-sm font-black text-teal-300">
                                +{{ round($analytics['summary']['latest_theta']) }}
                            </span>
                        </div>
                    </div>

                    <button type="button"
                            @click="showTable = !showTable"
                            class="w-full py-2 px-3 rounded-xl font-bold text-xs bg-slate-950 hover:bg-slate-800 border border-slate-700/80 text-teal-300 shadow transition flex items-center justify-between active:scale-98">
                        <span class="flex items-center gap-1.5">
                            <span>⏱️</span>
                            <span x-text="showTable ? 'Hide Time Progression Table' : 'View Time Progression Table'"></span>
                        </span>
                        <span class="text-[10px] px-2 py-0.5 rounded bg-slate-900 border border-slate-800 text-slate-400"
                              x-text="showTable ? '▲' : '▼'"></span>
                    </button>
                </div>
            </div>

            {{-- Resolved Basket Info & Leg Pills --}}
            <div class="pt-2.5 border-t border-slate-800/80 flex items-center justify-between text-xs flex-wrap gap-2">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[10px] uppercase font-extrabold text-slate-400 tracking-wider">RESOLVED BASKET:</span>
                    <span class="font-bold text-white">{{ $analytics['strategy_name'] }}</span>
                    <span class="text-slate-400 font-mono-num font-bold">({{ $analytics['total_lots'] }} Lots Total)</span>
                    <span class="text-[11px] px-2 py-0.5 rounded-lg bg-slate-950 border border-slate-800 text-cyan-300 font-mono-num font-bold flex items-center gap-1">
                        <span>ATM: {{ $analytics['atm_strike'] }}</span>
                        @if(!empty($analytics['is_custom_atm']))
                            <span class="text-[9px] text-amber-300 uppercase font-black px-1 rounded bg-amber-950/80 border border-amber-800">Custom</span>
                        @endif
                    </span>

                    {{-- Option Chain Breakdown Trigger --}}
                    <button type="button"
                            @click="showOptionChain = !showOptionChain"
                            class="px-2.5 py-0.5 rounded-lg border border-teal-800/80 bg-teal-950/40 hover:bg-teal-900/60 text-[11px] font-extrabold text-teal-300 flex items-center gap-1 transition active:scale-95 shadow">
                        <span>⛓️ Option Chain Breakdown</span>
                        <span class="text-[9px]" x-text="showOptionChain ? '▲' : '▼'"></span>
                    </button>
                </div>

                {{-- Leg Pills --}}
                <div class="flex items-center flex-wrap gap-1 font-mono-num text-[11px]">
                    @foreach($analytics['resolved_legs'] as $rl)
                        <span class="px-2 py-0.5 rounded-md bg-slate-950 border text-[11px] font-semibold
                            {{ $rl['option_type'] === 'CE' ? 'border-emerald-900/60 text-emerald-300' : 'border-rose-900/60 text-rose-300' }}">
                            {{ $rl['label'] }}
                        </span>
                    @endforeach
                </div>
            </div>

            {{-- Expandable Option Chain View Section (Basket Builder 100% Parity) --}}
            <div x-show="showOptionChain"
                 x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="pt-2 border-t border-slate-800/80">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    {{-- CE Legs --}}
                    <div class="bg-slate-950 border border-emerald-900/40 rounded-xl p-2.5">
                        <div class="text-[11px] font-bold text-emerald-400 uppercase tracking-wide mb-1.5 flex items-center justify-between border-b border-emerald-950 pb-1">
                            <span>Call Options (CE)</span>
                            <span class="text-[10px] text-emerald-400/90 font-mono-num font-bold">
                                {{ collect($analytics['resolved_legs'])->where('option_type', 'CE')->sum('lots') }} Lots Total
                            </span>
                        </div>
                        <div class="space-y-1 font-mono-num text-xs">
                            @forelse(collect($analytics['resolved_legs'])->where('option_type', 'CE') as $leg)
                                <div class="flex items-center justify-between px-2 py-1 bg-slate-900/90 rounded border border-slate-800/80">
                                    <span class="text-slate-200 font-semibold">{{ number_format($leg['strike']) }} CE</span>
                                    <span class="text-emerald-400 font-black">{{ $leg['lots'] }} Lot(s)</span>
                                </div>
                            @empty
                                <div class="text-center py-2 text-[11px] text-slate-500">No CE legs</div>
                            @endforelse
                        </div>
                    </div>

                    {{-- 3-Column Option Chain Matrix --}}
                    <div class="bg-slate-950 border border-teal-800/50 rounded-xl p-2.5 shadow-lg">
                        <div class="text-[11px] font-bold text-teal-300 uppercase tracking-wide mb-1.5 text-center flex items-center justify-center gap-1 border-b border-teal-950 pb-1">
                            <span>Option Chain Matrix</span>
                        </div>
                        <div class="overflow-x-auto font-mono-num text-xs custom-scrollbar">
                            <table class="w-full text-center divide-y divide-slate-800">
                                <thead>
                                    <tr class="text-[10px] uppercase font-bold text-slate-400 bg-slate-900/80">
                                        <th class="py-1 px-2 text-emerald-400">CE Lots</th>
                                        <th class="py-1 px-2 text-slate-200 bg-slate-900">Strike</th>
                                        <th class="py-1 px-2 text-rose-400">PE Lots</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/60 bg-slate-950">
                                    @foreach($analytics['option_chain_view'] as $row)
                                        @php $isAtm = !empty($row['is_atm']); @endphp
                                        <tr class="{{ $isAtm ? 'bg-teal-950/30 font-bold border border-teal-500/30' : 'hover:bg-slate-900/50' }}">
                                            <td class="py-1 px-2 text-emerald-400 font-bold">
                                                {{ $row['ce_lots'] > 0 ? $row['ce_lots'] . ' Lot(s)' : '—' }}
                                            </td>
                                            <td class="py-1 px-2 font-black {{ $isAtm ? 'text-amber-300 bg-slate-900/90' : 'text-slate-200 bg-slate-900/40' }}">
                                                {{ number_format($row['strike']) }}
                                                @if($isAtm)
                                                    <span class="ml-1 px-1 py-0.2 rounded text-[9px] bg-amber-400/20 text-amber-300 border border-amber-400/40">ATM</span>
                                                @endif
                                            </td>
                                            <td class="py-1 px-2 text-rose-400 font-bold">
                                                {{ $row['pe_lots'] > 0 ? $row['pe_lots'] . ' Lot(s)' : '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- PE Legs --}}
                    <div class="bg-slate-950 border border-rose-900/40 rounded-xl p-2.5">
                        <div class="text-[11px] font-bold text-rose-400 uppercase tracking-wide mb-1.5 flex items-center justify-between border-b border-rose-950 pb-1">
                            <span>Put Options (PE)</span>
                            <span class="text-[10px] text-rose-400/90 font-mono-num font-bold">
                                {{ collect($analytics['resolved_legs'])->where('option_type', 'PE')->sum('lots') }} Lots Total
                            </span>
                        </div>
                        <div class="space-y-1 font-mono-num text-xs">
                            @forelse(collect($analytics['resolved_legs'])->where('option_type', 'PE') as $leg)
                                <div class="flex items-center justify-between px-2 py-1 bg-slate-900/90 rounded border border-slate-800/80">
                                    <span class="text-slate-200 font-semibold">{{ number_format($leg['strike']) }} PE</span>
                                    <span class="text-rose-400 font-black">{{ $leg['lots'] }} Lot(s)</span>
                                </div>
                            @empty
                                <div class="text-center py-2 text-[11px] text-slate-500">No PE legs</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- 3. THE BIG UNIFIED MATRIX CHART                                        --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-3.5 shadow-xl">
        
        {{-- Chart Header & Toolbar --}}
        <div class="space-y-2.5 pb-2.5 border-b border-slate-800/80">
            
            {{-- Top Line: Chart Identity & Time Horizon --}}
            <div class="flex flex-wrap items-center justify-between gap-2.5">
                <div class="flex items-center gap-2">
                    <h2 class="text-xs sm:text-sm font-black uppercase tracking-wider text-slate-100 flex items-center gap-1.5">
                        <span>📈</span>
                        <span>COMBINED STRATEGY MATRIX CHART</span>
                    </h2>
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-950 border border-slate-800 text-teal-300 font-mono-num font-bold">
                        {{ $analytics['summary']['start_time'] ?? '09:15' }} &rarr; {{ $analytics['summary']['latest_time'] ?? '15:30' }}
                    </span>
                </div>

                <div class="text-xs text-slate-400 font-mono-num">
                    Base: <span class="text-emerald-400 font-bold">&#8377;{{ number_format($analytics['summary']['base_premium'] ?? 0, 2) }}</span>
                    &bull; Latest: <span class="text-cyan-300 font-bold">&#8377;{{ number_format($analytics['summary']['latest_premium'] ?? 0, 2) }}</span>
                </div>
            </div>

            {{-- Bottom Line: Organized Dataset Toggle Chips --}}
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                
                {{-- Group 1: Price & Flow --}}
                <div class="flex items-center flex-wrap gap-1.5 font-bold">
                    <span class="text-[10px] uppercase font-bold text-slate-400 mr-1 select-none">SERIES:</span>

                    {{-- Premium Toggle (Rating 7) --}}
                    <button type="button" @click="toggleDataset(0)"
                            :class="datasetVisibility[0] ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40 shadow-sm' : 'bg-slate-950 text-slate-500 border-slate-800 opacity-60'"
                            class="px-2.5 py-1 rounded-lg border transition flex items-center gap-1.5" title="Combined Premium (Thickness: 7)">
                        <span class="w-3.5 h-[3px] rounded-sm bg-emerald-400"></span>
                        <span>💵 Premium</span>
                    </button>

                    {{-- VWAP Toggle (Rating 10 - Thickest Line) --}}
                    <button type="button" @click="toggleDataset(1)"
                            :class="datasetVisibility[1] ? 'bg-amber-500/20 text-amber-300 border-amber-500/40 shadow-sm' : 'bg-slate-950 text-slate-500 border-slate-800 opacity-60'"
                            class="px-2.5 py-1 rounded-lg border transition flex items-center gap-1.5" title="Combined VWAP (Thickness: 10 - Thickest Solid Line)">
                        <span class="w-3.5 h-[4.5px] rounded-sm bg-amber-400"></span>
                        <span>🟠 VWAP</span>
                    </button>

                    {{-- OI-VWAP Toggle (Rating 4 - Dotted) --}}
                    <button type="button" @click="toggleDataset(2)"
                            :class="datasetVisibility[2] ? 'bg-purple-500/20 text-purple-300 border-purple-500/40 shadow-sm' : 'bg-slate-950 text-slate-500 border-slate-800 opacity-60'"
                            class="px-2.5 py-1 rounded-lg border transition flex items-center gap-1.5" title="OI-Weighted VWAP (Thickness: 4 - Dotted Line)">
                        <span class="w-3.5 border-b-[2px] border-dotted border-purple-400 h-[2px]"></span>
                        <span>🟣 OI-VWAP</span>
                    </button>

                    {{-- Net OI Change Toggle (Rating 5) --}}
                    <button type="button" @click="toggleDataset(3)"
                            :class="datasetVisibility[3] ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40 shadow-sm' : 'bg-slate-950 text-slate-500 border-slate-800 opacity-60'"
                            class="px-2.5 py-1 rounded-lg border transition flex items-center gap-1.5" title="Net OI Change (Thickness: 5)">
                        <span class="w-3.5 h-[2.5px] rounded-sm bg-cyan-400"></span>
                        <span>📊 Net OI</span>
                    </button>
                </div>

                {{-- Group 2: Strategy Greeks (Rating 3) --}}
                <div class="flex items-center flex-wrap gap-1.5 font-bold">
                    <span class="text-[10px] uppercase font-bold text-slate-400 mr-1 select-none">GREEKS:</span>

                    {{-- Delta Toggle --}}
                    <button type="button" @click="toggleDataset(4)"
                            :class="datasetVisibility[4] ? 'bg-blue-500/20 text-blue-300 border-blue-500/40 shadow-sm' : 'bg-slate-950 text-slate-500 border-slate-800 opacity-60'"
                            class="px-2.5 py-1 rounded-lg border transition flex items-center gap-1.5" title="Net Delta (Thickness: 3)">
                        <span class="w-3.5 h-[1.5px] rounded-sm bg-blue-400"></span>
                        <span>&Delta; Delta</span>
                    </button>

                    {{-- Theta Toggle --}}
                    <button type="button" @click="toggleDataset(5)"
                            :class="datasetVisibility[5] ? 'bg-teal-500/20 text-teal-300 border-teal-500/40 shadow-sm' : 'bg-slate-950 text-slate-500 border-slate-800 opacity-60'"
                            class="px-2.5 py-1 rounded-lg border transition flex items-center gap-1.5" title="Theta (Thickness: 3)">
                        <span class="w-3.5 h-[1.5px] rounded-sm bg-teal-400"></span>
                        <span>&Theta; Theta</span>
                    </button>

                    {{-- Gamma Toggle --}}
                    <button type="button" @click="toggleDataset(6)"
                            :class="datasetVisibility[6] ? 'bg-indigo-500/20 text-indigo-300 border-indigo-500/40 shadow-sm' : 'bg-slate-950 text-slate-500 border-slate-800 opacity-60'"
                            class="px-2.5 py-1 rounded-lg border transition flex items-center gap-1.5" title="Gamma (Thickness: 3)">
                        <span class="w-3.5 h-[1.5px] rounded-sm bg-indigo-400"></span>
                        <span>&Gamma; Gamma</span>
                    </button>

                    {{-- Vega Toggle --}}
                    <button type="button" @click="toggleDataset(7)"
                            :class="datasetVisibility[7] ? 'bg-rose-500/20 text-rose-300 border-rose-500/40 shadow-sm' : 'bg-slate-950 text-slate-500 border-slate-800 opacity-60'"
                            class="px-2.5 py-1 rounded-lg border transition flex items-center gap-1.5" title="Vega (Thickness: 3)">
                        <span class="w-3.5 h-[1.5px] rounded-sm bg-rose-400"></span>
                        <span>&nu; Vega</span>
                    </button>

                    {{-- IV Toggle --}}
                    <button type="button" @click="toggleDataset(8)"
                            :class="datasetVisibility[8] ? 'bg-yellow-500/20 text-yellow-300 border-yellow-500/40 shadow-sm' : 'bg-slate-950 text-slate-500 border-slate-800 opacity-60'"
                            class="px-2.5 py-1 rounded-lg border transition flex items-center gap-1.5" title="Implied Volatility (Thickness: 3)">
                        <span class="w-3.5 h-[1.5px] rounded-sm bg-yellow-400"></span>
                        <span>⚡ IV</span>
                    </button>
                </div>

            </div>

        </div>

        {{-- Big Chart Canvas Container (Height is dynamically customizable) --}}
        <div class="mt-3 relative w-full transition-all duration-300"
             :style="'height: ' + chartHeight + 'px'">
            <canvas id="bigUnifiedChart"></canvas>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- 4. COLLAPSIBLE TIME-SERIES PROGRESSION TABLE (LATEST TO OLDEST)       --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div x-show="showTable"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-2xl">
        
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <div>
                <h3 class="text-xs sm:text-sm font-black uppercase tracking-wider text-slate-200 flex items-center gap-2">
                    <span>⏱️</span>
                    <span>TIME-STEP PROGRESSION AUDIT TABLE (LATEST &rarr; OLDEST)</span>
                </h3>
                <p class="text-[11px] text-slate-400 mt-0.5">
                    Detailed chronological progression capturing decay velocity, Greek drift, and interval state transitions.
                </p>
            </div>
            <button type="button" @click="showTable = false" class="text-xs text-slate-400 hover:text-white font-bold px-3 py-1 bg-slate-800 rounded-lg">
                Close &times;
            </button>
        </div>

        <div class="overflow-x-auto mt-3 max-h-[500px] overflow-y-auto font-mono-num text-xs custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead class="sticky top-0 bg-slate-950/95 backdrop-blur z-10 border-b border-slate-800">
                    <tr class="text-[10px] uppercase text-slate-400">
                        <th class="py-2.5 px-3">Time</th>
                        <th class="py-2.5 px-3">Spot</th>
                        <th class="py-2.5 px-3">Combined Premium</th>
                        <th class="py-2.5 px-3">VWAP</th>
                        <th class="py-2.5 px-3">VWAP Diff</th>
                        <th class="py-2.5 px-3">Decay Velocity</th>
                        <th class="py-2.5 px-3">Net OI Change</th>
                        <th class="py-2.5 px-3">&Delta; Delta</th>
                        <th class="py-2.5 px-3">&Delta; Drift</th>
                        <th class="py-2.5 px-3">&Gamma; Gamma</th>
                        <th class="py-2.5 px-3">&Theta; Theta</th>
                        <th class="py-2.5 px-3">&nu; Vega</th>
                        <th class="py-2.5 px-3">IV</th>
                        <th class="py-2.5 px-3 text-right">Interval Signal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($analytics['progression_table'] as $row)
                    <tr class="hover:bg-slate-800/40 transition">
                        <td class="py-2 px-3 font-bold text-white">{{ $row['time'] }}</td>
                        <td class="py-2 px-3 text-slate-300">{{ number_format($row['spot'], 2) }}</td>
                        <td class="py-2 px-3 font-bold text-emerald-400">&#8377;{{ number_format($row['combined_ltp'], 2) }}</td>
                        <td class="py-2 px-3 text-amber-300">&#8377;{{ number_format($row['vwap'], 2) }}</td>
                        <td class="py-2 px-3 font-semibold {{ $row['vwap_diff'] <= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ $row['vwap_diff'] <= 0 ? '' : '+' }}{{ number_format($row['vwap_diff'], 2) }}
                        </td>
                        <td class="py-2 px-3 font-bold text-cyan-300">
                            {{ $row['decay_velocity'] >= 0 ? '+' : '' }}{{ $row['decay_velocity'] }} pts/h
                        </td>
                        <td class="py-2 px-3 text-slate-300">{{ number_format($row['net_oi_change']) }}</td>
                        <td class="py-2 px-3 font-bold {{ $row['delta'] >= 0 ? 'text-cyan-400' : 'text-rose-400' }}">
                            {{ $row['delta'] >= 0 ? '+' : '' }}{{ $row['delta'] }}
                        </td>
                        <td class="py-2 px-3 text-slate-400">{{ $row['delta_drift'] >= 0 ? '+' : '' }}{{ $row['delta_drift'] }}</td>
                        <td class="py-2 px-3 text-slate-400">{{ $row['gamma'] }}</td>
                        <td class="py-2 px-3 text-teal-300">{{ $row['theta'] }}</td>
                        <td class="py-2 px-3 text-slate-400">{{ $row['vega'] }}</td>
                        <td class="py-2 px-3 text-amber-400">{{ $row['iv'] }}%</td>
                        <td class="py-2 px-3 text-right">
                            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase {{ $row['signal_badge'] }}">
                                {{ $row['signal'] }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="14" class="text-center py-6 text-slate-500 italic">
                            No progression intervals recorded for the chosen timeframe.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- MODAL: MANUAL CE & PE STRIKES WITH MULTIPLE LOTS SELECTION            --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div x-show="showManualModal"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-3xl w-full p-5 shadow-2xl space-y-4"
             @click.away="showManualModal = false">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                <div class="flex items-center gap-2">
                    <span class="text-xl">🎯</span>
                    <h3 class="text-base font-black text-white">Configure Manual Strikes &amp; Multiple Lots</h3>
                </div>
                <button type="button" @click="showManualModal = false" class="text-slate-400 hover:text-white font-bold">&times;</button>
            </div>

            <p class="text-xs text-slate-300">
                Add Call (CE) and Put (PE) strikes with custom lots per strike. Both individual and multi-lot combinations will be aggregated:
            </p>

            <form method="GET" action="{{ route('strategy.premium.analytics') }}" class="space-y-4">
                <input type="hidden" name="mode" value="manual">
                <input type="hidden" name="symbol" value="NIFTY">
                <input type="hidden" name="date" value="{{ $selectedDate }}">
                <input type="hidden" name="expiry" value="{{ $selectedExpiry }}">
                <input type="hidden" name="end_time" value="{{ $endTime }}">
                <input type="hidden" name="chart_height" :value="chartHeight">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[380px] overflow-y-auto pr-1 custom-scrollbar">
                    {{-- Call Strikes List (CE) --}}
                    <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-2">
                        <div class="flex justify-between items-center text-xs font-bold text-teal-400 border-b border-slate-800 pb-1.5">
                            <span>CALL STRIKES (CE)</span>
                            <button type="button" @click="addCallLeg()" class="text-[10px] px-2 py-0.5 bg-teal-600/30 rounded text-teal-300 hover:bg-teal-600/50">+ Add Strike</button>
                        </div>
                        <template x-for="(leg, idx) in callLegs" :key="idx">
                            <div class="flex items-center gap-2 text-xs">
                                <select :name="'call_strikes[' + idx + ']'" x-model="leg.strike" class="flex-1 bg-slate-900 border border-slate-700 rounded px-2 py-1 text-white">
                                    <option value="">Select Strike</option>
                                    @foreach($allStrikes as $stk)
                                        <option value="{{ $stk }}">{{ $stk }}</option>
                                    @endforeach
                                </select>
                                <div class="flex items-center gap-1">
                                    <span class="text-[10px] text-slate-400">Lots:</span>
                                    <input type="number" :name="'call_lots[' + idx + ']'" x-model.number="leg.lots" min="1" max="50" class="w-14 bg-slate-900 border border-slate-700 rounded px-1.5 py-1 text-white text-center font-mono-num font-bold">
                                </div>
                                <button type="button" @click="removeCallLeg(idx)" class="text-rose-400 hover:text-rose-300 font-bold">&times;</button>
                            </div>
                        </template>
                    </div>

                    {{-- Put Strikes List (PE) --}}
                    <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-2">
                        <div class="flex justify-between items-center text-xs font-bold text-rose-400 border-b border-slate-800 pb-1.5">
                            <span>PUT STRIKES (PE)</span>
                            <button type="button" @click="addPutLeg()" class="text-[10px] px-2 py-0.5 bg-rose-600/30 rounded text-rose-300 hover:bg-rose-600/50">+ Add Strike</button>
                        </div>
                        <template x-for="(leg, idx) in putLegs" :key="idx">
                            <div class="flex items-center gap-2 text-xs">
                                <select :name="'put_strikes[' + idx + ']'" x-model="leg.strike" class="flex-1 bg-slate-900 border border-slate-700 rounded px-2 py-1 text-white">
                                    <option value="">Select Strike</option>
                                    @foreach($allStrikes as $stk)
                                        <option value="{{ $stk }}">{{ $stk }}</option>
                                    @endforeach
                                </select>
                                <div class="flex items-center gap-1">
                                    <span class="text-[10px] text-slate-400">Lots:</span>
                                    <input type="number" :name="'put_lots[' + idx + ']'" x-model.number="leg.lots" min="1" max="50" class="w-14 bg-slate-900 border border-slate-700 rounded px-1.5 py-1 text-white text-center font-mono-num font-bold">
                                </div>
                                <button type="button" @click="removePutLeg(idx)" class="text-rose-400 hover:text-rose-300 font-bold">&times;</button>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-800 flex justify-end gap-2">
                    <button type="button" @click="showManualModal = false" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-800 text-slate-300">Cancel</button>
                    <button type="submit" class="px-4 py-1.5 rounded-xl text-xs font-bold bg-teal-600 hover:bg-teal-500 text-white shadow">Apply &amp; Load Analysis</button>
                </div>
            </form>
        </div>
    </div>

</div>

@push('scripts')
<script>
// Custom Chart.js plugin to render right-side line labels (TradingView / Sensibull style)
const rightSideLineLabelsPlugin = {
    id: 'rightSideLineLabels',
    afterDraw(chart) {
        const { ctx, chartArea, scales } = chart;
        if (!chartArea) return;

        const activeItems = [];
        chart.data.datasets.forEach((ds, idx) => {
            if (!chart.isDatasetVisible(idx)) return;
            const meta = chart.getDatasetMeta(idx);
            if (!meta || meta.hidden || !meta.data || meta.data.length === 0) return;

            // Locate the last valid rendered point with numeric values
            let lastPoint = null;
            let lastValue = null;
            for (let i = meta.data.length - 1; i >= 0; i--) {
                const pt = meta.data[i];
                const rawVal = ds.data[i];
                if (pt && !isNaN(pt.y) && !isNaN(pt.x) && rawVal !== null && rawVal !== undefined && !isNaN(rawVal)) {
                    lastPoint = pt;
                    lastValue = Number(rawVal);
                    break;
                }
            }
            if (!lastPoint) return;

            let formattedVal = '';
            const yAxisID = ds.yAxisID;
            if (yAxisID === 'yPremium') {
                formattedVal = '₹' + lastValue.toFixed(2);
            } else if (yAxisID === 'yOI') {
                const sign = lastValue >= 0 ? '+' : '';
                if (Math.abs(lastValue) >= 100000) {
                    formattedVal = sign + (lastValue / 100000).toFixed(2) + 'L';
                } else {
                    formattedVal = sign + (lastValue / 1000).toFixed(1) + 'k';
                }
            } else if (yAxisID === 'yIV') {
                formattedVal = lastValue.toFixed(2) + '%';
            } else if (yAxisID === 'yDelta') {
                formattedVal = (lastValue >= 0 ? '+' : '') + lastValue.toFixed(3);
            } else if (yAxisID === 'yTheta') {
                formattedVal = (lastValue >= 0 ? '+' : '') + lastValue.toFixed(2);
            } else if (yAxisID === 'yVega') {
                formattedVal = (lastValue >= 0 ? '+' : '') + lastValue.toFixed(2);
            } else if (yAxisID === 'yGamma') {
                formattedVal = lastValue.toFixed(5);
            } else {
                formattedVal = lastValue.toFixed(2);
            }

            activeItems.push({
                name: ds.shortLabel || ds.label || '',
                value: formattedVal,
                color: ds.borderColor,
                x: lastPoint.x,
                y: lastPoint.y,
                targetY: lastPoint.y
            });
        });

        if (activeItems.length === 0) return;

        // Sort ascending by target Y coordinate
        activeItems.sort((a, b) => a.targetY - b.targetY);

        const badgeH = 18;
        const minGap = 20;

        // Resolve collisions downwards
        for (let i = 1; i < activeItems.length; i++) {
            if (activeItems[i].targetY < activeItems[i - 1].targetY + minGap) {
                activeItems[i].targetY = activeItems[i - 1].targetY + minGap;
            }
        }

        // Keep within chart vertical bounds
        const maxAllowedY = chartArea.bottom - badgeH / 2;
        const minAllowedY = chartArea.top + badgeH / 2;

        if (activeItems[activeItems.length - 1].targetY > maxAllowedY) {
            const shift = activeItems[activeItems.length - 1].targetY - maxAllowedY;
            for (let i = 0; i < activeItems.length; i++) {
                activeItems[i].targetY -= shift;
            }
        }

        for (let i = 0; i < activeItems.length; i++) {
            if (i === 0 && activeItems[i].targetY < minAllowedY) {
                activeItems[i].targetY = minAllowedY;
            } else if (i > 0 && activeItems[i].targetY < activeItems[i - 1].targetY + minGap) {
                activeItems[i].targetY = activeItems[i - 1].targetY + minGap;
            }
        }

        // Determine badge X position (placed immediately to the right of the rightmost axis)
        const yOIScale = scales['yOI'];
        const rightAxisOffset = (yOIScale && yOIScale.width && chart.isDatasetVisible(3)) ? yOIScale.width + 8 : 8;
        const badgeX = chartArea.right + rightAxisOffset;

        ctx.save();
        ctx.font = '600 10px monospace, sans-serif';
        ctx.textBaseline = 'middle';

        activeItems.forEach(item => {
            const text = `${item.name}: ${item.value}`;
            const textWidth = ctx.measureText(text).width;
            const badgeW = textWidth + 14;
            const rx = badgeX;
            const ry = item.targetY - badgeH / 2;

            // Connecting guide line from last point to badge
            ctx.beginPath();
            ctx.setLineDash([2, 3]);
            ctx.strokeStyle = item.color;
            ctx.globalAlpha = 0.55;
            ctx.lineWidth = 1;
            ctx.moveTo(item.x, item.y);
            ctx.lineTo(rx - 3, item.targetY);
            ctx.stroke();
            ctx.setLineDash([]);
            ctx.globalAlpha = 1.0;

            // Small connector circle
            ctx.fillStyle = item.color;
            ctx.beginPath();
            ctx.arc(rx - 3, item.targetY, 2, 0, Math.PI * 2);
            ctx.fill();

            // Dark pill badge background
            ctx.fillStyle = '#090d16';
            ctx.strokeStyle = item.color;
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            if (ctx.roundRect) {
                ctx.roundRect(rx, ry, badgeW, badgeH, 3);
            } else {
                ctx.rect(rx, ry, badgeW, badgeH);
            }
            ctx.fill();
            ctx.stroke();

            // Accent bar on the left inside the pill
            ctx.fillStyle = item.color;
            ctx.beginPath();
            if (ctx.roundRect) {
                ctx.roundRect(rx + 2, ry + 2, 3, badgeH - 4, 1.5);
            } else {
                ctx.rect(rx + 2, ry + 2, 3, badgeH - 4);
            }
            ctx.fill();

            // Text
            ctx.fillStyle = '#f8fafc';
            ctx.fillText(text, rx + 8, item.targetY);
        });

        ctx.restore();
    }
};

function strategyPremiumApp() {
    return {
        showTable: false,
        showOptionChain: false,
        showManualModal: false,
        chartHeight: {{ $chartHeight }},
        // Dataset visibility state retained across browser reloads
        datasetVisibility: (function() {
            const defaultVis = [true, true, false, false, false, false, false, false, false]; // Default: Premium & VWAP on
            try {
                const saved = localStorage.getItem('spa_dataset_visibility');
                if (saved) {
                    const parsed = JSON.parse(saved);
                    if (Array.isArray(parsed) && parsed.length === defaultVis.length) {
                        return parsed.map(v => Boolean(v));
                    }
                }
            } catch (e) {}
            return defaultVis;
        })(),

        // Strategy Health show/hide state retained across browser reloads
        showHealth: (function() {
            try {
                const saved = localStorage.getItem('spa_show_health');
                return saved !== null ? (saved === 'true') : false; // Sleek collapsed default, or user preference
            } catch (e) {
                return false;
            }
        })(),

        toggleHealth() {
            this.showHealth = !this.showHealth;
            try {
                localStorage.setItem('spa_show_health', this.showHealth);
            } catch (e) {}
        },

        saveDatasetVisibility() {
            try {
                localStorage.setItem('spa_dataset_visibility', JSON.stringify(this.datasetVisibility));
            } catch (e) {}
        },

        callLegs: [
            @if(!empty($manualCallStrikes))
                @foreach($manualCallStrikes as $i => $cs)
                    { strike: '{{ $cs }}', lots: {{ $manualCallLots[$i] ?? 1 }} },
                @endforeach
            @else
                { strike: '{{ $analytics['atm_strike'] ?? 22550 }}', lots: 1 }
            @endif
        ],
        putLegs: [
            @if(!empty($manualPutStrikes))
                @foreach($manualPutStrikes as $i => $ps)
                    { strike: '{{ $ps }}', lots: {{ $manualPutLots[$i] ?? 1 }} },
                @endforeach
            @else
                { strike: '{{ $analytics['atm_strike'] ?? 22550 }}', lots: 1 }
            @endif
        ],

        init() {
            this.renderBigChart();
        },

        getChart() {
            const canvas = document.getElementById('bigUnifiedChart');
            return (canvas && typeof Chart !== 'undefined') ? Chart.getChart(canvas) : null;
        },

        addCallLeg() {
            this.callLegs.push({ strike: '', lots: 1 });
        },
        removeCallLeg(idx) {
            this.callLegs.splice(idx, 1);
        },
        addPutLeg() {
            this.putLegs.push({ strike: '', lots: 1 });
        },
        removePutLeg(idx) {
            this.putLegs.splice(idx, 1);
        },

        resizeChart() {
            this.$nextTick(() => {
                const chart = this.getChart();
                if (chart) {
                    chart.resize();
                }
            });
        },

        toggleDataset(datasetIndex) {
            this.datasetVisibility[datasetIndex] = !this.datasetVisibility[datasetIndex];
            this.saveDatasetVisibility();
            const chart = this.getChart();
            if (chart) {
                chart.setDatasetVisibility(datasetIndex, this.datasetVisibility[datasetIndex]);
                chart.update();
            }
        },

        renderBigChart() {
            const canvas = document.getElementById('bigUnifiedChart');
            if (!canvas || typeof Chart === 'undefined') return;

            // Destroy existing chart on canvas to avoid collision
            const existingChart = Chart.getChart(canvas);
            if (existingChart) {
                existingChart.destroy();
            }

            const chartData = @json($analytics['chart_data'] ?? []);
            if (!chartData.labels || chartData.labels.length === 0) return;

            const labels = chartData.labels;

            new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        // 0: Combined Premium (Rating 7 - Solid line, width 3.2)
                        {
                            label: 'Combined Premium',
                            shortLabel: 'Premium',
                            data: chartData.combined_ltp,
                            borderColor: '#34d399', // Emerald
                            backgroundColor: 'rgba(52, 211, 153, 0.08)',
                            fill: true,
                            borderWidth: 3.2,
                            borderDash: [],
                            tension: 0.1,
                            pointRadius: 0,
                            pointHoverRadius: 5,
                            yAxisID: 'yPremium',
                            hidden: !this.datasetVisibility[0]
                        },
                        // 1: Combined VWAP (Rating 10 - Highest Thickness solid line, width 4.5)
                        {
                            label: 'Combined VWAP',
                            shortLabel: 'VWAP',
                            data: chartData.vwap,
                            borderColor: '#fbbf24', // Amber
                            borderDash: [], // Solid line
                            backgroundColor: 'transparent',
                            borderWidth: 4.5, // 10 Rating: highest thickness
                            tension: 0.1,
                            pointRadius: 0,
                            pointHoverRadius: 5,
                            yAxisID: 'yPremium',
                            hidden: !this.datasetVisibility[1]
                        },
                        // 2: Combined OI-VWAP (Rating 4 - Dotted line, width 2.0)
                        {
                            label: 'OI-Weighted VWAP',
                            shortLabel: 'OI-VWAP',
                            data: chartData.oi_vwap,
                            borderColor: '#c084fc', // Purple
                            borderDash: [3, 3], // Dotted line
                            backgroundColor: 'transparent',
                            borderWidth: 2.0,
                            tension: 0.1,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                            yAxisID: 'yPremium',
                            hidden: !this.datasetVisibility[2]
                        },
                        // 3: Net OI Change (Rating 5 - Solid line, width 2.5)
                        {
                            label: 'Net OI Change',
                            shortLabel: 'Net OI',
                            data: chartData.net_oi,
                            borderColor: '#22d3ee', // Cyan
                            backgroundColor: 'rgba(34, 211, 238, 0.04)',
                            borderWidth: 2.5,
                            borderDash: [],
                            tension: 0.2,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                            yAxisID: 'yOI',
                            hidden: !this.datasetVisibility[3]
                        },
                        // 4: Combined Delta (Rating 3 - Solid line, width 1.5)
                        {
                            label: 'Net Delta (Δ)',
                            shortLabel: 'Delta',
                            data: chartData.delta,
                            borderColor: '#60a5fa', // Blue
                            backgroundColor: 'transparent',
                            borderWidth: 1.5,
                            borderDash: [],
                            tension: 0.2,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                            yAxisID: 'yDelta',
                            hidden: !this.datasetVisibility[4]
                        },
                        // 5: Combined Theta (Rating 3 - Solid line, width 1.5)
                        {
                            label: 'Theta (θ/day)',
                            shortLabel: 'Theta',
                            data: chartData.theta,
                            borderColor: '#2dd4bf', // Teal
                            backgroundColor: 'transparent',
                            borderWidth: 1.5,
                            borderDash: [],
                            tension: 0.2,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                            yAxisID: 'yTheta',
                            hidden: !this.datasetVisibility[5]
                        },
                        // 6: Combined Gamma (Rating 3 - Solid line, width 1.5)
                        {
                            label: 'Gamma (Γ)',
                            shortLabel: 'Gamma',
                            data: chartData.gamma,
                            borderColor: '#a855f7', // Indigo/Purple
                            backgroundColor: 'transparent',
                            borderWidth: 1.5,
                            borderDash: [],
                            tension: 0.2,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                            yAxisID: 'yGamma',
                            hidden: !this.datasetVisibility[6]
                        },
                        // 7: Combined Vega (Rating 3 - Solid line, width 1.5)
                        {
                            label: 'Vega (ν/1% IV)',
                            shortLabel: 'Vega',
                            data: chartData.vega,
                            borderColor: '#fb7185', // Rose
                            backgroundColor: 'transparent',
                            borderWidth: 1.5,
                            borderDash: [],
                            tension: 0.2,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                            yAxisID: 'yVega',
                            hidden: !this.datasetVisibility[7]
                        },
                        // 8: Combined IV (Rating 3 - Solid line, width 1.5)
                        {
                            label: 'Implied Volatility (IV)',
                            shortLabel: 'IV',
                            data: chartData.iv,
                            borderColor: '#facc15', // Yellow
                            backgroundColor: 'transparent',
                            borderWidth: 1.5,
                            borderDash: [],
                            tension: 0.2,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                            yAxisID: 'yIV',
                            hidden: !this.datasetVisibility[8]
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            right: 175,
                            left: 8,
                            top: 10,
                            bottom: 6
                        }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#090d16',
                            borderColor: '#334155',
                            borderWidth: 1,
                            titleColor: '#e2e8f0',
                            bodyColor: '#cbd5e1',
                            padding: 10,
                            callbacks: {
                                label: function(context) {
                                    const lbl = context.dataset.label || '';
                                    const val = Number(context.raw);
                                    if (context.dataset.yAxisID === 'yPremium') {
                                        return lbl + ': ₹' + val.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                    } else if (context.dataset.yAxisID === 'yOI') {
                                        return lbl + ': ' + (val >= 0 ? '+' : '') + val.toLocaleString('en-IN');
                                    } else if (context.dataset.yAxisID === 'yIV') {
                                        return lbl + ': ' + val.toFixed(2) + '%';
                                    } else if (context.dataset.yAxisID === 'yDelta') {
                                        return lbl + ': ' + (val >= 0 ? '+' : '') + val.toFixed(3);
                                    } else if (context.dataset.yAxisID === 'yTheta') {
                                        return lbl + ': ' + (val >= 0 ? '+' : '') + val.toFixed(2) + '/day';
                                    } else if (context.dataset.yAxisID === 'yVega') {
                                        return lbl + ': ' + (val >= 0 ? '+' : '') + val.toFixed(2) + '/1% IV';
                                    } else if (context.dataset.yAxisID === 'yGamma') {
                                        return lbl + ': ' + val.toFixed(5);
                                    }
                                    return lbl + ': ' + val.toFixed(3);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(51, 65, 85, 0.25)' },
                            ticks: {
                                color: '#94a3b8',
                                font: { family: 'monospace', size: 10 },
                                maxTicksLimit: 14,
                            }
                        },
                        // Y-Axis 1: Combined Premium & VWAP
                        yPremium: {
                            type: 'linear',
                            position: 'left',
                            grid: { color: 'rgba(51, 65, 85, 0.35)' },
                            ticks: {
                                color: '#34d399',
                                font: { family: 'monospace', size: 11 },
                                callback: function(val) {
                                    return '₹' + val.toLocaleString('en-IN');
                                }
                            }
                        },
                        // Y-Axis 2: Net OI Change
                        yOI: {
                            type: 'linear',
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            ticks: {
                                color: '#22d3ee',
                                font: { family: 'monospace', size: 9 },
                                callback: function(val) {
                                    return (val / 1000).toFixed(0) + 'k';
                                }
                            }
                        },
                        // Y-Axis 3: Delta (Independent auto-scale)
                        yDelta: {
                            type: 'linear',
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            display: false,
                        },
                        // Y-Axis 4: Theta (Independent auto-scale)
                        yTheta: {
                            type: 'linear',
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            display: false,
                        },
                        // Y-Axis 5: Vega (Independent auto-scale)
                        yVega: {
                            type: 'linear',
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            display: false,
                        },
                        // Y-Axis 6: Gamma (Independent auto-scale)
                        yGamma: {
                            type: 'linear',
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            display: false,
                        },
                        // Y-Axis 7: IV (Independent auto-scale)
                        yIV: {
                            type: 'linear',
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            display: false,
                        }
                    }
                },
                plugins: [rightSideLineLabelsPlugin]
            });
        }
    };
}
</script>
@endpush
@endsection
