@props(['name', 'title' => null, 'maxWidth' => 'md'])

@php
$widths = [
    'sm' => 'max-w-sm',
    'md' => 'max-w-md',
    'lg' => 'max-w-lg',
    'xl' => 'max-w-xl',
];
@endphp

<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
    x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    class="relative z-50"
    style="display: none;"
>
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-black/50"></div>

    <div class="fixed inset-0 flex items-end justify-center p-4 sm:items-center">
        <div
            x-show="open"
            x-transition
            class="w-full {{ $widths[$maxWidth] }} rounded-xl bg-white shadow-xl"
            @click.outside="open = false"
        >
            @if ($title)
                <div class="flex items-center justify-between border-b px-5 py-4">
                    <h3 class="text-base font-semibold">{{ $title }}</h3>
                    <button type="button" @click="open = false" class="rounded p-1 hover:bg-gray-100">✕</button>
                </div>
            @endif

            <div class="px-5 py-4">
                {{ $slot }}
            </div>

            @if (isset($footer))
                <div class="flex justify-end gap-2 border-t px-5 py-3">
                    {{ $footer }}
                </div>
            @endif
        </div>
    </div>
</div>
