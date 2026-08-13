@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-8">
            <a href="{{ route('backtests.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                ← Backtest dashboard
            </a>

            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">
                Basket Builder
            </h1>

            <p class="mt-2 text-sm text-slate-600">
                Generate reusable strikes, CE/PE types, and lots for any future expiry.
            </p>
        </div>

        <form method="GET" action="{{ route('backtests.basket-builder') }}" class="mb-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label for="strategy_id" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Strategy
                    </label>

                    <select id="strategy_id" name="strategy_id" required class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select strategy</option>
                        @foreach ($strategies as $item)
                            <option value="{{ $item->id }}" @selected((string) ($filters['strategy_id'] ?? '') === (string) $item->id)>
                                {{ $item->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="atm_strike" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                        ATM strike
                    </label>

                    <input id="atm_strike" name="atm_strike" type="number" min="0" step="1" required value="{{ $filters['atm_strike'] ?? '' }}" placeholder="Example: 24500" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="lot_multiplier" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Lot multiplier
                    </label>

                    <input id="lot_multiplier" name="lot_multiplier" type="number" min="1" step="1" value="{{ $filters['lot_multiplier'] ?? 1 }}" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <div class="mt-5 flex gap-3">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Generate strikes
                </button>

                <a href="{{ route('backtests.basket-builder') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Clear
                </a>
            </div>
        </form>

        @if ($strategy && $strategyLegs->isEmpty())
            <div class="mb-6 rounded-xl bg-amber-50 p-5 text-sm text-amber-800 ring-1 ring-amber-200">
                No strategy legs were found. Check the JSON field used by the strategy editor.
            </div>
        @endif

        @if ($legs->isNotEmpty())
            <div class="space-y-8">
                <!-- SECTION 1: CE & PE Split Tables -->
                <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900">1. CE & PE Breakdown</h2>

                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- CE Table -->
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50/30 p-4">
                            <h3 class="mb-3 text-sm font-bold text-emerald-800">Call Options (CE)</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm divide-y divide-emerald-200">
                                    <thead>
                                    <tr class="text-xs font-semibold uppercase text-emerald-700">
                                        <th class="py-2">Leg</th>
                                        <th class="py-2 text-right">Strike</th>
                                        <th class="py-2 text-right">Lots</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-emerald-100">
                                    @forelse ($ceLegs as $leg)
                                        <tr>
                                            <td class="py-2.5 font-medium text-slate-900">#{{ $leg['leg_number'] }}</td>
                                            <td class="py-2.5 text-right font-semibold text-slate-900">{{ number_format($leg['strike']) }}</td>
                                            <td class="py-2.5 text-right font-bold text-emerald-700">{{ number_format($leg['lots']) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="py-4 text-center text-xs text-slate-500">No CE legs</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- PE Table -->
                        <div class="rounded-lg border border-rose-200 bg-rose-50/30 p-4">
                            <h3 class="mb-3 text-sm font-bold text-rose-800">Put Options (PE)</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm divide-y divide-rose-200">
                                    <thead>
                                    <tr class="text-xs font-semibold uppercase text-rose-700">
                                        <th class="py-2">Leg</th>
                                        <th class="py-2 text-right">Strike</th>
                                        <th class="py-2 text-right">Lots</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-rose-100">
                                    @forelse ($peLegs as $leg)
                                        <tr>
                                            <td class="py-2.5 font-medium text-slate-900">#{{ $leg['leg_number'] }}</td>
                                            <td class="py-2.5 text-right font-semibold text-slate-900">{{ number_format($leg['strike']) }}</td>
                                            <td class="py-2.5 text-right font-bold text-rose-700">{{ number_format($leg['lots']) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="py-4 text-center text-xs text-slate-500">No PE legs</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- SECTION 2: Option Chain View -->
                <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">2. Option Chain View</h2>
                            <p class="text-xs text-slate-500">ATM: {{ number_format((float) $filters['atm_strike']) }}</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-center text-sm divide-y divide-slate-200">
                            <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-600">
                            <tr>
                                <th class="px-4 py-3 text-emerald-700">CE Lots</th>
                                <th class="px-4 py-3 bg-slate-100 text-slate-800">Strike</th>
                                <th class="px-4 py-3 text-rose-700">PE Lots</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($optionChain as $row)
                                @php
                                    $isAtm = (float) $row['strike'] === (float) $filters['atm_strike'];
                                @endphp
                                <tr class="{{ $isAtm ? 'bg-amber-50/60 font-semibold' : 'hover:bg-slate-50' }}">
                                    <td class="px-4 py-3 font-bold text-emerald-700">
                                        {{ $row['ce_lots'] > 0 ? number_format($row['ce_lots']) . ' Lot(s)' : '—' }}
                                    </td>
                                    <td class="px-4 py-3 font-bold bg-slate-50 text-slate-900">
                                        {{ number_format($row['strike']) }}
                                        @if ($isAtm)
                                            <span class="ml-1 rounded bg-amber-200/60 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-700">ATM</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-bold text-rose-700">
                                        {{ $row['pe_lots'] > 0 ? number_format($row['pe_lots']) . ' Lot(s)' : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        @endif
    </div>

    <script>
        function copyBasket() {
            const rows = Array.from(document.querySelectorAll('#basket-table tbody tr'));
            const text = rows.map((row) => {
                const cells = Array.from(row.querySelectorAll('td'));
                return cells.map((cell) => cell.innerText.trim()).join('\t');
            }).join('\n');

            navigator.clipboard.writeText(text).then(() => alert('Basket copied'));
        }
    </script>
@endsection
