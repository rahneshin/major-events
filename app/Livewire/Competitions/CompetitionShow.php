<?php

declare(strict_types=1);

namespace App\Livewire\Competitions;

use App\Enums\CompetitionGender;
use App\Enums\CompetitionRole;
use App\Enums\CompetitorStatus;
use App\Enums\EventRole;
use App\Enums\MatchStatus;
use App\Enums\ResultStatus;
use App\Enums\StageStatus;
use App\Models\Competition;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\SportMatch;
use App\Models\Stage;
use App\Models\User;
use App\Services\CompetitionService;
use App\Services\Competitions\Engine\DrawEngine;
use App\Services\DrawService;
use App\Services\MatchService;
use App\Services\RegistrationService;
use App\Services\StandingsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * صفحه‌ی یک ورزش (Step 12A/12B): ثبت‌نام، قرعه و جدول، رده‌بندی، تنظیمات و نقش‌ها.
 *
 * دسترسی (CompetitionPolicy): view برای همه‌ی بیننده‌های رویداد؛ manage برای مدیر رویداد/مسئول ورزش.
 * هر اکشن جداگانه authorize می‌شود و هر شناسه فقط داخل همین ورزش پیدا می‌شود.
 * ثبت/تأیید نتیجه، زمان‌بندی دستی، بازی دستی، ایمپورت و بازگشایی (12C): ثبت = enterResult،
 * تأیید/حذف/برد اداری/لغو = confirmResult، زمان‌بندی/بازی دستی/بازگشایی = manage.
 */
#[Layout('layouts.app')]
class CompetitionShow extends Component
{
    public Event $event;

    public Competition $competition;

    #[Url(as: 'section')]
    public string $section = 'competitors';

    // ---- ثبت‌نام
    public string $rName = '';

    public string $rAffiliation = '';

    public string $rSeed = '';

    public string $rMembers = '';

    public string $bulkText = '';

    // ---- قرعه
    public string $dMethod = 'random';

    public string $dSeed = '';

    /** @var array{method: string, seed: string, order: list<int>, names: array<int, string>}|null */
    public ?array $preview = null;

    // ---- تنظیمات
    public string $sName = '';

    public string $sGender = 'open';

    public string $sCapacity = '';

    public bool $sConfirm = true;

    public string $roleUserId = '';

    public string $roleValue = 'scorer';

    // ---- برنامه‌ی بازی‌ها و نتیجه (12C)
    public string $fixturesView = 'rounds';

    public string $roundFilter = '';

    public ?int $editingMatchId = null;

    public string $mScoreA = '';

    public string $mScoreB = '';

    /** @var list<array{a: string, b: string}> */
    public array $mGames = [];

    public ?int $scheduleMatchId = null;

    public string $scDate = '';

    public string $scTime = '';

    public string $scCourt = '';

    public string $xRound = '1';

    public string $xA = '';

    public string $xB = '';

    public string $importText = '';

    public ?string $error = null;

    public ?string $notice = null;

    public function mount(Event $event, Competition $competition): void
    {
        abort_unless($competition->event_id === $event->id, 404);

        $this->authorize('view', $competition);

        $this->event = $event;
        $this->competition = $competition;
        $this->section = $this->normalizeSection($this->section);
        $this->fillSettings();
    }

    public function setSection(string $section): void
    {
        $this->section = $this->normalizeSection($section);
        $this->clearMessages();
    }

    // -------------------------------------------------------------- ثبت‌نام

    public function register(RegistrationService $service): void
    {
        $this->authorize('manage', $this->competition);
        $this->clearMessages();

        $this->validate([
            'rName' => ['required', 'string', 'max:'.RegistrationService::MAX_NAME_LENGTH],
            'rAffiliation' => ['nullable', 'string', 'max:'.RegistrationService::MAX_NAME_LENGTH],
            'rSeed' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'rMembers' => ['nullable', 'string', 'max:3000'],
        ], [], [
            'rName' => __('competition.fields.competitor_name'),
            'rAffiliation' => __('competition.fields.affiliation'),
            'rSeed' => __('competition.fields.seed'),
            'rMembers' => __('competition.fields.members'),
        ]);

        try {
            $service->register($this->competition, Auth::user(), [
                'name' => $this->rName,
                'affiliation' => $this->rAffiliation,
                'seed' => $this->rSeed,
                'members' => preg_split('/\R/u', $this->rMembers) ?: [],
            ]);
            $this->reset(['rName', 'rAffiliation', 'rSeed', 'rMembers']);
            $this->notice = __('competition.competitor_registered');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function bulkRegister(RegistrationService $service): void
    {
        $this->authorize('manage', $this->competition);
        $this->clearMessages();

        $this->validate(['bulkText' => ['required', 'string', 'max:20000']], [], ['bulkText' => __('competition.fields.bulk')]);

        try {
            $result = $service->bulkRegister($this->competition, Auth::user(), $this->bulkText);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->bulkText = '';
        $this->notice = __('competition.bulk_done', ['count' => fa_num($result['created'])]);

        if ($result['errors'] !== []) {
            $this->error = collect($result['errors'])
                ->take(5)
                ->map(fn (array $e): string => __('competition.bulk_line', ['line' => fa_num($e['line']), 'message' => $e['message']]))
                ->implode(' | ');
        }
    }

    public function confirmCompetitor(int $id, RegistrationService $service): void
    {
        $this->authorize('manage', $this->competition);
        $this->clearMessages();

        try {
            $service->confirm($this->findCompetitor($id), Auth::user());
            $this->notice = __('competition.competitor_confirmed');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function withdrawCompetitor(int $id, RegistrationService $service): void
    {
        $this->authorize('manage', $this->competition);
        $this->clearMessages();

        try {
            $service->withdraw($this->findCompetitor($id), Auth::user());
            $this->notice = __('competition.competitor_withdrawn');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    // ----------------------------------------------------------------- قرعه

    public function previewDraw(DrawService $service): void
    {
        $this->authorize('manage', $this->competition);
        $this->clearMessages();

        if (! in_array($this->dMethod, DrawEngine::METHODS, true)) {
            $this->error = __('competition.errors.invalid_value');

            return;
        }

        try {
            $this->preview = $service->preview($this->competition, $this->dMethod, $this->dSeed !== '' ? $this->dSeed : null);
            $this->dSeed = $this->preview['seed'];
        } catch (InvalidArgumentException $e) {
            $this->preview = null;
            $this->error = $e->getMessage();
        }
    }

    public function confirmDraw(DrawService $service): void
    {
        $this->authorize('manage', $this->competition);
        $this->clearMessages();

        if ($this->preview === null) {
            $this->error = __('competition.errors.preview_first');

            return;
        }

        try {
            // همان seed و روش پیش‌نمایش ← همان نتیجه
            $service->confirm($this->competition, Auth::user(), $this->preview['method'], $this->preview['seed']);
            $this->preview = null;
            $this->competition->refresh();
            $this->notice = __('competition.draw_done');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function generateFixtures(DrawService $service): void
    {
        $this->authorize('manage', $this->competition);
        $this->clearMessages();

        try {
            $count = $service->generateFixtures($this->competition, Auth::user());
            $this->notice = __('competition.fixtures_done', ['count' => fa_num($count)]);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function resetDraw(DrawService $service): void
    {
        $this->authorize('manage', $this->competition);
        $this->clearMessages();

        try {
            $service->reset($this->competition, Auth::user());
            $this->preview = null;
            $this->competition->refresh();
            $this->notice = __('competition.draw_reset_done');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function markReady(CompetitionService $service): void
    {
        $this->authorize('manage', $this->competition);
        $this->clearMessages();

        try {
            $this->competition = $service->markReady($this->competition, Auth::user());
            $this->notice = __('competition.ready_done');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    // --------------------------------------------------------------- تنظیمات

    public function saveSettings(CompetitionService $service): void
    {
        $this->authorize('manage', $this->competition);
        $this->clearMessages();

        $this->validate([
            'sName' => ['required', 'string', 'max:'.CompetitionService::MAX_NAME_LENGTH],
            'sGender' => ['required', 'in:'.implode(',', array_column(CompetitionGender::cases(), 'value'))],
            'sCapacity' => ['nullable', 'integer', 'min:2'],
        ], [], [
            'sName' => __('competition.fields.name'),
            'sGender' => __('competition.fields.gender'),
            'sCapacity' => __('competition.fields.capacity'),
        ]);

        try {
            $this->competition = $service->update($this->competition, Auth::user(), [
                'name' => $this->sName,
                'gender' => $this->sGender,
                'capacity' => $this->sCapacity,
                'requires_confirmation' => $this->sConfirm,
            ]);
            $this->notice = __('competition.settings_saved');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function assignRole(CompetitionService $service): void
    {
        $this->authorize('assignRoles', $this->competition);
        $this->clearMessages();

        $role = CompetitionRole::tryFrom($this->roleValue);
        $user = ctype_digit($this->roleUserId) ? User::query()->find((int) $this->roleUserId) : null;

        if ($role === null || $user === null) {
            $this->error = __('competition.errors.role_user_invalid');

            return;
        }

        try {
            $service->assignRole($this->competition, Auth::user(), $user, $role);
            $this->roleUserId = '';
            $this->notice = __('competition.role_assigned');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function removeRole(int $userId, CompetitionService $service): void
    {
        $this->authorize('assignRoles', $this->competition);
        $this->clearMessages();

        $user = $this->competition->members()->where('users.id', $userId)->firstOrFail();

        try {
            $service->removeRole($this->competition, Auth::user(), $user);
            $this->notice = __('competition.role_removed');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function deleteCompetition(CompetitionService $service): void
    {
        $this->authorize('delete', $this->competition);
        $this->clearMessages();

        try {
            $service->delete($this->competition, Auth::user());
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        session()->flash('success', __('competition.deleted'));
        $this->redirect(route('events.show', ['event' => $this->event, 'tab' => 'competitions']), navigate: true);
    }


    // ------------------------------------------------- نتیجه و زمان‌بندی (12C)

    public function setFixturesView(string $view): void
    {
        $this->fixturesView = in_array($view, ['rounds', 'matrix'], true) ? $view : 'rounds';
        $this->cancelEdit();
    }

    public function setRound(string $round): void
    {
        $this->roundFilter = ctype_digit($round) ? $round : '';
        $this->cancelEdit();
    }

    public function startResult(int $id): void
    {
        $this->authorize('enterResult', $this->competition);
        $this->clearMessages();

        $match = $this->findMatch($id);
        $this->cancelEdit();
        $this->editingMatchId = $match->id;
        $this->mScoreA = $match->score_a !== null ? (string) $match->score_a : '';
        $this->mScoreB = $match->score_b !== null ? (string) $match->score_b : '';
        $this->mGames = [];

        if ($this->competition->config('unit', 'goals') === 'games') {
            foreach ($match->games()->get() as $game) {
                $this->mGames[] = ['a' => (string) $game->points_a, 'b' => (string) $game->points_b];
            }
            while (count($this->mGames) < (int) $this->competition->config('best_of', 5)) {
                $this->mGames[] = ['a' => '', 'b' => ''];
            }
        }
    }

    public function cancelEdit(): void
    {
        $this->editingMatchId = null;
        $this->scheduleMatchId = null;
        $this->reset(['mScoreA', 'mScoreB', 'mGames', 'scDate', 'scTime', 'scCourt']);
    }

    public function saveResult(MatchService $service): void
    {
        $this->authorize('enterResult', $this->competition);
        $this->clearMessages();

        if ($this->editingMatchId === null) {
            return;
        }

        $games = [];
        foreach ($this->mGames as $game) {
            if (trim((string) ($game['a'] ?? '')) === '' && trim((string) ($game['b'] ?? '')) === '') {
                continue;
            }
            $games[] = [$game['a'] ?? '', $game['b'] ?? ''];
        }

        try {
            $service->enterResult($this->findMatch($this->editingMatchId), Auth::user(), [
                'score_a' => $this->mScoreA,
                'score_b' => $this->mScoreB,
                'games' => $games,
            ], Auth::user()->can('confirmResult', $this->competition));

            $this->cancelEdit();
            $this->competition->refresh();
            $this->notice = __('competition.result_saved');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function confirmMatch(int $id, MatchService $service): void
    {
        $this->matchAction('confirmResult', 'result_confirmed', fn (SportMatch $m) => $service->confirmResult($m, Auth::user()), $id);
    }

    public function clearMatch(int $id, MatchService $service): void
    {
        $this->matchAction('confirmResult', 'result_cleared', fn (SportMatch $m) => $service->clearResult($m, Auth::user()), $id);
    }

    public function walkoverMatch(int $id, string $side, MatchService $service): void
    {
        $this->matchAction('confirmResult', 'walkover_done', fn (SportMatch $m) => $service->walkover($m, Auth::user(), $side), $id);
    }

    public function cancelMatch(int $id, MatchService $service): void
    {
        $this->matchAction('confirmResult', 'match_cancelled_done', fn (SportMatch $m) => $service->cancel($m, Auth::user()), $id);
    }

    public function postponeMatch(int $id, MatchService $service): void
    {
        $this->matchAction('manage', 'postponed_done', fn (SportMatch $m) => $service->postpone($m, Auth::user()), $id);
    }

    public function deleteMatch(int $id, MatchService $service): void
    {
        $this->matchAction('manage', 'match_deleted', function (SportMatch $m) use ($service) {
            $service->deleteManual($m, Auth::user());
        }, $id);
    }

    public function startSchedule(int $id): void
    {
        $this->authorize('manage', $this->competition);
        $this->clearMessages();

        $match = $this->findMatch($id);
        $this->cancelEdit();
        $this->scheduleMatchId = $match->id;
        $this->scDate = $match->scheduled_at?->toDateString() ?? '';
        $this->scTime = $match->scheduled_at?->format('H:i') ?? '';
        $this->scCourt = (string) ($match->court ?? '');
    }

    public function saveSchedule(MatchService $service): void
    {
        $this->authorize('manage', $this->competition);
        $this->clearMessages();

        if ($this->scheduleMatchId === null) {
            return;
        }

        $at = null;
        if ($this->scDate !== '') {
            $time = $this->scTime !== '' ? $this->scTime : '09:00';

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->scDate) !== 1 || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
                $this->error = __('competition.errors.schedule_invalid');

                return;
            }

            $at = Carbon::createFromFormat('Y-m-d H:i', $this->scDate.' '.$time);
            if ($at === false) {
                $this->error = __('competition.errors.schedule_invalid');

                return;
            }
        }

        try {
            $service->schedule($this->findMatch($this->scheduleMatchId), Auth::user(), $at, $this->scCourt);
            $this->cancelEdit();
            $this->notice = __('competition.scheduled_done');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function addManualMatch(MatchService $service): void
    {
        $this->authorize('manage', $this->competition);
        $this->clearMessages();

        $stage = $this->competition->firstStage();
        if ($stage === null) {
            return;
        }

        try {
            $service->addManual($stage, Auth::user(), ['a_id' => $this->xA, 'b_id' => $this->xB, 'round' => $this->xRound]);
            $this->reset(['xA', 'xB']);
            $this->notice = __('competition.manual_added');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function importResults(MatchService $service): void
    {
        $this->authorize('enterResult', $this->competition);
        $this->clearMessages();

        $this->validate(['importText' => ['required', 'string', 'max:20000']], [], ['importText' => __('competition.fields.bulk')]);

        try {
            $result = $service->importResults(
                $this->competition,
                Auth::user(),
                $this->importText,
                Auth::user()->can('confirmResult', $this->competition),
            );
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->importText = '';
        $this->competition->refresh();
        $this->notice = __('competition.import_done', ['count' => fa_num($result['applied'])]);

        if ($result['errors'] !== []) {
            $this->error = collect($result['errors'])->take(5)
                ->map(fn (array $e): string => __('competition.bulk_line', ['line' => fa_num($e['line']), 'message' => $e['message']]))
                ->implode(' | ');
        }
    }

    public function reopen(CompetitionService $service): void
    {
        $this->authorize('manage', $this->competition);
        $this->clearMessages();

        try {
            $this->competition = $service->reopen($this->competition, Auth::user());
            $this->notice = __('competition.reopened');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    /**
     * @param  callable(SportMatch): mixed  $action
     */
    private function matchAction(string $ability, string $noticeKey, callable $action, int $id): void
    {
        $this->authorize($ability, $this->competition);
        $this->clearMessages();

        try {
            $action($this->findMatch($id));
            $this->cancelEdit();
            $this->competition->refresh();
            $this->notice = __('competition.'.$noticeKey);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    private function matchOrNull(?int $id): ?SportMatch
    {
        if ($id === null || $this->section !== 'fixtures') {
            return null;
        }

        return SportMatch::query()
            ->with(['a', 'b'])
            ->whereKey($id)
            ->whereHas('stage', fn ($q) => $q->where('competition_id', $this->competition->id))
            ->first();
    }

    private function findMatch(int $id): SportMatch
    {
        return SportMatch::query()
            ->whereKey($id)
            ->whereHas('stage', fn ($q) => $q->where('competition_id', $this->competition->id))
            ->firstOrFail();
    }

    // ----------------------------------------------------------------- render

    public function render(CompetitionService $service, StandingsService $standings)
    {
        $competition = $this->competition->fresh(['stages']) ?? $this->competition;
        $stage = $competition->stages->first();

        $competitors = $competition->competitors()->withCount('members')->orderBy('id')->get();

        $fixtures = ['rounds' => [], 'current' => null, 'list' => collect(), 'matrix' => null];
        $draw = null;
        $standingRows = [];
        $entries = collect();

        if ($stage !== null && $stage->status !== StageStatus::Pending) {
            $entries = $stage->entries()->with('competitor')->get();
            $draw = $stage->draws()->with('performer')->latest('id')->first();

            if ($this->section === 'fixtures') {
                $fixtures = $this->fixtureData($stage, $entries);
            }

            if ($this->section === 'standings') {
                $standingRows = $standings->forStage($stage);
            }
        }

        $user = Auth::user();

        return view('livewire.competitions.competition-show', [
            'comp' => $competition,
            'stage' => $stage,
            'competitors' => $competitors,
            'entries' => $entries,
            'draw' => $draw,
            'fixtures' => $fixtures,
            'editing' => $this->matchOrNull($this->editingMatchId),
            'scheduling' => $this->matchOrNull($this->scheduleMatchId),
            'canResult' => $user->can('enterResult', $competition),
            'canConfirm' => $user->can('confirmResult', $competition),
            'standingRows' => $standingRows,
            'canManage' => $user->can('manage', $competition),
            'canAssign' => $user->can('assignRoles', $competition),
            'canDelete' => $user->can('delete', $competition),
            'hasFeature' => $service->hasFeature($this->event),
            'members' => $competition->members()->get(),
            'eventMembers' => $this->event->members()->get()
                ->filter(fn ($u) => $u->pivot->role !== EventRole::Viewer->value)->values(),
            'roles' => CompetitionRole::cases(),
            'genders' => CompetitionGender::cases(),
            'sections' => $this->sections(),
            'counts' => [
                'registered' => $competitors->where('status', CompetitorStatus::Registered)->count(),
                'confirmed' => $competitors->where('status', CompetitorStatus::Confirmed)->count(),
                'withdrawn' => $competitors->where('status', CompetitorStatus::Withdrawn)->count(),
            ],
        ])->title($competition->name);
    }

    // -------------------------------------------------------------- internals

    /**
     * داده‌ی بخش برنامه‌ی بازی‌ها: دور جاری (صفحه‌بندی بر اساس دور) و ماتریس لیگ.
     *
     * @param  Collection<int, \App\Models\StageEntry>  $entries
     * @return array{rounds: list<int>, current: int|null, list: Collection<int, SportMatch>, matrix: array<string, mixed>|null}
     */
    private function fixtureData(Stage $stage, Collection $entries): array
    {
        $all = $stage->matches()->with(['a', 'b'])->orderBy('round')->orderBy('number')->limit(1000)->get();
        $rounds = $all->pluck('round')->unique()->values()->map(fn ($r) => (int) $r)->all();

        $current = null;
        if ($rounds !== []) {
            $pending = $all->first(fn (SportMatch $m) => $m->status !== MatchStatus::Cancelled
                && ! ($m->result_status === ResultStatus::Confirmed && $m->status->isPlayed()));

            $current = ctype_digit($this->roundFilter) && in_array((int) $this->roundFilter, $rounds, true)
                ? (int) $this->roundFilter
                : (int) ($pending?->round ?? end($rounds));
        }

        $matrix = null;
        if ($this->fixturesView === 'matrix' && $entries->isNotEmpty()) {
            $cells = [];
            foreach ($all as $m) {
                if ($m->a_id !== null && $m->b_id !== null) {
                    $cells[min($m->a_id, $m->b_id).'-'.max($m->a_id, $m->b_id).'-'.$m->leg] = $m;
                }
            }
            $matrix = ['entries' => $entries->values(), 'cells' => $cells, 'legs' => (int) $stage->config('legs', 1)];
        }

        return ['rounds' => $rounds, 'current' => $current, 'list' => $all->where('round', $current)->values(), 'matrix' => $matrix];
    }

    /**
     * @return list<string>
     */
    private function sections(): array
    {
        return ['competitors', 'draw', 'fixtures', 'standings', 'settings'];
    }

    private function normalizeSection(string $section): string
    {
        return in_array($section, $this->sections(), true) ? $section : 'competitors';
    }

    private function findCompetitor(int $id): Competitor
    {
        return $this->competition->competitors()->whereKey($id)->firstOrFail();
    }

    private function fillSettings(): void
    {
        $this->sName = $this->competition->name;
        $this->sGender = $this->competition->gender->value;
        $this->sCapacity = (string) ($this->competition->capacity ?? '');
        $this->sConfirm = $this->competition->requires_confirmation;
    }

    private function clearMessages(): void
    {
        $this->error = null;
        $this->notice = null;
    }
}
