<?php

namespace App\Console\Commands;

use App\Models\OptionChain;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use function Laravel\Prompts\table;

class FetchOptionChainData extends Command
{
    protected $signature = 'optionchain:fetch';
    protected $description = 'Fetch option chain data from Upstox API every 1 minute and aggregate every 3 minutes within market hours';

    public function handle()
    {
        info('Fetching option chain data from Upstox API at '.Carbon::now());
        // Step 1. Fetch and store 1-min data into option_chains
        $this->fetchAndStoreOptionChain();

        // Step 2. Determine if current time is within market hours (9:15–15:30)
//        $now          = now();
//        $start        = now()->copy()->setTime(9, 15);
//        $end          = now()->copy()->setTime(15, 30);
//        $isMarketTime = $now->between($start, $end);
//
//        // Step 3. Check if it’s a 3-minute interval
//        if ($isMarketTime && $now->second < 5 && $now->minute % 3 === 0) {
//            info('inside 3-minute interval '.$now->minute);
//            $this->aggregateThreeMinuteData();
//        }
        Log::info('Completed option chain data from Upstox API at '.Carbon::now());

        return Command::SUCCESS;
    }

    private function fetchAndStoreOptionChain()
    {
        Log::info('Fetching option chain data from Upstox API at '.Carbon::now());

        $instruments = [
            ['key' => 'NSE_INDEX|Nifty 50', 'symbol' => 'NIFTY'],
//            ['key' => 'BSE_INDEX|SENSEX', 'symbol' => 'SENSEX'],
//            ['key' => 'NSE_INDEX|Nifty Bank', 'symbol' => 'BANKNIFTY'],
//            ['key' => 'NSE_INDEX|Nifty Fin Service', 'symbol' => 'FINNIFTY'],
        ];

        $token = config('services.upstox.access_token');

        foreach ($instruments as $index => $inst) {
            Log::info('Fetching option chain for '.$inst['symbol'].' at '.Carbon::now());

            $expiry = DB::table('nse_expiries')
                        ->where('trading_symbol', $inst['symbol'])
                        ->where('is_current', 1)
                        ->where('instrument_type', 'OPT')
                        ->value('expiry_date');

            if ( ! $expiry) {
                Log::warning("No current expiry found for {$inst['symbol']}");
                continue;
            }

            $url      = 'https://api.upstox.com/v2/option/chain';
            $response = Http::withHeaders([
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ])->get($url, [
                'instrument_key' => $inst['key'],
                'expiry_date'    => $expiry,
            ]);

            $data = $response->json('data') ?? [];
            //info('$data',[$data]);
            if (empty($data)) {
                Log::error("Empty data for {$inst['symbol']}");
                continue;
            }

            $records          = [];
            $latestCapturedAt = DB::table('option_chains')->limit(1)
                                  ->latest('captured_at')  // or orderBy('captured_at', 'desc')
                                  ->value('captured_at');  // Gets just the scalar value

            Log::info('$latestCapturedAt '.$latestCapturedAt);

            $prevData = DB::table('option_chains')
                          ->where('captured_at', $latestCapturedAt)
                          ->get()
                          ->keyBy('instrument_key')
                          ->toArray();

            foreach ($data as $item) {
                $records[] = $this->buildRecord($item, $prevData, $inst['symbol'], 'CE');
                $records[] = $this->buildRecord($item, $prevData, $inst['symbol'], 'PE');
            }

            DB::table('option_chains')->insert($records);
            Log::info('Inserted '.count($records).' records for '.$inst['symbol']);
        }

        Log::info('Completed option chain fetch cycle at '.Carbon::now());
    }

    private function aggregateThreeMinuteData()
    {
        Log::info('Creating 3-min snapshot...');

        $capturedAt = now()->second(0)->minute(floor(now()->minute / 3) * 3);

        $latestRecords = OptionChain::where('captured_at', '>=', now()->subMinutes(2))->get();

        info('$latestRecords '.$capturedAt);

        foreach ($latestRecords as $record) {
            $previous = DB::table('option_chains_3m')
                          ->where('trading_symbol', $record->trading_symbol)
                          ->where('strike_price', $record->strike_price)
                          ->where('option_type', $record->option_type)
                          ->where('expiry', $record->expiry)
                          ->orderByDesc('captured_at')
                          ->first();

            $exists = DB::table('option_chains_3m')
                        ->where('trading_symbol', $record->trading_symbol)
                        ->where('strike_price', $record->strike_price)
                        ->where('option_type', $record->option_type)
                        ->where('expiry', $record->expiry)
                        ->where('captured_at', $capturedAt)
                        ->exists();

            if ($exists) {
                continue;
            }

            // Calculate the differences
            $diffLtp = $previous ? $record->ltp - $previous->ltp : null;
            $diffOi  = $previous ? $record->oi - $previous->oi : null;

            // Determine build-up type
            $buildUp = null;
            if ( ! is_null($diffLtp) && ! is_null($diffOi)) {
                if ($diffLtp > 0 && $diffOi > 0) {
                    $buildUp = 'Long Build';
                } elseif ($diffLtp < 0 && $diffOi > 0) {
                    $buildUp = 'Short Build';
                } elseif ($diffLtp > 0 && $diffOi < 0) {
                    $buildUp = 'Short Cover';
                } elseif ($diffLtp < 0 && $diffOi < 0) {
                    $buildUp = 'Long Unwind';
                }
            }

            DB::table('option_chains_3m')->insert([
                'underlying_key'        => $record->underlying_key,
                'trading_symbol'        => $record->trading_symbol,
                'expiry'                => $record->expiry,
                'strike_price'          => $record->strike_price,
                'option_type'           => $record->option_type,
                'ltp'                   => $record->ltp,
                'volume'                => $record->volume,
                'oi'                    => $record->oi,
                'close_price'           => $record->close_price,
                'bid_price'             => $record->bid_price,
                'bid_qty'               => $record->bid_qty,
                'ask_price'             => $record->ask_price,
                'ask_qty'               => $record->ask_qty,
                'prev_oi'               => $record->prev_oi,
                'vega'                  => $record->vega,
                'theta'                 => $record->theta,
                'gamma'                 => $record->gamma,
                'delta'                 => $record->delta,
                'iv'                    => $record->iv,
                'pop'                   => $record->pop,
                'underlying_spot_price' => $record->underlying_spot_price,
                'pcr'                   => $record->pcr,

                'diff_underlying_spot_price' => $previous ? $record->underlying_spot_price - $previous->underlying_spot_price : null,
                'diff_ltp'                   => $diffLtp,
                'diff_volume'                => $previous ? $record->volume - $previous->volume : null,
                'diff_oi'                    => $diffOi,

                // new column
                'build_up'                   => $buildUp,

                'captured_at' => $capturedAt,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        Log::info('3-min snapshot completed at '.$capturedAt);
    }

    private function buildRecord(array $item, $prevData, string $symbol, string $type)
    {
        $optData        = $type === 'CE' ? $item['call_options'] : $item['put_options'];
        $m              = $optData['market_data'];
        $g              = $optData['option_greeks'];
        $instrument_key = $optData['instrument_key'];

        $prevRecord = $prevData[$instrument_key] ?? null;

        // Calculate differences
        $diff_oi        = $prevRecord ? $m['oi'] - $prevRecord->oi : null;
        $diff_volume    = $prevRecord ? $m['volume'] - $prevRecord->volume : null;
        $diff_ltp       = $prevRecord ? $m['ltp'] - $prevRecord->ltp : null;

        // Derive build_up from diff_oi and diff_ltp
        $buildUp = null;

        if (!is_null($diff_oi) && !is_null($diff_ltp) && $diff_oi != 0 && $diff_ltp != 0) {
            if ($diff_ltp > 0 && $diff_oi > 0) {
                $buildUp = 'Long Build';      // Price ↑, OI ↑  => Long Build-up [web:11]
            } elseif ($diff_ltp < 0 && $diff_oi > 0) {
                $buildUp = 'Short Build';     // Price ↓, OI ↑  => Short Build-up [web:11]
            } elseif ($diff_ltp > 0 && $diff_oi < 0) {
                $buildUp = 'Short Cover';     // Price ↑, OI ↓  => Short Covering [web:11]
            } elseif ($diff_ltp < 0 && $diff_oi < 0) {
                $buildUp = 'Long Unwind';     // Price ↓, OI ↓  => Long Unwinding [web:11]
            }
        }

        return [
            'underlying_key'        => $item['underlying_key'],
            'instrument_key'        => $instrument_key,
            'trading_symbol'        => $symbol,
            'expiry'                => $item['expiry'],
            'strike_price'          => $item['strike_price'],
            'option_type'           => $type,
            'ltp'                   => $m['ltp'],
            'volume'                => $m['volume'],
            'oi'                    => $m['oi'],
            'close_price'           => $m['close_price'],
            'bid_price'             => $m['bid_price'],
            'bid_qty'               => $m['bid_qty'],
            'ask_price'             => $m['ask_price'],
            'ask_qty'               => $m['ask_qty'],
            'prev_oi'               => $m['prev_oi'],
            'vega'                  => $g['vega'],
            'theta'                 => $g['theta'],
            'gamma'                 => $g['gamma'],
            'delta'                 => $g['delta'],
            'iv'                    => $g['iv'],
            'pop'                   => $g['pop'],
            'underlying_spot_price' => $item['underlying_spot_price'],
            'pcr'                   => $item['pcr'] ?? null,
            'captured_at'           => now()->copy()->second(0),
            'created_at'            => now(),
            'updated_at'            => now(),

            // New diff columns
            'diff_oi'               => $diff_oi,
            'diff_volume'           => $diff_volume,
            'diff_ltp'              => $diff_ltp,

            'build_up'              => $buildUp,
        ];
    }
}
