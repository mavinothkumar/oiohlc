<!-- resources/views/option_chain_buildup.blade.php -->
@extends('layouts.app')

@section('content')
    <div class="container mx-auto p-4">
        <div class="bg-white shadow rounded-lg p-4 mb-6">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Symbol</label>
                    <select name="symbol" class="block w-32 px-3 py-2 border border-gray-300 rounded-md">
                        <option value="NIFTY" {{ $symbol == 'NIFTY' ? 'selected' : '' }}>NIFTY</option>
                        <option value="BANKNIFTY" {{ $symbol == 'BANKNIFTY' ? 'selected' : '' }}>BANKNIFTY</option>
                        <option value="SENSEX" {{ $symbol == 'SENSEX' ? 'selected' : '' }}>SENSEX</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Strike Window</label>
                    <input type="number" name="strike_window" class="block w-20 px-3 py-2 border border-gray-300 rounded-md" min="1" step="2" value="{{ $strike_window }}">
                </div>
                <div>
                    <button type="submit"
                        class="px-5 py-2 bg-blue-600 text-white rounded font-semibold shadow hover:bg-blue-700 transition">
                        Filter
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white shadow rounded-lg p-4">
            <h2 class="font-bold mb-2 text-lg">Build Up Windows for {{ $symbol }} (Expiry: {{ $expiry }})</h2>
            <table class="table table-auto w-full border border-gray-200 text-xs">
                <thead>
                <tr>
                    <th rowspan="2">Time</th>
                    <th colspan="3">Call (CE)</th>
                    <th colspan="3">Put (PE)</th>
                </tr>
                <tr>
                    <th>Strikes</th><th>Build Up</th><th>OI/Vol/LTP Diff</th>
                    <th>Strikes</th><th>Build Up</th><th>OI/Vol/LTP Diff</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr class="border-t">
                        <td class="text-xs">{{ $row['timestamp'] }}</td>
                        @foreach (['CE', 'PE'] as $type)
                            @php($item = $row[$type])
                            @if($item)
                                <td>{{ implode(', ', $item['strikes']) }}</td>
                                <td class="font-bold text-green-700">{{ $item['build_up'] }}</td>
                                <td>
                                    <div>OI: {{ implode(', ', array_filter($item['diff_oi'])) }}</div>
                                    <div>Vol: {{ implode(', ', array_filter($item['diff_vol'])) }}</div>
                                    <div>LTP: {{ implode(', ', array_filter($item['diff_ltp'])) }}</div>
                                </td>
                            @else
                                <td colspan="3" class="bg-gray-50 text-gray-400 text-center italic">N/A</td>
                            @endif
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-gray-500 text-center">No results found.</td></tr>
                @endforelse
                </tbody>

            </table>
        </div>
    </div>
@endsection
