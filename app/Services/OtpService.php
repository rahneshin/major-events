<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Models\User;
use App\Services\Sms\SmsGateway;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public const CODE_LENGTH = 6;

    public const EXPIRES_MINUTES = 2;

    public const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly SmsGateway $sms
    ) {}

    /**
     * ارسال کد OTP به شماره موبایل.
     *
     * @return string|null کد خام فقط وقتی درایور log/null است (برای تست محلی)
     *
     * @throws ValidationException
     */
    public function send(string $phone, ?string $ip = null, ?string $userAgent = null): ?string
    {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'phone' => __('auth.invalid_phone'),
            ]);
        }

        $this->ensureNotRateLimited($normalized, $ip);

        $code = $this->generateCode();

        OtpCode::query()->create([
            'phone' => $normalized,
            'code_hash' => Hash::make($code),
            'purpose' => 'login',
            'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
            'attempts' => 0,
            'ip' => $ip,
            'user_agent' => $userAgent,
        ]);

        $message = __('auth.otp_message', ['code' => $code]);

        $this->sms->send($normalized, $message);

        RateLimiter::hit($this->phoneLimiterKey($normalized), 60);
        RateLimiter::hit($this->phoneHourlyLimiterKey($normalized), 3600);

        if ($ip) {
            RateLimiter::hit($this->ipLimiterKey($ip), 3600);
        }

        // فقط در محیط توسعه / درایور log|null کد را برمی‌گردانیم تا در UI نمایش داده شود
        if ($this->shouldExposeCode()) {
            return $code;
        }

        return null;
    }

    /**
     * تأیید کد و ورود کاربر.
     *
     * @return User کاربر لاگین‌شده
     *
     * @throws ValidationException
     */
    public function verify(string $phone, string $code, ?string $ip = null): User
    {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'phone' => __('auth.invalid_phone'),
            ]);
        }

        $code = PhoneNumber::toLatinDigits(trim($code));

        if (! preg_match('/^\d{6}$/', $code)) {
            throw ValidationException::withMessages([
                'code' => __('auth.invalid_code'),
            ]);
        }

        /** @var OtpCode|null $otp */
        $otp = OtpCode::query()
            ->where('phone', $normalized)
            ->where('purpose', 'login')
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();

        if ($otp === null || $otp->isExpired()) {
            throw ValidationException::withMessages([
                'code' => __('auth.code_expired'),
            ]);
        }

        if ($otp->hasExceededAttempts(self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'code' => __('auth.too_many_attempts'),
            ]);
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            throw ValidationException::withMessages([
                'code' => __('auth.invalid_code'),
            ]);
        }

        $otp->update(['consumed_at' => now()]);

        $user = User::query()->firstOrCreate(
            ['phone' => $normalized],
            [
                'phone_verified_at' => now(),
                'status' => 'active',
            ]
        );

        if ($user->phone_verified_at === null) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'phone' => __('auth.account_suspended'),
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        Auth::login($user);
        session()->regenerate();

        return $user;
    }

    public function shouldExposeCode(): bool
    {
        if (app()->environment('local', 'testing')) {
            return true;
        }

        return in_array(config('sms.driver', 'log'), ['log', 'null'], true);
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    private function ensureNotRateLimited(string $phone, ?string $ip): void
    {
        // حداکثر ۱ کد در ۶۰ ثانیه برای هر شماره
        if (RateLimiter::tooManyAttempts($this->phoneLimiterKey($phone), 1)) {
            $seconds = RateLimiter::availableIn($this->phoneLimiterKey($phone));

            throw ValidationException::withMessages([
                'phone' => __('auth.throttle_phone', ['seconds' => $seconds]),
            ]);
        }

        // حداکثر ۵ کد در ساعت برای هر شماره
        if (RateLimiter::tooManyAttempts($this->phoneHourlyLimiterKey($phone), 5)) {
            throw ValidationException::withMessages([
                'phone' => __('auth.throttle_hourly'),
            ]);
        }

        // حداکثر ۲۰ درخواست در ساعت برای هر IP
        if ($ip && RateLimiter::tooManyAttempts($this->ipLimiterKey($ip), 20)) {
            throw ValidationException::withMessages([
                'phone' => __('auth.throttle_ip'),
            ]);
        }
    }

    private function phoneLimiterKey(string $phone): string
    {
        return 'otp-phone:'.$phone;
    }

    private function phoneHourlyLimiterKey(string $phone): string
    {
        return 'otp-phone-hourly:'.$phone;
    }

    private function ipLimiterKey(string $ip): string
    {
        return 'otp-ip:'.$ip;
    }
}
