<div>
    @section('page_title', 'داشبورد سیستم')

    <div class="mb-6">
        <h2 class="text-xl font-bold text-white">نمای کلی سامانه</h2>
        <p class="mt-1 text-sm text-slate-400">آمار لحظه‌ای کاربران و سازمان‌ها · پنل کامل Filament در مرحله ۲۰</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['label' => 'کاربران کل', 'value' => $users, 'color' => 'from-indigo-500 to-violet-500'],
            ['label' => 'سازمان‌ها', 'value' => $orgs, 'color' => 'from-violet-500 to-fuchsia-500'],
            ['label' => 'حساب سازمانی', 'value' => $organizational, 'color' => 'from-fuchsia-500 to-rose-500'],
            ['label' => 'حساب عادی', 'value' => $individual, 'color' => 'from-cyan-500 to-blue-500'],
        ] as $stat)
            <div class="relative overflow-hidden rounded-2xl border border-white/5 bg-slate-900 p-5">
                <div class="absolute -end-6 -top-6 h-24 w-24 rounded-full bg-gradient-to-br {{ $stat['color'] }} opacity-20 blur-2xl"></div>
                <p class="text-xs font-medium text-slate-400">{{ $stat['label'] }}</p>
                <p class="mt-2 text-3xl font-bold tabular-nums text-white">{{ number_format($stat['value']) }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-8 rounded-2xl border border-white/5 bg-slate-900 p-6">
        <h3 class="text-sm font-semibold text-white">وضعیت مراحل توسعه</h3>
        <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['00–04', 'هسته احراز و سازمان', true],
                ['05–08', 'رویداد · کمیته · وظیفه', false],
                ['09–14', 'بودجه · ریسک · خروجی', false],
                ['15–22', 'AI · ادمین · استقرار', false],
            ] as $block)
                <div class="rounded-xl border border-white/5 bg-slate-950/50 px-4 py-3">
                    <p class="text-xs font-bold {{ $block[2] ? 'text-emerald-400' : 'text-slate-500' }}">{{ $block[0] }}</p>
                    <p class="mt-0.5 text-sm text-slate-300">{{ $block[1] }}</p>
                    <p class="mt-1 text-[10px] {{ $block[2] ? 'text-emerald-500' : 'text-slate-600' }}">
                        {{ $block[2] ? 'تکمیل شده' : 'در صف' }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>
</div>
