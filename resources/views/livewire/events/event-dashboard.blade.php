<div class="space-y-5">
    @php
        $cd = $d['countdown'];
        $pct = $d['progress']['percent'];
    @endphp

    {{-- نوار شاخص‌ها --}}
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-ink-100 bg-white p-5">
            <p class="text-xs font-medium text-ink-500">{{ __('dashboard.task_progress') }}</p>
            <p class="mt-1 text-3xl font-bold tabular-nums text-ink-900">{{ fa_num((string) $pct) }}٪</p>
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-ink-100" role="img" aria-label="{{ $pct }}٪">
                <div class="h-full rounded-full bg-pool-600" style="width: {{ $pct }}%"></div>
            </div>
            <p class="mt-1.5 text-[11px] text-ink-400">
                {{ __('dashboard.tasks_done_of', ['done' => fa_num((string) $d['progress']['done']), 'total' => fa_num((string) $d['progress']['total'])]) }}
            </p>
        </div>

        <div class="rounded-2xl border border-ink-100 bg-white p-5">
            <p class="text-xs font-medium text-ink-500">{{ __('dashboard.overdue_tasks') }}</p>
            <p @class(['mt-1 text-3xl font-bold tabular-nums', 'text-signal-600' => $d['overdue_total'] > 0, 'text-ink-900' => $d['overdue_total'] === 0])>{{ fa_num((string) $d['overdue_total']) }}</p>
            <p class="mt-3 text-[11px] text-ink-400">{{ $d['overdue_total'] > 0 ? __('dashboard.overdue_hint') : __('dashboard.overdue_none') }}</p>
        </div>

        @if ($d['can_budget'] && $d['budget'])
            @php $b = $d['budget']; @endphp
            <div class="rounded-2xl border border-ink-100 bg-white p-5">
                <p class="text-xs font-medium text-ink-500">{{ __('dashboard.budget_used') }}</p>
                @if ($b['expense_usage_percent'] !== null)
                    <p @class(['mt-1 text-3xl font-bold tabular-nums', 'text-signal-600' => $b['expense_overrun'], 'text-ink-900' => ! $b['expense_overrun']])>{{ fa_num((string) round($b['expense_usage_percent'])) }}٪</p>
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-ink-100">
                        <div @class(['h-full rounded-full', 'bg-signal-500' => $b['expense_overrun'], 'bg-flag-500' => ! $b['expense_overrun']]) style="width: {{ min(100, (int) round($b['expense_usage_percent'])) }}%"></div>
                    </div>
                    <p class="mt-1.5 text-[11px] text-ink-400">{{ \App\Support\Money::format((int) $b['actual_expense']) }} / {{ \App\Support\Money::format((int) $b['planned_expense']) }}</p>
                @else
                    <p class="mt-1 text-3xl font-bold text-ink-300">—</p>
                    <p class="mt-3 text-[11px] text-ink-400">{{ __('dashboard.budget_none') }}</p>
                @endif
            </div>
        @endif

        @if ($d['can_risk'] && $d['risk'])
            @php $crit = (int) ($d['risk']['summary']['by_level']['critical'] ?? 0); @endphp
            <div class="rounded-2xl border border-ink-100 bg-white p-5">
                <p class="text-xs font-medium text-ink-500">{{ __('dashboard.critical_risks') }}</p>
                <p @class(['mt-1 text-3xl font-bold tabular-nums', 'text-signal-600' => $crit > 0, 'text-ink-900' => $crit === 0])>{{ fa_num((string) $crit) }}</p>
                <p class="mt-3 text-[11px] text-ink-400">{{ __('dashboard.risks_active', ['count' => fa_num((string) $d['risk']['summary']['total'])]) }}</p>
            </div>
        @endif
    </section>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            {{-- کمیته‌های عقب‌افتاده --}}
            <section class="rounded-2xl border border-ink-100 bg-white p-5">
                <h3 class="mb-3 text-sm font-bold text-ink-900">{{ __('dashboard.committees_behind') }}</h3>
                @if ($d['committees']->isEmpty())
                    <p class="text-sm text-ink-500">{{ __('dashboard.no_committees') }}</p>
                @elseif ($d['committees_behind']->isEmpty())
                    <p class="text-sm text-ink-500">{{ __('dashboard.no_committee_behind') }}</p>
                @else
                    <ul class="divide-y divide-ink-100">
                        @foreach ($d['committees_behind'] as $row)
                            <li class="py-3" wire:key="behind-{{ $row['committee']->id }}">
                                <div class="flex items-center justify-between gap-3">
                                    <a href="{{ route('events.show', ['event' => $event, 'tab' => 'tasks']) }}" wire:navigate class="truncate text-sm font-semibold text-ink-800 hover:text-pool-700">{{ $row['committee']->name }}</a>
                                    <span class="shrink-0 rounded-full bg-signal-50 px-2.5 py-1 text-[11px] font-bold text-signal-700">{{ __('dashboard.overdue_count', ['count' => fa_num((string) $row['overdue'])]) }}</span>
                                </div>
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-ink-100">
                                    <div class="h-full rounded-full bg-pool-600" style="width: {{ $row['progress']['percent'] }}%"></div>
                                </div>
                                <p class="mt-1 text-[11px] text-ink-400">{{ fa_num((string) $row['progress']['percent']) }}٪ — {{ __('dashboard.tasks_done_of', ['done' => fa_num((string) $row['progress']['done']), 'total' => fa_num((string) $row['progress']['total'])]) }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- ریسک‌های بحرانی --}}
            @if ($d['can_risk'] && $d['risk'])
                <section class="rounded-2xl border border-ink-100 bg-white p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-ink-900">{{ __('dashboard.critical_risks') }}</h3>
                        <a href="{{ route('events.show', ['event' => $event, 'tab' => 'risk']) }}" wire:navigate class="text-xs font-semibold text-pool-700 hover:text-pool-800">{{ __('dashboard.open_risks') }}</a>
                    </div>
                    @if ($d['risk']['critical']->isEmpty())
                        <p class="text-sm text-ink-500">{{ __('dashboard.no_critical_risk') }}</p>
                    @else
                        <ul class="space-y-2">
                            @foreach ($d['risk']['critical'] as $risk)
                                <li class="flex items-center justify-between gap-3 rounded-xl bg-signal-50 px-3 py-2.5 text-sm" wire:key="crit-{{ $risk->id }}">
                                    <span class="truncate font-medium text-ink-800">{{ $risk->title }}</span>
                                    <span class="shrink-0 rounded-md px-2 py-0.5 text-[11px] font-bold {{ $risk->level()->badgeClasses() }}">{{ fa_num((string) $risk->score) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif
        </div>

        <aside class="space-y-5">
            {{-- شمارش معکوس --}}
            <section class="led-panel rounded-3xl p-5">
                <p class="text-xs text-ink-300">{{ __('dashboard.countdown') }}</p>
                <div class="mt-3 rounded-2xl bg-black/50 px-4 py-4 text-center ring-1 ring-white/10">
                    @if ($cd['state'] === 'upcoming')
                        <p class="led-digit text-4xl font-bold">{{ fa_num((string) $cd['days']) }}</p>
                        <p class="mt-1 text-xs text-ink-300">{{ __('dashboard.days_to_start') }}</p>
                    @elseif ($cd['state'] === 'today')
                        <p class="text-lg font-bold text-flag-300">{{ __('dashboard.starts_today') }}</p>
                    @elseif ($cd['state'] === 'running')
                        <p class="text-lg font-bold text-flag-300">{{ __('dashboard.is_running') }}</p>
                    @elseif ($cd['state'] === 'finished')
                        <p class="text-lg font-bold text-ink-200">{{ __('dashboard.finished_days_ago', ['days' => fa_num((string) $cd['days'])]) }}</p>
                    @else
                        <p class="text-sm font-medium text-ink-300">{{ __('dashboard.no_start_date') }}</p>
                    @endif
                </div>
            </section>

            {{-- فعالیت اخیر --}}
            <section class="rounded-2xl border border-ink-100 bg-white p-5">
                <h3 class="mb-3 text-sm font-bold text-ink-900">{{ __('dashboard.recent_activity') }}</h3>
                @if ($d['activity']->isEmpty())
                    <p class="text-xs text-ink-400">{{ __('activity.empty') }}</p>
                @else
                    <ol class="space-y-2 border-s-2 border-ink-100 ps-3">
                        @foreach ($d['activity'] as $log)
                            <li class="text-[11px] leading-5 text-ink-500" wire:key="dact-{{ $log->id }}">
                                <span class="font-semibold text-ink-700">{{ \App\Support\ActivityDescriber::describe($log) }}</span>
                                <span class="block text-ink-400">
                                    {{ jalali_long($log->created_at) }} — {{ $log->user ? person_name($log->user) : __('activity.unknown_user') }}
                                </span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </aside>
    </div>
</div>
