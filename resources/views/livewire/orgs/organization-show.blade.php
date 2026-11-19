<div class="space-y-6">
    <x-page-header :title="$organization->name" :subtitle="$organization->type->label()">
        <x-slot:actions>
            @if ($canUpdate)
                <a href="{{ route('orgs.edit', $organization) }}" class="inline-flex items-center rounded-lg border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50">
                    {{ __('ui.edit') }}
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-1">
            <h2 class="mb-3 text-sm font-semibold text-gray-900">{{ __('org.info') }}</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-2">
                    <dt class="text-gray-500">{{ __('org.city') }}</dt>
                    <dd>{{ $organization->city ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-gray-500">{{ __('org.owner') }}</dt>
                    <dd>{{ $organization->owner?->name ?? phone($organization->owner?->phone) }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-gray-500">{{ __('ui.status') }}</dt>
                    <dd>{{ $organization->status }}</dd>
                </div>
            </dl>
            @if ($organization->description)
                <p class="mt-4 text-sm text-gray-600">{{ $organization->description }}</p>
            @endif

            @unless ($isOwner)
                <button type="button" wire:click="leave" wire:confirm="{{ __('org.leave_confirm') }}" class="mt-4 text-sm text-red-600 hover:text-red-700">
                    {{ __('org.leave') }}
                </button>
            @endunless
        </x-card>

        <div class="space-y-6 lg:col-span-2">
            <x-card>
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('org.members') }}</h2>
                </div>

                <ul class="divide-y divide-gray-100">
                    @foreach ($organization->members as $member)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div>
                                <p class="font-medium text-gray-900">{{ $member->name ?? phone($member->phone) }}</p>
                                <p class="text-xs text-gray-500" dir="ltr">{{ phone($member->phone) }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($canManage && $member->id !== $organization->owner_id)
                                    <select
                                        class="rounded-lg border border-gray-300 px-2 py-1 text-xs"
                                        wire:change="changeRole({{ $member->id }}, $event.target.value)"
                                    >
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->value }}" @selected($member->pivot->role === $role->value)>
                                                {{ $role->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="button" wire:click="removeMember({{ $member->id }})" wire:confirm="{{ __('org.remove_confirm') }}" class="text-xs text-red-600">
                                        {{ __('ui.delete') }}
                                    </button>
                                    @if ($canTransfer)
                                        <button type="button" wire:click="transferTo({{ $member->id }})" wire:confirm="{{ __('org.transfer_confirm') }}" class="text-xs text-primary-600">
                                            {{ __('org.transfer') }}
                                        </button>
                                    @endif
                                @else
                                    <x-badge color="gray">{{ \App\Enums\OrgRole::from($member->pivot->role)->label() }}</x-badge>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            @if ($canManage)
                <x-card>
                    <h2 class="mb-4 text-sm font-semibold text-gray-900">{{ __('org.invite_title') }}</h2>
                    <form wire:submit="invite" class="grid gap-3 sm:grid-cols-3">
                        <div class="sm:col-span-1">
                            <input type="tel" wire:model="invitePhone" placeholder="09..." dir="ltr" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                            @error('invitePhone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <select wire:model="inviteRole" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                @foreach ($roles as $role)
                                    <option value="{{ $role->value }}">{{ $role->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                                {{ __('org.send_invite') }}
                            </button>
                        </div>
                    </form>

                    @if ($pendingInvites->isNotEmpty())
                        <ul class="mt-4 space-y-2 text-sm text-gray-600">
                            @foreach ($pendingInvites as $invite)
                                <li class="flex justify-between border-t border-gray-100 pt-2">
                                    <span dir="ltr">{{ phone($invite->phone) }}</span>
                                    <span>{{ $invite->role->label() }} · {{ __('org.pending') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            @endif
        </div>
    </div>
</div>
