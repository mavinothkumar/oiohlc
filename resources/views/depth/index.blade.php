@extends('layouts.app')

@section('content')
    <div class="max-w-7xl mx-auto px-4 py-4">
        <div class="mb-4 p-3 border border-gray-300">
            <div class="flex items-center justify-between">
                <div class="font-semibold text-lg">Full Market Depth — {{ $symbol }} (Expiry: {{ $expiry ?? '—' }})</div>
                <div class="flex items-center gap-2">
                    <button id="btnFocusATMCE" class="px-3 py-1 text-white" style="background:#2271b1">Focus ATM CE</button>
                    <button id="btnFocusATMPE" class="px-3 py-1 text-white" style="background:#2271b1">Focus ATM PE</button>
                    <button id="btnFocusFUT"   class="px-3 py-1 text-white" style="background:#2271b1">Focus FUT</button>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="p-2 border border-gray-300">
                    <div class="text-sm">Spotlight: <span id="spot-name" class="font-medium">—</span></div>
                    <div class="mt-1 text-sm">Top Bid: <span id="spot-bid-qty" class="font-mono">—</span> @ <span id="spot-bid-px" class="font-mono">—</span></div>
                    <div class="text-sm">Top Ask: <span id="spot-ask-qty" class="font-mono">—</span> @ <span id="spot-ask-px" class="font-mono">—</span></div>
                </div>
                <div class="p-2 border border-gray-300">
                    <div class="text-sm">LTP: <span id="spot-ltp" class="font-mono">—</span></div>
                    <div class="text-sm">OI: <span id="spot-oi" class="font-mono">—</span></div>
                    <div class="text-sm">Vol: <span id="spot-vol" class="font-mono">—</span></div>
                </div>
                <div class="p-2 border border-gray-300">
                    <div class="text-sm">Time: <span id="spot-time" class="font-mono">—</span></div>
                    <div class="text-xs text-gray-600">200-level via proxy WS; grid via 1000-id snapshots.</div>
                </div>
            </div>
        </div>

        <div class="mb-2 flex flex-wrap items-center gap-2">
            <form method="get" class="flex items-center gap-2">
                <select name="symbol" class="border border-gray-300 px-2 py-1">
                    <option{{ $symbol==='NIFTY'?' selected':'' }}>NIFTY</option>
                    <option{{ $symbol==='BANKNIFTY'?' selected':'' }}>BANKNIFTY</option>
                    <option{{ $symbol==='SENSEX'?' selected':'' }}>SENSEX</option>
                </select>
                <input type="text" name="expiry" value="{{ $expiry }}" class="border border-gray-300 px-2 py-1" placeholder="YYYY-MM-DD (optional)">
                <button class="px-3 py-1 text-white" style="background:#2271b1">Apply</button>
            </form>
            <div class="ml-auto text-xs text-gray-600">Updates ~2.5s (batched /marketfeed/quote, 1000 ids/rps).</div>
        </div>

        <div class="overflow-auto max-h-[70vh] border border-gray-300">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-100 sticky top-0 z-10">
                <tr class="text-left">
                    <th class="px-2 py-2 border-b border-gray-300">Strike</th>
                    <th class="px-2 py-2 border-b border-gray-300">CE Bid</th>
                    <th class="px-2 py-2 border-b border-gray-300">CE Ask</th>
                    <th class="px-2 py-2 border-b border-gray-300">CE LTP</th>
                    <th class="px-2 py-2 border-b border-gray-300">CE OI</th>
                    <th class="px-2 py-2 border-b border-gray-300">PE Bid</th>
                    <th class="px-2 py-2 border-b border-gray-300">PE Ask</th>
                    <th class="px-2 py-2 border-b border-gray-300">PE LTP</th>
                    <th class="px-2 py-2 border-b border-gray-300">PE OI</th>
                </tr>
                </thead>
                <tbody id="grid">
                @foreach($strikes as $strike)
                    @php
                        $ce = $instruments->firstWhere(fn($r)=>$r->strike==$strike && $r->type==='CE');
                        $pe = $instruments->firstWhere(fn($r)=>$r->strike==$strike && $r->type==='PE');
                    @endphp
                    <tr class="{{ $loop->index % 2 ? 'bg-gray-50' : 'bg-white' }}">
                        <td class="px-2 py-1 border-b border-gray-200 font-mono {{ $strike==$atm ? 'font-bold' : '' }}">{{ $strike }}</td>
                        <td class="px-2 py-1 border-b border-gray-200 font-mono" data-k="bid:{{ $ce->security_id ?? '' }}">—</td>
                        <td class="px-2 py-1 border-b border-gray-200 font-mono" data-k="ask:{{ $ce->security_id ?? '' }}">—</td>
                        <td class="px-2 py-1 border-b border-gray-200 font-mono" data-k="ltp:{{ $ce->security_id ?? '' }}">—</td>
                        <td class="px-2 py-1 border-b border-gray-200 font-mono" data-k="oi:{{ $ce->security_id ?? '' }}">—</td>
                        <td class="px-2 py-1 border-b border-gray-200 font-mono" data-k="bid:{{ $pe->security_id ?? '' }}">—</td>
                        <td class="px-2 py-1 border-b border-gray-200 font-mono" data-k="ask:{{ $pe->security_id ?? '' }}">—</td>
                        <td class="px-2 py-1 border-b border-gray-200 font-mono" data-k="ltp:{{ $pe->security_id ?? '' }}">—</td>
                        <td class="px-2 py-1 border-b border-gray-200 font-mono" data-k="oi:{{ $pe->security_id ?? '' }}">—</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const proxyWs   = @json($proxyWs);
        const securityIds = @json($securityIds);

        // ------------ Grid refresh via Market Quote ------------
        async function refreshSnapshots() {
            const chunks = [];
            const n = 1000;
            for (let i=0;i<securityIds.length;i+=n) chunks.push(securityIds.slice(i,i+n));
            for (const ids of chunks) {
                const q = new URLSearchParams();
                ids.forEach((v,i)=>q.append('ids['+i+']', v));
                const res = await fetch(`{{ route('depth.snapshot') }}?` + q.toString());
                const json = await res.json();
                applyQuoteSnapshot(json?.data || []);
            }
        }
        function applyQuoteSnapshot(items) {
            for (const it of items) {
                const id = String(it.SecurityId ?? it.securityId ?? '');
                cell('bid:'+id, fmtPxQty(it.BestBidPrice, it.BestBidQty));
                cell('ask:'+id, fmtPxQty(it.BestAskPrice, it.BestAskQty));
                cell('ltp:'+id, it.LTP ?? '—');
                cell('oi:'+id, it.OI ?? '—');
            }
        }
        function cell(key, val){ const td = document.querySelector(`td[data-k="${key}"]`); if(td) td.textContent = val; }
        function fmtPxQty(px,q){ return px==null ? '—' : `${px} (${q ?? '—'})`; }

        setInterval(refreshSnapshots, 2500);
        refreshSnapshots();

        // ------------ 200-level spotlight via proxy WS ------------
        let wsClient = null;
        function openProxy() {
            if (wsClient && (wsClient.readyState===WebSocket.OPEN || wsClient.readyState===WebSocket.CONNECTING)) return;
            wsClient = new WebSocket(proxyWs);
            wsClient.onmessage = (ev)=>{
                try {
                    const d = JSON.parse(ev.data);
                    if (d.type === 'depth200') renderSpot(d);
                    if (d.type === 'meta') console.log('meta', d);
                    if (d.type === 'error') console.warn('proxy error', d);
                } catch(e) { console.warn('bad frame', e); }
            };
            wsClient.onclose = ()=> setTimeout(openProxy, 1000);
        }
        openProxy();

        async function focus(kind) {
            const r = await fetch(`{{ route('depth.focus') }}?symbol={{ $symbol }}`);
            const meta = await r.json();
            let id = null, name = '';
            if (kind==='ATMCE'){ id = meta.atmCE; name = 'ATM CE'; }
            if (kind==='ATMPE'){ id = meta.atmPE; name = 'ATM PE'; }
            if (kind==='FUT'){   id = meta.fut;   name = 'FUT'; }
            if (!id) { alert('Instrument not found'); return; }
            document.getElementById('spot-name').textContent = name;
            wsClient?.send(JSON.stringify({
                action: 'focus',
                key: `${meta.symbol}:${name}`,
                exchange: meta.exchange,
                securityId: String(id)
            }));
        }
        document.getElementById('btnFocusATMCE').onclick = ()=>focus('ATMCE');
        document.getElementById('btnFocusATMPE').onclick = ()=>focus('ATMPE');
        document.getElementById('btnFocusFUT').onclick   = ()=>focus('FUT');

        function renderSpot(p) {
            document.getElementById('spot-bid-qty').textContent = p.bidQty ?? '—';
            document.getElementById('spot-bid-px').textContent  = p.bidPx ?? '—';
            document.getElementById('spot-ask-qty').textContent = p.askQty ?? '—';
            document.getElementById('spot-ask-px').textContent  = p.askPx ?? '—';
            document.getElementById('spot-ltp').textContent     = p.ltp ?? '—';
            document.getElementById('spot-oi').textContent      = p.oi ?? '—';
            document.getElementById('spot-vol').textContent     = p.vol ?? '—';
            document.getElementById('spot-time').textContent    = p.time ?? '—';
        }
    </script>
@endsection
