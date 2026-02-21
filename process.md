php artisan upstox:sync-nifty-expiries
php artisan expired-expiries:create-fut


php artisan upstox:sync-nifty-expired-contracts --expiry=2026-02-17
php artisan upstox:sync-nifty-index-ohlc 2026-02-10 2026-02-17
php artisan upstox:sync-nifty-option-ohlc 2025-05-22 2025-05-29 --expiry=2025-05-29

php artisan upstox:sync-expired-futures-to-options






