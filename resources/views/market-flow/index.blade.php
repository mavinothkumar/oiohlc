@extends('layouts.app')

@section('filters')
    <form method="GET" class="bg-white p-4 rounded shadow flex flex-wrap gap-4 items-center mb-5">
        <select name="market" class="border rounded p-1">
            @foreach($markets as $mkt)
                <option value="{{ $mkt }}" {{ $market === $mkt ? 'selected' : '' }}>{{ ucfirst($mkt) }}</option>
            @endforeach
        </select>
        <select name="type" class="border rounded p-1">
            <option value="fut" {{ $type == 'fut' ? 'selected' : '' }}>Futures</option>
            <option value="opt" {{ $type == 'opt' ? 'selected' : '' }}>Options</option>
        </select>
        <input name="strike" type="number" class="border rounded p-1 w-24" placeholder="Strike (Optional)">
        <select name="duration" class="border rounded p-1">
            <option value="1" {{ $duration == '1' ? 'selected' : '' }}>1 min</option>
            <option value="3" {{ $duration == '3' ? 'selected' : '' }}>3 min</option>
        </select>
        <input type="date" name="date" value="{{ $date }}" class="border rounded p-1">
        <input name="limit" type="number" class="border rounded p-1 w-12" value="{{ $limit }}" min="1" max="100">
        <button type="submit" class="bg-blue-600 px-4 py-1 rounded text-white">Apply</button>
    </form>
@endsection

@section('content')
    <div class="overflow-x-auto">
        <table class="min-w-full bg-white rounded shadow text-xs">
            <thead>
            <tr>
                <th class="p-2">Time</th>
                <th class="p-2">Symbol</th>
                <th class="p-2">OI</th><th class="p-2">OI Rank</th>
                <th class="p-2">Vol</th><th class="p-2">Vol Rank</th>
                <th class="p-2">Δ OI</th>
                <th class="p-2">Δ Vol</th>
                <th class="p-2">Price</th>
                <th class="p-2">Move</th>
                <th class="p-2">Book Ratio</th>
                <th class="p-2">Status</th>
            </tr>
            </thead>
            <tbody>
            @foreach($rows as $row)
                <tr class="{{ $loop->index % 2 ? 'bg-gray-50' : '' }}">
                    <td class="p-1">{{ \Carbon\Carbon::parse($row->timestamp)->format('H:i') }}</td>
                    <td class="p-1">{{ $row->symbol }}</td>
                    <td class="p-1">{{ $row->oi }}</td>
                    <td class="p-1">{{ $row->oi_rank }}</td>
                    <td class="p-1">{{ $row->volume }}</td>
                    <td class="p-1">{{ $row->volume_rank }}</td>
                    <td class="p-1">{{ $row->diffoi }}</td>
                    <td class="p-1">{{ $row->diffvolume }}</td>
                    <td class="p-1">{{ $row->close }}</td>
                    <td class="p-1">{{ number_format($row->priceMove,2) }}</td>
                    <td class="p-1">{{ $row->book_ratio }}</td>
                    <td class="p-1">
                        @if($row->status === 'absorption')
                            <span class="bg-orange-200 text-orange-900 px-2 rounded">Absorption</span>
                        @elseif($row->status === 'breakout')
                            <span class="bg-green-200 text-green-900 px-2 rounded">Breakout</span>
                        @elseif($row->status === 'false-breakout')
                            <span class="bg-red-200 text-red-900 px-2 rounded">False Breakout</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
