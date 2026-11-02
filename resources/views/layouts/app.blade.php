<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Market Depth')</title>
    @vite('resources/css/app.css')
    @stack('styles')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        /* ════════════════════════ GLOBAL DISTRACTION-FREE (ZEN MODE) ════════════════════════ */
        body.zen-mode header {
            display: none !important;
        }
        body.zen-mode #page-updated-time {
            display: none !important;
        }
        body.zen-mode main {
            padding: 0 !important;
            margin: 0 !important;
            max-width: 100vw !important;
            width: 100% !important;
        }
        body.zen-mode main > div.mt-4,
        body.zen-mode main > .mt-4 {
            margin-top: 0 !important;
        }
        /* Floating Zen Exit Trigger */
        #zen-mode-exit-pill {
            display: none;
        }
        body.zen-mode #zen-mode-exit-pill {
            display: flex !important;
        }
    </style>
</head>
<body class="antialiased bg-gray-100 min-h-screen w-full">
@php
    $format = $format ?? 'd M Y, h:i:s'; // e.g. “02 Jun 2025, 10:15 AM”
    $routeName = request()->route()?->getName() ?? '';
@endphp

{{-- Global Floating Zen Exit Pill (Visible only when body.zen-mode is active) --}}
<div id="zen-mode-exit-pill" class="fixed top-2.5 right-3 z-50">
    <button type="button"
            onclick="toggleGlobalZenMode(false)"
            class="flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-900/95 hover:bg-rose-950/95 text-slate-300 hover:text-white border border-slate-700 hover:border-rose-500/80 shadow-2xl backdrop-blur-md text-[11px] font-semibold transition-all group cursor-pointer"
            title="Exit Zen Mode (Esc)">
        <span class="w-2 h-2 rounded-full bg-emerald-400 group-hover:bg-rose-400 animate-pulse"></span>
        <span>Exit Zen Mode</span>
        <span class="text-[9px] font-mono bg-slate-800 px-1 py-0.2 rounded text-slate-400 group-hover:text-white border border-slate-700">ESC</span>
        <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
    </button>
</div>

<header class="sticky top-0 z-40 bg-white border-b border-slate-200 shadow-xs">
    <x-header-nav :format="$format"/>
</header>

<main class="p-2 w-full">
    @yield('filters')
    <div class="mt-4">
        @yield('content')
    </div>
</main>
@if(!Str::startsWith($routeName, ['test.', 'trading.', 'live.strike.chart', 'straddle.chart', 'delta.neutral', 'matching.', 'strike.', 'nifty.matrix', 'nifty.weightage', 'options.breadth', 'vega.analysis', 'nifty.unified', 'order-flow', 'order.flow']))
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
<script>
    window.toggleGlobalZenMode = function(forceState = null) {
        const isZen = forceState !== null 
            ? document.body.classList.toggle('zen-mode', forceState)
            : document.body.classList.toggle('zen-mode');
        
        try {
            sessionStorage.setItem('zen_mode', isZen ? '1' : '0');
        } catch (e) {}

        // Notify charts and dynamic layouts to fit new viewport
        setTimeout(() => {
            window.dispatchEvent(new Event('resize'));
        }, 80);
    };

    // Keyboard shortcut: Press Escape to exit Zen Mode
    window.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.body.classList.contains('zen-mode')) {
            window.toggleGlobalZenMode(false);
        }
    });
</script>
@stack('scripts')
</body>
</html>
