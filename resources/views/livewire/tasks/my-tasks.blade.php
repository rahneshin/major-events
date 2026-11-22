@php
    use App\Enums\TaskStatus;

    $selectSm = 'rounded-lg border border-ink-200 bg-white px-2 py-1.5 text-xs font-medium text-ink-700 focus:border-pool-400 focus:outline-none';
    $statusClass = [
        'todo' => 'bg-ink-100 text-ink-700',
        'doing' => 'bg-pool-100 text-pool-700',
        'blocked' => 'bg-flag-300/60 text-ink-800',
        'done' => 'bg-success-500/15 text-success-500',
        'cancelled' => 'bg-ink-100 text-ink-400',
    ];
    $tabs = [
        'open' => [__('task.ui.mine_open'), $counts['open']],
        'overdue' => [__('task.ui.mine_overdue'), $counts['overdue']],
        'done' => [__('task.ui.mine_done'), $counts['done']],
        'all' => [__('task.ui.mine_all'), null],
    ];
@endphp

<div class="space-y-5">
    <div>
        <h1 class="text-xl font-bold text-ink-900">{{ __('task.ui.mine_title') }}</h1>
        <p class="mt-1 text-sm text-ink-500">{{ __('task.ui.mine_subtitle') }}</p>
    </div>

    @if ($error)
        <p class="rounded-xl bg-signal-50 px-4 py-2.5 text-xs font-semibold text-signal-600" role="alert">{{ $error }}</p>
    @endif

    <div class="flex gap-1 overflow-x-auto rounded-xl border border-ink-200 bg-white p-1 text-xs font-semibold" role="tablist">
        @foreach ($tabs as $key => [$text, $count])
            <button type="button" role="tab" aria-selected="{{ $filter === $key ? 'true' : 'false' }}"
                    wire:click="setFilter('{{ $key }}')" wire:key="mine-tab-{{ $key }}"
                    @class([
                        'shrink-0 rounded-lg px-3.5 py-2 transition-colors',
                        'bg-pool-600 text-white' => $filter === $key,
                        'text-ink-600 hover:bg-ink-50' => $filter !== $key,
                    ])>
                {{ $text }}@if ($count !== null) <span class="tabular-nums opacity-80">({{ fa_num($count) }})</span>@endif
            </button>
        @endforeach
    </div>

    @if ($groups->isEmpty())
        <div class="rounded-2xl border border-dashed border-ink-200 bg-white px-6 py-14 text-center">
            <p class="text-sm font-medium text-ink-500">{{ __('task.ui.mine_empty') }}</p>
        </div>
    @else
        @foreach ($groups as $eventId => $items)
            @php $evt = $items->first()->event; @endphp
            <section class="overflow-hidden rounded-2xl border border-ink-100 bg-white" wire:key="mine-ev-{{ $eventId }}">
                <header class="flex items-center justify-between gap-2 border-b border-ink-100 bg-ink-50/60 px-4 py-2.5">
                    <h2 class="truncate text-sm font-bold text-ink-900">{{ $evt->title }}</h2>
                    <a href="{{ route('events.show', ['event' => $evt, 'tab' => 'tasks']) }}" wire:navigate class="shrink-0 text-xs font-semibold text-pool-700 hover:underline">{{ __('task.ui.open_in_event') }}</a>
                </header>

                <ul class="divide-y divide-ink-100">
                    @foreach ($items as $task)
                        @php $overdue = $task->isOverdue(); @endphp
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between" wire:key="mine-{{ $task->id }}">
                            <div class="min-w-0">
                                <a href="{{ route('events.show', ['event' => $evt, 'tab' => 'tasks', 'task' => $task->uuid]) }}" wire:navigate
                                   @class(['block truncate text-sm font-semibold hover:underline', 'text-ink-900' => $task->status->isOpen(), 'text-ink-400 line-through' => $task->status->isFinal()])>{{ $task->title }}</a>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px]">
                                    <span class="rounded-full px-2 py-0.5 font-semibold {{ $statusClass[$task->status->value] }}">{{ $task->status->label() }}</span>
                                    @if ($task->committee)
                                        <span class="rounded-full bg-ink-50 px-2 py-0.5 text-ink-600">{{ $task->committee->name }}</span>
                                    @endif
                                    @if ($task->due_at)
                                        <span @class(['font-semibold', 'text-signal-600' => $overdue, 'text-ink-500' => ! $overdue])>
                                            {{ $overdue ? __('task.ui.overdue').' · ' : '' }}{{ fa_num(jalali($task->due_at)) }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            @if ($canMove($task))
                                <select wire:change="changeStatus({{ $task->id }}, $event.target.value)" class="{{ $selectSm }}" aria-label="{{ __('task.ui.change_status') }}">
                                    @if ($task->status === TaskStatus::Blocked)
                                        <option value="blocked" selected disabled>{{ TaskStatus::Blocked->label() }}</option>
                                    @endif
                                    @foreach ($statuses as $s)
                                        <option value="{{ $s->value }}" @selected($task->status === $s)>{{ $s->label() }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
</div>
