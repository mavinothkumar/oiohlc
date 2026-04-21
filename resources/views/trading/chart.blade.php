@extends('layouts.app')

@section('title', 'NIFTY Strike Charts')

@push('styles')
    <style>

        .signal-table th {
            text-align: left;
            padding: 8px 10px;
            color: #64748b;
            font-weight: 700;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            font-size: 11px;
            letter-spacing: .02em;
        }

        .signal-table td {
            vertical-align: top;
            padding: 10px;
            border-bottom: 1px solid #f1f5f9;
        }

        .signal-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .signal-strike-cell {
            min-width: 84px;
        }

        .signal-strike-main {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
        }

        .signal-strike-score {
            margin-top: 4px;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
        }

        .signal-bias-cell {
            width: 72px;
        }

        .signal-bias {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 56px;
            border-radius: 9999px;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 800;
            line-height: 1;
            letter-spacing: .03em;
        }

        .signal-bias--bull {
            background: #dcfce7;
            color: #166534;
        }

        .signal-bias--bear {
            background: #fee2e2;
            color: #b91c1c;
        }

        .signal-bias--ce {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .signal-bias--pe {
            background: #ede9fe;
            color: #6d28d9;
        }

        .signal-bias--neutral {
            background: #e2e8f0;
            color: #475569;
        }

        .signal-detail-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .signal-pill {
            border-radius: 9999px;
            padding: 5px 8px;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.1;
            background: #eef2ff;
            color: #334155;
            border: 1px solid #e2e8f0;
        }

        .signal-time-block {
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            background: #fff;
            padding: 12px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
            margin-bottom: 12px;
        }

        .signal-time-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .signal-time-label {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
        }

        .signal-time-meta {
            margin-top: 2px;
            font-size: 11px;
            color: #64748b;
        }

        .signal-overall {
            border-radius: 9999px;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .signal-overall--bullish {
            background: #dcfce7;
            color: #166534;
        }

        .signal-overall--buy {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .signal-overall--sell {
            background: #fee2e2;
            color: #b91c1c;
        }

        .signal-overall--bearish {
            background: #fecaca;
            color: #991b1b;
        }

        .signal-overall--ignore {
            background: #e2e8f0;
            color: #475569;
        }

        .signal-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 12px;
        }

        .signal-table th {
            text-align: left;
            padding: 8px 10px;
            color: #64748b;
            font-weight: 700;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .signal-table td {
            vertical-align: top;
            padding: 10px;
            border-bottom: 1px solid #f1f5f9;
        }

        .signal-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .signal-table-score {
            font-weight: 800;
            color: #0f172a;
        }

        .signal-table-side {
            display: inline-flex;
            align-items: center;
            border-radius: 9999px;
            padding: 3px 8px;
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
        }

        .signal-table-side--ce {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .signal-table-side--pe {
            background: #ede9fe;
            color: #6d28d9;
        }

        .signal-table-side--neutral {
            background: #e2e8f0;
            color: #475569;
        }

        .signal-detail-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        #signalToastHost {
            position: fixed !important;
            top: 16px !important;
            right: 16px !important;
            z-index: 10050 !important;
            pointer-events: none;
        }

        #signalToastHost .signal-toast {
            pointer-events: auto;
            position: relative;
            z-index: 10051;
        }

        #chartsGrid,
        .chart-box,
        article {
            position: relative;
            z-index: 1;
        }


        #signalPanel,
        #signalPanelDrawer,
        #signalPanelToggle,
        #signalToastHost {
            z-index: 10000 !important;
        }

        .chart-tooltip {
            z-index: 30 !important;
        }

        .signal-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 9999px;
            border: 1px solid #dbeafe;
            background: #eff6ff;
            color: #1d4ed8;
            padding: 4px 10px;
            font-size: 11px;
            line-height: 1;
            font-weight: 700;
        }
        .signal-pill--warn {
            border-color: #fde68a;
            background: #fefce8;
            color: #a16207;
        }
        .signal-pill--danger {
            border-color: #fecaca;
            background: #fef2f2;
            color: #b91c1c;
        }
        .signal-stat {
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 10px 12px;
        }
        .signal-stat-label {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .signal-stat-value {
            margin-top: 4px;
            font-size: 18px;
            line-height: 1;
            font-weight: 800;
            color: #0f172a;
        }
        .signal-item {
            border-radius: 18px;
            border: 1px solid #e2e8f0;
            background: #fff;
            padding: 12px;
            box-shadow: 0 8px 24px rgba(15,23,42,.05);
        }
        .signal-item__head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
        }
        .signal-item__call {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
        }
        .signal-item__meta {
            margin-top: 2px;
            font-size: 11px;
            color: #64748b;
        }
        .signal-item__score {
            border-radius: 9999px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 4px 9px;
            font-size: 11px;
            font-weight: 800;
            color: #0f172a;
            white-space: nowrap;
        }
        .signal-item__reasons {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .signal-toast {
            pointer-events: auto;
            border-radius: 18px;
            border: 1px solid #dbeafe;
            background: rgba(255,255,255,.97);
            box-shadow: 0 18px 42px rgba(15,23,42,.18);
            padding: 14px 14px 12px;
            backdrop-filter: blur(10px);
            animation: signal-toast-in .28s ease-out;
        }
        .signal-toast--buyce { border-color: #93c5fd; }
        .signal-toast--buype { border-color: #c4b5fd; }
        .signal-toast--neutral { border-color: #cbd5e1; }
        @keyframes signal-toast-in {
            from { opacity: 0; transform: translateY(-8px) translateX(8px); }
            to   { opacity: 1; transform: translateY(0) translateX(0); }
        }

        /* Chart host needs explicit pixel height for LightweightCharts to initialise */
        .chart-box {
            height: calc(58vh - 64px);
            min-height: 420px;
            width: 100%;
        }

        /* font-variant-numeric not in Tailwind v3 */
        .tabular-nums {
            font-variant-numeric: tabular-nums;
        }

        .full-screen-chart {
            position: fixed !important;
            inset: 0 !important;
            z-index: 9999;
            border-radius: 0 !important;
            background: #fff;
            display: flex !important;
            flex-direction: column;
            width: 100vw !important;
            height: 100vh !important;
            margin: 0 !important;
        }

        .full-screen-chart > .px-2.py-2 {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .full-screen-chart .chart-box {
            flex: 1 1 auto;
            width: 100% !important;
            height: 100% !important;
            min-height: 0 !important;
        }

        /* Filter panel slide transition */
        #filterPanel {
            overflow: hidden;
            transition: max-height 0.35s ease, opacity 0.35s ease, padding 0.35s ease;
        }

        #filterPanel.collapsed {
            max-height: 0 !important;
            opacity: 0;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }

        /* Live pulse ring */
        .live-ring::after {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 9999px;
            background: rgba(34, 197, 94, .18);
            animation: live-pulse 2s ease-in-out infinite;
        }

        .chart-summary {
            border-top: 1px solid #e2e8f0;
            margin-top: 8px;
            padding: 8px 4px 0;
        }

        .chart-summary-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 6px;
        }

        .chart-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border-radius: 9999px;
            padding: 3px 8px;
            font-size: 11px;
            line-height: 1;
            font-weight: 600;
            border: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .chart-chip--ceoi {
            background: #ecfeff;
            color: #0f766e;
        }

        .chart-chip--cevol {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .chart-chip--peoi {
            background: #faf5ff;
            color: #7c3aed;
        }

        .chart-chip--pevol {
            background: #fff7ed;
            color: #9a3412;
        }

        .chart-tooltip {
            position: absolute;
            z-index: 30;
            pointer-events: none;
            min-width: 210px;
            max-width: 260px;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            background: rgba(255, 255, 255, .96);
            box-shadow: 0 10px 30px rgba(15, 23, 42, .12);
            color: #0f172a;
            font-size: 12px;
            line-height: 1.35;
            display: none;
        }

        .chart-tooltip-grid {
            display: grid;
            grid-template-columns: auto auto;
            gap: 3px 10px;
        }

        .chart-tooltip-label {
            color: #64748b;
        }

        .chart-tooltip-value {
            text-align: right;
            font-variant-numeric: tabular-nums;
            font-weight: 600;
        }

        @keyframes live-pulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: .4;
                transform: scale(1.5);
            }
        }
    </style>
@endpush

@section('content')
    <script src="https://cdn.jsdelivr.net/npm/lightweight-charts@4.2.3/dist/lightweight-charts.standalone.production.js"></script>

    @php
        $routeChartData = route('trading.chart.data');
        // Pass PHP data to JS as JSON
        $jsConfig = json_encode([
            'symbol'     => $symbol,
            'expiry'     => $expiry,
            'tradeDate'  => $tradeDate,
            'midPoint'   => $midPoint,
            'strikes'    => $strikes,
            'chartDataUrl' => $routeChartData,
        ]);
    @endphp

    {{-- ── Top bar: minimal, just status + toggle ────────────────────────── --}}
    <div class="mx-auto max-w-[1800px] px-3 pt-3 pb-1">
        <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-2.5 shadow-sm">
            <div class="flex items-center gap-3">
            <span class="relative flex h-2.5 w-2.5">
                <span class="live-ring relative inline-flex h-2.5 w-2.5 rounded-full bg-green-500"></span>
            </span>
                <span class="text-sm font-semibold text-slate-800">NIFTY Strike Charts</span>
                <span id="statusBadge" class="rounded-full bg-teal-50 px-2.5 py-0.5 text-xs font-medium text-teal-700">Loading…</span>
            </div>
            <div class="flex items-center gap-2">
                {{-- Build-up legend pills --}}
                <div class="hidden items-center gap-1.5 sm:flex">
                    @foreach([['#dc2626','Short Build'],['#16a34a','Long Build'],['#eab308','Long Unwind'],['#1e3a8a','Short Cover']] as [$c,$l])
                        <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] text-slate-500">
                    <span class="h-2 w-2 rounded-full" style="background:{{$c}}"></span>{{$l}}
                </span>
                    @endforeach
                </div>
                <span class="hidden text-slate-300 sm:inline">|</span>
                <span class="text-[11px] text-slate-500">Last: <span id="lastUpdatedText" class="tabular-nums font-semibold text-slate-700">–</span></span>
                <button id="refreshNowBtn" title="Refresh now"
                    class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Refresh
                </button>
                <button id="toggleFilterBtn"
                    class="inline-flex items-center gap-1 rounded-xl border border-teal-200 bg-teal-50 px-2.5 py-1.5 text-xs font-semibold text-teal-700 transition hover:bg-teal-100">
                    <svg id="toggleFilterIcon" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18M7 8h10M10 12h4"/>
                    </svg>
                    <span id="toggleFilterLabel">Filters</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ── Filter panel (collapsed by default) ───────────────────────────── --}}
    <div class="mx-auto max-w-[1800px] px-3">
        <div id="filterPanel" class="collapsed rounded-2xl border border-slate-200 bg-white shadow-sm"
            style="max-height: 600px;">
            <form id="chartFilterForm" class="space-y-4 px-5 py-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-slate-600">Symbol</label>
                        <input id="underlying_symbol" name="underlying_symbol" type="text" readonly
                            value="{{ $symbol }}"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-700 outline-none">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-slate-600">Expiry Date</label>
                        <input id="expiry_date" name="expiry_date" type="text" readonly
                            value="{{ $expiry }}"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-700 outline-none">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-slate-600">Trade Date</label>
                        <input type="date" id="trade_date" name="trade_date"
                            value="{{ $tradeDate }}"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none transition focus:border-teal-400 focus:ring-2 focus:ring-teal-400/10">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-slate-600">MidPoint</label>
                        <input type="number" step="0.01" id="midpoint" name="midpoint"
                            value="{{ $midPoint }}"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none transition focus:border-teal-400 focus:ring-2 focus:ring-teal-400/10">
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-xs font-semibold text-slate-600">Strikes (ATM ±3 pre-filled)</p>
                    <div class="overflow-x-auto">
                        <div class="grid min-w-[1100px] grid-cols-10 gap-3">
                            @for($i = 0; $i < 10; $i++)
                                <input type="number" step="1" name="strikes[]"
                                    value="{{ $strikes[$i] ?? '' }}"
                                    placeholder="Strike {{ $i+1 }}"
                                    class="strike-field w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none transition focus:border-teal-400 focus:ring-2 focus:ring-teal-400/10">
                            @endfor
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 pt-1">
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-br from-teal-700 to-teal-500 px-4 py-2.5 text-sm font-semibold text-white shadow-md transition hover:-translate-y-px hover:shadow-lg active:translate-y-0">
                        Generate Charts
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Charts area ── --}}
    <div class="mx-auto max-w-[1800px] px-3 pb-3 pt-2">
        <div id="emptyState" class="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-teal-50 text-teal-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 14l3-3 3 2 5-6"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-slate-700">Initialising charts…</p>
        </div>

        {{-- Two charts per row on xl --}}
        <div id="chartsGrid" class="hidden grid-cols-1 gap-3 xl:grid-cols-2"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            'use strict';

            // ── Config injected by PHP ───────────────────────────────────────────────
            var CFG = {!! $jsConfig !!};

            // ── Constants ────────────────────────────────────────────────────────────
            var BUILDUP_COLORS = {
                'Short Build': '#dc2626',
                'Long Build': '#16a34a',
                'Long Unwind': '#eab308',
                'Short Cover': '#1e3a8a'
            };

            var lastFetchAt = null;
            var latestCandleAt = null;
            var pageUpdatedTimer = null;
            var pageUpdatedEl = document.getElementById('page-updated-time');

            var signalStore = {
                history: [],
                processedKeys: {},
                lastBySeries: {}
            };
            var signalDrawerOpen = false;
            var lastEvaluatedTradeDate = null;


            var SERIES_COLORS = {
                ce: {
                    line: '#2563eb',
                    up: 'rgba(37,99,235,0.75)',
                    down: 'rgba(37,99,235,0.75)',
                    border: 'rgba(37,99,235,0.95)',
                    wick: 'rgba(37,99,235,0.95)'
                },
                pe: {
                    line: '#7c3aed',
                    up: 'rgba(124,58,237,0.35)',
                    down: 'rgba(124,58,237,0.35)',
                    border: 'rgba(124,58,237,0.90)',
                    wick: 'rgba(124,58,237,0.90)'
                },
                midpoint: '#f97316'
            };

            // ── State ────────────────────────────────────────────────────────────────
            var autoRefreshTimer = null;
            var autoRefreshTimeout = null;
            var latestPayload = null;
            var chartRegistry = {};

            // ── DOM refs ─────────────────────────────────────────────────────────────
            var form = document.getElementById('chartFilterForm');
            var tradeDateInput = document.getElementById('trade_date');
            var midpointInput = document.getElementById('midpoint');
            var chartsGrid = document.getElementById('chartsGrid');
            var emptyState = document.getElementById('emptyState');
            var refreshNowBtn = document.getElementById('refreshNowBtn');
            var lastUpdatedText = document.getElementById('lastUpdatedText');
            var statusBadge = document.getElementById('statusBadge');
            var filterPanel = document.getElementById('filterPanel');
            var toggleFilterBtn = document.getElementById('toggleFilterBtn');
            var toggleLabel = document.getElementById('toggleFilterLabel');

            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            var csrfInput = document.querySelector('input[name="_token"]');
            var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') :
                csrfInput ? csrfInput.value : '';

            var signalPanel = document.getElementById('signalPanel');
            var signalPanelDrawer = document.getElementById('signalPanelDrawer');
            var signalPanelBackdrop = document.getElementById('signalPanelBackdrop');
            var signalPanelToggle = document.getElementById('signalPanelToggle');
            var signalPanelClose = document.getElementById('signalPanelClose');
            var signalHeadlineCall = document.getElementById('signalHeadlineCall');
            var signalHeadlineScore = document.getElementById('signalHeadlineScore');
            var signalHeadlineMeta = document.getElementById('signalHeadlineMeta');
            var signalHeadlineReasons = document.getElementById('signalHeadlineReasons');
            var signalStats = document.getElementById('signalStats');
            var signalHistory = document.getElementById('signalHistory');
            var signalHistoryCount = document.getElementById('signalHistoryCount');
            var signalToastHost = document.getElementById('signalToastHost');
            var clearSignalHistoryBtn = document.getElementById('clearSignalHistory');


            // ── Filter panel toggle (default: collapsed) ─────────────────────────────
            toggleFilterBtn.addEventListener('click', function () {
                var collapsed = filterPanel.classList.contains('collapsed');
                if (collapsed) {
                    filterPanel.style.maxHeight = filterPanel.scrollHeight + 'px';
                    filterPanel.classList.remove('collapsed');
                    toggleLabel.textContent = 'Hide Filters';
                } else {
                    filterPanel.style.maxHeight = '0';
                    filterPanel.classList.add('collapsed');
                    toggleLabel.textContent = 'Filters';
                }
            });

            // ── Form submit ──────────────────────────────────────────────────────────
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                loadCharts();
                startAutoRefresh();
            });

            refreshNowBtn.addEventListener('click', function () {
                loadCharts();
            });


            function signalTone(signal) {
                if (signal === 'Bullish Buy') return 'bullish';
                if (signal === 'Buy') return 'buy';
                if (signal === 'Sell') return 'sell';
                if (signal === 'Bearish Sell') return 'bearish';
                return 'ignore';
            }

            function signalSideTone(side) {
                if (side === 'CE') return 'ce';
                if (side === 'PE') return 'pe';
                return 'neutral';
            }

            function deriveOverallSignal(items, totalScore) {
                var ceScore = 0;
                var peScore = 0;

                items.forEach(function (item) {
                    if (item.side === 'CE') ceScore += Number(item.score || 0);
                    if (item.side === 'PE') peScore += Number(item.score || 0);
                });

                if (totalScore < 25) return 'Ignore';
                if (ceScore >= peScore + 15 && totalScore >= 45) return 'Bullish Buy';
                if (ceScore > peScore) return 'Buy';
                if (peScore >= ceScore + 15 && totalScore >= 45) return 'Bearish Sell';
                if (peScore > ceScore) return 'Sell';
                return 'Ignore';
            }

            function groupSignalsByTime(history) {
                var grouped = {};

                history.forEach(function (rawItem) {
                    var item = normalizeSignalEntry(rawItem);
                    var key = item.candleLabel || item.candleTime || '--';

                    if (!grouped[key]) {
                        grouped[key] = {
                            time: key,
                            displayTime: formatSignalTimeLabel(key),
                            items: [],
                            totalScore: 0,
                            maxScore: 0,
                            candleTime: Number(item.candleTime || 0),
                            topStrike: '--',
                            overallSignal: 'Ignore'
                        };
                    }

                    grouped[key].items.push(item);
                    grouped[key].totalScore += Number(item.score || 0);
                    grouped[key].maxScore = Math.max(grouped[key].maxScore, Number(item.score || 0));
                    grouped[key].candleTime = Math.max(grouped[key].candleTime, Number(item.candleTime || 0));
                });

                var result = Object.keys(grouped).map(function (key) {
                    var group = grouped[key];

                    group.items.sort(function (a, b) {
                        return Number(b.score || 0) - Number(a.score || 0);
                    });

                    group.totalScore = Number(group.totalScore || 0);
                    group.topStrike = group.items.length ? formatStrike(group.items[0].strike) : '--';
                    group.overallSignal = deriveOverallSignal(group.items, group.totalScore);

                    return group;
                });

                result.sort(function (a, b) {
                    return Number(b.candleTime || 0) - Number(a.candleTime || 0);
                });

                return result;
            }

            function formatSignalTimeLabel(label) {
                if (!label || label === '--') return '--';

                var parsed = new Date(String(label).replace(' ', 'T'));
                if (isNaN(parsed.getTime())) return label;

                return new Intl.DateTimeFormat('en-IN', {
                    timeZone: 'Asia/Kolkata',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                }).format(parsed);
            }

            function renderGroupedSignalTable(history) {
                var groups = groupSignalsByTime(history);

                if (!groups.length) {
                    signalHistory.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">No signal history yet.</div>';
                    return;
                }

                signalHistory.innerHTML = groups.map(function (group) {
                    var rowsHtml = group.items.map(function (item) {
                        var reasons = Array.isArray(item.reasons) ? item.reasons : [];
                        var formattedStrike = formatStrike(item.strike);
                        var bias = compactBiasLabel(item);

                        var detailHtml = reasons.map(function (reason) {
                            var label = compactReasonLabel(reason);
                            return label ? '<span class="signal-pill">' + escapeHtml(label) + '</span>' : '';
                        }).join('');

                        if (!detailHtml) {
                            detailHtml = '<span class="text-xs text-slate-400">No details</span>';
                        }

                        return '<tr>'
                            + '<td class="signal-strike-cell">'
                            + '<div class="signal-strike-main">' + escapeHtml(formattedStrike) + '</div>'
                            + '<div class="signal-strike-score">Score ' + escapeHtml(String(item.score || 0)) + '</div>'
                            + '</td>'
                            + '<td class="signal-bias-cell">'
                            + '<span class="signal-bias signal-bias--' + signalBiasTone(bias) + '">' + escapeHtml(bias) + '</span>'
                            + '</td>'
                            + '<td><div class="signal-detail-list">' + detailHtml + '</div></td>'
                            + '</tr>';
                    }).join('');

                    var strikeCount = (group.items || []).length;
                    var metaText = 'Score ' + String(group.totalScore || 0)
                        + ' · ' + String(strikeCount) + ' strike' + (strikeCount === 1 ? '' : 's')
                        + ' · Top ' + String(group.topStrike || '--');

                    return '<div class="signal-time-block">'
                        + '<div class="signal-time-head">'
                        + '<div>'
                        + '<div class="signal-time-label">' + escapeHtml(group.displayTime || group.time || '--') + '</div>'
                        + '<div class="signal-time-meta">' + escapeHtml(metaText) + '</div>'
                        + '</div>'
                        + '<span class="signal-overall signal-overall--' + signalTone(group.overallSignal) + '">' + escapeHtml(group.overallSignal || 'Ignore') + '</span>'
                        + '</div>'
                        + '<div class="mt-3 overflow-x-auto">'
                        + '<table class="signal-table">'
                        + '<thead>'
                        + '<tr>'
                        + '<th>Strike</th>'
                        + '<th>Bias</th>'
                        + '<th>Details</th>'
                        + '</tr>'
                        + '</thead>'
                        + '<tbody>' + rowsHtml + '</tbody>'
                        + '</table>'
                        + '</div>'
                        + '</div>';
                }).join('');
            }

            function signalBiasTone(bias) {
                if (bias === 'BULL') return 'bull';
                if (bias === 'BEAR') return 'bear';
                if (bias === 'CE') return 'ce';
                if (bias === 'PE') return 'pe';
                return 'neutral';
            }

            function compactReasonLabel(reason) {
                if (!reason) return '';

                var text = String(reason).trim();

                text = text.replace(/CE highest OI candle/i, 'CE OI #1');
                text = text.replace(/PE highest OI candle/i, 'PE OI #1');
                text = text.replace(/CE highest volume candle/i, 'CE VOL #1');
                text = text.replace(/PE highest volume candle/i, 'PE VOL #1');

                text = text.replace(/CE 2nd highest OI candle/i, 'CE OI #2');
                text = text.replace(/PE 2nd highest OI candle/i, 'PE OI #2');
                text = text.replace(/CE 3rd highest OI candle/i, 'CE OI #3');
                text = text.replace(/PE 3rd highest OI candle/i, 'PE OI #3');
                text = text.replace(/CE 4th highest OI candle/i, 'CE OI #4');
                text = text.replace(/PE 4th highest OI candle/i, 'PE OI #4');
                text = text.replace(/CE 5th highest OI candle/i, 'CE OI #5');
                text = text.replace(/PE 5th highest OI candle/i, 'PE OI #5');

                text = text.replace(/CE 2nd highest volume candle/i, 'CE VOL #2');
                text = text.replace(/PE 2nd highest volume candle/i, 'PE VOL #2');
                text = text.replace(/CE 3rd highest volume candle/i, 'CE VOL #3');
                text = text.replace(/PE 3rd highest volume candle/i, 'PE VOL #3');
                text = text.replace(/CE 4th highest volume candle/i, 'CE VOL #4');
                text = text.replace(/PE 4th highest volume candle/i, 'PE VOL #4');
                text = text.replace(/CE 5th highest volume candle/i, 'CE VOL #5');
                text = text.replace(/PE 5th highest volume candle/i, 'PE VOL #5');

                text = text.replace(/midpoint/i, 'MID');
                text = text.replace(/first 5 min high/i, '5M H');
                text = text.replace(/first 5 min low/i, '5M L');
                text = text.replace(/long buildup/i, 'LB');
                text = text.replace(/short buildup/i, 'SB');
                text = text.replace(/short covering/i, 'SC');
                text = text.replace(/long unwinding/i, 'LU');

                return text;
            }

            function compactBiasLabel(item) {
                var side = String(item.side || '').toUpperCase();
                var call = String(item.call || '').toUpperCase();

                if (call.indexOf('BULLISH') !== -1 || call.indexOf('BUY') !== -1) return 'BULL';
                if (call.indexOf('BEARISH') !== -1 || call.indexOf('SELL') !== -1) return 'BEAR';
                if (side === 'CE') return 'CE';
                if (side === 'PE') return 'PE';
                return 'N';
            }

            function formatStrike(strike) {
                var num = Number(strike);
                if (Number.isFinite(num)) {
                    return String(Math.round(num));
                }
                return String(strike || '--').replace(/\.00$/, '');
            }




            // ── Build payload from form ───────────────────────────────────────────────
            function buildPayload () {
                var strikes = Array.from(document.querySelectorAll('.strike-field'))
                    .map(function (el) { return el.value.trim(); })
                    .filter(Boolean);

                return {
                    underlying_symbol: CFG.symbol,
                    expiry_date: document.getElementById('expiry_date').value,
                    trade_date: tradeDateInput.value,
                    midpoint: midpointInput.value || null,
                    strikes: strikes
                };
            }

            function clearAutoRefresh () {
                if (autoRefreshTimer) clearInterval(autoRefreshTimer);
                if (autoRefreshTimeout) clearTimeout(autoRefreshTimeout);
                autoRefreshTimer = null;
                autoRefreshTimeout = null;
            }
            function getLatestCandleTimestamp(result) {
                var latest = null;
                Object.keys(result.data || {}).forEach(function (strike) {
                    ['CE', 'PE'].forEach(function (side) {
                        var candles = (result.data[strike] && result.data[strike][side]) ? result.data[strike][side] : [];
                        if (candles.length) {
                            var ts = candles[candles.length - 1].time;
                            if (!latest || ts > latest) latest = ts;
                        }
                    });
                });
                return latest;
            }

            function formatIstDateTime(date) {
                if (!date) return '--';
                return new Intl.DateTimeFormat('en-IN', {
                    timeZone: 'Asia/Kolkata',
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                }).format(date);
            }

            function formatIstTimeFromUnix(ts) {
                if (!ts) return '--';
                return new Intl.DateTimeFormat('en-IN', {
                    timeZone: 'Asia/Kolkata',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                }).format(new Date(ts * 1000));
            }

            function getNextFetchTime() {
                var now = new Date();
                var next = new Date(now);

                next.setSeconds(9, 0);

                var minute = now.getMinutes();
                var nextMinute = Math.ceil((minute + (now.getSeconds() >= 9 ? 0.0001 : 0)) / 5) * 5;

                if (nextMinute >= 60) {
                    next.setHours(now.getHours() + 1);
                    next.setMinutes(0);
                } else {
                    next.setMinutes(nextMinute);
                }

                next.setSeconds(9, 0);

                if (next <= now) {
                    next = new Date(next.getTime() + 5 * 60 * 1000);
                }

                return next;
            }


            function formatCountdown(ms) {
                var total = Math.max(0, Math.floor(ms / 1000));
                var min = Math.floor(total / 60);
                var sec = total % 60;
                return String(min).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
            }

            function updatePageUpdatedTime() {
                if (!pageUpdatedEl) return;

                var now = Date.now();
                var nextFetch = getNextFetchTime();
                var countdown = formatCountdown(nextFetch.getTime() - Date.now());
                var fetchedText = lastFetchAt ? formatIstDateTime(lastFetchAt) : '--';
                var candleText = latestCandleAt ? formatIstTimeFromUnix(latestCandleAt) : '--';

                pageUpdatedEl.innerHTML =
                    '<div class="text-right">' +
                    '<div><span class="font-semibold">Updated:</span> ' + fetchedText + '</div>' +
                    '<div><span class="font-semibold">Candle:</span> ' + candleText + '</div>' +
                    '<div><span class="font-semibold text-emerald-600">Next:</span> ' + countdown + '</div>' +
                    '</div>';
            }

            function startPageUpdatedClock() {
                if (pageUpdatedTimer) {
                    clearInterval(pageUpdatedTimer);
                }

                updatePageUpdatedTime();

                pageUpdatedTimer = setInterval(function () {
                    updatePageUpdatedTime();
                }, 1000);
            }


            function msUntilNextFiveMinuteMarkAt09 () {
                var now = new Date();
                var next = new Date(now);

                next.setSeconds(9, 0);

                var minute = now.getMinutes();
                var nextMinute = Math.ceil(( minute + ( now.getSeconds() >= 9 ? 0.0001 : 0 ) ) / 5) * 5;

                if (nextMinute >= 60) {
                    next.setHours(now.getHours() + 1);
                    next.setMinutes(0);
                } else {
                    next.setMinutes(nextMinute);
                }

                next.setSeconds(9, 0);

                if (next <= now) {
                    next = new Date(next.getTime() + 5 * 60 * 1000);
                }

                return next.getTime() - now.getTime();
            }

            // ── Fetch candle data ─────────────────────────────────────────────────────
            function loadCharts () {
                var payload = buildPayload();

                if ( ! payload.expiry_date || ! payload.trade_date || payload.strikes.length === 0) {
                    statusBadge.textContent = 'Missing required fields';
                    return;
                }

                latestPayload = payload;
                statusBadge.textContent = 'Loading…';

                fetch(CFG.chartDataUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(payload)
                })
                    .then(function (res) {
                        if ( ! res.ok) {
                            return res.text().then(function (t) { throw new Error(res.status + ': ' + t); });
                        }
                        return res.json();
                    })
                    .then(function (result) {
                        if ( ! result.success) { throw new Error('Server returned success:false'); }
                        renderCharts(result);

                        lastFetchAt = new Date();
                        latestCandleAt = getLatestCandleTimestamp(result);
                        updatePageUpdatedTime();

                        evaluateDaySignals(result);
                        lastUpdatedText.textContent = new Date().toLocaleTimeString('en-IN');
                        statusBadge.textContent = 'Live';


                    })
                    .catch(function (err) {
                        statusBadge.textContent = 'Error – see console';
                        console.error('[Chart] fetch error:', err);
                    });
            }

            function startAutoRefresh () {
                clearAutoRefresh();

                autoRefreshTimeout = setTimeout(function () {
                    if (latestPayload) loadCharts();

                    autoRefreshTimer = setInterval(function () {
                        if (latestPayload) loadCharts();
                    }, 5 * 60 * 1000);
                }, msUntilNextFiveMinuteMarkAt09());
            }

            // ── Render charts ─────────────────────────────────────────────────────────
            function renderCharts (result) {
                var strikes = Object.keys(result.data || {});
                var existing = Object.keys(chartRegistry);

                if (strikes.length === 0) {
                    destroyAllCharts();
                    chartsGrid.innerHTML = '';
                    emptyState.classList.remove('hidden');
                    chartsGrid.classList.remove('grid');
                    chartsGrid.classList.add('hidden');
                    emptyState.querySelector('p').textContent = 'No CE/PE data for selected date & strikes.';
                    return;
                }

                emptyState.classList.add('hidden');
                chartsGrid.classList.remove('hidden');
                chartsGrid.classList.add('grid');

                existing.forEach(function (strike) {
                    if ( ! strikes.includes(strike)) {
                        destroyChart(strike);
                    }
                });

                strikes.forEach(function (strike, index) {
                    if ( ! chartRegistry[ strike ]) {
                        mountStrikeChart(
                            strike,
                            index,
                            result.data[ strike ],
                            result.firstCandle[ strike ] || {},
                            result.topMarkers[ strike ] || {},
                            result.midpoint
                        );
                    } else {
                        updateStrikeChart(
                            strike,
                            result.data[ strike ],
                            result.firstCandle[ strike ] || {},
                            result.topMarkers[ strike ] || {},
                            result.midpoint
                        );
                    }
                });
            }

            function mountStrikeChart (strike, index, strikeData, firstCandle, topMarkers, midpoint) {
                var card = buildChartCard(strike, index);
                chartsGrid.appendChild(card);

                createStrikeChart(strike, strikeData, firstCandle, topMarkers, midpoint);

                var entry = chartRegistry[ strike ];
                if (entry) {
                    entry.card = card;
                    entry.summaryEl = document.getElementById('summary-' + sanitizeStrike(strike));
                    entry.priceLines = {
                        ceHigh: null,
                        ceLow: null,
                        peHigh: null,
                        peLow: null,
                        midpoint: null
                    };
                    entry.userInteracted = false;

                    bindChartInteraction(entry);
                    updatePriceLines(entry, firstCandle, midpoint);
                    renderTopSummary(strike, strikeData, topMarkers);
                }
            }

            function bindChartInteraction (entry) {
                var markInteracted = function () {
                    entry.userInteracted = true;
                };

                entry.container.addEventListener('wheel', markInteracted, { passive: true });
                entry.container.addEventListener('mousedown', markInteracted);
                entry.container.addEventListener('touchstart', markInteracted, { passive: true });
            }

            function updatePriceLines (entry, firstCandle, midpoint) {
                if ( ! entry.priceLines) {
                    entry.priceLines = { ceHigh: null, ceLow: null, peHigh: null, peLow: null, midpoint: null };
                }

                if (entry.priceLines.ceHigh) entry.ceSeries.removePriceLine(entry.priceLines.ceHigh);
                if (entry.priceLines.ceLow) entry.ceSeries.removePriceLine(entry.priceLines.ceLow);
                if (entry.priceLines.peHigh) entry.peSeries.removePriceLine(entry.priceLines.peHigh);
                if (entry.priceLines.peLow) entry.peSeries.removePriceLine(entry.priceLines.peLow);
                if (entry.priceLines.midpoint) entry.ceSeries.removePriceLine(entry.priceLines.midpoint);

                entry.priceLines.ceHigh = null;
                entry.priceLines.ceLow = null;
                entry.priceLines.peHigh = null;
                entry.priceLines.peLow = null;
                entry.priceLines.midpoint = null;

                if (firstCandle.CE) {
                    entry.priceLines.ceHigh = addPriceLine(entry.ceSeries, firstCandle.CE.high, SERIES_COLORS.ce.line, 'CE H', 2, LightweightCharts.LineStyle.Dashed);
                    entry.priceLines.ceLow = addPriceLine(entry.ceSeries, firstCandle.CE.low, SERIES_COLORS.ce.line, 'CE L', 1, LightweightCharts.LineStyle.Dashed);
                }

                if (firstCandle.PE) {
                    entry.priceLines.peHigh = addPriceLine(entry.peSeries, firstCandle.PE.high, SERIES_COLORS.pe.line, 'PE H', 2, LightweightCharts.LineStyle.Dashed);
                    entry.priceLines.peLow = addPriceLine(entry.peSeries, firstCandle.PE.low, SERIES_COLORS.pe.line, 'PE L', 1, LightweightCharts.LineStyle.Dashed);
                }

                if (midpoint !== null && midpoint !== undefined && midpoint !== '') {
                    entry.priceLines.midpoint = addPriceLine(entry.ceSeries, midpoint, SERIES_COLORS.midpoint, 'Mid', 3, LightweightCharts.LineStyle.Solid);
                }
            }

            function updateStrikeChart (strike, strikeData, firstCandle, topMarkers, midpoint) {
                var entry = chartRegistry[ strike ];
                if ( ! entry) return;

                entry.ceSeries.setData(( strikeData.CE || [] ).map(candleToSeries));
                entry.peSeries.setData(( strikeData.PE || [] ).map(candleToSeries));

                entry.ceSeries.setMarkers(buildMarkers(strikeData.CE || [], topMarkers.CE || { oi: [], volume: [] }, 'CE'));
                entry.peSeries.setMarkers(buildMarkers(strikeData.PE || [], topMarkers.PE || { oi: [], volume: [] }, 'PE'));

                updatePriceLines(entry, firstCandle, midpoint);
                renderTopSummary(strike, strikeData, topMarkers);

                if ( ! entry.userInteracted) {
                    entry.chart.timeScale().fitContent();
                }
            }

            function destroyChart (strike) {
                var entry = chartRegistry[ strike ];
                if ( ! entry) return;

                window.removeEventListener('resize', entry.resizeHandler);

                if (entry.card && entry.card.parentNode) {
                    entry.card.parentNode.removeChild(entry.card);
                }

                entry.chart.remove();
                delete chartRegistry[ strike ];
            }

            // ── Build chart card ──────────────────────────────────────────────────────
            function buildChartCard (strike, index) {
                var wrapper = document.createElement('article');
                wrapper.className = 'overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm';

                wrapper.innerHTML =
                    '<div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/60 px-4 py-2">' +
                    '<div class="flex items-center gap-2">' +
                    '<span class="tabular-nums text-sm font-bold text-slate-900">Strike ' + strike + '</span>' +
                    '<span class="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-medium text-slate-500">Panel ' + ( index + 1 ) + '</span>' +
                    '</div>' +
                    '<div class="flex items-center gap-2">' +
                    '<span class="inline-flex items-center gap-1 text-[11px] text-slate-400">' +
                    '<span class="h-1.5 w-1.5 rounded-full" style="background:' + SERIES_COLORS.ce.line + '"></span>CE right axis' +
                    '</span>' +
                    '<span class="inline-flex items-center gap-1 text-[11px] text-slate-400">' +
                    '<span class="h-1.5 w-1.5 rounded-full" style="background:' + SERIES_COLORS.pe.line + '"></span>PE left axis' +
                    '</span>' +
                    '<button type="button" class="fullscreen-btn rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50" data-strike="' + strike + '">&#x26F6; Full</button>' +
                    '</div>' +
                    '</div>' +
                    '<div class="px-2 py-2">' +
                    '<div id="chart-' + sanitizeStrike(strike) + '" class="chart-box rounded-xl"></div>' +
                    '<div class="mt-1 flex flex-wrap gap-3 px-1 text-[10px] text-slate-400">' +
                    '<span>OI1/OI2/OI3 = top OI candles</span>' +
                    '<span>V1/V2/V3 = top volume candles</span>' +
                    '<span style="color:' + SERIES_COLORS.midpoint + '">&#8212; MidPoint</span>' +
                    '<span style="color:' + SERIES_COLORS.ce.line + '">&#8212; CE 1st candle</span>' +
                    '<span style="color:' + SERIES_COLORS.pe.line + '">&#8212; PE 1st candle</span>' +
                    '</div>' +
                    '<div class="chart-summary" id="summary-' + sanitizeStrike(strike) + '"></div>' +
                    '</div>';

                wrapper.querySelector('.fullscreen-btn').addEventListener('click', function () {
                    toggleFullscreen(wrapper, strike);
                });

                return wrapper;
            }

            function formatIstTime(time) {
                if (time === null || time === undefined) return '';

                var ts = typeof time === 'number'
                    ? time
                    : (typeof time === 'object' && time.timestamp ? time.timestamp : null);

                if (!ts) return '';

                return new Intl.DateTimeFormat('en-IN', {
                    timeZone: 'Asia/Kolkata',
                    day: '2-digit',
                    month: 'short',
                    year: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                }).format(new Date(ts * 1000));
            }

            // ── Create LightweightChart ───────────────────────────────────────────────
            function createStrikeChart (strike, strikeData, firstCandle, topMarkers, midpoint) {
                var containerId = 'chart-' + sanitizeStrike(strike);
                var container = document.getElementById(containerId);
                if ( ! container) return;

                var chart = LightweightCharts.createChart(container, {
                    layout: {
                        background: { color: '#ffffff' },
                        textColor: '#334155',
                        fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif'
                    },
                    grid: {
                        vertLines: { color: '#ffffff' },
                        horzLines: { color: '#ffffff' }
                    },
                    width: container.clientWidth,
                    height: container.clientHeight,
                    crosshair: {
                        mode: LightweightCharts.CrosshairMode.Normal
                    },
                    rightPriceScale: {
                        borderColor: '#e2e8f0',
                        scaleMargins: {
                            top: 0.08,
                            bottom: 0.08
                        },
                        autoScale: true
                    },
                    leftPriceScale: {
                        visible: false
                    },
                    localization: {
                        timeFormatter: formatIstTime,
                    },
                    timeScale: {
                        borderColor: '#e2e8f0',
                        timeVisible: true,
                        secondsVisible: false,
                        rightOffset: 20,
                        barSpacing: 20,
                        minBarSpacing: 6,
                        tickMarkFormatter: function (time) {
                            var d = new Date(time * 1000);
                            var h = d.getHours().toString().padStart(2, '0');
                            var m = d.getMinutes().toString().padStart(2, '0');
                            return h + ':' + m;
                        }
                    }
                });

                // Both CE and PE use 'right' scale → single axis, candles overlay directly
                var ceSeries = chart.addCandlestickSeries({
                    title: 'CE',
                    priceScaleId: 'right',
                    upColor: SERIES_COLORS.ce.up,
                    downColor: SERIES_COLORS.ce.down,
                    borderColor: SERIES_COLORS.ce.border,
                    wickColor: SERIES_COLORS.ce.wick,
                    borderVisible: true,
                    wickVisible: true,
                    priceLineVisible: false
                });

                var peSeries = chart.addCandlestickSeries({
                    title: 'PE',
                    priceScaleId: 'right',
                    upColor: SERIES_COLORS.pe.up,
                    downColor: SERIES_COLORS.pe.down,
                    borderColor: SERIES_COLORS.pe.border,
                    wickColor: SERIES_COLORS.pe.wick,
                    borderVisible: true,
                    wickVisible: true,
                    priceLineVisible: false
                });

                ceSeries.setData(( strikeData.CE || [] ).map(candleToSeries));
                peSeries.setData(( strikeData.PE || [] ).map(candleToSeries));

                renderTopSummary(strike, strikeData, topMarkers);

                var tooltip = document.createElement('div');
                tooltip.className = 'chart-tooltip';
                container.style.position = 'relative';
                container.appendChild(tooltip);

                var ceMap = {};
                ( strikeData.CE || [] ).forEach(function (c) { ceMap[ c.time ] = candleToSeries(c); });

                var peMap = {};
                ( strikeData.PE || [] ).forEach(function (c) { peMap[ c.time ] = candleToSeries(c); });

                chart.subscribeCrosshairMove(function (param) {
                    if ( ! param || ! param.point || param.point.x < 0 || param.point.y < 0 || ! param.time) {
                        tooltip.style.display = 'none';
                        return;
                    }

                    var ceBar = ceMap[ param.time ] || null;
                    var peBar = peMap[ param.time ] || null;

                    if ( ! ceBar && ! peBar) {
                        tooltip.style.display = 'none';
                        return;
                    }

                    function blockHtml (label, bar, tone) {
                        if ( ! bar) return '';
                        return `
            <div style="margin-bottom:8px;">
                <div style="font-weight:700; color:${ tone }; margin-bottom:4px;">${ label }</div>
                <div class="chart-tooltip-grid">
                    <div class="chart-tooltip-label">Time</div><div class="chart-tooltip-value">${ bar._rawTime }</div>
                    <div class="chart-tooltip-label">O</div><div class="chart-tooltip-value">${ bar.open }</div>
                    <div class="chart-tooltip-label">H</div><div class="chart-tooltip-value">${ bar.high }</div>
                    <div class="chart-tooltip-label">L</div><div class="chart-tooltip-value">${ bar.low }</div>
                    <div class="chart-tooltip-label">C</div><div class="chart-tooltip-value">${ bar.close }</div>
                    <div class="chart-tooltip-label">OI</div><div class="chart-tooltip-value">${ bar._oi.toLocaleString('en-IN') }</div>
                    <div class="chart-tooltip-label">Vol</div><div class="chart-tooltip-value">${ bar._volume.toLocaleString('en-IN') }</div>
                    <div class="chart-tooltip-label">dOI</div><div class="chart-tooltip-value">${ bar._diffOi.toLocaleString('en-IN') }</div>
                    <div class="chart-tooltip-label">dVol</div><div class="chart-tooltip-value">${ bar._diffVol.toLocaleString('en-IN') }</div>
                    <div class="chart-tooltip-label">Build</div><div class="chart-tooltip-value">${ bar._buildUp }</div>
                </div>
            </div>
        `;
                    }

                    tooltip.innerHTML =
                        blockHtml('CE', ceBar, SERIES_COLORS.ce.line) +
                        blockHtml('PE', peBar, SERIES_COLORS.pe.line);

                    tooltip.style.display = 'block';

                    var left = param.point.x + 14;
                    var top = param.point.y + 14;

                    if (left + 240 > container.clientWidth) left = param.point.x - 250;
                    if (top + tooltip.offsetHeight > container.clientHeight) top = param.point.y - tooltip.offsetHeight - 14;

                    tooltip.style.left = left + 'px';
                    tooltip.style.top = top + 'px';
                });

                // Top OI / Volume markers
                ceSeries.setMarkers(buildMarkers(strikeData.CE || [], topMarkers.CE || {}, 'CE'));
                peSeries.setMarkers(buildMarkers(strikeData.PE || [], topMarkers.PE || {}, 'PE'));

                chart.timeScale().fitContent();

                var allTimes = []
                    .concat(( strikeData.CE || [] ).map(function (c) { return c.time; }))
                    .concat(( strikeData.PE || [] ).map(function (c) { return c.time; }))
                    .sort();

                //chart.timeScale().fitContent();

                var resizeHandler = function () {
                    if (container) {
                        chart.applyOptions({ width: container.clientWidth, height: container.clientHeight });
                    }
                };
                window.addEventListener('resize', resizeHandler);

                chartRegistry[ strike ] = {
                    chart: chart,
                    ceSeries: ceSeries,
                    peSeries: peSeries,
                    resizeHandler: resizeHandler,
                    container: container,
                    userInteracted: false,
                    priceLines: {
                        ceHigh: null,
                        ceLow: null,
                        peHigh: null,
                        peLow: null,
                        midpoint: null
                    }
                };
            }

            // ── Helpers ───────────────────────────────────────────────────────────────
            function candleToSeries (candle) {
                var color = BUILDUP_COLORS[ candle.build_up ] || '#94a3b8';
                return {
                    time: candle.time,
                    open: candle.open,
                    high: candle.high,
                    low: candle.low,
                    close: candle.close,
                    color: color,
                    borderColor: color,
                    wickColor: color,
                    _rawTime: candle.x || candle.time,
                    _oi: Number(candle.oi || 0),
                    _volume: Number(candle.volume || 0),
                    _diffOi: Number(candle.diff_oi || 0),
                    _diffVol: Number(candle.diff_vol || 0),
                    _buildUp: candle.build_up || 'Neutral'
                };
            }

            function addPriceLine (series, price, color, title, lineWidth, lineStyle) {
                return series.createPriceLine({
                    price: Number(price),
                    color: color,
                    lineWidth: lineWidth || 2,
                    lineStyle: lineStyle !== undefined ? lineStyle : LightweightCharts.LineStyle.Solid,
                    axisLabelVisible: true,
                    title: title
                });
            }

            function buildMarkers (candles, meta, label) {
                var oiTimes = meta.oi || [];
                var volTimes = meta.volume || [];
                var oiRank = {};
                var volRank = {};

                oiTimes.forEach(function (t, i) { oiRank[ t ] = i + 1; });
                volTimes.forEach(function (t, i) { volRank[ t ] = i + 1; });

                var markers = [];
                candles.forEach(function (c) {
                    if (oiRank[ c.time ]) {
                        markers.push({
                            time: c.time,
                            position: 'aboveBar',
                            color: label === 'CE' ? '#0f766e' : '#7c3aed',
                            shape: 'circle',
                            text: String(oiRank[ c.time ])
                        });
                    }
                    if (volRank[ c.time ]) {
                        markers.push({
                            time: c.time,
                            position: 'belowBar',
                            color: label === 'CE' ? '#2563eb' : '#9a3412',
                            shape: 'circle',
                            text: String(volRank[ c.time ])
                        });
                    }
                });

                return markers;
            }

            function renderTopSummary (strike, strikeData, topMarkers) {
                var el = document.getElementById('summary-' + sanitizeStrike(strike));
                if ( ! el) return;

                function asMap (arr) {
                    var map = {};
                    arr.forEach(function (c) { map[ c.time ] = c; });
                    return map;
                }

                var ceMap = asMap(strikeData.CE || []);
                var peMap = asMap(strikeData.PE || []);

                function chips (times, map, cls, prefix, field) {
                    return ( times || [] ).slice(0, 3).map(function (t, i) {
                        var row = map[ t ];
                        var stamp = row && row.x ? row.x.split(' ')[ 1 ] : t;
                        var value = row ? Number(row[ field ] || 0).toLocaleString('en-IN') : '-';
                        return '<span class="chart-chip ' + cls + '">' + prefix + ( i + 1 ) + ' ' + stamp + ' · ' + value + '</span>';
                    }).join('');
                }
            }

            function destroyAllCharts () {
                Object.keys(chartRegistry).forEach(function (strike) {
                    destroyChart(strike);
                });
                chartRegistry = {};
            }

            function sanitizeStrike (s) { return String(s).replace(/\./g, '-'); }

            // FIX: Full-screen uses inset:0 and flex layout so chart fills 100% viewport
            function toggleFullscreen (card, strike) {
                var wasFS = card.classList.contains('full-screen-chart');

                card.classList.toggle('full-screen-chart');
                document.body.classList.toggle('overflow-hidden');
                document.documentElement.classList.toggle('overflow-hidden');

                if ( ! wasFS) {
                    card.style.width = '100vw';
                    card.style.height = '100vh';
                } else {
                    card.style.width = '';
                    card.style.height = '';
                }

                var btn = card.querySelector('.fullscreen-btn');
                btn.textContent = wasFS ? '⛶ Full' : '✕ Exit';

                var entry = chartRegistry[ strike ];
                if (entry) {
                    setTimeout(function () {
                        var rect = entry.container.getBoundingClientRect();
                        entry.chart.resize(rect.width, rect.height);
                        if ( ! entry.userInteracted) {
                            entry.chart.timeScale().fitContent();
                        }
                    }, 180);
                }
            }

            // ── Auto-load on page ready ───────────────────────────────────────────────
            // Trigger form submission automatically once DOM is ready
            setTimeout(function () {
                form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
                startAutoRefresh();
            }, 100);


            function openSignalDrawer() {
                signalDrawerOpen = true;
                signalPanel.classList.remove('pointer-events-none');
                signalPanelBackdrop.classList.remove('hidden');
                requestAnimationFrame(function () {
                    signalPanelDrawer.classList.remove('translate-x-full');
                });
            }

            function closeSignalDrawer() {
                signalDrawerOpen = false;
                signalPanelDrawer.classList.add('translate-x-full');
                signalPanelBackdrop.classList.add('hidden');
                setTimeout(function () {
                    if (!signalDrawerOpen) {
                        signalPanel.classList.add('pointer-events-none');
                    }
                }, 300);
            }

            signalPanelToggle && signalPanelToggle.addEventListener('click', function () {
                signalDrawerOpen ? closeSignalDrawer() : openSignalDrawer();
            });
            signalPanelClose && signalPanelClose.addEventListener('click', closeSignalDrawer);
            signalPanelBackdrop && signalPanelBackdrop.addEventListener('click', closeSignalDrawer);


            function signalStoreKey() {
                var expiryEl = document.getElementById('expirydate');
                var tradeDateEl = document.getElementById('tradedate');

                var expiryValue = expiryEl ? expiryEl.value : (CFG.expiry || '');
                var tradeDateValue = tradeDateEl ? tradeDateEl.value : (CFG.tradeDate || '');

                return ['trading-signals', CFG.symbol || '', expiryValue, tradeDateValue].join('|');
            }

            function loadSignalStore() {
                var key = signalStoreKey();
                lastEvaluatedTradeDate = getActiveTradeDate();

                try {
                    var raw = window.localStorage ? localStorage.getItem(key) : null;
                    signalStore = raw ? JSON.parse(raw) : { history: [], processedKeys: {}, lastBySeries: {} };
                } catch (e) {
                    signalStore = { history: [], processedKeys: {}, lastBySeries: {} };
                }

                if (!Array.isArray(signalStore.history)) signalStore.history = [];
                if (!signalStore.processedKeys || typeof signalStore.processedKeys !== 'object') signalStore.processedKeys = {};
                if (!signalStore.lastBySeries || typeof signalStore.lastBySeries !== 'object') signalStore.lastBySeries = {};

                signalStore.history = signalStore.history.map(normalizeSignalEntry);

                saveSignalStore();
                renderSignalPanel();
            }



            function saveSignalStore() {
                try {
                    if (window.localStorage) {
                        localStorage.setItem(signalStoreKey(), JSON.stringify(signalStore));
                    }
                } catch (e) {}
            }

            function getActiveExpiry() {
                var el = document.getElementById('expirydate');
                return el ? el.value : (CFG.expiry || '');
            }

            function getActiveTradeDate() {
                return tradeDateInput ? tradeDateInput.value : (CFG.tradeDate || '');
            }

            function ensureSignalStoreDate() {
                var activeTradeDate = tradeDateInput ? tradeDateInput.value : (CFG.tradeDate || '');
                if (lastEvaluatedTradeDate !== activeTradeDate) {
                    loadSignalStore();
                }
            }

            clearSignalHistoryBtn && clearSignalHistoryBtn.addEventListener('click', function () {
                signalStore = { history: [], processedKeys: {}, lastBySeries: {} };
                saveSignalStore();
                renderSignalPanel();
            });

            function toNum(v) { return Number(v || 0); }
            function abs(v) { return Math.abs(toNum(v)); }
            function samePrice(a, b, tolerance) { return Math.abs(toNum(a) - toNum(b)) <= (tolerance || 0.15); }

            function nearLevel(price, level, tolerancePct, minAbs) {
                price = toNum(price);
                level = toNum(level);
                if (!level) return false;
                var tol = Math.max(Math.abs(level) * (tolerancePct || 0.003), minAbs || 2);
                return Math.abs(price - level) <= tol;
            }

            function latestCandle(candles) {
                return candles && candles.length ? candles[candles.length - 1] : null;
            }

            function previousCandle(candles) {
                return candles && candles.length > 1 ? candles[candles.length - 2] : null;
            }

            function getNeighbourStrikes(strike, allStrikes) {
                var sorted = allStrikes.map(Number).sort(function (a, b) { return a - b; });
                var idx = sorted.indexOf(Number(strike));
                return {
                    prev: idx > 0 ? String(sorted[idx - 1]) : null,
                    next: idx >= 0 && idx < sorted.length - 1 ? String(sorted[idx + 1]) : null
                };
            }

            function countByBuildUp(grouped, time, side, buildUp) {
                return Object.keys(grouped).reduce(function (acc, strike) {
                    var arr = grouped[strike] && grouped[strike][side] ? grouped[strike][side] : [];
                    var row = arr.find(function (c) { return c.time === time; });
                    return acc + (row && row.buildup === buildUp ? 1 : 0);
                }, 0);
            }

            function evaluateMidpointScenario(ctx) {
                var score = 0, reasons = [];
                var ce = ctx.ce, pe = ctx.pe;
                var ceNearMid = ce && nearLevel(ce.close, ctx.midpoint, 0.0025, 3);
                var peNearMid = pe && nearLevel(pe.close, ctx.midpoint, 0.0025, 3);

                if (ceNearMid && ce.buildup === 'Long Build') {
                    score += 30;
                    reasons.push('CE Long Build near midpoint');
                }
                if (peNearMid && pe.buildup === 'Long Build') {
                    score += 30;
                    reasons.push('PE Long Build near midpoint');
                }
                if (ceNearMid && pe && pe.buildup === 'Short Build') {
                    score += 12;
                    reasons.push('Opposite PE Short Build confirmation');
                }
                if (peNearMid && ce && ce.buildup === 'Short Build') {
                    score += 12;
                    reasons.push('Opposite CE Short Build confirmation');
                }
                return { name: 'midpoint', score: score, reasons: reasons };
            }

            function evaluateFirstFiveScenario(ctx) {
                var score = 0, reasons = [];
                if (ctx.firstCandle && ctx.firstCandle.CE && ctx.ce) {
                    if (nearLevel(ctx.ce.low, ctx.firstCandle.CE.low, 0.002, 2) && ctx.ce.buildup === 'Long Build') {
                        score += 24;
                        reasons.push('CE Long Build at first 5-min low');
                    }
                    if (nearLevel(ctx.ce.high, ctx.firstCandle.CE.high, 0.002, 2) && ctx.ce.buildup === 'Short Build') {
                        score += 18;
                        reasons.push('CE Short Build at first 5-min high');
                    }
                }
                if (ctx.firstCandle && ctx.firstCandle.PE && ctx.pe) {
                    if (nearLevel(ctx.pe.low, ctx.firstCandle.PE.low, 0.002, 2) && ctx.pe.buildup === 'Long Build') {
                        score += 24;
                        reasons.push('PE Long Build at first 5-min low');
                    }
                    if (nearLevel(ctx.pe.high, ctx.firstCandle.PE.high, 0.002, 2) && ctx.pe.buildup === 'Short Build') {
                        score += 18;
                        reasons.push('PE Short Build at first 5-min high');
                    }
                }
                return { name: 'firstFive', score: score, reasons: reasons };
            }

            function evaluateOiVolumeScenario(ctx) {
                var score = 0, reasons = [];
                var topOiCE = (ctx.topMarkers.CE && ctx.topMarkers.CE.oi || []).includes(ctx.time);
                var topVolCE = (ctx.topMarkers.CE && ctx.topMarkers.CE.volume || []).includes(ctx.time);
                var topOiPE = (ctx.topMarkers.PE && ctx.topMarkers.PE.oi || []).includes(ctx.time);
                var topVolPE = (ctx.topMarkers.PE && ctx.topMarkers.PE.volume || []).includes(ctx.time);

                if (ctx.ce && topOiCE) { score += 16; reasons.push('CE highest OI candle'); }
                if (ctx.ce && topVolCE) { score += 12; reasons.push('CE highest volume candle'); }
                if (ctx.pe && topOiPE) { score += 16; reasons.push('PE highest OI candle'); }
                if (ctx.pe && topVolPE) { score += 12; reasons.push('PE highest volume candle'); }

                if (ctx.ce && (topOiCE || topVolCE) && ctx.ce.buildup === 'Long Build' && ctx.pe && ctx.pe.buildup === 'Short Build') {
                    score += 10;
                    reasons.push('CE OI/Volume aligned with opposite PE Short Build');
                }
                if (ctx.pe && (topOiPE || topVolPE) && ctx.pe.buildup === 'Long Build' && ctx.ce && ctx.ce.buildup === 'Short Build') {
                    score += 10;
                    reasons.push('PE OI/Volume aligned with opposite CE Short Build');
                }

                return { name: 'oiVolume', score: score, reasons: reasons };
            }

            function evaluatePriceMeetScenario(ctx) {
                var score = 0, reasons = [];
                if (ctx.ce && ctx.pe) {
                    if (samePrice(ctx.ce.close, ctx.pe.close, 2)) {
                        score += 10;
                        reasons.push('CE and PE closed near same price');
                    }
                    if (samePrice(ctx.ce.high, ctx.pe.high, 2) || samePrice(ctx.ce.low, ctx.pe.low, 2)) {
                        score += 6;
                        reasons.push('Shared price decision zone formed');
                    }
                }
                return { name: 'priceMeet', score: score, reasons: reasons };
            }

            function evaluateBuildUpScenario(ctx) {
                var score = 0, reasons = [];
                var ceLB = countByBuildUp(ctx.grouped, ctx.time, 'CE', 'Long Build');
                var peSB = countByBuildUp(ctx.grouped, ctx.time, 'PE', 'Short Build');
                var peLB = countByBuildUp(ctx.grouped, ctx.time, 'PE', 'Long Build');
                var ceSB = countByBuildUp(ctx.grouped, ctx.time, 'CE', 'Short Build');

                if (ceLB >= 2 && peSB >= 2) {
                    score += 18;
                    reasons.push('Multi-strike CE LB with PE SB confirmation');
                }
                if (peLB >= 2 && ceSB >= 2) {
                    score += 18;
                    reasons.push('Multi-strike PE LB with CE SB confirmation');
                }
                return { name: 'buildup', score: score, reasons: reasons };
            }

            function evaluateContinuationScenario(ctx) {
                var score = 0, reasons = [];
                if (ctx.prevCe && ctx.ce && ctx.prevPe && ctx.pe) {
                    if (ctx.prevCe.buildup === 'Long Build' && ctx.ce.buildup === 'Long Build' &&
                        ctx.prevPe.buildup === 'Short Build' && ctx.pe.buildup === 'Short Build') {
                        score += 20;
                        reasons.push('Two-candle CE continuation with opposite PE Short Build');
                    }
                    if (ctx.prevPe.buildup === 'Long Build' && ctx.pe.buildup === 'Long Build' &&
                        ctx.prevCe.buildup === 'Short Build' && ctx.ce.buildup === 'Short Build') {
                        score += 20;
                        reasons.push('Two-candle PE continuation with opposite CE Short Build');
                    }
                }
                return { name: 'continuation', score: score, reasons: reasons };
            }

            function evaluateNeighbourConfirmation(ctx) {
                var score = 0, reasons = [];
                var neighbours = getNeighbourStrikes(ctx.strike, ctx.allStrikes);

                function candleAt(strikeKey, side) {
                    if (!strikeKey || !ctx.grouped[strikeKey] || !ctx.grouped[strikeKey][side]) return null;
                    var arr = ctx.grouped[strikeKey][side];
                    return arr.find(function (c) { return c.time === ctx.time; }) || null;
                }

                var prevCE = candleAt(neighbours.prev, 'CE');
                var prevPE = candleAt(neighbours.prev, 'PE');
                var nextCE = candleAt(neighbours.next, 'CE');
                var nextPE = candleAt(neighbours.next, 'PE');

                if ((prevCE && prevCE.buildup === 'Long Build' && prevPE && prevPE.buildup === 'Short Build') ||
                    (nextCE && nextCE.buildup === 'Long Build' && nextPE && nextPE.buildup === 'Short Build')) {
                    score += 14;
                    reasons.push('Nearest strike confirms CE buy structure');
                }
                if ((prevPE && prevPE.buildup === 'Long Build' && prevCE && prevCE.buildup === 'Short Build') ||
                    (nextPE && nextPE.buildup === 'Long Build' && nextCE && nextCE.buildup === 'Short Build')) {
                    score += 14;
                    reasons.push('Nearest strike confirms PE buy structure');
                }
                return { name: 'neighbour', score: score, reasons: reasons };
            }

            function buildFinalCall(ctx, parts) {
                var total = parts.reduce(function (acc, p) { return acc + p.score; }, 0);
                var reasonList = parts.reduce(function (acc, p) { return acc.concat(p.reasons); }, []).slice(0, 7);

                var ceBias = 0;
                var peBias = 0;

                if (ctx.ce && ctx.ce.buildup === 'Long Build') ceBias += 16;
                if (ctx.ce && ctx.ce.buildup === 'Short Build') ceBias -= 10;
                if (ctx.pe && ctx.pe.buildup === 'Long Build') peBias += 16;
                if (ctx.pe && ctx.pe.buildup === 'Short Build') peBias -= 10;

                if (ctx.ce && ctx.pe) {
                    if (ctx.ce.buildup === 'Long Build' && ctx.pe.buildup === 'Short Build') ceBias += 22;
                    if (ctx.pe.buildup === 'Long Build' && ctx.ce.buildup === 'Short Build') peBias += 22;
                }

                var side = 'NEUTRAL';
                if (ceBias > peBias) side = 'CE';
                if (peBias > ceBias) side = 'PE';

                var call = 'No Trade / Mixed Zone';
                if (side === 'CE' && total >= 80) call = 'Strong Buy CE';
                else if (side === 'CE' && total >= 60) call = 'Buy CE';
                else if (side === 'CE' && total >= 40) call = 'Watch CE';
                else if (side === 'PE' && total >= 80) call = 'Strong Buy PE';
                else if (side === 'PE' && total >= 60) call = 'Buy PE';
                else if (side === 'PE' && total >= 40) call = 'Watch PE';
                else if (total >= 30) call = 'Breakout Watch';

                return {
                    call: call,
                    side: side,
                    score: total,
                    reasons: reasonList,
                    parts: parts.map(function (p) { return { name: p.name, score: p.score, reasons: p.reasons }; })
                };
            }

            function evaluateDaySignals(result) {
                ensureSignalStoreDate();

                var grouped = result.data || {};
                var strikes = Object.keys(grouped).sort(function (a, b) { return Number(a) - Number(b); });

                strikes.forEach(function (strike) {
                    var ceArr = grouped[strike] && grouped[strike].CE ? grouped[strike].CE : [];
                    var peArr = grouped[strike] && grouped[strike].PE ? grouped[strike].PE : [];
                    var ce = latestCandle(ceArr);
                    var pe = latestCandle(peArr);
                    var prevCe = previousCandle(ceArr);
                    var prevPe = previousCandle(peArr);
                    var time = ce ? ce.time : (pe ? pe.time : null);
                    if (!time) return;

                    var candleKey = [tradeDateInput.value, strike, time].join('|');
                    if (signalStore.processedKeys[candleKey]) return;

                    var ctx = {
                        strike: strike,
                        time: time,
                        ce: ce,
                        pe: pe,
                        prevCe: prevCe,
                        prevPe: prevPe,
                        grouped: grouped,
                        allStrikes: strikes,
                        midpoint: result.midpoint,
                        firstCandle: result.firstCandle[strike] || {},
                        topMarkers: result.topMarkers[strike] || { CE: { oi: [], volume: [] }, PE: { oi: [], volume: [] } }
                    };

                    var parts = [
                        evaluateMidpointScenario(ctx),
                        evaluateFirstFiveScenario(ctx),
                        evaluateOiVolumeScenario(ctx),
                        evaluatePriceMeetScenario(ctx),
                        evaluateBuildUpScenario(ctx),
                        evaluateContinuationScenario(ctx),
                        evaluateNeighbourConfirmation(ctx)
                    ];

                    var finalCall = buildFinalCall(ctx, parts);
                    if (finalCall.score < 30) return;

                    var entry = {
                        id: candleKey,
                        tradeDate: tradeDateInput.value,
                        candleTime: time,
                        candleLabel: (ce && ce.timestamp) || (pe && pe.timestamp) || String(time),
                        strike: strike,
                        call: finalCall.call,
                        side: finalCall.side,
                        score: finalCall.score,
                        reasons: finalCall.reasons,
                        parts: finalCall.parts,
                        ceBuildUp: ce ? ce.buildup : null,
                        peBuildUp: pe ? pe.buildup : null,
                        createdAt: new Date().toISOString()
                    };

                    signalStore.processedKeys[candleKey] = 1;
                    signalStore.history.unshift(entry);
                    signalStore.history = signalStore.history.slice(0, 200);
                    saveSignalStore();
                    renderSignalPanel();
                    showSignalToast(entry);
                });
            }

            function normalizeSignalEntry(item) {
                item = item || {};

                return {
                    id: item.id || '',
                    tradeDate: item.tradeDate || getActiveTradeDate(),
                    candleTime: Number(item.candleTime || 0),
                    candleLabel: item.candleLabel || '--',
                    strike: item.strike || '--',
                    call: item.call || 'Ignore',
                    side: item.side || 'NEUTRAL',
                    score: Number(item.score || 0),
                    reasons: Array.isArray(item.reasons) ? item.reasons : [],
                    parts: Array.isArray(item.parts) ? item.parts : [],
                    ceBuildUp: item.ceBuildUp || null,
                    peBuildUp: item.peBuildUp || null,
                    createdAt: item.createdAt || null
                };
            }

            function renderSignalPanel() {
                var history = Array.isArray(signalStore.history)
                    ? signalStore.history.map(normalizeSignalEntry)
                    : [];

                signalStore.history = history;
                signalHistoryCount.textContent = String(history.length);

                if (!history.length) {
                    signalHeadlineCall.textContent = 'Waiting for new candle';
                    signalHeadlineScore.textContent = '--';
                    signalHeadlineMeta.textContent = 'No ranked call stored for this day yet';
                    signalHeadlineReasons.innerHTML = '';
                    signalStats.innerHTML = [
                        statCard('Bullish / Bearish', 0),
                        statCard('Buy / Sell', 0),
                        statCard('Ignore', 0),
                        statCard('Time blocks', 0)
                    ].join('');
                    renderGroupedSignalTable([]);
                    return;
                }

                var latest = normalizeSignalEntry(history[0]);
                var grouped = groupSignalsByTime(history);
                var latestGroup = grouped.length ? grouped[0] : null;

                var latestReasons = latestGroup
                    ? latestGroup.items.slice(0, 3).reduce(function (acc, item) {
                        var reasons = Array.isArray(item.reasons) ? item.reasons : [];
                        return acc.concat(reasons.slice(0, 2));
                    }, []).slice(0, 6)
                    : (Array.isArray(latest.reasons) ? latest.reasons.slice(0, 5) : []);

                signalHeadlineCall.textContent = latestGroup
                    ? (latestGroup.overallSignal + ' · ' + latestGroup.time)
                    : (latest.call + ' · ' + latest.strike);

                signalHeadlineScore.textContent = latestGroup ? latestGroup.totalScore : latest.score;
                signalHeadlineMeta.textContent = latestGroup
                    ? (latestGroup.items.length + ' strikes · Top ' + latestGroup.topStrike)
                    : (latest.candleLabel + ' · ' + latest.tradeDate);

                signalHeadlineReasons.innerHTML = latestReasons.map(function (r) {
                    return '<span class="signal-pill">' + escapeHtml(r) + '</span>';
                }).join('');

                var bullishCount = grouped.filter(function (g) {
                    return g.overallSignal === 'Bullish Buy';
                }).length;

                var bearishCount = grouped.filter(function (g) {
                    return g.overallSignal === 'Bearish Sell';
                }).length;

                var buySellBuyCount = grouped.filter(function (g) {
                    return g.overallSignal === 'Buy' || g.overallSignal === 'Bullish Buy';
                }).length;

                var buySellSellCount = grouped.filter(function (g) {
                    return g.overallSignal === 'Sell' || g.overallSignal === 'Bearish Sell';
                }).length;

                var ignoreCount = grouped.filter(function (g) {
                    return g.overallSignal === 'Ignore';
                }).length;

                signalStats.innerHTML = [
                    statCard('Bullish / Bearish', bullishCount + ' / ' + bearishCount),
                    statCard('Buy / Sell', buySellBuyCount + ' / ' + buySellSellCount),
                    statCard('Ignore', ignoreCount),
                    statCard('Time Blocks', grouped.length)
                ].join('');

                renderGroupedSignalTable(history);
            }


            function statCard(label, value) {
                return '<div class="signal-stat"><div class="signal-stat-label">' + escapeHtml(label) + '</div><div class="signal-stat-value">' + escapeHtml(String(value)) + '</div></div>';
            }

            function showSignalToast(entry) {
                var toneClass = entry.side === 'CE' ? 'signal-toast--buyce' : (entry.side === 'PE' ? 'signal-toast--buype' : 'signal-toast--neutral');
                var node = document.createElement('div');
                node.className = 'signal-toast ' + toneClass;
                node.innerHTML = '<div class="flex items-start justify-between gap-3">'
                    + '<div>'
                    + '<div class="text-xs font-semibold uppercase tracking-wide text-slate-500">New candle conclusion</div>'
                    + '<div class="mt-1 text-sm font-extrabold text-slate-900">' + escapeHtml(entry.call) + ' · ' + escapeHtml(entry.strike) + '</div>'
                    + '<div class="mt-1 text-xs text-slate-600">' + escapeHtml(entry.candleLabel) + ' · Score ' + escapeHtml(String(entry.score)) + '</div>'
                    + '</div>'
                    + '<button type="button" class="rounded-md border border-slate-200 px-2 py-1 text-11px font-semibold text-slate-500">✕</button>'
                    + '</div>'
                    + '<div class="mt-3 flex flex-wrap gap-2">'
                    + entry.reasons.slice(0, 3).map(function (r) { return '<span class="signal-pill">' + escapeHtml(r) + '</span>'; }).join('')
                    + '</div>';

                var closeBtn = node.querySelector('button');
                closeBtn.addEventListener('click', function () { node.remove(); });
                signalToastHost.prepend(node);
                setTimeout(function () {
                    if (node.parentNode) node.remove();
                }, 9000);
            }

            function escapeHtml(str) {
                return String(str == null ? '' : str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }



            loadSignalStore();
            startPageUpdatedClock();
        });
    </script>

    <button id="signalPanelToggle"
        type="button"
        class="fixed right-4 top-1/2 z-[9998] -translate-y-1/2 rounded-l-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-xl">
        Signals
    </button>

    <div id="signalPanel"
        class="fixed inset-0 z-[9999] pointer-events-none">
        <div id="signalPanelBackdrop"
            class="absolute inset-0 hidden bg-slate-900/20"></div>

        <aside id="signalPanelDrawer"
            class="absolute right-0 top-0 h-screen w-[380px] max-w-[92vw] translate-x-full overflow-hidden border-l border-slate-200 bg-white shadow-2xl transition-transform duration-300 ease-out pointer-events-auto">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <div>
                    <p class="text-sm font-bold text-slate-900">Signal Conclusion</p>
                    <p class="text-xs text-slate-500">Stored for selected trade day</p>
                </div>
                <button id="signalPanelClose"
                    type="button"
                    class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                    Close
                </button>
            </div>

            <div class="h-[calc(100vh-65px)] overflow-y-auto px-4 py-4">
                <div id="signalHeadline" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Latest call</p>
                            <p id="signalHeadlineCall" class="mt-1 text-lg font-bold text-slate-900">Waiting for new candle</p>
                        </div>
                        <span id="signalHeadlineScore" class="rounded-full bg-white px-3 py-1 text-sm font-bold text-slate-700">--</span>
                    </div>
                    <p id="signalHeadlineMeta" class="mt-2 text-xs text-slate-600">No candle evaluation yet</p>
                    <div id="signalHeadlineReasons" class="mt-3 flex flex-wrap gap-2"></div>
                </div>

                <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-bold text-slate-900">Day summary</p>
                        <button id="clearSignalHistory"
                            type="button"
                            class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-11px font-semibold text-slate-600 hover:bg-slate-50">
                            Clear day
                        </button>
                    </div>
                    <div id="signalStats" class="mt-3 grid grid-cols-2 gap-3"></div>
                </div>

                <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-bold text-slate-900">Candle timeline</p>
                        <span id="signalHistoryCount" class="rounded-full bg-slate-100 px-2.5 py-1 text-11px font-semibold text-slate-600">0</span>
                    </div>
                    <div id="signalHistory" class="mt-3 space-y-3"></div>
                </div>
            </div>
        </aside>
    </div>

    <div id="signalToastHost"
        class="pointer-events-none fixed right-4 top-4 z-[10050] flex w-[380px] max-w-[calc(100vw-24px)] flex-col gap-3">
    </div>

@endsection
