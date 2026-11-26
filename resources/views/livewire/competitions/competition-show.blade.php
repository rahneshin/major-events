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
                                    @if ($canManage && $comp->status->value !== 'finished' && $c->status->value !== 'withdrawn')
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
            @if ($drawStage)
                <p class="text-sm text-slate-700">
                    <span class="font-bold">{{ $drawStage->name }}</span> · {{ $drawStage->format->label() }} ·
                    {{ $drawStage->status->label() }}
                    @if ($drawStage->format->value === 'round_robin') · {{ __('competition.legs', ['legs' => fa_num((int) $drawStage->config('legs', 1))]) }} @endif
                </p>
            @endif

            @if ($drawStage && $drawStage->status->value === 'pending')
                @if ($canManage && $hasFeature)
                    <div class="grid gap-3 sm:grid-cols-3">
                        <select wire:model="dMethod" class="rounded-lg border-slate-300 text-sm">
                            <option value="random">{{ __('competition.draw_method.random') }}</option>
                            <option value="seeded">{{ __('competition.draw_method.seeded') }}</option>
                        </select>
                        <input type="text" wire:model="dSeed" maxlength="64" placeholder="{{ __('competition.draw_seed_hint') }}" class="rounded-lg border-slate-300 text-sm" dir="ltr">
                        <button type="button" wire:click="previewDraw" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">{{ __('competition.preview') }}</button>
                    </div>
                    @if ($drawStage->format->value === 'groups')
                        <div class="grid gap-3 sm:grid-cols-3">
                            <label class="text-xs text-slate-600">{{ __('competition.draw_groups') }}
                                <input type="number" wire:model="dGroups" min="2" max="16" class="mt-1 w-full rounded-lg border-slate-300 text-sm" dir="ltr">
                            </label>
                            <label class="text-xs text-slate-600">{{ __('competition.draw_advance') }}
                                <input type="number" wire:model="dAdvance" min="1" max="8" class="mt-1 w-full rounded-lg border-slate-300 text-sm" dir="ltr">
                            </label>
                        </div>
                    @endif
                    @if ($knockoutAhead)
                        <label class="flex items-center gap-2 text-xs text-slate-600">
                            <input type="checkbox" wire:model="dThird" class="rounded border-slate-300"> {{ __('competition.draw_third_place') }}
                        </label>
                        @if ($drawStage->format->value === 'single_elim')
                            <p class="text-xs text-slate-500">{{ __('competition.draw_bracket_hint') }}</p>
                        @endif
                    @endif
                @endif

                @if ($preview)
                    <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-4">
                        <p class="mb-2 text-xs text-slate-500">{{ __('competition.preview_note', ['seed' => $preview['seed']]) }}</p>
                        @if ($preview['groups'])
                            <div class="grid gap-3 text-sm sm:grid-cols-2">
                                @foreach ($preview['groups'] as $gName => $gIds)
                                    <div class="rounded-lg border border-indigo-100 bg-white p-3">
                                        <p class="mb-1 text-xs font-bold text-indigo-700">{{ __('competition.group_name', ['name' => $gName]) }}</p>
                                        <ul class="space-y-0.5">
                                            @foreach ($gIds as $cid)
                                                <li>{{ $preview['names'][$cid] ?? '—' }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <ol class="grid gap-1 text-sm sm:grid-cols-2">
                                @foreach ($preview['order'] as $i => $cid)
                                    <li class="tabular-nums">{{ fa_num($i + 1) }}. {{ $preview['names'][$cid] ?? '—' }}</li>
                                @endforeach
                            </ol>
                        @endif
                        @if ($canManage)
                            <button type="button" wire:click="confirmDraw" wire:confirm="{{ __('competition.confirm_draw') }}"
                                class="mt-3 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700">{{ __('competition.confirm_draw_btn') }}</button>
                        @endif
                    </div>
                @endif
            @elseif ($drawStage)
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
                        <li class="tabular-nums">{{ fa_num($e->draw_position) }}. {{ $e->competitor->name }}@if ($e->group_name) <span class="text-xs text-slate-500">({{ __('competition.group_name', ['name' => $e->group_name]) }})</span>@endif</li>
                    @endforeach
                </ol>

                @if ($canManage && $hasFeature)
                    <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                        @if ($drawStage->status->value === 'drawn' && ! $drawStage->matches()->exists())
                            <button type="button" wire:click="generateFixtures" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">{{ __('competition.generate_fixtures') }}</button>
                        @endif
                        @if ($comp->status->value === 'drawn' && $drawStage->matches()->exists())
                            <button type="button" wire:click="markReady" class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700">{{ __('competition.mark_ready') }}</button>
                        @endif
                        <button type="button" wire:click="resetDraw" wire:confirm="{{ __('competition.confirm_reset') }}" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100">{{ __('competition.reset_draw') }}</button>
                    </div>
                @endif
            @endif
        </div>

    {{-- ============================ برنامه‌ی بازی‌ها ============================ --}}
    @elseif ($section === 'fixtures')
        @php
            $unit = (string) $comp->config('unit', 'goals');
            $open = $comp->status->acceptsResults();
        @endphp

        @if ($stages->count() > 1)
            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                @foreach ($stages as $st)
                    <button type="button" wire:click="selectStage('{{ $st->id }}')" wire:key="stage-tab-{{ $st->id }}"
                        class="rounded-lg px-3 py-2 {{ $stage && $stage->id === $st->id ? 'bg-slate-800 text-white' : 'border border-slate-200 bg-white text-slate-600' }}">{{ $st->name }} · {{ $st->status->label() }}</button>
                @endforeach
            </div>
        @endif

        @if ($comp->status->value === 'finished' && $canManage && $hasFeature)
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                <span>{{ __('competition.finished_notice') }}</span>
                <button type="button" wire:click="reopen" wire:confirm="{{ __('competition.confirm_reopen') }}"
                    class="rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-xs font-bold text-emerald-800">{{ __('competition.reopen') }}</button>
            </div>
        @elseif (! $open && $stage && $stage->matches()->exists())
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ __('competition.errors.results_closed') }}</div>
        @endif

        @if ($editing)
            <form wire:submit="saveResult" class="glass space-y-3 rounded-2xl p-5" wire:key="edit-{{ $editing->id }}">
                <p class="text-sm font-bold text-slate-900">{{ $editing->a?->name }} <span class="text-slate-400">{{ __('competition.vs') }}</span> {{ $editing->b?->name }}</p>
                @if ($unit === 'games')
                    <div class="space-y-2">
                        @foreach ($mGames as $i => $g)
                            <div class="flex items-center gap-2" wire:key="game-{{ $i }}">
                                <span class="w-16 text-xs text-slate-500">{{ __('competition.game_n', ['n' => fa_num($i + 1)]) }}</span>
                                <input type="number" min="0" max="999" wire:model="mGames.{{ $i }}.a" class="w-20 rounded-lg border-slate-300 text-sm" dir="ltr">
                                <span class="text-slate-400">:</span>
                                <input type="number" min="0" max="999" wire:model="mGames.{{ $i }}.b" class="w-20 rounded-lg border-slate-300 text-sm" dir="ltr">
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex items-center gap-2">
                        <input type="number" min="0" max="999" wire:model="mScoreA" class="w-24 rounded-lg border-slate-300 text-sm" dir="ltr">
                        <span class="text-slate-400">:</span>
                        <input type="number" min="0" max="999" wire:model="mScoreB" class="w-24 rounded-lg border-slate-300 text-sm" dir="ltr">
                    </div>
                @endif
                @if ($unit !== 'games' && $stage && $stage->format->value === 'single_elim')
                    <label class="block text-xs text-slate-600">{{ __('competition.tiebreak_label') }}
                        <select wire:model="mTiebreak" class="mt-1 rounded-lg border-slate-300 text-sm">
                            <option value="">—</option>
                            <option value="a">{{ $editing->a?->name }}</option>
                            <option value="b">{{ $editing->b?->name }}</option>
                        </select>
                    </label>
                    <p class="text-xs text-slate-500">{{ __('competition.tiebreak_hint') }}</p>
                @endif
                <div class="flex gap-2">
                    <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">{{ __('competition.save_result') }}</button>
                    <button type="button" wire:click="cancelEdit" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600">{{ __('competition.cancel') }}</button>
                </div>
            </form>
        @endif

        @if ($scheduling)
            <form wire:submit="saveSchedule" class="glass space-y-3 rounded-2xl p-5" wire:key="sched-{{ $scheduling->id }}">
                <p class="text-sm font-bold text-slate-900">{{ $scheduling->a?->name }} <span class="text-slate-400">{{ __('competition.vs') }}</span> {{ $scheduling->b?->name }}</p>
                <div class="grid gap-3 sm:grid-cols-3">
                    <x-jalali-picker id="sc-date" wire:model="scDate" />
                    <input type="time" wire:model="scTime" class="rounded-lg border-slate-300 text-sm" dir="ltr">
                    <input type="text" wire:model="scCourt" maxlength="60" placeholder="{{ __('competition.court') }}" class="rounded-lg border-slate-300 text-sm">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">{{ __('competition.save') }}</button>
                    <button type="button" wire:click="cancelEdit" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600">{{ __('competition.cancel') }}</button>
                </div>
            </form>
        @endif

        @if ($fixtures['rounds'] === [])
            <div class="glass rounded-2xl px-6 py-12 text-center text-sm text-slate-500">{{ __('competition.no_fixtures') }}</div>
        @else
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                <button type="button" wire:click="setFixturesView('rounds')" class="rounded-lg px-3 py-2 {{ $fixtures['view'] === 'rounds' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border border-slate-200' }}">{{ __('competition.view_rounds') }}</button>
                @if ($stage->format->value === 'round_robin')
                    <button type="button" wire:click="setFixturesView('matrix')" class="rounded-lg px-3 py-2 {{ $fixtures['view'] === 'matrix' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border border-slate-200' }}">{{ __('competition.view_matrix') }}</button>
                @elseif ($stage->format->value === 'single_elim')
                    <button type="button" wire:click="setFixturesView('bracket')" class="rounded-lg px-3 py-2 {{ $fixtures['view'] === 'bracket' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border border-slate-200' }}">{{ __('competition.view_bracket') }}</button>
                @endif
                @if ($fixtures['view'] === 'rounds')
                    <select wire:change="setRound($event.target.value)" class="rounded-lg border-slate-300 text-xs">
                        @foreach ($fixtures['rounds'] as $r)
                            <option value="{{ $r }}" @selected($r === $fixtures['current'])>{{ $fixtures['labels'][$r] ?? __('competition.round', ['round' => fa_num($r)]) }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            @if ($fixtures['view'] === 'bracket' && $fixtures['bracket'])
                <div class="glass overflow-x-auto rounded-2xl p-4">
                    <div class="flex min-w-max gap-6">
                        @foreach ($fixtures['bracket'] as $bRound => $bMatches)
                            <div class="flex w-52 flex-col justify-around gap-3" wire:key="br-{{ $bRound }}">
                                <p class="text-center text-xs font-bold text-indigo-700">{{ $fixtures['labels'][$bRound] ?? $bRound }}</p>
                                @foreach ($bMatches as $bm)
                                    <div class="rounded-lg border border-slate-200 bg-white p-2 text-xs" wire:key="bm-{{ $bm->id }}">
                                        @if ($bm->bracket === 'third_place') <p class="mb-1 text-[10px] font-bold text-amber-700">{{ __('competition.third_place_match') }}</p> @endif
                                        @foreach (['a', 'b'] as $bSide)
                                            @php
                                                $bScore = $bSide === 'a' ? $bm->score_a : $bm->score_b;
                                                $bId = $bSide === 'a' ? $bm->a_id : $bm->b_id;
                                            @endphp
                                            <div class="flex items-center justify-between gap-2 py-0.5 {{ $bm->winner_id && $bId === $bm->winner_id ? 'font-bold text-emerald-700' : 'text-slate-700' }}">
                                                <span class="truncate">{{ $this->slotLabel($bm, $bSide) }}</span>
                                                <span class="tabular-nums">{{ $bScore !== null ? fa_num($bScore) : ($bm->status->value === 'walkover' && $bm->winner_id === $bId ? '+' : '') }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @elseif ($fixtures['view'] === 'matrix' && $fixtures['matrix'])
                @php $mx = $fixtures['matrix']; @endphp
                <div class="glass overflow-x-auto rounded-2xl p-4">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr>
                                <th></th>
                                @foreach ($mx['entries'] as $col)
                                    <th class="px-2 py-1 text-center tabular-nums text-slate-400">{{ fa_num($col->draw_position) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($mx['entries'] as $row)
                                <tr class="border-t border-slate-100" wire:key="mx-{{ $row->id }}">
                                    <th class="whitespace-nowrap py-1.5 pe-3 text-start font-semibold text-slate-700">{{ fa_num($row->draw_position) }}. {{ $row->competitor->name }}</th>
                                    @foreach ($mx['entries'] as $col)
                                        @if ($row->competitor_id == $col->competitor_id)
                                            <td class="bg-slate-100 text-center text-slate-300">—</td>
                                        @else
                                            @php
                                                $leg = $row->draw_position < $col->draw_position ? 1 : 2;
                                                $cell = $mx['cells'][min($row->competitor_id, $col->competitor_id).'-'.max($row->competitor_id, $col->competitor_id).'-'.$leg] ?? null;
                                                $label = '';
                                                if ($cell && $cell->score_a !== null) {
                                                    $label = $cell->a_id == $row->competitor_id
                                                        ? fa_num($cell->score_a).':'.fa_num($cell->score_b)
                                                        : fa_num($cell->score_b).':'.fa_num($cell->score_a);
                                                } elseif ($cell && $cell->status->value === 'walkover') {
                                                    $label = $cell->winner_id == $row->competitor_id ? '+' : '−';
                                                }
                                            @endphp
                                            <td class="px-2 py-1.5 text-center tabular-nums">
                                                @if ($cell && $canResult && $open)
                                                    <button type="button" wire:click="startResult({{ $cell->id }})" class="rounded px-1.5 py-0.5 hover:bg-indigo-50 {{ $cell->result_status?->value === 'entered' ? 'text-amber-700' : 'text-slate-800' }}">{{ $label !== '' ? $label : '·' }}</button>
                                                @else
                                                    {{ $label !== '' ? $label : ($cell ? '·' : '') }}
                                                @endif
                                            </td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="glass rounded-2xl p-5">
                    <ul class="divide-y divide-slate-100 text-sm">
                        @foreach ($fixtures['list'] as $m)
                            @php
                                $played = $m->result_status !== null || $m->status->value === 'walkover';
                                $cancelled = $m->status->value === 'cancelled';
                                $both = $m->a_id && $m->b_id;
                                $locked = $played && $m->result_status?->value === 'confirmed';
                                $canEnter = $canResult && $open && $both && ! $cancelled && (! $locked || $canConfirm);
                            @endphp
                            <li class="space-y-2 py-3 {{ $cancelled ? 'opacity-50' : '' }}" wire:key="m-{{ $m->id }}">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="font-medium {{ $m->a_id ? '' : 'text-slate-400' }}">{{ $this->slotLabel($m, 'a') }}</span>
                                    <span class="mx-2 tabular-nums text-slate-600">
                                        @if ($m->score_a !== null)
                                            {{ fa_num($m->score_a) }} : {{ fa_num($m->score_b) }}
                                        @elseif ($m->status->value === 'walkover')
                                            {{ $m->winner_id == $m->a_id ? '+ : −' : '− : +' }}
                                        @else
                                            {{ __('competition.vs') }}
                                        @endif
                                    </span>
                                    <span class="font-medium {{ $m->b_id ? '' : 'text-slate-400' }}">{{ $this->slotLabel($m, 'b') }}</span>
                                </div>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                    <span>{{ $m->status->label() }}@if ($m->result_status) · {{ $m->result_status->label() }} @endif</span>
                                    @if ($m->group_name) <span class="font-semibold text-indigo-700">{{ __('competition.group_name', ['name' => $m->group_name]) }}</span> @endif
                                    @if ($m->bracket === 'third_place') <span class="font-semibold text-amber-700">{{ __('competition.third_place_match') }}</span> @endif
                                    @if (! empty($m->result_meta['tiebreak'])) <span>{{ __('competition.tiebreak_winner', ['name' => $this->slotLabel($m, $m->result_meta['tiebreak']), ]) }}</span> @endif
                                    @if ($m->scheduled_at)
                                        <span class="tabular-nums">{{ fa_num(jalali($m->scheduled_at, 'Y/m/d')) }} {{ fa_num($m->scheduled_at->format('H:i')) }}</span>
                                    @endif
                                    @if ($m->court) <span>{{ $m->court }}</span> @endif
                                    @if ($m->is_manual) <span>{{ __('competition.manual_badge') }}</span> @endif

                                    @if ($canEnter)
                                        <button type="button" wire:click="startResult({{ $m->id }})" class="font-semibold text-indigo-700">{{ __('competition.enter_result') }}</button>
                                    @endif
                                    @if ($canConfirm && $open && $m->result_status?->value === 'entered')
                                        <button type="button" wire:click="confirmMatch({{ $m->id }})" class="font-semibold text-emerald-700">{{ __('competition.confirm') }}</button>
                                    @endif
                                    @if ($canConfirm && $open && $played)
                                        <button type="button" wire:click="clearMatch({{ $m->id }})" wire:confirm="{{ __('competition.confirm_clear') }}" class="font-semibold text-rose-700">{{ __('competition.clear_result') }}</button>
                                    @endif
                                    @if ($canConfirm && $open && ! $played && $both && ! $cancelled)
                                        <button type="button" wire:click="walkoverMatch({{ $m->id }}, 'a')" wire:confirm="{{ __('competition.confirm_walkover') }}" class="font-semibold text-slate-600">{{ __('competition.walkover_a') }}</button>
                                        <button type="button" wire:click="walkoverMatch({{ $m->id }}, 'b')" wire:confirm="{{ __('competition.confirm_walkover') }}" class="font-semibold text-slate-600">{{ __('competition.walkover_b') }}</button>
                                        @if ($stage->format->value !== 'single_elim')
                                            <button type="button" wire:click="cancelMatch({{ $m->id }})" wire:confirm="{{ __('competition.confirm_cancel_match') }}" class="font-semibold text-rose-700">{{ __('competition.cancel_match') }}</button>
                                        @endif
                                    @endif
                                    @if ($canManage && $hasFeature && ! $played && ! $cancelled && $comp->status->value !== 'finished')
                                        <button type="button" wire:click="startSchedule({{ $m->id }})" class="font-semibold text-slate-600">{{ __('competition.schedule') }}</button>
                                        @if ($m->status->value === 'scheduled')
                                            <button type="button" wire:click="postponeMatch({{ $m->id }})" class="font-semibold text-slate-600">{{ __('competition.postpone') }}</button>
                                        @endif
                                    @endif
                                    @if ($canManage && $hasFeature && $m->is_manual && ! $played)
                                        <button type="button" wire:click="deleteMatch({{ $m->id }})" wire:confirm="{{ __('competition.confirm_delete_match') }}" class="font-semibold text-rose-700">{{ __('competition.delete_match') }}</button>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($canResult && $hasFeature && $open && $unit !== 'games')
                <form wire:submit="importResults" class="glass space-y-3 rounded-2xl p-5">
                    <h3 class="text-sm font-bold text-slate-900">{{ __('competition.import_title') }}</h3>
                    <p class="text-xs text-slate-500">{{ __('competition.import_hint') }}</p>
                    <textarea wire:model="importText" rows="5" class="w-full rounded-lg border-slate-300 text-sm"></textarea>
                    @error('importText') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">{{ __('competition.import_submit') }}</button>
                </form>
            @endif

            @if ($canManage && $hasFeature && $comp->status->value !== 'finished' && $stage->format->value === 'round_robin')
                <form wire:submit="addManualMatch" class="glass space-y-3 rounded-2xl p-5">
                    <h3 class="text-sm font-bold text-slate-900">{{ __('competition.manual_title') }}</h3>
                    <div class="grid gap-3 sm:grid-cols-4">
                        <input type="number" min="1" max="999" wire:model="xRound" class="rounded-lg border-slate-300 text-sm" placeholder="{{ __('competition.round_label') }}">
                        <select wire:model="xA" class="rounded-lg border-slate-300 text-sm">
                            <option value="">{{ __('competition.team_a') }}</option>
                            @foreach ($competitors as $c) @if ($c->status->value !== 'withdrawn') <option value="{{ $c->id }}">{{ $c->name }}</option> @endif @endforeach
                        </select>
                        <select wire:model="xB" class="rounded-lg border-slate-300 text-sm">
                            <option value="">{{ __('competition.team_b') }}</option>
                            @foreach ($competitors as $c) @if ($c->status->value !== 'withdrawn') <option value="{{ $c->id }}">{{ $c->name }}</option> @endif @endforeach
                        </select>
                        <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">{{ __('competition.manual_add') }}</button>
                    </div>
                </form>
            @endif
        @endif

    {{-- ============================ رده‌بندی ============================ --}}
    @elseif ($section === 'standings')
        @if ($stages->count() > 1)
            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                @foreach ($stages as $st)
                    <button type="button" wire:click="selectStage('{{ $st->id }}')" wire:key="stage-tab-{{ $st->id }}"
                        class="rounded-lg px-3 py-2 {{ $stage && $stage->id === $st->id ? 'bg-slate-800 text-white' : 'border border-slate-200 bg-white text-slate-600' }}">{{ $st->name }} · {{ $st->status->label() }}</button>
                @endforeach
            </div>
        @endif

        @if ($stage && $stage->format->value === 'groups')
            @php $advanceCount = (int) $stage->config('advance_count', 2); @endphp
            @forelse ($groupTables as $gName => $gRows)
                <div class="glass overflow-x-auto rounded-2xl p-5" wire:key="grp-{{ $gName }}">
                    <h3 class="mb-2 text-sm font-bold text-indigo-700">{{ __('competition.group_name', ['name' => $gName]) }}</h3>
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
                            @foreach ($gRows as $i => $row)
                                <tr class="border-b border-slate-50 {{ $i < $advanceCount ? 'bg-emerald-50/60' : '' }}" wire:key="st-{{ $gName }}-{{ $row['competitor_id'] }}">
                                    <td class="py-2 tabular-nums">{{ fa_num($row['rank']) }}@if ($row['tied']) <span title="{{ __('competition.tied') }}">=</span> @endif</td>
                                    <td class="font-medium">{{ $row['name'] }}</td>
                                    @foreach (['played', 'won', 'drawn', 'lost', 'for', 'against', 'diff', 'points'] as $col)
                                        <td class="text-center tabular-nums {{ $col === 'points' ? 'font-bold' : '' }}">{{ fa_num($row[$col]) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @empty
                <div class="glass rounded-2xl"><p class="py-8 text-center text-sm text-slate-500">{{ __('competition.no_standings') }}</p></div>
            @endforelse
            <p class="text-xs text-slate-500">{{ __('competition.advance_note', ['count' => fa_num($advanceCount)]) }}</p>
        @elseif ($stage && $stage->format->value === 'single_elim')
            <div class="glass rounded-2xl p-5">
                @if ($standingRows === [])
                    <p class="py-8 text-center text-sm text-slate-500">{{ __('competition.standings_knockout_hint') }}</p>
                @else
                    <ol class="space-y-1 text-sm">
                        @foreach ($standingRows as $entry)
                            <li class="tabular-nums" wire:key="rk-{{ $entry->id }}">{{ fa_num($entry->final_rank) }}. {{ $entry->competitor->name }}</li>
                        @endforeach
                    </ol>
                @endif
            </div>
        @else
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
        @endif

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
