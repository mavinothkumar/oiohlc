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

        {{-- Table wrapper --}}
        <div class="overflow-x-auto bg-white border border-gray-200 rounded-lg shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-xs">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold text-gray-700 whitespace-nowrap">Date</th>
                    <th class="px-3 py-2 text-left font-semibold text-gray-700 whitespace-nowrap">Symbol</th>
                    <th class="px-3 py-2 text-left font-semibold text-gray-700 whitespace-nowrap">ATM</th>

                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">Prev Index C</th>


                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">Prev CE C</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">Prev PE C</th>

                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">Cur CE O/H/L/C</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">Cur PE O/H/L/C</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">Cur Index O/H/L/C</th>

                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">Index Low ± CE Low</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">Index Low ± PE Low</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">Index Low ± Avg Low</th>
                    <th class="px-3 py-2 text-center font-semibold text-gray-700 whitespace-nowrap">Index Low ± Avg High</th>
                </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 whitespace-nowrap text-gray-900">
                            {{ \Illuminate\Support\Carbon::parse($row->trade_date)->format('d-M-Y') }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">
                            {{ $row->underlying_symbol }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">
                            {{ $row->atm_strike }}
                        </td>

                        <td class="px-3 py-2 text-center text-gray-700">
                            {{ $row->prev_index_close }}
                        </td>



                        <td class="px-3 py-2 text-center text-teal-700">
                            {{ $row->prev_ce_close }}
                        </td>

                        <td class="px-3 py-2 text-center text-rose-700">
                            {{ $row->prev_pe_close }}
                        </td>

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

                        <td class="px-3 py-2 text-center text-gray-700">
                            {{ $row->cur_index_open }} /
                            {{ $row->cur_index_high }} /
                            {{ $row->cur_index_low }} /
                            <span class="text-red-600 font-semibold">{{ $row->cur_index_close }}</span>
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

                            // Collect all candidates with labels so we can find the closest
                            $candidates = [];

                            if ($cePlus !== null)     $candidates['ce_plus']      = $cePlus;
                            if ($ceMinus !== null)    $candidates['ce_minus']     = $ceMinus;
                            if ($pePlus !== null)     $candidates['pe_plus']      = $pePlus;
                            if ($peMinus !== null)    $candidates['pe_minus']     = $peMinus;
                            if ($avgLowPlus !== null) $candidates['avg_low_plus'] = $avgLowPlus;
                            if ($avgLowMinus !== null)$candidates['avg_low_minus']= $avgLowMinus;
                            if ($avgHighPlus !== null)$candidates['avg_high_plus']= $avgHighPlus;
                            if ($avgHighMinus !== null)$candidates['avg_high_minus'] = $avgHighMinus;

                            $closestKey = null;
                            $closestDiff = null;

                            foreach ($candidates as $key => $value) {
                                $diff = abs($value - $curClose);
                                if ($closestDiff === null || $diff < $closestDiff) {
                                    $closestDiff = $diff;
                                    $closestKey  = $key;
                                }
                            }

                            $highlightClass = 'text-red-600 font-semibold';
                        @endphp

                        {{-- 1) Index Low ± CE Low --}}
                        <td class="px-3 py-2 text-center text-gray-700">
                            {{ $row->cur_index_low }}<br>
                            <span class="text-xs text-gray-500">
        <span class="{{ $closestKey === 'ce_plus' ? $highlightClass : '' }}">
            +{{ $cePlus }}
        </span>
        /
        <span class="{{ $closestKey === 'ce_minus' ? $highlightClass : '' }}">
            -{{ $ceMinus }}
        </span>
    </span>
                        </td>

                        {{-- 2) Index Low ± PE Low --}}
                        <td class="px-3 py-2 text-center text-gray-700">
                            {{ $row->cur_index_low }}<br>
                            <span class="text-xs text-gray-500">
        @if($pePlus !== null)
                                    <span class="{{ $closestKey === 'pe_plus' ? $highlightClass : '' }}">
                +{{ $pePlus }}
            </span>
                                    /
                                    <span class="{{ $closestKey === 'pe_minus' ? $highlightClass : '' }}">
                -{{ $peMinus }}
            </span>
                                @else
                                    —
                                @endif
    </span>
                        </td>

                        {{-- 3) Index Low ± Avg Low --}}
                        <td class="px-3 py-2 text-center text-gray-700">
                            {{ $row->avg_low }}<br>
                            <span class="text-xs text-gray-500">
        <span class="{{ $closestKey === 'avg_low_plus' ? $highlightClass : '' }}">
            +{{ $avgLowPlus }}
        </span>
        /
        <span class="{{ $closestKey === 'avg_low_minus' ? $highlightClass : '' }}">
            -{{ $avgLowMinus }}
        </span>
    </span>
                        </td>

                        {{-- 4) Index Low ± Avg High --}}
                        <td class="px-3 py-2 text-center text-gray-700">
                            {{ $row->avg_high }}<br>
                            <span class="text-xs text-gray-500">
        <span class="{{ $closestKey === 'avg_high_plus' ? $highlightClass : '' }}">
            +{{ $avgHighPlus }}
        </span>
        /
        <span class="{{ $closestKey === 'avg_high_minus' ? $highlightClass : '' }}">
            -{{ $avgHighMinus }}
        </span>
    </span>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="px-3 py-6 text-center text-sm text-gray-500">
                            No records found for the selected filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-4">
            {{ $rows->links() }}
        </div>
    </div>
@endsection
