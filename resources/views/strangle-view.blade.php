@extends('layouts.app')

@section('title', 'Strangle View')

@section('content')
    <div class="container mx-auto px-4 py-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-blue-600">
                    📊 Strangle View
                </h1>
                <p class="text-gray-600 text-sm">View OTM strikes based on open price</p>
            </div>
            <div class="flex items-center space-x-2">
            <span class="px-3 py-1 bg-gray-100 rounded-full text-xs font-medium text-gray-700" id="current-time">
                {{ now()->format('H:i:s') }}
            </span>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="bg-white rounded-xl p-4 mb-6 border border-gray-200 shadow-sm">
            <form method="GET" action="{{ route('trading.strangle.view') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <input type="date" name="date" value="{{ $date }}"
                        class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Time</label>
                    <input type="time" name="time" value="{{ $time }}"
                        step="60"
                        class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Expiry</label>
                    <input type="date" name="expiry" value="{{ $expiry }}"
                        class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Open Price</label>
                    <input type="number" name="open_price" value="{{ $openPrice }}"
                        step="0.05"
                        class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div class="flex items-end">
                    <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors w-full">
                        <i class="fas fa-sync-alt mr-2"></i> Load
                    </button>
                </div>
            </form>

            <div class="mt-3 text-xs text-gray-500 flex flex-wrap gap-4">
                <span>NIFTY: <strong class="text-blue-600">{{ $currentPrice }}</strong></span>
                <span>Open: <strong class="text-gray-800">{{ $openPrice }}</strong></span>
                <span>Base: <strong class="text-purple-600">{{ round($openPrice / 100) * 100 }}</strong></span>
                <span>Time: <strong class="text-gray-600">{{ $time }}</strong></span>
            </div>
        </div>

        <!-- Strangle Legs Table -->
        <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm overflow-x-auto">
            <div class="flex justify-between items-center mb-3">
                <h2 class="text-lg font-semibold text-gray-800">Strangle Legs</h2>
                <span class="text-xs text-gray-500">{{ count($strangleLegs) }} legs found</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[800px]">
                    <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="py-2 px-3 text-left font-semibold text-gray-700">Dist</th>
                        <th class="py-2 px-3 text-right font-semibold text-red-600">PE Premium</th>
                        <th class="py-2 px-3 text-center font-semibold text-gray-700">Strike</th>
                        <th class="py-2 px-3 text-right font-semibold text-green-600">CE Premium</th>
                        <th class="py-2 px-3 text-right font-semibold text-blue-600">Total Premium</th>
                        <th class="py-2 px-3 text-right font-semibold text-orange-500">Premium Diff</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($strangleLegs as $leg)
                        <tr class="bg-white hover:bg-gray-50 transition-colors border-b border-gray-100">
                            <td class="py-2 px-3 text-left text-gray-700">{{ $leg['distance'] }}</td>
                            <td class="py-2 px-3 text-right font-medium text-red-600">{{ $leg['pe_premium'] }}</td>
                            <td class="py-2 px-3 text-center font-bold text-gray-800">{{ $leg['pe_strike'] }} / {{ $leg['ce_strike'] }}</td>
                            <td class="py-2 px-3 text-right font-medium text-green-600">{{ $leg['ce_premium'] }}</td>
                            <td class="py-2 px-3 text-right font-bold text-blue-600">{{ $leg['total_premium'] }}</td>
                            <td class="py-2 px-3 text-right font-bold text-orange-500">{{ $leg['premium_diff'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-gray-500">No data found for this timestamp</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
