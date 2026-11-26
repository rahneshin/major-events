<div class="space-y-4">
    @if ($notice)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $notice }}</div>
    @endif
    @if ($error)
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $error }}</div>
    @endif

    @if (! $hasFeature)
        <div class="glass rounded-2xl px-6 py-12 text-center">
            <p class="text-sm font-semibold text-slate-700">{{ __('competition.feature_unavailable_title') }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ __('competition.errors.feature_unavailable') }}</p>
        </div>
    @else
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900">{{ __('competition.title') }}</h3>
            @if ($canCreate && ! $showForm)
                <button type="button" wire:click="openForm"
                    class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-md hover:bg-indigo-700">
                    {{ __('competition.add') }}
                </button>
            @endif
        </div>

        @if ($showForm)
            <form wire:submit="save" class="glass grid gap-3 rounded-2xl p-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('competition.fields.template') }}</label>
                    <select wire:model="cTemplate" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">—</option>
                        @foreach ($templates as $tpl)
                            <option value="{{ $tpl->code }}">{{ $tpl->name_fa }}</option>
                        @endforeach
                    </select>
                    @error('cTemplate') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('competition.fields.name') }}</label>
                    <input type="text" wire:model="cName" maxlength="120" class="w-full rounded-lg border-slate-300 text-sm">
                    @error('cName') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('competition.fields.gender') }}</label>
                    <select wire:model="cGender" class="w-full rounded-lg border-slate-300 text-sm">
                        @foreach ($genders as $g)
                            <option value="{{ $g->value }}">{{ $g->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('competition.fields.capacity') }}</label>
                    <input type="number" wire:model="cCapacity" min="2" class="w-full rounded-lg border-slate-300 text-sm">
                    @error('cCapacity') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-2 sm:col-span-2">
                    <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">{{ __('competition.save') }}</button>
                    <button type="button" wire:click="cancelForm" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600">{{ __('competition.cancel') }}</button>
                </div>
            </form>
        @endif

        @forelse ($competitions as $c)
            <a href="{{ route('competitions.show', ['event' => $event, 'competition' => $c]) }}" wire:navigate
                class="glass flex items-center justify-between rounded-2xl p-4 transition hover:shadow-lg">
                <div>
                    <p class="text-sm font-bold text-slate-900">{{ $c->name }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ $c->gender->label() }} · {{ $c->participant_type->label() }} ·
                        {{ __('competition.competitors_count', ['count' => fa_num($c->active_competitors_count)]) }}
                    </p>
                </div>
                <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-[11px] font-semibold text-indigo-700">{{ $c->status->label() }}</span>
            </a>
        @empty
            <div class="glass rounded-2xl px-6 py-12 text-center text-sm text-slate-500">{{ __('competition.empty') }}</div>
        @endforelse

        @livewire('events.event-medals', ['event' => $event])
        @livewire('competitions.public-links', ['event' => $event])
    @endif
</div>
