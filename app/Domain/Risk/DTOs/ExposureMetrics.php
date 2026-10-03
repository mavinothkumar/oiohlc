<?php

namespace App\Domain\Risk\DTOs;

use App\Domain\Greeks\DTOs\GreekSet;

class ExposureMetrics
{
    public function __construct(
        public readonly GreekSet $netGreeks,
        public readonly float $absoluteDelta,
        public readonly float $longExposureValue,
        public readonly float $shortExposureValue,
        public readonly float $ceExposureValue,
        public readonly float $peExposureValue,
        public readonly array $legContributions = []
    ) {}

    public function toArray(): array
    {
        return [
            'net_greeks' => $this->netGreeks->toArray(),
            'absolute_delta' => round($this->absoluteDelta, 2),
            'long_exposure_value' => round($this->longExposureValue, 2),
            'short_exposure_value' => round($this->shortExposureValue, 2),
            'ce_exposure_value' => round($this->ceExposureValue, 2),
            'pe_exposure_value' => round($this->peExposureValue, 2),
            'leg_contributions' => $this->legContributions,
        ];
    }
}
