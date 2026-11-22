@php
    use App\Enums\BudgetLineType;
    use App\Support\Money;

    $m = fn (int $value, bool $withUnit = true): string => Money::format($value, $unit, $withUnit);
    $input = 'block w-full rounded-xl border border-ink-200 bg-white px-3 py-2.5 text-sm text-ink-900 placeholder:text-ink-300 focus:border-pool-400 focus:outline-none focus:ring-2 focus:ring-pool-100';
    $label = 'mb-1 block text-xs font-semibold text-ink-600';
    $err = 'mt-1 text-xs text-signal-600';
    $canEdit = $canManage && $basic && ! $closed;
    $planEditable = $canEdit && ! $locked;
    $sections = ['overview' => __('budget.section_overview')];
    if ($canManage) {
        $sections['lines'] = __('budget.section_lines');
        $sections['transactions'] = __('budget.section_transactions');
        if ($full) {
            $sections['versions'] = __('budget.section_versions');
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

    @if (! $canSummary)
        <div class="rounded-2xl border border-dashed border-ink-200 bg-white px-6 py-14 text-center">
            <h3 class="text-base font-bold text-ink-900">{{ __('budget.no_access_title') }}</h3>
            <p class="mx-auto mt-2 max-w-sm text-sm leading-7 text-ink-500">{{ __('budget.no_access_desc') }}</p>
        </div>
    @else
        {{-- نوار بخش‌ها + واحد پول --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-1 overflow-x-auto rounded-xl border border-ink-200 bg-white p-1 text-xs font-semibold" role="tablist">
                @foreach ($sections as $key => $text)
                    <button type="button" role="tab" aria-selected="{{ $section === $key ? 'true' : 'false' }}"
                            wire:click="setSection('{{ $key }}')" wire:key="bsec-{{ $key }}"
                            @class([
                                'shrink-0 rounded-lg px-3.5 py-2 transition-colors',
                                'bg-pool-600 text-white' => $section === $key,
                                'text-ink-600 hover:bg-ink-50' => $section !== $key,
                            ])>{{ $text }}</button>
                @endforeach
            </div>

            <div class="inline-flex rounded-xl border border-ink-200 bg-white p-1 text-xs font-semibold" role="group" aria-label="{{ __('budget.unit') }}">
                @foreach ([Money::RIAL => __('budget.unit_rial'), Money::TOMAN => __('budget.unit_toman')] as $key => $text)
                    <button type="button" wire:click="setUnit('{{ $key }}')" wire:key="bunit-{{ $key }}"
                            aria-pressed="{{ $unit === $key ? 'true' : 'false' }}"
                            @class([
                                'rounded-lg px-3 py-1.5 transition-colors',
                                'bg-flag-500 text-ink-950' => $unit === $key,
                                'text-ink-600 hover:bg-ink-50' => $unit !== $key,
                            ])>{{ $text }}</button>
                @endforeach
            </div>
        </div>

        @if ($closed)
            <p class="rounded-xl bg-flag-300/40 px-4 py-2.5 text-xs font-semibold text-ink-800">{{ __('budget.event_closed') }}</p>
        @endif

        @if ($canManage && ! $full)
            <p class="rounded-xl border border-pool-100 bg-pool-50 px-4 py-3 text-xs leading-6 text-pool-800">
                {{ __('budget.basic_notice') }}
                <a href="{{ route('account.index') }}" wire:navigate class="font-bold underline">{{ __('budget.upgrade') }}</a>
            </p>
        @endif

        {{-- ====================================================== خلاصه --}}
        @if ($section === 'overview')
            <div class="grid gap-3 sm:grid-cols-3">
                {{-- درآمد --}}
                <div class="rounded-2xl border border-ink-100 bg-white p-4">
                    <p class="text-xs font-semibold text-ink-500">{{ __('budget.total_revenue') }}</p>
                    <p class="mt-2 text-lg font-bold tabular-nums text-ink-900"><bdi>{{ $m($totals['actual_revenue']) }}</bdi></p>
                    <p class="mt-1 text-xs text-ink-500">{{ __('budget.planned') }}: <bdi class="tabular-nums">{{ $m($totals['planned_revenue']) }}</bdi></p>
                    @if ($totals['revenue_achievement_percent'] !== null)
                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-ink-100" role="img" aria-label="{{ fa_num($totals['revenue_achievement_percent']) }}٪">
                            <div class="h-full rounded-full bg-pool-600" style="width: {{ min(100, (int) round($totals['revenue_achievement_percent'])) }}%"></div>
                        </div>
                        <p class="mt-1 text-[11px] text-ink-400">{{ __('budget.revenue_achieved', ['percent' => fa_num($totals['revenue_achievement_percent'])]) }}</p>
                    @endif
                </div>

                {{-- هزینه --}}
                <div class="rounded-2xl border border-ink-100 bg-white p-4">
                    <p class="text-xs font-semibold text-ink-500">{{ __('budget.total_expense') }}</p>
                    <p class="mt-2 text-lg font-bold tabular-nums text-ink-900"><bdi>{{ $m($totals['actual_expense']) }}</bdi></p>
                    <p class="mt-1 text-xs text-ink-500">{{ __('budget.planned') }}: <bdi class="tabular-nums">{{ $m($totals['planned_expense']) }}</bdi></p>
                    @if ($totals['expense_usage_percent'] !== null)
                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-ink-100" role="img" aria-label="{{ fa_num($totals['expense_usage_percent']) }}٪">
                            <div @class(['h-full rounded-full', 'bg-signal-500' => $totals['expense_overrun'], 'bg-flag-500' => ! $totals['expense_overrun']])
                                 style="width: {{ min(100, (int) round($totals['expense_usage_percent'])) }}%"></div>
                        </div>
                        <p class="mt-1 text-[11px] text-ink-400">{{ __('budget.expense_used', ['percent' => fa_num($totals['expense_usage_percent'])]) }}</p>
                    @endif
                </div>

                {{-- تراز --}}
                <div class="rounded-2xl border border-ink-100 bg-white p-4">
                    <p class="text-xs font-semibold text-ink-500">{{ __('budget.balance') }}</p>
                    <p @class(['mt-2 text-lg font-bold tabular-nums', 'text-signal-600' => $totals['actual_balance'] < 0, 'text-ink-900' => $totals['actual_balance'] >= 0])>
                        <bdi>{{ $m($totals['actual_balance']) }}</bdi>
                    </p>
                    <p class="mt-1 text-xs text-ink-500">{{ __('budget.planned') }}:
                        <bdi class="tabular-nums">{{ $m($totals['planned_balance']) }}</bdi>
                    </p>
                    <p class="mt-3 text-[11px] leading-5 text-ink-400">{{ __('budget.balance_hint') }}</p>
                </div>
            </div>

            @if ($totals['line_count'] === 0)
                <div class="rounded-2xl border border-dashed border-ink-200 bg-white px-6 py-12 text-center">
                    <h3 class="text-base font-bold text-ink-900">{{ __('budget.empty_title') }}</h3>
                    <p class="mx-auto mt-2 max-w-sm text-sm leading-7 text-ink-500">{{ __('budget.empty_desc') }}</p>
                    @if ($planEditable)
                        <button type="button" wire:click="openLineForm" class="btn-primary mt-5">{{ __('budget.add_line') }}</button>
                    @endif
                </div>
            @endif

            @if (! $canManage)
                <p class="rounded-xl bg-ink-50 px-4 py-3 text-xs leading-6 text-ink-600">{{ __('budget.summary_only') }}</p>
            @else
                {{-- هشدار اضافه‌هزینه --}}
                @if ($totals['expense_overrun'] || count($overrunLines) > 0)
                    <div class="rounded-2xl border border-signal-500/30 bg-signal-50 p-4">
                        <h3 class="text-sm font-bold text-signal-600">{{ __('budget.overrun_title', ['percent' => fa_num($totals['threshold'])]) }}</h3>
                        @if ($totals['expense_overrun'])
                            <p class="mt-1 text-xs text-ink-700">{{ __('budget.overrun_total') }}</p>
                        @endif
                        <ul class="mt-2 space-y-1.5">
                            @foreach ($overrunLines as $alert)
                                <li class="flex flex-wrap items-center justify-between gap-2 text-xs text-ink-800" wire:key="overrun-{{ $alert['line']->id }}">
                                    <span class="font-semibold">{{ $alert['line']->name }}</span>
                                    <span class="tabular-nums">
                                        +<bdi>{{ $m($alert['excess']) }}</bdi>
                                        @if ($alert['percent'] !== null)
                                            ({{ fa_num($alert['percent']) }}٪)
                                        @else
                                            ({{ __('budget.unplanned') }})
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- به تفکیک دسته --}}
                @if (count($byCategory) > 0)
                    <div class="overflow-hidden rounded-2xl border border-ink-100 bg-white">
                        <h3 class="border-b border-ink-100 px-4 py-3 text-sm font-bold text-ink-900">{{ __('budget.by_category') }}</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[30rem] text-start text-xs">
                                <thead class="bg-ink-50 text-ink-500">
                                    <tr>
                                        <th class="px-4 py-2 text-start font-semibold">{{ __('budget.fields.category') }}</th>
                                        <th class="px-4 py-2 text-start font-semibold">{{ __('budget.fields.type') }}</th>
                                        <th class="px-4 py-2 text-start font-semibold">{{ __('budget.planned') }}</th>
                                        <th class="px-4 py-2 text-start font-semibold">{{ __('budget.actual') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-ink-100">
                                    @foreach ($byCategory as $row)
                                        <tr wire:key="cat-{{ $row['category']->value }}">
                                            <td class="px-4 py-2.5 font-semibold text-ink-800">{{ $row['category']->label() }}</td>
                                            <td class="px-4 py-2.5 text-ink-500">{{ $row['type']->label() }}</td>
                                            <td class="px-4 py-2.5 tabular-nums"><bdi>{{ $m($row['planned'], false) }}</bdi></td>
                                            <td class="px-4 py-2.5 tabular-nums"><bdi>{{ $m($row['actual'], false) }}</bdi></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if ($full)
                    {{-- به تفکیک کمیته --}}
                    @if (count($byCommittee) > 0)
                        <div class="overflow-hidden rounded-2xl border border-ink-100 bg-white">
                            <h3 class="border-b border-ink-100 px-4 py-3 text-sm font-bold text-ink-900">{{ __('budget.by_committee') }}</h3>
                            <ul class="divide-y divide-ink-100">
                                @foreach ($byCommittee as $row)
                                    <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-xs" wire:key="com-{{ $row['committee_id'] ?? 'none' }}">
                                        <span class="font-semibold text-ink-800">{{ $row['name'] ?? __('budget.no_committee') }}</span>
                                        <span class="tabular-nums text-ink-600">
                                            <bdi>{{ $m($row['actual'], false) }}</bdi> / <bdi>{{ $m($row['planned']) }}</bdi>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- سربه‌سر --}}
                    <div class="rounded-2xl border border-ink-100 bg-white p-4">
                        <h3 class="text-sm font-bold text-ink-900">{{ __('budget.break_even') }}</h3>
                        @if ($breakEven['available'])
                            <p class="mt-2 text-sm text-ink-800">
                                {{ __('budget.break_even_result', ['count' => fa_num(number_format($breakEven['participants']))]) }}
                            </p>
                            <p class="mt-1 text-xs text-ink-500">
                                {{ __('budget.break_even_expected', ['count' => fa_num(number_format($breakEven['expected']))]) }}
                                @if ($breakEven['margin'] >= 0)
                                    — {{ __('budget.break_even_margin', ['count' => fa_num(number_format($breakEven['margin']))]) }}
                                @else
                                    — <span class="font-semibold text-signal-600">{{ __('budget.break_even_short', ['count' => fa_num(number_format(abs($breakEven['margin'])))]) }}</span>
                                @endif
                            </p>
                            <p class="mt-2 text-[11px] leading-5 text-ink-400">{{ __('budget.break_even_note') }}</p>
                        @else
                            <p class="mt-2 text-sm text-ink-600">{{ __('budget.insufficient_data') }}</p>
                            <p class="mt-1 text-xs leading-6 text-ink-400">{{ __('budget.break_even_reason.'.$breakEven['reason']) }}</p>
                        @endif
                    </div>

                    {{-- آستانه‌ی هشدار --}}
                    @if (! $closed)
                        <div class="rounded-2xl border border-ink-100 bg-white p-4">
                            <h3 class="text-sm font-bold text-ink-900">{{ __('budget.threshold_title') }}</h3>
                            <p class="mt-1 text-xs leading-6 text-ink-500">{{ __('budget.threshold_desc') }}</p>
                            <div class="mt-3 flex max-w-xs gap-2">
                                <input type="number" min="0" max="1000" wire:model="thresholdInput" dir="ltr" class="{{ $input }}" aria-label="{{ __('budget.fields.threshold') }}">
                                <button type="button" wire:click="saveThreshold" class="btn-ghost shrink-0">{{ __('ui.save') }}</button>
                            </div>
                            @error('thresholdInput') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                    @endif
                @endif
            @endif
        @endif

        {{-- ======================================================== خطوط --}}
        @if ($section === 'lines' && $canManage)
            @if ($locked)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-pool-100 bg-pool-50 px-4 py-3">
                    <p class="text-xs leading-6 text-pool-800">{{ __('budget.locked_notice', ['number' => fa_num($latestVersion?->number)]) }}</p>
                </div>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-ink-500">{{ __('budget.line_count', ['count' => fa_num($lines->count())]) }}</p>
                @if ($planEditable)
                    <button type="button" wire:click="openLineForm" class="btn-primary">{{ __('budget.add_line') }}</button>
                @endif
            </div>

            @if ($showLineForm && $planEditable)
                <form wire:submit="saveLine" class="space-y-4 rounded-2xl border border-pool-200 bg-white p-4 sm:p-5">
                    <h3 class="text-sm font-bold text-ink-900">{{ $editingLineId ? __('budget.edit_line') : __('budget.add_line') }}</h3>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="lineType" class="{{ $label }}">{{ __('budget.fields.type') }}</label>
                            <select id="lineType" wire:model.live="lineType" class="{{ $input }}">
                                @foreach (BudgetLineType::cases() as $t)
                                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                @endforeach
                            </select>
                            @error('lineType') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="lineCategory" class="{{ $label }}">{{ __('budget.fields.category') }}</label>
                            <select id="lineCategory" wire:model="lineCategory" class="{{ $input }}">
                                @foreach ($categories as $c)
                                    <option value="{{ $c->value }}">{{ $c->label() }}</option>
                                @endforeach
                            </select>
                            @error('lineCategory') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="lineName" class="{{ $label }}">{{ __('budget.fields.name') }}</label>
                            <input id="lineName" type="text" wire:model="lineName" maxlength="160" class="{{ $input }}">
                            @error('lineName') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="lineAmount" class="{{ $label }}">{{ __('budget.fields.planned_amount') }} ({{ Money::unitLabel($unit) }})</label>
                            <input id="lineAmount" type="text" inputmode="numeric" wire:model="lineAmount" dir="ltr" placeholder="0" class="{{ $input }}">
                            @error('lineAmount') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>

                        @if ($full)
                            <div>
                                <label for="lineBehavior" class="{{ $label }}">{{ __('budget.fields.cost_behavior') }}</label>
                                <select id="lineBehavior" wire:model="lineBehavior" class="{{ $input }}">
                                    @foreach ($behaviors as $b)
                                        <option value="{{ $b->value }}">{{ $b->label() }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-[11px] leading-5 text-ink-400">{{ __('budget.behavior_hint') }}</p>
                                @error('lineBehavior') <p class="{{ $err }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="lineCommitteeId" class="{{ $label }}">{{ __('budget.fields.committee') }}</label>
                                <select id="lineCommitteeId" wire:model="lineCommitteeId" class="{{ $input }}">
                                    <option value="">{{ __('budget.no_committee') }}</option>
                                    @foreach ($committees as $committee)
                                        <option value="{{ $committee->id }}">{{ $committee->name }}</option>
                                    @endforeach
                                </select>
                                @error('lineCommitteeId') <p class="{{ $err }}">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="lineAssumption" class="{{ $label }}">{{ __('budget.fields.assumption') }}</label>
                                <textarea id="lineAssumption" rows="2" wire:model="lineAssumption" maxlength="1000" class="{{ $input }}"></textarea>
                                @error('lineAssumption') <p class="{{ $err }}">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="saveLine">{{ __('ui.save') }}</button>
                        <button type="button" wire:click="cancelLineForm" class="btn-ghost">{{ __('ui.cancel') }}</button>
                    </div>
                </form>
            @endif

            @if ($lines->isEmpty())
                <div class="rounded-2xl border border-dashed border-ink-200 bg-white px-6 py-12 text-center">
                    <h3 class="text-base font-bold text-ink-900">{{ __('budget.empty_title') }}</h3>
                    <p class="mx-auto mt-2 max-w-sm text-sm leading-7 text-ink-500">{{ __('budget.empty_desc') }}</p>
                </div>
            @else
                @foreach ([BudgetLineType::Expense, BudgetLineType::Revenue] as $type)
                    @php($group = $lines->filter(fn ($l) => $l->type === $type))
                    @if ($group->isNotEmpty())
                        <div class="overflow-hidden rounded-2xl border border-ink-100 bg-white" wire:key="group-{{ $type->value }}">
                            <h3 class="border-b border-ink-100 bg-ink-50 px-4 py-2.5 text-xs font-bold text-ink-700">
                                {{ $type === BudgetLineType::Expense ? __('budget.expense_lines') : __('budget.revenue_lines') }}
                            </h3>
                            <ul class="divide-y divide-ink-100">
                                @foreach ($group as $line)
                                    @php($actual = (int) $line->actual_amount)
                                    @php($over = collect($overrunLines)->contains(fn ($a) => $a['line']->id === $line->id))
                                    <li class="space-y-2 px-4 py-3" wire:key="line-{{ $line->id }}">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-ink-900">{{ $line->name }}</p>
                                                <p class="mt-0.5 text-[11px] text-ink-400">
                                                    {{ $line->category->label() }}
                                                    @if ($full)
                                                        · {{ $line->cost_behavior === \App\Enums\CostBehavior::Fixed ? __('budget.fixed') : __('budget.variable') }}
                                                        @if ($line->committee) · {{ $line->committee->name }} @endif
                                                    @endif
                                                </p>
                                                @if ($full && $line->assumption)
                                                    <p class="mt-1 text-[11px] leading-5 text-ink-500">{{ $line->assumption }}</p>
                                                @endif
                                            </div>
                                            <div class="text-end text-xs tabular-nums">
                                                <p class="font-bold text-ink-900"><bdi>{{ $m($line->planned_amount) }}</bdi></p>
                                                <p @class(['mt-0.5', 'font-semibold text-signal-600' => $over, 'text-ink-500' => ! $over])>
                                                    {{ __('budget.actual') }}: <bdi>{{ $m($actual) }}</bdi>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            @if ($over)
                                                <span class="rounded-full bg-signal-50 px-2.5 py-1 text-[11px] font-semibold text-signal-600">{{ __('budget.overrun_badge') }}</span>
                                            @endif
                                            @if ($canEdit)
                                                <button type="button" wire:click="openTxForm({{ $line->id }})" class="rounded-lg border border-ink-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-ink-700 transition-colors hover:border-pool-300 hover:bg-pool-50">{{ __('budget.add_transaction') }}</button>
                                            @endif
                                            @if ($planEditable)
                                                <button type="button" wire:click="editLine({{ $line->id }})" class="rounded-lg border border-ink-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-ink-700 transition-colors hover:bg-ink-50">{{ __('ui.edit') }}</button>
                                                @if ($line->transactions_count === 0)
                                                    <button type="button" wire:click="deleteLine({{ $line->id }})" wire:confirm="{{ __('budget.confirm_delete_line') }}" class="rounded-lg border border-signal-500/30 bg-signal-50 px-2.5 py-1.5 text-xs font-semibold text-signal-600 transition-colors hover:bg-signal-50/60">{{ __('ui.delete') }}</button>
                                                @endif
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
            @endif
        @endif

        {{-- ================================================== تراکنش‌ها --}}
        @if ($section === 'transactions' && $canManage)
            <div class="flex flex-wrap items-center justify-between gap-3">
                <select wire:model.live="txFilterLine" class="{{ $input }} max-w-xs" aria-label="{{ __('budget.filter_line') }}">
                    <option value="">{{ __('budget.all_lines') }}</option>
                    @foreach ($lines as $line)
                        <option value="{{ $line->id }}" wire:key="flt-{{ $line->id }}">{{ $line->name }}</option>
                    @endforeach
                </select>
                @if ($canEdit && $lines->isNotEmpty())
                    <button type="button" wire:click="openTxForm" class="btn-primary">{{ __('budget.add_transaction') }}</button>
                @endif
            </div>

            @if ($showTxForm && $canEdit)
                <form wire:submit="saveTx" class="space-y-4 rounded-2xl border border-pool-200 bg-white p-4 sm:p-5">
                    <h3 class="text-sm font-bold text-ink-900">{{ $editingTxId ? __('budget.edit_transaction') : __('budget.add_transaction') }}</h3>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="txLineId" class="{{ $label }}">{{ __('budget.fields.line') }}</label>
                            <select id="txLineId" wire:model="txLineId" @disabled($editingTxId !== null) class="{{ $input }}">
                                <option value="">{{ __('budget.pick_line') }}</option>
                                @foreach ($lines as $line)
                                    <option value="{{ $line->id }}" wire:key="txl-{{ $line->id }}">{{ $line->type->label() }} — {{ $line->name }}</option>
                                @endforeach
                            </select>
                            @error('txLineId') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="txAmount" class="{{ $label }}">{{ __('budget.fields.amount') }} ({{ Money::unitLabel($unit) }})</label>
                            <input id="txAmount" type="text" inputmode="numeric" wire:model="txAmount" dir="ltr" placeholder="0" class="{{ $input }}">
                            @error('txAmount') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="txDate" class="{{ $label }}">{{ __('budget.fields.date') }}</label>
                            <input id="txDate" type="text" wire:model="txDate" dir="ltr" placeholder="۱۴۰۵/۰۸/۲۹" class="{{ $input }}">
                            @error('txDate') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="txDescription" class="{{ $label }}">{{ __('budget.fields.description') }}</label>
                            <input id="txDescription" type="text" wire:model="txDescription" maxlength="255" class="{{ $input }}">
                            @error('txDescription') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>

                        @if ($full)
                            <div class="sm:col-span-2">
                                <label for="receipt" class="{{ $label }}">{{ __('budget.fields.receipt') }}</label>
                                <input id="receipt" type="file" wire:model="receipt" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-xs text-ink-600 file:me-3 file:rounded-lg file:border-0 file:bg-pool-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-pool-700">
                                <p class="mt-1 text-[11px] text-ink-400">{{ __('budget.receipt_hint', ['max' => fa_num($maxKb / 1024)]) }}</p>
                                <p wire:loading wire:target="receipt" class="mt-1 text-xs text-pool-600">{{ __('ui.loading') }}</p>
                                @error('receipt') <p class="{{ $err }}">{{ $message }}</p> @enderror
                                @if ($editingTxId)
                                    <label class="mt-2 inline-flex items-center gap-2 text-xs text-ink-600">
                                        <input type="checkbox" wire:model="removeReceipt" class="rounded border-ink-300">
                                        {{ __('budget.remove_receipt') }}
                                    </label>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="saveTx,receipt">{{ __('ui.save') }}</button>
                        <button type="button" wire:click="cancelTxForm" class="btn-ghost">{{ __('ui.cancel') }}</button>
                    </div>
                </form>
            @endif

            @if ($transactions->isEmpty())
                <div class="rounded-2xl border border-dashed border-ink-200 bg-white px-6 py-12 text-center">
                    <h3 class="text-base font-bold text-ink-900">{{ __('budget.tx_empty_title') }}</h3>
                    <p class="mx-auto mt-2 max-w-sm text-sm leading-7 text-ink-500">{{ __('budget.tx_empty_desc') }}</p>
                </div>
            @else
                <ul class="divide-y divide-ink-100 overflow-hidden rounded-2xl border border-ink-100 bg-white">
                    @foreach ($transactions as $tx)
                        <li class="flex flex-wrap items-start justify-between gap-3 px-4 py-3" wire:key="tx-{{ $tx->id }}">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-ink-900">{{ $tx->line?->name }}</p>
                                <p class="mt-0.5 text-[11px] text-ink-400">
                                    {{ fa_num(jalali($tx->date)) }}
                                    @if ($tx->line) · {{ $tx->line->type->label() }} @endif
                                    @if ($tx->creator) · {{ $tx->creator->name ?? phone($tx->creator->phone) }} @endif
                                </p>
                                @if ($tx->description)
                                    <p class="mt-1 text-xs leading-5 text-ink-600">{{ $tx->description }}</p>
                                @endif
                            </div>
                            <div class="text-end">
                                <p class="text-sm font-bold tabular-nums text-ink-900"><bdi>{{ $m($tx->amount) }}</bdi></p>
                                <div class="mt-2 flex flex-wrap justify-end gap-2">
                                    @if ($tx->receipt_path)
                                        <button type="button" wire:click="downloadReceipt({{ $tx->id }})" class="rounded-lg border border-ink-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-pool-700 transition-colors hover:bg-pool-50">{{ __('budget.receipt') }}</button>
                                    @endif
                                    @if ($canEdit)
                                        <button type="button" wire:click="editTx({{ $tx->id }})" class="rounded-lg border border-ink-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-ink-700 transition-colors hover:bg-ink-50">{{ __('ui.edit') }}</button>
                                        <button type="button" wire:click="deleteTx({{ $tx->id }})" wire:confirm="{{ __('budget.confirm_delete_tx') }}" class="rounded-lg border border-signal-500/30 bg-signal-50 px-2.5 py-1.5 text-xs font-semibold text-signal-600">{{ __('ui.delete') }}</button>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif

        {{-- ===================================================== نسخه‌ها --}}
        @if ($section === 'versions' && $canManage && $full)
            <div class="rounded-2xl border border-ink-100 bg-white p-4 sm:p-5">
                @if ($locked)
                    <h3 class="text-sm font-bold text-ink-900">{{ __('budget.state_locked', ['number' => fa_num($latestVersion?->number)]) }}</h3>
                    <p class="mt-1 text-xs leading-6 text-ink-500">{{ __('budget.state_locked_desc') }}</p>
                    @if (! $closed && auth()->user()->can('approve', [\App\Models\BudgetLine::class, $event]))
                        <button type="button" wire:click="startRevision" wire:confirm="{{ __('budget.confirm_revision') }}" class="btn-ghost mt-3">{{ __('budget.start_revision') }}</button>
                    @endif
                @else
                    <h3 class="text-sm font-bold text-ink-900">{{ __('budget.state_draft', ['number' => fa_num($workingNumber)]) }}</h3>
                    <p class="mt-1 text-xs leading-6 text-ink-500">{{ __('budget.state_draft_desc') }}</p>

                    @if ($pending)
                        <p class="mt-3 text-xs leading-6 text-ink-700">
                            {{ __('budget.pending_changes', ['number' => fa_num($pending['base_number'])]) }}
                            {{ __('budget.expense') }}: <bdi class="tabular-nums">{{ ($pending['expense_delta'] > 0 ? '+' : '').$m($pending['expense_delta']) }}</bdi>،
                            {{ __('budget.revenue') }}: <bdi class="tabular-nums">{{ ($pending['revenue_delta'] > 0 ? '+' : '').$m($pending['revenue_delta']) }}</bdi>
                        </p>
                    @endif

                    @if (! $closed && auth()->user()->can('approve', [\App\Models\BudgetLine::class, $event]))
                        <div class="mt-3 space-y-2">
                            <label for="approveNote" class="{{ $label }}">{{ __('budget.approve_note') }}</label>
                            <textarea id="approveNote" rows="2" wire:model="approveNote" maxlength="500" class="{{ $input }}"></textarea>
                            <button type="button" wire:click="approve" wire:confirm="{{ __('budget.confirm_approve') }}" class="btn-flag" @disabled($lines->isEmpty())>{{ __('budget.approve') }}</button>
                        </div>
                    @endif
                @endif
            </div>

            @if ($versions->isEmpty())
                <p class="rounded-xl bg-ink-50 px-4 py-3 text-xs text-ink-600">{{ __('budget.no_versions') }}</p>
            @else
                <ul class="divide-y divide-ink-100 overflow-hidden rounded-2xl border border-ink-100 bg-white">
                    @foreach ($versions as $version)
                        <li class="space-y-1 px-4 py-3" wire:key="ver-{{ $version->id }}">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-bold text-ink-900">{{ __('budget.version_title', ['number' => fa_num($version->number)]) }}</p>
                                <span @class([
                                    'rounded-full px-2.5 py-1 text-[11px] font-semibold',
                                    'bg-pool-50 text-pool-700' => $version->status === \App\Enums\BudgetVersionStatus::Approved,
                                    'bg-flag-300/40 text-ink-800' => $version->status === \App\Enums\BudgetVersionStatus::Draft,
                                    'bg-ink-100 text-ink-500' => $version->status === \App\Enums\BudgetVersionStatus::Superseded,
                                ])>{{ $version->status->label() }}</span>
                            </div>
                            @if ($version->approved_at)
                                <p class="text-[11px] text-ink-400">
                                    {{ fa_num(jalali($version->approved_at)) }}
                                    @if ($version->approver) · {{ $version->approver->name ?? phone($version->approver->phone) }} @endif
                                </p>
                                <p class="text-xs tabular-nums text-ink-700">
                                    {{ __('budget.revenue') }}: <bdi>{{ $m((int) $version->total_revenue) }}</bdi> ·
                                    {{ __('budget.expense') }}: <bdi>{{ $m((int) $version->total_expense) }}</bdi> ·
                                    {{ __('budget.balance') }}: <bdi>{{ $m((int) $version->balance()) }}</bdi>
                                </p>
                                @if ($version->note)
                                    <p class="text-xs leading-5 text-ink-500">{{ $version->note }}</p>
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    @endif
</div>
