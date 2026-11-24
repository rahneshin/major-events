<section class="rounded-2xl border border-ink-100 bg-white p-5">
    <h2 class="mb-4 text-sm font-bold text-ink-900">{{ __('hr.my_title') }}</h2>

    @if ($events->isEmpty() && $tasks->isEmpty())
        <p class="text-sm text-ink-500">{{ __('hr.my_empty') }}</p>
    @else
        <div class="grid gap-6 lg:grid-cols-2">
            {{-- پست‌ها --}}
            <div>
                <h3 class="mb-2 text-xs font-bold text-ink-500">{{ __('hr.my_posts') }}</h3>
                <ul class="space-y-3">
                    @foreach ($events as $event)
                        @php $role = $event->roleOf(auth()->user()); @endphp
                        <li class="rounded-xl bg-ink-50 px-3 py-2.5" wire:key="my-event-{{ $event->id }}">
                            <div class="flex items-center justify-between gap-2">
                                <a href="{{ route('events.show', $event) }}" wire:navigate class="text-sm font-semibold text-ink-900 hover:underline">{{ $event->title }}</a>
                                <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-ink-600">{{ $role?->label() }}</span>
                            </div>
                            @forelse ($event->committees as $committee)
                                <p class="mt-1 text-xs text-ink-600">
                                    {{ $committee->name }}
                                    <span class="text-ink-400">·</span>
                                    <span class="font-semibold">{{ $committee->members->first()?->pivot->post ?: __('hr.my_no_post') }}</span>
                                    @if ($committee->isHead(auth()->user()))
                                        <span class="ms-1 rounded-full bg-flag-100 px-1.5 py-0.5 text-[10px] font-bold text-flag-700">{{ __('hr.head') }}</span>
                                    @endif
                                </p>
                            @empty
                                <p class="mt-1 text-xs text-ink-400">{{ __('hr.no_assignment') }}</p>
                            @endforelse
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- وظایف باز --}}
            <div>
                <h3 class="mb-2 text-xs font-bold text-ink-500">{{ __('hr.my_tasks') }}</h3>
                @if ($tasks->isEmpty())
                    <p class="text-xs text-ink-400">—</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($tasks as $task)
                            <li class="rounded-xl bg-ink-50 px-3 py-2.5" wire:key="my-task-{{ $task->id }}">
                                <a href="{{ route('events.show', ['event' => $task->event, 'tab' => 'tasks']) }}" wire:navigate class="text-sm font-semibold text-ink-900 hover:underline">{{ $task->title }}</a>
                                <p class="mt-0.5 text-xs text-ink-500">
                                    {{ $task->event->title }}@if ($task->committee) · {{ $task->committee->name }}@endif
                                    · {{ $task->status->label() }}
                                    @if ($task->due_at)
                                        · @if ($task->due_at->isPast())<span class="font-semibold text-signal-600">{{ __('hr.my_overdue') }}</span>@else{{ __('hr.my_due', ['date' => jalali_long($task->due_at)]) }}@endif
                                    @endif
                                </p>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('tasks.mine') }}" wire:navigate class="mt-2 inline-block text-xs font-semibold text-ink-600 hover:underline">
                        @if ($moreTasks > 0){{ __('hr.my_tasks_more', ['count' => fa_num($moreTasks)]) }} · @endif{{ __('hr.my_all_tasks') }}
                    </a>
                @endif
            </div>
        </div>
    @endif
</section>
