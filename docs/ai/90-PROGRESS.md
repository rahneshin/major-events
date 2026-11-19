# 90 — PROGRESS (وضعیت پروژه، Manifest فایل‌ها، Decision Log، Changelog)

> **این فایل منبع حقیقت وضعیت پروژه است.** بعد از هر مرحله، بلوک `UPDATE` چت‌بات را در بخش «Changelog» بچسبان، جدول وضعیت و Manifest را به‌روز کن، سپس commit کن.
> **به چت‌بات:** وضعیت واقعی مخزن را فقط از همین فایل بخوان. اگر چیزی اینجا نیست، فرض نکن وجود دارد.

---

## ۱. خلاصه‌ی فنی فعلی

| مورد | مقدار |
|---|---|
| نسخه‌ی PHP | 8.3 |
| نسخه‌ی Laravel | 13.17 |
| نسخه‌ی Livewire | نصب شده |
| دیتابیس | SQLite (تا Step 16) |
| مخزن Git | https://github.com/rahneshin/major-events |
| شاخه‌ی اصلی | main |
| آخرین مرحله‌ی DONE | 01 |
| مرحله‌ی در حال انجام | 02 |
| تاریخ آخرین به‌روزرسانی | ۱۴۰۵/۰۸/۲۹ |

---

## ۲. جدول وضعیت مراحل

وضعیت‌ها: ⬜ شروع‌نشده · 🟨 در حال انجام · ✅ انجام‌شده · 🟥 مسدود

| # | مرحله | وضعیت | شاخه | تست‌ها | توضیح |
|---|---|---|---|---|---|
| 00 | نصب Laravel، Git، محیط | ✅ | step/00-tooling-and-locale | سبز | اسکلت + Pest + Larastan + Livewire + locale fa + CI |
| 01 | زیرساخت UI/RTL/جلالی | ✅ | step/01-ui-rtl-jalali | سبز | لایوت RTL، کامپوننت‌ها، Jalali، PhoneNumber، styleguide |
| 02 | ورود با موبایل (OTP) | 🟨 | step/02-otp-login | — | کد پیاده‌سازی شده؛ منتظر migrate + تست |
| 03 | نوع حساب و Entitlement | ⬜ | | | |
| 04 | سازمان مجازی و اعضا | ⬜ | | | |
| 05 | رویداد (هسته) | ⬜ | | | |
| 06 | اعضا و نقش‌های رویداد | ⬜ | | | |
| 07 | کمیته‌ها | ⬜ | | | |
| 08 | وظایف و زمان‌بندی | ⬜ | | | |
| 09 | بودجه و درآمد | ⬜ | | | |
| 10 | ریسک | ⬜ | | | |
| 11 | اسپانسرینگ و بازاریابی | ⬜ | | | |
| 12 | مسابقات | ⬜ | | | |
| 13 | داشبورد و اعلان | ⬜ | | | |
| 14 | خروجی گرفتن | ⬜ | | | |
| 15 | پایگاه دانش: آپلود/استخراج | ⬜ | | | |
| 16 | Chunk/Embedding/جست‌وجو | ⬜ | | | |
| 17 gross vis | Prompt Composer و AI Planner | ⬜ | | | |
| 18 | پیشنهاد AI ← تأیید ← وظیفه | ⬜ | | | |
| 19 | ارزیابی پسارویداد | ⬜ | | | |
| 20 | پنل ادمین و امنیت | ⬜ | | | |
| 21 | PWA و بهینه‌سازی | ⬜ | | | |
| 22 | استقرار و پایلوت | ⬜ | | | |

---

## ۳. پیش‌فرض‌های محصول (قابل تغییر توسط امیر)

| کلید | کاربر عادی | کاربر سازمانی |
|---|---|---|
| active_events | 1 | نامحدود |
| committees_per_event | 3 | نامحدود |
| members_per_event | 10 | نامحدود |
| tasks_per_event | 50 | نامحدود |
| organizations | 0 | 3 |
| export | PDF ساده | PDF/Word/Excel |
| مسابقات، اسپانسرینگ | ندارد | دارد |
| پایگاه دانش و AI | ندارد | دارد (با سهمیه‌ی ماهانه) |

---

## ۴. Decision Log (تصمیم‌های معماری)

| تاریخ | تصمیم | دلیل | جایگزین‌های ردشده | مرحله |
|---|---|---|---|---|
| ۱۴۰۵/۰۸/۲۹ | استفاده از SQLite تا Step 16 | سادگی توسعه محلی با Herd | PostgreSQL از ابتدا | 00 |
| ۱۴۰۵/۰۸/۲۹ | سطح Larastan = 5 | تعادل بین سخت‌گیری و سرعت اولیه | سطح بالاتر | 00 |
| ۱۴۰۵/۰۸/۲۹ | فونت Vazirmatn self-hosted | بدون CDN، کنترل کامل | CDN / Bunny fonts | 01 |
| ۱۴۰۵/۰۸/۲۹ | date-picker ساده متنی (شمسی) | سادگی MVP؛ بعداً تقویم پیشرفته | پکیج سنگین تقویم | 01 |
| ۱۴۰۵/۰۸/۲۹ | ورود فقط با OTP موبایل، بدون رمز | سادگی برای کاربر ایرانی | ایمیل/رمز | 02 |
| ۱۴۰۵/۰۸/۲۹ | SMS driver پیش‌فرض: log | تا انتخاب سرویس واقعی | — | 02 |
| — | محدودیت‌ها در `config/plans.php` و `EntitlementService` | تغییر سقف بدون تغییر کد | `if` پراکنده | 03 |
| — | مبلغ‌ها به ریال، عدد صحیح | جلوگیری از خطای اعشاری | float/decimal | 09 |
| — | قرعه و جدول مسابقات با الگوریتم قطعی | قابل ممیزی و تست | استفاده از AI | 12 |
| — | AI فقط پیشنهاد می‌دهد؛ اجرا پس از تأیید انسان | کنترل کیفیت و مسئولیت | اجرای خودکار | 18 |

### تصمیم‌های باز
- [ ] سرویس پیامک ایرانی (کاوه‌نگار / ملی‌پیامک / sms.ir) — فعلاً LogSmsGateway
- [ ] کاهش حساب سازمانی به عادی مجاز است؟ (Step 03)
- [ ] RAG داخل Laravel یا سرویس FastAPI جدا؟ (Step 15)
- [ ] سیاست آپلود و مجوز منابع (Step 15)
- [ ] مدل embedding و مدل زبانی (Step 16/17)
- [ ] کتابخانه‌ی PDF فارسی (Step 14)

---

## ۵. Manifest فایل‌های مخزن

### پیکربندی و ریشه
composer.json                          [Step 01] + hekmatinasser/verta + helpers autoload
.env.example                           [Step 02] + SMS_DRIVER
config/app.php                         [Step 00] Asia/Tehran + fa
config/sms.php                         [Step 02]
phpstan.neon                           [Step 00]
pint.json                              [Step 00]
.github/workflows/ci.yml               [Step 00]
vite.config.js                         [Step 01]

### Auth / OTP
app/Models/User.php                    [Step 02] phone-based, soft deletes, uuid
app/Models/OtpCode.php                 [Step 02]
app/Services/OtpService.php            [Step 02]
app/Services/Sms/SmsGateway.php        [Step 02]
app/Services/Sms/LogSmsGateway.php     [Step 02]
app/Services/Sms/NullSmsGateway.php    [Step 02]
app/Livewire/Auth/PhoneLogin.php       [Step 02]
app/Livewire/Auth/VerifyOtp.php        [Step 02]
app/Livewire/Auth/CompleteProfile.php  [Step 02]
app/Console/Commands/PruneOtpCodes.php [Step 02]
resources/views/livewire/auth/*        [Step 02]
lang/fa/auth.php                       [Step 02]

### Support (Step 01)
app/Support/PhoneNumber.php
app/Support/Jalali.php
app/Support/helpers.php

### Migrationها
- 0001_01_01_000000_create_users_table (بازنویسی‌شده: phone, uuid, softDeletes)
- 0001_01_01_000001_create_cache_table
- 0001_01_01_000002_create_jobs_table
- 2026_11_19_000001_create_otp_codes_table

### Routeها
- `/` home
- `/login` auth.login (guest)
- `/verify` auth.verify (guest)
- `/complete-profile` auth.complete-profile (auth)
- `POST /logout` auth.logout (auth)
- `/styleguide` local only

### Env keyها
APP_*, DB_*, SMS_DRIVER

### پکیج‌ها
- laravel/framework ^13.17
- livewire/livewire
- hekmatinasser/verta
- pestphp/pest + pestphp/pest-plugin-laravel
- larastan/larastan
- laravel/pint
- tailwindcss ^4

---

## ۶. مشکلات شناخته‌شده و بدهی فنی

| # | شرح | شدت | مرحله‌ی کشف | وضعیت |
|---|---|---|---|---|
| 1 | فایل‌های فونت Vazirmatn باید دستی در resources/fonts/vazirmatn قرار گیرند | پایین | 01 | باز |
| 2 | اخطار deprecation در Verta::instance (پکیج بالادستی) | پایین | 01 | باز |
| 3 | درایور واقعی پیامک هنوز انتخاب نشده (فعلاً log) | متوسط | 02 | باز |

---

## ۷. Changelog

## UPDATE — Step 02 — ورود با موبایل OTP — ۱۴۰۵/۰۸/۲۹
Status: PARTIAL (کد کامل؛ منتظر migrate + تست محلی)
Branch: step/02-otp-login
Commit: feat(auth): mobile OTP login - migrations, models, services, Livewire, tests
Files created: [
  database/migrations/2026_11_19_000001_create_otp_codes_table.php,
  app/Models/OtpCode.php,
  app/Services/OtpService.php,
  app/Services/Sms/SmsGateway.php,
  app/Services/Sms/LogSmsGateway.php,
  app/Services/Sms/NullSmsGateway.php,
  app/Livewire/Auth/PhoneLogin.php,
  app/Livewire/Auth/VerifyOtp.php,
  app/Livewire/Auth/CompleteProfile.php,
  app/Console/Commands/PruneOtpCodes.php,
  resources/views/livewire/auth/phone-login.blade.php,
  resources/views/livewire/auth/verify-otp.blade.php,
  resources/views/livewire/auth/complete-profile.blade.php,
  lang/fa/auth.php,
  config/sms.php,
  tests/Feature/Auth/OtpLoginTest.php
]
Files modified: [
  database/migrations/0001_01_01_000000_create_users_table.php,
  app/Models/User.php,
  app/Providers/AppServiceProvider.php,
  routes/web.php,
  routes/console.php,
  database/factories/UserFactory.php,
  .env.example
]
Migrations: [users rewritten, otp_codes]
New env keys: [SMS_DRIVER]
New packages: []
Routes added: [/login, /verify, /complete-profile, POST /logout]
Decisions: [SMS driver=log تا انتخاب سرویس واقعی؛ کد هش‌شده؛ rate-limit شماره+IP]
Known issues / TODO: [انتخاب سرویس پیامک ایرانی]
Tests: نوشته شده — باید محلی اجرا شود
Next suggested step: 03 (پس از سبز شدن تست‌ها)

## UPDATE — Step 01 — زیرساخت UI/RTL/جلالی — ۱۴۰۵/۰۸/۲۹
Status: DONE
Branch: step/01-ui-rtl-jalali
...

---

## ۸. روال به‌روزرسانی بعد از هر مرحله

1. فایل‌های چت‌بات را جایگزین کن
2. دستورها را اجرا کن
3. تست دستی انجام بده
4. بلوک UPDATE را در بخش ۷ بچسبان و جداول را به‌روز کن
5. Commit و push کن
