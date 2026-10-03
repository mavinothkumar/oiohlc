<?php

namespace App\Domain\Greeks\DTOs;

class GreekSet
{
    public function __construct(
        public readonly float $delta,
        public readonly float $gamma,
        public readonly float $theta,
        public readonly float $vega,
        public readonly float $rho,
        public readonly float $theoreticalPrice = 0.0,
        public readonly float $iv = 0.0,
    ) {}

    public static function zero(): self
    {
        return new self(0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0);
    }

    public function add(GreekSet $other): self
    {
        return new self(
            delta: $this->delta + $other->delta,
            gamma: $this->gamma + $other->gamma,
            theta: $this->theta + $other->theta,
            vega: $this->vega + $other->vega,
            rho: $this->rho + $other->rho,
            theoreticalPrice: $this->theoreticalPrice + $other->theoreticalPrice,
            iv: $this->iv, // blended or parent
        );
    }

    public function toArray(): array
    {
        return [
            'delta' => round($this->delta, 4),
            'gamma' => round($this->gamma, 6),
            'theta' => round($this->theta, 4),
            'vega' => round($this->vega, 4),
            'rho' => round($this->rho, 4),
            'theoretical_price' => round($this->theoreticalPrice, 2),
            'iv' => round($this->iv, 4),
        ];
    }
}
