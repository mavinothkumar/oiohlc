@extends('layouts.app')

@section('content')
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
    @php
        $isEdit = (bool) $strategy;
        $initialLegs = old('legs', $definition['legs'] ?? []);
        $initialParameters = array_merge($parameters, old('parameters', []));
    @endphp

    <div
        x-data="strategyBuilder(
        @js($initialLegs),
        @js($offsets)
    )"
        x-init="init()"
        class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8"
    >
        <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <a
                    href="{{ route('backtest.strategies.index') }}"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-700"
                >
                    ← All strategies
                </a>

                <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">
                    {{ $isEdit ? 'Edit strategy' : 'Create strategy' }}
                </h1>
            </div>
        </div>

        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            method="POST"
            action="{{ $isEdit
            ? route('backtest.strategies.update', $strategy->id)
            : route('backtest.strategies.store') }}"
            class="space-y-6"
        >
            @csrf

            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="grid gap-6 lg:grid-cols-3">
                <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 lg:col-span-2">
                    <h2 class="text-lg font-semibold text-slate-900">Strategy details</h2>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="name" class="mb-1 block text-sm font-medium text-slate-700">
                                Name
                            </label>

                            <input
                                id="name"
                                name="name"
                                type="text"
                                value="{{ old('name', $strategy->name ?? '') }}"
                                required
                                class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                        </div>

                        <div>
                            <label for="slug" class="mb-1 block text-sm font-medium text-slate-700">
                                Slug <span class="font-normal text-slate-400">(optional)</span>
                            </label>

                            <input
                                id="slug"
                                name="slug"
                                type="text"
                                value="{{ old('slug', $strategy->slug ?? '') }}"
                                class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                        </div>

                        <div class="flex items-end">
                            <label class="flex items-center gap-3 text-sm font-medium text-slate-700">
                                <input type="hidden" name="is_active" value="0">
                                <input
                                    type="checkbox"
                                    name="is_active"
                                    value="1"
                                    @checked(old('is_active', $strategy->is_active ?? false))
                                    class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                >
                                Active strategy
                            </label>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl bg-indigo-50 p-6 ring-1 ring-indigo-100">
                    <h2 class="text-lg font-semibold text-indigo-950">Live summary</h2>

                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-indigo-700">Total legs</dt>
                            <dd class="font-bold text-indigo-950" x-text="legs.length"></dd>
                        </div>

                        <div class="flex justify-between">
                            <dt class="text-indigo-700">BUY legs</dt>
                            <dd class="font-bold text-indigo-950" x-text="legs.filter(leg => leg.side === 'BUY').length"></dd>
                        </div>

                        <div class="flex justify-between">
                            <dt class="text-indigo-700">SELL legs</dt>
                            <dd class="font-bold text-indigo-950" x-text="legs.filter(leg => leg.side === 'SELL').length"></dd>
                        </div>

                        <div class="flex justify-between">
                            <dt class="text-indigo-700">Configured lots</dt>
                            <dd class="font-bold text-indigo-950" x-text="legs.reduce((sum, leg) => sum + Number(leg.lots || 0), 0)"></dd>
                        </div>
                    </dl>
                </section>
            </div>

            <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Strategy legs</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Add as many independent CE and PE legs as required.
                        </p>
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            @click="addPreset('shortatmotm')"
                            class="rounded-lg border border-indigo-200 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50"
                        >
                            Preset: ATM OTM
                        </button>

                        <button
                            type="button"
                            @click="addLeg()"
                            class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700"
                        >
                            + Add leg
                        </button>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    <template x-for="(leg, index) in legs" :key="index">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">
                                        Action
                                    </label>

                                    <select
                                        :name="`legs[${index}][side]`"
                                        x-model="leg.side"
                                        class="w-full rounded-md border-slate-300 text-sm"
                                    >
                                        <option value="SELL">SELL</option>
                                        <option value="BUY">BUY</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">
                                        Option
                                    </label>

                                    <select
                                        :name="`legs[${index}][option_type]`"
                                        x-model="leg.optiontype"
                                        class="w-full rounded-md border-slate-300 text-sm"
                                    >
                                        <option value="CE">CE</option>
                                        <option value="PE">PE</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">
                                        Moneyness
                                    </label>

                                    <select
                                        :name="`legs[${index}][moneyness]`"
                                        x-model="leg.moneyness"
                                        @change="onMoneynessChange(leg)"
                                        class="w-full rounded-md border-slate-300 text-sm"
                                    >
                                        <option value="ATM">ATM</option>
                                        <option value="OTM">OTM</option>
                                        <option value="ITM">ITM</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">
                                        Offset
                                    </label>

                                    <select
                                        x-model="leg.offsetChoice"
                                        @change="onOffsetChoiceChange(leg)"
                                        class="w-full rounded-md border-slate-300 text-sm"
                                    >
                                        <template x-for="offset in offsets" :key="offset">
                                            <option :value="offset" x-text="offset" :selected="leg.offsetChoice === String(offset)"></option>
                                        </template>

                                        <option value="custom" :selected="leg.offsetChoice === 'custom'">Custom</option>
                                    </select>

                                    <!-- Always send strike_offset to the backend -->
                                    <input
                                        type="text" class="hidden"
                                        :name="`legs[${index}][strike_offset]`"
                                        :value="leg.strikeoffset"
                                    />

                                    <!-- Visible input for Custom offsets -->
{{--                                    <input--}}
{{--                                        type="number"--}}
{{--                                        min="0"--}}
{{--                                        step="1"--}}
{{--                                        x-model.number="leg.strikeoffset"--}}
{{--                                        x-show="leg.offsetChoice === 'custom'"--}}
{{--                                        class="mt-2 w-full rounded-md border-slate-300 text-sm"--}}
{{--                                        placeholder="Custom offset"--}}
{{--                                    >--}}

                                    <p
                                        x-show="leg.offsetChoice === 'custom'"
                                        class="mt-1 text-xs text-slate-500"
                                    >
                                        Custom offset:
                                        <span x-text="leg.strikeoffset"></span>
                                    </p>
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">
                                        Lots
                                    </label>

                                    <input
                                        type="number"
                                        min="1"
                                        step="1"
                                        :name="`legs[${index}][lots]`"
                                        x-model.number="leg.lots"
                                        class="w-full rounded-md border-slate-300 text-sm"
                                    >
                                </div>

                                <div class="flex items-end">
                                    <button
                                        type="button"
                                        @click="removeLeg(index)"
                                        class="w-full rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>

                            <div class="mt-3 text-xs text-slate-500">
                                Preview:
                                <span
                                    class="font-semibold text-slate-700"
                                    x-text="preview(leg)"
                                ></span>
                            </div>
                        </div>
                    </template>

                    <div
                        x-show="legs.length === 0"
                        x-cloak
                        class="rounded-lg border border-dashed border-slate-300 px-5 py-10 text-center text-sm text-slate-500"
                    >
                        Add your first strategy leg.
                    </div>
                </div>
            </section>

            <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-lg font-semibold text-slate-900">Execution parameters</h2>

                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="entry_time" class="mb-1 block text-sm font-medium text-slate-700">Entry time</label>
                        <input id="entry_time" type="time" name="parameters[entry_time]" value="{{ $initialParameters['entry_time'] }}" required class="w-full rounded-lg border-slate-300 text-sm">
                    </div>

                    <div>
                        <label for="exit_time" class="mb-1 block text-sm font-medium text-slate-700">Exit time</label>
                        <input id="exit_time" type="time" name="parameters[exit_time]" value="{{ $initialParameters['exit_time'] }}" required class="w-full rounded-lg border-slate-300 text-sm">
                    </div>

                    <div>
                        <label for="default_lots" class="mb-1 block text-sm font-medium text-slate-700">Default lots</label>
                        <input id="default_lots" type="number" min="1" name="parameters[default_lots]" value="{{ $initialParameters['default_lots'] }}" required class="w-full rounded-lg border-slate-300 text-sm">
                    </div>

                    <div>
                        <label for="default_side" class="mb-1 block text-sm font-medium text-slate-700">Default action</label>
                        <select id="default_side" name="parameters[default_side]" required class="w-full rounded-lg border-slate-300 text-sm">
                            <option value="SELL" @selected($initialParameters['default_side'] === 'SELL')>SELL</option>
                            <option value="BUY" @selected($initialParameters['default_side'] === 'BUY')>BUY</option>
                        </select>
                    </div>

                    <div>
                        <label for="snapshot_minutes" class="mb-1 block text-sm font-medium text-slate-700">Snapshot minutes</label>
                        <input id="snapshot_minutes" type="number" min="1" name="parameters[snapshot_minutes]" value="{{ $initialParameters['snapshot_minutes'] }}" required class="w-full rounded-lg border-slate-300 text-sm">
                    </div>

                    <div>
                        <label for="entry_price_field" class="mb-1 block text-sm font-medium text-slate-700">Entry price</label>
                        <select id="entry_price_field" name="parameters[entry_price_field]" required class="w-full rounded-lg border-slate-300 text-sm">
                            @foreach (['open', 'high', 'low', 'close'] as $field)
                                <option value="{{ $field }}" @selected($initialParameters['entry_price_field'] === $field)>{{ ucfirst($field) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="snapshot_price_field" class="mb-1 block text-sm font-medium text-slate-700">Snapshot price</label>
                        <select id="snapshot_price_field" name="parameters[snapshot_price_field]" required class="w-full rounded-lg border-slate-300 text-sm">
                            @foreach (['open', 'high', 'low', 'close'] as $field)
                                <option value="{{ $field }}" @selected($initialParameters['snapshot_price_field'] === $field)>{{ ucfirst($field) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="missing_data_policy" class="mb-1 block text-sm font-medium text-slate-700">Missing data policy</label>
                        <select id="missing_data_policy" name="parameters[missing_data_policy]" required class="w-full rounded-lg border-slate-300 text-sm">
                            <option value="skip_day" @selected($initialParameters['missing_data_policy'] === 'skip_day')>Skip day</option>
                            <option value="fail_day" @selected($initialParameters['missing_data_policy'] === 'fail_day')>Fail day</option>
                        </select>
                    </div>
                </div>
            </section>

            <div class="flex justify-end gap-3">
                <a
                    href="{{ route('backtest.strategies.index') }}"
                    class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700"
                >
                    {{ $isEdit ? 'Save changes' : 'Create strategy' }}
                </button>
            </div>
        </form>
    </div>

    <script>
        function strategyBuilder(initialLegs, initialOffsets) {
            const offsets = (initialOffsets || []).map((value) => String(value));

            const numberValue = (value, fallback = 0) => {
                const number = Number(value);

                return Number.isFinite(number)
                    ? number
                    : fallback;
            };

            const normalizeLeg = (source = {}) => {
                const strikeoffset = numberValue(
                    source.strikeoffset ??
                    source.strike_offset ??
                    0
                );

                const isPreset = offsets.includes(String(strikeoffset));

                // Determine offsetChoice: preserve saved 'custom' or default to 'custom' if not in offsets array
                const offsetChoice = (source.offsetChoice || source.offset_choice)
                    ? String(source.offsetChoice || source.offset_choice)
                    : (isPreset ? String(strikeoffset) : 'custom');

                return {
                    side: String(source.side ?? 'SELL').toUpperCase(),
                    optiontype: String(source.optiontype ?? source.option_type ?? 'CE').toUpperCase(),
                    moneyness: String(source.moneyness ?? 'ATM').toUpperCase(),
                    strikeoffset: strikeoffset,
                    offsetChoice: offsetChoice,
                    lots: Math.max(1, numberValue(source.lots ?? 1, 1)),
                };
            };

            return {
                offsets,

                legs: (initialLegs || []).map((leg) => (
                    normalizeLeg(leg)
                )),

                init() {
                    this.legs = this.legs.map((leg) => (
                        normalizeLeg(leg)
                    ));
                },

                addLeg(overrides = {}) {
                    this.legs.push(normalizeLeg({
                        side: 'SELL',
                        optiontype: 'CE',
                        moneyness: 'ATM',
                        strikeoffset: 0,
                        offsetChoice: '0',
                        lots: 1,
                        ...overrides,
                    }));
                },

                removeLeg(index) {
                    this.legs.splice(index, 1);
                },

                onMoneynessChange(leg) {
                    if (leg.moneyness === 'ATM') {
                        leg.strikeoffset = 0;
                        leg.offsetChoice = offsets.includes('0')
                            ? '0'
                            : 'custom';

                        return;
                    }

                    if (
                        leg.offsetChoice === '0' ||
                        !leg.offsetChoice
                    ) {
                        leg.strikeoffset = offsets.includes('50')
                            ? 50
                            : 0;

                        leg.offsetChoice = offsets.includes('50')
                            ? '50'
                            : 'custom';
                    }
                },

                onOffsetChoiceChange(leg) {
                    if (leg.offsetChoice === 'custom') {
                        leg.strikeoffset = numberValue(
                            leg.strikeoffset,
                            0
                        );

                        return;
                    }

                    leg.strikeoffset = numberValue(
                        leg.offsetChoice,
                        0
                    );
                },

                addPreset(name) {
                    if (name !== 'shortatmotm') {
                        return;
                    }

                    [
                        {
                            side: 'SELL',
                            optiontype: 'CE',
                            moneyness: 'ATM',
                            strikeoffset: 0,
                            lots: 2,
                        },
                        {
                            side: 'SELL',
                            optiontype: 'PE',
                            moneyness: 'ATM',
                            strikeoffset: 0,
                            lots: 2,
                        },
                        {
                            side: 'SELL',
                            optiontype: 'CE',
                            moneyness: 'ITM',
                            strikeoffset: 0,
                            lots: 1,
                        },
                        {
                            side: 'SELL',
                            optiontype: 'PE',
                            moneyness: 'ITM',
                            strikeoffset: 0,
                            lots: 3,
                        },
                        {
                            side: 'SELL',
                            optiontype: 'CE',
                            moneyness: 'OTM',
                            strikeoffset: 0,
                            lots: 3,
                        },
                        {
                            side: 'SELL',
                            optiontype: 'PE',
                            moneyness: 'OTM',
                            strikeoffset: 0,
                            lots: 1,
                        },
                        {
                            side: 'SELL',
                            optiontype: 'CE',
                            moneyness: 'OTM',
                            strikeoffset: 50,
                            lots: 2,
                        },
                        {
                            side: 'SELL',
                            optiontype: 'CE',
                            moneyness: 'OTM',
                            strikeoffset: 100,
                            lots: 1,
                        },
                        {
                            side: 'SELL',
                            optiontype: 'PE',
                            moneyness: 'OTM',
                            strikeoffset: 50,
                            lots: 2,
                        },
                        {
                            side: 'SELL',
                            optiontype: 'PE',
                            moneyness: 'OTM',
                            strikeoffset: 100,
                            lots: 1,
                        },
                    ].forEach((leg) => this.addLeg(leg));
                },

                preview(leg) {
                    const offset = numberValue(
                        leg.strikeoffset,
                        0
                    );

                    const moneyness = leg.moneyness === 'ATM'
                        ? 'ATM'
                        : `${leg.moneyness} ${offset}`;

                    return `${leg.side} ${leg.optiontype} ${moneyness} × ${leg.lots} lot(s)`;
                },
            };
        }
    </script>
@endsection
