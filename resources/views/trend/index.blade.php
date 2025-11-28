@extends('layouts.app')

@section('title')
Trend
@endsection

@section('content')
    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="flex items-baseline justify-between mb-4">
            <h1 class="text-xl font-semibold">
                Index Option Trend – {{ $previousDay ?? '' }}
            </h1>

            @if(!empty($previousDay))
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                    Previous Day: {{ $previousDay }}
                </span>
            @endif
        </div>

        <div class="overflow-x-auto bg-white shadow-md rounded-lg border border-slate-200">
            <table class="min-w-full text-sm text-left text-gray-700">
                <thead class="bg-slate-100 text-xs uppercase tracking-wide text-slate-600">
                <tr>
                    <th class="px-3 py-2">Symbol</th>
                    <th class="px-3 py-2">Strike</th>
                    <th class="px-3 py-2">Option</th>
                    <th class="px-3 py-2">High</th>
                    <th class="px-3 py-2">Low</th>
                    <th class="px-3 py-2">Close</th>
                    <th class="px-3 py-2">High–Close</th>
                    <th class="px-3 py-2">Close–Low</th>
                    <th class="px-3 py-2">Type</th>
                    <th class="px-3 py-2">Min R</th>
                    <th class="px-3 py-2">Min S</th>
                    <th class="px-3 py-2">Max R</th>
                    <th class="px-3 py-2">Max S</th>
                    <th class="px-3 py-2">Earth</th>
                </tr>
                </thead>

                @php
                    // group by symbol + strike so we can rowspan for each CE/PE pair
                    $grouped = collect($rows ?? [])->groupBy(function ($row) {
                        return $row['symbol'].'_'.$row['strike'];
                    });
                @endphp

                <tbody>
                @forelse($grouped as $contracts)
                    @foreach($contracts as $index => $row)
                        <tr class="border-b last:border-b-0 hover:bg-slate-50">
                            {{-- Symbol (merged per symbol+strike) --}}
                            @if($index === 0)
                                <td class="px-3 py-2 font-semibold align-middle border-r border-slate-200"
                                    rowspan="{{ count($contracts) }}">
                                    {{ $row['symbol'] }}
                                </td>

                                {{-- Strike (merged per symbol+strike) --}}
                                <td class="px-3 py-2 align-middle border-r border-slate-200"
                                    rowspan="{{ count($contracts) }}">
                                    {{ number_format($row['strike'], 0) }}
                                </td>
                            @endif

                            {{-- Option specific --}}
                            <td class="px-3 py-2">
                                {{ $row['option_type'] }}
                            </td>
                            <td class="px-3 py-2">
                                {{ number_format($row['high'], 2) }}
                            </td>
                            <td class="px-3 py-2">
                                {{ number_format($row['low'], 2) }}
                            </td>
                            <td class="px-3 py-2 font-semibold">
                                {{ number_format($row['close'], 2) }}
                            </td>
                            <td class="px-3 py-2">
                                {{ number_format($row['high_close_diff'], 2) }}
                            </td>
                            <td class="px-3 py-2">
                                {{ number_format($row['close_low_diff'], 2) }}
                            </td>
                            <td class="px-3 py-2">
                                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold {{ $row['type_color'] }}">
                                        {{ $row['type'] }}
                                    </span>
                            </td>

                            {{-- Min/Max R/S & Earth (merged per symbol+strike) --}}
                            @if($index === 0)
                                <td class="px-3 py-2 bg-slate-50 align-middle"
                                    rowspan="{{ count($contracts) }}">
                                    {{ number_format($row['min_r'], 2) }}
                                </td>
                                <td class="px-3 py-2 bg-slate-50 align-middle"
                                    rowspan="{{ count($contracts) }}">
                                    {{ number_format($row['min_s'], 2) }}
                                </td>
                                <td class="px-3 py-2 bg-slate-50 align-middle"
                                    rowspan="{{ count($contracts) }}">
                                    {{ number_format($row['max_r'], 2) }}
                                </td>
                                <td class="px-3 py-2 bg-slate-50 align-middle"
                                    rowspan="{{ count($contracts) }}">
                                    {{ number_format($row['max_s'], 2) }}
                                </td>

                                {{-- Earth: base value, E-H, E-L in one cell --}}
                                <td class="px-3 py-2 bg-amber-50 align-middle text-xs"
                                    rowspan="{{ count($contracts) }}">
                                    @if(!is_null($row['earth_value']))
                                        <div>
                                            <span class="font-semibold">Earth:</span>
                                            {{ number_format($row['earth_value'], 2) }}
                                        </div>
                                        <div>
                                            <span class="font-semibold">E-H:</span>
                                            {{ number_format($row['earth_high'], 2) }}
                                        </div>
                                        <div>
                                            <span class="font-semibold">E-L:</span>
                                            {{ number_format($row['earth_low'], 2) }}
                                        </div>
                                    @else
                                        —
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="14" class="px-4 py-4 text-center text-slate-500">
                            No data available.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
