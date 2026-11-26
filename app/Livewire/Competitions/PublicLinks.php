<?php

declare(strict_types=1);

namespace App\Livewire\Competitions;

use App\Enums\ParticipantType;
use App\Models\Competition;
use App\Models\Event;
use App\Services\PublicLinkService;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Step 12E — مدیریت لینک عمومی ورزش‌ها (فعال/غیرفعال/تغییر توکن/مخفف‌کردن نام).
 * حالت رویداد: همه‌ی ورزش‌هایی که کاربر `manage` دارد؛ حالت تک‌ورزش: با `competitionId`.
 * هر اکشن جداگانه authorize می‌شود و شناسه فقط داخل همین رویداد پیدا می‌شود.
 */
class PublicLinks extends Component
{
    public Event $event;

    public ?int $competitionId = null;

    public ?string $error = null;

    public ?string $notice = null;

    public function mount(Event $event, ?int $competitionId = null): void
    {
        $this->authorize('viewAny', [Competition::class, $event]);

        if ($competitionId !== null) {
            abort_unless(Competition::query()->forEvent($event)->whereKey($competitionId)->exists(), 404);
        }

        $this->event = $event;
        $this->competitionId = $competitionId;
    }

    public function enable(int $id, PublicLinkService $links): void
    {
        $this->run($id, fn (Competition $c) => $links->enable($c, Auth::user()), 'publicpage.links.enabled_done');
    }

    public function rotate(int $id, PublicLinkService $links): void
    {
        $this->run($id, fn (Competition $c) => $links->rotate($c, Auth::user()), 'publicpage.links.rotated_done');
    }

    public function disable(int $id, PublicLinkService $links): void
    {
        $this->run($id, fn (Competition $c) => $links->disable($c, Auth::user()), 'publicpage.links.disabled_done');
    }

    public function toggleAbbreviate(int $id, PublicLinkService $links): void
    {
        $this->run(
            $id,
            fn (Competition $c) => $links->setAbbreviateNames($c, Auth::user(), ! $links->abbreviatesNames($c)),
            'publicpage.links.names_done',
        );
    }

    public function render(PublicLinkService $links)
    {
        $user = Auth::user();

        $rows = Competition::query()
            ->forEvent($this->event)
            ->when($this->competitionId !== null, fn ($q) => $q->whereKey($this->competitionId))
            ->orderBy('id')
            ->get()
            ->filter(fn (Competition $c): bool => $user->can('manage', $c))
            ->map(fn (Competition $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'url' => $links->url($c),
                'abbreviate' => $links->abbreviatesNames($c),
                'individual' => $c->participant_type === ParticipantType::Individual,
            ])->values();

        return view('livewire.competitions.public-links', ['rows' => $rows]);
    }

    /**
     * @param  callable(Competition): mixed  $action
     */
    private function run(int $id, callable $action, string $noticeKey): void
    {
        $this->error = null;
        $this->notice = null;

        $competition = Competition::query()->forEvent($this->event)
            ->when($this->competitionId !== null, fn ($q) => $q->whereKey($this->competitionId))
            ->whereKey($id)
            ->firstOrFail();

        $this->authorize('manage', $competition);

        try {
            $action($competition);
            $this->notice = __($noticeKey);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }
}
