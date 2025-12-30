@extends('layouts.app')

@section('title')
    HLC
@endsection

@section('content')

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">Monthly Strangle P&L</h1>
            <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                Daily profit/loss tracking for entire expiry month (Open-to-Open)
            </p>
        </div>

        <div class="bg-white rounded-2xl shadow-xl p-8 mb-12 border border-gray-100">
            <div class="flex flex-col lg:flex-row gap-6">
                <div class="flex-1">
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Strike-Instrument (comma separated)</label>
                    <input
                        type="text"
                        id="strikes"
                        value="{{ implode(',', array_map(fn($s) => $s['strike'] . '-' . $s['instrument_type'], $strikes ?? [])) }}"
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all duration-200 text-lg"
                        placeholder="24700-CE,23100-PE"
                    >
                </div>
                <div class="flex-1 lg:flex-none">
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Expiry Date</label>
                    <input
                        type="date"
                        id="expiry"
                        value="{{ $expiry }}"
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all duration-200 text-lg"
                    >
                </div>
                <div class="flex items-end lg:pt-9">
                    <button
                        onclick="updateURL()"
                        class="bg-primary hover:bg-blue-600 text-white font-semibold px-8 py-3 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 text-lg"
                    >
                        Analyze Month
                    </button>
                </div>
            </div>
        </div>
        <!-- Daily P&L Table -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100">
            <div class="bg-gradient-to-r from-primary to-blue-600 px-8 py-6">
                <h2 class="text-2xl font-bold text-white">Daily Open-to-Open P&L</h2>
                <p class="text-blue-100 mt-1">Positive = Profit for Short Strangle</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">CE Open</th>
                        <th class="px-4 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">PE Open</th>
                        <th class="px-4 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">CE+PE Today</th>
                        <th class="px-4 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Day P&L (₹)</th>
                        <th class="px-4 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Day P&L (%)</th>
                        <th class="px-4 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">CE+PE Cum</th>
                        <th class="px-4 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Cum P&L (₹)</th>
                        <th class="px-4 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Cum P&L (%)</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @foreach($data['combined'] as $row)
                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                            <td class="px-6 py-4 font-mono font-semibold text-sm bg-gray-50">
                                {{ \Carbon\Carbon::parse($row['date'])->format('M d') }}
                                <div class="text-xs text-gray-500">{{ $row['timestamp'] }}</div>
                            </td>

                            {{-- CE open --}}
                            <td class="px-4 py-4">
                                @if($row['ce'])
                                    <div class="font-mono text-sm">
                                        <div class="font-semibold text-gray-800">
                                            {{ number_format($row['ce']['open'], 2) }}
                                        </div>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400 italic">-</span>
                                @endif
                            </td>

                            {{-- PE open --}}
                            <td class="px-4 py-4">
                                @if($row['pe'])
                                    <div class="font-mono text-sm">
                                        <div class="font-semibold text-gray-800">
                                            {{ number_format($row['pe']['open'], 2) }}
                                        </div>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400 italic">-</span>
                                @endif
                            </td>

                            {{-- CE+PE today --}}
                            <td class="px-4 py-4">
                                <div class="font-mono text-sm font-semibold text-blue-700">
                                    {{ number_format($row['ce_pe_sum_today'], 2) }}
                                </div>
                            </td>

                            {{-- Day P&L --}}
                            <td class="px-4 py-4">
                        <span class="font-mono text-sm font-bold {{ $row['day_strangle_pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $row['day_strangle_pnl'] >= 0 ? '+' : '' }}{{ number_format($row['day_strangle_pnl'], 2) }}
                        </span>
                            </td>
                            <td class="px-4 py-4">
                        <span class="font-mono text-sm font-bold {{ $row['day_strangle_pnl_pct'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $row['day_strangle_pnl_pct'] >= 0 ? '+' : '' }}{{ number_format($row['day_strangle_pnl_pct'], 2) }}%
                        </span>
                            </td>

                            {{-- CE+PE cumulative value --}}
                            <td class="px-4 py-4">
                                <div class="font-mono text-sm font-semibold text-indigo-700">
                                    {{ number_format($row['ce_pe_sum_cum'], 2) }}
                                </div>
                            </td>

                            {{-- Cum P&L --}}
                            <td class="px-4 py-4">
                        <span class="font-mono text-sm font-bold {{ $row['cum_strangle_pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $row['cum_strangle_pnl'] >= 0 ? '+' : '' }}{{ number_format($row['cum_strangle_pnl'], 2) }}
                        </span>
                            </td>
                            <td class="px-4 py-4">
                        <span class="font-mono text-sm font-bold {{ $row['cum_strangle_pnl_pct'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $row['cum_strangle_pnl_pct'] >= 0 ? '+' : '' }}{{ number_format($row['cum_strangle_pnl_pct'], 2) }}%
                        </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>


        @if (!empty($data['strikes']))
            <!-- Monthly Summary -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                @foreach($data['strikes'] as $strikeKey => $strikeData)
                    <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xl font-bold text-gray-900">
                                {{ $strikeData['strike'] }} {{ $strikeData['instrument_type'] }}
                            </h3>
                            <span class="px-3 py-1 rounded-full text-sm font-semibold
                        {{ $strikeData['total_pnl_pct'] >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $strikeData['trading_days'] }} days
                    </span>
                        </div>
                        <div class="space-y-2 text-center">
                            <div class="text-3xl font-bold {{ $strikeData['total_pnl_pct'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $strikeData['total_pnl_pct'] >= 0 ? '+' : '' }}{{ number_format($strikeData['total_pnl_pct'], 1) }}%
                            </div>
                            <div class="text-2xl font-bold {{ $strikeData['total_pnl_abs'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                ₹{{ number_format($strikeData['total_pnl_abs'], 2) }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Daily P&L Table -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100">
                <div class="bg-gradient-to-r from-primary to-blue-600 px-8 py-6">
                    <h2 class="text-2xl font-bold text-white">Daily Open-to-Open P&L</h2>
                    <p class="text-blue-100 mt-1">Positive = Profit for Short Strangle</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Date</th>
                            @foreach($data['strikes'] as $strikeKey => $strikeData)
                                <th class="px-4 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    {{ $strikeData['strike'] }}<br><span class="text-xs text-gray-500">{{ $strikeData['instrument_type'] }}</span>
                                </th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        @foreach($data['strikes'][array_key_first($data['strikes'])]['daily_data'] ?? [] as $dayIndex => $dayData)
                            <tr class="hover:bg-gray-50 transition-colors duration-150">
                                <td class="px-6 py-4 font-mono font-semibold text-sm bg-gray-50">
                                    {{ \Carbon\Carbon::parse($dayData['date'])->format('M d') }}
                                    <div class="text-xs text-gray-500">{{ $dayData['timestamp'] }}</div>
                                </td>
                                @foreach($data['strikes'] as $strikeKey => $strikeData)
                                    @php
                                        $daily = $strikeData['daily_data'][$dayIndex] ?? null;
                                    @endphp
                                    <td class="px-4 py-4">
                                        @if($daily)
                                            <div class="font-mono text-sm">
                                                <div class="font-semibold text-lg {{ $daily['pnl_pct'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                    {{ $daily['pnl_pct'] >= 0 ? '+' : '' }}{{ number_format($daily['pnl_pct'], 1) }}%
                                                </div>
                                                <div class="text-xs text-gray-500">₹{{ number_format($daily['pnl_abs'], 2) }}</div>
                                                <div class="text-xs opacity-75">{{ number_format($daily['open'], 1) }}</div>
                                            </div>
                                        @else
                                            <div class="text-xs text-gray-400 italic">No data</div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Individual Strike Details -->
            @foreach($data['strikes'] as $strikeKey => $strikeData)
                <div class="mt-8 bg-white rounded-2xl shadow-xl p-6 border border-gray-100">
                    <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <span class="w-3 h-3 rounded-full mr-3 {{ $strikeData['total_pnl_pct'] >= 0 ? 'bg-green-500' : 'bg-red-500' }}"></span>
                        Detailed: {{ $strikeData['strike'] }} {{ $strikeData['instrument_type'] }}
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-left">O</th>
                                <th class="px-4 py-3 text-left">H</th>
                                <th class="px-4 py-3 text-left">L</th>
                                <th class="px-4 py-3 text-left">C</th>
                                <th class="px-4 py-3 text-left">Vol</th>
                                <th class="px-4 py-3 text-left">OI</th>
                                <th class="px-4 py-3 text-left">P&L %</th>
                                <th class="px-4 py-3 text-left">P&L ₹</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @foreach($strikeData['daily_data'] as $daily)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-mono">{{ \Carbon\Carbon::parse($daily['date'])->format('M d') }}</td>
                                    <td class="px-4 py-3 font-mono">{{ number_format($daily['open'], 1) }}</td>
                                    <td class="px-4 py-3 font-mono text-green-600">{{ number_format($daily['high'], 1) }}</td>
                                    <td class="px-4 py-3 font-mono text-red-600">{{ number_format($daily['low'], 1) }}</td>
                                    <td class="px-4 py-3 font-mono font-semibold">{{ number_format($daily['close'], 1) }}</td>
                                    <td class="px-4 py-3 font-mono">{{ number_format($daily['volume']/1000000, 1) }}M</td>
                                    <td class="px-4 py-3 font-mono">{{ number_format($daily['oi']/1000, 0) }}K</td>
                                    <td class="px-4 py-3 font-mono {{ $daily['pnl_pct'] >= 0 ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold' }}">
                                        {{ $daily['pnl_pct'] >= 0 ? '+' : '' }}{{ number_format($daily['pnl_pct'], 1) }}%
                                    </td>
                                    <td class="px-4 py-3 font-mono {{ $daily['pnl_abs'] >= 0 ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold' }}">
                                        {{ $daily['pnl_abs'] >= 0 ? '+' : '' }}₹{{ number_format($daily['pnl_abs'], 1) }}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    <script>
        function updateURL() {
            const strikes = document.getElementById('strikes').value;
            const expiry = document.getElementById('expiry').value;

            const params = new URLSearchParams();
            if (strikes) params.set('strike', strikes);
            if (expiry) params.set('expiry', expiry);

            const url = `/strangle-profit?${params.toString()}`;
            window.history.pushState({}, '', url);
            window.location.reload();
        }

        document.getElementById('strikes').addEventListener('input', debounce(updateURL, 1000));
        document.getElementById('expiry').addEventListener('change', updateURL);

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    </script>
@endsection
