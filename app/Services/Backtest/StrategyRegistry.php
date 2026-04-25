<?php
// app/Services/Backtest/StrategyRegistry.php

namespace App\Services\Backtest;

use App\Services\Backtest\Contracts\BacktestStrategy;
use App\Services\Backtest\Strategies\AtmStraddleStrategy;
use App\Services\Backtest\Strategies\FifteenMinBreakoutStrategy;
use App\Services\Backtest\Strategies\FirstCandleBreakoutStrategy;
use App\Services\Backtest\Strategies\FixedOffsetStrategy;
use App\Services\Backtest\Strategies\IronCondorLadderStrategy;
use App\Services\Backtest\Strategies\NearStraddleStrategy;
use App\Services\Backtest\Strategies\OtmStrangleStrategy;
use App\Services\Backtest\Strategies\SmartBalancedStrategy;
use InvalidArgumentException;

class StrategyRegistry {
    /**
     * Register strategies here — name => class.
     * Adding a new strategy = add one line here + create the class.
     */
    private static array $registry = [
        //'fixed_offset'    => FixedOffsetStrategy::class,
        'strangle_straddle'     => FixedOffsetStrategy::class,
        'smart_balanced'        => SmartBalancedStrategy::class,
        'first_candle_breakout' => FirstCandleBreakoutStrategy::class,
        'atm_straddle'          => AtmStraddleStrategy::class,
        'near_straddle'         => NearStraddleStrategy::class,
        'otm_strangle'          => OtmStrangleStrategy::class,
        '15min_breakout'        => FifteenMinBreakoutStrategy::class,
        'iron_condor_ladder'    => IronCondorLadderStrategy::class,
    ];

    public static function resolve( string $name ): BacktestStrategy {
        $name = strtolower( str_replace( ' ', '_', trim( $name ) ) );

        if ( ! isset( self::$registry[ $name ] ) ) {
            throw new InvalidArgumentException(
                "Unknown strategy \"{$name}\".\n" .
                "Available: " . implode( ', ', self::available() )
            );
        }

        return new self::$registry[ $name ]();
    }

    public static function available(): array {
        return array_keys( self::$registry );
    }

    public static function exists( string $name ): bool {
        return isset( self::$registry[ strtolower( $name ) ] );
    }
}
