<?php

namespace App\Services;

use Carbon\Carbon;

class TimeframeResampler
{
    /**
     * Standardize interval input to minute integer.
     * Supported: 1, 3, 5, 15, 30, 60, 375 (1 Day)
     */
    public static function normalizeInterval(mixed $input): int
    {
        if (is_numeric($input)) {
            $val = (int)$input;
            if ($val <= 1) return 1;
            if ($val <= 3) return 3;
            if ($val <= 5) return 5;
            if ($val <= 15) return 15;
            if ($val <= 30) return 30;
            if ($val <= 60) return 60;
            return 375; // Day
        }

        $str = strtolower(trim((string)$input));
        return match ($str) {
            '1', '1m', '1min'       => 1,
            '3', '3m', '3min'       => 3,
            '5', '5m', '5min'       => 5,
            '15', '15m', '15min'    => 15,
            '30', '30m', '30min'    => 30,
            '60', '60m', '1h', '60min' => 60,
            '1d', 'day', 'daily', '1day', '375' => 375,
            default                 => 5,
        };
    }

    /**
     * Resample standard candles to target interval minutes.
     *
     * @param array $candles Array of candles with time_str (H:i), open, high, low, close, volume
     * @param int $targetMinutes Target timeframe in minutes (1, 3, 5, 15, 30, 60, 375)
     * @param string $date Date string (YYYY-MM-DD)
     * @return array Resampled candles
     */
    public static function resampleCandles(array $candles, int $targetMinutes, string $date): array
    {
        if (empty($candles) || $targetMinutes <= 1) {
            return $candles;
        }

        $marketStartMin = 9 * 60 + 15; // 09:15 = 555 minutes from midnight
        $marketEndMin   = 15 * 60 + 30; // 15:30 = 930 minutes from midnight

        $buckets = [];

        foreach ($candles as $c) {
            $timeStr = $c['time_str'] ?? null;
            if (!$timeStr) {
                if (!empty($c['ts_at'])) {
                    $timeStr = Carbon::parse($c['ts_at'])->format('H:i');
                } else {
                    continue;
                }
            }

            [$h, $m] = explode(':', $timeStr);
            $totalMin = ((int)$h * 60) + (int)$m;

            if ($totalMin < $marketStartMin || $totalMin >= $marketEndMin) {
                continue;
            }

            $diff = $totalMin - $marketStartMin;
            $bucketIndex = (int) floor($diff / $targetMinutes);
            $bucketStartMin = $marketStartMin + ($bucketIndex * $targetMinutes);

            if ($bucketStartMin >= $marketEndMin) {
                continue;
            }

            $bH = (int) floor($bucketStartMin / 60);
            $bM = $bucketStartMin % 60;
            $bucketTimeStr = sprintf('%02d:%02d', $bH, $bM);

            $buckets[$bucketTimeStr][] = $c;
        }

        $resampled = [];

        foreach ($buckets as $bTime => $group) {
            if (empty($group)) continue;

            $first = $group[0];
            $last  = end($group);

            $open  = round((float)$first['open'], 2);
            $close = round((float)$last['close'], 2);
            $high  = round((float)max(array_column($group, 'high')), 2);
            $low   = round((float)min(array_column($group, 'low')), 2);
            $vol   = (int)array_sum(array_column($group, 'volume'));

            $cTs = Carbon::parse("{$date} {$bTime}:00", 'Asia/Kolkata');

            $resampled[] = [
                'ts_at'     => $cTs->toDateTimeString(),
                'time_str'  => $bTime,
                'timestamp' => Carbon::parse($cTs->toDateTimeString(), 'UTC')->timestamp,
                'open'      => $open,
                'high'      => $high,
                'low'       => $low,
                'close'     => $close,
                'volume'    => $vol,
                'move_pts'  => round($close - $open, 2),
                'move_pct'  => $open > 0 ? round((($close - $open) / $open) * 100, 3) : 0,
                'color'     => $close >= $open ? 'green' : 'red',
            ];
        }

        return $resampled;
    }

    /**
     * Resample raw stock quotes into keyed bucket array: [$sym][$bucketTimeStr] = [open, high, low, close, volume]
     */
    public static function resampleStockQuotes(array $rawStockQuotesBySymbol, int $targetMinutes): array
    {
        if (empty($rawStockQuotesBySymbol)) {
            return [];
        }

        $marketStartMin = 9 * 60 + 15;
        $marketEndMin   = 15 * 60 + 30;

        $resampledMatrix = [];

        foreach ($rawStockQuotesBySymbol as $sym => $quotes) {
            $buckets = [];
            foreach ($quotes as $q) {
                $timeStr = is_array($q) ? ($q['time_str'] ?? null) : ($q->time_str ?? null);
                if (!$timeStr) {
                    $tsAt = is_array($q) ? ($q['ts_at'] ?? null) : ($q->ts_at ?? null);
                    if ($tsAt) {
                        $timeStr = Carbon::parse($tsAt, 'Asia/Kolkata')->format('H:i');
                    } else {
                        continue;
                    }
                }

                [$h, $m] = explode(':', $timeStr);
                $totalMin = ((int)$h * 60) + (int)$m;

                if ($totalMin < $marketStartMin || $totalMin >= $marketEndMin) {
                    continue;
                }

                $diff = $totalMin - $marketStartMin;
                $bucketIndex = (int) floor($diff / $targetMinutes);
                $bucketStartMin = $marketStartMin + ($bucketIndex * $targetMinutes);

                if ($bucketStartMin >= $marketEndMin) {
                    continue;
                }

                $bH = (int) floor($bucketStartMin / 60);
                $bM = $bucketStartMin % 60;
                $bucketTimeStr = sprintf('%02d:%02d', $bH, $bM);

                $buckets[$bucketTimeStr][] = $q;
            }

            foreach ($buckets as $bTime => $group) {
                if (empty($group)) continue;
                $first = $group[0];
                $last  = end($group);

                $open  = is_array($first) ? (float)$first['open'] : (float)$first->open;
                $close = is_array($last)  ? (float)$last['close'] : (float)$last->close;

                $highs = array_map(fn($item) => is_array($item) ? (float)$item['high'] : (float)$item->high, $group);
                $lows  = array_map(fn($item) => is_array($item) ? (float)$item['low']  : (float)$item->low,  $group);
                $vols  = array_map(fn($item) => is_array($item) ? (int)$item['volume'] : (int)$item->volume, $group);

                $resampledMatrix[$sym][$bTime] = [
                    'open'   => round($open, 2),
                    'high'   => round(max($highs), 2),
                    'low'    => round(min($lows), 2),
                    'close'  => round($close, 2),
                    'volume' => array_sum($vols),
                ];
            }
        }

        return $resampledMatrix;
    }
}
