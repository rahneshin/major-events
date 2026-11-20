<div x-data="{ mounted: false }" x-init="setTimeout(() => mounted = true, 50)">
    @section('page_title', 'داشبورد')

    {{-- Welcome --}}
    <section
        class="mb-8 overflow-hidden rounded-3xl border border-white/70 bg-gradient-to-l from-indigo-600 via-violet-600 to-fuchsia-600 p-6 text-white shadow-xl shadow-indigo-500/20 sm:p-8"
        :class="mounted ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'"
        style="transition: all 0.5s ease"
    >
        <div class="relative">
            <div class="absolute -end-10 -top-10 h-40 w-40 rounded-full bg-white/10 blur-2xl"></div>
            <p class="text-sm font-medium text-indigo-100">{{ $greeting }}</p>
            <h2 class="mt-1 text-2xl font-bold sm:text-3xl">
                {{ auth()->user()->name ?? phone(auth()->user()->phone) }}
            </h2>
            <p class="mt-2 max-w-lg text-sm text-indigo-100/90">
                حساب <strong class="text-white">{{ $accountLabel ?? '—' }}</strong>
                · از اینجا رویدادها، سازمان‌ها و پیشرفت کار را مدیریت می‌کنید.
            </p>
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('orgs.index') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-white/15 px-4 py-2 text-sm font-semibold backdrop-blur transition hover:bg-white/25">
                    سازمان‌ها
                </a>
                <a href="{{ route('account.index') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-white/15 px-4 py-2 text-sm font-semibold backdrop-blur transition hover:bg-white/25">
                    حساب من
                </a>
            </div>
        </div>
    </section>

    {{-- Stats --}}
    <section class="mb-8 grid gap-4 sm:grid-cols-3">
        @php
            $cards = [
                ['label' => 'سازمان‌های من', 'value' => $orgCount, 'hint' => $limits['organizations'] === null ? 'نامحدود' : 'سقف: '.($limits['organizations'] ?? 0)],
                ['label' => 'رویداد فعال', 'value' => '—', 'hint' => 'از مرحله ۵ فعال می‌شود'],
                ['label' => 'وظایف من', 'value' => '—', 'hint' => 'از مرحله ۸ فعال می‌شود'],
            ];
        @endphp
        @foreach ($cards as $i => $c)
            <div
                class="glass card-hover rounded-2xl p-5"
                style="transition: all 0.45s ease {{ 80 + $i * 60 }}ms"
                :class="mounted ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'"
            >
                <p class="text-xs font-medium text-slate-500">{{ $c['label'] }}</p>
                <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900">{{ $c['value'] }}</p>
                <p class="mt-2 text-[11px] text-slate-400">{{ $c['hint'] }}</p>
            </div>
        @endforeach
    </section>

    {{-- Quick start --}}
    <section
        class="grid gap-4 lg:grid-cols-2"
        style="transition: all 0.5s ease 0.25s"
        :class="mounted ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'"
    >
        <div class="glass rounded-2xl p-6">
            <h3 class="text-base font-bold text-slate-900">شروع سریع</h3>
            <p class="mt-1 text-sm text-slate-500">مراحل بعدی توسعه سامانه به‌ترتیب فعال می‌شوند.</p>
            <ul class="mt-4 space-y-3">
                @foreach ([
                    ['done' => true, 't' => 'ورود و نوع حساب'],
                    ['done' => true, 't' => 'سازمان و اعضا'],
                    ['done' => false, 't' => 'ایجاد رویداد (مرحله ۵)'],
                    ['done' => false, 't' => 'کمیته و وظیفه'],
                ] as $row)
                    <li class="flex items-center gap-3 text-sm">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold {{ $row['done'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400' }}">
                            {{ $row['done'] ? '✓' : '·' }}
                        </span>
                        <span class="{{ $row['done'] ? 'text-slate-800' : 'text-slate-400' }}">{{ $row['t'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="glass rounded-2xl p-6">
            <h3 class="text-base font-bold text-slate-900">محدودیت حساب شما</h3>
            <div class="mt-4 space-y-2">
                @foreach ($limits as $key => $val)
                    <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2.5 text-sm">
                        <span class="text-slate-600">{{ __('account.limits.'.$key) ?: $key }}</span>
                        <span class="font-bold tabular-nums text-slate-900">{{ $val === null ? '∞' : number_format($val) }}</span>
                    </div>
                @endforeach
            </div>
            @if (($accountLabel ?? '') === 'عادی')
                <a href="{{ route('account.index') }}" class="btn-primary mt-4 w-full text-xs">ارتقا به سازمانی</a>
            @endif
        </div>
    </section>
</div>
