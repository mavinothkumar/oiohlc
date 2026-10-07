<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StrategyPanel;
use App\Models\StrategyPanelLeg;
use Illuminate\Support\Facades\DB;
use App\Models\Instrument;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class TradingJournalController extends Controller {
    public function index() {
        $backtestStrategies = DB::table( 'backtest_strategies' )
                                ->select( 'id', 'slug', 'name', 'version' )
                                ->where( 'is_active', 1 )
                                ->orderBy( 'id', 'asc' )
                                ->get();

        $expiries = DB::table( 'nse_expiries' )
                        ->where( 'trading_symbol', 'NIFTY' )
                        ->where( 'instrument_type', 'OPT' )
                        ->where( 'expiry_date', '>=', today()->toDateString() )
                        ->orderBy( 'expiry_date' )
                        ->take( 5 )
                        ->get();

        $availableExpiries = [];
        foreach ( $expiries as $idx => $exp ) {
            $value = match ($idx) {
                0 => 'Current',
                1 => 'Next',
                default => 'Expiry ' . ($idx + 1),
            };

            $dateFormatted = Carbon::parse($exp->expiry_date)->format('d M');
            $label = match ($idx) {
                0 => "Current ({$dateFormatted})",
                1 => "Next ({$dateFormatted})",
                default => "Expiry " . ($idx + 1) . " ({$dateFormatted})",
            };

            $availableExpiries[] = [
                'value'       => $value,
                'label'       => $label,
                'expiry_date' => $exp->expiry_date,
                'expiry'      => $exp->expiry,
            ];
        }

        return view( 'trading-journal.index', compact( 'backtestStrategies', 'availableExpiries' ) );
    }

    public function getPanels() {
        $panels = StrategyPanel::with( 'legs' )->orderBy( 'sort_order', 'asc' )->get();

        $expiries = DB::table( 'nse_expiries' )
                        ->where( 'trading_symbol', 'NIFTY' )
                        ->where( 'instrument_type', 'OPT' )
                        ->where( 'expiry_date', '>=', today()->toDateString() )
                        ->orderBy( 'expiry_date' )
                        ->take( 5 )
                        ->get();

        $expiryMap = [];
        foreach ( $expiries as $idx => $exp ) {
            if ( $idx === 0 ) {
                $expiryMap['Current'] = $exp->expiry;
            } elseif ( $idx === 1 ) {
                $expiryMap['Next'] = $exp->expiry;
            }
            $expiryMap['Expiry ' . ($idx + 1)] = $exp->expiry;
            $expiryMap['Exp' . ($idx + 1)] = $exp->expiry;
            $expiryMap[$exp->expiry_date] = $exp->expiry;
            $expiryMap[(string)$exp->expiry] = $exp->expiry;
        }

        $defaultExpiry = $expiries->first()->expiry ?? null;

        foreach ( $panels as $panel ) {
            // Find closest ohlc_quotes time matching panel->entry_time for today
            $today         = Carbon::today()->format( 'Y-m-d' );
            $entryDateTime = $today . ' ' . $panel->entry_time;

            foreach ( $panel->legs as $leg ) {
                $expiryToUse = $expiryMap[$leg->expiry_type] ?? $defaultExpiry;

                // Find instrument key
                $instrument = Instrument::where( 'name', 'NIFTY' )
                                        ->where( 'strike_price', $leg->strike_price )
                                        ->where( 'instrument_type', $leg->option_type )
                                        ->where( 'expiry', $expiryToUse )
                                        ->first();

                $leg->instrument_key = $instrument ? $instrument->instrument_key : null;
                $leg->entry_price    = 0;

                if ( $leg->instrument_key ) {
                    $ohlc_quotes = getTableName( 'ohlc_quotes' );
                    // Fetch entry price
                    $quote = DB::table( $ohlc_quotes )
                               ->where( 'instrument_key', $leg->instrument_key )
                               ->where( 'ts_at', '>=', $entryDateTime )
                               ->orderBy( 'ts_at', 'asc' )
                               ->first();

                    if ( $quote ) {
                        $leg->entry_price = $quote->open; // or close, depending on preference
                    }
                }
            }
        }

        return response()->json( $panels );
    }

    public function savePanel( Request $request ) {
        $validated = $request->validate( [
            'id'                  => 'nullable|exists:strategy_panels,id',
            'name'                => 'required|string',
            'entry_time'          => 'required',
            'legs'                => 'required|array',
            'legs.*.strike_price' => 'required|numeric',
            'legs.*.option_type'  => 'required|string|in:CE,PE',
            'legs.*.expiry_type'  => 'required|string',
            'legs.*.quantity'     => 'required|integer',
            'legs.*.side'         => 'required|string|in:Buy,Sell',
        ] );

        $isNew = empty( $validated['id'] );

        if ( $isNew ) {
            // Shift all existing panels down to make room at the top (sort_order = 0)
            StrategyPanel::query()->increment( 'sort_order' );
        }

        $panel = StrategyPanel::updateOrCreate(
            [ 'id' => $validated['id'] ?? null ],
            [
                'name'       => $validated['name'],
                'entry_time' => $validated['entry_time'],
                'sort_order' => $isNew ? 0 : DB::raw( 'sort_order' ), // keep existing order on update
            ]
        );

        $panel->legs()->delete();
        foreach ( $validated['legs'] as $legData ) {
            $panel->legs()->create( $legData );
        }

        return response()->json( [ 'success' => true, 'panel' => $panel->load( 'legs' ) ] );
    }

    public function deletePanel( $id ) {
        StrategyPanel::findOrFail( $id )->delete();

        return response()->json( [ 'success' => true ] );
    }

    public function reorderPanels( Request $request ) {
        $validated = $request->validate( [
            'ordered_ids'   => 'required|array',
            'ordered_ids.*' => 'integer|exists:strategy_panels,id',
        ] );

        foreach ( $validated['ordered_ids'] as $index => $id ) {
            StrategyPanel::where( 'id', $id )->update( [ 'sort_order' => $index ] );
        }

        return response()->json( [ 'success' => true ] );
    }

    public function updateAllTimes( Request $request ) {
        $validated = $request->validate( [
            'entry_time' => 'required',
        ] );

        StrategyPanel::query()->update( [ 'entry_time' => $validated['entry_time'] ] );

        return response()->json( [ 'success' => true ] );
    }

    public function getWsUrl() {
        $token = config( 'services.upstox.analytics_token' );
        if ( ! $token ) {
            return response()->json( [ 'error' => 'Upstox access token not configured' ], 400 );
        }

        $response = Http::withHeaders( [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ] )->get( 'https://api.upstox.com/v3/feed/market-data-feed/authorize' );

        if ( $response->successful() ) {
            return response()->json( $response->json() );
        }

        return response()->json( [ 'error' => 'Failed to fetch WS URL', 'details' => $response->body() ], $response->status() );
    }

    public function generateFromTemplate( Request $request ) {
        $validated = $request->validate([
            'strategy_id' => 'required|integer',
            'atm'         => 'required|numeric',
            'expiry_type' => 'nullable|string',
        ]);

        // 1. Fetch template strategy definition
        $strategy = DB::table('backtest_strategies')->where('id', $validated['strategy_id'])->first();
        if (!$strategy) {
            return response()->json(['error' => 'Strategy template not found.'], 404);
        }

        $definition = json_decode($strategy->definition, true);
        $parameters = json_decode($strategy->parameters, true);

        $atm = (float) $validated['atm'];
        $entryTime = $parameters['entry_time'] ?? '09:15';
        $targetExpiryType = $validated['expiry_type'] ?? 'Current';

        // Shift existing panels down so newly generated strategy appears at top
        StrategyPanel::query()->increment('sort_order');

        // 2. Create Strategy Panel
        $panel = StrategyPanel::create([
            'name'       => $strategy->name . ' (ATM ' . $atm . ')',
            'entry_time' => $entryTime,
            'sort_order' => 0,
        ]);

        $legsData = [];
        if (isset($definition['legs']) && is_array($definition['legs'])) {
            foreach ($definition['legs'] as $legDef) {
                $moneyness    = strtoupper($legDef['moneyness'] ?? 'ATM');
                $offset       = (float) ($legDef['strike_offset'] ?? 0);
                $optionType   = strtoupper($legDef['option_type'] ?? 'CE');
                $lots         = (int) ($legDef['lots'] ?? 1);
                $side         = ucfirst(strtolower($legDef['side'] ?? 'Sell'));

                // Standard NIFTY lot size multiplier is 65 (adjust if needed)
                $quantity = $lots * 65;

                // Calculate Strike based on Moneyness and Offset (consistent with BasketBuilder & TrendingOi)
                $moneynessAdj = match ($moneyness) {
                    'ITM'   => -50 - $offset,
                    'OTM'   => 50 + $offset,
                    default => 0,
                };
                $strikePrice = $atm + $moneynessAdj;

                $legsData[] = [
                    'strike_price' => $strikePrice,
                    'option_type'  => $optionType,
                    'expiry_type'  => $targetExpiryType,
                    'quantity'     => $quantity,
                    'side'         => $side,
                ];
            }
        }

        // Save legs
        foreach ($legsData as $leg) {
            $panel->legs()->create($leg);
        }

        return response()->json([
            'success' => true,
            'panel'   => $panel->load('legs'),
        ]);
    }
}
