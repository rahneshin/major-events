<?php

namespace App\Services;

use App\Enums\OrganizationType;
use App\Enums\OrgRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganizationService
{
    public function __construct(
        private readonly EntitlementService $entitlement,
    ) {}

    public function create(User $user, array $data): Organization
    {
        if (! $user->isOrganizational()) {
            throw ValidationException::withMessages([
                'organization' => __('org.only_organizational'),
            ]);
        }

        $current = $user->ownedOrganizations()->count();
        $this->entitlement->assertWithinLimit($user, 'organizations', $current);

        return DB::transaction(function () use ($user, $data) {
            $org = Organization::query()->create([
                'name' => $data['name'],
                'type' => OrganizationType::from($data['type'] ?? OrganizationType::Other->value),
                'description' => $data['description'] ?? null,
                'city' => $data['city'] ?? null,
                'owner_id' => $user->id,
                'status' => 'active',
            ]);

            $org->members()->attach($user->id, [
                'role' => OrgRole::Owner->value,
                'joined_at' => now(),
            ]);

            if ($user->current_organization_id === null) {
                $user->forceFill(['current_organization_id' => $org->id])->save();
            }

            session(['current_organization_id' => $org->id]);

            return $org;
        });
    }

    public function update(Organization $org, array $data): Organization
    {
        $org->fill([
            'name' => $data['name'] ?? $org->name,
            'type' => isset($data['type']) ? OrganizationType::from($data['type']) : $org->type,
            'description' => $data['description'] ?? $org->description,
            'city' => $data['city'] ?? $org->city,
        ]);

        if ($org->isDirty('name')) {
            $org->slug = Organization::uniqueSlug($org->name);
        }

        $org->save();

        return $org;
    }

    public function switchContext(User $user, Organization $org): void
    {
        if (! $org->hasMember($user)) {
            throw ValidationException::withMessages([
                'organization' => __('org.not_a_member'),
            ]);
        }

        $user->forceFill(['current_organization_id' => $org->id])->save();
        session(['current_organization_id' => $org->id]);
    }

    public function invite(Organization $org, User $actor, string $phone, OrgRole $role): OrganizationInvitation
    {
        if ($role === OrgRole::Owner) {
            throw ValidationException::withMessages([
                'role' => __('org.cannot_invite_owner'),
            ]);
        }

        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'phone' => __('auth.invalid_phone'),
            ]);
        }

        $existingUser = User::query()->where('phone', $normalized)->first();

        if ($existingUser && $org->hasMember($existingUser)) {
            throw ValidationException::withMessages([
                'phone' => __('org.already_member'),
            ]);
        }

        $pending = OrganizationInvitation::query()
            ->where('organization_id', $org->id)
            ->where('phone', $normalized)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages([
                'phone' => __('org.invite_already_pending'),
            ]);
        }

        $plainToken = Str::random(40);

        return OrganizationInvitation::query()->create([
            'organization_id' => $org->id,
            'phone' => $normalized,
            'role' => $role,
            'token_hash' => Hash::make($plainToken),
            'invited_by' => $actor->id,
            'expires_at' => now()->addDays(7),
            'status' => 'pending',
        ]);
    }

    public function acceptInvitation(OrganizationInvitation $invitation, User $user): void
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'invitation' => __('org.invite_invalid'),
            ]);
        }

        if ($user->phone !== $invitation->phone) {
            throw ValidationException::withMessages([
                'invitation' => __('org.invite_phone_mismatch'),
            ]);
        }

        DB::transaction(function () use ($invitation, $user) {
            $org = $invitation->organization;

            if (! $org->hasMember($user)) {
                $org->members()->attach($user->id, [
                    'role' => $invitation->role->value,
                    'joined_at' => now(),
                ]);
            }

            $invitation->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            if ($user->current_organization_id === null) {
                $user->forceFill(['current_organization_id' => $org->id])->save();
                session(['current_organization_id' => $org->id]);
            }
        });
    }

    public function changeMemberRole(Organization $org, User $target, OrgRole $role, User $actor): void
    {
        if ($target->id === $org->owner_id) {
            throw ValidationException::withMessages([
                'role' => __('org.cannot_change_owner_role'),
            ]);
        }

        if ($role === OrgRole::Owner) {
            throw ValidationException::withMessages([
                'role' => __('org.use_transfer_for_owner'),
            ]);
        }

        if (! $org->hasMember($target)) {
            throw ValidationException::withMessages([
                'member' => __('org.not_a_member'),
            ]);
        }

        $org->members()->updateExistingPivot($target->id, [
            'role' => $role->value,
        ]);
    }

    public function removeMember(Organization $org, User $target, User $actor): void
    {
        if ($target->id === $org->owner_id) {
            throw ValidationException::withMessages([
                'member' => __('org.cannot_remove_owner'),
            ]);
        }

        $org->members()->detach($target->id);

        if ($target->current_organization_id === $org->id) {
            $target->forceFill(['current_organization_id' => null])->save();
        }
    }

    public function leave(Organization $org, User $user): void
    {
        if ($user->id === $org->owner_id) {
            throw ValidationException::withMessages([
                'organization' => __('org.owner_must_transfer'),
            ]);
        }

        $this->removeMember($org, $user, $user);
    }

    public function transferOwnership(Organization $org, User $newOwner, User $actor): void
    {
        if ($actor->id !== $org->owner_id) {
            throw ValidationException::withMessages([
                'organization' => __('org.only_owner_can_transfer'),
            ]);
        }

        if (! $org->hasMember($newOwner)) {
            throw ValidationException::withMessages([
                'member' => __('org.transfer_must_be_member'),
            ]);
        }

        DB::transaction(function () use ($org, $newOwner, $actor) {
            $org->members()->updateExistingPivot($actor->id, [
                'role' => OrgRole::Admin->value,
            ]);

            $org->members()->updateExistingPivot($newOwner->id, [
                'role' => OrgRole::Owner->value,
            ]);

            $org->forceFill(['owner_id' => $newOwner->id])->save();
        });
    }
}
