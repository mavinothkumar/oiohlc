<?php


// app/Http/Controllers/TrendController.php

namespace App\Http\Controllers;

use App\Models\DailyOhlcQuote;
use Illuminate\Support\Facades\DB;

class TrendController extends Controller
{
    public function index()
    {
        // 1. Previous working day
        $workingDate = DB::table('nse_working_days')->where('previous', 1)
                                    ->orderByDesc('id')
                                    ->value('working_date'); // 'Y-m-d'

        if (! $workingDate) {
            abort(404, 'No previous working date found');
        }

        // 2. Index OHLC for that day
        $indexes = DailyOhlcQuote::query()
                                 ->where('option_type', 'INDEX')
                                 ->where('quote_date', $workingDate)
                                 ->whereIn('symbol_name', ['NIFTY','BANKNIFTY','SENSEX','FINNIFTY'])
                                 ->get()
                                 ->keyBy('symbol_name');

        $rows = [];

        foreach ($indexes as $symbol => $indexRow) {
            // 3.a Determine current expiry for that symbol (if any)
            $currentExpiry = DailyOhlcQuote::query()
                                           ->where('quote_date', $workingDate)
                                           ->where('symbol_name', $symbol)
                                           ->whereIn('option_type', ['CE','PE'])
                                           ->orderBy('expiry_date')
                                           ->value('expiry_date'); // nearest expiry

            $optionQuery = DailyOhlcQuote::query()
                                         ->where('quote_date', $workingDate)
                                         ->where('symbol_name', $symbol)
                                         ->whereIn('option_type', ['CE','PE']);

            if ($currentExpiry) {
                $optionQuery->where('expiry_date', $currentExpiry);
            }

            $options = $optionQuery->get();

            if ($options->isEmpty()) {
                continue;
            }

            // 3.b Group by strike and pick CE/PE pairs
            $groupedByStrike = $options->groupBy('strike');

            $bestPair = null;
            $bestDiff = null;

            foreach ($groupedByStrike as $strike => $contracts) {
                $ce = $contracts->firstWhere('option_type', 'CE');
                $pe = $contracts->firstWhere('option_type', 'PE');

                if (! $ce || ! $pe) {
                    continue;
                }

                $diff = abs($ce->close - $pe->close);

                if ($bestDiff === null || $diff < $bestDiff) {
                    $bestDiff = $diff;
                    $bestPair = [
                        'symbol' => $symbol,
                        'strike' => $strike,
                        'ce'     => $ce,
                        'pe'     => $pe,
                    ];
                }
            }

            if (! $bestPair) {
                continue;
            }

            foreach (['ce', 'pe'] as $side) {
                $contract = $bestPair[$side];

                $highCloseDiff = max(0, $contract->high - $contract->close);
                $closeLowDiff  = max(0, $contract->close - $contract->low);

                // 4–5. Decide type
                $type = 'Side';
                $typeColor = 'bg-yellow-100 text-yellow-800';

                if ($highCloseDiff < $closeLowDiff) {
                    $type = 'Panic';
                    $typeColor = 'bg-red-100 text-red-800';
                } elseif ($closeLowDiff < $highCloseDiff) {
                    $type = 'Profit';
                    $typeColor = 'bg-green-100 text-green-800';
                }

                if ($highCloseDiff > 30 || $closeLowDiff > 30) {
                    $type = 'Side';
                    $typeColor = 'bg-yellow-100 text-yellow-800';
                }

                $rows[] = [
                    'symbol'        => $symbol,
                    'strike'        => $bestPair['strike'],
                    'option_type'   => $contract->option_type,
                    'high'          => $contract->high,
                    'low'           => $contract->low,
                    'close'         => $contract->close,
                    'high_close_diff' => $highCloseDiff,
                    'close_low_diff'  => $closeLowDiff,
                    'type'          => $type,
                    'type_color'    => $typeColor,
                ];
            }
        }

        return view('trend.index', [
            'workingDate' => $workingDate,
            'rows'        => $rows,
        ]);
    }
}
