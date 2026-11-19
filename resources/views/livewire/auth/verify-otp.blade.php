<div class="w-full max-w-sm" x-data="{ seconds: @js($resendAvailableIn) }" x-init="
    if (seconds > 0) {
        let timer = setInterval(() => {
            seconds--;
            if (seconds <= 0) clearInterval(timer);
        }, 1000);
    }
">
    <div class="mb-8 text-center">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('auth.verify_title') }}</h1>
        <p class="mt-2 text-sm text-gray-500">
            {{ __('auth.verify_subtitle') }}
            <span dir="ltr" class="font-medium text-gray-800">{{ phone($phone) }}</span>
        </p>
    </div>

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

    <div class="mt-6 text-center text-sm text-gray-500">
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
        <a href="{{ route('auth.login') }}" class="text-sm text-gray-500 hover:text-gray-700" wire:navigate>
            {{ __('auth.change_phone') }}
        </a>
    </div>
</div>
