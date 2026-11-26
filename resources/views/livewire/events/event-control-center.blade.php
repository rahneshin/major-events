<div class="space-y-5">
    @if (session('success'))
        <x-toast type="success">{{ session('success') }}</x-toast>
    @endif

    @php $cd = $c['countdown']; @endphp

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-ink-100 bg-white p-5">
            <p class="text-xs font-medium text-ink-500">{{ __('control.day_status') }}</p>
            <p class="mt-1 text-lg font-bold text-ink-900">
                @if ($cd['state'] === 'today') {{ __('dashboard.starts_today') }}
                @elseif ($cd['state'] === 'running') {{ __('dashboard.is_running') }}
                @elseif ($cd['state'] === 'upcoming') {{ __('control.days_left', ['days' => fa_num((string) $cd['days'])]) }}
                @elseif ($cd['state'] === 'finished') {{ __('control.finished') }}
                @else {{ __('dashboard.no_start_date') }} @endif
            </p>
        </div>
        <div class="rounded-2xl border border-ink-100 bg-white p-5">
            <p class="text-xs font-medium text-ink-500">{{ __('control.during_tasks') }}</p>
            <p class="mt-1 text-3xl font-bold tabular-nums text-ink-900">{{ fa_num((string) $c['task_total']) }}</p>
            <p class="mt-3 text-[11px] text-ink-400">{{ __('control.done_count', ['count' => fa_num((string) $c['task_counts']['done'])]) }}</p>
        </div>
        <div class="rounded-2xl border border-ink-100 bg-white p-5">
            <p class="text-xs font-medium text-ink-500">{{ __('control.overdue_during') }}</p>
            <p @class(['mt-1 text-3xl font-bold tabular-nums', 'text-signal-600' => $c['overdue_during'] > 0, 'text-ink-900' => $c['overdue_during'] === 0])>{{ fa_num((string) $c['overdue_during']) }}</p>
        </div>
        <div class="rounded-2xl border border-ink-100 bg-white p-5">
            <p class="text-xs font-medium text-ink-500">{{ __('control.open_incidents') }}</p>
            <p @class(['mt-1 text-3xl font-bold tabular-nums', 'text-signal-600' => $c['severe_unresolved'] > 0, 'text-ink-900' => $c['severe_unresolved'] === 0])>{{ fa_num((string) $c['unresolved_count']) }}</p>
            @if ($c['severe_unresolved'] > 0)
                <p class="mt-3 text-[11px] text-signal-700">{{ __('control.severe_hint', ['count' => fa_num((string) $c['severe_unresolved'])]) }}</p>
            @endif
        </div>
    </section>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <section class="rounded-2xl border border-ink-100 bg-white p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-ink-900">{{ __('control.open_tasks') }}</h3>
                    <a href="{{ route('events.show', ['event' => $event, 'tab' => 'tasks']) }}" wire:navigate class="text-xs font-semibold text-pool-700 hover:text-pool-800">{{ __('control.all_tasks') }}</a>
                </div>
                @if ($c['open_tasks']->isEmpty())
                    <p class="text-sm text-ink-500">{{ $c['task_total'] === 0 ? __('control.no_during_tasks') : __('control.all_during_done') }}</p>
                @else
                    <ul class="divide-y divide-ink-100">
                        @foreach ($c['open_tasks'] as $task)
                            <li class="flex items-center justify-between gap-3 py-2.5" wire:key="ctl-task-{{ $task->id }}">
                                <div class="min-w-0">
                                    <a href="{{ route('events.show', ['event' => $event, 'tab' => 'tasks', 'task' => $task->uuid]) }}" wire:navigate class="block truncate text-sm font-semibold text-ink-800 hover:text-pool-700">{{ $task->title }}</a>
                                    <p class="truncate text-[11px] text-ink-400">
                                        {{ $task->assignee ? person_name($task->assignee) : __('control.unassigned') }}
                                        @if ($task->committee) · {{ $task->committee->name }} @endif
                                    </p>
                                </div>
                                <div class="shrink-0 text-end">
                                    <span class="rounded-md bg-ink-100 px-2 py-0.5 text-[11px] font-medium text-ink-700">{{ $task->status->label() }}</span>
                                    @if ($task->isOverdue())
                                        <p class="mt-1 text-[11px] font-bold text-signal-600">{{ __('control.overdue') }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="rounded-2xl border border-ink-100 bg-white p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-ink-900">{{ __('control.incidents') }}</h3>
                    <a href="{{ route('events.show', ['event' => $event, 'tab' => 'risk']) }}" wire:navigate class="text-xs font-semibold text-pool-700 hover:text-pool-800">{{ __('control.report_incident') }}</a>
                </div>
                @if ($c['unresolved_incidents']->isEmpty())
                    <p class="text-sm text-ink-500">{{ __('control.no_incidents') }}</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($c['unresolved_incidents'] as $incident)
                            <li class="flex items-center justify-between gap-3 rounded-xl bg-ink-50 px-3 py-2.5 text-sm" wire:key="ctl-inc-{{ $incident->id }}">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-ink-800">{{ $incident->title }}</p>
                                    <p class="text-[11px] text-ink-400">{{ jalali_long($incident->occurred_at) }}</p>
                                </div>
                                <span class="shrink-0 rounded-md px-2 py-0.5 text-[11px] font-bold {{ $incident->severity->badgeClasses() }}">{{ $incident->severity->label() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <aside class="space-y-5">
            <section class="rounded-2xl border border-ink-100 bg-white p-5">
                <h3 class="mb-3 text-sm font-bold text-ink-900">{{ __('control.quick_announcement') }}</h3>

                @if ($canAnnounce)
                    <form wire:submit="publish" class="space-y-3">
                        <textarea wire:model="announcement" rows="3" maxlength="{{ config('notifications.announcement.max_length') }}"
                            class="w-full rounded-xl border border-ink-200 bg-white px-3 py-2 text-sm focus:border-pool-500 focus:outline-none focus:ring-2 focus:ring-pool-200"
                            placeholder="{{ __('control.announcement_placeholder') }}"></textarea>
                        <label class="flex items-start gap-2 text-xs text-ink-600">
                            <input type="checkbox" wire:model="urgent" class="mt-0.5 rounded border-ink-300 text-pool-600">
                            <span>{{ __('control.urgent_sms') }}</span>
                        </label>
                        @if ($error)
                            <p class="rounded-lg bg-signal-50 px-3 py-2 text-xs text-signal-700" role="alert">{{ $error }}</p>
                        @endif
                        <button type="submit" class="btn-primary w-full text-xs" wire:loading.attr="disabled">{{ __('control.send') }}</button>
                    </form>
                @else
                    <p class="text-xs leading-6 text-ink-500">{{ __('control.announce_denied') }}</p>
                @endif
            </section>

            <section class="rounded-2xl border border-ink-100 bg-white p-5">
                <h3 class="mb-3 text-sm font-bold text-ink-900">{{ __('control.recent_announcements') }}</h3>
                @if ($c['announcements']->isEmpty())
                    <p class="text-xs text-ink-400">{{ __('control.no_announcements') }}</p>
                @else
                    <ul class="space-y-3">
                        @foreach ($c['announcements'] as $a)
                            <li class="text-sm leading-6 text-ink-700" wire:key="ann-{{ $a->id }}">
                                {{ $a->body }}
                                <span class="mt-0.5 block text-[11px] text-ink-400">
                                    {{ jalali_long($a->created_at) }} — {{ $a->author ? person_name($a->author) : __('activity.unknown_user') }}
                                    @if ($a->urgent) · <span class="font-bold text-signal-600">{{ __('control.urgent') }}</span> @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </aside>
    </div>
</div>
