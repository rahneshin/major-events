<div class="space-y-5">
    @section('page_title', $comp->name)

    <div class="rounded-3xl bg-gradient-to-l from-indigo-600 to-violet-600 p-6 text-white shadow-xl">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <span class="rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-semibold">{{ $comp->status->label() }}</span>
                <h2 class="mt-2 text-2xl font-bold">{{ $comp->name }}</h2>
                <p class="mt-1 text-sm text-indigo-100">
                    {{ $event->title }} · {{ $comp->gender->label() }} · {{ __('ranked.better.'.$better) }}
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
                {{ __('ranked.sections.'.$s) }}
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
                        <tr class="border-b border-slate-100 text-xs text-slate-400">
                            <th class="py-2 text-start">#</th>
                            <th class="text-start">{{ __('competition.fields.competitor_name') }}</th>
                            <th class="text-start">{{ __('competition.fields.affiliation') }}</th>
                            <th class="text-start">{{ __('competition.fields.status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($competitors as $c)
                            <tr class="border-b border-slate-50" wire:key="rc-{{ $c->id }}">
                                <td class="py-2 tabular-nums">{{ fa_num($loop->iteration) }}</td>
                                <td class="font-medium">{{ $c->name }}</td>
                                <td class="text-slate-500">{{ $c->affiliation ?? '—' }}</td>
                                <td>{{ $c->status->label() }}</td>
                                <td class="text-end">
                                    @if ($canManage && $hasFeature)
                                        @if ($c->status === \App\Enums\CompetitorStatus::Registered && $comp->status === \App\Enums\CompetitionStatus::Registration)
                                            <button type="button" wire:click="confirmCompetitor({{ $c->id }})" class="text-xs font-semibold text-emerald-700">{{ __('competition.confirm') }}</button>
                                        @endif
                                        @if ($c->status !== \App\Enums\CompetitorStatus::Withdrawn && $comp->status !== \App\Enums\CompetitionStatus::Finished)
                                            <button type="button" wire:click="withdrawCompetitor({{ $c->id }})" wire:confirm="{{ __('competition.confirm_withdraw') }}" class="ms-2 text-xs font-semibold text-rose-700">{{ __('competition.withdraw') }}</button>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-slate-400">{{ __('competition.no_competitors') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($canManage && $hasFeature && $comp->status === \App\Enums\CompetitionStatus::Registration)
            <form wire:submit="register" class="glass grid gap-3 rounded-2xl p-5 sm:grid-cols-3">
                <h3 class="text-sm font-bold text-slate-900 sm:col-span-3">{{ __('competition.register_title') }}</h3>
                <div>
                    <input type="text" wire:model="rName" maxlength="120" placeholder="{{ __('competition.fields.competitor_name') }}" class="w-full rounded-lg border-slate-300 text-sm">
                    @error('rName') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <input type="text" wire:model="rAffiliation" maxlength="120" placeholder="{{ __('competition.fields.affiliation') }}" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <input type="number" wire:model="rSeed" min="1" placeholder="{{ __('competition.fields.seed') }}" class="w-full rounded-lg border-slate-300 text-sm">
                    @error('rSeed') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-3">
                    <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">{{ __('competition.register') }}</button>
                </div>
            </form>

            <form wire:submit="bulkRegister" class="glass space-y-2 rounded-2xl p-5">
                <h3 class="text-sm font-bold text-slate-900">{{ __('competition.bulk_title') }}</h3>
                <p class="text-xs text-slate-500">{{ __('competition.bulk_hint') }}</p>
                <textarea wire:model="bulkText" rows="4" class="w-full rounded-lg border-slate-300 text-sm"></textarea>
                @error('bulkText') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">{{ __('competition.bulk_submit') }}</button>
            </form>
        @endif

    {{-- ============================ سری‌ها ============================ --}}
    @elseif ($section === 'heats')
        @if ($comp->status === \App\Enums\CompetitionStatus::Registration)
            @if ($canManage && $hasFeature)
                @foreach ($stages->where('is_final', false) as $st)
                    <form wire:submit="saveStageConfig({{ $st->id }})" class="glass grid gap-3 rounded-2xl p-5 sm:grid-cols-3" wire:key="cfg-{{ $st->id }}">
                        <h3 class="text-sm font-bold text-slate-900 sm:col-span-3">{{ __('ranked.stage_config_title', ['stage' => $st->name]) }}</h3>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('ranked.heat_size') }}</label>
                            <input type="number" wire:model="cHeatSize" min="2" max="64" class="w-full rounded-lg border-slate-300 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('ranked.advance_per_heat') }}</label>
                            <input type="number" wire:model="cPerHeat" min="0" max="64" class="w-full rounded-lg border-slate-300 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('ranked.advance_best_remaining') }}</label>
                            <input type="number" wire:model="cBest" min="0" max="256" class="w-full rounded-lg border-slate-300 text-sm">
                        </div>
                        <div class="sm:col-span-3">
                            <button type="submit" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700">{{ __('competition.save') }}</button>
                        </div>
                    </form>
                @endforeach

                <div class="glass space-y-3 rounded-2xl p-5">
                    <h3 class="text-sm font-bold text-slate-900">{{ __('ranked.draw_title') }}</h3>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <select wire:model="dMethod" class="rounded-lg border-slate-300 text-sm">
                            @foreach ($drawMethods as $m) <option value="{{ $m }}">{{ __('competition.draw_method.'.$m) }}</option> @endforeach
                        </select>
                        <input type="text" wire:model="dSeed" placeholder="{{ __('competition.draw_seed_hint') }}" class="rounded-lg border-slate-300 text-sm">
                        <button type="button" wire:click="previewDraw" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">{{ __('competition.preview') }}</button>
                    </div>

                    @if ($preview)
                        <p class="text-xs text-slate-500">{{ __('competition.preview_note', ['seed' => $preview['seed']]) }}</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($preview['heats'] as $i => $ids)
                                <div class="rounded-xl border border-slate-100 p-3">
                                    <p class="mb-1 text-xs font-bold text-slate-700">{{ __('ranked.heat_name', ['n' => fa_num($i + 1)]) }}</p>
                                    <ol class="list-decimal ps-5 text-sm">
                                        @foreach ($ids as $id) <li>{{ $preview['names'][$id] ?? '—' }}</li> @endforeach
                                    </ol>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" wire:click="confirmDraw" wire:confirm="{{ __('competition.confirm_draw') }}"
                            class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700">{{ __('competition.confirm_draw_btn') }}</button>
                    @endif
                </div>
            @else
                <div class="glass rounded-2xl px-6 py-12 text-center text-sm text-slate-500">{{ __('ranked.no_heats') }}</div>
            @endif
        @else
            @if ($canManage && $hasFeature)
                <div class="flex flex-wrap gap-2 text-xs">
                    @if ($comp->status === \App\Enums\CompetitionStatus::Drawn)
                        <button type="button" wire:click="markReady" class="rounded-xl bg-emerald-600 px-4 py-2 font-bold text-white">{{ __('competition.mark_ready') }}</button>
                    @endif
                    @if ($canAdvance)
                        <button type="button" wire:click="advance" class="rounded-xl bg-indigo-600 px-4 py-2 font-bold text-white">{{ __('ranked.advance') }}</button>
                    @endif
                    @if ($canRollback)
                        <button type="button" wire:click="rollbackAdvance" wire:confirm="{{ __('ranked.confirm_rollback') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 font-semibold text-slate-700">{{ __('ranked.rollback') }}</button>
                    @endif
                    @if ($comp->status === \App\Enums\CompetitionStatus::Finished)
                        <button type="button" wire:click="reopen" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 font-semibold text-amber-800">{{ __('competition.reopen') }}</button>
                    @endif
                    @if ($canReset)
                        <button type="button" wire:click="resetDraw" wire:confirm="{{ __('competition.confirm_reset') }}" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 font-semibold text-rose-700">{{ __('competition.reset_draw') }}</button>
                    @endif
                </div>
            @endif

            @foreach ($board as $block)
                <div class="space-y-3" wire:key="stage-{{ $block['stage']->id }}">
                    <h3 class="text-sm font-bold text-slate-900">{{ $block['stage']->name }}
                        <span class="text-xs font-normal text-slate-400">· {{ $block['stage']->status->label() }}</span>
                    </h3>

                    @foreach ($block['heats'] as $heat)
                        <div class="glass rounded-2xl p-4" wire:key="heat-{{ $heat->id }}">
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-bold text-slate-800">
                                    {{ $heat->name }}
                                    <span class="ms-1 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $heat->isConfirmed() ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        @if ($heat->isConfirmed()) {{ __('ranked.confirmed') }}
                                        @elseif ($heat->result_status) {{ __('ranked.waiting_confirm') }}
                                        @else — @endif
                                    </span>
                                </p>
                                <div class="flex gap-2 text-xs">
                                    @if ($canResult && $resultsOpen && $hasFeature && $editing?->id !== $heat->id && (! $heat->isConfirmed() || $canConfirm))
                                        <button type="button" wire:click="openHeat({{ $heat->id }})" class="font-semibold text-indigo-700">{{ $heat->result_status ? __('ranked.edit') : __('ranked.enter') }}</button>
                                    @endif
                                    @if ($canConfirm && $resultsOpen && $heat->result_status === \App\Enums\ResultStatus::Entered)
                                        <button type="button" wire:click="confirmHeat({{ $heat->id }})" class="font-semibold text-emerald-700">{{ __('ranked.confirm_heat') }}</button>
                                    @endif
                                    @if ($canConfirm && $resultsOpen && $heat->result_status)
                                        <button type="button" wire:click="clearHeat({{ $heat->id }})" wire:confirm="{{ __('ranked.confirm_clear') }}" class="font-semibold text-rose-700">{{ __('ranked.clear_heat') }}</button>
                                    @endif
                                </div>
                            </div>

                            @if ($editing?->id === $heat->id)
                                <p class="mb-2 text-[11px] text-slate-500">{{ __('ranked.value_hint.'.($kind === 'time' ? 'time' : 'distance')) }}</p>
                                <form wire:submit="saveHeat" class="space-y-2">
                                    @foreach ($heat->results as $r)
                                        <div class="grid grid-cols-[2rem_1fr_7rem_8rem] items-center gap-2 text-sm" wire:key="e-{{ $r->competitor_id }}">
                                            <span class="tabular-nums text-slate-400">{{ $r->lane ? fa_num($r->lane) : '' }}</span>
                                            <span class="truncate font-medium">{{ $r->competitor->name }}</span>
                                            <input type="text" dir="ltr" wire:model="entry.{{ $r->competitor_id }}.value" class="rounded-lg border-slate-300 text-sm">
                                            <select wire:model="entry.{{ $r->competitor_id }}.status" class="rounded-lg border-slate-300 text-xs">
                                                @foreach ($statuses as $st) <option value="{{ $st->value }}">{{ $st->label() }}</option> @endforeach
                                            </select>
                                        </div>
                                    @endforeach
                                    <div class="flex gap-2 pt-1">
                                        <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">{{ __('ranked.save_heat') }}</button>
                                        <button type="button" wire:click="cancelEdit" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600">{{ __('competition.cancel') }}</button>
                                    </div>
                                </form>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="border-b border-slate-100 text-xs text-slate-400">
                                                <th class="py-1.5 text-start">{{ __('ranked.col.lane') }}</th>
                                                <th class="text-start">{{ __('ranked.col.rank') }}</th>
                                                <th class="text-start">{{ __('ranked.col.name') }}</th>
                                                <th class="text-start">{{ __('ranked.col.affiliation') }}</th>
                                                <th class="text-start">{{ __('ranked.col.value') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($heat->results as $r)
                                                <tr class="border-b border-slate-50">
                                                    <td class="py-1.5 tabular-nums">{{ $r->lane ? fa_num($r->lane) : '—' }}</td>
                                                    <td class="tabular-nums font-bold">{{ $r->rank ? fa_num($r->rank) : '—' }}</td>
                                                    <td class="font-medium">{{ $r->competitor->name }}</td>
                                                    <td class="text-slate-500">{{ $r->competitor->affiliation ?? '—' }}</td>
                                                    <td class="tabular-nums">
                                                        @if ($r->status->isRanked())
                                                            {{ $r->value !== null ? fa_num(\App\Support\HeatValue::format($r->value, $kind)) : '—' }}
                                                        @else
                                                            {{ $r->status->label() }}
                                                        @endif
                                                        @if ($r->advanced) <span class="ms-1 rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700">{{ __('ranked.advanced_mark') }}</span> @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif

    {{-- ============================ رتبه‌ی نهایی ============================ --}}
    @elseif ($section === 'ranking')
        <div class="glass overflow-x-auto rounded-2xl p-5">
            @if ($ranking->isEmpty())
                <p class="py-8 text-center text-sm text-slate-500">{{ __('ranked.no_ranking') }}</p>
            @else
                <h3 class="mb-3 text-sm font-bold text-slate-900">{{ __('ranked.ranking_title') }}</h3>
                <table class="w-full text-sm">
                    <tbody>
                        @foreach ($ranking as $c)
                            <tr class="border-b border-slate-50" wire:key="rk-{{ $c->id }}">
                                <td class="w-12 py-2 tabular-nums font-bold">{{ fa_num($c->final_rank) }}</td>
                                <td class="font-medium">{{ $c->name }}</td>
                                <td class="text-slate-500">{{ $c->affiliation ?? '—' }}</td>
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

        @if ($canManage && $hasFeature)
            <livewire:competitions.public-links :event="$event" :competition-id="$comp->id" :key="'pl-'.$comp->id" />
        @endif

        @if ($canDelete)
            <div class="glass rounded-2xl p-5">
                <h3 class="mb-2 text-sm font-bold text-rose-700">{{ __('competition.danger_zone') }}</h3>
                <button type="button" wire:click="deleteCompetition" wire:confirm="{{ __('competition.confirm_delete') }}"
                    class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-bold text-rose-700 hover:bg-rose-100">{{ __('competition.delete') }}</button>
            </div>
        @endif
    @endif
</div>
