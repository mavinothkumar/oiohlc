@extends('layouts.app')

@section('title')
    HLC
@endsection

@section('content')
    {{-- Controls --}}
    <div class="flex items-center gap-2 mb-4">
        <button id="startBtn" class="px-3 py-1 rounded bg-emerald-600 text-white text-sm">Start</button>
        <button id="pauseBtn" class="px-3 py-1 rounded bg-amber-500 text-white text-sm">Pause</button>
        <button id="stopBtn" class="px-3 py-1 rounded bg-rose-600 text-white text-sm">Stop</button>
        <span id="statusText" class="ml-3 text-xs text-slate-400">Status: Paused</span>
    </div>

    {{-- Entry form --}}
    <form method="POST" action="{{ route('entries.store') }}"
        class="mb-6 grid grid-cols-2 md:grid-cols-4 gap-3 p-4 rounded-lg">
        @csrf

        <div class="flex flex-col gap-1">
            <span class="text-[11px] ">Symbol</span>
            <input name="underlying_symbol" value="NIFTY"
                class="px-2 py-1 rounded border border-slate-700 text-xs" />
        </div>

        <div class="flex flex-col gap-1">
            <span class="text-[11px] ">Exchange</span>
            <input name="exchange" value="NSE"
                class="px-2 py-1 rounded border border-slate-700 text-xs" />
        </div>

        <div class="flex flex-col gap-1">
            <span class="text-[11px] ">Expiry</span>
            <input type="date" name="expiry"
                class="px-2 py-1 rounded border border-slate-700 text-xs" />
        </div>

        <div class="flex flex-col gap-1">
            <span class="text-[11px] ">Strike</span>
            <input type="number" name="strike"
                class="px-2 py-1 rounded border border-slate-700 text-xs" />
        </div>

        <div class="flex flex-col gap-1">
            <span class="text-[11px] ">Type</span>
            <select name="instrument_type"
                class="px-2 py-1 rounded border border-slate-700 text-xs">
                <option value="CE">CE</option>
                <option value="PE">PE</option>
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <span class="text-[11px] ">Side</span>
            <select name="side"
                class="px-2 py-1 rounded border border-slate-700 text-xs">
                <option value="BUY">Buy</option>
                <option value="SELL">Sell</option>
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <span class="text-[11px] ">Entry Date</span>
            <input type="date" name="entry_date"
                class="px-2 py-1 rounded border border-slate-700 text-xs" />
        </div>

        <div class="flex flex-col gap-1">
            <span class="text-[11px] ">Entry Time</span>
            <input type="time" name="entry_time" step="300"
                class="px-2 py-1 rounded border border-slate-700 text-xs" />
        </div>

        <div class="flex flex-col gap-1">
            <span class="text-[11px] ">Entry Price</span>
            <input type="number" step="0.05" name="entry_price"
                class="px-2 py-1 rounded border border-slate-700 text-xs" />
        </div>

        <div class="flex flex-col gap-1">
            <span class="text-[11px] ">Qty</span>
            <input type="number" name="quantity"
                class="px-2 py-1 rounded border border-slate-700 text-xs" />
        </div>

        <div class="flex items-end">
            <button class="w-full px-3 py-2 rounded bg-indigo-600 text-white text-sm">
                Add Entry
            </button>
        </div>
    </form>


    {{-- Profit table --}}
    <div class="bg-slate-800 rounded-lg overflow-hidden">
        <table class="min-w-full text-xs">
            <thead class="bg-slate-700 ">
            <tr>
                <th class="px-3 py-2 text-left">Script</th>
                <th class="px-3 py-2 text-center">Side</th>
                <th class="px-3 py-2 text-right">Qty</th>
                <th class="px-3 py-2 text-right">Entry</th>
                <th class="px-3 py-2 text-right">LTP</th>
                <th class="px-3 py-2 text-right">P&L</th>
            </tr>
            </thead>
            <tbody id="pnlBody">
            @foreach($entries as $e)
                <tr class="border-t border-slate-700">
                    <td class="px-3 py-1">{{ $e->underlying_symbol }} {{ $e->expiry }} {{ $e->strike }} {{ $e->instrument_type }}</td>
                    <td class="px-3 py-1 text-center">
                        <span class="inline-flex items-center px-2 rounded-full text-[10px]
                            {{ $e->side === 'BUY' ? 'bg-emerald-700' : 'bg-rose-700' }}">
                            {{ $e->side }}
                        </span>
                    </td>
                    <td class="px-3 py-1 text-right">{{ $e->quantity }}</td>
                    <td class="px-3 py-1 text-right">{{ number_format($e->entry_price, 2) }}</td>
                    <td class="px-3 py-1 text-right">-</td>
                    <td class="px-3 py-1 text-right">-</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr class="bg-slate-700 font-semibold">
                <td colspan="5" class="px-3 py-2 text-right">Total P&L</td>
                <td id="totalPnl" class="px-3 py-2 text-right">0.00</td>
            </tr>
            </tfoot>
        </table>
    </div>

    {{-- JS polling: calls /pnl-data every 0.5s and updates the table --}}
    <script>
        let timer = null;
        let running = false;

        const statusText = document.getElementById('statusText');
        const bodyEl = document.getElementById('pnlBody');
        const totalEl = document.getElementById('totalPnl');

        async function fetchPnl() {
            if (!running) return;

            const res = await fetch('{{ route('entries.pnl') }}');
            const data = await res.json();

            bodyEl.innerHTML = '';
            data.rows.forEach(row => {
                const tr = document.createElement('tr');
                tr.className = 'border-t border-slate-700';

                const pnlColor = row.pnl >= 0 ? 'text-emerald-400' : 'text-rose-400';

                tr.innerHTML = `
                    <td class="px-3 py-1">${row.script}</td>
                    <td class="px-3 py-1 text-center">
                        <span class="inline-flex px-2 rounded-full text-[10px] ${
                    row.side === 'BUY' ? 'bg-emerald-700' : 'bg-rose-700'
                }">${row.side}</span>
                    </td>
                    <td class="px-3 py-1 text-right">${row.qty}</td>
                    <td class="px-3 py-1 text-right">${row.entry.toFixed(2)}</td>
                    <td class="px-3 py-1 text-right">${row.ltp ? row.ltp.toFixed(2) : '-'}</td>
                    <td class="px-3 py-1 text-right ${pnlColor}">${row.pnl.toFixed(2)}</td>
                `;
                bodyEl.appendChild(tr);
            });

            totalEl.textContent = data.total.toFixed(2);
            totalEl.className = 'px-3 py-2 text-right ' + (data.total >= 0 ? 'text-emerald-400' : 'text-rose-400');
        }

        function startPolling() {
            if (running) return;
            running = true;
            statusText.textContent = 'Status: Running';
            timer = setInterval(fetchPnl, 500); // 0.5 seconds
        }

        function pausePolling() {
            running = false;
            statusText.textContent = 'Status: Paused';
        }

        function stopPolling() {
            running = false;
            statusText.textContent = 'Status: Stopped';
            if (timer) clearInterval(timer);
        }

        document.getElementById('startBtn').addEventListener('click', startPolling);
        document.getElementById('pauseBtn').addEventListener('click', pausePolling);
        document.getElementById('stopBtn').addEventListener('click', stopPolling);
    </script>
@endsection
