@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'error' => null,
    'hint' => null,
    'required' => false,
])

<div class="space-y-1.5">
    @if ($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">
            {{ $label }}
            @if ($required)
                <span class="text-danger-500">*</span>
            @endif
        </label>
    @endif

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        {{ $attributes->merge([
            'class' => 'block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm
                        placeholder:text-gray-400
                        focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20
                        disabled:bg-gray-100 disabled:cursor-not-allowed
                        '.($error ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500/20' : '')
        ]) }}
        @if($required) required @endif
    >

    @if ($error)
        <p class="text-sm text-danger-500">{{ $error }}</p>
    @elseif ($hint)
        <p class="text-sm text-gray-500">{{ $hint }}</p>
    @endif
</div>
