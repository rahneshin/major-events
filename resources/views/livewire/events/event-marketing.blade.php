@php
    $input = 'block w-full rounded-xl border border-ink-200 bg-white px-3 py-2.5 text-sm text-ink-900 placeholder:text-ink-300 focus:border-pool-400 focus:outline-none focus:ring-2 focus:ring-pool-100';
    $label = 'mb-1 block text-xs font-semibold text-ink-600';
    $err = 'mt-1 text-xs text-signal-600';
    $btn = 'rounded-xl bg-pool-600 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-pool-700';
    $btnGhost = 'rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-xs font-semibold text-ink-700 transition hover:bg-ink-50';
    $mini = 'rounded-lg border border-ink-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-ink-700 hover:bg-ink-50';
    $sections = ['calendar' => __('marketing.section_calendar'), 'plan' => __('marketing.section_plan')];
@endphp

<div class="space-y-5">
    @if (! $feature)
        <div class="rounded-2xl border border-pool-100 bg-pool-50 px-5 py-6 text-center">
            <p class="text-sm font-bold text-pool-800">{{ __('marketing.org_only_title') }}</p>
            <p class="mt-1 text-xs leading-6 text-pool-700">{{ __('marketing.org_only_body') }}</p>
            <a href="{{ route('account.index') }}" wire:navigate class="mt-3 inline-block text-xs font-bold text-pool-800 underline">{{ __('marketing.upgrade') }}</a>
        </div>
    @else
        @if ($error)
            <p class="rounded-xl bg-signal-50 px-4 py-2.5 text-xs font-semibold text-signal-600" role="alert">{{ $error }}</p>
        @endif
        @if ($notice)
            <p class="rounded-xl bg-pool-50 px-4 py-2.5 text-xs font-semibold text-pool-700" role="status">{{ $notice }}</p>
        @endif
        @if ($closed)
            <p class="rounded-xl bg-flag-300/40 px-4 py-2.5 text-xs font-semibold text-ink-800">{{ __('marketing.event_closed') }}</p>
        @endif

        {{-- خلاصه و KPI --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-2xl border border-ink-100 bg-white p-4">
                <p class="text-xs font-semibold text-ink-500">{{ __('marketing.summary_items') }}</p>
                <p class="mt-2 text-lg font-bold tabular-nums text-ink-900">{{ fa_num($summary['total']) }}</p>
                <p class="mt-1 text-[11px] text-ink-500">
                    @foreach ($phases as $ph) {{ $ph->label() }} {{ fa_num($summary['by_phase'][$ph->value]) }}@if (! $loop->last) · @endif @endforeach
                </p>
                @if ($summary['late'] > 0)
                    <p class="mt-1 text-[11px] font-bold text-signal-600">{{ __('marketing.late_count', ['count' => fa_num($summary['late'])]) }}</p>
                @endif
            </div>
            @foreach ($metrics as $m)
                @php $k = $summary['kpi'][$m->value]; @endphp
                <div class="rounded-2xl border border-ink-100 bg-white p-4" wire:key="kpi-{{ $m->value }}">
                    <p class="text-xs font-semibold text-ink-500">{{ $m->label() }}</p>
                    <p class="mt-2 text-lg font-bold tabular-nums text-ink-900">{{ $k['percent'] === null ? '—' : fa_num($k['percent']).'٪' }}</p>
                    <p class="mt-1 text-[11px] tabular-nums text-ink-500">{{ fa_num(number_format($k['actual'])) }} / {{ fa_num(number_format($k['target'])) }}</p>
                </div>
            @endforeach
        </div>

        <div class="flex gap-1 overflow-x-auto rounded-xl border border-ink-200 bg-white p-1 text-xs font-semibold" role="tablist">
            @foreach ($sections as $key => $text)
                <button type="button" role="tab" aria-selected="{{ $section === $key ? 'true' : 'false' }}"
                        wire:click="setSection('{{ $key }}')" wire:key="msec-{{ $key }}"
                        @class([
                            'shrink-0 rounded-lg px-3.5 py-2 transition-colors',
                            'bg-pool-600 text-white' => $section === $key,
                            'text-ink-600 hover:bg-ink-50' => $section !== $key,
                        ])>{{ $text }}</button>
            @endforeach
        </div>

        {{-- ============================ برنامه ============================ --}}
        @if ($section === 'plan')
            @if ($canEdit)
                <form wire:submit="savePlan" class="space-y-3 rounded-2xl border border-ink-100 bg-white p-4">
                    <div>
                        <label class="{{ $label }}" for="m-seg">{{ __('marketing.fields.target_segments') }}</label>
                        <textarea id="m-seg" rows="4" wire:model="mSegments" placeholder="{{ __('marketing.segments_hint') }}" class="{{ $input }}"></textarea>
                        @error('mSegments') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="m-pos">{{ __('marketing.fields.positioning') }}</label>
                        <textarea id="m-pos" rows="3" wire:model="mPositioning" class="{{ $input }}"></textarea>
                        @error('mPositioning') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="m-msg">{{ __('marketing.fields.message') }}</label>
                        <textarea id="m-msg" rows="3" wire:model="mMessage" class="{{ $input }}"></textarea>
                        @error('mMessage') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <fieldset>
                        <legend class="{{ $label }}">{{ __('marketing.fields.channels') }}</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($channels as $ch)
                                <label class="flex cursor-pointer items-center gap-1.5 rounded-full border border-ink-200 bg-white px-3 py-1.5 text-xs text-ink-700" wire:key="mch-{{ $ch->value }}">
                                    <input type="checkbox" wire:model="mChannels" value="{{ $ch->value }}" class="rounded border-ink-300">
                                    {{ $ch->label() }}
                                </label>
                            @endforeach
                        </div>
                        @error('mChannels.*') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </fieldset>
                    <button type="submit" class="{{ $btn }}">{{ __('marketing.save_plan') }}</button>
                </form>
            @else
                <div class="space-y-3 rounded-2xl border border-ink-100 bg-white p-4 text-sm text-ink-700">
                    @if ($plan === null)
                        <p class="text-ink-400">{{ __('marketing.plan_empty') }}</p>
                    @else
                        <div>
                            <p class="{{ $label }}">{{ __('marketing.fields.target_segments') }}</p>
                            <ul class="flex flex-wrap gap-1.5">
                                @foreach ($plan->target_segments ?? [] as $seg)
                                    <li class="rounded-full bg-pool-50 px-2.5 py-0.5 text-[11px] font-semibold text-pool-800">{{ $seg }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <div><p class="{{ $label }}">{{ __('marketing.fields.positioning') }}</p><p class="whitespace-pre-line leading-7">{{ $plan->positioning ?: '—' }}</p></div>
                        <div><p class="{{ $label }}">{{ __('marketing.fields.message') }}</p><p class="whitespace-pre-line leading-7">{{ $plan->message ?: '—' }}</p></div>
                        <div>
                            <p class="{{ $label }}">{{ __('marketing.fields.channels') }}</p>
                            <ul class="flex flex-wrap gap-1.5">
                                @foreach ($plan->channels ?? [] as $chKey)
                                    <li class="rounded-full bg-ink-100 px-2.5 py-0.5 text-[11px] font-semibold text-ink-700">{{ \App\Enums\CampaignChannel::tryFrom($chKey)?->label() ?? $chKey }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif
        @endif

        {{-- ========================== تقویم محتوا ========================== --}}
        @if ($section === 'calendar')
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap gap-2">
                    <select wire:model.live="filterPhase" class="{{ $input }} !w-auto" aria-label="{{ __('marketing.fields.phase') }}">
                        <option value="">{{ __('marketing.all_phases') }}</option>
                        @foreach ($phases as $ph)
                            <option value="{{ $ph->value }}">{{ $ph->label() }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="filterStatus" class="{{ $input }} !w-auto" aria-label="{{ __('marketing.fields.status') }}">
                        <option value="all">{{ __('marketing.filter_all') }}</option>
                        <option value="open">{{ __('marketing.filter_open') }}</option>
                        @foreach ($statuses as $st)
                            <option value="{{ $st->value }}">{{ $st->label() }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($canEdit)
                    <button type="button" wire:click="openItemForm" class="{{ $btn }}">{{ __('marketing.add_item') }}</button>
                @endif
            </div>

            @if ($showItemForm && $canEdit)
                <form wire:submit="saveItem" class="space-y-3 rounded-2xl border border-pool-100 bg-white p-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="{{ $label }}" for="i-title">{{ __('marketing.fields.title') }}</label>
                            <input id="i-title" type="text" wire:model="iTitle" maxlength="160" class="{{ $input }}">
                            @error('iTitle') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="i-phase">{{ __('marketing.fields.phase') }}</label>
                            <select id="i-phase" wire:model="iPhase" class="{{ $input }}">
                                @foreach ($phases as $ph)
                                    <option value="{{ $ph->value }}">{{ $ph->label() }}</option>
                                @endforeach
                            </select>
                            @error('iPhase') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="i-channel">{{ __('marketing.fields.channel') }}</label>
                            <select id="i-channel" wire:model="iChannel" class="{{ $input }}">
                                @foreach ($channels as $ch)
                                    <option value="{{ $ch->value }}">{{ $ch->label() }}</option>
                                @endforeach
                            </select>
                            @error('iChannel') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="i-date">{{ __('marketing.fields.publish_date') }}</label>
                            <x-jalali-picker id="i-date" wire:model="iDate" />
                            @error('iDate') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="i-time">{{ __('marketing.fields.publish_time') }}</label>
                            <input id="i-time" type="text" inputmode="numeric" dir="ltr" wire:model="iTime" placeholder="14:30" maxlength="5" class="{{ $input }}">
                            @error('iTime') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="i-owner">{{ __('marketing.fields.owner') }}</label>
                            <select id="i-owner" wire:model="iOwnerId" class="{{ $input }}">
                                <option value="">{{ __('marketing.no_owner') }}</option>
                                @foreach ($owners as $o)
                                    <option value="{{ $o->id }}">{{ $o->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="{{ $label }}" for="i-metric">{{ __('marketing.fields.kpi_metric') }}</label>
                                <select id="i-metric" wire:model="iMetric" class="{{ $input }}">
                                    <option value="">{{ __('marketing.no_kpi') }}</option>
                                    @foreach ($metrics as $m)
                                        <option value="{{ $m->value }}">{{ $m->label() }}</option>
                                    @endforeach
                                </select>
                                @error('iMetric') <p class="{{ $err }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $label }}" for="i-target">{{ __('marketing.fields.kpi_target') }}</label>
                                <input id="i-target" type="text" inputmode="numeric" dir="ltr" wire:model="iTarget" class="{{ $input }}">
                                @error('iTarget') <p class="{{ $err }}">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $label }}" for="i-notes">{{ __('marketing.fields.notes') }}</label>
                            <textarea id="i-notes" rows="2" wire:model="iNotes" class="{{ $input }}"></textarea>
                            @error('iNotes') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="{{ $btn }}">{{ $editingItemId ? __('marketing.save_changes') : __('marketing.create') }}</button>
                        <button type="button" wire:click="cancelItemForm" class="{{ $btnGhost }}">{{ __('marketing.cancel') }}</button>
                    </div>
                </form>
            @endif

            <div class="space-y-2">
                @forelse ($items as $item)
                    <div class="rounded-2xl border bg-white p-3.5 {{ $item->isLate() ? 'border-signal-200' : 'border-ink-100' }}" wire:key="ci-{{ $item->id }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-bold text-ink-900">{{ $item->title }}</p>
                                <p class="mt-0.5 text-[11px] text-ink-500">
                                    {{ $item->phase->label() }} · {{ $item->channel->label() }}
                                    @if ($item->publish_at) · {{ jalali($item->publish_at) }} {{ fa_num($item->publish_at->format('H:i')) }} @endif
                                    @if ($item->owner) · {{ $item->owner->name }} @endif
                                    @if ($item->isLate()) · <span class="font-bold text-signal-600">{{ __('marketing.late') }}</span> @endif
                                </p>
                                @if ($item->kpi_metric)
                                    <p class="mt-1 text-[11px] tabular-nums text-ink-600">
                                        {{ $item->kpi_metric->label() }}:
                                        {{ fa_num(number_format((int) $item->kpi_actual)) }} / {{ fa_num(number_format((int) $item->kpi_target)) }}
                                        @if ($item->kpiPercent() !== null) ({{ fa_num($item->kpiPercent()) }}٪) @endif
                                    </p>
                                @endif
                                @if ($item->notes)
                                    <p class="mt-1 text-[11px] leading-5 text-ink-500">{{ $item->notes }}</p>
                                @endif
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $item->status->badgeClasses() }}">{{ $item->status->label() }}</span>
                        </div>

                        @if ($canEdit)
                            <div class="mt-3 flex flex-wrap items-center gap-1.5">
                                @foreach ($statuses as $st)
                                    @if ($st !== $item->status)
                                        <button type="button" wire:click="changeItemStatus({{ $item->id }}, '{{ $st->value }}')" class="{{ $mini }}" wire:key="cis-{{ $item->id }}-{{ $st->value }}">← {{ $st->label() }}</button>
                                    @endif
                                @endforeach
                                @if ($item->kpi_metric)
                                    <button type="button" wire:click="startKpi({{ $item->id }})" class="{{ $mini }}">{{ __('marketing.record_kpi') }}</button>
                                @endif
                                <button type="button" wire:click="editItem({{ $item->id }})" class="{{ $mini }}">{{ __('marketing.edit') }}</button>
                                <button type="button" wire:click="deleteItem({{ $item->id }})" wire:confirm="{{ __('marketing.confirm_delete') }}" class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-signal-600 hover:bg-signal-50">{{ __('marketing.delete') }}</button>
                            </div>

                            @if ($recordingKpiId === $item->id)
                                <form wire:submit="saveKpi" class="mt-3 flex flex-wrap items-center gap-2">
                                    <input type="text" inputmode="numeric" dir="ltr" wire:model="kpiActual" placeholder="{{ __('marketing.fields.kpi_actual') }}" class="{{ $input }} !w-40" aria-label="{{ __('marketing.fields.kpi_actual') }}">
                                    <button type="submit" class="{{ $btn }}">{{ __('marketing.save_kpi') }}</button>
                                    <button type="button" wire:click="cancelKpi" class="{{ $btnGhost }}">{{ __('marketing.cancel') }}</button>
                                </form>
                            @endif
                        @endif
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-ink-200 bg-white px-5 py-10 text-center text-sm text-ink-400">{{ __('marketing.items_empty') }}</div>
                @endforelse
            </div>
        @endif
    @endif
</div>
