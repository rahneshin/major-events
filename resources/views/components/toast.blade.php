@props(['type' => 'info']) {{-- success, error, warning, info --}}

@php
$styles = [
    'success' => 'bg-green-50 text-green-800 border-green-200',
    'error'   => 'bg-red-50 text-red-800 border-red-200',
    'warning' => 'bg-flag-50 text-flag-800 border-flag-200',
    'info'    => 'bg-blue-50 text-blue-800 border-blue-200',
];
@endphp

<div
    x-data="{ show: true }"
    x-show="show"
    x-init="setTimeout(() => show = false, 5000)"
    x-transition
    {{ $attributes->merge(['class' => 'rounded-lg border px-4 py-3 text-sm '.$styles[$type]]) }}
    role="alert"
>
    <div class="flex items-start justify-between gap-3">
        <div>{{ $slot }}</div>
        <button type="button" @click="show = false" class="shrink-0 opacity-60 hover:opacity-100">✕</button>
    </div>
</div>
