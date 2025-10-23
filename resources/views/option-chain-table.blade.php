@extends('layouts.app')

@section('content')
    <div class="max-w-7xl mx-auto py-6">
        <!-- Page header -->
        <h1 class="text-xl font-bold mb-4">
            {{ $symbol }} — Build‑Up Snapshot ({{ $date }}) &nbsp;|&nbsp; Expiry: {{ $expiry }}
        </h1>
        <p class="mb-6 text-sm text-gray-600">
            Underlying: <strong>{{ $underlying }}</strong>
            &nbsp;|&nbsp; Range: {{ $range[0] }} → {{ $range[1] }}
        </p>

        <!-- ============  Timeline  ============ -->
        <div class="overflow-x-auto">
            <table class="w-full table-fixed text-sm border border-gray-400 border-collapse">
                <thead class="bg-gray-200 sticky top-0 z-10">
                <tr>
                    <th class="px-2 py-2 border whitespace-nowrap">Time</th>
                    <th class="px-2 py-2 border">Strike</th>
                    @if($colVisible['Long Build'])  <th class="px-2 py-2 border">Long Build</th>@endif
                    @if($colVisible['Short Build']) <th class="px-2 py-2 border">Short Build</th>@endif
                    @if($colVisible['Long Unwind']) <th class="px-2 py-2 border">Long Unwind</th>@endif
                    @if($colVisible['Short Cover']) <th class="px-2 py-2 border">Short Cover</th>@endif
                </tr>
                </thead>

                <tbody>
                @foreach ($timeline as $row)
                    @php
                        $rowKey   = $row['time'].'|'.$row['strike'].'|'.($row['opt_type'] ?? '');
                        $rowColor = ($row['opt_type'] ?? '') === 'CE'
                                    ? 'border-l-4 border-green-500'
                                    : (($row['opt_type'] ?? '') === 'PE'
                                       ? 'border-l-4 border-red-500' : '');
                        $bestRank = $row['best_rank'] ?? null;
                        $show_price =  'inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 inset-ring inset-ring-red-600/10';
                        $is_price_positive = false;
                        if($row['diff_ltp'] > 0){
                            $is_price_positive = true;
                            $show_price =  'inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 inset-ring inset-ring-green-600/20';
                        }
                    @endphp
                    <tr
                        @if($loop->first) id="latest" @endif
                    class="{{ $loop->even ? 'bg-gray-100' : 'bg-white' }} {{ $rowColor }}"
                        data-timeline-key="{{ $rowKey }}"
                        data-best-rank="{{ $bestRank }}"
                    >
                        <td class="border px-2 py-1 font-medium">{{ $row['time'] }}</td>

                        <td class="border px-2 py-1 font-medium">
                            {{ $row['strike'] }}
                            <span class="text-xs font-semibold
                              {{ ($row['opt_type'] ?? '') === 'CE' ? 'text-green-600'
                                 : (($row['opt_type'] ?? '') === 'PE' ? 'text-red-600' : '') }}">
                            {{ $row['opt_type'] ?? '' }}
                        </span>
                            <span class="{{$show_price}}">{{$row['diff_ltp']}}</span>
                        </td>

                        @foreach (['Long Build','Short Build','Long Unwind','Short Cover'] as $t)
                            @if ($colVisible[$t])
                                <td class="border px-2 py-1">
                                    @if(isset($row[$t]))
                                        @php
                                            $src      = $row[$t]['source'] ?? [];
                                            $oiDiff   = $row[$t]['oi_diff']  ?? null;
                                            $volDiff  = $row[$t]['vol_diff'] ?? null;
                                            $oiRank   = $row[$t]['oi_rank']  ?? null;
                                            $volRank  = $row[$t]['vol_rank'] ?? null;

                                            $badgeColor = fn($rank) => match($rank) {
                                                1       => 'bg-yellow-400 text-black',
                                                2       => 'bg-gray-300 text-black',
                                                3       => 'bg-orange-400 text-white',
                                                default => 'bg-yellow-200 text-yellow-900',
                                            };

                                            $lbl = in_array('oi',$src) && in_array('vol',$src) ? 'OI&VOL'
                                                 : (in_array('oi',$src) ? 'OI' : 'VOL');
                                            $cls = $lbl === 'OI'     ? 'bg-blue-600  text-white'
                                                 : ($lbl === 'VOL'   ? 'bg-purple-600 text-white'
                                                 :                    'bg-red-200  text-black');
                                        @endphp

                                        <div class="flex justify-between">
                                            <div class="space-y-0.5">
                                                {{-- ΔOI --}}
                                                @if (in_array('oi', $src))
                                                    <div>
                                                    <span class="{{ $oiDiff >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                        {{ format_inr_compact($oiDiff) }}
                                                    </span>
                                                        <span class="ml-1 inline-block text-[10px] px-1 py-0.5
                                                         {{ $badgeColor($oiRank) }} rounded-sm font-bold"
                                                            title="ΔOI rank">
                                                        #{{ $oiRank }}
                                                    </span>
                                                    </div>
                                                @endif

                                                {{-- ΔVol --}}
                                                @if (in_array('vol', $src))
                                                    <div>
                                                        <span>{{ format_inr_compact($volDiff) }}</span>
                                                        <span class="ml-1 inline-block text-[10px] px-1 py-0.5
                                                         {{ $badgeColor($volRank) }} rounded-sm font-bold"
                                                            title="ΔVol rank">
                                                        #{{ $volRank }}
                                                    </span>
                                                    </div>
                                                @endif
                                            </div>

                                            {{-- source-type chip --}}
                                            <span class="ml-1 px-1 text-[10px] flex items-center font-semibold uppercase {{ $cls }}">
                                            {{ $lbl }}
                                        </span>
                                        </div>
                                    @else
                                        <span class="text-gray-400 text-xs">-</span>
                                    @endif
                                </td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <!-- =================  SOUND & NEW‑ROW HIGHLIGHT  ================ -->
        <audio id="notifySound" muted preload="auto">
            <source src="{{ asset('sounds/beep.mp3') }}" type="audio/ogg">
        </audio>

        <style>
            /* subtle yellow highlight for unseen rows */
            .new-row { @apply bg-yellow-100; }
        </style>

        <script>
            (() => {
                const MAX_RANK = 5;   // 🔔 threshold for sound

                // build a namespace key so multiple tabs with different params don’t clash
                const BASE = `${@json($symbol)}-${@json($date)}-${@json($expiry)}`;
                const STORAGE_ALERT = `${BASE}-lastAlertKey`;
                const STORAGE_SEEN  = `${BASE}-lastSeenKey`;

                const beep = document.getElementById('notifySound');

                /* --------------------------------------------------
                   Highlight rows that arrived since the previous load
                -------------------------------------------------- */
                function highlightNewRows() {
                    const lastSeen = localStorage.getItem(STORAGE_SEEN);
                    const rows     = document.querySelectorAll('tr[data-timeline-key]');
                    let latestKey  = null;

                    for (const row of rows) {
                        const key = row.dataset.timelineKey;
                        if (!latestKey) latestKey = key;  // first iteration == newest row

                        if (!lastSeen || key !== lastSeen) {
                            row.classList.add('new-row');
                        } else {
                            break;                         // we reached rows already seen
                        }
                    }
                    if (latestKey) localStorage.setItem(STORAGE_SEEN, latestKey);
                }

                /* --------------------------------------------------
                   Beep when newest row’s best_rank ≤ MAX_RANK
                -------------------------------------------------- */
                function playBeep() {
                    if (!beep) return;
                    beep.muted = false;
                    beep.volume = 0.8;
                    beep.currentTime = 0;
                    beep.play().catch(() => {});
                }

                function checkAndBeep() {
                    const row = document.getElementById('latest');
                    if (!row) return;

                    const best      = Number(row.dataset.bestRank);
                    if (!(best > 0 && best <= MAX_RANK)) return;

                    const latestKey = row.dataset.timelineKey;
                    if (latestKey === localStorage.getItem(STORAGE_ALERT)) return;

                    playBeep();
                    localStorage.setItem(STORAGE_ALERT, latestKey);
                }

                /* --------------------------------------------------
                   Wire‑up events
                -------------------------------------------------- */
                document.addEventListener('DOMContentLoaded', () => {
                    highlightNewRows();
                    checkAndBeep();              // may be silent if autoplay blocked
                });

                // first user gesture → unlock audio, rerun beep logic so it’s audible
                document.addEventListener('click', (evt) => {
                    if (beep && beep.muted) beep.muted = false;

                    // optional manual test button – remove if you don’t use it
                    if (evt.target.id === 'test') playBeep();

                    checkAndBeep();
                }, { once:true });
            })();
        </script>
    </div>
@endsection
