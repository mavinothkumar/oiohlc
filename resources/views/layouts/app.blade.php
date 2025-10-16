<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Market Depth')</title>
    @vite('resources/css/app.css')
</head>
<body class="antialiased bg-gray-100 min-h-screen w-full">
<header class="bg-white shadow p-4 flex justify-between items-center">
    <div class="font-bold text-xl">Market Depth</div>
    <div class="text-sm text-gray-500 flex items-center">
        <span id="last-refresh">Last Refresh: <span id="refresh-time"></span></span>
        <button id="manual-refresh" class="ml-4 bg-blue-500 text-white px-3 py-1 rounded">Refresh Now</button>
    </div>
</header>

<main class="p-6 w-full">
    @yield('filters')
    <div class="mt-4">
        @yield('content')
    </div>
</main>
</body>
</html>
