<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\DailyTrend;
use App\Models\DailyTrendMeta;
use App\Models\Ohlc5mQuote;

class ProcessHlc5mQuotes extends Command
{
    protected $signature = 'trend:process-5m {--date= : Trading date YYYY-MM-DD (optional, defaults to today)}';
    protected $description = 'Process 5-minute OHLC data and populate daily_trend_meta for HLC strategy';

    public function handle(): int
    {
        // 1. Resolve trading date and working days
        $tradingDate = $this->option('date') ?: now()->toDateString();

        $days = DB::table('nse_working_days')
                  ->where(function ($q) {
                      $q->where('previous', 1)
                        ->orWhere('current', 1);
                  })
                  ->orderByDesc('id')
                  ->get();

        $previousDay = optional($days->firstWhere('previous', 1))->working_date;
        $currentDay  = optional($days->firstWhere('current', 1))->working_date;

        if (! $previousDay || ! $currentDay) {
            $this->error('Working days not configured');
            return 1;
        }

        // tracked_date is currentDay (today)
        if ($tradingDate !== (string) $currentDay) {
            $this->warn("Using working day {$currentDay} as tracked_date, ignoring option --date={$tradingDate}");
            $tradingDate = (string) $currentDay;
        }

        $this->info("Processing HLC 5m for tracked_date = {$tradingDate}, quote_date = {$previousDay}");

        // 2. Load static daily_trend for yesterday
        $dailyTrends = DailyTrend::whereDate('quote_date', $previousDay)
                                 ->whereIn('symbol_name', ['NIFTY', 'BANKNIFTY', 'SENSEX'])
                                 ->get()
                                 ->keyBy('symbol_name');

        if ($dailyTrends->isEmpty()) {
            $this->error('No DailyTrend rows found for quote_date ' . $previousDay);
            return 1;
        }

        // 3. Map symbol_name -> index trading_symbol in 5m table
        $indexMap = [
            'NIFTY'     => 'Nifty 50',
            'BANKNIFTY' => 'Nifty Bank',
            'SENSEX'    => 'BSE SENSEX',
        ];

        // 4. Build option contract descriptors (CE/PE per symbol+strike+expiry)
        $optionContracts = [];
        foreach ($dailyTrends as $symbol => $trend) {
            $strike = (float) $trend->strike;

            $expiryStr = $trend->expiry_date?->toDateString();

            $optionContracts[] = [
                'symbol_name'    => $symbol,
                'trading_symbol' => $symbol,
                'strike_price'   => $strike,
                'side'           => 'CE',
                'expiry_date'    => $expiryStr,
            ];
            $optionContracts[] = [
                'symbol_name'    => $symbol,
                'trading_symbol' => $symbol,
                'strike_price'   => $strike,
                'side'           => 'PE',
                'expiry_date'    => $expiryStr,
            ];
        }

        // 5. Fetch all relevant 5m candles for today (index + options)
        $indexSymbols = array_values($indexMap);

        $fiveMinQuotes = Ohlc5mQuote::query()
                                    ->whereDate('ts_at', $tradingDate)
                                    ->where(function ($q) use ($indexSymbols, $optionContracts) {
                                        // Index candles
                                        $q->where(function ($sub) use ($indexSymbols) {
                                            $sub->where('instrument_type', 'INDEX')
                                                ->whereIn('trading_symbol', $indexSymbols);
                                        });

                                        // Option candles
                                        $q->orWhere(function ($sub) use ($optionContracts) {
                                            $sub->whereIn('instrument_type', ['CE', 'PE'])
                                                ->where(function ($sub2) use ($optionContracts) {
                                                    foreach ($optionContracts as $c) {
                                                        $sub2->orWhere(function ($sub3) use ($c) {
                                                            $sub3->where('trading_symbol', $c['trading_symbol'])
                                                                 ->where('strike_price', $c['strike_price'])
                                                                 ->where('instrument_type', $c['side'])
                                                                 ->whereDate('expiry_date', $c['expiry_date']);
                                                        });
                                                    }
                                                });
                                        });
                                    })
                                    ->orderBy('ts_at')
                                    ->get();

        if ($fiveMinQuotes->isEmpty()) {
            $this->warn('No 5m OHLC data found for ' . $tradingDate);
            return 0;
        }

        // 6. Group by ts_at (each 5min bar)
        $barsByTime = $fiveMinQuotes->groupBy(function ($row) {
            return optional($row->ts_at)->format('Y-m-d H:i:s');
        });

        $totalInserted = 0;

        foreach ($barsByTime as $timestamp => $barRows) {
            $recordedAt = $timestamp;

            foreach ($dailyTrends as $symbol => $trend) {
                $indexTsSymbol = $indexMap[$symbol] ?? null;
                if (! $indexTsSymbol) {
                    continue;
                }

                // Index candle for this 5m bar
                $indexRow = $barRows
                    ->where('instrument_type', 'INDEX')
                    ->firstWhere('trading_symbol', $indexTsSymbol);

                if (! $indexRow) {
                    continue;
                }

                // Use last_price as primary source
                $spot = (float) ($indexRow->last_price ?? $indexRow->close ?? 0);

                $strike    = (float) $trend->strike;
                $expiryStr = $trend->expiry_date?->toDateString();

                // CE row
                $ceRow = $barRows->first(function ($row) use ($symbol, $strike, $expiryStr) {
                    return $row->instrument_type === 'CE'
                           && $row->trading_symbol === $symbol
                           && (float) $row->strike_price === $strike
                           && $row->expiry_date?->toDateString() === $expiryStr;
                });

                // PE row
                $peRow = $barRows->first(function ($row) use ($symbol, $strike, $expiryStr) {
                    return $row->instrument_type === 'PE'
                           && $row->trading_symbol === $symbol
                           && (float) $row->strike_price === $strike
                           && $row->expiry_date?->toDateString() === $expiryStr;
                });

                $ceLtp = $ceRow ? (float) ($ceRow->last_price ?? $ceRow->close ?? 0) : null;
                $peLtp = $peRow ? (float) ($peRow->last_price ?? $peRow->close ?? 0) : null;

                // 7. Build context from DailyTrend + live prices
                $context = $this->buildContextFromTrend($symbol, $trend, $spot, $ceLtp, $peLtp);

                // 8. Decision logic
                [$tradeSignal, $scenarioGroup, $triggers] = $this->decideTrade($context);

                // 9. sequence_id per symbol/day
                $sequenceId = (int) DailyTrendMeta::where('daily_trend_id', $trend->id)
                                                  ->whereDate('tracked_date', $tradingDate)
                                                  ->max('sequence_id') + 1;

                // 10. Insert meta row
                DailyTrendMeta::create([
                    'daily_trend_id' => $trend->id,
                    'tracked_date'   => $tradingDate,
                    'recorded_at'    => $recordedAt,

                    'ce_ltp'         => $ceLtp,
                    'pe_ltp'         => $peLtp,
                    'index_ltp'      => $spot,

                    'market_scenario'=> $scenarioGroup,
                    'trade_signal'   => $tradeSignal,

                    'ce_type'        => $trend->ce_type,
                    'pe_type'        => $trend->pe_type,

                    'triggers'       => $triggers,
                    'levels_crossed' => null,

                    'broken_status'   => null,
                    'first_broken_at' => null,

                    'dominant_side'   => $triggers['dominant_side'] ?? null,
                    'good_zone'       => null,

                    'sequence_id'     => $sequenceId,
                ]);

                $totalInserted++;
            }
        }

        $this->info("Inserted {$totalInserted} daily_trend_meta rows for {$tradingDate}");

        return 0;
    }

    /**
     * Build HLC context from DailyTrend row + live LTPs.
     */
    protected function buildContextFromTrend(string $symbol, DailyTrend $trend, float $spot, ?float $ceLtp, ?float $peLtp): array
    {
        // Index HLC from daily_trend (PDC = index_close)
        $PDH = (float) $trend->index_high;
        $PDL = (float) $trend->index_low;
        $PDC = (float) $trend->index_close;

        // CE HLC
        $CE_PDL = (float) $trend->ce_low;
        $CE_PDH = (float) $trend->ce_high;
        $CE_PDC = (float) $trend->ce_close;

        // PE HLC
        $PE_PDL = (float) $trend->pe_low;
        $PE_PDH = (float) $trend->pe_high;
        $PE_PDC = (float) $trend->pe_close;

        // R/S
        $MinRes = (float) $trend->min_r;
        $MaxRes = (float) $trend->max_r;
        $MinSup = (float) $trend->min_s;
        $MaxSup = (float) $trend->max_s;

        $spotAbovePdc = $spot > $PDC;
        $spotBelowPdc = $spot < $PDC;

        $spotBreakMinRes = $spot > $MinRes;
        $spotBreakMaxRes = $spot > $MaxRes;

        $spotBreakMinSup = $spot < $MinSup;
        $spotBreakMaxSup = $spot < $MaxSup;

        $ceAbovePdh = $ceLtp !== null && $ceLtp > $CE_PDH;
        $peAbovePdh = $peLtp !== null && $peLtp > $PE_PDH;

        // spot_near_pdc with symbol-based tolerance
        $tolerance = match ($symbol) {
            'NIFTY', 'FINNIFTY'     => 10,
            'BANKNIFTY', 'SENSEX'   => 20,
            default                 => 10,
        };
        $spotNearPdc = abs($spot - $PDC) <= $tolerance;

        $ceType = (string) $trend->ce_type;
        $peType = (string) $trend->pe_type;

        $csPanic = str_starts_with($ceType, 'Panic');
        $psPanic = str_starts_with($peType, 'Panic');
        $csPb    = str_starts_with($ceType, 'Profit');
        $psPb    = str_starts_with($peType, 'Profit');

        $dominantSide = 'NONE';
        if ($csPanic && ! $psPanic) {
            $dominantSide = 'CALL';
        } elseif ($psPanic && ! $csPanic) {
            $dominantSide = 'PUT';
        } elseif ($csPb && $psPb) {
            $dominantSide = 'BOTH_PB';
        }

        return [
            'symbol'             => $symbol,
            'spot'               => $spot,
            'ce_ltp'             => $ceLtp,
            'pe_ltp'             => $peLtp,
            'PDH'                => $PDH,
            'PDL'                => $PDL,
            'PDC'                => $PDC,
            'CE_PDL'             => $CE_PDL,
            'CE_PDH'             => $CE_PDH,
            'CE_PDC'             => $CE_PDC,
            'PE_PDL'             => $PE_PDL,
            'PE_PDH'             => $PE_PDH,
            'PE_PDC'             => $PE_PDC,
            'MinRes'             => $MinRes,
            'MaxRes'             => $MaxRes,
            'MinSup'             => $MinSup,
            'MaxSup'             => $MaxSup,
            'spot_above_pdc'     => $spotAbovePdc,
            'spot_below_pdc'     => $spotBelowPdc,
            'spot_break_min_res' => $spotBreakMinRes,
            'spot_break_max_res' => $spotBreakMaxRes,
            'spot_break_min_sup' => $spotBreakMinSup,
            'spot_break_max_sup' => $spotBreakMaxSup,
            'ce_above_pdh'       => $ceAbovePdh,
            'pe_above_pdh'       => $peAbovePdh,
            'spot_near_pdc'      => $spotNearPdc,
            'ce_type'            => $ceType,
            'pe_type'            => $peType,
            'cs_panic'           => $csPanic,
            'ps_panic'           => $psPanic,
            'cs_pb'              => $csPb,
            'ps_pb'              => $psPb,
            'dominant_side'      => $dominantSide,
        ];
    }

    /**
     * Apply CSP-PSPB / CSPB-PSP / BOTHPB logic.
     */
    protected function decideTrade(array $c): array
    {
        $tradeSignal   = null;
        $scenarioGroup = null;

        $triggers = [
            'spot_break_min_res' => $c['spot_break_min_res'],
            'spot_break_max_res' => $c['spot_break_max_res'],
            'spot_break_min_sup' => $c['spot_break_min_sup'],
            'spot_break_max_sup' => $c['spot_break_max_sup'],
            'spot_near_pdc'      => $c['spot_near_pdc'],
            'spot_above_pdc'     => $c['spot_above_pdc'],
            'spot_below_pdc'     => $c['spot_below_pdc'],
            'ce_above_pdh'       => $c['ce_above_pdh'],
            'pe_above_pdh'       => $c['pe_above_pdh'],
            'cs_panic'           => $c['cs_panic'],
            'ps_panic'           => $c['ps_panic'],
            'cs_pb'              => $c['cs_pb'],
            'ps_pb'              => $c['ps_pb'],
            'dominant_side'      => $c['dominant_side'],
        ];

        $spot   = $c['spot'];
        $PDH    = $c['PDH'];
        $PDL    = $c['PDL'];
        $PDC    = $c['PDC'];
        $ceLtp  = $c['ce_ltp'];
        $peLtp  = $c['pe_ltp'];
        $CE_PDL = $c['CE_PDL'];
        $CE_PDH = $c['CE_PDH'];
        $PE_PDC = $c['PE_PDC'];
        $PE_PDH = $c['PE_PDH'];
        $MinRes = $c['MinRes'];
        $MinSup = $c['MinSup'];

        // 1. CSP-PSPB (Call Seller Panic + Put Seller Profit Booking)
        if ($c['cs_panic'] && $c['ps_pb']) {
            $scenarioGroup = 'CSP-PSPB';

            // Scenario 1: spot > MinRes and <= MaxRes → BUY_CE
            if ($c['spot_break_min_res'] && ! $c['spot_break_max_res']) {
                $tradeSignal = 'BUY_CE';
                return [$tradeSignal, $scenarioGroup, $triggers];
            }

            // Scenario 2: spot > PDH and CE above PDH → BUY_CE
            if ($spot > $PDH && $c['ce_above_pdh']) {
                $tradeSignal = 'BUY_CE';
                return [$tradeSignal, $scenarioGroup, $triggers];
            }

            // Scenario 3: spot near PDC → BUY_CE
            if ($c['spot_near_pdc']) {
                $tradeSignal = 'BUY_CE';
                return [$tradeSignal, $scenarioGroup, $triggers];
            }

            // Scenario 4: optional complex CSP pattern can go here

            // Scenario 5: downside confirmation → BUY_PE
            if (
                ($spot < $PDC || $spot < $PDL) &&
                $ceLtp !== null &&
                $peLtp !== null &&
                $ceLtp < $CE_PDL &&
                $PE_PDC < $peLtp &&
                $peLtp < $PE_PDH
            ) {
                $tradeSignal = 'BUY_PE';
                return [$tradeSignal, $scenarioGroup, $triggers];
            }
        }

        // 2. CSPB-PSP (Call Seller Profit Booking + Put Seller Panic)
        if ($c['cs_pb'] && $c['ps_panic']) {
            $scenarioGroup = 'CSPB-PSP';

            // Scenario 1: spot < MinSup and >= MaxSup → BUY_PE
            if ($c['spot_break_min_sup'] && ! $c['spot_break_max_sup']) {
                $tradeSignal = 'BUY_PE';
                return [$tradeSignal, $scenarioGroup, $triggers];
            }

            // Scenario 2: spot < PDL and PE above PDH → BUY_PE
            if ($spot < $PDL && $c['pe_above_pdh']) {
                $tradeSignal = 'BUY_PE';
                return [$tradeSignal, $scenarioGroup, $triggers];
            }

            // Scenario 3: spot near PDC → BUY_PE
            if ($c['spot_near_pdc']) {
                $tradeSignal = 'BUY_PE';
                return [$tradeSignal, $scenarioGroup, $triggers];
            }

            // Scenario 4: optional complex PSP pattern can go here
        }

        // 3. BOTHPB (Both Profit Booking)
        if ($c['cs_pb'] && $c['ps_pb']) {
            $scenarioGroup = 'BOTHPB';

            // Scenario 1: spot > PDC → BUY_CE
            if ($spot > $PDC) {
                $tradeSignal = 'BUY_CE';
                return [$tradeSignal, $scenarioGroup, $triggers];
            }

            // Scenario 2: spot < PDC → BUY_PE
            if ($spot < $PDC) {
                $tradeSignal = 'BUY_PE';
                return [$tradeSignal, $scenarioGroup, $triggers];
            }

            // Scenario 3: spot > MinRes AND ce_ltp > PDH → BUY_CE
            if ($spot > $MinRes && $ceLtp !== null && $ceLtp > $PDH) {
                $tradeSignal = 'BUY_CE';
                return [$tradeSignal, $scenarioGroup, $triggers];
            }

            // Scenario 4: spot < MinSup AND pe_ltp > PDH → BUY_PE
            if ($spot < $MinSup && $peLtp !== null && $peLtp > $PDH) {
                $tradeSignal = 'BUY_PE';
                return [$tradeSignal, $scenarioGroup, $triggers];
            }
        }

        // 4. Fallback → indecision
        if (! $scenarioGroup) {
            $scenarioGroup = 'INDECISION';
        }
        if (! $tradeSignal) {
            $tradeSignal = 'SIDEWAYS_NO_TRADE';
        }

        return [$tradeSignal, $scenarioGroup, $triggers];
    }
}
