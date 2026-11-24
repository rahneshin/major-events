@props(['paginator'])

@if ($paginator->hasPages())
    <nav class="flex items-center justify-between border-t border-ink-200 pt-4">
        <p class="text-sm text-ink-600">
            {{ __('ui.pagination.showing', [
                'from' => fa_num($paginator->firstItem()),
                'to' => fa_num($paginator->lastItem()),
                'total' => fa_num($paginator->total()),
            ]) }}
        </p>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="rounded-lg border px-3 py-1.5 text-sm text-ink-400">{{ __('ui.pagination.previous') }}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="rounded-lg border px-3 py-1.5 text-sm hover:bg-ink-50">
                    {{ __('ui.pagination.previous') }}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="rounded-lg border px-3 py-1.5 text-sm hover:bg-ink-50">
                    {{ __('ui.pagination.next') }}
                </a>
            @else
                <span class="rounded-lg border px-3 py-1.5 text-sm text-ink-400">{{ __('ui.pagination.next') }}</span>
            @endif
        </div>
    </nav>
@endif
