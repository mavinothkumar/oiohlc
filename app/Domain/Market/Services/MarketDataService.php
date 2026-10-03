<?php

namespace App\Domain\Market\Services;

use App\Domain\Market\Contracts\MarketDataProviderInterface;
use App\Domain\Market\DTOs\OptionChainDTO;
use App\Domain\Market\DTOs\QuoteDTO;
use App\Domain\Market\Providers\MockMarketDataProvider;
use Illuminate\Support\Facades\Cache;

class MarketDataService
{
    protected MarketDataProviderInterface $provider;

    public function __construct()
    {
        $providerKey = config('market.default_provider', 'mock');
        $providerClass = config("market.providers.{$providerKey}.class", MockMarketDataProvider::class);

        if (class_exists($providerClass)) {
            $this->provider = new $providerClass();
        } else {
            $this->provider = new MockMarketDataProvider();
        }
    }

    public function getProviderName(): string
    {
        return $this->provider->name();
    }

    public function getQuote(string $symbol): QuoteDTO
    {
        $ttl = config('market.cache_ttl_seconds', 15);
        $cacheKey = "market_quote_{$symbol}";

        return Cache::remember($cacheKey, $ttl, function () use ($symbol) {
            return $this->provider->quote($symbol);
        });
    }

    public function getOptionChain(string $underlying, ?string $expiry = null): OptionChainDTO
    {
        $ttl = config('market.cache_ttl_seconds', 15);
        $cacheKey = "market_chain_{$underlying}_" . ($expiry ?? 'default');

        return Cache::remember($cacheKey, $ttl, function () use ($underlying, $expiry) {
            return $this->provider->optionChain($underlying, $expiry);
        });
    }

    public function getHistorical(string $symbol, string $timeframe = '1D', int $limit = 30): array
    {
        return $this->provider->historical($symbol, $timeframe, $limit);
    }
}
