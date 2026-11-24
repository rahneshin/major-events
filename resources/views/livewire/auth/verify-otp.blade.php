<div class="w-full max-w-sm" x-data="{ seconds: @js($resendAvailableIn) }" x-init="
    if (seconds > 0) {
        let timer = setInterval(() => {
            seconds--;
            if (seconds <= 0) clearInterval(timer);
        }, 1000);
    }
">
    <div class="mb-8 text-center">
        <h1 class="text-2xl font-bold text-ink-900">{{ __('auth.verify_title') }}</h1>
        <p class="mt-2 text-sm text-ink-500">
            {{ __('auth.verify_subtitle') }}
            <span dir="ltr" class="font-medium text-ink-800">{{ phone($phone) }}</span>
        </p>
    </div>

    @if ($debugCode)
        <div class="mb-5 rounded-xl border border-flag-300 bg-flag-50 p-4 text-center">
            <p class="text-xs font-medium text-flag-800">{{ __('auth.debug_code_label') }}</p>
            <p class="mt-1 text-3xl font-bold tracking-[0.3em] text-flag-900" dir="ltr">{{ $debugCode }}</p>
            <button
                type="button"
                wire:click="useDebugCode"
                class="mt-3 text-sm font-medium text-flag-800 underline hover:text-flag-950"
            >
                {{ __('auth.debug_code_fill') }}
            </button>
        </div>
    @endif

    <form wire:submit="verify" class="space-y-5">
        <x-input
            label="{{ __('auth.code') }}"
            name="code"
            type="text"
            inputmode="numeric"
            maxlength="6"
            wire:model="code"
            placeholder="۱۲۳۴۵۶"
            dir="ltr"
            required
            :error="$errors->first('code')"
            autocomplete="one-time-code"
        />

        <x-button type="submit" class="w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="verify">{{ __('auth.verify_code') }}</span>
            <span wire:loading wire:target="verify">{{ __('ui.loading') }}</span>
        </x-button>
    </form>

    <div class="mt-6 text-center text-sm text-ink-500">
        <template x-if="seconds > 0">
            <p>
                {{ __('auth.resend_in') }}
                <span x-text="seconds" class="font-medium" dir="ltr"></span>
                {{ __('auth.seconds') }}
            </p>
        </template>
        <template x-if="seconds <= 0">
            <button
                type="button"
                wire:click="resend"
                wire:loading.attr="disabled"
                class="font-medium text-primary-600 hover:text-primary-700"
                @click="seconds = 60; let t = setInterval(() => { seconds--; if (seconds <= 0) clearInterval(t); }, 1000)"
            >
                {{ __('auth.resend_code') }}
            </button>
        </template>
    </div>

    <div class="mt-4 text-center">
        <a href="{{ route('auth.login') }}" class="text-sm text-ink-500 hover:text-ink-700" wire:navigate>
            {{ __('auth.change_phone') }}
        </a>
    </div>
</div>
