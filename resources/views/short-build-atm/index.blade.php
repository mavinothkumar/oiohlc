@extends('layouts.app')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="bg-white shadow-sm rounded-xl border border-slate-200 p-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-800">Short Build Around Open ATM</h1>
                    <p class="text-sm text-slate-500 mt-1">
                        Select expiry date, then use previous working day daily trend ATM open.
                    </p>
                </div>

                <form method="GET" action="{{ route('test.short-build-atm.index') }}" class="flex flex-col sm:flex-row gap-3">
                    <div>
                        <label for="date" class="block text-sm font-medium text-slate-700 mb-1">Expiry Date</label>
                        <input
                            type="date"
                            name="date"
                            id="date"
                            value="{{ $selectedDate }}"
                            class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                        >
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-white text-sm font-medium hover:bg-indigo-700"
                    >
                        Filter
                    </button>
                </form>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
                <div class="rounded-lg border border-slate-200 p-4 bg-slate-50">
                    <p class="text-xs text-slate-500">Selected Expiry</p>
                    <p class="text-lg font-semibold text-slate-800">{{ $selectedDate }}</p>
                </div>

                <div class="rounded-lg border border-slate-200 p-4 bg-slate-50">
                    <p class="text-xs text-slate-500">Previous Working Day</p>
                    <p class="text-lg font-semibold text-slate-800">{{ $previousWorkingDay ?? '-' }}</p>
                </div>

                <div class="rounded-lg border border-slate-200 p-4 bg-slate-50">
                    <p class="text-xs text-slate-500">ATM Index Open</p>
                    <p class="text-lg font-semibold text-slate-800">
                        {{ !is_null($openAtmIndex) ? number_format($openAtmIndex, 2) : '-' }}
                    </p>
                </div>

                <div class="rounded-lg border border-slate-200 p-4 bg-slate-50">
                    <p class="text-xs text-slate-500">Rounded ATM Strike</p>
                    <p class="text-lg font-semibold text-slate-800">{{ $roundedAtmStrike ?? '-' }}</p>
                </div>
            </div>

            <div class="mt-4">
                <p class="text-sm text-slate-600">
                    <span class="font-medium">Tracked strikes:</span>
                    @if(count($strikes))
                        {{ implode(', ', $strikes) }}
                    @else
                        -
                    @endif
                </p>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Captured At</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Strike</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Build Up</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-600 uppercase">Diff OI</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-600 uppercase">Diff Volume</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($rows as $capturedAt => $items)
                        @foreach($items as $index => $row)
                            <tr class="hover:bg-slate-50">
                                @if($index === 0)
                                    <td rowspan="{{ $items->count() }}" class="px-4 py-3 text-sm font-medium text-slate-800 align-top bg-slate-50">
                                        {{ \Carbon\Carbon::parse($capturedAt)->format('d-m-Y H:i:s') }}
                                    </td>
                                @endif

                                <td class="px-4 py-3 text-sm text-slate-700">
                                    {{ number_format($row->strike_price, 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">
                                    {{ $row->option_type }}
                                </td>
                                <td class="px-4 py-3 text-sm text-red-700 font-medium">
                                    {{ $row->build_up }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-slate-700">
                                    {{ format_inr_compact($row->diff_oi ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-slate-700">
                                    {{ format_inr_compact($row->diff_volume ?? 0) }}
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">
                                No timestamps found where all 5 strikes are Short Build.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
@endsection
