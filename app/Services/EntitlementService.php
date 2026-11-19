<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Exceptions\LimitExceededException;
use App\Models\User;

class EntitlementService
{
    public function planFor(User $user): array
    {
        $type = $user->account_type?->value ?? AccountType::Individual->value;

        return config("plans.{$type}", config('plans.individual'));
    }

    public function can(User $user, string $feature): bool
    {
        if ($user->account_type === null) {
            return false;
        }

        $features = $this->planFor($user)['features'] ?? [];

        if (in_array('*', $features, true)) {
            return true;
        }

        return in_array($feature, $features, true);
    }

    /**
     * @return int|null null means unlimited
     */
    public function limit(User $user, string $key): ?int
    {
        $limits = $this->planFor($user)['limits'] ?? [];

        if (! array_key_exists($key, $limits)) {
            return 0;
        }

        $value = $limits[$key];

        return $value === null ? null : (int) $value;
    }

    /**
     * Remaining slots for a limit key given current usage.
     * null means unlimited.
     */
    public function remaining(User $user, string $key, int $currentUsage): ?int
    {
        $limit = $this->limit($user, $key);

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $currentUsage);
    }

    public function isWithinLimit(User $user, string $key, int $currentUsage): bool
    {
        $limit = $this->limit($user, $key);

        if ($limit === null) {
            return true;
        }

        return $currentUsage < $limit;
    }

    /**
     * @throws LimitExceededException
     */
    public function assertWithinLimit(User $user, string $key, int $currentUsage): void
    {
        if ($this->isWithinLimit($user, $key, $currentUsage)) {
            return;
        }

        $limit = $this->limit($user, $key);

        throw new LimitExceededException(
            $key,
            $limit,
            __('account.limit_exceeded_detail', [
                'limit' => __('account.limits.'.$key),
                'max' => $limit,
            ]),
        );
    }

    public function isOrganizational(User $user): bool
    {
        return $user->account_type === AccountType::Organizational;
    }

    public function isIndividual(User $user): bool
    {
        return $user->account_type === AccountType::Individual;
    }
}
