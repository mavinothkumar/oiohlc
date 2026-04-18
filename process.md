php artisan upstox:sync-nifty-expiries
php artisan expired-expiries:create-fut


php artisan upstox:sync-nifty-expired-contracts --expiry=2026-04-13
php artisan upstox:sync-nifty-index-ohlc 2026-04-07 2026-04-13
php artisan upstox:sync-nifty-option-ohlc 2026-04-07 2026-04-13 --expiry=2026-04-13
php artisan ohlc:update-buildup 2026-04-07 2026-04-13

php artisan upstox:sync-expired-futures-to-options --expiry=2026-03-30
php artisan upstox:sync-expired-future-ohlc --expiry=2026-03-30





