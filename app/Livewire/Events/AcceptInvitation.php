<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Exceptions\LimitExceededException;
use App\Models\EventInvitation;
use App\Services\HumanResourceService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * لینک دعوت پیامکی: /invite/{token}
 *  - مهمان: توکن در session می‌ماند ← ورود با OTP ← برگشت به همین آدرس
 *  - واردشده: عضو رویداد (و کمیته/پست) می‌شود ← پنل کاربر
 */
#[Layout('layouts.guest')]
#[Title('دعوت به رویداد')]
class AcceptInvitation extends Component
{
    public ?string $error = null;

    public ?string $eventTitle = null;

    public function mount(string $token, HumanResourceService $hr): void
    {
        $invitation = EventInvitation::findByToken($token);

        if ($invitation === null || ! $invitation->isPending()) {
            $this->error = __('hr.invite_invalid');

            return;
        }

        $this->eventTitle = $invitation->event?->title;

        if (! Auth::check()) {
            session()->put('pending_invite_token', $token);
            session()->put('invite_phone', $invitation->phone);
            $this->redirect(route('auth.login'));

            return;
        }

        try {
            $event = $hr->accept($invitation, Auth::user());
        } catch (LimitExceededException|\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        session()->forget(['pending_invite_token', 'invite_phone']);
        session()->flash('success', __('hr.invite_accepted', ['event' => $event->title]));

        $this->redirect(route('app.dashboard'));
    }

    public function render()
    {
        return view('livewire.events.accept-invitation');
    }
}
