@php
    use App\Enums\RiskLevel;

    $input = 'block w-full rounded-xl border border-ink-200 bg-white px-3 py-2.5 text-sm text-ink-900 placeholder:text-ink-300 focus:border-pool-400 focus:outline-none focus:ring-2 focus:ring-pool-100';
    $label = 'mb-1 block text-xs font-semibold text-ink-600';
    $err = 'mt-1 text-xs text-signal-600';
    $canEdit = $canManage && $basic && ! $closed;
    $canReportNow = $canReport && $full && ! $closed;
    $sections = ['register' => __('risk.section_register')];
    if ($full) {
        $sections['matrix'] = __('risk.section_matrix');
        $sections['incidents'] = __('risk.section_incidents');
        if ($canManage) {
            $sections['templates'] = __('risk.section_templates');
        }
    }
@endphp

<div class="space-y-5">
    @if ($error)
        <p class="rounded-xl bg-signal-50 px-4 py-2.5 text-xs font-semibold text-signal-600" role="alert">{{ $error }}</p>
    @endif
    @if ($notice)
        <p class="rounded-xl bg-pool-50 px-4 py-2.5 text-xs font-semibold text-pool-700" role="status">{{ $notice }}</p>
    @endif

    {{-- نوار بخش‌ها --}}
    @if (count($sections) > 1)
        <div class="flex gap-1 overflow-x-auto rounded-xl border border-ink-200 bg-white p-1 text-xs font-semibold" role="tablist">
            @foreach ($sections as $key => $text)
                <button type="button" role="tab" aria-selected="{{ $section === $key ? 'true' : 'false' }}"
                        wire:click="setSection('{{ $key }}')" wire:key="rsec-{{ $key }}"
                        @class([
                            'shrink-0 rounded-lg px-3.5 py-2 transition-colors',
                            'bg-pool-600 text-white' => $section === $key,
                            'text-ink-600 hover:bg-ink-50' => $section !== $key,
                        ])>{{ $text }}</button>
            @endforeach
        </div>
    @endif

    @if ($closed)
        <p class="rounded-xl bg-flag-300/40 px-4 py-2.5 text-xs font-semibold text-ink-800">{{ __('risk.event_closed') }}</p>
    @endif

    @if ($canManage && ! $full)
        <p class="rounded-xl border border-pool-100 bg-pool-50 px-4 py-3 text-xs leading-6 text-pool-800">
            {{ __('risk.basic_notice') }}
            <a href="{{ route('account.index') }}" wire:navigate class="font-bold underline">{{ __('risk.upgrade') }}</a>
        </p>
    @endif

    {{-- خلاصه --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-2xl border border-ink-100 bg-white p-4">
            <p class="text-xs font-semibold text-ink-500">{{ __('risk.summary_active') }}</p>
            <p class="mt-2 text-lg font-bold tabular-nums text-ink-900">{{ fa_num($summary['total']) }}</p>
            <div class="mt-2 flex flex-wrap gap-1">
                @foreach (RiskLevel::cases() as $level)
                    @if ($summary['by_level'][$level->value] > 0)
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $level->badgeClasses() }}">{{ $level->label() }}: {{ fa_num($summary['by_level'][$level->value]) }}</span>
                    @endif
                @endforeach
            </div>
        </div>
        <div class="rounded-2xl border border-ink-100 bg-white p-4">
            <p class="text-xs font-semibold text-ink-500">{{ __('risk.summary_overdue') }}</p>
            <p @class(['mt-2 text-lg font-bold tabular-nums', 'text-signal-600' => $summary['review_overdue'] > 0, 'text-ink-900' => $summary['review_overdue'] === 0])>{{ fa_num($summary['review_overdue']) }}</p>
        </div>
        <div class="rounded-2xl border border-ink-100 bg-white p-4">
            <p class="text-xs font-semibold text-ink-500">{{ __('risk.summary_soon') }}</p>
            <p class="mt-2 text-lg font-bold tabular-nums text-ink-900">{{ fa_num($summary['review_soon']) }}</p>
        </div>
        <div class="rounded-2xl border border-ink-100 bg-white p-4">
            <p class="text-xs font-semibold text-ink-500">{{ __('risk.summary_occurred') }}</p>
            <p class="mt-2 text-lg font-bold tabular-nums text-ink-900">{{ fa_num($summary['occurred']) }}</p>
        </div>
    </div>

    {{-- ====================================================== ثبت ریسک --}}
    @if ($section === 'register')
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label for="rf-status" class="{{ $label }}">{{ __('risk.filter_status') }}</label>
                    <select id="rf-status" wire:model.live="filterStatus" class="{{ $input }} py-2">
                        <option value="active">{{ __('risk.filter_active') }}</option>
                        <option value="all">{{ __('risk.filter_all') }}</option>
                        @foreach ($statuses as $st)
                            <option value="{{ $st->value }}">{{ $st->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="rf-cat" class="{{ $label }}">{{ __('risk.filter_category') }}</label>
                    <select id="rf-cat" wire:model.live="filterCategory" class="{{ $input }} py-2">
                        <option value="">{{ __('risk.filter_all') }}</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($filterCell !== '')
                    @php [$fl, $fi] = explode('-', $filterCell); @endphp
                    <button type="button" wire:click="clearCell"
                            class="rounded-xl border border-ink-200 bg-white px-3 py-2 text-xs font-semibold text-ink-700 hover:bg-ink-50">
                        {{ __('risk.filter_cell', ['l' => fa_num($fl), 'i' => fa_num($fi)]) }} ✕
                    </button>
                @endif
            </div>

            @if ($canEdit)
                <button type="button" wire:click="openRiskForm" class="btn-primary">{{ __('risk.add_risk') }}</button>
            @endif
        </div>

        {{-- فرم ریسک --}}
        @if ($showRiskForm && $canEdit)
            <form wire:submit="saveRisk" class="space-y-4 rounded-2xl border border-ink-100 bg-white p-4 sm:p-5" wire:key="risk-form">
                <h3 class="text-sm font-bold text-ink-900">{{ $editingRiskId ? __('risk.edit_risk') : __('risk.add_risk') }}</h3>

                <div>
                    <label for="r-title" class="{{ $label }}">{{ __('risk.fields.title') }}</label>
                    <input id="r-title" type="text" wire:model="rTitle" class="{{ $input }}" maxlength="160">
                    @error('rTitle') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label for="r-cat" class="{{ $label }}">{{ __('risk.fields.category') }}</label>
                        <select id="r-cat" wire:model="rCategory" class="{{ $input }}">
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                            @endforeach
                        </select>
                        @error('rCategory') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="r-lik" class="{{ $label }}">{{ __('risk.fields.likelihood') }}</label>
                        <select id="r-lik" wire:model="rLikelihood" class="{{ $input }}">
                            @foreach ([1, 2, 3, 4, 5] as $n)
                                <option value="{{ $n }}">{{ fa_num($n) }} — {{ __('risk.scale.likelihood.'.$n) }}</option>
                            @endforeach
                        </select>
                        @error('rLikelihood') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="r-imp" class="{{ $label }}">{{ __('risk.fields.impact') }}</label>
                        <select id="r-imp" wire:model="rImpact" class="{{ $input }}">
                            @foreach ([1, 2, 3, 4, 5] as $n)
                                <option value="{{ $n }}">{{ fa_num($n) }} — {{ __('risk.scale.impact.'.$n) }}</option>
                            @endforeach
                        </select>
                        @error('rImpact') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label for="r-resp" class="{{ $label }}">{{ __('risk.fields.response') }}</label>
                        <select id="r-resp" wire:model="rResponse" class="{{ $input }}">
                            @foreach ($responses as $resp)
                                <option value="{{ $resp->value }}">{{ $resp->label() }}</option>
                            @endforeach
                        </select>
                        @error('rResponse') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="r-owner" class="{{ $label }}">{{ __('risk.fields.owner') }}</label>
                        <select id="r-owner" wire:model="rOwnerId" class="{{ $input }}">
                            <option value="">{{ __('risk.no_owner') }}</option>
                            @foreach ($owners as $member)
                                <option value="{{ $member->id }}">{{ $member->name ?: phone($member->phone) }}</option>
                            @endforeach
                        </select>
                        @error('rOwnerId') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="r-status" class="{{ $label }}">{{ __('risk.fields.status') }}</label>
                        <select id="r-status" wire:model="rStatus" class="{{ $input }}">
                            @foreach ($statuses as $st)
                                <option value="{{ $st->value }}">{{ $st->label() }}</option>
                            @endforeach
                        </select>
                        @error('rStatus') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="r-desc" class="{{ $label }}">{{ __('risk.fields.description') }}</label>
                    <textarea id="r-desc" wire:model="rDescription" rows="2" class="{{ $input }}" maxlength="2000"></textarea>
                    @error('rDescription') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="r-ctl" class="{{ $label }}">{{ __('risk.fields.control_actions') }}</label>
                    <textarea id="r-ctl" wire:model="rControl" rows="2" class="{{ $input }}" maxlength="2000"></textarea>
                    @error('rControl') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>
                <div class="sm:w-1/3">
                    <label for="r-review" class="{{ $label }}">{{ __('risk.fields.review_date') }}</label>
                    <x-jalali-picker id="r-review" wire:model="rReview" />
                    @error('rReview') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="btn-primary">{{ __('risk.save') }}</button>
                    <button type="button" wire:click="cancelRiskForm" class="btn-ghost">{{ __('risk.cancel') }}</button>
                </div>
            </form>
        @endif

        {{-- فهرست --}}
        @if ($risks->isEmpty())
            <div class="rounded-2xl border border-dashed border-ink-200 bg-white px-6 py-12 text-center">
                @if ($summary['total'] === 0 && $filterStatus === 'active' && $filterCategory === '' && $filterCell === '')
                    <h3 class="text-base font-bold text-ink-900">{{ __('risk.empty_title') }}</h3>
                    <p class="mx-auto mt-2 max-w-sm text-sm leading-7 text-ink-500">{{ __('risk.empty_desc') }}</p>
                @else
                    <p class="text-sm text-ink-500">{{ __('risk.none_filtered') }}</p>
                @endif
            </div>
        @else
            <ul class="space-y-3">
                @foreach ($risks as $risk)
                    @php $level = $risk->level(); @endphp
                    <li class="rounded-2xl border border-ink-100 bg-white p-4" wire:key="risk-{{ $risk->id }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $level->badgeClasses() }}">{{ $level->label() }} · {{ __('risk.score_label', ['score' => fa_num($risk->score)]) }}</span>
                                    <span class="rounded-full bg-ink-50 px-2.5 py-0.5 text-[11px] font-semibold text-ink-600">{{ $risk->category->label() }}</span>
                                    <span class="rounded-full bg-ink-50 px-2.5 py-0.5 text-[11px] font-semibold text-ink-600">{{ $risk->status->label() }}</span>
                                    @if ($risk->isReviewOverdue())
                                        <span class="rounded-full bg-signal-50 px-2.5 py-0.5 text-[11px] font-semibold text-signal-600">{{ __('risk.review_overdue') }}</span>
                                    @endif
                                </div>
                                <h4 class="mt-2 text-sm font-bold text-ink-900">{{ $risk->title }}</h4>
                                @if ($risk->description)
                                    <p class="mt-1 text-xs leading-6 text-ink-600">{{ $risk->description }}</p>
                                @endif
                                <p class="mt-2 text-[11px] text-ink-500">
                                    {{ __('risk.likelihood_short') }}: {{ fa_num($risk->likelihood) }}
                                    · {{ __('risk.impact_short') }}: {{ fa_num($risk->impact) }}
                                    · {{ $risk->response->label() }}
                                    · {{ $risk->owner ? ($risk->owner->name ?: phone($risk->owner->phone)) : __('risk.no_owner') }}
                                    · {{ $risk->review_date ? __('risk.review_on', ['date' => jalali_long($risk->review_date)]) : __('risk.no_review_date') }}
                                </p>
                                @if ($risk->control_actions)
                                    <p class="mt-2 rounded-xl bg-pool-50 px-3 py-2 text-xs leading-6 text-pool-800">{{ $risk->control_actions }}</p>
                                @endif
                                <x-last-action class="mt-2" :subject="$risk" />
                            </div>

                            <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                                <button type="button" wire:click="toggleRisk({{ $risk->id }})"
                                        class="rounded-lg border border-ink-200 px-2.5 py-1.5 text-xs font-semibold text-ink-700 hover:bg-ink-50">
                                    {{ __('risk.details') }} ({{ fa_num($risk->tasks->count()) }})
                                </button>
                                @if ($canEdit)
                                    <select wire:change="changeStatus({{ $risk->id }}, $event.target.value)" aria-label="{{ __('risk.fields.status') }}"
                                            class="rounded-lg border border-ink-200 bg-white px-2 py-1.5 text-xs text-ink-700">
                                        @foreach ($statuses as $st)
                                            <option value="{{ $st->value }}" @selected($risk->status === $st)>{{ $st->label() }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" wire:click="editRisk({{ $risk->id }})"
                                            class="rounded-lg border border-ink-200 px-2.5 py-1.5 text-xs font-semibold text-ink-700 hover:bg-ink-50">{{ __('risk.edit') }}</button>
                                    <button type="button" wire:click="deleteRisk({{ $risk->id }})" wire:confirm="{{ __('risk.confirm_delete_risk') }}"
                                            class="rounded-lg border border-signal-200 px-2.5 py-1.5 text-xs font-semibold text-signal-600 hover:bg-signal-50">{{ __('risk.delete') }}</button>
                                @endif
                            </div>
                        </div>

                        {{-- وظایف کنترلی --}}
                        @if ($expandedRiskId === $risk->id)
                            <div class="mt-4 space-y-3 border-t border-ink-100 pt-4">
                                <h5 class="text-xs font-bold text-ink-700">{{ __('risk.control_tasks') }}</h5>

                                @if ($risk->tasks->isEmpty())
                                    <p class="text-xs text-ink-500">{{ __('risk.control_tasks_empty') }}</p>
                                @else
                                    <ul class="space-y-1.5">
                                        @foreach ($risk->tasks as $task)
                                            <li class="flex items-center justify-between gap-2 rounded-xl bg-ink-50 px-3 py-2 text-xs" wire:key="rt-{{ $risk->id }}-{{ $task->id }}">
                                                <span class="min-w-0 flex-1 truncate font-semibold text-ink-800">{{ $task->title }}</span>
                                                <span class="shrink-0 text-ink-500">{{ $task->status->label() }}</span>
                                                @if ($canEdit)
                                                    <button type="button" wire:click="unlinkTask({{ $risk->id }}, {{ $task->id }})"
                                                            class="shrink-0 font-semibold text-signal-600 hover:underline">{{ __('risk.unlink') }}</button>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                @if ($canEdit)
                                    <div class="grid gap-4 lg:grid-cols-2">
                                        {{-- ۱) ساخت وظیفه‌ی کنترلیِ تازه --}}
                                        <div class="rounded-2xl border border-pool-100 bg-pool-50/40 p-4">
                                            <h6 class="text-xs font-bold text-pool-700">{{ __('risk.new_control_task') }}</h6>
                                            <p class="mt-1 text-[11px] leading-5 text-ink-500">{{ __('risk.new_control_task_hint') }}</p>
                                            <div class="mt-3 space-y-2">
                                                <input type="text" wire:model="ctTitle" placeholder="{{ __('risk.control_task_title') }}" class="{{ $input }}" maxlength="200" aria-label="{{ __('risk.control_task_title') }}">
                                                <select wire:model="ctCommitteeId" class="{{ $input }}" aria-label="{{ __('risk.control_task_committee') }}">
                                                    <option value="">{{ __('risk.no_committee') }}</option>
                                                    @foreach ($committees as $c)
                                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="button" wire:click="createControlTask({{ $risk->id }})" class="btn-primary w-full">{{ __('risk.create_control_task') }}</button>
                                            </div>
                                        </div>

                                        {{-- ۲) پیوند وظیفه‌ی موجود (حتی از کمیته‌ای دیگر) --}}
                                        <div class="rounded-2xl border border-ink-200 bg-white p-4">
                                            <h6 class="text-xs font-bold text-ink-700">{{ __('risk.link_existing_task') }}</h6>
                                            <p class="mt-1 text-[11px] leading-5 text-ink-500">{{ __('risk.link_existing_task_hint') }}</p>
                                            <div class="mt-3 space-y-2">
                                                <select wire:model.live="linkCommitteeId" class="{{ $input }}" aria-label="{{ __('risk.filter_by_committee') }}">
                                                    <option value="">{{ __('risk.all_committees') }}</option>
                                                    <option value="none">{{ __('risk.no_committee') }}</option>
                                                    @foreach ($committees as $c)
                                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                                    @endforeach
                                                </select>
                                                <select wire:model="ctTaskId" class="{{ $input }}" aria-label="{{ __('risk.link_task') }}">
                                                    <option value="">{{ __('risk.choose_task') }}</option>
                                                    @foreach ($linkableTasks as $lt)
                                                        <option value="{{ $lt->id }}">{{ $lt->title }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="button" wire:click="linkTask({{ $risk->id }})" class="btn-ghost w-full">{{ __('risk.link') }}</button>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <x-activity-log :subject="$risk" :limit="6" class="rounded-2xl bg-ink-50/60 p-3" />
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

    {{-- ========================================================= ماتریس --}}
    @elseif ($section === 'matrix' && $full)
        <div class="rounded-2xl border border-ink-100 bg-white p-4 sm:p-5">
            <h3 class="text-sm font-bold text-ink-900">{{ __('risk.matrix_title') }}</h3>
            <p class="mt-1 text-xs leading-6 text-ink-500">{{ __('risk.matrix_hint') }}</p>

            <div class="mt-4 overflow-x-auto">
                <div class="min-w-[26rem]">
                    <p class="mb-1 text-[11px] font-semibold text-ink-500">{{ __('risk.matrix_axis_impact') }}</p>
                    @foreach ([5, 4, 3, 2, 1] as $impact)
                        <div class="mb-1.5 flex items-stretch gap-1.5">
                            <div class="flex w-20 shrink-0 items-center text-[11px] leading-4 text-ink-500">
                                {{ fa_num($impact) }} · {{ __('risk.scale.impact.'.$impact) }}
                            </div>
                            @foreach ([1, 2, 3, 4, 5] as $likelihood)
                                @php
                                    $cellKey = $likelihood.'-'.$impact;
                                    $count = isset($matrix[$cellKey]) ? count($matrix[$cellKey]) : 0;
                                    $cellLevel = RiskLevel::fromScore($likelihood * $impact);
                                @endphp
                                <button type="button" wire:click="setCell('{{ $cellKey }}')" wire:key="mc-{{ $cellKey }}"
                                        aria-label="{{ __('risk.matrix_cell_label', ['l' => fa_num($likelihood), 'i' => fa_num($impact), 'count' => fa_num($count)]) }}"
                                        @class([
                                            'flex h-14 min-w-0 flex-1 items-center justify-center rounded-lg text-sm font-bold tabular-nums transition-transform hover:scale-[1.03] focus:outline-none focus:ring-2 focus:ring-pool-400',
                                            $cellLevel->cellClasses(),
                                            'ring-2 ring-ink-900' => $filterCell === $cellKey,
                                        ])>
                                    {{ $count > 0 ? fa_num($count) : '·' }}
                                </button>
                            @endforeach
                        </div>
                    @endforeach
                    <div class="flex gap-1.5">
                        <div class="w-20 shrink-0"></div>
                        @foreach ([1, 2, 3, 4, 5] as $likelihood)
                            <div class="min-w-0 flex-1 text-center text-[11px] leading-4 text-ink-500">{{ fa_num($likelihood) }}<br>{{ __('risk.scale.likelihood.'.$likelihood) }}</div>
                        @endforeach
                    </div>
                    <p class="mt-2 text-[11px] font-semibold text-ink-500">{{ __('risk.matrix_axis_likelihood') }}</p>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-1.5">
                @foreach (RiskLevel::cases() as $lv)
                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $lv->badgeClasses() }}">{{ $lv->label() }}</span>
                @endforeach
            </div>
        </div>

    {{-- ======================================================= چک‌لیست‌ها --}}
    @elseif ($section === 'templates' && $full && $canManage)
        <p class="rounded-xl border border-flag-300 bg-flag-300/30 px-4 py-3 text-xs font-semibold leading-6 text-ink-800">{{ __('risk.templates_notice') }}</p>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($templates as $tKey => $template)
                <div class="rounded-2xl border border-ink-100 bg-white p-4" wire:key="tpl-{{ $tKey }}">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-bold text-ink-900">{{ $template['title'] }}</h3>
                        @if ($canEdit)
                            <button type="button" wire:click="applyTemplate('{{ $tKey }}')" class="btn-ghost text-xs">{{ __('risk.add_all') }}</button>
                        @endif
                    </div>
                    <ul class="mt-3 space-y-2">
                        @foreach ($template['items'] as $item)
                            @php $added = in_array($item['key'], $existingTemplateKeys, true); @endphp
                            <li class="rounded-xl bg-ink-50 px-3 py-2.5" wire:key="tpli-{{ $item['key'] }}">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-xs font-semibold leading-6 text-ink-800">{{ $item['title'] }}</p>
                                    @if ($added)
                                        <span class="shrink-0 text-[11px] font-semibold text-pool-700">{{ __('risk.already_added') }}</span>
                                    @elseif ($canEdit)
                                        <button type="button" wire:click="applyTemplateItem('{{ $tKey }}', '{{ $item['key'] }}')"
                                                class="shrink-0 text-[11px] font-bold text-pool-700 hover:underline">{{ __('risk.add_item') }}</button>
                                    @endif
                                </div>
                                <p class="mt-1 text-[11px] leading-5 text-ink-500">{{ $item['control_actions'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

    {{-- ========================================================== حادثه --}}
    @elseif ($section === 'incidents' && $full)
        <div class="flex justify-end">
            @if ($canReportNow)
                <button type="button" wire:click="openIncidentForm" class="btn-primary">{{ __('risk.add_incident') }}</button>
            @endif
        </div>

        @if ($showIncidentForm && $canReportNow)
            <form wire:submit="saveIncident" class="space-y-4 rounded-2xl border border-ink-100 bg-white p-4 sm:p-5" wire:key="incident-form">
                <h3 class="text-sm font-bold text-ink-900">{{ $editingIncidentId ? __('risk.edit_incident') : __('risk.add_incident') }}</h3>

                <div>
                    <label for="i-title" class="{{ $label }}">{{ __('risk.fields.incident_title') }}</label>
                    <input id="i-title" type="text" wire:model="iTitle" class="{{ $input }}" maxlength="160">
                    @error('iTitle') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-3 sm:grid-cols-4">
                    <div>
                        <label for="i-sev" class="{{ $label }}">{{ __('risk.fields.severity') }}</label>
                        <select id="i-sev" wire:model="iSeverity" class="{{ $input }}">
                            @foreach ($severities as $sev)
                                <option value="{{ $sev->value }}">{{ $sev->label() }}</option>
                            @endforeach
                        </select>
                        @error('iSeverity') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="i-date" class="{{ $label }}">{{ __('risk.fields.occurred_date') }}</label>
                        <x-jalali-picker id="i-date" wire:model="iDate" />
                        @error('iDate') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="i-time" class="{{ $label }}">{{ __('risk.fields.occurred_time') }}</label>
                        <input id="i-time" type="text" dir="ltr" wire:model="iTime" placeholder="۱۴:۳۰" class="{{ $input }}">
                        @error('iTime') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="i-risk" class="{{ $label }}">{{ __('risk.fields.related_risk') }}</label>
                        <select id="i-risk" wire:model="iRiskId" class="{{ $input }}">
                            <option value="">{{ __('risk.no_risk') }}</option>
                            @foreach ($allRisks as $r)
                                <option value="{{ $r->id }}">{{ $r->title }}</option>
                            @endforeach
                        </select>
                        @error('iRiskId') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="i-desc" class="{{ $label }}">{{ __('risk.fields.incident_description') }}</label>
                    <textarea id="i-desc" wire:model="iDescription" rows="2" class="{{ $input }}" maxlength="2000"></textarea>
                    @error('iDescription') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="btn-primary">{{ __('risk.save') }}</button>
                    <button type="button" wire:click="cancelIncidentForm" class="btn-ghost">{{ __('risk.cancel') }}</button>
                </div>
            </form>
        @endif

        @if ($incidents->isEmpty())
            <div class="rounded-2xl border border-dashed border-ink-200 bg-white px-6 py-12 text-center">
                <h3 class="text-base font-bold text-ink-900">{{ __('risk.incidents_empty_title') }}</h3>
                <p class="mx-auto mt-2 max-w-sm text-sm leading-7 text-ink-500">{{ __('risk.incidents_empty_desc') }}</p>
            </div>
        @else
            <ul class="space-y-3">
                @foreach ($incidents as $incident)
                    <li class="rounded-2xl border border-ink-100 bg-white p-4" wire:key="inc-{{ $incident->id }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $incident->severity->badgeClasses() }}">{{ $incident->severity->label() }}</span>
                                    <span class="text-[11px] text-ink-500">{{ jalali_long($incident->occurred_at) }} · ساعت {{ fa_num($incident->occurred_at->format('H:i')) }}</span>
                                    <span @class(['rounded-full px-2.5 py-0.5 text-[11px] font-semibold', 'bg-pool-50 text-pool-700' => $incident->isResolved(), 'bg-ink-50 text-ink-600' => ! $incident->isResolved()])>
                                        {{ $incident->isResolved() ? __('risk.resolved') : __('risk.unresolved') }}
                                    </span>
                                </div>
                                <h4 class="mt-2 text-sm font-bold text-ink-900">{{ $incident->title }}</h4>
                                @if ($incident->description)
                                    <p class="mt-1 text-xs leading-6 text-ink-600">{{ $incident->description }}</p>
                                @endif
                                <x-audit-stamp class="mt-1" :at="$incident->created_at" :by="$incident->reporter" verb="گزارش شده" />
                                <x-last-action :subject="$incident" />
                                <p class="mt-2 text-[11px] text-ink-500">
                                    {{ $incident->risk ? $incident->risk->title : __('risk.no_risk') }}
                                    @if ($incident->reporter)
                                        · {{ __('risk.reported_by', ['name' => $incident->reporter->name ?: phone($incident->reporter->phone)]) }}
                                    @endif
                                </p>
                                @if ($incident->resolution)
                                    <p class="mt-2 rounded-xl bg-pool-50 px-3 py-2 text-xs leading-6 text-pool-800">{{ $incident->resolution }}</p>
                                @endif
                            </div>

                            @if ($canEdit)
                                <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                                    <button type="button" wire:click="startResolve({{ $incident->id }})"
                                            class="rounded-lg border border-ink-200 px-2.5 py-1.5 text-xs font-semibold text-ink-700 hover:bg-ink-50">{{ __('risk.resolve') }}</button>
                                    <button type="button" wire:click="editIncident({{ $incident->id }})"
                                            class="rounded-lg border border-ink-200 px-2.5 py-1.5 text-xs font-semibold text-ink-700 hover:bg-ink-50">{{ __('risk.edit') }}</button>
                                    <button type="button" wire:click="deleteIncident({{ $incident->id }})" wire:confirm="{{ __('risk.confirm_delete_incident') }}"
                                            class="rounded-lg border border-signal-200 px-2.5 py-1.5 text-xs font-semibold text-signal-600 hover:bg-signal-50">{{ __('risk.delete') }}</button>
                                </div>
                            @endif
                        </div>

                        @if ($resolvingIncidentId === $incident->id && $canEdit)
                            <form wire:submit="saveResolution" class="mt-4 space-y-3 border-t border-ink-100 pt-4">
                                <label for="i-res-{{ $incident->id }}" class="{{ $label }}">{{ __('risk.fields.resolution') }}</label>
                                <textarea id="i-res-{{ $incident->id }}" wire:model="resolutionText" rows="2" class="{{ $input }}" maxlength="2000"></textarea>
                                <div class="flex gap-2">
                                    <button type="submit" class="btn-primary">{{ __('risk.save') }}</button>
                                    <button type="button" wire:click="cancelResolve" class="btn-ghost">{{ __('risk.cancel') }}</button>
                                </div>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</div>
