@props(['format' => 'd M Y, h:i:s'])

@php
    $currentRoute = request()->route()?->getName() ?? '';

    // Route group active helpers
    $isMonitorsActive = request()->routeIs([
        'nifty.unified.*', 'nifty.weightage.*', 'options.breadth.*',
        'trending-oi.*', 'live.strike.chart', 'delta.neutral', 'order-flow-depth.*', 'gamma-blast.*'
    ]);

    $isAnalyticsActive = request()->routeIs([
        'nifty.matrix.*', 'strategy.premium.*', 'strategy.multi.*',
        'matching.*', 'strike.matcher', 'options-analytics.*', 'straddle.*',
        'option-chain-view', 'combined.premium.*', 'vega.analysis', 'greek.analysis',
        'multi.strike.*', 'option-chain.analysis', 'fii-dii.*', 'expiry.oi.volume.*',
        'live.straddle.*'
    ]);

    $isStrategiesActive = request()->routeIs([
        'trading-journal.*', 'quant-strategy.*', 'test.trading-simulator*',
        'backtest.*', 'backtests.*', 'strangle.profit', 'entries.*', 'nifty.unified.backtest'
    ]);

    $isToolsActive = request()->routeIs([
        'trend.*', 'daily_trend.*', 'strike.optimizer', 'mid-point.*',
        'trading.prev-level-match*', 'oi-buildup.*', 'buildups.*', 'build-up.*',
        'buildup.*', 'oi-diff-live.*', 'option-chain-diff*', 'option-chain.build-up*',
        'strangle.analyzer', 'strike.detail', 'hlc.*'
    ]);
@endphp

<!-- Main Enterprise Navigation Bar -->
<nav class="w-full bg-white select-none relative border-b border-slate-200 shadow-2xs">
    <div class="w-full px-3 sm:px-4 lg:px-6">
        <div class="flex items-center justify-between h-14 gap-3">
            
            <!-- LEFT: Brand & Unified "All Modules" Directory -->
            <div class="flex items-center gap-3 sm:gap-4 shrink-0">
                <!-- Brand / Logo -->
                <a href="{{ route('home') }}" class="flex items-center gap-2 group focus:outline-none shrink-0" title="Home Dashboard">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-slate-900 to-indigo-950 flex items-center justify-center shadow-xs border border-slate-800 group-hover:border-indigo-500 transition-colors">
                        <svg class="w-4 h-4 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>
                            <polyline points="16 7 22 7 22 13"></polyline>
                        </svg>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-base font-bold text-slate-900 tracking-tight group-hover:text-indigo-600 transition-colors whitespace-nowrap">OI OHLC</span>
                        <span class="text-[9px] font-mono font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">PRO</span>
                    </div>
                </a>

                <!-- ════════════════════════════════════════════════════════════════════
                     UNIFIED FULL MEGA-MENU (ALL MODULES & PLATFORM DIRECTORY)
                ════════════════════════════════════════════════════════════════════ -->
                <div class="relative group shrink-0">
                    <button type="button"
                            class="whitespace-nowrap inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-bold transition-all border border-slate-200/90 bg-slate-50 hover:bg-slate-100 text-slate-800 hover:text-indigo-600 shadow-2xs focus:outline-none"
                            title="Open Comprehensive Platform Directory">
                        <svg class="w-4 h-4 text-indigo-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect width="7" height="7" x="3" y="3" rx="1.5"/>
                            <rect width="7" height="7" x="14" y="3" rx="1.5"/>
                            <rect width="7" height="7" x="14" y="14" rx="1.5"/>
                            <rect width="7" height="7" x="3" y="14" rx="1.5"/>
                        </svg>
                        <span>All Modules</span>
                        <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-slate-600 transition-transform group-hover:rotate-180" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                        </svg>
                    </button>

                    <!-- Comprehensive 4-Column Dropdown Panel -->
                    <div class="invisible group-hover:visible opacity-0 group-hover:opacity-100 transition-all duration-150 ease-out absolute left-0 top-full pt-1.5 z-50 w-[1120px] max-w-[95vw]">
                        <div class="bg-white rounded-2xl shadow-2xl border border-slate-200/90 p-5 ring-1 ring-black/5 grid grid-cols-4 gap-4 text-left max-h-[85vh] overflow-y-auto">
                            
                            <!-- COLUMN 1: MONITORS & COCKPITS -->
                            <div class="space-y-1">
                                <div class="flex items-center gap-1.5 pb-2 mb-2 border-b border-slate-100 text-[11px] font-black text-indigo-600 uppercase tracking-wider">
                                    <svg class="w-3.5 h-3.5 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect width="20" height="14" x="2" y="3" rx="2"/>
                                        <line x1="8" x2="16" y1="21" y2="21"/>
                                        <line x1="12" x2="12" y1="17" y2="21"/>
                                    </svg>
                                    Monitors &amp; Cockpits
                                </div>
                                <div class="space-y-0.5">
                                    <a href="{{ route('nifty.unified.monitor') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-bold flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-cyan-500"></span>
                                            Unified Cockpit
                                        </span>
                                        <span class="text-[9px] font-bold uppercase tracking-wider text-indigo-700 bg-indigo-50 px-1 py-0.2 rounded">Flagship</span>
                                    </a>
                                    <a href="{{ route('order-flow-depth.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-amber-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">30-Depth Radar</span>
                                        <span class="text-[9px] font-bold uppercase tracking-wider text-amber-700 bg-amber-50 px-1 py-0.2 rounded">Live Flow</span>
                                    </a>
                                    <a href="{{ route('gamma-blast.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-fuchsia-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Gamma Blast Radar</span>
                                        <span class="text-[9px] font-bold uppercase tracking-wider text-fuchsia-700 bg-fuchsia-50 px-1 py-0.2 rounded">Scalping</span>
                                    </a>
                                    <a href="{{ route('nifty.weightage.monitor') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-emerald-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Nifty 50 Weightage</span>
                                        <span class="text-[10px] text-slate-400">Stock Heat</span>
                                    </a>
                                    <a href="{{ route('options.breadth.monitor') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Options Greek Breadth</span>
                                        <span class="text-[10px] text-slate-400">Greeks</span>
                                    </a>
                                    <a href="{{ route('trending-oi.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-rose-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Trending OI Tracker</span>
                                        <span class="text-[10px] text-slate-400">Momentum</span>
                                    </a>
                                    <a href="{{ route('live.strike.chart') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-cyan-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Live Strike Streaming</span>
                                        <span class="text-[10px] text-slate-400">Ticks</span>
                                    </a>
                                    <a href="{{ route('delta.neutral') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-teal-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Delta Neutral Corridor</span>
                                        <span class="text-[10px] text-slate-400">Equilibrium</span>
                                    </a>
                                </div>
                            </div>

                            <!-- COLUMN 2: OPTION CHAINS & ANALYTICS -->
                            <div class="space-y-1">
                                <div class="flex items-center gap-1.5 pb-2 mb-2 border-b border-slate-100 text-[11px] font-black text-cyan-600 uppercase tracking-wider">
                                    <svg class="w-3.5 h-3.5 text-cyan-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="8" x2="21" y1="6" y2="6"/>
                                        <line x1="8" x2="21" y1="12" y2="12"/>
                                        <line x1="8" x2="21" y1="18" y2="18"/>
                                        <line x1="3" x2="3.01" y1="6" y2="6"/>
                                        <line x1="3" x2="3.01" y1="12" y2="12"/>
                                        <line x1="3" x2="3.01" y1="18" y2="18"/>
                                    </svg>
                                    Option Chains &amp; Analytics
                                </div>
                                <div class="space-y-0.5">
                                    <a href="{{ route('live.straddle.radar') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-rose-600 hover:bg-rose-50/50 transition">
                                        <span class="font-bold flex items-center gap-1.5 text-rose-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                            Straddle Radar
                                        </span>
                                        <span class="text-[9px] font-bold uppercase tracking-wider text-rose-700 bg-rose-50 px-1 py-0.2 rounded">Live 🔔</span>
                                    </a>
                                    <a href="{{ route('expiry.oi.volume.analysis') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-purple-600 hover:bg-purple-50/50 transition">
                                        <span class="font-medium">Expiry Day-over-Day</span>
                                        <span class="text-[9px] font-bold uppercase tracking-wider text-purple-700 bg-purple-50 px-1 py-0.2 rounded">Radar</span>
                                    </a>
                                    <a href="{{ route('option-chain-view') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Option Chain View</span>
                                        <span class="text-[9px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-1 py-0.2 rounded">Live</span>
                                    </a>
                                    <a href="{{ route('option-chain.analysis') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Option Chain Deep Buildup</span>
                                        <span class="text-[10px] text-slate-400">Analysis</span>
                                    </a>
                                    <a href="{{ route('nifty.matrix.chart') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">NIFTY Matrix (ATM Centered)</span>
                                        <span class="text-[10px] text-slate-400 font-mono">4×2 / 3×3</span>
                                    </a>
                                    <a href="{{ route('strategy.premium.analytics') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Strategy Matrix</span>
                                        <span class="text-[10px] text-slate-400">Velocity</span>
                                    </a>
                                    <a href="{{ route('strategy.multi.chart') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Multi-Chart Grid</span>
                                        <span class="text-[10px] text-slate-400">Sync Grid</span>
                                    </a>
                                    <a href="{{ route('matching.strike.analysis') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Strike Matcher</span>
                                        <span class="text-[10px] text-slate-400">Delta Diff</span>
                                    </a>
                                    <a href="{{ route('straddle.chart') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Straddle Premium &amp; VWAP</span>
                                        <span class="text-[10px] text-slate-400">Decay</span>
                                    </a>
                                    <a href="{{ route('greek.analysis') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Greeks &amp; Multi-Strike</span>
                                        <span class="text-[10px] text-slate-400">Δ Γ Θ V</span>
                                    </a>
                                    <a href="{{ route('fii-dii.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">FII &amp; DII Institutional Flow</span>
                                        <span class="text-[10px] text-slate-400">Cash/F&amp;O</span>
                                    </a>
                                </div>
                            </div>

                            <!-- COLUMN 3: STRATEGIES & BACKTESTS -->
                            <div class="space-y-1">
                                <div class="flex items-center gap-1.5 pb-2 mb-2 border-b border-slate-100 text-[11px] font-black text-emerald-600 uppercase tracking-wider">
                                    <svg class="w-3.5 h-3.5 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                                    </svg>
                                    Strategies &amp; Execution
                                </div>
                                <div class="space-y-0.5">
                                    <a href="{{ route('trading-journal.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-bold flex items-center gap-1.5 text-indigo-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                                            Live Trade &amp; Journal
                                        </span>
                                        <span class="text-[9px] font-bold uppercase tracking-wider text-indigo-700 bg-indigo-50 px-1 py-0.2 rounded">Live P&amp;L</span>
                                    </a>
                                    <a href="{{ route('quant-strategy.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-emerald-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">High-Probability Quant</span>
                                        <span class="text-[9px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-1 py-0.2 rounded">80%+ Target</span>
                                    </a>
                                    <a href="{{ route('test.trading-simulator') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Trading Simulator</span>
                                        <span class="text-[10px] text-slate-400">Paper Trade</span>
                                    </a>
                                    <a href="{{ route('test.trading-simulator.report') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Simulator Performance</span>
                                        <span class="text-[10px] text-slate-400">Report</span>
                                    </a>
                                    <a href="{{ route('entries.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Trade Entries &amp; P&amp;L Log</span>
                                        <span class="text-[10px] text-slate-400">History</span>
                                    </a>
                                    <a href="{{ route('backtest.strategies.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-amber-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Backtest Strategies</span>
                                        <span class="text-[10px] text-slate-400">Hub</span>
                                    </a>
                                    <a href="{{ route('backtests.basket-builder') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-amber-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Basket Builder</span>
                                        <span class="text-[10px] text-slate-400">Multi-Leg</span>
                                    </a>
                                    <a href="{{ route('nifty.unified.backtest') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Unified Breadth Backtest</span>
                                        <span class="text-[10px] text-slate-400">Validation</span>
                                    </a>
                                    <a href="{{ route('backtests.six-level.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Six Levels Backtest</span>
                                        <span class="text-[10px] text-slate-400">Intraday</span>
                                    </a>
                                    <a href="{{ route('backtests.straddles.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Straddle Backtest</span>
                                        <span class="text-[10px] text-slate-400">Theta</span>
                                    </a>
                                    <a href="{{ route('strangle.profit') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Monthly Strangle Profit</span>
                                        <span class="text-[10px] text-slate-400">Positional</span>
                                    </a>
                                </div>
                            </div>

                            <!-- COLUMN 4: TOOLS & MARKET OI -->
                            <div class="space-y-1">
                                <div class="flex items-center gap-1.5 pb-2 mb-2 border-b border-slate-100 text-[11px] font-black text-amber-600 uppercase tracking-wider">
                                    <svg class="w-3.5 h-3.5 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                                    </svg>
                                    Tools &amp; Market OI
                                </div>
                                <div class="space-y-0.5">
                                    <a href="{{ route('trend.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Trend Indicator</span>
                                        <span class="text-[10px] text-slate-400">Directional</span>
                                    </a>
                                    <a href="{{ route('daily_trend.view') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Daily Trend View</span>
                                        <span class="text-[10px] text-slate-400">Daily Bias</span>
                                    </a>
                                    <a href="{{ route('oi-buildup.live') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-rose-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Live OI Build</span>
                                        <span class="text-[9px] font-bold uppercase tracking-wider text-rose-700 bg-rose-50 px-1 py-0.2 rounded">Real-time</span>
                                    </a>
                                    <a href="{{ route('buildups.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Buildups Summary</span>
                                        <span class="text-[10px] text-slate-400">Long/Short</span>
                                    </a>
                                    <a href="{{ route('build-up.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Build Up Analysis</span>
                                        <span class="text-[10px] text-slate-400">Snapshot</span>
                                    </a>
                                    <a href="{{ route('option-chain-diff') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Option Chain Diff</span>
                                        <span class="text-[10px] text-slate-400">Net Shift</span>
                                    </a>
                                    <a href="{{ route('oi-diff-live.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">OI List Diff</span>
                                        <span class="text-[10px] text-slate-400">Granular</span>
                                    </a>
                                    <a href="{{ route('strike.optimizer') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Strike Premium Identifier</span>
                                        <span class="text-[10px] text-slate-400">Optimizer</span>
                                    </a>
                                    <a href="{{ route('mid-point.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Mid Point Equilibrium</span>
                                        <span class="text-[10px] text-slate-400">Levels</span>
                                    </a>
                                    <a href="{{ route('trading.prev-level-match') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">Previous Level Match</span>
                                        <span class="text-[10px] text-slate-400">S/R</span>
                                    </a>
                                    <a href="{{ route('hlc.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                        <span class="font-medium">HLC Close Analysis</span>
                                        <span class="text-[10px] text-slate-400">Price Action</span>
                                    </a>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>

            <!-- ════════════════════════════════════════════════════════════════════
                 RIGHT: THE CHERRY-PICKED LIVE TRADING BAR (Zero Distraction Core)
            ════════════════════════════════════════════════════════════════════ -->
            <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">

                <!-- 1. Unified Cockpit (Flagship 3-in-1 Direct Action Pill) -->
                <a href="{{ route('nifty.unified.monitor') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition-all shadow-xs {{ request()->routeIs('nifty.unified.monitor') ? 'bg-indigo-600 text-white ring-2 ring-indigo-300 ring-offset-1' : 'bg-slate-900 hover:bg-slate-800 text-white' }}"
                   title="NIFTY 50 3-in-1 Unified Cockpit (Weightage, Breadth &amp; OI)">
                    <span class="relative flex h-2 w-2 shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cyan-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-cyan-400"></span>
                    </span>
                    <span class="whitespace-nowrap">Unified Cockpit</span>
                </a>

                <!-- 2. Live Straddle Decay & Expansion Radar (Audio Spike Alarm) -->
                <a href="{{ route('live.straddle.radar') }}"
                   class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition-all border {{ request()->routeIs('live.straddle.*') ? 'bg-rose-600 text-white shadow-xs border-rose-600 ring-2 ring-rose-300 ring-offset-1' : 'bg-white hover:bg-rose-50 text-rose-700 border-rose-200 hover:border-rose-300' }}"
                   title="Live Straddle Decay &amp; Spike Alert Radar (Intraday Short Straddle Entry Clue)">
                    <span class="relative flex h-2 w-2 shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ request()->routeIs('live.straddle.*') ? 'bg-white' : 'bg-rose-500' }} opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 {{ request()->routeIs('live.straddle.*') ? 'bg-white' : 'bg-rose-600' }}"></span>
                    </span>
                    <span class="whitespace-nowrap">Straddle Radar</span>
                    <span class="text-[10px]">🔔</span>
                </a>

                <!-- 3. Expiry Day-over-Day Dynamics Radar -->
                <a href="{{ route('expiry.oi.volume.analysis') }}"
                   class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition border {{ request()->routeIs('expiry.oi.volume.analysis') ? 'bg-indigo-600 text-white shadow-xs border-indigo-600 ring-1 ring-indigo-400' : 'bg-white hover:bg-indigo-50 text-slate-700 border-slate-200 hover:border-indigo-300' }}"
                   title="Option Expiry Day-over-Day OI, Volume &amp; Straddle Dynamics">
                    <svg class="w-3.5 h-3.5 shrink-0 {{ request()->routeIs('expiry.oi.volume.analysis') ? 'text-white' : 'text-indigo-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                    <span class="whitespace-nowrap">Expiry Dynamics</span>
                </a>

                <!-- 4. 30-Depth Order Flow Confluence Radar -->
                <a href="{{ route('order-flow-depth.index') }}"
                   class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition border {{ request()->routeIs('order-flow-depth.*') ? 'bg-amber-50 text-amber-900 border-amber-300 ring-1 ring-amber-200' : 'bg-white hover:bg-amber-50 text-slate-700 border-slate-200 hover:border-amber-300' }}"
                   title="30-Depth Institutional Order Flow &amp; Resting Limits Radar">
                    <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <circle cx="12" cy="12" r="6"/>
                        <circle cx="12" cy="12" r="2"/>
                    </svg>
                    <span class="whitespace-nowrap">30-Depth</span>
                </a>

                <!-- 5. Gamma Blast Scalping Radar -->
                <a href="{{ route('gamma-blast.index') }}"
                   class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition border {{ request()->routeIs('gamma-blast.*') ? 'bg-fuchsia-900/40 text-fuchsia-300 border-fuchsia-500 ring-1 ring-fuchsia-400' : 'bg-white hover:bg-fuchsia-50 text-fuchsia-700 border-slate-200 hover:border-fuchsia-300' }}"
                   title="Institutional Gamma Exposure (GEX) &amp; Scalping Blast Radar">
                    <span class="text-xs">⚡</span>
                    <span class="whitespace-nowrap">Gamma Blast</span>
                </a>

                <!-- 6. Strategy Live Trade & Journal -->
                <a href="{{ route('trading-journal.index') }}"
                   class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition border {{ request()->routeIs('trading-journal.*') ? 'bg-indigo-50 text-indigo-900 border-indigo-300 ring-1 ring-indigo-200' : 'bg-white hover:bg-slate-50 text-slate-700 border-slate-200 hover:border-slate-300' }}"
                   title="Strategy Live Trade Execution &amp; Journal">
                    <svg class="w-3.5 h-3.5 text-indigo-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                    </svg>
                    <span class="whitespace-nowrap">Live Trade</span>
                </a>

                <div class="hidden xl:block h-4 w-px bg-slate-200"></div>

                <!-- Integrated NSE Market Clock -->
                <div class="hidden xl:flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-slate-50 border border-slate-200/90 text-xs font-mono text-slate-600 shadow-2xs shrink-0"
                     title="Indian Standard Time (NSE Real-time Clock)">
                    <span class="relative flex h-2 w-2 shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">IST</span>
                    <span id="page-updated-time" class="font-medium text-slate-700 whitespace-nowrap">
                        {{ \Carbon\Carbon::now('Asia/Kolkata')->format($format) }}
                    </span>
                </div>

                <!-- Zen Mode / Fullscreen Toggle -->
                <button type="button"
                        onclick="window.toggleGlobalZenMode()"
                        class="p-1.5 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition focus:outline-none shrink-0"
                        title="Toggle Zen Mode / Fullscreen Distraction-Free (Esc)">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
                    </svg>
                </button>

                <!-- Mobile Menu Hamburger Button -->
                <button type="button"
                        id="mobile-nav-toggle-btn"
                        class="lg:hidden inline-flex items-center justify-center p-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 focus:outline-none"
                        aria-label="Toggle navigation menu">
                    <svg id="mobile-nav-icon-open" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg id="mobile-nav-icon-close" class="hidden w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

            </div>
        </div>
    </div>

    <!-- Responsive Mobile Drawer / Accordion Menu -->
    <div id="mobile-nav-drawer" class="hidden lg:hidden border-t border-slate-200 bg-white shadow-xl max-h-[85vh] overflow-y-auto px-4 py-3">
        <div class="space-y-3">
            
            <!-- Cherry-Picked Quick Trading Pills in Mobile -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 pb-2 border-b border-slate-100">
                <a href="{{ route('nifty.unified.monitor') }}" class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-indigo-600 text-white text-xs font-bold shadow-xs">
                    <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                    Unified Cockpit
                </a>
                <a href="{{ route('live.straddle.radar') }}" class="flex items-center justify-center gap-1.5 px-2.5 py-2 rounded-lg bg-rose-600 text-white text-xs font-bold shadow-xs">
                    <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                    Straddle Radar 🔔
                </a>
                <a href="{{ route('expiry.oi.volume.analysis') }}" class="flex items-center justify-center gap-1 px-2.5 py-2 rounded-lg bg-slate-800 text-white text-xs font-semibold">
                    <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                    Expiry Radar
                </a>
                <a href="{{ route('order-flow-depth.index') }}" class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-amber-500 text-white text-xs font-semibold">
                    30-Depth Radar
                </a>
                <a href="{{ route('gamma-blast.index') }}" class="flex items-center justify-center gap-1 px-2.5 py-2 rounded-lg bg-fuchsia-700 text-white text-xs font-semibold">
                    ⚡ Gamma Blast
                </a>
                <a href="{{ route('trading-journal.index') }}" class="flex items-center justify-center gap-1 px-2.5 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold">
                    💼 Live Trade
                </a>
            </div>

            <!-- Section 1: Monitors & Cockpits -->
            <details class="group/mob">
                <summary class="flex items-center justify-between py-2 text-xs font-bold uppercase tracking-wider text-slate-700 cursor-pointer list-none">
                    <span class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                        Monitors &amp; Cockpits
                    </span>
                    <svg class="w-4 h-4 text-slate-400 group-open/mob:rotate-180 transition-transform" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </summary>
                <div class="pl-4 space-y-1.5 py-1 text-xs">
                    <a href="{{ route('nifty.unified.monitor') }}" class="block py-1 text-indigo-600 font-bold">⚡ Unified Cockpit (Flagship)</a>
                    <a href="{{ route('order-flow-depth.index') }}" class="block py-1 text-amber-700 font-semibold">30-Depth Radar</a>
                    <a href="{{ route('gamma-blast.index') }}" class="block py-1 text-fuchsia-700 font-semibold">⚡ Gamma Blast Radar</a>
                    <a href="{{ route('nifty.weightage.monitor') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Nifty 50 Weightage</a>
                    <a href="{{ route('options.breadth.monitor') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Options Greek Breadth</a>
                    <a href="{{ route('trending-oi.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Trending OI Tracker</a>
                    <a href="{{ route('live.strike.chart') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Live Strike Streaming</a>
                    <a href="{{ route('delta.neutral') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Delta Neutral Corridor</a>
                </div>
            </details>

            <!-- Section 2: Option Chains & Analytics -->
            <details class="group/mob">
                <summary class="flex items-center justify-between py-2 text-xs font-bold uppercase tracking-wider text-slate-700 cursor-pointer list-none">
                    <span class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-cyan-500"></span>
                        Option Chains &amp; Analytics
                    </span>
                    <svg class="w-4 h-4 text-slate-400 group-open/mob:rotate-180 transition-transform" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </summary>
                <div class="pl-4 space-y-1.5 py-1 text-xs">
                    <a href="{{ route('live.straddle.radar') }}" class="block py-1 text-rose-600 font-bold">🔴 Straddle Radar (Spike Alarm)</a>
                    <a href="{{ route('expiry.oi.volume.analysis') }}" class="block py-1 text-purple-700 font-semibold">⚡ Expiry Day-over-Day Dynamics</a>
                    <a href="{{ route('option-chain-view') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Option Chain View (Live)</a>
                    <a href="{{ route('option-chain.analysis') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Option Chain Deep Buildup</a>
                    <a href="{{ route('nifty.matrix.chart') }}" class="block py-1 text-slate-700 hover:text-indigo-600">⊞ NIFTY Matrix (ATM Centered)</a>
                    <a href="{{ route('strategy.premium.analytics') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Strategy Matrix</a>
                    <a href="{{ route('strategy.multi.chart') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Multi-Chart Grid</a>
                    <a href="{{ route('matching.strike.analysis') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Strike Matcher</a>
                    <a href="{{ route('straddle.chart') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Straddle Premium &amp; VWAP</a>
                    <a href="{{ route('greek.analysis') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Greeks Analysis &amp; Multi-Strike</a>
                    <a href="{{ route('fii-dii.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">FII &amp; DII Institutional Flow</a>
                </div>
            </details>

            <!-- Section 3: Strategies & Execution -->
            <details class="group/mob">
                <summary class="flex items-center justify-between py-2 text-xs font-bold uppercase tracking-wider text-slate-700 cursor-pointer list-none">
                    <span class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        Strategies &amp; Execution
                    </span>
                    <svg class="w-4 h-4 text-slate-400 group-open/mob:rotate-180 transition-transform" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </summary>
                <div class="pl-4 space-y-1.5 py-1 text-xs">
                    <a href="{{ route('trading-journal.index') }}" class="block py-1 text-indigo-600 font-bold">⚡ Live Trade &amp; Journal</a>
                    <a href="{{ route('quant-strategy.index') }}" class="block py-1 text-emerald-700 font-semibold">⚡ High-Probability Quant (80%+)</a>
                    <a href="{{ route('test.trading-simulator') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Trading Simulator</a>
                    <a href="{{ route('test.trading-simulator.report') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Simulator Performance Report</a>
                    <a href="{{ route('entries.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Trade Entries &amp; P&amp;L Log</a>
                    <a href="{{ route('backtest.strategies.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Backtest Strategies Hub</a>
                    <a href="{{ route('backtests.basket-builder') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Backtest Basket Builder</a>
                    <a href="{{ route('nifty.unified.backtest') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Unified Breadth Backtest</a>
                    <a href="{{ route('backtests.six-level.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Six Levels Backtest</a>
                    <a href="{{ route('backtests.straddles.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Straddle Backtest</a>
                    <a href="{{ route('strangle.profit') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Monthly Strangle Profit</a>
                </div>
            </details>

            <!-- Section 4: Tools & Market OI -->
            <details class="group/mob">
                <summary class="flex items-center justify-between py-2 text-xs font-bold uppercase tracking-wider text-slate-700 cursor-pointer list-none">
                    <span class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        Tools &amp; Market OI
                    </span>
                    <svg class="w-4 h-4 text-slate-400 group-open/mob:rotate-180 transition-transform" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </summary>
                <div class="pl-4 space-y-1.5 py-1 text-xs">
                    <a href="{{ route('trend.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Trend Indicator</a>
                    <a href="{{ route('daily_trend.view') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Daily Trend View</a>
                    <a href="{{ route('oi-buildup.live') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Live OI Build</a>
                    <a href="{{ route('buildups.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Buildups Summary</a>
                    <a href="{{ route('build-up.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Build Up Analysis &amp; Snapshot</a>
                    <a href="{{ route('option-chain-diff') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Option Chain Diff</a>
                    <a href="{{ route('oi-diff-live.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">OI List Diff</a>
                    <a href="{{ route('strike.optimizer') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Strike Premium Identifier</a>
                    <a href="{{ route('strangle.analyzer') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Strangle Analyzer</a>
                    <a href="{{ route('mid-point.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Mid Point Equilibrium</a>
                    <a href="{{ route('trading.prev-level-match') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Previous Level Match</a>
                    <a href="{{ route('hlc.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">HLC &amp; HLC Close</a>
                </div>
            </details>

        </div>
    </div>
</nav>

<script>
    (function () {
        const toggleBtn = document.getElementById('mobile-nav-toggle-btn');
        const drawer = document.getElementById('mobile-nav-drawer');
        const iconOpen = document.getElementById('mobile-nav-icon-open');
        const iconClose = document.getElementById('mobile-nav-icon-close');

        if (toggleBtn && drawer) {
            toggleBtn.addEventListener('click', function () {
                const isHidden = drawer.classList.contains('hidden');
                if (isHidden) {
                    drawer.classList.remove('hidden');
                    if (iconOpen) iconOpen.classList.add('hidden');
                    if (iconClose) iconClose.classList.remove('hidden');
                } else {
                    drawer.classList.add('hidden');
                    if (iconOpen) iconOpen.classList.remove('hidden');
                    if (iconClose) iconClose.classList.add('hidden');
                }
            });
        }
    })();
</script>
