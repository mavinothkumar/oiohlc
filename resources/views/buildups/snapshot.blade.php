@extends('layouts.app')

@section('title','Build Up')

@section('content')

    <div class="max-w-7xl mx-auto px-4 py-6">

        <div class="flex items-center justify-between mb-1">
            <h1 class="text-xl font-bold text-gray-800">
                {{ $underlyingLabel }} — Build-Up Snapshot
                <span class="text-gray-400 font-normal text-sm">({{ $date }})</span>
                <span class="text-gray-500 font-normal text-base ml-2">| Expiry: {{ $expiry }}</span>
            </h1>
            <form method="GET" class="flex items-center gap-2 text-sm">
                <label class="text-gray-500 font-medium">Top</label>
                <select name="top" onchange="this.form.submit()"
                    class="border border-gray-300 rounded px-2 py-1 text-sm text-gray-700 focus:outline-none focus:ring-1 focus:ring-blue-400">
                    @foreach ([5, 10, 15, 20] as $n)
                        <option value="{{ $n }}" {{ $top == $n ? 'selected' : '' }}>{{ $n }}</option>
                    @endforeach
                </select>
{{--                <input type="hidden" name="underlying" value="{{ request('underlying') }}">--}}
{{--                <input type="hidden" name="label" value="{{ request('label') }}">--}}
{{--                <input type="hidden" name="date" value="{{ $date }}">--}}
            </form>
        </div>

        <p class="text-sm text-gray-600 mb-4">
            Underlying:
            <strong>{{ number_format($spotPrice, 2) }}</strong>
            &nbsp;|&nbsp;
            Range:
            <strong>{{ $allStrikes->min() }} → {{ $allStrikes->max() }}</strong>
        </p>

        <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
            <div class="overflow-y-auto max-h-[75vh]">
                <table class="min-w-full text-sm text-gray-700 border-collapse">
                    <thead class="bg-gray-100 text-gray-600 uppercase text-xs sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-3 text-center border-r border-gray-200 w-20">Time</th>
                        <th class="px-4 py-3 text-center border-r border-gray-200 w-36">Strike</th>
                        <th class="px-4 py-3 text-center border-r border-gray-200 w-8">Type</th>
                        <th class="px-4 py-3 text-center border-r border-gray-200">Long Build</th>
                        <th class="px-4 py-3 text-center border-r border-gray-200">Short Build</th>
                        <th class="px-4 py-3 text-center border-r border-gray-200">Long Unwind</th>
                        <th class="px-4 py-3 text-center">Short Cover</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($snapshot as $timeSlot => $strikes)

                        {{-- Time slot separator --}}
                        <tr class="border-t-2 border-gray-300 bg-gray-50 js-time-row" data-time="{{ $timeSlot }}">
                            <td colspan="7" class="px-4 py-1 text-xl font-bold tracking-widest uppercase">
                                {{ $timeSlot }}
                            </td>
                        </tr>

                        @foreach ($strikes as $strike)
                            @php
                                $buildUp   = $strike['build_up'];
                                $rankType  = $strike['rank_type']; // 'OI' or 'VOL'
                                $rank      = $strike['rank'];

                                // Show the value relevant to rank_type
                                $isOi      = $rankType === 'OI';
                                $rawValue  = $isOi ? $strike['diff_oi'] : $strike['diff_volume'];
                                $formatted = number_format(abs($rawValue) / 100000, 2);
                                $sign      = $rawValue >= 0 ? '+' : '-';
                                $color     = $rawValue >= 0 ? 'text-green-600' : 'text-red-500';

                                $buildColors = [
                                    'Long Build'   => 'bg-blue-600',
                                    'Short Build'  => 'bg-orange-500',
                                    'Long Unwind'  => 'bg-indigo-500',
                                    'Short Cover'  => 'bg-purple-600',
                                ];
                                $badgeColor = $buildColors[$buildUp] ?? 'bg-gray-400';

                                $rankBadgeColor = $isOi ? $badgeColor : 'bg-purple-500';
                            @endphp

                            <tr class="bg-white hover:bg-blue-50 transition-colors border-t border-gray-100">

                                <td class="px-4 py-2 border-r border-gray-100"></td>

                                {{-- Strike --}}
                                <td class="px-4 py-2 text-center border-r border-gray-100">
                                    <span class="font-semibold text-gray-900">
                                        {{ number_format($strike['strike_price'], 0) }}
                                    </span>
                                    <span class="ml-1 text-xs font-bold
                                        {{ $strike['option_type'] === 'CE' ? 'text-teal-600' : 'text-pink-500' }}">
                                        {{ $strike['option_type'] }}
                                    </span>
                                </td>

                                {{-- OI / VOL type indicator --}}
                                <td class="px-2 py-2 text-center border-r border-gray-100">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-white text-xs font-bold {{ $rankBadgeColor }}">
                                        {{ $rankType }}
                                    </span>
                                </td>

                                @foreach (['Long Build', 'Short Build', 'Long Unwind', 'Short Cover'] as $col)
                                    <td class="px-4 py-2 text-center {{ !$loop->last ? 'border-r border-gray-100' : '' }}">
                                        @if ($buildUp === $col)
                                            <div class="flex items-center justify-center gap-1">
                                                <span class="font-semibold {{ $color }} text-xs">
                                                    {{ $sign }}{{ $formatted }} L
                                                </span>
                                                <span class="inline-flex items-center justify-center w-4 h-4 rounded text-white text-xs font-bold {{ $rankBadgeColor }}">
                                                    {{ $rank }}
                                                </span>
                                            </div>
                                        @else
                                            <span class="text-gray-200">—</span>
                                        @endif
                                    </td>
                                @endforeach

                            </tr>
                        @endforeach

                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400">
                                No data available for {{ $date }}
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <audio id="snapshotAlert" preload="auto">
        <source src="{{ asset('sounds/beep.mp3') }}" type="audio/mpeg">
    </audio>
    <script>
        (() => {
            const STORAGE_KEY = 'buildup_seen_times_v1';

            function getSeenSet() {
                try { return new Set(JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]')); }
                catch { return new Set(); }
            }

            function saveSeenSet(set) {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(Array.from(set)));
            }

            function getTimesFromDom() {
                return Array.from(document.querySelectorAll('.js-time-row'))
                    .map(el => el.dataset.time)
                    .filter(Boolean);
            }

            function playAlert() {
                const audio = document.getElementById('snapshotAlert');
                if (!audio) return;

                // Browsers may block autoplay until the user interacts once with the page.
                audio.currentTime = 0;
                audio.play().catch(() => {});
            }

            function checkForNewTimesAndAlert() {
                const seen = getSeenSet();
                const times = getTimesFromDom();

                let foundNew = false;
                for (const t of times) {
                    if (!seen.has(t)) {
                        seen.add(t);
                        foundNew = true;
                    }
                }

                if (foundNew) {
                    saveSeenSet(seen);
                    playAlert();
                }
            }

            // Run once on load
            document.addEventListener('DOMContentLoaded', checkForNewTimesAndAlert);
        })();
    </script>
@endsection
