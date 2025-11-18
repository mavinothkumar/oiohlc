@extends('layouts.app')
@section('title')
    Snipper Point
@endsection
@section('content')
    <div class="max-w-screen-2xl mx-auto py-8 px-2">
        <form method="GET" class="flex flex-wrap gap-6 mb-8 items-center">
            <div>
                <select name="index" class="border px-3 py-2 rounded text-base">
                    <option value="NIFTY" {{ request('index', 'NIFTY') == 'NIFTY' ? 'selected' : '' }}>NIFTY</option>
                    <option value="BANKNIFTY" {{ request('index') == 'BANKNIFTY' ? 'selected' : '' }}>BANKNIFTY</option>
                    <option value="SENSEX" {{ request('index') == 'SENSEX' ? 'selected' : '' }}>SENSEX</option>
                </select>
            </div>
            <div>
                <label>Strike Step</label>
                <select name="strike_step" class="border px-3 py-2 rounded text-base">
                    <option value="50" {{ request('strike_step') == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('strike_step', 100) == 100 ? 'selected' : '' }}>100</option>
                    <option value="150" {{ request('strike_step') == 150 ? 'selected' : '' }}>150</option>
                    <option value="200" {{ request('strike_step') == 200 ? 'selected' : '' }}>200</option>
                </select>
            </div>
            <div>
                <label class="mr-2 font-semibold text-gray-700">± Strike Range:</label>
                <input type="number" name="strike_range" step="50" min="50" max="500" class="border px-3 py-2 rounded w-28 text-base" value="{{ request('strike_range', $strikeRange) }}" />
            </div>
            <div>
                <label class="mr-2 font-semibold text-gray-700">Delta:</label>
                <input type="number" name="delta" class="border px-3 py-2 rounded w-20 text-base" value="{{ request('delta', $delta) }}" />
            </div>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded font-bold text-base shadow">Filter</button>
        </form>

        <div class="mb-4 text-base flex flex-wrap gap-10">
            <span><span class="font-bold">Index:</span> <span class="font-mono">{{ $index }}</span></span>
            <span><span class="font-bold">Spot Price:</span> <span class="font-mono">{{ number_format($spotPrice,2) }}</span></span>
            <span><span class="font-bold">Prev Day:</span> <span class="font-mono">{{ $prevDay }}</span></span>
            <span><span class="font-bold">Captured at:</span> <span class="font-mono">{{ $captured_at }}</span></span>
        </div>

        <div class="overflow-x-auto rounded-lg shadow mb-2">
            <table class="min-w-full text-lg border border-gray-200 rounded-lg bg-white">
                <thead>
                <tr class="bg-gray-300 text-gray-900">
                    <th class="py-4 px-6 font-extrabold text-left">Base Strike</th>
                    <th class="py-4 px-6 font-extrabold text-left">Option</th>
                    <th class="py-4 px-6 font-extrabold text-left">Strike</th>
                    <th class="py-4 px-6 font-extrabold text-left">Close</th>
                    <th class="py-4 px-6 font-extrabold text-left">High</th>
                    <th class="py-4 px-6 font-extrabold text-left">LTP</th>
                    <th class="py-4 px-6 font-extrabold text-left">Diff</th>
                    <th class="py-4 px-6 font-extrabold text-left">Snipper</th>
                </tr>
                </thead>
                <tbody>
                @foreach($tableRows as $row)
                    <tr class="bg-gray-100 border-b border-gray-300">
                        <td class="font-bold text-left align-middle py-5 px-6" rowspan="2">{{ $row['base'] }}</td>
                        <td class="text-left py-5 px-6">CE</td>
                        <td class="text-left py-5 px-6">{{ $row['ce_otm'] }}</td>
                        <td class="text-left py-5 px-6">{{ $row['close_ce'] !== null ? number_format($row['close_ce'],2) : '-' }}</td>
                        <td class="text-left py-5 px-6">{{ $row['high_ce'] !== null ? number_format($row['high_ce'],2) : '-' }}</td>
                        <td class="text-left py-5 px-6">{{ $row['ltp_ce'] !== null ? number_format($row['ltp_ce'],2) : '-' }}</td>
                        <td class="text-left py-5 px-6 @if($row['ce_diff'] !== null && abs($row['ce_diff']) <= $delta) bg-green-300 text-white font-bold @elseif($row['ce_diff'] !== null) bg-red-300 text-white font-bold @endif">
                            {{ $row['ce_diff'] !== null ? number_format($row['ce_diff'],2) : '-' }}
                        </td>
                        <td class="text-left py-5 px-6 font-bold" rowspan="2">
                            {{ $row['snipper_avg'] !== null ? number_format($row['snipper_avg'],2) : '-' }}
                        </td>
                    </tr>
                    <tr class="bg-white border-b border-gray-300">
                        <td class="text-left py-5 px-6">PE</td>
                        <td class="text-left py-5 px-6">{{ $row['pe_otm'] }}</td>
                        <td class="text-left py-5 px-6">{{ $row['close_pe'] !== null ? number_format($row['close_pe'],2) : '-' }}</td>
                        <td class="text-left py-5 px-6">{{ $row['high_pe'] !== null ? number_format($row['high_pe'],2) : '-' }}</td>
                        <td class="text-left py-5 px-6">{{ $row['ltp_pe'] !== null ? number_format($row['ltp_pe'],2) : '-' }}</td>
                        <td class="text-left py-5 px-6 @if($row['pe_diff'] !== null && abs($row['pe_diff']) <= $delta) bg-green-300 text-white font-bold @elseif($row['pe_diff'] !== null) bg-red-300 text-white font-bold @endif">
                            {{ $row['pe_diff'] !== null ? number_format($row['pe_diff'],2) : '-' }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>


    </div>
@endsection
