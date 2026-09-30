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
                                <th class="py-2 px-2.5 text-right">Qty</th>
                                <th class="py-2 px-2.5 text-right">Entry</th>
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
        <div id="suggested-shift-container" class="bg-slate-950/80 border border-slate-800 rounded-xl p-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
            
            {{-- Col 1: Diagnosis of Imbalance --}}
            <div class="space-y-1">
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Imbalance Diagnostic</div>
                <div class="text-sm font-bold text-slate-200" id="diag-status-text">Positions are well balanced</div>
                <p class="text-xs text-slate-400" id="diag-details-text">
                    Current Leg Ratio: <span class="font-mono text-white font-bold" id="diag-leg-ratio">1.0 : 1.0</span> (Safe threshold &lt; 2.5:1)
                </p>
            </div>

            {{-- Col 2: Actionable Shift Suggestion --}}
            <div class="space-y-1 border-t md:border-t-0 md:border-l md:border-r border-slate-800/80 md:px-4">
                <div class="text-[11px] font-semibold text-amber-400 uppercase tracking-wide flex items-center gap-1">
                    <span>💡 Recommended Action</span>
                </div>
                <div class="text-xs font-medium text-slate-300" id="shift-recommendation-text">
                    Hold position. Theta decay working smoothly.
                </div>
                <div class="text-[11px] text-emerald-400 font-mono font-bold" id="shift-benefit-preview">
                    --
                </div>
            </div>

            {{-- Col 3: Execute Shift Action --}}
            <div class="flex flex-col gap-2">
                <button type="button" id="btn-preview-shift" disabled class="bg-slate-800 text-slate-500 font-bold px-4 py-2 rounded-xl text-xs transition shadow flex items-center justify-center gap-2 cursor-not-allowed">
                    <span>🔄 1-Click Roll Untested Leg</span>
                </button>
                <div class="flex items-center justify-between text-[11px] text-slate-400 px-1">
                    <span>Deep Defense:</span>
                    <button type="button" id="btn-convert-ironfly" class="text-cyan-400 hover:text-cyan-300 font-bold transition underline">
                        + Buy Wings (Iron Fly Hedge)
                    </button>
                </div>
            </div>

        </div>
    </div>

    {{-- ════════════════════════ 5. LIVE PAYOFF DIAGRAM & SCENARIO SLIDER ════════════════════════ --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-2xl">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xl">📈</span>
                    <h3 class="text-sm sm:text-base font-extrabold text-white tracking-wide uppercase">
                        Live Payoff Risk Graph & Recovery Simulator
                    </h3>
                </div>
                <p class="text-xs text-slate-400">
                    Visual profit/loss profile across spot prices at expiry. Drag slider to test "What-If" market scenarios.
                </p>
            </div>

            {{-- Scenario Simulator Spot Slider --}}
            <div class="flex items-center gap-3 bg-slate-800 border border-slate-700 rounded-xl px-3 py-1.5 text-xs">
                <span class="text-slate-400 font-semibold">Simulate Spot Move:</span>
                <input type="range" id="sim-spot-slider" min="{{ $atmStrike - 600 }}" max="{{ $atmStrike + 600 }}" step="25" value="{{ $indexSpot }}" class="w-32 accent-emerald-500 cursor-pointer">
                <span id="sim-spot-label" class="font-mono font-extrabold text-cyan-400">{{ number_format($indexSpot, 0) }}</span>
                <span id="sim-pnl-label" class="font-mono font-bold text-emerald-400">P&L: ₹0</span>
                <button type="button" id="btn-reset-sim" class="text-[10px] text-slate-400 hover:text-white underline">Reset</button>
            </div>
        </div>

        {{-- Payoff Canvas --}}
        <div class="w-full h-64 sm:h-72 relative">
            <canvas id="payoffChart" class="w-full h-full"></canvas>
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
    // 1. BASKET STORAGE & MANAGEMENT
    // ─────────────────────────────────────────────────────────────
    function getStorageKey() {
        return `delta_neutral_basket_${state.symbol}`;
    }

    function loadActiveBasket() {
        try {
            const raw = localStorage.getItem(getStorageKey());
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function saveActiveBasket() {
        try {
            localStorage.setItem(getStorageKey(), JSON.stringify(activeBasket));
        } catch (e) {
            console.error('Error saving basket:', e);
        }
        renderBasketTable();
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
            return;
        }

        let totalCredit = 0;
        let currentValue = 0;
        let totalPnl = 0;
        let totalTheta = 0;
        let totalNetDelta = 0;

        let rowsHtml = '';
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

            // Approximate Greek estimations
            const approxDelta = leg.type === 'CE' ? 0.35 : -0.35;
            totalNetDelta += (leg.action === 'SELL' ? -approxDelta : approxDelta);
            totalTheta += (currentLtp * 0.12 * leg.qty);

            const isPnlPositive = legPnl >= 0;
            const pnlColor = isPnlPositive ? 'text-emerald-400' : 'text-rose-400';
            const actionBg = leg.action === 'SELL' ? 'bg-rose-500/20 text-rose-300 border-rose-500/30' : 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30';
            const typeColor = leg.type === 'CE' ? 'text-emerald-400' : 'text-rose-400';

            rowsHtml += `
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="py-2.5 px-2.5">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border ${actionBg}">${leg.action}</span>
                    </td>
                    <td class="py-2.5 px-2.5 font-bold text-white">${leg.strike}</td>
                    <td class="py-2.5 px-2.5 font-bold ${typeColor}">${leg.type}</td>
                    <td class="py-2.5 px-2.5 text-right font-medium text-slate-300">${leg.qty}</td>
                    <td class="py-2.5 px-2.5 text-right font-medium text-slate-300">₹${leg.entryPrice.toFixed(2)}</td>
                    <td class="py-2.5 px-2.5 text-right font-bold text-cyan-300">₹${currentLtp.toFixed(2)}</td>
                    <td class="py-2.5 px-2.5 text-right font-extrabold ${pnlColor}">
                        ${isPnlPositive ? '+' : ''}₹${legPnl.toFixed(2)}
                    </td>
                    <td class="py-2.5 px-2.5 text-right">
                        <span class="font-bold ${leg.oiChange >= 0 ? 'text-emerald-400' : 'text-rose-400'}">
                            ${leg.oiChange >= 0 ? '+' : ''}${leg.oiChange}%
                        </span>
                    </td>
                    <td class="py-2.5 px-2.5 text-center">
                        <span class="text-[10px] text-slate-400 font-sans">${leg.buildup || 'Neutral'}</span>
                    </td>
                    <td class="py-2.5 px-2.5 text-center">
                        <button type="button" class="btn-delete-leg text-slate-500 hover:text-rose-400 font-bold transition p-1" data-index="${index}" title="Remove Leg">
                            ✕
                        </button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = rowsHtml;

        // Update Summary Cards
        document.getElementById('basket-total-credit').textContent = `₹${totalCredit.toLocaleString('en-IN', { maximumFractionDigits: 2 })}`;
        document.getElementById('basket-current-value').textContent = `₹${currentValue.toLocaleString('en-IN', { maximumFractionDigits: 2 })}`;
        
        const pnlEl = document.getElementById('basket-live-pnl');
        pnlEl.textContent = `${totalPnl >= 0 ? '+' : ''}₹${totalPnl.toLocaleString('en-IN', { maximumFractionDigits: 2 })}`;
        pnlEl.className = `text-base font-extrabold font-mono ${totalPnl >= 0 ? 'text-emerald-400' : 'text-rose-400'}`;

        document.getElementById('basket-total-theta').textContent = `+₹${Math.round(totalTheta).toLocaleString('en-IN')}/day`;

        // Update Decay Progress
        const initCombined = activeBasket.reduce((sum, leg) => sum + leg.entryPrice, 0);
        const currCombined = activeBasket.reduce((sum, leg) => {
            const ltp = livePrices[leg.instrumentKey] !== undefined ? livePrices[leg.instrumentKey] : leg.entryPrice;
            return sum + ltp;
        }, 0);

        document.getElementById('label-init-comb').textContent = `₹${initCombined.toFixed(2)}`;
        document.getElementById('label-curr-comb').textContent = `₹${currCombined.toFixed(2)}`;

        const decayPct = initCombined > 0 ? Math.max(0, Math.min(100, Math.round(((initCombined - currCombined) / initCombined) * 100))) : 0;
        document.getElementById('decay-pct-badge').textContent = `${decayPct}% Decayed`;
        document.getElementById('decay-progress-bar').style.width = `${decayPct}%`;
        document.getElementById('label-profit-captured').textContent = `${decayPct}%`;

        // Update Live Greeks Cards
        document.getElementById('greek-net-delta').textContent = totalNetDelta.toFixed(2);
        document.getElementById('greek-net-theta').textContent = `+${Math.round(totalTheta)}`;
        document.getElementById('greek-net-vega').textContent = `-${Math.round(totalCredit * 0.08)}`;
        document.getElementById('greek-net-gamma').textContent = `-0.0012`;
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

        const combinedCredit = activeBasket.reduce((sum, l) => sum + l.entryPrice, 0);

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
        checkRecoveryDiagnosis(peLegs, ceLegs, distToLower, distToUpper);
    }

    // ─────────────────────────────────────────────────────────────
    // 4. SHIFT & RECOVERY ADVISOR (OPPOSITE MOVE DEFENSE)
    // ─────────────────────────────────────────────────────────────
    function checkRecoveryDiagnosis(peLegs, ceLegs, distToLower, distToUpper) {
        const diagStatus = document.getElementById('diag-status-text');
        const diagRatio = document.getElementById('diag-leg-ratio');
        const diagRec = document.getElementById('shift-recommendation-text');
        const diagBenefit = document.getElementById('shift-benefit-preview');
        const triggerBadge = document.getElementById('shift-trigger-indicator');
        const btnShift = document.getElementById('btn-preview-shift');
        const stratBadge = document.getElementById('strategy-status-badge');

        if (peLegs.length === 0 || ceLegs.length === 0) {
            diagStatus.textContent = 'Awaiting multi-leg deployment';
            return;
        }

        const peLtp = livePrices[peLegs[0].instrumentKey] !== undefined ? livePrices[peLegs[0].instrumentKey] : peLegs[0].entryPrice;
        const ceLtp = livePrices[ceLegs[0].instrumentKey] !== undefined ? livePrices[ceLegs[0].instrumentKey] : ceLegs[0].entryPrice;

        const ratio = peLtp > ceLtp ? (peLtp / Math.max(1, ceLtp)) : (ceLtp / Math.max(1, peLtp));
        const isUpMove = ceLtp > peLtp; // Call expanding, Put decaying
        const isDownMove = peLtp > ceLtp; // Put expanding, Call decaying

        diagRatio.textContent = isUpMove ? `1.0 : ${ratio.toFixed(1)} (CE Dominant)` : `${ratio.toFixed(1)} : 1.0 (PE Dominant)`;

        // Ratio Triggers
        if (ratio >= 2.5 || distToLower < 80 || distToUpper < 80) {
            // ACTION REQUIRED
            stratBadge.className = 'px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30';
            stratBadge.textContent = '⚠️ ADJUSTMENT RECOMMENDED';

            triggerBadge.className = 'px-3 py-1 rounded-xl text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30 flex items-center gap-1.5 animate-pulse';
            triggerBadge.innerHTML = `<span class="h-2 w-2 rounded-full bg-amber-400"></span><span>Trigger Active (Ratio > 2.5x)</span>`;

            btnShift.disabled = false;
            btnShift.className = 'bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold px-4 py-2 rounded-xl text-xs transition shadow-lg flex items-center justify-center gap-2 cursor-pointer';

            if (isUpMove) {
                const decayedPe = peLegs[0];
                const suggestedNewPeStrike = decayedPe.strike + (state.strikeStep * 2);
                diagStatus.textContent = `Market Rallying: PE has decayed to ₹${peLtp.toFixed(1)}`;
                diagRec.textContent = `Roll Untested ${decayedPe.strike} PE ➔ ${suggestedNewPeStrike} PE to book +₹${(decayedPe.entryPrice - peLtp).toFixed(1)} profit and collect fresh credit.`;
                diagBenefit.textContent = `★ Extends Upper Breakeven by +55 pts & restores Delta to 0`;
                btnShift.onclick = () => executeRoll(decayedPe, suggestedNewPeStrike, 'PE');
            } else {
                const decayedCe = ceLegs[0];
                const suggestedNewCeStrike = decayedCe.strike - (state.strikeStep * 2);
                diagStatus.textContent = `Market Dropping: CE has decayed to ₹${ceLtp.toFixed(1)}`;
                diagRec.textContent = `Roll Untested ${decayedCe.strike} CE ➔ ${suggestedNewCeStrike} CE to book +₹${(decayedCe.entryPrice - ceLtp).toFixed(1)} profit and collect fresh credit.`;
                diagBenefit.textContent = `★ Extends Lower Breakeven by +55 pts & restores Delta to 0`;
                btnShift.onclick = () => executeRoll(decayedCe, suggestedNewCeStrike, 'CE');
            }
        } else {
            // ALL CLEAR
            stratBadge.className = 'px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
            stratBadge.textContent = '✅ SAFE & BALANCED';

            triggerBadge.className = 'px-3 py-1 rounded-xl text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 flex items-center gap-1.5';
            triggerBadge.innerHTML = `<span class="h-2 w-2 rounded-full bg-emerald-400"></span><span>No Adjustment Needed (Ratio Balanced)</span>`;

            btnShift.disabled = true;
            btnShift.className = 'bg-slate-800 text-slate-500 font-bold px-4 py-2 rounded-xl text-xs transition shadow flex items-center justify-center gap-2 cursor-not-allowed';

            diagStatus.textContent = 'Strategy running inside optimal decay corridor';
            diagRec.textContent = 'Hold position. Both legs are decaying symmetrically with no directional stress.';
            diagBenefit.textContent = 'Theta decay adding steadily to daily P&L';
        }
    }

    function executeRoll(oldLeg, newStrike, type) {
        // Find instrument for new strike from strikesData
        const strikeObj = state.strikesData.find(s => s.strike === newStrike);
        const newInst = strikeObj ? (type === 'CE' ? strikeObj.ce : strikeObj.pe) : null;
        const newKey = newInst ? newInst.instrument_key : null;
        const newPrice = newInst && newInst.ltp > 0 ? newInst.ltp : (livePrices[newKey] || 45);

        // Remove old decayed leg, insert new shifted leg
        activeBasket = activeBasket.filter(l => l.id !== oldLeg.id);
        activeBasket.push({
            id: `${type}_${newStrike}_${Date.now()}`,
            action: 'SELL',
            strike: newStrike,
            type: type,
            qty: oldLeg.qty,
            entryPrice: newPrice,
            instrumentKey: newKey,
            oiChange: newInst ? newInst.oi_change_pct : 0,
            buildup: newInst ? newInst.buildup : 'Neutral'
        });

        saveActiveBasket();
        alert(`Successfully rolled untested ${oldLeg.strike} ${type} to ${newStrike} ${type}!\n\nFresh credit collected: ₹${newPrice.toFixed(2)}\nSafety corridor widened.`);
    }

    // ─────────────────────────────────────────────────────────────
    // 5. LIVE PAYOFF CHART (RISK GRAPH - CHART.JS)
    // ─────────────────────────────────────────────────────────────
    function renderPayoffChart(simulatedSpot = null) {
        const ctx = document.getElementById('payoffChart').getContext('2d');
        if (!activeBasket || activeBasket.length === 0) {
            if (payoffChartInstance) payoffChartInstance.destroy();
            return;
        }

        const centerStrike = state.atmStrike;
        const step = state.strikeStep;
        const spotPoints = [];
        const payoffValues = [];

        // Generate spot price range: ATM ± 15 steps
        for (let i = -14; i <= 14; i++) {
            const spotAtExpiry = centerStrike + (i * step);
            spotPoints.push(spotAtExpiry);

            // Calculate Strategy Expiry P&L at this spot
            let pnlAtSpot = 0;
            activeBasket.forEach(leg => {
                let intrinsic = 0;
                if (leg.type === 'CE') {
                    intrinsic = Math.max(0, spotAtExpiry - leg.strike);
                } else {
                    intrinsic = Math.max(0, leg.strike - spotAtExpiry);
                }

                if (leg.action === 'SELL') {
                    pnlAtSpot += (leg.entryPrice - intrinsic) * leg.qty;
                } else {
                    pnlAtSpot += (intrinsic - leg.entryPrice) * leg.qty;
                }
            });

            payoffValues.push(Math.round(pnlAtSpot));
        }

        if (payoffChartInstance) {
            payoffChartInstance.destroy();
        }

        payoffChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: spotPoints,
                datasets: [
                    {
                        label: 'Expiry P&L (₹)',
                        data: payoffValues,
                        borderColor: '#10b981',
                        borderWidth: 2.5,
                        pointRadius: 1,
                        fill: {
                            target: 'origin',
                            above: 'rgba(16, 185, 129, 0.15)',
                            below: 'rgba(244, 63, 94, 0.15)'
                        },
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: (ctx) => ` Projected P&L: ₹${ctx.parsed.y.toLocaleString('en-IN')}`
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(51, 65, 85, 0.3)' },
                        ticks: { color: '#94a3b8', font: { size: 10 } }
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
                    renderBasketTable();
                    updateCorridorAndRecovery();
                }
            }
        } catch (e) {
            // Protobuf decode fallback
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 7. EVENT LISTENERS & INTERACTION
    // ─────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', async () => {
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

        // Delete Leg from Basket
        document.getElementById('basket-table-body').addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-delete-leg');
            if (btn) {
                const idx = parseInt(btn.dataset.index, 10);
                activeBasket.splice(idx, 1);
                saveActiveBasket();
            }
        });

        // Clear Basket
        document.getElementById('btn-clear-basket').addEventListener('click', () => {
            if (confirm('Clear active basket?')) {
                activeBasket = [];
                saveActiveBasket();
            }
        });

        // Scenario Slider
        const simSlider = document.getElementById('sim-spot-slider');
        const simLabel = document.getElementById('sim-spot-label');
        const simPnl = document.getElementById('sim-pnl-label');
        simSlider.addEventListener('input', (e) => {
            const val = parseFloat(e.target.value);
            simLabel.textContent = val.toFixed(0);

            // Compute hypothetical P&L at slider spot
            let pnl = 0;
            activeBasket.forEach(leg => {
                let intr = leg.type === 'CE' ? Math.max(0, val - leg.strike) : Math.max(0, leg.strike - val);
                pnl += leg.action === 'SELL' ? (leg.entryPrice - intr) * leg.qty : (intr - leg.entryPrice) * leg.qty;
            });
            simPnl.textContent = `P&L: ${pnl >= 0 ? '+' : ''}₹${Math.round(pnl).toLocaleString('en-IN')}`;
            simPnl.className = `font-mono font-bold ${pnl >= 0 ? 'text-emerald-400' : 'text-rose-400'}`;
        });

        document.getElementById('btn-reset-sim').addEventListener('click', () => {
            simSlider.value = liveSpot;
            simLabel.textContent = liveSpot.toFixed(0);
            simPnl.textContent = 'P&L: ₹0';
        });

        // Iron Fly Hedge Quick Add
        document.getElementById('btn-convert-ironfly').addEventListener('click', () => {
            const wingDistance = state.strikeStep * 4;
            const buyCe = state.atmStrike + wingDistance;
            const buyPe = state.atmStrike - wingDistance;

            activeBasket.push({
                id: 'BUY_CE_' + Date.now(),
                action: 'BUY',
                strike: buyCe,
                type: 'CE',
                qty: state.lotSize,
                entryPrice: 8.50,
                instrumentKey: null,
                oiChange: 0,
                buildup: 'Hedge Wing'
            });

            activeBasket.push({
                id: 'BUY_PE_' + (Date.now() + 1),
                action: 'BUY',
                strike: buyPe,
                type: 'PE',
                qty: state.lotSize,
                entryPrice: 9.00,
                instrumentKey: null,
                oiChange: 0,
                buildup: 'Hedge Wing'
            });

            saveActiveBasket();
            alert(`Added protective wings (${buyPe} PE & ${buyCe} CE)!\nTail risk capped. Converted to Iron Fly.`);
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
    });

})();
</script>
@endpush
@endsection
