<div class="glass space-y-4 rounded-2xl p-5">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h3 class="text-sm font-bold text-slate-900">{{ __('medals.title') }}</h3>
            <p class="mt-1 text-xs text-slate-500">{{ __('medals.subtitle') }}</p>
        </div>
        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
            {{ __('medals.finished_count', ['count' => fa_num($table['competitions'])]) }}
        </span>
    </div>

    @if ($table['rows'] === [])
        <p class="py-6 text-center text-sm text-slate-500">{{ __('medals.empty') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-xs text-slate-400">
                        <th class="py-2 text-start">{{ __('medals.col.rank') }}</th>
                        <th class="text-start">{{ __('medals.col.affiliation') }}</th>
                        <th class="text-center">{{ __('medals.col.gold') }}</th>
                        <th class="text-center">{{ __('medals.col.silver') }}</th>
                        <th class="text-center">{{ __('medals.col.bronze') }}</th>
                        <th class="text-center">{{ __('medals.col.total') }}</th>
                        @if ($table['weights'] !== null)
                            <th class="text-center">{{ __('medals.col.points') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($table['rows'] as $row)
                        <tr class="border-b border-slate-50 align-top" wire:key="medal-{{ $loop->index }}">
                            <td class="py-2 tabular-nums font-bold">{{ fa_num($row['rank']) }}</td>
                            <td class="font-medium">
                                {{ $row['affiliation'] }}
                                <details class="mt-1 text-[11px] font-normal text-slate-500">
                                    <summary class="cursor-pointer">{{ __('ui.details') }}</summary>
                                    <ul class="mt-1 space-y-0.5">
                                        @foreach ($row['entries'] as $entry)
                                            <li>{{ __('medals.medal.'.$entry['medal']) }} · {{ $entry['competition'] }} · {{ $entry['name'] }}</li>
                                        @endforeach
                                    </ul>
                                </details>
                            </td>
                            <td class="text-center tabular-nums font-semibold text-amber-600">{{ fa_num($row['gold']) }}</td>
                            <td class="text-center tabular-nums font-semibold text-slate-500">{{ fa_num($row['silver']) }}</td>
                            <td class="text-center tabular-nums font-semibold text-orange-700">{{ fa_num($row['bronze']) }}</td>
                            <td class="text-center tabular-nums">{{ fa_num($row['total']) }}</td>
                            @if ($table['weights'] !== null)
                                <td class="text-center tabular-nums font-bold">{{ fa_num($row['points']) }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($table['unaffiliated'] > 0)
        <p class="text-xs text-amber-700">{{ __('medals.unaffiliated', ['count' => fa_num($table['unaffiliated'])]) }}</p>
    @endif

    @if ($canConfigure)
        <form wire:submit="saveSettings" class="space-y-3 border-t border-slate-100 pt-4">
            <h4 class="text-xs font-bold text-slate-700">{{ __('medals.settings_title') }}</h4>

            @if ($notice)
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs text-emerald-800">{{ $notice }}</div>
            @endif
            @if ($error)
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-xs text-rose-800">{{ $error }}</div>
            @endif

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" wire:model.live="wEnabled" class="rounded border-slate-300"> {{ __('medals.weights_enabled') }}
            </label>

            @if ($wEnabled)
                <div class="grid gap-3 sm:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('medals.weight_gold') }}</label>
                        <input type="number" min="0" max="1000" wire:model="wGold" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('medals.weight_silver') }}</label>
                        <input type="number" min="0" max="1000" wire:model="wSilver" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('medals.weight_bronze') }}</label>
                        <input type="number" min="0" max="1000" wire:model="wBronze" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">{{ __('medals.sort_label') }}</label>
                        <select wire:model="wSort" class="w-full rounded-lg border-slate-300 text-sm">
                            <option value="medals">{{ __('medals.sort.medals') }}</option>
                            <option value="points">{{ __('medals.sort.points') }}</option>
                        </select>
                    </div>
                </div>
            @endif

            <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">{{ __('competition.save') }}</button>
        </form>
    @endif
</div>
