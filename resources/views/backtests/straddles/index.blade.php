@extends('layouts.app')

@section('title')
    Straddle Backtest
@endsection

@section('content')
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold text-gray-900 mb-4">
            NIFTY Straddle Hourly Backtest
        </h1>

        {{-- Filters --}}
        <form method="GET" class="mb-4 bg-white p-4 rounded-lg shadow flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Symbol</label>
                <select name="symbol"
                    class="border border-gray-300 rounded-md px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach($symbols as $symbol)
                        <option value="{{ $symbol }}"
                            @selected(($filters['symbol'] ?? '') === $symbol)>
                            {{ $symbol }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Expiry from</label>
                <input type="date" name="expiry_from"
                    value="{{ $filters['expiry_from'] ?? '' }}"
                    class="border border-gray-300 rounded-md px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Expiry to</label>
                <input type="date" name="expiry_to"
                    value="{{ $filters['expiry_to'] ?? '' }}"
                    class="border border-gray-300 rounded-md px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Hour slot</label>
                <select name="hour_slot"
                    class="border border-gray-300 rounded-md px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach(['09_30',
                                '10_00',
                                '10_30',
                                '11_00',
                                '11_30',
                                '12_00',
                                '12_30',
                                '13_00',
                                '13_30',
                                '14_00',
                                '14_30',
                                '15_00',] as $slot)
                        <option value="{{ $slot }}"
                            @selected(($filters['hour_slot'] ?? '') === $slot)>
                            {{ $slot }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">PnL min</label>
                <input type="number" step="0.01" name="pnl_min"
                    value="{{ $filters['pnl_min'] ?? '' }}"
                    class="border border-gray-300 rounded-md px-3 py-2 text-sm w-28">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">PnL max</label>
                <input type="number" step="0.01" name="pnl_max"
                    value="{{ $filters['pnl_max'] ?? '' }}"
                    class="border border-gray-300 rounded-md px-3 py-2 text-sm w-28">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Trade date from</label>
                <input type="date" name="trade_date_from"
                    value="{{ $filters['trade_date_from'] ?? '' }}"
                    class="border border-gray-300 rounded-md px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Trade date to</label>
                <input type="date" name="trade_date_to"
                    value="{{ $filters['trade_date_to'] ?? '' }}"
                    class="border border-gray-300 rounded-md px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Min CE entry</label>
                <input type="number" step="0.1" name="min_ce_entry"
                    value="{{ $filters['min_ce_entry'] ?? '' }}"
                    class="border border-gray-300 rounded-md px-3 py-2 text-sm w-28">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Max CE entry</label>
                <input type="number" step="0.1" name="max_ce_entry"
                    value="{{ $filters['max_ce_entry'] ?? '' }}"
                    class="border border-gray-300 rounded-md px-3 py-2 text-sm w-28">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Min PE entry</label>
                <input type="number" step="0.1" name="min_pe_entry"
                    value="{{ $filters['min_pe_entry'] ?? '' }}"
                    class="border border-gray-300 rounded-md px-3 py-2 text-sm w-28">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Max PE entry</label>
                <input type="number" step="0.1" name="max_pe_entry"
                    value="{{ $filters['max_pe_entry'] ?? '' }}"
                    class="border border-gray-300 rounded-md px-3 py-2 text-sm w-28">
            </div>

            {{-- Optional combined leg filter --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Min leg entry (CE or PE)</label>
                <input type="number" step="0.1" name="min_leg_entry"
                    value="{{ $filters['min_leg_entry'] ?? '' }}"
                    class="border border-gray-300 rounded-md px-3 py-2 text-sm w-32">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Max leg entry (CE or PE)</label>
                <input type="number" step="0.1" name="max_leg_entry"
                    value="{{ $filters['max_leg_entry'] ?? '' }}"
                    class="border border-gray-300 rounded-md px-3 py-2 text-sm w-32">
            </div>

            <div class="flex items-center space-x-2">
                <input
                    id="exclude_expiry_day"
                    type="checkbox"
                    name="exclude_expiry_day"
                    value="1"
                    @checked(!empty($filters['exclude_expiry_day']))
                    class="h-4 w-4 text-indigo-600 border-gray-300 rounded"
                >
                <label for="exclude_expiry_day" class="text-xs font-medium text-gray-700">
                    Exclude expiry day
                </label>
            </div>

            <div class="ml-auto flex gap-2">
                <button type="submit"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-indigo-700">
                    Apply
                </button>
                <a href="{{ route('backtests.straddles.index') }}"
                    class="bg-gray-100 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-200">
                    Reset
                </a>
            </div>
        </form>

        {{-- Table --}}
        <div class="bg-white shadow rounded-lg overflow-x-auto max-h-[700px]">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 sticky top-0 z-10">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trade date</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expiry</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Symbol</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ATM</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slot time</th>

                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">CE entry</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">CE close</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">CE PnL</th>

                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">PE entry</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">PE close</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">PE PnL</th>

                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total PnL</th>
                </tr>
                </thead>
                @php
                    $currentDate = null;
                    $dateIndex = 0;
                @endphp
                <tbody class="bg-white divide-y divide-gray-200">
                @forelse($slots as $slot)
                    @php
                        $dateString = $slot->trade_date;
                        if ($currentDate !== $dateString) {
                            $currentDate = $dateString;
                            $dateIndex++;
                        }
                        // even/odd groups get different color
                        $rowBg = $dateIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                        $rowHover = 'hover:bg-gray-100';
                    @endphp
                    <tr class="{{ $rowBg }} {{ $rowHover }}">
                        <td class="px-4 py-2 whitespace-nowrap text-gray-900">
                            {{ $slot->trade_date }}
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-gray-900">
                            {{ $slot->expiry_date->format('Y-m-d') }}
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-gray-900">
                            {{ $slot->symbol }}
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-gray-900">
                            {{ $slot->atm_strike }}
                        </td>

                        <td class="px-4 py-2 whitespace-nowrap text-gray-500">
                            {{ $slot->slot_time->format('H:i') }}
                        </td>

                        <td class="px-4 py-2 whitespace-nowrap text-right text-gray-900">
                            {{ number_format($slot->ce_entry_price, 2) }}
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-right text-gray-900">
                            {{ number_format($slot->ce_close_price, 2) }}
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-right {{ $slot->ce_pnl >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format($slot->ce_pnl, 2) }}
                        </td>

                        <td class="px-4 py-2 whitespace-nowrap text-right text-gray-900">
                            {{ number_format($slot->pe_entry_price, 2) }}
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-right text-gray-900">
                            {{ number_format($slot->pe_close_price, 2) }}
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-right {{ $slot->pe_pnl >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format($slot->pe_pnl, 2) }}
                        </td>

                        <td class="px-4 py-2 whitespace-nowrap text-right {{ $slot->total_pnl >= 0 ? 'text-green-700' : 'text-red-700' }} font-semibold">
                            {{ number_format($slot->total_pnl, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="px-4 py-4 text-center text-gray-500">
                            No data found for the selected filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            @if ($slots->total() < 50)
                <div class="mt-3 text-xs text-gray-600">
                    Showing
                    <span class="font-semibold">{{ $slots->firstItem() }}</span>
                    to
                    <span class="font-semibold">{{ $slots->lastItem() }}</span>
                    of
                    <span class="font-semibold">{{ $slots->total() }}</span>
                    results
                </div>
            @endif
            {{ $slots->links() }}
        </div>
    </div>
@endsection('content')
