<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Enums\CompetitionGender;
use App\Models\Competition;
use App\Models\Event;
use App\Services\CompetitionService;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * تب «مسابقات» در هاب رویداد (Step 12): فهرست ورزش‌ها + ساخت ورزش از قالب.
 * ورود به هر ورزش: صفحه‌ی Competitions\CompetitionShow.
 */
class EventCompetitions extends Component
{
    public Event $event;

    public bool $showForm = false;

    public string $cTemplate = '';

    public string $cName = '';

    public string $cGender = 'open';

    public string $cCapacity = '';

    public ?string $error = null;

    public ?string $notice = null;

    public function mount(Event $event): void
    {
        $this->authorize('viewAny', [Competition::class, $event]);
        $this->event = $event;
    }

    public function openForm(): void
    {
        $this->authorize('create', [Competition::class, $this->event]);
        $this->reset(['cTemplate', 'cName', 'cCapacity', 'error', 'notice']);
        $this->cGender = 'open';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function save(CompetitionService $service): void
    {
        $this->authorize('create', [Competition::class, $this->event]);
        $this->error = null;
        $this->notice = null;

        $this->validate([
            'cTemplate' => ['required', 'string', 'max:40'],
            'cName' => ['required', 'string', 'max:'.CompetitionService::MAX_NAME_LENGTH],
            'cGender' => ['required', 'in:'.implode(',', array_column(CompetitionGender::cases(), 'value'))],
            'cCapacity' => ['nullable', 'integer', 'min:2'],
        ], [], [
            'cTemplate' => __('competition.fields.template'),
            'cName' => __('competition.fields.name'),
            'cGender' => __('competition.fields.gender'),
            'cCapacity' => __('competition.fields.capacity'),
        ]);

        try {
            $competition = $service->create($this->event, Auth::user(), [
                'template_code' => $this->cTemplate,
                'name' => $this->cName,
                'gender' => $this->cGender,
                'capacity' => $this->cCapacity,
            ]);

            $this->showForm = false;
            $this->notice = __('competition.created');
            $this->redirect(route('competitions.show', ['event' => $this->event, 'competition' => $competition]), navigate: true);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(CompetitionService $service)
    {
        return view('livewire.events.event-competitions', [
            'hasFeature' => $service->hasFeature($this->event),
            'canCreate' => Auth::user()->can('create', [Competition::class, $this->event]),
            'competitions' => Competition::query()->forEvent($this->event)
                ->withCount(['competitors as active_competitors_count' => fn ($q) => $q->where('status', '!=', 'withdrawn')])
                ->orderBy('id')->get(),
            'templates' => $service->templates(),
            'genders' => CompetitionGender::cases(),
        ]);
    }
}
