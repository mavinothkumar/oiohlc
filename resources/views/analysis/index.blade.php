@extends('layouts.app')

@section('content')
    <div class="px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-lg font-semibold text-gray-900">
                Index & Option ATM Analysis
            </h1>
        </div>

        {{-- Filter bar --}}
        <form method="GET" action="{{ route('analysis.index') }}" class="mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">
                        Underlying symbol
                    </label>
                    <input
                        type="text"
                        name="symbol"
                        value="{{ request('symbol') }}"
                        placeholder="e.g. NIFTY"
                        class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                           focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">
                        From date
                    </label>
                    <input
                        type="date"
                        name="from"
                        value="{{ request('from') }}"
                        class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                           focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">
                        To date
                    </label>
                    <input
                        type="date"
                        name="to"
                        value="{{ request('to') }}"
                        class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                           focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">
                        ATM strike
                    </label>
                    <input
                        type="number"
                        name="atm"
                        value="{{ request('atm') }}"
                        placeholder="e.g. 22500"
                        class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                           focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>
            </div>

            <div class="flex items-center justify-end mt-3 gap-2">
                <a href="{{ route('analysis.index') }}"
                    class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md
                      text-xs font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Reset
                </a>
                <button type="submit"
                    class="inline-flex items-center px-4 py-1.5 border border-transparent rounded-md
                       text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700
                       focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Apply filters
                </button>
            </div>
        </form>


        @if(isset($summary))
            <div class="mb-4 rounded-md border border-gray-200 bg-white p-3 text-xs text-gray-700">
                <div class="font-semibold mb-1">Highlight summary (current page):</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    <div>
                        Index Low ± CE Low:
                        <span class="text-green-600 font-semibold">+{{ $summary['idx_low_ce']['+'] }}</span>
                        /
                        <span class="text-red-600 font-semibold">-{{ $summary['idx_low_ce']['-'] }}</span>
                    </div>
                    <div>
                        Index Low ± PE Low:
                        <span class="text-green-600 font-semibold">+{{ $summary['idx_low_pe']['+'] }}</span>
                        /
                        <span class="text-red-600 font-semibold">-{{ $summary['idx_low_pe']['-'] }}</span>
                    </div>
                    <div>
                        Index Low ± Avg Low:
                        <span class="text-green-600 font-semibold">+{{ $summary['idx_low_avg_low']['+'] }}</span>
                        /
                        <span class="text-red-600 font-semibold">-{{ $summary['idx_low_avg_low']['-'] }}</span>
                    </div>
                    <div>
                        Index Low ± Avg High:
                        <span class="text-green-600 font-semibold">+{{ $summary['idx_low_avg_high']['+'] }}</span>
                        /
                        <span class="text-red-600 font-semibold">-{{ $summary['idx_low_avg_high']['-'] }}</span>
                    </div>
                    <div>
                        Index High ± Avg Low/High:
                        <span class="text-green-600 font-semibold">+{{ $summary['idx_high_avg']['+'] }}</span>
                        /
                        <span class="text-red-600 font-semibold">-{{ $summary['idx_high_avg']['-'] }}</span>
                    </div>
                    <div>
                        Mid (H+L)/2 ± Avg H/L:
                        <span class="text-green-600 font-semibold">+{{ $summary['mid_avg']['+'] }}</span>
                        /
                        <span class="text-red-600 font-semibold">-{{ $summary['mid_avg']['-'] }}</span>
                    </div>
                </div>
            </div>
        @endif

        @if(isset($within10))
            <div class="mb-2 rounded-md border border-gray-200 bg-white p-3 text-xs text-gray-700">
                <div class="font-semibold mb-1">
                    Levels within ±20 points of close (current page):
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    <div>
                        Index Low ± CE Low:
                        <span class="text-green-600 font-semibold">
                    +{{ $within10['idx_low_ce']['+'] }}
                </span>
                        /
                        <span class="text-red-600 font-semibold">
                    -{{ $within10['idx_low_ce']['-'] }}
                </span>
                    </div>

                    <div>
                        Index Low ± PE Low:
                        <span class="text-green-600 font-semibold">
                    +{{ $within10['idx_low_pe']['+'] }}
                </span>
                        /
                        <span class="text-red-600 font-semibold">
                    -{{ $within10['idx_low_pe']['-'] }}
                </span>
                    </div>

                    <div>
                        Index Low ± Avg Low:
                        <span class="text-green-600 font-semibold">
                    +{{ $within10['idx_low_avg_low']['+'] }}
                </span>
                        /
                        <span class="text-red-600 font-semibold">
                    -{{ $within10['idx_low_avg_low']['-'] }}
                </span>
                    </div>

                    <div>
                        Index Low ± Avg High:
                        <span class="text-green-600 font-semibold">
                    +{{ $within10['idx_low_avg_high']['+'] }}
                </span>
                        /
                        <span class="text-red-600 font-semibold">
                    -{{ $within10['idx_low_avg_high']['-'] }}
                </span>
                    </div>

                    <div>
                        Index High ± Avg L/H:
                        <span class="text-green-600 font-semibold">
                    +{{ $within10['idx_high_avg']['+'] }}
                </span>
                        /
                        <span class="text-red-600 font-semibold">
                    -{{ $within10['idx_high_avg']['-'] }}
                </span>
                    </div>

                    <div>
                        Mid (H+L)/2 ± Avg H/L:
                        <span class="text-green-600 font-semibold">
                    +{{ $within10['mid_avg']['+'] }}
                </span>
                        /
                        <span class="text-red-600 font-semibold">
                    -{{ $within10['mid_avg']['-'] }}
                </span>
                    </div>

                    <div>
                        Index High − CE Low:
                        <span class="text-red-600 font-semibold">
                    -{{ $within10['idx_high_ce_low']['-'] }}
                </span>
                    </div>
                    <div>
                        Index High − CE High:
                        <span class="text-red-600 font-semibold">
                    -{{ $within10['idx_high_ce_high']['-'] }}
                </span>
                    </div>
                    <div>
                        Index High − PE Low:
                        <span class="text-red-600 font-semibold">
                    -{{ $within10['idx_high_pe_low']['-'] }}
                </span>
                    </div>
                    <div>
                        Index High − PE High:
                        <span class="text-red-600 font-semibold">
                    -{{ $within10['idx_high_pe_high']['-'] }}
                </span>
                    </div>
                </div>
            </div>
        @endif





        {{-- Table wrapper --}}
        <div class="overflow-x-auto bg-white border border-gray-200 rounded-lg shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-xs">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold text-gray-700 whitespace-nowrap">Date</th>
                    <th class="px-3 py-2 text-left font-semibold text-gray-700 whitespace-nowrap">Symbol</th>
                    <th class="px-3 py-2 text-left font-semibold text-gray-700 whitespace-nowrap">ATM</th>

                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">Prev Index C</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">Gap (Prev C → Cur O)</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">Cur Index O/H/L/C</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">
                        Close − Low
                    </th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">
                        Close − High
                    </th>

                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">
                        Prev CE/PE C &Delta; (CE−PE)
                    </th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">
                        Gap − &Delta;(Prev CE/PE C)
                    </th>

                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">Cur CE O/H/L/C</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">Cur PE O/H/L/C</th>

                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">Index Low ± CE Low</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">Index Low ± PE Low</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">Index Low ± Avg Low</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">Index Low ± Avg High</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">Index High ± Avg L/H</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">Mid (H+L)/2 ± Avg H/L</th>
                </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $row)
                    @php
                        // Gap between prev close and current open
                        $gap = $row->cur_index_open - $row->prev_index_close;
                        $gapClass = $gap > 0
                            ? 'text-green-600 font-semibold'
                            : ($gap < 0 ? 'text-red-600 font-semibold' : 'text-gray-700');

                        // Prev CE/PE close and their difference (CE - PE)
                        $prevCe = $row->prev_ce_close;
                        $prevPe = $row->prev_pe_close;
                        $cePeDiff = ($prevCe !== null && $prevPe !== null)
                            ? $prevCe - $prevPe
                            : null;

                        // Gap minus CE/PE diff
                        $gapMinusCePeDiff = $cePeDiff !== null ? $gap - $cePeDiff : null;
                        $gapMinusClass = $gapMinusCePeDiff > 0
                            ? 'text-green-600 font-semibold'
                            : ($gapMinusCePeDiff < 0 ? 'text-red-600 font-semibold' : 'text-gray-700');
                    @endphp

                    <tr class="hover:bg-gray-50">
                        {{-- Date / Symbol / ATM --}}
                        <td class="px-3 py-2 whitespace-nowrap text-gray-900">
                            {{ \Illuminate\Support\Carbon::parse($row->trade_date)->format('d-M-Y') }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">
                            {{ $row->underlying_symbol }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">
                            {{ $row->atm_strike }}
                        </td>

                        {{-- Prev Index C --}}
                        <td class="px-3 py-2 text-center text-gray-700">
                            {{ $row->prev_index_close }}
                        </td>

                        {{-- Gap --}}
                        <td class="px-3 py-2 text-center {{ $gapClass }}">
                            {{ number_format($gap, 2) }}
                        </td>

                        {{-- Cur Index O/H/L/C --}}
                        <td class="px-3 py-2 text-center text-gray-700">
                            {{ $row->cur_index_open }} /
                            {{ $row->cur_index_high }} /
                            {{ $row->cur_index_low }} /
                            {{ $row->cur_index_close }}
                        </td>
                        @php
                            $closeMinusLow = $row->cur_index_close - $row->cur_index_low;
                            $closeMinusHigh = $row->cur_index_close - $row->cur_index_high;
                        @endphp

                        {{-- Close − Low --}}
                        <td class="px-3 py-2 text-center text-gray-700">
                            {{ number_format($closeMinusLow, 2) }}
                        </td>

                        {{-- Close − High --}}
                        <td class="px-3 py-2 text-center text-gray-700">
                            {{ number_format($closeMinusHigh, 2) }}
                        </td>

                        {{-- Prev CE/PE C with difference --}}
                        <td class="px-3 py-2 text-center text-gray-700">
                            @if($prevCe !== null && $prevPe !== null)
                                {{ number_format($prevCe, 2) }} /
                                {{ number_format($prevPe, 2) }}
                                <span class="text-xs text-gray-500">
                    ({{ number_format($cePeDiff, 2) }})
                </span>
                            @else
                                —
                            @endif
                        </td>

                        {{-- Gap − Diff Prev CE/PE C --}}
                        <td class="px-3 py-2 text-center {{ $cePeDiff !== null ? $gapMinusClass : 'text-gray-400' }}">
                            @if($gapMinusCePeDiff !== null)
                                {{ number_format($gapMinusCePeDiff, 2) }}
                            @else
                                —
                            @endif
                        </td>

                        {{-- Cur CE / PE OHLC --}}
                        <td class="px-3 py-2 text-center text-teal-700">
                            {{ $row->cur_ce_open }} /
                            {{ $row->cur_ce_high }} /
                            {{ $row->cur_ce_low }} /
                            {{ $row->cur_ce_close }}
                        </td>
                        <td class="px-3 py-2 text-center text-rose-700">
                            {{ $row->cur_pe_open }} /
                            {{ $row->cur_pe_high }} /
                            {{ $row->cur_pe_low }} /
                            {{ $row->cur_pe_close }}
                        </td>

                        @php
                            $curClose = $row->cur_index_close;

                            // 1) Index Low ± CE Low
                            $cePlus  = $row->range_ce_low_plus;
                            $ceMinus = $row->range_ce_low_minus;

                            // 2) Index Low ± PE Low
                            $pePlus  = $row->cur_pe_low !== null ? $row->cur_index_low + $row->cur_pe_low : null;
                            $peMinus = $row->cur_pe_low !== null ? $row->cur_index_low - $row->cur_pe_low : null;

                            // 3) Index Low ± Avg Low
                            $avgLowPlus  = $row->range_avg_low_plus;
                            $avgLowMinus = $row->range_avg_low_minus;

                            // 4) Index Low ± Avg High
                            $avgHighPlus  = $row->range_avg_high_plus;
                            $avgHighMinus = $row->range_avg_high_minus;

                            // Index High ± Avg Low
                            $idxHighPlusAvgLow  = $row->cur_index_high + $row->avg_low;
                            $idxHighMinusAvgLow = $row->cur_index_high - $row->avg_low;

                            // Index High ± Avg High
                            $idxHighPlusAvgHigh  = $row->cur_index_high + $row->avg_high;
                            $idxHighMinusAvgHigh = $row->cur_index_high - $row->avg_high;

                            // Mid (H+L)/2
                            $idxMid = ($row->cur_index_high + $row->cur_index_low) / 2;
                            $midPlusAvgHigh  = $idxMid + $row->avg_high;
                            $midMinusAvgHigh = $idxMid - $row->avg_high;
                            $midPlusAvgLow   = $idxMid + $row->avg_low;
                            $midMinusAvgLow  = $idxMid - $row->avg_low;

                            // candidates for closest
                            $candidates = [];
                            if ($cePlus !== null)      $candidates['ce_plus']       = $cePlus;
                            if ($ceMinus !== null)     $candidates['ce_minus']      = $ceMinus;
                            if ($pePlus !== null)      $candidates['pe_plus']       = $pePlus;
                            if ($peMinus !== null)     $candidates['pe_minus']      = $peMinus;
                            if ($avgLowPlus !== null)  $candidates['avg_low_plus']  = $avgLowPlus;
                            if ($avgLowMinus !== null) $candidates['avg_low_minus'] = $avgLowMinus;
                            if ($avgHighPlus !== null) $candidates['avg_high_plus'] = $avgHighPlus;
                            if ($avgHighMinus !== null)$candidates['avg_high_minus']= $avgHighMinus;

                            $candidates['idx_high_plus_avg_low']   = $idxHighPlusAvgLow;
                            $candidates['idx_high_minus_avg_low']  = $idxHighMinusAvgLow;
                            $candidates['idx_high_plus_avg_high']  = $idxHighPlusAvgHigh;
                            $candidates['idx_high_minus_avg_high'] = $idxHighMinusAvgHigh;

                            $candidates['mid_plus_avg_high']  = $midPlusAvgHigh;
                            $candidates['mid_minus_avg_high'] = $midMinusAvgHigh;
                            $candidates['mid_plus_avg_low']   = $midPlusAvgLow;
                            $candidates['mid_minus_avg_low']  = $midMinusAvgLow;

                            $closestKey  = null;
                            $closestDiff = null;

                            foreach ($candidates as $key => $value) {
                                $d = abs($value - $curClose);
                                if ($closestDiff === null || $d < $closestDiff) {
                                    $closestDiff = $d;
                                    $closestKey  = $key;
                                }
                            }

                            $highlightClass = 'text-red-600 font-semibold';

                            $diffFn = function ($level) use ($curClose) {
                                return $level !== null ? number_format($level - $curClose, 2) : null;
                            };
                        @endphp

                        {{-- Index Low ± CE Low --}}
                        <td class="px-3 py-2 text-center text-gray-700">
                            {{ $row->cur_index_low }}<br>
                            <span class="text-xs text-gray-500">
                <span class="{{ $closestKey === 'ce_plus' ? $highlightClass : '' }}">
                    +{{ $cePlus }} ({{ $diffFn($cePlus) }})
                </span>
                /
                <span class="{{ $closestKey === 'ce_minus' ? $highlightClass : '' }}">
                    -{{ $ceMinus }} ({{ $diffFn($ceMinus) }})
                </span>
            </span>
                        </td>

                        {{-- Index Low ± PE Low --}}
                        <td class="px-3 py-2 text-center text-gray-700">
                            {{ $row->cur_index_low }}<br>
                            <span class="text-xs text-gray-500">
                @if($pePlus !== null)
                                    <span class="{{ $closestKey === 'pe_plus' ? $highlightClass : '' }}">
                        +{{ $pePlus }} ({{ $diffFn($pePlus) }})
                    </span>
                                    /
                                    <span class="{{ $closestKey === 'pe_minus' ? $highlightClass : '' }}">
                        -{{ $peMinus }} ({{ $diffFn($peMinus) }})
                    </span>
                                @else
                                    —
                                @endif
            </span>
                        </td>

                        {{-- Index Low ± Avg Low --}}
                        <td class="px-3 py-2 text-center text-gray-700">
                            {{ $row->avg_low }}<br>
                            <span class="text-xs text-gray-500">
                <span class="{{ $closestKey === 'avg_low_plus' ? $highlightClass : '' }}">
                    +{{ $avgLowPlus }} ({{ $diffFn($avgLowPlus) }})
                </span>
                /
                <span class="{{ $closestKey === 'avg_low_minus' ? $highlightClass : '' }}">
                    -{{ $avgLowMinus }} ({{ $diffFn($avgLowMinus) }})
                </span>
            </span>
                        </td>

                        {{-- Index Low ± Avg High --}}
                        <td class="px-3 py-2 text-center text-gray-700">
                            {{ $row->avg_high }}<br>
                            <span class="text-xs text-gray-500">
                <span class="{{ $closestKey === 'avg_high_plus' ? $highlightClass : '' }}">
                    +{{ $avgHighPlus }} ({{ $diffFn($avgHighPlus) }})
                </span>
                /
                <span class="{{ $closestKey === 'avg_high_minus' ? $highlightClass : '' }}">
                    -{{ $avgHighMinus }} ({{ $diffFn($avgHighMinus) }})
                </span>
            </span>
                        </td>

                        {{-- Index High ± Avg L/H --}}
                        <td class="px-3 py-2 text-center text-gray-700">
                            {{ $row->cur_index_high }}<br>
                            <span class="text-xs text-gray-500">
                {{-- High ± Avg Low --}}
                <span class="{{ $closestKey === 'idx_high_plus_avg_low' ? $highlightClass : '' }}">
                    +{{ number_format($idxHighPlusAvgLow, 2) }} ({{ $diffFn($idxHighPlusAvgLow) }})
                </span>
                /
                <span class="{{ $closestKey === 'idx_high_minus_avg_low' ? $highlightClass : '' }}">
                    -{{ number_format($idxHighMinusAvgLow, 2) }} ({{ $diffFn($idxHighMinusAvgLow) }})
                </span>
                <br>
                {{-- High ± Avg High --}}
                <span class="{{ $closestKey === 'idx_high_plus_avg_high' ? $highlightClass : '' }}">
                    +{{ number_format($idxHighPlusAvgHigh, 2) }} ({{ $diffFn($idxHighPlusAvgHigh) }})
                </span>
                /
                <span class="{{ $closestKey === 'idx_high_minus_avg_high' ? $highlightClass : '' }}">
                    -{{ number_format($idxHighMinusAvgHigh, 2) }} ({{ $diffFn($idxHighMinusAvgHigh) }})
                </span>
            </span>
                        </td>

                        {{-- Mid (H+L)/2 ± Avg H/L --}}
                        <td class="px-3 py-2 text-center text-gray-700">
                            {{ number_format($idxMid, 2) }}<br>
                            <span class="text-xs text-gray-500">
                {{-- Mid ± Avg High --}}
                <span class="{{ $closestKey === 'mid_plus_avg_high' ? $highlightClass : '' }}">
                    +{{ number_format($midPlusAvgHigh, 2) }} ({{ $diffFn($midPlusAvgHigh) }})
                </span>
                /
                <span class="{{ $closestKey === 'mid_minus_avg_high' ? $highlightClass : '' }}">
                    -{{ number_format($midMinusAvgHigh, 2) }} ({{ $diffFn($midMinusAvgHigh) }})
                </span>
                <br>
                {{-- Mid ± Avg Low --}}
                <span class="{{ $closestKey === 'mid_plus_avg_low' ? $highlightClass : '' }}">
                    +{{ number_format($midPlusAvgLow, 2) }} ({{ $diffFn($midPlusAvgLow) }})
                </span>
                /
                <span class="{{ $closestKey === 'mid_minus_avg_low' ? $highlightClass : '' }}">
                    -{{ number_format($midMinusAvgLow, 2) }} ({{ $diffFn($midMinusAvgLow) }})
                </span>
            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="16" class="px-3 py-6 text-center text-sm text-gray-500">
                            No records found for the selected filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $rows->links() }}
        </div>
    </div>
@endsection
