{{-- تاریخچه‌ی اقدامات یک رکورد؛ هر خط: چه شد، در چه تاریخی، توسط چه کسی --}}
@props(['subject', 'limit' => 8, 'title' => null])

@php($logs = \App\Support\ActivityDescriber::for($subject, $limit))

<section {{ $attributes }}>
    <h4 class="mb-2 text-xs font-bold text-ink-700">{{ $title ?? __('activity.history') }}</h4>
    @if ($logs->isEmpty())
        <p class="text-xs text-ink-400">{{ __('activity.empty') }}</p>
    @else
        <ol class="space-y-1.5 border-s-2 border-ink-100 ps-3">
            @foreach ($logs as $log)
                <li class="text-[11px] leading-5 text-ink-500" wire:key="act-{{ $log->id }}">
                    <span class="font-semibold text-ink-700">{{ \App\Support\ActivityDescriber::describe($log) }}</span>
                    <span class="block text-ink-400">
                        {{ __('activity.on') }} {{ jalali_long($log->created_at) }}
                        {{ __('activity.by') }} {{ $log->user ? person_name($log->user) : __('activity.unknown_user') }}
                    </span>
                </li>
            @endforeach
        </ol>
    @endif
</section>
