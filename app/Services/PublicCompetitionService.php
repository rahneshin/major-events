<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CompetitionStatus;
use App\Enums\MatchStatus;
use App\Enums\ParticipantType;
use App\Enums\ResultStatus;
use App\Enums\StageFormat;
use App\Enums\StageStatus;
use App\Models\Competition;
use App\Models\Competitor;
use App\Models\Heat;
use App\Models\SportMatch;
use App\Support\HeatValue;
use App\Support\Jalali;
use App\Support\NameMasker;

/**
 * Step 12E — داده‌ی صفحه‌ی عمومی یک ورزش.
 *
 * خروجی فقط آرایه‌ی ساده‌ی «لیست سفید» است (نه مدل): فقط نام و وابستگی؛ هرگز اطلاعات تماس، ترکیب تیم،
 * مدارک، سید، شناسه‌ی کاربر یا نتیجه‌ی تأییدنشده. نام انفرادی‌ها در صورت فعال‌بودن گزینه مخفف می‌شود.
 */
class PublicCompetitionService
{
    /** سقف ردیف بازی در صفحه‌ی عمومی (کارایی). */
    public const MAX_MATCHES = 2000;

    public function __construct(
        private StandingsService $standings,
        private HeatService $heats,
        private PublicLinkService $links,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Competition $competition): array
    {
        $abbreviate = $this->links->abbreviatesNames($competition)
            && $competition->participant_type === ParticipantType::Individual;

        $people = Competitor::query()
            ->where('competition_id', $competition->id)
            ->get(['id', 'name', 'affiliation'])
            ->mapWithKeys(fn (Competitor $c): array => [$c->id => [
                'name' => $abbreviate ? NameMasker::abbreviate($c->name) : $c->name,
                'affiliation' => $c->affiliation,
            ]]);

        $ranked = $competition->result_type === 'ranked';

        return [
            'name' => $competition->name,
            'event' => $competition->event->title,
            'gender' => $competition->gender->label(),
            'participant' => $competition->participant_type->label(),
            'status' => $competition->status->label(),
            'finished' => $competition->status === CompetitionStatus::Finished,
            'ranked' => $ranked,
            'standings' => $ranked ? [] : $this->standingTables($competition, $people),
            'rounds' => $ranked ? [] : $this->rounds($competition, $people),
            'heats' => $ranked ? $this->heatTables($competition, $people) : [],
            'final_ranking' => $this->finalRanking($competition, $people),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{name: string, affiliation: string|null}>  $people
     * @return list<array{stage: string, rows: list<array<string, mixed>>}>
     */
    private function standingTables(Competition $competition, $people): array
    {
        $tables = [];

        foreach ($competition->stages as $stage) {
            if ($stage->format !== StageFormat::RoundRobin || $stage->status === StageStatus::Pending) {
                continue;
            }

            $rows = [];
            foreach ($this->standings->forStage($stage) as $row) {
                $person = $people->get($row['competitor_id']);

                $rows[] = [
                    'rank' => $row['rank'],
                    'tied' => (bool) ($row['tied'] ?? false),
                    'name' => $person['name'] ?? '—',
                    'affiliation' => $person['affiliation'] ?? null,
                    'played' => $row['played'] ?? 0,
                    'won' => $row['won'] ?? 0,
                    'drawn' => $row['drawn'] ?? 0,
                    'lost' => $row['lost'] ?? 0,
                    'for' => $row['for'] ?? 0,
                    'against' => $row['against'] ?? 0,
                    'diff' => $row['diff'] ?? 0,
                    'points' => $row['points'] ?? 0,
                ];
            }

            $tables[] = ['stage' => $stage->name, 'rows' => $rows];
        }

        return $tables;
    }

    /**
     * بازی‌ها به تفکیک مرحله و دور. نتیجه فقط وقتی نشان داده می‌شود که «تأییدشده» باشد.
     *
     * @param  \Illuminate\Support\Collection<int, array{name: string, affiliation: string|null}>  $people
     * @return list<array{stage: string, rounds: list<array{round: int, matches: list<array<string, mixed>>}>}>
     */
    private function rounds(Competition $competition, $people): array
    {
        $out = [];
        $remaining = self::MAX_MATCHES;

        foreach ($competition->stages as $stage) {
            if ($remaining <= 0) {
                break;
            }

            $matches = SportMatch::query()
                ->where('stage_id', $stage->id)
                ->whereNull('parent_match_id')
                ->where('status', '!=', MatchStatus::Cancelled->value)
                ->orderBy('round')->orderBy('number')->orderBy('id')
                ->limit($remaining)
                ->get();

            if ($matches->isEmpty()) {
                continue;
            }

            $remaining -= $matches->count();

            $rounds = [];
            foreach ($matches->groupBy('round') as $round => $group) {
                $rounds[] = [
                    'round' => (int) $round,
                    'matches' => $group->map(fn (SportMatch $m): array => $this->matchRow($m, $people))->values()->all(),
                ];
            }

            $out[] = ['stage' => $stage->name, 'rounds' => $rounds];
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{name: string, affiliation: string|null}>  $people
     * @return array<string, mixed>
     */
    private function matchRow(SportMatch $match, $people): array
    {
        $confirmed = $match->result_status === ResultStatus::Confirmed;
        $played = $confirmed && $match->status->isPlayed();

        return [
            'a' => $match->a_id !== null ? ($people->get($match->a_id)['name'] ?? '—') : '—',
            'b' => $match->b_id !== null ? ($people->get($match->b_id)['name'] ?? '—') : '—',
            'score_a' => $played && $match->status === MatchStatus::Finished ? $match->score_a : null,
            'score_b' => $played && $match->status === MatchStatus::Finished ? $match->score_b : null,
            'walkover' => $played && $match->status === MatchStatus::Walkover,
            'winner' => $played && $match->winner_id !== null ? ($people->get($match->winner_id)['name'] ?? null) : null,
            'status' => $match->status === MatchStatus::Postponed ? $match->status->label() : null,
            'when' => $match->scheduled_at !== null ? Jalali::format($match->scheduled_at, 'Y/m/d H:i') : null,
            'court' => $match->court,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{name: string, affiliation: string|null}>  $people
     * @return list<array{stage: string, heats: list<array{name: string, confirmed: bool, rows: list<array<string, mixed>>}>}>
     */
    private function heatTables(Competition $competition, $people): array
    {
        $kind = $this->heats->valueKind($competition);
        $out = [];

        foreach ($this->heats->board($competition) as $item) {
            $heats = $item['heats']->map(function (Heat $heat) use ($people, $kind): array {
                $confirmed = $heat->isConfirmed();

                return [
                    'name' => $heat->name,
                    'confirmed' => $confirmed,
                    'when' => $heat->scheduled_at !== null ? Jalali::format($heat->scheduled_at, 'Y/m/d H:i') : null,
                    'rows' => $heat->results->map(fn ($r): array => [
                        'lane' => $r->lane,
                        // مقدار/رتبه/وضعیت فقط برای سری تأییدشده
                        'rank' => $confirmed ? $r->rank : null,
                        'name' => $people->get($r->competitor_id)['name'] ?? '—',
                        'affiliation' => $people->get($r->competitor_id)['affiliation'] ?? null,
                        'value' => $confirmed && $r->status->isRanked() ? HeatValue::format($r->value, $kind) : null,
                        'status' => $confirmed && ! $r->status->isRanked() ? $r->status->label() : null,
                        'advanced' => $confirmed && $r->advanced,
                    ])->values()->all(),
                ];
            })->values()->all();

            $out[] = ['stage' => $item['stage']->name, 'heats' => $heats];
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{name: string, affiliation: string|null}>  $people
     * @return list<array{rank: int, name: string, affiliation: string|null}>
     */
    private function finalRanking(Competition $competition, $people): array
    {
        if ($competition->status !== CompetitionStatus::Finished) {
            return [];
        }

        return Competitor::query()
            ->where('competition_id', $competition->id)
            ->whereNotNull('final_rank')
            ->where('status', '!=', 'withdrawn')
            ->orderBy('final_rank')->orderBy('id')
            ->get(['id', 'final_rank'])
            ->map(fn (Competitor $c): array => [
                'rank' => (int) $c->final_rank,
                'name' => $people->get($c->id)['name'] ?? '—',
                'affiliation' => $people->get($c->id)['affiliation'] ?? null,
            ])->all();
    }
}
