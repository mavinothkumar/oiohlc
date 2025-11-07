@extends('layouts.app')

@section('content')
    <div class="max-w-5xl mx-auto py-6">
        <form method="GET" class="flex flex-wrap gap-4 mb-6">
            <div>
                <select name="index" class="border px-2 py-1 rounded">
                    <option value="NIFTY" {{ request('index', 'NIFTY') == 'NIFTY' ? 'selected' : '' }}>NIFTY</option>
                    <option value="BANKNIFTY" {{ request('index') == 'BANKNIFTY' ? 'selected' : '' }}>BANKNIFTY</option>
                    <option value="SENSEX" {{ request('index') == 'SENSEX' ? 'selected' : '' }}>SENSEX</option>
                </select>
            </div>
            <div>
                <label class="mr-2 text-gray-700">± Strike Range:</label>
                <input type="number" name="strike_range" class="border px-2 py-1 rounded w-24" value="{{ request('strike_range', $strikeRange) }}" />
            </div>
            <div>
                <label class="mr-2 text-gray-700">Delta:</label>
                <input type="number" name="delta" class="border px-2 py-1 rounded w-16" value="{{ request('delta', $delta) }}" />
            </div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-1 rounded">Filter</button>
        </form>

        <div class="mb-4 text-sm">
            <span class="mr-4 font-bold">Index:</span> {{ $index }}
            <span class="mr-4 font-bold">Spot Price:</span> {{ number_format($spotPrice,2) }}
            <span class="font-bold">Prev Day:</span> {{ $prevDay }}
        </div>

        <table class="min-w-full border rounded shadow text-xs">
            <thead>
            <tr class="bg-gray-100">
                <th class="py-2 px-2">Step</th>
                <th>CE OTM</th>
                <th>PE OTM</th>
                <th>Snipper Avg</th>
                <th>Close CE</th>
                <th>Close PE</th>
                <th>High CE</th>
                <th>High PE</th>
                <th>LTP CE</th>
                <th>LTP PE</th>
                <th>CE Diff</th>
                <th>PE Diff</th>
            </tr>
            </thead>
            <tbody>
            @foreach($strikes as $step => $sp)
                @php
                    $ceOtm = $sp['ce_otm'];
                    $peOtm = $sp['pe_otm'];

                    $ohlcCe = $ohlc->where('strike', $ceOtm)->where('option_type', 'CE')->first();
                    $ohlcPe = $ohlc->where('strike', $peOtm)->where('option_type', 'PE')->first();
                    $ltpCe = $ltps[$ceOtm . 'CE'] ?? null;
                    $ltpPe = $ltps[$peOtm . 'PE'] ?? null;
                    $avg = ($ohlcCe && $ohlcPe) ? ($ohlcCe->close + $ohlcPe->close) / 2 : null;
                    $diffCe = ($avg && $ltpCe) ? $ltpCe - $avg : null;
                    $diffPe = ($avg && $ltpPe) ? $ltpPe - $avg : null;
                @endphp
                <tr>
                    <td class="font-bold">{{ $step }}</td>
                    <td>{{ $ceOtm }}</td>
                    <td>{{ $peOtm }}</td>
                    <td class="@if($avg && $diffCe && abs($diffCe) <= $delta) bg-green-200 font-bold @else bg-red-200 @endif">
                        {{ $avg !== null ? number_format($avg,2) : '-' }}
                    </td>
                    <td class="bg-yellow-100 font-bold">{{ $ohlcCe->close ?? '-' }}</td>
                    <td class="bg-yellow-100 font-bold">{{ $ohlcPe->close ?? '-' }}</td>
                    <td class="bg-pink-100">{{ $ohlcCe->high ?? '-' }}</td>
                    <td class="bg-pink-100">{{ $ohlcPe->high ?? '-' }}</td>
                    <td>{{ $ltpCe ?? '-' }}</td>
                    <td>{{ $ltpPe ?? '-' }}</td>
                    <td class="@if($diffCe !== null && abs($diffCe) <= $delta) bg-green-400 text-white @else bg-red-400 text-white @endif">
                        {{ $diffCe !== null ? number_format($diffCe,2) : '-' }}
                    </td>
                    <td class="@if($diffPe !== null && abs($diffPe) <= $delta) bg-green-400 text-white @else bg-red-400 text-white @endif">
                        {{ $diffPe !== null ? number_format($diffPe,2) : '-' }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
