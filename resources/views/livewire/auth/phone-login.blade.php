<div class="w-full max-w-sm">
    <div class="mb-8 text-center">
        <h1 class="text-2xl font-bold text-ink-900">{{ __('auth.login_title') }}</h1>
        <p class="mt-2 text-sm text-ink-500">{{ __('auth.login_subtitle') }}</p>
    </div>

    <form wire:submit="sendCode" class="space-y-5">
        <x-input
            label="{{ __('ui.phone') }}"
            name="phone"
            type="tel"
            wire:model="phone"
            placeholder="۰۹۱۲۳۴۵۶۷۸۹"
            dir="ltr"
            required
            :error="$errors->first('phone')"
            hint="{{ __('auth.phone_hint') }}"
        />

        <x-button type="submit" class="w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="sendCode">{{ __('auth.send_code') }}</span>
            <span wire:loading wire:target="sendCode">{{ __('ui.loading') }}</span>
        </x-button>
    </form>
</div>
