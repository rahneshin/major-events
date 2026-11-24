{{-- «ثبت شده در تاریخ نهم تیر ۱۴۰۵ توسط علی رضایی» — بدون ساعت --}}
@props(['at' => null, 'by' => null, 'verb' => 'ثبت شده'])

@if ($at)
    <p {{ $attributes->merge(['class' => 'text-[11px] leading-5 text-ink-400']) }}>
        {{ $verb }} در تاریخ {{ jalali_long($at) }}
        @if ($by !== null) توسط <span class="font-semibold text-ink-500">{{ person_name($by) }}</span>@endif
    </p>
@endif
