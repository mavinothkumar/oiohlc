@extends('layouts.app')

@section('title', 'Multi-Chart Strategy Grid — Strike Reaction Analysis')

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
    [x-cloak] { display: none !important; }
    .font-mono-num { font-variant-numeric: tabular-nums; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
    .custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #090d16; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #334155; }
    .chart-spin {
        width: 32px; height: 32px; border: 3px solid #334155;
        border-top-color: #2dd4bf; border-radius: 50%; animation: spin 0.7s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .center-card-highlight {
        box-shadow: 0 0 0 1.5px rgba(245, 158, 11, 0.5), 0 8px 24px -4px rgba(245, 158, 11, 0.15);
    }
</style>
@endpush

@section('content')
<div class="w-full max-w-[1920px] mx-auto px-2 sm:px-4 py-2 space-y-2.5 text-slate-100 font-sans"
     x-data="multiChartApp()"
     x-init="init()"
     id="multi-chart-root">

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- ONE UNIFIED MASTER FORM FOR ALL GLOBAL & PER-CHART INPUTS              --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <form method="GET" action="{{ route('strategy.multi.chart') }}" id="master-grid-form" onsubmit="saveParams()">
        <input type="hidden" name="symbol" value="{{ $symbol }}">

        {{-- ── 1. GLOBAL FILTER BAR ───────────────────────────────────────── --}}
        <div class="bg-slate-900/95 border border-slate-800 rounded-xl px-3 py-2 shadow-lg backdrop-blur-md mb-2.5">
            <div class="flex items-center gap-2 overflow-x-auto custom-scrollbar py-0.5 text-xs flex-wrap">

                {{-- Page Title --}}
                <div class="flex items-center gap-2 flex-shrink-0">
                    <span class="text-sm font-black uppercase tracking-wider text-teal-400">⊞ Multi-Chart Grid</span>
                    <span class="text-[10px] text-slate-500 font-mono-num">Strike Reaction Analysis</span>
                </div>

                {{-- Layout Toggle (2x3 or 3x3) --}}
                <div class="flex items-center gap-1.5 flex-shrink-0">
                    <span class="text-[10px] uppercase font-bold text-slate-400">Layout:</span>
                    <div class="inline-flex bg-slate-950 p-0.5 rounded-lg border border-slate-800 font-bold shadow-inner">
                        <label class="px-2.5 py-1 rounded cursor-pointer transition text-[11px] {{ $layout === '2x3' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-white' }}">
                            <input type="radio" name="layout" value="2x3" class="hidden" onchange="submitMasterForm()" {{ $layout === '2x3' ? 'checked' : '' }}>
                            <span>2 × 3</span>
                        </label>
                        <label class="px-2.5 py-1 rounded cursor-pointer transition text-[11px] {{ $layout === '3x3' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-white' }}">
                            <input type="radio" name="layout" value="3x3" class="hidden" onchange="submitMasterForm()" {{ $layout === '3x3' ? 'checked' : '' }}>
                            <span>3 × 3</span>
                        </label>
                    </div>
                </div>

                {{-- Date --}}
                <div class="flex items-center gap-1.5 bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1 focus-within:border-teal-500 transition flex-shrink-0">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Date:</span>
                    <select name="date" onchange="submitMasterForm()"
                            class="bg-transparent text-xs font-mono-num font-bold text-white focus:outline-none cursor-pointer">
                        @foreach($availableDates as $ad)
                            <option value="{{ $ad }}" class="bg-slate-900 text-white" {{ $selectedDate == $ad ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::parse($ad)->format('d M Y') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Expiry --}}
                <div class="flex items-center gap-1.5 bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1 focus-within:border-teal-500 transition flex-shrink-0">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Expiry:</span>
                    <select name="expiry" onchange="submitMasterForm()"
                            class="bg-transparent text-xs font-mono-num font-bold text-emerald-300 focus:outline-none cursor-pointer">
                        @foreach($availableExpiries as $exp)
                            <option value="{{ $exp }}" class="bg-slate-900 text-emerald-300" {{ $selectedExpiry == $exp ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::parse($exp)->format('d M Y') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Time Range --}}
                <div class="flex items-center gap-1 bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1 font-mono-num flex-shrink-0">
                    <span class="text-[10px] text-slate-400 font-bold uppercase mr-1">Time:</span>
                    <input type="hidden" name="start_time" value="{{ $startTime }}">
                    <span class="text-slate-400 text-xs">{{ $startTime }} &rarr;</span>
                    <input type="text" name="end_time" value="{{ $endTime }}" placeholder="15:30"
                           class="w-12 bg-slate-900 border border-slate-700 rounded px-1 py-0.5 text-xs text-amber-300 font-mono-num text-center focus:outline-none focus:border-teal-500">
                </div>

                {{-- ── CENTER ATM (GLOBAL ANCHOR) ─────────────────────────── --}}
                <div class="flex items-center gap-1.5 rounded-lg px-2.5 py-1 flex-shrink-0 transition-all duration-200 border bg-amber-950/40 border-amber-600/70 shadow-sm"
                     title="Center ATM: Center chart gets this strike. Left/preceding charts get -50, -100, -150... Right/following charts get +50, +100, +150...">
                    <span class="text-[10px] font-black uppercase text-amber-400 flex items-center gap-1">
                        <span>🎯 Center ATM:</span>
                    </span>
                    <select name="global_atm" id="global-atm-select"
                            onchange="onGlobalAtmChange(this.value)"
                            class="bg-transparent text-xs font-mono-num font-black text-amber-300 focus:outline-none cursor-pointer">
                        @foreach($allStrikes as $stk)
                            <option value="{{ $stk }}" class="bg-slate-900 text-amber-300"
                                    {{ ((float)$globalAtm === (float)$stk) ? 'selected' : '' }}>
                                {{ $stk }} {{ ($stk == $calculatedAtm) ? '★' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <span class="text-[9px] text-amber-300 uppercase font-black px-1.5 py-0.2 rounded bg-amber-900/80 border border-amber-600 whitespace-nowrap">
                        Center on #{{ $centerIndex + 1 }}
                    </span>
                </div>

                {{-- Quick Applicator: Apply Strategy to All (Optional Convenience) --}}
                <div class="flex items-center gap-1 bg-slate-950 border border-slate-800 rounded-lg px-2 py-1 flex-shrink-0">
                    <span class="text-[9px] uppercase font-bold text-slate-400">Apply Strat to All:</span>
                    <select onchange="applyStrategyToAll(this.value)"
                            class="bg-transparent text-xs font-semibold text-indigo-300 focus:outline-none cursor-pointer">
                        <option value="" class="bg-slate-900 text-slate-400">— Set All —</option>
                        @foreach($backtestStrategies as $bStrat)
                            <option value="{{ $bStrat->id }}" class="bg-slate-900 text-white">
                                {{ $bStrat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Submit CTA --}}
                <div class="flex items-center flex-shrink-0 ml-auto">
                    <button type="button" onclick="submitMasterForm()"
                            class="px-4 py-1.5 rounded-lg font-black text-xs bg-gradient-to-r from-teal-500 via-emerald-500 to-cyan-500 hover:from-teal-400 hover:to-cyan-400 text-slate-950 shadow-md shadow-teal-500/10 transition active:scale-95 flex items-center gap-1 whitespace-nowrap">
                        <span>⚡ Apply All</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ── 2. GLOBAL DATASET SERIES TOGGLES (Shared across all charts) ──── --}}
        <div class="bg-slate-900/80 border border-slate-800 rounded-xl px-3 py-2 shadow flex flex-wrap items-center gap-2 text-xs mb-2.5">
            <span class="text-[10px] uppercase font-extrabold text-slate-400 tracking-wider mr-1">SERIES:</span>

            <button type="button" @click="toggleAllDataset(0)"
                    :class="datasetVisibility[0] ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40 shadow-sm' : 'bg-slate-950 text-slate-500 border-slate-800 opacity-60'"
                    class="px-2.5 py-1 rounded-lg border transition flex items-center gap-1.5 font-bold">
                <span class="w-3.5 h-[3px] rounded-sm bg-emerald-400"></span><span>💵 Premium</span>
            </button>

            <button type="button" @click="toggleAllDataset(1)"
                    :class="datasetVisibility[1] ? 'bg-amber-500/20 text-amber-300 border-amber-500/40 shadow-sm' : 'bg-slate-950 text-slate-500 border-slate-800 opacity-60'"
                    class="px-2.5 py-1 rounded-lg border transition flex items-center gap-1.5 font-bold">
                <span class="w-3.5 h-[4.5px] rounded-sm bg-amber-400"></span><span>🟠 VWAP</span>
            </button>

            <button type="button" @click="toggleAllDataset(2)"
                    :class="datasetVisibility[2] ? 'bg-purple-500/20 text-purple-300 border-purple-500/40 shadow-sm' : 'bg-slate-950 text-slate-500 border-slate-800 opacity-60'"
                    class="px-2.5 py-1 rounded-lg border transition flex items-center gap-1.5 font-bold">
                <span class="w-3.5 border-b-[2px] border-dotted border-purple-400 h-[2px]"></span><span>🟣 OI-VWAP</span>
            </button>

            <button type="button" @click="toggleAllDataset(3)"
                    :class="datasetVisibility[3] ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40 shadow-sm' : 'bg-slate-950 text-slate-500 border-slate-800 opacity-60'"
                    class="px-2.5 py-1 rounded-lg border transition flex items-center gap-1.5 font-bold">
                <span class="w-3.5 h-[2.5px] rounded-sm bg-cyan-400"></span><span>📊 Net OI</span>
            </button>

            <span class="text-[10px] uppercase font-bold text-slate-500 mx-1">GREEKS:</span>

            <button type="button" @click="toggleAllDataset(4)"
                    :class="datasetVisibility[4] ? 'bg-blue-500/20 text-blue-300 border-blue-500/40 shadow-sm' : 'bg-slate-950 text-slate-500 border-slate-800 opacity-60'"
                    class="px-2.5 py-1 rounded-lg border transition flex items-center gap-1.5 font-bold">
                <span class="w-3.5 h-[1.5px] rounded-sm bg-blue-400"></span><span>&Delta; Delta</span>
            </button>

            <button type="button" @click="toggleAllDataset(5)"
                    :class="datasetVisibility[5] ? 'bg-teal-500/20 text-teal-300 border-teal-500/40 shadow-sm' : 'bg-slate-950 text-slate-500 border-slate-800 opacity-60'"
                    class="px-2.5 py-1 rounded-lg border transition flex items-center gap-1.5 font-bold">
                <span class="w-3.5 h-[1.5px] rounded-sm bg-teal-400"></span><span>&Theta; Theta</span>
            </button>

            <button type="button" @click="toggleAllDataset(6)"
                    :class="datasetVisibility[6] ? 'bg-indigo-500/20 text-indigo-300 border-indigo-500/40 shadow-sm' : 'bg-slate-950 text-slate-500 border-slate-800 opacity-60'"
                    class="px-2.5 py-1 rounded-lg border transition flex items-center gap-1.5 font-bold">
                <span class="w-3.5 h-[1.5px] rounded-sm bg-indigo-400"></span><span>&Gamma; Gamma</span>
            </button>

            <button type="button" @click="toggleAllDataset(7)"
                    :class="datasetVisibility[7] ? 'bg-rose-500/20 text-rose-300 border-rose-500/40 shadow-sm' : 'bg-slate-950 text-slate-500 border-slate-800 opacity-60'"
                    class="px-2.5 py-1 rounded-lg border transition flex items-center gap-1.5 font-bold">
                <span class="w-3.5 h-[1.5px] rounded-sm bg-rose-400"></span><span>&nu; Vega</span>
            </button>

            <button type="button" @click="toggleAllDataset(8)"
                    :class="datasetVisibility[8] ? 'bg-yellow-500/20 text-yellow-300 border-yellow-500/40 shadow-sm' : 'bg-slate-950 text-slate-500 border-slate-800 opacity-60'"
                    class="px-2.5 py-1 rounded-lg border transition flex items-center gap-1.5 font-bold">
                <span class="w-3.5 h-[1.5px] rounded-sm bg-yellow-400"></span><span>⚡ IV</span>
            </button>

            <div class="ml-auto flex items-center gap-2">
                <button type="button" @click="reloadAll()"
                        :class="loading ? 'opacity-60 cursor-not-allowed' : ''"
                        class="px-3 py-1 rounded-lg font-bold text-xs bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white transition active:scale-95 flex items-center gap-1">
                    <span x-text="loading ? '⟳ Loading…' : '⟳ Reload All'"></span>
                </button>
            </div>
        </div>

        {{-- ── 3. THE MULTI-CHART MATRIX GRID ─────────────────────────────── --}}
        <div class="{{ $layout === '3x3' ? 'grid grid-cols-3 gap-2.5' : 'grid grid-cols-2 md:grid-cols-3 gap-2.5' }}">
            @foreach($chartConfigs as $ci => $cfg)
            <div class="bg-slate-900 border rounded-xl overflow-hidden shadow-xl chart-cell transition-all duration-300
                        {{ $cfg['is_center'] ? 'border-amber-500/70 center-card-highlight' : 'border-slate-800 hover:border-slate-700' }}"
                 id="chart-card-{{ $ci }}"
                 x-data="chartCell({{ $ci }}, {{ $cfg['strategy_id'] }}, {{ $cfg['custom_atm'] }})"
                 x-init="init()">

                {{-- ── Card Header ── --}}
                <div class="px-3 py-2 border-b space-y-1.5 {{ $cfg['is_center'] ? 'border-amber-500/30 bg-amber-950/20' : 'border-slate-800/80 bg-slate-950/40' }}">

                    {{-- Row 1: Chart Index + Badge + Strategy Selector + ATM Selector --}}
                    <div class="flex items-center gap-1.5 flex-wrap">

                        {{-- Index --}}
                        <span class="text-[11px] font-black {{ $cfg['is_center'] ? 'text-amber-400' : 'text-slate-400' }} select-none font-mono-num">
                            #{{ $ci + 1 }}
                        </span>

                        {{-- Center vs Offset Badge --}}
                        @if($cfg['is_center'])
                            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-amber-500/25 text-amber-300 border border-amber-500/50 shadow-sm">
                                ★ CENTER (ATM)
                            </span>
                        @else
                            @php $off = $cfg['offset']; @endphp
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-mono-num font-bold {{ $off < 0 ? 'text-cyan-300 bg-cyan-950/50 border border-cyan-800/50' : 'text-purple-300 bg-purple-950/50 border border-purple-800/50' }}">
                                {{ $off > 0 ? "+{$off}" : $off }} pts
                            </span>
                        @endif

                        {{-- Strategy Selector --}}
                        <div class="flex items-center gap-1 bg-slate-950 border border-slate-800 rounded-lg px-2 py-0.5 focus-within:border-indigo-500 transition flex-1 min-w-[140px]">
                            <span class="text-[9px] uppercase font-bold text-slate-400 flex-shrink-0">Strat:</span>
                            <select name="strategy_id[{{ $ci }}]"
                                    id="chart-strat-{{ $ci }}"
                                    onchange="onChartStratChange({{ $ci }}, this.value)"
                                    class="bg-transparent text-xs font-bold text-white focus:outline-none cursor-pointer truncate w-full">
                                @foreach($backtestStrategies as $bStrat)
                                    <option value="{{ $bStrat->id }}" class="bg-slate-900 text-white" {{ $cfg['strategy_id'] == $bStrat->id ? 'selected' : '' }}>
                                        {{ $bStrat->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- ATM Selector --}}
                        <div class="flex items-center gap-1 rounded-lg px-2 py-0.5 border transition flex-shrink-0
                                    {{ $cfg['is_center'] ? 'bg-amber-950/50 border-amber-600/70 focus-within:border-amber-400' : 'bg-slate-950 border-slate-800 focus-within:border-cyan-500' }}">
                            <span class="text-[9px] uppercase font-bold {{ $cfg['is_center'] ? 'text-amber-400' : 'text-slate-400' }}">ATM:</span>
                            <select name="custom_atm[{{ $ci }}]"
                                    id="chart-atm-{{ $ci }}"
                                    onchange="onChartAtmChange({{ $ci }}, this.value)"
                                    class="bg-transparent text-xs font-mono-num font-black focus:outline-none cursor-pointer {{ $cfg['is_center'] ? 'text-amber-300' : 'text-cyan-300' }}">
                                @foreach($allStrikes as $stk)
                                    <option value="{{ $stk }}" class="bg-slate-900 text-white"
                                            {{ ((float)$cfg['custom_atm'] === (float)$stk) ? 'selected' : '' }}>
                                        {{ $stk }} {{ ($stk == $globalAtm) ? '★' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                    </div>

                    {{-- Row 2: Mini Summary Bar --}}
                    <div class="flex items-center gap-1.5 text-[10px] font-mono-num flex-wrap pt-0.5">
                        <span class="text-slate-400 font-semibold" x-text="strategyName || '…'"></span>
                        <span class="text-slate-600">|</span>
                        <span class="text-slate-400">ATM:</span>
                        <span class="font-black" :class="isCenter ? 'text-amber-300' : 'text-cyan-300'" x-text="atmStrike || '—'"></span>

                        <span class="text-slate-600 mx-0.5">|</span>
                        <span class="text-slate-400">Prem:</span>
                        <span class="font-bold" :class="(decayPts ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400'"
                              x-text="latestPrem !== null ? '₹' + latestPrem.toFixed(2) : '—'"></span>
                        <template x-if="decayPts !== null">
                            <span class="font-bold text-[10px]" :class="decayPts >= 0 ? 'text-emerald-400' : 'text-rose-400'"
                                  x-text="(decayPts >= 0 ? '+' : '') + decayPts.toFixed(1) + ' (' + decayPct.toFixed(1) + '%)'"></span>
                        </template>

                        <span class="text-slate-600">|</span>
                        <span class="text-slate-400">Δ:</span>
                        <span class="font-bold" :class="Math.abs(latestDelta ?? 0) < 20 ? 'text-emerald-400' : 'text-amber-400'"
                              x-text="latestDelta !== null ? (latestDelta >= 0 ? '+' : '') + latestDelta.toFixed(3) : '—'"></span>

                        {{-- Leg Pills --}}
                        <div class="flex items-center gap-1 ml-auto">
                            <template x-for="leg in legs" :key="leg.leg_id">
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold"
                                      :class="leg.option_type === 'CE' ? 'bg-emerald-950/60 border border-emerald-900/60 text-emerald-300' : 'bg-rose-950/60 border border-rose-900/60 text-rose-300'"
                                      x-text="leg.label"></span>
                            </template>
                        </div>
                    </div>

                </div>

                {{-- ── Chart Canvas (Absolute layout inside rigid container stops infinite growth) ── --}}
                <div class="relative" style="height: {{ $layout === '3x3' ? '260px' : '300px' }}; overflow: hidden;">
                    <canvas :id="'chart-' + cellIndex"
                            style="position:absolute;top:0;left:0;width:100%;height:100%;display:block;"></canvas>

                    {{-- Loading Overlay --}}
                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(9,13,22,0.88);border-radius:8px;z-index:10;"
                         x-show="loading" x-cloak>
                        <div class="chart-spin"></div>
                    </div>

                    {{-- Empty State --}}
                    <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#475569;font-size:12px;gap:6px;pointer-events:none;"
                         x-show="!loading && empty" x-cloak>
                        <span style="font-size:24px;">📭</span>
                        <span>No data for this strike</span>
                    </div>
                </div>

            </div>
            @endforeach
        </div>

    </form>

</div>

@push('scripts')
<script>
// ─── LOCAL STORAGE KEY ────────────────────────────────────────────────────────
const MC_PARAMS_KEY = 'mc_last_query';

/**
 * Save current master form query string into localStorage.
 */
function saveParams() {
    try {
        const form = document.getElementById('master-grid-form');
        if (form) {
            const qs = new URLSearchParams(new FormData(form)).toString();
            if (qs) localStorage.setItem(MC_PARAMS_KEY, qs);
        }
    } catch(e) {}
}

/**
 * Update URL in address bar without reload, and save to localStorage.
 */
function updateUrlAndStorage() {
    try {
        const form = document.getElementById('master-grid-form');
        if (form) {
            const qs = new URLSearchParams(new FormData(form)).toString();
            if (qs) {
                localStorage.setItem(MC_PARAMS_KEY, qs);
                history.replaceState(null, '', '?' + qs);
            }
        }
    } catch(e) {}
}

/**
 * Submit the unified master form.
 */
function submitMasterForm() {
    saveParams();
    const form = document.getElementById('master-grid-form');
    if (form) form.submit();
}

/**
 * Center ATM Changed:
 * Calculates new centered strikes for all 6/9 charts and reloads the grid.
 */
function onGlobalAtmChange(newAtm) {
    const centerAtm = Number(newAtm || {{ $calculatedAtm }});
    const centerIdx = {{ $centerIndex }};
    const step = {{ $step }};
    const count = {{ $chartCount }};

    // Update each chart's custom_atm select to its new shifted strike
    for (let i = 0; i < count; i++) {
        const shift = (i - centerIdx) * step;
        const targetStrike = centerAtm + shift;
        const select = document.getElementById('chart-atm-' + i);
        if (select) {
            select.value = targetStrike;
        }
    }

    submitMasterForm();
}

/**
 * Quick shortcut: Apply chosen strategy to all charts.
 */
function applyStrategyToAll(strategyId) {
    if (!strategyId) return;
    const count = {{ $chartCount }};
    for (let i = 0; i < count; i++) {
        const select = document.getElementById('chart-strat-' + i);
        if (select) select.value = strategyId;
        const cell = getCellComponent(i);
        if (cell) {
            cell.strategyId = strategyId;
            cell.loadData();
        }
    }
    updateUrlAndStorage();
}

/**
 * Per-chart strategy change:
 * Reloads only that chart via AJAX (no flicker/scroll-jump), updates URL & storage.
 */
function onChartStratChange(cellIndex, strategyId) {
    const cell = getCellComponent(cellIndex);
    if (cell) {
        cell.strategyId = strategyId;
        cell.loadData();
    }
    updateUrlAndStorage();
}

/**
 * Per-chart ATM change:
 * Reloads only that chart via AJAX (no flicker/scroll-jump), updates URL & storage.
 */
function onChartAtmChange(cellIndex, customAtm) {
    const cell = getCellComponent(cellIndex);
    if (cell) {
        cell.customAtm = customAtm;
        cell.loadData();
    }
    updateUrlAndStorage();
}

/**
 * Helper to fetch Alpine cell component instance.
 */
function getCellComponent(cellIndex) {
    const el = document.getElementById('chart-card-' + cellIndex);
    if (el && el._x_dataStack && el._x_dataStack.length > 0) {
        return el._x_dataStack[0];
    }
    return null;
}

// ─── Shared right-side label plugin ──────────────────────────────────────────
const mcRightSideLabels = {
    id: 'mcRightSideLabels',
    afterDraw(chart) {
        const { ctx, chartArea, scales } = chart;
        if (!chartArea) return;

        const activeItems = [];
        chart.data.datasets.forEach((ds, idx) => {
            if (!chart.isDatasetVisible(idx)) return;
            const meta = chart.getDatasetMeta(idx);
            if (!meta || meta.hidden || !meta.data || meta.data.length === 0) return;

            let lastPoint = null, lastValue = null;
            for (let i = meta.data.length - 1; i >= 0; i--) {
                const pt = meta.data[i];
                const rawVal = ds.data[i];
                if (pt && !isNaN(pt.y) && !isNaN(pt.x) && rawVal !== null && rawVal !== undefined && !isNaN(rawVal)) {
                    lastPoint = pt; lastValue = Number(rawVal); break;
                }
            }
            if (!lastPoint) return;

            let formattedVal = '';
            const ax = ds.yAxisID;
            if (ax === 'yPremium') formattedVal = '₹' + lastValue.toFixed(2);
            else if (ax === 'yOI') {
                const s = lastValue >= 0 ? '+' : '';
                formattedVal = Math.abs(lastValue) >= 100000
                    ? s + (lastValue / 100000).toFixed(2) + 'L'
                    : s + (lastValue / 1000).toFixed(1) + 'k';
            }
            else if (ax === 'yIV')    formattedVal = lastValue.toFixed(2) + '%';
            else if (ax === 'yDelta') formattedVal = (lastValue >= 0 ? '+' : '') + lastValue.toFixed(3);
            else if (ax === 'yTheta') formattedVal = (lastValue >= 0 ? '+' : '') + lastValue.toFixed(2);
            else if (ax === 'yVega')  formattedVal = (lastValue >= 0 ? '+' : '') + lastValue.toFixed(2);
            else if (ax === 'yGamma') formattedVal = lastValue.toFixed(5);
            else                      formattedVal = lastValue.toFixed(2);

            activeItems.push({ name: ds.shortLabel || ds.label || '', value: formattedVal, color: ds.borderColor, x: lastPoint.x, y: lastPoint.y, targetY: lastPoint.y });
        });

        if (activeItems.length === 0) return;
        activeItems.sort((a, b) => a.targetY - b.targetY);

        const badgeH = 16, minGap = 18;
        for (let i = 1; i < activeItems.length; i++) {
            if (activeItems[i].targetY < activeItems[i - 1].targetY + minGap)
                activeItems[i].targetY = activeItems[i - 1].targetY + minGap;
        }
        const maxAllowedY = chartArea.bottom - badgeH / 2;
        const minAllowedY = chartArea.top + badgeH / 2;
        if (activeItems[activeItems.length - 1].targetY > maxAllowedY) {
            const shift = activeItems[activeItems.length - 1].targetY - maxAllowedY;
            for (let i = 0; i < activeItems.length; i++) activeItems[i].targetY -= shift;
        }
        for (let i = 0; i < activeItems.length; i++) {
            if (i === 0 && activeItems[i].targetY < minAllowedY) activeItems[i].targetY = minAllowedY;
            else if (i > 0 && activeItems[i].targetY < activeItems[i - 1].targetY + minGap) activeItems[i].targetY = activeItems[i - 1].targetY + minGap;
        }

        const yOIScale = scales['yOI'];
        const rightAxisOffset = (yOIScale && yOIScale.width && chart.isDatasetVisible(3)) ? yOIScale.width + 6 : 6;
        const badgeX = chartArea.right + rightAxisOffset;

        ctx.save();
        ctx.font = '600 9px monospace, sans-serif';
        ctx.textBaseline = 'middle';

        activeItems.forEach(item => {
            const text = `${item.name}: ${item.value}`;
            const textWidth = ctx.measureText(text).width;
            const badgeW = textWidth + 12;
            const rx = badgeX, ry = item.targetY - badgeH / 2;

            ctx.beginPath(); ctx.setLineDash([2, 3]); ctx.strokeStyle = item.color;
            ctx.globalAlpha = 0.5; ctx.lineWidth = 1;
            ctx.moveTo(item.x, item.y); ctx.lineTo(rx - 3, item.targetY); ctx.stroke();
            ctx.setLineDash([]); ctx.globalAlpha = 1.0;

            ctx.fillStyle = item.color; ctx.beginPath();
            ctx.arc(rx - 3, item.targetY, 2, 0, Math.PI * 2); ctx.fill();

            ctx.fillStyle = '#090d16'; ctx.strokeStyle = item.color; ctx.lineWidth = 1.2;
            ctx.beginPath();
            if (ctx.roundRect) ctx.roundRect(rx, ry, badgeW, badgeH, 2);
            else ctx.rect(rx, ry, badgeW, badgeH);
            ctx.fill(); ctx.stroke();

            ctx.fillStyle = item.color; ctx.beginPath();
            if (ctx.roundRect) ctx.roundRect(rx + 2, ry + 2, 3, badgeH - 4, 1);
            else ctx.rect(rx + 2, ry + 2, 3, badgeH - 4);
            ctx.fill();

            ctx.fillStyle = '#f8fafc';
            ctx.fillText(text, rx + 8, item.targetY);
        });

        ctx.restore();
    }
};

// ─── Global chart registry ────────────────────────────────────────────────────
window._mcCharts = {};

// ─── Dataset visibility (shared, persisted) ───────────────────────────────────
function loadMcVisibility() {
    const def = [true, true, false, false, false, false, false, false, false];
    try {
        const saved = localStorage.getItem('mc_dataset_visibility');
        if (saved) {
            const parsed = JSON.parse(saved);
            if (Array.isArray(parsed) && parsed.length === def.length) return parsed.map(v => Boolean(v));
        }
    } catch(e) {}
    return def;
}

function saveMcVisibility(vis) {
    try { localStorage.setItem('mc_dataset_visibility', JSON.stringify(vis)); } catch(e) {}
}

// ─── Parent Alpine component ──────────────────────────────────────────────────
function multiChartApp() {
    return {
        layout: '{{ $layout }}',
        loading: false,
        datasetVisibility: loadMcVisibility(),

        init() {
            const search = window.location.search;
            if (!search || search === '?' || search === '') {
                // Naked URL without parameters: check localStorage to restore session
                try {
                    const saved = localStorage.getItem(MC_PARAMS_KEY);
                    if (saved && saved.length > 3) {
                        window.location.replace('{{ route('strategy.multi.chart') }}?' + saved);
                        return;
                    }
                } catch(e) {}
            } else {
                // URL has parameters: save current state to localStorage
                try {
                    localStorage.setItem(MC_PARAMS_KEY, search.replace(/^\?/, ''));
                } catch(e) {}
            }
        },

        toggleAllDataset(idx) {
            this.datasetVisibility[idx] = !this.datasetVisibility[idx];
            saveMcVisibility(this.datasetVisibility);
            Object.values(window._mcCharts).forEach(chart => {
                if (chart) {
                    chart.setDatasetVisibility(idx, this.datasetVisibility[idx]);
                    chart.update();
                }
            });
        },

        reloadAll() {
            const count = {{ $chartCount }};
            for (let i = 0; i < count; i++) {
                const cell = getCellComponent(i);
                if (cell && typeof cell.loadData === 'function') {
                    cell.loadData();
                }
            }
        }
    };
}

// ─── Per-chart Alpine component ───────────────────────────────────────────────
function chartCell(cellIndex, initialStrategyId, initialCustomAtm) {
    return {
        cellIndex,
        strategyId:   initialStrategyId,
        customAtm:    initialCustomAtm,
        isCenter:     (cellIndex === {{ $centerIndex }}),
        loading:      false,
        empty:        false,
        strategyName: null,
        atmStrike:    null,
        latestPrem:   null,
        decayPts:     null,
        decayPct:     0,
        latestDelta:  null,
        legs:         [],

        init() {
            this.loadData();
        },

        getParentVis() {
            return loadMcVisibility();
        },

        async loadData() {
            this.loading = true;
            this.empty   = false;

            const params = new URLSearchParams({
                symbol:      '{{ $symbol }}',
                date:        '{{ $selectedDate }}',
                expiry:      '{{ $selectedExpiry }}',
                start_time:  '{{ $startTime }}',
                end_time:    '{{ $endTime }}',
                strategy_id: this.strategyId,
            });
            if (this.customAtm) params.append('custom_atm', this.customAtm);

            try {
                const resp = await fetch('{{ route('api.strategy.multi.chart.data') }}?' + params.toString());
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                const data = await resp.json();
                this.updateMeta(data);
                this.renderChart(data.chart_data);
            } catch(err) {
                console.error('Chart ' + this.cellIndex + ' load error:', err);
                this.empty = true;
            } finally {
                this.loading = false;
            }
        },

        updateMeta(data) {
            this.strategyName = data.strategy_name || null;
            this.atmStrike    = data.atm_strike    || this.customAtm || null;
            this.legs         = data.resolved_legs || [];
            const s = data.summary || {};
            this.latestPrem  = s.latest_premium  ?? null;
            this.decayPts    = s.net_decay_points ?? null;
            this.decayPct    = s.decay_pct        ?? 0;
            this.latestDelta = s.latest_delta     ?? null;
        },

        renderChart(chartData) {
            if (!chartData || !chartData.labels || chartData.labels.length === 0) {
                this.empty = true;
                return;
            }

            const canvasId = 'chart-' + this.cellIndex;
            const canvas   = document.getElementById(canvasId);
            if (!canvas || typeof Chart === 'undefined') return;

            // Destroy existing chart
            const existing = window._mcCharts[this.cellIndex];
            if (existing) { existing.destroy(); delete window._mcCharts[this.cellIndex]; }

            // Stamp explicit pixel dimensions — prevents Chart.js infinite-growth loop
            const wrapper = canvas.parentElement;
            if (wrapper) {
                canvas.width  = wrapper.clientWidth  || wrapper.offsetWidth  || 400;
                canvas.height = wrapper.clientHeight || wrapper.offsetHeight || 300;
            }

            const vis    = this.getParentVis();
            const labels = chartData.labels;

            const chart = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        // 0: Combined Premium (Emerald)
                        {
                            label: 'Combined Premium', shortLabel: 'Premium',
                            data: chartData.combined_ltp,
                            borderColor: '#34d399', backgroundColor: 'rgba(52, 211, 153, 0.07)',
                            fill: true, borderWidth: 3.0, tension: 0.1,
                            pointRadius: 0, pointHoverRadius: 5,
                            yAxisID: 'yPremium', hidden: !vis[0]
                        },
                        // 1: VWAP (Amber solid thick)
                        {
                            label: 'Combined VWAP', shortLabel: 'VWAP',
                            data: chartData.vwap,
                            borderColor: '#fbbf24', backgroundColor: 'transparent',
                            borderWidth: 4.0, tension: 0.1,
                            pointRadius: 0, pointHoverRadius: 5,
                            yAxisID: 'yPremium', hidden: !vis[1]
                        },
                        // 2: OI-VWAP (Purple dotted)
                        {
                            label: 'OI-Weighted VWAP', shortLabel: 'OI-VWAP',
                            data: chartData.oi_vwap,
                            borderColor: '#c084fc', backgroundColor: 'transparent',
                            borderDash: [3, 3], borderWidth: 1.8, tension: 0.1,
                            pointRadius: 0, pointHoverRadius: 4,
                            yAxisID: 'yPremium', hidden: !vis[2]
                        },
                        // 3: Net OI Change (Cyan)
                        {
                            label: 'Net OI Change', shortLabel: 'Net OI',
                            data: chartData.net_oi,
                            borderColor: '#22d3ee', backgroundColor: 'rgba(34, 211, 238, 0.04)',
                            borderWidth: 2.2, tension: 0.2,
                            pointRadius: 0, pointHoverRadius: 4,
                            yAxisID: 'yOI', hidden: !vis[3]
                        },
                        // 4: Net Delta (Blue)
                        {
                            label: 'Net Delta (Δ)', shortLabel: 'Delta',
                            data: chartData.delta,
                            borderColor: '#60a5fa', backgroundColor: 'transparent',
                            borderWidth: 1.5, tension: 0.2,
                            pointRadius: 0, pointHoverRadius: 4,
                            yAxisID: 'yDelta', hidden: !vis[4]
                        },
                        // 5: Theta (Teal)
                        {
                            label: 'Theta (θ/day)', shortLabel: 'Theta',
                            data: chartData.theta,
                            borderColor: '#2dd4bf', backgroundColor: 'transparent',
                            borderWidth: 1.5, tension: 0.2,
                            pointRadius: 0, pointHoverRadius: 4,
                            yAxisID: 'yTheta', hidden: !vis[5]
                        },
                        // 6: Gamma (Indigo)
                        {
                            label: 'Gamma (Γ)', shortLabel: 'Gamma',
                            data: chartData.gamma,
                            borderColor: '#a855f7', backgroundColor: 'transparent',
                            borderWidth: 1.5, tension: 0.2,
                            pointRadius: 0, pointHoverRadius: 4,
                            yAxisID: 'yGamma', hidden: !vis[6]
                        },
                        // 7: Vega (Rose)
                        {
                            label: 'Vega (ν/1% IV)', shortLabel: 'Vega',
                            data: chartData.vega,
                            borderColor: '#fb7185', backgroundColor: 'transparent',
                            borderWidth: 1.5, tension: 0.2,
                            pointRadius: 0, pointHoverRadius: 4,
                            yAxisID: 'yVega', hidden: !vis[7]
                        },
                        // 8: IV (Yellow)
                        {
                            label: 'Implied Volatility (IV)', shortLabel: 'IV',
                            data: chartData.iv,
                            borderColor: '#facc15', backgroundColor: 'transparent',
                            borderWidth: 1.5, tension: 0.2,
                            pointRadius: 0, pointHoverRadius: 4,
                            yAxisID: 'yIV', hidden: !vis[8]
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { right: 130, left: 4, top: 8, bottom: 4 } },
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#090d16',
                            borderColor: '#334155', borderWidth: 1,
                            titleColor: '#e2e8f0', bodyColor: '#cbd5e1', padding: 8,
                            callbacks: {
                                label(context) {
                                    const lbl = context.dataset.label || '';
                                    const val = Number(context.raw);
                                    const ax  = context.dataset.yAxisID;
                                    if (ax === 'yPremium') return lbl + ': ₹' + val.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                    if (ax === 'yOI')     return lbl + ': ' + (val >= 0 ? '+' : '') + val.toLocaleString('en-IN');
                                    if (ax === 'yIV')     return lbl + ': ' + val.toFixed(2) + '%';
                                    if (ax === 'yDelta')  return lbl + ': ' + (val >= 0 ? '+' : '') + val.toFixed(3);
                                    if (ax === 'yTheta')  return lbl + ': ' + (val >= 0 ? '+' : '') + val.toFixed(2) + '/day';
                                    if (ax === 'yVega')   return lbl + ': ' + (val >= 0 ? '+' : '') + val.toFixed(2) + '/1% IV';
                                    if (ax === 'yGamma')  return lbl + ': ' + val.toFixed(5);
                                    return lbl + ': ' + val.toFixed(3);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(51, 65, 85, 0.2)' },
                            ticks: { color: '#94a3b8', font: { family: 'monospace', size: 9 }, maxTicksLimit: 10 }
                        },
                        yPremium: {
                            type: 'linear', position: 'left',
                            grid: { color: 'rgba(51, 65, 85, 0.3)' },
                            ticks: {
                                color: '#34d399', font: { family: 'monospace', size: 9 },
                                callback: val => '₹' + val.toLocaleString('en-IN')
                            }
                        },
                        yOI: {
                            type: 'linear', position: 'right',
                            grid: { drawOnChartArea: false },
                            ticks: {
                                color: '#22d3ee', font: { family: 'monospace', size: 8 },
                                callback: val => (val / 1000).toFixed(0) + 'k'
                            }
                        },
                        yDelta: { type: 'linear', position: 'right', grid: { drawOnChartArea: false }, display: false },
                        yTheta: { type: 'linear', position: 'right', grid: { drawOnChartArea: false }, display: false },
                        yVega:  { type: 'linear', position: 'right', grid: { drawOnChartArea: false }, display: false },
                        yGamma: { type: 'linear', position: 'right', grid: { drawOnChartArea: false }, display: false },
                        yIV:    { type: 'linear', position: 'right', grid: { drawOnChartArea: false }, display: false },
                    }
                },
                plugins: [mcRightSideLabels]
            });

            window._mcCharts[this.cellIndex] = chart;
        }
    };
}
</script>
@endpush
@endsection
