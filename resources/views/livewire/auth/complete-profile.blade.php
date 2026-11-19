<div class="w-full max-w-sm">
    <div class="mb-8 text-center">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('auth.complete_profile_title') }}</h1>
        <p class="mt-2 text-sm text-gray-500">{{ __('auth.complete_profile_subtitle') }}</p>
    </div>

    <form wire:submit="save" class="space-y-5">
        <x-input
            label="{{ __('ui.name') }}"
            name="name"
            wire:model="name"
            placeholder="{{ __('auth.name_placeholder') }}"
            required
            :error="$errors->first('name')"
        />

        <x-button type="submit" class="w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">{{ __('ui.save') }}</span>
            <span wire:loading wire:target="save">{{ __('ui.loading') }}</span>
        </x-button>
    </form>
</div>
