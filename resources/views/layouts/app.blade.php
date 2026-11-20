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
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased" x-data="{ sidebarOpen: false }">

    {{-- Mobile sidebar overlay --}}
    <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false"></div>

    {{-- Sidebar --}}
    <aside
        class="fixed inset-y-0 start-0 z-50 flex w-72 flex-col border-e border-slate-200/80 bg-white transition-transform duration-300 lg:translate-x-0"
        :class="sidebarOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'"
    >
        <div class="flex h-16 items-center gap-2.5 border-b border-slate-100 px-5">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 text-sm font-bold text-white shadow-md shadow-indigo-500/25">SE</span>
            <div>
                <p class="text-sm font-bold text-slate-900">{{ config('app.name') }}</p>
                <p class="text-[10px] text-slate-400">پنل کاربری</p>
            </div>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto p-3">
            @php
                $nav = [
                    ['route' => 'app.dashboard', 'label' => 'داشبورد', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                    ['route' => 'orgs.index', 'label' => 'سازمان‌ها', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5'],
                    ['route' => 'account.index', 'label' => 'حساب من', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
                ];
            @endphp
            @foreach ($nav as $item)
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                   {{ request()->routeIs($item['route']) ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                    <svg class="h-5 w-5 shrink-0 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                    {{ $item['label'] }}
                </a>
            @endforeach

            <div class="my-3 border-t border-slate-100"></div>
            <p class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">به‌زودی</p>
            @foreach (['رویدادها', 'وظایف من', 'اعلان‌ها'] as $soon)
                <span class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-400">
                    <span class="h-5 w-5 rounded-md bg-slate-100"></span>
                    {{ $soon }}
                    <span class="ms-auto rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px]">به‌زودی</span>
                </span>
            @endforeach
        </nav>

        <div class="border-t border-slate-100 p-3">
            @auth
            <div class="flex items-center gap-3 rounded-xl bg-slate-50 px-3 py-2.5">
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-700">
                    {{ mb_substr(auth()->user()->name ?? 'ک', 0, 1) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-slate-800">{{ auth()->user()->name ?? phone(auth()->user()->phone) }}</p>
                    <p class="truncate text-[11px] text-slate-400">{{ auth()->user()->account_type?->value === 'organizational' ? 'سازمانی' : 'عادی' }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('auth.logout') }}" class="mt-2">
                @csrf
                <button type="submit" class="w-full rounded-xl px-3 py-2 text-start text-sm text-rose-600 transition hover:bg-rose-50">خروج</button>
            </form>
            @endauth
        </div>
    </aside>

    {{-- Main --}}
    <div class="lg:ps-72">
        <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200/80 bg-white/90 px-4 backdrop-blur-xl sm:px-6">
            <button type="button" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" @click="sidebarOpen = true">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div class="flex-1">
                <h1 class="text-sm font-semibold text-slate-800">@yield('page_title', 'پنل')</h1>
            </div>
            <a href="{{ route('home') }}" class="hidden text-xs text-slate-400 transition hover:text-indigo-600 sm:inline">مشاهده سایت</a>
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

        <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6">
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
