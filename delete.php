CREATE DATABASE IF NOT EXISTS `oiohlc` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */; USE `oiohlc`;


CREATE TABLE IF NOT EXISTS `backtest_trades` (
`id` BIGINT unsigned NOT NULL AUTO_INCREMENT,
`underlying_symbol` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NIFTY',
`instrument_type` ENUM('CE','PE') COLLATE utf8mb4_unicode_ci NOT NULL,
`exchange` VARCHAR(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NSE',
`expiry` DATE NOT NULL,
`instrument_key` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`strike` INT NOT NULL,
`ce_strike` INT DEFAULT NULL,
`pe_strike` INT DEFAULT NULL,
`entry_price` DECIMAL(10,2) NOT NULL,
`exit_price` DECIMAL(10,2) DEFAULT NULL,
`side` ENUM('BUY','SELL') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SELL',
`qty` INT NOT NULL DEFAULT '65',
`pnl` DECIMAL(12,2) DEFAULT NULL COMMENT 'Per leg P&L',
`strategy` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'strangle_straddle',
`entry_time` DATETIME NOT NULL,
`signal_time` DATETIME DEFAULT NULL COMMENT 'Time when breakout signal was confirmed (FCB strategy)',
`exit_time` DATETIME DEFAULT NULL,
`trade_time_duration` INT DEFAULT NULL COMMENT 'In minutes',
`outcome` ENUM('profit','loss','open') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
`trade_date` DATE NOT NULL,
`backtest_run_id` VARCHAR(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`day_group_id` VARCHAR(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'UUID shared by all 4 legs of the same day',
`day_total_pnl` DECIMAL(12,2) DEFAULT NULL,
`day_max_profit` DECIMAL(12,2) DEFAULT '0.00' COMMENT 'Highest combined P&L reached during the day before exit',
`day_max_loss` DECIMAL(12,2) DEFAULT '0.00' COMMENT 'Lowest combined P&L reached during the day before exit (negative value)',
`day_max_profit_time` DATETIME DEFAULT NULL COMMENT 'Timestamp when combined P&L hit its highest point',
`day_max_loss_time` DATETIME DEFAULT NULL COMMENT 'Timestamp when combined P&L hit its lowest point',
`day_outcome` ENUM('profit','loss','open') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
`index_price_at_entry` DECIMAL(12,2) DEFAULT NULL,
`target` DECIMAL(10,2) DEFAULT NULL,
`stoploss` DECIMAL(10,2) DEFAULT NULL,
`lot_size` INT NOT NULL DEFAULT '65',
`strike_offset` INT NOT NULL DEFAULT '300',
`gap_pct_prev_range` DECIMAL(12,4) DEFAULT NULL,
`previous_day_range` DECIMAL(12,2) DEFAULT NULL,
`gap_used` DECIMAL(12,2) DEFAULT NULL COMMENT 'Actual |daily_trend.open_value| on trading_date used for gap filter',
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (`id`), KEY `backtest_trades_underlying_symbol_trade_date_index` (`underlying_symbol`,`trade_date`), KEY `backtest_trades_backtest_run_id_trade_date_index` (`backtest_run_id`,`trade_date`), KEY `backtest_trades_day_group_id_index` (`day_group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=57957 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE IF NOT EXISTS `daily_ohlc_quotes` (
`id` BIGINT unsigned NOT NULL AUTO_INCREMENT,
`symbol_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`instrument_key` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`expiry` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`expiry_date` DATE DEFAULT NULL,
`strike` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`option_type` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`quote_date` DATE NOT NULL,
`open` DECIMAL(15,4) DEFAULT NULL,
`high` DECIMAL(15,4) DEFAULT NULL,
`low` DECIMAL(15,4) DEFAULT NULL,
`close` DECIMAL(15,4) DEFAULT NULL,
`volume` BIGINT DEFAULT NULL,
`open_interest` BIGINT DEFAULT NULL,
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (`id`), KEY `daily_ohlc_quotes_symbol_name_index` (`symbol_name`), KEY `daily_ohlc_quotes_instrument_key_index` (`instrument_key`), KEY `daily_ohlc_quotes_expiry_index` (`expiry`), KEY `daily_ohlc_quotes_strike_index` (`strike`), KEY `daily_ohlc_quotes_option_type_index` (`option_type`), KEY `daily_ohlc_quotes_quote_date_index` (`quote_date`), KEY `daily_ohlc_quotes_expiry_date_index` (`expiry_date`)
) ENGINE=InnoDB AUTO_INCREMENT=90008 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE IF NOT EXISTS `daily_trend` (
`id` BIGINT unsigned NOT NULL AUTO_INCREMENT,
`quote_date` DATE NOT NULL,
`trading_date` DATE DEFAULT NULL,
`symbol_name` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL,
`index_high` DECIMAL(12,2) NOT NULL,
`index_low` DECIMAL(12,2) NOT NULL,
`index_close` DECIMAL(12,2) DEFAULT NULL,
`index_day_range` DECIMAL(12,2) DEFAULT NULL,
`strike` INT NOT NULL,
`ce_high` DECIMAL(12,2) NOT NULL,
`ce_low` DECIMAL(12,2) NOT NULL,
`ce_close` DECIMAL(12,2) NOT NULL,
`pe_high` DECIMAL(12,2) NOT NULL,
`pe_low` DECIMAL(12,2) NOT NULL,
`pe_close` DECIMAL(12,2) NOT NULL,
`mid_point` DECIMAL(12,2) DEFAULT NULL,
`min_r` DECIMAL(12,2) NOT NULL,
`min_s` DECIMAL(12,2) NOT NULL,
`max_r` DECIMAL(12,2) NOT NULL,
`max_s` DECIMAL(12,2) NOT NULL,
`expiry_date` DATE NOT NULL,
`earth_value` DECIMAL(12,2) NOT NULL,
`earth_high` DECIMAL(12,2) DEFAULT NULL,
`earth_low` DECIMAL(12,2) DEFAULT NULL,
`ce_type` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'side',
`pe_type` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'side',
`market_type` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'REGULAR',
`atm_ce` DECIMAL(12,2) DEFAULT NULL,
`atm_pe` DECIMAL(12,2) DEFAULT NULL,
`atm_ce_close` DECIMAL(12,2) DEFAULT NULL,
`atm_pe_close` DECIMAL(12,2) DEFAULT NULL,
`atm_ce_high` DECIMAL(12,2) DEFAULT NULL,
`atm_pe_high` DECIMAL(12,2) DEFAULT NULL,
`atm_ce_low` DECIMAL(12,2) DEFAULT NULL,
`atm_pe_low` DECIMAL(12,2) DEFAULT NULL,
`atm_s_avg` DECIMAL(12,2) DEFAULT NULL,
`atm_r_avg` DECIMAL(12,2) DEFAULT NULL,
`atm_r` DECIMAL(12,2) DEFAULT NULL,
`atm_s` DECIMAL(12,2) DEFAULT NULL,
`open_type` ENUM('Gap Up','Gap Down','Positive Open','Negative Open') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`open_value` DECIMAL(12,2) DEFAULT NULL,
`atm_r_1` DECIMAL(12,2) DEFAULT NULL,
`atm_r_2` DECIMAL(12,2) DEFAULT NULL,
`atm_r_3` DECIMAL(12,2) DEFAULT NULL,
`atm_s_1` DECIMAL(12,2) DEFAULT NULL,
`atm_s_2` DECIMAL(12,2) DEFAULT NULL,
`atm_s_3` DECIMAL(12,2) DEFAULT NULL,
`atm_index_open` DECIMAL(12,2) DEFAULT NULL,
`six_levels_broken` JSON DEFAULT NULL,
`current_day_index_open` DECIMAL(12,2) DEFAULT NULL,
`market_open_time` TIMESTAMP NULL DEFAULT NULL,
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `daily_trend_quote_date_symbol_name_unique` (`quote_date`,`symbol_name`), KEY `daily_trend_quote_date_symbol_name_index` (`quote_date`,`symbol_name`), KEY `daily_trend_quote_date_index` (`quote_date`), KEY `daily_trend_open_type_index` (`open_type`), KEY `daily_trend_trading_date_index` (`trading_date`)
) ENGINE=InnoDB AUTO_INCREMENT=605 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE IF NOT EXISTS `entries` (
`id` BIGINT unsigned NOT NULL AUTO_INCREMENT,
`underlying_symbol` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`exchange` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`expiry` DATE NOT NULL,
`instrument_type` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`strike` INT NOT NULL,
`side` ENUM('BUY','SELL') COLLATE utf8mb4_unicode_ci NOT NULL,
`quantity` INT NOT NULL,
`entry_date` DATE NOT NULL,
`entry_time` TIME NOT NULL,
`entry_price` DECIMAL(10,2) NOT NULL,
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE IF NOT EXISTS `expired_expiries` (
`id` BIGINT unsigned NOT NULL AUTO_INCREMENT,
`underlying_instrument_key` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`underlying_symbol` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`instrument_type` ENUM('FUT','OPT') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPT',
`expiry_date` DATE NOT NULL,
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `expired_expiries_underlying_instrument_key_expiry_date_unique` (`underlying_instrument_key`,`expiry_date`,`instrument_type`) USING BTREE, KEY `expired_expiries_expiry_date_index` (`expiry_date`)
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE IF NOT EXISTS `expired_ohlc` (
`id` BIGINT unsigned NOT NULL AUTO_INCREMENT,
`underlying_symbol` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`exchange` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`expiry` DATE DEFAULT NULL,
`instrument_key` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`instrument_type` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`strike` INT DEFAULT NULL,
`interval` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`open` DECIMAL(12,2) NOT NULL,
`high` DECIMAL(12,2) NOT NULL,
`low` DECIMAL(12,2) NOT NULL,
`close` DECIMAL(12,2) NOT NULL,
`volume` BIGINT DEFAULT NULL,
`open_interest` BIGINT DEFAULT NULL,
`build_up` ENUM('Long Build','Short Build','Long Unwind','Short Cover','Neutral') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`diff_oi` BIGINT DEFAULT NULL,
`diff_volume` BIGINT DEFAULT NULL,
`diff_ltp` DECIMAL(10,2) DEFAULT NULL,
`timestamp` DATETIME NOT NULL,
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (`id`), KEY `ohlc_usym_exp_strike_type_idx` (`underlying_symbol`,`expiry`,`strike`,`instrument_type`), KEY `idx_ohlc_usym_exp_type_int_ts` (`underlying_symbol`,`expiry`,`instrument_type`,`interval`,`timestamp`), KEY `expired_ohlc_instrument_type_index` (`underlying_symbol`,`expiry`,`instrument_type`) USING BTREE, KEY `expired_ohlc_index` (`instrument_key`,`interval`,`timestamp`) USING BTREE, KEY `Index 6` (`expiry`,`open_interest`,`strike`,`interval`,`timestamp`), KEY `option_index_fut` (`underlying_symbol`,`instrument_type`,`interval`,`timestamp`), KEY `idx_instrument_interval_ts` (`instrument_key`,`interval`,`timestamp`,`strike`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=12827648 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE IF NOT EXISTS `expired_ohlc_detailed` (
`id` BIGINT unsigned NOT NULL AUTO_INCREMENT,
`underlying_symbol` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`exchange` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`expiry` DATE DEFAULT NULL,
`instrument_key` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`instrument_type` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`strike` INT DEFAULT NULL,
`interval` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`open` DECIMAL(12,2) NOT NULL,
`high` DECIMAL(12,2) NOT NULL,
`low` DECIMAL(12,2) NOT NULL,
`close` DECIMAL(12,2) NOT NULL,
`volume` BIGINT DEFAULT NULL,
`open_interest` BIGINT DEFAULT NULL,
`timestamp` DATETIME NOT NULL,
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (`id`), KEY `expired_ohlc_instrument_key_interval_timestamp_index` (`instrument_key`,`interval`,`timestamp`), KEY `expired_ohlc_underlying_symbol_expiry_instrument_type_index` (`underlying_symbol`,`expiry`,`instrument_type`), KEY `ohlc_usym_exp_strike_type_idx` (`underlying_symbol`,`expiry`,`strike`,`instrument_type`), KEY `idx_ohlc_usym_exp_type_int_ts` (`underlying_symbol`,`expiry`,`instrument_type`,`interval`,`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE IF NOT EXISTS `expired_option_contracts` (
`id` BIGINT unsigned NOT NULL AUTO_INCREMENT,
`name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`segment` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`exchange` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`expiry` DATE NOT NULL,
`instrument_key` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`exchange_token` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`trading_symbol` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`tick_size` INT unsigned NOT NULL,
`lot_size` INT unsigned NOT NULL,
`instrument_type` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`freeze_quantity` INT unsigned DEFAULT NULL,
`weekly` TINYINT(1) NOT NULL DEFAULT '0',
`underlying_key` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`underlying_type` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`underlying_symbol` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`strike_price` INT unsigned NOT NULL,
`minimum_lot` INT unsigned DEFAULT NULL,
`expired_expiry_id` BIGINT unsigned DEFAULT NULL,
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `expired_option_contracts_instrument_key_unique` (`instrument_key`), KEY `expired_option_contracts_expired_expiry_id_foreign` (`expired_expiry_id`), KEY `expired_option_contracts_underlying_key_expiry_index` (`underlying_key`,`expiry`), KEY `expired_option_contracts_underlying_symbol_expiry_index` (`underlying_symbol`,`expiry`), CONSTRAINT `expired_option_contracts_expired_expiry_id_foreign` FOREIGN KEY (`expired_expiry_id`) REFERENCES `expired_expiries` (`id`) ON
DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=37540 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;





CREATE TABLE IF NOT EXISTS `holidays` (
`id` BIGINT unsigned NOT NULL AUTO_INCREMENT,
`holiday_type` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`description` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL,
`date` DATE NOT NULL,
`exchange` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL, PRIMARY KEY (`id`), KEY `holidays_date_index` (`date`), KEY `holidays_exchange_index` (`exchange`)
) ENGINE=InnoDB AUTO_INCREMENT=321 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE IF NOT EXISTS `index_gap` (
`id` BIGINT unsigned NOT NULL AUTO_INCREMENT,
`symbol_name` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL,
`trading_date` DATE NOT NULL,
`previous_trading_date` DATE NOT NULL,
`previous_close` DECIMAL(12,2) DEFAULT NULL,
`previous_high` DECIMAL(12,2) DEFAULT NULL,
`previous_low` DECIMAL(12,2) DEFAULT NULL,
`previous_day_range` DECIMAL(12,2) DEFAULT NULL,
`current_open` DECIMAL(12,2) DEFAULT NULL,
`gap_value` DECIMAL(12,2) DEFAULT NULL,
`gap_abs` DECIMAL(12,2) DEFAULT NULL,
`gap_pct_prev_close` DECIMAL(10,4) DEFAULT NULL,
`gap_pct_prev_range` DECIMAL(10,4) DEFAULT NULL,
`gap_type` ENUM('Gap Up','Gap Down','Flat') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `index_gap_symbol_name_trading_date_unique` (`symbol_name`,`trading_date`), KEY `index_gap_trading_date_index` (`trading_date`)
) ENGINE=InnoDB AUTO_INCREMENT=353 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE IF NOT EXISTS `instruments` (
`id` BIGINT unsigned NOT NULL AUTO_INCREMENT,
`name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`instrument_type` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`exchange_token` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL,
`isin` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`short_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`security_type` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`lot_size` INT DEFAULT NULL,
`freeze_quantity` DOUBLE DEFAULT NULL,
`tick_size` DOUBLE DEFAULT NULL,
`minimum_lot` INT DEFAULT NULL,
`underlying_symbol` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`underlying_key` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`underlying_type` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`weekly` TINYINT(1) DEFAULT NULL,
`strike_price` DOUBLE DEFAULT NULL,
`option_type` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`qty_multiplier` DOUBLE DEFAULT NULL,
`mtf_enabled` TINYINT(1) DEFAULT NULL,
`mtf_bracket` DOUBLE DEFAULT NULL,
`intraday_margin` DOUBLE DEFAULT NULL,
`intraday_leverage` INT DEFAULT NULL,
`instrument_key` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`exchange` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`segment` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`trading_symbol` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`expiry` BIGINT DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `instruments_instrument_key_unique` (`instrument_key`), KEY `instruments_exchange_index` (`exchange`), KEY `instruments_segment_index` (`segment`), KEY `instruments_trading_symbol_index` (`trading_symbol`), KEY `instruments_expiry_index` (`expiry`)
) ENGINE=InnoDB AUTO_INCREMENT=7416 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;





CREATE TABLE IF NOT EXISTS `nse_atm_day_data` (
`id` BIGINT unsigned NOT NULL AUTO_INCREMENT,
`underlying_symbol` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
`previous_date` DATE NOT NULL,
`current_date` DATE NOT NULL,
`atm_strike` INT unsigned DEFAULT NULL,
`mid_point` DECIMAL(12,2) DEFAULT NULL COMMENT 'Minimum absolute difference between previous day CE and PE close of same strike',
`current_expiry_date` DATE DEFAULT NULL,
`next_expiry_date` DATE DEFAULT NULL,
`current_day_index_open` DECIMAL(12,2) DEFAULT NULL,
`previous_day_index_open` DECIMAL(12,2) DEFAULT NULL,
`previous_day_index_high` DECIMAL(12,2) DEFAULT NULL,
`previous_day_index_low` DECIMAL(12,2) DEFAULT NULL,
`previous_day_index_close` DECIMAL(12,2) DEFAULT NULL,
`previous_day_ce_close` DECIMAL(12,2) DEFAULT NULL,
`previous_day_pe_close` DECIMAL(12,2) DEFAULT NULL,
`previous_day_ce_high` DECIMAL(12,2) DEFAULT NULL,
`previous_day_pe_high` DECIMAL(12,2) DEFAULT NULL,
`previous_day_ce_low` DECIMAL(12,2) DEFAULT NULL,
`previous_day_pe_low` DECIMAL(12,2) DEFAULT NULL,
`is_expiry_day_rollover` TINYINT(1) NOT NULL DEFAULT '0',
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `nse_atm_day_data_symbol_current_date_unique` (`underlying_symbol`,`current_date`), KEY `nse_atm_day_data_symbol_previous_date_index` (`underlying_symbol`,`previous_date`), KEY `nse_atm_day_data_symbol_current_expiry_index` (`underlying_symbol`,`current_expiry_date`), KEY `nse_atm_day_data_underlying_symbol_index` (`underlying_symbol`), KEY `nse_atm_day_data_previous_date_index` (`previous_date`), KEY `nse_atm_day_data_current_date_index` (`current_date`), KEY `nse_atm_day_data_current_expiry_date_index` (`current_expiry_date`), KEY `nse_atm_day_data_next_expiry_date_index` (`next_expiry_date`), KEY `nse_atm_day_data_is_expiry_day_rollover_index` (`is_expiry_day_rollover`)
) ENGINE=InnoDB AUTO_INCREMENT=325 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE IF NOT EXISTS `nse_expiries` (
`id` BIGINT unsigned NOT NULL AUTO_INCREMENT,
`expiry_date` DATE DEFAULT NULL,
`instrument_type` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`trading_symbol` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`is_current` TINYINT(1) NOT NULL DEFAULT '0',
`is_next` TINYINT(1) NOT NULL DEFAULT '0',
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL,
`expiry` BIGINT NOT NULL,
`exchange` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
`segment` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL, PRIMARY KEY (`id`) USING BTREE, KEY `expiries_is_current_index` (`is_current`) USING BTREE, KEY `expiries_is_next_index` (`is_next`) USING BTREE, KEY `expiries_expiry_index` (`expiry`) USING BTREE, KEY `expiries_exchange_index` (`exchange`) USING BTREE, KEY `expiries_segment_index` (`segment`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE IF NOT EXISTS `nse_working_days` (
`id` BIGINT unsigned NOT NULL AUTO_INCREMENT,
`working_date` DATE NOT NULL,
`previous` TINYINT(1) NOT NULL DEFAULT '0',
`current` TINYINT(1) NOT NULL DEFAULT '0',
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `nse_working_days_working_date_unique` (`working_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2018 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE IF NOT EXISTS `ohlc_day_quotes` (
`id` BIGINT unsigned NOT NULL AUTO_INCREMENT,
`instrument_key` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
`instrument_type` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL,
`trading_symbol` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL,
`expiry_date` DATE DEFAULT NULL,
`strike_price` DECIMAL(10,2) DEFAULT NULL,
`open` DECIMAL(15,5) DEFAULT NULL,
`high` DECIMAL(15,5) DEFAULT NULL,
`low` DECIMAL(15,5) DEFAULT NULL,
`close` DECIMAL(15,5) DEFAULT NULL,
`volume` BIGINT DEFAULT NULL,
`ts` BIGINT unsigned DEFAULT NULL,
`ts_at` TIMESTAMP NULL DEFAULT NULL,
`last_price` DECIMAL(15,5) DEFAULT NULL,
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_instrument_ts` (`instrument_key`,`ts`), KEY `ohlc_day_quotes_instrument_key_index` (`instrument_key`), KEY `ohlc_day_quotes_instrument_type_index` (`instrument_type`), KEY `ohlc_day_quotes_trading_symbol_index` (`trading_symbol`), KEY `ohlc_day_quotes_expiry_date_index` (`expiry_date`), KEY `ohlc_day_quotes_strike_price_index` (`strike_price`)
) ENGINE=InnoDB AUTO_INCREMENT=148419 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;





CREATE TABLE IF NOT EXISTS `ohlc_quotes` (
`id` BIGINT unsigned NOT NULL AUTO_INCREMENT,
`instrument_key` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
`instrument_type` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL,
`trading_symbol` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`expiry_date` DATE DEFAULT NULL,
`strike_price` DECIMAL(10,2) DEFAULT NULL,
`open` DECIMAL(15,5) DEFAULT NULL,
`high` DECIMAL(15,5) DEFAULT NULL,
`low` DECIMAL(15,5) DEFAULT NULL,
`close` DECIMAL(15,5) DEFAULT NULL,
`volume` BIGINT DEFAULT NULL,
`ts` BIGINT unsigned DEFAULT NULL,
`ts_at` TIMESTAMP NULL DEFAULT NULL,
`last_price` DECIMAL(15,5) DEFAULT NULL,
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_instrument_ts` (`instrument_key`,`ts`), KEY `ohlc_quotes_symbol_strike_type_expiry_index` (`trading_symbol`,`strike_price`,`instrument_type`,`expiry_date`)
) ENGINE=InnoDB AUTO_INCREMENT=918478 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;






CREATE TABLE IF NOT EXISTS `option_chains` (
`id` BIGINT unsigned NOT NULL AUTO_INCREMENT,
`instrument_key` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`underlying_key` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
`trading_symbol` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL,
`expiry` DATE NOT NULL,
`strike_price` DECIMAL(10,2) NOT NULL,
`option_type` ENUM('CE','PE') COLLATE utf8mb4_unicode_ci NOT NULL,
`ltp` DECIMAL(10,2) DEFAULT NULL,
`diff_ltp` DECIMAL(10,2) DEFAULT NULL,
`volume` BIGINT DEFAULT NULL,
`diff_volume` BIGINT DEFAULT NULL,
`oi` BIGINT DEFAULT NULL,
`diff_oi` BIGINT DEFAULT NULL,
`close_price` DECIMAL(10,2) DEFAULT NULL,
`bid_price` DECIMAL(10,2) DEFAULT NULL,
`bid_qty` BIGINT DEFAULT NULL,
`ask_price` DECIMAL(10,2) DEFAULT NULL,
`ask_qty` BIGINT DEFAULT NULL,
`prev_oi` BIGINT DEFAULT NULL,
`vega` DECIMAL(10,4) DEFAULT NULL,
`theta` DECIMAL(10,4) DEFAULT NULL,
`gamma` DECIMAL(10,4) DEFAULT NULL,
`delta` DECIMAL(10,4) DEFAULT NULL,
`iv` DECIMAL(10,2) DEFAULT NULL,
`pop` DECIMAL(10,2) DEFAULT NULL,
`underlying_spot_price` DECIMAL(10,2) DEFAULT NULL,
`pcr` DECIMAL(10,4) DEFAULT NULL,
`captured_at` TIMESTAMP NOT NULL,
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL,
`build_up` ENUM('Long Build','Short Build','Short Cover','Long Unwind') COLLATE utf8mb4_unicode_ci DEFAULT NULL, PRIMARY KEY (`id`), KEY `option_chains_underlying_key_index` (`underlying_key`), KEY `option_chains_trading_symbol_index` (`trading_symbol`), KEY `option_chains_expiry_index` (`expiry`), KEY `option_chains_strike_price_index` (`strike_price`), KEY `option_chains_captured_at_index` (`captured_at`), KEY `option_chains_instrument_key_index` (`instrument_key`)
) ENGINE=InnoDB AUTO_INCREMENT=3389961 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;





CREATE TABLE IF NOT EXISTS `sim_orders` (
`id` BIGINT unsigned NOT NULL AUTO_INCREMENT,
`position_id` BIGINT unsigned NOT NULL,
`session_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`trade_date` DATE NOT NULL,
`order_type` ENUM('entry','partial_exit','full_exit') COLLATE utf8mb4_unicode_ci NOT NULL,
`side` VARCHAR(4) COLLATE utf8mb4_unicode_ci NOT NULL,
`price` DECIMAL(10,2) NOT NULL,
`qty` INT NOT NULL,
`lots` INT NOT NULL DEFAULT '1',
`pnl` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
`executed_at` TIMESTAMP NOT NULL,
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=829 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE IF NOT EXISTS `sim_positions` (
`id` BIGINT unsigned NOT NULL AUTO_INCREMENT,
`session_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`trade_date` DATE NOT NULL,
`expiry` DATE NOT NULL,
`underlying` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NIFTY',
`strike` INT NOT NULL,
`instrument_type` VARCHAR(2) COLLATE utf8mb4_unicode_ci NOT NULL,
`side` VARCHAR(4) COLLATE utf8mb4_unicode_ci NOT NULL,
`avg_entry` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
`total_qty` INT NOT NULL DEFAULT '0',
`open_qty` INT NOT NULL DEFAULT '0',
`realized_pnl` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
`status` ENUM('open','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
`strategy` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (`id`), KEY `sim_positions_session_id_index` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=381 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE IF NOT EXISTS `sim_trade_notes` (
`id` BIGINT unsigned NOT NULL AUTO_INCREMENT,
`position_id` BIGINT unsigned NOT NULL,
`session_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`comment` TEXT COLLATE utf8mb4_unicode_ci,
`outcome` ENUM('profit','stoploss','breakeven') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`strategy` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`exit_price` DECIMAL(10,2) DEFAULT NULL,
`exit_qty` INT DEFAULT NULL,
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (`id`), KEY `sim_trade_notes_position_id_foreign` (`position_id`), KEY `sim_trade_notes_session_id_index` (`session_id`), CONSTRAINT `sim_trade_notes_position_id_foreign` FOREIGN KEY (`position_id`) REFERENCES `sim_positions` (`id`) ON
DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS `users` (
`id` BIGINT unsigned NOT NULL AUTO_INCREMENT,
`name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`email` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`email_verified_at` TIMESTAMP NULL DEFAULT NULL,
`password` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`remember_token` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
