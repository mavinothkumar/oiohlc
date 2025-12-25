<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BuildIndexOptionAnalysis extends Command
{
    protected $signature = 'ohlc:build-atm {--from=} {--to=} {--truncate}';

    protected $description = 'Build ATM index-option analysis from expired_ohlc into index_option_analysis';

    public function handle(): int
    {
        $from = $this->option('from');
        $to   = $this->option('to');

        if ($this->option('truncate')) {
            DB::table('index_option_analysis')->truncate();
            $this->info('index_option_analysis truncated.');
        }

        $dateFilter = '';
        $bindings   = [];

        if ($from && $to) {
            $dateFilter = 'WHERE ip.trade_date BETWEEN ? AND ?';
            $bindings[] = $from;
            $bindings[] = $to;
        } elseif ($from) {
            $dateFilter = 'WHERE ip.trade_date >= ?';
            $bindings[] = $from;
        } elseif ($to) {
            $dateFilter = 'WHERE ip.trade_date <= ?';
            $bindings[] = $to;
        }

        $sql = <<<SQL
INSERT INTO index_option_analysis (
    underlying_symbol, exchange, trade_date,
    prev_index_open, prev_index_high, prev_index_low, prev_index_close,
    gap_prev_close_to_open,
    atm_strike,
    prev_ce_open, prev_ce_high, prev_ce_low, prev_ce_close,
    prev_pe_open, prev_pe_high, prev_pe_low, prev_pe_close,
    cur_ce_open, cur_ce_high, cur_ce_low, cur_ce_close,
    cur_pe_open, cur_pe_high, cur_pe_low, cur_pe_close,
    cur_index_open, cur_index_high, cur_index_low, cur_index_close,
    range_ce_low_plus, range_ce_low_minus,
    avg_low, range_avg_low_plus, range_avg_low_minus,
    avg_high, range_avg_high_plus, range_avg_high_minus,
    created_at, updated_at
)
WITH calendar AS (
    -- NSE working days calendar
    SELECT
        working_date,
        LAG(working_date) OVER (ORDER BY working_date) AS prev_working_date
    FROM nse_working_days
),
index_daily AS (
    -- Daily index OHLC (interval = day, instrument_type = INDEX)
    SELECT
        e.underlying_symbol,
        e.exchange,
        DATE(e.`timestamp`) AS trade_date,
        MIN(e.`timestamp`) AS first_ts,
        MAX(e.`timestamp`) AS last_ts,
        SUBSTRING_INDEX(
            SUBSTRING_INDEX(
                GROUP_CONCAT(e.`open` ORDER BY e.`timestamp`),
                ',', 1
            ),
            ',', -1
        ) AS day_open,
        MAX(e.`high`) AS day_high,
        MIN(e.`low`)  AS day_low,
        SUBSTRING_INDEX(
            SUBSTRING_INDEX(
                GROUP_CONCAT(e.`close` ORDER BY e.`timestamp`),
                ',', -1
            ),
            ',', 1
        ) AS day_close
    FROM expired_ohlc e
    WHERE e.`interval` = 'day'
      AND e.instrument_type = 'INDEX'
    GROUP BY e.underlying_symbol, e.exchange, DATE(e.`timestamp`)
),
index_with_prev AS (
    -- Attach calendar and previous working date, plus prev index OHLC via self-join
    SELECT
        idc.underlying_symbol,
        idc.exchange,
        c.working_date      AS trade_date,
        idc.day_open        AS cur_open,
        idc.day_high        AS cur_high,
        idc.day_low         AS cur_low,
        idc.day_close       AS cur_close,
        idp.day_open        AS prev_open,
        idp.day_high        AS prev_high,
        idp.day_low         AS prev_low,
        idp.day_close       AS prev_close,
        c.prev_working_date AS prev_trade_date
    FROM calendar c
    JOIN index_daily idc
      ON idc.trade_date = c.working_date
    LEFT JOIN index_daily idp
      ON idp.trade_date = c.prev_working_date
),
next_expiry AS (
    -- For each trade_date, choose the minimum expiry >= trade_date
    SELECT
        ip.underlying_symbol,
        ip.exchange,
        ip.trade_date,
        MIN(o.expiry) AS expiry
    FROM index_with_prev ip
    JOIN expired_ohlc o
      ON o.underlying_symbol = ip.underlying_symbol
     AND o.exchange          = ip.exchange
     AND o.instrument_type IN ('CE','PE')
     AND o.`interval` = 'day'
     AND o.expiry >= ip.trade_date
    GROUP BY ip.underlying_symbol, ip.exchange, ip.trade_date
),
atm_prev_strike AS (
    -- For each trade_date, expiry and strike on prev_trade_date,
    -- compute |CE_close - PE_close| and pick the minimum
    SELECT
        x.underlying_symbol,
        x.exchange,
        x.trade_date,
        x.prev_trade_date,
        x.prev_close,
        x.expiry,
        x.strike,
        ROW_NUMBER() OVER (
            PARTITION BY x.underlying_symbol, x.exchange, x.trade_date
            ORDER BY x.diff_cp ASC,                      -- min |CE-PE|
                     ABS(x.strike - x.prev_close) ASC,   -- tie-break: nearest to index prev close
                     x.strike ASC
        ) AS rn
    FROM (
        SELECT
            ip.underlying_symbol,
            ip.exchange,
            ip.trade_date,
            ip.prev_trade_date,
            ip.prev_close,
            ne.expiry,
            o.strike,
            -- previous working day's CE & PE closes at this strike/expiry
            MAX(CASE WHEN o.instrument_type = 'CE' THEN o.close END) AS ce_close,
            MAX(CASE WHEN o.instrument_type = 'PE' THEN o.close END) AS pe_close,
            ABS(
                MAX(CASE WHEN o.instrument_type = 'CE' THEN o.close END)
              - MAX(CASE WHEN o.instrument_type = 'PE' THEN o.close END)
            ) AS diff_cp
        FROM index_with_prev ip
        JOIN next_expiry ne
          ON ne.underlying_symbol = ip.underlying_symbol
         AND ne.exchange          = ip.exchange
         AND ne.trade_date        = ip.trade_date
        JOIN expired_ohlc o
          ON o.underlying_symbol  = ip.underlying_symbol
         AND o.exchange           = ip.exchange
         AND o.instrument_type IN ('CE','PE')
         AND o.`interval`        = 'day'
         AND o.expiry            = ne.expiry
         AND DATE(o.`timestamp`) = ip.prev_trade_date
        GROUP BY
            ip.underlying_symbol,
            ip.exchange,
            ip.trade_date,
            ip.prev_trade_date,
            ip.prev_close,
            ne.expiry,
            o.strike
        HAVING ce_close IS NOT NULL AND pe_close IS NOT NULL
    ) AS x
),
atm_selected AS (
    -- Only the nearest ATM strike per trade_date
    SELECT underlying_symbol, exchange, trade_date, prev_trade_date, prev_close, expiry, strike
    FROM atm_prev_strike
    WHERE rn = 1
),
prev_cepe AS (
    -- Previous working day CE/PE OHLC for that ATM strike & expiry
    SELECT
        a.underlying_symbol,
        a.exchange,
        a.trade_date,
        a.expiry,
        a.strike,
        MAX(CASE WHEN o.instrument_type = 'CE' THEN o.open  END) AS prev_ce_open,
        MAX(CASE WHEN o.instrument_type = 'CE' THEN o.high  END) AS prev_ce_high,
        MAX(CASE WHEN o.instrument_type = 'CE' THEN o.low   END) AS prev_ce_low,
        MAX(CASE WHEN o.instrument_type = 'CE' THEN o.close END) AS prev_ce_close,
        MAX(CASE WHEN o.instrument_type = 'PE' THEN o.open  END) AS prev_pe_open,
        MAX(CASE WHEN o.instrument_type = 'PE' THEN o.high  END) AS prev_pe_high,
        MAX(CASE WHEN o.instrument_type = 'PE' THEN o.low   END) AS prev_pe_low,
        MAX(CASE WHEN o.instrument_type = 'PE' THEN o.close END) AS prev_pe_close
    FROM atm_selected a
    JOIN expired_ohlc o
      ON o.underlying_symbol = a.underlying_symbol
     AND o.exchange          = a.exchange
     AND o.strike            = a.strike
     AND o.expiry            = a.expiry
     AND DATE(o.`timestamp`) = a.prev_trade_date
     AND o.`interval`        = 'day'
     AND o.instrument_type IN ('CE','PE')
    GROUP BY a.underlying_symbol, a.exchange, a.trade_date, a.expiry, a.strike
),
cur_cepe AS (
    -- Current day CE/PE OHLC for same ATM strike & expiry
    SELECT
        a.underlying_symbol,
        a.exchange,
        a.trade_date,
        a.expiry,
        a.strike,
        MAX(CASE WHEN o.instrument_type = 'CE' THEN o.open  END) AS cur_ce_open,
        MAX(CASE WHEN o.instrument_type = 'CE' THEN o.high  END) AS cur_ce_high,
        MAX(CASE WHEN o.instrument_type = 'CE' THEN o.low   END) AS cur_ce_low,
        MAX(CASE WHEN o.instrument_type = 'CE' THEN o.close END) AS cur_ce_close,
        MAX(CASE WHEN o.instrument_type = 'PE' THEN o.open  END) AS cur_pe_open,
        MAX(CASE WHEN o.instrument_type = 'PE' THEN o.high  END) AS cur_pe_high,
        MAX(CASE WHEN o.instrument_type = 'PE' THEN o.low   END) AS cur_pe_low,
        MAX(CASE WHEN o.instrument_type = 'PE' THEN o.close END) AS cur_pe_close
    FROM atm_selected a
    JOIN expired_ohlc o
      ON o.underlying_symbol = a.underlying_symbol
     AND o.exchange          = a.exchange
     AND o.strike            = a.strike
     AND o.expiry            = a.expiry
     AND DATE(o.`timestamp`) = a.trade_date
     AND o.`interval`        = 'day'
     AND o.instrument_type IN ('CE','PE')
    GROUP BY a.underlying_symbol, a.exchange, a.trade_date, a.expiry, a.strike
)
SELECT
    ip.underlying_symbol,
    ip.exchange,
    ip.trade_date,

    ip.prev_open  AS prev_index_open,
    ip.prev_high  AS prev_index_high,
    ip.prev_low   AS prev_index_low,
    ip.prev_close AS prev_index_close,
    (ip.cur_open - ip.prev_close) AS gap_prev_close_to_open,

    a.strike      AS atm_strike,

    p.prev_ce_open,
    p.prev_ce_high,
    p.prev_ce_low,
    p.prev_ce_close,
    p.prev_pe_open,
    p.prev_pe_high,
    p.prev_pe_low,
    p.prev_pe_close,

    c.cur_ce_open,
    c.cur_ce_high,
    c.cur_ce_low,
    c.cur_ce_close,
    c.cur_pe_open,
    c.cur_pe_high,
    c.cur_pe_low,
    c.cur_pe_close,

    ip.cur_open   AS cur_index_open,
    ip.cur_high   AS cur_index_high,
    ip.cur_low    AS cur_index_low,
    ip.cur_close  AS cur_index_close,

    -- Index low +/- CE low
    CASE WHEN c.cur_ce_low IS NOT NULL
         THEN (ip.cur_low + c.cur_ce_low)
         ELSE NULL
    END AS range_ce_low_plus,
    CASE WHEN c.cur_ce_low IS NOT NULL
         THEN (ip.cur_low - c.cur_ce_low)
         ELSE NULL
    END AS range_ce_low_minus,

    -- Index low +/- avg(CE low, PE low)
    CASE WHEN c.cur_ce_low IS NOT NULL AND c.cur_pe_low IS NOT NULL
         THEN ((c.cur_ce_low + c.cur_pe_low) / 2)
         ELSE NULL
    END AS avg_low,
    CASE WHEN c.cur_ce_low IS NOT NULL AND c.cur_pe_low IS NOT NULL
         THEN (ip.cur_low + (c.cur_ce_low + c.cur_pe_low) / 2)
         ELSE NULL
    END AS range_avg_low_plus,
    CASE WHEN c.cur_ce_low IS NOT NULL AND c.cur_pe_low IS NOT NULL
         THEN (ip.cur_low - (c.cur_ce_low + c.cur_pe_low) / 2)
         ELSE NULL
    END AS range_avg_low_minus,

    -- Index low +/- avg(CE high, PE high)
    CASE WHEN c.cur_ce_high IS NOT NULL AND c.cur_pe_high IS NOT NULL
         THEN ((c.cur_ce_high + c.cur_pe_high) / 2)
         ELSE NULL
    END AS avg_high,
    CASE WHEN c.cur_ce_high IS NOT NULL AND c.cur_pe_high IS NOT NULL
         THEN (ip.cur_low + (c.cur_ce_high + c.cur_pe_high) / 2)
         ELSE NULL
    END AS range_avg_high_plus,
    CASE WHEN c.cur_ce_high IS NOT NULL AND c.cur_pe_high IS NOT NULL
         THEN (ip.cur_low - (c.cur_ce_high + c.cur_pe_high) / 2)
         ELSE NULL
    END AS range_avg_high_minus,

    NOW() AS created_at,
    NOW() AS updated_at
FROM index_with_prev ip
LEFT JOIN atm_selected a
  ON a.underlying_symbol = ip.underlying_symbol
 AND a.exchange          = ip.exchange
 AND a.trade_date        = ip.trade_date
LEFT JOIN prev_cepe p
  ON p.underlying_symbol = a.underlying_symbol
 AND p.exchange          = a.exchange
 AND p.trade_date        = a.trade_date
 AND p.strike            = a.strike
LEFT JOIN cur_cepe c
  ON c.underlying_symbol = a.underlying_symbol
 AND c.exchange          = a.exchange
 AND c.trade_date        = a.trade_date
 AND c.strike            = a.strike
{$dateFilter}
SQL;

        DB::statement($sql, $bindings);

        $this->info('index_option_analysis built successfully.');

        return self::SUCCESS;
    }
}
