@props([
    'title' => __('ui.empty_title'),
    'description' => __('ui.empty_description'),
])

<div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
    <div class="mb-3 text-4xl text-gray-300">📭</div>
    <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
    <p class="mt-1 max-w-sm text-sm text-gray-500">{{ $description }}</p>
    @if (isset($action))
        <div class="mt-5">
            {{ $action }}
        </div>
    @endif
</div>
