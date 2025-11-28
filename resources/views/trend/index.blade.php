@extends('layouts.app')

@section('title')
Trend
@endsection

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">
    <h1 class="text-xl font-semibold mb-4">
        Index Option Panic / Profit – {{ $workingDate }}
    </h1>

    <div class="overflow-x-auto bg-white shadow-md rounded-lg">
        <table class="min-w-full text-sm text-left text-gray-700">
            <thead class="bg-slate-100 text-xs uppercase tracking-wide text-slate-600">
            <tr>
                <th class="px-4 py-2">Symbol</th>
                <th class="px-4 py-2">Strike</th>
                <th class="px-4 py-2">Option</th>
                <th class="px-4 py-2">High</th>
                <th class="px-4 py-2">Low</th>
                <th class="px-4 py-2">Close</th>
                <th class="px-4 py-2">High–Close</th>
                <th class="px-4 py-2">Close–Low</th>
                <th class="px-4 py-2">Type</th>
            </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
            <tr class="border-b last:border-b-0 hover:bg-slate-50">
                <td class="px-4 py-2 font-semibold">{{ $row['symbol'] }}</td>
                <td class="px-4 py-2">{{ number_format($row['strike'], 0) }}</td>
                <td class="px-4 py-2">{{ $row['option_type'] }}</td>
                <td class="px-4 py-2">{{ number_format($row['high'], 2) }}</td>
                <td class="px-4 py-2">{{ number_format($row['low'], 2) }}</td>
                <td class="px-4 py-2">{{ number_format($row['close'], 2) }}</td>
                <td class="px-4 py-2">{{ number_format($row['high_close_diff'], 2) }}</td>
                <td class="px-4 py-2">{{ number_format($row['close_low_diff'], 2) }}</td>
                <td class="px-4 py-2">
                                <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold {{ $row['type_color'] }}">
                                    {{ $row['type'] }}
                                </span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="px-4 py-4 text-center text-slate-500">
                    No data for {{ $workingDate }}.
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
