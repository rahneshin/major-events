@props(['color' => 'gray']) {{-- gray, primary, success, warning, danger --}}

@php
$colors = [
    'gray'    => 'bg-ink-100 text-ink-700',
    'primary' => 'bg-primary-100 text-primary-700',
    'success' => 'bg-green-100 text-green-700',
    'warning' => 'bg-flag-100 text-flag-700',
    'danger'  => 'bg-red-100 text-red-700',
];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '.$colors[$color]]) }}>
    {{ $slot }}
</span>
