php artisan upstox:sync-nifty-expiries
php artisan expired-expiries:create-fut


php artisan upstox:sync-nifty-expired-contracts --expiry=2026-02-10
php artisan upstox:sync-nifty-index-ohlc 2026-02-03 2026-02-10
php artisan upstox:sync-nifty-option-ohlc 2026-02-03 2026-02-10 --expiry=2026-02-10

php artisan upstox:sync-expired-futures-to-options






