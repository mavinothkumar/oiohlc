previous_date : previous trading day
Get the working date from nse_working_days and run this for all the available OHLC days.
CREATE TABLE `nse_working_days` (
`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
`working_date` DATE NOT NULL,
`previous` TINYINT(1) NOT NULL DEFAULT '0',
`current` TINYINT(1) NOT NULL DEFAULT '0',
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL,
PRIMARY KEY (`id`) USING BTREE,
UNIQUE INDEX `nse_working_days_working_date_unique` (`working_date`) USING BTREE
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
AUTO_INCREMENT=2018
;


current_date : current trading day
Take the date from the nse_working_days.


atm_strike : strike price
use the table expired_ohlc  to collect the respective previous_date OHLC of instrument_type = INDEX and expiry should the current expiry
you can find the expiry from the expired_expiries table where the expiry_date should be the based on the previous_day, when the expiry_date and the
previou_day is same, then take the next week expiry from the expired_expiry table, because the ATM value of the expiry date would be 0.

we want to find the minimum difference of CE and PE of same strike and that should be the atm_strike

CREATE TABLE `expired_ohlc` (
`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
`underlying_symbol` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
`exchange` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
`expiry` DATE NULL DEFAULT NULL,
`instrument_key` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
`instrument_type` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
`strike` INT NULL DEFAULT NULL,
`interval` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
`open` DECIMAL(12,2) NOT NULL,
`high` DECIMAL(12,2) NOT NULL,
`low` DECIMAL(12,2) NOT NULL,
`close` DECIMAL(12,2) NOT NULL,
`volume` BIGINT NULL DEFAULT NULL,
`open_interest` BIGINT NULL DEFAULT NULL,
`build_up` ENUM('Long Build','Short Build','Long Unwind','Short Cover','Neutral') NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
`diff_oi` BIGINT NULL DEFAULT NULL,
`diff_volume` BIGINT NULL DEFAULT NULL,
`diff_ltp` DECIMAL(10,2) NULL DEFAULT NULL,
`timestamp` DATETIME NOT NULL,
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL,
PRIMARY KEY (`id`) USING BTREE,
INDEX `ohlc_usym_exp_strike_type_idx` (`underlying_symbol`, `expiry`, `strike`, `instrument_type`) USING BTREE,
INDEX `idx_ohlc_usym_exp_type_int_ts` (`underlying_symbol`, `expiry`, `instrument_type`, `interval`, `timestamp`) USING BTREE,
INDEX `expired_ohlc_instrument_type_index` (`underlying_symbol`, `expiry`, `instrument_type`) USING BTREE,
INDEX `expired_ohlc_index` (`instrument_key`, `interval`, `timestamp`) USING BTREE,
INDEX `Index 6` (`expiry`, `open_interest`, `strike`, `interval`, `timestamp`) USING BTREE,
INDEX `option_index_fut` (`underlying_symbol`, `instrument_type`, `interval`, `timestamp`) USING BTREE,
INDEX `idx_instrument_interval_ts` (`instrument_key`, `interval`, `timestamp`, `strike`) USING BTREE
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
AUTO_INCREMENT=12827648
;


CREATE TABLE `expired_expiries` (
`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
`underlying_instrument_key` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
`underlying_symbol` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
`instrument_type` ENUM('FUT','OPT') NOT NULL DEFAULT 'OPT' COLLATE 'utf8mb4_unicode_ci',
`expiry_date` DATE NOT NULL,
`created_at` TIMESTAMP NULL DEFAULT NULL,
`updated_at` TIMESTAMP NULL DEFAULT NULL,
PRIMARY KEY (`id`) USING BTREE,
UNIQUE INDEX `expired_expiries_underlying_instrument_key_expiry_date_unique` (`underlying_instrument_key`, `expiry_date`, `instrument_type`) USING BTREE,
INDEX `expired_expiries_expiry_date_index` (`expiry_date`) USING BTREE
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
AUTO_INCREMENT=109
;

mid_point : the value got form the atm_strike (minimum difference of same strike close price of the day)
current_expiry_date : value taken from the expired_ohlc table on the current expiry_date.
next_expiry_date : On expiry date, we will take the value of next expiry date, so on that particular date we will update this to the next_expiry_date
current_day_index_open
created_at:
updated_at :

