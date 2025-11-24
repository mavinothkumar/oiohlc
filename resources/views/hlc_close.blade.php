@extends('layouts.app')
@section('title')
    HLC Close
@endsection
@section('content')
    <div class="container mx-auto p-6">
        <h1 class="text-2xl font-bold mb-4">NIFTY CE/PE Custom Pairing (by Nearest Close Value)</h1>

        <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-4 bg-indigo-50 rounded shadow">
                <div>
                    <span class="text-gray-500 text-xs">Expiry Date</span>
                    <span class="font-bold">{{ $expiryDate }}</span>
                </div>
                <div>
                    <span class="text-gray-500 text-xs">Prev Work Date</span>
                    <span class="font-bold">{{ $prevWorkDate }}</span>
                </div>
                <div>
                    <span class="text-gray-500 text-xs">Trading Date</span>
                    <span class="font-bold">{{ $currentWorkDate }}</span>
                </div>
            </div>
        </div>

        <form method="get" action="{{ route('hlc.close') }}" class="flex items-end gap-2">
            <div>
                <label class="block text-xs text-gray-700">Strike Range (+/-)</label>
                <input type="number" name="strike_range" value="{{ $strikeRange }}"
                    class="border px-2 py-1 rounded w-28" min="50" step="50" max="1000">
            </div>
            <div>
                <label class="block text-xs text-gray-700">Symbol</label>
                <select name="symbol"  class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-white">
                    <option value="NIFTY">NIFTY</option>
                    <option value="BANKNIFTY" {{isset($_GET['symbol']) && $_GET['symbol'] === 'BANKNIFTY' ? 'selected' : ''}}>BANKNIFTY</option>
                    <option value="SENSEX" {{isset($_GET['symbol']) && $_GET['symbol'] === 'SENSEX' ? 'selected' : ''}}>SENSEX</option>
                    <option value="FINNIFTY" {{isset($_GET['symbol']) && $_GET['symbol'] === 'FINNIFTY' ? 'selected' : ''}}>FINNIFTY</option>
                    <option value="BANKEX" {{isset($_GET['symbol']) && $_GET['symbol'] === 'BANKEX' ? 'selected' : ''}}>BANKEX</option>
                </select>
            </div>
            <button type="submit"
                class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-800 mb-1">
                Update Range
            </button>
        </form>

        <h2 class="text-lg font-semibold mb-2">Pairing: CE → Nearest PE</h2>
        <div class="overflow-x-auto shadow rounded mb-8">
            <table class="min-w-full bg-white border border-gray-300 text-sm">
                <thead>
                <tr class="bg-slate-200 text-gray-900 font-semibold">
                    <th class="px-4 py-3 text-center">CE Strike</th>
                    <th class="px-4 py-3 text-right">CE Close</th>
                    <th class="px-4 py-3 text-right">PE Close</th>
                    <th class="px-4 py-3 text-center">PE Strike</th>
                    <th class="px-4 py-3 text-right">|CE-PE|</th>
                    <th class="px-4 py-3 text-right">Min Resistance</th>
                    <th class="px-4 py-3 text-right">Min Support</th>
                    <th class="px-4 py-3 text-right">CE+PE</th>
                    <th class="px-4 py-3 text-right">Max Resistance</th>
                    <th class="px-4 py-3 text-right">Max Support</th>
                </tr>
                </thead>
                <tbody>
                @foreach($pairs as $i => $row)
                    <tr class="{{ $i % 2 == 0 ? 'bg-gray-50' : 'bg-white' }} hover:bg-indigo-50 transition-colors border-b border-gray-200">
                        <td class="px-4 py-3 text-center">{{ (int) $row['ce_strike'] }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row['ce_close'],2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row['pe_close'],2) }}</td>
                        <td class="px-4 py-3 text-center">{{ (int) $row['pe_strike'] }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row['diff'],2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row['min_resistance'],2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row['min_support'],2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row['sum_ce_pe'],2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row['max_resistance'],2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row['max_support'],2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @if(empty($pairs))
                <div class="p-4 text-center text-red-600 font-semibold">
                    No CE/PE pairing data found for this session.
                </div>
            @endif
        </div>

        <h2 class="text-lg font-semibold mb-2">Pairing: PE → Nearest CE</h2>
        <div class="overflow-x-auto shadow rounded mb-8">
            <table class="min-w-full bg-white border border-gray-300 text-sm">
                <thead>
                <tr class="bg-slate-200 text-gray-900 font-semibold">
                    <th class="px-4 py-3 text-center">PE Strike</th>
                    <th class="px-4 py-3 text-right">PE Close</th>
                    <th class="px-4 py-3 text-right">CE Close</th>
                    <th class="px-4 py-3 text-center">CE Strike</th>
                    <th class="px-4 py-3 text-right">|PE-CE|</th>
                    <th class="px-4 py-3 text-right">Min Resistance</th>
                    <th class="px-4 py-3 text-right">Min Support</th>
                    <th class="px-4 py-3 text-right">CE+PE</th>
                    <th class="px-4 py-3 text-right">Max Resistance</th>
                    <th class="px-4 py-3 text-right">Max Support</th>
                </tr>
                </thead>
                <tbody>
                @foreach($reversePairs as $i => $row)
                    <tr class="{{ $i % 2 == 0 ? 'bg-gray-50' : 'bg-white' }} hover:bg-indigo-50 transition-colors border-b border-gray-200">
                        <td class="px-4 py-3 text-center">{{ (int) $row['pe_strike'] }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row['pe_close'], 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row['ce_close'], 2) }}</td>
                        <td class="px-4 py-3 text-center">{{ (int) $row['ce_strike'] }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row['diff'], 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row['min_resistance'], 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row['min_support'], 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row['sum_ce_pe'], 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row['max_resistance'], 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row['max_support'], 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @if(empty($reversePairs))
                <div class="p-4 text-center text-red-600 font-semibold">
                    No PE/CE pairing data found for this session.
                </div>
            @endif
        </div>

    </div>
@endsection
