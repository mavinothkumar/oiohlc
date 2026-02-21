{{-- resources/views/zones/index.blade.php --}}

@extends('layouts.app')

@section('content')
    <div class="max-w-7xl mx-auto px-4 py-6">
        <h1 class="text-2xl font-semibold mb-6">Volumetric Supply & Demand Zones</h1>

        {{-- Filter form here (same as before) --}}
        @include('zones._filters')

        <div class="bg-white shadow rounded-lg p-4 mt-4">
            <h2 class="text-lg font-medium mb-2">Chart</h2>

            @if (! $hasDate)
                <p class="text-sm text-yellow-700 bg-yellow-50 border border-yellow-200 rounded px-3 py-2">
                    Please select a date range to load OHLC data.
                </p>
            @elseif (! $expiryDate)
                <p class="text-sm text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2">
                    No expiry found for the selected date and instrument type.
                </p>
            @elseif ($ohlc->isEmpty())
                <p class="text-sm text-gray-500">
                    No OHLC data found for the selected filters and date range.
                </p>
            @else
                <p class="text-sm text-gray-700 mb-2">
                    Loaded {{ $ohlc->count() }} bars for expiry {{ $expiryDate }}.
                </p>

                {{-- Container --}}
                <div id="candlestick-chart" class="w-full h-80 border border-gray-200 rounded mb-4"></div>
                <div id="volume-profile" class="w-full h-64 border border-gray-200 rounded"></div>

                {{-- Highcharts CDN --}}
                <script src="https://code.highcharts.com/stock/highstock.js"></script>

                <script>
                    const ohlcData = @json($ohlc);

                    const candles = ohlcData.map(row => {
                        return [
                            new Date(row.timestamp).getTime(), // x
                            Number(row.open),                  // o
                            Number(row.high),                  // h
                            Number(row.low),                   // l
                            Number(row.close),                 // c
                        ];
                    });

                    const volumes = ohlcData.map(row => {
                        return [
                            new Date(row.timestamp).getTime(),
                            Number(row.volume),
                        ];
                    });

                    // Basic candlestick + volume chart
                    Highcharts.stockChart('candlestick-chart', {
                        rangeSelector: { selected: 1 },

                        title: { text: 'NIFTY OHLC' },

                        series: [
                            {
                                type: 'candlestick',
                                name: 'Price',
                                data: candles,
                            },
                            {
                                type: 'column',
                                name: 'Volume',
                                data: volumes,
                                yAxis: 1,
                            }
                        ],

                        yAxis: [
                            {
                                height: '70%',
                                resize: { enabled: true },
                            },
                            {
                                top: '72%',
                                height: '28%',
                                offset: 0
                            }
                        ],
                    });

                    // Simple volume profile (aggregated volume per price bucket)
                    function buildVolumeProfile(bars, bucketCount = 20) {
                        if (!bars.length) return [];

                        let minLow = Math.min(...bars.map(b => Number(b.low)));
                        let maxHigh = Math.max(...bars.map(b => Number(b.high)));

                        if (minLow === maxHigh) {
                            minLow *= 0.99;
                            maxHigh *= 1.01;
                        }

                        const range = maxHigh - minLow;
                        const bucketSize = range / bucketCount;

                        const buckets = [];
                        for (let i = 0; i < bucketCount; i++) {
                            const low = minLow + i * bucketSize;
                            const high = low + bucketSize;
                            buckets.push({ low, high, volume: 0 });
                        }

                        ohlcData.forEach(row => {
                            const barLow = Number(row.low);
                            const barHigh = Number(row.high);
                            const barVol = Number(row.volume) || 0;
                            if (barVol <= 0) return;

                            buckets.forEach(b => {
                                const overlapLow = Math.max(barLow, b.low);
                                const overlapHigh = Math.min(barHigh, b.high);
                                const overlapRange = overlapHigh - overlapLow;
                                const barRange = barHigh - barLow;

                                if (overlapRange > 0 && barRange > 0) {
                                    const overlapPct = overlapRange / barRange;
                                    b.volume += barVol * overlapPct;
                                }
                            });
                        });

                        return buckets;
                    }

                    const vpBuckets = buildVolumeProfile(ohlcData, 20);

                    // Render volume profile as horizontal bar chart (price on Y, volume on X)
                    Highcharts.chart('volume-profile', {
                        chart: { type: 'bar' },
                        title: { text: 'Volume Profile (approx)' },
                        xAxis: {
                            categories: vpBuckets.map(b => ((b.low + b.high) / 2).toFixed(2)),
                            title: { text: 'Price' }
                        },
                        yAxis: {
                            title: { text: 'Volume' }
                        },
                        series: [{
                            name: 'Volume',
                            data: vpBuckets.map(b => Math.round(b.volume)),
                        }],
                    });
                </script>

            @endif
        </div>
    </div>
@endsection
