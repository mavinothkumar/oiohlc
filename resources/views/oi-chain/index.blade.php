@extends('layouts.app')

@section('title')
    OI Chain
@endsection

@section('content')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root,[data-theme="light"]{
            --bg:#f4f5f7;--surface:#ffffff;--surface2:#f0f1f3;--border:#e2e4e8;
            --text:#111827;--muted:#6b7280;--faint:#9ca3af;
            --green:#16a34a;--green-bg:#dcfce7;--green-muted:#86efac;
            --red:#dc2626;--red-bg:#fee2e2;--red-muted:#fca5a5;
            --yellow:#ca8a04;--yellow-bg:#fef9c3;--yellow-muted:#fde047;
            --blue:#2563eb;--blue-bg:#dbeafe;--blue-muted:#93c5fd;
            --purple:#7c3aed;--purple-bg:#ede9fe;--purple-muted:#c4b5fd;
            --accent:#0ea5e9;
            --shadow:0 1px 3px rgba(0,0,0,.08),0 4px 12px rgba(0,0,0,.06);
            --shadow-lg:0 4px 24px rgba(0,0,0,.10);
        }
        [data-theme="dark"]{
            --bg:#0f1117;--surface:#1a1d27;--surface2:#23263a;--border:#2e3148;
            --text:#e2e8f0;--muted:#8892a4;--faint:#4a5568;
            --green:#22c55e;--green-bg:#052e16;--green-muted:#166534;
            --red:#ef4444;--red-bg:#2d1010;--red-muted:#7f1d1d;
            --yellow:#eab308;--yellow-bg:#1c1a00;--yellow-muted:#713f12;
            --blue:#60a5fa;--blue-bg:#0d1f3c;--blue-muted:#1e3a5f;
            --purple:#a78bfa;--purple-bg:#1e1040;--purple-muted:#4c1d95;
            --accent:#38bdf8;
            --shadow:0 1px 4px rgba(0,0,0,.4),0 4px 16px rgba(0,0,0,.3);
            --shadow-lg:0 8px 32px rgba(0,0,0,.5);
        }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        html{scroll-behavior:smooth}
        body{
            font-family:'Inter',sans-serif;
            background:var(--bg);color:var(--text);
            font-size:14px;line-height:1.5;
            min-height:100dvh;
        }
        .mono{font-family:'JetBrains Mono',monospace}
        .card{background:var(--surface);border:1px solid var(--border);border-radius:10px;box-shadow:var(--shadow)}
        .card-inner{background:var(--surface2);border:1px solid var(--border);border-radius:8px}
        .badge{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;padding:2px 8px;border-radius:999px;letter-spacing:.03em;text-transform:uppercase}
        .badge-lb{background:var(--green-bg);color:var(--green)}
        .badge-sb{background:var(--red-bg);color:var(--red)}
        .badge-sc{background:var(--blue-bg);color:var(--blue)}
        .badge-lu{background:var(--yellow-bg);color:var(--yellow)}
        .badge-neutral{background:var(--surface2);color:var(--muted)}
        input,select{
            background:var(--surface2);border:1px solid var(--border);
            color:var(--text);border-radius:6px;padding:6px 10px;font-size:13px;
            outline:none;font-family:inherit;
            transition:border-color .15s;
        }
        input:focus,select:focus{border-color:var(--accent)}
        .btn{
            display:inline-flex;align-items:center;gap:6px;padding:7px 16px;
            border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;
            transition:all .15s;border:none;
        }
        .btn-primary{background:var(--accent);color:#fff}
        .btn-primary:hover{filter:brightness(1.1)}
        .btn-ghost{background:var(--surface2);color:var(--text);border:1px solid var(--border)}
        .btn-ghost:hover{background:var(--border)}
        /* OI Bar custom */
        .oi-bar-wrap{display:flex;align-items:center;gap:8px}
        .oi-bar-track{flex:1;height:6px;background:var(--surface2);border-radius:3px;overflow:hidden}
        .oi-bar-fill{height:100%;border-radius:3px;transition:width .6s ease}
        .oi-bar-ce{background:var(--red)}
        .oi-bar-pe{background:var(--green)}
        /* pulse dot */
        @keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
        .pulse{animation:pulse 1.5s infinite}
        /* Heatmap cell */
        .hm-cell{border-radius:4px;transition:all .2s;cursor:default;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:4px 2px;min-height:52px}
        /* Scrollbar */
        ::-webkit-scrollbar{width:5px;height:5px}
        ::-webkit-scrollbar-track{background:var(--surface)}
        ::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}
        /* Massive change pulse */
        @keyframes flashIn{0%{opacity:0;transform:translateX(-8px)}100%{opacity:1;transform:none}}
        .flash-in{animation:flashIn .4s ease both}
        /* Responsive table */
        .tbl{width:100%;border-collapse:collapse}
        .tbl th{font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);padding:6px 10px;border-bottom:1px solid var(--border);text-align:left}
        .tbl td{padding:6px 10px;border-bottom:1px solid var(--border);font-size:12px;vertical-align:middle}
        .tbl tr:last-child td{border-bottom:none}
        .tbl tr:hover td{background:var(--surface2)}
        .chip{display:inline-block;padding:1px 7px;border-radius:4px;font-size:11px;font-weight:600}
        /* Theme toggle */
        #theme-toggle{background:none;border:none;cursor:pointer;color:var(--muted);padding:4px;border-radius:6px}
        #theme-toggle:hover{color:var(--text);background:var(--surface2)}
        /* Bias banner */
        .bias-bullish{background:var(--green-bg);color:var(--green);border:1px solid var(--green-muted)}
        .bias-bearish{background:var(--red-bg);color:var(--red);border:1px solid var(--red-muted)}
        .bias-sideways{background:var(--yellow-bg);color:var(--yellow);border:1px solid var(--yellow-muted)}
        .bias-neutral{background:var(--surface2);color:var(--muted);border:1px solid var(--border)}
        /* section labels */
        .sec-label{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}
    </style>
{{-- ═══════════════════════════════════════════════════ HEADER ════════ --}}
<header style="background:var(--surface);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:50">
    <div style="max-width:1600px;margin:auto;padding:10px 20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        {{-- Logo --}}
        <svg width="28" height="28" viewBox="0 0 28 28" fill="none" aria-label="OI Chain">
            <rect width="28" height="28" rx="6" fill="var(--accent)" opacity=".15"/>
            <path d="M7 21 L14 7 L21 21" stroke="var(--accent)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="14" cy="14" r="2.5" fill="var(--accent)"/>
        </svg>
        <span style="font-weight:700;font-size:15px;letter-spacing:-.01em">OI Chain <span style="color:var(--accent)">Analyser</span></span>

        {{-- Spot price --}}
        @if($spotRow)
            <div style="margin-left:8px;display:flex;align-items:center;gap:6px">
                <span class="sec-label">SPOT</span>
                <span class="mono" style="font-size:15px;font-weight:700;color:var(--accent)">₹{{ number_format($spotRow['underlying_spot_price'],2) }}</span>
                <span style="font-size:11px;color:var(--muted)">@ {{ \Carbon\Carbon::parse($spotRow['captured_at'])->format('H:i') }}</span>
            </div>
        @endif

        <div style="flex:1"></div>

        {{-- Bias Banner --}}
        @php
            $biasClass = match($bias['color']){
              'green' => 'bias-bullish', 'red' => 'bias-bearish',
              'yellow' => 'bias-sideways', default => 'bias-neutral'
            };
            $biasIcon = match($bias['direction']){
              'BULLISH' => '▲', 'BEARISH' => '▼', 'SIDEWAYS' => '↔', default => '–'
            };
        @endphp
        <div class="card {{ $biasClass }}" style="padding:6px 14px;font-weight:700;font-size:13px;display:flex;align-items:center;gap:6px;border-radius:8px">
            <span class="pulse">●</span>
            {{ $biasIcon }} {{ $bias['direction'] }}
            <span style="font-size:11px;font-weight:400;opacity:.7">CE:{{ $bias['ceBuildup'] }} / PE:{{ $bias['peBuildup'] }}</span>
        </div>

        <button id="theme-toggle" aria-label="Toggle theme">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
            </svg>
        </button>
    </div>
</header>

{{-- ═════════════════════════════════════════════ FILTER BAR ══════════ --}}
<div style="background:var(--surface);border-bottom:1px solid var(--border)">
    <div style="max-width:1600px;margin:auto;padding:10px 20px">
        <form method="GET" action="{{ route('oi-chain.index') }}" id="filter-form"
            style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">

            {{-- Date --}}
            <div style="display:flex;flex-direction:column;gap:3px">
                <label style="font-size:10px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--muted)">Date</label>
                <input type="date" name="date" value="{{ $workingDate }}"
                    max="{{ date('Y-m-d') }}" style="width:145px">
            </div>

            {{-- Expiry --}}
            <div style="display:flex;flex-direction:column;gap:3px">
                <label style="font-size:10px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--muted)">Expiry</label>
                <input type="text" name="expiry" value="{{ $expiry }}"
                    placeholder="{{ $currentExpiry['expiry_date'] ?? '' }}" style="width:130px">
            </div>

            {{-- From --}}
            <div style="display:flex;flex-direction:column;gap:3px">
                <label style="font-size:10px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--muted)">From</label>
                <input type="time" name="from_time" value="{{ $fromTime }}" style="width:100px" step="300">
            </div>

            {{-- To --}}
            <div style="display:flex;flex-direction:column;gap:3px">
                <label style="font-size:10px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--muted)">To</label>
                <input type="time" name="to_time" value="{{ $toTime }}" style="width:100px" step="300">
            </div>

            {{-- Underlying hidden --}}
            <input type="hidden" name="underlying" value="{{ request('underlying','NSE_INDEX|Nifty 50') }}">

            <div style="display:flex;align-items:flex-end;gap:8px;padding-top:14px">
                <button type="submit" class="btn btn-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    Analyse
                </button>
                <a href="{{ route('oi-chain.index') }}" class="btn btn-ghost">Reset</a>
            </div>

            {{-- Session shortcuts --}}
            <div style="display:flex;gap:6px;padding-top:14px">
                <button type="button" class="btn btn-ghost" style="font-size:11px;padding:5px 10px" onclick="setSession('09:15','09:30')">9:15–9:30</button>
                <button type="button" class="btn btn-ghost" style="font-size:11px;padding:5px 10px" onclick="setSession('09:15','10:15')">9:15–10:15</button>
                <button type="button" class="btn btn-ghost" style="font-size:11px;padding:5px 10px" onclick="setSession('09:15','15:30')">Full Day</button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════════════════════════════════ MAIN GRID ══════════ --}}
<main style="max-width:1600px;margin:auto;padding:16px 20px;display:grid;gap:16px">

    {{-- ══ ROW 1: Top OI Bar Charts + Build-up Heatmap ══ --}}
    <div style="display:grid;grid-template-columns:1fr 1fr 360px;gap:16px">

        {{-- CE Top OI --}}
        <div class="card" style="padding:16px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                <div>
                    <p class="sec-label">Call OI — Top 15 Strikes</p>
                    <p style="font-size:11px;color:var(--muted);margin-top:2px">Higher CE OI = Resistance</p>
                </div>
                <span class="badge" style="background:var(--red-bg);color:var(--red)">CE</span>
            </div>
            <div style="position:relative;height:320px">
                <canvas id="ceOiChart"></canvas>
            </div>
        </div>

        {{-- PE Top OI --}}
        <div class="card" style="padding:16px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                <div>
                    <p class="sec-label">Put OI — Top 15 Strikes</p>
                    <p style="font-size:11px;color:var(--muted);margin-top:2px">Higher PE OI = Support</p>
                </div>
                <span class="badge" style="background:var(--green-bg);color:var(--green)">PE</span>
            </div>
            <div style="position:relative;height:320px">
                <canvas id="peOiChart"></canvas>
            </div>
        </div>

        {{-- Build-up Summary Panel --}}
        <div class="card" style="padding:16px;display:flex;flex-direction:column;gap:12px">
            <p class="sec-label">Build-up Signals</p>

            @php
                $allCeCounts = ['Long Build'=>0,'Short Build'=>0,'Short Cover'=>0,'Long Unwind'=>0];
                $allPeCounts = ['Long Build'=>0,'Short Build'=>0,'Short Cover'=>0,'Long Unwind'=>0];
                foreach($buildupTimeline as $slot => $types){
                  foreach($allCeCounts as $bt => $_){
                    $allCeCounts[$bt] += $types['CE']['counts'][$bt] ?? 0;
                    $allPeCounts[$bt] += $types['PE']['counts'][$bt] ?? 0;
                  }
                }
            @endphp

            {{-- CE signals --}}
            <div class="card-inner" style="padding:12px">
                <p style="font-size:11px;font-weight:700;margin-bottom:8px;color:var(--red)">CALL (CE)</p>
                @foreach(['Long Build','Short Build','Short Cover','Long Unwind'] as $bt)
                    @php
                        $val = $allCeCounts[$bt];
                        $cls = match($bt){'Long Build'=>'badge-lb','Short Build'=>'badge-sb','Short Cover'=>'badge-sc',default=>'badge-lu'};
                    @endphp
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                        <span class="badge {{ $cls }}">{{ $bt }}</span>
                        <span class="mono" style="font-size:13px;font-weight:700">{{ $val }}</span>
                    </div>
                @endforeach
            </div>

            {{-- PE signals --}}
            <div class="card-inner" style="padding:12px">
                <p style="font-size:11px;font-weight:700;margin-bottom:8px;color:var(--green)">PUT (PE)</p>
                @foreach(['Long Build','Short Build','Short Cover','Long Unwind'] as $bt)
                    @php
                        $val = $allPeCounts[$bt];
                        $cls = match($bt){'Long Build'=>'badge-lb','Short Build'=>'badge-sb','Short Cover'=>'badge-sc',default=>'badge-lu'};
                    @endphp
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                        <span class="badge {{ $cls }}">{{ $bt }}</span>
                        <span class="mono" style="font-size:13px;font-weight:700">{{ $val }}</span>
                    </div>
                @endforeach
            </div>

            {{-- Interpretation --}}
            <div class="card-inner" style="padding:10px">
                <p style="font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin-bottom:6px">Interpretation</p>
                @if($bias['direction'] === 'BULLISH')
                    <p style="font-size:12px;color:var(--green)">▲ PE Long Build dominant — Bulls in control. Expect upside.</p>
                @elseif($bias['direction'] === 'BEARISH')
                    <p style="font-size:12px;color:var(--red)">▼ CE Short Build dominant — Bears in control. Expect downside.</p>
                @elseif($bias['direction'] === 'SIDEWAYS')
                    <p style="font-size:12px;color:var(--yellow)">↔ Both CE & PE Short Build — Market may consolidate sideways.</p>
                @else
                    <p style="font-size:12px;color:var(--muted)">– Mixed signals. No clear direction yet.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- ══ ROW 2: OI Change Timeline + Massive Changes ══ --}}
    <div style="display:grid;grid-template-columns:1fr 380px;gap:16px">

        {{-- Timeline Heatmap + Stacked Bar --}}
        <div class="card" style="padding:16px">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap">
                <div>
                    <p class="sec-label">OI Change Timeline — 5-min Candles</p>
                    <p style="font-size:11px;color:var(--muted);margin-top:2px">Net diff_oi per candle. Green=PE add, Red=CE add.</p>
                </div>
                <div style="display:flex;gap:8px;margin-left:auto">
                    <span class="badge badge-lb">Long Build</span>
                    <span class="badge badge-sb">Short Build</span>
                    <span class="badge badge-sc">Short Cover</span>
                    <span class="badge badge-lu">Long Unwind</span>
                </div>
            </div>

            {{-- Heatmap grid --}}
            @if(count($buildupTimeline))
                <div style="overflow-x:auto;padding-bottom:8px">
                    <div style="min-width:600px">
                        {{-- Slot headers --}}
                        <div style="display:grid;grid-template-columns:60px repeat({{ count($buildupTimeline) }},1fr);gap:4px;margin-bottom:4px">
                            <div></div>
                            @foreach($buildupTimeline as $slot => $_)
                                <div style="text-align:center;font-size:10px;font-weight:600;color:var(--muted)">{{ $slot }}</div>
                            @endforeach
                        </div>

                        @foreach(['CE','PE'] as $ot)
                            <div style="display:grid;grid-template-columns:60px repeat({{ count($buildupTimeline) }},1fr);gap:4px;margin-bottom:4px">
                                <div style="display:flex;align-items:center;font-size:11px;font-weight:700;color:{{ $ot==='CE' ? 'var(--red)' : 'var(--green)' }}">{{ $ot }}</div>
                                @foreach($buildupTimeline as $slot => $types)
                                    @php
                                        $data  = $types[$ot];
                                        $dom   = $data['dominant'];
                                        $total = array_sum($data['counts']);
                                        $dOi   = $data['total_diff_oi'];
                                        $intensity = $total > 0 ? min(1, $total/10) : 0;

                                        $colorMap = [
                                          'Long Build'  => ['bg'=>'#16a34a','text'=>'#fff'],
                                          'Short Build' => ['bg'=>'#dc2626','text'=>'#fff'],
                                          'Short Cover' => ['bg'=>'#2563eb','text'=>'#fff'],
                                          'Long Unwind' => ['bg'=>'#ca8a04','text'=>'#fff'],
                                        ];
                                        $bg   = $total > 0 ? ($colorMap[$dom]['bg'] ?? '#4b5563') : 'var(--surface2)';
                                        $fg   = $total > 0 ? ($colorMap[$dom]['text'] ?? '#fff') : 'var(--muted)';
                                        $alpha = $total > 0 ? max(0.25, min(1, 0.25 + $intensity * 0.75)) : 0.12;
                                    @endphp
                                    <div class="hm-cell" title="{{ $ot }} {{ $slot }}: {{ $dom }} ({{ $total }} strikes)"
                                        style="background:{{ $bg }};opacity:{{ $alpha }};color:{{ $fg }}">
                                        <span style="font-size:9px;font-weight:700;opacity:1">{{ $total > 0 ? substr($dom,0,2) : '–' }}</span>
                                        @if($dOi != 0)
                                            <span style="font-size:8px;margin-top:1px">{{ $dOi > 0 ? '+' : '' }}{{ number_format($dOi/100000,1) }}L</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div style="text-align:center;padding:40px;color:var(--muted)">No data for selected range.</div>
            @endif

            {{-- Stacked Bar — Net diff OI per slot --}}
            <div style="margin-top:16px;position:relative;height:160px">
                <canvas id="oiDiffChart"></canvas>
            </div>
        </div>

        {{-- Massive OI Changes Panel --}}
        <div class="card" style="padding:16px;overflow:hidden">
            <div style="margin-bottom:12px">
                <p class="sec-label">🔥 Massive OI Moves</p>
                <p style="font-size:11px;color:var(--muted);margin-top:2px">Top changes in last 10 min candles</p>
            </div>

            @if(count($massiveChanges))
                <div style="display:flex;flex-direction:column;gap:6px;max-height:560px;overflow-y:auto">
                    @foreach($massiveChanges as $i => $ch)
                        @php
                            $isPos  = $ch['diff_oi'] > 0;
                            $otColor = $ch['option_type']==='CE' ? 'var(--red)' : 'var(--green)';
                            $sign   = $isPos ? '+' : '';
                            $diffL  = number_format($ch['diff_oi']/100000,2);
                            $bClass = match($ch['build_up'] ?? ''){
                              'Long Build'  => 'badge-lb', 'Short Build' => 'badge-sb',
                              'Short Cover' => 'badge-sc', 'Long Unwind' => 'badge-lu',
                              default => 'badge-neutral'
                            };
                        @endphp
                        <div class="card-inner flash-in" style="padding:8px 10px;animation-delay:{{ $i * 40 }}ms">
                            <div style="display:flex;align-items:center;justify-content:space-between">
                                <div style="display:flex;align-items:center;gap:8px">
                                    <span style="font-size:13px;font-weight:700;color:{{ $otColor }}" class="mono">{{ $ch['strike_price'] }}</span>
                                    <span style="font-size:11px;font-weight:700;color:{{ $otColor }}">{{ $ch['option_type'] }}</span>
                                    @if($ch['build_up'])
                                        <span class="badge {{ $bClass }}">{{ $ch['build_up'] }}</span>
                                    @endif
                                </div>
                                <span class="mono" style="font-size:12px;font-weight:700;color:{{ $isPos ? 'var(--green)' : 'var(--red)' }}">
                {{ $sign }}{{ $diffL }}L
              </span>
                            </div>
                            <div style="display:flex;gap:12px;margin-top:4px">
                                <span style="font-size:10px;color:var(--muted)">{{ $ch['slot'] }}</span>
                                <span style="font-size:10px;color:var(--muted)">OI: {{ number_format($ch['oi']/100000,2) }}L</span>
                                @if($ch['ltp'])
                                    <span style="font-size:10px;color:var(--muted)">LTP: {{ $ch['ltp'] }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div style="text-align:center;padding:32px;color:var(--muted)">
                    <p>No significant OI moves in this window.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- ══ ROW 3: Combined OI Bar (CE vs PE mirror) + PCR Timeline ══ --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

        {{-- Mirror Bar: CE vs PE OI at each strike --}}
        <div class="card" style="padding:16px">
            <div style="margin-bottom:12px">
                <p class="sec-label">CE vs PE OI — Mirror View</p>
                <p style="font-size:11px;color:var(--muted);margin-top:2px">Red = CE resistance ← | → Green = PE support. ATM zone highlighted.</p>
            </div>
            <div style="position:relative;height:300px">
                <canvas id="mirrorChart"></canvas>
            </div>
        </div>

        {{-- Stacked build-up per time slot (CE+PE counts) --}}
        <div class="card" style="padding:16px">
            <div style="margin-bottom:12px">
                <p class="sec-label">Build-up Frequency — Timeline</p>
                <p style="font-size:11px;color:var(--muted);margin-top:2px">Count of build-up types per 5-min slot.</p>
            </div>
            <div style="position:relative;height:300px">
                <canvas id="buildupChart"></canvas>
            </div>
        </div>
    </div>

    {{-- ══ ROW 4: Detailed Strike Table ══ --}}
    <div class="card" style="padding:16px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
            <p class="sec-label">Detailed Strike Snapshot — Latest Values</p>
            <div style="display:flex;gap:8px">
                <button onclick="filterTable('all')"  class="btn btn-ghost" id="tf-all"  style="font-size:11px;padding:4px 10px">All</button>
                <button onclick="filterTable('CE')"   class="btn btn-ghost" id="tf-ce"   style="font-size:11px;padding:4px 10px">CE only</button>
                <button onclick="filterTable('PE')"   class="btn btn-ghost" id="tf-pe"   style="font-size:11px;padding:4px 10px">PE only</button>
            </div>
        </div>
        <div style="overflow-x:auto">
            <table class="tbl" id="strikeTable">
                <thead>
                <tr>
                    <th>Strike</th>
                    <th>Type</th>
                    <th>OI</th>
                    <th>Diff OI</th>
                    <th>Build-up</th>
                    <th>LTP</th>
                    <th>IV</th>
                    <th>Delta</th>
                    <th>OI Bar</th>
                </tr>
                </thead>
                <tbody>
                @php
                    $allStrikes = array_merge($topCe, $topPe);
                    usort($allStrikes, fn($a,$b) => $b['oi'] <=> $a['oi']);
                    $maxOi = $allStrikes[0]['oi'] ?? 1;
                @endphp
                @foreach($allStrikes as $row)
                    @php
                        $isCe = $row['option_type']==='CE';
                        $otColor = $isCe ? 'var(--red)' : 'var(--green)';
                        $diffColor = ($row['diff_oi'] ?? 0) > 0 ? 'var(--green)' : (($row['diff_oi'] ?? 0) < 0 ? 'var(--red)' : 'var(--muted)');
                        $bClass = match($row['build_up'] ?? ''){
                          'Long Build'  => 'badge-lb', 'Short Build' => 'badge-sb',
                          'Short Cover' => 'badge-sc', 'Long Unwind' => 'badge-lu',
                          default => 'badge-neutral'
                        };
                        $barPct = $maxOi > 0 ? round($row['oi']/$maxOi*100) : 0;
                        $barCls = $isCe ? 'oi-bar-ce' : 'oi-bar-pe';
                    @endphp
                    <tr data-ot="{{ $row['option_type'] }}">
                        <td><span class="mono" style="font-weight:700;font-size:13px">{{ number_format($row['strike_price'],0) }}</span></td>
                        <td><span style="font-weight:700;color:{{ $otColor }}">{{ $row['option_type'] }}</span></td>
                        <td><span class="mono">{{ number_format($row['oi']/100000,2) }}L</span></td>
                        <td>
                <span class="mono" style="color:{{ $diffColor }}">
                  {{ ($row['diff_oi'] ?? 0) >= 0 ? '+' : '' }}{{ number_format(($row['diff_oi'] ?? 0)/100000,2) }}L
                </span>
                        </td>
                        <td>
                            @if($row['build_up'])
                                <span class="badge {{ $bClass }}">{{ $row['build_up'] }}</span>
                            @else
                                <span style="color:var(--faint)">–</span>
                            @endif
                        </td>
                        <td class="mono">{{ $row['ltp'] ?? '–' }}</td>
                        <td class="mono">{{ $row['iv'] ?? '–' }}</td>
                        <td class="mono">{{ isset($row['delta']) ? number_format($row['delta'],3) : '–' }}</td>
                        <td style="min-width:100px">
                            <div class="oi-bar-wrap">
                                <div class="oi-bar-track">
                                    <div class="oi-bar-fill {{ $barCls }}" style="width:{{ $barPct }}%"></div>
                                </div>
                                <span style="font-size:10px;color:var(--muted);width:30px;text-align:right">{{ $barPct }}%</span>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

</main>

{{-- ═══════════════════════════════════════════════════ SCRIPTS ════════ --}}
<script>
    // ── Theme toggle ────────────────────────────────────────────────────────────
    (function(){
        const toggle = document.getElementById('theme-toggle');
        const html   = document.documentElement;
        let theme = window.matchMedia('(prefers-color-scheme:dark)').matches ? 'dark' : 'light';
        html.setAttribute('data-theme', theme);
        toggle.addEventListener('click', () => {
            theme = theme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', theme);
        });
    })();

    // ── Session shortcuts ────────────────────────────────────────────────────────
    function setSession(from, to){
        document.querySelector('[name=from_time]').value = from;
        document.querySelector('[name=to_time]').value   = to;
        document.getElementById('filter-form').submit();
    }

    // ── Table filter ─────────────────────────────────────────────────────────────
    function filterTable(ot){
        document.querySelectorAll('#strikeTable tbody tr').forEach(tr => {
            tr.style.display = (ot==='all' || tr.dataset.ot===ot) ? '' : 'none';
        });
        document.querySelectorAll('[id^=tf-]').forEach(b => b.style.background='');
        document.getElementById('tf-'+ot).style.background='var(--accent)';
        document.getElementById('tf-'+ot).style.color='#fff';
    }

    // ── Chart.js helpers ─────────────────────────────────────────────────────────
    function getVar(v){ return getComputedStyle(document.documentElement).getPropertyValue(v).trim(); }
    const fontFamily = "'Inter', sans-serif";
    const monoFamily = "'JetBrains Mono', monospace";

    Chart.defaults.font.family  = fontFamily;
    Chart.defaults.color        = getVar('--muted');
    Chart.defaults.borderColor  = getVar('--border');

    // ── PHP → JS data ────────────────────────────────────────────────────────────
    const ceStrikes  = @json(array_column($topCe,'strike_price'));
    const ceOiData   = @json(array_map(fn($r)=>round($r['oi']/100000,2),$topCe));
    const peStrikes  = @json(array_column($topPe,'strike_price'));
    const peOiData   = @json(array_map(fn($r)=>round($r['oi']/100000,2),$topPe));
    const slots      = @json(array_keys($buildupTimeline));

    @php
        $ceNetDiff=[]; $peNetDiff=[];
        $ceLb=[]; $ceSb=[]; $ceSc=[]; $ceLu=[];
        $peLb=[]; $peSb=[]; $peSc=[]; $peLu=[];
        foreach($buildupTimeline as $slot=>$types){
          $ceNetDiff[] = round(($types['CE']['total_diff_oi']??0)/100000,2);
          $peNetDiff[] = round(($types['PE']['total_diff_oi']??0)/100000,2);
          $ceLb[] = $types['CE']['counts']['Long Build']??0;
          $ceSb[] = $types['CE']['counts']['Short Build']??0;
          $ceSc[] = $types['CE']['counts']['Short Cover']??0;
          $ceLu[] = $types['CE']['counts']['Long Unwind']??0;
          $peLb[] = $types['PE']['counts']['Long Build']??0;
          $peSb[] = $types['PE']['counts']['Short Build']??0;
          $peSc[] = $types['PE']['counts']['Short Cover']??0;
          $peLu[] = $types['PE']['counts']['Long Unwind']??0;
        }

        // Mirror chart: merge CE and PE by strike
        $allStrikesMirror = [];
        foreach($topCe as $r) $allStrikesMirror[$r['strike_price']]['CE'] = round($r['oi']/100000,2);
        foreach($topPe as $r) $allStrikesMirror[$r['strike_price']]['PE'] = round($r['oi']/100000,2);
        ksort($allStrikesMirror);
        $mirrorStrikes = array_keys($allStrikesMirror);
        $mirrorCe = array_map(fn($s)=>-($allStrikesMirror[$s]['CE']??0), $mirrorStrikes);
        $mirrorPe = array_map(fn($s)=>$allStrikesMirror[$s]['PE']??0, $mirrorStrikes);
    @endphp

    const ceNetDiff = @json($ceNetDiff);
    const peNetDiff = @json($peNetDiff);
    const ceLb = @json($ceLb); const ceSb = @json($ceSb); const ceSc = @json($ceSc); const ceLu = @json($ceLu);
    const peLb = @json($peLb); const peSb = @json($peSb); const peSc = @json($peSc); const peLu = @json($peLu);
    const mirrorStrikes = @json($mirrorStrikes);
    const mirrorCe = @json($mirrorCe);
    const mirrorPe = @json($mirrorPe);

    // ── 1. CE Top OI horizontal bar ──────────────────────────────────────────────
    new Chart(document.getElementById('ceOiChart'), {
        type: 'bar',
        data: {
            labels: ceStrikes,
            datasets: [{
                label: 'CE OI (Lakh)',
                data: ceOiData,
                backgroundColor: ceOiData.map((v,i) => i===0 ? '#ef4444' : '#ef444466'),
                borderRadius: 4,
                borderSkipped: false,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => ` ${ctx.raw}L OI` } }
            },
            scales: {
                x: { grid: { color: getVar('--border') }, ticks: { font: { family: monoFamily, size:11 } } },
                y: { grid: { display: false }, ticks: { font: { family: monoFamily, size:11 } } }
            }
        }
    });

    // ── 2. PE Top OI horizontal bar ──────────────────────────────────────────────
    new Chart(document.getElementById('peOiChart'), {
        type: 'bar',
        data: {
            labels: peStrikes,
            datasets: [{
                label: 'PE OI (Lakh)',
                data: peOiData,
                backgroundColor: peOiData.map((v,i) => i===0 ? '#22c55e' : '#22c55e66'),
                borderRadius: 4,
                borderSkipped: false,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => ` ${ctx.raw}L OI` } }
            },
            scales: {
                x: { grid: { color: getVar('--border') }, ticks: { font: { family: monoFamily, size:11 } } },
                y: { grid: { display: false }, ticks: { font: { family: monoFamily, size:11 } } }
            }
        }
    });

    // ── 3. OI Diff timeline grouped bar ──────────────────────────────────────────
    new Chart(document.getElementById('oiDiffChart'), {
        type: 'bar',
        data: {
            labels: slots,
            datasets: [
                { label: 'CE Diff OI', data: ceNetDiff, backgroundColor: '#ef444488', borderRadius: 3 },
                { label: 'PE Diff OI', data: peNetDiff, backgroundColor: '#22c55e88', borderRadius: 3 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { labels: { boxWidth: 10, font: { size:11 } } },
                tooltip: { callbacks: { label: ctx => ` ${ctx.dataset.label}: ${ctx.raw}L` } }
            },
            scales: {
                x: { grid: { display:false }, ticks: { font: { size:10 } } },
                y: { grid: { color: getVar('--border') }, ticks: { font: { family: monoFamily, size:10 } } }
            }
        }
    });

    // ── 4. Mirror CE vs PE bar chart ─────────────────────────────────────────────
    new Chart(document.getElementById('mirrorChart'), {
        type: 'bar',
        data: {
            labels: mirrorStrikes,
            datasets: [
                { label: 'CE OI (−)', data: mirrorCe, backgroundColor: '#ef444477', borderRadius: 3 },
                { label: 'PE OI (+)', data: mirrorPe, backgroundColor: '#22c55e77', borderRadius: 3 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { labels: { boxWidth:10, font:{size:11} } },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.dataset.label}: ${Math.abs(ctx.raw)}L`
                    }
                }
            },
            scales: {
                x: { stacked: false, grid: { display:false }, ticks: { font: { family: monoFamily, size:10 }, maxRotation:45 } },
                y: {
                    grid: { color: getVar('--border') },
                    ticks: {
                        font: { family: monoFamily, size:10 },
                        callback: v => Math.abs(v)+'L'
                    }
                }
            }
        }
    });

    // ── 5. Build-up frequency stacked bar ────────────────────────────────────────
    new Chart(document.getElementById('buildupChart'), {
        type: 'bar',
        data: {
            labels: slots,
            datasets: [
                { label:'CE LB', data:ceLb, backgroundColor:'#16a34a', stack:'CE' },
                { label:'CE SB', data:ceSb, backgroundColor:'#dc2626', stack:'CE' },
                { label:'CE SC', data:ceSc, backgroundColor:'#2563eb', stack:'CE' },
                { label:'CE LU', data:ceLu, backgroundColor:'#ca8a04', stack:'CE' },
                { label:'PE LB', data:peLb, backgroundColor:'#4ade80', stack:'PE' },
                { label:'PE SB', data:peSb, backgroundColor:'#f87171', stack:'PE' },
                { label:'PE SC', data:peSc, backgroundColor:'#60a5fa', stack:'PE' },
                { label:'PE LU', data:peLu, backgroundColor:'#fde047', stack:'PE' },
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { labels: { boxWidth:8, font:{size:10} } },
                tooltip: { mode:'index', intersect:false }
            },
            scales: {
                x: { stacked:true, grid:{display:false}, ticks:{font:{size:10}} },
                y: { stacked:true, grid:{color:getVar('--border')}, ticks:{font:{family:monoFamily,size:10}} }
            }
        }
    });
</script>
@endsection
