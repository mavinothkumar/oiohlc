<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Instrument;
use App\Models\Expiry;
use App\Models\FullMarketQuote;
use App\Models\ThreeMinQuote;
use App\Models\FiveMinQuote;
use Carbon\Carbon;

class FullMarketQuotesCollectCommand extends Command
{
    protected $signature = 'market:collect-quotes';
    protected $description = 'Fetch/stores 1-min quotes then aggregates 3-min & 5-min data for Nifty/BankNifty/Sensex FUT/OPT current/next expiry';

    public function handle()
    {
        // 1. PREP: Instrument keys for current/next expiry
        $indexSymbols = ['NIFTY', 'BANKNIFTY', 'SENSEX'];
        $types        = ['FUT', 'CE', 'PE'];
        $expiryDates  = Expiry::whereIn('trading_symbol', $indexSymbols)
                              ->where(function ($q) {
                                  $q->where('is_current', true)->orWhere('is_next', true);
                              })
                              ->pluck('expiry')
                              ->unique()
                              ->toArray();

        if (empty($expiryDates)) {
            $this->warn('No expiry dates found for required symbols.');

            return 1;
        }

        $instrumentKeys = Instrument::where(function ($q) use ($indexSymbols) {
            $q->whereIn('underlying_symbol', $indexSymbols)
              ->orWhereIn('trading_symbol', $indexSymbols);
        })
                                    ->whereIn('instrument_type', $types)
                                    ->whereIn('expiry', $expiryDates)
                                    ->pluck('instrument_key')
                                    ->unique()
                                    ->toArray();

        $indexSpotNames = ['Nifty 50', 'Nifty Bank', 'BSE SENSEX'];
        $indexSpotKeys = Instrument::where('instrument_type', 'INDEX')
                                   ->whereIn('name', $indexSpotNames)
                                   ->pluck('instrument_key')
                                   ->unique()
                                   ->toArray();

// Combine all instrument keys (remove duplicates)
        $allInstrumentKeys = array_unique(array_merge($instrumentKeys, $indexSpotKeys));

        if (empty($allInstrumentKeys)) {
            $this->warn('No instrument keys found matching criteria.');

            return 1;
        }

        $chunks = array_chunk($allInstrumentKeys, 500);

        $apiToken = config('services.upstox.access_token'); // set in .env/services.php!
        $url      = 'https://api.upstox.com/v2/market-quote/quotes';

        $now = Carbon::now();

        // 2. FETCH & STORE 1-min FULL MARKET QUOTES
        foreach ($chunks as $batch) {
            $params   = ['instrument_key' => implode(',', $batch)];
            $response = Http::withToken($apiToken)
                            ->withHeaders([
                                'Content-Type' => 'application/json',
                                'Accept'       => 'application/json',
                            ])
                            ->get($url, $params);

            if ( ! $response->successful()) {
                $this->error('API call failed: '.$response->body());
                continue;
            }

            $quotes = $response->json('data');
            foreach ($quotes as $instKey => $q) {
                $ohlc      = $q['ohlc'] ?? [];
                $symbol    = $q['symbol'] ?? null;
                $parsed    = $this->parseOptionSymbol($symbol);
                $timestamp = isset($q['timestamp']) ? Carbon::parse($q['timestamp']) : $now;

                // Store in full_market_quotes (1-min)
                $fmq = FullMarketQuote::create([
                    'instrument_token'    => $q['instrument_token'] ?? $instKey,
                    'symbol'              => $symbol,
                    'symbol_name'         => $parsed['underlying'],
                    'expiry'              => $parsed['expiry'],
                    'strike'              => $parsed['strike'],
                    'option_type'         => $parsed['option_type'],
                    'last_price'          => $q['last_price'] ?? null,
                    'volume'              => $q['volume'] ?? null,
                    'average_price'       => $q['average_price'] ?? null,
                    'oi'                  => $q['oi'] ?? null,
                    'net_change'          => $q['net_change'] ?? null,
                    'total_buy_quantity'  => $q['total_buy_quantity'] ?? null,
                    'total_sell_quantity' => $q['total_sell_quantity'] ?? null,
                    'lower_circuit_limit' => $q['lower_circuit_limit'] ?? null,
                    'upper_circuit_limit' => $q['upper_circuit_limit'] ?? null,
                    'last_trade_time'     => $q['last_trade_time'] ?? null,
                    'oi_day_high'         => $q['oi_day_high'] ?? null,
                    'oi_day_low'          => $q['oi_day_low'] ?? null,
                    'open'                => $ohlc['open'] ?? null,
                    'high'                => $ohlc['high'] ?? null,
                    'low'                 => $ohlc['low'] ?? null,
                    'close'               => $ohlc['close'] ?? null,
                    'timestamp'           => $timestamp,
                ]);

                // 3-minute AGGREGATION
                $prev3 = FullMarketQuote::where('instrument_token', $fmq->instrument_token)
                                        ->where('timestamp', '<', $fmq->timestamp)
                                        ->orderBy('timestamp', 'desc')
                                        ->skip(2)->first(); // Get the quote that's 3 mins ago

                if ($prev3) {
                    ThreeMinQuote::create([
                        'instrument_token'    => $fmq->instrument_token,
                        'symbol'              => $fmq->symbol,
                        'symbol_name'         => $parsed['underlying'],
                        'expiry'              => $parsed['expiry'],
                        'strike'              => $parsed['strike'],
                        'option_type'         => $parsed['option_type'],
                        'last_price'          => $fmq->last_price,
                        'volume'              => $fmq->volume,
                        'average_price'       => $fmq->average_price,
                        'oi'                  => $fmq->oi,
                        'net_change'          => $fmq->net_change,
                        'total_buy_quantity'  => $fmq->total_buy_quantity,
                        'total_sell_quantity' => $fmq->total_sell_quantity,
                        'lower_circuit_limit' => $fmq->lower_circuit_limit,
                        'upper_circuit_limit' => $fmq->upper_circuit_limit,
                        'last_trade_time'     => $fmq->last_trade_time,
                        'oi_day_high'         => $fmq->oi_day_high,
                        'oi_day_low'          => $fmq->oi_day_low,
                        'open'                => $fmq->open,
                        'high'                => $fmq->high,
                        'low'                 => $fmq->low,
                        'close'               => $fmq->close,
                        'timestamp'           => $fmq->timestamp,
                        'diff_oi'             => $prev3->oi - $fmq->oi,
                        'diff_volume'         => $prev3->volume - $fmq->volume,
                        'diff_buy_quantity'   => $prev3->total_buy_quantity - $fmq->total_buy_quantity,
                        'diff_sell_quantity'  => $prev3->total_sell_quantity - $fmq->total_sell_quantity,
                        'diff_quantity'       => $fmq->total_buy_quantity - $fmq->total_sell_quantity,
                    ]);
                }

                // 5-minute AGGREGATION
                $prev5 = FullMarketQuote::where('instrument_token', $fmq->instrument_token)
                                        ->where('timestamp', '<', $fmq->timestamp)
                                        ->orderBy('timestamp', 'desc')
                                        ->skip(4)->first(); // Get the quote that's 5 mins ago

                if ($prev5) {
                    FiveMinQuote::create([
                        'instrument_token'    => $fmq->instrument_token,
                        'symbol'              => $fmq->symbol,
                        'symbol_name'         => $parsed['underlying'],
                        'expiry'              => $parsed['expiry'],
                        'strike'              => $parsed['strike'],
                        'option_type'         => $parsed['option_type'],
                        'last_price'          => $fmq->last_price,
                        'volume'              => $fmq->volume,
                        'average_price'       => $fmq->average_price,
                        'oi'                  => $fmq->oi,
                        'net_change'          => $fmq->net_change,
                        'total_buy_quantity'  => $fmq->total_buy_quantity,
                        'total_sell_quantity' => $fmq->total_sell_quantity,
                        'lower_circuit_limit' => $fmq->lower_circuit_limit,
                        'upper_circuit_limit' => $fmq->upper_circuit_limit,
                        'last_trade_time'     => $fmq->last_trade_time,
                        'oi_day_high'         => $fmq->oi_day_high,
                        'oi_day_low'          => $fmq->oi_day_low,
                        'open'                => $fmq->open,
                        'high'                => $fmq->high,
                        'low'                 => $fmq->low,
                        'close'               => $fmq->close,
                        'timestamp'           => $fmq->timestamp,
                        'diff_oi'             => $prev5->oi - $fmq->oi,
                        'diff_volume'         => $prev5->volume - $fmq->volume,
                        'diff_buy_quantity'   => $prev5->total_buy_quantity - $fmq->total_buy_quantity,
                        'diff_sell_quantity'  => $prev5->total_sell_quantity - $fmq->total_sell_quantity,
                        'diff_quantity'       => $fmq->total_buy_quantity - $fmq->total_sell_quantity,
                    ]);
                }
            }
            $this->info("Stored ".count($quotes)." full market quotes for this batch, and aggregated 3/5-min data.");
        }

        $this->info('All market quotes (1-min) and 3/5-min aggregates inserted for current/next expiry of Nifty50, BankNifty, Sensex.');

        return 0;
    }

    // 1. Symbol parse helper (add to top of command file)
    protected function parseOptionSymbol($symbol)
    {
        $parts = [
            'underlying'   => null,
            'expiry'       => null,
            'strike'       => null,
            'option_type'  => null,
        ];

        // SENSEX: SENSEX25O0976300PE
        if (preg_match('/^(SENSEX)(\d{2}[A-Z]\d{2})(\d{5})(CE|PE)$/', $symbol, $m)) {
            $parts['underlying']  = $m[1];
            $parts['expiry']      = $m[2];
            $parts['strike']      = $m[3];
            $parts['option_type'] = $m[4];
        }
        // NIFTY/BANKNIFTY: NIFTY25O1424450CE, NIFTY25OCT22700CE, BANKNIFTY25OCT1424450PE, etc.
        elseif (preg_match('/^([A-Z]+)(\d{2}[A-Z]{1,3}\d{0,2})(\d{5})(CE|PE)$/', $symbol, $m)) {
            $parts['underlying']  = $m[1];
            $parts['expiry']      = $m[2]; // Handles both monthly and weekly
            $parts['strike']      = $m[3];
            $parts['option_type'] = $m[4];
        }
        // Futures
        elseif (preg_match('/^([A-Z]+)(\d{2}[A-Z]{1,3})FUT$/', $symbol, $m)) {
            $parts['underlying']  = $m[1];
            $parts['expiry']      = $m[2];
            $parts['strike']      = null;
            $parts['option_type'] = 'FUT';
        }
        elseif (preg_match('/^([A-Z]+)/', $symbol, $m)) {
            $parts['underlying'] = $m[1];
        }
        return $parts;
    }



}

