@extends('layouts.app')

@section('content')
    <div class="max-w-7xl mx-auto px-4 py-6">
        <h1 class="text-2xl font-semibold text-gray-900 mb-6">
            OI Buildup Scanner
        </h1>

        {{-- Filters --}}
        <form method="GET" action="{{ route('oi-buildup.index') }}" class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-4 gap-4 mb-4">
{{--            <div>--}}
{{--                <label class="block text-sm font-medium text-gray-700">Underlying</label>--}}
{{--                <input type="text" name="underlying_symbol"--}}
{{--                    value="{{ $filters['underlying_symbol'] }}"--}}
{{--                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">--}}
{{--            </div>--}}

            <div>
                <label class="block text-sm font-medium text-gray-700">Expiry</label>
                <input type="date" name="expiry"
                    value="{{ $filters['expiry'] }}"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">At</label>
                <input
                    type="datetime-local"
                    name="at"
                    value="{{ $filters['at']
        ? \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $filters['at'])->format('Y-m-d\TH:i')
        : now()->format('Y-m-d\TH:i') }}"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                />
            </div>


            <div>
                <label class="block text-sm font-medium text-gray-700">Top N</label>
                <input type="number" name="limit" min="1" max="100"
                    value="{{ $filters['limit'] }}"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
            </div>

            <div class="md:col-span-3 lg:col-span-6 flex items-end justify-end">
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Apply Filters
                </button>
            </div>
        </form>

        @isset($no_filter)
            No Proper filter
        @endif

        {{-- Results --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
            @foreach([5, 10, 15, 30] as $i)
                <div class="bg-white shadow rounded-lg p-4 flex flex-col">
                    <div class="flex items-center justify-between mb-2">
                        <h2 class="text-sm font-semibold text-gray-800">
                            OI Buildup {{ $i }} min
                        </h2>
                        <span class="text-xs text-gray-500">
                    Top {{ $filters['limit'] }}
                </span>
                    </div>

                    <div class="flex-1">
                        @php $rows = $datasets[$i] ?? []; @endphp

                        @if(empty($rows))
                            <p class="text-xs text-gray-400 italic">
                                No data for this interval.
                            </p>
                        @else
                            <table class="min-w-full text-xs">
                                <thead>
                                <tr class="text-gray-500">
                                    <th class="text-left py-1">Strike</th>
                                    <th class="text-right py-1">ΔOI</th>
                                    <th class="text-right py-1">ΔPx</th>
                                    <th class="text-center py-1">Type</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($rows as $row)
                                    @php
                                        $color = match($row['buildup']) {
                                            'Long'   => 'text-green-600',
                                            'Short'  => 'text-red-600',
                                            'Cover'  => 'text-blue-600',
                                            'Unwind' => 'text-yellow-600',
                                            default  => 'text-gray-600',
                                        };
                                    @endphp
                                    <tr class="border-t border-gray-100">
                                        <td class="py-1 pr-2 text-gray-900">
                                            {{ $row['strike'] .' '.$row['instrument_type'] }}
                                        </td>
                                        <td class="py-1 text-right {{ $row['delta_oi'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                            {{ number_format($row['delta_oi']) }}
                                        </td>
                                        <td class="py-1 text-right {{ $row['delta_price'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                            {{ number_format($row['delta_price'], 2) }}
                                        </td>
                                        <td class="py-1 text-center font-semibold {{ $color }}">
                                            {{ $row['buildup'] }}
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

    </div>
@endsection
