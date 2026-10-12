@extends('layouts.app')

@section('title', 'NIFTY Multi-Chart Matrix (ATM Centered)')

@push('styles')
    <!-- TradingView Lightweight Charts -->
    <script src="https://cdn.jsdelivr.net/npm/lightweight-charts@4.2.3/dist/lightweight-charts.standalone.production.js"></script>
    <style>
        body {
            background-color: #0b0e14 !important;
            color: #d1d5db;
            transition: all 0.2s ease;
        }
        /* Distraction-Free (Zen) Mode */
        body.zen-mode header {
            display: none !important;
        }
        body.zen-mode #page-updated-time {
            display: none !important;
        }
        body.zen-mode main {
            padding: 0 !important;
            margin: 0 !important;
            max-width: 100vw !important;
        }
        body.zen-mode .matrix-main-wrap {
            margin: 0 !important;
            padding: 0 !important;
        }
        body.zen-mode .matrix-toolbar {
            padding: 4px 8px !important;
        }
        .matrix-toolbar {
            background: #131722;
            border-bottom: 1px solid #2a2e39;
        }
        .matrix-card {
            background: #131722;
            border: 1px solid #2a2e39;
            border-radius: 6px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .matrix-card.is-atm-center {
            border: 2px solid #3b82f6;
            box-shadow: 0 0 12px rgba(59, 130, 246, 0.25);
        }
        .matrix-pane-header {
            background: #1a1e29;
            padding: 4px 8px;
            border-bottom: 1px solid #2a2e39;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 28px;
            user-select: none;
        }
        .matrix-chart-container {
            width: 100%;
            position: relative;
        }
        /* Grid Layout Modes */
        .grid-layout-1x1 {
            display: grid;
            grid-template-columns: 1fr;
            gap: 6px;
        }
        .grid-layout-2x2 {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 6px;
        }
        .grid-layout-3x2 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px;
        }
        .grid-layout-4x2 {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 6px;
        }
        .grid-layout-3x3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px;
        }
        .grid-layout-4x3 {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 6px;
        }
        .grid-layout-3x4 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px;
        }
        @media (max-width: 1024px) {
            .grid-layout-3x2, .grid-layout-4x2, .grid-layout-3x3, .grid-layout-4x3, .grid-layout-3x4 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 640px) {
            .grid-layout-2x2, .grid-layout-3x2, .grid-layout-4x2, .grid-layout-3x3, .grid-layout-4x3, .grid-layout-3x4 {
                grid-template-columns: 1fr;
            }
        }
        /* Buttons & Badges */
        .btn-toggle {
            padding: 3px 8px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 4px;
            transition: all 0.15s ease;
            cursor: pointer;
            border: 1px solid #374151;
            background: #1f2937;
            color: #9ca3af;
        }
        .btn-toggle:hover {
            color: #fff;
            border-color: #4b5563;
        }
        .btn-toggle.active {
            background: #2563eb;
            border-color: #3b82f6;
            color: #ffffff;
        }
        .indicator-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 3px;
            border: 1px solid transparent;
            user-select: none;
            opacity: 0.55;
            transition: all 0.15s ease;
        }
        .indicator-badge.active {
            opacity: 1;
            border-color: rgba(255,255,255,0.25);
            box-shadow: 0 0 6px rgba(0,0,0,0.3);
        }
        .chart-legend {
            position: absolute;
            top: 4px;
            left: 8px;
            z-index: 10;
            font-size: 10px;
            font-family: monospace;
            pointer-events: none;
            background: rgba(19, 23, 34, 0.8);
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        /* Fullscreen Overlay Mode */
        .fullscreen-pane {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            z-index: 99999 !important;
            border-radius: 0 !important;
            margin: 0 !important;
        }
        .fullscreen-pane .matrix-chart-container {
            height: calc(100vh - 36px) !important;
        }
    </style>
@endpush

@section('content')
<div class="matrix-main-wrap w-full flex flex-col min-h-screen -mt-4 -mx-2">
    <!-- Top Toolbar -->
    <div class="matrix-toolbar px-3 py-2 flex flex-wrap items-center justify-between gap-2 text-xs">
        <!-- 1. Spot, ATM & Recenter -->
        <div class="flex items-center flex-wrap gap-2">
            <div class="flex items-center bg-gray-800/90 px-2.5 py-1 rounded border border-gray-700">
                <span class="font-bold text-white tracking-wider mr-2 text-sm">NIFTY 50</span>
                <span class="text-gray-400 mr-1 text-[11px]">Spot:</span>
                <span id="spotDisplay" class="font-mono font-bold text-emerald-400 mr-2 text-sm">{{ number_format($spot, 2) }}</span>
                <span class="text-gray-400 mr-1 text-[11px]">ATM:</span>
                <span id="atmDisplay" class="font-mono font-bold text-blue-400 text-sm">{{ number_format($atm, 0) }}</span>
            </div>

            <button id="recenterBtn" title="Recenter grid around current Spot ATM" class="px-2.5 py-1 bg-blue-600 hover:bg-blue-500 text-white rounded font-medium flex items-center gap-1 transition shadow-sm">
                <span>⚡ Recenter ATM</span>
            </button>

            <!-- Expiry Selection -->
            <div class="flex items-center bg-gray-800 rounded border border-gray-700 p-0.5">
                <button type="button" data-expiry="{{ $currentExpiry }}" class="expiry-btn px-2 py-0.5 rounded text-[11px] font-semibold transition {{ $selectedExpiry === $currentExpiry ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white' }}">
                    Current ({{ \Carbon\Carbon::parse($currentExpiry)->format('d M') }})
                </button>
                @if($nextExpiry && $nextExpiry !== $currentExpiry)
                <button type="button" data-expiry="{{ $nextExpiry }}" class="expiry-btn px-2 py-0.5 rounded text-[11px] font-semibold transition {{ $selectedExpiry === $nextExpiry ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white' }}">
                    Next ({{ \Carbon\Carbon::parse($nextExpiry)->format('d M') }})
                </button>
                @endif
                <select id="allExpiriesSelect" class="bg-gray-900 text-gray-300 text-[11px] rounded px-1.5 py-0.5 border-0 focus:ring-1 focus:ring-blue-500 ml-1">
                    @foreach($expiries as $exp)
                        <option value="{{ $exp->expiry_date }}" {{ $selectedExpiry === $exp->expiry_date ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::parse($exp->expiry_date)->format('d M Y') }} {{ $exp->is_current ? '★' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- MidPoints & Previous Day Close Info -->
            <div class="flex items-center gap-1.5 bg-gray-800/80 px-2 py-1 rounded border border-gray-700 text-[11px]">
                <span class="text-gray-400" title="Previous Day Close">PDC:</span>
                <span class="font-mono font-bold text-white">{{ number_format($pdc, 2) }}</span>
                <span class="text-gray-500">|</span>
                <span class="text-emerald-400 font-semibold" title="Current Week MidPoint">CW Mid:</span>
                <input type="number" step="0.01" id="cwMidInput" value="{{ $cwMid }}" class="w-14 bg-gray-900 text-emerald-300 font-mono font-bold text-center rounded px-1 py-0 border border-gray-700 text-[11px]">
                <span class="text-red-400 font-semibold ml-1" title="Next Week MidPoint">NW Mid:</span>
                <input type="number" step="0.01" id="nwMidInput" value="{{ $nwMid }}" class="w-14 bg-gray-900 text-red-300 font-mono font-bold text-center rounded px-1 py-0 border border-gray-700 text-[11px]">
            </div>
        </div>

        <!-- 2. Grid Size, Chart Height & Timeframe -->
        <div class="flex items-center flex-wrap gap-2">
            <!-- Grid Layout Selector -->
            <div class="flex items-center bg-gray-800 rounded border border-gray-700 p-0.5">
                <span class="text-gray-400 px-1 text-[10px] uppercase font-bold">Grid:</span>
                <button class="layout-btn btn-toggle {{ $layout === '1x1' ? 'active' : '' }}" data-layout="1x1">1×1</button>
                <button class="layout-btn btn-toggle {{ $layout === '2x2' ? 'active' : '' }}" data-layout="2x2">2×2</button>
                <button class="layout-btn btn-toggle {{ $layout === '3x2' ? 'active' : '' }}" data-layout="3x2">3×2</button>
                <button class="layout-btn btn-toggle {{ $layout === '4x2' ? 'active' : '' }}" data-layout="4x2">4×2</button>
                <button class="layout-btn btn-toggle {{ $layout === '3x3' ? 'active' : '' }}" data-layout="3x3">3×3</button>
                <button class="layout-btn btn-toggle {{ $layout === '4x3' ? 'active' : '' }}" data-layout="4x3">4×3</button>
                <button class="layout-btn btn-toggle {{ $layout === '3x4' ? 'active' : '' }}" data-layout="3x4">3×4</button>
            </div>

            <!-- Height Dropdown -->
            <div class="flex items-center bg-gray-800 px-2 py-0.5 rounded border border-gray-700">
                <label for="chartHeightSelect" class="text-gray-400 text-[11px] font-semibold mr-1.5">Height:</label>
                <select id="chartHeightSelect" class="bg-gray-900 text-gray-200 border border-gray-700 rounded px-1.5 py-0.5 text-[11px] font-bold focus:ring-1 focus:ring-blue-500">
                    <option value="200" {{ $chartHeight == '200' ? 'selected' : '' }}>200px</option>
                    <option value="300" {{ $chartHeight == '300' ? 'selected' : '' }}>300px</option>
                    <option value="400" {{ $chartHeight == '400' ? 'selected' : '' }}>400px</option>
                    <option value="500" {{ $chartHeight == '500' ? 'selected' : '' }}>500px</option>
                </select>
            </div>

            <!-- Timeframe -->
            <div class="flex items-center bg-gray-800 rounded border border-gray-700 p-0.5">
                <span class="text-gray-400 px-1 text-[10px] uppercase font-bold">TF:</span>
                <button class="tf-btn btn-toggle {{ $timeframe === '1m' ? 'active' : '' }}" data-tf="1m">1m</button>
                <button class="tf-btn btn-toggle {{ $timeframe === '3m' ? 'active' : '' }}" data-tf="3m">3m</button>
                <button class="tf-btn btn-toggle {{ $timeframe === '5m' ? 'active' : '' }}" data-tf="5m">5m</button>
                <button class="tf-btn btn-toggle {{ $timeframe === '15m' ? 'active' : '' }}" data-tf="15m">15m</button>
            </div>

            <!-- Days Back -->
            <div class="flex items-center bg-gray-800 rounded border border-gray-700 p-0.5">
                <span class="text-gray-400 px-1 text-[10px] uppercase font-bold">Days:</span>
                <button class="days-btn btn-toggle {{ $daysBack === '1' ? 'active' : '' }}" data-days="1">1D</button>
                <button class="days-btn btn-toggle {{ $daysBack === '2' ? 'active' : '' }}" data-days="2">2D</button>
                <button class="days-btn btn-toggle {{ $daysBack === '3' ? 'active' : '' }}" data-days="3">3D</button>
                <button class="days-btn btn-toggle {{ $daysBack === '5' ? 'active' : '' }}" data-days="5">5D</button>
                <button class="days-btn btn-toggle {{ $daysBack === 'all' ? 'active' : '' }}" data-days="all" title="Show maximum available days of contract">All</button>
            </div>
        </div>

        <!-- 3. Indicators (Index & Fut), Labels Toggle & Zen Mode -->
        <div class="flex items-center flex-wrap gap-1.5">
            <span class="text-gray-400 text-[10px] uppercase font-bold mr-0.5">Ind:</span>
            <!-- Core Moving Averages -->
            <span class="indicator-badge active" data-ind="ema9" style="background: rgba(6, 182, 212, 0.2); color: #06b6d4;" title="EMA 9 (Cyan)">
                <input type="checkbox" id="chkEma9" checked class="accent-cyan-400 pointer-events-none w-3 h-3"> EMA 9
            </span>
            <span class="indicator-badge active" data-ind="ema21" style="background: rgba(168, 85, 247, 0.2); color: #c084fc;" title="EMA 21 (Purple)">
                <input type="checkbox" id="chkEma21" checked class="accent-purple-400 pointer-events-none w-3 h-3"> EMA 21
            </span>
            <span class="indicator-badge active" data-ind="sma20" style="background: rgba(249, 115, 22, 0.2); color: #fb923c;" title="SMA 20 (Orange)">
                <input type="checkbox" id="chkSma20" checked class="accent-orange-400 pointer-events-none w-3 h-3"> SMA 20
            </span>
            <span class="indicator-badge active" data-ind="vwma20" style="background: rgba(59, 130, 246, 0.2); color: #60a5fa;" title="VWMA 20 (Blue)">
                <input type="checkbox" id="chkVwma20" checked class="accent-blue-400 pointer-events-none w-3 h-3"> VWMA 20
            </span>
            <span class="indicator-badge active" data-ind="vwap" style="background: rgba(234, 179, 8, 0.2); color: #facc15;" title="Session VWAP (Yellow)">
                <input type="checkbox" id="chkVwap" checked class="accent-yellow-400 pointer-events-none w-3 h-3"> VWAP
            </span>

            <!-- 246 Period Indicators (New Option) -->
            <span class="indicator-badge" data-ind="ema246" style="background: rgba(244, 63, 94, 0.2); color: #f43f5e;" title="EMA 246 (Rose)">
                <input type="checkbox" id="chkEma246" class="accent-rose-400 pointer-events-none w-3 h-3"> EMA 246
            </span>
            <span class="indicator-badge" data-ind="sma246" style="background: rgba(217, 119, 6, 0.2); color: #d97706;" title="SMA 246 (Amber)">
                <input type="checkbox" id="chkSma246" class="accent-amber-500 pointer-events-none w-3 h-3"> SMA 246
            </span>
            <span class="indicator-badge" data-ind="vwma246" style="background: rgba(132, 204, 22, 0.2); color: #84cc16;" title="VWMA 246 (Lime)">
                <input type="checkbox" id="chkVwma246" class="accent-lime-400 pointer-events-none w-3 h-3"> VWMA 246
            </span>

            <!-- Show/Hide Indicator Price Labels Toggle -->
            <button id="toggleLabelsBtn" class="px-2 py-0.5 rounded text-[11px] font-semibold border border-gray-700 bg-gray-800 text-gray-300 hover:text-white transition flex items-center gap-1" title="Show or hide price axis labels to prevent candle override">
                <span>🏷️</span>
                <span id="labelStatusText">Labels: OFF</span>
            </button>

            <!-- Distraction Free Mode Button -->
            <button id="zenModeBtn" class="px-2.5 py-1 bg-indigo-700 hover:bg-indigo-600 text-white rounded font-bold flex items-center gap-1 transition shadow-sm" title="Toggle Distraction-Free View (Hide headers & maximize charts. Shortcut: Press 'D' or Escape)">
                <span id="zenIcon">🖥️</span>
                <span id="zenText">Distraction Free</span>
            </button>

            <!-- Refresh Button -->
            <button id="refreshDataBtn" class="px-2.5 py-1 bg-gray-800 hover:bg-gray-700 text-gray-200 rounded font-semibold border border-gray-600 flex items-center gap-1 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                <span>Refresh</span>
            </button>
        </div>
    </div>

    <!-- Charts Grid Area -->
    <div class="flex-1 p-2 bg-[#0b0e14]">
        <div id="matrixGrid" class="grid-layout-{{ $layout }}">
            @foreach($tiles as $tile)
                <div class="matrix-card {{ $tile['is_center'] ? 'is-atm-center' : '' }}" id="card_{{ $tile['id'] }}" data-tile-id="{{ $tile['id'] }}">
                    <!-- Mini Pane Header -->
                    <div class="matrix-pane-header">
                        <div class="flex items-center gap-1.5">
                            @if($tile['is_center'])
                                <span class="bg-blue-600 text-white text-[9px] font-extrabold px-1 py-0.2 rounded uppercase tracking-wider">ATM Center</span>
                            @endif

                            <!-- Strike / Instrument selector -->
                            <select class="tile-type-select bg-gray-900 text-gray-200 border border-gray-700 rounded text-[11px] font-bold px-1.5 py-0 focus:ring-1 focus:ring-blue-500" data-tile-id="{{ $tile['id'] }}">
                                <option value="BOTH" {{ $tile['type'] === 'BOTH' ? 'selected' : '' }}>CE & PE</option>
                                <option value="CE" {{ $tile['type'] === 'CE' ? 'selected' : '' }}>CALL (CE Only)</option>
                                <option value="PE" {{ $tile['type'] === 'PE' ? 'selected' : '' }}>PUT (PE Only)</option>
                                <option value="INDEX" {{ $tile['type'] === 'INDEX' ? 'selected' : '' }}>NIFTY INDEX</option>
                                <option value="FUT" {{ $tile['type'] === 'FUT' ? 'selected' : '' }}>NIFTY FUT</option>
                            </select>

                            @if(!in_array($tile['type'], ['INDEX', 'FUT']))
                                <input type="number" step="50" class="tile-strike-input w-16 bg-gray-900 text-gray-100 font-mono font-bold text-center rounded px-1 py-0 border border-gray-700 text-[11px]"
                                       data-tile-id="{{ $tile['id'] }}" value="{{ $tile['strike'] }}">
                            @endif
                        </div>

                        <!-- Right: Latest Price Badges & Fullscreen -->
                        <div class="flex items-center gap-2">
                            <span class="tile-ltp font-mono font-bold text-xs" id="ltp_{{ $tile['id'] }}">--</span>
                            <button class="pane-fullscreen-btn text-gray-400 hover:text-white p-0.5 rounded transition" data-tile-id="{{ $tile['id'] }}" title="Toggle Fullscreen">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Chart Container -->
                    <div class="matrix-chart-container" id="chart_container_{{ $tile['id'] }}" style="height: {{ $chartHeight }}px;">
                        <div class="chart-legend" id="legend_{{ $tile['id'] }}">
                            <span class="text-gray-400 font-bold" id="legend_title_{{ $tile['id'] }}">{{ $tile['label'] }}</span>
                            <span class="text-gray-300 ml-1" id="legend_ohlc_{{ $tile['id'] }}"></span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // ── Application State ──────────────────────────────────────────────────
    var state = {
        symbol: '{{ $symbol }}',
        spot: {{ $spot }},
        atm: {{ $atm }},
        step: {{ $step }},
        currentExpiry: '{{ $currentExpiry }}',
        selectedExpiry: '{{ $selectedExpiry }}',
        cwMid: {{ $cwMid }},
        nwMid: {{ $nwMid }},
        pdc: {{ $pdc }},
        layout: '{{ $layout }}',
        timeframe: '{{ $timeframe }}',
        daysBack: '{{ $daysBack }}',
        chartHeight: '{{ $chartHeight }}',
        showLabels: false, // Default false to prevent indicator labels overriding candles!
        tiles: @json($tiles),
        indicators: {
            ema9: true,
            ema21: true,
            ema246: false,
            sma20: true,
            sma246: false,
            vwma20: true,
            vwma246: false,
            vwap: true,
        }
    };

    var charts = {};       // tileId -> LightweightChart instance
    var seriesMap = {};    // tileId -> { ceSeries, peSeries, ema9, ema21, ema246, sma20, sma246, vwma20, vwma246, vwap, lines: [] }
    var rawDataMap = {};   // tileId -> raw data bundle from server

    // ── Indicator Colors ───────────────────────────────────────────────────
    var COLORS = {
        bg: '#131722',
        grid: '#1f2430',
        text: '#9ca3af',
        crosshair: '#758696',
        // Candlestick colors
        ceUp: '#089981',
        ceDown: '#f23645',
        peUp: '#06b6d4',
        peDown: '#f97316',
        indexUp: '#089981',
        indexDown: '#f23645',
        // Moving Averages (Index & Fut only)
        ema9: '#06b6d4',
        ema21: '#c084fc',
        ema246: '#f43f5e',   // Rose
        sma20: '#fb923c',
        sma246: '#d97706',   // Amber
        vwma20: '#3b82f6',
        vwma246: '#84cc16',  // Lime
        vwap: '#facc15',     // Yellow
        // Logic Lines
        r1: '#ef4444',       // Red (PDC + CW Mid)
        s1: '#10b981',       // Green (PDC - CW Mid)
        r2: '#dc2626',       // Darker Red / Dashed (PDC + NW Mid)
        s2: '#059669',       // Darker Green / Dashed (PDC - NW Mid)
        optCwMid: '#10b981', // Green (CW Midpoint)
        optNwMid: '#ef4444', // Red (NW Midpoint)
    };

    // ── IST Time Formatter for Lightweight Charts ──────────────────────────
    function formatChartTime(time) {
        if (!time) return '';
        var ts = typeof time === 'number' ? time : (time.timestamp || Number(time));
        if (!ts || isNaN(ts)) return '';
        var d = new Date(ts * 1000);
        var day = d.getUTCDate().toString().padStart(2, '0');
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        var month = months[d.getUTCMonth()];
        var h = d.getUTCHours().toString().padStart(2, '0');
        var m = d.getUTCMinutes().toString().padStart(2, '0');
        return day + ' ' + month + ' ' + h + ':' + m;
    }

    // ── Chart Creation Engine ──────────────────────────────────────────────
    function initChart(tile) {
        var container = document.getElementById('chart_container_' + tile.id);
        if (!container) return;

        // Cleanup existing
        if (charts[tile.id]) {
            charts[tile.id].remove();
            delete charts[tile.id];
        }

        var chart = LightweightCharts.createChart(container, {
            layout: {
                background: { color: COLORS.bg },
                textColor: COLORS.text,
                fontSize: 10,
            },
            grid: {
                vertLines: { color: COLORS.grid },
                horzLines: { color: COLORS.grid },
            },
            crosshair: {
                mode: LightweightCharts.CrosshairMode.Normal,
                vertLine: { color: COLORS.crosshair, style: LightweightCharts.LineStyle.Dotted },
                horzLine: { color: COLORS.crosshair, style: LightweightCharts.LineStyle.Dotted },
            },
            localization: {
                timeFormatter: function (time) {
                    return formatChartTime(time);
                }
            },
            timeScale: {
                borderColor: COLORS.grid,
                timeVisible: true,
                secondsVisible: false,
                barSpacing: 6,
                minBarSpacing: 3,
                tickMarkFormatter: function (time, tickMarkType) {
                    var ts = typeof time === 'number' ? time : (time.timestamp || Number(time));
                    if (!ts || isNaN(ts)) return null;
                    var d = new Date(ts * 1000);
                    if (tickMarkType < 3) {
                        var day = d.getUTCDate().toString().padStart(2, '0');
                        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                        return day + ' ' + months[d.getUTCMonth()];
                    }
                    var h = d.getUTCHours().toString().padStart(2, '0');
                    var m = d.getUTCMinutes().toString().padStart(2, '0');
                    return h + ':' + m;
                }
            },
            rightPriceScale: {
                borderColor: COLORS.grid,
                scaleMargins: { top: 0.1, bottom: 0.15 },
            },
            handleScroll: { mouseWheel: true, pressedMouseMove: true },
            handleScale: { axisPressedMouseMove: true, mouseWheel: true, pinch: true },
        });

        // 1. Candlestick Series:
        // CE / Primary Candlestick Series
        var ceSeries = chart.addCandlestickSeries({
            upColor: COLORS.ceUp,
            downColor: COLORS.ceDown,
            borderUpColor: COLORS.ceUp,
            borderDownColor: COLORS.ceDown,
            wickUpColor: COLORS.ceUp,
            wickDownColor: COLORS.ceDown,
            priceFormat: { type: 'price', precision: 2, minMove: 0.05 },
            title: tile.type === 'INDEX' ? 'INDEX' : (tile.type === 'FUT' ? 'FUT' : 'CE'),
        });

        // PE Candlestick Series (Used for dual option tiles)
        var peSeries = chart.addCandlestickSeries({
            upColor: COLORS.peUp,
            downColor: COLORS.peDown,
            borderUpColor: COLORS.peUp,
            borderDownColor: COLORS.peDown,
            wickUpColor: COLORS.peUp,
            wickDownColor: COLORS.peDown,
            priceFormat: { type: 'price', precision: 2, minMove: 0.05 },
            title: 'PE',
        });

        // 2. Technical Indicators (Index & Fut only)
        function createLine(color, title) {
            return chart.addLineSeries({
                color: color,
                lineWidth: 1.5,
                title: state.showLabels ? title : '',
                priceLineVisible: false,
                lastValueVisible: state.showLabels,
            });
        }

        var ema9Series   = createLine(COLORS.ema9, 'EMA 9');
        var ema21Series  = createLine(COLORS.ema21, 'EMA 21');
        var ema246Series = createLine(COLORS.ema246, 'EMA 246');
        var sma20Series  = createLine(COLORS.sma20, 'SMA 20');
        var sma246Series = createLine(COLORS.sma246, 'SMA 246');
        var vwma20Series = createLine(COLORS.vwma20, 'VWMA 20');
        var vwma246Series= createLine(COLORS.vwma246, 'VWMA 246');
        var vwapSeries   = createLine(COLORS.vwap, 'VWAP');

        charts[tile.id] = chart;
        seriesMap[tile.id] = {
            ceSeries: ceSeries,
            peSeries: peSeries,
            ema9: ema9Series,
            ema21: ema21Series,
            ema246: ema246Series,
            sma20: sma20Series,
            sma246: sma246Series,
            vwma20: vwma20Series,
            vwma246: vwma246Series,
            vwap: vwapSeries,
            priceLines: [],
        };

        // Crosshair move tooltip
        chart.subscribeCrosshairMove(function (param) {
            var legendElem = document.getElementById('legend_ohlc_' + tile.id);
            if (!legendElem) return;

            if (param.time) {
                var timeStr = formatChartTime(param.time);
                var ceData = param.seriesData.get(ceSeries);
                var peData = param.seriesData.get(peSeries);

                var parts = [`<span style="color:#94a3b8;font-weight:600;">${timeStr}</span>`];
                if (ceData && tile.type !== 'PE') {
                    var ceColor = ceData.close >= ceData.open ? '#089981' : '#f23645';
                    var lbl = tile.type === 'INDEX' ? 'INDEX' : (tile.type === 'FUT' ? 'FUT' : 'CE');
                    parts.push(`<span style="color:#089981;font-weight:bold;">${lbl}:</span> <span style="color:${ceColor}">O:${ceData.open.toFixed(2)} H:${ceData.high.toFixed(2)} L:${ceData.low.toFixed(2)} C:${ceData.close.toFixed(2)}</span>`);
                }
                if (peData && (tile.type === 'BOTH' || tile.type === 'PE')) {
                    var peColor = peData.close >= peData.open ? '#06b6d4' : '#f97316';
                    parts.push(`<span style="color:#06b6d4;font-weight:bold;">PE:</span> <span style="color:${peColor}">O:${peData.open.toFixed(2)} H:${peData.high.toFixed(2)} L:${peData.low.toFixed(2)} C:${peData.close.toFixed(2)}</span>`);
                }
                legendElem.innerHTML = parts.join(' | ');
            }
        });

        // ResizeObserver
        var ro = new ResizeObserver(function (entries) {
            for (var entry of entries) {
                var cr = entry.contentRect;
                if (cr.width > 0 && cr.height > 0) {
                    chart.applyOptions({ width: cr.width, height: cr.height });
                }
            }
        });
        ro.observe(container);
    }

    // ── Initialize All Grid Charts ─────────────────────────────────────────
    function initAllCharts() {
        state.tiles.forEach(function (tile) {
            initChart(tile);
        });
    }

    // ── Fetch Multi-Day Data & Populate Panes ──────────────────────────────
    function loadData() {
        var payload = {
            expiry_date: state.selectedExpiry,
            timeframe: state.timeframe,
            days_back: state.daysBack,
            cw_mid: parseFloat(document.getElementById('cwMidInput').value || state.cwMid),
            nw_mid: parseFloat(document.getElementById('nwMidInput').value || state.nwMid),
            pdc: state.pdc,
            tiles: state.tiles.map(function (t) {
                return { id: t.id, type: t.type, strike: t.strike };
            })
        };

        fetch('{{ route('api.nifty.matrix.chart.data') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(payload)
        })
        .then(function (res) { return res.json(); })
        .then(function (resp) {
            if (!resp.success || !resp.tiles) return;

            Object.keys(resp.tiles).forEach(function (tileId) {
                var tileData = resp.tiles[tileId];
                rawDataMap[tileId] = tileData;
                applyTileData(tileId, tileData);
            });
        })
        .catch(function (err) {
            console.error('Error fetching matrix data:', err);
        });
    }

    // ── Apply Data, Dual CE/PE, Indicators & Logic Lines ────────────────────
    function applyTileData(tileId, data) {
        var s = seriesMap[tileId];
        var chart = charts[tileId];
        var tile = state.tiles.find(function (t) { return t.id === parseInt(tileId); });
        if (!s || !chart || !tile) return;

        var isIndexOrFut = (tile.type === 'INDEX' || tile.type === 'FUT');

        // Clear existing logic lines
        s.priceLines.forEach(function (pl) {
            try { s.ceSeries.removePriceLine(pl); } catch (e) {}
            try { s.peSeries.removePriceLine(pl); } catch (e) {}
        });
        s.priceLines = [];

        if (isIndexOrFut) {
            // INDEX or FUT: Single series (use ceSeries), hide peSeries
            s.peSeries.applyOptions({ visible: false });
            s.ceSeries.applyOptions({
                visible: true,
                title: tile.type,
                upColor: COLORS.indexUp,
                downColor: COLORS.indexDown,
                borderUpColor: COLORS.indexUp,
                borderDownColor: COLORS.indexDown,
                wickUpColor: COLORS.indexUp,
                wickDownColor: COLORS.indexDown,
            });

            if (data.candles && data.candles.length > 0) {
                s.ceSeries.setData(data.candles);
                var latest = data.candles[data.candles.length - 1];
                var ltpEl = document.getElementById('ltp_' + tileId);
                if (ltpEl && latest) {
                    var color = latest.close >= latest.open ? '#34d399' : '#f87171';
                    ltpEl.innerHTML = `<span style="color:${color}">₹${latest.close.toFixed(2)}</span>`;
                }
            }

            // Indicators: Render EMA, SMA, VWMA, VWAP exclusively for Index & Fut
            var inds = data.indicators || {};

            function applyIndicator(seriesObj, dataArray, isVisible, labelTitle) {
                seriesObj.applyOptions({
                    visible: isVisible,
                    title: (state.showLabels && isVisible) ? labelTitle : '',
                    lastValueVisible: (state.showLabels && isVisible),
                });
                if (isVisible && dataArray) {
                    seriesObj.setData(dataArray);
                }
            }

            applyIndicator(s.ema9, inds.ema9, state.indicators.ema9, 'EMA 9');
            applyIndicator(s.ema21, inds.ema21, state.indicators.ema21, 'EMA 21');
            applyIndicator(s.ema246, inds.ema246, state.indicators.ema246, 'EMA 246');
            applyIndicator(s.sma20, inds.sma20, state.indicators.sma20, 'SMA 20');
            applyIndicator(s.sma246, inds.sma246, state.indicators.sma246, 'SMA 246');
            applyIndicator(s.vwma20, inds.vwma20, state.indicators.vwma20, 'VWMA 20');
            applyIndicator(s.vwma246, inds.vwma246, state.indicators.vwma246, 'VWMA 246');
            applyIndicator(s.vwap, inds.vwap, state.indicators.vwap, 'VWAP');

            // Logic Lines for Index & Future:
            // 4 Lines: 2 Resistance (Red) + 2 Support (Green)
            var lines = data.logic_lines || {};

            function addLogicLine(priceVal, color, lineStyle, title) {
                if (!priceVal) return;
                var pl = s.ceSeries.createPriceLine({
                    price: priceVal,
                    color: color,
                    lineWidth: 1.5,
                    lineStyle: lineStyle,
                    axisLabelVisible: state.showLabels,
                    title: state.showLabels ? title : '',
                });
                s.priceLines.push(pl);
            }

            addLogicLine(lines.r1, COLORS.r1, LightweightCharts.LineStyle.Solid, `R1 (+CW Mid): ${lines.r1}`);
            addLogicLine(lines.s1, COLORS.s1, LightweightCharts.LineStyle.Solid, `S1 (-CW Mid): ${lines.s1}`);
            addLogicLine(lines.r2, COLORS.r2, LightweightCharts.LineStyle.Dashed, `R2 (+NW Mid): ${lines.r2}`);
            addLogicLine(lines.s2, COLORS.s2, LightweightCharts.LineStyle.Dashed, `S2 (-NW Mid): ${lines.s2}`);

        } else {
            // OPTION STRIKE: Dual CE & PE on the same chart
            // Hide all indicators on option charts
            s.ema9.applyOptions({ visible: false, lastValueVisible: false });
            s.ema21.applyOptions({ visible: false, lastValueVisible: false });
            s.ema246.applyOptions({ visible: false, lastValueVisible: false });
            s.sma20.applyOptions({ visible: false, lastValueVisible: false });
            s.sma246.applyOptions({ visible: false, lastValueVisible: false });
            s.vwma20.applyOptions({ visible: false, lastValueVisible: false });
            s.vwma246.applyOptions({ visible: false, lastValueVisible: false });
            s.vwap.applyOptions({ visible: false, lastValueVisible: false });

            var showCe = (tile.type === 'BOTH' || tile.type === 'CE');
            var showPe = (tile.type === 'BOTH' || tile.type === 'PE');

            s.ceSeries.applyOptions({ visible: showCe, title: 'CE' });
            s.peSeries.applyOptions({ visible: showPe, title: 'PE' });

            var ceLatest = null;
            var peLatest = null;

            if (showCe && data.ce_candles && data.ce_candles.length > 0) {
                s.ceSeries.setData(data.ce_candles);
                ceLatest = data.ce_candles[data.ce_candles.length - 1];
            }
            if (showPe && data.pe_candles && data.pe_candles.length > 0) {
                s.peSeries.setData(data.pe_candles);
                peLatest = data.pe_candles[data.pe_candles.length - 1];
            }

            // Header LTP badge
            var ltpEl = document.getElementById('ltp_' + tileId);
            if (ltpEl) {
                var ltpBadges = [];
                if (ceLatest && showCe) {
                    ltpBadges.push(`<span style="color:#089981">CE: ₹${ceLatest.close.toFixed(2)}</span>`);
                }
                if (peLatest && showPe) {
                    ltpBadges.push(`<span style="color:#06b6d4">PE: ₹${peLatest.close.toFixed(2)}</span>`);
                }
                ltpEl.innerHTML = ltpBadges.join(' | ');
            }

            // Option Logic Lines: Exactly Two Lines (CW Mid & NW Mid)
            var optLines = data.logic_lines || {};

            if (optLines.cw_mid) {
                s.priceLines.push(s.ceSeries.createPriceLine({
                    price: optLines.cw_mid,
                    color: COLORS.optCwMid,
                    lineWidth: 2,
                    lineStyle: LightweightCharts.LineStyle.Solid,
                    axisLabelVisible: state.showLabels,
                    title: state.showLabels ? `CW Mid: ${optLines.cw_mid}` : '',
                }));
            }

            if (optLines.nw_mid) {
                s.priceLines.push(s.ceSeries.createPriceLine({
                    price: optLines.nw_mid,
                    color: COLORS.optNwMid,
                    lineWidth: 2,
                    lineStyle: LightweightCharts.LineStyle.Solid,
                    axisLabelVisible: state.showLabels,
                    title: state.showLabels ? `NW Mid: ${optLines.nw_mid}` : '',
                }));
            }
        }

        // Fit time-scale
        chart.timeScale().fitContent();
    }

    // ── Recenter Grid Around ATM ───────────────────────────────────────────
    function recenterAroundAtm() {
        var step = state.step;
        var atm = state.atm;

        if (state.layout === '1x1') {
            state.tiles[0].strike = atm;
            state.tiles[0].type = 'BOTH';
        } else if (state.layout === '2x2') {
            var offsets = [-1, 0, 1, 2];
            for (var i = 0; i < 4; i++) {
                state.tiles[i].strike = atm + (offsets[i] * step);
                state.tiles[i].is_center = (offsets[i] === 0);
            }
        } else if (state.layout === '3x2') {
            var offsets = [-2, -1, 0, 1, 2, 3];
            for (var i = 0; i < 6; i++) {
                state.tiles[i].strike = atm + (offsets[i] * step);
                state.tiles[i].is_center = (offsets[i] === 0);
            }
        } else if (state.layout === '4x2') {
            var offsets = [-3, -2, -1, 0, 1, 2, 3, 4];
            for (var i = 0; i < 8; i++) {
                state.tiles[i].strike = atm + (offsets[i] * step);
                state.tiles[i].is_center = (offsets[i] === 0);
            }
        } else if (state.layout === '3x3') {
            for (var i = 0; i < 9; i++) {
                var offset = i - 4;
                state.tiles[i].strike = atm + (offset * step);
                state.tiles[i].is_center = (offset === 0);
            }
        } else if (state.layout === '4x3' || state.layout === '3x4') {
            var offsets = [-4, -3, -2, -1, 0, 1, 2, 3, 4, 5];
            for (var i = 0; i < 10; i++) {
                state.tiles[i + 2].strike = atm + (offsets[i] * step);
                state.tiles[i + 2].is_center = (offsets[i] === 0);
            }
        }

        // Update inputs and titles
        state.tiles.forEach(function (tile) {
            var input = document.querySelector(`.tile-strike-input[data-tile-id="${tile.id}"]`);
            if (input) input.value = tile.strike;

            var title = document.getElementById('legend_title_' + tile.id);
            if (title) title.innerText = (tile.strike > 0 ? tile.strike + ' ' : '') + tile.type;

            var card = document.getElementById('card_' + tile.id);
            if (card) {
                if (tile.is_center) card.classList.add('is-atm-center');
                else card.classList.remove('is-atm-center');
            }
        });

        loadData();
    }

    // ── Adjust Chart Heights Dropdown ──────────────────────────────────────
    function setChartHeight(newHeight) {
        state.chartHeight = newHeight;
        var hPx = parseInt(newHeight);

        document.querySelectorAll('.matrix-chart-container').forEach(function (el) {
            el.style.height = hPx + 'px';
        });

        setTimeout(function () {
            Object.keys(charts).forEach(function (tileId) {
                var container = document.getElementById('chart_container_' + tileId);
                var chart = charts[tileId];
                if (container && chart) {
                    chart.applyOptions({
                        width: container.clientWidth,
                        height: hPx
                    });
                    chart.timeScale().fitContent();
                }
            });
        }, 50);
    }

    document.getElementById('chartHeightSelect').addEventListener('change', function (e) {
        setChartHeight(e.target.value);
    });

    // ── Fullscreen Toggle with Stretch Fix ─────────────────────────────────
    function toggleFullscreen(tileId) {
        var card = document.getElementById('card_' + tileId);
        if (!card) return;

        var isNowFullscreen = card.classList.toggle('fullscreen-pane');

        requestAnimationFrame(function () {
            setTimeout(function () {
                var container = document.getElementById('chart_container_' + tileId);
                var chart = charts[tileId];
                if (chart && container) {
                    var targetHeight = isNowFullscreen ? (window.innerHeight - 36) : parseInt(state.chartHeight);
                    chart.applyOptions({
                        width: container.clientWidth,
                        height: targetHeight
                    });
                    chart.timeScale().fitContent();
                }
            }, 60);
        });
    }

    document.querySelectorAll('.pane-fullscreen-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tileId = btn.getAttribute('data-tile-id');
            toggleFullscreen(tileId);
        });
    });

    // ── Distraction Free (Zen Mode) ────────────────────────────────────────
    function toggleZenMode() {
        var isZen = document.body.classList.toggle('zen-mode');
        var btnText = document.getElementById('zenText');
        var btnIcon = document.getElementById('zenIcon');
        var zenBtn = document.getElementById('zenModeBtn');

        if (isZen) {
            btnText.innerText = 'Exit Zen';
            btnIcon.innerText = '✕';
            zenBtn.classList.remove('bg-indigo-700');
            zenBtn.classList.add('bg-rose-700', 'hover:bg-rose-600');
        } else {
            btnText.innerText = 'Distraction Free';
            btnIcon.innerText = '🖥️';
            zenBtn.classList.remove('bg-rose-700', 'hover:bg-rose-600');
            zenBtn.classList.add('bg-indigo-700');
        }

        setTimeout(function () {
            Object.keys(charts).forEach(function (tileId) {
                var container = document.getElementById('chart_container_' + tileId);
                var chart = charts[tileId];
                if (container && chart) {
                    chart.applyOptions({ width: container.clientWidth });
                    chart.timeScale().fitContent();
                }
            });
        }, 60);
    }

    document.getElementById('zenModeBtn').addEventListener('click', toggleZenMode);

    // Keyboard shortcut: Press 'D' or 'Escape' for Zen Mode
    window.addEventListener('keydown', function (e) {
        if (e.key === 'd' || e.key === 'D') {
            if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'SELECT') {
                toggleZenMode();
            }
        } else if (e.key === 'Escape' && document.body.classList.contains('zen-mode')) {
            toggleZenMode();
        }
    });

    // ── Show / Hide Axis Indicator Labels Toggle ───────────────────────────
    document.getElementById('toggleLabelsBtn').addEventListener('click', function () {
        state.showLabels = !state.showLabels;
        var btn = document.getElementById('toggleLabelsBtn');
        var text = document.getElementById('labelStatusText');

        if (state.showLabels) {
            text.innerText = 'Labels: ON';
            btn.classList.remove('bg-gray-800', 'text-gray-300');
            btn.classList.add('bg-blue-600', 'text-white', 'border-blue-500');
        } else {
            text.innerText = 'Labels: OFF';
            btn.classList.remove('bg-blue-600', 'text-white', 'border-blue-500');
            btn.classList.add('bg-gray-800', 'text-gray-300');
        }

        // Reapply all tile data to update label visibility
        Object.keys(rawDataMap).forEach(function (tileId) {
            applyTileData(tileId, rawDataMap[tileId]);
        });
    });

    // ── Global Event Handlers ──────────────────────────────────────────────
    document.getElementById('recenterBtn').addEventListener('click', recenterAroundAtm);
    document.getElementById('refreshDataBtn').addEventListener('click', loadData);

    // Days Back buttons
    document.querySelectorAll('.days-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.days-btn').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            state.daysBack = btn.getAttribute('data-days');
            loadData();
        });
    });

    // Timeframe buttons
    document.querySelectorAll('.tf-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.tf-btn').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            state.timeframe = btn.getAttribute('data-tf');
            loadData();
        });
    });

    // Layout buttons
    document.querySelectorAll('.layout-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetLayout = btn.getAttribute('data-layout');
            var url = new URL(window.location.href);
            url.searchParams.set('layout', targetLayout);
            url.searchParams.set('expiry', state.selectedExpiry);
            url.searchParams.set('timeframe', state.timeframe);
            url.searchParams.set('days_back', state.daysBack);
            url.searchParams.set('chart_height', state.chartHeight);
            window.location.href = url.toString();
        });
    });

    // Expiry Toggle buttons
    document.querySelectorAll('.expiry-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            state.selectedExpiry = btn.getAttribute('data-expiry');
            document.getElementById('allExpiriesSelect').value = state.selectedExpiry;
            document.querySelectorAll('.expiry-btn').forEach(function (b) {
                b.classList.remove('bg-blue-600', 'text-white');
                b.classList.add('text-gray-400');
            });
            btn.classList.add('bg-blue-600', 'text-white');
            btn.classList.remove('text-gray-400');
            loadData();
        });
    });

    document.getElementById('allExpiriesSelect').addEventListener('change', function (e) {
        state.selectedExpiry = e.target.value;
        loadData();
    });

    // Indicator Badge Click Handlers (Index/Fut only)
    document.querySelectorAll('.indicator-badge').forEach(function (badge) {
        badge.addEventListener('click', function () {
            var indKey = badge.getAttribute('data-ind');
            var chk = badge.querySelector('input[type="checkbox"]');
            var newVal = !chk.checked;
            chk.checked = newVal;
            state.indicators[indKey] = newVal;

            if (newVal) badge.classList.add('active');
            else badge.classList.remove('active');

            // Reapply indicator visibility across Index & Fut panes
            Object.keys(rawDataMap).forEach(function (tileId) {
                applyTileData(tileId, rawDataMap[tileId]);
            });
        });
    });

    // Per-tile Strike / Type changes
    document.querySelectorAll('.tile-type-select').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var tileId = parseInt(sel.getAttribute('data-tile-id'));
            var tile = state.tiles.find(function (t) { return t.id === tileId; });
            if (tile) {
                tile.type = sel.value;
                var title = document.getElementById('legend_title_' + tile.id);
                if (title) title.innerText = (tile.strike > 0 ? tile.strike + ' ' : '') + tile.type;
                loadData();
            }
        });
    });

    document.querySelectorAll('.tile-strike-input').forEach(function (inp) {
        inp.addEventListener('change', function () {
            var tileId = parseInt(inp.getAttribute('data-tile-id'));
            var tile = state.tiles.find(function (t) { return t.id === tileId; });
            if (tile) {
                tile.strike = parseFloat(inp.value);
                var title = document.getElementById('legend_title_' + tile.id);
                if (title) title.innerText = tile.strike + ' ' + tile.type;
                loadData();
            }
        });
    });

    // ── Boot ───────────────────────────────────────────────────────────────
    initAllCharts();
    loadData();
});
</script>
@endsection
