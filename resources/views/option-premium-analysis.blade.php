@extends('layouts.app')

@section('title', 'Option Premium Analysis')

@section('filters')
    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Filters</h2>
            @if($current_day_index_open)
                <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-2 rounded-md shadow-sm font-medium">
                    Nifty Open Price: <span class="font-bold text-blue-900">{{ number_format($current_day_index_open, 2) }}</span>
                </div>
            @endif
        </div>
        <form action="{{ route('option.premium.analysis') }}" method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <div>
                <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                <input type="date" name="date" id="date" value="{{ $date }}" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            
            <div>
                <label for="time" class="block text-sm font-medium text-gray-700 mb-1">Time</label>
                <input type="time" name="time" id="time" value="{{ $time }}" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>

            <div>
                <label for="expiry" class="block text-sm font-medium text-gray-700 mb-1">Expiry</label>
                <select name="expiry" id="expiry" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    @foreach($expiries as $exp)
                        <option value="{{ $exp }}" {{ $exp == $expiry ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::parse($exp)->format('d-M-Y') }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="ce_strike" class="block text-sm font-medium text-gray-700 mb-1">CE Strike</label>
                <input type="number" name="ce_strike" id="ce_strike" value="{{ $ce_strike }}" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>

            <div>
                <label for="pe_strike" class="block text-sm font-medium text-gray-700 mb-1">PE Strike</label>
                <input type="number" name="pe_strike" id="pe_strike" value="{{ $pe_strike }}" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>

            <div class="flex items-end">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-md shadow-sm transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Analyze
                </button>
            </div>
        </form>
    </div>
@endsection

@section('content')
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-800">Premium Analysis</h3>
            <span class="text-sm text-gray-500">Based on requested strikes</span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left font-semibold text-gray-700 uppercase tracking-wider">
                            Distance
                        </th>
                        <th scope="col" class="px-6 py-3 text-right font-semibold text-gray-700 uppercase tracking-wider">
                            CE Premium
                        </th>
                        <th scope="col" class="px-6 py-3 text-center font-semibold text-gray-700 uppercase tracking-wider">
                            CE Strike
                        </th>
                        <th scope="col" class="px-6 py-3 text-center font-semibold text-gray-700 uppercase tracking-wider">
                            PE Strike
                        </th>
                        <th scope="col" class="px-6 py-3 text-right font-semibold text-gray-700 uppercase tracking-wider">
                            PE Premium
                        </th>
                        <th scope="col" class="px-6 py-3 text-right font-semibold text-indigo-700 uppercase tracking-wider">
                            Total Premium
                        </th>
                        <th scope="col" class="px-6 py-3 text-right font-semibold text-green-700 uppercase tracking-wider">
                            Prem. Diff
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($data as $index => $row)
                        <tr class="hover:bg-gray-50 transition-colors duration-150 {{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                            <td class="px-6 py-4 whitespace-nowrap text-left font-medium text-gray-900">
                                {{ $row['distance'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-gray-700 font-medium">
                                {{ number_format($row['ce_premium'], 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center font-bold text-red-600 bg-red-50/30">
                                {{ $row['ce_strike'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center font-bold text-green-600 bg-green-50/30">
                                {{ $row['pe_strike'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-gray-700 font-medium">
                                {{ number_format($row['pe_premium'], 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-bold text-indigo-600 bg-indigo-50/30">
                                {{ number_format($row['total_premium'], 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-bold text-gray-900">
                                {{ number_format($row['premium_difference'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                No data available. Please select valid strikes and criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
