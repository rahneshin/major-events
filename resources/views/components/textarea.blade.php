@props([
    'label' => null,
    'name' => null,
    'error' => null,
    'hint' => null,
    'required' => false,
    'rows' => 4,
])

<div class="space-y-1.5">
    @if ($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">
            {{ $label }}
            @if ($required) <span class="text-danger-500">*</span> @endif
        </label>
    @endif

    <textarea
        name="{{ $name }}"
        id="{{ $name }}"
        rows="{{ $rows }}"
        {{ $attributes->merge([
            'class' => 'block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm
                        placeholder:text-gray-400
                        focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20
                        '.($error ? 'border-danger-500' : '')
        ]) }}
        @if($required) required @endif
    >{{ $slot }}</textarea>

    @if ($error)
        <p class="text-sm text-danger-500">{{ $error }}</p>
    @elseif ($hint)
        <p class="text-sm text-gray-500">{{ $hint }}</p>
    @endif
</div>
