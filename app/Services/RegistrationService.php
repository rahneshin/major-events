<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CompetitionStatus;
use App\Enums\CompetitorStatus;
use App\Models\Competition;
use App\Models\Competitor;
use App\Models\User;
use App\Support\LogsActivity;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Step 12A — ثبت‌نام تیم/نفر در یک ورزش.
 *
 * قواعد: نام داخل هر ورزش یکتا (بدون حساسیت به حروف)، ظرفیت (بدون احتساب انصرافی‌ها)،
 * ثبت‌نام فقط تا قبل از قرعه؛ انصراف بعد از قرعه در 12C (سیاست انصراف) پیاده می‌شود.
 */
class RegistrationService
{
    use LogsActivity;

    public const MAX_NAME_LENGTH = 120;

    public function __construct(private CompetitionService $competitions) {}

    /**
     * @param  array<string, mixed>  $data  name, affiliation?, seed?, members? (list<string>), documents? (list<string>)
     *
     * @throws InvalidArgumentException
     */
    public function register(Competition $competition, User $actor, array $data): Competitor
    {
        $this->assertOpen($competition);

        $name = $this->validName($data['name'] ?? '');
        $this->assertUnique($competition, $name);
        $this->assertCapacity($competition);

        $competitor = DB::transaction(function () use ($competition, $name, $data): Competitor {
            $competitor = Competitor::query()->create([
                'competition_id' => $competition->id,
                'name' => $name,
                'affiliation' => $this->nullableString($data['affiliation'] ?? null),
                'seed' => $this->seed($data['seed'] ?? null),
                'status' => $competition->requires_confirmation
                    ? CompetitorStatus::Registered->value
                    : CompetitorStatus::Confirmed->value,
                'documents' => $this->stringList($data['documents'] ?? []),
            ]);

            foreach ($this->stringList($data['members'] ?? []) as $member) {
                $competitor->members()->create(['full_name' => $member]);
            }

            return $competitor;
        });

        self::logActivity('competitor.registered', $competitor, $competition->event_id, ['name' => $name]);

        return $competitor->refresh();
    }

    /**
     * ثبت‌نام گروهی: هر خط «نام، وابستگی». خطای هر خط جدا گزارش می‌شود و بقیه ثبت می‌شوند.
     *
     * @return array{created: int, errors: list<array{line: int, message: string}>}
     */
    public function bulkRegister(Competition $competition, User $actor, string $text): array
    {
        $this->assertOpen($competition);

        $created = 0;
        $errors = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $i => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$name, $affiliation] = array_pad(array_map('trim', preg_split('/[,،;\t]/u', $line, 2) ?: []), 2, null);

            try {
                $this->register($competition, $actor, ['name' => $name, 'affiliation' => $affiliation]);
                $created++;
            } catch (InvalidArgumentException $e) {
                $errors[] = ['line' => $i + 1, 'message' => $e->getMessage()];
            }
        }

        return ['created' => $created, 'errors' => $errors];
    }

    public function confirm(Competitor $competitor, User $actor): Competitor
    {
        $competition = $competitor->competition;
        $this->assertOpen($competition);

        if ($competitor->status === CompetitorStatus::Withdrawn) {
            throw new InvalidArgumentException(__('competition.errors.competitor_withdrawn'));
        }

        $competitor->update(['status' => CompetitorStatus::Confirmed->value]);

        self::logActivity('competitor.confirmed', $competitor, $competition->event_id, ['name' => $competitor->name]);

        return $competitor->refresh();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function withdraw(Competitor $competitor, User $actor): Competitor
    {
        $competition = $competitor->competition;
        $this->competitions->assertWritable($competition);

        if ($competition->status !== CompetitionStatus::Registration) {
            // بعد از قرعه، انصراف با سیاست «keep_played/void_all/walkover» در Step 12C انجام می‌شود
            throw new InvalidArgumentException(__('competition.errors.withdraw_after_draw'));
        }

        $competitor->update(['status' => CompetitorStatus::Withdrawn->value]);

        self::logActivity('competitor.withdrawn', $competitor, $competition->event_id, ['name' => $competitor->name]);

        return $competitor->refresh();
    }

    public function setSeed(Competitor $competitor, User $actor, mixed $seed): Competitor
    {
        $this->assertOpen($competitor->competition);

        $competitor->update(['seed' => $this->seed($seed)]);

        return $competitor->refresh();
    }

    // ---------------------------------------------------------------- helpers

    private function assertOpen(Competition $competition): void
    {
        $this->competitions->assertWritable($competition);

        if ($competition->status !== CompetitionStatus::Registration) {
            throw new InvalidArgumentException(__('competition.errors.registration_closed'));
        }

        $now = now();
        if ($competition->registration_opens_at !== null && $now->lt($competition->registration_opens_at)) {
            throw new InvalidArgumentException(__('competition.errors.registration_not_open'));
        }
        if ($competition->registration_closes_at !== null && $now->gt($competition->registration_closes_at)) {
            throw new InvalidArgumentException(__('competition.errors.registration_closed'));
        }
    }

    private function assertUnique(Competition $competition, string $name): void
    {
        $exists = $competition->competitors()
            ->where('status', '!=', CompetitorStatus::Withdrawn->value)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException(__('competition.errors.competitor_duplicate', ['name' => $name]));
        }
    }

    private function assertCapacity(Competition $competition): void
    {
        $limit = $competition->capacity ?? (int) config('competitions.max_competitors', 256);

        $active = $competition->competitors()->where('status', '!=', CompetitorStatus::Withdrawn->value)->count();

        if ($active >= $limit) {
            throw new InvalidArgumentException(__('competition.errors.capacity_full'));
        }
    }

    private function validName(mixed $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            throw new InvalidArgumentException(__('competition.errors.name_required'));
        }
        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new InvalidArgumentException(__('competition.errors.name_too_long', ['max' => self::MAX_NAME_LENGTH]));
        }

        return $name;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, self::MAX_NAME_LENGTH);
    }

    private function seed(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || (int) $value < 1 || (int) $value > 9999) {
            throw new InvalidArgumentException(__('competition.errors.seed_invalid'));
        }

        return (int) $value;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        $out = [];
        foreach ((array) $value as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $out[] = mb_substr($item, 0, self::MAX_NAME_LENGTH);
            }
        }

        return array_slice($out, 0, 50);
    }
}
