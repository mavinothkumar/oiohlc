@extends('layouts.app')
@section('content')
    <div class="w-full bg-gray-50 px-2 py-4">
        <form method="get" class="mb-6 flex flex-wrap gap-4 items-center">
            <select name="index" class="border rounded p-2">
                <option value="NIFTY" {{ $index == 'NIFTY' ? 'selected' : '' }}>NIFTY</option>
                <option value="BANKNIFTY" {{ $index == 'BANKNIFTY' ? 'selected' : '' }}>BANKNIFTY</option>
                <option value="SENSEX" {{ $index == 'SENSEX' ? 'selected' : '' }}>SENSEX</option>
            </select>
            <input type="number" step="1" name="atm_strike" placeholder="ATM Override" class="border rounded p-2 w-32" value="{{ $atmOverride }}">
            <select name="strikeDiff" class="border rounded p-2 w-28">
                <option value="100" {{ $strikeDiff == 100 ? 'selected' : '' }}>100</option>
                <option value="50" {{ $strikeDiff == 50 ? 'selected' : '' }}>50</option>
            </select>
            <input type="number" name="proximity" min="0" step="1" value="{{ $proximity }}" class="border rounded p-2 w-28" placeholder="Proximity (default 5)">
            <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700" type="submit">Filter</button>
        </form>
        <div class="mb-2 text-base">
            <span class="font-semibold">Date:</span> {{ $prevDate }} |
            <span class="font-semibold">ATM Strike:</span> {{ $atmStrike }} |
            <span class="font-semibold">PE Strike:</span> {{ $peStrike }} |
            <span class="font-semibold">CE Strike:</span> {{ $ceStrike }}
            <span class="ml-6 font-semibold text-green-700">Highlight if ATM CE/PE LTP is within ±{{ $proximity }} of any summary value</span>
        </div>
        @if(count($summaryRows))
            <div class="mb-8">
                <h3 class="font-bold text-lg mb-2">Previous Day OTM CE/PE OHLC Combinations</h3>
                <div class="overflow-x-auto w-full">
                    <table class="table-auto border bg-white w-full mb-2 text-xs lg:text-sm">
                        <thead>
                        <tr class="bg-blue-100">
                            @foreach($summaryRows as $label => $value)
                                <th class="border px-2 py-1 whitespace-nowrap">
                                    {{ $label }}
                                </th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            @foreach($summaryRows as $value)
                                <td class="border px-2 py-1 font-mono whitespace-nowrap">{{ round($value, 2) }}</td>
                            @endforeach
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="text-gray-400 text-xs">Each value: (OTM CE X + OTM PE Y) / 2 as per column header</div>
            </div>
        @endif

        <div>
            <h3 class="font-bold text-lg mb-2">ATM 3-min LTP Data with Proximity Highlights</h3>
            <div class="overflow-x-auto w-full">
                <table class="min-w-max table-auto border bg-white w-full text-xs lg:text-sm">
                    <thead>
                    <tr class="bg-blue-50 font-semibold">
                        <th class="border p-2">Time</th>
                        <th class="border p-2">{{ $atmStrike }} CE LTP</th>
                        <th class="border p-2">{{ $atmStrike }} PE LTP</th>
                        @foreach($summaryRows as $label => $value)
                            <th class="border px-1 py-2 whitespace-nowrap">{{ $label }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($results as $row)
                        <tr>
                            <td class="border p-2">{{ $row['captured_at'] }}</td>
                            <td class="border p-2 font-mono {{ count($row['atm_ce_hits']) ? 'bg-green-200 text-green-800 font-bold' : '' }}">
                                {{ $row['atm_ce_ltp'] }}
                                @if(count($row['atm_ce_hits']))
                                    <div class="text-xs text-green-700">
                                        Hit: {{ implode(', ', $row['atm_ce_hits']) }}
                                    </div>
                                @endif
                            </td>
                            <td class="border p-2 font-mono {{ count($row['atm_pe_hits']) ? 'bg-green-200 text-green-800 font-bold' : '' }}">
                                {{ $row['atm_pe_ltp'] }}
                                @if(count($row['atm_pe_hits']))
                                    <div class="text-xs text-green-700">
                                        Hit: {{ implode(', ', $row['atm_pe_hits']) }}
                                    </div>
                                @endif
                            </td>
                            @foreach($summaryRows as $label => $value)
                                <td class="border px-1 py-2 font-mono bg-gray-50">{{ round($value, 2) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
