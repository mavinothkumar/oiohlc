@extends('layouts.app')

@section('title', 'Institutional EOD Cumulative Report · ' . $report['meta']['symbol'] . ' (' . $report['meta']['trade_date'] . ')')

@push('styles')
<style>
    html, body {
        background-color: #0b0f19 !important;
        color: #e2e8f0 !important;
    }
    main {
        padding: 0.5rem !important;
        max-width: 100vw !important;
        background-color: #0b0f19 !important;
    }
    main > .mt-4 {
        margin-top: 0 !important;
    }
    .custom-scrollbar::-webkit-scrollbar {
        width: 5px;
        height: 5px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #090d16;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #334155;
        border-radius: 4px;
    }

    /* Guaranteed Grid Column Layouts across all screen sizes */
    .report-kpi-grid {
        display: grid;
        grid-template-columns: repeat(1, minmax(0, 1fr));
        gap: 0.75rem;
    }
    @media (min-width: 640px) {
        .report-kpi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (min-width: 1024px) {
        .report-kpi-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }

    .report-main-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.25rem;
        width: 100%;
    }
    @media (min-width: 1024px) {
        .report-main-grid {
            grid-template-columns: repeat(12, minmax(0, 1fr));
        }
        .report-col-left {
            grid-column: span 7 / span 7 !important;
            width: 100% !important;
        }
        .report-col-right {
            grid-column: span 5 / span 5 !important;
            width: 100% !important;
        }
    }

    @media print {
        header, nav, .no-print {
            display: none !important;
        }
        body {
            background-color: #ffffff !important;
            color: #000000 !important;
        }
        .print-card {
            border: 1px solid #cbd5e1 !important;
            background: #ffffff !important;
            color: #0f172a !important;
            box-shadow: none !important;
        }
    }
</style>
@endpush

@section('content')
<div class="min-h-screen bg-[#0b0f19] text-slate-100 p-2 sm:p-4 lg:p-6 space-y-5">

    {{-- Top Action & Title Bar --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 bg-slate-900/90 border border-slate-800 rounded-2xl p-4 shadow-xl backdrop-blur">
        <div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-gradient-to-r from-indigo-500 to-amber-500 text-white shadow-sm">
                    ENTERPRISE SYNTHESIS
                </span>
                <span class="text-xs font-mono text-slate-400">
                    {{ $report['meta']['version'] }} · Generated {{ Carbon\Carbon::parse($report['meta']['generated_at'])->format('d M Y, H:i') }} IST
                </span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight mt-1 flex items-center gap-2">
                <span>🏛️</span>
                <span>{{ $report['meta']['symbol'] }} End-of-Day Cumulative Institutional Intelligence</span>
            </h1>
            <p class="text-xs text-slate-400 mt-0.5">
                Multi-dimensional synthesis of Depth Alerts, Opportunity Journal, Value Area, and Next-Day Support/Resistance Playbook.
            </p>
        </div>

        {{-- Date Picker & Action Controls --}}
        <div class="flex items-center gap-2 flex-wrap no-print">
            <form method="GET" action="{{ route('order-flow-depth.report') }}" class="flex items-center gap-1.5 bg-slate-950 p-1 rounded-xl border border-slate-800">
                <input type="hidden" name="symbol" value="{{ $report['meta']['symbol'] }}">
                <span class="text-[10px] font-bold text-slate-400 pl-1.5">DATE:</span>
                <input type="date" name="date" value="{{ $report['meta']['trade_date'] }}" onchange="this.form.submit()" class="bg-transparent text-xs font-mono font-bold text-amber-300 focus:outline-none cursor-pointer px-1">
                <select name="mode" onchange="this.form.submit()" class="bg-slate-900 text-slate-300 text-[11px] font-bold rounded px-1.5 py-0.5 focus:outline-none border border-slate-800 cursor-pointer">
                    <option value="" {{ request('mode') == '' ? 'selected' : '' }}>All Modes</option>
                    <option value="live" {{ request('mode') == 'live' ? 'selected' : '' }}>🔴 Live Only</option>
                    <option value="sim" {{ request('mode') == 'sim' ? 'selected' : '' }}>🧪 Sim Only</option>
                </select>
            </form>

            <button onclick="copyDeskBrief()" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 hover:text-white transition text-xs font-bold flex items-center gap-1.5 shadow-sm cursor-pointer" title="Copy formatted Telegram / Desk briefing">
                <span>📋</span>
                <span>Copy Brief</span>
            </button>

            <button onclick="window.print()" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 hover:text-white transition text-xs font-bold flex items-center gap-1.5 shadow-sm cursor-pointer" title="Print or Save as PDF">
                <span>🖨️</span>
                <span>Print / PDF</span>
            </button>

            <a href="{{ route('order-flow-depth.index') }}" class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white transition text-xs font-bold flex items-center gap-1.5 shadow-lg shadow-indigo-600/30">
                <span>⚡</span>
                <span>Live 30-Depth Radar</span>
            </a>
        </div>
    </div>

    {{-- Executive Summary & Top 4 Mission-Critical KPI Cards --}}
    <div class="report-kpi-grid">
        {{-- KPI 1: Dominant Participant Archetype --}}
        <div class="bg-gradient-to-br from-slate-900 to-slate-950 border border-slate-800 rounded-2xl p-4 shadow-lg space-y-2 print-card">
            <div class="flex items-center justify-between">
                <span class="text-[10px] uppercase font-black tracking-wider text-slate-400">WHO DOMINATED</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase {{ $report['dominance_matrix']['dominant_side'] === 'BULLS' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-rose-500/20 text-rose-300 border border-rose-500/40' }}">
                    {{ $report['dominance_matrix']['archetype_tag'] }}
                </span>
            </div>
            <div>
                <p class="text-lg font-black text-white leading-tight">
                    {{ $report['dominance_matrix']['who']['verdict'] }}
                </p>
                <div class="mt-2 space-y-1">
                    <div class="flex justify-between text-[11px] font-mono">
                        <span class="text-emerald-400 font-bold">Bulls: {{ $report['dominance_matrix']['bull_score_pct'] }}%</span>
                        <span class="text-rose-400 font-bold">Bears: {{ $report['dominance_matrix']['bear_score_pct'] }}%</span>
                    </div>
                    <div class="w-full bg-slate-800 h-2 rounded-full overflow-hidden flex">
                        <div class="bg-emerald-500 h-full transition-all duration-500" style="width: {{ $report['dominance_matrix']['bull_score_pct'] }}%"></div>
                        <div class="bg-rose-500 h-full transition-all duration-500" style="width: {{ $report['dominance_matrix']['bear_score_pct'] }}%"></div>
                    </div>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 line-clamp-2">
                {{ $report['dominance_matrix']['who']['description'] }}
            </p>
        </div>

        {{-- KPI 2: Day Range & Value Area --}}
        <div class="bg-gradient-to-br from-slate-900 to-slate-950 border border-slate-800 rounded-2xl p-4 shadow-lg space-y-2 print-card">
            <div class="flex items-center justify-between">
                <span class="text-[10px] uppercase font-black tracking-wider text-slate-400">WHICH RANGE DOMINATED</span>
                <span class="text-[10px] font-mono text-amber-300 font-bold">{{ $report['range_summary']['day_range_pts'] }} Pts Range</span>
            </div>
            <div>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-black text-amber-400 font-mono">{{ $report['dominance_matrix']['range']['poc'] }}</span>
                    <span class="text-xs text-slate-400 font-bold">Point of Control (POC)</span>
                </div>
                <div class="text-xs text-slate-300 mt-1 flex items-center gap-2">
                    <span class="text-[11px] text-slate-400">Value Area:</span>
                    <span class="font-bold text-white font-mono">{{ $report['range_summary']['dominant_shelf'] }}</span>
                </div>
            </div>
            <div class="pt-1 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-400">
                <span>High: <b class="text-white font-mono">₹{{ number_format($report['range_summary']['day_high'], 2) }}</b></span>
                <span>Low: <b class="text-white font-mono">₹{{ number_format($report['range_summary']['day_low'], 2) }}</b></span>
                <span>Close: <b class="text-white font-mono">₹{{ number_format($report['range_summary']['day_close'], 2) }}</b></span>
            </div>
        </div>

        {{-- KPI 3: Journal Setups Execution Scorecard --}}
        <div class="bg-gradient-to-br from-slate-900 to-slate-950 border border-slate-800 rounded-2xl p-4 shadow-lg space-y-2 print-card">
            <div class="flex items-center justify-between">
                <span class="text-[10px] uppercase font-black tracking-wider text-slate-400">HOW IT PERFORMED (JOURNAL)</span>
                <span class="text-[10px] font-mono text-emerald-400 font-bold">{{ $report['journal_summary']['win_rate_pct'] }}% Win Rate</span>
            </div>
            <div>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-black font-mono {{ $report['journal_summary']['net_pnl_pts'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                        {{ $report['journal_summary']['net_pnl_pts'] >= 0 ? '+' : '' }}{{ $report['journal_summary']['net_pnl_pts'] }} Pts
                    </span>
                    <span class="text-xs text-slate-400">Net Index PnL</span>
                </div>
                <div class="flex items-center gap-3 text-xs mt-1">
                    <span class="text-emerald-400 font-bold font-mono">🎯 {{ $report['journal_summary']['target_hit'] }} Hit</span>
                    <span class="text-rose-400 font-bold font-mono">🛑 {{ $report['journal_summary']['stopped_out'] }} Stopped</span>
                    <span class="text-amber-300 font-bold font-mono">⏳ {{ $report['journal_summary']['active'] }} Active</span>
                </div>
            </div>
            <div class="pt-1 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-400">
                <span>Total Setups: <b class="text-white font-mono">{{ $report['journal_summary']['total_signals'] }}</b></span>
                <span>Profit Factor: <b class="text-white font-mono">{{ $report['journal_summary']['profit_factor'] }}x</b></span>
            </div>
        </div>

        {{-- KPI 4: Tomorrow's Strategic Balance Pivot --}}
        <div class="bg-gradient-to-br from-slate-900 to-slate-950 border border-amber-500/30 rounded-2xl p-4 shadow-lg space-y-2 print-card">
            <div class="flex items-center justify-between">
                <span class="text-[10px] uppercase font-black tracking-wider text-amber-300">TOMORROW TRADE ANCHOR</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-amber-500/20 text-amber-300 border border-amber-500/40">
                    LINE IN SAND
                </span>
            </div>
            <div>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-black text-amber-300 font-mono">₹{{ number_format($report['tomorrow_playbook']['central_pivot'], 0) }}</span>
                    <span class="text-xs text-slate-400 font-bold">Central Pivot</span>
                </div>
                <p class="text-xs text-slate-300 mt-1">
                    Bullish above; Bearish below. Key inflection line for next session.
                </p>
            </div>
            <div class="pt-1 border-t border-slate-800/80 flex items-center justify-between text-[11px]">
                <span class="text-emerald-400 font-bold">R1: ₹{{ number_format($report['tomorrow_playbook']['levels'][2]['price'], 0) }}</span>
                <span class="text-rose-400 font-bold">S1: ₹{{ number_format($report['tomorrow_playbook']['levels'][4]['price'], 0) }}</span>
                <span class="text-amber-400 font-bold">R2: ₹{{ number_format($report['tomorrow_playbook']['levels'][1]['price'], 0) }}</span>
            </div>
        </div>
    </div>

    {{-- Main Body: Two Column Strategic Layout --}}
    <div class="report-main-grid">

        {{-- LEFT COLUMN: Microstructure Dominance, Alerts & Journal Breakdown (7 cols) --}}
        <div class="report-col-left space-y-5">

            {{-- 1. WHO DOMINATED: Deep Market Participant Analysis --}}
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4 print-card">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h2 class="text-base font-black text-white flex items-center gap-2">
                        <span>🛡️</span>
                        <span>1. WHO DOMINATED: Institutional Participant Dynamics</span>
                    </h2>
                    <span class="text-xs font-mono text-slate-400">{{ $report['alerts_summary']['total_alerts'] }} Total Order Flow Events</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="bg-slate-950 p-3 rounded-xl border border-emerald-500/30 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-emerald-400">🟢 BUY SIDE FLOW (DEMAND &amp; ABSORPTION)</span>
                            <span class="font-mono font-black text-emerald-300">{{ $report['alerts_summary']['absorption_bull_pct'] ?? $report['alerts_summary']['buy_pct'] }}%</span>
                        </div>
                        <p class="text-slate-300 text-[11px] leading-relaxed">
                            {{ $report['alerts_summary']['buy_count'] }} Resting Bid alerts · {{ number_format($report['alerts_summary']['bid_liquidity_qty']) }} bid quantity.
                            @if(isset($report['alerts_summary']['swept_ask_count']) && $report['alerts_summary']['swept_ask_count'] > 0)
                                <span class="text-emerald-400 font-semibold block mt-0.5">⚡ Buyers conquered &amp; swept {{ $report['alerts_summary']['swept_ask_count'] }} overhead ask blocks to the upside.</span>
                            @endif
                        </p>
                        <div class="flex items-center gap-2 text-[10px] text-slate-400 pt-1 border-t border-slate-800 flex-wrap">
                            <span>Defended Bids: <b class="text-emerald-300 font-mono">{{ $report['alerts_summary']['defended_bid_count'] ?? 0 }}</b></span>
                            <span>Swept Asks: <b class="text-emerald-300 font-mono">{{ $report['alerts_summary']['swept_ask_count'] ?? 0 }}</b></span>
                            <span>Strategy: <b class="text-white font-mono">{{ $report['alerts_summary']['strategy_buy_count'] }}</b></span>
                        </div>
                    </div>

                    <div class="bg-slate-950 p-3 rounded-xl border border-rose-500/30 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-rose-400">🔴 SELL SIDE FLOW (SUPPLY &amp; OVERHEAD)</span>
                            <span class="font-mono font-black text-rose-300">{{ $report['alerts_summary']['absorption_bear_pct'] ?? $report['alerts_summary']['sell_pct'] }}%</span>
                        </div>
                        <p class="text-slate-300 text-[11px] leading-relaxed">
                            {{ $report['alerts_summary']['sell_count'] }} Resting Ask alerts logged · {{ number_format($report['alerts_summary']['ask_liquidity_qty']) }} total resting ask quantity.
                            @if(isset($report['alerts_summary']['defended_ask_count']))
                                <span class="text-slate-400 block mt-0.5">{{ $report['alerts_summary']['defended_ask_count'] }} active overhead ask walls defended above close.</span>
                            @endif
                        </p>
                        <div class="flex items-center gap-2 text-[10px] text-slate-400 pt-1 border-t border-slate-800 flex-wrap">
                            <span>Active Supply: <b class="text-rose-300 font-mono">{{ $report['alerts_summary']['defended_ask_count'] ?? 0 }}</b></span>
                            <span>Broken Bids: <b class="text-rose-300 font-mono">{{ $report['alerts_summary']['swept_bid_count'] ?? 0 }}</b></span>
                            <span>Strategy: <b class="text-white font-mono">{{ $report['alerts_summary']['strategy_sell_count'] }}</b></span>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-950/60 p-3 rounded-xl border border-slate-800 text-xs space-y-1">
                    <p class="text-slate-200 font-bold">
                        Institutional Order Book Mechanics:
                    </p>
                    <p class="text-slate-400 leading-relaxed">
                        {{ $report['dominance_matrix']['who']['aggression'] }}.
                        The market spent the majority of its liquidity volume in {{ $report['dominance_matrix']['range']['structural_mode'] }} mode around {{ $report['dominance_matrix']['which']['magnet_strike'] }}.
                    </p>
                </div>
            </div>

            {{-- 2. WHEN DOMINATED: Session Phases & Critical Turning Points --}}
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4 print-card">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h2 class="text-base font-black text-white flex items-center gap-2">
                        <span>⏱️</span>
                        <span>2. WHEN DOMINATED: Timeline & Market Session Breakdown</span>
                    </h2>
                    <span class="text-xs font-mono text-amber-300 font-bold">Peak: {{ $report['dominance_matrix']['when']['peak_window'] }}</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
                    @foreach($report['alerts_summary']['market_phases'] as $phaseKey => $phase)
                        <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-white text-[11px]">{{ $phase['name'] }}</span>
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-black {{ $phase['bias'] === 'BULLISH' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400' }}">
                                    {{ $phase['bias'] }}
                                </span>
                            </div>
                            <p class="text-[10.5px] text-slate-400 leading-snug">
                                {{ $phase['description'] }}
                            </p>
                            <div class="text-[10px] text-slate-300 font-mono flex items-center justify-between pt-1 border-t border-slate-800">
                                <span>Total: <b>{{ $phase['total'] }}</b></span>
                                <span class="text-emerald-400">🟢 {{ $phase['buys'] }}</span>
                                <span class="text-rose-400">🔴 {{ $phase['sells'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Critical Turning Points Log --}}
                @if(!empty($report['alerts_summary']['turning_points']))
                    <div class="space-y-1.5">
                        <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider">Critical Turning Points & Block Defenses</h3>
                        <div class="space-y-1.5 max-h-48 overflow-y-auto custom-scrollbar">
                            @foreach($report['alerts_summary']['turning_points'] as $tp)
                                <div class="bg-slate-950 px-3 py-1.5 rounded-lg border border-slate-800 flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-amber-300 font-bold">{{ $tp['time'] }}</span>
                                        <span class="px-1.5 py-0.2 rounded text-[10px] font-bold {{ $tp['side'] === 'BUY' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400' }}">
                                            {{ $tp['type'] }}
                                        </span>
                                        <span class="text-slate-300 font-semibold">{{ $tp['title'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-right">
                                        @if($tp['price'] > 0)
                                            <span class="font-mono text-white font-bold">₹{{ number_format($tp['price'], 2) }}</span>
                                        @endif
                                        <span class="text-[10px] text-slate-400 hidden sm:inline">{{ $tp['significance'] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- 3. WHICH INSTRUMENTS & STRIKES DOMINATED --}}
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4 print-card">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h2 class="text-base font-black text-white flex items-center gap-2">
                        <span>🎯</span>
                        <span>3. WHICH INSTRUMENTS & STRIKES DOMINATED</span>
                    </h2>
                    <span class="text-xs text-amber-300 font-bold">Institutional Gravity Nodes</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
                    <div class="bg-slate-950 p-3 rounded-xl border border-indigo-500/30">
                        <span class="text-[10px] uppercase font-bold text-slate-400">LIQUIDITY MAGNET</span>
                        <p class="text-sm font-black text-indigo-300 mt-1">{{ $report['dominance_matrix']['which']['magnet_strike'] }}</p>
                        <p class="text-[10.5px] text-slate-400 mt-1">High volume node & institutional fair value center.</p>
                    </div>
                    <div class="bg-slate-950 p-3 rounded-xl border border-rose-500/30">
                        <span class="text-[10px] uppercase font-bold text-slate-400">MAX RESISTANCE CEILING</span>
                        <p class="text-sm font-black text-rose-300 mt-1">{{ $report['dominance_matrix']['which']['resistance_strike'] }}</p>
                        <p class="text-[10.5px] text-slate-400 mt-1">Heavy resting ask blocks capped further upside.</p>
                    </div>
                    <div class="bg-slate-950 p-3 rounded-xl border border-emerald-500/30">
                        <span class="text-[10px] uppercase font-bold text-slate-400">MAX SUPPORT FLOOR</span>
                        <p class="text-sm font-black text-emerald-300 mt-1">{{ $report['dominance_matrix']['which']['support_strike'] }}</p>
                        <p class="text-[10.5px] text-slate-400 mt-1">Heavy resting bid walls absorbed selling pressure.</p>
                    </div>
                </div>

                {{-- Key Resting Walls Table --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="space-y-1.5">
                        <span class="font-bold text-emerald-400 text-[11px]">🛡️ Top Institutional Bid Walls (Support)</span>
                        @forelse($report['alerts_summary']['key_bid_walls'] as $wall)
                            <div class="bg-slate-950 px-2.5 py-1.5 rounded-lg border border-slate-800 flex justify-between items-center text-xs">
                                <span class="font-mono font-bold text-white">₹{{ number_format($wall['price'], 2) }}</span>
                                <span class="text-emerald-400 font-mono font-bold">{{ number_format($wall['total_qty']) }} Qty ({{ $wall['count'] }}x)</span>
                                <span class="text-[10px] text-slate-400 font-mono">{{ $wall['last_seen'] }}</span>
                            </div>
                        @empty
                            <p class="text-slate-500 text-[11px]">No resting bid block walls recorded</p>
                        @endforelse
                    </div>

                    <div class="space-y-1.5">
                        <span class="font-bold text-rose-400 text-[11px]">🚧 Top Institutional Ask Walls (Resistance)</span>
                        @forelse($report['alerts_summary']['key_ask_walls'] as $wall)
                            <div class="bg-slate-950 px-2.5 py-1.5 rounded-lg border border-slate-800 flex justify-between items-center text-xs">
                                <span class="font-mono font-bold text-white">₹{{ number_format($wall['price'], 2) }}</span>
                                <span class="text-rose-400 font-mono font-bold">{{ number_format($wall['total_qty']) }} Qty ({{ $wall['count'] }}x)</span>
                                <span class="text-[10px] text-slate-400 font-mono">{{ $wall['last_seen'] }}</span>
                            </div>
                        @empty
                            <p class="text-slate-500 text-[11px]">No resting ask block walls recorded</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- 4. HOW IT UNFOLDED: Journal Execution Scorecard & 0Click Audit --}}
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4 print-card">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h2 class="text-base font-black text-white flex items-center gap-2">
                        <span>📖</span>
                        <span>4. HOW IT UNFOLDED: Journal Performance & Execution Audit</span>
                    </h2>
                    <span class="text-xs font-mono text-emerald-400 font-bold">Profit Factor: {{ $report['journal_summary']['profit_factor'] }}x</span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                    <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 text-center">
                        <span class="text-[10px] text-slate-400 font-bold uppercase">WIN RATE</span>
                        <p class="text-base font-black text-emerald-400 mt-0.5">{{ $report['journal_summary']['win_rate_pct'] }}%</p>
                        <span class="text-[9px] text-slate-400">{{ $report['journal_summary']['target_hit'] }} of {{ $report['journal_summary']['target_hit'] + $report['journal_summary']['stopped_out'] }} Decided</span>
                    </div>
                    <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 text-center">
                        <span class="text-[10px] text-slate-400 font-bold uppercase">NET INDEX PnL</span>
                        <p class="text-base font-black font-mono mt-0.5 {{ $report['journal_summary']['net_pnl_pts'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ $report['journal_summary']['net_pnl_pts'] >= 0 ? '+' : '' }}{{ $report['journal_summary']['net_pnl_pts'] }} Pts
                        </p>
                        <span class="text-[9px] text-slate-400">Gross: +{{ $report['journal_summary']['gross_win_pts'] }} / -{{ $report['journal_summary']['gross_loss_pts'] }}</span>
                    </div>
                    <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 text-center">
                        <span class="text-[10px] text-slate-400 font-bold uppercase">AVG MFE / MAE</span>
                        <p class="text-base font-black font-mono text-amber-300 mt-0.5">
                            +{{ $report['journal_summary']['avg_mfe_pts'] }} / -{{ abs($report['journal_summary']['avg_mae_pts']) }}
                        </p>
                        <span class="text-[9px] text-slate-400">Excursion Efficiency</span>
                    </div>
                    <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 text-center">
                        <span class="text-[10px] text-slate-400 font-bold uppercase">0CLICK WEBHOOK</span>
                        <p class="text-base font-black font-mono text-indigo-300 mt-0.5">
                            {{ $report['journal_summary']['webhook_audit']['dispatched'] }} Orders
                        </p>
                        <span class="text-[9px] text-slate-400">{{ $report['journal_summary']['webhook_audit']['rate_pct'] }}% Success Rate</span>
                    </div>
                </div>

                {{-- Recent Setups Table --}}
                @if(!empty($report['journal_summary']['recent_setups']))
                    <div class="space-y-1.5">
                        <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider">Opportunity Journal Trade Setups</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-800 text-[10px] text-slate-400 font-bold uppercase">
                                        <th class="py-1 px-2">Time</th>
                                        <th class="py-1 px-2">Action / Setup</th>
                                        <th class="py-1 px-2">Instrument</th>
                                        <th class="py-1 px-2">Entry</th>
                                        <th class="py-1 px-2">Exit</th>
                                        <th class="py-1 px-2">PnL</th>
                                        <th class="py-1 px-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/60 font-mono text-[11px]">
                                    @foreach($report['journal_summary']['recent_setups'] as $setup)
                                        <tr class="hover:bg-slate-800/40">
                                            <td class="py-1.5 px-2 text-slate-400">{{ $setup->signal_time }}</td>
                                            <td class="py-1.5 px-2 font-sans font-bold text-white">{{ $setup->action }}</td>
                                            <td class="py-1.5 px-2 text-amber-300">{{ $setup->instrument }}</td>
                                            <td class="py-1.5 px-2 text-white">₹{{ number_format($setup->entry_price, 2) }}</td>
                                            <td class="py-1.5 px-2 text-slate-400">{{ $setup->exit_price ? '₹' . number_format($setup->exit_price, 2) : '--' }}</td>
                                            <td class="py-1.5 px-2 font-bold {{ $setup->pnl_points > 0 ? 'text-emerald-400' : ($setup->pnl_points < 0 ? 'text-rose-400' : 'text-slate-400') }}">
                                                {{ $setup->pnl_points > 0 ? '+' : '' }}{{ $setup->pnl_points }}
                                            </td>
                                            <td class="py-1.5 px-2">
                                                <span class="px-1.5 py-0.2 rounded text-[9px] font-sans font-bold {{ $setup->status === 'TARGET_HIT' ? 'bg-emerald-500/20 text-emerald-400' : ($setup->status === 'STOPPED_OUT' ? 'bg-rose-500/20 text-rose-400' : 'bg-slate-800 text-slate-300') }}">
                                                    {{ $setup->status }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

        </div>

        {{-- RIGHT COLUMN: Support & Resistance Matrix & Actionable Tomorrow Trade Playbook (5 cols) --}}
        <div class="report-col-right space-y-5">

            {{-- TOMORROW S/R LADDER MATRIX --}}
            <div class="bg-gradient-to-br from-slate-900 to-slate-950 border-2 border-indigo-500/40 rounded-2xl p-5 shadow-2xl space-y-4 print-card">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-wider text-amber-400">INSTITUTIONAL QUANT LEVELS</span>
                        <h2 class="text-base font-black text-white flex items-center gap-2 mt-0.5">
                            <span>📐</span>
                            <span>Tomorrow's Support & Resistance Ladder</span>
                        </h2>
                    </div>
                    <div class="text-right">
                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-indigo-500/20 text-indigo-300 border border-indigo-500/40">
                            {{ $report['meta']['symbol'] }} SPOT
                        </span>
                        <span class="text-[9px] text-slate-400 block mt-0.5">Cash Index Levels</span>
                    </div>
                </div>

                <p class="text-xs text-slate-300">
                    {{ $report['tomorrow_playbook']['summary_guidance'] }}
                </p>

                {{-- Interactive Price Ladder --}}
                <div class="space-y-2 text-xs">
                    @foreach($report['tomorrow_playbook']['levels'] as $lvl)
                        <div class="p-2.5 rounded-xl border flex items-center justify-between transition {{ $lvl['type'] === 'RESISTANCE' ? 'bg-rose-950/20 border-rose-500/30 text-rose-200' : ($lvl['type'] === 'SUPPORT' ? 'bg-emerald-950/20 border-emerald-500/30 text-emerald-200' : 'bg-amber-950/30 border-amber-500/50 text-amber-200 ring-1 ring-amber-400/20') }}">
                            <div class="flex items-center gap-2.5">
                                <span class="px-2 py-0.5 rounded text-[11px] font-mono font-black {{ $lvl['type'] === 'RESISTANCE' ? 'bg-rose-500 text-white' : ($lvl['type'] === 'SUPPORT' ? 'bg-emerald-500 text-white' : 'bg-amber-400 text-slate-950') }}">
                                    {{ $lvl['code'] }}
                                </span>
                                <div>
                                    <span class="font-bold text-white text-xs block leading-tight">{{ $lvl['level_name'] }}</span>
                                    <span class="text-[10px] text-slate-400 block leading-tight">{{ $lvl['description'] }}</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="font-mono text-sm font-black text-white">₹{{ number_format($lvl['price'], 2) }}</span>
                                <span class="text-[9px] uppercase font-bold block {{ $lvl['strength'] === 'CRITICAL' ? 'text-amber-400' : 'text-slate-400' }}">{{ $lvl['strength'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- TOMORROW ACTIONABLE TRADE PLAYBOOK (3 SCENARIOS) --}}
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4 print-card">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h2 class="text-base font-black text-white flex items-center gap-2">
                        <span>🎯</span>
                        <span>Tomorrow's Tactical Execution Playbook</span>
                    </h2>
                    <span class="text-xs font-mono text-emerald-400 font-bold">Rule-Based Plans</span>
                </div>

                {{-- Plan A: Bullish Long Continuation --}}
                <div class="bg-slate-950 p-3.5 rounded-xl border border-emerald-500/30 space-y-2 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-emerald-400 text-[12px]">{{ $report['tomorrow_playbook']['trade_plans']['bull_plan']['title'] }}</span>
                        <span class="px-1.5 py-0.2 rounded text-[9px] font-black bg-emerald-500/20 text-emerald-300">PLAN A</span>
                    </div>
                    <div class="space-y-1 text-slate-300 text-[11.5px]">
                        <p><b class="text-slate-400">Trigger:</b> {{ $report['tomorrow_playbook']['trade_plans']['bull_plan']['trigger'] }}</p>
                        <p><b class="text-slate-400">Entry Zone:</b> <span class="text-white font-mono font-bold">{{ $report['tomorrow_playbook']['trade_plans']['bull_plan']['entry_zone'] }}</span></p>
                        <p><b class="text-slate-400">Targets:</b> <span class="text-emerald-300 font-mono font-bold">{{ $report['tomorrow_playbook']['trade_plans']['bull_plan']['target_1'] }}</span> · <span class="text-emerald-400 font-mono font-bold">{{ $report['tomorrow_playbook']['trade_plans']['bull_plan']['target_2'] }}</span></p>
                        <p><b class="text-slate-400">Stop Loss:</b> <span class="text-rose-400 font-mono font-bold">{{ $report['tomorrow_playbook']['trade_plans']['bull_plan']['stop_loss'] }}</span></p>
                        <p class="text-[10.5px] text-emerald-300/90 pt-1 border-t border-slate-800/80">⚡ {{ $report['tomorrow_playbook']['trade_plans']['bull_plan']['strategy'] }}</p>
                    </div>
                </div>

                {{-- Plan B: Bearish Short Breakdown --}}
                <div class="bg-slate-950 p-3.5 rounded-xl border border-rose-500/30 space-y-2 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-rose-400 text-[12px]">{{ $report['tomorrow_playbook']['trade_plans']['bear_plan']['title'] }}</span>
                        <span class="px-1.5 py-0.2 rounded text-[9px] font-black bg-rose-500/20 text-rose-300">PLAN B</span>
                    </div>
                    <div class="space-y-1 text-slate-300 text-[11.5px]">
                        <p><b class="text-slate-400">Trigger:</b> {{ $report['tomorrow_playbook']['trade_plans']['bear_plan']['trigger'] }}</p>
                        <p><b class="text-slate-400">Entry Zone:</b> <span class="text-white font-mono font-bold">{{ $report['tomorrow_playbook']['trade_plans']['bear_plan']['entry_zone'] }}</span></p>
                        <p><b class="text-slate-400">Targets:</b> <span class="text-rose-300 font-mono font-bold">{{ $report['tomorrow_playbook']['trade_plans']['bear_plan']['target_1'] }}</span> · <span class="text-rose-400 font-mono font-bold">{{ $report['tomorrow_playbook']['trade_plans']['bear_plan']['target_2'] }}</span></p>
                        <p><b class="text-slate-400">Stop Loss:</b> <span class="text-rose-400 font-mono font-bold">{{ $report['tomorrow_playbook']['trade_plans']['bear_plan']['stop_loss'] }}</span></p>
                        <p class="text-[10.5px] text-rose-300/90 pt-1 border-t border-slate-800/80">🩸 {{ $report['tomorrow_playbook']['trade_plans']['bear_plan']['strategy'] }}</p>
                    </div>
                </div>

                {{-- Plan C: Neutral Mean Reversion --}}
                <div class="bg-slate-950 p-3.5 rounded-xl border border-amber-500/30 space-y-2 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-amber-400 text-[12px]">{{ $report['tomorrow_playbook']['trade_plans']['range_plan']['title'] }}</span>
                        <span class="px-1.5 py-0.2 rounded text-[9px] font-black bg-amber-500/20 text-amber-300">PLAN C</span>
                    </div>
                    <div class="space-y-1 text-slate-300 text-[11.5px]">
                        <p><b class="text-slate-400">Trigger:</b> {{ $report['tomorrow_playbook']['trade_plans']['range_plan']['trigger'] }}</p>
                        <p><b class="text-slate-400">Execution:</b> <span class="text-white">{{ $report['tomorrow_playbook']['trade_plans']['range_plan']['entry_zone'] }}</span></p>
                        <p><b class="text-slate-400">Target:</b> <span class="text-amber-300 font-mono font-bold">{{ $report['tomorrow_playbook']['trade_plans']['range_plan']['target_1'] }}</span></p>
                        <p class="text-[10.5px] text-amber-300/90 pt-1 border-t border-slate-800/80">⚖️ {{ $report['tomorrow_playbook']['trade_plans']['range_plan']['strategy'] }}</p>
                    </div>
                </div>
            </div>

        </div>

    </div>

    {{-- FULL-WIDTH SECTION: TOP 20 HEAVY ORDER FLOW BLOCK TRANSACTIONS --}}
    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-2xl space-y-4 print-card">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 border-b border-slate-800 pb-4">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-500/20 text-amber-300 border border-amber-500/40">
                        INSTITUTIONAL TAPE FOOTPRINT
                    </span>
                    <span class="text-xs font-mono text-slate-400">
                        {{ $report['heavy_blocks']['total_count'] ?? 0 }} Total Blocks Recorded
                    </span>
                </div>
                <h2 class="text-lg sm:text-xl font-black text-white tracking-tight mt-1 flex items-center gap-2">
                    <span>🏛️</span>
                    <span>Top Heavy Order Flow Block Transactions Today ({{ Carbon\Carbon::parse($report['meta']['trade_date'])->format('d-m-Y') }})</span>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">
                    Highest volume resting liquidity walls, absorption clusters, and high-stakes order book auctions. Filter out opening/closing auction distortions to isolate core intraday institutional positioning.
                </p>
            </div>

            {{-- Summary Metric Badges --}}
            <div class="flex items-center gap-2 flex-wrap text-xs">
                <div class="bg-slate-950 px-3 py-1.5 rounded-xl border border-slate-800">
                    <span class="text-[9.5px] uppercase font-bold text-slate-400 block">MAX WALL SIZE</span>
                    <span class="font-mono font-black text-amber-300">{{ number_format($report['heavy_blocks']['summary']['max_block_qty'] ?? 0) }} qty</span>
                </div>
                <div class="bg-slate-950 px-3 py-1.5 rounded-xl border border-slate-800">
                    <span class="text-[9.5px] uppercase font-bold text-slate-400 block">CORE HOURS BLOCKS</span>
                    <span class="font-mono font-black text-indigo-300">{{ $report['heavy_blocks']['summary']['core_hours_count'] ?? 0 }}</span>
                </div>
                <div class="bg-slate-950 px-3 py-1.5 rounded-xl border border-slate-800">
                    <span class="text-[9.5px] uppercase font-bold text-slate-400 block">BID / ASK RATIO</span>
                    <span class="font-mono font-bold text-emerald-400">{{ $report['heavy_blocks']['summary']['bid_blocks_count'] ?? 0 }}B</span>
                    <span class="text-slate-500">/</span>
                    <span class="font-mono font-bold text-rose-400">{{ $report['heavy_blocks']['summary']['ask_blocks_count'] ?? 0 }}A</span>
                </div>
            </div>
        </div>

        {{-- Interactive Filter Toolbar --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 bg-slate-950/70 p-3 rounded-xl border border-slate-800/80">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider mr-1">SESSION:</span>
                <button type="button" onclick="setBlockTimeFilter('core', this)" class="block-time-btn px-2.5 py-1 rounded-lg text-xs font-bold transition bg-indigo-600 text-white shadow-sm border border-indigo-400/30">
                    ⭐ Core Market Hours (09:30 - 15:15)
                </button>
                <button type="button" onclick="setBlockTimeFilter('all', this)" class="block-time-btn px-2.5 py-1 rounded-lg text-xs font-bold transition bg-slate-900 text-slate-400 hover:text-white border border-slate-800">
                    All Hours (09:15 - 15:30)
                </button>
                <button type="button" onclick="setBlockTimeFilter('opening', this)" class="block-time-btn px-2.5 py-1 rounded-lg text-xs font-bold transition bg-slate-900 text-slate-400 hover:text-white border border-slate-800">
                    Opening Rush (09:15 - 09:30)
                </button>
                <button type="button" onclick="setBlockTimeFilter('midday', this)" class="block-time-btn px-2.5 py-1 rounded-lg text-xs font-bold transition bg-slate-900 text-slate-400 hover:text-white border border-slate-800">
                    Midday (10:30 - 13:30)
                </button>
                <button type="button" onclick="setBlockTimeFilter('closing', this)" class="block-time-btn px-2.5 py-1 rounded-lg text-xs font-bold transition bg-slate-900 text-slate-400 hover:text-white border border-slate-800">
                    Closing Auction (15:15 - 15:30)
                </button>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <div class="flex items-center gap-1">
                    <span class="text-[10px] font-black uppercase text-slate-400">SIDE:</span>
                    <select id="block-side-filter" onchange="applyBlockFilters()" class="bg-slate-900 text-slate-200 text-xs font-bold rounded-lg px-2 py-1 border border-slate-800 focus:outline-none cursor-pointer">
                        <option value="all">All Sides</option>
                        <option value="BID">🟢 Bids (Support)</option>
                        <option value="ASK">🔴 Asks (Resistance)</option>
                    </select>
                </div>
                <div class="flex items-center gap-1">
                    <span class="text-[10px] font-black uppercase text-slate-400">OUTCOME:</span>
                    <select id="block-outcome-filter" onchange="applyBlockFilters()" class="bg-slate-900 text-slate-200 text-xs font-bold rounded-lg px-2 py-1 border border-slate-800 focus:outline-none cursor-pointer">
                        <option value="all">All Outcomes</option>
                        <option value="DEFENDED">🎯 Defended / Target Hit</option>
                        <option value="SWEPT">💥 Swept / Conquered</option>
                    </select>
                </div>
                <div class="flex items-center gap-1">
                    <span class="text-[10px] font-black uppercase text-slate-400">SHOW:</span>
                    <select id="block-limit-filter" onchange="applyBlockFilters()" class="bg-slate-900 text-slate-200 text-xs font-bold rounded-lg px-2 py-1 border border-slate-800 focus:outline-none cursor-pointer">
                        <option value="20" selected>Top 20</option>
                        <option value="50">Top 50</option>
                        <option value="100">Top 100</option>
                        <option value="all">All Available</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Table Container --}}
        <div class="overflow-x-auto rounded-xl border border-slate-800 custom-scrollbar">
            <table class="w-full text-left text-xs whitespace-nowrap">
                <thead class="bg-slate-950/90 text-slate-400 uppercase font-black text-[10px] tracking-wider border-b border-slate-800">
                    <tr>
                        <th class="py-3 px-3 text-center">#</th>
                        <th class="py-3 px-3">Time</th>
                        <th class="py-3 px-3">Strike / Level</th>
                        <th class="py-3 px-3">Instrument & Setup</th>
                        <th class="py-3 px-3 text-center">Side</th>
                        <th class="py-3 px-3 text-right">Wall / Block Qty</th>
                        <th class="py-3 px-3 text-right">Spot Ref</th>
                        <th class="py-3 px-3 text-right">VPOC Vol / CVD</th>
                        <th class="py-3 px-3">OI Buildup / Microstructure Notes</th>
                        <th class="py-3 px-3 text-center">Defense Outcome</th>
                    </tr>
                </thead>
                <tbody id="heavy-blocks-tbody" class="divide-y divide-slate-800/60 bg-slate-900/40 font-mono text-[11px]">
                    @forelse($report['heavy_blocks']['top_20_core'] ?? [] as $idx => $b)
                        @php
                            $isBid = ($b['side'] === 'BID');
                            $maxQ = $report['heavy_blocks']['summary']['max_block_qty'] ?? 1;
                            $barW = $maxQ > 0 ? min(100, round(($b['wall_qty'] / $maxQ) * 100)) : 0;
                        @endphp
                        <tr class="hover:bg-slate-800/40 transition">
                            <td class="py-2.5 px-3 text-center text-slate-400 font-bold">{{ $idx + 1 }}</td>
                            <td class="py-2.5 px-3 text-white font-bold whitespace-nowrap">
                                {{ $b['time'] }}
                                <span class="text-[9px] px-1 py-0.2 rounded bg-indigo-950 text-indigo-300 border border-indigo-800/60 ml-1">CORE</span>
                            </td>
                            <td class="py-2.5 px-3 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded bg-slate-950 border border-slate-700/80 text-amber-300 font-bold font-mono">
                                    {{ $b['strike'] }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 font-sans text-xs">
                                <span class="text-white font-bold block">{{ $b['instrument'] }}</span>
                                <span class="text-[10px] text-slate-400 block">{{ $b['action'] }}</span>
                            </td>
                            <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                @if($isBid)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">🟢 BID (SUPPORT)</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black bg-rose-500/20 text-rose-300 border border-rose-500/40">🔴 ASK (RESIST)</span>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 text-right">
                                <span class="font-black text-amber-300 text-xs block">{{ number_format($b['wall_qty']) }} <span class="text-[9px] text-slate-400 font-normal">qty</span></span>
                                <div class="w-16 bg-slate-800 h-1 rounded-full ml-auto mt-1 overflow-hidden">
                                    <div class="{{ $isBid ? 'bg-emerald-400' : 'bg-rose-400' }} h-full" style="width: {{ $barW }}%"></div>
                                </div>
                            </td>
                            <td class="py-2.5 px-3 text-right text-slate-200 font-mono">
                                ₹{{ number_format($b['spot_price'], 2) }}
                            </td>
                            <td class="py-2.5 px-3 text-right">
                                <span class="text-slate-200 block">{{ $b['vpoc_volume'] ? number_format($b['vpoc_volume']) . ' vol' : '—' }}</span>
                                <span class="text-[10px] {{ ($b['cvd'] ?? 0) > 0 ? 'text-emerald-400' : (($b['cvd'] ?? 0) < 0 ? 'text-rose-400' : 'text-slate-500') }} block font-mono">
                                    {{ isset($b['cvd']) && $b['cvd'] !== null ? (($b['cvd'] > 0 ? '+' : '') . number_format($b['cvd']) . ' CVD') : '—' }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 font-sans text-xs text-slate-300 max-w-xs truncate" title="{{ $b['oi_details'] }}">
                                {{ $b['oi_details'] ?: '—' }}
                            </td>
                            <td class="py-2.5 px-3 text-center whitespace-nowrap font-sans">
                                @if($b['outcome'] === 'DEFENDED')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">🎯 Defended</span>
                                @elseif($b['outcome'] === 'SWEPT')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black bg-amber-500/20 text-amber-300 border border-amber-500/40">💥 Swept / Broken</span>
                                @elseif($b['outcome'] === 'EXECUTED')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black bg-cyan-500/20 text-cyan-300 border border-cyan-500/40">⚡ Tape Sweep</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-300">⏳ Active</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-8 text-center text-slate-400 font-sans">No block transactions matching the selected criteria.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Active Filter Counter & Footnote --}}
        <div class="flex items-center justify-between text-xs text-slate-400 px-1 pt-1">
            <span id="blocks-count-label">Showing Top {{ count($report['heavy_blocks']['top_20_core'] ?? []) }} heavy block transactions in Core Market Hours (09:30 - 15:15).</span>
            <span class="text-[10px] text-slate-500 font-sans">💡 Tip: Filter by "Core Market Hours" to view true midday institutional positioning without the 09:15 open and 15:15 expiry/auction noise.</span>
        </div>
    </div>

</div>

<script>
    const _heavyBlocksData = @json($report['heavy_blocks']['all'] ?? []);
    let _activeBlockTimeFilter = 'core';

    function setBlockTimeFilter(filterType, btnEl) {
        _activeBlockTimeFilter = filterType;
        document.querySelectorAll('.block-time-btn').forEach(b => {
            b.className = 'block-time-btn px-2.5 py-1 rounded-lg text-xs font-bold transition bg-slate-900 text-slate-400 hover:text-white border border-slate-800';
        });
        if (btnEl) {
            btnEl.className = 'block-time-btn px-2.5 py-1 rounded-lg text-xs font-bold transition bg-indigo-600 text-white shadow-sm border border-indigo-400/30';
        }
        applyBlockFilters();
    }

    function applyBlockFilters() {
        const side = document.getElementById('block-side-filter')?.value || 'all';
        const outcome = document.getElementById('block-outcome-filter')?.value || 'all';
        const limitVal = document.getElementById('block-limit-filter')?.value || '20';

        let filtered = _heavyBlocksData.slice();

        // 1. Time Filter
        if (_activeBlockTimeFilter === 'core') {
            filtered = filtered.filter(b => b.is_core_hours);
        } else if (_activeBlockTimeFilter === 'opening') {
            filtered = filtered.filter(b => b.is_opening);
        } else if (_activeBlockTimeFilter === 'closing') {
            filtered = filtered.filter(b => b.is_closing);
        } else if (_activeBlockTimeFilter === 'midday') {
            filtered = filtered.filter(b => b.is_midday);
        }

        // 2. Side Filter
        if (side !== 'all') {
            filtered = filtered.filter(b => b.side === side);
        }

        // 3. Outcome Filter
        if (outcome !== 'all') {
            filtered = filtered.filter(b => b.outcome === outcome);
        }

        // Sort descending by wall_qty
        filtered.sort((a, b) => b.wall_qty - a.wall_qty);

        const limit = limitVal === 'all' ? filtered.length : parseInt(limitVal, 10);
        const displayList = filtered.slice(0, limit);

        renderHeavyBlocksTable(displayList, filtered.length);
    }

    function renderHeavyBlocksTable(blocks, totalMatching) {
        const tbody = document.getElementById('heavy-blocks-tbody');
        const countLabel = document.getElementById('blocks-count-label');
        if (!tbody) return;

        if (blocks.length === 0) {
            tbody.innerHTML = `<tr><td colspan="10" class="py-8 text-center text-slate-400 font-sans">No block transactions matching the selected criteria.</td></tr>`;
            if (countLabel) countLabel.textContent = `0 blocks found.`;
            return;
        }

        if (countLabel) {
            countLabel.textContent = `Showing Top ${blocks.length} of ${totalMatching} matching blocks (${_activeBlockTimeFilter === 'core' ? 'Core Market Hours 09:30 - 15:15' : (_activeBlockTimeFilter === 'all' ? 'All Hours' : _activeBlockTimeFilter)}).`;
        }

        const maxQty = Math.max(...blocks.map(b => b.wall_qty), 1);

        let html = '';
        blocks.forEach((b, idx) => {
            const isBid = (b.side === 'BID');
            const sideBadge = isBid 
                ? '<span class="px-2 py-0.5 rounded text-[10px] font-black bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">🟢 BID (SUPPORT)</span>' 
                : '<span class="px-2 py-0.5 rounded text-[10px] font-black bg-rose-500/20 text-rose-300 border border-rose-500/40">🔴 ASK (RESIST)</span>';
            
            let outcomeBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-300">⏳ Active</span>';
            if (b.outcome === 'DEFENDED') {
                outcomeBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-black bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">🎯 Defended</span>';
            } else if (b.outcome === 'SWEPT') {
                outcomeBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-black bg-amber-500/20 text-amber-300 border border-amber-500/40">💥 Swept / Broken</span>';
            }

            const timePhaseBadge = b.is_core_hours 
                ? '<span class="text-[9px] px-1 py-0.2 rounded bg-indigo-950 text-indigo-300 border border-indigo-800/60 ml-1">CORE</span>'
                : (b.is_opening ? '<span class="text-[9px] px-1 py-0.2 rounded bg-amber-950 text-amber-300 border border-amber-800/60 ml-1">OPEN</span>' : '<span class="text-[9px] px-1 py-0.2 rounded bg-purple-950 text-purple-300 border border-purple-800/60 ml-1">CLOSE</span>');

            const barWidth = Math.min(100, Math.round((b.wall_qty / maxQty) * 100));

            html += `
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="py-2.5 px-3 text-center text-slate-400 font-bold">${idx + 1}</td>
                    <td class="py-2.5 px-3 text-white font-bold whitespace-nowrap">
                        ${b.time} ${timePhaseBadge}
                    </td>
                    <td class="py-2.5 px-3 whitespace-nowrap">
                        <span class="px-2 py-0.5 rounded bg-slate-950 border border-slate-700/80 text-amber-300 font-bold font-mono">
                            ${b.strike}
                        </span>
                    </td>
                    <td class="py-2.5 px-3 font-sans text-xs">
                        <span class="text-white font-bold block">${b.instrument}</span>
                        <span class="text-[10px] text-slate-400 block">${b.action}</span>
                    </td>
                    <td class="py-2.5 px-3 text-center whitespace-nowrap">
                        ${sideBadge}
                    </td>
                    <td class="py-2.5 px-3 text-right">
                        <span class="font-black text-amber-300 text-xs block">${b.wall_qty.toLocaleString()} <span class="text-[9px] text-slate-400 font-normal">qty</span></span>
                        <div class="w-16 bg-slate-800 h-1 rounded-full ml-auto mt-1 overflow-hidden">
                            <div class="${isBid ? 'bg-emerald-400' : 'bg-rose-400'} h-full" style="width: ${barWidth}%"></div>
                        </div>
                    </td>
                    <td class="py-2.5 px-3 text-right text-slate-200 font-mono">
                        ₹${parseFloat(b.spot_price).toFixed(2)}
                    </td>
                    <td class="py-2.5 px-3 text-right">
                        <span class="text-slate-200 block">${b.vpoc_volume ? b.vpoc_volume.toLocaleString() + ' vol' : '—'}</span>
                        <span class="text-[10px] ${b.cvd > 0 ? 'text-emerald-400' : (b.cvd < 0 ? 'text-rose-400' : 'text-slate-500')} block font-mono">
                            ${b.cvd !== null && b.cvd !== undefined ? (b.cvd > 0 ? '+' : '') + Math.round(b.cvd).toLocaleString() + ' CVD' : '—'}
                        </span>
                    </td>
                    <td class="py-2.5 px-3 font-sans text-xs text-slate-300 max-w-xs truncate" title="${b.oi_details}">
                        ${b.oi_details || '<span class="text-slate-500">—</span>'}
                    </td>
                    <td class="py-2.5 px-3 text-center whitespace-nowrap font-sans">
                        ${outcomeBadge}
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    function copyDeskBrief() {
        const text = `🏛️ **{{ $report['meta']['symbol'] }} EOD CUMULATIVE INSTITUTIONAL REPORT (${$report['meta']['trade_date']})**
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 **WHO DOMINATED:** {{ $report['dominance_matrix']['who']['verdict'] }} (Bulls: {{ $report['dominance_matrix']['bull_score_pct'] }}% | Bears: {{ $report['dominance_matrix']['bear_score_pct'] }}%)
🎯 **ARCHETYPE:** {{ $report['dominance_matrix']['archetype'] }}
⏱️ **PEAK WINDOW:** {{ $report['dominance_matrix']['when']['peak_window'] }}
📍 **POC (POINT OF CONTROL):** {{ $report['dominance_matrix']['range']['poc'] }}
📏 **VALUE AREA SHELF:** {{ $report['range_summary']['dominant_shelf'] }}
📖 **JOURNAL WIN RATE:** {{ $report['journal_summary']['win_rate_pct'] }}% (Net PnL: {{ $report['journal_summary']['net_pnl_pts'] }} Pts)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📐 **TOMORROW'S KEY QUANT LEVELS:**
• R3 (Ceiling / Day High): ₹{{ number_format($report['tomorrow_playbook']['levels'][0]['price'], 2) }}
• R2 (VAH / Ask Wall): ₹{{ number_format($report['tomorrow_playbook']['levels'][1]['price'], 2) }}
• R1 (Immediate Resistance): ₹{{ number_format($report['tomorrow_playbook']['levels'][2]['price'], 2) }}
⚡ **CENTRAL PIVOT (ANCHOR): ₹{{ number_format($report['tomorrow_playbook']['central_pivot'], 2) }}**
• S1 (Immediate Support): ₹{{ number_format($report['tomorrow_playbook']['levels'][4]['price'], 2) }}
• S2 (VAL / Bid Wall): ₹{{ number_format($report['tomorrow_playbook']['levels'][5]['price'], 2) }}
• S3 (Floor / Day Low): ₹{{ number_format($report['tomorrow_playbook']['levels'][6]['price'], 2) }}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🚀 **PLAN A (BULLISH):** Above ₹{{ number_format($report['tomorrow_playbook']['central_pivot'], 0) }} targets ₹{{ number_format($report['tomorrow_playbook']['levels'][1]['price'], 0) }} & ₹{{ number_format($report['tomorrow_playbook']['levels'][0]['price'], 0) }}.
🩸 **PLAN B (BEARISH):** Below ₹{{ number_format($report['tomorrow_playbook']['central_pivot'], 0) }} targets ₹{{ number_format($report['tomorrow_playbook']['levels'][5]['price'], 0) }} & ₹{{ number_format($report['tomorrow_playbook']['levels'][6]['price'], 0) }}.`;

        navigator.clipboard.writeText(text).then(() => {
            alert('Institutional Desk Brief copied to clipboard!');
        }).catch(() => {
            prompt('Copy desk brief:', text);
        });
    }
</script>
@endsection
