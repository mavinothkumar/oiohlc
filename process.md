php artisan upstox:sync-nifty-expiries
php artisan expired-expiries:create-fut


php artisan upstox:sync-nifty-expired-contracts --expiry=2026-03-10
php artisan upstox:sync-nifty-index-ohlc 2026-03-02 2026-03-10
php artisan upstox:sync-nifty-option-ohlc 2026-03-02 2026-03-10 --expiry=2026-03-10

php artisan upstox:sync-expired-futures-to-options --expiry=2026-02-24
php artisan upstox:sync-expired-future-ohlc --expiry=2026-02-24






