<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShortStrangleController extends Controller
{
    public function index()
    {
        $currentDate = now()->toDateString();
        $currentExpiry = $this->getCurrentExpiry();
        $currentSpot = $this->getCurrentSpot();
        $openPrice = $this->getOpenPrice($currentDate, $currentExpiry);

        return view('short-strangle', compact(
            'currentDate',
            'currentExpiry',
            'currentSpot',
            'openPrice'
        ));
    }

    public function getStrangleData(Request $request)
    {
        $date = $request->input('date', now()->toDateString());
        $expiry = $request->input('expiry', $this->getCurrentExpiry());
        $openPrice = $request->input('open_price', $this->getOpenPrice($date, $expiry));

        $currentPrice = $this->getCurrentPrice($date, $expiry);
        $openPrice = floatval($openPrice);

        // Generate strangle legs based on open price
        $strangleLegs = $this->generateStrangleLegs($date, $expiry, $openPrice, $currentPrice);

        // Find best entry with specific strikes
        $bestEntry = $this->findBestEntry($date, $expiry, $openPrice, $currentPrice, $strangleLegs);

        return response()->json([
            'strangle_legs' => $strangleLegs,
            'best_entry' => $bestEntry,
            'current_price' => round($currentPrice, 2),
            'open_price' => round($openPrice, 2),
            'expiry' => $expiry,
            'date' => $date,
        ]);
    }

    private function generateStrangleLegs($date, $expiry, $openPrice, $currentPrice)
    {
        // Round open price to nearest 100
        $baseStrike = round($openPrice / 100) * 100;

        $legs = [];
        $distances = [50, 100, 150, 200, 250, 300, 350, 400, 450, 500];

        foreach ($distances as $distance) {
            $ceStrike = $baseStrike + $distance;
            $peStrike = $baseStrike - $distance;

            $ceData = $this->getOptionData($date, $expiry, $ceStrike, 'CE');
            $peData = $this->getOptionData($date, $expiry, $peStrike, 'PE');

            $totalPremium = $ceData['premium'] + $peData['premium'];
            $totalTheta = $ceData['theta'] + $peData['theta'];
            $premiumDiff = abs($ceData['premium'] - $peData['premium']);

            // Calculate safety score (higher = safer)
            $safetyScore = $this->calculateSafetyScore($ceData, $peData, $currentPrice, $openPrice, $distance);

            $legs[] = [
                'distance' => $distance,
                'ce_strike' => $ceStrike,
                'pe_strike' => $peStrike,
                'ce_premium' => round($ceData['premium'], 2),
                'pe_premium' => round($peData['premium'], 2),
                'ce_delta' => round($ceData['delta'], 4),
                'pe_delta' => round($peData['delta'], 4),
                'ce_theta' => round($ceData['theta'], 4),
                'pe_theta' => round($peData['theta'], 4),
                'total_premium' => round($totalPremium, 2),
                'total_theta' => round($totalTheta, 4),
                'premium_diff' => round($premiumDiff, 2),
                'ce_oi' => $ceData['oi'],
                'pe_oi' => $peData['oi'],
                'safety_score' => round($safetyScore, 1),
                'is_base' => ($distance == 50) ? true : false,
            ];
        }

        return $legs;
    }

    private function getOptionData($date, $expiry, $strike, $type)
    {
        $record = DB::table('option_chains')
                    ->select('bid_price', 'ltp', 'delta', 'theta', 'oi', 'volume')
                    ->whereDate('captured_at', $date)
                    ->where('expiry', $expiry)
                    ->where('strike_price', $strike)
                    ->where('option_type', $type)
                    ->orderBy('captured_at', 'desc')
                    ->first();

        return [
            'premium' => $record->bid_price ?? $record->ltp ?? 0,
            'delta' => $record->delta ?? 0,
            'theta' => $record->theta ?? 0,
            'oi' => $record->oi ?? 0,
            'volume' => $record->volume ?? 0,
        ];
    }

    private function calculateSafetyScore($ceData, $peData, $currentPrice, $openPrice, $distance)
    {
        $score = 0;

        // 1. Distance from current price (farther = safer)
        $ceDistance = abs($currentPrice - (round($openPrice / 100) * 100 + $distance));
        $peDistance = abs($currentPrice - (round($openPrice / 100) * 100 - $distance));
        $avgDistance = ($ceDistance + $peDistance) / 2;
        $score += min($avgDistance / 100, 3) * 10;

        // 2. Delta safety (lower delta = safer)
        $deltaSafety = 1 - (abs($ceData['delta']) + abs($peData['delta'])) / 2;
        $score += $deltaSafety * 30;

        // 3. Theta (higher theta = better)
        $thetaScore = min(abs($ceData['theta'] + $peData['theta']) / 20, 1) * 20;
        $score += $thetaScore;

        // 4. Premium balance (balanced premiums = safer)
        $premiumDiff = abs($ceData['premium'] - $peData['premium']);
        $premiumBalance = 1 - ($premiumDiff / max($ceData['premium'] + $peData['premium'], 1));
        $score += $premiumBalance * 20;

        // 5. OI liquidity (higher = safer)
        $oiScore = min(($ceData['oi'] + $peData['oi']) / 2000000, 1) * 20;
        $score += $oiScore;

        return min($score, 100);
    }

    private function findBestEntry($date, $expiry, $openPrice, $currentPrice, $legs)
    {
        // Get market data for the day
        $candles = $this->getCandleData($date, $expiry);

        $entryTime = '10:15';
        $confidence = 60;
        $reason = 'Market settling after open';
        $bestLeg = null;

        // Find the best leg based on safety score
        if (!empty($legs)) {
            usort($legs, function($a, $b) {
                return $b['safety_score'] - $a['safety_score'];
            });
            $bestLeg = $legs[0];
        }

        // Check if market has moved significantly from open
        $priceChange = $currentPrice - $openPrice;
        $absChange = abs($priceChange);

        if ($absChange > 100) {
            $entryTime = '11:00';
            $confidence = 75;
            $reason = 'Significant move detected, waiting for stabilization';
        } elseif ($absChange > 50) {
            $entryTime = '10:30';
            $confidence = 70;
            $reason = 'Moderate move detected, waiting for stabilization';
        }

        // Check last 3 candles for decreasing volatility
        $lastCandles = array_slice($candles, -3);
        if (count($lastCandles) >= 3) {
            $volatility = [];
            foreach ($lastCandles as $candle) {
                $volatility[] = $candle['high'] - $candle['low'];
            }
            if ($volatility[2] < $volatility[0]) {
                $confidence += 10;
                $reason = 'Volatility decreasing, good entry';
            }
        }

        return [
            'entry_time' => $entryTime,
            'reason' => $reason,
            'confidence' => min($confidence, 95),
            'best_ce_strike' => $bestLeg ? $bestLeg['ce_strike'] : null,
            'best_pe_strike' => $bestLeg ? $bestLeg['pe_strike'] : null,
            'best_ce_premium' => $bestLeg ? $bestLeg['ce_premium'] : 0,
            'best_pe_premium' => $bestLeg ? $bestLeg['pe_premium'] : 0,
            'best_total_premium' => $bestLeg ? $bestLeg['total_premium'] : 0,
            'best_safety' => $bestLeg ? $bestLeg['safety_score'] : 0,
        ];
    }

    private function getCandleData($date, $expiry)
    {
        $prices = DB::table('option_chains')
                    ->select(
                        DB::raw('DATE_FORMAT(captured_at, "%H:%i") as time'),
                        'underlying_spot_price',
                        'captured_at'
                    )
                    ->whereDate('captured_at', $date)
                    ->where('expiry', $expiry)
                    ->orderBy('captured_at', 'asc')
                    ->get()
                    ->unique('time');

        if ($prices->isEmpty()) return [];

        $candles = [];
        $candleInterval = 15;
        $candleData = [];

        foreach ($prices as $price) {
            $minute = Carbon::parse($price->captured_at)->minute;
            $candleKey = floor($minute / $candleInterval);

            if (!isset($candleData[$candleKey])) {
                $candleData[$candleKey] = [
                    'open' => $price->underlying_spot_price,
                    'high' => $price->underlying_spot_price,
                    'low' => $price->underlying_spot_price,
                    'close' => $price->underlying_spot_price,
                    'time' => $price->time,
                ];
            } else {
                $candleData[$candleKey]['high'] = max($candleData[$candleKey]['high'], $price->underlying_spot_price);
                $candleData[$candleKey]['low'] = min($candleData[$candleKey]['low'], $price->underlying_spot_price);
                $candleData[$candleKey]['close'] = $price->underlying_spot_price;
            }
        }

        return array_values($candleData);
    }

    private function roundToNearestStrike($price)
    {
        return round($price / 50) * 50;
    }

    private function getCurrentExpiry()
    {
        return DB::table('nse_expiries')
                 ->where('is_current', 1)
                 ->where('instrument_type', 'OPT')
                 ->where('trading_symbol', 'NIFTY')
                 ->value('expiry_date');
    }

    private function getCurrentSpot()
    {
        $expiry = $this->getCurrentExpiry();
        return DB::table('option_chains')
                 ->where('expiry', $expiry)
                 ->whereDate('captured_at', now()->toDateString())
                 ->orderBy('captured_at', 'desc')
                 ->value('underlying_spot_price') ?? 23400;
    }

    private function getOpenPrice($date, $expiry)
    {
        return DB::table('daily_trend')
                 ->where('symbol_name', 'NIFTY')
                 ->whereDate('trading_date', $date)
                 ->value('current_day_index_open') ?? 23400;
    }

    private function getCurrentPrice($date, $expiry)
    {
        $record = DB::table('option_chains')
                    ->select('underlying_spot_price')
                    ->whereDate('captured_at', $date)
                    ->where('expiry', $expiry)
                    ->orderBy('captured_at', 'desc')
                    ->first();

        return $record->underlying_spot_price ?? $this->getCurrentSpot();
    }
}
