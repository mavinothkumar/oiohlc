<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\ExpiredExpiry;
use App\Models\ExpiredOptionContract;
use App\Models\ExpiredOhlc;
use Carbon\Carbon;

class SyncExpiredFutureOhlc extends Command
{
    protected $signature = 'upstox:sync-expired-future-ohlc';
    protected $description = 'Fetch expired OHLC (day & 5minute) for FUT monthly expiries and store in expired_ohlc';

    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        parent::__construct();

        $this->baseUrl = config('services.upstox.base_url', 'https://api.upstox.com/v2');
        $this->token   = config('services.upstox.access_token');
    }

    public function handle(): int
    {
        if (! $this->token) {
            $this->error('Upstox access token not configured.');
            return self::FAILURE;
        }

        // 1) Get all FUT expiries sorted
        $futExpiries = ExpiredExpiry::query()
                                    ->where('instrument_type', 'FUT')
                                    ->orderBy('expiry_date')
                                    ->get();

        if ($futExpiries->isEmpty()) {
            $this->info('No FUT expiries found.');
            return self::SUCCESS;
        }

        // Manual first "from" date for the very first FUT expiry in your dataset
        // Example given: for 2024-10-31 you want from 2024-09-27
        $manualFirstFrom = Carbon::parse('2024-09-27'); // adjust as needed

        $previousExpiryDate = null;

        foreach ($futExpiries as $index => $expiryRow) {
            $currentExpiry = Carbon::parse($expiryRow->expiry_date);

            if ($index === 0) {
                // First expiry in the series
                $fromDate = $manualFirstFrom->copy();         // e.g. 2024-09-27
                $toDate   = $currentExpiry->copy();           // e.g. 2024-10-31
            } else {
                // From = next day after previous expiry
                $fromDate = $previousExpiryDate->copy()->addDay(); // e.g. 2024-11-01
                $toDate   = $currentExpiry->copy();                // e.g. 2024-11-28
            }

            // Save for next loop
            $previousExpiryDate = $currentExpiry->copy();

            $this->info("Expiry {$currentExpiry->toDateString()} => range {$fromDate->toDateString()} to {$toDate->toDateString()}");

            // 2) Get the FUT contract corresponding to this expiry
            // Assuming expired_option_contracts has a FUT row linked via expired_expiry_id
            $contract = ExpiredOptionContract::query()
                                             ->where('expired_expiry_id', $expiryRow->id)
                                             ->where('instrument_type', 'FUT')
                                             ->first();

            if (! $contract) {
                $this->warn("No FUT contract row found for expired_expiry_id={$expiryRow->id}");
                continue;
            }

            // For expired OHLC API we must use expired_instrument_key as returned by expired contracts API.[web:2][file:1]
            $expiredInstrumentKey = $contract->instrument_key;

            // 3) Fetch and store DAY candles
            $dayCandles = $this->fetchExpiredCandles(
                $expiredInstrumentKey,
                'day',
                $fromDate->toDateString(),
                $toDate->toDateString()
            );
            $this->storeCandles($contract, $dayCandles, 'day');

            // 4) Fetch and store 5MIN candles
            $fiveMinCandles = $this->fetchExpiredCandles(
                $expiredInstrumentKey,
                '5minute',
                $fromDate->toDateString(),
                $toDate->toDateString()
            );
            $this->storeCandles($contract, $fiveMinCandles, '5minute');
        }

        $this->info('Expired FUT OHLC synced for day & 5minute.');
        return self::SUCCESS;
    }

    /**
     * Call Upstox Get Expired Historical Candle Data API.
     *
     * Path pattern (per docs):
     * /expired-instruments/historical-candle/{expired_instrument_key}/{interval}/{to_date}/{from_date}
     * where candles = [ timestamp, open, high, low, close, volume, open_interest ].[web:2][file:1]
     */
    protected function fetchExpiredCandles(
        string $expiredInstrumentKey,
        string $interval,
        string $fromDate,
        string $toDate
    ): array {
        $url = sprintf(
            '%s/expired-instruments/historical-candle/%s/%s/%s/%s',
            $this->baseUrl,
            urlencode($expiredInstrumentKey),
            $interval,
            $toDate,
            $fromDate
        );

        $this->info("Fetching {$interval} candles for {$expiredInstrumentKey} from {$fromDate} to {$toDate}");

        $response = Http::withToken($this->token)
                        ->acceptJson()
                        ->get($url);

        if ($response->failed()) {
            $this->error("Failed for {$expiredInstrumentKey} {$interval}: " . $response->body());
            return [];
        }

        return $response->json('data.candles') ?? [];
    }

    /**
     * Store candles into expired_ohlc.
     */
    protected function storeCandles(ExpiredOptionContract $contract, array $candles, string $interval): void
    {
        foreach ($candles as $candle) {
            // candle: [ timestamp, open, high, low, close, volume, open_interest ][web:2][file:1]
            [$ts, $open, $high, $low, $close, $volume, $oi] = $candle;

            // Avoid duplicates per instrument_key + interval + timestamp
            $exists = ExpiredOhlc::query()
                                 ->where('instrument_key', $contract->instrument_key)
                                 ->where('interval', $interval)
                                 ->where('timestamp', $ts)
                                 ->exists();

            if ($exists) {
                continue;
            }

            ExpiredOhlc::create([
                'underlying_symbol' => $contract->underlying_symbol,
                'exchange'          => $contract->exchange ?? 'NSE',
                'expiry'            => $contract->expiry,           // FUT expiry
                'instrument_key'    => $contract->instrument_key,   // expired_instrument_key
                'instrument_type'   => 'FUT',
                'strike'            => $contract->strike_price ?? null,
                'interval'          => $interval,
                'open'              => $open,
                'high'              => $high,
                'low'               => $low,
                'close'             => $close,
                'volume'            => $volume,
                'open_interest'     => $oi,
                'timestamp'         => $ts, // API timestamp string, Laravel will cast to datetime if configured
            ]);
        }
    }
}
