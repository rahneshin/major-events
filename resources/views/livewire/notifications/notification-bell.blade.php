<div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false" wire:poll.60s>
    <button type="button" class="relative rounded-lg p-2 text-ink-600 transition hover:bg-ink-100" @click="open = ! open"
            aria-label="{{ __('notification.title') }}" :aria-expanded="open">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0"/></svg>
        @if ($unread > 0)
            <span class="absolute -end-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-signal-500 px-1 text-[10px] font-bold text-white">{{ $unread > 9 ? fa_num('9').'+' : fa_num((string) $unread) }}</span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.150ms
         class="absolute end-0 z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-ink-100 bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-ink-100 px-4 py-3">
            <p class="text-sm font-bold text-ink-900">{{ __('notification.title') }}</p>
            @if ($unread > 0)
                <button type="button" wire:click="markAllRead" class="text-xs font-semibold text-pool-700 hover:text-pool-800">{{ __('notification.mark_all_read') }}</button>
            @endif
        </div>

        @if ($items->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-ink-500">{{ __('notification.empty') }}</p>
        @else
            <ul class="max-h-80 divide-y divide-ink-100 overflow-y-auto">
                @foreach ($items as $item)
                    <li wire:key="bell-{{ $item['id'] }}" @class(['px-4 py-3 text-sm', 'bg-pool-50/60' => ! $item['read']])>
                        <p class="leading-6 text-ink-800">{{ $item['message'] }}</p>
                        <div class="mt-1 flex items-center justify-between text-[11px] text-ink-400">
                            <span>{{ jalali_long($item['at']) }}</span>
                            <span class="flex items-center gap-3">
                                @if ($item['url'])
                                    <a href="{{ $item['url'] }}" wire:navigate wire:click="markRead('{{ $item['id'] }}')" class="font-semibold text-pool-700 hover:text-pool-800">{{ __('notification.open') }}</a>
                                @endif
                                @unless ($item['read'])
                                    <button type="button" wire:click="markRead('{{ $item['id'] }}')" class="font-semibold text-ink-500 hover:text-ink-800">{{ __('notification.mark_read') }}</button>
                                @endunless
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <a href="{{ route('notifications.index') }}" wire:navigate class="block border-t border-ink-100 px-4 py-3 text-center text-xs font-semibold text-pool-700 hover:bg-ink-50">{{ __('notification.see_all') }}</a>
    </div>
</div>
