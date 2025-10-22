<?php

namespace App\Console\Commands;

use App\Models\OptionChain;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchOptionChainData extends Command
{
    protected $signature = 'optionchain:fetch';
    protected $description = 'Fetch option chain data from Upstox API every 1 minute and aggregate every 3 minutes within market hours';

    public function handle()
    {
        // Step 1. Fetch and store 1-min data into option_chains
        $this->fetchAndStoreOptionChain();

        // Step 2. Determine if current time is within market hours (9:15–15:30)
        $now = now();
        $start = now()->copy()->setTime(9, 15);
        $end = now()->copy()->setTime(15, 30);
        $isMarketTime = $now->between($start, $end);

        // Step 3. Check if it’s a 3-minute interval
        if ($isMarketTime && $now->second < 5 && $now->minute % 3 === 0) {
            $this->aggregateThreeMinuteData();
        }

        return Command::SUCCESS;
    }

    private function fetchAndStoreOptionChain()
    {
        Log::info('Fetching option chain data from Upstox API at '.Carbon::now());

        $instruments = [
            ['key' => 'NSE_INDEX|Nifty Bank', 'symbol' => 'BANKNIFTY'],
            ['key' => 'NSE_INDEX|Nifty 50',   'symbol' => 'NIFTY'],
            ['key' => 'BSE_INDEX|SENSEX',     'symbol' => 'SENSEX'],
        ];

        $token = config('services.upstox.access_token');

        foreach ($instruments as $index => $inst) {
            Log::info('Fetching option chain for '.$inst['symbol'].' at '.Carbon::now());

            $expiry = DB::table('expiries')
                        ->where('trading_symbol', $inst['symbol'])
                        ->where('is_current', 1)
                        ->where('instrument_type', 'OPT')
                        ->value('expiry_date');

            if (!$expiry) {
                Log::warning("No current expiry found for {$inst['symbol']}");
                continue;
            }

            $url = 'https://api.upstox.com/v2/option/chain';
            $response = Http::withHeaders([
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ])->get($url, [
                'instrument_key' => $inst['key'],
                'expiry_date'    => $expiry,
            ]);

            $data = $response->json('data') ?? [];
            if (empty($data)) {
                Log::error("Empty data for {$inst['symbol']}");
                continue;
            }

            $records = [];
            foreach ($data as $item) {
                $records[] = $this->buildRecord($item, $inst['symbol'], 'CE');
                $records[] = $this->buildRecord($item, $inst['symbol'], 'PE');
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

        // Fetch latest 2-minute recent data from option_chains
        $latestRecords = OptionChain::where('created_at', '>=', now()->subMinutes(2))->get();

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
                continue; // Avoid duplicate entries in same 3-min window
            }

            DB::table('option_chains_3m')->insert([
                'underlying_key' => $record->underlying_key,
                'trading_symbol' => $record->trading_symbol,
                'expiry' => $record->expiry,
                'strike_price' => $record->strike_price,
                'option_type' => $record->option_type,
                'ltp' => $record->ltp,
                'volume' => $record->volume,
                'oi' => $record->oi,
                'close_price' => $record->close_price,
                'bid_price' => $record->bid_price,
                'bid_qty' => $record->bid_qty,
                'ask_price' => $record->ask_price,
                'ask_qty' => $record->ask_qty,
                'prev_oi' => $record->prev_oi,
                'vega' => $record->vega,
                'theta' => $record->theta,
                'gamma' => $record->gamma,
                'delta' => $record->delta,
                'iv' => $record->iv,
                'pop' => $record->pop,
                'underlying_spot_price' => $record->underlying_spot_price,
                'pcr' => $record->pcr,

                'diff_underlying_spot_price' => $previous ? $record->underlying_spot_price - $previous->underlying_spot_price : null,
                'diff_ltp' => $previous ? $record->ltp - $previous->ltp : null,
                'diff_volume' => $previous ? $record->volume - $previous->volume : null,
                'diff_oi' => $previous ? $record->oi - $previous->oi : null,

                'captured_at' => $capturedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Log::info('3-min snapshot stored at '.$capturedAt);
    }

    private function buildRecord(array $item, string $symbol, string $type)
    {
        $optData = $type === 'CE' ? $item['call_options'] : $item['put_options'];
        $m = $optData['market_data'];
        $g = $optData['option_greeks'];

        return [
            'underlying_key'       => $item['underlying_key'],
            'trading_symbol'       => $symbol,
            'expiry'               => $item['expiry'],
            'strike_price'         => $item['strike_price'],
            'option_type'          => $type,
            'ltp'                  => $m['ltp'],
            'volume'               => $m['volume'],
            'oi'                   => $m['oi'],
            'close_price'          => $m['close_price'],
            'bid_price'            => $m['bid_price'],
            'bid_qty'              => $m['bid_qty'],
            'ask_price'            => $m['ask_price'],
            'ask_qty'              => $m['ask_qty'],
            'prev_oi'              => $m['prev_oi'],
            'vega'                 => $g['vega'],
            'theta'                => $g['theta'],
            'gamma'                => $g['gamma'],
            'delta'                => $g['delta'],
            'iv'                   => $g['iv'],
            'pop'                  => $g['pop'],
            'underlying_spot_price'=> $item['underlying_spot_price'],
            'pcr'                  => $item['pcr'] ?? null,
            'captured_at'          => now()->copy()->second(0),
        ];
    }
}
