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
<div class="space-y-3 w-full px-1 sm:px-3 pb-8">

    {{-- ════════════════════════════════════════════════════════════════════════════ --}}
    {{-- TOP COMMAND BAR: DATE, EXPIRY, SPOT TICKER, ALARM STATUS, AUDIO & CONTROLS  --}}
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

            <!-- Playbook Toggle Button -->
            <button type="button" id="btn-toggle-playbook" class="flex items-center gap-1 bg-[#131a2b] hover:bg-slate-800 border border-indigo-700/60 text-indigo-300 hover:text-white rounded px-2 py-0.5 text-xs font-bold transition cursor-pointer shadow-xs" title="Toggle Decision Playbook & Trading Strategy Guide">
                <span>📖</span>
                <span class="hidden md:inline">Decision Guide</span>
            </button>
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

        <!-- Right: Spot Ticker, Alarm Settings, Auto-Refresh & Reload -->
        <div class="flex items-center gap-2 flex-wrap">
            <!-- Spot Price Pill -->
            <div class="flex items-center bg-[#131a2b] border border-slate-700/80 rounded px-2.5 py-0.5 text-xs font-mono">
                <span class="text-slate-400 text-[10px] mr-1.5 font-bold uppercase">SPOT:</span>
                <span id="spot-price-val" class="text-white font-extrabold text-sm tracking-tight">₹{{ number_format($gexData['spot'] ?? 0, 2) }}</span>
            </div>

            <!-- Audio Alarm Settings Pill Button -->
            <button type="button" id="btn-open-alarm-settings" class="flex items-center bg-[#131a2b] hover:bg-slate-800 border border-slate-700/80 rounded px-2.5 py-1 text-xs gap-1.5 transition cursor-pointer shadow-xs" title="Configure Gamma Blast Sound & Alert Triggers">
                <span id="alarm-status-dot" class="w-2 h-2 rounded-full bg-emerald-400 live-dot"></span>
                <span id="alarm-status-text" class="text-slate-200 font-bold text-[11px]">Alarm: ACTIVE</span>
                <span class="text-slate-400 text-[10px]">⚙️</span>
            </button>

            <!-- Quick Test Alarm Sound Button -->
            <button type="button" id="btn-test-quick-alarm" class="px-2 py-1 bg-amber-500/20 hover:bg-amber-500/30 border border-amber-500/50 rounded text-amber-300 text-xs font-bold transition cursor-pointer flex items-center gap-1 shadow-xs" title="Test 3-second alarm sound">
                <span>⚡ Test Alarm</span>
            </button>

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

    {{-- ════════════════════════════════════════════════════════════════════════════ --}}
    {{-- LIVE ACTIVE ALARM BANNER (Appears immediately when condition triggers)        --}}
    {{-- ════════════════════════════════════════════════════════════════════════════ --}}
    <div id="gamma-alarm-banner" class="hidden p-3 rounded-lg bg-rose-950/90 border-2 border-rose-500 text-white shadow-2xl flex items-center justify-between gap-3 animate-pulse transition">
        <div class="flex items-center gap-2.5 flex-1 min-w-0">
            <span class="text-2xl animate-bounce">🚨</span>
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-black uppercase bg-rose-600 text-white tracking-wider">GAMMA BLAST ALERT ACTIVE</span>
                    <span id="alarm-banner-time" class="text-[11px] font-mono text-rose-200"></span>
                </div>
                <div id="alarm-banner-message" class="text-xs sm:text-sm font-bold text-white mt-0.5 truncate font-mono">
                    High Probability Gamma Squeeze Triggered!
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button type="button" id="btn-banner-silence" class="px-3 py-1.5 rounded bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold font-mono transition cursor-pointer flex items-center gap-1 shadow">
                <span>🔕 Silence Alarm (ESC)</span>
            </button>
            <button type="button" id="btn-banner-settings" class="px-2.5 py-1.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-mono transition cursor-pointer" title="Alarm Settings">
                ⚙️
            </button>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════════ --}}
    {{-- COLLAPSIBLE INSTITUTIONAL TRADING PLAYBOOK & DECISION GUIDE                  --}}
    {{-- ════════════════════════════════════════════════════════════════════════════ --}}
    <div id="decision-guide-card" class="cockpit-card p-3 sm:p-4 border-indigo-900/50 shadow-xl transition-all">
        <div class="flex items-center justify-between pb-2 mb-3 border-b border-slate-800">
            <div class="flex items-center gap-2">
                <span class="p-1 rounded bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 text-xs">📖</span>
                <div>
                    <h2 class="text-xs sm:text-sm font-extrabold text-white tracking-wide">HOW TO READ THIS PAGE & MAKE THE RIGHT TRADING DECISIONS</h2>
                    <p class="text-[10px] text-slate-400 font-sans">Institutional Market Maker Gamma Playbook for Scalping & Expiry Days</p>
                </div>
            </div>
            <button type="button" id="btn-close-playbook" class="text-slate-400 hover:text-white text-xs px-2 py-0.5 rounded hover:bg-slate-800 transition cursor-pointer">
                ✕ Collapse
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3 text-xs leading-relaxed">
            <!-- Pillar 1: GEX Regimes -->
            <div class="p-3 rounded-lg bg-[#101626] border border-slate-800">
                <div class="flex items-center gap-1.5 text-emerald-400 font-bold mb-1.5">
                    <span>1. Net GEX Regimes</span>
                </div>
                <div class="space-y-1.5 text-slate-300 text-[11px]">
                    <p><strong class="text-emerald-400">+GEX Regime:</strong> Market Makers are long gamma. They buy dips and sell rallies $\rightarrow$ <em>Volatility is compressed</em>. Market stays rangebound. <span class="text-emerald-300 font-semibold">Action: Fade edges, sell straddles/strangles.</span></p>
                    <p><strong class="text-rose-400">-GEX Regime:</strong> Market Makers are short gamma. They are forced to sell selloffs and buy surges $\rightarrow$ <em>Volatility explodes</em>. <span class="text-rose-300 font-semibold">Action: Buy naked calls/puts, ride momentum breakouts.</span></p>
                </div>
            </div>

            <!-- Pillar 2: Zero-Gamma Flip -->
            <div class="p-3 rounded-lg bg-[#101626] border border-slate-800">
                <div class="flex items-center gap-1.5 text-amber-400 font-bold mb-1.5">
                    <span>2. Zero-Gamma Flip Trigger</span>
                </div>
                <div class="space-y-1.5 text-slate-300 text-[11px]">
                    <p>The exact price level where market gamma switches between positive and negative ("Line in the Sand").</p>
                    <p><strong class="text-emerald-400">Spot &gt; Flip:</strong> Calm, mean-reverting environment. Breakouts often fail.</p>
                    <p><strong class="text-rose-400">Spot &lt; Flip:</strong> Danger zone! Dealer hedging triggers aggressive cascades and fast intraday directional moves.</p>
                </div>
            </div>

            <!-- Pillar 3: Call & Put Walls -->
            <div class="p-3 rounded-lg bg-[#101626] border border-slate-800">
                <div class="flex items-center gap-1.5 text-sky-400 font-bold mb-1.5">
                    <span>3. Call & Put Gamma Walls</span>
                </div>
                <div class="space-y-1.5 text-slate-300 text-[11px]">
                    <p><strong class="text-rose-400">Call Wall:</strong> Strike with highest call open interest & gamma. Heavy resistance. <span class="text-slate-300">First test usually rejects; breach triggers massive Call short-squeeze.</span></p>
                    <p><strong class="text-emerald-400">Put Wall:</strong> Strike with highest put open interest. Heavy floor. <span class="text-slate-300">Prime zone for intraday reversal bounce; breakdown causes violent long liquidation.</span></p>
                </div>
            </div>

            <!-- Pillar 4: Gamma Blast Scalps -->
            <div class="p-3 rounded-lg bg-[#101626] border border-slate-800">
                <div class="flex items-center gap-1.5 text-fuchsia-400 font-bold mb-1.5">
                    <span>4. Gamma Blast Pop (≥75%)</span>
                </div>
                <div class="space-y-1.5 text-slate-300 text-[11px]">
                    <p>Pinpoints strikes where trapped OI + high gamma concentration forces automated dealer buying/selling.</p>
                    <p><strong class="text-fuchsia-300">When Alarm Fires:</strong> Check the Active Blast Card below. If trigger condition is met, buy the highlighted option.</p>
                    <p class="font-mono text-[10px] text-fuchsia-200">Target: +55% | Stop-Loss: -30%.</p>
                </div>
            </div>
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

    {{-- ════════════════════════════════════════════════════════════════════════════ --}}
    {{-- GAMMA BLAST ALERT AUDIT & MARKET MOVEMENT LOG TABLE                           --}}
    {{-- ════════════════════════════════════════════════════════════════════════════ --}}
    <div class="cockpit-card p-3 shadow-lg overflow-hidden border-rose-900/30">
        <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-800 flex-wrap gap-2">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500 live-dot"></span>
                <div>
                    <h3 class="text-xs font-extrabold text-white uppercase tracking-wider flex items-center gap-2">
                        <span>🚨 GAMMA BLAST TRIGGER AUDIT &amp; MARKET MOVEMENT LOG</span>
                        <span id="alert-logs-count-badge" class="px-1.5 py-0.2 rounded text-[10px] font-mono font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40">
                            {{ count($initialAlertLogs ?? []) }} Alerts Logged
                        </span>
                    </h3>
                    <p class="text-[10px] text-slate-400">Database recorded triggers based on your configured thresholds • Inspect timing vs subsequent spot price movement</p>
                </div>
            </div>
            
            <div class="flex items-center gap-2">
                <div class="flex items-center gap-1.5 bg-[#101626] border border-slate-800 px-2 py-1 rounded text-[10px] font-mono text-slate-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 live-dot"></span>
                    <span>Live Audit: ACTIVE</span>
                </div>
                <button type="button" id="btn-refresh-alert-logs" class="px-2 py-1 bg-[#131a2b] hover:bg-slate-800 text-slate-300 hover:text-white rounded border border-slate-700 text-xs font-bold font-mono transition flex items-center gap-1 cursor-pointer" title="Refresh logged alerts table">
                    <span>🔄 Refresh</span>
                </button>
                <button type="button" id="btn-clear-alert-logs" class="px-2 py-1 bg-rose-950/40 hover:bg-rose-900/60 text-rose-300 hover:text-white rounded border border-rose-800/50 text-xs font-bold font-mono transition flex items-center gap-1 cursor-pointer" title="Clear logs for selected date">
                    <span>🗑️ Clear</span>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar max-h-[380px]">
            <table class="w-full text-left font-mono text-[11px] border-collapse">
                <thead class="sticky top-0 bg-[#101626] border-b border-slate-700 text-slate-400 text-[10px] uppercase tracking-wider z-10">
                    <tr>
                        <th class="py-2 px-2 text-center whitespace-nowrap">Time</th>
                        <th class="py-2 px-2 text-left whitespace-nowrap">Alert Type</th>
                        <th class="py-2 px-2 text-left whitespace-nowrap">Strike / Target</th>
                        <th class="py-2 px-2 text-center whitespace-nowrap">Bias</th>
                        <th class="py-2 px-2 text-center whitespace-nowrap">Score / Dist</th>
                        <th class="py-2 px-2 text-right whitespace-nowrap">Spot at Trigger</th>
                        <th class="py-2 px-2 text-left whitespace-nowrap">Trigger Condition</th>
                        <th class="py-2 px-2 text-center whitespace-nowrap">Market Regime</th>
                        <th class="py-2 px-2 text-left min-w-[240px]">Alert Details</th>
                    </tr>
                </thead>
                <tbody id="alert-logs-tbody" class="divide-y divide-slate-800/60">
                    @forelse($initialAlertLogs ?? [] as $log)
                        <tr class="hover:bg-slate-800/40 transition">
                            <td class="py-1.5 px-2 text-center font-bold text-sky-300 whitespace-nowrap">
                                {{ $log->alert_time }}
                            </td>
                            <td class="py-1.5 px-2 whitespace-nowrap">
                                @if($log->alert_type === 'BLAST_SQUEEZE')
                                    <span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-fuchsia-500/20 text-fuchsia-300 border border-fuchsia-500/40">⚡ Blast Squeeze</span>
                                @elseif($log->alert_type === 'ZERO_FLIP_TEST')
                                    <span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/40">🎯 Zero-Flip Test</span>
                                @elseif($log->alert_type === 'CALL_WALL_TEST')
                                    <span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40">🧱 Call Wall Test</span>
                                @elseif($log->alert_type === 'PUT_WALL_TEST')
                                    <span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">🧱 Put Wall Test</span>
                                @elseif($log->alert_type === 'NEGATIVE_GEX_REGIME')
                                    <span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40">⚠️ -GEX Shift</span>
                                @else
                                    <span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-slate-700 text-slate-300">{{ $log->alert_type }}</span>
                                @endif
                            </td>
                            <td class="py-1.5 px-2 font-bold text-white whitespace-nowrap">
                                {{ $log->title }}
                            </td>
                            <td class="py-1.5 px-2 text-center whitespace-nowrap">
                                @if($log->direction === 'BULLISH')
                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">BULLISH</span>
                                @elseif($log->direction === 'BEARISH')
                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30">BEARISH</span>
                                @else
                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">NEUTRAL</span>
                                @endif
                            </td>
                            <td class="py-1.5 px-2 text-center whitespace-nowrap">
                                @if($log->score)
                                    <span class="font-bold text-amber-300">{{ $log->score }}%</span>
                                @elseif($log->distance_pts !== null)
                                    <span class="text-slate-300">{{ $log->distance_pts }} pts</span>
                                @else
                                    <span class="text-slate-500">—</span>
                                @endif
                            </td>
                            <td class="py-1.5 px-2 text-right font-bold text-white whitespace-nowrap">
                                ₹{{ number_format($log->spot_price, 2) }}
                            </td>
                            <td class="py-1.5 px-2 text-slate-300 text-[10.5px] whitespace-nowrap">
                                {{ $log->trigger_condition ?? '—' }}
                            </td>
                            <td class="py-1.5 px-2 text-center whitespace-nowrap">
                                @if($log->regime === 'POSITIVE_GAMMA')
                                    <span class="text-[9.5px] text-emerald-400 font-bold">+GEX</span>
                                @elseif($log->regime === 'NEGATIVE_GAMMA')
                                    <span class="text-[9.5px] text-rose-400 font-bold">-GEX</span>
                                @else
                                    <span class="text-[9.5px] text-amber-400 font-bold">BALANCED</span>
                                @endif
                            </td>
                            <td class="py-1.5 px-2 text-slate-300 text-[10.5px]">
                                {{ $log->message }}
                            </td>
                        </tr>
                    @empty
                        <tr id="alert-logs-empty">
                            <td colspan="9" class="text-center py-6 text-slate-500 font-mono text-xs">
                                No alerts triggered yet for selected session. Alerts will be recorded automatically when trigger conditions are met.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════════ --}}
    {{-- AUDIO ALARM CONFIGURATION SETTINGS MODAL                                     --}}
    {{-- ════════════════════════════════════════════════════════════════════════════ --}}
    <div id="alarm-settings-modal" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/80 backdrop-blur-xs hidden">
        <div class="cockpit-card w-full max-w-xl border-fuchsia-600/50 shadow-2xl overflow-hidden flex flex-col max-h-[92vh]">
            <!-- Modal Header -->
            <div class="p-3.5 sm:p-4 border-b border-slate-800 bg-[#101626] flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-fuchsia-600 text-white flex items-center justify-center shadow-xs">
                        <span class="text-base">🔔</span>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-sm text-white tracking-wide">Gamma Blast Audio Alert Configuration</h3>
                        <p class="text-[10px] text-slate-400">Pure Web Audio synthesizer • Instant trigger on live market conditions</p>
                    </div>
                </div>
                <button type="button" id="btn-close-alarm-modal" class="text-slate-400 hover:text-white p-1 rounded-md transition cursor-pointer text-sm font-bold">✕</button>
            </div>

            <!-- Modal Scrollable Body -->
            <div class="p-4 sm:p-5 space-y-4 overflow-y-auto custom-scrollbar text-xs">
                
                <!-- 1. Master Alarm Toggle -->
                <div class="flex items-center justify-between p-3 rounded-lg bg-[#101626] border border-slate-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-white block">Master Audio Alerts</span>
                            <span class="px-1.5 py-0.2 rounded text-[9.5px] font-mono text-slate-400 bg-slate-800/80 border border-slate-700/60">Default: Enabled</span>
                        </div>
                        <span class="text-[11px] text-slate-400 block mt-0.5">Sound continuous or single-burst alarm when chosen conditions fire</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="cfg-alarm-enabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-fuchsia-600"></div>
                    </label>
                </div>

                <!-- 2. Trigger Conditions Selection -->
                <div class="p-3 rounded-lg bg-[#101626] border border-slate-800 space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-800/80 pb-1.5">
                        <label class="font-bold text-white block uppercase text-[11px] tracking-wider text-fuchsia-400">Trigger Conditions (When To Ring Alarm)</label>
                        <span class="text-[10px] text-slate-400">Custom values &amp; strict presets to prevent frequent alarms</span>
                    </div>

                    <!-- Trigger A: High Blast Readiness Score -->
                    <div class="p-2.5 rounded bg-[#0b0e17] border border-slate-800 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <label class="flex items-center gap-2 cursor-pointer flex-1">
                                <input type="checkbox" id="cfg-blast-enabled" class="rounded border-slate-700 text-fuchsia-600 focus:ring-0 cursor-pointer">
                                <div>
                                    <span class="font-bold text-slate-200">⚡ High Blast Readiness Score</span>
                                    <span class="text-[10px] text-slate-400 block">Strike setup triggers explosive delta hedging</span>
                                </div>
                            </label>
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-mono text-slate-400 bg-slate-800/80 border border-slate-700/60 shrink-0" title="Factory Default Value">Default: ≥ 75%</span>
                        </div>
                        <div class="flex items-center justify-between gap-2 pt-1.5 border-t border-slate-800/70 flex-wrap">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Min Score:</span>
                                <div class="relative flex items-center">
                                    <span class="absolute left-2 text-[11px] font-mono text-slate-500 font-bold">≥</span>
                                    <input type="number" id="cfg-min-blast-score" min="1" max="100" step="1" value="75"
                                           class="w-24 pl-5 pr-6 py-1 bg-[#131a2b] text-amber-300 font-mono font-bold text-xs rounded border border-slate-700 focus:border-fuchsia-500 focus:outline-none text-right" title="Enter custom blast readiness score percentage">
                                    <span class="absolute right-2 text-[10px] font-mono text-slate-400 font-bold">%</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-1 flex-wrap">
                                <span class="text-[10px] text-slate-500 mr-0.5">Presets:</span>
                                <button type="button" class="preset-btn px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-slate-800 text-slate-300 hover:bg-slate-700 border border-slate-700 transition cursor-pointer" data-target="cfg-min-blast-score" data-value="75">75% (Def)</button>
                                <button type="button" class="preset-btn px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-slate-800 text-slate-300 hover:bg-slate-700 border border-slate-700 transition cursor-pointer" data-target="cfg-min-blast-score" data-value="80">80%</button>
                                <button type="button" class="preset-btn px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-amber-500/10 text-amber-300 hover:bg-amber-500/20 border border-amber-500/30 transition cursor-pointer" data-target="cfg-min-blast-score" data-value="85">85% (Strict)</button>
                                <button type="button" class="preset-btn px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-rose-500/10 text-rose-300 hover:bg-rose-500/20 border border-rose-500/30 transition cursor-pointer" data-target="cfg-min-blast-score" data-value="90">90% (Extreme)</button>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 pt-0.5">
                            <label class="flex items-center gap-1.5 text-[10.5px] text-slate-400 hover:text-slate-200 cursor-pointer select-none">
                                <input type="checkbox" id="cfg-blast-atm-only" class="rounded border-slate-700 text-fuchsia-600 focus:ring-0 cursor-pointer">
                                <span>Strict Strike Filter: Only alert for Near-ATM strikes (±200 pts), ignore far OTM</span>
                            </label>
                        </div>
                    </div>

                    <!-- Trigger B: Zero-Gamma Flip Proximity / Breach -->
                    <div class="p-2.5 rounded bg-[#0b0e17] border border-slate-800 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <label class="flex items-center gap-2 cursor-pointer flex-1">
                                <input type="checkbox" id="cfg-flip-enabled" class="rounded border-slate-700 text-fuchsia-600 focus:ring-0 cursor-pointer">
                                <div>
                                    <span class="font-bold text-slate-200">🎯 Zero-Gamma Flip Point Test</span>
                                    <span class="text-[10px] text-slate-400 block">Spot tests volatility trigger boundary</span>
                                </div>
                            </label>
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-mono text-slate-400 bg-slate-800/80 border border-slate-700/60 shrink-0" title="Factory Default Value">Default: ≤ 25 pts</span>
                        </div>
                        <div class="flex items-center justify-between gap-2 pt-1.5 border-t border-slate-800/70 flex-wrap">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Proximity:</span>
                                <div class="relative flex items-center">
                                    <span class="absolute left-2 text-[11px] font-mono text-slate-500 font-bold">≤</span>
                                    <input type="number" id="cfg-flip-proximity" min="1" max="500" step="1" value="25"
                                           class="w-24 pl-5 pr-8 py-1 bg-[#131a2b] text-amber-300 font-mono font-bold text-xs rounded border border-slate-700 focus:border-fuchsia-500 focus:outline-none text-right" title="Enter custom proximity points to Zero-Gamma Flip">
                                    <span class="absolute right-2 text-[10px] font-mono text-slate-400 font-bold">pts</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-1 flex-wrap">
                                <span class="text-[10px] text-slate-500 mr-0.5">Presets:</span>
                                <button type="button" class="preset-btn px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-rose-500/10 text-rose-300 hover:bg-rose-500/20 border border-rose-500/30 transition cursor-pointer" data-target="cfg-flip-proximity" data-value="5">5 pts (Ultra-Strict)</button>
                                <button type="button" class="preset-btn px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-amber-500/10 text-amber-300 hover:bg-amber-500/20 border border-amber-500/30 transition cursor-pointer" data-target="cfg-flip-proximity" data-value="10">10 pts (Strict)</button>
                                <button type="button" class="preset-btn px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-slate-800 text-slate-300 hover:bg-slate-700 border border-slate-700 transition cursor-pointer" data-target="cfg-flip-proximity" data-value="25">25 pts (Def)</button>
                                <button type="button" class="preset-btn px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-slate-800 text-slate-300 hover:bg-slate-700 border border-slate-700 transition cursor-pointer" data-target="cfg-flip-proximity" data-value="50">50 pts</button>
                            </div>
                        </div>
                    </div>

                    <!-- Trigger C: Call Wall / Put Wall Test -->
                    <div class="p-2.5 rounded bg-[#0b0e17] border border-slate-800 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <label class="flex items-center gap-2 cursor-pointer flex-1">
                                <input type="checkbox" id="cfg-wall-enabled" class="rounded border-slate-700 text-fuchsia-600 focus:ring-0 cursor-pointer">
                                <div>
                                    <span class="font-bold text-slate-200">🧱 Call Wall / Put Wall Test</span>
                                    <span class="text-[10px] text-slate-400 block">Spot approaches ceiling squeeze or floor bounce zone</span>
                                </div>
                            </label>
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-mono text-slate-400 bg-slate-800/80 border border-slate-700/60 shrink-0" title="Factory Default Value">Default: ≤ 25 pts</span>
                        </div>
                        <div class="flex items-center justify-between gap-2 pt-1.5 border-t border-slate-800/70 flex-wrap">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Proximity:</span>
                                <div class="relative flex items-center">
                                    <span class="absolute left-2 text-[11px] font-mono text-slate-500 font-bold">≤</span>
                                    <input type="number" id="cfg-wall-proximity" min="1" max="500" step="1" value="25"
                                           class="w-24 pl-5 pr-8 py-1 bg-[#131a2b] text-amber-300 font-mono font-bold text-xs rounded border border-slate-700 focus:border-fuchsia-500 focus:outline-none text-right" title="Enter custom proximity points to Call Wall or Put Wall">
                                    <span class="absolute right-2 text-[10px] font-mono text-slate-400 font-bold">pts</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-1 flex-wrap">
                                <span class="text-[10px] text-slate-500 mr-0.5">Presets:</span>
                                <button type="button" class="preset-btn px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-rose-500/10 text-rose-300 hover:bg-rose-500/20 border border-rose-500/30 transition cursor-pointer" data-target="cfg-wall-proximity" data-value="5">5 pts (Ultra-Strict)</button>
                                <button type="button" class="preset-btn px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-amber-500/10 text-amber-300 hover:bg-amber-500/20 border border-amber-500/30 transition cursor-pointer" data-target="cfg-wall-proximity" data-value="10">10 pts (Strict)</button>
                                <button type="button" class="preset-btn px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-slate-800 text-slate-300 hover:bg-slate-700 border border-slate-700 transition cursor-pointer" data-target="cfg-wall-proximity" data-value="25">25 pts (Def)</button>
                                <button type="button" class="preset-btn px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-slate-800 text-slate-300 hover:bg-slate-700 border border-slate-700 transition cursor-pointer" data-target="cfg-wall-proximity" data-value="40">40 pts</button>
                            </div>
                        </div>
                    </div>

                    <!-- Trigger D: Regime Shift into -GEX -->
                    <div class="p-2.5 rounded bg-[#0b0e17] border border-slate-800 flex items-center justify-between gap-2">
                        <label class="flex items-center gap-2 cursor-pointer flex-1">
                            <input type="checkbox" id="cfg-neg-gex-enabled" class="rounded border-slate-700 text-fuchsia-600 focus:ring-0 cursor-pointer">
                            <div>
                                <span class="font-bold text-slate-200">⚠️ Negative Gamma (-GEX) Regime</span>
                                <span class="text-[10px] text-slate-400 block">Alert immediately if Market Makers flip to Short Gamma</span>
                            </div>
                        </label>
                        <span class="px-1.5 py-0.5 rounded text-[10px] font-mono text-slate-400 bg-slate-800/80 border border-slate-700/60 shrink-0">Default: Off</span>
                    </div>

                    <!-- Trigger E: Alert Repeat Cooldown -->
                    <div class="p-2.5 rounded bg-[#0b0e17] border border-slate-800 space-y-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <span class="font-bold text-slate-200">⏳ Alert Repeat Cooldown</span>
                                <span class="text-[10px] text-slate-400 block">Suppress repeated sirens while market hovers in trigger zone</span>
                            </div>
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-mono text-slate-400 bg-slate-800/80 border border-slate-700/60 shrink-0">Default: 3 mins</span>
                        </div>
                        <div class="flex items-center justify-between gap-2 pt-1.5 border-t border-slate-800/70 flex-wrap">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Cooldown:</span>
                                <div class="relative flex items-center">
                                    <input type="number" id="cfg-cooldown-mins" min="0" max="60" step="1" value="3"
                                           class="w-20 pl-2 pr-10 py-1 bg-[#131a2b] text-amber-300 font-mono font-bold text-xs rounded border border-slate-700 focus:border-fuchsia-500 focus:outline-none text-right" title="Minutes between recurring alerts for the same condition">
                                    <span class="absolute right-2 text-[10px] font-mono text-slate-400 font-bold">mins</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-1 flex-wrap">
                                <span class="text-[10px] text-slate-500 mr-0.5">Presets:</span>
                                <button type="button" class="preset-btn px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-slate-800 text-slate-300 hover:bg-slate-700 border border-slate-700 transition cursor-pointer" data-target="cfg-cooldown-mins" data-value="0">0 (Every Tick)</button>
                                <button type="button" class="preset-btn px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-slate-800 text-slate-300 hover:bg-slate-700 border border-slate-700 transition cursor-pointer" data-target="cfg-cooldown-mins" data-value="1">1 min</button>
                                <button type="button" class="preset-btn px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-slate-800 text-slate-300 hover:bg-slate-700 border border-slate-700 transition cursor-pointer" data-target="cfg-cooldown-mins" data-value="3">3 mins (Def)</button>
                                <button type="button" class="preset-btn px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-amber-500/10 text-amber-300 hover:bg-amber-500/20 border border-amber-500/30 transition cursor-pointer" data-target="cfg-cooldown-mins" data-value="5">5 mins</button>
                                <button type="button" class="preset-btn px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-amber-500/10 text-amber-300 hover:bg-amber-500/20 border border-amber-500/30 transition cursor-pointer" data-target="cfg-cooldown-mins" data-value="10">10 mins</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Audio Tone Selector -->
                <div class="p-3 rounded-lg bg-[#101626] border border-slate-800 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <label class="font-bold text-white block uppercase text-[11px] tracking-wider text-fuchsia-400">Alarm Audio Tone</label>
                        <span class="px-1.5 py-0.2 rounded text-[9.5px] font-mono text-slate-400 bg-slate-800/80 border border-slate-700/60">Default: Emergency Siren</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2" id="tone-options-container">
                        <!-- Siren -->
                        <div class="tone-card p-2.5 rounded-lg border border-slate-700 bg-[#0b0e17] flex items-center justify-between cursor-pointer transition hover:border-slate-500" data-tone="siren">
                            <div class="flex items-center gap-2">
                                <input type="radio" name="alarm-tone" value="siren" class="text-fuchsia-600 focus:ring-0 cursor-pointer">
                                <div>
                                    <span class="font-bold text-slate-200 block text-xs">🚨 Emergency Siren <span class="text-[9.5px] text-fuchsia-400 font-normal">(Default)</span></span>
                                    <span class="text-[9.5px] text-slate-500 font-sans">Dual-frequency warble</span>
                                </div>
                            </div>
                            <button type="button" class="btn-preview-tone px-2 py-0.5 rounded text-[10px] bg-slate-800 hover:bg-slate-700 text-slate-300 font-mono transition" data-tone="siren">
                                Play ▶
                            </button>
                        </div>

                        <!-- Cyber Pulse -->
                        <div class="tone-card p-2.5 rounded-lg border border-slate-700 bg-[#0b0e17] flex items-center justify-between cursor-pointer transition hover:border-slate-500" data-tone="pulse">
                            <div class="flex items-center gap-2">
                                <input type="radio" name="alarm-tone" value="pulse" class="text-fuchsia-600 focus:ring-0 cursor-pointer">
                                <div>
                                    <span class="font-bold text-slate-200 block text-xs">⚡ Cyber Pulse</span>
                                    <span class="text-[9.5px] text-slate-500 font-sans">Fast digital chirp</span>
                                </div>
                            </div>
                            <button type="button" class="btn-preview-tone px-2 py-0.5 rounded text-[10px] bg-slate-800 hover:bg-slate-700 text-slate-300 font-mono transition" data-tone="pulse">
                                Play ▶
                            </button>
                        </div>

                        <!-- Chime -->
                        <div class="tone-card p-2.5 rounded-lg border border-slate-700 bg-[#0b0e17] flex items-center justify-between cursor-pointer transition hover:border-slate-500" data-tone="chime">
                            <div class="flex items-center gap-2">
                                <input type="radio" name="alarm-tone" value="chime" class="text-fuchsia-600 focus:ring-0 cursor-pointer">
                                <div>
                                    <span class="font-bold text-slate-200 block text-xs">🔔 Harmonic Chime</span>
                                    <span class="text-[9.5px] text-slate-500 font-sans">Twin musical chime</span>
                                </div>
                            </div>
                            <button type="button" class="btn-preview-tone px-2 py-0.5 rounded text-[10px] bg-slate-800 hover:bg-slate-700 text-slate-300 font-mono transition" data-tone="chime">
                                Play ▶
                            </button>
                        </div>

                        <!-- Sonar -->
                        <div class="tone-card p-2.5 rounded-lg border border-slate-700 bg-[#0b0e17] flex items-center justify-between cursor-pointer transition hover:border-slate-500" data-tone="sonar">
                            <div class="flex items-center gap-2">
                                <input type="radio" name="alarm-tone" value="sonar" class="text-fuchsia-600 focus:ring-0 cursor-pointer">
                                <div>
                                    <span class="font-bold text-slate-200 block text-xs">📡 Tactical Sonar</span>
                                    <span class="text-[9.5px] text-slate-500 font-sans">Submarine pulse</span>
                                </div>
                            </div>
                            <button type="button" class="btn-preview-tone px-2 py-0.5 rounded text-[10px] bg-slate-800 hover:bg-slate-700 text-slate-300 font-mono transition" data-tone="sonar">
                                Play ▶
                            </button>
                        </div>

                        <!-- Rapid Beep -->
                        <div class="tone-card p-2.5 rounded-lg border border-slate-700 bg-[#0b0e17] flex items-center justify-between cursor-pointer transition hover:border-slate-500 sm:col-span-2" data-tone="beep">
                            <div class="flex items-center gap-2">
                                <input type="radio" name="alarm-tone" value="beep" class="text-fuchsia-600 focus:ring-0 cursor-pointer">
                                <div>
                                    <span class="font-bold text-slate-200 block text-xs">⏰ Urgent Rapid Beep</span>
                                    <span class="text-[9.5px] text-slate-500 font-sans">Triple high-frequency bursts</span>
                                </div>
                            </div>
                            <button type="button" class="btn-preview-tone px-2 py-0.5 rounded text-[10px] bg-slate-800 hover:bg-slate-700 text-slate-300 font-mono transition" data-tone="beep">
                                Play ▶
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 4. Playback Mode & Volume -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- Playback Mode -->
                    <div class="p-3 rounded-lg bg-[#101626] border border-slate-800 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="font-bold text-white block uppercase text-[11px] tracking-wider text-fuchsia-400">Alarm Duration</label>
                            <span class="px-1.5 py-0.2 rounded text-[9.5px] font-mono text-slate-400 bg-slate-800/80 border border-slate-700/60">Default: Continuous</span>
                        </div>
                        <select id="cfg-continuous-mode" class="w-full bg-[#131a2b] text-slate-200 border border-slate-700 rounded p-1.5 text-xs font-semibold focus:outline-none cursor-pointer">
                            <option value="true">Continuous (Rings until dismissed / ESC)</option>
                            <option value="false">Single Burst (Rings 3 times then stops)</option>
                        </select>
                    </div>

                    <!-- Volume Slider -->
                    <div class="p-3 rounded-lg bg-[#101626] border border-slate-800 space-y-1.5">
                        <div class="flex items-center justify-between font-bold text-white text-[11px] uppercase tracking-wider text-fuchsia-400">
                            <div class="flex items-center gap-2">
                                <span>Alarm Volume</span>
                                <span class="px-1.5 py-0.2 rounded text-[9.5px] font-mono text-slate-400 bg-slate-800/80 border border-slate-700/60 font-normal">Default: 70%</span>
                            </div>
                            <span id="cfg-volume-label" class="font-mono text-amber-300">70%</span>
                        </div>
                        <input type="range" id="cfg-alarm-volume" min="0.1" max="1" step="0.05" value="0.7" class="w-full h-1.5 bg-slate-700 rounded-lg appearance-none cursor-pointer accent-fuchsia-500">
                    </div>
                </div>

            </div>

            <!-- Modal Footer -->
            <div class="p-3.5 bg-[#101626] border-t border-slate-800 flex flex-wrap items-center justify-between gap-2.5">
                <div class="flex items-center gap-2">
                    <button type="button" id="btn-test-modal-alarm" class="px-3 py-1.5 rounded-lg text-xs font-bold bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 flex items-center gap-1.5 transition cursor-pointer">
                        <span>⚡ Test Alarm (3s)</span>
                    </button>
                    <button type="button" id="btn-reset-alarm-defaults" class="px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 flex items-center gap-1.5 transition cursor-pointer" title="Reset all thresholds and sound settings to recommended defaults">
                        <span>↺ Reset to Defaults</span>
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <span id="alarm-reset-msg" class="text-xs text-emerald-400 font-bold hidden flex items-center gap-1">✓ Reset to defaults</span>
                    <button type="button" id="btn-save-alarm-settings" class="px-4 py-1.5 rounded-lg text-xs font-bold bg-fuchsia-600 hover:bg-fuchsia-500 text-white transition cursor-pointer shadow">
                        Save &amp; Close
                    </button>
                </div>
            </div>
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

    // ── Alarm Settings State & Persistence ───────────────────────────────────────
    const DEFAULT_ALARM_SETTINGS = {
        enabled: true,
        continuous: true,
        tone: 'siren',
        volume: 0.7,
        blastScoreEnabled: true,
        minBlastScore: 75,
        blastAtmOnly: false,
        flipEnabled: true,
        flipProximityPts: 25,
        wallEnabled: true,
        wallProximityPts: 25,
        negGexEnabled: false,
        cooldownMinutes: 3,
    };

    let alarmSettings = { ...DEFAULT_ALARM_SETTINGS };
    let isAlarmRinging = false;
    let alarmLoopInterval = null;
    let testTimeoutId = null;
    let audioCtx = null;
    let lastAlarmTriggeredAt = 0;
    let lastAlarmReasonsKey = '';

    function loadAlarmSettings() {
        try {
            const saved = localStorage.getItem('gamma_blast_alarm_settings');
            if (saved) {
                alarmSettings = { ...DEFAULT_ALARM_SETTINGS, ...JSON.parse(saved) };
            }
        } catch (e) {
            console.warn('Failed to load gamma blast alarm settings:', e);
        }
        updateAlarmStatusPill();
    }

    function saveAlarmSettings() {
        try {
            localStorage.setItem('gamma_blast_alarm_settings', JSON.stringify(alarmSettings));
        } catch (e) {
            console.warn('Failed to save gamma blast alarm settings:', e);
        }
        updateAlarmStatusPill();
    }

    function updateAlarmStatusPill() {
        const dot = document.getElementById('alarm-status-dot');
        const text = document.getElementById('alarm-status-text');
        if (alarmSettings.enabled) {
            if (dot) dot.className = 'w-2 h-2 rounded-full bg-emerald-400 live-dot';
            if (text) text.textContent = 'Alarm: ACTIVE';
        } else {
            if (dot) dot.className = 'w-2 h-2 rounded-full bg-slate-500';
            if (text) text.textContent = 'Alarm: MUTED';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadAlarmSettings();
        initPlaybookState();
        initDatePicker();
        initCharts();
        renderCharts(currentGexData);
        filterLadder('atm');
        initSlider();
        startAutoRefreshTimer();
        initAlarmUiHandlers();

        // Run initial evaluation
        if (currentGexData) {
            evaluateGammaAlarms(currentGexData);
        }

        // Global Unlock Audio Context on first click anywhere
        document.addEventListener('click', () => {
            getAudioContext();
        }, { once: true });

        // Global ESC key to silence alarm or close modal
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                silenceAlarm();
                closeAlarmModal();
            }
        });

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

        // Alert table action buttons
        document.getElementById('btn-refresh-alert-logs')?.addEventListener('click', () => {
            fetchAlertLogs();
        });

        document.getElementById('btn-clear-alert-logs')?.addEventListener('click', () => {
            clearAlertLogs();
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
            fetchAlertLogs();
        } catch (e) {
            console.error('Error fetching expiries for date:', e);
            fetchGexData();
            fetchAlertLogs();
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

        // Evaluate live alarm triggers
        evaluateGammaAlarms(data);
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

    // ── 10. Playbook Card Collapsible Initialization ──────────────────────────────
    function initPlaybookState() {
        const isHidden = localStorage.getItem('gamma_blast_playbook_hidden') === 'true';
        const card = document.getElementById('decision-guide-card');
        if (card && isHidden) {
            card.classList.add('hidden');
        }

        document.getElementById('btn-toggle-playbook')?.addEventListener('click', () => {
            if (!card) return;
            card.classList.toggle('hidden');
            localStorage.setItem('gamma_blast_playbook_hidden', card.classList.contains('hidden'));
        });

        document.getElementById('btn-close-playbook')?.addEventListener('click', () => {
            if (!card) return;
            card.classList.add('hidden');
            localStorage.setItem('gamma_blast_playbook_hidden', 'true');
        });
    }

    // ── 11. Web Audio API Synthesizer (Pure JS, Zero MP3 Downloads) ──────────────
    function getAudioContext() {
        if (!audioCtx) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (AudioContextClass) {
                audioCtx = new AudioContextClass();
            }
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        return audioCtx;
    }

    function playTone(toneName, customVol = null) {
        const ctx = getAudioContext();
        if (!ctx) return;
        const now = ctx.currentTime;
        const vol = customVol !== null ? customVol : (alarmSettings.volume ?? 0.7);

        const masterGain = ctx.createGain();
        masterGain.gain.setValueAtTime(vol, now);
        masterGain.connect(ctx.destination);

        switch(toneName) {
            case 'siren': {
                // Emergency rising-falling frequency warble
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(650, now);
                osc.frequency.linearRampToValueAtTime(950, now + 0.25);
                osc.frequency.linearRampToValueAtTime(650, now + 0.5);

                gain.gain.setValueAtTime(0.35, now);
                gain.gain.linearRampToValueAtTime(0, now + 0.55);

                osc.connect(gain);
                gain.connect(masterGain);
                osc.start(now);
                osc.stop(now + 0.55);
                break;
            }
            case 'pulse': {
                // Fast cyber alert chirp
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(1200, now);
                osc.frequency.exponentialRampToValueAtTime(400, now + 0.35);

                gain.gain.setValueAtTime(0.5, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.35);

                osc.connect(gain);
                gain.connect(masterGain);
                osc.start(now);
                osc.stop(now + 0.36);
                break;
            }
            case 'chime': {
                // Twin musical harmonic bell
                [880, 1318.5].forEach((freq, i) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, now + (i * 0.12));

                    gain.gain.setValueAtTime(0, now + (i * 0.12));
                    gain.gain.linearRampToValueAtTime(0.4, now + (i * 0.12) + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + (i * 0.12) + 0.8);

                    osc.connect(gain);
                    gain.connect(masterGain);
                    osc.start(now + (i * 0.12));
                    osc.stop(now + (i * 0.12) + 0.85);
                });
                break;
            }
            case 'sonar': {
                // Tactical submarine sonar pulse
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(1046.5, now);

                gain.gain.setValueAtTime(0.6, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.9);

                osc.connect(gain);
                gain.connect(masterGain);
                osc.start(now);
                osc.stop(now + 0.92);
                break;
            }
            case 'beep': {
                // Rapid triple alert beeps
                [0, 0.14, 0.28].forEach((delay) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(880, now + delay);

                    gain.gain.setValueAtTime(0.4, now + delay);
                    gain.gain.exponentialRampToValueAtTime(0.01, now + delay + 0.1);

                    osc.connect(gain);
                    gain.connect(masterGain);
                    osc.start(now + delay);
                    osc.stop(now + delay + 0.11);
                });
                break;
            }
        }
    }

    // ── 12. Alarm Trigger & Silence Loop Controller ──────────────────────────────
    function triggerAlarm(message) {
        if (!alarmSettings.enabled) return;

        const banner = document.getElementById('gamma-alarm-banner');
        const msgEl = document.getElementById('alarm-banner-message');
        const timeEl = document.getElementById('alarm-banner-time');

        if (msgEl) msgEl.textContent = message;
        if (timeEl) {
            const d = new Date();
            timeEl.textContent = d.toLocaleTimeString('en-IN');
        }
        if (banner) banner.classList.remove('hidden');

        if (isAlarmRinging) return;
        isAlarmRinging = true;

        // Play initial audio tone
        playTone(alarmSettings.tone);

        if (alarmSettings.continuous) {
            alarmLoopInterval = setInterval(() => {
                if (isAlarmRinging) {
                    playTone(alarmSettings.tone);
                }
            }, 1100);
        } else {
            let count = 1;
            alarmLoopInterval = setInterval(() => {
                count++;
                if (count <= 3 && isAlarmRinging) {
                    playTone(alarmSettings.tone);
                } else {
                    clearInterval(alarmLoopInterval);
                    alarmLoopInterval = null;
                    isAlarmRinging = false;
                }
            }, 1100);
        }
    }

    function silenceAlarm() {
        isAlarmRinging = false;
        if (alarmLoopInterval) {
            clearInterval(alarmLoopInterval);
            alarmLoopInterval = null;
        }
        if (testTimeoutId) {
            clearTimeout(testTimeoutId);
            testTimeoutId = null;
        }
        const banner = document.getElementById('gamma-alarm-banner');
        if (banner) banner.classList.add('hidden');
        // Engaging cooldown upon silence avoids re-ringing on next auto-refresh
        lastAlarmTriggeredAt = Date.now();
    }

    function testAlarmSound(durationSeconds = 3) {
        silenceAlarm();
        triggerAlarm(`⚡ TEST ALARM: ${alarmSettings.tone.toUpperCase()} tone preview at ${Math.round(alarmSettings.volume * 100)}% volume`);
        testTimeoutId = setTimeout(() => {
            silenceAlarm();
        }, durationSeconds * 1000);
    }

    // ── 13. Evaluate Alarm Trigger Conditions Against Live GEX Payload ───────────
    function evaluateGammaAlarms(data) {
        if (!data || !data.summary || !alarmSettings.enabled) return;

        const spot = parseFloat(data.spot || 0);
        const summary = data.summary || {};
        const flip = parseFloat(summary.zero_gamma_flip || spot);
        const callWall = parseFloat(summary.call_wall?.strike || 0);
        const putWall = parseFloat(summary.put_wall?.strike || 0);
        const regime = summary.regime || 'NEUTRAL';
        const setups = data.top_blast_setups || [];

        let reasons = [];
        let alertItems = [];

        // Condition A: High Blast Readiness Score
        if (alarmSettings.blastScoreEnabled && setups.length > 0) {
            const minScore = parseFloat(alarmSettings.minBlastScore ?? DEFAULT_ALARM_SETTINGS.minBlastScore);
            const candidates = alarmSettings.blastAtmOnly
                ? setups.filter(s => Math.abs(parseFloat(s.strike) - spot) <= 200)
                : setups;

            if (candidates.length > 0) {
                const top = candidates[0];
                if (top && top.score >= minScore) {
                    const msg = `${top.option} Blast Squeeze Score: ${top.score}% (${top.direction}) — ${top.trigger}`;
                    reasons.push(msg);
                    alertItems.push({
                        trade_date: selectedDate,
                        alert_time: (new Date()).toLocaleTimeString('en-GB'),
                        symbol: selectedSymbol,
                        expiry: selectedExpiry,
                        spot_price: spot,
                        alert_type: 'BLAST_SQUEEZE',
                        title: `${top.option} Gamma Blast`,
                        strike: top.strike,
                        option_type: (top.option && top.option.includes('CE')) ? 'CE' : 'PE',
                        direction: top.direction,
                        score: top.score,
                        distance_pts: Math.abs(spot - parseFloat(top.strike || spot)),
                        trigger_condition: top.trigger,
                        zero_gamma_flip: flip,
                        call_wall: callWall,
                        put_wall: putWall,
                        net_gex_cr: summary.total_net_gex_cr,
                        regime: regime,
                        message: msg,
                        settings_snapshot: alarmSettings,
                        meta: top,
                    });
                }
            }
        }

        // Condition B: Zero-Gamma Flip Proximity / Test
        if (alarmSettings.flipEnabled) {
            const diffFlip = Math.abs(spot - flip);
            const flipProx = parseFloat(alarmSettings.flipProximityPts ?? DEFAULT_ALARM_SETTINGS.flipProximityPts);
            if (diffFlip <= flipProx) {
                const msg = `Spot ₹${spot.toFixed(1)} testing Zero-Flip boundary ₹${flip.toFixed(1)} (dist: ${diffFlip.toFixed(1)} pts)`;
                reasons.push(msg);
                alertItems.push({
                    trade_date: selectedDate,
                    alert_time: (new Date()).toLocaleTimeString('en-GB'),
                    symbol: selectedSymbol,
                    expiry: selectedExpiry,
                    spot_price: spot,
                    alert_type: 'ZERO_FLIP_TEST',
                    title: `Zero-Gamma Flip Boundary Test`,
                    strike: flip,
                    direction: spot >= flip ? 'BULLISH' : 'BEARISH',
                    distance_pts: diffFlip,
                    trigger_condition: `Testing Zero-Flip ₹${flip.toFixed(1)} (dist: ${diffFlip.toFixed(1)} pts)`,
                    zero_gamma_flip: flip,
                    call_wall: callWall,
                    put_wall: putWall,
                    net_gex_cr: summary.total_net_gex_cr,
                    regime: regime,
                    message: msg,
                    settings_snapshot: alarmSettings,
                });
            }
        }

        // Condition C: Wall Proximity / Test
        if (alarmSettings.wallEnabled) {
            const wallProx = parseFloat(alarmSettings.wallProximityPts ?? DEFAULT_ALARM_SETTINGS.wallProximityPts);
            if (callWall > 0 && Math.abs(spot - callWall) <= wallProx) {
                const distWall = Math.abs(spot - callWall);
                const msg = `Spot ₹${spot.toFixed(1)} testing Call Wall ₹${callWall} (Ceiling Squeeze Alert)`;
                reasons.push(msg);
                alertItems.push({
                    trade_date: selectedDate,
                    alert_time: (new Date()).toLocaleTimeString('en-GB'),
                    symbol: selectedSymbol,
                    expiry: selectedExpiry,
                    spot_price: spot,
                    alert_type: 'CALL_WALL_TEST',
                    title: `Call Wall Ceiling Test (₹${callWall})`,
                    strike: callWall,
                    option_type: 'CE',
                    direction: 'BULLISH',
                    distance_pts: distWall,
                    trigger_condition: `Spot testing Call Wall ceiling ₹${callWall}`,
                    zero_gamma_flip: flip,
                    call_wall: callWall,
                    put_wall: putWall,
                    net_gex_cr: summary.total_net_gex_cr,
                    regime: regime,
                    message: msg,
                    settings_snapshot: alarmSettings,
                });
            } else if (putWall > 0 && Math.abs(spot - putWall) <= wallProx) {
                const distWall = Math.abs(spot - putWall);
                const msg = `Spot ₹${spot.toFixed(1)} testing Put Wall ₹${putWall} (Floor Demand Alert)`;
                reasons.push(msg);
                alertItems.push({
                    trade_date: selectedDate,
                    alert_time: (new Date()).toLocaleTimeString('en-GB'),
                    symbol: selectedSymbol,
                    expiry: selectedExpiry,
                    spot_price: spot,
                    alert_type: 'PUT_WALL_TEST',
                    title: `Put Wall Floor Demand Test (₹${putWall})`,
                    strike: putWall,
                    option_type: 'PE',
                    direction: 'BEARISH',
                    distance_pts: distWall,
                    trigger_condition: `Spot testing Put Wall floor ₹${putWall}`,
                    zero_gamma_flip: flip,
                    call_wall: callWall,
                    put_wall: putWall,
                    net_gex_cr: summary.total_net_gex_cr,
                    regime: regime,
                    message: msg,
                    settings_snapshot: alarmSettings,
                });
            }
        }

        // Condition D: Regime Shift into Negative Gamma
        if (alarmSettings.negGexEnabled && regime === 'NEGATIVE_GAMMA') {
            const msg = `Negative Gamma (-GEX) Regime Active (${summary.total_net_gex_cr} Cr) — Volatility Expansion`;
            reasons.push(msg);
            alertItems.push({
                trade_date: selectedDate,
                alert_time: (new Date()).toLocaleTimeString('en-GB'),
                symbol: selectedSymbol,
                expiry: selectedExpiry,
                spot_price: spot,
                alert_type: 'NEGATIVE_GEX_REGIME',
                title: `Negative Gamma (-GEX) Regime Shift`,
                direction: 'BEARISH',
                trigger_condition: `Net GEX flipped negative (${summary.total_net_gex_cr} Cr)`,
                zero_gamma_flip: flip,
                call_wall: callWall,
                put_wall: putWall,
                net_gex_cr: summary.total_net_gex_cr,
                regime: regime,
                message: msg,
                settings_snapshot: alarmSettings,
            });
        }

        if (reasons.length > 0) {
            const currentReasonsKey = reasons.slice().sort().join(' | ');
            const now = Date.now();
            const cooldownMins = parseFloat(alarmSettings.cooldownMinutes ?? DEFAULT_ALARM_SETTINGS.cooldownMinutes);
            const cooldownMs = (isNaN(cooldownMins) ? 3 : cooldownMins) * 60 * 1000;

            const isUnderCooldown = cooldownMs > 0 &&
                (now - lastAlarmTriggeredAt < cooldownMs) &&
                (currentReasonsKey === lastAlarmReasonsKey);

            if (!isUnderCooldown) {
                lastAlarmTriggeredAt = now;
                lastAlarmReasonsKey = currentReasonsKey;
                triggerAlarm(reasons.join(' | '));
                
                // Persist each triggered alert to server database & update audit table
                alertItems.forEach(item => logAlertToServer(item));
            } else {
                // Keep banner text current if banner is open without re-sounding audio loop
                const banner = document.getElementById('gamma-alarm-banner');
                if (banner && !banner.classList.contains('hidden')) {
                    const msgEl = document.getElementById('alarm-banner-message');
                    if (msgEl) msgEl.textContent = reasons.join(' | ');
                }
            }
        } else {
            lastAlarmReasonsKey = '';
        }
    }

    // ── 14. Server Database Alert Logging & Table Management ─────────────────────
    async function logAlertToServer(payload) {
        try {
            const resp = await fetch('/api/gamma-blast/alerts/log', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });
            const data = await resp.json();
            if (data.success && data.logged && data.log) {
                prependAlertLogRow(data.log);
            }
        } catch (e) {
            console.error('Failed to log gamma blast alert:', e);
        }
    }

    function prependAlertLogRow(log) {
        const tbody = document.getElementById('alert-logs-tbody');
        if (!tbody) return;

        const emptyRow = document.getElementById('alert-logs-empty');
        if (emptyRow) emptyRow.remove();

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-800/40 transition bg-fuchsia-950/20';

        let typeBadge = `<span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-slate-700 text-slate-300">${log.alert_type}</span>`;
        if (log.alert_type === 'BLAST_SQUEEZE') {
            typeBadge = '<span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-fuchsia-500/20 text-fuchsia-300 border border-fuchsia-500/40">⚡ Blast Squeeze</span>';
        } else if (log.alert_type === 'ZERO_FLIP_TEST') {
            typeBadge = '<span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/40">🎯 Zero-Flip Test</span>';
        } else if (log.alert_type === 'CALL_WALL_TEST') {
            typeBadge = '<span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40">🧱 Call Wall Test</span>';
        } else if (log.alert_type === 'PUT_WALL_TEST') {
            typeBadge = '<span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">🧱 Put Wall Test</span>';
        } else if (log.alert_type === 'NEGATIVE_GEX_REGIME') {
            typeBadge = '<span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40">⚠️ -GEX Shift</span>';
        }

        let biasBadge = '<span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">NEUTRAL</span>';
        if (log.direction === 'BULLISH') {
            biasBadge = '<span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">BULLISH</span>';
        } else if (log.direction === 'BEARISH') {
            biasBadge = '<span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30">BEARISH</span>';
        }

        let scoreDist = '—';
        if (log.score) {
            scoreDist = `<span class="font-bold text-amber-300">${Number(log.score).toFixed(1)}%</span>`;
        } else if (log.distance_pts !== null && log.distance_pts !== undefined) {
            scoreDist = `<span class="text-slate-300">${Number(log.distance_pts).toFixed(1)} pts</span>`;
        }

        let regimeBadge = '<span class="text-[9.5px] text-amber-400 font-bold">BALANCED</span>';
        if (log.regime === 'POSITIVE_GAMMA') {
            regimeBadge = '<span class="text-[9.5px] text-emerald-400 font-bold">+GEX</span>';
        } else if (log.regime === 'NEGATIVE_GAMMA') {
            regimeBadge = '<span class="text-[9.5px] text-rose-400 font-bold">-GEX</span>';
        }

        tr.innerHTML = `
            <td class="py-1.5 px-2 text-center font-bold text-sky-300 whitespace-nowrap">${log.alert_time}</td>
            <td class="py-1.5 px-2 whitespace-nowrap">${typeBadge}</td>
            <td class="py-1.5 px-2 font-bold text-white whitespace-nowrap">${escapeHtml(log.title)}</td>
            <td class="py-1.5 px-2 text-center whitespace-nowrap">${biasBadge}</td>
            <td class="py-1.5 px-2 text-center whitespace-nowrap">${scoreDist}</td>
            <td class="py-1.5 px-2 text-right font-bold text-white whitespace-nowrap">₹${Number(log.spot_price).toFixed(2)}</td>
            <td class="py-1.5 px-2 text-slate-300 text-[10.5px] whitespace-nowrap">${escapeHtml(log.trigger_condition || '—')}</td>
            <td class="py-1.5 px-2 text-center whitespace-nowrap">${regimeBadge}</td>
            <td class="py-1.5 px-2 text-slate-300 text-[10.5px]">${escapeHtml(log.message)}</td>
        `;

        tbody.insertBefore(tr, tbody.firstChild);

        // Update badge count
        const countBadge = document.getElementById('alert-logs-count-badge');
        if (countBadge) {
            const currentCount = tbody.querySelectorAll('tr:not(#alert-logs-empty)').length;
            countBadge.textContent = `${currentCount} Alerts Logged`;
        }
    }

    async function fetchAlertLogs() {
        try {
            const resp = await fetch(`/api/gamma-blast/alerts?date=${selectedDate}&symbol=${selectedSymbol}`);
            const data = await resp.json();
            if (data.success) {
                renderAlertLogs(data.logs || []);
            }
        } catch (e) {
            console.error('Failed to fetch gamma blast alert logs:', e);
        }
    }

    function renderAlertLogs(logs) {
        const tbody = document.getElementById('alert-logs-tbody');
        const countBadge = document.getElementById('alert-logs-count-badge');
        if (!tbody) return;

        if (countBadge) {
            countBadge.textContent = `${logs.length} Alerts Logged`;
        }

        if (!logs || logs.length === 0) {
            tbody.innerHTML = `
                <tr id="alert-logs-empty">
                    <td colspan="9" class="text-center py-6 text-slate-500 font-mono text-xs">
                        No alerts triggered yet for selected session. Alerts will be recorded automatically when trigger conditions are met.
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = '';
        logs.forEach(log => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-800/40 transition';

            let typeBadge = `<span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-slate-700 text-slate-300">${log.alert_type}</span>`;
            if (log.alert_type === 'BLAST_SQUEEZE') {
                typeBadge = '<span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-fuchsia-500/20 text-fuchsia-300 border border-fuchsia-500/40">⚡ Blast Squeeze</span>';
            } else if (log.alert_type === 'ZERO_FLIP_TEST') {
                typeBadge = '<span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/40">🎯 Zero-Flip Test</span>';
            } else if (log.alert_type === 'CALL_WALL_TEST') {
                typeBadge = '<span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40">🧱 Call Wall Test</span>';
            } else if (log.alert_type === 'PUT_WALL_TEST') {
                typeBadge = '<span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">🧱 Put Wall Test</span>';
            } else if (log.alert_type === 'NEGATIVE_GEX_REGIME') {
                typeBadge = '<span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40">⚠️ -GEX Shift</span>';
            }

            let biasBadge = '<span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">NEUTRAL</span>';
            if (log.direction === 'BULLISH') {
                biasBadge = '<span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">BULLISH</span>';
            } else if (log.direction === 'BEARISH') {
                biasBadge = '<span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30">BEARISH</span>';
            }

            let scoreDist = '—';
            if (log.score) {
                scoreDist = `<span class="font-bold text-amber-300">${Number(log.score).toFixed(1)}%</span>`;
            } else if (log.distance_pts !== null && log.distance_pts !== undefined) {
                scoreDist = `<span class="text-slate-300">${Number(log.distance_pts).toFixed(1)} pts</span>`;
            }

            let regimeBadge = '<span class="text-[9.5px] text-amber-400 font-bold">BALANCED</span>';
            if (log.regime === 'POSITIVE_GAMMA') {
                regimeBadge = '<span class="text-[9.5px] text-emerald-400 font-bold">+GEX</span>';
            } else if (log.regime === 'NEGATIVE_GAMMA') {
                regimeBadge = '<span class="text-[9.5px] text-rose-400 font-bold">-GEX</span>';
            }

            tr.innerHTML = `
                <td class="py-1.5 px-2 text-center font-bold text-sky-300 whitespace-nowrap">${log.alert_time}</td>
                <td class="py-1.5 px-2 whitespace-nowrap">${typeBadge}</td>
                <td class="py-1.5 px-2 font-bold text-white whitespace-nowrap">${escapeHtml(log.title)}</td>
                <td class="py-1.5 px-2 text-center whitespace-nowrap">${biasBadge}</td>
                <td class="py-1.5 px-2 text-center whitespace-nowrap">${scoreDist}</td>
                <td class="py-1.5 px-2 text-right font-bold text-white whitespace-nowrap">₹${Number(log.spot_price).toFixed(2)}</td>
                <td class="py-1.5 px-2 text-slate-300 text-[10.5px] whitespace-nowrap">${escapeHtml(log.trigger_condition || '—')}</td>
                <td class="py-1.5 px-2 text-center whitespace-nowrap">${regimeBadge}</td>
                <td class="py-1.5 px-2 text-slate-300 text-[10.5px]">${escapeHtml(log.message)}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    async function clearAlertLogs() {
        if (!confirm(`Are you sure you want to clear all logged alerts for ${selectedDate}?`)) {
            return;
        }
        try {
            const resp = await fetch(`/api/gamma-blast/alerts?date=${selectedDate}&symbol=${selectedSymbol}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json'
                }
            });
            const data = await resp.json();
            if (data.success) {
                renderAlertLogs([]);
            }
        } catch (e) {
            console.error('Failed to clear gamma blast alert logs:', e);
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ── 15. Alarm Settings Modal UI Handlers ──────────────────────────────────────
    function initAlarmUiHandlers() {
        const modal = document.getElementById('alarm-settings-modal');

        // Open Modal
        document.getElementById('btn-open-alarm-settings')?.addEventListener('click', () => {
            populateModalFromSettings();
            if (modal) modal.classList.remove('hidden');
        });

        document.getElementById('btn-banner-settings')?.addEventListener('click', () => {
            populateModalFromSettings();
            if (modal) modal.classList.remove('hidden');
        });

        // Close Modal
        document.getElementById('btn-close-alarm-modal')?.addEventListener('click', () => {
            closeAlarmModal();
        });

        // Banner silence
        document.getElementById('btn-banner-silence')?.addEventListener('click', () => {
            silenceAlarm();
        });

        // Quick test buttons
        document.getElementById('btn-test-quick-alarm')?.addEventListener('click', () => {
            testAlarmSound(3);
        });

        document.getElementById('btn-test-modal-alarm')?.addEventListener('click', () => {
            testAlarmSound(3);
        });

        // Reset to Defaults
        document.getElementById('btn-reset-alarm-defaults')?.addEventListener('click', () => {
            alarmSettings = { ...DEFAULT_ALARM_SETTINGS };
            populateModalFromSettings();
            saveAlarmSettings();

            const msgEl = document.getElementById('alarm-reset-msg');
            if (msgEl) {
                msgEl.classList.remove('hidden');
                setTimeout(() => {
                    msgEl.classList.add('hidden');
                }, 2500);
            }
        });

        // Preset button quick-fill handlers
        document.querySelectorAll('.preset-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = btn.getAttribute('data-target');
                const val = btn.getAttribute('data-value');
                const input = document.getElementById(targetId);
                if (input) {
                    input.value = val;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    highlightActivePresets();
                }
            });
        });

        // Highlight preset buttons when inputs change
        ['cfg-min-blast-score', 'cfg-flip-proximity', 'cfg-wall-proximity', 'cfg-cooldown-mins'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', () => {
                highlightActivePresets();
            });
        });

        // Tone preview buttons
        document.querySelectorAll('.btn-preview-tone').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const tone = btn.getAttribute('data-tone');
                playTone(tone);
            });
        });

        // Tone card click to select radio
        document.querySelectorAll('.tone-card').forEach(card => {
            card.addEventListener('click', () => {
                const tone = card.getAttribute('data-tone');
                const radio = card.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
                highlightSelectedToneCard(tone);
            });
        });

        // Volume range slider
        const volInput = document.getElementById('cfg-alarm-volume');
        const volLabel = document.getElementById('cfg-volume-label');
        if (volInput && volLabel) {
            volInput.addEventListener('input', (e) => {
                volLabel.textContent = `${Math.round(e.target.value * 100)}%`;
            });
        }

        // Save button
        document.getElementById('btn-save-alarm-settings')?.addEventListener('click', () => {
            saveModalToSettings();
            saveAlarmSettings();
            closeAlarmModal();
        });
    }

    function highlightActivePresets() {
        document.querySelectorAll('.preset-btn').forEach(btn => {
            const targetId = btn.getAttribute('data-target');
            const val = btn.getAttribute('data-value');
            const input = document.getElementById(targetId);
            if (input && String(input.value).trim() === String(val).trim()) {
                btn.classList.add('ring-1', 'ring-fuchsia-400', 'font-bold');
            } else {
                btn.classList.remove('ring-1', 'ring-fuchsia-400');
            }
        });
    }

    function closeAlarmModal() {
        const modal = document.getElementById('alarm-settings-modal');
        if (modal) modal.classList.add('hidden');
    }

    function populateModalFromSettings() {
        const enabledEl = document.getElementById('cfg-alarm-enabled');
        if (enabledEl) enabledEl.checked = !!alarmSettings.enabled;

        const blastEnabledEl = document.getElementById('cfg-blast-enabled');
        if (blastEnabledEl) blastEnabledEl.checked = !!alarmSettings.blastScoreEnabled;

        const minScoreEl = document.getElementById('cfg-min-blast-score');
        if (minScoreEl) minScoreEl.value = Number(alarmSettings.minBlastScore ?? DEFAULT_ALARM_SETTINGS.minBlastScore);

        const blastAtmOnlyEl = document.getElementById('cfg-blast-atm-only');
        if (blastAtmOnlyEl) blastAtmOnlyEl.checked = !!alarmSettings.blastAtmOnly;

        const flipEnabledEl = document.getElementById('cfg-flip-enabled');
        if (flipEnabledEl) flipEnabledEl.checked = !!alarmSettings.flipEnabled;

        const flipProxEl = document.getElementById('cfg-flip-proximity');
        if (flipProxEl) flipProxEl.value = Number(alarmSettings.flipProximityPts ?? DEFAULT_ALARM_SETTINGS.flipProximityPts);

        const wallEnabledEl = document.getElementById('cfg-wall-enabled');
        if (wallEnabledEl) wallEnabledEl.checked = !!alarmSettings.wallEnabled;

        const wallProxEl = document.getElementById('cfg-wall-proximity');
        if (wallProxEl) wallProxEl.value = Number(alarmSettings.wallProximityPts ?? DEFAULT_ALARM_SETTINGS.wallProximityPts);

        const negGexEl = document.getElementById('cfg-neg-gex-enabled');
        if (negGexEl) negGexEl.checked = !!alarmSettings.negGexEnabled;

        const cooldownEl = document.getElementById('cfg-cooldown-mins');
        if (cooldownEl) cooldownEl.value = Number(alarmSettings.cooldownMinutes ?? DEFAULT_ALARM_SETTINGS.cooldownMinutes);

        const contEl = document.getElementById('cfg-continuous-mode');
        if (contEl) contEl.value = alarmSettings.continuous ? 'true' : 'false';

        const volEl = document.getElementById('cfg-alarm-volume');
        const volLabel = document.getElementById('cfg-volume-label');
        if (volEl) volEl.value = alarmSettings.volume ?? DEFAULT_ALARM_SETTINGS.volume;
        if (volLabel) volLabel.textContent = `${Math.round((alarmSettings.volume ?? DEFAULT_ALARM_SETTINGS.volume) * 100)}%`;

        // Radio tone
        const toneToSelect = alarmSettings.tone || DEFAULT_ALARM_SETTINGS.tone;
        const radio = document.querySelector(`input[name="alarm-tone"][value="${toneToSelect}"]`);
        if (radio) radio.checked = true;
        highlightSelectedToneCard(toneToSelect);
        highlightActivePresets();
    }

    function highlightSelectedToneCard(selectedTone) {
        document.querySelectorAll('.tone-card').forEach(card => {
            const tone = card.getAttribute('data-tone');
            if (tone === selectedTone) {
                card.classList.add('border-fuchsia-500', 'bg-fuchsia-950/20');
                card.classList.remove('border-slate-700', 'bg-[#0b0e17]');
            } else {
                card.classList.remove('border-fuchsia-500', 'bg-fuchsia-950/20');
                card.classList.add('border-slate-700', 'bg-[#0b0e17]');
            }
        });
    }

    function saveModalToSettings() {
        alarmSettings.enabled = document.getElementById('cfg-alarm-enabled')?.checked ?? true;
        alarmSettings.blastScoreEnabled = document.getElementById('cfg-blast-enabled')?.checked ?? true;

        const minScoreVal = parseFloat(document.getElementById('cfg-min-blast-score')?.value);
        alarmSettings.minBlastScore = isNaN(minScoreVal) ? DEFAULT_ALARM_SETTINGS.minBlastScore : Math.min(100, Math.max(1, minScoreVal));

        alarmSettings.blastAtmOnly = document.getElementById('cfg-blast-atm-only')?.checked ?? false;

        alarmSettings.flipEnabled = document.getElementById('cfg-flip-enabled')?.checked ?? true;
        const flipProxVal = parseFloat(document.getElementById('cfg-flip-proximity')?.value);
        alarmSettings.flipProximityPts = isNaN(flipProxVal) ? DEFAULT_ALARM_SETTINGS.flipProximityPts : Math.max(1, flipProxVal);

        alarmSettings.wallEnabled = document.getElementById('cfg-wall-enabled')?.checked ?? true;
        const wallProxVal = parseFloat(document.getElementById('cfg-wall-proximity')?.value);
        alarmSettings.wallProximityPts = isNaN(wallProxVal) ? DEFAULT_ALARM_SETTINGS.wallProximityPts : Math.max(1, wallProxVal);

        alarmSettings.negGexEnabled = document.getElementById('cfg-neg-gex-enabled')?.checked ?? false;

        const cooldownVal = parseFloat(document.getElementById('cfg-cooldown-mins')?.value);
        alarmSettings.cooldownMinutes = isNaN(cooldownVal) ? DEFAULT_ALARM_SETTINGS.cooldownMinutes : Math.max(0, cooldownVal);

        alarmSettings.continuous = document.getElementById('cfg-continuous-mode')?.value === 'true';
        alarmSettings.volume = parseFloat(document.getElementById('cfg-alarm-volume')?.value || 0.7);

        const checkedTone = document.querySelector('input[name="alarm-tone"]:checked');
        if (checkedTone) {
            alarmSettings.tone = checkedTone.value;
        }
    }
</script>
@endpush
