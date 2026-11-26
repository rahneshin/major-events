<?php

declare(strict_types=1);

namespace App\Livewire\Competitions;

use App\Enums\CompetitionGender;
use App\Enums\CompetitionRole;
use App\Enums\CompetitionStatus;
use App\Enums\CompetitorStatus;
use App\Enums\EventRole;
use App\Enums\HeatResultStatus;
use App\Models\Competition;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\Heat;
use App\Models\User;
use App\Services\CompetitionService;
use App\Services\Competitions\Engine\DrawEngine;
use App\Services\HeatService;
use App\Services\RegistrationService;
use App\Support\HeatValue;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Step 12E — صفحه‌ی ورزش رتبه‌ای (دوومیدانی/شنا): ثبت‌نام، قرعه‌ی سری‌ها، ثبت/تأیید نتیجه‌ی هر سری،
 * ساخت مرحله‌ی بعد، رتبه‌ی نهایی، تنظیمات و لینک عمومی.
 *
 * دسترسی (CompetitionPolicy): view برای همه‌ی بیننده‌های رویداد؛ manage برای مدیر رویداد/مسئول ورزش؛
 * ثبت نتیجه = enterResult؛ تأیید/حذف نتیجه = confirmResult. هر اکشن جداگانه authorize می‌شود
 * و هر شناسه فقط داخل همین ورزش پیدا می‌شود.
 */
#[Layout('layouts.app')]
class RankedShow extends Component
{
    public Event $event;

    public Competition $competition;

    #[Url(as: 'section')]
    public string $section = 'competitors';

    // ---- ثبت‌نام
    public string $rName = '';

    public string $rAffiliation = '';

    public string $rSeed = '';

    public string $bulkText = '';

    // ---- قرعه و تنظیم مرحله
    public string $dMethod = 'random';

    public string $dSeed = '';

    /** @var array{method: string, seed: string, order: list<int>, heats: list<list<int>>, names: array<int, string>}|null */
    public ?array $preview = null;

    public string $cHeatSize = '';

    public string $cPerHeat = '';

    public string $cBest = '';

    // ---- نتیجه‌ی سری
    public ?int $editingHeatId = null;

    /** @var array<int, array{value: string, status: string}> */
    public array $entry = [];

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

        // ورزش غیررتبه‌ای صفحه‌ی خودش را دارد
        if ($competition->result_type !== 'ranked') {
            $this->redirect(route('competitions.show', ['event' => $event, 'competition' => $competition]), navigate: true);

            return;
        }

        $this->section = $this->normalizeSection($this->section);
        $this->fillSettings();
        $this->fillStageConfig();
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
        ], [], [
            'rName' => __('competition.fields.competitor_name'),
            'rAffiliation' => __('competition.fields.affiliation'),
            'rSeed' => __('competition.fields.seed'),
        ]);

        try {
            $service->register($this->competition, Auth::user(), [
                'name' => $this->rName,
                'affiliation' => $this->rAffiliation,
                'seed' => $this->rSeed,
            ]);
            $this->reset(['rName', 'rAffiliation', 'rSeed']);
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

    // ------------------------------------------------------------ قرعه/سری

    public function saveStageConfig(int $stageId, HeatService $service): void
    {
        $this->authorize('manage', $this->competition);
        $this->clearMessages();

        $stage = $this->competition->stages()->whereKey($stageId)->firstOrFail();

        try {
            $service->configureStage($stage, Auth::user(), [
                'heat_size' => $this->cHeatSize,
                'advance_per_heat' => $this->cPerHeat,
                'advance_best_remaining' => $this->cBest,
            ]);
            $this->notice = __('ranked.config_saved');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function previewDraw(HeatService $service): void
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

    public function confirmDraw(HeatService $service): void
    {
        $this->authorize('manage', $this->competition);
        $this->clearMessages();

        if ($this->preview === null) {
            return;
        }

        try {
            $service->draw($this->competition, Auth::user(), $this->preview['method'], $this->preview['seed']);
            $this->competition->refresh();
            $this->preview = null;
            $this->notice = __('competition.draw_done');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function resetDraw(HeatService $service): void
    {
        $this->authorize('manage', $this->competition);
        $this->clearMessages();

        try {
            $service->reset($this->competition, Auth::user());
            $this->competition->refresh();
            $this->cancelEdit();
            $this->notice = __('competition.draw_reset_done');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function markReady(HeatService $service): void
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

    public function advance(HeatService $service): void
    {
        $this->authorize('manage', $this->competition);
        $this->clearMessages();

        try {
            $result = $service->advance($this->competition, Auth::user());
            $this->competition->refresh();
            $this->notice = __('ranked.advance_done', ['count' => fa_num($result['advancers'])]);

            if ($result['tied_at_cutoff']) {
                $this->notice .= ' '.__('ranked.advance_tied');
            }
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function rollbackAdvance(HeatService $service): void
    {
        $this->authorize('manage', $this->competition);
        $this->clearMessages();

        try {
            $service->rollbackAdvance($this->competition, Auth::user());
            $this->competition->refresh();
            $this->notice = __('ranked.rollback_done');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function reopen(HeatService $service): void
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

    // ------------------------------------------------------------- نتیجه‌ی سری

    public function openHeat(int $heatId): void
    {
        $this->authorize('enterResult', $this->competition);
        $this->clearMessages();

        $heat = $this->findHeat($heatId);

        if ($heat->isConfirmed() && ! Auth::user()->can('confirmResult', $this->competition)) {
            $this->error = __('competition.errors.confirmed_locked');

            return;
        }

        $kind = app(HeatService::class)->valueKind($this->competition);
        $this->entry = [];

        foreach ($heat->results()->with('competitor')->get() as $row) {
            $withdrawn = $row->competitor->status === CompetitorStatus::Withdrawn;

            $this->entry[$row->competitor_id] = [
                'value' => $row->value !== null ? HeatValue::format($row->value, $kind) : '',
                'status' => $withdrawn ? HeatResultStatus::Dns->value : $row->status->value,
            ];
        }

        $this->editingHeatId = $heat->id;
    }

    public function cancelEdit(): void
    {
        $this->editingHeatId = null;
        $this->entry = [];
        $this->resetValidation();
    }

    public function saveHeat(HeatService $service): void
    {
        $this->authorize('enterResult', $this->competition);
        $this->clearMessages();

        if ($this->editingHeatId === null) {
            return;
        }

        $this->validate([
            'entry.*.value' => ['nullable', 'string', 'max:24'],
            'entry.*.status' => ['required', 'in:'.implode(',', array_column(HeatResultStatus::cases(), 'value'))],
        ]);

        try {
            $service->enterResults(
                $this->findHeat($this->editingHeatId),
                Auth::user(),
                $this->entry,
                Auth::user()->can('confirmResult', $this->competition),
            );
            $this->competition->refresh();
            $this->cancelEdit();
            $this->notice = __('ranked.heat_saved');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function confirmHeat(int $heatId, HeatService $service): void
    {
        $this->authorize('confirmResult', $this->competition);
        $this->clearMessages();

        try {
            $service->confirmHeat($this->findHeat($heatId), Auth::user());
            $this->competition->refresh();
            $this->notice = __('ranked.heat_confirmed');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function clearHeat(int $heatId, HeatService $service): void
    {
        $this->authorize('confirmResult', $this->competition);
        $this->clearMessages();

        try {
            $service->clearHeat($this->findHeat($heatId), Auth::user());
            $this->competition->refresh();
            $this->cancelEdit();
            $this->notice = __('ranked.heat_cleared');
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

    public function deleteCompetition(CompetitionService $service, HeatService $heats): void
    {
        $this->authorize('delete', $this->competition);
        $this->clearMessages();

        // CompetitionService::delete فقط بازی‌های تأییدشده را می‌شناسد؛ نتیجه‌ی سری‌ها را اینجا مسدود می‌کنیم
        if ($heats->hasRecordedResults($this->competition)) {
            $this->error = __('ranked.errors.has_results');

            return;
        }

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

    public function render(CompetitionService $service, HeatService $heats)
    {
        $competition = $this->competition->fresh(['stages']) ?? $this->competition;
        $user = Auth::user();

        $competitors = $competition->competitors()->orderBy('id')->get();
        $board = $heats->board($competition);
        $first = $competition->stages->first();

        $last = $board === [] ? null : $board[array_key_last($board)];
        $lastConfirmed = $last !== null && $last['heats']->every(fn (Heat $h): bool => $h->isConfirmed());

        return view('livewire.competitions.ranked-show', [
            'comp' => $competition,
            'competitors' => $competitors,
            'counts' => [
                'registered' => $competitors->where('status', CompetitorStatus::Registered)->count(),
                'confirmed' => $competitors->where('status', CompetitorStatus::Confirmed)->count(),
                'withdrawn' => $competitors->where('status', CompetitorStatus::Withdrawn)->count(),
            ],
            'board' => $board,
            'stages' => $competition->stages,
            'draw' => $first !== null ? $first->draws()->latest('id')->first() : null,
            'editing' => $this->editingHeatId !== null
                ? Heat::query()->whereIn('stage_id', $competition->stages->pluck('id'))->whereKey($this->editingHeatId)->first()
                : null,
            'better' => $heats->better($competition),
            'kind' => $heats->valueKind($competition),
            'resultsOpen' => $competition->status->acceptsResults(),
            'canAdvance' => $last !== null && $competition->status === CompetitionStatus::Running && ! $last['stage']->is_final && $lastConfirmed,
            'canRollback' => count($board) >= 2
                && $competition->status !== CompetitionStatus::Finished
                && ! $last['heats']->contains(fn (Heat $h): bool => $h->result_status !== null),
            'canReset' => $competition->status === CompetitionStatus::Drawn || $competition->status === CompetitionStatus::Ready
                || ($competition->status === CompetitionStatus::Running && ! $heats->hasRecordedResults($competition)),
            'ranking' => $competition->status === CompetitionStatus::Finished
                ? $competitors->whereNotNull('final_rank')->where('status', '!=', CompetitorStatus::Withdrawn)->sortBy(fn (Competitor $c): array => [$c->final_rank, $c->id])->values()
                : collect(),
            'canResult' => $user->can('enterResult', $competition),
            'canConfirm' => $user->can('confirmResult', $competition),
            'canManage' => $user->can('manage', $competition),
            'canAssign' => $user->can('assignRoles', $competition),
            'canDelete' => $user->can('delete', $competition),
            'hasFeature' => $service->hasFeature($this->event),
            'members' => $competition->members()->get(),
            'eventMembers' => $this->event->members()->get()
                ->filter(fn ($u) => $u->pivot->role !== EventRole::Viewer->value)->values(),
            'roles' => CompetitionRole::cases(),
            'genders' => CompetitionGender::cases(),
            'statuses' => HeatResultStatus::cases(),
            'drawMethods' => DrawEngine::METHODS,
            'sections' => $this->sections(),
        ]);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @return list<string>
     */
    private function sections(): array
    {
        return ['competitors', 'heats', 'ranking', 'settings'];
    }

    private function normalizeSection(string $section): string
    {
        return in_array($section, $this->sections(), true) ? $section : 'competitors';
    }

    private function findCompetitor(int $id): Competitor
    {
        return $this->competition->competitors()->whereKey($id)->firstOrFail();
    }

    /** سری فقط اگر مال یکی از مرحله‌های همین ورزش باشد. */
    private function findHeat(int $id): Heat
    {
        return Heat::query()
            ->whereIn('stage_id', $this->competition->stages()->pluck('id'))
            ->whereKey($id)
            ->firstOrFail();
    }

    private function fillSettings(): void
    {
        $this->sName = $this->competition->name;
        $this->sGender = $this->competition->gender->value;
        $this->sCapacity = (string) ($this->competition->capacity ?? '');
        $this->sConfirm = $this->competition->requires_confirmation;
    }

    private function fillStageConfig(): void
    {
        $stage = $this->competition->stages()->where('is_final', false)->first();

        $this->cHeatSize = (string) ($stage?->config('heat_size', HeatService::DEFAULT_HEAT_SIZE) ?? HeatService::DEFAULT_HEAT_SIZE);
        $this->cPerHeat = (string) ($stage?->config('advance_per_heat', 2) ?? 2);
        $this->cBest = (string) ($stage?->config('advance_best_remaining', 0) ?? 0);
    }

    private function clearMessages(): void
    {
        $this->error = null;
        $this->notice = null;
    }
}
