<?php

use App\Enums\DeliverableStatus;
use App\Enums\SponsorStatus;

it('allows the expected sponsor transitions only', function () {
    expect(SponsorStatus::Prospect->canTransitionTo(SponsorStatus::Negotiating))->toBeTrue()
        ->and(SponsorStatus::Prospect->canTransitionTo(SponsorStatus::Delivered))->toBeFalse()
        ->and(SponsorStatus::Negotiating->canTransitionTo(SponsorStatus::Signed))->toBeTrue()
        ->and(SponsorStatus::Negotiating->canTransitionTo(SponsorStatus::Delivered))->toBeFalse()
        ->and(SponsorStatus::Signed->canTransitionTo(SponsorStatus::Delivered))->toBeTrue()
        ->and(SponsorStatus::Signed->canTransitionTo(SponsorStatus::Prospect))->toBeFalse()
        ->and(SponsorStatus::Delivered->canTransitionTo(SponsorStatus::Signed))->toBeTrue()
        ->and(SponsorStatus::Delivered->canTransitionTo(SponsorStatus::Lost))->toBeFalse()
        ->and(SponsorStatus::Lost->canTransitionTo(SponsorStatus::Prospect))->toBeTrue()
        ->and(SponsorStatus::Lost->canTransitionTo(SponsorStatus::Signed))->toBeFalse();
});

it('splits statuses into committed and pipeline without overlap', function () {
    foreach (SponsorStatus::cases() as $status) {
        expect($status->isCommitted() && $status->isPipeline())->toBeFalse();
    }

    expect(SponsorStatus::Signed->isCommitted())->toBeTrue()
        ->and(SponsorStatus::Delivered->isCommitted())->toBeTrue()
        ->and(SponsorStatus::Prospect->isPipeline())->toBeTrue()
        ->and(SponsorStatus::Negotiating->isPipeline())->toBeTrue()
        ->and(SponsorStatus::Lost->isCommitted())->toBeFalse()
        ->and(SponsorStatus::Lost->isPipeline())->toBeFalse();
});

it('treats only pending and in-progress deliverables as open', function () {
    expect(DeliverableStatus::Pending->isOpen())->toBeTrue()
        ->and(DeliverableStatus::InProgress->isOpen())->toBeTrue()
        ->and(DeliverableStatus::Delivered->isOpen())->toBeFalse()
        ->and(DeliverableStatus::Cancelled->isOpen())->toBeFalse()
        ->and(DeliverableStatus::openValues())->toBe(['pending', 'in_progress']);
});
