{{-- resources/views/test/trading-simulator-report-detail.blade.php --}}
@extends('layouts.app')
@section('title', 'Trade Detail — ' . $position->strike . ' ' . $position->instrument_type)

@push('styles')
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
    <style>
        .ql-toolbar.ql-snow  { background:#1f2937; border-color:#374151!important; border-radius:8px 8px 0 0; }
        .ql-container.ql-snow{ background:#111827; border-color:#374151!important; border-radius:0 0 8px 8px; color:#e5e7eb; }
        .ql-snow .ql-stroke  { stroke:#9ca3af; }
        .ql-snow .ql-fill    { fill:#9ca3af; }
        .ql-snow .ql-picker   { color:#9ca3af; }
        .ql-snow .ql-picker-options { background:#1f2937; }
    </style>
@endpush

@section('content')
    <div class="min-h-screen bg-gray-950 py-5">
        <div class="max-w-4xl mx-auto px-4">

            {{-- ── HEADER ── --}}
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-3">
                    <a href="{{ route('test.trading-simulator.report') }}"
                        class="w-9 h-9 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-xl flex items-center
                  justify-center flex-shrink-0 transition-colors">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-base font-bold text-white leading-tight">
                            {{ $position->strike }}
                            <span class="text-sm font-normal {{ $position->instrument_type === 'CE' ? 'text-blue-400' : 'text-orange-400' }}">
              {{ $position->instrument_type }}
            </span>
                            &nbsp;·&nbsp;
                            <span class="{{ $position->side === 'BUY' ? 'text-emerald-400' : 'text-red-400' }} text-sm font-normal">
              {{ $position->side }}
            </span>
                        </h1>
                        <p class="text-xs text-gray-500">
                            {{ \Carbon\Carbon::parse($position->trade_date)->format('d M Y') }}
                            &nbsp;·&nbsp; Expiry {{ \Carbon\Carbon::parse($position->expiry)->format('d M Y') }}
                            &nbsp;·&nbsp; Session <span class="font-mono text-gray-600">{{ substr($position->session_id, 0, 8) }}…</span>
                        </p>
                    </div>
                </div>

                {{-- Status Badge --}}
                @if($position->status === 'closed')
                    <span class="text-xs font-bold px-3 py-1.5 rounded-xl border bg-gray-800 text-gray-400 border-gray-700">
          Closed
        </span>
                @else
                    <span class="text-xs font-bold px-3 py-1.5 rounded-xl border bg-emerald-900/40 text-emerald-400 border-emerald-800/60 animate-pulse">
          Open
        </span>
                @endif
            </div>

            {{-- ── POSITION SUMMARY CARDS ── --}}
            @php
                $pnl      = $position->realized_pnl;
                $pnlClass = $pnl >= 0 ? 'text-emerald-400' : 'text-red-400';
                $note     = $position->notes->first();
            @endphp
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
                <div class="bg-gray-900 border border-gray-800 rounded-2xl px-4 py-3">
                    <p class="text-xs text-gray-500 mb-1">Avg Entry</p>
                    <p class="text-xl font-bold font-mono text-white">{{ number_format($position->avg_entry, 2) }}</p>
                </div>
                <div class="bg-gray-900 border border-gray-800 rounded-2xl px-4 py-3">
                    <p class="text-xs text-gray-500 mb-1">Total Qty</p>
                    <p class="text-xl font-bold font-mono text-white">
                        {{ intdiv($position->total_qty, 75) }} lots
                        <span class="text-sm text-gray-500">({{ $position->total_qty }})</span>
                    </p>
                </div>
                <div class="bg-gray-900 border border-gray-800 rounded-2xl px-4 py-3">
                    <p class="text-xs text-gray-500 mb-1">Realized P&amp;L</p>
                    <p class="text-xl font-bold font-mono {{ $pnlClass }}">
                        {{ $pnl >= 0 ? '+' : '' }}{{ number_format($pnl, 2) }}
                    </p>
                </div>
                <div class="bg-gray-900 border border-gray-800 rounded-2xl px-4 py-3">
                    <p class="text-xs text-gray-500 mb-1">Outcome</p>
                    @php
                        $outcomeBadge = match($note?->outcome) {
                            'profit'    => 'text-emerald-400',
                            'stoploss'  => 'text-red-400',
                            'breakeven' => 'text-yellow-400',
                            default     => 'text-gray-600',
                        };
                        $outcomeLabel = match($note?->outcome) {
                            'profit'    => '✓ Profit',
                            'stoploss'  => '✗ Stoploss',
                            'breakeven' => '~ Breakeven',
                            default     => 'No note',
                        };
                    @endphp
                    <p class="text-xl font-bold {{ $outcomeBadge }}">{{ $outcomeLabel }}</p>
                </div>
            </div>

            {{-- ── ORDERS LOG ── --}}
            <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden mb-5">
                <div class="px-4 py-3 border-b border-gray-800">
                    <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                        Order Executions
                        <span class="bg-gray-700 text-gray-300 text-xs px-2 py-0.5 rounded-full font-mono">
            {{ $position->orders->count() }}
          </span>
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                        <tr class="text-xs text-gray-500 border-b border-gray-800 bg-gray-900/60">
                            <th class="px-4 py-2.5 text-left font-medium">#</th>
                            <th class="px-4 py-2.5 text-left font-medium">Type</th>
                            <th class="px-4 py-2.5 text-left font-medium">Side</th>
                            <th class="px-4 py-2.5 text-right font-medium">Price</th>
                            <th class="px-4 py-2.5 text-right font-medium">Lots / Qty</th>
                            <th class="px-4 py-2.5 text-right font-medium">P&amp;L</th>
                            <th class="px-4 py-2.5 text-right font-medium">Executed At</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($position->orders as $i => $order)
                            @php
                                $orderPnlClass  = $order->pnl >= 0 ? 'text-emerald-400' : 'text-red-400';
                                $orderTypeBadge = match($order->order_type) {
                                    'entry'        => 'bg-blue-900/60 text-blue-300 border-blue-800',
                                    'partial_exit' => 'bg-orange-900/60 text-orange-300 border-orange-800',
                                    'full_exit'    => 'bg-red-900/60 text-red-300 border-red-800',
                                    default        => 'bg-gray-800 text-gray-400 border-gray-700',
                                };
                                $orderSideBadge = $order->side === 'BUY'
                                    ? 'bg-emerald-900/60 text-emerald-300 border-emerald-800'
                                    : 'bg-red-900/60 text-red-300 border-red-800';
                            @endphp
                            <tr class="border-b border-gray-800/60 hover:bg-gray-800/30 transition-colors">
                                <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $i + 1 }}</td>
                                <td class="px-4 py-3">
                  <span class="text-xs font-bold px-2 py-0.5 rounded-md border {{ $orderTypeBadge }}">
                    {{ str_replace('_', ' ', strtoupper($order->order_type)) }}
                  </span>
                                </td>
                                <td class="px-4 py-3">
                  <span class="text-xs font-bold px-2 py-0.5 rounded-md border {{ $orderSideBadge }}">
                    {{ $order->side }}
                  </span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-gray-300">{{ number_format($order->price, 2) }}</td>
                                <td class="px-4 py-3 text-right text-gray-300">
                                    <span class="font-mono">{{ $order->lots }}</span>
                                    <span class="text-gray-600 text-xs ml-1">({{ $order->qty }} qty)</span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold {{ $orderPnlClass }}">
                                    {{ $order->pnl != 0 ? ($order->pnl >= 0 ? '+' : '') . number_format($order->pnl, 2) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-xs text-gray-500">
                                    {{ \Carbon\Carbon::parse($order->executed_at)->format('H:i:s') }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ── TRADE NOTES ── --}}
            <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden mb-5">
                <div class="px-4 py-3 border-b border-gray-800 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                        Trade Notes
                        <span class="bg-gray-700 text-gray-300 text-xs px-2 py-0.5 rounded-full font-mono">
            {{ $position->notes->count() }}
          </span>
                    </h2>
                    {{-- Add Note Button --}}
                    <button id="btn-add-note" onclick="document.getElementById('add-note-form').classList.toggle('hidden')"
                        class="text-xs bg-blue-900/40 hover:bg-blue-900/70 text-blue-400 border border-blue-800/60
                       hover:border-blue-700 rounded-lg px-3 py-1.5 transition-all font-medium">
                        + Add Note
                    </button>
                </div>

                {{-- Existing Notes --}}
                @forelse($position->notes as $note)
                    @php
                        $nb = match($note->outcome) {
                            'profit'    => 'border-l-emerald-500',
                            'stoploss'  => 'border-l-red-500',
                            'breakeven' => 'border-l-yellow-500',
                            default     => 'border-l-gray-700',
                        };
                        $nl = match($note->outcome) {
                            'profit'    => ['bg-emerald-900/60 text-emerald-400 border-emerald-800/60', '✓ Profit'],
                            'stoploss'  => ['bg-red-900/60 text-red-400 border-red-800/60', '✗ Stoploss'],
                            'breakeven' => ['bg-yellow-900/60 text-yellow-400 border-yellow-800/60', '~ Breakeven'],
                            default     => ['bg-gray-800 text-gray-500 border-gray-700', '—'],
                        };
                    @endphp
                    <div class="px-4 py-4 border-b border-gray-800/60 border-l-4 {{ $nb }} ml-0">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-xs font-bold px-2 py-0.5 rounded-md border {{ $nl[0] }}">{{ $nl[1] }}</span>
                            @if($note->exit_price)
                                <span class="text-xs text-gray-600">Exit @ <span class="font-mono text-gray-500">{{ number_format($note->exit_price, 2) }}</span></span>
                            @endif
                            @if($note->exit_qty)
                                <span class="text-xs text-gray-600">· {{ $note->exit_qty }} qty</span>
                            @endif
                            <span class="text-xs text-gray-700 ml-auto">{{ $note->created_at->format('d M Y, H:i') }}</span>
                        </div>
                        {{-- Render Quill HTML safely --}}
                        <div class="prose prose-invert prose-sm max-w-none text-gray-300 leading-relaxed">
                            {!! $note->comment !!}
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-gray-600 text-sm">No notes yet for this trade.</div>
                @endforelse

                {{-- Add Note Form (hidden by default) --}}
                <div id="add-note-form" class="hidden px-4 py-4 border-t border-gray-800 bg-gray-950/40">
                    <form method="POST" action="{{ route('test.trading-simulator.report.note', $position->id) }}" id="note-form">
                        @csrf
                        <input type="hidden" name="comment" id="note-comment-hidden">
                        <div class="space-y-3">
                            <div class="flex gap-3">
                                <div class="flex-1">
                                    <label class="block text-xs text-gray-500 mb-1.5 font-medium uppercase tracking-wide">Outcome</label>
                                    <select name="outcome"
                                        class="w-full bg-gray-800 border border-gray-700 rounded-xl px-3 py-2 text-sm text-white focus:outline-none">
                                        <option value="profit">✓ Profit</option>
                                        <option value="stoploss">✗ Stoploss</option>
                                        <option value="breakeven">~ Breakeven</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1.5 font-medium uppercase tracking-wide">Note</label>
                                <div id="add-note-editor" class="min-h-[100px]"></div>
                            </div>
                            <div class="flex gap-2 justify-end">
                                <button type="button" onclick="document.getElementById('add-note-form').classList.add('hidden')"
                                    class="bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-xl px-4 py-2 text-sm text-gray-300 transition-colors">
                                    Cancel
                                </button>
                                <button type="submit" id="btn-save-note"
                                    class="bg-blue-600 hover:bg-blue-500 text-white rounded-xl px-5 py-2 text-sm font-semibold transition-colors">
                                    Save Note
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
        <script>
            const noteQuill = new Quill('#add-note-editor', {
                theme: 'snow',
                placeholder: 'Add your trade analysis, observations, or lessons learned...',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline'],
                        [{ color: ['#f87171','#34d399','#fbbf24','#60a5fa','#c084fc','#e5e7eb'] }],
                        [{ 'list': 'bullet' }],
                        ['clean']
                    ]
                }
            });

            document.getElementById('note-form').addEventListener('submit', function () {
                document.getElementById('note-comment-hidden').value = noteQuill.root.innerHTML;
            });
        </script>
    @endpush
@endsection
