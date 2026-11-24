{{-- آخرین اقدام روی یک رکورد: «وضعیت … تغییر کرد — در تاریخ … توسط …» --}}
@props(['subject'])

@php($log = \App\Support\ActivityDescriber::for($subject, 1)->first())

@if ($log)
    <p {{ $attributes->merge(['class' => 'text-[11px] leading-5 text-ink-400']) }}>
        {{ \App\Support\ActivityDescriber::describe($log) }}
        — {{ __('activity.on') }} {{ jalali_long($log->created_at) }}
        {{ __('activity.by') }} <span class="font-semibold text-ink-500">{{ $log->user ? person_name($log->user) : __('activity.unknown_user') }}</span>
    </p>
@endif
