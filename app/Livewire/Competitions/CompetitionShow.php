<?php

declare(strict_types=1);

namespace App\Livewire\Competitions;

use App\Enums\CompetitionGender;
use App\Enums\CompetitionRole;
use App\Enums\CompetitorStatus;
use App\Enums\EventRole;
use App\Enums\StageStatus;
use App\Models\Competition;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\User;
use App\Services\CompetitionService;
use App\Services\Competitions\Engine\DrawEngine;
use App\Services\DrawService;
use App\Services\RegistrationService;
use App\Services\StandingsService;
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
 * ثبت و تأیید نتیجه در Step 12C اضافه می‌شود.
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

    // ----------------------------------------------------------------- render

    public function render(CompetitionService $service, StandingsService $standings)
    {
        $competition = $this->competition->fresh(['stages']) ?? $this->competition;
        $stage = $competition->stages->first();

        $competitors = $competition->competitors()->withCount('members')->orderBy('id')->get();

        $matches = collect();
        $draw = null;
        $standingRows = [];
        $entries = collect();

        if ($stage !== null && $stage->status !== StageStatus::Pending) {
            $entries = $stage->entries()->with('competitor')->get();
            $draw = $stage->draws()->with('performer')->latest('id')->first();

            if ($this->section === 'fixtures') {
                $matches = $stage->matches()->with(['a', 'b'])->orderBy('round')->orderBy('number')->limit(500)->get();
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
            'matches' => $matches->groupBy('round'),
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
