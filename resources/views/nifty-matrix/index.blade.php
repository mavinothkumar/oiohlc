@extends('layouts.app')

@section('title', 'NIFTY Multi-Chart Matrix (ATM Centered)')

@push('styles')
    <!-- TradingView Lightweight Charts & Protobuf -->
    <script src="https://cdn.jsdelivr.net/npm/lightweight-charts@4.2.3/dist/lightweight-charts.standalone.production.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/protobufjs@7.2.5/dist/protobuf.min.js"></script>
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
            position: relative;
            z-index: 1000;
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
        .grid-layout-4x4 {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 6px;
        }
        .grid-layout-4x5 {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 6px;
        }
        @media (max-width: 1024px) {
            .grid-layout-3x2, .grid-layout-4x2, .grid-layout-3x3, .grid-layout-4x3, .grid-layout-3x4, .grid-layout-4x4, .grid-layout-4x5 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 640px) {
            .grid-layout-2x2, .grid-layout-3x2, .grid-layout-4x2, .grid-layout-3x3, .grid-layout-4x3, .grid-layout-3x4, .grid-layout-4x4, .grid-layout-4x5 {
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
    <!-- Top Toolbar (Unified Single-Line Compact Layout) -->
    <div class="matrix-toolbar px-2.5 py-1.5 flex items-center justify-between gap-1.5 text-xs whitespace-nowrap relative z-50">
        <!-- 1. Spot, ATM, Recenter, Expiry & Levels -->
        <div class="flex items-center gap-1.5 shrink-0">
            <div class="flex items-center bg-gray-800/90 px-2 py-0.5 rounded border border-gray-700 text-[11px]">
                <span class="font-bold text-white tracking-wider mr-1.5 text-xs">NIFTY</span>
                <span class="text-gray-400 mr-1 text-[10px]">Spot:</span>
                <span id="spotDisplay" class="font-mono font-bold text-emerald-400 mr-1.5 text-xs">{{ number_format($spot, 2) }}</span>
                <span class="text-gray-400 mr-1 text-[10px]">ATM:</span>
                <span id="atmDisplay" class="font-mono font-bold text-blue-400 text-xs">{{ number_format($atm, 0) }}</span>
            </div>

            <button id="recenterBtn" title="Recenter grid around current Spot ATM" class="px-2 py-0.5 bg-blue-600 hover:bg-blue-500 text-white rounded font-medium text-[11px] flex items-center gap-1 transition shadow-sm">
                <span>⚡ ATM</span>
            </button>

            <!-- WebSocket Live Feed Status Badge -->
            <div id="wsStatusBadge" class="flex items-center gap-1.5 px-2 py-0.5 rounded bg-gray-800 border border-gray-700 text-[11px] font-semibold text-gray-300" title="Upstox WebSocket Market Data Stream">
                <span id="wsDot" class="inline-block w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                <span id="wsText">Connecting feed...</span>
            </div>

            <!-- Expiry Selection Dropdown -->
            <div class="flex items-center bg-gray-800 px-1.5 py-0.5 rounded border border-gray-700 text-[11px]">
                <label for="allExpiriesSelect" class="text-gray-400 font-semibold mr-1">Exp:</label>
                <select id="allExpiriesSelect" class="bg-gray-900 text-gray-200 border-0 rounded px-1 py-0 text-[11px] font-semibold focus:ring-1 focus:ring-blue-500">
                    @foreach($expiries as $exp)
                        <option value="{{ $exp->expiry_date }}" {{ $selectedExpiry === $exp->expiry_date ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::parse($exp->expiry_date)->format('d M') }} {{ $exp->is_current ? '(CW)' : ($exp->is_next ? '(NW)' : '') }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- MidPoints & Previous Day Close Info -->
            <div class="flex items-center gap-1 bg-gray-800/80 px-1.5 py-0.5 rounded border border-gray-700 text-[11px]">
                <span class="text-gray-400" title="Previous Day Close">PDC:</span>
                <span class="font-mono font-bold text-white text-[11px]">{{ number_format($pdc, 2) }}</span>
                <span class="text-gray-600">|</span>
                <span class="text-emerald-400 font-semibold" title="Current Week MidPoint">CW:</span>
                <input type="number" step="0.01" id="cwMidInput" value="{{ $cwMid }}" class="w-12 bg-gray-900 text-emerald-300 font-mono font-bold text-center rounded px-1 py-0 border border-gray-700 text-[11px]">
                <span class="text-red-400 font-semibold ml-0.5" title="Next Week MidPoint">NW:</span>
                <input type="number" step="0.01" id="nwMidInput" value="{{ $nwMid }}" class="w-12 bg-gray-900 text-red-300 font-mono font-bold text-center rounded px-1 py-0 border border-gray-700 text-[11px]">
            </div>
        </div>

        <!-- 2. Controls in Dropdowns: Grid, TF, Days, Height, Indicators, Labels, Zen -->
        <div class="flex items-center gap-1.5 shrink-0">
            <!-- Grid Layout Dropdown -->
            <div class="flex items-center bg-gray-800 px-1.5 py-0.5 rounded border border-gray-700 text-[11px]">
                <label for="gridLayoutSelect" class="text-gray-400 font-semibold mr-1">⊞ Grid:</label>
                <select id="gridLayoutSelect" class="bg-gray-900 text-gray-200 border-0 rounded px-1 py-0 text-[11px] font-semibold focus:ring-1 focus:ring-blue-500">
                    <option value="1x1" {{ $layout === '1x1' ? 'selected' : '' }}>1×1 (Focus)</option>
                    <option value="2x2" {{ $layout === '2x2' ? 'selected' : '' }}>2×2 (4 Tiles)</option>
                    <option value="3x2" {{ $layout === '3x2' ? 'selected' : '' }}>3×2 (6 Tiles)</option>
                    <option value="4x2" {{ $layout === '4x2' ? 'selected' : '' }}>4×2 (8 Tiles)</option>
                    <option value="3x3" {{ $layout === '3x3' ? 'selected' : '' }}>3×3 (9 Tiles)</option>
                    <option value="4x3" {{ $layout === '4x3' ? 'selected' : '' }}>4×3 (12 Tiles: Ind/Fut + Opt)</option>
                    <option value="4x4" {{ $layout === '4x4' ? 'selected' : '' }}>4×4 (16 Tiles: Ind/Fut + Opt)</option>
                    <option value="4x5" {{ $layout === '4x5' ? 'selected' : '' }}>4×5 (20 Tiles: Ind/Fut + Opt)</option>
                    <option value="3x4" {{ $layout === '3x4' ? 'selected' : '' }}>3×4 (12 Tiles)</option>
                </select>
            </div>

            <!-- Timeframe Dropdown -->
            <div class="flex items-center bg-gray-800 px-1.5 py-0.5 rounded border border-gray-700 text-[11px]">
                <label for="timeframeSelect" class="text-gray-400 font-semibold mr-1">⏱ TF:</label>
                <select id="timeframeSelect" class="bg-gray-900 text-gray-200 border-0 rounded px-1 py-0 text-[11px] font-semibold focus:ring-1 focus:ring-blue-500">
                    <option value="1m" {{ $timeframe === '1m' ? 'selected' : '' }}>1 min</option>
                    <option value="3m" {{ $timeframe === '3m' ? 'selected' : '' }}>3 min</option>
                    <option value="5m" {{ $timeframe === '5m' ? 'selected' : '' }}>5 min</option>
                    <option value="15m" {{ $timeframe === '15m' ? 'selected' : '' }}>15 min</option>
                </select>
            </div>

            <!-- Days Dropdown -->
            <div class="flex items-center bg-gray-800 px-1.5 py-0.5 rounded border border-gray-700 text-[11px]">
                <label for="daysBackSelect" class="text-gray-400 font-semibold mr-1">📅 Days:</label>
                <select id="daysBackSelect" class="bg-gray-900 text-gray-200 border-0 rounded px-1 py-0 text-[11px] font-semibold focus:ring-1 focus:ring-blue-500">
                    <option value="1" {{ $daysBack === '1' ? 'selected' : '' }}>1D (Today)</option>
                    <option value="2" {{ $daysBack === '2' ? 'selected' : '' }}>2 Days</option>
                    <option value="3" {{ $daysBack === '3' ? 'selected' : '' }}>3 Days</option>
                    <option value="5" {{ $daysBack === '5' ? 'selected' : '' }}>5 Days</option>
                    <option value="all" {{ $daysBack === 'all' ? 'selected' : '' }}>All Days</option>
                </select>
            </div>

            <!-- Height Dropdown -->
            <div class="flex items-center bg-gray-800 px-1.5 py-0.5 rounded border border-gray-700 text-[11px]">
                <label for="chartHeightSelect" class="text-gray-400 font-semibold mr-1">Height:</label>
                <select id="chartHeightSelect" class="bg-gray-900 text-gray-200 border-0 rounded px-1.5 py-0.5 text-[11px] font-semibold focus:ring-1 focus:ring-blue-500">
                    <option value="200" {{ $chartHeight == '200' ? 'selected' : '' }}>200px</option>
                    <option value="300" {{ $chartHeight == '300' ? 'selected' : '' }}>300px</option>
                    <option value="400" {{ $chartHeight == '400' ? 'selected' : '' }}>400px</option>
                    <option value="500" {{ $chartHeight == '500' ? 'selected' : '' }}>500px</option>
                </select>
            </div>

            <!-- Indicators Dropdown Popover -->
            <div class="relative inline-block text-left" id="indicatorDropdownWrap">
                <button type="button" id="indicatorMenuBtn" class="px-2 py-0.5 bg-gray-800 hover:bg-gray-700 text-gray-200 rounded font-semibold border border-gray-700 flex items-center gap-1 transition text-[11px]">
                    <span>📈 Indicators</span>
                    <span id="activeIndCount" class="bg-blue-600 text-white rounded-full px-1.5 py-0 text-[9px] font-bold">5</span>
                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
                <div id="indicatorMenu" class="hidden absolute right-0 mt-1 w-64 rounded-md shadow-2xl bg-[#1a1e29] border border-gray-600 p-2.5 text-[11px] space-y-1.5" style="z-index: 99999;">
                    <div class="text-[10px] uppercase font-bold text-gray-400 pb-1 border-b border-gray-700/60 flex justify-between items-center">
                        <span>Index & Fut Indicators</span>
                        <span class="text-[9px] text-gray-500">(Not for Options)</span>
                    </div>
                    <div class="grid grid-cols-2 gap-1.5 pt-0.5">
                        <label class="flex items-center gap-1.5 cursor-pointer px-1 py-0.5 rounded hover:bg-gray-800 transition" style="color: #06b6d4;">
                            <input type="checkbox" id="chkEma9" checked class="accent-cyan-400 rounded w-3.5 h-3.5">
                            <span class="font-semibold">EMA 9</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer px-1 py-0.5 rounded hover:bg-gray-800 transition" style="color: #c084fc;">
                            <input type="checkbox" id="chkEma21" checked class="accent-purple-400 rounded w-3.5 h-3.5">
                            <span class="font-semibold">EMA 21</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer px-1 py-0.5 rounded hover:bg-gray-800 transition" style="color: #fb923c;">
                            <input type="checkbox" id="chkSma20" checked class="accent-orange-400 rounded w-3.5 h-3.5">
                            <span class="font-semibold">SMA 20</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer px-1 py-0.5 rounded hover:bg-gray-800 transition" style="color: #60a5fa;">
                            <input type="checkbox" id="chkVwma20" checked class="accent-blue-400 rounded w-3.5 h-3.5">
                            <span class="font-semibold">VWMA 20</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer px-1 py-0.5 rounded hover:bg-gray-800 transition" style="color: #facc15;">
                            <input type="checkbox" id="chkVwap" checked class="accent-yellow-400 rounded w-3.5 h-3.5">
                            <span class="font-semibold">VWAP</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer px-1 py-0.5 rounded hover:bg-gray-800 transition" style="color: #f43f5e;">
                            <input type="checkbox" id="chkEma246" class="accent-rose-400 rounded w-3.5 h-3.5">
                            <span class="font-semibold">EMA 246</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer px-1 py-0.5 rounded hover:bg-gray-800 transition" style="color: #d97706;">
                            <input type="checkbox" id="chkSma246" class="accent-amber-500 rounded w-3.5 h-3.5">
                            <span class="font-semibold">SMA 246</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer px-1 py-0.5 rounded hover:bg-gray-800 transition" style="color: #84cc16;">
                            <input type="checkbox" id="chkVwma246" class="accent-lime-400 rounded w-3.5 h-3.5">
                            <span class="font-semibold">VWMA 246</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Show/Hide Indicator Price Labels Toggle -->
            <button id="toggleLabelsBtn" class="px-2 py-0.5 rounded text-[11px] font-semibold border border-gray-700 bg-gray-800 text-gray-300 hover:text-white transition flex items-center gap-1" title="Show or hide price axis labels">
                <span>🏷️</span>
                <span id="labelStatusText">Labels: OFF</span>
            </button>

            <!-- Distraction Free Mode Button -->
            <button id="zenModeBtn" class="px-2 py-0.5 bg-indigo-700 hover:bg-indigo-600 text-white rounded font-bold text-[11px] flex items-center gap-1 transition shadow-sm" title="Toggle Distraction-Free View (D or Escape)">
                <span id="zenIcon">🖥️</span>
                <span id="zenText">Zen</span>
            </button>

            <!-- Refresh Button -->
            <button id="refreshDataBtn" class="px-2 py-0.5 bg-gray-800 hover:bg-gray-700 text-gray-200 rounded font-semibold border border-gray-600 flex items-center gap-1 transition text-[11px]" title="Refresh Data">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
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
    // ── Persistent Browser-Level Settings (localStorage) ─────────────────
    var STORAGE_KEY = 'nifty_matrix_settings_v1';

    function loadSavedSettings() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return {};
            var parsed = JSON.parse(raw);
            return (typeof parsed === 'object' && parsed !== null) ? parsed : {};
        } catch (e) {
            console.warn('[Matrix] Failed to read saved settings:', e);
            return {};
        }
    }

    var savedSettings = loadSavedSettings();

    // ── Application State ──────────────────────────────────────────────────
    var state = {
        symbol: '{{ $symbol }}',
        spot: {{ $spot }},
        atm: {{ $atm }},
        step: {{ $step }},
        currentExpiry: '{{ $currentExpiry }}',
        selectedExpiry: savedSettings.selectedExpiry || '{{ $selectedExpiry }}',
        cwMid: {{ $cwMid }},
        nwMid: {{ $nwMid }},
        pdc: {{ $pdc }},
        layout: savedSettings.layout || '{{ $layout }}',
        timeframe: savedSettings.timeframe || '{{ $timeframe }}',
        daysBack: savedSettings.daysBack || '{{ $daysBack }}',
        chartHeight: savedSettings.chartHeight || '{{ $chartHeight }}',
        showLabels: savedSettings.showLabels !== undefined ? savedSettings.showLabels : false,
        tiles: @json($tiles),
        indicators: Object.assign({
            ema9: true,
            ema21: true,
            ema246: false,
            sma20: true,
            sma246: false,
            vwma20: true,
            vwma246: false,
            vwap: true,
        }, savedSettings.indicators || {}),
        allInstrumentKeys: [],
        instrumentKeyMap: {}, // key -> array of { tileId, type, strike }
        liveCandles: {}       // key -> { time, open, high, low, close }
    };

    function saveSettings() {
        try {
            var toSave = {
                layout: state.layout,
                timeframe: state.timeframe,
                daysBack: state.daysBack,
                chartHeight: state.chartHeight,
                selectedExpiry: state.selectedExpiry,
                showLabels: state.showLabels,
                indicators: state.indicators,
                timestamp: Date.now()
            };
            localStorage.setItem(STORAGE_KEY, JSON.stringify(toSave));
        } catch (e) {
            console.warn('[Matrix] Failed to save settings:', e);
        }
    }

    var charts = {};       // tileId -> LightweightChart instance
    var seriesMap = {};    // tileId -> { ceSeries, peSeries, ema9, ema21, ema246, sma20, sma246, vwma20, vwma246, vwap, lines: [] }
    var rawDataMap = {};   // tileId -> raw data bundle from server
    var ws = null;
    var protobufRoot = null;
    var isConnectingWs = false;

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
                vertLines: { visible: false },
                horzLines: { visible: false },
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
                barSpacing: 9,
                minBarSpacing: 3,
                rightOffset: 0,
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

        // Vertical line series for Previous Day Session Close (light gray)
        var vlineSeries = chart.addHistogramSeries({
            color: 'rgba(156, 163, 175, 0.35)', // subtle light gray
            priceScaleId: 'vlines',
            priceLineVisible: false,
            lastValueVisible: false,
            base: 0,
        });
        chart.priceScale('vlines').applyOptions({
            visible: false,
            scaleMargins: { top: 0, bottom: 0 },
        });

        charts[tile.id] = chart;
        seriesMap[tile.id] = {
            ceSeries: ceSeries,
            peSeries: peSeries,
            vlineSeries: vlineSeries,
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

                var vwapData = param.seriesData.get(vwapSeries);
                if (vwapData && state.indicators.vwap) {
                    var vwapLbl = (tile.type === 'INDEX' || tile.type === 'FUT') ? 'VWAP' : 'Comb VWAP';
                    parts.push(`<span style="color:#facc15;font-weight:bold;">${vwapLbl}:</span> <span style="color:#facc15">${vwapData.value.toFixed(2)}</span>`);
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
                return { id: t.id, type: t.type, strike: t.strike, timeframe: t.timeframe || state.timeframe };
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

            // Reset instrument map
            state.instrumentKeyMap = {};
            var keysToSub = resp.all_instrument_keys || [];

            Object.keys(resp.tiles).forEach(function (tileId) {
                var tileData = resp.tiles[tileId];
                rawDataMap[tileId] = tileData;
                applyTileData(tileId, tileData);

                // Build lookup map for WebSocket updates
                if (tileData.instrument_key) {
                    if (!state.instrumentKeyMap[tileData.instrument_key]) state.instrumentKeyMap[tileData.instrument_key] = [];
                    state.instrumentKeyMap[tileData.instrument_key].push({
                        tileId: tileId,
                        kind: tileData.type // 'INDEX' or 'FUT'
                    });
                }
                if (tileData.ce_instrument_key) {
                    if (!state.instrumentKeyMap[tileData.ce_instrument_key]) state.instrumentKeyMap[tileData.ce_instrument_key] = [];
                    state.instrumentKeyMap[tileData.ce_instrument_key].push({
                        tileId: tileId,
                        kind: 'CE',
                        strike: tileData.strike
                    });
                }
                if (tileData.pe_instrument_key) {
                    if (!state.instrumentKeyMap[tileData.pe_instrument_key]) state.instrumentKeyMap[tileData.pe_instrument_key] = [];
                    state.instrumentKeyMap[tileData.pe_instrument_key].push({
                        tileId: tileId,
                        kind: 'PE',
                        strike: tileData.strike
                    });
                }
            });

            state.allInstrumentKeys = keysToSub;
            subscribeKeys();
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

                // Seed live candle cache from latest historical candle
                var candleKey = tileId + '_' + tile.type;
                state.liveCandles[candleKey] = {
                    time: latest.time,
                    open: latest.open,
                    high: latest.high,
                    low: latest.low,
                    close: latest.close
                };
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
            // 4 Lines: 2 Resistance (Red) + 2 Support (Green) - Thicker & Prominent
            var lines = data.logic_lines || {};

            function addLogicLine(priceVal, color, lineStyle, lineWidth, title) {
                if (!priceVal) return;
                var pl = s.ceSeries.createPriceLine({
                    price: priceVal,
                    color: color,
                    lineWidth: lineWidth || 2.5,
                    lineStyle: lineStyle,
                    axisLabelVisible: state.showLabels,
                    title: state.showLabels ? title : '',
                });
                s.priceLines.push(pl);
            }

            // Resistance lines (Red): R1 = +CW Mid, R2 = +NW Mid
            addLogicLine(lines.r1, COLORS.r1, LightweightCharts.LineStyle.Solid, 2.5, `R1 (+CW Mid): ${lines.r1}`);
            addLogicLine(lines.s1, COLORS.s1, LightweightCharts.LineStyle.Solid, 2.5, `S1 (-CW Mid): ${lines.s1}`);
            addLogicLine(lines.r2, COLORS.r2, LightweightCharts.LineStyle.Dashed, 2, `R2 (+NW Mid): ${lines.r2}`);
            addLogicLine(lines.s2, COLORS.s2, LightweightCharts.LineStyle.Dashed, 2, `S2 (-NW Mid): ${lines.s2}`);

        } else {
            // OPTION STRIKE: Dual CE & PE on the same chart
            // Hide other indicators on option charts
            s.ema9.applyOptions({ visible: false, lastValueVisible: false });
            s.ema21.applyOptions({ visible: false, lastValueVisible: false });
            s.ema246.applyOptions({ visible: false, lastValueVisible: false });
            s.sma20.applyOptions({ visible: false, lastValueVisible: false });
            s.sma246.applyOptions({ visible: false, lastValueVisible: false });
            s.vwma20.applyOptions({ visible: false, lastValueVisible: false });
            s.vwma246.applyOptions({ visible: false, lastValueVisible: false });

            // Option A: Strike Combined (CE + PE) Average VWAP
            var optVwapData = (data.indicators && data.indicators.vwap) ? data.indicators.vwap : [];
            var showOptVwap = state.indicators.vwap && optVwapData.length > 0;
            s.vwap.applyOptions({
                visible: showOptVwap,
                color: COLORS.vwap,
                title: (state.showLabels && showOptVwap) ? 'Comb VWAP' : '',
                lastValueVisible: (state.showLabels && showOptVwap),
            });
            if (showOptVwap) {
                s.vwap.setData(optVwapData);
            }

            var showCe = (tile.type === 'BOTH' || tile.type === 'CE');
            var showPe = (tile.type === 'BOTH' || tile.type === 'PE');

            s.ceSeries.applyOptions({ visible: showCe, title: 'CE' });
            s.peSeries.applyOptions({ visible: showPe, title: 'PE' });

            var ceLatest = null;
            var peLatest = null;

            if (showCe && data.ce_candles && data.ce_candles.length > 0) {
                s.ceSeries.setData(data.ce_candles);
                ceLatest = data.ce_candles[data.ce_candles.length - 1];
                state.liveCandles[tileId + '_CE'] = {
                    time: ceLatest.time,
                    open: ceLatest.open,
                    high: ceLatest.high,
                    low: ceLatest.low,
                    close: ceLatest.close
                };
            }
            if (showPe && data.pe_candles && data.pe_candles.length > 0) {
                s.peSeries.setData(data.pe_candles);
                peLatest = data.pe_candles[data.pe_candles.length - 1];
                state.liveCandles[tileId + '_PE'] = {
                    time: peLatest.time,
                    open: peLatest.open,
                    high: peLatest.high,
                    low: peLatest.low,
                    close: peLatest.close
                };
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

        // ── Previous Day Session Close Vertical Line & Marker ──────────────
        var allCandles = (tile.type === 'INDEX' || tile.type === 'FUT')
            ? (data.candles || [])
            : (data.ce_candles || data.pe_candles || data.candles || []);

        var sessionCloseTimes = [];
        if (Array.isArray(allCandles) && allCandles.length > 1) {
            for (var ci = 0; ci < allCandles.length - 1; ci++) {
                if (allCandles[ci].date && allCandles[ci + 1].date && allCandles[ci].date !== allCandles[ci + 1].date) {
                    sessionCloseTimes.push(allCandles[ci].time);
                }
            }
        }

        if (s.vlineSeries) {
            var vlineData = sessionCloseTimes.map(function (t) {
                return { time: t, value: 100, color: 'rgba(156, 163, 175, 0.35)' };
            });
            s.vlineSeries.setData(vlineData);
        }

        if (s.ceSeries) {
            var closeMarkers = sessionCloseTimes.map(function (t) {
                return {
                    time: t,
                    position: 'aboveBar',
                    color: '#9ca3af',
                    shape: 'arrowDown',
                    text: 'Prev Close',
                    size: 0.8,
                };
            });
            s.ceSeries.setMarkers(closeMarkers);
        }

        // Position time-scale viewport: Center current candle on screen with healthy zoom (like the reference screenshot)
        if (Array.isArray(allCandles) && allCandles.length > 0) {
            var todayStr = allCandles[allCandles.length - 1].date;
            var todayCandles = allCandles.filter(function (c) { return c.date === todayStr; });
            var prevCandles = allCandles.filter(function (c) { return c.date !== todayStr; });

            // Start view from 5-8 candles prior to today's open
            var startCandle = null;
            if (prevCandles.length > 6) {
                startCandle = prevCandles[prevCandles.length - 6];
            } else if (prevCandles.length > 0) {
                startCandle = prevCandles[0];
            } else if (todayCandles.length > 0) {
                startCandle = todayCandles[0];
            }

            var lastCandle = allCandles[allCandles.length - 1];
            var tileTf = tile.timeframe || state.timeframe;
            var intervalSec = getTimeframeSeconds(tileTf);

            if (startCandle && lastCandle) {
                // Number of candles from startCandle to current candle
                var candlesOnLeft = Math.max(8, Math.round((lastCandle.time - startCandle.time) / intervalSec));
                
                // Add symmetric forward blank space on the right (approx 85-100% of left span) so current candle sits right in the center
                var forwardPaddingSec = Math.max(candlesOnLeft * intervalSec, intervalSec * 22);
                
                try {
                    chart.timeScale().setVisibleRange({
                        from: startCandle.time,
                        to: lastCandle.time + forwardPaddingSec,
                    });
                } catch (e) {
                    chart.timeScale().fitContent();
                }
            } else {
                chart.timeScale().fitContent();
            }
        } else {
            chart.timeScale().fitContent();
        }
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
        } else if (state.layout === '4x3' || state.layout === '3x4' || state.layout === '4x4' || state.layout === '4x5') {
            // Keep tiles 0..3 intact (Index 5m, Index 15m, Fut 5m, Fut 15m)
            var optTiles = state.tiles.filter(function (t) { return t.id >= 4; });
            var half = Math.floor(optTiles.length / 2);
            optTiles.forEach(function (tile, idx) {
                var offset = idx - half;
                tile.strike = atm + (offset * step);
                tile.is_center = (offset === 0);
            });
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
        saveSettings();
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
    function updateLabelsButtonUI() {
        var btn = document.getElementById('toggleLabelsBtn');
        var text = document.getElementById('labelStatusText');
        if (!btn || !text) return;
        if (state.showLabels) {
            text.innerText = 'Labels: ON';
            btn.classList.remove('bg-gray-800', 'text-gray-300');
            btn.classList.add('bg-blue-600', 'text-white', 'border-blue-500');
        } else {
            text.innerText = 'Labels: OFF';
            btn.classList.remove('bg-blue-600', 'text-white', 'border-blue-500');
            btn.classList.add('bg-gray-800', 'text-gray-300');
        }
    }

    document.getElementById('toggleLabelsBtn').addEventListener('click', function () {
        state.showLabels = !state.showLabels;
        saveSettings();
        updateLabelsButtonUI();

        // Reapply all tile data to update label visibility
        Object.keys(rawDataMap).forEach(function (tileId) {
            applyTileData(tileId, rawDataMap[tileId]);
        });
    });

    // ── Global Event Handlers ──────────────────────────────────────────────
    document.getElementById('recenterBtn').addEventListener('click', recenterAroundAtm);
    document.getElementById('refreshDataBtn').addEventListener('click', loadData);

    // Grid Layout dropdown
    var gridLayoutSel = document.getElementById('gridLayoutSelect');
    if (gridLayoutSel) {
        gridLayoutSel.addEventListener('change', function (e) {
            var targetLayout = e.target.value;
            state.layout = targetLayout;
            saveSettings();
            var url = new URL(window.location.href);
            url.searchParams.set('layout', targetLayout);
            url.searchParams.set('expiry', state.selectedExpiry);
            url.searchParams.set('timeframe', state.timeframe);
            url.searchParams.set('days_back', state.daysBack);
            url.searchParams.set('chart_height', state.chartHeight);
            window.location.href = url.toString();
        });
    }

    // Timeframe dropdown
    var tfSel = document.getElementById('timeframeSelect');
    if (tfSel) {
        tfSel.addEventListener('change', function (e) {
            state.timeframe = e.target.value;
            saveSettings();
            loadData();
        });
    }

    // Days Back dropdown
    var daysSel = document.getElementById('daysBackSelect');
    if (daysSel) {
        daysSel.addEventListener('change', function (e) {
            state.daysBack = e.target.value;
            saveSettings();
            loadData();
        });
    }

    // Expiry dropdown
    var expSel = document.getElementById('allExpiriesSelect');
    if (expSel) {
        expSel.addEventListener('change', function (e) {
            state.selectedExpiry = e.target.value;
            saveSettings();
            loadData();
        });
    }

    // Indicator Popover Menu toggle & clicks
    var indMenuBtn = document.getElementById('indicatorMenuBtn');
    var indMenu = document.getElementById('indicatorMenu');
    var indWrap = document.getElementById('indicatorDropdownWrap');

    if (indMenuBtn && indMenu) {
        indMenuBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            indMenu.classList.toggle('hidden');
        });

        document.addEventListener('click', function (e) {
            if (indWrap && !indWrap.contains(e.target)) {
                indMenu.classList.add('hidden');
            }
        });
    }

    function updateActiveIndCount() {
        var count = Object.values(state.indicators).filter(Boolean).length;
        var badge = document.getElementById('activeIndCount');
        if (badge) badge.innerText = count;
    }

    ['ema9', 'ema21', 'ema246', 'sma20', 'sma246', 'vwma20', 'vwma246', 'vwap'].forEach(function (k) {
        var cap = k.charAt(0).toUpperCase() + k.slice(1);
        var chk = document.getElementById('chk' + cap);
        if (chk) {
            chk.checked = !!state.indicators[k];
            chk.addEventListener('change', function () {
                state.indicators[k] = chk.checked;
                saveSettings();
                updateActiveIndCount();
                Object.keys(rawDataMap).forEach(function (tileId) {
                    applyTileData(tileId, rawDataMap[tileId]);
                });
            });
        }
    });
    updateActiveIndCount();
    updateLabelsButtonUI();

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

    // ── WebSocket Engine (Upstox Live Stream Feed) ──────────────────────────
    function getTimeframeSeconds(tf) {
        if (tf === '1m') return 60;
        if (tf === '3m') return 180;
        if (tf === '5m') return 300;
        if (tf === '15m') return 900;
        return 300;
    }

    async function initProtobuf() {
        try {
            protobufRoot = await protobuf.load('/MarketDataFeed_v3.proto');
            console.log('[Matrix] MarketDataFeed Protobuf schema loaded successfully.');
        } catch (e) {
            console.warn('[Matrix] Protobuf load error:', e);
        }
    }

    async function connectWebsocket() {
        if (isConnectingWs) return;
        isConnectingWs = true;

        var dot = document.getElementById('wsDot');
        var text = document.getElementById('wsText');

        if (dot && text) {
            dot.className = 'inline-block w-2 h-2 rounded-full bg-amber-500 animate-pulse';
            text.textContent = 'Connecting feed...';
        }

        try {
            var res = await fetch('{{ route('api.nifty.matrix.chart.ws-url') }}');
            var data = await res.json();
            if (!data || !data.data || !data.data.authorizedRedirectUri) {
                throw new Error('Authorized WebSocket redirect URI missing');
            }

            var wsUrl = data.data.authorizedRedirectUri;
            ws = new WebSocket(wsUrl);
            ws.binaryType = 'arraybuffer';

            ws.onopen = function () {
                isConnectingWs = false;
                if (dot && text) {
                    dot.className = 'inline-block w-2 h-2 rounded-full bg-emerald-500 shadow-md shadow-emerald-500/50';
                    text.textContent = 'Live Feed Active';
                }
                console.log('[Matrix] WebSocket connected.');
                subscribeKeys();
            };

            ws.onclose = function () {
                isConnectingWs = false;
                if (dot && text) {
                    dot.className = 'inline-block w-2 h-2 rounded-full bg-red-500';
                    text.textContent = 'Disconnected';
                }
                console.warn('[Matrix] WebSocket disconnected. Reconnecting in 5s...');
                setTimeout(connectWebsocket, 5000);
            };

            ws.onerror = function (e) {
                console.error('[Matrix] WebSocket Error:', e);
            };

            ws.onmessage = function (event) {
                decodeProtobuf(event.data);
            };

        } catch (err) {
            isConnectingWs = false;
            if (dot && text) {
                dot.className = 'inline-block w-2 h-2 rounded-full bg-red-500';
                text.textContent = 'Offline';
            }
            console.warn('[Matrix] WebSocket connect failed:', err.message);
        }
    }

    function subscribeKeys() {
        if (!ws || ws.readyState !== WebSocket.OPEN) return;
        if (!state.allInstrumentKeys || state.allInstrumentKeys.length === 0) return;

        var subMessage = {
            guid: 'nifty_matrix_' + Date.now(),
            method: 'sub',
            data: {
                mode: 'full',
                instrumentKeys: state.allInstrumentKeys
            }
        };

        ws.send(new TextEncoder().encode(JSON.stringify(subMessage)));
        console.log(`[Matrix] Subscribed to ${state.allInstrumentKeys.length} instruments on live stream.`);
    }

    function decodeProtobuf(buffer) {
        if (!protobufRoot) return;

        try {
            var arr = new Uint8Array(buffer);
            if (arr.length > 0 && arr[0] === 123) return; // JSON text message

            var FeedResponse = protobufRoot.lookupType('com.upstox.marketdatafeederv3udapi.rpc.proto.FeedResponse');
            var message = FeedResponse.decode(arr);
            var obj = FeedResponse.toObject(message, { enums: String, bytes: String });

            if (obj && obj.feeds) {
                for (var key in obj.feeds) {
                    var feed = obj.feeds[key];
                    var ltp = null;
                    var ltt = null;
                    if (feed.fullFeed && feed.fullFeed.marketFF && feed.fullFeed.marketFF.ltpc) {
                        ltp = feed.fullFeed.marketFF.ltpc.ltp;
                        ltt = feed.fullFeed.marketFF.ltpc.ltt;
                    } else if (feed.fullFeed && feed.fullFeed.indexFF && feed.fullFeed.indexFF.ltpc) {
                        ltp = feed.fullFeed.indexFF.ltpc.ltp;
                        ltt = feed.fullFeed.indexFF.ltpc.ltt;
                    } else if (feed.ltpc) {
                        ltp = feed.ltpc.ltp;
                        ltt = feed.ltpc.ltt;
                    }

                    if (ltp !== null && ltp > 0) {
                        handleLiveTick(key, ltp, ltt, feed);
                    }
                }
            }
        } catch (e) {
            console.error('[Matrix] Protobuf decode error:', e);
        }
    }

    function handleLiveTick(instrumentKey, ltp, ltt, feed) {
        // 1. Update Global Header Spot & ATM if Spot Index Key
        if (instrumentKey === 'NSE_INDEX|Nifty 50') {
            state.spot = ltp;
            var spotEl = document.getElementById('spotDisplay');
            if (spotEl) spotEl.textContent = ltp.toFixed(2);

            var newAtm = Math.round(ltp / state.step) * state.step;
            var atmEl = document.getElementById('atmDisplay');
            if (atmEl) atmEl.textContent = newAtm.toString();
        }

        // 2. Identify associated tiles and series
        var targets = state.instrumentKeyMap[instrumentKey];
        if (!targets || targets.length === 0) return;

        // Convert tick timestamp (milliseconds) to IST clock as UTC seconds to match database candles:
        var tickMs = (ltt && Number(ltt) > 0) ? Number(ltt) : Date.now();
        var istDate = new Date(tickMs + (5.5 * 3600 * 1000));
        var year = istDate.getUTCFullYear();
        var month = istDate.getUTCMonth();
        var day = istDate.getUTCDate();
        var hour = istDate.getUTCHours();
        var minute = istDate.getUTCMinutes();

        targets.forEach(function (t) {
            var tileId = t.tileId;
            var kind = t.kind; // 'INDEX', 'FUT', 'CE', or 'PE'
            var s = seriesMap[tileId];
            var tile = state.tiles.find(function (item) { return item.id === parseInt(tileId); });
            if (!s || !tile) return;

            // Use tile timeframe if specified (e.g. 5m or 15m), else fallback to global timeframe
            var tileTf = tile.timeframe || state.timeframe;
            var intervalSec = getTimeframeSeconds(tileTf);
            var alignedMin = Math.floor(minute / (intervalSec / 60)) * (intervalSec / 60);
            var bucketTs = Math.floor(Date.UTC(year, month, day, hour, alignedMin, 0) / 1000);

            var targetSeries = null;
            if (kind === 'INDEX' || kind === 'FUT' || kind === 'CE') {
                targetSeries = s.ceSeries;
            } else if (kind === 'PE') {
                targetSeries = s.peSeries;
            }

            if (!targetSeries) return;

            // Form or update active candle for this series
            var candleKey = tileId + '_' + kind;
            var liveCandle = state.liveCandles[candleKey];

            if (!liveCandle || liveCandle.time !== bucketTs) {
                // Initialize new candle bucket with initial tick
                liveCandle = {
                    time: bucketTs,
                    open: ltp,
                    high: ltp,
                    low: ltp,
                    close: ltp
                };
            } else {
                // Update running candle with new high, low, close
                liveCandle.high = Math.max(liveCandle.high, ltp);
                liveCandle.low = Math.min(liveCandle.low, ltp);
                liveCandle.close = ltp;
            }
            state.liveCandles[candleKey] = liveCandle;

            // Push tick to Lightweight Charts
            try {
                targetSeries.update({
                    time: liveCandle.time,
                    open: liveCandle.open,
                    high: liveCandle.high,
                    low: liveCandle.low,
                    close: liveCandle.close
                });
            } catch (err) {
                // Silently skip if chart scale is not ready
            }

            // Update Tile Header LTP Badge
            updateTileLtpHeader(tileId, tile);
        });
    }

    function updateTileLtpHeader(tileId, tile) {
        var ltpEl = document.getElementById('ltp_' + tileId);
        if (!ltpEl) return;

        var isIndexOrFut = (tile.type === 'INDEX' || tile.type === 'FUT');
        if (isIndexOrFut) {
            var k = tileId + '_' + tile.type;
            var c = state.liveCandles[k];
            if (c) {
                var color = c.close >= c.open ? '#34d399' : '#f87171';
                ltpEl.innerHTML = `<span style="color:${color}">₹${c.close.toFixed(2)}</span>`;
            }
        } else {
            var ceCandle = state.liveCandles[tileId + '_CE'];
            var peCandle = state.liveCandles[tileId + '_PE'];
            var badges = [];

            if (tile.type === 'BOTH' || tile.type === 'CE') {
                if (ceCandle) {
                    badges.push(`<span style="color:#089981">CE: ₹${ceCandle.close.toFixed(2)}</span>`);
                }
            }
            if (tile.type === 'BOTH' || tile.type === 'PE') {
                if (peCandle) {
                    badges.push(`<span style="color:#06b6d4">PE: ₹${peCandle.close.toFixed(2)}</span>`);
                }
            }
            if (badges.length > 0) {
                ltpEl.innerHTML = badges.join(' | ');
            }
        }
    }

    // ── Boot ───────────────────────────────────────────────────────────────
    // Restore dropdown element selections from saved settings
    if (savedSettings.timeframe && tfSel) tfSel.value = savedSettings.timeframe;
    if (savedSettings.daysBack && daysSel) daysSel.value = savedSettings.daysBack;
    if (savedSettings.chartHeight) {
        var hSel = document.getElementById('chartHeightSelect');
        if (hSel) hSel.value = savedSettings.chartHeight;
        setChartHeight(savedSettings.chartHeight);
    }
    if (savedSettings.selectedExpiry && expSel) expSel.value = savedSettings.selectedExpiry;

    // Check if user landed on default layout without URL param, but has a saved layout
    var currentUrl = new URL(window.location.href);
    if (!currentUrl.searchParams.has('layout') && savedSettings.layout && savedSettings.layout !== '{{ $layout }}') {
        currentUrl.searchParams.set('layout', savedSettings.layout);
        if (savedSettings.timeframe) currentUrl.searchParams.set('timeframe', savedSettings.timeframe);
        if (savedSettings.daysBack) currentUrl.searchParams.set('days_back', savedSettings.daysBack);
        if (savedSettings.chartHeight) currentUrl.searchParams.set('chart_height', savedSettings.chartHeight);
        if (savedSettings.selectedExpiry) currentUrl.searchParams.set('expiry', savedSettings.selectedExpiry);
        window.location.replace(currentUrl.toString());
        return;
    }

    initAllCharts();
    loadData();
    initProtobuf().then(connectWebsocket);
});
</script>
@endsection
