@php
    use App\Enums\DeliverableStatus;
    use App\Support\Money;

    $input = 'block w-full rounded-xl border border-ink-200 bg-white px-3 py-2.5 text-sm text-ink-900 placeholder:text-ink-300 focus:border-pool-400 focus:outline-none focus:ring-2 focus:ring-pool-100';
    $label = 'mb-1 block text-xs font-semibold text-ink-600';
    $err = 'mt-1 text-xs text-signal-600';
    $btn = 'rounded-xl bg-pool-600 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-pool-700';
    $btnGhost = 'rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-xs font-semibold text-ink-700 transition hover:bg-ink-50';
    $mini = 'rounded-lg border border-ink-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-ink-700 hover:bg-ink-50';
    $sections = [
        'overview' => __('sponsorship.section_overview'),
        'sponsors' => __('sponsorship.section_sponsors'),
        'assets' => __('sponsorship.section_assets'),
        'deliverables' => __('sponsorship.section_deliverables'),
    ];
@endphp

<div class="space-y-5">
    @if (! $feature)
        <div class="rounded-2xl border border-pool-100 bg-pool-50 px-5 py-6 text-center">
            <p class="text-sm font-bold text-pool-800">{{ __('sponsorship.org_only_title') }}</p>
            <p class="mt-1 text-xs leading-6 text-pool-700">{{ __('sponsorship.org_only_body') }}</p>
            <a href="{{ route('account.index') }}" wire:navigate class="mt-3 inline-block text-xs font-bold text-pool-800 underline">{{ __('sponsorship.upgrade') }}</a>
        </div>
    @elseif (! $canView)
        <div class="rounded-2xl border border-ink-100 bg-white px-5 py-8 text-center text-sm text-ink-500">{{ __('sponsorship.no_access') }}</div>
    @else
        @if ($error)
            <p class="rounded-xl bg-signal-50 px-4 py-2.5 text-xs font-semibold text-signal-600" role="alert">{{ $error }}</p>
        @endif
        @if ($notice)
            <p class="rounded-xl bg-pool-50 px-4 py-2.5 text-xs font-semibold text-pool-700" role="status">{{ $notice }}</p>
        @endif
        @if ($closed)
            <p class="rounded-xl bg-flag-300/40 px-4 py-2.5 text-xs font-semibold text-ink-800">{{ __('sponsorship.event_closed') }}</p>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex gap-1 overflow-x-auto rounded-xl border border-ink-200 bg-white p-1 text-xs font-semibold" role="tablist">
                @foreach ($sections as $key => $text)
                    <button type="button" role="tab" aria-selected="{{ $section === $key ? 'true' : 'false' }}"
                            wire:click="setSection('{{ $key }}')" wire:key="ssec-{{ $key }}"
                            @class([
                                'shrink-0 rounded-lg px-3.5 py-2 transition-colors',
                                'bg-pool-600 text-white' => $section === $key,
                                'text-ink-600 hover:bg-ink-50' => $section !== $key,
                            ])>{{ $text }}</button>
                @endforeach
            </div>
            @if ($canManage)
                <div class="flex gap-1 rounded-xl border border-ink-200 bg-white p-1 text-xs font-semibold" role="group" aria-label="{{ __('sponsorship.unit') }}">
                    @foreach ([Money::RIAL, Money::TOMAN] as $u)
                        <button type="button" wire:click="setUnit('{{ $u }}')" wire:key="sunit-{{ $u }}"
                                @class(['rounded-lg px-3 py-1.5', 'bg-ink-800 text-white' => $unit === $u, 'text-ink-600 hover:bg-ink-50' => $unit !== $u])>{{ Money::unitLabel($u) }}</button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ============================ نمای کلی ============================ --}}
        @if ($section === 'overview')
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-2xl border border-ink-100 bg-white p-4">
                    <p class="text-xs font-semibold text-ink-500">{{ __('sponsorship.summary_sponsors') }}</p>
                    <p class="mt-2 text-lg font-bold tabular-nums text-ink-900">{{ fa_num($summary['sponsor_total']) }}</p>
                    <div class="mt-2 flex flex-wrap gap-1">
                        @foreach ($statuses as $st)
                            @if ($summary['counts'][$st->value] > 0)
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $st->badgeClasses() }}">{{ $st->label() }} {{ fa_num($summary['counts'][$st->value]) }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>
                @if ($canManage)
                    <div class="rounded-2xl border border-ink-100 bg-white p-4">
                        <p class="text-xs font-semibold text-ink-500">{{ __('sponsorship.summary_committed') }}</p>
                        <p class="mt-2 text-lg font-bold tabular-nums text-ink-900">{{ fa_num(Money::format($summary['committed_total'], $unit)) }}</p>
                        <p class="mt-1 text-[11px] text-ink-500">
                            {{ __('sponsorship.cash') }}: {{ fa_num(Money::format($summary['committed_cash'], $unit, false)) }}
                            · {{ __('sponsorship.in_kind') }}: {{ fa_num(Money::format($summary['committed_in_kind'], $unit, false)) }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-ink-100 bg-white p-4">
                        <p class="text-xs font-semibold text-ink-500">{{ __('sponsorship.summary_pipeline') }}</p>
                        <p class="mt-2 text-lg font-bold tabular-nums text-ink-900">{{ fa_num(Money::format($summary['pipeline_total'], $unit)) }}</p>
                    </div>
                @endif
                <div class="rounded-2xl border border-ink-100 bg-white p-4">
                    <p class="text-xs font-semibold text-ink-500">{{ __('sponsorship.summary_delivery') }}</p>
                    <p class="mt-2 text-lg font-bold tabular-nums text-ink-900">{{ $summary['delivery_percent'] === null ? '—' : fa_num($summary['delivery_percent']).'٪' }}</p>
                    <p class="mt-1 text-[11px] text-ink-500">
                        {{ __('sponsorship.deliverables_open', ['count' => fa_num($summary['deliverables']['open'])]) }}
                        · {{ __('sponsorship.deliverables_done', ['count' => fa_num($summary['deliverables']['delivered'])]) }}
                    </p>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-signal-100 bg-signal-50/50 p-4">
                    <p class="text-xs font-bold text-signal-700">{{ __('sponsorship.alert_overdue') }} ({{ fa_num($alerts['overdue']->count()) }})</p>
                    <ul class="mt-2 space-y-1.5 text-xs text-ink-700">
                        @forelse ($alerts['overdue'] as $d)
                            <li wire:key="ao-{{ $d->id }}">{{ $d->sponsor?->name }} — {{ $d->commitment }}
                                <span class="text-signal-600">({{ jalali($d->due_at) }})</span></li>
                        @empty
                            <li class="text-ink-400">{{ __('sponsorship.alert_none') }}</li>
                        @endforelse
                    </ul>
                </div>
                <div class="rounded-2xl border border-flag-300 bg-flag-300/20 p-4">
                    <p class="text-xs font-bold text-ink-800">{{ __('sponsorship.alert_due_soon', ['days' => fa_num(config('sponsorship.due_soon_days'))]) }} ({{ fa_num($alerts['due_soon']->count()) }})</p>
                    <ul class="mt-2 space-y-1.5 text-xs text-ink-700">
                        @forelse ($alerts['due_soon'] as $d)
                            <li wire:key="ad-{{ $d->id }}">{{ $d->sponsor?->name }} — {{ $d->commitment }}
                                <span class="text-ink-500">({{ jalali($d->due_at) }})</span></li>
                        @empty
                            <li class="text-ink-400">{{ __('sponsorship.alert_none') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        @endif

        {{-- ============================= اسپانسرها ============================= --}}
        @if ($section === 'sponsors')
            <div class="flex flex-wrap items-center justify-between gap-2">
                <select wire:model.live="filterSponsorStatus" class="{{ $input }} !w-auto" aria-label="{{ __('sponsorship.fields.status') }}">
                    <option value="">{{ __('sponsorship.all_statuses') }}</option>
                    @foreach ($statuses as $st)
                        <option value="{{ $st->value }}">{{ $st->label() }}</option>
                    @endforeach
                </select>
                @if ($canEdit)
                    <button type="button" wire:click="openSponsorForm" class="{{ $btn }}">{{ __('sponsorship.add_sponsor') }}</button>
                @endif
            </div>

            @if ($showSponsorForm && $canEdit)
                <form wire:submit="saveSponsor" class="space-y-3 rounded-2xl border border-pool-100 bg-white p-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="{{ $label }}" for="s-name">{{ __('sponsorship.fields.name') }}</label>
                            <input id="s-name" type="text" wire:model="sName" maxlength="160" class="{{ $input }}">
                            @error('sName') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="s-cat">{{ __('sponsorship.fields.category') }}</label>
                            <select id="s-cat" wire:model="sCategory" class="{{ $input }}">
                                @foreach ($categories as $c)
                                    <option value="{{ $c->value }}">{{ $c->label() }}</option>
                                @endforeach
                            </select>
                            @error('sCategory') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="s-cn">{{ __('sponsorship.fields.contact_name') }}</label>
                            <input id="s-cn" type="text" wire:model="sContactName" maxlength="120" class="{{ $input }}">
                            @error('sContactName') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="s-cp">{{ __('sponsorship.fields.contact_phone') }}</label>
                            <input id="s-cp" type="text" inputmode="tel" dir="ltr" wire:model="sContactPhone" maxlength="25" class="{{ $input }}">
                            @error('sContactPhone') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="s-cash">{{ __('sponsorship.fields.cash_value') }} ({{ Money::unitLabel($unit) }})</label>
                            <input id="s-cash" type="text" inputmode="numeric" dir="ltr" wire:model="sCash" class="{{ $input }}">
                            @error('sCash') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="s-ik">{{ __('sponsorship.fields.in_kind_value') }} ({{ Money::unitLabel($unit) }})</label>
                            <input id="s-ik" type="text" inputmode="numeric" dir="ltr" wire:model="sInKind" class="{{ $input }}">
                            @error('sInKind') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $label }}" for="s-pkg">{{ __('sponsorship.fields.package') }}</label>
                            <select id="s-pkg" wire:model="sPackageId" class="{{ $input }}">
                                <option value="">{{ __('sponsorship.no_package') }}</option>
                                @foreach ($packages as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $label }}" for="s-notes">{{ __('sponsorship.fields.notes') }}</label>
                            <textarea id="s-notes" rows="2" wire:model="sNotes" class="{{ $input }}"></textarea>
                            @error('sNotes') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="{{ $btn }}">{{ $editingSponsorId ? __('sponsorship.save_changes') : __('sponsorship.create') }}</button>
                        <button type="button" wire:click="cancelSponsorForm" class="{{ $btnGhost }}">{{ __('sponsorship.cancel') }}</button>
                    </div>
                </form>
            @endif

            <div class="space-y-3">
                @forelse ($sponsors as $sponsor)
                    <div class="rounded-2xl border border-ink-100 bg-white p-4" wire:key="sp-{{ $sponsor->id }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-bold text-ink-900">{{ $sponsor->name }}</p>
                                <p class="mt-0.5 text-[11px] text-ink-500">
                                    {{ $sponsor->category->label() }}
                                    @if ($sponsor->package) · {{ $sponsor->package->name }} @endif
                                    · {{ __('sponsorship.deliverables_progress', ['open' => fa_num($sponsor->open_deliverables_count), 'total' => fa_num($sponsor->total_deliverables_count)]) }}
                                </p>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $sponsor->status->badgeClasses() }}">{{ $sponsor->status->label() }}</span>
                        </div>

                        @if ($canManage)
                            <p class="mt-2 text-xs tabular-nums text-ink-700">
                                {{ __('sponsorship.cash') }}: {{ fa_num(Money::format((int) $sponsor->cash_value, $unit)) }}
                                · {{ __('sponsorship.in_kind') }}: {{ fa_num(Money::format((int) $sponsor->in_kind_value, $unit)) }}
                            </p>
                            @if ($sponsor->contact_name || $sponsor->contact_phone)
                                <p class="mt-1 text-[11px] text-ink-500">{{ $sponsor->contact_name }} <span dir="ltr">{{ $sponsor->contact_phone ? fa_num($sponsor->contact_phone) : '' }}</span></p>
                            @endif
                            @if ($sponsor->notes)
                                <p class="mt-1 text-[11px] leading-5 text-ink-500">{{ $sponsor->notes }}</p>
                            @endif
                        @endif

                        @if ($canEdit)
                            <div class="mt-3 flex flex-wrap items-center gap-1.5">
                                @foreach ($sponsor->status->allowedTransitions() as $to)
                                    <button type="button" wire:click="changeSponsorStatus({{ $sponsor->id }}, '{{ $to->value }}')" class="{{ $mini }}" wire:key="sps-{{ $sponsor->id }}-{{ $to->value }}">← {{ $to->label() }}</button>
                                @endforeach
                                <button type="button" wire:click="openDeliverableForm('{{ $sponsor->id }}')" class="{{ $mini }}">{{ __('sponsorship.add_deliverable') }}</button>
                                @if ($sponsor->package_id)
                                    <button type="button" wire:click="generateFromPackage({{ $sponsor->id }})" class="{{ $mini }}">{{ __('sponsorship.generate_from_package') }}</button>
                                @endif
                                <button type="button" wire:click="editSponsor({{ $sponsor->id }})" class="{{ $mini }}">{{ __('sponsorship.edit') }}</button>
                                <button type="button" wire:click="deleteSponsor({{ $sponsor->id }})" wire:confirm="{{ __('sponsorship.confirm_delete') }}" class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-signal-600 hover:bg-signal-50">{{ __('sponsorship.delete') }}</button>
                            </div>
                        @endif
                        <x-last-action class="mt-2" :subject="$sponsor" />
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-ink-200 bg-white px-5 py-10 text-center text-sm text-ink-400">{{ __('sponsorship.sponsors_empty') }}</div>
                @endforelse
            </div>
        @endif

        {{-- ====================== دارایی‌ها و بسته‌ها ====================== --}}
        @if ($section === 'assets')
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-bold text-ink-800">{{ __('sponsorship.assets_title') }}</h3>
                @if ($canEdit)
                    <button type="button" wire:click="openAssetForm" class="{{ $btn }}">{{ __('sponsorship.add_asset') }}</button>
                @endif
            </div>

            @if ($showAssetForm && $canEdit)
                <form wire:submit="saveAsset" class="space-y-3 rounded-2xl border border-pool-100 bg-white p-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="{{ $label }}" for="a-name">{{ __('sponsorship.fields.asset_name') }}</label>
                            <input id="a-name" type="text" wire:model="aName" maxlength="160" class="{{ $input }}">
                            @error('aName') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="a-type">{{ __('sponsorship.fields.asset_type') }}</label>
                            <select id="a-type" wire:model="aType" class="{{ $input }}">
                                @foreach ($assetTypes as $t)
                                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                @endforeach
                            </select>
                            @error('aType') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="a-inv">{{ __('sponsorship.fields.inventory') }}</label>
                            <input id="a-inv" type="text" inputmode="numeric" dir="ltr" wire:model="aInventory" class="{{ $input }}">
                            @error('aInventory') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="a-price">{{ __('sponsorship.fields.price') }} ({{ Money::unitLabel($unit) }})</label>
                            <input id="a-price" type="text" inputmode="numeric" dir="ltr" wire:model="aPrice" class="{{ $input }}">
                            @error('aPrice') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $label }}" for="a-desc">{{ __('sponsorship.fields.asset_description') }}</label>
                            <textarea id="a-desc" rows="2" wire:model="aDescription" class="{{ $input }}"></textarea>
                            @error('aDescription') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="{{ $btn }}">{{ $editingAssetId ? __('sponsorship.save_changes') : __('sponsorship.create') }}</button>
                        <button type="button" wire:click="cancelAssetForm" class="{{ $btnGhost }}">{{ __('sponsorship.cancel') }}</button>
                    </div>
                </form>
            @endif

            <div class="space-y-2">
                @forelse ($assets as $asset)
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-ink-100 bg-white p-3.5" wire:key="as-{{ $asset->id }}">
                        <div>
                            <p class="text-sm font-bold text-ink-900">{{ $asset->name }}</p>
                            <p class="mt-0.5 text-[11px] text-ink-500">
                                {{ $asset->type->label() }}
                                · {{ __('sponsorship.inventory_line', ['inventory' => fa_num($asset->inventory), 'allocated' => fa_num($asset->allocated), 'remaining' => fa_num($asset->remaining)]) }}
                                @if ($canManage) · {{ fa_num(Money::format((int) $asset->price, $unit)) }} @endif
                            </p>
                        </div>
                        @if ($canEdit)
                            <div class="flex gap-1.5">
                                <button type="button" wire:click="editAsset({{ $asset->id }})" class="{{ $mini }}">{{ __('sponsorship.edit') }}</button>
                                <button type="button" wire:click="deleteAsset({{ $asset->id }})" wire:confirm="{{ __('sponsorship.confirm_delete') }}" class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-signal-600 hover:bg-signal-50">{{ __('sponsorship.delete') }}</button>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-ink-200 bg-white px-5 py-8 text-center text-sm text-ink-400">{{ __('sponsorship.assets_empty') }}</div>
                @endforelse
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 pt-2">
                <h3 class="text-sm font-bold text-ink-800">{{ __('sponsorship.packages_title') }}</h3>
                @if ($canEdit)
                    <button type="button" wire:click="openPackageForm" class="{{ $btn }}">{{ __('sponsorship.add_package') }}</button>
                @endif
            </div>

            @if ($showPackageForm && $canEdit)
                <form wire:submit="savePackage" class="space-y-3 rounded-2xl border border-pool-100 bg-white p-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="{{ $label }}" for="p-name">{{ __('sponsorship.fields.package_name') }}</label>
                            <input id="p-name" type="text" wire:model="pName" maxlength="120" class="{{ $input }}">
                            @error('pName') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="p-price">{{ __('sponsorship.fields.package_price') }} ({{ Money::unitLabel($unit) }})</label>
                            <input id="p-price" type="text" inputmode="numeric" dir="ltr" wire:model="pPrice" class="{{ $input }}">
                            @error('pPrice') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $label }}" for="p-desc">{{ __('sponsorship.fields.package_description') }}</label>
                            <textarea id="p-desc" rows="2" wire:model="pDescription" class="{{ $input }}"></textarea>
                            @error('pDescription') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <p class="{{ $label }}">{{ __('sponsorship.package_assets_hint') }}</p>
                        @forelse ($assets as $asset)
                            <div class="mt-1.5 flex items-center justify-between gap-3 rounded-xl bg-ink-50 px-3 py-2" wire:key="pq-{{ $asset->id }}">
                                <span class="text-xs text-ink-800">{{ $asset->name }}</span>
                                <input type="text" inputmode="numeric" dir="ltr" wire:model="pQty.{{ $asset->id }}" placeholder="۰"
                                       class="w-20 rounded-lg border border-ink-200 bg-white px-2 py-1.5 text-center text-xs" aria-label="{{ __('sponsorship.fields.quantity') }}">
                            </div>
                        @empty
                            <p class="mt-1 text-xs text-ink-400">{{ __('sponsorship.assets_empty') }}</p>
                        @endforelse
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="{{ $btn }}">{{ $editingPackageId ? __('sponsorship.save_changes') : __('sponsorship.create') }}</button>
                        <button type="button" wire:click="cancelPackageForm" class="{{ $btnGhost }}">{{ __('sponsorship.cancel') }}</button>
                    </div>
                </form>
            @endif

            <div class="space-y-2">
                @forelse ($packages as $package)
                    <div class="rounded-2xl border border-ink-100 bg-white p-3.5" wire:key="pk-{{ $package->id }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-bold text-ink-900">{{ $package->name }}</p>
                                <p class="mt-0.5 text-[11px] text-ink-500">
                                    @if ($canManage) {{ fa_num(Money::format((int) $package->price, $unit)) }} · @endif
                                    {{ __('sponsorship.package_sponsors', ['count' => fa_num($package->sponsors_count)]) }}
                                </p>
                            </div>
                            @if ($canEdit)
                                <div class="flex gap-1.5">
                                    <button type="button" wire:click="editPackage({{ $package->id }})" class="{{ $mini }}">{{ __('sponsorship.edit') }}</button>
                                    <button type="button" wire:click="deletePackage({{ $package->id }})" wire:confirm="{{ __('sponsorship.confirm_delete') }}" class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-signal-600 hover:bg-signal-50">{{ __('sponsorship.delete') }}</button>
                                </div>
                            @endif
                        </div>
                        @if ($package->description)
                            <p class="mt-1 text-[11px] leading-5 text-ink-500">{{ $package->description }}</p>
                        @endif
                        <ul class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($package->assets as $pa)
                                <li class="rounded-full bg-pool-50 px-2.5 py-0.5 text-[11px] font-semibold text-pool-800" wire:key="pka-{{ $package->id }}-{{ $pa->id }}">{{ $pa->name }} × {{ fa_num($pa->pivot->quantity) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-ink-200 bg-white px-5 py-8 text-center text-sm text-ink-400">{{ __('sponsorship.packages_empty') }}</div>
                @endforelse
            </div>
        @endif

        {{-- ============================== تعهدها ============================== --}}
        @if ($section === 'deliverables')
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap gap-2">
                    <select wire:model.live="filterDeliverables" class="{{ $input }} !w-auto" aria-label="{{ __('sponsorship.fields.status') }}">
                        <option value="open">{{ __('sponsorship.filter_open') }}</option>
                        <option value="all">{{ __('sponsorship.filter_all') }}</option>
                        @foreach ($deliverableStatuses as $ds)
                            <option value="{{ $ds->value }}">{{ $ds->label() }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="filterDeliverableSponsor" class="{{ $input }} !w-auto" aria-label="{{ __('sponsorship.fields.sponsor') }}">
                        <option value="">{{ __('sponsorship.all_sponsors') }}</option>
                        @foreach ($sponsors as $sp)
                            <option value="{{ $sp->id }}">{{ $sp->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($canEdit)
                    <button type="button" wire:click="openDeliverableForm" class="{{ $btn }}">{{ __('sponsorship.add_deliverable') }}</button>
                @endif
            </div>

            @if ($showDeliverableForm && $canEdit)
                <form wire:submit="saveDeliverable" class="space-y-3 rounded-2xl border border-pool-100 bg-white p-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="{{ $label }}" for="d-sp">{{ __('sponsorship.fields.sponsor') }}</label>
                            <select id="d-sp" wire:model="dSponsorId" @disabled($editingDeliverableId) class="{{ $input }}">
                                <option value="">{{ __('sponsorship.choose') }}</option>
                                @foreach ($sponsors as $sp)
                                    <option value="{{ $sp->id }}">{{ $sp->name }}</option>
                                @endforeach
                            </select>
                            @error('dSponsorId') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="d-asset">{{ __('sponsorship.fields.asset') }}</label>
                            <select id="d-asset" wire:model="dAssetId" class="{{ $input }}">
                                <option value="">{{ __('sponsorship.no_asset') }}</option>
                                @foreach ($assets as $asset)
                                    <option value="{{ $asset->id }}">{{ $asset->name }} ({{ __('sponsorship.remaining_short', ['count' => fa_num($asset->remaining)]) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $label }}" for="d-com">{{ __('sponsorship.fields.commitment') }}</label>
                            <input id="d-com" type="text" wire:model="dCommitment" maxlength="255" class="{{ $input }}">
                            @error('dCommitment') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="d-qty">{{ __('sponsorship.fields.quantity') }}</label>
                            <input id="d-qty" type="text" inputmode="numeric" dir="ltr" wire:model="dQuantity" class="{{ $input }}">
                            @error('dQuantity') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="d-due">{{ __('sponsorship.fields.due_at') }}</label>
                            <x-jalali-picker id="d-due" wire:model="dDue" />
                            @error('dDue') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="{{ $btn }}">{{ $editingDeliverableId ? __('sponsorship.save_changes') : __('sponsorship.create') }}</button>
                        <button type="button" wire:click="cancelDeliverableForm" class="{{ $btnGhost }}">{{ __('sponsorship.cancel') }}</button>
                    </div>
                </form>
            @endif

            <div class="space-y-2">
                @forelse ($deliverables as $d)
                    <div class="rounded-2xl border bg-white p-3.5 {{ $d->isOverdue() ? 'border-signal-200' : 'border-ink-100' }}" wire:key="dl-{{ $d->id }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-bold text-ink-900">{{ $d->commitment }}</p>
                                <p class="mt-0.5 text-[11px] text-ink-500">
                                    {{ $d->sponsor?->name }}
                                    @if ($d->asset) · {{ $d->asset->name }} × {{ fa_num($d->quantity) }} @endif
                                    @if ($d->due_at) · {{ __('sponsorship.fields.due_at') }}: {{ jalali($d->due_at) }} @endif
                                    @if ($d->isOverdue()) · <span class="font-bold text-signal-600">{{ __('sponsorship.overdue') }}</span> @endif
                                </p>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $d->status->badgeClasses() }}">{{ $d->status->label() }}</span>
                        </div>

                        @if ($d->task)
                            <p class="mt-2 text-[11px] text-ink-600">
                                {{ __('sponsorship.linked_task') }}: <span class="font-semibold">{{ $d->task->title }}</span>
                                ({{ $d->task->status->label() }})
                            </p>
                        @endif

                        @if ($canEdit)
                            <div class="mt-3 flex flex-wrap items-center gap-1.5">
                                @foreach ($deliverableStatuses as $ds)
                                    @if ($ds !== $d->status)
                                        <button type="button" wire:click="changeDeliverableStatus({{ $d->id }}, '{{ $ds->value }}')" class="{{ $mini }}" wire:key="dls-{{ $d->id }}-{{ $ds->value }}">← {{ $ds->label() }}</button>
                                    @endif
                                @endforeach
                                <button type="button" wire:click="editDeliverable({{ $d->id }})" class="{{ $mini }}">{{ __('sponsorship.edit') }}</button>
                                <button type="button" wire:click="toggleDeliverable({{ $d->id }})" class="{{ $mini }}">{{ $expandedDeliverableId === $d->id ? __('sponsorship.collapse') : __('sponsorship.details') }}</button>
                                <button type="button" wire:click="deleteDeliverable({{ $d->id }})" wire:confirm="{{ __('sponsorship.confirm_delete') }}" class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-signal-600 hover:bg-signal-50">{{ __('sponsorship.delete') }}</button>
                            </div>
                        @endif

                        @if ($canManage && $expandedDeliverableId === $d->id)
                            <div class="mt-3 space-y-4 rounded-2xl bg-ink-50/60 p-3">
                                {{-- مدرک تحویل --}}
                                <div>
                                    <p class="{{ $label }}">{{ __('sponsorship.fields.evidence') }}</p>
                                    @if ($d->evidence_path)
                                        <div class="flex flex-wrap gap-1.5">
                                            <button type="button" wire:click="downloadEvidence({{ $d->id }})" class="{{ $mini }}">{{ __('sponsorship.download_evidence') }}</button>
                                            @if ($canEdit)
                                                <button type="button" wire:click="removeEvidence({{ $d->id }})" wire:confirm="{{ __('sponsorship.confirm_delete') }}" class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-signal-600 hover:bg-signal-50">{{ __('sponsorship.remove_evidence') }}</button>
                                            @endif
                                        </div>
                                    @else
                                        <p class="text-[11px] text-ink-400">{{ __('sponsorship.no_evidence') }}</p>
                                    @endif
                                    @if ($canEdit)
                                        <div class="mt-2 flex flex-wrap items-center gap-2">
                                            <input type="file" wire:model="evidence" accept=".pdf,.jpg,.jpeg,.png" class="text-xs">
                                            <button type="button" wire:click="saveEvidence({{ $d->id }})" class="{{ $mini }}">{{ __('sponsorship.upload_evidence') }}</button>
                                        </div>
                                        <p class="mt-1 text-[10px] text-ink-400">{{ __('sponsorship.evidence_hint', ['max' => fa_num(intdiv($maxKb, 1024))]) }}</p>
                                        @error('evidence') <p class="{{ $err }}">{{ $message }}</p> @enderror
                                    @endif
                                </div>

                                {{-- وظیفه‌ی روز رویداد --}}
                                @if ($canEdit && ! $d->task && $d->status !== DeliverableStatus::Cancelled)
                                    <div>
                                        <p class="{{ $label }}">{{ __('sponsorship.create_task_title') }}</p>
                                        <div class="grid gap-2 sm:grid-cols-3">
                                            <input type="text" wire:model="ctTitle" maxlength="200" placeholder="{{ __('sponsorship.task_title') }}" class="{{ $input }}">
                                            <select wire:model="ctCommitteeId" class="{{ $input }}" aria-label="{{ __('sponsorship.task_committee') }}">
                                                <option value="">{{ __('sponsorship.task_no_committee') }}</option>
                                                @foreach ($committees as $c)
                                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                                @endforeach
                                            </select>
                                            <select wire:model="ctAssigneeId" class="{{ $input }}" aria-label="{{ __('sponsorship.task_assignee') }}">
                                                <option value="">{{ __('sponsorship.task_no_assignee') }}</option>
                                                @foreach ($assignees as $u)
                                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @error('ctTitle') <p class="{{ $err }}">{{ $message }}</p> @enderror
                                        <button type="button" wire:click="createDeliverableTask({{ $d->id }})" class="{{ $mini }} mt-2">{{ __('sponsorship.create_task') }}</button>

                                        <p class="{{ $label }} mt-3">{{ __('sponsorship.link_task_title') }}</p>
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            <select wire:model.live="linkCommitteeId" class="{{ $input }}" aria-label="{{ __('sponsorship.task_committee') }}">
                                                <option value="">{{ __('sponsorship.link_all_committees') }}</option>
                                                <option value="none">{{ __('sponsorship.task_no_committee') }}</option>
                                                @foreach ($committees as $c)
                                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                                @endforeach
                                            </select>
                                            <select wire:model="ctTaskId" class="{{ $input }}" aria-label="{{ __('sponsorship.link_task_title') }}">
                                                <option value="">{{ __('sponsorship.choose') }}</option>
                                                @foreach ($linkableTasks as $t)
                                                    <option value="{{ $t->id }}">{{ $t->title }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="button" wire:click="linkDeliverableTask({{ $d->id }})" class="{{ $mini }} mt-2">{{ __('sponsorship.link_task') }}</button>
                                    </div>
                                @elseif ($canEdit && $d->task)
                                    <button type="button" wire:click="unlinkDeliverableTask({{ $d->id }})" class="{{ $mini }}">{{ __('sponsorship.unlink_task') }}</button>
                                @endif

                                <x-activity-log :subject="$d" :limit="6" class="rounded-2xl bg-white p-3" />
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-ink-200 bg-white px-5 py-10 text-center text-sm text-ink-400">{{ __('sponsorship.deliverables_empty') }}</div>
                @endforelse
            </div>
        @endif
    @endif
</div>
