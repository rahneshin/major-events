<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ادمین — '.config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased" x-data="{ sidebarOpen: false }">

    <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-black/50 lg:hidden" @click="sidebarOpen = false"></div>

    <aside
        class="fixed inset-y-0 start-0 z-50 flex w-64 flex-col border-e border-white/5 bg-slate-900 transition-transform duration-300 lg:translate-x-0"
        :class="sidebarOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'"
    >
        <div class="flex h-16 items-center gap-2 border-b border-white/5 px-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-rose-500 to-orange-500 text-xs font-bold text-white">AD</span>
            <div>
                <p class="text-sm font-bold">پنل ادمین</p>
                <p class="text-[10px] text-slate-500">مالک سامانه</p>
            </div>
        </div>

        <nav class="flex-1 space-y-0.5 p-3 text-sm">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 {{ request()->routeIs('admin.dashboard') ? 'bg-white/10 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                داشبورد سیستم
            </a>
            <span class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-slate-600">کاربران <span class="ms-auto text-[10px]">به‌زودی</span></span>
            <span class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-slate-600">سازمان‌ها <span class="ms-auto text-[10px]">به‌زودی</span></span>
            <span class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-slate-600">لاگ‌ها <span class="ms-auto text-[10px]">به‌زودی</span></span>
            <span class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-slate-600">تنظیمات <span class="ms-auto text-[10px]">به‌زودی</span></span>
        </nav>

        <div class="border-t border-white/5 p-3">
            <a href="{{ route('app.dashboard') }}" class="block rounded-lg px-3 py-2 text-sm text-slate-400 hover:bg-white/5 hover:text-white">← بازگشت به پنل کاربر</a>
        </div>
    </aside>

    <div class="lg:ps-64">
        <header class="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-white/5 bg-slate-950/80 px-4 backdrop-blur">
            <button type="button" class="rounded p-2 text-slate-400 hover:bg-white/5 lg:hidden" @click="sidebarOpen = true">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <h1 class="text-sm font-semibold">@yield('page_title', 'ادمین')</h1>
        </header>

        <main class="p-4 sm:p-6">
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
