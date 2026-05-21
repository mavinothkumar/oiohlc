<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PremiumSellController extends Controller
{
    public function index()
    {
        $currentDate = now()->toDateString();
        $currentExpiry = $this->getCurrentExpiry();
        $currentSpot = $this->getCurrentSpot();
        $workingDates = $this->getWorkingDates();

        return view('premium-sell', compact(
            'currentDate',
            'currentExpiry',
            'currentSpot',
            'workingDates'
        ));
    }

    public function dynamicIndex()
    {
        $currentDate = now()->toDateString();
        $currentExpiry = $this->getCurrentExpiry();
        $currentSpot = $this->getCurrentSpot();
        $workingDates = $this->getWorkingDates();

        return view('dynamic-sell', compact(
            'currentDate',
            'currentExpiry',
            'currentSpot',
            'workingDates'
        ));
    }

    // ===== NEW: Straddle Index Method =====
    public function straddleIndex()
    {
        $currentDate = now()->toDateString();
        $currentExpiry = $this->getCurrentExpiry();
        $currentSpot = $this->getCurrentSpot();
        $workingDates = $this->getWorkingDates();

        // Find the strike with highest OI for straddle
        $straddleStrike = $this->findHighestOIStrike($currentDate, $currentExpiry, $currentSpot);

        return view('straddle-sell', compact(
            'currentDate',
            'currentExpiry',
            'currentSpot',
            'workingDates',
            'straddleStrike'
        ));
    }

    public function getPremiumSellData(Request $request)
    {
        $date = $request->input('date', now()->toDateString());
        $expiry = $request->input('expiry', $this->getCurrentExpiry());
        $strikeRange = $request->input('strike_range', 10);

        $currentSpot = $this->getPriceAtTime($date, $expiry, Carbon::now());
        $strikeStep = 50;
        $lowerStrike = $currentSpot - ($strikeRange * $strikeStep);
        $upperStrike = $currentSpot + ($strikeRange * $strikeStep);

        $strikeData = $this->getStrikeData($date, $expiry, $lowerStrike, $upperStrike, $currentSpot);
        $premiumData = $this->calculatePremiumSellOpportunities($strikeData, $currentSpot);

        return response()->json([
            'data' => $premiumData,
            'current_spot' => round($currentSpot, 2),
            'expiry' => $expiry,
            'date' => $date,
            'strike_range' => $strikeRange,
        ]);
    }

    public function getDynamicSellData(Request $request)
    {
        $date = $request->input('date', now()->toDateString());
        $expiry = $request->input('expiry', $this->getCurrentExpiry());
        $range = $request->input('range', 300);

        $marketData = $this->getMarketData($date, $expiry);
        $oiWalls = $this->findOIWalls($date, $expiry, $marketData['current_price']);
        $marketContext = $this->calculateMarketContext($date, $expiry, $marketData);
        $coolDownEntry = $this->detectCoolDownEntry($date, $expiry, $marketData, $marketContext);
        $bestStrikes = $this->getBestSellStrikes($date, $expiry, $marketData, $oiWalls, $range);

        return response()->json([
            'market_data' => $marketData,
            'oi_walls' => $oiWalls,
            'market_context' => $marketContext,
            'cool_down_entry' => $coolDownEntry,
            'best_strikes' => $bestStrikes,
            'current_spot' => round($marketData['current_price'], 2),
            'expiry' => $expiry,
            'date' => $date,
        ]);
    }

    // ===== NEW: Get Straddle Data Method =====
    public function getStraddleData(Request $request)
    {
        $date = $request->input('date', now()->toDateString());
        $expiry = $request->input('expiry', $this->getCurrentExpiry());
        $straddleStrike = $request->input('straddle_strike', 23800);
        $range = $request->input('range', 300);

        $marketData = $this->getMarketData($date, $expiry);
        $currentPrice = $marketData['current_price'];

        $peStrike = $straddleStrike - $range;
        $ceStrike = $straddleStrike + $range;

        $premiumData = $this->getStraddlePremiumData($date, $expiry, $ceStrike, $peStrike);
        $premiumDiff = $premiumData['ce_premium'] - $premiumData['pe_premium'];
        $absPremiumDiff = abs($premiumDiff);
        $avgPremiumDiff = $this->calculateAveragePremiumDiff($date, $expiry, $ceStrike, $peStrike);

        $isMismatch = $absPremiumDiff > $avgPremiumDiff * 1.5 || $absPremiumDiff > 20;

        $opportunity = $this->detectStraddleOpportunity(
            $marketData,
            $premiumData,
            $avgPremiumDiff,
            $straddleStrike,
            $currentPrice
        );

        // Get additional strike pairs for comparison
        $additionalPairs = $this->getAdditionalStrikePairs($date, $expiry, $straddleStrike, $currentPrice, $range);

        return response()->json([
            'market_data' => $marketData,
            'straddle_data' => [
                'straddle_strike' => $straddleStrike,
                'pe_strike' => $peStrike,
                'ce_strike' => $ceStrike,
                'pe_premium' => round($premiumData['pe_premium'], 2),
                'ce_premium' => round($premiumData['ce_premium'], 2),
                'pe_delta' => round($premiumData['pe_delta'], 4),
                'ce_delta' => round($premiumData['ce_delta'], 4),
                'pe_theta' => round($premiumData['pe_theta'], 4),
                'ce_theta' => round($premiumData['ce_theta'], 4),
                'pe_gamma' => round($premiumData['pe_gamma'], 6),
                'ce_gamma' => round($premiumData['ce_gamma'], 6),
                'pe_vega' => round($premiumData['pe_vega'], 4),
                'ce_vega' => round($premiumData['ce_vega'], 4),
                'pe_iv' => round($premiumData['pe_iv'], 2),
                'ce_iv' => round($premiumData['ce_iv'], 2),
                'pe_oi' => $premiumData['pe_oi'],
                'ce_oi' => $premiumData['ce_oi'],
                'pe_volume' => $premiumData['pe_volume'],
                'ce_volume' => $premiumData['ce_volume'],
                'premium_diff' => round($premiumDiff, 2),
                'abs_premium_diff' => round($absPremiumDiff, 2),
                'avg_premium_diff' => round($avgPremiumDiff, 2),
                'is_mismatch' => $isMismatch,
                'opportunity' => $opportunity,
            ],
            'additional_pairs' => $additionalPairs,
            'current_spot' => round($currentPrice, 2),
            'expiry' => $expiry,
            'date' => $date,
        ]);
    }

    private function getAdditionalStrikePairs($date, $expiry, $straddleStrike, $currentPrice, $range)
    {
        $pairs = [];
        $steps = [50, 100, 150, 200]; // Different distances from straddle

        foreach ($steps as $step) {
            $ceStrike = $straddleStrike + $step;
            $peStrike = $straddleStrike - $step;

            $premiumData = $this->getStraddlePremiumData($date, $expiry, $ceStrike, $peStrike);

            // Calculate premium difference
            $premiumDiff = $premiumData['ce_premium'] - $premiumData['pe_premium'];

            // Calculate risk score (higher = more risk of one leg moving too fast)
            $riskScore = $this->calculateRiskScore($premiumData, $currentPrice, $straddleStrike);

            $pairs[] = [
                'ce_strike' => $ceStrike,
                'pe_strike' => $peStrike,
                'ce_premium' => round($premiumData['ce_premium'], 2),
                'pe_premium' => round($premiumData['pe_premium'], 2),
                'ce_delta' => round($premiumData['ce_delta'], 4),
                'pe_delta' => round($premiumData['pe_delta'], 4),
                'ce_theta' => round($premiumData['ce_theta'], 4),
                'pe_theta' => round($premiumData['pe_theta'], 4),
                'premium_diff' => round($premiumDiff, 2),
                'ce_oi' => $premiumData['ce_oi'],
                'pe_oi' => $premiumData['pe_oi'],
                'risk_score' => $riskScore,
                'is_balanced' => $riskScore < 30, // Balanced if risk score is low
            ];
        }

        return $pairs;
    }

    private function calculateRiskScore($premiumData, $currentPrice, $straddleStrike)
    {
        $riskScore = 0;

        // 1. Check premium difference (larger difference = higher risk)
        $premiumDiff = abs($premiumData['ce_premium'] - $premiumData['pe_premium']);
        if ($premiumDiff > 100) {
            $riskScore += 40;
        } elseif ($premiumDiff > 50) {
            $riskScore += 20;
        } elseif ($premiumDiff > 30) {
            $riskScore += 10;
        }

        // 2. Check delta difference (one leg much closer to ITM = higher risk)
        $deltaDiff = abs(abs($premiumData['ce_delta']) - abs($premiumData['pe_delta']));
        if ($deltaDiff > 0.3) {
            $riskScore += 30;
        } elseif ($deltaDiff > 0.2) {
            $riskScore += 15;
        }

        // 3. Check distance from straddle strike
        $ceDistance = abs($currentPrice - ($straddleStrike + 50));
        $peDistance = abs($currentPrice - ($straddleStrike - 50));
        $distanceDiff = abs($ceDistance - $peDistance);
        if ($distanceDiff > 100) {
            $riskScore += 20;
        }

        // 4. OI imbalance (one side has much higher OI = higher risk)
        $oiDiff = abs($premiumData['ce_oi'] - $premiumData['pe_oi']);
        if ($oiDiff > 500000) {
            $riskScore += 10;
        }

        return min($riskScore, 100);
    }

    // ===== Helper Methods =====

    private function getCurrentExpiry()
    {
        return DB::table('nse_expiries')
                 ->where('is_current', 1)
                 ->where('instrument_type', 'OPT')
                 ->where('trading_symbol', 'NIFTY')
                 ->value('expiry_date');
    }

    private function getWorkingDates()
    {
        return DB::table('nse_working_days')
                 ->where('working_date', '>=', now()->subDays(30))
                 ->where('working_date', '<=', now())
                 ->orderBy('working_date', 'desc')
                 ->pluck('working_date')
                 ->toArray();
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

    private function getPriceAtTime($date, $expiry, $timestamp)
    {
        $record = DB::table('option_chains')
                    ->whereDate('captured_at', $date)
                    ->where('expiry', $expiry)
                    ->where('captured_at', '<=', $timestamp)
                    ->orderBy('captured_at', 'desc')
                    ->first();

        return $record ? $record->underlying_spot_price : $this->getCurrentSpot();
    }

    private function findHighestOIStrike($date, $expiry, $currentSpot)
    {
        $records = DB::table('option_chains')
                     ->select('strike_price', DB::raw('SUM(oi) as total_oi'))
                     ->whereDate('captured_at', $date)
                     ->where('expiry', $expiry)
                     ->whereBetween('strike_price', [$currentSpot - 200, $currentSpot + 200])
                     ->groupBy('strike_price')
                     ->orderBy('total_oi', 'desc')
                     ->first();

        return $records ? $records->strike_price : round($currentSpot / 50) * 50;
    }

    private function getStrikeData($date, $expiry, $lowerStrike, $upperStrike, $currentSpot)
    {
        $records = DB::table('option_chains')
                     ->select(
                         'strike_price',
                         'option_type',
                         'oi',
                         'volume',
                         'ltp',
                         'delta',
                         'theta',
                         'gamma',
                         'vega',
                         'iv',
                         'bid_price',
                         'ask_price',
                         'bid_qty',
                         'ask_qty',
                         'underlying_spot_price',
                         'pcr'
                     )
                     ->whereDate('captured_at', $date)
                     ->where('expiry', $expiry)
                     ->whereBetween('strike_price', [$lowerStrike, $upperStrike])
                     ->orderBy('captured_at', 'desc')
                     ->get();

        $grouped = [];
        foreach ($records as $record) {
            $strike = $record->strike_price;
            $type = $record->option_type;

            if (!isset($grouped[$strike])) {
                $grouped[$strike] = [
                    'ce' => null,
                    'pe' => null,
                ];
            }

            if ($type === 'CE') {
                $grouped[$strike]['ce'] = $record;
            } else {
                $grouped[$strike]['pe'] = $record;
            }
        }

        return $grouped;
    }

    private function calculatePremiumSellOpportunities($strikeData, $currentSpot)
    {
        $results = [];

        foreach ($strikeData as $strike => $data) {
            $ce = $data['ce'];
            $pe = $data['pe'];

            if (!$ce && !$pe) continue;

            $distance = abs($currentSpot - $strike);
            $ceStatus = $currentSpot > $strike ? 'ITM' : 'OTM';
            $peStatus = $currentSpot < $strike ? 'ITM' : 'OTM';

            $otmCE = ($ceStatus === 'OTM' && $ce) ? ($ce->oi ?? 0) : 0;
            $otmPE = ($peStatus === 'OTM' && $pe) ? ($pe->oi ?? 0) : 0;
            $totalOTM = $otmCE + $otmPE;
            $diffOTM = $otmCE - $otmPE;

            $ceSellScore = $this->calculateSellScore($ce, $currentSpot, $strike, 'CE');
            $peSellScore = $this->calculateSellScore($pe, $currentSpot, $strike, 'PE');

            $ceSellRightTime = $ceSellScore >= 70;
            $peSellRightTime = $peSellScore >= 70;

            $cePremium = $ce ? ($ce->bid_price ?? $ce->ltp) : 0;
            $pePremium = $pe ? ($pe->bid_price ?? $pe->ltp) : 0;

            $results[] = [
                'strike' => $strike,
                'atm_distance' => $distance,
                'ce_status' => $ceStatus,
                'pe_status' => $peStatus,
                'ce_oi' => $ce ? ($ce->oi ?? 0) : 0,
                'pe_oi' => $pe ? ($pe->oi ?? 0) : 0,
                'ce_volume' => $ce ? ($ce->volume ?? 0) : 0,
                'pe_volume' => $pe ? ($pe->volume ?? 0) : 0,
                'ce_premium' => round($cePremium, 2),
                'pe_premium' => round($pePremium, 2),
                'ce_delta' => $ce ? round($ce->delta ?? 0, 4) : 0,
                'pe_delta' => $pe ? round($pe->delta ?? 0, 4) : 0,
                'ce_theta' => $ce ? round($ce->theta ?? 0, 4) : 0,
                'pe_theta' => $pe ? round($pe->theta ?? 0, 4) : 0,
                'ce_gamma' => $ce ? round($ce->gamma ?? 0, 4) : 0,
                'pe_gamma' => $pe ? round($pe->gamma ?? 0, 4) : 0,
                'ce_vega' => $ce ? round($ce->vega ?? 0, 4) : 0,
                'pe_vega' => $pe ? round($pe->vega ?? 0, 4) : 0,
                'ce_iv' => $ce ? round($ce->iv ?? 0, 2) : 0,
                'pe_iv' => $pe ? round($pe->iv ?? 0, 2) : 0,
                'otm_ce' => $otmCE,
                'otm_pe' => $otmPE,
                'total_otm' => $totalOTM,
                'diff_otm' => $diffOTM,
                'ce_sell_score' => $ceSellScore,
                'pe_sell_score' => $peSellScore,
                'ce_sell_right_time' => $ceSellRightTime,
                'pe_sell_right_time' => $peSellRightTime,
                'best_sell' => $this->getBestSell($ceSellScore, $peSellScore, $cePremium, $pePremium),
            ];
        }

        usort($results, function($a, $b) {
            return $b['ce_sell_score'] - $a['ce_sell_score'];
        });

        return $results;
    }

    private function calculateSellScore($option, $currentSpot, $strike, $type)
    {
        if (!$option) return 0;

        $score = 0;
        $distance = abs($currentSpot - $strike);

        if ($distance > 100) {
            $score += 30;
        } elseif ($distance > 50) {
            $score += 20;
        } elseif ($distance > 25) {
            $score += 10;
        }

        $delta = abs($option->delta ?? 0);
        if ($delta < 0.2) {
            $score += 30;
        } elseif ($delta < 0.3) {
            $score += 20;
        } elseif ($delta < 0.4) {
            $score += 10;
        }

        $theta = abs($option->theta ?? 0);
        if ($theta > 10) {
            $score += 20;
        } elseif ($theta > 5) {
            $score += 10;
        }

        $oi = $option->oi ?? 0;
        if ($oi > 1000000) {
            $score += 10;
        } elseif ($oi > 500000) {
            $score += 5;
        }

        $volume = $option->volume ?? 0;
        if ($volume > 500000) {
            $score += 10;
        } elseif ($volume > 200000) {
            $score += 5;
        }

        return min($score, 100);
    }

    private function getBestSell($ceScore, $peScore, $cePremium, $pePremium)
    {
        if ($ceScore >= 70 && $peScore >= 70) {
            return 'BOTH';
        } elseif ($ceScore >= 70 && $cePremium > $pePremium) {
            return 'CE';
        } elseif ($peScore >= 70 && $pePremium > $cePremium) {
            return 'PE';
        } elseif ($ceScore > $peScore) {
            return 'CE';
        } elseif ($peScore > $ceScore) {
            return 'PE';
        } else {
            return 'NONE';
        }
    }

    private function getMarketData($date, $expiry)
    {
        $prices = DB::table('option_chains')
                    ->select(
                        DB::raw('DATE_FORMAT(captured_at, "%H:%i") as time'),
                        'underlying_spot_price',
                        'captured_at'
                    )
                    ->whereDate('captured_at', $date)
                    ->where('expiry', $expiry)
                    ->orderBy('captured_at', 'desc')
                    ->get()
                    ->unique('time');

        if ($prices->isEmpty()) {
            return [
                'current_price' => $this->getCurrentSpot(),
                'open_price' => 0,
                'high_price' => 0,
                'low_price' => 0,
                'candles' => [],
            ];
        }

        $first = $prices->last();
        $last = $prices->first();
        $high = $prices->max('underlying_spot_price');
        $low = $prices->min('underlying_spot_price');

        $candles = [];
        $candleInterval = 15;
        $candleData = [];

        $chronological = $prices->reverse();
        foreach ($chronological as $price) {
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

        $candles = array_values($candleData);
        usort($candles, function($a, $b) {
            return strcmp($a['time'], $b['time']);
        });

        return [
            'current_price' => $last->underlying_spot_price ?? $this->getCurrentSpot(),
            'open_price' => $first->underlying_spot_price ?? 0,
            'high_price' => $high ?? 0,
            'low_price' => $low ?? 0,
            'candles' => $candles,
        ];
    }

    private function findOIWalls($date, $expiry, $currentPrice)
    {
        $records = DB::table('option_chains')
                     ->select(
                         'strike_price',
                         'option_type',
                         'oi'
                     )
                     ->whereDate('captured_at', $date)
                     ->where('expiry', $expiry)
                     ->whereBetween('strike_price', [$currentPrice - 500, $currentPrice + 500])
                     ->orderBy('captured_at', 'desc')
                     ->get();

        $ceOI = [];
        $peOI = [];

        foreach ($records as $record) {
            if ($record->option_type === 'CE') {
                if (!isset($ceOI[$record->strike_price])) {
                    $ceOI[$record->strike_price] = 0;
                }
                $ceOI[$record->strike_price] = $record->oi;
            } else {
                if (!isset($peOI[$record->strike_price])) {
                    $peOI[$record->strike_price] = 0;
                }
                $peOI[$record->strike_price] = $record->oi;
            }
        }

        arsort($ceOI);
        arsort($peOI);

        $topCEStrikes = array_slice(array_keys($ceOI), 0, 5, true);
        $topPEStrikes = array_slice(array_keys($peOI), 0, 5, true);

        return [
            'ce_walls' => $topCEStrikes,
            'pe_walls' => $topPEStrikes,
            'ce_oi_data' => $ceOI,
            'pe_oi_data' => $peOI,
        ];
    }

    private function calculateMarketContext($date, $expiry, $marketData)
    {
        $currentPrice = $marketData['current_price'];
        $openPrice = $marketData['open_price'];
        $highPrice = $marketData['high_price'];
        $lowPrice = $marketData['low_price'];
        $candles = $marketData['candles'];

        $gap = $currentPrice - $openPrice;
        $range = $highPrice - $lowPrice;
        $avgDailyMove = 175;

        $direction = 'NEUTRAL';
        $lastCandles = array_slice($candles, -3);
        if (count($lastCandles) >= 3) {
            $candle1 = $lastCandles[0]['close'] - $lastCandles[0]['open'];
            $candle2 = $lastCandles[1]['close'] - $lastCandles[1]['open'];
            $candle3 = $lastCandles[2]['close'] - $lastCandles[2]['open'];

            if ($candle1 > 0 && $candle2 > 0 && $candle3 > 0) {
                $direction = 'UP';
            } elseif ($candle1 < 0 && $candle2 < 0 && $candle3 < 0) {
                $direction = 'DOWN';
            }
        }

        $remainingMove = $avgDailyMove - abs($gap);
        $expectedUp = $remainingMove > 0 ? $remainingMove / 2 : 0;
        $expectedDown = $remainingMove > 0 ? $remainingMove / 2 : 0;

        return [
            'gap' => round($gap, 2),
            'range' => round($range, 2),
            'avg_daily_move' => $avgDailyMove,
            'direction' => $direction,
            'remaining_move' => round($remainingMove, 2),
            'expected_up' => round($expectedUp, 2),
            'expected_down' => round($expectedDown, 2),
            'candles_analyzed' => count($lastCandles),
        ];
    }

    private function detectCoolDownEntry($date, $expiry, $marketData, $marketContext)
    {
        $candles = $marketData['candles'];
        $direction = $marketContext['direction'];

        if (count($candles) < 4) {
            return [
                'entry_available' => false,
                'message' => 'Not enough candles for analysis',
            ];
        }

        $lastCandles = array_slice($candles, -4);
        $consecutiveCount = 0;
        $totalMove = 0;

        for ($i = count($lastCandles) - 1; $i >= 0; $i--) {
            $candle = $lastCandles[$i];
            $move = $candle['close'] - $candle['open'];
            $totalMove += $move;

            if (($direction === 'UP' && $move > 0) || ($direction === 'DOWN' && $move < 0)) {
                $consecutiveCount++;
            } else {
                break;
            }
        }

        $isCoolingDown = false;
        if ($consecutiveCount >= 3) {
            $firstMove = $lastCandles[count($lastCandles) - $consecutiveCount]['close'] -
                         $lastCandles[count($lastCandles) - $consecutiveCount]['open'];
            $lastMove = $lastCandles[count($lastCandles) - 1]['close'] -
                        $lastCandles[count($lastCandles) - 1]['open'];

            if (abs($lastMove) < abs($firstMove) * 0.5) {
                $isCoolingDown = true;
            }
        }

        return [
            'entry_available' => $consecutiveCount >= 3 && $isCoolingDown,
            'consecutive_candles' => $consecutiveCount,
            'total_move' => round($totalMove, 2),
            'is_cooling_down' => $isCoolingDown,
            'message' => $isCoolingDown ? 'Premium decay opportunity detected!' : 'Waiting for cool down...',
        ];
    }

    private function getBestSellStrikes($date, $expiry, $marketData, $oiWalls, $range)
    {
        $currentPrice = $marketData['current_price'];
        $ceOI = $oiWalls['ce_oi_data'];
        $peOI = $oiWalls['pe_oi_data'];

        $bestCEStrike = null;
        $bestCEScore = 0;

        foreach ($ceOI as $strike => $oi) {
            if ($strike > $currentPrice && $strike <= $currentPrice + $range) {
                $distance = $strike - $currentPrice;
                $score = $oi / 1000000 + ($distance / 100) * 0.5;
                if ($score > $bestCEScore) {
                    $bestCEScore = $score;
                    $bestCEStrike = $strike;
                }
            }
        }

        $bestPEStrike = null;
        $bestPEScore = 0;

        foreach ($peOI as $strike => $oi) {
            if ($strike < $currentPrice && $strike >= $currentPrice - $range) {
                $distance = $currentPrice - $strike;
                $score = $oi / 1000000 + ($distance / 100) * 0.5;
                if ($score > $bestPEScore) {
                    $bestPEScore = $score;
                    $bestPEStrike = $strike;
                }
            }
        }

        $premiumData = $this->getPremiumData($date, $expiry, $bestCEStrike, $bestPEStrike);

        return [
            'ce' => [
                'strike' => $bestCEStrike,
                'score' => round($bestCEScore, 2),
                'premium' => $premiumData['ce_premium'] ?? 0,
                'delta' => $premiumData['ce_delta'] ?? 0,
                'theta' => $premiumData['ce_theta'] ?? 0,
                'oi' => $ceOI[$bestCEStrike] ?? 0,
            ],
            'pe' => [
                'strike' => $bestPEStrike,
                'score' => round($bestPEScore, 2),
                'premium' => $premiumData['pe_premium'] ?? 0,
                'delta' => $premiumData['pe_delta'] ?? 0,
                'theta' => $premiumData['pe_theta'] ?? 0,
                'oi' => $peOI[$bestPEStrike] ?? 0,
            ],
        ];
    }

    private function getPremiumData($date, $expiry, $ceStrike, $peStrike)
    {
        $data = DB::table('option_chains')
                  ->select(
                      'strike_price',
                      'option_type',
                      'bid_price',
                      'ltp',
                      'delta',
                      'theta'
                  )
                  ->whereDate('captured_at', $date)
                  ->where('expiry', $expiry)
                  ->whereIn('strike_price', [$ceStrike, $peStrike])
                  ->orderBy('captured_at', 'desc')
                  ->get();

        $result = [
            'ce_premium' => 0,
            'ce_delta' => 0,
            'ce_theta' => 0,
            'pe_premium' => 0,
            'pe_delta' => 0,
            'pe_theta' => 0,
        ];

        foreach ($data as $record) {
            if ($record->strike_price == $ceStrike && $record->option_type === 'CE') {
                $result['ce_premium'] = $record->bid_price ?? $record->ltp ?? 0;
                $result['ce_delta'] = $record->delta ?? 0;
                $result['ce_theta'] = $record->theta ?? 0;
            }
            if ($record->strike_price == $peStrike && $record->option_type === 'PE') {
                $result['pe_premium'] = $record->bid_price ?? $record->ltp ?? 0;
                $result['pe_delta'] = $record->delta ?? 0;
                $result['pe_theta'] = $record->theta ?? 0;
            }
        }

        return $result;
    }

    // ===== NEW: Straddle Helper Methods =====

    private function getStraddlePremiumData($date, $expiry, $ceStrike, $peStrike)
    {
        // For CE strike, we want CE option type
        $ceRecord = DB::table('option_chains')
                      ->select(
                          'bid_price',
                          'ltp',
                          'delta',
                          'theta',
                          'gamma',
                          'vega',
                          'iv',
                          'oi',
                          'volume'
                      )
                      ->whereDate('captured_at', $date)
                      ->where('expiry', $expiry)
                      ->where('strike_price', $ceStrike)
                      ->where('option_type', 'CE')  // ← Explicitly CE
                      ->orderBy('captured_at', 'desc')
                      ->first();

        // For PE strike, we want PE option type
        $peRecord = DB::table('option_chains')
                      ->select(
                          'bid_price',
                          'ltp',
                          'delta',
                          'theta',
                          'gamma',
                          'vega',
                          'iv',
                          'oi',
                          'volume'
                      )
                      ->whereDate('captured_at', $date)
                      ->where('expiry', $expiry)
                      ->where('strike_price', $peStrike)
                      ->where('option_type', 'PE')  // ← Explicitly PE
                      ->orderBy('captured_at', 'desc')
                      ->first();

        // Log the results for debugging
        \Log::info('CE Record for ' . $ceStrike . ': ' . ($ceRecord ? 'Found' : 'Not Found'));
        \Log::info('PE Record for ' . $peStrike . ': ' . ($peRecord ? 'Found' : 'Not Found'));

        $result = [
            'ce_premium' => 0,
            'ce_delta' => 0,
            'ce_theta' => 0,
            'ce_gamma' => 0,
            'ce_vega' => 0,
            'ce_iv' => 0,
            'ce_oi' => 0,
            'ce_volume' => 0,
            'pe_premium' => 0,
            'pe_delta' => 0,
            'pe_theta' => 0,
            'pe_gamma' => 0,
            'pe_vega' => 0,
            'pe_iv' => 0,
            'pe_oi' => 0,
            'pe_volume' => 0,
        ];

        if ($ceRecord) {
            $result['ce_premium'] = $ceRecord->bid_price ?? $ceRecord->ltp ?? 0;
            $result['ce_delta'] = $ceRecord->delta ?? 0;
            $result['ce_theta'] = $ceRecord->theta ?? 0;
            $result['ce_gamma'] = $ceRecord->gamma ?? 0;
            $result['ce_vega'] = $ceRecord->vega ?? 0;
            $result['ce_iv'] = $ceRecord->iv ?? 0;
            $result['ce_oi'] = $ceRecord->oi ?? 0;
            $result['ce_volume'] = $ceRecord->volume ?? 0;
        }

        if ($peRecord) {
            $result['pe_premium'] = $peRecord->bid_price ?? $peRecord->ltp ?? 0;
            $result['pe_delta'] = $peRecord->delta ?? 0;
            $result['pe_theta'] = $peRecord->theta ?? 0;
            $result['pe_gamma'] = $peRecord->gamma ?? 0;
            $result['pe_vega'] = $peRecord->vega ?? 0;
            $result['pe_iv'] = $peRecord->iv ?? 0;
            $result['pe_oi'] = $peRecord->oi ?? 0;
            $result['pe_volume'] = $peRecord->volume ?? 0;
        }

        return $result;
    }

    private function calculateAveragePremiumDiff($date, $expiry, $ceStrike, $peStrike)
    {
        $records = DB::table('option_chains')
                     ->select(
                         'strike_price',
                         'option_type',
                         'bid_price',
                         'ltp',
                         'captured_at'
                     )
                     ->whereDate('captured_at', $date)
                     ->where('expiry', $expiry)
                     ->whereIn('strike_price', [$ceStrike, $peStrike])
                     ->orderBy('captured_at', 'desc')
                     ->limit(20)
                     ->get();

        $diffs = [];
        $grouped = [];

        foreach ($records as $record) {
            $time = Carbon::parse($record->captured_at)->format('H:i');
            if (!isset($grouped[$time])) {
                $grouped[$time] = ['ce' => 0, 'pe' => 0];
            }
            if ($record->strike_price == $ceStrike && $record->option_type === 'CE') {
                $grouped[$time]['ce'] = $record->bid_price ?? $record->ltp ?? 0;
            }
            if ($record->strike_price == $peStrike && $record->option_type === 'PE') {
                $grouped[$time]['pe'] = $record->bid_price ?? $record->ltp ?? 0;
            }
        }

        foreach ($grouped as $time => $data) {
            if ($data['ce'] > 0 && $data['pe'] > 0) {
                $diffs[] = abs($data['ce'] - $data['pe']);
            }
        }

        if (count($diffs) === 0) return 10;

        return array_sum($diffs) / count($diffs);
    }

    private function detectStraddleOpportunity($marketData, $premiumData, $avgPremiumDiff, $straddleStrike, $currentPrice)
    {
        $candles = $marketData['candles'];
        $lastCandles = array_slice($candles, -5);

        $pePremium = $premiumData['pe_premium'];
        $cePremium = $premiumData['ce_premium'];
        $premiumDiff = abs($cePremium - $pePremium);

        $moveFromOpen = $currentPrice - $marketData['open_price'];
        $absMove = abs($moveFromOpen);

        $significantMove = $absMove >= 150;
        $significantDiff = $premiumDiff > $avgPremiumDiff * 1.5 || $premiumDiff > 20;
        $nearWall = abs($currentPrice - $straddleStrike) <= 50;

        $isSlowingDown = false;
        if (count($lastCandles) >= 3) {
            $firstMove = abs($lastCandles[0]['close'] - $lastCandles[0]['open']);
            $lastMove = abs($lastCandles[count($lastCandles)-1]['close'] - $lastCandles[count($lastCandles)-1]['open']);
            $isSlowingDown = $lastMove < $firstMove * 0.5;
        }

        $opportunity = 'NONE';

        if ($significantMove && $significantDiff) {
            if ($moveFromOpen < 0 && $pePremium > $cePremium) {
                $opportunity = 'SELL PE, BUY CE';
            } elseif ($moveFromOpen > 0 && $cePremium > $pePremium) {
                $opportunity = 'SELL CE, BUY PE';
            }
        }

        if ($nearWall && $isSlowingDown && $opportunity !== 'NONE') {
            $opportunity = 'STRONG ' . $opportunity;
        }

        return [
            'type' => $opportunity,
            'is_significant_move' => $significantMove,
            'is_significant_diff' => $significantDiff,
            'is_near_wall' => $nearWall,
            'is_slowing_down' => $isSlowingDown,
            'move_from_open' => round($moveFromOpen, 2),
            'abs_move' => round($absMove, 2),
            'premium_diff' => round($premiumDiff, 2),
            'avg_premium_diff' => round($avgPremiumDiff, 2),
            'detected_at' => now()->toDateTimeString(),
        ];
    }
}
