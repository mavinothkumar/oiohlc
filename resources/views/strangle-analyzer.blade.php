@extends('layouts.app')

@section('title', 'NIFTY OI & Volume - Multi Strike Analysis')

@section('content')

    <div class="bg-gray-950 text-gray-100 min-h-screen font-sans antialiased">


        <header class="border-b border-gray-800 bg-gray-900/80 backdrop-blur sticky top-0 z-10">
            <div class="max-w-7xl mx-auto px-6 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-indigo-400 text-2xl">📊</span>
                    <div>
                        <h1 class="text-lg font-bold tracking-tight text-white">Strangle / Straddle Analyzer</h1>
                        <p class="text-xs text-gray-400">NIFTY OTM Premium Distribution</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs text-gray-500">
                    <span class="inline-block w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                    Live Quotes
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-6 py-8 space-y-8">

            {{-- ===== FILTER CARD ===== --}}
            <section class="bg-gray-900 border border-gray-800 rounded-2xl p-6 shadow-xl">
                <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-widest mb-5">Filters</h2>

                <form method="GET" action="{{ route('strangle.analyzer') }}" class="space-y-5">

                    {{-- Row 1: Date, Time, Expiry --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="space-y-1">
                            <label class="text-xs text-gray-400 font-medium">Trading Date</label>
                            <input type="date" name="trading_date"
                                value="{{ $tradingDate }}"
                                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs text-gray-400 font-medium">Time</label>
                            <input type="time" name="time"
                                value="{{ $time }}" min="09:15" max="15:30"
                                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs text-gray-400 font-medium">Expiry Date</label>
                            <select name="expiry_date"
                                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white
                                   focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                @foreach($expiries as $exp)
                                    <option value="{{ $exp->expiry_date }}"
                                        {{ $exp->expiry_date === $expiry ? 'selected' : '' }}>
                                        {{ \Carbon\Carbon::parse($exp->expiry_date)->format('d M Y') }}
                                        {{ $exp->is_current ? '★ Current' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Row 2: NIFTY Open (readonly info) + CE / PE Strikes --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">

                        {{-- NIFTY Open Badge --}}
                        <div class="space-y-1">
                            <label class="text-xs text-gray-400 font-medium">NIFTY Day Open</label>
                            <div class="flex items-center gap-3 bg-gray-800/60 border border-indigo-800/50 rounded-lg px-4 py-2">
                                @if($niftyOpen)
                                    <span class="text-2xl font-bold text-indigo-300">{{ number_format($niftyOpen, 2) }}</span>
                                    @if($atmStrike)
                                        <span class="text-xs text-gray-500 mt-0.5">ATM ≈ <strong class="text-gray-300">{{ $atmStrike }}</strong></span>
                                    @endif
                                @else
                                    <span class="text-gray-500 text-sm italic">No data for selected date</span>
                                @endif
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs text-gray-400 font-medium">CE Strike</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-emerald-400">CE</span>
                                <input type="number" name="ce_strike" step="50"
                                    value="{{ $ceStrike ?: '' }}"
                                    placeholder="{{ $atmStrike ?? 'e.g. 23800' }}"
                                    class="w-full bg-gray-800 border border-emerald-800/60 rounded-lg pl-10 pr-3 py-2 text-sm text-white
                                      focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs text-gray-400 font-medium">PE Strike</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-rose-400">PE</span>
                                <input type="number" name="pe_strike" step="50"
                                    value="{{ $peStrike ?: '' }}"
                                    placeholder="{{ $atmStrike ?? 'e.g. 23800' }}"
                                    class="w-full bg-gray-800 border border-rose-800/60 rounded-lg pl-10 pr-3 py-2 text-sm text-white
                                      focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700
                               text-white text-sm font-semibold px-6 py-2.5 rounded-lg transition-colors shadow-lg shadow-indigo-900/40">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-4.35-4.35M11 19A8 8 0 1 1 11 3a8 8 0 0 1 0 16z"/>
                            </svg>
                            Analyze
                        </button>
                    </div>
                </form>
            </section>

            {{-- ===== RESULTS ===== --}}
            @if(count($legs))
                <section class="space-y-4">

                    {{-- Type Badge --}}
                    <div class="flex items-center gap-4">
            <span class="text-sm font-semibold text-gray-300">
                Strategy:
                <span class="{{ $isStraddle ? 'text-yellow-400' : 'text-indigo-400' }} font-bold text-base ml-1">
                    {{ $isStraddle ? '⚡ Straddle' : '🔀 Strangle' }}
                </span>
            </span>
                        <span class="text-xs text-gray-500">
                CE base: <strong class="text-emerald-400">{{ $ceStrike }}</strong> &nbsp;|&nbsp;
                PE base: <strong class="text-rose-400">{{ $peStrike }}</strong>
            </span>
                    </div>

                    {{-- Legend --}}
                    <div class="flex flex-wrap gap-4 text-xs text-gray-500">
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-emerald-700/60 inline-block"></span> CE (Call)</span>
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-rose-700/60 inline-block"></span> PE (Put)</span>
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-indigo-700/60 inline-block"></span> Combined</span>
                    </div>

                    {{-- Table --}}
                    <div class="overflow-x-auto rounded-2xl border border-gray-800 shadow-2xl">
                        <table class="w-full text-sm">
                            <thead>
                            <tr class="bg-gray-800/80 text-xs uppercase tracking-wider text-gray-400 border-b border-gray-700">
                                <th class="px-4 py-3 text-center">#</th>
                                <th class="px-4 py-3 text-center">Distance</th>
                                <th class="px-4 py-3 text-right text-emerald-400">CE Premium</th>
                                <th class="px-4 py-3 text-right text-emerald-400">CE Strike</th>
                                <th class="px-4 py-3 text-center text-gray-500">↔</th>
                                <th class="px-4 py-3 text-left text-rose-400">PE Strike</th>
                                <th class="px-4 py-3 text-left text-rose-400">PE Premium</th>
                                <th class="px-4 py-3 text-center text-indigo-300">Total Premium</th>
                                <th class="px-4 py-3 text-center text-amber-400">Prem. Diff</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800/60">
                            @foreach($legs as $i => $leg)
                                @php
                                    $rowClass = $i % 2 === 0 ? 'bg-gray-900' : 'bg-gray-900/50';
                                    $isTop    = $leg['total'] && $i === 0;
                                    // Visual bar widths
                                    $maxTotal = collect($legs)->max('total') ?: 1;
                                    $ceBarW   = $leg['ce_premium'] ? round(($leg['ce_premium'] / $maxTotal) * 100) : 0;
                                    $peBarW   = $leg['pe_premium'] ? round(($leg['pe_premium'] / $maxTotal) * 100) : 0;
                                @endphp
                                <tr class="{{ $rowClass }} hover:bg-indigo-900/20 transition-colors group">
                                    {{-- Row # --}}
                                    <td class="px-4 py-3 text-center text-gray-600 group-hover:text-gray-400 font-mono text-xs">
                                        {{ $i + 1 }}
                                    </td>

                                    {{-- Distance --}}
                                    <td class="px-4 py-3 text-center">
                            <span class="inline-block bg-gray-800 text-gray-300 text-xs font-mono px-2 py-0.5 rounded-full border border-gray-700">
                                +{{ $leg['distance'] }}
                            </span>
                                    </td>

                                    {{-- CE Premium --}}
                                    <td class="px-4 py-3 text-right">
                                        @if($leg['ce_premium'] !== null)
                                            <div class="flex flex-col items-end gap-0.5">
                                                <span class="font-semibold text-emerald-300">{{ number_format($leg['ce_premium'], 2) }}</span>
                                                <div class="h-1 rounded-full bg-emerald-800/40 w-20 overflow-hidden">
                                                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ $ceBarW }}%"></div>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-gray-600 text-xs italic">—</span>
                                        @endif
                                    </td>

                                    {{-- CE Strike --}}
                                    <td class="px-4 py-3 text-right">
                                        <span class="font-bold text-emerald-400 font-mono text-base">{{ $leg['ce_strike'] }}</span>
                                    </td>

                                    {{-- Separator --}}
                                    <td class="px-2 py-3 text-center text-gray-700 text-xs">│</td>

                                    {{-- PE Strike --}}
                                    <td class="px-4 py-3 text-left">
                                        <span class="font-bold text-rose-400 font-mono text-base">{{ $leg['pe_strike'] }}</span>
                                    </td>

                                    {{-- PE Premium --}}
                                    <td class="px-4 py-3 text-left">
                                        @if($leg['pe_premium'] !== null)
                                            <div class="flex flex-col items-start gap-0.5">
                                                <span class="font-semibold text-rose-300">{{ number_format($leg['pe_premium'], 2) }}</span>
                                                <div class="h-1 rounded-full bg-rose-800/40 w-20 overflow-hidden">
                                                    <div class="h-full rounded-full bg-rose-500" style="width: {{ $peBarW }}%"></div>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-gray-600 text-xs italic">—</span>
                                        @endif
                                    </td>

                                    {{-- Total Premium --}}
                                    <td class="px-4 py-3 text-center">
                                        @if($leg['total'] !== null)
                                            <span class="inline-block bg-indigo-900/60 text-indigo-200 font-bold px-3 py-0.5 rounded-lg border border-indigo-700/40 font-mono">
                                    {{ number_format($leg['total'], 2) }}
                                </span>
                                        @else
                                            <span class="text-gray-600 text-xs italic">—</span>
                                        @endif
                                    </td>

                                    {{-- Premium Diff --}}
                                    <td class="px-4 py-3 text-center">
                                        @if($leg['diff'] !== null)
                                            @php $diffColor = $leg['diff'] <= 5 ? 'text-green-400' : ($leg['diff'] <= 20 ? 'text-yellow-400' : 'text-red-400'); @endphp
                                            <span class="font-mono font-semibold {{ $diffColor }}">
                                    {{ number_format($leg['diff'], 2) }}
                                </span>
                                        @else
                                            <span class="text-gray-600 text-xs italic">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Footer insight --}}
                    <p class="text-xs text-gray-600 text-right pt-1">
                        Premiums fetched from <code class="text-indigo-400">ohlc_quotes</code> at last tick within
                        <span class="text-gray-400">{{ $time }}</span>
                        on
                        <span class="text-gray-400">{{ \Carbon\Carbon::parse($tradingDate)->format('d M Y') }}</span>
                        ,
                        expiry
                        <span class="text-gray-400">{{ \Carbon\Carbon::parse($expiry)->format('d M Y') }}</span>
                        .
                    </p>
                </section>

            @elseif(request()->isMethod('get') && request()->has('ce_strike'))
                <div class="bg-gray-900 border border-gray-800 rounded-2xl p-10 text-center text-gray-500 text-sm">
                    No premium data found for the selected filters. Try adjusting the date, time range, or expiry.
                </div>
            @else
                {{-- Welcome State --}}
                <div class="bg-gray-900 border border-dashed border-gray-800 rounded-2xl p-14 text-center space-y-3">
                    <div class="text-5xl">📈</div>
                    <h3 class="text-lg font-semibold text-gray-300">Select your strikes to begin</h3>
                    <p class="text-sm text-gray-500 max-w-md mx-auto">
                        Fill in the CE and PE strikes above (auto-suggested from NIFTY day open),
                        pick your expiry and time window, then click
                        <strong class="text-white">Analyze</strong>
                        .
                    </p>
                </div>
            @endif

        </main>

    </div>
@endsection
