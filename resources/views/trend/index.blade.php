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
                    <th class="px-3 py-2 w-28">Symbol</th>
                    <th class="px-3 py-2 w-28">Idx Open</th>
                    <th class="px-3 py-2 w-24">Strike</th>
                    <th class="px-3 py-2 w-24">Index Close</th>
                    <th class="px-3 py-2 w-24">Open Type</th>
                    <th class="px-3 py-2 w-24">Open Val</th>
                    <th class="px-3 py-2 w-86"></th>
                    <th class="px-3 py-2 w-34">ATM Res</th>
                    <th class="px-3 py-2 w-34">ATM Sup</th>
                    <th class="px-3 py-2 w-20">Avg Res</th>
                    <th class="px-3 py-2 w-20">Avg Sup</th>
                    <th class="px-3 py-2 w-34">Res</th>
                    <th class="px-3 py-2 w-34">Sup</th>
                    <th class="px-3 py-2 w-34">Earth</th>
                </tr>
                </thead>

                <tbody>

                @forelse($dailyTrends as $index => $row)
                    <tr class="border-b last:border-b-0 hover:bg-slate-50">
                        {{-- Symbol (merged) --}}
                        <td class="px-3 py-2 font-semibold align-middle border-r border-slate-200"
                            rowspan="">
                            {{ $row['symbol_name'] }}
                        </td>

                        {{-- Index LTP (merged) --}}
                        <td class="px-3 py-2 align-middle border-r border-slate-200 text-indigo-700 font-semibold"
                            rowspan="">
                            @if(!is_null($row['current_day_index_open']))
                                <div class="py-2">
                                    {{ number_format($row['current_day_index_open'], 2) }}
                                </div>

                                <div class="py-2">
                                    ATM Open: {{ number_format($row['atm_index_open'], 2) }}
                                </div>

                            @else
                                —
                            @endif
                        </td>

                        {{-- Strike (merged) --}}
                        <td class="px-3 py-2 align-middle border-r border-slate-200"
                            rowspan="">
                            <div class="py-2">
                                {{ number_format($row['strike'], 0) }}
                            </div>

                            <div class="py-2">
                                <strong>CE:</strong> {{ number_format($row['atm_ce'], 0) }}</div>
                            <div class="py-2">
                                <strong>PE:</strong> {{ number_format($row['atm_pe'], 0) }} </div>


                        </td>

                        <td class="px-3 py-2 align-middle border-r border-slate-200"
                            rowspan="">
                            {{ number_format($row['index_close'], 2) }}
                        </td>


                        <td class="px-3 py-2 align-middle border-r border-slate-200"
                            rowspan="">
                            {{ $row['open_type'] }}
                        </td>

                        <td class="px-3 py-2 align-middle border-r border-slate-200"
                            rowspan="">
                            {{ number_format($row['open_value'], 2) }}
                        </td>


                        {{-- Option side --}}
                        <td class="px-3 py-2">
                            <table>
                                <tr>
                                    <th class="px-3 py-2"></th>
                                    <th class="px-3 py-2">High</th>
                                    <th class="px-3 py-2">Low</th>
                                    <th class="px-3 py-2">Close</th>
                                    <th class="px-3 py-2">Type</th>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2"><strong>CE:</strong></td>
                                    <td class="px-3 py-2"> {{ number_format($row['ce_high'], 2) }}</td>
                                    <td class="px-3 py-2"> {{ number_format($row['ce_low'], 2) }}</td>
                                    <td class="px-3 py-2"> {{ number_format($row['ce_close'], 2) }}</td>
                                    <td class="px-3 py-2"> {{ $row['ce_type']}}</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2"><strong>PE:</strong></td>
                                    <td class="px-3 py-2"> {{ number_format($row['pe_high'], 2) }}</td>
                                    <td class="px-3 py-2"> {{ number_format($row['pe_low'], 2) }}</td>
                                    <td class="px-3 py-2"> {{ number_format($row['pe_close'], 2) }}</td>
                                    <td class="px-3 py-2"> {{ $row['pe_type']}}</td>
                                </tr>
                            </table>
                        </td>


                        <td class="px-3 py-2 bg-slate-50 align-middle" rowspan="">
                            <div class=" py-2">
                                <strong>R1:</strong> {{ number_format($row['atm_r'], 2) }}
                            </div>
                            <div class=" py-2">
                                <strong>R2:</strong> {{ number_format($row['atm_r_1'], 2) }}
                            </div>
                            <div class=" py-2">
                                <strong>R3:</strong> {{ number_format($row['atm_r_2'], 2) }}
                            </div>
                            <div class=" py-2">
                                <strong>R4:</strong> {{ number_format($row['atm_r_3'], 2) }}
                            </div>
                        </td>
                        <td class="px-3 py-2 bg-slate-50 align-middle" rowspan="">
                            <div class=" py-2">
                                <strong>S1:</strong> {{ number_format($row['atm_s'], 2) }}
                            </div>
                            <div class=" py-2">
                                <strong>S2:</strong> {{ number_format($row['atm_s_1'], 2) }}
                            </div>
                            <div class=" py-2">
                                <strong>S3:</strong> {{ number_format($row['atm_s_2'], 2) }}
                            </div>
                            <div class=" py-2">
                                <strong>S4:</strong> {{ number_format($row['atm_s_3'], 2) }}
                            </div>

                        </td>
                        <td class="px-3 py-2 bg-slate-50 align-middle" rowspan="">
                            {{ number_format($row['atm_r_avg'], 2) }}
                        </td>
                        <td class="px-3 py-2 bg-slate-50 align-middle" rowspan="">
                            {{ number_format($row['atm_s_avg'], 2) }}
                        </td>
                        <td class="px-3 py-2 bg-slate-50 align-middle" rowspan="">
                            <div class=" py-2">
                                <strong>Min:</strong> {{ number_format($row['min_r'], 2) }}
                            </div>
                            <div class=" py-2">
                                <strong>Max:</strong> {{ number_format($row['max_r'], 2) }}
                            </div>

                        </td>
                        <td class="px-3 py-2 bg-slate-50 align-middle" rowspan="">
                            <div class=" py-2">
                                <strong>Min:</strong> {{ number_format($row['min_s'], 2) }}
                            </div>
                            <div class=" py-2">
                                <strong>Max:</strong> {{ number_format($row['max_s'], 2) }}
                            </div>

                        </td>

                        {{-- Earth: separate dots for E-H and E-L --}}
                        <td class="px-3 py-2 bg-amber-50 align-middle text-xs"
                            rowspan="">
                            @if(!is_null($row['earth_value']))
                                <div class="mb-1">
                                    <span class="font-semibold">Earth:</span>
                                    {{ number_format($row['earth_value'], 2) }}
                                </div>
                                <div class="flex items-center gap-1 mb-1">
                                    <span class="font-semibold">E-H:</span>
                                    {{ number_format($row['earth_high'], 2) }}
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="font-semibold">E-L:</span>
                                    {{ number_format($row['earth_low'], 2) }}
                                </div>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
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
