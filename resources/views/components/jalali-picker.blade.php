@props(['id' => null, 'placeholder' => 'انتخاب تاریخ', 'clearable' => true])

{{--
    انتخاب‌گر تاریخ شمسی (resources/js/jalali.js ← jalaliPicker).
    مقدار به‌صورت ISO میلادی (YYYY-MM-DD) به Livewire می‌رسد؛ نمایش شمسی فقط در مرورگر است.

        <x-jalali-picker id="t-due" wire:model="dueAt" />
--}}
<div x-data="jalaliPicker" x-modelable="value" {{ $attributes->whereStartsWith('wire:model') }}
     class="relative" x-on:keydown.escape.stop="open = false" x-on:click.outside="open = false"
     x-on:scroll.window.capture.passive="open && place()" x-on:resize.window="open && place()">
    <button type="button" x-ref="btn" @if ($id) id="{{ $id }}" @endif x-on:click="toggle()"
            class="flex w-full items-center justify-between gap-2 rounded-xl border border-ink-200 bg-white px-3 py-2.5 text-start text-sm text-ink-900 transition-colors hover:border-ink-300 focus:border-pool-400 focus:outline-none focus:ring-2 focus:ring-pool-100"
            aria-haspopup="dialog" x-bind:aria-expanded="open.toString()">
        <span x-text="display || '{{ $placeholder }}'" :class="display ? 'text-ink-900' : 'text-ink-300'"></span>
        <svg class="h-4 w-4 shrink-0 text-ink-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M5 11h14M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.120ms role="dialog" aria-label="انتخاب تاریخ"
         x-bind:style="pos" class="fixed z-[80] w-72 rounded-2xl border border-ink-100 bg-white p-3 shadow-xl">
        <div class="mb-2 flex items-center justify-between">
            <button type="button" x-on:click="prev()" class="rounded-lg p-1.5 text-ink-600 hover:bg-ink-50" aria-label="ماه قبل">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
            <p class="text-sm font-bold text-ink-900" x-text="title"></p>
            <button type="button" x-on:click="next()" class="rounded-lg p-1.5 text-ink-600 hover:bg-ink-50" aria-label="ماه بعد">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
        </div>

        <div class="grid grid-cols-7 gap-0.5 pb-1 text-center text-[11px] font-semibold">
            <template x-for="(w, i) in weekdaysShort" :key="i">
                <div :class="i === 6 ? 'text-signal-500' : 'text-ink-400'" x-text="w"></div>
            </template>
        </div>

        <div class="grid grid-cols-7 gap-0.5">
            <template x-for="cell in grid" :key="cell.iso">
                <button type="button" x-on:click="pick(cell)"
                        class="flex h-9 items-center justify-center rounded-lg text-sm tabular-nums transition-colors"
                        :class="[
                            cell.iso === value ? 'bg-pool-600 font-bold text-white' : 'hover:bg-pool-50',
                            cell.iso !== value && cell.iso === today ? 'ring-1 ring-inset ring-pool-400' : '',
                            cell.iso !== value && !cell.inMonth ? 'text-ink-300' : '',
                            cell.iso !== value && cell.inMonth && (cell.friday || cell.holiday) ? 'text-signal-600' : '',
                            cell.iso !== value && cell.inMonth && !cell.friday && !cell.holiday ? 'text-ink-800' : '',
                        ]"
                        :title="cell.holiday || ''" :aria-label="fa(cell.jd) + ' ' + months[cell.jm - 1]" :aria-current="cell.iso === value ? 'date' : null"
                        x-text="fa(cell.jd)"></button>
            </template>
        </div>

        <div class="mt-2 flex items-center justify-between border-t border-ink-100 pt-2 text-xs font-semibold">
            <button type="button" x-on:click="pickToday()" class="rounded-lg px-2 py-1.5 text-pool-700 hover:bg-pool-50">امروز</button>
            @if ($clearable)
                <button type="button" x-on:click="clear()" class="rounded-lg px-2 py-1.5 text-ink-500 hover:bg-ink-50">پاک‌کردن</button>
            @endif
        </div>
    </div>
</div>
