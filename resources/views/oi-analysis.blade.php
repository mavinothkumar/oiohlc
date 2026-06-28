@extends('layouts.app')

@section('title')
    HLC
@endsection

@section('content')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;900&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        display: ['Outfit', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                }
            }
        }
    </script>
    <style>
        :root {
            --bg: #060a13;
            --bg2: #0c1220;
            --card: #111a2e;
            --card2: #162038;
            --border: #1e2d4a;
            --border2: #2a3f6a;
            --fg: #e2e8f0;
            --muted: #64748b;
            --dim: #475569;
            --bull: #00e676;
            --bull-dim: rgba(0,230,118,0.12);
            --bear: #ff1744;
            --bear-dim: rgba(255,23,68,0.12);
            --amber: #ffab00;
            --cyan: #00bcd4;
        }
        * { box-sizing: border-box; }
        body {
            background: var(--bg);
            color: var(--fg);
            font-family: 'Outfit', sans-serif;
            margin: 0;
            min-height: 100vh;
        }
        /* Subtle grid background */
        body::before {
            content: '';
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image:
                linear-gradient(rgba(30,45,74,0.25) 1px, transparent 1px),
                linear-gradient(90deg, rgba(30,45,74,0.25) 1px, transparent 1px);
            background-size: 60px 60px;
        }
        .relative-z { position: relative; z-index: 1; }

        /* Heatmap grid */
        .hm-wrap { overflow: auto; max-height: 620px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg2); }
        .hm-grid {
            display: grid;
            gap: 1px;
            min-width: max-content;
        }
        .hm-cell {
            display: flex; align-items: center; justify-content: center;
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            transition: transform 0.1s, box-shadow 0.15s;
            cursor: crosshair;
            position: relative;
            min-width: 48px;
            min-height: 26px;
        }
        .hm-cell.data:hover {
            transform: scale(1.25);
            z-index: 10;
            box-shadow: 0 0 12px rgba(255,255,255,0.15);
            border-radius: 3px;
        }
        .hm-cell.strike-label {
            justify-content: flex-end;
            padding-right: 8px;
            font-size: 11px;
            font-weight: 600;
            color: var(--muted);
            min-width: 68px;
            position: sticky;
            left: 0;
            z-index: 5;
            background: var(--bg2);
        }
        .hm-cell.strike-label.is-atm {
            color: var(--amber);
            font-weight: 700;
        }
        .hm-cell.time-label {
            font-size: 10px;
            font-weight: 600;
            color: var(--dim);
            position: sticky;
            top: 0;
            z-index: 6;
            background: var(--bg2);
            min-height: 24px;
        }
        .hm-cell.time-label.skip-zone {
            color: var(--dim);
            opacity: 0.5;
        }
        .hm-cell.time-label.analysis-zone {
            color: var(--cyan);
        }
        .hm-cell.oi-bar-cell {
            min-width: 38px;
            padding: 0 2px;
            justify-content: flex-start;
            position: sticky;
            left: 68px;
            z-index: 4;
            background: var(--bg2);
        }
        .oi-mini-bar {
            height: 14px;
            border-radius: 2px;
            transition: width 0.3s;
        }
        /* Skip zone overlay (first 10-15 min) */
        .hm-cell.data.skip {
            opacity: 0.35;
        }
        /* Massive change pulse */
        .hm-cell.data.massive {
            animation: cellPulse 2s ease-in-out infinite;
        }
        @keyframes cellPulse {
            0%, 100% { box-shadow: inset 0 0 0 1px rgba(255,255,255,0.1); }
            50% { box-shadow: inset 0 0 0 2px rgba(255,255,255,0.5); }
        }

        /* Direction banner */
        .dir-banner {
            border-radius: 10px;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.4s;
        }
        .dir-banner.bullish {
            background: linear-gradient(135deg, rgba(0,230,118,0.08), rgba(0,230,118,0.02));
            border: 1px solid rgba(0,230,118,0.25);
            animation: bullGlow 3s ease-in-out infinite;
        }
        .dir-banner.bearish {
            background: linear-gradient(135deg, rgba(255,23,68,0.08), rgba(255,23,68,0.02));
            border: 1px solid rgba(255,23,68,0.25);
            animation: bearGlow 3s ease-in-out infinite;
        }
        .dir-banner.sideways {
            background: linear-gradient(135deg, rgba(255,171,0,0.08), rgba(255,171,0,0.02));
            border: 1px solid rgba(255,171,0,0.25);
        }
        .dir-banner.nodata {
            background: var(--card);
            border: 1px solid var(--border);
        }
        @keyframes bullGlow {
            0%,100% { box-shadow: 0 0 20px rgba(0,230,118,0.05); }
            50% { box-shadow: 0 0 40px rgba(0,230,118,0.15); }
        }
        @keyframes bearGlow {
            0%,100% { box-shadow: 0 0 20px rgba(255,23,68,0.05); }
            50% { box-shadow: 0 0 40px rgba(255,23,68,0.15); }
        }

        /* Net flow bars */
        .flow-bar {
            height: 20px;
            border-radius: 3px;
            transition: width 0.3s, background 0.3s;
            min-width: 2px;
        }

        /* Tooltip */
        #hm-tooltip {
            position: fixed;
            z-index: 100;
            pointer-events: none;
            background: rgba(12,18,32,0.96);
            border: 1px solid var(--border2);
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 12px;
            line-height: 1.7;
            box-shadow: 0 8px 32px rgba(0,0,0,0.5);
            max-width: 320px;
            opacity: 0;
            transition: opacity 0.12s;
            backdrop-filter: blur(8px);
        }
        #hm-tooltip.visible { opacity: 1; }

        /* Tab buttons */
        .tab-btn {
            padding: 6px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--muted);
            transition: all 0.2s;
        }
        .tab-btn:hover { border-color: var(--border2); color: var(--fg); }
        .tab-btn.active {
            background: var(--card2);
            border-color: var(--cyan);
            color: var(--cyan);
        }

        /* Reversal card */
        .reversal-card {
            border-radius: 8px;
            padding: 10px 14px;
            border: 1px solid;
            animation: revPulse 2.5s ease-in-out infinite;
        }
        .reversal-card.bull-rev {
            background: var(--bull-dim);
            border-color: rgba(0,230,118,0.3);
        }
        .reversal-card.bear-rev {
            background: var(--bear-dim);
            border-color: rgba(255,23,68,0.3);
        }
        @keyframes revPulse {
            0%,100% { opacity: 0.85; }
            50% { opacity: 1; }
        }

        /* Change card */
        .change-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            transition: border-color 0.2s, transform 0.15s;
        }
        .change-card:hover {
            border-color: var(--border2);
            transform: translateY(-1px);
        }

        /* Build badge */
        .build-badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: 0.3px;
        }
        .build-badge.lb { background: rgba(0,230,118,0.15); color: var(--bull); }
        .build-badge.sc { background: rgba(0,230,118,0.08); color: #66bb6a; }
        .build-badge.sb { background: rgba(255,23,68,0.15); color: var(--bear); }
        .build-badge.lu { background: rgba(255,23,68,0.08); color: #ef5350; }

        /* Legend */
        .legend-dot {
            width: 12px; height: 12px; border-radius: 2px; display: inline-block;
        }

        /* Inputs */
        .fi {
            background: var(--card);
            border: 1px solid var(--border);
            color: var(--fg);
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 13px;
            font-family: 'JetBrains Mono', monospace;
            outline: none;
            transition: border-color 0.2s;
        }
        .fi:focus { border-color: var(--cyan); }
        .fi::-webkit-calendar-picker-indicator { filter: invert(0.7); }

        /* No data */
        .no-data {
            text-align: center;
            padding: 80px 20px;
            color: var(--dim);
            font-size: 16px;
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg2); }
        ::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--dim); }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>

<div class="relative-z max-w-[1600px] mx-auto px-4 py-6">

    <!-- ═══ FILTER BAR ═══ -->
    <form method="GET" action="{{ route('oi-analysis.index') }}" class="flex flex-wrap items-end gap-4 mb-6">
        <div>
            <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">DATE</label>
            <input type="date" name="date" value="{{ $date }}" class="fi w-[160px]">
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">EXPIRY</label>
            <input type="date" name="expiry" value="{{ $expiry }}" class="fi w-[160px]">
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1.5" style="color:var(--muted)">UNTIL TIME</label>
            <select name="time" class="fi w-[120px]">
                @foreach(['10:15','10:30','11:00','11:30','12:00','12:30','13:00','13:30','14:00','14:30','15:00','15:25'] as $t)
                    <option value="{{ $t }}" {{ $endTime === $t ? 'selected' : '' }}>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="fi px-6 py-2 font-semibold text-sm cursor-pointer hover:border-[var(--cyan)] transition-colors" style="color:var(--cyan)">
            Analyze
        </button>
        <div class="ml-auto text-right">
            <div class="text-xs" style="color:var(--dim)">SPOT</div>
            <div class="font-mono font-bold text-lg" style="color:var(--fg)">{{ number_format($latestSpot, 2) }}</div>
        </div>
        <div class="text-right">
            <div class="text-xs" style="color:var(--dim)">ATM</div>
            <div class="font-mono font-bold text-lg" style="color:var(--amber)">{{ $atmStrike }}</div>
        </div>
    </form>

    @if($timeSlots->isEmpty())
        <div class="no-data">
            <div class="text-3xl mb-3" style="color:var(--border2)">No Data Found</div>
            <div>No option chain data available for {{ $date }} / expiry {{ $expiry }}.</div>
        </div>
    @else

        <!-- ═══ DIRECTION SIGNAL ═══ -->
        <div id="dir-banner" class="dir-banner mb-6 {{ match($direction['signal']) {
        'BULLISH' => 'bullish',
        'BEARISH' => 'bearish',
        default => $direction['signal'] === 'NO_DATA' ? 'nodata' : 'sideways',
    } }}">
            <div class="text-3xl font-black font-display tracking-tight" style="min-width:180px">
                @if($direction['signal'] === 'BULLISH')
                    <span style="color:var(--bull)">&#9650; BULLISH</span>
                @elseif($direction['signal'] === 'BEARISH')
                    <span style="color:var(--bear)">&#9660; BEARISH</span>
                @elseif($direction['signal'] === 'SIDEWAYS')
                    <span style="color:var(--amber)">&#9654; SIDEWAYS</span>
                @else
                    <span style="color:var(--dim)">-- NO DATA</span>
                @endif
            </div>
            <div class="h-10 w-px" style="background:var(--border)"></div>
            <div class="font-mono text-sm">
                <div>Confidence: <span class="font-bold text-base">{{ $direction['conf'] ?? 0 }}%</span></div>
            </div>
            <div class="h-10 w-px" style="background:var(--border)"></div>
            <div class="font-mono text-xs leading-relaxed" style="color:var(--muted)">
                <div>Bull Score: <span style="color:var(--bull)">{{ number_format($direction['bull'] ?? 0) }}</span></div>
                <div>Bear Score: <span style="color:var(--bear)">{{ number_format($direction['bear'] ?? 0) }}</span></div>
                <div>CE SB hits: {{ $direction['ceSb'] ?? 0 }} &nbsp;|&nbsp; PE SB hits: {{ $direction['peSb'] ?? 0 }}</div>
            </div>
            @if(count($reversals) > 0)
                <div class="h-10 w-px" style="background:var(--border)"></div>
                <div class="text-xs font-semibold" style="color:var(--amber)">
                    &#9888; {{ count($reversals) }} Pattern Reversal{{ count($reversals) > 1 ? 's' : '' }} Detected
                </div>
            @endif
        </div>

        <!-- ═══ PATTERN REVERSALS ═══ -->
        @if(count($reversals) > 0)
            <div class="mb-5">
                <div class="text-xs font-bold mb-2 tracking-wider" style="color:var(--amber)">PATTERN REVERSALS (09:20-09:45 &#8594; 09:45-10:00)</div>
                <div class="flex flex-wrap gap-2">
                    @foreach($reversals as $rev)
                        <div class="reversal-card {{ $rev['dir'] === 'BULLISH' ? 'bull-rev' : 'bear-rev' }}">
                            <span class="font-mono font-bold text-sm">{{ $rev['strike'] }}</span>
                            <span class="text-xs mx-1" style="color:var(--muted)">{{ $rev['type'] }}</span>
                            <span class="build-badge {{ Str::startsWith($rev['from'], 'Long') ? 'lb' : 'sb' }}">{{ $rev['from'] }}</span>
                            <span class="text-xs mx-1" style="color:var(--dim)">&#8594;</span>
                            <span class="build-badge {{ Str::startsWith($rev['to'], 'Long') ? 'lb' : 'sc' }}">{{ $rev['to'] }}</span>
                            <span class="text-xs font-bold ml-1" style="color:{{ $rev['dir'] === 'BULLISH' ? 'var(--bull)' : 'var(--bear)' }}">
                    {{ $rev['dir'] === 'BULLISH' ? '&#9650;' : '&#9660;' }} {{ $rev['dir'] }}
                </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- ═══ HEATMAP SECTION ═══ -->
        <div class="mb-5">
            <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold tracking-wider" style="color:var(--muted)">OI HEATMAP</span>
                    <div class="flex gap-1 ml-3">
                        <button class="tab-btn active" data-view="combined">Combined</button>
                        <button class="tab-btn" data-view="ce">CE Only</button>
                        <button class="tab-btn" data-view="pe">PE Only</button>
                    </div>
                </div>
                <div class="flex items-center gap-4 text-xs" style="color:var(--muted)">
                    <span><span class="legend-dot" style="background:var(--bull)"></span> Bullish</span>
                    <span><span class="legend-dot" style="background:var(--bear)"></span> Bearish</span>
                    <span><span class="legend-dot" style="background:var(--amber)"></span> ATM</span>
                    <span style="opacity:0.4">| First 10-15 min dimmed (skip zone)</span>
                    <span><span class="legend-dot" style="border:1px solid rgba(255,255,255,0.4);background:transparent"></span> Pulsing = Massive change</span>
                </div>
            </div>

            <!-- Heatmap Grid -->
            <div class="hm-wrap" id="hm-wrap">
                <div class="hm-grid" id="hm-grid"
                    style="grid-template-columns: 68px 38px repeat({{ $timeSlots->count() }}, 48px); grid-template-rows: 24px repeat({{ $strikes->count() }}, 26px);">
                    <!-- Corner -->
                    <div class="hm-cell" style="background:var(--bg2)"></div>
                    <div class="hm-cell" style="background:var(--bg2);font-size:8px;color:var(--dim)">OI</div>
                    <!-- Time headers -->
                    @foreach($timeSlots as $t)
                        <div class="hm-cell time-label {{ $t < '09:30' ? 'skip-zone' : ($t <= '10:00' ? 'analysis-zone' : '') }}">
                            {{ $t }}
                        </div>
                    @endforeach

                    <!-- Strike rows -->
                    @php $latestT = $timeSlots->last(); @endphp
                    @foreach($strikes as $s)
                        <div class="hm-cell strike-label {{ (float)$s == $atmStrike ? 'is-atm' : '' }}">
                            {{ $s }}
                        </div>
                        <!-- OI mini bar -->
                        @php
                            $ceOi = $heatmap[$s][$latestT]['CE']['oi'] ?? 0;
                            $peOi = $heatmap[$s][$latestT]['PE']['oi'] ?? 0;
                            $totalOi = $ceOi + $peOi;
                        @endphp
                        <div class="hm-cell oi-bar-cell">
                            @if($topOiCe[0]['oi'] ?? 0 > 0)
                                <div class="oi-mini-bar" style="width:{{ round($totalOi / max($topOiCe[0]['oi'], $topOiPe[0]['oi'] ?? 1) * 34) }}px; background:{{ $ceOi > $peOi ? 'var(--cyan)' : 'var(--amber)' }};opacity:0.7"></div>
                            @endif
                        </div>
                        <!-- Data cells -->
                        @foreach($timeSlots as $t)
                            <div class="hm-cell data {{ $t < '09:30' ? 'skip' : '' }}"
                                data-s="{{ $s }}" data-t="{{ $t }}"></div>
                        @endforeach
                    @endforeach
                </div>
            </div>

            <!-- Net OI Flow Bar -->
            <div class="mt-3">
                <div class="text-xs font-bold mb-1.5 tracking-wider" style="color:var(--muted)">NET OI FLOW PER SLOT</div>
                <div class="flex items-end gap-px" style="height:32px" id="flow-bars">
                    @php
                        $maxAbsFlow = collect($netFlow)->pluck('net')->map(fn($v) => abs($v))->max() ?: 1;
                    @endphp
                    @foreach($netFlow as $f)
                        <div class="flex flex-col items-center flex-1" style="min-width:48px">
                            <div class="flow-bar w-full" style="
                            height:{{ max(2, abs($f['net']) / $maxAbsFlow * 28) }}px;
                            background:{{ $f['net'] >= 0 ? 'var(--bull)' : 'var(--bear)' }};
                            opacity:{{ 0.3 + abs($f['net']) / $maxAbsFlow * 0.7 }};
                        "></div>
                            <div class="text-[9px] font-mono mt-0.5" style="color:var(--dim)">{{ $f['time'] }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-between text-[9px] font-mono mt-0.5" style="color:var(--dim)">
                    <span>Bearish &#9660;</span>
                    <span>Bullish &#9650;</span>
                </div>
            </div>
        </div>

        <!-- ═══ BOTTOM: TOP OI + RECENT CHANGES ═══ -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

            <!-- Top CE OI -->
            <div class="lg:col-span-3 rounded-lg p-4" style="background:var(--card);border:1px solid var(--border)">
                <div class="text-xs font-bold tracking-wider mb-3" style="color:var(--cyan)">TOP CE OI (Latest: {{ $latestT }})</div>
                <div style="height:260px"><canvas id="chart-ce-oi"></canvas></div>
            </div>

            <!-- Recent Massive Changes -->
            <div class="lg:col-span-6 rounded-lg p-4" style="background:var(--card);border:1px solid var(--border)">
                <div class="flex items-center justify-between mb-3">
                    <div class="text-xs font-bold tracking-wider" style="color:var(--amber)">RECENT MASSIVE CHANGES</div>
                    <div class="flex gap-1" id="rc-tabs">
                        @foreach(['5min','10min','15min'] as $w)
                            <button class="tab-btn text-xs {{ $w === '5min' ? 'active' : '' }}" data-rc="{{ $w }}">{{ $w }}</button>
                        @endforeach
                    </div>
                </div>
                <div id="rc-content" class="grid grid-cols-1 sm:grid-cols-2 gap-2" style="min-height:240px">
                    @foreach(($recentChanges['5min'] ?? []) as $ch)
                        <div class="change-card rc-card" data-rc="5min">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-mono font-bold text-sm">{{ $ch['strike'] }}</span>
                                <span class="text-xs font-bold px-2 py-0.5 rounded" style="background:{{ $ch['type'] === 'CE' ? 'rgba(0,188,212,0.12);color:var(--cyan)' : 'rgba(255,171,0,0.12);color:var(--amber)' }}">{{ $ch['type'] }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                            <span class="font-mono text-lg font-bold" style="color:{{ $ch['total_diff'] > 0 ? 'var(--bull)' : 'var(--bear)' }}">
                                {{ $ch['total_diff'] > 0 ? '+' : '' }}{{ number_format($ch['total_diff']) }}
                            </span>
                                @if($ch['dominant'])
                                    <span class="build-badge {{ match($ch['dominant']) {
                                    'Long Build' => 'lb', 'Short Cover' => 'sc',
                                    'Short Build' => 'sb', 'Long Unwind' => 'lu', default => ''
                                } }}">{{ $ch['dominant'] }}</span>
                                @endif
                            </div>
                            <div class="text-[10px] font-mono mt-1" style="color:var(--dim)">OI Change: {{ number_format($ch['oi_change']) }}</div>
                        </div>
                    @endforeach
                    @if(empty($recentChanges['5min'] ?? []))
                        <div class="col-span-2 text-center py-10 text-sm" style="color:var(--dim)">No massive changes in this window</div>
                    @endif
                </div>
            </div>

            <!-- Top PE OI -->
            <div class="lg:col-span-3 rounded-lg p-4" style="background:var(--card);border:1px solid var(--border)">
                <div class="text-xs font-bold tracking-wider mb-3" style="color:var(--amber)">TOP PE OI (Latest: {{ $latestT }})</div>
                <div style="height:260px"><canvas id="chart-pe-oi"></canvas></div>
            </div>
        </div>

    @endif {{-- end timeSlots check --}}

</div>

<!-- Tooltip -->
<div id="hm-tooltip"></div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // ── Data ──────────────────────────────────────────────────────
        const DATA = @json([
        'timeSlots' => $timeSlots,
        'strikes'   => $strikes,
        'heatmap'   => $heatmap ?? [],
        'topOiCe'   => $topOiCe,
        'topOiPe'   => $topOiPe,
        'recentChanges' => $recentChanges ?? [],
        'direction' => $direction,
    ]);

        if (!DATA.timeSlots || DATA.timeSlots.length === 0) return;

        const { timeSlots, strikes, heatmap, topOiCe, topOiPe, recentChanges } = DATA;
        let currentView = 'combined';

        // ── Build Type → Sentiment Map ────────────────────────────────
        // CE: LB=+bull, SC=+bull, SB=-bear, LU=-bear
        // PE: LB=-bear, SC=-bear, SB=+bull, LU=+bull
        const BULLISH_BUILDS_CE = ['Long Build', 'Short Cover'];
        const BEARISH_BUILDS_CE = ['Short Build', 'Long Unwind'];
        // For PE the polarity flips
        const BULLISH_BUILDS_PE = ['Short Build', 'Long Unwind'];
        const BEARISH_BUILDS_PE = ['Long Build', 'Short Cover'];

        function isBullish(buildUp, type) {
            if (!buildUp) return null;
            return type === 'CE' ? BULLISH_BUILDS_CE.includes(buildUp) : BULLISH_BUILDS_PE.includes(buildUp);
        }

        // ── Pre-compute max log diff_oi for normalization ─────────────
        let maxLogDiff = 1;
        for (const s of strikes) {
            for (const t of timeSlots) {
                const cell = heatmap[s]?.[t];
                if (!cell) continue;
                for (const type of ['CE', 'PE']) {
                    const d = cell[type];
                    if (d && d.diff_oi) {
                        maxLogDiff = Math.max(maxLogDiff, Math.log(Math.abs(d.diff_oi) + 1));
                    }
                }
            }
        }

        // ── Compute massive threshold (90th percentile of |diff_oi|) ─
        const allAbsDiff = [];
        for (const s of strikes) {
            for (const t of timeSlots) {
                const cell = heatmap[s]?.[t];
                if (!cell) continue;
                for (const type of ['CE', 'PE']) {
                    const d = cell[type];
                    if (d && d.diff_oi) allAbsDiff.push(Math.abs(d.diff_oi));
                }
            }
        }
        allAbsDiff.sort((a, b) => a - b);
        const massiveThreshold = allAbsDiff[Math.floor(allAbsDiff.length * 0.9)] || 0;

        // ── Color Heatmap Cells ───────────────────────────────────────
        function colorCells() {
            const cells = document.querySelectorAll('.hm-cell.data');
            cells.forEach(cell => {
                const s = cell.dataset.s;
                const t = cell.dataset.t;
                const entry = heatmap[s]?.[t];
                if (!entry) { cell.style.background = 'transparent'; return; }

                let r = 6, g = 10, b = 19, a = 0; // base: var(--bg)
                let isMassive = false;

                if (currentView === 'ce' || currentView === 'combined') {
                    const d = entry.CE;
                    if (d && d.build_up) {
                        const intensity = Math.log(Math.abs(d.diff_oi) + 1) / maxLogDiff;
                        const alpha = 0.12 + intensity * 0.78;
                        const bull = isBullish(d.build_up, 'CE');
                        if (bull === true) { r = 0; g = 230; b = 118; a = alpha; }
                        else if (bull === false) { r = 255; g = 23; b = 68; a = alpha; }
                        if (Math.abs(d.diff_oi) >= massiveThreshold) isMassive = true;
                    }
                }

                if (currentView === 'pe' || currentView === 'combined') {
                    const d = entry.PE;
                    if (d && d.build_up) {
                        const intensity = Math.log(Math.abs(d.diff_oi) + 1) / maxLogDiff;
                        const alpha = 0.12 + intensity * 0.78;
                        const bull = isBullish(d.build_up, 'PE');
                        // For combined, blend colors
                        if (currentView === 'combined' && a > 0) {
                            // Already has CE color — blend
                            if (bull === true) { g = Math.min(255, g + 60); a = Math.min(0.95, a + alpha * 0.3); }
                            else if (bull === false) { r = Math.min(255, r + 60); a = Math.min(0.95, a + alpha * 0.3); }
                        } else if (a === 0) {
                            if (bull === true) { r = 0; g = 230; b = 118; a = alpha; }
                            else if (bull === false) { r = 255; g = 23; b = 68; a = alpha; }
                        }
                        if (Math.abs(d.diff_oi) >= massiveThreshold) isMassive = true;
                    }
                }

                cell.style.background = a > 0 ? `rgba(${r},${g},${b},${a})` : 'transparent';
                cell.classList.toggle('massive', isMassive && !cell.classList.contains('skip'));
            });
        }

        colorCells();

        // ── View Tabs ─────────────────────────────────────────────────
        document.querySelectorAll('.tab-btn[data-view]').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn[data-view]').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentView = btn.dataset.view;
                colorCells();
            });
        });

        // ── Tooltip ───────────────────────────────────────────────────
        const tooltip = document.getElementById('hm-tooltip');
        const wrap = document.getElementById('hm-wrap');

        function buildBadgeClass(buildUp) {
            if (!buildUp) return '';
            return buildUp === 'Long Build' ? 'lb' : buildUp === 'Short Cover' ? 'sc' : buildUp === 'Short Build' ? 'sb' : 'lu';
        }

        document.querySelectorAll('.hm-cell.data').forEach(cell => {
            cell.addEventListener('mouseenter', (e) => {
                const s = cell.dataset.s;
                const t = cell.dataset.t;
                const entry = heatmap[s]?.[t];
                if (!entry) return;

                let html = `<div style="font-weight:700;font-size:14px;margin-bottom:6px;color:var(--fg)">${s} @ ${t}</div>`;

                for (const type of ['CE', 'PE']) {
                    const d = entry[type];
                    const typeColor = type === 'CE' ? 'var(--cyan)' : 'var(--amber)';
                    html += `<div style="color:${typeColor};font-weight:700;font-size:11px;margin-top:6px">${type}</div>`;
                    if (d) {
                        html += `<div style="color:var(--fg)">OI: ${d.oi.toLocaleString()} &nbsp; Diff: <span style="color:${d.diff_oi >= 0 ? 'var(--bull)' : 'var(--bear)'}">${d.diff_oi >= 0 ? '+' : ''}${d.diff_oi.toLocaleString()}</span></div>`;
                        html += `<div style="color:var(--fg)">LTP: ${d.ltp} &nbsp; Vol: ${d.volume.toLocaleString()}</div>`;
                        if (d.build_up) {
                            html += `<span class="build-badge ${buildBadgeClass(d.build_up)}">${d.build_up}</span>`;
                        } else {
                            html += `<span style="color:var(--dim);font-size:10px">No build detected</span>`;
                        }
                    } else {
                        html += `<div style="color:var(--dim)">No data</div>`;
                    }
                }

                tooltip.innerHTML = html;
                tooltip.classList.add('visible');
                positionTooltip(e);
            });

            cell.addEventListener('mousemove', positionTooltip);
            cell.addEventListener('mouseleave', () => {
                tooltip.classList.remove('visible');
            });
        });

        function positionTooltip(e) {
            let x = e.clientX + 16;
            let y = e.clientY - 10;
            const tw = tooltip.offsetWidth;
            const th = tooltip.offsetHeight;
            if (x + tw > window.innerWidth - 10) x = e.clientX - tw - 16;
            if (y + th > window.innerHeight - 10) y = window.innerHeight - th - 10;
            if (y < 10) y = 10;
            tooltip.style.left = x + 'px';
            tooltip.style.top = y + 'px';
        }

        // ── Recent Changes Tabs ───────────────────────────────────────
        const rcData = recentChanges;
        const rcContent = document.getElementById('rc-content');

        document.querySelectorAll('#rc-tabs .tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#rc-tabs .tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                renderRcCards(btn.dataset.rc);
            });
        });

        function renderRcCards(window) {
            const items = rcData[window] || [];
            if (items.length === 0) {
                rcContent.innerHTML = '<div class="col-span-2 text-center py-10 text-sm" style="color:var(--dim)">No massive changes in this window</div>';
                return;
            }
            rcContent.innerHTML = items.map(ch => `
            <div class="change-card">
                <div class="flex items-center justify-between mb-1">
                    <span class="font-mono font-bold text-sm">${ch.strike}</span>
                    <span class="text-xs font-bold px-2 py-0.5 rounded" style="background:${ch.type === 'CE' ? 'rgba(0,188,212,0.12);color:var(--cyan)' : 'rgba(255,171,0,0.12);color:var(--amber)'}">${ch.type}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-mono text-lg font-bold" style="color:${ch.total_diff > 0 ? 'var(--bull)' : 'var(--bear)'}">
                        ${ch.total_diff > 0 ? '+' : ''}${ch.total_diff.toLocaleString()}
                    </span>
                    ${ch.dominant ? `<span class="build-badge ${buildBadgeClass(ch.dominant)}">${ch.dominant}</span>` : ''}
                </div>
                <div class="text-[10px] font-mono mt-1" style="color:var(--dim)">OI Change: ${ch.oi_change.toLocaleString()}</div>
            </div>
        `).join('');
        }

        // ── Chart.js: Top OI ──────────────────────────────────────────
        const chartFont = { family: "'JetBrains Mono', monospace", size: 10 };
        const gridColor = 'rgba(255,255,255,0.04)';
        const tickColor = '#64748b';

        function makeOiChart(canvasId, data, barColor, borderColor) {
            const ctx = document.getElementById(canvasId);
            if (!ctx || data.length === 0) return;
            const maxOi = data[0].oi;
            const bgColors = data.map(d => {
                const intensity = 0.3 + (d.oi / maxOi) * 0.7;
                if (barColor === 'cyan') return `rgba(0,188,212,${intensity})`;
                return `rgba(255,171,0,${intensity})`;
            });

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.strike),
                    datasets: [{
                        data: data.map(d => d.oi),
                        backgroundColor: bgColors,
                        borderColor: borderColor,
                        borderWidth: 1,
                        borderRadius: 3,
                        barThickness: 16,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => `OI: ${ctx.raw.toLocaleString()}`,
                                afterLabel: (ctx) => {
                                    const d = data[ctx.dataIndex];
                                    return `Diff: ${d.diff_oi >= 0 ? '+' : ''}${d.diff_oi.toLocaleString()}\nBuild: ${d.build_up || 'N/A'}`;
                                }
                            },
                            titleFont: chartFont,
                            bodyFont: chartFont,
                            backgroundColor: 'rgba(12,18,32,0.95)',
                            borderColor: 'rgba(42,53,85,0.8)',
                            borderWidth: 1,
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: gridColor },
                            ticks: { color: tickColor, font: chartFont, callback: v => (v/1000).toFixed(0) + 'K' },
                            border: { color: 'transparent' },
                        },
                        y: {
                            grid: { display: false },
                            ticks: { color: tickColor, font: { ...chartFont, weight: 600 } },
                            border: { color: 'transparent' },
                        }
                    }
                }
            });
        }

        makeOiChart('chart-ce-oi', topOiCe, 'cyan', 'rgba(0,188,212,0.6)');
        makeOiChart('chart-pe-oi', topOiPe, 'amber', 'rgba(255,171,0,0.6)');

        // ── Keyboard Navigation ───────────────────────────────────────
        const dataCells = Array.from(document.querySelectorAll('.hm-cell.data'));
        let focusedIdx = -1;

        document.addEventListener('keydown', (e) => {
            if (!dataCells.length) return;
            const cols = timeSlots.length;

            if (e.key === 'ArrowRight') { focusedIdx = Math.min(focusedIdx + 1, dataCells.length - 1); e.preventDefault(); }
            else if (e.key === 'ArrowLeft') { focusedIdx = Math.max(focusedIdx - 1, 0); e.preventDefault(); }
            else if (e.key === 'ArrowDown') { focusedIdx = Math.min(focusedIdx + cols, dataCells.length - 1); e.preventDefault(); }
            else if (e.key === 'ArrowUp') { focusedIdx = Math.max(focusedIdx - cols, 0); e.preventDefault(); }
            else if (e.key === 'Escape') { focusedIdx = -1; tooltip.classList.remove('visible'); return; }
            else return;

            dataCells.forEach(c => c.style.outline = 'none');
            if (focusedIdx >= 0) {
                const cell = dataCells[focusedIdx];
                cell.style.outline = '2px solid var(--cyan)';
                cell.style.outlineOffset = '-1px';
                cell.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                // Trigger tooltip
                cell.dispatchEvent(new MouseEvent('mouseenter', { clientX: cell.getBoundingClientRect().right + 10, clientY: cell.getBoundingClientRect().top }));
            }
        });
    });
</script>
@endsection
