<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Market Depth')</title>
    @vite('resources/css/app.css')
</head>
<body class="antialiased bg-gray-100 min-h-screen w-full">
<header class="bg-white shadow p-4 flex items-center">
    <x-header-nav />
</header>

<main class="p-6 w-full">
    @yield('filters')
    <div class="mt-4">
        @yield('content')
    </div>
</main>

@php
    $format = $format ?? 'd M Y, h:i:s'; // e.g. “02 Jun 2025, 10:15 AM”
@endphp

<div class="fixed top-0 right-0 m-4 text-sm font-medium">
    {{ \Carbon\Carbon::now('Asia/Kolkata')->format($format) }}
</div>
@if(! request()->has('nr'))
    <script>
        (function () {
            function isWithinTradingHours() {
                const now = new Date();
                const hours = now.getHours();
                const minutes = now.getMinutes();

                // Convert current time to minutes since midnight
                const currentMinutes = hours * 60 + minutes;
                const startMinutes = 9 * 60 + 14;   // 09:14
                const endMinutes = 15 * 60 + 31;    // 15:31

                return currentMinutes >= startMinutes && currentMinutes <= endMinutes;
            }

            function msUntilNextNineSeconds() {
                const now = new Date();
                const seconds = now.getSeconds();
                const ms = now.getMilliseconds();

                if (seconds < 9) {
                    return ((9 - seconds) * 1000) - ms;
                } else {
                    return ((60 - seconds + 9) * 1000) - ms;
                }
            }

            function scheduleReload() {
                const initialDelay = msUntilNextNineSeconds();

                setTimeout(() => {
                    if (isWithinTradingHours()) {
                        window.location.reload();
                    }

                    setInterval(() => {
                        if (isWithinTradingHours()) {
                            window.location.reload();
                        }
                    }, 60000);
                }, initialDelay);
            }

            scheduleReload();
        })();
    </script>
@endif

</body>
</html>
