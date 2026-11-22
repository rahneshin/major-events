<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0a4ba0">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-ink-50 text-ink-900 antialiased"
      x-data="{ sidebarOpen: false, scrolled: false }"
      @scroll.window.passive="scrolled = window.scrollY > 4"
      @keydown.escape.window="sidebarOpen = false">

    @php
        $nav = [
            ['route' => 'app.dashboard', 'match' => 'app.dashboard', 'label' => 'داشبورد', 'short' => 'داشبورد', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
            ['route' => 'events.index', 'match' => 'events.*', 'label' => 'رویدادها', 'short' => 'رویدادها', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
            ['route' => 'tasks.mine', 'match' => 'tasks.*', 'label' => 'وظایف من', 'short' => 'وظایف', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['route' => 'orgs.index', 'match' => 'orgs.*', 'label' => 'سازمان‌ها', 'short' => 'سازمان‌ها', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5'],
            ['route' => 'account.index', 'match' => 'account.*', 'label' => 'حساب من', 'short' => 'حساب', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
        ];
        $isOrg = auth()->user()?->account_type?->value === 'organizational';
    @endphp

    <div x-show="sidebarOpen" x-cloak x-transition.opacity.duration.200ms
         class="fixed inset-0 z-40 bg-ink-950/50 lg:hidden" @click="sidebarOpen = false" aria-hidden="true"></div>

    <aside class="fixed inset-y-0 start-0 z-50 flex w-72 flex-col border-e border-ink-100 bg-white transition-transform duration-300 lg:translate-x-0"
           :class="sidebarOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'"
           aria-label="منوی اصلی">
        <div class="flex h-16 items-center gap-3 border-b border-ink-100 px-5">
            <x-brand-mark />
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-bold text-ink-900">{{ config('app.name') }}</p>
                <p class="text-[11px] text-ink-400">پنل کاربری</p>
            </div>
            <button type="button" class="rounded-lg p-1.5 text-ink-500 hover:bg-ink-100 lg:hidden" @click="sidebarOpen = false" aria-label="بستن منو">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto p-3">
            @foreach ($nav as $item)
                @php $active = request()->routeIs($item['match']); @endphp
                <a href="{{ route($item['route']) }}" wire:navigate
                   @class([
                       'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors',
                       'bg-pool-50 text-pool-700' => $active,
                       'text-ink-600 hover:bg-ink-50 hover:text-ink-900' => ! $active,
                   ])
                   @if ($active) aria-current="page" @endif>
                    <svg class="h-5 w-5 shrink-0 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                    {{ $item['label'] }}
                </a>
            @endforeach

            <div class="my-3 border-t border-ink-100"></div>
            <p class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-ink-400">به‌زودی</p>
            @foreach (['اعلان‌ها'] as $soon)
                <span class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-ink-400">
                    <span class="h-5 w-5 rounded-md bg-ink-100"></span>
                    {{ $soon }}
                    <span class="ms-auto rounded-md bg-ink-100 px-1.5 py-0.5 text-[10px]">به‌زودی</span>
                </span>
            @endforeach
        </nav>

        <div class="border-t border-ink-100 p-3">
            @auth
            <div class="flex items-center gap-3 rounded-xl bg-ink-50 px-3 py-2.5">
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-pool-100 text-sm font-bold text-pool-700">
                    {{ mb_substr(auth()->user()->name ?? 'ک', 0, 1) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-ink-800">{{ auth()->user()->name ?? phone(auth()->user()->phone) }}</p>
                    <p class="truncate text-[11px] text-ink-400">{{ $isOrg ? 'سازمانی' : 'عادی' }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('auth.logout') }}" class="mt-2">
                @csrf
                <button type="submit" class="w-full rounded-xl px-3 py-2 text-start text-sm text-signal-600 transition hover:bg-signal-50">خروج</button>
            </form>
            @endauth
        </div>
    </aside>

    <div class="lg:ps-72">
        <header class="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-ink-100 bg-white/90 px-4 backdrop-blur-xl sm:px-6"
                :class="scrolled && 'shadow-sm'">
            <button type="button" class="rounded-lg p-2 text-ink-600 hover:bg-ink-100 lg:hidden" @click="sidebarOpen = true" aria-label="باز کردن منو">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-sm font-bold text-ink-900">@yield('page_title', 'پنل')</h1>
            </div>
            <span class="hidden text-xs text-ink-500 sm:inline">امروز {{ fa_num(jalali(now(), 'Y/m/d')) }}</span>
            <a href="{{ route('home') }}" class="hidden rounded-lg px-2.5 py-1.5 text-xs text-ink-500 transition-colors hover:bg-ink-100 hover:text-ink-800 sm:inline">مشاهده سایت</a>
        </header>

        @if (session('success') || session('error') || session('warning') || session('info'))
            <div class="mx-auto max-w-6xl px-4 pt-4 sm:px-6">
                @foreach (['success', 'error', 'warning', 'info'] as $type)
                    @if (session($type))
                        <x-toast :type="$type">{{ session($type) }}</x-toast>
                    @endif
                @endforeach
            </div>
        @endif

        <main class="page-enter mx-auto max-w-6xl px-4 pb-28 pt-6 sm:px-6 lg:pb-10">
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </div>

    {{-- ناوبری پایین (موبایل) --}}
    <nav class="pb-safe fixed inset-x-0 bottom-0 z-40 border-t border-ink-200/80 bg-white/95 backdrop-blur-xl lg:hidden" aria-label="ناوبری سریع">
        <ul class="mx-auto grid max-w-md grid-cols-5 px-1 py-1.5">
            @foreach ($nav as $item)
                @php $active = request()->routeIs($item['match']); @endphp
                <li>
                    <a href="{{ route($item['route']) }}" wire:navigate
                       @class([
                           'flex flex-col items-center gap-0.5 rounded-xl py-1.5 text-[11px] font-medium transition-colors',
                           'text-pool-700' => $active,
                           'text-ink-500' => ! $active,
                       ])
                       @if ($active) aria-current="page" @endif>
                        <span @class(['flex h-7 w-12 items-center justify-center rounded-full transition-colors', 'bg-pool-100' => $active])>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                        </span>
                        {{ $item['short'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    @livewireScripts
    @stack('scripts')
</body>
</html>
