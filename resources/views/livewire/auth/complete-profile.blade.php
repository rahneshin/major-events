<div class="w-full max-w-sm">
    <div class="mb-8 text-center">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('auth.complete_profile_title') }}</h1>
        <p class="mt-2 text-sm text-gray-500">{{ __('auth.complete_profile_subtitle') }}</p>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            <ul class="list-disc ps-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form wire:submit.prevent="save" class="space-y-5">
        <div class="space-y-1.5">
            <label for="name" class="block text-sm font-medium text-gray-700">
                {{ __('ui.name') }}
                <span class="text-red-500">*</span>
            </label>

            <input
                id="name"
                type="text"
                wire:model="name"
                placeholder="{{ __('auth.name_placeholder') }}"
                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm
                       placeholder:text-gray-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                autocomplete="name"
            />

            @error('name')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            class="inline-flex w-full items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove wire:target="save">{{ __('ui.save') }}</span>
            <span wire:loading wire:target="save">{{ __('ui.loading') }}</span>
        </button>
    </form>

    <p class="mt-4 text-center text-xs text-gray-400" wire:loading wire:target="save">
        در حال ذخیره...
    </p>
</div>
