@extends('layouts.app')

@section('title', 'Mid-Point Analysis')

@section('content')
<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 bg-white border-b border-gray-200">
            <h2 class="text-2xl font-bold mb-4">Mid-Point Analysis Filter</h2>
            <form method="GET" action="{{ route('mid-point.index') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="from_date" class="block text-sm font-medium text-gray-700">From Date & Time</label>
                        <input type="text" name="from_date" id="from_date" value="{{ $fromDate }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" placeholder="YYYY-MM-DD HH:mm:ss">
                    </div>
                    <div>
                        <label for="to_date" class="block text-sm font-medium text-gray-700">To Date & Time</label>
                        <input type="text" name="to_date" id="to_date" value="{{ $toDate }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" placeholder="YYYY-MM-DD HH:mm:ss">
                    </div>
                    <div>
                        <label for="expiry_date" class="block text-sm font-medium text-gray-700">Expiry Date</label>
                       <input type="date" name="expiry_date" value="{{$expiryDate}}" />
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150">
                        Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if($midPoint > 0 && $startStrike > 0)
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 flex flex-col justify-center items-center">
            <h3 class="text-lg font-semibold text-gray-700">Mid Point</h3>
            <p class="text-3xl font-bold text-blue-600">{{ $midPoint }}</p>
        </div>
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 flex flex-col justify-center items-center">
            <h3 class="text-lg font-semibold text-gray-700">Start Strike (Raw)</h3>
            <p class="text-3xl font-bold text-gray-800">{{ $startStrikeRaw }}</p>
        </div>
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 flex flex-col justify-center items-center">
            <h3 class="text-lg font-semibold text-gray-700">Start Strike (Rounded)</h3>
            <p class="text-3xl font-bold text-green-600">{{ $startStrike }}</p>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-800">Strikes Option Chain (< {{ $midPoint }})</h3>
            <div>
                <span class="px-3 py-1 mr-2 rounded-full text-xs font-semibold {{ count($ceStrikes) >= 6 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    CE Count: {{ count($ceStrikes) }}
                </span>
                <span class="px-3 py-1 rounded-full text-xs font-semibold {{ count($peStrikes) >= 6 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    PE Count: {{ count($peStrikes) }}
                </span>
            </div>
        </div>
        <div class="p-0 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-center">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider w-1/3">CE Price</th>
                        <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider w-1/3 bg-gray-100">Strike</th>
                        <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider w-1/3">PE Price</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @if(isset($combinedStrikes) && count($combinedStrikes) > 0)
                        @foreach($combinedStrikes as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm {{ $row['ce_price'] !== '-' ? 'text-gray-900 font-semibold' : 'text-gray-400' }}">
                                    {{ $row['ce_price'] }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-800 bg-gray-50">
                                    {{ $row['strike'] }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm {{ $row['pe_price'] !== '-' ? 'text-gray-900 font-semibold' : 'text-gray-400' }}">
                                    {{ $row['pe_price'] }}
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500">No strikes found below mid-point.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    @else
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mt-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">
                        Could not find valid Daily Trend or Open values for the selected date. Mid Point: {{ $midPoint }}, Start Strike: {{ $startStrikeRaw }}
                    </p>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
