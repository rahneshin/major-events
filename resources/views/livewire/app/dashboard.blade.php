<div class="space-y-8">
    @section('page_title', 'داشبورد')

    {{-- سربرگ: خوش‌آمد + تابلوی رویداد بعدی (تنها عنصر پرنگ صفحه) --}}
    <section class="grid gap-6 lg:grid-cols-5 lg:items-stretch">
        <div class="flex flex-col justify-center lg:col-span-3">
            <p class="text-sm text-ink-500">{{ $greeting }}</p>
            <h2 class="mt-1 text-2xl font-bold text-ink-900 sm:text-3xl">{{ auth()->user()->name ?? phone(auth()->user()->phone) }}</h2>
            <p class="mt-3 text-sm leading-7 text-ink-500">
                امروز {{ fa_num(jalali(now(), 'Y/m/d')) }}. حساب شما {{ $accountLabel ?? '—' }} است.
            </p>
            <div class="mt-5 flex flex-wrap gap-2">
                @if ($canCreate)
                    <a href="{{ route('events.create') }}" wire:navigate class="btn-primary">ساخت رویداد جدید</a>
                @else
                    <a href="{{ route('events.index') }}" wire:navigate class="btn-primary">رویدادهای من</a>
                @endif
                @if (($accountLabel ?? '') === 'سازمانی')
                    <a href="{{ route('orgs.index') }}" wire:navigate class="btn-ghost">سازمان‌ها</a>
                @endif
            </div>
        </div>

        <div class="led-panel rounded-3xl p-5 sm:p-6 lg:col-span-2"
             @if ($nextEvent) x-data="countdown('{{ $nextEvent->start_date->copy()->startOfDay()->toIso8601String() }}')" @endif>
            @if ($nextEvent)
                <p class="text-xs text-ink-300">رویداد بعدی شما</p>
                <a href="{{ route('events.show', $nextEvent) }}" wire:navigate class="mt-1 block truncate text-lg font-bold text-white hover:text-flag-300">{{ $nextEvent->title }}</a>
                <p class="mt-0.5 text-xs text-ink-300">شروع: {{ fa_num(jalali($nextEvent->start_date, 'Y/m/d')) }}</p>

                <div class="mt-4 grid grid-cols-4 gap-2">
                    @foreach ([['d', 'روز'], ['h', 'ساعت'], ['m', 'دقیقه'], ['s', 'ثانیه']] as $i => [$key, $unit])
                        <div class="overflow-hidden rounded-xl bg-black/50 py-2.5 text-center ring-1 ring-white/10">
                            @if ($key === 's')
                                <p class="led-digit text-2xl font-bold sm:text-3xl" x-text="s"
                                   x-effect="s; $el.classList.remove('roll'); void $el.offsetWidth; $el.classList.add('roll')">۰۰</p>
                            @else
                                <p class="led-digit roll text-2xl font-bold sm:text-3xl" style="--d: {{ $i * 80 }}ms" x-text="{{ $key }}">۰۰</p>
                            @endif
                            <p class="mt-0.5 text-[11px] text-ink-300">{{ $unit }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-xs text-ink-300">رویداد بعدی شما</p>
                <p class="mt-3 text-base font-bold leading-8 text-white">هنوز رویدادی با تاریخ شروع در آینده ندارید.</p>
                <p class="mt-2 text-sm leading-7 text-ink-300">تاریخ شروع را در رویداد ثبت کنید تا شمارش معکوس اینجا شروع شود.</p>
                <a href="{{ $canCreate ? route('events.create') : route('events.index') }}" wire:navigate class="btn-flag mt-4 text-xs">
                    {{ $canCreate ? 'ساخت رویداد' : 'رفتن به رویدادها' }}
                </a>
            @endif
        </div>
    </section>

    {{-- نوار آمار --}}
    <section class="grid divide-y divide-ink-100 overflow-hidden rounded-2xl border border-ink-100 bg-white sm:grid-cols-2 sm:divide-y-0 lg:grid-cols-4 lg:divide-x">
        <div class="p-5">
            <p class="text-xs font-medium text-ink-500">رویداد فعال</p>
            <p class="mt-1 text-3xl font-bold tabular-nums text-ink-900" x-data="counter({{ $activeCount }})" x-init="run()" x-text="shown">{{ fa_num((string) $activeCount) }}</p>
            @if ($eventLimit !== null)
                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-ink-100" role="img" aria-label="{{ $usedEvents }} از {{ $eventLimit }} رویداد">
                    <div class="bar-fill h-full rounded-full bg-pool-600" style="--w: {{ min(100, $eventLimit > 0 ? (int) round($usedEvents / $eventLimit * 100) : 0) }}%" x-reveal></div>
                </div>
                <p class="mt-1.5 text-[11px] text-ink-400">{{ fa_num((string) $usedEvents) }} از {{ fa_num((string) $eventLimit) }} رویداد مجاز</p>
            @else
                <p class="mt-3 text-[11px] text-ink-400">بدون سقف</p>
            @endif
        </div>
        <div class="p-5">
            <p class="text-xs font-medium text-ink-500">کمیته‌ی فعال</p>
            <p class="mt-1 text-3xl font-bold tabular-nums text-ink-900" x-data="counter({{ $committeeCount }})" x-init="run()" x-text="shown">{{ fa_num((string) $committeeCount) }}</p>
            <p class="mt-3 text-[11px] text-ink-400">در رویدادهای فعال شما</p>
        </div>
        <div class="p-5">
            <p class="text-xs font-medium text-ink-500">سازمان‌های من</p>
            <p class="mt-1 text-3xl font-bold tabular-nums text-ink-900" x-data="counter({{ $orgCount }})" x-init="run()" x-text="shown">{{ fa_num((string) $orgCount) }}</p>
            @php $orgLimit = $limits['organizations'] ?? null; @endphp
            <p class="mt-3 text-[11px] text-ink-400">{{ $orgLimit === null ? 'بدون سقف' : 'سقف: '.fa_num((string) $orgLimit) }}</p>
        </div>
        <div class="p-5">
            <p class="text-xs font-medium text-ink-500">وظایف من</p>
            <p class="mt-1 text-3xl font-bold text-ink-300">—</p>
            <p class="mt-3 text-[11px] text-ink-400">با ماژول وظایف فعال می‌شود</p>
        </div>
    </section>

    <div class="grid gap-8 lg:grid-cols-3">
        {{-- رویدادها --}}
        <section class="lg:col-span-2">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-bold text-ink-900">رویدادهای فعال</h3>
                <a href="{{ route('events.index') }}" wire:navigate class="text-sm font-medium text-pool-700 hover:text-pool-800">همه‌ی رویدادها</a>
            </div>

            @if ($events->isEmpty())
                <div class="rounded-2xl border border-dashed border-ink-200 bg-white px-6 py-12 text-center">
                    <p class="text-base font-bold text-ink-900">هنوز رویداد فعالی ندارید</p>
                    <p class="mx-auto mt-2 max-w-sm text-sm leading-7 text-ink-500">اولین رویداد را بسازید؛ بعد می‌توانید کمیته‌ها را بچینید و همکاران را دعوت کنید.</p>
                    @if ($canCreate)
                        <a href="{{ route('events.create') }}" wire:navigate class="btn-primary mt-5">ساخت اولین رویداد</a>
                    @endif
                </div>
            @else
                <div class="divide-y divide-ink-100 overflow-hidden rounded-2xl border border-ink-100 bg-white">
                    @foreach ($events as $event)
                        <x-event-row :event="$event" wire:key="dash-event-{{ $event->id }}" />
                    @endforeach
                </div>
                @if ($activeCount > $events->count())
                    <p class="mt-3 text-center text-xs text-ink-400">{{ fa_num((string) ($activeCount - $events->count())) }} رویداد دیگر در فهرست کامل هست.</p>
                @endif
            @endif
        </section>

        {{-- چک‌لیست + محدودیت‌ها --}}
        <aside class="space-y-6">
            <section class="rounded-2xl border border-ink-100 bg-white p-5">
                @php
                    $pct = count($checklist) ? (int) round($doneCount / count($checklist) * 100) : 0;
                    $circ = 163.4;
                    $offset = round($circ * (1 - $pct / 100), 1);
                @endphp
                <div class="flex items-center gap-4">
                    <div class="relative h-16 w-16 shrink-0">
                        <svg viewBox="0 0 64 64" class="h-full w-full -rotate-90" aria-hidden="true">
                            <circle cx="32" cy="32" r="26" fill="none" stroke-width="6" class="stroke-ink-100"/>
                            <circle cx="32" cy="32" r="26" fill="none" stroke-width="6" stroke-linecap="round"
                                    class="ring-fill stroke-pool-600" stroke-dasharray="163.4"
                                    style="--full: 163.4; --off: {{ $offset }}" x-reveal/>
                        </svg>
                        <span class="absolute inset-0 flex items-center justify-center text-sm font-bold text-ink-900">{{ fa_num((string) $pct) }}٪</span>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink-900">شروع کار</h3>
                        <p class="mt-0.5 text-xs text-ink-500">{{ fa_num((string) $doneCount) }} از {{ fa_num((string) count($checklist)) }} قدم انجام شده</p>
                    </div>
                </div>

                <ul class="mt-4 space-y-1">
                    @foreach ($checklist as $row)
                        <li class="flex items-center gap-3 rounded-lg px-1 py-2 text-sm">
                            <span @class([
                                'flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                'bg-pool-600 text-white' => $row['done'],
                                'border border-ink-200 text-transparent' => ! $row['done'],
                            ]) aria-hidden="true">✓</span>
                            <span class="{{ $row['done'] ? 'text-ink-400 line-through' : 'text-ink-800' }}">{{ $row['t'] }}</span>
                            @unless ($row['done'])
                                <a href="{{ $row['href'] }}" wire:navigate class="ms-auto text-xs font-semibold text-pool-700 hover:text-pool-800">{{ $row['cta'] }}</a>
                            @endunless
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="rounded-2xl border border-ink-100 bg-white p-5">
                <h3 class="text-base font-bold text-ink-900">سقف‌های حساب شما</h3>
                <dl class="mt-3 divide-y divide-ink-100 text-sm">
                    @foreach ($limits as $key => $val)
                        <div class="flex items-center justify-between py-2.5">
                            <dt class="text-ink-500">{{ __('account.limits.'.$key) ?: $key }}</dt>
                            <dd class="font-bold tabular-nums text-ink-900">{{ $val === null ? 'نامحدود' : fa_num((string) number_format($val)) }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if (($accountLabel ?? '') === 'عادی')
                    <a href="{{ route('account.index') }}" wire:navigate class="btn-primary mt-4 w-full text-xs">ارتقا به حساب سازمانی</a>
                @endif
            </section>
        </aside>
    </div>
</div>
