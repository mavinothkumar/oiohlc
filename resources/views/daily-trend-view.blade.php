@extends('layouts.app')

@section('title', 'Daily Trend View')

@section('content')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">
                    Daily Trend – {{ $symbol }}
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                    Combined close = (CE Close + PE Close) / 2
                </p>
            </div>

            <form method="GET" class="flex items-center gap-3">
                <label class="text-sm text-gray-600">
                    Symbol
                    <input type="text"
                        name="symbol_name"
                        value="{{ $symbol }}"
                        class="ml-2 w-28 rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                </label>

                <button type="submit"
                    class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1">
                    Apply
                </button>
            </form>
        </div>

        {{-- Tabs --}}
        <div x-data="{ activeTab: 'daily' }" class="mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <button @click="activeTab = 'daily'"
                        :class="activeTab === 'daily' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                        Daily Trend
                    </button>
                    <button @click="activeTab = 'expiry'"
                        :class="activeTab === 'expiry' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                        Expiry Day Analysis
                    </button>
                </nav>
            </div>

            {{-- Tab 1: Daily Trend Table --}}
            <div x-show="activeTab === 'daily'" class="mt-6">
                <div class="bg-white shadow-sm rounded-lg overflow-hidden border border-gray-200">
                    {{-- Add max-height and overflow to THIS div, not the table --}}
                    <div class="overflow-x-auto max-h-[700px] overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50">Expiry</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50">Strike</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50">CE Close</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50">PE Close</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50">Combined Close</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50">Difference</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50">Change %</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                            @php
                                $lastExpiry = null;
                            @endphp

                            @forelse ($rows as $row)
                                {{-- Visual separator when expiry changes --}}
                                @if(!is_null($lastExpiry) && $row->expiry_date !== $lastExpiry)
                                    <tr>
                                        <td colspan="8" class="px-4 py-2 bg-indigo-50 text-xs text-indigo-700 font-medium">
                                            New expiry starts ({{ \Illuminate\Support\Carbon::parse($row->expiry_date)->format('d M Y') }})
                                        </td>
                                    </tr>
                                @endif

                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 text-gray-900 whitespace-nowrap">
                                        {{ \Illuminate\Support\Carbon::parse($row->quote_date)->format('d M Y') }}
                                    </td>
                                    <td class="px-4 py-2 text-gray-700 whitespace-nowrap">
                                        {{ \Illuminate\Support\Carbon::parse($row->expiry_date)->format('d M Y') }}
                                    </td>
                                    <td class="px-4 py-2 text-gray-900 whitespace-nowrap">
                                        {{ number_format($row->strike, 2) }}
                                    </td>
                                    <td class="px-4 py-2 text-gray-900 whitespace-nowrap font-mono">
                                        {{ number_format($row->ce_close, 2) }}
                                    </td>
                                    <td class="px-4 py-2 text-gray-900 whitespace-nowrap font-mono">
                                        {{ number_format($row->pe_close, 2) }}
                                    </td>
                                    <td class="px-4 py-2 text-gray-900 whitespace-nowrap font-mono">
                                        {{ number_format($row->combined_close, 2) }}
                                    </td>
                                    <td class="px-4 py-2 text-center whitespace-nowrap font-mono">
                                        @if(!is_null($row->diff))
                                            <span class="{{ $row->diff >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $row->diff >= 0 ? '+' : '' }}{{ number_format($row->diff, 2) }}
                                    </span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-center whitespace-nowrap font-mono">
                                        @if(!is_null($row->diff_pct))
                                            <span class="{{ $row->diff_pct >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $row->diff_pct >= 0 ? '+' : '' }}{{ number_format($row->diff_pct, 2) }}%
                                    </span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>

                                @php
                                    $lastExpiry = $row->expiry_date;
                                @endphp
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-6 text-center text-sm text-gray-500">
                                        No records found for the selected symbol.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Info note --}}
                <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-sm text-blue-800">
                        <strong>Note:</strong> Difference and Change % compare each day to the previous trading day <strong>within the same expiry</strong>.
                        The first day of each new expiry shows "—" as there's no previous day to compare within that expiry.
                    </p>
                </div>
            </div>


            {{-- Tab 2: Expiry Day Analysis --}}
            <div x-show="activeTab === 'expiry'" class="mt-6">
                <div class="bg-white shadow-sm rounded-lg overflow-hidden border border-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Month</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Day 1 Avg</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Day 2 Avg</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Day 3 Avg</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Day 4 Avg</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Day 5 Avg</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                            @forelse ($expiryAnalysis as $month => $data)
                                <tr class="hover:bg-gray-50 {{ $month === 'Overall' ? 'bg-indigo-50 font-semibold' : '' }}">
                                    <td class="px-4 py-2 text-gray-900 whitespace-nowrap {{ $month === 'Overall' ? 'text-indigo-900' : '' }}">
                                        {{ $month }}
                                    </td>
                                    <td class="px-4 py-2 text-gray-900 text-center whitespace-nowrap font-mono">
                                        {{ $data['day1_avg'] ? number_format($data['day1_avg'], 2) : '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-gray-900 text-center whitespace-nowrap font-mono">
                                        {{ $data['day2_avg'] ? number_format($data['day2_avg'], 2) : '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-gray-900 text-center whitespace-nowrap font-mono">
                                        {{ $data['day3_avg'] ? number_format($data['day3_avg'], 2) : '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-gray-900 text-center whitespace-nowrap font-mono">
                                        {{ $data['day4_avg'] ? number_format($data['day4_avg'], 2) : '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-gray-900 text-center whitespace-nowrap font-mono">
                                        {{ $data['day5_avg'] ? number_format($data['day5_avg'], 2) : '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">
                                        No analysis data available.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Info note below table --}}
                <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-sm text-blue-800">
                        <strong>Note:</strong> Day 1-5 represent the first five trading days after each expiry starts.
                        The "Overall" row shows the average across all months.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
