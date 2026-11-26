<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <meta http-equiv="refresh" content="60">
    <meta name="theme-color" content="#0a4ba0">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-ink-50 text-ink-900 antialiased">
    <main class="mx-auto max-w-4xl space-y-5 px-4 py-6 sm:px-6">
        @yield('content')
    </main>
    <footer class="mx-auto max-w-4xl px-4 pb-8 text-center text-xs text-ink-400 sm:px-6">
        {{ __('publicpage.readonly') }}
        · {{ __('publicpage.powered_by', ['app' => config('app.name')]) }}
    </footer>
</body>
</html>
