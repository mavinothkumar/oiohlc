<?php

namespace App\Http\Controllers;

use App\Models\BacktestStrategy;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class BasketBuilderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'strategy_id' => ['nullable', 'integer'],
            'atm_strike' => ['nullable', 'numeric', 'min:0'],
            'lot_multiplier' => ['nullable', 'integer', 'min:1'],
        ]);

        $filters = array_merge([
            'strategy_id' => null,
            'atm_strike' => null,
            'lot_multiplier' => 1,
        ], $filters);

        $strategy = null;
        $strategyLegs = collect();
        $legs = collect();

        if (filled($filters['strategy_id'])) {
            $strategy = BacktestStrategy::query()
                                        ->findOrFail((int) $filters['strategy_id']);

            $definition = $this->decodeJson(
                $strategy->getAttribute('definition')
            );

            $strategyLegs = collect($definition['legs'] ?? []);
        }

        if ($strategyLegs->isNotEmpty() && filled($filters['atm_strike'])) {
            $atmStrike = (float) $filters['atm_strike'];
            $lotMultiplier = (int) $filters['lot_multiplier'];

            $legs = $strategyLegs
                ->values()
                ->map(function (array $leg, int $index) use (
                    $atmStrike,
                    $lotMultiplier,
                ): array {
                    $optionType = strtoupper((string) ($leg['option_type'] ?? ''));
                    $moneyness = strtoupper((string) ($leg['moneyness'] ?? 'ATM'));
                    $strikeOffset = (float) ($leg['strike_offset'] ?? 0);
                    $configuredLots = (int) ($leg['lots'] ?? 1);

                    return [
                        'leg_number' => $index + 1,
                        'option_type' => in_array($optionType, ['CE', 'PE'], true)
                            ? $optionType
                            : '—',
                        'strike' => $this->resolveStrike(
                            atmStrike: $atmStrike,
                            optionType: $optionType,
                            moneyness: $moneyness,
                            strikeOffset: $strikeOffset,
                        ),
                        'lots' => $configuredLots * $lotMultiplier,
                    ];
                });
        }

        // --- ADD THIS BLOCK HERE ---
        $ceLegs = $legs->where('option_type', 'CE')->values();
        $peLegs = $legs->where('option_type', 'PE')->values();

        $optionChain = $legs->groupBy('strike')
                            ->sortKeys()
                            ->map(function ($groupLegs, $strike) {
                                $ceLeg = $groupLegs->firstWhere('option_type', 'CE');
                                $peLeg = $groupLegs->firstWhere('option_type', 'PE');

                                return [
                                    'strike' => $strike,
                                    'ce_lots' => $ceLeg ? $ceLeg['lots'] : 0,
                                    'pe_lots' => $peLeg ? $peLeg['lots'] : 0,
                                ];
                            })
                            ->values();
        // ---------------------------

        return view('backtests.basket-builder', [
            'strategies' => BacktestStrategy::query()
                                            ->where('is_active', true)
                                            ->orderBy('name')
                                            ->get(),
            'filters' => $filters,
            'strategy' => $strategy,
            'strategyLegs' => $strategyLegs,
            'legs' => $legs,
            // ADD THESE THREE LINES BELOW:
            'ceLegs' => $ceLegs,
            'peLegs' => $peLegs,
            'optionChain' => $optionChain,
        ]);
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function resolveStrike(
        float $atmStrike,
        string $optionType,
        string $moneyness,
        float $strikeOffset,
    ): int|float {
        $moneynessAdjustment = match ($moneyness) {
            'ITM' => -50 - $strikeOffset,
            'OTM' => 50 + $strikeOffset,
            default => 0,
        };

        return $this->normaliseStrike($atmStrike + $moneynessAdjustment);
    }

    private function normaliseStrike(float $strike): int|float
    {
        return fmod($strike, 1.0) === 0.0
            ? (int) $strike
            : round($strike, 2);
    }
}
