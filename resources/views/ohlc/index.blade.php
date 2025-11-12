@extends('layouts.app')

@section('title')
    OHLC
@endsection
@section('content')
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">NIFTY Option Chains (Expiry: {{ $expiryDate }})</h1>

        {{-- Filter Form --}}
        <form method="GET" action="{{ url()->current() }}" class="mb-6 flex items-center gap-4 flex-wrap">
            <label for="date" class="font-semibold">Date:</label>
            <input type="date" id="date" name="date" value="{{ $filterDate }}" max="{{ \Carbon\Carbon::today()->toDateString() }}" class="border rounded px-2 py-1">

            <label for="range" class="font-semibold">Strike Price Range (+/-):</label>
            <input type="number" id="range" name="range" value="{{ $range }}" min="50" step="50" class="border rounded px-2 py-1 w-20">

            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Apply Filter</button>
        </form>

        @if(isset($error))
            <div class="text-red-600 font-semibold mb-4">{{ $error }}</div>
        @else
            <div class="overflow-x-auto bg-gray-50 py-8">
                <table class="min-w-full bg-white shadow-md rounded-xl ring-1 ring-gray-200 border-separate border-spacing-0">
                    <thead class="bg-gray-100 text-gray-700 text-base">
                    <tr>
                        <th class="px-6 py-4 text-left font-semibold rounded-tl-xl">Strike</th>
                        <th class="px-4 py-4 text-center font-semibold">Option Type</th>
                        <th class="px-4 py-4 text-center">Open</th>
                        <th class="px-4 py-4 text-center">High</th>
                        <th class="px-4 py-4 text-center">Low</th>
                        <th class="px-4 py-4 text-center">Close</th>
                        <th class="px-4 py-4 text-center">Last Price</th>
                        <th class="px-4 py-4 text-center">Last Price + Strike</th>
                        <th class="px-4 py-4 text-center">Underlying Spot Price</th>
                        <th class="px-4 py-4 text-center rounded-tr-xl">Underlying Spot Price - (Last Price + Strike)</th>
                    </tr>
                    </thead>
                    @php
                        $grouped = $optionsData->groupBy('strike')->sortKeys();
                        $rowColors = ['bg-white', 'bg-gray-50'];
                        $strikeIndex = 0;
                        $atmStrike = null;
                        $atmMinDiff = null;
                        // Find ATM
                        foreach ($grouped as $strike => $rows) {
                            $rows = $rows->sortBy(fn($opt) => $opt->option_type === 'CE' ? 0 : 1)->values();
                            if ($rows->count() == 2) {
                                $diff = abs(($rows[0]->last_price ?? 0) - ($rows[1]->last_price ?? 0));
                                if ($atmMinDiff === null || $diff < $atmMinDiff) {
                                    $atmMinDiff = $diff;
                                    $atmStrike = $strike;
                                }
                            }
                        }
                    @endphp
                    <tbody>
                    @foreach($grouped as $strike => $rows)
                        @php
                            $rows = $rows->sortBy(fn($opt) => $opt->option_type === 'CE' ? 0 : 1)->values();
                            $bgColor = ($strikeIndex % 2 == 0) ? 'bg-white' : 'bg-gray-50';
                            // ATM highlight
                            $atmColor = $strike == $atmStrike ? 'bg-green-100' : $bgColor;
                            $borderColor = $strike == $atmStrike ? 'border-green-400' : 'border-gray-300';
                            $strikeIndex++;
                        @endphp
                        <tr class="{{ $atmColor }}">
                            <td class="font-bold px-6 py-4 align-middle rounded-l-lg left-border-blue border-r {{ $borderColor }}" rowspan="{{ $rows->count() }}">
                                {{ $strike }}
                            </td>
                            {{-- CE row --}}
                            <td class="py-4 px-4 font-semibold text-center {{ $rows[0]->option_type == 'CE' ? 'text-green-600' : 'text-red-600' }} border-b border-gray-300">
                                {{ $rows[0]->option_type }}
                            </td>
                            <td class="py-4 px-4 text-center border-b border-gray-300">{{ number_format($rows[0]->open, 2) }}</td>
                            <td class="py-4 px-4 text-center border-b border-gray-300">{{ number_format($rows[0]->high, 2) }}</td>
                            <td class="py-4 px-4 text-center border-b border-gray-300">{{ number_format($rows[0]->low, 2) }}</td>
                            <td class="py-4 px-4 text-center border-b border-gray-300">{{ number_format($rows[0]->close, 2) }}</td>
                            <td class="py-4 px-4 text-center border-b border-gray-300">{{ number_format($rows[0]->last_price, 2) }}</td>
                            <td class="py-4 px-4 text-center border-b border-gray-300">{{ number_format($rows[0]->last_price + $rows[0]->strike, 2) }}</td>
                            <td class="py-4 px-4 text-center font-bold text-blue-800 border-b border-gray-300">{{ number_format($spotPrice, 2) }}</td>
                            <td class="py-4 px-4 text-center font-bold border-b border-gray-300
                {{ ($spotPrice - ($rows[0]->last_price + $rows[0]->strike)) < 0 ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600' }}">
                                {{ number_format($spotPrice - ($rows[0]->last_price + $rows[0]->strike), 2) }}
                            </td>
                        </tr>
                        @if(isset($rows[1]))
                            <tr class="{{ $atmColor }}">
                                <td class="py-4 px-4 font-semibold text-center border-t border-gray-400 {{ $rows[1]->option_type == 'CE' ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $rows[1]->option_type }}
                                </td>
                                <td class="py-4 px-4 text-center border-t border-gray-400">{{ number_format($rows[1]->open, 2) }}</td>
                                <td class="py-4 px-4 text-center border-t border-gray-400">{{ number_format($rows[1]->high, 2) }}</td>
                                <td class="py-4 px-4 text-center border-t border-gray-400">{{ number_format($rows[1]->low, 2) }}</td>
                                <td class="py-4 px-4 text-center border-t border-gray-400">{{ number_format($rows[1]->close, 2) }}</td>
                                <td class="py-4 px-4 text-center border-t border-gray-400">{{ number_format($rows[1]->last_price, 2) }}</td>
                                <td class="py-4 px-4 text-center border-t border-gray-400">{{ number_format($rows[1]->last_price + $rows[1]->strike, 2) }}</td>
                                <td class="py-4 px-4 text-center font-bold text-blue-800 border-t border-gray-400">{{ number_format($spotPrice, 2) }}</td>
                                <td class="py-4 px-4 text-center font-bold border-t border-gray-400
                    {{ ($spotPrice - ($rows[1]->last_price + $rows[1]->strike)) < 0 ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600' }}">
                                    {{ number_format($spotPrice - ($rows[1]->last_price + $rows[1]->strike), 2) }}
                                </td>
                            </tr>
                        @endif
                    @endforeach
                    </tbody>



                </table>
            </div>


        @endif
    </div>
@endsection
