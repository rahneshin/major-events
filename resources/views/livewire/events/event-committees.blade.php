<div class="space-y-5">
    @section('page_title', $event->title)

    <div class="flex flex-col gap-3 rounded-2xl bg-gradient-to-l from-indigo-600 via-violet-600 to-fuchsia-600 p-5 text-white shadow-lg shadow-indigo-500/20 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ route('events.show', $event) }}" class="text-xs text-indigo-200 hover:text-white">← {{ __('ui.back') }}</a>
            <h2 class="mt-1 text-xl font-bold">{{ __('committee.title') }}</h2>
            <p class="text-xs text-indigo-100">{{ $event->title }}</p>
        </div>
        @if ($canManage)
            <div class="flex gap-2">
                <button type="button" wire:click="applyTemplate"
                    class="rounded-xl border border-white/30 bg-white/10 px-4 py-2 text-xs font-semibold text-white backdrop-blur transition hover:bg-white/20">
                    {{ __('committee.apply_template') }}
                </button>
                <button type="button" wire:click="create"
                    class="rounded-xl bg-white px-4 py-2 text-xs font-bold text-indigo-700 shadow-md transition hover:bg-indigo-50">
                    + {{ __('committee.add') }}
                </button>
            </div>
        @endif
    </div>

    @if ($error)
        <p class="rounded-xl bg-rose-50 px-4 py-2.5 text-xs font-medium text-rose-700">{{ $error }}</p>
    @endif

    @if (session('success'))
        <p class="rounded-xl bg-emerald-50 px-4 py-2.5 text-xs font-medium text-emerald-700">{{ session('success') }}</p>
    @endif

    @if ($showForm)
        <div class="glass rounded-2xl p-5">
            <h3 class="mb-4 text-sm font-bold text-slate-900">
                {{ $editingId ? __('committee.edit') : __('committee.add') }}
            </h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('committee.fields.name') }}</label>
                    <input type="text" wire:model="name"
                        class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                    @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('committee.fields.type') }}</label>
                    <select wire:model="type" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                        @foreach ($types as $t)
                            <option value="{{ $t->value }}">{{ $t->label() }}</option>
                        @endforeach
                    </select>
                    @error('type') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('committee.fields.head') }}</label>
                    <select wire:model="head_user_id" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                        <option value="">{{ __('committee.no_head') }}</option>
                        @foreach ($eventMembers as $m)
                            <option value="{{ $m->id }}">{{ $m->name ?? phone($m->phone) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('committee.fields.description') }}</label>
                    <textarea wire:model="description" rows="2"
                        class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"></textarea>
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="button" wire:click="save"
                    class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-indigo-500/25 transition hover:bg-indigo-700">
                    {{ __('ui.save') }}
                </button>
                <button type="button" wire:click="cancel"
                    class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                    {{ __('ui.cancel') }}
                </button>
            </div>
        </div>
    @endif

    @if ($committees->isEmpty())
        <div class="glass rounded-2xl px-6 py-14 text-center">
            <p class="text-sm font-medium text-slate-500">{{ __('committee.empty') }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ __('committee.empty_hint') }}</p>
        </div>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($committees as $committee)
                <div class="glass rounded-2xl p-5" wire:key="committee-{{ $committee->id }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="h-10 w-1.5 rounded-full" style="background: {{ $committee->color ?? $committee->type->color() }}"></span>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">{{ $committee->name }}</h3>
                                <p class="text-xs text-slate-400">{{ $committee->type->label() }}</p>
                            </div>
                        </div>
                        @if ($canManage || $committee->isHead(auth()->user()))
                            <div class="flex gap-1.5">
                                <button type="button" wire:click="edit({{ $committee->id }})"
                                    class="rounded-lg border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-50">
                                    {{ __('ui.edit') }}
                                </button>
                                @if ($canManage)
                                    <button type="button" wire:click="delete({{ $committee->id }})" wire:confirm="{{ __('committee.confirm_delete') }}"
                                        class="rounded-lg border border-rose-200 bg-rose-50 px-2 py-1 text-[11px] font-semibold text-rose-600 hover:bg-rose-100">
                                        {{ __('ui.delete') }}
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if ($committee->description)
                        <p class="mt-3 text-xs leading-relaxed text-slate-500">{{ $committee->description }}</p>
                    @endif

                    @if ($committee->head)
                        <p class="mt-3 text-xs text-slate-500">
                            {{ __('committee.head') }}:
                            <span class="font-semibold text-slate-700">{{ $committee->head->name ?? phone($committee->head->phone) }}</span>
                        </p>
                    @endif

                    <div class="mt-3 border-t border-slate-100 pt-3">
                        <p class="mb-2 text-[11px] font-semibold text-slate-400">{{ __('committee.members') }} ({{ $committee->members->count() }})</p>
                        @if ($committee->members->isEmpty())
                            <p class="text-xs text-slate-400">{{ __('committee.no_members') }}</p>
                        @else
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($committee->members as $m)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-700" wire:key="cm-{{ $committee->id }}-{{ $m->id }}">
                                        {{ $m->name ?? phone($m->phone) }}
                                        @if ((int) $committee->head_user_id === (int) $m->id)
                                            <span class="text-[9px] font-bold text-indigo-600">★</span>
                                        @endif
                                        @if ($canManage || $committee->isHead(auth()->user()))
                                            <button type="button" wire:click="removeMember({{ $committee->id }}, {{ $m->id }})"
                                                class="text-slate-400 hover:text-rose-500">×</button>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        @if ($canManage || $committee->isHead(auth()->user()))
                            <div class="mt-3 flex gap-2">
                                <select wire:model="memberPicks.{{ $committee->id }}"
                                    class="flex-1 rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
                                    <option value="">{{ __('committee.select_member') }}</option>
                                    @foreach ($eventMembers as $m)
                                        @continue($committee->hasMember($m))
                                        <option value="{{ $m->id }}">{{ $m->name ?? phone($m->phone) }}</option>
                                    @endforeach
                                </select>
                                <button type="button" wire:click="addMember({{ $committee->id }})"
                                    class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-indigo-700">
                                    +
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
