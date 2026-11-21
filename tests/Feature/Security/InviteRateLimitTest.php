<?php

use App\Enums\OrgRole;
use App\Models\User;
use App\Services\EventMemberService;
use App\Services\EventService;
use App\Services\OrganizationService;
use Illuminate\Validation\ValidationException;

it('rate limits event invitations per user', function () {
    $owner = User::factory()->organizational()->create();
    $event = app(EventService::class)->create($owner, ['title' => 'محدودیت دعوت']);
    $svc = app(EventMemberService::class);

    foreach (range(1, EventMemberService::INVITES_PER_HOUR) as $i) {
        $svc->inviteByPhone($event, $owner, '0912'.str_pad((string) $i, 7, '0', STR_PAD_LEFT));
    }

    expect(fn () => $svc->inviteByPhone($event, $owner, '09129999999'))
        ->toThrow(InvalidArgumentException::class);
});

it('rate limits organization invitations per user', function () {
    $owner = User::factory()->organizational()->create();
    $svc = app(OrganizationService::class);
    $org = $svc->create($owner, ['name' => 'سازمان محدودیت', 'type' => 'club']);

    foreach (range(1, 20) as $i) {
        $svc->invite($org, $owner, '0913'.str_pad((string) $i, 7, '0', STR_PAD_LEFT), OrgRole::Member);
    }

    expect(fn () => $svc->invite($org, $owner, '09139999999', OrgRole::Member))
        ->toThrow(ValidationException::class);
});
