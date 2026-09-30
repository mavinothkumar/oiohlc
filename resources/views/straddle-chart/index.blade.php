@extends('layouts.app')

@section('title', 'Straddle Chart – Combined Premium & VWAP')

@push('styles')
<style>
    #strike-dropdown-menu {
        max-height: 260px !important;
        overflow-y: auto !important;
        z-index: 9999 !important;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.6), 0 8px 10px -6px rgba(0, 0, 0, 0.6) !important;
    }
    #strike-dropdown-menu::-webkit-scrollbar {
        width: 6px;
    }
    #strike-dropdown-menu::-webkit-scrollbar-track {
        background: #0f172a;
    }
    #strike-dropdown-menu::-webkit-scrollbar-thumb {
        background: #334155;
        border-radius: 3px;
    }
    #strike-dropdown-menu::-webkit-scrollbar-thumb:hover {
        background: #475569;
    }
</style>
@endpush

@section('content')
<div class="w-full px-2 sm:px-4 py-1 text-slate-100 font-sans" id="straddle-app">

    {{-- ════════════════════════ TOP FILTER BAR ════════════════════════ --}}
    <div class="bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 shadow-2xl mb-2">
        <div class="flex flex-wrap items-center justify-between gap-3">
            
            {{-- Left Section: Selectors (Date, Expiry, Symbol & Quick Spot/ATM) --}}
            <div class="flex flex-wrap items-center gap-2.5">
                
                {{-- Date Selector --}}
                <div class="flex items-center gap-1.5 bg-slate-800/90 border border-slate-700/80 rounded-lg px-2.5 py-1">
                    <span class="text-[11px] font-semibold text-slate-400">Date:</span>
                    <select id="filter-date" class="bg-transparent text-xs font-bold text-white focus:outline-none cursor-pointer">
                        @foreach($availableDates as $d)
                            <option value="{{ $d }}" class="bg-slate-800 text-white" {{ $d === $selectedDate ? 'selected' : '' }}>
                                {{ $d }} {{ $d === $today ? '(Today)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Expiry Selector --}}
                <div class="flex items-center gap-1.5 bg-slate-800/90 border border-slate-700/80 rounded-lg px-2.5 py-1">
                    <span class="text-[11px] font-semibold text-slate-400">Expiry:</span>
                    <select id="filter-expiry" class="bg-transparent text-xs font-bold text-amber-400 focus:outline-none cursor-pointer">
                        @foreach($expiries as $exp)
                            <option value="{{ $exp }}" class="bg-slate-800 text-white" {{ $exp === $selectedExpiry ? 'selected' : '' }}>
                                {{ $exp }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Symbol & Spot Display --}}
                <div class="flex items-center gap-2 bg-slate-800/60 border border-slate-700/60 rounded-lg px-2.5 py-1">
                    <span class="text-xs font-black tracking-wide text-indigo-400">{{ $selectedSymbol }}</span>
                    <span class="text-[11px] text-slate-400">Spot: <strong class="text-white">{{ number_format($spot, 2) }}</strong></span>
                    <span class="text-[11px] text-slate-400">ATM: <strong class="text-emerald-400">{{ $atmStrike }}</strong></span>
                </div>

                <div class="h-5 w-px bg-slate-700 hidden sm:block"></div>

                {{-- Searchable Strike Adder & Quick Buttons --}}
                <div class="flex items-center gap-1.5">
                    <div class="relative" id="strike-combobox-wrapper">
                        <div class="relative flex items-center">
                            <span class="absolute left-2.5 text-slate-400 pointer-events-none">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                            <input type="text"
                                   id="strike-search-input"
                                   placeholder="Search strike..."
                                   autocomplete="off"
                                   class="w-36 sm:w-44 bg-slate-800 border border-slate-700 hover:border-indigo-500 focus:border-indigo-500 rounded-lg pl-8 pr-7 py-1 text-xs font-bold text-white placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-indigo-500 transition shadow-inner">
                            <button type="button" id="btn-toggle-dropdown" class="absolute right-1 text-slate-400 hover:text-white p-0.5 focus:outline-none" title="Show all strikes">
                                <svg id="dropdown-chevron" class="w-3.5 h-3.5 transition-transform duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>

                        {{-- Searchable Dropdown List Panel --}}
                        <div id="strike-dropdown-menu"
                             style="max-height: 260px !important; overflow-y: auto !important; z-index: 9999 !important;"
                             class="hidden absolute left-0 top-full mt-1 w-56 sm:w-64 bg-slate-900 border border-slate-700 rounded-xl shadow-2xl divide-y divide-slate-800">
                        </div>
                    </div>

                    <button type="button" id="btn-add-strike" class="bg-indigo-600 hover:bg-indigo-500 active:scale-95 text-white text-xs font-bold px-2.5 py-1 rounded-lg shadow transition flex items-center gap-1">
                        <span>+</span> Add
                    </button>
                    
                    {{-- Quick ATM / Offset shortcuts (-100, -50, ATM, +50, +100) --}}
                    <div class="inline-flex items-center gap-0.5 bg-slate-800/60 border border-slate-700/60 rounded-lg p-0.5">
                        <button type="button" id="btn-quick-minus-100" class="text-slate-300 hover:text-white px-1.5 py-0.5 rounded text-[10px] font-semibold hover:bg-slate-700 transition" title="Add Strike -100">
                            -100
                        </button>
                        <button type="button" id="btn-quick-minus-50" class="text-slate-300 hover:text-white px-1.5 py-0.5 rounded text-[10px] font-semibold hover:bg-slate-700 transition" title="Add Strike -50">
                            -50
                        </button>
                        <button type="button" id="btn-quick-atm" class="text-emerald-400 hover:text-emerald-300 px-2 py-0.5 rounded text-[11px] font-bold hover:bg-slate-700 transition" title="Add ATM Strike">
                            ATM
                        </button>
                        <button type="button" id="btn-quick-plus-50" class="text-slate-300 hover:text-white px-1.5 py-0.5 rounded text-[10px] font-semibold hover:bg-slate-700 transition" title="Add Strike +50">
                            +50
                        </button>
                        <button type="button" id="btn-quick-plus-100" class="text-slate-300 hover:text-white px-1.5 py-0.5 rounded text-[10px] font-semibold hover:bg-slate-700 transition" title="Add Strike +100">
                            +100
                        </button>
                    </div>
                </div>

            </div>

            {{-- Right Section: VWAP toggle, Auto-Refresh & Actions --}}
            <div class="flex items-center gap-3">
                {{-- VWAP Toggle --}}
                <label class="inline-flex items-center gap-1.5 bg-slate-800/90 border border-slate-700 px-2.5 py-1 rounded-lg text-xs font-semibold cursor-pointer select-none transition hover:bg-slate-750">
                    <input type="checkbox" id="toggle-vwap" checked class="rounded bg-slate-900 border-slate-600 text-indigo-500 focus:ring-indigo-400 h-3.5 w-3.5">
                    <span class="text-slate-200">VWAP (Dashed)</span>
                </label>

                {{-- Auto Refresh --}}
                <button type="button" id="btn-refresh" class="inline-flex items-center gap-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 px-2.5 py-1 rounded-lg text-xs font-bold transition">
                    <svg id="refresh-spinner" class="w-3.5 h-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Refresh</span>
                </button>
            </div>

        </div>

        {{-- Active Strikes Row (Interactive Chips with Color Badges) --}}
        <div class="flex flex-wrap items-center gap-2 pt-2.5 mt-2 border-t border-slate-800/80" id="active-strikes-container">
            <span class="text-[11px] font-semibold text-slate-400">Active Strikes:</span>
            <div class="flex flex-wrap items-center gap-2" id="strikes-pills">
                {{-- Injected dynamically --}}
            </div>
            <button type="button" id="btn-clear-strikes" class="text-[10px] text-slate-400 hover:text-rose-400 underline ml-2 hidden">
                Clear All
            </button>
        </div>
    </div>

    {{-- ════════════════════════ BIG FULL-WIDTH & FULL-HEIGHT CHART CANVAS ════════════════════════ --}}
    <div id="chart-card" class="relative w-full bg-slate-950 border border-slate-800/90 rounded-2xl p-2 sm:p-3 shadow-2xl flex flex-col"
         style="height: calc(100vh - 170px); min-height: 720px;">
        
        {{-- Inner Header Bar with Title & Fullscreen --}}
        <div class="flex items-center justify-between px-2 pb-1.5 border-b border-slate-800/60 mb-1 flex-shrink-0">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-300">Combined Straddle Premium (CE+PE)/2</span>
                <span class="text-[10px] text-slate-500 font-mono">1-Min Candles &amp; VWAP</span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" id="btn-fullscreen" class="text-xs text-slate-300 hover:text-white bg-slate-800/80 hover:bg-slate-700 px-2.5 py-1 rounded-lg border border-slate-700/80 transition flex items-center gap-1.5 shadow" title="Toggle Full Screen">
                    <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                    <span id="fullscreen-text">Full Screen</span>
                </button>
            </div>
        </div>

        {{-- Loading Overlay --}}
        <div id="chart-loader" class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm z-30 rounded-2xl flex flex-col items-center justify-center gap-3">
            <div class="animate-spin rounded-full h-10 w-10 border-4 border-indigo-500 border-t-transparent"></div>
            <p class="text-sm font-semibold text-indigo-300 tracking-wide">Loading Straddle Intraday Quotes &amp; VWAP...</p>
        </div>

        {{-- No Data Notice --}}
        <div id="no-data-notice" class="hidden absolute inset-0 bg-slate-950/90 z-20 rounded-2xl flex flex-col items-center justify-center p-6 text-center">
            <div class="w-12 h-12 rounded-full bg-rose-500/10 text-rose-400 flex items-center justify-center mb-3 text-xl">⚠️</div>
            <h3 class="text-base font-bold text-white mb-1">No Combined Data Found</h3>
            <p class="text-xs text-slate-400 max-w-md" id="no-data-msg">No 1-minute OHLC quotes available for the selected strikes and expiry.</p>
        </div>

        {{-- Canvas Container (Flex 1, Takes 100% Remaining Height) --}}
        <div class="relative w-full flex-1" style="min-height: 0; position: relative; height: 100%;">
            <canvas id="straddleChart" style="display: block; width: 100%; height: 100%;"></canvas>
        </div>

    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Initial State from Blade
    const symbol = "{{ $selectedSymbol }}";
    const atmStrike = {{ (int) $atmStrike }};
    const strikeStep = {{ (int) ($strikeStep ?? 50) }};
    const allAvailableStrikes = @json($allStrikes);
    let selectedDate = "{{ $selectedDate }}";
    let selectedExpiry = "{{ $selectedExpiry }}";
    let activeStrikes = @json($defaultStrikes); // array of numbers, e.g. [22700]
    let showVwap = true;

    // Strike Color Palette
    const STRIKE_COLORS = [
        { border: '#10b981', bg: 'rgba(16, 185, 129, 0.12)' }, // Emerald
        { border: '#06b6d4', bg: 'rgba(6, 182, 212, 0.12)' },  // Cyan
        { border: '#8b5cf6', bg: 'rgba(139, 92, 246, 0.12)' }, // Violet
        { border: '#f59e0b', bg: 'rgba(245, 158, 11, 0.12)' }, // Amber
        { border: '#ec4899', bg: 'rgba(236, 72, 153, 0.12)' }, // Pink
        { border: '#3b82f6', bg: 'rgba(59, 130, 246, 0.12)' }, // Blue
        { border: '#f97316', bg: 'rgba(249, 115, 22, 0.12)' }, // Orange
        { border: '#14b8a6', bg: 'rgba(20, 184, 166, 0.12)' }, // Teal
    ];

    // DOM Elements
    const dateSelect = document.getElementById('filter-date');
    const expirySelect = document.getElementById('filter-expiry');
    const strikeSearchWrapper = document.getElementById('strike-combobox-wrapper');
    const strikeSearchInput = document.getElementById('strike-search-input');
    const strikeDropdownMenu = document.getElementById('strike-dropdown-menu');
    const btnToggleDropdown = document.getElementById('btn-toggle-dropdown');
    const dropdownChevron = document.getElementById('dropdown-chevron');
    const btnAddStrike = document.getElementById('btn-add-strike');
    const btnQuickMinus100 = document.getElementById('btn-quick-minus-100');
    const btnQuickMinus50 = document.getElementById('btn-quick-minus-50');
    const btnQuickAtm = document.getElementById('btn-quick-atm');
    const btnQuickPlus50 = document.getElementById('btn-quick-plus-50');
    const btnQuickPlus100 = document.getElementById('btn-quick-plus-100');
    const btnClearStrikes = document.getElementById('btn-clear-strikes');
    const toggleVwap = document.getElementById('toggle-vwap');
    const btnRefresh = document.getElementById('btn-refresh');
    const refreshSpinner = document.getElementById('refresh-spinner');
    const strikesPillsContainer = document.getElementById('strikes-pills');
    const chartLoader = document.getElementById('chart-loader');
    const noDataNotice = document.getElementById('no-data-notice');
    const noDataMsg = document.getElementById('no-data-msg');
    const canvas = document.getElementById('straddleChart');
    const chartCard = document.getElementById('chart-card');
    const btnFullscreen = document.getElementById('btn-fullscreen');
    const fullscreenText = document.getElementById('fullscreen-text');

    let chartInstance = null;
    let latestSummaries = {};

    // Helper: Rounded Rectangle for Canvas
    function drawRoundRect(ctx, x, y, width, height, radius) {
        ctx.beginPath();
        ctx.moveTo(x + radius, y);
        ctx.lineTo(x + width - radius, y);
        ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
        ctx.lineTo(x + width, y + height - radius);
        ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
        ctx.lineTo(x + radius, y + height);
        ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
        ctx.lineTo(x, y + radius);
        ctx.quadraticCurveTo(x, y, x + radius, y);
        ctx.closePath();
    }

    // ════════════════════════ CUSTOM RIGHT-SIDE STRIKE LABELS PLUGIN ════════════════════════
    // Labels the strike price and latest premium directly on the right edge of each line
    const rightSideStrikeLabelsPlugin = {
        id: 'rightSideStrikeLabels',
        afterDatasetsDraw(chart) {
            const { ctx, chartArea } = chart;
            if (!chartArea) return;
            const { right, top, bottom } = chartArea;

            const labelItems = [];

            chart.data.datasets.forEach((dataset, idx) => {
                const meta = chart.getDatasetMeta(idx);
                if (meta.hidden || dataset.isVwap) return; // Only label solid straddle average lines

                const points = meta.data;
                if (!points || points.length === 0) return;

                // Find the latest valid point
                let lastPoint = null;
                let lastVal = null;
                for (let j = points.length - 1; j >= 0; j--) {
                    const pt = points[j];
                    const val = dataset.data[j];
                    if (pt && val !== null && val !== undefined && !isNaN(val) && pt.y !== undefined && !isNaN(pt.y)) {
                        lastPoint = pt;
                        lastVal = val;
                        break;
                    }
                }

                if (lastPoint) {
                    labelItems.push({
                        strike: dataset.strike,
                        color: dataset.borderColor,
                        rawY: lastPoint.y,
                        targetY: lastPoint.y,
                        val: lastVal,
                        label: `${dataset.strike} : ₹${lastVal.toFixed(2)}`
                    });
                }
            });

            if (labelItems.length === 0) return;

            // Sort by targetY to prevent overlapping badges
            labelItems.sort((a, b) => a.targetY - b.targetY);
            const minSpacing = 22; // Height + margin
            for (let i = 1; i < labelItems.length; i++) {
                if (labelItems[i].targetY - labelItems[i - 1].targetY < minSpacing) {
                    labelItems[i].targetY = labelItems[i - 1].targetY + minSpacing;
                }
            }

            // Draw badges
            ctx.save();
            labelItems.forEach(item => {
                ctx.font = 'bold 11px Inter, system-ui, -apple-system, sans-serif';
                const text = item.label;
                const textWidth = ctx.measureText(text).width;
                const padX = 7;
                const pillWidth = textWidth + padX * 2;
                const pillHeight = 20;
                const x = right + 8;
                const y = Math.max(top + 10, Math.min(bottom - 10, item.targetY));

                // Connector line from chart edge to label badge
                ctx.beginPath();
                ctx.strokeStyle = item.color;
                ctx.lineWidth = 1;
                ctx.setLineDash([2, 2]);
                ctx.moveTo(right, item.rawY);
                ctx.lineTo(x, y);
                ctx.stroke();
                ctx.setLineDash([]);

                // Pill background
                ctx.fillStyle = item.color;
                drawRoundRect(ctx, x, y - pillHeight / 2, pillWidth, pillHeight, 4);
                ctx.fill();

                // Text
                ctx.fillStyle = '#ffffff';
                ctx.textAlign = 'left';
                ctx.textBaseline = 'middle';
                ctx.fillText(text, x + padX, y);
            });
            ctx.restore();
        }
    };

    // ════════════════════════ RENDER ACTIVE STRIKE PILLS ════════════════════════
    function renderStrikePills() {
        strikesPillsContainer.innerHTML = '';

        if (activeStrikes.length === 0) {
            strikesPillsContainer.innerHTML = '<span class="text-xs text-amber-400 italic">No strikes selected. Pick a strike above to plot.</span>';
            btnClearStrikes.classList.add('hidden');
            return;
        }

        btnClearStrikes.classList.remove('hidden');

        activeStrikes.forEach((stk, idx) => {
            const color = STRIKE_COLORS[idx % STRIKE_COLORS.length];
            const summary = latestSummaries[stk];

            const pill = document.createElement('div');
            pill.className = 'inline-flex items-center gap-1.5 bg-slate-800/90 border border-slate-700 rounded-lg px-2.5 py-1 text-xs shadow-sm transition hover:border-slate-600';
            
            let priceText = '';
            if (summary && summary.current_avg > 0) {
                const changeSign = summary.change >= 0 ? '+' : '';
                const changeClass = summary.change >= 0 ? 'text-emerald-400' : 'text-rose-400';
                priceText = `<span class="font-bold text-white">₹${summary.current_avg.toFixed(2)}</span> <span class="text-[10px] ${changeClass}">(${changeSign}${summary.change_pct.toFixed(1)}%)</span>`;
            }

            pill.innerHTML = `
                <span class="w-2.5 h-2.5 rounded-full" style="background-color: ${color.border};"></span>
                <span class="font-black text-white">${stk}</span>
                ${priceText}
                <button type="button" class="btn-remove-strike text-slate-400 hover:text-rose-400 transition ml-1 font-bold text-xs" data-strike="${stk}" title="Remove Strike">✕</button>
            `;

            strikesPillsContainer.appendChild(pill);
        });

        // Attach remove events
        document.querySelectorAll('.btn-remove-strike').forEach(btn => {
            btn.addEventListener('click', function () {
                const stkToRemove = parseInt(this.getAttribute('data-strike'), 10);
                activeStrikes = activeStrikes.filter(s => s !== stkToRemove);
                renderStrikePills();
                fetchChartData();
            });
        });

        // Refresh open dropdown if active strikes changed
        if (typeof isDropdownOpen !== 'undefined' && isDropdownOpen) {
            renderDropdownList(strikeSearchInput.value.trim());
        }
    }

    // ════════════════════════ FETCH DATA & UPDATE CHART ════════════════════════
    async function fetchChartData() {
        if (activeStrikes.length === 0) {
            if (chartInstance) {
                chartInstance.destroy();
                chartInstance = null;
            }
            chartLoader.classList.add('hidden');
            noDataNotice.classList.remove('hidden');
            noDataMsg.textContent = "Please add at least one strike to view the Combined Straddle Average chart.";
            return;
        }

        chartLoader.classList.remove('hidden');
        noDataNotice.classList.add('hidden');
        refreshSpinner.classList.add('animate-spin');

        try {
            const url = `{{ route('api.straddle.chart.data') }}?symbol=${encodeURIComponent(symbol)}&date=${encodeURIComponent(selectedDate)}&expiry=${encodeURIComponent(selectedExpiry)}&strikes=${encodeURIComponent(activeStrikes.join(','))}&show_vwap=${showVwap ? 1 : 0}`;
            const response = await axios.get(url);
            const data = response.data;

            if (!data.success || !data.datasets || data.datasets.length === 0) {
                noDataNotice.classList.remove('hidden');
                noDataMsg.textContent = data.message || "No intraday 1-minute quotes found for the selected parameters.";
                if (chartInstance) {
                    chartInstance.destroy();
                    chartInstance = null;
                }
                return;
            }

            latestSummaries = data.summaries || {};
            renderStrikePills();

            renderChart(data.labels, data.datasets);

        } catch (err) {
            console.error("Error loading straddle chart data:", err);
            noDataNotice.classList.remove('hidden');
            noDataMsg.textContent = "An error occurred while fetching chart data. Please try again.";
        } finally {
            chartLoader.classList.add('hidden');
            refreshSpinner.classList.remove('animate-spin');
        }
    }

    // ════════════════════════ CHART.JS INITIALIZATION & UPDATE ════════════════════════
    function renderChart(labels, datasets) {
        if (chartInstance) {
            chartInstance.data.labels = labels;
            chartInstance.data.datasets = datasets;
            chartInstance.update('none'); // Update smoothly without re-animating
            return;
        }

        const ctx = canvas.getContext('2d');

        chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: datasets,
            },
            plugins: [rightSideStrikeLabelsPlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                layout: {
                    padding: {
                        top: 20,
                        bottom: 15,
                        left: 15,
                        right: 140, // Space for right-side strike label badges
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(51, 65, 85, 0.4)',
                            drawTicks: true,
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 11, weight: '500' },
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 14,
                        }
                    },
                    y: {
                        position: 'left',
                        grid: {
                            color: 'rgba(51, 65, 85, 0.4)',
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 11, weight: '600' },
                            padding: 6,
                            callback: function (val) {
                                return '₹' + val.toFixed(1);
                            }
                        },
                        title: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'start',
                        labels: {
                            boxWidth: 14,
                            boxHeight: 2,
                            color: '#e2e8f0',
                            font: { size: 11, weight: 'bold' },
                            usePointStyle: false,
                            padding: 16,
                            filter: function(item, chartData) {
                                // Keep legends clear and concise
                                return true;
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleColor: '#f8fafc',
                        titleFont: { size: 13, weight: 'bold' },
                        bodyColor: '#e2e8f0',
                        bodyFont: { size: 12 },
                        borderColor: '#334155',
                        borderWidth: 1,
                        padding: 10,
                        boxPadding: 4,
                        callbacks: {
                            title: function(items) {
                                return `Time: ${items[0].label}`;
                            },
                            label: function(context) {
                                const dataset = context.dataset;
                                const val = context.parsed.y;
                                if (val === null || val === undefined) return null;
                                const prefix = dataset.isVwap ? 'VWAP' : 'Avg';
                                return `  ${dataset.strike} ${prefix}: ₹${val.toFixed(2)}`;
                            }
                        }
                    }
                }
            }
        });
    }

    // ════════════════════════ SEARCHABLE STRIKE COMBOBOX & EVENT LISTENERS ════════════════════════
    let isDropdownOpen = false;
    let highlightedIndex = -1;
    let currentFilteredStrikes = [];

    function openDropdown() {
        if (isDropdownOpen) return;
        isDropdownOpen = true;
        strikeDropdownMenu.classList.remove('hidden');
        if (dropdownChevron) dropdownChevron.style.transform = 'rotate(180deg)';
        renderDropdownList(strikeSearchInput.value.trim());

        // Auto-scroll to ATM strike if search input is empty
        if (!strikeSearchInput.value.trim()) {
            setTimeout(() => {
                const targetStrike = (activeStrikes.length > 0 && activeStrikes[0]) ? activeStrikes[0] : atmStrike;
                const targetElem = strikeDropdownMenu.querySelector(`[data-strike="${targetStrike}"]`);
                if (targetElem) {
                    targetElem.scrollIntoView({ block: 'center' });
                }
            }, 30);
        }
    }

    function closeDropdown() {
        if (!isDropdownOpen) return;
        isDropdownOpen = false;
        strikeDropdownMenu.classList.add('hidden');
        if (dropdownChevron) dropdownChevron.style.transform = '';
        highlightedIndex = -1;
    }

    function renderDropdownList(query = '') {
        strikeDropdownMenu.innerHTML = '';
        const q = query.toString().trim();

        currentFilteredStrikes = allAvailableStrikes.filter(s => {
            if (!q) return true;
            return String(s).includes(q);
        });

        if (currentFilteredStrikes.length === 0) {
            const emptyEl = document.createElement('div');
            emptyEl.className = 'px-3 py-3 text-center text-xs text-slate-400 italic';
            emptyEl.textContent = `No strikes matching "${q}"`;
            strikeDropdownMenu.appendChild(emptyEl);
            highlightedIndex = -1;
            return;
        }

        currentFilteredStrikes.forEach((stk, idx) => {
            const isAtm = (stk === atmStrike);
            const isAdded = activeStrikes.includes(stk);
            const diff = stk - atmStrike;
            const diffText = isAtm ? 'ATM' : (diff > 0 ? `+${diff}` : `${diff}`);

            const item = document.createElement('div');
            item.className = `strike-option px-3 py-1.5 flex items-center justify-between text-xs cursor-pointer select-none transition ${
                idx === highlightedIndex ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800 text-slate-200'
            } ${isAdded ? 'bg-slate-800/40 text-slate-300' : ''}`;
            item.setAttribute('data-strike', stk);
            item.setAttribute('data-index', idx);

            item.innerHTML = `
                <div class="flex items-center gap-1.5 font-mono">
                    <span class="font-bold ${isAtm ? 'text-emerald-400' : ''}">${stk}</span>
                    ${isAtm 
                        ? '<span class="text-[9px] font-black uppercase bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 px-1 py-0.5 rounded leading-none">ATM</span>' 
                        : `<span class="text-[10px] text-slate-400 font-sans">(${diffText})</span>`
                    }
                </div>
                <div class="flex items-center">
                    ${isAdded 
                        ? '<span class="text-[10px] font-semibold text-emerald-400 flex items-center gap-0.5"><svg class="w-3 h-3 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> Added</span>' 
                        : '<span class="text-[10px] text-indigo-400 hover:text-indigo-300 font-semibold">+ Add</span>'
                    }
                </div>
            `;

            item.addEventListener('click', function (e) {
                e.stopPropagation();
                addStrike(stk);
                closeDropdown();
                strikeSearchInput.value = '';
            });

            item.addEventListener('mouseenter', function () {
                highlightedIndex = idx;
                updateHighlight();
            });

            strikeDropdownMenu.appendChild(item);
        });
    }

    function updateHighlight() {
        const items = strikeDropdownMenu.querySelectorAll('.strike-option');
        items.forEach((item, idx) => {
            if (idx === highlightedIndex) {
                item.classList.add('bg-indigo-600', 'text-white');
                item.classList.remove('text-slate-200', 'hover:bg-slate-800', 'text-slate-300');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('bg-indigo-600', 'text-white');
                item.classList.add('text-slate-200');
            }
        });
    }

    function addStrike(strikeVal) {
        const val = parseInt(strikeVal, 10);
        if (!val || isNaN(val)) return;

        if (!activeStrikes.includes(val)) {
            activeStrikes.push(val);
            activeStrikes.sort((a, b) => a - b);
            renderStrikePills();
            fetchChartData();
        }
    }

    // Toggle dropdown button
    if (btnToggleDropdown) {
        btnToggleDropdown.addEventListener('click', function (e) {
            e.stopPropagation();
            if (isDropdownOpen) {
                closeDropdown();
            } else {
                openDropdown();
                strikeSearchInput.focus();
            }
        });
    }

    // Input focus / click
    strikeSearchInput.addEventListener('focus', function () {
        openDropdown();
    });

    strikeSearchInput.addEventListener('click', function (e) {
        e.stopPropagation();
        openDropdown();
    });

    // Input filter
    strikeSearchInput.addEventListener('input', function () {
        highlightedIndex = -1;
        if (!isDropdownOpen) {
            openDropdown();
        } else {
            renderDropdownList(this.value.trim());
        }
    });

    // Keyboard navigation
    strikeSearchInput.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (!isDropdownOpen) {
                openDropdown();
                return;
            }
            if (currentFilteredStrikes.length > 0) {
                highlightedIndex = (highlightedIndex + 1) % currentFilteredStrikes.length;
                updateHighlight();
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (!isDropdownOpen) {
                openDropdown();
                return;
            }
            if (currentFilteredStrikes.length > 0) {
                highlightedIndex = (highlightedIndex - 1 + currentFilteredStrikes.length) % currentFilteredStrikes.length;
                updateHighlight();
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (highlightedIndex >= 0 && highlightedIndex < currentFilteredStrikes.length) {
                addStrike(currentFilteredStrikes[highlightedIndex]);
                closeDropdown();
                strikeSearchInput.value = '';
            } else {
                const typedVal = parseInt(strikeSearchInput.value.trim(), 10);
                if (typedVal && !isNaN(typedVal)) {
                    addStrike(typedVal);
                    closeDropdown();
                    strikeSearchInput.value = '';
                } else if (currentFilteredStrikes.length > 0) {
                    addStrike(currentFilteredStrikes[0]);
                    closeDropdown();
                    strikeSearchInput.value = '';
                }
            }
        } else if (e.key === 'Escape') {
            closeDropdown();
        }
    });

    // Close on click outside
    document.addEventListener('click', function (e) {
        if (strikeSearchWrapper && !strikeSearchWrapper.contains(e.target)) {
            closeDropdown();
        }
    });

    // Add Button Click
    btnAddStrike.addEventListener('click', function () {
        if (highlightedIndex >= 0 && highlightedIndex < currentFilteredStrikes.length) {
            addStrike(currentFilteredStrikes[highlightedIndex]);
            closeDropdown();
            strikeSearchInput.value = '';
            return;
        }

        const typedVal = parseInt(strikeSearchInput.value.trim(), 10);
        if (typedVal && !isNaN(typedVal)) {
            addStrike(typedVal);
            closeDropdown();
            strikeSearchInput.value = '';
        } else if (currentFilteredStrikes.length > 0) {
            addStrike(currentFilteredStrikes[0]);
            closeDropdown();
            strikeSearchInput.value = '';
        }
    });

    // Quick ATM Button
    if (btnQuickAtm) {
        btnQuickAtm.addEventListener('click', function () {
            addStrike(atmStrike);
        });
    }

    // Quick -50 Button
    if (btnQuickMinus50) {
        btnQuickMinus50.addEventListener('click', function () {
            const base = activeStrikes.length > 0 ? Math.min(...activeStrikes) : atmStrike;
            addStrike(base - 50);
        });
    }

    // Quick +50 Button
    if (btnQuickPlus50) {
        btnQuickPlus50.addEventListener('click', function () {
            const base = activeStrikes.length > 0 ? Math.max(...activeStrikes) : atmStrike;
            addStrike(base + 50);
        });
    }

    // Quick -100 Button
    if (btnQuickMinus100) {
        btnQuickMinus100.addEventListener('click', function () {
            const base = activeStrikes.length > 0 ? Math.min(...activeStrikes) : atmStrike;
            addStrike(base - 100);
        });
    }

    // Quick +100 Button
    if (btnQuickPlus100) {
        btnQuickPlus100.addEventListener('click', function () {
            const base = activeStrikes.length > 0 ? Math.max(...activeStrikes) : atmStrike;
            addStrike(base + 100);
        });
    }

    // Clear All Strikes
    btnClearStrikes.addEventListener('click', function () {
        activeStrikes = [];
        renderStrikePills();
        fetchChartData();
    });

    // Date Change
    dateSelect.addEventListener('change', function () {
        selectedDate = this.value;
        // Reload page to re-fetch valid expiries for date, or re-fetch directly
        window.location.href = `{{ route('straddle.chart') }}?date=${selectedDate}&expiry=${selectedExpiry}&strikes=${activeStrikes.join(',')}`;
    });

    // Expiry Change
    expirySelect.addEventListener('change', function () {
        selectedExpiry = this.value;
        fetchChartData();
    });

    // VWAP Toggle
    toggleVwap.addEventListener('change', function () {
        showVwap = this.checked;
        fetchChartData();
    });

    // Manual Refresh
    btnRefresh.addEventListener('click', function () {
        fetchChartData();
    });

    // Full Screen Toggle
    if (btnFullscreen && chartCard) {
        btnFullscreen.addEventListener('click', function () {
            if (!document.fullscreenElement) {
                chartCard.requestFullscreen().then(() => {
                    fullscreenText.textContent = "Exit Full Screen";
                    chartCard.style.height = "100vh";
                    chartCard.style.borderRadius = "0px";
                    setTimeout(() => { if (chartInstance) chartInstance.resize(); }, 100);
                }).catch(err => console.error("Error entering fullscreen:", err));
            } else {
                document.exitFullscreen().then(() => {
                    fullscreenText.textContent = "Full Screen";
                    chartCard.style.height = "calc(100vh - 170px)";
                    chartCard.style.borderRadius = "1rem";
                    setTimeout(() => { if (chartInstance) chartInstance.resize(); }, 100);
                }).catch(err => console.error("Error exiting fullscreen:", err));
            }
        });

        document.addEventListener('fullscreenchange', function () {
            if (!document.fullscreenElement) {
                fullscreenText.textContent = "Full Screen";
                chartCard.style.height = "calc(100vh - 170px)";
                chartCard.style.borderRadius = "1rem";
                setTimeout(() => { if (chartInstance) chartInstance.resize(); }, 100);
            }
        });
    }

    // Auto-poll every 30 seconds during market hours
    setInterval(() => {
        const now = new Date();
        const hours = now.getHours();
        const mins = now.getMinutes();
        const currentMins = hours * 60 + mins;
        // 09:15 to 15:30 IST is roughly market hours
        if (currentMins >= 9 * 60 + 15 && currentMins <= 15 * 60 + 30) {
            fetchChartData();
        }
    }, 30000);

    // Initial Load
    renderStrikePills();
    fetchChartData();
});
</script>
@endpush
