<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Market Depth')</title>
    @vite('resources/css/app.css')
    @stack('styles')
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="antialiased bg-gray-100 min-h-screen w-full">
<header class="bg-white shadow p-4 flex items-center">
    <x-header-nav/>
</header>

<main class="p-2 w-full">
    @yield('filters')
    <div class="mt-4">
        @yield('content')
    </div>
</main>

@php
    $format = $format ?? 'd M Y, h:i:s'; // e.g. “02 Jun 2025, 10:15 AM”
$routeName = request()->route()?->getName() ?? '';
@endphp

<div class="fixed top-0 right-0 m-4 rounded-xl border border-slate-200 bg-white/90 px-3 py-2 text-sm font-medium text-slate-700 shadow-sm backdrop-blur"
    id="page-updated-time">
    {{ \Carbon\Carbon::now('Asia/Kolkata')->format($format) }}
</div>
@if(!Str::startsWith($routeName, ['test.', 'trading.']))
{{--    @if(!request()->has('nr'))--}}
<script>
    ( function () {
        function isWithinTradingHours() {
            const now = new Date();
            const day = now.getDay();
            if (day === 0 || day === 6) return false;

            const currentSeconds =
                now.getHours() * 3600 +
                now.getMinutes() * 60 +
                now.getSeconds();

            const startSeconds = 9 * 3600 + 15 * 60 + 9; // 09:15:09
            const endSeconds = 15 * 3600 + 30 * 60 + 9;  // 15:30:09

            return currentSeconds >= startSeconds && currentSeconds <= endSeconds;
        }

        function msUntilNextNineSeconds () {
            const now = new Date();
            const seconds = now.getSeconds();
            const ms = now.getMilliseconds();

            if (seconds < 9) {
                return ( ( 9 - seconds ) * 1000 ) - ms;
            } else {
                return ( ( 60 - seconds + 9 ) * 1000 ) - ms;
            }
        }

        function scheduleReload () {
            const initialDelay = msUntilNextNineSeconds();

            setTimeout(() => {
                if (isWithinTradingHours()) {
                    window.location.reload();
                }

                setInterval(() => {
                    if (isWithinTradingHours()) {
                        window.location.reload();
                    }
                }, 309000); // 5 minutes 9 seconds = 309,000 milliseconds
            }, initialDelay);
        }

        scheduleReload();
    } )();
</script>
@endif
@stack('scripts')
</body>
</html>
