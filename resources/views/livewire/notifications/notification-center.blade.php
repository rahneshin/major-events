<div class="space-y-6">
    @section('page_title', __('notification.title'))

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-2xl border border-ink-100 bg-white p-5 lg:col-span-2">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-ink-900">{{ __('notification.title') }}</h2>
                    <p class="text-xs text-ink-500">{{ __('notification.unread_count', ['count' => fa_num((string) $unread)]) }}</p>
                </div>
                <div class="flex items-center gap-3 text-xs">
                    <label class="flex items-center gap-1.5 text-ink-600">
                        <input type="checkbox" wire:model.live="onlyUnread" class="rounded border-ink-300 text-pool-600">
                        {{ __('notification.only_unread') }}
                    </label>
                    @if ($unread > 0)
                        <button type="button" wire:click="markAllRead" class="font-semibold text-pool-700 hover:text-pool-800">{{ __('notification.mark_all_read') }}</button>
                    @endif
                </div>
            </div>

            @if ($items->isEmpty())
                <div class="rounded-2xl border border-dashed border-ink-200 px-6 py-12 text-center">
                    <p class="text-sm font-bold text-ink-900">{{ __('notification.empty') }}</p>
                    <p class="mt-1 text-xs text-ink-500">{{ __('notification.empty_hint') }}</p>
                </div>
            @else
                <ul class="divide-y divide-ink-100">
                    @foreach ($items as $item)
                        <li wire:key="nc-{{ $item['id'] }}" @class(['flex items-start justify-between gap-3 py-3', 'bg-pool-50/40' => ! $item['read']])>
                            <div class="min-w-0">
                                <p class="text-sm leading-6 text-ink-800">{{ $item['message'] }}</p>
                                <p class="mt-0.5 text-[11px] text-ink-400">{{ jalali_long($item['at']) }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-3 text-xs">
                                @if ($item['url'])
                                    <a href="{{ $item['url'] }}" wire:navigate wire:click="markRead('{{ $item['id'] }}')" class="font-semibold text-pool-700 hover:text-pool-800">{{ __('notification.open') }}</a>
                                @endif
                                @unless ($item['read'])
                                    <button type="button" wire:click="markRead('{{ $item['id'] }}')" class="font-semibold text-ink-500 hover:text-ink-800">{{ __('notification.mark_read') }}</button>
                                @endunless
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <aside class="rounded-2xl border border-ink-100 bg-white p-5">
            <h3 class="text-base font-bold text-ink-900">{{ __('notification.preferences') }}</h3>
            <p class="mt-1 text-xs leading-6 text-ink-500">{{ __('notification.preferences_hint') }}</p>

            <div class="mt-4 divide-y divide-ink-100">
                @foreach ($prefs as $category => $pref)
                    <div class="py-3" wire:key="pref-{{ $category }}">
                        <p class="text-sm font-semibold text-ink-800">{{ __('notification.categories.'.$category) }}</p>
                        <div class="mt-2 flex items-center gap-2 text-xs">
                            <button type="button" wire:click="toggle('{{ $category }}', 'in_app')"
                                @class(['rounded-lg px-3 py-1.5 font-semibold transition', 'bg-pool-700 text-white' => $pref['in_app'], 'bg-ink-100 text-ink-500' => ! $pref['in_app']])
                                aria-pressed="{{ $pref['in_app'] ? 'true' : 'false' }}">
                                {{ __('notification.channel_in_app') }}
                            </button>
                            @if ($pref['sms_supported'])
                                <button type="button" wire:click="toggle('{{ $category }}', 'sms')"
                                    @class(['rounded-lg px-3 py-1.5 font-semibold transition', 'bg-pool-700 text-white' => $pref['sms'], 'bg-ink-100 text-ink-500' => ! $pref['sms']])
                                    aria-pressed="{{ $pref['sms'] ? 'true' : 'false' }}">
                                    {{ __('notification.channel_sms') }}
                                </button>
                            @else
                                <span class="text-[11px] text-ink-400">{{ __('notification.in_app_only') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-[11px] leading-5 text-ink-400">{{ __('notification.sms_note', ['max' => fa_num((string) config('notifications.sms.per_user_per_day'))]) }}</p>
        </aside>
    </div>
</div>
