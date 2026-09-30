@extends('layouts.app')

@section('title', 'Delta Neutral Strategy - Live Peace of Mind Trader')

@section('content')
<div class="w-full px-2 sm:px-4 py-3 space-y-4 text-slate-100 font-sans" id="delta-neutral-app">

    {{-- ════════════════════════ 1. TOP HEADER & LIVE METRICS BAR ════════════════════════ --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 shadow-2xl backdrop-blur-md">
        <div class="flex flex-wrap items-center justify-between gap-3">
            
            {{-- Left: Strategy Identity & Filter Controls --}}
            <div class="flex items-center flex-wrap gap-2.5">
                <div class="flex items-center gap-2 pr-2 border-r border-slate-750">
                    <span class="flex h-3 w-3 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    </span>
                    <h1 class="text-base sm:text-lg font-black tracking-tight bg-gradient-to-r from-emerald-400 via-teal-300 to-cyan-400 bg-clip-text text-transparent">
                        ⚖️ DELTA NEUTRAL
                    </h1>
                </div>

                {{-- Symbol Selector --}}
                <select id="filter-symbol" class="bg-slate-800 hover:bg-slate-750 border border-slate-700 rounded-xl px-3 py-1.5 text-xs text-white font-bold tracking-wide focus:ring-2 focus:ring-emerald-500 focus:outline-none cursor-pointer">
                    <option value="NIFTY" {{ $symbol === 'NIFTY' ? 'selected' : '' }}>NIFTY 50</option>
                    <option value="BANKNIFTY" {{ $symbol === 'BANKNIFTY' ? 'selected' : '' }}>BANKNIFTY</option>
                </select>

                {{-- Expiry Selector --}}
                <select id="filter-expiry" class="bg-slate-800 hover:bg-slate-750 border border-slate-700 rounded-xl px-3 py-1.5 text-xs text-emerald-300 font-bold focus:ring-2 focus:ring-emerald-500 focus:outline-none cursor-pointer">
                    @foreach($expiries as $exp)
                        <option value="{{ $exp->expiry_date }}" {{ $exp->expiry_date == $selectedExpiry ? 'selected' : '' }}>
                            📅 {{ \Carbon\Carbon::parse($exp->expiry_date)->format('d M Y') }} {{ $exp->is_current ? '(Current)' : '' }}
                        </option>
                    @endforeach
                </select>

                {{-- ATM Strike Quick Shift Controller --}}
                <div class="inline-flex items-center bg-slate-800 border border-slate-700 rounded-xl p-0.5">
                    <span class="text-[11px] font-bold text-slate-400 px-2 select-none">ATM</span>
                    <button type="button" id="btn-atm-minus" class="text-slate-300 hover:text-white px-2 py-0.5 rounded text-xs font-bold transition hover:bg-slate-700" title="Minus {{ $strikeStep }}">
                        -{{ $strikeStep }}
                    </button>
                    <input type="number" id="filter-atm" value="{{ $atmStrike }}" step="{{ $strikeStep }}" class="w-16 bg-slate-900 border border-slate-700 rounded px-1 py-0.5 text-xs text-emerald-400 font-extrabold text-center focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                    <button type="button" id="btn-atm-plus" class="text-slate-300 hover:text-white px-2 py-0.5 rounded text-xs font-bold transition hover:bg-slate-700" title="Plus {{ $strikeStep }}">
                        +{{ $strikeStep }}
                    </button>
                </div>

                <button type="button" id="btn-refresh-data" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-3 py-1.5 rounded-xl text-xs transition shadow flex items-center gap-1.5">
                    <svg id="refresh-spinner" class="animate-spin h-3.5 w-3.5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>Update Data</span>
                </button>
            </div>

            {{-- Right: Live India VIX, Spot, and WebSocket Status --}}
            <div class="flex items-center flex-wrap gap-2.5 text-xs">
                
                {{-- Live Spot Badge --}}
                <div class="bg-slate-800 border border-slate-700 rounded-xl px-3 py-1.5 flex items-center gap-2">
                    <span class="text-slate-400 font-medium">{{ $symbol }} Spot:</span>
                    <span id="live-spot-val" class="font-extrabold font-mono text-cyan-400 text-sm">
                        {{ number_format($indexSpot, 2) }}
                    </span>
                    <span id="live-spot-diff" class="text-[11px] font-bold text-slate-400">
                        {{ ($indexSpot - $indexOpen) >= 0 ? '+' : '' }}{{ number_format($indexSpot - $indexOpen, 2) }}
                    </span>
                </div>

                {{-- Live India VIX Badge (The Panic Killer) --}}
                <div class="bg-gradient-to-r from-purple-950/60 to-slate-800 border border-purple-500/40 rounded-xl px-3 py-1.5 flex items-center gap-2" title="India VIX Volatility & 1-Day Expected Move">
                    <div class="flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full bg-purple-400 animate-pulse"></span>
                        <span class="text-purple-300 font-bold uppercase tracking-wider text-[11px]">India VIX:</span>
                    </div>
                    <span id="live-vix-val" class="font-mono font-extrabold text-purple-200 text-sm">
                        {{ number_format($vix['value'], 2) }}
                    </span>
                    <span id="live-vix-change" class="text-[10px] font-semibold {{ $vix['change'] >= 0 ? 'text-rose-400' : 'text-emerald-400' }}">
                        ({{ $vix['change'] >= 0 ? '+' : '' }}{{ $vix['change_pct'] }}%)
                    </span>
                    <div class="pl-2 border-l border-purple-500/30 text-[11px] text-slate-300 font-mono" title="Expected 1-Day Move based on VIX">
                        1D Exp: <span id="vix-1d-range" class="text-amber-300 font-bold">±{{ $vix1DayMove }} pts</span>
                    </div>
                </div>

                {{-- Upstox WebSocket Status --}}
                <div class="flex items-center gap-1.5 bg-slate-800 border border-slate-700 px-2.5 py-1.5 rounded-xl">
                    <span id="ws-dot" class="inline-block h-2.5 w-2.5 rounded-full bg-red-500 shadow-sm"></span>
                    <span id="ws-status-text" class="text-slate-300 text-[11px] font-semibold">Connecting…</span>
                    <span class="text-slate-600">|</span>
                    <span class="text-slate-400 font-mono text-[10px]" id="tick-counter">0 ticks</span>
                </div>
            </div>

        </div>
    </div>

    {{-- ════════════════════════ 2. HERO: VISUAL HOLDING CORRIDOR ("PEACE OF MIND GAUGE") ════════════════════════ --}}
    <div class="bg-gradient-to-b from-slate-900 via-slate-900/95 to-slate-950 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-2xl relative overflow-hidden">
        
        {{-- Header of Corridor --}}
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xl">🛡️</span>
                    <h2 class="text-base sm:text-lg font-extrabold tracking-tight text-white">
                        VISUAL HOLDING CORRIDOR & RECOVERY SHIELD
                    </h2>
                    <span id="strategy-status-badge" class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                        ✅ SAFE & BALANCED
                    </span>
                </div>
                <p class="text-xs text-slate-400 mt-0.5">
                    Mathematically calculated maximum and minimum price boundaries where your position remains in profit.
                </p>
            </div>

            {{-- VIX Safety Cushion Ratio Badge --}}
            <div class="flex items-center gap-2 bg-slate-800/90 border border-slate-700 rounded-xl px-3 py-1.5 shadow">
                <span class="text-xs text-slate-400 font-medium">VIX Safety Cushion:</span>
                <span id="vix-shield-ratio" class="font-extrabold font-mono text-emerald-400 text-sm">--%</span>
                <span class="text-[10px] text-slate-400" id="vix-shield-subtext">(1D Volatility Covered)</span>
            </div>
        </div>

        {{-- Dynamic Interactive Visual Bar Track --}}
        <div class="relative py-7 px-2">
            
            {{-- Background Track (Loss zones on edges, Safe Green Profit Zone in middle) --}}
            <div class="h-6 w-full rounded-full bg-slate-800 flex overflow-hidden border border-slate-700 relative shadow-inner">
                {{-- Left Red Zone (Below Lower Breakeven) --}}
                <div id="corridor-left-red" class="bg-rose-900/60 transition-all duration-300 flex items-center justify-center text-[10px] text-rose-300 font-bold uppercase tracking-wider" style="width: 20%;">
                    Loss Zone
                </div>
                {{-- Middle Green Safe Profit Corridor --}}
                <div id="corridor-safe-green" class="bg-gradient-to-r from-emerald-900/70 via-emerald-800/80 to-emerald-900/70 transition-all duration-300 flex items-center justify-center text-[11px] text-emerald-200 font-extrabold tracking-widest relative" style="width: 60%;">
                    <div class="absolute inset-0 bg-emerald-500/10 animate-pulse"></div>
                    <span class="relative z-10">★ SAFE PROFIT CORRIDOR ★</span>
                </div>
                {{-- Right Red Zone (Above Upper Breakeven) --}}
                <div id="corridor-right-red" class="bg-rose-900/60 transition-all duration-300 flex items-center justify-center text-[10px] text-rose-300 font-bold uppercase tracking-wider" style="width: 20%;">
                    Loss Zone
                </div>
            </div>

            {{-- VIX 1-Day Expected Move Overlay Band --}}
            <div id="vix-band-marker" class="absolute top-5 h-10 border-2 border-purple-400/50 bg-purple-500/10 rounded-lg pointer-events-none transition-all duration-300 z-10 flex items-start justify-center" style="left: 32%; width: 36%;">
                <span class="text-[9px] font-extrabold bg-purple-900/90 text-purple-200 px-2 py-0.5 rounded-full border border-purple-400/40 -mt-3 shadow">
                    VIX 1-Day Move Range (±<span id="vix-band-pts">{{ $vix1DayMove }}</span>)
                </span>
            </div>

            {{-- Lower Breakeven Line & Label --}}
            <div id="marker-lower-be" class="absolute top-1 bottom-1 w-0.5 bg-rose-400 z-20 transition-all duration-300" style="left: 20%;">
                <div class="absolute -top-6 -translate-x-1/2 bg-slate-900 border border-rose-500 text-rose-300 font-mono text-xs font-bold px-2 py-0.5 rounded shadow">
                    MIN: <span id="label-lower-be">--</span>
                </div>
            </div>

            {{-- Upper Breakeven Line & Label --}}
            <div id="marker-upper-be" class="absolute top-1 bottom-1 w-0.5 bg-rose-400 z-20 transition-all duration-300" style="left: 80%;">
                <div class="absolute -top-6 -translate-x-1/2 bg-slate-900 border border-rose-500 text-rose-300 font-mono text-xs font-bold px-2 py-0.5 rounded shadow">
                    MAX: <span id="label-upper-be">--</span>
                </div>
            </div>

            {{-- Dynamic Current SPOT Marker (Pulsing Cyan Indicator) --}}
            <div id="marker-spot-pulse" class="absolute top-0 bottom-0 w-1 bg-cyan-400 z-30 transition-all duration-300 flex flex-col items-center" style="left: 50%;">
                <div class="absolute -bottom-8 bg-cyan-950 border border-cyan-400 text-cyan-300 font-mono text-xs font-extrabold px-2.5 py-1 rounded-lg shadow-lg flex items-center gap-1.5 whitespace-nowrap">
                    <span class="h-2 w-2 rounded-full bg-cyan-400 animate-ping"></span>
                    <span>SPOT:</span>
                    <span id="label-spot-indicator">--</span>
                </div>
            </div>

        </div>

        {{-- Buffer Metrics Grid (Cushion & Safe Distance) --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-6 pt-4 border-t border-slate-800/80">
            
            {{-- Downside Cushion --}}
            <div class="bg-slate-950/60 border border-slate-800 rounded-xl p-3 flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Downside Cushion (To Min)</span>
                    <div class="flex items-baseline gap-1.5 mt-0.5">
                        <span id="cushion-downside-pts" class="text-lg font-extrabold font-mono text-emerald-400">-- pts</span>
                        <span id="cushion-downside-pct" class="text-xs text-emerald-500 font-bold">(--%)</span>
                    </div>
                </div>
                <div class="text-2xl text-emerald-500/80">🛡️</div>
            </div>

            {{-- Current Position Status & Net Skew --}}
            <div class="bg-slate-950/60 border border-slate-800 rounded-xl p-3 flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Net Delta Skew</span>
                    <div class="flex items-baseline gap-1.5 mt-0.5">
                        <span id="metric-net-delta" class="text-lg font-extrabold font-mono text-cyan-400">0.00</span>
                        <span id="metric-net-delta-label" class="text-xs text-slate-400 font-semibold">(Neutral)</span>
                    </div>
                </div>
                <div class="text-2xl">⚖️</div>
            </div>

            {{-- Upside Cushion --}}
            <div class="bg-slate-950/60 border border-slate-800 rounded-xl p-3 flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Upside Cushion (To Max)</span>
                    <div class="flex items-baseline gap-1.5 mt-0.5">
                        <span id="cushion-upside-pts" class="text-lg font-extrabold font-mono text-emerald-400">-- pts</span>
                        <span id="cushion-upside-pct" class="text-xs text-emerald-500 font-bold">(--%)</span>
                    </div>
                </div>
                <div class="text-2xl text-emerald-500/80">🛡️</div>
            </div>

        </div>

    </div>

    {{-- ════════════════════════ 3. ACTIVE BASKET & COMBINED DECAY TRACKER ════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        
        {{-- Left 2 Cols: Active Trade Basket Table --}}
        <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">💼</span>
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider">Active Strategy Basket</h3>
                        <span class="text-xs bg-slate-800 text-slate-400 px-2 py-0.5 rounded-full" id="basket-legs-count">0 Legs</span>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <button type="button" id="btn-clear-basket" class="text-xs text-slate-400 hover:text-rose-400 transition underline">
                            Clear Basket
                        </button>
                        <button type="button" id="btn-add-custom-leg" class="bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 px-2.5 py-1 rounded-lg text-xs font-semibold transition">
                            + Add Leg
                        </button>
                    </div>
                </div>

                {{-- Active Legs Table with OI & Volume Change % --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-slate-950/70 text-slate-400 border-b border-slate-800 text-left">
                                <th class="py-2 px-2.5">Action</th>
                                <th class="py-2 px-2.5">Strike</th>
                                <th class="py-2 px-2.5">Type</th>
                                <th class="py-2 px-2.5 text-right" title="Click quantity to edit">Qty ✏️</th>
                                <th class="py-2 px-2.5 text-right" title="Click entry price to edit">Entry ✏️</th>
                                <th class="py-2 px-2.5 text-right">Live LTP</th>
                                <th class="py-2 px-2.5 text-right">Live P&L</th>
                                <th class="py-2 px-2.5 text-right">OI & Chg%</th>
                                <th class="py-2 px-2.5 text-center">Buildup</th>
                                <th class="py-2 px-2.5 text-center">Manage</th>
                            </tr>
                        </thead>
                        <tbody id="basket-table-body" class="divide-y divide-slate-800/60 font-mono">
                            {{-- Populated by JS --}}
                            <tr>
                                <td colspan="10" class="py-8 text-center text-slate-500 font-sans">
                                    No active legs in basket. Select a preset below or click "Add Leg".
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Summary Footer of Basket --}}
            <div class="mt-4 pt-3 border-t border-slate-800 grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-950/50 rounded-xl p-2.5">
                <div>
                    <span class="text-[10px] text-slate-400 uppercase font-semibold">Total Credit Collected:</span>
                    <div class="text-sm font-bold font-mono text-emerald-400" id="basket-total-credit">₹0.00</div>
                </div>
                <div>
                    <span class="text-[10px] text-slate-400 uppercase font-semibold">Current Value:</span>
                    <div class="text-sm font-bold font-mono text-slate-300" id="basket-current-value">₹0.00</div>
                </div>
                <div>
                    <span class="text-[10px] text-slate-400 uppercase font-semibold">Total Live P&L:</span>
                    <div class="text-base font-extrabold font-mono text-emerald-400" id="basket-live-pnl">₹0.00</div>
                </div>
                <div>
                    <span class="text-[10px] text-slate-400 uppercase font-semibold">Daily Theta Decay (Est):</span>
                    <div class="text-sm font-bold font-mono text-teal-400" id="basket-total-theta">+₹0.00/day</div>
                </div>
            </div>
        </div>

        {{-- Right Col: Combined Premium Decay Tracker & Greeks Meter --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-1.5">
                        <span class="text-lg">⏳</span>
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider">Premium Decay Tracker</h3>
                    </div>
                    <span id="decay-pct-badge" class="text-xs font-mono font-bold bg-teal-500/20 text-teal-300 px-2 py-0.5 rounded-full border border-teal-500/30">
                        0% Decayed
                    </span>
                </div>

                {{-- Progress Meter for Premium Captured --}}
                <div class="space-y-1 mb-4">
                    <div class="flex justify-between text-xs font-mono text-slate-400">
                        <span>Initial: <span id="label-init-comb" class="text-slate-200 font-bold">₹0.00</span></span>
                        <span>Current: <span id="label-curr-comb" class="text-emerald-400 font-bold">₹0.00</span></span>
                    </div>
                    <div class="w-full bg-slate-800 h-3 rounded-full overflow-hidden border border-slate-700/80">
                        <div id="decay-progress-bar" class="bg-gradient-to-r from-teal-500 to-emerald-400 h-full transition-all duration-300" style="width: 0%;"></div>
                    </div>
                    <p class="text-[10px] text-slate-400 text-center">
                        <span id="label-profit-captured">0%</span> of maximum strategy profit captured.
                    </p>
                </div>

                {{-- Greeks Card Grid --}}
                <div class="space-y-2">
                    <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Live Greek Exposure</div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-2.5">
                            <span class="text-[10px] text-slate-400">Net Delta:</span>
                            <div class="text-sm font-bold font-mono text-cyan-400" id="greek-net-delta">0.00</div>
                        </div>
                        <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-2.5">
                            <span class="text-[10px] text-slate-400">Net Theta:</span>
                            <div class="text-sm font-bold font-mono text-teal-400" id="greek-net-theta">0.00</div>
                        </div>
                        <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-2.5">
                            <span class="text-[10px] text-slate-400">Net Vega:</span>
                            <div class="text-sm font-bold font-mono text-purple-400" id="greek-net-vega">0.00</div>
                        </div>
                        <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-2.5">
                            <span class="text-[10px] text-slate-400">Net Gamma:</span>
                            <div class="text-sm font-bold font-mono text-amber-400" id="greek-net-gamma">0.00</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Psychological Calm Prompt --}}
            <div class="mt-4 p-2.5 bg-emerald-950/30 border border-emerald-500/20 rounded-xl text-xs text-emerald-300/90 flex items-start gap-2">
                <span class="text-base">🧘</span>
                <p class="leading-relaxed">
                    <strong>Rule of Calm:</strong> Intraday swings are natural. As long as Spot stays between Min and Max, time decay is continuously adding to your profit.
                </p>
            </div>
        </div>

    </div>

    {{-- ════════════════════════ 4. INTELLIGENT SHIFT & RECOVERY ADVISOR (THE OPPOSITE MOVE DEFENSE) ════════════════════════ --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-2xl">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <div class="flex items-center gap-2">
                <span class="text-xl">🔄</span>
                <div>
                    <h3 class="text-sm sm:text-base font-extrabold text-white tracking-wide uppercase">
                        Intelligent Strike Shifting & Recovery Playbook
                    </h3>
                    <p class="text-xs text-slate-400">
                        When the market moves against one leg, shift the decayed untested leg to collect fresh credit and expand your breakevens.
                    </p>
                </div>
            </div>
            
            <div id="shift-trigger-indicator" class="px-3 py-1 rounded-xl text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 flex items-center gap-1.5">
                <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                <span>No Adjustment Needed (Ratio Balanced)</span>
            </div>
        </div>

        {{-- Dynamic Suggested Shift Widget --}}
        <div id="suggested-shift-container" class="bg-slate-950/90 border border-slate-800 rounded-xl p-4 grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
            
            {{-- Col 1: Diagnosis of Imbalance --}}
            <div class="space-y-1.5">
                <div class="flex items-center justify-between text-[11px] font-semibold text-slate-400 uppercase tracking-wide">
                    <span>Imbalance Diagnostic</span>
                    <span id="diag-urgency-badge" class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-400">Normal</span>
                </div>
                <div class="text-sm font-bold text-slate-200" id="diag-status-text">Positions are well balanced</div>
                <p class="text-xs text-slate-400" id="diag-details-text">
                    Current Leg Ratio: <span class="font-mono text-white font-bold" id="diag-leg-ratio">1.0 : 1.0</span> (Safe threshold &lt; 1.6:1)
                </p>
                <div class="text-[11px] font-mono text-cyan-400" id="diag-delta-text">
                    Net Delta: 0.00 (Neutral)
                </div>
            </div>

            {{-- Col 2: Actionable Shift Suggestion --}}
            <div class="space-y-1.5 border-t lg:border-t-0 lg:border-l lg:border-r border-slate-800/80 lg:px-4">
                <div class="text-[11px] font-semibold text-amber-400 uppercase tracking-wide flex items-center gap-1">
                    <span>💡 Recommended Loss Defense</span>
                </div>
                <div class="text-xs font-medium text-slate-200 leading-relaxed" id="shift-recommendation-text">
                    Hold position. Theta decay working smoothly.
                </div>
                <div class="text-[11px] text-emerald-400 font-mono font-bold" id="shift-benefit-preview">
                    --
                </div>
            </div>

            {{-- Col 3: Execute Shift Action --}}
            <div class="flex flex-col gap-2">
                {{-- Action 1: 1-Click Roll Untested Leg --}}
                <button type="button" id="btn-preview-shift" disabled class="bg-slate-800 text-slate-500 font-bold px-3.5 py-2 rounded-xl text-xs transition shadow flex items-center justify-center gap-2 cursor-not-allowed">
                    <span>🔄 1-Click Roll Untested Leg</span>
                </button>

                {{-- Action 2: 1-Click Buy Protection Wing --}}
                <button type="button" id="btn-buy-hedge-wing" disabled class="bg-slate-800 text-slate-500 font-bold px-3.5 py-2 rounded-xl text-xs transition shadow flex items-center justify-center gap-2 cursor-not-allowed opacity-50">
                    <span>🛡️ 1-Click Buy Protection Wing</span>
                </button>

                {{-- Action 3: Deep Defense / Full Iron Fly --}}
                <div class="flex items-center justify-between text-[11px] text-slate-400 px-1 pt-1 border-t border-slate-800/60">
                    <span>Deep Defense:</span>
                    <button type="button" id="btn-convert-ironfly" class="text-indigo-400 hover:text-indigo-300 font-bold transition underline">
                        + Buy Both Wings (Iron Fly)
                    </button>
                </div>
            </div>

        </div>
    </div>

    {{-- ════════════════════════ 5. LIVE PAYOFF DIAGRAM & SCENARIO SLIDER ════════════════════════ --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-2xl space-y-3.5">
        {{-- Section Header & Scenario Simulator --}}
        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-800">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xl">📈</span>
                    <h3 class="text-sm sm:text-base font-extrabold text-white tracking-wide uppercase">
                        Live Payoff Risk Graph & Recovery Simulator
                    </h3>
                    <span id="payoff-strategy-type" class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/40">
                        Active Basket
                    </span>
                </div>
                <p class="text-xs text-slate-400 mt-0.5">
                    Visual expiry profit & loss profile calculated across spot prices for your active basket. Drag the simulator slider to stress-test market movements.
                </p>
            </div>

            {{-- Scenario Simulator Spot Slider --}}
            <div class="flex flex-wrap items-center gap-2 bg-slate-800/90 border border-slate-700 rounded-xl px-3 py-1.5 text-xs shadow-inner">
                <span class="text-slate-400 font-semibold flex items-center gap-1">
                    <span>🎯</span> Simulate Spot Move:
                </span>
                <input type="range" id="sim-spot-slider" min="{{ $atmStrike - 800 }}" max="{{ $atmStrike + 800 }}" step="25" value="{{ $indexSpot }}" class="w-28 sm:w-36 accent-emerald-500 cursor-pointer">
                <span id="sim-spot-label" class="font-mono font-extrabold text-cyan-300 bg-slate-900 px-2 py-0.5 rounded border border-slate-700 text-xs">{{ number_format($indexSpot, 0) }}</span>
                <span id="sim-pnl-label" class="font-mono font-extrabold px-2 py-0.5 rounded text-xs bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">P&L: ₹0</span>
                <div class="flex items-center gap-1 border-l border-slate-700 pl-2">
                    <button type="button" id="btn-sim-minus-1" class="text-[10px] bg-slate-700 hover:bg-slate-650 text-slate-300 px-1.5 py-0.5 rounded font-bold transition cursor-pointer" title="Simulate -1% Market Drop">-1%</button>
                    <button type="button" id="btn-sim-plus-1" class="text-[10px] bg-slate-700 hover:bg-slate-650 text-slate-300 px-1.5 py-0.5 rounded font-bold transition cursor-pointer" title="Simulate +1% Market Rally">+1%</button>
                    <button type="button" id="btn-reset-sim" class="text-[10px] text-slate-400 hover:text-white underline ml-1 cursor-pointer">Reset</button>
                </div>
            </div>
        </div>

        {{-- Active Basket Strategy Legs Strip --}}
        <div id="payoff-legs-container" class="flex flex-wrap items-center gap-2 bg-slate-950/70 border border-slate-800/80 rounded-xl p-2.5">
            <span class="text-xs font-bold text-slate-400 flex items-center gap-1.5 flex-shrink-0">
                <span>🧺</span> Active Strategy Legs:
            </span>
            <div id="payoff-legs-list" class="flex flex-wrap items-center gap-1.5 flex-1">
                {{-- Dynamically populated with active leg badges --}}
            </div>
        </div>

        {{-- Strategy Risk & Payoff KPI Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-3 text-xs">
            {{-- Max Profit --}}
            <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-2.5">
                <div class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider flex items-center justify-between">
                    <span>Max Profit</span>
                    <span class="text-emerald-400">▲</span>
                </div>
                <div id="payoff-max-profit" class="text-base sm:text-lg font-black font-mono text-emerald-400 mt-0.5">
                    +₹0
                </div>
                <div id="payoff-max-profit-sub" class="text-[10px] text-slate-400 mt-0.5">
                    Combined net credit
                </div>
            </div>

            {{-- Max Loss --}}
            <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-2.5">
                <div class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider flex items-center justify-between">
                    <span>Max Loss (Tail Risk)</span>
                    <span class="text-rose-400">▼</span>
                </div>
                <div id="payoff-max-loss" class="text-base sm:text-lg font-black font-mono text-rose-400 mt-0.5">
                    Uncapped Loss ⚠️
                </div>
                <div id="payoff-max-loss-sub" class="text-[10px] text-slate-400 mt-0.5">
                    Naked short legs active
                </div>
            </div>

            {{-- Breakeven Range --}}
            <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-2.5">
                <div class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider flex items-center justify-between">
                    <span>Breakeven Range</span>
                    <span class="text-cyan-400">↔</span>
                </div>
                <div id="payoff-be-range" class="text-xs sm:text-sm font-extrabold font-mono text-cyan-300 mt-0.5 truncate">
                    0 - 0
                </div>
                <div id="payoff-be-sub" class="text-[10px] text-slate-400 mt-0.5">
                    Safety Buffer: ±0 pts
                </div>
            </div>

            {{-- Expiry P&L at Current Spot --}}
            <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-2.5">
                <div class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider flex items-center justify-between">
                    <span>At Spot Expiry</span>
                    <span class="text-amber-400">📍</span>
                </div>
                <div id="payoff-current-spot-pnl" class="text-base sm:text-lg font-black font-mono text-emerald-300 mt-0.5">
                    +₹0
                </div>
                <div id="payoff-current-spot-sub" class="text-[10px] text-slate-400 mt-0.5">
                    At spot {{ number_format($indexSpot, 0) }}
                </div>
            </div>
        </div>

        {{-- Payoff Empty State (shown when activeBasket has 0 legs) --}}
        <div id="payoff-empty-state" class="hidden text-center py-10 px-4 bg-slate-950/50 border border-dashed border-slate-800 rounded-xl">
            <span class="text-3xl">📊</span>
            <h4 class="text-sm font-bold text-white mt-2">Active Basket is Empty</h4>
            <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                No active strategy legs found. Deploy a pair from the Intelligent Strike Scanner below or click "+ Add Leg" to generate the live payoff graph.
            </p>
        </div>

        {{-- Payoff Canvas Container --}}
        <div id="payoff-canvas-container" class="w-full relative" style="position: relative; height: 320px;">
            <canvas id="payoffChart"></canvas>
        </div>
    </div>

    {{-- ════════════════════════ 6. INTELLIGENT STRIKE SCANNER & 1-CLICK DEPLOY ════════════════════════ --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-2xl">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xl">🎯</span>
                    <h3 class="text-sm sm:text-base font-extrabold text-white tracking-wide uppercase">
                        Strike Scanner & 1-Click Strategy Deployer
                    </h3>
                </div>
                <p class="text-xs text-slate-400">
                    Instantly view pre-calculated delta-neutral strike pairs with Open Interest (OI) changes, Volume, and Safety Margins.
                </p>
            </div>

            {{-- Preset Filter Tabs --}}
            <div class="flex items-center gap-1.5 bg-slate-800/90 p-1 rounded-xl border border-slate-700 text-xs">
                <button type="button" class="preset-tab active px-3 py-1 rounded-lg font-bold transition bg-emerald-600 text-white" data-preset="ALL">
                    All Pairs
                </button>
                <button type="button" class="preset-tab px-3 py-1 rounded-lg font-bold transition text-slate-400 hover:text-white" data-preset="ATM">
                    ATM Straddle
                </button>
                <button type="button" class="preset-tab px-3 py-1 rounded-lg font-bold transition text-slate-400 hover:text-white" data-preset="SAFE">
                    Safe Strangle (15Δ)
                </button>
                <button type="button" class="preset-tab px-3 py-1 rounded-lg font-bold transition text-slate-400 hover:text-white" data-preset="EQUAL_PREMIUM">
                    Equal Premium
                </button>
            </div>
        </div>

        {{-- Scanner Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-slate-950/80 text-slate-400 border-b border-slate-800 text-left font-semibold">
                        <th class="py-2.5 px-3">Strategy Name</th>
                        <th class="py-2.5 px-2.5 text-center text-rose-400 font-bold">PE Strike & LTP</th>
                        <th class="py-2.5 px-2.5 text-center text-green-400 font-bold">CE Strike & LTP</th>
                        <th class="py-2.5 px-2.5 text-right font-mono text-cyan-300">Combined Credit</th>
                        <th class="py-2.5 px-2.5 text-center font-mono">Breakeven Range</th>
                        <th class="py-2.5 px-2.5 text-right font-mono text-emerald-400">Safety Buffer</th>
                        <th class="py-2.5 px-2.5 text-center">PE OI & Chg%</th>
                        <th class="py-2.5 px-2.5 text-center">CE OI & Chg%</th>
                        <th class="py-2.5 px-3 text-center">Deploy</th>
                    </tr>
                </thead>
                <tbody id="scanner-table-body" class="divide-y divide-slate-800/60 font-mono">
                    @forelse($scannerPairs as $pair)
                        <tr class="hover:bg-slate-800/50 transition scanner-row" data-preset="{{ $pair['preset'] }}">
                            <td class="py-2.5 px-3 font-sans">
                                <div class="font-bold text-white text-xs">{{ $pair['name'] }}</div>
                                <span class="text-[10px] text-slate-400">{{ $pair['tag'] }}</span>
                            </td>
                            <td class="py-2.5 px-2.5 text-center">
                                <span class="font-bold text-rose-300">{{ $pair['pe_strike'] }} PE</span>
                                <span class="text-slate-400 ml-1">@ ₹{{ number_format($pair['pe_price'], 2) }}</span>
                            </td>
                            <td class="py-2.5 px-2.5 text-center">
                                <span class="font-bold text-emerald-300">{{ $pair['ce_strike'] }} CE</span>
                                <span class="text-slate-400 ml-1">@ ₹{{ number_format($pair['ce_price'], 2) }}</span>
                            </td>
                            <td class="py-2.5 px-2.5 text-right font-bold text-cyan-300">
                                ₹{{ number_format($pair['combined_credit'], 2) }}
                            </td>
                            <td class="py-2.5 px-2.5 text-center font-bold text-slate-300">
                                {{ $pair['lower_breakeven'] }} - {{ $pair['upper_breakeven'] }}
                            </td>
                            <td class="py-2.5 px-2.5 text-right font-bold text-emerald-400">
                                ±{{ $pair['safety_pts'] }} pts <span class="text-[10px] text-slate-400">({{ $pair['safety_pct'] }}%)</span>
                            </td>
                            <td class="py-2.5 px-2.5 text-center">
                                <span class="text-[11px] font-bold {{ $pair['pe_oi_change'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $pair['pe_oi_change'] >= 0 ? '+' : '' }}{{ $pair['pe_oi_change'] }}%
                                </span>
                                <div class="text-[9px] text-slate-400">{{ $pair['pe_buildup'] ?? 'Neutral' }}</div>
                            </td>
                            <td class="py-2.5 px-2.5 text-center">
                                <span class="text-[11px] font-bold {{ $pair['ce_oi_change'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $pair['ce_oi_change'] >= 0 ? '+' : '' }}{{ $pair['ce_oi_change'] }}%
                                </span>
                                <div class="text-[9px] text-slate-400">{{ $pair['ce_buildup'] ?? 'Neutral' }}</div>
                            </td>
                            <td class="py-2.5 px-3 text-center">
                                <button type="button" 
                                    class="btn-deploy-pair bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-3 py-1 rounded-lg text-[11px] transition shadow"
                                    data-pair='@json($pair)'>
                                    + Deploy Basket
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-6 text-center text-slate-500 font-sans">
                                No strike pairs found for the selected range.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ════════════════════════ 7. OPTION CHAIN ADD LEG MODAL ════════════════════════ --}}
    <div id="modal-add-leg" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md hidden flex items-center justify-center p-2 sm:p-4">
        <div class="bg-slate-900 border border-slate-700/80 rounded-2xl w-full max-w-5xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden">
            
            {{-- Modal Header --}}
            <div class="p-3.5 sm:p-4 border-b border-slate-800 bg-slate-950/70 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-xl">📋</span>
                    <div>
                        <h3 class="text-sm sm:text-base font-bold text-white flex items-center gap-2">
                            Option Chain Strike Selector
                        </h3>
                        <p class="text-[11px] text-slate-400">
                            Select strikes directly from the live option chain. Recommended strikes are highlighted based on delta neutrality.
                        </p>
                    </div>
                </div>

                {{-- Action (SELL/BUY) & Global Lots --}}
                <div class="flex items-center gap-2.5">
                    <div class="inline-flex items-center bg-slate-800 p-0.5 rounded-xl border border-slate-700 text-xs">
                        <button type="button" id="oc-action-sell" class="px-3 py-1 rounded-lg font-bold bg-rose-600 text-white shadow cursor-pointer">
                            SELL (Short Writing)
                        </button>
                        <button type="button" id="oc-action-buy" class="px-3 py-1 rounded-lg font-bold text-slate-400 hover:text-white cursor-pointer">
                            BUY (Hedge Wing)
                        </button>
                    </div>

                    <div class="inline-flex items-center bg-slate-800 px-2 py-1 rounded-xl border border-slate-700 text-xs font-mono">
                        <span class="text-slate-400 mr-1.5 text-[11px]">Lots:</span>
                        <input type="number" id="oc-default-qty" value="{{ $lotSize }}" step="{{ $lotSize }}" min="{{ $lotSize }}" class="w-14 bg-slate-900 border border-slate-700 rounded px-1.5 py-0.5 text-center text-emerald-400 font-bold focus:outline-none">
                    </div>

                    <button type="button" id="btn-close-add-modal" class="text-slate-400 hover:text-white text-lg font-bold p-1 ml-1 cursor-pointer">✕</button>
                </div>
            </div>

            {{-- Market Recommendations Ribbon --}}
            <div class="bg-slate-950/90 border-b border-slate-800 px-4 py-2 flex flex-wrap items-center justify-between gap-2 text-xs">
                <div class="flex items-center gap-1.5 text-slate-300 font-semibold">
                    <span>💡 Market Suggestions:</span>
                </div>
                <div class="flex items-center flex-wrap gap-2" id="oc-recommendations-bar">
                    {{-- Dynamically populated with Quick 1-Click buttons --}}
                </div>
            </div>

            {{-- Option Chain Table Container --}}
            <div class="overflow-y-auto flex-1 overflow-x-auto p-1 sm:p-2 bg-slate-900/60">
                <table class="w-full text-xs font-mono border-collapse min-w-[760px]">
                    <thead class="sticky top-0 bg-slate-950 text-slate-400 border-b border-slate-800 z-10 font-sans uppercase text-[10px] tracking-wider">
                        <tr>
                            <th class="py-2 px-2 text-center text-emerald-400 bg-emerald-950/30">Add</th>
                            <th class="py-2 px-2 text-center text-slate-400 bg-emerald-950/20">Lots</th>
                            <th class="py-2 px-2 text-center text-slate-300 bg-emerald-950/20">Delta</th>
                            <th class="py-2 px-3 text-right text-emerald-300 bg-emerald-950/20">CE Price</th>
                            <th class="py-2 px-4 text-center text-white bg-slate-900 font-black border-x border-slate-800">Strike</th>
                            <th class="py-2 px-3 text-left text-rose-300 bg-rose-950/20">PE Price</th>
                            <th class="py-2 px-2 text-center text-slate-300 bg-rose-950/20">Delta</th>
                            <th class="py-2 px-2 text-center text-slate-400 bg-rose-950/20">Lots</th>
                            <th class="py-2 px-2 text-center text-rose-400 bg-rose-950/30">Add</th>
                        </tr>
                    </thead>
                    <tbody id="oc-table-body" class="divide-y divide-slate-800/60 text-xs">
                        {{-- Populated dynamically with strikes --}}
                    </tbody>
                </table>
            </div>

            {{-- Modal Footer --}}
            <div class="p-3 border-t border-slate-800 bg-slate-950/80 flex items-center justify-between text-xs text-slate-400">
                <div class="flex items-center gap-3">
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-400"></span> <strong>15Δ</strong>: High Safety Strangle</span>
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-cyan-400"></span> <strong>25Δ</strong>: Balanced Theta</span>
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-400"></span> <strong>ATM</strong>: Maximum Decay</span>
                </div>
                <button type="button" id="btn-done-add-modal" class="bg-slate-800 hover:bg-slate-700 text-white font-bold px-4 py-1.5 rounded-xl border border-slate-700 transition cursor-pointer">
                    Done
                </button>
            </div>

        </div>
    </div>

    {{-- ════════════════════════ 8. CLEAR BASKET CONFIRMATION MODAL ════════════════════════ --}}
    <div id="modal-clear-basket" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-sm p-5 shadow-2xl space-y-4 text-center">
            <div class="w-12 h-12 rounded-full bg-rose-500/20 text-rose-400 flex items-center justify-center mx-auto text-2xl border border-rose-500/30">
                🗑️
            </div>
            <div>
                <h3 class="text-base font-bold text-white">Clear Active Strategy Basket?</h3>
                <p class="text-xs text-slate-400 mt-1">
                    This will remove all active option legs from your basket. You can deploy a fresh strategy anytime.
                </p>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button type="button" id="btn-cancel-clear-basket" class="w-1/2 py-2 rounded-xl text-xs font-semibold bg-slate-800 hover:bg-slate-750 text-slate-300 border border-slate-700 transition cursor-pointer">
                    Cancel
                </button>
                <button type="button" id="btn-confirm-clear-basket" class="w-1/2 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-500 text-white transition shadow-lg cursor-pointer">
                    Yes, Clear Basket
                </button>
            </div>
        </div>
    </div>

    {{-- ════════════════════════ 9. FLOATING TOAST NOTIFICATIONS ════════════════════════ --}}
    <div id="toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 pointer-events-none"></div>

</div>

{{-- ════════════════════════ JAVASCRIPT ENGINE: CHART.JS, PROTOBUF, WEBSOCKET ════════════════════════ --}}
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/protobufjs@7.2.5/dist/protobuf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
(function () {
    // Initial State from Server
    let state = {
        symbol: @json($symbol),
        lotSize: {{ (int) $lotSize }},
        selectedExpiry: @json($selectedExpiry),
        indexSpot: {{ (float) $indexSpot }},
        indexOpen: {{ (float) $indexOpen }},
        indexKey: @json($indexKey),
        atmStrike: {{ (int) $atmStrike }},
        strikeStep: {{ (int) $strikeStep }},
        range: {{ (int) $range }},
        vix: @json($vix),
        vix1DayMove: {{ (float) $vix1DayMove }},
        strikesData: @json($strikesData),
        scannerPairs: @json($scannerPairs),
        allInstrumentKeys: @json($allInstrumentKeys)
    };

    // Live Prices Map (instrument_key => last_price)
    let livePrices = {};
    let liveSpot = state.indexSpot;
    let liveVix = state.vix.value || 13.37;
    let liveVix1Day = state.vix1DayMove;
    let ws = null;
    let protobufRoot = null;
    let tickCount = 0;
    let isConnecting = false;
    let payoffChartInstance = null;

    // Active Basket Storage (Saved in localStorage scoped by Symbol)
    let activeBasket = loadActiveBasket();

    // Map initial prices from fallback
    state.strikesData.forEach(item => {
        if (item.ce && item.ce.instrument_key && item.ce.ltp > 0) {
            livePrices[item.ce.instrument_key] = item.ce.ltp;
        }
        if (item.pe && item.pe.instrument_key && item.pe.ltp > 0) {
            livePrices[item.pe.instrument_key] = item.pe.ltp;
        }
    });

    // Default deploy first pair (e.g. ATM or Strangle) if basket is completely empty
    if (activeBasket.length === 0 && state.scannerPairs.length > 0) {
        deployPair(state.scannerPairs[0]);
    }

    // ─────────────────────────────────────────────────────────────
    // 0. TOAST NOTIFICATION ENGINE
    // ─────────────────────────────────────────────────────────────
    function showToast(title, message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        const bgClass = type === 'success' 
            ? 'bg-slate-900/95 border-emerald-500/60 text-emerald-200' 
            : 'bg-slate-900/95 border-slate-700 text-white';
        const icon = type === 'success' ? '✓' : 'ℹ️';

        toast.className = `${bgClass} border rounded-2xl p-3.5 shadow-2xl backdrop-blur-md pointer-events-auto flex items-start gap-2.5 max-w-sm transition-all duration-300 transform translate-y-3 opacity-0 font-sans text-xs`;
        toast.innerHTML = `
            <div class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center font-bold flex-shrink-0 text-xs">
                ${icon}
            </div>
            <div class="flex-1">
                <div class="font-bold text-white text-xs tracking-tight">${title}</div>
                <div class="text-[11px] text-slate-300 mt-0.5 leading-snug whitespace-pre-line">${message}</div>
            </div>
            <button type="button" class="text-slate-400 hover:text-white font-bold ml-1 text-sm leading-none cursor-pointer">✕</button>
        `;

        toast.querySelector('button').onclick = () => {
            toast.remove();
        };

        container.appendChild(toast);
        setTimeout(() => {
            toast.classList.remove('translate-y-3', 'opacity-0');
        }, 10);

        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-3');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // ─────────────────────────────────────────────────────────────
    // 1. BASKET STORAGE & MANAGEMENT
    // ─────────────────────────────────────────────────────────────
    function getStorageKey() {
        return `delta_neutral_basket_${state.symbol}`;
    }

    function loadActiveBasket() {
        try {
            const raw = localStorage.getItem(getStorageKey());
            const basket = raw ? JSON.parse(raw) : [];
            // Automatically upgrade old default qty 25 to 65 for NIFTY
            if (state.symbol === 'NIFTY' && state.lotSize === 65) {
                let updated = false;
                basket.forEach(l => {
                    if (l.qty === 25) {
                        l.qty = 65;
                        updated = true;
                    }
                });
                if (updated) {
                    localStorage.setItem(getStorageKey(), JSON.stringify(basket));
                }
            }
            return basket;
        } catch (e) {
            return [];
        }
    }

    function saveActiveBasket(shouldRenderTable = true) {
        try {
            localStorage.setItem(getStorageKey(), JSON.stringify(activeBasket));
        } catch (e) {
            console.error('Error saving basket:', e);
        }
        if (shouldRenderTable) {
            renderBasketTable();
        } else {
            updateBasketLiveOnly();
        }
        updateCorridorAndRecovery();
        renderPayoffChart();
    }

    function deployPair(pair) {
        // Deploy CE and PE short legs
        const ceLeg = {
            id: 'CE_' + pair.ce_strike + '_' + Date.now(),
            action: 'SELL',
            strike: pair.ce_strike,
            type: 'CE',
            qty: state.lotSize,
            entryPrice: pair.ce_price > 0 ? pair.ce_price : (livePrices[pair.ce_key] || 50),
            instrumentKey: pair.ce_key,
            oiChange: pair.ce_oi_change || 0,
            buildup: pair.ce_buildup || 'Neutral'
        };

        const peLeg = {
            id: 'PE_' + pair.pe_strike + '_' + (Date.now() + 1),
            action: 'SELL',
            strike: pair.pe_strike,
            type: 'PE',
            qty: state.lotSize,
            entryPrice: pair.pe_price > 0 ? pair.pe_price : (livePrices[pair.pe_key] || 50),
            instrumentKey: pair.pe_key,
            oiChange: pair.pe_oi_change || 0,
            buildup: pair.pe_buildup || 'Neutral'
        };

        activeBasket = [ceLeg, peLeg];
        saveActiveBasket();
    }

    // ─────────────────────────────────────────────────────────────
    // 2. RENDER BASKET TABLE & CALCULATIONS
    // ─────────────────────────────────────────────────────────────
    function renderBasketTable() {
        const tbody = document.getElementById('basket-table-body');
        document.getElementById('basket-legs-count').textContent = `${activeBasket.length} Legs`;

        if (!activeBasket || activeBasket.length === 0) {
            tbody.innerHTML = `<tr><td colspan="10" class="py-8 text-center text-slate-500 font-sans">No active legs in basket. Select a preset below or click "Add Leg".</td></tr>`;
            document.getElementById('basket-total-credit').textContent = '₹0.00';
            document.getElementById('basket-current-value').textContent = '₹0.00';
            document.getElementById('basket-live-pnl').textContent = '₹0.00';
            document.getElementById('basket-total-theta').textContent = '+₹0.00/day';
            renderPayoffChart();
            return;
        }

        let rowsHtml = '';
        activeBasket.forEach((leg, index) => {
            const currentLtp = livePrices[leg.instrumentKey] !== undefined ? livePrices[leg.instrumentKey] : leg.entryPrice;
            const legPnl = leg.action === 'SELL' ? (leg.entryPrice - currentLtp) * leg.qty : (currentLtp - leg.entryPrice) * leg.qty;

            const isPnlPositive = legPnl >= 0;
            const pnlColor = isPnlPositive ? 'text-emerald-400' : 'text-rose-400';
            const actionBg = leg.action === 'SELL' ? 'bg-rose-500/20 text-rose-300 border-rose-500/30' : 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30';
            const typeColor = leg.type === 'CE' ? 'text-emerald-400' : 'text-rose-400';

            let statusTag = '';
            let quickActionButton = '';
            if (leg.action === 'SELL') {
                if (legPnl <= -600 || currentLtp >= (leg.entryPrice * 1.30)) {
                    statusTag = '<span class="ml-1 px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-rose-500/20 text-rose-300 border border-rose-500/40 animate-pulse">🚨 TESTED</span>';
                    quickActionButton = `<button type="button" class="btn-row-add-wing text-[10px] bg-cyan-950 hover:bg-cyan-900 border border-cyan-700/60 text-cyan-300 px-1.5 py-0.5 rounded font-bold transition mr-1 cursor-pointer" data-strike="${leg.strike}" data-type="${leg.type}" title="Buy protection wing to cap runaway loss on this leg">+ Wing</button>`;
                } else if (legPnl >= 350 || currentLtp <= (leg.entryPrice * 0.70)) {
                    statusTag = '<span class="ml-1 px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-teal-500/20 text-teal-300 border border-teal-500/40">💎 DECAYED</span>';
                    quickActionButton = `<button type="button" class="btn-row-roll-untested text-[10px] bg-emerald-950 hover:bg-emerald-900 border border-emerald-700/60 text-emerald-300 px-1.5 py-0.5 rounded font-bold transition mr-1 cursor-pointer" data-index="${index}" title="Roll this decayed leg closer to spot to lock in profit and collect fresh credit">Roll Closer</button>`;
                }
            }

            rowsHtml += `
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="py-2 px-2.5">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border ${actionBg}">${leg.action}</span>
                    </td>
                    <td class="py-2 px-2.5 font-bold text-white text-xs">
                        <div class="flex items-center">
                            <span>${leg.strike}</span>
                            <span id="basket-tag-${index}">${statusTag}</span>
                        </div>
                    </td>
                    <td class="py-2 px-2.5 font-bold ${typeColor} text-xs">${leg.type}</td>
                    <td class="py-2 px-2 text-right">
                        <input type="number" 
                            class="basket-qty-input w-16 bg-slate-950 border border-slate-700/80 hover:border-slate-500 rounded px-1.5 py-0.5 text-right text-slate-200 font-mono font-bold focus:ring-1 focus:ring-emerald-500 focus:outline-none transition text-xs" 
                            data-index="${index}" 
                            value="${leg.qty}" 
                            step="${state.lotSize}" 
                            min="${state.lotSize}"
                            title="Edit Quantity (Shares)">
                    </td>
                    <td class="py-2 px-2 text-right">
                        <div class="inline-flex items-center gap-0.5 bg-slate-950 border border-slate-700/80 hover:border-emerald-500/60 rounded px-1.5 py-0.5 focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500 transition" title="Click to edit entry price">
                            <span class="text-slate-500 font-mono text-[11px]">₹</span>
                            <input type="number" 
                                class="basket-entry-input w-20 bg-transparent text-right text-emerald-400 font-mono font-bold focus:outline-none text-xs" 
                                data-index="${index}" 
                                value="${leg.entryPrice.toFixed(2)}" 
                                step="0.05" 
                                min="0.05">
                        </div>
                    </td>
                    <td class="py-2 px-2.5 text-right font-bold text-cyan-300 font-mono">
                        <span id="basket-ltp-${index}">₹${currentLtp.toFixed(2)}</span>
                    </td>
                    <td class="py-2 px-2.5 text-right font-extrabold font-mono">
                        <span id="basket-pnl-${index}" class="${pnlColor}">
                            ${isPnlPositive ? '+' : ''}₹${legPnl.toFixed(2)}
                        </span>
                    </td>
                    <td class="py-2 px-2.5 text-right">
                        <span class="font-bold ${leg.oiChange >= 0 ? 'text-emerald-400' : 'text-rose-400'}">
                            ${leg.oiChange >= 0 ? '+' : ''}${leg.oiChange}%
                        </span>
                    </td>
                    <td class="py-2 px-2.5 text-center">
                        <span class="text-[10px] text-slate-400 font-sans">${leg.buildup || 'Neutral'}</span>
                    </td>
                    <td class="py-2 px-2.5 text-center">
                        <div class="flex items-center justify-center">
                            <span id="basket-action-btn-${index}">${quickActionButton}</span>
                            <button type="button" class="btn-delete-leg text-slate-500 hover:text-rose-400 font-bold transition p-1 cursor-pointer" data-index="${index}" title="Remove Leg">
                                ✕
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = rowsHtml;
        updateBasketLiveOnly();
        renderPayoffChart();
    }

    function updateBasketLiveOnly() {
        if (!activeBasket || activeBasket.length === 0) return;

        let totalCredit = 0;
        let currentValue = 0;
        let totalPnl = 0;
        let totalTheta = 0;
        let totalNetDelta = 0;

        activeBasket.forEach((leg, index) => {
            const currentLtp = livePrices[leg.instrumentKey] !== undefined ? livePrices[leg.instrumentKey] : leg.entryPrice;
            const legCredit = leg.entryPrice * leg.qty;
            const legCurrVal = currentLtp * leg.qty;

            // For SELL legs: Profit = (Entry - Current) * Qty
            // For BUY legs:  Profit = (Current - Entry) * Qty
            const legPnl = leg.action === 'SELL' ? (leg.entryPrice - currentLtp) * leg.qty : (currentLtp - leg.entryPrice) * leg.qty;

            totalCredit += (leg.action === 'SELL' ? legCredit : -legCredit);
            currentValue += legCurrVal;
            totalPnl += legPnl;

            // Dynamic Delta calculation per leg
            let legDelta = 0;
            const strikeObj = state.strikesData ? state.strikesData.find(s => s.strike === leg.strike) : null;
            if (strikeObj) {
                if (leg.type === 'CE' && strikeObj.ce && strikeObj.ce.delta) {
                    legDelta = Math.abs(strikeObj.ce.delta);
                } else if (leg.type === 'PE' && strikeObj.pe && strikeObj.pe.delta) {
                    legDelta = -Math.abs(strikeObj.pe.delta);
                }
            }
            if (legDelta === 0) {
                const diff = (liveSpot - leg.strike);
                if (leg.type === 'CE') {
                    legDelta = 1 / (1 + Math.exp(-diff / 140));
                } else {
                    legDelta = -(1 / (1 + Math.exp(diff / 140)));
                }
            }

            const positionDelta = (leg.action === 'SELL' ? -legDelta : legDelta) * (leg.qty / state.lotSize);
            totalNetDelta += positionDelta;
            totalTheta += (currentLtp * 0.12 * leg.qty);

            const isPnlPositive = legPnl >= 0;
            const ltpEl = document.getElementById(`basket-ltp-${index}`);
            if (ltpEl) {
                ltpEl.textContent = `₹${currentLtp.toFixed(2)}`;
            }

            const pnlEl = document.getElementById(`basket-pnl-${index}`);
            if (pnlEl) {
                pnlEl.textContent = `${isPnlPositive ? '+' : ''}₹${legPnl.toFixed(2)}`;
                pnlEl.className = isPnlPositive ? 'text-emerald-400' : 'text-rose-400';
            }

            // Update live tag and action button dynamically without re-rendering table
            const tagEl = document.getElementById(`basket-tag-${index}`);
            const actionEl = document.getElementById(`basket-action-btn-${index}`);
            if (leg.action === 'SELL') {
                if (legPnl <= -600 || currentLtp >= (leg.entryPrice * 1.30)) {
                    if (tagEl && !tagEl.innerHTML.includes('TESTED')) {
                        tagEl.innerHTML = '<span class="ml-1 px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-rose-500/20 text-rose-300 border border-rose-500/40 animate-pulse">🚨 TESTED</span>';
                    }
                    if (actionEl && !actionEl.innerHTML.includes('btn-row-add-wing')) {
                        actionEl.innerHTML = `<button type="button" class="btn-row-add-wing text-[10px] bg-cyan-950 hover:bg-cyan-900 border border-cyan-700/60 text-cyan-300 px-1.5 py-0.5 rounded font-bold transition mr-1 cursor-pointer" data-strike="${leg.strike}" data-type="${leg.type}" title="Buy protection wing to cap runaway loss on this leg">+ Wing</button>`;
                    }
                } else if (legPnl >= 350 || currentLtp <= (leg.entryPrice * 0.70)) {
                    if (tagEl && !tagEl.innerHTML.includes('DECAYED')) {
                        tagEl.innerHTML = '<span class="ml-1 px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-teal-500/20 text-teal-300 border border-teal-500/40">💎 DECAYED</span>';
                    }
                    if (actionEl && !actionEl.innerHTML.includes('btn-row-roll-untested')) {
                        actionEl.innerHTML = `<button type="button" class="btn-row-roll-untested text-[10px] bg-emerald-950 hover:bg-emerald-900 border border-emerald-700/60 text-emerald-300 px-1.5 py-0.5 rounded font-bold transition mr-1 cursor-pointer" data-index="${index}" title="Roll this decayed leg closer to spot to lock in profit and collect fresh credit">Roll Closer</button>`;
                    }
                } else {
                    if (tagEl && tagEl.innerHTML !== '') tagEl.innerHTML = '';
                    if (actionEl && actionEl.innerHTML !== '') actionEl.innerHTML = '';
                }
            }
        });

        // Update Summary Cards
        const creditEl = document.getElementById('basket-total-credit');
        if (creditEl) creditEl.textContent = `₹${totalCredit.toLocaleString('en-IN', { maximumFractionDigits: 2 })}`;

        const valEl = document.getElementById('basket-current-value');
        if (valEl) valEl.textContent = `₹${currentValue.toLocaleString('en-IN', { maximumFractionDigits: 2 })}`;

        const pnlEl = document.getElementById('basket-live-pnl');
        if (pnlEl) {
            pnlEl.textContent = `${totalPnl >= 0 ? '+' : ''}₹${totalPnl.toLocaleString('en-IN', { maximumFractionDigits: 2 })}`;
            pnlEl.className = `text-base font-extrabold font-mono ${totalPnl >= 0 ? 'text-emerald-400' : 'text-rose-400'}`;
        }

        const thetaEl = document.getElementById('basket-total-theta');
        if (thetaEl) thetaEl.textContent = `+₹${Math.round(totalTheta).toLocaleString('en-IN')}/day`;

        // Update Decay Progress
        const initCombined = activeBasket.reduce((sum, leg) => sum + (leg.action === 'SELL' ? leg.entryPrice : -leg.entryPrice), 0);
        const currCombined = activeBasket.reduce((sum, leg) => {
            const ltp = livePrices[leg.instrumentKey] !== undefined ? livePrices[leg.instrumentKey] : leg.entryPrice;
            return sum + (leg.action === 'SELL' ? ltp : -ltp);
        }, 0);

        const initEl = document.getElementById('label-init-comb');
        if (initEl) initEl.textContent = `₹${initCombined.toFixed(2)}`;

        const currEl = document.getElementById('label-curr-comb');
        if (currEl) currEl.textContent = `₹${currCombined.toFixed(2)}`;

        const decayPct = initCombined > 0 ? Math.max(0, Math.min(100, Math.round(((initCombined - currCombined) / initCombined) * 100))) : 0;
        const decayBadge = document.getElementById('decay-pct-badge');
        if (decayBadge) decayBadge.textContent = `${decayPct}% Decayed`;

        const decayBar = document.getElementById('decay-progress-bar');
        if (decayBar) decayBar.style.width = `${decayPct}%`;

        const capEl = document.getElementById('label-profit-captured');
        if (capEl) capEl.textContent = `${decayPct}%`;

        // Update Live Greeks Cards
        const deltaEl = document.getElementById('greek-net-delta');
        if (deltaEl) deltaEl.textContent = `${totalNetDelta >= 0 ? '+' : ''}${totalNetDelta.toFixed(2)}`;

        // Update Top Corridor Net Delta Skew
        const metricDeltaEl = document.getElementById('metric-net-delta');
        if (metricDeltaEl) {
            metricDeltaEl.textContent = `${totalNetDelta >= 0 ? '+' : ''}${totalNetDelta.toFixed(2)}`;
            metricDeltaEl.className = `text-lg font-extrabold font-mono ${Math.abs(totalNetDelta) <= 0.15 ? 'text-cyan-400' : (totalNetDelta > 0 ? 'text-emerald-400' : 'text-rose-400')}`;
        }

        const metricDeltaLabel = document.getElementById('metric-net-delta-label');
        if (metricDeltaLabel) {
            if (Math.abs(totalNetDelta) <= 0.15) {
                metricDeltaLabel.textContent = '(Neutral)';
                metricDeltaLabel.className = 'text-xs text-slate-400 font-semibold';
            } else if (totalNetDelta > 0.15) {
                metricDeltaLabel.textContent = '(Bullish / PE Decayed)';
                metricDeltaLabel.className = 'text-xs text-emerald-400 font-bold';
            } else {
                metricDeltaLabel.textContent = '(Short Call Stress / Rally ⚠️)';
                metricDeltaLabel.className = 'text-xs text-rose-400 font-bold';
            }
        }

        const diagDeltaEl = document.getElementById('diag-delta-text');
        if (diagDeltaEl) {
            diagDeltaEl.textContent = `Net Delta: ${totalNetDelta >= 0 ? '+' : ''}${totalNetDelta.toFixed(2)} (${Math.abs(totalNetDelta) <= 0.15 ? 'Neutral' : (totalNetDelta > 0 ? 'Bullish Skew' : 'Bearish / Call Stress')})`;
            diagDeltaEl.className = `text-[11px] font-mono font-bold ${Math.abs(totalNetDelta) <= 0.15 ? 'text-cyan-400' : (totalNetDelta > 0 ? 'text-emerald-400' : 'text-rose-400')}`;
        }

        const greekThetaEl = document.getElementById('greek-net-theta');
        if (greekThetaEl) greekThetaEl.textContent = `+${Math.round(totalTheta)}`;

        const vegaEl = document.getElementById('greek-net-vega');
        if (vegaEl) vegaEl.textContent = `-${Math.round(totalCredit * 0.08)}`;

        const gammaEl = document.getElementById('greek-net-gamma');
        if (gammaEl) gammaEl.textContent = `-0.0012`;

        updateCorridorAndRecovery();

        // Keep Payoff Risk Graph legs updated with live P&L
        try {
            const payoffLegsList = document.getElementById('payoff-legs-list');
            if (payoffLegsList && activeBasket && activeBasket.length > 0) {
                payoffLegsList.innerHTML = activeBasket.map(leg => {
                    const isSell = leg.action === 'SELL';
                    const isCe = leg.type === 'CE';
                    const entry = parseFloat(leg.entryPrice || 0);
                    const qty = parseInt(leg.qty || state.lotSize, 10);
                    const ltp = livePrices[leg.instrumentKey] !== undefined ? livePrices[leg.instrumentKey] : entry;
                    const pnl = isSell ? (entry - ltp) * qty : (ltp - entry) * qty;
                    return `
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold border ${isSell ? 'bg-rose-500/10 text-rose-300 border-rose-500/30' : 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30'}">
                            <span class="px-1 py-0.2 rounded text-[10px] ${isSell ? 'bg-rose-500/30 text-rose-200' : 'bg-emerald-500/30 text-emerald-200'}">${leg.action}</span>
                            <span class="${isCe ? 'text-emerald-300' : 'text-rose-300'} font-extrabold">${leg.strike} ${leg.type}</span>
                            <span class="text-slate-400 font-sans text-[11px]">(${qty} @ ₹${entry.toFixed(1)})</span>
                            <span class="text-[11px] ${pnl >= 0 ? 'text-emerald-400' : 'text-rose-400'} font-extrabold">${pnl >= 0 ? '+' : ''}₹${Math.round(pnl)}</span>
                        </span>
                    `;
                }).join('');
            }
        } catch (e) {
            console.warn('payoffLegsList update error:', e);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 3. VISUAL HOLDING CORRIDOR & RECOVERY SHIELD CALCULATOR
    // ─────────────────────────────────────────────────────────────
    function updateCorridorAndRecovery() {
        if (!activeBasket || activeBasket.length === 0) return;

        // Calculate Breakevens
        // Lower Breakeven = Min(Sell PE strike) - Combined Premium Collected
        // Upper Breakeven = Max(Sell CE strike) + Combined Premium Collected
        const peLegs = activeBasket.filter(l => l.type === 'PE' && l.action === 'SELL');
        const ceLegs = activeBasket.filter(l => l.type === 'CE' && l.action === 'SELL');

        const combinedCredit = activeBasket.reduce((sum, l) => sum + (l.action === 'SELL' ? l.entryPrice : -l.entryPrice), 0);

        const peStrike = peLegs.length > 0 ? Math.min(...peLegs.map(l => l.strike)) : (state.atmStrike - 200);
        const ceStrike = ceLegs.length > 0 ? Math.max(...ceLegs.map(l => l.strike)) : (state.atmStrike + 200);

        const lowerBe = Math.round(peStrike - combinedCredit);
        const upperBe = Math.round(ceStrike + combinedCredit);

        document.getElementById('label-lower-be').textContent = lowerBe;
        document.getElementById('label-upper-be').textContent = upperBe;
        document.getElementById('label-spot-indicator').textContent = liveSpot.toFixed(1);

        // Distance & Cushion
        const distToLower = Math.round(liveSpot - lowerBe);
        const distToUpper = Math.round(upperBe - liveSpot);
        const pctToLower = ((distToLower / liveSpot) * 100).toFixed(1);
        const pctToUpper = ((distToUpper / liveSpot) * 100).toFixed(1);

        document.getElementById('cushion-downside-pts').textContent = `${distToLower} pts`;
        document.getElementById('cushion-downside-pct').textContent = `(${pctToLower}%)`;
        document.getElementById('cushion-upside-pts').textContent = `${distToUpper} pts`;
        document.getElementById('cushion-upside-pct').textContent = `(${pctToUpper}%)`;

        // VIX Safety Cushion Calculation
        // Ratio = Minimum Cushion / 1-Day VIX Move
        const minCushion = Math.min(distToLower, distToUpper);
        const vixCoveragePct = liveVix1Day > 0 ? Math.round((minCushion / liveVix1Day) * 100) : 100;
        
        const shieldEl = document.getElementById('vix-shield-ratio');
        shieldEl.textContent = `${vixCoveragePct}%`;
        if (vixCoveragePct >= 120) {
            shieldEl.className = 'font-extrabold font-mono text-emerald-400 text-sm';
            document.getElementById('vix-shield-subtext').textContent = '(Exceptional 1D Safety)';
        } else if (vixCoveragePct >= 90) {
            shieldEl.className = 'font-extrabold font-mono text-teal-300 text-sm';
            document.getElementById('vix-shield-subtext').textContent = '(Normal Safety Cushion)';
        } else {
            shieldEl.className = 'font-extrabold font-mono text-amber-400 text-sm';
            document.getElementById('vix-shield-subtext').textContent = '(Approaching VIX Bound)';
        }

        // Position Spot Marker on Corridor Gauge
        // Total Visual Span: [LowerBE - 250, UpperBE + 250]
        const gaugeMin = lowerBe - 200;
        const gaugeMax = upperBe + 200;
        const gaugeSpan = gaugeMax - gaugeMin;

        const leftRedPct = Math.max(5, Math.min(40, ((lowerBe - gaugeMin) / gaugeSpan) * 100));
        const rightRedPct = Math.max(5, Math.min(40, ((gaugeMax - upperBe) / gaugeSpan) * 100));
        const greenPct = 100 - leftRedPct - rightRedPct;

        document.getElementById('corridor-left-red').style.width = `${leftRedPct}%`;
        document.getElementById('corridor-safe-green').style.width = `${greenPct}%`;
        document.getElementById('corridor-right-red').style.width = `${rightRedPct}%`;

        document.getElementById('marker-lower-be').style.left = `${leftRedPct}%`;
        document.getElementById('marker-upper-be').style.left = `${leftRedPct + greenPct}%`;

        const spotPct = Math.max(2, Math.min(98, ((liveSpot - gaugeMin) / gaugeSpan) * 100));
        document.getElementById('marker-spot-pulse').style.left = `${spotPct}%`;

        // Position VIX 1-Day Overlay Band around current Spot
        const vixHalfPts = liveVix1Day;
        const vixLeftBound = liveSpot - vixHalfPts;
        const vixRightBound = liveSpot + vixHalfPts;
        const vixLeftPct = Math.max(2, Math.min(98, ((vixLeftBound - gaugeMin) / gaugeSpan) * 100));
        const vixWidthPct = Math.max(10, Math.min(95, ((vixRightBound - vixLeftBound) / gaugeSpan) * 100));

        const vixBandEl = document.getElementById('vix-band-marker');
        vixBandEl.style.left = `${vixLeftPct}%`;
        vixBandEl.style.width = `${vixWidthPct}%`;
        document.getElementById('vix-band-pts').textContent = Math.round(liveVix1Day);

        // Update Recovery Diagnostic & Imbalance Checker
        checkRecoveryDiagnosis(distToLower, distToUpper);
    }

    // ─────────────────────────────────────────────────────────────
    // 4. SHIFT & RECOVERY ADVISOR (THE ACTIVE LOSS RECOVERY ENGINE)
    // ─────────────────────────────────────────────────────────────
    function checkRecoveryDiagnosis(distToLower = 400, distToUpper = 400) {
        const diagStatus = document.getElementById('diag-status-text');
        const diagRatio = document.getElementById('diag-leg-ratio');
        const diagRec = document.getElementById('shift-recommendation-text');
        const diagBenefit = document.getElementById('shift-benefit-preview');
        const triggerBadge = document.getElementById('shift-trigger-indicator');
        const urgencyBadge = document.getElementById('diag-urgency-badge');
        const btnShift = document.getElementById('btn-preview-shift');
        const btnHedge = document.getElementById('btn-buy-hedge-wing');
        const stratBadge = document.getElementById('strategy-status-badge');

        if (!activeBasket || activeBasket.length === 0) {
            if (diagStatus) diagStatus.textContent = 'Awaiting strategy basket deployment';
            return;
        }

        const shortLegs = activeBasket.filter(l => l.action === 'SELL');
        const shortCeLegs = shortLegs.filter(l => l.type === 'CE');
        const shortPeLegs = shortLegs.filter(l => l.type === 'PE');

        if (shortCeLegs.length === 0 && shortPeLegs.length === 0) {
            if (diagStatus) diagStatus.textContent = 'No short legs active in basket';
            return;
        }

        // Calculate P&L and metrics for every short leg
        let totalPnl = 0;
        let totalCredit = 0;
        let estTheta = 0;
        activeBasket.forEach(leg => {
            const ltp = livePrices[leg.instrumentKey] !== undefined ? livePrices[leg.instrumentKey] : leg.entryPrice;
            estTheta += (ltp * 0.12 * leg.qty);
        });
        const evaluatedShorts = shortLegs.map((leg, index) => {
            const ltp = livePrices[leg.instrumentKey] !== undefined ? livePrices[leg.instrumentKey] : leg.entryPrice;
            const pnl = (leg.entryPrice - ltp) * leg.qty;
            const pnlPct = ((leg.entryPrice - ltp) / leg.entryPrice) * 100;
            totalPnl += pnl;
            totalCredit += (leg.entryPrice * leg.qty);
            return {
                ...leg,
                index,
                ltp,
                pnl,
                pnlPct
            };
        });

        // Group evaluated legs by CE and PE
        const evCe = evaluatedShorts.filter(l => l.type === 'CE');
        const evPe = evaluatedShorts.filter(l => l.type === 'PE');

        // Worst losing leg (Tested leg)
        let worstCe = evCe.length > 0 ? evCe.reduce((min, l) => l.pnl < min.pnl ? l : min, evCe[0]) : null;
        let worstPe = evPe.length > 0 ? evPe.reduce((min, l) => l.pnl < min.pnl ? l : min, evPe[0]) : null;

        // Best decayed leg (Untested leg with profit)
        let bestCe = evCe.length > 0 ? evCe.reduce((max, l) => l.pnl > max.pnl ? l : max, evCe[0]) : null;
        let bestPe = evPe.length > 0 ? evPe.reduce((max, l) => l.pnl > max.pnl ? l : max, evPe[0]) : null;

        const cePnlSum = evCe.reduce((s, l) => s + l.pnl, 0);
        const pePnlSum = evPe.reduce((s, l) => s + l.pnl, 0);

        // Determine Directional Pressure Side
        const isUpStress = cePnlSum < pePnlSum; // Call side has worse loss
        const testedLeg = isUpStress ? worstCe : worstPe;
        const decayedLeg = isUpStress ? bestPe : bestCe;

        // Ratio between tested leg LTP and decayed leg LTP
        let ratio = 1.0;
        if (testedLeg && decayedLeg) {
            const highLtp = Math.max(testedLeg.ltp, decayedLeg.ltp);
            const lowLtp = Math.max(0.1, Math.min(testedLeg.ltp, decayedLeg.ltp));
            ratio = highLtp / lowLtp;
        }

        if (diagRatio) {
            diagRatio.textContent = isUpStress 
                ? `1.0 : ${ratio.toFixed(1)} (CE Dominant)`
                : `${ratio.toFixed(1)} : 1.0 (PE Dominant)`;
        }

        // ─────────────────────────────────────────────────────────────
        // 1. STRATEGY IN PROFIT (totalPnl >= 0)
        // ─────────────────────────────────────────────────────────────
        if (totalPnl >= 0) {
            // Check if spot is threatening breakeven (very close to BE edges)
            const isThreateningBE = (isUpStress && distToUpper < 80) || (!isUpStress && distToLower < 80);

            if (isThreateningBE) {
                if (urgencyBadge) {
                    urgencyBadge.textContent = '⚠️ BE PROXIMITY';
                    urgencyBadge.className = 'px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500 text-slate-950';
                }
                if (stratBadge) {
                    stratBadge.className = 'px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40';
                    stratBadge.textContent = `⚠️ APPROACHING BREAKEVEN (P&L +₹${Math.round(totalPnl).toLocaleString('en-IN')})`;
                }
                if (triggerBadge) {
                    triggerBadge.className = 'px-3 py-1 rounded-xl text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 flex items-center gap-1.5';
                    triggerBadge.innerHTML = `<span class="h-2 w-2 rounded-full bg-amber-400"></span><span>Spot near ${isUpStress ? 'Upper' : 'Lower'} BE (Profit +₹${Math.round(totalPnl).toLocaleString('en-IN')})</span>`;
                }
            } else {
                // Normal healthy profit!
                if (urgencyBadge) {
                    urgencyBadge.textContent = 'PROFITABLE';
                    urgencyBadge.className = 'px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
                }
                if (stratBadge) {
                    stratBadge.className = 'px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
                    stratBadge.textContent = `✅ SAFE & IN PROFIT (+₹${Math.round(totalPnl).toLocaleString('en-IN')})`;
                }
                if (triggerBadge) {
                    triggerBadge.className = 'px-3 py-1 rounded-xl text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 flex items-center gap-1.5';
                    triggerBadge.innerHTML = `<span class="h-2 w-2 rounded-full bg-emerald-400"></span><span>Healthy Decay (Net Profit +₹${Math.round(totalPnl).toLocaleString('en-IN')})</span>`;
                }
            }

            // Diagnostic status
            if (diagStatus) {
                diagStatus.textContent = `Strategy Profitable: Running safely inside corridor (+₹${Math.round(totalPnl).toLocaleString('en-IN')})`;
                diagStatus.className = 'text-sm font-bold text-emerald-300';
            }

            // If a decayed leg has profit > 250, offer an optional roll to lock in gains
            if (decayedLeg && decayedLeg.pnl > 250) {
                let suggestedRollStrike;
                if (isUpStress) {
                    suggestedRollStrike = Math.min(state.atmStrike - state.strikeStep, decayedLeg.strike + (state.strikeStep * 2));
                    if (suggestedRollStrike <= decayedLeg.strike) suggestedRollStrike = decayedLeg.strike + state.strikeStep;
                } else {
                    suggestedRollStrike = Math.max(state.atmStrike + state.strikeStep, decayedLeg.strike - (state.strikeStep * 2));
                    if (suggestedRollStrike >= decayedLeg.strike) suggestedRollStrike = decayedLeg.strike - state.strikeStep;
                }

                const rollStrikeObj = state.strikesData ? state.strikesData.find(s => s.strike === suggestedRollStrike) : null;
                const rollInst = rollStrikeObj ? (isUpStress ? rollStrikeObj.pe : rollStrikeObj.ce) : null;
                const rollPrice = rollInst && rollInst.ltp > 0 ? rollInst.ltp : (livePrices[rollInst?.instrument_key] || 45);

                if (diagRec) {
                    diagRec.innerHTML = `
                        <div class="space-y-1">
                            <div class="font-bold text-white text-xs">
                                <span class="text-emerald-400">Position Status:</span> Net profit is <span class="text-emerald-400 font-bold">+₹${Math.round(totalPnl).toLocaleString('en-IN')}</span>. Theta decay is active (+₹${Math.round(estTheta).toLocaleString('en-IN')}/day).
                            </div>
                            <div class="text-[11px] text-slate-300 pl-2 border-l-2 border-emerald-500/40">
                                💡 <span class="text-teal-300 font-bold">Optional Profit Lock:</span> Untested <span class="text-cyan-300 font-mono font-bold">${decayedLeg.strike} ${decayedLeg.type}</span> has locked <span class="text-emerald-400 font-bold">+₹${Math.round(decayedLeg.pnl).toLocaleString('en-IN')}</span> profit. You can optionally roll it to <span class="text-emerald-300 font-bold font-mono">${suggestedRollStrike}</span> (@ ~₹${rollPrice.toFixed(1)}) to compound decay, or simply <span class="text-white font-bold">HOLD</span>.
                            </div>
                        </div>
                    `;
                }

                if (diagBenefit) {
                    diagBenefit.textContent = `★ Position is running smoothly. Holding position continues daily time decay.`;
                }

                // Enable button as an optional optimization
                if (btnShift) {
                    btnShift.disabled = false;
                    btnShift.className = 'bg-slate-800 hover:bg-slate-700 border border-emerald-500/40 text-emerald-300 font-bold px-3.5 py-2 rounded-xl text-xs transition shadow flex items-center justify-center gap-1.5 cursor-pointer';
                    btnShift.innerHTML = `<span>💎 Optional: Lock +₹${Math.round(decayedLeg.pnl)} & Roll to ${suggestedRollStrike}</span>`;
                    btnShift.onclick = () => executeRoll(decayedLeg, suggestedRollStrike, decayedLeg.type);
                }
            } else {
                if (diagRec) {
                    diagRec.textContent = `Hold position. Both legs are decaying safely within corridor bounds. Total profit: +₹${Math.round(totalPnl).toLocaleString('en-IN')}.`;
                }
                if (diagBenefit) {
                    diagBenefit.textContent = `Theta decay adding steadily to daily P&L (+₹${Math.round(estTheta)}/day).`;
                }
                if (btnShift) {
                    btnShift.disabled = true;
                    btnShift.className = 'bg-slate-800 text-slate-500 font-bold px-3.5 py-2 rounded-xl text-xs transition shadow flex items-center justify-center gap-2 cursor-not-allowed';
                    btnShift.innerHTML = `<span>🔄 1-Click Roll Untested Leg</span>`;
                    btnShift.onclick = null;
                }
            }

            if (btnHedge) {
                btnHedge.disabled = true;
                btnHedge.className = 'bg-slate-800 text-slate-500 font-bold px-3.5 py-2 rounded-xl text-xs transition shadow flex items-center justify-center gap-2 cursor-not-allowed opacity-50';
                btnHedge.innerHTML = `<span>🛡️ 1-Click Buy Protection Wing</span>`;
                btnHedge.onclick = null;
            }

            return; // Finished for profit state
        }

        // ─────────────────────────────────────────────────────────────
        // 2. STRATEGY IN DEFICIT (totalPnl < 0)
        // ─────────────────────────────────────────────────────────────
        const isSevereLoss = totalPnl < -1800;
        const isModerateLoss = totalPnl < -500;

        // Minor normal oscillation (between 0 and -500) within safe distance
        if (!isModerateLoss && ratio < 1.8 && distToLower > 150 && distToUpper > 150) {
            if (urgencyBadge) {
                urgencyBadge.textContent = 'Normal';
                urgencyBadge.className = 'px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-400';
            }
            if (stratBadge) {
                stratBadge.className = 'px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
                stratBadge.textContent = `✅ SAFE & BALANCED (P&L -₹${Math.round(Math.abs(totalPnl))})`;
            }
            if (triggerBadge) {
                triggerBadge.className = 'px-3 py-1 rounded-xl text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 flex items-center gap-1.5';
                triggerBadge.innerHTML = `<span class="h-2 w-2 rounded-full bg-emerald-400"></span><span>Normal Oscillation (Minor -₹${Math.round(Math.abs(totalPnl))})</span>`;
            }
            if (diagStatus) {
                diagStatus.textContent = 'Strategy running inside optimal decay corridor';
                diagStatus.className = 'text-sm font-bold text-slate-200';
            }
            if (diagRec) {
                diagRec.textContent = `Hold position. Minor intraday swing of -₹${Math.round(Math.abs(totalPnl))}. Theta decay (+₹${Math.round(estTheta)}/day) will steadily recover this.`;
            }
            if (diagBenefit) {
                diagBenefit.textContent = 'Theta decay adding steadily to daily P&L';
            }
            if (btnShift) {
                btnShift.disabled = true;
                btnShift.className = 'bg-slate-800 text-slate-500 font-bold px-3.5 py-2 rounded-xl text-xs transition shadow flex items-center justify-center gap-2 cursor-not-allowed';
                btnShift.innerHTML = `<span>🔄 1-Click Roll Untested Leg</span>`;
                btnShift.onclick = null;
            }
            if (btnHedge) {
                btnHedge.disabled = true;
                btnHedge.className = 'bg-slate-800 text-slate-500 font-bold px-3.5 py-2 rounded-xl text-xs transition shadow flex items-center justify-center gap-2 cursor-not-allowed opacity-50';
                btnHedge.innerHTML = `<span>🛡️ 1-Click Buy Protection Wing</span>`;
                btnHedge.onclick = null;
            }
            return;
        }

        // Active Loss Defense Triggered (Loss < -500, or heavy skew / tested leg)
        if (testedLeg && decayedLeg) {
            if (urgencyBadge) {
                urgencyBadge.textContent = isSevereLoss ? '🚨 HIGH DEFENSE' : '⚠️ ADJUSTMENT NEEDED';
                urgencyBadge.className = isSevereLoss 
                    ? 'px-2 py-0.5 rounded text-[10px] font-extrabold bg-rose-600 text-white animate-pulse'
                    : 'px-2 py-0.5 rounded text-[10px] font-extrabold bg-amber-500 text-slate-950 animate-pulse';
            }

            if (stratBadge) {
                stratBadge.className = isSevereLoss 
                    ? 'px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40 animate-pulse'
                    : 'px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40';
                stratBadge.textContent = `⚠️ ADJUSTMENT REQUIRED (Loss -₹${Math.round(Math.abs(totalPnl)).toLocaleString('en-IN')})`;
            }

            if (triggerBadge) {
                triggerBadge.className = 'px-3 py-1 rounded-xl text-xs font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40 flex items-center gap-1.5 animate-pulse';
                triggerBadge.innerHTML = `<span class="h-2 w-2 rounded-full bg-rose-400"></span><span>Defense Triggered: ${isUpStress ? 'Call Under Stress' : 'Put Under Stress'} (Net Loss -₹${Math.round(Math.abs(totalPnl)).toLocaleString('en-IN')})</span>`;
            }

            if (diagStatus) {
                diagStatus.textContent = isUpStress 
                    ? `Market Rally Stress: Tested ${testedLeg.strike} CE (LTP ₹${testedLeg.ltp.toFixed(1)}, Loss -₹${Math.round(Math.abs(testedLeg.pnl)).toLocaleString('en-IN')})`
                    : `Market Drop Stress: Tested ${testedLeg.strike} PE (LTP ₹${testedLeg.ltp.toFixed(1)}, Loss -₹${Math.round(Math.abs(testedLeg.pnl)).toLocaleString('en-IN')})`;
                diagStatus.className = 'text-sm font-extrabold text-rose-300';
            }

            // Suggested new strike for rolling the decayed untested leg closer to ATM
            let suggestedRollStrike;
            if (isUpStress) {
                suggestedRollStrike = Math.min(state.atmStrike - state.strikeStep, decayedLeg.strike + (state.strikeStep * 3));
                if (suggestedRollStrike <= decayedLeg.strike) {
                    suggestedRollStrike = decayedLeg.strike + (state.strikeStep * 2);
                }
            } else {
                suggestedRollStrike = Math.max(state.atmStrike + state.strikeStep, decayedLeg.strike - (state.strikeStep * 3));
                if (suggestedRollStrike >= decayedLeg.strike) {
                    suggestedRollStrike = decayedLeg.strike - (state.strikeStep * 2);
                }
            }

            const rollStrikeObj = state.strikesData.find(s => s.strike === suggestedRollStrike);
            const rollInst = rollStrikeObj ? (isUpStress ? rollStrikeObj.pe : rollStrikeObj.ce) : null;
            const rollKey = rollInst ? rollInst.instrument_key : null;
            const rollPrice = rollInst && rollInst.ltp > 0 ? rollInst.ltp : (livePrices[rollKey] || 65);

            let suggestedWingStrike;
            if (isUpStress) {
                suggestedWingStrike = testedLeg.strike + (state.strikeStep * 3);
            } else {
                suggestedWingStrike = testedLeg.strike - (state.strikeStep * 3);
            }

            const wingStrikeObj = state.strikesData.find(s => s.strike === suggestedWingStrike);
            const wingInst = wingStrikeObj ? (isUpStress ? wingStrikeObj.ce : wingStrikeObj.pe) : null;
            const wingKey = wingInst ? wingInst.instrument_key : null;
            const wingPrice = wingInst && wingInst.ltp > 0 ? wingInst.ltp : (livePrices[wingKey] || 20);

            const bookedProfit = Math.round(decayedLeg.pnl);
            const freshCredit = Math.round(rollPrice * decayedLeg.qty);

            if (diagRec) {
                diagRec.innerHTML = `
                    <div class="space-y-1.5">
                        <div class="font-bold text-white text-xs">
                            <span class="text-emerald-400">Step 1 (Recover Loss):</span> Roll untested <span class="text-cyan-300 font-mono font-bold">${decayedLeg.strike} ${decayedLeg.type}</span> ➔ <span class="text-emerald-400 font-mono font-bold">${suggestedRollStrike} ${decayedLeg.type}</span> (@ ~₹${rollPrice.toFixed(1)}).
                        </div>
                        <div class="text-[11px] text-slate-300 pl-2 border-l-2 border-emerald-500/40">
                            • Locks in <span class="text-emerald-400 font-bold">+₹${bookedProfit.toLocaleString('en-IN')}</span> profit from decayed leg.<br>
                            • Collects fresh <span class="text-cyan-300 font-bold">+₹${freshCredit.toLocaleString('en-IN')}</span> credit to offset CE loss.
                        </div>
                        <div class="font-bold text-white text-xs pt-0.5">
                            <span class="text-cyan-400">Step 2 (Cap Risk):</span> Buy <span class="text-cyan-300 font-mono font-bold">${suggestedWingStrike} ${testedLeg.type}</span> Wing (@ ~₹${wingPrice.toFixed(1)}) to cap runaway upside risk.
                        </div>
                    </div>
                `;
            }

            if (diagBenefit) {
                diagBenefit.textContent = `★ Cuts deficit by +₹${(freshCredit + bookedProfit).toLocaleString('en-IN')} & restores Delta to Neutral`;
            }

            if (btnShift) {
                btnShift.disabled = false;
                btnShift.className = 'bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-slate-950 font-extrabold px-3.5 py-2 rounded-xl text-xs transition shadow-lg shadow-emerald-950/50 flex items-center justify-center gap-1.5 cursor-pointer';
                btnShift.innerHTML = `<span>🔄 1-Click Roll ${decayedLeg.strike} ${decayedLeg.type} ➔ ${suggestedRollStrike} ${decayedLeg.type}</span>`;
                btnShift.onclick = () => executeRoll(decayedLeg, suggestedRollStrike, decayedLeg.type);
            }

            if (btnHedge) {
                btnHedge.disabled = false;
                btnHedge.className = 'bg-cyan-600 hover:bg-cyan-500 text-slate-950 font-extrabold px-3.5 py-2 rounded-xl text-xs transition shadow flex items-center justify-center gap-1.5 cursor-pointer opacity-100';
                btnHedge.innerHTML = `<span>🛡️ 1-Click Buy ${suggestedWingStrike} ${testedLeg.type} Wing (@ ₹${wingPrice.toFixed(1)})</span>`;
                btnHedge.onclick = () => executeBuyWing(suggestedWingStrike, testedLeg.type, wingPrice, wingKey);
            }
        }
    }

    function executeRoll(oldLeg, newStrike, type) {
        // Find instrument for new strike from strikesData
        const strikeObj = state.strikesData.find(s => s.strike === newStrike);
        const newInst = strikeObj ? (type === 'CE' ? strikeObj.ce : strikeObj.pe) : null;
        const newKey = newInst ? newInst.instrument_key : null;
        const newPrice = newInst && newInst.ltp > 0 ? newInst.ltp : (livePrices[newKey] || 45);

        // Remove old decayed leg, insert new shifted leg
        activeBasket = activeBasket.filter(l => l.id !== oldLeg.id && !(l.strike === oldLeg.strike && l.type === oldLeg.type && l.action === oldLeg.action));
        activeBasket.push({
            id: `${type}_${newStrike}_${Date.now()}`,
            action: 'SELL',
            strike: newStrike,
            type: type,
            qty: oldLeg.qty,
            entryPrice: newPrice,
            instrumentKey: newKey,
            oiChange: newInst ? newInst.oi_change_pct : 0,
            buildup: newInst ? newInst.buildup : 'Shifted Leg'
        });

        if (newKey && !state.allInstrumentKeys.includes(newKey)) {
            state.allInstrumentKeys.push(newKey);
            subscribeKeys();
        }

        saveActiveBasket(true);
        showToast('Strike Rolled Successfully', `Rolled untested ${oldLeg.strike} ${type} ➔ ${newStrike} ${type}.\nFresh credit collected: ₹${newPrice.toFixed(2)} | Delta restored to neutral.`, 'success');
    }

    function executeBuyWing(strike, type, price, key = null) {
        activeBasket.push({
            id: `BUY_${type}_${strike}_${Date.now()}`,
            action: 'BUY',
            strike: strike,
            type: type,
            qty: state.lotSize,
            entryPrice: price > 0 ? price : 20,
            instrumentKey: key,
            oiChange: 0,
            buildup: 'Protective Wing'
        });

        if (key && !state.allInstrumentKeys.includes(key)) {
            state.allInstrumentKeys.push(key);
            subscribeKeys();
        }

        saveActiveBasket(true);
        showToast('Hedge Wing Deployed', `Bought ${strike} ${type} @ ₹${price.toFixed(2)}.\nCapped runaway risk. Strategy converted to defined-risk Iron Condor!`, 'success');
    }

    // ─────────────────────────────────────────────────────────────
    // 5. LIVE PAYOFF CHART (RISK GRAPH - CHART.JS)
    // ─────────────────────────────────────────────────────────────
    function renderPayoffChart(simulatedSpot = null) {
        try {
            const emptyEl = document.getElementById('payoff-empty-state');
            const canvasContainer = document.getElementById('payoff-canvas-container');
            const stratTypeBadge = document.getElementById('payoff-strategy-type');
            const legsList = document.getElementById('payoff-legs-list');
            const maxProfitEl = document.getElementById('payoff-max-profit');
            const maxProfitSub = document.getElementById('payoff-max-profit-sub');
            const maxLossEl = document.getElementById('payoff-max-loss');
            const maxLossSub = document.getElementById('payoff-max-loss-sub');
            const beRangeEl = document.getElementById('payoff-be-range');
            const beSubEl = document.getElementById('payoff-be-sub');
            const currentSpotEl = document.getElementById('payoff-current-spot-pnl');
            const currentSpotSub = document.getElementById('payoff-current-spot-sub');

            if (!activeBasket || activeBasket.length === 0) {
                if (emptyEl) emptyEl.classList.remove('hidden');
                if (canvasContainer) canvasContainer.classList.add('hidden');
                if (payoffChartInstance) {
                    try { payoffChartInstance.destroy(); } catch (e) {}
                    payoffChartInstance = null;
                }
                if (stratTypeBadge) stratTypeBadge.textContent = 'Empty Basket';
                if (legsList) legsList.innerHTML = '<span class="text-slate-500 italic text-xs">No active legs in basket</span>';
                if (maxProfitEl) maxProfitEl.textContent = '₹0';
                if (maxLossEl) maxLossEl.textContent = '₹0';
                if (beRangeEl) beRangeEl.textContent = '0 - 0';
                if (currentSpotEl) currentSpotEl.textContent = '₹0';
                return;
            }

            if (emptyEl) emptyEl.classList.add('hidden');
            if (canvasContainer) canvasContainer.classList.remove('hidden');

            // 1. Identify Strategy Configuration
            const shortLegs = activeBasket.filter(l => l.action === 'SELL');
            const longLegs = activeBasket.filter(l => l.action === 'BUY');
            let stratTitle = `${activeBasket.length}-Leg Basket`;
            if (shortLegs.length === 2 && longLegs.length === 0) {
                const pe = shortLegs.find(l => l.type === 'PE');
                const ce = shortLegs.find(l => l.type === 'CE');
                if (pe && ce) {
                    stratTitle = parseFloat(pe.strike) === parseFloat(ce.strike) ? 'ATM Short Straddle' : 'Delta-Neutral Strangle';
                }
            } else if (shortLegs.length === 2 && longLegs.length === 2) {
                const pe = shortLegs.find(l => l.type === 'PE');
                const ce = shortLegs.find(l => l.type === 'CE');
                if (pe && ce) {
                    stratTitle = parseFloat(pe.strike) === parseFloat(ce.strike) ? 'Iron Fly (Defined Risk)' : 'Iron Condor (Defined Risk)';
                }
            } else if (shortLegs.length > 0 && longLegs.length === 0) {
                stratTitle = `${shortLegs.length}-Leg Short Writing`;
            }

            if (stratTypeBadge) stratTypeBadge.textContent = stratTitle;

            // 2. Render Active Leg Badges
            if (legsList) {
                legsList.innerHTML = activeBasket.map(leg => {
                    const isSell = leg.action === 'SELL';
                    const isCe = leg.type === 'CE';
                    const entry = parseFloat(leg.entryPrice || 0);
                    const qty = parseInt(leg.qty || state.lotSize, 10);
                    const ltp = livePrices[leg.instrumentKey] !== undefined ? livePrices[leg.instrumentKey] : entry;
                    const pnl = isSell ? (entry - ltp) * qty : (ltp - entry) * qty;
                    return `
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold border ${isSell ? 'bg-rose-500/10 text-rose-300 border-rose-500/30' : 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30'}">
                            <span class="px-1 py-0.2 rounded text-[10px] ${isSell ? 'bg-rose-500/30 text-rose-200' : 'bg-emerald-500/30 text-emerald-200'}">${leg.action}</span>
                            <span class="${isCe ? 'text-emerald-300' : 'text-rose-300'} font-extrabold">${leg.strike} ${leg.type}</span>
                            <span class="text-slate-400 font-sans text-[11px]">(${qty} @ ₹${entry.toFixed(1)})</span>
                            <span class="text-[11px] ${pnl >= 0 ? 'text-emerald-400' : 'text-rose-400'} font-extrabold">${pnl >= 0 ? '+' : ''}₹${Math.round(pnl)}</span>
                        </span>
                    `;
                }).join('');
            }

            // 3. Strategy Payoff Engine across Spot Range
            function calcPayoffAt(spotPrice) {
                let pnl = 0;
                activeBasket.forEach(leg => {
                    let intr = 0;
                    const strike = parseFloat(leg.strike);
                    const entry = parseFloat(leg.entryPrice || 0);
                    const qty = parseInt(leg.qty || state.lotSize, 10);
                    if (leg.type === 'CE') {
                        intr = Math.max(0, spotPrice - strike);
                    } else {
                        intr = Math.max(0, strike - spotPrice);
                    }
                    pnl += leg.action === 'SELL' ? (entry - intr) * qty : (intr - entry) * qty;
                });
                return pnl;
            }

            const basketStrikes = activeBasket.map(l => parseFloat(l.strike));
            const minStrike = Math.min(...basketStrikes, state.atmStrike);
            const maxStrike = Math.max(...basketStrikes, state.atmStrike);
            const step = state.strikeStep || 50;
            const spanMin = Math.round(minStrike - (step * 14));
            const spanMax = Math.round(maxStrike + (step * 14));

            // Generate dense sample points
            const sampleStep = Math.max(10, Math.round(step / 2));
            const rawPoints = [];
            for (let s = spanMin; s <= spanMax; s += sampleStep) {
                rawPoints.push(s);
            }

            // Also add exact strikes, liveSpot, and simulatedSpot
            basketStrikes.forEach(stk => rawPoints.push(stk));
            rawPoints.push(Math.round(liveSpot));
            if (simulatedSpot !== null && !isNaN(simulatedSpot)) {
                rawPoints.push(Math.round(simulatedSpot));
            }

            // Find Breakevens via sign crossing
            const breakevens = [];
            for (let s = spanMin; s <= spanMax; s += 5) {
                const p1 = calcPayoffAt(s);
                const p2 = calcPayoffAt(s + 5);
                if ((p1 <= 0 && p2 >= 0) || (p1 >= 0 && p2 <= 0)) {
                    if (p2 !== p1) {
                        const be = Math.round(s + (0 - p1) * 5 / (p2 - p1));
                        if (!breakevens.includes(be)) {
                            breakevens.push(be);
                            rawPoints.push(be);
                        }
                    }
                }
            }
            breakevens.sort((a, b) => a - b);

            // Sort and deduplicate all x spots
            const uniqueSpots = Array.from(new Set(rawPoints)).sort((a, b) => a - b);

            let maxProfit = -Infinity;
            let minProfit = Infinity;
            const payoffPoints = uniqueSpots.map(s => {
                const pnl = calcPayoffAt(s);
                if (pnl > maxProfit) maxProfit = pnl;
                if (pnl < minProfit) minProfit = pnl;
                return { x: s, y: Math.round(pnl) };
            });

            // 4. Update Strategy KPI Cards
            let shortCeQty = 0, longCeQty = 0, shortPeQty = 0, longPeQty = 0;
            activeBasket.forEach(l => {
                const q = parseInt(l.qty || state.lotSize, 10);
                if (l.type === 'CE') {
                    if (l.action === 'SELL') shortCeQty += q; else longCeQty += q;
                } else {
                    if (l.action === 'SELL') shortPeQty += q; else longPeQty += q;
                }
            });
            const hasUnhedgedCe = shortCeQty > longCeQty;
            const hasUnhedgedPe = shortPeQty > longPeQty;

            if (maxProfitEl) {
                maxProfitEl.textContent = `+₹${Math.round(maxProfit).toLocaleString('en-IN')}`;
            }

            if (maxLossEl) {
                if (hasUnhedgedCe || hasUnhedgedPe) {
                    maxLossEl.textContent = 'Uncapped Loss ⚠️';
                    maxLossEl.className = 'text-base sm:text-lg font-black font-mono text-rose-400 mt-0.5';
                    if (maxLossSub) maxLossSub.textContent = (hasUnhedgedCe && hasUnhedgedPe) ? 'Naked CE & PE tail risk' : (hasUnhedgedCe ? 'Naked upside rally risk' : 'Naked downside drop risk');
                } else {
                    const maxLossVal = Math.round(Math.abs(minProfit));
                    maxLossEl.textContent = `-₹${maxLossVal.toLocaleString('en-IN')}`;
                    maxLossEl.className = 'text-base sm:text-lg font-black font-mono text-amber-300 mt-0.5';
                    if (maxLossSub) maxLossSub.textContent = 'Defined risk with wings';
                }
            }

            if (beRangeEl) {
                if (breakevens.length >= 2) {
                    const lowerBe = Math.min(...breakevens);
                    const upperBe = Math.max(...breakevens);
                    beRangeEl.textContent = `${lowerBe.toLocaleString('en-IN')} - ${upperBe.toLocaleString('en-IN')}`;
                    const buffer = Math.round(Math.min(Math.abs(liveSpot - lowerBe), Math.abs(upperBe - liveSpot)));
                    const bufferPct = ((buffer / liveSpot) * 100).toFixed(1);
                    if (beSubEl) beSubEl.textContent = `Safety Buffer: ±${buffer} pts (${bufferPct}%)`;
                } else if (breakevens.length === 1) {
                    beRangeEl.textContent = `BE: ${breakevens[0].toLocaleString('en-IN')}`;
                    if (beSubEl) beSubEl.textContent = `Buffer: ±${Math.abs(Math.round(liveSpot - breakevens[0]))} pts`;
                } else {
                    beRangeEl.textContent = maxProfit > 0 ? 'Full Profit Zone' : 'Deficit';
                    if (beSubEl) beSubEl.textContent = 'No breakeven cross';
                }
            }

            const currentSpotPnl = Math.round(calcPayoffAt(liveSpot));
            if (currentSpotEl) {
                currentSpotEl.textContent = `${currentSpotPnl >= 0 ? '+' : ''}₹${currentSpotPnl.toLocaleString('en-IN')}`;
                currentSpotEl.className = `text-base sm:text-lg font-black font-mono mt-0.5 ${currentSpotPnl >= 0 ? 'text-emerald-400' : 'text-rose-400'}`;
                if (currentSpotSub) currentSpotSub.textContent = `At spot ${Math.round(liveSpot).toLocaleString('en-IN')}`;
            }

            // 5. Draw Canvas Chart
            const canvasEl = document.getElementById('payoffChart');
            if (!canvasEl) return;
            const ctx = canvasEl.getContext('2d');

            const datasets = [
                {
                    label: 'Expiry P&L',
                    data: payoffPoints,
                    borderColor: '#10b981',
                    borderWidth: 2.5,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    fill: 'origin',
                    backgroundColor: 'rgba(16, 185, 129, 0.12)',
                    tension: 0.1
                },
                {
                    label: `Current Spot (${Math.round(liveSpot)})`,
                    data: [{ x: Math.round(liveSpot), y: currentSpotPnl }],
                    borderColor: '#ffffff',
                    backgroundColor: '#06b6d4',
                    borderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    showLine: false
                }
            ];

            if (simulatedSpot !== null && !isNaN(simulatedSpot)) {
                const simPnlVal = Math.round(calcPayoffAt(simulatedSpot));
                datasets.push({
                    label: `Simulated Spot (${Math.round(simulatedSpot)})`,
                    data: [{ x: Math.round(simulatedSpot), y: simPnlVal }],
                    borderColor: '#ffffff',
                    backgroundColor: '#f59e0b',
                    borderWidth: 2.5,
                    pointRadius: 8,
                    pointHoverRadius: 10,
                    showLine: false
                });
            }

            if (breakevens.length > 0) {
                datasets.push({
                    label: 'Breakevens',
                    data: breakevens.map(b => ({ x: b, y: 0 })),
                    borderColor: '#f1f5f9',
                    backgroundColor: '#e2e8f0',
                    borderWidth: 1.5,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    showLine: false
                });
            }

            function drawChart() {
                if (typeof Chart === 'undefined') {
                    setTimeout(drawChart, 150);
                    return;
                }

                if (payoffChartInstance) {
                    try { payoffChartInstance.destroy(); } catch (e) {}
                    payoffChartInstance = null;
                }

                payoffChartInstance = new Chart(ctx, {
                    type: 'line',
                    data: { datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'nearest',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                align: 'end',
                                labels: {
                                    boxWidth: 8,
                                    boxHeight: 8,
                                    color: '#94a3b8',
                                    font: { size: 10, weight: 'bold' }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    title: (items) => {
                                        if (!items || !items.length) return '';
                                        return `Spot at Expiry: ${items[0].parsed.x ? items[0].parsed.x.toLocaleString('en-IN') : ''}`;
                                    },
                                    label: (item) => {
                                        const y = item.parsed.y;
                                        const lbl = item.dataset.label || 'Projected P&L';
                                        return ` ${lbl}: ${y >= 0 ? '+' : ''}₹${Math.round(y).toLocaleString('en-IN')}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: 'linear',
                                grid: { color: 'rgba(51, 65, 85, 0.3)' },
                                ticks: {
                                    color: '#94a3b8',
                                    font: { size: 10 },
                                    callback: (v) => v.toLocaleString('en-IN')
                                }
                            },
                            y: {
                                grid: { color: 'rgba(51, 65, 85, 0.3)' },
                                ticks: {
                                    color: '#94a3b8',
                                    font: { size: 10 },
                                    callback: (v) => `₹${v.toLocaleString('en-IN')}`
                                }
                            }
                        }
                    }
                });
            }

            drawChart();
        } catch (err) {
            console.error('renderPayoffChart error:', err);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 6. PROTOBUF & WEBSOCKET ENGINE (UPSTOX STREAM)
    // ─────────────────────────────────────────────────────────────
    async function initProtobuf() {
        try {
            protobufRoot = await protobuf.load('/MarketDataFeed_v3.proto');
            console.log('MarketDataFeed Protobuf loaded for Delta Neutral.');
        } catch (e) {
            console.warn('Protobuf load error:', e);
        }
    }

    async function connectWebsocket() {
        if (isConnecting) return;
        isConnecting = true;

        const dot = document.getElementById('ws-dot');
        const text = document.getElementById('ws-status-text');

        dot.className = 'inline-block h-2.5 w-2.5 rounded-full bg-amber-500 animate-pulse';
        text.textContent = 'Connecting…';

        try {
            const res = await axios.get('/api/delta-neutral/ws-url');
            if (!res.data || !res.data.data || !res.data.data.authorizedRedirectUri) {
                throw new Error('No WS URL returned');
            }

            const wsUrl = res.data.data.authorizedRedirectUri;
            ws = new WebSocket(wsUrl);
            ws.binaryType = 'arraybuffer';

            ws.onopen = () => {
                isConnecting = false;
                dot.className = 'inline-block h-2.5 w-2.5 rounded-full bg-emerald-500 shadow-md shadow-emerald-500/50';
                text.textContent = 'Live Feed Connected';
                subscribeKeys();
            };

            ws.onclose = () => {
                isConnecting = false;
                dot.className = 'inline-block h-2.5 w-2.5 rounded-full bg-red-500';
                text.textContent = 'Disconnected';
                setTimeout(connectWebsocket, 5000);
            };

            ws.onerror = (e) => {
                console.error('WS Error:', e);
            };

            ws.onmessage = (event) => {
                decodeProtobuf(event.data);
            };

        } catch (err) {
            isConnecting = false;
            dot.className = 'inline-block h-2.5 w-2.5 rounded-full bg-red-500';
            text.textContent = 'Stream Offline (Polling)';
            console.warn('WebSocket failed, running in static mode:', err.message);
        }
    }

    function subscribeKeys() {
        if (!ws || ws.readyState !== WebSocket.OPEN) return;
        if (!state.allInstrumentKeys || state.allInstrumentKeys.length === 0) return;

        const subMessage = {
            guid: 'delta_neutral_' + Date.now(),
            method: 'sub',
            data: {
                mode: 'full',
                instrumentKeys: state.allInstrumentKeys
            }
        };

        ws.send(new TextEncoder().encode(JSON.stringify(subMessage)));
        console.log(`Subscribed to ${state.allInstrumentKeys.length} instruments.`);
    }

    function decodeProtobuf(buffer) {
        if (!protobufRoot) return;

        try {
            const arr = new Uint8Array(buffer);
            if (arr.length > 0 && arr[0] === 123) return; // JSON text message

            const FeedResponse = protobufRoot.lookupType('com.upstox.marketdatafeederv3udapi.rpc.proto.FeedResponse');
            const message = FeedResponse.decode(arr);
            const obj = FeedResponse.toObject(message, { enums: String, bytes: String });

            if (obj && obj.feeds) {
                let updated = false;
                for (const [key, feed] of Object.entries(obj.feeds)) {
                    let ltp = null;
                    if (feed.fullFeed && feed.fullFeed.marketFF && feed.fullFeed.marketFF.ltpc) {
                        ltp = feed.fullFeed.marketFF.ltpc.ltp;
                    } else if (feed.fullFeed && feed.fullFeed.indexFF && feed.fullFeed.indexFF.ltpc) {
                        ltp = feed.fullFeed.indexFF.ltpc.ltp;
                    }

                    if (ltp !== null && ltp > 0) {
                        livePrices[key] = ltp;

                        // Check if Spot update
                        if (key === state.indexKey) {
                            liveSpot = ltp;
                            document.getElementById('live-spot-val').textContent = liveSpot.toFixed(2);
                            const diff = liveSpot - state.indexOpen;
                            const diffEl = document.getElementById('live-spot-diff');
                            diffEl.textContent = `${diff >= 0 ? '+' : ''}${diff.toFixed(2)}`;
                            diffEl.className = `text-[11px] font-bold ${diff >= 0 ? 'text-emerald-400' : 'text-rose-400'}`;
                        }

                        // Check if India VIX update
                        if (key === 'NSE_INDEX|India VIX') {
                            liveVix = ltp;
                            document.getElementById('live-vix-val').textContent = liveVix.toFixed(2);
                            liveVix1Day = Math.round(liveSpot * ((liveVix / 100) / Math.sqrt(365)));
                            document.getElementById('vix-1d-range').textContent = `±${liveVix1Day} pts`;
                        }

                        updated = true;
                    }
                }

                if (updated) {
                    tickCount++;
                    document.getElementById('tick-counter').textContent = `${tickCount} ticks`;
                    updateBasketLiveOnly();
                }
            }
        } catch (e) {
            // Protobuf decode fallback
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 7. EVENT LISTENERS & INTERACTION
    // ─────────────────────────────────────────────────────────────
    async function initApp() {
        renderBasketTable();
        updateCorridorAndRecovery();
        renderPayoffChart();

        // Connect Protobuf & WebSocket
        await initProtobuf();
        await connectWebsocket();

        // Preset Tabs
        document.querySelectorAll('.preset-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.preset-tab').forEach(t => {
                    t.className = 'preset-tab px-3 py-1 rounded-lg font-bold transition text-slate-400 hover:text-white';
                });
                tab.className = 'preset-tab active px-3 py-1 rounded-lg font-bold transition bg-emerald-600 text-white';

                const preset = tab.dataset.preset;
                document.querySelectorAll('.scanner-row').forEach(row => {
                    if (preset === 'ALL' || row.dataset.preset.includes(preset)) {
                        row.classList.remove('hidden');
                    } else {
                        row.classList.add('hidden');
                    }
                });
            });
        });

        // Deploy Pair Buttons
        document.querySelectorAll('.btn-deploy-pair').forEach(btn => {
            btn.addEventListener('click', () => {
                const pair = JSON.parse(btn.dataset.pair);
                deployPair(pair);
            });
        });

        // Basket Click Actions: Delete Leg, In-Row Hedge Wing, and In-Row Roll Closer
        document.getElementById('basket-table-body').addEventListener('click', (e) => {
            const btnDel = e.target.closest('.btn-delete-leg');
            if (btnDel) {
                const idx = parseInt(btnDel.dataset.index, 10);
                activeBasket.splice(idx, 1);
                saveActiveBasket(true);
                return;
            }

            const btnWing = e.target.closest('.btn-row-add-wing');
            if (btnWing) {
                const strike = parseInt(btnWing.dataset.strike, 10);
                const type = btnWing.dataset.type;
                const wingStrike = type === 'CE' ? (strike + state.strikeStep * 3) : (strike - state.strikeStep * 3);
                const wingObj = state.strikesData.find(s => s.strike === wingStrike);
                const wingInst = wingObj ? (type === 'CE' ? wingObj.ce : wingObj.pe) : null;
                const wingPrice = wingInst && wingInst.ltp > 0 ? wingInst.ltp : (livePrices[wingInst?.instrument_key] || 20);
                executeBuyWing(wingStrike, type, wingPrice, wingInst?.instrument_key || null);
                return;
            }

            const btnRoll = e.target.closest('.btn-row-roll-untested');
            if (btnRoll) {
                const idx = parseInt(btnRoll.dataset.index, 10);
                const leg = activeBasket[idx];
                if (leg) {
                    const newStrike = leg.type === 'PE'
                        ? Math.min(state.atmStrike - state.strikeStep, leg.strike + (state.strikeStep * 2))
                        : Math.max(state.atmStrike + state.strikeStep, leg.strike - (state.strikeStep * 2));
                    executeRoll(leg, newStrike, leg.type);
                }
                return;
            }
        });

        // Inline Real-Time Edit Entry Price & Quantity in Active Basket Table
        const basketTableBody = document.getElementById('basket-table-body');

        // 1. Real-time typing response without destroying inputs or focus
        basketTableBody.addEventListener('input', (e) => {
            const entryInp = e.target.closest('.basket-entry-input');
            if (entryInp) {
                const idx = parseInt(entryInp.dataset.index, 10);
                const val = parseFloat(entryInp.value);
                if (!isNaN(val) && val > 0 && activeBasket[idx]) {
                    activeBasket[idx].entryPrice = val;
                    try {
                        localStorage.setItem(getStorageKey(), JSON.stringify(activeBasket));
                    } catch (err) {}
                    updateBasketLiveOnly();
                    renderPayoffChart();
                }
            }

            const qtyInp = e.target.closest('.basket-qty-input');
            if (qtyInp) {
                const idx = parseInt(qtyInp.dataset.index, 10);
                const val = parseInt(qtyInp.value, 10);
                if (!isNaN(val) && val > 0 && activeBasket[idx]) {
                    activeBasket[idx].qty = val;
                    try {
                        localStorage.setItem(getStorageKey(), JSON.stringify(activeBasket));
                    } catch (err) {}
                    updateBasketLiveOnly();
                    renderPayoffChart();
                }
            }
        });

        // 2. Commit and format on blur / change
        basketTableBody.addEventListener('change', (e) => {
            const entryInp = e.target.closest('.basket-entry-input');
            if (entryInp) {
                const idx = parseInt(entryInp.dataset.index, 10);
                const val = parseFloat(entryInp.value);
                if (!isNaN(val) && val > 0 && activeBasket[idx]) {
                    activeBasket[idx].entryPrice = val;
                    entryInp.value = val.toFixed(2);
                    saveActiveBasket(false); // false = do not re-render table DOM
                    showToast('Entry Price Saved', `Strike ${activeBasket[idx].strike} ${activeBasket[idx].type} entry set to ₹${val.toFixed(2)}`, 'success');
                } else if (activeBasket[idx]) {
                    entryInp.value = activeBasket[idx].entryPrice.toFixed(2);
                }
            }

            const qtyInp = e.target.closest('.basket-qty-input');
            if (qtyInp) {
                const idx = parseInt(qtyInp.dataset.index, 10);
                const val = parseInt(qtyInp.value, 10);
                if (!isNaN(val) && val > 0 && activeBasket[idx]) {
                    activeBasket[idx].qty = val;
                    saveActiveBasket(false); // false = do not re-render table DOM
                    showToast('Quantity Saved', `Strike ${activeBasket[idx].strike} ${activeBasket[idx].type} qty set to ${val} shares`, 'success');
                } else if (activeBasket[idx]) {
                    qtyInp.value = activeBasket[idx].qty;
                }
            }
        });

        // 3. Pressing Enter cleanly commits and unfocuses
        basketTableBody.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const inp = e.target.closest('.basket-entry-input, .basket-qty-input');
                if (inp) {
                    inp.blur();
                }
            }
        });

        // ─────────────────────────────────────────────────────────────
        // 8. CLEAR BASKET CUSTOM MODAL
        // ─────────────────────────────────────────────────────────────
        const modalClear = document.getElementById('modal-clear-basket');
        document.getElementById('btn-clear-basket').addEventListener('click', () => {
            modalClear.classList.remove('hidden');
        });

        document.getElementById('btn-cancel-clear-basket').addEventListener('click', () => {
            modalClear.classList.add('hidden');
        });

        modalClear.addEventListener('click', (e) => {
            if (e.target === modalClear) modalClear.classList.add('hidden');
        });

        document.getElementById('btn-confirm-clear-basket').addEventListener('click', () => {
            activeBasket = [];
            saveActiveBasket();
            modalClear.classList.add('hidden');
        });

        // ─────────────────────────────────────────────────────────────
        // 9. OPTION CHAIN ADD LEG MODAL
        // ─────────────────────────────────────────────────────────────
        const addModal = document.getElementById('modal-add-leg');
        const ocTableBody = document.getElementById('oc-table-body');
        const ocDefaultQtyInput = document.getElementById('oc-default-qty');
        const btnOcSell = document.getElementById('oc-action-sell');
        const btnOcBuy = document.getElementById('oc-action-buy');
        const recommendationsBar = document.getElementById('oc-recommendations-bar');

        let ocAction = 'SELL'; // default SELL (option writing)

        // Action toggle
        btnOcSell.addEventListener('click', () => {
            ocAction = 'SELL';
            btnOcSell.className = 'px-3 py-1 rounded-lg font-bold bg-rose-600 text-white shadow cursor-pointer';
            btnOcBuy.className = 'px-3 py-1 rounded-lg font-bold text-slate-400 hover:text-white cursor-pointer';
            renderOptionChainTable();
        });

        btnOcBuy.addEventListener('click', () => {
            ocAction = 'BUY';
            btnOcBuy.className = 'px-3 py-1 rounded-lg font-bold bg-emerald-600 text-white shadow cursor-pointer';
            btnOcSell.className = 'px-3 py-1 rounded-lg font-bold text-slate-400 hover:text-white cursor-pointer';
            renderOptionChainTable();
        });

        // Global Lots sync
        ocDefaultQtyInput.addEventListener('input', (e) => {
            const qty = parseInt(e.target.value, 10) || state.lotSize;
            document.querySelectorAll('.oc-lots-input').forEach(inp => inp.value = qty);
        });

        // Open Modal
        document.getElementById('btn-add-custom-leg').addEventListener('click', () => {
            renderOptionChainTable();
            addModal.classList.remove('hidden');
        });

        // Close Modal
        document.getElementById('btn-close-add-modal').addEventListener('click', () => {
            addModal.classList.add('hidden');
        });
        document.getElementById('btn-done-add-modal').addEventListener('click', () => {
            addModal.classList.add('hidden');
        });
        addModal.addEventListener('click', (e) => {
            if (e.target === addModal) addModal.classList.add('hidden');
        });

        // Calculate Market Suggested Strikes
        function getMarketRecommendations() {
            let safeCe = null, safePe = null;
            let balCe = null, balPe = null;
            let eqCe = null, eqPe = null;
            let minDiffSafeCe = 999, minDiffSafePe = 999;
            let minDiffBalCe = 999, minDiffBalPe = 999;
            let minEqDiff = 999;

            state.strikesData.forEach(item => {
                const ce = item.ce;
                const pe = item.pe;

                if (ce && ce.delta) {
                    const diffSafe = Math.abs(ce.delta - 0.18);
                    if (diffSafe < minDiffSafeCe) { minDiffSafeCe = diffSafe; safeCe = item.strike; }

                    const diffBal = Math.abs(ce.delta - 0.25);
                    if (diffBal < minDiffBalCe) { minDiffBalCe = diffBal; balCe = item.strike; }
                }

                if (pe && pe.delta) {
                    const diffSafe = Math.abs(Math.abs(pe.delta) - 0.18);
                    if (diffSafe < minDiffSafePe) { minDiffSafePe = diffSafe; safePe = item.strike; }

                    const diffBal = Math.abs(Math.abs(pe.delta) - 0.25);
                    if (diffBal < minDiffBalPe) { minDiffBalPe = diffBal; balPe = item.strike; }
                }

                if (ce && pe && ce.ltp > 5 && pe.ltp > 5) {
                    const diff = Math.abs(ce.ltp - pe.ltp);
                    if (diff < minEqDiff) { minEqDiff = diff; eqCe = item.strike; eqPe = item.strike; }
                }
            });

            return {
                atm: state.atmStrike,
                safeCe: safeCe || (state.atmStrike + (state.strikeStep * 4)),
                safePe: safePe || (state.atmStrike - (state.strikeStep * 4)),
                balCe: balCe || (state.atmStrike + (state.strikeStep * 2)),
                balPe: balPe || (state.atmStrike - (state.strikeStep * 2)),
                eqCe: eqCe || state.atmStrike,
                eqPe: eqPe || state.atmStrike
            };
        }

        // Render Option Chain Table & Suggestions
        function renderOptionChainTable() {
            const rec = getMarketRecommendations();
            const defaultQty = parseInt(ocDefaultQtyInput.value, 10) || state.lotSize;

            // Render Top Suggestions Bar
            recommendationsBar.innerHTML = `
                <button type="button" class="btn-quick-rec px-2.5 py-1 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/30 font-bold transition flex items-center gap-1 cursor-pointer" data-ce="${rec.atm}" data-pe="${rec.atm}" title="Maximum time decay at ATM">
                    🎯 ATM Straddle (${rec.atm})
                </button>
                <button type="button" class="btn-quick-rec px-2.5 py-1 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 border border-emerald-500/30 font-bold transition flex items-center gap-1 cursor-pointer" data-ce="${rec.safeCe}" data-pe="${rec.safePe}" title="High Probability (~85%) Safe Strangle">
                    🛡️ Recommended Safe (15Δ: ${rec.safePe} PE / ${rec.safeCe} CE)
                </button>
                <button type="button" class="btn-quick-rec px-2.5 py-1 rounded-lg bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 border border-cyan-500/30 font-bold transition flex items-center gap-1 cursor-pointer" data-ce="${rec.balCe}" data-pe="${rec.balPe}" title="Optimal Balanced Strangle">
                    ⚖️ Balanced (25Δ: ${rec.balPe} PE / ${rec.balCe} CE)
                </button>
            `;

            // Render Table Rows
            let rowsHtml = '';
            state.strikesData.forEach(item => {
                const strike = item.strike;
                const isAtm = item.is_atm;
                const isSafeCe = strike === rec.safeCe;
                const isSafePe = strike === rec.safePe;
                const isBalCe = strike === rec.balCe;
                const isBalPe = strike === rec.balPe;

                const ce = item.ce;
                const pe = item.pe;

                const ceLtp = ce && ce.instrument_key && livePrices[ce.instrument_key] !== undefined ? livePrices[ce.instrument_key] : (ce?.ltp || 0);
                const peLtp = pe && pe.instrument_key && livePrices[pe.instrument_key] !== undefined ? livePrices[pe.instrument_key] : (pe?.ltp || 0);

                const ceDelta = ce?.delta !== undefined ? ce.delta : 0.50;
                const peDelta = pe?.delta !== undefined ? pe.delta : -0.50;

                const ceOiChg = ce?.oi_change_pct || 0;
                const peOiChg = pe?.oi_change_pct || 0;

                const ceBuildup = ce?.buildup || 'Neutral';
                const peBuildup = pe?.buildup || 'Neutral';

                const addBtnColor = ocAction === 'SELL' ? 'bg-rose-600 hover:bg-rose-500 text-white' : 'bg-emerald-600 hover:bg-emerald-500 text-white';

                rowsHtml += `
                    <tr class="hover:bg-slate-800/50 transition ${isAtm ? 'bg-amber-950/20' : ''}">
                        {{-- 1. ADD CE BUTTON --}}
                        <td class="py-2 px-2 text-center bg-emerald-950/20">
                            <button type="button" 
                                class="btn-oc-add-ce px-2.5 py-1 rounded-lg font-bold text-[11px] ${addBtnColor} shadow transition cursor-pointer"
                                data-strike="${strike}"
                                data-key="${ce?.instrument_key || ''}"
                                data-price="${ceLtp}">
                                + Add
                            </button>
                        </td>

                        {{-- 2. CE LOTS --}}
                        <td class="py-2 px-2 text-center bg-emerald-950/10">
                            <input type="number" 
                                id="oc-lots-ce-${strike}"
                                value="${defaultQty}" 
                                step="${state.lotSize}" 
                                min="${state.lotSize}" 
                                class="oc-lots-input w-12 bg-slate-900 border border-slate-700 rounded px-1 py-0.5 text-center text-emerald-400 font-bold focus:outline-none">
                        </td>

                        {{-- 3. CE DELTA --}}
                        <td class="py-2 px-2 text-center bg-emerald-950/10 font-bold text-slate-300">
                            ${ceDelta.toFixed(2)}
                            ${isSafeCe ? '<div class="text-[9px] text-emerald-400 font-bold">15Δ</div>' : ''}
                            ${isBalCe ? '<div class="text-[9px] text-cyan-400 font-bold">25Δ</div>' : ''}
                        </td>

                        {{-- 4. CE PRICE & OI --}}
                        <td class="py-2 px-3 text-right bg-emerald-950/10">
                            <div class="font-extrabold text-emerald-300 text-sm">₹${ceLtp.toFixed(2)}</div>
                            <div class="text-[10px] text-slate-400">
                                <span class="${ceOiChg >= 0 ? 'text-emerald-400 font-bold' : 'text-rose-400 font-bold'}">${ceOiChg >= 0 ? '+' : ''}${ceOiChg}%</span>
                                <span class="text-slate-500">|</span>
                                <span>${ceBuildup}</span>
                            </div>
                        </td>

                        {{-- 5. STRIKE & CENTER RECOMMENDATIONS --}}
                        <td class="py-2 px-4 text-center bg-slate-900 font-sans border-x border-slate-800">
                            <div class="flex items-center justify-center gap-1.5">
                                <span class="font-extrabold font-mono text-sm text-white">${strike}</span>
                                ${isAtm ? '<span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">ATM</span>' : ''}
                            </div>
                            <div class="flex items-center justify-center gap-1 mt-0.5">
                                ${isSafeCe && isSafePe ? '<span class="text-[9px] font-bold text-emerald-400">🛡️ Rec Safe</span>' : ''}
                                ${isBalCe && isBalPe ? '<span class="text-[9px] font-bold text-cyan-400">⚖️ Balanced</span>' : ''}
                                <button type="button" class="btn-oc-add-both text-[10px] bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 px-1.5 py-0.2 rounded font-sans transition cursor-pointer" data-strike="${strike}" title="Add both CE & PE at ${strike}">
                                    + Both
                                </button>
                            </div>
                        </td>

                        {{-- 6. PE PRICE & OI --}}
                        <td class="py-2 px-3 text-left bg-rose-950/10">
                            <div class="font-extrabold text-rose-300 text-sm">₹${peLtp.toFixed(2)}</div>
                            <div class="text-[10px] text-slate-400">
                                <span class="${peOiChg >= 0 ? 'text-emerald-400 font-bold' : 'text-rose-400 font-bold'}">${peOiChg >= 0 ? '+' : ''}${peOiChg}%</span>
                                <span class="text-slate-500">|</span>
                                <span>${peBuildup}</span>
                            </div>
                        </td>

                        {{-- 7. PE DELTA --}}
                        <td class="py-2 px-2 text-center bg-rose-950/10 font-bold text-slate-300">
                            ${peDelta.toFixed(2)}
                            ${isSafePe ? '<div class="text-[9px] text-emerald-400 font-bold">15Δ</div>' : ''}
                            ${isBalPe ? '<div class="text-[9px] text-cyan-400 font-bold">25Δ</div>' : ''}
                        </td>

                        {{-- 8. PE LOTS --}}
                        <td class="py-2 px-2 text-center bg-rose-950/10">
                            <input type="number" 
                                id="oc-lots-pe-${strike}"
                                value="${defaultQty}" 
                                step="${state.lotSize}" 
                                min="${state.lotSize}" 
                                class="oc-lots-input w-12 bg-slate-900 border border-slate-700 rounded px-1 py-0.5 text-center text-emerald-400 font-bold focus:outline-none">
                        </td>

                        {{-- 9. ADD PE BUTTON --}}
                        <td class="py-2 px-2 text-center bg-rose-950/20">
                            <button type="button" 
                                class="btn-oc-add-pe px-2.5 py-1 rounded-lg font-bold text-[11px] ${addBtnColor} shadow transition cursor-pointer"
                                data-strike="${strike}"
                                data-key="${pe?.instrument_key || ''}"
                                data-price="${peLtp}">
                                + Add
                            </button>
                        </td>
                    </tr>
                `;
            });

            ocTableBody.innerHTML = rowsHtml;
        }

        // Add CE Click Handler
        ocTableBody.addEventListener('click', (e) => {
            const btnCe = e.target.closest('.btn-oc-add-ce');
            if (btnCe) {
                const strike = parseInt(btnCe.dataset.strike, 10);
                const lotsInp = document.getElementById(`oc-lots-ce-${strike}`);
                const qty = parseInt(lotsInp?.value, 10) || state.lotSize;
                const strikeObj = state.strikesData.find(s => s.strike === strike);
                const ce = strikeObj ? strikeObj.ce : null;
                const key = btnCe.dataset.key;
                const price = parseFloat(btnCe.dataset.price) || 50;

                activeBasket.push({
                    id: `${ocAction}_CE_${strike}_${Date.now()}`,
                    action: ocAction,
                    strike: strike,
                    type: 'CE',
                    qty: qty,
                    entryPrice: price,
                    instrumentKey: key || null,
                    oiChange: ce ? ce.oi_change_pct : 0,
                    buildup: ce ? ce.buildup : 'Option Chain'
                });

                if (key && !state.allInstrumentKeys.includes(key)) {
                    state.allInstrumentKeys.push(key);
                    subscribeKeys();
                }

                saveActiveBasket();
                btnCe.textContent = '✓ Added';
                btnCe.className = 'px-2.5 py-1 rounded-lg font-bold text-[11px] bg-emerald-500 text-slate-950 shadow transition';
                setTimeout(() => {
                    btnCe.textContent = '+ Add';
                    const addBtnColor = ocAction === 'SELL' ? 'bg-rose-600 hover:bg-rose-500 text-white' : 'bg-emerald-600 hover:bg-emerald-500 text-white';
                    btnCe.className = `btn-oc-add-ce px-2.5 py-1 rounded-lg font-bold text-[11px] ${addBtnColor} shadow transition cursor-pointer`;
                }, 1200);
            }

            const btnPe = e.target.closest('.btn-oc-add-pe');
            if (btnPe) {
                const strike = parseInt(btnPe.dataset.strike, 10);
                const lotsInp = document.getElementById(`oc-lots-pe-${strike}`);
                const qty = parseInt(lotsInp?.value, 10) || state.lotSize;
                const strikeObj = state.strikesData.find(s => s.strike === strike);
                const pe = strikeObj ? strikeObj.pe : null;
                const key = btnPe.dataset.key;
                const price = parseFloat(btnPe.dataset.price) || 50;

                activeBasket.push({
                    id: `${ocAction}_PE_${strike}_${Date.now()}`,
                    action: ocAction,
                    strike: strike,
                    type: 'PE',
                    qty: qty,
                    entryPrice: price,
                    instrumentKey: key || null,
                    oiChange: pe ? pe.oi_change_pct : 0,
                    buildup: pe ? pe.buildup : 'Option Chain'
                });

                if (key && !state.allInstrumentKeys.includes(key)) {
                    state.allInstrumentKeys.push(key);
                    subscribeKeys();
                }

                saveActiveBasket();
                btnPe.textContent = '✓ Added';
                btnPe.className = 'px-2.5 py-1 rounded-lg font-bold text-[11px] bg-emerald-500 text-slate-950 shadow transition';
                setTimeout(() => {
                    btnPe.textContent = '+ Add';
                    const addBtnColor = ocAction === 'SELL' ? 'bg-rose-600 hover:bg-rose-500 text-white' : 'bg-emerald-600 hover:bg-emerald-500 text-white';
                    btnPe.className = `btn-oc-add-pe px-2.5 py-1 rounded-lg font-bold text-[11px] ${addBtnColor} shadow transition cursor-pointer`;
                }, 1200);
            }

            const btnBoth = e.target.closest('.btn-oc-add-both');
            if (btnBoth) {
                const strike = parseInt(btnBoth.dataset.strike, 10);
                const lotsInp = document.getElementById(`oc-lots-ce-${strike}`);
                const qty = parseInt(lotsInp?.value, 10) || state.lotSize;
                const strikeObj = state.strikesData.find(s => s.strike === strike);
                const ce = strikeObj?.ce;
                const pe = strikeObj?.pe;
                const ceLtp = ce && ce.instrument_key && livePrices[ce.instrument_key] !== undefined ? livePrices[ce.instrument_key] : (ce?.ltp || 50);
                const peLtp = pe && pe.instrument_key && livePrices[pe.instrument_key] !== undefined ? livePrices[pe.instrument_key] : (pe?.ltp || 50);

                activeBasket.push({
                    id: `${ocAction}_CE_${strike}_${Date.now()}`,
                    action: ocAction,
                    strike: strike,
                    type: 'CE',
                    qty: qty,
                    entryPrice: ceLtp,
                    instrumentKey: ce?.instrument_key || null,
                    oiChange: ce ? ce.oi_change_pct : 0,
                    buildup: ce ? ce.buildup : 'Option Chain'
                });

                activeBasket.push({
                    id: `${ocAction}_PE_${strike}_${Date.now() + 1}`,
                    action: ocAction,
                    strike: strike,
                    type: 'PE',
                    qty: qty,
                    entryPrice: peLtp,
                    instrumentKey: pe?.instrument_key || null,
                    oiChange: pe ? pe.oi_change_pct : 0,
                    buildup: pe ? pe.buildup : 'Option Chain'
                });

                if (ce?.instrument_key && !state.allInstrumentKeys.includes(ce.instrument_key)) state.allInstrumentKeys.push(ce.instrument_key);
                if (pe?.instrument_key && !state.allInstrumentKeys.includes(pe.instrument_key)) state.allInstrumentKeys.push(pe.instrument_key);
                subscribeKeys();

                saveActiveBasket();
                btnBoth.textContent = '✓ Added Both';
                btnBoth.className = 'text-[10px] bg-emerald-500 text-slate-950 font-bold px-1.5 py-0.2 rounded font-sans transition';
                setTimeout(() => {
                    btnBoth.textContent = '+ Both';
                    btnBoth.className = 'btn-oc-add-both text-[10px] bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 px-1.5 py-0.2 rounded font-sans transition cursor-pointer';
                }, 1200);
            }
        });

        // Quick Suggestions Click Handler
        recommendationsBar.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-quick-rec');
            if (btn) {
                const ceStrike = parseInt(btn.dataset.ce, 10);
                const peStrike = parseInt(btn.dataset.pe, 10);
                const qty = parseInt(ocDefaultQtyInput.value, 10) || state.lotSize;

                const ceObj = state.strikesData.find(s => s.strike === ceStrike)?.ce;
                const peObj = state.strikesData.find(s => s.strike === peStrike)?.pe;

                const ceLtp = ceObj && ceObj.instrument_key && livePrices[ceObj.instrument_key] !== undefined ? livePrices[ceObj.instrument_key] : (ceObj?.ltp || 50);
                const peLtp = peObj && peObj.instrument_key && livePrices[peObj.instrument_key] !== undefined ? livePrices[peObj.instrument_key] : (peObj?.ltp || 50);

                activeBasket.push({
                    id: `${ocAction}_CE_${ceStrike}_${Date.now()}`,
                    action: ocAction,
                    strike: ceStrike,
                    type: 'CE',
                    qty: qty,
                    entryPrice: ceLtp,
                    instrumentKey: ceObj?.instrument_key || null,
                    oiChange: ceObj ? ceObj.oi_change_pct : 0,
                    buildup: ceObj ? ceObj.buildup : 'Market Suggestion'
                });

                activeBasket.push({
                    id: `${ocAction}_PE_${peStrike}_${Date.now() + 1}`,
                    action: ocAction,
                    strike: peStrike,
                    type: 'PE',
                    qty: qty,
                    entryPrice: peLtp,
                    instrumentKey: peObj?.instrument_key || null,
                    oiChange: peObj ? peObj.oi_change_pct : 0,
                    buildup: peObj ? peObj.buildup : 'Market Suggestion'
                });

                if (ceObj?.instrument_key && !state.allInstrumentKeys.includes(ceObj.instrument_key)) state.allInstrumentKeys.push(ceObj.instrument_key);
                if (peObj?.instrument_key && !state.allInstrumentKeys.includes(peObj.instrument_key)) state.allInstrumentKeys.push(peObj.instrument_key);
                subscribeKeys();

                saveActiveBasket();
                addModal.classList.add('hidden');
                showToast('Strategy Deployed', `Added ${peStrike} PE & ${ceStrike} CE (${qty} shares each)!`, 'success');
            }
        });

        // Scenario Slider & Stress Tester
        const simSlider = document.getElementById('sim-spot-slider');
        const simLabel = document.getElementById('sim-spot-label');
        const simPnl = document.getElementById('sim-pnl-label');

        function updateSimulation(val) {
            if (!simSlider) return;
            simSlider.value = val;
            if (simLabel) simLabel.textContent = Math.round(val).toLocaleString('en-IN');

            let pnl = 0;
            activeBasket.forEach(leg => {
                let intr = leg.type === 'CE' ? Math.max(0, val - leg.strike) : Math.max(0, leg.strike - val);
                pnl += leg.action === 'SELL' ? (leg.entryPrice - intr) * leg.qty : (intr - leg.entryPrice) * leg.qty;
            });
            if (simPnl) {
                simPnl.textContent = `P&L: ${pnl >= 0 ? '+' : ''}₹${Math.round(pnl).toLocaleString('en-IN')}`;
                simPnl.className = `font-mono font-extrabold px-2 py-0.5 rounded text-xs border ${pnl >= 0 ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border-rose-500/30'}`;
            }

            renderPayoffChart(val);
        }

        if (simSlider) {
            simSlider.addEventListener('input', (e) => {
                updateSimulation(parseFloat(e.target.value));
            });
        }

        const btnResetSim = document.getElementById('btn-reset-sim');
        if (btnResetSim) {
            btnResetSim.addEventListener('click', () => {
                if (simSlider) simSlider.value = liveSpot;
                if (simLabel) simLabel.textContent = Math.round(liveSpot).toLocaleString('en-IN');
                if (simPnl) {
                    simPnl.textContent = 'P&L: ₹0';
                    simPnl.className = 'font-mono font-extrabold px-2 py-0.5 rounded text-xs bg-slate-800 text-slate-300 border border-slate-700';
                }
                renderPayoffChart(null);
            });
        }

        const btnSimMinus1 = document.getElementById('btn-sim-minus-1');
        if (btnSimMinus1) {
            btnSimMinus1.addEventListener('click', () => {
                updateSimulation(Math.round(liveSpot * 0.99));
            });
        }

        const btnSimPlus1 = document.getElementById('btn-sim-plus-1');
        if (btnSimPlus1) {
            btnSimPlus1.addEventListener('click', () => {
                updateSimulation(Math.round(liveSpot * 1.01));
            });
        }

        // Iron Fly Hedge Quick Add
        document.getElementById('btn-convert-ironfly').addEventListener('click', () => {
            const wingDistance = state.strikeStep * 4;
            const buyCe = state.atmStrike + wingDistance;
            const buyPe = state.atmStrike - wingDistance;

            const ceObj = state.strikesData.find(s => s.strike === buyCe)?.ce;
            const peObj = state.strikesData.find(s => s.strike === buyPe)?.pe;

            const cePrice = ceObj && ceObj.ltp > 0 ? ceObj.ltp : (livePrices[ceObj?.instrument_key] || 15);
            const pePrice = peObj && peObj.ltp > 0 ? peObj.ltp : (livePrices[peObj?.instrument_key] || 15);

            activeBasket.push({
                id: 'BUY_CE_' + Date.now(),
                action: 'BUY',
                strike: buyCe,
                type: 'CE',
                qty: state.lotSize,
                entryPrice: cePrice,
                instrumentKey: ceObj?.instrument_key || null,
                oiChange: ceObj?.oi_change_pct || 0,
                buildup: 'Hedge Wing'
            });

            activeBasket.push({
                id: 'BUY_PE_' + (Date.now() + 1),
                action: 'BUY',
                strike: buyPe,
                type: 'PE',
                qty: state.lotSize,
                entryPrice: pePrice,
                instrumentKey: peObj?.instrument_key || null,
                oiChange: peObj?.oi_change_pct || 0,
                buildup: 'Hedge Wing'
            });

            if (ceObj?.instrument_key && !state.allInstrumentKeys.includes(ceObj.instrument_key)) {
                state.allInstrumentKeys.push(ceObj.instrument_key);
            }
            if (peObj?.instrument_key && !state.allInstrumentKeys.includes(peObj.instrument_key)) {
                state.allInstrumentKeys.push(peObj.instrument_key);
            }
            subscribeKeys();

            saveActiveBasket(true);
            showToast('Hedge Wings Added', `Added ${buyPe} PE (@ ₹${pePrice.toFixed(1)}) & ${buyCe} CE (@ ₹${cePrice.toFixed(1)}) protective wings.\nTail risk capped (Iron Fly).`, 'success');
        });

        // Filter Selectors
        document.getElementById('filter-symbol').addEventListener('change', (e) => {
            window.location.href = `/delta-neutral?symbol=${e.target.value}`;
        });

        document.getElementById('filter-expiry').addEventListener('change', (e) => {
            const symbol = document.getElementById('filter-symbol').value;
            window.location.href = `/delta-neutral?symbol=${symbol}&expiry=${e.target.value}`;
        });

        document.getElementById('btn-refresh-data').addEventListener('click', () => {
            window.location.reload();
        });

        document.getElementById('btn-atm-minus').addEventListener('click', () => {
            const input = document.getElementById('filter-atm');
            input.value = parseInt(input.value, 10) - state.strikeStep;
            const symbol = document.getElementById('filter-symbol').value;
            const expiry = document.getElementById('filter-expiry').value;
            window.location.href = `/delta-neutral?symbol=${symbol}&expiry=${expiry}&atm=${input.value}`;
        });

        document.getElementById('btn-atm-plus').addEventListener('click', () => {
            const input = document.getElementById('filter-atm');
            input.value = parseInt(input.value, 10) + state.strikeStep;
            const symbol = document.getElementById('filter-symbol').value;
            const expiry = document.getElementById('filter-expiry').value;
            window.location.href = `/delta-neutral?symbol=${symbol}&expiry=${expiry}&atm=${input.value}`;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initApp);
    } else {
        initApp();
    }

})();
</script>
@endpush
@endsection
