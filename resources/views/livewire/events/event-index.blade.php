<div>
    @section('page_title', __('event.index_title'))

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-ink-900">{{ __('event.index_title') }}</h2>
            <p class="mt-1 text-sm text-ink-500">
                @if ($limit === null)
                    {{ __('event.unlimited') }}
                @else
                    {{ __('event.usage', ['used' => $used, 'limit' => $limit]) }}
                @endif
            </p>
            @if ($limit !== null && $limit > 0)
                <div class="mt-3 h-1.5 w-56 overflow-hidden rounded-full bg-ink-100" role="img" aria-label="{{ $used }} از {{ $limit }}">
                    <div class="bar-fill h-full rounded-full {{ $used >= $limit ? 'bg-flag-500' : 'bg-pool-600' }}" style="--w: {{ min(100, (int) round($used / $limit * 100)) }}%" x-reveal></div>
                </div>
            @endif
        </div>

        @if ($canCreate)
            <a href="{{ route('events.create') }}" wire:navigate class="btn-primary self-start sm:self-auto">{{ __('event.create') }}</a>
        @else
            <p class="self-start rounded-xl bg-flag-300/40 px-3 py-2 text-xs font-semibold text-ink-800 sm:self-auto">{{ __('event.limit_reached') }}</p>
        @endif
    </div>

    {{-- فیلتر: دکمه‌های قطعه‌ای --}}
    <div class="mb-4 inline-flex rounded-xl border border-ink-200 bg-white p-1 text-sm font-medium" role="tablist">
        @foreach (['active' => __('event.filter_active'), 'all' => __('event.filter_all'), 'archived' => __('event.filter_archived')] as $key => $label)
            <button type="button" role="tab" aria-selected="{{ $filter === $key ? 'true' : 'false' }}"
                    wire:click="$set('filter', '{{ $key }}')" wire:key="filter-{{ $key }}"
                    @class([
                        'rounded-lg px-4 py-1.5 transition-colors',
                        'bg-pool-600 text-white' => $filter === $key,
                        'text-ink-600 hover:bg-ink-50' => $filter !== $key,
                    ])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div wire:loading.delay wire:target="filter, gotoPage, nextPage, previousPage" class="mb-4 w-full">
        <div class="divide-y divide-ink-100 overflow-hidden rounded-2xl border border-ink-100 bg-white" aria-hidden="true">
            @foreach (range(1, 3) as $i)
                <div class="grid gap-4 px-5 py-4 sm:grid-cols-[1fr_17rem] sm:items-center">
                    <div class="space-y-2.5">
                        <div class="skeleton h-4 w-2/5 rounded"></div>
                        <div class="skeleton h-3 w-3/5 rounded"></div>
                    </div>
                    <div class="skeleton h-3 w-full rounded-full"></div>
                </div>
            @endforeach
        </div>
    </div>

    <div wire:loading.remove.delay wire:target="filter, gotoPage, nextPage, previousPage">
        @if ($events->isEmpty())
            <div class="rounded-2xl border border-dashed border-ink-200 bg-white px-6 py-16 text-center">
                <h3 class="text-base font-bold text-ink-900">{{ __('event.empty_title') }}</h3>
                <p class="mx-auto mt-2 max-w-sm text-sm leading-7 text-ink-500">{{ __('event.empty_desc') }}</p>
                @if ($canCreate)
                    <a href="{{ route('events.create') }}" wire:navigate class="btn-primary mt-6">{{ __('event.create') }}</a>
                @endif
            </div>
        @else
            <div class="divide-y divide-ink-100 overflow-hidden rounded-2xl border border-ink-100 bg-white">
                @foreach ($events as $event)
                    <x-event-row :event="$event" wire:key="event-{{ $event->id }}" />
                @endforeach
            </div>

            <div class="mt-6">
                {{ $events->links() }}
            </div>
        @endif
    </div>
</div>
