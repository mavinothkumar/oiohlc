@extends('layouts.app')

@section('title', 'NIFTY 50 Unified Breadth & Confluence Backtest Cockpit')

@push('styles')
    <!-- Chart.js 4.4 -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <!-- Flatpickr (Calendar Date Picker) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        body {
            background-color: #080b11 !important;
            color: #d1d5db;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .cockpit-panel {
            background: #0f1523;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        }

        .metric-card {
            background: linear-gradient(145deg, #131b2e, #0f1524);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 10px;
            padding: 1rem;
            position: relative;
            overflow: hidden;
        }
        .metric-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 2px;
            background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.4), transparent);
        }

        .trade-row:hover {
            background-color: rgba(255, 255, 255, 0.03);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #090d16;
        }
        ::-webkit-scrollbar-thumb {
            background: #1e293b;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #334155;
        }
    </style>
@endpush

@section('content')
<div class="w-full px-3 sm:px-6 py-4">

    {{-- Top Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
        <div>
            <div class="flex items-center gap-3">
                <span class="text-3xl">📊</span>
                <div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight text-white flex items-center gap-2">
                        NIFTY 50 Unified Breadth Backtest Cockpit
                        <span class="text-[10px] px-2.5 py-0.5 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20 font-bold uppercase tracking-wider">
                            Multi-Year Engine
                        </span>
                        @if($dbTradesCount > 0)
                            <span class="text-[10px] px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-bold uppercase tracking-wider" title="Pre-computed database active: instant sub-second filters!">
                                ⚡ {{ number_format($dbTradesCount) }} Trades Pre-Computed
                            </span>
                        @endif
                    </h1>
                    <p class="text-xs text-gray-400 mt-0.5">
                        Backtest 80% Stock Breadth surges & Options Flow Parity Confluence with Alarm Candle Low/High SL and Dual Target Profit.
                    </p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="{{ route('nifty.unified.monitor') }}" target="_blank"
               class="px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-200 border border-gray-700 text-xs font-semibold flex items-center gap-1.5 transition">
                <span>🎯 Live Unified Cockpit</span>
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            </a>
        </div>
    </div>

    {{-- Configuration & Filter Bar --}}
    <div class="cockpit-panel p-4 mb-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-3.5">
            {{-- Strategy Mode --}}
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1">Strategy Mode</label>
                <select id="cfgMode" class="w-full bg-[#0a0e17] border border-gray-700/80 rounded-lg px-3 py-1.5 text-xs text-gray-100 font-semibold focus:outline-none focus:border-blue-500">
                    <option value="stocks" selected>📈 Stock Breadth Only (2+ Years: Jan 2024 → Present)</option>
                    <option value="confluence">🔥 Dual Confluence (Options Parity + Stocks: May 2026 → Present)</option>
                    <option value="both">⚡ Combined (Confluence when available, else Stocks)</option>
                </select>
            </div>

            {{-- Date Presets --}}
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1">Date Range Preset</label>
                <select id="cfgPreset" onchange="applyDatePreset(this.value)" class="w-full bg-[#0a0e17] border border-gray-700/80 rounded-lg px-3 py-1.5 text-xs text-gray-100 font-semibold focus:outline-none focus:border-blue-500">
                    <option value="last30">Last 30 Days</option>
                    <option value="last90">Last 90 Days (~3 Months)</option>
                    <option value="ytd2026">2026 Year-to-Date</option>
                    <option value="year2025">2025 Full Year</option>
                    <option value="all" selected>All Available Data (2+ Years)</option>
                    <option value="custom">Custom Date Range</option>
                </select>
            </div>

            {{-- From Date --}}
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1">Start Date</label>
                <input type="text" id="cfgFromDate" value="{{ $minDate }}" class="w-full bg-[#0a0e17] border border-gray-700/80 rounded-lg px-3 py-1.5 text-xs text-gray-100 font-mono focus:outline-none focus:border-blue-500">
            </div>

            {{-- To Date --}}
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1">End Date</label>
                <input type="text" id="cfgToDate" value="{{ $maxDate }}" class="w-full bg-[#0a0e17] border border-gray-700/80 rounded-lg px-3 py-1.5 text-xs text-gray-100 font-mono focus:outline-none focus:border-blue-500">
            </div>
        </div>

        {{-- Parameter Tweaks Row --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 pt-3 border-t border-gray-800/80 items-end">
            {{-- Start Time --}}
            <div>
                <label class="block text-[10.5px] font-semibold text-gray-400 mb-1">Start Time</label>
                <select id="cfgStartTime" class="w-full bg-[#0a0e17] border border-gray-700/80 rounded px-2 py-1.5 text-xs text-cyan-300 font-mono">
                    @for ($h = 9; $h <= 15; $h++)
                        @for ($m = 0; $m < 60; $m += 5)
                            @php
                                if ($h == 9 && $m < 15) continue;
                                if ($h == 15 && $m > 30) continue;
                                $val = sprintf('%02d:%02d', $h, $m);
                                $ampm = $h >= 12 ? 'PM' : 'AM';
                                $displayH = $h > 12 ? $h - 12 : ($h == 0 ? 12 : $h);
                                $label = sprintf('%02d:%02d %s', $displayH, $m, $ampm);
                            @endphp
                            <option value="{{ $val }}" {{ $val === '09:15' ? 'selected' : '' }}>
                                {{ $label }}{{ $val === '09:15' ? ' (Open)' : '' }}
                            </option>
                        @endfor
                    @endfor
                </select>
            </div>

            {{-- End Time --}}
            <div>
                <label class="block text-[10.5px] font-semibold text-gray-400 mb-1">End Time</label>
                <select id="cfgEndTime" class="w-full bg-[#0a0e17] border border-gray-700/80 rounded px-2 py-1.5 text-xs text-cyan-300 font-mono">
                    @for ($h = 9; $h <= 15; $h++)
                        @for ($m = 0; $m < 60; $m += 5)
                            @php
                                if ($h == 9 && $m < 15) continue;
                                if ($h == 15 && $m > 30) continue;
                                $val = sprintf('%02d:%02d', $h, $m);
                                $ampm = $h >= 12 ? 'PM' : 'AM';
                                $displayH = $h > 12 ? $h - 12 : ($h == 0 ? 12 : $h);
                                $label = sprintf('%02d:%02d %s', $displayH, $m, $ampm);
                            @endphp
                            <option value="{{ $val }}" {{ $val === '15:30' ? 'selected' : '' }}>
                                {{ $label }}{{ $val === '15:30' ? ' (Close)' : '' }}
                            </option>
                        @endfor
                    @endfor
                </select>
            </div>

            {{-- SL Buffer --}}
            <div>
                <label class="block text-[10.5px] font-semibold text-gray-400 mb-1" title="Buffer pts added to High (Bear) or subtracted from Low (Bull)">SL Buffer</label>
                <div class="flex items-center gap-1">
                    <input type="number" id="cfgSlBuffer" value="15" min="0" max="50" class="w-full bg-[#0a0e17] border border-gray-700/80 rounded px-2 py-1.5 text-xs text-white font-mono text-center">
                    <span class="text-xs text-gray-500">p</span>
                </div>
            </div>

            {{-- Target 1 --}}
            <div>
                <label class="block text-[10.5px] font-semibold text-gray-400 mb-1">Target 1 (TP1)</label>
                <div class="flex items-center gap-1">
                    <input type="number" id="cfgTp1" value="100" min="20" max="300" class="w-full bg-[#0a0e17] border border-gray-700/80 rounded px-2 py-1.5 text-xs text-white font-mono text-center">
                    <span class="text-xs text-gray-500">p</span>
                </div>
            </div>

            {{-- Target 2 --}}
            <div>
                <label class="block text-[10.5px] font-semibold text-gray-400 mb-1">Target 2 (TP2)</label>
                <div class="flex items-center gap-1">
                    <input type="number" id="cfgTp2" value="150" min="50" max="500" class="w-full bg-[#0a0e17] border border-gray-700/80 rounded px-2 py-1.5 text-xs text-white font-mono text-center">
                    <span class="text-xs text-gray-500">p</span>
                </div>
            </div>

            {{-- Stock Threshold --}}
            <div>
                <label class="block text-[10.5px] font-semibold text-gray-400 mb-1">Surge %</label>
                <div class="flex items-center gap-1">
                    <input type="number" id="cfgStockThresh" value="80" min="50" max="95" class="w-full bg-[#0a0e17] border border-gray-700/80 rounded px-2 py-1.5 text-xs text-white font-mono text-center">
                    <span class="text-xs text-gray-500">%</span>
                </div>
            </div>

            {{-- Max Trades / Day --}}
            <div>
                <label class="block text-[10.5px] font-semibold text-gray-400 mb-1">Max Trades</label>
                <input type="number" id="cfgMaxTrades" value="2" min="1" max="5" class="w-full bg-[#0a0e17] border border-gray-700/80 rounded px-2 py-1.5 text-xs text-white font-mono text-center">
            </div>

            {{-- Execute Button --}}
            <div>
                <button id="btnRunBacktest" onclick="executeBacktest()"
                        class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold py-1.5 px-3 rounded-lg shadow-lg flex items-center justify-center gap-1.5 text-xs transition duration-150">
                    <span id="btnIcon">🚀</span>
                    <span id="btnLabel">Launch</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Execution Status Banner (Hidden initially) --}}
    <div id="statusBanner" class="hidden cockpit-panel p-4 mb-6 border-blue-500/30 bg-blue-950/20 text-center animate-pulse">
        <div class="flex items-center justify-center gap-3">
            <svg class="animate-spin h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
            <span class="text-sm font-semibold text-blue-200" id="statusMessage">Running multi-year simulation across historical candles...</span>
        </div>
    </div>

    {{-- Results Container --}}
    <div id="resultsContainer" class="space-y-6">

        {{-- KPI Summary Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="metric-card">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Total Trades</span>
                <div class="text-2xl font-black text-white mt-1" id="kpiTotalTrades">--</div>
                <div class="text-[11px] text-gray-400 mt-0.5" id="kpiTradingDays">-- days analyzed</div>
            </div>

            <div class="metric-card">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Win Rate</span>
                <div class="text-2xl font-black text-emerald-400 mt-1" id="kpiWinRate">--%</div>
                <div class="text-[11px] text-gray-400 mt-0.5" id="kpiWinsLosses">-- W / -- L</div>
            </div>

            <div class="metric-card">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Net Profit</span>
                <div class="text-2xl font-black text-emerald-400 mt-1" id="kpiNetPoints">-- pts</div>
                <div class="text-[11px] text-emerald-400 font-mono mt-0.5" id="kpiNetRupees">₹-- (1 Lot)</div>
            </div>

            <div class="metric-card">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Profit Factor</span>
                <div class="text-2xl font-black text-blue-400 mt-1" id="kpiProfitFactor">--</div>
                <div class="text-[11px] text-gray-400 mt-0.5" id="kpiGrossWinLoss">Win/Loss ratio</div>
            </div>

            <div class="metric-card">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Max Drawdown</span>
                <div class="text-2xl font-black text-rose-400 mt-1" id="kpiMaxDrawdown">-- pts</div>
                <div class="text-[11px] text-rose-400/80 mt-0.5" id="kpiDrawdownRupees">Peak-to-trough</div>
            </div>

            <div class="metric-card">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Avg Realized R:R</span>
                <div class="text-2xl font-black text-amber-400 mt-1" id="kpiAvgRR">1 : --</div>
                <div class="text-[11px] text-gray-400 mt-0.5" id="kpiAvgDuration">Avg -- mins/trade</div>
            </div>
        </div>

        {{-- Visual Charts Row 1: Equity Curve & Drawdown --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Equity Curve (2 cols) --}}
            <div class="cockpit-panel p-5 lg:col-span-2">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                            <span>📈 Cumulative Net Points Growth</span>
                        </h3>
                        <p class="text-xs text-gray-400">Equity curve tracking running points won across all simulated trades.</p>
                    </div>
                    <div class="text-xs font-mono px-2.5 py-1 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-bold" id="badgeEquityGrowth">
                        +0.0 pts
                    </div>
                </div>
                <div class="h-72 w-full">
                    <canvas id="chartEquityCurve"></canvas>
                </div>
            </div>

            {{-- Outcome Distribution Donut (1 col) --}}
            <div class="cockpit-panel p-5">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-1 flex items-center gap-2">
                    <span>🎯 Trade Outcome Breakdown</span>
                </h3>
                <p class="text-xs text-gray-400 mb-3">Distribution of targets reached vs stop losses hit.</p>
                <div class="h-64 w-full relative flex items-center justify-center">
                    <canvas id="chartOutcomes"></canvas>
                </div>
            </div>
        </div>

        {{-- Visual Charts Row 2: Drawdown & Monthly Breakdown --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Drawdown Underwater Chart --}}
            <div class="cockpit-panel p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span>🌊 Drawdown Underwater Profile</span>
                    </h3>
                    <span class="text-xs text-rose-400 font-mono font-bold" id="badgeMaxDd">Max: -0 pts</span>
                </div>
                <div class="h-56 w-full">
                    <canvas id="chartDrawdown"></canvas>
                </div>
            </div>

            {{-- Monthly Performance Bar Chart --}}
            <div class="cockpit-panel p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span>📅 Monthly P&L Breakdown (Points)</span>
                    </h3>
                    <span class="text-xs text-gray-400" id="badgeMonthlyStats">Monthly consistency</span>
                </div>
                <div class="h-56 w-full">
                    <canvas id="chartMonthly"></canvas>
                </div>
            </div>
        </div>

        {{-- Interactive Trade Log Table --}}
        <div class="cockpit-panel p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <div>
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span>📋 Detailed Trade Log</span>
                    </h3>
                    <p class="text-xs text-gray-400">Complete record of every trigger, entry spot, SL, exit, and points captured.</p>
                </div>

                {{-- Table Filter Pills --}}
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <button onclick="filterTrades('all')" class="trade-filter-btn px-2.5 py-1 rounded-md font-semibold bg-blue-600 text-white" data-filter="all">All (<span id="cntAll">0</span>)</button>
                    <button onclick="filterTrades('win')" class="trade-filter-btn px-2.5 py-1 rounded-md font-semibold bg-gray-800 text-gray-300 hover:bg-gray-700" data-filter="win">Wins (<span id="cntWin">0</span>)</button>
                    <button onclick="filterTrades('loss')" class="trade-filter-btn px-2.5 py-1 rounded-md font-semibold bg-gray-800 text-gray-300 hover:bg-gray-700" data-filter="loss">Losses (<span id="cntLoss">0</span>)</button>
                    <button onclick="filterTrades('bull')" class="trade-filter-btn px-2.5 py-1 rounded-md font-semibold bg-gray-800 text-gray-300 hover:bg-gray-700" data-filter="bull">🐂 Bull (<span id="cntBull">0</span>)</button>
                    <button onclick="filterTrades('bear')" class="trade-filter-btn px-2.5 py-1 rounded-md font-semibold bg-gray-800 text-gray-300 hover:bg-gray-700" data-filter="bear">🐻 Bear (<span id="cntBear">0</span>)</button>
                    <button onclick="exportTradesCsv()" class="px-2.5 py-1 rounded-md font-semibold bg-gray-800 hover:bg-gray-700 text-gray-300 border border-gray-700 flex items-center gap-1">
                        <span>⬇️ CSV</span>
                    </button>
                </div>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto rounded-lg border border-gray-800">
                <table class="w-full text-left text-xs text-gray-300">
                    <thead class="bg-[#0b101c] text-gray-400 uppercase font-mono text-[10px] tracking-wider border-b border-gray-800">
                        <tr>
                            <th class="py-2.5 px-3">#</th>
                            <th class="py-2.5 px-3">Date</th>
                            <th class="py-2.5 px-3">Direction</th>
                            <th class="py-2.5 px-3">Signal Type</th>
                            <th class="py-2.5 px-3">Entry Time</th>
                            <th class="py-2.5 px-3">Entry Spot</th>
                            <th class="py-2.5 px-3">Candle SL (Risk)</th>
                            <th class="py-2.5 px-3">Exit Time</th>
                            <th class="py-2.5 px-3">Exit Spot</th>
                            <th class="py-2.5 px-3">Outcome</th>
                            <th class="py-2.5 px-3">Duration</th>
                            <th class="py-2.5 px-3 text-right">Net Points</th>
                            <th class="py-2.5 px-3 text-right">Realized R:R</th>
                        </tr>
                    </thead>
                    <tbody id="tradeTableBody" class="divide-y divide-gray-800/60 font-mono">
                        <tr>
                            <td colspan="13" class="py-8 text-center text-gray-500 font-sans">
                                Select your desired date range and click <strong class="text-blue-400">Launch Backtest</strong> to compute.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
    let globalTrades = [];
    let chartEquity = null;
    let chartDrawdown = null;
    let chartMonthly = null;
    let chartOutcomes = null;

    document.addEventListener('DOMContentLoaded', () => {
        flatpickr('#cfgFromDate', { dateFormat: 'Y-m-d', theme: 'dark' });
        flatpickr('#cfgToDate', { dateFormat: 'Y-m-d', theme: 'dark' });

        // Set default date preset (Last 30 Days) without auto-executing heavy simulation
        applyDatePreset('last30');
    });

    function applyDatePreset(preset) {
        const toInput = document.getElementById('cfgToDate');
        const fromInput = document.getElementById('cfgFromDate');
        const maxDate = '{{ $maxDate }}';
        toInput.value = maxDate;

        const maxD = new Date(maxDate);

        if (preset === 'last30') {
            const d = new Date(maxD);
            d.setDate(d.getDate() - 30);
            fromInput.value = d.toISOString().split('T')[0];
        } else if (preset === 'last90') {
            const d = new Date(maxD);
            d.setDate(d.getDate() - 90);
            fromInput.value = d.toISOString().split('T')[0];
        } else if (preset === 'ytd2026') {
            fromInput.value = '2026-01-01';
        } else if (preset === 'year2025') {
            fromInput.value = '2025-01-01';
            toInput.value = '2025-12-31';
        } else if (preset === 'all') {
            fromInput.value = '{{ $minDate }}';
            toInput.value = maxDate;
        }
    }

    async function executeBacktest() {
        const btn = document.getElementById('btnRunBacktest');
        const btnIcon = document.getElementById('btnIcon');
        const btnLabel = document.getElementById('btnLabel');
        const statusBanner = document.getElementById('statusBanner');
        const statusMsg = document.getElementById('statusMessage');

        btn.disabled = true;
        btn.classList.add('opacity-70', 'cursor-not-allowed');
        btnIcon.innerHTML = '⏳';
        btnLabel.innerText = 'Calculating...';
        statusBanner.classList.remove('hidden');

        const mode = document.getElementById('cfgMode').value;
        const fromDate = document.getElementById('cfgFromDate').value;
        const toDate = document.getElementById('cfgToDate').value;
        const slBuffer = parseFloat(document.getElementById('cfgSlBuffer').value) || 15;
        const tp1 = parseFloat(document.getElementById('cfgTp1').value) || 100;
        const tp2 = parseFloat(document.getElementById('cfgTp2').value) || 150;
        const stockThresh = parseFloat(document.getElementById('cfgStockThresh').value) || 80;
        const maxTrades = parseInt(document.getElementById('cfgMaxTrades').value) || 2;
        const startTime = document.getElementById('cfgStartTime').value || '09:15';
        const endTime = document.getElementById('cfgEndTime').value || '15:30';

        statusMsg.innerText = `Simulating ${mode.toUpperCase()} strategy from ${fromDate} to ${toDate} (${startTime} to ${endTime})...`;

        try {
            const resp = await fetch('{{ route("api.nifty.unified.backtest.run") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    mode: mode,
                    from_date: fromDate,
                    to_date: toDate,
                    sl_buffer: slBuffer,
                    tp1: tp1,
                    tp2: tp2,
                    stock_threshold: stockThresh,
                    option_threshold: 75,
                    max_trades_per_day: maxTrades,
                    start_time: startTime,
                    end_time: endTime
                })
            });

            if (!resp.ok) {
                if (resp.status === 504) {
                    alert('Server Gateway Timeout (HTTP 504): The simulation took longer than 60s. Please run "php artisan backtest:unified-breadth --save-db" to pre-populate all trades into database for instant sub-second filtering.');
                    return;
                }
                const errText = await resp.text();
                alert(`Server returned HTTP ${resp.status}: ${errText.substring(0, 150)}`);
                return;
            }

            const data = await resp.json();
            if (!data.success) {
                alert('Backtest error: ' + (data.message || 'Unknown error'));
                return;
            }

            globalTrades = data.trades || [];
            renderKPIs(data.stats, data.execution_time);
            renderCharts(data.charts, data.stats);
            renderTradesTable(globalTrades);

        } catch (err) {
            console.error('Backtest request failed:', err);
            alert('Failed to execute backtest: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.classList.remove('opacity-70', 'cursor-not-allowed');
            btnIcon.innerHTML = '🚀';
            btnLabel.innerText = 'Launch Backtest';
            statusBanner.classList.add('hidden');
        }
    }

    function renderKPIs(stats, execTime) {
        document.getElementById('kpiTotalTrades').innerText = stats.total_trades || 0;
        document.getElementById('kpiTradingDays').innerText = `${stats.total_trading_days || 0} days (${execTime}s)`;

        const wrEl = document.getElementById('kpiWinRate');
        wrEl.innerText = `${stats.win_rate_pct || 0}%`;
        wrEl.className = 'text-2xl font-black mt-1 ' + (stats.win_rate_pct >= 50 ? 'text-emerald-400' : 'text-amber-400');
        document.getElementById('kpiWinsLosses').innerText = `${stats.wins || 0} Wins / ${stats.losses || 0} Losses`;

        const netPnlEl = document.getElementById('kpiNetPoints');
        const netPts = stats.net_pnl_pts || 0;
        netPnlEl.innerText = `${netPts >= 0 ? '+' : ''}${netPts.toLocaleString()} pts`;
        netPnlEl.className = 'text-2xl font-black mt-1 ' + (netPts >= 0 ? 'text-emerald-400' : 'text-rose-400');

        // NIFTY 1 Lot = 25 or 75 qty (simulating approx ₹25/point)
        const rupees = Math.round(netPts * 25);
        document.getElementById('kpiNetRupees').innerText = `₹${rupees >= 0 ? '+' : ''}${rupees.toLocaleString()} (1 Lot)`;

        document.getElementById('kpiProfitFactor').innerText = stats.profit_factor || '0.0';
        document.getElementById('kpiGrossWinLoss').innerText = `+${stats.gross_wins_pts || 0} / -${stats.gross_losses_pts || 0} pts`;

        document.getElementById('kpiMaxDrawdown').innerText = `-${stats.max_drawdown_pts || 0} pts`;
        document.getElementById('badgeMaxDd').innerText = `Max DD: -${stats.max_drawdown_pts || 0} pts`;

        document.getElementById('kpiAvgRR').innerText = `1 : ${stats.avg_rr_ratio || 0}`;
        document.getElementById('kpiAvgDuration').innerText = `Avg ${stats.avg_duration_minutes || 0} mins/trade`;

        const badgeEq = document.getElementById('badgeEquityGrowth');
        badgeEq.innerText = `${netPts >= 0 ? '+' : ''}${netPts} pts`;
        badgeEq.className = `text-xs font-mono px-2.5 py-1 rounded font-bold border ${netPts >= 0 ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border-rose-500/20'}`;
    }

    function renderCharts(charts, stats) {
        // 1. Equity Curve
        if (chartEquity) chartEquity.destroy();
        const ctxEq = document.getElementById('chartEquityCurve').getContext('2d');
        const gradient = ctxEq.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(16, 185, 129, 0.35)');
        gradient.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

        chartEquity = new Chart(ctxEq, {
            type: 'line',
            data: {
                labels: charts.equity.labels,
                datasets: [{
                    label: 'Cumulative Net Points',
                    data: charts.equity.values,
                    borderColor: '#10b981',
                    borderWidth: 2,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.2,
                    pointRadius: charts.equity.values.length > 100 ? 0 : 2,
                    pointHoverRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `Net P&L: ${ctx.parsed.y >= 0 ? '+' : ''}${ctx.parsed.y} pts`
                        }
                    }
                },
                scales: {
                    x: {
                        display: false
                    },
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#9ca3af', font: { family: 'monospace' } }
                    }
                }
            }
        });

        // 2. Outcomes Donut
        if (chartOutcomes) chartOutcomes.destroy();
        const ctxOc = document.getElementById('chartOutcomes').getContext('2d');
        const oc = stats.outcomes || {};
        chartOutcomes = new Chart(ctxOc, {
            type: 'doughnut',
            data: {
                labels: ['TP2 Reached (+150p)', 'TP1 Reached (+100p)', 'Candle SL Hit', 'EOD Profit', 'EOD Loss'],
                datasets: [{
                    data: [
                        oc.tp2_hits || 0,
                        (oc.tp1_hits || 0) - (oc.tp2_hits || 0),
                        oc.sl_hits || 0,
                        oc.eod_profits || 0,
                        oc.eod_losses || 0
                    ],
                    backgroundColor: ['#10b981', '#34d399', '#f43f5e', '#38bdf8', '#fbbf24'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#9ca3af', boxWidth: 10, font: { size: 10 } }
                    }
                },
                cutout: '70%'
            }
        });

        // 3. Drawdown Chart
        if (chartDrawdown) chartDrawdown.destroy();
        const ctxDd = document.getElementById('chartDrawdown').getContext('2d');
        const ddGrad = ctxDd.createLinearGradient(0, 0, 0, 200);
        ddGrad.addColorStop(0, 'rgba(244, 63, 94, 0.0)');
        ddGrad.addColorStop(1, 'rgba(244, 63, 94, 0.35)');

        chartDrawdown = new Chart(ctxDd, {
            type: 'line',
            data: {
                labels: charts.drawdown.labels,
                datasets: [{
                    label: 'Drawdown (pts)',
                    data: charts.drawdown.values,
                    borderColor: '#f43f5e',
                    borderWidth: 1.5,
                    backgroundColor: ddGrad,
                    fill: true,
                    tension: 0.1,
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { display: false },
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#9ca3af', font: { family: 'monospace' } }
                    }
                }
            }
        });

        // 4. Monthly Bar Chart
        if (chartMonthly) chartMonthly.destroy();
        const ctxMo = document.getElementById('chartMonthly').getContext('2d');
        const moColors = charts.monthly.values.map(v => v >= 0 ? '#10b981' : '#f43f5e');

        chartMonthly = new Chart(ctxMo, {
            type: 'bar',
            data: {
                labels: charts.monthly.labels,
                datasets: [{
                    label: 'Monthly Points',
                    data: charts.monthly.values,
                    backgroundColor: moColors,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#9ca3af', font: { family: 'monospace', size: 10 } }
                    },
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#9ca3af', font: { family: 'monospace' } }
                    }
                }
            }
        });
    }

    function renderTradesTable(trades) {
        const tbody = document.getElementById('tradeTableBody');
        tbody.innerHTML = '';

        if (!trades || trades.length === 0) {
            tbody.innerHTML = '<tr><td colspan="13" class="py-8 text-center text-gray-500">No trades matching criteria.</td></tr>';
            return;
        }

        // Update counts
        document.getElementById('cntAll').innerText = trades.length;
        document.getElementById('cntWin').innerText = trades.filter(t => t.net_pts > 0).length;
        document.getElementById('cntLoss').innerText = trades.filter(t => t.net_pts <= 0).length;
        document.getElementById('cntBull').innerText = trades.filter(t => t.direction === 'BULL').length;
        document.getElementById('cntBear').innerText = trades.filter(t => t.direction === 'BEAR').length;

        trades.forEach((t, i) => {
            const isWin = t.net_pts > 0;
            const dirColor = t.direction === 'BULL' ? 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20' : 'text-rose-400 bg-rose-500/10 border-rose-500/20';
            const outcomeBadge = getOutcomeBadge(t.outcome);

            const tr = document.createElement('tr');
            tr.className = 'trade-row transition duration-100';
            tr.innerHTML = `
                <td class="py-2 px-3 text-gray-500">${i + 1}</td>
                <td class="py-2 px-3 font-semibold text-gray-200">${t.date}</td>
                <td class="py-2 px-3">
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold border ${dirColor}">
                        ${t.direction === 'BULL' ? '🐂 BULL' : '🐻 BEAR'}
                    </span>
                </td>
                <td class="py-2 px-3 text-gray-400 text-[11px]">${t.signal_type || 'SURGE'}</td>
                <td class="py-2 px-3 text-gray-200">${t.entry_time}</td>
                <td class="py-2 px-3 text-white font-bold">${t.entry_price.toFixed(2)}</td>
                <td class="py-2 px-3 text-rose-400">${t.sl_price.toFixed(2)} <span class="text-gray-500">(${t.risk_pts}p)</span></td>
                <td class="py-2 px-3 text-gray-300">${t.exit_time || '--'}</td>
                <td class="py-2 px-3 text-gray-200">${t.exit_price ? t.exit_price.toFixed(2) : '--'}</td>
                <td class="py-2 px-3">${outcomeBadge}</td>
                <td class="py-2 px-3 text-gray-400">${(t.candles_held || 0) * 5}m</td>
                <td class="py-2 px-3 text-right font-bold ${isWin ? 'text-emerald-400' : 'text-rose-400'}">
                    ${t.net_pts >= 0 ? '+' : ''}${t.net_pts.toFixed(2)}
                </td>
                <td class="py-2 px-3 text-right text-gray-400 font-mono">
                    ${t.rr_realized !== undefined ? t.rr_realized.toFixed(2) : '--'}
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function getOutcomeBadge(outcome) {
        switch (outcome) {
            case 'TP2_HIT':
                return '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">TP2 (+150p)</span>';
            case 'TP1_HIT':
                return '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-teal-500/20 text-teal-300 border border-teal-500/30">TP1 (+100p)</span>';
            case 'SL_HIT':
                return '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30">SL Hit</span>';
            case 'EOD_PROFIT':
                return '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-500/20 text-sky-300 border border-sky-500/30">EOD Win</span>';
            case 'EOD_LOSS':
                return '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">EOD Loss</span>';
            default:
                return `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-800 text-gray-400">${outcome}</span>`;
        }
    }

    function filterTrades(filterType) {
        document.querySelectorAll('.trade-filter-btn').forEach(b => {
            if (b.dataset.filter === filterType) {
                b.className = 'trade-filter-btn px-2.5 py-1 rounded-md font-semibold bg-blue-600 text-white';
            } else {
                b.className = 'trade-filter-btn px-2.5 py-1 rounded-md font-semibold bg-gray-800 text-gray-300 hover:bg-gray-700';
            }
        });

        let filtered = globalTrades;
        if (filterType === 'win') filtered = globalTrades.filter(t => t.net_pts > 0);
        else if (filterType === 'loss') filtered = globalTrades.filter(t => t.net_pts <= 0);
        else if (filterType === 'bull') filtered = globalTrades.filter(t => t.direction === 'BULL');
        else if (filterType === 'bear') filtered = globalTrades.filter(t => t.direction === 'BEAR');

        renderTradesTable(filtered);
    }

    function exportTradesCsv() {
        if (!globalTrades || globalTrades.length === 0) {
            alert('No trades available to export.');
            return;
        }

        const headers = ['Index', 'Date', 'Direction', 'SignalType', 'EntryTime', 'EntryPrice', 'SLPrice', 'RiskPoints', 'ExitTime', 'ExitPrice', 'Outcome', 'NetPoints', 'RealizedRR'];
        const csvRows = [headers.join(',')];

        globalTrades.forEach((t, i) => {
            csvRows.push([
                i + 1,
                t.date,
                t.direction,
                t.signal_type,
                t.entry_time,
                t.entry_price,
                t.sl_price,
                t.risk_pts,
                t.exit_time || '',
                t.exit_price || '',
                t.outcome,
                t.net_pts,
                t.rr_realized || ''
            ].join(','));
        });

        const blob = new Blob([csvRows.join('\n')], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `nifty_breadth_backtest_${new Date().toISOString().slice(0, 10)}.csv`;
        a.click();
    }
</script>
@endsection
