php artisan upstox:sync-nifty-expiries
php artisan expired-expiries:create-fut


php artisan upstox:sync-nifty-expired-contracts --expiry=2026-03-17
php artisan upstox:sync-nifty-index-ohlc 2026-03-10 2026-03-17
php artisan upstox:sync-nifty-option-ohlc 2026-03-10 2026-03-17 --expiry=2026-03-17
php artisan ohlc:update-buildup 2026-03-10 2026-03-17

php artisan upstox:sync-expired-futures-to-options --expiry=2026-02-24
php artisan upstox:sync-expired-future-ohlc --expiry=2026-02-24





