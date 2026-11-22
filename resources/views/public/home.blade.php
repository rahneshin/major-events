@extends('layouts.public')

@section('title', config('app.name') . ' — مدیریت رویداد ورزشی')
@section('meta_description', 'کمیته‌ها، اعضا، وظیفه‌ها و بودجه‌ی رویدادهای ورزشی را در یک سامانه‌ی فارسی و راست‌به‌چپ مدیریت کنید.')

@section('content')
@php
    // فقط برای تابلوی نمونه‌ی هیرو
    $demoStart = now()->addDays(12)->addHours(5)->addMinutes(20)->toIso8601String();

    $live = [
        ['رویداد و اهداف', 'مشخصات رویداد، نوع ورزش، مقیاس، تاریخ و محل را ثبت کنید و اهداف را بنویسید.', 'flag'],
        ['اعضا و نقش‌ها', 'همکاران را با شماره‌ی موبایل دعوت کنید و برای هرکدام نقش مشخص کنید؛ از مدیر رویداد تا ناظر.', 'whistle'],
        ['سازمان‌ها', 'برای چند رویداد یک سازمان بسازید و اعضای آن را با نقش‌های مدیریتی کنار هم نگه دارید.', 'trophy'],
        ['کمیته‌ها', 'کمیته‌های فنی، پزشکی، بازاریابی و داوطلبان را بسازید و رئیس و اعضای هرکدام را تعیین کنید.', 'stopwatch'],
    ];
    $soon = [
        ['وظایف و زمان‌بندی', 'کارها را بین کمیته‌ها تقسیم کنید، وابستگی بدهید و روی تقویم شمسی ببینید.', 'stopwatch'],
        ['بودجه و درآمد', 'خطوط بودجه به ریال، نسخه‌ی مصوب و مقایسه با هزینه‌ی واقعی.', 'medal'],
        ['ریسک', 'فهرست ریسک‌ها و ماتریس احتمال و اثر، با مسئول هر ریسک.', 'cross'],
        ['مسابقات', 'ثبت‌نام تیم‌ها، قرعه‌کشی قطعی، برنامه‌ی بازی‌ها و ثبت نتایج (حساب سازمانی).', 'trophy'],
        ['اسپانسرینگ و بازاریابی', 'بسته‌های حمایت، دارایی‌های تبلیغاتی و تقویم کمپین.', 'flag'],
        ['پایگاه دانش و برنامه‌ریز هوشمند', 'منابع خودتان را بارگذاری کنید و پیشنهادهای مستند بگیرید؛ هیچ پیشنهادی بدون تأیید شما وارد برنامه نمی‌شود.', 'eye'],
    ];
@endphp

{{-- ===== Stadium: صحنه‌ی تعاملی با اسکرول ===== --}}
<x-stadium :demo-start="$demoStart" />

{{-- ===== Facts ===== --}}
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

{{-- ===== Features: به شکل «برنامه‌ی روز» ===== --}}
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
                        @foreach ($rows as $i => [$title, $desc, $icon])
                            <li class="reveal flex gap-4 py-5" x-reveal style="--d: {{ $i * 70 }}ms">
                                <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $isLive ? 'bg-pool-50 text-pool-700' : 'bg-ink-100 text-ink-400' }}">
                                    <x-sport-icon :name="$icon" class="h-5 w-5" />
                                </span>
                                <div>
                                    <p class="font-bold {{ $isLive ? 'text-ink-900' : 'text-ink-600' }}">{{ $title }}</p>
                                    <p class="mt-1.5 max-w-lg text-sm leading-7 {{ $isLive ? 'text-ink-500' : 'text-ink-400' }}">{{ $desc }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===== Plans ===== --}}
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

{{-- ===== CTA ===== --}}
<section class="bg-pool-deep relative isolate overflow-hidden py-20 text-white sm:py-24">
    <x-lanes class="absolute inset-0 -z-10 h-full w-full text-white/10" />
    <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
        <h2 class="text-3xl font-bold leading-snug sm:text-4xl">اولین رویدادتان را همین حالا بسازید</h2>
        <p class="mx-auto mt-4 max-w-lg leading-8 text-pool-200">شماره‌ی موبایل را وارد کنید، کد پیامکی را بگیرید و شروع کنید. کمتر از یک دقیقه طول می‌کشد.</p>
        <a href="{{ route('auth.login') }}" class="btn-flag mt-8 px-8 py-3 text-base">ورود با موبایل</a>
    </div>
</section>
@endsection
