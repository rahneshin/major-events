<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

/**
 * Step 13 — per-user notification preferences and the SMS cost guard.
 *
 * Defaults (no row): in-app ON, SMS ON for categories that may use SMS at all.
 * Even with SMS ON, a notification only goes out by SMS when the notification
 * itself is "important" (urgent assignment, due soon, urgent announcement)
 * and the per-user daily cap is not exhausted.
 */
class NotificationPreferenceService
{
    /** @return list<string> */
    public function categories(): array
    {
        return array_keys((array) config('notifications.categories', []));
    }

    public function exists(string $category): bool
    {
        return in_array($category, $this->categories(), true);
    }

    public function smsSupported(string $category): bool
    {
        return (bool) config("notifications.categories.{$category}.sms", false);
    }

    /**
     * @return array<string, array{in_app: bool, sms: bool, sms_supported: bool}>
     */
    public function forUser(User $user): array
    {
        $rows = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('category');

        $result = [];
        foreach ($this->categories() as $category) {
            $supported = $this->smsSupported($category);
            $row = $rows->get($category);

            $result[$category] = [
                'in_app' => $row?->in_app ?? true,
                'sms' => $supported && ($row?->sms ?? true),
                'sms_supported' => $supported,
            ];
        }

        return $result;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function set(User $user, string $category, bool $inApp, bool $sms): NotificationPreference
    {
        if (! $this->exists($category)) {
            throw new InvalidArgumentException(__('notification.unknown_category'));
        }

        return NotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->id, 'category' => $category],
            ['in_app' => $inApp, 'sms' => $this->smsSupported($category) && $sms],
        );
    }

    /**
     * Channels a notification of `$category` should use for `$user`.
     *
     * @return list<string>
     */
    public function channels(User $user, string $category, bool $smsEligible): array
    {
        if (! $this->exists($category)) {
            return ['database'];
        }

        $prefs = $this->forUser($user)[$category];
        $channels = [];

        if ($prefs['in_app']) {
            $channels[] = 'database';
        }

        if ($smsEligible && $prefs['sms'] && filled($user->phone)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    /**
     * Takes one SMS from the user's daily allowance. false = cap reached.
     */
    public function consumeSmsQuota(User $user): bool
    {
        $max = (int) config('notifications.sms.per_user_per_day', 5);
        $key = 'sms-quota:'.$user->id.':'.now('Asia/Tehran')->toDateString();

        if (RateLimiter::tooManyAttempts($key, $max)) {
            return false;
        }

        RateLimiter::hit($key, 86400);

        return true;
    }
}
