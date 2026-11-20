<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('meta_description', 'پلتفرم هوشمند برنامه‌ریزی و مدیریت رویدادهای ورزشی')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased" x-data="{ navOpen: false, scrolled: false }" @scroll.window="scrolled = window.scrollY > 24">

    {{-- Public Navbar --}}
    <header
        class="fixed inset-x-0 top-0 z-50 transition-all duration-300"
        :class="scrolled ? 'border-b border-slate-200/80 bg-white/90 shadow-sm backdrop-blur-xl' : 'bg-transparent'"
    >
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 text-sm font-bold text-white shadow-md shadow-indigo-500/30">
                    SE
                </span>
                <span class="text-base font-bold tracking-tight text-slate-900">{{ config('app.name') }}</span>
            </a>

            <nav class="hidden items-center gap-8 text-sm font-medium text-slate-600 md:flex">
                <a href="#features" class="transition hover:text-indigo-600">امکانات</a>
                <a href="#how" class="transition hover:text-indigo-600">چگونه کار می‌کند</a>
                <a href="#plans" class="transition hover:text-indigo-600">حساب‌ها</a>
            </nav>

            <div class="hidden items-center gap-3 md:flex">
                @auth
                    <a href="{{ route('app.dashboard') }}" class="btn-primary text-xs sm:text-sm">ورود به پنل</a>
                @else
                    <a href="{{ route('auth.login') }}" class="btn-ghost text-xs sm:text-sm">ورود</a>
                    <a href="{{ route('auth.login') }}" class="btn-primary text-xs sm:text-sm">شروع رایگان</a>
                @endauth
            </div>

            <button type="button" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 md:hidden" @click="navOpen = !navOpen" aria-label="منو">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>

        {{-- Mobile menu --}}
        <div x-show="navOpen" x-transition class="border-t border-slate-100 bg-white px-4 py-4 md:hidden" @click.outside="navOpen = false">
            <div class="flex flex-col gap-3 text-sm font-medium">
                <a href="#features" @click="navOpen=false" class="rounded-lg px-3 py-2 hover:bg-slate-50">امکانات</a>
                <a href="#how" @click="navOpen=false" class="rounded-lg px-3 py-2 hover:bg-slate-50">چگونه کار می‌کند</a>
                <a href="#plans" @click="navOpen=false" class="rounded-lg px-3 py-2 hover:bg-slate-50">حساب‌ها</a>
                <hr class="my-1 border-slate-100">
                @auth
                    <a href="{{ route('app.dashboard') }}" class="btn-primary w-full">ورود به پنل</a>
                @else
                    <a href="{{ route('auth.login') }}" class="btn-primary w-full">ورود / ثبت‌نام</a>
                @endauth
            </div>
        </div>
    </header>

    <main>
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 py-10 sm:flex-row sm:px-6">
            <div class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-xs font-bold text-white">SE</span>
                <span class="text-sm font-semibold text-slate-800">{{ config('app.name') }}</span>
            </div>
            <p class="text-xs text-slate-500">
                © {{ fa_num(jalali(now(), 'Y')) }} — پلتفرم هوشمند مدیریت رویداد ورزشی
            </p>
        </div>
    </footer>

    @livewireScripts
    @stack('scripts')
</body>
</html>
