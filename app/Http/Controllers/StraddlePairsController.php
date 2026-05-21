<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StraddlePairsController extends Controller
{
    public function index()
    {
        $currentDate = now()->toDateString();
        $currentExpiry = $this->getCurrentExpiry();
        $currentSpot = $this->getCurrentSpot();
        $openPrice = $this->getOpenPrice($currentDate, $currentExpiry);
        $roundStrike = round($openPrice / 50) * 50;

        return view('straddle-pairs', compact(
            'currentDate',
            'currentExpiry',
            'currentSpot',
            'openPrice',
            'roundStrike'
        ));
    }

    public function getPairsData(Request $request)
    {
        $date = $request->input('date', now()->toDateString());
        $expiry = $request->input('expiry', $this->getCurrentExpiry());
        $roundStrike = $request->input('round_strike', 23600);
        $range = $request->input('range', 500);

        $currentPrice = $this->getCurrentPrice($date, $expiry);
        $openPrice = $this->getOpenPrice($date, $expiry);

        // Generate 10 OTM pairs
        $pairs = $this->generateOTMPairs($date, $expiry, $roundStrike, $range, $currentPrice);

        // Find best entry
        $bestEntry = $this->findBestEntry($pairs, $currentPrice, $openPrice);

        return response()->json([
            'pairs' => $pairs,
            'best_entry' => $bestEntry,
            'current_price' => round($currentPrice, 2),
            'open_price' => round($openPrice, 2),
            'round_strike' => $roundStrike,
            'expiry' => $expiry,
            'date' => $date,
        ]);
    }

    private function generateOTMPairs($date, $expiry, $roundStrike, $range, $currentPrice)
    {
        $pairs = [];
        $steps = [50, 100, 150, 200, 250, 300, 350, 400, 450, 500];

        foreach ($steps as $step) {
            $ceStrike = $roundStrike + $step;
            $peStrike = $roundStrike - $step;

            // Skip if too far from current price
            if (abs($ceStrike - $currentPrice) > $range * 1.2) continue;
            if (abs($peStrike - $currentPrice) > $range * 1.2) continue;

            $data = $this->getPairData($date, $expiry, $ceStrike, $peStrike);

            // Calculate metrics
            $diff = abs($data['ce_premium'] - $data['pe_premium']);
            $sum = $data['ce_premium'] + $data['pe_premium'];
            $distance = abs($currentPrice - $roundStrike);

            // Win probability based on delta balance and distance
            $winProbability = $this->calculateWinProbability($data, $distance, $currentPrice, $roundStrike);

            $pairs[] = [
                'ce_strike' => $ceStrike,
                'pe_strike' => $peStrike,
                'ce_premium' => round($data['ce_premium'], 2),
                'pe_premium' => round($data['pe_premium'], 2),
                'ce_delta' => round($data['ce_delta'], 4),
                'pe_delta' => round($data['pe_delta'], 4),
                'ce_theta' => round($data['ce_theta'], 4),
                'pe_theta' => round($data['pe_theta'], 4),
                'difference' => round($diff, 2),
                'sum' => round($sum, 2),
                'ce_oi' => $data['ce_oi'],
                'pe_oi' => $data['pe_oi'],
                'win_probability' => round($winProbability, 1),
                'is_best' => false, // Will be set later
            ];
        }

        return $pairs;
    }

    private function getPairData($date, $expiry, $ceStrike, $peStrike)
    {
        $ceRecord = DB::table('option_chains')
                      ->select('bid_price', 'ltp', 'delta', 'theta', 'oi', 'volume')
                      ->whereDate('captured_at', $date)
                      ->where('expiry', $expiry)
                      ->where('strike_price', $ceStrike)
                      ->where('option_type', 'CE')
                      ->orderBy('captured_at', 'desc')
                      ->first();

        $peRecord = DB::table('option_chains')
                      ->select('bid_price', 'ltp', 'delta', 'theta', 'oi', 'volume')
                      ->whereDate('captured_at', $date)
                      ->where('expiry', $expiry)
                      ->where('strike_price', $peStrike)
                      ->where('option_type', 'PE')
                      ->orderBy('captured_at', 'desc')
                      ->first();

        return [
            'ce_premium' => $ceRecord->bid_price ?? $ceRecord->ltp ?? 0,
            'ce_delta' => $ceRecord->delta ?? 0,
            'ce_theta' => $ceRecord->theta ?? 0,
            'ce_oi' => $ceRecord->oi ?? 0,
            'ce_volume' => $ceRecord->volume ?? 0,
            'pe_premium' => $peRecord->bid_price ?? $peRecord->ltp ?? 0,
            'pe_delta' => $peRecord->delta ?? 0,
            'pe_theta' => $peRecord->theta ?? 0,
            'pe_oi' => $peRecord->oi ?? 0,
            'pe_volume' => $peRecord->volume ?? 0,
        ];
    }

    private function calculateWinProbability($data, $distance, $currentPrice, $roundStrike)
    {
        $score = 0;

        // 1. Delta balance (closer to 0.5 each = more balanced)
        $ceDelta = abs($data['ce_delta']);
        $peDelta = abs($data['pe_delta']);
        $deltaBalance = 1 - abs($ceDelta - $peDelta);
        $score += $deltaBalance * 30;

        // 2. Premium balance (closer sum = more balanced)
        $premiumBalance = 1 - (abs($data['ce_premium'] - $data['pe_premium']) / max($data['ce_premium'] + $data['pe_premium'], 1));
        $score += $premiumBalance * 25;

        // 3. Distance from current price (farther = safer)
        $distanceScore = min($distance / 300, 1) * 20;
        $score += $distanceScore;

        // 4. OI balance (both sides have liquidity)
        $oiBalance = 1 - (abs($data['ce_oi'] - $data['pe_oi']) / max($data['ce_oi'] + $data['pe_oi'], 1));
        $score += $oiBalance * 15;

        // 5. Theta (higher theta = better time decay)
        $thetaScore = min(abs($data['ce_theta'] + $data['pe_theta']) / 30, 1) * 10;
        $score += $thetaScore;

        return min($score, 100);
    }

    private function findBestEntry($pairs, $currentPrice, $openPrice)
    {
        if (empty($pairs)) return null;

        // Sort by win probability
        usort($pairs, function($a, $b) {
            return $b['win_probability'] - $a['win_probability'];
        });

        $best = $pairs[0];

        // Determine which side to sell
        $marketDirection = $currentPrice > $openPrice ? 'UP' : 'DOWN';
        $side = $marketDirection === 'UP' ? 'PE' : 'CE';

        // Calculate stop loss based on premium
        $stopLoss = $side === 'CE'
            ? $best['ce_premium'] * 1.5
            : $best['pe_premium'] * 1.5;

        return [
            'pair' => $best,
            'side' => $side,
            'market_direction' => $marketDirection,
            'stop_loss' => round($stopLoss, 2),
            'target' => round($stopLoss * 0.5, 2), // 1:2 risk-reward
            'confidence' => $best['win_probability'],
            'entry_price' => $side === 'CE' ? $best['ce_premium'] : $best['pe_premium'],
        ];
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
        $record = DB::table('option_chains')
                    ->select('underlying_spot_price')
                    ->whereDate('captured_at', $date)
                    ->where('expiry', $expiry)
                    ->orderBy('captured_at', 'asc')
                    ->first();

        return $record->underlying_spot_price ?? $this->getCurrentSpot();
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
