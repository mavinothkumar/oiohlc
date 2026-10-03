<?php

namespace App\Domain\Market\Contracts;

use App\Domain\Market\DTOs\OptionChainDTO;
use App\Domain\Market\DTOs\QuoteDTO;

interface MarketDataProviderInterface
{
    public function name(): string;

    public function quote(string $symbol): QuoteDTO;

    public function optionChain(string $underlying, ?string $expiry = null): OptionChainDTO;

    public function historical(string $symbol, string $timeframe = '1D', int $limit = 30): array;
}
