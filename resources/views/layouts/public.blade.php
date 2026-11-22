<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0a4ba0">
    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('meta_description', 'پلتفرم برنامه‌ریزی و مدیریت رویدادهای ورزشی')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
{{-- dark=true: بالای صفحه زمینه‌ی آبی است و منو سفید نمایش داده می‌شود. صفحه‌های روشن @section('light_nav') بگذارند. --}}
<body class="min-h-screen bg-ink-50 text-ink-900 antialiased"
      x-data="{ navOpen: false, scrolled: false, active: '', dark: {{ $__env->hasSection('light_nav') ? 'false' : 'true' }} }"
      x-init="scrolled = window.scrollY > 24"
      @scroll.window.passive="scrolled = window.scrollY > 24"
      @keydown.escape.window="navOpen = false">

    <div class="scroll-progress" aria-hidden="true"></div>

    <header class="fixed inset-x-0 top-0 z-50 transition-colors duration-300"
            :class="scrolled
                ? 'border-b border-ink-100 bg-white/90 text-ink-800 shadow-sm shadow-ink-900/5 backdrop-blur-xl'
                : (dark ? 'text-white' : 'text-ink-800')">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5" aria-label="{{ config('app.name') }}">
                <x-brand-mark />
                <span class="text-base font-bold tracking-tight">{{ config('app.name') }}</span>
            </a>

            <nav class="hidden items-center gap-1 text-sm font-medium md:flex" aria-label="بخش‌های صفحه">
                @foreach ([['features', 'امکانات'], ['plans', 'نوع حساب']] as [$id, $label])
                    <a href="#{{ $id }}"
                       class="relative rounded-lg px-3 py-2 opacity-80 transition hover:opacity-100"
                       :class="active === '{{ $id }}' && 'opacity-100'">
                        {{ $label }}
                        <span class="absolute inset-x-3 -bottom-0.5 h-0.5 origin-right scale-x-0 rounded-full bg-flag-500 transition-transform duration-300"
                              :class="active === '{{ $id }}' && 'scale-x-100'"></span>
                    </a>
                @endforeach
            </nav>

            <div class="hidden items-center gap-2 md:flex">
                @auth
                    <a href="{{ route('app.dashboard') }}" class="btn-primary text-xs sm:text-sm">ورود به پنل</a>
                @else
                    <a href="{{ route('auth.login') }}"
                       class="rounded-xl px-3.5 py-2 text-sm font-semibold transition"
                       :class="scrolled || !dark ? 'text-ink-700 hover:bg-ink-100' : 'text-white/90 hover:bg-white/10'">ورود</a>
                    <a href="{{ route('auth.login') }}" class="btn-primary text-xs sm:text-sm">شروع رایگان</a>
                @endauth
            </div>

            <button type="button" class="rounded-lg p-2 md:hidden" @click="navOpen = !navOpen" aria-label="منو"
                    :class="scrolled || !dark ? 'text-ink-700 hover:bg-ink-100' : 'text-white hover:bg-white/10'">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>

        <div x-show="navOpen" x-cloak x-transition class="border-t border-ink-100 bg-white px-4 py-4 text-ink-800 md:hidden" @click.outside="navOpen = false">
            <div class="flex flex-col gap-1 text-sm font-medium">
                <a href="#features" @click="navOpen = false" class="rounded-lg px-3 py-2.5 hover:bg-ink-50">امکانات</a>
                <a href="#plans" @click="navOpen = false" class="rounded-lg px-3 py-2.5 hover:bg-ink-50">نوع حساب</a>
                <hr class="my-2 border-ink-100">
                @auth
                    <a href="{{ route('app.dashboard') }}" class="btn-primary w-full">ورود به پنل</a>
                @else
                    <a href="{{ route('auth.login') }}" class="btn-primary w-full">ورود یا ثبت‌نام</a>
                @endauth
            </div>
        </div>
    </header>

    <main>
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <footer class="bg-pool-950 text-pool-200">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-3">
            <div>
                <div class="flex items-center gap-2.5 text-white">
                    <x-brand-mark class="h-8 w-8" />
                    <span class="font-bold">{{ config('app.name') }}</span>
                </div>
                <p class="mt-4 max-w-xs text-sm leading-7 text-pool-300">
                    سامانه‌ی فارسی برنامه‌ریزی و مدیریت رویدادهای ورزشی؛ از اولین جلسه تا روز مسابقه.
                </p>
            </div>
            <nav class="text-sm" aria-label="پیوندهای فوتر">
                <p class="font-semibold text-white">در سایت</p>
                <ul class="mt-4 space-y-2.5">
                    <li><a href="#features" class="transition hover:text-white">امکانات</a></li>
                    <li><a href="#plans" class="transition hover:text-white">نوع حساب</a></li>
                </ul>
            </nav>
            <div class="text-sm">
                <p class="font-semibold text-white">شروع کنید</p>
                <p class="mt-4 leading-7 text-pool-300">با شماره‌ی موبایل وارد شوید و اولین رویدادتان را بسازید.</p>
                <a href="{{ route('auth.login') }}" class="btn-flag mt-4">ورود با موبایل</a>
            </div>
        </div>
        <div class="border-t border-white/10">
            <p class="mx-auto max-w-6xl px-4 py-5 text-xs text-pool-300 sm:px-6">© {{ fa_num(jalali(now(), 'Y')) }} {{ config('app.name') }}</p>
        </div>
    </footer>

    @livewireScripts
    @stack('scripts')
</body>
</html>
