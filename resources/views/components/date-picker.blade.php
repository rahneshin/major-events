@props([
    'label' => null,
    'name' => null,
    'error' => null,
    'value' => null,
    'required' => false,
    'hint' => 'فرمت: ۱۴۰۵/۰۸/۲۹',
])

@php
    $displayValue = $value ? jalali($value) : old($name);
@endphp

<div class="space-y-1.5">
    @if ($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-ink-700">
            {{ $label }}
            @if ($required) <span class="text-danger-500">*</span> @endif
        </label>
    @endif

    <input
        type="text"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ $displayValue }}"
        placeholder="۱۴۰۵/۰۸/۲۹"
        dir="ltr"
        class="block w-full rounded-lg border border-ink-300 bg-white px-3 py-2 text-sm shadow-sm
               focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20
               {{ $error ? 'border-danger-500' : '' }}"
        @if($required) required @endif
        {{ $attributes }}
    >

    @if ($error)
        <p class="text-sm text-danger-500">{{ $error }}</p>
    @elseif ($hint)
        <p class="text-sm text-ink-500">{{ $hint }}</p>
    @endif
</div>
