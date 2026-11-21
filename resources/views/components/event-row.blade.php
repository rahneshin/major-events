{{-- ردیف رویداد: عنوان، زمان‌بندی، و طناب مراحل. استفاده: <x-event-row :event="$event" /> --}}
@props(['event'])

@php
    $order = [
        \App\Enums\EventStatus::Draft->value,
        \App\Enums\EventStatus::Planning->value,
        \App\Enums\EventStatus::Ready->value,
        \App\Enums\EventStatus::Running->value,
    ];
    $stage = array_search($event->status?->value, $order, true);
    $today = now()->startOfDay();
    $days = $event->start_date ? (int) $today->diffInDays($event->start_date->copy()->startOfDay(), false) : null;
    $ongoing = $days !== null && $days < 0 && $event->end_date && $event->end_date->copy()->startOfDay()->gte($today);
    $meta = collect([$event->event_type?->label(), $event->sport_type, $event->city])->filter()->implode('، ');
@endphp

<a href="{{ route('events.show', $event) }}" wire:navigate {{ $attributes }}
   class="group grid gap-4 px-4 py-4 transition-colors hover:bg-pool-50/70 sm:grid-cols-[1fr_17rem] sm:items-center sm:px-5">
    <div class="min-w-0">
        <div class="flex items-center gap-2">
            <h3 class="truncate text-base font-bold text-ink-900 group-hover:text-pool-700">{{ $event->title }}</h3>
            @if ($days !== null && $days > 0)
                <span class="shrink-0 rounded-md bg-flag-300/40 px-1.5 py-0.5 text-[11px] font-semibold text-ink-800">{{ fa_num((string) $days) }} روز تا شروع</span>
            @elseif ($days === 0)
                <span class="shrink-0 rounded-md bg-flag-300/40 px-1.5 py-0.5 text-[11px] font-semibold text-ink-800">امروز شروع می‌شود</span>
            @elseif ($ongoing)
                <span class="shrink-0 rounded-md bg-pool-100 px-1.5 py-0.5 text-[11px] font-semibold text-pool-800">در جریان</span>
            @endif
        </div>
        @if ($meta)
            <p class="mt-1 truncate text-xs text-ink-500">{{ $meta }}</p>
        @endif
        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-400">
            @if ($event->start_date)
                <span>{{ fa_num(jalali($event->start_date, 'Y/m/d')) }}@if ($event->end_date) تا {{ fa_num(jalali($event->end_date, 'Y/m/d')) }}@endif</span>
            @endif
            @if (isset($event->committees_count))
                <span>{{ fa_num((string) $event->committees_count) }} کمیته</span>
            @endif
            @if (isset($event->members_count))
                <span>{{ fa_num((string) $event->members_count) }} عضو</span>
            @endif
            @if ($event->organization)
                <span class="truncate">{{ $event->organization->name }}</span>
            @endif
        </div>
    </div>

    <div>
        <x-lane-rope :stage="$stage" :label="'مرحله: '.$event->status?->label()" />
        <p class="mt-2 text-[11px] font-medium text-ink-500">{{ $event->status?->label() }}</p>
    </div>
</a>
