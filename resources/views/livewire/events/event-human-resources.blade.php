<div class="space-y-5">
    @if ($error)
        <p class="rounded-xl bg-rose-50 px-4 py-2.5 text-sm text-rose-700">{{ $error }}</p>
    @endif
    @if ($notice)
        <p class="rounded-xl bg-emerald-50 px-4 py-2.5 text-sm text-emerald-700">{{ $notice }}</p>
    @endif

    @if ($canManage)
        <div class="glass rounded-2xl p-5">
            <h3 class="text-sm font-bold text-slate-900">{{ __('hr.add_title') }}</h3>
            <p class="mb-4 mt-1 text-xs text-slate-500">{{ __('hr.add_hint') }}</p>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <label class="block text-xs font-semibold text-slate-600">{{ __('hr.phone') }}
                    <input type="text" wire:model="phone" dir="ltr" placeholder="0912..."
                        class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                    @error('phone') <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
                </label>
                <label class="block text-xs font-semibold text-slate-600">{{ __('hr.name') }}
                    <input type="text" wire:model="name"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                </label>
                <label class="block text-xs font-semibold text-slate-600">{{ __('hr.event_role') }}
                    <select wire:model="eventRole" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                        @foreach ($assignableRoles as $r)
                            <option value="{{ $r->value }}">{{ $r->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-xs font-semibold text-slate-600">{{ __('hr.committee') }}
                    <select wire:model="committeeId" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                        <option value="">{{ __('hr.no_committee') }}</option>
                        @foreach ($committees as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-xs font-semibold text-slate-600">{{ __('hr.post') }}
                    <input type="text" wire:model="post" placeholder="{{ __('hr.post_placeholder') }}"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                </label>
                <div class="flex items-end justify-between gap-3">
                    <label class="flex items-center gap-2 pb-2.5 text-xs font-semibold text-slate-600">
                        <input type="checkbox" wire:model="asHead" class="rounded border-slate-300">
                        {{ __('hr.as_head') }}
                    </label>
                    <button type="button" wire:click="enroll" wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-indigo-500/25 transition hover:bg-indigo-700">
                        {{ __('hr.enroll') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="glass rounded-2xl p-5">
        <h3 class="mb-4 text-sm font-bold text-slate-900">
            {{ __('hr.list_title') }}
            <span class="ms-1 text-slate-400">({{ fa_num($members->count()) }})</span>
        </h3>

        @if ($members->isEmpty())
            <p class="text-sm text-slate-500">{{ __('hr.empty') }}</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($members as $member)
                    @php
                        $role = \App\Enums\EventRole::tryFrom($member->pivot->role);
                        $mine = $assignments[$member->id] ?? [];
                        $free = $committees->reject(fn ($c) => collect($mine)->contains(fn ($a) => $a['committee']->id === $c->id));
                        $isOwner = $role === \App\Enums\EventRole::EventOwner;
                    @endphp
                    <li class="space-y-3 py-4" wire:key="hr-member-{{ $member->id }}">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-700">
                                    {{ mb_substr($member->name ?? '؟', 0, 1) }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $member->name ?? phone($member->phone) }}</p>
                                    <p class="text-xs text-slate-400" dir="ltr">{{ phone($member->phone) }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                @if ($canManage && ! $isOwner)
                                    <select wire:change="changeRole({{ $member->id }}, $event.target.value)"
                                        class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-medium">
                                        @foreach ($assignableRoles as $r)
                                            <option value="{{ $r->value }}" @selected($role === $r)>{{ $r->label() }}</option>
                                        @endforeach
                                        @if ($role === \App\Enums\EventRole::CommitteeHead)
                                            <option value="committee_head" selected>{{ $role->label() }}</option>
                                        @endif
                                    </select>
                                    <button type="button" wire:click="removeMember({{ $member->id }})"
                                        wire:confirm="{{ __('event.confirm_remove_member') }}"
                                        class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-100">
                                        {{ __('ui.delete') }}
                                    </button>
                                @else
                                    <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-[11px] font-semibold text-indigo-700">{{ $role?->label() }}</span>
                                @endif
                            </div>
                        </div>

                        {{-- کمیته‌ها و پست‌ها --}}
                        <div class="space-y-2 ps-0 sm:ps-[3.25rem]">
                            @forelse ($mine as $a)
                                @php $key = $a['committee']->id.'-'.$member->id; @endphp
                                <div class="flex flex-wrap items-center gap-2 rounded-xl bg-slate-50 px-3 py-2" wire:key="hr-a-{{ $key }}">
                                    <span class="text-xs font-bold text-slate-700">{{ $a['committee']->name }}</span>
                                    @if ($a['is_head'])
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">{{ __('hr.head') }}</span>
                                    @endif

                                    @if ($canManage)
                                        <input type="text" wire:model="postDraft.{{ $key }}" placeholder="{{ __('hr.post_placeholder') }}"
                                            class="min-w-[9rem] flex-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs">
                                        <button type="button" wire:click="savePost({{ $a['committee']->id }}, {{ $member->id }})"
                                            class="rounded-lg bg-indigo-600 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-indigo-700">{{ __('hr.save_post') }}</button>
                                        <button type="button" wire:click="toggleHead({{ $a['committee']->id }}, {{ $member->id }})"
                                            class="rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700 hover:bg-amber-100">
                                            {{ $a['is_head'] ? __('hr.remove_head') : __('hr.make_head') }}
                                        </button>
                                        <button type="button" wire:click="removeFromCommittee({{ $a['committee']->id }}, {{ $member->id }})"
                                            wire:confirm="{{ __('hr.confirm_remove_from_committee') }}"
                                            class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-600 hover:bg-rose-100">{{ __('hr.remove_from_committee') }}</button>
                                    @else
                                        <span class="text-xs text-slate-600">{{ $a['post'] ?: '—' }}</span>
                                    @endif
                                </div>
                            @empty
                                <p class="text-xs text-slate-400">{{ __('hr.no_assignment') }}</p>
                            @endforelse

                            @if ($canManage && $free->isNotEmpty() && $role !== \App\Enums\EventRole::Viewer)
                                <div class="flex flex-wrap items-center gap-2">
                                    <select wire:model="rowCommittee.{{ $member->id }}" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs">
                                        <option value="">{{ __('hr.add_to_committee') }}…</option>
                                        @foreach ($free as $c)
                                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" wire:model="rowPost.{{ $member->id }}" placeholder="{{ __('hr.post_placeholder') }}"
                                        class="min-w-[9rem] flex-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs">
                                    <button type="button" wire:click="assign({{ $member->id }})"
                                        class="rounded-lg bg-slate-800 px-3 py-1 text-[11px] font-bold text-white hover:bg-slate-900">{{ __('hr.add') }}</button>
                                </div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
