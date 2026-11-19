<div>
    <x-page-header :title="__('org.index_title')" :subtitle="__('org.index_subtitle')">
        <x-slot:actions>
            @if ($canCreate)
                <a href="{{ route('orgs.create') }}" class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                    {{ __('org.create') }}
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($organizations->isEmpty())
        <x-empty-state :title="__('org.empty_title')" :description="__('org.empty_description')" />
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($organizations as $org)
                <x-card class="relative">
                    <div class="mb-3 flex items-start justify-between gap-2">
                        <div>
                            <a href="{{ route('orgs.show', $org) }}" class="text-lg font-semibold text-gray-900 hover:text-primary-700">
                                {{ $org->name }}
                            </a>
                            <p class="mt-1 text-xs text-gray-500">{{ $org->type->label() }}</p>
                        </div>
                        @if ($currentId === $org->id)
                            <x-badge color="primary">{{ __('org.current') }}</x-badge>
                        @endif
                    </div>

                    @if ($org->city)
                        <p class="mb-3 text-sm text-gray-600">{{ $org->city }}</p>
                    @endif

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('orgs.show', $org) }}" class="text-sm font-medium text-primary-600 hover:text-primary-700">
                            {{ __('ui.view') }}
                        </a>
                        @if ($currentId !== $org->id)
                            <button type="button" wire:click="switchTo({{ $org->id }})" class="text-sm text-gray-600 hover:text-gray-900">
                                {{ __('org.switch') }}
                            </button>
                        @endif
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</div>
