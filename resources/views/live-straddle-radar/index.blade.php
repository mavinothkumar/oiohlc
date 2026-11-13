@extends('layouts.app')

@section('title', 'Live Straddle Decay & Expansion Radar | Real-Time Stream & Audio Alert')

@push('styles')
<!-- Load Protobuf JS and Alpine.js explicitly in head -->
<script src="https://cdn.jsdelivr.net/npm/protobufjs@7.2.5/dist/protobuf.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
<style>
    [x-cloak] { display: none !important; }
    
    @keyframes pulse-red-glow {
        0%, 100% {
            box-shadow: inset 0 0 0 2px rgba(239, 68, 68, 0.8), 0 0 15px rgba(239, 68, 68, 0.25);
            background-color: rgba(254, 242, 242, 0.95);
        }
        50% {
            box-shadow: inset 0 0 0 2px rgba(239, 68, 68, 0.3), 0 0 5px rgba(239, 68, 68, 0.1);
            background-color: rgba(254, 226, 226, 0.7);
        }
    }
    
    .alarm-spike-active {
        animation: pulse-red-glow 1.2s infinite ease-in-out;
    }

    .tick-up {
        animation: flash-green 0.6s ease-out;
    }
    .tick-down {
        animation: flash-red 0.6s ease-out;
    }
    @keyframes flash-green {
        0% { background-color: rgba(16, 185, 129, 0.4); }
        100% { background-color: transparent; }
    }
    @keyframes flash-red {
        0% { background-color: rgba(239, 68, 68, 0.4); }
        100% { background-color: transparent; }
    }

    /* Custom scrollbar */
    .custom-scrollbar::-webkit-scrollbar {
        height: 6px;
        width: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f1f5f9;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
    }
</style>
@endpush

@section('content')
<div class="w-full px-2 sm:px-4 pb-16 font-sans antialiased text-slate-800"
     x-data="liveStraddleRadarApp()">

    <!-- ════════════════════════════════════════════════════════════════════════
         0. PERSISTENT FLOATING CONTINUOUS ALARM BANNER (When any strike spikes)
    ════════════════════════════════════════════════════════════════════════ -->
    <div x-show="isAlarmRinging"
         x-cloak
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="-translate-y-full opacity-0"
         x-transition:enter-end="translate-y-0 opacity-100"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="translate-y-0 opacity-100"
         x-transition:leave-end="-translate-y-full opacity-0"
         class="fixed top-0 left-0 right-0 z-50 bg-rose-600 text-white shadow-2xl border-b-2 border-rose-700 px-4 py-3 flex items-center justify-between">
        
        <div class="flex items-center gap-3">
            <span class="relative flex h-4 w-4">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-90"></span>
                <span class="relative inline-flex rounded-full h-4 w-4 bg-white text-rose-600 font-black text-[10px] items-center justify-center">!</span>
            </span>
            <div>
                <div class="flex items-center gap-2">
                    <span class="font-black text-sm uppercase tracking-wider">Continuous Alarm Triggered: Straddle Premium Spike Detected!</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-white/20 border border-white/30" x-text="overpricedStrikesCount + ' Strikes Inflated'"></span>
                </div>
                <p class="text-xs text-rose-100 mt-0.5" x-text="alarmBannerMessage"></p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="button"
                    @click="silenceAlarm()"
                    class="px-4 py-2 rounded-lg bg-white hover:bg-rose-50 text-rose-700 font-extrabold text-xs shadow-md transition flex items-center gap-1.5 cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/></svg>
                <span>SILENCE ALARM (ESC)</span>
            </button>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════════
         1. TOP APP BAR & LIVE STREAM STATUS
    ════════════════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-4 mb-4">
        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
            
            <!-- Left: Title, Live Spot & Alpha Thesis -->
            <div class="flex items-start sm:items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-indigo-600 via-indigo-700 to-rose-600 flex items-center justify-center text-white shadow-md shadow-indigo-100 shrink-0">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-lg font-black text-slate-900 tracking-tight">
                            Live Straddle Decay &amp; Expansion Radar
                        </h1>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-rose-100 text-rose-800 border border-rose-200">
                            Short Straddle Spike Hunter
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Historical straddles show daily decay. Real-time stream monitors ±10 strikes against yesterday's close. Rings continuous audio alarm when premium expands above yesterday.
                    </p>
                </div>
            </div>

            <!-- Right: Live WebSocket Status, Spot Ticker & Controls -->
            <div class="flex flex-wrap items-center gap-2.5 self-stretch lg:self-auto justify-between lg:justify-end">
                
                <!-- Live Spot Index Widget -->
                <div class="px-3 py-1.5 rounded-lg bg-slate-900 text-white flex items-center gap-2 text-xs shadow-xs">
                    <span class="font-bold text-slate-400 font-mono">{{ $symbol }} Spot:</span>
                    <span class="font-mono font-black text-sm text-cyan-300" x-text="Number(spotPrice).toFixed(2)">{{ number_format($spot['price'], 2) }}</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded font-mono font-bold bg-slate-800 text-slate-300">
                        ATM <span x-text="atmStrike">{{ $spot['atm_strike'] }}</span>
                    </span>
                </div>

                <!-- WebSocket Feed Status -->
                <div class="px-3 py-1.5 rounded-lg border flex items-center gap-2 text-xs shadow-2xs transition-colors"
                     :class="wsConnected ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-slate-50 border-slate-200 text-slate-600'">
                    <span class="relative flex h-2.5 w-2.5">
                        <span x-show="wsConnected" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5" :class="wsConnected ? 'bg-emerald-500' : 'bg-amber-400'"></span>
                    </span>
                    <span class="font-semibold font-mono" x-text="wsStatusText">Connecting Feed...</span>
                    <span class="text-[10px] text-slate-400 font-mono" x-text="tickCount > 0 ? (tickCount + ' ticks') : ''"></span>
                </div>

                <!-- Audio Alarm Master Toggle & Settings Button -->
                <button type="button"
                        @click="alarmSettingsModalOpen = true"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 border cursor-pointer"
                        :class="alarmEnabled ? 'bg-indigo-50 border-indigo-300 text-indigo-900 hover:bg-indigo-100' : 'bg-slate-100 border-slate-300 text-slate-500'">
                    <span x-text="alarmEnabled ? '🔔' : '🔕'">🔔</span>
                    <span>Alarm: <strong x-text="alarmEnabled ? 'ACTIVE' : 'MUTED'">ACTIVE</strong></span>
                    <span class="w-1.5 h-1.5 rounded-full" :class="alarmEnabled ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"></span>
                </button>

                <!-- Simulate Live Spike (For Testing Anytime) -->
                <button type="button"
                        @click="simulateTestSpike()"
                        class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 flex items-center gap-1 transition cursor-pointer"
                        title="Simulate an intraday price surge on ATM strike to test the live continuous alarm">
                    <span>⚡ Test Spike</span>
                </button>

            </div>

        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════════
         2. CONTROLS TOOLBAR (Symbol, Expiry, Strike Range)
    ════════════════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-3.5 mb-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
        
        <!-- Left: Symbol & Expiry Dropdowns -->
        <div class="flex flex-wrap items-center gap-3">
            
            <!-- Symbol Selector -->
            <div class="flex items-center gap-1.5 text-xs">
                <span class="font-bold text-slate-500">Symbol:</span>
                <div class="inline-flex rounded-lg p-0.5 bg-slate-100 border border-slate-200">
                    @foreach($symbols as $s)
                        <a href="{{ route('live.straddle.radar', array_merge(request()->query(), ['symbol' => $s])) }}"
                           class="px-2.5 py-1 rounded-md text-xs font-semibold transition {{ $symbol === $s ? 'bg-indigo-600 text-white font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900' }}">
                            {{ $s }}
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Expiry Dropdown -->
            <div class="flex items-center gap-1.5 text-xs">
                <span class="font-bold text-slate-500">Expiry:</span>
                <select onchange="window.location.href = this.value"
                        class="bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1 text-xs font-bold text-slate-800 focus:outline-none focus:ring-1 focus:ring-indigo-500 cursor-pointer">
                    @foreach($expiries as $exp)
                        <option value="{{ route('live.straddle.radar', array_merge(request()->query(), ['expiry' => $exp['expiry_date']])) }}"
                                {{ $selected_expiry === $exp['expiry_date'] ? 'selected' : '' }}>
                            {{ $exp['formatted'] }} ({{ $exp['day_count'] }} days recorded)
                        </option>
                    @endforeach
                </select>
            </div>

        </div>

        <!-- Right: Corridor Strike Range Presets -->
        <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-slate-500">Strike Range:</span>
            <div class="inline-flex rounded-lg p-0.5 bg-slate-100 border border-slate-200">
                @foreach(['5' => '±5 (11)', '10' => '±10 (21)', '15' => '±15 (31)', '20' => '±20 (41)'] as $rKey => $rLbl)
                    <a href="{{ route('live.straddle.radar', array_merge(request()->query(), ['strike_range' => $rKey])) }}"
                       class="px-2.5 py-1 rounded-md text-xs font-semibold transition {{ $corridor['range'] === (string)$rKey ? 'bg-indigo-600 text-white font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900' }}">
                        {{ $rLbl }}
                    </a>
                @endforeach
            </div>
        </div>

    </div>

    <!-- ════════════════════════════════════════════════════════════════════════
         3. RADAR CORRIDOR SUMMARY METRIC CARDS
    ════════════════════════════════════════════════════════════════════════ -->
    @php
        $atmRow = collect($strikes)->firstWhere('is_atm', true) ?? ($strikes[0] ?? null);
        $atmYesterdayStraddle = $atmRow['yesterday_close']['straddle'] ?? null;
        $atmLiveStraddle = $atmRow['live']['straddle'] ?? 0;
        $atmLiveDiff = $atmRow['live']['diff_pts'] ?? 0;
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        
        <!-- Card 1: ATM Strike Straddle Baseline -->
        <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-3.5">
            <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-slate-500">
                <span>ATM Strike Yesterday</span>
                <span class="px-1.5 py-0.2 rounded bg-indigo-100 text-indigo-800 font-mono">{{ $spot['atm_strike'] }}</span>
            </div>
            <div class="flex items-baseline gap-2 mt-1.5">
                <span class="text-xl font-black text-slate-900 font-mono"
                      x-text="'₹' + Number(getAtmStrikeRecord()?.yesterday_close?.straddle || {{ $atmYesterdayStraddle ?? 0 }}).toFixed(2)">
                    ₹{{ number_format($atmYesterdayStraddle ?? 0, 2) }}
                </span>
                <span class="text-xs font-bold font-mono text-slate-500">Yesterday Base</span>
            </div>
            <div class="text-[11px] text-slate-500 mt-1 flex items-center justify-between">
                <span>Live Straddle: ₹<span class="font-bold text-slate-800 font-mono" x-text="Number(getAtmStrikeRecord()?.live?.straddle || {{ $atmLiveStraddle }}).toFixed(2)">{{ number_format($atmLiveStraddle, 2) }}</span></span>
                <span class="font-bold font-mono text-xs"
                      :class="(getAtmStrikeRecord()?.live?.diff_pts || {{ $atmLiveDiff }}) <= 0 ? 'text-emerald-600' : 'text-rose-600'"
                      x-text="((getAtmStrikeRecord()?.live?.diff_pts || {{ $atmLiveDiff }}) <= 0 ? '' : '+') + Number(getAtmStrikeRecord()?.live?.diff_pts || {{ $atmLiveDiff }}).toFixed(1)">
                    {{ $atmLiveDiff > 0 ? '+' : '' }}{{ number_format($atmLiveDiff, 1) }}
                </span>
            </div>
        </div>

        <!-- Card 2: Inflated / Overpriced Strikes Alert -->
        <div class="rounded-xl shadow-xs border p-3.5 transition"
             :class="overpricedStrikesCount > 0 ? 'bg-rose-50 border-rose-300' : 'bg-white border-slate-200'">
            <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider"
                 :class="overpricedStrikesCount > 0 ? 'text-rose-800' : 'text-slate-500'">
                <span>Spiking Above Yesterday</span>
                <span class="w-2 h-2 rounded-full" :class="overpricedStrikesCount > 0 ? 'bg-rose-500 animate-ping' : 'bg-emerald-500'"></span>
            </div>
            <div class="flex items-baseline gap-2 mt-1.5">
                <span class="text-xl font-black font-mono" :class="overpricedStrikesCount > 0 ? 'text-rose-700' : 'text-emerald-700'" x-text="overpricedStrikesCount + ' Strikes'">
                    {{ $stats['overpriced_count'] }} Strikes
                </span>
                <span class="text-xs font-bold font-mono" :class="overpricedStrikesCount > 0 ? 'text-rose-600' : 'text-emerald-600'" x-text="overpricedStrikesCount > 0 ? '🔥 SELL OPPORTUNITY' : 'NORMAL DECAY'">
                    {{ $stats['overpriced_count'] > 0 ? '🔥 SELL OPPORTUNITY' : 'NORMAL DECAY' }}
                </span>
            </div>
            <div class="text-[11px] mt-1" :class="overpricedStrikesCount > 0 ? 'text-rose-600 font-medium' : 'text-slate-500'">
                <span x-text="overpricedStrikesCount > 0 ? 'Premia currently higher than yesterday close' : 'All strikes decaying as expected below yesterday'">
                    {{ $stats['overpriced_count'] > 0 ? 'Premia currently higher than yesterday close' : 'All strikes decaying as expected below yesterday' }}
                </span>
            </div>
        </div>

        <!-- Card 3: Maximum Observed Strike Spike -->
        <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-3.5">
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Max Strike Premium Spike</div>
            <div class="flex items-baseline gap-2 mt-1.5">
                <span class="text-xl font-black text-rose-700 font-mono" x-text="'+' + Number(maxObservedSpikePts).toFixed(1) + ' pts'">
                    +{{ number_format($stats['max_spike'], 1) }} pts
                </span>
                <span class="text-xs font-bold text-slate-400">Peak Expansion</span>
            </div>
            <div class="text-[11px] text-slate-500 mt-1">
                Strike: <span class="font-bold text-slate-800 font-mono" x-text="maxObservedSpikeStrike || 'Corridor Peak'">Corridor Peak</span>
            </div>
        </div>

        <!-- Card 4: Baseline Session Info -->
        <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-3.5">
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Yesterday Baseline Session</div>
            <div class="flex items-baseline gap-2 mt-1.5">
                <span class="text-lg font-black text-slate-900 font-mono">{{ $prev_date_formatted }}</span>
            </div>
            <div class="text-[11px] text-slate-500 mt-1">
                Comparing live streamed LTP vs {{ $prev_date_formatted }} EOD close
            </div>
        </div>

    </div>

    <!-- ════════════════════════════════════════════════════════════════════════
         4. CORE STRADDLE MATRIX TABLE (Historical Days + Live Today + Δ Diff)
    ════════════════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
        
        <!-- Table Subheader -->
        <div class="p-3.5 bg-slate-50/80 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Live Straddle Tracker Corridor ({{ count($strikes) }} Strikes):</span>
                <span class="text-xs text-slate-500 font-medium">Historical Straddle vs Live Streamed Straddle</span>
            </div>
            <div class="text-xs text-slate-500 flex items-center gap-3">
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-emerald-500"></span> <strong>Green:</strong> Live ≤ Yesterday (Decay in progress)</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-rose-500 animate-pulse"></span> <strong>Red Alert:</strong> Live &gt; Yesterday (Spike / Short Straddle Zone)</span>
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-50 text-slate-700 uppercase font-black text-[11px] border-b border-slate-200 tracking-wider">
                    <tr>
                        
                        <!-- 1. Strike & Moneyness -->
                        <th class="py-3 px-3 text-center sticky left-0 bg-slate-100 z-10 border-r border-slate-200 min-w-[130px]">
                            Strike / Moneyness
                        </th>

                        <!-- 2. Historical Available Days Columns (Fixed Historical Data) -->
                        @foreach($day_headers as $dh)
                            <th class="py-3 px-3 text-center border-r border-slate-200 min-w-[130px] {{ $dh['is_yesterday'] ? 'bg-amber-50/80 text-amber-950 font-black border-l-2 border-amber-300' : '' }}">
                                <div class="flex items-center justify-center gap-1">
                                    <span>Day {{ $dh['day_number'] }}</span>
                                    @if($dh['is_yesterday'])
                                        <span class="px-1.5 py-0.2 rounded text-[9px] uppercase font-black bg-amber-200 text-amber-900 border border-amber-300">Yesterday Base</span>
                                    @endif
                                </div>
                                <div class="text-[10px] font-medium text-slate-500 font-sans mt-0.5">{{ $dh['formatted'] }}</div>
                            </th>
                        @endforeach

                        <!-- 3. LIVE TODAY (Real-Time Streamed Columns) -->
                        <th class="py-3 px-3 text-right bg-rose-50/60 border-l-2 border-rose-300 text-rose-900 font-black min-w-[110px]">
                            <div>Live CE LTP</div>
                            <div class="text-[10px] font-medium text-rose-700 font-sans mt-0.5">Today ({{ \Carbon\Carbon::parse($latest_date)->format('d M') }})</div>
                        </th>
                        <th class="py-3 px-3 text-right bg-emerald-50/60 border-r border-slate-200 text-emerald-900 font-black min-w-[110px]">
                            <div>Live PE LTP</div>
                            <div class="text-[10px] font-medium text-emerald-700 font-sans mt-0.5">Today ({{ \Carbon\Carbon::parse($latest_date)->format('d M') }})</div>
                        </th>
                        <th class="py-3 px-3 text-right bg-indigo-50/80 border-r-2 border-indigo-300 text-indigo-950 font-black min-w-[140px]">
                            <div>Live Straddle (CE+PE)</div>
                            <div class="text-[10px] font-medium text-indigo-700 font-sans mt-0.5">Today ({{ \Carbon\Carbon::parse($latest_date)->format('d M') }})</div>
                        </th>

                        <!-- 4. Difference Column (Live Straddle vs Yesterday Close) -->
                        <th class="py-3 px-3 text-center bg-slate-50 border-r border-slate-200 text-slate-900 font-black min-w-[160px]">
                            <div>Δ vs Yesterday Close</div>
                            <div class="text-[10px] font-medium text-slate-500 font-sans mt-0.5">vs {{ \Carbon\Carbon::parse($prev_date)->format('d M') }} Base</div>
                        </th>

                        <!-- 5. Opportunity Status -->
                        <th class="py-3 px-3 text-center bg-slate-50 text-slate-800 font-black min-w-[160px]">
                            Decay / Spike Status
                        </th>

                        <!-- 6. Alarm Mute Control -->
                        <th class="py-3 px-2 text-center bg-slate-50 min-w-[60px]">
                            Alert
                        </th>

                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono">
                    @forelse($strikes as $row)
                        @php
                            $strike = $row['strike'];
                            $isAtm = $row['is_atm'];
                            $yClose = $row['yesterday_close']['straddle'] ?? null;
                            $liveCe = $row['live']['ce_ltp'];
                            $livePe = $row['live']['pe_ltp'];
                            $liveStraddle = $row['live']['straddle'];
                            $diffPts = $row['live']['diff_pts'] ?? 0;
                            $diffPct = $row['live']['diff_pct'] ?? 0;
                            $isOverpriced = $row['live']['is_overpriced'] ?? false;
                        @endphp
                        <tr id="strike-row-{{ $strike }}"
                            class="transition {{ $isAtm ? 'bg-indigo-50/40 border-y border-indigo-300 font-semibold' : 'hover:bg-slate-50' }}"
                            :class="{
                                'alarm-spike-active': isStrikeOverpriced({{ $strike }}),
                                'bg-indigo-50/40 border-y border-indigo-300 font-semibold': ({{ $isAtm ? 'true' : 'false' }} && !isStrikeOverpriced({{ $strike }})),
                                'hover:bg-slate-50': (!{{ $isAtm ? 'true' : 'false' }} && !isStrikeOverpriced({{ $strike }}))
                            }">
                            
                            <!-- 1. Sticky Left: Strike & Moneyness -->
                            <td class="py-2.5 px-3 sticky left-0 z-10 border-r border-slate-200 whitespace-nowrap text-center {{ $isAtm ? 'bg-indigo-100/70' : 'bg-white' }}">
                                <div class="flex items-center justify-center gap-1.5">
                                    <span class="text-sm font-black text-slate-900">{{ $strike }}</span>
                                    @if($isAtm)
                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase bg-indigo-600 text-white">ATM</span>
                                    @endif
                                </div>
                                <div class="text-[10px] text-slate-400 font-sans mt-0.5">{{ $row['moneyness'] }}</div>
                            </td>

                            <!-- 2. Historical Available Days Columns (Fixed EOD Close Values) -->
                            @foreach($row['historical_days'] as $hIdx => $hDay)
                                @php
                                    $isYesterdayCol = ($hDay['quote_date'] === $prev_date);
                                @endphp
                                <td class="py-2.5 px-3 border-r border-slate-100 whitespace-nowrap text-right {{ $isYesterdayCol ? 'bg-amber-50/30 border-l-2 border-amber-200' : '' }}">
                                    @if($hDay['straddle'] !== null)
                                        <div>
                                            <span class="font-bold text-slate-900">₹{{ number_format($hDay['straddle'], 2) }}</span>
                                            <div class="text-[10px] text-slate-400 font-sans mt-0.5 flex items-center justify-end gap-1">
                                                <span>C: <strong class="text-slate-600 font-mono">{{ $hDay['ce_close'] !== null ? number_format($hDay['ce_close'], 1) : '—' }}</strong></span>
                                                <span>|</span>
                                                <span>P: <strong class="text-slate-600 font-mono">{{ $hDay['pe_close'] !== null ? number_format($hDay['pe_close'], 1) : '—' }}</strong></span>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-slate-400 text-xs italic font-sans" title="Untraded on this session">—</span>
                                    @endif
                                </td>
                            @endforeach

                            <!-- 3. LIVE CE LTP (Streamed) -->
                            <td class="py-2.5 px-3 text-right whitespace-nowrap border-l-2 border-rose-200 bg-rose-50/30">
                                <span class="font-bold text-slate-900 text-sm transition-colors"
                                      id="ce-ltp-{{ $strike }}"
                                      x-text="'₹' + Number(getLiveStrike({{ $strike }})?.ce_ltp ?? {{ $liveCe }}).toFixed(2)">
                                    ₹{{ number_format($liveCe, 2) }}
                                </span>
                            </td>

                            <!-- 4. LIVE PE LTP (Streamed) -->
                            <td class="py-2.5 px-3 text-right whitespace-nowrap border-r border-slate-200 bg-emerald-50/30">
                                <span class="font-bold text-slate-900 text-sm transition-colors"
                                      id="pe-ltp-{{ $strike }}"
                                      x-text="'₹' + Number(getLiveStrike({{ $strike }})?.pe_ltp ?? {{ $livePe }}).toFixed(2)">
                                    ₹{{ number_format($livePe, 2) }}
                                </span>
                            </td>

                            <!-- 5. LIVE STRADDLE (CE + PE) (Streamed) -->
                            <td class="py-2.5 px-3 text-right whitespace-nowrap border-r-2 border-indigo-300 bg-indigo-50/40 font-bold">
                                <span class="font-black text-sm text-indigo-950 font-mono"
                                      id="straddle-{{ $strike }}"
                                      x-text="'₹' + Number(getLiveStrike({{ $strike }})?.straddle ?? {{ $liveStraddle }}).toFixed(2)">
                                    ₹{{ number_format($liveStraddle, 2) }}
                                </span>
                            </td>

                            <!-- 6. DIFFERENCE vs YESTERDAY CLOSE -->
                            <td class="py-2.5 px-3 border-r border-slate-200 text-center whitespace-nowrap">
                                @if($yClose !== null)
                                    <div>
                                        <!-- Normal Decay: Green -->
                                        <template x-if="!isStrikeOverpriced({{ $strike }})">
                                            <div class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-black bg-emerald-100 text-emerald-800 border border-emerald-300">
                                                <span>↓</span>
                                                <span x-text="Number(getLiveStrike({{ $strike }})?.diff_pts ?? {{ $diffPts }}).toFixed(2) + ' pts'">
                                                    {{ number_format($diffPts, 2) }} pts
                                                </span>
                                                <span class="text-[10px] font-semibold"
                                                      x-text="'(' + Number(getLiveStrike({{ $strike }})?.diff_pct ?? {{ $diffPct }}).toFixed(1) + '%)'">
                                                    ({{ number_format($diffPct, 1) }}%)
                                                </span>
                                            </div>
                                        </template>

                                        <!-- SPIKE / INFLATED: Flashing Crimson Warning -->
                                        <template x-if="isStrikeOverpriced({{ $strike }})">
                                            <div class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-black bg-rose-600 text-white shadow-xs animate-bounce">
                                                <span>🔥 +</span>
                                                <span x-text="Number(getLiveStrike({{ $strike }})?.diff_pts ?? {{ $diffPts }}).toFixed(2) + ' pts'">
                                                    +{{ number_format($diffPts, 2) }} pts
                                                </span>
                                                <span class="text-[10px] font-bold"
                                                      x-text="'(+' + Number(getLiveStrike({{ $strike }})?.diff_pct ?? {{ $diffPct }}).toFixed(1) + '%)'">
                                                    (+{{ number_format($diffPct, 1) }}%)
                                                </span>
                                            </div>
                                        </template>
                                    </div>
                                @else
                                    <span class="text-slate-400 font-sans italic text-xs">—</span>
                                @endif
                            </td>

                            <!-- 7. OPPORTUNITY STATUS / HYPOTHESIS -->
                            <td class="py-2.5 px-3 text-center whitespace-nowrap font-sans">
                                <span class="px-2 py-1 rounded text-[11px] font-black uppercase tracking-wider transition"
                                      :class="isStrikeOverpriced({{ $strike }}) ? 'bg-rose-100 text-rose-800 border border-rose-300' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'"
                                      x-text="isStrikeOverpriced({{ $strike }}) ? '⚡ Overpriced (Short Straddle Entry)' : '✓ Safe Theta Decay'">
                                    {{ $isOverpriced ? '⚡ Overpriced (Short Straddle Entry)' : '✓ Safe Theta Decay' }}
                                </span>
                            </td>

                            <!-- 8. ALARM BELL TOGGLE -->
                            <td class="py-2.5 px-2 text-center whitespace-nowrap">
                                <button type="button"
                                        @click="toggleStrikeMute({{ $strike }})"
                                        class="p-1 rounded hover:bg-slate-200 transition cursor-pointer"
                                        :title="isStrikeMuted({{ $strike }}) ? 'Unmute this strike' : 'Mute alarm for this strike'">
                                    <span x-text="isStrikeMuted({{ $strike }}) ? '🔕' : '🔔'">🔔</span>
                                </button>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 6 + count($day_headers) }}" class="py-8 text-center text-slate-400 font-sans text-sm">
                                No strikes recorded for this corridor.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Table Footer -->
        <div class="p-3 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between text-xs text-slate-500 gap-2">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full" :class="wsConnected ? 'bg-emerald-500' : 'bg-amber-400'"></span>
                <span>Live stream connected to Upstox WebSocket market ticks. Alarm rings continuously on any strike premium expansion.</span>
            </div>
            <div class="font-sans">
                Press <kbd class="px-1.5 py-0.5 rounded bg-slate-200 border border-slate-300 text-slate-700 font-bold font-mono text-[10px]">ESC</kbd> to immediately silence any active ringing alarm.
            </div>
        </div>

    </div>

    <!-- ════════════════════════════════════════════════════════════════════════
         5. MODAL: ALARM SETTINGS & AUDIO TONE SELECTOR
    ════════════════════════════════════════════════════════════════════════ -->
    <div x-show="alarmSettingsModalOpen"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
         @click.self="alarmSettingsModalOpen = false">
        
        <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-lg overflow-hidden animate-scale-up"
             @keydown.escape.window="alarmSettingsModalOpen = false">
            
            <!-- Header -->
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center shadow-xs">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-sm text-slate-900">Continuous Alarm Audio Configuration</h3>
                        <p class="text-[11px] text-slate-500">Pure Web Audio synthesizer • Zero MP3 downloads</p>
                    </div>
                </div>
                <button type="button" @click="alarmSettingsModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 rounded-md transition cursor-pointer">✕</button>
            </div>

            <!-- Body -->
            <div class="p-5 space-y-4">
                
                <!-- Master Toggle -->
                <div class="flex items-center justify-between p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                    <div>
                        <span class="text-xs font-bold text-slate-900 block">Master Alarm Status</span>
                        <span class="text-[11px] text-slate-500 block mt-0.5">Toggle to completely enable or disable sound notifications</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" x-model="alarmEnabled" @change="saveAlarmSettings()" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                    </label>
                </div>

                <!-- Selectable Audio Tones -->
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2.5">
                    <label class="text-xs font-bold text-slate-800 block">Select Alarm Tone</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <template x-for="tone in audioTones" :key="tone.id">
                            <div class="p-2.5 rounded-lg border flex items-center justify-between cursor-pointer transition"
                                 :class="selectedTone === tone.id ? 'bg-indigo-50 border-indigo-400 text-indigo-950 font-bold' : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-100'"
                                 @click="selectedTone = tone.id; saveAlarmSettings()">
                                <div class="flex items-center gap-1.5 text-xs">
                                    <span x-text="tone.icon"></span>
                                    <span x-text="tone.name"></span>
                                </div>
                                <button type="button"
                                        @click.stop="previewTone(tone.id)"
                                        class="px-2 py-0.5 rounded text-[10px] bg-slate-200 hover:bg-slate-300 text-slate-800 font-sans font-semibold transition"
                                        title="Preview Tone">
                                    Play ▶
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Volume Slider -->
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between text-xs font-bold text-slate-800">
                        <span>Alarm Volume</span>
                        <span x-text="Math.round(alarmVolume * 100) + '%'"></span>
                    </div>
                    <input type="range" min="0.1" max="1" step="0.05" x-model.number="alarmVolume" @change="saveAlarmSettings()" class="w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-indigo-600">
                </div>

                <!-- Spike Threshold Sensitivity -->
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                    <label class="text-xs font-bold text-slate-800 block">Trigger Threshold Sensitivity</label>
                    <select x-model="alarmThreshold" @change="saveAlarmSettings()" class="w-full bg-white border border-slate-200 rounded-lg p-2 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        <option value="0">Any Price Higher than Yesterday Close (&gt; 0 pts)</option>
                        <option value="2">+2.00 pts or more above Yesterday Close</option>
                        <option value="5">+5.00 pts or more above Yesterday Close</option>
                        <option value="10">+10.00 pts or more above Yesterday Close</option>
                        <option value="1pct">+1.0% or more above Yesterday Close</option>
                        <option value="2pct">+2.0% or more above Yesterday Close</option>
                    </select>
                </div>

            </div>

            <!-- Footer -->
            <div class="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                <button type="button"
                        @click="testCurrentAlarmLoop()"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-amber-100 hover:bg-amber-200 text-amber-900 border border-amber-300 flex items-center gap-1.5 transition cursor-pointer">
                    <span>⚡ Test Continuous Alarm (3s)</span>
                </button>
                <button type="button"
                        @click="alarmSettingsModalOpen = false"
                        class="px-4 py-1.5 rounded-lg text-xs font-bold bg-slate-800 hover:bg-slate-900 text-white transition cursor-pointer">
                    Done
                </button>
            </div>

        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function liveStraddleRadarApp() {
    return {
        symbol: '{{ $symbol }}',
        expiry: '{{ $selected_expiry }}',
        spotPrice: {{ $spot['price'] }},
        atmStrike: {{ $spot['atm_strike'] }},
        strikesDataMap: @json(collect($strikes)->keyBy('strike')),
        subscribedKeys: @json($subscribed_keys),

        // Live Feed State
        ws: null,
        wsConnected: false,
        wsStatusText: 'Initializing Feed...',
        tickCount: 0,
        protobufRoot: null,
        livePrices: {},

        // Audio Alarm Engine State
        audioCtx: null,
        alarmEnabled: true,
        alarmSettingsModalOpen: false,
        selectedTone: 'siren', // 'siren', 'chime', 'pulse', 'sonar', 'alarm'
        alarmVolume: 0.8,
        alarmThreshold: '0',
        isAlarmRinging: false,
        alarmIntervalId: null,
        alarmBannerMessage: '',
        mutedStrikes: {},
        overpricedStrikesCount: {{ $stats['overpriced_count'] }},
        maxObservedSpikePts: {{ $stats['max_spike'] }},
        maxObservedSpikeStrike: null,

        audioTones: [
            { id: 'siren', name: 'Emergency Warble Siren', icon: '🚨' },
            { id: 'chime', name: 'Harmonic Twin Bells', icon: '🔔' },
            { id: 'pulse', name: 'Digital Cyber Pulse', icon: '⚡' },
            { id: 'sonar', name: 'Tactical Sonar Ping', icon: '🎯' },
            { id: 'alarm', name: 'High-Pitched Repeating Beep', icon: '⚠️' }
        ],

        init() {
            this.loadAlarmSettings();

            // ESC hotkey to silence active alarm
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' || e.key === 'Esc') {
                    if (this.isAlarmRinging) {
                        this.silenceAlarm();
                    }
                    this.alarmSettingsModalOpen = false;
                }
            });

            // Seed initial prices
            Object.values(this.strikesDataMap).forEach(row => {
                if (row.ce_key && row.live?.ce_ltp) this.livePrices[row.ce_key] = row.live.ce_ltp;
                if (row.pe_key && row.live?.pe_ltp) this.livePrices[row.pe_key] = row.live.pe_ltp;
            });

            // Initialize Protobuf and WebSocket Feed
            this.initProtobufAndWebSocket();

            // Run check on initial load
            this.evaluateAlarms();
        },

        loadAlarmSettings() {
            try {
                const saved = localStorage.getItem('straddle_radar_alarm_cfg');
                if (saved) {
                    const parsed = JSON.parse(saved);
                    if (parsed.alarmEnabled !== undefined) this.alarmEnabled = parsed.alarmEnabled;
                    if (parsed.selectedTone) this.selectedTone = parsed.selectedTone;
                    if (parsed.alarmVolume !== undefined) this.alarmVolume = parsed.alarmVolume;
                    if (parsed.alarmThreshold !== undefined) this.alarmThreshold = parsed.alarmThreshold;
                }
            } catch (e) {
                console.warn('Could not read alarm settings from localStorage', e);
            }
        },

        saveAlarmSettings() {
            try {
                const cfg = {
                    alarmEnabled: this.alarmEnabled,
                    selectedTone: this.selectedTone,
                    alarmVolume: this.alarmVolume,
                    alarmThreshold: this.alarmThreshold,
                };
                localStorage.setItem('straddle_radar_alarm_cfg', JSON.stringify(cfg));
            } catch (e) {
                console.warn('Could not save alarm settings', e);
            }
        },

        getAtmStrikeRecord() {
            return Object.values(this.strikesDataMap).find(s => s.is_atm) || Object.values(this.strikesDataMap)[0];
        },

        getLiveStrike(strike) {
            return this.strikesDataMap[strike]?.live;
        },

        isStrikeOverpriced(strike) {
            const s = this.strikesDataMap[strike];
            if (!s || !s.live) return false;
            return Boolean(s.live.is_overpriced && !this.isStrikeMuted(strike));
        },

        isStrikeMuted(strike) {
            return !!this.mutedStrikes[strike];
        },

        toggleStrikeMute(strike) {
            this.mutedStrikes[strike] = !this.mutedStrikes[strike];
            this.evaluateAlarms();
        },

        /* ══════════════════════════════════════════════════════════════════
           WEB AUDIO API SYNTHESIZER (Pure JS, Zero MP3 Dependency)
           ══════════════════════════════════════════════════════════════════ */
        getAudioContext() {
            if (!this.audioCtx) {
                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (AudioContextClass) {
                    this.audioCtx = new AudioContextClass();
                }
            }
            if (this.audioCtx && this.audioCtx.state === 'suspended') {
                this.audioCtx.resume();
            }
            return this.audioCtx;
        },

        playTone(toneName, volume = null) {
            const ctx = this.getAudioContext();
            if (!ctx) return;
            const now = ctx.currentTime;
            const vol = volume !== null ? volume : this.alarmVolume;

            const masterGain = ctx.createGain();
            masterGain.gain.setValueAtTime(vol, now);
            masterGain.connect(ctx.destination);

            switch(toneName) {
                case 'siren': {
                    // Dual frequency emergency warble
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(650, now);
                    osc.frequency.linearRampToValueAtTime(950, now + 0.25);
                    osc.frequency.linearRampToValueAtTime(650, now + 0.5);

                    gain.gain.setValueAtTime(0.3, now);
                    gain.gain.linearRampToValueAtTime(0, now + 0.55);

                    osc.connect(gain);
                    gain.connect(masterGain);
                    osc.start(now);
                    osc.stop(now + 0.55);
                    break;
                }
                case 'chime': {
                    // Twin harmonic chime
                    [880, 1318.5].forEach((freq, i) => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(freq, now + (i * 0.1));

                        gain.gain.setValueAtTime(0, now + (i * 0.1));
                        gain.gain.linearRampToValueAtTime(0.4, now + (i * 0.1) + 0.02);
                        gain.gain.exponentialRampToValueAtTime(0.001, now + (i * 0.1) + 0.8);

                        osc.connect(gain);
                        gain.connect(masterGain);
                        osc.start(now + (i * 0.1));
                        osc.stop(now + (i * 0.1) + 0.85);
                    });
                    break;
                }
                case 'pulse': {
                    // Digital fast cyber ping
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(1200, now);
                    osc.frequency.exponentialRampToValueAtTime(400, now + 0.35);

                    gain.gain.setValueAtTime(0.5, now);
                    gain.gain.exponentialRampToValueAtTime(0.01, now + 0.35);

                    osc.connect(gain);
                    gain.connect(masterGain);
                    osc.start(now);
                    osc.stop(now + 0.36);
                    break;
                }
                case 'sonar': {
                    // Deep tactical sonar ping
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(1046.5, now); // C6

                    gain.gain.setValueAtTime(0.5, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.9);

                    osc.connect(gain);
                    gain.connect(masterGain);
                    osc.start(now);
                    osc.stop(now + 0.95);
                    break;
                }
                case 'alarm':
                default: {
                    // Triple high-pitched stutter alert beep
                    [0, 0.12, 0.24].forEach(offset => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.type = 'square';
                        osc.frequency.setValueAtTime(1500, now + offset);

                        gain.gain.setValueAtTime(0.25, now + offset);
                        gain.gain.linearRampToValueAtTime(0, now + offset + 0.07);

                        osc.connect(gain);
                        gain.connect(masterGain);
                        osc.start(now + offset);
                        osc.stop(now + offset + 0.08);
                    });
                    break;
                }
            }
        },

        previewTone(toneId) {
            this.playTone(toneId);
        },

        startContinuousAlarm(message = '') {
            this.alarmBannerMessage = message;
            this.isAlarmRinging = true;

            // Immediately sound tone
            this.playTone(this.selectedTone);

            // Repeat continuously every 1.2 seconds until silenced
            if (!this.alarmIntervalId) {
                this.alarmIntervalId = setInterval(() => {
                    if (this.isAlarmRinging && this.alarmEnabled) {
                        this.playTone(this.selectedTone);
                    } else {
                        this.silenceAlarm();
                    }
                }, 1200);
            }
        },

        silenceAlarm() {
            this.isAlarmRinging = false;
            if (this.alarmIntervalId) {
                clearInterval(this.alarmIntervalId);
                this.alarmIntervalId = null;
            }
        },

        testCurrentAlarmLoop() {
            this.startContinuousAlarm('TEST MODE: Simulating continuous audio alarm loop. Press ESC or click Done.');
            setTimeout(() => {
                this.silenceAlarm();
            }, 3000);
        },

        simulateTestSpike() {
            // Find ATM strike and bump its live Straddle by +15 pts above yesterday close
            const atm = this.getAtmStrikeRecord();
            if (!atm) return;
            const yClose = Number(atm.yesterday_close?.straddle || 500);
            const spikeAmount = 15.5;
            atm.live.straddle = Number((yClose + spikeAmount).toFixed(2));
            atm.live.ce_ltp = Number((Number(atm.live.ce_ltp || 0) + 10).toFixed(2));
            atm.live.pe_ltp = Number((Number(atm.live.pe_ltp || 0) + 5.5).toFixed(2));
            atm.live.diff_pts = spikeAmount;
            atm.live.diff_pct = Number(((spikeAmount / yClose) * 100).toFixed(2));
            atm.live.is_overpriced = true;

            delete this.mutedStrikes[atm.strike];

            // Flash ATM strike elements
            const ceEl = document.getElementById('ce-ltp-' + atm.strike);
            const peEl = document.getElementById('pe-ltp-' + atm.strike);
            if (ceEl) { ceEl.classList.add('tick-up'); setTimeout(() => ceEl.classList.remove('tick-up'), 600); }
            if (peEl) { peEl.classList.add('tick-up'); setTimeout(() => peEl.classList.remove('tick-up'), 600); }

            this.evaluateAlarms();
        },

        /* ══════════════════════════════════════════════════════════════════
           ALARM EVALUATION LOGIC
           ══════════════════════════════════════════════════════════════════ */
        evaluateAlarms() {
            let count = 0;
            let peakSpike = 0;
            let peakStrike = null;
            let activeSpikeMessages = [];

            Object.values(this.strikesDataMap).forEach(row => {
                const yClose = row.yesterday_close?.straddle;
                if (yClose !== null && yClose !== undefined && yClose > 0) {
                    const liveStraddle = Number(row.live.straddle);
                    const diff = liveStraddle - Number(yClose);
                    row.live.diff_pts = Number(diff.toFixed(2));
                    row.live.diff_pct = Number(((diff / yClose) * 100).toFixed(2));

                    let triggersAlert = false;
                    if (this.alarmThreshold === '0' && diff > 0) triggersAlert = true;
                    else if (this.alarmThreshold === '2' && diff >= 2) triggersAlert = true;
                    else if (this.alarmThreshold === '5' && diff >= 5) triggersAlert = true;
                    else if (this.alarmThreshold === '10' && diff >= 10) triggersAlert = true;
                    else if (this.alarmThreshold === '1pct' && row.live.diff_pct >= 1.0) triggersAlert = true;
                    else if (this.alarmThreshold === '2pct' && row.live.diff_pct >= 2.0) triggersAlert = true;

                    row.live.is_overpriced = triggersAlert;

                    if (triggersAlert) {
                        count++;
                        if (diff > peakSpike) {
                            peakSpike = diff;
                            peakStrike = row.strike;
                        }
                        if (!this.isStrikeMuted(row.strike)) {
                            activeSpikeMessages.push(`Strike ${row.strike}: ₹${liveStraddle.toFixed(2)} (+${diff.toFixed(2)} pts)`);
                        }
                    }
                }
            });

            this.overpricedStrikesCount = count;
            this.maxObservedSpikePts = peakSpike;
            this.maxObservedSpikeStrike = peakStrike;

            if (activeSpikeMessages.length > 0 && this.alarmEnabled) {
                const msg = activeSpikeMessages.slice(0, 3).join(' • ');
                this.startContinuousAlarm(msg);
            } else if (activeSpikeMessages.length === 0) {
                this.silenceAlarm();
            }
        },

        /* ══════════════════════════════════════════════════════════════════
           UPSTOX PROTOBUF & WEBSOCKET LIVE FEED INTEGRATION
           ══════════════════════════════════════════════════════════════════ */
        async initProtobufAndWebSocket() {
            try {
                this.wsStatusText = 'Authorizing Live Feed...';
                
                // 1. Ensure Protobuf is loaded
                if (window.protobuf && !this.protobufRoot) {
                    try {
                        this.protobufRoot = await protobuf.load('/MarketDataFeed_v3.proto');
                    } catch (pErr) {
                        console.warn('Could not load MarketDataFeed_v3.proto:', pErr);
                    }
                }

                // 2. Fetch WebSocket authorized URI
                const res = await fetch('{{ route('api.live.straddle.radar.ws-url') }}').then(r => r.json());
                const wsUrl = res.authorizedRedirectUri || res.data?.authorizedRedirectUri;
                
                if (!wsUrl) {
                    this.wsStatusText = 'Feed Offline (Token Inactive)';
                    this.wsConnected = false;
                    return;
                }

                this.connectWebSocket(wsUrl);
            } catch (err) {
                console.warn('Live WebSocket fallback to simulated / REST mode', err);
                this.wsStatusText = 'Feed Offline (Market Closed)';
                this.wsConnected = false;
            }
        },

        connectWebSocket(wsUrl) {
            try {
                if (this.ws) {
                    this.ws.onclose = null;
                    this.ws.close();
                }

                this.ws = new WebSocket(wsUrl);
                this.ws.binaryType = 'arraybuffer';

                this.ws.onopen = () => {
                    this.wsConnected = true;
                    this.wsStatusText = 'LIVE STREAM ACTIVE';
                    this.subscribeAllKeys();
                };

                this.ws.onmessage = (event) => {
                    if (typeof event.data === 'string') return;
                    this.decodeFeed(event.data);
                };

                this.ws.onerror = (e) => {
                    console.warn('WebSocket stream error', e);
                    this.wsConnected = false;
                    this.wsStatusText = 'Feed Reconnecting...';
                };

                this.ws.onclose = () => {
                    this.wsConnected = false;
                    this.wsStatusText = 'Feed Disconnected';
                    // Reconnect attempt after 5 seconds
                    setTimeout(() => {
                        this.initProtobufAndWebSocket();
                    }, 5000);
                };
            } catch (e) {
                console.error('WebSocket connection error', e);
                this.wsStatusText = 'Feed Offline';
            }
        },

        subscribeAllKeys() {
            if (!this.ws || this.ws.readyState !== WebSocket.OPEN) return;
            if (!this.subscribedKeys || this.subscribedKeys.length === 0) return;

            const subMessage = {
                guid: 'straddle_radar_' + Date.now(),
                method: 'sub',
                params: {
                    mode: 'full',
                    instrumentKeys: this.subscribedKeys,
                }
            };
            this.ws.send(new TextEncoder().encode(JSON.stringify(subMessage)));
        },

        decodeFeed(buffer) {
            if (!this.protobufRoot) return;
            try {
                const arr = new Uint8Array(buffer);
                if (arr.length > 0 && arr[0] === 123) return; // JSON text message

                const FeedResponse = this.protobufRoot.lookupType('com.upstox.marketdatafeederv3udapi.rpc.proto.FeedResponse');
                const message = FeedResponse.decode(arr);
                const obj = FeedResponse.toObject(message, { enums: String, bytes: String });

                if (obj && obj.feeds) {
                    let hasPriceUpdate = false;

                    for (const [key, feed] of Object.entries(obj.feeds)) {
                        let ltp = null;
                        if (feed.fullFeed && feed.fullFeed.marketFF && feed.fullFeed.marketFF.ltpc) {
                            ltp = feed.fullFeed.marketFF.ltpc.ltp;
                        } else if (feed.fullFeed && feed.fullFeed.indexFF && feed.fullFeed.indexFF.ltpc) {
                            ltp = feed.fullFeed.indexFF.ltpc.ltp;
                        } else if (feed.ltpc && feed.ltpc.ltp) {
                            ltp = feed.ltpc.ltp;
                        }

                        if (ltp !== null && ltp > 0) {
                            const oldPrice = this.livePrices[key] || ltp;
                            this.livePrices[key] = ltp;

                            // Spot check
                            if (key.includes('Nifty 50') || key.includes(this.symbol)) {
                                this.spotPrice = ltp;
                            }

                            // Flash strike DOM elements on tick
                            this.flashTickElement(key, ltp, oldPrice);
                            hasPriceUpdate = true;
                        }
                    }

                    if (hasPriceUpdate) {
                        this.tickCount++;
                        this.updateStrikeLiveCalculations();
                    }
                }
            } catch (e) {
                // Protobuf decode fallback
            }
        },

        flashTickElement(key, newLtp, oldLtp) {
            // Find strike that uses this key
            const row = Object.values(this.strikesDataMap).find(s => s.ce_key === key || s.pe_key === key);
            if (!row) return;

            const isCe = row.ce_key === key;
            const elId = (isCe ? 'ce-ltp-' : 'pe-ltp-') + row.strike;
            const el = document.getElementById(elId);
            if (!el) return;

            const cls = newLtp >= oldLtp ? 'tick-up' : 'tick-down';
            el.classList.add(cls);
            setTimeout(() => {
                el.classList.remove(cls);
            }, 600);
        },

        updateStrikeLiveCalculations() {
            Object.values(this.strikesDataMap).forEach(row => {
                const ceLtp = (row.ce_key && this.livePrices[row.ce_key] !== undefined) ? this.livePrices[row.ce_key] : row.live.ce_ltp;
                const peLtp = (row.pe_key && this.livePrices[row.pe_key] !== undefined) ? this.livePrices[row.pe_key] : row.live.pe_ltp;

                row.live.ce_ltp = ceLtp;
                row.live.pe_ltp = peLtp;
                row.live.straddle = Number((ceLtp + peLtp).toFixed(2));
            });

            this.evaluateAlarms();
        }
    };
}
</script>
@endpush
