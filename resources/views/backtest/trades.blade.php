{{-- resources/views/backtest/trades.blade.php --}}
@extends('layouts.app')

@section('title')
    Straddle and Strangle
@endsection

@section('content')
    {{-- resources/views/backtest/trades.blade.php --}}
    <div class="min-h-screen bg-gray-950 text-gray-100 p-6">

        {{-- Back --}}
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('backtest.index') }}"
                    class="text-gray-400 hover:text-white text-sm transition-colors">
                ← All Days
            </a>
            <span class="text-gray-700">/</span>
            <h1 class="text-xl font-bold text-white">
                {{ \Carbon\Carbon::parse($day->trade_date)->format('d M Y') }}
                <span class="text-gray-500 font-normal text-base ml-1">
                ({{ \Carbon\Carbon::parse($day->trade_date)->format('l') }})
            </span>
            </h1>
        </div>

        {{-- Day Summary Card --}}
        @php
            $isProfit = $day->day_outcome === 'profit';
            $atm      = $day->index_price_at_entry
                ? (int)(round($day->index_price_at_entry / 100) * 100)
                : null;
            $upper    = $atm ? $atm + $day->strike_offset : null;
            $lower    = $atm ? $atm - $day->strike_offset : null;
            $entryFmt = $day->entry_time
                ? \Carbon\Carbon::parse($day->entry_time)->format('H:i')
                : '—';
            $exitFmt  = $day->exit_time
                ? \Carbon\Carbon::parse($day->exit_time)->format('H:i')
                : '—';
            $dur      = $day->trade_time_duration;
            $durFmt   = $dur
                ? (intdiv($dur,60) > 0 ? intdiv($dur,60).'h ' : '').($dur%60).'m'
                : '—';
        @endphp

        <div class="bg-gray-900 rounded-xl border
                {{ $isProfit ? 'border-emerald-700/50' : 'border-red-700/50' }}
                p-5 mb-6">
            <div class="flex flex-wrap gap-6 items-center">

                {{-- Outcome badge --}}
                <div>
                    @if($isProfit)
                        <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm
                                 font-bold bg-emerald-900 text-emerald-300">
                        ✓ Profit Day
                    </span>
                    @else
                        <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm
                                 font-bold bg-red-900 text-red-300">
                        ✗ Loss Day
                    </span>
                    @endif
                </div>

                {{-- Index --}}
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Index @ {{ $entryFmt }}</p>
                    <p class="text-lg font-bold text-cyan-300 font-mono">
                        {{ $day->index_price_at_entry
                            ? number_format($day->index_price_at_entry, 2)
                            : '—' }}
                    </p>
                </div>

                {{-- Strikes --}}
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Strikes Sold</p>
                    <div class="flex items-center gap-2 mt-1">
                        @if($lower)
                            <span class="px-2 py-0.5 bg-indigo-900/60 text-indigo-300
                                     rounded font-mono text-sm font-semibold">
                            {{ number_format($lower) }}
                        </span>
                            <span class="text-gray-600">&</span>
                            <span class="px-2 py-0.5 bg-indigo-900/60 text-indigo-300
                                     rounded font-mono text-sm font-semibold">
                            {{ number_format($upper) }}
                        </span>
                            <span class="text-xs text-gray-500">(CE + PE each)</span>
                        @endif
                    </div>
                </div>

                {{-- Timing --}}
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Entry → Exit</p>
                    <p class="font-mono text-white text-sm mt-0.5">
                        {{ $entryFmt }}
                        <span class="text-gray-500 mx-1">→</span>
                        {{ $exitFmt }}
                        <span class="text-yellow-400 ml-2 text-xs">({{ $durFmt }})</span>
                    </p>
                </div>

                {{-- Max Profit Reached --}}
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Max Profit Reached</p>
                    @if(($day->day_max_profit ?? 0) > 0)
                        <p class="text-lg font-bold font-mono text-emerald-300 mt-0.5">
                            +₹{{ number_format($day->day_max_profit, 0) }}
                        </p>
                        <p class="text-xs text-gray-500 font-mono mt-0.5">
                            @ {{ $day->day_max_profit_time
                ? \Carbon\Carbon::parse($day->day_max_profit_time)->format('H:i')
                : '—' }}
                            @if($day->day_outcome === 'loss')
                                <span class="text-yellow-500 ml-1">then reversed ↓</span>
                            @endif
                        </p>
                    @else
                        <p class="text-lg font-bold text-gray-600 mt-0.5">₹0</p>
                    @endif
                </div>

                {{-- Max Loss Reached --}}
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Max Loss Reached</p>
                    @if(($day->day_max_loss ?? 0) < 0)
                        <p class="text-lg font-bold font-mono text-red-300 mt-0.5">
                            ₹{{ number_format($day->day_max_loss, 0) }}
                        </p>
                        <p class="text-xs text-gray-500 font-mono mt-0.5">
                            @ {{ $day->day_max_loss_time
                ? \Carbon\Carbon::parse($day->day_max_loss_time)->format('H:i')
                : '—' }}
                            @if($day->day_outcome === 'profit')
                                <span class="text-yellow-500 ml-1">then recovered ↑</span>
                            @endif
                        </p>
                    @else
                        <p class="text-lg font-bold text-gray-600 mt-0.5">₹0</p>
                    @endif
                </div>

                {{-- Expiry --}}
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Expiry</p>
                    <p class="font-mono text-gray-300 text-sm mt-0.5">
                        {{ $day->expiry
                            ? \Carbon\Carbon::parse($day->expiry)->format('d M Y')
                            : '—' }}
                    </p>
                </div>

                {{-- Parameters --}}
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Parameters</p>
                    <div class="flex gap-1 mt-1">
                    <span class="px-2 py-0.5 bg-gray-800 rounded text-xs font-mono text-indigo-300">
                        ±{{ $day->strike_offset }}
                    </span>
                        <span class="px-2 py-0.5 bg-emerald-900/40 rounded text-xs font-mono text-emerald-400">
                        T:{{ number_format($day->target, 0) }}
                    </span>
                        <span class="px-2 py-0.5 bg-red-900/40 rounded text-xs font-mono text-red-400">
                        SL:{{ number_format($day->stoploss, 0) }}
                    </span>
                        <span class="px-2 py-0.5 bg-gray-800 rounded text-xs font-mono text-gray-400">
                        {{ $day->lot_size }} qty
                    </span>
                    </div>
                </div>

                {{-- Day P&L --}}
                <div class="ml-auto text-right">
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Combined Day P&L</p>
                    <p class="text-3xl font-bold font-mono
                    {{ $isProfit ? 'text-emerald-400' : 'text-red-400' }}">
                        {{ $day->day_total_pnl >= 0 ? '+' : '' }}₹{{ number_format($day->day_total_pnl, 0) }}
                    </p>
                    <p class="text-xs text-gray-500 mt-0.5">across 4 legs</p>
                </div>


                @if($day->signal_time)
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider">Signal Confirmed</p>
                        <p class="font-mono text-yellow-300 text-sm mt-0.5">
                            {{ \Carbon\Carbon::parse($day->signal_time)->format('H:i') }}
                            <span class="text-gray-500 text-xs ml-1">breakout</span>
                        </p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Entry: {{ \Carbon\Carbon::parse($day->entry_time)->format('H:i') }}
                            @if($day->signal_time !== $day->entry_time)
                                <span class="text-yellow-600 ml-1">
                (+{{ \Carbon\Carbon::parse($day->signal_time)->diffInMinutes(\Carbon\Carbon::parse($day->entry_time)) }} min late)
            </span>
                            @endif
                        </p>
                    </div>
                @endif
            </div>
        </div>

        {{-- 4 Legs Table --}}
        <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-800 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">
                    4 Legs Detail
                </h2>
                <span class="text-xs text-gray-500">{{ $day->underlying_symbol }} · {{ $day->exchange }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                    <tr class="bg-gray-800/60 text-gray-400 uppercase text-xs tracking-wider">
                        <th class="px-4 py-3 text-left">Strike</th>
                        <th class="px-4 py-3 text-center">Type</th>
                        <th class="px-4 py-3 text-center">Side</th>
                        <th class="px-4 py-3 text-right">Avg Entry</th>
                        <th class="px-4 py-3 text-right">Lots</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">Exit Price</th>
                        <th class="px-4 py-3 text-right">Diff (pts)</th>
                        <th class="px-4 py-3 text-right">Chg %</th>
                        <th class="px-4 py-3 text-right">Leg P&L</th>
                        <th class="px-4 py-3 text-left">Entry Time</th>
                        <th class="px-4 py-3 text-left">Exit Time</th>
                        <th class="px-4 py-3 text-right">Duration</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                    @foreach($legs as $leg)
                        @php
                            $diff     = $leg->exit_price
                                ? round($leg->entry_price - $leg->exit_price, 2)
                                : 0;
                            $chgPct   = $leg->entry_price > 0
                                ? round($diff / $leg->entry_price * 100, 2)
                                : 0;
                            $legDur   = $leg->trade_time_duration;
                            $legDurFmt = $legDur
                                ? (intdiv($legDur,60) > 0 ? intdiv($legDur,60).'h ' : '').($legDur%60).'m'
                                : '—';
                        @endphp
                        <tr class="hover:bg-gray-800/30 transition-colors">

                            <td class="px-4 py-3 font-mono font-bold text-white">
                                {{ number_format($leg->strike) }}
                            </td>

                            <td class="px-4 py-3 text-center">
                            <span class="px-2.5 py-1 rounded font-bold text-xs
                                {{ $leg->instrument_type === 'CE'
                                    ? 'bg-blue-900/70 text-blue-300'
                                    : 'bg-orange-900/70 text-orange-300' }}">
                                {{ $leg->instrument_type }}
                            </span>
                            </td>

                            <td class="px-4 py-3 text-center">
                            <span class="px-2.5 py-1 rounded font-bold text-xs
                                         bg-red-900/60 text-red-300">
                                SELL
                            </span>
                            </td>

                            <td class="px-4 py-3 text-right font-mono text-white">
                                {{ number_format($leg->entry_price, 2) }}
                            </td>

                            <td class="px-4 py-3 text-right text-gray-400">1</td>

                            <td class="px-4 py-3 text-right text-gray-400">{{ $leg->qty }}</td>

                            <td class="px-4 py-3 text-right font-mono text-gray-300">
                                {{ $leg->exit_price ? number_format($leg->exit_price, 2) : '—' }}
                            </td>

                            <td class="px-4 py-3 text-right font-mono
                            {{ $diff >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                {{ $diff >= 0 ? '+' : '' }}{{ number_format($diff, 2) }}
                            </td>

                            <td class="px-4 py-3 text-right font-mono
                            {{ $chgPct >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                {{ $chgPct >= 0 ? '+' : '' }}{{ $chgPct }}%
                            </td>

                            <td class="px-4 py-3 text-right font-mono font-bold
                            {{ $leg->pnl >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                {{ $leg->pnl >= 0 ? '+' : '' }}₹{{ number_format($leg->pnl, 0) }}
                            </td>

                            <td class="px-4 py-3 font-mono text-gray-400 text-xs">
                                {{ $leg->entry_time
                                    ? \Carbon\Carbon::parse($leg->entry_time)->format('H:i')
                                    : '—' }}
                            </td>

                            <td class="px-4 py-3 font-mono text-gray-400 text-xs">
                                {{ $leg->exit_time
                                    ? \Carbon\Carbon::parse($leg->exit_time)->format('H:i')
                                    : '—' }}
                            </td>

                            <td class="px-4 py-3 text-right text-gray-500 text-xs">
                                {{ $legDurFmt }}
                            </td>

                        </tr>
                    @endforeach
                    </tbody>

                    {{-- Combined Total --}}
                    <tfoot>
                    <tr class="border-t-2 border-gray-700 bg-gray-800/50 font-bold">
                        <td colspan="9" class="px-4 py-3 text-gray-400 text-xs uppercase tracking-wider">
                            Combined Total ({{ $legs->count() }} legs)
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-lg
                            {{ $day->day_total_pnl >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                            {{ $day->day_total_pnl >= 0 ? '+' : '' }}₹{{ number_format($day->day_total_pnl, 0) }}
                        </td>
                        <td colspan="3" class="px-4 py-3 text-right text-gray-500 text-xs">
                            {{ $durFmt }} total hold
                        </td>
                    </tr>
                    </tfoot>

                </table>
            </div>
        </div>

    </div>
@endsection
