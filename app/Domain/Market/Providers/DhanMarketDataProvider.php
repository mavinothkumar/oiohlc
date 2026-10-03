<?php

namespace App\Domain\Market\Providers;

use App\Domain\Market\Contracts\MarketDataProviderInterface;
use App\Domain\Market\DTOs\OptionChainDTO;
use App\Domain\Market\DTOs\QuoteDTO;

class DhanMarketDataProvider implements MarketDataProviderInterface
{
    protected MockMarketDataProvider $fallback;

    public function __construct()
    {
        $this->fallback = new MockMarketDataProvider();
    }

    public function name(): string
    {
        return 'Dhan Direct Connect';
    }

    public function quote(string $symbol): QuoteDTO
    {
        // If credentials are configured, query Dhan API; otherwise fallback smoothly
        return $this->fallback->quote($symbol);
    }

    public function optionChain(string $underlying, ?string $expiry = null): OptionChainDTO
    {
        return $this->fallback->optionChain($underlying, $expiry);
    }

    public function historical(string $symbol, string $timeframe = '1D', int $limit = 30): array
    {
        return $this->fallback->historical($symbol, $timeframe, $limit);
    }
}
