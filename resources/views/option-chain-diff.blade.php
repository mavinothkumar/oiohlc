@extends('layouts.app')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <!-- Header Section -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-4">Option Chain - Change Analysis</h1>

            <!-- Filter Form -->
            <form method="GET" class="bg-white rounded-lg shadow p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Trading Symbol Dropdown -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Trading Symbol</label>
                        <select name="trading_symbol"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-white">
                            @foreach($tradingSymbols as $symbol => $label)
                                <option value="{{ $symbol }}" {{ $tradingSymbol === $symbol ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Expiry Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Date (Optional)</label>
                        <input type="date" name="expiry_date" value="{{ old('expiry_date') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data Date</label>
                        <input type="date" name="date" value="{{ $selectedDate ?? now()->toDateString() }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Submit Button -->
                    <div class="flex items-end">
                        <button type="submit"
                            class="w-full px-4 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700 transition">
                            Filter
                        </button>
                    </div>
                </div>
            </form>

            <!-- Display Info -->
            <div class="flex flex-wrap items-center space-x-6 text-sm text-gray-600">
                <div class="flex items-center">
                    <span class="font-semibold mr-2">Symbol:</span>
                    <span class="text-gray-900">{{ $tradingSymbol }}</span>
                </div>
                <div class="flex items-center">
                    <span class="font-semibold mr-2">Expiry:</span>
                    <span class="text-gray-900">{{ \Carbon\Carbon::parse($expiry)->format('d M Y') }}</span>
                </div>
                <div class="flex items-center">
                    <span class="font-semibold mr-2">Data Date:</span>
                    <span class="text-gray-900">{{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</span>
                </div>
                <div class="flex items-center">
                    <span class="font-semibold mr-2">Spot Price:</span>
                    <span class="text-blue-600 font-bold">₹{{ number_format($spotPrice, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Metric Tabs -->
        <div class="mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8" aria-label="Metric">
                    <button
                        onclick="switchMetric('oi')"
                        id="metric-tab-oi"
                        class="metric-tab-button border-b-2 border-blue-500 py-4 px-1 text-sm font-medium text-blue-600">
                        Diff OI Changes
                    </button>
                    <button
                        onclick="switchMetric('volume')"
                        id="metric-tab-volume"
                        class="metric-tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        Diff Volume Changes
                    </button>
                </nav>
            </div>
        </div>

        <!-- Diff OI Table -->
        <div id="oi-table" class="metric-content bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white">Diff OI Changes by Strike Price</h2>
                <p class="text-blue-100 text-sm mt-1">Top 10 changes in Open Interest with Price, OI & Build-up</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50">
                    <!-- Strike Price Row -->
                    <tr>
                        <th rowspan="2" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50 z-10 border-r-2 border-gray-300">
                            Rank
                        </th>
                        @foreach($strikes as $strike)
                            <th colspan="2" class="px-4 py-3 text-center text-xs font-medium text-gray-700 uppercase tracking-wider border-r-2 border-gray-300 bg-gray-100">
                                ₹{{ number_format($strike, 0) }}
                            </th>
                        @endforeach
                    </tr>
                    <!-- CE/PE Row -->
                    <tr>
                        @foreach($strikes as $strike)
                            <th class="px-3 py-2 text-center text-xs font-semibold text-green-700 uppercase tracking-wider bg-green-50">
                                CE
                            </th>
                            <th class="px-3 py-2 text-center text-xs font-semibold text-red-700 uppercase tracking-wider bg-red-50 border-r-2 border-gray-300">
                                PE
                            </th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($diffOiTable as $index => $row)
                        <tr class="hover:bg-blue-50">
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-900 sticky left-0 bg-white z-10 border-r-2 border-gray-300">
                                {{ $row['rank'] }}
                            </td>
                            @foreach($strikes as $strike)
                                @php
                                    $strikeKey = (string)intval($strike);
                                @endphp
                                    <!-- CE Cell -->
                                <td class="px-3 py-2 text-center border-l border-gray-200 bg-white">
                                    @php
                                        $ceValue = $row['ce'][$strikeKey] ?? null;
                                    @endphp
                                    @if($ceValue !== null && $ceValue !== '-')
                                        @php
                                            $isPositive = $ceValue['diff_value'] > 0;
                                            $isNegative = $ceValue['diff_value'] < 0;
                                            $buildUpInfo = getBuildUpLabel($ceValue['build_up']);
                                        @endphp
                                        <div class="flex flex-col items-center gap-0.5 text-xs">
                                            <!-- Diff OI (Skip if 0) -->
                                            @if($ceValue['diff_value'] != 0)
                                                <span class="font-bold {{ $isPositive ? 'text-green-600' : 'text-red-600' }}">
                                        {{ number_format($ceValue['diff_value']) }}
                                    </span>
                                            @endif
                                            <!-- OI in Compact Format -->
                                            <span class="text-gray-700 font-semibold">{{ format_inr_compact($ceValue['oi']) }}</span>
                                            <!-- Price -->
                                            <span class="text-gray-600">₹{{ number_format($ceValue['ltp'], 2) }}</span>
                                            <!-- Time -->
                                            <span class="text-gray-500">{{ $ceValue['time'] }}</span>
                                            <!-- Build-up Badge -->
                                            @if($buildUpInfo)
                                                <span class="text-xs font-bold px-1.5 py-0.5 rounded whitespace-nowrap {{ $buildUpInfo['color'] }}">
                                        {{ $buildUpInfo['label'] }}
                                    </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-gray-300">-</span>
                                    @endif
                                </td>
                                <!-- PE Cell -->
                                <td class="px-3 py-2 text-center border-r-2 border-gray-300 bg-white">
                                    @php
                                        $peValue = $row['pe'][$strikeKey] ?? null;
                                    @endphp
                                    @if($peValue !== null && $peValue !== '-')
                                        @php
                                            $isPositive = $peValue['diff_value'] > 0;
                                            $isNegative = $peValue['diff_value'] < 0;
                                            $buildUpInfo = getBuildUpLabel($peValue['build_up']);
                                        @endphp
                                        <div class="flex flex-col items-center gap-0.5 text-xs">
                                            <!-- Diff OI (Skip if 0) -->
                                            @if($peValue['diff_value'] != 0)
                                                <span class="font-bold {{ $isPositive ? 'text-green-600' : 'text-red-600' }}">
                                        {{ number_format($peValue['diff_value']) }}
                                    </span>
                                            @endif
                                            <!-- OI in Compact Format -->
                                            <span class="text-gray-700 font-semibold">{{ format_inr_compact($peValue['oi']) }}</span>
                                            <!-- Price -->
                                            <span class="text-gray-600">₹{{ number_format($peValue['ltp'], 2) }}</span>
                                            <!-- Time -->
                                            <span class="text-gray-500">{{ $peValue['time'] }}</span>
                                            <!-- Build-up Badge -->
                                            @if($buildUpInfo)
                                                <span class="text-xs font-bold px-1.5 py-0.5 rounded whitespace-nowrap {{ $buildUpInfo['color'] }}">
                                        {{ $buildUpInfo['label'] }}
                                    </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-gray-300">-</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Diff Volume Table -->
        <div id="volume-table" class="metric-content bg-white rounded-lg shadow-lg overflow-hidden mb-8 hidden">
            <div class="bg-gradient-to-r from-purple-500 to-pink-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white">Diff Volume Changes by Strike Price</h2>
                <p class="text-purple-100 text-sm mt-1">Top 10 changes in Volume with Price, Volume & Build-up</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50">
                    <!-- Strike Price Row -->
                    <tr>
                        <th rowspan="2" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50 z-10 border-r-2 border-gray-300">
                            Rank
                        </th>
                        @foreach($strikes as $strike)
                            <th colspan="2" class="px-4 py-3 text-center text-xs font-medium text-gray-700 uppercase tracking-wider border-r-2 border-gray-300 bg-gray-100">
                                ₹{{ number_format($strike, 0) }}
                            </th>
                        @endforeach
                    </tr>
                    <!-- CE/PE Row -->
                    <tr>
                        @foreach($strikes as $strike)
                            <th class="px-3 py-2 text-center text-xs font-semibold text-green-700 uppercase tracking-wider bg-green-50">
                                CE
                            </th>
                            <th class="px-3 py-2 text-center text-xs font-semibold text-red-700 uppercase tracking-wider bg-red-50 border-r-2 border-gray-300">
                                PE
                            </th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($diffVolumeTable as $index => $row)
                        <tr class="hover:bg-purple-50">
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-900 sticky left-0 bg-white z-10 border-r-2 border-gray-300">
                                {{ $row['rank'] }}
                            </td>
                            @foreach($strikes as $strike)
                                @php
                                    $strikeKey = (string)intval($strike);
                                @endphp
                                    <!-- CE Cell -->
                                <td class="px-3 py-2 text-center border-l border-gray-200 bg-white">
                                    @php
                                        $ceValue = $row['ce'][$strikeKey] ?? null;
                                    @endphp
                                    @if($ceValue !== null && $ceValue !== '-')
                                        @php
                                            $isPositive = $ceValue['diff_value'] > 0;
                                            $isNegative = $ceValue['diff_value'] < 0;
                                            $buildUpInfo = getBuildUpLabel($ceValue['build_up']);
                                        @endphp
                                        <div class="flex flex-col items-center gap-0.5 text-xs">
                                            <!-- Diff Volume (Skip if 0) -->
                                            @if($ceValue['diff_value'] != 0)
                                                <span class="font-bold {{ $isPositive ? 'text-purple-600' : 'text-red-600' }}">
                                        {{ number_format($ceValue['diff_value']) }}
                                    </span>
                                            @endif
                                            <!-- Volume in Compact Format -->
                                            <span class="text-gray-700 font-semibold">{{ format_inr_compact($ceValue['volume']) }}</span>
                                            <!-- Price -->
                                            <span class="text-gray-600">₹{{ number_format($ceValue['ltp'], 2) }}</span>
                                            <!-- Time -->
                                            <span class="text-gray-500">{{ $ceValue['time'] }}</span>
                                            <!-- Build-up Badge -->
                                            @if($buildUpInfo)
                                                <span class="text-xs font-bold px-1.5 py-0.5 rounded whitespace-nowrap {{ $buildUpInfo['color'] }}">
                                        {{ $buildUpInfo['label'] }}
                                    </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-gray-300">-</span>
                                    @endif
                                </td>
                                <!-- PE Cell -->
                                <td class="px-3 py-2 text-center border-r-2 border-gray-300 bg-white">
                                    @php
                                        $peValue = $row['pe'][$strikeKey] ?? null;
                                    @endphp
                                    @if($peValue !== null && $peValue !== '-')
                                        @php
                                            $isPositive = $peValue['diff_value'] > 0;
                                            $isNegative = $peValue['diff_value'] < 0;
                                            $buildUpInfo = getBuildUpLabel($peValue['build_up']);
                                        @endphp
                                        <div class="flex flex-col items-center gap-0.5 text-xs">
                                            <!-- Diff Volume (Skip if 0) -->
                                            @if($peValue['diff_value'] != 0)
                                                <span class="font-bold {{ $isPositive ? 'text-purple-600' : 'text-red-600' }}">
                                        {{ number_format($peValue['diff_value']) }}
                                    </span>
                                            @endif
                                            <!-- Volume in Compact Format -->
                                            <span class="text-gray-700 font-semibold">{{ format_inr_compact($peValue['volume']) }}</span>
                                            <!-- Price -->
                                            <span class="text-gray-600">₹{{ number_format($peValue['ltp'], 2) }}</span>
                                            <!-- Time -->
                                            <span class="text-gray-500">{{ $peValue['time'] }}</span>
                                            <!-- Build-up Badge -->
                                            @if($buildUpInfo)
                                                <span class="text-xs font-bold px-1.5 py-0.5 rounded whitespace-nowrap {{ $buildUpInfo['color'] }}">
                                        {{ $buildUpInfo['label'] }}
                                    </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-gray-300">-</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Legend -->
        <div class="mt-8 bg-gray-50 rounded-lg p-4 border border-gray-200">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Build-up Legend:</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold px-2 py-1 rounded bg-blue-200 text-blue-800">LB</span>
                    <span class="text-sm text-gray-700">Long Build</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold px-2 py-1 rounded bg-orange-200 text-orange-800">SB</span>
                    <span class="text-sm text-gray-700">Short Build</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold px-2 py-1 rounded bg-green-200 text-green-800">SC</span>
                    <span class="text-sm text-gray-700">Short Cover</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold px-2 py-1 rounded bg-red-200 text-red-800">LU</span>
                    <span class="text-sm text-gray-700">Long Unwind</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchMetric(metric) {
            const metricTabButtons = document.querySelectorAll('.metric-tab-button');
            const metricContents = document.querySelectorAll('.metric-content');

            metricTabButtons.forEach(button => {
                button.classList.remove('border-blue-500', 'text-blue-600');
                button.classList.add('border-transparent', 'text-gray-500');
            });

            metricContents.forEach(content => {
                content.classList.add('hidden');
            });

            const activeButton = document.getElementById(`metric-tab-${metric}`);
            activeButton.classList.remove('border-transparent', 'text-gray-500');
            activeButton.classList.add('border-blue-500', 'text-blue-600');

            const activeContent = document.getElementById(`${metric}-table`);
            activeContent.classList.remove('hidden');
        }
    </script>
@endsection
