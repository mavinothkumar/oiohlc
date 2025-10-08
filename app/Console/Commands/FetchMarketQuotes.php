<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\UpstoxMarketQuoteService;

class FetchMarketQuotes extends Command
{
    protected $signature = 'market:fetch-quotes';
    protected $description = 'Fetch and store full market quotes for all instruments';

    public function handle(UpstoxMarketQuoteService $service)
    {
        // Example instrument keys, can fetch from DB or config
        $instrumentKeys = [
            'NSE_FO:NIFTY25O2025000CE',
            // Add more
        ];

        foreach ($instrumentKeys as $key) {
            $service->fetchAndStoreQuote($key);
        }

        $this->info('Market quotes fetched and stored.');
    }
}

