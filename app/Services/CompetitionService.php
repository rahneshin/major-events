<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CompetitionGender;
use App\Enums\CompetitionRole;
use App\Enums\CompetitionStatus;
use App\Enums\EventRole;
use App\Enums\EventStatus;
use App\Enums\StageStatus;
use App\Models\Competition;
use App\Models\CompetitionTemplate;
use App\Models\Event;
use App\Models\User;
use App\Support\LogsActivity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Step 12A — ورزش‌ها (Competition) داخل یک رویداد.
 *
 * قواعد:
 *  - ماژول فقط برای رویدادی کار می‌کند که «سازنده‌اش» ویژگی `competitions` را دارد (حساب سازمانی)،
 *    تا ثبت‌کننده‌ی نتیجه با حساب عادی هم بتواند در رویداد سازمانی کار کند.
 *  - رویداد بایگانی/لغوشده فقط‌خواندنی است.
 *  - مسئول/ثبت‌کننده باید عضو غیر-بیننده‌ی همین رویداد باشد.
 */
class CompetitionService
{
    use LogsActivity;

    public const FEATURE = 'competitions';

    public const MAX_NAME_LENGTH = 120;

    public function __construct(private EntitlementService $entitlement) {}

    public function hasFeature(Event $event): bool
    {
        $creator = $event->creator;

        return $creator !== null && $this->entitlement->can($creator, self::FEATURE);
    }

    /**
     * @return Collection<int, CompetitionTemplate>
     */
    public function templates(): Collection
    {
        return CompetitionTemplate::query()->orderBy('id')->get();
    }

    /**
     * @param  array<string, mixed>  $data  template_code, name, gender?, capacity?, requires_confirmation?
     *
     * @throws InvalidArgumentException
     */
    public function create(Event $event, User $actor, array $data): Competition
    {
        $this->assertEventWritable($event);

        $template = CompetitionTemplate::query()->where('code', (string) ($data['template_code'] ?? ''))->first();
        if ($template === null) {
            throw new InvalidArgumentException(__('competition.errors.template_unknown'));
        }

        $name = $this->validName($data['name'] ?? '');

        $competition = DB::transaction(function () use ($event, $template, $name, $data): Competition {
            $competition = Competition::query()->create([
                'event_id' => $event->id,
                'name' => $name,
                'sport_code' => $template->code,
                'gender' => $this->gender($data['gender'] ?? null)->value,
                'participant_type' => $template->participant_type,
                'result_type' => $template->result_type,
                'scoring_config' => $template->scoring_config,
                'status' => CompetitionStatus::Registration->value,
                'requires_confirmation' => (bool) ($data['requires_confirmation'] ?? true),
                'capacity' => $this->capacity($data['capacity'] ?? null),
            ]);

            foreach (array_values((array) $template->stages) as $i => $stage) {
                $competition->stages()->create([
                    'position' => $i + 1,
                    'name' => $stage['name'],
                    'format' => $stage['format'],
                    'config' => $stage['config'] ?? [],
                    'is_final' => (bool) ($stage['is_final'] ?? false),
                ]);
            }

            return $competition;
        });

        self::logActivity('competition.created', $competition, $event->id, [
            'name' => $name,
            'template' => $template->code,
        ]);

        return $competition->refresh();
    }

    /**
     * @param  array<string, mixed>  $data  name, gender, capacity, requires_confirmation
     *
     * @throws InvalidArgumentException
     */
    public function update(Competition $competition, User $actor, array $data): Competition
    {
        $this->assertWritable($competition);

        $payload = [];

        if (array_key_exists('name', $data)) {
            $payload['name'] = $this->validName($data['name']);
        }
        if (array_key_exists('gender', $data)) {
            $payload['gender'] = $this->gender($data['gender'])->value;
        }
        if (array_key_exists('capacity', $data)) {
            $capacity = $this->capacity($data['capacity']);
            $active = $competition->competitors()->where('status', '!=', 'withdrawn')->count();
            if ($capacity !== null && $capacity < $active) {
                throw new InvalidArgumentException(__('competition.errors.capacity_below_registered'));
            }
            $payload['capacity'] = $capacity;
        }
        if (array_key_exists('requires_confirmation', $data)) {
            $payload['requires_confirmation'] = (bool) $data['requires_confirmation'];
        }

        if ($payload !== []) {
            $competition->update($payload);

            self::logActivity('competition.updated', $competition, $competition->event_id, [
                'changes' => array_keys($payload),
            ]);
        }

        return $competition->refresh();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function delete(Competition $competition, User $actor): void
    {
        $this->assertEventWritable($competition->event);

        if ($competition->hasConfirmedResults()) {
            throw new InvalidArgumentException(__('competition.errors.has_results'));
        }

        self::logActivity('competition.deleted', $competition, $competition->event_id, ['name' => $competition->name]);

        $competition->delete();
    }

    // ------------------------------------------------------------------ roles

    /**
     * @throws InvalidArgumentException
     */
    public function assignRole(Competition $competition, User $actor, User $user, CompetitionRole $role): void
    {
        $this->assertWritable($competition);

        $eventRole = $competition->event->roleOf($user);
        if ($eventRole === null || $eventRole === EventRole::Viewer) {
            throw new InvalidArgumentException(__('competition.errors.role_user_invalid'));
        }

        $competition->members()->syncWithoutDetaching([$user->id => ['role' => $role->value]]);
        $competition->members()->updateExistingPivot($user->id, ['role' => $role->value]);

        self::logActivity('competition.role_assigned', $competition, $competition->event_id, [
            'user_id' => $user->id,
            'role' => $role->value,
        ]);
    }

    public function removeRole(Competition $competition, User $actor, User $user): void
    {
        $this->assertWritable($competition);

        if ($competition->members()->detach($user->id) > 0) {
            self::logActivity('competition.role_removed', $competition, $competition->event_id, ['user_id' => $user->id]);
        }
    }

    // ----------------------------------------------------------------- status

    /**
     * drawn → ready: جدول ساخته شده و مدیر صریحاً «آماده‌ی برگزاری» می‌زند.
     *
     * @throws InvalidArgumentException
     */
    public function markReady(Competition $competition, User $actor): Competition
    {
        $this->assertWritable($competition);

        if ($competition->status !== CompetitionStatus::Drawn) {
            throw new InvalidArgumentException(__('competition.errors.not_drawn'));
        }

        $first = $competition->firstStage();
        if ($first === null || ! $first->matches()->exists()) {
            throw new InvalidArgumentException(__('competition.errors.no_fixtures'));
        }

        $competition->update(['status' => CompetitionStatus::Ready->value]);

        self::logActivity('competition.status_changed', $competition, $competition->event_id, [
            'to' => CompetitionStatus::Ready->value,
        ]);

        return $competition->refresh();
    }

    /**
     * بازگشایی ورزش پایان‌یافته (مدیر): رتبه‌های نهایی پاک و ثبت/ویرایش نتیجه دوباره ممکن می‌شود.
     *
     * @throws InvalidArgumentException
     */
    public function reopen(Competition $competition, User $actor): Competition
    {
        $this->assertWritable($competition);

        if ($competition->status !== CompetitionStatus::Finished) {
            throw new InvalidArgumentException(__('competition.errors.not_finished'));
        }

        DB::transaction(function () use ($competition): void {
            // فقط مرحله‌ی نهایی دوباره باز می‌شود؛ مرحله‌ی گروهیِ پایان‌یافته پایان‌یافته می‌ماند
            $final = $competition->stages->firstWhere('is_final', true) ?? $competition->stages->last();

            foreach ($competition->stages as $stage) {
                $stage->entries()->update(['final_rank' => null]);
            }

            $final?->update(['status' => StageStatus::Running->value]);
            $competition->competitors()->update(['final_rank' => null]);
            $competition->update(['status' => CompetitionStatus::Running->value]);
        });

        self::logActivity('competition.reopened', $competition, $competition->event_id, []);

        return $competition->refresh();
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @throws InvalidArgumentException
     */
    public function assertWritable(Competition $competition): void
    {
        $this->assertEventWritable($competition->event);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertEventWritable(Event $event): void
    {
        if (! $this->hasFeature($event)) {
            throw new InvalidArgumentException(__('competition.errors.feature_unavailable'));
        }

        if (in_array($event->status, [EventStatus::Archived, EventStatus::Cancelled], true)) {
            throw new InvalidArgumentException(__('competition.errors.event_closed'));
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

    private function gender(mixed $value): CompetitionGender
    {
        if ($value === null || $value === '') {
            return CompetitionGender::Open;
        }

        return CompetitionGender::tryFrom((string) $value)
            ?? throw new InvalidArgumentException(__('competition.errors.invalid_value'));
    }

    private function capacity(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || (int) $value < 2 || (int) $value > (int) config('competitions.max_competitors', 256)) {
            throw new InvalidArgumentException(__('competition.errors.capacity_invalid', [
                'max' => (int) config('competitions.max_competitors', 256),
            ]));
        }

        return (int) $value;
    }
}
