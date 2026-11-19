@props([
    'type' => 'button',
    'variant' => 'primary', // primary, secondary, danger, ghost
    'size' => 'md',         // sm, md, lg
    'disabled' => false,
])

@php
$base = 'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';
$variants = [
    'primary'   => 'bg-primary-600 text-white hover:bg-primary-700 focus:ring-primary-500',
    'secondary' => 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 focus:ring-primary-500',
    'danger'    => 'bg-danger-500 text-white hover:bg-red-600 focus:ring-red-500',
    'ghost'     => 'bg-transparent text-gray-700 hover:bg-gray-100 focus:ring-primary-500',
];
$sizes = [
    'sm' => 'px-3 py-1.5 text-sm',
    'md' => 'px-4 py-2 text-sm',
    'lg' => 'px-5 py-2.5 text-base',
];
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => $base.' '.$variants[$variant].' '.$sizes[$size]]) }}
    @disabled($disabled)
>
    {{ $slot }}
</button>
