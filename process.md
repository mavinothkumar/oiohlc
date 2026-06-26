php artisan upstox:sync-nifty-expiries
php artisan expired-expiries:create-fut


php artisan upstox:sync-nifty-expired-contracts --expiry=2026-06-23
php artisan upstox:sync-nifty-index-ohlc 2026-06-16 2026-06-23
php artisan upstox:sync-nifty-option-ohlc 2026-06-16 2026-06-23 --expiry=2026-06-23
php artisan index-gap:generate --from=2026-06-16 --to=2026-06-23
php artisan nse:generate-atm-day-data --from=2026-06-16 --to=2026-06-23


php artisan upstox:sync-expired-futures-to-options --expiry=2026-05-26
php artisan upstox:sync-expired-future-ohlc --expiry=2026-05-26

php artisan ohlc:update-buildup 2026-05-19 2026-06-23


