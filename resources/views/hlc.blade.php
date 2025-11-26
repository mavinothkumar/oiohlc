@extends('layouts.app')

@section('title')
    HLC
@endsection

@section('content')
    @php
        $level = ($spotData->high - $spotData->low) * 0.2611;
    @endphp

    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">NIFTY ATM Strikes & OI (Previous Day)</h1>

        <div class="max-w-5xl mx-auto mt-4">
            <!-- Row 1: Expiry, Current Day, OHLC -->
            <div class="flex flex-wrap items-center gap-4 bg-white shadow rounded px-4 py-2 mb-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500">Expiry Date:</span>
                    <span class="font-semibold text-indigo-700">{{ $expiryDate }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500">Current Day:</span>
                    <span class="font-semibold text-gray-800">{{ $currentWorkDate }}</span>
                </div>
                @if($spotData)
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-500">Open:</span>
                        <span class="font-semibold text-blue-600">{{ $spotData->open }}</span>
                        <span class="text-xs text-gray-500">High:</span>
                        <span class="font-semibold text-green-600">{{ $spotData->high }}</span>
                        <span class="text-xs text-gray-500">Low:</span>
                        <span class="font-semibold text-red-600">{{ $spotData->low }}</span>
                        <span class="text-xs text-gray-500">Close:</span>
                        <span class="font-semibold text-yellow-600">{{ $spotData->close }}</span>
                    </div>
                @else
                    <span class="text-xs text-red-500">No OHLC data found for previous day.</span>
                @endif
            </div>

            <!-- Row 2: Earth Level/Preopen -->
            <div class="flex flex-wrap items-center gap-4 bg-white shadow rounded px-4 py-2 mb-3">
                <label for="preopen" class="text-xs font-semibold text-gray-700">Preopen Value:</label>
                <input
                    type="number"
                    id="preopen"
                    step="any"
                    class="border border-gray-300 rounded px-2 py-1 w-32 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                    placeholder="Enter preopen"
                />
                <span class="font-bold text-indigo-800">Earth Level (26.11%):</span>
                <span id="earthLevel" class="font-semibold">{{ number_format($level, 2) }}</span>
                <span class="font-bold text-gray-700">Preopen High:</span>
                <span id="preopenHigh">-</span>
                <span class="font-bold text-gray-700">Preopen Low:</span>
                <span id="preopenLow">-</span>
            </div>

            <!-- Row 3: Strike Range, Symbol, Button -->
            <form method="get" action="{{ route('hlc.index') }}"
                class="flex flex-wrap items-center gap-4 bg-white shadow rounded px-4 py-2 mb-2">
                <label class="text-xs font-semibold text-gray-700" for="strike_range">Strike Range (+/-):</label>
                <input type="number" name="strike_range" value="{{ $strikeRange }}"
                    class="border border-gray-300 px-2 py-1 rounded w-24 focus:ring-2 focus:ring-indigo-200" min="50" step="50" max="1000">

                <label class="text-xs font-semibold text-gray-700" for="symbol">Symbol:</label>
                <select name="symbol"
                    class="px-2 py-1 border border-gray-300 rounded focus:ring-indigo-200 focus:border-indigo-400 bg-white">
                    <option value="NIFTY">NIFTY</option>
                    <option value="BANKNIFTY" {{isset($_GET['symbol']) && $_GET['symbol'] === 'BANKNIFTY' ? 'selected' : ''}}>BANKNIFTY</option>
                    <option value="SENSEX" {{isset($_GET['symbol']) && $_GET['symbol'] === 'SENSEX' ? 'selected' : ''}}>SENSEX</option>
                    <option value="FINNIFTY" {{isset($_GET['symbol']) && $_GET['symbol'] === 'FINNIFTY' ? 'selected' : ''}}>FINNIFTY</option>
                    <option value="BANKEX" {{isset($_GET['symbol']) && $_GET['symbol'] === 'BANKEX' ? 'selected' : ''}}>BANKEX</option>
                </select>
                <button type="submit"
                    class="bg-indigo-600 text-white font-bold px-4 py-2 rounded shadow hover:bg-indigo-800 transition-all">
                    Update Range
                </button>
            </form>

            <!-- Data table below... -->
        </div>

        <script>
            const level = @json($level);
            const preopenInput = document.getElementById('preopen');
            const preopenHigh = document.getElementById('preopenHigh');
            const preopenLow = document.getElementById('preopenLow');
            preopenInput.addEventListener('input', function() {
                const preopen = parseFloat(this.value || 0);
                if (this.value !== '') {
                    preopenHigh.textContent = (preopen + level).toFixed(2);
                    preopenLow.textContent = (preopen - level).toFixed(2);
                } else {
                    preopenHigh.textContent = '-';
                    preopenLow.textContent = '-';
                }
            });
        </script>


        <div class="overflow-x-auto shadow rounded">
            <table class="min-w-full bg-white border border-gray-200 text-xs">
                <thead>
                <tr class="bg-slate-100">
                    <th class="px-2 py-3">Strike</th>
                    <th class="px-2 py-3">CE LTP</th>
                    <th class="px-2 py-3">PE LTP</th>
                    <th class="px-2 py-3">|CE-PE|</th>
                    <th class="px-2 py-3">Min Resistance</th>
                    <th class="px-2 py-3">Min Support</th>
                    <th class="px-2 py-3">CE+PE</th>
                    <th class="px-2 py-3">Max Resistance</th>
                    <th class="px-2 py-3">Max Support</th>
                </tr>
                </thead>
                <tbody>
                @foreach($rows as $row)
                    <tr class="@if($row['strike'] == $atmStrike) bg-yellow-100 font-bold @else hover:bg-slate-50 @endif transition-colors">
                        <td class="px-2 py-2 text-center">{{ $row['strike'] }}</td>
                        <td class="px-2 py-2 text-right">{{ number_format($row['ce_ltp'], 2) }}</td>
                        <td class="px-2 py-2 text-right">{{ number_format($row['pe_ltp'],2) }}</td>
                        <td class="px-2 py-2 text-right">{{ number_format($row['diff'], 2) }}</td>
                        <td class="px-2 py-2 text-right">{{ number_format($row['min_resistance'], 2) }}</td>
                        <td class="px-2 py-2 text-right">{{ number_format($row['min_support'], 2) }}</td>
                        <td class="px-2 py-2 text-right">{{ number_format($row['sum_ce_pe'], 2) }}</td>
                        <td class="px-2 py-2 text-right">{{ number_format($row['max_resistance'], 2) }}</td>
                        <td class="px-2 py-2 text-right">{{ number_format($row['max_support'], 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @if(empty($rows))
                <div class="p-4 text-center text-red-600 font-semibold">
                    No strike data found for this range and expiry.
                </div>
            @endif
        </div>
    </div>
@endsection
