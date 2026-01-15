upstox:sync-nifty-expiries
upstox:sync-nifty-expired-contracts --expiry=
php artisan upstox:sync-nifty-index-ohlc 2025-12-31 2026-01-13
php artisan upstox:sync-nifty-option-ohlc 2025-12-31 2026-01-13 --expiry=2026-01-13

php artisan upstox:sync-expired-futures-to-options
