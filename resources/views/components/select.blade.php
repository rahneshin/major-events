@props([
    'label' => null,
    'name' => null,
    'error' => null,
    'hint' => null,
    'required' => false,
    'placeholder' => null,
])

<div class="space-y-1.5">
    @if ($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-ink-700">
            {{ $label }}
            @if ($required) <span class="text-danger-500">*</span> @endif
        </label>
    @endif

    <select
        name="{{ $name }}"
        id="{{ $name }}"
        {{ $attributes->merge([
            'class' => 'block w-full rounded-lg border border-ink-300 bg-white px-3 py-2 text-sm shadow-sm
                        focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20
                        '.($error ? 'border-danger-500' : '')
        ]) }}
        @if($required) required @endif
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        {{ $slot }}
    </select>

    @if ($error)
        <p class="text-sm text-danger-500">{{ $error }}</p>
    @elseif ($hint)
        <p class="text-sm text-ink-500">{{ $hint }}</p>
    @endif
</div>
