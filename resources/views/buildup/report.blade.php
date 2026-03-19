@extends('layouts.app')

@section('title', 'Build-Up Report')

@section('content')
    <div class="w-full min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-slate-100">

        {{-- ✅ Sticky Filter Bar (replaces old header + filter card) --}}
        <div class="sticky top-0 bg-slate-900/95 backdrop-blur border-b border-slate-700 shadow-xl">
            <form method="GET" action="{{ route('test.buildup.report') }}" id="filterForm">
                <div class="w-full px-6 py-3 flex flex-wrap items-center gap-4">

                    {{-- Page Title (inline, compact) --}}
                    <div class="flex items-center gap-2 mr-4">
                        <div class="bg-indigo-500/20 rounded-lg p-1.5">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <span class="text-white font-bold text-sm tracking-wide whitespace-nowrap">Build-Up Report</span>
                    </div>

                    {{-- Divider --}}
                    <div class="hidden sm:block w-px h-8 bg-slate-700"></div>

                    {{-- Expiry Dropdown --}}
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-semibold text-indigo-300 uppercase tracking-wider whitespace-nowrap">Expiry</label>
                        <select name="expiry" id="expiry"
                            onchange="autoSubmit()"
                            class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-slate-100 text-sm
                               focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                               hover:border-indigo-400 transition-colors cursor-pointer min-w-[150px]">
                            <option value="">-- Expiry --</option>
                            @foreach($expiries as $exp)
                                <option value="{{ $exp }}" {{ $expiry === $exp ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::parse($exp)->format('d M Y') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Build-Up Type --}}
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-semibold text-violet-300 uppercase tracking-wider whitespace-nowrap">Build-Up</label>
                        <select name="build_up_type" id="buildUpType"
                            onchange="autoSubmit()"
                            class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-slate-100 text-sm
           focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500
           hover:border-violet-400 transition-colors cursor-pointer min-w-[130px]">
                            <option value="">-- Type --</option>
                            <optgroup label="── Same Build ──">
                                @foreach(['LB-LB', 'SB-SB', 'LU-LU', 'SC-SC'] as $type)
                                    <option value="{{ $type }}" {{ $buildType === $type ? 'selected' : '' }}>{{ $type }}</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="── Mixed ──">
                                @foreach(['SB-LB', 'SB-SC', 'SB-LU', 'LB-SC', 'LB-LU', 'SC-LU'] as $type)
                                    <option value="{{ $type }}" {{ $buildType === $type ? 'selected' : '' }}>{{ $type }}</option>
                                @endforeach
                            </optgroup>
                        </select>

                    </div>

                    {{-- Submit Button --}}
                    <button type="submit"
                        class="bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700
                           text-white font-semibold px-5 py-2 rounded-lg shadow transition-all duration-200
                           flex items-center gap-1.5 text-sm whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Generate
                    </button>

                    {{-- Active filter badge --}}
                    @if($expiry && $buildType)
                        <div class="ml-auto flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full bg-violet-500/20 border border-violet-500/40 text-violet-300 text-xs font-bold">
                        {{ \Carbon\Carbon::parse($expiry)->format('d M Y') }}
                    </span>
                            <span class="px-3 py-1 rounded-full bg-indigo-500/20 border border-indigo-500/40 text-indigo-300 text-xs font-bold">
                        {{ $buildType }}
                    </span>
                            <span class="text-slate-500 text-xs">{{ $results->count() }} strikes</span>
                        </div>
                    @endif

                </div>
            </form>
        </div>

        {{-- ✅ Full Width Table Area --}}
        <div class="w-full px-4 py-6">

            @if($expiry && $buildType)
                <div class="w-full bg-slate-800/60 backdrop-blur border border-slate-700 rounded-2xl shadow-xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="sticky top-[57px]">
                            <tr class="bg-slate-900 text-xs uppercase tracking-widest border-b border-slate-700">
                                <th class="px-4 py-4 text-slate-400 text-left whitespace-nowrap">Date</th>
                                <th class="px-4 py-4 text-slate-400 text-left whitespace-nowrap">Time</th>
                                <th class="px-4 py-4 text-slate-400 text-center whitespace-nowrap">Strike</th>

                                {{-- CE --}}
                                <th class="px-3 py-4 text-sky-400 text-right border-l border-slate-700 whitespace-nowrap">CE LTP</th>
                                <th class="px-3 py-4 text-sky-400 text-right whitespace-nowrap">CE Diff OI</th>
                                <th class="px-3 py-4 text-sky-400 text-right whitespace-nowrap">CE Diff Vol</th>
                                <th class="px-3 py-4 text-sky-400 text-right whitespace-nowrap">CE OI</th>
                                <th class="px-3 py-4 text-sky-400 text-center whitespace-nowrap">CE Build</th>

                                {{-- PE --}}
                                <th class="px-3 py-4 text-rose-400 text-right border-l border-slate-700 whitespace-nowrap">PE LTP</th>
                                <th class="px-3 py-4 text-rose-400 text-right whitespace-nowrap">PE Diff OI</th>
                                <th class="px-3 py-4 text-rose-400 text-right whitespace-nowrap">PE Diff Vol</th>
                                <th class="px-3 py-4 text-rose-400 text-right whitespace-nowrap">PE OI</th>
                                <th class="px-3 py-4 text-rose-400 text-center whitespace-nowrap">PE Build</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/40">
                            @forelse($results as $row)
                                @php
                                    $ce = $row['ce'];
                                    $pe = $row['pe'];
                                    $ts = $row['timestamp'] ? \Carbon\Carbon::parse($row['timestamp']) : null;
                                @endphp
                                <tr class="hover:bg-slate-700/30 transition-colors">
                                    <td class="px-4 py-3 text-slate-400 text-xs whitespace-nowrap">
                                        {{ $ts ? $ts->format('d M y') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-300 text-xs font-mono whitespace-nowrap">
                                        {{ $ts ? $ts->format('H:i') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="font-bold text-white text-base">{{ number_format($row['strike']) }}</span>
                                    </td>

                                    {{-- CE columns --}}
                                    <td class="px-3 py-3 text-right border-l border-slate-700/30 text-slate-200 font-mono whitespace-nowrap">
                                        {{ $ce ? number_format($ce->close, 2) : '—' }}
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono whitespace-nowrap">
                                        @if($ce)
                                            <span class="{{ $ce->diff_oi >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                            {{ $ce->diff_oi >= 0 ? '+' : '' }}{{ number_format($ce->diff_oi) }}
                                        </span>
                                        @else <span class="text-slate-600">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono whitespace-nowrap">
                                        @if($ce)
                                            <span class="{{ $ce->diff_volume >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                            {{ $ce->diff_volume >= 0 ? '+' : '' }}{{ number_format($ce->diff_volume) }}
                                        </span>
                                        @else <span class="text-slate-600">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-right text-slate-300 font-mono text-xs whitespace-nowrap">
                                        {{ $ce ? number_format($ce->open_interest) : '—' }}
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        @if($ce)
                                            @include('buildup._badge', ['build' => $ce->build_up])
                                        @else <span class="text-slate-600">—</span>
                                        @endif
                                    </td>

                                    {{-- PE columns --}}
                                    <td class="px-3 py-3 text-right border-l border-slate-700/30 text-slate-200 font-mono whitespace-nowrap">
                                        {{ $pe ? number_format($pe->close, 2) : '—' }}
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono whitespace-nowrap">
                                        @if($pe)
                                            <span class="{{ $pe->diff_oi >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                            {{ $pe->diff_oi >= 0 ? '+' : '' }}{{ number_format($pe->diff_oi) }}
                                        </span>
                                        @else <span class="text-slate-600">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono whitespace-nowrap">
                                        @if($pe)
                                            <span class="{{ $pe->diff_volume >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                            {{ $pe->diff_volume >= 0 ? '+' : '' }}{{ number_format($pe->diff_volume) }}
                                        </span>
                                        @else <span class="text-slate-600">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-right text-slate-300 font-mono text-xs whitespace-nowrap">
                                        {{ $pe ? number_format($pe->open_interest) : '—' }}
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        @if($pe)
                                            @include('buildup._badge', ['build' => $pe->build_up])
                                        @else <span class="text-slate-600">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="px-6 py-16 text-center">
                                        <div class="flex flex-col items-center gap-3 text-slate-500">
                                            <svg class="w-12 h-12 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                    d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <p class="text-lg font-medium">No matching strikes found</p>
                                            <p class="text-sm">Try a different expiry or build-up type combination.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            @else
                <div class="flex flex-col items-center justify-center py-32 text-slate-500">
                    <svg class="w-16 h-16 mb-4 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                    </svg>
                    <p class="text-xl font-semibold">Select filters to generate report</p>
                    <p class="text-sm mt-1">Choose an expiry date and build-up type above</p>
                </div>
            @endif

        </div>
    </div>

    <script>
        function autoSubmit() {
            const expiry = document.getElementById('expiry').value;
            const type   = document.getElementById('buildUpType').value;
            if (expiry && type) {
                document.getElementById('filterForm').submit();
            }
        }
    </script>
@endsection
