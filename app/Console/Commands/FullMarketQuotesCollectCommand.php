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
        try {
            info('Start FullMarketQuotesCollectCommand: ' . Carbon::now());
            $indexSymbols = ['NIFTY', 'BANKNIFTY', 'SENSEX'];
            $types        = ['FUT', 'CE', 'PE'];
            $_expiryDates = Expiry::whereIn('trading_symbol', $indexSymbols)
                                  ->where(function ($q) {
                                      $q->where('is_current', true)
                                        ->orWhere('is_next', true);
                                  });

            $expiryDates     = $_expiryDates->pluck('expiry')->unique()->toArray();
            $fullExpiryDates = $_expiryDates->get();

            $_instrumentKeys = Instrument::where(function ($q) use ($indexSymbols, $types) {
                $q->whereIn('underlying_symbol', $indexSymbols)
                  ->orWhereIn('trading_symbol', $indexSymbols);
            })
                                         ->whereIn('instrument_type', $types)
                                         ->whereIn('expiry', $expiryDates);

            $instrumentKeys = $_instrumentKeys->pluck('instrument_key')->unique()->toArray();
            $fullInstrument = $_instrumentKeys->get()->keyBy('instrument_key')->toArray();

            $indexSpotNames    = ['Nifty 50', 'Nifty Bank', 'BSE SENSEX'];
            $indexSpotKeys     = Instrument::where('instrument_type', 'INDEX')
                                           ->whereIn('name', $indexSpotNames)
                                           ->pluck('instrument_key')
                                           ->unique()
                                           ->toArray();
            $nifty50           = self::nifty50();
            $allInstrumentKeys = array_unique(array_merge($instrumentKeys, $indexSpotKeys, $nifty50));

            if (empty($allInstrumentKeys)) {
                $this->warn('No instrument keys found matching criteria.');
                return 1;
            }

            $apiToken = config('services.upstox.access_token');
            $url      = 'https://api.upstox.com/v2/market-quote/quotes';
            $now = Carbon::now();
            $chunks = array_chunk($allInstrumentKeys, 500);
            $allQuotes = [];
            foreach ($chunks as $key => $batch) {
                $params   = ['instrument_key' => implode(',', $batch)];
                $response = Http::withToken($apiToken)
                                ->withHeaders([
                                    'Content-Type' => 'application/json',
                                    'Accept'       => 'application/json',
                                ])
                                ->get($url, $params);

                if (!$response->successful()) {
                    $this->error('API call failed: '.$response->body());
                    continue;
                }

                $quotes = $response->json('data');
                foreach ($quotes as $instKey => $q) {
                    $allQuotes[$instKey] = $q;
                }
            }
            // Prepare for bulk insert
            $toInsert = [];

            foreach ($allQuotes as $instKey => $q) {
                $ohlc = $q['ohlc'] ?? [];
                $symbol = $q['symbol'] ?? null;
                $timestamp = isset($q['timestamp']) ? Carbon::parse($q['timestamp']) : $now;
                $instrumentDetails = $fullInstrument[$q['instrument_token'] ?? $instKey] ?? null;
                $expiry_value = $instrumentDetails && $instrumentDetails['expiry'] ? $instrumentDetails['expiry'] : null;
                $symbol_name = $instrumentDetails && $instrumentDetails['name'] ? $instrumentDetails['name'] : null;
                $strike_price = $instrumentDetails && $instrumentDetails['strike_price'] ? $instrumentDetails['strike_price'] : null;
                $instrument_type = $instrumentDetails && $instrumentDetails['instrument_type'] ? $instrumentDetails['instrument_type'] : null;


                $toInsert[] = [
                    'instrument_token'    => $q['instrument_token'] ?? $instKey,
                    'symbol'              => $symbol,
                    'symbol_name'         => $symbol_name,//$parsed['underlying'],
                    'expiry'              => null,//$parsed['expiry'],
                    'expiry_date'         => $expiry_value ? \Carbon\Carbon::createFromTimestampMs($expiry_value)->format('Y-m-d') : null,
                    'expiry_timestamp'    => $expiry_value,
                    'strike'              => $strike_price, //$parsed['strike'],
                    'option_type'         => $instrument_type, //$parsed['option_type'],
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
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];
            }
            // Bulk insert all 1-min records
            if (!empty($toInsert)) {
                $batchSize = 1000;
                foreach (array_chunk($toInsert, $batchSize) as $batch) {
                    FullMarketQuote::insert($batch);
                }
            }
            $this->info("Bulk inserted ".count($toInsert)." quotes");

            // Aggregation for 3-min quotes (one loop; can be optimized/bulked further if desired)
            foreach ($toInsert as $row) {
                $prev3 = FullMarketQuote::where('instrument_token', $row['instrument_token'])
                                        ->where('timestamp', '<', $row['timestamp'])
                                        ->orderBy('timestamp', 'desc')
                                        ->skip(2)->first();
                if ($prev3) {
                    ThreeMinQuote::create([
                        'instrument_token'    => $row['instrument_token'],
                        'symbol'              => $row['symbol'],
                        'symbol_name'         => $row['symbol_name'],
                        'expiry'              => null,//$row['expiry'],
                        'expiry_date'         => $row['expiry_date'],
                        'expiry_timestamp'    => $row['expiry_timestamp'],
                        'strike'              => $row['strike'],
                        'option_type'         => $row['option_type'],
                        'last_price'          => $row['last_price'],
                        'volume'              => $row['volume'],
                        'average_price'       => $row['average_price'],
                        'oi'                  => $row['oi'],
                        'net_change'          => $row['net_change'],
                        'total_buy_quantity'  => $row['total_buy_quantity'],
                        'total_sell_quantity' => $row['total_sell_quantity'],
                        'lower_circuit_limit' => $row['lower_circuit_limit'],
                        'upper_circuit_limit' => $row['upper_circuit_limit'],
                        'last_trade_time'     => $row['last_trade_time'],
                        'oi_day_high'         => $row['oi_day_high'],
                        'oi_day_low'          => $row['oi_day_low'],
                        'open'                => $row['open'],
                        'high'                => $row['high'],
                        'low'                 => $row['low'],
                        'close'               => $row['close'],
                        'timestamp'           => $row['timestamp'],
                        'diff_oi'             => $prev3->oi - $row['oi'],
                        'diff_volume'         => $prev3->volume - $row['volume'],
                        'diff_buy_quantity'   => $prev3->total_buy_quantity - $row['total_buy_quantity'],
                        'diff_sell_quantity'  => $prev3->total_sell_quantity - $row['total_sell_quantity'],
                        'diff_quantity'       => $row['total_buy_quantity'] - $row['total_sell_quantity'],
                    ]);
                }
            }

            $this->info('All quotes stored and aggregated.');
            info('End FullMarketQuotesCollectCommand: ' . Carbon::now());

            return 0;
        } catch (\Throwable $e) {
            info('Error in FullMarketQuotesCollectCommand: '.$e->getMessage());
            $this->error('Exception: '.$e->getMessage());
            return 1;
        }
    }

    // 1. Symbol parse helper (add to top of command file)
    protected function parseOptionSymbol($symbol)
    {
        $parts = [
            'underlying'  => null,
            'expiry'      => null,
            'strike'      => null,
            'option_type' => null,
        ];

        // SENSEX: SENSEX25O0976300PE
        if (preg_match('/^(SENSEX)(\d{2}[A-Z]\d{2})(\d{5})(CE|PE)$/', $symbol, $m)) {
            $parts['underlying']  = $m[1];
            $parts['expiry']      = $m[2];
            $parts['strike']      = $m[3];
            $parts['option_type'] = $m[4];
        } // NIFTY/BANKNIFTY: NIFTY25O1424450CE, NIFTY25OCT22700CE, BANKNIFTY25OCT1424450PE, etc.
        elseif (preg_match('/^([A-Z]+)(\d{2}[A-Z]{1,3}\d{0,2})(\d{5})(CE|PE)$/', $symbol, $m)) {
            $parts['underlying']  = $m[1];
            $parts['expiry']      = $m[2]; // Handles both monthly and weekly
            $parts['strike']      = $m[3];
            $parts['option_type'] = $m[4];
        } // Futures
        elseif (preg_match('/^([A-Z]+)(\d{2}[A-Z]{1,3})FUT$/', $symbol, $m)) {
            $parts['underlying']  = $m[1];
            $parts['expiry']      = $m[2];
            $parts['strike']      = null;
            $parts['option_type'] = 'FUT';
        } elseif (preg_match('/^([A-Z]+)/', $symbol, $m)) {
            $parts['underlying']  = $m[1];
            $parts['option_type'] = 'EQ';
        }

        return $parts;
    }

    public static function nifty50()
    {
        $nifty50List = self::nifty50List();

        return \App\Models\Instrument::where('instrument_type', 'EQ')
                                     ->whereIn('trading_symbol', $nifty50List)
                                     ->pluck('instrument_key')
                                     ->unique()
                                     ->toArray();
    }

    public static function nifty50List()
    {
        return [
            'ADANIPORTS', 'ASIANPAINT', 'AXISBANK', 'BAJAJ-AUTO', 'BAJFINANCE', 'BAJAJFINSV',
            'BPCL', 'BHARTIARTL', 'BRITANNIA', 'CIPLA', 'COALINDIA', 'DIVISLAB', 'DRREDDY',
            'EICHERMOT', 'GRASIM', 'HCLTECH', 'HDFCBANK', 'HDFCLIFE', 'HEROMOTOCO', 'HINDALCO',
            'HINDUNILVR', 'ICICIBANK', 'ITC', 'INDUSINDBK', 'INFY', 'JSWSTEEL', 'KOTAKBANK',
            'LT', 'M&M', 'MARUTI', 'NTPC', 'NESTLEIND', 'ONGC', 'POWERGRID', 'RELIANCE',
            'SBIN', 'SHREECEM', 'SUNPHARMA', 'TCS', 'TATACONSUM', 'TATAMOTORS', 'TATASTEEL',
            'TECHM', 'TITAN', 'UPL', 'ULTRACEMCO', 'WIPRO', 'HDFCAMC', 'ADANIENT', 'APOLLOHOSP',
        ];
    }

    protected function matchExpiryDate($parsed, $fullExpiryDates)
    {
        // Convert CE/PE to OPT, FUT stays as FUT
        $instrumentType = (in_array(strtoupper($parsed['option_type']), ['CE', 'PE'])) ? 'OPT' : strtoupper($parsed['option_type']);

        foreach ($fullExpiryDates as $expObj) {
            if (
                strtoupper($expObj->trading_symbol) === strtoupper($parsed['underlying']) &&
                strtoupper($expObj->instrument_type) === $instrumentType
            ) {
                return $expObj->expiry_date;
            }
        }

        return null;
    }

}

