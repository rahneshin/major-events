<div class="mx-auto max-w-2xl space-y-6">
    <x-page-header :title="__('account.my_account')" />

    <x-card>
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-sm text-ink-500">{{ __('ui.name') }}</span>
                <span class="font-medium">{{ $user->name }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm text-ink-500">{{ __('ui.phone') }}</span>
                <span class="font-medium" dir="ltr">{{ phone($user->phone) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm text-ink-500">{{ __('account.account_type') }}</span>
                <x-badge :color="$user->isOrganizational() ? 'primary' : 'gray'">
                    {{ $user->account_type?->label() ?? '—' }}
                </x-badge>
            </div>
        </div>
    </x-card>

    <x-card>
        <h2 class="mb-4 text-base font-semibold">{{ __('account.plan_limits') }}</h2>
        <ul class="space-y-2 text-sm">
            @foreach ($plan['limits'] as $key => $value)
                <li class="flex items-center justify-between border-b border-ink-100 py-2 last:border-0">
                    <span class="text-ink-600">{{ __('account.limits.'.$key) }}</span>
                    <span class="font-medium">
                        {{ $value === null ? __('account.unlimited') : fa_num($value) }}
                    </span>
                </li>
            @endforeach
        </ul>
    </x-card>

    @if ($canUpgrade)
        <x-card class="border-primary-200 bg-primary-50/50">
            <h2 class="mb-2 text-base font-semibold text-primary-900">{{ __('account.upgrade_title') }}</h2>
            <p class="mb-4 text-sm text-primary-800">{{ __('account.upgrade_description') }}</p>
            <x-button type="button" wire:click="upgrade" wire:loading.attr="disabled" wire:confirm="{{ __('account.upgrade_confirm') }}">
                <span wire:loading.remove wire:target="upgrade">{{ __('account.upgrade_cta') }}</span>
                <span wire:loading wire:target="upgrade">{{ __('ui.loading') }}</span>
            </x-button>
        </x-card>
    @endif

    <form method="POST" action="{{ route('auth.logout') }}">
        @csrf
        <x-button type="submit" variant="secondary" class="w-full">{{ __('ui.logout') }}</x-button>
    </form>
</div>
