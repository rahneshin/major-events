<div class="mx-auto max-w-xl">
    <x-page-header :title="$isEdit ? __('org.edit_title') : __('org.create_title')" />

    <x-card>
        <form wire:submit="save" class="space-y-5">
            <div class="space-y-1.5">
                <label class="block text-sm font-medium text-gray-700">{{ __('org.name') }} *</label>
                <input type="text" wire:model="name" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" />
                @error('name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-1.5">
                <label class="block text-sm font-medium text-gray-700">{{ __('org.type') }} *</label>
                <select wire:model="type" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    @foreach ($types as $t)
                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                    @endforeach
                </select>
                @error('type') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-1.5">
                <label class="block text-sm font-medium text-gray-700">{{ __('org.city') }}</label>
                <input type="text" wire:model="city" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                @error('city') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-1.5">
                <label class="block text-sm font-medium text-gray-700">{{ __('org.description') }}</label>
                <textarea wire:model="description" rows="4" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                @error('description') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-3">
                <button type="submit" class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">{{ __('ui.save') }}</span>
                    <span wire:loading wire:target="save">{{ __('ui.loading') }}</span>
                </button>
                <a href="{{ route('orgs.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    {{ __('ui.cancel') }}
                </a>
            </div>
        </form>
    </x-card>
</div>
