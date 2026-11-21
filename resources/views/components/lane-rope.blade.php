{{--
    نوار مراحل رویداد به شکل طناب مسیر با ۴ شناور:
    پیش‌نویس ← برنامه‌ریزی ← آماده ← در حال اجرا
    $stage: ایندکس مرحله‌ی فعلی (۰ تا ۳) یا false برای رویدادهای پایان‌یافته/آرشیوشده
--}}
@props(['stage' => false, 'label' => null])

@php $total = 4; @endphp

<div class="flex items-center" @if ($label) role="img" aria-label="{{ $label }}" @endif>
    @for ($i = 0; $i < $total; $i++)
        @php
            $state = $stage === false ? 'off' : ($i < $stage ? 'done' : ($i === $stage ? 'now' : 'todo'));
        @endphp
        <span @class([
            'shrink-0 rounded-full transition-colors',
            'h-3 w-3 bg-pool-600' => $state === 'done',
            'h-4 w-4 bg-flag-500 ring-4 ring-flag-500/25' => $state === 'now',
            'h-3 w-3 bg-ink-200' => $state === 'todo',
            'h-3 w-3 bg-ink-300' => $state === 'off',
        ])></span>
        @if ($i < $total - 1)
            <span @class([
                'h-0.5 flex-1 rounded-full',
                'bg-pool-600' => $stage !== false && $i < $stage,
                'bg-ink-200' => $stage === false || $i >= $stage,
            ])></span>
        @endif
    @endfor
</div>
