<?php

namespace App\Domain\Position\Services;

use App\Domain\Analytics\Services\OptionsAnalyticsService;
use App\Domain\Market\Services\MarketDataService;
use App\Domain\Strategy\Services\StrategyRegistry;
use App\Models\AnalyticsAuditLog;
use App\Models\Position;
use App\Models\PositionLeg;
use App\Models\PositionSnapshot;
use App\Models\RiskEvent;
use App\Models\StrategyModel;
use App\Models\Underlying;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class PositionService
{
    public function __construct(
        protected OptionsAnalyticsService $analyticsService,
        protected StrategyRegistry $strategyRegistry,
        protected MarketDataService $marketData
    ) {}

    /**
     * Get or create default underlyings (NIFTY, BANKNIFTY, FINNIFTY)
     */
    public function ensureDefaultUnderlyings(): void
    {
        $indices = config('options.indices', []);
        foreach ($indices as $sym => $info) {
            Underlying::firstOrCreate(
                ['symbol' => $sym],
                [
                    'name' => $info['name'],
                    'exchange' => $info['exchange'],
                    'segment' => 'INDEX',
                    'tick_size' => $info['tick_size'],
                    'lot_size' => $info['lot_size'],
                    'status' => 'ACTIVE',
                ]
            );
        }
    }

    /**
     * Sync registered strategies to strategies database table.
     */
    public function syncStrategies(): void
    {
        foreach ($this->strategyRegistry->all() as $strat) {
            StrategyModel::updateOrCreate(
                ['key' => $strat->key()],
                [
                    'name' => $strat->name(),
                    'description' => $strat->description(),
                    'category' => $strat->category(),
                    'version' => '1.0',
                    'configuration' => $strat->characteristics()->toArray(),
                    'status' => 'ACTIVE',
                ]
            );
        }
    }

    /**
     * Seed or get demo positions for the user to explore immediately.
     */
    public function seedDemoPositions(): Position
    {
        $this->ensureDefaultUnderlyings();
        $this->syncStrategies();

        $nifty = Underlying::where('symbol', 'NIFTY')->first();
        $straddleModel = StrategyModel::where('key', 'short_straddle')->first();
        $quote = $this->marketData->getQuote('NIFTY');

        // Check if demo position already exists
        $existing = Position::where('name', 'NIFTY Intraday Short Straddle')->first();
        if ($existing) {
            return $existing;
        }

        // Create Short Straddle as canonical first strategy position
        $stratDef = $this->strategyRegistry->get('short_straddle');
        $legs = $stratDef->defaultLegTemplates(
            spot: $quote->spot,
            strikeStep: 50.0,
            dte: 3.2,
            iv: 0.138,
            quantity: 65
        );

        $initialCredit = 0.0;
        foreach ($legs as $l) {
            $initialCredit += ($l['entry_price'] * $l['quantity']);
        }

        $position = Position::create([
            'strategy_id' => $straddleModel?->id,
            'underlying_id' => $nifty?->id,
            'name' => 'NIFTY Intraday Short Straddle',
            'status' => 'OPEN',
            'entry_timestamp' => Carbon::now('Asia/Kolkata')->subHours(3),
            'entry_underlying_price' => $quote->spot - 35.0, // entered 35 pts lower
            'current_underlying_price' => $quote->spot,
            'entry_iv' => 0.1350,
            'current_iv' => 0.1385,
            'initial_credit' => $initialCredit,
            'current_value' => $initialCredit - 1200.0,
            'realized_pnl' => 0.0,
            'unrealized_pnl' => 1200.0,
            'total_pnl' => 1200.0,
            'metadata' => [
                'strategy_key' => 'short_straddle',
                'entry_assumptions' => [
                    'spot' => $quote->spot - 35.0,
                    'iv' => 0.1350,
                    'delta' => 1.5,
                    'gamma' => -0.0035,
                    'theta' => 285.0,
                    'vega' => -180.0,
                    'dte' => 3.4,
                    'expected_move' => 210.0,
                ],
            ],
        ]);

        foreach ($legs as $l) {
            PositionLeg::create([
                'position_id' => $position->id,
                'side' => $l['side'],
                'quantity' => $l['quantity'],
                'entry_price' => $l['entry_price'],
                'current_price' => $l['current_price'],
                'entry_timestamp' => $position->entry_timestamp,
                'status' => 'OPEN',
                'metadata' => [
                    'option_type' => $l['option_type'],
                    'strike' => $l['strike'],
                    'dte' => $l['dte'],
                    'iv' => $l['iv'],
                ],
            ]);
        }

        // Also seed an Iron Condor and a Bull Call Spread to prove strategy independence
        $this->seedAdditionalStrategyPositions($nifty);

        return $position;
    }

    protected function seedAdditionalStrategyPositions(Underlying $nifty): void
    {
        $quote = $this->marketData->getQuote('NIFTY');

        // 1. Iron Condor
        if (!Position::where('name', 'NIFTY Weekly Iron Condor')->exists()) {
            $icModel = StrategyModel::where('key', 'iron_condor')->first();
            $icDef = $this->strategyRegistry->get('iron_condor');
            $icLegs = $icDef->defaultLegTemplates($quote->spot, 50.0, 3.2, 0.14, 65);

            $icPos = Position::create([
                'strategy_id' => $icModel?->id,
                'underlying_id' => $nifty->id,
                'name' => 'NIFTY Weekly Iron Condor',
                'status' => 'OPEN',
                'entry_timestamp' => Carbon::now('Asia/Kolkata')->subHours(5),
                'entry_underlying_price' => $quote->spot - 15.0,
                'current_underlying_price' => $quote->spot,
                'entry_iv' => 0.1410,
                'current_iv' => 0.1385,
                'initial_credit' => 3800.0,
                'current_value' => 3100.0,
                'realized_pnl' => 0.0,
                'unrealized_pnl' => 700.0,
                'total_pnl' => 700.0,
                'metadata' => [
                    'strategy_key' => 'iron_condor',
                    'entry_assumptions' => [
                        'spot' => $quote->spot - 15.0,
                        'iv' => 0.1410,
                        'delta' => 0.2,
                        'dte' => 3.5,
                        'expected_move' => 215.0,
                    ],
                ],
            ]);
            foreach ($icLegs as $l) {
                PositionLeg::create([
                    'position_id' => $icPos->id,
                    'side' => $l['side'],
                    'quantity' => $l['quantity'],
                    'entry_price' => $l['entry_price'],
                    'current_price' => $l['current_price'],
                    'entry_timestamp' => $icPos->entry_timestamp,
                    'status' => 'OPEN',
                    'metadata' => [
                        'option_type' => $l['option_type'],
                        'strike' => $l['strike'],
                        'dte' => $l['dte'],
                        'iv' => $l['iv'],
                    ],
                ]);
            }
        }

        // 2. Bull Call Spread
        if (!Position::where('name', 'NIFTY Bull Call Spread')->exists()) {
            $bcsModel = StrategyModel::where('key', 'bull_call_spread')->first();
            $bcsDef = $this->strategyRegistry->get('bull_call_spread');
            $bcsLegs = $bcsDef->defaultLegTemplates($quote->spot, 50.0, 3.2, 0.14, 65);

            $bcsPos = Position::create([
                'strategy_id' => $bcsModel?->id,
                'underlying_id' => $nifty->id,
                'name' => 'NIFTY Bull Call Spread',
                'status' => 'OPEN',
                'entry_timestamp' => Carbon::now('Asia/Kolkata')->subDay(),
                'entry_underlying_price' => $quote->spot - 80.0,
                'current_underlying_price' => $quote->spot,
                'entry_iv' => 0.1340,
                'current_iv' => 0.1385,
                'initial_credit' => -4200.0, // Debit
                'current_value' => 6100.0,
                'realized_pnl' => 0.0,
                'unrealized_pnl' => 1900.0,
                'total_pnl' => 1900.0,
                'metadata' => [
                    'strategy_key' => 'bull_call_spread',
                    'entry_assumptions' => [
                        'spot' => $quote->spot - 80.0,
                        'iv' => 0.1340,
                        'delta' => 14.5,
                        'dte' => 4.2,
                        'expected_move' => 240.0,
                    ],
                ],
            ]);
            foreach ($bcsLegs as $l) {
                PositionLeg::create([
                    'position_id' => $bcsPos->id,
                    'side' => $l['side'],
                    'quantity' => $l['quantity'],
                    'entry_price' => $l['entry_price'],
                    'current_price' => $l['current_price'],
                    'entry_timestamp' => $bcsPos->entry_timestamp,
                    'status' => 'OPEN',
                    'metadata' => [
                        'option_type' => $l['option_type'],
                        'strike' => $l['strike'],
                        'dte' => $l['dte'],
                        'iv' => $l['iv'],
                    ],
                ]);
            }
        }
    }

    /**
     * Create a new strategy position from preset or custom legs.
     */
    public function createPositionFromStrategy(
        string $strategyKey,
        string $underlyingSymbol = 'NIFTY',
        ?string $name = null,
        ?float $customSpot = null
    ): Position {
        $this->ensureDefaultUnderlyings();
        $this->syncStrategies();

        $stratDef = $this->strategyRegistry->get($strategyKey);
        $underlying = Underlying::where('symbol', $underlyingSymbol)->firstOrFail();
        $stratModel = StrategyModel::where('key', $strategyKey)->first();

        $quote = $this->marketData->getQuote($underlyingSymbol);
        $spot = $customSpot ?? $quote->spot;

        $step = (float)(config("options.indices.{$underlyingSymbol}.strike_step", 50.0));
        $lotSize = (int)(config("options.indices.{$underlyingSymbol}.lot_size", 65));

        $legs = $stratDef->defaultLegTemplates(
            spot: $spot,
            strikeStep: $step,
            dte: 3.2,
            iv: $quote->iv,
            quantity: $lotSize
        );

        $initialOutlay = 0.0;
        foreach ($legs as $l) {
            $cost = $l['entry_price'] * $l['quantity'];
            $initialOutlay += ($l['side'] === 'SELL') ? $cost : -$cost;
        }

        $posName = $name ?? ($underlyingSymbol . ' ' . $stratDef->name() . ' ' . Carbon::now()->format('d M H:i'));

        $position = Position::create([
            'strategy_id' => $stratModel?->id,
            'underlying_id' => $underlying->id,
            'name' => $posName,
            'status' => 'OPEN',
            'entry_timestamp' => Carbon::now('Asia/Kolkata'),
            'entry_underlying_price' => $spot,
            'current_underlying_price' => $spot,
            'entry_iv' => $quote->iv,
            'current_iv' => $quote->iv,
            'initial_credit' => $initialOutlay,
            'current_value' => $initialOutlay,
            'realized_pnl' => 0.0,
            'unrealized_pnl' => 0.0,
            'total_pnl' => 0.0,
            'metadata' => [
                'strategy_key' => $strategyKey,
                'entry_assumptions' => [
                    'spot' => $spot,
                    'iv' => $quote->iv,
                    'dte' => 3.2,
                ],
            ],
        ]);

        foreach ($legs as $l) {
            PositionLeg::create([
                'position_id' => $position->id,
                'side' => $l['side'],
                'quantity' => $l['quantity'],
                'entry_price' => $l['entry_price'],
                'current_price' => $l['current_price'],
                'entry_timestamp' => $position->entry_timestamp,
                'status' => 'OPEN',
                'metadata' => [
                    'option_type' => $l['option_type'],
                    'strike' => $l['strike'],
                    'dte' => $l['dte'],
                    'iv' => $l['iv'],
                ],
            ]);
        }

        // Record Audit Log
        AnalyticsAuditLog::create([
            'position_id' => $position->id,
            'action' => 'POSITION_CREATED',
            'new_values' => ['strategy' => $strategyKey, 'legs_count' => count($legs), 'spot' => $spot],
            'reason' => 'Created via Strategy Preset',
        ]);

        return $position;
    }

    /**
     * Normalize stored DB legs into analytics format.
     */
    public function getNormalizedLegs(Position $position): array
    {
        $normalized = [];
        $position->loadMissing('legs');

        foreach ($position->legs as $leg) {
            $meta = $leg->metadata ?? [];
            $normalized[] = [
                'id' => $leg->id,
                'side' => $leg->side,
                'option_type' => $meta['option_type'] ?? 'CE',
                'strike' => (float)($meta['strike'] ?? $position->current_underlying_price),
                'quantity' => $leg->quantity,
                'entry_price' => (float)$leg->entry_price,
                'current_price' => (float)($leg->current_price ?? $leg->entry_price),
                'dte' => (float)($meta['dte'] ?? 3.0),
                'iv' => (float)($meta['iv'] ?? 0.14),
                'status' => $leg->status,
            ];
        }

        return $normalized;
    }

    /**
     * Compute full live analytics for a saved Position.
     */
    public function getPositionAnalytics(Position $position): array
    {
        $underlyingSymbol = $position->underlying?->symbol ?? 'NIFTY';
        $quote = $this->marketData->getQuote($underlyingSymbol);
        $legs = $this->getNormalizedLegs($position);

        $strategyKey = $position->metadata['strategy_key'] ?? ($position->strategy?->key ?? 'custom');
        $entryAssumptions = $position->metadata['entry_assumptions'] ?? [
            'spot' => (float)$position->entry_underlying_price,
            'iv' => (float)$position->entry_iv,
        ];

        return $this->analyticsService->analyze($legs, $quote->spot, [
            'underlying' => $underlyingSymbol,
            'strategy_key' => $strategyKey,
            'entry_assumptions' => $entryAssumptions,
            'status' => $position->status,
        ]);
    }
}
