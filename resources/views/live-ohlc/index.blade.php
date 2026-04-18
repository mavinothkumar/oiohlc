@extends('layouts.app')

@section('title', 'Live OHLC · Build-Up')

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- ── Page Header ──────────────────────────────────────────────────── --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 fw-semibold">Live OHLC &amp; Build-Up</h4>
            <small class="text-muted">
                Interval: 5m &nbsp;|&nbsp;
                Last candle:
                @if ($latestTs)
                    <span class="fw-medium text-dark">{{ \Carbon\Carbon::parse($latestTs)->format('d M Y  H:i') }}</span>
                @else
                    <span class="text-secondary">—</span>
                @endif
            </small>
        </div>

        {{-- Auto-refresh every 5 minutes --}}
        <div class="d-flex align-items-center gap-2">
            <span id="refresh-countdown" class="badge bg-secondary">Next refresh in 5:00</span>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="location.reload()">
                ⟳ Refresh
            </button>
        </div>
    </div>

    {{-- ── Build-Up Summary Badges ─────────────────────────────────────── --}}
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach (['Long Build' => 'success', 'Short Cover' => 'info', 'Short Build' => 'danger', 'Long Unwind' => 'warning'] as $label => $color)
            <span class="badge bg-{{ $color }} bg-opacity-10 text-{{ $color }} border border-{{ $color }} px-3 py-2 fs-6">
                {{ $label }}
                <span class="badge bg-{{ $color }} ms-1">{{ $buildUpCounts[$label] ?? 0 }}</span>
            </span>
        @endforeach
    </div>

    {{-- ── Filters ──────────────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('live-ohlc.index') }}" class="row g-2 mb-3">
        <div class="col-auto">
            <select name="symbol" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach ($symbols as $s)
                    <option value="{{ $s }}" @selected($s === $symbol)>{{ $s }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-auto">
            <select name="expiry" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach ($expiries as $e)
                    <option value="{{ $e }}" @selected($e == $expiry)>{{ \Carbon\Carbon::parse($e)->format('d M Y') }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-auto">
            <select name="instrument_type" class="form-select form-select-sm">
                <option value="">All Types</option>
                @foreach (['CE', 'PE', 'FUT'] as $t)
                    <option value="{{ $t }}" @selected($type === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-auto">
            <select name="build_up" class="form-select form-select-sm">
                <option value="">All Build-Up</option>
                @foreach (['Long Build', 'Short Build', 'Short Cover', 'Long Unwind'] as $b)
                    <option value="{{ $b }}" @selected($buildUp === $b)>{{ $b }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-auto">
            <select name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach ([25, 50, 100] as $pp)
                    <option value="{{ $pp }}" @selected($perPage == $pp)>{{ $pp }} / page</option>
                @endforeach
            </select>
        </div>

        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary">Apply</button>
            <a href="{{ route('live-ohlc.index', ['symbol' => $symbol]) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>

    {{-- ── Table ────────────────────────────────────────────────────────── --}}
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 small">
            <thead class="table-dark sticky-top">
                <tr>
                    <th>Instrument Key</th>
                    <th>Symbol</th>
                    <th>Expiry</th>
                    <th class="text-end">Strike</th>
                    <th>Type</th>
                    <th class="text-end">Open</th>
                    <th class="text-end">High</th>
                    <th class="text-end">Low</th>
                    <th class="text-end">Close</th>
                    <th class="text-end">OI</th>
                    <th class="text-end">Volume</th>
                    <th>Exchange</th>
                    <th>Interval</th>
                    <th>Timestamp</th>
                    <th>Build-Up</th>
                    <th class="text-end">Δ OI</th>
                    <th class="text-end">Δ Volume</th>
                    <th class="text-end">Δ LTP</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($snapshots as $row)
                <tr>
                    {{-- Instrument Key (truncated, full on hover) --}}
                    <td>
                        <span class="font-monospace text-muted" title="{{ $row->instrument_key }}" style="max-width:160px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                            {{ $row->instrument_key }}
                        </span>
                    </td>

                    <td class="fw-semibold">{{ $row->underlying_symbol }}</td>

                    <td class="text-nowrap">{{ \Carbon\Carbon::parse($row->expiry_date)->format('d M Y') }}</td>

                    <td class="text-end font-monospace">
                        {{ $row->strike ? number_format($row->strike, 0) : '—' }}
                    </td>

                    {{-- Instrument Type badge --}}
                    <td>
                        @php
                            $typeBadge = match($row->instrument_type) {
                                'CE'  => 'primary',
                                'PE'  => 'danger',
                                'FUT' => 'warning',
                                default => 'secondary',
                            };
                        @endphp
                        <span class="badge bg-{{ $typeBadge }}">{{ $row->instrument_type }}</span>
                    </td>

                    <td class="text-end font-monospace">{{ number_format($row->open, 2) }}</td>
                    <td class="text-end font-monospace text-success">{{ number_format($row->high, 2) }}</td>
                    <td class="text-end font-monospace text-danger">{{ number_format($row->low, 2) }}</td>
                    <td class="text-end font-monospace fw-semibold">{{ number_format($row->close, 2) }}</td>

                    <td class="text-end font-monospace">{{ $row->oi ? number_format($row->oi) : '—' }}</td>
                    <td class="text-end font-monospace">{{ $row->volume ? number_format($row->volume) : '—' }}</td>

                    <td>{{ $row->exchange }}</td>
                    <td><span class="badge bg-light text-dark border">{{ $row->interval }}</span></td>

                    <td class="text-nowrap">{{ \Carbon\Carbon::parse($row->timestamp)->format('H:i') }}</td>

                    {{-- Build-Up badge --}}
                    <td>
                        @if ($row->build_up)
                            @php
                                $buColor = match($row->build_up) {
                                    'Long Build'  => 'success',
                                    'Short Cover' => 'info',
                                    'Short Build' => 'danger',
                                    'Long Unwind' => 'warning',
                                    default       => 'secondary',
                                };
                            @endphp
                            <span class="badge bg-{{ $buColor }}">{{ $row->build_up }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>

                    {{-- Δ OI --}}
                    <td class="text-end font-monospace @if (!is_null($row->diff_oi)) {{ $row->diff_oi > 0 ? 'text-success' : ($row->diff_oi < 0 ? 'text-danger' : '') }} @endif">
                        @if (!is_null($row->diff_oi))
                            {{ $row->diff_oi > 0 ? '+' : '' }}{{ number_format($row->diff_oi) }}
                        @else
                            —
                        @endif
                    </td>

                    {{-- Δ Volume --}}
                    <td class="text-end font-monospace @if (!is_null($row->diff_volume)) {{ $row->diff_volume > 0 ? 'text-success' : ($row->diff_volume < 0 ? 'text-danger' : '') }} @endif">
                        @if (!is_null($row->diff_volume))
                            {{ $row->diff_volume > 0 ? '+' : '' }}{{ number_format($row->diff_volume) }}
                        @else
                            —
                        @endif
                    </td>

                    {{-- Δ LTP --}}
                    <td class="text-end font-monospace @if (!is_null($row->diff_ltp)) {{ $row->diff_ltp > 0 ? 'text-success' : ($row->diff_ltp < 0 ? 'text-danger' : '') }} @endif">
                        @if (!is_null($row->diff_ltp))
                            {{ $row->diff_ltp > 0 ? '+' : '' }}{{ number_format($row->diff_ltp, 2) }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="18" class="text-center text-muted py-5">
                        No data available for the selected filters.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── Pagination ───────────────────────────────────────────────────── --}}
    <div class="d-flex justify-content-between align-items-center mt-3">
        <small class="text-muted">
            Showing {{ $snapshots->firstItem() }}–{{ $snapshots->lastItem() }} of {{ number_format($snapshots->total()) }} rows
        </small>
        {{ $snapshots->links() }}
    </div>
</div>
@endsection

@push('scripts')
<script>
    // ── Countdown to next 5-minute auto-refresh ─────────────────────────────
    (function () {
        const el       = document.getElementById('refresh-countdown');
        const interval = 5 * 60; // seconds
        let   remaining = interval;

        function pad(n) { return String(n).padStart(2, '0'); }

        function tick() {
            const m = Math.floor(remaining / 60);
            const s = remaining % 60;
            el.textContent = `Next refresh in ${m}:${pad(s)}`;
            if (remaining <= 0) { location.reload(); return; }
            remaining--;
            setTimeout(tick, 1000);
        }

        tick();
    })();
</script>
@endpush
