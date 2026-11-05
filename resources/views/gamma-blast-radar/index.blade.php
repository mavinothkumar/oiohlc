@extends('layouts.app')

@section('title', 'Gamma Blast & GEX Scalper Radar | Institutional Microstructure')

@push('styles')
    <!-- Chart.js 4.4 -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <!-- Flatpickr (Calendar Date Picker) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        body {
            background-color: #080c14 !important;
            color: #d1d5db;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        /* Top header minimal spacing */
        header {
            padding: 0.5rem 1rem !important;
        }
        main {
            padding: 0.25rem 0.5rem !important;
        }

        /* Custom Dark Scrollbars */
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        ::-webkit-scrollbar-track {
            background: #0d121f;
        }
        ::-webkit-scrollbar-thumb {
            background: #1f293d;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #374151;
        }

        /* Subtle Pulse Animations */
        @keyframes pulse-subtle {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.15); }
        }
        .live-dot {
            animation: pulse-subtle 2s infinite ease-in-out;
        }

        @keyframes blast-pulse {
            0%, 100% { box-shadow: 0 0 15px rgba(236, 72, 153, 0.4), inset 0 0 10px rgba(236, 72, 153, 0.2); }
            50% { box-shadow: 0 0 25px rgba(236, 72, 153, 0.8), inset 0 0 15px rgba(236, 72, 153, 0.4); }
        }
        .blast-glow {
            animation: blast-pulse 2s infinite ease-in-out;
        }

        /* Dark Cockpit Card */
        .cockpit-card {
            background-color: #0d121f;
            border: 1px solid #1a2234;
            border-radius: 0.5rem;
        }

        /* Flatpickr Dark Customization */
        .flatpickr-calendar {
            background: #101626 !important;
            border: 1px solid #28354f !important;
            border-radius: 8px !important;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.8) !important;
        }
        .flatpickr-calendar .flatpickr-months {
            background: #172033 !important;
        }
        .flatpickr-day {
            color: #e5e7eb !important;
        }
        .flatpickr-day.selected {
            background: #8b5cf6 !important;
            border-color: #8b5cf6 !important;
        }
    </style>
@endpush

@section('content')
<div class="space-y-3 w-full max-w-[1920px] mx-auto pb-8">

    {{-- ════════════════════════════════════════════════════════════════════════════ --}}
    {{-- TOP COMMAND BAR: DATE, EXPIRY, SPOT TICKER, AUTO-REFRESH & LAST SYNC TIME    --}}
    {{-- ════════════════════════════════════════════════════════════════════════════ --}}
    <div class="cockpit-card p-2 sm:p-2.5 flex flex-wrap items-center justify-between gap-2.5 shadow-lg border-fuchsia-950/60">
        <!-- Left: Title & Symbol -->
        <div class="flex items-center gap-2 flex-wrap">
            <div class="flex items-center gap-1.5">
                <span class="p-1 rounded bg-fuchsia-500/20 text-fuchsia-400 border border-fuchsia-500/30 text-sm">⚡</span>
                <div>
                    <h1 class="text-sm sm:text-base font-extrabold text-white tracking-wide flex items-center gap-2">
                        <span>GAMMA BLAST RADAR</span>
                        <span class="text-[10px] font-mono px-1.5 py-0.2 rounded uppercase bg-fuchsia-900/60 text-fuchsia-300 border border-fuchsia-600/40">GEX SCALPER</span>
                    </h1>
                    <p class="text-[10px] text-slate-400 font-sans hidden sm:block">Market Maker Gamma Exposure, Zero-Flip Trigger & Expiry Squeeze Detection</p>
                </div>
            </div>

            <!-- Underlying Selector -->
            <div class="flex items-center bg-[#131a2b] border border-slate-700/80 rounded px-2 py-0.5 text-xs font-mono font-bold text-white">
                <span class="text-fuchsia-400 mr-1.5">INDEX:</span>
                <span>{{ $symbol }}</span>
            </div>
        </div>

        <!-- Middle: Date & Expiry Controls -->
        <div class="flex items-center gap-2 flex-wrap">
            <!-- Date Navigator -->
            <div class="flex items-center bg-[#131a2b] border border-slate-700/80 rounded overflow-hidden text-xs">
                <button type="button" id="btn-prev-date" title="Previous Trading Day" class="px-2 py-1 text-slate-400 hover:text-white hover:bg-slate-800 transition cursor-pointer font-bold">&larr;</button>
                <div class="relative flex items-center px-2 py-0.5 border-x border-slate-700/80">
                    <span class="text-slate-400 text-[10px] mr-1 hidden sm:inline">📅</span>
                    <input type="text" id="date-picker-input" value="{{ $selectedDate }}" readonly class="bg-transparent text-white font-mono text-xs w-24 text-center cursor-pointer focus:outline-none font-semibold">
                </div>
                <button type="button" id="btn-next-date" title="Next Trading Day" class="px-2 py-1 text-slate-400 hover:text-white hover:bg-slate-800 transition cursor-pointer font-bold">&rarr;</button>
            </div>

            <!-- Expiry Selector -->
            <div class="flex items-center bg-[#131a2b] border border-slate-700/80 rounded px-2 py-0.5 text-xs">
                <span class="text-slate-400 text-[10px] mr-1.5 uppercase font-bold hidden sm:inline">EXP:</span>
                <select id="expiry-select" class="bg-transparent text-amber-300 font-mono font-bold text-xs focus:outline-none cursor-pointer">
                    @foreach($expiries as $exp)
                        <option value="{{ $exp }}" {{ $exp === $selectedExpiry ? 'selected' : '' }} class="bg-[#101626] text-white">
                            {{ $exp }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Right: Spot Ticker, Sync Controls, Last Sync Time & Reload -->
        <div class="flex items-center gap-2 flex-wrap">
            <!-- Spot Price Pill -->
            <div class="flex items-center bg-[#131a2b] border border-slate-700/80 rounded px-2.5 py-0.5 text-xs font-mono">
                <span class="text-slate-400 text-[10px] mr-1.5 font-bold uppercase">SPOT:</span>
                <span id="spot-price-val" class="text-white font-extrabold text-sm tracking-tight">₹{{ number_format($gexData['spot'] ?? 0, 2) }}</span>
            </div>

            <!-- Auto-Refresh Selector -->
            <div class="flex items-center bg-[#131a2b] border border-slate-700/80 rounded px-1.5 py-0.5 text-xs gap-1" title="Real-time Auto-Refresh Interval">
                <span class="text-slate-400 text-[10px] flex items-center gap-0.5 font-bold">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 live-dot"></span>
                    <span class="hidden sm:inline">Sync:</span>
                </span>
                <select id="auto-refresh-select" class="bg-transparent text-emerald-300 font-bold focus:outline-none cursor-pointer text-[11px]">
                    <option value="15">15s</option>
                    <option value="30" selected>30s</option>
                    <option value="60">1m</option>
                    <option value="300">5m</option>
                    <option value="0">Off</option>
                </select>
                <div class="flex items-center gap-0.5 border-l border-slate-700/80 pl-1">
                    <span id="countdown-badge" class="px-1.5 py-0.2 bg-slate-800 text-sky-300 font-mono text-[10px] rounded font-bold">30s</span>
                </div>
                <!-- Last Sync Time Badge (Only Time: HH:mm:ss) -->
                <div class="flex items-center gap-1 border-l border-slate-700/80 pl-1.5" title="Last Sync Time (HH:mm:ss)">
                    <span class="text-slate-400 text-[9.5px] uppercase font-sans tracking-tight hidden md:inline">Synced:</span>
                    <span id="last-sync-time-badge" class="px-1.5 py-0.2 bg-slate-800 text-emerald-300 font-mono text-[10px] rounded font-bold cursor-default select-none">{{ \Carbon\Carbon::now('Asia/Kolkata')->format('H:i:s') }}</span>
                </div>
            </div>

            <!-- Manual Reload Button -->
            <button id="btn-reload" title="Refresh Gamma Exposure Now" class="p-1.5 bg-[#131a2b] hover:bg-slate-700 border border-slate-700/80 rounded text-slate-300 hover:text-white transition cursor-pointer">
                <svg id="reload-spinner" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </button>
        </div>
    </div>

    @if(isset($gexData['success']) && $gexData['success'] === false && !empty($gexData['error']))
        <div class="p-2.5 rounded-lg bg-amber-950/40 border border-amber-500/40 text-amber-300 text-xs flex items-center justify-between gap-2 font-mono shadow-md">
            <span class="flex items-center gap-2">
                <span class="text-base">⏳</span>
                <span>{{ $gexData['error'] }}</span>
            </span>
            <a href="{{ route('gamma-blast.index', ['date' => $availableDates[1] ?? ($availableDates[0] ?? $selectedDate)]) }}" class="underline text-sky-400 hover:text-sky-300 font-bold shrink-0">
                View Previous Day Data &rarr;
            </a>
        </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════════════════ --}}
    {{-- 4 KEY MICROSTRUCTURE HERO CARDS: GEX REGIME, FLIP POINT, CALL & PUT WALLS    --}}
    {{-- ════════════════════════════════════════════════════════════════════════════ --}}
    @php
        $summary = $gexData['summary'] ?? [];
        $netGex = $summary['total_net_gex_cr'] ?? 0;
        $callWall = $summary['call_wall'] ?? [];
        $putWall = $summary['put_wall'] ?? [];
        $flip = $summary['zero_gamma_flip'] ?? ($gexData['spot'] ?? 0);
        $spot = $gexData['spot'] ?? 0;
        $spotDiffFlip = $spot - $flip;
    @endphp
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-2.5">
        <!-- 1. Market GEX Regime & Net Exposure -->
        <div class="cockpit-card p-3 shadow-md border-l-4 {{ $netGex >= 0 ? 'border-l-emerald-500' : 'border-l-rose-500' }} flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">NET MARKET GAMMA (GEX)</span>
                    <span id="hero-regime-pill" class="px-2 py-0.5 rounded text-[9.5px] font-mono font-black uppercase {{ $summary['regime_bg'] ?? 'bg-emerald-500/10 border-emerald-500/30' }} {{ $summary['regime_color'] ?? 'text-emerald-400' }} border">
                        {{ ($summary['regime'] ?? 'NEUTRAL') === 'POSITIVE_GAMMA' ? '+GEX REGIME' : (($summary['regime'] ?? 'NEUTRAL') === 'NEGATIVE_GAMMA' ? '-GEX REGIME' : 'NEUTRAL GEX') }}
                    </span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span id="hero-net-gex-val" class="text-2xl sm:text-3xl font-mono font-black {{ $netGex >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                        {{ ($netGex >= 0 ? '+' : '') . number_format($netGex, 1) }} Cr
                    </span>
                </div>
            </div>
            <div class="mt-2 pt-2 border-t border-slate-800/80 text-[11px] text-slate-300">
                <span id="hero-regime-desc" class="line-clamp-2">
                    {{ $summary['regime_desc'] ?? 'Market Makers gamma dampens volatility. Mean-reverting regime.' }}
                </span>
            </div>
        </div>

        <!-- 2. Zero-Gamma Flip Point (Volatility Trigger Line) -->
        <div class="cockpit-card p-3 shadow-md border-l-4 border-l-amber-500 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">ZERO-GAMMA FLIP TRIGGER</span>
                    <span class="px-1.5 py-0.2 rounded text-[9.5px] font-mono font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">LINE IN SAND</span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span id="hero-flip-point-val" class="text-2xl sm:text-3xl font-mono font-black text-amber-300">
                        ₹{{ number_format($flip, 1) }}
                    </span>
                    <span id="hero-spot-diff-flip" class="text-xs font-mono font-bold {{ $spotDiffFlip >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                        ({{ ($spotDiffFlip >= 0 ? '+' : '') . number_format($spotDiffFlip, 1) }} pts)
                    </span>
                </div>
            </div>
            <div class="mt-2 pt-2 border-t border-slate-800/80 text-[11px] text-slate-300">
                <span id="hero-flip-guidance">
                    @if($spot >= $flip)
                        <span class="text-emerald-400 font-semibold">Above Flip:</span> Market volatility compressed. Fading high/low boundaries has edge.
                    @else
                        <span class="text-rose-400 font-semibold">Below Flip:</span> High-volatility expansion active. Trend momentum scalps dominate.
                    @endif
                </span>
            </div>
        </div>

        <!-- 3. Call Gamma Wall (Major Ceiling Pin) -->
        <div class="cockpit-card p-3 shadow-md border-l-4 border-l-rose-500 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">CALL GAMMA WALL (SUPPLY PIN)</span>
                    <span class="px-1.5 py-0.2 rounded text-[9.5px] font-mono font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30">CEILING</span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span id="hero-call-wall-val" class="text-2xl sm:text-3xl font-mono font-black text-rose-300">
                        ₹{{ number_format($callWall['strike'] ?? 0, 0) }}
                    </span>
                    <span id="hero-call-wall-gex" class="text-xs font-mono font-bold text-rose-400">
                        (+{{ number_format($callWall['gex_cr'] ?? 0, 1) }} Cr)
                    </span>
                </div>
            </div>
            <div class="mt-2 pt-2 border-t border-slate-800/80 text-[11px] text-slate-300">
                <span>Heavy institutional Call writing. Hard barrier on 1st test; breach unleashes a violent short squeeze.</span>
            </div>
        </div>

        <!-- 4. Put Gamma Wall (Major Floor Support) -->
        <div class="cockpit-card p-3 shadow-md border-l-4 border-l-emerald-500 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">PUT GAMMA WALL (DEMAND FLOOR)</span>
                    <span class="px-1.5 py-0.2 rounded text-[9.5px] font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">SUPPORT</span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span id="hero-put-wall-val" class="text-2xl sm:text-3xl font-mono font-black text-emerald-300">
                        ₹{{ number_format($putWall['strike'] ?? 0, 0) }}
                    </span>
                    <span id="hero-put-wall-gex" class="text-xs font-mono font-bold text-emerald-400">
                        (-{{ number_format($putWall['gex_cr'] ?? 0, 1) }} Cr)
                    </span>
                </div>
            </div>
            <div class="mt-2 pt-2 border-t border-slate-800/80 text-[11px] text-slate-300">
                <span>Concentrated Put block absorption zone. Prime location for intraday dip-buying bounces.</span>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════════ --}}
    {{-- GAMMA BLAST SCALPER RADAR: TOP HIGH-PROBABILITY SQUEEZE OPPORTUNITIES        --}}
    {{-- ════════════════════════════════════════════════════════════════════════════ --}}
    <div class="cockpit-card p-3 shadow-xl border-fuchsia-900/40">
        <div class="flex items-center justify-between pb-2 mb-2.5 border-b border-slate-800">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-fuchsia-500 live-dot"></span>
                <h2 class="text-sm font-extrabold text-white uppercase tracking-wider flex items-center gap-2">
                    <span>⚡ ACTIVE GAMMA BLAST SCALPING RADAR</span>
                    <span class="text-[10px] text-slate-400 font-sans font-normal">(Auto-calculated across Gamma Density, Trapped OI & Delta Expansion)</span>
                </h2>
            </div>
            <div class="text-xs text-slate-400 font-mono">
                <span>Targeting 35% – 70%+ Option Premium Surges</span>
            </div>
        </div>

        <div id="blast-setups-container" class="grid grid-cols-1 md:grid-cols-3 gap-3">
            @forelse($gexData['top_blast_setups'] ?? [] as $idx => $setup)
                <div class="bg-[#101626] border {{ $setup['direction'] === 'BULLISH' ? 'border-emerald-500/40 bg-emerald-950/10' : 'border-rose-500/40 bg-rose-950/10' }} rounded-lg p-3 flex flex-col justify-between transition hover:border-fuchsia-500/60 shadow-md">
                    <div>
                        <!-- Header: Option & Blast Score -->
                        <div class="flex items-center justify-between mb-2">
                            <span class="px-2 py-0.5 rounded text-xs font-mono font-black {{ $setup['direction'] === 'BULLISH' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-rose-500/20 text-rose-300 border border-rose-500/40' }}">
                                {{ $setup['option'] }}
                            </span>
                            <div class="flex items-center gap-1 font-mono">
                                <span class="text-[10px] text-slate-400">BLAST POP:</span>
                                <span class="text-xs font-black {{ $setup['score'] >= 80 ? 'text-fuchsia-400 font-extrabold animate-pulse' : 'text-amber-400' }}">
                                    {{ $setup['score'] }}%
                                </span>
                            </div>
                        </div>

                        <!-- Type & Narrative -->
                        <h3 class="text-xs font-bold text-white mb-1">{{ $setup['type'] }}</h3>
                        <p class="text-[11px] text-slate-400 leading-snug mb-2.5">{{ $setup['description'] }}</p>

                        <!-- Key Trade Parameters -->
                        <div class="grid grid-cols-2 gap-1.5 p-2 rounded bg-[#0b0e17] border border-slate-800 text-[11px] font-mono mb-2.5">
                            <div>
                                <span class="text-[9.5px] text-slate-500 block uppercase">Option LTP:</span>
                                <span class="text-white font-bold">₹{{ number_format($setup['ltp'], 2) }}</span>
                            </div>
                            <div>
                                <span class="text-[9.5px] text-slate-500 block uppercase">Delta / Gamma:</span>
                                <span class="text-cyan-300 font-bold">{{ number_format($setup['delta'], 2) }} / {{ number_format($setup['gamma'], 4) }}</span>
                            </div>
                            <div class="col-span-2 pt-1 border-t border-slate-800/80">
                                <span class="text-[9.5px] text-amber-400/90 block uppercase">Trigger Condition:</span>
                                <span class="text-amber-200 font-bold">{{ $setup['trigger'] }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Target / SL & Action -->
                    <div>
                        <div class="flex items-center justify-between text-xs font-mono p-1.5 rounded bg-slate-900/80 border border-slate-800 mb-2">
                            <div>
                                <span class="text-[9px] text-slate-500 block">OPTION TGT (+55%):</span>
                                <span class="text-emerald-400 font-bold">₹{{ number_format($setup['target_opt'], 1) }}</span>
                            </div>
                            <div class="text-right">
                                <span class="text-[9px] text-slate-500 block">OPTION SL (-30%):</span>
                                <span class="text-rose-400 font-bold">₹{{ number_format($setup['stop_loss'], 1) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-3 text-center py-6 text-slate-500 text-xs font-mono">
                    No active gamma squeeze blast candidates detected for current snapshot.
                </div>
            @endforelse
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════════ --}}
    {{-- VISUALIZATION SECTION: STRIKE GEX PROFILE & INTRADAY GEX EVOLUTION CHARTS   --}}
    {{-- ════════════════════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-3">
        <!-- Chart 1: Strike-by-Strike Net GEX Profile Bar Chart (7 Cols) -->
        <div class="xl:col-span-7 cockpit-card p-3 shadow-lg flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2 pb-1.5 border-b border-slate-800">
                <div class="flex items-center gap-2">
                    <span class="text-sm">📊</span>
                    <h3 class="text-xs font-extrabold text-white uppercase tracking-wider">STRIKE-BY-STRIKE GAMMA EXPOSURE (GEX)</h3>
                </div>
                <div class="flex items-center gap-2 text-[10px] font-mono">
                    <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 bg-rose-500 rounded-sm"></span> Call GEX</span>
                    <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 bg-emerald-500 rounded-sm"></span> Put GEX</span>
                    <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 bg-amber-400 rounded-sm"></span> Net GEX</span>
                </div>
            </div>
            <div class="relative w-full h-[320px] sm:h-[380px]">
                <canvas id="gexProfileChart"></canvas>
            </div>
        </div>

        <!-- Chart 2: Intraday GEX & Spot Evolution Dual-Axis Chart (5 Cols) -->
        <div class="xl:col-span-5 cockpit-card p-3 shadow-lg flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2 pb-1.5 border-b border-slate-800">
                <div class="flex items-center gap-2">
                    <span class="text-sm">📈</span>
                    <h3 class="text-xs font-extrabold text-white uppercase tracking-wider">INTRADAY GEX & SPOT EVOLUTION</h3>
                </div>
                <div class="flex items-center gap-2 text-[10px] font-mono">
                    <span class="flex items-center gap-1 text-cyan-400 font-bold">Spot</span>
                    <span class="flex items-center gap-1 text-amber-400 font-bold">Net GEX</span>
                </div>
            </div>
            <div class="relative w-full h-[320px] sm:h-[380px]">
                <canvas id="gexEvolutionChart"></canvas>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════════ --}}
    {{-- INTRADAY SNAPSHOT SCRUBBER / REPLAY BAR                                      --}}
    {{-- ════════════════════════════════════════════════════════════════════════════ --}}
    <div class="cockpit-card p-2 sm:p-2.5 shadow-md flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-2 shrink-0">
            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-black uppercase bg-indigo-500/20 text-indigo-300 border border-indigo-500/40">
                ⏱️ SNAPSHOT SCRUBBER
            </span>
            <span class="text-xs text-slate-400 hidden sm:inline">Scrub to view Gamma evolution across market hours:</span>
        </div>

        @php
            $timesList = $gexData['available_times'] ?? [];
            $maxTimeLbl = !empty($timesList) ? end($timesList) : '15:30';
            $minTimeLbl = !empty($timesList) ? $timesList[0] : '09:15';
        @endphp
        <div class="flex-1 min-w-[200px] flex items-center gap-2">
            <span id="slider-min-lbl" class="text-[10px] font-mono text-slate-500">{{ $minTimeLbl }}</span>
            <input type="range" id="snapshot-time-slider" min="0" max="{{ max(0, count($timesList) - 1) }}" value="{{ max(0, count($timesList) - 1) }}" class="w-full h-1.5 bg-slate-700 rounded-lg appearance-none cursor-pointer accent-fuchsia-500">
            <span id="slider-max-lbl" class="text-[10px] font-mono text-slate-500">{{ $maxTimeLbl }}</span>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <span class="text-[10px] text-slate-400 font-bold uppercase">Viewing Time:</span>
            <span id="current-snapshot-time" class="px-2 py-0.5 rounded bg-slate-800 text-sky-300 font-mono font-bold text-xs border border-slate-700">
                {{ $gexData['time'] ?? '15:30' }}
            </span>
            <button type="button" id="btn-snap-latest" class="px-2 py-0.5 rounded bg-fuchsia-950/80 hover:bg-fuchsia-900 border border-fuchsia-600/50 text-fuchsia-300 text-xs font-mono font-bold cursor-pointer transition">
                ⚡ LATEST
            </button>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════════ --}}
    {{-- INSTITUTIONAL STRIKE GAMMA MATRIX TABLE                                      --}}
    {{-- ════════════════════════════════════════════════════════════════════════════ --}}
    <div class="cockpit-card p-3 shadow-lg overflow-hidden">
        <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-800 flex-wrap gap-2">
            <div class="flex items-center gap-2">
                <span class="text-sm">📐</span>
                <h3 class="text-xs font-extrabold text-white uppercase tracking-wider">FULL STRIKE GAMMA EXPOSURE & SQUEEZE MATRIX</h3>
            </div>
            <!-- Strike Range Filter Buttons -->
            <div class="flex items-center gap-1 text-[11px] font-mono">
                <button type="button" onclick="filterLadder('atm')" id="btn-filter-atm" class="px-2 py-0.5 rounded bg-fuchsia-600 text-white font-bold transition">Near ATM (±300)</button>
                <button type="button" onclick="filterLadder('all')" id="btn-filter-all" class="px-2 py-0.5 rounded bg-slate-800 text-slate-300 hover:text-white transition">All Strikes</button>
                <button type="button" onclick="filterLadder('blast')" id="btn-filter-blast" class="px-2 py-0.5 rounded bg-slate-800 text-slate-300 hover:text-white transition">High Blast (≥60%)</button>
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar max-h-[460px]">
            <table class="w-full text-left font-mono text-[11px] border-collapse">
                <thead class="sticky top-0 bg-[#101626] border-b border-slate-700 text-slate-400 text-[10px] uppercase tracking-wider z-10">
                    <tr>
                        <th class="py-2 px-2 text-center">Strike</th>
                        <th class="py-2 px-2 text-center">Dist</th>
                        <th class="py-2 px-2 text-right text-rose-300">CE LTP</th>
                        <th class="py-2 px-2 text-right text-rose-400">Call OI</th>
                        <th class="py-2 px-2 text-right text-rose-300">Call GEX (Cr)</th>
                        <th class="py-2 px-2 text-center text-amber-300 font-extrabold">NET GEX (Cr)</th>
                        <th class="py-2 px-2 text-left text-emerald-300">Put GEX (Cr)</th>
                        <th class="py-2 px-2 text-left text-emerald-400">Put OI</th>
                        <th class="py-2 px-2 text-left text-emerald-300">PE LTP</th>
                        <th class="py-2 px-2 text-center">CE / PE Delta</th>
                        <th class="py-2 px-2 text-center text-fuchsia-300">Blast Readiness</th>
                    </tr>
                </thead>
                <tbody id="ladder-table-body" class="divide-y divide-slate-800/60">
                    @foreach($gexData['ladder'] ?? [] as $stk)
                        @php
                            $isAtm = abs($stk['spot_diff']) <= 25;
                            $isCallWall = ($stk['strike'] == ($summary['call_wall']['strike'] ?? 0));
                            $isPutWall = ($stk['strike'] == ($summary['put_wall']['strike'] ?? 0));
                        @endphp
                        <tr class="hover:bg-slate-800/40 transition ladder-row {{ $isAtm ? 'bg-amber-950/20 font-bold border-y border-amber-500/40' : '' }}" data-dist="{{ abs($stk['spot_diff']) }}" data-score="{{ $stk['blast_score'] }}">
                            <!-- Strike -->
                            <td class="py-1.5 px-2 text-center font-bold">
                                <span class="{{ $isAtm ? 'text-amber-300 text-xs font-black' : 'text-white' }}">
                                    ₹{{ number_format($stk['strike'], 0) }}
                                </span>
                                @if($isCallWall)
                                    <span class="ml-1 px-1 py-0.2 rounded text-[8px] bg-rose-500/30 text-rose-300 uppercase">CE Wall</span>
                                @elseif($isPutWall)
                                    <span class="ml-1 px-1 py-0.2 rounded text-[8px] bg-emerald-500/30 text-emerald-300 uppercase">PE Wall</span>
                                @elseif($isAtm)
                                    <span class="ml-1 px-1 py-0.2 rounded text-[8px] bg-amber-500/30 text-amber-300 uppercase">ATM</span>
                                @endif
                            </td>
                            <!-- Dist -->
                            <td class="py-1.5 px-2 text-center {{ $stk['spot_diff'] >= 0 ? 'text-rose-400' : 'text-emerald-400' }}">
                                {{ ($stk['spot_diff'] >= 0 ? '+' : '') . number_format($stk['spot_diff'], 0) }}
                            </td>
                            <!-- CE LTP -->
                            <td class="py-1.5 px-2 text-right text-slate-200">₹{{ number_format($stk['ce_ltp'], 2) }}</td>
                            <!-- Call OI -->
                            <td class="py-1.5 px-2 text-right text-rose-300">{{ number_format($stk['call_oi']) }}</td>
                            <!-- Call GEX -->
                            <td class="py-1.5 px-2 text-right text-rose-400 font-bold">+{{ number_format($stk['call_gex_cr'], 1) }}</td>
                            <!-- NET GEX -->
                            <td class="py-1.5 px-2 text-center font-black {{ $stk['net_gex_cr'] >= 0 ? 'text-emerald-400 bg-emerald-950/20' : 'text-rose-400 bg-rose-950/20' }}">
                                {{ ($stk['net_gex_cr'] >= 0 ? '+' : '') . number_format($stk['net_gex_cr'], 1) }}
                            </td>
                            <!-- Put GEX -->
                            <td class="py-1.5 px-2 text-left text-emerald-400 font-bold">{{ number_format($stk['put_gex_cr'], 1) }}</td>
                            <!-- Put OI -->
                            <td class="py-1.5 px-2 text-left text-emerald-300">{{ number_format($stk['put_oi']) }}</td>
                            <!-- PE LTP -->
                            <td class="py-1.5 px-2 text-left text-slate-200">₹{{ number_format($stk['pe_ltp'], 2) }}</td>
                            <!-- CE / PE Delta -->
                            <td class="py-1.5 px-2 text-center text-slate-400 text-[10px]">
                                {{ number_format($stk['ce_delta'], 2) }} / {{ number_format($stk['pe_delta'], 2) }}
                            </td>
                            <!-- Blast Readiness -->
                            <td class="py-1.5 px-2 text-center">
                                @if($stk['blast_score'] >= 70)
                                    <span class="px-1.5 py-0.5 rounded text-[9.5px] font-black bg-fuchsia-500/20 text-fuchsia-300 border border-fuchsia-500/40">
                                        ⚡ {{ $stk['blast_score'] }}%
                                    </span>
                                @elseif($stk['blast_score'] >= 50)
                                    <span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-amber-500/20 text-amber-300">
                                        {{ $stk['blast_score'] }}%
                                    </span>
                                @else
                                    <span class="text-slate-500 text-[10px]">{{ $stk['blast_score'] }}%</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Initial payload from server
    let currentGexData = @json($gexData ?? null);
    let selectedDate = "{{ $selectedDate }}";
    let selectedExpiry = "{{ $selectedExpiry }}";
    let selectedSymbol = "{{ $symbol }}";
    let availableTimes = @json($gexData['available_times'] ?? []);
    let activeLadderFilter = 'atm';

    // Chart instances
    let profileChart = null;
    let evolutionChart = null;

    // Timer & Auto-refresh state
    let refreshSeconds = 30;
    let countdownRemaining = 30;
    let timerInterval = null;

    document.addEventListener('DOMContentLoaded', () => {
        initDatePicker();
        initCharts();
        renderCharts(currentGexData);
        filterLadder('atm');
        initSlider();
        startAutoRefreshTimer();

        // Event listeners
        document.getElementById('expiry-select')?.addEventListener('change', (e) => {
            selectedExpiry = e.target.value;
            fetchGexData();
        });

        document.getElementById('auto-refresh-select')?.addEventListener('change', (e) => {
            refreshSeconds = parseInt(e.target.value);
            countdownRemaining = refreshSeconds;
            startAutoRefreshTimer();
        });

        document.getElementById('btn-reload')?.addEventListener('click', () => {
            fetchGexData();
        });

        document.getElementById('btn-snap-latest')?.addEventListener('click', () => {
            const slider = document.getElementById('snapshot-time-slider');
            if (slider && availableTimes.length > 0) {
                slider.value = availableTimes.length - 1;
                fetchGexData(null);
            }
        });
    });

    // ── 1. Date Picker Initialization ─────────────────────────────────────────────
    function initDatePicker() {
        const input = document.getElementById('date-picker-input');
        if (!input) return;

        flatpickr(input, {
            dateFormat: "Y-m-d",
            defaultDate: selectedDate,
            onChange: (selectedDates, dateStr) => {
                if (dateStr && dateStr !== selectedDate) {
                    selectedDate = dateStr;
                    fetchExpiriesForDate();
                }
            }
        });

        document.getElementById('btn-prev-date')?.addEventListener('click', () => {
            const cur = new Date(selectedDate);
            cur.setDate(cur.getDate() - 1);
            selectedDate = cur.toISOString().split('T')[0];
            input.value = selectedDate;
            fetchExpiriesForDate();
        });

        document.getElementById('btn-next-date')?.addEventListener('click', () => {
            const cur = new Date(selectedDate);
            cur.setDate(cur.getDate() + 1);
            selectedDate = cur.toISOString().split('T')[0];
            input.value = selectedDate;
            fetchExpiriesForDate();
        });
    }

    async function fetchExpiriesForDate() {
        try {
            const resp = await fetch(`/api/gamma-blast/expiries?date=${selectedDate}&symbol=${selectedSymbol}`);
            const data = await resp.json();
            if (data.success && data.expiries && data.expiries.length > 0) {
                const sel = document.getElementById('expiry-select');
                if (sel) {
                    sel.innerHTML = data.expiries.map(exp => `<option value="${exp}">${exp}</option>`).join('');
                    selectedExpiry = data.expiries[0];
                }
            }
            fetchGexData();
        } catch (e) {
            console.error('Error fetching expiries for date:', e);
            fetchGexData();
        }
    }

    // ── 2. Data Fetcher & AJAX Sync ───────────────────────────────────────────────
    async function fetchGexData(timeStr = null) {
        const spinner = document.getElementById('reload-spinner');
        if (spinner) spinner.classList.add('animate-spin');

        let url = `/api/gamma-blast/data?date=${selectedDate}&expiry=${selectedExpiry}&symbol=${selectedSymbol}&_t=${Date.now()}`;
        if (timeStr) {
            url += `&time=${encodeURIComponent(timeStr)}`;
        }

        try {
            const resp = await fetch(url);
            const data = await resp.json();
            if (data && data.success) {
                currentGexData = data;
                updateUI(data);
                renderCharts(data);

                // Update Last Sync Time (Only Time: HH:mm:ss)
                const now = new Date();
                const timeOnly = String(now.getHours()).padStart(2, '0') + ':' +
                                 String(now.getMinutes()).padStart(2, '0') + ':' +
                                 String(now.getSeconds()).padStart(2, '0');
                const badge = document.getElementById('last-sync-time-badge');
                if (badge) {
                    badge.textContent = timeOnly;
                    badge.classList.add('bg-emerald-500/30', 'text-emerald-200');
                    setTimeout(() => badge.classList.remove('bg-emerald-500/30', 'text-emerald-200'), 800);
                }
            } else if (data && data.error) {
                console.warn(data.error);
            }
        } catch (e) {
            console.error('Error fetching GEX data:', e);
        } finally {
            if (spinner) spinner.classList.remove('animate-spin');
        }
    }

    // ── 3. DOM & UI Updates ───────────────────────────────────────────────────────
    function updateUI(data) {
        const s = data.summary || {};
        const spot = data.spot || 0;

        // Spot Price
        const spotEl = document.getElementById('spot-price-val');
        if (spotEl) spotEl.textContent = '₹' + Number(spot).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        // Net GEX & Regime
        const netGex = s.total_net_gex_cr || 0;
        const netEl = document.getElementById('hero-net-gex-val');
        if (netEl) {
            netEl.textContent = (netGex >= 0 ? '+' : '') + Number(netGex).toLocaleString('en-IN', { maximumFractionDigits: 1 }) + ' Cr';
            netEl.className = 'text-2xl sm:text-3xl font-mono font-black ' + (netGex >= 0 ? 'text-emerald-400' : 'text-rose-400');
        }
        const regimePill = document.getElementById('hero-regime-pill');
        if (regimePill) {
            regimePill.textContent = s.regime === 'POSITIVE_GAMMA' ? '+GEX REGIME' : (s.regime === 'NEGATIVE_GAMMA' ? '-GEX REGIME' : 'NEUTRAL GEX');
            regimePill.className = `px-2 py-0.5 rounded text-[9.5px] font-mono font-black uppercase ${s.regime_bg} ${s.regime_color} border`;
        }
        const regimeDesc = document.getElementById('hero-regime-desc');
        if (regimeDesc) regimeDesc.textContent = s.regime_desc || '';

        // Flip Point
        const flip = s.zero_gamma_flip || spot;
        const flipEl = document.getElementById('hero-flip-point-val');
        if (flipEl) flipEl.textContent = '₹' + Number(flip).toLocaleString('en-IN', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
        const diffFlip = spot - flip;
        const diffFlipEl = document.getElementById('hero-spot-diff-flip');
        if (diffFlipEl) {
            diffFlipEl.textContent = `(${diffFlip >= 0 ? '+' : ''}${diffFlip.toFixed(1)} pts)`;
            diffFlipEl.className = 'text-xs font-mono font-bold ' + (diffFlip >= 0 ? 'text-emerald-400' : 'text-rose-400');
        }

        // Call Wall & Put Wall
        const cw = s.call_wall || {};
        const pw = s.put_wall || {};
        const cwEl = document.getElementById('hero-call-wall-val');
        if (cwEl) cwEl.textContent = '₹' + Number(cw.strike || 0).toLocaleString('en-IN');
        const cwGexEl = document.getElementById('hero-call-wall-gex');
        if (cwGexEl) cwGexEl.textContent = `(+${Number(cw.gex_cr || 0).toFixed(1)} Cr)`;

        const pwEl = document.getElementById('hero-put-wall-val');
        if (pwEl) pwEl.textContent = '₹' + Number(pw.strike || 0).toLocaleString('en-IN');
        const pwGexEl = document.getElementById('hero-put-wall-gex');
        if (pwGexEl) pwGexEl.textContent = `(-${Number(pw.gex_cr || 0).toFixed(1)} Cr)`;

        // Update Blast Cards
        renderBlastCards(data.top_blast_setups || []);

        // Update Ladder Table Body
        renderLadderTable(data.ladder || [], s, spot);

        // Update Time Slider & Label
        const timeLabel = document.getElementById('current-snapshot-time');
        if (timeLabel) timeLabel.textContent = data.time || '--:--';
        if (data.available_times && data.available_times.length > 0) {
            availableTimes = data.available_times;
            const slider = document.getElementById('snapshot-time-slider');
            if (slider) {
                slider.max = availableTimes.length - 1;
                const minLbl = document.getElementById('slider-min-lbl');
                const maxLbl = document.getElementById('slider-max-lbl');
                if (minLbl) minLbl.textContent = availableTimes[0];
                if (maxLbl) maxLbl.textContent = availableTimes[availableTimes.length - 1];
            }
        }
    }

    // ── 4. Render Active Gamma Blast Cards ────────────────────────────────────────
    function renderBlastCards(setups) {
        const container = document.getElementById('blast-setups-container');
        if (!container) return;

        if (!setups || setups.length === 0) {
            container.innerHTML = `<div class="col-span-3 text-center py-6 text-slate-500 text-xs font-mono">No active gamma squeeze blast candidates detected for current snapshot.</div>`;
            return;
        }

        container.innerHTML = setups.map(s => {
            const isBull = s.direction === 'BULLISH';
            const borderCls = isBull ? 'border-emerald-500/40 bg-emerald-950/10' : 'border-rose-500/40 bg-rose-950/10';
            const badgeCls = isBull ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' : 'bg-rose-500/20 text-rose-300 border-rose-500/40';

            return `
                <div class="bg-[#101626] border ${borderCls} rounded-lg p-3 flex flex-col justify-between transition hover:border-fuchsia-500/60 shadow-md">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="px-2 py-0.5 rounded text-xs font-mono font-black ${badgeCls} border">
                                ${s.option}
                            </span>
                            <div class="flex items-center gap-1 font-mono">
                                <span class="text-[10px] text-slate-400">BLAST POP:</span>
                                <span class="text-xs font-black ${s.score >= 80 ? 'text-fuchsia-400 font-extrabold animate-pulse' : 'text-amber-400'}">
                                    ${s.score}%
                                </span>
                            </div>
                        </div>

                        <h3 class="text-xs font-bold text-white mb-1">${s.type}</h3>
                        <p class="text-[11px] text-slate-400 leading-snug mb-2.5">${s.description}</p>

                        <div class="grid grid-cols-2 gap-1.5 p-2 rounded bg-[#0b0e17] border border-slate-800 text-[11px] font-mono mb-2.5">
                            <div>
                                <span class="text-[9.5px] text-slate-500 block uppercase">Option LTP:</span>
                                <span class="text-white font-bold">₹${Number(s.ltp).toFixed(2)}</span>
                            </div>
                            <div>
                                <span class="text-[9.5px] text-slate-500 block uppercase">Delta / Gamma:</span>
                                <span class="text-cyan-300 font-bold">${Number(s.delta).toFixed(2)} / ${Number(s.gamma).toFixed(4)}</span>
                            </div>
                            <div class="col-span-2 pt-1 border-t border-slate-800/80">
                                <span class="text-[9.5px] text-amber-400/90 block uppercase">Trigger Condition:</span>
                                <span class="text-amber-200 font-bold">${s.trigger}</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between text-xs font-mono p-1.5 rounded bg-slate-900/80 border border-slate-800 mb-2">
                            <div>
                                <span class="text-[9px] text-slate-500 block">OPTION TGT (+55%):</span>
                                <span class="text-emerald-400 font-bold">₹${Number(s.target_opt).toFixed(1)}</span>
                            </div>
                            <div class="text-right">
                                <span class="text-[9px] text-slate-500 block">OPTION SL (-30%):</span>
                                <span class="text-rose-400 font-bold">₹${Number(s.stop_loss).toFixed(1)}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    // ── 5. Render Ladder Table ───────────────────────────────────────────────────
    function renderLadderTable(ladder, summary, spot) {
        const tbody = document.getElementById('ladder-table-body');
        if (!tbody) return;

        const callWallStrike = summary.call_wall?.strike || 0;
        const putWallStrike = summary.put_wall?.strike || 0;

        tbody.innerHTML = ladder.map(stk => {
            const isAtm = Math.abs(stk.spot_diff) <= 25;
            const isCallWall = (stk.strike == callWallStrike);
            const isPutWall = (stk.strike == putWallStrike);

            let wallBadge = '';
            if (isCallWall) wallBadge = `<span class="ml-1 px-1 py-0.2 rounded text-[8px] bg-rose-500/30 text-rose-300 uppercase">CE Wall</span>`;
            else if (isPutWall) wallBadge = `<span class="ml-1 px-1 py-0.2 rounded text-[8px] bg-emerald-500/30 text-emerald-300 uppercase">PE Wall</span>`;
            else if (isAtm) wallBadge = `<span class="ml-1 px-1 py-0.2 rounded text-[8px] bg-amber-500/30 text-amber-300 uppercase">ATM</span>`;

            let blastBadge = `<span class="text-slate-500 text-[10px]">${stk.blast_score}%</span>`;
            if (stk.blast_score >= 70) {
                blastBadge = `<span class="px-1.5 py-0.5 rounded text-[9.5px] font-black bg-fuchsia-500/20 text-fuchsia-300 border border-fuchsia-500/40">⚡ ${stk.blast_score}%</span>`;
            } else if (stk.blast_score >= 50) {
                blastBadge = `<span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-amber-500/20 text-amber-300">${stk.blast_score}%</span>`;
            }

            return `
                <tr class="hover:bg-slate-800/40 transition ladder-row ${isAtm ? 'bg-amber-950/20 font-bold border-y border-amber-500/40' : ''}" data-dist="${Math.abs(stk.spot_diff)}" data-score="${stk.blast_score}">
                    <td class="py-1.5 px-2 text-center font-bold">
                        <span class="${isAtm ? 'text-amber-300 text-xs font-black' : 'text-white'}">₹${Number(stk.strike).toLocaleString('en-IN')}</span>
                        ${wallBadge}
                    </td>
                    <td class="py-1.5 px-2 text-center ${stk.spot_diff >= 0 ? 'text-rose-400' : 'text-emerald-400'}">
                        ${stk.spot_diff >= 0 ? '+' : ''}${Math.round(stk.spot_diff)}
                    </td>
                    <td class="py-1.5 px-2 text-right text-slate-200">₹${Number(stk.ce_ltp).toFixed(2)}</td>
                    <td class="py-1.5 px-2 text-right text-rose-300">${Number(stk.call_oi).toLocaleString('en-IN')}</td>
                    <td class="py-1.5 px-2 text-right text-rose-400 font-bold">+${Number(stk.call_gex_cr).toFixed(1)}</td>
                    <td class="py-1.5 px-2 text-center font-black ${stk.net_gex_cr >= 0 ? 'text-emerald-400 bg-emerald-950/20' : 'text-rose-400 bg-rose-950/20'}">
                        ${stk.net_gex_cr >= 0 ? '+' : ''}${Number(stk.net_gex_cr).toFixed(1)}
                    </td>
                    <td class="py-1.5 px-2 text-left text-emerald-400 font-bold">${Number(stk.put_gex_cr).toFixed(1)}</td>
                    <td class="py-1.5 px-2 text-left text-emerald-300">${Number(stk.put_oi).toLocaleString('en-IN')}</td>
                    <td class="py-1.5 px-2 text-left text-slate-200">₹${Number(stk.pe_ltp).toFixed(2)}</td>
                    <td class="py-1.5 px-2 text-center text-slate-400 text-[10px]">
                        ${Number(stk.ce_delta).toFixed(2)} / ${Number(stk.pe_delta).toFixed(2)}
                    </td>
                    <td class="py-1.5 px-2 text-center">
                        ${blastBadge}
                    </td>
                </tr>
            `;
        }).join('');

        filterLadder(activeLadderFilter);
    }

    // ── 6. Filter Ladder Rows ────────────────────────────────────────────────────
    function filterLadder(type) {
        activeLadderFilter = type;
        const rows = document.querySelectorAll('.ladder-row');

        ['atm', 'all', 'blast'].forEach(t => {
            const btn = document.getElementById(`btn-filter-${t}`);
            if (btn) {
                if (t === type) {
                    btn.className = 'px-2 py-0.5 rounded bg-fuchsia-600 text-white font-bold transition';
                } else {
                    btn.className = 'px-2 py-0.5 rounded bg-slate-800 text-slate-300 hover:text-white transition';
                }
            }
        });

        rows.forEach(r => {
            const dist = parseFloat(r.getAttribute('data-dist') || 0);
            const score = parseFloat(r.getAttribute('data-score') || 0);

            if (type === 'atm') {
                r.style.display = dist <= 350 ? '' : 'none';
            } else if (type === 'blast') {
                r.style.display = score >= 60 ? '' : 'none';
            } else {
                r.style.display = '';
            }
        });
    }
    window.filterLadder = filterLadder;

    // ── 7. Charts Initialization & Rendering ─────────────────────────────────────
    function initCharts() {
        const ctxProfile = document.getElementById('gexProfileChart')?.getContext('2d');
        if (ctxProfile) {
            profileChart = new Chart(ctxProfile, {
                type: 'bar',
                data: { labels: [], datasets: [] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 400 },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(13, 18, 31, 0.95)',
                            borderColor: '#8b5cf6',
                            borderWidth: 1,
                            titleFont: { family: 'ui-monospace, monospace', weight: 'bold' },
                            bodyFont: { family: 'ui-monospace, monospace' },
                        }
                    },
                    scales: {
                        x: {
                            stacked: false,
                            grid: { color: 'rgba(55, 65, 81, 0.25)' },
                            ticks: { color: '#9ca3af', font: { family: 'monospace', size: 10 } }
                        },
                        y: {
                            grid: { color: 'rgba(55, 65, 81, 0.25)' },
                            ticks: {
                                color: '#9ca3af',
                                font: { family: 'monospace', size: 10 },
                                callback: val => `${val} Cr`
                            }
                        }
                    }
                }
            });
        }

        const ctxEvolution = document.getElementById('gexEvolutionChart')?.getContext('2d');
        if (ctxEvolution) {
            evolutionChart = new Chart(ctxEvolution, {
                type: 'line',
                data: { labels: [], datasets: [] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 400 },
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(13, 18, 31, 0.95)',
                            borderColor: '#38bdf8',
                            borderWidth: 1,
                            titleFont: { family: 'ui-monospace, monospace', weight: 'bold' },
                            bodyFont: { family: 'ui-monospace, monospace' },
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(55, 65, 81, 0.25)' },
                            ticks: { color: '#9ca3af', font: { family: 'monospace', size: 10 } }
                        },
                        y: {
                            type: 'linear',
                            position: 'left',
                            grid: { color: 'rgba(55, 65, 81, 0.25)' },
                            ticks: {
                                color: '#38bdf8',
                                font: { family: 'monospace', size: 10 },
                                callback: val => `₹${Math.round(val)}`
                            }
                        },
                        y1: {
                            type: 'linear',
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            ticks: {
                                color: '#f59e0b',
                                font: { family: 'monospace', size: 10 },
                                callback: val => `${Math.round(val)} Cr`
                            }
                        }
                    }
                }
            });
        }
    }

    function renderCharts(data) {
        if (!data || !data.ladder) return;

        // Filter ladder around spot for chart clarity (±350 pts)
        const spot = data.spot || 0;
        const chartStrikes = data.ladder.filter(l => Math.abs(l.spot_diff) <= 350);

        // Update Profile Chart
        if (profileChart && chartStrikes.length > 0) {
            profileChart.data.labels = chartStrikes.map(l => l.strike);
            profileChart.data.datasets = [
                {
                    label: 'Call GEX (Supply)',
                    data: chartStrikes.map(l => l.call_gex_cr),
                    backgroundColor: 'rgba(239, 68, 68, 0.75)',
                    borderColor: '#ef4444',
                    borderWidth: 1,
                    borderRadius: 3,
                },
                {
                    label: 'Put GEX (Demand)',
                    data: chartStrikes.map(l => l.put_gex_cr),
                    backgroundColor: 'rgba(16, 185, 129, 0.75)',
                    borderColor: '#10b981',
                    borderWidth: 1,
                    borderRadius: 3,
                },
                {
                    label: 'Net GEX',
                    type: 'line',
                    data: chartStrikes.map(l => l.net_gex_cr),
                    borderColor: '#f59e0b',
                    backgroundColor: '#f59e0b',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    tension: 0.2,
                }
            ];
            profileChart.update();
        }

        // Update Evolution Chart
        const timeline = data.timeline || [];
        if (evolutionChart && timeline.length > 0) {
            evolutionChart.data.labels = timeline.map(t => t.time);
            evolutionChart.data.datasets = [
                {
                    label: 'Spot Price',
                    data: timeline.map(t => t.spot),
                    borderColor: '#38bdf8',
                    backgroundColor: 'rgba(56, 189, 248, 0.1)',
                    borderWidth: 2,
                    pointRadius: 1,
                    yAxisID: 'y',
                    tension: 0.2,
                },
                {
                    label: 'Net GEX (Cr)',
                    data: timeline.map(t => t.net_gex),
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderWidth: 2,
                    pointRadius: 1,
                    yAxisID: 'y1',
                    tension: 0.2,
                }
            ];
            evolutionChart.update();
        }
    }

    // ── 8. Snapshot Scrubber / Slider ────────────────────────────────────────────
    function initSlider() {
        const slider = document.getElementById('snapshot-time-slider');
        if (!slider) return;

        slider.addEventListener('input', (e) => {
            const idx = parseInt(e.target.value);
            if (availableTimes[idx]) {
                const timeStr = availableTimes[idx];
                const lbl = document.getElementById('current-snapshot-time');
                if (lbl) lbl.textContent = timeStr;
            }
        });

        slider.addEventListener('change', (e) => {
            const idx = parseInt(e.target.value);
            if (availableTimes[idx]) {
                fetchGexData(availableTimes[idx]);
            }
        });
    }

    // ── 9. Auto-Refresh Interval Timer ───────────────────────────────────────────
    function startAutoRefreshTimer() {
        if (timerInterval) clearInterval(timerInterval);
        if (refreshSeconds <= 0) {
            const badge = document.getElementById('countdown-badge');
            if (badge) badge.textContent = 'Off';
            return;
        }

        countdownRemaining = refreshSeconds;
        timerInterval = setInterval(() => {
            countdownRemaining--;
            const badge = document.getElementById('countdown-badge');
            if (badge) badge.textContent = `${countdownRemaining}s`;

            if (countdownRemaining <= 0) {
                countdownRemaining = refreshSeconds;
                fetchGexData();
            }
        }, 1000);
    }
</script>
@endpush
