<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Competition;
use App\Models\User;
use App\Support\LogsActivity;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Step 12E — لینک عمومی فقط‌خواندنی هر ورزش (`competitions.public_token`).
 *
 *  - فعال‌سازی توکن تصادفی ۴۰ نویسه می‌سازد؛ «تغییر توکن» لینک قبلی را بی‌اعتبار می‌کند؛ «غیرفعال» توکن را پاک می‌کند.
 *  - گزینه‌ی «نمایش مخفف نام» (`settings.public.abbreviate_names`) برای شرکت‌کننده‌های انفرادیِ زیر ۱۸ سال.
 *  - مجوز (manage) در لایه‌ی Livewire سنجیده می‌شود؛ جست‌وجوی توکن فقط از `findByToken`.
 */
class PublicLinkService
{
    use LogsActivity;

    public const TOKEN_LENGTH = 40;

    public const TOKEN_PATTERN = '/^[A-Za-z0-9]{32,64}$/';

    public function __construct(private CompetitionService $competitions) {}

    /**
     * فعال‌سازی (اگر از قبل فعال باشد، همان توکن می‌ماند).
     *
     * @throws InvalidArgumentException
     */
    public function enable(Competition $competition, User $actor): Competition
    {
        $this->competitions->assertWritable($competition);

        if ($competition->public_token !== null) {
            return $competition;
        }

        $competition->update(['public_token' => $this->newToken()]);

        self::logActivity('competition.public_link_enabled', $competition, $competition->event_id, []);

        return $competition->refresh();
    }

    /**
     * توکن جدید؛ لینک قبلی فوراً از کار می‌افتد.
     *
     * @throws InvalidArgumentException
     */
    public function rotate(Competition $competition, User $actor): Competition
    {
        $this->competitions->assertWritable($competition);

        $competition->update(['public_token' => $this->newToken()]);

        self::logActivity('competition.public_link_rotated', $competition, $competition->event_id, []);

        return $competition->refresh();
    }

    /** غیرفعال‌سازی همیشه ممکن است (حتی برای رویداد بایگانی‌شده)؛ اقدام حریم خصوصی است. */
    public function disable(Competition $competition, User $actor): Competition
    {
        if ($competition->public_token !== null) {
            $competition->update(['public_token' => null]);

            self::logActivity('competition.public_link_disabled', $competition, $competition->event_id, []);
        }

        return $competition->refresh();
    }

    public function setAbbreviateNames(Competition $competition, User $actor, bool $abbreviate): Competition
    {
        $settings = $competition->settings ?? [];
        data_set($settings, 'public.abbreviate_names', $abbreviate);

        $competition->update(['settings' => $settings]);

        self::logActivity('competition.public_names_changed', $competition, $competition->event_id, [
            'abbreviate' => $abbreviate,
        ]);

        return $competition->refresh();
    }

    public function abbreviatesNames(Competition $competition): bool
    {
        return (bool) data_get($competition->settings, 'public.abbreviate_names', false);
    }

    public function url(Competition $competition): ?string
    {
        return $competition->public_token === null
            ? null
            : route('competitions.public', ['token' => $competition->public_token]);
    }

    /**
     * ورزشی که این توکن را دارد؛ null اگر توکن نامعتبر/غیرفعال، ورزش حذف‌شده یا طرح بدون ماژول باشد.
     */
    public function findByToken(string $token): ?Competition
    {
        if (preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            return null;
        }

        $competition = Competition::query()->where('public_token', $token)->with('event')->first();

        if ($competition === null || $competition->event === null || ! $this->competitions->hasFeature($competition->event)) {
            return null;
        }

        return $competition;
    }

    private function newToken(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $token = Str::random(self::TOKEN_LENGTH);

            if (! Competition::query()->withTrashed()->where('public_token', $token)->exists()) {
                return $token;
            }
        }

        throw new InvalidArgumentException(__('publicpage.errors.token_failed'));
    }
}
