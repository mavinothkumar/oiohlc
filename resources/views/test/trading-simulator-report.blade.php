{{-- resources/views/test/trading-simulator-report.blade.php --}}
@extends('layouts.app')
@section('title', 'Trade Report')

@section('content')
    <div class="min-h-screen bg-gray-950 py-5">
        <div class="max-w-7xl mx-auto px-4">

            {{-- ── HEADER ── --}}
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-indigo-600 rounded-xl flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0120 9.414V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-base font-bold text-white leading-tight">Trade Report</h1>
                        <p class="text-xs text-gray-500">Nifty Options Simulator — Performance Log</p>
                    </div>
                </div>
                <a href="{{ route('test.trading-simulator') }}"
                    class="text-xs bg-gray-800 hover:bg-gray-700 border border-gray-700 text-gray-300 rounded-xl px-4 py-2 transition-colors flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Simulator
                </a>
            </div>

            {{-- ── STATS CARDS ── --}}
            @if($stats && $stats->total_trades > 0)
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 mb-5">
                    @php
                        $winRate = $stats->total_trades > 0 ? round(($stats->winners / $stats->total_trades) * 100) : 0;
                    @endphp

                    <div class="bg-gray-900 border border-gray-800 rounded-2xl px-4 py-3 col-span-1">
                        <p class="text-xs text-gray-500 mb-1">Total Trades</p>
                        <p class="text-xl font-bold font-mono text-white">{{ $stats->total_trades }}</p>
                    </div>

                    <div class="bg-gray-900 border border-gray-800 rounded-2xl px-4 py-3">
                        <p class="text-xs text-gray-500 mb-1">Win Rate</p>
                        <p class="text-xl font-bold font-mono {{ $winRate >= 50 ? 'text-emerald-400' : 'text-red-400' }}">
                            {{ $winRate }}%
                        </p>
                    </div>

                    <div class="bg-gray-900 border border-gray-800 rounded-2xl px-4 py-3">
                        <p class="text-xs text-gray-500 mb-1">Winners</p>
                        <p class="text-xl font-bold font-mono text-emerald-400">{{ $stats->winners }}</p>
                    </div>

                    <div class="bg-gray-900 border border-gray-800 rounded-2xl px-4 py-3">
                        <p class="text-xs text-gray-500 mb-1">Losers</p>
                        <p class="text-xl font-bold font-mono text-red-400">{{ $stats->losers }}</p>
                    </div>

                    <div class="bg-gray-900 border border-gray-800 rounded-2xl px-4 py-3">
                        <p class="text-xs text-gray-500 mb-1">Total P&amp;L</p>
                        <p class="text-xl font-bold font-mono {{ $stats->total_pnl >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                            {{ $stats->total_pnl >= 0 ? '+' : '' }}{{ number_format($stats->total_pnl, 2) }}
                        </p>
                    </div>

                    <div class="bg-gray-900 border border-gray-800 rounded-2xl px-4 py-3">
                        <p class="text-xs text-gray-500 mb-1">Best Trade</p>
                        <p class="text-xl font-bold font-mono text-emerald-400">+{{ number_format($stats->best_trade, 2) }}</p>
                    </div>

                    <div class="bg-gray-900 border border-gray-800 rounded-2xl px-4 py-3">
                        <p class="text-xs text-gray-500 mb-1">Worst Trade</p>
                        <p class="text-xl font-bold font-mono text-red-400">{{ number_format($stats->worst_trade, 2) }}</p>
                    </div>
                </div>
            @endif

            {{-- ── FILTERS ── --}}
            <div class="bg-gray-900 border border-gray-800 rounded-2xl px-4 py-3 mb-4">
                <form method="GET" action="{{ route('test.trading-simulator.report') }}"
                    class="flex items-end gap-3 flex-wrap">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1.5 font-medium uppercase tracking-wide">Date</label>
                        <input type="date" name="date" value="{{ request('date') }}"
                            class="bg-gray-800 border border-gray-700 hover:border-blue-500 focus:border-blue-500
                        rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:ring-2
                        focus:ring-blue-500/30 transition-colors">
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 mb-1.5 font-medium uppercase tracking-wide">Outcome</label>
                        <select name="outcome"
                            class="bg-gray-800 border border-gray-700 hover:border-blue-500 focus:border-blue-500
                         rounded-xl px-3 py-2 text-sm text-white focus:outline-none transition-colors">
                            <option value="">All</option>
                            <option value="profit"    {{ request('outcome') === 'profit'    ? 'selected' : '' }}>Profit</option>
                            <option value="stoploss"  {{ request('outcome') === 'stoploss'  ? 'selected' : '' }}>Stoploss</option>
                            <option value="breakeven" {{ request('outcome') === 'breakeven' ? 'selected' : '' }}>Breakeven</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 mb-1.5 font-medium uppercase tracking-wide">Status</label>
                        <select name="status"
                            class="bg-gray-800 border border-gray-700 hover:border-blue-500 focus:border-blue-500
                         rounded-xl px-3 py-2 text-sm text-white focus:outline-none transition-colors">
                            <option value="">All</option>
                            <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                            <option value="open"   {{ request('status') === 'open'   ? 'selected' : '' }}>Open</option>
                        </select>
                    </div>
                    {{-- Strategy filter — NEW --}}
                    <div>
                        <label class="block text-xs text-gray-500 mb-1 uppercase tracking-wide">Strategy</label>
                        <select name="strategy" class="bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-xl px-3 py-2 focus:outline-none focus:border-purple-600">
                            <option value="">All Strategies</option>
                            @foreach($strategies as $strat)
                                <option value="{{ $strat }}" {{ request('strategy') == $strat ? 'selected' : '' }}>
                                    {{ $strat }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit"
                            class="bg-blue-600 hover:bg-blue-500 text-white rounded-xl px-4 py-2 text-sm font-semibold transition-colors">
                            Filter
                        </button>
                        @if(request()->hasAny(['date','outcome','status']))
                            <a href="{{ route('test.trading-simulator.report') }}"
                                class="bg-gray-800 hover:bg-gray-700 border border-gray-700 text-gray-400 rounded-xl px-4 py-2 text-sm transition-colors">
                                Clear
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- ── POSITIONS TABLE ── --}}
            <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden mb-5">
                <div class="px-4 py-3 border-b border-gray-800 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                        Trade History
                        <span class="bg-gray-700 text-gray-300 text-xs px-2 py-0.5 rounded-full font-mono">
            {{ $positions->total() }}
          </span>
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                        <tr class="text-xs text-gray-500 border-b border-gray-800 bg-gray-900/60">
                            <th class="px-4 py-2.5 text-left font-medium">Date</th>
                            <th class="px-4 py-2.5 text-left font-medium">Strike / Type</th>
                            <th class="px-4 py-2.5 text-left font-medium">Strategy</th>
                            <th class="px-4 py-2.5 text-left font-medium">Side</th>
                            <th class="px-4 py-2.5 text-right font-medium">Avg Entry</th>
                            <th class="px-4 py-2.5 text-right font-medium">Lots / Qty</th>
                            <th class="px-4 py-2.5 text-right font-medium">P&amp;L</th>
                            <th class="px-4 py-2.5 text-left font-medium">Outcome</th>
                            <th class="px-4 py-2.5 text-left font-medium">Status</th>
                            <th class="px-4 py-2.5 text-center font-medium">Orders</th>
                            <th class="px-4 py-2.5 text-center font-medium">Detail</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($positions as $position)
                            @php
                                $pnl         = $position->realized_pnl;
                                $pnlClass    = $pnl >= 0 ? 'text-emerald-400' : 'text-red-400';
                                $typeClass   = $position->instrument_type === 'CE'
                                               ? 'bg-blue-900/60 text-blue-300 border border-blue-800'
                                               : 'bg-orange-900/60 text-orange-300 border border-orange-800';
                                $sideClass   = $position->side === 'BUY'
                                               ? 'bg-emerald-900/60 text-emerald-300 border border-emerald-800'
                                               : 'bg-red-900/60 text-red-300 border border-red-800';
                                $statusClass = $position->status === 'closed'
                                               ? 'bg-gray-800 text-gray-400 border-gray-700'
                                               : 'bg-emerald-900/40 text-emerald-400 border-emerald-800/60 animate-pulse';
                                $note        = $position->notes->first();
                                $outcomeBadge = match($note?->outcome) {
                                    'profit'    => 'bg-emerald-900/60 text-emerald-400 border-emerald-800/60',
                                    'stoploss'  => 'bg-red-900/60 text-red-400 border-red-800/60',
                                    'breakeven' => 'bg-yellow-900/60 text-yellow-400 border-yellow-800/60',
                                    default     => 'bg-gray-800 text-gray-600 border-gray-700',
                                };
                                $outcomeLabel = match($note?->outcome) {
                                    'profit'    => '✓ Profit',
                                    'stoploss'  => '✗ Stoploss',
                                    'breakeven' => '~ Breakeven',
                                    default     => '—',
                                };
                            @endphp
                            <tr class="border-b border-gray-800/60 hover:bg-gray-800/30 transition-colors">
                                <td class="px-4 py-3 font-mono text-xs text-gray-400">
                                    {{ \Carbon\Carbon::parse($position->trade_date)->format('d M Y') }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-mono font-semibold text-white">{{ $position->strike }}</span>
                                    <span class="text-xs font-bold px-2 py-0.5 rounded-md ml-1 {{ $typeClass }}">
                    {{ $position->instrument_type }}
                  </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($position->strategy)
                                        <span class="text-xs font-medium px-2 py-0.5 rounded-md bg-purple-900/40 text-purple-300 border border-purple-800/60">
            {{ $position->strategy }}
        </span>
                                    @else
                                        <span class="text-gray-700">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                  <span class="text-xs font-bold px-2 py-0.5 rounded-md {{ $sideClass }}">
                    {{ $position->side }}
                  </span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-gray-300">
                                    {{ number_format($position->avg_entry, 2) }}
                                </td>
                                <td class="px-4 py-3 text-right text-gray-300">
                                    <span class="font-mono">{{ intdiv($position->total_qty, 75) }}</span>
                                    <span class="text-gray-600 text-xs ml-1">({{ $position->total_qty }} qty)</span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold {{ $pnlClass }}">
                                    {{ $pnl >= 0 ? '+' : '' }}{{ number_format($pnl, 2) }}
                                </td>
                                <td class="px-4 py-3">
                  <span class="text-xs font-bold px-2 py-0.5 rounded-md border {{ $outcomeBadge }}">
                    {{ $outcomeLabel }}
                  </span>
                                </td>
                                <td class="px-4 py-3">
                  <span class="text-xs font-bold px-2 py-0.5 rounded-md border {{ $statusClass }}">
                    {{ ucfirst($position->status) }}
                  </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                  <span class="bg-gray-800 text-gray-400 border border-gray-700 text-xs font-mono px-2 py-0.5 rounded-full">
                    {{ $position->orders->count() }}
                  </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('test.trading-simulator.report.detail', $position->id) }}"
                                        class="text-xs bg-blue-900/40 hover:bg-blue-900/70 text-blue-400 border border-blue-800/60
                            hover:border-blue-700 rounded-lg px-3 py-1 transition-all font-medium">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-16 text-center text-gray-600">
                                    <svg class="w-10 h-10 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                                            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0120 9.414V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <p class="text-sm font-medium text-gray-500">No trades found</p>
                                    <p class="text-xs mt-1 text-gray-600">Try adjusting the filters or run a simulation first</p>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($positions->hasPages())
                    <div class="px-4 py-3 border-t border-gray-800">
                        {{ $positions->links('pagination::tailwind') }}
                    </div>
                @endif
            </div>

        </div>
    </div>
@endsection
