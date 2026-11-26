<div>
    @section('page_title', $event->title)

    <div class="mb-6 overflow-hidden rounded-3xl bg-gradient-to-l from-pool-700 via-pool-800 to-ink-900 p-6 text-white shadow-xl shadow-pool-900/20 sm:p-7">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-semibold backdrop-blur">
                        {{ $event->status?->label() }}
                    </span>
                    <span class="text-xs text-pool-100">{{ $event->event_type?->label() }}</span>
                </div>
                <h2 class="text-2xl font-bold">{{ $event->title }}</h2>
                <p class="mt-1 text-sm text-pool-100">
                    @if ($event->city) {{ $event->city }} @endif
                    @if ($event->start_date)
                        · {{ jalali_range($event->start_date, $event->end_date) }}
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($canUpdate)
                    <a href="{{ route('events.edit', $event) }}" class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-xs font-bold text-pool-700 shadow-md transition hover:bg-pool-50">
                        {{ __('ui.edit') }}
                    </a>
                @endif
                <a href="{{ route('events.index') }}" class="inline-flex items-center rounded-xl border border-white/30 bg-white/10 px-4 py-2 text-xs font-semibold text-white backdrop-blur transition hover:bg-white/20">
                    {{ __('ui.back') }}
                </a>
            </div>
        </div>
        <x-last-action class="mt-4 !text-pool-100/80" :subject="$event" />
    </div>

    <div class="sticky top-14 z-20 mb-5 flex gap-1 overflow-x-auto rounded-xl border border-ink-200 bg-white/95 p-1 text-xs font-medium shadow-sm backdrop-blur">
        @foreach ([
            'overview' => __('event.tab_overview'),
            'members' => __('event.tab_members'),
            'committees' => __('event.tab_committees'),
            'tasks' => __('event.tab_tasks'),
            'budget' => __('event.tab_budget'),
            'risk' => __('event.tab_risk'),
            'sponsorship' => __('event.tab_sponsorship'),
            'marketing' => __('event.tab_marketing'),
                        'competitions' => __('competition.tab'),
        ] as $key => $label)
            <button type="button" wire:click="setTab('{{ $key }}')"
                class="shrink-0 rounded-lg px-3.5 py-2.5 transition font-semibold
                {{ $tab === $key ? 'bg-pool-700 text-white shadow-md shadow-pool-700/25' : 'text-ink-600 hover:bg-ink-50' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($tab === 'overview')
        <div class="grid gap-5 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <div class="glass rounded-2xl p-5">
                    <h3 class="mb-3 text-sm font-bold text-ink-900">{{ __('event.overview') }}</h3>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-ink-400">{{ __('event.fields.scale') }}</dt>
                            <dd class="font-medium">{{ $event->scale?->label() ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-ink-400">{{ __('event.fields.sport_type') }}</dt>
                            <dd class="font-medium">{{ $event->sport_type ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-ink-400">{{ __('event.fields.venue') }}</dt>
                            <dd class="font-medium">{{ $event->venue_name ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-ink-400">{{ __('event.fields.main_goal_type') }}</dt>
                            <dd class="font-medium">{{ $event->main_goal_type?->label() ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-ink-400">{{ __('event.fields.participants') }}</dt>
                            <dd class="font-medium tabular-nums">{{ $event->expected_participants ? fa_num(number_format($event->expected_participants)) : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-ink-400">{{ __('event.fields.budget') }}</dt>
                            <dd class="font-medium tabular-nums">{{ $event->budget_estimate ? fa_num(number_format($event->budget_estimate)).' ریال' : '—' }}</dd>
                        </div>
                    </dl>
                    @if ($event->description)
                        <p class="mt-4 border-t border-ink-100 pt-4 text-sm leading-relaxed text-ink-600">{{ $event->description }}</p>
                    @endif
                </div>

                @if ($event->goals->isNotEmpty())
                    <div class="glass rounded-2xl p-5">
                        <h3 class="mb-3 text-sm font-bold text-ink-900">{{ __('event.goals') }}</h3>
                        <ul class="space-y-2">
                            @foreach ($event->goals as $goal)
                                <li class="flex items-start gap-2 rounded-xl bg-ink-50 px-3 py-2.5 text-sm">
                                    <span class="mt-0.5 rounded bg-pool-100 px-1.5 py-0.5 text-[10px] font-bold text-pool-700">
                                        {{ $goal->kind->label() }}
                                    </span>
                                    <div>
                                        <p class="font-medium text-ink-800">{{ $goal->title }}</p>
                                        @if ($goal->target_value)
                                            <p class="text-xs text-ink-400">{{ $goal->target_value }} {{ $goal->unit }}</p>
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
                        <h3 class="mb-3 text-sm font-bold text-ink-900">{{ __('event.change_status') }}</h3>
                        <div class="flex flex-col gap-2">
                            @foreach ($transitions as $t)
                                <button type="button" wire:click="transitionTo('{{ $t->value }}')"
                                    class="w-full rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-start text-sm font-semibold text-ink-700 shadow-sm transition hover:border-pool-300 hover:bg-pool-50 hover:text-pool-700">
                                    {{ $t->label() }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($canDelete)
                    <div class="glass rounded-2xl p-5">
                        <h3 class="mb-2 text-sm font-bold text-signal-700">{{ __('event.danger_zone') }}</h3>
                        <button type="button" wire:click="deleteEvent" wire:confirm="{{ __('event.confirm_delete') }}"
                            class="w-full rounded-xl border border-signal-200 bg-signal-50 px-4 py-2.5 text-sm font-bold text-signal-700 shadow-sm transition hover:bg-signal-100">
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

    @elseif ($tab === 'risk')
        <livewire:events.event-risk :event="$event" :key="'risk-'.$event->id" />

    @elseif ($tab === 'sponsorship')
        <livewire:events.event-sponsorship :event="$event" :key="'sponsorship-'.$event->id" />

    @elseif ($tab === 'marketing')
        <livewire:events.event-marketing :event="$event" :key="'marketing-'.$event->id" />

    @elseif ($tab === 'competitions')
        <livewire:events.event-competitions :event="$event" :key="'competitions-'.$event->id" />
            
    @elseif ($tab === 'committees')
        <livewire:events.event-committees :event="$event" :key="'committees-'.$event->id" />

    @else
        <div class="glass rounded-2xl px-6 py-14 text-center">
            <p class="text-sm font-medium text-ink-500">{{ __('event.tab_coming_soon') }}</p>
            <p class="mt-1 text-xs text-ink-400">{{ __('event.tab_coming_soon_hint') }}</p>
        </div>
    @endif
</div>
