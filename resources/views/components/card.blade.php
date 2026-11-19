@props(['title' => null, 'padding' => true])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white shadow-sm']) }}>
    @if ($title || isset($header))
        <div class="border-b border-gray-100 px-4 py-3 sm:px-5">
            @if (isset($header))
                {{ $header }}
            @else
                <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
            @endif
        </div>
    @endif

    <div class="{{ $padding ? 'p-4 sm:p-5' : '' }}">
        {{ $slot }}
    </div>

    @if (isset($footer))
        <div class="border-t border-gray-100 px-4 py-3 sm:px-5">
            {{ $footer }}
        </div>
    @endif
</div>
