{{--
    نمادهای ورزشی اصلی (طراحی اختصاصی پروژه، سبک خطی). به‌عمد از نشان‌های ثبت‌شده‌ی
    نهادهای بین‌المللی (حلقه‌های المپیک، فیفا و ...) استفاده نشده است.
    لوگوی رسمی فدراسیون/سازمان خودتان را به‌صورت تصویر جدا در public/images/brands بگذارید.
    استفاده: <x-sport-icon name="football" class="h-6 w-6" />
--}}
@props(['name'])

<svg {{ $attributes->merge(['class' => 'h-6 w-6']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('football')
            <circle cx="12" cy="12" r="9"/>
            <path d="M12 8.8l3.04 2.21-1.16 3.58h-3.76l-1.16-3.58z"/>
            <path d="M12 8.8V3M15.04 11.01l5.52-1.79M13.88 14.59l3.41 4.69M10.12 14.59l-3.41 4.69M8.96 11.01L3.44 9.22"/>
            @break
        @case('volleyball')
            <circle cx="12" cy="12" r="9"/>
            <path d="M12 3c-1 4 0 8 5.5 11M4.5 8c4.5-.5 8 1 10 5M5.3 17.5c1.5-4 4.5-6 8.2-6.2"/>
            @break
        @case('basketball')
            <circle cx="12" cy="12" r="9"/>
            <path d="M12 3v18M3 12h18M5.6 5.6c3 3 3 9.8 0 12.8M18.4 5.6c-3 3-3 9.8 0 12.8"/>
            @break
        @case('swimming')
            <circle cx="17" cy="6" r="1.8"/>
            <path d="M5.5 11.5l5-2.2 3.2 2.2M3 16c1.5-1 3-1 4.5 0s3 1 4.5 0 3-1 4.5 0 3 1 4.5 0M3 20c1.5-1 3-1 4.5 0s3 1 4.5 0 3-1 4.5 0 3 1 4.5 0"/>
            @break
        @case('athletics')
            <rect x="3" y="6" width="18" height="12" rx="6"/>
            <rect x="6.5" y="9.5" width="11" height="5" rx="2.5"/>
            @break
        @case('whistle')
            <circle cx="8.5" cy="14.5" r="4.5"/>
            <path d="M12.5 11.5L21 10v4h-8M8.5 12.5v2h2"/>
            @break
        @case('stopwatch')
            <circle cx="12" cy="13.5" r="7.5"/>
            <path d="M12 13.5V9.5M9.5 3h5M12 3v3M18.6 6.6l1.4-1.4"/>
            @break
        @case('medal')
            <circle cx="12" cy="15" r="5"/>
            <path d="M8 3l4 7 4-7"/>
            @break
        @case('trophy')
            <path d="M8 4h8v6a4 4 0 01-8 0V4zM8 6H5v2a3 3 0 003 3M16 6h3v2a3 3 0 01-3 3M12 14v4M8.5 21h7M10 18h4"/>
            @break
        @case('cross')
            <rect x="3.5" y="3.5" width="17" height="17" rx="4"/>
            <path d="M12 8v8M8 12h8"/>
            @break
        @case('flag')
            <path d="M5 21V4M5 4h11l-2 3.5 2 3.5H5"/>
            @break
        @case('eye')
            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/>
            <circle cx="12" cy="12" r="3"/>
            @break
    @endswitch
</svg>
