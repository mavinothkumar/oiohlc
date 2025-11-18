@extends('layouts.app')

@section('title')
    HLC
@endsection

@section('content')
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">NIFTY ATM Strikes & OI (Previous Day)</h1>

        <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-4 bg-indigo-50 rounded shadow">
                <div class="text-sm text-gray-500">Expiry Date</div>
                <div class="text-lg font-semibold">{{ $expiryDate }}</div>
                <div class="text-sm text-gray-500 mt-2">Spot (Previous Day)</div>
                <div class="text-lg font-semibold">{{ $underlyingSpotPrice }}</div>
                <div class="text-sm text-gray-500 mt-2">Current Day</div>
                <div class="text-lg font-semibold">{{ $currentWorkDate }}</div>
                <div class="text-sm text-gray-500 mt-2">OHLC ({{ $prevWorkDate }})</div>

                @if($ohlcQuote)
                    <div class="text-xs">Open: <b>{{ $ohlcQuote->open }}</b>
                        | High: <b>{{ $ohlcQuote->high }}</b>
                        | Low: <b>{{ $ohlcQuote->low }}</b>
                        | Close: <b>{{ $ohlcQuote->close }}</b>
                    </div>
                @else
                    <div class="text-xs text-red-500">No OHLC data found for previous day.</div>
                @endif
            </div>
            <form method="get" action="{{ route('hlc.index') }}" class="flex items-end gap-2">
                <div>
                    <label class="block text-xs text-gray-700">Strike Range (+/-)</label>
                    <input type="number" name="strike_range" value="{{ $strikeRange }}"
                        class="border px-2 py-1 rounded w-28" min="50" step="50" max="1000">
                </div>
                <button type="submit"
                    class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-800 mb-1">
                    Update Range
                </button>
            </form>
        </div>

        <div class="overflow-x-auto shadow rounded">
            <table class="min-w-full bg-white border border-gray-200 text-xs">
                <thead>
                <tr class="bg-slate-100">
                    <th class="px-2 py-3">Strike</th>
                    <th class="px-2 py-3">CE LTP</th>
                    <th class="px-2 py-3">PE LTP</th>
                    <th class="px-2 py-3">|CE-PE|</th>
                    <th class="px-2 py-3">Min Resistance</th>
                    <th class="px-2 py-3">Min Support</th>
                    <th class="px-2 py-3">CE+PE</th>
                    <th class="px-2 py-3">Max Resistance</th>
                    <th class="px-2 py-3">Max Support</th>
                </tr>
                </thead>
                <tbody>
                @foreach($rows as $row)
                    <tr class="@if($row['strike'] == $atmStrike) bg-yellow-100 font-bold @else hover:bg-slate-50 @endif transition-colors">
                        <td class="px-2 py-2 text-center">{{ $row['strike'] }}</td>
                        <td class="px-2 py-2 text-right">{{ number_format($row['ce_ltp'], 2) }}</td>
                        <td class="px-2 py-2 text-right">{{ number_format($row['pe_ltp'],2) }}</td>
                        <td class="px-2 py-2 text-right">{{ number_format($row['diff'], 2) }}</td>
                        <td class="px-2 py-2 text-right">{{ number_format($row['min_resistance'], 2) }}</td>
                        <td class="px-2 py-2 text-right">{{ number_format($row['min_support'], 2) }}</td>
                        <td class="px-2 py-2 text-right">{{ number_format($row['sum_ce_pe'], 2) }}</td>
                        <td class="px-2 py-2 text-right">{{ number_format($row['max_resistance'], 2) }}</td>
                        <td class="px-2 py-2 text-right">{{ number_format($row['max_support'], 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @if(empty($rows))
                <div class="p-4 text-center text-red-600 font-semibold">
                    No strike data found for this range and expiry.
                </div>
            @endif
        </div>
    </div>
@endsection
