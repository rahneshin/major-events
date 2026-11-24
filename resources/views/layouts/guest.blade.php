<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-ink-50 text-ink-900 antialiased">
    <div class="flex min-h-screen flex-col">
        <main class="flex flex-1 flex-col items-center justify-center px-4 py-8">
            {{ $slot ?? '' }}
            @yield('content')
        </main>

        <footer class="py-4 text-center text-sm text-ink-500">
            {{ fa_num(jalali(now(), 'Y')) }} — {{ config('app.name') }}
        </footer>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
