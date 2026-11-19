<?php

namespace App\Console\Commands;

use App\Models\OtpCode;
use Illuminate\Console\Command;

class PruneOtpCodes extends Command
{
    protected $signature = 'otp:prune';

    protected $description = 'حذف کدهای OTP منقضی و مصرف‌شده';

    public function handle(): int
    {
        $deleted = OtpCode::query()
            ->where(function ($q) {
                $q->where('expires_at', '<', now()->subHour())
                    ->orWhereNotNull('consumed_at');
            })
            ->delete();

        $this->info("Deleted {$deleted} OTP code(s).");

        return self::SUCCESS;
    }
}
