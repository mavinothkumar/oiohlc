<?php

namespace App\Domain\Market\DTOs;

class QuoteDTO
{
    public function __construct(
        public readonly string $symbol,
        public readonly float $spot,
        public readonly float $bid,
        public readonly float $ask,
        public readonly float $high,
        public readonly float $low,
        public readonly float $open,
        public readonly float $close,
        public readonly float $change,
        public readonly float $changePct,
        public readonly int $volume,
        public readonly float $iv,
        public readonly float $vwap,
        public readonly string $timestamp,
        public readonly string $source
    ) {}

    public function toArray(): array
    {
        return [
            'symbol' => $this->symbol,
            'spot' => round($this->spot, 2),
            'bid' => round($this->bid, 2),
            'ask' => round($this->ask, 2),
            'high' => round($this->high, 2),
            'low' => round($this->low, 2),
            'open' => round($this->open, 2),
            'close' => round($this->close, 2),
            'change' => round($this->change, 2),
            'change_pct' => round($this->changePct, 2),
            'volume' => $this->volume,
            'iv' => round($this->iv * 100, 2),
            'vwap' => round($this->vwap, 2),
            'timestamp' => $this->timestamp,
            'source' => $this->source,
        ];
    }
}
