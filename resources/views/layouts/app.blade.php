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
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased" x-data="{ mobileMenuOpen: false }">
    {{-- Header --}}
    <header class="sticky top-0 z-40 border-b border-gray-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex h-14 max-w-7xl items-center justify-between px-4">
            <a href="{{ url('/') }}" class="text-lg font-semibold text-primary-700">
                {{ config('app.name') }}
            </a>

            <div class="flex items-center gap-3">
                @auth
                    <span class="hidden text-sm text-gray-600 sm:inline">
                        {{ auth()->user()->name ?? phone(auth()->user()->phone) }}
                    </span>
                @endauth

                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg p-2 text-gray-600 hover:bg-gray-100 sm:hidden"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    aria-label="منو"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
        </div>
    </header>

    {{-- Mobile menu --}}
    <div
        x-show="mobileMenuOpen"
        x-transition
        class="fixed inset-0 z-50 bg-black/40 sm:hidden"
        @click="mobileMenuOpen = false"
    >
        <div
            class="absolute inset-y-0 end-0 w-72 bg-white shadow-xl"
            @click.stop
        >
            <div class="flex items-center justify-between border-b px-4 py-3">
                <span class="font-semibold">منو</span>
                <button type="button" @click="mobileMenuOpen = false" class="rounded p-1 hover:bg-gray-100">
                    ✕
                </button>
            </div>
            <nav class="p-4 space-y-2">
                {{-- منوها در مراحل بعد اضافه می‌شوند --}}
                <a href="{{ url('/') }}" class="block rounded-lg px-3 py-2 hover:bg-gray-100">خانه</a>
            </nav>
        </div>
    </div>

    {{-- Flash messages --}}
    @if (session('success') || session('error') || session('warning') || session('info'))
        <div class="mx-auto max-w-7xl px-4 pt-4">
            @if (session('success'))
                <x-toast type="success">{{ session('success') }}</x-toast>
            @endif
            @if (session('error'))
                <x-toast type="error">{{ session('error') }}</x-toast>
            @endif
            @if (session('warning'))
                <x-toast type="warning">{{ session('warning') }}</x-toast>
            @endif
            @if (session('info'))
                <x-toast type="info">{{ session('info') }}</x-toast>
            @endif
        </div>
    @endif

    <main class="mx-auto max-w-7xl px-4 py-6">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    {{-- Bottom mobile nav (placeholder) --}}
    <nav class="fixed bottom-0 inset-x-0 z-30 border-t border-gray-200 bg-white sm:hidden">
        <div class="flex h-14 items-center justify-around">
            <a href="{{ url('/') }}" class="flex flex-col items-center text-xs text-primary-600">
                <span>خانه</span>
            </a>
            {{-- آیتم‌های بیشتر در مراحل بعد --}}
        </div>
    </nav>

    @livewireScripts
    @stack('scripts')
</body>
</html>
