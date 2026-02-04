<!-- resources/views/components/header-nav.blade.php -->
<nav class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">
            <div class="flex-shrink-0">
                <!-- Logo/Brand Here -->
                <a href="{{ route('home') }}" class="text-xl font-bold">OI OHLC</a>
            </div>
            <div class="hidden md:flex md:items-center space-x-4">
                <!-- Normal Links -->
                <a href="{{ route('home') }}" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded">Dashboard</a>
                <!-- Dropdown -->
                <div class="relative group">
                    <button class="inline-flex items-center px-3 py-2 rounded hover:bg-blue-50 focus:outline-none text-gray-700">
                        Menu
                        <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div class="absolute z-20 left-0 mt-1 w-60 bg-white rounded-md shadow-lg opacity-0 group-hover:opacity-100 group-focus:opacity-100 transition pointer-events-none group-hover:pointer-events-auto group-focus:pointer-events-auto">
                        <a href="{{ route('trend.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">Trend</a>
                        <a href="{{ route('oi-buildup.live') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">Live OI Build</a>
                        <a href="{{ route('option.chain') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">Option Chain</a>
                        <a href="{{ route('buildups.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">Buildups</a>
                        <a href="{{ route('buildups.strike') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">Buildup Strike</a>
                        <a href="{{ route('option-chain-diff') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">Option Chain Diff</a>
                        <a href="{{ route('option-chain.build-up') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">Option Buildup</a>
                        <a href="{{ route('option-chain.build-up-all') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">Option Buildup All</a>
                        <a href="{{ route('snipper-point') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">Snipper</a>
                        <a href="{{ route('option-straddle') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">Straddle</a>
                        <a href="{{ route('ohlc.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">OHLC</a>
                        <a href="{{ route('hlc.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">HLC</a>
                        <a href="{{ route('hlc.close') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">HLC Close</a>
                        <a href="{{ route('hlc.close') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100"></a>
                    </div>
                </div>

                <div class="relative group">
                    <button class="inline-flex items-center px-3 py-2 rounded hover:bg-blue-50 focus:outline-none text-gray-700">
                        Strategies
                        <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div class="absolute z-20 left-0 mt-1 w-60 bg-white rounded-md shadow-lg opacity-0 group-hover:opacity-100 group-focus:opacity-100 transition pointer-events-none group-hover:pointer-events-auto group-focus:pointer-events-auto">
                        <a href="{{ route('oi-buildup.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">OI Builups</a>
                        <a href="{{ route('backtests.six-level.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">Six Levels</a>
                        <a href="{{ route('backtests.straddles.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">Straddle</a>
                        <a href="{{ route('backtests.futures.ohlc.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">Future</a>
                        <a href="{{ route('analysis.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">ATM Analysis</a>
                        <a href="{{ route('strangle.profit') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">Monthly Strangle</a>
                        <a href="{{ route('entries.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">Entry</a>
                        <a href="{{ route('options.chart') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">Chart</a>
                        <a href="{{ route('index.futures.chart') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">Index Chart</a>
                        <a href="{{ route('options.multi.chart') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100">Multi Chart</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>
