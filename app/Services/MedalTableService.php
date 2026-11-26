<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\User;
use App\Support\LogsActivity;
use InvalidArgumentException;

/**
 * Step 12E — جدول مدال («کدام مدرسه/دانشکده بیشتر برد»).
 *
 * رتبه‌ی نهایی ۱/۲/۳ (`competitors.final_rank`) ورزش‌های «پایان‌یافته»ی یک رویداد بر اساس
 * `affiliation` جمع می‌شود. مساوی‌ها رتبه‌ی مشترک دارند و هر کدام مدال همان رتبه را می‌گیرند.
 * شرکت‌کننده‌ی بدون وابستگی در جدول نمی‌آید (فقط تعدادش گزارش می‌شود). منصرف‌شده‌ها حساب نمی‌شوند.
 * امتیاز کل اختیاری با وزن در `events.metadata.medals` (weights و sort = medals|points).
 */
class MedalTableService
{
    use LogsActivity;

    public const MAX_WEIGHT = 1000;

    public const MEDALS = [1 => 'gold', 2 => 'silver', 3 => 'bronze'];

    /**
     * @return array{
     *   rows: list<array{rank: int, affiliation: string, gold: int, silver: int, bronze: int, total: int, points: int|null, entries: list<array{competition: string, name: string, medal: string}>}>,
     *   unaffiliated: int,
     *   competitions: int,
     *   weights: array{gold: int, silver: int, bronze: int}|null,
     *   sort: string
     * }
     */
    public function forEvent(Event $event): array
    {
        $competitions = Competition::query()
            ->forEvent($event)
            ->where('status', CompetitionStatus::Finished->value)
            ->pluck('name', 'id');

        $medalists = $competitions->isEmpty() ? collect() : Competitor::query()
            ->whereIn('competition_id', $competitions->keys())
            ->whereIn('final_rank', array_keys(self::MEDALS))
            ->where('status', '!=', 'withdrawn')
            ->orderBy('id')
            ->get(['id', 'competition_id', 'name', 'affiliation', 'final_rank']);

        $weights = $this->weights($event);
        $sort = $weights !== null && $this->sortMode($event) === 'points' ? 'points' : 'medals';

        $groups = [];
        $unaffiliated = 0;

        foreach ($medalists as $competitor) {
            $label = trim((string) $competitor->affiliation);

            if ($label === '') {
                $unaffiliated++;

                continue;
            }

            $key = mb_strtolower($label);
            $groups[$key] ??= ['affiliation' => $label, 'gold' => 0, 'silver' => 0, 'bronze' => 0, 'entries' => []];

            $medal = self::MEDALS[(int) $competitor->final_rank];
            $groups[$key][$medal]++;
            $groups[$key]['entries'][] = [
                'competition' => (string) $competitions->get($competitor->competition_id),
                'name' => $competitor->name,
                'medal' => $medal,
            ];
        }

        $rows = array_values(array_map(function (array $g) use ($weights): array {
            $g['total'] = $g['gold'] + $g['silver'] + $g['bronze'];
            $g['points'] = $weights === null
                ? null
                : $g['gold'] * $weights['gold'] + $g['silver'] * $weights['silver'] + $g['bronze'] * $weights['bronze'];

            return $g;
        }, $groups));

        usort($rows, function (array $x, array $y) use ($sort): int {
            if ($sort === 'points') {
                $cmp = $y['points'] <=> $x['points'];
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            return [$y['gold'], $y['silver'], $y['bronze'], $x['affiliation']]
                <=> [$x['gold'], $x['silver'], $x['bronze'], $y['affiliation']];
        });

        $previous = null;
        $rank = 0;
        foreach ($rows as $i => $row) {
            $signature = $sort === 'points'
                ? [$row['points'], $row['gold'], $row['silver'], $row['bronze']]
                : [$row['gold'], $row['silver'], $row['bronze']];

            if ($previous === null || $signature !== $previous) {
                $rank = $i + 1;
                $previous = $signature;
            }

            $rows[$i]['rank'] = $rank;
        }

        return [
            'rows' => $rows,
            'unaffiliated' => $unaffiliated,
            'competitions' => $competitions->count(),
            'weights' => $weights,
            'sort' => $sort,
        ];
    }

    /**
     * @return array{gold: int, silver: int, bronze: int}|null
     */
    public function weights(Event $event): ?array
    {
        $weights = data_get($event->metadata, 'medals.weights');

        if (! is_array($weights)) {
            return null;
        }

        return [
            'gold' => (int) ($weights['gold'] ?? 0),
            'silver' => (int) ($weights['silver'] ?? 0),
            'bronze' => (int) ($weights['bronze'] ?? 0),
        ];
    }

    public function sortMode(Event $event): string
    {
        return data_get($event->metadata, 'medals.sort') === 'points' ? 'points' : 'medals';
    }

    /**
     * @param  array{gold: mixed, silver: mixed, bronze: mixed}|null  $weights  null = بدون امتیاز کل
     *
     * @throws InvalidArgumentException
     */
    public function configure(Event $event, User $actor, ?array $weights, string $sort): Event
    {
        if (! in_array($sort, ['medals', 'points'], true)) {
            throw new InvalidArgumentException(__('medals.errors.invalid'));
        }

        $clean = null;

        if ($weights !== null) {
            $clean = [];
            foreach (['gold', 'silver', 'bronze'] as $medal) {
                $value = filter_var($weights[$medal] ?? null, FILTER_VALIDATE_INT);

                if ($value === false || $value < 0 || $value > self::MAX_WEIGHT) {
                    throw new InvalidArgumentException(__('medals.errors.weight_invalid', ['max' => self::MAX_WEIGHT]));
                }

                $clean[$medal] = $value;
            }
        }

        if ($sort === 'points' && $clean === null) {
            throw new InvalidArgumentException(__('medals.errors.points_need_weights'));
        }

        $metadata = $event->metadata ?? [];

        if ($clean === null) {
            unset($metadata['medals']);
        } else {
            data_set($metadata, 'medals', ['weights' => $clean, 'sort' => $sort]);
        }

        $event->update(['metadata' => $metadata === [] ? null : $metadata]);

        self::logActivity('medals.configured', $event, $event->id, ['weights' => $clean, 'sort' => $sort]);

        return $event->refresh();
    }
}
