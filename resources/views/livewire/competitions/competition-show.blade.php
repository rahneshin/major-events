<div class="space-y-5">
    @section('page_title', $comp->name)

    <div class="rounded-3xl bg-gradient-to-l from-indigo-600 to-violet-600 p-6 text-white shadow-xl">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <span class="rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-semibold">{{ $comp->status->label() }}</span>
                <h2 class="mt-2 text-2xl font-bold">{{ $comp->name }}</h2>
                <p class="mt-1 text-sm text-indigo-100">
                    {{ $event->title }} · {{ $comp->gender->label() }} · {{ $comp->participant_type->label() }}
                </p>
            </div>
            <a href="{{ route('events.show', ['event' => $event, 'tab' => 'competitions']) }}" wire:navigate
                class="rounded-xl border border-white/30 bg-white/10 px-4 py-2 text-xs font-semibold text-white hover:bg-white/20">
                {{ __('ui.back') }}
            </a>
        </div>
    </div>

    @if ($notice)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $notice }}</div>
    @endif
    @if ($error)
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $error }}</div>
    @endif
    @if (! $hasFeature)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ __('competition.errors.feature_unavailable') }}</div>
    @endif

    <div class="flex gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 text-xs font-semibold shadow-sm">
        @foreach ($sections as $s)
            <button type="button" wire:click="setSection('{{ $s }}')"
                class="shrink-0 rounded-lg px-3.5 py-2.5 transition {{ $section === $s ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 hover:bg-slate-50' }}">
                {{ __('competition.sections.'.$s) }}
            </button>
        @endforeach
    </div>

    {{-- ============================ ثبت‌نام ============================ --}}
    @if ($section === 'competitors')
        <div class="glass rounded-2xl p-5">
            <p class="mb-3 text-xs text-slate-500">
                {{ __('competition.counts', [
                    'registered' => fa_num($counts['registered']),
                    'confirmed' => fa_num($counts['confirmed']),
                    'withdrawn' => fa_num($counts['withdrawn']),
                ]) }}
                @if ($comp->capacity) · {{ __('competition.capacity_of', ['capacity' => fa_num($comp->capacity)]) }} @endif
            </p>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 text-start text-xs text-slate-400">
                            <th class="py-2 text-start">#</th>
                            <th class="text-start">{{ __('competition.fields.competitor_name') }}</th>
                            <th class="text-start">{{ __('competition.fields.affiliation') }}</th>
                            <th class="text-start">{{ __('competition.fields.seed') }}</th>
                            <th class="text-start">{{ __('competition.fields.status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($competitors as $i => $c)
                            <tr class="border-b border-slate-50 {{ $c->status->value === 'withdrawn' ? 'opacity-50' : '' }}" wire:key="cmp-{{ $c->id }}">
                                <td class="py-2 tabular-nums">{{ fa_num($i + 1) }}</td>
                                <td class="font-medium">{{ $c->name }}
                                    @if ($c->members_count) <span class="text-xs text-slate-400">({{ fa_num($c->members_count) }})</span> @endif
                                </td>
                                <td>{{ $c->affiliation ?: '—' }}</td>
                                <td class="tabular-nums">{{ $c->seed ? fa_num($c->seed) : '—' }}</td>
                                <td>{{ $c->status->label() }}</td>
                                <td class="space-x-2 space-x-reverse text-end">
                                    @if ($canManage && $comp->status->value === 'registration' && $c->status->value === 'registered')
                                        <button type="button" wire:click="confirmCompetitor({{ $c->id }})" class="text-xs font-semibold text-emerald-700">{{ __('competition.confirm') }}</button>
                                    @endif
                                    @if ($canManage && $comp->status->value === 'registration' && $c->status->value !== 'withdrawn')
                                        <button type="button" wire:click="withdrawCompetitor({{ $c->id }})" wire:confirm="{{ __('competition.confirm_withdraw') }}" class="text-xs font-semibold text-rose-700">{{ __('competition.withdraw') }}</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-slate-400">{{ __('competition.no_competitors') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($canManage && $hasFeature && $comp->status->value === 'registration')
            <div class="grid gap-4 lg:grid-cols-2">
                <form wire:submit="register" class="glass space-y-3 rounded-2xl p-5">
                    <h3 class="text-sm font-bold text-slate-900">{{ __('competition.register_title') }}</h3>
                    <div>
                        <input type="text" wire:model="rName" maxlength="120" placeholder="{{ __('competition.fields.competitor_name') }}" class="w-full rounded-lg border-slate-300 text-sm">
                        @error('rName') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <input type="text" wire:model="rAffiliation" maxlength="120" placeholder="{{ __('competition.fields.affiliation') }}" class="w-full rounded-lg border-slate-300 text-sm">
                    <div>
                        <input type="number" wire:model="rSeed" min="1" max="9999" placeholder="{{ __('competition.fields.seed') }}" class="w-full rounded-lg border-slate-300 text-sm">
                        @error('rSeed') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <textarea wire:model="rMembers" rows="3" placeholder="{{ __('competition.fields.members_hint') }}" class="w-full rounded-lg border-slate-300 text-sm"></textarea>
                    <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">{{ __('competition.register') }}</button>
                </form>

                <form wire:submit="bulkRegister" class="glass space-y-3 rounded-2xl p-5">
                    <h3 class="text-sm font-bold text-slate-900">{{ __('competition.bulk_title') }}</h3>
                    <p class="text-xs text-slate-500">{{ __('competition.bulk_hint') }}</p>
                    <textarea wire:model="bulkText" rows="6" class="w-full rounded-lg border-slate-300 text-sm"></textarea>
                    @error('bulkText') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">{{ __('competition.bulk_submit') }}</button>
                </form>
            </div>
        @endif

    {{-- ============================= قرعه ============================== --}}
    @elseif ($section === 'draw')
        <div class="glass space-y-4 rounded-2xl p-5">
            @if ($stage)
                <p class="text-sm text-slate-700">
                    <span class="font-bold">{{ $stage->name }}</span> · {{ $stage->format->label() }} ·
                    {{ $stage->status->label() }}
                    @if ($stage->format->value === 'round_robin') · {{ __('competition.legs', ['legs' => fa_num((int) $stage->config('legs', 1))]) }} @endif
                </p>
            @endif

            @if ($stage && $stage->status->value === 'pending')
                @if ($canManage && $hasFeature)
                    <div class="grid gap-3 sm:grid-cols-3">
                        <select wire:model="dMethod" class="rounded-lg border-slate-300 text-sm">
                            <option value="random">{{ __('competition.draw_method.random') }}</option>
                            <option value="seeded">{{ __('competition.draw_method.seeded') }}</option>
                        </select>
                        <input type="text" wire:model="dSeed" maxlength="64" placeholder="{{ __('competition.draw_seed_hint') }}" class="rounded-lg border-slate-300 text-sm" dir="ltr">
                        <button type="button" wire:click="previewDraw" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">{{ __('competition.preview') }}</button>
                    </div>
                @endif

                @if ($preview)
                    <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-4">
                        <p class="mb-2 text-xs text-slate-500">{{ __('competition.preview_note', ['seed' => $preview['seed']]) }}</p>
                        <ol class="grid gap-1 text-sm sm:grid-cols-2">
                            @foreach ($preview['order'] as $i => $cid)
                                <li class="tabular-nums">{{ fa_num($i + 1) }}. {{ $preview['names'][$cid] ?? '—' }}</li>
                            @endforeach
                        </ol>
                        @if ($canManage)
                            <button type="button" wire:click="confirmDraw" wire:confirm="{{ __('competition.confirm_draw') }}"
                                class="mt-3 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700">{{ __('competition.confirm_draw_btn') }}</button>
                        @endif
                    </div>
                @endif
            @elseif ($stage)
                @if ($draw)
                    <p class="text-xs text-slate-500">
                        {{ __('competition.draw_doc', [
                            'method' => __('competition.draw_method.'.$draw->method),
                            'seed' => $draw->rng_seed,
                            'by' => $draw->performer?->name ?? '—',
                        ]) }}
                    </p>
                @endif
                <ol class="grid gap-1 text-sm sm:grid-cols-2">
                    @foreach ($entries as $e)
                        <li class="tabular-nums">{{ fa_num($e->draw_position) }}. {{ $e->competitor->name }}</li>
                    @endforeach
                </ol>

                @if ($canManage && $hasFeature)
                    <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                        @if ($stage->status->value === 'drawn' && ! $stage->matches()->exists())
                            <button type="button" wire:click="generateFixtures" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">{{ __('competition.generate_fixtures') }}</button>
                        @endif
                        @if ($comp->status->value === 'drawn' && $stage->matches()->exists())
                            <button type="button" wire:click="markReady" class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700">{{ __('competition.mark_ready') }}</button>
                        @endif
                        <button type="button" wire:click="resetDraw" wire:confirm="{{ __('competition.confirm_reset') }}" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100">{{ __('competition.reset_draw') }}</button>
                    </div>
                @endif
            @endif
        </div>

    {{-- ============================ برنامه‌ی بازی‌ها ============================ --}}
    @elseif ($section === 'fixtures')
        @forelse ($matches as $round => $list)
            <div class="glass rounded-2xl p-5" wire:key="round-{{ $round }}">
                <h3 class="mb-3 text-sm font-bold text-slate-900">{{ __('competition.round', ['round' => fa_num($round)]) }}</h3>
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($list as $m)
                        <li class="flex items-center justify-between py-2" wire:key="m-{{ $m->id }}">
                            <span class="font-medium">{{ $m->a?->name ?? '—' }}</span>
                            <span class="mx-3 tabular-nums text-slate-500">
                                @if ($m->status->isPlayed() && $m->score_a !== null)
                                    {{ fa_num($m->score_a) }} : {{ fa_num($m->score_b) }}
                                @else
                                    {{ __('competition.vs') }}
                                @endif
                            </span>
                            <span class="font-medium">{{ $m->b?->name ?? '—' }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <div class="glass rounded-2xl px-6 py-12 text-center text-sm text-slate-500">{{ __('competition.no_fixtures') }}</div>
        @endforelse

    {{-- ============================ رده‌بندی ============================ --}}
    @elseif ($section === 'standings')
        <div class="glass overflow-x-auto rounded-2xl p-5">
            @if ($standingRows === [])
                <p class="py-8 text-center text-sm text-slate-500">{{ __('competition.no_standings') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 text-xs text-slate-400">
                            <th class="py-2 text-start">{{ __('competition.col.rank') }}</th>
                            <th class="text-start">{{ __('competition.col.name') }}</th>
                            @foreach (['played', 'won', 'drawn', 'lost', 'for', 'against', 'diff', 'points'] as $col)
                                <th class="text-center">{{ __('competition.col.'.$col) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($standingRows as $row)
                            <tr class="border-b border-slate-50" wire:key="st-{{ $row['competitor_id'] }}">
                                <td class="py-2 tabular-nums">{{ fa_num($row['rank']) }}@if ($row['tied']) <span title="{{ __('competition.tied') }}">=</span> @endif</td>
                                <td class="font-medium">{{ $row['name'] }}</td>
                                @foreach (['played', 'won', 'drawn', 'lost', 'for', 'against', 'diff', 'points'] as $col)
                                    <td class="text-center tabular-nums {{ $col === 'points' ? 'font-bold' : '' }}">{{ fa_num($row[$col]) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

    {{-- ============================ تنظیمات ============================ --}}
    @else
        @if ($canManage && $hasFeature)
            <form wire:submit="saveSettings" class="glass grid gap-3 rounded-2xl p-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('competition.fields.name') }}</label>
                    <input type="text" wire:model="sName" maxlength="120" class="w-full rounded-lg border-slate-300 text-sm">
                    @error('sName') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('competition.fields.gender') }}</label>
                    <select wire:model="sGender" class="w-full rounded-lg border-slate-300 text-sm">
                        @foreach ($genders as $g) <option value="{{ $g->value }}">{{ $g->label() }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('competition.fields.capacity') }}</label>
                    <input type="number" wire:model="sCapacity" min="2" class="w-full rounded-lg border-slate-300 text-sm">
                    @error('sCapacity') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <label class="flex items-center gap-2 pt-6 text-sm text-slate-700">
                    <input type="checkbox" wire:model="sConfirm" class="rounded border-slate-300"> {{ __('competition.fields.requires_confirmation') }}
                </label>
                <div class="sm:col-span-2">
                    <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">{{ __('competition.save') }}</button>
                </div>
            </form>
        @endif

        <div class="glass space-y-3 rounded-2xl p-5">
            <h3 class="text-sm font-bold text-slate-900">{{ __('competition.roles_title') }}</h3>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($members as $u)
                    <li class="flex items-center justify-between py-2" wire:key="role-{{ $u->id }}">
                        <span>{{ $u->name }} <span class="text-xs text-slate-400">· {{ __('competition.role.'.$u->pivot->role) }}</span></span>
                        @if ($canAssign)
                            <button type="button" wire:click="removeRole({{ $u->id }})" class="text-xs font-semibold text-rose-700">{{ __('competition.remove') }}</button>
                        @endif
                    </li>
                @empty
                    <li class="py-4 text-slate-400">{{ __('competition.no_roles') }}</li>
                @endforelse
            </ul>

            @if ($canAssign && $hasFeature)
                <div class="grid gap-2 border-t border-slate-100 pt-3 sm:grid-cols-3">
                    <select wire:model="roleUserId" class="rounded-lg border-slate-300 text-sm">
                        <option value="">{{ __('competition.pick_member') }}</option>
                        @foreach ($eventMembers as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
                    </select>
                    <select wire:model="roleValue" class="rounded-lg border-slate-300 text-sm">
                        @foreach ($roles as $r) <option value="{{ $r->value }}">{{ $r->label() }}</option> @endforeach
                    </select>
                    <button type="button" wire:click="assignRole" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">{{ __('competition.assign') }}</button>
                </div>
            @endif
        </div>

        @if ($canDelete)
            <div class="glass rounded-2xl p-5">
                <h3 class="mb-2 text-sm font-bold text-rose-700">{{ __('competition.danger_zone') }}</h3>
                <button type="button" wire:click="deleteCompetition" wire:confirm="{{ __('competition.confirm_delete') }}"
                    class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-bold text-rose-700 hover:bg-rose-100">{{ __('competition.delete') }}</button>
            </div>
        @endif
    @endif
</div>
