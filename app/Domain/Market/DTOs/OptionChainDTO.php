<?php

namespace App\Domain\Market\DTOs;

class OptionChainDTO
{
    /**
     * @param string $underlying
     * @param float $spot
     * @param string $expiry
     * @param float $dte
     * @param array<int, array{
     *     strike: float,
     *     ce: array,
     *     pe: array
     * }> $strikes
     * @param string $timestamp
     * @param string $source
     */
    public function __construct(
        public readonly string $underlying,
        public readonly float $spot,
        public readonly string $expiry,
        public readonly float $dte,
        public readonly array $strikes,
        public readonly string $timestamp,
        public readonly string $source
    ) {}

    public function toArray(): array
    {
        return [
            'underlying' => $this->underlying,
            'spot' => round($this->spot, 2),
            'expiry' => $this->expiry,
            'dte' => $this->dte,
            'strikes' => $this->strikes,
            'timestamp' => $this->timestamp,
            'source' => $this->source,
        ];
    }
}
