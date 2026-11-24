<div>
    @section('page_title', $event ? __('event.edit_title') : __('event.create_title'))

    <div class="mb-6">
        <h2 class="text-xl font-bold text-ink-900">{{ $event ? __('event.edit_title') : __('event.create_title') }}</h2>
        <p class="mt-1 text-sm text-ink-500">{{ __('event.form_hint') }}</p>
    </div>

    {{-- Steps indicator --}}
    <div class="mb-8 flex items-center gap-2">
        @foreach ([1 => __('event.step_basic'), 2 => __('event.step_goals'), 3 => __('event.step_place'), 4 => __('event.step_summary')] as $n => $label)
            <button type="button" wire:click="$set('step', {{ $n }})"
                class="flex flex-1 flex-col items-center gap-1 rounded-xl px-2 py-2 text-center transition
                {{ $step === $n ? 'bg-pool-50 text-pool-700' : ($step > $n ? 'text-success-600' : 'text-ink-400') }}">
                <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold
                    {{ $step === $n ? 'bg-pool-600 text-white' : ($step > $n ? 'bg-success-100 text-success-700' : 'bg-ink-100') }}">
                    {{ $step > $n ? '✓' : $n }}
                </span>
                <span class="hidden text-[10px] font-medium sm:block">{{ $label }}</span>
            </button>
            @if ($n < 4)
                <div class="h-px w-4 shrink-0 bg-ink-200 sm:w-8"></div>
            @endif
        @endforeach
    </div>

    <div class="glass rounded-3xl p-5 sm:p-7">
        {{-- Step 1: Basic --}}
        @if ($step === 1)
            <div class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-ink-700">{{ __('event.fields.title') }} *</label>
                    <input type="text" wire:model="title" class="w-full rounded-xl border border-ink-200 px-4 py-2.5 text-sm focus:border-pool-400 focus:outline-none focus:ring-2 focus:ring-pool-100" placeholder="مثلاً جام فوتبال محلات">
                    @error('title') <p class="mt-1 text-xs text-signal-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-ink-700">{{ __('event.fields.sport_type') }}</label>
                        <input type="text" wire:model="sport_type" class="w-full rounded-xl border border-ink-200 px-4 py-2.5 text-sm focus:border-pool-400 focus:outline-none focus:ring-2 focus:ring-pool-100" placeholder="فوتبال، دوومیدانی، ...">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-ink-700">{{ __('event.fields.event_type') }}</label>
                        <select wire:model="event_type" class="w-full rounded-xl border border-ink-200 px-4 py-2.5 text-sm focus:border-pool-400 focus:outline-none focus:ring-2 focus:ring-pool-100">
                            @foreach ($eventTypes as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-ink-700">{{ __('event.fields.scale') }}</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($scales as $s)
                            <button type="button" wire:click="$set('scale', '{{ $s->value }}')"
                                class="rounded-xl px-4 py-2 text-sm font-medium transition
                                {{ $scale === $s->value ? 'bg-pool-600 text-white shadow-md shadow-pool-500/25' : 'bg-ink-50 text-ink-600 hover:bg-ink-100' }}">
                                {{ $s->label() }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Step 2: Goals --}}
        @if ($step === 2)
            <div class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-ink-700">{{ __('event.fields.main_goal_type') }}</label>
                    <select wire:model="main_goal_type" class="w-full rounded-xl border border-ink-200 px-4 py-2.5 text-sm focus:border-pool-400 focus:outline-none focus:ring-2 focus:ring-pool-100">
                        <option value="">—</option>
                        @foreach ($goalTypes as $g)
                            <option value="{{ $g->value }}">{{ $g->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-ink-700">{{ __('event.fields.description') }}</label>
                    <textarea wire:model="description" rows="3" class="w-full rounded-xl border border-ink-200 px-4 py-2.5 text-sm focus:border-pool-400 focus:outline-none focus:ring-2 focus:ring-pool-100"></textarea>
                </div>

                <div class="border-t border-ink-100 pt-4">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-sm font-semibold text-ink-800">{{ __('event.goals') }}</p>
                        <button type="button" wire:click="addGoal" class="text-xs font-semibold text-pool-600 hover:text-pool-700">+ {{ __('event.add_goal') }}</button>
                    </div>
                    @foreach ($goals as $i => $goal)
                        <div class="mb-3 rounded-xl border border-ink-100 bg-ink-50/50 p-3" wire:key="goal-{{ $i }}">
                            <div class="mb-2 flex items-center justify-between">
                                <select wire:model="goals.{{ $i }}.kind" class="rounded-lg border border-ink-200 bg-white px-2 py-1 text-xs">
                                    <option value="main">{{ __('event.goal_main') }}</option>
                                    <option value="secondary">{{ __('event.goal_secondary') }}</option>
                                </select>
                                @if (count($goals) > 1)
                                    <button type="button" wire:click="removeGoal({{ $i }})" class="text-xs text-signal-500">{{ __('ui.delete') }}</button>
                                @endif
                            </div>
                            <input type="text" wire:model="goals.{{ $i }}.title" placeholder="{{ __('event.goal_title') }}" class="mb-2 w-full rounded-lg border border-ink-200 px-3 py-2 text-sm">
                            <div class="grid gap-2 sm:grid-cols-2">
                                <input type="text" wire:model="goals.{{ $i }}.target_value" placeholder="{{ __('event.goal_target') }}" class="rounded-lg border border-ink-200 px-3 py-2 text-sm">
                                <input type="text" wire:model="goals.{{ $i }}.unit" placeholder="{{ __('event.goal_unit') }}" class="rounded-lg border border-ink-200 px-3 py-2 text-sm">
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Step 3: Place & time --}}
        @if ($step === 3)
            <div class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-ink-700">{{ __('event.fields.start_date') }}</label>
                        <x-jalali-picker id="start_date" wire:model="start_date_jalali" />
                        @error('start_date_jalali') <p class="mt-1 text-xs text-signal-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-ink-700">{{ __('event.fields.end_date') }}</label>
                        <x-jalali-picker id="end_date" wire:model="end_date_jalali" />
                        @error('end_date_jalali') <p class="mt-1 text-xs text-signal-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-ink-700">{{ __('event.fields.venue') }}</label>
                    <input type="text" wire:model="venue_name" class="w-full rounded-xl border border-ink-200 px-4 py-2.5 text-sm focus:border-pool-400 focus:outline-none focus:ring-2 focus:ring-pool-100">
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-ink-700">{{ __('event.fields.city') }}</label>
                        <input type="text" wire:model="city" class="w-full rounded-xl border border-ink-200 px-4 py-2.5 text-sm focus:border-pool-400 focus:outline-none focus:ring-2 focus:ring-pool-100">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-ink-700">{{ __('event.fields.province') }}</label>
                        <input type="text" wire:model="province" class="w-full rounded-xl border border-ink-200 px-4 py-2.5 text-sm focus:border-pool-400 focus:outline-none focus:ring-2 focus:ring-pool-100">
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-ink-700">{{ __('event.fields.participants') }}</label>
                        <input type="number" wire:model="expected_participants" min="0" class="w-full rounded-xl border border-ink-200 px-4 py-2.5 text-sm focus:border-pool-400 focus:outline-none focus:ring-2 focus:ring-pool-100">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-ink-700">{{ __('event.fields.spectators') }}</label>
                        <input type="number" wire:model="expected_spectators" min="0" class="w-full rounded-xl border border-ink-200 px-4 py-2.5 text-sm focus:border-pool-400 focus:outline-none focus:ring-2 focus:ring-pool-100">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-ink-700">{{ __('event.fields.budget') }}</label>
                        <input type="number" wire:model="budget_estimate" min="0" class="w-full rounded-xl border border-ink-200 px-4 py-2.5 text-sm focus:border-pool-400 focus:outline-none focus:ring-2 focus:ring-pool-100" placeholder="ریال">
                    </div>
                </div>
            </div>
        @endif

        {{-- Step 4: Summary --}}
        @if ($step === 4)
            <div class="space-y-4 text-sm">
                <div class="rounded-2xl bg-ink-50 p-4">
                    <p class="text-xs font-medium text-ink-400">{{ __('event.fields.title') }}</p>
                    <p class="mt-0.5 text-base font-bold text-ink-900">{{ $title }}</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-ink-100 p-3">
                        <p class="text-xs text-ink-400">{{ __('event.fields.event_type') }}</p>
                        <p class="font-medium">{{ \App\Enums\EventType::tryFrom($event_type)?->label() }}</p>
                    </div>
                    <div class="rounded-xl border border-ink-100 p-3">
                        <p class="text-xs text-ink-400">{{ __('event.fields.scale') }}</p>
                        <p class="font-medium">{{ \App\Enums\EventScale::tryFrom($scale)?->label() }}</p>
                    </div>
                    <div class="rounded-xl border border-ink-100 p-3">
                        <p class="text-xs text-ink-400">{{ __('event.fields.start_date') }}</p>
                        <p class="font-medium">{{ $start_date_jalali ? jalali_long(\App\Support\Jalali::parse($start_date_jalali)) : '—' }}</p>
                    </div>
                    <div class="rounded-xl border border-ink-100 p-3">
                        <p class="text-xs text-ink-400">{{ __('event.fields.city') }}</p>
                        <p class="font-medium">{{ $city ?: '—' }}</p>
                    </div>
                </div>
                @if (collect($goals)->where('title', '!=', '')->isNotEmpty())
                    <div>
                        <p class="mb-2 text-xs font-medium text-ink-400">{{ __('event.goals') }}</p>
                        <ul class="space-y-1">
                            @foreach ($goals as $g)
                                @if ($g['title'])
                                    <li class="rounded-lg bg-pool-50 px-3 py-2 text-pool-800">{{ $g['title'] }}</li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        {{-- Nav buttons --}}
        <div class="mt-8 flex items-center justify-between border-t border-ink-100 pt-5">
            <button type="button" wire:click="prevStep" @if($step === 1) disabled @endif
                class="btn-ghost text-sm disabled:opacity-40">
                {{ __('ui.previous') }}
            </button>

            @if ($step < 4)
                <button type="button" wire:click="nextStep" class="btn-primary text-sm">
                    {{ __('ui.next') }}
                </button>
            @else
                <button type="button" wire:click="save" class="btn-primary text-sm" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ $event ? __('ui.save') : __('event.create') }}</span>
                    <span wire:loading>{{ __('ui.loading') }}</span>
                </button>
            @endif
        </div>
    </div>
</div>
