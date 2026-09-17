@extends('layouts.app')

@section('title', 'Live Strike LTP & Mid-Point Chart')

@section('content')
<div class="w-full px-1 sm:px-3 py-2 space-y-3 text-slate-100 font-sans" id="live-strike-app">

    {{-- ════════════════════════ 1. SLEEK PROFESSIONAL TOP FILTER BAR ════════════════════════ --}}
    <div class="bg-slate-900/95 backdrop-blur border border-slate-800 rounded-xl px-3 py-2 shadow-xl">
        <div class="flex flex-wrap items-center justify-between gap-2.5">
            
            {{-- Left: Filter Controls Group --}}
            <div class="flex flex-wrap items-center gap-2">
                {{-- Symbol Selector --}}
                <div class="flex items-center">
                    <select id="filter-symbol" class="bg-slate-800 hover:bg-slate-750 border border-slate-700 rounded-lg px-2.5 py-1 text-xs text-white font-bold tracking-wide focus:ring-1 focus:ring-emerald-500 focus:outline-none cursor-pointer">
                        <option value="NIFTY" {{ $symbol === 'NIFTY' ? 'selected' : '' }}>NIFTY</option>
                        <option value="BANKNIFTY" {{ $symbol === 'BANKNIFTY' ? 'selected' : '' }}>BANKNIFTY</option>
                    </select>
                </div>

                {{-- Strike Range Selector (Default ±8) --}}
                <div class="flex items-center">
                    <select id="filter-range" class="bg-slate-800 hover:bg-slate-750 border border-slate-700 rounded-lg px-2.5 py-1 text-xs text-slate-200 font-semibold focus:ring-1 focus:ring-emerald-500 focus:outline-none cursor-pointer">
                        <option value="2" {{ $range == 2 ? 'selected' : '' }}>± 2 (5 strikes)</option>
                        <option value="3" {{ $range == 3 ? 'selected' : '' }}>± 3 (7 strikes)</option>
                        <option value="4" {{ $range == 4 ? 'selected' : '' }}>± 4 (9 strikes)</option>
                        <option value="5" {{ $range == 5 ? 'selected' : '' }}>± 5 (11 strikes)</option>
                        <option value="8" {{ $range == 8 ? 'selected' : '' }}>± 8 (17 strikes)</option>
                        <option value="10" {{ $range == 10 ? 'selected' : '' }}>± 10 (21 strikes)</option>
                    </select>
                </div>

                {{-- ATM Center Controller Group --}}
                <div class="inline-flex items-center bg-slate-800/90 border border-slate-700 rounded-lg p-0.5">
                    <span class="text-[11px] font-bold text-slate-400 px-2 select-none">ATM</span>
                    <button type="button" id="btn-atm-minus" class="text-slate-300 hover:text-white px-1.5 py-0.5 rounded text-xs font-bold transition hover:bg-slate-700" title="Minus {{ $strikeStep }}">
                        -{{ $strikeStep }}
                    </button>
                    <input type="number" id="filter-atm" value="{{ $atmStrike }}" step="{{ $strikeStep }}" class="w-16 bg-slate-900 border border-slate-700/80 rounded px-1.5 py-0.5 text-xs text-emerald-400 font-extrabold text-center focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                    <button type="button" id="btn-atm-plus" class="text-slate-300 hover:text-white px-1.5 py-0.5 rounded text-xs font-bold transition hover:bg-slate-700" title="Plus {{ $strikeStep }}">
                        +{{ $strikeStep }}
                    </button>
                    <button type="button" id="btn-reset-atm" class="text-[10px] bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 px-1.5 py-0.5 rounded font-medium ml-1 transition" title="Reset to open ATM ({{ $detectedAtm }})">
                        Reset (<span id="label-detected-atm">{{ $detectedAtm }}</span>)
                    </button>
                </div>

                {{-- Spot & Open Toggles --}}
                <label class="inline-flex items-center gap-1.5 bg-slate-800/90 hover:bg-slate-800 border border-slate-700 px-2 py-1 rounded-lg text-xs font-semibold cursor-pointer select-none transition">
                    <input type="checkbox" id="filter-show-spot" checked class="rounded bg-slate-900 border-slate-600 text-cyan-500 focus:ring-cyan-400 h-3.5 w-3.5">
                    <span class="text-cyan-300 flex items-center gap-1">
                        <span class="h-1.5 w-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                        Spot
                    </span>
                    <span id="label-spot-val" class="hidden">{{ number_format($indexSpot ?: $indexClose, 2) }}</span>
                </label>

                <label class="inline-flex items-center gap-1.5 bg-slate-800/90 hover:bg-slate-800 border border-slate-700 px-2 py-1 rounded-lg text-xs font-semibold cursor-pointer select-none transition">
                    <input type="checkbox" id="filter-show-open" checked class="rounded bg-slate-900 border-slate-600 text-amber-500 focus:ring-amber-400 h-3.5 w-3.5">
                    <span class="text-amber-300 flex items-center gap-1">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                        Open
                    </span>
                    <span id="label-open-val" class="hidden">{{ number_format($indexOpen, 2) }}</span>
                </label>

                {{-- Toggle Show Current Month Future --}}
                <label class="inline-flex items-center gap-1.5 bg-slate-800/90 hover:bg-slate-800 border border-slate-700 px-2 py-1 rounded-lg text-xs font-semibold cursor-pointer select-none transition" title="Toggle Current Month Future Line">
                    <input type="checkbox" id="filter-show-fut" checked class="rounded bg-slate-900 border-slate-600 text-indigo-500 focus:ring-indigo-400 h-3.5 w-3.5">
                    <span class="text-indigo-300 flex items-center gap-1">
                        <span class="h-1.5 w-1.5 rounded-full bg-indigo-400 animate-pulse"></span>
                        Fut
                    </span>
                    <span id="label-fut-val" class="hidden">{{ number_format($futurePrice, 2) }}</span>
                </label>

                {{-- Update Button --}}
                <button type="button" id="btn-apply-filters" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-3 py-1 rounded-lg text-xs transition shadow flex items-center gap-1.5">
                    <svg id="apply-spinner" class="animate-spin h-3.5 w-3.5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>Update</span>
                </button>
            </div>

            {{-- Right: Live Summary Chips & Live Feed Status --}}
            <div class="flex items-center gap-1.5 sm:gap-2 text-xs">
                {{-- Live Spot, Open, Fut & Difference Chips --}}
                <span class="inline-flex items-center gap-1 bg-cyan-500/10 border border-cyan-500/30 text-cyan-300 px-2 py-0.5 rounded-md font-medium text-[11px]">
                    Spot: <strong class="font-mono text-cyan-300" id="chip-index-spot">₹{{ number_format($indexSpot ?: $indexClose, 2) }}</strong>
                </span>
                <span class="inline-flex items-center gap-1 bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 px-2 py-0.5 rounded-md font-medium text-[11px]">
                    Fut: <strong class="font-mono text-indigo-300" id="chip-index-fut">₹{{ number_format($futurePrice, 2) }}</strong>
                </span>
                <span class="inline-flex items-center gap-1 bg-amber-500/10 border border-amber-500/30 text-amber-300 px-2 py-0.5 rounded-md font-medium text-[11px]">
                    Open: <strong class="font-mono text-amber-400" id="chip-index-open">{{ number_format($indexOpen, 2) }}</strong>
                </span>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md font-bold text-[11px] border" id="chip-index-diff">
                    Diff: <span class="font-mono" id="chip-diff-val">--</span>
                </span>
                <span class="hidden xl:inline-flex items-center gap-1 bg-blue-500/10 border border-blue-500/30 text-blue-300 px-2 py-0.5 rounded-md font-medium text-[11px]">
                    Curr: <strong class="font-mono text-blue-400" id="chip-curr-mid">{{ number_format($currentMidPoint, 2) }}</strong>
                </span>
                <span class="hidden xl:inline-flex items-center gap-1 bg-purple-500/10 border border-purple-500/30 text-purple-300 px-2 py-0.5 rounded-md font-medium text-[11px]">
                    Next: <strong class="font-mono text-purple-400" id="chip-next-mid">{{ number_format($nextMidPoint, 2) }}</strong>
                </span>

                {{-- Live Feed Status Indicator --}}
                <div class="flex items-center gap-1.5 bg-slate-800 border border-slate-700 px-2 py-1 rounded-lg text-xs font-medium">
                    <span id="ws-dot" class="inline-block h-2 w-2 rounded-full bg-red-500"></span>
                    <span id="ws-status-text" class="text-slate-300 text-[11px]">Connecting…</span>
                    <span class="text-slate-600">|</span>
                    <span class="text-slate-400 font-mono text-[11px]" id="tick-counter">0 ticks</span>
                </div>

                {{-- Expand / Collapse Info Drawer Toggle --}}
                <button type="button" id="btn-toggle-stats" class="bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 px-2.5 py-1 rounded-lg text-xs font-semibold transition flex items-center gap-1" title="Toggle Detailed Stats">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    <span>Stats</span>
                </button>
            </div>
        </div>

        {{-- ════════════════════════ COLLAPSIBLE DETAILS DRAWER (Hidden by Default) ════════════════════════ --}}
        <div id="stats-drawer" class="hidden mt-3 pt-3 border-t border-slate-800/80 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            {{-- Current Week Mid-Point --}}
            <div class="bg-slate-950/60 border border-blue-500/30 rounded-xl p-3 shadow">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-semibold text-blue-300 uppercase">Current Week Mid Point</span>
                    <span class="text-[10px] font-bold bg-blue-500/20 text-blue-300 px-1.5 py-0.5 rounded border border-blue-500/30" id="card-curr-expiry">
                        {{ $currentExpiry ? \Carbon\Carbon::parse($currentExpiry)->format('d M') : 'N/A' }}
                    </span>
                </div>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-xl font-extrabold text-blue-400 font-mono" id="card-curr-midpoint">
                        {{ number_format($currentMidPoint, 2) }}
                    </span>
                    <span class="text-[11px] text-slate-400">pts</span>
                </div>
                <p class="text-[10px] text-slate-500 mt-0.5">daily_trend current expiry mid_point</p>
            </div>

            {{-- Next Week Mid-Point --}}
            <div class="bg-slate-950/60 border border-purple-500/30 rounded-xl p-3 shadow">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-semibold text-purple-300 uppercase">Next Week Mid Point</span>
                    <span class="text-[10px] font-bold bg-purple-500/20 text-purple-300 px-1.5 py-0.5 rounded border border-purple-500/30" id="card-next-expiry">
                        {{ $nextExpiry ? \Carbon\Carbon::parse($nextExpiry)->format('d M') : 'N/A' }}
                    </span>
                </div>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-xl font-extrabold text-purple-400 font-mono" id="card-next-midpoint">
                        {{ number_format($nextMidPoint, 2) }}
                    </span>
                    <span class="text-[11px] text-slate-400">pts</span>
                </div>
                <p class="text-[10px] text-slate-500 mt-0.5">daily_trend next expiry mid_point</p>
            </div>

            {{-- Center ATM Strike, Spot & Open Diff --}}
            <div class="bg-slate-950/60 border border-emerald-500/30 rounded-xl p-3 shadow">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-semibold text-emerald-300 uppercase">Center ATM & Spot</span>
                    <span class="text-[10px] font-bold bg-emerald-500/20 text-emerald-300 px-1.5 py-0.5 rounded border border-emerald-500/30">
                        Center
                    </span>
                </div>
                <div class="mt-1 flex items-baseline justify-between">
                    <span class="text-xl font-extrabold text-emerald-400 font-mono" id="card-atm-strike">
                        {{ $atmStrike }}
                    </span>
                    <div class="text-right">
                        <span class="text-xs text-cyan-300 font-mono font-bold" id="card-index-spot">Spot: {{ number_format($indexSpot ?: $indexClose, 2) }}</span>
                        <span class="block text-[10px] text-indigo-400 font-mono" id="card-index-fut">Fut: {{ number_format($futurePrice, 2) }}</span>
                        <span class="block text-[10px] text-amber-400 font-mono">Open: {{ number_format($indexOpen, 2) }}</span>
                        <span class="block text-[10px] font-mono font-bold" id="card-diff-val">--</span>
                    </div>
                </div>
                <p class="text-[10px] text-slate-500 mt-0.5">
                    Open ATM: <span class="font-semibold text-slate-300" id="card-detected-atm">{{ $detectedAtm }}</span>
                </p>
            </div>

            {{-- Monitored Strikes Count --}}
            <div class="bg-slate-950/60 border border-slate-700/80 rounded-xl p-3 shadow">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-semibold text-slate-300 uppercase">Monitored Strikes</span>
                    <span class="text-[10px] font-bold bg-slate-800 text-slate-300 px-1.5 py-0.5 rounded" id="card-range-badge">
                        ±{{ $range }} Range
                    </span>
                </div>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-xl font-extrabold text-white font-mono" id="card-strikes-count">
                        {{ count($strikesData) }}
                    </span>
                    <span class="text-[11px] text-slate-400">Total</span>
                </div>
                <p class="text-[10px] text-slate-500 mt-0.5" id="card-strike-span">
                    {{ $strikesData[0]['strike'] ?? '-' }} to {{ $strikesData[count($strikesData)-1]['strike'] ?? '-' }}
                </p>
            </div>
        </div>
    </div>

    {{-- ════════════════════════ 2. FULL-PAGE-WIDTH LARGE CHART (TAKES FULL SCREEN ON LOAD) ════════════════════════ --}}
    <div class="bg-slate-900/95 backdrop-blur border border-slate-800 rounded-2xl p-3 sm:p-4 shadow-2xl space-y-2">
        {{-- Chart Header with Legends --}}
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800 pb-2">
            <div class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-emerald-400 animate-ping"></span>
                <span class="text-xs sm:text-sm font-bold text-slate-200">
                    Strike Curve vs Current (<span id="legend-curr-mid" class="text-blue-400 font-mono">{{ number_format($currentMidPoint, 2) }}</span>) & Next Week (<span id="legend-next-mid" class="text-purple-400 font-mono">{{ number_format($nextMidPoint, 2) }}</span>) Mid-Point
                </span>
            </div>

            <div class="flex flex-wrap items-center gap-3 text-xs font-medium">
                <div class="flex items-center gap-1.5">
                    <span class="w-3.5 h-1 bg-blue-500 rounded"></span>
                    <span class="text-slate-300">Curr Mid</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3.5 h-1 bg-purple-500 rounded"></span>
                    <span class="text-slate-300">Next Mid</span>
                </div>
                <div class="flex items-center gap-1.5" id="legend-spot-wrapper">
                    <span class="w-3 h-0.5 border-t-2 border-dashed border-cyan-400"></span>
                    <span class="text-cyan-300 font-semibold">Spot: <span id="legend-spot-val" class="font-mono">{{ number_format($indexSpot ?: $indexClose, 2) }}</span></span>
                </div>
                <div class="flex items-center gap-1.5" id="legend-fut-wrapper">
                    <span class="w-3 h-0.5 border-t-2 border-dashed border-indigo-400"></span>
                    <span class="text-indigo-300 font-semibold">Fut: <span id="legend-fut-val" class="font-mono">{{ number_format($futurePrice, 2) }}</span></span>
                </div>
                <div class="flex items-center gap-1.5" id="legend-open-wrapper">
                    <span class="w-3 h-0.5 border-t-2 border-dashed border-amber-400"></span>
                    <span class="text-amber-400 font-semibold">Open: <span id="legend-open-val" class="font-mono">{{ number_format($indexOpen, 2) }}</span></span>
                </div>
                <div class="flex items-center gap-1.5" id="legend-diff-wrapper">
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold border" id="legend-diff-badge">
                        Δ Diff: <span id="legend-diff-val">--</span>
                    </span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                    <span class="text-emerald-400 font-semibold">CE Live</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                    <span class="text-rose-400 font-semibold">PE Live</span>
                </div>
            </div>
        </div>

        {{-- Expanded Large Chart Canvas (Prominent & Spacious) --}}
        <div class="relative w-full h-[640px] sm:h-[680px] lg:h-[720px]">
            <canvas id="liveStrikeChart" class="w-full h-full"></canvas>
        </div>
    </div>

    {{-- ════════════════════════ 3. LIVE STRIKE CAPSULES SECTION (LOCATED BELOW CHART, SCROLL TO VIEW) ════════════════════════ --}}
    <div class="bg-slate-900/90 backdrop-blur border border-slate-800 rounded-2xl p-4 shadow-xl space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800 pb-2.5">
            <div class="flex items-center gap-2">
                <span class="text-sm font-bold text-slate-200">Live Strike Capsules Matrix</span>
                <span class="text-xs text-slate-400">(Green Dot CE / Red Dot PE in capsule pills)</span>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 font-semibold text-[11px]">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> CE Live
                </span>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-rose-500/10 border border-rose-500/30 text-rose-400 font-semibold text-[11px]">
                    <span class="h-1.5 w-1.5 rounded-full bg-rose-400"></span> PE Live
                </span>
            </div>
        </div>

        {{-- Multi-Line Wrapped Container (No horizontal scroll, wraps cleanly across rows) --}}
        <div id="capsules-wrap-grid" class="w-full flex flex-wrap items-stretch justify-center gap-2">
            {{-- Populated dynamically via renderCapsules() --}}
        </div>
    </div>

</div>

{{-- ════════════════════════ SCRIPTS: CHART.JS, PROTOBUF, AXIOS ════════════════════════ --}}
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/protobufjs@7.2.5/dist/protobuf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
(function () {
    // Initial payload hydrated from server
    let state = {
        symbol: @json($symbol),
        workingDate: @json($workingDate),
        currentExpiry: @json($currentExpiry),
        nextExpiry: @json($nextExpiry),
        currentMidPoint: {{ (float) $currentMidPoint }},
        nextMidPoint: {{ (float) $nextMidPoint }},
        detectedAtm: {{ (int) $detectedAtm }},
        atmStrike: {{ (int) $atmStrike }},
        indexOpen: {{ (float) $indexOpen }},
        indexSpot: {{ (float) $indexSpot }},
        indexClose: {{ (float) $indexClose }},
        indexKey: @json($indexKey),
        futurePrice: {{ (float) ($futurePrice ?? 0) }},
        futureKey: @json($futureKey ?? null),
        futureSymbol: @json($futureSymbol ?? ''),
        range: {{ (int) $range }},
        strikeStep: {{ (int) $strikeStep }},
        strikesData: @json($strikesData),
        allInstrumentKeys: @json($allInstrumentKeys),
    };

    let livePrices = {};
    let openPrice = state.indexOpen || 0;
    let liveSpotPrice = state.indexSpot || state.indexClose || state.indexOpen || 0;
    let liveFuturePrice = state.futurePrice || 0;
    let showSpotMarker = true;
    let showOpenMarker = true;
    let showFutMarker = true;

    let ws = null;
    let protobufRoot = null;
    let chartInstance = null;
    let tickCount = 0;
    let isConnecting = false;

    // Initialize initial prices from fallback
    state.strikesData.forEach(item => {
        if (item.ce.instrument_key && item.ce.price > 0) {
            livePrices[item.ce.instrument_key] = item.ce.price;
        }
        if (item.pe.instrument_key && item.pe.price > 0) {
            livePrices[item.pe.instrument_key] = item.pe.price;
        }
    });

    // Helper to calculate pixel X position for any strike/index value along X axis
    function getPixelXForValue(val, strikeLabels, xScale) {
        if (!val || strikeLabels.length === 0) return null;
        const firstStrike = strikeLabels[0];
        const lastStrike  = strikeLabels[strikeLabels.length - 1];

        if (val < firstStrike - 150 || val > lastStrike + 150) return null;

        for (let i = 0; i < strikeLabels.length - 1; i++) {
            const s1 = strikeLabels[i];
            const s2 = strikeLabels[i + 1];
            if (val >= s1 && val <= s2) {
                const x1 = xScale.getPixelForValue(i);
                const x2 = xScale.getPixelForValue(i + 1);
                const ratio = (val - s1) / (s2 - s1);
                return x1 + (x2 - x1) * ratio;
            }
        }

        if (val < firstStrike) return xScale.getPixelForValue(0);
        return xScale.getPixelForValue(strikeLabels.length - 1);
    }

    // Helper to format Difference string & class
    function formatDiff(spot, open) {
        if (!open || open === 0 || !spot || spot === 0) {
            return { text: '--', shortText: '--', isPos: true, diff: 0, pct: 0 };
        }
        const diff = spot - open;
        const pct  = (diff / open) * 100;
        const isPos = diff >= 0;
        const sign = isPos ? '+' : '';
        return {
            text: `${sign}${diff.toFixed(2)} pts (${sign}${pct.toFixed(2)}%)`,
            shortText: `${sign}${diff.toFixed(2)} (${sign}${pct.toFixed(2)}%)`,
            isPos: isPos,
            diff: diff,
            pct: pct
        };
    }

    // ──────────────────────────────────────────────
    // 1. IN-CHART CAPSULES, SPOT, FUT, OPEN, MID-POINTS & DIFFERENCE MARKER PLUGIN
    // ──────────────────────────────────────────────
    const chartCapsuleAndReferencePlugin = {
        id: 'chartCapsuleAndReferencePlugin',
        afterDatasetsDraw(chart) {
            const { ctx, scales: { x, y } } = chart;
            const strikeLabels = chart.data.labels;
            const ceMeta = chart.getDatasetMeta(2);
            const peMeta = chart.getDatasetMeta(3);
            const topY = y.top;
            const bottomY = y.bottom;

            ctx.save();

            let spotPixelX = null;
            let futPixelX  = null;
            let openPixelX = null;

            if (showSpotMarker && liveSpotPrice > 0) {
                spotPixelX = getPixelXForValue(liveSpotPrice, strikeLabels, x);
            }
            if (showFutMarker && liveFuturePrice > 0) {
                futPixelX = getPixelXForValue(liveFuturePrice, strikeLabels, x);
            }
            if (showOpenMarker && openPrice > 0) {
                openPixelX = getPixelXForValue(openPrice, strikeLabels, x);
            }

            // A. SHADED RANGE ZONE & CONNECTING BRIDGE FOR DIFFERENCE (If both Spot and Open are shown)
            if (spotPixelX !== null && openPixelX !== null && openPrice > 0 && liveSpotPrice > 0) {
                const minX = Math.min(spotPixelX, openPixelX);
                const maxX = Math.max(spotPixelX, openPixelX);
                const diffObj = formatDiff(liveSpotPrice, openPrice);

                // 1. Subtle shaded vertical zone between Open and Spot lines
                ctx.fillStyle = diffObj.isPos ? 'rgba(16, 185, 129, 0.08)' : 'rgba(244, 63, 94, 0.08)';
                ctx.fillRect(minX, topY, maxX - minX, bottomY - topY);

                // 2. Connecting Bridge Line between the two markers
                const bridgeY = topY + 44;
                ctx.save();
                ctx.strokeStyle = diffObj.isPos ? '#10b981' : '#f43f5e';
                ctx.lineWidth = 1.5;
                ctx.setLineDash([3, 2]);
                ctx.beginPath();
                ctx.moveTo(minX, bridgeY);
                ctx.lineTo(maxX, bridgeY);
                ctx.stroke();

                // End ticks on bridge line
                ctx.setLineDash([]);
                ctx.beginPath();
                ctx.moveTo(minX, bridgeY - 4);
                ctx.lineTo(minX, bridgeY + 4);
                ctx.moveTo(maxX, bridgeY - 4);
                ctx.lineTo(maxX, bridgeY + 4);
                ctx.stroke();
                ctx.restore();

                // 3. Difference Badge centered along the bridge
                const midX = (minX + maxX) / 2;
                ctx.font = 'bold 10px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';

                const diffBadgeText = `Δ Diff: ${diffObj.text}`;
                const diffBadgeWidth = ctx.measureText(diffBadgeText).width + 16;
                const diffBadgeHeight = 19;
                const diffBadgeX = midX - (diffBadgeWidth / 2);

                // Pill background
                ctx.fillStyle = diffObj.isPos ? 'rgba(6, 78, 59, 0.95)' : 'rgba(136, 19, 55, 0.95)';
                ctx.strokeStyle = diffObj.isPos ? '#10b981' : '#f43f5e';
                ctx.lineWidth = 1.2;
                ctx.beginPath();
                ctx.roundRect(diffBadgeX, bridgeY - (diffBadgeHeight / 2), diffBadgeWidth, diffBadgeHeight, 6);
                ctx.fill();
                ctx.stroke();

                // Pill text
                ctx.fillStyle = diffObj.isPos ? '#ecfdf5' : '#fff1f2';
                ctx.fillText(diffBadgeText, midX, bridgeY);
            }

            // B. DRAW CURRENT INDEX SPOT VERTICAL REFERENCE LINE & 'Spot' BADGE (Cyan)
            if (spotPixelX !== null) {
                // Draw vertical dashed line for Spot
                ctx.save();
                ctx.strokeStyle = '#06b6d4'; // Cyan-500
                ctx.lineWidth = 2.2;
                ctx.setLineDash([5, 3]);
                ctx.beginPath();
                ctx.moveTo(spotPixelX, topY);
                ctx.lineTo(spotPixelX, bottomY);
                ctx.stroke();
                ctx.restore();

                // Draw 'Spot' Badge at top
                ctx.font = 'bold 11px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                const spotText = `⚡ Spot: ${liveSpotPrice.toFixed(2)}`;
                const badgeWidth = ctx.measureText(spotText).width + 16;
                const badgeHeight = 22;
                const badgeX = spotPixelX - (badgeWidth / 2);
                const badgeY = topY + 12;

                // Badge background
                ctx.fillStyle = 'rgba(8, 145, 178, 0.95)';
                ctx.strokeStyle = '#cffafe';
                ctx.lineWidth = 1.2;
                ctx.beginPath();
                ctx.roundRect(badgeX, badgeY - (badgeHeight / 2), badgeWidth, badgeHeight, 6);
                ctx.fill();
                ctx.stroke();

                // Badge text
                ctx.fillStyle = '#ffffff';
                ctx.fillText(spotText, spotPixelX, badgeY);
            }

            // C. DRAW CURRENT MONTH FUTURE VERTICAL REFERENCE LINE & 'Fut' BADGE (Indigo)
            if (futPixelX !== null) {
                // Draw vertical dashed line for Fut
                ctx.save();
                ctx.strokeStyle = '#818cf8'; // Indigo-400
                ctx.lineWidth = 2.2;
                ctx.setLineDash([4, 3]);
                ctx.beginPath();
                ctx.moveTo(futPixelX, topY);
                ctx.lineTo(futPixelX, bottomY);
                ctx.stroke();
                ctx.restore();

                // Stagger badge height if close to Spot
                let futBadgeY = topY + 12;
                if (spotPixelX !== null && Math.abs(futPixelX - spotPixelX) < 100) {
                    futBadgeY = topY + 34;
                }

                // Draw 'Fut' Badge
                ctx.font = 'bold 11px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                const futText = `🎯 Fut: ${liveFuturePrice.toFixed(2)}`;
                const badgeWidth = ctx.measureText(futText).width + 16;
                const badgeHeight = 22;
                const badgeX = futPixelX - (badgeWidth / 2);

                // Badge background
                ctx.fillStyle = 'rgba(67, 56, 202, 0.95)'; // Indigo-700
                ctx.strokeStyle = '#c7d2fe';
                ctx.lineWidth = 1.2;
                ctx.beginPath();
                ctx.roundRect(badgeX, futBadgeY - (badgeHeight / 2), badgeWidth, badgeHeight, 6);
                ctx.fill();
                ctx.stroke();

                // Badge text
                ctx.fillStyle = '#ffffff';
                ctx.fillText(futText, futPixelX, futBadgeY);
            }

            // D. DRAW INDEX OPEN VERTICAL REFERENCE LINE & 'Open' BADGE (Amber)
            if (openPixelX !== null) {
                // Draw vertical dashed line for Open
                ctx.save();
                ctx.strokeStyle = '#f59e0b'; // Amber-500
                ctx.lineWidth = 2.2;
                ctx.setLineDash([6, 4]);
                ctx.beginPath();
                ctx.moveTo(openPixelX, topY);
                ctx.lineTo(openPixelX, bottomY);
                ctx.stroke();
                ctx.restore();

                // Stagger badge height if close to Spot or Fut
                let openBadgeY = topY + 12;
                const closeToSpot = (spotPixelX !== null && Math.abs(openPixelX - spotPixelX) < 100);
                const closeToFut  = (futPixelX !== null && Math.abs(openPixelX - futPixelX) < 100);
                if (closeToSpot && closeToFut) {
                    openBadgeY = topY + 56;
                } else if (closeToSpot || closeToFut) {
                    openBadgeY = (closeToSpot && futPixelX !== null && Math.abs(futPixelX - spotPixelX) < 100) ? topY + 56 : topY + 34;
                }

                // Draw 'Open' Badge at top
                ctx.font = 'bold 11px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                const openText = `📍 Open: ${openPrice.toFixed(2)}`;
                const badgeWidth = ctx.measureText(openText).width + 16;
                const badgeHeight = 22;
                const badgeX = openPixelX - (badgeWidth / 2);

                // Badge background
                ctx.fillStyle = 'rgba(217, 119, 6, 0.95)';
                ctx.strokeStyle = '#fef3c7';
                ctx.lineWidth = 1.2;
                ctx.beginPath();
                ctx.roundRect(badgeX, openBadgeY - (badgeHeight / 2), badgeWidth, badgeHeight, 6);
                ctx.fill();
                ctx.stroke();

                // Badge text
                ctx.fillStyle = '#ffffff';
                ctx.fillText(openText, openPixelX, openBadgeY);
            }

            // E. DRAW CURRENT & NEXT WEEK MID-POINT VALUE BADGES ON HORIZONTAL LINES
            const currMidVal = state.currentMidPoint;
            const nextMidVal = state.nextMidPoint;

            if (currMidVal > 0) {
                const currMidY = y.getPixelForValue(currMidVal);
                if (currMidY >= topY && currMidY <= bottomY) {
                    ctx.font = 'bold 10px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                    ctx.textAlign = 'right';
                    ctx.textBaseline = 'middle';
                    const text = `Curr Mid: ₹${parseFloat(currMidVal).toFixed(2)}`;
                    const textWidth = ctx.measureText(text).width;
                    const badgeW = textWidth + 14;
                    const badgeH = 18;
                    const badgeX = x.right - 8;
                    const badgeY = currMidY;

                    ctx.fillStyle = 'rgba(30, 58, 138, 0.95)'; // Blue-900
                    ctx.strokeStyle = '#3b82f6'; // Blue-500
                    ctx.lineWidth = 1.2;
                    ctx.beginPath();
                    ctx.roundRect(badgeX - badgeW, badgeY - (badgeH / 2), badgeW, badgeH, 4);
                    ctx.fill();
                    ctx.stroke();

                    ctx.fillStyle = '#93c5fd'; // Blue-200
                    ctx.fillText(text, badgeX - 7, badgeY);
                }
            }

            if (nextMidVal > 0) {
                let nextMidY = y.getPixelForValue(nextMidVal);
                if (nextMidY >= topY && nextMidY <= bottomY) {
                    ctx.font = 'bold 10px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                    ctx.textAlign = 'right';
                    ctx.textBaseline = 'middle';
                    const text = `Next Mid: ₹${parseFloat(nextMidVal).toFixed(2)}`;
                    const textWidth = ctx.measureText(text).width;
                    const badgeW = textWidth + 14;
                    const badgeH = 18;
                    const badgeX = x.right - 8;
                    let badgeY = nextMidY;

                    if (currMidVal > 0) {
                        const currMidY = y.getPixelForValue(currMidVal);
                        if (Math.abs(badgeY - currMidY) < 20) {
                            badgeY = currMidY + (badgeY >= currMidY ? 20 : -20);
                        }
                    }

                    ctx.fillStyle = 'rgba(88, 28, 135, 0.95)'; // Purple-900
                    ctx.strokeStyle = '#a855f7'; // Purple-500
                    ctx.lineWidth = 1.2;
                    ctx.beginPath();
                    ctx.roundRect(badgeX - badgeW, badgeY - (badgeH / 2), badgeW, badgeH, 4);
                    ctx.fill();
                    ctx.stroke();

                    ctx.fillStyle = '#e9d5ff'; // Purple-200
                    ctx.fillText(text, badgeX - 7, badgeY);
                }
            }

            // F. DRAW CE GREEN CAPSULES ABOVE CE POINTS
            ctx.font = 'bold 10px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';

            if (ceMeta && !ceMeta.hidden) {
                ceMeta.data.forEach((element, index) => {
                    const val = chart.data.datasets[2].data[index];
                    if (val === null || val === undefined || isNaN(val)) return;

                    const posX = element.x;
                    const posY = element.y - 15;
                    const text = `₹${parseFloat(val).toFixed(1)}`;
                    const textWidth = ctx.measureText(text).width;
                    const pillWidth = Math.max(textWidth + 18, 44);
                    const pillHeight = 17;
                    const pillX = posX - (pillWidth / 2);
                    const pillY = posY - (pillHeight / 2);

                    // Capsule Body
                    ctx.fillStyle = 'rgba(6, 78, 59, 0.92)';
                    ctx.strokeStyle = '#10b981';
                    ctx.lineWidth = 1.2;
                    ctx.beginPath();
                    ctx.roundRect(pillX, pillY, pillWidth, pillHeight, 8.5);
                    ctx.fill();
                    ctx.stroke();

                    // Green Dot
                    ctx.fillStyle = '#34d399';
                    ctx.beginPath();
                    ctx.arc(pillX + 6.5, posY, 2.8, 0, Math.PI * 2);
                    ctx.fill();

                    // Text
                    ctx.fillStyle = '#ecfdf5';
                    ctx.fillText(text, pillX + 13, posY);
                });
            }

            // G. DRAW PE RED CAPSULES BELOW PE POINTS
            if (peMeta && !peMeta.hidden) {
                peMeta.data.forEach((element, index) => {
                    const val = chart.data.datasets[3].data[index];
                    if (val === null || val === undefined || isNaN(val)) return;

                    const posX = element.x;
                    const posY = element.y + 15;
                    const text = `₹${parseFloat(val).toFixed(1)}`;
                    const textWidth = ctx.measureText(text).width;
                    const pillWidth = Math.max(textWidth + 18, 44);
                    const pillHeight = 17;
                    const pillX = posX - (pillWidth / 2);
                    const pillY = posY - (pillHeight / 2);

                    // Capsule Body
                    ctx.fillStyle = 'rgba(136, 19, 55, 0.92)';
                    ctx.strokeStyle = '#f43f5e';
                    ctx.lineWidth = 1.2;
                    ctx.beginPath();
                    ctx.roundRect(pillX, pillY, pillWidth, pillHeight, 8.5);
                    ctx.fill();
                    ctx.stroke();

                    // Red Dot
                    ctx.fillStyle = '#fb7185';
                    ctx.beginPath();
                    ctx.arc(pillX + 6.5, posY, 2.8, 0, Math.PI * 2);
                    ctx.fill();

                    // Text
                    ctx.fillStyle = '#fff1f2';
                    ctx.fillText(text, pillX + 13, posY);
                });
            }

            ctx.restore();
        }
    };

    // ──────────────────────────────────────────────
    // 2. CHART INITIALIZATION
    // ──────────────────────────────────────────────
    function initChart() {
        const ctx = document.getElementById('liveStrikeChart').getContext('2d');

        const strikeLabels = state.strikesData.map(d => d.strike);

        const currentMidLine = state.strikesData.map(() => state.currentMidPoint);
        const nextMidLine    = state.strikesData.map(() => state.nextMidPoint);

        const cePrices = state.strikesData.map(d => {
            const key = d.ce.instrument_key;
            return key && livePrices[key] !== undefined ? livePrices[key] : (d.ce.price || null);
        });

        const pePrices = state.strikesData.map(d => {
            const key = d.pe.instrument_key;
            return key && livePrices[key] !== undefined ? livePrices[key] : (d.pe.price || null);
        });

        chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: strikeLabels,
                datasets: [
                    // Thick Line 1: Current Week Mid Point
                    {
                        label: 'Current Week Mid Point',
                        data: currentMidLine,
                        borderColor: '#3b82f6',
                        backgroundColor: '#3b82f6',
                        borderWidth: 4,
                        borderDash: [6, 4],
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        tension: 0,
                        order: 3,
                    },
                    // Thick Line 2: Next Week Mid Point
                    {
                        label: 'Next Week Mid Point',
                        data: nextMidLine,
                        borderColor: '#a855f7',
                        backgroundColor: '#a855f7',
                        borderWidth: 4,
                        borderDash: [2, 2],
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        tension: 0,
                        order: 4,
                    },
                    // CE Live Prices Curve & Green Dots
                    {
                        label: 'CE Live LTP',
                        data: cePrices,
                        borderColor: '#10b981',
                        backgroundColor: '#10b981',
                        borderWidth: 3,
                        pointRadius: 6,
                        pointHoverRadius: 9,
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        tension: 0.2,
                        order: 1,
                    },
                    // PE Live Prices Curve & Red Dots
                    {
                        label: 'PE Live LTP',
                        data: pePrices,
                        borderColor: '#f43f5e',
                        backgroundColor: '#f43f5e',
                        borderWidth: 3,
                        pointRadius: 6,
                        pointHoverRadius: 9,
                        pointBackgroundColor: '#f43f5e',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        tension: 0.2,
                        order: 2,
                    }
                ]
            },
            plugins: [chartCapsuleAndReferencePlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: { top: 38, bottom: 25, left: 10, right: 10 }
                },
                animation: { duration: 250 },
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        grid: {
                            color: function (context) {
                                const strikeVal = strikeLabels[context.index];
                                if (strikeVal === state.atmStrike) {
                                    return 'rgba(16, 185, 129, 0.45)'; // Highlight center ATM
                                }
                                return 'rgba(51, 65, 85, 0.35)';
                            },
                            lineWidth: function (context) {
                                const strikeVal = strikeLabels[context.index];
                                return strikeVal === state.atmStrike ? 2 : 1;
                            }
                        },
                        ticks: {
                            color: function (context) {
                                const strikeVal = strikeLabels[context.index];
                                return strikeVal === state.atmStrike ? '#34d399' : '#94a3b8';
                            },
                            font: function (context) {
                                const strikeVal = strikeLabels[context.index];
                                return {
                                    weight: strikeVal === state.atmStrike ? 'bold' : 'normal',
                                    size: 11
                                };
                            }
                        },
                        title: {
                            display: true,
                            text: `Option Strike Prices (Center ATM: ${state.atmStrike})`,
                            color: '#cbd5e1',
                            font: { size: 12, weight: '600' }
                        }
                    },
                    y: {
                        grace: '18%', // Gives headroom for top/bottom capsules and difference bridge
                        grid: { color: 'rgba(51, 65, 85, 0.3)' },
                        ticks: {
                            color: '#94a3b8',
                            callback: function (val) {
                                return '₹' + val.toFixed(1);
                            }
                        },
                        title: {
                            display: true,
                            text: 'Option Price / Mid-Point Level (₹)',
                            color: '#cbd5e1',
                            font: { size: 12, weight: '600' }
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleColor: '#38bdf8',
                        bodyColor: '#f1f5f9',
                        borderColor: '#334155',
                        borderWidth: 1,
                        padding: 10,
                        boxPadding: 4,
                        callbacks: {
                            title: function (items) {
                                if (!items.length) return '';
                                const strike = items[0].label;
                                const isAtm = parseInt(strike) === state.atmStrike;
                                return `Strike: ${strike} ${isAtm ? '⭐ (ATM Center)' : ''}`;
                            },
                            label: function (ctx) {
                                const val = ctx.parsed.y;
                                if (val === null || val === undefined) return `${ctx.dataset.label}: —`;
                                return `${ctx.dataset.label}: ₹${val.toFixed(2)}`;
                            }
                        }
                    }
                }
            }
        });
    }

    // ──────────────────────────────────────────────
    // 3. RENDER CAPSULE PILLS (MULTI-LINE WRAP IN SEPARATE BOTTOM CARD)
    // ──────────────────────────────────────────────
    function renderCapsules() {
        const wrapGrid = document.getElementById('capsules-wrap-grid');
        if (!wrapGrid) return;

        wrapGrid.innerHTML = '';

        state.strikesData.forEach(item => {
            const isAtm = item.is_atm;
            const ceKey = item.ce.instrument_key;
            const peKey = item.pe.instrument_key;

            const cePrice = ceKey && livePrices[ceKey] !== undefined ? livePrices[ceKey] : (item.ce.price || 0);
            const pePrice = peKey && livePrices[peKey] !== undefined ? livePrices[peKey] : (item.pe.price || 0);

            const card = document.createElement('div');
            card.className = `flex-1 min-w-[110px] max-w-[150px] flex flex-col p-2 rounded-xl border transition-all ${
                isAtm 
                ? 'bg-emerald-950/40 border-emerald-500/60 ring-1 ring-emerald-500/40 shadow-lg' 
                : 'bg-slate-800/70 border-slate-700/60 hover:border-slate-600'
            }`;

            card.innerHTML = `
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-xs font-extrabold ${isAtm ? 'text-emerald-300' : 'text-slate-200'}">
                        ${item.strike}
                    </span>
                    <span class="text-[9px] font-semibold px-1 py-0.2 rounded ${
                        isAtm 
                        ? 'bg-emerald-500/20 text-emerald-300 font-bold border border-emerald-500/40' 
                        : 'text-slate-400 bg-slate-700/40'
                    }">
                        ${isAtm ? '⭐ ATM' : (item.offset > 0 ? `+${item.offset}` : item.offset)}
                    </span>
                </div>

                <div class="space-y-1">
                    {{-- CE Capsule (Green Dot & Green Pill) --}}
                    <div class="w-full flex items-center justify-between rounded-full px-2 py-0.5 bg-emerald-500/10 border border-emerald-500/40 text-emerald-300 text-[11px] font-semibold shadow-sm">
                        <span class="flex items-center gap-1 font-bold text-[10px]">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 inline-block shadow-sm shadow-emerald-400"></span>
                            CE
                        </span>
                        <span class="font-mono font-bold text-xs" data-ce-strike="${item.strike}">
                            ₹${parseFloat(cePrice).toFixed(1)}
                        </span>
                    </div>

                    {{-- PE Capsule (Red Dot & Red Pill) --}}
                    <div class="w-full flex items-center justify-between rounded-full px-2 py-0.5 bg-rose-500/10 border border-rose-500/40 text-rose-300 text-[11px] font-semibold shadow-sm">
                        <span class="flex items-center gap-1 font-bold text-[10px]">
                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500 inline-block shadow-sm shadow-rose-500"></span>
                            PE
                        </span>
                        <span class="font-mono font-bold text-xs" data-pe-strike="${item.strike}">
                            ₹${parseFloat(pePrice).toFixed(1)}
                        </span>
                    </div>
                </div>
            `;

            wrapGrid.appendChild(card);
        });
    }

    // ──────────────────────────────────────────────
    // 4. UPDATE LIVE VALUES IN DOM & ON CHART
    // ──────────────────────────────────────────────
    function updateLiveValues() {
        // 1. Update capsule text elements
        state.strikesData.forEach(item => {
            const ceKey = item.ce.instrument_key;
            const peKey = item.pe.instrument_key;

            if (ceKey && livePrices[ceKey] !== undefined) {
                const ceEls = document.querySelectorAll(`[data-ce-strike="${item.strike}"]`);
                ceEls.forEach(el => el.textContent = `₹${parseFloat(livePrices[ceKey]).toFixed(1)}`);
            }

            if (peKey && livePrices[peKey] !== undefined) {
                const peEls = document.querySelectorAll(`[data-pe-strike="${item.strike}"]`);
                peEls.forEach(el => el.textContent = `₹${parseFloat(livePrices[peKey]).toFixed(1)}`);
            }
        });

        // 2. Update Difference Displays in Header, Legends & Drawer
        const diffObj = formatDiff(liveSpotPrice, openPrice);
        const chipDiff = document.getElementById('chip-index-diff');
        const chipDiffVal = document.getElementById('chip-diff-val');
        const legDiffBadge = document.getElementById('legend-diff-badge');
        const legDiffVal = document.getElementById('legend-diff-val');
        const cardDiffVal = document.getElementById('card-diff-val');

        if (chipDiffVal) chipDiffVal.textContent = diffObj.shortText;
        if (legDiffVal) legDiffVal.textContent = diffObj.text;
        if (cardDiffVal) cardDiffVal.textContent = `Diff: ${diffObj.text}`;

        const diffBgColor = diffObj.isPos ? 'bg-emerald-500/15 text-emerald-300 border-emerald-500/40' : 'bg-rose-500/15 text-rose-300 border-rose-500/40';
        if (chipDiff) chipDiff.className = `inline-flex items-center gap-1 px-2 py-0.5 rounded-md font-bold text-[11px] border ${diffBgColor}`;
        if (legDiffBadge) legDiffBadge.className = `px-1.5 py-0.5 rounded text-[10px] font-bold border ${diffBgColor}`;
        if (cardDiffVal) cardDiffVal.className = `block text-[10px] font-mono font-bold ${diffObj.isPos ? 'text-emerald-400' : 'text-rose-400'}`;

        // 3. Update chart datasets & custom plugin drawing
        if (chartInstance) {
            const cePrices = state.strikesData.map(d => {
                const key = d.ce.instrument_key;
                return key && livePrices[key] !== undefined ? livePrices[key] : (d.ce.price || null);
            });

            const pePrices = state.strikesData.map(d => {
                const key = d.pe.instrument_key;
                return key && livePrices[key] !== undefined ? livePrices[key] : (d.pe.price || null);
            });

            chartInstance.data.datasets[2].data = cePrices;
            chartInstance.data.datasets[3].data = pePrices;
            chartInstance.update('none'); // Update smoothly without lag
        }

        // 4. Tick counter
        tickCount++;
        const tcEl = document.getElementById('tick-counter');
        if (tcEl) tcEl.textContent = `${tickCount} ticks`;
    }

    // ──────────────────────────────────────────────
    // 5. PROTOBUF & WEBSOCKET ENGINE
    // ──────────────────────────────────────────────
    async function initProtobuf() {
        try {
            protobufRoot = await protobuf.load('/MarketDataFeed_v3.proto');
            console.log('MarketDataFeed Protobuf loaded successfully.');
        } catch (e) {
            console.error('Failed to load MarketDataFeed_v3.proto:', e);
        }
    }

    async function connectWebsocket() {
        if (isConnecting) return;
        isConnecting = true;

        const dot = document.getElementById('ws-dot');
        const statusText = document.getElementById('ws-status-text');

        if (statusText) statusText.textContent = 'Connecting…';
        if (dot) dot.className = 'inline-block h-2 w-2 rounded-full bg-amber-500';

        try {
            const res = await axios.get('/api/live-strike-chart/ws-url');
            const wsUrl = res.data?.data?.authorizedRedirectUri;

            if (!wsUrl) {
                throw new Error('Authorized WebSocket URI not returned');
            }

            if (ws) {
                ws.onclose = null;
                ws.close();
            }

            ws = new WebSocket(wsUrl);
            ws.binaryType = "arraybuffer";

            ws.onopen = () => {
                isConnecting = false;
                console.log('Upstox WebSocket connected.');
                if (statusText) statusText.textContent = 'Live Feed Active';
                if (dot) dot.className = 'inline-block h-2 w-2 rounded-full bg-emerald-400 animate-pulse';

                subscribeInstruments();
            };

            ws.onclose = (e) => {
                isConnecting = false;
                console.warn('WebSocket closed, retrying in 4s...', e);
                if (statusText) statusText.textContent = 'Disconnected';
                if (dot) dot.className = 'inline-block h-2 w-2 rounded-full bg-red-500';

                setTimeout(() => connectWebsocket(), 4000);
            };

            ws.onerror = (err) => {
                console.error('WebSocket error:', err);
                isConnecting = false;
            };

            ws.onmessage = (event) => {
                if (typeof event.data === 'string') return;
                decodeProtobuf(event.data);
            };

        } catch (error) {
            isConnecting = false;
            console.error('Error establishing WebSocket:', error);
            if (statusText) statusText.textContent = 'Feed Error';
            if (dot) dot.className = 'inline-block h-2 w-2 rounded-full bg-red-500';
            setTimeout(() => connectWebsocket(), 5000);
        }
    }

    function subscribeInstruments() {
        if (!ws || ws.readyState !== WebSocket.OPEN) return;
        if (!state.allInstrumentKeys || state.allInstrumentKeys.length === 0) return;

        const subMessage = {
            guid: "live_strike_" + Date.now(),
            method: "sub",
            data: {
                mode: "full",
                instrumentKeys: state.allInstrumentKeys
            }
        };

        ws.send(new TextEncoder().encode(JSON.stringify(subMessage)));
        console.log(`Subscribed to ${state.allInstrumentKeys.length} instruments (including index).`);
    }

    function decodeProtobuf(buffer) {
        if (!protobufRoot) return;

        try {
            const arr = new Uint8Array(buffer);
            if (arr.length > 0 && arr[0] === 123) return; // JSON text message

            const FeedResponse = protobufRoot.lookupType("com.upstox.marketdatafeederv3udapi.rpc.proto.FeedResponse");
            const message = FeedResponse.decode(arr);
            const obj = FeedResponse.toObject(message, { enums: String, bytes: String });

            if (obj && obj.feeds) {
                let hasUpdates = false;

                for (const [key, feed] of Object.entries(obj.feeds)) {
                    let ltp = null;
                    if (feed.fullFeed && feed.fullFeed.marketFF && feed.fullFeed.marketFF.ltpc) {
                        ltp = feed.fullFeed.marketFF.ltpc.ltp;
                    } else if (feed.fullFeed && feed.fullFeed.indexFF && feed.fullFeed.indexFF.ltpc) {
                        ltp = feed.fullFeed.indexFF.ltpc.ltp;
                    } else if (feed.ltpc) {
                        ltp = feed.ltpc.ltp;
                    }

                    if (ltp !== null && ltp !== undefined) {
                        const parsedLtp = parseFloat(ltp);
                        if (key === state.indexKey) {
                            liveSpotPrice = parsedLtp;
                            // Update Spot Labels in Toolbar, Legend & Drawer
                            const spotLabel = document.getElementById('label-spot-val');
                            const legSpot = document.getElementById('legend-spot-val');
                            const chipSpot = document.getElementById('chip-index-spot');
                            const cardSpot = document.getElementById('card-index-spot');

                            if (spotLabel) spotLabel.textContent = parsedLtp.toFixed(2);
                            if (legSpot) legSpot.textContent = parsedLtp.toFixed(2);
                            if (chipSpot) chipSpot.textContent = `₹${parsedLtp.toFixed(2)}`;
                            if (cardSpot) cardSpot.textContent = `Spot: ${parsedLtp.toFixed(2)}`;
                        } else if (state.futureKey && key === state.futureKey) {
                            liveFuturePrice = parsedLtp;
                            // Update Fut Labels in Toolbar, Legend & Drawer
                            const futLabel = document.getElementById('label-fut-val');
                            const legFut = document.getElementById('legend-fut-val');
                            const chipFut = document.getElementById('chip-index-fut');
                            const cardFut = document.getElementById('card-index-fut');

                            if (futLabel) futLabel.textContent = parsedLtp.toFixed(2);
                            if (legFut) legFut.textContent = parsedLtp.toFixed(2);
                            if (chipFut) chipFut.textContent = `₹${parsedLtp.toFixed(2)}`;
                            if (cardFut) cardFut.textContent = `Fut: ${parsedLtp.toFixed(2)}`;
                        } else {
                            livePrices[key] = parsedLtp;
                        }
                        hasUpdates = true;
                    }
                }

                if (hasUpdates) {
                    updateLiveValues();
                }
            }
        } catch (e) {
            console.warn('Protobuf decode error:', e);
        }
    }

    // ──────────────────────────────────────────────
    // 6. DYNAMIC FILTER UPDATE VIA AJAX
    // ──────────────────────────────────────────────
    async function applyFilters() {
        const symbol   = document.getElementById('filter-symbol').value;
        const atm      = document.getElementById('filter-atm').value;
        const range    = document.getElementById('filter-range').value;
        const spinner  = document.getElementById('apply-spinner');

        if (spinner) spinner.classList.remove('hidden');

        try {
            const res = await axios.get('/api/live-strike-chart/data', {
                params: { symbol, atm, range }
            });

            if (res.data && res.data.success) {
                const d = res.data.data;
                state = d;
                openPrice = d.indexOpen || openPrice;
                if (!liveSpotPrice || liveSpotPrice === 0) {
                    liveSpotPrice = d.indexSpot || d.indexClose || openPrice;
                }
                liveFuturePrice = d.futurePrice || liveFuturePrice || liveSpotPrice;

                // Update Chips & Legends
                document.getElementById('chip-curr-mid').textContent = parseFloat(d.currentMidPoint).toFixed(2);
                document.getElementById('chip-next-mid').textContent = parseFloat(d.nextMidPoint).toFixed(2);
                document.getElementById('legend-curr-mid').textContent = parseFloat(d.currentMidPoint).toFixed(2);
                document.getElementById('legend-next-mid').textContent = parseFloat(d.nextMidPoint).toFixed(2);

                document.getElementById('chip-index-open').textContent = parseFloat(openPrice).toFixed(2);
                document.getElementById('label-open-val').textContent = parseFloat(openPrice).toFixed(2);
                document.getElementById('legend-open-val').textContent = parseFloat(openPrice).toFixed(2);

                document.getElementById('chip-index-spot').textContent = `₹${parseFloat(liveSpotPrice).toFixed(2)}`;
                document.getElementById('label-spot-val').textContent = parseFloat(liveSpotPrice).toFixed(2);
                document.getElementById('legend-spot-val').textContent = parseFloat(liveSpotPrice).toFixed(2);

                const futLabel = document.getElementById('label-fut-val');
                const legFut = document.getElementById('legend-fut-val');
                const chipFut = document.getElementById('chip-index-fut');
                const cardFut = document.getElementById('card-index-fut');
                if (futLabel) futLabel.textContent = parseFloat(liveFuturePrice).toFixed(2);
                if (legFut) legFut.textContent = parseFloat(liveFuturePrice).toFixed(2);
                if (chipFut) chipFut.textContent = `₹${parseFloat(liveFuturePrice).toFixed(2)}`;
                if (cardFut) cardFut.textContent = `Fut: ${parseFloat(liveFuturePrice).toFixed(2)}`;

                // Update Drawer Stats
                document.getElementById('card-curr-midpoint').textContent = parseFloat(d.currentMidPoint).toFixed(2);
                document.getElementById('card-next-midpoint').textContent = parseFloat(d.nextMidPoint).toFixed(2);
                document.getElementById('card-atm-strike').textContent = d.atmStrike;
                document.getElementById('card-index-spot').textContent = `Spot: ${parseFloat(liveSpotPrice).toFixed(2)}`;
                document.getElementById('card-detected-atm').textContent = d.detectedAtm;
                document.getElementById('label-detected-atm').textContent = d.detectedAtm;

                document.getElementById('card-range-badge').textContent = `±${d.range} Range`;
                document.getElementById('card-strikes-count').textContent = d.strikesData.length;
                if (d.strikesData.length > 0) {
                    document.getElementById('card-strike-span').textContent = 
                        `${d.strikesData[0].strike} to ${d.strikesData[d.strikesData.length - 1].strike}`;
                }

                // Hydrate fallback prices for new strikes if not in livePrices
                d.strikesData.forEach(item => {
                    if (item.ce.instrument_key && !livePrices[item.ce.instrument_key] && item.ce.price > 0) {
                        livePrices[item.ce.instrument_key] = item.ce.price;
                    }
                    if (item.pe.instrument_key && !livePrices[item.pe.instrument_key] && item.pe.price > 0) {
                        livePrices[item.pe.instrument_key] = item.pe.price;
                    }
                });

                // Update Chart structure
                if (chartInstance) {
                    const strikeLabels = d.strikesData.map(item => item.strike);
                    chartInstance.data.labels = strikeLabels;
                    chartInstance.data.datasets[0].data = d.strikesData.map(() => d.currentMidPoint);
                    chartInstance.data.datasets[1].data = d.strikesData.map(() => d.nextMidPoint);
                    chartInstance.options.scales.x.title.text = `Option Strike Prices (Center ATM: ${d.atmStrike})`;
                    chartInstance.update();
                }

                // Re-render multi-line capsules & difference
                renderCapsules();
                updateLiveValues();

                // Subscribe to newly added keys
                subscribeInstruments();
            }
        } catch (error) {
            console.error('Error applying filters:', error);
            alert('Failed to update chart data with selected filters.');
        } finally {
            if (spinner) spinner.classList.add('hidden');
        }
    }

    // ──────────────────────────────────────────────
    // 7. DOM EVENT LISTENERS
    // ──────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', async () => {
        // Render initial UI
        renderCapsules();
        initChart();
        updateLiveValues();

        // Connect Protobuf & WebSocket
        await initProtobuf();
        await connectWebsocket();

        // Show/Hide Spot Marker Checkbox Toggle
        const showSpotCheckbox = document.getElementById('filter-show-spot');
        const legendSpotWrapper = document.getElementById('legend-spot-wrapper');
        if (showSpotCheckbox) {
            showSpotCheckbox.addEventListener('change', (e) => {
                showSpotMarker = e.target.checked;
                if (legendSpotWrapper) {
                    legendSpotWrapper.style.display = showSpotMarker ? 'flex' : 'none';
                }
                const legendDiffWrapper = document.getElementById('legend-diff-wrapper');
                if (legendDiffWrapper) {
                    legendDiffWrapper.style.display = (showSpotMarker && showOpenMarker) ? 'flex' : 'none';
                }
                if (chartInstance) chartInstance.update('none');
            });
        }

        // Show/Hide Fut Marker Checkbox Toggle
        const showFutCheckbox = document.getElementById('filter-show-fut');
        const legendFutWrapper = document.getElementById('legend-fut-wrapper');
        if (showFutCheckbox) {
            showFutCheckbox.addEventListener('change', (e) => {
                showFutMarker = e.target.checked;
                if (legendFutWrapper) {
                    legendFutWrapper.style.display = showFutMarker ? 'flex' : 'none';
                }
                if (chartInstance) chartInstance.update('none');
            });
        }

        // Show/Hide Open Marker Checkbox Toggle
        const showOpenCheckbox = document.getElementById('filter-show-open');
        const legendOpenWrapper = document.getElementById('legend-open-wrapper');
        if (showOpenCheckbox) {
            showOpenCheckbox.addEventListener('change', (e) => {
                showOpenMarker = e.target.checked;
                if (legendOpenWrapper) {
                    legendOpenWrapper.style.display = showOpenMarker ? 'flex' : 'none';
                }
                const legendDiffWrapper = document.getElementById('legend-diff-wrapper');
                if (legendDiffWrapper) {
                    legendDiffWrapper.style.display = (showSpotMarker && showOpenMarker) ? 'flex' : 'none';
                }
                if (chartInstance) chartInstance.update('none');
            });
        }

        // Stats drawer toggle
        const toggleStatsBtn = document.getElementById('btn-toggle-stats');
        const statsDrawer = document.getElementById('stats-drawer');
        if (toggleStatsBtn && statsDrawer) {
            toggleStatsBtn.addEventListener('click', () => {
                statsDrawer.classList.toggle('hidden');
            });
        }

        // Filter button
        document.getElementById('btn-apply-filters').addEventListener('click', applyFilters);

        // Reset to ATM button
        document.getElementById('btn-reset-atm').addEventListener('click', () => {
            document.getElementById('filter-atm').value = state.detectedAtm;
            applyFilters();
        });

        // +/- ATM Buttons
        document.getElementById('btn-atm-minus').addEventListener('click', () => {
            const atmInput = document.getElementById('filter-atm');
            atmInput.value = parseInt(atmInput.value || 0) - state.strikeStep;
            applyFilters();
        });

        document.getElementById('btn-atm-plus').addEventListener('click', () => {
            const atmInput = document.getElementById('filter-atm');
            atmInput.value = parseInt(atmInput.value || 0) + state.strikeStep;
            applyFilters();
        });

        // Symbol change
        document.getElementById('filter-symbol').addEventListener('change', () => {
            applyFilters();
        });

        // Range change
        document.getElementById('filter-range').addEventListener('change', () => {
            applyFilters();
        });
    });

})();
</script>
@endpush
@endsection
