<div>
    @section('page_title', $event->title)

    <div class="mb-6 overflow-hidden rounded-3xl bg-gradient-to-l from-indigo-600 via-violet-600 to-fuchsia-600 p-6 text-white shadow-xl shadow-indigo-500/20 sm:p-7">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-semibold backdrop-blur">
                        {{ $event->status?->label() }}
                    </span>
                    <span class="text-xs text-indigo-100">{{ $event->event_type?->label() }}</span>
                </div>
                <h2 class="text-2xl font-bold">{{ $event->title }}</h2>
                <p class="mt-1 text-sm text-indigo-100">
                    @if ($event->city) {{ $event->city }} @endif
                    @if ($event->start_date)
                        · {{ fa_num(jalali($event->start_date, 'Y/m/d')) }}
                        @if ($event->end_date) – {{ fa_num(jalali($event->end_date, 'Y/m/d')) }} @endif
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($canUpdate)
                    <a href="{{ route('events.edit', $event) }}" class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-xs font-bold text-indigo-700 shadow-md transition hover:bg-indigo-50">
                        {{ __('ui.edit') }}
                    </a>
                @endif
                <a href="{{ route('events.index') }}" class="inline-flex items-center rounded-xl border border-white/30 bg-white/10 px-4 py-2 text-xs font-semibold text-white backdrop-blur transition hover:bg-white/20">
                    {{ __('ui.back') }}
                </a>
            </div>
        </div>
    </div>

    <div class="mb-5 flex gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 text-xs font-medium shadow-sm">
        @foreach ([
            'overview' => __('event.tab_overview'),
            'members' => __('event.tab_members'),
            'committees' => __('event.tab_committees'),
            'tasks' => __('event.tab_tasks'),
            'budget' => __('event.tab_budget'),
            'risk' => __('event.tab_risk'),
        ] as $key => $label)
            <button type="button" wire:click="setTab('{{ $key }}')"
                class="shrink-0 rounded-lg px-3.5 py-2.5 transition font-semibold
                {{ $tab === $key ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/25' : 'text-slate-600 hover:bg-slate-50' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($tab === 'overview')
        <div class="grid gap-5 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <div class="glass rounded-2xl p-5">
                    <h3 class="mb-3 text-sm font-bold text-slate-900">{{ __('event.overview') }}</h3>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-slate-400">{{ __('event.fields.scale') }}</dt>
                            <dd class="font-medium">{{ $event->scale?->label() ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400">{{ __('event.fields.sport_type') }}</dt>
                            <dd class="font-medium">{{ $event->sport_type ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400">{{ __('event.fields.venue') }}</dt>
                            <dd class="font-medium">{{ $event->venue_name ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400">{{ __('event.fields.main_goal_type') }}</dt>
                            <dd class="font-medium">{{ $event->main_goal_type?->label() ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400">{{ __('event.fields.participants') }}</dt>
                            <dd class="font-medium tabular-nums">{{ $event->expected_participants ? fa_num(number_format($event->expected_participants)) : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400">{{ __('event.fields.budget') }}</dt>
                            <dd class="font-medium tabular-nums">{{ $event->budget_estimate ? fa_num(number_format($event->budget_estimate)).' ریال' : '—' }}</dd>
                        </div>
                    </dl>
                    @if ($event->description)
                        <p class="mt-4 border-t border-slate-100 pt-4 text-sm leading-relaxed text-slate-600">{{ $event->description }}</p>
                    @endif
                </div>

                @if ($event->goals->isNotEmpty())
                    <div class="glass rounded-2xl p-5">
                        <h3 class="mb-3 text-sm font-bold text-slate-900">{{ __('event.goals') }}</h3>
                        <ul class="space-y-2">
                            @foreach ($event->goals as $goal)
                                <li class="flex items-start gap-2 rounded-xl bg-slate-50 px-3 py-2.5 text-sm">
                                    <span class="mt-0.5 rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700">
                                        {{ $goal->kind->label() }}
                                    </span>
                                    <div>
                                        <p class="font-medium text-slate-800">{{ $goal->title }}</p>
                                        @if ($goal->target_value)
                                            <p class="text-xs text-slate-400">{{ $goal->target_value }} {{ $goal->unit }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="space-y-4">
                @if ($canUpdate && count($transitions))
                    <div class="glass rounded-2xl p-5">
                        <h3 class="mb-3 text-sm font-bold text-slate-900">{{ __('event.change_status') }}</h3>
                        <div class="flex flex-col gap-2">
                            @foreach ($transitions as $t)
                                <button type="button" wire:click="transitionTo('{{ $t->value }}')"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-start text-sm font-semibold text-slate-700 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700">
                                    {{ $t->label() }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($canDelete)
                    <div class="glass rounded-2xl p-5">
                        <h3 class="mb-2 text-sm font-bold text-rose-700">{{ __('event.danger_zone') }}</h3>
                        <button type="button" wire:click="deleteEvent" wire:confirm="{{ __('event.confirm_delete') }}"
                            class="w-full rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-bold text-rose-700 shadow-sm transition hover:bg-rose-100">
                            {{ __('event.delete') }}
                        </button>
                    </div>
                @endif
            </div>
        </div>

    @elseif ($tab === 'members')
        <livewire:events.event-human-resources :event="$event" :key="'hr-'.$event->id" />

    @elseif ($tab === 'tasks')
        <livewire:events.event-tasks :event="$event" :key="'tasks-'.$event->id" />

    @elseif ($tab === 'budget')
        <livewire:events.event-budget :event="$event" :key="'budget-'.$event->id" />

    @elseif ($tab === 'committees')
        <livewire:events.event-committees :event="$event" :key="'committees-'.$event->id" />

    @else
        <div class="glass rounded-2xl px-6 py-14 text-center">
            <p class="text-sm font-medium text-slate-500">{{ __('event.tab_coming_soon') }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ __('event.tab_coming_soon_hint') }}</p>
        </div>
    @endif
</div>
