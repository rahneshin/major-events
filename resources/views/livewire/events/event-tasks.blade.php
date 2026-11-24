@php
    use App\Enums\MilestoneStatus;
    use App\Enums\TaskStatus;

    $input = 'block w-full rounded-xl border border-ink-200 bg-white px-3 py-2.5 text-sm text-ink-900 placeholder:text-ink-300 focus:border-pool-400 focus:outline-none focus:ring-2 focus:ring-pool-100';
    $selectSm = 'rounded-lg border border-ink-200 bg-white px-2 py-1.5 text-xs font-medium text-ink-700 focus:border-pool-400 focus:outline-none';
    $label = 'mb-1 block text-xs font-semibold text-ink-600';
    $err = 'mt-1 text-xs text-signal-600';
    $btnPrimary = 'inline-flex items-center justify-center rounded-xl bg-pool-600 px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-pool-700 disabled:opacity-50';
    $btnGhost = 'inline-flex items-center justify-center rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-sm font-semibold text-ink-700 transition-colors hover:bg-ink-50';
    $btnDanger = 'rounded-lg border border-signal-500/30 bg-signal-50 px-2.5 py-1.5 text-xs font-semibold text-signal-600 transition-colors hover:bg-signal-500/10';

    $statusClass = [
        'todo' => 'bg-ink-100 text-ink-700',
        'doing' => 'bg-pool-100 text-pool-700',
        'blocked' => 'bg-flag-300/60 text-ink-800',
        'done' => 'bg-success-500/15 text-success-500',
        'cancelled' => 'bg-ink-100 text-ink-400',
    ];
    $priorityClass = [
        'low' => 'bg-ink-100 text-ink-500',
        'medium' => 'bg-pool-50 text-pool-700',
        'high' => 'bg-flag-300/60 text-ink-800',
        'urgent' => 'bg-signal-50 text-signal-600',
    ];
    $manualStatuses = array_filter($statuses, fn ($s) => $s !== TaskStatus::Blocked);
    $uid = (int) auth()->id();
    $canMove = fn ($t) => ! $closed && ($isManager
        || ($t->committee_id !== null && in_array((int) $t->committee_id, $headIds, true))
        || ($nonViewer && (int) $t->assignee_id === $uid));
    $initial = fn ($u) => mb_substr($u?->name ?? '؟', 0, 1);
    $hasFilters = $fCommittee !== '' || $fStatus !== '' || $fAssignee !== '' || $fPhase !== '' || $fDue !== '' || $fMine || $fSearch !== '';
@endphp

<div class="space-y-5">
    @if ($error)
        <p class="sticky top-16 z-30 rounded-xl bg-signal-50 px-4 py-2.5 text-xs font-semibold text-signal-600 shadow-sm" role="alert">{{ $error }}</p>
    @endif
    @if ($notice)
        <p class="sticky top-16 z-30 rounded-xl bg-pool-50 px-4 py-2.5 text-xs font-semibold text-pool-700 shadow-sm" role="status">{{ $notice }}</p>
    @endif
    @if ($closed)
        <p class="rounded-xl bg-flag-300/40 px-4 py-2.5 text-xs font-semibold text-ink-800">{{ __('task.ui.event_closed') }}</p>
    @endif

    {{-- ============================================================ خلاصه --}}
    <div class="grid gap-3 sm:grid-cols-4">
        <div class="rounded-2xl border border-ink-100 bg-white p-4 sm:col-span-2">
            <p class="text-xs font-semibold text-ink-500">{{ __('task.ui.progress') }}</p>
            <p class="mt-2 text-lg font-bold tabular-nums text-ink-900">
                {{ fa_num($progress['percent']) }}٪
                <span class="text-xs font-medium text-ink-400">({{ fa_num($progress['done']) }} / {{ fa_num($progress['total']) }})</span>
            </p>
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-ink-100" role="img" aria-label="{{ fa_num($progress['percent']) }}٪">
                <div class="h-full rounded-full bg-pool-600" style="width: {{ $progress['percent'] }}%"></div>
            </div>
            @if ($progress['total'] === 0)
                <p class="mt-1 text-[11px] text-ink-400">{{ __('task.ui.progress_empty') }}</p>
            @endif
        </div>
        <div class="rounded-2xl border border-ink-100 bg-white p-4">
            <p class="text-xs font-semibold text-ink-500">{{ __('task.ui.overdue') }}</p>
            <p @class(['mt-2 text-lg font-bold tabular-nums', 'text-signal-600' => $overdueCount > 0, 'text-ink-900' => $overdueCount === 0])>{{ fa_num($overdueCount) }}</p>
            <button type="button" wire:click="$set('fDue', 'overdue')" class="mt-1 text-[11px] font-semibold text-pool-700 hover:underline">{{ __('task.ui.show') }}</button>
        </div>
        <div class="rounded-2xl border border-ink-100 bg-white p-4">
            <p class="text-xs font-semibold text-ink-500">{{ __('task.status.blocked') }}</p>
            <p class="mt-2 text-lg font-bold tabular-nums text-ink-900">{{ fa_num($blockedCount) }}</p>
            <button type="button" wire:click="$set('fStatus', 'blocked')" class="mt-1 text-[11px] font-semibold text-pool-700 hover:underline">{{ __('task.ui.show') }}</button>
        </div>
    </div>

    {{-- ============================================================ نوار ابزار --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-1 overflow-x-auto rounded-xl border border-ink-200 bg-white p-1 text-xs font-semibold" role="tablist">
            @foreach (['list' => __('task.ui.view_list'), 'board' => __('task.ui.view_board'), 'calendar' => __('task.ui.view_calendar')] as $key => $text)
                <button type="button" role="tab" aria-selected="{{ $mode === $key ? 'true' : 'false' }}"
                        wire:click="setView('{{ $key }}')" wire:key="tview-{{ $key }}"
                        @class([
                            'shrink-0 rounded-lg px-3.5 py-2 transition-colors',
                            'bg-pool-600 text-white' => $mode === $key,
                            'text-ink-600 hover:bg-ink-50' => $mode !== $key,
                        ])>{{ $text }}</button>
            @endforeach
        </div>

        @if ($canCreate)
            <button type="button" wire:click="openCreate" class="{{ $btnPrimary }}">+ {{ __('task.add') }}</button>
        @endif
    </div>

    {{-- ============================================================== فیلترها --}}
    <div class="rounded-2xl border border-ink-100 bg-white p-3">
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            <input type="search" wire:model.live.debounce.400ms="fSearch" placeholder="{{ __('task.ui.search') }}" class="{{ $input }}" aria-label="{{ __('task.ui.search') }}">

            <select wire:model.live="fCommittee" class="{{ $input }}" aria-label="{{ __('task.ui.f_committee') }}">
                <option value="">{{ __('task.ui.all_committees') }}</option>
                <option value="none">{{ __('task.ui.no_committee') }}</option>
                @foreach ($committees as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>

            @if ($mode === 'list')
                <select wire:model.live="fStatus" class="{{ $input }}" aria-label="{{ __('task.ui.f_status') }}">
                    <option value="">{{ __('task.ui.all_statuses') }}</option>
                    <option value="open">{{ __('task.ui.open_only') }}</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
            @endif

            <select wire:model.live="fAssignee" class="{{ $input }}" aria-label="{{ __('task.ui.f_assignee') }}">
                <option value="">{{ __('task.ui.all_assignees') }}</option>
                <option value="none">{{ __('task.ui.unassigned') }}</option>
                @foreach ($members as $m)
                    <option value="{{ $m->id }}">{{ $m->name ?? phone($m->phone) }}</option>
                @endforeach
            </select>

            <select wire:model.live="fPhase" class="{{ $input }}" aria-label="{{ __('task.ui.f_phase') }}">
                <option value="">{{ __('task.ui.all_phases') }}</option>
                @foreach ($phases as $p)
                    <option value="{{ $p->value }}">{{ $p->label() }}</option>
                @endforeach
            </select>

            <select wire:model.live="fDue" class="{{ $input }}" aria-label="{{ __('task.ui.f_due') }}">
                <option value="">{{ __('task.ui.any_due') }}</option>
                <option value="overdue">{{ __('task.ui.overdue') }}</option>
                <option value="week">{{ __('task.ui.due_week') }}</option>
                <option value="none">{{ __('task.ui.no_due') }}</option>
            </select>

            @if ($mode === 'list')
                <select wire:model.live="sort" class="{{ $input }}" aria-label="{{ __('task.ui.sort') }}">
                    <option value="position">{{ __('task.ui.sort_position') }}</option>
                    <option value="due">{{ __('task.ui.sort_due') }}</option>
                    <option value="priority">{{ __('task.ui.sort_priority') }}</option>
                    <option value="status">{{ __('task.ui.sort_status') }}</option>
                </select>
            @endif

            <label class="flex items-center gap-2 rounded-xl border border-ink-200 px-3 py-2.5 text-sm text-ink-700">
                <input type="checkbox" wire:model.live="fMine" class="h-4 w-4 rounded border-ink-300 text-pool-600">
                {{ __('task.ui.only_mine') }}
            </label>
        </div>
        @if ($hasFilters)
            <button type="button" wire:click="resetFilters" class="mt-2 text-xs font-semibold text-pool-700 hover:underline">{{ __('task.ui.reset_filters') }}</button>
        @endif
    </div>

    {{-- ============================================================== لیست --}}
    @if ($mode === 'list')
        @if ($tasks->isEmpty())
            <div class="rounded-2xl border border-dashed border-ink-200 bg-white px-6 py-14 text-center">
                <h3 class="text-base font-bold text-ink-900">{{ $hasFilters ? __('task.ui.no_results') : __('task.ui.empty_title') }}</h3>
                <p class="mx-auto mt-2 max-w-sm text-sm leading-7 text-ink-500">{{ $hasFilters ? __('task.ui.no_results_desc') : __('task.ui.empty_desc') }}</p>
            </div>
        @else
            <ul class="divide-y divide-ink-100 overflow-hidden rounded-2xl border border-ink-100 bg-white">
                @foreach ($tasks as $task)
                    @php $overdue = $task->isOverdue(); @endphp
                    <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center" wire:key="t-{{ $task->id }}">
                        <div class="min-w-0 flex-1">
                            <button type="button" wire:click="openTask({{ $task->id }})" class="block max-w-full text-start">
                                @if ($task->parent)
                                    <span class="block truncate text-[11px] text-ink-400">↳ {{ $task->parent->title }}</span>
                                @endif
                                <span @class(['block truncate text-sm font-semibold', 'text-ink-900' => $task->status->isOpen(), 'text-ink-400 line-through' => $task->status->isFinal()])>{{ $task->title }}</span>
                            </button>
                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11px]">
                                <span class="rounded-full px-2 py-0.5 font-semibold {{ $statusClass[$task->status->value] }}">{{ $task->status->label() }}</span>
                                <span class="rounded-full px-2 py-0.5 font-semibold {{ $priorityClass[$task->priority->value] }}">{{ $task->priority->label() }}</span>
                                @if ($task->committee)
                                    <span class="rounded-full bg-ink-50 px-2 py-0.5 text-ink-600">{{ $task->committee->name }}</span>
                                @endif
                                @if ($task->due_at)
                                    <span @class(['font-semibold', 'text-signal-600' => $overdue, 'text-ink-500' => ! $overdue])>
                                        {{ $overdue ? __('task.ui.overdue').' · ' : '' }}{{ jalali_long($task->due_at) }}
                                    </span>
                                @endif
                            </div>
                            <x-audit-stamp class="mt-1" :at="$task->created_at" :by="$task->creator" />
                        </div>

                        <div class="flex items-center gap-2">
                            @if ($task->assignee)
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-pool-100 text-xs font-bold text-pool-700" title="{{ $task->assignee->name }}">{{ $initial($task->assignee) }}</span>
                            @endif

                            @if ($canMove($task))
                                <select wire:change="changeStatus({{ $task->id }}, $event.target.value)" class="{{ $selectSm }}" aria-label="{{ __('task.ui.change_status') }}">
                                    @if ($task->status === TaskStatus::Blocked)
                                        <option value="blocked" selected disabled>{{ TaskStatus::Blocked->label() }}</option>
                                    @endif
                                    @foreach ($manualStatuses as $s)
                                        <option value="{{ $s->value }}" @selected($task->status === $s)>{{ $s->label() }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($tasksTruncated)
                <p class="text-center text-xs text-ink-400">{{ __('task.ui.truncated', ['max' => fa_num(config('tasks.list_limit', 300))]) }}</p>
            @endif
        @endif

    {{-- ============================================================= کانبان --}}
    @elseif ($mode === 'board')
        @php $byStatus = $tasks->groupBy(fn ($t) => $t->status->value); @endphp
        <p class="text-xs leading-6 text-ink-500">{{ __('task.ui.board_hint') }}</p>

        <div class="flex gap-3 overflow-x-auto pb-2" x-data="{ dragging: null, over: null }">
            @foreach ($statuses as $col)
                @php $items = $byStatus->get($col->value, collect()); $droppable = $col !== TaskStatus::Blocked; @endphp
                <section class="w-72 shrink-0 rounded-2xl border bg-ink-50/60 p-2.5 transition-colors"
                         :class="over === '{{ $col->value }}' ? 'border-pool-400 bg-pool-50' : 'border-ink-100'"
                         wire:key="col-{{ $col->value }}"
                         @if ($droppable)
                             x-on:dragover.prevent="over = '{{ $col->value }}'"
                             x-on:dragleave="over = null"
                             x-on:drop.prevent="if (dragging) { $wire.changeStatus(dragging, '{{ $col->value }}'); } dragging = null; over = null"
                         @endif
                         aria-label="{{ $col->label() }}">
                    <header class="mb-2 flex items-center justify-between px-1">
                        <h4 class="text-xs font-bold text-ink-700">{{ $col->label() }}</h4>
                        <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold tabular-nums text-ink-500">{{ fa_num($items->count()) }}</span>
                    </header>

                    <div class="space-y-2">
                        @forelse ($items as $task)
                            @php $movable = $canMove($task); $overdue = $task->isOverdue(); @endphp
                            <article class="rounded-xl border border-ink-100 bg-white p-3 shadow-sm"
                                     wire:key="card-{{ $task->id }}"
                                     @if ($movable) draggable="true" x-on:dragstart="dragging = {{ $task->id }}; $event.dataTransfer.effectAllowed = 'move'" x-on:dragend="dragging = null; over = null" @endif>
                                <button type="button" wire:click="openTask({{ $task->id }})" class="block w-full text-start text-sm font-semibold text-ink-900">{{ $task->title }}</button>
                                <div class="mt-2 flex flex-wrap items-center gap-1.5 text-[11px]">
                                    <span class="rounded-full px-2 py-0.5 font-semibold {{ $priorityClass[$task->priority->value] }}">{{ $task->priority->label() }}</span>
                                    @if ($task->due_at)
                                        <span @class(['font-semibold', 'text-signal-600' => $overdue, 'text-ink-500' => ! $overdue])>{{ jalali_long($task->due_at, false) }}</span>
                                    @endif
                                    @if ($task->committee)
                                        <span class="rounded-full bg-ink-50 px-2 py-0.5 text-ink-600">{{ $task->committee->name }}</span>
                                    @endif
                                    @if ($task->assignee)
                                        <span class="ms-auto flex h-6 w-6 items-center justify-center rounded-full bg-pool-100 text-[10px] font-bold text-pool-700" title="{{ $task->assignee->name }}">{{ $initial($task->assignee) }}</span>
                                    @endif
                                </div>
                                @if ($movable)
                                    {{-- موبایل/لمسی: کشیدن-رها کار نمی‌کند؛ انتخاب مستقیم وضعیت --}}
                                    <select wire:change="changeStatus({{ $task->id }}, $event.target.value)" class="{{ $selectSm }} mt-2 w-full" aria-label="{{ __('task.ui.move_to') }}">
                                        @if ($task->status === TaskStatus::Blocked)
                                            <option value="blocked" selected disabled>{{ TaskStatus::Blocked->label() }}</option>
                                        @endif
                                        @foreach ($manualStatuses as $s)
                                            <option value="{{ $s->value }}" @selected($task->status === $s)>{{ __('task.ui.move_to') }}: {{ $s->label() }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </article>
                        @empty
                            <p class="px-2 py-6 text-center text-[11px] text-ink-300">{{ __('task.ui.col_empty') }}</p>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
        @if ($tasksTruncated)
            <p class="text-center text-xs text-ink-400">{{ __('task.ui.truncated', ['max' => fa_num(config('tasks.list_limit', 300))]) }}</p>
        @endif

    {{-- ============================================================= تقویم --}}
    @else
        {{-- تقویم شمسی کاملاً در مرورگر (jalali.js)؛ داده از data-events می‌آید و با هر رندر Livewire تازه می‌شود --}}
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]" x-data="jalaliCalendar" wire:key="task-calendar"
             data-events="{{ json_encode($calendarEvents, JSON_UNESCAPED_UNICODE) }}">

            <section class="overflow-hidden rounded-2xl border border-ink-100 bg-white" aria-label="{{ __('task.ui.view_calendar') }}">
                <header class="flex flex-wrap items-center justify-between gap-2 border-b border-ink-100 px-4 py-3">
                    <div class="flex items-center gap-1">
                        <button type="button" x-on:click="prev()" class="rounded-lg border border-ink-200 p-2 text-ink-600 hover:bg-ink-50" aria-label="{{ __('task.ui.prev_month') }}">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </button>
                        <button type="button" x-on:click="next()" class="rounded-lg border border-ink-200 p-2 text-ink-600 hover:bg-ink-50" aria-label="{{ __('task.ui.next_month') }}">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <button type="button" x-on:click="goToday()" class="rounded-lg border border-ink-200 px-3 py-2 text-xs font-semibold text-ink-700 hover:bg-ink-50">{{ __('task.ui.today') }}</button>
                    </div>

                    <div class="flex items-center gap-2">
                        <select x-model.number="jm" class="{{ $selectSm }} !py-2 text-sm font-bold" aria-label="{{ __('task.ui.month') }}">
                            <template x-for="(name, i) in months" :key="i">
                                <option :value="i + 1" x-text="name"></option>
                            </template>
                        </select>
                        <select x-model.number="jy" class="{{ $selectSm }} !py-2 text-sm font-bold tabular-nums" aria-label="{{ __('task.ui.year') }}">
                            <template x-for="y in years" :key="y">
                                <option :value="y" x-text="fa(y)"></option>
                            </template>
                        </select>
                    </div>
                </header>

                <p class="border-b border-ink-100 bg-ink-50/50 px-4 py-1.5 text-[11px] text-ink-500" x-text="fa(monthCount) + ' {{ __('task.ui.cal_items_in_month') }}'"></p>

                <div class="grid grid-cols-7 border-b border-ink-100 bg-ink-50/50 text-center text-[11px] font-bold">
                    <template x-for="(w, i) in weekdays" :key="i">
                        <div class="py-2" :class="i === 6 ? 'text-signal-500' : 'text-ink-500'">
                            <span class="hidden sm:inline" x-text="w"></span>
                            <span class="sm:hidden" x-text="weekdaysShort[i]"></span>
                        </div>
                    </template>
                </div>

                <div class="grid grid-cols-7">
                    <template x-for="cell in grid" :key="cell.iso">
                        <button type="button" x-on:click="select(cell)"
                                class="relative flex min-h-[3.4rem] flex-col items-stretch gap-1 border-b border-s border-ink-100 p-1.5 text-start transition-colors hover:bg-pool-50/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-pool-400 sm:min-h-[6.75rem]"
                                :class="[
                                    !cell.inMonth ? 'bg-ink-50/60' : (cell.friday ? 'bg-signal-50/40' : 'bg-white'),
                                    cell.iso === selected ? '!bg-pool-50 ring-2 ring-inset ring-pool-500' : '',
                                ]"
                                :aria-label="fa(cell.jd) + ' ' + months[cell.jm - 1] + (cell.items.length ? '، ' + fa(cell.items.length) + ' مورد' : '')"
                                :aria-pressed="cell.iso === selected ? 'true' : 'false'">
                            <span class="flex items-center justify-between">
                                <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-xs font-bold tabular-nums"
                                      :class="[
                                          cell.iso === today ? 'bg-pool-600 text-white' : (cell.friday || cell.holiday ? 'text-signal-600' : 'text-ink-800'),
                                          !cell.inMonth && cell.iso !== today ? 'opacity-40' : '',
                                      ]" x-text="fa(cell.jd)"></span>
                                <span class="hidden text-[10px] tabular-nums text-ink-300 sm:inline" dir="ltr" x-text="cell.gd"></span>
                            </span>

                            <span class="hidden truncate text-[10px] font-semibold text-signal-500 sm:block" x-show="cell.holiday && cell.inMonth" x-text="cell.holiday"></span>

                            <span class="hidden flex-col gap-0.5 sm:flex">
                                <template x-for="e in cell.items.slice(0, 2)" :key="e.kind + e.id">
                                    <span class="truncate rounded px-1.5 py-0.5 text-[10px] font-semibold" :class="chipClass(e)" x-text="e.title"></span>
                                </template>
                                <span class="px-1 text-[10px] font-semibold text-ink-500" x-show="cell.items.length > 2" x-text="'+' + fa(cell.items.length - 2) + ' {{ __('task.ui.more') }}'"></span>
                            </span>

                            <span class="mt-auto flex flex-wrap gap-0.5 sm:hidden">
                                <template x-for="e in cell.items.slice(0, 4)" :key="e.kind + e.id">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="dotClass(e)"></span>
                                </template>
                            </span>
                        </button>
                    </template>
                </div>

                <footer class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-ink-100 px-4 py-2.5 text-[11px] text-ink-500">
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-signal-500"></span>{{ __('task.ui.overdue') }}</span>
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-pool-500"></span>{{ __('task.status.doing') }}</span>
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-pool-300"></span>{{ __('task.status.todo') }}</span>
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-success-500"></span>{{ __('task.status.done') }}</span>
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-flag-500"></span>{{ __('task.ui.milestones') }}</span>
                    <span class="text-ink-400">{{ __('task.ui.cal_holiday_note') }}</span>
                </footer>
            </section>

            {{-- پنل روز انتخاب‌شده --}}
            <aside class="rounded-2xl border border-ink-100 bg-white p-4 lg:sticky lg:top-4 lg:self-start" aria-live="polite">
                <p class="text-[11px] tabular-nums text-ink-400" dir="ltr" x-text="selectedGregorian"></p>
                <h3 class="mt-0.5 text-sm font-bold text-ink-900" x-text="selectedLabel"></h3>
                <p class="mt-1 text-xs font-semibold text-signal-600" x-show="selectedHoliday" x-text="selectedHoliday"></p>

                <ul class="mt-3 space-y-2">
                    <template x-for="e in agenda" :key="e.kind + e.id">
                        <li>
                            <button type="button" class="flex w-full items-start gap-2 rounded-xl border border-ink-100 p-2.5 text-start transition-colors hover:bg-ink-50"
                                    x-on:click="e.kind === 'task' ? $wire.openTask(e.id) : null">
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="dotClass(e)"></span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-ink-900" :class="e.status === 'done' || e.status === 'cancelled' ? 'line-through opacity-60' : ''" x-text="(e.kind === 'milestone' ? '⚑ ' : '') + e.title"></span>
                                    <span class="mt-0.5 block text-[11px] text-ink-500">
                                        <span x-text="e.statusLabel"></span>
                                        <template x-if="e.priorityLabel"><span x-text="' · ' + e.priorityLabel"></span></template>
                                        <template x-if="e.who"><span x-text="' · ' + e.who"></span></template>
                                        <template x-if="e.overdue"><span class="font-semibold text-signal-600">{{ ' · '.__('task.ui.overdue') }}</span></template>
                                    </span>
                                </span>
                            </button>
                        </li>
                    </template>
                </ul>

                <p class="mt-3 text-xs text-ink-400" x-show="agenda.length === 0">{{ __('task.ui.cal_day_empty') }}</p>

                @if ($canCreate)
                    <button type="button" x-on:click="$wire.openCreateOn(selected)" class="{{ $btnGhost }} mt-4 w-full !py-2 text-xs">+ {{ __('task.ui.cal_add_on_day') }}</button>
                @endif
            </aside>
        </div>

        @if ($undated->isNotEmpty())
            <div class="rounded-2xl border border-ink-100 bg-white p-4">
                <h4 class="mb-2 text-xs font-bold text-ink-700">{{ __('task.ui.undated') }}</h4>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($undated as $task)
                        <button type="button" wire:click="openTask({{ $task->id }})" wire:key="und-{{ $task->id }}" class="rounded-lg bg-ink-50 px-2.5 py-1 text-xs font-medium text-ink-700 hover:bg-ink-100">{{ $task->title }}</button>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    {{-- ========================================================= نقاط عطف --}}
    <div class="rounded-2xl border border-ink-100 bg-white p-5">
        <div class="mb-3 flex items-center justify-between gap-2">
            <h3 class="text-sm font-bold text-ink-900">{{ __('task.ui.milestones') }}</h3>
            @if ($canManageMilestones)
                <button type="button" wire:click="openMilestoneForm()" class="text-xs font-bold text-pool-700 hover:underline">+ {{ __('task.ui.add_milestone') }}</button>
            @endif
        </div>

        @if ($showMilestoneForm)
            <div class="mb-4 grid gap-3 rounded-xl bg-ink-50 p-3 sm:grid-cols-3" wire:key="ms-form">
                <div class="sm:col-span-2">
                    <label for="ms-title" class="{{ $label }}">{{ __('task.ui.f_title') }}</label>
                    <input id="ms-title" type="text" wire:model="msTitle" maxlength="200" class="{{ $input }}">
                    @error('msTitle') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="ms-due" class="{{ $label }}">{{ __('task.ui.f_due') }}</label>
                    <x-jalali-picker id="ms-due" wire:model="msDue" />
                    @error('msDue') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-3">
                    <label for="ms-desc" class="{{ $label }}">{{ __('task.ui.f_description') }}</label>
                    <textarea id="ms-desc" wire:model="msDescription" rows="2" class="{{ $input }}"></textarea>
                </div>
                <div class="flex gap-2 sm:col-span-3">
                    <button type="button" wire:click="saveMilestone" class="{{ $btnPrimary }}">{{ __('ui.save') }}</button>
                    <button type="button" wire:click="cancelMilestoneForm" class="{{ $btnGhost }}">{{ __('ui.cancel') }}</button>
                </div>
            </div>
        @endif

        @if ($milestones->isEmpty())
            <p class="text-sm text-ink-500">{{ __('task.ui.no_milestones') }}</p>
        @else
            <ul class="divide-y divide-ink-100">
                @foreach ($milestones as $ms)
                    @php $msOverdue = $ms->isOverdue(); @endphp
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2.5" wire:key="ms-{{ $ms->id }}">
                        <div class="min-w-0">
                            <p @class(['truncate text-sm font-semibold', 'text-ink-900' => $ms->status === MilestoneStatus::Pending, 'text-ink-400 line-through' => $ms->status !== MilestoneStatus::Pending])>⚑ {{ $ms->title }}</p>
                            <p class="text-[11px] text-ink-500">
                                {{ $ms->status->label() }}
                                @if ($ms->due_at)
                                    · <span @class(['font-semibold text-signal-600' => $msOverdue])>{{ jalali_long($ms->due_at) }}</span>
                                @endif
                            </p>
                        </div>
                        @if ($canManageMilestones)
                            <div class="flex items-center gap-1.5">
                                @if ($ms->status === MilestoneStatus::Pending)
                                    <button type="button" wire:click="setMilestoneStatus({{ $ms->id }}, 'reached')" class="{{ $selectSm }}">{{ __('task.ui.mark_reached') }}</button>
                                @else
                                    <button type="button" wire:click="setMilestoneStatus({{ $ms->id }}, 'pending')" class="{{ $selectSm }}">{{ __('task.ui.reopen') }}</button>
                                @endif
                                <button type="button" wire:click="openMilestoneForm({{ $ms->id }})" class="{{ $selectSm }}">{{ __('ui.edit') }}</button>
                                <button type="button" wire:click="deleteMilestone({{ $ms->id }})" wire:confirm="{{ __('task.ui.confirm_delete_milestone') }}" class="{{ $btnDanger }}">{{ __('ui.delete') }}</button>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- ====================================================== پنل جزئیات --}}
    @if ($open)
        @php $task = $open['task']; @endphp
        <div class="fixed inset-0 z-50" wire:key="task-drawer-{{ $task->id }}" x-data x-on:keydown.escape.window="$wire.closeTask()">
            <div class="absolute inset-0 bg-ink-950/50" wire:click="closeTask" aria-hidden="true"></div>

            <aside class="absolute inset-y-0 end-0 flex w-full max-w-lg flex-col overflow-y-auto bg-white shadow-2xl" role="dialog" aria-modal="true" aria-label="{{ $task->title }}">
                <header class="sticky top-0 z-10 flex items-start justify-between gap-3 border-b border-ink-100 bg-white px-5 py-4">
                    <div class="min-w-0">
                        @if ($task->parent)
                            <button type="button" wire:click="openTask({{ $task->parent->id }})" class="mb-0.5 block max-w-full truncate text-[11px] font-semibold text-pool-700 hover:underline">↳ {{ $task->parent->title }}</button>
                        @endif
                        <h3 class="text-base font-bold leading-7 text-ink-900">{{ $task->title }}</h3>
                    </div>
                    <button type="button" wire:click="closeTask" class="rounded-lg p-1.5 text-ink-500 hover:bg-ink-100" aria-label="{{ __('ui.close') }}">✕</button>
                </header>

                <div class="space-y-5 px-5 py-4">
                    @if ($error)
                        <p class="rounded-xl bg-signal-50 px-3 py-2 text-xs font-semibold text-signal-600" role="alert">{{ $error }}</p>
                    @endif
                    @if ($notice)
                        <p class="rounded-xl bg-pool-50 px-3 py-2 text-xs font-semibold text-pool-700" role="status">{{ $notice }}</p>
                    @endif

                    {{-- وضعیت --}}
                    <div>
                        <div class="mb-2 flex flex-wrap items-center gap-1.5 text-[11px]">
                            <span class="rounded-full px-2 py-0.5 font-semibold {{ $statusClass[$task->status->value] }}">{{ $task->status->label() }}</span>
                            <span class="rounded-full px-2 py-0.5 font-semibold {{ $priorityClass[$task->priority->value] }}">{{ $task->priority->label() }}</span>
                            <span class="rounded-full bg-ink-50 px-2 py-0.5 text-ink-600">{{ $task->phase->label() }}</span>
                            <span class="rounded-full bg-ink-50 px-2 py-0.5 text-ink-600">{{ $task->module->label() }}</span>
                            @if ($task->source === \App\Enums\TaskSource::Ai)
                                <span class="rounded-full bg-flag-300/60 px-2 py-0.5 font-semibold text-ink-800">{{ $task->source->label() }}</span>
                            @endif
                        </div>

                        @if ($open['canTransition'])
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($manualStatuses as $s)
                                    <button type="button" wire:click="changeStatus({{ $task->id }}, '{{ $s->value }}')" wire:key="ds-{{ $s->value }}"
                                            @disabled($task->status === $s)
                                            @class([
                                                'rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors',
                                                'bg-pool-600 text-white' => $task->status === $s,
                                                'border border-ink-200 bg-white text-ink-700 hover:bg-ink-50' => $task->status !== $s,
                                            ])>{{ $s->label() }}</button>
                                @endforeach
                            </div>
                            @if ($task->status === TaskStatus::Blocked)
                                <p class="mt-2 text-[11px] leading-5 text-ink-500">{{ __('task.ui.blocked_hint') }}</p>
                            @endif
                        @endif
                    </div>

                    {{-- مشخصات --}}
                    <dl class="grid grid-cols-2 gap-3 rounded-xl bg-ink-50 p-3 text-xs">
                        <div><dt class="text-ink-400">{{ __('task.ui.f_assignee') }}</dt><dd class="mt-0.5 font-semibold text-ink-800">{{ $task->assignee?->name ?? ($task->assignee ? phone($task->assignee->phone) : '—') }}</dd></div>
                        <div><dt class="text-ink-400">{{ __('task.ui.f_committee') }}</dt><dd class="mt-0.5 font-semibold text-ink-800">{{ $task->committee?->name ?? '—' }}</dd></div>
                        <div><dt class="text-ink-400">{{ __('task.ui.f_start') }}</dt><dd class="mt-0.5 font-semibold text-ink-800">{{ $task->start_at ? jalali_long($task->start_at) : '—' }}</dd></div>
                        <div><dt class="text-ink-400">{{ __('task.ui.f_due') }}</dt><dd @class(['mt-0.5 font-semibold', 'text-signal-600' => $task->isOverdue(), 'text-ink-800' => ! $task->isOverdue()])>{{ $task->due_at ? jalali_long($task->due_at) : '—' }}</dd></div>
                        <div><dt class="text-ink-400">{{ __('task.ui.f_hours') }}</dt><dd class="mt-0.5 font-semibold text-ink-800">{{ $task->estimated_hours !== null ? fa_num(rtrim(rtrim((string) $task->estimated_hours, '0'), '.')) : '—' }}</dd></div>
                        <div><dt class="text-ink-400">{{ __('task.ui.completed_at') }}</dt><dd class="mt-0.5 font-semibold text-ink-800">{{ $task->completed_at ? jalali_long($task->completed_at) : '—' }}</dd></div>
                    </dl>

                    @if ($task->description)
                        <p class="whitespace-pre-line text-sm leading-7 text-ink-700">{{ $task->description }}</p>
                    @endif

                    @if ($open['canUpdate'] || $open['canDelete'])
                        <div class="flex gap-2">
                            @if ($open['canUpdate'])
                                <button type="button" wire:click="openEdit({{ $task->id }})" class="{{ $btnGhost }} !py-1.5 text-xs">{{ __('ui.edit') }}</button>
                            @endif
                            @if ($open['canDelete'])
                                <button type="button" wire:click="deleteTask({{ $task->id }})" wire:confirm="{{ __('task.confirm_delete') }}" class="{{ $btnDanger }}">{{ __('ui.delete') }}</button>
                            @endif
                        </div>
                    @endif

                    {{-- زیروظیفه‌ها --}}
                    @if (! $task->isSubtask())
                        <section>
                            <h4 class="mb-2 text-xs font-bold text-ink-700">{{ __('task.ui.subtasks') }} <span class="text-ink-400">({{ fa_num($task->subtasks->count()) }})</span></h4>
                            @if ($task->subtasks->isNotEmpty())
                                <ul class="mb-2 divide-y divide-ink-100 rounded-xl border border-ink-100">
                                    @foreach ($task->subtasks as $sub)
                                        <li class="flex items-center justify-between gap-2 px-3 py-2" wire:key="sub-{{ $sub->id }}">
                                            <button type="button" wire:click="openTask({{ $sub->id }})" @class(['truncate text-start text-sm', 'text-ink-800' => $sub->status->isOpen(), 'text-ink-400 line-through' => $sub->status->isFinal()])>{{ $sub->title }}</button>
                                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $statusClass[$sub->status->value] }}">{{ $sub->status->label() }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if ($open['canAddSubtask'])
                                <div class="flex gap-2">
                                    <input type="text" wire:model="subtaskTitle" wire:keydown.enter="addSubtask" placeholder="{{ __('task.ui.subtask_placeholder') }}" maxlength="200" class="{{ $input }}">
                                    <button type="button" wire:click="addSubtask" class="{{ $btnGhost }} shrink-0">{{ __('task.ui.add') }}</button>
                                </div>
                            @endif
                        </section>
                    @endif

                    {{-- وابستگی‌ها --}}
                    <section>
                        <h4 class="mb-2 text-xs font-bold text-ink-700">{{ __('task.ui.prerequisites') }}</h4>
                        @if ($task->prerequisites->isEmpty())
                            <p class="text-xs text-ink-400">{{ __('task.ui.no_prerequisites') }}</p>
                        @else
                            <ul class="mb-2 divide-y divide-ink-100 rounded-xl border border-ink-100">
                                @foreach ($task->prerequisites as $pre)
                                    <li class="flex items-center justify-between gap-2 px-3 py-2" wire:key="pre-{{ $pre->id }}">
                                        <button type="button" wire:click="openTask({{ $pre->id }})" class="truncate text-start text-sm text-ink-800">{{ $pre->title }}</button>
                                        <span class="flex shrink-0 items-center gap-1.5">
                                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $statusClass[$pre->status->value] }}">{{ $pre->status->label() }}</span>
                                            @if ($open['canDeps'])
                                                <button type="button" wire:click="removeDependency({{ $pre->id }})" class="text-xs text-signal-600 hover:underline" aria-label="{{ __('ui.delete') }}">✕</button>
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if ($open['canDeps'])
                            <div class="flex gap-2">
                                <select wire:model="depPick" class="{{ $input }}" aria-label="{{ __('task.ui.add_prerequisite') }}">
                                    <option value="">{{ __('task.ui.add_prerequisite') }}</option>
                                    @foreach ($open['candidates'] as $cand)
                                        <option value="{{ $cand->id }}">{{ $cand->title }}</option>
                                    @endforeach
                                </select>
                                <button type="button" wire:click="addDependency" class="{{ $btnGhost }} shrink-0">{{ __('task.ui.add') }}</button>
                            </div>
                        @endif
                        @if ($task->dependents->isNotEmpty())
                            <p class="mt-3 text-[11px] font-semibold text-ink-500">{{ __('task.ui.dependents') }}</p>
                            <ul class="mt-1 space-y-1">
                                @foreach ($task->dependents as $dep)
                                    <li wire:key="dep-{{ $dep->id }}"><button type="button" wire:click="openTask({{ $dep->id }})" class="text-start text-xs text-pool-700 hover:underline">{{ $dep->title }}</button></li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    {{-- پیوست‌ها --}}
                    <section>
                        <h4 class="mb-2 text-xs font-bold text-ink-700">{{ __('task.ui.attachments') }} <span class="text-ink-400">({{ fa_num($task->attachments->count()) }})</span></h4>
                        @if ($task->attachments->isNotEmpty())
                            <ul class="mb-2 divide-y divide-ink-100 rounded-xl border border-ink-100">
                                @foreach ($task->attachments as $att)
                                    <li class="flex items-center justify-between gap-2 px-3 py-2" wire:key="att-{{ $att->id }}">
                                        <a href="{{ route('tasks.attachments.download', [$event, $task, $att]) }}" class="min-w-0 truncate text-sm font-medium text-pool-700 hover:underline">{{ $att->original_name }}</a>
                                        <span class="flex shrink-0 items-center gap-2 text-[11px] text-ink-400">
                                            <bdi class="tabular-nums">{{ fa_num(number_format($att->size / 1024, 0)) }} KB</bdi>
                                            @if (auth()->user()->can('removeAttachment', [$task, $att]))
                                                <button type="button" wire:click="removeAttachment({{ $att->id }})" wire:confirm="{{ __('task.ui.confirm_remove_attachment') }}" class="text-signal-600 hover:underline" aria-label="{{ __('ui.delete') }}">✕</button>
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if ($open['canAttach'])
                            <div class="flex flex-wrap items-center gap-2">
                                <input type="file" wire:model="attachment" class="block w-full text-xs text-ink-600 file:me-3 file:rounded-lg file:border-0 file:bg-ink-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-ink-700">
                                <button type="button" wire:click="uploadAttachment" wire:loading.attr="disabled" wire:target="attachment,uploadAttachment" class="{{ $btnGhost }} shrink-0">{{ __('task.ui.upload') }}</button>
                            </div>
                            <p class="mt-1 text-[11px] text-ink-400">{{ __('task.ui.attachment_hint', ['max' => fa_num($open['maxMb'])]) }}</p>
                            @error('attachment') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        @endif
                    </section>

                    {{-- نظرها --}}
                    <section>
                        <h4 class="mb-2 text-xs font-bold text-ink-700">{{ __('task.ui.comments') }} <span class="text-ink-400">({{ fa_num($task->comments->count()) }})</span></h4>
                        @if ($task->comments->isNotEmpty())
                            <ul class="mb-3 space-y-2">
                                @foreach ($task->comments as $comment)
                                    <li class="rounded-xl bg-ink-50 px-3 py-2" wire:key="cm-{{ $comment->id }}">
                                        <div class="mb-1 flex items-center justify-between gap-2 text-[11px] text-ink-400">
                                            <span class="font-semibold text-ink-600">{{ $comment->user?->name ?? '—' }}</span>
                                            <span class="flex items-center gap-2">
                                                {{ jalali_long($comment->created_at) }}
                                                @if (auth()->user()->can('deleteComment', [$task, $comment]))
                                                    <button type="button" wire:click="deleteComment({{ $comment->id }})" wire:confirm="{{ __('task.ui.confirm_delete_comment') }}" class="text-signal-600 hover:underline" aria-label="{{ __('ui.delete') }}">✕</button>
                                                @endif
                                            </span>
                                        </div>
                                        <p class="whitespace-pre-line text-sm leading-6 text-ink-800">{{ $comment->body }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if ($open['canComment'])
                            <textarea wire:model="commentBody" rows="2" maxlength="2000" placeholder="{{ __('task.ui.comment_placeholder') }}" class="{{ $input }}"></textarea>
                            <button type="button" wire:click="addComment" class="{{ $btnGhost }} mt-2">{{ __('task.ui.send_comment') }}</button>
                        @endif
                    </section>

                    {{-- تاریخچه‌ی اقدامات: ثبت، تغییر وضعیت، ویرایش … با تاریخ و شخص --}}
                    <x-activity-log :subject="$task" :limit="12" class="rounded-2xl bg-ink-50/60 p-3" />
                </div>
            </aside>
        </div>
    @endif

    {{-- ========================================== فرم وظیفه (مودال؛ همیشه در دید) --}}
    @if ($showForm)
        <div class="modal-backdrop" wire:key="task-form-{{ $editingId ?? 'new' }}-{{ $formParentId ?? 0 }}"
             x-data x-on:keydown.escape.window="$wire.cancelForm()">
            <div class="absolute inset-0" wire:click="cancelForm" aria-hidden="true"></div>
            <div class="modal-panel relative" role="dialog" aria-modal="true" aria-label="{{ $editingId ? __('task.ui.edit') : __('task.add') }}">
                <header class="flex items-center justify-between gap-3 border-b border-ink-100 px-5 py-4">
                    <h3 class="text-sm font-bold text-ink-900">
                        {{ $editingId ? __('task.ui.edit') : ($formParentId ? __('task.ui.add_subtask') : __('task.add')) }}
                    </h3>
                    <button type="button" wire:click="cancelForm" class="rounded-lg p-1.5 text-ink-500 hover:bg-ink-100" aria-label="{{ __('ui.close') }}">✕</button>
                </header>

                <div class="flex-1 overflow-y-auto px-5 py-4">
                    @if ($error)
                        <p class="mb-3 rounded-xl bg-signal-50 px-3 py-2 text-xs font-semibold text-signal-600" role="alert">{{ $error }}</p>
                    @endif
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="t-title" class="{{ $label }}">{{ __('task.ui.f_title') }}</label>
                    <input id="t-title" type="text" wire:model="title" maxlength="200" class="{{ $input }}">
                    @error('title') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="t-desc" class="{{ $label }}">{{ __('task.ui.f_description') }}</label>
                    <textarea id="t-desc" wire:model="description" rows="3" class="{{ $input }}"></textarea>
                    @error('description') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="t-committee" class="{{ $label }}">{{ __('task.ui.f_committee') }}</label>
                    <select id="t-committee" wire:model="committeeId" class="{{ $input }}">
                        <option value="">{{ __('task.ui.no_committee') }}</option>
                        @foreach ($formCommittees as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                    @error('committeeId') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="t-assignee" class="{{ $label }}">{{ __('task.ui.f_assignee') }}</label>
                    <select id="t-assignee" wire:model="assigneeId" class="{{ $input }}">
                        <option value="">{{ __('task.ui.unassigned') }}</option>
                        @foreach ($members as $m)
                            <option value="{{ $m->id }}">{{ $m->name ?? phone($m->phone) }}</option>
                        @endforeach
                    </select>
                    @error('assigneeId') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="t-phase" class="{{ $label }}">{{ __('task.ui.f_phase') }}</label>
                    <select id="t-phase" wire:model="phase" class="{{ $input }}">
                        @foreach ($phases as $p)
                            <option value="{{ $p->value }}">{{ $p->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="t-module" class="{{ $label }}">{{ __('task.ui.f_module') }}</label>
                    <select id="t-module" wire:model="module" class="{{ $input }}">
                        @foreach ($modules as $mo)
                            <option value="{{ $mo->value }}">{{ $mo->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="t-priority" class="{{ $label }}">{{ __('task.ui.f_priority') }}</label>
                    <select id="t-priority" wire:model="priority" class="{{ $input }}">
                        @foreach ($priorities as $pr)
                            <option value="{{ $pr->value }}">{{ $pr->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="t-hours" class="{{ $label }}">{{ __('task.ui.f_hours') }}</label>
                    <input id="t-hours" type="text" inputmode="decimal" dir="ltr" wire:model="hours" class="{{ $input }}">
                    @error('hours') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="t-start" class="{{ $label }}">{{ __('task.ui.f_start') }}</label>
                    <x-jalali-picker id="t-start" wire:model="startAt" />
                    @error('startAt') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="t-due" class="{{ $label }}">{{ __('task.ui.f_due') }}</label>
                    <x-jalali-picker id="t-due" wire:model="dueAt" />
                    @error('dueAt') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>
            </div>

                </div>

                <footer class="flex gap-2 border-t border-ink-100 bg-white px-5 py-3">
                    <button type="button" wire:click="save" wire:loading.attr="disabled" class="{{ $btnPrimary }}">{{ __('ui.save') }}</button>
                    <button type="button" wire:click="cancelForm" class="{{ $btnGhost }}">{{ __('ui.cancel') }}</button>
                </footer>
            </div>
        </div>
    @endif
</div>
