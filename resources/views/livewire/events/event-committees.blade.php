<div class="space-y-5">
    @if ($error)
        <p class="rounded-xl bg-signal-50 px-4 py-2.5 text-xs font-medium text-signal-700">{{ $error }}</p>
    @endif
    @if ($notice)
        <p class="rounded-xl bg-success-50 px-4 py-2.5 text-xs font-medium text-success-700">{{ $notice }}</p>
    @endif

    @if ($selected)
        {{-- Committee detail --}}
        <div class="glass rounded-2xl p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="h-10 w-1.5 rounded-full" style="background: {{ $selected->color }}"></span>
                    <div>
                        <h3 class="text-base font-bold text-ink-900">{{ $selected->name }}</h3>
                        <p class="text-xs text-ink-400">
                            {{ $selected->type->label() }}
                            · {{ __('committee.head') }}: {{ $selected->head?->name ?? $selected->head?->phone ?? '—' }}
                        </p>
                    </div>
                </div>
                <button type="button" wire:click="select(null)"
                    class="rounded-xl border border-ink-200 bg-white px-4 py-2 text-xs font-semibold text-ink-600 shadow-sm transition hover:bg-ink-50">
                    {{ __('committee.back_to_list') }}
                </button>
            </div>

            <div class="mb-5 rounded-xl bg-ink-50 p-4">
                <h4 class="mb-2 text-xs font-bold text-ink-700">{{ __('committee.responsibilities') }}</h4>
                @if ($canEditDescription)
                    <textarea wire:model="descriptionDraft" rows="3"
                        class="w-full rounded-xl border border-ink-200 px-3 py-2 text-sm focus:border-pool-400 focus:outline-none focus:ring-2 focus:ring-pool-100"></textarea>
                    @error('descriptionDraft') <p class="mt-1 text-xs text-signal-600">{{ $message }}</p> @enderror
                    <button type="button" wire:click="saveDescription"
                        class="mt-2 rounded-lg bg-pool-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-pool-700">
                        {{ __('ui.save') }}
                    </button>
                @else
                    <p class="text-sm leading-relaxed text-ink-600">{{ $selected->description ?: '—' }}</p>
                @endif
            </div>

            @if ($canManageSelected)
                <div class="mb-5 grid gap-3 lg:grid-cols-2">
                    <div class="flex gap-2">
                        <select wire:model="memberUserId" class="flex-1 rounded-xl border border-ink-200 px-3 py-2.5 text-sm">
                            <option value="">{{ __('committee.pick_event_member') }}</option>
                            @foreach ($candidates as $c)
                                <option value="{{ $c->id }}">{{ $c->name ?? phone($c->phone) }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="addMember"
                            class="rounded-xl bg-pool-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-pool-700">
                            {{ __('committee.add_member') }}
                        </button>
                    </div>
                    <div class="flex gap-2">
                        <input type="text" wire:model="memberPhone" dir="ltr" placeholder="0912..."
                            class="flex-1 rounded-xl border border-ink-200 px-4 py-2.5 text-sm focus:border-pool-400 focus:outline-none focus:ring-2 focus:ring-pool-100">
                        <button type="button" wire:click="addByPhone"
                            class="rounded-xl border border-pool-200 bg-pool-50 px-4 py-2.5 text-sm font-bold text-pool-700 transition hover:bg-pool-100">
                            {{ __('committee.add_by_phone') }}
                        </button>
                    </div>
                </div>
            @endif

            <h4 class="mb-2 text-xs font-bold text-ink-700">
                {{ __('committee.members') }} <span class="text-ink-400">({{ fa_num($selectedMembers->count()) }})</span>
            </h4>
            @if ($selectedMembers->isEmpty())
                <p class="text-sm text-ink-500">{{ __('committee.no_members') }}</p>
            @else
                <ul class="divide-y divide-ink-100">
                    @foreach ($selectedMembers as $m)
                        <li class="flex items-center justify-between gap-3 py-3" wire:key="cm-{{ $selected->id }}-{{ $m->id }}">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-pool-100 text-sm font-bold text-pool-700">
                                    {{ mb_substr($m->name ?? '؟', 0, 1) }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-ink-900">{{ $m->name ?? phone($m->phone) }}</p>
                                    <p class="text-xs text-ink-400" dir="ltr">{{ phone($m->phone) }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($m->pivot->role === 'head')
                                    <span class="rounded-full bg-flag-50 px-2.5 py-1 text-[11px] font-semibold text-flag-700">{{ __('committee.head') }}</span>
                                @elseif ($canManageEvent)
                                    <button type="button" wire:click="makeHead({{ $m->id }})"
                                        class="rounded-lg border border-ink-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-ink-600 transition hover:bg-flag-50 hover:text-flag-700">
                                        {{ __('committee.make_head') }}
                                    </button>
                                @endif
                                @if ($canManageSelected)
                                    <button type="button" wire:click="removeMember({{ $m->id }})" wire:confirm="{{ __('committee.confirm_remove_member') }}"
                                        class="rounded-lg border border-signal-200 bg-signal-50 px-2.5 py-1.5 text-xs font-semibold text-signal-600 transition hover:bg-signal-100">
                                        {{ __('ui.delete') }}
                                    </button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @else
        {{-- List --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-ink-500">
                {{ fa_num($committees->count()) }}
                @if ($limit !== null) / {{ fa_num($limit) }} @endif
                {{ __('committee.count_label') }}
            </p>
            @if ($canCreate)
                <div class="flex gap-2">
                    <button type="button" wire:click="applyTemplate"
                        class="rounded-xl border border-pool-200 bg-pool-50 px-4 py-2 text-xs font-bold text-pool-700 transition hover:bg-pool-100">
                        {{ __('committee.apply_template') }}
                    </button>
                    <button type="button" wire:click="openCreate"
                        class="rounded-xl bg-pool-600 px-4 py-2 text-xs font-bold text-white shadow-md shadow-pool-500/25 transition hover:bg-pool-700">
                        {{ __('committee.add') }}
                    </button>
                </div>
            @endif
        </div>

        @if ($showForm)
            <div class="glass rounded-2xl p-5">
                <h3 class="mb-3 text-sm font-bold text-ink-900">{{ $editingId ? __('committee.edit') : __('committee.add') }}</h3>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs text-ink-500">{{ __('committee.fields.type') }}</label>
                        <select wire:model.live="type" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm">
                            @foreach ($types as $t)
                                <option value="{{ $t->value }}">{{ $t->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-ink-500">{{ __('committee.fields.name') }}</label>
                        <input type="text" wire:model="name"
                            class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm focus:border-pool-400 focus:outline-none focus:ring-2 focus:ring-pool-100">
                        @error('name') <p class="mt-1 text-xs text-signal-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs text-ink-500">{{ __('committee.fields.head') }}</label>
                        <select wire:model="headUserId" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm">
                            <option value="">{{ __('committee.no_head') }}</option>
                            @foreach ($eventMembers as $m)
                                <option value="{{ $m->id }}">{{ $m->name ?? phone($m->phone) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs text-ink-500">{{ __('committee.fields.description') }}</label>
                        <textarea wire:model="description" rows="2"
                            class="w-full rounded-xl border border-ink-200 px-3 py-2 text-sm focus:border-pool-400 focus:outline-none focus:ring-2 focus:ring-pool-100"></textarea>
                        @error('description') <p class="mt-1 text-xs text-signal-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-ink-500">{{ __('committee.fields.color') }}</label>
                        <input type="color" wire:model="color" class="h-10 w-16 cursor-pointer rounded-lg border border-ink-200">
                        @error('color') <p class="mt-1 text-xs text-signal-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <button type="button" wire:click="save"
                        class="rounded-xl bg-pool-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-pool-500/25 transition hover:bg-pool-700">
                        {{ __('ui.save') }}
                    </button>
                    <button type="button" wire:click="cancelForm"
                        class="rounded-xl border border-ink-200 bg-white px-5 py-2.5 text-sm font-semibold text-ink-600 transition hover:bg-ink-50">
                        {{ __('ui.cancel') }}
                    </button>
                </div>
            </div>
        @endif

        @if ($committees->isEmpty())
            <div class="glass rounded-2xl px-6 py-14 text-center">
                <p class="text-sm font-medium text-ink-500">{{ __('committee.empty_title') }}</p>
                <p class="mt-1 text-xs text-ink-400">{{ __('committee.empty_desc') }}</p>
            </div>
        @else
            <ul class="space-y-3">
                @foreach ($committees as $committee)
                    <li class="glass flex flex-col gap-3 rounded-2xl p-4 sm:flex-row sm:items-center sm:justify-between" wire:key="committee-{{ $committee->id }}">
                        <button type="button" wire:click="select({{ $committee->id }})" class="flex flex-1 items-center gap-3 text-start">
                            <span class="h-10 w-1.5 shrink-0 rounded-full" style="background: {{ $committee->color }}"></span>
                            <span>
                                <span class="block text-sm font-bold text-ink-900">{{ $committee->name }}</span>
                                <span class="block text-xs text-ink-400">
                                    {{ $committee->type->label() }}
                                    · {{ fa_num($committee->members_count) }} {{ __('committee.member_unit') }}
                                    · {{ __('committee.head') }}: {{ $committee->head?->name ?? '—' }}
                                </span>
                            </span>
                        </button>
                        @if ($canManageEvent)
                            <div class="flex items-center gap-1.5">
                                <button type="button" wire:click="move({{ $committee->id }}, 'up')" @disabled($loop->first)
                                    class="rounded-lg border border-ink-200 bg-white px-2.5 py-1.5 text-xs text-ink-600 transition hover:bg-ink-50 disabled:opacity-30">↑</button>
                                <button type="button" wire:click="move({{ $committee->id }}, 'down')" @disabled($loop->last)
                                    class="rounded-lg border border-ink-200 bg-white px-2.5 py-1.5 text-xs text-ink-600 transition hover:bg-ink-50 disabled:opacity-30">↓</button>
                                <button type="button" wire:click="edit({{ $committee->id }})"
                                    class="rounded-lg border border-ink-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-ink-600 transition hover:bg-ink-50">
                                    {{ __('ui.edit') }}
                                </button>
                                <button type="button" wire:click="delete({{ $committee->id }})" wire:confirm="{{ __('committee.confirm_delete') }}"
                                    class="rounded-lg border border-signal-200 bg-signal-50 px-2.5 py-1.5 text-xs font-semibold text-signal-600 transition hover:bg-signal-100">
                                    {{ __('ui.delete') }}
                                </button>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</div>
