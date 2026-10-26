<!-- resources/views/components/header-nav.blade.php -->
<nav class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">
            <div class="flex-shrink-0 flex items-center gap-3">
                <!-- Logo/Brand Here -->
                <a href="{{ route('home') }}" class="text-xl font-bold text-gray-900 tracking-tight">OI OHLC</a>
                <a href="{{ route('home') }}" class="text-xs text-gray-500 hover:text-blue-600 px-2 py-1 rounded hidden sm:inline">Dashboard</a>
            </div>

            <div class="hidden md:flex md:items-center space-x-3">
                <!-- PRIMARY FEATURED BUTTONS ONLY (Requested by User) -->
                
                <!-- 1. Nifty 50 Weightage Monitor -->
                <a href="{{ route('nifty.weightage.monitor') }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition text-sm shadow-sm {{ request()->routeIs('nifty.weightage.monitor') ? 'ring-2 ring-emerald-300 ring-offset-1 bg-emerald-700' : '' }}">
                    <span>📊</span>
                    <span>Nifty 50 Weightage</span>
                </a>

                <!-- 2. Options Breadth Monitor -->
                <a href="{{ route('options.breadth.monitor') }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition text-sm shadow-sm {{ request()->routeIs('options.breadth.monitor') ? 'ring-2 ring-indigo-300 ring-offset-1 bg-indigo-800' : '' }}">
                    <span>⚡</span>
                    <span>Options Breadth</span>
                </a>

                <!-- 3. Trending OI -->
                <a href="{{ route('trending-oi.index') }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded font-semibold text-white bg-rose-600 hover:bg-rose-700 transition text-sm shadow-sm {{ request()->routeIs('trending-oi.index') ? 'ring-2 ring-rose-300 ring-offset-1 bg-rose-700' : '' }}">
                    <span>📈</span>
                    <span>Trending OI</span>
                </a>

                <!-- 3. Strategy Live Trade / Trading Journal -->
                <a href="{{ route('trading-journal.index') }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition text-sm shadow-sm {{ request()->routeIs('trading-journal.*') ? 'ring-2 ring-indigo-300 ring-offset-1 bg-indigo-700' : '' }}">
                    <span>⚡</span>
                    <span>Strategy Live Trade</span>
                </a>

                <!-- SUBMENU 1: Analytics & Matrices (Moved from top-level buttons) -->
                <div class="relative group">
                    <button class="inline-flex items-center px-3 py-2 rounded hover:bg-gray-100 focus:outline-none text-gray-700 text-sm font-medium">
                        <span>📊 Analytics &amp; Matrix</span>
                        <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div class="absolute z-30 left-0 mt-1 w-64 bg-white rounded-md shadow-lg border border-gray-100 opacity-0 group-hover:opacity-100 group-focus:opacity-100 transition pointer-events-none group-hover:pointer-events-auto group-focus:pointer-events-auto max-h-[85vh] overflow-y-auto">
                        <div class="p-2 bg-gray-50 border-b border-gray-100 text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                            Matrix &amp; Charts
                        </div>
                        <a href="{{ route('nifty.matrix.chart') }}" class="block px-4 py-2 font-bold text-blue-700 hover:bg-blue-50 border-b border-gray-100">⊞ NIFTY Matrix (ATM Centered)</a>
                        <a href="{{ route('strategy.premium.analytics') }}" class="block px-4 py-2 font-bold text-teal-700 hover:bg-teal-50 border-b border-gray-100">📈 Strategy Matrix</a>
                        <a href="{{ route('strategy.multi.chart') }}" class="block px-4 py-2 font-bold text-indigo-700 hover:bg-indigo-50 border-b border-gray-100">⊞ Multi-Chart Grid (2×3 / 3×3)</a>
                        <a href="{{ route('matching.strike.analysis') }}" class="block px-4 py-2 font-bold text-amber-700 hover:bg-amber-50 border-b border-gray-100">⚖️ Strike Matcher</a>
                        <a href="{{ route('options-analytics.index') }}" class="block px-4 py-2 font-bold text-cyan-700 hover:bg-cyan-50 border-b border-gray-100">🎯 Strategy Analytics Framework</a>
                        <a href="{{ route('delta.neutral') }}" class="block px-4 py-2 font-bold text-emerald-700 hover:bg-emerald-50 border-b border-gray-100">⚖️ Delta Neutral</a>
                        <a href="{{ route('fii-dii.index') }}" class="block px-4 py-2 text-indigo-700 hover:bg-indigo-50 font-semibold border-b border-gray-100">📊 FII &amp; DII Activity</a>
                        <a href="{{ route('straddle.chart') }}" class="block px-4 py-2 text-indigo-700 hover:bg-indigo-50 border-b border-gray-100">📊 Straddle Chart</a>
                        <a href="{{ route('live.strike.chart') }}" class="block px-4 py-2 text-emerald-700 hover:bg-emerald-50 font-semibold border-b border-gray-100">⚡ Live Strike Chart</a>
                        <a href="{{ route('option-chain-view') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">📋 Option Chain View</a>
                        <a href="{{ route('combined.premium.analysis') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Combined Premium</a>
                        <a href="{{ route('options.breadth.monitor') }}" class="block px-4 py-2 font-bold text-indigo-700 bg-indigo-50/70 hover:bg-indigo-100 border-b border-indigo-100">⚡ Options Greek &amp; Breadth Monitor</a>
                        <a href="{{ route('vega.analysis') }}" class="block px-4 py-2 font-bold text-indigo-700 hover:bg-indigo-50 border-b border-gray-100">🎯 Vega Clue Monitor (Buy/Sell)</a>
                        <a href="{{ route('greek.analysis') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Greek Analysis</a>
                        <a href="{{ route('multi.strike.analysis') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Multi Strike Greeks</a>
                        <a href="{{ route('option-chain.analysis') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Option Chain Deep Analysis</a>
                    </div>
                </div>

                <!-- SUBMENU 2: Strategies & Backtests -->
                <div class="relative group">
                    <button class="inline-flex items-center px-3 py-2 rounded hover:bg-gray-100 focus:outline-none text-gray-700 text-sm font-medium">
                        <span>🎯 Strategies</span>
                        <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div class="absolute z-30 left-0 mt-1 w-64 bg-white rounded-md shadow-lg border border-gray-100 opacity-0 group-hover:opacity-100 group-focus:opacity-100 transition pointer-events-none group-hover:pointer-events-auto group-focus:pointer-events-auto max-h-[85vh] overflow-y-auto">
                        <div class="p-2 bg-gray-50 border-b border-gray-100 text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                            Trading Strategies
                        </div>
                        <a href="{{ route('quant-strategy.index') }}" class="block px-4 py-2 font-bold text-indigo-700 bg-indigo-50/70 hover:bg-indigo-100 border-b border-indigo-100">⚡ High-Probability Quant (80%+)</a>
                        <a href="{{ route('backtest.strategies.index') }}" class="block px-4 py-2 font-semibold text-gray-800 hover:bg-blue-50 border-b border-gray-100">Backtest Strategies</a>
                        <a href="{{ route('backtests.basket-builder') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Backtest Basket Builder</a>
                        <a href="{{ route('backtests.six-level.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Six Levels Backtest</a>
                        <a href="{{ route('backtests.straddles.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Straddle Backtest</a>
                        <a href="{{ route('backtests.futures.ohlc.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Futures OHLC Backtest</a>
                        <a href="{{ route('strangle.profit') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Monthly Strangle</a>
                        <a href="{{ route('entries.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Entry Log</a>
                        <a href="{{ route('test.trading-simulator') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Trading Simulator</a>
                        <a href="{{ route('test.trading-simulator.report') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Simulator Report</a>
                        <a href="{{ route('backtest.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Backtest Archive</a>
                    </div>
                </div>

                <!-- SUBMENU 3: Market Tools & OI Buildups -->
                <div class="relative group">
                    <button class="inline-flex items-center px-3 py-2 rounded hover:bg-gray-100 focus:outline-none text-gray-700 text-sm font-medium">
                        <span>🛠️ Tools &amp; OI</span>
                        <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div class="absolute z-30 right-0 mt-1 w-60 bg-white rounded-md shadow-lg border border-gray-100 opacity-0 group-hover:opacity-100 group-focus:opacity-100 transition pointer-events-none group-hover:pointer-events-auto group-focus:pointer-events-auto max-h-[85vh] overflow-y-auto">
                        <div class="p-2 bg-gray-50 border-b border-gray-100 text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                            OI &amp; Market Depth
                        </div>
                        <a href="{{ route('trend.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Trend Indicator</a>
                        <a href="{{ route('daily_trend.view') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Daily Trend View</a>
                        <a href="{{ route('strike.optimizer') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Strike Premium Identifier</a>
                        <a href="{{ route('mid-point.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Mid Point</a>
                        <a href="{{ route('trading.prev-level-match') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Level Match</a>
                        <a href="{{ route('oi-buildup.live') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Live OI Build</a>
                        <a href="{{ route('buildups.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Buildups Summary</a>
                        <a href="{{ route('build-up.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Build Up Analysis</a>
                        <a href="{{ route('buildup.snapshot') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Build Up Snapshot</a>
                        <a href="{{ route('buildups.strike') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Buildup Strike</a>
                        <a href="{{ route('oi-diff-live.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">OI List Diff</a>
                        <a href="{{ route('option-chain-diff') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Option Chain Diff</a>
                        <a href="{{ route('option-chain.build-up') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Option Buildup</a>
                        <a href="{{ route('option-chain.build-up-all') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Option Buildup All</a>
                        <a href="{{ route('strangle.analyzer') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Strangle Analyzer</a>
                        <a href="{{ route('strike.detail') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">Strike OI Detail</a>
                        <a href="{{ route('hlc.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">HLC</a>
                        <a href="{{ route('hlc.close') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50">HLC Close</a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</nav>
