{{-- resources/views/futures/ohlc-index.blade.php --}}
@extends('layouts.app')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <h1 class="text-2xl font-semibold text-gray-800 mb-4">
            Futures OHLC
        </h1>

        {{-- Filters --}}
        <form method="GET" action="{{ route('backtests.futures.ohlc.index') }}" class="mb-6 bg-white shadow-sm rounded-lg p-4 border border-gray-200">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                {{-- Date from --}}
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">
                        From date
                    </label>
                    <input
                        type="date"
                        id="date_from"
                        name="date_from"
                        value="{{ $dateFrom }}"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    >
                </div>

                {{-- Date to --}}
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">
                        To date
                    </label>
                    <input
                        type="date"
                        id="date_to"
                        name="date_to"
                        value="{{ $dateTo }}"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    >
                </div>

                {{-- Interval --}}
                <div>
                    <label for="interval" class="block text-sm font-medium text-gray-700 mb-1">
                        Interval
                    </label>
                    <select
                        id="interval"
                        name="interval"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    >
                        <option value="all" {{ $interval === 'all' || !$interval ? 'selected' : '' }}>All</option>
                        <option value="day" {{ $interval === 'day' ? 'selected' : '' }}>Day</option>
                        <option value="5minute" {{ $interval === '5minute' ? 'selected' : '' }}>5 minute</option>
                        <option value="1hour" {{ $interval === '1hour' ? 'selected' : '' }}>1 hour</option>
                    </select>
                </div>

                {{-- Expiry --}}
                <div>
                    <label for="expiry_id" class="block text-sm font-medium text-gray-700 mb-1">
                        Expiry
                    </label>
                    <select
                        id="expiry_id"
                        name="expiry_id"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    >
                        <option value="">All</option>
                        @foreach ($expiries as $exp)
                            <option value="{{ $exp->id }}" {{ (string)$expiryId === (string)$exp->id ? 'selected' : '' }}>
                                {{ $exp->underlying_symbol }} - {{ $exp->expiry_date->format('Y-m-d') }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <button
                    type="submit"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    Apply filters
                </button>

                <a
                    href="{{ route('backtests.futures.ohlc.index') }}"
                    class="text-sm text-gray-600 hover:text-gray-800"
                >
                    Reset
                </a>
            </div>
        </form>

        {{-- Table --}}
        <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Timestamp
                    </th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Symbol
                    </th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Expiry
                    </th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Interval
                    </th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        O
                    </th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        H
                    </th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        L
                    </th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        C
                    </th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Volume
                    </th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        OI
                    </th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($ohlc as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">
                            @if($interval === '1hour')
                                {{ $row->label }}
                            @else
                                {{ $row->timestamp ?? '' }}
                            @endif
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">
                            {{ $row->underlying_symbol }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">
                            {{ optional($row->expiry)->format('Y-m-d') }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">
                            {{ $row->timestamp }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-right text-gray-700">
                            {{ number_format($row->open, 2) }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-right text-gray-700">
                            {{ number_format($row->high, 2) }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-right text-gray-700">
                            {{ number_format($row->low, 2) }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-right text-gray-700">
                            {{ number_format($row->close, 2) }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-right text-gray-700">
                            {{ number_format($row->volume) }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-right text-gray-700">
                            {{ number_format($row->open_interest) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-3 py-4 text-center text-gray-500">
                            No data found for selected filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>


        <div class="mt-4">
            {{ $ohlc->links() }}
        </div>
    </div>
@endsection
