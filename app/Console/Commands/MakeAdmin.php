<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MakeAdmin extends Command
{
    protected $signature = 'user:make-admin {phone : شماره موبایل کاربر} {--revoke : لغو دسترسی ادمین}';

    protected $description = 'دادن یا گرفتن دسترسی ادمین سیستم (فقط از طریق CLI)';

    public function handle(): int
    {
        $phone = PhoneNumber::normalize((string) $this->argument('phone'));

        if ($phone === null) {
            $this->error('شماره موبایل نامعتبر است.');

            return self::FAILURE;
        }

        $user = User::query()->where('phone', $phone)->first();

        if ($user === null) {
            $this->error('کاربری با این شماره پیدا نشد. ابتدا باید یک‌بار وارد سامانه شده باشد.');

            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');

        $user->forceFill(['is_super_admin' => $grant])->save();

        Log::info($grant ? 'admin.granted' : 'admin.revoked', ['user_id' => $user->id]);

        $this->info($grant
            ? "کاربر {$phone} اکنون ادمین سیستم است."
            : "دسترسی ادمین کاربر {$phone} لغو شد.");

        return self::SUCCESS;
    }
}
