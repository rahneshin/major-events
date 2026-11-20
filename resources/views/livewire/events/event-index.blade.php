<div x-data="{ mounted: false }" x-init="setTimeout(() => mounted = true, 40)">
    @section('page_title', __('event.index_title'))

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-900">{{ __('event.index_title') }}</h2>
            <p class="mt-1 text-sm text-slate-500">
                @if ($limit === null)
                    {{ __('event.unlimited') }}
                @else
                    {{ __('event.usage', ['used' => $used, 'limit' => $limit]) }}
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex rounded-xl border border-slate-200 bg-white p-1 text-xs font-medium">
                <button type="button" wire:click="$set('filter', 'active')"
                    class="rounded-lg px-3 py-1.5 transition {{ $filter === 'active' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-50' }}">
                    {{ __('event.filter_active') }}
                </button>
                <button type="button" wire:click="$set('filter', 'all')"
                    class="rounded-lg px-3 py-1.5 transition {{ $filter === 'all' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-50' }}">
                    {{ __('event.filter_all') }}
                </button>
                <button type="button" wire:click="$set('filter', 'archived')"
                    class="rounded-lg px-3 py-1.5 transition {{ $filter === 'archived' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-50' }}">
                    {{ __('event.filter_archived') }}
                </button>
            </div>
            @if ($canCreate)
                <a href="{{ route('events.create') }}" class="btn-primary text-xs sm:text-sm">
                    {{ __('event.create') }}
                </a>
            @else
                <span class="rounded-xl bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700 ring-1 ring-amber-200">
                    {{ __('event.limit_reached') }}
                </span>
            @endif
        </div>
    </div>

    @if ($events->isEmpty())
        <div class="glass rounded-3xl px-6 py-16 text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <h3 class="text-base font-bold text-slate-900">{{ __('event.empty_title') }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ __('event.empty_desc') }}</p>
            @if ($canCreate)
                <a href="{{ route('events.create') }}" class="btn-primary mt-6">{{ __('event.create') }}</a>
            @endif
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($events as $i => $event)
                <a href="{{ route('events.show', $event) }}"
                   class="glass card-hover group block rounded-2xl p-5"
                   style="transition-delay: {{ $i * 40 }}ms"
                   :class="mounted ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate text-base font-bold text-slate-900 group-hover:text-indigo-700">{{ $event->title }}</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ $event->event_type?->label() }}
                                @if ($event->city) · {{ $event->city }} @endif
                            </p>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold
                            {{ $event->status?->isActive() ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                            {{ $event->status?->label() }}
                        </span>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-3 text-[11px] text-slate-400">
                        @if ($event->start_date)
                            <span>{{ fa_num(jalali($event->start_date, 'Y/m/d')) }}</span>
                        @endif
                        @if ($event->organization)
                            <span>{{ $event->organization->name }}</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $events->links() }}
        </div>
    @endif
</div>
