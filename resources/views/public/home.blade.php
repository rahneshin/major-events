@extends('layouts.public')

@section('title', config('app.name') . ' — مدیریت هوشمند رویداد ورزشی')
@section('meta_description', 'برنامه‌ریزی، کمیته، وظیفه، بودجه و پایگاه دانش رویدادهای ورزشی با هوش مصنوعی')

@section('content')
<div class="relative overflow-hidden bg-mesh pt-24 pb-20">
    {{-- floating orbs --}}
    <div class="pointer-events-none absolute -top-32 start-1/4 h-96 w-96 rounded-full bg-indigo-400/20 blur-3xl animate-float-slow"></div>
    <div class="pointer-events-none absolute top-40 -end-20 h-80 w-80 rounded-full bg-fuchsia-400/15 blur-3xl animate-float-slow" style="animation-delay: -4s"></div>

    {{-- Hero --}}
    <section class="relative mx-auto max-w-6xl px-4 sm:px-6">
        <div class="mx-auto max-w-3xl text-center animate-rise">
            <span class="mb-5 inline-flex items-center gap-2 rounded-full border border-indigo-200/80 bg-white/80 px-3.5 py-1.5 text-xs font-semibold text-indigo-700 shadow-sm backdrop-blur">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-indigo-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-indigo-500"></span>
                </span>
                پلتفرم هوشمند رویداد ورزشی
            </span>

            <h1 class="text-4xl font-bold leading-tight tracking-tight text-slate-900 sm:text-5xl lg:text-6xl">
                از ایده تا اجرا
                <span class="gradient-text block mt-1">رویداد ورزشی خود را مدیریت کنید</span>
            </h1>

            <p class="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-slate-600 sm:text-lg">
                برنامه‌ریزی، کمیته‌ها، وظایف، بودجه، ریسک و پایگاه دانش — همه در یک سامانه فارسی و راست‌به‌چپ.
                پیشنهادهای هوشمند فقط پس از تأیید شما اجرا می‌شوند.
            </p>

            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a href="{{ route('auth.login') }}" class="btn-primary px-7 py-3 text-base">
                    شروع رایگان با موبایل
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <a href="#features" class="btn-ghost px-7 py-3 text-base">مشاهده امکانات</a>
            </div>

            <p class="mt-4 text-xs text-slate-400">ورود فقط با شماره موبایل · بدون رمز عبور</p>
        </div>

        {{-- Hero visual card --}}
        <div class="relative mx-auto mt-14 max-w-4xl animate-scale-in stagger-3">
            <div class="glass overflow-hidden rounded-3xl p-1.5">
                <div class="rounded-2xl bg-gradient-to-br from-slate-900 via-indigo-950 to-violet-950 p-6 sm:p-8">
                    <div class="flex items-center gap-2 mb-6">
                        <span class="h-3 w-3 rounded-full bg-rose-400"></span>
                        <span class="h-3 w-3 rounded-full bg-amber-400"></span>
                        <span class="h-3 w-3 rounded-full bg-emerald-400"></span>
                        <span class="ms-3 text-xs text-slate-400">داشبورد رویداد</span>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-xl bg-white/5 p-4 ring-1 ring-white/10">
                            <p class="text-xs text-slate-400">پیشرفت کلی</p>
                            <p class="mt-1 text-2xl font-bold text-white">۶۸٪</p>
                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/10">
                                <div class="h-full w-[68%] rounded-full bg-gradient-to-l from-indigo-400 to-violet-400"></div>
                            </div>
                        </div>
                        <div class="rounded-xl bg-white/5 p-4 ring-1 ring-white/10">
                            <p class="text-xs text-slate-400">وظایف باز</p>
                            <p class="mt-1 text-2xl font-bold text-white">۲۴</p>
                            <p class="mt-2 text-xs text-emerald-400">۸ تکمیل این هفته</p>
                        </div>
                        <div class="rounded-xl bg-white/5 p-4 ring-1 ring-white/10">
                            <p class="text-xs text-slate-400">بودجه مصرفی</p>
                            <p class="mt-1 text-2xl font-bold text-white">۴۲٪</p>
                            <p class="mt-2 text-xs text-slate-400">در محدوده مصوب</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

{{-- Features --}}
<section id="features" class="py-20">
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">همه چیز برای یک رویداد حرفه‌ای</h2>
            <p class="mt-3 text-slate-600">از مفهوم‌سازی تا ارزیابی پسارویداد، در یک فضای یکپارچه.</p>
        </div>

        <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @php
            $features = [
                ['title' => 'سازمان و اعضا', 'desc' => 'سازمان مجازی، دعوت با موبایل، نقش‌های owner تا viewer', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5'],
                ['title' => 'رویداد و اهداف', 'desc' => 'پروفایل رویداد، اهداف SMART، وضعیت و آرشیو', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                ['title' => 'کمیته و وظیفه', 'desc' => 'کمیته‌ها، وابستگی وظایف، کانبان و تقویم شمسی', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                ['title' => 'بودجه و ریسک', 'desc' => 'خطوط بودجه ریالی، نسخه مصوب، ماتریس ریسک', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['title' => 'مسابقات', 'desc' => 'ثبت‌نام، قرعه قطعی، برنامه و نتایج (حساب سازمانی)', 'icon' => 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z'],
                ['title' => 'پایگاه دانش + AI', 'desc' => 'آپلود PDF/PPT، RAG و برنامه‌ریز هوشمند با تأیید انسان', 'icon' => 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z'],
            ];
            @endphp

            @foreach ($features as $i => $f)
            <div class="group glass card-hover rounded-2xl p-6 animate-rise stagger-{{ min($i+1, 6) }}">
                <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 transition group-hover:scale-110 group-hover:bg-indigo-100">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $f['icon'] }}"/></svg>
                </div>
                <h3 class="text-base font-bold text-slate-900">{{ $f['title'] }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $f['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- How it works --}}
<section id="how" class="border-y border-slate-100 bg-white py-20">
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold text-slate-900">سه قدم تا مدیریت رویداد</h2>
        </div>
        <div class="mt-12 grid gap-8 sm:grid-cols-3">
            @foreach ([
                ['n' => '۱', 't' => 'ورود با موبایل', 'd' => 'شماره بدهید، کد پیامکی بگیرید. بدون رمز عبور.'],
                ['n' => '۲', 't' => 'نوع حساب را انتخاب کنید', 'd' => 'عادی برای یک رویداد؛ سازمانی برای چند رویداد و تیم.'],
                ['n' => '۳', 't' => 'رویداد بسازید و اجرا کنید', 'd' => 'کمیته، وظیفه، بودجه و در آینده پیشنهاد AI با تأیید شما.'],
            ] as $step)
            <div class="relative text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-600 text-xl font-bold text-white shadow-lg shadow-indigo-500/30">
                    {{ $step['n'] }}
                </div>
                <h3 class="mt-4 text-lg font-bold text-slate-900">{{ $step['t'] }}</h3>
                <p class="mt-2 text-sm text-slate-600">{{ $step['d'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Plans --}}
<section id="plans" class="py-20">
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold text-slate-900">دو نوع حساب، شفاف و قابل ارتقا</h2>
            <p class="mt-3 text-slate-600">محدودیت‌ها از config قابل تنظیم‌اند و بعداً با پرداخت ارتقا می‌یابند.</p>
        </div>

        <div class="mx-auto mt-12 grid max-w-4xl gap-6 sm:grid-cols-2">
            <div class="glass card-hover rounded-3xl p-7">
                <p class="text-sm font-semibold text-slate-500">کاربر عادی</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">Individual</p>
                <ul class="mt-5 space-y-2.5 text-sm text-slate-600">
                    <li class="flex gap-2"><span class="text-emerald-500">✓</span> ۱ رویداد فعال</li>
                    <li class="flex gap-2"><span class="text-emerald-500">✓</span> تا ۳ کمیته</li>
                    <li class="flex gap-2"><span class="text-emerald-500">✓</span> وظایف و بودجه ساده</li>
                    <li class="flex gap-2"><span class="text-slate-300">—</span> سازمان و مسابقات ندارد</li>
                </ul>
                <a href="{{ route('auth.login') }}" class="btn-ghost mt-6 w-full">شروع رایگان</a>
            </div>

            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 p-7 text-white shadow-2xl shadow-indigo-500/30">
                <div class="absolute -end-8 -top-8 h-32 w-32 rounded-full bg-white/10 blur-2xl"></div>
                <p class="text-sm font-semibold text-indigo-100">کاربر سازمانی</p>
                <p class="mt-1 text-2xl font-bold">Organizational</p>
                <ul class="mt-5 space-y-2.5 text-sm text-indigo-50">
                    <li class="flex gap-2"><span>✓</span> رویداد نامحدود</li>
                    <li class="flex gap-2"><span>✓</span> سازمان و دعوت عضو</li>
                    <li class="flex gap-2"><span>✓</span> مسابقات، اسپانسر، خروجی کامل</li>
                    <li class="flex gap-2"><span>✓</span> پایگاه دانش و AI (سهمیه ماهانه)</li>
                </ul>
                <a href="{{ route('auth.login') }}" class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-white px-5 py-2.5 text-sm font-semibold text-indigo-700 shadow-lg transition hover:bg-indigo-50">شروع سازمانی</a>
            </div>
        </div>
    </div>
</section>

{{-- CTA --}}
<section class="pb-24">
    <div class="mx-auto max-w-4xl px-4 sm:px-6">
        <div class="relative overflow-hidden rounded-3xl bg-slate-900 px-8 py-14 text-center shadow-2xl">
            <div class="absolute inset-0 bg-mesh opacity-40"></div>
            <div class="relative">
                <h2 class="text-2xl font-bold text-white sm:text-3xl">آماده ساخت اولین رویداد هستید؟</h2>
                <p class="mx-auto mt-3 max-w-lg text-slate-300">فقط با شماره موبایل وارد شوید و در کمتر از یک دقیقه شروع کنید.</p>
                <a href="{{ route('auth.login') }}" class="btn-primary mt-7 px-8 py-3 text-base">ورود با موبایل</a>
            </div>
        </div>
    </div>
</section>
@endsection
