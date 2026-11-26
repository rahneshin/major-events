<div class="space-y-3">
    @if ($rows->isNotEmpty())
        <div class="glass space-y-3 rounded-2xl p-5">
            <div>
                <h3 class="text-sm font-bold text-slate-900">{{ __('publicpage.links.title') }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ __('publicpage.links.hint') }}</p>
            </div>

            @if ($notice)
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs text-emerald-800">{{ $notice }}</div>
            @endif
            @if ($error)
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-xs text-rose-800">{{ $error }}</div>
            @endif

            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($rows as $row)
                    <li class="space-y-2 py-3" wire:key="pl-{{ $row['id'] }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-semibold text-slate-900">{{ $row['name'] }}</span>
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $row['url'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $row['url'] ? __('publicpage.links.active') : __('publicpage.links.inactive') }}
                            </span>
                        </div>

                        @if ($row['url'])
                            <div class="flex gap-2" x-data="{ copied: false }">
                                <input type="text" readonly x-ref="url" value="{{ $row['url'] }}" dir="ltr"
                                    class="min-w-0 flex-1 rounded-lg border-slate-300 bg-slate-50 text-xs">
                                <button type="button"
                                    @click="navigator.clipboard.writeText($refs.url.value); copied = true; setTimeout(() => copied = false, 1500)"
                                    class="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700">
                                    <span x-show="!copied">{{ __('publicpage.links.copy') }}</span>
                                    <span x-show="copied" x-cloak>{{ __('publicpage.links.copied') }}</span>
                                </button>
                            </div>
                        @endif

                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            @if (! $row['url'])
                                <button type="button" wire:click="enable({{ $row['id'] }})"
                                    class="rounded-lg bg-indigo-600 px-3 py-1.5 font-bold text-white hover:bg-indigo-700">{{ __('publicpage.links.enable') }}</button>
                            @else
                                <button type="button" wire:click="rotate({{ $row['id'] }})" wire:confirm="{{ __('publicpage.links.confirm_rotate') }}"
                                    class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-semibold text-slate-700">{{ __('publicpage.links.rotate') }}</button>
                                <button type="button" wire:click="disable({{ $row['id'] }})" wire:confirm="{{ __('publicpage.links.confirm_disable') }}"
                                    class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 font-semibold text-rose-700">{{ __('publicpage.links.disable') }}</button>
                            @endif

                            @if ($row['individual'])
                                <label class="flex items-center gap-1.5 text-slate-600">
                                    <input type="checkbox" class="rounded border-slate-300" @checked($row['abbreviate'])
                                        wire:click="toggleAbbreviate({{ $row['id'] }})">
                                    {{ __('publicpage.links.abbreviate') }}
                                </label>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
