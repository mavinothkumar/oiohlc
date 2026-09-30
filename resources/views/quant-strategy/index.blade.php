@extends('layouts.app')

@section('title', 'Nifty 50 High-Probability Quant Strategy (80%+ Target)')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- TOP HEADER & CONTROLS BAR -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <span class="p-2 bg-indigo-50 text-indigo-700 rounded-xl font-bold text-lg">⚡ QUANT</span>
                <div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">Nifty 50 High-Probability Options Strategy</h1>
                    <p class="text-sm text-slate-500">Mathematical 4-Pillar Confluence Engine • Target 80%+ Win Probability • Defined Risk</p>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route('quant-strategy.index') }}" class="flex flex-wrap items-center gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Trade Date</label>
                <select name="date" onchange="this.form.submit()" class="bg-slate-50 border border-slate-300 text-slate-800 text-sm rounded-lg px-3 py-1.5 focus:ring-indigo-500 focus:border-indigo-500 font-bold">
                    @foreach($availableDates as $d)
                        <option value="{{ $d }}" {{ $selectedDate === $d ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::parse($d)->format('d M Y') }}
                            @if($d === $today) (Today - Live) @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Target Expiry</label>
                <select name="expiry" onchange="this.form.submit()" class="bg-slate-50 border border-slate-300 text-slate-800 text-sm rounded-lg px-3 py-1.5 focus:ring-indigo-500 focus:border-indigo-500 font-bold">
                    @foreach($expiries as $exp)
                        <option value="{{ $exp }}" {{ $selectedExpiry === $exp ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::parse($exp)->format('d M Y') }}
                            @if($exp === $selectedDate) (Current / 0-DTE)
                            @elseif($loop->iteration === 2) (Next Weekly Expiry)
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Entry Window</label>
                <select name="entry_time" onchange="this.form.submit()" class="bg-slate-50 border border-slate-300 text-slate-800 text-sm rounded-lg px-3 py-1.5 focus:ring-indigo-500 focus:border-indigo-500 font-bold">
                    @foreach($entryTimes as $tVal => $tLabel)
                        <option value="{{ $tVal }}" {{ $selectedEntryTime === $tVal ? 'selected' : '' }}>{{ $tLabel }}</option>
                    @endforeach
                </select>
            </div>

            <div class="pt-5 flex items-center gap-2">
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-semibold rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition">
                    🔄 Refresh
                </button>
            </div>
        </form>
    </div>


    <!-- Quick Expiry Toggle Pill for Today -->
    @if($selectedDate === $today && $expiries->count() >= 2)
    <div class="flex items-center gap-2 bg-indigo-50/60 border border-indigo-100 rounded-xl p-2.5">
        <span class="text-xs font-bold text-indigo-900 uppercase tracking-wider px-2">⚡ Quick Mode:</span>
        <a href="{{ route('quant-strategy.index', ['date' => $today, 'expiry' => $expiries[0]]) }}"
           class="px-3 py-1 rounded-lg text-xs font-bold transition shadow-sm {{ $selectedExpiry === $expiries[0] ? 'bg-indigo-600 text-white' : 'bg-white text-indigo-700 hover:bg-indigo-100 border border-indigo-200' }}">
           🔥 Current Expiry (0-DTE Today: {{ \Carbon\Carbon::parse($expiries[0])->format('d M') }})
        </a>
        <a href="{{ route('quant-strategy.index', ['date' => $today, 'expiry' => $expiries[1]]) }}"
           class="px-3 py-1 rounded-lg text-xs font-bold transition shadow-sm {{ $selectedExpiry === $expiries[1] ? 'bg-indigo-600 text-white' : 'bg-white text-indigo-700 hover:bg-indigo-100 border border-indigo-200' }}">
           🛡️ Next Expiry (Weekly Safe: {{ \Carbon\Carbon::parse($expiries[1])->format('d M') }})
        </a>
    </div>
    @endif


    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- REAL-TIME STATUS & SPOT BANNER (DUAL SNAPSHOT: ENTRY VS LIVE) -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Spot Price (Entry vs Live)</span>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-2xl font-black text-slate-900">{{ number_format($signal['live_spot'], 2) }}</span>
                <span class="text-xs px-2 py-0.5 rounded font-bold {{ $signal['spot_change'] >= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                    {{ $signal['spot_change'] >= 0 ? '+' : '' }}{{ $signal['spot_change'] }} pts
                </span>
            </div>
            <div class="text-xs text-slate-400 mt-1">
                Entry Spot: <strong class="text-slate-700">{{ number_format($signal['entry_spot'], 2) }}</strong> (@ {{ $signal['entry_time'] }})
            </div>
        </div>

        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Live Trade Running P&L</span>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-2xl font-black {{ $signal['financials']['live_pnl_inr'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ $signal['financials']['live_pnl_inr'] >= 0 ? '+' : '' }}₹{{ number_format($signal['financials']['live_pnl_inr']) }}
                </span>
                <span class="text-xs font-bold px-2 py-0.5 rounded {{ $signal['financials']['live_pnl_inr'] >= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                    {{ $signal['financials']['live_pnl_points'] >= 0 ? '+' : '' }}{{ $signal['financials']['live_pnl_points'] }} pts
                </span>
            </div>
            <div class="text-xs text-slate-400 mt-1">
                Net Prem: <strong>₹{{ $signal['financials']['current_net_premium'] }}</strong> (Target: ₹{{ $signal['financials']['target_credit_exit'] }})
            </div>
        </div>

        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Days to Expiry (DTE)</span>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-2xl font-black text-slate-900">{{ $signal['dte'] }}</span>
                <span class="text-xs font-medium text-slate-500">{{ $signal['dte'] <= 1 ? 'Intraday/Expiry Day' : 'Days remaining' }}</span>
            </div>
            <div class="text-xs text-slate-400 mt-1">
                ATM Strike: <strong class="text-slate-700">{{ $signal['atm_strike'] }}</strong> (Round 100)
            </div>
        </div>

        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Implied Volatility (IV)</span>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-2xl font-black text-slate-900">{{ $signal['iv'] }}%</span>
                <span class="text-xs px-2 py-0.5 rounded font-bold {{ $signal['iv'] <= 16 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                    {{ $signal['iv'] <= 16 ? 'Stable Regime' : 'Elevated' }}
                </span>
            </div>
            <div class="text-xs text-slate-400 mt-1">
                Put/Call Ratio (PCR): <strong class="text-slate-700">{{ $signal['pcr'] }}</strong>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- MASTER TRADE SIGNAL ACTION CARD -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-gradient-to-r {{ $signal['is_trade_ready'] ? 'from-emerald-50/70 via-white to-white' : 'from-amber-50/70 via-white to-white' }}">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <span class="px-3.5 py-1.5 rounded-full text-sm font-black tracking-wide uppercase shadow-sm
                            {{ $signal['badge_color'] === 'emerald' ? 'bg-emerald-600 text-white' : ($signal['badge_color'] === 'amber' ? 'bg-amber-500 text-white' : 'bg-rose-600 text-white') }}">
                            {{ $signal['badge'] }}
                        </span>
                        <span class="text-sm font-semibold text-slate-500">{{ $signal['strategy_name'] }}</span>
                    </div>
                    <p class="mt-2 text-slate-600 text-sm">
                        Entry snapshot locked at <strong class="text-slate-900 font-bold">{{ $signal['entry_time'] }}</strong>.
                        Live tracking active at <strong class="text-slate-900 font-bold">{{ $signal['latest_time'] }}</strong>.
                        Probability of profit: <strong class="text-emerald-700 font-bold">{{ $signal['pop_pct'] }}%</strong>.
                    </p>
                </div>

                <div class="flex items-center gap-4 bg-white/90 backdrop-blur p-3 rounded-xl border border-slate-200">
                    <div class="text-center px-2">
                        <div class="text-xs font-bold text-slate-400 uppercase">Win Probability</div>
                        <div class="text-2xl font-black text-emerald-600">{{ $signal['pop_pct'] }}%</div>
                    </div>
                    <div class="h-8 w-px bg-slate-200"></div>
                    <div class="text-center px-2">
                        <div class="text-xs font-bold text-slate-400 uppercase">Confluence Score</div>
                        <div class="text-2xl font-black text-indigo-600">{{ $signal['confluence_score'] }}<span class="text-sm font-medium text-slate-400">/100</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Exact 4 Legs Table with Dual Snapshots -->
        <div class="p-6 space-y-6">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <span>🎯</span> Strategy Execution & Live Tracking
                    </h3>
                    <span class="text-xs font-bold px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700">
                        Locked Entry: {{ $signal['entry_time'] }} | Live: {{ $signal['latest_time'] }}
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-3 text-left font-bold text-slate-600">Action</th>
                                <th class="px-3 py-3 text-left font-bold text-slate-600">Contract</th>
                                <th class="px-3 py-3 text-center font-bold text-slate-600">Strike</th>
                                <th class="px-3 py-3 text-center font-bold text-slate-600">Delta ($\Delta$)</th>
                                <th class="px-3 py-3 text-right font-bold text-slate-600">Entry LTP ({{ $signal['entry_time'] }})</th>
                                <th class="px-3 py-3 text-right font-bold text-slate-600">Live LTP</th>
                                <th class="px-3 py-3 text-right font-bold text-slate-600">Hard Leg Stop-Loss</th>
                                <th class="px-3 py-3 text-right font-bold text-slate-600">Live Leg P&amp;L</th>
                                <th class="px-3 py-3 text-center font-bold text-slate-600">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white font-medium">
                            <!-- Short Call -->
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-3 py-3"><span class="px-2 py-0.5 rounded font-black text-xs bg-rose-100 text-rose-800">SELL</span></td>
                                <td class="px-3 py-3 font-bold text-slate-900">{{ $signal['legs']['short_ce']['symbol'] }}</td>
                                <td class="px-3 py-3 text-center">{{ $signal['legs']['short_ce']['strike'] }}</td>
                                <td class="px-3 py-3 text-center font-mono">{{ $signal['legs']['short_ce']['delta'] }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-slate-900">₹{{ number_format($signal['legs']['short_ce']['entry_ltp'], 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-indigo-700">₹{{ number_format($signal['legs']['short_ce']['live_ltp'], 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-rose-600">₹{{ number_format($signal['legs']['short_ce']['sl_ltp'], 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold {{ $signal['legs']['short_ce']['pnl_inr'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ $signal['legs']['short_ce']['pnl_inr'] >= 0 ? '+' : '' }}₹{{ number_format($signal['legs']['short_ce']['pnl_inr']) }}
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <span class="px-2 py-0.5 rounded text-xs font-bold {{ $signal['legs']['short_ce']['sl_status'] === 'SAFE' ? 'bg-emerald-100 text-emerald-800' : ($signal['legs']['short_ce']['sl_status'] === 'WARNING' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') }}">
                                        {{ $signal['legs']['short_ce']['sl_status'] }}
                                    </span>
                                </td>
                            </tr>
                            <!-- Hedge Call -->
                            <tr class="hover:bg-slate-50/80 bg-slate-50/30">
                                <td class="px-3 py-3"><span class="px-2 py-0.5 rounded font-black text-xs bg-emerald-100 text-emerald-800">BUY</span></td>
                                <td class="px-3 py-3 font-bold text-slate-900">{{ $signal['legs']['hedge_ce']['symbol'] }}</td>
                                <td class="px-3 py-3 text-center">{{ $signal['legs']['hedge_ce']['strike'] }}</td>
                                <td class="px-3 py-3 text-center font-mono text-slate-400">~0.05</td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-slate-900">₹{{ number_format($signal['legs']['hedge_ce']['entry_ltp'], 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-indigo-700">₹{{ number_format($signal['legs']['hedge_ce']['live_ltp'], 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono text-slate-400">N/A (Hedge)</td>
                                <td class="px-3 py-3 text-right font-mono font-bold {{ $signal['legs']['hedge_ce']['pnl_inr'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ $signal['legs']['hedge_ce']['pnl_inr'] >= 0 ? '+' : '' }}₹{{ number_format($signal['legs']['hedge_ce']['pnl_inr']) }}
                                </td>
                                <td class="px-3 py-3 text-center"><span class="px-2 py-0.5 rounded text-xs font-bold bg-slate-100 text-slate-600">HEDGE</span></td>
                            </tr>
                            <!-- Short Put -->
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-3 py-3"><span class="px-2 py-0.5 rounded font-black text-xs bg-rose-100 text-rose-800">SELL</span></td>
                                <td class="px-3 py-3 font-bold text-slate-900">{{ $signal['legs']['short_pe']['symbol'] }}</td>
                                <td class="px-3 py-3 text-center">{{ $signal['legs']['short_pe']['strike'] }}</td>
                                <td class="px-3 py-3 text-center font-mono">{{ $signal['legs']['short_pe']['delta'] }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-slate-900">₹{{ number_format($signal['legs']['short_pe']['entry_ltp'], 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-indigo-700">₹{{ number_format($signal['legs']['short_pe']['live_ltp'], 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-rose-600">₹{{ number_format($signal['legs']['short_pe']['sl_ltp'], 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold {{ $signal['legs']['short_pe']['pnl_inr'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ $signal['legs']['short_pe']['pnl_inr'] >= 0 ? '+' : '' }}₹{{ number_format($signal['legs']['short_pe']['pnl_inr']) }}
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <span class="px-2 py-0.5 rounded text-xs font-bold {{ $signal['legs']['short_pe']['sl_status'] === 'SAFE' ? 'bg-emerald-100 text-emerald-800' : ($signal['legs']['short_pe']['sl_status'] === 'WARNING' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') }}">
                                        {{ $signal['legs']['short_pe']['sl_status'] }}
                                    </span>
                                </td>
                            </tr>
                            <!-- Hedge Put -->
                            <tr class="hover:bg-slate-50/80 bg-slate-50/30">
                                <td class="px-3 py-3"><span class="px-2 py-0.5 rounded font-black text-xs bg-emerald-100 text-emerald-800">BUY</span></td>
                                <td class="px-3 py-3 font-bold text-slate-900">{{ $signal['legs']['hedge_pe']['symbol'] }}</td>
                                <td class="px-3 py-3 text-center">{{ $signal['legs']['hedge_pe']['strike'] }}</td>
                                <td class="px-3 py-3 text-center font-mono text-slate-400">~0.05</td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-slate-900">₹{{ number_format($signal['legs']['hedge_pe']['entry_ltp'], 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-indigo-700">₹{{ number_format($signal['legs']['hedge_pe']['live_ltp'], 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono text-slate-400">N/A (Hedge)</td>
                                <td class="px-3 py-3 text-right font-mono font-bold {{ $signal['legs']['hedge_pe']['pnl_inr'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ $signal['legs']['hedge_pe']['pnl_inr'] >= 0 ? '+' : '' }}₹{{ number_format($signal['legs']['hedge_pe']['pnl_inr']) }}
                                </td>
                                <td class="px-3 py-3 text-center"><span class="px-2 py-0.5 rounded text-xs font-bold bg-slate-100 text-slate-600">HEDGE</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Financials & Asymmetry Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 p-4 bg-slate-50 rounded-xl border border-slate-200">
                <div class="p-2">
                    <span class="text-xs font-semibold text-slate-500 uppercase">Entry Net Credit</span>
                    <div class="text-lg font-black text-slate-900">₹{{ $signal['financials']['net_credit_points'] }} <span class="text-xs font-normal text-slate-500">pts</span></div>
                    <span class="text-xs text-slate-400">₹{{ number_format($signal['financials']['max_profit_per_lot']) }} total value</span>
                </div>
                <div class="p-2 border-l border-slate-200">
                    <span class="text-xs font-semibold text-slate-500 uppercase">Target Profit (60% Exit)</span>
                    <div class="text-lg font-black text-emerald-600">+₹{{ number_format($signal['financials']['target_profit_per_lot']) }} <span class="text-xs font-normal text-slate-500">/ lot</span></div>
                    <span class="text-xs text-slate-400">Target premium: ₹{{ $signal['financials']['target_credit_exit'] }}</span>
                </div>
                <div class="p-2 border-l border-slate-200">
                    <span class="text-xs font-semibold text-slate-500 uppercase">Plan Stop-Loss Risk</span>
                    <div class="text-lg font-black text-rose-600">-₹{{ number_format($signal['financials']['plan_risk_per_lot']) }} <span class="text-xs font-normal text-slate-500">/ lot</span></div>
                    <span class="text-xs text-slate-400">At hard +50% Leg SL</span>
                </div>
                <div class="p-2 border-l border-slate-200">
                    <span class="text-xs font-semibold text-slate-500 uppercase">Live Running P&amp;L</span>
                    <div class="text-lg font-black {{ $signal['financials']['live_pnl_inr'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ $signal['financials']['live_pnl_inr'] >= 0 ? '+' : '' }}₹{{ number_format($signal['financials']['live_pnl_inr']) }}
                    </div>
                    <span class="text-xs text-slate-400">{{ $signal['financials']['live_pnl_points'] >= 0 ? '+' : '' }}{{ $signal['financials']['live_pnl_points'] }} pts decay</span>
                </div>
                <div class="p-2 border-l border-slate-200">
                    <span class="text-xs font-semibold text-slate-500 uppercase">Black-Swan Wing Cap</span>
                    <div class="text-lg font-black text-slate-600">-₹{{ number_format($signal['financials']['catastrophic_gap_risk']) }}</div>
                    <span class="text-xs text-slate-400">Overnight disaster ceiling</span>
                </div>
            </div>


            <!-- Risk Clarification Note -->
            <div class="p-3 bg-amber-50/80 border border-amber-200 rounded-xl flex items-start gap-3">
                <span class="text-base">💡</span>
                <div class="text-xs text-amber-900 leading-relaxed">
                    <strong>Why You Never Take a 7x Loss:</strong> In live trading, your rule exits the tested leg immediately at <strong>+50% stop-loss</strong> (limiting your actual loss to ~<strong>₹{{ number_format($signal['financials']['plan_risk_per_lot']) }}</strong> per lot while you target <strong>+₹{{ number_format($signal['financials']['target_profit_per_lot']) }}</strong> profit). The larger Black-Swan figure (-₹{{ number_format($signal['financials']['catastrophic_gap_risk']) }}) is only your outer protective hedge wing in case of an extreme overnight gap or circuit-breaker.
                </div>
            </div>


            <!-- Execution & Fire-Fighting Instructions -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="border border-emerald-200 bg-emerald-50/40 rounded-xl p-4">
                    <h4 class="font-bold text-emerald-900 text-sm flex items-center gap-2 mb-2">
                        <span>🎯</span> Exit Strategy & Profit Locking
                    </h4>
                    <ul class="text-xs text-emerald-800 space-y-1.5 list-disc list-inside">
                        <li><strong>Target Exit:</strong> {{ $signal['execution_plan']['exit_target'] }}</li>
                        <li><strong>Hard Leg Stop-Loss:</strong> {{ $signal['execution_plan']['exit_stop_loss'] }}</li>
                        <li><strong>Time Cutoff:</strong> {{ $signal['execution_plan']['exit_time'] }}</li>
                    </ul>
                </div>

                <div class="border border-indigo-200 bg-indigo-50/40 rounded-xl p-4">
                    <h4 class="font-bold text-indigo-900 text-sm flex items-center gap-2 mb-2">
                        <span>🛡️</span> Intraday Fire-Fighting & Adjustment Protocol
                    </h4>
                    <p class="text-xs text-indigo-900 leading-relaxed">
                        {{ $signal['execution_plan']['adjustment_rule'] }}
                    </p>
                    <p class="text-xs text-indigo-700 mt-1 italic">
                        *This rule locks in profit on the decaying wing and neutralizes delta when Nifty experiences an unexpected midday trend.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- THE 4-FILTER CONFLUENCE VERIFICATION -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
        <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
            <span>🔬</span> Data Verification Checklist (Why This Setup Has 80%+ Accuracy)
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            @foreach($signal['filters'] as $fKey => $filter)
                <div class="p-4 rounded-xl border {{ $filter['passed'] ? 'border-emerald-200 bg-emerald-50/30' : 'border-rose-200 bg-rose-50/30' }}">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $filter['title'] }}</span>
                        <span class="text-sm">{{ $filter['passed'] ? '✅' : '❌' }}</span>
                    </div>
                    <div class="mt-2 text-lg font-black {{ $filter['passed'] ? 'text-emerald-700' : 'text-rose-700' }}">
                        +{{ $filter['points'] }} <span class="text-xs font-normal text-slate-400">pts</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-600 leading-relaxed">{{ $filter['details'] }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- THE 4 MATHEMATICAL PILLARS & MONTE CARLO STRESS TEST -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Pillar 1: Statistics & Expectancy -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-900">Pillar 1: Statistical Edge</h3>
                <span class="text-xs font-bold px-2 py-0.5 rounded bg-indigo-50 text-indigo-700">N = {{ $totalCount }} Trades</span>
            </div>

            <div class="space-y-3 text-sm">
                <div class="flex justify-between py-1.5 border-b border-slate-100">
                    <span class="text-slate-500">Historical Win Rate</span>
                    <strong class="text-emerald-600 font-bold">{{ $winRatePct }}%</strong>
                </div>
                <div class="flex justify-between py-1.5 border-b border-slate-100">
                    <span class="text-slate-500">Median PnL per Trade</span>
                    <strong class="text-slate-900 font-bold">₹{{ number_format($stats['median'], 1) }}</strong>
                </div>
                <div class="flex justify-between py-1.5 border-b border-slate-100">
                    <span class="text-slate-500">Mean PnL (Average)</span>
                    <strong class="text-slate-900 font-bold">₹{{ number_format($stats['mean'], 1) }}</strong>
                </div>
                <div class="flex justify-between py-1.5 border-b border-slate-100">
                    <span class="text-slate-500">Average Win</span>
                    <strong class="text-emerald-600 font-bold">+₹{{ number_format($avgWin, 1) }}</strong>
                </div>
                <div class="flex justify-between py-1.5 border-b border-slate-100">
                    <span class="text-slate-500">Average Loss (Controlled)</span>
                    <strong class="text-rose-600 font-bold">-₹{{ number_format($avgLoss, 1) }}</strong>
                </div>
                <div class="flex justify-between py-1.5 border-b border-slate-100">
                    <span class="text-slate-500">Mathematical Expectancy ($E$)</span>
                    <strong class="text-indigo-600 font-bold">+₹{{ number_format($expectancy, 1) }} / trade</strong>
                </div>
                <div class="flex justify-between py-1.5">
                    <span class="text-slate-500">Kurtosis (Fat Tails)</span>
                    <span class="text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-mono">{{ $stats['excess_kurtosis'] }}</span>
                </div>
            </div>
        </div>

        <!-- Pillar 4: Cornish-Fisher VaR & Monte Carlo -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4 lg:col-span-2">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Pillar 4: Monte Carlo Stress Test & Fat-Tail Risk</h3>
                    <p class="text-xs text-slate-500">1,000 Resampled Simulations • 95% Confidence Intervals</p>
                </div>
                <span class="text-xs font-bold px-2 py-1 rounded bg-emerald-50 text-emerald-700">Ruin Probability: {{ $monteCarlo['prob_ruin_pct'] }}%</span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-center">
                    <span class="text-xs font-semibold text-slate-500 uppercase">Median Ending Equity</span>
                    <div class="text-base font-black text-slate-900 mt-1">₹{{ number_format($monteCarlo['median_final_equity']) }}</div>
                </div>
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-center">
                    <span class="text-xs font-semibold text-slate-500 uppercase">5th %ile (Worst Case)</span>
                    <div class="text-base font-black text-rose-600 mt-1">₹{{ number_format($monteCarlo['p5_worst_equity']) }}</div>
                </div>
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-center">
                    <span class="text-xs font-semibold text-slate-500 uppercase">Median Max Drawdown</span>
                    <div class="text-base font-black text-slate-900 mt-1">{{ $monteCarlo['median_max_drawdown'] }}%</div>
                </div>
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-center">
                    <span class="text-xs font-semibold text-slate-500 uppercase">95% Fat-Tail VaR</span>
                    <div class="text-base font-black text-rose-600 mt-1">₹{{ number_format($stats['cornish_fisher_var']) }}</div>
                </div>
            </div>

            <!-- Monte Carlo Simulation Chart Canvas -->
            <div class="pt-2">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-slate-500 uppercase">Sample Monte Carlo Equity Paths (Starting ₹1,00,000)</span>
                    <span class="text-xs text-slate-400">Green = Growth Trajectories</span>
                </div>
                <div class="h-44 w-full bg-slate-950 rounded-xl p-3 relative overflow-hidden flex items-center justify-center">
                    <canvas id="mcCanvas" class="w-full h-full"></canvas>
                </div>
            </div>
        </div>

    </div>

</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const trajectories = @json($monteCarlo['sample_trajectories'] ?? []);
        const canvas = document.getElementById('mcCanvas');
        if (!canvas || trajectories.length === 0) return;

        const ctx = canvas.getContext('2d');
        const dpr = window.devicePixelRatio || 1;
        const rect = canvas.getBoundingClientRect();

        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        ctx.scale(dpr, dpr);

        const width = rect.width;
        const height = rect.height;

        // Find min and max across all paths
        let minVal = Infinity;
        let maxVal = -Infinity;
        trajectories.forEach(path => {
            path.forEach(val => {
                if (val < minVal) minVal = val;
                if (val > maxVal) maxVal = val;
            });
        });

        // Add 5% padding
        const pad = (maxVal - minVal) * 0.05 || 1000;
        minVal -= pad;
        maxVal += pad;

        const range = maxVal - minVal;

        // Draw paths
        trajectories.forEach((path, idx) => {
            ctx.beginPath();
            const stepX = width / (path.length - 1);

            path.forEach((val, stepIdx) => {
                const x = stepIdx * stepX;
                const y = height - ((val - minVal) / range) * height;

                if (stepIdx === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            });

            const isPositive = path[path.length - 1] >= path[0];
            ctx.strokeStyle = isPositive ? 'rgba(16, 185, 129, 0.45)' : 'rgba(239, 68, 68, 0.45)';
            ctx.lineWidth = idx === 0 ? 2 : 1;
            ctx.stroke();
        });

        // Draw 100k starting line
        const baselineY = height - ((100000 - minVal) / range) * height;
        ctx.beginPath();
        ctx.setLineDash([4, 4]);
        ctx.moveTo(0, baselineY);
        ctx.lineTo(width, baselineY);
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.3)';
        ctx.lineWidth = 1;
        ctx.stroke();
    });
</script>
@endpush
@endsection
