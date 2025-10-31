@extends('layouts.app')

@section('content')
    <div class="container mx-auto p-4">
        <form method="GET" class="flex items-end gap-4 mb-6">
            <label>
                <span class="text-gray-700">Strike Window</span>
                <input type="number" name="strike_window" class="form-input w-24" min="1" step="2" value="{{ $strike_window }}">
            </label>
            <label class="flex items-center space-x-2">
                <input type="checkbox" name="only_with_both" value="1"
                    {{ request('only_with_both') ? 'checked' : '' }}
                    class="form-checkbox rounded border-gray-300">
                <span class="text-gray-700 text-sm">Show only when both CE & PE available</span>
            </label>
            <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded font-semibold shadow hover:bg-blue-700 transition">Filter</button>
        </form>

        <div class="bg-white shadow rounded-lg p-4">
            <h2 class="font-bold mb-2 text-lg">Build Up Windows (All Symbols)</h2>
            <table class="table table-auto w-full border border-gray-200 text-xs">
                <thead>
                <tr>
                    <th>Symbol</th>
                    <th>Expiry</th>
                    <th>Time</th>
                    <th colspan="3">Call (CE)</th>
                    <th colspan="3">Put (PE)</th>
                </tr>
                <tr>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th>Strikes</th><th>Build Up</th><th>OI/Vol/LTP Diff</th>
                    <th>Strikes</th><th>Build Up</th><th>OI/Vol/LTP Diff</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr class="border-t">
                        <td class="font-semibold">{{ $row['symbol'] }}</td>
                        <td>{{ $row['expiry'] }}</td>
                        <td>{{ $row['timestamp'] }}</td>
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
                    <tr>
                        <td colspan="9" class="text-gray-500 text-center py-4">No matching build up windows found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
