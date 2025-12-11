@extends('layouts.app')

@section('content')
    <div class="space-y-4">
        {{-- Title / filters row if needed --}}
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">
                NIFTY Backtest Results
            </h2>

            {{-- Optional summary --}}
            <p class="text-xs text-gray-500">
                Showing {{ $backtests->count() }} records
            </p>
        </div>


        <form method="GET" class="mb-4 flex flex-wrap gap-3 items-end text-xs">
            <div>
                <label class="block text-gray-600 mb-1">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
                    class="border rounded px-2 py-1 text-xs">
            </div>

            <div>
                <label class="block text-gray-600 mb-1">To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
                    class="border rounded px-2 py-1 text-xs">
            </div>

            <div>
                <label class="block text-gray-600 mb-1">6‑Level Side</label>
                <select name="six_level_side" class="border rounded px-2 py-1 text-xs">
                    <option value="all">All</option>
                    <option value="CE" {{ ($filters['six_level_side'] ?? '') === 'CE' ? 'selected' : '' }}>CE</option>
                    <option value="PE" {{ ($filters['six_level_side'] ?? '') === 'PE' ? 'selected' : '' }}>PE</option>
                </select>
            </div>

            <div>
                <label class="block text-gray-600 mb-1">Retested Low?</label>
                <select name="retested_low" class="border rounded px-2 py-1 text-xs">
                    <option value="all">All</option>
                    <option value="yes" {{ ($filters['retested_low'] ?? '') === 'yes' ? 'selected' : '' }}>Yes</option>
                    <option value="no"  {{ ($filters['retested_low'] ?? '') === 'no'  ? 'selected' : '' }}>No</option>
                </select>
            </div>

            <div>
                <label class="block text-gray-600 mb-1">Opponent High/Close?</label>
                <select name="opponent_reached" class="border rounded px-2 py-1 text-xs">
                    <option value="all">All</option>
                    <option value="yes" {{ ($filters['opponent_reached'] ?? '') === 'yes' ? 'selected' : '' }}>Yes</option>
                    <option value="no"  {{ ($filters['opponent_reached'] ?? '') === 'no'  ? 'selected' : '' }}>No</option>
                </select>
            </div>

            <div>
                <label class="block text-gray-600 mb-1">Broke at 9:15?</label>
                <select name="broke_at_open" class="border rounded px-2 py-1 text-xs">
                    <option value="all">All</option>
                    <option value="yes" {{ ($filters['broke_at_open'] ?? '') === 'yes' ? 'selected' : '' }}>Yes</option>
                    <option value="no"  {{ ($filters['broke_at_open'] ?? '') === 'no'  ? 'selected' : '' }}>No</option>
                </select>
            </div>

            <div>
                <button type="submit"
                    class="bg-indigo-600 text-white px-3 py-1 rounded text-xs hover:bg-indigo-700">
                    Apply
                </button>
            </div>
        </form>

        {{-- Quick stats for the current filter --}}
        <div class="mb-4 flex flex-wrap gap-4 text-xs text-gray-700">
            <div>Total days: <span class="font-semibold">{{ $totalDays }}</span></div>
            <div>Retested low: <span class="font-semibold">{{ $daysRetestedLow }}</span></div>
            <div>Opponent high/close reached: <span class="font-semibold">{{ $daysOpponentReached }}</span></div>
            <div>Broke at 9:15: <span class="font-semibold">{{ $daysBrokeAtOpen }}</span></div>
        </div>

        {{-- Table wrapper --}}
        <div class="relative">
            <div class="overflow-x-auto border border-gray-200 rounded-lg shadow-sm bg-white">
                    <table class="min-w-full border-collapse table-auto text-xs text-left text-gray-700">
                        <thead class="bg-gray-50">
                        <tr>
                            {{-- CORE --}}
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">Date</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">Expiry</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">Strike</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">CE Prev Low</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">PE Prev Low</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">Lowest Prev Low</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">Lowest Side</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">Six Level Side</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">CE Break Time</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">PE Break Time</th>

                            {{-- CE break OHLCV/OI --}}
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">CE Br Open</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">CE Br High</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">CE Br Low</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">CE Br Close</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">CE Br Vol</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">CE Br OI</th>

                            {{-- PE break OHLCV/OI --}}
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">PE Br Open</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">PE Br High</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">PE Br Low</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">PE Br Close</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">PE Br Vol</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">PE Br OI</th>

                            {{-- CE opponent previous day --}}
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">CE Opp Prev High?</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">CE Opp Prev High Time</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">CE Opp Prev High Price</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">CE Opp Prev Close?</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">CE Opp Prev Close Time</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">CE Opp Prev Close Price</th>

                            {{-- PE opponent previous day --}}
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">PE Opp Prev High?</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">PE Opp Prev High Time</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">PE Opp Prev High Price</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">PE Opp Prev Close?</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">PE Opp Prev Close Time</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">PE Opp Prev Close Price</th>

                            {{-- CE retest --}}
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">CE Low Retested?</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">CE Low Retest Time</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">CE Low Retest Price</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">CE Dist From Low</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">CE Max High From Low</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">CE Max High Time</th>

                            {{-- PE retest --}}
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">PE Low Retested?</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">PE Low Retest Time</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">PE Low Retest Price</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">PE Dist From Low</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">PE Max High From Low</th>
                            <th class="border px-2 py-1 font-semibold text-[14px] whitespace-nowrap">PE Max High Time</th>
                        </tr>
                        </thead>

                        <tbody class="bg-white">
                        @foreach ($backtests as $row)
                            <tr class="hover:bg-gray-50">
                                {{-- CORE --}}
                                <td class="border px-2 py-1 whitespace-nowrap text-[14px]">
                                    {{ $row->trade_date ?? $row->date ?? '' }}
                                </td>
                                <td class="border px-2 py-1 whitespace-nowrap text-[14px]">
                                    {{ $row->expiry ?? '' }}
                                </td>
                                <td class="border px-2 py-1 whitespace-nowrap text-[14px]">
                                    {{ $row->strike ?? $row->strike_price ?? '' }}
                                </td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->ce_prev_low ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->pe_prev_low ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->lowest_prev_low ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->lowest_prev_low_side ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->six_level_broken_side ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->ce_break_time ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->pe_break_time ?? '-' }}</td>

                                {{-- CE break OHLCV/OI --}}
                                <td class="border px-2 py-1 text-[14px]">{{ $row->ce_break_open ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->ce_break_high ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->ce_break_low ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->ce_break_close ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->ce_break_volume ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->ce_break_oi ?? '-' }}</td>

                                {{-- PE break OHLCV/OI --}}
                                <td class="border px-2 py-1 text-[14px]">{{ $row->pe_break_open ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->pe_break_high ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->pe_break_low ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->pe_break_close ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->pe_break_volume ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->pe_break_oi ?? '-' }}</td>

                                {{-- CE opponent prev day --}}
                                <td class="border px-2 py-1 text-[14px]">
                                    {{ !empty($row->ce_opponent_prev_high_broken) ? 'Yes' : 'No' }}
                                </td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->ce_opponent_prev_high_break_time ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->ce_opponent_prev_high_break_price ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">
                                    {{ !empty($row->ce_opponent_prev_close_crossed) ? 'Yes' : 'No' }}
                                </td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->ce_opponent_prev_close_cross_time ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->ce_opponent_prev_close_cross_price ?? '-' }}</td>

                                {{-- PE opponent prev day --}}
                                <td class="border px-2 py-1 text-[14px]">
                                    {{ !empty($row->pe_opponent_prev_high_broken) ? 'Yes' : 'No' }}
                                </td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->pe_opponent_prev_high_break_time ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->pe_opponent_prev_high_break_price ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">
                                    {{ !empty($row->pe_opponent_prev_close_crossed) ? 'Yes' : 'No' }}
                                </td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->pe_opponent_prev_close_cross_time ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->pe_opponent_prev_close_cross_price ?? '-' }}</td>

                                {{-- CE retest --}}
                                <td class="border px-2 py-1 text-[14px]">
                                    {{ !empty($row->ce_low_retested) ? 'Yes' : 'No' }}
                                </td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->ce_low_retest_time ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->ce_low_retest_price ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->ce_retest_distance_from_low ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->ce_max_high_from_low ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->ce_max_high_from_low_time ?? '-' }}</td>

                                {{-- PE retest --}}
                                <td class="border px-2 py-1 text-[14px]">
                                    {{ !empty($row->pe_low_retested) ? 'Yes' : 'No' }}
                                </td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->pe_low_retest_time ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->pe_low_retest_price ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->pe_retest_distance_from_low ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->pe_max_high_from_low ?? '-' }}</td>
                                <td class="border px-2 py-1 text-[14px]">{{ $row->pe_max_high_from_low_time ?? '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>

            </div>

            <div class="mt-4">
                {{ $backtests->links() }}
            </div>
        </div>
    </div>

@endsection
