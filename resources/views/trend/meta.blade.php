@extends('layouts.app')

@section('title', 'HLC Alerts')

@section('content')
    <div class="w-full px-2 py-4">
        <div class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-2 mb-4">
            <div>
                <h1 class="text-xl font-semibold">
                    HLC Strategy Alerts – {{ $currentDay ?? '' }}
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    Previous Day: {{ $previousDay ?? 'N/A' }} • Updated every 5 minutes
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                    Today: {{ $currentDay ?? 'N/A' }}
                </span>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                    Yesterday (HLC base): {{ $previousDay ?? 'N/A' }}
                </span>
            </div>
        </div>

        <div class="w-full overflow-x-auto bg-white shadow-md rounded-lg border border-slate-200">
            <table class="w-full text-sm text-left text-gray-700">
                <thead class="bg-slate-100 text-xs uppercase tracking-wide text-slate-600">
                <tr>
                    <th class="px-3 py-2">Time</th>
                    <th class="px-3 py-2">Symbol</th>
                    <th class="px-3 py-2">Index LTP</th>
                    <th class="px-3 py-2">CE LTP</th>
                    <th class="px-3 py-2">PE LTP</th>
                    <th class="px-3 py-2">Scenario</th>
                    <th class="px-3 py-2">Signal</th>
                    <th class="px-3 py-2">CE Type</th>
                    <th class="px-3 py-2">PE Type</th>
                    <th class="px-3 py-2">Dominant</th>
                    <th class="px-3 py-2">Broken</th>
                    <th class="px-3 py-2">Zone</th>
                    <th class="px-3 py-2">Reason</th>
                    <th class="px-3 py-2">Key Levels</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    @php
                        $triggers = $row['triggers'] ?? [];

                        // Scenario badge
                        $scenarioBadge = 'bg-slate-100 text-slate-800';
                        if ($row['market_scenario'] === 'CSP-PSPB') {
                            $scenarioBadge = 'bg-amber-100 text-amber-800';
                        } elseif ($row['market_scenario'] === 'CSPB-PSP') {
                            $scenarioBadge = 'bg-sky-100 text-sky-800';
                        } elseif ($row['market_scenario'] === 'BOTHPB') {
                            $scenarioBadge = 'bg-purple-100 text-purple-800';
                        } elseif ($row['market_scenario'] === 'INDECISION') {
                            $scenarioBadge = 'bg-slate-200 text-slate-800';
                        }

                        // Trade signal badge
                        $signalBadge = 'bg-slate-100 text-slate-800';
                        if ($row['trade_signal'] === 'BUY_CE') {
                            $signalBadge = 'bg-green-100 text-green-800';
                        } elseif ($row['trade_signal'] === 'BUY_PE') {
                            $signalBadge = 'bg-red-100 text-red-800';
                        } elseif ($row['trade_signal'] === 'BUY_OPPOSITE') {
                            $signalBadge = 'bg-orange-100 text-orange-800';
                        } elseif ($row['trade_signal'] === 'SIDEWAYS_NO_TRADE') {
                            $signalBadge = 'bg-slate-200 text-slate-800';
                        }

                        // CE/PE type badges
                        $ceType = $row['ce_type'] ?? 'N/A';
                        $peType = $row['pe_type'] ?? 'N/A';

                        $ceTypeColor = str_starts_with($ceType, 'Panic')
                            ? 'bg-red-50 text-red-700'
                            : (str_starts_with($ceType, 'Profit') ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700');

                        $peTypeColor = str_starts_with($peType, 'Panic')
                            ? 'bg-red-50 text-red-700'
                            : (str_starts_with($peType, 'Profit') ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700');

                        // Dominant side
                        $dominant = $row['dominant_side'] ?? 'NONE';

                        $dominantClass =
                            $dominant === 'CALL' ? 'bg-green-50 text-green-700' :
                            ($dominant === 'PUT' ? 'bg-red-50 text-red-700' :
                             ($dominant === 'BOTH_PB' ? 'bg-purple-50 text-purple-700' : 'bg-slate-50 text-slate-700'));

                        // Broken status
                        $broken = $row['broken_status'] ?? null;
                        $brokenColor =
                            $broken === 'Up' ? 'bg-green-100 text-green-800' :
                            ($broken === 'Down' ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-800');

                        // Zone
                        $goodZone = $row['good_zone'] ?? ($triggers['good_zone'] ?? null);
                        $zoneColor =
                            $goodZone === 'CE_ZONE' ? 'bg-green-50 text-green-700' :
                            ($goodZone === 'PE_ZONE' ? 'bg-red-50 text-red-700' : 'bg-slate-50 text-slate-700');

                        // Reason builder
                        $reasonParts = [];

                        if (!empty($triggers['cs_panic']) && !empty($triggers['ps_pb'])) {
                            $reasonParts[] = 'Call Panic + Put Profit Booking (CSP-PSPB)';
                        }
                        if (!empty($triggers['cs_pb']) && !empty($triggers['ps_panic'])) {
                            $reasonParts[] = 'Call Profit Booking + Put Panic (CSPB-PSP)';
                        }
                        if (!empty($triggers['cs_pb']) && !empty($triggers['ps_pb'])) {
                            $reasonParts[] = 'Both Profit Booking (BOTHPB)';
                        }

                        if (!empty($triggers['spot_break_min_res']) && empty($triggers['spot_break_max_res'])) {
                            $reasonParts[] = 'Spot broke MinRes (towards resistance)';
                        }
                        if (!empty($triggers['spot_break_min_sup']) && empty($triggers['spot_break_max_sup'])) {
                            $reasonParts[] = 'Spot broke MinSup (towards support)';
                        }
                        if (!empty($triggers['spot_near_pdc'])) {
                            $reasonParts[] = 'Spot near PDC';
                        }
                        if (!empty($triggers['ce_above_pdh'])) {
                            $reasonParts[] = 'CE above PDH';
                        }
                        if (!empty($triggers['pe_above_pdh'])) {
                            $reasonParts[] = 'PE above PDH';
                        }

                        if ($row['trade_signal'] === 'SIDEWAYS_NO_TRADE' && empty($reasonParts)) {
                            $reasonParts[] = 'No clear side; market indecision';
                        }

                        $reasonText = implode(' • ', $reasonParts);
                    @endphp

                    <tr class="border-b last:border-b-0 hover:bg-slate-50 align-top">
                        <td class="px-3 py-2 text-xs text-slate-500 whitespace-nowrap">
                            {{ $row['recorded_at'] ?? '—' }}
                        </td>

                        <td class="px-3 py-2 font-semibold">
                            {{ $row['symbol'] }}
                        </td>

                        <td class="px-3 py-2 text-indigo-700 font-semibold">
                            @if(!is_null($row['index_ltp']))
                                {{ number_format($row['index_ltp'], 2) }}
                            @else
                                —
                            @endif
                        </td>

                        <td class="px-3 py-2">
                            @if(!is_null($row['ce_ltp']))
                                <span class="text-sky-700 font-semibold">
                                    {{ number_format($row['ce_ltp'], 2) }}
                                </span>
                            @else
                                —
                            @endif
                        </td>

                        <td class="px-3 py-2">
                            @if(!is_null($row['pe_ltp']))
                                <span class="text-emerald-700 font-semibold">
                                    {{ number_format($row['pe_ltp'], 2) }}
                                </span>
                            @else
                                —
                            @endif
                        </td>

                        <td class="px-3 py-2">
                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold {{ $scenarioBadge }}">
                                {{ $row['market_scenario'] ?? '—' }}
                            </span>
                        </td>

                        <td class="px-3 py-2">
                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold {{ $signalBadge }}">
                                {{ $row['trade_signal'] ?? '—' }}
                            </span>
                        </td>

                        <td class="px-3 py-2">
                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium {{ $ceTypeColor }}">
                                CE: {{ $ceType }}
                            </span>
                        </td>

                        <td class="px-3 py-2">
                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium {{ $peTypeColor }}">
                                PE: {{ $peType }}
                            </span>
                        </td>

                        <td class="px-3 py-2 text-xs">
                            @if($dominant !== 'NONE')
                                <span class="inline-flex px-2 py-1 rounded-full font-medium {{ $dominantClass }}">
                                    {{ $dominant }}
                                </span>
                            @else
                                <span class="text-slate-400">Neutral</span>
                            @endif
                        </td>

                        <td class="px-3 py-2 text-xs">
                            @if(!empty($broken))
                                <span class="inline-flex px-2 py-1 rounded-full font-medium {{ $brokenColor }}">
                                    {{ $broken }}
                                </span>
                                @if(!empty($row['first_broken_at']))
                                    <div class="text-[10px] text-slate-500 mt-0.5">
                                        First: {{ \Carbon\Carbon::parse($row['first_broken_at'])->format('H:i') }}
                                    </div>
                                @endif
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>

                        <td class="px-3 py-2 text-xs">
                            @if(!empty($goodZone))
                                <span class="inline-flex px-2 py-1 rounded-full font-medium {{ $zoneColor }}">
                                    {{ $goodZone }}
                                </span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>

                        <td class="px-3 py-2 text-xs text-slate-700">
                            @if($reasonText)
                                {{ $reasonText }}
                            @else
                                <span class="text-slate-400">No specific trigger</span>
                            @endif
                        </td>

                        <td class="px-3 py-2 text-xs text-slate-600">
                            <div class="flex flex-col gap-0.5">
{{--                                <span>PDH: {{ number_format($row['index_high'], 2) }}</span>--}}
{{--                                <span>PDL: {{ number_format($row['index_low'], 2) }}</span>--}}
{{--                                @if(!is_null($row['index_close']))--}}
{{--                                    <span>PDC: {{ number_format($row['index_close'], 2) }}</span>--}}
{{--                                @endif--}}
{{--                                <span>Strike: {{ number_format($row['strike'], 0) }}</span>--}}
{{--                                <span>MinR / MaxR: {{ number_format($row['min_r'], 2) }} / {{ number_format($row['max_r'], 2) }}</span>--}}
{{--                                <span>MinS / MaxS: {{ number_format($row['min_s'], 2) }} / {{ number_format($row['max_s'], 2) }}</span>--}}
{{--                                <span>Earth H / L: {{ number_format($row['earth_high'], 2) }} / {{ number_format($row['earth_low'], 2) }}</span>--}}

                                @if(!empty($row['index_crossed']))
                                    <span class="mt-1 text-[11px] text-slate-500">
                {{ $row['index_crossed'] }}
            </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="14" class="px-4 py-4 text-center text-slate-500">
                            No meta data available for today.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
