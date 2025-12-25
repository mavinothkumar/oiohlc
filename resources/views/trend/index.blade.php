@extends('layouts.app')

@section('title')
    Trend
@endsection

@section('content')
    <div class="w-full px-2 py-4">
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

        <div class="w-full overflow-x-auto bg-white shadow-md rounded-lg border border-slate-200">
            <table class="w-full text-sm text-left text-gray-700 table-fixed">
                <thead class="bg-slate-100 text-xs uppercase tracking-wide text-slate-600">
                <tr>
                    <th class="px-3 py-2 w-20">Symbol</th>
                    <th class="px-3 py-2 w-28">Idx Open</th>
                    <th class="px-3 py-2 w-24">Strike</th>
                    <th class="px-3 py-2 w-24">Index Close</th>
                    <th class="px-3 py-2 w-14">Opt</th>
                    <th class="px-3 py-2 w-28">Opt LTP</th>
                    <th class="px-3 py-2 w-28">High</th>
                    <th class="px-3 py-2 w-28">Low</th>
                    <th class="px-3 py-2 w-28">Close</th>
                    <th class="px-3 py-2 w-24">H–C</th>
                    <th class="px-3 py-2 w-24">C–L</th>
                    <th class="px-3 py-2 w-24">Type</th>
                    <th class="px-3 py-2 w-24">Broken</th>
                    <th class="px-3 py-2 w-28">Min R</th>
                    <th class="px-3 py-2 w-28">Min S</th>
                    <th class="px-3 py-2 w-28">Max R</th>
                    <th class="px-3 py-2 w-28">Max S</th>
                    <th class="px-3 py-2 w-40">Earth</th>
                </tr>
                </thead>

                @php
                    $grouped = collect($rows ?? [])->groupBy(fn($row) => $row['symbol'].'_'.$row['strike']);
                @endphp

                <tbody>
                @forelse($grouped as $contracts)
                    @foreach($contracts as $index => $row)
                        <tr class="border-b last:border-b-0 hover:bg-slate-50">
                            {{-- Symbol (merged) --}}
                            @if($index === 0)
                                <td class="px-3 py-2 font-semibold align-middle border-r border-slate-200"
                                    rowspan="{{ count($contracts) }}">
                                    {{ $row['symbol'] }}
                                </td>

                                {{-- Index LTP (merged) --}}
                                <td class="px-3 py-2 align-middle border-r border-slate-200 text-indigo-700 font-semibold"
                                    rowspan="{{ count($contracts) }}">
                                    @if(!is_null($row['index_open']))
                                        {{ number_format($row['index_open'], 2) }}
                                    @else
                                        —
                                    @endif
                                </td>

                                {{-- Strike (merged) --}}
                                <td class="px-3 py-2 align-middle border-r border-slate-200"
                                    rowspan="{{ count($contracts) }}">
                                    {{ number_format($row['strike'], 0) }}
                                </td>

                                <td class="px-3 py-2 align-middle border-r border-slate-200"
                                    rowspan="{{ count($contracts) }}">
                                    {{ number_format($row['index_close'], 0) }}
                                </td>
                            @endif

                            {{-- Option side --}}
                            <td class="px-3 py-2">
                                {{ $row['option_type'] }}
                            </td>

                            {{-- OPT LTP (no dots, just value) --}}
                            <td class="px-3 py-2 border-r border-slate-200">
                                @if(!is_null($row['option_ltp']))
                                    <span class="text-sky-700 font-semibold">
                                        {{ number_format($row['option_ltp'], 2) }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>

                            {{-- High with CE/PE dots --}}
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center gap-1">
                                    @if($row['ce_near_high'])
                                        <span class="inline-block h-2 w-2 rounded-full bg-red-500"></span>
                                    @endif
                                    @if($row['pe_near_high'])
                                        <span class="inline-block h-2 w-2 rounded-full bg-green-500"></span>
                                    @endif
                                    {{ number_format($row['high'], 2) }}
                                </span>
                            </td>

                            {{-- Low with dots --}}
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center gap-1">
                                    @if($row['ce_near_low'])
                                        <span class="inline-block h-2 w-2 rounded-full bg-red-500"></span>
                                    @endif
                                    @if($row['pe_near_low'])
                                        <span class="inline-block h-2 w-2 rounded-full bg-green-500"></span>
                                    @endif
                                    {{ number_format($row['low'], 2) }}
                                </span>
                            </td>

                            {{-- Close with dots --}}
                            <td class="px-3 py-2 font-semibold">
                                <span class="inline-flex items-center gap-1">
                                    @if($row['ce_near_close'])
                                        <span class="inline-block h-2 w-2 rounded-full bg-red-500"></span>
                                    @endif
                                    @if($row['pe_near_close'])
                                        <span class="inline-block h-2 w-2 rounded-full bg-green-500"></span>
                                    @endif
                                    {{ number_format($row['close'], 2) }}
                                </span>
                            </td>

                            <td class="px-3 py-2">{{ number_format($row['high_close_diff'], 2) }}</td>
                            <td class="px-3 py-2">{{ number_format($row['close_low_diff'], 2) }}</td>

                            <td class="px-3 py-2">
                                <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold {{ $row['type_color'] }}">
                                    {{ $row['type'] }}
                                </span>
                            </td>

                            <td class="px-3 py-2">
                                @if(!empty($row['broken']))
                                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold {{ $row['broken_color'] }}">
                                        {{ $row['broken'] }}
                                    </span>
                                @else
                                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold"> — </span>
                                @endif
                            </td>

                            {{-- R/S + Earth merged per symbol+strike --}}
                            @if($index === 0)
                                <td class="px-3 py-2 bg-slate-50 align-middle"
                                    rowspan="{{ count($contracts) }}">
                                    <span class="inline-flex items-center gap-1">
                                        @if($row['idx_minr_near'])
                                            <span class="inline-block h-2 w-2 rounded-full bg-orange-500"></span>
                                        @endif
                                        {{ number_format($row['min_r'], 2) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 bg-slate-50 align-middle"
                                    rowspan="{{ count($contracts) }}">
                                    <span class="inline-flex items-center gap-1">
                                        @if($row['idx_mins_near'])
                                            <span class="inline-block h-2 w-2 rounded-full bg-orange-500"></span>
                                        @endif
                                        {{ number_format($row['min_s'], 2) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 bg-slate-50 align-middle"
                                    rowspan="{{ count($contracts) }}">
                                    <span class="inline-flex items-center gap-1">
                                        @if($row['idx_maxr_near'])
                                            <span class="inline-block h-2 w-2 rounded-full bg-orange-500"></span>
                                        @endif
                                        {{ number_format($row['max_r'], 2) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 bg-slate-50 align-middle"
                                    rowspan="{{ count($contracts) }}">
                                    <span class="inline-flex items-center gap-1">
                                        @if($row['idx_maxs_near'])
                                            <span class="inline-block h-2 w-2 rounded-full bg-orange-500"></span>
                                        @endif
                                        {{ number_format($row['max_s'], 2) }}
                                    </span>
                                </td>

                                {{-- Earth: separate dots for E-H and E-L --}}
                                <td class="px-3 py-2 bg-amber-50 align-middle text-xs"
                                    rowspan="{{ count($contracts) }}">
                                    @if(!is_null($row['earth_value']))
                                        <div class="mb-1">
                                            <span class="font-semibold">Earth:</span>
                                            {{ number_format($row['earth_value'], 2) }}
                                        </div>
                                        <div class="flex items-center gap-1 mb-1">
                                            @if($row['idx_eh_near'])
                                                <span class="inline-block h-2 w-2 rounded-full bg-orange-500"></span>
                                            @endif
                                            <span class="font-semibold">E-H:</span>
                                            {{ number_format($row['earth_high'], 2) }}
                                        </div>
                                        <div class="flex items-center gap-1">
                                            @if($row['idx_el_near'])
                                                <span class="inline-block h-2 w-2 rounded-full bg-orange-500"></span>
                                            @endif
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
                        <td colspan="16" class="px-4 py-4 text-center text-slate-500">
                            No data available.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
