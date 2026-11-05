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
        'multi.strike.*', 'option-chain.analysis', 'fii-dii.*'
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
<nav class="w-full bg-white select-none relative">
    <div class="max-w-[1760px] mx-auto px-3 sm:px-5 lg:px-6">
        <div class="flex items-center justify-between h-14">
            
            <!-- LEFT: Brand & Quick Core -->
            <div class="flex items-center gap-6">
                <!-- Brand / Logo -->
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 group focus:outline-none">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-slate-900 to-indigo-950 flex items-center justify-center shadow-xs border border-slate-800 group-hover:border-indigo-500 transition-colors">
                        <svg class="w-4 h-4 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>
                            <polyline points="16 7 22 7 22 13"></polyline>
                        </svg>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-base font-bold text-slate-900 tracking-tight group-hover:text-indigo-600 transition-colors">OI OHLC</span>
                        <span class="text-[9px] font-mono font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">PRO</span>
                    </div>
                </a>

                <!-- Desktop Mega-Menu Nav Links -->
                <div class="hidden lg:flex items-center space-x-1">

                    <!-- 1. MONITORS & COCKPITS -->
                    <div class="relative group">
                        <button type="button"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-colors focus:outline-none {{ $isMonitorsActive ? 'text-indigo-600 bg-indigo-50/80 font-semibold' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-100' }}">
                            <svg class="w-3.5 h-3.5 {{ $isMonitorsActive ? 'text-indigo-600' : 'text-slate-500' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect width="20" height="14" x="2" y="3" rx="2"/>
                                <line x1="8" x2="16" y1="21" y2="21"/>
                                <line x1="12" x2="12" y1="17" y2="21"/>
                            </svg>
                            <span>Monitors</span>
                            <svg class="w-3 h-3 text-slate-400 group-hover:text-slate-600 transition-transform group-hover:rotate-180" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <!-- Dropdown Panel -->
                        <div class="invisible group-hover:visible opacity-0 group-hover:opacity-100 transition-all duration-150 ease-out absolute left-0 top-full pt-1.5 z-50 w-[560px]">
                            <div class="bg-white rounded-xl shadow-xl border border-slate-200/90 p-3 ring-1 ring-black/5">
                                <!-- Featured Command Row -->
                                <div class="grid grid-cols-2 gap-2 mb-3">
                                    <a href="{{ route('nifty.unified.monitor') }}"
                                       class="flex flex-col p-2.5 rounded-lg border transition-all {{ request()->routeIs('nifty.unified.monitor') ? 'bg-indigo-50/80 border-indigo-300' : 'bg-slate-50/70 hover:bg-indigo-50/40 border-slate-200/80 hover:border-indigo-200' }}">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                                                <span class="w-2 h-2 rounded-full bg-cyan-500 animate-pulse"></span>
                                                Unified Cockpit
                                            </span>
                                            <span class="text-[9px] font-bold uppercase tracking-wider text-indigo-700 bg-indigo-100/80 px-1.5 py-0.2 rounded">Flagship</span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 line-clamp-2 leading-tight">3-in-1 monitor: Weightage, Options Breadth &amp; Trending OI confluence.</p>
                                    </a>

                                    <a href="{{ route('order-flow-depth.index') }}"
                                       class="flex flex-col p-2.5 rounded-lg border transition-all {{ request()->routeIs('order-flow-depth.*') ? 'bg-amber-50/80 border-amber-300' : 'bg-slate-50/70 hover:bg-amber-50/40 border-slate-200/80 hover:border-amber-200' }}">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                                                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                                30-Depth Radar
                                            </span>
                                            <span class="text-[9px] font-bold uppercase tracking-wider text-amber-700 bg-amber-100/80 px-1.5 py-0.2 rounded">Live Flow</span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 line-clamp-2 leading-tight">Order flow depth, institutional resting limits &amp; breakout confluence.</p>
                                    </a>
                                </div>

                                <!-- Monitor List Grid -->
                                <div class="border-t border-slate-100 pt-2 grid grid-cols-2 gap-1">
                                    <a href="{{ route('nifty.weightage.monitor') }}"
                                       class="flex items-center gap-2 px-2.5 py-1.5 rounded-md text-xs transition {{ request()->routeIs('nifty.weightage.monitor') ? 'bg-emerald-50 text-emerald-800 font-semibold' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900' }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        <span class="truncate">Nifty 50 Weightage</span>
                                    </a>

                                    <a href="{{ route('options.breadth.monitor') }}"
                                       class="flex items-center gap-2 px-2.5 py-1.5 rounded-md text-xs transition {{ request()->routeIs('options.breadth.monitor') ? 'bg-indigo-50 text-indigo-800 font-semibold' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900' }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                                        <span class="truncate">Options Greek Breadth</span>
                                    </a>

                                    <a href="{{ route('trending-oi.index') }}"
                                       class="flex items-center gap-2 px-2.5 py-1.5 rounded-md text-xs transition {{ request()->routeIs('trending-oi.index') ? 'bg-rose-50 text-rose-800 font-semibold' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900' }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        <span class="truncate">Trending OI Tracker</span>
                                    </a>

                                    <a href="{{ route('live.strike.chart') }}"
                                       class="flex items-center gap-2 px-2.5 py-1.5 rounded-md text-xs transition {{ request()->routeIs('live.strike.chart') ? 'bg-cyan-50 text-cyan-800 font-semibold' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900' }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-cyan-500"></span>
                                        <span class="truncate">Live Strike Streaming</span>
                                    </a>

                                    <a href="{{ route('delta.neutral') }}"
                                       class="flex items-center gap-2 px-2.5 py-1.5 rounded-md text-xs transition {{ request()->routeIs('delta.neutral') ? 'bg-teal-50 text-teal-800 font-semibold' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900' }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span>
                                        <span class="truncate">Delta Neutral Corridor</span>
                                    </a>

                                    <a href="{{ route('gamma-blast.index') }}"
                                       class="flex items-center gap-2 px-2.5 py-1.5 rounded-md text-xs transition {{ request()->routeIs('gamma-blast.*') ? 'bg-fuchsia-50 text-fuchsia-800 font-semibold' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900' }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-fuchsia-500"></span>
                                        <span class="truncate">⚡ Gamma Blast Radar</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. ANALYTICS & MATRICES -->
                    <div class="relative group">
                        <button type="button"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-colors focus:outline-none {{ $isAnalyticsActive ? 'text-indigo-600 bg-indigo-50/80 font-semibold' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-100' }}">
                            <svg class="w-3.5 h-3.5 {{ $isAnalyticsActive ? 'text-indigo-600' : 'text-slate-500' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 3v18h18"/>
                                <path d="m19 9-5 5-4-4-3 3"/>
                            </svg>
                            <span>Analytics &amp; Matrices</span>
                            <svg class="w-3 h-3 text-slate-400 group-hover:text-slate-600 transition-transform group-hover:rotate-180" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <!-- Dropdown Panel (2-Column Mega Menu) -->
                        <div class="invisible group-hover:visible opacity-0 group-hover:opacity-100 transition-all duration-150 ease-out absolute left-0 top-full pt-1.5 z-50 w-[640px]">
                            <div class="bg-white rounded-xl shadow-xl border border-slate-200/90 p-4 ring-1 ring-black/5 grid grid-cols-2 gap-4">
                                
                                <!-- Column 1: Multi-Charts & Matrices -->
                                <div>
                                    <div class="flex items-center gap-1.5 pb-1.5 mb-1.5 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                        <svg class="w-3 h-3 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect width="7" height="7" x="3" y="3" rx="1"/>
                                            <rect width="7" height="7" x="14" y="3" rx="1"/>
                                            <rect width="7" height="7" x="14" y="14" rx="1"/>
                                            <rect width="7" height="7" x="3" y="14" rx="1"/>
                                        </svg>
                                        Matrices &amp; Multi-Charts
                                    </div>
                                    <div class="space-y-0.5">
                                        <a href="{{ route('nifty.matrix.chart') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">NIFTY Matrix (ATM Centered)</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500 font-mono">4×2 / 3×3</span>
                                        </a>
                                        <a href="{{ route('strategy.premium.analytics') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Strategy Matrix</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Velocity</span>
                                        </a>
                                        <a href="{{ route('strategy.multi.chart') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Multi-Chart Strategy Grid</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500 font-mono">Grid</span>
                                        </a>
                                        <a href="{{ route('matching.strike.analysis') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Strike Matcher</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Delta Diff</span>
                                        </a>
                                        <a href="{{ route('options-analytics.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Strategy Analytics Framework</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Risk Profile</span>
                                        </a>
                                        <a href="{{ route('straddle.chart') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Straddle Premium &amp; VWAP</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">VWAP</span>
                                        </a>
                                    </div>
                                </div>

                                <!-- Column 2: Option Chains & Greek Analysis -->
                                <div>
                                    <div class="flex items-center gap-1.5 pb-1.5 mb-1.5 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                        <svg class="w-3 h-3 text-cyan-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="8" x2="21" y1="6" y2="6"/>
                                            <line x1="8" x2="21" y1="12" y2="12"/>
                                            <line x1="8" x2="21" y1="18" y2="18"/>
                                            <line x1="3" x2="3.01" y1="6" y2="6"/>
                                            <line x1="3" x2="3.01" y1="12" y2="12"/>
                                            <line x1="3" x2="3.01" y1="18" y2="18"/>
                                        </svg>
                                        Option Chains &amp; Greeks
                                    </div>
                                    <div class="space-y-0.5">
                                        <a href="{{ route('option-chain-view') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Option Chain View</span>
                                            <span class="text-[9px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-1 py-0.2 rounded">Live</span>
                                        </a>
                                        <a href="{{ route('option-chain.analysis') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Option Chain Deep Analysis</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Buildup</span>
                                        </a>
                                        <a href="{{ route('vega.analysis') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Vega Clue Monitor</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Buy/Sell</span>
                                        </a>
                                        <a href="{{ route('greek.analysis') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Greeks &amp; Multi-Strike Greeks</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Δ Γ Θ V</span>
                                        </a>
                                        <a href="{{ route('combined.premium.analysis') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Combined Premium Analysis</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Decay</span>
                                        </a>
                                        <a href="{{ route('fii-dii.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">FII &amp; DII Institutional Flow</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Cash/F&amp;O</span>
                                        </a>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- 3. STRATEGIES & EXECUTION -->
                    <div class="relative group">
                        <button type="button"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-colors focus:outline-none {{ $isStrategiesActive ? 'text-indigo-600 bg-indigo-50/80 font-semibold' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-100' }}">
                            <svg class="w-3.5 h-3.5 {{ $isStrategiesActive ? 'text-indigo-600' : 'text-slate-500' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="m4.93 4.93 4.24 4.24"/>
                                <path d="m14.83 9.17 4.24-4.24"/>
                                <path d="m14.83 14.83 4.24 4.24"/>
                                <path d="m9.17 14.83-4.24 4.24"/>
                                <circle cx="12" cy="12" r="4"/>
                            </svg>
                            <span>Strategies &amp; Trade</span>
                            <svg class="w-3 h-3 text-slate-400 group-hover:text-slate-600 transition-transform group-hover:rotate-180" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <!-- Dropdown Panel (2-Column Mega Menu) -->
                        <div class="invisible group-hover:visible opacity-0 group-hover:opacity-100 transition-all duration-150 ease-out absolute left-0 top-full pt-1.5 z-50 w-[640px]">
                            <div class="bg-white rounded-xl shadow-xl border border-slate-200/90 p-4 ring-1 ring-black/5 grid grid-cols-2 gap-4">

                                <!-- Column 1: Live Execution & Quant -->
                                <div>
                                    <div class="flex items-center gap-1.5 pb-1.5 mb-1.5 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                        <svg class="w-3 h-3 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                                        </svg>
                                        Live Execution &amp; Quant
                                    </div>
                                    <div class="space-y-0.5">
                                        <a href="{{ route('trading-journal.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Strategy Live Trade &amp; Journal</span>
                                            <span class="text-[9px] font-bold uppercase tracking-wider text-indigo-700 bg-indigo-50 px-1 py-0.2 rounded">Live P&amp;L</span>
                                        </a>
                                        <a href="{{ route('quant-strategy.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">High-Probability Quant</span>
                                            <span class="text-[9px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-1 py-0.2 rounded">80%+ Target</span>
                                        </a>
                                        <a href="{{ route('test.trading-simulator') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Trading Simulator</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Paper Trade</span>
                                        </a>
                                        <a href="{{ route('test.trading-simulator.report') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Simulator Performance Report</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Metrics</span>
                                        </a>
                                        <a href="{{ route('entries.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Trade Entries &amp; P&amp;L Log</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">History</span>
                                        </a>
                                    </div>
                                </div>

                                <!-- Column 2: Backtest Hub -->
                                <div>
                                    <div class="flex items-center gap-1.5 pb-1.5 mb-1.5 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                        <svg class="w-3 h-3 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 2v4"/>
                                            <path d="m4.93 4.93 2.83 2.83"/>
                                            <path d="M2 12h4"/>
                                            <path d="m4.93 19.07 2.83-2.83"/>
                                            <path d="M12 22v-4"/>
                                            <path d="m19.07 19.07-2.83-2.83"/>
                                            <path d="M22 12h-4"/>
                                            <path d="m19.07 4.93-2.83 2.83"/>
                                        </svg>
                                        Strategy Backtesting Hub
                                    </div>
                                    <div class="space-y-0.5">
                                        <a href="{{ route('backtest.strategies.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Backtest Strategies</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Config</span>
                                        </a>
                                        <a href="{{ route('backtests.basket-builder') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Basket Builder</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Multi-Leg</span>
                                        </a>
                                        <a href="{{ route('nifty.unified.backtest') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Unified Breadth Backtest</span>
                                            <span class="text-[9px] font-bold uppercase tracking-wider text-cyan-700 bg-cyan-50 px-1 py-0.2 rounded">5-Min</span>
                                        </a>
                                        <a href="{{ route('backtests.six-level.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Six Levels Backtest</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Levels</span>
                                        </a>
                                        <a href="{{ route('backtests.straddles.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Straddle &amp; Strangle Backtest</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Decay</span>
                                        </a>
                                        <a href="{{ route('backtests.futures.ohlc.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Futures OHLC Backtest</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Index</span>
                                        </a>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- 4. TOOLS & OI DYNAMICS -->
                    <div class="relative group">
                        <button type="button"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-colors focus:outline-none {{ $isToolsActive ? 'text-indigo-600 bg-indigo-50/80 font-semibold' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-100' }}">
                            <svg class="w-3.5 h-3.5 {{ $isToolsActive ? 'text-indigo-600' : 'text-slate-500' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                            </svg>
                            <span>Tools &amp; OI</span>
                            <svg class="w-3 h-3 text-slate-400 group-hover:text-slate-600 transition-transform group-hover:rotate-180" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <!-- Dropdown Panel (2-Column Mega Menu) -->
                        <div class="invisible group-hover:visible opacity-0 group-hover:opacity-100 transition-all duration-150 ease-out absolute left-0 top-full pt-1.5 z-50 w-[640px]">
                            <div class="bg-white rounded-xl shadow-xl border border-slate-200/90 p-4 ring-1 ring-black/5 grid grid-cols-2 gap-4">

                                <!-- Column 1: OI & Buildup Diagnostics -->
                                <div>
                                    <div class="flex items-center gap-1.5 pb-1.5 mb-1.5 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                        <svg class="w-3 h-3 text-rose-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M18 20V10"/>
                                            <path d="M12 20V4"/>
                                            <path d="M6 20v-6"/>
                                        </svg>
                                        OI Buildup Diagnostics
                                    </div>
                                    <div class="space-y-0.5">
                                        <a href="{{ route('oi-buildup.live') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Live OI Build</span>
                                            <span class="text-[9px] font-bold uppercase tracking-wider text-rose-700 bg-rose-50 px-1 py-0.2 rounded">Real-time</span>
                                        </a>
                                        <a href="{{ route('buildups.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Buildups Summary</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Long/Short</span>
                                        </a>
                                        <a href="{{ route('build-up.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Build Up Analysis &amp; Snapshot</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Snapshot</span>
                                        </a>
                                        <a href="{{ route('option-chain-diff') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Option Chain Diff &amp; Buildup</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Net Shift</span>
                                        </a>
                                        <a href="{{ route('oi-diff-live.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">OI List Diff &amp; Strike Detail</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Granular</span>
                                        </a>
                                    </div>
                                </div>

                                <!-- Column 2: Market Structure & Strike Tools -->
                                <div>
                                    <div class="flex items-center gap-1.5 pb-1.5 mb-1.5 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                        <svg class="w-3 h-3 text-teal-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M2 12h20"/>
                                            <path d="M20 12l-4-4"/>
                                            <path d="M20 12l-4 4"/>
                                            <path d="M4 12l4-4"/>
                                            <path d="M4 12l4 4"/>
                                        </svg>
                                        Market Structure &amp; Levels
                                    </div>
                                    <div class="space-y-0.5">
                                        <a href="{{ route('trend.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Trend Indicator &amp; Daily View</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Directional</span>
                                        </a>
                                        <a href="{{ route('strike.optimizer') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Strike Premium Identifier</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Optimizer</span>
                                        </a>
                                        <a href="{{ route('strangle.analyzer') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Strangle Analyzer</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Payoff</span>
                                        </a>
                                        <a href="{{ route('mid-point.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">Mid Point &amp; Level Match</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Equilibrium</span>
                                        </a>
                                        <a href="{{ route('hlc.index') }}" class="group/item flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition">
                                            <span class="font-medium">HLC &amp; HLC Close Analysis</span>
                                            <span class="text-[10px] text-slate-400 group-hover/item:text-indigo-500">Price Action</span>
                                        </a>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- RIGHT: High-Priority Direct Pills + Market Clock + Actions -->
            <div class="flex items-center gap-2.5">

                <!-- 1. Unified Cockpit (Flagship Direct Action Pill) -->
                <a href="{{ route('nifty.unified.monitor') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all shadow-xs {{ request()->routeIs('nifty.unified.monitor') ? 'bg-indigo-600 text-white ring-2 ring-indigo-300 ring-offset-1' : 'bg-slate-900 hover:bg-slate-800 text-white' }}"
                   title="NIFTY 50 3-in-1 Unified Cockpit (Weightage, Breadth &amp; OI)">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cyan-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-cyan-400"></span>
                    </span>
                    <span class="whitespace-nowrap font-medium">Unified Cockpit</span>
                </a>

                <!-- 2. 30-Depth Radar Direct Pill -->
                <a href="{{ route('order-flow-depth.index') }}"
                   class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs transition border {{ request()->routeIs('order-flow-depth.*') ? 'bg-amber-50 text-amber-900 border-amber-300 font-semibold ring-1 ring-amber-200' : 'bg-white hover:bg-slate-50 text-slate-700 border-slate-200 hover:border-slate-300' }}"
                   title="30-Depth Order Flow Confluence Radar">
                    <svg class="w-3.5 h-3.5 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <circle cx="12" cy="12" r="6"/>
                        <circle cx="12" cy="12" r="2"/>
                    </svg>
                    <span class="whitespace-nowrap font-medium">30-Depth</span>
                </a>

                <!-- 2.5 Gamma Blast Scalping Radar Direct Pill -->
                <a href="{{ route('gamma-blast.index') }}"
                   class="hidden lg:inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs transition border {{ request()->routeIs('gamma-blast.*') ? 'bg-fuchsia-900/40 text-fuchsia-300 border-fuchsia-500 font-bold ring-1 ring-fuchsia-400' : 'bg-white hover:bg-fuchsia-50 text-fuchsia-700 border-slate-200 hover:border-fuchsia-300' }}"
                   title="Institutional Gamma Exposure (GEX) & Scalping Blast Radar">
                    <span class="text-xs">⚡</span>
                    <span class="whitespace-nowrap font-medium">Gamma Blast</span>
                </a>

                <!-- 3. Live Trade Direct Pill -->
                <a href="{{ route('trading-journal.index') }}"
                   class="hidden md:inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs transition border {{ request()->routeIs('trading-journal.*') ? 'bg-indigo-50 text-indigo-900 border-indigo-300 font-semibold ring-1 ring-indigo-200' : 'bg-white hover:bg-slate-50 text-slate-700 border-slate-200 hover:border-slate-300' }}"
                   title="Strategy Live Trade &amp; Journal">
                    <svg class="w-3.5 h-3.5 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                    </svg>
                    <span class="whitespace-nowrap font-medium">Live Trade</span>
                </a>

                <div class="hidden xl:block h-4 w-px bg-slate-200"></div>

                <!-- Integrated NSE Market Clock (Embedded ID for script updates) -->
                <div class="hidden xl:flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-slate-50 border border-slate-200/90 text-xs font-mono text-slate-600 shadow-2xs"
                     title="Indian Standard Time (NSE Real-time Clock)">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">IST</span>
                    <span id="page-updated-time" class="font-medium text-slate-700 whitespace-nowrap">
                        {{ \Carbon\Carbon::now('Asia/Kolkata')->format($format) }}
                    </span>
                </div>

                <!-- Zen / Fullscreen Toggle -->
                <button type="button"
                        id="global-zen-toggle-btn"
                        onclick="toggleGlobalZenMode()"
                        class="hidden lg:inline-flex items-center justify-center p-1.5 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition focus:outline-none cursor-pointer"
                        title="Toggle Zen Mode / Full View (Esc)">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
                    </svg>
                </button>

                <!-- Mobile Hamburger Button -->
                <button type="button"
                        id="mobile-nav-toggle-btn"
                        class="lg:hidden inline-flex items-center justify-center p-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 focus:outline-none"
                        aria-label="Toggle Navigation">
                    <svg id="mobile-nav-icon-open" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                    <svg id="mobile-nav-icon-close" class="hidden w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>

            </div>
        </div>
    </div>

    <!-- Responsive Mobile Drawer / Accordion Menu -->
    <div id="mobile-nav-drawer" class="hidden lg:hidden border-t border-slate-200 bg-white shadow-xl max-h-[85vh] overflow-y-auto px-4 py-3">
        <div class="space-y-3">
            
            <!-- Quick Pills in Mobile -->
            <div class="grid grid-cols-2 gap-2 pb-2 border-b border-slate-100">
                <a href="{{ route('nifty.unified.monitor') }}" class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-indigo-600 text-white text-xs font-semibold">
                    <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
                    Unified Cockpit
                </a>
                <a href="{{ route('order-flow-depth.index') }}" class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-amber-500 text-white text-xs font-semibold">
                    30-Depth Radar
                </a>
            </div>

            <!-- Section 1: Monitors -->
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
                    <a href="{{ route('nifty.unified.monitor') }}" class="block py-1 text-slate-700 hover:text-indigo-600 font-semibold">🎯 NIFTY Unified Cockpit</a>
                    <a href="{{ route('order-flow-depth.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600 font-semibold">🌊 30-Depth Order Flow Radar</a>
                    <a href="{{ route('nifty.weightage.monitor') }}" class="block py-1 text-slate-700 hover:text-indigo-600">📊 Nifty 50 Weightage Monitor</a>
                    <a href="{{ route('options.breadth.monitor') }}" class="block py-1 text-slate-700 hover:text-indigo-600">⚡ Options Greek Breadth</a>
                    <a href="{{ route('trending-oi.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">📈 Trending OI Tracker</a>
                    <a href="{{ route('live.strike.chart') }}" class="block py-1 text-slate-700 hover:text-indigo-600">⚡ Live Strike Streaming</a>
                    <a href="{{ route('delta.neutral') }}" class="block py-1 text-slate-700 hover:text-indigo-600">⚖️ Delta Neutral Strategy</a>
                </div>
            </details>

            <!-- Section 2: Analytics -->
            <details class="group/mob">
                <summary class="flex items-center justify-between py-2 text-xs font-bold uppercase tracking-wider text-slate-700 cursor-pointer list-none">
                    <span class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-cyan-500"></span>
                        Analytics &amp; Matrices
                    </span>
                    <svg class="w-4 h-4 text-slate-400 group-open/mob:rotate-180 transition-transform" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </summary>
                <div class="pl-4 space-y-1.5 py-1 text-xs">
                    <a href="{{ route('nifty.matrix.chart') }}" class="block py-1 text-slate-700 hover:text-indigo-600">⊞ NIFTY Matrix (ATM Centered)</a>
                    <a href="{{ route('strategy.premium.analytics') }}" class="block py-1 text-slate-700 hover:text-indigo-600">📈 Strategy Matrix</a>
                    <a href="{{ route('strategy.multi.chart') }}" class="block py-1 text-slate-700 hover:text-indigo-600">⊞ Multi-Chart Grid (2×3 / 3×3)</a>
                    <a href="{{ route('matching.strike.analysis') }}" class="block py-1 text-slate-700 hover:text-indigo-600">⚖️ Strike Matcher</a>
                    <a href="{{ route('options-analytics.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">🎯 Strategy Analytics Framework</a>
                    <a href="{{ route('straddle.chart') }}" class="block py-1 text-slate-700 hover:text-indigo-600">📊 Straddle Chart &amp; VWAP</a>
                    <a href="{{ route('option-chain-view') }}" class="block py-1 text-slate-700 hover:text-indigo-600">📋 Option Chain View</a>
                    <a href="{{ route('option-chain.analysis') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Option Chain Deep Analysis</a>
                    <a href="{{ route('vega.analysis') }}" class="block py-1 text-slate-700 hover:text-indigo-600">🎯 Vega Clue Monitor</a>
                    <a href="{{ route('greek.analysis') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Greek Analysis &amp; Multi-Strike</a>
                    <a href="{{ route('combined.premium.analysis') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Combined Premium Analysis</a>
                    <a href="{{ route('fii-dii.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">📊 FII &amp; DII Institutional Flow</a>
                </div>
            </details>

            <!-- Section 3: Strategies -->
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
                    <a href="{{ route('trading-journal.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600 font-semibold">⚡ Strategy Live Trade &amp; Journal</a>
                    <a href="{{ route('quant-strategy.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600 font-semibold">⚡ High-Probability Quant (80%+)</a>
                    <a href="{{ route('test.trading-simulator') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Trading Simulator</a>
                    <a href="{{ route('test.trading-simulator.report') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Simulator Performance Report</a>
                    <a href="{{ route('entries.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Trade Entries &amp; P&amp;L Log</a>
                    <a href="{{ route('backtest.strategies.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Backtest Strategies</a>
                    <a href="{{ route('backtests.basket-builder') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Backtest Basket Builder</a>
                    <a href="{{ route('nifty.unified.backtest') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Unified Breadth Backtest</a>
                    <a href="{{ route('backtests.six-level.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Six Levels Backtest</a>
                    <a href="{{ route('backtests.straddles.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Straddle Backtest</a>
                    <a href="{{ route('backtests.futures.ohlc.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Futures OHLC Backtest</a>
                    <a href="{{ route('strangle.profit') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Monthly Strangle</a>
                    <a href="{{ route('backtest.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Backtest Archive</a>
                </div>
            </details>

            <!-- Section 4: Tools & OI -->
            <details class="group/mob">
                <summary class="flex items-center justify-between py-2 text-xs font-bold uppercase tracking-wider text-slate-700 cursor-pointer list-none">
                    <span class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                        Tools &amp; OI Dynamics
                    </span>
                    <svg class="w-4 h-4 text-slate-400 group-open/mob:rotate-180 transition-transform" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </summary>
                <div class="pl-4 space-y-1.5 py-1 text-xs">
                    <a href="{{ route('oi-buildup.live') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Live OI Build</a>
                    <a href="{{ route('buildups.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Buildups Summary</a>
                    <a href="{{ route('build-up.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Build Up Analysis &amp; Snapshot</a>
                    <a href="{{ route('option-chain-diff') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Option Chain Diff</a>
                    <a href="{{ route('option-chain.build-up') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Option Buildup All</a>
                    <a href="{{ route('oi-diff-live.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">OI List Diff</a>
                    <a href="{{ route('trend.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Trend Indicator</a>
                    <a href="{{ route('daily_trend.view') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Daily Trend View</a>
                    <a href="{{ route('strike.optimizer') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Strike Premium Identifier</a>
                    <a href="{{ route('strangle.analyzer') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Strangle Analyzer</a>
                    <a href="{{ route('mid-point.index') }}" class="block py-1 text-slate-700 hover:text-indigo-600">Mid Point</a>
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
