@extends('layouts.public')

@section('title', config('app.name') . ' — مدیریت رویداد ورزشی')
@section('meta_description', 'کمیته‌ها، اعضا، وظیفه‌ها و بودجه‌ی رویدادهای ورزشی را در یک سامانه‌ی فارسی و راست‌به‌چپ مدیریت کنید.')

@section('content')
@php
    $demoStart = now()->addDays(12)->addHours(5)->addMinutes(20)->toIso8601String();

    $live = [
        ['رویداد و اهداف', 'مشخصات رویداد، نوع ورزش، مقیاس، تاریخ و محل را ثبت کنید و اهداف را بنویسید.'],
        ['اعضا و نقش‌ها', 'همکاران را با شماره‌ی موبایل دعوت کنید و برای هرکدام نقش مشخص کنید؛ از مدیر رویداد تا ناظر.'],
        ['سازمان‌ها', 'برای چند رویداد یک سازمان بسازید و اعضای آن را با نقش‌های مدیریتی کنار هم نگه دارید.'],
        ['کمیته‌ها', 'کمیته‌های فنی، پزشکی، بازاریابی و داوطلبان را بسازید و رئیس و اعضای هرکدام را تعیین کنید.'],
    ];
    $soon = [
        ['وظایف و زمان‌بندی', 'کارها را بین کمیته‌ها تقسیم کنید، وابستگی بدهید و روی تقویم شمسی ببینید.'],
        ['بودجه و درآمد', 'خطوط بودجه به ریال، نسخه‌ی مصوب و مقایسه با هزینه‌ی واقعی.'],
        ['ریسک', 'فهرست ریسک‌ها و ماتریس احتمال و اثر، با مسئول هر ریسک.'],
        ['مسابقات', 'ثبت‌نام تیم‌ها، قرعه‌کشی قطعی، برنامه‌ی بازی‌ها و ثبت نتایج (حساب سازمانی).'],
        ['اسپانسرینگ و بازاریابی', 'بسته‌های حمایت، دارایی‌های تبلیغاتی و تقویم کمپین.'],
        ['پایگاه دانش و برنامه‌ریز هوشمند', 'منابع خودتان را بارگذاری کنید و پیشنهادهای مستند بگیرید؛ هیچ پیشنهادی بدون تأیید شما وارد برنامه نمی‌شود.'],
    ];
    $steps = [
        ['پیش‌نویس', 'رویداد را تعریف کنید', 'نام، نوع ورزش، مقیاس، تاریخ و محل را بنویسید و اهداف را مشخص کنید. هنوز چیزی قطعی نیست و هر زمان می‌توانید ویرایش کنید.'],
        ['برنامه‌ریزی', 'تیم و کمیته‌ها را بچینید', 'همکاران را دعوت کنید، نقش بدهید و کمیته‌ها را با رئیس و اعضا بسازید. از قالب پیشنهادی هم می‌توانید شروع کنید.'],
        ['آماده‌ی اجرا', 'وظیفه، بودجه و ریسک را نهایی کنید', 'کارها، بودجه‌ی مصوب و ریسک‌ها را مرور کنید. این ماژول‌ها در نسخه‌های بعدی اضافه می‌شوند.'],
        ['در حال اجرا', 'روز رویداد را پیگیری کنید', 'با تکمیل ماژول‌های بالا، وضعیت کارها و کمیته‌ها را در همان روز مسابقه یک‌جا می‌بینید.'],
    ];
@endphp

<section id="top" class="bg-pool-field relative isolate overflow-hidden pb-24 pt-32 text-white sm:pt-40 lg:pb-32"
         x-inview.mid="active = ''">
    <div class="parallax-slow absolute inset-0 -z-10">
        <x-lanes class="lanes-draw absolute inset-x-0 top-10 h-[120%] w-full text-white/25" />
    </div>

    <div class="mx-auto grid max-w-6xl items-center gap-14 px-4 sm:px-6 lg:grid-cols-12">
        <div class="lg:col-span-6">
            <h1 class="text-4xl font-bold leading-[1.35] sm:text-5xl lg:text-[3.4rem]">
                رویداد ورزشی‌تان را از اولین جلسه تا روز مسابقه یک‌جا مدیریت کنید
            </h1>
            <p class="mt-6 max-w-xl text-base leading-8 text-pool-100 sm:text-lg">
                کمیته‌ها، اعضا، وظیفه‌ها و بودجه را کنار هم ببینید. با شماره‌ی موبایل وارد می‌شوید؛ رمز عبوری در کار نیست.
            </p>
            <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('auth.login') }}" class="btn-flag px-7 py-3 text-base">ساخت اولین رویداد</a>
                <a href="#features" class="btn-ghost-light px-7 py-3 text-base">دیدن امکانات</a>
            </div>
        </div>

        <div class="lg:col-span-6">
            <div class="parallax-fast mx-auto max-w-md lg:max-w-none">
                <div class="led-panel rounded-3xl p-5 sm:p-7" x-data="countdown('{{ $demoStart }}')">
                    <div class="flex items-center justify-between text-xs text-ink-300">
                        <span class="flex items-center gap-2">
                            <span class="relative flex h-2 w-2">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-flag-500 opacity-70"></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-flag-500"></span>
                            </span>
                            تابلوی رویداد
                        </span>
                        <span class="rounded-md bg-white/10 px-2 py-0.5">نمونه</span>
                    </div>

                    <p class="mt-5 text-lg font-bold text-white sm:text-xl">لیگ والیبال دانشگاه‌ها</p>
                    <p class="mt-1 text-xs text-ink-300">تا شروع مسابقات</p>

                    <div class="mt-4 grid grid-cols-4 gap-2.5 sm:gap-3">
                        @foreach ([['d', 'روز', '۱۲', 0], ['h', 'ساعت', '۰۵', 80], ['m', 'دقیقه', '۲۰', 160], ['s', 'ثانیه', '۰۰', 240]] as [$key, $unit, $initial, $delay])
                            <div class="overflow-hidden rounded-xl bg-black/50 py-3 text-center ring-1 ring-white/10">
                                @if ($key === 's')
                                    <p class="led-digit text-3xl font-bold sm:text-4xl" x-text="s"
                                       x-effect="s; $el.classList.remove('roll'); void $el.offsetWidth; $el.classList.add('roll')">{{ $initial }}</p>
                                @else
                                    <p class="led-digit roll text-3xl font-bold sm:text-4xl" style="--d: {{ $delay }}ms" x-text="{{ $key }}">{{ $initial }}</p>
                                @endif
                                <p class="mt-1 text-[11px] text-ink-300">{{ $unit }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 space-y-4 border-t border-white/10 pt-5 text-sm">
                        <div>
                            <div class="flex items-center justify-between text-ink-200">
                                <span>کمیته‌ها</span>
                                <span class="font-semibold text-white">۶ از ۶ تشکیل شد</span>
                            </div>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10">
                                <div class="bar-fill h-full rounded-full bg-flag-500" style="--w: 100%; --d: 700ms" x-reveal></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-ink-200">
                                <span>وظایف انجام‌شده</span>
                                <span class="font-semibold text-white">۶۸٪</span>
                            </div>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10">
                                <div class="bar-fill h-full rounded-full bg-flag-500" style="--w: 68%; --d: 900ms" x-reveal></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="border-b border-ink-100 bg-white">
    <dl class="mx-auto grid max-w-6xl divide-y divide-ink-100 px-4 sm:grid-cols-3 sm:divide-x sm:divide-y-0 sm:px-6">
        @foreach ([[6, '', 'ماژول مدیریتی: رویداد، عضو، کمیته، وظیفه، بودجه و ریسک'], [2, '', 'نوع حساب: عادی برای یک رویداد، سازمانی برای چند رویداد و تیم'], [100, '٪', 'فارسی و راست‌به‌چپ، با تاریخ شمسی']] as [$value, $suffix, $text])
            <div class="px-2 py-8 sm:px-8">
                <dt class="flex items-baseline gap-1 text-4xl font-bold text-pool-700">
                    <span x-data="counter({{ $value }})" x-inview.once="run()" x-text="shown">{{ fa_num((string) $value) }}</span>
                    @if ($suffix)<span class="text-2xl">{{ $suffix }}</span>@endif
                </dt>
                <dd class="mt-2 text-sm leading-7 text-ink-500">{{ $text }}</dd>
            </div>
        @endforeach
    </dl>
</section>

<section id="features" class="bg-ink-50 py-20 sm:py-24" x-inview.mid="active = 'features'">
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <h2 class="max-w-2xl text-3xl font-bold leading-snug text-ink-900 sm:text-4xl">آنچه امروز کار می‌کند و آنچه در راه است</h2>
        <p class="mt-4 max-w-2xl leading-8 text-ink-500">
            سامانه مرحله‌به‌مرحله ساخته می‌شود. بخش‌های فعال را همین حالا استفاده کنید؛ بقیه به ترتیب اضافه می‌شوند.
        </p>

        <div class="mt-12 grid gap-12 lg:grid-cols-2 lg:gap-10">
            @foreach ([['الان می‌توانید', $live, true], ['به‌زودی', $soon, false]] as [$heading, $rows, $isLive])
                <div>
                    <div class="flex items-center gap-3 border-b-2 {{ $isLive ? 'border-pool-600' : 'border-ink-200' }} pb-3">
                        <h3 class="text-lg font-bold {{ $isLive ? 'text-pool-700' : 'text-ink-500' }}">{{ $heading }}</h3>
                        <span class="rounded-md px-2 py-0.5 text-xs font-semibold {{ $isLive ? 'bg-pool-100 text-pool-800' : 'bg-ink-100 text-ink-500' }}">{{ fa_num((string) count($rows)) }} بخش</span>
                    </div>
                    <ul class="divide-y divide-ink-100">
                        @foreach ($rows as $i => [$title, $desc])
                            <li class="reveal py-5" x-reveal style="--d: {{ $i * 70 }}ms">
                                <p class="font-bold {{ $isLive ? 'text-ink-900' : 'text-ink-600' }}">{{ $title }}</p>
                                <p class="mt-1.5 max-w-lg text-sm leading-7 {{ $isLive ? 'text-ink-500' : 'text-ink-400' }}">{{ $desc }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section id="how" class="border-y border-ink-100 bg-white py-20 sm:py-24"
         x-data="{ step: 0, names: ['پیش‌نویس', 'برنامه‌ریزی', 'آماده‌ی اجرا', 'در حال اجرا'], digits: ['۱', '۲', '۳', '۴'] }"
         x-inview.mid="active = 'how'">
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <h2 class="max-w-2xl text-3xl font-bold leading-snug text-ink-900 sm:text-4xl">هر رویداد این چهار مرحله را می‌گذراند</h2>

        <div class="mt-12 grid gap-10 lg:grid-cols-12">
            <div class="hidden lg:col-span-5 lg:block">
                <div class="sticky top-28 rounded-3xl border border-ink-100 bg-ink-50 p-8">
                    <div class="relative mx-auto h-48 w-48">
                        <svg viewBox="0 0 160 160" class="h-full w-full -rotate-90" aria-hidden="true">
                            <circle cx="80" cy="80" r="64" fill="none" stroke-width="10" class="stroke-ink-200"/>
                            <circle cx="80" cy="80" r="64" fill="none" stroke-width="10" stroke-linecap="round"
                                    class="stroke-pool-600 transition-all duration-700 ease-out"
                                    stroke-dasharray="402" :stroke-dashoffset="402 * (1 - (step + 1) / 4)"/>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-5xl font-bold text-pool-700" x-text="digits[step]">۱</span>
                            <span class="mt-1 text-sm font-semibold text-ink-600" x-text="names[step]">پیش‌نویس</span>
                        </div>
                    </div>
                    <div class="mt-8 flex gap-1.5" aria-hidden="true">
                        <template x-for="(n, i) in names" :key="i">
                            <span class="h-1.5 flex-1 rounded-full transition-colors duration-500" :class="i <= step ? 'bg-pool-600' : 'bg-ink-200'"></span>
                        </template>
                    </div>
                </div>
            </div>

            <ol class="lg:col-span-7">
                @foreach ($steps as $i => [$stage, $title, $desc])
                    <li class="flex gap-5 border-t border-ink-100 py-8 first:border-t-0 lg:min-h-[52vh] lg:items-start"
                        x-inview.mid="step = {{ $i }}">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold transition-colors duration-300"
                              :class="step === {{ $i }} ? 'bg-flag-500 text-ink-950' : 'bg-ink-100 text-ink-500'">{{ fa_num((string) ($i + 1)) }}</span>
                        <div>
                            <p class="text-sm font-semibold text-pool-700">{{ $stage }}</p>
                            <h3 class="mt-1 text-xl font-bold text-ink-900">{{ $title }}</h3>
                            <p class="mt-3 max-w-lg leading-8 text-ink-500">{{ $desc }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</section>

<section id="plans" class="bg-ink-50 py-20 sm:py-24" x-inview.mid="active = 'plans'">
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <h2 class="max-w-2xl text-3xl font-bold leading-snug text-ink-900 sm:text-4xl">حساب عادی برای یک رویداد، حساب سازمانی برای یک تیم</h2>
        <p class="mt-4 max-w-2xl leading-8 text-ink-500">سقف‌ها قابل تنظیم‌اند و بعداً می‌توانید حساب را ارتقا بدهید.</p>

        <div class="mt-12 grid gap-6 md:grid-cols-2">
            <div class="rounded-3xl border border-ink-200 bg-white p-8">
                <h3 class="text-xl font-bold text-ink-900">حساب عادی</h3>
                <p class="mt-1 text-sm text-ink-500">برای برگزارکننده‌ای که یک رویداد را اداره می‌کند.</p>
                <ul class="mt-6 space-y-3 text-sm text-ink-700">
                    <li class="flex gap-3"><span class="text-pool-600" aria-hidden="true">✓</span> یک رویداد فعال</li>
                    <li class="flex gap-3"><span class="text-pool-600" aria-hidden="true">✓</span> تا ۳ کمیته در هر رویداد</li>
                    <li class="flex gap-3"><span class="text-pool-600" aria-hidden="true">✓</span> دعوت عضو با موبایل</li>
                    <li class="flex gap-3 text-ink-400"><span aria-hidden="true">–</span> بدون سازمان و مسابقات</li>
                </ul>
                <a href="{{ route('auth.login') }}" class="btn-ghost mt-8 w-full">شروع با حساب عادی</a>
            </div>

            <div class="rounded-3xl bg-pool-700 p-8 text-white">
                <h3 class="text-xl font-bold">حساب سازمانی</h3>
                <p class="mt-1 text-sm text-pool-100">برای فدراسیون، باشگاه یا مجموعه‌ای با چند رویداد.</p>
                <ul class="mt-6 space-y-3 text-sm text-pool-50">
                    <li class="flex gap-3"><span class="text-flag-400" aria-hidden="true">✓</span> رویداد و کمیته‌ی نامحدود</li>
                    <li class="flex gap-3"><span class="text-flag-400" aria-hidden="true">✓</span> سازمان، دعوت عضو و نقش‌ها</li>
                    <li class="flex gap-3"><span class="text-flag-400" aria-hidden="true">✓</span> مسابقات و اسپانسرینگ (به‌زودی)</li>
                    <li class="flex gap-3"><span class="text-flag-400" aria-hidden="true">✓</span> پایگاه دانش و برنامه‌ریز هوشمند با سهمیه‌ی ماهانه (به‌زودی)</li>
                </ul>
                <a href="{{ route('auth.login') }}" class="btn-flag mt-8 w-full">شروع با حساب سازمانی</a>
            </div>
        </div>
    </div>
</section>

<section class="bg-pool-deep relative isolate overflow-hidden py-20 text-white sm:py-24">
    <x-lanes class="absolute inset-0 -z-10 h-full w-full text-white/10" />
    <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
        <h2 class="text-3xl font-bold leading-snug sm:text-4xl">اولین رویدادتان را همین حالا بسازید</h2>
        <p class="mx-auto mt-4 max-w-lg leading-8 text-pool-200">شماره‌ی موبایل را وارد کنید، کد پیامکی را بگیرید و شروع کنید. کمتر از یک دقیقه طول می‌کشد.</p>
        <a href="{{ route('auth.login') }}" class="btn-flag mt-8 px-8 py-3 text-base">ورود با موبایل</a>
    </div>
</section>
@endsection
