@extends('layouts.app')

@section('title', 'Institutional NIFTY Option Trader Desk (0Click Integrated)')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
<style>
    body {
        background-color: #080b11 !important;
        color: #f1f5f9;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }
    main {
        padding: 0 !important;
    }
    main > div.mt-4 {
        margin-top: 0 !important;
    }
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #0d131f;
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #1e293b;
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #334155;
    }
    .pulse-glow-green {
        box-shadow: 0 0 15px rgba(16, 185, 129, 0.4);
    }
    .pulse-glow-red {
        box-shadow: 0 0 15px rgba(239, 68, 68, 0.4);
    }
    .pulse-glow-cyan {
        box-shadow: 0 0 15px rgba(6, 182, 212, 0.4);
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/lightweight-charts@4.2.3/dist/lightweight-charts.standalone.production.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
@endpush

@section('content')
<div class="min-h-screen bg-[#080b11] text-slate-100 flex flex-col">
    <!-- Sub-Header: Institutional NIFTY Option Desk Toolbar -->
    <header class="bg-[#0d131f] border-b border-slate-800/80 px-4 py-2.5 shrink-0 select-none shadow-md">
        <div class="flex flex-wrap items-center justify-between gap-3">
            
            <!-- Left: Spot Badge, Price Action, Expiry & Date -->
            <div class="flex items-center flex-wrap gap-2.5 sm:gap-3">
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-700/80 shadow-inner">
                    <span class="flex h-2.5 w-2.5 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                    </span>
                    <span class="font-extrabold text-xs tracking-wider text-white">NIFTY 50 SPOT</span>
                    <span id="nifty-spot-price" class="font-mono font-bold text-sm text-cyan-300">--.--</span>
                    <span id="nifty-spot-diff" class="text-[11px] font-mono font-semibold text-slate-400">(0.00 pts)</span>
                </div>

                <!-- Date Picker -->
                <div class="flex items-center gap-1.5 bg-slate-900/90 border border-slate-700/80 px-2.5 py-1 rounded-lg">
                    <span class="text-xs text-slate-400">📅</span>
                    <select id="trade-date-select" class="bg-transparent text-xs font-semibold text-slate-200 focus:outline-none cursor-pointer">
                        @foreach($availableDates as $d)
                            <option value="{{ $d }}" {{ $d === $selectedDate ? 'selected' : '' }} class="bg-slate-900 text-slate-200">{{ $d }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Expiry Picker -->
                <div class="flex items-center gap-1.5 bg-slate-900/90 border border-slate-700/80 px-2.5 py-1 rounded-lg">
                    <span class="text-xs text-amber-400">⚡ Exp:</span>
                    <select id="trade-expiry-select" class="bg-transparent text-xs font-semibold text-amber-300 focus:outline-none cursor-pointer">
                        @foreach($expiries as $exp)
                            <option value="{{ $exp }}" {{ $exp === $selectedExpiry ? 'selected' : '' }} class="bg-slate-900 text-slate-200">{{ $exp }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Historical 5-Day Top 1 Dropdown -->
                <div class="relative inline-block text-left" id="historical-5d-dropdown-wrap">
                    <button id="historical-5d-btn" type="button" class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-900 border border-indigo-700/70 hover:bg-indigo-950/40 text-indigo-300 transition">
                        <span>🎯 5D Historical S/R</span>
                        <span class="text-[10px]">▾</span>
                    </button>
                    <div id="historical-5d-menu" class="hidden absolute left-0 mt-1.5 w-72 bg-[#0f172a] border border-slate-700 rounded-xl shadow-2xl p-2.5 z-50 text-xs">
                        <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-800 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                            <span>Historical Absorption Anchors</span>
                            <button id="toggle-all-5d-btn" class="text-indigo-400 hover:text-indigo-300">All ON/OFF</button>
                        </div>
                        <div id="historical-5d-list" class="space-y-1.5 max-h-56 overflow-y-auto custom-scrollbar">
                            <div class="text-slate-500 text-center py-2">Loading historical levels...</div>
                        </div>
                    </div>
                </div>

                <!-- Basis Mode Toggle (FUT vs SPOT) -->
                <button id="basis-calib-toggle-btn" type="button" class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-900 border border-amber-600/70 hover:bg-amber-950/40 text-amber-300 transition" title="Toggle between Raw Futures and Spot-Calibrated Order Flow Levels">
                    <span class="text-[11px]">⚖️</span>
                    <span>Basis: <b id="basis-calib-label" class="font-mono text-amber-400 font-extrabold">FUT</b></span>
                </button>
            </div>

            <!-- Right: Webhook Mode, Config & Execution Pill -->
            <div class="flex items-center gap-2.5">
                <!-- Live / Simulation Mode Toggle -->
                <div class="flex items-center bg-slate-900/90 p-0.5 rounded-lg border border-slate-700/80 text-xs">
                    <button id="mode-live-btn" type="button" class="px-2.5 py-1 rounded-md font-bold transition {{ env('ZEROCLICK_LIVE', true) ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-400 hover:text-white' }}">
                        🟢 0Click LIVE
                    </button>
                    <button id="mode-sim-btn" type="button" class="px-2.5 py-1 rounded-md font-bold transition {{ !env('ZEROCLICK_LIVE', true) ? 'bg-amber-600 text-white shadow-xs' : 'text-slate-400 hover:text-white' }}">
                        🧪 SIMULATION
                    </button>
                </div>

                <!-- Webhook Config Trigger Modal -->
                <button id="open-config-btn" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs text-slate-300 border border-slate-700 transition" title="Configure 0Click.in Webhook URL & Token">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
                    <span>Config</span>
                </button>

                <!-- Refresh Data Button -->
                <button id="refresh-desk-btn" class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition" title="Refresh Live Data">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Desk Grid: 8 Cols (Chart & Institutional Radar) + 4 Cols (0Click Execution Ticket) -->
    <main class="flex-1 p-3 grid grid-cols-1 lg:grid-cols-12 gap-3 overflow-hidden">
        
        <!-- LEFT COLUMN (Col 8): Live Chart + S/R Walls + Time Invalidation Strip -->
        <div class="lg:col-span-8 flex flex-col gap-3 min-h-[550px]">
            
            <!-- Top Strip: Timeframe Switcher, 4-Candle Invalidation Clock & Active S/R Summary -->
            <div class="bg-[#0f172a] rounded-xl border border-slate-800 p-2.5 flex flex-wrap items-center justify-between gap-2 shadow-xs">
                <!-- Timeframes -->
                <div class="flex items-center gap-1 bg-slate-900 p-1 rounded-lg border border-slate-800 text-xs">
                    <button type="button" class="chart-tf-btn px-2.5 py-1 rounded font-bold text-slate-400 hover:text-white transition" data-tf="1">1M</button>
                    <button type="button" class="chart-tf-btn px-2.5 py-1 rounded font-bold text-slate-400 hover:text-white transition" data-tf="3">3M</button>
                    <button type="button" class="chart-tf-btn px-2.5 py-1 rounded font-bold bg-cyan-600 text-white shadow-xs transition" data-tf="5">5M</button>
                    <button type="button" class="chart-tf-btn px-2.5 py-1 rounded font-bold text-slate-400 hover:text-white transition" data-tf="15">15M</button>
                </div>

                <!-- 4-Candle (20-Min) Time Invalidation Tracker -->
                <div id="time-invalidation-badge" class="flex items-center gap-2 px-3 py-1 rounded-lg bg-amber-950/40 border border-amber-600/50 text-amber-300 text-xs font-semibold">
                    <span class="animate-pulse">⏳</span>
                    <span>4-Candle Invalidation:</span>
                    <span id="candle-invalidation-status" class="font-mono font-bold text-white">4 Candles Active (20m window)</span>
                </div>

                <!-- Fast Order Flow Wall Presets (One-Click Bracket Loaders) -->
                <div class="flex items-center gap-1.5">
                    <button id="preset-bull-wall-btn" type="button" class="flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-950/70 hover:bg-emerald-900 border border-emerald-600/80 text-emerald-300 shadow-xs transition" title="Auto-stage Option Sell PE when defending Bid Wall">
                        <span>🛡️ Bullish Bid Wall</span>
                    </button>
                    <button id="preset-bear-wall-btn" type="button" class="flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-950/70 hover:bg-rose-900 border border-rose-600/80 text-rose-300 shadow-xs transition" title="Auto-stage Option Sell CE when defending Ask Wall">
                        <span>🛡️ Bearish Ask Wall</span>
                    </button>
                    <button id="preset-breakout-buy-btn" type="button" class="flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-fuchsia-950/70 hover:bg-fuchsia-900 border border-fuchsia-600/80 text-fuchsia-300 shadow-xs transition" title="Auto-stage Option Buy CE/PE on momentum blast">
                        <span>⚡ Momentum Buy</span>
                    </button>
                </div>
            </div>

            <!-- Main Interactive Chart Container -->
            <div class="relative flex-1 bg-[#090d16] rounded-xl border border-slate-800 p-2 overflow-hidden flex flex-col shadow-inner">
                <div id="chart-container" class="w-full flex-1 min-h-[440px]"></div>

                <!-- Floating Institutional Level Badges on Chart -->
                <div class="absolute top-4 left-4 z-10 flex flex-col gap-1.5 pointer-events-none">
                    <div id="td-ask-badge" class="hidden px-2 py-0.5 rounded bg-rose-950/90 border border-rose-600/80 text-[11px] font-mono font-bold text-rose-300 shadow-md">
                        TD Ask Wall: <span class="text-white" id="td-ask-val">--</span> (<span id="td-ask-vol">0</span>)
                    </div>
                    <div id="td-bid-badge" class="hidden px-2 py-0.5 rounded bg-emerald-950/90 border border-emerald-600/80 text-[11px] font-mono font-bold text-emerald-300 shadow-md">
                        TD Bid Wall: <span class="text-white" id="td-bid-val">--</span> (<span id="td-bid-vol">0</span>)
                    </div>
                    <div id="yd-ask-badge" class="hidden px-2 py-0.5 rounded bg-amber-950/90 border border-amber-600/80 text-[11px] font-mono font-bold text-amber-300 shadow-md">
                        YD Ask Wall: <span class="text-white" id="yd-ask-val">--</span>
                    </div>
                    <div id="yd-bid-badge" class="hidden px-2 py-0.5 rounded bg-cyan-950/90 border border-cyan-600/80 text-[11px] font-mono font-bold text-cyan-300 shadow-md">
                        YD Bid Wall: <span class="text-white" id="yd-bid-val">--</span>
                    </div>
                </div>

                <!-- Floating Bracket Lines Legend -->
                <div class="absolute bottom-4 left-4 z-10 flex items-center gap-3 bg-slate-900/90 border border-slate-700/80 px-3 py-1.5 rounded-lg text-[11px] font-mono">
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-0.5 bg-emerald-400"></span>
                        <span class="text-slate-300">Spot Entry: <b id="legend-entry" class="text-emerald-300">--</b></span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-0.5 bg-rose-500"></span>
                        <span class="text-slate-300">Spot SL (5 pts): <b id="legend-sl" class="text-rose-400">--</b></span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-0.5 bg-cyan-400"></span>
                        <span class="text-slate-300">Target 1: <b id="legend-tp1" class="text-cyan-300">--</b></span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-0.5 bg-blue-400"></span>
                        <span class="text-slate-300">Target 2: <b id="legend-tp2" class="text-blue-300">--</b></span>
                    </div>
                </div>

                <!-- Chart Loading Overlay -->
                <div id="chart-loading" class="absolute inset-0 bg-[#090d16]/80 backdrop-blur-xs flex items-center justify-center z-20 transition-opacity">
                    <div class="flex flex-col items-center gap-2">
                        <svg class="animate-spin h-7 w-7 text-cyan-500" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        <span class="text-xs text-slate-400 font-mono tracking-wider">Synchronizing Candlesticks &amp; Depth...</span>
                    </div>
                </div>
            </div>

            <!-- Bottom Live Flow Strip: 30-Depth Totals & Imbalance -->
            <div class="bg-[#0f172a] rounded-xl border border-slate-800 px-3 py-2 flex items-center justify-between text-xs font-mono shadow-xs">
                <div class="flex items-center gap-4">
                    <div>
                        <span class="text-slate-400 text-[10px] block">TOTAL BIDS</span>
                        <span id="total-bids-val" class="font-bold text-emerald-400 text-sm">--</span>
                    </div>
                    <div class="h-6 w-px bg-slate-800"></div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">TOTAL ASKS</span>
                        <span id="total-asks-val" class="font-bold text-rose-400 text-sm">--</span>
                    </div>
                    <div class="h-6 w-px bg-slate-800"></div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">DEPTH RATIO (B/A)</span>
                        <span id="depth-ratio-val" class="font-bold text-cyan-300 text-sm">--</span>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-[11px] text-slate-400">INSTITUTIONAL BIAS:</span>
                    <span id="flow-bias-pill" class="px-2.5 py-0.5 rounded font-bold text-xs bg-slate-800 text-slate-300 border border-slate-700">NEUTRAL</span>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN (Col 4): 0Click.in Option Execution Ticket -->
        <div class="lg:col-span-4 flex flex-col gap-3">
            
            <div class="bg-[#0f172a] rounded-xl border border-slate-800 p-4 shadow-xl flex flex-col gap-4">
                
                <!-- Ticket Header & Trading Style Selector -->
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-base font-extrabold text-white">0Click Order Ticket</span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-400 bg-emerald-950/80 px-1.5 py-0.5 rounded border border-emerald-700/60">INSTITUTIONAL</span>
                    </div>
                    <span id="ticket-mode-pill" class="text-[11px] font-bold font-mono px-2 py-0.5 rounded bg-emerald-900/60 text-emerald-300 border border-emerald-600/60">LIVE</span>
                </div>

                <!-- 1. Strategy Style Selector: 80% Option Selling vs Option Buying -->
                <div class="grid grid-cols-2 gap-2 bg-slate-900/90 p-1 rounded-xl border border-slate-800 text-xs">
                    <button id="style-selling-btn" type="button" class="py-2 rounded-lg font-extrabold text-center transition bg-indigo-600 text-white shadow-md">
                        🛡️ Option Selling (80%)
                    </button>
                    <button id="style-buying-btn" type="button" class="py-2 rounded-lg font-bold text-center text-slate-400 hover:text-white transition">
                        ⚡ Momentum Buying
                    </button>
                </div>

                <!-- 2. Contract Config: Instrument (PE / CE) & Strike Selection -->
                <div class="space-y-3 bg-[#090d16] p-3 rounded-xl border border-slate-800/80">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-400">Action &amp; Option Type</span>
                        <div class="flex items-center gap-1">
                            <button id="opt-action-sell-btn" type="button" class="px-2.5 py-0.5 rounded text-xs font-bold bg-rose-600 text-white shadow-xs">SELL</button>
                            <button id="opt-action-buy-btn" type="button" class="px-2.5 py-0.5 rounded text-xs font-bold text-slate-400 hover:text-white">BUY</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <button id="type-pe-btn" type="button" class="py-2 rounded-lg font-bold text-xs text-center border transition bg-emerald-950/80 border-emerald-500 text-emerald-300 shadow-sm">
                            PUT (PE)
                        </button>
                        <button id="type-ce-btn" type="button" class="py-2 rounded-lg font-bold text-xs text-center border transition bg-slate-900 border-slate-800 text-slate-400 hover:text-white">
                            CALL (CE)
                        </button>
                    </div>

                    <!-- Strike Selection -->
                    <div class="flex items-center justify-between gap-2 pt-1">
                        <label class="text-xs text-slate-300 font-medium">Strike Price:</label>
                        <div class="flex items-center gap-1.5">
                            <select id="strike-select" class="bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1 text-xs font-mono font-bold text-cyan-300 focus:outline-none focus:border-cyan-500">
                                <!-- Populated dynamically around spot -->
                            </select>
                            <button id="atm-snap-btn" type="button" class="px-2 py-1 rounded bg-slate-800 hover:bg-slate-700 text-[10px] font-bold text-amber-300 border border-slate-700" title="Snap to ATM Strike">ATM</button>
                        </div>
                    </div>

                    <div class="text-[11px] text-slate-400 font-mono flex items-center justify-between pt-1 border-t border-slate-800/50">
                        <span>Trading Symbol:</span>
                        <span id="selected-contract-name" class="font-bold text-white">NIFTY 25200 PE</span>
                    </div>
                </div>

                <!-- 3. Position Sizing: Lots (Default 8 lots = 520 Qty @ 65/lot) or High Qty for Buying -->
                <div class="space-y-2 bg-[#090d16] p-3 rounded-xl border border-slate-800/80">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-300">Position Lots (65 / lot)</span>
                        <span class="text-xs font-mono font-bold text-cyan-400">Qty: <span id="total-qty-display">520</span></span>
                    </div>

                    <!-- Lot Presets for Selling -->
                    <div id="selling-lots-group" class="grid grid-cols-4 gap-1.5 text-xs">
                        <button type="button" class="lot-btn py-1 rounded bg-slate-900 border border-slate-700 font-bold text-slate-300 hover:bg-slate-800" data-lots="2">2 (130)</button>
                        <button type="button" class="lot-btn py-1 rounded bg-slate-900 border border-slate-700 font-bold text-slate-300 hover:bg-slate-800" data-lots="4">4 (260)</button>
                        <button type="button" class="lot-btn py-1 rounded bg-indigo-600 border border-indigo-500 font-extrabold text-white shadow-xs" data-lots="8">8 (520) ⚡</button>
                        <button type="button" class="lot-btn py-1 rounded bg-slate-900 border border-slate-700 font-bold text-slate-300 hover:bg-slate-800" data-lots="10">10 (650)</button>
                    </div>

                    <!-- Quantity Presets for Buying (Scaled for Momentum) -->
                    <div id="buying-lots-group" class="hidden grid grid-cols-4 gap-1.5 text-xs">
                        <button type="button" class="buy-qty-btn py-1 rounded bg-slate-900 border border-slate-700 font-bold text-slate-300 hover:bg-slate-800" data-lots="8">520 qty</button>
                        <button type="button" class="buy-qty-btn py-1 rounded bg-slate-900 border border-slate-700 font-bold text-slate-300 hover:bg-slate-800" data-lots="16">1040 qty</button>
                        <button type="button" class="buy-qty-btn py-1 rounded bg-fuchsia-600 border border-fuchsia-500 font-bold text-white shadow-xs" data-lots="31">2015 qty</button>
                        <button type="button" class="buy-qty-btn py-1 rounded bg-slate-900 border border-slate-700 font-bold text-slate-300 hover:bg-slate-800" data-lots="40">2600 qty</button>
                    </div>

                    <div class="flex items-center justify-between gap-2 pt-1">
                        <label class="text-[11px] text-slate-400">Custom Lots:</label>
                        <input type="number" id="custom-lots-input" min="1" max="100" value="8" class="w-20 bg-slate-900 border border-slate-700 rounded-lg px-2 py-0.5 text-xs font-mono font-bold text-right text-white focus:outline-none focus:border-cyan-500">
                    </div>
                </div>

                <!-- 4. Spot-Anchored Risk & Bracket Settings -->
                <div class="space-y-2 bg-[#090d16] p-3 rounded-xl border border-slate-800/80 text-xs">
                    <div class="flex items-center justify-between border-b border-slate-800/80 pb-1.5">
                        <span class="font-bold text-slate-300">Spot Protection Bracket</span>
                        <span class="text-[10px] text-cyan-400 font-mono">3-5 pts Behind Wall</span>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-[11px] text-slate-400 block mb-0.5">Spot Entry</label>
                            <input type="number" step="0.05" id="bracket-spot-entry" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1 font-mono font-bold text-emerald-400 text-xs focus:outline-none">
                        </div>
                        <div>
                            <label class="text-[11px] text-rose-400 block mb-0.5">Spot SL (Wall ± 5pts)</label>
                            <input type="number" step="0.05" id="bracket-spot-sl" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1 font-mono font-bold text-rose-400 text-xs focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-[11px] text-cyan-400 block mb-0.5">Target 1 (Pivot)</label>
                            <input type="number" step="0.05" id="bracket-spot-tp1" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1 font-mono font-bold text-cyan-300 text-xs focus:outline-none">
                        </div>
                        <div>
                            <label class="text-[11px] text-blue-400 block mb-0.5">Target 2 (Opp Wall)</label>
                            <input type="number" step="0.05" id="bracket-spot-tp2" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1 font-mono font-bold text-blue-300 text-xs focus:outline-none">
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-1 text-[11px] font-mono text-slate-400">
                        <span>Risk/Reward:</span>
                        <span id="calculated-rr-badge" class="font-bold text-emerald-400">1 : 4.5</span>
                    </div>
                </div>

                <!-- 5. 0Click.in MTM Protection (Loss ₹3,000 / Target ₹5,000 / Trail ₹1,000) -->
                <div class="space-y-2 bg-[#090d16] p-3 rounded-xl border border-slate-800/80 text-xs">
                    <div class="flex items-center justify-between border-b border-slate-800/80 pb-1.5">
                        <span class="font-bold text-slate-300">0Click.in MTM Controls</span>
                        <span class="text-[10px] text-emerald-400 font-mono">Automated Exit</span>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-[11px] text-emerald-400 block mb-0.5">MTM Profit (₹)</label>
                            <input type="number" id="mtm-target-input" value="5000" step="500" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1 font-mono font-bold text-emerald-300 text-xs focus:outline-none">
                        </div>
                        <div>
                            <label class="text-[11px] text-rose-400 block mb-0.5">Max Loss (₹)</label>
                            <input type="number" id="mtm-loss-input" value="3000" step="500" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1 font-mono font-bold text-rose-300 text-xs focus:outline-none">
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2 pt-1">
                        <div class="flex items-center gap-1.5">
                            <input type="checkbox" id="auto-trailing-check" checked class="rounded bg-slate-800 border-slate-700 text-cyan-500 focus:ring-0">
                            <label for="auto-trailing-check" class="text-[11px] text-slate-300 cursor-pointer">Auto-Trailing (₹)</label>
                        </div>
                        <input type="number" id="trail-step-input" value="1000" step="250" class="w-20 bg-slate-900 border border-slate-700 rounded-lg px-2 py-0.5 font-mono text-xs text-right text-cyan-300 focus:outline-none">
                    </div>
                </div>

                <!-- 6. Big Action Buttons -->
                <div class="space-y-2 pt-1">
                    <button id="dispatch-0click-btn" type="button" class="w-full py-3 rounded-xl font-extrabold text-sm text-center uppercase tracking-wider bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white shadow-lg pulse-glow-green transition-all flex items-center justify-center gap-2">
                        <svg class="w-5 h-5 text-white animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <span>DISPATCH TO 0CLICK.IN</span>
                    </button>

                    <button id="dispatch-sim-btn" type="button" class="w-full py-2 rounded-xl font-bold text-xs text-center uppercase tracking-wider bg-slate-800 hover:bg-slate-700 text-amber-300 border border-amber-600/40 transition flex items-center justify-center gap-1.5">
                        <span>🧪 Paper Trade / Local Simulation</span>
                    </button>
                </div>

                <!-- Last Execution Feedback Bar -->
                <div id="execution-feedback-bar" class="hidden p-2.5 rounded-lg border text-xs font-mono space-y-1">
                    <div class="flex items-center justify-between font-bold">
                        <span id="feedback-status-title">STATUS</span>
                        <span id="feedback-latency" class="text-[10px] text-slate-400">0.0 ms</span>
                    </div>
                    <div id="feedback-msg" class="text-slate-300 text-[11px]">Ready</div>
                </div>

            </div>

        </div>

    </main>

    <!-- Bottom Section: Execution Audit Journal -->
    <section class="bg-[#0b101a] border-t border-slate-800/80 px-4 py-3 shrink-0">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2">
                <span class="text-xs font-extrabold text-slate-200 uppercase tracking-wider">0Click Execution Audit Journal</span>
                <span id="logs-count-badge" class="px-2 py-0.2 rounded-full text-[10px] font-mono bg-slate-800 text-cyan-400 border border-slate-700">0 Logs</span>
            </div>
            <button id="refresh-logs-btn" class="text-[11px] text-indigo-400 hover:text-indigo-300 font-bold flex items-center gap-1">
                <span>🔄 Refresh Journal</span>
            </button>
        </div>

        <div class="bg-[#080c14] border border-slate-800/80 rounded-xl overflow-hidden max-h-48 overflow-y-auto custom-scrollbar">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-[#0f172a] text-[10px] font-bold text-slate-400 uppercase tracking-wider sticky top-0 border-b border-slate-800">
                    <tr>
                        <th class="py-2 px-3">Time</th>
                        <th class="py-2 px-3">Mode</th>
                        <th class="py-2 px-3">Action</th>
                        <th class="py-2 px-3">Contract</th>
                        <th class="py-2 px-3 text-right">Lots / Qty</th>
                        <th class="py-2 px-3 text-right">Spot Entry</th>
                        <th class="py-2 px-3 text-right">Spot SL</th>
                        <th class="py-2 px-3 text-right">Target</th>
                        <th class="py-2 px-3 text-right">MTM TP/SL</th>
                        <th class="py-2 px-3 text-center">Status</th>
                        <th class="py-2 px-3 text-right">Latency</th>
                    </tr>
                </thead>
                <tbody id="logs-table-body" class="divide-y divide-slate-800/60 text-slate-300">
                    <tr>
                        <td colspan="11" class="py-4 text-center text-slate-500 font-sans text-xs">No orders dispatched in this session yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Webhook Config Modal -->
    <div id="config-modal" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-[#0f172a] border border-slate-700 rounded-2xl w-full max-w-lg p-5 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-base font-bold text-white">⚙️ 0Click.in Webhook Configuration</span>
                </div>
                <button id="close-config-btn" class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="block text-slate-300 font-semibold mb-1">Webhook Dispatch URL:</label>
                    <input type="url" id="cfg-webhook-url" value="{{ $defaultWebhookUrl }}" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-200 font-mono focus:outline-none focus:border-cyan-500">
                    <span class="text-[10px] text-slate-400">Default: https://0click.in/api/webhook/tradingview</span>
                </div>

                <div>
                    <label class="block text-slate-300 font-semibold mb-1">Webhook Secret / Authorization Token:</label>
                    <input type="text" id="cfg-webhook-token" value="{{ $defaultWebhookToken }}" placeholder="Enter secret or token configured in 0Click" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-200 font-mono focus:outline-none focus:border-cyan-500">
                    <span class="text-[10px] text-slate-400">Passed as 'secret' in JSON body and Bearer token in headers</span>
                </div>

                <div class="p-3 rounded-lg bg-indigo-950/40 border border-indigo-700/50 text-indigo-300 text-[11px] space-y-1">
                    <div class="font-bold">Institutional Safeguards Active:</div>
                    <ul class="list-disc pl-4 space-y-0.5">
                        <li>Spot Stop-Loss protection_basis: BOTH sends native Spot and MTM bounds.</li>
                        <li>80% Selling Mode enforces 8 lots maximum lot threshold unless manually adjusted.</li>
                        <li>Audit log automatically captures every dispatch into local database table.</li>
                    </ul>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                <button id="cancel-cfg-btn" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs">Cancel</button>
                <button id="save-cfg-btn" class="px-4 py-1.5 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs shadow-md">Save Settings</button>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── 1. State & Configurations ──────────────────────────────────────────
    let selectedDate = '{{ $selectedDate }}';
    let selectedExpiry = '{{ $selectedExpiry }}';
    let currentTimeframe = 5; // 5 min default
    let isLiveMode = {{ env('ZEROCLICK_LIVE', true) ? 'true' : 'false' }};
    let currentTradeStyle = 'OPTION_SELLING'; // 'OPTION_SELLING' or 'OPTION_BUYING'
    let currentAction = 'SELL';
    let currentOptionType = 'PE';
    let currentLots = 8;
    const lotSize = 65;

    let spotPrice = 25215.0;
    let orderFlowLevels = null;
    let multiDayData = null;
    let active5dLevels = {}; // { 'key': boolean }
    let ofCalibrationMode = localStorage.getItem('nifty_option_trader_of_calibration') || 'future';

    // Chart variables
    let chart, candleSeries;
    let bracketLines = { entry: null, sl: null, tp1: null, tp2: null };
    let historicalLines = [];
    let orderFlowLines = [];

    // LocalStorage for Webhook Config
    const savedUrl = localStorage.getItem('0click_webhook_url');
    const savedToken = localStorage.getItem('0click_webhook_token');
    if (savedUrl) document.getElementById('cfg-webhook-url').value = savedUrl;
    if (savedToken) document.getElementById('cfg-webhook-token').value = savedToken;

    // Basis Mode UI Updater
    function updateOfCalibrationUI() {
        const isFut = (ofCalibrationMode === 'future');
        const btn = document.getElementById('basis-calib-toggle-btn');
        const label = document.getElementById('basis-calib-label');
        if (label) {
            label.textContent = isFut ? 'FUT' : 'SPOT';
            label.className = isFut ? 'font-mono text-amber-400 font-extrabold' : 'font-mono text-teal-400 font-extrabold';
        }
        if (btn) {
            btn.className = isFut 
                ? 'flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-900 border border-amber-600/70 hover:bg-amber-950/40 text-amber-300 transition'
                : 'flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-900 border border-teal-600/70 hover:bg-teal-950/40 text-teal-300 transition';
        }
    }

    function toggleOfCalibration() {
        ofCalibrationMode = (ofCalibrationMode === 'future') ? 'spot' : 'future';
        try {
            localStorage.setItem('nifty_option_trader_of_calibration', ofCalibrationMode);
        } catch (e) {}
        updateOfCalibrationUI();
        renderOrderFlowBadges();
        drawOrderFlowLines();
        render5dDropdown();
        drawHistorical5dLines();
    }
    document.getElementById('basis-calib-toggle-btn')?.addEventListener('click', toggleOfCalibration);
    updateOfCalibrationUI();

    // ── 2. Initialize Lightweight Chart ────────────────────────────────────
    function initChart() {
        const container = document.getElementById('chart-container');
        if (!container) return;
        container.innerHTML = '';

        chart = LightweightCharts.createChart(container, {
            layout: {
                background: { type: 'solid', color: '#090d16' },
                textColor: '#94a3b8',
                fontSize: 11,
                fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
            },
            grid: {
                vertLines: { color: 'rgba(30, 41, 59, 0.4)' },
                horzLines: { color: 'rgba(30, 41, 59, 0.4)' }
            },
            crosshair: {
                mode: LightweightCharts.CrosshairMode.Normal,
                vertLine: { color: '#64748b', width: 1, style: 2 },
                horzLine: { color: '#64748b', width: 1, style: 2 }
            },
            rightPriceScale: {
                borderColor: '#1e293b',
                scaleMargins: { top: 0.1, bottom: 0.15 }
            },
            timeScale: {
                borderColor: '#1e293b',
                timeVisible: true,
                secondsVisible: false
            }
        });

        candleSeries = chart.addCandlestickSeries({
            upColor: '#10b981',
            downColor: '#ef4444',
            borderUpColor: '#10b981',
            borderDownColor: '#ef4444',
            wickUpColor: '#10b981',
            wickDownColor: '#ef4444'
        });

        new ResizeObserver(entries => {
            if (entries.length && entries[0].contentRect && chart) {
                chart.applyOptions({
                    width: entries[0].contentRect.width,
                    height: entries[0].contentRect.height
                });
            }
        }).observe(container);
    }

    // ── 3. Fetch Candlesticks & Draw Chart ──────────────────────────────────
    async function loadCandlesticks() {
        document.getElementById('chart-loading')?.classList.remove('hidden');
        try {
            const res = await fetch(`/api/nifty-weightage-monitor/data?date=${selectedDate}&interval=${currentTimeframe}&_t=${Date.now()}`);
            const data = await res.json();
            
            let candles = data.candles || data.nifty_candles || [];
            if (candles.length > 0) {
                const formatted = candles.map(c => ({
                    time: c.time || c.timestamp,
                    open: parseFloat(c.open),
                    high: parseFloat(c.high),
                    low: parseFloat(c.low),
                    close: parseFloat(c.close)
                })).sort((a, b) => a.time - b.time);

                candleSeries.setData(formatted);
                chart.timeScale().fitContent();

                // Update Spot Price from last candle
                const lastCandle = formatted[formatted.length - 1];
                spotPrice = lastCandle.close;
                updateSpotBadge(lastCandle);
                populateStrikes(spotPrice);
            }
        } catch (e) {
            console.error('Error fetching candles:', e);
        } finally {
            document.getElementById('chart-loading')?.classList.add('hidden');
        }
    }

    function updateSpotBadge(candle) {
        const spotEl = document.getElementById('nifty-spot-price');
        const diffEl = document.getElementById('nifty-spot-diff');
        if (spotEl) spotEl.textContent = candle.close.toFixed(2);
        
        const diff = candle.close - candle.open;
        if (diffEl) {
            diffEl.textContent = `(${diff >= 0 ? '+' : ''}${diff.toFixed(2)} pts)`;
            diffEl.className = diff >= 0 ? 'text-[11px] font-mono font-semibold text-emerald-400' : 'text-[11px] font-mono font-semibold text-rose-400';
        }
    }

    // ── 4. Fetch Order Flow Levels & 5D Historical Anchors ──────────────────
    async function loadOrderFlowLevels() {
        try {
            const res = await fetch(`/api/multi-timeframe/order-flow-levels?date=${selectedDate}&symbol=NIFTY&limit=5&rank_by=traded_volume&spot=${spotPrice}&_t=${Date.now()}`);
            const data = await res.json();
            if (data && data.success) {
                orderFlowLevels = data;
                renderOrderFlowBadges();
                drawOrderFlowLines();
            }
        } catch (e) {
            console.warn('Error loading order flow levels:', e);
        }

        // Load 5D Historical Top 1
        try {
            const res5d = await fetch(`/api/multi-timeframe/multi-day-order-flow-levels?date=${selectedDate}&symbol=NIFTY&days=5&spot=${spotPrice}&_t=${Date.now()}`);
            const data5d = await res5d.json();
            if (data5d && data5d.success && data5d.days) {
                multiDayData = data5d;
                render5dDropdown();
            }
        } catch (e) {
            console.warn('Error loading 5D historical levels:', e);
        }
    }

    function renderOrderFlowBadges() {
        if (!orderFlowLevels) return;
        const td = orderFlowLevels.today || {};
        const yd = orderFlowLevels.yesterday || {};
        const isFut = (ofCalibrationMode === 'future');
        const basisTag = isFut ? 'FUT' : 'SPOT';

        if (td.asks && td.asks.length > 0) {
            const topAsk = td.asks[0];
            const askPrice = isFut ? (topAsk.raw_price || topAsk.price) : (topAsk.calibrated_price || topAsk.price);
            const askQty = topAsk.qty || topAsk.volume || 0;
            document.getElementById('td-ask-badge')?.classList.remove('hidden');
            document.getElementById('td-ask-val').textContent = `${parseFloat(askPrice).toFixed(1)} (${basisTag})`;
            document.getElementById('td-ask-vol').textContent = askQty >= 1000 ? (askQty / 1000).toFixed(1) + 'k' : askQty;
        }
        if (td.bids && td.bids.length > 0) {
            const topBid = td.bids[0];
            const bidPrice = isFut ? (topBid.raw_price || topBid.price) : (topBid.calibrated_price || topBid.price);
            const bidQty = topBid.qty || topBid.volume || 0;
            document.getElementById('td-bid-badge')?.classList.remove('hidden');
            document.getElementById('td-bid-val').textContent = `${parseFloat(bidPrice).toFixed(1)} (${basisTag})`;
            document.getElementById('td-bid-vol').textContent = bidQty >= 1000 ? (bidQty / 1000).toFixed(1) + 'k' : bidQty;
        }
        if (yd.asks && yd.asks.length > 0) {
            const ydAskPrice = isFut ? (yd.asks[0].raw_price || yd.asks[0].price) : (yd.asks[0].calibrated_price || yd.asks[0].price);
            document.getElementById('yd-ask-badge')?.classList.remove('hidden');
            document.getElementById('yd-ask-val').textContent = `${parseFloat(ydAskPrice).toFixed(1)} (${basisTag})`;
        }
        if (yd.bids && yd.bids.length > 0) {
            const ydBidPrice = isFut ? (yd.bids[0].raw_price || yd.bids[0].price) : (yd.bids[0].calibrated_price || yd.bids[0].price);
            document.getElementById('yd-bid-badge')?.classList.remove('hidden');
            document.getElementById('yd-bid-val').textContent = `${parseFloat(ydBidPrice).toFixed(1)} (${basisTag})`;
        }

        // Totals & Ratio
        const totalBids = (td.bids || []).reduce((acc, b) => acc + (b.qty || 0), 0) || orderFlowLevels.total_bid_volume || 0;
        const totalAsks = (td.asks || []).reduce((acc, a) => acc + (a.qty || 0), 0) || orderFlowLevels.total_ask_volume || 0;
        document.getElementById('total-bids-val').textContent = totalBids.toLocaleString();
        document.getElementById('total-asks-val').textContent = totalAsks.toLocaleString();
        const ratio = totalAsks > 0 ? (totalBids / totalAsks).toFixed(2) : '1.00';
        document.getElementById('depth-ratio-val').textContent = ratio;

        const biasPill = document.getElementById('flow-bias-pill');
        if (biasPill) {
            if (ratio > 1.25) {
                biasPill.textContent = 'BULLISH ABSORPTION (BUY/DEFEND)';
                biasPill.className = 'px-2.5 py-0.5 rounded font-bold text-xs bg-emerald-950 text-emerald-300 border border-emerald-600';
            } else if (ratio < 0.8) {
                biasPill.textContent = 'BEARISH ABSORPTION (SELL/REJECT)';
                biasPill.className = 'px-2.5 py-0.5 rounded font-bold text-xs bg-rose-950 text-rose-300 border border-rose-600';
            } else {
                biasPill.textContent = 'BALANCED FLOW';
                biasPill.className = 'px-2.5 py-0.5 rounded font-bold text-xs bg-slate-800 text-slate-300 border border-slate-700';
            }
        }
    }

    function drawOrderFlowLines() {
        // Clear previous lines
        orderFlowLines.forEach(l => candleSeries.removePriceLine(l));
        orderFlowLines = [];

        if (!orderFlowLevels) return;
        const td = orderFlowLevels.today || {};
        const yd = orderFlowLevels.yesterday || {};
        const isFut = (ofCalibrationMode === 'future');
        const tag = isFut ? '(FUT)' : '(SPOT)';

        if (td.asks && td.asks[0]) {
            const p = parseFloat(isFut ? (td.asks[0].raw_price || td.asks[0].price) : (td.asks[0].calibrated_price || td.asks[0].price));
            const kQty = td.asks[0].qty ? ` [${(td.asks[0].qty / 1000).toFixed(1)}k]` : '';
            orderFlowLines.push(candleSeries.createPriceLine({
                price: p,
                color: '#f43f5e',
                lineWidth: 2,
                lineStyle: LightweightCharts.LineStyle.Solid,
                axisLabelVisible: true,
                title: `TD RES ${tag}${kQty}`
            }));
        }
        if (td.bids && td.bids[0]) {
            const p = parseFloat(isFut ? (td.bids[0].raw_price || td.bids[0].price) : (td.bids[0].calibrated_price || td.bids[0].price));
            const kQty = td.bids[0].qty ? ` [${(td.bids[0].qty / 1000).toFixed(1)}k]` : '';
            orderFlowLines.push(candleSeries.createPriceLine({
                price: p,
                color: '#10b981',
                lineWidth: 2,
                lineStyle: LightweightCharts.LineStyle.Solid,
                axisLabelVisible: true,
                title: `TD SUP ${tag}${kQty}`
            }));
        }
        if (yd.asks && yd.asks[0]) {
            const p = parseFloat(isFut ? (yd.asks[0].raw_price || yd.asks[0].price) : (yd.asks[0].calibrated_price || yd.asks[0].price));
            const kQty = yd.asks[0].qty ? ` [${(yd.asks[0].qty / 1000).toFixed(1)}k]` : '';
            orderFlowLines.push(candleSeries.createPriceLine({
                price: p,
                color: '#f59e0b',
                lineWidth: 1,
                lineStyle: LightweightCharts.LineStyle.Dashed,
                axisLabelVisible: true,
                title: `YD RES ${tag}${kQty}`
            }));
        }
        if (yd.bids && yd.bids[0]) {
            const p = parseFloat(isFut ? (yd.bids[0].raw_price || yd.bids[0].price) : (yd.bids[0].calibrated_price || yd.bids[0].price));
            const kQty = yd.bids[0].qty ? ` [${(yd.bids[0].qty / 1000).toFixed(1)}k]` : '';
            orderFlowLines.push(candleSeries.createPriceLine({
                price: p,
                color: '#06b6d4',
                lineWidth: 1,
                lineStyle: LightweightCharts.LineStyle.Dashed,
                axisLabelVisible: true,
                title: `YD SUP ${tag}${kQty}`
            }));
        }
    }

    function render5dDropdown() {
        const list = document.getElementById('historical-5d-list');
        if (!list || !multiDayData || !multiDayData.days) return;
        const isFut = (ofCalibrationMode === 'future');

        let html = '';
        multiDayData.days.forEach((d, idx) => {
            const askKey = `d_${idx}_ask`;
            const bidKey = `d_${idx}_bid`;
            const dayLabel = d.day_code || `D-${idx + 1}`;
            const dateStr = d.formatted_date || d.date;

            const askPrice = isFut ? (d.ask?.price ?? null) : (d.ask?.calibrated_price ?? d.ask?.price ?? null);
            const bidPrice = isFut ? (d.bid?.price ?? null) : (d.bid?.calibrated_price ?? d.bid?.price ?? null);
            const askQty = d.ask?.qty ? Math.round(d.ask.qty / 1000) + 'k' : '';
            const bidQty = d.bid?.qty ? Math.round(d.bid.qty / 1000) + 'k' : '';

            html += `
                <div class="p-2 rounded bg-slate-900 border border-slate-800 flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <div class="font-bold text-slate-200 flex items-center gap-1.5">
                            <span class="text-indigo-400 font-mono">${dayLabel}</span>
                            <span class="text-[10px] text-slate-400">(${dateStr})</span>
                        </div>
                        <div class="flex items-center gap-2 mt-0.5 text-[11px] font-mono">
                            <span class="text-rose-400">R: ${askPrice !== null ? parseFloat(askPrice).toFixed(1) : '--'}${askQty ? ' (' + askQty + ')' : ''}</span>
                            <span class="text-emerald-400">S: ${bidPrice !== null ? parseFloat(bidPrice).toFixed(1) : '--'}${bidQty ? ' (' + bidQty + ')' : ''}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" class="toggle-5d-item px-2 py-0.5 rounded text-[10px] font-bold ${active5dLevels[askKey] ? 'bg-rose-600 text-white' : 'bg-slate-800 text-slate-400'}" data-key="${askKey}" title="Toggle ${dayLabel} RES line on chart">R</button>
                        <button type="button" class="toggle-5d-item px-2 py-0.5 rounded text-[10px] font-bold ${active5dLevels[bidKey] ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-400'}" data-key="${bidKey}" title="Toggle ${dayLabel} SUP line on chart">S</button>
                    </div>
                </div>
            `;
        });
        list.innerHTML = html;

        // Attach click listeners to 5D toggles
        list.querySelectorAll('.toggle-5d-item').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const key = this.dataset.key;
                active5dLevels[key] = !active5dLevels[key];
                this.className = `toggle-5d-item px-2 py-0.5 rounded text-[10px] font-bold ${active5dLevels[key] ? (key.includes('ask') ? 'bg-rose-600 text-white' : 'bg-emerald-600 text-white') : 'bg-slate-800 text-slate-400'}`;
                drawHistorical5dLines();
            });
        });
    }

    function drawHistorical5dLines() {
        historicalLines.forEach(l => candleSeries.removePriceLine(l));
        historicalLines = [];

        if (!multiDayData || !multiDayData.days) return;
        const isFut = (ofCalibrationMode === 'future');
        const tag = isFut ? '(FUT)' : '(SPOT)';

        multiDayData.days.forEach((d, idx) => {
            const askKey = `d_${idx}_ask`;
            const bidKey = `d_${idx}_bid`;
            const dayLabel = d.day_code || `D-${idx + 1}`;
            const askPrice = isFut ? (d.ask?.price ?? null) : (d.ask?.calibrated_price ?? d.ask?.price ?? null);
            const bidPrice = isFut ? (d.bid?.price ?? null) : (d.bid?.calibrated_price ?? d.bid?.price ?? null);

            if (active5dLevels[askKey] && askPrice !== null) {
                historicalLines.push(candleSeries.createPriceLine({
                    price: parseFloat(askPrice),
                    color: '#fb7185',
                    lineWidth: 1,
                    lineStyle: LightweightCharts.LineStyle.Dotted,
                    axisLabelVisible: true,
                    title: `${dayLabel} RES ${tag}`
                }));
            }
            if (active5dLevels[bidKey] && bidPrice !== null) {
                historicalLines.push(candleSeries.createPriceLine({
                    price: parseFloat(bidPrice),
                    color: '#34d399',
                    lineWidth: 1,
                    lineStyle: LightweightCharts.LineStyle.Dotted,
                    axisLabelVisible: true,
                    title: `${dayLabel} SUP ${tag}`
                }));
            }
        });
    }

    // ── 5. Bracket Calculation & Interactive Lines on Chart ────────────────
    function updateVisualBracket() {
        const entry = parseFloat(document.getElementById('bracket-spot-entry').value) || spotPrice;
        const sl = parseFloat(document.getElementById('bracket-spot-sl').value) || (entry - 5);
        const tp1 = parseFloat(document.getElementById('bracket-spot-tp1').value) || (entry + 25);
        const tp2 = parseFloat(document.getElementById('bracket-spot-tp2').value) || (entry + 50);

        // Update Legend
        document.getElementById('legend-entry').textContent = entry.toFixed(2);
        document.getElementById('legend-sl').textContent = sl.toFixed(2);
        document.getElementById('legend-tp1').textContent = tp1.toFixed(2);
        document.getElementById('legend-tp2').textContent = tp2.toFixed(2);

        // Calculate Risk:Reward
        const risk = Math.abs(entry - sl);
        const reward = Math.abs(tp1 - entry);
        const rr = risk > 0 ? (reward / risk).toFixed(1) : '1.0';
        document.getElementById('calculated-rr-badge').textContent = `1 : ${rr}`;

        // Redraw Bracket Lines on Chart
        if (bracketLines.entry) candleSeries.removePriceLine(bracketLines.entry);
        if (bracketLines.sl) candleSeries.removePriceLine(bracketLines.sl);
        if (bracketLines.tp1) candleSeries.removePriceLine(bracketLines.tp1);
        if (bracketLines.tp2) candleSeries.removePriceLine(bracketLines.tp2);

        bracketLines.entry = candleSeries.createPriceLine({
            price: entry,
            color: '#10b981',
            lineWidth: 2,
            lineStyle: LightweightCharts.LineStyle.Dotted,
            axisLabelVisible: true,
            title: 'PROPOSED ENTRY'
        });

        bracketLines.sl = candleSeries.createPriceLine({
            price: sl,
            color: '#ef4444',
            lineWidth: 2,
            lineStyle: LightweightCharts.LineStyle.Solid,
            axisLabelVisible: true,
            title: 'SPOT SL (5 pts)'
        });

        bracketLines.tp1 = candleSeries.createPriceLine({
            price: tp1,
            color: '#06b6d4',
            lineWidth: 2,
            lineStyle: LightweightCharts.LineStyle.Dashed,
            axisLabelVisible: true,
            title: 'TARGET 1'
        });

        bracketLines.tp2 = candleSeries.createPriceLine({
            price: tp2,
            color: '#3b82f6',
            lineWidth: 1,
            lineStyle: LightweightCharts.LineStyle.Dashed,
            axisLabelVisible: true,
            title: 'TARGET 2'
        });
    }

    // ── 6. Preset Loaders (Bull Wall Defense, Bear Wall Defense, Momentum) ──
    document.getElementById('preset-bull-wall-btn')?.addEventListener('click', () => {
        // Bullish Bid Wall Defense -> Sell PE
        setTradeStyle('OPTION_SELLING');
        setOptionType('PE');
        setAction('SELL');

        const isFut = (ofCalibrationMode === 'future');
        const rawBid = orderFlowLevels?.today?.bids?.[0];
        const bidWall = rawBid ? parseFloat(isFut ? (rawBid.raw_price || rawBid.price) : (rawBid.calibrated_price || rawBid.price)) : spotPrice;

        document.getElementById('bracket-spot-entry').value = bidWall.toFixed(2);
        document.getElementById('bracket-spot-sl').value = (bidWall - 5.0).toFixed(2); // 5 pts behind wall
        document.getElementById('bracket-spot-tp1').value = (bidWall + 25.0).toFixed(2);
        document.getElementById('bracket-spot-tp2').value = (bidWall + 50.0).toFixed(2);
        updateVisualBracket();
    });

    document.getElementById('preset-bear-wall-btn')?.addEventListener('click', () => {
        // Bearish Ask Wall Defense -> Sell CE
        setTradeStyle('OPTION_SELLING');
        setOptionType('CE');
        setAction('SELL');

        const isFut = (ofCalibrationMode === 'future');
        const rawAsk = orderFlowLevels?.today?.asks?.[0];
        const askWall = rawAsk ? parseFloat(isFut ? (rawAsk.raw_price || rawAsk.price) : (rawAsk.calibrated_price || rawAsk.price)) : spotPrice;

        document.getElementById('bracket-spot-entry').value = askWall.toFixed(2);
        document.getElementById('bracket-spot-sl').value = (askWall + 5.0).toFixed(2); // 5 pts above wall
        document.getElementById('bracket-spot-tp1').value = (askWall - 25.0).toFixed(2);
        document.getElementById('bracket-spot-tp2').value = (askWall - 50.0).toFixed(2);
        updateVisualBracket();
    });

    document.getElementById('preset-breakout-buy-btn')?.addEventListener('click', () => {
        // Momentum Confluence Buying -> Buy CE
        setTradeStyle('OPTION_BUYING');
        setOptionType('CE');
        setAction('BUY');

        const isFut = (ofCalibrationMode === 'future');
        const rawAsk = orderFlowLevels?.today?.asks?.[0];
        const askWall = rawAsk ? parseFloat(isFut ? (rawAsk.raw_price || rawAsk.price) : (rawAsk.calibrated_price || rawAsk.price)) : spotPrice;

        document.getElementById('bracket-spot-entry').value = (askWall + 2.0).toFixed(2);
        document.getElementById('bracket-spot-sl').value = (askWall - 5.0).toFixed(2);
        document.getElementById('bracket-spot-tp1').value = (askWall + 35.0).toFixed(2);
        document.getElementById('bracket-spot-tp2').value = (askWall + 70.0).toFixed(2);
        updateVisualBracket();
    });

    // ── 7. UI Controls & Event Listeners ───────────────────────────────────
    function populateStrikes(refSpot) {
        const select = document.getElementById('strike-select');
        if (!select) return;
        const currentVal = parseInt(select.value);
        const atm = Math.round(refSpot / 50) * 50;

        select.innerHTML = '';
        for (let s = atm - 500; s <= atm + 500; s += 50) {
            const opt = document.createElement('option');
            opt.value = s;
            opt.textContent = `${s} ${s === atm ? '★ ATM' : ''}`;
            if ((currentVal && s === currentVal) || (!currentVal && s === atm)) {
                opt.selected = true;
            }
            select.appendChild(opt);
        }
        updateContractLabel();
    }

    function updateContractLabel() {
        const strike = document.getElementById('strike-select').value;
        document.getElementById('selected-contract-name').textContent = `NIFTY ${strike} ${currentOptionType}`;
    }

    function setTradeStyle(style) {
        currentTradeStyle = style;
        const sellBtn = document.getElementById('style-selling-btn');
        const buyBtn = document.getElementById('style-buying-btn');
        const sellLotsGrp = document.getElementById('selling-lots-group');
        const buyLotsGrp = document.getElementById('buying-lots-group');

        if (style === 'OPTION_SELLING') {
            sellBtn.className = 'py-2 rounded-lg font-extrabold text-center transition bg-indigo-600 text-white shadow-md';
            buyBtn.className = 'py-2 rounded-lg font-bold text-center text-slate-400 hover:text-white transition';
            sellLotsGrp.classList.remove('hidden');
            buyLotsGrp.classList.add('hidden');
            setLots(8); // Default 8 lots
            setAction('SELL');
        } else {
            buyBtn.className = 'py-2 rounded-lg font-extrabold text-center transition bg-fuchsia-600 text-white shadow-md';
            sellBtn.className = 'py-2 rounded-lg font-bold text-center text-slate-400 hover:text-white transition';
            buyLotsGrp.classList.remove('hidden');
            sellLotsGrp.classList.add('hidden');
            setLots(31); // ~2000 qty
            setAction('BUY');
        }
    }

    function setOptionType(type) {
        currentOptionType = type;
        const peBtn = document.getElementById('type-pe-btn');
        const ceBtn = document.getElementById('type-ce-btn');
        if (type === 'PE') {
            peBtn.className = 'py-2 rounded-lg font-bold text-xs text-center border transition bg-emerald-950/80 border-emerald-500 text-emerald-300 shadow-sm';
            ceBtn.className = 'py-2 rounded-lg font-bold text-xs text-center border transition bg-slate-900 border-slate-800 text-slate-400 hover:text-white';
        } else {
            ceBtn.className = 'py-2 rounded-lg font-bold text-xs text-center border transition bg-rose-950/80 border-rose-500 text-rose-300 shadow-sm';
            peBtn.className = 'py-2 rounded-lg font-bold text-xs text-center border transition bg-slate-900 border-slate-800 text-slate-400 hover:text-white';
        }
        updateContractLabel();
    }

    function setAction(act) {
        currentAction = act;
        const sellBtn = document.getElementById('opt-action-sell-btn');
        const buyBtn = document.getElementById('opt-action-buy-btn');
        if (act === 'SELL') {
            sellBtn.className = 'px-2.5 py-0.5 rounded text-xs font-bold bg-rose-600 text-white shadow-xs';
            buyBtn.className = 'px-2.5 py-0.5 rounded text-xs font-bold text-slate-400 hover:text-white';
        } else {
            buyBtn.className = 'px-2.5 py-0.5 rounded text-xs font-bold bg-emerald-600 text-white shadow-xs';
            sellBtn.className = 'px-2.5 py-0.5 rounded text-xs font-bold text-slate-400 hover:text-white';
        }
    }

    function setLots(lots) {
        currentLots = parseInt(lots);
        document.getElementById('custom-lots-input').value = currentLots;
        document.getElementById('total-qty-display').textContent = (currentLots * lotSize).toLocaleString();
    }

    // Event attachments
    document.getElementById('style-selling-btn')?.addEventListener('click', () => setTradeStyle('OPTION_SELLING'));
    document.getElementById('style-buying-btn')?.addEventListener('click', () => setTradeStyle('OPTION_BUYING'));
    document.getElementById('type-pe-btn')?.addEventListener('click', () => setOptionType('PE'));
    document.getElementById('type-ce-btn')?.addEventListener('click', () => setOptionType('CE'));
    document.getElementById('opt-action-sell-btn')?.addEventListener('click', () => setAction('SELL'));
    document.getElementById('opt-action-buy-btn')?.addEventListener('click', () => setAction('BUY'));
    document.getElementById('strike-select')?.addEventListener('change', updateContractLabel);
    document.getElementById('atm-snap-btn')?.addEventListener('click', () => populateStrikes(spotPrice));

    document.querySelectorAll('.lot-btn').forEach(b => {
        b.addEventListener('click', function() {
            setLots(this.dataset.lots);
            document.querySelectorAll('.lot-btn').forEach(x => x.className = 'lot-btn py-1 rounded bg-slate-900 border border-slate-700 font-bold text-slate-300 hover:bg-slate-800');
            this.className = 'lot-btn py-1 rounded bg-indigo-600 border border-indigo-500 font-extrabold text-white shadow-xs';
        });
    });

    document.querySelectorAll('.buy-qty-btn').forEach(b => {
        b.addEventListener('click', function() {
            setLots(this.dataset.lots);
            document.querySelectorAll('.buy-qty-btn').forEach(x => x.className = 'buy-qty-btn py-1 rounded bg-slate-900 border border-slate-700 font-bold text-slate-300 hover:bg-slate-800');
            this.className = 'buy-qty-btn py-1 rounded bg-fuchsia-600 border border-fuchsia-500 font-bold text-white shadow-xs';
        });
    });

    document.getElementById('custom-lots-input')?.addEventListener('input', function() {
        setLots(this.value || 1);
    });

    ['bracket-spot-entry', 'bracket-spot-sl', 'bracket-spot-tp1', 'bracket-spot-tp2'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', updateVisualBracket);
    });

    // Timeframe switcher
    document.querySelectorAll('.chart-tf-btn').forEach(b => {
        b.addEventListener('click', function() {
            currentTimeframe = parseInt(this.dataset.tf);
            document.querySelectorAll('.chart-tf-btn').forEach(x => x.className = 'chart-tf-btn px-2.5 py-1 rounded font-bold text-slate-400 hover:text-white transition');
            this.className = 'chart-tf-btn px-2.5 py-1 rounded font-bold bg-cyan-600 text-white shadow-xs transition';
            loadCandlesticks();
        });
    });

    // 5D Historical Dropdown Toggle
    const histBtn = document.getElementById('historical-5d-btn');
    const histMenu = document.getElementById('historical-5d-menu');
    histBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        histMenu.classList.toggle('hidden');
    });
    document.addEventListener('click', () => histMenu?.classList.add('hidden'));
    histMenu?.addEventListener('click', (e) => e.stopPropagation());

    document.getElementById('toggle-all-5d-btn')?.addEventListener('click', () => {
        const anyActive = Object.values(active5dLevels).some(v => v);
        Object.keys(active5dLevels).forEach(k => active5dLevels[k] = !anyActive);
        render5dDropdown();
        drawHistorical5dLines();
    });

    // Mode Toggle (LIVE vs SIMULATION)
    document.getElementById('mode-live-btn')?.addEventListener('click', () => {
        isLiveMode = true;
        document.getElementById('mode-live-btn').className = 'px-2.5 py-1 rounded-md font-bold transition bg-emerald-600 text-white shadow-xs';
        document.getElementById('mode-sim-btn').className = 'px-2.5 py-1 rounded-md font-bold transition text-slate-400 hover:text-white';
        document.getElementById('ticket-mode-pill').textContent = 'LIVE';
        document.getElementById('ticket-mode-pill').className = 'text-[11px] font-bold font-mono px-2 py-0.5 rounded bg-emerald-900/60 text-emerald-300 border border-emerald-600/60';
    });

    document.getElementById('mode-sim-btn')?.addEventListener('click', () => {
        isLiveMode = false;
        document.getElementById('mode-sim-btn').className = 'px-2.5 py-1 rounded-md font-bold transition bg-amber-600 text-white shadow-xs';
        document.getElementById('mode-live-btn').className = 'px-2.5 py-1 rounded-md font-bold transition text-slate-400 hover:text-white';
        document.getElementById('ticket-mode-pill').textContent = 'SIM';
        document.getElementById('ticket-mode-pill').className = 'text-[11px] font-bold font-mono px-2 py-0.5 rounded bg-amber-900/60 text-amber-300 border border-amber-600/60';
    });

    // Config Modal
    document.getElementById('open-config-btn')?.addEventListener('click', () => document.getElementById('config-modal').classList.remove('hidden'));
    document.getElementById('close-config-btn')?.addEventListener('click', () => document.getElementById('config-modal').classList.add('hidden'));
    document.getElementById('cancel-cfg-btn')?.addEventListener('click', () => document.getElementById('config-modal').classList.add('hidden'));
    document.getElementById('save-cfg-btn')?.addEventListener('click', () => {
        const u = document.getElementById('cfg-webhook-url').value;
        const t = document.getElementById('cfg-webhook-token').value;
        localStorage.setItem('0click_webhook_url', u);
        localStorage.setItem('0click_webhook_token', t);
        document.getElementById('config-modal').classList.add('hidden');
    });

    // Date & Expiry change
    document.getElementById('trade-date-select')?.addEventListener('change', function() {
        selectedDate = this.value;
        loadCandlesticks().then(() => loadOrderFlowLevels());
        loadLogs();
    });
    document.getElementById('trade-expiry-select')?.addEventListener('change', function() {
        selectedExpiry = this.value;
    });

    // ── 8. Dispatch Order Execution (LIVE & SIMULATION) ─────────────────────
    async function executeOrder(forcedMode = null) {
        const mode = forcedMode || (isLiveMode ? 'LIVE' : 'SIMULATION');
        const webhookUrl = document.getElementById('cfg-webhook-url').value;
        const webhookToken = document.getElementById('cfg-webhook-token').value;

        const strike = parseInt(document.getElementById('strike-select').value) || 25200;
        const entryPrice = parseFloat(document.getElementById('bracket-spot-entry').value) || spotPrice;
        const slPrice = parseFloat(document.getElementById('bracket-spot-sl').value) || (entryPrice - 5);
        const tpPrice = parseFloat(document.getElementById('bracket-spot-tp1').value) || (entryPrice + 25);
        const mtmTarget = parseFloat(document.getElementById('mtm-target-input').value) || 5000;
        const mtmLoss = parseFloat(document.getElementById('mtm-loss-input').value) || 3000;
        const autoTrailing = document.getElementById('auto-trailing-check').checked;
        const trailStep = parseFloat(document.getElementById('trail-step-input').value) || 1000;

        const feedbackBar = document.getElementById('execution-feedback-bar');
        const feedbackTitle = document.getElementById('feedback-status-title');
        const feedbackMsg = document.getElementById('feedback-msg');
        const feedbackLatency = document.getElementById('feedback-latency');

        feedbackBar.classList.remove('hidden');
        feedbackBar.className = 'p-2.5 rounded-lg border text-xs font-mono space-y-1 bg-slate-900 border-slate-700';
        feedbackTitle.textContent = 'DISPATCHING...';
        feedbackMsg.textContent = `Connecting to ${mode === 'LIVE' ? '0Click Webhook' : 'Local Simulation Simulator'}...`;

        try {
            const res = await fetch('/api/nifty-option-trader/dispatch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    mode: mode,
                    trade_style: currentTradeStyle,
                    action: currentAction,
                    option_type: currentOptionType,
                    strike: strike,
                    lots: currentLots,
                    spot_price: entryPrice,
                    nifty_sl: slPrice,
                    nifty_tp: tpPrice,
                    mtm_target: mtmTarget,
                    mtm_loss: mtmLoss,
                    auto_trailing: autoTrailing,
                    trail_step: trailStep,
                    webhook_url: webhookUrl,
                    webhook_token: webhookToken,
                    notes: `Institutional ${currentTradeStyle} Wall Execution`
                })
            });

            const data = await res.json();
            feedbackLatency.textContent = `${data.latency_ms || 0} ms`;

            if (data.success) {
                feedbackBar.className = 'p-2.5 rounded-lg border text-xs font-mono space-y-1 bg-emerald-950/70 border-emerald-600 text-emerald-200';
                feedbackTitle.textContent = `SUCCESS [${data.status}]`;
                feedbackMsg.textContent = `${currentAction} ${currentLots} Lots (${currentLots * 65} Qty) of NIFTY ${strike} ${currentOptionType} - Fill: ₹${data.api_response?.fill_price || 'Market'} | Latency: ${data.latency_ms}ms`;
            } else {
                feedbackBar.className = 'p-2.5 rounded-lg border text-xs font-mono space-y-1 bg-rose-950/70 border-rose-600 text-rose-200';
                feedbackTitle.textContent = `FAILED [${data.status_code || 500}]`;
                feedbackMsg.textContent = data.api_response?.error || data.api_response?.message || 'Webhook rejected or unreachable';
            }

            // Reload audit logs
            loadLogs();
        } catch (e) {
            feedbackBar.className = 'p-2.5 rounded-lg border text-xs font-mono space-y-1 bg-rose-950/70 border-rose-600 text-rose-200';
            feedbackTitle.textContent = 'NETWORK ERROR';
            feedbackMsg.textContent = e.message || 'Failed to dispatch request';
        }
    }

    document.getElementById('dispatch-0click-btn')?.addEventListener('click', () => executeOrder('LIVE'));
    document.getElementById('dispatch-sim-btn')?.addEventListener('click', () => executeOrder('SIMULATION'));

    // ── 9. Execution Audit Journal Fetcher ───────────────────────────────────
    async function loadLogs() {
        try {
            const res = await fetch(`/api/nifty-option-trader/logs?date=${selectedDate}&limit=20&_t=${Date.now()}`);
            const data = await res.json();
            const tbody = document.getElementById('logs-table-body');
            const badge = document.getElementById('logs-count-badge');
            if (!tbody) return;

            const logs = data.logs || [];
            badge.textContent = `${logs.length} Logs`;

            if (logs.length === 0) {
                tbody.innerHTML = `<tr><td colspan="11" class="py-4 text-center text-slate-500 font-sans text-xs">No orders dispatched in this session yet.</td></tr>`;
                return;
            }

            let html = '';
            logs.forEach(log => {
                const isExecuted = log.status === 'DISPATCHED' || log.status === 'SIMULATED';
                const statusColor = isExecuted ? 'text-emerald-400 bg-emerald-950/80 border-emerald-700/60' : 'text-rose-400 bg-rose-950/80 border-rose-700/60';
                const modeColor = log.mode === 'LIVE' ? 'text-cyan-400' : 'text-amber-400';
                const actionColor = log.action === 'BUY' ? 'text-emerald-400 font-bold' : 'text-rose-400 font-bold';

                html += `
                    <tr class="hover:bg-slate-900/60 transition">
                        <td class="py-2 px-3 text-slate-400">${log.candle_time || '--:--'}</td>
                        <td class="py-2 px-3 font-bold ${modeColor}">${log.mode || 'LIVE'}</td>
                        <td class="py-2 px-3 ${actionColor}">${log.action}</td>
                        <td class="py-2 px-3 font-bold text-white">${log.symbol} ${log.strike} ${log.instrument}</td>
                        <td class="py-2 px-3 text-right text-cyan-300">${log.lots} (${log.lots * 65})</td>
                        <td class="py-2 px-3 text-right font-mono text-emerald-400">${parseFloat(log.spot_price).toFixed(2)}</td>
                        <td class="py-2 px-3 text-right font-mono text-rose-400">${parseFloat(log.sl_price).toFixed(2)}</td>
                        <td class="py-2 px-3 text-right font-mono text-cyan-300">${parseFloat(log.tp_price).toFixed(2)}</td>
                        <td class="py-2 px-3 text-right text-slate-300">₹${log.payload?.mtm_target || 5000} / -₹${log.payload?.mtm_loss || 3000}</td>
                        <td class="py-2 px-3 text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold border ${statusColor}">${log.status}</span>
                        </td>
                        <td class="py-2 px-3 text-right text-slate-400">${log.latency_ms || 0}ms</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        } catch (e) {
            console.warn('Error fetching logs:', e);
        }
    }

    document.getElementById('refresh-logs-btn')?.addEventListener('click', loadLogs);
    document.getElementById('refresh-desk-btn')?.addEventListener('click', () => {
        loadCandlesticks().then(() => loadOrderFlowLevels());
        loadLogs();
    });

    // ── 10. Initial Boot ───────────────────────────────────────────────────
    initChart();
    loadCandlesticks().then(() => {
        loadOrderFlowLevels();
        // Pre-fill initial spot entry and visual bracket
        document.getElementById('bracket-spot-entry').value = spotPrice.toFixed(2);
        document.getElementById('bracket-spot-sl').value = (spotPrice - 5.0).toFixed(2);
        document.getElementById('bracket-spot-tp1').value = (spotPrice + 25.0).toFixed(2);
        document.getElementById('bracket-spot-tp2').value = (spotPrice + 50.0).toFixed(2);
        updateVisualBracket();
    });
    loadLogs();

    // Auto-refresh logs every 10 seconds
    setInterval(loadLogs, 10000);
});
</script>
@endpush
@endsection
