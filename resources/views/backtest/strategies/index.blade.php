@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        @if (session('success'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Strategy Builder</h1>
                <p class="mt-2 text-sm text-slate-600">Create and manage configurable CE/PE backtest strategies.</p>
            </div>

            <a href="{{ route('backtest.strategies.create') }}"
                class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                Create strategy
            </a>
        </div>

        <form method="GET" class="mb-6 flex flex-col gap-3 sm:flex-row">
            <input name="search" value="{{ request('search') }}" placeholder="Search name or slug"
                class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:max-w-sm">
            <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
        </form>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Strategy</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Version</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Legs</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse ($strategies as $strategy)
                        @php($definition = json_decode($strategy->definition ?: '{"legs":[]}', true))
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-4">
                                <div class="font-semibold text-slate-900">{{ $strategy->name }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $strategy->slug }}</div>
                            </td>
                            <td class="px-5 py-4 text-sm text-slate-700">v{{ $strategy->version }}</td>
                            <td class="px-5 py-4 text-sm text-slate-700">{{ count($definition['legs'] ?? []) }}</td>
                            <td class="px-5 py-4">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $strategy->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ $strategy->is_active ? 'Active' : 'Inactive' }}
                            </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('backtest.strategies.edit', $strategy->id) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
                                    <form method="POST" action="{{ route('backtest.strategies.clone', $strategy->id) }}">
                                        @csrf
                                        <button class="rounded-md border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Clone</button>
                                    </form>
                                    @if ($strategy->is_active)
                                        <form method="POST" action="{{ route('backtest.strategies.destroy', $strategy->id) }}" onsubmit="return confirm('Deactivate this strategy?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded-md border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Deactivate</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-slate-500">No strategies found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-4">{{ $strategies->links() }}</div>
        </div>
    </div>
@endsection
