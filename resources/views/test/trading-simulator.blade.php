{{-- resources/views/test/trading-simulator.blade.php --}}
@extends('layouts.app')

@section('title', 'Simulation')

@section('content')

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <style>
        #trading-simulator-root { background: #030712; }
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }
        .flatpickr-calendar { font-family: inherit !important; }
        [x-cloak] { display: none !important; }

        /* Override Quill dark theme */
        .ql-toolbar.ql-snow { background: #1f2937; border-color: #374151 !important; border-radius: 8px 8px 0 0; }
        .ql-container.ql-snow { background: #111827; border-color: #374151 !important; border-radius: 0 0 8px 8px; color: #e5e7eb; min-height: 80px; }
        .ql-snow .ql-stroke { stroke: #9ca3af; }
        .ql-snow .ql-fill { fill: #9ca3af; }
        .ql-snow .ql-picker { color: #9ca3af; }
        .ql-snow .ql-picker-options { background: #1f2937; }
    </style>

    <div id="trading-simulator-root" class="min-h-screen bg-gray-950 py-5">
        <div id="sim-app" class="max-w-7xl mx-auto px-4">

            {{-- HEADER --}}
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-blue-600 rounded-xl flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-base font-bold text-white leading-tight">Nifty Options Simulator</h1>
                        <p class="text-xs text-gray-500">Lot Size: 75 &nbsp;&nbsp; Paper Trading Mode</p>
                    </div>

                    <div>
                        <a href="{{ route('test.trading-simulator.report') }}"
                            class="text-xs bg-gray-800 hover:bg-gray-700 border border-gray-700 text-gray-300 rounded-xl px-4 py-2 transition-colors flex items-center gap-1.5">
                            Simulator Report
                        </a>
                    </div>
                </div>
                <div class="text-right">
                    <div id="display-current-time" class="text-2xl font-mono font-bold text-blue-400 leading-tight">--:--</div>
                    <div class="text-xs text-gray-500">Simulated Time</div>
                </div>
            </div>

            {{-- CONTROLS BAR --}}
            <div class="bg-gray-900 border border-gray-800 rounded-2xl px-4 py-3 mb-3">
                <div class="flex items-center gap-3 flex-wrap">

                    {{-- Date Picker --}}
                    <div class="relative flex-shrink-0">
                        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-blue-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <input type="text" id="tradingDatePicker" placeholder="Pick date" readonly
                            class="bg-gray-800 border border-gray-700 hover:border-blue-500 focus:border-blue-500 rounded-xl pl-8 pr-3 py-2 text-sm text-white w-40 focus:outline-none focus:ring-2 focus:ring-blue-500/30 cursor-pointer placeholder-gray-500 transition-colors">
                    </div>

                    <div class="h-6 w-px bg-gray-700 flex-shrink-0 hidden sm:block"></div>

                    {{-- Expiry --}}
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="text-xs text-gray-500 font-medium">Expiry</span>
                        <span id="expiry-placeholder"
                            class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-1 text-xs text-gray-500 font-mono min-w-[90px] text-center inline-block">
            —
          </span>
                        <span id="expiry-value"
                            class="bg-indigo-950 border border-indigo-600 rounded-lg px-3 py-1 text-xs font-bold text-indigo-300 font-mono min-w-[90px] text-center hidden">
          </span>
                    </div>

                    <div class="h-6 w-px bg-gray-700 flex-shrink-0 hidden sm:block"></div>

                    {{-- Time Navigation --}}
                    <div class="flex items-center gap-1.5 flex-shrink-0">
                        <button id="btn-rewind"
                            class="bg-gray-800 hover:bg-gray-700 active:scale-95 disabled:opacity-30 disabled:cursor-not-allowed border border-gray-700 hover:border-gray-500 rounded-lg px-2.5 py-1.5 flex items-center gap-1 text-xs font-semibold text-gray-300 transition-all duration-150">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                            </svg>
                            5m
                        </button>

                        <div id="display-time-pill"
                            class="bg-gray-950 border border-yellow-600/60 rounded-lg px-3 py-1.5 font-mono font-bold text-yellow-400 text-sm min-w-[68px] text-center tracking-wide">
                            --:--
                        </div>

                        <button id="btn-forward"
                            class="bg-gray-800 hover:bg-gray-700 active:scale-95 disabled:opacity-30 disabled:cursor-not-allowed border border-gray-700 hover:border-gray-500 rounded-lg px-2.5 py-1.5 flex items-center gap-1 text-xs font-semibold text-gray-300 transition-all duration-150">
                            5m
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>

                    <div class="h-6 w-px bg-gray-700 flex-shrink-0 hidden sm:block"></div>

                    {{-- P&L Pill --}}
                    <div class="flex-shrink-0 ml-auto">
                        <div id="pnl-pill" class="flex items-center gap-2 rounded-lg border px-3 py-1.5 transition-all duration-300 bg-gray-800 border-gray-700">
                            <span id="pnl-label" class="text-xs font-medium text-gray-400">P&amp;L</span>
                            <span id="pnl-value" class="font-mono font-bold text-sm text-gray-300">₹0.00</span>
                            <span id="pnl-arrow" class="text-xs leading-none text-gray-600"></span>
                        </div>
                    </div>

                </div>
            </div>

            {{-- ADD STRIKE PANEL --}}
            <div id="add-strike-panel" class="bg-gray-900 border border-gray-800 rounded-2xl px-4 py-3 mb-3 hidden">
                <div class="flex items-end gap-3 flex-wrap">

                    {{-- Strike Search --}}
                    <div class="flex-shrink-0 w-44">
                        <label class="block text-xs text-gray-500 mb-1.5 font-medium uppercase tracking-wide">Strike</label>
                        <div class="relative">
                            <input type="text" id="strike-search-input" placeholder="Search" autocomplete="off"
                                class="w-full bg-gray-800 border border-gray-700 hover:border-blue-500 focus:border-blue-500 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500/30 transition-colors placeholder-gray-500">
                            <div id="strike-dropdown"
                                class="absolute z-50 mt-1 w-full bg-gray-800 border border-gray-700 rounded-xl shadow-2xl max-h-48 overflow-y-auto hidden">
                            </div>
                        </div>
                    </div>

                    {{-- CE / PE Toggle --}}
                    <div class="flex-shrink-0">
                        <label class="block text-xs text-gray-500 mb-1.5 font-medium uppercase tracking-wide">Type</label>
                        <div class="flex bg-gray-800 border border-gray-700 rounded-xl p-0.5 gap-0.5">
                            <button data-type="CE" id="btn-type-CE"
                                class="type-toggle px-4 py-1.5 rounded-lg text-xs font-bold transition-all duration-150 bg-blue-600 text-white">CE</button>
                            <button data-type="PE" id="btn-type-PE"
                                class="type-toggle px-4 py-1.5 rounded-lg text-xs font-bold transition-all duration-150 text-gray-400 hover:text-white">PE</button>
                        </div>
                    </div>

                    {{-- Lots --}}
                    <div class="flex-shrink-0">
                        <label class="block text-xs text-gray-500 mb-1.5 font-medium uppercase tracking-wide">Lots</label>
                        <div class="flex items-center bg-gray-800 border border-gray-700 rounded-xl overflow-hidden">
                            <button id="btn-lots-dec" class="px-3 py-2 text-gray-400 hover:text-white hover:bg-gray-700 transition-colors text-sm font-bold leading-none select-none">−</button>
                            <input type="number" id="input-lots" min="1" value="1"
                                class="w-10 bg-transparent text-center text-sm font-bold text-white focus:outline-none border-x border-gray-700 py-2">
                            <button id="btn-lots-inc" class="px-3 py-2 text-gray-400 hover:text-white hover:bg-gray-700 transition-colors text-sm font-bold leading-none select-none">+</button>
                        </div>
                    </div>

                    {{-- BUY / SELL Toggle --}}
                    <div class="flex-shrink-0">
                        <label class="block text-xs text-gray-500 mb-1.5 font-medium uppercase tracking-wide">Action</label>
                        <div class="flex bg-gray-800 border border-gray-700 rounded-xl p-0.5 gap-0.5">
                            <button data-action="BUY" id="btn-action-BUY"
                                class="action-toggle px-4 py-1.5 rounded-lg text-xs font-bold transition-all duration-150 bg-emerald-600 text-white">BUY</button>
                            <button data-action="SELL" id="btn-action-SELL"
                                class="action-toggle px-4 py-1.5 rounded-lg text-xs font-bold transition-all duration-150 text-gray-400 hover:text-white">SELL</button>
                        </div>
                    </div>

                    {{-- Add Strike Button --}}
                    <div class="flex-shrink-0">
                        <label class="block text-xs text-gray-500 mb-1.5 font-medium uppercase tracking-wide opacity-0 select-none">Add</label>
                        <button id="btn-add-strike" disabled
                            class="bg-blue-600 hover:bg-blue-500 active:bg-blue-700 disabled:opacity-30 disabled:cursor-not-allowed text-white rounded-xl px-5 py-2 text-sm font-semibold transition-all duration-150 flex items-center gap-2 shadow-lg shadow-blue-900/30 whitespace-nowrap">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add Strike
                        </button>
                    </div>

                </div>
            </div>

            {{-- OPEN POSITIONS TABLE --}}
            <div id="positions-section" class="mb-3 hidden">
                <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-800 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse inline-block"></span>
                            Open Positions
                            <span id="positions-count" class="bg-blue-600 text-white text-xs px-2 py-0.5 rounded-full font-mono">0</span>
                        </h2>
                        <button id="btn-square-off-all"
                            class="text-xs text-red-400 hover:text-red-300 border border-red-900 hover:border-red-700 bg-red-950/40 hover:bg-red-950/70 rounded-lg px-3 py-1.5 transition-all font-medium">
                            Square Off All
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                            <tr class="text-xs text-gray-500 border-b border-gray-800 bg-gray-900/60">
                                <th class="px-4 py-2.5 text-left font-medium">Strike</th>
                                <th class="px-4 py-2.5 text-left font-medium">Type</th>
                                <th class="px-4 py-2.5 text-left font-medium">Side</th>
                                <th class="px-4 py-2.5 text-right font-medium">Avg Entry</th>
                                <th class="px-4 py-2.5 text-right font-medium">Lots</th>
                                <th class="px-4 py-2.5 text-right font-medium">Qty</th>
                                <th class="px-4 py-2.5 text-right font-medium">Current</th>
                                <th class="px-4 py-2.5 text-right font-medium text-yellow-500">Diff (pts)</th>  {{-- NEW --}}
                                <th class="px-4 py-2.5 text-right font-medium">P&amp;L</th>
                                <th class="px-4 py-2.5 text-center font-medium">Actions</th>
                            </tr>
                            </thead>

                            <tbody id="positions-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- CLOSED TRADES LOG --}}
            <div id="trades-section" class="hidden">
                <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-800">
                        <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            Trade History
                            <span id="trades-count" class="bg-gray-700 text-gray-300 text-xs px-2 py-0.5 rounded-full font-mono">0</span>
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                            <tr class="text-xs text-gray-500 border-b border-gray-800 bg-gray-900/60">
                                <th class="px-4 py-2.5 text-left font-medium">Strike / Type</th>
                                <th class="px-4 py-2.5 text-left font-medium">Side</th>
                                <th class="px-4 py-2.5 text-right font-medium">Avg Entry</th>
                                <th class="px-4 py-2.5 text-right font-medium">Exit Price</th>
                                <th class="px-4 py-2.5 text-right font-medium">Lots</th>
                                <th class="px-4 py-2.5 text-right font-medium">Qty</th>
                                <th class="px-4 py-2.5 text-right font-medium">P&amp;L</th>
                                <th class="px-4 py-2.5 text-left font-medium">Outcome</th>   {{-- NEW --}}
                                <th class="px-4 py-2.5 text-right font-medium">Exit Time</th>
                                <th class="px-4 py-2.5 text-center font-medium">Note</th>    {{-- NEW --}}
                            </tr>
                            </thead>

                            <tbody id="trades-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- EMPTY STATE --}}
            <div id="empty-state" class="text-center py-24 text-gray-700">
                <svg class="w-14 h-14 mx-auto mb-4 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <p class="text-base font-semibold text-gray-500">Select a trading date to begin</p>
                <p class="text-sm mt-1 text-gray-600">Choose a date from the calendar above</p>
            </div>

            {{-- AVERAGE POSITION MODAL --}}
            <div id="average-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4 hidden">
                <div id="average-modal-inner" class="bg-gray-900 border border-gray-700 rounded-2xl p-6 w-full max-w-sm shadow-2xl">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-base font-bold text-white">Average Position</h3>
                            <p id="avg-modal-subtitle" class="text-xs text-gray-400 mt-0.5"></p>
                        </div>
                        <button id="btn-avg-modal-close" class="text-gray-600 hover:text-gray-300 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs text-gray-400 mb-1.5 font-medium">Additional Lots</label>
                            <div class="flex items-center bg-gray-800 border border-gray-700 rounded-xl overflow-hidden">
                                <button id="btn-avg-lots-dec" class="px-4 py-2.5 text-gray-400 hover:text-white hover:bg-gray-700 transition-colors font-bold select-none">−</button>
                                <input type="number" id="avg-lots-input" min="1" value="1"
                                    class="flex-1 bg-transparent text-center text-sm font-bold text-white focus:outline-none border-x border-gray-700 py-2.5">
                                <button id="btn-avg-lots-inc" class="px-4 py-2.5 text-gray-400 hover:text-white hover:bg-gray-700 transition-colors font-bold select-none">+</button>
                            </div>
                        </div>
                        <div class="bg-gray-800/60 border border-gray-700 rounded-xl p-3 space-y-2">
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-500">Current Avg Entry</span>
                                <span id="avg-current-entry" class="font-mono text-gray-200">—</span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-500">Current Candle Open</span>
                                <span id="avg-current-price" class="font-mono text-gray-200">—</span>
                            </div>
                            <div class="flex justify-between text-xs pt-2 border-t border-gray-700">
                                <span class="text-gray-400 font-medium">New Avg Entry</span>
                                <span id="avg-new-entry" class="font-mono font-bold text-yellow-400">—</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2 mt-5">
                        <button id="btn-avg-cancel"
                            class="flex-1 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-xl py-2.5 text-sm font-medium text-gray-300 transition-colors">Cancel</button>
                        <button id="btn-avg-confirm"
                            class="flex-1 bg-yellow-600 hover:bg-yellow-500 active:bg-yellow-700 rounded-xl py-2.5 text-sm font-semibold text-white transition-colors">Confirm Average</button>
                    </div>
                </div>
            </div>

        </div>

        {{-- ===== EXIT MODAL ===== --}}
        <div id="exit-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4 hidden">
            <div class="bg-gray-900 border border-gray-700 rounded-2xl p-6 w-full max-w-md shadow-2xl">

                {{-- Header --}}
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base font-bold text-white">Exit Position</h3>
                        <p id="exit-modal-subtitle" class="text-xs text-gray-400 mt-0.5 font-mono"></p>
                    </div>
                    <button id="btn-exit-modal-close" class="text-gray-600 hover:text-gray-300 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">

                    {{-- Current Price Info --}}
                    <div class="bg-gray-800/60 border border-gray-700 rounded-xl p-3 flex justify-between text-xs">
                        <span class="text-gray-500">Avg Entry</span>
                        <span id="exit-avg-entry" class="font-mono text-gray-200"></span>
                        <span class="text-gray-500">Current Price</span>
                        <span id="exit-current-price" class="font-mono text-yellow-400 font-bold"></span>
                    </div>

                    {{-- Exit Qty (Lots) --}}
                    <div>
                        <label class="block text-xs text-gray-400 mb-1.5 font-medium uppercase tracking-wide">
                            Exit Lots <span class="normal-case text-gray-600">(max: <span id="exit-max-lots"></span> lots)</span>
                        </label>
                        <div class="flex items-center bg-gray-800 border border-gray-700 rounded-xl overflow-hidden w-36">
                            <button id="btn-exit-lots-dec" class="px-4 py-2.5 text-gray-400 hover:text-white hover:bg-gray-700 transition-colors font-bold select-none">−</button>
                            <input type="number" id="exit-lots-input" min="1" value="1"
                                class="flex-1 bg-transparent text-center text-sm font-bold text-white focus:outline-none border-x border-gray-700 py-2.5">
                            <button id="btn-exit-lots-inc" class="px-4 py-2.5 text-gray-400 hover:text-white hover:bg-gray-700 transition-colors font-bold select-none">+</button>
                        </div>
                    </div>

                    {{-- Outcome --}}
                    <div>
                        <label class="block text-xs text-gray-400 mb-1.5 font-medium uppercase tracking-wide">Outcome</label>
                        <div class="flex gap-2">
                            <button data-outcome="profit"
                                class="outcome-toggle flex-1 py-1.5 rounded-lg text-xs font-bold border transition-all
                         bg-emerald-900/40 text-emerald-400 border-emerald-800/60 hover:bg-emerald-900/70">
                                ✓ Profit
                            </button>
                            <button data-outcome="stoploss"
                                class="outcome-toggle flex-1 py-1.5 rounded-lg text-xs font-bold border transition-all
                         text-gray-400 border-gray-700 hover:text-red-400 hover:border-red-800">
                                ✗ Stoploss
                            </button>
                            <button data-outcome="breakeven"
                                class="outcome-toggle flex-1 py-1.5 rounded-lg text-xs font-bold border transition-all
                         text-gray-400 border-gray-700 hover:text-yellow-400 hover:border-yellow-800">
                                ~ Breakeven
                            </button>
                        </div>
                    </div>

                    {{-- Comment (Quill) --}}
                    <div>
                        <label class="block text-xs text-gray-400 mb-1.5 font-medium uppercase tracking-wide">
                            Trade Comment <span class="normal-case text-gray-600">(optional — supports bold, color highlights)</span>
                        </label>
                        <div id="exit-comment-editor"></div>
                    </div>

                </div>

                {{-- Footer Buttons --}}
                <div class="flex gap-2 mt-5">
                    <button id="btn-exit-cancel"
                        class="flex-1 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-xl py-2.5 text-sm font-medium text-gray-300 transition-colors">
                        Cancel
                    </button>
                    <button id="btn-exit-confirm"
                        class="flex-1 bg-red-600 hover:bg-red-500 active:bg-red-700 rounded-xl py-2.5 text-sm font-semibold text-white transition-colors">
                        Confirm Exit
                    </button>
                </div>

            </div>
        </div>

    </div>

    <script>
        jQuery(document).ready(function ($) {
            const LOTSIZE = 75;
            const STARTMINUTES = 9 * 60 + 15;   // 555 = 09:15
            const ENDMINUTES   = 15 * 60 + 30;  // 930 = 15:30
            const STEPMINUTES  = 5;

            // ── Session ID (UUID per browser tab, persisted in localStorage) ──────────
            function generateUUID() {
                return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                    const r = Math.random() * 16 | 0;
                    return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
                });
            }
            const SESSION_KEY = 'sim_session_id';
            if (!localStorage.getItem(SESSION_KEY)) {
                localStorage.setItem(SESSION_KEY, generateUUID());
            }
            const sessionId = localStorage.getItem(SESSION_KEY);


            // ─── State ──────────────────────────────────────────────────────────────
            const state = {
                selectedDate: null,
                selectedExpiry: null,
                availableStrikes: [],
                currentMinutes: STARTMINUTES,
                newStrike: null,
                newType: 'CE',
                newLots: 1,
                newAction: 'BUY',
                positions: [],
                closedTrades: [],
                averageModal: { open: false, idx: null, lots: 1 },
                exitModal: { open: false, idx: null, lots: 1, outcome: 'profit' },
            };

            // ─── Helpers ────────────────────────────────────────────────────────────
            function minutesToTime(m) {
                const h = Math.floor(m / 60);
                const min = m % 60;
                return String(h).padStart(2, '0') + ':' + String(min).padStart(2, '0');
            }

            function currentTimestamp() {
                return state.selectedDate + ' ' + minutesToTime(state.currentMinutes) + ':00';
            }

            function calcPnl(pos) {
                if (!pos.currentPrice) return 0;
                const qty = pos.lots * LOTSIZE;
                const diff = pos.action === 'BUY'
                    ? pos.currentPrice - pos.avgEntry
                    : pos.avgEntry - pos.currentPrice;
                return parseFloat((diff * qty).toFixed(2));
            }

            function calcNewAverage() {
                const pos = state.positions[state.averageModal.idx];
                if (!pos || !pos.currentPrice) return null;
                const oldQty = pos.lots * LOTSIZE;
                const newQty = state.averageModal.lots * LOTSIZE;
                return ((pos.avgEntry * oldQty + pos.currentPrice * newQty) / (oldQty + newQty)).toFixed(2);
            }

            function totalPnl() {
                const open   = state.positions.reduce((s, p) => s + (p.pnl || 0), 0);
                const closed = state.closedTrades.reduce((s, t) => s + (t.pnl || 0), 0);
                return open + closed;
            }

            // ─── Render ─────────────────────────────────────────────────────────────
            function render() {
                const time = minutesToTime(state.currentMinutes);
                $('#display-current-time, #display-time-pill').text(time);

                // Nav buttons
                $('#btn-rewind').prop('disabled', !state.selectedDate || state.currentMinutes <= STARTMINUTES);
                $('#btn-forward').prop('disabled', !state.selectedDate || state.currentMinutes >= ENDMINUTES);

                // Expiry
                if (state.selectedExpiry) {
                    $('#expiry-placeholder').addClass('hidden');
                    $('#expiry-value').removeClass('hidden').text(state.selectedExpiry);
                    $('#add-strike-panel').removeClass('hidden');
                } else {
                    $('#expiry-placeholder').removeClass('hidden');
                    $('#expiry-value').addClass('hidden');
                    $('#add-strike-panel').addClass('hidden');
                }

                // Empty state
                if (!state.selectedDate) {
                    $('#empty-state').show();
                } else {
                    $('#empty-state').hide();
                }

                // P&L pill
                const pnl = totalPnl();
                const $pill = $('#pnl-pill');
                $pill.removeClass('bg-emerald-950 border-emerald-700 bg-red-950 border-red-800 bg-gray-800 border-gray-700');
                $('#pnl-label').removeClass('text-emerald-400 text-red-400 text-gray-400');
                $('#pnl-value').removeClass('text-emerald-300 text-red-400 text-gray-300');
                $('#pnl-arrow').removeClass('text-emerald-400 text-red-400 text-gray-600').text('');

                if (pnl > 0) {
                    $pill.addClass('bg-emerald-950 border-emerald-700');
                    $('#pnl-label').addClass('text-emerald-400');
                    $('#pnl-value').addClass('text-emerald-300');
                    $('#pnl-arrow').addClass('text-emerald-400').text('▲');
                } else if (pnl < 0) {
                    $pill.addClass('bg-red-950 border-red-800');
                    $('#pnl-label').addClass('text-red-400');
                    $('#pnl-value').addClass('text-red-400');
                    $('#pnl-arrow').addClass('text-red-400').text('▼');
                } else {
                    $pill.addClass('bg-gray-800 border-gray-700');
                    $('#pnl-label').addClass('text-gray-400');
                    $('#pnl-value').addClass('text-gray-300');
                    $('#pnl-arrow').addClass('text-gray-600');
                }
                $('#pnl-value').text((pnl >= 0 ? '▲ ' : '') + pnl.toLocaleString('en-IN', { minimumFractionDigits: 2 }));

                // Add strike button
                $('#btn-add-strike').prop('disabled', !state.newStrike);

                // Positions
                renderPositions();
                renderTrades();
            }

            function renderPositions() {
                const tbody = $('#positions-tbody').empty();
                if (state.positions.length === 0) {
                    $('#positions-section').addClass('hidden');
                } else {
                    $('#positions-section').removeClass('hidden');
                    $('#positions-count').text(state.positions.length);
                }

                state.positions.forEach(function (pos, idx) {
                    if (!pos.orders) pos.orders = [];
                    const pnlClass   = pos.pnl >= 0 ? 'text-emerald-400' : 'text-red-400';
                    const typeClass  = pos.type === 'CE'
                        ? 'bg-blue-900/60 text-blue-300 border border-blue-800'
                        : 'bg-orange-900/60 text-orange-300 border border-orange-800';
                    const sideClass  = pos.action === 'BUY'
                        ? 'bg-emerald-900/60 text-emerald-300 border border-emerald-800'
                        : 'bg-red-900/60 text-red-300 border border-red-800';
                    const pnlDisplay = pos.currentPrice
                        ? (pos.pnl >= 0 ? '+' : '') + pos.pnl.toLocaleString('en-IN', { minimumFractionDigits: 2 })
                        : '—';
                    const currentDisplay = pos.currentPrice ? pos.currentPrice.toFixed(2) : '—';

                    // Diff calculation
                    const diff = (pos.currentPrice && pos.avgEntry)
                        ? (pos.currentPrice - pos.avgEntry).toFixed(2)
                        : null;
                    const diffDisplay = diff !== null
                        ? (diff >= 0 ? '+' : '') + diff
                        : '—';
                    const diffClass = diff === null ? 'text-gray-600'
                        : diff >= 15  ? 'text-emerald-400 font-bold'
                            : diff <= -15 ? 'text-red-400 font-bold'
                                : 'text-yellow-400';

                    const ordersCount = (pos.orders || []).length;

                    const row = $(`
            <tr class="border-b border-gray-800/60 hover:bg-gray-800/30 transition-colors" data-idx="${idx}">
              <td class="px-4 py-3 font-mono font-semibold text-white">${pos.strike}</td>
              <td class="px-4 py-3"><span class="text-xs font-bold px-2 py-0.5 rounded-md ${typeClass}">${pos.type}</span></td>
              <td class="px-4 py-3"><span class="text-xs font-bold px-2 py-0.5 rounded-md ${sideClass}">${pos.action}</span></td>
              <td class="px-4 py-3 text-right font-mono text-gray-200">${pos.avgEntry.toFixed(2)}</td>
              <td class="px-4 py-3 text-right text-gray-200">${pos.lots}</td>
              <td class="px-4 py-3 text-right text-gray-500 font-mono">${pos.lots * LOTSIZE}</td>
              <td class="px-4 py-3 text-right font-mono text-gray-200">${currentDisplay}</td>
              <td class="px-4 py-3 text-right font-mono ${diffClass}">${diffDisplay}</td>
              <td class="px-4 py-3 text-right font-mono font-semibold ${pnlClass}">${pnlDisplay}</td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-center gap-1.5">
                  <button class="btn-avg-open text-xs bg-yellow-900/40 hover:bg-yellow-900/70 text-yellow-400 border border-yellow-800/60 hover:border-yellow-700 rounded-lg px-2.5 py-1 transition-all font-medium" data-idx="${idx}">Avg</button>
                  <button class="btn-orders-toggle text-xs bg-gray-800/60 hover:bg-gray-700 text-gray-400 border border-gray-700 hover:border-gray-500 rounded-lg px-2.5 py-1 transition-all font-medium" data-idx="${idx}">
                    Orders <span class="bg-gray-700 text-gray-300 rounded-full px-1.5 py-0.5 text-xs font-mono">${ordersCount}</span>
                  </button>
                  <button class="btn-exit-open text-xs bg-red-900/40 hover:bg-red-900/70 text-red-400 border border-red-800/60 hover:border-red-700 rounded-lg px-2.5 py-1 transition-all font-medium" data-idx="${idx}">Exit</button>
                </div>
              </td>
            </tr>
        `);
                    tbody.append(row);

                    // Orders sub-row (collapsed by default)
                    if (pos.showOrders && ordersCount > 0) {
                        const ordersHtml = (pos.orders || []).map((o, i) => `
                <tr class="text-xs text-gray-400">
                  <td class="py-1 pr-4 font-mono text-gray-600">#${i + 1}</td>
                  <td class="py-1 pr-4">
                    <span class="px-2 py-0.5 rounded text-xs font-bold ${
                            o.type === 'entry' ? 'bg-blue-900/60 text-blue-300'
                                : o.type === 'partial_exit' ? 'bg-orange-900/60 text-orange-300'
                                    : 'bg-red-900/60 text-red-300'
                        }">${o.type.replace('_', ' ').toUpperCase()}</span>
                  </td>
                  <td class="py-1 pr-4 font-mono">${o.side}</td>
                  <td class="py-1 pr-4 font-mono">${o.price.toFixed(2)}</td>
                  <td class="py-1 pr-4">${o.lots} lots (${o.qty} qty)</td>
                  <td class="py-1 pr-4 font-mono ${o.pnl >= 0 ? 'text-emerald-400' : 'text-red-400'}">${o.pnl !== 0 ? (o.pnl >= 0 ? '+' : '') + o.pnl.toFixed(2) : '—'}</td>
                  <td class="py-1 text-gray-600">${o.time}</td>
                </tr>
            `).join('');

                        tbody.append(`
                <tr class="bg-gray-950/60 border-b border-gray-800/40">
                  <td colspan="10" class="px-8 py-2">
                    <table class="w-full">
                      <thead><tr class="text-xs text-gray-600 border-b border-gray-800/60">
                        <th class="py-1 pr-4 text-left font-medium">#</th>
                        <th class="py-1 pr-4 text-left font-medium">Type</th>
                        <th class="py-1 pr-4 text-left font-medium">Side</th>
                        <th class="py-1 pr-4 text-left font-medium">Price</th>
                        <th class="py-1 pr-4 text-left font-medium">Qty</th>
                        <th class="py-1 pr-4 text-left font-medium">P&L</th>
                        <th class="py-1 text-left font-medium">Time</th>
                      </tr></thead>
                      <tbody>${ordersHtml}</tbody>
                    </table>
                  </td>
                </tr>
            `);
                    }
                });
            }


            function renderTrades() {
                const tbody = $('#trades-tbody').empty();
                if (state.closedTrades.length === 0) {
                    $('#trades-section').addClass('hidden');
                } else {
                    $('#trades-section').removeClass('hidden');
                    $('#trades-count').text(state.closedTrades.length);
                }

                state.closedTrades.forEach(function (trade) {
                    const pnlClass  = trade.pnl >= 0 ? 'text-emerald-400' : 'text-red-400';
                    const sideClass = trade.action === 'BUY'
                        ? 'bg-emerald-900/60 text-emerald-300 border border-emerald-800'
                        : 'bg-red-900/60 text-red-300 border border-red-800';

                    const outcomeBadge = {
                        profit:    'bg-emerald-900/60 text-emerald-400 border-emerald-800/60',
                        stoploss:  'bg-red-900/60 text-red-400 border-red-800/60',
                        breakeven: 'bg-yellow-900/60 text-yellow-400 border-yellow-800/60',
                    }[trade.outcome] || 'bg-gray-800 text-gray-500 border-gray-700';

                    const outcomeLabel = {
                        profit: '✓ Profit', stoploss: '✗ SL', breakeven: '~ B/E'
                    }[trade.outcome] || '—';

                    const isPartial = trade.exitType === 'partial_exit';
                    const typeLabel = isPartial
                        ? '<span class="text-xs bg-orange-900/60 text-orange-300 border border-orange-800/60 rounded px-1.5 py-0.5 ml-1">PARTIAL</span>'
                        : '<span class="text-xs bg-red-900/60 text-red-300 border border-red-800/60 rounded px-1.5 py-0.5 ml-1">CLOSED</span>';

                    const noteIcon = trade.comment
                        ? `<button class="btn-view-note text-blue-400 hover:text-blue-300 transition-colors" title="View Note">
                 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                     d="M7 8h10M7 12h6m-6 4h10M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                 </svg>
               </button>`
                        : '<span class="text-gray-700 text-xs">—</span>';

                    tbody.append(`
            <tr class="border-b border-gray-800/60 hover:bg-gray-800/20 transition-colors">
              <td class="px-4 py-3">
                <span class="font-mono font-semibold text-gray-200">${trade.strike} ${trade.type}</span>
                ${typeLabel}
              </td>
              <td class="px-4 py-3"><span class="text-xs font-bold px-2 py-0.5 rounded-md ${sideClass}">${trade.action}</span></td>
              <td class="px-4 py-3 text-right font-mono text-gray-300">${trade.avgEntry.toFixed(2)}</td>
              <td class="px-4 py-3 text-right font-mono text-gray-300">${trade.exitPrice.toFixed(2)}</td>
              <td class="px-4 py-3 text-right text-gray-300">${trade.lots}</td>
              <td class="px-4 py-3 text-right font-mono text-gray-500">${trade.lots * LOTSIZE}</td>
              <td class="px-4 py-3 text-right font-mono font-semibold ${pnlClass}">
                ${trade.pnl >= 0 ? '+' : ''}${trade.pnl.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
              </td>
              <td class="px-4 py-3">
                <span class="text-xs font-bold px-2 py-0.5 rounded-md border ${outcomeBadge}">${outcomeLabel}</span>
              </td>
              <td class="px-4 py-3 text-right font-mono text-xs text-gray-500">${trade.exitTime}</td>
              <td class="px-4 py-3 text-center">${noteIcon}</td>
            </tr>
        `);

                    // Note preview row (hidden by default, shown on note icon click)
                    if (trade.comment) {
                        tbody.append(`
                <tr class="note-preview-row hidden bg-gray-950/80 border-b border-gray-800/40">
                  <td colspan="10" class="px-6 py-3">
                    <div class="prose prose-invert prose-sm max-w-none bg-gray-900 border border-gray-700 rounded-xl p-3 text-gray-300 text-sm">
                      ${trade.comment}
                    </div>
                  </td>
                </tr>
            `);
                    }
                });

                // Toggle note preview rows
                $('#trades-tbody').on('click', '.btn-view-note', function () {
                    $(this).closest('tr').next('.note-preview-row').toggleClass('hidden');
                });
            }


            // ─── API Calls ──────────────────────────────────────────────────────────
            async function fetchExpiry(date) {
                const res  = await fetch(`{{ route('test.trading-simulator.expiry') }}?date=${date}`);
                const data = await res.json();
                return data.expiry ?? null;
            }

            async function fetchStrikes(date, expiry) {
                const res  = await fetch(`{{ route('test.trading-simulator.strikes') }}?date=${date}&expiry=${expiry}`);
                const data = await res.json();
                console.log('fetchStrikes raw response:', data);  // ADD THIS
                return Array.isArray(data.strikes) ? data.strikes : [];
            }


            async function fetchPrice(strike, type) {
                const ts  = encodeURIComponent(currentTimestamp());
                const res = await fetch(`{{ route('test.trading-simulator.price') }}?expiry=${state.selectedExpiry}&strike=${strike}&type=${type}&timestamp=${ts}`);
                const data = await res.json();
                return data.open !== undefined ? parseFloat(data.open) : null;
            }


            async function refreshPrices() {
                for (let pos of state.positions) {
                    const price = await fetchPrice(pos.strike, pos.type);
                    if (price !== null) {
                        pos.currentPrice = price;
                        pos.pnl = calcPnl(pos);
                    }
                }
                render();
            }

            async function onDateChange() {
                state.currentMinutes = STARTMINUTES;
                state.selectedExpiry  = null;
                state.positions   = [];
                state.closedTrades = [];
                if (!state.selectedDate) { render(); return; }
                state.selectedExpiry = await fetchExpiry(state.selectedDate);
                if (state.selectedExpiry) {
                    await refreshPrices();
                }
                render();
            }



            // ─── Flatpickr ──────────────────────────────────────────────────────────
            const enabledDates = @json($dates ?? []);
            const latestDate   = @json($latestDate ?? null);

            flatpickr('#tradingDatePicker', {
                dateFormat: 'Y-m-d',
                disableMobile: true,
                defaultDate: latestDate ?? null,
                enable: enabledDates,
                maxDate: 'today',
                onDayCreate(_, __, ___, dayElem) {
                    const d   = dayElem.dateObj;
                    const ymd = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                    if (ymd === latestDate) {
                        dayElem.style.position = 'relative';
                        dayElem.innerHTML += '<span style="position:absolute;bottom:2px;left:50%;transform:translateX(-50%);width:4px;height:4px;border-radius:50%;background:#60a5fa;display:block"></span>';
                    }
                },
                onChange(_, dateStr) {
                    state.selectedDate = dateStr;
                    onDateChange();
                },
            });

            if (latestDate) {
                state.selectedDate = latestDate;
                onDateChange();
            }

            // ─── Strike Search ──────────────────────────────────────────────────────
            function filterStrikes(query) {
                if (!query || !Array.isArray(state.availableStrikes)) return [];
                return state.availableStrikes
                    .filter(s => String(s).includes(query))
                    .slice(0, 20);
            }


            function renderDropdown(query) {
                const $dd = $('#strike-dropdown').empty();
                const results = filterStrikes(query);
                if (!results.length || !query) { $dd.addClass('hidden'); return; }
                results.forEach(function (s) {
                    $dd.append(`<div class="strike-option px-3 py-2 text-sm text-gray-200 hover:bg-blue-600 hover:text-white cursor-pointer font-mono transition-colors" data-strike="${s}">${s}</div>`);
                });
                $dd.removeClass('hidden');
            }

            // ── Save entry order to DB ─────────────────────────────────────────────────
            async function saveEntryOrder({ strike, type, action, avgEntry, entryPrice, lots, qty, orderType }) {
                try {
                    await $.ajax({
                        url:    '{{ route("test.trading-simulator.enter") }}',
                        method: 'POST',
                        data: {
                            _token:          '{{ csrf_token() }}',
                            session_id:      sessionId,
                            trade_date:      state.selectedDate,
                            expiry:          state.selectedExpiry,
                            strike:          strike,
                            instrument_type: type,
                            side:            action,
                            avg_entry:       avgEntry,
                            entry_price:     entryPrice,
                            lots:            lots,
                            qty:             qty,
                            order_type:      orderType,
                            entry_time:      minutesToTime(state.currentMinutes),
                        }
                    });
                } catch (e) {
                    console.warn('DB entry save failed (non-blocking):', e);
                }
            }


            $('#strike-search-input').on('input', function () {
                const val = $(this).val().trim();
                state.newStrike = val ? val : null;
                render();
            });


            $(document).on('click', '.strike-option', function () {
                state.newStrike = $(this).data('strike');
                $('#strike-search-input').val(state.newStrike);
                $('#strike-dropdown').addClass('hidden');
                render();
            });

            $(document).on('click', function (e) {
                if (!$(e.target).closest('#strike-search-input, #strike-dropdown').length) {
                    $('#strike-dropdown').addClass('hidden');
                }
            });

            // ─── Type Toggle ────────────────────────────────────────────────────────
            $(document).on('click', '.type-toggle', function () {
                state.newType = $(this).data('type');
                $('.type-toggle').removeClass('bg-blue-600 bg-orange-500 text-white').addClass('text-gray-400 hover:text-white');
                if (state.newType === 'CE') {
                    $('#btn-type-CE').addClass('bg-blue-600 text-white').removeClass('text-gray-400');
                } else {
                    $('#btn-type-PE').addClass('bg-orange-500 text-white').removeClass('text-gray-400');
                }
            });

            // ─── Action Toggle ──────────────────────────────────────────────────────
            $(document).on('click', '.action-toggle', function () {
                state.newAction = $(this).data('action');
                $('.action-toggle').removeClass('bg-emerald-600 bg-red-600 text-white').addClass('text-gray-400 hover:text-white');
                if (state.newAction === 'BUY') {
                    $('#btn-action-BUY').addClass('bg-emerald-600 text-white').removeClass('text-gray-400');
                } else {
                    $('#btn-action-SELL').addClass('bg-red-600 text-white').removeClass('text-gray-400');
                }
            });

            // ─── Lots ────────────────────────────────────────────────────────────────
            $('#btn-lots-dec').on('click', function () {
                state.newLots = Math.max(1, state.newLots - 1);
                $('#input-lots').val(state.newLots);
            });
            $('#btn-lots-inc').on('click', function () {
                state.newLots++;
                $('#input-lots').val(state.newLots);
            });
            $('#input-lots').on('change input', function () {
                state.newLots = Math.max(1, parseInt($(this).val()) || 1);
                $(this).val(state.newLots);
            });

            // ─── Time Navigation ─────────────────────────────────────────────────────
            $('#btn-forward').on('click', async function () {
                if (state.currentMinutes < ENDMINUTES) {
                    state.currentMinutes += STEPMINUTES;
                    await refreshPrices();
                }
            });
            $('#btn-rewind').on('click', async function () {
                if (state.currentMinutes > STARTMINUTES) {
                    state.currentMinutes -= STEPMINUTES;
                    await refreshPrices();
                }
            });

            // ─── Add Strike ──────────────────────────────────────────────────────────
            $('#btn-add-strike').on('click', async function () {
                if (!state.newStrike || !state.selectedExpiry) return;

                const entryPrice = await fetchPrice(state.newStrike, state.newType);
                if (entryPrice === null) {
                    alert('No data found for this strike at the current candle time.');
                    return;
                }

                const existing = state.positions.find(p =>
                    p.strike == state.newStrike &&
                    p.type === state.newType &&
                    p.action === state.newAction
                );

                if (existing) {
                    const oldQty   = existing.lots * LOTSIZE;
                    const newQty   = state.newLots * LOTSIZE;
                    const totalQty = oldQty + newQty;

                    existing.avgEntry     = (existing.avgEntry * oldQty + entryPrice * newQty) / totalQty;
                    existing.lots        += state.newLots;
                    existing.currentPrice = entryPrice;
                    existing.pnl          = calcPnl(existing);

                    if (!existing.orders) existing.orders = [];
                    existing.orders.push({
                        type:  'entry',
                        side:  existing.action,
                        price: entryPrice,
                        lots:  state.newLots,
                        qty:   state.newLots * LOTSIZE,
                        pnl:   0,
                        time:  minutesToTime(state.currentMinutes),
                    });

                    // ✅ Save averaging entry to DB
                    await saveEntryOrder({
                        strike:       existing.strike,
                        type:         existing.type,
                        action:       existing.action,
                        avgEntry:     existing.avgEntry,
                        entryPrice:   entryPrice,
                        lots:         state.newLots,
                        qty:          state.newLots * LOTSIZE,
                        orderType:    'entry',
                    });

                } else {
                    const newPos = {
                        strike:       parseInt(state.newStrike),
                        type:         state.newType,
                        action:       state.newAction,
                        lots:         state.newLots,
                        avgEntry:     entryPrice,
                        currentPrice: entryPrice,
                        pnl:          0,
                        showOrders:   false,
                        orders:       [],
                    };

                    newPos.orders.push({
                        type:  'entry',
                        side:  state.newAction,
                        price: entryPrice,
                        lots:  state.newLots,
                        qty:   state.newLots * LOTSIZE,
                        pnl:   0,
                        time:  minutesToTime(state.currentMinutes),
                    });

                    state.positions.push(newPos);

                    // ✅ Save new entry to DB
                    await saveEntryOrder({
                        strike:     parseInt(state.newStrike),
                        type:       state.newType,
                        action:     state.newAction,
                        avgEntry:   entryPrice,
                        entryPrice: entryPrice,
                        lots:       state.newLots,
                        qty:        state.newLots * LOTSIZE,
                        orderType:  'entry',
                    });
                }

                state.newStrike = null;
                $('#strike-search-input').val('');
                state.newLots = 1;
                $('#input-lots').val(1);
                render();
            });




            // ─── Exit Position ───────────────────────────────────────────────────────
            // function exitPosition(idx) {
            //     const pos = state.positions[idx];
            //     if (!pos.currentPrice) { alert('No current price available to exit.'); return; }
            //     state.closedTrades.push({
            //         strike: pos.strike, type: pos.type, action: pos.action,
            //         lots: pos.lots, avgEntry: pos.avgEntry,
            //         exitPrice: pos.currentPrice, pnl: pos.pnl,
            //         exitTime: minutesToTime(state.currentMinutes),
            //     });
            //     state.positions.splice(idx, 1);
            //     render();
            // }

            // $(document).on('click', '.btn-exit', function () {
            //     exitPosition(parseInt($(this).data('idx')));
            // });
// NEW ✅
            $('#btn-square-off-all').on('click', async function () {
                if (!state.positions.length) return;

                const confirmAll = confirm(`Square off all ${state.positions.length} position(s) at current price?`);
                if (!confirmAll) return;

                // Process all positions — iterate over a copy since we're modifying the array
                const toExit = [...state.positions];

                for (const pos of toExit) {
                    if (!pos.currentPrice) continue;

                    const exitLots  = pos.lots;
                    const exitQty   = exitLots * LOTSIZE;
                    const exitPrice = pos.currentPrice;
                    const multiplier = pos.action === 'BUY' ? 1 : -1;
                    const pnl = parseFloat(((exitPrice - pos.avgEntry) * exitQty * multiplier).toFixed(2));

                    // Push to Trade History
                    state.closedTrades.unshift({
                        strike:    pos.strike,
                        type:      pos.type,
                        action:    pos.action,
                        lots:      exitLots,
                        avgEntry:  pos.avgEntry,
                        exitPrice: exitPrice,
                        pnl:       pnl,
                        exitTime:  minutesToTime(state.currentMinutes),
                        exitType:  'full_exit',
                        outcome:   pnl >= 0 ? 'profit' : 'stoploss',
                        comment:   '',
                    });

                    // Save to DB (non-blocking)
                    try {
                        await $.ajax({
                            url:    '{{ route("test.trading-simulator.exit") }}',
                            method: 'POST',
                            data: {
                                _token:          '{{ csrf_token() }}',
                                session_id:      sessionId,
                                trade_date:      state.selectedDate,
                                expiry:          state.selectedExpiry,
                                strike:          pos.strike,
                                instrument_type: pos.type,
                                side:            pos.action,
                                avg_entry:       pos.avgEntry,
                                exit_price:      exitPrice,
                                exit_lots:       exitLots,
                                exit_qty:        exitQty,
                                pnl:             pnl,
                                order_type:      'full_exit',
                                outcome:         pnl >= 0 ? 'profit' : 'stoploss',
                                comment:         '',
                                exit_time:       minutesToTime(state.currentMinutes),
                                total_lots:      exitLots,
                                orders:          JSON.stringify(pos.orders || []),
                            }
                        });
                    } catch (e) {
                        console.warn('DB save failed for square off:', pos.strike, e);
                    }
                }

                // Clear all open positions
                state.positions = [];
                render();
            });


            // ─── Average Modal ────────────────────────────────────────────────────────
            function updateAvgModal() {
                const pos = state.positions[state.averageModal.idx];
                if (!pos) return;
                $('#avg-modal-subtitle').text(`${pos.strike} ${pos.type}`);
                $('#avg-current-entry').text(pos.avgEntry.toFixed(2));
                $('#avg-current-price').text(pos.currentPrice ? pos.currentPrice.toFixed(2) : '—');
                const newAvg = calcNewAverage();
                $('#avg-new-entry').text(newAvg ?? '—');
            }

            $(document).on('click', '.btn-avg-open', function () {
                state.averageModal.idx  = parseInt($(this).data('idx'));
                state.averageModal.lots = 1;
                $('#avg-lots-input').val(1);
                updateAvgModal();
                $('#average-modal').removeClass('hidden');
            });

            $('#btn-avg-modal-close, #btn-avg-cancel').on('click', function () {
                $('#average-modal').addClass('hidden');
            });

            $('#average-modal').on('click', function (e) {
                if ($(e.target).is('#average-modal')) $('#average-modal').addClass('hidden');
            });




            let exitQuill = null;

            jQuery(document).ready(function () {
                exitQuill = new Quill('#exit-comment-editor', {
                    theme: 'snow',
                    placeholder: 'Why did you exit? What was the setup, emotion, or reason...',
                    modules: {
                        toolbar: [
                            ['bold', 'italic', 'underline'],
                            [{ color: ['#f87171', '#34d399', '#fbbf24', '#60a5fa', '#e5e7eb'] }],
                            ['clean']
                        ]
                    }
                });
            });

            // Outcome toggle
            $(document).on('click', '.outcome-toggle', function () {
                const outcome = $(this).data('outcome');
                state.exitModal.outcome = outcome;
                $('.outcome-toggle').each(function () {
                    const o = $(this).data('outcome');
                    $(this).removeClass('bg-emerald-900/40 text-emerald-400 border-emerald-800/60 bg-red-900/40 text-red-400 border-red-800/60 bg-yellow-900/40 text-yellow-400 border-yellow-800/60 text-gray-400 border-gray-700');
                    if (o === outcome) {
                        const activeClass = { profit: 'bg-emerald-900/40 text-emerald-400 border-emerald-800/60', stoploss: 'bg-red-900/40 text-red-400 border-red-800/60', breakeven: 'bg-yellow-900/40 text-yellow-400 border-yellow-800/60' }[o];
                        $(this).addClass(activeClass);
                    } else {
                        $(this).addClass('text-gray-400 border-gray-700');
                    }
                });
            });

            // Open Exit Modal
            $(document).on('click', '.btn-exit-open', function () {
                const idx = parseInt($(this).data('idx'));
                const pos = state.positions[idx];
                if (!pos || !pos.currentPrice) {
                    alert('No current price available to exit.');
                    return;
                }
                state.exitModal.idx  = idx;
                state.exitModal.lots = pos.lots; // default to full exit
                state.exitModal.outcome = 'profit';

                $('#exit-modal-subtitle').text(`${pos.strike} ${pos.type} · ${pos.action}`);
                $('#exit-avg-entry').text(pos.avgEntry.toFixed(2));
                $('#exit-current-price').text(pos.currentPrice.toFixed(2));
                $('#exit-max-lots').text(pos.lots);
                $('#exit-lots-input').val(pos.lots).attr('max', pos.lots);

                // Reset Quill
                if (exitQuill) exitQuill.setText('');

                // Reset outcome UI
                $('.outcome-toggle').removeClass('bg-emerald-900/40 text-emerald-400 border-emerald-800/60 bg-red-900/40 text-red-400 border-red-800/60 bg-yellow-900/40 text-yellow-400 border-yellow-800/60')
                    .addClass('text-gray-400 border-gray-700');
                $('[data-outcome="profit"]').removeClass('text-gray-400 border-gray-700')
                    .addClass('bg-emerald-900/40 text-emerald-400 border-emerald-800/60');

                $('#exit-modal').removeClass('hidden');
            });

            $('#btn-exit-lots-dec').on('click', function () {
                const cur = parseInt($('#exit-lots-input').val()) || 1;
                if (cur > 1) $('#exit-lots-input').val(cur - 1);
                state.exitModal.lots = parseInt($('#exit-lots-input').val());
            });
            $('#btn-exit-lots-inc').on('click', function () {
                const cur  = parseInt($('#exit-lots-input').val()) || 1;
                const max  = state.positions[state.exitModal.idx]?.lots || 1;
                if (cur < max) $('#exit-lots-input').val(cur + 1);
                state.exitModal.lots = parseInt($('#exit-lots-input').val());
            });
            $('#exit-lots-input').on('change input', function () {
                const max = state.positions[state.exitModal.idx]?.lots || 1;
                const val = Math.min(Math.max(1, parseInt($(this).val()) || 1), max);
                $(this).val(val);
                state.exitModal.lots = val;
            });

            $('#btn-exit-modal-close, #btn-exit-cancel').on('click', function () {
                $('#exit-modal').addClass('hidden');
            });
            $('#exit-modal').on('click', function (e) {
                if (e.target === this) $('#exit-modal').addClass('hidden');
            });

            // Confirm Exit
            $('#btn-exit-confirm').on('click', async function () {
                const idx  = state.exitModal.idx;
                const pos  = state.positions[idx];
                if (!pos || !pos.currentPrice) return;

                const exitLots  = state.exitModal.lots;
                const exitQty   = exitLots * LOTSIZE;
                const exitPrice = pos.currentPrice;
                const outcome   = state.exitModal.outcome;
                const comment   = exitQuill ? exitQuill.root.innerHTML.trim() : '';
                const isPartial = exitLots < pos.lots;

                // P&L calculation
                const multiplier = pos.action === 'BUY' ? 1 : -1;
                const pnl = parseFloat(((exitPrice - pos.avgEntry) * exitQty * multiplier).toFixed(2));

                const orderType = isPartial ? 'partial_exit' : 'full_exit';
                const orderEntry = {
                    type:  orderType,
                    side:  pos.action === 'BUY' ? 'SELL' : 'BUY',
                    price: exitPrice,
                    lots:  exitLots,
                    qty:   exitQty,
                    pnl:   pnl,
                    time:  minutesToTime(state.currentMinutes),
                };

                // Push to Trade History
                state.closedTrades.unshift({
                    strike:    pos.strike,
                    type:      pos.type,
                    action:    pos.action,
                    lots:      exitLots,
                    avgEntry:  pos.avgEntry,
                    exitPrice: exitPrice,
                    pnl:       pnl,
                    exitTime:  minutesToTime(state.currentMinutes),
                    exitType:  orderType,
                    outcome:   outcome,
                    comment:   comment !== '<p><br></p>' ? comment : '',
                });

                // Update or remove position
                if (isPartial) {
                    pos.lots  -= exitLots;
                    pos.pnl    = calcPnl(pos);
                    if (!pos.orders) pos.orders = [];
                    pos.orders.push(orderEntry);
                } else {
                    state.positions.splice(idx, 1);
                }

                // Save to DB
                try {
                    await $.ajax({
                        url:    '{{ route("test.trading-simulator.exit") }}',
                        method: 'POST',
                        data: {
                            _token:       '{{ csrf_token() }}',
                            session_id:   sessionId,
                            trade_date:   state.selectedDate,
                            expiry:       state.selectedExpiry,
                            strike:       pos.strike,
                            instrument_type: pos.type,
                            side:         pos.action,
                            avg_entry:    pos.avgEntry,
                            exit_price:   exitPrice,
                            exit_lots:    exitLots,
                            exit_qty:     exitQty,
                            pnl:          pnl,
                            order_type:   orderType,
                            outcome:      outcome,
                            comment:      comment !== '<p><br></p>' ? comment : '',
                            exit_time:    minutesToTime(state.currentMinutes),
                            // Full position snapshot for DB
                            total_lots:   isPartial ? pos.lots : pos.lots + exitLots,
                            orders:       JSON.stringify(pos.orders || []),
                        }
                    });
                } catch (e) {
                    console.warn('DB save failed (non-blocking):', e);
                }

                $('#exit-modal').addClass('hidden');
                render();
            });




            $('#btn-avg-lots-dec').on('click', function () {
                state.averageModal.lots = Math.max(1, state.averageModal.lots - 1);
                $('#avg-lots-input').val(state.averageModal.lots);
                updateAvgModal();
            });
            $('#btn-avg-lots-inc').on('click', function () {
                state.averageModal.lots++;
                $('#avg-lots-input').val(state.averageModal.lots);
                updateAvgModal();
            });
            $('#avg-lots-input').on('change input', function () {
                state.averageModal.lots = Math.max(1, parseInt($(this).val()) || 1);
                $(this).val(state.averageModal.lots);
                updateAvgModal();
            });

            $('#btn-avg-confirm').on('click', async function () {
                const pos = state.positions[state.averageModal.idx];
                if (!pos || !pos.currentPrice) return;

                const oldQty = pos.lots * LOTSIZE;
                const newQty = state.averageModal.lots * LOTSIZE;

                pos.avgEntry = (pos.avgEntry * oldQty + pos.currentPrice * newQty) / (oldQty + newQty);
                pos.lots    += state.averageModal.lots;
                pos.pnl      = calcPnl(pos);

                if (!pos.orders) pos.orders = [];
                pos.orders.push({
                    type:  'entry',
                    side:  pos.action,
                    price: pos.currentPrice,
                    lots:  state.averageModal.lots,
                    qty:   state.averageModal.lots * LOTSIZE,
                    pnl:   0,
                    time:  minutesToTime(state.currentMinutes),
                });

                // ✅ Save avg modal entry to DB
                await saveEntryOrder({
                    strike:     pos.strike,
                    type:       pos.type,
                    action:     pos.action,
                    avgEntry:   pos.avgEntry,
                    entryPrice: pos.currentPrice,
                    lots:       state.averageModal.lots,
                    qty:        state.averageModal.lots * LOTSIZE,
                    orderType:  'entry',
                });

                $('#average-modal').addClass('hidden');
                render();
            });



            // Orders sub-row toggle
            $(document).on('click', '.btn-orders-toggle', function () {
                const idx = parseInt($(this).data('idx'));
                state.positions[idx].showOrders = !state.positions[idx].showOrders;
                render();
            });

            // ─── Initial Render ──────────────────────────────────────────────────────
            render();
        });

    </script>
    <script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
@endsection
