@extends('layouts.app')

@section('title')
    HLC
@endsection

@section('content')
    {{-- Controls --}}

    <div class="flex items-center gap-2 mb-4">
        <button id="startBtn" class="px-3 py-1 rounded bg-emerald-600 text-white text-sm">Start</button>
        <button id="pauseBtn" class="px-3 py-1 rounded bg-amber-500 text-white text-sm">Pause</button>
        <button id="stopBtn"  class="px-3 py-1 rounded bg-rose-600 text-white text-sm">Stop</button>

        <label class="ml-4 text-xs flex items-center gap-1">
            From:
            <input type="datetime-local" id="startDateTime"
                class="px-2 py-1 rounded   border border-slate-600 text-xs">
        </label>

        <label class="ml-2 text-xs flex items-center gap-1">
            To:
            <input type="datetime-local" id="endDateTime"
                class="px-2 py-1 rounded  border border-slate-600 text-xs">
        </label>

        <span id="statusText" class="ml-3 text-xs">Status: Paused</span>
        <span id="currentTimeText" class="ml-4 text-xs">Time: --</span>
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
                <option value="SELL">Sell</option>
                <option value="BUY">Buy</option>
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
            <input type="number" name="quantity" step="65"
                class="px-2 py-1 rounded border border-slate-700 text-xs" />
        </div>

        <div class="flex items-end">
            <button class="w-full px-3 py-2 rounded bg-indigo-600 text-white text-sm">
                Add Entry
            </button>
        </div>
    </form>


    {{-- Profit table --}}
    <div class="rounded-lg overflow-hidden">
        <table class="min-w-full text-xs">
            <thead class=" ">
            <tr>
                <th class="px-3 py-2 text-left">Script</th>
                <th class="px-3 py-2 text-center">Side</th>
                <th class="px-3 py-2 text-right">Qty</th>
                <th class="px-3 py-2 text-right">Entry</th>
                <th class="px-3 py-2 text-right">LTP</th>
                <th class="px-3 py-2 text-right">P&L</th>
                <th class="px-3 py-2 text-right">Action</th>
            </tr>
            </thead>
            <tbody id="pnlBody">
            @foreach($entries as $e)
                <tr class="border-t border-slate-700">
                    <form method="POST" action="{{ route('entries.update', $e) }}">
                        @csrf
                        @method('PUT')

                        <td class="px-3 py-1">
                            <div class="flex gap-1">
                                <input name="underlying_symbol" value="{{ $e->underlying_symbol }}"
                                    class="w-16 px-1 py-0.5 border border-slate-700 text-[11px]">
                                <input type="date" name="expiry" value="{{ $e->expiry->format('Y-m-d') }}"
                                    class="w-28 px-1 py-0.5 border border-slate-700 text-[11px]">
                                <input type="number" name="strike" value="{{ $e->strike }}"
                                    class="w-20 px-1 py-0.5 border border-slate-700 text-[11px]">
                                <select name="instrument_type"
                                    class="w-14 px-1 py-0.5 border border-slate-700 text-[11px]">
                                    <option value="CE" @selected($e->instrument_type=='CE')>CE</option>
                                    <option value="PE" @selected($e->instrument_type=='PE')>PE</option>
                                </select>
                            </div>
                        </td>

                        <td class="px-3 py-1 text-center">
                            <select name="side"
                                class="px-2 py-0.5 rounded text-[11px] border border-slate-700">
                                <option value="BUY"  @selected($e->side=='BUY')>Buy</option>
                                <option value="SELL" @selected($e->side=='SELL')>Sell</option>
                            </select>
                        </td>

                        <td class="px-3 py-1 text-right">
                            <input type="number" name="quantity" value="{{ $e->quantity }}"
                                class="w-16 text-right px-1 py-0.5 border border-slate-700 text-[11px]">
                        </td>

                        <td class="px-3 py-1 text-right">
                            <input type="number" step="0.05" name="entry_price" value="{{ $e->entry_price }}"
                                class="w-20 text-right px-1 py-0.5 border border-slate-700 text-[11px]">
                        </td>

                        <td class="px-3 py-1 text-right"><span class="ltp-cell">-</span></td>
                        <td class="px-3 py-1 text-right"><span class="pnl-cell">-</span></td>

                        <td class="px-3 py-1 text-right">
                            <input type="date" name="entry_date" value="{{ $e->entry_date->format('Y-m-d') }}"
                                class="w-28 mb-1 px-1 py-0.5 border border-slate-700 text-[11px]">
                            <input type="time" name="entry_time"
                                value="{{ $e->entry_time instanceof \Carbon\Carbon ? $e->entry_time->format('H:i') : $e->entry_time }}"
                                class="w-20 mb-1 px-1 py-0.5 border border-slate-700 text-[11px]">

                            <div class="flex justify-end gap-1 mt-1">
                                {{-- Save updates this row --}}
                                <button type="submit"
                                    class="px-2 py-0.5 bg-indigo-600 text-white rounded text-[11px]">
                                    Save
                                </button>

                                {{-- Delete uses a separate hidden form (see below) --}}
                                <button type="button"
                                    onclick="if(confirm('Delete this entry?')) document.getElementById('delete-entry-{{ $e->id }}').submit();"
                                    class="px-2 py-0.5 bg-rose-600 text-white rounded text-[11px]">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </form>
                </tr>

                {{-- hidden delete form OUTSIDE the update form --}}
                <form id="delete-entry-{{ $e->id }}" method="POST"
                    action="{{ route('entries.destroy', $e) }}">
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach

            </tbody>
            <tfoot>
            <tr class=" font-semibold">
                <td colspan="5" class="px-3 py-2 text-right">Total P&L</td>
                <td id="totalPnl" class="px-3 py-2 text-right">0.00</td>
            </tr>
            </tfoot>
        </table>
    </div>

    {{-- JS polling: calls /pnl-data every 0.5s and updates the table --}}
    <script>
        let running    = false;
        let stepIndex  = 0;
        let timer      = null;
        let seriesData = null;

        const bodyEl          = document.getElementById('pnlBody');
        const totalEl         = document.getElementById('totalPnl');
        const statusText      = document.getElementById('statusText');
        const currentTimeText = document.getElementById('currentTimeText');

        function renderStep() {
            if (!running || !seriesData) return;

            const { series, maxSteps } = seriesData;

            if (stepIndex >= maxSteps) {
                running = false;
                statusText.textContent = 'Status: Finished';
                clearInterval(timer);
                return;
            }

            bodyEl.innerHTML = '';
            let total = 0;
            let stepTime = null;

            series.forEach(row => {
                const p = row.points[stepIndex] || row.points[row.points.length - 1];
                if (p && !stepTime) {
                    stepTime = p.time; // "YYYY-MM-DD HH:MM:SS"
                }

                const ltp = p ? p.ltp : null;
                const pnl = p ? p.pnl : 0;
                total += pnl;

                const tr = document.createElement('tr');
                tr.className = 'border-t border-slate-700';
                const pnlColor = pnl >= 0 ? 'text-green-700' : 'text-red-700';

                tr.innerHTML = `
            <td class="px-3 py-1">${row.script}</td>
            <td class="px-3 py-1 text-center">
                <span class="inline-flex px-2 rounded-full  text-white ${
                    row.side === 'BUY' ? 'bg-green-700' : 'bg-red-700'
                }">${row.side}</span>
            </td>
            <td class="px-3 py-1 text-right">${row.qty}</td>
            <td class="px-3 py-1 text-right">${row.entry.toFixed(2)}</td>
            <td class="px-3 py-1 text-right">${ltp !== null ? ltp.toFixed(2) : '-'}</td>
            <td class="px-3 py-1 text-right ${pnlColor}">${pnl.toFixed(2)}</td>
        `;
                bodyEl.appendChild(tr);
            });

            if (stepTime) {
                currentTimeText.textContent = 'Time: ' + stepTime;
            }

            totalEl.textContent = total.toFixed(2);
            totalEl.className = 'px-3 py-2 text-right ' +
                (total >= 0 ? 'text-green-400' : 'text-red-400');

            stepIndex++;
        }

        async function startReplay() {
            // always reload when Start is clicked (so From/To changes apply)
            const rawFrom = document.getElementById('startDateTime').value;
            const rawTo   = document.getElementById('endDateTime').value;

            statusText.textContent = 'Status: Loading...';

            const url = new URL('{{ route('entries.pnlSeries') }}', window.location.origin);

            if (rawFrom) {
                const formattedFrom = rawFrom.replace('T', ' ') + ':00';
                url.searchParams.set('from', formattedFrom);
            }
            if (rawTo) {
                const formattedTo = rawTo.replace('T', ' ') + ':00';
                url.searchParams.set('to', formattedTo);
            }

            const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
            seriesData = await res.json();

            // reset replay state
            stepIndex = 0;
            running   = true;
            statusText.textContent = 'Status: Running';

            if (timer) clearInterval(timer);
            renderStep();                 // draw first step → updates Time label
            timer = setInterval(renderStep, 300);
        }

        function pauseReplay() {
            running = false;
            statusText.textContent = 'Status: Paused';
        }

        function stopReplay() {
            running = false;
            stepIndex = 0;
            seriesData = null;
            statusText.textContent = 'Status: Stopped';
            currentTimeText.textContent = 'Time: --';
            if (timer) clearInterval(timer);
        }

        document.getElementById('startBtn').addEventListener('click', startReplay);
        document.getElementById('pauseBtn').addEventListener('click', pauseReplay);
        document.getElementById('stopBtn').addEventListener('click', stopReplay);

    </script>

@endsection
