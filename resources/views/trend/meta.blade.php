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
                        $triggers      = $row['triggers'] ?? [];
                        $levelsCrossed = $row['levels_crossed'] ?? [];
                        $indexCrossed  = $row['index_crossed'] ?? null;
                        $reasonText    = $row['reason'] ?? '';

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

                        $ceType = $row['ce_type'] ?? 'N/A';
                        $peType = $row['pe_type'] ?? 'N/A';

                        $ceTypeColor = str_starts_with($ceType, 'Panic')
                            ? 'bg-red-50 text-red-700'
                            : (str_starts_with($ceType, 'Profit') ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700');

                        $peTypeColor = str_starts_with($peType, 'Panic')
                            ? 'bg-red-50 text-red-700'
                            : (str_starts_with($peType, 'Profit') ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700');

                        $dominant = $row['dominant_side'] ?? 'NONE';
                        $dominantClass =
                            $dominant === 'CALL' ? 'bg-green-50 text-green-700' :
                            ($dominant === 'PUT' ? 'bg-red-50 text-red-700' :
                             ($dominant === 'BOTH_PB' ? 'bg-purple-50 text-purple-700' : 'bg-slate-50 text-slate-700'));

                        $broken = $row['broken_status'] ?? null;
                        $brokenColor =
                            $broken === 'Up' ? 'bg-green-100 text-green-800' :
                            ($broken === 'Down' ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-800');

                        $goodZone = $row['good_zone'] ?? ($triggers['good_zone'] ?? null);
                        $zoneColor =
                            $goodZone === 'CE_ZONE' ? 'bg-green-50 text-green-700' :
                            ($goodZone === 'PE_ZONE' ? 'bg-red-50 text-red-700' : 'bg-slate-50 text-slate-700');
                    @endphp

                    <tr class="border-b last:border-b-0 hover:bg-slate-50 align-top">
                        <td class="px-3 py-2 text-xs text-slate-500 whitespace-nowrap">
                            {{ $row['recorded_at'] ?? '—' }}
                        </td>
                        <td class="px-3 py-2 font-semibold">
                            {{ $row['symbol'] }}
                        </td>
                        <td class="px-3 py-2 text-indigo-700 font-semibold">
                            {{ $row['index_ltp'] !== null ? number_format($row['index_ltp'], 2) : '—' }}
                        </td>
                        <td class="px-3 py-2">
                            {{ $row['ce_ltp'] !== null ? number_format($row['ce_ltp'], 2) : '—' }}
                        </td>
                        <td class="px-3 py-2">
                            {{ $row['pe_ltp'] !== null ? number_format($row['pe_ltp'], 2) : '—' }}
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
                            @if($broken)
                                <span class="inline-flex px-2 py-1 rounded-full font-medium {{ $brokenColor }}">
                                {{ $broken }}
                            </span>
                                @if($row['first_broken_at'])
                                    <div class="text-[10px] text-slate-500 mt-0.5">
                                        First: {{ \Carbon\Carbon::parse($row['first_broken_at'])->format('H:i') }}
                                    </div>
                                @endif
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>

                        <td class="px-3 py-2 text-xs">
                            @if($goodZone)
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
                                @if($indexCrossed)
                                    <span class="text-[11px] text-slate-700">
                                    {{ $indexCrossed }}
                                </span>
                                @endif

                                @if(!empty($levelsCrossed))
                                    <details class="mt-1">
                                        <summary class="cursor-pointer text-[11px] text-slate-500">
                                            View all levels
                                        </summary>
                                        <div class="mt-1 space-y-0.5">
                                            @foreach($levelsCrossed as $lc)
                                                <div class="text-[11px] text-slate-600">
                                                    {{ $lc['level'] ?? '' }}
                                                    ({{ $lc['direction'] ?? '' }})
                                                    @if(isset($lc['price'], $lc['level_price']))
                                                        – Px: {{ $lc['price'] }} vs {{ $lc['level_price'] }}
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>
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
