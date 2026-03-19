@php
    $styles = [
        'Short Build'  => 'bg-red-500/20 text-red-300 border border-red-500/40',
        'Long Build'   => 'bg-green-500/20 text-green-300 border border-green-500/40',
        'Short Cover'  => 'bg-amber-500/20 text-amber-300 border border-amber-500/40',
        'Long Unwind'  => 'bg-sky-500/20 text-sky-300 border border-sky-500/40',
    ];
    $cls = $styles[$build] ?? 'bg-slate-700 text-slate-300 border border-slate-600';
    $abbr = ['Short Build'=>'SB','Long Build'=>'LB','Short Cover'=>'SC','Long Unwind'=>'LU'];
@endphp
<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $cls }}" title="{{ $build }}">
    {{ $abbr[$build] ?? $build }}
</span>
