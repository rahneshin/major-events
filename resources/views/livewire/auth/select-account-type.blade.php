<div class="w-full max-w-lg">
    <div class="mb-8 text-center">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('account.select_type_title') }}</h1>
        <p class="mt-2 text-sm text-gray-500">{{ __('account.select_type_subtitle') }}</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ($types as $type)
            <button
                type="button"
                wire:click="select('{{ $type->value }}')"
                class="rounded-xl border-2 p-5 text-start transition
                    {{ $selected === $type->value
                        ? 'border-primary-600 bg-primary-50 ring-2 ring-primary-200'
                        : 'border-gray-200 bg-white hover:border-primary-300' }}"
            >
                <div class="mb-2 text-lg font-semibold text-gray-900">{{ $type->label() }}</div>
                <p class="text-sm text-gray-600">{{ $type->description() }}</p>

                @php($plan = config('plans.'.$type->value))
                <ul class="mt-4 space-y-1 text-xs text-gray-500">
                    <li>{{ __('account.limits.active_events') }}:
                        {{ $plan['limits']['active_events'] === null ? __('account.unlimited') : fa_num($plan['limits']['active_events']) }}
                    </li>
                    <li>{{ __('account.limits.organizations') }}:
                        {{ $plan['limits']['organizations'] === null ? __('account.unlimited') : fa_num($plan['limits']['organizations']) }}
                    </li>
                    <li>{{ __('account.limits.committees_per_event') }}:
                        {{ $plan['limits']['committees_per_event'] === null ? __('account.unlimited') : fa_num($plan['limits']['committees_per_event']) }}
                    </li>
                </ul>
            </button>
        @endforeach
    </div>

    @error('selected')
        <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
    @enderror

    <div class="mt-6">
        <x-button
            type="button"
            class="w-full"
            wire:click="save"
            wire:loading.attr="disabled"
            :disabled="! $selected"
        >
            <span wire:loading.remove wire:target="save">{{ __('account.confirm_selection') }}</span>
            <span wire:loading wire:target="save">{{ __('ui.loading') }}</span>
        </x-button>
    </div>
</div>
