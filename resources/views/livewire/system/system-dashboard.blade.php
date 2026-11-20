<div
    class="relative min-h-[70vh] overflow-hidden"
    x-data="{
        mounted: false,
        activeCard: null,
        particles: Array.from({length: 18}, (_, i) => ({
            id: i,
            x: Math.random() * 100,
            y: Math.random() * 100,
            size: 4 + Math.random() * 10,
            delay: Math.random() * 4,
            duration: 8 + Math.random() * 12
        }))
    }"
    x-init="setTimeout(() => mounted = true, 80)"
>
    {{-- Animated background orbs --}}
    <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-24 -start-24 h-72 w-72 rounded-full bg-gradient-to-br from-indigo-400/30 via-violet-400/20 to-fuchsia-400/10 blur-3xl animate-float-slow"></div>
        <div class="absolute top-1/3 -end-20 h-80 w-80 rounded-full bg-gradient-to-bl from-cyan-400/25 via-blue-400/15 to-indigo-400/10 blur-3xl animate-float-medium"></div>
        <div class="absolute -bottom-16 start-1/4 h-64 w-64 rounded-full bg-gradient-to-tr from-emerald-400/20 via-teal-400/15 to-cyan-400/10 blur-3xl animate-float-fast"></div>

        {{-- Floating particles --}}
        <template x-for="p in particles" :key="p.id">
            <div
                class="absolute rounded-full bg-indigo-400/40 animate-particle"
                :style="`left:${p.x}%; top:${p.y}%; width:${p.size}px; height:${p.size}px; animation-delay:${p.delay}s; animation-duration:${p.duration}s`"
            ></div>
        </template>
    </div>

    {{-- Hero --}}
    <section
        class="mb-10 transition-all duration-700 ease-out"
        :class="mounted ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'"
    >
        <div class="relative overflow-hidden rounded-3xl border border-white/60 bg-white/70 p-6 shadow-xl shadow-indigo-500/5 backdrop-blur-xl sm:p-8">
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 via-transparent to-fuchsia-500/5"></div>
            <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="mb-1 text-sm font-medium text-indigo-600">سامانه مدیریت رویدادهای ورزشی</p>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                        داشبورد سیستم
                    </h1>
                    <p class="mt-2 max-w-xl text-sm text-gray-600">
                        نمای زنده از وضعیت هسته، پیشرفت مراحل توسعه و محدودیت‌های حساب شما.
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-600/20">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        </span>
                        سیستم فعال
                    </span>
                </div>
            </div>
        </div>
    </section>

    {{-- Stats grid --}}
    <section class="mb-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @php
            $stats = [
                ['label' => 'کاربران کل', 'value' => $totalUsers, 'icon' => 'users', 'color' => 'indigo', 'delay' => 100],
                ['label' => 'سازمان‌ها', 'value' => $totalOrganizations, 'icon' => 'building', 'color' => 'violet', 'delay' => 180],
                ['label' => 'حساب سازمانی', 'value' => $organizationalUsers, 'icon' => 'briefcase', 'color' => 'fuchsia', 'delay' => 260],
                ['label' => 'حساب عادی', 'value' => $individualUsers, 'icon' => 'user', 'color' => 'cyan', 'delay' => 340],
            ];
        @endphp

        @foreach ($stats as $i => $stat)
            <div
                class="group relative overflow-hidden rounded-2xl border border-white/70 bg-white/80 p-5 shadow-lg shadow-gray-200/50 backdrop-blur transition-all duration-500 hover:-translate-y-1 hover:shadow-xl hover:shadow-indigo-500/10"
                style="transition-delay: {{ $stat['delay'] }}ms"
                :class="mounted ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'"
                @mouseenter="activeCard = {{ $i }}"
                @mouseleave="activeCard = null"
            >
                <div class="absolute -end-6 -top-6 h-24 w-24 rounded-full bg-{{ $stat['color'] }}-400/10 blur-2xl transition-all duration-500 group-hover:scale-150"></div>
                <div class="relative">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-{{ $stat['color'] }}-50 text-{{ $stat['color'] }}-600 ring-1 ring-{{ $stat['color'] }}-100">
                        @if ($stat['icon'] === 'users')
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        @elseif ($stat['icon'] === 'building')
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        @elseif ($stat['icon'] === 'briefcase')
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        @else
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        @endif
                    </div>
                    <p class="text-xs font-medium text-gray-500">{{ $stat['label'] }}</p>
                    <p class="mt-1 text-3xl font-bold tracking-tight text-gray-900 tabular-nums" x-text="mounted ? '{{ number_format($stat['value']) }}' : '0'">
                        {{ number_format($stat['value']) }}
                    </p>
                </div>
            </div>
        @endforeach
    </section>

    <div class="grid gap-6 lg:grid-cols-5">
        {{-- Progress timeline --}}
        <section
            class="lg:col-span-3 rounded-3xl border border-white/70 bg-white/80 p-6 shadow-lg backdrop-blur transition-all duration-700"
            :class="mounted ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'"
            style="transition-delay: 400ms"
        >
            <div class="mb-5 flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-900">پیشرفت توسعه</h2>
                <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                    ۵ / ۲۲ مرحله
                </span>
            </div>

            <div class="relative space-y-0">
                <div class="absolute start-4 top-2 bottom-2 w-0.5 bg-gradient-to-b from-indigo-400 via-violet-300 to-gray-200"></div>

                @foreach ($progressSteps as $idx => $step)
                    <div
                        class="relative flex gap-4 pb-6 last:pb-0"
                        style="transition-delay: {{ 450 + $idx * 60 }}ms"
                        :class="mounted ? 'opacity-100 translate-x-0' : 'opacity-0 translate-x-4'"
                    >
                        <div class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold
                            {{ $step['done'] ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/40' : 'bg-white text-gray-400 ring-2 ring-gray-200' }}">
                            @if ($step['done'])
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            @else
                                {{ $step['num'] }}
                            @endif
                        </div>
                        <div class="pt-1">
                            <p class="text-sm font-semibold {{ $step['done'] ? 'text-gray-900' : 'text-gray-400' }}">
                                مرحله {{ $step['num'] }} — {{ $step['title'] }}
                            </p>
                            @if ($step['done'])
                                <p class="mt-0.5 text-xs text-emerald-600">تکمیل شده</p>
                            @else
                                <p class="mt-0.5 text-xs text-gray-400">در صف اجرا</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Account entitlement card --}}
        <section
            class="lg:col-span-2 rounded-3xl border border-white/70 bg-white/80 p-6 shadow-lg backdrop-blur transition-all duration-700"
            :class="mounted ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'"
            style="transition-delay: 500ms"
        >
            <h2 class="mb-4 text-lg font-bold text-gray-900">حساب شما</h2>

            @if ($accountType)
                <div class="mb-5 overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-600 p-4 text-white shadow-lg shadow-indigo-500/25">
                    <p class="text-xs font-medium text-indigo-100">نوع حساب</p>
                    <p class="mt-1 text-xl font-bold">
                        {{ $accountType === 'organizational' ? 'سازمانی' : 'عادی' }}
                    </p>
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/20">
                        <div class="h-full w-full origin-right animate-pulse rounded-full bg-white/80"></div>
                    </div>
                </div>

                <div class="space-y-3">
                    @foreach ($limits as $key => $value)
                        <div class="flex items-center justify-between rounded-xl bg-gray-50 px-3 py-2.5">
                            <span class="text-xs text-gray-600">{{ __("account.limits.{$key}") ?: $key }}</span>
                            <span class="text-sm font-bold tabular-nums text-gray-900">
                                {{ $value === null ? '∞' : number_format($value) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 p-6 text-center">
                    <p class="text-sm text-gray-500">برای مشاهده محدودیت‌ها وارد شوید.</p>
                    <a href="{{ route('auth.login') }}" class="mt-3 inline-flex rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-md shadow-indigo-500/25 transition hover:bg-indigo-700">
                        ورود
                    </a>
                </div>
            @endif
        </section>
    </div>

    {{-- Quick links --}}
    <section
        class="mt-8 grid gap-3 sm:grid-cols-3 transition-all duration-700"
        :class="mounted ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'"
        style="transition-delay: 600ms"
    >
        <a href="{{ route('orgs.index') }}" class="group flex items-center gap-3 rounded-2xl border border-white/70 bg-white/80 p-4 shadow-md backdrop-blur transition-all hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-lg">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 transition group-hover:scale-110">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-900">سازمان‌ها</p>
                <p class="text-xs text-gray-500">مدیریت و دعوت اعضا</p>
            </div>
        </a>
        <a href="{{ route('account.index') }}" class="group flex items-center gap-3 rounded-2xl border border-white/70 bg-white/80 p-4 shadow-md backdrop-blur transition-all hover:-translate-y-0.5 hover:border-violet-200 hover:shadow-lg">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-50 text-violet-600 transition group-hover:scale-110">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-900">حساب من</p>
                <p class="text-xs text-gray-500">نوع حساب و ارتقا</p>
            </div>
        </a>
        <a href="{{ route('home') }}" class="group flex items-center gap-3 rounded-2xl border border-white/70 bg-white/80 p-4 shadow-md backdrop-blur transition-all hover:-translate-y-0.5 hover:border-cyan-200 hover:shadow-lg">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-50 text-cyan-600 transition group-hover:scale-110">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-900">خانه</p>
                <p class="text-xs text-gray-500">بازگشت به صفحه اصلی</p>
            </div>
        </a>
    </section>

    <style>
        @keyframes float-slow {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -20px) scale(1.05); }
            66% { transform: translate(-20px, 15px) scale(0.95); }
        }
        @keyframes float-medium {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-25px, -30px) scale(1.08); }
        }
        @keyframes float-fast {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(20px, -15px); }
        }
        @keyframes particle {
            0%, 100% { opacity: 0.2; transform: translateY(0) scale(1); }
            50% { opacity: 0.7; transform: translateY(-40px) scale(1.3); }
        }
        .animate-float-slow { animation: float-slow 18s ease-in-out infinite; }
        .animate-float-medium { animation: float-medium 14s ease-in-out infinite; }
        .animate-float-fast { animation: float-fast 10s ease-in-out infinite; }
        .animate-particle { animation: particle ease-in-out infinite; }
    </style>
</div>
