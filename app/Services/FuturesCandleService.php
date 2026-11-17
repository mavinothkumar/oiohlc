<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FuturesCandleService
{
    /**
     * Resolve the active or applicable nearest-month futures instrument from the instruments table.
     */
    public static function resolveFuturesInstrument(string $symbol, ?string $date = null): ?object
    {
        $symbolUpper = strtoupper($symbol);
        $underlying = (str_contains($symbolUpper, 'BANK') || str_contains($symbolUpper, 'BANKNIFTY')) ? 'BANKNIFTY' : 'NIFTY';

        $nowKolkata = Carbon::now('Asia/Kolkata');
        $todayStr = $nowKolkata->toDateString();
        $isToday = (!$date || $date === $todayStr);

        if ($isToday) {
            $nowMs = time() * 1000;
            $instrument = DB::table('instruments')
                ->where('underlying_symbol', $underlying)
                ->where('instrument_type', 'FUT')
                ->where('expiry', '>=', $nowMs)
                ->orderBy('expiry', 'asc')
                ->first(['instrument_key', 'trading_symbol', 'expiry']);

            if ($instrument) {
                return $instrument;
            }
        }

        // For historical dates or fallback: find contract active on target date
        if ($date) {
            try {
                $dateStartMs = Carbon::parse($date, 'Asia/Kolkata')->startOfDay()->timestamp * 1000;
                $instrument = DB::table('instruments')
                    ->where('underlying_symbol', $underlying)
                    ->where('instrument_type', 'FUT')
                    ->where('expiry', '>=', $dateStartMs)
                    ->orderBy('expiry', 'asc')
                    ->first(['instrument_key', 'trading_symbol', 'expiry']);

                if ($instrument) {
                    return $instrument;
                }
            } catch (\Throwable $e) {
                Log::warning("FuturesCandleService: Failed parsing date {$date} for {$underlying}: " . $e->getMessage());
            }
        }

        // Ultimate fallback: closest future instrument by expiry
        return DB::table('instruments')
            ->where('underlying_symbol', $underlying)
            ->where('instrument_type', 'FUT')
            ->orderBy('expiry', 'desc')
            ->first(['instrument_key', 'trading_symbol', 'expiry']);
    }

    /**
     * Load real 1-minute futures candles for a given date and resample to target timeframe interval.
     *
     * @param string $symbol 'NIFTY' or 'BANKNIFTY'
     * @param string $date Y-m-d format
     * @param int $interval Timeframe in minutes (1, 3, 5, 15, 30, 60, 375)
     * @return array Resampled candlestick array
     */
    public static function loadCandlesForDate(string $symbol, string $date, int $interval = 5): array
    {
        $symbolUpper = strtoupper($symbol);
        $underlying = (str_contains($symbolUpper, 'BANK') || str_contains($symbolUpper, 'BANKNIFTY')) ? 'BANKNIFTY' : 'NIFTY';
        $normalizedInterval = TimeframeResampler::normalizeInterval($interval);

        $nowKolkata = Carbon::now('Asia/Kolkata');
        $todayStr = $nowKolkata->toDateString();
        $isToday = ($date === $todayStr);
        $curTimeStr = $nowKolkata->format('H:i');
        $isWeekday = !in_array($nowKolkata->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]);
        $isMarketHours = ($isToday && $isWeekday && $curTimeStr >= '09:15' && $curTimeStr <= '15:30');

        $cacheTtl = $isToday ? ($isMarketHours ? 15 : 30) : 86400;
        $cacheKey = "fut_candles_{$underlying}_{$date}_{$normalizedInterval}";

        return Cache::store('file')->remember($cacheKey, $cacheTtl, function () use ($underlying, $date, $normalizedInterval, $isToday, $isMarketHours, $curTimeStr, $isWeekday) {
            $futInstrument = self::resolveFuturesInstrument($underlying, $date);
            if (!$futInstrument || empty($futInstrument->instrument_key)) {
                Log::warning("FuturesCandleService: No futures instrument found for {$underlying} on {$date}");
                return [];
            }

            $token = config('services.upstox.analytics_token') ?: config('services.upstox.access_token');
            if (!$token) {
                Log::warning("FuturesCandleService: Missing Upstox access token");
                return [];
            }

            $encodedKey = rawurlencode($futInstrument->instrument_key);

            // Fetch from Upstox
            try {
                if ($isToday) {
                    $url = "https://api.upstox.com/v2/historical-candle/intraday/{$encodedKey}/1minute";
                } else {
                    $url = "https://api.upstox.com/v2/historical-candle/{$encodedKey}/1minute/{$date}";
                }

                $resp = Http::withHeaders([
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ])->timeout(12)->get($url);

                if (!$resp->ok()) {
                    Log::warning("FuturesCandleService: Upstox API error {$resp->status()} for {$url}");
                    return [];
                }

                $upstoxCandles = $resp->json('data.candles') ?? [];
                if (empty($upstoxCandles)) {
                    return [];
                }

                // Upstox returns newest candles first; reverse to chronological order
                $chronological = array_reverse($upstoxCandles);
                $raw1m = [];
                $seenTimes = [];

                foreach ($chronological as $c) {
                    if (!isset($c[0], $c[1], $c[2], $c[3], $c[4])) continue;

                    $ts = Carbon::parse($c[0], 'Asia/Kolkata');
                    $candleDate = $ts->toDateString();
                    if ($candleDate !== $date) continue;

                    $timeStr = $ts->format('H:i');
                    if ($timeStr < '09:15' || $timeStr > '15:29') continue;
                    if ($isToday && $isWeekday && $curTimeStr <= '15:30' && $timeStr > $curTimeStr) continue;

                    if (isset($seenTimes[$timeStr])) continue;
                    $seenTimes[$timeStr] = true;

                    $open  = round((float)$c[1], 2);
                    $high  = round((float)$c[2], 2);
                    $low   = round((float)$c[3], 2);
                    $close = round((float)$c[4], 2);
                    $vol   = (int)($c[5] ?? 0);
                    $oi    = (int)($c[6] ?? 0);

                    $raw1m[] = [
                        'ts_at'     => $ts->toDateTimeString(),
                        'time_str'  => $timeStr,
                        'timestamp' => Carbon::parse($ts->toDateTimeString(), 'UTC')->timestamp,
                        'open'      => $open,
                        'high'      => $high,
                        'low'       => $low,
                        'close'     => $close,
                        'volume'    => $vol,
                        'oi'        => $oi,
                        'move_pts'  => round($close - $open, 2),
                        'move_pct'  => $open > 0 ? round((($close - $open) / $open) * 100, 3) : 0,
                        'color'     => $close >= $open ? 'green' : 'red',
                    ];
                }

                if (empty($raw1m)) {
                    return [];
                }

                if ($normalizedInterval === 1) {
                    return $raw1m;
                }

                return TimeframeResampler::resampleCandles($raw1m, $normalizedInterval, $date);
            } catch (\Throwable $e) {
                Log::error("FuturesCandleService: Exception while fetching candles: " . $e->getMessage());
                return [];
            }
        });
    }
}
